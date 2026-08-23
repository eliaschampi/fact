<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | beta        — SUNAT structural XML tests (01/03/07/08). Never real
    |               taxpayer effect. Auth: {RUC}MODDATOS / MODDATOS.
    |               Certificate need not be registered. Does not cover GRE.
    | production  — live SEE del Contribuyente for one issuer.
    |
    | Homologación is a different SOAP host from beta. Do not mix them.
    | SUNAT authenticates the issuer, not this repository.
    |
    */

    'environment' => env('SUNAT_ENVIRONMENT', 'beta'),

    /*
    |--------------------------------------------------------------------------
    | SOAP / REST endpoints
    |--------------------------------------------------------------------------
    |
    | Verified 2026-08-23 against:
    | - https://orientacion.sunat.gob.pe/guias-manuales-y-servicios-web
    | - Greenter v5.3.0 Greenter\Ws\Services\SunatEndpoints
    | - https://greenter.dev/production/ (GRE REST)
    |
    | A second official page ("Rutas para pruebas y envíos - CPE - SEE
    | Contribuyente") publishes ol-ti-itemision-otroscpe-gem hosts. Those
    | are the "otros CPE" / retention-perception service, not the invoice
    | billService. Keep them here as config, never as Action constants.
    |
    */

    'endpoints' => [
        'fe' => [
            'beta' => 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService',
            'production' => 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService',
            'homologacion' => 'https://www.sunat.gob.pe/ol-ti-itcpgem-sqa/billService',
            'consulta_cdr' => 'https://e-factura.sunat.gob.pe/ol-it-wsconscpegem/billConsultService',
        ],
        'otros_cpe' => [
            'beta' => 'https://e-beta.sunat.gob.pe/ol-ti-itemision-otroscpe-gem-beta/billService',
            'production' => 'https://e-factura.sunat.gob.pe/ol-ti-itemision-otroscpe-gem/billService',
            'production_alt' => 'https://www.sunat.gob.pe/ol-ti-itemision-otroscpe-gem/billService',
        ],
        'gre' => [
            'auth' => env('SUNAT_GRE_AUTH_URL', 'https://api-seguridad.sunat.gob.pe/v1'),
            'cpe' => env('SUNAT_GRE_CPE_URL', 'https://api-cpe.sunat.gob.pe/v1'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Legal send deadlines
    |--------------------------------------------------------------------------
    |
    | Official pages still disagree on invoice days (3 vs 7). The number
    | lives here with its source, never as a TaxMath constant.
    |
    | invoice_send_days: R.S. N.° 000003-2023/SUNAT — 3 calendar days
    | counted from the calendar day after issue. Contrast against the
    | SEE Contribuyente guide in force before go-live.
    |
    | receipt_summary_days: SEE Contribuyente — daily summary (RC) of
    | boletas may be sent the same day or until the 7th calendar day
    | after generation. RS 000048-2026/SUNAT restates the 7th-day RC
    | window (in force from 2026-08-01). Contrast before go-live.
    |
    */

    'deadlines' => [
        'invoice_send_days' => (int) env('SUNAT_INVOICE_SEND_DAYS', 3),
        'invoice_send_source' => 'R.S. N.° 000003-2023/SUNAT — 3 días calendario desde el día siguiente a la emisión. Contrastar la guía SEE vigente antes de go-live.',
        'receipt_summary_days' => (int) env('SUNAT_RECEIPT_SUMMARY_DAYS', 7),
        'receipt_summary_source' => 'SEE Contribuyente / RS 000048-2026/SUNAT — RC hasta el 7.º día calendario siguiente a la generación. Contrastar antes de go-live.',
    ],

    'igv_percent' => env('SUNAT_IGV_PERCENT', '18'),

    'boleta_identity_threshold' => env('SUNAT_BOLETA_IDENTITY_THRESHOLD', '700.00'),

    'ubl_version' => '2.1',

    'operation_type' => '0101',

    'currency' => 'PEN',

    // PEM passwordless: Greenter's signer takes no password; a p12 is
    // converted to PEM offline. Nothing consumes a certificate password.
    'certificate_path' => env('SUNAT_CERTIFICATE_PATH'),

    'sol' => [
        'ruc' => env('SUNAT_SOL_RUC'),
        'user' => env('SUNAT_SOL_USER'),
        'password' => env('SUNAT_SOL_PASSWORD'),
    ],

    'gre_oauth' => [
        'client_id' => env('SUNAT_GRE_CLIENT_ID'),
        'client_secret' => env('SUNAT_GRE_CLIENT_SECRET'),
    ],

    // Official beta pair (orientacion.sunat.gob.pe/12-pautas-servicio-beta).
    // Username sent to SOAP is {ruc}{user}. Not production Clave SOL.
    'beta' => [
        'ruc' => '20000000001',
        'user' => 'MODDATOS',
        'password' => 'MODDATOS',
    ],

];
