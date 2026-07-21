# Sistema de eventos y observabilidad de controladores en VoltStack


**Versión:** 1.0
**Estado:** Draft arquitectónico
**Módulo:** `VoltStack\Quantum\Controllers\Observability`
**Ámbito:** Pipeline completo de ejecución de controladores
**Integraciones principales:** Lifecycle, Events, Logging, Metrics, Tracing, Exceptions, Transport, FrankenPHP y OpenTelemetry

---

## 1. Introducción

El **Controller Events and Observability System** es el subsistema responsable de observar, registrar y exponer el comportamiento del pipeline completo de controladores de VoltStack.

Este sistema deberá proporcionar una visión coherente de:

* inicio y finalización de ejecuciones;
* fases visitadas;
* duración de cada etapa;
* resolución del controlador;
* resolución de metadata;
* resolución de parámetros;
* ejecución de interceptores;
* invocación;
* transformación de resultados;
* transporte de respuestas;
* short-circuits;
* cancelaciones;
* errores;
* recuperación;
* cleanup;
* uso de recursos;
* salud del Worker.

La observabilidad no deberá alterar el comportamiento funcional del controlador.

Su función será describir qué ocurrió, cuándo ocurrió y cuánto costó.

---

## 2. Objetivo principal

Proporcionar una infraestructura transversal de eventos, métricas, tracing, logging y profiling para toda ejecución de controlador.

```text
ControllerExecution
        │
        ├── Events
        ├── Metrics
        ├── Traces
        ├── Logs
        ├── Timeline
        ├── Profiles
        └── Diagnostics
```

---

## 3. Problema arquitectónico

Sin una capa unificada de observabilidad, cada módulo podría registrar información de manera diferente.

Esto produciría:

* eventos duplicados;
* nombres inconsistentes;
* métricas incompatibles;
* spans desconectados;
* logs sin correlación;
* payloads con datos sensibles;
* instrumentation overhead excesivo;
* dificultades para reconstruir el flujo completo;
* herramientas de debugging fragmentadas.

El sistema deberá definir una única convención transversal.

---

## 4. Principios arquitectónicos

El sistema seguirá estos principios:

* Los eventos describen hechos ocurridos.
* Las métricas describen comportamiento agregado.
* Los traces describen causalidad y duración.
* Los logs describen hechos detallados.
* El profiling describe consumo de recursos.
* La observabilidad no deberá modificar el flujo funcional.
* Los datos públicos deberán estar sanitizados.
* Los IDs deberán permitir correlación end-to-end.
* Los listeners no críticos no deberán bloquear la ejecución.
* Los eventos de dominio no deberán mezclarse con eventos de infraestructura.
* Los Workers persistentes no deberán conservar estado de observabilidad entre peticiones.
* La instrumentación deberá ser desactivable y configurable.
* Los planes de observabilidad podrán compilarse.

---

## 5. No responsabilidades

El sistema no deberá:

* ejecutar lógica de negocio;
* modificar respuestas;
* reintentar operaciones;
* manejar excepciones funcionalmente;
* autorizar usuarios;
* transformar resultados;
* emitir respuestas;
* almacenar indefinidamente traces;
* implementar un backend de observabilidad completo;
* sustituir sistemas externos como OpenTelemetry, Prometheus o plataformas de logging.

VoltStack deberá generar señales y delegar su exportación.

---

## 6. Posición dentro del pipeline

```text
ControllerExecutionManager
        │
        ▼
Controller Lifecycle
        │
        ├── Event Publisher
        ├── Metric Recorder
        ├── Trace Recorder
        ├── Timeline Recorder
        ├── Profiler
        └── Diagnostic Collector
```

Todos los motores del pipeline podrán emitir señales mediante contratos compartidos.

---

## 7. Arquitectura general

```text
Execution Signal
        │
        ▼
ObservabilityContext
        │
        ▼
SignalNormalizer
        │
        ▼
ObservabilityPolicyResolver
        │
        ▼
ObservabilityPipeline
        │
        ├── Sanitize
        ├── Enrich
        ├── Sample
        ├── Record
        ├── Export
        └── Finalize
```

---

## 8. Componentes principales

```text
ControllerObservabilityManager
ObservabilityContext
ObservabilityPolicy
ObservabilityPolicyResolver
ControllerEvent
ControllerEventDispatcher
ControllerMetricRecorder
ControllerTraceRecorder
ControllerLogEnricher
ControllerProfiler
ControllerTimelineRecorder
ControllerDiagnosticCollector
CorrelationContext
SamplingPolicy
ObservabilityRegistry
ObservabilityCompiler
CompiledObservabilityPlan
```

---

## 9. ControllerObservabilityManager

Punto de entrada principal.

```php
interface ControllerObservabilityManagerInterface
{
    public function record(
        ControllerObservabilitySignal $signal,
        ObservabilityContext $context
    ): void;

    public function finalize(
        ControllerExecution $execution
    ): ControllerObservabilityResult;
}
```

Implementación oficial:

```php
final class ControllerObservabilityManager
    implements ControllerObservabilityManagerInterface
{
}
```

---

## 10. Responsabilidades del Manager

El Manager deberá:

1. recibir señales;
2. normalizarlas;
3. enriquecerlas;
4. aplicar sanitización;
5. aplicar sampling;
6. publicar eventos;
7. registrar métricas;
8. crear o completar spans;
9. enriquecer logs;
10. registrar profiling;
11. producir diagnósticos;
12. finalizar la observabilidad de la ejecución.

---

## 11. ControllerObservabilitySignal

Contrato base de señal.

```php
interface ControllerObservabilitySignal
{
    public function name(): string;

    public function category(): ObservabilitySignalCategory;

    public function timestamp(): float;

    public function attributes(): array;
}
```

---

## 12. ObservabilitySignalCategory

```php
enum ObservabilitySignalCategory: string
{
    case Lifecycle = 'lifecycle';
    case Resolution = 'resolution';
    case Invocation = 'invocation';
    case Transformation = 'transformation';
    case Transport = 'transport';
    case Exception = 'exception';
    case Cancellation = 'cancellation';
    case ShortCircuit = 'short_circuit';
    case Resource = 'resource';
    case Cleanup = 'cleanup';
    case Worker = 'worker';
    case Performance = 'performance';
    case Diagnostic = 'diagnostic';
}
```

---

## 13. ObservabilityContext

