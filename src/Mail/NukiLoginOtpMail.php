<?php

declare(strict_types=1);

namespace Darvis\Nuki\Mail;

use Darvis\Nuki\Support\NukiConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NukiLoginOtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly int $expiryMinutes,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $recipientName = null,
    ) {}

    public function envelope(): Envelope
    {
        $from = null;
        $address = NukiConfig::mailFromAddress();
        if (! empty($address)) {
            $from = new Address(
                address: (string) $address,
                name: (string) (NukiConfig::mailFromName() ?? NukiConfig::uiBrand()),
            );
        }

        return new Envelope(
            from: $from,
            subject: (string) __('nuki::mail.login_otp.subject', [
                'brand' => NukiConfig::uiBrand(),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'nuki::mail.login-otp');
    }
}
