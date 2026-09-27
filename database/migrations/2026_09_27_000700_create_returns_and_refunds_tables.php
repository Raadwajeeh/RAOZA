<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('returns',function(Blueprint $t){$t->id();$t->string('return_number')->unique();$t->foreignId('order_id')->constrained()->restrictOnDelete();$t->string('status',32)->index();$t->string('reason_summary')->nullable();$t->timestampTz('requested_at');$t->timestampTz('approved_at')->nullable();$t->timestampTz('received_at')->nullable();$t->timestampTz('completed_at')->nullable();$t->timestampTz('cancelled_at')->nullable();$t->text('customer_note')->nullable();$t->text('admin_note')->nullable();$t->timestampsTz();$t->index(['order_id','created_at']);});
  Schema::create('return_items',function(Blueprint $t){$t->id();$t->foreignId('return_id')->constrained('returns')->cascadeOnDelete();$t->foreignId('order_item_id')->constrained()->restrictOnDelete();$t->unsignedInteger('quantity');$t->string('reason_code',64);$t->text('reason_text')->nullable();$t->string('condition',32)->nullable();$t->string('resolution',32)->default('pending');$t->timestampTz('restocked_at')->nullable();$t->timestampsTz();$t->unique(['return_id','order_item_id']);});
  Schema::create('refunds',function(Blueprint $t){$t->id();$t->foreignId('payment_id')->constrained()->restrictOnDelete();$t->foreignId('order_id')->constrained()->restrictOnDelete();$t->foreignId('return_id')->nullable()->constrained('returns')->nullOnDelete();$t->string('provider_refund_id')->nullable();$t->unsignedBigInteger('amount');$t->char('currency',3);$t->string('reason')->nullable();$t->string('status',32)->index();$t->string('idempotency_key',128)->unique();$t->timestampTz('requested_at');$t->timestampTz('processed_at')->nullable();$t->jsonb('metadata')->nullable();$t->timestampsTz();$t->unique(['payment_id','provider_refund_id']);$t->index(['order_id','created_at']);});
 }
 public function down():void {Schema::dropIfExists('refunds');Schema::dropIfExists('return_items');Schema::dropIfExists('returns');}
};
