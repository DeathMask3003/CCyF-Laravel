<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

class ConvocationDocuments
{
    private const IMAGE_URL_PATTERN = '~^/emision-convocatorias/imagenes/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\.(png|jpg)$~';

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

    public function imagePath(string $url): ?string
    {
        if (! preg_match(self::IMAGE_URL_PATTERN, $url, $matches)) {
            return null;
        }

        return 'ccyf/convocatorias/imagenes/'.$matches[1].'.'.$matches[2];
    }

    private function cleanNode(DOMNode $node): void
    {
        if (! $node instanceof DOMElement) {
            return;
        }
        $allowed = ['p', 'div', 'br', 'strong', 'b', 'em', 'i', 'u', 'span', 'h2', 'h3', 'ol', 'ul', 'li',
            'blockquote', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'a', 'img'];
        $name = strtolower($node->tagName);
        if (in_array($name, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'svg'], true)) {
            $node->parentNode?->removeChild($node);
            return;
        }
        if ($name === 'img') {
            $src = trim($node->getAttribute('src'));
            $path = $this->imagePath($src);
            if (! $path || ! Storage::disk('local')->exists($path)) {
                $node->parentNode?->removeChild($node);
                return;
            }
            $alt = mb_substr(trim($node->getAttribute('alt')), 0, 120);
            $width = (int) $node->getAttribute('width');
            if ($width <= 0 && preg_match('/(?:^|;)\s*width\s*:\s*(\d+)px\s*(?:;|$)/i',
                $node->getAttribute('style'), $match)) {
                $width = (int) $match[1];
            }
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $node->removeAttribute($attribute->name);
            }
            $node->setAttribute('src', $src);
            $node->setAttribute('alt', $alt);
            $node->setAttribute('width', (string) min(480, max(60, $width ?: 180)));
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
        $inlineStyles = [];
        if ($name === 'span') {
            if (preg_match('/(?:^|;)\s*font-family\s*:\s*(dejavusans|dejavuserif|freesans)\s*(?:;|$)/i',
                $node->getAttribute('style'), $match)) {
                $inlineStyles[] = 'font-family:'.strtolower($match[1]);
            }
            if (preg_match('/(?:^|;)\s*font-size\s*:\s*(\d+(?:\.5)?)pt\s*(?:;|$)/i',
                $node->getAttribute('style'), $match)) {
                $size = (float) $match[1];
                if ($size >= 7 && $size <= 14) $inlineStyles[] = 'font-size:'.$match[1].'pt';
            }
        }
        foreach (iterator_to_array($node->attributes) as $attribute) {
            $node->removeAttribute($attribute->name);
        }
        if ($alignment !== null) $node->setAttribute('style', 'text-align:'.$alignment);
        if ($inlineStyles !== []) $node->setAttribute('style', implode(';', $inlineStyles));
        if ($name === 'a' && preg_match('~^https?://~i', $href)) {
            $node->setAttribute('href', $href);
        }
    }

    public function detailsWithAnnex(string $details, string $annex, ?string $signerName = null): string
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
            if (filled($signerName)) {
                $name = $document->createElement('strong');
                $name->appendChild($document->createTextNode($signerName));
                $signature->appendChild($name);
                $signature->appendChild($document->createElement('br'));
            }
            $signature->appendChild($document->createTextNode('DIRECCIÓN DE ADMINISTRACIÓN Y FINANZAS'));
        } elseif ($signature && filled($signerName)
            && ! str_contains(mb_strtoupper((string) $signature->textContent), mb_strtoupper($signerName))) {
            $signature->appendChild($document->createElement('br'));
            $name = $document->createElement('strong');
            $name->appendChild($document->createTextNode($signerName));
            $signature->appendChild($name);
        } elseif (! $signature && filled($signerName)) {
            $signature = $document->createElement('p');
            $signature->setAttribute('style', 'text-align:center');
            $signature->appendChild($document->createElement('strong', 'ATENTAMENTE'));
            for ($i = 0; $i < 3; $i++) $signature->appendChild($document->createElement('br'));
            $signature->appendChild($document->createTextNode('__________________________________'));
            $signature->appendChild($document->createElement('br'));
            $name = $document->createElement('strong');
            $name->appendChild($document->createTextNode($signerName));
            $signature->appendChild($name);
            $signature->appendChild($document->createElement('br'));
            $signature->appendChild($document->createTextNode('DIRECCIÓN DE ADMINISTRACIÓN Y FINANZAS'));
            $root->appendChild($signature);
        }

        $anchor = $requirements ?: $signature;
        foreach (iterator_to_array($annexRoot->childNodes) as $node) {
            $imported = $document->importNode($node, true);
            if ($anchor) $anchor->parentNode->insertBefore($imported, $anchor);
            else $root->appendChild($imported);
        }
        if ($signature && $signature->parentNode) {
            $table = $document->createElement('table');
            $table->setAttribute('class', 'ccyf-signature-table');
            $row = $document->createElement('tr');
            $cell = $document->createElement('td');
            $cell->setAttribute('align', 'center');
            $signature->parentNode->replaceChild($table, $signature);
            $table->appendChild($row);
            $row->appendChild($cell);
            $cell->appendChild($signature);
        }
        $result = '';
        foreach ($root->childNodes as $node) $result .= $document->saveHTML($node);
        return $result;
    }

    public function pdf(object $convocation, string $title, string $details, Collection $campuses,
        string $fontFamily = 'dejavusans', float $fontSize = 9, ?string $signerName = null,
        bool $draft = false): string
    {
        abort_unless(array_key_exists($fontFamily, self::FONT_FAMILIES), 422, 'Fuente de PDF no disponible.');
        $fontSize = min(14, max(7, $fontSize));
        $rows = '';
        foreach ($campuses as $campus) {
            $rows .= '<tr><td>'.e($campus->nombre).'</td><td>'.e($campus->direccion ?: '—').'</td>'
                .'<td>'.e($campus->espacio ?: 'Pendiente').'</td><td>'.e($campus->matricula ?? '—').'</td>'
                .'<td>'.($campus->monto === null ? 'Pendiente' : '$'.number_format((float) $campus->monto, 2)).'</td><td>'
                .($campus->garantia !== null ? '$'.number_format((float) $campus->garantia, 2) : '—').'</td>'
                .'<td>'.($campus->fecha_inicio ? e(\Carbon\Carbon::parse($campus->fecha_inicio)->format('d/m/Y')) : 'Pendiente').'</td></tr>';
        }
        $annex = '<h2>Anexo I · Planteles participantes</h2>'
            .'<table class="campus-annex"><thead><tr><th>Plantel</th><th>Dirección</th><th>Espacio</th><th>Matrícula</th><th>Monto</th><th>Garantía</th><th>Inicio</th></tr></thead><tbody>'.$rows.'</tbody></table>';
        $details = $this->detailsWithAnnex($details, $annex, $signerName);
        $details = $this->paginateLongTablesForPdf($details, $fontSize);
        $details = $this->embedImagesForPdf($details);
        $details = $this->alignTextForPdf($details);
        $html = '<html><head><meta charset="UTF-8"><style>
            body{font-family:'.$fontFamily.',sans-serif;color:#29242a;font-size:'.$fontSize.'pt;line-height:1.5}
            .eyebrow{color:#875a16;font-size:8pt;letter-spacing:1px;font-weight:bold;text-transform:uppercase}
            h1{color:#611232;font-size:19pt;line-height:1.2;margin:10px 0 8px}
            h2{color:#611232;font-size:12pt;margin:18px 0 8px}
            h3{color:#611232;font-size:10pt;margin:14px 0 6px}
            .rule{border-top:3px solid #611232;margin:0 0 18px}
            .meta{color:#675c60;font-size:9pt;margin-bottom:20px}
            .draft-note{background:#fff4dc;border:1px solid #d2b56f;color:#6f4b17;padding:7px 10px;margin:0 0 15px;font-size:8pt}
            table{width:100%;border-collapse:collapse;font-size:'.max(7, $fontSize - 1).'pt;margin-top:9px}
            th{background:#611232;color:#fff;text-align:left;padding:7px}
            td{border-bottom:1px solid #ded7d9;padding:7px;vertical-align:top}
            .details p{margin:0 0 9px}.details li{margin-bottom:5px}
            .details table{line-height:1.2}.details table p{margin:0 0 3px}.details table td,.details table th{border:1px solid #ded7d9}
            .details h2,.details h3{page-break-after:avoid}
            .details img{height:auto;page-break-inside:avoid}
            .details table.ccyf-image-table{width:100%;border:0;margin:8px 0;page-break-inside:avoid}
            .details table.ccyf-image-table td{border:0;padding:0;text-align:center}
            .details table.ccyf-flow-table{page-break-inside:auto;border:0}
            .details table.ccyf-flow-table tr{page-break-inside:avoid}
            .details table.ccyf-flow-table td{border:0;padding:2px 8px;vertical-align:top;text-align:justify}
            .details table.ccyf-aligned-block{width:100%;border:0;margin:0}
            .details table.ccyf-flow-table table.ccyf-image-table td,.details table.ccyf-flow-table table.ccyf-aligned-block td{border:0;padding:0;text-align:center}
            .details table.ccyf-signature-table{width:100%;border:0;margin:12px 0 0;page-break-inside:avoid}
            .details table.ccyf-signature-table td{border:0;padding:0;text-align:center}
            .campus-annex{page-break-inside:auto}.campus-annex tr{page-break-inside:avoid}
            .footer{color:#776b70;font-size:7pt;margin-top:25px;border-top:1px solid #ded7d9;padding-top:7px}
        </style></head><body>'
            .'<div class="eyebrow">Colegio de Bachilleres del Estado de México · CCyF</div>'
            .'<h1>'.e($title).'</h1><div class="rule"></div>'
            .'<div class="meta">Convocatoria: <strong>'.e($convocation->numero).'</strong> &nbsp;·&nbsp; Servicio: <strong>'.e($convocation->servicio_nombre).'</strong></div>'
            .($draft ? '<div class="draft-note">VISTA PREVIA SIN GUARDAR · Los datos faltantes se muestran como Pendiente.</div>' : '')
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

    private function paginateLongTablesForPdf(string $html, float $fontSize): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div id="ccyf-flow-content">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementById('ccyf-flow-content');
        if (! $root) return $html;
        $chunkLimit = max(650, min(1300, (int) round(1100 * 9 / $fontSize)));

        foreach (iterator_to_array($root->getElementsByTagName('table')) as $table) {
            if ($table->getAttribute('class') === 'campus-annex'
                || $table->getAttribute('class') === 'ccyf-signature-table') continue;
            $rows = $table->getElementsByTagName('tr');
            if ($rows->length !== 1) continue;
            $sourceRow = $rows->item(0);
            $cells = array_values(array_filter(iterator_to_array($sourceRow->childNodes),
                fn ($node) => $node instanceof DOMElement && strtolower($node->tagName) === 'td'));
            if (count($cells) !== 2 || mb_strlen(trim($cells[0]->textContent.$cells[1]->textContent)) < 900) continue;

            $groups = [];
            foreach ($cells as $cell) {
                $chunks = [];
                $chunk = [];
                $weight = 0;
                foreach (iterator_to_array($cell->childNodes) as $child) {
                    if (! $child instanceof DOMElement && trim($child->textContent) === '') continue;
                    $size = mb_strlen(trim($child->textContent));
                    if ($child instanceof DOMElement && $child->getElementsByTagName('img')->length) $size += 700;
                    if ($chunk !== [] && $weight + $size > $chunkLimit) {
                        $chunks[] = $chunk;
                        $chunk = [];
                        $weight = 0;
                    }
                    $chunk[] = $child->cloneNode(true);
                    $weight += $size;
                }
                if ($chunk !== []) $chunks[] = $chunk;
                $groups[] = $chunks;
            }
            $table->setAttribute('class', 'ccyf-flow-table');
            for ($index = 0; $index < max(count($groups[0]), count($groups[1])); $index++) {
                $row = $document->createElement('tr');
                foreach ($cells as $column => $cell) {
                    $newCell = $cell->cloneNode(false);
                    preg_match('/(?:^|;)\s*text-align\s*:\s*(left|center|right|justify)\s*(?:;|$)/i',
                        $cell->getAttribute('style'), $match);
                    $alignment = strtolower($match[1] ?? $cell->getAttribute('align'));
                    if (! in_array($alignment, ['left', 'center', 'right', 'justify'], true)) {
                        $alignment = 'justify';
                    }
                    $newCell->setAttribute('style', 'border:0;padding:2px 8px;text-align:'.$alignment);
                    $newCell->setAttribute('align', $alignment);
                    $newCell->setAttribute('width', '50%');
                    foreach ($groups[$column][$index] ?? [] as $child) {
                        if ($child instanceof DOMElement && strtolower($child->tagName) === 'div'
                            && str_contains($child->getAttribute('style'), 'text-align:center')) {
                            $centered = $document->createElement('table');
                            $centered->setAttribute('class', 'ccyf-aligned-block');
                            $centeredRow = $document->createElement('tr');
                            $centeredCell = $document->createElement('td');
                            $centeredCell->setAttribute('align', 'center');
                            $centeredCell->setAttribute('style', 'text-align:center');
                            while ($child->firstChild) $centeredCell->appendChild($child->firstChild);
                            $centered->appendChild($centeredRow);
                            $centeredRow->appendChild($centeredCell);
                            $newCell->appendChild($centered);
                        } else {
                            $newCell->appendChild($child);
                        }
                    }
                    $row->appendChild($newCell);
                }
                $sourceRow->parentNode->insertBefore($row, $sourceRow);
            }
            $sourceRow->parentNode->removeChild($sourceRow);
        }

        $result = '';
        foreach ($root->childNodes as $node) $result .= $document->saveHTML($node);
        return $result;
    }

    private function embedImagesForPdf(string $html): string
    {
        if (! str_contains($html, '<img')) {
            return $html;
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div id="ccyf-pdf-content">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementById('ccyf-pdf-content');
        if (! $root) {
            return $html;
        }
        foreach (iterator_to_array($root->getElementsByTagName('img')) as $image) {
            $path = $this->imagePath($image->getAttribute('src'));
            if (! $path || ! Storage::disk('local')->exists($path)) {
                $image->parentNode?->removeChild($image);
                continue;
            }
            $block = $image->parentNode;
            while ($block instanceof DOMElement && $block !== $root
                && ! in_array(strtolower($block->tagName), ['p', 'div', 'h2', 'h3', 'li', 'blockquote', 'td', 'th'], true)) {
                $block = $block->parentNode;
            }
            if ($block instanceof DOMElement) {
                $style = $block->getAttribute('style');
                preg_match('/text-align\s*:\s*(left|center|right|justify)/i', $style, $alignment);
                $choice = strtolower($alignment[1] ?? $block->getAttribute('align'));
                if (strtolower($block->tagName) === 'p' && trim($block->textContent) === ''
                    && $block->getElementsByTagName('img')->length === 1) {
                    $table = $document->createElement('table');
                    $table->setAttribute('class', 'ccyf-image-table');
                    $table->setAttribute('width', '100%');
                    $row = $document->createElement('tr');
                    $cell = $document->createElement('td');
                    $imageAlignment = in_array($choice, ['left', 'center', 'right'], true) ? $choice : 'center';
                    $cell->setAttribute('align', $imageAlignment);
                    $cell->setAttribute('style', 'text-align:'.$imageAlignment);
                    $block->parentNode?->replaceChild($table, $block);
                    $table->appendChild($row);
                    $row->appendChild($cell);
                    $cell->appendChild($image);
                } elseif ($block !== $root) {
                    $imageAlignment = in_array($choice, ['left', 'center', 'right'], true) ? $choice : 'center';
                    $block->setAttribute('align', $imageAlignment);
                    $block->setAttribute('style', 'text-align:'.$imageAlignment);
                }
            }
            $mime = str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg';
            $image->setAttribute('src', 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($path)));
        }
        $result = '';
        foreach ($root->childNodes as $node) {
            $result .= $document->saveHTML($node);
        }

        return $result;
    }

    private function alignTextForPdf(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div id="ccyf-pdf-alignment">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementById('ccyf-pdf-alignment');
        if (! $root) return $html;

        foreach ($root->getElementsByTagName('*') as $node) {
            if (! $node instanceof DOMElement) continue;
            $tag = strtolower($node->tagName);
            if (! in_array($tag, ['p', 'div', 'h2', 'h3', 'li', 'blockquote', 'td', 'th'], true)) continue;
            preg_match('/(?:^|;)\s*text-align\s*:\s*(left|center|right|justify)\s*(?:;|$)/i',
                $node->getAttribute('style'), $match);
            $alignment = strtolower($match[1] ?? $node->getAttribute('align'));
            if (! in_array($alignment, ['left', 'center', 'right', 'justify'], true)) {
                if ($tag !== 'p') continue;
                for ($parent = $node->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
                    if (strtolower($parent->tagName) === 'table') continue 2;
                }
                $alignment = 'justify';
            }
            $node->setAttribute('align', $alignment);
            $node->setAttribute('style', 'text-align:'.$alignment);
        }
        $result = '';
        foreach ($root->childNodes as $node) $result .= $document->saveHTML($node);
        return $result;
    }
}
