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

| Version | Estado | Corte | Fecha | Resumen | Pruebas |
|---|---|---|---|---|---|
| 0.0.0 | `[x]` | Baseline | 2026-08-02 | Dispatcher/argumentos/normalizacion actuales (sin engine formal) | `vendor/voltstack/framework/tests` |
| 0.1.0 | `[x]` | MVP-1 | 2026-08-02 | Controllers Engine minimo (dispatcher+invoker+normalize) + context inject/release (worker-safe) | `phpunit` (suite `framework`) |
| 0.1.1 | `[x]` | MVP-1.1 | 2026-08-02 | ParameterResolutionEngine formal + errores estandar `controller.*` | `phpunit` (suite `framework`) |
| 0.2.0 | `[x]` | MVP-2 | 2026-08-02 | Interceptor system MVP (registry+resolver+pipeline around) via `controller.interceptors` | `phpunit` (suite `framework`) |
| 0.2.1 | `[x]` | MVP-2.1 | 2026-08-02 | InterceptorDefinition: soporte de `priority`, `arguments` y `conditions` (condition registry + chain matching) | `phpunit` (suite `framework`) |
| 0.2.2 | `[x]` | MVP-2.2 | 2026-08-02 | Conditions: alias + formatos alternos (`type:value`, asociativo) + dedupe por `id` eligiendo mayor `priority` | `phpunit` (suite `framework`) |

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

| Codigo | Tipo | Cuando ocurre |
|---|---|---|
| `controller.unsupported_action` | `UnsupportedControllerActionException` | action de ruta no soportada por el engine |
| `controller.method_invalid` | `InvalidControllerMethodException` | nombre de metodo vacio o invalido |
| `controller.method_not_allowed` | `ControllerMethodNotAllowedException` | intento de invocar magic method distinto de `__invoke` |
| `controller.method_not_found` | `ControllerMethodNotFoundException` | metodo no existe en la instancia |
| `controller.method_not_public` | `ControllerMethodNotPublicException` | metodo existe pero no es publico |
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

### Bloques Postergados Explicitamente (No Iniciar Aun)

- `[ ]` metadata engine (Docs 06)
- `[ ]` compilation framework (Docs 13)
- `[ ]` observabilidad unificada (Docs 11)
- `[ ]` Controller Security Model (carpeta excluida)

## Checklist De Desarrollo (MVP-1)

### A. Definicion, Contexto y Resolucion

- `[ ]` `ControllerDefinition` y parser de referencias (`invokable`, `Class@method`, `[class, method]`)
- `[ ]` `ControllerContext` / `ControllerExecutionContext` (request-scoped)
- `[ ]` `ControllerResolver` (validaciones minimas: callable soportado, metodo publico, metodo no vacio)

### B. Inyeccion y Release De Contexto

- `[ ]` `ControllerExecutionContextAwareInterface` (`set...` / `release...`)
- `[ ]` `ControllerContextInjectorInterface` (inyectar y liberar sin fugas)
- `[ ]` asegurar ejecucion de `release()` en `finally` incluso en excepcion

### C. Parameter Engine (Compatibilidad)

- `[x]` integrar `RouteArgumentResolver` como backend (sin reescritura de reglas)
- `[x]` soportar `parameter_aliases` desde `routeMetadata`
- `[x]` soportar `RouteBindableInterface` y `MissingRouteBindingException`

### D. Invoker (Ejecucion)

- `[ ]` `ControllerInvokerInterface` (invoca y retorna `mixed` del controller)
- `[ ]` prohibir doble invocacion accidental (guard basico a nivel execution)
- `[ ]` no coercion de tipos en invoker (si hay mismatch: falla)

### E. Result Normalization (Respuesta Final)

- `[ ]` normalizar `mixed` a `Response` con el mismo contrato actual de `ResponseNormalizer`
- `[ ]` mantener compatibilidad con `View`, `Component`, `array`, `string|numeric`, `null`

### F. Interceptors (MVP-2)

- `[x]` `ControllerExecution` (definition/context/controller/arguments/executionContext/attributes)
- `[x]` `ControllerInterceptorInterface` + `ControllerInterceptorChainInterface`
- `[x]` `ControllerInterceptorRegistryInterface` + registry default singleton
- `[x]` `ControllerInterceptorResolver` (`controller.interceptors` desde route metadata)
- `[x]` `ControllerInterceptorPlanBuilder` (prioridad + orden estable)
- `[x]` `ControllerInterceptorPipeline` + chain + terminal de invocacion

## Checklist De Pruebas (MVP-1)

### 1. Resolucion de Target

- `[ ]` invocable: `Route::get(..., HomeController::class)`
- `[ ]` class@method: `Route::get(..., UserController::class . '@show')`
- `[ ]` array callable: `Route::get(..., [UserController::class, 'show'])`

### 2. Release En Exito y Error

- `[ ]` `release()` ocurre en success
- `[ ]` `release()` ocurre en excepcion del controller

### 3. Normalizacion (Compatibilidad)

- `[ ]` retorna `Response` si el controller retorna `Response`
- `[ ]` retorna `JsonResponse` si el controller retorna `array`
- `[ ]` retorna `Response` si el controller retorna `View` o `Component`

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
