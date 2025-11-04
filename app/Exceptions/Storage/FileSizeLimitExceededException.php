<?php

namespace App\Exceptions\Storage;

use Exception;

class FileSizeLimitExceededException extends Exception
{
    public static function forLimit(int $size, int $limit): self
    {
        return new self(
            sprintf(
                'File size of %s bytes exceeds the allowed limit of %s bytes.',
                $size,
                $limit,
            ),
        );
    }
}
