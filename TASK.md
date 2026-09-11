# TASK.md — vertical activo

Único archivo que se muta cada sesión. Un vertical por sesión. Un criterio
sin test no cuenta como hecho. Ley: `docs/SPEC.md`. Hechos: `docs/STATUS.md`.

## Estado

| Campo                | Valor       |
| -------------------- | ----------- |
| Fase                 | V4 en curso |
| Siguiente sesión     | **V4 Caja** |
| Última actualización | 2026-09-03  |

---

## V0 — Fundación (cerrado)

Login, sede, permisos, Greenter 5.3, TaxMath, CpeState, adapters SUNAT.
Checks verdes. Detalle en `docs/STATUS.md`.

---

## V1 — Catálogo (cerrado)

**Frase:** hay productos vendibles con precio y afectación, por sede.

- [x] Migración `products` + `product_variants`: `unit_code` Cat. 03 default
      `NIU`; `tax_affectation` Cat. 07 default `'10'`; precios `decimal(12,2)`;
      sku único lower; `issuer_code`.
- [x] Actions + FormRequests + `/products` (Index/Form) con
      `products.view` / `products.manage`.
- [x] Seed demo: 3 productos, afectaciones 10 / 20 / 30.
- [x] Feature test: crear/editar/inactivar; inactivo no se opera en el Action.

`SaveProduct` guarda el agregado en una transacción; inactivo ⇒ sólo
reactivación (SPEC §4 Catálogo). Checks verdes 2026-08-23: 49 tests /
163 assertions, build verde.

---

## V2 — Cliente (cerrado)

**Frase:** el cajero registra DNI/RUC sin salir del ticket.

- [x] Migración `customers`: Cat. 06 v1 (`0`/`1`/`6`), identidad
      única por emisor, trgm en nombre, índice en documento.
- [x] `Support\RucNumber` (módulo 11, factores 5,4,3,2,7,6,5,4,3,2;
      `dv = (11 − resto) % 10`, verificado contra stdnum) + validación
      por tipo de documento en `SaveCustomerRequest`.
- [x] `SaveCustomer`: consumidor final (`0`/`00000000`) no se crea a
      mano ni se edita; duplicado = error amigable (savepoint aísla la
      violación UNIQUE de la transacción exterior).
- [x] `/customers` (Index/Form) con `customers.view/manage`; seed:
      consumidor final + DNI + RUC demo.

El diálogo desde el ticket (POS) llega con V5; V2 entrega el registro
esencial. Checks verdes 2026-08-23: 63 tests / 211 assertions.

---

## Endurecimiento pre-V3 (cerrado)

**Frase:** los invariantes fiscales ya escritos no mienten; PostgreSQL
guarda lo que el Action ya escribía bien.

- [x] `CdrOutcome` / `TicketResult` fail-closed: código no numérico ≠
      aceptación (`(int)'foo' === 0` ya no clasifica).
- [x] `LineComputation.igvBase`: UBL TaxSubtotal positivo también en
      `20`/`30`; el agregado gravado sigue sumando sólo `10`.
- [x] `ArtifactStore`: create-if-absent atómico; mismo SHA = replay;
      SHA distinto = error de integridad.
- [x] FK compuesta variante↔producto+emisor; `UNIQUE` establecimiento
      por emisor; CHECK de forma DNI/RUC; GRE OAuth exige secret.
- [x] Un staff de test comparte un `issuer` entre sus sedes.
      `phpunit.xml` clava `pgsql` / `necofact_test`.

No es V3. No hay compras, stock, caja ni `fiscal_*`. Checks 2026-08-24:
70 tests / 234 assertions, Pint, svelte-check, oxlint, Prettier, build.

---

## V3 — Compras (cerrado)

**Frase:** compro in_transit, recibo, entra stock con costo promedio.

- [x] `product_variants.cost_price`; `inventory_balances` sparse
      `(variant_code, branch_code)` con CHECK `on_hand >= 0`.
- [x] `purchases` + `purchase_items`: default `in_transit`;
      `PUR-000001` por emisor; UNIQUE variante por compra.
- [x] `RecordPurchase` / `ReceivePurchase` / `CancelPurchase`:
      receive lock + delta + promedio ponderado; cancel received resta;
      in_transit no mueve stock; producto inactivo no se opera.
- [x] `/purchases` Index/Form/Show y `/inventory` con
      `purchases.view/manage` e `inventory.view`. Sin caja ni GRE.

Checks 2026-08-24: 80 tests / 276 assertions, Pint, svelte-check, oxlint,
Prettier, build. `down()` de `000007` verde.

---

## V4 — Caja (en curso)

**Frase:** cada cajero conoce y opera su efectivo real; Yape, Plin, tarjeta y
transferencia bancaria no alteran esa posición.

- [ ] Ledger físico `cashbox_movements`; saldo derivado exacto por cajero;
      vista diaria con apertura, entradas, salidas, transferencias y cierre.
- [ ] Ingreso manual, gasto `OUT-`, Vuelto como hecho nuevo y transferencia
      `CTX-` con dos movimientos; fecha Lima del servidor e idempotencia.
- [ ] Reversiones con motivo, actor y timestamp; locks antes de toda salida y
      ninguna operación puede dejar una caja negativa.
- [ ] `/cashbox` entrega día, operaciones en rango y mes; `view/manage` propios
      y `supervise` de otro cajero sólo lectura, sin filtrar el saldo por sede.
- [ ] PostgreSQL protege montos, estados, dueños, direcciones, FKs y claves de
      solicitud; tests cubren replay, colisiones, concurrencia lógica y permisos.

No crea sesiones, apertura/cierre mutable, backdating, rendiciones, ventas ni
CPE. V5 enlazará sólo el monto `cash` de `sale_payments`; los demás medios nunca
entrarán al ledger.

---

## Después (orden V3→V5 estricto)

| V   | Frase                                                            |
| --- | ---------------------------------------------------------------- |
| 3   | Compro in_transit, recibo, entra stock con costo promedio        |
| 4   | Caja = ledger de efectivo (SPEC §4). Supervise; Yape no entra    |
| 5   | Vendió, cobró, reservó F001-n, firmó XML, CDR fake 0             |
| 6   | Boletas del día se informan con RC                               |
| 7   | El adquirente descarga XML/PDF de un link firmado                |
| 8   | F001-12 se corrige con NC; el XML original sigue                 |
| 9   | Nada queda en limbo; unknown se consulta, error reintenta el XML |
| 10  | T001 pide traslado; el stock lo movió Sale o Transfer            |
| 11  | Moví stock entre sedes una vez                                   |
| 12  | Un emisor de tercera categoría factura todos los días            |

Mecánica SQL y reglas: `docs/SPEC.md` §3–§4 (SEE contribuyente + laboratorio
beta; caja: fusión Faztore ledger + Coedula R-01 + Aeduca supervise/vuelto).
No competir esta spec. Red a SUNAT = V12 con emisor de tercera categoría,
no el RUC NRUS del desarrollador.
