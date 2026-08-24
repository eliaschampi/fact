<?php

namespace Tests\Unit;

use App\Support\LimaDate;
use App\Support\Sunat\ArtifactStore;
use App\Support\Sunat\CacheTokenStore;
use App\Support\Sunat\CpeGateway;
use App\Support\Sunat\GreenterCpeGateway;
use App\Support\Sunat\SunatCredentials;
use App\Support\Sunat\SunatEndpoints;
use Carbon\CarbonImmutable;
use Greenter\See;
use Greenter\Services\Api\TokenStoreInterface;
use Tests\TestCase;

class SunatConfigTest extends TestCase
{
    public function test_bill_service_follows_configured_environment(): void
    {
        config()->set('sunat.environment', 'beta');
        $endpoints = app(SunatEndpoints::class);

        $this->assertSame(
            'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService',
            $endpoints->billService(),
        );

        config()->set('sunat.environment', 'production');
        $this->assertSame(
            'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService',
            $endpoints->billService(),
        );
    }

    public function test_deadlines_are_config_not_magic_constants(): void
    {
        $this->assertSame(3, config('sunat.deadlines.invoice_send_days'));
        $this->assertSame(7, config('sunat.deadlines.receipt_summary_days'));
        $this->assertNotEmpty(config('sunat.deadlines.invoice_send_source'));
        $this->assertNotEmpty(config('sunat.deadlines.receipt_summary_source'));
    }

    public function test_greenter_see_loads_on_php_eight_five(): void
    {
        $this->assertTrue(class_exists(See::class));
        $this->assertInstanceOf(GreenterCpeGateway::class, app(CpeGateway::class));
        $this->assertInstanceOf(TokenStoreInterface::class, app(CacheTokenStore::class));
    }

    public function test_artifact_paths_follow_issuer_year_month_document(): void
    {
        $store = app(ArtifactStore::class);
        $issuedAt = CarbonImmutable::parse('2026-08-23 10:00:00', LimaDate::TIMEZONE);

        $this->assertSame(
            '20100000000/2026/08/01-F001-12/signed.xml',
            $store->path('20100000000', $issuedAt, '01', 'F001', 12, ArtifactStore::KIND_SIGNED_XML),
        );
    }

    public function test_lima_business_date_is_server_owned(): void
    {
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', LimaDate::businessDate());
        $this->assertSame('America/Lima', LimaDate::TIMEZONE);
    }

    public function test_gre_oauth_requires_both_client_id_and_secret(): void
    {
        config([
            'sunat.gre_oauth.client_id' => 'client-id',
            'sunat.gre_oauth.client_secret' => null,
        ]);

        $this->assertFalse(SunatCredentials::fromConfig()->hasGreOAuth());

        config(['sunat.gre_oauth.client_secret' => 'client-secret']);

        $this->assertTrue(SunatCredentials::fromConfig()->hasGreOAuth());
    }
}
