<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('nuki::mail.login_otp.title') }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #18181b; background: #fafafa; margin: 0; padding: 24px;">
    <table style="max-width: 480px; margin: 0 auto; background: #ffffff; border: 1px solid #e4e4e7; border-radius: 8px; padding: 32px;">
        <tr>
            <td>
                <h1 style="font-size: 20px; margin: 0 0 16px;">{{ __('nuki::mail.login_otp.title') }}</h1>

                <p style="margin: 0 0 16px;">
                    @if (! empty($recipientName))
                        {{ __('nuki::mail.login_otp.greeting_named', ['name' => $recipientName]) }}
                    @else
                        {{ __('nuki::mail.login_otp.greeting_anon') }}
                    @endif
                </p>

                <p style="margin: 0 0 16px;">
                    {{ __('nuki::mail.login_otp.instruction', ['minutes' => $expiryMinutes]) }}
                </p>

                <p style="font-size: 32px; letter-spacing: 8px; text-align: center; background: #f4f4f5; padding: 16px; border-radius: 6px; margin: 0 0 24px; font-family: 'SF Mono', Menlo, Consolas, monospace;">
                    <strong>{{ $code }}</strong>
                </p>

                @if (! empty($ip) || ! empty($userAgent))
                    <p style="font-size: 12px; color: #71717a; margin: 0 0 8px;">
                        {{ __('nuki::mail.login_otp.requested_from') }}
                    </p>
                    <ul style="font-size: 12px; color: #71717a; margin: 0 0 16px; padding-left: 16px;">
                        @if (! empty($ip))
                            <li>{{ __('nuki::mail.login_otp.ip', ['ip' => $ip]) }}</li>
                        @endif
                        @if (! empty($userAgent))
                            <li>{{ __('nuki::mail.login_otp.browser', ['ua' => $userAgent]) }}</li>
                        @endif
                    </ul>
                @endif

                <p style="font-size: 12px; color: #71717a; margin: 0;">
                    {{ __('nuki::mail.login_otp.security_note') }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
