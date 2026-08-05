# Volt Controllers - Seguimiento De Desarrollo

## Objetivo

Este documento funciona como checklist viva del **sistema de controladores** de VoltStack (Controllers Engine).

Aqui se registra:

- lo que falta desarrollar
- lo que falta probar
- el estado actual de cada bloque
- el avance conforme se vaya implementando
- el corte actual del MVP

Este desarrollo sigue `vendor/voltstack/controller-lab/Docs` como referencia, excepto por el bloque excluido temporalmente:

- `Docs/Controller Security Model` (no se implementa en esta fase)

Lectura recomendada:

- matriz ejecutiva: [01_DEVELOPMENT_MATRIX.md](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/controller-lab/Docs/Development/01_DEVELOPMENT_MATRIX.md)
- arquitectura: [01_CONTROLLER_ARCHITECTURE.md](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/controller-lab/Docs/01_CONTROLLER_ARCHITECTURE.md)
- dispatcher: [03_CONTROLLER_DISPATCHER.md](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/controller-lab/Docs/03_CONTROLLER_DISPATCHER.md)
- invoker: [08_CONTROLLER_INVOKER.md](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/controller-lab/Docs/08_CONTROLLER_INVOKER.md)
- transformation: [09_RESULT_TRANSFORMATION_ENGINE.md](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/controller-lab/Docs/09_RESULT_TRANSFORMATION_ENGINE.md)
- lifecycle: [10_CONTROLLER_LIFECYCLE_AND_EXECUTION_STATE.md](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/controller-lab/Docs/10_CONTROLLER_LIFECYCLE_AND_EXECUTION_STATE.md)
- testing: [14_CONTROLLER_TESTING_ARCHITECTURE.md](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/controller-lab/Docs/14_CONTROLLER_TESTING_ARCHITECTURE.md)

## Convencion De Estado

- `[ ]` pendiente
- `[-]` en progreso
- `[x]` completado
- `[!]` pendiente critico o con riesgo

## Estado General Actual (Observado En Codigo)

Estado del framework hoy (antes del Controllers Engine):

- `[x]` dispatch de controllers por string `InvokableController::class`, `Class@method` y `[Class, method]` via [ControllerDispatcher.php](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/framework/src/Quantum/Routing/Dispatching/ControllerDispatcher.php)
- `[x]` resolucion de argumentos por reflection via [RouteArgumentResolver.php](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/framework/src/Quantum/Routing/Dispatching/RouteArgumentResolver.php)
- `[x]` normalizacion de respuesta por [ResponseNormalizer.php](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/framework/src/Quantum/Routing/Dispatching/ResponseNormalizer.php)
- `[ ]` engine formal de controllers (definition/context/resolver/invoker/normalizer) segun docs
- `[ ]` inyeccion + release de contexto (worker-safe para long-running / FrankenPHP)
- `[ ]` pruebas contractuales propias del nuevo pipeline (equivalencia con el comportamiento actual)

## Versionado De Desarrollo

Este documento usa versiones incrementales (no necesariamente SemVer publico) para marcar cortes de avance.

Regla:

- cada PR/cambio relevante agrega una entrada en el historial
- cada version debe tener: alcance, riesgos, archivos tocados y pruebas ejecutadas
- el MVP debe mantener compatibilidad con las rutas actuales del skeleton

### Historial

