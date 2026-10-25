<?php

namespace App\Shipping\Labels\DTO;

final readonly class LabelDocument
{
    public function __construct(
        public string $contents,
        public string $filename,
        public string $mimeType = 'application/pdf',
        public ?string $trackingNumber = null,
    ) {}
}
