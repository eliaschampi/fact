<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import {
        Button,
        Chip,
        EmptyState,
        Input,
        PageHeader,
        SegmentedControl,
        Table,
    } from '@lumi-ui/svelte';
    import { affectationColor, affectationLabel } from '@/lib/catalog';
    import { money } from '@/lib/money';

    interface VariantRow {
        code: string;
        sku: string;
        name: string | null;
        unit_code: string;
        tax_affectation: string;
        price: string;
        product: { code: string; name: string; is_active: boolean };
    }

    interface VariantPagination {
        data: VariantRow[];
        current_page: number;
        last_page: number;
        total: number;
    }

    interface Props {
        variants?: VariantPagination;
        filters?: { q?: string; status?: string };
        can_manage?: boolean;
    }

    const {
        variants = { data: [], current_page: 1, last_page: 1, total: 0 },
        filters = {},
        can_manage = false,
    }: Props = $props();

    let query = $state(untrack(() => filters.q ?? ''));
    let status = $state(untrack(() => filters.status ?? 'all'));

    const statusOptions = [
        { value: 'all', label: 'Todos' },
        { value: 'active', label: 'Activos' },
        { value: 'inactive', label: 'Inactivos' },
    ];

    const isUnfiltered = $derived(query.trim() === '' && status === 'all');

    function apply(page = 1): void {
        router.get(
            '/products',
            {
                ...(query.trim() !== '' ? { q: query.trim() } : {}),
                ...(status !== 'all' ? { status } : {}),
                ...(page > 1 ? { page } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function editProduct(productCode: string): void {
        router.visit(`/products/${productCode}/edit`);
    }
</script>

<svelte:head>
    <title>Productos · NecoFact</title>
</svelte:head>

<div class="lumi-stack lumi-stack--lg lumi-min-width--0">
    <PageHeader
        title="Productos"
        subtitle="Catálogo vendible con precio y afectación IGV por variante."
        icon="package"
        size="xl"
    >
        {#snippet actions()}
            {#if can_manage}
                <Button icon="plus" onclick={() => router.visit('/products/new')}>
                    Nuevo producto
                </Button>
            {/if}
        {/snippet}
    </PageHeader>

    {#if variants.total === 0 && isUnfiltered}
        <EmptyState
            icon="package"
            title="Sin productos"
            description={can_manage
                ? 'Registra el primer producto con su precio y afectación IGV.'
                : 'Todavía no hay productos registrados.'}
        >
            {#snippet actions()}
                {#if can_manage}
                    <Button icon="plus" onclick={() => router.visit('/products/new')}>
                        Registrar producto
                    </Button>
                {/if}
            {/snippet}
        </EmptyState>
    {:else}
        <div class="lumi-flex lumi-flex--gap-md lumi-align-items--center lumi-flex--wrap">
            <div class="lumi-flex-item--grow">
                <Input
                    bind:value={query}
                    icon="search"
                    actionIcon="search"
                    onaction-click={() => apply()}
                    placeholder="Buscar por nombre o SKU…"
                    aria-label="Buscar productos"
                />
            </div>
            <SegmentedControl
                value={status}
                options={statusOptions}
                onchange={(value) => {
                    status = String(value);
                    apply();
                }}
                aria-label="Filtrar por estado"
            />
        </div>

        <Table
            data={variants.data}
            rowKey={(row) => row.code}
            pagination={variants.last_page > 1}
            paginationMode="server"
            currentPage={variants.current_page}
            totalItems={variants.total}
            itemsPerPage={20}
            onpage-change={apply}
            compact
            aria-label="Variantes de producto"
            noDataText="Aún no hay variantes registradas."
            noResultsText="Ningún producto coincide con la búsqueda."
        >
            {#snippet thead()}
                <th scope="col">Producto</th>
                <th scope="col">SKU</th>
                <th scope="col">Presentación</th>
                <th scope="col">Unidad</th>
                <th scope="col">Afectación</th>
                <th scope="col">Precio</th>
                <th scope="col">Estado</th>
                {#if can_manage}<th scope="col">Acciones</th>{/if}
            {/snippet}
            {#snippet row({ row }: { row: VariantRow })}
                <td><strong>{row.product.name}</strong></td>
                <td><span class="lumi-text--sm lumi-text--muted">{row.sku}</span></td>
                <td>{row.name ?? '—'}</td>
                <td>{row.unit_code}</td>
                <td>
                    <Chip size="sm" color={affectationColor(row.tax_affectation)}>
                        {affectationLabel(row.tax_affectation)}
                    </Chip>
                </td>
                <td><strong>{money(row.price)}</strong></td>
                <td>
                    <Chip size="sm" color={row.product.is_active ? 'success' : 'secondary'}>
                        {row.product.is_active ? 'Activo' : 'Inactivo'}
                    </Chip>
                </td>
                {#if can_manage}
                    <td>
                        <Button
                            type="button"
                            variant="flat"
                            size="sm"
                            icon="edit"
                            aria-label={`Editar ${row.product.name}`}
                            onclick={() => editProduct(row.product.code)}
                        />
                    </td>
                {/if}
            {/snippet}
        </Table>
    {/if}
</div>