| Version | Estado | Corte                         | Fecha      | Resumen                                                                                                                                          | Pruebas                            |
| ------- | ------ | ----------------------------- | ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------- |
| 0.0.0   | `[x]`  | Baseline                      | 2026-08-02 | Dispatcher/argumentos/normalizacion actuales (sin engine formal)                                                                                 | `vendor/voltstack/framework/tests` |
| 0.1.0   | `[x]`  | MVP-1                         | 2026-08-02 | Controllers Engine minimo (dispatcher+invoker+normalize) + context inject/release (worker-safe)                                                  | `phpunit` (suite `framework`)      |
| 0.1.1   | `[x]`  | MVP-1.1                       | 2026-08-02 | ParameterResolutionEngine formal + errores estandar `controller.*`                                                                               | `phpunit` (suite `framework`)      |
| 0.2.0   | `[x]`  | MVP-2                         | 2026-08-02 | Interceptor system MVP (registry+resolver+pipeline around) via `controller.interceptors`                                                         | `phpunit` (suite `framework`)      |
| 0.2.1   | `[x]`  | MVP-2.1                       | 2026-08-02 | InterceptorDefinition: soporte de `priority`, `arguments` y `conditions` (condition registry + chain matching)                                   | `phpunit` (suite `framework`)      |
| 0.2.2   | `[x]`  | MVP-2.2                       | 2026-08-02 | Conditions: alias + formatos alternos (`type:value`, asociativo) + dedupe por `id` eligiendo mayor `priority`                                    | `phpunit` (suite `framework`)      |
| 0.3.0   | `[x]`  | Metadata Engine (V1)          | 2026-08-03 | Metadata Engine: sujeto Route + provider route + schemas base + integración Controllers (interceptors)                                           | `phpunit` (suite `framework`)      |
| 0.3.1   | `[x]`  | Metadata Engine (V1.1)        | 2026-08-03 | Providers: attributes/reflection para Controller class/method + merge con route metadata                                                         | `phpunit` (suite `framework`)      |
| 0.3.2   | `[x]`  | Metadata Engine (V1.2)        | 2026-08-03 | Atributos friendly Controllers: `#[Interceptors]` y `#[ParameterAliases]` (mapean a keys estándar)                                               | `phpunit` (suite `framework`)      |
| 0.3.3   | `[x]`  | Metadata Engine (V1.3)        | 2026-08-03 | Providers: config + convention (defaults por config y convenciones por namespace/route)                                                          | `phpunit` (suite `framework`)      |
| 0.3.4   | `[x]`  | Controllers Runtime (V1)      | 2026-08-03 | Runtime options desde metadata: `controller.lifecycle.*` + `controller.compilation.*` adjuntados a `ControllerExecution`                         | `phpunit` (suite `framework`)      |
| 0.4.0   | `[x]`  | Controller Lifecycle (V1)     | 2026-08-03 | Execution state mínimo (`created/running/succeeded/failed`) + captura de excepción en `ControllerExecution`                                      | `phpunit` (suite `framework`)      |
| 0.4.1   | `[x]`  | Controller Lifecycle (V1.1)   | 2026-08-03 | Short-circuit vs invoked (flags) + guard de doble invocación (`controller.already_invoked`)                                                      | `phpunit` (suite `framework`)      |
| 0.4.2   | `[x]`  | Controller Lifecycle (V1.2)   | 2026-08-03 | Short-circuit origin + short-circuit result almacenados en `ControllerExecution`                                                                 | `phpunit` (suite `framework`)      |
| 0.4.3   | `[x]`  | Controller Lifecycle (V1.3)   | 2026-08-03 | Short-circuit reason + metadata almacenados en `ControllerExecution`                                                                             | `phpunit` (suite `framework`)      |
| 0.4.4   | `[x]`  | Controller Lifecycle (V1.4)   | 2026-08-03 | Execution timeline mínimo (timestamps de created/running/invoked/short_circuited/succeeded/failed)                                               | `phpunit` (suite `framework`)      |
| 0.4.5   | `[x]`  | Controller Lifecycle (V1.5)   | 2026-08-03 | Helpers de duración derivados del timeline (`timelineAt`, `durationBetween`, `totalDuration`)                                                    | `phpunit` (suite `framework`)      |
| 0.4.6   | `[x]`  | Controller Lifecycle (V1.6)   | 2026-08-03 | Comportamiento según `controller.lifecycle.mode`: en `production` no se guarda timeline ni payloads sensibles (exception/result/reason/metadata) | `phpunit` (suite `framework`)      |
| 0.4.7   | `[x]`  | Controller Lifecycle (V1.7)   | 2026-08-03 | Timeouts (soft): usa `controller.lifecycle.timeouts.*` para marcar `timeout_exceeded` + `duration_seconds`                                       | `phpunit` (suite `framework`)      |
| 0.4.8   | `[x]`  | Controller Lifecycle (V1.8)   | 2026-08-03 | Metadata schemas para `controller.lifecycle.timeouts.enabled` y `controller.lifecycle.timeouts.default` con defaults deterministas               | `phpunit` (suite `framework`)      |
| 0.4.9   | `[x]`  | Controller Lifecycle (V1.9)   | 2026-08-04 | Metadata: `MetadataValueType::Float` + schema tipado para normalizar `controller.lifecycle.timeouts.default`                                     | `phpunit` (suite `framework`)      |
| 0.5.0   | `[x]`  | Controller Observability (V0) | 2026-08-04 | Observability mínima (Docs 11): contratos + dispatcher no-op + hooks de eventos en `ControllerEngine`                                            | `phpunit` (suite `framework`)      |
| 0.5.1   | `[x]`  | Controller Observability (V0.1) | 2026-08-04 | Dispatchers: in-memory (ring buffer) + JSON line sanitizado + estrategia auto por entorno/config (`controller_observability.*`)                  | `phpunit` (suite `framework`)      |
| 0.6.0   | `[x]`  | Response Transport (V0)       | 2026-08-04 | `Quantum\\Transport`: contratos + `ResponseTransportManager` + `HttpTransportAdapter` + emitters (null/in-memory) + bindings base + tests        | `phpunit` (suite `framework`)      |
| 0.6.1   | `[x]`  | Response Transport (V0.1)   | 2026-08-04 | Integración host: `public/index.php` emite vía `ResponseTransportManager` (transformer `Quantum\\Http\\Response` → `Quantum\\Transport\\Response`) | `phpunit` (suite `framework`)      |
| 0.6.2   | `[x]`  | Response Transport (V0.2)   | 2026-08-05 | Frontera Transport: `TransportKernelInterface` + `HttpKernelTransportKernel` + `public/index.php` usa la frontera como entrada de `ResponseInterface` + tests | `phpunit` (suite `framework`)      |
| 0.7.0   | `[x]`  | Exception Handling (V0)       | 2026-08-04 | `Quantum\\Exceptions`: handler V0 + context/result/enums + bridge `VoltStack\\Framework\\Exceptions\\ExceptionHandler` → Quantum handler + binding | `phpunit` (suite `framework`)      |
| 0.7.1   | `[x]`  | Exception Handling (V0.1)     | 2026-08-04 | Post-emisión: `TransportResult` propaga `TransportExecution` + `public/index.php` fallback seguro si falla antes de emitir + aborta si ya emitió | `phpunit` (suite `framework`)      |
| 0.7.2   | `[x]`  | Exception Handling (V0.2)     | 2026-08-04 | Worker-safety: `WorkerLifecycle` + política mínima de disposición (terminate/reset) integrada en handler Platform y host                         | `phpunit` (suite `framework`)      |
| 0.8.0   | `[x]`  | Runtime Stack Harness (QA)    | 2026-08-05 | Harness/lab de integración: `RuntimeLabController` + vista `runtime-lab-harness.volt.php` + ruta + `RuntimeStackHarnessLabTest` (summary/json/probe) para validar bindings de TransportKernel + Manager y smoke de send, sin loops recursivos | `phpunit` (suite `framework`, 3 tests verdes) |

