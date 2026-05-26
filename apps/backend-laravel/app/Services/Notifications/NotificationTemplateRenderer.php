<?php

namespace App\Services\Notifications;

class NotificationTemplateRenderer
{
    /**
     * @param  array<string, mixed>  $variables
     */
    public function render(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/{{\s*([A-Za-z0-9_.-]+)\s*}}/',
            function (array $matches) use ($variables): string {
                $value = data_get($variables, $matches[1], '');

                if ($value === null) {
                    return '';
                }

                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }

                if (is_scalar($value)) {
                    return (string) $value;
                }

                return (string) json_encode($value);
            },
            $template,
        ) ?? $template;
    }
}
