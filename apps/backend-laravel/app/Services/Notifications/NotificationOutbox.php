<?php

namespace App\Services\Notifications;

use App\Models\NotificationEvent;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Database\QueryException;

class NotificationOutbox
{
    public function __construct(
        private readonly NotificationPreferenceService $preferences,
        private readonly NotificationTemplateRenderer $renderer,
    ) {}

    public function enqueue(
        ?User $user,
        string $type,
        string $recipientEmail,
        string $subject,
        string $body,
        string $sourceType,
        ?string $sourceId,
        string $idempotencyKey,
        array $payload = [],
    ): ?NotificationEvent {
        $channel = 'email';

        if (! $this->preferences->enabled($user, $type, $channel)) {
            return null;
        }

        [$subject, $body] = $this->renderContent(
            $user,
            $type,
            $channel,
            $recipientEmail,
            $subject,
            $body,
            $sourceType,
            $sourceId,
            $payload,
        );

        $attributes = ['idempotency_key' => $idempotencyKey];
        $values = [
            'user_id' => $user?->id,
            'channel' => $channel,
            'type' => $type,
            'recipient_email' => $recipientEmail,
            'subject' => $subject,
            'body_text' => $body,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'available_at' => now(),
            'sent_at' => null,
            'last_error' => null,
            'payload' => $payload,
        ];

        try {
            return NotificationEvent::firstOrCreate($attributes, $values);
        } catch (QueryException $exception) {
            $existing = NotificationEvent::where('idempotency_key', $idempotencyKey)->first();

            if ($existing instanceof NotificationEvent) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function enqueueOperator(
        string $type,
        string $subject,
        string $body,
        string $sourceType,
        ?string $sourceId,
        string $idempotencyKey,
        array $payload = [],
    ): ?NotificationEvent {
        return $this->enqueue(
            null,
            $type,
            (string) config('mail.from.address'),
            $subject,
            $body,
            $sourceType,
            $sourceId,
            $idempotencyKey,
            $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string}
     */
    private function renderContent(
        ?User $user,
        string $type,
        string $channel,
        string $recipientEmail,
        string $fallbackSubject,
        string $fallbackBody,
        string $sourceType,
        ?string $sourceId,
        array $payload,
    ): array {
        $template = NotificationTemplate::where('type', $type)
            ->where('channel', $channel)
            ->where('enabled', true)
            ->first();

        if (! $template instanceof NotificationTemplate) {
            return [$fallbackSubject, $fallbackBody];
        }

        $variables = array_replace_recursive($payload, [
            'recipient_email' => $recipientEmail,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'type' => $type,
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
            ],
        ]);

        $subject = trim($this->renderer->render($template->subject_template, $variables));
        $body = trim($this->renderer->render($template->body_template, $variables));

        return [
            $subject === '' ? $fallbackSubject : $subject,
            $body === '' ? $fallbackBody : $body,
        ];
    }
}
