<script setup>
import { formatAmount } from '@/utils/trading';

defineProps({
    usdBalance: { type: String, required: true },
    assets: { type: Array, required: true },
    loadingProfile: { type: Boolean, default: false },
});
</script>

<template>
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
</template>
