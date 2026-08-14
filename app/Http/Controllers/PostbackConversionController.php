<?php

namespace App\Http\Controllers;

use App\Actions\Conversion\RecordConversionAction;
use App\Exceptions\DuplicateConversionException;
use App\Http\Requests\Api\V1\Conversion\StorePostbackConversionRequest;
use App\Models\TrackingLink;
use App\Services\PostbackSigner;
use Illuminate\Http\JsonResponse;

class PostbackConversionController extends Controller
{
    public function __construct(
        private PostbackSigner $signer,
    ) {}

    public function __invoke(
        string $code,
        StorePostbackConversionRequest $request,
        RecordConversionAction $action,
    ): JsonResponse {
        $trackingLink = TrackingLink::with('campaign')
            ->where('code', $code)
            ->first();

        if ($trackingLink === null || $trackingLink->campaign === null) {
            abort(404);
        }

        if (! $this->signer->isValid($code, $request->validated('token'))) {
            abort(403, 'Invalid postback token.');
        }

        try {
            $conversion = $action->execute(
                $trackingLink->campaign,
                $request->validated('external_id'),
                $request->validated('source'),
            );

            return response()->json([
                'status' => 'ok',
                'duplicate' => false,
            ]);
        } catch (DuplicateConversionException) {
            return response()->json([
                'status' => 'ok',
                'duplicate' => true,
            ]);
        }
    }
}
