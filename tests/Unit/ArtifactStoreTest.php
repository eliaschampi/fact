<?php

namespace Tests\Unit;

use App\Support\Sunat\ArtifactStore;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ArtifactStoreTest extends TestCase
{
    public function test_signed_xml_same_bytes_are_idempotent(): void
    {
        Storage::fake('fiscal');
        $store = app(ArtifactStore::class);
        $path = '20100000000/2026/08/01-F001-1/signed.xml';

        $first = $store->put($path, '<xml/>');
        $second = $store->put($path, '<xml/>');

        $this->assertSame(hash('sha256', '<xml/>'), $first);
        $this->assertSame($first, $second);
        $this->assertSame('<xml/>', Storage::disk('fiscal')->get($path));
    }

    public function test_signed_xml_rejects_different_bytes_at_the_same_path(): void
    {
        Storage::fake('fiscal');
        $store = app(ArtifactStore::class);
        $path = '20100000000/2026/08/01-F001-1/signed.xml';

        $store->put($path, '<xml/>');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('contenido distinto');
        $store->put($path, '<other/>');
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
