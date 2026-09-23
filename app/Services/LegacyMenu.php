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

        return $this->permissions[$key] ??= $this->check($user, $menu);
    }

    public function roleActive(LegacyUser $user): bool
    {
        if (! $user->rol_id) {
            return false;
        }

        $role = DB::table('ccyf_roles')->where('rol_id', $user->rol_id)->first();
        return $role ? (bool) $role->est : true;
    }

    public function isContestant(LegacyUser $user): bool
    {
        $role = DB::table('ccyf_roles')->where('rol_id', $user->rol_id)
            ->first(['legacy_rol_id', 'rol_nom']);

        return (int) ($role?->legacy_rol_id ?? $user->rol_id) === 1
            || str_contains(mb_strtolower((string) $role?->rol_nom), 'concursante');
    }

    private function check(LegacyUser $user, string $menu): bool
    {
        if (! $user->est || ! $this->roleActive($user)) {
            return false;
        }

        if (DB::table('ccyf_roles')->where('rol_id', $user->rol_id)->exists()) {
            return DB::table('ccyf_role_permissions')
                ->where('rol_id', $user->rol_id)->where('menu_key', $menu)
                ->where('allowed', true)->exists();
        }

        return DB::connection('legacy')
            ->table('td_medu_detalle as detalle')
            ->join('tm_mennu as menu', 'menu.men_id', '=', 'detalle.men_id')
            ->where('detalle.rol_id', $user->rol_id)
            ->where('detalle.mend_permi', 'si')
            ->where('menu.men_nom', $menu)
            ->where('menu.est', 1)
            ->exists();
    }
}
