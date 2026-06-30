<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EcommerceCart;
use App\Models\EcommerceCartItem;
use App\Models\EcommerceCartType;
use App\Services\Ecommerce\CartService;
use App\Services\Ecommerce\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly OrderService $orderService,
    ) {}

    // Aktuellen Warenkorb inkl. Items zurückgeben
    public function show(Request $request, string $cartTypeName): JsonResponse
    {
        $cartType = EcommerceCartType::where('technical_name', $cartTypeName)
            ->where('is_active', true)
            ->firstOrFail();

        $cart = $this->cartService->getOrCreateCart($request, $cartType);

        return response()->json(['data' => $this->cartService->getCartData($cart)]);
    }

    // Produkt zum Warenkorb hinzufügen
    public function addItem(Request $request, string $cartTypeName): JsonResponse
    {
        $cartType = EcommerceCartType::where('technical_name', $cartTypeName)
            ->where('is_active', true)
            ->firstOrFail();

        $data = $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'quantity'   => 'nullable|numeric|min:0.001|max:99999',
            'note'       => 'nullable|string|max:500',
        ]);

        $cart = $this->cartService->getOrCreateCart($request, $cartType);
        $item = $this->cartService->addItem($cart, $data['product_id'], (float) ($data['quantity'] ?? 1.0));

        if (isset($data['note'])) {
            $item->update(['note' => $data['note']]);
        }

        return response()->json(['data' => $this->cartService->getCartData($cart)], 201);
    }

    // Menge eines Items aktualisieren (Produkt-ID als URL-Parameter)
    public function updateItem(Request $request, string $cartTypeName, string $productId): JsonResponse
    {
        $cartType = EcommerceCartType::where('technical_name', $cartTypeName)
            ->where('is_active', true)
            ->firstOrFail();

        $data = $request->validate([
            'quantity' => 'required|numeric|min:0.001|max:99999',
            'note'     => 'nullable|string|max:500',
        ]);

        $cart = $this->cartService->getOrCreateCart($request, $cartType);
        $item = EcommerceCartItem::where('cart_id', $cart->id)
            ->where('product_id', $productId)
            ->firstOrFail();

        $this->cartService->updateItem($item, (float) $data['quantity']);

        if (array_key_exists('note', $data)) {
            $item->update(['note' => $data['note']]);
        }

        return response()->json(['data' => $this->cartService->getCartData($cart)]);
    }

    // Produkt aus dem Warenkorb entfernen
    public function removeItem(Request $request, string $cartTypeName, string $productId): JsonResponse
    {
        $cartType = EcommerceCartType::where('technical_name', $cartTypeName)
            ->where('is_active', true)
            ->firstOrFail();

        $cart = $this->cartService->getOrCreateCart($request, $cartType);
        $item = EcommerceCartItem::where('cart_id', $cart->id)
            ->where('product_id', $productId)
            ->firstOrFail();

        $this->cartService->removeItem($item);

        return response()->json(['data' => $this->cartService->getCartData($cart)]);
    }

    // Warenkorb leeren
    public function clear(Request $request, string $cartTypeName): JsonResponse
    {
        $cartType = EcommerceCartType::where('technical_name', $cartTypeName)
            ->where('is_active', true)
            ->firstOrFail();

        $cart = $this->cartService->getOrCreateCart($request, $cartType);
        $this->cartService->clearCart($cart);

        return response()->json(['data' => $this->cartService->getCartData($cart)]);
    }

    // Warenkorb als Bestellung/Anfrage abschicken
    public function submit(Request $request, string $cartTypeName): JsonResponse
    {
        $cartType = EcommerceCartType::where('technical_name', $cartTypeName)
            ->where('is_active', true)
            ->where('allow_checkout', true)
            ->firstOrFail();

        $data = $request->validate([
            'billing_address'  => 'nullable|array',
            'shipping_address' => 'nullable|array',
            'payment_type_id'  => 'nullable|uuid|exists:ecommerce_payment_types,id',
            'notes'            => 'nullable|string|max:2000',
        ]);

        $cart  = $this->cartService->getOrCreateCart($request, $cartType);
        $order = $this->orderService->submitCart($cart, $data);

        return response()->json([
            'data' => [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'status'       => $order->status,
                'total_amount' => $order->total_amount,
                'currency'     => $order->currency,
                'submitted_at' => $order->submitted_at?->toISOString(),
            ],
        ], 201);
    }

    // Gast-Cart nach dem Login mit Benutzer-Account mergen
    public function merge(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Nicht authentifiziert.'], 401);
        }

        $sessionHash = $this->cartService->resolveSessionId($request);
        $this->cartService->mergeGuestCart($sessionHash, $request->user());

        return response()->json(['message' => 'Warenkorb erfolgreich gemergt.']);
    }
}
