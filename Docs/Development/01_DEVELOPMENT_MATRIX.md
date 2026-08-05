# Volt Controllers - Matriz Ejecutiva (Desarrollo)

## Objetivo

Este documento es el mapa ejecutivo de implementacion del **Controllers Engine**.

Se usa para:

- ubicar rapidamente los archivos relevantes
- entender responsabilidades y dependencias
- marcar progreso (pendiente / en progreso / completado)
- mantener una lista corta de riesgos y pruebas asociadas

Alcance de esta matriz:

- implementacion en `vendor/voltstack/framework`
- ruta de integracion por `Quantum\Routing\Dispatching\ControllerDispatcher`
- excluye temporalmente `Docs/Controller Security Model`

## Convencion De Estado

- `[ ]` pendiente
- `[-]` en progreso
- `[x]` completado
- `[!]` riesgo o pendiente critico

## Matriz (Framework)

| Archivo | Rol | Estado | Notas / Dependencias |
|---|---|---:|---|
| [ControllerDispatcher.php](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/framework/src/Quantum/Routing/Dispatching/ControllerDispatcher.php) | Punto de entrada de dispatch de controllers (Router → DispatcherResolver → ControllerDispatcher) | `[x]` | Delegacion a `Quantum\Controllers\ControllerEngine` |
| [RouteArgumentResolver.php](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/framework/src/Quantum/Routing/Dispatching/RouteArgumentResolver.php) | Resolucion de argumentos por reflection + container + route binding | `[x]` | Se reusa como backend del MVP (sin cambios de reglas) |
| [MissingRouteHandler.php](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/framework/src/Quantum/Routing/Dispatching/MissingRouteHandler.php) | Manejo de binding faltante segun metadata `missing` | `[x]` | Se mantiene como comportamiento contractual |
| [ResponseNormalizer.php](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/framework/src/Quantum/Routing/Dispatching/ResponseNormalizer.php) | Normaliza `mixed` a `Response` | `[x]` | En MVP se reusa para normalizacion de controllers |
| [HttpKernel.php](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/framework/src/Quantum/HttpKernel/HttpKernel.php) | Orquestacion HTTP + middleware + normalizacion global | `[x]` | Debe seguir siendo compatible si ControllerDispatcher empieza a devolver `Response` |
| [DispatcherResolver.php](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/framework/src/Quantum/Routing/Dispatching/DispatcherResolver.php) | Seleccion de dispatcher por tipo de action | `[x]` | No cambia para MVP |
| [ExceptionHandler.php](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/framework/src/Platform/Exceptions/ExceptionHandler.php) | Adaptador `Framework\Contracts\ExceptionHandler` → `Quantum\Exceptions` | `[x]` | Preserva `X-Volt-Error-Code` (incluye `controller.*`) |

## Matriz (Controllers Engine - MVP-1)

| Archivo | Rol | Estado | Notas / Dependencias |
|---|---|---:|---|
| `src/Quantum/Controllers/ControllerEngine.php` | Orquestador MVP: resolve → args → invoke → normalize | `[x]` | Depende de resolver/argument resolver/invoker/normalizer |
| `src/Quantum/Controllers/ControllerDefinition.php` | Representa la definicion del controller (action) | `[x]` | Se construye desde `RouteMatch->route()->action()` |
| `src/Quantum/Controllers/ControllerContext.php` | Contexto de resolucion (app + request + routeMatch + metadata) | `[x]` | Base para evolucion a lifecycle |
| `src/Quantum/Controllers/ResolvedController.php` | Resultado del resolver: `instance + method` | `[x]` | Se usa para invocacion |
| `src/Quantum/Controllers/ControllerResolver.php` | Resuelve y valida target (`invokable`, `Class@method`, `[Class,method]`) | `[x]` | Debe mantener compatibilidad con reglas actuales |
| `src/Quantum/Controllers/ControllerExecutionContext.php` | Contexto request-scoped para inyeccion/release (worker-safe) | `[x]` | No debe retener referencias globales ni estaticas |
| `src/Quantum/Controllers/ControllerInvoker.php` | Invoca controller y garantiza release en `finally` | `[x]` | No transforma resultado a Response (eso es normalizer) |
| `src/Quantum/Controllers/ControllerContextInjector.php` | Inyecta/libera contexto en controllers aware | `[x]` | Implementa el contrato de base class opcional |
| `src/Quantum/Controllers/Contracts/ControllerExecutionContextAwareInterface.php` | Contrato para controllers que reciben contexto | `[x]` | `set...` y `release...` |
| `src/Quantum/Controllers/ParameterResolutionEngine.php` | Capa formal de resolucion de parametros (backend: RouteArgumentResolver) | `[x]` | Centraliza `parameter_aliases` y mantiene `RouteBindableInterface`/`MissingRouteBindingException` |
| `src/Quantum/Controllers/Exceptions/*` | Taxonomia de errores estandar del engine | `[x]` | Codigos `controller.*` |

