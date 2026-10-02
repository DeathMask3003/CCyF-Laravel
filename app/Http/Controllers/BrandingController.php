<?php

namespace App\Http\Controllers;

use App\Services\Branding;
use App\Services\LegacyMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandingController extends Controller
{
    public function edit(Request $request, LegacyMenu $menu, Branding $branding): View
    {
        $this->authorizeAdmin($request, $menu);
        $current = $branding->current();

        return view('branding.edit', [
            'branding' => $current,
            'logoUrl' => $branding->logoUrl($current),
            'documentAvailable' => $branding->documentFile($current) !== null,
        ]);
    }

    public function update(Request $request, LegacyMenu $menu, Branding $branding): RedirectResponse
    {
        $this->authorizeAdmin($request, $menu);
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'motto' => ['required', 'string', 'min:3', 'max:160'],
            'logo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096',
                'dimensions:min_width=80,min_height=80,max_width=4000,max_height=4000'],
            'remove_logo' => ['sometimes', 'boolean'],
        ], [
            'logo.image' => 'Selecciona una imagen PNG, JPG o WebP válida.',
            'logo.mimes' => 'La imagen debe estar en formato PNG, JPG o WebP.',
            'logo.max' => 'La imagen no debe superar 4 MB.',
            'logo.dimensions' => 'La imagen debe medir entre 80 × 80 y 4000 × 4000 píxeles.',
            'logo.uploaded' => 'No se pudo subir la imagen. Comprueba su tamaño e inténtalo de nuevo.',
        ]);

        if ($request->hasFile('logo') && $request->boolean('remove_logo')) {
            throw ValidationException::withMessages(['logo' => 'Elige una imagen nueva o marca quitar logo.']);
        }

        $current = $branding->current();
        $newPath = null;
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $newPath = $file->storeAs('ccyf/branding', Str::uuid().'.'.$file->extension(), 'local');
            if (! $newPath) {
                throw ValidationException::withMessages(['logo' => 'No se pudo guardar la imagen. Inténtalo de nuevo.']);
            }
        }

        $logoPath = $request->boolean('remove_logo') ? null : ($newPath ?: $current->logo_path);
        try {
            DB::table('ccyf_branding')->updateOrInsert(['id' => 1], [
                'title' => trim($data['title']),
                'motto' => trim($data['motto']),
                'logo_path' => $logoPath,
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }

        if ($current->logo_path && $current->logo_path !== $logoPath
            && str_starts_with($current->logo_path, 'ccyf/branding/')) {
            Storage::disk('local')->delete($current->logo_path);
        }

        return back()->with('status', 'La identidad del portal se actualizó correctamente.');
    }

    public function logo(Branding $branding): BinaryFileResponse
    {
        $file = $branding->logoFile($branding->current());
        abort_unless($file, 404);
        $mime = @getimagesize($file)['mime'] ?? null;
        abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true), 404);

        return response()->file($file, [
            'Content-Type' => $mime,
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updateDocument(Request $request, LegacyMenu $menu, Branding $branding): RedirectResponse
    {
        $this->authorizeAdmin($request, $menu);
        $data = $request->validate([
            'document_title' => ['required', 'string', 'min:3', 'max:120'],
            'document_description' => ['nullable', 'string', 'max:240'],
            'document' => ['nullable', 'file', 'mimes:pdf', 'max:15360'],
            'remove_document' => ['sometimes', 'boolean'],
        ], [
            'document.mimes' => 'Selecciona un archivo PDF válido.',
            'document.max' => 'El PDF no debe superar 15 MB.',
            'document.uploaded' => 'No se pudo subir el PDF. Comprueba su tamaño e inténtalo de nuevo.',
        ]);

        if ($request->hasFile('document') && $request->boolean('remove_document')) {
            throw ValidationException::withMessages(['document' => 'Elige un PDF nuevo o marca quitar el documento.']);
        }
        if ($request->hasFile('document')
            && file_get_contents($request->file('document')->getRealPath(), false, null, 0, 5) !== '%PDF-') {
            throw ValidationException::withMessages(['document' => 'El archivo no contiene un PDF válido.']);
        }

        $current = $branding->current();
        $newPath = null;
        if ($request->hasFile('document')) {
            $newPath = $request->file('document')->storeAs('ccyf/branding/documents', Str::uuid().'.pdf', 'local');
            if (! $newPath) {
                throw ValidationException::withMessages(['document' => 'No se pudo guardar el PDF. Inténtalo de nuevo.']);
            }
        }

        $path = $request->boolean('remove_document') ? null : ($newPath ?: $current->document_path);
        try {
            DB::table('ccyf_branding')->updateOrInsert(['id' => 1], [
                'title' => $current->title,
                'motto' => $current->motto,
                'document_title' => trim($data['document_title']),
                'document_description' => trim((string) ($data['document_description'] ?? '')),
                'document_path' => $path,
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }

        if ($current->document_path && $current->document_path !== $path
            && $branding->documentFile($current)) {
            Storage::disk('local')->delete($current->document_path);
        }

        return back()->with('status', $path
            ? 'El documento para participantes se publicó correctamente.'
            : 'El documento para participantes se retiró del inicio.');
    }

    public function document(Request $request, LegacyMenu $menu, Branding $branding): BinaryFileResponse
    {
        abort_unless($menu->isContestant($request->user())
            || $menu->allows($request->user(), 'Usuarios'), 403);
        $file = $branding->documentFile($branding->current());
        abort_unless($file, 404);

        return response()->file($file, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Informacion_Portal_CCyF.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeAdmin(Request $request, LegacyMenu $menu): void
    {
        abort_unless($menu->allows($request->user(), 'Usuarios'), 403);
    }
}
