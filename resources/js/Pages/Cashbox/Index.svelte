<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import {
        Alert,
        Button,
        Card,
        Chip,
        DateRangeFilter,
        Dialog,
        Dropdown,
        DropdownItem,
        EmptyState,
        InfoItem,
        Input,
        PageHeader,
        SegmentedControl,
        Select,
        StatCard,
        Table,
        Textarea,
        Title,
        type SelectOption,
        type SelectValue,
    } from '@lumi-ui/svelte';
    import {
        CASHBOX_VIEW_OPTIONS,
        cashboxDirectionColor,
        cashboxDirectionLabel,
        cashboxMovementLabel,
        cashboxStatusColor,
        cashboxStatusLabel,
        shortBusinessDate,
        shortLimaDateTime,
    } from '@/lib/cashbox';
    import { money } from '@/lib/money';

    interface CashboxUser {
        code: string;
        name: string;
    }

    interface CashboxSummary {
        opening_amount: string;
        income_amount: string;
        expense_amount: string;
        transfer_in_amount: string;
        transfer_out_amount: string;
        current_amount: string;
    }

    interface CashboxMovementRow {
        code: string;
        owner_code: string;
        reference: string | null;
        movement_type: string;
        direction: string;
        amount: string;
        business_date: string | null;
        created_at: string | null;
        title: string;
        detail: string | null;
        counterparty: string | null;
        branch_name: string;
        status: string;
        reversal_reason: string | null;
        return_amount: string | null;
        can_return: boolean;
        can_reverse: boolean;
    }

    interface MovementPagination {
        data: CashboxMovementRow[];
        current_page: number;
        last_page: number;
        total: number;
    }

    interface CashboxDay extends CashboxSummary {
        business_date: string;
    }

    interface Props {
        cashier: CashboxUser;
        cashiers?: CashboxUser[];
        transfer_recipients?: CashboxUser[];
        filters: {
            view: string;
            date: string;
            from: string;
            to: string;
            month: string;
            today: string;
        };
        summary: CashboxSummary;
        movements?: MovementPagination;
        month_days?: CashboxDay[];
        month_totals: CashboxSummary;
        can_manage?: boolean;
        can_write?: boolean;
        can_supervise?: boolean;
        has_branch?: boolean;
        is_supervising?: boolean;
    }

    const EMPTY_MOVEMENTS: MovementPagination = {
        data: [],
        current_page: 1,
        last_page: 1,
        total: 0,
    };

    const {
        cashier,
        cashiers = [],
        transfer_recipients = [],
        filters,
        summary,
        movements = EMPTY_MOVEMENTS,
        month_days = [],
        month_totals,
        can_manage = false,
        can_write = false,
        can_supervise = false,
        has_branch = false,
        is_supervising = false,
    }: Props = $props();

    let activeView = $state(untrack(() => filters.view));
    let date = $state(untrack(() => filters.date));
    let from = $state(untrack(() => filters.from));
    let to = $state(untrack(() => filters.to));
    let month = $state(untrack(() => filters.month));
    let cashierCode = $state(untrack(() => cashier.code));

    let incomeOpen = $state(false);
    let outflowOpen = $state(false);
    let transferOpen = $state(false);
    let returnOpen = $state(false);
    let reversalOpen = $state(false);
    let detailOpen = $state(false);
    let processing = $state(false);
    let errors = $state<Record<string, string>>({});
    let selectedMovement = $state<CashboxMovementRow | null>(null);

    let income = $state({ request_key: '', amount: '', concept: '', note: '' });
    let outflow = $state({ request_key: '', amount: '', concept: '', note: '' });
    let transfer = $state({
        request_key: '',
        destination_cashier_code: '',
        amount: '',
        note: '',
    });
    let cashReturn = $state({ request_key: '', amount: '', note: '' });
    let reversalReason = $state('');

    const cashierOptions = $derived<SelectOption[]>(
        cashiers.map((item) => ({ value: item.code, label: item.name })),
    );
    const recipientOptions = $derived<SelectOption[]>(
        transfer_recipients.map((item) => ({ value: item.code, label: item.name })),
    );
    const heading = $derived(
        activeView === 'month'
            ? 'Pulso mensual'
            : activeView === 'operations'
              ? 'Rastro de efectivo'
              : 'Caja',
    );
    const subtitle = $derived(
        is_supervising
            ? `Consulta de ${cashier.name}; no permite operar en su nombre.`
            : 'Tu efectivo real, continuo entre sedes y sin cierres artificiales.',
    );

    $effect(() => {
        activeView = filters.view;
        date = filters.date;
        from = filters.from;
        to = filters.to;
        month = filters.month;
        cashierCode = cashier.code;
    });

    function requestKey(): string {
        return crypto.randomUUID();
    }

    function selectedCashier(value: SelectValue | null): void {
        cashierCode = typeof value === 'string' ? value : cashier.code;
    }

    function selectedRecipient(value: SelectValue | null): void {
        transfer.destination_cashier_code = typeof value === 'string' ? value : '';
    }

    function queryFor(view = activeView, page = 1): Record<string, string | number> {
        return {
            view,
            ...(view === 'day' ? { date } : {}),
            ...(view === 'operations' ? { from, to } : {}),
            ...(view === 'month' ? { month } : {}),
            ...(can_supervise ? { cashier: cashierCode } : {}),
            ...(page > 1 ? { page } : {}),
        };
    }

    function apply(page = 1): void {
        router.get('/cashbox', queryFor(activeView, page), {
            preserveScroll: true,
            replace: true,
        });
    }

    function changeView(value: string | number): void {
        activeView = String(value);
        apply();
    }

    function openIncome(): void {
        income = { request_key: requestKey(), amount: '', concept: '', note: '' };
        errors = {};
        incomeOpen = true;
    }

    function openOutflow(): void {
        outflow = { request_key: requestKey(), amount: '', concept: '', note: '' };
        errors = {};
        outflowOpen = true;
    }

    function openTransfer(): void {
        transfer = {
            request_key: requestKey(),
            destination_cashier_code: '',
            amount: '',
            note: '',
        };
        errors = {};
        transferOpen = true;
    }

    function showDetail(movement: CashboxMovementRow): void {
        selectedMovement = movement;
        detailOpen = true;
    }

    function openReturn(movement: CashboxMovementRow): void {
        selectedMovement = movement;
        cashReturn = { request_key: requestKey(), amount: movement.amount, note: '' };
        errors = {};
        returnOpen = true;
    }

    function openReversal(movement: CashboxMovementRow): void {
        selectedMovement = movement;
        reversalReason = '';
        errors = {};
        reversalOpen = true;
    }

    function submitIncome(): void {
        if (processing) return;

        router.post('/cashbox/incomes', income, {
            onStart: () => {
                processing = true;
                errors = {};
            },
            onError: (formErrors) => (errors = formErrors),
            onSuccess: () => (incomeOpen = false),
            onFinish: () => (processing = false),
        });
    }

    function submitOutflow(): void {
        if (processing) return;

        router.post('/cashbox/outflows', outflow, {
            onStart: () => {
                processing = true;
                errors = {};
            },
            onError: (formErrors) => (errors = formErrors),
            onSuccess: () => (outflowOpen = false),
            onFinish: () => (processing = false),
        });
    }

    function submitTransfer(): void {
        if (processing) return;

        router.post('/cashbox/transfers', transfer, {
            onStart: () => {
                processing = true;
                errors = {};
            },
            onError: (formErrors) => (errors = formErrors),
            onSuccess: () => (transferOpen = false),
            onFinish: () => (processing = false),
        });
    }

    function submitReturn(): void {
        if (processing || !selectedMovement) return;

        router.post(`/cashbox/outflows/${selectedMovement.owner_code}/returns`, cashReturn, {
            preserveScroll: true,
            onStart: () => {
                processing = true;
                errors = {};
            },
            onError: (formErrors) => (errors = formErrors),
            onSuccess: () => (returnOpen = false),
            onFinish: () => (processing = false),
        });
    }

    function submitReversal(): void {
        if (processing || !selectedMovement) return;

        const path =
            selectedMovement.movement_type === 'manual_income'
                ? `/cashbox/incomes/${selectedMovement.owner_code}/reverse`
                : selectedMovement.movement_type === 'expense'
                  ? `/cashbox/outflows/${selectedMovement.owner_code}/reverse`
                  : `/cashbox/transfers/${selectedMovement.owner_code}/reverse`;

        router.post(
            path,
            { reason: reversalReason },
            {
                preserveScroll: true,
                onStart: () => {
                    processing = true;
                    errors = {};
                },
                onError: (formErrors) => (errors = formErrors),
                onSuccess: () => (reversalOpen = false),
                onFinish: () => (processing = false),
            },
        );
    }
