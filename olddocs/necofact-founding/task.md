# task.md — Ledger de ejecución vertical (archivo)

> Archivo (2026-08-23). El ledger vivo es `TASK.md` en la raíz. Este documento se
> conserva por el detalle de tareas y criterios de hecho por vertical.

Un vertical por sesión. Cada vertical deja algo ejecutable y verificado. Criterios
referencian `requirements.md` (FR/NFR) y `foundation.md` (FDN).

## V0 — Fundación
Login, sedes, permisos, cola, Greenter instalado, check pipeline verde, tres docs.
**[Ejecutado 2026-08-23 por la sesión del agent coder — ver TASK.md raíz.]**

## V1 — Catálogo mínimo
- 1.1 Migración `products`/`product_variants` (FR-PRD-01) con CHECKs.
- 1.2 Actions + FormRequests + `/products`.
- 1.3 Seed demo (afectaciones 10/20/30).
**Hecho cuando:** Feature test crea/edita/inactiva; inactivar impide operar (FR-INV-03).

## V2 — Cliente esencial
- 2.1 Migración `customers` exacta (FR-CLI-01/02); consumidor final seedeado.
- 2.2 `UpsertCustomer` + búsqueda trgm + diálogo.
- 2.3 Reglas de emisión en Action (FR-CLI-04/05).
**Hecho cuando:** tests FR-CLI-02..05 verdes.

## V3 — Inventario + compras
- 3.1 `inventory_balances` sparse + CHECK + vista overview.
- 3.2 `purchases`/items/payments + CHECKs + `PUR-`.
- 3.3 `RecordPurchase`/`ReceivePurchase`/`CancelPurchase` (FDN-7).
- 3.4 Páginas `/inventory`, `/purchases`.
**Hecho cuando:** tests FR-INV-* y FR-COM-01..04.

## V4 — Caja ledger
- 4.1 `cashbox_movements`/`cash_outflows`/`cashbox_transfers` + alignment CHECKs.
- 4.2 Actions ingreso/egreso/transferencia/vuelto; idempotencia (FDN-9).
- 4.3 Página `/cashbox`; resumen efectivo vs medios (FR-CAJ-07).
**Hecho cuando:** egreso sin saldo falla bajo lock concurrente; Yape jamás en ledger.

## V5 — Venta + Factura E2E
- 5.1 `fiscal_series`/`fiscal_documents`(+items)/`idempotency_keys` (FDN-10, FR-FIS-14).
- 5.2 `TaxMath`+`Money` ya presentes; completar tests redondeo.
- 5.3 `Correlative` (lock) — `CpeState` ya presente.
- 5.4 Gateways ya presentes; agregar mapper FiscalDocument→Greenter DENTRO de
  `Support/Sunat` (frontera FDN-3).
- 5.5 `RecordSale` transacción única (FR-VEN-03) + afterCommit jobs.
- 5.6 `SendCpeJob`: XML persistido, CDR parse, submissions, timeout semantics.
- 5.7 POS `/sales/new` + `/sales` + detalle.
- 5.8 `ReconcileSunatStatus` + `ReconcileCpeJob`.
- 5.9 Golden fixture factura + suite transporte fake + concurrencia 20 paralelos.
**Hecho cuando:** E2E fake completo; timeout post-write no genera F001-(n+1).

## V6 — Boleta + RC
`daily_summaries`, `QueueDailySummary`, `PollTicketJob` (98/0/99), `/cpe` v1.
RC rechazado no acepta a medias. **Ojo:** 99 en ExtService es error, no completed.

## V7 — PDF + `/c/{token}`
`PdfEngine` real (FDN-12), `RenderPdfJob`, URL firmada, QR.

## V8 — NC `07`
`IssueCreditNote` + gating de `VoidSale` (FR-VEN-07/FR-FIS-09).

## V9 — Recuperación
`/cpe` completo, reconcile, `sunat:smoke` fuera de CI, alertas.

## V10 — GRE
`despatch_documents`, `GreenterGreGateway` ya presente + mapper + poll.

## V11 — Transferencia stock
`product_transfers` + inmutabilidad con GRE.

## V12 — Hardening
`/settings/issuer`, PEM restringido, alertas cert, runbook, piloto real.

## Reglas del ledger
1. Vertical cerrado = checkboxes + tests + `composer run check` + `pnpm run build`.
2. Un FR/NFR sin test no cuenta como hecho.
3. Hallazgos a `docs/STATUS.md`; cambios de regla primero a la ley.
4. Orden estricto V0→V5; V6–V11 reordenables sólo con necesidad documentada.
