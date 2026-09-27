<?php
namespace App\Domain\Content\Models; use Illuminate\Database\Eloquent\Model; class SiteContent extends Model {protected $table='site_content';protected $fillable=['key','value'];protected function casts():array{return ['value'=>'array'];}}