## Plan Ejecutivo Recomendado (Corte Actual)

Prioridad: **cierre operativo y determinista** del MVP antes de abrir fases grandes (interceptors, metadata, compilation, observability).

### Bloque Activo 1. MVP-1 (Dispatcher + Invoker + Normalize)

- `[x]` crear `Quantum\Controllers` (definition/context/resolver/parameter engine/invoker/normalizer)
- `[x]` integrar el engine detras del dispatcher actual sin romper compatibilidad de rutas
- `[x]` garantizar `release()` del contexto en `finally` (ok y error)
- `[x]` garantizar que el resultado final sea `Quantum\Http\Response`
- `[x]` agregar pruebas unitarias/feature minimas de compatibilidad

Impacta directamente:

- `Quantum\Routing\Dispatching\ControllerDispatcher`
- `Quantum\Routing\Dispatching\RouteArgumentResolver`
- `Quantum\Routing\Dispatching\ResponseNormalizer` (solo si se decide reusar o delegar)
- tests en `vendor/voltstack/framework/tests`

### Bloque Activo 2. Parametros (Equivalencia + Guardrails)

- `[x]` formalizar `ParameterResolutionEngine` sin cambiar reglas actuales
- `[x]` mantener soporte de `RouteBindableInterface` y `Request` injection
- `[x]` mantener `MissingRouteBindingException` + metadata `missing` route handler
- `[x]` definir errores estandar del engine (sin Security Model)

