<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LegacyUser;
use App\Services\LegacyMenu;
use App\Services\LegacyPasswordVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class AccessController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, LegacyPasswordVerifier $passwords, LegacyMenu $menu): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        try {
            $user = LegacyUser::query()
                ->whereRaw('LOWER(usu_correo) = ?', [mb_strtolower(trim($credentials['email']))])
                ->where('est', 1)
                ->first();
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['email' => 'El acceso aún no está disponible.'])->onlyInput('email');
        }

        if (! $user || ! $menu->roleActive($user) || ! $passwords->verify($credentials['password'], $user->getAuthPassword())) {
            return back()->withErrors(['email' => 'El correo o la contraseña no son correctos.'])->onlyInput('email');
        }

        $this->clearPending($request);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $this->clearPending($request);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function clearPending(Request $request): void
    {
        $request->session()->forget([
            'ccyf.pending_user',
            'ccyf.pending_until',
            'ccyf.code_hash',
            'ccyf.code_attempts',
        ]);
    }

}
