<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\EcommercePaymentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcommercePaymentTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', EcommercePaymentType::class);

        $types = EcommercePaymentType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name_de')
            ->get();

        return response()->json(['data' => $types]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', EcommercePaymentType::class);

        $data = $request->validate([
            'technical_name' => 'required|string|max:100|unique:ecommerce_payment_types',
            'name_de'        => 'required|string|max:255',
            'name_en'        => 'nullable|string|max:255',
            'description_de' => 'nullable|string',
            'description_en' => 'nullable|string',
            'icon'           => 'nullable|string|max:50',
            'is_active'      => 'boolean',
            'sort_order'     => 'integer',
        ]);

        $type = EcommercePaymentType::create($data);

        return response()->json(['data' => $type], 201);
    }

    public function show(EcommercePaymentType $paymentType): JsonResponse
    {
        $this->authorize('view', $paymentType);

        return response()->json(['data' => $paymentType]);
    }

    public function update(Request $request, EcommercePaymentType $paymentType): JsonResponse
    {
        $this->authorize('update', $paymentType);

        $data = $request->validate([
            'technical_name' => "sometimes|string|max:100|unique:ecommerce_payment_types,technical_name,{$paymentType->id}",
            'name_de'        => 'sometimes|string|max:255',
            'name_en'        => 'nullable|string|max:255',
            'description_de' => 'nullable|string',
            'description_en' => 'nullable|string',
            'icon'           => 'nullable|string|max:50',
            'is_active'      => 'boolean',
            'sort_order'     => 'integer',
        ]);

        $paymentType->update($data);

        return response()->json(['data' => $paymentType->fresh()]);
    }

    public function destroy(EcommercePaymentType $paymentType): JsonResponse
    {
        $this->authorize('delete', $paymentType);

        if ($paymentType->orders()->exists()) {
            return response()->json([
                'message' => 'Zahlungsart kann nicht gelöscht werden — es existieren abhängige Bestellungen.',
            ], 409);
        }

        $paymentType->delete();

        return response()->json(null, 204);
    }
}
