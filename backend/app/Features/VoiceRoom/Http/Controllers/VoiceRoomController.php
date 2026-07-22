<?php

namespace App\Features\VoiceRoom\Http\Controllers;

use App\Features\VoiceRoom\Actions\ApproveVoiceSeat;
use App\Features\VoiceRoom\Actions\CreateVoiceRoom;
use App\Features\VoiceRoom\Actions\EndVoiceRoom;
use App\Features\VoiceRoom\Actions\JoinVoiceRoom;
use App\Features\VoiceRoom\Actions\LeaveVoiceRoom;
use App\Features\VoiceRoom\Actions\MuteVoiceSpeaker;
use App\Features\VoiceRoom\Actions\RejectVoiceSeat;
use App\Features\VoiceRoom\Actions\RemoveVoiceSpeaker;
use App\Features\VoiceRoom\Actions\RequestVoiceSeat;
use App\Features\VoiceRoom\DTOs\CreateVoiceRoomData;
use App\Features\VoiceRoom\DTOs\SeatActionData;
use App\Features\VoiceRoom\DTOs\VoiceRoomConnectionData;
use App\Features\VoiceRoom\Http\Requests\CreateVoiceRoomRequest;
use App\Features\VoiceRoom\Http\Requests\SeatActionRequest;
use App\Features\VoiceRoom\Http\Resources\VoiceRoomResource;
use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VoiceRoomController extends Controller
{
    public function store(CreateVoiceRoomRequest $request, CreateVoiceRoom $action): JsonResponse
    {
        return $this->connectionResponse($request, $action->execute(
            $request->user(),
            new CreateVoiceRoomData(
                (string) $request->validated('title'),
                $request->validated('description'),
                $request->validated('thumbnail'),
                $request->seatCount(),
            ),
        ), 201);
    }

    public function join(Request $request, VoiceRoom $room, JoinVoiceRoom $action): JsonResponse
    {
        $this->authorize('join', $room);

        return $this->connectionResponse($request, $action->execute($room, $request->user()));
    }

    public function leave(Request $request, VoiceRoom $room, LeaveVoiceRoom $action): VoiceRoomResource
    {
        $this->authorize('leave', $room);

        return new VoiceRoomResource($action->execute($room, $request->user()));
    }

    public function requestSeat(SeatActionRequest $request, VoiceRoom $room, RequestVoiceSeat $action): VoiceRoomResource
    {
        $this->authorize('requestSeat', $room);

        return new VoiceRoomResource($action->execute(
            $room,
            $request->user(),
            new SeatActionData((int) $request->validated('seat_index')),
        ));
    }

    public function approveSeat(SeatActionRequest $request, VoiceRoom $room, ApproveVoiceSeat $action): VoiceRoomResource
    {
        $this->authorize('approveSeat', $room);

        return new VoiceRoomResource($action->execute(
            $room,
            new SeatActionData((int) $request->validated('seat_index')),
        ));
    }

    public function rejectSeat(SeatActionRequest $request, VoiceRoom $room, RejectVoiceSeat $action): VoiceRoomResource
    {
        $this->authorize('rejectSeat', $room);

        return new VoiceRoomResource($action->execute(
            $room,
            new SeatActionData((int) $request->validated('seat_index')),
        ));
    }

    public function removeSpeaker(SeatActionRequest $request, VoiceRoom $room, RemoveVoiceSpeaker $action): VoiceRoomResource
    {
        $this->authorize('removeSpeaker', $room);

        return new VoiceRoomResource($action->execute(
            $room,
            new SeatActionData((int) $request->validated('seat_index')),
        ));
    }

    public function muteSpeaker(SeatActionRequest $request, VoiceRoom $room, MuteVoiceSpeaker $action): VoiceRoomResource
    {
        $this->authorize('muteSpeaker', $room);

        return new VoiceRoomResource($action->execute(
            $room,
            new SeatActionData(
                (int) $request->validated('seat_index'),
                $request->has('muted') ? (bool) $request->validated('muted') : true,
            ),
        ));
    }

    public function end(VoiceRoom $room, EndVoiceRoom $action): VoiceRoomResource
    {
        $this->authorize('end', $room);

        return new VoiceRoomResource($action->execute($room));
    }

    private function connectionResponse(Request $request, VoiceRoomConnectionData $result, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => [
                'room' => (new VoiceRoomResource($result->room))->resolve($request),
                'rtc' => $result->rtc->toArray(),
            ],
        ], $status);
    }
}
