# foundation.md — La ley del codebase (archivo)

> Archivo (2026-08-23). Referencia para V1–V12; la ley vigente vive en `SPEC.md` de la raíz.
> Nota: el nombre Composer correcto es `greenter/lite` (verificado en Packagist y vendor);
> la mención original `thegreenter/greenter` era un error corregido por la sesión V0.

Reglas `FDN-nn` que todo código de NecoFact obedece. Fuente: `plan.md` (decisiones),
`aeduca` (idiom Laravel exacto), `faztore` (invariantes SQL), `coedula`/`omr-rs`
(fronteras).

## FDN-0 — Tesis de arquitectura

Híbrido limpio, monolito modular, cero ceremonia. Prioridad (no invertir):
**correctness → readability → consistency → performance → simplicity.**
Prohibido: microservicio SUNAT, segundo runtime, package interno en v1, repositories
ceremoniales, CQRS, event sourcing, segunda UI.

## FDN-1 — Stack

PHP `^8.5` · Laravel `^13.8` · Inertia `^3.1` · Svelte `^5.56` + TS strict + Vite 8 ·
Lumi `file:../lumi-ui` · PostgreSQL (`pgcrypto`, `pg_trgm`) · `greenter/lite ^5.3` +
`gre-api ^1.0` + ext-soap/zlib/openssl/curl · queue/cache/session `database`,
broadcast `log` · PHPUnit 12 + Pint + svelte-check + oxlint + prettier · pnpm.
Scripts espejo de aeduca: `composer run check` = pint --test → artisan test →
`pnpm run check`. `composer run dev` = serve + queue:listen --tries=1 --timeout=0 +
pail + vite.

## FDN-2 — Layout

```
app/Actions/<Dominio>/   un write de agregado, final, handle()
app/Http/Controllers/    finales, delgados
app/Http/Requests/       forma
app/Jobs/                afterCommit
app/Models/              Eloquent mínimo
app/Support/Fiscal/      TaxMath, CpeState
app/Support/Sunat/       ÚNICO lugar que importa Greenter\*
app/Support/Pdf/         ÚNICO spawn de binario
resources/js/Pages/      Svelte 5 + Lumi only
tests/{Feature,Fiscal}/
```

Prohibido: `Services/` genérica, `Repositories/`, `Domains/`, `modules/`.

## FDN-3 — Fronteras

```text
App\Actions\Sales\*     dueño de stock, caja, venta
App\Actions\Fiscal\*    dueño de CPE/GRE, correlativo, XML, CDR
App\Support\Sunat\*     único importador de Greenter\*
App\Support\Pdf\*       único spawn del binario
```

`Greenter\*` no aparece en controllers, models públicos, Inertia, JSON ni
migrations. Gateways con interfaces propias (`CpeGateway`, `GreGateway`).

## FDN-4 — Semántica PHP

`final` en Actions/Controllers/Requests/Support/Jobs (Models no). Sin
`declare(strict_types)`. Promoción `private readonly`. Clases en inglés, copy UI en
español. Un Action = un `handle()`. Estados `public const string`. Enums en
`app/Support/<Dominio>/`. Fallo de dominio = `ValidationException` español. Commits
Conventional en inglés minúscula.

## FDN-5 — Idiom de datos

PK `code` UUID (`HasUuids`, `$primaryKey = 'code'`); FK `<entidad>_code`;
`#[Fillable]`; `casts()`; `immutable_datetime`; `decimal:2`. Migraciones prefijo
`0001_01_01_0000NN_` por dominio; `timestampTz`; constraints nombradas
(`<tabla>_<regla>_check`, `<tabla>_<cols>_uq`); `restrictOnDelete`; CHECKs vía
`DB::statement(<<<'SQL')`; `pg_trgm`+GIN; vistas SQL para read models. Sin soft
delete; sin arrays/JSON para relaciones; sin polimorfismo manual. `issuer_code` en
toda tabla de negocio; `SAL-`/`PUR-`/`TRF-` por secuencia LPAD 6.

## FDN-6 — Dinero e impuestos

