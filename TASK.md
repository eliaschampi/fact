# TASK.md — Ledger de ejecución

Único archivo que se muta en cada sesión. Un vertical por sesión. Un criterio
no cubierto por test no cuenta como hecho.

## Estado

| Campo                | Valor                                 |
| -------------------- | ------------------------------------- |
| Fase                 | **V0 Fundación** — codebase arrancado |
| Siguiente sesión     | **V1 Catálogo**                       |
| Última actualización | 2026-08-23 (V0 hardening)             |

Verificado en esta sesión: Laravel 13.26.1, PHP 8.5.8, Greenter lite **5.3.0**,
Inertia 3.3.1, PostgreSQL 18.4, endpoints SUNAT oficiales (ver `SPEC.md` §4).

Documentos de brainstorm archivados en `olddocs/necofact-founding/` (no son
autoridad). Quedan `README.md`, `SPEC.md`, este archivo.

---

## V0 — Fundación

**Frase:** arrancó el sistema: hay login, sedes, permisos, cola y Greenter.

- [x] 0.1 `laravel/laravel` 13.10 + framework 13.26 · PHP `^8.5` · Inertia 3 ·
      `.env` `es` / `es_PE` / `America/Lima`
- [x] 0.2 `.gitignore` cubre `.env`, `.env.testing`, vendor, node_modules, storage
- [x] 0.3 `config/sunat.php` endpoints + plazos documentados · disco `fiscal` ·
      queue/cache/session `database` · `LimaDate`
- [x] 0.4 Migración auth+org+issuer (UUID `code`, `user_branches` explícito,
      `branches.sunat_establishment_code`) · seed de permisos
- [x] 0.5 Login Inertia + `Gate::before` + PermissionResolver + selector de sede
- [x] 0.6 Svelte 5 + TS strict + Lumi `file:../lumi-ui` + alias `@/`
- [x] 0.7 Greenter `lite` 5.3.0 + `gre-api` 1.0.2 · `ext-soap/zlib/openssl/curl` ·
      `sunat:diagnose` (sin red)
- [x] 0.8 PHPUnit contra `necofact_test` · TestCase builders · tests login/permisos/
      TaxMath/CpeState/CDR/config
- [x] 0.9 Tres documentos: `README.md` · `SPEC.md` · `TASK.md`

**Hecho cuando:** `composer run check` y `pnpm run build` verdes; V0 merged.

Núcleo fiscal ya presente (sin emisión): `Support/Sunat/*` (gateways, endpoints,
TokenStore persistente, ArtifactStore), `TaxMath`, `CpeState`, `FakeCpeGateway`
configurable. No hay `RecordSale` ni XML de producción todavía — eso es V5.

Hardening post-review (misma sesión V0): ticket `99` → `failed` (no accepted);
`queryTicket`/`queryCdr` no lanzan; `brick/math` declarado; ambiente
`beta`/`production` unificado; `LimaDate` dueño del timezone; secretos fuera
de `issuers`; `see()` removido; mapper queda en `Support/Sunat` (V5).

---

## Siguientes verticales (orden estricto V1→V5)

### V1 — Catálogo

Productos / variantes con `unit_code` (Cat. 03, default `NIU`) y
`tax_affectation` (Cat. 07, default `10`). `/products`. Seed 10/20/30.

### V2 — Cliente esencial

`customers` con Cat. 06, UNIQUE emisor+tipo+número, consumidor final
`0`/`00000000`. Factura exige RUC. Boleta ≥ 700 exige identidad.

### V3 — Inventario + compras

Balances sparse, compras `in_transit|received|cancelled`, costo promedio
`NUMERIC`, `FOR UPDATE` al recibir.

### V4 — Caja ledger

Sólo efectivo. Advisory lock. Reversión, nunca DELETE. Mixto: Yape no entra.

### V5 — Venta + Factura E2E

La transacción única: sale + stock + caja cash + correlativo + snapshot +
`SendCpeJob` afterCommit. `getXmlSigned` → persistir → `sendXmlFile`.
Golden fixture factura. Fake CDR 0. Timeout post-write no reserva F001-(n+1).

### V6 — Boleta + RC

`pending_summary` → resumen del día → ticket 98/0/99. RC rechazado no acepta
a medias.

### V7 — PDF + `/c/{token}`

`PdfEngine` spawn JSON. El CPE no depende del PDF.

### V8 — Nota de crédito `07`

`VoidSale` comercial sólo después de NC accepted.

### V9 — Recuperación

`/cpe`, `ReconcileCpeJob`, `sunat:smoke` fuera de CI, techo → `manual_review`.

### V10 — GRE remitente

REST + TokenStore (ya hay adapter). Motivo 01 y 04. No mueve stock.

### V11 — Transferencia de stock

Inmutable si hay GRE ligada.

### V12 — Hardening y piloto

`/settings/issuer`, PEM, alertas de certificado, beta `MODDATOS` → piloto real.

---

## Hallazgos V0 (no son desvíos de SPEC)

1. Packagist correcto: `greenter/lite` 5.3.0. `thegreenter/greenter` 404.
2. `greenter/lite` no declara `gre-api`; GRE REST se añade con
   `greenter/gre-api` ^1.0. `Greenter\Api` usa `InMemoryStore` por defecto;
   NecoFact inyecta `CacheTokenStore`.
3. Laravel 13 actual: framework **13.26.1**, PHP mínimo 8.3, usamos 8.5.8.
4. Guzzle bajó 8 → 7.15 por `gre-api`. Aceptable; no tocar hasta que Greenter
   declare Guzzle 8.
5. `sunat.md` del brainstorm proponía `app/Domains/` y estado `draft`.
   Rechazado: Actions + `reserved`, igual que el resto del SPEC.
