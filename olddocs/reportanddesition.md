# Reporte técnico: plataforma de ventas y facturación electrónica SUNAT inspirada en Faztore, Aeduca y Coedula

**Fecha de investigación:** 23 de agosto de 2026  
**Repositorios inspeccionados:** `eliaschampi/faztore`, `eliaschampi/aeduca`, `eliaschampi/coedula`  
**Objetivo:** decidir un stack y una arquitectura para construir un sistema de ventas con facturación electrónica SUNAT que pueda funcionar como producto independiente y, a la vez, como API integrable desde Faztore, Aeduca u otros sistemas durante los próximos cinco a siete años.

**Documento generado:** [Descargar `report.md`](sandbox:/mnt/data/report.md)

## Decisión ejecutiva

Tu idea puede reducirse a una decisión bastante concreta:

> **Construir un producto nuevo; no simplemente “meter SUNAT dentro de Faztore”.**

Ese producto debe ser simultáneamente:

1. un **sistema de ventas completo** con productos, variantes, clientes, sedes, inventario, caja y comprobantes;
2. un **motor fiscal SUNAT** con factura, boleta, notas, bajas/resúmenes, XML firmado, CDR y posteriormente GRE/SIRE;
3. una **API reusable** para que Faztore, Aeduca u otro software pueda delegarle facturación sin adoptar todo su dominio de ventas.

Mi recomendación final de stack es:

| Capa | Decisión |
|---|---|
| Backend | **Laravel 13 + PHP 8.5** |
| Frontend | **Inertia + Svelte 5 + TypeScript strict + Lumi UI** |
| DB | **PostgreSQL 18** |
| SUNAT/CPE | **Greenter 5.x encapsulado tras un adapter propio** |
| Arquitectura | **Monolito modular**, no microservicios internos |
| Integración | REST `/api/v1` + idempotencia + webhooks |
| Asincronía | Laravel Queue |
| Artifacts | XML firmado + CDR + PDF en storage privado |
| Primer alcance fiscal | Factura, boleta, Resumen Diario, Nota de Crédito |
| Segundo alcance | Nota de Débito, GRE, bajas avanzadas |
| Declaración | Cierre fiscal + conciliación + SIRE; no automatizar FV 621 sin contrato oficial validado |
| Despliegue inicial | Un emisor/RUC por deployment, aunque el modelo contemple `issuer` |

**No recomiendo escribir el motor SUNAT desde cero en TypeScript o Rust para la primera versión.** Greenter ya cubre generación UBL, firma digital, transmisión a SUNAT y procesamiento de CDR; además mantiene actividad reciente y la versión 5.3.0 fue publicada el 8 de junio de 2026. citeturn9search0turn9search1turn9search9

Esto tampoco significa escoger una plataforma “antigua”. Aeduca actualmente ya demuestra que Laravel/PHP puede convivir con exactamente el frontend moderno que quieres: su stack declarado es Laravel 13, PHP 8.5, Inertia, Svelte 5, TypeScript strict, Lumi y PostgreSQL; el `composer.json` vigente requiere PHP `^8.5` y Laravel `^13.8`. fileciteturn21file0L2-L2 fileciteturn26file0L2-L2

Laravel 13 fue publicado el 17 de marzo de 2026, soporta PHP 8.3–8.5, recibe bug fixes hasta el tercer trimestre de 2027 y actualizaciones de seguridad hasta el 17 de marzo de 2028. Laravel mantiene una cadencia major anual. Por ello, un sistema diseñado para durar cinco a siete años **no debe congelarse en Laravel 13 durante siete años**: debe estar diseñado para que actualizar Laravel cada año sea una operación rutinaria y de bajo riesgo. citeturn12search0

PostgreSQL 18 es también la elección correcta para un proyecto nuevo hoy: la versión estable publicada actualmente es 18.6; PostgreSQL 19 continúa en beta. PostgreSQL soporta cada major durante cinco años y PostgreSQL 18 llega hasta el 14 de noviembre de 2030. Un proyecto 2026–2033 tendrá inevitablemente al menos una actualización mayor de PostgreSQL. citeturn11search0turn11search2turn11search6

El informe anterior sobre Aeduca ya había llegado a una conclusión importante: el documento fiscal debe permanecer separado de pagos/caja y SUNAT debe vivir detrás de una capacidad especializada. La inspección de Faztore y Coedula fortalece esa decisión. fileciteturn0file0

Mi matriz de decisión queda así. Las puntuaciones son una evaluación de ingeniería basada en riesgo de implementación, ajuste con tus repositorios, ecosistema fiscal y complejidad operacional; no son benchmarks de CPU.

| Opción | Puntuación ponderada | Evaluación |
|---|---:|---|
| **Laravel + Greenter + Inertia/Svelte** | **9.35 / 10** | Mejor equilibrio |
| SvelteKit + servicio PHP/Greenter separado | 8.45 / 10 | Bueno, pero introduce dos backends |
| SvelteKit full-stack + implementación SUNAT propia | 7.40 / 10 | Gran fit con Faztore, mayor riesgo fiscal |
| Rust API + SvelteKit | 6.23 / 10 | Excelente técnicamente, peor relación riesgo/beneficio aquí |

Rust continúa siendo extraordinariamente sólido y actual; Rust 1.98.0 fue publicado el 20 de agosto de 2026. citeturn11search1 Mi objeción a Rust aquí no es la tecnología. Es que el problema dominante de SUNAT no es conseguir diez veces más throughput de CPU: es producir exactamente el XML correcto, firmarlo correctamente, mantener catálogos/reglas, soportar respuestas y CDR, manejar timeouts, correlativos, notas, reintentos y cambios regulatorios. Dado que Greenter ya implementa esa especialización sobre los servicios SUNAT, reescribirla en Rust aumenta la cantidad de código fiscal que tú mismo tendrías que demostrar correcto. Esa conclusión es una inferencia arquitectónica basada en el flujo SUNAT y en las capacidades comprobadas de Greenter. citeturn7search1turn9search1

## Lo que realmente dicen Faztore, Aeduca y Coedula

