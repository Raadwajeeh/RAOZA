<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MariaDbShippingConcurrencyTest extends TestCase
{
    public function test_order_row_lock_excludes_a_second_shipping_transaction_on_mariadb(): void
    {
        if(DB::connection()->getDriverName()!=='mysql')$this->markTestSkipped('Requires a disposable MariaDB/MySQL test database; production is never used.');
        $database=(string)config('database.connections.'.config('database.default').'.database');
        if(!app()->environment('testing')||!preg_match('/(^|[_-])test([_-]|$)/i',$database))$this->markTestSkipped('Refusing destructive setup: database name must explicitly identify a test database.');
        $this->artisan('migrate:fresh',['--force'=>true])->assertExitCode(0);

        $config=config('database.connections.'.config('database.default'));
        config(['database.connections.shipping_concurrency'=>$config]);
        $orderId=DB::table('orders')->insertGetId([
            'order_number'=>'RZ-LOCK-TEST','customer_email'=>'lock@example.test','currency'=>'EUR',
            'subtotal_amount'=>1000,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>174,'total_amount'=>1000,
            'order_status'=>'confirmed','payment_status'=>'paid','fulfillment_status'=>'ready_to_ship','placed_at'=>now(),'paid_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        $primary=DB::connection();$secondary=DB::connection('shipping_concurrency');
        $primary->beginTransaction();
        try{
            $primary->table('orders')->where('id',$orderId)->lockForUpdate()->first();
            $secondary->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $secondary->beginTransaction();
            try{
                $secondary->table('orders')->where('id',$orderId)->lockForUpdate()->first();
                $this->fail('The second transaction unexpectedly acquired the locked order row.');
            }catch(\Illuminate\Database\QueryException){$this->addToAssertionCount(1);}
            finally{if($secondary->transactionLevel()>0)$secondary->rollBack();}
        }finally{if($primary->transactionLevel()>0)$primary->rollBack();DB::purge('shipping_concurrency');}
    }
}
