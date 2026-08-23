import type { LumiColor, SelectOption } from '@lumi-ui/svelte';

export const AFFECTATION_OPTIONS: SelectOption[] = [
    { value: '10', label: 'Gravado 18%' },
    { value: '20', label: 'Exonerado' },
    { value: '30', label: 'Inafecto' },
];

export const UNIT_OPTIONS: SelectOption[] = [
    { value: 'NIU', label: 'Unidad (NIU)' },
    { value: 'ZZ', label: 'Servicio / otros (ZZ)' },
];

export function affectationLabel(code: string): string {
    return AFFECTATION_OPTIONS.find((option) => option.value === code)?.label ?? code;
}

export function affectationColor(code: string): LumiColor {
    if (code === '20') return 'info';
    if (code === '30') return 'warning';

    return 'primary';
}

export function unitLabel(code: string): string {
    return UNIT_OPTIONS.find((option) => option.value === code)?.label ?? code;
}
