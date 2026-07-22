<?php

namespace App\Features\LiveStreaming\Http\Controllers;

use App\Features\LiveStreaming\Http\Resources\LiveCategoryResource;
use App\Features\LiveStreaming\Models\LiveCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class LiveCategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return LiveCategoryResource::collection(
            LiveCategory::query()
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
        );
    }
}
