<?php
use Illuminate\Support\Facades\Artisan;
Artisan::command('raoza:about', function () { $this->info('RAOZA — Urban Editorial commerce foundation'); })->purpose('Show RAOZA project identity');
