<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import { Button, Card, EmptyState, PageHeader, QuickAccessCard } from '@lumi-ui/svelte';
    import { can } from '@/lib/permissions';

    const auth = $derived(page.props.auth);
    const userName = $derived(auth ? `${auth.user.first_name} ${auth.user.last_name}` : '');
    const needsBranch = $derived(Boolean(auth && auth.branches.length > 0 && !auth.current_branch));
    const noBranches = $derived(Boolean(auth && auth.branches.length === 0));
</script>

<svelte:head>
    <title>Inicio · NecoFact</title>
</svelte:head>

<div class="lumi-stack lumi-stack--xl lumi-min-width--0">
    <PageHeader
        title="Inicio"
        subtitle={userName ? `Hola, ${userName}. Elige la sede y empieza a operar.` : 'NecoFact'}
        icon="house"
        size="xl"
    />

    {#if noBranches}
        <Card spaced>
            <EmptyState
                icon="building2"
                title="Sin sede asignada"
                description="Pide a un administrador que te asigne a una sede habilitada para operar."
            />
        </Card>
    {:else if needsBranch}
        <Card spaced>
            <EmptyState
                icon="mapPin"
                title="Selecciona una sede de trabajo"
                description="Elige la sede activa de tu sesión para continuar con el trabajo diario."
            >
                {#snippet actions()}
                    <Button
                        type="button"
                        icon="building2"
                        onclick={() => router.visit('/branches')}
                    >
                        Ir a sedes
                    </Button>
                {/snippet}
            </EmptyState>
        </Card>
    {:else}
        <div
            class="lumi-grid lumi-grid--columns-3 lumi-grid--gap-md lumi-width--full lumi-min-width--0"
        >
            <QuickAccessCard
                href="/branches"
                title="Sede de trabajo"
                description={auth?.current_branch
                    ? `Activa: ${auth.current_branch.name}`
                    : 'Cambia la sede de tu sesión.'}
                icon="building2"
                color="secondary"
            />
            {#if can('purchases.view')}
                <QuickAccessCard
                    href="/purchases"
                    title="Compras"
                    description="En tránsito y recepción de mercadería."
                    icon="shoppingCart"
                    color="primary"
                />
            {/if}
            {#if can('inventory.view')}
                <QuickAccessCard
                    href="/inventory"
                    title="Inventario"
                    description="Saldos de la sede activa."
                    icon="boxes"
                    color="secondary"
                />
            {/if}
            {#if can('cashbox.view')}
                <QuickAccessCard
                    href="/cashbox"
                    title="Caja"
                    description="Tu posición continua de efectivo."
                    icon="wallet"
                    color="success"
                />
            {/if}
        </div>
    {/if}
</div>
