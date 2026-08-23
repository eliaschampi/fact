# SPEC.md — Ley de NecoFact

Fuente de verdad permanente. Lo implementado vive en el código y se marca en
`TASK.md`. Este archivo no se reescribe por sesión.

Verificado contra internet el 2026-08-23: Laravel 13.26 (PHP ≥ 8.3, usamos 8.5),
Greenter `lite`/`core`/`ws`/`xml` **v5.3.0**, guías CPE SUNAT (mod. 24-jul-2026,
reglas 30-jul-2026), endpoints oficiales de orientación SUNAT.

---

## 1. Producto

NecoFact es el sistema de ventas del cliente. El CPE es el centro, no un
printout del movimiento de stock.

**Es:** POS, catálogo, inventario, compras, caja de efectivo, ciclo fiscal
completo (`01`, `03`, `07`, RC/RA, GRE `09`).

**No es:** integración SUNAT en Faztore/Aeduca/Coedula, microservicio,
SaaS multi-RUC, SIRE/RVIE, percepción/retención/detracción, ND `08` con UI,
GRE transportista, CRM, sesiones de caja abrir/cerrar.

### Tesis (una sola)

`Sale` y `FiscalDocument` son agregados distintos. Anular una venta no borra
un XML aceptado. El cliente es identidad tributaria (snapshot en el CPE).
La caja es ledger de efectivo físico: Yape/Plin/tarjeta no entran. GRE no es
una transferencia. El POS elige `01` o `03` antes de confirmar. El correlativo
se reserva una vez y no se devuelve. Un timeout no es un rechazo.

Despliegue v1: 1 RUC · 1 deployment · N sedes. Schema con `issuer_code`.

### De dónde se hereda disciplina (no el producto)

| Origen   | Qué se copia                                                                            | Qué se descarta                                                                                   |
| -------- | --------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| Aeduca   | Laravel 13, Actions, FormRequests, Inertia, Svelte 5, Lumi, AuthAccount, Gate único     | Pagos académicos, alumnos, `VoidPayment` como efecto SUNAT                                        |
| Faztore  | Stock sparse, deltas condicionales, compra in_transit, caja user-scoped, advisory locks | Cliente sin doc tributario, un solo medio de pago, void = baja SUNAT, `Number()` fiscal, `UUID[]` |
| Coedula  | Service sólo si orquesta, caja = efectivo, `request_key`, binario PDF                   | Runtime Node                                                                                      |
| Greenter | UBL + firma + SOAP/REST                                                                 | `$see->send($doc)` en retry, `greenter/report`, `htmltopdf`, `laravel-greenter`                   |

Prioridad de ingeniería, no invertir: **correctness → readability → consistency → performance → simplicity.**

---

## 2. Stack y layout

| Capa               | Pin                                                                                             |
| ------------------ | ----------------------------------------------------------------------------------------------- |
| PHP                | `^8.5`                                                                                          |
| Laravel            | `^13.17` (skeleton 13.10 / framework 13.26.x)                                                   |
| Inertia            | `inertiajs/inertia-laravel ^3.1` + `@inertiajs/svelte ^3.6`                                     |
| Front              | Svelte 5 runes · TypeScript strict · Vite 8 · Lumi `file:../lumi-ui`                            |
| DB                 | PostgreSQL 18, `pgcrypto`, `pg_trgm`                                                            |
| Fiscal             | `greenter/lite ^5.3` + `greenter/gre-api ^1.0` + `ext-soap` `ext-zlib` `ext-openssl` `ext-curl` |
| Cola/cache/session | `database` / `database` / `database`; broadcast `log`                                           |
| QA                 | PHPUnit 12 · Pint · svelte-check · oxlint · prettier                                            |

```
app/Actions/<Dominio>/     un write de agregado, final, handle()
app/Http/Controllers/      autorizan, componen, Inertia
app/Http/Requests/         forma
app/Jobs/                  afterCommit (V5+)
app/Models/                Eloquent mínimo; invariantes en SQL
app/Support/Fiscal/        TaxMath, CpeState
app/Support/Sunat/         ÚNICO lugar que importa Greenter\*
app/Support/Pdf/           ÚNICO spawn de binario
resources/js/Pages/        Svelte 5 + Lumi only
```

