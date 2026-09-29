<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class PdfDocument implements ValidationRule
{
    public function __construct(private readonly string $documentName) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        if (strtolower($value->getClientOriginalExtension()) !== 'pdf') {
            $fail('El archivo de «'.$this->documentName.'» debe tener extensión .pdf.');
            return;
        }

        $path = $value->getRealPath();
        $size = $value->getSize();
        if (! $path || ! $size || $size < 14 || ! ($stream = @fopen($path, 'rb'))) {
            $fail('No se pudo leer el PDF de «'.$this->documentName.'». Vuelve a adjuntarlo.');
            return;
        }

        try {
            $header = fread($stream, 12);
            fseek($stream, -min(4096, $size), SEEK_END);
            $trailer = stream_get_contents($stream);
        } finally {
            fclose($stream);
        }

        if (! is_string($header) || ! is_string($trailer)
            || ! preg_match('/^(?:\xEF\xBB\xBF)?%PDF-(?:1\.[0-7]|2\.0)/', $header)
            || ! str_contains($trailer, '%%EOF')) {
            $fail('El archivo de «'.$this->documentName.'» no es un PDF válido. Expórtalo nuevamente a PDF y vuelve a adjuntarlo.');
        }
    }
}
