# requirements.md — Requisitos verificables

> Archivo (2026-08-23). Referencia para V1–V12; la ley vigente vive en `SPEC.md` de la raíz.

Enunciados cortos con ID estable. Cada uno es comprobable por un test, una
constraint o una revisión de código. La justificación profunda vive en `plan.md`.
Módulos: `PLT` fundación · `CLI` clientes · `PRD` productos · `INV` inventario ·
`COM` compras · `CAJ` caja · `VEN` ventas · `FIS` fiscal CPE · `GRE` guías ·
`PDF` representación · `SEG` secretos · `OBS` observabilidad.

## PLT — Fundación

- **FR-PLT-01** Auth con `AuthAccount`/`User` separados; sin segundo sistema de auth.
- **FR-PLT-02** `user_branches` como tabla intermedia explícita. Prohibido `UUID[]`.
- **FR-PLT-03** Permisos `dominio.view`/`dominio.manage` (+ `.delete` puntual) vía
  Gate con un único resolver; nunca autorizar por nombre de rol.
- **FR-PLT-04** Queue/cache/session `database`; broadcast `log`.
- **FR-PLT-05** Storage privado durable para artefactos fiscales.
- **FR-PLT-06** Toda fecha de negocio deriva de la fecha civil Lima calculada por
  el servidor; el cliente jamás envía fechas de negocio.
- **FR-PLT-07** Emisor único por deployment: `issuers` con `tax_regime`,
  `capabilities` JSONB, `environment`, referencias a secrets — nunca el secreto.
- **FR-PLT-08** `branches.sunat_establishment_code CHAR(4)` default `0000`.

## CLI — Clientes

- **FR-CLI-01** Campos mínimos: `doc_type` (Cat. 06), `doc_number`, `legal_name`,
  `address_line`, `ubigeo` + dep/prov/dist, `email`, `phone`, `is_active`.
- **FR-CLI-02** `UNIQUE (issuer_code, doc_type, doc_number)` + CHECK de formato
  (DNI 8, RUC 11).
- **FR-CLI-03** Consumidor final: una fila por emisor, `0`/`00000000`, no borrable.
- **FR-CLI-04** Factura `01` exige RUC 11. Boleta ≥ 700 PEN exige identidad real.
  Lo enforcea el Action, no sólo la UI.
- **FR-CLI-05** Capacidad de emitir `01` la define el régimen del emisor, nunca el
  prefijo del RUC del cliente.
- **FR-CLI-06** Editar un cliente no reescribe CPE históricos (snapshot).
- **FR-CLI-07** El POS puede crear el cliente dentro del ticket.

## PRD / INV — Catálogo e inventario

- **FR-PRD-01** SKU vendible = `product_variants` con `unit_code` (Cat. 03, default
  `NIU`) y `tax_affectation` (Cat. 07, default `'10'`).
- **FR-PRD-02** `UNIQUE (sale_code, variant_code)`; una variante no se repite.
- **FR-INV-01** `inventory_balances` sparse: `INSERT … ON CONFLICT DO NOTHING`
  antes de cualquier delta.
- **FR-INV-02** Delta condicional: `UPDATE … SET on_hand = on_hand + Δ WHERE
  on_hand >= |Δ|` cuando Δ<0; 0 filas = error de dominio; CHECK ≥ 0 de red.
- **FR-INV-03** Productos inactivos no se venden/compran/transferen (re-check en Action).
- **FR-INV-04** `on_hand` entero en v1.
- **FR-INV-05** No existe "poner stock = N".

## COM — Compras

- **FR-COM-01** Estados `in_transit | received | cancelled` + CHECK de timestamps.
- **FR-COM-02** Default siempre `in_transit`.
- **FR-COM-03** `receive`: `FOR UPDATE` compra+variantes; costo promedio ponderado;
  persiste `received_by_user_code`.
- **FR-COM-04** `cancel` desde `received` resta stock; desde `in_transit` no mueve.
- **FR-COM-05** Pago efectivo → caja `out` con lock. Yape/transfer no mueve caja.
- **FR-COM-06** La compra no emite CPE de venta.

## CAJ — Caja

- **FR-CAJ-01** Ledger user-scoped, sin sesión abrir/cerrar; saldo derivado, nunca
  columna mutable.
- **FR-CAJ-02** `business_date` = hoy Lima, calculado por el servidor.
- **FR-CAJ-03** Salidas toman `pg_advisory_xact_lock(hashtext(ns), hashtext(cashier))`
  antes de verificar saldo.
- **FR-CAJ-04** Sólo `cash` genera movimiento `in`. Mixto = dos filas payments, una
  entrada a caja por el efectivo.
- **FR-CAJ-05** Nunca DELETE: reversión = `reversed` + `reversed_at` + actor, con CHECK.
- **FR-CAJ-06** Egreso y transferencia no generan CPE.
- **FR-CAJ-07** Resumen diario de caja = efectivo; reporte de ventas = todos los medios.

## VEN — Ventas

- **FR-VEN-01** Entrada POS: sede, cajero, cliente, `document_type 01|03`, serie,
  `payments[]`, `items[]` (precio con IGV incluido), nota, `Idempotency-Key`.
- **FR-VEN-02** El backend recalcula totales; ignora el total del frontend.
- **FR-VEN-03** `RecordSale` = UNA transacción DB: sale+items+payments, stock −,
  caja cash, lock serie + INSERT fiscal + snapshot, dispatch afterCommit. SUNAT
  nunca dentro de la transacción.
- **FR-VEN-04** `UNIQUE (issuer_code, idempotency_key)`; mismo key+hash → misma
  Sale; key+payload distinto → 409.
