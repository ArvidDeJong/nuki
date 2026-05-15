<?php

declare(strict_types=1);

return [

    'login_otp' => [
        'subject' => 'Login code :brand',
        'title' => 'Login code',
        'greeting_named' => 'Hello :name,',
        'greeting_anon' => 'Hello,',
        'instruction' => 'Use the code below to complete your sign-in. The code is valid for :minutes minutes.',
        'requested_from' => 'Requested from:',
        'ip' => 'IP: :ip',
        'browser' => 'Browser: :ua',
        'security_note' => 'Did you not request this code? Ignore this email and change your password as soon as possible.',
    ],

    'password_reset' => [
        'subject' => 'Reset password :brand',
        'title' => 'Reset password',
        'greeting_named' => 'Hello :name,',
        'greeting_anon' => 'Hello,',
        'instruction' => 'A request has been made to reset your password. Click the button below to choose a new password. The link is valid for :minutes minutes.',
        'button' => 'Change password',
        'fallback_note' => 'Button not working? Paste this link into your browser:',
        'security_note' => 'Did you not request this? Ignore this email; your password remains unchanged.',
    ],

    'verify_email' => [
        'subject' => 'Confirm your email address :brand',
        'title' => 'Confirm your email address',
        'greeting_named' => 'Hello :name,',
        'greeting_anon' => 'Hello,',
        'instruction' => 'Welcome! Confirm your email address with the button below to activate your account. The link is valid for :minutes minutes.',
        'button' => 'Confirm email address',
        'fallback_note' => 'Button not working? Paste this link into your browser:',
        'security_note' => 'Did you not create an account? Then ignore this email.',
    ],

];
