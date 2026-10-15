<?php

namespace App\Mail;

use App\Domain\Invoicing\Models\Invoice;

/**
 * Prepared for a later, explicitly approved mail workflow. It is not dispatched automatically.
 */
class InvoiceAvailableMail extends TransactionalMail
{
    public function __construct(public Invoice $invoice) {}

    public function build(): static
    {
        $mail = $this->subject('Your RAOZA document '.$this->invoice->document_number)
            ->view('emails.invoice-available')
            ->with('store', $this->storeInformation());

        if ($this->invoice->pdf_path) {
            $mail->attachFromStorageDisk(
                (string) config('invoicing.disk', 'local'),
                $this->invoice->pdf_path,
                $this->invoice->document_number.'.pdf',
                ['mime' => 'application/pdf'],
            );
        }

        return $mail;
    }
}
