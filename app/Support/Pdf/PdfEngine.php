<?php

namespace App\Support\Pdf;

use RuntimeException;

final class PdfEngine
{
    public function isConfigured(): bool
    {
        $path = $this->binaryPath();

        return $path !== null && is_executable($path);
    }

    public function binaryPath(): ?string
    {
        $configured = config('necofact.pdf.binary_path');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    public function spawn(array $manifest): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('PDF_BINARY_PATH no está configurado.');
        }

        throw new RuntimeException(
            'El binario PDF se conecta en el vertical de representación. El CPE no depende de él.',
        );
    }
}
