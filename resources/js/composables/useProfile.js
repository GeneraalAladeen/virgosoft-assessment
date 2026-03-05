import { ref, computed } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';

export function useProfile() {
    const profile = ref(null);
    const loadingProfile = ref(false);

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

    return { profile, loadingProfile, usdBalance, assets, fetchProfile };
}
