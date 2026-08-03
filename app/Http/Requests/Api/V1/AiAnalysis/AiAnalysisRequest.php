<?php

namespace App\Http\Requests\Api\V1\AiAnalysis;

use App\Models\Offer;
use Illuminate\Foundation\Http\FormRequest;

final class AiAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        $offer = $this->route('offer');

        return $offer instanceof Offer
            && $this->user()?->can('analyze', $offer) === true;
    }

    public function rules(): array
    {
        return [];
    }
}
