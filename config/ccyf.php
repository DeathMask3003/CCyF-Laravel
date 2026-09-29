<?php

return [
    'turnstile' => [
        'enabled' => (bool) env('CCYF_TURNSTILE_ENABLED', false),
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],
    'google_login_enabled' => (bool) env('CCYF_GOOGLE_LOGIN_ENABLED', false),
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
    // La copia histórica se encuentra junto al proyecto Laravel en esta instalación.
    'legacy_files_root' => env('CCYF_LEGACY_FILES_ROOT', dirname(base_path()).DIRECTORY_SEPARATOR.'ccyf'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'documents'),
    'legacy_reports_root' => env('CCYF_LEGACY_REPORTS_ROOT', dirname(base_path()).DIRECTORY_SEPARATOR.'ccyf'.DIRECTORY_SEPARATOR.'reportes'),
    'legacy_signatures_root' => env('CCYF_LEGACY_SIGNATURES_ROOT', dirname(base_path()).DIRECTORY_SEPARATOR.'ccyf'.DIRECTORY_SEPARATOR.'ccyf'.DIRECTORY_SEPARATOR.'e-signs'),
    'legacy_profile_photos_root' => env('CCYF_LEGACY_PROFILE_PHOTOS_ROOT', dirname(base_path()).DIRECTORY_SEPARATOR.'ccyf'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR.'users'),
    'mail_legal' => env('CCYF_MAIL_LEGAL', 'unidad.juridica@cobaemex.edu.mx'),
    'mail_management' => env('CCYF_MAIL_MANAGEMENT', 'mda@cobaemex.edu.mx'),
    'mail_ccyf' => env('CCYF_MAIL_CCYF', 'cafeteria.fotocopiado@cobaemex.edu.mx'),
    'legacy_complaints_root' => env('CCYF_LEGACY_COMPLAINTS_ROOT', dirname(base_path()).DIRECTORY_SEPARATOR.'ccyf'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'quejas'),
];
