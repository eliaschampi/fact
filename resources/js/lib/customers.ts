import type { LumiColor, SelectOption } from '@lumi-ui/svelte';

export const DOC_TYPE_OPTIONS: SelectOption[] = [
    { value: '1', label: 'DNI' },
    { value: '6', label: 'RUC' },
    { value: '0', label: 'Otros' },
];

export function docTypeLabel(code: string): string {
    return DOC_TYPE_OPTIONS.find((option) => option.value === code)?.label ?? code;
}

export function docTypeColor(code: string): LumiColor {
    if (code === '1') return 'info';
    if (code === '6') return 'primary';

    return 'secondary';
}
