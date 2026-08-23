<?php

namespace App\Support\Sunat;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

final class ArtifactStore
{
    public const string KIND_SIGNED_XML = 'signed.xml';

    public const string KIND_CDR_ZIP = 'cdr.zip';

    public const string KIND_CDR_XML = 'cdr.xml';

    public const string KIND_PDF = 'representation.pdf';

    public function disk(): Filesystem
    {
        return Storage::disk('fiscal');
    }

    public function directory(
        string $issuerKey,
        CarbonInterface $issuedAt,
        string $documentType,
        string $series,
        int $number,
    ): string {
        return sprintf(
            '%s/%s/%s/%s-%s-%d',
            $issuerKey,
            $issuedAt->timezone('America/Lima')->format('Y'),
            $issuedAt->timezone('America/Lima')->format('m'),
            $documentType,
            $series,
            $number,
        );
    }

    public function path(
        string $issuerKey,
        CarbonInterface $issuedAt,
        string $documentType,
        string $series,
        int $number,
        string $kind,
    ): string {
        return $this->directory($issuerKey, $issuedAt, $documentType, $series, $number).'/'.$kind;
    }

    public function put(string $path, string $contents): string
    {
        $this->disk()->put($path, $contents);

        return hash('sha256', $contents);
    }
}
