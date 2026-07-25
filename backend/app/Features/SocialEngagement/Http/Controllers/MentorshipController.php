<?php

namespace App\Features\SocialEngagement\Http\Controllers;

use App\Features\SocialEngagement\Actions\ManageSocialEngagement;
use App\Features\SocialEngagement\DTOs\LogMentorshipSessionData;
use App\Features\SocialEngagement\DTOs\RequestMentorshipData;
use App\Features\SocialEngagement\Http\Requests\LogMentorshipSessionRequest;
use App\Features\SocialEngagement\Http\Requests\RequestMentorshipRequest;
use App\Features\SocialEngagement\Http\Resources\MentorshipRequestResource;
use App\Features\SocialEngagement\Http\Resources\MentorshipResource;
use App\Features\SocialEngagement\Http\Resources\MentorshipSessionResource;
use App\Features\SocialEngagement\Models\Mentorship;
use App\Features\SocialEngagement\Models\MentorshipRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class MentorshipController extends Controller
{
    public function index(Request $request, ManageSocialEngagement $action): AnonymousResourceCollection
    {
        return MentorshipResource::collection(
            $action->myMentorships($request->user(), (int) $request->integer('per_page', 20)),
        );
    }

    public function incoming(Request $request, ManageSocialEngagement $action): AnonymousResourceCollection
    {
        return MentorshipRequestResource::collection(
            $action->incomingMentorshipRequests($request->user(), (int) $request->integer('per_page', 20)),
        );
    }

    public function store(RequestMentorshipRequest $request, ManageSocialEngagement $action): JsonResponse
    {
        $v = $request->validated();
        $row = $action->requestMentorship(
            $request->user(),
            new RequestMentorshipData(
                targetUserId: (string) $v['target_user_id'],
                role: (string) $v['role'],
                message: $v['message'] ?? null,
            ),
        );

        return response()->json([
            'data' => (new MentorshipRequestResource($row))->resolve($request),
        ], 201);
    }

    public function accept(Request $request, MentorshipRequest $mentorshipRequest, ManageSocialEngagement $action): JsonResponse
    {
        $mentorship = $action->acceptMentorship($request->user(), $mentorshipRequest);

        return response()->json([
            'data' => (new MentorshipResource($mentorship))->resolve($request),
        ]);
    }

    public function reject(Request $request, MentorshipRequest $mentorshipRequest, ManageSocialEngagement $action): JsonResponse
    {
        $row = $action->rejectMentorship($request->user(), $mentorshipRequest);

        return response()->json([
            'data' => (new MentorshipRequestResource($row))->resolve($request),
        ]);
    }

    public function end(Request $request, Mentorship $mentorship, ManageSocialEngagement $action): JsonResponse
    {
        $row = $action->endMentorship($request->user(), $mentorship);

        return response()->json([
            'data' => (new MentorshipResource($row))->resolve($request),
        ]);
    }

    public function logSession(
        LogMentorshipSessionRequest $request,
        Mentorship $mentorship,
        ManageSocialEngagement $action,
    ): JsonResponse {
        $v = $request->validated();
        $session = $action->logSession(
            $request->user(),
            $mentorship,
            new LogMentorshipSessionData(
                durationMinutes: (int) $v['duration_minutes'],
                notes: $v['notes'] ?? null,
                heldAt: isset($v['held_at']) ? new \DateTimeImmutable($v['held_at']) : null,
            ),
        );

        return response()->json([
            'data' => (new MentorshipSessionResource($session))->resolve($request),
        ], 201);
    }
}
