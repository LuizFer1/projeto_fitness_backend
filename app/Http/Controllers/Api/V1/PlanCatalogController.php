<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use OpenApi\Attributes as OA;

class PlanCatalogController extends Controller
{
    #[OA\Get(
        path: '/api/v1/plans/catalog',
        summary: 'Catálogo público de planos',
        description: 'Retorna a lista pública de planos ativos com seus preços. Endpoint público (não requer autenticação).',
        tags: ['Plans Catalog'],
        responses: [
            new OA\Response(response: 200, description: 'Catálogo de planos'),
        ]
    )]
    public function index()
    {
        $plans = Plan::where('is_active', true)
            ->with(['activePrices' => fn ($q) => $q->orderBy('price_cents')])
            ->orderBy('name')
            ->get()
            ->map(function (Plan $plan) {
                return [
                    'code'        => $plan->code,
                    'name'        => $plan->name,
                    'description' => $plan->description,
                    'trial_days'  => $plan->trial_days,
                    'prices'      => $plan->activePrices->map(fn ($p) => [
                        'billing_period' => $p->billing_period,
                        'price_cents'    => $p->price_cents,
                        'currency'       => $p->currency,
                    ])->values(),
                ];
            });

        return response()->json(['data' => $plans]);
    }
}
