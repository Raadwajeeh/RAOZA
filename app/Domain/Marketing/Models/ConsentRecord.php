<?php
namespace App\Domain\Marketing\Models; use Illuminate\Database\Eloquent\Model; class ConsentRecord extends Model {protected $fillable=['consent_id','user_id','analytics','marketing','policy_version','locale','consented_at'];protected function casts():array{return ['analytics'=>'boolean','marketing'=>'boolean','consented_at'=>'immutable_datetime'];}}
