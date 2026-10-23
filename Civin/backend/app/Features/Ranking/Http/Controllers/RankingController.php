<?php

namespace App\Features\Ranking\Http\Controllers;

use App\Features\Ranking\Actions\ListGifterRankings;
use App\Features\Ranking\Actions\ListHostRankings;
use App\Features\Ranking\Actions\ListPkRankings;
use App\Features\Ranking\Actions\ListVoiceRankings;
use App\Features\Ranking\Http\Requests\ListRankingsRequest;
use App\Features\Ranking\Http\Resources\RankingEntryResource;
use App\Features\Ranking\Models\Ranking;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class RankingController extends Controller
{
    public function hosts(ListRankingsRequest $request, ListHostRankings $action): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Ranking::class);

        return RankingEntryResource::collection(
            $action->execute(
                $request->user(),
                $request->period(),
                $request->country(),
                $request->limit(),
            ),
        );
    }

    public function gifters(ListRankingsRequest $request, ListGifterRankings $action): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Ranking::class);

        return RankingEntryResource::collection(
            $action->execute(
                $request->user(),
                $request->period(),
                $request->country(),
                $request->limit(),
            ),
        );
    }

    public function pk(ListRankingsRequest $request, ListPkRankings $action): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Ranking::class);

        return RankingEntryResource::collection(
            $action->execute(
                $request->user(),
                $request->period(),
                $request->country(),
                $request->limit(),
            ),
        );
    }

    public function voice(ListRankingsRequest $request, ListVoiceRankings $action): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Ranking::class);

        return RankingEntryResource::collection(
            $action->execute(
                $request->user(),
                $request->period(),
                $request->country(),
                $request->limit(),
            ),
        );
    }
}
