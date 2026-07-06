<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\EcommerceCartType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcommerceCartTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EcommerceCartType::class);

        $types = EcommerceCartType::query()
            ->with('priceType')
            ->orderBy('sort_order')
            ->orderBy('name_de')
            ->get();

        return response()->json(['data' => $types]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', EcommerceCartType::class);

        $data = $request->validate([
            'technical_name' => 'required|string|max:100|unique:ecommerce_cart_types',
            'name_de'        => 'required|string|max:255',
            'name_en'        => 'nullable|string|max:255',
            'name_json'      => 'nullable|array',
            'type'           => 'required|in:wishlist,pdf_list,ecommerce',
            'price_type_id'  => 'nullable|uuid|exists:price_types,id',
            'show_prices'    => 'boolean',
            'show_quantity'  => 'boolean',
            'show_total'     => 'boolean',
            'allow_checkout' => 'boolean',
            'adapter_type'   => 'in:none,email,rest_api',
            'adapter_config' => 'nullable|array',
            'is_active'      => 'boolean',
            'sort_order'     => 'integer',
        ]);

        $type = EcommerceCartType::create($data);

        return response()->json(['data' => $type->load('priceType')], 201);
    }

    public function show(EcommerceCartType $cartType): JsonResponse
    {
        $this->authorize('view', $cartType);

        return response()->json(['data' => $cartType->load('priceType')]);
    }

    public function update(Request $request, EcommerceCartType $cartType): JsonResponse
    {
        $this->authorize('update', $cartType);

        $data = $request->validate([
            'technical_name' => "sometimes|string|max:100|unique:ecommerce_cart_types,technical_name,{$cartType->id}",
            'name_de'        => 'sometimes|string|max:255',
            'name_en'        => 'nullable|string|max:255',
            'name_json'      => 'nullable|array',
            'type'           => 'sometimes|in:wishlist,pdf_list,ecommerce',
            'price_type_id'  => 'nullable|uuid|exists:price_types,id',
            'show_prices'    => 'boolean',
            'show_quantity'  => 'boolean',
            'show_total'     => 'boolean',
            'allow_checkout' => 'boolean',
            'adapter_type'   => 'in:none,email,rest_api',
            'adapter_config' => 'nullable|array',
            'is_active'      => 'boolean',
            'sort_order'     => 'integer',
        ]);

        $cartType->update($data);

        return response()->json(['data' => $cartType->fresh('priceType')]);
    }

    public function destroy(EcommerceCartType $cartType): JsonResponse
    {
        $this->authorize('delete', $cartType);

        // Prüfen ob noch aktive Warenkörbe oder Bestellungen vorhanden
        $cartCount  = $cartType->carts()->active()->count();
        $orderCount = $cartType->orders()->count();

        if ($cartCount > 0 || $orderCount > 0) {
            return response()->json([
                'message' => 'Warenkorbtyp kann nicht gelöscht werden — es existieren abhängige Warenkörbe oder Bestellungen.',
                'counts'  => ['carts' => $cartCount, 'orders' => $orderCount],
            ], 409);
        }

        $cartType->delete();

        return response()->json(null, 204);
    }
}
