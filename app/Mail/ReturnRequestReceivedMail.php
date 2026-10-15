<?php
namespace App\Mail;
use App\Domain\Returns\Models\ReturnRequest;
final class ReturnRequestReceivedMail extends TransactionalMail {
 public function __construct(public ReturnRequest $return){}
 public function build():static{return $this->subject('RAOZA return request '.$this->return->return_number)->view('emails.return-requested')->with('store',$this->storeInformation());}
}
