<?php

namespace App\Http\Requests\Api\V1\Conversion;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;

final class StoreConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');

        if (! $campaign instanceof Campaign) {
            return false;
        }

        return $this->user()?->can(
            'recordConversion',
            $campaign
        ) === true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
        ];
    }
}
