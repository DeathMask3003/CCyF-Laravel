<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

class RegistrationNotice extends Mailable
{
    use Queueable, SerializesModels;

    private const HEADER_CID = 'cabecera-registro@ccyf.cobaemex.edu.mx';

    public function __construct(public object $record, public string $audience)
    {
        $this->withSymfonyMessage(function (Email $email): void {
            $email->addPart((new DataPart(
                new File(resource_path('images/cabezera.png')),
                'cabecera-institucional.png', 'image/png',
            ))->asInline()->setContentId(self::HEADER_CID));
        });
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Registro recibido · CCyF '.$this->record->folio);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.registration-notice', text: 'emails.registration-notice-text',
            with: ['institutionalHeaderCid' => 'cid:'.self::HEADER_CID]);
    }
}
