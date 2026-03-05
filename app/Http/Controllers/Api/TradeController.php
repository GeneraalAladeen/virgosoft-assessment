<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TradeIndexRequest;
use App\Http\Resources\TradeResource;
use App\Models\Trade;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TradeController extends Controller
{
    public function index(TradeIndexRequest $request): AnonymousResourceCollection
    {
        $trades = Trade::with(['buyOrder', 'sellOrder', 'buyer', 'seller'])
            ->when($request->filled('symbol'), fn ($q) => $q->whereHas('buyOrder', fn ($q) => $q->where('symbol', $request->symbol)))
            ->latest()
            ->simplePaginate(10);

        return TradeResource::collection($trades);
    }
}