Representa el contexto común de todas las señales.

```php
final readonly class ObservabilityContext
{
    public function __construct(
        public ControllerExecutionId $executionId,
        public CorrelationContext $correlation,
        public RuntimeContext $runtime,
        public MetadataBag $metadata,
        public ObservabilityPolicy $policy,
        public bool $debug,
    ) {
    }
}
```

---

## 14. CorrelationContext

Contiene identificadores relacionados.

```php
final readonly class CorrelationContext
{
    public function __construct(
        public string $requestId,
        public string $executionId,
        public string $traceId,
        public ?string $spanId,
        public ?string $parentSpanId,
        public ?string $sessionCorrelationId,
        public ?string $tenantCorrelationId,
    ) {
    }
}
```

---

## 15. Identificadores

VoltStack distinguirá:

```text
Request ID
Execution ID
Trace ID
Span ID
Parent Span ID
Public Error ID
Worker ID
Subrequest Parent ID
```

No deberán utilizarse como equivalentes.

---

## 16. Request ID

Identifica la petición o mensaje de entrada.

Puede provenir de:

* reverse proxy;
* gateway;
* cliente autorizado;
* runtime;
* generador interno.

Los IDs externos deberán validarse antes de reutilizarlos.

---

## 17. Execution ID

Identifica una ejecución concreta del lifecycle.

Un mismo Request podrá originar:

* ejecución principal;
* subrequests;
* ejecuciones de componentes;
* renderizados internos.

---

## 18. Trace ID

Agrupa operaciones causales distribuidas.

Deberá propagarse a:

* consultas;
* clientes HTTP;
* colas;
* subrequests;
* runtime SPA;
* logs;
* métricas ejemplares cuando aplique.

---

## 19. Span ID

Identifica una operación dentro del trace.

Cada fase relevante podrá tener su propio span.

---

## 20. Correlation ID validation

El sistema deberá impedir:

* IDs excesivamente largos;
* caracteres inválidos;
* inyección en logs;
* colisiones por fuentes no confiables;
* uso directo de datos sensibles como IDs.

---

## 21. ObservabilityPolicy

```php
final readonly class ObservabilityPolicy
{
    public function __construct(
        public bool $events,
        public bool $metrics,
        public bool $tracing,
        public bool $logging,
        public bool $profiling,
        public bool $timeline,
        public bool $diagnostics,
        public SamplingPolicy $sampling,
        public array $sanitizers,
        public array $exporters,
    ) {
    }
}
```

---

## 22. Policy resolution

La política podrá resolverse según:

1. configuración global;
2. entorno;
3. metadata de ruta;
4. tipo de controlador;
5. categoría de señal;
6. severidad;
7. runtime;
8. estado Debug.

---

## 23. Metadata keys

```text
observability.enabled
observability.events
observability.metrics
observability.tracing
observability.logging
observability.profiling
observability.timeline
observability.sample_rate
observability.tags
observability.sensitive
observability.exporters
```

---

## 24. Attributes potenciales

```php
#[Observed]
#[WithoutObservability]
#[Trace]
#[WithoutTracing]
#[Profile]
#[MetricTag('module', 'billing')]
#[SampleRate(0.25)]
#[SensitiveTelemetry]
```

El módulo consumirá metadata ya resuelta.

---

## 25. Catálogo oficial de eventos

Los eventos oficiales deberán utilizar nombres estables y versionables.

Convención:

```text
controllers.<domain>.<action>
```

Ejemplos:

```text
controllers.execution.created
controllers.execution.started
controllers.controller.resolved
controllers.parameters.resolved
controllers.invocation.started
controllers.invocation.completed
controllers.execution.completed
```

---

## 26. Eventos de lifecycle

```text
controllers.execution.created
controllers.execution.initializing
controllers.execution.started
controllers.execution.completing
controllers.execution.completed
controllers.execution.terminated
```

---

## 27. Eventos de resolución

```text
controllers.route.prepared
controllers.controller.resolution_started
controllers.controller.resolved
controllers.metadata.resolution_started
controllers.metadata.resolved
controllers.parameters.resolution_started
controllers.parameters.resolved
controllers.interceptors.resolved
```

---

## 28. Eventos de interceptores

```text
controllers.interceptors.pipeline_started
controllers.interceptors.entered
controllers.interceptors.exited
controllers.interceptors.short_circuited
controllers.interceptors.pipeline_completed
controllers.interceptors.pipeline_failed
```

---

## 29. Eventos de invocación

```text
controllers.invocation.started
controllers.invocation.completed
controllers.invocation.failed
controllers.invocation.skipped
```

---

## 30. Eventos de transformación

```text
controllers.transformation.started
controllers.transformation.strategy_resolved
controllers.transformation.completed
controllers.transformation.failed
```

---

## 31. Eventos de transporte

```text
controllers.transport.started
controllers.transport.prepared
controllers.transport.headers_emitted
controllers.transport.body_started
controllers.transport.body_completed
controllers.transport.completed
controllers.transport.failed
controllers.transport.client_disconnected
```

---

## 32. Eventos de short-circuit

```text
controllers.execution.short_circuit_detected
controllers.execution.short_circuited
controllers.execution.short_circuit_completed
```

---

## 33. Eventos de cancelación

```text
controllers.execution.cancellation_requested
controllers.execution.cancelling
controllers.execution.cancelled
controllers.execution.cancellation_ignored
```

---

## 34. Eventos de excepciones

```text
controllers.exception.captured
controllers.exception.classified
controllers.exception.reported
controllers.exception.recovery_started
controllers.exception.recovered
controllers.exception.rendered
controllers.exception.failed
```

---

## 35. Eventos de recursos

```text
controllers.resource.acquired
controllers.resource.transferred
controllers.resource.released
controllers.resource.release_failed
controllers.resource.leak_detected
```

---

## 36. Eventos de cleanup

```text
controllers.cleanup.started
controllers.cleanup.resource_released
controllers.cleanup.failed
controllers.cleanup.completed
```

---

## 37. Eventos de Worker

```text
controllers.worker.health_evaluated
controllers.worker.reset_requested
controllers.worker.reset_completed
controllers.worker.restart_recommended
controllers.worker.termination_requested
```

---

## 38. Eventos de profiling

```text
controllers.profile.started
controllers.profile.snapshot
controllers.profile.completed
controllers.profile.threshold_exceeded
```

---

## 39. Contrato de eventos

