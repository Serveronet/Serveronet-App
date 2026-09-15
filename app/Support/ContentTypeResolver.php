<?php

namespace App\Support;

use App\Dicts\MimeType;
use finfo;
use InvalidArgumentException;

final class ContentTypeResolver
{
    public function resolve(string $filePath, string $originalFileName): string
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new InvalidArgumentException(
                'File does not exist or is not readable.'
            );
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        
        $detectedMimeType = $finfo->file($filePath);

        if ($detectedMimeType === false) {
            return 'application/octet-stream';
        }

        // Normalize the MIME type.
        $detectedMimeType = strtolower(trim($detectedMimeType));

        // Find the extension of the file.
        $extension = strtolower(
            pathinfo($originalFileName, PATHINFO_EXTENSION)
        );

        // If the detected type is insecure, neutralize it.
        if ($this->isInsecure($extension, $detectedMimeType)) {
            return MimeType::NEUTRALIZED_MIME_TYPE;
        }

        // Use your existing MIME_TYPES mapping.
        $mappedMimeType = MimeType::MIME_TYPES[$extension] ?? null;

        if ($mappedMimeType === null) {
            return 'application/octet-stream';
        }

        return $mappedMimeType;
    }

    private function isInsecure(
        string $extension,
        string $detectedMimeType
    ): bool {

        return in_array(
            $extension,
            MimeType::INSECURE_EXTENSIONS,
            true
        ) || in_array(
            $detectedMimeType,
            MimeType::INSECURE_MIME_TYPES,
            true
        );
    }
}
