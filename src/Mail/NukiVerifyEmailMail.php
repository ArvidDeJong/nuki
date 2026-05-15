<?php

declare(strict_types=1);

namespace Darvis\Nuki\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NukiVerifyEmailMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $verifyUrl,
        public readonly int $expiryMinutes,
        public readonly ?string $recipientName = null,
    ) {}

    public function envelope(): Envelope
    {
        $from = null;
        $address = config('nuki.auth_users.mail.from.address');
        if (! empty($address)) {
            $from = new Address(
                address: (string) $address,
                name: (string) (config('nuki.auth_users.mail.from.name') ?? config('nuki.ui.brand', 'NUKI')),
            );
        }

        return new Envelope(
            from: $from,
            subject: (string) __('nuki::mail.verify_email.subject', [
                'brand' => (string) config('nuki.ui.brand', 'NUKI'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'nuki::mail.verify-email');
    }
}
