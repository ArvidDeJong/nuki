<?php

declare(strict_types=1);

return [

    'login_otp' => [
        'subject' => 'Inlogcode :brand',
        'title' => 'Inlogcode',
        'greeting_named' => 'Hallo :name,',
        'greeting_anon' => 'Hallo,',
        'instruction' => 'Gebruik onderstaande code om je inlogpoging af te ronden. De code is :minutes minuten geldig.',
        'requested_from' => 'Aangevraagd vanaf:',
        'ip' => 'IP: :ip',
        'browser' => 'Browser: :ua',
        'security_note' => 'Heb je deze code niet aangevraagd? Negeer deze e-mail en wijzig zo snel mogelijk je wachtwoord.',
    ],

    'password_reset' => [
        'subject' => 'Wachtwoord opnieuw instellen :brand',
        'title' => 'Wachtwoord opnieuw instellen',
        'greeting_named' => 'Hallo :name,',
        'greeting_anon' => 'Hallo,',
        'instruction' => 'Er is een verzoek ingediend om je wachtwoord opnieuw in te stellen. Klik op de knop hieronder om een nieuw wachtwoord te kiezen. De link is :minutes minuten geldig.',
        'button' => 'Wachtwoord wijzigen',
        'fallback_note' => 'Werkt de knop niet? Plak deze link in je browser:',
        'security_note' => 'Heb je dit verzoek niet zelf gedaan? Negeer deze e-mail; je wachtwoord blijft ongewijzigd.',
    ],

    'verify_email' => [
        'subject' => 'Bevestig je e-mailadres :brand',
        'title' => 'Bevestig je e-mailadres',
        'greeting_named' => 'Hallo :name,',
        'greeting_anon' => 'Hallo,',
        'instruction' => 'Welkom! Bevestig je e-mailadres met de knop hieronder om je account te activeren. De link is :minutes minuten geldig.',
        'button' => 'E-mailadres bevestigen',
        'fallback_note' => 'Werkt de knop niet? Plak deze link in je browser:',
        'security_note' => 'Heb je geen account aangemaakt? Negeer dan deze e-mail.',
    ],

];
