<?php

use App\Support\Sunat\SunatDiagnose;
use Illuminate\Support\Facades\Artisan;

Artisan::command('sunat:diagnose', function (SunatDiagnose $diagnose): void {
    $this->info('NecoFact SUNAT diagnose (no network).');

    foreach ($diagnose->lines() as $line) {
        $this->line($line);
    }

    $this->warn('This command never calls SUNAT. Use sunat:smoke (later vertical) against beta.');
})->purpose('Print SUNAT/Greenter local configuration without contacting SUNAT.');
