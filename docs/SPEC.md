# NecoFact — especificación

Ley permanente de producto, dominio, datos y SUNAT. Hechos de código:
[`STATUS.md`](STATUS.md). Ejecución: `TASK.md` en la raíz.

Verificado 2026-08-23: Laravel 13.26 / PHP 8.5 · Greenter `lite` **5.3.0** ·
guías CPE SUNAT (mod. 24-jul-2026; reglas 30-jul-2026) · endpoints de
[orientación SUNAT](https://orientacion.sunat.gob.pe/guias-manuales-y-servicios-web)
· Catálogos 01/03/06/07 vigentes en UBL 2.1 · UNSPSC Cat. 25 aplazado al
**1-ene-2027** (fuera de v1).

El código manda sobre este archivo. Este archivo manda sobre `TASK.md`.

---

## 1. Producto

NecoFact es el sistema de ventas del cliente. El CPE es el centro, no un
printout del movimiento de stock.

**Es:** POS, catálogo, inventario, compras, caja de efectivo, ciclo fiscal
(`01` factura, `03` boleta, `07` nota de crédito, RC/RA, GRE remitente `09`).

**No es:** plugin SUNAT para Faztore/Aeduca, ERP, CRM, POS que “imprime
factura”, SaaS multi-RUC, microservicio, SIRE/RVIE, percepción/retención/
detracción, ND `08` con UI, GRE transportista, sesiones de caja abrir/cerrar.

Despliegue v1: **1 RUC · 1 deployment · N sedes.** Schema con `issuer_code`.
Repo privado. El software no se registra ante SUNAT.

### Tesis

`Sale` y `FiscalDocument` son agregados distintos (`1 — 0..n`). Anular una
venta no borra un XML aceptado. El cliente es identidad tributaria: el CPE
lee su snapshot, nunca la fila mutable. La caja es ledger de **efectivo
físico**. GRE no es una transferencia. El POS elige `01` o `03` antes de
confirmar. El correlativo se reserva una vez. Un timeout no es un rechazo.

| Actor      | Usa                                  | No usa                 |
| ---------- | ------------------------------------ | ---------------------- |
| Cajero     | POS, clientes en diálogo, caja       | series, retries        |
| Supervisor | catálogo, compras, `/cpe`, settings  | secretos en plano      |
| Contador   | `/cpe`, XML/CDR/PDF, huecos de serie | edición de operaciones |
| Adquirente | `/c/{token}` sin login               | el resto               |

### Disciplina heredada (no el producto)

Prioridad, no invertir: **correctness → readability → consistency →
performance → simplicity.**

| Origen   | Copiar                                                                             | Descartar                                                            |
| -------- | ---------------------------------------------------------------------------------- | -------------------------------------------------------------------- |
| Aeduca   | Laravel 13, Actions, FormRequests, Inertia, Svelte 5, Lumi, AuthAccount            | pagos académicos, `VoidPayment` = efecto SUNAT                       |
| Faztore  | stock sparse, deltas, compra `in_transit`, caja user-scoped, locks                 | cliente sin doc, un medio de pago, void = baja, `Number()`, `UUID[]` |
| Coedula  | service sólo si orquesta, caja = efectivo, `request_key`, spawn aislado (`omr-rs`) | runtime Node                                                         |
| Greenter | UBL + firma + SOAP/REST                                                            | `$see->send($doc)` en retry, `report`, `htmltopdf`                   |

Evidencia local para copiar invariantes (no pegar Kysely):

- stock: `faztore/src/lib/server/repositories/inventory.repository.ts`
  (`ensureInventoryBalanceRow`, `applyInventoryDeltaByVariant`)
- caja ledger: `faztore/.../cashbox.repository.ts` + `database/init/01-tables.sql` (`cashbox_movements`)
- caja permisos/void/vuelto: `aeduca/docs/SPEC.md` §6 + `RecordCashOutflowReturn`
- caja = efectivo hoy: `coedula/docs/PRODUCT.md` R-01
- idempotencia: `coedula/.../cashbox-repository.internal.ts`
  (`resolveCashboxRequestReplay`)
- spawn aislado: `coedula/src/lib/server/services/omr-engine.service.ts`
  (tmpdir, timeout, tope de concurrencia). El binario externo de Coedula
  es el OMR; sus PDF son pdf-lib in-process. NecoFact aplica el patrón de
  aislamiento a su binario de PDF fiscal.
- idiom Laravel: `aeduca/app/Actions`, `aeduca/AGENTS.md`

---

## 2. Stack, layout, convenciones

| Capa    | Pin                                                                         |
| ------- | --------------------------------------------------------------------------- |
| PHP     | `^8.5`                                                                      |
| Laravel | `^13.17` (framework 13.26.x)                                                |
| Inertia | `inertiajs/inertia-laravel ^3.1` + `@inertiajs/svelte ^3.6`                 |
| Front   | Svelte 5 runes · TS strict · Vite 8 · Lumi `file:../lumi-ui`                |
| DB      | PostgreSQL 18, `pgcrypto`, `pg_trgm`                                        |
| Fiscal  | `greenter/lite ^5.3` + `greenter/gre-api ^1.0` + ext-soap/zlib/openssl/curl |
| Math    | `brick/math ^0.18` (directo; TaxMath)                                       |
| Cola    | queue/cache/session `database`; broadcast `log`                             |
| QA      | PHPUnit 12 · Pint · svelte-check · oxlint · prettier · pnpm                 |

```
app/Actions/<Dominio>/     un write de agregado, final, handle()
app/Http/Controllers/      autorizan, componen, Inertia
app/Http/Requests/         forma
app/Jobs/                  afterCommit
app/Models/                Eloquent mínimo; invariantes en SQL
app/Support/Fiscal/        TaxMath, CpeState
app/Support/Sunat/         ÚNICO import de Greenter\*
app/Support/Pdf/           ÚNICO spawn del binario
resources/js/Pages/        Svelte 5 + Lumi only
```

Prohibido: `Services/` genérica, `Repositories/`, `Domains/`, `modules/`,
CQRS, event sourcing, segunda UI, microservicio SUNAT, package Composer
interno en v1.

- `final` en Actions/Controllers/Requests/Support/Jobs. Models **no**.
- Sin `declare(strict_types=1)` (mismo runtime que Aeduca).
- PK `code` UUID; FK `<entidad>_code`; `#[Fillable]`; `timestampsTz`.
- Relaciones con FK explícita. Nunca `UUID[]`.
- `LimaDate::TIMEZONE` es la única constante de zona (`America/Lima`).
  `config/app.php` y el storage fiscal la reutilizan. El cliente no envía
  fechas de negocio.
- Permisos `dominio.view` / `dominio.manage` vía un `Gate::before`.
  `can()` del frontend es UX-only.
- Dinero: caja `NUMERIC(12,2)` + `Money` (céntimos); cantidades `INTEGER`;
  líneas fiscales `NUMERIC(16,6)` unitario y `NUMERIC(12,2)` bases/IGV.
  `TaxMath` es el único dueño del IGV. Afectaciones v1: `10` `20` `30`.
  Half-up a 2 en la línea, luego suma. Nunca `float` como fuente.
- Jobs `afterCommit`. Idempotentes: no-op si el estado ya es terminal.
- CI sin red a SUNAT.

Dueños:

```
Actions\Sales\*      stock, caja, venta
Actions\Fiscal\*     CPE/GRE, correlativo, XML, CDR
Support\Sunat\*      Greenter
Support\Pdf\*        binario
```

El mapper FiscalDocument → modelo Greenter vive en `Support/Sunat`.
`signXml` firma XML ya construido. Nadie expone `See`.

---

## 3. Contrato SUNAT

NecoFact es el [SEE del contribuyente](https://cpe.sunat.gob.pe/sistema_emision/see_contribuyente):
el emisor genera UBL, firma y envía desde este sistema. No es SEE-SOL, no
es Facturador SUNAT, no es OSE, no es PSE.

SUNAT autentica al **contribuyente emisor** (RUC + Clave SOL + certificado;
GRE añade `client_id`/`client_secret` emitidos en SOL). No autentica al
programador ni al repositorio. No hay cuenta “developer NecoFact”.

Producción (emisor, no el código): Clave SOL; calidad de emisor electrónico
por designación o elección en SOL; domicilio habido; RUC activo; afecto a
renta de **tercera categoría** si genera esa renta. Conservar XML, notas,
RC/RA y CDR; poner el CPE a disposición del adquirente un año
(`/c/{token}`).

Paquete: **`greenter/lite` 5.3.0**. Packagist `thegreenter/greenter` no existe.
Camino:

```
mapper → See::getXmlSigned → persistir XML+sha256 → See::sendXmlFile
```

`$see->send($model)` regenera XML: prohibido en retry y en producción.
SOAP de GRE está deprecado: GRE va por `Greenter\Api` REST.

### Laboratorio de desarrollo

CI y `composer run check` **nunca** llaman a SUNAT.

| Capa                     | Cubre                                                             | No cubre                        |
| ------------------------ | ----------------------------------------------------------------- | ------------------------------- |
| Tests + `FakeCpeGateway` | dominio, TaxMath, CpeState, tickets 98/0/99, XML inmutable        | red                             |
| Beta SOAP                | estructuras XML de `01` `03` `07` `08`; certificado no registrado | GRE; validez tributaria; stress |
| Producción               | CPE + GRE con efecto tributario                                   | el día a día de desarrollo      |

Beta oficial
([pautas](https://orientacion.sunat.gob.pe/12-pautas-servicio-beta)):
usuario `{RUC}MODDATOS`, password `MODDATOS`. El RUC del XML de prueba
puede ser el del desarrollador: es identidad de laboratorio, no emisión
real. `config/sunat.beta` guarda el par. Homologación es **otro** host
(`ol-ti-itcpgem-sqa`); no mezclar.

Un RUC **NRUS** no es el laboratorio de este producto:

- boleta NRUS = SEE-SOL portal
  ([gob.pe/7010](https://www.gob.pe/7010-comprobantes-que-emites-en-el-nrus-emitir-boleta-de-venta-electronica-en-el-nuevo-rus)),
  no Greenter;
- NRUS no emite factura
  ([gob.pe/1153](https://www.gob.pe/1153-superintendencia-nacional-de-aduanas-y-de-administracion-tributaria-comprobantes-que-emites-en-el-nrus));
- CDT y GRE documentados piden tercera categoría
  ([CDT](https://www.gob.pe/26402-certificado-digital-tributario-cdt),
  [GRE](https://www.gob.pe/26296-guia-de-remision-electronica-gre));
- RUC que empieza en `10` no es NRUS; RUC `20` no es requisito de
  factura. Emitir `01` = `issuers.tax_regime` ≠ `NRUS` y
  `capabilities.invoice`.

Primera red de CPE: **beta**. GRE y validez tributaria: emisor de tercera
categoría en V12. No rediseñar `.env` a multi-emisor en v1.

### Credenciales (env, no `issuers`)

`issuers.environment` y `SUNAT_ENVIRONMENT`: `beta` \| `production`.
PEM, Clave SOL y OAuth GRE viven en env. Un cert y un SOL por deployment.
El software no tiene certificado propio.

| Variable                                | Dueño                                                                            |
| --------------------------------------- | -------------------------------------------------------------------------------- |
| `SUNAT_CERTIFICATE_PATH`                | PEM del emisor sin contraseña; el p12 se convierte fuera de línea. Firma el XML. |
| `SUNAT_SOL_RUC` / `_USER` / `_PASSWORD` | Clave SOL del emisor                                                             |
| `SUNAT_GRE_CLIENT_ID` / `_SECRET`       | aplicación registrada en SOL por ese RUC, no Greenter                            |

### Endpoints (`config/sunat.php`, nunca Actions)

| Uso           | URL                                                                    |
| ------------- | ---------------------------------------------------------------------- |
| FE beta       | `https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService`         |
| FE producción | `https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService`           |
| Consulta CDR  | `https://e-factura.sunat.gob.pe/ol-it-wsconscpegem/billConsultService` |
| Homologación  | `https://www.sunat.gob.pe/ol-ti-itcpgem-sqa/billService`               |
| GRE auth      | `https://api-seguridad.sunat.gob.pe/v1`                                |
| GRE cpe       | `https://api-cpe.sunat.gob.pe/v1`                                      |

`ol-ti-itemision-otroscpe-gem` es **otros CPE** (retención/percepción), no
factura.

### Catálogos v1

| Cat. | Uso                    | Valores v1                                  |
| ---- | ---------------------- | ------------------------------------------- |
| 01   | Tipo de comprobante    | `01` `03` `07` `09`                         |
| 03   | Unidad                 | `NIU` (default)                             |
| 06   | Doc. identidad         | `0` otros, `1` DNI, `6` RUC                 |
| 07   | Afectación IGV         | `10` gravado, `20` exonerado, `30` inafecto |
| 09   | Motivo NC              | al emitir `07`                              |
| 18   | Modalidad traslado GRE | `01` público, `02` privado                  |
| 20   | Motivo GRE             | `01` venta, `04` entre establecimientos     |

### CDR y tickets

`CdrResponse::isAccepted`: code `0` o `>= 4000`.

| CDR                         | Estado fiscal                      |
| --------------------------- | ---------------------------------- |
| 0, sin notes                | `accepted`                         |
| 0 con notes, o `>= 4000`    | `accepted_with_observations`       |
| presente y `!isAccepted`    | `rejected` (típicamente 2000–3999) |
| `applicationResponse` vacío | `transport_error` / `unknown`      |

SOAP success ≠ aceptación. Timeout post-write → `ConsultCdrService`, nunca
un segundo correlativo. Rechazo de negocio: no retry del mismo XML.

Tickets RC/RA (SOAP `getStatus`) y GRE (`codRespuesta`):

| Código | `TicketResult` | Significado                                      |
| ------ | -------------- | ------------------------------------------------ |
| 98     | `pending`      | Sigue en proceso. Reencolar.                     |
| 0      | `completed`    | Proceso terminó. Leer el CDR.                    |
| 99     | `failed`       | Proceso con error. No aceptar. CDR puede existir |

Greenter trata 0 y 99 como “ya no es 98”. El negocio sigue en el CDR.

### Máquina CPE

```
reserved → signed → queued → submitted → accepted | accepted_with_observations
                              |            → rejected | transport_error | unknown
                              └→ pending_summary → in_summary → accepted (RC)
unknown → accepted|rejected vía ConsultCdr
transport_error → submitted (mismo XML)
accepted → corrected (NC 07 accepted)
```

`accepted ↛ reserved|signed`. `manual_review` es flag ortogonal.

### Series y correlativo

`F001` / `B001` / `T001` por sede. RC/RA `{ruc}-{RC\|RA}-{Ymd}-{n}`.
Huecos se explican, no se rellenan.

```sql
SELECT * FROM fiscal_series
 WHERE issuer_code = :i AND document_type = :t AND series = :s
 FOR UPDATE;
UPDATE fiscal_series SET next_number = next_number + 1 …;
INSERT INTO fiscal_documents (…, number = :reservado, status = 'reserved');
-- UNIQUE (issuer_code, document_type, series, number)
```

### Plazos (config, no constantes)

| Clave                                  | Default | Fuente 2026-08-23                         |
| -------------------------------------- | ------- | ----------------------------------------- |
| `sunat.deadlines.invoice_send_days`    | 3       | R.S. 000003-2023/SUNAT (otras páginas: 7) |
| `sunat.deadlines.receipt_summary_days` | 7       | SEE Contribuyente / RS 000048-2026/SUNAT  |

Contrastar la guía vigente antes de go-live.

### Artefactos

`{issuer}/{yyyy}/{mm}/{tipo}-{serie}-{numero}/` en disco `fiscal`:
`signed.xml`, `cdr.zip`, `cdr.xml`, `representation.pdf`.
XML y CDR son inmutables (sha256 en DB). El PDF es regenerable y no es
evidencia fiscal.

---

## 4. Invariantes operativos (copiar al implementar)

SQL tomado de Faztore/Coedula y adaptado a NecoFact. No pegar Kysely.

### Inventario (Faztore)

Sparse `(variant_code, branch_code)`. `on_hand` entero. CHECK `>= 0`.

```sql
INSERT INTO inventory_balances (variant_code, branch_code)
VALUES (:v, :b)
ON CONFLICT (variant_code, branch_code) DO NOTHING;

UPDATE inventory_balances
   SET on_hand = on_hand + :delta
 WHERE variant_code = :v
   AND branch_code = :b
   AND (:delta >= 0 OR on_hand >= abs(:delta));
-- 0 filas → error de dominio
```

Compra: default `in_transit` (no toca stock). `receive` con `lockForUpdate`
de compra y variantes; costo promedio ponderado en `NUMERIC`. `cancel` desde
`received` resta; desde `in_transit` no mueve. Producto inactivo no se opera.

Transferencia: 1 variante, origen ≠ destino, inmediata. Inmutable si hay GRE
ligada. Corrección = transferencia compensatoria + GRE nueva.

### Caja

Fusión, no copia. Cada hermano aporta una pieza; NecoFact corrige lo que
rompe un facturador.

| De           | Qué entra                                                                                                                                                       | Qué no entra                                                                                                               |
| ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| Faztore      | Ledger único `cashbox_movements`, tipos, CHECKs de alineación, vista diaria, transferencias `CTX-`, caja del **cajero** no de la sede                           | Meter el **total** de la venta si hay un peso `cash`; `Number()`; `source_type`+`source_code` sin FK; `deleted` en egresos |
| Coedula R-01 | `/cashbox` = efectivo físico; escrituras = hoy Lima (servidor); `request_key`; UI no basta                                                                      | Obligaciones académicas; cobro mixto en la misma pantalla de caja                                                          |
| Aeduca       | `cashbox.view/manage/supervise`; selector de cajero read-only; Vuelto como **hecho nuevo**; void con motivo; no dejar saldo negativo; sede = metadato de origen | Ítems de pensión; `receipt_number` global como serie; sesiones abrir/cerrar                                                |

**Modelo**

- Un cajero = una posición continua. No hay entidad caja, ni abrir/cerrar, ni saldo mutable.
- El ledger es **tabla física** con FK y CHECK de alineación, no vista derivada. Coedula y Aeduca proyectan hechos con `UNION ALL`; aquí el movimiento es fact propio con PK, dueño y corrección. La lectura diaria sí es vista SQL.
- `cashbox_movements` es el libro. Saldo = `SUM` de `active` con `business_date <= hoy Lima`.
- Tipos: `sale` · `purchase` · `manual_income` · `expense` · `outflow_return` · `transfer`.
- Dirección `in`/`out`. Status `active`/`reversed`. Nunca DELETE.
- Cabeceras con número humano: `cash_outflows` (`OUT-`) y `cashbox_transfers` (`CTX-`).
- Vuelto: fila nueva `cash_outflow_returns` (como Aeduca) **y** movimiento `outflow_return` en el ledger. No mutar `returned_amount` en el egreso (Faztore lo hace; se descarta).
- FKs explícitas en el movimiento, no polimorfismo: `sale_code`, `purchase_code`, `outflow_code`, `transfer_code` nullable + CHECK de “exactamente el dueño que el tipo exige”. `manual_income` no referencia cabecera.
- `branch_code` en el movimiento es origen inmutable (la venta/compra ya tienen sede). La caja no se filtra por sede.

**Efectivo vs otros medios** — la regla que Faztore no expresa (un solo
medio por venta) y NecoFact sí necesita:

| Medio                           | `sale_payments` | `cashbox_movements`      |
| ------------------------------- | --------------- | ------------------------ |
| `cash`                          | sí              | sí, `in`, sólo ese monto |
| `yape` `plin` `transfer` `card` | sí              | no                       |
| mixto 40 efectivo + 20 Yape     | dos filas       | sólo 40                  |

El resumen diario de `/cashbox` muestra efectivo. El reporte de ventas muestra todos los medios. Nunca una sola cifra mezclada.

**Escrituras**

- `business_date` = hoy Lima, calculado por el servidor. El cliente no envía fecha. (Coedula R-01.)
- Toda salida toma el lock y luego verifica saldo. Void de un `in` también: no dejar la posición negativa.

```sql
SELECT pg_advisory_xact_lock(
  hashtext('necofact.cashbox.cashier'),
  hashtext(:cashier_user_code)
);
```

- Idempotencia `request_key` en egreso, vuelto y transferencia (misma mecánica que la venta).
- Egreso y transferencia **no** emiten CPE.
- No hay “rendición al superadmin” en v1: una transferencia entre cajeros cubre el efectivo que cambia de manos. (Aeduca/Coedula la tienen por cobro escolar; aquí sobra.)

**Permisos y UI (Aeduca, adaptado)**

- `cashbox.view` — hechos propios. `cashbox.manage` — escribe sólo los propios. `cashbox.supervise` — elige otro cajero **sólo lectura**. El selector vive en `/cashbox`; las hijas conservan `?cashier=` y jamás impersonan writes.
- Tres lecturas en la misma ruta: día (apertura / ingresos / egresos netos / cierre), operaciones en rango (default hoy), reporte mensual. Vista SQL `cashbox_daily_summary` al estilo Faztore (ingresos, egresos, transfer in/out, neto, running).
- Acciones de fila: detalle siempre; corrección contextual (un egreso no ofrece Vuelto y anular a la vez).
- Ticket 80 mm de caja es representación operativa (browser), no CPE. El binario PDF es del comprobante SUNAT.

**No copiar**

- Faztore `createSale`: `amount: roundMoney(totalAmount)` al ledger si hay `cashboxPayment`.
- Faztore `cash_outflows.status = 'deleted'`.
- Aeduca `payments` con conceptos de matrícula.
- Sesiones de caja, saldo en columna, backdating.

### Idempotencia (Coedula)

De la **venta**, no del SOAP. El cliente genera `request_key` UUID al abrir
el ticket. Fingerprint = `sha256(JSON.stringify([operación, …partes]))`.

```sql
SELECT pg_advisory_xact_lock(
  hashtext('necofact.request'),
  hashtext(:request_key)
);
-- probe UNION ALL … LIMIT 2 en tablas con key
-- 0 filas → insertar
-- 1 fila, mismo fingerprint → devolver (replay)
-- fingerprint distinto o 2 filas → 409
```

Backstop: `UNIQUE (issuer_code, request_key)`.

### Venta

`RecordSale` es **una** transacción DB: sale+items+payments, stock −, caja
sólo cash, lock de serie + snapshot fiscal, dispatch afterCommit. SUNAT
nunca dentro de la transacción. El backend recalcula totales; ignora el
total del frontend. Precios POS con IGV incluido.

`sale_number` (`SAL-` + secuencia) **nunca** es `F001-12`.
`Sale.status ∈ {posted, voided}` — máquina distinta de la fiscal.

`VoidSale`: permitido sin CPE, o CPE `rejected` / `cancelled_by_summary`.
Con CPE aceptado: NC `07` (otorgada) o baja RC/RA (no otorgada). NC
accepted **antes** de restaurar stock/caja.

Factura `01` exige receptor `doc_type='6'` RUC 11. Boleta ≥ 700 PEN exige
identidad real. Capacidad de emitir `01` = régimen del **emisor** distinto
de `NRUS` y `issuers.capabilities.invoice`, no el prefijo `10`/`20` del
RUC del cliente ni del emisor. Consumidor final: una fila por emisor,
`0` / `00000000`, no borrable.

`UNIQUE (sale_code, variant_code)`.

### GRE

REST + OAuth + `TokenStoreInterface` persistente. No mueve stock ni caja.
v1: motivo `01` (venta) y `04` (entre establecimientos). Ítems sin precios.

---

## 5. Superficie HTTP v1

`/sales/new` POS · `/sales` · `/sales/{code}` · `/customers` · `/products` ·
`/inventory` · `/purchases` · `/transfers` · `/cashbox` · `/cpe` ·
`/gre/new` · `/settings/issuer` · `/c/{token}`.

Confirmar venta = 200 con `sale.code` y fiscal `queued` \| `pending_summary`.
El badge SUNAT se actualiza por poll. El POS no espera a SUNAT.

V0 sólo tiene login, inicio y selector de sede. El resto abre con su vertical.

Orden: **V0→V5 estricto.** V6–V11 se reordenan sólo con necesidad de piloto
documentada en `TASK.md`.

| V   | Resultado observable                                           |
| --- | -------------------------------------------------------------- |
| 0   | login, sede, permisos, Greenter instalado (hecho)              |
| 1   | productos/variantes con precio y afectación                    |
| 2   | cliente DNI/RUC esencial, sin CRM                              |
| 3   | compra in_transit → receive entra stock y costo promedio       |
| 4   | caja = efectivo del cajero; Yape no entra                      |
| 5   | venta + factura: stock, caja, F001-n, XML persistido, CDR fake |
| 6   | boletas del día → RC → ticket                                  |
| 7   | PDF binario + `/c/{token}`                                     |
| 8   | NC `07`; VoidSale gated                                        |
| 9   | cola `/cpe`, reconcile, techo → `manual_review`                |
| 10  | GRE remitente                                                  |
| 11  | transferencia de stock; inmutable con GRE                      |
| 12  | settings emisor, PEM, piloto con emisor de tercera categoría   |

Un vertical está hecho cuando su usuario entra, opera, ve el resultado
persistido, y autorización/fallos son correctos — no cuando hay CRUD.