Errores estandar (Controllers Engine):

| Codigo                                   | Tipo                                     | Cuando ocurre                                          |
| ---------------------------------------- | ---------------------------------------- | ------------------------------------------------------ |
| `controller.unsupported_action`          | `UnsupportedControllerActionException`   | action de ruta no soportada por el engine              |
| `controller.method_invalid`              | `InvalidControllerMethodException`       | nombre de metodo vacio o invalido                      |
| `controller.method_not_allowed`          | `ControllerMethodNotAllowedException`    | intento de invocar magic method distinto de `__invoke` |
| `controller.method_not_found`            | `ControllerMethodNotFoundException`      | metodo no existe en la instancia                       |
| `controller.method_not_public`           | `ControllerMethodNotPublicException`     | metodo existe pero no es publico                       |
| `controller.parameter_resolution_failed` | `ControllerParameterResolutionException` | falla al resolver argumentos (excluye binding missing) |

### Bloque Activo 3. Interceptors (MVP-2)

- `[x]` introducir `ControllerExecution` como contexto de ejecucion
- `[x]` registry de interceptores (descriptor + alias + freeze)
- `[x]` resolver de interceptores por `routeMetadata` key `controller.interceptors`
- `[x]` plan builder determinista (prioridad + estabilidad por orden original)
- `[x]` pipeline around con short-circuit (sin invocar controller si un interceptor no llama `proceed`)
- `[x]` pruebas contractuales minimas (orden, short-circuit, mutacion args, captura excepcion)
- `[x]` InterceptorDefinition en metadata con `priority`, `arguments` y `conditions`
- `[x]` conditions: alias + string `type:value` + array asociativo; dedupe por `id` con prioridad (no-repeatable)

### Bloque Activo 4. Metadata Engine (Docs 06)

- `[x]` introducir `Quantum\Metadata` (engine + provider pipeline + schema registry)
- `[x]` provider base: `RouteMetadataProvider` (route metadata -> fragments)
- `[x]` providers: `AttributeMetadataProvider` + `ReflectionMetadataProvider` (controller class/method)
- `[x]` providers: `ConfigMetadataProvider` + `ConventionMetadataProvider` (defaults)
- `[x]` schemas base: `controller.interceptors` (append) + `parameter_aliases` (replace)
- `[x]` integrar Controllers: `ControllerInterceptorResolver` consume `ControllerMetadataResolver`
- `[x]` pruebas unitarias base del engine

### Bloque Activo 5. Lifecycle + Execution State (Docs 10)

