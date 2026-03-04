<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    symbol: { type: String, required: true },
});

const emit = defineEmits(['order-placed']);

const side = ref('buy');
const price = ref('');
const amount = ref('');
const errors = ref({});
const submitting = ref(false);

const total = computed(() => {
    const p = parseFloat(price.value);
    const a = parseFloat(amount.value);
    if (!p || !a) return '0.00';
    return (p * a).toFixed(2);
});

async function submit() {
    errors.value = {};
    submitting.value = true;

    try {
        await axios.post('/api/orders', {
            symbol: props.symbol,
            side: side.value,
            price: price.value,
            amount: amount.value,
        });

        price.value = '';
        amount.value = '';
        emit('order-placed');
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
            const message = e.response.data.message;
            if (message && !Object.keys(errors.value).length) {
                errors.value.general = [message];
            }
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="rounded-lg bg-white p-4 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold text-gray-700">Place Limit Order — {{ symbol }}</h3>

        <div class="mb-4 flex rounded-md overflow-hidden border border-gray-200">
            <button
                @click="side = 'buy'"
                :class="side === 'buy' ? 'bg-green-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                class="flex-1 py-2 text-sm font-medium transition-colors"
            >
                Buy
            </button>
            <button
                @click="side = 'sell'"
                :class="side === 'sell' ? 'bg-red-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                class="flex-1 py-2 text-sm font-medium transition-colors"
            >
                Sell
            </button>
        </div>

        <div class="space-y-3">
            <div>
                <label class="mb-1 block text-xs text-gray-500">Price (USD)</label>
                <input
                    v-model="price"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none"
                />
                <p v-if="errors.price" class="mt-1 text-xs text-red-500">{{ errors.price[0] }}</p>
            </div>

            <div>
                <label class="mb-1 block text-xs text-gray-500">Amount ({{ symbol }})</label>
                <input
                    v-model="amount"
                    type="number"
                    step="0.00000001"
                    min="0"
                    placeholder="0.00000000"
                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none"
                />
                <p v-if="errors.amount" class="mt-1 text-xs text-red-500">{{ errors.amount[0] }}</p>
            </div>

            <div class="rounded bg-gray-50 px-3 py-2 text-xs text-gray-500">
                Total: <span class="font-medium text-gray-700">{{ total }} USD</span>
            </div>

            <p v-if="errors.balance || errors.general" class="text-xs text-red-500">
                {{ (errors.balance ?? errors.general)?.[0] }}
            </p>

            <button
                @click="submit"
                :disabled="submitting"
                :class="side === 'buy' ? 'bg-green-500 hover:bg-green-600' : 'bg-red-500 hover:bg-red-600'"
                class="w-full rounded py-2 text-sm font-semibold text-white transition-colors disabled:opacity-50"
            >
                {{ submitting ? 'Placing...' : (side === 'buy' ? 'Buy ' + symbol : 'Sell ' + symbol) }}
            </button>
        </div>
    </div>
</template>
