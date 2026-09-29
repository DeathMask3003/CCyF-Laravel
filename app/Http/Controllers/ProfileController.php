<?php

namespace App\Http\Controllers;

use App\Services\LegacyPasswordVerifier;
use App\Services\MexicanPhone;
use App\Services\ProfilePhotos;
use App\Services\SatElectronicSignatures;
use App\Services\SignatureImages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request, SignatureImages $signatures, ProfilePhotos $photos,
        SatElectronicSignatures $sat): View
    {
        return view('perfil.show', [
            'user' => $request->user(),
            'phone' => MexicanPhone::local($request->user()->usu_telf),
            'alternatePhone' => MexicanPhone::local($request->user()->telf_alter),
            'ip' => $request->ip(),
            'agent' => $request->userAgent(),
            'lastLocation' => DB::table('ccyf_ubicaciones')->where('usu_id', $request->user()->getKey())->orderByDesc('fecha_registro')->first(),
            'canManageSignature' => $signatures->canView($request->user()),
            'signatureAvailable' => $signatures->pathFor($request->user()) !== null,
            'photoAvailable' => $photos->pathFor($request->user()) !== null,
            'satStatus' => $signatures->canView($request->user()) ? $sat->status($request->user()) : null,
        ]);
    }

    public function photo(Request $request, ProfilePhotos $photos): BinaryFileResponse
    {
        $path = $photos->pathFor($request->user());
        abort_unless($path, 404);
        $response = response()->file($path, [
            'Content-Type' => getimagesize($path)['mime'],
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        return $response;
    }

    public function uploadPhoto(Request $request, ProfilePhotos $photos): RedirectResponse
    {
        $data = $request->validate([
            'foto' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:3072',
                'dimensions:max_width=5000,max_height=5000'],
        ]);
        $photos->save($request->user(), $data['foto']);
        return back()->with('status', 'Tu foto de perfil se actualizó correctamente.');
    }

    public function signature(Request $request, SignatureImages $signatures): BinaryFileResponse
    {
        abort_unless($signatures->canView($request->user()), 403);
        $path = $signatures->pathFor($request->user());
        abort_unless($path, 404);

        $response = response()->file($path, [
            'Content-Type' => getimagesize($path)['mime'],
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    public function uploadSignature(Request $request, SignatureImages $signatures): RedirectResponse
    {
        abort_unless($signatures->canView($request->user()), 403);
        $data = $request->validate([
            'firma' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048',
                'dimensions:max_width=3000,max_height=3000'],
        ]);
        $signatures->save($request->user(), $data['firma']);

        return back()->with('status', 'Tu firma se actualizó correctamente.');
    }

    public function uploadSatSignature(Request $request, SignatureImages $signatures,
        SatElectronicSignatures $sat): RedirectResponse
    {
        abort_unless($signatures->canView($request->user()), 403);
        $data = $request->validate([
            'alias' => ['nullable', 'string', 'max:80'],
            'cer' => ['required', 'file', 'max:128'],
            'key' => ['required', 'file', 'max:256'],
            'password_sat' => ['required', 'string', 'max:1024'],
        ]);
        $sat->save($request->user(), $data['cer'], $data['key'], $data['password_sat'],
            (string) ($data['alias'] ?? 'Mi e.firma SAT'));
        return back()->with('status', 'El certificado y la clave de tu e.firma se validaron y guardaron.');
    }

    public function deleteSatSignature(Request $request, SignatureImages $signatures,
        SatElectronicSignatures $sat): RedirectResponse
    {
        abort_unless($signatures->canView($request->user()), 403);
        $sat->delete($request->user());
        return back()->with('status', 'La e.firma se eliminó físicamente del servidor.');
    }

    public function signatureMethod(Request $request, SignatureImages $signatures,
        SatElectronicSignatures $sat): RedirectResponse
    {
        abort_unless($signatures->canView($request->user()), 403);
        $data = $request->validate(['metodo' => ['required', 'in:efirma,imagen']]);
        $sat->setMethod($request->user(), $data['metodo']);
        return back()->with('status', 'Método de firma preferido actualizado.');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge([
            'rfc' => mb_strtoupper(trim((string) $request->input('rfc'))),
            'curp' => mb_strtoupper(trim((string) $request->input('curp'))),
            'ine_clave' => mb_strtoupper(trim((string) $request->input('ine_clave'))),
        ]);
        $identityRequired = ! in_array((int) $request->user()->rol_id, [2, 3, 18], true);
        $user = $request->user();
        $identityRule = fn (string $pattern, ?string $previous) => function (string $attribute, mixed $value, \Closure $fail) use ($pattern, $previous): void {
            if (mb_strtoupper((string) $previous) !== $value && ! preg_match($pattern, (string) $value)) {
                $fail('El formato de este identificador no es válido.');
            }
        };
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:5', 'max:150'],
            'telefono' => ['required', 'regex:/^\d{10}$/'],
            'rfc' => [$identityRequired ? 'required' : 'nullable', $identityRule('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', $user->rfc)],
            'curp' => [$identityRequired ? 'required' : 'nullable', $identityRule('/^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]{2}$/', $user->curp)],
            'ine_clave' => [$identityRequired ? 'required' : 'nullable', $identityRule('/^(?:[A-Z0-9]{18}|\d{9}|\d{13})$/', $user->ine_clave)],
            'direccion' => ['nullable', 'string', 'max:400'],
            'contacto_alterno' => ['nullable', 'string', 'max:150'],
            'telefono_alterno' => ['nullable', 'regex:/^\d{10}$/'],
            'telegram_chat_id' => ['nullable', 'regex:/^\d{5,20}$/'],
        ], [
            'telefono.regex' => 'Escribe solo los 10 dígitos de tu teléfono.',
            'telefono_alterno.regex' => 'Escribe solo los 10 dígitos del teléfono alterno.',
        ]);

        $user->usu_area = trim($data['nombre']);
        $user->usu_telf = MexicanPhone::store($data['telefono']);
        $user->rfc = $data['rfc'] ?? null;
        $user->curp = $data['curp'] ?? null;
        $user->ine_clave = $data['ine_clave'] ?? null;
        $user->direcc = $data['direccion'] ?? null;
        $user->cont_alter = $data['contacto_alterno'] ?? null;
        $user->telf_alter = filled($data['telefono_alterno'] ?? null) ? MexicanPhone::store($data['telefono_alterno']) : null;
        $user->telegram_chat_id = $data['telegram_chat_id'] ?? null;
        $user->save();

        return back()->with('status', 'Tu perfil se actualizó correctamente.');
    }

    public function password(Request $request, LegacyPasswordVerifier $verifier): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ]);

        if (! $verifier->verify($data['current_password'], $request->user()->getAuthPassword())) {
            return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.']);
        }

        $request->user()->usu_pass = Hash::make($data['password']);
        $request->user()->save();
        $request->session()->regenerate();

        return back()->with('status', 'Tu contraseña se actualizó correctamente.');
    }
}