Prohibido: `Services/` genérica, `Repositories/`, `Domains/`, `modules/`,
CQRS, event sourcing, segunda UI, microservicio SUNAT, package Composer
interno `necofact/sunat` en v1.

---

## 3. Convenciones de código

- Clases `final` en Actions/Controllers/Requests/Support/Jobs. Models **no** final.
- `declare(strict_types=1)` **no** se usa (mismo runtime que Aeduca).
- Promoción `private readonly`. Nombres de clase en inglés. Copy de UI en español.
- Un Action = un `handle()`. Fallo de dominio = `ValidationException` en español.
- PK `code` UUID (`HasUuids`). FK `<entidad>_code`. `#[Fillable]`. `timestampsTz`.
- Dinero de caja: `NUMERIC(12,2)` + `Money` (céntimos). Líneas fiscales:
  `NUMERIC(16,6)` unitario, `NUMERIC(12,2)` bases/IGV/total. Nunca `float` como fuente.
- `TaxMath` es el único dueño del IGV. Afectaciones v1: `10` `20` `30`.
  Redondeo half-up a 2 en la línea, luego suma.
- Timezone de negocio: `LimaDate::TIMEZONE` (`America/Lima`). Es la única
  constante; `config/app.php` y el storage fiscal la reutilizan. El cliente
  no envía fechas de negocio.
- Permisos `dominio.view` / `dominio.manage` vía `Gate::before` + `PermissionResolver`.
  `can()` del frontend es UX-only.
- Svelte 5 runes. Sólo tokens `--lumi-*`. Sin tabs/rutas vacías.

---

## 4. Contrato SUNAT

Paquete: **`greenter/lite` 5.3.0**, no `thegreenter/greenter` (ese nombre de
Packagist no existe). El starter oficial enseña `$see->send($invoice)`.
**Ese no es el camino de NecoFact.**

```
mapper → See::getXmlSigned → persistir XML+sha256 → See::sendXmlFile
```

`$see->send($model)` está prohibido en producción porque regenera XML.
SOAP de GRE está deprecado: GRE va por `Greenter\Api` REST.

### Endpoints (config, no Actions)

