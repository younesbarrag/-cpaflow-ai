<?php

namespace App\Actions\Dashboard;

use App\DTOs\DashboardStatisticsPeriod;
use App\Enums\CampaignStatus;
use App\Enums\ConversionStatus;
use App\Models\Campaign;
use App\Models\CampaignExpense;
use App\Models\Conversion;
use App\Models\Offer;
use App\Models\TrackingClick;
use App\Models\User;

final class GetDashboardStatisticsAction
{
    public function execute(
        User $user,
        ?DashboardStatisticsPeriod $period = null,
    ): array {
        $hasPeriod = $period !== null && ! $period->isAllTime();

        $offerCount = Offer::where('user_id', $user->id)->count();

        $campaignCount = Campaign::whereHas('offer', fn ($q) => $q->where('user_id', $user->id))
            ->count();

        $activeCampaignCount = Campaign::whereHas('offer', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', CampaignStatus::Active)
            ->count();

        $clickCount = TrackingClick::whereHas(
            'trackingLink.campaign.offer',
            fn ($q) => $q->where('user_id', $user->id)
        )->when($hasPeriod, function ($q) use ($period) {
            $q->where('created_at', '>=', $period->start)
                ->where('created_at', '<', $period->endExclusive);
        })->count();

        $conversionStats = Conversion::whereHas(
            'campaign.offer',
            fn ($q) => $q->where('user_id', $user->id)
        )->when($hasPeriod, function ($q) use ($period) {
            $q->where('converted_at', '>=', $period->start)
                ->where('converted_at', '<', $period->endExclusive);
        })->selectRaw('COUNT(*) as count, COALESCE(SUM(revenue), 0) as total')
            ->first();

        $approvedRevenue = Conversion::whereHas(
            'campaign.offer',
            fn ($q) => $q->where('user_id', $user->id)
        )->where('status', ConversionStatus::Approved)
            ->when($hasPeriod, function ($q) use ($period) {
                $q->where('converted_at', '>=', $period->start)
                    ->where('converted_at', '<', $period->endExclusive);
            })->selectRaw('COALESCE(SUM(revenue), 0) as total')
            ->value('total');

        $totalExpenses = CampaignExpense::whereHas(
            'campaign.offer',
            fn ($q) => $q->where('user_id', $user->id)
        )->when($hasPeriod, function ($q) use ($period) {
            $q->where('spent_at', '>=', $period->start->format('Y-m-d'))
                ->where('spent_at', '<', $period->endExclusive->format('Y-m-d'));
        })->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->value('total');

        $revenue = (float) $approvedRevenue;
        $expenses = (float) $totalExpenses;

        return [
            'offer_count' => $offerCount,
            'campaign_count' => $campaignCount,
            'active_campaign_count' => $activeCampaignCount,
            'click_count' => $clickCount,
            'conversion_count' => (int) $conversionStats->count,
            'revenue' => $revenue,
            'total_expenses' => $expenses,
            'profit' => $revenue - $expenses,
        ];
    }
}
