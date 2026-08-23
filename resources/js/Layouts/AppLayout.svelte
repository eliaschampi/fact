<script lang="ts">
    import { onMount, type Snippet } from 'svelte';
    import { ColorSchemeScript } from '@lumi-ui/svelte/color-scheme';
    import { colorScheme, colorSchemeOptions } from '@/lib/color-scheme.svelte';

    interface Props {
        children?: Snippet;
    }

    const { children }: Props = $props();

    onMount(colorScheme.start);
</script>

<ColorSchemeScript {...colorSchemeOptions} />
{@render children?.()}
