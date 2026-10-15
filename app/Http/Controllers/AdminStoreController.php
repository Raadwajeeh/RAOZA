<?php

namespace App\Http\Controllers;

use App\Domain\Admin\Services\AuditService;
use App\Domain\Admin\Services\OwnerReadinessService;
use App\Domain\Content\Models\SiteContent;
use App\Domain\Content\Services\StoreInformation;
use App\Domain\Fulfillment\Models\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminStoreController
{
    public function __construct(private AuditService $audit, private OwnerReadinessService $readiness) {}

    public function index(StoreInformation $storeInformation)
    {
        return Inertia::render('Admin/Store/Index', [
            'store'=>$storeInformation->get(),
            'storeDraft'=>SiteContent::where('key', 'store_information_draft')->value('value'),
            'shippingMethods'=>ShippingMethod::orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function updateStore(Request $request)
    {
        $data=$request->validate([
            'brand_name'=>'required|string|max:120','company_name'=>'nullable|string|max:160',
            'contact_email'=>'nullable|email|max:254','support_email'=>'nullable|email|max:254','contact_phone'=>['nullable','string','max:40','regex:/^\+?[0-9 ()-]{7,24}$/'],
            'street'=>'nullable|string|max:160','house_number'=>'nullable|string|max:32','addition'=>'nullable|string|max:32',
            'postal_code'=>['nullable','string','regex:/^\d{4}\s?[A-Z]{2}$/i'],'city'=>'nullable|string|max:120','country'=>'required|string|max:120','country_code'=>'required|string|size:2',
            'registration_number'=>['nullable','string','regex:/^\d{8}$/'],'vat_number'=>['nullable','string','regex:/^NL\d{9}B\d{2}$/i'],
            'currency'=>'required|string|size:3','locale'=>'required|string|max:16','shipping_origin'=>'nullable|string|max:160',
            'customer_service'=>'nullable|string|max:500','instagram_url'=>'nullable|url|max:2048',
        ]);
        $record=SiteContent::firstOrNew(['key'=>'store_information_draft']);
        $before=$record->exists?$record->value:null;
        $record->value=$data; $record->save();
        $this->readiness->supersede('business.identity');
        $this->readiness->supersede('commercial.delivery_scope');
        $this->audit->record($request,'store.information_draft.updated',$record,$before,$data);
        return back()->with('success','Business information saved as a draft. Public store and invoice snapshot values were not changed.');
    }

    public function storeShipping(Request $request)
    {
        $data=$this->shippingData($request); $data['code']=$data['code']?:Str::slug($data['name']);
        $description=$data['description']??null; unset($data['description']); $data['configuration']=$description?['description'=>$description]:null;
        $requested=$data; $data['active']=false; $data['pending_configuration']=$requested; $data['pending_hash']=$this->readiness->hash($requested);
        $method=ShippingMethod::create($data); $this->readiness->supersede('commercial.shipping_methods'); $this->audit->record($request,'shipping_method.draft_created',$method,null,$method->toArray());
        return back()->with('success','Shipping method saved disabled with a pending configuration. Checkout was not changed.');
    }

    public function updateShipping(Request $request, ShippingMethod $shippingMethod)
    {
        $before=$shippingMethod->toArray(); $data=$this->shippingData($request,$shippingMethod); $data['code']=$data['code']?:Str::slug($data['name']);
        $description=$data['description']??null; unset($data['description']); $data['configuration']=$description?['description'=>$description]:null;
        $shippingMethod->update(['pending_configuration'=>$data,'pending_hash'=>$this->readiness->hash($data)]); $this->readiness->supersede('commercial.shipping_methods'); $this->audit->record($request,'shipping_method.draft_updated',$shippingMethod,$before,$shippingMethod->fresh()->toArray());
        return back()->with('success','Shipping changes saved for review. The live checkout method was not changed.');
    }

    private function shippingData(Request $request, ?ShippingMethod $method=null): array
    {
        return $request->validate([
            'name'=>'required|string|max:120','code'=>['nullable','string','max:80',Rule::unique('shipping_methods','code')->ignore($method?->id)],
            'price'=>'required|integer|min:0','currency'=>'required|string|size:3','active'=>'boolean','position'=>'required|integer|min:0|max:10000',
            'provider'=>['required',Rule::in(['manual','demo'])],'description'=>'nullable|string|max:500',
        ]);
    }
}
