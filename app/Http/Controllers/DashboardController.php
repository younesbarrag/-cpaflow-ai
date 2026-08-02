<?php

namespace App\Http\Controllers;

use App\Enums\OfferStatus;
use App\Models\Campaign;
use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $recentOffers = Offer::where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $recentCampaigns = Campaign::whereHas('offer', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->with('offer:id,name')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $offerCount = Offer::where('user_id', $user->id)->count();
        $campaignCount = Campaign::whereHas('offer', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->count();

        $hasEligibleOffers = Offer::where('user_id', $user->id)
            ->where('status', '!=', OfferStatus::Archived)
            ->exists();

        return view('dashboard', [
            'recentOffers' => $recentOffers,
            'recentCampaigns' => $recentCampaigns,
            'offerCount' => $offerCount,
            'campaignCount' => $campaignCount,
            'hasEligibleOffers' => $hasEligibleOffers,
        ]);
    }
}
