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
        $convocation = DB::table('ccyf_convocatorias')->where('id', $convocationId)->first();
        if (! $convocation || ! $convocation->servicio_id) {
            return collect();
        }

        return DB::table('ccyf_convocatoria_planteles as enlace')
            ->join('ccyf_planteles as plantel', 'plantel.id', '=', 'enlace.plantel_id')
            ->leftJoin('ccyf_plantel_servicios as condiciones', function ($join) use ($convocation): void {
                $join->on('condiciones.plantel_id', '=', 'plantel.id')
                    ->where('condiciones.servicio_id', '=', $convocation->servicio_id);
            })
            ->where('enlace.convocatoria_id', $convocationId)
            ->when($onlyActive, fn ($query) => $query->where('plantel.activo', true))
            ->orderBy('plantel.nombre')
            ->get(['plantel.id', 'plantel.nombre', 'plantel.direccion', 'plantel.activo',
                'condiciones.espacio', 'condiciones.matricula', 'condiciones.monto', 'condiciones.garantia']);
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
        $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h2', 'h3', 'ol', 'ul', 'li',
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
        foreach (iterator_to_array($node->attributes) as $attribute) {
            $node->removeAttribute($attribute->name);
        }
        if ($name === 'a' && preg_match('~^https?://~i', $href)) {
            $node->setAttribute('href', $href);
        }
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
            .details p{margin:0 0 9px}.details li{margin-bottom:5px}
            .details table td,.details table th{border:1px solid #ded7d9}
            .footer{color:#776b70;font-size:7pt;margin-top:25px;border-top:1px solid #ded7d9;padding-top:7px}
        </style></head><body>'
            .'<div class="eyebrow">Colegio de Bachilleres del Estado de México · CCyF</div>'
            .'<h1>'.e($title).'</h1><div class="rule"></div>'
            .'<div class="meta">Convocatoria: <strong>'.e($convocation->numero).'</strong> &nbsp;·&nbsp; Servicio: <strong>'.e($convocation->servicio_nombre).'</strong></div>'
            .'<div class="details">'.$details.'</div>'
            .'<h2>Anexo I · Planteles participantes</h2>'
            .'<table><thead><tr><th>Plantel</th><th>Dirección</th><th>Espacio</th><th>Matrícula</th><th>Monto</th><th>Garantía</th><th>Inicio</th></tr></thead><tbody>'.$rows.'</tbody></table>'
            .'<div class="footer">Documento generado desde CCyF · '.e(now()->format('d/m/Y H:i')).'</div></body></html>';

        $directory = storage_path('app/mpdf');
        if (! is_dir($directory)) mkdir($directory, 0775, true);
        $pdf = new Mpdf(['mode' => 'utf-8', 'format' => [215.9, 355.6], 'tempDir' => $directory,
            'margin_top' => 22, 'margin_bottom' => 23, 'margin_left' => 17, 'margin_right' => 17,
            'default_font' => $fontFamily, 'default_font_size' => $fontSize]);
        $pdf->SetTitle($title);
        $pdf->WriteHTML($html);
        return $pdf->Output('', 'S');
    }
}
