<?php

namespace App\Enums;

use Illuminate\Support\Facades\Config;

enum Plan: string
{
    case Basic = 'basic';
    case Premium = 'premium';

    public static function default(): self
    {
        $default = Config::get('airlane.default_plan', self::Basic->value);

        return self::from((string) $default);
    }

    public function name(): string
    {
        $key = 'airlane.plans.'.$this->value.'.name';
        $name = Config::get($key, ucfirst($this->value));

        return (string) $name;
    }

    public function storageLimitBytes(): int
    {
        $key = 'airlane.plans.'.$this->value.'.storage_limit_bytes';
        $limit = Config::get($key, 0);

        return (int) $limit;
    }

    public function maxFileSizeBytes(): int
    {
        $key = 'airlane.plans.'.$this->value.'.max_file_size_bytes';
        $size = Config::get($key, 0);

        return (int) $size;
    }

    public function versionCap(): int
    {
        $key = 'airlane.plans.'.$this->value.'.version_cap';
        $cap = Config::get($key, 0);

        return (int) $cap;
    }
}