Faztore no es únicamente una referencia visual. La arquitectura actual contiene exactamente varias de las ideas que debería heredar el nuevo producto.

Su README define un sistema empresarial SvelteKit + PostgreSQL para ventas, productos, inventario, sedes, transferencias, compras, caja y reportes. Usa Svelte 5, TypeScript, Kysely, un schema SQL-first y Lumi UI. Entre sus principios explícitos están mantener datos operativos normalizados y por sede, evitar fuentes duplicadas de verdad, conservar una UI coherente y añadir complejidad sólo cuando protege corrección o mejora un workflow real. fileciteturn8file0L2-L2

Todavía más importante: la arquitectura de Faztore fija el orden de prioridades como:

> **correctness → readability → consistency → performance → simplicity**

y pide código predecible y testeable antes que novedad. También establece que SQL complejo, invariantes y actualizaciones de varias tablas deben estar en repositories/helpers, mientras las rutas deben permanecer delgadas. fileciteturn9file0L2-L2

Ese orden es particularmente adecuado para facturación. En un sistema fiscal, ahorrar algunos milisegundos jamás compensa:

```text
correlativo repetido
XML regenerado de forma diferente
CDR perdido
timeout interpretado como rechazo
factura anulada por un UPDATE local
nota ligada al comprobante equivocado
```

Faztore ya tiene una separación operacional muy buena. Su esquema de ventas almacena identificador interno, sede, cajero, cliente, método de pago y estados `posted/voided`; las líneas usan `NUMERIC` para precios y costos. Caja se mantiene como un ledger independiente en `cashbox_movements`. fileciteturn23file0L2-L2

Conceptualmente, el nuevo producto debe continuar esa separación:

```text
Sale
 ├── SaleItems
 ├── Inventory
 ├── Payments
 └── Cashbox

FiscalDocument
 ├── FiscalItems
 ├── Series / Correlative
 ├── Signed XML
 ├── SUNAT submissions
 ├── CDR
 └── Credit/Debit/Cancel relations
```

Hoy `InventoryRepository.createSale()` en Faztore abre una transacción, crea la venta, modifica inventario, guarda líneas y genera el movimiento de caja cuando corresponde. `voidSale()` restaura stock, marca la venta como anulada y revierte el movimiento de caja. fileciteturn16file0L2-L2 La ruta de ventas simplemente verifica permisos, normaliza datos y delega en el repository. fileciteturn12file0L2-L2

Éste es un excelente patrón para reutilizar, con una excepción esencial:

```text
InventoryRepository.voidSale()
                  ≠
anulación tributaria SUNAT
```

Una venta puede anularse operacionalmente. Un CPE que ya alcanzó determinados estados fiscales debe corregirse con el mecanismo tributario que corresponda. SUNAT reconoce Nota de Crédito para anulaciones, descuentos, bonificaciones, devoluciones y otras modificaciones de factura/boleta, y para boletas existen además mecanismos de baja a través del Resumen Diario. citeturn7search3

Por ello:

```text
sales.status
```

y:

```text
fiscal_documents.status
```

**deben ser máquinas de estado independientes.**

Faztore revela también otro hueco que confirma esta separación. Su tabla `customers` actual contiene básicamente `full_name`, teléfono y notas; no existe todavía un perfil tributario completo con RUC/DNI, razón social, domicilio fiscal, etc. fileciteturn25file0L2-L2

No intentaría convertir la fila mutable de `customers` en evidencia fiscal. El CPE debe conservar su propio **snapshot tributario**:

```text
document_type
document_number
legal_name
address
ubigeo
email si corresponde
```

Una modificación futura del cliente no puede alterar qué datos fueron utilizados cuando se emitió `F001-123`.

Aeduca aporta la otra mitad de la arquitectura. Su baseline dice explícitamente que Laravel es dueño de las reglas de negocio, autorización, validación y transacciones; Svelte se ocupa de interacción/presentación; PostgreSQL protege la verdad estructural mediante FK, `UNIQUE` y `CHECK`. fileciteturn21file0L2-L2

Además, su setup actual ya levanta Laravel Queue junto al servidor, logs, Reverb y Vite. fileciteturn26file0L2-L2 Ésa es precisamente la infraestructura necesaria para que una llamada externa a SUNAT no mantenga abierta la transacción de una venta.

Y no hay sacrificio de modernidad frontend: Aeduca utiliza actualmente Svelte 5.56.9, TypeScript 5.9.3, Vite 8.1.4 e Inertia Svelte 3.6.1. fileciteturn27file0L2-L2

Coedula aporta una tercera lección: cómo aislar capacidades especializadas. Su `omr-rs` vive como binario externo y configurable; Rust no está embebido dentro de Node. La arquitectura también mantiene OMR/PDF fuera del request normal de página. fileciteturn17file0L2-L2 fileciteturn18file0L2-L2

Tu intuición de “quiero algo integrable como `omr-rs`” es correcta, pero el límite debería cambiar de forma.

OMR es aproximadamente:

```text
input
  ↓
computation
  ↓
output
```

Facturación es:

```text
request
  ↓
correlative reservation
  ↓
immutable fiscal snapshot
  ↓
XML
  ↓
signature
  ↓
external submission
  ↓
possibly unknown network state
  ↓
CDR/status
  ↓
possible correction later
```

Es un workflow persistente. Por ello, la equivalencia correcta a `omr-rs` no es “ejecutar un binario PHP”, sino poseer un contrato muy estrecho:

```text
Application
     ↓
FiscalGateway
     ↓
GreenterSunatGateway
     ↓
SUNAT
```

Coedula además contiene una regla especialmente buena para este proyecto: **un service no existe por ceremonia**. Sólo aparece si realmente orquesta, transacciona o comparte una regla; un wrapper `Service -> Repository` sin lógica está prohibido. fileciteturn18file0L2-L2

Eso debería convertirse en la filosofía del nuevo repo:

> Arquitectura limpia en responsabilidades, no una “Clean Architecture” de cincuenta carpetas.