## Matriz (Interceptors - MVP-2)

| Archivo | Rol | Estado | Notas / Dependencias |
|---|---|---:|---|
| `src/Quantum/Controllers/Execution/ControllerExecution.php` | Contexto mutable de ejecucion para interceptores | `[x]` | Contiene definition/context/controller/arguments/executionContext/attributes |
| `src/Quantum/Controllers/Interceptors/Contracts/ControllerInterceptorInterface.php` | Contrato principal de interceptor | `[x]` | Semantica around |
| `src/Quantum/Controllers/Interceptors/Contracts/ControllerInterceptorChainInterface.php` | Contrato de cadena | `[x]` | `proceed(execution): mixed` |
| `src/Quantum/Controllers/Interceptors/Contracts/ControllerInterceptorRegistryInterface.php` | Contrato de registry | `[x]` | Binding singleton en Application |
| `src/Quantum/Controllers/Interceptors/ControllerInterceptorRegistry.php` | Implementacion registry (descriptors + aliases + freeze) | `[x]` | Determinista, sin metadata engine |
| `src/Quantum/Controllers/Interceptors/InterceptorDescriptor.php` | Descriptor registrado (id/clase/scope/prioridad/fase) | `[x]` | Prioridad default para orden |
| `src/Quantum/Controllers/Interceptors/ControllerInterceptorPlanBuilder.php` | Ordena/normaliza plan | `[x]` | Prioridad desc + orden estable |
| `src/Quantum/Controllers/Interceptors/ControllerInterceptorResolver.php` | Resuelve interceptores desde `controller.interceptors` | `[x]` | Soporta ids/aliases registry y class-string directo; conditions en formatos: array, string alias, `type:value`, asociativo; dedupe por `id` (no-repeatable) eligiendo mayor `priority` |
| `src/Quantum/Controllers/Interceptors/ControllerInterceptorPipeline.php` | Ejecuta pipeline around y terminal de invocacion | `[x]` | Short-circuit si un interceptor no llama `proceed` |
| `src/Quantum/Controllers/Interceptors/ControllerInterceptorChain.php` | Chain mutable (index) | `[x]` | Instancia interceptores con container |
| `src/Quantum/Controllers/Interceptors/InterceptorDefinition.php` | Definicion declarativa (metadata) | `[x]` | Soporta `priority`, `arguments`, `conditions` |
| `src/Quantum/Controllers/Interceptors/ResolvedInterceptorDefinition.php` | Definicion resuelta para ejecucion | `[x]` | Contiene `id` (dedupe), conditions ya resueltas + `matches()` |
| `src/Quantum/Controllers/Interceptors/Conditions/InterceptorConditionRegistry.php` | Registry de conditions (type -> class) | `[x]` | Soporta alias (`post` -> `http_method:POST`) via `makeFrom()`; se registra en Application con defaults |
| `src/Quantum/Controllers/Interceptors/Conditions/*InterceptorCondition.php` | Condiciones iniciales | `[x]` | environment/http_method/route_name |
| `src/Quantum/Controllers/Exceptions/UnknownInterceptorConditionException.php` | Error condition desconocida | `[x]` | `controller.interceptor_condition_unknown` |
| `src/Quantum/Controllers/Exceptions/InvalidInterceptorConditionException.php` | Error condition invalida | `[x]` | `controller.interceptor_condition_invalid` |

