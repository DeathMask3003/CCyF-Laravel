<?php

namespace App\Services;

class ProductionIntegrations
{
    public function turnstile(): bool
    {
        return config('app.env') === 'production' && (bool) config('ccyf.turnstile.enabled');
    }

    public function google(): bool
    {
        return config('app.env') === 'production'
            && (bool) config('ccyf.google_login_enabled')
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }
}
