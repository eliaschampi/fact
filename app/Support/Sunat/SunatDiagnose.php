<?php

namespace App\Support\Sunat;

use Greenter\See;

final class SunatDiagnose
{
    public function __construct(private readonly SunatEndpoints $endpoints) {}

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return [
            'PHP: '.PHP_VERSION,
            'Greenter See: '.(class_exists(See::class) ? 'loaded' : 'MISSING'),
            'ext-soap: '.(extension_loaded('soap') ? 'yes' : 'NO'),
            'ext-zlib: '.(extension_loaded('zlib') ? 'yes' : 'NO'),
            'ext-openssl: '.(extension_loaded('openssl') ? 'yes' : 'NO'),
            'ext-curl: '.(extension_loaded('curl') ? 'yes' : 'NO'),
            'environment: '.$this->endpoints->environment(),
            'fe: '.$this->endpoints->billService(),
            'consulta_cdr: '.$this->endpoints->cdrConsult(),
            'gre auth: '.$this->endpoints->greAuth(),
            'gre cpe: '.$this->endpoints->greCpe(),
            'invoice send days: '.(string) config('sunat.deadlines.invoice_send_days'),
            'rc send days: '.(string) config('sunat.deadlines.receipt_summary_days'),
            'certificate configured: '.(filled(config('sunat.certificate_path')) ? 'yes' : 'no'),
        ];
    }
}
