<?php

namespace App\Http\Controllers;

use App\Domain\Admin\Services\AuditService;
use App\Domain\Content\Models\SiteContent;
use App\Domain\Content\Services\StoreInformation;
use App\Domain\Fulfillment\Models\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminStoreController
{
    public function __construct(private AuditService $audit) {}

    public function index(StoreInformation $storeInformation)
    {
        return Inertia::render('Admin/Store/Index', [
            'store'=>$storeInformation->get(),
            'shippingMethods'=>ShippingMethod::orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function updateStore(Request $request)
    {
        $data=$request->validate([
            'brand_name'=>'required|string|max:120','company_name'=>'required|string|max:160',
            'contact_email'=>'required|email|max:254','support_email'=>'required|email|max:254','contact_phone'=>'nullable|string|max:40',
            'street'=>'nullable|string|max:160','house_number'=>'nullable|string|max:32','addition'=>'nullable|string|max:32',
            'postal_code'=>'nullable|string|max:24','city'=>'nullable|string|max:120','country'=>'required|string|max:120','country_code'=>'required|string|size:2',
            'registration_number'=>'nullable|string|max:80','vat_number'=>'nullable|string|max:80',
            'currency'=>'required|string|size:3','locale'=>'required|string|max:16','shipping_origin'=>'required|string|max:160',
            'customer_service'=>'required|string|max:500','instagram_url'=>'nullable|url|max:2048',
        ]);
        $record=SiteContent::updateOrCreate(['key'=>'store_information'],['value'=>$data]);
        $this->audit->record($request,'store.information.updated',$record,null,$data);
        return back()->with('success','Store information saved. Empty fields remain unpublished.');
    }

    public function storeShipping(Request $request)
    {
        $data=$this->shippingData($request); $data['code']=$data['code']?:Str::slug($data['name']);
        $description=$data['description']??null; unset($data['description']); $data['configuration']=$description?['description'=>$description]:null;
        $method=ShippingMethod::create($data); $this->audit->record($request,'shipping_method.created',$method,null,$method->toArray());
        return back()->with('success','Shipping method created.');
    }

    public function updateShipping(Request $request, ShippingMethod $shippingMethod)
    {
        $before=$shippingMethod->toArray(); $data=$this->shippingData($request,$shippingMethod); $data['code']=$data['code']?:Str::slug($data['name']);
        $description=$data['description']??null; unset($data['description']); $data['configuration']=$description?['description'=>$description]:null;
        $shippingMethod->update($data); $this->audit->record($request,'shipping_method.updated',$shippingMethod,$before,$shippingMethod->fresh()->toArray());
        return back()->with('success','Shipping method updated.');
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