La síntesis de patrones queda así:

| Patrón | Procedencia | Uso |
|---|---|---|
| Sede/workspace explícito | Faztore, Coedula | Serie fiscal por establecimiento |
| Producto ≠ variante | Faztore | Stock/comercialización correcta |
| Caja como ledger | Faztore, Coedula | No acoplar saldo a CPE |
| Multi-write transaccional | Los tres | Venta, stock, pago, intención fiscal |
| Entry point delgado | Los tres | Controllers/actions pequeños |
| Queue | Aeduca | SUNAT fuera de transacciones DB |
| Specialized boundary | Coedula | Adapter Greenter |
| Un owner por write path | Aeduca | FiscalDocument dueño del ciclo fiscal |
| Sin servicios ceremoniales | Coedula | Menos arquitectura accidental |

## Restricciones SUNAT y alcance fiscal real

La primera conclusión de la investigación SUNAT es que el motor fiscal **no puede considerarse código terminado para siempre**.

SUNAT mantiene documentación UBL 2.1 y publica actualizaciones de reglas. Su página oficial de guías, modificada el 24 de julio de 2026, lista reglas de validación CPE actualizadas al 30 de julio de 2026 y mantiene XSD/XSL UBL 2.1. citeturn8search2

Esto obliga a una arquitectura donde:

```text
SUNAT catalogs
SUNAT validation rules
SUNAT endpoints
Greenter version
XML fixtures
contract tests
```

puedan evolucionar sin tocar ventas, inventario o caja.

En el SEE del Contribuyente, SUNAT permite emitir factura, boleta, notas de crédito y notas de débito. Para ser emisor existen condiciones sobre el estado del RUC y la configuración electrónica. SUNAT señala además que la factura y la nota vinculada deben enviarse en la fecha de emisión o dentro de los tres días calendario siguientes. citeturn7search2

Los servicios oficiales publicados actualmente incluyen un `billService` beta, un `billService` de producción y servicios de consulta de validez y de CDR/estado. citeturn7search1

Por eso:

```text
SUNAT_BILL_ENDPOINT
SUNAT_QUERY_ENDPOINT
SUNAT_ENVIRONMENT
```

deben ser configuración central. No URLs repetidas dentro de cinco classes.

La boleta requiere atención especial porque su lifecycle no es idéntico al de factura. SUNAT documenta el envío de la información mediante **Resumen Diario**; el resumen correspondiente a un día puede enviarse ese mismo día o, como máximo, hasta el séptimo día calendario siguiente. SUNAT devuelve una CDR del resumen con estado aceptado o rechazado. citeturn7search5

En consecuencia, esto sería incorrecto:

```text
Invoice.send()
Boleta.send()
```

como si ambas fueran el mismo workflow con diferente código.

Un modelo más realista sería:

```text
Invoice
  reserved
  signed
  submitted
  accepted/rejected

Boleta
  reserved
  signed
  pending_summary
  included_in_summary
  accepted/rejected
```

El CDR tampoco debe “generarse” localmente. El sistema genera/firma su XML y procesa la respuesta que recibe de SUNAT. Greenter ya proporciona precisamente generación UBL, firma, envío y procesamiento del CDR. citeturn9search1turn9search9

### Tu RUC personal

Aquí hay una distinción importante:

> **Tu problema para factura no es que tu RUC empiece en 10. Es que actualmente estás en Nuevo RUS.**

SUNAT establece que los sujetos del NRUS pueden emitir:

- boletas de venta;
- tickets;
- Guías de Remisión Remitente;

y que el NRUS no emite factura. citeturn7search10turn8search0turn8search1

Pero SUNAT también indica que RER, RMT y Régimen General admiten personas naturales y permiten factura/boleta según el régimen. citeturn14search0turn14search1

Por tanto, **jamás programaría**:

```ts
if (ruc.startsWith("10")) {
  invoice = false;
}
```

Eso sería conceptualmente incorrecto.

Modelaría:

```text
Issuer
  tax_regime
  electronic_issuer_status
  fiscal_capabilities
```

Por ejemplo:

```json
{
  "tax_regime": "NRUS",
  "capabilities": {
    "invoice": false,
    "receipt": true,
    "credit_note": "conditional",
    "gre_sender": true,
    "sire": false
  }
}
```

y para un RMT:

```json
{
  "tax_regime": "RMT",
  "capabilities": {
    "invoice": true,
    "receipt": true,
    "credit_note": true,
    "gre_sender": true,
    "sire": true
  }
}
```

No dejaría necesariamente estas capacidades hardcoded para siempre; pueden derivarse/configurarse bajo reglas del adaptador tributario.

Esto también define cómo probar el producto:

| Caso | Emisor adecuado |
|---|---|
| Boleta real | Tu NRUS puede servir |
| GRE real | Tu NRUS puede ser caso válido según operación |
| Factura real | Emisor RER/RMT/General habilitado |
| B2B completo | Uno de tus clientes/amigos RUC 20 |
| Desarrollo | Ambiente beta + fixtures |
| Regresión | Contract tests offline |

SUNAT señala actualmente que la mayoría de contribuyentes ya están obligados a CPE, salvo excepciones como Nuevo RUS. citeturn7search6

### Anulaciones

SUNAT reconoce Nota de Crédito para anulaciones, descuentos, bonificaciones, devoluciones y otras correcciones relacionadas con comprobantes ya emitidos. citeturn7search3

Consecuencia:

```text
"Anular venta"
```

y:

```text
"Corregir/anular comprobante"
```

deben ser acciones diferentes.

La UI podría incluso mostrar:

```text
Venta
Estado comercial: ANULADA
Comprobante: F001-123
Estado SUNAT: ACEPTADO
Corrección fiscal: NC FC01-45 ACEPTADA
```

Eso preserva historia y es mucho más correcto que convertir todo en un `status = cancelled`.

### Guías de remisión

GRE es totalmente factible dentro del mismo producto, pero merece un vertical propio.

SUNAT mantiene para GRE reglas separadas, actualizadas al 20 de junio de 2026, y la Resolución 108-2026 introdujo cambios vigentes desde el 1 de junio de 2026. citeturn8search4turn8search16

