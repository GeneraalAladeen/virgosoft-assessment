<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\OrderMatched;
use App\Events\OrderPlaced;
use App\Events\TradeExecuted;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderMatchingService
{
    public const COMMISSION_RATE = '0.015';

    public function placeOrder(User $user, string $symbol, string $side, string $price, string $amount): Order
    {
        return DB::transaction(function () use ($user, $symbol, $side, $price, $amount) {
            $user = User::lockForUpdate()->findOrFail($user->id);

            if ($side === 'buy') {
                $cost = bcmul($amount, $price, 8);

                if (bccomp((string) $user->balance, $cost, 8) < 0) {
                    throw ValidationException::withMessages([
                        'balance' => 'Insufficient USD balance to place this buy order.',
                    ]);
                }

                $user->balance = bcsub($user->balance, $cost, 8);
                $user->save();
            } else {
                $asset = Asset::where('user_id', $user->id)
                    ->where('symbol', $symbol)
                    ->lockForUpdate()
                    ->first();

                $available = $asset ? bcsub((string) $asset->amount, (string) $asset->locked_amount, 8) : '0.00000000';

                if (bccomp($available, $amount, 8) < 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'Insufficient asset balance to place this sell order.',
                    ]);
                }

                $asset->locked_amount = bcadd((string) $asset->locked_amount, $amount, 8);
                $asset->save();
            }

            $order = Order::create([
                'user_id' => $user->id,
                'symbol' => $symbol,
                'side' => $side,
                'price' => $price,
                'amount' => $amount,
                'status' => OrderStatus::Open,
            ]);

            DB::afterCommit(fn () => broadcast(new OrderPlaced($order)));

            $this->matchOrder($order);

            return $order->fresh();
        });
    }

    private function matchOrder(Order $order): void
    {
        if ($order->side === 'buy') {
            $counterOrder = Order::where('symbol', $order->symbol)
                ->where('side', 'sell')
                ->where('status', OrderStatus::Open)
                ->where('price', '<=', $order->price)
                ->where('amount', $order->amount)
                ->where('user_id', '!=', $order->user_id)
                ->lockForUpdate()
                ->orderBy('price', 'asc')
                ->orderBy('created_at', 'asc')
                ->first();

            if ($counterOrder) {
                $this->settle($order, $counterOrder);
            }
        } else {
            $counterOrder = Order::where('symbol', $order->symbol)
                ->where('side', 'buy')
                ->where('status', OrderStatus::Open)
                ->where('price', '>=', $order->price)
                ->where('amount', $order->amount)
                ->where('user_id', '!=', $order->user_id)
                ->lockForUpdate()
                ->orderBy('price', 'desc')
                ->orderBy('created_at', 'asc')
                ->first();

            if ($counterOrder) {
                $this->settle($counterOrder, $order);
            }
        }
    }

    /**
     * Settle a matched buy/sell pair.
     * Matched price is the sell (maker) order's price.
     * Commission of 1.5% of volume is deducted from the buyer only.
     */
    private function settle(Order $buyOrder, Order $sellOrder): void
    {
        $matchedPrice = $sellOrder->price;
        $volume = bcmul((string) $buyOrder->amount, (string) $matchedPrice, 8);
        $commission = bcmul($volume, self::COMMISSION_RATE, 8);

        $buyer = User::lockForUpdate()->findOrFail($buyOrder->user_id);
        $seller = User::lockForUpdate()->findOrFail($sellOrder->user_id);

        $priceImprovement = bcmul(bcsub((string) $buyOrder->price, (string) $matchedPrice, 8), (string) $buyOrder->amount, 8);
        $buyer->balance = bcadd((string) $buyer->balance, bcsub($priceImprovement, $commission, 8), 8);
        $buyer->save();

        $seller->balance = bcadd((string) $seller->balance, $volume, 8);
        $seller->save();

        $buyerAsset = Asset::where('user_id', $buyer->id)
            ->where('symbol', $buyOrder->symbol)
            ->lockForUpdate()
            ->first();

        if ($buyerAsset) {
            $buyerAsset->amount = bcadd((string) $buyerAsset->amount, (string) $buyOrder->amount, 8);
            $buyerAsset->save();
        } else {
            Asset::create([
                'user_id' => $buyer->id,
                'symbol' => $buyOrder->symbol,
                'amount' => $buyOrder->amount,
                'locked_amount' => '0.00000000',
            ]);
        }

        $sellerAsset = Asset::where('user_id', $seller->id)
            ->where('symbol', $sellOrder->symbol)
            ->lockForUpdate()
            ->firstOrFail();

        $sellerAsset->locked_amount = bcsub((string) $sellerAsset->locked_amount, (string) $sellOrder->amount, 8);
        $sellerAsset->amount = bcsub((string) $sellerAsset->amount, (string) $sellOrder->amount, 8);
        $sellerAsset->save();

        $buyOrder->status = OrderStatus::Filled;
        $buyOrder->save();

        $sellOrder->status = OrderStatus::Filled;
        $sellOrder->save();

        Trade::create([
            'buy_order_id'  => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'buyer_id'      => $buyer->id,
            'seller_id'     => $seller->id,
            'commission'    => $commission,
        ]);

        DB::afterCommit(function () use ($buyOrder, $sellOrder, $matchedPrice, $volume, $commission, $buyer, $seller) {
            $buyer->load('assets');
            $seller->load('assets');
            broadcast(new OrderMatched($buyOrder, $sellOrder, (string) $matchedPrice, $volume, $commission, $buyer, $seller));
            broadcast(new TradeExecuted($buyOrder, $sellOrder, (string) $matchedPrice, $volume, $commission, $buyer, $seller));
        });
    }
}
