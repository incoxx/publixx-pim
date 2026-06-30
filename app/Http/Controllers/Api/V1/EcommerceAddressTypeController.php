<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\EcommerceAddressType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcommerceAddressTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $types = EcommerceAddressType::query()
            ->orderBy('sort_order')
            ->orderBy('name_de')
            ->get();

        return response()->json(['data' => $types]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'technical_name' => 'required|string|max:100|unique:ecommerce_address_types',
            'name_de'        => 'required|string|max:255',
            'name_en'        => 'nullable|string|max:255',
            'role'           => 'required|in:billing,shipping,general',
            'field_schema'   => 'nullable|array',
            'field_schema.*.key'       => 'required_with:field_schema|string|max:100',
            'field_schema.*.label_de'  => 'required_with:field_schema|string|max:255',
            'field_schema.*.type'      => 'required_with:field_schema|in:text,email,phone,select,checkbox,textarea',
            'field_schema.*.required'  => 'boolean',
            'field_schema.*.sort_order' => 'integer',
            'is_active'      => 'boolean',
            'sort_order'     => 'integer',
        ]);

        $type = EcommerceAddressType::create($data);

        return response()->json(['data' => $type], 201);
    }

    public function show(EcommerceAddressType $addressType): JsonResponse
    {
        return response()->json(['data' => $addressType]);
    }

    public function update(Request $request, EcommerceAddressType $addressType): JsonResponse
    {
        $data = $request->validate([
            'technical_name' => "sometimes|string|max:100|unique:ecommerce_address_types,technical_name,{$addressType->id}",
            'name_de'        => 'sometimes|string|max:255',
            'name_en'        => 'nullable|string|max:255',
            'role'           => 'sometimes|in:billing,shipping,general',
            'field_schema'   => 'nullable|array',
            'field_schema.*.key'       => 'required_with:field_schema|string|max:100',
            'field_schema.*.label_de'  => 'required_with:field_schema|string|max:255',
            'field_schema.*.type'      => 'required_with:field_schema|in:text,email,phone,select,checkbox,textarea',
            'field_schema.*.required'  => 'boolean',
            'field_schema.*.sort_order' => 'integer',
            'is_active'      => 'boolean',
            'sort_order'     => 'integer',
        ]);

        $addressType->update($data);

        return response()->json(['data' => $addressType->fresh()]);
    }

    public function destroy(EcommerceAddressType $addressType): JsonResponse
    {
        $addressType->delete();

        return response()->json(null, 204);
    }
}