- **FR-VEN-05** `sale_number` (`SAL-`) nunca es la serie tributaria.
- **FR-VEN-06** `Sale.status ∈ {posted, voided}`; máquina comercial independiente.
- **FR-VEN-07** `VoidSale` sólo sin CPE o CPE `rejected`/`cancelled_by_summary`;
  con CPE aceptado sin corrección aceptada → bloqueado (NC o baja).
- **FR-VEN-08** `granted_to_customer` flag explícito (default true en POS).

## FIS — Ciclo fiscal CPE

- **FR-FIS-01** UBL 2.1 vía Greenter 5.3.x fijado en lock. Camino: mapper →
  `getXmlSigned` → persistir XML+sha256 → `sendXmlFile`. Prohibido `$see->send()`
  en retry. Prohibido `greenter/report`, `htmltopdf`, `laravel-greenter`.
- **FR-FIS-02** Correlativo: `FOR UPDATE` + `next_number+1` + `UNIQUE (issuer_code,
  document_type, series, number)`. Huecos quedan; jamás se rellenan.
- **FR-FIS-03** Rechazo de negocio: no retry del mismo XML; el número queda usado.
- **FR-FIS-04** Timeout pre-write → `transport_error`, retry mismo XML. Post-write
  → `unknown` + ConsultCdr, nunca un segundo correlativo.
- **FR-FIS-05** CDR: 0 sin notes = accepted; 0 con notes o ≥4000 = observed; CDR
  `!isAccepted` = rejected; `applicationResponse` vacío = transport/unknown.
- **FR-FIS-06** Estados y transiciones de plan §10; `accepted ↛ draft|reserved|signed`;
  `manual_review` flag ortogonal.
- **FR-FIS-07** Boletas: `pending_summary` → RC del día → ticket → poll (98 reencola,
  0 extrae CDR, 99 error) → RC aceptado pasa todas a `accepted`; rechazado no acepta
  a medias. Baja de boleta no otorgada = ítem en RC.
- **FR-FIS-08** Plazos en config documentada con fuente, nunca constantes mágicas.
- **FR-FIS-09** NC `07`: referencia obligatoria + motivo Cat. 09; `VoidSale` de
  anulación total sólo tras NC accepted.
- **FR-FIS-10** RA: Action explícito, no botón default.
- **FR-FIS-11** Artefactos en `{issuer}/{yyyy}/{mm}/{tipo}-{serie}-{numero}/`;
  sha256 de XML y CDR en DB; PDF regenerable, no evidencia fiscal.
- **FR-FIS-12** Endpoints por ambiente en config.
- **FR-FIS-13** NRUS: `capabilities.invoice=false`; POS no ofrece `01`; sin CHECK
  global anti-`01`.
- **FR-FIS-14** Snapshot receptor en `fiscal_documents`; líneas sin FK viva.
- **FR-FIS-15** Cada intento en `sunat_submissions`.

## GRE — Guías remitente

- **FR-GRE-01** `09` vía REST (`gre-api`). SOAP GRE deprecado. OAuth propio.
- **FR-GRE-02** `TokenStoreInterface` persistente (cache/DB).
- **FR-GRE-03** Ciclo con ticket + poll backoff/techo → `manual_review`.
- **FR-GRE-04** 98 pending; 0/99 completed; `indCdrGenerado='1'` = CDR; 422/500 API.
- **FR-GRE-05** Snapshot envío completo; ítems sin precios.
- **FR-GRE-06** GRE no mueve caja ni stock.
- **FR-GRE-07** Transferencia ligada a GRE es inmutable; corrección = compensatoria.

## PDF — Representación

- **FR-PDF-01** Binario externo (patrón omr-rs): spawn stdin/stdout JSON, env knobs.
- **FR-PDF-02** `RenderPdfJob` afterCommit; si falla, CPE sigue válido.
- **FR-PDF-03** DTO sin certificado/SUNAT/dominio; QR fiscal del binario.
- **FR-PDF-04** `/c/{token}` con XML+PDF, sin login, ≥ 1 año.

## SEG — Secretos

- **FR-SEG-01** PEM y Clave SOL en secret store/env; nunca Git/Inertia. Password
  cert ≠ password SOL. Usuario SOL secundario.
- **FR-SEG-02** Subir certificado es permiso restringido.
- **FR-SEG-03** Cert vencido = fail cerrado; alertas 60/30/15/7.
- **FR-SEG-04** Beta sólo estructura.

## OBS — Observabilidad

- **FR-OBS-01** Pantalla por documento responde todo sin cruzar cinco tablas.
- **FR-OBS-02** Métricas de calidad (tasas, unknown>min, huecos, cert, RC D+1,
  GRE 98>X). No p99 de SOAP.

## NFR — Transversales

- **NFR-01** correctness → readability → consistency → performance → simplicity.
- **NFR-02** Tres capas de dinero: `NUMERIC(12,2)`+cents; `INTEGER` cantidades;
  `NUMERIC(16,6)` unitario fiscal. Nunca float fiscal.
- **NFR-03** Half-up a 2 en línea, luego suma; fixtures de regresión.
- **NFR-04** CI sin red a SUNAT: fakes con CDR 0/4000/2000, fault, timeout, 98, 422.
- **NFR-05** Golden files XML por tipo de documento.
- **NFR-06** Concurrencia: 20 paralelos misma serie → 20 números, 0 duplicados.
- **NFR-07** PostgreSQL dueño de invariantes; sin soft delete fiscal.
- **NFR-08** Cierre: `composer run check` + `pnpm run build` verdes.
- **NFR-09** Docs en el mismo cambio que el modelo.
- **NFR-10** Actualizar dependencias es mantenimiento rutinario.
