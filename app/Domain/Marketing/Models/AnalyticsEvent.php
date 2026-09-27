<?php
namespace App\Domain\Marketing\Models; use Illuminate\Database\Eloquent\Model; class AnalyticsEvent extends Model {protected $fillable=['event_id','event_name','order_id','session_hash','payload','occurred_at'];protected function casts():array{return ['payload'=>'array','occurred_at'=>'immutable_datetime'];}}
