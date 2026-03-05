<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import LimitOrderForm from '@/Components/LimitOrderForm.vue';
import WalletPanel from '@/Components/WalletPanel.vue';
import OrderBookPanel from '@/Components/OrderBookPanel.vue';
import MyOrdersPanel from '@/Components/MyOrdersPanel.vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { useProfile } from '@/composables/useProfile';
import { useMyOrders } from '@/composables/useMyOrders';
import { useMarketData } from '@/composables/useMarketData';
import { formatPrice } from '@/utils/trading';

const page = usePage();
const userId = computed(() => page.props.auth.user.id);

const selectedSymbol = ref('BTC');

const { profile, loadingProfile, usdBalance, assets, fetchProfile } = useProfile();
const { myOrders, loadingOrders, filterSide, filterStatus, fetchMyOrders } = useMyOrders(selectedSymbol);
const {
    orderbook, trades, loadingOrderbook, asksDisplay, bidsDisplay,
    fetchOrderbook, fetchTrades,
    subscribeOrderbook, unsubscribeOrderbook,
} = useMarketData(selectedSymbol, userId);

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

function onOrderPlaced() {
    toast.success('Order placed successfully');
    fetchProfile();
    fetchMyOrders();
    fetchOrderbook();
}

// Private user channel — cross-cuts profile + myOrders
let echoChannel = null;

function subscribeToPrivateEvents() {
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
            const me = event.buyer?.id === userId.value ? event.buyer : event.seller;
            if (me) profile.value = { ...profile.value, balance: me.balance, assets: me.assets };
            const isBuyer = event.buyer?.id === userId.value;
            const commissionNote = isBuyer ? ` (commission: $${formatPrice(event.commission)})` : '';
            toast.success(`Order matched — ${event.amount} ${event.symbol} at $${formatPrice(event.matched_price)}${commissionNote}`);
        });
}

function unsubscribeFromPrivateEvents() {
    if (echoChannel) {
        window.Echo.leave(`user.${userId.value}`);
        echoChannel = null;
    }
}

onMounted(() => {
    fetchProfile();
    fetchMyOrders();
    fetchOrderbook();
    fetchTrades();
    subscribeOrderbook();
    subscribeToPrivateEvents();
});

onUnmounted(() => {
    unsubscribeOrderbook();
    unsubscribeFromPrivateEvents();
});
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
                        <WalletPanel
                            :usdBalance="usdBalance"
                            :assets="assets"
                            :loadingProfile="loadingProfile"
                        />
                        <LimitOrderForm :symbol="selectedSymbol" @order-placed="onOrderPlaced" />
                    </div>

                    <OrderBookPanel
                        :asksDisplay="asksDisplay"
                        :bidsDisplay="bidsDisplay"
                        :trades="trades"
                        :selectedSymbol="selectedSymbol"
                        :userId="userId"
                        :loadingOrderbook="loadingOrderbook"
                    />

                    <MyOrdersPanel
                        :orders="myOrders"
                        :loadingOrders="loadingOrders"
                        v-model:filterSide="filterSide"
                        v-model:filterStatus="filterStatus"
                        @cancel="cancelOrder"
                    />

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
