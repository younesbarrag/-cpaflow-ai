<?php

namespace App\Http\Requests\Api\V1\Campaign;

use App\Models\Campaign;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');

        return $campaign instanceof Campaign
            && $this->user()?->can('update', $campaign) === true;
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
            'traffic_source' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'budget' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:9999999999.99',
                'decimal:0,2',
            ],

            /*
             * Ces champs ne peuvent pas être modifiés par PATCH.
             */
            'offer_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->containsEditableField()) {
                    return;
                }

                /*
                 * Les champs protégés produisent déjà leur propre
                 * erreur grâce à la règle "prohibited".
                 */
                if ($this->containsProtectedField()) {
                    return;
                }

                $validator->errors()->add(
                    'campaign',
                    'At least one editable campaign field must be provided.',
                );
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (
            $this->exists('name')
            && is_string($this->input('name'))
        ) {
            $normalized['name'] = trim($this->input('name'));
        }

        if (
            $this->exists('traffic_source')
            && is_string($this->input('traffic_source'))
        ) {
            $normalized['traffic_source'] = trim(
                $this->input('traffic_source'),
            );
        }

        if (
            $this->exists('budget')
            && is_string($this->input('budget'))
        ) {
            $normalized['budget'] = trim($this->input('budget'));
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    private function containsEditableField(): bool
    {
        foreach (['name', 'traffic_source', 'budget'] as $field) {
            if ($this->exists($field)) {
                return true;
            }
        }

        return false;
    }

    private function containsProtectedField(): bool
    {
        foreach (['offer_id', 'user_id', 'status'] as $field) {
            if ($this->exists($field)) {
                return true;
            }
        }

        return false;
    }
}