</script>

<svelte:head>
    <title>{heading} · NecoFact</title>
</svelte:head>

{#snippet movementTable(paginated: boolean)}
    {#if movements.data.length === 0}
        <EmptyState
            icon="wallet"
            title="Sin movimientos"
            description="No hay efectivo registrado en este periodo."
        />
    {:else}
        <Table
            data={movements.data}
            rowKey={(row) => row.code}
            pagination={paginated && movements.last_page > 1}
            paginationMode="server"
            currentPage={movements.current_page}
            totalItems={movements.total}
            itemsPerPage={20}
            onpage-change={(page) => apply(page)}
            compact
            aria-label="Movimientos de efectivo"
        >
            {#snippet thead()}
                <th scope="col">Movimiento</th>
                <th scope="col">Fecha</th>
                <th scope="col">Origen</th>
                <th scope="col">Flujo</th>
                <th scope="col">Importe</th>
                <th scope="col">Estado</th>
                <th scope="col">Acciones</th>
            {/snippet}
            {#snippet row({ row }: { row: CashboxMovementRow })}
                <td>
                    <div class="lumi-stack lumi-stack--2xs">
                        <strong>{row.title}</strong>
                        <span class="lumi-text--xs lumi-text--muted">
                            {row.reference ?? cashboxMovementLabel(row.movement_type)}
                            {row.counterparty ? ` · ${row.counterparty}` : ''}
                        </span>
                    </div>
                </td>
                <td>{shortBusinessDate(row.business_date)}</td>
                <td>{row.branch_name}</td>
                <td>
                    <Chip size="sm" color={cashboxDirectionColor(row.direction)}>
                        {cashboxDirectionLabel(row.direction)}
                    </Chip>
                </td>
                <td>
                    <strong class={row.direction === 'in' ? 'lumi-text--success' : ''}>
                        {row.direction === 'in' ? '+' : '−'}{money(row.amount)}
                    </strong>
                </td>
                <td>
                    <Chip size="sm" color={cashboxStatusColor(row.status)}>
                        {cashboxStatusLabel(row.status)}
                    </Chip>
                </td>
                <td>
                    <div class="lumi-flex lumi-flex--gap-xs">
                        <Button
                            type="button"
                            variant="flat"
                            size="sm"
                            icon="eye"
                            aria-label={`Ver ${row.title}`}
                            onclick={() => showDetail(row)}
                        />
                        {#if row.can_return || row.can_reverse}
                            <Dropdown placement="bottom-end" aria-label={`Corregir ${row.title}`}>
                                {#snippet triggerContent()}
                                    <Button
                                        type="button"
                                        variant="flat"
                                        size="sm"
                                        icon="moreVertical"
                                        aria-label={`Abrir correcciones de ${row.title}`}
                                    />
                                {/snippet}
                                {#snippet content()}
                                    {#if row.can_return}
                                        <DropdownItem icon="rotateCcw" onclick={() => openReturn(row)}>
                                            Registrar Vuelto
                                        </DropdownItem>
                                    {/if}
                                    {#if row.can_reverse}
                                        <DropdownItem
                                            icon="xCircle"
                                            color="danger"
                                            onclick={() => openReversal(row)}
                                        >
                                            Anular movimiento
                                        </DropdownItem>
                                    {/if}
                                {/snippet}
                            </Dropdown>
                        {/if}
                    </div>
                </td>
            {/snippet}
        </Table>
    {/if}
{/snippet}

<div class="lumi-stack lumi-stack--lg lumi-min-width--0">
    <PageHeader title={heading} subtitle={subtitle} icon="wallet" size="xl">
        {#snippet actions()}
            <SegmentedControl
                value={activeView}
                options={CASHBOX_VIEW_OPTIONS}
                onchange={changeView}
                aria-label="Vista de caja"
            />
        {/snippet}
    </PageHeader>

    {#if is_supervising}
        <Alert color="info">
            Estás viendo la caja de {cashier.name}. Ninguna acción operará en su nombre.
        </Alert>
    {:else if can_write && !has_branch}
        <Alert color="warning">
            Selecciona una sede para registrar movimientos. Tu saldo sigue siendo uno solo entre
            todas tus sedes.
        </Alert>
    {/if}

    <Card spaced>
        <form
            class="lumi-flex lumi-flex--gap-md lumi-align-items--end lumi-flex--wrap"
            onsubmit={(event) => {
                event.preventDefault();
                apply();
            }}
        >
            {#if activeView === 'day'}
                <Input bind:value={date} type="date" label="Día consultado" icon="calendar" />
            {:else if activeView === 'operations'}
                <DateRangeFilter bind:fromValue={from} bind:toValue={to} hideApply />
            {:else}
                <Input bind:value={month} type="month" label="Mes consultado" icon="calendar" />
            {/if}

            {#if can_supervise}
                <Select
                    value={cashierCode}
                    options={cashierOptions}
                    label="Cajero"
                    clearable={false}
                    autocomplete
                    onchange={selectedCashier}
                />
            {/if}

            <Button type="submit" variant="border" icon="search">Consultar</Button>
        </form>
    </Card>

    {#if activeView === 'day'}
        <div class="lumi-grid lumi-grid--columns-4 lumi-grid--gap-md">
            <StatCard
                title="Apertura calculada"
                value={money(summary.opening_amount)}
                icon="history"
                color="secondary"
                subtitle="Saldo previo, no una sesión manual"
            />
            <StatCard
                title="Entró"
                value={money(summary.income_amount)}
                icon="plus"
                color="success"
                subtitle={`Transferido: ${money(summary.transfer_in_amount)}`}
            />
            <StatCard
                title="Salió"
                value={money(summary.expense_amount)}
                icon="minus"
                color="danger"
                subtitle={`Transferido: ${money(summary.transfer_out_amount)}`}
            />
            <StatCard
                title="Efectivo disponible"
                value={money(summary.current_amount)}
                icon="wallet"
                color="primary"
                subtitle={`Al ${shortBusinessDate(filters.date)}`}
            />
        </div>

        {#if can_manage}
            <Card spaced>
                {#snippet header()}
                    <Title
                        size="md"
                        title="Mover efectivo"
                        subtitle={`Todo se registra hoy, ${shortBusinessDate(filters.today)}, en ${cashier.name}.`}
                        icon="sparkles"
                    />
                {/snippet}
                <div class="lumi-grid lumi-grid--columns-3 lumi-grid--gap-md">
                    <Button variant="gradient" color="success" icon="plus" onclick={openIncome}>
                        Sumar efectivo
                    </Button>
                    <Button variant="flat" color="danger" icon="minus" onclick={openOutflow}>
                        Registrar gasto
                    </Button>
                    <Button
                        variant="border"
                        icon="send"
                        disabled={transfer_recipients.length === 0}
                        onclick={openTransfer}
                    >
                        Pasar a otro cajero
                    </Button>
                </div>
            </Card>
        {/if}

        <Card spaced>
            {#snippet header()}
                <Title
                    size="md"
                    title="Actividad del día"
                    subtitle="Cada fila es un hecho auditable; nada se elimina."
                    icon="receiptText"
                />
            {/snippet}
            {@render movementTable(false)}
            {#if movements.data.length > 0}
                <div class="lumi-flex lumi-justify--end">
                    <Button
                        variant="border"
                        icon="list"
                        onclick={() => changeView('operations')}
                    >
                        Ver todo el rastro
                    </Button>
                </div>
            {/if}
        </Card>
    {:else if activeView === 'operations'}
        <Card spaced>
            {#snippet header()}
                <Title
                    size="md"
                    title="Rastro de efectivo"
                    subtitle={`${shortBusinessDate(filters.from)} — ${shortBusinessDate(filters.to)}`}
                    icon="list"
                />
            {/snippet}
            {@render movementTable(true)}
        </Card>
    {:else}
        <div class="lumi-grid lumi-grid--columns-4 lumi-grid--gap-md">
            <StatCard
                title="Inicio de mes"
                value={money(month_totals.opening_amount)}
                icon="history"
                color="secondary"
            />
            <StatCard
                title="Entradas"
                value={money(month_totals.income_amount)}
                icon="plus"
                color="success"
            />
            <StatCard
                title="Salidas"
                value={money(month_totals.expense_amount)}
                icon="minus"
                color="danger"
            />
            <StatCard
                title="Cierre calculado"
                value={money(month_totals.current_amount)}
                icon="wallet"
                color="primary"
            />
        </div>

        <Card spaced>
            {#snippet header()}
                <Title
                    size="md"
                    title="Días con movimiento"
                    subtitle={`Transferencias recibidas ${money(month_totals.transfer_in_amount)} · enviadas ${money(month_totals.transfer_out_amount)}`}
                    icon="calendar"
                />
            {/snippet}
            {#if month_days.length === 0}
                <EmptyState
                    icon="calendar"
                    title="Mes sin movimientos"
                    description="No hubo entradas ni salidas de efectivo en este mes."
                />
            {:else}
                <Table
                    data={month_days}
                    rowKey={(row) => row.business_date}
                    compact
                    aria-label="Resumen mensual de caja"
                >
                    {#snippet thead()}
                        <th scope="col">Día</th>
                        <th scope="col">Apertura</th>
                        <th scope="col">Entradas</th>
                        <th scope="col">Salidas</th>
                        <th scope="col">Recibido</th>
                        <th scope="col">Enviado</th>
                        <th scope="col">Cierre</th>
                    {/snippet}
                    {#snippet row({ row }: { row: CashboxDay })}
                        <td><strong>{shortBusinessDate(row.business_date)}</strong></td>
                        <td>{money(row.opening_amount)}</td>
                        <td>{money(row.income_amount)}</td>
                        <td>{money(row.expense_amount)}</td>
                        <td>{money(row.transfer_in_amount)}</td>
                        <td>{money(row.transfer_out_amount)}</td>
                        <td><strong>{money(row.current_amount)}</strong></td>
                    {/snippet}
                </Table>
            {/if}
        </Card>
    {/if}
</div>

<Dialog bind:open={incomeOpen} title="Sumar efectivo" size="sm" persistent={processing}>
    <form
        class="lumi-stack lumi-stack--md"
        onsubmit={(event) => {
            event.preventDefault();
            submitIncome();
        }}
    >
        <Alert color="info">Registra sólo billetes y monedas que recibiste físicamente.</Alert>
        <Input
            bind:value={income.amount}
            type="number"
            min="0.01"
            step="0.01"
            label="Importe (S/)"
            placeholder="0.00"
            danger={Boolean(errors.amount)}
            dangerText={errors.amount}
            required
        />
        <Input
            bind:value={income.concept}
            label="Origen"
            placeholder="Ej. Fondo inicial entregado"
            maxlength={160}
            danger={Boolean(errors.concept)}
            dangerText={errors.concept}
            required
        />
        <Textarea
            bind:value={income.note}
            label="Nota (opcional)"
            maxlength={500}
            error={errors.note}
        />
        {#if errors.request_key || errors.message}
            <Alert color="danger">{errors.request_key ?? errors.message}</Alert>
        {/if}
        <div class="lumi-flex lumi-justify--end lumi-flex--gap-sm">
            <Button
                type="button"
                variant="border"
                disabled={processing}
                onclick={() => (incomeOpen = false)}
            >
                Cancelar
            </Button>
            <Button type="submit" color="success" icon="plus" loading={processing}>
                Sumar a mi caja
            </Button>
        </div>
    </form>
</Dialog>

<Dialog bind:open={outflowOpen} title="Registrar gasto" size="sm" persistent={processing}>
    <form
        class="lumi-stack lumi-stack--md"
        onsubmit={(event) => {
            event.preventDefault();
            submitOutflow();
        }}
    >
        <Input
            bind:value={outflow.amount}
            type="number"
            min="0.01"
            step="0.01"
            label="Efectivo usado (S/)"
            placeholder="0.00"
            danger={Boolean(errors.amount)}
            dangerText={errors.amount}
            required
        />
        <Input
            bind:value={outflow.concept}
            label="Concepto"
            placeholder="Ej. Movilidad para entrega"
            maxlength={160}
            danger={Boolean(errors.concept)}
            dangerText={errors.concept}
            required
        />
        <Textarea
            bind:value={outflow.note}
            label="Nota (opcional)"
            maxlength={500}
            error={errors.note}
        />
        {#if errors.request_key || errors.message}
            <Alert color="danger">{errors.request_key ?? errors.message}</Alert>
        {/if}
        <div class="lumi-flex lumi-justify--end lumi-flex--gap-sm">
            <Button
                type="button"
                variant="border"
                disabled={processing}
                onclick={() => (outflowOpen = false)}
            >
                Cancelar
            </Button>
            <Button type="submit" color="danger" icon="minus" loading={processing}>
                Registrar gasto
            </Button>
        </div>
    </form>
</Dialog>

<Dialog bind:open={transferOpen} title="Pasar efectivo" size="sm" persistent={processing}>
    <form
        class="lumi-stack lumi-stack--md"
        onsubmit={(event) => {
            event.preventDefault();
            submitTransfer();
        }}
    >
        <Alert color="info">
            El importe saldrá de tu caja y entrará a la del cajero elegido en una sola operación.
        </Alert>
        <Select
            value={transfer.destination_cashier_code}
            options={recipientOptions}
            label="Recibe"
            placeholder="Selecciona otro cajero"
            clearable={false}
            autocomplete
            error={Boolean(errors.destination_cashier_code)}
            errorMessage={errors.destination_cashier_code}
            onchange={selectedRecipient}
        />
        <Input
            bind:value={transfer.amount}
            type="number"
            min="0.01"
            step="0.01"
            label="Importe (S/)"
            placeholder="0.00"
            danger={Boolean(errors.amount)}
            dangerText={errors.amount}
            required
        />
        <Textarea
            bind:value={transfer.note}
            label="Nota (opcional)"
            maxlength={500}
            error={errors.note}
        />
        {#if errors.request_key || errors.message}
            <Alert color="danger">{errors.request_key ?? errors.message}</Alert>
        {/if}
        <div class="lumi-flex lumi-justify--end lumi-flex--gap-sm">
            <Button
                type="button"
                variant="border"
                disabled={processing}
                onclick={() => (transferOpen = false)}
            >
                Cancelar
            </Button>
            <Button type="submit" icon="send" loading={processing}>Transferir efectivo</Button>
        </div>
    </form>
</Dialog>

<Dialog bind:open={returnOpen} title="Registrar Vuelto" size="sm" persistent={processing}>
    <form
        class="lumi-stack lumi-stack--md"
        onsubmit={(event) => {
            event.preventDefault();
            submitReturn();
        }}
    >
        {#if selectedMovement}
            <div class="lumi-grid lumi-grid--columns-2-lg lumi-grid--gap-md">
                <InfoItem icon="receiptText" label="Gasto" value={selectedMovement.title} />
                <InfoItem icon="wallet" label="Entregado" value={money(selectedMovement.amount)} />
            </div>
        {/if}
        <Input
            bind:value={cashReturn.amount}
            type="number"
            min="0.01"
            max={selectedMovement?.amount}
            step="0.01"
            label="Efectivo devuelto (S/)"
            danger={Boolean(errors.amount)}
            dangerText={errors.amount}
            required
        />
        <Textarea
            bind:value={cashReturn.note}
            label="Nota (opcional)"
            maxlength={500}
            error={errors.note}
        />
        {#if errors.request_key || errors.message}
            <Alert color="danger">{errors.request_key ?? errors.message}</Alert>
        {/if}
        <div class="lumi-flex lumi-justify--end lumi-flex--gap-sm">
            <Button
                type="button"
                variant="border"
                disabled={processing}
                onclick={() => (returnOpen = false)}
            >
                Cancelar
            </Button>
            <Button type="submit" icon="rotateCcw" loading={processing}>Registrar Vuelto</Button>
        </div>
    </form>
</Dialog>

<Dialog bind:open={reversalOpen} title="Anular movimiento" size="sm" persistent={processing}>
    <form
        class="lumi-stack lumi-stack--md"
        onsubmit={(event) => {
            event.preventDefault();
            submitReversal();
        }}
    >
        <Alert color="warning" title={selectedMovement?.title}>
            El hecho se conservará en el rastro con actor, momento y motivo de anulación.
        </Alert>
        <Textarea
            bind:value={reversalReason}
            label="Motivo de anulación"
            placeholder="Explica por qué debe corregirse"
            maxlength={500}
            error={errors.reason}
            required
        />
        {#if errors.amount || errors.message}
            <Alert color="danger">{errors.amount ?? errors.message}</Alert>
        {/if}
        <div class="lumi-flex lumi-justify--end lumi-flex--gap-sm">
            <Button
                type="button"
                variant="border"
                disabled={processing}
                onclick={() => (reversalOpen = false)}
            >
                Cancelar
            </Button>
            <Button type="submit" color="danger" icon="xCircle" loading={processing}>
                Anular movimiento
            </Button>
        </div>
    </form>
</Dialog>

<Dialog bind:open={detailOpen} title="Detalle de efectivo" size="md" scrollable>
    {#if selectedMovement}
        <div class="lumi-stack lumi-stack--md">
            <div class="lumi-grid lumi-grid--columns-2-lg lumi-grid--gap-md">
                <InfoItem
                    icon="receiptText"
                    label="Operación"
                    value={cashboxMovementLabel(selectedMovement.movement_type)}
                />
                <InfoItem
                    icon="wallet"
                    label="Importe"
                    value={money(selectedMovement.amount)}
                />
                <InfoItem
                    icon="calendar"
                    label="Registrado"
                    value={shortLimaDateTime(selectedMovement.created_at)}
                />
                <InfoItem icon="building2" label="Sede de origen" value={selectedMovement.branch_name} />
                {#if selectedMovement.reference}
                    <InfoItem
                        icon="receiptText"
                        label="Referencia"
                        value={selectedMovement.reference}
                    />
                {/if}
                {#if selectedMovement.counterparty}
                    <InfoItem
                        icon="user"
                        label="Contraparte"
                        value={selectedMovement.counterparty}
                    />
                {/if}
                {#if selectedMovement.return_amount}
                    <InfoItem
                        icon="rotateCcw"
                        label="Vuelto"
                        value={money(selectedMovement.return_amount)}
                    />
                {/if}
            </div>
            <InfoItem icon="tag" label="Concepto" value={selectedMovement.title} />
            {#if selectedMovement.detail}
                <InfoItem icon="info" label="Nota" value={selectedMovement.detail} />
            {/if}
            {#if selectedMovement.reversal_reason}
                <Alert color="secondary" title="Movimiento anulado">
                    {selectedMovement.reversal_reason}
                </Alert>
            {/if}
        </div>
    {/if}
</Dialog>
