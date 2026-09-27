<?php
namespace App\Domain\Payments\Providers;
use App\Domain\Commerce\Models\Order;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Data\ProviderPayment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class MolliePaymentProvider implements PaymentProvider {
 public function name():string{return 'mollie';}
 public function create(Order $order,string $redirectUrl,string $webhookUrl):ProviderPayment {
  $json=$this->client()->post('/payments',['amount'=>['currency'=>$order->currency,'value'=>$this->decimal($order->total_amount)],'description'=>'RAOZA '.$order->order_number,'redirectUrl'=>$redirectUrl,'webhookUrl'=>$webhookUrl,'metadata'=>['order_number'=>$order->order_number]])->throw()->json(); return $this->map($json);
 }
 public function fetch(string $id):ProviderPayment{return $this->map($this->client()->get('/payments/'.rawurlencode($id))->throw()->json());}
 private function client():PendingRequest { $key=(string)config('payments.mollie.api_key'); if($key==='')throw new RuntimeException('MOLLIE_API_KEY is not configured.'); return Http::baseUrl((string)config('payments.mollie.base_url'))->withToken($key)->acceptJson()->asJson()->timeout(10)->retry(2,200); }
 private function map(array $j):ProviderPayment { $amount=(string)($j['amount']['value']??'0.00'); return new ProviderPayment((string)$j['id'],(string)$j['status'],$this->minor($amount),strtoupper((string)($j['amount']['currency']??'EUR')),$j['_links']['checkout']['href']??null,$j['method']??null,$j['metadata']['order_number']??null,$j); }
 private function decimal(int $minor):string{return number_format($minor/100,2,'.','');}
 private function minor(string $decimal):int { [$a,$b]=array_pad(explode('.',$decimal,2),2,''); return ((int)$a*100)+(int)str_pad(substr($b,0,2),2,'0'); }
}
