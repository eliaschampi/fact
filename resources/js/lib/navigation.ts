import type { IconName } from '@lumi-ui/svelte';

export interface NavigationItem {
    label: string;
    href: string;
    icon: IconName;
    permission?: string;
    requiresBranch?: boolean;
    exact?: boolean;
    activePrefixes?: readonly string[];
}

export const APP_NAVIGATION: readonly NavigationItem[] = [
    {
        label: 'Inicio',
        href: '/',
        icon: 'house',
        permission: 'dashboard.view',
    },
    {
        label: 'Sedes',
        href: '/branches',
        icon: 'building2',
    },
];
