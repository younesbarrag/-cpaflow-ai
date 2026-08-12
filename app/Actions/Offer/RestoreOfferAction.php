<?php

namespace App\Actions\Offer;

use App\Enums\OfferStatus;
use App\Models\Offer;

final class RestoreOfferAction
{
    public function execute(Offer $offer): Offer
    {
        if ($offer->status !== OfferStatus::Archived) {
            return $offer;
        }

        $offer->status = OfferStatus::Draft;
        $offer->save();

        return $offer->refresh();
    }
}
