<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Attachment;

/**
 * Email scritta a mano dal venditore a uno o più clienti registrati, con eventuali allegati.
 */
class CustomerMessageMail extends TemplatedMail
{
    /**
     * @param  list<array{path: string, name: string, mime: ?string}>  $files
     */
    public function __construct(
        string $mailSubject,
        string $mailBody,
        public array $files = [],
    ) {
        parent::__construct($mailSubject, $mailBody);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return array_map(function (array $file): Attachment {
            $attachment = Attachment::fromPath($file['path'])->as($file['name']);

            return $file['mime'] ? $attachment->withMime($file['mime']) : $attachment;
        }, $this->files);
    }
}
