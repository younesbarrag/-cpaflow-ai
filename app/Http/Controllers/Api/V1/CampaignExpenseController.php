<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CampaignExpense\DeleteCampaignExpenseAction;
use App\Actions\CampaignExpense\RecordCampaignExpenseAction;
use App\Actions\CampaignExpense\UpdateCampaignExpenseAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CampaignExpense\StoreCampaignExpenseRequest;
use App\Http\Requests\Api\V1\CampaignExpense\UpdateCampaignExpenseRequest;
use App\Http\Resources\Api\V1\CampaignExpenseResource;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CampaignExpenseController extends Controller
{
    public function index(
        Request $request,
        Campaign $campaign,
    ): JsonResponse {
        Gate::authorize('viewExpenses', $campaign);

        $expenses = $campaign->expenses()
            ->orderByDesc('spent_at')
            ->orderByDesc('id')
            ->paginate(15);

        return CampaignExpenseResource::collection($expenses)->response();
    }

    public function store(
        StoreCampaignExpenseRequest $request,
        Campaign $campaign,
        RecordCampaignExpenseAction $action,
    ): JsonResponse {
        $expense = $action->execute(
            $campaign,
            $request->validated('amount'),
            $request->validated('spent_at'),
            $request->validated('description'),
        );

        return response()->json([
            'data' => [
                'campaign_expense' => new CampaignExpenseResource($expense),
            ],
        ], 201);
    }

    public function update(
        UpdateCampaignExpenseRequest $request,
        Campaign $campaign,
        int $expense,
        UpdateCampaignExpenseAction $action,
    ): JsonResponse {
        $expenseModel = $campaign->expenses()->findOrFail($expense);

        $updatedExpense = $action->execute(
            $expenseModel,
            $request->validated(),
        );

        return response()->json([
            'data' => [
                'campaign_expense' => new CampaignExpenseResource($updatedExpense),
            ],
        ]);
    }

    public function destroy(
        Request $request,
        Campaign $campaign,
        int $expense,
        DeleteCampaignExpenseAction $action,
    ): JsonResponse {
        Gate::authorize('deleteExpense', $campaign);

        $expenseModel = $campaign->expenses()->findOrFail($expense);

        $action->execute($expenseModel);

        return response()->json(null, 204);
    }
}
