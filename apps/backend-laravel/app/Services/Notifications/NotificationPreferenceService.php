<?php

namespace App\Services\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;

class NotificationPreferenceService
{
    public function __construct(private readonly NotificationTemplateCatalog $catalog) {}

    public function enabled(?User $user, string $type, string $channel = 'email'): bool
    {
        if (! $user instanceof User) {
            return true;
        }

        if (! array_key_exists($type, $this->catalog->customerTypes())) {
            return true;
        }

        $preference = NotificationPreference::where('user_id', $user->id)
            ->where('type', $type)
            ->where('channel', $channel)
            ->first();

        return $preference?->enabled ?? true;
    }

    /**
     * @param  array<int, string>  $enabledTypes
     */
    public function sync(User $user, array $enabledTypes, string $channel = 'email'): void
    {
        $enabled = array_flip($enabledTypes);

        foreach (array_keys($this->catalog->customerTypes()) as $type) {
            NotificationPreference::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => $type,
                    'channel' => $channel,
                ],
                ['enabled' => array_key_exists($type, $enabled)],
            );
        }
    }

    /**
     * @return array<string, bool>
     */
    public function statesFor(User $user, string $channel = 'email'): array
    {
        $states = array_fill_keys(array_keys($this->catalog->customerTypes()), true);
        $preferences = NotificationPreference::where('user_id', $user->id)
            ->where('channel', $channel)
            ->get(['type', 'enabled']);

        foreach ($preferences as $preference) {
            if (array_key_exists($preference->type, $states)) {
                $states[$preference->type] = $preference->enabled;
            }
        }

        return $states;
    }
}
