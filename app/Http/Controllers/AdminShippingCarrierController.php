<?php

namespace App\Http\Controllers;

use App\Domain\Admin\Services\AuditService;
use App\Domain\Fulfillment\Models\ShippingCarrier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminShippingCarrierController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function store(Request $request): RedirectResponse
    {
        $data=$this->validated($request);
        $data['code']=$data['code']?:Str::slug($data['name']);
        $carrier=ShippingCarrier::create($data);
        $this->audit->record($request,'shipping_carrier.created',$carrier,null,$carrier->toArray());
        return back()->with('success','Carrier created for manual fulfillment.');
    }

    public function update(Request $request,ShippingCarrier $shippingCarrier): RedirectResponse
    {
        $before=$shippingCarrier->toArray();
        $data=$this->validated($request,$shippingCarrier);
        $data['code']=$data['code']?:Str::slug($data['name']);
        $shippingCarrier->update($data);
        $this->audit->record($request,'shipping_carrier.updated',$shippingCarrier,$before,$shippingCarrier->fresh()->toArray());
        return back()->with('success','Carrier settings updated. Existing shipment snapshots were not changed.');
    }

    private function validated(Request $request,?ShippingCarrier $carrier=null):array
    {
        $data=$request->validate([
            'name'=>['required','string','max:120'],
            'code'=>['nullable','string','max:80','regex:/^[a-z0-9-]+$/',Rule::unique('shipping_carriers','code')->ignore($carrier?->id)],
            'integration_key'=>['required',Rule::in(['manual','postnl','dhl'])],
            'tracking_url_template'=>['nullable','string','max:2048'],
            'active'=>['required','boolean'],'position'=>['required','integer','min:0','max:10000'],'notes'=>['nullable','string','max:1000'],
        ]);
        if($data['tracking_url_template']){
            $template=$data['tracking_url_template'];
            if(substr_count($template,'{tracking_number}')!==1)$this->templateError($request);
            $parts=parse_url(str_replace('{tracking_number}','TEST123',$template));
            if(($parts['scheme']??null)!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass']))$this->templateError($request);
        }
        return $data;
    }

    private function templateError(Request $request):never
    {
        throw \Illuminate\Validation\ValidationException::withMessages(['tracking_url_template'=>'Use one {tracking_number} placeholder in a credential-free HTTPS URL.']);
    }
}
