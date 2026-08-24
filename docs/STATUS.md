# NecoFact — estado verificado

Sólo hechos que el código y los tests demuestran. Decisiones permanentes:
[`SPEC.md`](SPEC.md). Ejecución: `TASK.md` en la raíz.

**Última verificación:** 2026-08-24 (V0–V3 Compras).

`composer run check` verde: 80 tests / 276 assertions · Pint · svelte-check ·
oxlint · Prettier. `pnpm run build` verde. `down()` de `000007` probado.

## Implementado

| Área        | Hecho                                                                                                                                                                                |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Acceso      | `AuthAccount` + `User`; login throttled con dummy-hash; logout invalida sesión                                                                                                       |
| Sedes       | `issuer` + `branches`; `UNIQUE` establecimiento; selector de sesión; `user_branches` explícito                                                                                       |
| Permisos    | `dominio.view/manage`; grants ∩ role scope; superadmin = catálogo; `Gate::before` + `can()` UX-only                                                                                  |
| Front       | Inertia 3 + Svelte 5 runes + Lumi; shell login/inicio/sedes/productos/clientes/compras/inventario                                                                                    |
| Fiscal core | `TaxMath` (`igvBase`); `CpeState`; gateways Greenter; `ArtifactStore` write-once; `Greenter\*` sólo en `Support/Sunat`                                                               |
| Tickets     | 98/0/99; código no numérico = `unknown`/`error`; `queryTicket`/`queryCdr` no lanzan                                                                                                  |
| Config      | `config/sunat.php`; disco `fiscal`; `LimaDate`; cola `after_commit`; tests `pgsql`/`necofact_test`                                                                                   |
| Catálogo    | productos/variantes; `cost_price NUMERIC(12,2)`; FK compuesta issuer; CHECKs + trgm; `SaveProduct`                                                                                   |
| Clientes    | Cat. 06; UNIQUE identidad; CHECK DNI/RUC; `RucNumber`; consumidor final de sistema                                                                                                   |
| Compras     | `purchases` default `in_transit`; `PUR-` por emisor; receive con lock, stock + y costo promedio ponderado; cancel received resta; inactivo no se opera; `/purchases` Index/Form/Show |
| Inventario  | `inventory_balances` sparse `(variant_code, branch_code)` CHECK `on_hand >= 0`; `/inventory` saldos por sede; `InventoryBalance::applyDelta` (insertOrIgnore + increment)            |

## No implementado (siguiente: V4)

Caja ledger, ventas, tablas `fiscal_*`, jobs de envío, PDF binario, GRE,
transferencias entre sedes, settings de emisor.

Secretos SUNAT se leen de env. `issuers` no guarda PEM ni Clave SOL.

## Pins verificados en lock

Laravel framework 13.26.x · PHP 8.5.8 · Greenter lite 5.3.0 ·
`greenter/gre-api` 1.0.2 · Inertia Laravel 3.3.x · `brick/math` ^0.18 ·
PostgreSQL 18. Guzzle quedó en 7.15 por `gre-api` (aceptable hasta que
Greenter declare 8).
