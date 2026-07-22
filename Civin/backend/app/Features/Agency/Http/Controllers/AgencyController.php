<?php

namespace App\Features\Agency\Http\Controllers;

use App\Features\Agency\Actions\ApplyToAgency;
use App\Features\Agency\Actions\ApproveAgencyApplication;
use App\Features\Agency\Actions\CreateAgency;
use App\Features\Agency\Actions\GetAgency;
use App\Features\Agency\Actions\ListAgencyEarnings;
use App\Features\Agency\Actions\ListAgencyHosts;
use App\Features\Agency\Actions\RejectAgencyApplication;
use App\Features\Agency\Actions\RemoveAgencyMember;
use App\Features\Agency\DTOs\ApplyAgencyData;
use App\Features\Agency\DTOs\CreateAgencyData;
use App\Features\Agency\DTOs\ReviewApplicationData;
use App\Features\Agency\Http\Requests\ApplyAgencyRequest;
use App\Features\Agency\Http\Requests\CreateAgencyRequest;
use App\Features\Agency\Http\Requests\ListAgencyEarningsRequest;
use App\Features\Agency\Http\Requests\ReviewAgencyApplicationRequest;
use App\Features\Agency\Http\Resources\AgencyCommissionResource;
use App\Features\Agency\Http\Resources\AgencyMemberResource;
use App\Features\Agency\Http\Resources\AgencyResource;
use App\Features\Agency\Models\Agency;
use App\Features\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AgencyController extends Controller
{
    public function store(CreateAgencyRequest $request, CreateAgency $action): JsonResponse
    {
        $this->authorize('create', Agency::class);

        $agency = $action->execute(
            $request->user(),
            new CreateAgencyData(
                name: (string) $request->validated('name'),
                description: $request->validated('description'),
                logo: $request->validated('logo'),
                commissionRate: (float) ($request->validated('commission_rate') ?? 10),
            ),
        );

        return response()->json([
            'data' => (new AgencyResource($agency))->resolve($request),
        ], 201);
    }

    public function show(Request $request, Agency $agency, GetAgency $action): JsonResponse
    {
        $this->authorize('view', $agency);

        return response()->json([
            'data' => (new AgencyResource($action->execute($agency)))->resolve($request),
        ]);
    }

    public function apply(ApplyAgencyRequest $request, Agency $agency, ApplyToAgency $action): JsonResponse
    {
        $this->authorize('apply', $agency);

        $member = $action->execute(
            $agency,
            $request->user(),
            new ApplyAgencyData(message: $request->validated('message')),
        );

        return response()->json([
            'data' => (new AgencyMemberResource($member))->resolve($request),
        ], 201);
    }

    public function approve(
        ReviewAgencyApplicationRequest $request,
        Agency $agency,
        ApproveAgencyApplication $action,
    ): JsonResponse {
        $this->authorize('approve', $agency);

        $member = $action->execute(
            $agency,
            $request->user(),
            new ReviewApplicationData(userId: (string) $request->validated('user_id')),
        );

        return response()->json([
            'data' => (new AgencyMemberResource($member))->resolve($request),
        ]);
    }

    public function reject(
        ReviewAgencyApplicationRequest $request,
        Agency $agency,
        RejectAgencyApplication $action,
    ): JsonResponse {
        $this->authorize('reject', $agency);

        $member = $action->execute(
            $agency,
            $request->user(),
            new ReviewApplicationData(userId: (string) $request->validated('user_id')),
        );

        return response()->json([
            'data' => (new AgencyMemberResource($member))->resolve($request),
        ]);
    }

    public function removeMember(
        Request $request,
        Agency $agency,
        User $user,
        RemoveAgencyMember $action,
    ): JsonResponse {
        $this->authorize('removeMember', $agency);

        $member = $action->execute($agency, $request->user(), $user);

        return response()->json([
            'data' => (new AgencyMemberResource($member))->resolve($request),
        ]);
    }

    public function hosts(Request $request, Agency $agency, ListAgencyHosts $action): AnonymousResourceCollection
    {
        $this->authorize('viewHosts', $agency);

        return AgencyMemberResource::collection($action->execute($agency, $request->user()));
    }

    public function earnings(
        ListAgencyEarningsRequest $request,
        Agency $agency,
        ListAgencyEarnings $action,
    ): AnonymousResourceCollection {
        $this->authorize('viewEarnings', $agency);

        return AgencyCommissionResource::collection(
            $action->execute($agency, $request->user(), $request->perPage()),
        );
    }
}
