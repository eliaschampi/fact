/// <reference types="svelte" />
/// <reference types="vite/client" />

import type { AuthenticatedContext } from './types/auth';

declare module '@inertiajs/core' {
    interface InertiaConfig {
        flashDataType: {
            success?: string;
        };
        sharedPageProps: {
            auth: AuthenticatedContext | null;
        };
    }
}

declare module '@lumi-ui/svelte/styles';
declare module '*.css';
