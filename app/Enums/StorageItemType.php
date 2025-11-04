<?php

namespace App\Enums;

enum StorageItemType: string
{
    case Folder = 'folder';
    case File = 'file';
    case Note = 'note';

    public function hasBinaryPayload(): bool
    {
        return $this === self::File;
    }

    public function isContainer(): bool
    {
        return $this === self::Folder;
    }

    public function supportsRichContent(): bool
    {
        return $this === self::Note;
    }
}
