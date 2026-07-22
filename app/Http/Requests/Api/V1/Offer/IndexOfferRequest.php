<?php

namespace App\Http\Requests\Api\V1\Offer;

use App\Enums\OfferStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                Rule::enum(OfferStatus::class),
            ],
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('status') && is_string($this->input('status'))) {
            $status = trim($this->input('status'));

            $normalized['status'] = $status === '' ? null : $status;
        }

        if ($this->has('search') && is_string($this->input('search'))) {
            $search = preg_replace(
                '/\s+/',
                ' ',
                trim($this->input('search')),
            );

            $normalized['search'] = $search === '' ? null : $search;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
