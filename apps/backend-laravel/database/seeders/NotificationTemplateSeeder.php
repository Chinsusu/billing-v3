<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use App\Services\Notifications\NotificationTemplateCatalog;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(NotificationTemplateCatalog $catalog): void
    {
        foreach ($catalog->defaults() as $definition) {
            NotificationTemplate::firstOrCreate(
                [
                    'type' => $definition['type'],
                    'channel' => $definition['channel'],
                ],
                [
                    'name' => $definition['name'],
                    'subject_template' => $definition['subject_template'],
                    'body_template' => $definition['body_template'],
                    'variables' => $definition['variables'],
                    'enabled' => true,
                ],
            );
        }
    }
}
