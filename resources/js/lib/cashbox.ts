import type { LumiColor } from '@lumi-ui/svelte';

export const CASHBOX_VIEW_OPTIONS = [
    { value: 'day', label: 'Día', icon: 'wallet' },
    { value: 'operations', label: 'Movimientos', icon: 'list' },
    { value: 'month', label: 'Mes', icon: 'calendar' },
];

export function cashboxMovementLabel(type: string): string {
    if (type === 'manual_income') return 'Ingreso manual';
    if (type === 'expense') return 'Gasto';
    if (type === 'outflow_return') return 'Vuelto';
    if (type === 'transfer') return 'Transferencia';

    return type;
}

export function cashboxStatusLabel(status: string): string {
    return status === 'reversed' ? 'Anulado' : 'Vigente';
}

export function cashboxStatusColor(status: string): LumiColor {
    return status === 'reversed' ? 'secondary' : 'success';
}

export function cashboxDirectionColor(direction: string): LumiColor {
    return direction === 'in' ? 'success' : 'danger';
}

export function cashboxDirectionLabel(direction: string): string {
    return direction === 'in' ? 'Entrada' : 'Salida';
}

export function shortBusinessDate(date: string | null): string {
    if (!date) return '—';

    const [year, month, day] = date.split('-');
    return year && month && day ? `${day}/${month}/${year}` : date;
}

export function shortLimaDateTime(value: string | null): string {
    if (!value) return '—';

    const [date, time] = value.split(' ');
    return `${shortBusinessDate(date ?? null)}${time ? ` · ${time.slice(0, 5)}` : ''}`;
}
