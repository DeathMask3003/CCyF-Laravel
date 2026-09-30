<?php

namespace App\Services;

class ProductionIntegrations
{
    public function turnstile(): bool
    {
        $environment = config('app.env');
        $appUrl = rtrim((string) config('app.url'), '/');

        return in_array($environment, ['staging', 'production'], true)
            && (bool) config('ccyf.turnstile.enabled')
            && ($environment === 'production' || str_starts_with($appUrl, 'https://'));
    }

    public function google(): bool
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        return in_array(config('app.env'), ['staging', 'production'], true)
            && (bool) config('ccyf.google_login_enabled')
            && str_starts_with($appUrl, 'https://')
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && config('services.google.redirect') === $appUrl.'/acceso/google/callback';
    }
}
