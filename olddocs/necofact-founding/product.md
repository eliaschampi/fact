# product.md — Qué es NecoFact

> Archivo (2026-08-23). Referencia para V1–V12; la ley vigente vive en `SPEC.md` de la raíz.

Distilado de `plan.md` §0–§1, §14, §16, §18. Los requisitos verificables viven en
`requirements.md`; este archivo define identidad y límites.

## Tesis

Los sistemas de venta peruanos tratan la factura como un printout del movimiento de
stock: mezclan anulación de caja con baja SUNAT, reutilizan correlativos internos como
serie tributaria y convierten al cliente en un CRM. NecoFact invierte eso:

> La operación comercial existe para producir un documento electrónico correcto,
> recuperable y corregible. Stock, caja y clientes son el mínimo que hace eso
> usable todos los días.

El producto es un **híbrido limpio** de lo ya probado por los proyectos hermanos:

| Herencia | De dónde |
|---|---|
| Escrito como Faztore | invariantes, SQL, poca ceremonia |
| Montado como Aeduca | Laravel 13, Actions, Inertia, Svelte 5, Lumi |
| Fronteras como Coedula | binario PDF externo, adapter SUNAT, service sólo si orquesta |
| Greenter | encapsulado en el último milímetro (`Support/Sunat`) |

## Qué es / qué no es

**Es:** un sistema de ventas para clientes, con POS, inventario, compras, caja y
ciclo de vida fiscal completo (CPE `01`/`03`/`07`, RC/RA, GRE remitente `09`).
Artefacto primario: el comprobante electrónico.

**No es:** plugin SUNAT para Faztore/Aeduca, ERP, CRM, POS que "imprime factura",
multi-tenant SaaS compartido, microservicio.

## Actores

| Actor | Usa | No usa |
|---|---|---|
| Cajero | POS `/sales/new`, clientes en diálogo, caja | series, retries manuales |
| Supervisor / admin | catálogo, compras, transferencias, `/cpe` (cola fiscal), settings | secrets en plano |
| Contador | `/cpe`, artefactos XML/CDR/PDF, reportes de serie | edición de operaciones |
| Adquirente (cliente final) | `/c/{token}` consulta pública sin login | nada más |

## Conceptos que definen el producto

1. **`Sale` y `FiscalDocument` son agregados distintos.** La relación es `1 — 0..n`
   (CPE inicial, luego notas). Una anulación de venta no borra un XML aceptado.
2. **El cliente es identidad tributaria**, no ficha comercial. El CPE lee siempre su
   propio snapshot, nunca la fila mutable de `customers`.
3. **La caja es ledger de efectivo físico**, scoped al cajero. Yape/plin/transferencia/
   tarjeta quedan en `sale_payments` y jamás entran a `cashbox_movements`.
4. **GRE no es una transferencia con otro nombre.** Es un documento SUNAT `09` con
   ticket y CDR propios, vía REST, desacoplado de la mutación de stock.
5. **El POS elige `01` o `03` antes de confirmar.** No existe "venta ahora, factura
   después" en el flujo normal.
6. **El correlativo fiscal se reserva una vez y no se devuelve.**
7. **Un timeout no es un rechazo.** Se resuelve consultando, nunca reemitiendo.

## Superficies v1

`/sales/new` (POS) · `/sales` · `/sales/{code}` · `/customers` · `/products` ·
`/inventory` · `/purchases` · `/transfers` · `/cashbox` · `/cpe` · `/gre/new` ·
`/settings/issuer` · `/c/{token}`.

El POS no espera a SUNAT: confirmar venta = 200 con `sale.code` +
`fiscal.status = queued | pending_summary`. El badge se actualiza por poll Inertia.

## Modelo de despliegue

1 RUC · 1 deployment · N sedes. Schema con `issuer_code` desde el día uno; v1 no
comparte DB entre emisores. NRUS del operador sirve para boleta/GRE; factura exige
piloto RER/RMT/Régimen General.

## Fuera de alcance (explícito)

Integrar SUNAT en Faztore/Aeduca/Coedula · microservicio SUNAT/Lycet · SaaS
multi-empresa · SIRE/RVIE/RCE/FV 621 · percepción/retención/detracción/anticipos/
crédito/exportación/ICBPER · ND `08` con UI · GRE transportista · CRM · sesiones de
caja abrir/cerrar · lotes/series/recetas · editar transferencias ligadas a GRE · PDF
dentro de PHP · regenerar XML en cada retry · tratar RUC 10 como "no factura" ·
`laravel-greenter` · catálogo público/storefront.

## Piloto real (orden)

1. Beta con RUC de prueba + `MODDATOS` → 2. Un cliente RER/RMT/RG · una sede ·
`F001` + `B001` → 3. Boletas reales de bajo monto → 4. Factura real → 5. NC →
6. GRE → 7. Más sedes.
