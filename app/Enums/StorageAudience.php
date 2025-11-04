<?php

namespace App\Enums;

enum StorageAudience: string
{
    case Company = 'company';
    case Team = 'team';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Everyone in the company',
            self::Team => 'Everyone in the team',
        };
    }
}
