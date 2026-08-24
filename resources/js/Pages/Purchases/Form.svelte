<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import {
        Alert,
        Button,
        Card,
        Input,
        PageHeader,
        Table,
        Textarea,
        Title,
    } from '@lumi-ui/svelte';
    import { money } from '@/lib/money';

    interface CatalogVariant {
        code: string;
        sku: string;
        name: string | null;
        cost_price: string;
        product: { code: string; name: string };
    }

    interface Line {
        variant_code: string;
        sku: string;
        product_name: string;
        presentation: string | null;
        quantity: string;
        unit_cost: string;
    }

    interface Props {
        catalog?: CatalogVariant[];
        filters?: { q?: string };
    }

    const { catalog = [], filters = {} }: Props = $props();

    let query = $state(untrack(() => filters.q ?? ''));
    let note = $state('');
    let lines = $state<Line[]>([]);
    let processing = $state(false);
    let errors = $state<Record<string, string>>({});

    function search(): void {
        router.get('/purchases/new', query.trim() !== '' ? { q: query.trim() } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function addVariant(variant: CatalogVariant): void {
        if (lines.some((line) => line.variant_code === variant.code)) {
            return;
        }

        lines = [
            ...lines,
            {
                variant_code: variant.code,
                sku: variant.sku,
                product_name: variant.product.name,
                presentation: variant.name,
                quantity: '1',
                unit_cost: variant.cost_price,
            },
        ];
        errors = {};
    }

    function removeLine(variantCode: string): void {
        lines = lines.filter((line) => line.variant_code !== variantCode);
    }

    function lineError(index: number, field: string): string {
        return errors[`items.${index}.${field}`] ?? '';
    }

    function submit(event: SubmitEvent): void {
        event.preventDefault();

        router.post(
            '/purchases',
            {
                note: note.trim() === '' ? null : note.trim(),
                items: lines.map((line) => ({
                    variant_code: line.variant_code,
                    quantity: Number(line.quantity),
                    unit_cost: line.unit_cost,
                })),
            },
            {
                onStart: () => {
                    processing = true;
                    errors = {};
                },
                onError: (formErrors: Record<string, string>) => {
                    errors = formErrors;
                },
                onFinish: () => {
                    processing = false;
                },
            },
        );
    }
</script>

<svelte:head>
    <title>Nueva compra · NecoFact</title>
</svelte:head>

<div class="lumi-stack lumi-stack--lg lumi-min-width--0">
    <PageHeader
        title="Nueva compra"
        subtitle="Queda en tránsito. El stock y el costo promedio se actualizan al recibir."
        icon="shoppingCart"
        size="xl"
    />

    {#if errors.message || errors.items || errors.branch}
        <Alert color="danger">{errors.message ?? errors.items ?? errors.branch}</Alert>
    {/if}

    <Card spaced>
        {#snippet header()}
            <Title
                size="md"
                title="Catálogo"
                subtitle="Busca y agrega variantes activas."
                icon="package"
            />
        {/snippet}
        <div class="lumi-stack lumi-stack--md">
            <Input
                bind:value={query}
                icon="search"
                actionIcon="search"
                onaction-click={() => search()}
                placeholder="Buscar por nombre o SKU…"
                aria-label="Buscar productos para comprar"
            />

            {#if catalog.length === 0}
                <p class="lumi-text--sm lumi-text--muted lumi-margin--none">
                    No hay variantes para mostrar. Prueba otra búsqueda.
                </p>
            {:else}
                <Table
                    data={catalog}
                    rowKey={(row) => row.code}
                    compact
                    aria-label="Variantes para agregar"
                >
                    {#snippet thead()}
                        <th scope="col">Producto</th>
                        <th scope="col">SKU</th>
                        <th scope="col">Costo actual</th>
                        <th scope="col">Acciones</th>
                    {/snippet}
                    {#snippet row({ row }: { row: CatalogVariant })}
                        <td>
                            <strong>{row.product.name}</strong>
                            {#if row.name}
                                <span class="lumi-text--sm lumi-text--muted"> · {row.name}</span>
                            {/if}
                        </td>
                        <td>{row.sku}</td>
                        <td>{money(row.cost_price)}</td>
                        <td>
                            <Button
                                type="button"
                                variant="flat"
                                size="sm"
                                icon="plus"
                                disabled={lines.some((line) => line.variant_code === row.code)}
                                onclick={() => addVariant(row)}
                            >
                                Agregar
                            </Button>
                        </td>
                    {/snippet}
                </Table>
            {/if}
        </div>
    </Card>

    <form class="lumi-stack lumi-stack--lg" onsubmit={submit}>
        <Card spaced>
            {#snippet header()}
                <Title
                    size="md"
                    title="Líneas"
                    subtitle="Cantidad entera y costo unitario sin IGV de compra."
                    icon="boxes"
                />
            {/snippet}
            {#if lines.length === 0}
                <p class="lumi-text--sm lumi-text--muted lumi-margin--none">
                    Agrega al menos una variante desde el catálogo.
                </p>
            {:else}
                <div class="lumi-stack lumi-stack--md">
                    {#each lines as line, index (line.variant_code)}
                        <div class="lumi-grid lumi-grid--columns-2-lg lumi-grid--gap-md">
                            <div>
                                <p class="lumi-font--medium lumi-margin--none">
                                    {line.product_name}
                                </p>
                                <p class="lumi-text--sm lumi-text--muted lumi-margin--none">
                                    {line.sku}{line.presentation ? ` · ${line.presentation}` : ''}
                                </p>
                            </div>
                            <div class="lumi-flex lumi-flex--gap-sm lumi-align-items--end">
                                <Input
                                    bind:value={line.quantity}
                                    type="number"
                                    min="1"
                                    step="1"
                                    label="Cantidad"
                                    required
                                    danger={Boolean(lineError(index, 'quantity'))}
                                    dangerText={lineError(index, 'quantity')}
                                />
                                <Input
                                    bind:value={line.unit_cost}
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    label="Costo unitario (S/)"
                                    required
                                    danger={Boolean(lineError(index, 'unit_cost'))}
                                    dangerText={lineError(index, 'unit_cost')}
                                />
                                <Button
                                    type="button"
                                    variant="flat"
                                    color="danger"
                                    size="sm"
                                    icon="x"
                                    aria-label={`Quitar ${line.sku}`}
                                    onclick={() => removeLine(line.variant_code)}
                                />
                            </div>
                        </div>
                    {/each}
                </div>
            {/if}
        </Card>

        <Card spaced>
            {#snippet header()}
                <Title size="md" title="Nota" icon="tag" />
            {/snippet}
            <Textarea
                bind:value={note}
                label="Nota interna (opcional)"
                rows={2}
                maxlength={500}
                showCount
                error={errors.note}
            />
        </Card>

        <div class="lumi-flex lumi-flex--gap-md lumi-justify--end">
            <Button
                type="button"
                variant="border"
                icon="arrowLeft"
                disabled={processing}
                onclick={() => router.visit('/purchases')}
            >
                Cancelar
            </Button>
            <Button type="submit" icon="check" loading={processing} disabled={lines.length === 0}>
                Registrar en tránsito
            </Button>
        </div>
    </form>
</div>
