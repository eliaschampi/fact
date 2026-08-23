# TASK.md — vertical activo

Único archivo que se muta cada sesión. Un vertical por sesión. Un criterio
sin test no cuenta como hecho. Ley: `docs/SPEC.md`. Hechos: `docs/STATUS.md`.

## Estado

| Campo                | Valor           |
| -------------------- | --------------- |
| Fase                 | V0 cerrado      |
| Siguiente sesión     | **V1 Catálogo** |
| Última actualización | 2026-08-23      |

---

## V0 — Fundación (cerrado)

Login, sede, permisos, Greenter 5.3, TaxMath, CpeState, adapters SUNAT.
Checks verdes. Detalle en `docs/STATUS.md`.

---

## V1 — Catálogo (siguiente)

**Frase:** hay productos vendibles con precio y afectación, por sede.

Leer `docs/SPEC.md` §2 (layout) y §4 catálogo/inventario. Evidencia:
Faztore `product_variants` (sku, precio, no stock en el producto).

- [ ] Migración `products` + `product_variants`: `unit_code` Cat. 03 default
      `NIU`; `tax_affectation` Cat. 07 default `'10'`; precios `decimal(12,2)`;
      sku único lower; `issuer_code`.
- [ ] Actions + FormRequests + `/products` (Index/Form) con
      `products.view` / `products.manage`.
- [ ] Seed demo: 3 productos, afectaciones 10 / 20 / 30.
- [ ] Feature test: crear/editar/inactivar; inactivo no se opera en el Action.

**Hecho cuando:** el usuario entra a `/products`, registra una variante
gravada, la ve en el listado, y un test impide operar la inactiva.
`composer run check` y `pnpm run build` verdes. `docs/STATUS.md` actualizado.

---

## Después (orden V2→V5 estricto)

| V   | Frase                                                            |
| --- | ---------------------------------------------------------------- |
| 2   | El cajero registra DNI/RUC sin salir del ticket                  |
| 3   | Compro in_transit, recibo, entra stock con costo promedio        |
| 4   | La caja es el efectivo del cajero; Yape no entra                 |
| 5   | Vendió, cobró, reservó F001-n, firmó XML, CDR fake 0             |
| 6   | Boletas del día se informan con RC                               |
| 7   | El adquirente descarga XML/PDF de un link firmado                |
| 8   | F001-12 se corrige con NC; el XML original sigue                 |
| 9   | Nada queda en limbo; unknown se consulta, error reintenta el XML |
| 10  | T001 pide traslado; el stock lo movió Sale o Transfer            |
| 11  | Moví stock entre sedes una vez                                   |
| 12  | Un cliente real factura todos los días                           |

Mecánica SQL y reglas: `docs/SPEC.md` §3–§4. No reabrir archivos
archivados ni competir esta spec.