Fuente: [Servicios Web SUNAT](https://orientacion.sunat.gob.pe/guias-manuales-y-servicios-web)
y `Greenter\Ws\Services\SunatEndpoints` v5.3.0.

| Uso           | URL                                                                    |
| ------------- | ---------------------------------------------------------------------- |
| FE beta       | `https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService`         |
| FE producción | `https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService`           |
| Consulta CDR  | `https://e-factura.sunat.gob.pe/ol-it-wsconscpegem/billConsultService` |
| Homologación  | `https://www.sunat.gob.pe/ol-ti-itcpgem-sqa/billService`               |
| GRE auth      | `https://api-seguridad.sunat.gob.pe/v1`                                |
| GRE cpe       | `https://api-cpe.sunat.gob.pe/v1`                                      |

Otra página oficial publica `ol-ti-itemision-otroscpe-gem` — es el servicio
de **otros CPE** (retención/percepción), no el de factura. Vive en
`config/sunat.php` como `otros_cpe`.

Beta: `MODDATOS` / `moddatos`. No stress test. No datos reales.

### CDR (`CdrResponse::isAccepted`)

```
accepted                   code 0, sin notes
accepted_with_observations code 0 con notes, o code >= 4000
rejected                   CDR presente y !isAccepted (típicamente 2000–3999)
```

SOAP success ≠ aceptación. `applicationResponse` vacío → `transport_error` /
`unknown`, no rechazo. Timeout post-write → `ConsultCdrService`, nunca un
segundo correlativo. Rechazo de negocio: no retry del mismo XML.

### Plazos (config documentada, no constantes mágicas)

| Clave                                  | Default | Fuente contrastada 2026-08-23                                |
| -------------------------------------- | ------- | ------------------------------------------------------------ |
| `sunat.deadlines.invoice_send_days`    | 3       | R.S. 000003-2023/SUNAT (otras páginas oficiales aún dicen 7) |
| `sunat.deadlines.receipt_summary_days` | 7       | SEE Contribuyente / RS 000048-2026/SUNAT                     |

Contrastar la guía vigente antes de go-live. El número no se hardcodea en TaxMath.

### Máquina CPE

```
reserved → signed → queued → submitted → accepted | accepted_with_observations
                              |            → rejected | transport_error | unknown
                              └→ pending_summary → in_summary → accepted (RC)
unknown → accepted|rejected vía consulta CDR
transport_error → submitted (mismo XML)
accepted → corrected (NC 07 accepted)
```

`accepted ↛ reserved|signed`. `manual_review` es flag ortogonal.

Ticket de RC/RA (SOAP `getStatus`) y GRE REST (`codRespuesta`) usan los
mismos códigos de **proceso**, no de aceptación del comprobante:

| Código | TicketResult | Significado                                                        |
| ------ | ------------ | ------------------------------------------------------------------ |
| 98     | `pending`    | Sigue en proceso. Reencolar.                                       |
| 0      | `completed`  | Proceso terminó. Leer el CDR.                                      |
| 99     | `failed`     | Proceso terminó con error. No aceptar. El CDR puede existir igual. |

`99` **no** es aceptación. Greenter `ExtService::isProcessed` y
`GreSender::processResponse` tratan 0 y 99 como “ya no está en 98”; el
resultado de negocio sigue en el CDR / nodo error.

El mapper FiscalDocument → modelo Greenter vive en `Support/Sunat`. Las
Actions nunca importan `Greenter\*`. El gateway firma XML ya construido
(`signXml`); no expone `See`.

### Series

`F001` / `B001` / `T001` por sede. RC/RA `{ruc}-RC/RA-{Ymd}-{n}`.
`UNIQUE (issuer_code, document_type, series, number)`. Huecos se explican, no se rellenan.

### GRE

REST, OAuth propio, `TokenStoreInterface` persistente (Greenter trae
`InMemoryStore`, insuficiente). `codRespuesta` 98 pending, 0 completed, 99
failed (`envío con error`). GRE no mueve stock ni caja.

Secretos (PEM, Clave SOL, GRE OAuth) viven en env/secret store, no en
`issuers`. `issuers.environment` es `beta` o `production`, el mismo
vocabulario que `SUNAT_ENVIRONMENT`. Punteros a secretos llegan en V12.

---

## 5. Invariantes operativos (V1+, ya decididos)

- Inventario sparse `(variant_code, branch_code)`. `INSERT … ON CONFLICT DO NOTHING`
  luego delta condicional. `CHECK (on_hand >= 0)`.
- Compra default `in_transit`. Receive con `FOR UPDATE` y costo promedio `NUMERIC`.
- Caja: ledger append-only, saldo derivado, `pg_advisory_xact_lock` antes de
  salidas. Sólo `cash` escribe el ledger.
- Idempotencia de la **venta** (`Idempotency-Key`), no del SOAP.
- Snapshot fiscal del receptor; editar `customers` no reescribe CPE.
- Factura `01` exige RUC 11. Boleta ≥ 700 PEN exige identidad. Capacidad de
  emitir `01` = régimen del **emisor**, no prefijo del RUC del cliente.
- PDF = binario externo, nunca en el request del POS. Si el binario falla, el
  CPE sigue válido.

---

## 6. Superficie HTTP v1 (no construir de golpe)

`/sales/new` (POS) · `/sales` · `/customers` · `/products` · `/inventory` ·
`/purchases` · `/transfers` · `/cashbox` · `/cpe` · `/gre/new` ·
`/settings/issuer` · `/c/{token}`.

V0 sólo tiene login, inicio y selector de sede. El resto se abre con su vertical.