Greenter 5 añadió soporte para la nueva GRE y su ecosistema tiene un cliente dedicado para la API REST de GRE. citeturn9search0turn9search10

Por eso diseñaría:

```text
Fiscal CPE
 ├── Invoice
 ├── Receipt
 ├── CreditNote
 ├── DebitNote
 └── DailySummary

Logistics
 ├── SenderDespatch
 ├── CarrierDespatch
 └── EventDespatch
```

Comparten:

```text
Issuer
credentials
audit
storage
API auth
observability
```

pero no tienen que usar el mismo builder o state machine.

### Declaración y SIRE

“Declaración básica” debería definirse de manera conservadora.

SUNAT describe SIRE como el sistema que genera y administra:

```text
RVIE
Registro de Ventas e Ingresos Electrónico

RCE
Registro de Compras Electrónico
```

a partir de CPE; después de generar ambos registros, SUNAT genera una propuesta para la declaración mensual del IGV. citeturn10search0turn10search4

Además existe oficialmente un Web API SIRE. La FAQ de SUNAT describe REST, archivos ZIP/JSON y OAuth 2.0. citeturn10search3 Tanto RVIE como RCE publican manuales Web API y SUNAT mantiene PVSIRE para validar archivos de reemplazo antes del envío. citeturn10search1turn10search2turn10search6

Así que mi alcance de compliance sería:

```text
CPE aceptados
      +
Notas
      +
Bajas
      +
Compras
      ↓
Cierre del periodo
      ↓
Conciliación local
      ↓
SIRE
      ↓
RVIE / RCE
      ↓
Diferencias
      ↓
Propuesta de declaración
```

**No incluiría en el MVP “presentar automáticamente el FV 621”.**

Las fuentes oficiales que he podido verificar sí establecen una interfaz API para SIRE y una propuesta de declaración, pero no aportan base suficiente para considerar una API pública y estable de presentación automática del 621 como contrato disponible para este producto. Eso sólo debería implementarse después de verificar expresamente la interfaz oficial vigente.

Para tu NRUS la situación es diferente: SUNAT indica que NRUS no está obligado a llevar libros/registros contables y que el pago de la cuota mensual constituye su workflow tributario propio. citeturn8search0turn14search0

Por eso evitaría algo como:

```text
TaxDeclarationService
```

universal.

Preferiría:

```text
ComplianceCapability
   ├── NrusCompliance
   └── Vat/SireCompliance
```

conceptualmente, sin necesidad de convertir eso en una jerarquía de clases prematura.

## Decisión de stack y arquitectura objetivo

El producto debería ser un **monolito modular API-first**.

“API-first” no significa que la propia interfaz tenga que hacer `fetch()` contra su backend para todo. El panel puede utilizar Laravel + Inertia directamente; el contrato REST existe para sistemas externos.

La arquitectura:

```text
                            Integraciones

     Faztore ───────────────┐
     Aeduca ────────────────┼───────┐
     ERP/POS externo ───────┘       │
                                    ▼
                           ┌─────────────────┐
                           │ REST /api/v1    │
                           │ auth + scopes   │
                           │ idempotency     │
                           └────────┬────────┘
                                    │
                                    ▼
                    ┌────────────────────────────┐
                    │ Laravel modular monolith   │
                    │                            │
                    │ Sales        Fiscal        │
                    │ Inventory    Customers     │
                    │ Cashbox      Integrations  │
                    │ Reports      Compliance    │
                    └────────┬───────────┬───────┘
                             │           │
                             │           ▼
                             │      Laravel Queue
                             │           │
                             │           ▼
                             │   ┌────────────────┐
                             │   │ FiscalGateway  │
                             │   └───────┬────────┘
                             │           │
                             │           ▼
                             │   Greenter Adapter
                             │           │
                             │           ▼
                             │         SUNAT
                             ▼
                       PostgreSQL 18
```

El panel:

```text
Laravel 13
PHP 8.5
Inertia
Svelte 5
TypeScript strict
Lumi UI
Vite 8
```

es probablemente el punto de equilibrio perfecto entre Aeduca y Faztore.

No escogería SvelteKit para **este nuevo panel**, aunque Faztore lo usa excelentemente, porque ya necesitas Laravel como backend fiscal y queue runtime. Añadir SvelteKit como segundo backend significaría tener:

```text
Node deployment
PHP deployment
authentication boundary
two error pipelines
two deployment pipelines
two health models
cross-service calls
```

sin una necesidad real todavía.

Aeduca demuestra que Inertia/Svelte preserva exactamente el frontend moderno que buscas sin introducir ese segundo servidor. fileciteturn21file0L2-L2 fileciteturn27file0L2-L2

Eso **no implica modificar Faztore**. Faztore debe seguir siendo SvelteKit. Su stack actual es SvelteKit 2, Svelte 5.56.4, TypeScript 5.9.3, Vite 8.1.5, Kysely 0.29.4 y PostgreSQL. fileciteturn10file0L2-L2

El nuevo producto simplemente le ofrece:

```text
POST /api/v1/fiscal-documents
```

### Adapter fiscal interno

Crearía un contrato pequeño, propio y estable.

Conceptualmente:

```php
interface FiscalGateway
{
    public function buildAndSign(
        FiscalDocument $document
    ): SignedArtifact;

    public function submit(
        SignedArtifact $artifact
    ): SubmissionResult;

    public function queryStatus(
        FiscalDocument $document
    ): StatusResult;
}
```

Implementación inicial:

```php
final class GreenterFiscalGateway implements FiscalGateway
{
    // Greenter queda encapsulado aquí.
}
```

Greenter **no debe aparecer** en:

```text
Controllers
API JSON
Eloquent models públicos
DB schema
Svelte pages
external SDK
```

Así el dominio habla de:

```text
FiscalDocument
SignedArtifact
SubmissionResult
```

no de clases concretas de Greenter.

