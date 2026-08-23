import { vitePreprocess } from '@sveltejs/vite-plugin-svelte';

/** @type {import('@sveltejs/vite-plugin-svelte').SvelteConfig} */
const config = {
    preprocess: vitePreprocess(),
    // Do not force runes: true globally — @inertiajs/svelte still uses legacy mode.
    // App pages use $props/$state and opt into runes automatically.
};

export default config;
