<?php

namespace App\Http\Requests\Api\V1\Offer;

use App\Enums\OfferStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim($this->name)]);
        }

        if ($this->has('destination_url')) {
            $this->merge(['destination_url' => trim($this->destination_url)]);
        }

        if ($this->has('description') && $this->description !== null) {
            $this->merge(['description' => trim($this->description)]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'destination_url' => ['required', 'string', 'url:http,https', 'max:2048'],
            'payout' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'status' => ['required', Rule::enum(OfferStatus::class)],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
