<?php

namespace App\Support\Sunat;

final readonly class SignedXml
{
    public function __construct(
        public string $contents,
        public string $sha256,
        public string $filename,
    ) {}

    public static function fromContents(string $contents, string $filename): self
    {
        return new self($contents, hash('sha256', $contents), $filename);
    }
}