Caja `NUMERIC(12,2)`+cents `Money`; cantidades `INTEGER`; líneas fiscales
`NUMERIC(16,6)` unitario y `NUMERIC(12,2)` bases/IGV/total. `TaxMath` único dueño
del IGV; half-up a 2 en línea, luego suma. Afectaciones v1: `10`/`20`/`30`. Nunca
float fiscal; el mapper formatea desde `NUMERIC` en el último milímetro.

## FDN-7 — Inventario (Faztore)

Sparse antes de mover; delta condicional `WHERE on_hand >= |Δ|`; 0 filas = error;
receive/cancel con `lockForUpdate`; costo promedio global de la variante; transfer
1 variante origen≠destino, inmutable con GRE.

## FDN-8 — Caja (Faztore + R-01)

Ledger append-only: `movement_type/direction/status` + CHECKs de alineación. Saldo
derivado. `business_date` hoy Lima del servidor. Antes de salidas:
`pg_advisory_xact_lock(hashtext('necofact.cashbox.cashier'), hashtext(:cashier))`.
Reversión = flip + timestamps + actor. `/cashbox` sólo efectivo físico.

## FDN-9 — Idempotencia (Coedula)

`request_key` UUID + fingerprint `sha256(JSON([operación, …partes]))`. En la
transacción: advisory lock por key → probe `UNION ALL … LIMIT 2` → sin fila inserta;
misma fingerprint replay; distinta → 409. Backstop: `UNIQUE (issuer_code,
request_key)`. Idempotencia de la venta, no del SOAP.

## FDN-10 — Correlativo

`SELECT … FOR UPDATE` + `next_number+1` + INSERT con número reservado; UNIQUE como
última barrera. Huecos quedan. Series F/B/T por sede; RC/RA `{ruc}-{RC|RA}-{Ymd}-{n}`.

## FDN-11 — Máquina CPE

reserved→signed→queued→submitted→{accepted|observed|rejected|transport_error|unknown};
queued→pending_summary→in_summary→accepted; unknown→vía ConsultCdr;
transport_error→submitted mismo XML; accepted→corrected. `accepted ↛ reserved|signed`.
`manual_review` ortogonal.

## FDN-12 — Binario PDF (omr-rs)

`PDF_BINARY_PATH`/`PDF_TIMEOUT_MS`/`PDF_MAX_CONCURRENCY`/`PDF_MAX_QUEUE`;
spawn shell:false; stdin JSON; stdout JSON con echo de id y códigos cerrados;
caps 1 MiB/64 KiB; timeout→SIGKILL; workdir 0700, archivos 0600 'wx', cleanup
finally; gate de concurrencia; caller revalida todo; nunca en el request del POS;
DTO sin certificado/SUNAT/dominio.

## FDN-13 — HTTP/Inertia/permisos

FormRequest → Action → Controller compone Inertia. `middleware('can:dominio.view')`
por grupo; `Gate::before` único resolver; `can()` frontend UX-only. Props arrays
planos; dinero string; `paginate(PAGE_SIZE)->withQueryString()`;
`Inertia::flash` + `to_route`. `app.d.ts` aumenta `InertiaConfig`.

## FDN-14 — Frontend

Svelte 5 runes + TS strict + `verbatimModuleSyntax`. Sólo Lumi público y tokens.
`Pages/<Dominio>/Index|Create` + `components/`; `types/` espejo; alias `@/`. Copy
español. Sin rutas vacías. POS: `01|03` antes de confirmar; key idempotencia al
abrir el ticket; badge SUNAT por poll.

## FDN-15 — Cola

Driver `database`; dispatch afterCommit; jobs idempotentes (no-op en terminal);
backoff con techo → `manual_review`; `queue:listen --tries=1 --timeout=0` en dev.

## FDN-16 — Testing

PHPUnit + RefreshDatabase contra `necofact_test`; TestCase con builders. Feature:
venta boleta+efectivo, factura+RUC+mixto, doble POST idempotente. Golden XML
fixtures. Transporte con fakes (CDR 0/4000/2000, fault, refused, vacío, timeouts,
98→0, GRE 422). Dominio: TaxMath, receptor, NRUS, correlativo 20 paralelos, VoidSale
gate, caja, compras. CI sin red a SUNAT.

## FDN-17 — Disciplina documental

Código > foundation > requirements > product > plan > task. Ningún documento declara
implementado lo que el código no contiene. Docs en el mismo commit que el modelo.
