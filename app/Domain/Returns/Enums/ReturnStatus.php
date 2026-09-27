<?php
namespace App\Domain\Returns\Enums;
enum ReturnStatus:string { case Requested='requested'; case UnderReview='under_review'; case Approved='approved'; case AwaitingReturn='awaiting_return'; case Received='received'; case Inspected='inspected'; case Completed='completed'; case Rejected='rejected'; case Cancelled='cancelled'; }
