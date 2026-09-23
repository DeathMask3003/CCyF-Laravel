<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;

class ConvocationDocuments
{
    public const FONT_FAMILIES = [
        'dejavusans' => 'DejaVu Sans',
        'dejavuserif' => 'DejaVu Serif',
        'freesans' => 'FreeSans',
    ];

    public function templateFor(object $service): object
    {
        $saved = DB::table('ccyf_convocatoria_plantillas')->where('servicio_id', $service->id)->first();
        if ($saved) {
            return $saved;
        }
        return (object) [
            'cuerpo_html' => $this->defaultTemplate($service->nombre),
            'font_family' => 'dejavusans', 'font_size' => 9,
            'updated_at' => null,
        ];
    }

    public function defaultTemplate(string $serviceName): string
    {
        $slug = str_contains(mb_strtolower(\Illuminate\Support\Str::ascii($serviceName)), 'fotocopi')
            ? 'fotocopiado' : 'cafeteria';
        return $this->cleanHtml(file_get_contents(resource_path('convocation_templates/'.$slug.'.html')));
    }

    public function campuses(int $convocationId, bool $onlyActive = false): Collection
    {
        $service = DB::table('ccyf_convocatorias as convocatoria')
            ->join('ccyf_tipos_servicio as servicio', 'servicio.id', '=', 'convocatoria.servicio_id')
            ->where('convocatoria.id', $convocationId)
            ->first(['servicio.id', 'servicio.nombre', 'servicio.plantilla']);
        if (! $service) {
            return collect();
        }

        $photocopy = $service->plantilla === 'fotocopiado'
            || str_contains(mb_strtolower(\Illuminate\Support\Str::ascii($service->nombre)), 'fotocopi');
        $suffix = $photocopy ? '_foto' : '';

        $campuses = DB::connection('legacy')->table('tm_areas')
            ->where(function ($query): void {
                $query->where('area_nom', 'like', 'Plantel %')
                    ->orWhere('area_nom', 'like', 'Cemsad %');
            })
            ->when($onlyActive, fn ($query) => $query->where('est', 1))
            ->orderBy('area_nom')
            ->get(['area_id as id', 'area_nom as nombre', 'area_correo as correo',
                'direccion_plantel as direccion', 'est as activo',
                'espacio'.$suffix.' as espacio', 'matricula'.$suffix.' as matricula',
                'monto'.$suffix.' as monto', 'garantia'.$suffix.' as garantia']);

        $configured = DB::table('ccyf_plantel_servicios as datos')
            ->join('ccyf_planteles as plantel', 'plantel.id', '=', 'datos.plantel_id')
            ->where('datos.servicio_id', $service->id)
            ->whereIn('plantel.legacy_area_id', $campuses->pluck('id'))
            ->get(['plantel.legacy_area_id', 'datos.espacio', 'datos.matricula',
                'datos.monto', 'datos.garantia'])
            ->keyBy('legacy_area_id');

        return $campuses->map(function ($campus) use ($configured) {
            $saved = $configured->get($campus->id);
            foreach (['espacio', 'matricula', 'monto', 'garantia'] as $field) {
                if (($campus->{$field} === null || $campus->{$field} === '') && $saved) {
                    $campus->{$field} = $saved->{$field};
                }
            }
            return $campus;
        });
    }

    public function cleanHtml(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div id="ccyf-content">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementById('ccyf-content');
        if (! $root) {
            return '';
        }
        foreach (iterator_to_array($root->childNodes) as $node) {
            $this->cleanNode($node);
        }
        $result = '';
        foreach ($root->childNodes as $node) {
            $result .= $document->saveHTML($node);
        }
        return trim($result);
    }

