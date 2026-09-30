@php($visual = $signer['visual'] ?? null)
<div class="signature-visual">
    @if (($visual['type'] ?? null) === 'imagen')
        <img src="{{ $visual['image'] }}" alt="">
    @elseif (($visual['type'] ?? null) === 'efirma')
        <div class="signature-sat">
            <strong>{{ $visual['expired'] ? 'e.firma SAT vencida' : 'e.firma SAT' }} · Serie: {{ $visual['serial'] }}</strong><br>
            <span class="fingerprint">SHA-256: {{ substr($visual['fingerprint'], 0, 32) }}</span><br>
            <span class="fingerprint">{{ substr($visual['fingerprint'], 32) }}</span><br>
            Vigente hasta: {{ $visual['validTo'] }}
        </div>
    @else
        <div class="signature-line"></div>
    @endif
</div>
<div class="signature-name">{{ $signer['name'] }}</div>
<div class="signature-role">{{ $signer['role'] }}</div>
