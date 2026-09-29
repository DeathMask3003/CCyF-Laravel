<?php

namespace App\Services;

class CcyfPermissions
{
    public const MODULES = [
        'NuevoOficio' => 'Nuevo registro',
        'gestionOficio' => 'Convocatorias pendientes',
        'buscarOficio' => 'Convocatorias finalizadas',
        'seguimiento_permisionarios' => 'Seguimiento de permisionarios',
        'prevaluacion' => 'Prevaluar documentación',
        'Prevaluaciones_admin' => 'Observaciones de prevaluación',
        'actualiza_docs' => 'Actualización de documentación',
        'Permisionarios_aceptados_vujeig' => 'Contratos de permisionarios · UJEIG',
        'Categorias_widi' => 'Número de convocatoria y precios',
        'Subcategorias_widi' => 'Enlaces para convocatoria',
        'Areas' => 'Gestionar planteles',
        'Tipo' => 'Tipos de documento',
        'Asuntos' => 'Tipos de servicios',
        'Usuarios' => 'Gestión de usuarios',
        'Rol' => 'Gestión de roles',
        'convocatorias' => 'Emisión de convocatorias',
        'quejas' => 'Observaciones y quejas',
    ];

    public static function groups(): array
    {
        return [
            'Convocatorias' => array_merge(['convocatorias' => self::MODULES['convocatorias']], array_slice(self::MODULES, 0, 4, true)),
            'Prevaluaciones' => array_slice(self::MODULES, 4, 2, true),
            'Documentación' => array_slice(self::MODULES, 6, 2, true),
            'Catálogos' => array_slice(self::MODULES, 8, 5, true),
            'Administración' => array_slice(self::MODULES, 13, 2, true) + ['quejas' => self::MODULES['quejas']],
        ];
    }
}