Esto deja abierta la posibilidad de sustituir Greenter por un PSE/otro transport en el futuro. SUNAT contempla que un emisor pueda vincularse con proveedores de servicios electrónicos dentro de su configuración de emisión. citeturn7search2

### ¿Librería interna separada desde el inicio?

**No.**

La mejor primera versión sería:

```text
app/
  Fiscal/
    ...
```

o una estructura equivalente adaptada al estilo Aeduca.

No:

```text
packages/
  super-enterprise-clean-sunat-core/
```

el primer día.

Extraería un package Composer únicamente cuando tengamos un segundo consumidor PHP real.

Esto sigue exactamente la filosofía de Coedula de no crear capas sólo porque parecen arquitectónicamente elegantes. fileciteturn18file0L2-L2

### Despliegue por empresa

Aunque el schema debe tener una entidad:

```text
issuers
```

recomiendo que los primeros despliegues sean:

```text
1 deployment
1 company/RUC
N branches
```

y no:

```text
1 shared SaaS DB
50 RUC
```

desde la primera versión.

Cada emisor tiene:

```text
certificado
SOL/API credentials
series
correlativos
régimen
establecimientos
artifacts
riesgo fiscal
```

El aislamiento inicial reduce enormemente el blast radius.

El schema preparado para `issuer_code` hace que esto no cierre la puerta a SaaS más adelante.

### Runtime recomendado

| Capa | Elección |
|---|---|
| Runtime | PHP 8.5 |
| Framework | Laravel 13 |
| Front | Inertia + Svelte 5 |
| UI | Lumi |
| DB | PostgreSQL 18.6 o minor 18 vigente |
| Queue inicial | Laravel database queue |
| Queue futuro | Redis sólo si métricas lo justifican |
| Fiscal | Greenter 5.x fijado por `composer.lock` |
| Storage | filesystem durable o S3-compatible privado |
| Secrets | secret store / variables protegidas |
| API | `/api/v1` |
| Contract | OpenAPI |
| Logs | JSON estructurado + correlation ID |

Empezaría con queue sobre DB antes de Redis. Para el volumen esperable de tus primeros negocios, eliminar una dependencia operativa tiene más valor que optimizar prematuramente la cola. Redis entra cuando haya una medida concreta que lo justifique.

Eso encaja exactamente con el principio de Faztore: añadir complejidad sólo cuando protege corrección o mejora workflows reales. fileciteturn8file0L2-L2

## Diseño de dominio, API y persistencia

La decisión central del modelo es:

> **`Sale` y `FiscalDocument` son agregados distintos.**

`Sale` responde:

```text
¿Qué se vendió?
¿Dónde?
¿Quién?
¿Cuánto?
¿Cómo se pagó?
¿Cómo cambió el stock?
¿Cómo cambió caja?
```

`FiscalDocument` responde:

```text
¿Qué emisor emitió?
¿Qué tipo de CPE?
¿Qué serie/correlativo?
¿Qué identidad tributaria tenía el cliente?
¿Qué importes fiscales exactos?
¿Qué XML exacto fue firmado?
¿Qué respondió SUNAT?
¿Cómo se corrigió después?
```

La relación adecuada es:

```text
Sale 1 ────── 0..N FiscalDocument
```

no necesariamente `1:1`.

Una venta puede:

```text
no tener CPE todavía
tener una factura
tener una boleta
tener después una nota de crédito
provenir de Faztore
provenir de otro POS
```

### Tablas principales

| Tabla | Owner |
|---|---|
| `issuers` | Emisor/RUC |
| `issuer_credentials` | Secret references |
| `branches` | Sedes/establecimientos |
| `customers` | CRM mutable |
| `products` | Familia comercial |
| `product_variants` | Unidad vendible |
| `inventory_balances` | Stock |
| `sales` | Venta |
| `sale_items` | Detalle de venta |
| `sale_payments` | Métodos de pago |
| `cashbox_movements` | Ledger |
| `fiscal_series` | Serie/correlativo |
| `fiscal_documents` | CPE |
| `fiscal_document_items` | Snapshot de líneas |
| `fiscal_relations` | Notas/bajas/origen |
| `fiscal_artifacts` | XML/CDR/PDF/hash |
| `sunat_submissions` | Intentos |
| `integration_idempotency` | Requests externos |
| `outbox_events` | Delivery confiable |

Un posible `fiscal_documents`:

```text
code UUID

issuer_code UUID
branch_code UUID

sale_code UUID NULL

external_system VARCHAR NULL
external_reference VARCHAR NULL

document_type VARCHAR
series VARCHAR
number BIGINT

issue_at TIMESTAMPTZ
currency CHAR(3)
operation_type VARCHAR

customer_document_type VARCHAR
customer_document_number VARCHAR
customer_legal_name VARCHAR
customer_address TEXT NULL

taxable_amount NUMERIC
exempt_amount NUMERIC
unaffected_amount NUMERIC
tax_amount NUMERIC
total_amount NUMERIC

status VARCHAR

sunat_response_code VARCHAR NULL
sunat_response_description TEXT NULL

accepted_at TIMESTAMPTZ NULL
rejected_at TIMESTAMPTZ NULL

signed_xml_sha256 CHAR(64) NULL
cdr_sha256 CHAR(64) NULL

created_at TIMESTAMPTZ
updated_at TIMESTAMPTZ
```

### Serie y correlativo

No reutilizaría nunca:

```text
SAL-000123
```

como:

```text
F001-123
```

Faztore ya tiene numeraciones independientes para venta, compra, transferencia, etc. fileciteturn23file0L2-L2 El número fiscal tiene otro owner y otras reglas.

Reserva:

```sql
BEGIN;

SELECT *
FROM fiscal_series
WHERE issuer_code = ?
  AND document_type = ?
  AND series = ?
FOR UPDATE;

-- obtener next_number

UPDATE fiscal_series
SET next_number = next_number + 1
WHERE ...;

INSERT INTO fiscal_documents (...);

COMMIT;
```

Y, aunque hagamos locking correctamente:

```sql
UNIQUE (
  issuer_code,
  document_type,
  series,
  number
)
```

