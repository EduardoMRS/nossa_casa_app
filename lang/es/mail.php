<?php

return [
    'welcome' => [
        'subject' => 'Bienvenido a :brand',
        'preheader' => 'Tu cuenta en :brand está lista.',
        'heading' => '¡Bienvenido, :name!',
        'introduction' => 'Tu cuenta en :brand se creó correctamente.',
        'lines' => [
            'Ya puedes seguir noticias y eventos, acceder a recursos bíblicos y participar en los espacios disponibles para tu comunidad.',
            'La información opcional de tu perfil se puede actualizar en cualquier momento desde la configuración de tu cuenta.',
        ],
        'action' => 'Abrir :brand',
        'outro' => 'Nos alegra tenerte con nosotros.',
    ],
    'reset_password' => [
        'subject' => 'Restablece tu contraseña en :brand',
        'preheader' => 'Usa el enlace seguro para crear una nueva contraseña.',
        'heading' => 'Restablece tu contraseña',
        'introduction' => 'Recibimos una solicitud para crear una nueva contraseña para tu cuenta.',
        'expiration' => 'Por tu seguridad, este enlace caduca en :count minutos.',
        'action' => 'Crear nueva contraseña',
        'outro' => 'Si no solicitaste restablecer la contraseña, puedes ignorar este correo de forma segura.',
    ],
    'church_registration_request' => [
        'subject' => 'Nueva solicitud de registro de iglesia: :church',
        'preheader' => 'Una nueva solicitud de registro de iglesia está esperando revisión.',
        'heading' => 'Nueva solicitud de registro',
        'introduction' => ':requester solicitó el registro de :church.',
        'community' => 'Comunidad: :community',
        'parent_church' => 'Iglesia principal solicitada: :church',
        'contact' => 'Correo de contacto: :email',
        'address' => 'Dirección: :address',
        'language' => 'Idioma predeterminado: :language',
        'languages' => [
            'pt' => 'Portugués',
            'en' => 'Inglés',
        ],
        'action' => 'Revisar solicitud',
        'outro' => 'Abre el portal para revisar la información y aprobar o rechazar esta solicitud.',
    ],
    'footer' => [
        'application' => 'Nossa Casa · Tecnología al servicio de las comunidades de fe.',
        'church' => ':brand usa Nossa Casa para estar más cerca de su comunidad.',
        'legal' => 'Política de privacidad y términos de uso',
    ],
];
