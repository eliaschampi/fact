<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { Alert, Button, Card, Chip, InfoItem, PageHeader, Table, Title } from '@lumi-ui/svelte';
    import { money } from '@/lib/money';
    import { purchaseStatusColor, purchaseStatusLabel } from '@/lib/purchases';

    interface PurchaseItemRow {
        code: string;
        quantity: number;
        unit_cost: string;
        line_total: string;
        variant: {
            code: string;
            sku: string;
            name: string | null;
            product_name: string;
        };
    }

    interface PurchaseDetail {
        code: string;
        purchase_number: string;
        status: string;
        ordered_at: string | null;
        received_at: string | null;
        cancelled_at: string | null;
        note: string | null;
        branch_name: string;
        user_name: string;
        is_receivable: boolean;
        is_cancellable: boolean;
        items: PurchaseItemRow[];
        total_cost: string;
    }

    interface Props {
        purchase: PurchaseDetail;
        can_manage?: boolean;
    }

    const { purchase, can_manage = false }: Props = $props();

    let processing = $state(false);
    let errors = $state<Record<string, string>>({});

    function receive(): void {
        router.post(
            `/purchases/${purchase.code}/receive`,
            {},
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

    function cancel(): void {
        router.post(
            `/purchases/${purchase.code}/cancel`,
            {},
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
    <title>{purchase.purchase_number} · NecoFact</title>
</svelte:head>

<div class="lumi-stack lumi-stack--lg lumi-min-width--0">
    <PageHeader
        title={purchase.purchase_number}
        subtitle="Compra de la sede {purchase.branch_name}."
        icon="shoppingCart"
        size="xl"
    >
        {#snippet actions()}
            <Chip color={purchaseStatusColor(purchase.status)}>
                {purchaseStatusLabel(purchase.status)}
            </Chip>
        {/snippet}
    </PageHeader>

    {#if errors.status || errors.items || errors.message}
        <Alert color="danger">{errors.status ?? errors.items ?? errors.message}</Alert>
    {/if}

    <div class="lumi-grid lumi-grid--columns-2-lg lumi-grid--gap-md">
        <Card spaced>
            {#snippet header()}
                <Title size="md" title="Datos" icon="info" />
            {/snippet}
            <div class="lumi-stack lumi-stack--md">
                <InfoItem icon="calendar" label="Fecha" value={purchase.ordered_at ?? '—'} />
                <InfoItem icon="building2" label="Sede" value={purchase.branch_name} />
                <InfoItem icon="user" label="Registró" value={purchase.user_name} />
                {#if purchase.received_at}
                    <InfoItem icon="checkCircle" label="Recibida" value={purchase.received_at} />
                {/if}
                {#if purchase.cancelled_at}
                    <InfoItem icon="x" label="Anulada" value={purchase.cancelled_at} />
                {/if}
                {#if purchase.note}
                    <InfoItem icon="tag" label="Nota" value={purchase.note} />
                {/if}
            </div>
        </Card>

        <Card spaced>
            {#snippet header()}
                <Title size="md" title="Acciones" icon="boxes" />
            {/snippet}
            <div class="lumi-stack lumi-stack--md">
                <p class="lumi-text--sm lumi-text--muted lumi-margin--none">
                    Recibir suma stock en esta sede y recalcula el costo promedio de la variante.
                    Anular una recibida resta el mismo stock; en tránsito no mueve inventario.
                </p>
                {#if can_manage && (purchase.is_receivable || purchase.is_cancellable)}
                    <div class="lumi-flex lumi-flex--gap-sm lumi-flex--wrap">
                        {#if purchase.is_receivable}
                            <Button icon="check" loading={processing} onclick={receive}>
                                Recibir compra
                            </Button>
                        {/if}
                        {#if purchase.is_cancellable}
                            <Button
                                variant="border"
                                color="danger"
                                icon="x"
                                loading={processing}
                                onclick={cancel}
                            >
                                Anular
                            </Button>
                        {/if}
                    </div>
                {/if}
            </div>
        </Card>
    </div>

    <Card spaced>
        {#snippet header()}
            <Title size="md" title="Ítems" icon="package" />
        {/snippet}
        <Table
            data={purchase.items}
            rowKey={(row) => row.code}
            compact
            aria-label="Ítems de la compra"
        >
            {#snippet thead()}
                <th scope="col">Producto</th>
                <th scope="col">SKU</th>
                <th scope="col">Cantidad</th>
                <th scope="col">Costo</th>
                <th scope="col">Total</th>
            {/snippet}
            {#snippet row({ row }: { row: PurchaseItemRow })}
                <td>
                    <strong>{row.variant.product_name}</strong>
                    {#if row.variant.name}
                        <span class="lumi-text--sm lumi-text--muted"> · {row.variant.name}</span>
                    {/if}
                </td>
                <td>{row.variant.sku}</td>
                <td>{row.quantity}</td>
                <td>{money(row.unit_cost)}</td>
                <td><strong>{money(row.line_total)}</strong></td>
            {/snippet}
        </Table>
        <p class="lumi-font--medium lumi-margin--none">Total: {money(purchase.total_cost)}</p>
    </Card>

    <div>
        <Button
            type="button"
            variant="border"
            icon="arrowLeft"
            onclick={() => router.visit('/purchases')}
        >
            Volver a compras
        </Button>
    </div>
</div>