PostgreSQL debe defender la invariancia incluso si en el futuro alguien introduce un bug.

### Dinero

No utilizaría `number` de JavaScript como verdad fiscal.

Externamente:

```json
{
  "quantity": "2.000",
  "unit_price": "118.00"
}
```

Internamente:

```text
NUMERIC / decimal exact
```

El backend calcula:

```text
valor de venta
base gravada
IGV
total
```

Nunca confiaría en totals enviados por el frontend.

Tampoco hardcodearía:

```php
$tax = $price * 0.18;
```

en controllers.

El producto debe soportar clasificación de afectación porque SUNAT maneja más de una categoría tributaria y sus reglas/catálogos evolucionan. citeturn8search2

### Estado fiscal

Una máquina razonable:

```text
draft
  ↓
reserved
  ↓
signed
  ↓
queued
  ↓
submitted
  ├── accepted
  ├── accepted_with_observations
  ├── rejected
  └── transport_error
```

Luego:

```text
accepted
  ↓
correction_pending
  ↓
corrected
```

y:

```text
unknown/manual_review
```

para condiciones en las que no se puede demostrar inmediatamente qué ocurrió.

Principio:

> Un documento `accepted` jamás vuelve a `draft`.

Una corrección crea otro documento relacionado.

### Workflow de emisión

```text
request
   ↓
auth + permission
   ↓
issuer capability validation
   ↓
customer validation
   ↓
line/tax validation
   ↓

DATABASE TRANSACTION

   sale
   sale_items
   inventory mutations
   payments
   cashbox movements

   reserve fiscal number
   fiscal snapshot
   outbox event

COMMIT

   ↓
queue
   ↓
generate UBL
   ↓
sign
   ↓
store exact signed XML
   ↓
calculate SHA-256
   ↓
submit
   ↓
persist attempt
   ↓
receive/query state
   ↓
store CDR
   ↓
accepted / rejected / manual review
```

La llamada SUNAT debe ocurrir **después del commit**, no mientras la venta mantiene locks abiertos.

### Retry: una regla crítica

Supongamos:

```text
F001-123
```

se firma.

La aplicación envía.

La conexión se corta.

No sabemos todavía si SUNAT lo recibió.

**Jamás debemos hacer esto:**

```text
timeout
→ create F001-124
→ retry
```

El sistema debe conservar:

```text
F001-123
signed XML
hash
request
submission attempt
```

y consultar/reintentar de manera segura.

El timeout es:

```text
transport state unknown
```

no:

```text
document rejected
```

Esto probablemente será uno de los puntos de calidad más importantes de todo el proyecto.

### API dual

El producto propio consume el dominio de ventas:

```http
POST /api/v1/sales
GET  /api/v1/sales/{sale}
POST /api/v1/sales/{sale}/void
```

Pero una integración como Faztore no necesita que el nuevo sistema vuelva a gestionar su stock.

Faztore usa:

```http
POST /api/v1/fiscal-documents
GET  /api/v1/fiscal-documents/{document}
POST /api/v1/fiscal-documents/{document}/credit-notes
POST /api/v1/fiscal-documents/{document}/cancel
GET  /api/v1/fiscal-documents/{document}/artifacts/{kind}
```

Ésta es una arquitectura particularmente importante porque permite dos modos:

```text
MODE A
Nuevo sistema
owns Sale + FiscalDocument

MODE B
Faztore
owns Sale

Fiscal platform
owns FiscalDocument
```

No se duplican inventarios.

### Payload fiscal

Ejemplo conceptual:

```json
{
  "external_system": "faztore",
  "external_reference": "SAL-000123",
  "document_type": "01",
  "series": "F001",
  "issued_at": "2026-08-23T15:42:10-05:00",
  "currency": "PEN",
  "customer": {
    "document_type": "6",
    "document_number": "20123456789",
    "legal_name": "Cliente SAC",
    "address": "Lima, Perú"
  },
  "pricing_mode": "tax_inclusive",
  "lines": [
    {
      "sku": "KZ-001",
      "description": "Producto ejemplo",
      "quantity": "1.000",
      "unit_price": "118.00",
      "unit_code": "NIU",
      "tax_affectation": "taxed"
    }
  ]
}
```

No pediría al consumidor que construya XML ni conozca Greenter.

La API pública debe ser **business-oriented**:

```text
invoice
receipt
customer
lines
price
tax classification
```

El adapter transforma eso en SUNAT/UBL.

### Idempotencia

Toda creación externa debería requerir:

```http
Idempotency-Key: 6e3386...
```

DB:

```text
UNIQUE (issuer_code, idempotency_key)
```

Semántica:

```text
same key + same payload
→ same fiscal document

same key + different payload
→ 409 Conflict
```

Esto permite que Faztore haga retry sin generar dos facturas.

### Respuesta

Inicial:

```json
{
  "code": "65f4...",
  "external_reference": "SAL-000123",
  "number": "F001-123",
  "status": "queued",
  "total": "118.00"
}
```

Más tarde:

```json
{
  "code": "65f4...",
  "number": "F001-123",
  "status": "accepted",
  "sunat": {
    "code": "0",
    "description": "Aceptado",
    "observations": []
  },
  "artifacts": {
    "pdf": true,
    "xml": true,
    "cdr": true
  }
}
```

`202 Accepted` es perfectamente apropiado cuando el transporte sigue pendiente.

### Webhooks

Eventos:

```text
fiscal_document.accepted
fiscal_document.rejected
fiscal_document.observed
fiscal_document.manual_review
```

Con:

```text
event_id
timestamp
HMAC
retry
replay protection
```

Faztore no necesita estar consultando cada segundo.

### Credenciales

Faztore/Aeduca **nunca deben recibir**:

```text
Clave SOL
private certificate key
SUNAT client_secret
```

Sólo:

```text
Fiscal API token
```

con scopes:

```text
documents:create
documents:read
documents:correct
artifacts:read
```

## Integración con Faztore y camino a guías/declaración

Éste es probablemente el punto que mejor representa tu idea original.

