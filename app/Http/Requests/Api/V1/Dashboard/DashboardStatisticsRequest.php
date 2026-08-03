<?php

namespace App\Http\Requests\Api\V1\Dashboard;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DashboardStatisticsRequest extends FormRequest
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
        $period = $this->input('period');
        $isCustom = $period === 'custom';

        return [
            'period' => [
                'nullable',
                'string',
                Rule::in(['today', 'last_7_days', 'last_30_days', 'this_month', 'custom']),
            ],
            'from' => $isCustom
                ? ['required', 'date_format:Y-m-d', 'before_or_equal:to', 'before_or_equal:today']
                : ['prohibited'],
            'to' => $isCustom
                ? ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today']
                : ['prohibited'],
        ];
    }
}
