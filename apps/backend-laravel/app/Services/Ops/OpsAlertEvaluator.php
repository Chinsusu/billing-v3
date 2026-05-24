<?php

namespace App\Services\Ops;

use App\Models\OpsAlertEvent;
use App\Models\OpsAlertRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpsAlertEvaluator
{
    public function __construct(private readonly OpsHealthSnapshot $snapshot) {}

    public function evaluate(): int
    {
        $now = now();
        $created = 0;
        $candidates = $this->candidates($this->snapshot->data());

        OpsAlertRule::where('enabled', true)
            ->where('type', 'ops_health')
            ->get()
            ->each(function (OpsAlertRule $rule) use ($candidates, $now, &$created): void {
                foreach ($candidates as $candidate) {
                    if ($this->record($rule, $candidate, $now)) {
                        $created++;
                    }
                }

                $rule->forceFill(['last_evaluated_at' => $now])->save();
            });

        return $created;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<OpsAlertCandidate>
     */
    private function candidates(array $snapshot): array
    {
        $candidates = [];

        foreach ($snapshot['tasks'] as $task => $health) {
            if ($health['status'] === 'ok') {
                continue;
            }

            $candidates[] = new OpsAlertCandidate(
                fingerprint: "task:{$task}:{$health['status']}",
                severity: $health['status'] === 'failed' ? 'critical' : 'warning',
                title: "Scheduled task {$task} is {$health['status']}",
                message: $health['message'],
                context: [
                    'source' => 'scheduled_task',
                    'task' => $task,
                    'status' => $health['status'],
                    'fresh_minutes' => $health['freshMinutes'],
                ],
            );
        }

        foreach ([
            'provisioning' => $snapshot['provisioningQueue'],
            'provider_action' => $snapshot['providerActionQueue'],
        ] as $queue => $health) {
            if ($health['status'] === 'ok') {
                continue;
            }

            $label = str_replace('_', ' ', $queue);
            $candidates[] = new OpsAlertCandidate(
                fingerprint: "queue:{$queue}:{$health['status']}",
                severity: $health['status'] === 'failed' ? 'critical' : 'warning',
                title: ucfirst($label).' queue is '.$health['status'],
                message: "pending: {$health['pending']}, processing: {$health['processing']}, failed: {$health['failed']}",
                context: [
                    'source' => 'queue',
                    'queue' => $queue,
                    'status' => $health['status'],
                    'pending' => $health['pending'],
                    'processing' => $health['processing'],
                    'failed' => $health['failed'],
                ],
            );
        }

        if ($snapshot['overdueActiveServiceCount'] > 0) {
            $candidates[] = new OpsAlertCandidate(
                fingerprint: 'services:overdue_active:warning',
                severity: 'warning',
                title: 'Overdue active services detected',
                message: "{$snapshot['overdueActiveServiceCount']} active services are overdue.",
                context: [
                    'source' => 'services',
                    'overdue_active_service_count' => $snapshot['overdueActiveServiceCount'],
                ],
            );
        }

        if ($snapshot['enabledBankIntegrationCount'] === 0) {
            $candidates[] = new OpsAlertCandidate(
                fingerprint: 'bank_integrations:enabled:none',
                severity: 'warning',
                title: 'No enabled bank integrations',
                message: 'No enabled bank integrations are available for payment sync.',
                context: [
                    'source' => 'bank_integrations',
                    'enabled_bank_integration_count' => 0,
                ],
            );
        }

        return $candidates;
    }

    private function record(OpsAlertRule $rule, OpsAlertCandidate $candidate, CarbonInterface $now): bool
    {
        $existing = OpsAlertEvent::where('ops_alert_rule_id', $rule->id)
            ->where('fingerprint', $candidate->fingerprint)
            ->whereIn('status', ['open', 'acknowledged'])
            ->latest('last_seen_at')
            ->first();

        if ($existing && $existing->last_seen_at->gte($now->copy()->subMinutes($rule->cooldown_minutes))) {
            $existing->forceFill([
                'title' => $candidate->title,
                'message' => $candidate->message,
                'context' => $candidate->context,
                'last_seen_at' => $now,
            ])->save();

            return false;
        }

        $event = OpsAlertEvent::create([
            'ops_alert_rule_id' => $rule->id,
            'fingerprint' => $candidate->fingerprint,
            'severity' => $candidate->severity,
            'status' => 'open',
            'title' => $candidate->title,
            'message' => $candidate->message,
            'context' => $candidate->context,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'delivery_status' => $rule->webhook_url ? null : 'skipped',
        ]);

        if ($rule->webhook_url) {
            $this->deliver($rule, $event, $now);
        }

        return true;
    }

    private function deliver(OpsAlertRule $rule, OpsAlertEvent $event, CarbonInterface $now): void
    {
        $payload = [
            'event_id' => $event->id,
            'rule' => $rule->name,
            'severity' => $event->severity,
            'fingerprint' => $event->fingerprint,
            'title' => $event->title,
            'message' => $event->message,
            'context' => $event->context,
            'occurred_at' => $now->toIso8601String(),
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $headers = ['Content-Type' => 'application/json'];

        if ($rule->webhook_secret) {
            $headers['X-Billing-Signature'] = hash_hmac('sha256', $body, $rule->webhook_secret);
        }

        try {
            $response = Http::withHeaders($headers)->withBody($body, 'application/json')->post($rule->webhook_url);

            $event->forceFill([
                'delivery_status' => $response->successful() ? 'delivered' : 'failed',
                'delivery_error' => $response->successful() ? null : 'HTTP '.$response->status(),
            ])->save();
        } catch (Throwable $exception) {
            $event->forceFill([
                'delivery_status' => 'failed',
                'delivery_error' => $exception->getMessage(),
            ])->save();
        }
    }
}
