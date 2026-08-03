<?php

namespace App\Actions\CampaignExpense;

use App\Models\CampaignExpense;

final class UpdateCampaignExpenseAction
{
    public function execute(
        CampaignExpense $expense,
        array $fields,
    ): CampaignExpense {
        $expense->update($fields);

        return $expense;
    }
}
