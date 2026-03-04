<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import LimitOrderForm from '@/Components/LimitOrderForm.vue';
import axios from 'axios';

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
    } finally {
        loadingProfile.value = false;
    }
}

async function fetchMyOrders() {
    loadingOrders.value = true;
    try {
        const { data } = await axios.get('/api/orders');
        myOrders.value = data.data;
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
    } finally {
        loadingOrderbook.value = false;
    }
}

function onOrderPlaced() {
    fetchProfile();
    fetchMyOrders();
    fetchOrderbook();
}

async function cancelOrder(orderId) {
    try {
        await axios.post(`/api/orders/${orderId}/cancel`);
        fetchProfile();
        fetchMyOrders();
        fetchOrderbook();
    } catch (e) {
        // order may no longer be open
    }
}

// Pusher subscription
let echoChannel = null;

function subscribeToEvents() {
    if (!window.Echo) return;

    echoChannel = window.Echo.private(`user.${userId.value}`)
        .listen('OrderMatched', () => {
            fetchProfile();
            fetchMyOrders();
            fetchOrderbook();
        });
}

function unsubscribeFromEvents() {
    if (echoChannel) {
        window.Echo.leave(`user.${userId.value}`);
        echoChannel = null;
    }
}

watch(selectedSymbol, fetchOrderbook);

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
</script>

<template>
    <Head title="Trading" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Trading</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <!-- Symbol tabs -->
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

                    <!-- Left: Wallet + Order form -->
                    <div class="space-y-4">

                        <!-- Wallet -->
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

                    <!-- Center: Orderbook -->
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">Orderbook — {{ selectedSymbol }}/USD</h3>

                        <!-- Asks (sell orders) -->
                        <div class="mb-2">
                            <div class="mb-1 grid grid-cols-2 text-xs text-gray-400">
                                <span>Price (USD)</span>
                                <span class="text-right">Amount ({{ selectedSymbol }})</span>
                            </div>
                            <div v-if="orderbook.asks.length" class="space-y-0.5">
                                <div
                                    v-for="order in orderbook.asks.slice(0, 10)"
                                    :key="order.id"
                                    class="grid grid-cols-2 text-xs"
                                >
                                    <span class="text-red-500">{{ formatPrice(order.price) }}</span>
                                    <span class="text-right text-gray-700">{{ formatAmount(order.amount) }}</span>
                                </div>
                            </div>
                            <p v-else class="text-xs text-gray-400">No sell orders</p>
                        </div>

                        <div class="my-2 border-t border-gray-100"></div>

                        <!-- Bids (buy orders) -->
                        <div>
                            <div v-if="orderbook.bids.length" class="space-y-0.5">
                                <div
                                    v-for="order in orderbook.bids.slice(0, 10)"
                                    :key="order.id"
                                    class="grid grid-cols-2 text-xs"
                                >
                                    <span class="text-green-500">{{ formatPrice(order.price) }}</span>
                                    <span class="text-right text-gray-700">{{ formatAmount(order.amount) }}</span>
                                </div>
                            </div>
                            <p v-else class="text-xs text-gray-400">No buy orders</p>
                        </div>
                    </div>

                    <!-- Right: My orders -->
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
