<style>
    .signatures { margin-top: 13px; page-break-inside: avoid; }
    .signatures h2 { background: #8c2236; color: white; font-size: 8pt; padding: 6px; text-align: center; }
    .signature-table { border-collapse: collapse; width: 100%; margin-top: 8px; table-layout: fixed; }
    .signature-table td { border: 0; background: white !important; text-align: center; padding: 4px 6px 6px; vertical-align: bottom; }
    .signature-visual { height: 56px; border-bottom: 1px solid #666; margin-bottom: 5px; text-align: center; }
    .signature-visual img { width: 200px; height: 54px; }
    .signature-name { font-size: 6.8pt; font-weight: bold; }
    .signature-role { font-size: 6.2pt; line-height: 1.2; }
</style>
<div class="signatures">
    <h2>FIRMAS</h2>
    <table class="signature-table"><tr>
        @foreach (array_slice($signers, 0, 3) as $signer)
            <td width="33.33%">
                <div class="signature-visual">@if ($signer['image'])<img src="{{ $signer['image'] }}" alt="">@endif</div>
                <div class="signature-name">{{ $signer['name'] }}</div>
                <div class="signature-role">{{ $signer['role'] }}</div>
            </td>
        @endforeach
    </tr></table>
    <table class="signature-table"><tr><td width="16.67%"></td>
        @foreach (array_slice($signers, 3, 2) as $signer)
            <td width="33.33%">
                <div class="signature-visual">@if ($signer['image'])<img src="{{ $signer['image'] }}" alt="">@endif</div>
                <div class="signature-name">{{ $signer['name'] }}</div>
                <div class="signature-role">{{ $signer['role'] }}</div>
            </td>
        @endforeach
        <td width="16.67%"></td></tr></table>
</div>
