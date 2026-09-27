<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('payments', function(Blueprint $t){
   $t->id(); $t->foreignId('order_id')->constrained()->restrictOnDelete(); $t->string('provider',32); $t->string('provider_payment_id')->nullable(); $t->string('method',64)->nullable(); $t->char('currency',3); $t->unsignedBigInteger('amount'); $t->string('status',32)->index(); $t->timestampTz('provider_created_at')->nullable(); $t->timestampTz('paid_at')->nullable(); $t->timestampTz('failed_at')->nullable(); $t->timestampTz('cancelled_at')->nullable(); $t->timestampTz('expired_at')->nullable(); $t->jsonb('metadata')->nullable(); $t->timestampsTz();
   $t->unique(['provider','provider_payment_id']); $t->index(['order_id','created_at']);
  });
  Schema::create('payment_events', function(Blueprint $t){
   $t->id(); $t->foreignId('payment_id')->constrained()->cascadeOnDelete(); $t->string('event_key',128)->unique(); $t->string('event_type',64); $t->string('provider_status',32)->nullable(); $t->jsonb('payload')->nullable(); $t->timestampTz('processed_at')->nullable(); $t->timestampTz('created_at'); $t->index(['payment_id','created_at']);
  });
 }
 public function down(): void { Schema::dropIfExists('payment_events'); Schema::dropIfExists('payments'); }
};
