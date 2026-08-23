# NecoFact

SEE del contribuyente: el emisor genera, firma y envía CPE `01` / `03` / `07`
y GRE remitente `09` desde este sistema. Repositorio **privado**. SUNAT
autentica al emisor, no al programador.

La operación comercial existe para producir un documento correcto, recuperable
y corregible. Stock, caja y clientes son el mínimo que hace eso usable todos
los días.

No es un plugin para Faztore o Aeduca. No es un ERP. No es un POS que imprime
una factura. No es SEE-SOL ni un registro de “developer SUNAT”.

**Stack:** Laravel 13 · PHP 8.5 · Inertia · Svelte 5 · TypeScript strict ·
Lumi · PostgreSQL · pnpm · Greenter `lite` 5.3.x

## Documentación

Mismo contrato que Aeduca. El código manda. Nada se declara implementado si
el código no lo contiene.

| Fuente           | Autoridad                                                    |
| ---------------- | ------------------------------------------------------------ |
| `AGENTS.md`      | Protocolo de ejecución                                       |
| `docs/SPEC.md`   | Ley permanente: producto, dominio, datos, SUNAT, invariantes |
| `docs/STATUS.md` | Hechos verificados del código actual                         |
| `TASK.md`        | Un vertical observable, temporal                             |

Evidencia de hermanos (no son plantilla ni autoridad):
`~/Documents/aeduca` (runtime), `~/Documents/faztore` (stock/caja),
`~/Documents/coedula` (idempotencia, caja = efectivo), `~/Documents/lumi-ui`.

## Arranque

```bash
cp .env.example .env
cp .env.testing.example .env.testing
php artisan key:generate
php artisan key:generate --env=testing
createdb necofact
createdb necofact_test
composer install
pnpm --dir ../lumi-ui install
pnpm --dir ../lumi-ui run package
pnpm install
php artisan migrate --seed
pnpm run build
composer run check
```

Opcional: `NECOFACT_SEED_ADMIN_LOGIN` y `NECOFACT_SEED_ADMIN_PASSWORD` crean
el primer superadmin.

```bash
composer run dev              # serve + queue + pail + vite
php artisan sunat:diagnose    # sin red a SUNAT
```

Tests y CI usan `necofact_test`. Nunca llaman a SUNAT. Nunca
`migrate:fresh` contra `necofact`. Certificados y Clave SOL no se
commitean.

Laboratorio CPE: ambiente `beta` + `MODDATOS` (estructuras XML, sin efecto
tributario, sin GRE). Un RUC NRUS no sustituye a un emisor de tercera
categoría. Detalle y fuentes: `docs/SPEC.md` §3.
