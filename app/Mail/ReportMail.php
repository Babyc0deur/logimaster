<?php

namespace App\Mail;

use App\Models\Report;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

/** Email de transmission d'un ou plusieurs rapports (pièces jointes PDF / Excel). */
class ReportMail extends Mailable
{
    /** @param array<int, Report> $reports */
    public function __construct(public array $reports) {}

    public function envelope(): Envelope
    {
        $first = $this->reports[0];

        return new Envelope(subject: 'LogiMaster Pro — '.$first->titre);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.report', with: ['reports' => $this->reports]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return array_map(
            fn (Report $r) => Attachment::fromStorageDisk('local', $r->fichier_path)
                ->as(Str::slug($r->titre).'.'.$r->format)
                ->withMime($r->format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            $this->reports
        );
    }
}
