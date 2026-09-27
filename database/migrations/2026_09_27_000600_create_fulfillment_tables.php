<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('shipping_methods',function(Blueprint $t){$t->id();$t->string('name');$t->string('code')->unique();$t->string('provider')->nullable();$t->unsignedBigInteger('price');$t->char('currency',3)->default('EUR');$t->boolean('active')->default(true)->index();$t->unsignedInteger('position')->default(0);$t->jsonb('configuration')->nullable();$t->timestampsTz();});
  Schema::create('shipments',function(Blueprint $t){$t->id();$t->foreignId('order_id')->constrained()->restrictOnDelete();$t->foreignId('shipping_method_id')->nullable()->constrained('shipping_methods')->nullOnDelete();$t->string('provider')->nullable();$t->string('provider_shipment_id')->nullable();$t->string('service_code')->nullable();$t->string('status',32)->default('pending')->index();$t->string('tracking_number')->nullable();$t->text('tracking_url')->nullable();$t->unsignedBigInteger('shipping_cost')->default(0);$t->char('currency',3)->default('EUR');$t->string('label_path')->nullable();$t->timestampTz('shipped_at')->nullable();$t->timestampTz('delivered_at')->nullable();$t->timestampsTz();$t->unique(['provider','provider_shipment_id']);$t->index(['order_id','status']);});
  Schema::create('shipment_items',function(Blueprint $t){$t->id();$t->foreignId('shipment_id')->constrained()->cascadeOnDelete();$t->foreignId('order_item_id')->constrained()->restrictOnDelete();$t->unsignedInteger('quantity');$t->timestampsTz();$t->unique(['shipment_id','order_item_id']);});
  Schema::create('shipment_events',function(Blueprint $t){$t->id();$t->foreignId('shipment_id')->constrained()->cascadeOnDelete();$t->string('external_event_id')->nullable();$t->string('status',32)->index();$t->text('description')->nullable();$t->timestampTz('occurred_at');$t->timestampTz('created_at');$t->unique(['shipment_id','external_event_id']);});
 }
 public function down():void {Schema::dropIfExists('shipment_events');Schema::dropIfExists('shipment_items');Schema::dropIfExists('shipments');Schema::dropIfExists('shipping_methods');}
};