```php
interface ControllerEventInterface
{
    public function name(): string;

    public function version(): int;

    public function executionId(): ControllerExecutionId;

    public function occurredAt(): DateTimeImmutable;

    public function payload(): array;
}
```

---

## 40. Event versioning

Los eventos deberán incluir versión.

Ejemplo:

```text
controllers.execution.completed:v1
```

La versión cambiará cuando exista una modificación incompatible del payload.

---

## 41. Payload base

Todos los eventos podrán incluir:

```text
event_name
event_version
timestamp
execution_id
request_id
trace_id
span_id
phase
status
runtime
route_name
controller
```

---

## 42. Payload mínimo

Los eventos de alta frecuencia deberán utilizar payloads pequeños.

No se deberá serializar automáticamente:

* `ControllerExecution`;
* Request completo;
* Response completa;
* stack trace completo;
* body;
* arguments;
* service container.

---

## 43. Event dispatcher

```php
interface ControllerEventDispatcherInterface
{
    public function dispatch(
        ControllerEventInterface $event
    ): void;
}
```

---

## 44. Event listeners

Los listeners se clasificarán como:

```php
enum EventListenerMode: string
{
    case Synchronous = 'synchronous';
    case Deferred = 'deferred';
    case Buffered = 'buffered';
    case Async = 'async';
}
```

La V1 podrá implementar sincronía y buffering local.

---

## 45. Listeners síncronos

Solo deberán usarse cuando:

* el resultado sea necesario inmediatamente;
* el coste sea pequeño;
* la operación sea crítica para observabilidad;
* no introduzca lógica de negocio.

---

## 46. Listeners diferidos

Podrán ejecutarse al finalizar la petición.

Ejemplos:

* exportación de métricas;
* envío de spans;
* escritura de perfiles;
* flush de buffers.

---

## 47. Listener failure policy

Un listener de observabilidad que falle:

* no deberá modificar la respuesta;
* no reemplazará la excepción principal;
* se registrará como fallo secundario;
* podrá desactivarse temporalmente;
* podrá afectar la salud del Worker si corrompe estado.

---

## 48. Event ordering

Los eventos de una misma ejecución deberán conservar orden lógico.

```text
created
started
phase started
phase completed
completed
cleanup
```

En exportación asíncrona podrán llegar fuera de orden, por lo que deberán incluir timestamp y sequence number.

---

## 49. EventSequence

```php
final class EventSequence
{
    public function next(): int;
}
```

Cada evento de ejecución incluirá:

```text
sequence
```

---

## 50. Métricas

VoltStack deberá soportar:

* counters;
* gauges;
* histograms;
* up-down counters;
* timers;
* exemplars cuando el backend lo permita.

---

## 51. Metric contract

```php
interface ControllerMetricRecorderInterface
{
    public function increment(
        string $name,
        int|float $value = 1,
        array $labels = []
    ): void;

    public function record(
        string $name,
        int|float $value,
        array $labels = []
    ): void;
}
```

---

## 52. Convención de métricas

```text
voltstack.controllers.<metric>
```

Ejemplos:

```text
voltstack.controllers.execution.total
voltstack.controllers.execution.duration
voltstack.controllers.invocation.duration
voltstack.controllers.cleanup.failures
```

---

## 53. Métricas principales

```text
voltstack.controllers.execution.total
voltstack.controllers.execution.active
voltstack.controllers.execution.duration
voltstack.controllers.execution.completed
voltstack.controllers.execution.failed
voltstack.controllers.execution.cancelled
voltstack.controllers.execution.short_circuited
```

---

## 54. Métricas de resolución

```text
voltstack.controllers.resolution.controller.duration
voltstack.controllers.resolution.metadata.duration
voltstack.controllers.resolution.parameters.duration
voltstack.controllers.resolution.interceptors.duration
voltstack.controllers.resolution.cache_hits
voltstack.controllers.resolution.cache_misses
```

---

## 55. Métricas de invocación

```text
voltstack.controllers.invocation.total
voltstack.controllers.invocation.duration
voltstack.controllers.invocation.failed
voltstack.controllers.invocation.skipped
voltstack.controllers.invocation.retry
```

---

## 56. Métricas de transformación

```text
voltstack.controllers.transformation.total
voltstack.controllers.transformation.duration
voltstack.controllers.transformation.failed
voltstack.controllers.transformation.by_strategy
voltstack.controllers.transformation.compiled_plan
voltstack.controllers.transformation.dynamic_plan
```

---

## 57. Métricas de transporte

```text
voltstack.controllers.transport.total
voltstack.controllers.transport.duration
voltstack.controllers.transport.bytes
voltstack.controllers.transport.failed
voltstack.controllers.transport.disconnects
voltstack.controllers.transport.streaming
```

---

## 58. Métricas de recursos

```text
voltstack.controllers.resources.acquired
voltstack.controllers.resources.released
voltstack.controllers.resources.active
voltstack.controllers.resources.leaked
voltstack.controllers.resources.release_failed
```

---

## 59. Métricas de cleanup

```text
voltstack.controllers.cleanup.duration
voltstack.controllers.cleanup.failed
voltstack.controllers.cleanup.partial
```

---

## 60. Métricas de Workers

```text
voltstack.controllers.worker.reused
voltstack.controllers.worker.reset
voltstack.controllers.worker.restart_recommended
voltstack.controllers.worker.terminated
voltstack.controllers.worker.memory_after_execution
```

---

## 61. Cardinalidad

El sistema deberá controlar la cardinalidad de labels.

No deberán utilizarse como labels no acotados:

* user ID;
* request ID;
* execution ID;
* URL completa;
* mensaje de excepción;
* query string;
* nombres dinámicos de recursos.

---

## 62. Labels recomendados

```text
route
controller
method
result_type
transport
status_class
exception_category
runtime
compiled
short_circuit_origin
cancellation_reason
```

Siempre que sus valores sean acotados.

---

## 63. CardinalityGuard

```php
interface CardinalityGuardInterface
{
    public function sanitizeLabels(
        string $metric,
        array $labels
    ): array;
}
```

---

## 64. Tracing

Cada ejecución tendrá un span raíz:

```text
voltstack.controller.execution
```

---

## 65. Span raíz

Atributos recomendados:

```text
voltstack.execution.id
voltstack.controller.class
voltstack.controller.method
voltstack.route.name
voltstack.runtime
voltstack.compiled
http.request.method
http.route
```

