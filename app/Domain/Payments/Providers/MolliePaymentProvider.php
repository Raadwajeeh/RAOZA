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
 private function map(array $j):ProviderPayment { $id=$j['id']??null;$status=$j['status']??null;$currency=$j['amount']['currency']??null;$amount=$j['amount']['value']??null;if(!is_string($id)||$id===''||!is_string($status)||$status===''||!is_string($currency)||strlen($currency)!==3||!is_string($amount))throw new RuntimeException('Mollie returned an invalid payment response.');return new ProviderPayment($id,$status,$this->minor($amount),strtoupper($currency),$j['_links']['checkout']['href']??null,$j['method']??null,$j['metadata']['order_number']??null,$j); }
 private function decimal(int $minor):string{if($minor<0)throw new RuntimeException('Payment amount cannot be negative.');return intdiv($minor,100).'.'.str_pad((string)($minor%100),2,'0',STR_PAD_LEFT);}
 private function minor(string $decimal):int {if(!preg_match('/^(0|[1-9][0-9]*)\.[0-9]{2}$/D',$decimal,$matches))throw new RuntimeException('Mollie returned an invalid payment amount.');return ((int)$matches[1]*100)+(int)substr($decimal,-2); }
}
