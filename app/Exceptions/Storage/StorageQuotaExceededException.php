<?php

namespace App\Exceptions\Storage;

use Exception;

class StorageQuotaExceededException extends Exception
{
    public static function forUser(int $requiredBytes, int $remainingBytes): self
    {
        return new self(
            sprintf(
                'Storage quota exceeded. Required: %s bytes, remaining: %s bytes.',
                $requiredBytes,
                $remainingBytes,
            ),
        );
    }
}