    private function cleanNode(DOMNode $node): void
    {
        if (! $node instanceof DOMElement) {
            return;
        }
        $allowed = ['p', 'div', 'br', 'strong', 'b', 'em', 'i', 'u', 'h2', 'h3', 'ol', 'ul', 'li',
            'blockquote', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'a'];
        $name = strtolower($node->tagName);
        if (in_array($name, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'img', 'svg'], true)) {
            $node->parentNode?->removeChild($node);
            return;
        }
        foreach (iterator_to_array($node->childNodes) as $child) {
            $this->cleanNode($child);
        }
        if (! in_array($name, $allowed, true)) {
            while ($node->firstChild) {
                $node->parentNode?->insertBefore($node->firstChild, $node);
            }
            $node->parentNode?->removeChild($node);
            return;
        }
        $href = $name === 'a' ? trim($node->getAttribute('href')) : '';
        $alignment = null;
        if (in_array($name, ['p', 'div', 'h2', 'h3', 'li', 'blockquote', 'td', 'th'], true)) {
            if (preg_match('/(?:^|;)\s*text-align\s*:\s*(left|center|right|justify)\s*(?:;|$)/i',
                $node->getAttribute('style'), $match)) {
                $alignment = strtolower($match[1]);
            } elseif (in_array(strtolower($node->getAttribute('align')),
                ['left', 'center', 'right', 'justify'], true)) {
                $alignment = strtolower($node->getAttribute('align'));
            }
        }
        foreach (iterator_to_array($node->attributes) as $attribute) {
            $node->removeAttribute($attribute->name);
        }
        if ($alignment !== null) $node->setAttribute('style', 'text-align:'.$alignment);
        if ($name === 'a' && preg_match('~^https?://~i', $href)) {
            $node->setAttribute('href', $href);
        }
    }

    public function detailsWithAnnex(string $details, string $annex): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div id="ccyf-details">'.$details.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $annexDocument = new DOMDocument('1.0', 'UTF-8');
        $annexDocument->loadHTML('<?xml encoding="utf-8"?><div id="ccyf-annex">'.$annex.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('ccyf-details');
        $annexRoot = $annexDocument->getElementById('ccyf-annex');
        if (! $root || ! $annexRoot) return $details.$annex;

        $requirements = null;
        $signature = null;
        foreach ($root->getElementsByTagName('*') as $node) {
            if (! in_array(strtolower($node->tagName), ['p', 'h2', 'h3'], true)) continue;
            $label = mb_strtoupper(preg_replace('/[\s\x{00A0}]+/u', '', $node->textContent) ?? '');
            if ($requirements === null && str_starts_with($label, 'REQUISITOS') && mb_strlen($label) < 100) {
                $requirements = $node;
            }
            if ($signature === null && str_starts_with($label, 'ATENTAMENTE') && mb_strlen($label) < 100) {
                $signature = $node;
            }
        }

        // Historical templates have only the office name, without a space for a handwritten signature.
        if ($signature && mb_strtoupper(preg_replace('/[\s\x{00A0}]+/u', '', $signature->textContent) ?? '') === 'ATENTAMENTEDIRECCIÓNDEADMINISTRACIÓNYFINANZAS') {
            while ($signature->firstChild) $signature->removeChild($signature->firstChild);
            $signature->setAttribute('style', 'text-align:center');
            $signature->appendChild($document->createElement('strong', 'ATENTAMENTE'));
            for ($i = 0; $i < 3; $i++) $signature->appendChild($document->createElement('br'));
            $signature->appendChild($document->createTextNode('__________________________________'));
            $signature->appendChild($document->createElement('br'));
            $signature->appendChild($document->createTextNode('DIRECCIÓN DE ADMINISTRACIÓN Y FINANZAS'));
        }

        $anchor = $requirements ?: $signature;
        if ($requirements && mb_strlen(strip_tags($details)) > 5000) {
            $requirements->setAttribute('class', 'requirements-heading');
        }
        foreach (iterator_to_array($annexRoot->childNodes) as $node) {
            $imported = $document->importNode($node, true);
            if ($anchor) $anchor->parentNode->insertBefore($imported, $anchor);
            else $root->appendChild($imported);
        }
        $result = '';
        foreach ($root->childNodes as $node) $result .= $document->saveHTML($node);
        return $result;
    }

    public function pdf(object $convocation, string $title, string $details, Collection $campuses,
        string $fontFamily = 'dejavusans', float $fontSize = 9): string
    {
        abort_unless(array_key_exists($fontFamily, self::FONT_FAMILIES), 422, 'Fuente de PDF no disponible.');
        $fontSize = min(14, max(7, $fontSize));
        $rows = '';
        foreach ($campuses as $campus) {
            $rows .= '<tr><td>'.e($campus->nombre).'</td><td>'.e($campus->direccion ?: '—').'</td>'
                .'<td>'.e($campus->espacio).'</td><td>'.e($campus->matricula ?? '—').'</td>'
                .'<td>$'.number_format((float) $campus->monto, 2).'</td><td>'
                .($campus->garantia !== null ? '$'.number_format((float) $campus->garantia, 2) : '—').'</td>'
                .'<td>'.e(\Carbon\Carbon::parse($campus->fecha_inicio)->format('d/m/Y')).'</td></tr>';
        }
        $annex = '<h2>Anexo I · Planteles participantes</h2>'
            .'<table class="campus-annex"><thead><tr><th>Plantel</th><th>Dirección</th><th>Espacio</th><th>Matrícula</th><th>Monto</th><th>Garantía</th><th>Inicio</th></tr></thead><tbody>'.$rows.'</tbody></table>';
        $details = $this->detailsWithAnnex($details, $annex);
        $html = '<html><head><meta charset="UTF-8"><style>
            body{font-family:'.$fontFamily.',sans-serif;color:#29242a;font-size:'.$fontSize.'pt;line-height:1.5}
            .eyebrow{color:#875a16;font-size:8pt;letter-spacing:1px;font-weight:bold;text-transform:uppercase}
            h1{color:#611232;font-size:19pt;line-height:1.2;margin:10px 0 8px}
            h2{color:#611232;font-size:12pt;margin:18px 0 8px}
            h3{color:#611232;font-size:10pt;margin:14px 0 6px}
            .rule{border-top:3px solid #611232;margin:0 0 18px}
            .meta{color:#675c60;font-size:9pt;margin-bottom:20px}
            table{width:100%;border-collapse:collapse;font-size:'.max(7, $fontSize - 1).'pt;margin-top:9px}
            th{background:#611232;color:#fff;text-align:left;padding:7px}
            td{border-bottom:1px solid #ded7d9;padding:7px;vertical-align:top}
            .details p{margin:0 0 9px;text-align:justify}.details li{margin-bottom:5px}
            .details table{line-height:1.2}.details table p{margin:0 0 3px}.details table td,.details table th{border:1px solid #ded7d9}
            .requirements-heading{page-break-before:always}
            .campus-annex{page-break-inside:auto}.campus-annex tr{page-break-inside:avoid}
            .footer{color:#776b70;font-size:7pt;margin-top:25px;border-top:1px solid #ded7d9;padding-top:7px}
        </style></head><body>'
            .'<div class="eyebrow">Colegio de Bachilleres del Estado de México · CCyF</div>'
            .'<h1>'.e($title).'</h1><div class="rule"></div>'
            .'<div class="meta">Convocatoria: <strong>'.e($convocation->numero).'</strong> &nbsp;·&nbsp; Servicio: <strong>'.e($convocation->servicio_nombre).'</strong></div>'
            .'<div class="details">'.$details.'</div>'
            .'<div class="footer">Documento generado desde CCyF · '.e(now()->format('d/m/Y H:i')).'</div></body></html>';

        $directory = storage_path('app/mpdf');
        if (! is_dir($directory)) mkdir($directory, 0775, true);
        $pdf = new Mpdf(['mode' => 'utf-8', 'format' => [215.9, 355.6], 'tempDir' => $directory,
            'margin_top' => 31, 'margin_header' => 6, 'margin_bottom' => 16,
            'margin_left' => 17, 'margin_right' => 17,
            'default_font' => $fontFamily, 'default_font_size' => $fontSize]);
        $pdf->SetTitle($title);
        InstitutionalPdfHeader::apply($pdf, 180);
        $pdf->WriteHTML($html);
        return $pdf->Output('', 'S');
    }
}
