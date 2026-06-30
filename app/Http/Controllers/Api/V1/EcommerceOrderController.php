<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\EcommerceOrder;
use App\Services\Ecommerce\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcommerceOrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function index(Request $request): JsonResponse
    {
        $query = EcommerceOrder::query()
            ->with(['cartType', 'paymentType'])
            ->orderBy('submitted_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('cart_type_id')) {
            $query->where('cart_type_id', $request->input('cart_type_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereJsonContains('billing_address->email', $search);
            });
        }

        $orders = $query->paginate($this->getPerPage($request));

        return response()->json($orders);
    }

    public function show(EcommerceOrder $order): JsonResponse
    {
        return response()->json([
            'data' => $order->load(['cartType', 'paymentType', 'user']),
        ]);
    }

    public function update(Request $request, EcommerceOrder $order): JsonResponse
    {
        $data = $request->validate([
            'status'         => 'sometimes|in:pending,processing,confirmed,failed',
            'internal_notes' => 'nullable|string',
        ]);

        $order->update($data);

        return response()->json(['data' => $order->fresh(['cartType', 'paymentType'])]);
    }

    // Adapter erneut auslösen (z.B. nach Email-Fehler)
    public function retry(EcommerceOrder $order): JsonResponse
    {
        $updated = $this->orderService->retryDispatch($order);

        return response()->json(['data' => $updated->load(['cartType', 'paymentType'])]);
    }
}
