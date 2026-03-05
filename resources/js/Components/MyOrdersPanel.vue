<script setup>
import { formatPrice, formatAmount, statusLabel, statusClass } from '@/utils/trading';

defineProps({
    orders: { type: Array, required: true },
    loadingOrders: { type: Boolean, default: false },
    filterSide: { type: String, default: '' },
    filterStatus: { type: String, default: '' },
});

const emit = defineEmits(['update:filterSide', 'update:filterStatus', 'cancel']);
</script>

<template>
    <div class="rounded-lg bg-white p-4 shadow-sm">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-gray-700 shrink-0">My Orders</h3>
            <div class="flex gap-1.5 flex-wrap justify-end">
                <select
                    :value="filterSide"
                    @change="emit('update:filterSide', $event.target.value)"
                    class="text-xs border border-gray-200 rounded px-1.5 py-0.5 text-gray-600 bg-white"
                >
                    <option value="">All sides</option>
                    <option value="buy">Buy</option>
                    <option value="sell">Sell</option>
                </select>
                <select
                    :value="filterStatus"
                    @change="emit('update:filterStatus', $event.target.value)"
                    class="text-xs border border-gray-200 rounded px-1.5 py-0.5 text-gray-600 bg-white"
                >
                    <option value="">All statuses</option>
                    <option value="1">Open</option>
                    <option value="2">Filled</option>
                    <option value="3">Cancelled</option>
                </select>
            </div>
        </div>

        <div v-if="orders.length" class="space-y-2">
            <div
                v-for="order in orders"
                :key="order.id"
                class="rounded border border-gray-100 p-2 text-xs"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span
                            :class="order.side === 'buy' ? 'text-green-600' : 'text-red-500'"
                            class="font-semibold uppercase"
                        >
                            {{ order.side }}
                        </span>
                        <span class="text-gray-500">{{ order.symbol }}/USD</span>
                    </div>
                    <span :class="statusClass(order.status)" class="font-medium">
                        {{ statusLabel(order.status) }}
                    </span>
                </div>
                <div class="mt-1 flex justify-between text-gray-500">
                    <span>{{ formatAmount(order.amount) }} @ ${{ formatPrice(order.price) }}</span>
                    <button
                        v-if="order.status === 1"
                        @click="emit('cancel', order.id)"
                        class="text-red-400 hover:text-red-600"
                    >
                        Cancel
                    </button>
                </div>
                <div v-if="order.status === 2 && order.matched_price" class="mt-1 text-gray-400">
                    Filled at ${{ formatPrice(order.matched_price) }}
                    <span v-if="order.commission" class="text-orange-400">· commission ${{ formatPrice(order.commission) }}</span>
                </div>
            </div>
        </div>
        <p v-else-if="!loadingOrders" class="text-xs text-gray-400">No orders yet</p>
        <p v-else class="text-xs text-gray-400">Loading...</p>
    </div>
</template>
