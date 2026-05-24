<?php

namespace App\Services\Notifications;

use App\Models\NotificationEvent;
use App\Models\User;
use Illuminate\Database\QueryException;

class NotificationOutbox
{
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
    ): NotificationEvent {
        $attributes = ['idempotency_key' => $idempotencyKey];
        $values = [
            'user_id' => $user?->id,
            'channel' => 'email',
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
    ): NotificationEvent {
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
}
