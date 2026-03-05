<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import LimitOrderForm from '@/Components/LimitOrderForm.vue';
import axios from 'axios';
import { toast } from 'vue-sonner';

const page = usePage();
const userId = computed(() => page.props.auth.user.id);

const selectedSymbol = ref('BTC');
const profile = ref(null);
const myOrders = ref([]);
const orderbook = ref({ bids: [], asks: [] });

const loadingProfile = ref(false);
const loadingOrders = ref(false);
const loadingOrderbook = ref(false);

// Derived wallet display
const usdBalance = computed(() => profile.value ? parseFloat(profile.value.balance).toFixed(2) : '—');
const assets = computed(() => profile.value?.assets ?? []);

async function fetchProfile() {
    loadingProfile.value = true;
    try {
        const { data } = await axios.get('/api/profile');
        profile.value = data.data;
    } catch {
        toast.error('Failed to load wallet');
    } finally {
        loadingProfile.value = false;
    }
}

async function fetchMyOrders() {
    loadingOrders.value = true;
    try {
        const { data } = await axios.get('/api/orders');
        myOrders.value = data.data;
    } catch {
        toast.error('Failed to load orders');
    } finally {
        loadingOrders.value = false;
    }
}

async function fetchOrderbook() {
    loadingOrderbook.value = true;
    try {
        const { data } = await axios.get('/api/orders', {
            params: { symbol: selectedSymbol.value },
        });
        const orders = data.data;
        orderbook.value = {
            bids: orders.filter(o => o.side === 'buy').sort((a, b) => b.price - a.price),
            asks: orders.filter(o => o.side === 'sell').sort((a, b) => a.price - b.price),
        };
    } catch {
        toast.error('Failed to load orderbook');
    } finally {
        loadingOrderbook.value = false;
    }
}

function onOrderPlaced() {
    toast.success('Order placed successfully');
    fetchProfile();
    fetchMyOrders();

}

async function cancelOrder(orderId) {
    try {
        await axios.post(`/api/orders/${orderId}/cancel`);

        myOrders.value = myOrders.value.map(o =>
            o.id === orderId ? { ...o, status: 3 } : o
        );
        orderbook.value.bids = orderbook.value.bids.filter(o => o.id !== orderId);
        orderbook.value.asks = orderbook.value.asks.filter(o => o.id !== orderId);

        fetchProfile(); // balance refunded, assets unlocked
        toast.success('Order cancelled');
    } catch (e) {
        toast.error(e.response?.data?.message ?? 'Failed to cancel order');
    }
}

// Pusher subscription
let echoChannel = null;
let echoOrderbookChannel = null;

function applyOrderPlaced(event) {
    if (event.symbol !== selectedSymbol.value || event.status !== 1) return;

    const order = { id: event.id, symbol: event.symbol, side: event.side, price: event.price, amount: event.amount, status: event.status };

    if (event.side === 'buy') {
        orderbook.value.bids = [...orderbook.value.bids, order].sort((a, b) => b.price - a.price);
    } else {
        orderbook.value.asks = [...orderbook.value.asks, order].sort((a, b) => a.price - b.price);
    }
}

function applyOrderMatched(event) {
    const filledIds = new Set([event.buy_order_id, event.sell_order_id]);
    orderbook.value.bids = orderbook.value.bids.filter(o => !filledIds.has(o.id));
    orderbook.value.asks = orderbook.value.asks.filter(o => !filledIds.has(o.id));
}

