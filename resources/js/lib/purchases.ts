import type { LumiColor } from '@lumi-ui/svelte';

export const PURCHASE_STATUS_OPTIONS = [
    { value: 'all', label: 'Todas' },
    { value: 'in_transit', label: 'En tránsito' },
    { value: 'received', label: 'Recibidas' },
    { value: 'cancelled', label: 'Anuladas' },
];

export function purchaseStatusLabel(status: string): string {
    if (status === 'in_transit') return 'En tránsito';
    if (status === 'received') return 'Recibida';
    if (status === 'cancelled') return 'Anulada';

    return status;
}

export function purchaseStatusColor(status: string): LumiColor {
    if (status === 'received') return 'success';
    if (status === 'cancelled') return 'danger';

    return 'warning';
}
