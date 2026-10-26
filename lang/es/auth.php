<?php

return [
    'church_domain_session_ended' => 'Tu sesión finalizó porque este dominio pertenece a otra congregación.',

    /*
    |--------------------------------------------------------------------------
    | Líneas de idioma de autenticación
    |--------------------------------------------------------------------------
    |
    | Las siguientes líneas de idioma se usan durante la autenticación para los
    | distintos mensajes que necesitamos mostrar al usuario. Puedes modificarlas
    | libremente según los requisitos de tu aplicación.
    |
    */

    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'church_membership_required' => 'Transfiere tu membresía a esta iglesia antes de acceder a su área privada.',
    'password' => 'La contraseña proporcionada es incorrecta.',
    'throttle' => 'Demasiados intentos de inicio de sesión. Inténtalo de nuevo en :seconds segundos.',

    'mobile' => [
        'account_blocked' => 'Esta cuenta está bloqueada.',
        'invalid_refresh_token' => 'La sesión móvil ya no es válida. Inicia sesión de nuevo.',
        'refresh_reused' => 'Esta sesión móvil fue revocada porque se reutilizó un token de actualización.',
        'two_factor_required' => 'Ingresa tu código de autenticación de dos factores.',
        'two_factor_invalid' => 'El código de autenticación de dos factores no es válido.',
        'rate_limited' => 'Demasiadas solicitudes. Inténtalo de nuevo en breve.',
    ],

];
