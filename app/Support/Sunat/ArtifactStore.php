<?php

namespace App\Support\Sunat;

use App\Support\LimaDate;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

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
        $lima = $issuedAt->timezone(LimaDate::TIMEZONE);

        return sprintf(
            '%s/%s/%s/%s-%s-%d',
            $issuerKey,
            $lima->format('Y'),
            $lima->format('m'),
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

    public function put(string $path, string $contents, bool $overwrite = false): string
    {
        $sha256 = hash('sha256', $contents);

        if ($overwrite) {
            $this->disk()->put($path, $contents);

            return $sha256;
        }

        if ($this->writeExclusive($path, $contents)) {
            return $sha256;
        }

        $existingHash = hash('sha256', $this->disk()->get($path));

        if (hash_equals($sha256, $existingHash)) {
            return $sha256;
        }

        throw new RuntimeException("El artefacto fiscal ya existe con contenido distinto: {$path}");
    }

    private function writeExclusive(string $path, string $contents): bool
    {
        $disk = $this->disk();

        if ($disk instanceof FilesystemAdapter) {
            $fullPath = $disk->path($path);
            $directory = dirname($fullPath);

            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new RuntimeException("No se pudo crear el directorio fiscal: {$directory}");
            }

            // 'x' is O_EXCL: atomic create-if-absent, unlike exists()+put().
            $handle = @fopen($fullPath, 'xb');

            if ($handle === false) {
                return false;
            }

            try {
                $written = fwrite($handle, $contents);

                if ($written === false || $written !== strlen($contents)) {
                    fclose($handle);
                    unlink($fullPath);

                    throw new RuntimeException("No se pudo escribir el artefacto fiscal: {$path}");
                }

                fflush($handle);
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }

            return true;
        }

        if ($disk->exists($path)) {
            return false;
        }

        $disk->put($path, $contents);

        return true;
    }
}
