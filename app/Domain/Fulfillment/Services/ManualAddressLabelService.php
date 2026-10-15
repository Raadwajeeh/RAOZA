<?php

namespace App\Domain\Fulfillment\Services;

use App\Domain\Fulfillment\Models\Shipment;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

final class ManualAddressLabelService
{
    public function render(Shipment $shipment):string
    {
        $shipment->loadMissing(['order.addresses']);
        $address=$shipment->order->addresses->firstWhere('type','shipping');
        if(!$address)throw new RuntimeException('This order has no shipping address snapshot.');
        $options=new Options();
        $options->set('isRemoteEnabled',false);$options->set('isPhpEnabled',false);$options->set('defaultFont','DejaVu Sans');
        $dompdf=new Dompdf($options);
        $dompdf->setPaper([0,0,297.64,419.53]);
        $dompdf->loadHtml(view('documents.manual-address-label',['shipment'=>$shipment,'address'=>$address])->render(),'UTF-8');
        $dompdf->render();
        return $dompdf->output();
    }
}
