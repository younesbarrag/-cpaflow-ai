<?php

namespace App\Enums;

enum OfferStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';
}
