<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;
use NumberFormatter;

class ContractDocuments
{
    public const FONT_FAMILIES = [
        'dejavusans' => 'DejaVu Sans',
        'dejavuserif' => 'DejaVu Serif',
        'freesans' => 'FreeSans',
    ];

    private array $templateCache = [];
    private array $priceCache = [];

    public const VARIABLES = [
        'permisionario' => 'Permisionario', 'plantel' => 'Plantel',
        'direccion_plantel' => 'Dirección del plantel', 'correo' => 'Correo',
        'telefono' => 'Teléfono', 'servicio' => 'Servicio',
        'nro_contrato' => 'Número de contrato', 'nro_expediente' => 'Folio del expediente',
        'fecha_hoy' => 'Fecha de elaboración', 'fecha_ini' => 'Inicio de vigencia',
        'fecha_fin' => 'Fin de vigencia', 'monto' => 'Monto mensual',
        'monto_letras' => 'Monto en letras', 'direccion_permisionario' => 'Domicilio del permisionario',
        'ine_permisionario' => 'INE', 'contacto_alterno' => 'Contacto alterno',
        'telefono_alterno' => 'Teléfono alterno', 'precio_carta' => 'Copia carta',
        'precio_oficio' => 'Copia oficio', 'precio_carta_mayor' => 'Carta más de 30',
        'precio_oficio_mayor' => 'Oficio más de 30', 'mtc' => 'Carta más de 30',
        'mto' => 'Oficio más de 30', 'tabla_precios' => 'Tabla de precios de la propuesta',
    ];

    public function template(int $serviceId): ?object
    {
        if (array_key_exists($serviceId, $this->templateCache)) return $this->templateCache[$serviceId];
        $local = DB::table('ccyf_contract_templates')->where('legacy_trami_id', $serviceId)->orderByDesc('id')->first();
        if ($local) return $this->templateCache[$serviceId] = (object) ['id' => $local->id, 'body' => $local->body, 'source' => 'local',
            'institution_signer' => $local->institution_signer, 'institution_role' => $local->institution_role,
            'witness_signer' => $local->witness_signer, 'witness_role' => $local->witness_role,
            'additional_signers' => json_decode((string) $local->additional_signers, true) ?: [],
            'font_family' => $local->font_family, 'font_size' => (float) $local->font_size,
            'updated_at' => $local->updated_at];

        $legacy = DB::connection('legacy')->table('tm_plantilla_contrato')
            ->where('trami_id', $serviceId)->where('est', 0)->orderByDesc('plantilla_id')->first();
        if (! $legacy || trim((string) $legacy->plantilla_body) === '') return $this->templateCache[$serviceId] = null;
        return $this->templateCache[$serviceId] = (object) ['id' => null, 'body' => $legacy->plantilla_body, 'source' => 'historica',
            'institution_signer' => null, 'institution_role' => null,
            'witness_signer' => null, 'witness_role' => null,
            'additional_signers' => [],
            'font_family' => 'dejavusans', 'font_size' => 9.0,
            'updated_at' => $legacy->fech_modif ?: $legacy->fech_crea];
    }

    public function issues(object $row): array
    {
        $template = $this->template($row->service_id);
        if (! $template) return ['Plantilla de contrato'];
        $issues = [];
        if ($template->source !== 'local') $issues[] = 'Revisión jurídica de la plantilla histórica';
        if (! array_key_exists($template->font_family, self::FONT_FAMILIES)) {
            $issues[] = 'Fuente de contrato no disponible en este servidor';
        }
        if ($template->source === 'local') {
            foreach (['institution_signer', 'institution_role', 'witness_signer', 'witness_role'] as $field) {
                if (! trim((string) $template->{$field})) $issues[] = 'Datos de firmantes';
            }
            foreach ([0, 1] as $position) {
                foreach (['title', 'name', 'role'] as $field) {
                    if (! trim((string) ($template->additional_signers[$position][$field] ?? ''))) {
                        $issues[] = 'Datos de las cinco firmas';
                        break 2;
                    }
                }
            }
        }
        if (preg_match('/\*{5,}/', strip_tags($template->body))) {
            $issues[] = 'Texto con asteriscos pendientes en la plantilla';
        }
        $values = $this->values($row);
        preg_match_all('/\{([a-z_]+)\}/', $template->body, $tokens);
        foreach (array_unique($tokens[1]) as $token) {
            if (! array_key_exists($token, $values) || trim((string) $values[$token]) === '') {
                $issues[] = 'Campo {'.$token.'} sin valor';
            }
        }
        return $issues;
    }

