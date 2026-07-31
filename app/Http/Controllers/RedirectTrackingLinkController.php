<?php

namespace App\Http\Controllers;

use App\Actions\TrackingLink\RecordTrackingClickAction;
use App\Enums\CampaignStatus;
use App\Models\TrackingLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RedirectTrackingLinkController extends Controller
{
    public function __invoke(
        string $code,
        RecordTrackingClickAction $action,
        Request $request,
    ): RedirectResponse {
        $trackingLink = TrackingLink::with('campaign.offer')
            ->where('code', $code)
            ->first();

        if (
            $trackingLink === null
            || $trackingLink->campaign === null
            || $trackingLink->campaign->offer === null
            || $trackingLink->campaign->status !== CampaignStatus::Active
        ) {
            abort(404);
        }

        $destinationUrl = $trackingLink->campaign->offer->destination_url;

        if (! $this->isSafeDestination($destinationUrl)) {
            abort(404);
        }

        try {
            $action->execute(
                $trackingLink,
                $request->ip(),
                $request->header('User-Agent'),
                $request->header('Referer'),
                $request->query('utm_source'),
                $request->query('utm_medium'),
                $request->query('utm_campaign'),
                $request->query('utm_term'),
                $request->query('utm_content'),
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect($destinationUrl, 302);
    }

    private function isSafeDestination(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        return in_array(strtolower((string) $scheme), ['http', 'https'], true)
            && $host !== null
            && $host !== '';
    }
}
