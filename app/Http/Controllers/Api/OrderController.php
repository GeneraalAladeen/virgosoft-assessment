<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderIndexRequest;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

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

    public function cancel(Order $order): JsonResponse
    {
        DB::transaction(function () use ($order): void {
            $user = User::lockForUpdate()->findOrFail($order->user_id);

            if ($order->side === 'buy') {
                $refund = bcmul((string) $order->amount, (string) $order->price, 8);
                $user->balance = bcadd((string) $user->balance, $refund, 8);
                $user->save();
            } else {
                $asset = Asset::where('user_id', $user->id)
                    ->where('symbol', $order->symbol)
                    ->lockForUpdate()
                    ->firstOrFail();

                $asset->locked_amount = bcsub((string) $asset->locked_amount, (string) $order->amount, 8);
                $asset->save();
            }

            $order->status = OrderStatus::Cancelled;
            $order->save();
        });

        return (new OrderResource($order->fresh()))->response();
    }
}
