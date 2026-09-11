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
        label: 'Productos',
        href: '/products',
        icon: 'package',
        permission: 'products.view',
    },
    {
        label: 'Clientes',
        href: '/customers',
        icon: 'users',
        permission: 'customers.view',
    },
    {
        label: 'Compras',
        href: '/purchases',
        icon: 'shoppingCart',
        permission: 'purchases.view',
        requiresBranch: true,
    },
    {
        label: 'Inventario',
        href: '/inventory',
        icon: 'boxes',
        permission: 'inventory.view',
        requiresBranch: true,
    },
    {
        label: 'Caja',
        href: '/cashbox',
        icon: 'wallet',
        permission: 'cashbox.view',
    },
    {
        label: 'Sedes',
        href: '/branches',
        icon: 'building2',
    },
];
