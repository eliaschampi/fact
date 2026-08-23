import { createInertiaApp, type ResolvedComponent } from '@inertiajs/svelte';
import { mount } from 'svelte';
import '@lumi-ui/svelte/styles';
import './styles/lumi-theme.css';
import AppLayout from './Layouts/AppLayout.svelte';
import DashboardLayout from './Layouts/DashboardLayout.svelte';
import { installInertiaLinkDelegation } from '@/lib/inertia-links';

interface PageModule {
    default: ResolvedComponent['default'];
    layout?: false;
}

const pages = import.meta.glob<PageModule>('./Pages/**/*.svelte');

let disposeLinkDelegation: (() => void) | undefined;

createInertiaApp({
    resolve: async (name): Promise<ResolvedComponent> => {
        const loadPage = pages[`./Pages/${name}.svelte`];

        if (!loadPage) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        const page = await loadPage();

        return {
            default: page.default,
            layout: page.layout === false ? AppLayout : [AppLayout, DashboardLayout],
        };
    },
    setup({ el, App, props }) {
        mount(App, { target: el!, props });
        disposeLinkDelegation?.();
        disposeLinkDelegation = installInertiaLinkDelegation();
    },
    progress: {
        color: 'var(--lumi-color-primary)',
    },
});
