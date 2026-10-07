<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLabelRequest;
use App\Http\Requests\UpdateLabelRequest;
use App\Http\Resources\LabelResource;
use App\Models\Etiqueta;
use App\Services\Label\LabelReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Etiquetas (labels) CRUD + the label roll-up report. Every query is tenant
 * scoped by the global UserScope, so route-model binding only resolves the
 * authenticated user's labels.
 */
class LabelController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $labels = Etiqueta::query()->withCount('accounts')->orderBy('name')->get();

        return LabelResource::collection($labels);
    }

    public function store(StoreLabelRequest $request): JsonResponse
    {
        $label = Etiqueta::create($request->validated());

        return LabelResource::make($label)->response()->setStatusCode(201);
    }

    public function update(UpdateLabelRequest $request, Etiqueta $label): LabelResource
    {
        $label->update($request->validated());

        return LabelResource::make($label);
    }

    public function destroy(Etiqueta $label): JsonResponse
    {
        $label->delete(); // pivot rows cascade

        return response()->json(status: 204);
    }

    /** Label roll-up report for a period. */
    public function report(Request $request, LabelReportService $service): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return response()->json($service->report($request->user()->id, $data['from'], $data['to']));
    }
}
