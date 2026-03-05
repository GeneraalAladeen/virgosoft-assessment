export function formatPrice(val) {
    return parseFloat(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export function formatAmount(val) {
    return parseFloat(val).toFixed(8).replace(/\.?0+$/, '');
}

export function statusLabel(status) {
    const map = { 1: 'Open', 2: 'Filled', 3: 'Cancelled' };
    return map[status] ?? status;
}

export function statusClass(status) {
    if (status === 1) return 'text-blue-600';
    if (status === 2) return 'text-green-600';
    return 'text-gray-400';
}