No deberá incluir datos sensibles.

---

## 66. Spans por fase

```text
voltstack.controller.resolve
voltstack.controller.metadata
voltstack.controller.parameters
voltstack.controller.interceptors
voltstack.controller.invoke
voltstack.controller.transform
voltstack.controller.transport
voltstack.controller.cleanup
voltstack.controller.exception
```

---

## 67. Span contract

```php
interface ControllerTraceRecorderInterface
{
    public function startSpan(
        string $name,
        array $attributes = [],
        ?TraceContext $parent = null
    ): ControllerSpan;

    public function endSpan(
        ControllerSpan $span,
        SpanStatus $status = SpanStatus::Ok
    ): void;
}
```

---

## 68. SpanStatus

```php
enum SpanStatus: string
{
    case Unset = 'unset';
    case Ok = 'ok';
    case Error = 'error';
    case Cancelled = 'cancelled';
}
```

---

## 69. Span events

Dentro de un span podrán registrarse:

```text
short_circuit
cancellation_requested
retry
cache_hit
client_disconnected
resource_leak
exception
```

---

## 70. Exception recording

Las excepciones podrán registrarse en spans con:

* clase;
* categoría;
* severidad;
* fingerprint;
* handled;
* escaped.

El mensaje podrá sanitizarse según política.

---

## 71. Trace propagation

La propagación deberá soportar:

* headers HTTP;
* subrequests;
* runtime SPA;
* clientes HTTP;
* jobs;
* eventos;
* WebSockets futuros;
* gRPC futuro.

---

## 72. OpenTelemetry

VoltStack deberá proporcionar una integración opcional con OpenTelemetry.

```text
VoltStack signal
    │
    ▼
OpenTelemetry Adapter
    │
    ▼
OTel SDK
    │
    ▼
OTLP Exporter
```

La arquitectura interna no dependerá directamente del SDK.

---

## 73. OpenTelemetry adapter

```php
interface OpenTelemetryControllerAdapterInterface
{
    public function exportEvent(
        ControllerEventInterface $event
    ): void;

    public function exportMetric(
        ControllerMetric $metric
    ): void;

    public function exportSpan(
        ControllerSpan $span
    ): void;
}
```

---

## 74. Logging integration

El sistema deberá enriquecer logs con contexto.

```php
interface ControllerLogContextEnricherInterface
{
    public function enrich(
        array $context,
        ObservabilityContext $observability
    ): array;
}
```

---

## 75. Log context estándar

```text
request_id
execution_id
trace_id
span_id
route
controller
phase
status
worker_id
tenant_reference
```

---

## 76. Structured logging

Los logs oficiales deberán ser estructurados.

Ejemplo conceptual:

```json
{
  "message": "Controller execution completed",
  "execution_id": "exec_...",
  "route": "orders.show",
  "controller": "OrderController::show",
  "duration_ms": 8.42,
  "status": "completed"
}
```

---

## 77. Logging levels

```text
Debug:
    phase details, plan resolution

Info:
    normal lifecycle milestones when enabled

Notice:
    short-circuit, graceful degradation

Warning:
    slow phase, cleanup partial, cancellation ignored

Error:
    failed execution

Critical:
    corrupted state, leaked critical resources

Emergency:
    Worker unsafe or runtime failure
```

---

## 78. Logging duplication

El sistema deberá evitar registrar la misma excepción múltiples veces en:

* lifecycle;
* exception handler;
* transport;
* global handler.

Se utilizarán flags como:

```text
exception.reported
exception.logged
```

---

## 79. Timeline

El timeline representa el orden detallado de eventos de una ejecución.

```php
interface ControllerTimelineRecorderInterface
{
    public function mark(
        ControllerExecutionId $executionId,
        string $name,
        array $metadata = []
    ): void;

    public function measure(
        ControllerExecutionId $executionId,
        string $name,
        float $duration,
        array $metadata = []
    ): void;
}
```

---

## 80. Timeline vs tracing

El timeline es una representación local y detallada de la ejecución.

Tracing está orientado a causalidad distribuida.

Ambos pueden compartir datos, pero no son equivalentes.

---

## 81. Timeline estándar

```text
0.000 execution.created
0.080 lifecycle.started
0.150 controller.resolved
0.280 metadata.resolved
0.610 parameters.resolved
0.850 interceptors.started
1.430 controller.invoked
2.150 result.transformed
2.750 transport.started
3.100 response.emitted
3.400 cleanup.completed
```

---

## 82. Timeline production policy

En producción podrá:

* deshabilitarse;
* muestrearse;
* almacenarse solo para ejecuciones lentas;
* almacenarse para fallos;
* mantenerse únicamente en memoria hasta completion.

---

## 83. Profiling

El profiler medirá consumo de recursos.

```php
interface ControllerProfilerInterface
{
    public function start(
        ControllerExecution $execution
    ): ProfileSession;

    public function snapshot(
        ProfileSession $session,
        string $label
    ): ProfileSnapshot;

    public function stop(
        ProfileSession $session
    ): ControllerProfile;
}
```

---

## 84. ProfileSnapshot

Podrá registrar:

* tiempo;
* memoria;
* peak memory;
* CPU estimado;
* número de queries;
* tiempo de queries;
* I/O;
* bytes emitidos;
* resources activos;
* collectors personalizados.

---

## 85. ControllerProfile

```php
final readonly class ControllerProfile
{
    public function __construct(
        public float $duration,
        public int $memoryStart,
        public int $memoryEnd,
        public int $memoryPeak,
        public array $phases,
        public array $resources,
        public array $thresholdViolations,
    ) {
    }
}
```

---

## 86. Profiler modes

```php
enum ProfilingMode: string
{
    case Disabled = 'disabled';
    case Lightweight = 'lightweight';
    case Sampled = 'sampled';
    case Full = 'full';
    case Debug = 'debug';
}
```

---

## 87. Slow execution detection

```php
final readonly class SlowExecutionPolicy
{
    public function __construct(
        public float $totalThreshold,
        public array $phaseThresholds,
        public bool $captureProfile,
        public bool $emitEvent,
    ) {
    }
}
```

---

## 88. Slow events

```text
controllers.execution.slow
controllers.phase.slow
controllers.cleanup.slow
controllers.transport.slow
```

---

## 89. Thresholds

Los thresholds podrán definirse por:

