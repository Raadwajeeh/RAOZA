<?php
namespace App\Mail;
use App\Domain\Returns\Models\ReturnRequest;
final class ReturnStatusUpdatedMail extends TransactionalMail {
 public function __construct(public ReturnRequest $return){}
 public function build():static{return $this->subject('RAOZA return update '.$this->return->return_number)->view('emails.return-status')->with('store',$this->storeInformation());}
}
