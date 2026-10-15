<?php
namespace App\Http\Controllers;
use App\Domain\Fulfillment\Models\Shipment;
use App\Domain\Fulfillment\Services\ManualAddressLabelService;
use Symfony\Component\HttpFoundation\Response;
class AdminShipmentDocumentController extends Controller
{
    public function label(Shipment $shipment,ManualAddressLabelService $service):Response
    {
        return response($service->render($shipment),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="RAOZA-'.$shipment->order->order_number.'-manual-A6.pdf"','Cache-Control'=>'private, no-store']);
    }
}
