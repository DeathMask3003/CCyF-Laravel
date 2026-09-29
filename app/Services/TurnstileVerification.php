<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class TurnstileVerification
{
    public function __construct(private readonly ProductionIntegrations $integrations) {}

    public function verify(Request $request, string $action): void
    {
        if (! $this->integrations->turnstile()) {
            return;
        }

        $token = $request->input('cf-turnstile-response');
        $secret = config('ccyf.turnstile.secret_key');
        $hostname = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (! is_string($token) || $token === '' || strlen($token) > 2048 || ! filled($secret) || ! $hostname) {
            $this->fail();
        }

        try {
            $response = Http::asForm()->timeout(8)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);
        } catch (Throwable) {
            $this->fail();
        }

        if (! $response->successful() || $response->json('success') !== true
            || $response->json('action') !== $action
            || $response->json('hostname') !== $hostname) {
            $this->fail();
        }
    }

    private function fail(): never
    {
        throw ValidationException::withMessages([
            'cf-turnstile-response' => 'No se pudo verificar la seguridad. Inténtalo de nuevo.',
        ]);
    }
}
