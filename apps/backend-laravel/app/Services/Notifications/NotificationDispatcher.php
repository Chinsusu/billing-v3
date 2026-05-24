<?php

namespace App\Services\Notifications;

use App\Models\NotificationEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationDispatcher
{
    public function __construct(private readonly ServiceExpiryWarningNotifier $expiryWarnings) {}

    /**
     * @return array{sent: int, retried: int, failed: int}
     */
    public function send(int $limit = 50): array
    {
        $this->expiryWarnings->enqueueDueWarnings();

        $sent = 0;
        $retried = 0;
        $failed = 0;
        $limit = max(1, $limit);

        for ($i = 0; $i < $limit; $i++) {
            $event = $this->claimNext();
            if (! $event instanceof NotificationEvent) {
                break;
            }

            try {
                Mail::raw($event->body_text, function ($message) use ($event): void {
                    $message->to($event->recipient_email)->subject($event->subject);
                });

                $event->forceFill([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'last_error' => null,
                ])->save();
                $sent++;
            } catch (Throwable $exception) {
                if ($event->attempts >= $event->max_attempts) {
                    $event->forceFill([
                        'status' => 'failed',
                        'last_error' => $exception->getMessage(),
                        'available_at' => null,
                    ])->save();
                    $failed++;
                } else {
                    $event->forceFill([
                        'status' => 'pending',
                        'last_error' => $exception->getMessage(),
                        'available_at' => now()->addSeconds(60),
                    ])->save();
                    $retried++;
                }
            }
        }

        return ['sent' => $sent, 'retried' => $retried, 'failed' => $failed];
    }

    private function claimNext(): ?NotificationEvent
    {
        return DB::transaction(function (): ?NotificationEvent {
            $event = NotificationEvent::query()
                ->where('status', 'pending')
                ->where(function ($query): void {
                    $query->whereNull('available_at')
                        ->orWhere('available_at', '<=', now());
                })
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $event instanceof NotificationEvent) {
                return null;
            }

            $event->forceFill([
                'status' => 'sending',
                'attempts' => $event->attempts + 1,
                'available_at' => null,
            ])->save();

            return $event->refresh();
        });
    }
}
