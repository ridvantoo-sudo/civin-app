<?php

namespace App\Features\Kyc\Http\Controllers;

use App\Features\Kyc\Actions\GetMyKyc;
use App\Features\Kyc\Actions\SubmitKyc;
use App\Features\Kyc\DTOs\SubmitKycData;
use App\Features\Kyc\Http\Requests\SubmitKycRequest;
use App\Features\Kyc\Http\Resources\KycSubmissionResource;
use App\Features\Kyc\Models\KycSubmission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class KycController extends Controller
{
    public function me(Request $request, GetMyKyc $action): JsonResponse
    {
        $this->authorize('viewOwn', KycSubmission::class);

        $submission = $action->execute($request->user());

        return response()->json([
            'data' => $submission === null
                ? null
                : (new KycSubmissionResource($submission))->resolve($request),
        ]);
    }

    public function submit(SubmitKycRequest $request, SubmitKyc $action): JsonResponse
    {
        $this->authorize('submit', KycSubmission::class);

        $userId = $request->user()->getKey();
        $prefix = 'kyc/'.$userId;
        $disk = (string) config('filesystems.secure_media');

        $frontPath = $request->file('document_front')->store($prefix, $disk);
        $selfiePath = $request->file('selfie')->store($prefix, $disk);
        $backPath = $request->hasFile('document_back')
            ? $request->file('document_back')->store($prefix, $disk)
            : null;

        try {
            $submission = $action->execute(
                $request->user(),
                new SubmitKycData(
                    documentType: (string) $request->validated('document_type'),
                    documentNumber: (string) $request->validated('document_number'),
                    fullLegalName: (string) $request->validated('full_legal_name'),
                    documentFrontPath: $frontPath,
                    selfiePath: $selfiePath,
                    documentBackPath: $backPath,
                    nationality: $request->validated('nationality'),
                    dateOfBirth: $request->validated('date_of_birth'),
                ),
            );
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete(array_filter([$frontPath, $backPath, $selfiePath]));

            throw $exception;
        }

        return response()->json([
            'data' => (new KycSubmissionResource($submission))->resolve($request),
        ], 201);
    }
}
