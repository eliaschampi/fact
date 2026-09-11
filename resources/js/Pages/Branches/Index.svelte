<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import { Alert, Button, Card, EmptyState, InfoItem, Input, PageHeader } from '@lumi-ui/svelte';
    import type { BranchSummary } from '@/types/auth';

    interface CatalogBranch {
        code: string;
        name: string;
        is_active: boolean;
        sunat_establishment_code: string;
        users_count: number;
    }

    interface Props {
        catalog?: CatalogBranch[];
        can_view_catalog?: boolean;
    }

    const { catalog = [], can_view_catalog = false }: Props = $props();

    const auth = $derived(page.props.auth);
    const workspaceCodes = $derived(
        new Set((auth?.branches ?? []).map((branch: BranchSummary) => branch.code)),
    );

    let switchingBranchCode = $state<string | null>(null);
    let branchFilter = $state('');

    const normalizedFilter = $derived(branchFilter.trim().toLowerCase());

    const cards = $derived.by(() => {
        if (can_view_catalog) {
            const source =
                normalizedFilter.length === 0
                    ? catalog
                    : catalog.filter((branch) =>
                          branch.name.toLowerCase().includes(normalizedFilter),
                      );

            return source.map((branch) => ({
                code: branch.code,
                name: branch.name,
                is_active: branch.is_active as boolean | null,
                users_count: branch.users_count,
                establishment: branch.sunat_establishment_code,
                canUse: workspaceCodes.has(branch.code),
            }));
        }

        return (auth?.branches ?? []).map((branch: BranchSummary) => ({
            code: branch.code,
            name: branch.name,
            is_active: null as boolean | null,
            users_count: null as number | null,
            establishment: null as string | null,
            canUse: true,
        }));
    });

    function selectBranch(branchCode: string): void {
        switchingBranchCode = branchCode;
        router.put(
            '/current-branch',
            { branch_code: branchCode },
            {
                preserveScroll: true,
                onFinish: () => {
                    switchingBranchCode = null;
                },
            },
        );
    }
</script>

<svelte:head>
    <title>Sedes · NecoFact</title>
</svelte:head>

<div class="lumi-stack lumi-stack--lg lumi-min-width--0">
    <PageHeader
        title="Sedes"
        subtitle={can_view_catalog
            ? 'Elige tu sede de trabajo. El catálogo es de consulta; el alta llega con el vertical de administración.'
            : 'Elige la sede con la que trabajarás durante esta sesión.'}
        icon="building2"
        size="xl"
    />

    {#if can_view_catalog && catalog.length > 0}
        <div class="lumi-flex lumi-flex--gap-md lumi-align-items--center lumi-flex--wrap">
            <div class="lumi-flex-item--grow">
                <Input
                    bind:value={branchFilter}
                    icon="search"
                    placeholder="Buscar por nombre de sede…"
                    aria-label="Filtrar sedes por nombre"
                />
            </div>
            <span class="lumi-text--sm lumi-text--muted">
                {cards.length} de {catalog.length}
            </span>
        </div>
    {/if}

    {#if cards.length === 0}
        {#if can_view_catalog && catalog.length === 0}
            <EmptyState
                icon="building2"
                title="Sin sedes registradas"
                description="El emisor todavía no tiene sedes. Eso se corrige en el seeder o en un vertical posterior."
            />
        {:else if can_view_catalog}
            <Alert color="info">No hay sedes que coincidan con tu búsqueda.</Alert>
        {:else}
            <EmptyState
                icon="building2"
                title="Sin sedes disponibles"
                description="No tienes sedes asignadas. Solicita acceso a un administrador."
            />
        {/if}
    {:else}
        <div
            class="lumi-grid lumi-grid--cards lumi-grid--gap-md"
            role="list"
            aria-label={can_view_catalog ? 'Catálogo de sedes' : 'Sedes de trabajo'}
        >
            {#each cards as branch (branch.code)}
                {@const isCurrent = auth?.current_branch?.code === branch.code}
                <div role="listitem">
                    <Card
                        spaced
                        hoverable
                        selected={isCurrent}
                        class="lumi-width--full lumi-h--full"
                        title={branch.name}
                        subtitle={branch.is_active === false ? 'Deshabilitada' : 'Sede habilitada'}
                    >
                        <div class="lumi-stack lumi-stack--md">
                            {#if branch.users_count !== null}
                                <InfoItem
                                    icon="users"
                                    iconColor="info"
                                    label="Usuarios asignados"
                                    value={`${branch.users_count}`}
                                />
                            {/if}
                            {#if branch.establishment}
                                <InfoItem
                                    icon="building2"
                                    iconColor="secondary"
                                    label="CE SUNAT"
                                    value={branch.establishment}
                                />
                            {/if}
                            <div class="lumi-flex lumi-justify--end">
                                {#if isCurrent}
                                    <Button type="button" size="sm" icon="checkCircle" disabled>
                                        Sede activa
                                    </Button>
                                {:else}
                                    <Button
                                        type="button"
                                        variant="border"
                                        size="sm"
                                        icon="mapPin"
                                        loading={switchingBranchCode === branch.code}
                                        disabled={!branch.canUse || switchingBranchCode !== null}
                                        onclick={() => selectBranch(branch.code)}
                                    >
                                        Usar sede
                                    </Button>
                                {/if}
                            </div>
                        </div>
                    </Card>
                </div>
            {/each}
        </div>
    {/if}
</div>