- `[x]` exponer `ControllerExecutionState` (enum) y almacenarlo en `ControllerExecution`
- `[x]` transiciones mínimas en el engine: `created` → `running` → `succeeded|failed`
- `[x]` capturar excepción en `ControllerExecution` cuando falle la ejecución
- `[x]` registrar `invoked` vs `short_circuited` de forma determinista
- `[x]` registrar `short_circuit_origin` y `short_circuit_result` para short-circuits por interceptores
- `[x]` registrar `short_circuit_reason` y `short_circuit_metadata` (diagnóstico/depuración)
- `[x]` exponer timeline mínimo de ejecución (timestamps por eventos)
- `[x]` respetar `controller.lifecycle.mode` para controlar diagnóstico (en `production` no guardar payloads)
- `[x]` timeouts (soft) desde `controller.lifecycle.timeouts.*` (`timeout_exceeded`, `timeout_seconds`, `duration_seconds`)
- `[x]` formalizar schemas de metadata para `controller.lifecycle.timeouts.enabled` y `controller.lifecycle.timeouts.default`
- `[x]` tipar y normalizar `controller.lifecycle.timeouts.default` como float en el Metadata Engine
- `[x]` prohibir doble invocación desde `InterceptorChain` (guard mínimo)
- `[x]` pruebas: estado final en success y en excepción

### Bloque Activo 6. Observability (Docs 11)

- `[x]` contratos mínimos: eventos, dispatcher y manager
- `[x]` implementación no-op default (no overhead cuando no hay integración)
- `[x]` hooks de eventos en `ControllerEngine` (created/started/invocation/completed/short-circuit)
- `[x]` pruebas unitarias: captura de eventos, `executionId` consistente y `sequence` creciente
- `[x]` implementar `ControllerEventDispatcherInterface` in-memory (útil para debugging local y harnesses)
- `[x]` implementar `ControllerEventDispatcherInterface` como logger estructurado (JSON line) con payload mínimo y sanitizado
- `[x]` definir estrategia de configuración/binding por entorno (ej. `local` vs `production`) para seleccionar dispatcher sin tocar el pipeline funcional
- `[x]` harness de integración QA (lab) para validar bindings de observability/transport via runtime probe autocontenido

### Bloque Activo 7. Response Transport System (http-lab)

- `[x]` introducir `Quantum\Transport` (contratos + tipos base) sin romper el pipeline actual
- `[x]` definir frontera: `TransportKernelInterface` produce `ResponseInterface` (abstracta) y el Transport la entrega (frontera mínima sobre `ResultTransformationEngine` vía bridge HttpKernel)
- `[x]` introducir `ResponseTransportManagerInterface` + pipeline mínimo con `TransportExecution` (created → prepared → emitted|failed)
- `[x]` introducir `TransportAdapterInterface` vs `TransportEmitterInterface` (separación preparación vs emisión)
- `[x]` implementar `Testing/InMemoryTransportEmitter` para contract tests (sin funciones globales)
- `[x]` definir política “exactly once”: prevenir doble emisión y registrar estado de emisión
- `[x]` integrar host de emisión (`public/index.php`) vía Transport Manager (sin cambiar el contrato actual de `HttpKernel`)
- `[x]` harness de integración QA (lab) `RuntimeStackHarnessLabTest`: summary page, JSON endpoint y probe request alineados a `SkeletonSpaRoadmapTest`

### Bloque Activo 8. Exception & Error Handling System (exception-lab)

- `[x]` introducir `Quantum\Exceptions` (contratos + modelos) como sistema transversal (no solo Controllers/HTTP)
- `[x]` `ExceptionHandlingContext` (incluye `ControllerExecution` y futuro `TransportExecution`)
- `[x]` pipeline mínimo: resolve → classify → map → render (sin reporting/recovery al inicio)
- `[x]` integración con `HttpKernel`: unificar captura de `Throwable` y producir representación segura
- `[x]` reglas de “post-emisión”: si el transporte ya inició, no intentar segunda respuesta (solo abort/mark incomplete)
- `[x]` preservar headers `X-Volt-Error-Code` para errores `controller.*` (consistencia contractual)
- `[x]` worker-safety: definir `WorkerDisposition` mínimo y reset request-scoped tras excepción
- `[x]` integración smoke en harness lab: validar `last_error === null` y `TransportStatus::Completed` por `ResponseTransportManager::send` (no-loop probe autocontenido)

