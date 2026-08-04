<?php

namespace App\Http\Requests\Api\V1\AiGeneration;

use App\Models\Offer;
use Illuminate\Foundation\Http\FormRequest;

final class AiGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $offer = $this->route('offer');

        return $offer instanceof Offer
            && $this->user()?->can('generate', $offer) === true;
    }

    public function rules(): array
    {
        return [];
    }
}
