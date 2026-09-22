<?php

namespace App\Http\Middleware;

use App\Services\LegacyMenu;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCcyfAccountActive
{
    public function __construct(private LegacyMenu $menu)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->est || ! $this->menu->roleActive($request->user())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['email' => 'La cuenta no está activa.']);
        }

        return $next($request);
    }
}
