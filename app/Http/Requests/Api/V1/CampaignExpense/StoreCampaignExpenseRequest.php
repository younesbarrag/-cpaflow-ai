<?php

namespace App\Http\Requests\Api\V1\CampaignExpense;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCampaignExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');

        if (! $campaign instanceof Campaign) {
            return false;
        }

        return $this->user()?->can(
            'recordExpense',
            $campaign
        ) === true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                'max:9999999999.99',
                'decimal:0,2',
            ],
            'spent_at' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'description' => [
                'nullable',
                'string',
                'max:10000',
            ],

            'campaign_id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('description'))) {
            $trimmed = trim($this->input('description'));
            $normalized['description'] = $trimmed === '' ? null : $trimmed;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
