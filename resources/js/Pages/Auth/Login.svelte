<script module lang="ts">
    export const layout = false;
</script>

<script lang="ts">
    import { page, useForm } from '@inertiajs/svelte';
    import { Button, Card, Input, Title } from '@lumi-ui/svelte';

    interface LoginForm {
        login: string;
        password: string;
    }

    const form = useForm<LoginForm>({
        login: '',
        password: '',
    });

    let showPassword = $state(false);
    const loginError = $derived(form.errors.login ?? page.props.errors.login ?? '');
    const passwordError = $derived(form.errors.password ?? page.props.errors.password ?? '');

    function submit(event: SubmitEvent): void {
        event.preventDefault();
        form.post('/login', {
            onFinish: () => {
                form.reset('password');
                showPassword = false;
            },
        });
    }
</script>

<svelte:head>
    <title>Ingresar · NecoFact</title>
</svelte:head>

<main class="lumi-centered-layout">
    <Card class="lumi-centered-card lumi-centered-card--sm" spaced>
        {#snippet header()}
            <Title
                level={1}
                size="lg"
                icon="receiptText"
                title="Bienvenido a NecoFact"
                subtitle="Ingresa con tu cuenta de usuario"
            />
        {/snippet}

        <form class="lumi-stack lumi-stack--md" onsubmit={submit}>
            <Input
                bind:value={form.login}
                name="login"
                label="Usuario"
                icon="user"
                iconLabel="Enfocar usuario"
                placeholder="Ingresa tu usuario"
                maxlength={100}
                required
                disabled={form.processing}
                danger={Boolean(loginError)}
                dangerText={loginError}
            />

            <Input
                bind:value={form.password}
                name="password"
                type={showPassword ? 'text' : 'password'}
                label="Contraseña"
                icon="lock"
                iconLabel="Enfocar contraseña"
                actionIcon={showPassword ? 'eyeOff' : 'eye'}
                placeholder="Ingresa tu contraseña"
                actionLabel={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                onaction-click={() => (showPassword = !showPassword)}
                maxlength={255}
                required
                disabled={form.processing}
                danger={Boolean(passwordError)}
                dangerText={passwordError}
            />

            <Button
                type="submit"
                variant="filled"
                class="lumi-width--full"
                loading={form.processing}
            >
                Ingresar
            </Button>
        </form>
    </Card>
</main>