## Matriz (Metadata Engine - Docs 06)

| Archivo | Rol | Estado | Notas / Dependencias |
|---|---|---:|---|
| `src/Quantum/Metadata/Contracts/MetadataEngineInterface.php` | Contrato principal de resolución | `[x]` | `resolve(MetadataRequest): MetadataBag` |
| `src/Quantum/Metadata/MetadataEngine.php` | Engine: collect → normalize → merge → bag | `[x]` | Cache in-memory determinista (request-level). Materializa `defaultValue` de schemas aun cuando `keys=[]` (no filtrado) |
| `src/Quantum/Metadata/MetadataProviderRegistry.php` | Registry de providers (orden estable) | `[x]` | Orden: priority desc + index asc |
| `src/Quantum/Metadata/MetadataProviderPipeline.php` | Pipeline para recolectar fragments | `[x]` | Filtra por `supports()` |
| `src/Quantum/Metadata/Schema/MetadataSchemaRegistry.php` | Registry de schemas | `[x]` | Define tipo + merge strategy + defaults (incluye `controller.lifecycle.*`, `controller.lifecycle.timeouts.*`, `controller.compilation.*`) |
| `src/Quantum/Metadata/Providers/RouteMetadataProvider.php` | Provider: route metadata → fragments | `[x]` | Integra con `RouteMatchSubject` |
| `src/Quantum/Metadata/Providers/ConfigMetadataProvider.php` | Provider: config -> fragments | `[x]` | Mapea `controller_lifecycle` y `controller_compilation` a keys `controller.*` |
| `src/Quantum/Metadata/Providers/AttributeMetadataProvider.php` | Provider: atributos PHP -> fragments | `[x]` | Soporta `#[Meta(...)]` y atributos friendly de Controllers |
| `src/Quantum/Controllers/Attributes/Interceptors.php` | Attribute friendly: interceptors | `[x]` | Mapea a `controller.interceptors` |
| `src/Quantum/Controllers/Attributes/ParameterAliases.php` | Attribute friendly: parameter aliases | `[x]` | Mapea a `parameter_aliases` |
| `src/Quantum/Metadata/Providers/ReflectionMetadataProvider.php` | Provider: reflection base -> fragments | `[x]` | Expone `controller.reflection.*` |
| `src/Quantum/Metadata/Providers/ConventionMetadataProvider.php` | Provider: convenciones -> fragments | `[x]` | Defaults por namespace y/o route |
| `src/Quantum/Metadata/Attributes/Meta.php` | Attribute base para metadata | `[x]` | `key/value/priority/final` |
| `src/Quantum/Metadata/Subjects/ControllerClassSubject.php` | Subject: controller class | `[x]` | Padre: route subject |
| `src/Quantum/Metadata/Subjects/ControllerMethodSubject.php` | Subject: controller method | `[x]` | Padre: controller class subject |
| `src/Quantum/Controllers/Metadata/ControllerMetadataResolver.php` | Adapter Controllers -> Metadata Engine | `[x]` | `ControllerInterceptorResolver` consume este resolver |

## Matriz (Controllers Runtime)