* entorno;
* ruta;
* controlador;
* fase;
* runtime;
* transporte.

---

## 90. Diagnostic collector

```php
interface ControllerDiagnosticCollectorInterface
{
    public function collect(
        ControllerExecution $execution
    ): ControllerDiagnosticReport;
}
```

---

## 91. ControllerDiagnosticReport

```php
final readonly class ControllerDiagnosticReport
{
    public function __construct(
        public array $warnings,
        public array $violations,
        public array $performanceIssues,
        public array $resourceIssues,
        public array $stateIssues,
        public array $recommendations,
    ) {
    }
}
```

---

## 92. Diagnósticos posibles

* doble transición;
* fase inesperadamente lenta;
* cache compilada no utilizada;
* demasiados resolvers;
* demasiados interceptores;
* body materializado innecesariamente;
* stream no cerrado;
* cleanup incompleto;
* Worker memory growth;
* alta cardinalidad;
* instrumentation overhead.

---

## 93. Observability overhead

El sistema deberá medir su propio coste.

Métrica:

```text
voltstack.controllers.observability.duration
```

También podrá registrar:

```text
voltstack.controllers.observability.dropped_signals
voltstack.controllers.observability.export_failures
```

---

## 94. Sampling

El sampling decide qué señales conservar.

```php
interface SamplingPolicyInterface
{
    public function shouldSample(
        ControllerObservabilitySignal $signal,
        ObservabilityContext $context
    ): bool;
}
```

---

## 95. Sampling strategies

```text
AlwaysSample
NeverSample
ProbabilitySample
RateLimitedSample
ErrorBiasedSample
LatencyBiasedSample
RouteBasedSample
ParentBasedSample
AdaptiveSample
```

---

## 96. Error-biased sampling

Los errores deberán conservarse con mayor probabilidad que ejecuciones exitosas.

---

## 97. Latency-biased sampling

Las ejecuciones lentas podrán conservar traces completos aunque la ejecución normal no haya sido seleccionada inicialmente.

Esto requiere buffering temporal hasta completion.

---

## 98. Tail sampling

El sistema podrá implementar tail sampling básico:

```text
Collect lightweight context
        │
        ▼
Execution completes
        │
        ├── Slow or failed → retain
        └── Normal → discard
```

---

## 99. Sampling consistency

Una decisión de sampling deberá propagarse a subspans para evitar traces fragmentados.

---

## 100. Sanitización

Toda señal deberá pasar por sanitización.

```php
interface ObservabilitySanitizerInterface
{
    public function sanitize(
        array $attributes,
        ObservabilityContext $context
    ): array;
}
```

---

## 101. Sanitizers iniciales

```text
HeaderObservabilitySanitizer
RequestDataObservabilitySanitizer
PathObservabilitySanitizer
ExceptionObservabilitySanitizer
DatabaseObservabilitySanitizer
IdentityObservabilitySanitizer
CustomAttributeSanitizer
```

---

## 102. Datos sensibles

No deberán registrarse automáticamente:

* passwords;
* tokens;
* cookies;
* authorization headers;
* bodies completos;
* datos financieros;
* archivos;
* secretos;
* payloads SPA completos;
* argumentos de controladores.

---

## 103. Controller arguments

Por defecto solo podrá registrarse:

* cantidad de argumentos;
* tipos;
* origen;
* duración de resolución.

No sus valores.

---

## 104. Result observability

Del resultado podrá registrarse:

* tipo;
* estrategia;
* tamaño estimado;
* streaming;
* status;
* content type.

No el contenido.

---

## 105. Tenant and user data

Podrán registrarse identificadores opacos o hashes controlados.

No deberán utilizarse como labels de alta cardinalidad.

---

## 106. Observability Registry

```php
interface ControllerObservabilityRegistryInterface
{
    public function registerExporter(
        ObservabilityExporterInterface $exporter
    ): void;

    public function registerSanitizer(
        ObservabilitySanitizerInterface $sanitizer
    ): void;

    public function registerCollector(
        ControllerDiagnosticCollectorInterface $collector
    ): void;

    public function freeze(): void;
}
```

---

## 107. Exporters

```text
LogExporter
MetricsExporter
OpenTelemetryExporter
InMemoryExporter
NullExporter
CompositeExporter
```

---

## 108. Exporter contract

```php
interface ObservabilityExporterInterface
{
    public function supports(
        ControllerObservabilitySignal $signal
    ): bool;

    public function export(
        ControllerObservabilitySignal $signal,
        ObservabilityContext $context
    ): void;

    public function flush(): void;
}
```

---

## 109. Buffered exporters

Los exporters podrán acumular señales request-scoped y enviarlas durante finalización.

Nunca deberán conservar referencias al Request después del reset.

---

## 110. Backpressure de observabilidad

Si un exporter no puede procesar señales:

* podrá descartar señales de baja prioridad;
* podrá agrupar métricas;
* podrá escribir en buffer limitado;
* no deberá bloquear indefinidamente la respuesta.

---

## 111. SignalPriority

```php
enum SignalPriority: int
{
    case Debug = 10;
    case Normal = 20;
    case Important = 30;
    case Error = 40;
    case Critical = 50;
}
```

---

## 112. Drop policy

Ante saturación se descartarán primero:

1. snapshots de Debug;
2. eventos redundantes;
3. perfiles normales;
4. timelines normales;
5. métricas no críticas.

Nunca se descartarán silenciosamente errores críticos sin registrar al menos un contador.

---

## 113. Compilación

El sistema podrá compilar la política de observabilidad aplicable a cada ruta.

```php
final readonly class CompiledControllerObservabilityPlan
{
    public function __construct(
        public string $routeSignature,
        public bool $events,
        public bool $metrics,
        public bool $tracing,
        public bool $profiling,
        public bool $timeline,
        public array $exporters,
        public array $sanitizers,
        public array $metricLabels,
        public string $samplingStrategy,
        public string $frameworkVersion,
        public string $signature,
    ) {
    }
}
```

---

## 114. ObservabilityCompiler

```php
interface ControllerObservabilityCompilerInterface
{
    public function compile(): CompiledControllerObservabilityRegistry;
}
```

---

## 115. Compiler inputs

* configuración;
* metadata;
* exporters;
* sanitizers;
* sampling policies;
* profiling policies;
* metric definitions;
* route definitions;
* runtime capabilities.

---

## 116. Cache multinivel

