<?php
namespace App\Domain\Fulfillment\Services;
use App\Domain\Fulfillment\Models\ShippingMethod;
use Illuminate\Validation\ValidationException;
class ShippingQuoteService {
 public function active():array{return ShippingMethod::query()->where('active',true)->orderBy('position')->get()->map(fn($m)=>['id'=>$m->id,'name'=>$m->name,'code'=>$m->code,'price'=>$m->price,'currency'=>$m->currency,'description'=>$m->configuration['description']??null])->all();}
 public function resolve(int $id):ShippingMethod{$m=ShippingMethod::query()->whereKey($id)->where('active',true)->first();if(!$m)throw ValidationException::withMessages(['shipping_method_id'=>'Please select an available shipping method.']);return $m;}
}