| Archivo | Rol | Estado | Notas / Dependencias |
|---|---|---:|---|
| `src/Quantum/Controllers/Runtime/ControllerRuntimeOptions.php` | DTO de runtime options | `[x]` | `lifecycleMode`, `compilationEnabled`, `compilationArtifactsFormat`, `timeoutsEnabled`, `timeoutDefaultSeconds` |
| `src/Quantum/Controllers/Runtime/ControllerRuntimeResolver.php` | Resolver runtime desde metadata | `[x]` | Lee `controller.lifecycle.*`, `controller.lifecycle.timeouts.*` y `controller.compilation.*` |
| `src/Quantum/Controllers/Runtime/ControllerRuntimeResolverInterface.php` | Contrato de resolver runtime | `[x]` | Inyectado en `ControllerEngine` |
| `src/Quantum/Controllers/Runtime/ControllerExecutionState.php` | Estado de ejecución (mínimo) | `[x]` | `created/running/succeeded/failed` |
| `src/Quantum/Controllers/Runtime/ControllerShortCircuitOrigin.php` | Origen del short-circuit | `[x]` | V1: `interceptor` |
| `src/Quantum/Controllers/ControllerEngine.php` | Hook runtime options | `[x]` | Adjunta `controller.runtime` como attribute en `ControllerExecution` + marca `controller.lifecycle.started_at` y evalúa timeouts (soft) para setear `duration_seconds`/`timeout_seconds`/`timeout_exceeded` |
| `src/Quantum/Controllers/Execution/ControllerExecution.php` | Execution API | `[x]` | Diagnóstico condicionado por `lifecycleMode` (production minimiza payloads); timeline + helpers de duración; helpers de timeout (`durationSeconds`, `timeoutSeconds`, `timeoutExceeded`) |
| `src/Quantum/Controllers/Exceptions/ControllerAlreadyInvokedException.php` | Guard: doble invocación | `[x]` | `controller.already_invoked` |

## Matriz (Observability - Docs 11)

| Archivo | Rol | Estado | Notas / Dependencias |
|---|---|---:|---|
| `src/Quantum/Controllers/Observability/Contracts/ControllerEventInterface.php` | Contrato de evento V1 (name/version/executionId/sequence/payload) | `[x]` | Payload mínimo, sin serializar request/response |
| `src/Quantum/Controllers/Observability/Contracts/ControllerEventDispatcherInterface.php` | Contrato dispatcher de eventos | `[x]` | Se implementa no-op por defecto |
| `src/Quantum/Controllers/Observability/Contracts/ControllerObservabilityManagerInterface.php` | Contrato manager mínimo (emit) | `[x]` | Punto único para no propagar try/catch por todo el pipeline |
| `src/Quantum/Controllers/Observability/Events/ControllerEvent.php` | Implementación inmutable de evento | `[x]` | Incluye `sequence` |
| `src/Quantum/Controllers/Observability/Events/EventSequence.php` | Secuenciador por ejecución | `[x]` | Request-scoped via `ControllerExecution` attributes |
| `src/Quantum/Controllers/Observability/Engine/ControllerObservabilityManager.php` | Manager V0: emite eventos sin bloquear ejecución | `[x]` | Si falla, marca `controller.observability.failed` |
| `src/Quantum/Controllers/Observability/Engine/NullControllerEventDispatcher.php` | Dispatcher no-op default | `[x]` | Evita overhead cuando no hay integración |
| `src/Quantum/Controllers/Observability/Engine/InMemoryControllerEventDispatcher.php` | Dispatcher in-memory (ring buffer) | `[x]` | Útil para debugging local/harness; evita crecimiento sin límite |
| `src/Quantum/Controllers/Observability/Engine/JsonLineControllerEventDispatcher.php` | Dispatcher logger JSON line | `[x]` | Sanitiza payload y escribe en `storage/framework/logs/controller-events.jsonl` por default |
| `src/Quantum/Controllers/ControllerEngine.php` | Hooks de eventos (created/started/invocation/completed/short-circuit) | `[x]` | Genera `controller.execution.id` |
| `config/controller_observability.php` | Config de selección de dispatcher | `[x]` | `dispatcher=auto|null|in_memory|jsonl`, `jsonl_path` opcional |

## Matriz (Response Transport System - http-lab)

