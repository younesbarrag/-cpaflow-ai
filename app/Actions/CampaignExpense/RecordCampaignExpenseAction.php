<?php

namespace App\Actions\CampaignExpense;

use App\Models\Campaign;
use App\Models\CampaignExpense;

final class RecordCampaignExpenseAction
{
    public function execute(
        Campaign $campaign,
        string $amount,
        string $spentAt,
        ?string $description = null,
    ): CampaignExpense {
        return $campaign->expenses()->create([
            'amount' => $amount,
            'spent_at' => $spentAt,
            'description' => $description,
        ]);
    }
}