    public function editorHtml(string $body): string
    {
        $body = preg_replace('/<body\b[^>]*>(.*?)<\/body>/si', '$1', $body) ?? $body;
        $body = preg_replace('/<style\b[^>]*>.*?<\/style>|<script\b[^>]*>.*?<\/script>|<!--.*?-->/si', '', $body) ?? $body;
        return $this->clean($body);
    }

    public function render(object $row, bool $draft = true): string
    {
        $template = $this->template($row->service_id);
        abort_unless($template, 422, 'No hay una plantilla de contrato para este servicio.');
        $values = $this->values($row);
        $html = $this->editorHtml((string) $template->body);
        $replacements = [];
        foreach ($values as $key => $value) {
            $replacements['{'.$key.'}'] = ' '.e($value).' ';
        }
        $replacements['{tabla_precios}'] = $this->priceTableHtml($row);
        $html = strtr($html, $replacements);
        $html = preg_replace('/(?:&nbsp;|\x{00A0})+/u', ' ', $html) ?? $html;
        abort_unless(array_key_exists($template->font_family, self::FONT_FAMILIES), 422,
            'La fuente de esta plantilla no está disponible en el servidor.');
        $family = $template->font_family;
        $size = min(14, max(7, (float) $template->font_size));
        $html = '<!doctype html><html lang="es"><head><meta charset="UTF-8"><style>
            @page { margin: 21mm 19mm 22mm; }
            body { font-family: '.$family.'; color:#202020; font-size:'.$size.'pt; line-height:1.4; }
            p { margin:0 0 7px; text-align:justify; } h1,h2,h3 { margin:0 0 9px; text-align:center; }
            table { width:100%; border-collapse:collapse; } td,th { vertical-align:top; padding:3px; }
            .prices td,.prices th { border:1px solid #bbb; padding:6px; } .prices th { background:#f1ecee; }
            img { max-width:170mm; height:auto; } .draft { color:#8d2d49; font-weight:bold;
                border:1px solid #d9b3bf; padding:7px 12px; margin-bottom:15px; text-align:center; }
            .signatures { margin-top:25px; width:100%; }
            .signatures tr { page-break-inside:avoid; }
            .signatures td { width:50%; text-align:center; padding:15px 16px 14px; }
            .signatures .last-signature { width:50%; margin:0 auto; }
            .signature-title { font-weight:bold; font-size:8pt; margin-bottom:5px; }
            .signature-space { line-height:13pt; }
            .line { border-top:1px solid #333; padding-top:6px; font-weight:bold; }
            .signature-role { font-size:8pt; font-weight:normal; }
            .contract-note { margin-top:13px; padding-top:6px; border-top:1px solid #bbb;
                color:#666; font-size:7pt; text-align:center; }
            </style></head><body>'
            .($draft ? '<div class="draft">BORRADOR PARA REVISIÓN · No enviar ni firmar</div>' : '')
            .$html.$this->signaturesHtml($template, (string) $row->name)
            .'<div class="contract-note">No. de Registro CCyF: '.e((string) $row->folio)
            .' &nbsp;|&nbsp; Convocatoria: '.e((string) $row->convocation)
            .' &nbsp;|&nbsp; Generado: '.e((string) $values['fecha_hoy']).'</div>'
            .'</body></html>';

        $directory = storage_path('app/mpdf');
        if (! is_dir($directory)) mkdir($directory, 0775, true);
        $pdf = new Mpdf(['mode' => 'utf-8', 'format' => 'Letter', 'tempDir' => $directory,
            'default_font' => $family, 'default_font_size' => $size]);
        $pdf->showImageErrors = false;
        $pdf->WriteHTML($html);
        return $pdf->Output('', 'S');
    }

    private function signaturesHtml(object $template, string $permittee): string
    {
        $extra = $template->additional_signers;
        $signers = [
            ['title' => 'POR EL “COBAEM”', 'name' => $template->institution_signer ?: 'FIRMA PENDIENTE',
                'role' => $template->institution_role ?: 'CARGO PENDIENTE'],
            ['title' => 'POR EL “PERMISIONARIO”', 'name' => $permittee, 'role' => ''],
            ['title' => 'TESTIGOS', 'name' => $template->witness_signer ?: 'FIRMA PENDIENTE',
                'role' => $template->witness_role ?: 'CARGO PENDIENTE'],
            ['title' => $extra[0]['title'] ?? 'FIRMANTE 4', 'name' => $extra[0]['name'] ?? 'FIRMA PENDIENTE',
                'role' => $extra[0]['role'] ?? 'CARGO PENDIENTE'],
            ['title' => $extra[1]['title'] ?? 'FIRMANTE 5', 'name' => $extra[1]['name'] ?? 'FIRMA PENDIENTE',
                'role' => $extra[1]['role'] ?? 'CARGO PENDIENTE'],
        ];
        $html = '<table class="signatures"><tbody>';
        foreach ($signers as $index => $signer) {
            if ($index % 2 === 0) $html .= '<tr>';
            if ($index === 4) $html .= '<td colspan="2"><div class="last-signature">';
            else $html .= '<td>';
            $html .= '<div class="signature-title">'.e((string) $signer['title']).'</div>'
                .'<div class="signature-space">&nbsp;<br>&nbsp;<br>&nbsp;</div><div class="line">'.e((string) $signer['name'])
                .'<br><span class="signature-role">'.e((string) $signer['role']).'</span></div>';
            if ($index === 4) $html .= '</div></td>';
            else $html .= '</td>';
            if ($index % 2 === 1 || $index === 4) $html .= '</tr>';
        }
        return $html.'</tbody></table>';
    }

    public function values(object $row): array
    {
        $formatter = new NumberFormatter('es_MX', NumberFormatter::SPELLOUT);
        $amount = (float) ($row->amount ?? 0);
        $cents = (int) round(($amount - floor($amount)) * 100);
        $today = now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
        $values = [
            'permisionario' => (string) $row->name,
            'plantel' => (string) $row->campus,
            'direccion_plantel' => (string) $row->campus_address,
            'correo' => (string) $row->email, 'telefono' => (string) $row->phone,
            'servicio' => $row->service === 'cafeteria' ? 'Cafetería' : 'Centro de Fotocopiado',
            'nro_contrato' => 'COBAEM-DRMS-'.str_pad((string) $row->id, 4, '0', STR_PAD_LEFT).'-'.now()->year,
            'nro_expediente' => (string) $row->folio, 'fecha_hoy' => $today,
            'fecha_ini' => $row->starts ? \Carbon\Carbon::parse($row->starts)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') : '',
            'fecha_fin' => $row->ends ? \Carbon\Carbon::parse($row->ends)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') : '',
            'monto' => '$'.number_format($amount, 2, '.', ',').' MXN',
            'monto_letras' => mb_strtoupper($formatter->format((int) floor($amount))).' PESOS '.sprintf('%02d', $cents).'/100 M.N.',
            'direccion_permisionario' => (string) $row->address,
            'ine_permisionario' => (string) $row->ine,
            'contacto_alterno' => (string) $row->alternate,
            'telefono_alterno' => (string) $row->alternate_phone,
        ];
        $prices = $this->photocopyPrices($row);
        $values['tabla_precios'] = $this->proposalPrices($row) ? 'Precios disponibles' : '';
        return $values + $prices;
    }

    private function photocopyPrices(object $row): array
    {
        $values = ['carta'=>null, 'oficio'=>null, 'mtc'=>null, 'mto'=>null];
        if ($row->service === 'fotocopiado') {
            foreach ($this->proposalPrices($row) as $price) {
                $name = mb_strtolower(\Illuminate\Support\Str::ascii($price->nombre));
                if (str_contains($name, 'recicl')) continue;
                $key = match (true) {
                    $name === 'mtc', str_contains($name, 'mayoreo tamano carta') => 'mtc',
                    $name === 'mto', str_contains($name, 'mayoreo tamano oficio') => 'mto',
                    $name === 'carta', $name === 'tamano carta' => 'carta',
                    $name === 'oficio', $name === 'tamano oficio' => 'oficio',
                    default => null,
                };
                if ($key) $values[$key] = '$'.number_format((float) $price->precio, 2, '.', ',');
            }
        }
        return ['precio_carta'=>$values['carta'] ?? '', 'precio_oficio'=>$values['oficio'] ?? '',
            'precio_carta_mayor'=>$values['mtc'] ?? '', 'precio_oficio_mayor'=>$values['mto'] ?? '',
            'mtc'=>$values['mtc'] ?? '', 'mto'=>$values['mto'] ?? ''];
    }

    private function proposalPrices(object $row): array
    {
        if (array_key_exists($row->key, $this->priceCache)) return $this->priceCache[$row->key];
        return $this->priceCache[$row->key] = app(PrevaluationRecords::class)->prices((object) [
            'origen' => $row->origin, 'registro_id' => $row->id, 'servicio' => $row->service,
        ])->all();
    }

    private function priceTableHtml(object $row): string
    {
        $prices = $this->proposalPrices($row);
        if (! $prices) return '';
        $html = '<table class="prices"><thead><tr><th>Producto o servicio</th><th>Precio</th><th>Unidad</th></tr></thead><tbody>';
        foreach ($prices as $price) {
            $html .= '<tr><td>'.e($price->nombre).'</td><td>$'.number_format((float) $price->precio, 2, '.', ',')
                .'</td><td>'.e($price->unidad ?: '—').'</td></tr>';
        }
        return $html.'</tbody></table>';
    }

    private function clean(string $html): string
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"?><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $body = $document->getElementsByTagName('body')->item(0);
        if (! $body) return '';
        $this->cleanNode($body);
        $result = '';
        foreach ($body->childNodes as $child) $result .= $document->saveHTML($child);
        return $result;
    }

    private function cleanNode(DOMNode $node): void
    {
        $allowed = ['p','br','b','strong','i','em','u','s','span','div','h1','h2','h3','h4',
            'ul','ol','li','table','thead','tbody','tr','td','th','img','hr','sup','sub'];
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                if (! in_array(strtolower($child->tagName), $allowed, true)) {
                    $this->cleanNode($child);
                    while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                    $node->removeChild($child);
                    continue;
                }
                $alignment = null;
                $alignable = in_array(strtolower($child->tagName),
                    ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'li', 'td', 'th'], true);
                foreach (iterator_to_array($child->attributes) as $attribute) {
                    $name = strtolower($attribute->name);
                    $value = $attribute->value;
                    if ($alignable && $name === 'style'
                        && preg_match('/(?:^|;)\s*text-align\s*:\s*(left|center|right|justify)\s*(?:;|$)/i', $value, $match)) {
                        $alignment = strtolower($match[1]);
                    }
                    if ($alignable && $name === 'align'
                        && in_array(strtolower($value), ['left', 'center', 'right', 'justify'], true)
                        && $alignment === null) {
                        $alignment = strtolower($value);
                    }
                    $safeSpan = in_array($name, ['colspan', 'rowspan'], true) && ctype_digit($value) && (int) $value <= 12;
                    $safeImage = $name === 'src' && $child->tagName === 'img'
                        && preg_match('#^data:image/(png|jpeg|gif);base64,[A-Za-z0-9+/=]+$#', $value);
                    if (! $safeSpan && ! $safeImage) $child->removeAttributeNode($attribute);
                }
                if ($alignment !== null) $child->setAttribute('style', 'text-align:'.$alignment);
                $this->cleanNode($child);
            } elseif (! in_array($child->nodeType, [XML_TEXT_NODE], true)) {
                $node->removeChild($child);
            }
        }
    }
}
