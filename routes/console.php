<?php

use App\Support\Sunat\SunatEndpoints;
use Greenter\See;
use Illuminate\Support\Facades\Artisan;

Artisan::command('sunat:diagnose', function (): void {
    $environment = (string) config('sunat.environment');
    $endpoints = app(SunatEndpoints::class);

    $this->info('NecoFact SUNAT diagnose (no network).');
    $this->line('PHP: '.PHP_VERSION);
    $this->line('Greenter See: '.(class_exists(See::class) ? 'loaded' : 'MISSING'));
    $this->line('ext-soap: '.(extension_loaded('soap') ? 'yes' : 'NO'));
    $this->line('ext-zlib: '.(extension_loaded('zlib') ? 'yes' : 'NO'));
    $this->line('ext-openssl: '.(extension_loaded('openssl') ? 'yes' : 'NO'));
    $this->line('ext-curl: '.(extension_loaded('curl') ? 'yes' : 'NO'));
    $this->line('environment: '.$environment);
    $this->line('fe: '.$endpoints->billService());
    $this->line('consulta_cdr: '.$endpoints->cdrConsult());
    $this->line('gre auth: '.$endpoints->greAuth());
    $this->line('gre cpe: '.$endpoints->greCpe());
    $this->line('invoice send days: '.(string) config('sunat.deadlines.invoice_send_days'));
    $this->line('rc send days: '.(string) config('sunat.deadlines.receipt_summary_days'));
    $this->line('certificate configured: '.(filled(config('sunat.certificate_path')) ? 'yes' : 'no'));
    $this->warn('This command never calls SUNAT. Use sunat:smoke (later vertical) against beta.');
})->purpose('Print SUNAT/Greenter local configuration without contacting SUNAT.');