function subscribeToEvents() {
    if (!window.Echo) return;

    echoChannel = window.Echo.private(`user.${userId.value}`)
        .listen('OrderMatched', (event) => {
            const filledIds = new Set([event.buy_order_id, event.sell_order_id]);
            myOrders.value = myOrders.value.map(o => {
                if (!filledIds.has(o.id)) return o;
                return {
                    ...o,
                    status: 2,
                    matched_price: event.matched_price,
                    commission: o.id === event.buy_order_id ? event.commission : null,
                };
            });
            orderbook.value.bids = orderbook.value.bids.filter(o => !filledIds.has(o.id));
            orderbook.value.asks = orderbook.value.asks.filter(o => !filledIds.has(o.id));
            const me = event.buyer?.id === userId.value ? event.buyer : event.seller;
            if (me) profile.value = { ...profile.value, balance: me.balance, assets: me.assets };
            const isBuyer = event.buyer?.id === userId.value;
            const commissionNote = isBuyer ? ` (commission: $${formatPrice(event.commission)})` : '';
            toast.success(`Order matched — ${event.amount} ${event.symbol} at $${formatPrice(event.matched_price)}${commissionNote}`);
        });

    echoOrderbookChannel = window.Echo.channel(`orders.${selectedSymbol.value}`)
        .listen('OrderPlaced', applyOrderPlaced)
        .listen('OrderMatched', applyOrderMatched);
}

function unsubscribeFromEvents() {
    if (echoChannel) {
        window.Echo.leave(`user.${userId.value}`);
        echoChannel = null;
    }
    if (echoOrderbookChannel) {
        window.Echo.leave(`orders.${selectedSymbol.value}`);
        echoOrderbookChannel = null;
    }
}

watch(selectedSymbol, (newSymbol, oldSymbol) => {
    if (echoOrderbookChannel) {
        window.Echo.leave(`orders.${oldSymbol}`);
        echoOrderbookChannel = null;
    }
    fetchOrderbook();
    if (window.Echo) {
        echoOrderbookChannel = window.Echo.channel(`orders.${newSymbol}`)
            .listen('OrderPlaced', applyOrderPlaced)
            .listen('OrderMatched', applyOrderMatched);
    }
});

onMounted(() => {
    fetchProfile();
    fetchMyOrders();
    fetchOrderbook();
    subscribeToEvents();
});

onUnmounted(() => {
    unsubscribeFromEvents();
});

function statusLabel(status) {
    const map = { 1: 'Open', 2: 'Filled', 3: 'Cancelled' };
    return map[status] ?? status;
}

function statusClass(status) {
    if (status === 1) return 'text-blue-600';
    if (status === 2) return 'text-green-600';
    return 'text-gray-400';
}

function formatPrice(val) {
    return parseFloat(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatAmount(val) {
    return parseFloat(val).toFixed(8).replace(/\.?0+$/, '');
}


const asksDisplay = computed(() => orderbook.value.asks.slice(0, 10).reverse());
const bidsDisplay = computed(() => orderbook.value.bids.slice(0, 10));
</script>

<template>
    <Head title="Trading" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Trading</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <div class="mb-4 flex gap-2">
                    <button
                        v-for="sym in ['BTC', 'ETH']"
                        :key="sym"
                        @click="selectedSymbol = sym"
                        :class="selectedSymbol === sym ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                        class="rounded-md px-4 py-2 text-sm font-medium shadow-sm transition-colors"
                    >
                        {{ sym }}/USD
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

                    <div class="space-y-4">
                        <div class="rounded-lg bg-white p-4 shadow-sm">
                            <h3 class="mb-3 text-sm font-semibold text-gray-700">Wallet</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">USD</span>
                                    <span class="font-medium">${{ usdBalance }}</span>
                                </div>
                                <template v-if="assets.length">
                                    <div v-for="asset in assets" :key="asset.symbol" class="flex justify-between">
                                        <span class="text-gray-500">{{ asset.symbol }}</span>
                                        <span class="font-medium">
                                            {{ formatAmount(asset.amount) }}
                                            <span v-if="parseFloat(asset.locked_amount) > 0" class="text-xs text-orange-400">
                                                ({{ formatAmount(asset.locked_amount) }} locked)
                                            </span>
                                        </span>
                                    </div>
                                </template>
                                <p v-else-if="!loadingProfile" class="text-gray-400">No assets</p>
                            </div>
                        </div>

                        <LimitOrderForm :symbol="selectedSymbol" @order-placed="onOrderPlaced" />
                    </div>

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
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">My Orders</h3>

                        <div v-if="myOrders.length" class="space-y-2">
                            <div
                                v-for="order in myOrders"
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
                                        @click="cancelOrder(order.id)"
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

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
