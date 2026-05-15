<?php

declare(strict_types=1);

return [

    'login_otp' => [
        'subject' => 'Código de inicio :brand',
        'title' => 'Código de inicio',
        'greeting_named' => 'Hola :name,',
        'greeting_anon' => 'Hola,',
        'instruction' => 'Usa el código de abajo para completar tu inicio de sesión. El código es válido :minutes minutos.',
        'requested_from' => 'Solicitado desde:',
        'ip' => 'IP: :ip',
        'browser' => 'Navegador: :ua',
        'security_note' => '¿No has solicitado este código? Ignora este correo y cambia tu contraseña lo antes posible.',
    ],

    'password_reset' => [
        'subject' => 'Restablecer contraseña :brand',
        'title' => 'Restablecer contraseña',
        'greeting_named' => 'Hola :name,',
        'greeting_anon' => 'Hola,',
        'instruction' => 'Se ha solicitado restablecer tu contraseña. Haz clic en el botón de abajo para elegir una nueva contraseña. El enlace es válido :minutes minutos.',
        'button' => 'Cambiar contraseña',
        'fallback_note' => '¿No funciona el botón? Pega este enlace en tu navegador:',
        'security_note' => '¿No has solicitado esto? Ignora este correo; tu contraseña no cambiará.',
    ],

    // NOTE: Have a native speaker review
    'verify_email' => [
        'subject' => 'Confirma tu correo electrónico :brand',
        'title' => 'Confirma tu correo electrónico',
        'greeting_named' => 'Hola :name,',
        'greeting_anon' => 'Hola,',
        'instruction' => '¡Bienvenido! Confirma tu correo electrónico con el botón de abajo para activar tu cuenta. El enlace es válido :minutes minutos.',
        'button' => 'Confirmar correo electrónico',
        'fallback_note' => '¿No funciona el botón? Pega este enlace en tu navegador:',
        'security_note' => '¿No has creado una cuenta? Entonces ignora este correo.',
    ],

];
