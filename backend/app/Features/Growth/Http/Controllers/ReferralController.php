<?php

namespace App\Features\Growth\Http\Controllers;

use App\Features\Growth\Actions\ManageGrowth;
use App\Features\Growth\DTOs\AttachReferralData;
use App\Features\Growth\Http\Requests\AttachReferralRequest;
use App\Features\Growth\Http\Resources\ReferralAttributionResource;
use App\Features\Growth\Http\Resources\ReferralCodeResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReferralController extends Controller
{
    public function me(Request $request, ManageGrowth $action): JsonResponse
    {
        $stats = $action->referralStats($request->user());
        $code = $action->myReferralCode($request->user());

        return response()->json([
            'data' => array_merge(
                (new ReferralCodeResource($code))->resolve($request),
                ['successful_referrals' => $stats['successful_referrals']],
            ),
        ]);
    }

    public function attach(AttachReferralRequest $request, ManageGrowth $action): JsonResponse
    {
        $attribution = $action->attachReferral(
            $request->user(),
            new AttachReferralData((string) $request->validated('code')),
        );

        return response()->json([
            'data' => (new ReferralAttributionResource($attribution))->resolve($request),
        ], 201);
    }
}
