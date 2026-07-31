<?php

namespace App\Http\Requests\Api\V1\TrackingLink;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

class GenerateTrackingLinkRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may generate
     * a tracking link for the route-bound Campaign.
     */
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');

        if (! $campaign instanceof Campaign) {
            return false;
        }

        return $this->user()?->can(
            'generateTrackingLink',
            $campaign
        ) === true;
    }

    /**
     * KAN-14 does not accept request-body fields.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Validate the Campaign business state after authorization.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $campaign = $this->campaign();

                if ($campaign->status !== CampaignStatus::Active) {
                    $validator->errors()->add(
                        'status',
                        'Only an active campaign can generate tracking links.'
                    );
                }
            },
        ];
    }

    /**
     * Return the route-bound Campaign.
     */
    public function campaign(): Campaign
    {
        $campaign = $this->route('campaign');

        if (! $campaign instanceof Campaign) {
            throw new LogicException(
                'The route-bound campaign could not be resolved.'
            );
        }

        return $campaign;
    }
}