El nuevo servicio no reemplaza la arquitectura de Faztore.

Faztore sigue siendo owner de:

```text
products
variants
inventory
sale
cashbox
branch
user
```

El sistema fiscal se vuelve owner de:

```text
issuer
series
correlative
fiscal snapshot
UBL
signature
SUNAT transport
CDR
fiscal corrections
SIRE
GRE
```

En Faztore, el punto de integración está inmediatamente después de su excelente transacción de venta. `InventoryRepository.createSale()` ya garantiza venta + líneas + stock + caja en una sola transacción. fileciteturn16file0L2-L2

No llamaría:

```text
SUNAT SOAP
```

dentro de esa misma transacción.

La evolución correcta sería:

```text
InventoryRepository.createSale()

BEGIN
  INSERT sales
  INSERT sale_items
  UPDATE inventory
  INSERT cashbox_movements

  INSERT integration_outbox
    event_type = fiscal_document.requested
COMMIT
```

Después:

```text
Faztore worker
      ↓
reads outbox
      ↓
POST Fiscal API
      ↓
uses Idempotency-Key
      ↓
stores remote document code
      ↓
marks outbox delivered
```

Si el servicio fiscal está caído:

```text
SALE = valid
OUTBOX = pending
```

No:

```text
sale disappeared
```

ni:

```text
sale exists but nobody knows invoice was never requested
```

### Cambios necesarios en Faztore

| Cambio | Motivo |
|---|---|
| Selector `Boleta / Factura / Sin CPE` | intención fiscal |
| Perfil fiscal/snapshot de cliente | DNI/RUC/razón social |
| `integration_outbox` | delivery confiable |
| `sale_fiscal_links` | documento remoto |
| Badge de estado | pending/accepted/rejected |
| Acción fiscal independiente | no reutilizar `voidSale` |

No tocaría el modelo de cashbox para hacerlo “SUNAT-aware”.

Tampoco guardaría el XML y CDR de forma duplicada dentro de Faztore. La plataforma fiscal es su owner.

### Aeduca

Aeduca podría consumir exactamente el mismo contrato.

Como ya dispone de Laravel Queue, incluso la integración outbox/worker sería natural dentro de su runtime. Su propio informe SUNAT anterior ya encontró que pagos/caja y documento fiscal deben estar separados. fileciteturn0file0

Lo importante es que:

```text
Faztore integration
```

y:

```text
Aeduca integration
```

utilicen **el mismo API fiscal**, no dos implementaciones SUNAT diferentes.

Ése es el equivalente real de tu idea de `omr-rs`.

### GRE

Cuando factura/boleta estén sólidos:

```text
/api/v1/despatch-documents
```

podría convertirse en la segunda superficie.

Pero internamente mantendría:

```text
FiscalGateway
```

y:

```text
GreGateway
```

separados si los protocolos/reglas así lo justifican.

SUNAT mantiene GRE como superficie técnica propia y Greenter posee herramientas específicas para ella. citeturn8search16turn9search10

Eso cubre exactamente los clientes que hoy te dicen:

> “quiero sistema de ventas y guías”.

### SIRE

Sólo después de tener un ledger fiscal confiable.

El módulo mensual puede producir:

```text
Periodo: 2026-08

Facturas aceptadas
Boletas informadas
Notas de crédito
Notas de débito
Bajas
Ventas gravadas
Ventas exoneradas
Ventas inafectas
IGV
Documentos pendientes
Documentos rechazados
Diferencias SUNAT
```

y compararlo con RVIE/SIRE.

SUNAT confirma que SIRE dispone de Portal, aplicación cliente y Web API, y que la API emplea REST/OAuth 2.0. citeturn10search0turn10search3

La secuencia adecuada:

```text
facturar correctamente
       ↓
reconciliar correctamente
       ↓
construir cierre mensual
       ↓
integrar SIRE
       ↓
automatizar más sólo donde el contrato oficial sea claro
```

No al revés.

## Plan de ejecución, pruebas y horizonte de mantenimiento

El proyecto debería construirse en **verticales funcionales**, no primero creando cien tablas y cien DTO.

El primer vertical tiene que demostrar:

```text
Issuer
  ↓
Certificate
  ↓
Series
  ↓
Customer
  ↓
Sale
  ↓
Fiscal calculation
  ↓
UBL
  ↓
Signature
  ↓
Submission
  ↓
CDR
  ↓
Persistence
  ↓
PDF/query
  ↓
retry/idempotency
```

Si eso funciona, tienes un motor fiscal.

Si sólo tienes:

```text
customers CRUD
products CRUD
invoice table
```

todavía no tienes facturación SUNAT.

Una secuencia pragmática sería:

| Vertical | Resultado |
|---|---|
| Fundación | auth, issuer, branch, permissions, secrets, DB, queue, storage |
| Ventas | producto, variante, stock, venta, pagos, ledger |
| Factura | `01`, serie, UBL, firma, submit, CDR |
| Boleta | `03`, Resumen Diario |
| Correcciones | Nota de Crédito `07` |
| Operación | retries, queries, manual review, customer portal |
| API externa | `/api/v1` + webhooks |
| Faztore | outbox + integration |
| GRE | remitente primero |
| Compliance | cierre + SIRE/RVIE |
| Compras | RCE cuando el producto realmente maneje compras |
| Multiempresa | sólo cuando haya demanda real |

El MVP comercial que considero **suficientemente serio** no es “genera XML”.

Debe tener:

```text
Factura
Boleta
Resumen Diario
Nota de Crédito
CDR
PDF/QR
Series
Correlativos concurrentes
Idempotency
Retries
Status reconciliation
Audit
Customer consultation
Artifact backups
Certificate expiry alerts
```

SUNAT señala además que el emisor del SEE del Contribuyente debe poner sus comprobantes/notas a disposición del adquirente mediante una consulta web durante al menos un año. citeturn7search2turn7search5

Por eso sí incorporaría desde relativamente temprano:

```text
https://billing.example.com/cpe/{secure-token}
```

conceptualmente, con acceso apropiadamente protegido.

