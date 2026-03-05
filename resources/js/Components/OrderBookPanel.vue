<script setup>
import { formatPrice, formatAmount } from '@/utils/trading';

defineProps({
    asksDisplay: { type: Array, required: true },
    bidsDisplay: { type: Array, required: true },
    trades: { type: Array, required: true },
    selectedSymbol: { type: String, required: true },
    userId: { type: Number, required: true },
    loadingOrderbook: { type: Boolean, default: false },
});
</script>

<template>
    <div class="rounded-lg bg-white shadow-sm overflow-hidden">
        <!-- Header -->
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-800">Order Book</h3>
            <span class="text-xs font-medium text-gray-500 bg-gray-100 rounded px-2 py-0.5">{{ selectedSymbol }}/USD</span>
        </div>

        <!-- Column headers -->
        <div class="px-4 py-1.5 grid grid-cols-2 text-xs font-medium text-gray-400 bg-gray-50 border-b border-gray-100">
            <span>Price (USD)</span>
            <span class="text-right">Amount ({{ selectedSymbol }})</span>
        </div>

        <!-- Asks (sells) — worst to best, best ask at bottom -->
        <div class="px-4 pt-2 pb-1">
            <div class="mb-1 text-xs font-semibold text-red-400 uppercase tracking-wide">
                Asks <span class="normal-case font-normal text-gray-400">— sellers offering {{ selectedSymbol }}</span>
            </div>
            <div v-if="asksDisplay.length" class="space-y-0.5">
                <div
                    v-for="order in asksDisplay"
                    :key="order.id"
                    :title="order.user_id === userId ? 'Your order — cannot self-match' : ''"
                    :class="order.user_id === userId
                        ? 'border-orange-300 bg-orange-50/60'
                        : 'border-transparent hover:border-red-300 hover:bg-red-50/50'"
                    class="grid grid-cols-2 text-xs py-0.5 border-l-2 rounded-r transition-colors"
                >
                    <span class="font-mono font-medium text-red-500 flex items-center gap-1">
                        {{ formatPrice(order.price) }}
                        <span v-if="order.user_id === userId" class="text-orange-400 font-semibold normal-case tracking-normal">·You</span>
                    </span>
                    <span class="text-right text-gray-600 font-mono">{{ formatAmount(order.amount) }}</span>
                </div>
            </div>
            <p v-else class="text-xs text-gray-400 py-2">No sell orders</p>
        </div>

        <div class="mx-4 my-2 border-t border-gray-100"></div>

        <!-- Bids (buys) — best bid at top -->
        <div class="px-4 pt-1 pb-3">
            <div class="mb-1 text-xs font-semibold text-green-500 uppercase tracking-wide">
                Bids <span class="normal-case font-normal text-gray-400">— buyers wanting {{ selectedSymbol }}</span>
            </div>
            <div v-if="bidsDisplay.length" class="space-y-0.5">
                <div
                    v-for="order in bidsDisplay"
                    :key="order.id"
                    :title="order.user_id === userId ? 'Your order — cannot self-match' : ''"
                    :class="order.user_id === userId
                        ? 'border-orange-300 bg-orange-50/60'
                        : 'border-transparent hover:border-green-400 hover:bg-green-50/50'"
                    class="grid grid-cols-2 text-xs py-0.5 border-l-2 rounded-r transition-colors"
                >
                    <span class="font-mono font-medium text-green-600 flex items-center gap-1">
                        {{ formatPrice(order.price) }}
                        <span v-if="order.user_id === userId" class="text-orange-400 font-semibold normal-case tracking-normal">·You</span>
                    </span>
                    <span class="text-right text-gray-600 font-mono">{{ formatAmount(order.amount) }}</span>
                </div>
            </div>
            <p v-else class="text-xs text-gray-400 py-2">No buy orders</p>
        </div>

        <!-- Recent Trades -->
        <div class="border-t border-gray-100 px-4 pt-3 pb-2">
            <div class="mb-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Recent Trades</div>
            <div v-if="trades.length" class="space-y-1.5">
                <div
                    v-for="trade in trades"
                    :key="trade.id"
                    :class="trade.my_role === 'buyer' ? 'bg-green-50 border-green-200' : trade.my_role === 'seller' ? 'bg-red-50 border-red-200' : 'border-gray-100'"
                    class="rounded border px-2 py-1 text-xs"
                >
                    <div class="flex justify-between">
                        <span class="font-mono font-medium text-gray-800">${{ formatPrice(trade.price) }}</span>
                        <span class="font-mono text-gray-500">{{ formatAmount(trade.amount) }}</span>
                        <span class="text-gray-400">{{ new Date(trade.executed_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }) }}</span>
                    </div>
                    <div class="mt-0.5 flex items-center justify-between text-gray-400">
                        <span>
                            <span :class="trade.my_role === 'seller' ? 'text-red-500 font-medium' : ''">{{ trade.seller?.name ?? '—' }}</span>
                            <span class="mx-1">→</span>
                            <span :class="trade.my_role === 'buyer' ? 'text-green-600 font-medium' : ''">{{ trade.buyer?.name ?? '—' }}</span>
                        </span>
                        <span v-if="trade.my_role === 'buyer' && trade.commission" class="text-orange-400">
                            fee ${{ formatPrice(trade.commission) }}
                        </span>
                        <span v-if="trade.my_role" class="ml-1 rounded px-1 py-0.5 text-white text-[10px] font-semibold"
                            :class="trade.my_role === 'buyer' ? 'bg-green-500' : 'bg-red-400'">
                            You {{ trade.my_role === 'buyer' ? 'bought' : 'sold' }}
                        </span>
                    </div>
                </div>
            </div>
            <p v-else class="text-xs text-gray-400 py-1">No trades yet</p>
        </div>
    </div>
</template>
