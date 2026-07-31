<?php

namespace App\Services\TrackingLink;

use Illuminate\Support\Str;

class TrackingCodeGenerator
{
    public function generate(): string
    {
        return Str::random(32);
    }
}