### Pruebas de dominio

Deben cubrir:

```text
decimal calculations
rounding
tax categories
issuer capabilities
series
correlative allocation
state transitions
idempotency
credit-note relations
```

### Pruebas de concurrencia

Una prueba crítica:

```text
100 concurrent requests
same issuer
same F001 series
```

Resultado esperado:

```text
100 distinct numbers
no duplicates
no silent gaps caused by rollback design errors
```

y PostgreSQL debe actuar como última barrera.

### Contract tests XML

Mantendría:

```text
tests/Fixtures/Fiscal/
  invoice/
  receipt/
  credit-note/
  daily-summary/
```

Cada fixture tiene:

```text
input.json
expected.xml
```

más validación XSD.

SUNAT mantiene reglas actualizadas al 30 de julio de 2026, por lo que cada actualización fiscal debe pasar la suite completa antes de producción. citeturn8search2

### Tests de transporte

Casos mínimos:

```text
accepted
accepted_with_observation
rejected

SOAP fault
connection refused
DNS failure
timeout before write
timeout after probable write

retry
duplicate request
query after unknown result
```

El último grupo es más importante que el happy path.

### Beta

SUNAT expone un `billService` beta separado de producción. citeturn7search1

Usaría beta para smoke/integration tests, pero **CI normal no debería depender de que SUNAT esté disponible**.

La mayoría de pruebas debe ejecutarse con:

```text
FakeFiscalGateway
fixture CDR
fixture SOAP responses
```

y un job separado realiza smoke tests beta periódicos.

### Piloto productivo

Para factura, tu NRUS no puede ser el emisor real porque SUNAT indica que NRUS no emite factura. citeturn8search1

Por tanto, el primer E2E real debería ser:

```text
RUC de un cliente/amigo
RER, RMT o General
emisor electrónico habilitado
certificado configurado
una sola sede
una serie F001
```

Después:

```text
B001
credit note
```

y sólo tras estabilidad:

```text
additional branches
GRE
```

### Observabilidad

Para cada CPE, el sistema debe poder contestar inmediatamente:

```text
¿Quién lo solicitó?

¿Desde qué sistema?
Faztore?
Aeduca?
UI local?

¿Cuál era external_reference?

¿Qué serie/número obtuvo?

¿Qué payload fiscal se fijó?

¿Qué XML exacto se firmó?

¿Cuál es su SHA-256?

¿Cuándo fue enviado?

¿A qué ambiente?

¿Cuántos intentos existen?

¿Qué respondió SUNAT?

¿Existe CDR?

¿Qué hash tiene el CDR?

¿Tiene observaciones?

¿Se corrigió?

¿Qué documento de corrección lo referencia?
```

Eso es más importante que tener un dashboard con treinta charts.

### Artifacts

Guardar:

```text
signed XML
CDR original
PDF representation
```

El PDF puede considerarse regenerable bajo ciertas condiciones.

El signed XML y el CDR deben considerarse inmutables.

Además:

```text
signed_xml_sha256
cdr_sha256
```

permiten comprobar integridad.

### Política de mantenimiento

Para llegar limpio a 2031–2033:

| Componente | Política |
|---|---|
| Laravel | actualizar cada major anual |
| PHP | mantener versión soportada por Laravel |
| Greenter | actualizar tras contract tests |
| SUNAT rules | monitorizar actualizaciones |
| PostgreSQL | minor actual; major antes del EOL |
| Svelte | pequeños upgrades frecuentes |
| TypeScript | pequeños upgrades frecuentes |
| Vite | pequeños upgrades frecuentes |
| API propia | `/api/v1` estable |
| Certificados | alertas 60/30/15/7 días |
| Backups | restore test periódico |
| DB migrations | sin cambios manuales de producción |

Laravel 13 no tiene siete años de soporte; PostgreSQL 18 tampoco. Laravel documenta dos años de security fixes por major y PostgreSQL soporta cada major cinco años. citeturn12search0turn11search0

La conclusión correcta sobre “quiero algo que aguante cinco a siete años” no es:

```text
buscar una versión que no cambie
```

sino:

```text
hacer que actualizarla sea aburrido
```

Eso se consigue con:

```text
boundaries
tests
typed inputs
DB constraints
small controllers
immutable fiscal snapshots
no framework leaking into API
no Greenter leaking into domain
no duplicated SUNAT implementations
```

La dirección final queda resumida así:

```text
NO
│
├── pegar SUNAT a InventoryRepository de Faztore
├── convertir sale.voided en “factura anulada”
├── escribir UBL/SOAP/XMLDSig desde cero sólo por usar Rust
├── montar PHP + Node + Rust como tres servicios desde el día uno
├── compartir credenciales SUNAT con consumidores
├── tratar RUC 10 como incapaz de facturar por definición
├── unificar NRUS y RMT/RER/General bajo la misma declaración
└── automatizar 621 antes de tener CPE/SIRE correctos


SÍ
│
├── producto fiscal/ventas nuevo
├── Laravel 13 + PHP 8.5
├── Svelte 5 + TypeScript + Lumi
├── PostgreSQL 18
├── Greenter encapsulado
├── monolito modular
├── Sale separado de FiscalDocument
├── series protegidas por DB
├── decimal exacto
├── immutable snapshots
├── outbox
├── queue
├── idempotency
├── retry/reconciliation
├── XML/CDR persistidos
├── API reusable
├── Faztore como primer consumidor ideal
├── Factura
├── Boleta + Resumen Diario
├── Nota de Crédito
├── GRE
└── SIRE después
```

Éste es, en mi evaluación, el camino que preserva mejor lo que ya hiciste bien: **la transaccionalidad y normalización de Faztore, el backend/queue moderno de Aeduca, la disciplina de fronteras de Coedula y la filosofía de `omr-rs`, pero usando un motor fiscal peruano especializado en vez de reinventar la parte donde el costo de equivocarse es mayor.** fileciteturn8file0L2-L2 fileciteturn21file0L2-L2 fileciteturn18file0L2-L2 citeturn9search0turn9search1