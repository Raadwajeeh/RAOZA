<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('orders',function(Blueprint $t){
   $t->foreignId('shipping_method_id')->nullable()->after('source_cart_id')->constrained('shipping_methods')->nullOnDelete();
   $t->string('shipping_method_name')->nullable()->after('shipping_method_id');
   $t->string('discount_code',50)->nullable()->after('currency');
   $t->unsignedSmallInteger('tax_rate_basis_points')->default(0)->after('tax_amount');
  });
  Schema::create('discount_usages',function(Blueprint $t){
   $t->id();$t->foreignId('discount_id')->constrained()->restrictOnDelete();$t->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
   $t->string('customer_email',254)->index();$t->unsignedBigInteger('amount');$t->timestampTz('created_at')->useCurrent();
   $t->index(['discount_id','customer_email']);
  });
 }
 public function down():void {
  Schema::dropIfExists('discount_usages');
  Schema::table('orders',function(Blueprint $t){$t->dropConstrainedForeignId('shipping_method_id');$t->dropColumn(['shipping_method_name','discount_code','tax_rate_basis_points']);});
 }
};
