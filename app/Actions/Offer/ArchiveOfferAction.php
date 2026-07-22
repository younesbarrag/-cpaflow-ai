<?php

namespace App\Actions\Offer;

use App\Enums\OfferStatus;
use App\Models\Offer;

final class ArchiveOfferAction
{
    public function execute(Offer $offer): Offer
    {
        if ($offer->status === OfferStatus::Archived) {
            return $offer;
        }

        $offer->status = OfferStatus::Archived;
        $offer->save();

        return $offer->refresh();
    }
}
