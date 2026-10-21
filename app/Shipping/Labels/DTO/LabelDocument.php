<?php

namespace App\Shipping\Labels\DTO;

/**
 * A label document (the PDF bytes) ready to store on the private disk and stream to the admin.
 */
final readonly class LabelDocument
{
    public function __construct(
        public string $contents,
        public string $filename,
        public string $mimeType = 'application/pdf',
        public ?string $trackingNumber = null,
    ) {}
}
