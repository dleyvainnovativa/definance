<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Tax\TaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function __construct(private readonly TaxService $tax)
    {
    }

    /** Rates + the user's resolved IVA accounts, for the entry form. */
    public function config(Request $request): JsonResponse
    {
        $accounts = $this->tax->accounts($request->user()->id);

        return response()->json([
            'rates' => $this->tax->rates(),
            'accounts' => [
                'acreditable' => $accounts['acreditable']
                    ? ['id' => $accounts['acreditable']->id, 'code' => $accounts['acreditable']->code, 'name' => $accounts['acreditable']->name]
                    : null,
                'trasladado' => $accounts['trasladado']
                    ? ['id' => $accounts['trasladado']->id, 'code' => $accounts['trasladado']->code, 'name' => $accounts['trasladado']->name]
                    : null,
            ],
        ]);
    }

    /** IVA declaration for a period. */
    public function iva(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return response()->json($this->tax->ivaReport($request->user()->id, $data['from'], $data['to']));
    }
}
