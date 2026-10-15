<?php

namespace Database\Seeders;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Fulfillment\Models\ShippingCarrier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class E2eSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || ! config('commerce.demo_mode')) {
            throw new RuntimeException('E2E fixtures are restricted to testing with demo payments enabled.');
        }
        $customerPassword=(string) env('E2E_CUSTOMER_PASSWORD');
        $intruderPassword=(string) env('E2E_INTRUDER_PASSWORD');
        if ($customerPassword === '' || $intruderPassword === '') throw new RuntimeException('E2E fixture passwords must be supplied by the isolated test runner.');

        $this->call(DatabaseSeeder::class);
        $customer=User::query()->updateOrCreate(['email'=>'customer.e2e@raoza.test'],[
            'name'=>'RAOZA E2E Customer','phone'=>'+31600000000','password'=>Hash::make($customerPassword),
            'email_verified_at'=>now(),'role'=>AdminRole::Customer,'is_admin'=>false,
        ]);
        $customer->addresses()->updateOrCreate(['label'=>'E2E Home'],[
            'first_name'=>'E2E','last_name'=>'Customer','phone'=>'+31600000000','street'=>'Teststraat',
            'house_number'=>'10','postal_code'=>'1012AB','city'=>'Amsterdam','country_code'=>'NL',
            'is_shipping'=>true,'is_billing'=>true,'is_default_shipping'=>true,'is_default_billing'=>true,
        ]);
        User::query()->updateOrCreate(['email'=>'intruder.e2e@raoza.test'],[
            'name'=>'RAOZA E2E Intruder','password'=>Hash::make($intruderPassword),
            'email_verified_at'=>now(),'role'=>AdminRole::Customer,'is_admin'=>false,
        ]);
        ShippingCarrier::query()->updateOrCreate(['code'=>'e2e-manual'],[
            'name'=>'E2E Manual Carrier','integration_key'=>'manual',
            'tracking_url_template'=>'https://tracking.example.test/{tracking_number}','active'=>true,'position'=>1,
            'notes'=>'Testing-only manual carrier.',
        ]);
    }
}
