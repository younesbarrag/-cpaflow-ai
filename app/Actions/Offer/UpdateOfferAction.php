<?php

namespace App\Actions\Offer;

use App\Enums\OfferStatus;
use App\Models\Offer;

final class UpdateOfferAction
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public function execute(Offer $offer, array $fields): Offer
    {
        $allowedFields = [
            'name',
            'destination_url',
            'payout',
            'status',
            'description',
        ];

        $trustedFields = array_intersect_key(
            $fields,
            array_flip($allowedFields),
        );

        if (array_key_exists('status', $trustedFields)) {
            $trustedFields['status'] = $this->resolveStatus(
                $trustedFields['status'],
            );
        }

        $offer->fill($trustedFields);
        $offer->save();

        return $offer->refresh();
    }

    private function resolveStatus(mixed $status): OfferStatus
    {
        if ($status instanceof OfferStatus) {
            return $status;
        }

        return OfferStatus::from((string) $status);
    }
}
