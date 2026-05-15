<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('nuki::mail.verify_email.title') }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #18181b; background: #fafafa; margin: 0; padding: 24px;">
    <table style="max-width: 480px; margin: 0 auto; background: #ffffff; border: 1px solid #e4e4e7; border-radius: 8px; padding: 32px;">
        <tr>
            <td>
                <h1 style="font-size: 20px; margin: 0 0 16px;">{{ __('nuki::mail.verify_email.title') }}</h1>

                <p style="margin: 0 0 16px;">
                    @if (! empty($recipientName))
                        {{ __('nuki::mail.verify_email.greeting_named', ['name' => $recipientName]) }}
                    @else
                        {{ __('nuki::mail.verify_email.greeting_anon') }}
                    @endif
                </p>

                <p style="margin: 0 0 16px;">
                    {{ __('nuki::mail.verify_email.instruction', ['minutes' => $expiryMinutes]) }}
                </p>

                <p style="text-align: center; margin: 0 0 24px;">
                    <a href="{{ $verifyUrl }}" style="display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600;">
                        {{ __('nuki::mail.verify_email.button') }}
                    </a>
                </p>

                <p style="font-size: 12px; color: #71717a; margin: 0 0 8px;">
                    {{ __('nuki::mail.verify_email.fallback_note') }}
                </p>
                <p style="font-size: 12px; color: #2563eb; word-break: break-all; margin: 0 0 16px;">
                    {{ $verifyUrl }}
                </p>

                <p style="font-size: 12px; color: #71717a; margin: 0;">
                    {{ __('nuki::mail.verify_email.security_note') }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
