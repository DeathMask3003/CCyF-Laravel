@props(['action'])
@if(app(\App\Services\ProductionIntegrations::class)->turnstile() && filled(config('ccyf.turnstile.site_key')))
    <div class="ccyf-turnstile" data-turnstile-action="{{ $action }}">
        <div class="cf-turnstile" data-sitekey="{{ config('ccyf.turnstile.site_key') }}" data-action="{{ $action }}" data-refresh-expired="auto"></div>
    </div>
    @once
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endonce
@endif
@error('cf-turnstile-response')<p class="field-error" role="alert">{{ $message }}</p>@enderror
