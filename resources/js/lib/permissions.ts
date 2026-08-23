import { page } from '@inertiajs/svelte';

export function can(permission: string): boolean {
    return page.props.auth?.permissions.includes(permission) ?? false;
}