| Archivo | Rol | Estado | Notas / Dependencias |
|---|---|---:|---|
| `src/Quantum/Transport/Contracts/ResponseInterface.php` | Contrato de respuesta abstracta (destino del framework) | `[x]` | Implementación actual: `TransportResponse` (inmutable) |
| `src/Quantum/Transport/Contracts/ResponseTransportManagerInterface.php` | Contrato del manager de transporte | `[x]` | Punto de entrada del sistema |
| `src/Quantum/Transport/ResponseTransportManager.php` | Manager V0: prepare→emit con estado | `[x]` | Integración incremental: `public/index.php` emite; `HttpKernel` se mantiene retornando `Quantum\\Http\\Response` |
| `src/Quantum/Transport/Contracts/TransportAdapterInterface.php` | Contrato de adapter (prepare sin E/S) | `[x]` | Separa preparación vs emisión |
| `src/Quantum/Transport/Contracts/TransportEmitterInterface.php` | Contrato de emitter (E/S) | `[x]` | Default no-op |
| `src/Quantum/Transport/Adapters/HttpTransportAdapter.php` | Adapter HTTP V0 (payload string + headers/status) | `[x]` | `TextResponseBody`/`EmptyResponseBody` |
| `src/Quantum/Transport/Emitters/HttpSapiEmitter.php` | Emitter HTTP (SAPI) V0.1 | `[x]` | Emite status/headers/body |
| `src/Quantum/Transport/Emitters/NullTransportEmitter.php` | Emitter no-op | `[x]` | Útil para pruebas/unit |
| `src/Quantum/Transport/Testing/InMemoryTransportEmitter.php` | Emitter in-memory para contract tests | `[x]` | Captura `PreparedTransportResponseInterface` |
| `src/Quantum/Transport/Bridges/Http/HttpResponseTransformer.php` | Bridge: `Quantum\\Http\\Response` → `Quantum\\Transport\\ResponseInterface` | `[x]` | Permite integración incremental sin reescribir `HttpKernel` |
| `src/Quantum/Transport/Contracts/TransportKernelInterface.php` | Contrato frontera: Request → `ResponseInterface` | `[x]` | Salida abstracta nativa (puerta para Transport destino) |
| `src/Quantum/Transport/Bridges/Http/HttpKernelTransportKernel.php` | Implementación mínima: delega en `Kernel` + `HttpResponseTransformer` | `[x]` | Compatibilidad mientras no exista un `ResultTransformationEngine` nativo |
| `app/Controllers/RuntimeLabController.php` | Lab de integración runtime: summary / JSON / probe | `[x]` | Resuelve bindings, config observability y smoke de `ResponseTransportManager::send` con probe autocontenido |
| `resources/views/runtime-lab-harness.volt.php` | Vista UI del lab | `[x]` | Expone markers `data-runtime-check` y navegación Resumen/JSON |
| `routes/web.php` | Ruta `/runtime-lab-harness` | `[x]` | `Route::get(...)->name('runtimeLabHarness')` |
| `src/Quantum/Transport/Runtime/TransportResult.php` | Resultado del transport (status/bytes/exception) | `[x]` | Propaga `TransportExecution` para awareness post-emisión (`emissionStarted`) |
| `public/index.php` | Host HTTP: emisión final de la respuesta | `[x]` | Usa frontera `TransportKernelInterface` + emite vía `ResponseTransportManagerInterface` + fallback a `Quantum\\Exceptions` si falla antes de emitir |

## Matriz (Exception & Error Handling System - exception-lab)

