<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderIndexRequest;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(private readonly OrderMatchingService $matchingService) {}

    public function index(OrderIndexRequest $request): AnonymousResourceCollection
    {
        if ($request->filled('symbol')) {
            $orders = Order::query()
                ->where('symbol', $request->symbol)
                ->where('status', OrderStatus::Open)
                ->orderBy('price')
                ->orderBy('created_at')
                ->simplePaginate();
        } else {
            $orders = $request->user()
                ->orders()
                ->latest()
                ->simplePaginate();
        }

        return OrderResource::collection($orders);
    }

    public function store(PlaceOrderRequest $request): JsonResponse
    {
        $order = $this->matchingService->placeOrder(
            user: $request->user(),
            symbol: $request->string('symbol')->toString(),
            side: $request->string('side')->toString(),
            price: $request->string('price')->toString(),
            amount: $request->string('amount')->toString(),
        );

        return (new OrderResource($order))->response()->setStatusCode(201);
    }
}
