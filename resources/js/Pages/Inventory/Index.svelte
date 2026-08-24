<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { Chip, EmptyState, Input, PageHeader, Table } from '@lumi-ui/svelte';
    import { money } from '@/lib/money';

    interface BalanceRow {
        code: string;
        sku: string;
        name: string | null;
        unit_code: string;
        cost_price: string;
        price: string;
        on_hand: number;
        product: { code: string; name: string; is_active: boolean };
    }

    interface BalancePagination {
        data: BalanceRow[];
        current_page: number;
        last_page: number;
        total: number;
    }

    interface Props {
        balances?: BalancePagination;
        filters?: { q?: string };
        has_branch?: boolean;
    }

    const {
        balances = { data: [], current_page: 1, last_page: 1, total: 0 },
        filters = {},
        has_branch = false,
    }: Props = $props();

    let query = $state(untrack(() => filters.q ?? ''));

    function apply(page = 1): void {
        router.get(
            '/inventory',
            {
                ...(query.trim() !== '' ? { q: query.trim() } : {}),
                ...(page > 1 ? { page } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }
</script>

<svelte:head>
    <title>Inventario · NecoFact</title>
</svelte:head>

<div class="lumi-stack lumi-stack--lg lumi-min-width--0">
    <PageHeader
        title="Inventario"
        subtitle="Saldos por sede. El costo promedio lo actualiza la recepción de compras."
        icon="boxes"
        size="xl"
    />

    {#if !has_branch}
        <EmptyState
            icon="mapPin"
            title="Selecciona una sede"
            description="El inventario se lee en la sede de trabajo de tu sesión."
        />
    {:else}
        <div class="lumi-flex lumi-flex--gap-md lumi-align-items--center lumi-flex--wrap">
            <div class="lumi-flex-item--grow">
                <Input
                    bind:value={query}
                    icon="search"
                    actionIcon="search"
                    onaction-click={() => apply()}
                    placeholder="Buscar por nombre o SKU…"
                    aria-label="Buscar inventario"
                />
            </div>
        </div>

        <Table
            data={balances.data}
            rowKey={(row) => row.code}
            pagination={balances.last_page > 1}
            paginationMode="server"
            currentPage={balances.current_page}
            totalItems={balances.total}
            itemsPerPage={20}
            onpage-change={apply}
            compact
            aria-label="Saldos de inventario"
            noDataText="Aún no hay variantes en el catálogo."
            noResultsText="Ningún producto coincide con la búsqueda."
        >
            {#snippet thead()}
                <th scope="col">Producto</th>
                <th scope="col">SKU</th>
                <th scope="col">Unidad</th>
                <th scope="col">Stock</th>
                <th scope="col">Costo</th>
                <th scope="col">Precio</th>
                <th scope="col">Estado</th>
            {/snippet}
            {#snippet row({ row }: { row: BalanceRow })}
                <td>
                    <strong>{row.product.name}</strong>
                    {#if row.name}
                        <span class="lumi-text--sm lumi-text--muted"> · {row.name}</span>
                    {/if}
                </td>
                <td>{row.sku}</td>
                <td>{row.unit_code}</td>
                <td><strong>{row.on_hand}</strong></td>
                <td>{money(row.cost_price)}</td>
                <td>{money(row.price)}</td>
                <td>
                    <Chip size="sm" color={row.product.is_active ? 'success' : 'secondary'}>
                        {row.product.is_active ? 'Activo' : 'Inactivo'}
                    </Chip>
                </td>
            {/snippet}
        </Table>
    {/if}
</div>
