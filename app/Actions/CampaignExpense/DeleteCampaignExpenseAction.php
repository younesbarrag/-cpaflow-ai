<?php

namespace App\Actions\CampaignExpense;

use App\Models\CampaignExpense;

final class DeleteCampaignExpenseAction
{
    public function execute(CampaignExpense $expense): void
    {
        $expense->delete();
    }
}
