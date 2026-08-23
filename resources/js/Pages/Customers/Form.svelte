<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { Alert, Button, Card, Input, PageHeader, Select, Title } from '@lumi-ui/svelte';
    import { DOC_TYPE_OPTIONS } from '@/lib/customers';

    interface CustomerFormData {
        code: string;
        doc_type: string;
        doc_number: string;
        name: string;
    }

    interface Props {
        customer?: CustomerFormData | null;
    }

    const { customer = null }: Props = $props();

    let docType = $state(untrack(() => customer?.doc_type ?? '1'));
    let docNumber = $state(untrack(() => customer?.doc_number ?? ''));
    let name = $state(untrack(() => customer?.name ?? ''));

    let processing = $state(false);
    let errors = $state<Record<string, string>>({});

    const isCreate = untrack(() => customer === null);

    function submit(event: SubmitEvent): void {
        event.preventDefault();

        const payload = {
            doc_type: docType,
            doc_number: docNumber.trim(),
            name: name.trim(),
        };

        const options = {
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
        };

        if (isCreate) {
            router.post('/customers', payload, options);
        } else if (customer) {
            router.put(`/customers/${customer.code}`, payload, options);
        }
    }
</script>

<svelte:head>
    <title>{isCreate ? 'Nuevo cliente' : 'Editar cliente'} · NecoFact</title>
</svelte:head>

<div class="lumi-stack lumi-stack--lg lumi-min-width--0">
    <PageHeader
        title={isCreate ? 'Nuevo cliente' : 'Editar cliente'}
        subtitle="La factura exige RUC; la boleta de 700 soles o más exige identidad real."
        icon="users"
        size="xl"
    />

    {#if errors.message}
        <Alert color="danger">{errors.message}</Alert>
    {/if}

    <form class="lumi-stack lumi-stack--lg" onsubmit={submit}>
        <Card spaced>
            {#snippet header()}
                <Title size="md" title="Identidad tributaria" icon="userRound" />
            {/snippet}
            <div class="lumi-grid lumi-grid--columns-2-lg lumi-grid--gap-md">
                <Select
                    bind:value={docType}
                    options={DOC_TYPE_OPTIONS}
                    label="Tipo de documento (Cat. 06)"
                    clearable={false}
                    error={Boolean(errors.doc_type)}
                    errorMessage={errors.doc_type}
                />
                <Input
                    bind:value={docNumber}
                    label="Número de documento"
                    placeholder="DNI 8 dígitos · RUC 11 dígitos"
                    required
                    danger={Boolean(errors.doc_number)}
                    dangerText={errors.doc_number}
                />
                <Input
                    bind:value={name}
                    label="Nombre o razón social"
                    placeholder="María Quispe · COMERCIAL ANDINA SAC"
                    required
                    danger={Boolean(errors.name)}
                    dangerText={errors.name}
                />
            </div>
        </Card>

        <div class="lumi-flex lumi-flex--gap-md lumi-justify--end">
            <Button
                type="button"
                variant="border"
                icon="arrowLeft"
                disabled={processing}
                onclick={() => router.visit('/customers')}
            >
                Cancelar
            </Button>
            <Button type="submit" icon="check" loading={processing}>
                {isCreate ? 'Registrar cliente' : 'Guardar cambios'}
            </Button>
        </div>
    </form>
</div>