```text
L1 Execution signals
L2 Request buffers
L3 Worker definitions
L4 Compiled plans
```

---

## 117. L1 Execution

Contendrá:

* active spans;
* sequence;
* sampling decision;
* timeline;
* profile session;
* signal counters.

---

## 118. L2 Request

Podrá contener:

* batched events;
* metric aggregation;
* deferred export;
* parent correlation.

---

## 119. L3 Worker

Podrá almacenar:

* exporters stateless;
* compiled plans;
* metric definitions;
* registries congelados;
* sanitizers stateless.

---

## 120. L4 Compiled

Artefactos PHP para:

* políticas;
* listeners;
* exporters;
* labels;
* sampling;
* thresholds.

---

## 121. Worker safety

En Workers persistentes deberán resetearse:

* current trace context;
* active spans;
* event buffers;
* metric buffers request-scoped;
* timeline;
* profiler session;
* correlation context;
* deferred listeners.

---

## 122. Observability resetter

```php
interface ControllerObservabilityResetterInterface
{
    public function reset(): void;
}
```

---

## 123. Leaks de contexto

El sistema deberá detectar:

* span activo después de completion;
* buffer no vaciado;
* correlation context anterior;
* profiler abierto;
* timeline no liberado;
* listeners request-scoped retenidos.

---

## 124. Integración con Lifecycle

El lifecycle será la principal fuente de señales.

```php
$observability->record(
    new ControllerInvocationStartedSignal(...),
    $context,
);
```

La instrumentación deberá concentrarse en phase handlers y state transitions.

---

## 125. Integración con Exceptions

El sistema de excepciones deberá:

* reutilizar trace y execution IDs;
* registrar exception events;
* marcar span como error;
* evitar doble logging;
* conservar fingerprint;
* añadir public error ID cuando exista.

---

## 126. Integración con Transport

El transporte deberá registrar:

* preparación;
* status;
* headers emitted;
* bytes;
* duración;
* streaming;
* desconexión;
* completion.

No deberá registrar cuerpos.

---

## 127. Integración con Metadata Engine

Metadata podrá definir:

* tags;
* sampling;
* profiling;
* exporters;
* sensibilidad;
* thresholds.

La observabilidad no leerá atributos mediante reflexión.

---

## 128. Integración con Routing

La ruta deberá proporcionar nombres estables para métricas.

Preferencia:

```text
orders.show
```

No:

```text
/orders/98374
```

---

## 129. Integración con subrequests

Los subrequests deberán:

* conservar Trace ID;
* crear Execution ID propio;
* crear span hijo;
* registrar parent execution ID;
* mantener timeline separado.

---

## 130. Integración con SPA

Podrán propagarse:

```text
trace_id
request_id
navigation_id
component_execution_id
```

Nunca deberán exponerse detalles internos sensibles.

---

## 131. Integración con streaming

Para streams largos deberán registrarse:

* tiempo hasta primer byte;
* duración total;
* chunks;
* bytes;
* flushes;
* desconexión;
* errores.

---

## 132. Time to first byte

Métrica:

```text
voltstack.controllers.transport.time_to_first_byte
```

---

## 133. Long-lived streams

En streams largos, la telemetría no deberá esperar indefinidamente al cierre para exportar toda señal.

Podrán emitirse snapshots periódicos limitados.

---

## 134. Health checks

El sistema podrá exponer diagnóstico interno sobre:

* exporters;
* buffers;
* dropped signals;
* tracing;
* metric backend;
* Worker reset;
* sampling.

---

## 135. Testing

El módulo incluirá:

```text
FakeControllerEventDispatcher
FakeControllerMetricRecorder
FakeControllerTraceRecorder
FakeControllerProfiler
FakeObservabilityExporter
InMemoryObservabilityExporter
ObservabilityTestHarness
ControllerObservabilityAssertions
```

---

## 136. Assertions

```php
ControllerObservabilityAssert::eventDispatched(
    'controllers.execution.completed'
);

ControllerObservabilityAssert::metricRecorded(
    'voltstack.controllers.execution.total'
);

ControllerObservabilityAssert::spanCreated(
    'voltstack.controller.invoke'
);

ControllerObservabilityAssert::noSensitiveData();

ControllerObservabilityAssert::traceCompleted();
```

---

## 137. Casos de prueba

* ejecución normal;
* ejecución fallida;
* short-circuit;
* cancelación;
* subrequest;
* stream;
* desconexión;
* cleanup fallido;
* exporter fallido;
* sampling;
* tail sampling;
* Worker reset;
* span no cerrado;
* cardinalidad excesiva;
* datos sensibles;
* orden de eventos.

---

## 138. Benchmarks

```text
Observability disabled
Events only
Metrics only
Tracing only
Lightweight full observability
Full profiling
Sampled tracing
High-frequency controller
FrankenPHP repeated executions
Streaming execution
```

---

## 139. Performance targets

Los objetivos deberán definirse mediante benchmarks.

La arquitectura deberá minimizar:

* asignaciones;
* creación de arrays;
* serialización;
* llamadas de reloj;
* stack captures;
* exportaciones síncronas;
* creación de spans deshabilitados;
* snapshots innecesarios.

---

## 140. No-op implementations

Cuando una capacidad esté deshabilitada se utilizarán implementaciones no-op.

```text
NullEventDispatcher
NullMetricRecorder
NullTraceRecorder
NullProfiler
NullTimelineRecorder
```

Esto evita condicionales repetidos en todo el pipeline.

---

## 141. Lazy attributes

Los atributos costosos podrán calcularse mediante closures diferidas.

```php
$signal->withLazyAttribute(
    'diagnostic',
    fn () => $collector->collect()
);
```

Solo deberán evaluarse si la señal será exportada.

---

## 142. Estructura de directorios