| Archivo | Rol | Estado | Notas / Dependencias |
|---|---|---:|---|
| `src/Quantum/Exceptions/Contracts/ExceptionHandlerInterface.php` | Contrato principal del handler transversal | `[x]` | Entrada `handle(Throwable, ExceptionHandlingContext)` |
| `src/Quantum/Exceptions/ExceptionHandler.php` | Handler V0: mapea status/headers y renderiza HTML/JSON/Volt | `[x]` | Compatible con tests actuales del Kernel |
| `src/Quantum/Exceptions/ExceptionHandlingContext.php` | Contexto de manejo de excepción | `[x]` | Incluye `Request`, `ControllerExecution` y futuro `TransportExecution` |
| `src/Quantum/Exceptions/ExceptionHandlingResult.php` | Resultado del handler | `[x]` | Incluye `WorkerDisposition` y `emissionStarted` |
| `src/Quantum/Exceptions/Enums/*` | Enums del sistema (origen/estado/disposición) | `[x]` | Base para expandir pipeline (classify/report) |
| `src/Quantum/Exceptions/Runtime/*` | Runtime context/state del handler | `[x]` | Tracking de status/attempts |
| [Application.php](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/framework/src/Platform/Application.php) | Bindings base del handler Quantum | `[x]` | `Quantum\\Exceptions\\Contracts\\ExceptionHandlerInterface` → `Quantum\\Exceptions\\ExceptionHandler` |
| `src/Runtime/Context/WorkerLifecycle.php` | Estado mínimo de disposición del worker | `[x]` | Singleton; no se limpia con `flushScope()` |
| [ExceptionHandler.php](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/framework/src/Platform/Exceptions/ExceptionHandler.php) | Registra `WorkerDisposition` devuelto por `Quantum\\Exceptions` | `[x]` | Marca terminate/reset para que el host decida |
| `public/index.php` | Aplica política mínima de worker-safety al final del request | `[x]` | Si `shouldTerminate()`: finaliza request y termina el proceso |

## Matriz (Tests)

| Archivo | Rol | Estado | Notas / Dependencias |
|---|---|---:|---|
| `vendor/voltstack/framework/tests/Unit/*` | Unit tests del engine y compatibilidad de dispatch | `[x]` | Cubre invocable, class@method, array callable, release (success/error), parameter_aliases, missing binding, normalizacion (Response/JsonResponse/View/string/null), lifecycle (state/timeline/short-circuit/production mode/timeouts soft), observability (eventos mínimos) y transport V0 |
| `vendor/voltstack/framework/tests/Unit/ControllerInterceptorSystemTest.php` | Contract tests de interceptores | `[x]` | Orden, short-circuit, mutacion de args, recovery por excepcion; conditions por alias, `type:value` y asociativo; dedupe por `id` con mayor `priority` |
| `vendor/voltstack/framework/tests/Unit/ResponseTransportManagerTest.php` | Contract tests de transport manager | `[x]` | Prepara + emite con `InMemoryTransportEmitter`; manejo de excepción en adapter |
| `vendor/voltstack/framework/tests/Unit/HttpResponseTransformerTest.php` | Test del bridge HTTP→Transport | `[x]` | Mapea status/headers/body |
| `vendor/voltstack/framework/tests/Unit/HttpKernelTransportKernelTest.php` | Test de frontera TransportKernel | `[x]` | Valida retorno `ResponseInterface` con status/headers/body de `Kernel` |
| `vendor/voltstack/framework/tests/Feature/RuntimeStackHarnessLabTest.php` | Harness lab QA (SkeletonSpaRoadmapTest-style) | `[x]` | Procesos separados; smoke summary page, JSON endpoint y probe request (200 + binding/ok) |
| `vendor/voltstack/framework/tests/Unit/ObservabilityDispatcherBindingTest.php` | Binding/estrategia auto de observability | `[x]` | `auto` selecciona in-memory en local + jsonl si `APP_ENV=production` y `jsonl_path` |
| `vendor/voltstack/framework/tests/Unit/InMemoryControllerEventDispatcherTest.php` | Dispatcher in-memory | `[x]` | Ring buffer, `events()` y `clear()` |
| `vendor/voltstack/framework/tests/Unit/JsonLineControllerEventDispatcherTest.php` | Dispatcher JSONL | `[x]` | Payload sanitizado, headers/cookies/tokens filtrados |

## Riesgos (Corte MVP-1)

- `[!]` compatibilidad: no romper dispatch actual (rutas invocables existentes)
- `[!]` worker-safety: asegurar `release()` aun cuando el controller falle
- `[!]` consistencia: no duplicar side effects al normalizar (evitar doble render de views/components)

## Evidencia / Pruebas Esperadas

- Ejecutar `phpunit` (suite `framework`) y mantener verde.
