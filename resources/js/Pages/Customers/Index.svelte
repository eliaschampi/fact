<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { Button, Chip, EmptyState, Input, PageHeader, Table } from '@lumi-ui/svelte';
    import { docTypeColor, docTypeLabel } from '@/lib/customers';

    interface CustomerRow {
        code: string;
        doc_type: string;
        doc_number: string;
        name: string;
        is_final_consumer: boolean;
    }

    interface CustomerPagination {
        data: CustomerRow[];
        current_page: number;
        last_page: number;
        total: number;
    }

    interface Props {
        customers?: CustomerPagination;
        filters?: { q?: string };
        can_manage?: boolean;
    }

    const {
        customers = { data: [], current_page: 1, last_page: 1, total: 0 },
        filters = {},
        can_manage = false,
    }: Props = $props();

    let query = $state(untrack(() => filters.q ?? ''));

    function apply(page = 1): void {
        router.get(
            '/customers',
            {
                ...(query.trim() !== '' ? { q: query.trim() } : {}),
                ...(page > 1 ? { page } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function editCustomer(customerCode: string): void {
        router.visit(`/customers/${customerCode}/edit`);
    }
</script>

<svelte:head>
    <title>Clientes · NecoFact</title>
</svelte:head>

<div class="lumi-stack lumi-stack--lg lumi-min-width--0">
    <PageHeader
        title="Clientes"
        subtitle="Identidad tributaria esencial: DNI, RUC y consumidor final."
        icon="users"
        size="xl"
    >
        {#snippet actions()}
            {#if can_manage}
                <Button icon="userPlus" onclick={() => router.visit('/customers/new')}>
                    Nuevo cliente
                </Button>
            {/if}
        {/snippet}
    </PageHeader>

    {#if customers.total === 0 && query.trim() === ''}
        <EmptyState
            icon="users"
            title="Sin clientes"
            description={can_manage
                ? 'Registra el primer DNI o RUC. El consumidor final lo crea el sistema.'
                : 'Todavía no hay clientes registrados.'}
        >
            {#snippet actions()}
                {#if can_manage}
                    <Button icon="userPlus" onclick={() => router.visit('/customers/new')}>
                        Registrar cliente
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
                    placeholder="Buscar por documento o nombre…"
                    aria-label="Buscar clientes"
                />
            </div>
        </div>

        <Table
            data={customers.data}
            rowKey={(row) => row.code}
            pagination={customers.last_page > 1}
            paginationMode="server"
            currentPage={customers.current_page}
            totalItems={customers.total}
            itemsPerPage={20}
            onpage-change={apply}
            compact
            aria-label="Clientes registrados"
            noDataText="Aún no hay clientes registrados."
            noResultsText="Ningún cliente coincide con la búsqueda."
        >
            {#snippet thead()}
                <th scope="col">Documento</th>
                <th scope="col">Número</th>
                <th scope="col">Nombre</th>
                {#if can_manage}<th scope="col">Acciones</th>{/if}
            {/snippet}
            {#snippet row({ row }: { row: CustomerRow })}
                <td>
                    <Chip size="sm" color={docTypeColor(row.doc_type)}>
                        {docTypeLabel(row.doc_type)}
                    </Chip>
                </td>
                <td><strong>{row.doc_number}</strong></td>
                <td>{row.name}</td>
                {#if can_manage}
                    <td>
                        {#if row.is_final_consumer}
                            <span class="lumi-text--sm lumi-text--muted">Fila del sistema</span>
                        {:else}
                            <Button
                                type="button"
                                variant="flat"
                                size="sm"
                                icon="edit"
                                aria-label={`Editar ${row.name}`}
                                onclick={() => editCustomer(row.code)}
                            />
                        {/if}
                    </td>
                {/if}
            {/snippet}
        </Table>
    {/if}
</div>
