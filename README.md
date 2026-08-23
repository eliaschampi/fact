# NecoFact

Sistema de ventas cuyo artefacto primario es un documento electrónico SUNAT:
CPE `01` / `03` / `07` y GRE remitente `09`.

La operación comercial existe para producir un documento correcto, recuperable
y corregible. Stock, caja y clientes son el mínimo que hace eso usable todos
los días.

No es un plugin para Faztore o Aeduca. No es un ERP. No es un POS que imprime
una factura.

## Documentación (tres archivos)

| Archivo     | Dueño                                   |
| ----------- | --------------------------------------- |
| `README.md` | Cómo se trabaja y cómo se arranca       |
| `SPEC.md`   | Ley del producto y del código           |
| `TASK.md`   | Vertical en curso y ledger de ejecución |

El código manda sobre cualquier documento. Ningún archivo declara implementado
lo que el código no contiene.

## Stack

Laravel 13 · PHP 8.5 · Inertia 3 · Svelte 5 · Lumi · PostgreSQL 18 · pnpm ·
Greenter `lite` 5.3.x.

Hermano de runtime: `~/Documents/aeduca`. Invariantes de stock/caja:
`~/Documents/faztore` y `~/Documents/coedula`. UI: `~/Documents/lumi-ui`.

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

Opcional: `NECOFACT_SEED_ADMIN_LOGIN` y `NECOFACT_SEED_ADMIN_PASSWORD` en `.env`
crean el primer superadmin.

```bash
composer run dev     # serve + queue + pail + vite
php artisan sunat:diagnose   # sin red a SUNAT
```

CI y tests nunca llaman a SUNAT. `php artisan test` usa `necofact_test`.

## Protocolo de sesión

1. Leer este README, `SPEC.md` y el vertical activo en `TASK.md`.
2. Un vertical por sesión. No construir el producto entero en un prompt.
3. Seguir `SPEC.md` literalmente. Si una regla debe cambiar, se cambia ahí
   primero, en el mismo commit.
4. Al cerrar: `composer run check` y `pnpm run build` verdes, tests del
   vertical verdes, `TASK.md` actualizado.

Nunca saltar el vertical 0. Nunca llamar a SUNAT desde CI. Nunca regenerar
XML firmado en un retry.
