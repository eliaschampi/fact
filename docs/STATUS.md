# NecoFact — estado verificado

Sólo hechos que el código y los tests demuestran. Decisiones permanentes:
[`SPEC.md`](SPEC.md). Ejecución: `TASK.md` en la raíz.

**Última verificación:** 2026-08-23 (V0 + V1 Catálogo, con pase de
integridad).

`composer run check` verde: 50 tests / 165 assertions · Pint · svelte-check ·
oxlint · Prettier. `pnpm run build` verde. `down()` de la migración probado;
CHECKs, UNIQUE e índices trgm verificados en la base; FKs en `restrict`
(la variante no se borra ni por cascada).

## Implementado

| Área        | Hecho                                                                                                                                                                                                                                                                                                                                                                                     |
| ----------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Acceso      | `AuthAccount` + `User`; login throttled con dummy-hash; logout invalida sesión                                                                                                                                                                                                                                                                                                            |
| Sedes       | `issuer` + `branches` (`sunat_establishment_code`); selector de sesión; `user_branches` explícito                                                                                                                                                                                                                                                                                         |
| Permisos    | `dominio.view/manage`; grants ∩ role scope; superadmin = catálogo; `Gate::before` + `can()` UX-only                                                                                                                                                                                                                                                                                       |
| Front       | Inertia 3 + Svelte 5 runes + Lumi; shell login/inicio/sedes/productos                                                                                                                                                                                                                                                                                                                     |
| Fiscal core | `TaxMath` (10/20/30, half-up, brick/math); `CpeState`; gateways Greenter; `CacheTokenStore`; `ArtifactStore` inmutable; `Greenter\*` importado sólo en `Support/Sunat`                                                                                                                                                                                                                    |
| Tickets     | 98 pending · 0 completed · 99 failed; `queryTicket`/`queryCdr` no lanzan                                                                                                                                                                                                                                                                                                                  |
| Config      | `config/sunat.php` endpoints, plazos, par beta `MODDATOS`/`MODDATOS`; ambiente `beta`/`production`; disco `fiscal`; `LimaDate`; cola database `after_commit`                                                                                                                                                                                                                              |
| Diagnose    | `php artisan sunat:diagnose` (sin red)                                                                                                                                                                                                                                                                                                                                                    |
| Catálogo    | `products` + `product_variants`: sku lower único por emisor, `unit_code` Cat. 03 (`NIU`/`ZZ`) y `tax_affectation` Cat. 07 (`10`/`20`/`30`) en la variante, precio `NUMERIC(12,2)` IGV incluido; CHECKs nombrados + trgm GIN; `SaveProduct` (transacción, guard inactivo ⇒ sólo reactivar, variante nunca se borra); `/products` Index+Form con `products.view/manage`; seed demo 10/20/30 |

## No implementado (siguiente: V2)

Clientes, inventario, compras, caja ledger, ventas, tablas
`fiscal_*`, jobs de envío, PDF binario, GRE de producto, settings de emisor.

Secretos SUNAT se leen de env. `issuers` no guarda PEM ni Clave SOL.

## Pins verificados en lock

Laravel framework 13.26.x · PHP 8.5.8 · Greenter lite 5.3.0 ·
`greenter/gre-api` 1.0.2 · Inertia Laravel 3.3.x · `brick/math` ^0.18 ·
PostgreSQL 18. Guzzle quedó en 7.15 por `gre-api` (aceptable hasta que
Greenter declare 8).
