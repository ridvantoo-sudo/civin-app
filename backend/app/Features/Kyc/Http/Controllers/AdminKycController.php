<?php

namespace App\Features\Kyc\Http\Controllers;

use App\Features\Kyc\Actions\ListKycForAdmin;
use App\Features\Kyc\Actions\ReviewKyc;
use App\Features\Kyc\DTOs\ReviewKycData;
use App\Features\Kyc\Http\Requests\ListKycPagedRequest;
use App\Features\Kyc\Http\Requests\ReviewKycRequest;
use App\Features\Kyc\Http\Resources\KycSubmissionResource;
use App\Features\Kyc\Models\KycSubmission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminKycController extends Controller
{
    public function index(ListKycPagedRequest $request, ListKycForAdmin $action): AnonymousResourceCollection
    {
        $this->authorize('viewAnyAdmin', KycSubmission::class);

        return KycSubmissionResource::collection(
            $action->execute($request->user(), $request->status(), $request->perPage()),
        );
    }

    public function review(
        ReviewKycRequest $request,
        KycSubmission $kycSubmission,
        ReviewKyc $action,
    ): JsonResponse {
        $this->authorize('review', KycSubmission::class);

        $submission = $action->execute(
            $kycSubmission,
            $request->user(),
            new ReviewKycData(
                status: (string) $request->validated('status'),
                notes: $request->validated('notes'),
                faceMatchStatus: $request->validated('face_match_status'),
                faceMatchScore: $request->validated('face_match_score') === null
                    ? null
                    : (float) $request->validated('face_match_score'),
                withdrawalVerified: (bool) ($request->validated('withdrawal_verified') ?? false),
            ),
        );

        return response()->json([
            'data' => (new KycSubmissionResource($submission))->resolve($request),
        ]);
    }
}
