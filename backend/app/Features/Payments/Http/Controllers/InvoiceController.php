<?php

namespace App\Features\Payments\Http\Controllers;

use App\Features\Payments\Actions\ListInvoices;
use App\Features\Payments\Http\Resources\InvoiceResource;
use App\Features\Payments\Models\Invoice;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class InvoiceController extends Controller
{
    public function index(Request $request, ListInvoices $action): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Invoice::class);

        return InvoiceResource::collection(
            $action->forUser($request->user(), (int) $request->integer('per_page', 20)),
        );
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return response()->json([
            'data' => (new InvoiceResource($invoice->load('lines')))->resolve($request),
        ]);
    }
}
