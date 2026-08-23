# NecoFact — protocolo de ejecución

Gobierna cómo se cambia el código. La ley permanente vive en `docs/SPEC.md`.

## 1. Autoridad y lectura

Antes de cambiar código, leer:

1. `README.md`
2. `AGENTS.md`
3. `docs/SPEC.md`
4. `docs/STATUS.md`
5. el `TASK.md` de raíz, si existe
6. implementación y tests actuales
7. la evidencia hermana que `SPEC.md` nombra para ese vertical

| Fuente           | Dueño                                                      |
| ---------------- | ---------------------------------------------------------- |
| `docs/SPEC.md`   | Decisiones permanentes de producto, dominio, datos y SUNAT |
| `docs/STATUS.md` | Hechos de implementación verificados                       |
| `TASK.md`        | Un resultado observable temporal                           |
| Código existente | Baseline aceptado y patrones actuales                      |

Un task no anula la spec en silencio. La documentación no declara
implementado lo que el código no contiene.

Preservar la arquitectura actual salvo regla de producto o defecto
verificado. Si las fuentes chocan: inspeccionar código y evidencia
operativa, reportar el conflicto, corregir lo mínimo coherente.

Un refactor documental debe auditar cobertura. Compactar fusionando una
regla en su dueño; nunca borrar contexto válido sólo para reducir líneas.

## 2. Investigación antes de implementar

Para cada capacidad:

1. Inspeccionar código y tests de NecoFact primero.
2. Inspeccionar el patrón de runtime en Aeduca (`Actions`, FormRequests,
   Inertia, Lumi, permisos).
3. Inspeccionar el invariante operativo en Faztore (stock, compras, caja)
   o Coedula (idempotencia, caja = efectivo, binario externo).
4. Separar el comportamiento que vale de la estructura histórica (Kysely,
   `UUID[]`, `Number()`, `$see->send($doc)`).
5. Definir el resultado observable y la aceptación antes de ampliar.

Evidencia no es plantilla. Tampoco es contexto opcional. No inventar
semántica fiscal, de caja o de correlativo por conveniencia.

## 3. El baseline es el código de `main`

Extender los dueños ya presentes:

- `AuthAccount` autentica; `User` es la identidad de personal
- `PermissionResolver` + `PermissionDependency` autorizan
- `BranchContext` es la sede de sesión
- un Action enfocado escribe un agregado
- FormRequest normaliza y valida forma
- el controller compone queries/actions e Inertia
- Svelte presenta; Lumi es el único UI kit

No añadir segundo auth, capa de repositorios, `Services/` genérica,
`Domains/`, CQRS, ni un segundo sistema de UI.

### Forma

- Un dueño y un write path por responsabilidad.
- Action para write de agregado, transacción o invariante real.
- Alta/edición de fila simple: Eloquent directo.
- Prohibido: repositories genéricos, service gods, Actions base,
  librerías DTO que sólo renombran arrays, event sourcing.
- Subcarpetas sólo cuando el contenido actual lo exige.
- Consecuencias obligatorias: llamada directa. Events sólo con
  consumidores independientes.

### Datos

- PK UUID `code`; FK `<entidad>_code`.
- Relaciones con FK o tabla intermedia, nunca `UUID[]` ni JSON.
- PostgreSQL dueño de FK, UNIQUE y CHECK nombrados.
- Read models estables: vistas SQL. Laravel sigue autorizando y paginando.
- Escrituras multi-tabla de negocio: transacción.
- Sin soft delete por defecto. Jamás en fiscal.
- Nunca autorizar por nombre de rol.

### Permisos

- `dominio.view` / `dominio.manage`. `manage` implica `view`.
- No un permiso por botón, campo o tab.
- Permiso más específico sólo con evidencia operativa.

### Frontend

- Svelte 5 runes y TypeScript strict.
- Copy en español.
- Una navegación, un shell, `can()` UX-only, un dueño de notificación.
- Sólo componentes y tokens públicos de Lumi.
- Sin rutas, tabs, tablas, permisos o actions vacíos “para después”.

## 4. Alcance y completez

Implementar sólo el `TASK.md` activo, acotado por un resultado observable,
no por una tabla.

Una capacidad adyacente no es creep si el flujo no funciona sin ella.
Módulos decorativos futuros sí. Si la investigación prueba que el task
omitió algo necesario, se revisa el task antes de seguir.

Un vertical no está hecho porque existan migraciones, CRUD o tests.
Hace falta entrada, operación principal, autorización, persistencia,
listado/detalle y fallos correctos.

Detenerse cuando un término tiene dos significados, un campo no tiene
dueño, la evidencia contradice el task, un invariante no se puede
proteger, o un check requerido falla.

## 5. Base de datos y cierre

Desarrollo y tests usan `necofact_test`. Nunca `migrate:fresh` contra
`necofact`.

Tras schema o seed:

```bash
php artisan migrate:fresh --seed --env=testing
```

Cierre de código:

```bash
composer run format
composer run check
pnpm run build
```

Cierre sólo de docs: Prettier del markdown y `git diff --check`. No
declarar completo con un check relevante en rojo.

CI no llama a SUNAT. Nunca regenerar XML firmado en un retry.
Laboratorio y credenciales: `docs/SPEC.md` §3. Beta no es homologación ni
producción. Un RUC NRUS no es el emisor de prueba del SEE.

## 6. Cierre documental

Después de un vertical:

1. Fusionar reglas durables en `docs/SPEC.md`.
2. Reemplazar hechos actuales en `docs/STATUS.md`.
3. Actualizar checkboxes y estado en `TASK.md`.
4. No dejar roadmaps o specs competidores.
