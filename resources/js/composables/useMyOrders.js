import { ref, watch } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';

export function useMyOrders() {
    const myOrders = ref([]);
    const loadingOrders = ref(false);
    const filterSide = ref('');
    const filterStatus = ref('');

    async function fetchMyOrders() {
        loadingOrders.value = true;
        try {
            const params = {};
            if (filterSide.value)   params.side   = filterSide.value;
            if (filterStatus.value) params.status  = filterStatus.value;
            const { data } = await axios.get('/api/orders', { params });
            myOrders.value = data.data;
        } catch {
            toast.error('Failed to load orders');
        } finally {
            loadingOrders.value = false;
        }
    }

    watch([filterSide, filterStatus], fetchMyOrders);

    return { myOrders, loadingOrders, filterSide, filterStatus, fetchMyOrders };
}
