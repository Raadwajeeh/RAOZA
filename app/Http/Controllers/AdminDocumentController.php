<?php

namespace App\Http\Controllers;

use App\Domain\Commerce\Models\Order;
use App\Domain\Invoicing\Models\Invoice;
use App\Domain\Invoicing\Services\InvoicePdfService;
use App\Domain\Invoicing\Services\OperationalDocumentService;
use Symfony\Component\HttpFoundation\Response;

class AdminDocumentController extends Controller
{
    public function accounting(Order $order, string $documentNumber, InvoicePdfService $pdf): Response
    {
        $document = Invoice::query()->where('order_id', $order->id)->where('document_number', $documentNumber)->firstOrFail();
        return $this->pdfResponse($pdf->contents($document), $document->document_number.'.pdf', false);
    }

    public function operational(Order $order, string $type, OperationalDocumentService $documents): Response
    {
        abort_unless(in_array($type, OperationalDocumentService::TYPES, true), 404);
        return $this->pdfResponse($documents->render($order, $type), $type.'-'.$order->order_number.'.pdf', false);
    }

    private function pdfResponse(string $contents, string $filename, bool $attachment): Response
    {
        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($attachment ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
