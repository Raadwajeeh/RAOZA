<?php
return ['default'=>env('PAYMENT_PROVIDER',env('APP_ENV')==='local'?'demo':'mollie'),'mollie'=>['api_key'=>env('MOLLIE_API_KEY'),'base_url'=>env('MOLLIE_API_BASE_URL','https://api.mollie.com/v2')]];
