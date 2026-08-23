<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import {
        Alert,
        Button,
        Card,
        Divider,
        Input,
        PageHeader,
        Select,
        Switch,
        Textarea,
        Title,
    } from '@lumi-ui/svelte';
    import { AFFECTATION_OPTIONS, UNIT_OPTIONS } from '@/lib/catalog';

    interface VariantFormRow {
        code: string | null;
        sku: string;
        name: string;
        unit_code: string;
        tax_affectation: string;
        price: string;
    }

    interface ProductFormData {
        code: string;
        name: string;
        description: string | null;
        is_active: boolean;
        variants: Array<{
            code: string;
            sku: string;
            name: string | null;
            unit_code: string;
            tax_affectation: string;
            price: string;
        }>;
    }

    interface Props {
        product?: ProductFormData | null;
    }

    const { product = null }: Props = $props();

    function emptyVariant(): VariantFormRow {
        return {
            code: null,
            sku: '',
            name: '',
            unit_code: 'NIU',
            tax_affectation: '10',
            price: '',
        };
    }

    let name = $state(untrack(() => product?.name ?? ''));
    let description = $state(untrack(() => product?.description ?? ''));
    let isActive = $state(untrack(() => product?.is_active ?? true));
    let variants = $state<VariantFormRow[]>(
        untrack(() => {
            const seeded = (product?.variants ?? []).map((variant) => ({
                code: variant.code,
                sku: variant.sku,
                name: variant.name ?? '',
                unit_code: variant.unit_code,
                tax_affectation: variant.tax_affectation,
                price: variant.price,
            }));

            return seeded.length > 0 ? seeded : [emptyVariant()];
        }),
    );

    let processing = $state(false);
    let errors = $state<Record<string, string>>({});

    const isCreate = untrack(() => product === null);

    function addVariant(): void {
        variants = [...variants, emptyVariant()];
    }

    function removeVariant(index: number): void {
        variants = variants.filter((_, current) => current !== index);
    }

    function variantError(index: number, field: string): string {
        return errors[`variants.${index}.${field}`] ?? '';
    }

    function submit(event: SubmitEvent): void {
        event.preventDefault();

        const payload = {
            name: name.trim(),
            description: description.trim() === '' ? null : description.trim(),
            is_active: isActive,
            variants: variants.map((variant) => ({
                code: variant.code,
                sku: variant.sku.trim(),
                name: variant.name.trim() === '' ? null : variant.name.trim(),
                unit_code: variant.unit_code,
                tax_affectation: variant.tax_affectation,
                price: variant.price,
            })),
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
            router.post('/products', payload, options);
        } else if (product) {
            router.put(`/products/${product.code}`, payload, options);
        }
    }
</script>

<svelte:head>
    <title>{isCreate ? 'Nuevo producto' : 'Editar producto'} · NecoFact</title>
</svelte:head>

<div class="lumi-stack lumi-stack--lg lumi-min-width--0">
    <PageHeader
        title={isCreate ? 'Nuevo producto' : 'Editar producto'}
        subtitle="El precio del POS incluye IGV; la afectación se define por variante."
        icon="package"
        size="xl"
    />

    {#if errors.message}
        <Alert color="danger">{errors.message}</Alert>
    {/if}

    {#if product !== null && !product.is_active}
        <Alert color="warning">
            Este producto está inactivo. Actívalo con el interruptor para guardar cambios.
        </Alert>
    {/if}

    <form class="lumi-stack lumi-stack--lg" onsubmit={submit}>
        <Card spaced>
            {#snippet header()}
                <Title size="md" title="Datos del producto" icon="tag" />
            {/snippet}
            <div class="lumi-stack lumi-stack--md">
                <Input
                    bind:value={name}
                    label="Nombre"
                    placeholder="Café molido 250 g"
                    required
                    danger={Boolean(errors.name)}
                    dangerText={errors.name}
                />
                <Textarea
                    bind:value={description}
                    label="Descripción"
                    placeholder="Detalle interno del producto (opcional)"
                    rows={2}
                    maxlength={500}
                    showCount
                />
                <Switch bind:checked={isActive} label="Producto activo" />
                {#if errors.is_active}
                    <Alert color="danger">{errors.is_active}</Alert>
                {/if}
            </div>
        </Card>

        <Card spaced>
            {#snippet header()}
                <Title
                    size="md"
                    title="Variantes"
                    subtitle="La variante es lo que se vende y lo que se factura."
                    icon="boxes"
                />
            {/snippet}
            <div class="lumi-stack lumi-stack--lg">
                {#each variants as variant, index (index)}
                    {@const skuError = variantError(index, 'sku')}
                    {@const priceError = variantError(index, 'price')}
                    {@const affectationError = variantError(index, 'tax_affectation')}
                    {@const unitError = variantError(index, 'unit_code')}
                    {#if index > 0}
                        <Divider />
                    {/if}
                    <div class="lumi-grid lumi-grid--columns-2-lg lumi-grid--gap-md">
                        <Input
                            bind:value={variant.sku}
                            label="SKU"
                            placeholder="cafe-molido-250"
                            required
                            danger={Boolean(skuError)}
                            dangerText={skuError}
                        />
                        <Input
                            bind:value={variant.name}
                            label="Presentación"
                            placeholder="Bolsa 250 g (opcional)"
                        />
                        <Select
                            bind:value={variant.unit_code}
                            options={UNIT_OPTIONS}
                            label="Unidad (Cat. 03)"
                            clearable={false}
                            error={Boolean(unitError)}
                            errorMessage={unitError}
                        />
                        <Select
                            bind:value={variant.tax_affectation}
                            options={AFFECTATION_OPTIONS}
                            label="Afectación IGV (Cat. 07)"
                            clearable={false}
                            error={Boolean(affectationError)}
                            errorMessage={affectationError}
                        />
                        <Input
                            bind:value={variant.price}
                            type="number"
                            min="0"
                            step="0.01"
                            label="Precio (S/, IGV incluido)"
                            placeholder="18.50"
                            required
                            danger={Boolean(priceError)}
                            dangerText={priceError}
                        />
                        <div class="lumi-flex lumi-flex--gap-sm lumi-align-items--end">
                            {#if variant.code === null && variants.length > 1}
                                <Button
                                    type="button"
                                    variant="flat"
                                    color="danger"
                                    size="sm"
                                    icon="x"
                                    aria-label={`Quitar variante ${index + 1}`}
                                    onclick={() => removeVariant(index)}
                                >
                                    Quitar
                                </Button>
                            {/if}
                        </div>
                    </div>
                {/each}

                {#if errors.variants}
                    <Alert color="danger">{errors.variants}</Alert>
                {/if}
                <div>
                    <Button type="button" variant="border" icon="plus" onclick={addVariant}>
                        Agregar variante
                    </Button>
                </div>
            </div>
        </Card>

        <div class="lumi-flex lumi-flex--gap-md lumi-justify--end">
            <Button
                type="button"
                variant="border"
                icon="arrowLeft"
                disabled={processing}
                onclick={() => router.visit('/products')}
            >
                Cancelar
            </Button>
            <Button type="submit" icon="check" loading={processing}>
                {isCreate ? 'Registrar producto' : 'Guardar cambios'}
            </Button>
        </div>
    </form>
</div>
