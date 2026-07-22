<?php

namespace App\Http\Requests\Api\V1\Offer;

use App\Enums\OfferStatus;
use App\Models\Offer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $offer = $this->route('offer');

        return $offer instanceof Offer
            && $this->user()?->can('update', $offer) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'destination_url' => [
                'sometimes',
                'required',
                'string',
                'url:http,https',
                'max:2048',
            ],
            'payout' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:9999999999.99',
                'decimal:0,2',
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::enum(OfferStatus::class),
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:10000',
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $editableFields = [
                    'name',
                    'destination_url',
                    'payout',
                    'status',
                    'description',
                ];

                $submittedFields = array_intersect(
                    array_keys($this->all()),
                    $editableFields,
                );

                if ($submittedFields === []) {
                    $validator->errors()->add(
                        'offer',
                        'At least one editable offer field must be provided.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();
        $normalized = [];

        if (array_key_exists('name', $input) && is_string($input['name'])) {
            $normalized['name'] = trim($input['name']);
        }

        if (
            array_key_exists('destination_url', $input)
            && is_string($input['destination_url'])
        ) {
            $normalized['destination_url'] = trim(
                $input['destination_url'],
            );
        }

        if (
            array_key_exists('description', $input)
            && is_string($input['description'])
        ) {
            $description = trim($input['description']);

            $normalized['description'] = $description === ''
                ? null
                : $description;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
