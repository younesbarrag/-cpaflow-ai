<?php

namespace App\Http\Requests\Api\V1\Conversion;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class StorePostbackConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'token' => ['required', 'string'],
            'revenue' => ['prohibited'],
            'status' => ['prohibited'],
            'campaign_id' => ['prohibited'],
            'converted_at' => ['prohibited'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
