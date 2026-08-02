<?php

namespace App\Http\Controllers;

use App\Actions\Campaign\ActivateCampaignAction;
use App\Actions\Campaign\CreateCampaignAction;
use App\Actions\Campaign\SuspendCampaignAction;
use App\Actions\Campaign\UpdateCampaignAction;
use App\Actions\TrackingLink\GenerateTrackingLinkAction;
use App\Enums\OfferStatus;
use App\Exceptions\InvalidCampaignTransition;
use App\Http\Requests\Api\V1\Campaign\StoreCampaignRequest;
use App\Http\Requests\Api\V1\Campaign\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(Request $request): View
    {
        $query = Campaign::query()
            ->whereHas(
                'offer',
                function (Builder $query) use ($request): void {
                    $query->where('user_id', $request->user()->id);
                },
            )
            ->with('offer:id,name')
            ->orderByDesc('id');

        $campaigns = $query->paginate(15)->withQueryString();

        $hasEligibleOffers = Offer::where('user_id', $request->user()->id)
            ->where('status', '!=', OfferStatus::Archived)
            ->exists();

        return view('campaigns.index', [
            'campaigns' => $campaigns,
            'hasEligibleOffers' => $hasEligibleOffers,
        ]);
    }

    public function create(): View
    {
        $user = auth()->user();

        $offers = Offer::where('user_id', $user->id)
            ->where('status', '!=', OfferStatus::Archived)
            ->orderBy('name')
            ->get();

        return view('campaigns.create', [
            'offers' => $offers,
        ]);
    }

    public function store(StoreCampaignRequest $request, CreateCampaignAction $action): RedirectResponse
    {
        $offer = $request->offer();

        Gate::authorize('createCampaign', $offer);

        $campaign = $action->execute(
            $offer,
            $request->safe()->only([
                'name',
                'traffic_source',
                'budget',
            ]),
        );

        return redirect()->route('campaigns.index')
            ->with('success', 'Campaign created successfully.');
    }

    public function show(Campaign $campaign): View
    {
        Gate::authorize('view', $campaign);

        $campaign->load('offer:id,name,destination_url');
        $campaign->load('trackingLinks');

        return view('campaigns.show', [
            'campaign' => $campaign,
        ]);
    }

    public function edit(Campaign $campaign): View
    {
        Gate::authorize('update', $campaign);

        $user = auth()->user();

        $offers = Offer::where('user_id', $user->id)
            ->where('status', '!=', OfferStatus::Archived)
            ->orderBy('name')
            ->get();

        $campaign->load('offer:id,name');

        return view('campaigns.edit', [
            'campaign' => $campaign,
            'offers' => $offers,
        ]);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign, UpdateCampaignAction $action): RedirectResponse
    {
        $action->execute(
            $campaign,
            $request->safe()->only([
                'name',
                'traffic_source',
                'budget',
            ]),
        );

        return redirect()->route('campaigns.index')
            ->with('success', 'Campaign updated successfully.');
    }

    public function activate(Campaign $campaign, ActivateCampaignAction $action): RedirectResponse
    {
        Gate::authorize('activate', $campaign);

        try {
            $action->execute($campaign);
        } catch (InvalidCampaignTransition $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('campaigns.index')
            ->with('success', 'Campaign activated successfully.');
    }

    public function suspend(Campaign $campaign, SuspendCampaignAction $action): RedirectResponse
    {
        Gate::authorize('suspend', $campaign);

        try {
            $action->execute($campaign);
        } catch (InvalidCampaignTransition $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('campaigns.index')
            ->with('success', 'Campaign suspended successfully.');
    }

    public function storeTrackingLink(Campaign $campaign, GenerateTrackingLinkAction $action): RedirectResponse
    {
        Gate::authorize('generateTrackingLink', $campaign);

        $action->execute($campaign);

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Tracking link generated successfully.');
    }
}
