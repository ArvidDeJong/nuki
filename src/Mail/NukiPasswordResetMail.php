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

class NukiPasswordResetMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $resetUrl,
        public readonly int $expiryMinutes,
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
            subject: (string) __('nuki::mail.password_reset.subject', [
                'brand' => NukiConfig::uiBrand(),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'nuki::mail.password-reset');
    }
}
