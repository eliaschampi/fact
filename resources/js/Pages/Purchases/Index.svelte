<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { Button, Chip, EmptyState, PageHeader, SegmentedControl, Table } from '@lumi-ui/svelte';
    import { money } from '@/lib/money';
    import {
        PURCHASE_STATUS_OPTIONS,
        purchaseStatusColor,
        purchaseStatusLabel,
    } from '@/lib/purchases';

    interface PurchaseRow {
        code: string;
        purchase_number: string;
        status: string;
        ordered_at: string | null;
        item_count: number;
        total_cost: string;
    }

    interface PurchasePagination {
        data: PurchaseRow[];
        current_page: number;
        last_page: number;
        total: number;
    }

    interface Props {
        purchases?: PurchasePagination;
        filters?: { status?: string };
        has_branch?: boolean;
        can_manage?: boolean;
    }

    const {
        purchases = { data: [], current_page: 1, last_page: 1, total: 0 },
        filters = {},
        has_branch = false,
        can_manage = false,
    }: Props = $props();

    let status = $state(untrack(() => filters.status ?? 'all'));

    function apply(page = 1): void {
        router.get(
            '/purchases',
            {
                ...(status !== 'all' ? { status } : {}),
                ...(page > 1 ? { page } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }
</script>

<svelte:head>
    <title>Compras · NecoFact</title>
</svelte:head>

<div class="lumi-stack lumi-stack--lg lumi-min-width--0">
    <PageHeader
        title="Compras"
        subtitle="En tránsito no mueve stock. Recibir entra el inventario y actualiza el costo promedio."
        icon="shoppingCart"
        size="xl"
    >
        {#snippet actions()}
            {#if can_manage && has_branch}
                <Button icon="plus" onclick={() => router.visit('/purchases/new')}>
                    Nueva compra
                </Button>
            {/if}
        {/snippet}
    </PageHeader>

    {#if !has_branch}
        <EmptyState
            icon="mapPin"
            title="Selecciona una sede"
            description="Las compras se registran en la sede de trabajo de tu sesión."
        >
            {#snippet actions()}
                <Button icon="building2" onclick={() => router.visit('/branches')}
                    >Ir a sedes</Button
                >
            {/snippet}
        </EmptyState>
    {:else if purchases.total === 0 && status === 'all'}
        <EmptyState
            icon="shoppingCart"
            title="Sin compras"
            description={can_manage
                ? 'Registra la primera compra. Queda en tránsito hasta que la recibas.'
                : 'Todavía no hay compras en esta sede.'}
        >
            {#snippet actions()}
                {#if can_manage}
                    <Button icon="plus" onclick={() => router.visit('/purchases/new')}>
                        Registrar compra
                    </Button>
                {/if}
            {/snippet}
        </EmptyState>
    {:else}
        <SegmentedControl
            value={status}
            options={PURCHASE_STATUS_OPTIONS}
            onchange={(value) => {
                status = String(value);
                apply();
            }}
            aria-label="Filtrar por estado"
        />

        <Table
            data={purchases.data}
            rowKey={(row) => row.code}
            pagination={purchases.last_page > 1}
            paginationMode="server"
            currentPage={purchases.current_page}
            totalItems={purchases.total}
            itemsPerPage={20}
            onpage-change={apply}
            compact
            aria-label="Compras de la sede"
            noDataText="Aún no hay compras."
            noResultsText="Ninguna compra coincide con el filtro."
        >
            {#snippet thead()}
                <th scope="col">Número</th>
                <th scope="col">Fecha</th>
                <th scope="col">Ítems</th>
                <th scope="col">Total costo</th>
                <th scope="col">Estado</th>
                <th scope="col">Acciones</th>
            {/snippet}
            {#snippet row({ row }: { row: PurchaseRow })}
                <td><strong>{row.purchase_number}</strong></td>
                <td>{row.ordered_at ?? '—'}</td>
                <td>{row.item_count}</td>
                <td><strong>{money(row.total_cost)}</strong></td>
                <td>
                    <Chip size="sm" color={purchaseStatusColor(row.status)}>
                        {purchaseStatusLabel(row.status)}
                    </Chip>
                </td>
                <td>
                    <Button
                        type="button"
                        variant="flat"
                        size="sm"
                        icon="eye"
                        aria-label={`Ver ${row.purchase_number}`}
                        onclick={() => router.visit(`/purchases/${row.code}`)}
                    />
                </td>
            {/snippet}
        </Table>
    {/if}
</div>