### Bloques Postergados Explicitamente (No Iniciar Aun)

- `[ ]` compilation framework (Docs 13)
- `[ ]` Controller Security Model (carpeta excluida)

## Checklist De Desarrollo (MVP-1)

### A. Definicion, Contexto y Resolucion

- `[x]` `ControllerDefinition` y parser de referencias (`invokable`, `Class@method`, `[class, method]`)
- `[x]` `ControllerContext` / `ControllerExecutionContext` (request-scoped)
- `[x]` `ControllerResolver` (validaciones minimas: callable soportado, metodo publico, metodo no vacio)

### B. Inyeccion y Release De Contexto

- `[x]` `ControllerExecutionContextAwareInterface` (`set...` / `release...`)
- `[x]` `ControllerContextInjectorInterface` (inyectar y liberar sin fugas)
- `[x]` asegurar ejecucion de `release()` en `finally` incluso en excepcion

### C. Parameter Engine (Compatibilidad)

- `[x]` integrar `RouteArgumentResolver` como backend (sin reescritura de reglas)
- `[x]` soportar `parameter_aliases` desde `routeMetadata`
- `[x]` soportar `RouteBindableInterface` y `MissingRouteBindingException`

### D. Invoker (Ejecucion)

- `[x]` `ControllerInvokerInterface` (invoca y retorna `mixed` del controller)
- `[x]` prohibir doble invocacion accidental (guard basico a nivel execution)
- `[x]` no coercion de tipos en invoker (si hay mismatch: falla)

### E. Result Normalization (Respuesta Final)

- `[x]` normalizar `mixed` a `Response` con el mismo contrato actual de `ResponseNormalizer`
- `[x]` mantener compatibilidad con `View`, `Component`, `array`, `string|numeric`, `null`

### F. Interceptors (MVP-2)

- `[x]` `ControllerExecution` (definition/context/controller/arguments/executionContext/attributes)
- `[x]` `ControllerInterceptorInterface` + `ControllerInterceptorChainInterface`
- `[x]` `ControllerInterceptorRegistryInterface` + registry default singleton
- `[x]` `ControllerInterceptorResolver` (`controller.interceptors` desde route metadata)
- `[x]` `ControllerInterceptorPlanBuilder` (prioridad + orden estable)
- `[x]` `ControllerInterceptorPipeline` + chain + terminal de invocacion

## Checklist De Pruebas (MVP-1)

### 1. Resolucion de Target

- `[x]` invocable: `Route::get(..., HomeController::class)`
- `[x]` class@method: `Route::get(..., UserController::class . '@show')`
- `[x]` array callable: `Route::get(..., [UserController::class, 'show'])`

### 2. Release En Exito y Error

- `[x]` `release()` ocurre en success
- `[x]` `release()` ocurre en excepcion del controller

### 3. Normalizacion (Compatibilidad)

- `[x]` retorna `Response` si el controller retorna `Response`
- `[x]` retorna `JsonResponse` si el controller retorna `array`
- `[x]` retorna `Response` si el controller retorna `View` o `Component`

### 4. Interceptors (MVP-2)

- `[x]` orden determinista por prioridad
- `[x]` short-circuit (sin invocar controller)
- `[x]` mutacion de argumentos antes de invocar
- `[x]` captura de excepcion y recovery result

## Regla Operativa Del Documento

Cuando se trabaje en este corte:

1. marcar como `[-]` solo items del bloque activo
2. mover a `[x]` cada item apenas quede cerrado (codigo + pruebas)
3. no iniciar bloques postergados hasta cerrar MVP-1
4. si un cambio rompe compatibilidad, registrar el incidente en el historial con mitigacion y prueba asociada