```text
src/
└── Quantum/
    └── Controllers/
        └── Observability/
            ├── Contracts/
            │   ├── ControllerObservabilityManagerInterface.php
            │   ├── ControllerEventDispatcherInterface.php
            │   ├── ControllerMetricRecorderInterface.php
            │   ├── ControllerTraceRecorderInterface.php
            │   ├── ControllerProfilerInterface.php
            │   ├── ControllerTimelineRecorderInterface.php
            │   ├── ControllerDiagnosticCollectorInterface.php
            │   ├── ObservabilityExporterInterface.php
            │   └── ObservabilitySanitizerInterface.php
            │
            ├── Engine/
            │   └── ControllerObservabilityManager.php
            │
            ├── Context/
            │   ├── ObservabilityContext.php
            │   ├── CorrelationContext.php
            │   └── TraceContext.php
            │
            ├── Policy/
            │   ├── ObservabilityPolicy.php
            │   ├── ObservabilityPolicyResolver.php
            │   ├── SamplingPolicy.php
            │   ├── SlowExecutionPolicy.php
            │   └── ProfilingPolicy.php
            │
            ├── Signals/
            │   ├── ControllerObservabilitySignal.php
            │   ├── ObservabilitySignalCategory.php
            │   ├── SignalPriority.php
            │   └── Lifecycle/
            │
            ├── Events/
            │   ├── ControllerEvent.php
            │   ├── ControllerEventDispatcher.php
            │   ├── EventSequence.php
            │   ├── EventListenerMode.php
            │   └── Catalog/
            │
            ├── Metrics/
            │   ├── ControllerMetricRecorder.php
            │   ├── ControllerMetric.php
            │   ├── MetricType.php
            │   ├── CardinalityGuard.php
            │   └── Catalog/
            │
            ├── Tracing/
            │   ├── ControllerTraceRecorder.php
            │   ├── ControllerSpan.php
            │   ├── SpanStatus.php
            │   ├── SpanRegistry.php
            │   └── Propagation/
            │
            ├── Logging/
            │   ├── ControllerLogContextEnricher.php
            │   ├── StructuredControllerLogger.php
            │   └── LoggingDeduplicationGuard.php
            │
            ├── Timeline/
            │   ├── ControllerTimelineRecorder.php
            │   ├── ControllerTimeline.php
            │   └── ControllerTimelineEntry.php
            │
            ├── Profiling/
            │   ├── ControllerProfiler.php
            │   ├── ProfileSession.php
            │   ├── ProfileSnapshot.php
            │   ├── ControllerProfile.php
            │   └── ProfilingMode.php
            │
            ├── Diagnostics/
            │   ├── ControllerDiagnosticCollector.php
            │   ├── ControllerDiagnosticReport.php
            │   ├── SlowExecutionDetector.php
            │   └── ObservabilityOverheadMonitor.php
            │
            ├── Sampling/
            │   ├── SamplingPolicyInterface.php
            │   ├── AlwaysSample.php
            │   ├── ProbabilitySample.php
            │   ├── ErrorBiasedSample.php
            │   ├── LatencyBiasedSample.php
            │   ├── ParentBasedSample.php
            │   └── TailSampler.php
            │
            ├── Sanitization/
            │   ├── ObservabilitySanitizerPipeline.php
            │   └── Sanitizers/
            │
            ├── Exporters/
            │   ├── CompositeExporter.php
            │   ├── LogExporter.php
            │   ├── MetricsExporter.php
            │   ├── OpenTelemetryExporter.php
            │   ├── InMemoryExporter.php
            │   └── NullExporter.php
            │
            ├── OpenTelemetry/
            │   ├── OpenTelemetryControllerAdapter.php
            │   ├── OpenTelemetrySpanAdapter.php
            │   ├── OpenTelemetryMetricAdapter.php
            │   └── OpenTelemetryContextPropagator.php
            │
            ├── Registry/
            │   └── ControllerObservabilityRegistry.php
            │
            ├── Compiler/
            │   ├── ControllerObservabilityCompiler.php
            │   ├── CompiledControllerObservabilityPlan.php
            │   ├── CompiledControllerObservabilityRegistry.php
            │   └── ObservabilityArtifactWriter.php
            │
            ├── Cache/
            ├── Events/
            ├── Exceptions/
            ├── Testing/
            └── Providers/
                └── ControllerObservabilityServiceProvider.php
```

---

## 143. Configuración

```php
// config/controller_observability.php

return [
    'enabled' => true,

    'events' => [
        'enabled' => true,
        'buffered' => true,
    ],

    'metrics' => [
        'enabled' => true,
        'prefix' => 'voltstack.controllers',
        'cardinality_guard' => true,
    ],

    'tracing' => [
        'enabled' => true,
        'propagate' => true,
        'open_telemetry' => true,
    ],

    'logging' => [
        'enabled' => true,
        'structured' => true,
        'deduplicate_exceptions' => true,
    ],

    'timeline' => [
        'enabled' => false,
        'retain_on_error' => true,
        'retain_when_slow' => true,
    ],

    'profiling' => [
        'mode' => 'sampled',
        'sample_rate' => 0.05,
    ],

    'sampling' => [
        'strategy' => 'parent_based',
        'rate' => 0.10,
        'retain_errors' => true,
        'retain_slow' => true,
    ],

    'sanitization' => [
        'enabled' => true,
        'record_arguments' => false,
        'record_bodies' => false,
        'hide_paths' => true,
    ],

    'exporters' => [
        'default' => ['log', 'metrics', 'otel'],
        'flush_on_completion' => true,
    ],

    'workers' => [
        'reset_after_execution' => true,
        'detect_context_leaks' => true,
    ],

    'compiled' => [
        'enabled' => true,
        'strict' => false,
    ],
];
```

---

## 144. Service Provider

```php
final class ControllerObservabilityServiceProvider
    extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(
            ControllerObservabilityManagerInterface::class,
            ControllerObservabilityManager::class,
        );

        $this->container->singleton(
            ControllerObservabilityRegistryInterface::class,
            ControllerObservabilityRegistry::class,
        );

        $this->container->singleton(
            ControllerMetricRecorderInterface::class,
            ControllerMetricRecorder::class,
        );

        $this->container->singleton(
            ControllerTraceRecorderInterface::class,
            ControllerTraceRecorder::class,
        );
    }

    public function boot(
        ControllerObservabilityRegistryInterface $registry
    ): void {
        $registry->freeze();
    }
}
```

---

## 145. Integración con State Machine

Cada transición podrá producir una señal.

```php
$stateMachine->transition(
    $execution,
    ControllerExecutionTransition::EnterInvocation,
);

$observability->record(
    ControllerSignals::invocationStarted($execution),
    $context,
);
```

La máquina de estados seguirá siendo la fuente de verdad funcional.

---

## 146. Integración con phase handlers

Cada phase handler deberá instrumentar:

```text
start
success
failure
duration
relevant metadata
```

No deberá repetir instrumentación interna de los engines delegados cuando estos ya produzcan spans hijos.

