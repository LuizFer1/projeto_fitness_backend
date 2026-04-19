<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class SubscriptionController extends Controller
{
    #[OA\Get(
        path: '/api/v1/subscriptions/me',
        summary: 'Minha assinatura atual',
        description: 'Retorna a assinatura ativa/trial do usuário. Se não houver, retorna plano free implícito.',
        tags: ['Subscriptions'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Assinatura atual do usuário'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function me(Request $request)
    {
        $user = $request->user();

        $subscription = Subscription::with(['plan', 'planPrice'])
            ->where('user_id', $user->id)
            ->whereIn('status', [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE])
            ->latest('started_at')
            ->first();

        if (! $subscription) {
            return response()->json([
                'data' => [
                    'plan_code' => 'free',
                    'status'    => null,
                    'message'   => 'Usuário no plano free implícito.',
                ],
            ]);
        }

        return response()->json(['data' => $this->serialize($subscription)]);
    }

    #[OA\Post(
        path: '/api/v1/subscriptions',
        summary: 'Criar assinatura',
        description: 'Cria uma assinatura para o usuário. Suporta trial se o plano tiver dias de trial. Endpoint idempotente via header Idempotency-Key.',
        tags: ['Subscriptions'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: false, description: 'Chave de idempotência', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['plan_code'],
                properties: [
                    new OA\Property(property: 'plan_code', type: 'string', example: 'premium'),
                    new OA\Property(property: 'billing_period', type: 'string', enum: ['monthly', 'semiannual', 'annual'], example: 'monthly'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Assinatura criada'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 409, description: 'Usuário já possui assinatura ativa'),
            new OA\Response(response: 422, description: 'Erro de validação ou preço indisponível'),
        ]
    )]
    public function store(Request $request)
    {
        $data = $request->validate([
            'plan_code'       => ['required', 'string', Rule::exists('plans', 'code')->where('is_active', true)],
            'billing_period'  => ['required_unless:plan_code,free', 'nullable', Rule::in(['monthly', 'semiannual', 'annual'])],
        ]);

        $user = $request->user();

        $exists = Subscription::activeForUser($user->id)->exists();
        if ($exists) {
            return response()->json([
                'message' => 'Usuário já possui assinatura ativa. Cancele antes de criar outra.',
            ], 409);
        }

        $plan = Plan::where('code', $data['plan_code'])->where('is_active', true)->firstOrFail();

        $planPrice = null;
        if ($plan->code !== 'free') {
            $planPrice = PlanPrice::where('plan_id', $plan->id)
                ->where('billing_period', $data['billing_period'])
                ->where('is_active', true)
                ->first();

            if (! $planPrice) {
                return response()->json([
                    'message' => 'Preço indisponível para o período selecionado.',
                ], 422);
            }
        }

        $now = CarbonImmutable::now();
        $trialEndsAt = $plan->trial_days > 0 ? $now->addDays($plan->trial_days) : null;
        $status = $trialEndsAt ? Subscription::STATUS_TRIALING : Subscription::STATUS_ACTIVE;

        $currentPeriodEnd = match ($data['billing_period'] ?? null) {
            'monthly'    => $now->addMonth(),
            'semiannual' => $now->addMonths(6),
            'annual'     => $now->addYear(),
            default      => null,
        };

        $subscription = Subscription::create([
            'user_id'             => $user->id,
            'plan_id'             => $plan->id,
            'plan_price_id'       => $planPrice?->id,
            'status'              => $status,
            'started_at'          => $now,
            'trial_ends_at'       => $trialEndsAt,
            'current_period_end'  => $currentPeriodEnd,
            'cancel_at_period_end' => false,
        ]);

        $subscription->load(['plan', 'planPrice']);

        return response()->json(['data' => $this->serialize($subscription)], 201);
    }

    #[OA\Post(
        path: '/api/v1/subscriptions/cancel',
        summary: 'Cancelar assinatura',
        description: 'Marca a assinatura ativa para cancelamento ao fim do período corrente. Endpoint idempotente via header Idempotency-Key.',
        tags: ['Subscriptions'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: false, description: 'Chave de idempotência', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Assinatura marcada para cancelamento'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 404, description: 'Nenhuma assinatura ativa encontrada'),
        ]
    )]
    public function cancel(Request $request)
    {
        $user = $request->user();

        $subscription = Subscription::activeForUser($user->id)->latest('started_at')->first();

        if (! $subscription) {
            return response()->json(['message' => 'Nenhuma assinatura ativa encontrada.'], 404);
        }

        $subscription->update([
            'cancel_at_period_end' => true,
            'canceled_at' => CarbonImmutable::now(),
        ]);

        $subscription->load(['plan', 'planPrice']);

        return response()->json(['data' => $this->serialize($subscription)]);
    }

    #[OA\Post(
        path: '/api/v1/subscriptions/resume',
        summary: 'Retomar assinatura cancelada',
        description: 'Reativa uma assinatura marcada para cancelamento (antes do fim do período). Endpoint idempotente via header Idempotency-Key.',
        tags: ['Subscriptions'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: false, description: 'Chave de idempotência', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Assinatura retomada'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 404, description: 'Nenhuma assinatura cancelável encontrada'),
            new OA\Response(response: 422, description: 'Período de cobrança já encerrado'),
        ]
    )]
    public function resume(Request $request)
    {
        $user = $request->user();

        $subscription = Subscription::activeForUser($user->id)
            ->where('cancel_at_period_end', true)
            ->latest('started_at')
            ->first();

        if (! $subscription) {
            return response()->json(['message' => 'Nenhuma assinatura cancelável encontrada.'], 404);
        }

        $periodEnd = $subscription->current_period_end;
        if ($periodEnd && $periodEnd->isPast()) {
            return response()->json(['message' => 'Período de cobrança já encerrado.'], 422);
        }

        $subscription->update([
            'cancel_at_period_end' => false,
            'canceled_at' => null,
        ]);

        $subscription->load(['plan', 'planPrice']);

        return response()->json(['data' => $this->serialize($subscription)]);
    }

    private function serialize(Subscription $s): array
    {
        return [
            'id'                    => $s->id,
            'plan_code'             => $s->plan->code,
            'plan_name'             => $s->plan->name,
            'billing_period'        => $s->planPrice?->billing_period,
            'price_cents'           => $s->planPrice?->price_cents,
            'currency'              => $s->planPrice?->currency,
            'status'                => $s->status,
            'started_at'            => $s->started_at,
            'trial_ends_at'         => $s->trial_ends_at,
            'current_period_end'    => $s->current_period_end,
            'canceled_at'           => $s->canceled_at,
            'cancel_at_period_end'  => $s->cancel_at_period_end,
        ];
    }
}
