<?php

return [
    'legacy_root' => env('CCYF_LEGACY_ROOT'),
    'legacy_password_key' => env('CCYF_LEGACY_PASSWORD_KEY'),
    'expected_tables' => [
        'tm_usuario',
        'tm_rol',
        'tm_areas',
        'tm_convocatorias',
        'td_convocatoria_planteles',
        'tm_documento_cafeteria',
        'tm_documento_fotocopiado',
        'tm_eval_cafe',
        'tm_eval_foto_seleccion',
        'tm_bitacora_ccyf',
    ],
];
