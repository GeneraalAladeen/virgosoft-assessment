import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';

export function useMarketData(selectedSymbol, userId) {
    const orderbook = ref({ bids: [], asks: [] });
    const trades = ref([]);
    const loadingOrderbook = ref(false);

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

    async function fetchTrades() {
        try {
            const { data } = await axios.get('/api/trades', {
                params: { symbol: selectedSymbol.value },
            });
            trades.value = data.data;
        } catch {
            // silent — trades are supplementary
        }
    }

    function applyOrderPlaced(event) {
        if (event.symbol !== selectedSymbol.value) return;

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

        const myRole = event.buyer?.id === userId.value ? 'buyer'
            : event.seller?.id === userId.value ? 'seller'
            : null;

        trades.value = [
            {
                id: Date.now(),
                symbol: event.symbol,
                price: event.matched_price,
                amount: event.amount,
                buyer: event.buyer ? { id: event.buyer.id, name: event.buyer.name } : null,
                seller: event.seller ? { id: event.seller.id, name: event.seller.name } : null,
                commission: event.commission ?? null,
                my_role: myRole,
                executed_at: new Date().toISOString(),
            },
            ...trades.value,
        ].slice(0, 20);
    }

    const asksDisplay = computed(() => orderbook.value.asks.slice(0, 10).reverse());
    const bidsDisplay = computed(() => orderbook.value.bids.slice(0, 10));

    let echoOrderbookChannel = null;

    function subscribeOrderbook() {
        if (!window.Echo) return;
        echoOrderbookChannel = window.Echo.channel(`orders.${selectedSymbol.value}`)
            .listen('OrderPlaced', applyOrderPlaced)
            .listen('TradeExecuted', applyOrderMatched);
    }

    function unsubscribeOrderbook() {
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
        fetchTrades();
        if (window.Echo) {
            echoOrderbookChannel = window.Echo.channel(`orders.${newSymbol}`)
                .listen('OrderPlaced', applyOrderPlaced)
                .listen('TradeExecuted', applyOrderMatched);
        }
    });

    return {
        orderbook, trades, loadingOrderbook, asksDisplay, bidsDisplay,
        fetchOrderbook, fetchTrades, applyOrderMatched,
        subscribeOrderbook, unsubscribeOrderbook,
    };
}
