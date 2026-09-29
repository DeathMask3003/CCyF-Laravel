<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DecisionNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public object $record,
        public string $audience,
        public array $documents,
        public string $letter,
        public int $part,
        public int $totalParts,
    ) {}

    public function envelope(): Envelope
    {
        $result = $this->record->decision === 'designado' ? 'Designación' : 'Resultado';
        $suffix = $this->totalParts > 1 ? ' · Parte '.$this->part.' de '.$this->totalParts : '';

        return new Envelope(subject: $result.' del concurso CCyF · '.$this->record->folio.$suffix);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.decision');
    }

    public function attachments(): array
    {
        $attachments = [Attachment::fromData(fn (): string => $this->letter,
            'resultado-'.$this->record->folio.'.pdf')->withMime('application/pdf')];

        foreach ($this->documents as $document) {
            $attachments[] = Attachment::fromPath($document['path'])
                ->as($document['name'])->withMime($document['mime']);
        }

        return $attachments;
    }
}
