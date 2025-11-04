<?php

namespace App\Enums;

enum StoragePermission: string
{
    case Viewer = 'viewer';
    case Editor = 'editor';
    case Owner = 'owner';

    public function allowsWrite(): bool
    {
        return match ($this) {
            self::Owner, self::Editor => true,
            self::Viewer => false,
        };
    }

    public function allowsShareManagement(): bool
    {
        return $this === self::Owner;
    }
}
