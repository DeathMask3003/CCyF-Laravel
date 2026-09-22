<?php

namespace App\Services;

class CcyfPermissions
{
    public const MODULES = [
        'NuevoOficio' => 'Nuevo registro',
        'gestionOficio' => 'Convocatorias pendientes',
        'buscarOficio' => 'Convocatorias finalizadas',
        'Categorias_widi' => 'Número de convocatoria y precios',
        'Subcategorias_widi' => 'Enlaces para convocatoria',
        'Areas' => 'Gestionar planteles',
        'Tipo' => 'Tipos de documento',
        'Asuntos' => 'Tipos de servicios',
        'Usuarios' => 'Gestión de usuarios',
        'Rol' => 'Gestión de roles',
    ];

    public static function groups(): array
    {
        return [
            'Convocatorias' => array_slice(self::MODULES, 0, 3, true),
            'Catálogos' => array_slice(self::MODULES, 3, 5, true),
            'Administración' => array_slice(self::MODULES, 8, 2, true),
        ];
    }
}
