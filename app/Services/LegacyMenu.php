<?php

namespace App\Services;

use App\Models\LegacyUser;
use Illuminate\Support\Facades\DB;

class LegacyMenu
{
    /** @var array<string, bool> */
    private array $permissions = [];

    public function allows(LegacyUser $user, string $menu): bool
    {
        $key = $user->getKey().':'.$menu;

        return $this->permissions[$key] ??= DB::connection('legacy')
            ->table('td_medu_detalle as detalle')
            ->join('tm_mennu as menu', 'menu.men_id', '=', 'detalle.men_id')
            ->where('detalle.rol_id', $user->rol_id)
            ->where('detalle.mend_permi', 'si')
            ->where('menu.men_nom', $menu)
            ->where('menu.est', 1)
            ->exists();
    }
}
