<?php

namespace Tests\Unit;

use App\Support\Sunat\ArtifactStore;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ArtifactStoreTest extends TestCase
{
    public function test_signed_xml_is_immutable_once_written(): void
    {
        Storage::fake('fiscal');
        $store = app(ArtifactStore::class);

        $hash = $store->put('20100000000/2026/08/01-F001-1/signed.xml', '<xml/>');

        $this->assertSame(hash('sha256', '<xml/>'), $hash);

        $this->expectException(RuntimeException::class);
        $store->put('20100000000/2026/08/01-F001-1/signed.xml', '<other/>');
    }

    public function test_pdf_representation_may_be_overwritten(): void
    {
        Storage::fake('fiscal');
        $store = app(ArtifactStore::class);
        $path = '20100000000/2026/08/01-F001-1/representation.pdf';

        $store->put($path, 'v1');
        $store->put($path, 'v2', overwrite: true);

        $this->assertSame('v2', Storage::disk('fiscal')->get($path));
    }
}
