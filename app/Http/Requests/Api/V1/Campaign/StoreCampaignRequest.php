<?php

namespace App\Http\Requests\Api\V1\Campaign;

use App\Enums\OfferStatus;
use App\Models\Offer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

final class StoreCampaignRequest extends FormRequest
{
    private ?Offer $resolvedOffer = null;

    public function authorize(): bool
    {
        $offerId = $this->resolveValidOfferId();

        /*
         * Si offer_id est absent ou mal formé, on laisse les règles
         * de validation retourner une réponse 422.
         */
        if ($offerId === null) {
            return true;
        }

        /*
         * Une valeur numérique valide mais inexistante retourne 404.
         */
        $this->resolvedOffer = Offer::query()->find($offerId);

        abort_if($this->resolvedOffer === null, 404);

        /*
         * Une offre existante appartenant à un autre utilisateur
         * retourne 403 avant la validation métier.
         */
        return $this->user()?->can(
            'createCampaign',
            $this->resolvedOffer,
        ) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'offer_id' => [
                'bail',
                'required',
                'integer',
                'min:1',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'traffic_source' => [
                'required',
                'string',
                'max:255',
            ],
            'budget' => [
                'required',
                'numeric',
                'min:0',
                'max:9999999999.99',
                'decimal:0,2',
            ],

            /*
             * Ces champs sont contrôlés par le serveur.
             */
            'status' => ['prohibited'],
            'user_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->rejectProtectedFields($validator);

                if (
                    $validator->errors()->has('offer_id')
                    || $this->resolvedOffer === null
                ) {
                    return;
                }

                if ($this->resolvedOffer->status === OfferStatus::Archived) {
                    $validator->errors()->add(
                        'offer_id',
                        'An archived offer cannot be used to create a campaign.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('name'))) {
            $normalized['name'] = trim($this->input('name'));
        }

        if (is_string($this->input('traffic_source'))) {
            $normalized['traffic_source'] = trim(
                $this->input('traffic_source'),
            );
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function offer(): Offer
    {
        return $this->resolvedOffer
            ?? throw new LogicException(
                'The campaign Offer has not been resolved.',
            );
    }

    private function resolveValidOfferId(): ?int
    {
        $offerId = $this->input('offer_id');

        if (is_int($offerId)) {
            return $offerId > 0 ? $offerId : null;
        }

        if (
            ! is_string($offerId)
            || $offerId === ''
            || ! ctype_digit($offerId)
        ) {
            return null;
        }

        $normalizedOfferId = (int) $offerId;

        return $normalizedOfferId > 0
            ? $normalizedOfferId
            : null;
    }

    private function rejectProtectedFields(Validator $validator): void
    {
        foreach (['status', 'user_id'] as $field) {
            if (
                $this->exists($field)
                && ! $validator->errors()->has($field)
            ) {
                $validator->errors()->add(
                    $field,
                    "The {$field} field must not be provided.",
                );
            }
        }
    }
}
