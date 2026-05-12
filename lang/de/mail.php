<?php

declare(strict_types=1);

return [

    'login_otp' => [
        'subject' => 'Login-Code :brand',
        'title' => 'Login-Code',
        'greeting_named' => 'Hallo :name,',
        'greeting_anon' => 'Hallo,',
        'instruction' => 'Verwende den untenstehenden Code, um deinen Anmeldevorgang abzuschließen. Der Code ist :minutes Minuten gültig.',
        'requested_from' => 'Angefordert von:',
        'ip' => 'IP: :ip',
        'browser' => 'Browser: :ua',
        'security_note' => 'Hast du diesen Code nicht angefordert? Ignoriere diese E-Mail und ändere dein Passwort so schnell wie möglich.',
    ],

    'password_reset' => [
        'subject' => 'Passwort zurücksetzen :brand',
        'title' => 'Passwort zurücksetzen',
        'greeting_named' => 'Hallo :name,',
        'greeting_anon' => 'Hallo,',
        'instruction' => 'Es wurde eine Anfrage zum Zurücksetzen deines Passworts gestellt. Klicke auf die Schaltfläche unten, um ein neues Passwort zu wählen. Der Link ist :minutes Minuten gültig.',
        'button' => 'Passwort ändern',
        'fallback_note' => 'Funktioniert die Schaltfläche nicht? Füge diesen Link in deinen Browser ein:',
        'security_note' => 'Hast du dies nicht angefordert? Ignoriere diese E-Mail; dein Passwort bleibt unverändert.',
    ],

];