---

## 147. Jerarquía de spans

```text
controller.execution
    ├── controller.resolve
    ├── controller.metadata
    ├── controller.parameters
    ├── controller.interceptors
    │   ├── interceptor.authorization
    │   └── interceptor.transaction
    ├── controller.invoke
    ├── controller.transform
    ├── controller.transport
    └── controller.cleanup
```

---

## 148. Evitar duplicación

Cada operación deberá tener un propietario de instrumentación.

Ejemplo:

```text
Lifecycle:
    span de fase general

ParameterResolutionEngine:
    spans internos de resolvers

Transport:
    spans de preparación y emisión
```

---

## 149. ADR-001

**Los eventos, métricas, traces, logs y perfiles serán señales diferentes.**

No deberán tratarse como representaciones equivalentes.

---

## 150. ADR-002

**El lifecycle será la fuente principal de señales de ejecución.**

---

## 151. ADR-003

**Los eventos describirán hechos ya ocurridos.**

No se utilizarán para decidir lógica funcional del controlador.

---

## 152. ADR-004

**La instrumentación no deberá modificar respuestas ni excepciones.**

---

## 153. ADR-005

**Los eventos oficiales tendrán nombres y versiones estables.**

---

## 154. ADR-006

**Las métricas utilizarán labels de cardinalidad controlada.**

---

## 155. ADR-007

**Los IDs de ejecución no se utilizarán como labels de métricas.**

---

## 156. ADR-008

**Cada ejecución tendrá un Trace ID y un Execution ID diferenciados.**

---

## 157. ADR-009

**Los subrequests crearán spans y ejecuciones hijas.**

---

## 158. ADR-010

**La información sensible será eliminada antes de exportar.**

---

## 159. ADR-011

**OpenTelemetry será una integración, no una dependencia del núcleo.**

---

## 160. ADR-012

**Los exporters no críticos no deberán bloquear la respuesta indefinidamente.**

---

## 161. ADR-013

**Los errores de observabilidad no reemplazarán errores de aplicación.**

---

## 162. ADR-014

**El timeline local y el tracing distribuido serán sistemas relacionados, pero distintos.**

---

## 163. ADR-015

**El profiling completo estará deshabilitado por defecto en producción.**

---

## 164. ADR-016

**La decisión de sampling se propagará a spans hijos.**

---

## 165. ADR-017

**Los errores y ejecuciones lentas podrán retenerse mediante tail sampling.**

---

## 166. ADR-018

**El sistema medirá su propio overhead.**

---

## 167. ADR-019

**Los registries de observabilidad se congelarán después del bootstrap.**

---

## 168. ADR-020

**Todo contexto request-scoped de observabilidad deberá resetearse en Workers persistentes.**

---

## 169. Implementación V1

La V1 deberá incluir:

* ControllerObservabilityManager;
* CorrelationContext;
* catálogo oficial de eventos;
* event dispatcher;
* metric recorder;
* métricas principales;
* trace recorder;
* spans por fase;
* logging context;
* timeline básico;
* profiler ligero;
* sampling;
* sanitización;
* cardinality guard;
* exporters;
* OpenTelemetry adapter;
* Worker reset;
* compiled observability plans;
* testing utilities.

---

## 170. Fuera de V1

Se aplazarán:

* continuous profiling;
* eBPF integration;
* adaptive sampling distribuido;
* trace storage propio;
* métricas predictivas;
* replay completo;
* flame graphs nativos;
* análisis automático de causa raíz.

---

## 171. Roadmap V2

Podrá incluir:

* tail sampling avanzado;
* profiling continuo;
* flame graphs;
* query correlation;
* external service maps;
* dashboards oficiales;
* alert rules;
* error budgets;
* performance baselines;
* análisis de Workers.

---

## 172. Roadmap V3

Podrá incorporar:

* adaptive sampling;
* anomaly detection;
* root cause analysis;
* predictive performance;
* distributed execution visualization;
* automatic instrumentation recommendations;
* observability-driven optimization.

---

## 173. Flujo completo de observabilidad

```text
Controller execution starts
        │
        ▼
Create correlation context
        │
        ▼
Start root span
        │
        ▼
Record lifecycle events
        │
        ▼
Record phase metrics
        │
        ▼
Enrich logs
        │
        ▼
Capture optional profile
        │
        ▼
Finalize execution
        │
        ▼
Apply sampling decision
        │
        ▼
Export signals
        │
        ▼
Reset request-scoped observability
```

---

## 174. Resultado arquitectónico

Con este sistema, VoltStack dispondrá de una única capa coherente para observar todo el pipeline de controladores.

La arquitectura permitirá responder preguntas como:

* ¿Qué controlador se ejecutó?
* ¿Qué fase fue más lenta?
* ¿Por qué no se invocó el controlador?
* ¿Qué interceptor produjo un short-circuit?
* ¿Cuánto tardó la transformación?
* ¿Cuántos bytes fueron emitidos?
* ¿Hubo una desconexión?
* ¿Qué recurso quedó abierto?
* ¿El Worker puede reutilizarse?
* ¿Qué trace corresponde al error mostrado al usuario?

---

## 175. Conclusión

El **Controller Events and Observability System** convierte el lifecycle de controladores en un flujo medible, trazable y diagnosticable.

La separación entre:

* eventos;
* métricas;
* tracing;
* logging;
* profiling;
* timeline;
* diagnósticos;

permite que cada señal tenga una responsabilidad precisa.

Esto proporciona a VoltStack una infraestructura preparada para desarrollo local, producción, FrankenPHP, sistemas distribuidos y futuras integraciones con OpenTelemetry y plataformas externas de observabilidad.

---

## 176. Próximo documento recomendado

Con el pipeline funcional y su observabilidad definidos, el siguiente documento recomendado es:

```text
CONTROLLER_COMPILATION_AND_CACHE_SYSTEM.md
```

Este documento deberá consolidar:

* compilación de controladores;
* planes de ejecución;
* metadata compilada;
* parámetros compilados;
* interceptores compilados;
* invocación compilada;
* transformación compilada;
* transporte compilado;
* lifecycle compilado;
* observabilidad compilada;
* cache multinivel;
* fingerprints;
* invalidación;
* warmup;
* preload;
* OPcache;
* FrankenPHP;
* despliegues atómicos;
* debugging;
* testing;
* ADRs.
