# 12_CONTROLLER_LIFECYCLE_AND_EXECUTION_STATE.md

## Ciclo de vida y estado de ejecución de controladores en VoltStack

**Versión:** 1.0
**Estado:** Draft arquitectónico
**Módulo:** `VoltStack\Quantum\Controllers\Lifecycle`
**Ámbito:** Ejecución completa de controladores
**Integraciones principales:** Routing, Parameters, Metadata, Interceptors, Invoker, Result Transformation, Response Transport, Exceptions, Events, Observability y FrankenPHP

---

# 1. Introducción

El **Controller Lifecycle and Execution State System** es el subsistema responsable de representar, coordinar y proteger el ciclo de vida completo de una ejecución de controlador en VoltStack.

Hasta este punto, la arquitectura ha definido motores especializados para:

* resolver controladores;
* resolver parámetros;
* obtener metadata;
* ejecutar interceptores;
* invocar el controlador;
* transformar el resultado;
* transportar la respuesta;
* manejar excepciones.

Sin embargo, estos motores necesitan un modelo compartido que permita conocer:

* en qué etapa se encuentra la ejecución;
* qué operaciones ya fueron completadas;
* si la ejecución fue cancelada;
* si ocurrió un short-circuit;
* qué recursos fueron adquiridos;
* qué cleanup debe ejecutarse;
* qué resultado intermedio existe;
* si la respuesta comenzó a emitirse;
* si el Worker puede reutilizarse;
* si una transición de estado es válida.

Este documento define ese modelo.

---

# 2. Objetivo principal

Proporcionar una representación formal, observable y segura del ciclo de ejecución completo de un controlador.

```text
Request accepted
    │
    ▼
Controller execution created
    │
    ▼
Resolution
    │
    ▼
Parameter binding
    │
    ▼
Interception
    │
    ▼
Invocation
    │
    ▼
Transformation
    │
    ▼
Transport
    │
    ▼
Completion
    │
    ▼
Cleanup
```

El sistema deberá permitir que todos los motores colaboren sin compartir estado global ni duplicar responsabilidades.

---

# 3. Problema arquitectónico

Sin un modelo formal de ejecución, cada motor podría mantener su propio estado de manera aislada.

Esto produciría problemas como:

* transiciones inconsistentes;
* errores difíciles de diagnosticar;
* doble invocación;
* doble transformación;
* doble emisión;
* recursos no liberados;
* cancelaciones incompletas;
* transacciones abiertas;
* locks olvidados;
* state leaks entre peticiones;
* incompatibilidad con Workers persistentes;
* telemetría fragmentada.

El sistema de lifecycle será la fuente de verdad sobre el estado de una ejecución.

---

# 4. Principios arquitectónicos

El sistema seguirá estos principios:

* Una ejecución tendrá un único objeto de estado.
* Las transiciones estarán formalmente validadas.
* Cada etapa tendrá límites explícitos.
* El estado mutable será exclusivo de una ejecución.
* Los servicios compartidos deberán ser inmutables o stateless.
* El cleanup se ejecutará siempre que sea posible.
* La cancelación será cooperativa.
* El short-circuit será una decisión formal.
* La emisión será irreversible una vez iniciada.
* Los errores no deberán dejar recursos huérfanos.
* La observabilidad será parte del lifecycle, no lógica de negocio.
* Los Workers persistentes deberán resetear todo estado request-scoped.

---

# 5. No responsabilidades

El sistema no deberá:

* resolver rutas;
* resolver controladores;
* ejecutar controladores;
* transformar resultados;
* emitir respuestas;
* autorizar usuarios;
* validar datos;
* gestionar directamente transacciones;
* gestionar directamente locks;
* implementar logging;
* construir respuestas de error.

Su función será coordinar y representar el estado de esas operaciones.

---

# 6. Posición dentro de la arquitectura

```text
HttpKernel
    │
    ▼
ControllerExecutionManager
    │
    ▼
ControllerExecution
    │
    ├── ControllerResolver
    ├── ParameterResolutionEngine
    ├── MetadataEngine
    ├── InterceptorPipeline
    ├── ControllerInvoker
    ├── ResultTransformationEngine
    ├── ResponseTransportSystem
    └── ExceptionHandlingSystem
```

Todos los motores operan dentro del mismo contexto de ejecución.

---

# 7. Componentes principales

```text
ControllerExecutionManager
ControllerExecution
ControllerExecutionContext
ControllerExecutionState
ControllerExecutionStatus
ControllerExecutionPhase
ControllerExecutionTransition
ControllerExecutionStateMachine
ControllerExecutionTimeline
ControllerExecutionResources
ControllerExecutionResult
CancellationToken
ShortCircuitDecision
CleanupCoordinator
ExecutionGuard
ExecutionSnapshot
ExecutionRecorder
ExecutionCompiler
CompiledLifecyclePlan
```

---

# 8. ControllerExecutionManager

Será el orquestador de alto nivel del ciclo de ejecución.

```php
interface ControllerExecutionManagerInterface
{
    public function execute(
        ControllerExecutionRequest $request
    ): ControllerExecutionResult;
}
```

Implementación oficial:

```php
final class ControllerExecutionManager
    implements ControllerExecutionManagerInterface
{
}
```

---

# 9. Responsabilidades del Manager

El Manager deberá:

1. crear la ejecución;
2. inicializar el contexto;
3. resolver el lifecycle plan;
4. ejecutar las fases;
5. controlar transiciones;
6. manejar cancelaciones;
7. procesar short-circuits;
8. capturar fallos;
9. delegar al sistema de excepciones;
10. ejecutar cleanup;
11. cerrar la ejecución;
12. producir el resultado final.

---

# 10. ControllerExecutionRequest

Representa la entrada inicial del lifecycle.

```php
final readonly class ControllerExecutionRequest
{
    public function __construct(
        public RequestInterface $request,
        public RouteMatch $routeMatch,
        public RuntimeContext $runtime,
        public MetadataBag $metadata,
        public CancellationToken $cancellation,
    ) {
    }
}
```

---

# 11. ControllerExecution

Objeto mutable central de una ejecución.

```php
final class ControllerExecution
{
    public ControllerExecutionId $id;

    public ControllerExecutionContext $context;

    public ControllerExecutionState $state;

    public ControllerExecutionTimeline $timeline;

    public ControllerExecutionResources $resources;

    public ?ResolvedController $controller = null;

    public ?ResolvedArguments $arguments = null;

    public mixed $rawResult = null;

    public ?ResponseInterface $response = null;

    public ?TransportResult $transportResult = null;

    public ?Throwable $throwable = null;

    public ?ExceptionHandlingResult $exceptionResult = null;
}
```

---

# 12. ControllerExecutionId

Cada ejecución tendrá un identificador único.

```php
final readonly class ControllerExecutionId
{
    public function __construct(
        public string $value
    ) {
    }
}
```

El ID deberá ser:

* único;
* trazable;
* seguro para logs;
* independiente del request ID;
* reutilizable en métricas y tracing.

---

# 13. ControllerExecutionContext

Contexto inmutable de la ejecución.

```php
final readonly class ControllerExecutionContext
{
    public function __construct(
        public ControllerExecutionId $executionId,
        public RequestInterface $request,
        public RouteMatch $route,
        public RuntimeContext $runtime,
        public MetadataBag $metadata,
        public CancellationToken $cancellation,
        public ExecutionCapabilities $capabilities,
    ) {
    }
}
```

---

# 14. Estado mutable e inmutable

La arquitectura separará:

```text
ControllerExecutionContext
    └── Datos inmutables de entrada

ControllerExecutionState
    └── Estado mutable del ciclo

ControllerExecutionResources
    └── Recursos adquiridos

ControllerExecutionTimeline
    └── Historial de transiciones
```

---

# 15. ControllerExecutionStatus

Representa el estado global.

```php
enum ControllerExecutionStatus: string
{
    case Created = 'created';
    case Initializing = 'initializing';
    case Running = 'running';
    case ShortCircuited = 'short_circuited';
    case Cancelling = 'cancelling';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case Recovering = 'recovering';
    case Completing = 'completing';
    case Completed = 'completed';
    case CleaningUp = 'cleaning_up';
    case Cleaned = 'cleaned';
    case Terminated = 'terminated';
}
```

---

# 16. ControllerExecutionPhase

Representa la fase específica.

```php
enum ControllerExecutionPhase: string
{
    case None = 'none';
    case Initialization = 'initialization';
    case RoutePreparation = 'route_preparation';
    case ControllerResolution = 'controller_resolution';
    case MetadataResolution = 'metadata_resolution';
    case ParameterResolution = 'parameter_resolution';
    case InterceptorResolution = 'interceptor_resolution';
    case BeforeInterceptors = 'before_interceptors';
    case Invocation = 'invocation';
    case AfterInterceptors = 'after_interceptors';
    case ResultTransformation = 'result_transformation';
    case ResponsePreparation = 'response_preparation';
    case Transport = 'transport';
    case ExceptionHandling = 'exception_handling';
    case Completion = 'completion';
    case Cleanup = 'cleanup';
}
```

---

# 17. ControllerExecutionState

```php
final class ControllerExecutionState
{
    public ControllerExecutionStatus $status;

    public ControllerExecutionPhase $phase;

    public int $transitionCount = 0;

    public bool $controllerInvoked = false;

    public bool $resultTransformed = false;

    public bool $transportStarted = false;

    public bool $responseEmitted = false;

    public bool $cleanupStarted = false;

    public bool $cleanupCompleted = false;

    public ?ShortCircuitDecision $shortCircuit = null;

    public ?CancellationReason $cancellationReason = null;
}
```

---

# 18. Estado inicial

Toda ejecución comenzará como:

```text
Status: Created
Phase: None
```

La primera transición válida será:

```text
Created → Initializing
```

---

# 19. State Machine

El lifecycle estará protegido por una máquina de estados explícita.

```php
interface ControllerExecutionStateMachineInterface
{
    public function transition(
        ControllerExecution $execution,
        ControllerExecutionTransition $transition
    ): void;

    public function canTransition(
        ControllerExecutionState $state,
        ControllerExecutionTransition $transition
    ): bool;
}
```

---

# 20. ControllerExecutionTransition

```php
enum ControllerExecutionTransition: string
{
    case StartInitialization = 'start_initialization';
    case StartRunning = 'start_running';
    case EnterControllerResolution = 'enter_controller_resolution';
    case ControllerResolved = 'controller_resolved';
    case EnterMetadataResolution = 'enter_metadata_resolution';
    case MetadataResolved = 'metadata_resolved';
    case EnterParameterResolution = 'enter_parameter_resolution';
    case ParametersResolved = 'parameters_resolved';
    case EnterInterceptorResolution = 'enter_interceptor_resolution';
    case InterceptorsResolved = 'interceptors_resolved';
    case EnterBeforeInterceptors = 'enter_before_interceptors';
    case EnterInvocation = 'enter_invocation';
    case ControllerInvoked = 'controller_invoked';
    case EnterAfterInterceptors = 'enter_after_interceptors';
    case EnterTransformation = 'enter_transformation';
    case ResultTransformed = 'result_transformed';
    case EnterTransport = 'enter_transport';
    case TransportStarted = 'transport_started';
    case ResponseEmitted = 'response_emitted';
    case ShortCircuit = 'short_circuit';
    case RequestCancellation = 'request_cancellation';
    case CancellationCompleted = 'cancellation_completed';
    case FailureDetected = 'failure_detected';
    case StartRecovery = 'start_recovery';
    case RecoveryCompleted = 'recovery_completed';
    case StartCompletion = 'start_completion';
    case ExecutionCompleted = 'execution_completed';
    case StartCleanup = 'start_cleanup';
    case CleanupCompleted = 'cleanup_completed';
    case Terminate = 'terminate';
}
```

---

# 21. Reglas de transición

Las transiciones inválidas deberán lanzar:

```php
InvalidControllerExecutionTransitionException
```

Ejemplos inválidos:

* transformar antes de invocar;
* emitir antes de preparar respuesta;
* invocar dos veces;
* iniciar cleanup dos veces;
* reanudar después de terminación;
* cancelar después de completar;
* modificar respuesta después de emitir.

---

# 22. Diagrama principal de estados

```text
Created
    │
    ▼
Initializing
    │
    ▼
Running
    │
    ├── ShortCircuited
    │       │
    │       ▼
    │   Completing
    │
    ├── Cancelling
    │       │
    │       ▼
    │   Cancelled
    │
    ├── Failed
    │       │
    │       ▼
    │   Recovering
    │       │
    │       ├── Running
    │       └── Completing
    │
    ▼
Completing
    │
    ▼
Completed
    │
    ▼
CleaningUp
    │
    ▼
Cleaned
```

---

# 23. LifecyclePlan

Describe las fases que deberán ejecutarse.

```php
final readonly class ControllerLifecyclePlan
{
    public function __construct(
        public array $phases,
        public array $guards,
        public array $cleanupHandlers,
        public array $policies,
        public bool $compiled,
        public string $signature,
    ) {
    }
}
```

---

# 24. LifecyclePlanResolver

```php
interface ControllerLifecyclePlanResolverInterface
{
    public function resolve(
        ControllerExecutionRequest $request
    ): ControllerLifecyclePlan;
}
```

---

# 25. Plan estándar

```text
Initialize
Resolve Controller
Resolve Metadata
Resolve Parameters
Resolve Interceptors
Run Interceptor Pipeline
Invoke Controller
Transform Result
Transport Response
Complete
Cleanup
```

---

# 26. Planes especializados

Podrán existir planes para:

```text
Standard HTTP controller
Closure route
Invokable controller
Action
Resource controller
Volt page
Volt component
SPA action
Internal subrequest
CLI controller
Streaming controller
```

---

# 27. CompiledLifecyclePlan

```php
final readonly class CompiledLifecyclePlan
{
    public function __construct(
        public string $routeSignature,
        public string $controllerType,
        public array $phaseHandlers,
        public array $guards,
        public array $cleanupHandlers,
        public array $policyReferences,
        public string $frameworkVersion,
        public string $signature,
    ) {
    }
}
```

---

# 28. Phase Handler

Cada fase será ejecutada por un handler especializado.

```php
interface ControllerLifecyclePhaseHandlerInterface
{
    public function phase(): ControllerExecutionPhase;

    public function handle(
        ControllerExecution $execution
    ): ControllerPhaseResult;
}
```

---

# 29. ControllerPhaseResult

```php
final readonly class ControllerPhaseResult
{
    public function __construct(
        public ControllerPhaseOutcome $outcome,
        public mixed $value = null,
        public ?ShortCircuitDecision $shortCircuit = null,
        public ?Throwable $throwable = null,
    ) {
    }
}
```

---

# 30. ControllerPhaseOutcome

```php
enum ControllerPhaseOutcome: string
{
    case Continue = 'continue';
    case ShortCircuit = 'short_circuit';
    case Cancel = 'cancel';
    case Fail = 'fail';
    case Complete = 'complete';
}
```

---

# 31. Fase de inicialización

La inicialización deberá:

* crear scopes;
* registrar lifecycle;
* iniciar tracing;
* capturar baseline de recursos;
* preparar cancellation token;
* crear timeline;
* validar el request;
* cargar el plan.

---

# 32. Fase de resolución del controlador

Delegará al `ControllerResolver`.

Resultado esperado:

```php
ResolvedController
```

Después de esta fase:

```text
execution.controller !== null
```

---

# 33. Fase de metadata

Delegará al `MetadataEngine`.

La metadata resultante se asociará al contexto de ejecución sin realizar reflexión directa en fases posteriores.

---

# 34. Fase de parámetros

Delegará al `ParameterResolutionEngine`.

Resultado:

```php
ResolvedArguments
```

---

# 35. Fase de interceptores

Resolverá el plan de interceptores aplicable.

El lifecycle no ejecutará su lógica interna, pero registrará:

* inicio;
* orden;
* short-circuit;
* resultado;
* excepciones;
* recursos creados.

---

# 36. Fase de invocación

Delegará al `ControllerInvoker`.

Solo podrá ejecutarse una vez.

```php
$execution->state->controllerInvoked = true;
```

---

# 37. Invocation guard

```php
final class ControllerInvocationGuard
{
    public function assertCanInvoke(
        ControllerExecution $execution
    ): void;
}
```

Deberá impedir:

* doble invocación;
* invocación después de cancelación;
* invocación después de short-circuit terminal;
* invocación después de fallo no recuperado.

---

# 38. Resultado bruto

El resultado del controlador se almacenará en:

```php
$execution->rawResult
```

No deberá persistir más allá del cleanup cuando contenga recursos pesados.

---

# 39. Fase de transformación

Delegará al `ResultTransformationEngine`.

Resultado:

```php
ResponseInterface
```

La transformación solo podrá ocurrir cuando exista:

* resultado bruto;
* resultado de short-circuit;
* representación de error recuperada.

---

# 40. Transformation guard

Impedirá:

* transformación duplicada;
* transformar después de emitir;
* transformar un estado cancelado sin fallback;
* transformar durante cleanup.

---

# 41. Fase de transporte

Delegará al `ResponseTransportSystem`.

Antes de iniciar:

```php
$execution->state->transportStarted = true;
```

Después de emitir:

```php
$execution->state->responseEmitted = true;
```

---

# 42. Frontera irreversible

La emisión marca una frontera irreversible.

Antes:

* se puede sustituir la respuesta;
* se puede recuperar de una excepción;
* se puede modificar metadata;
* se puede cancelar con una respuesta alternativa.

Después:

* no puede reiniciarse el pipeline;
* no puede emitirse una segunda respuesta;
* no puede modificarse el status ya enviado;
* no puede reemplazarse el body emitido.

---

# 43. Short-circuit

Un short-circuit termina anticipadamente una parte del pipeline proporcionando un resultado válido.

Ejemplos:

* middleware devuelve una respuesta;
* interceptor de cache devuelve contenido;
* autorización produce una respuesta;
* rate limiter produce `429`;
* mantenimiento produce `503`;
* route binding produce `404`;
* feature flag devuelve fallback.

---

# 44. ShortCircuitDecision

```php
final readonly class ShortCircuitDecision
{
    public function __construct(
        public ShortCircuitOrigin $origin,
        public mixed $result,
        public ShortCircuitMode $mode,
        public string $reason,
        public array $metadata = [],
    ) {
    }
}
```

---

# 45. ShortCircuitOrigin

```php
enum ShortCircuitOrigin: string
{
    case Routing = 'routing';
    case ParameterResolution = 'parameter_resolution';
    case Middleware = 'middleware';
    case Interceptor = 'interceptor';
    case Authorization = 'authorization';
    case Cache = 'cache';
    case RateLimit = 'rate_limit';
    case FeatureFlag = 'feature_flag';
    case Maintenance = 'maintenance';
    case Component = 'component';
    case Custom = 'custom';
}
```

---

# 46. ShortCircuitMode

```php
enum ShortCircuitMode: string
{
    case TransformResult = 'transform_result';
    case UseResponse = 'use_response';
    case AbortTransport = 'abort_transport';
    case CompleteWithoutResponse = 'complete_without_response';
}
```

---

# 47. Reglas del short-circuit

Un short-circuit deberá:

* registrar origen;
* conservar razón;
* impedir invocación si ocurre antes de ella;
* continuar hacia transformación cuando corresponda;
* ejecutar interceptores de salida compatibles;
* ejecutar cleanup;
* emitir eventos;
* aparecer en métricas.

---

# 48. Short-circuit antes de invocación

```text
Interceptor
    │
    ▼
Short-circuit result
    │
    ▼
Skip ControllerInvoker
    │
    ▼
ResultTransformationEngine
    │
    ▼
Transport
```

---

# 49. Short-circuit con Response existente

Cuando el resultado ya sea `ResponseInterface`:

```text
ShortCircuitDecision::UseResponse
```

podrá saltar la transformación semántica, pero seguirá pasando por validación y transporte.

---

# 50. Cancelación

La cancelación representa una solicitud para detener la ejecución.

Puede originarse por:

* desconexión del cliente;
* timeout;
* cancelación del runtime;
* shutdown;
* política de aplicación;
* cancelación de tarea padre;
* fallo crítico;
* deadline excedido.

---

# 51. CancellationToken

```php
interface CancellationTokenInterface
{
    public function isCancellationRequested(): bool;

    public function reason(): ?CancellationReason;

    public function throwIfCancellationRequested(): void;

    public function onCancellation(callable $listener): void;
}
```

---

# 52. CancellationSource

```php
interface CancellationSourceInterface
{
    public function token(): CancellationTokenInterface;

    public function cancel(
        CancellationReason $reason
    ): void;
}
```

---

# 53. CancellationReason

```php
enum CancellationReason: string
{
    case ClientDisconnected = 'client_disconnected';
    case Timeout = 'timeout';
    case DeadlineExceeded = 'deadline_exceeded';
    case RuntimeShutdown = 'runtime_shutdown';
    case ParentCancelled = 'parent_cancelled';
    case ApplicationRequested = 'application_requested';
    case WorkerTermination = 'worker_termination';
    case FatalFailure = 'fatal_failure';
}
```

---

# 54. Cancelación cooperativa

La cancelación no deberá interrumpir arbitrariamente código PHP.

Los motores deberán consultar el token en puntos seguros:

* antes de resolver;
* antes de invocar;
* entre interceptores;
* entre chunks;
* antes de emitir;
* durante loops largos;
* antes de reintentos.

---

# 55. Cancellation checkpoints

```php
$execution->context
    ->cancellation
    ->throwIfCancellationRequested();
```

---

# 56. Cancelación antes de invocación

Si se cancela antes de invocar:

* no se ejecutará el controlador;
* se podrá producir una respuesta de cancelación;
* se ejecutará cleanup;
* se registrará el origen.

---

# 57. Cancelación durante invocación

Solo podrá detenerse cooperativamente.

Si el controlador no comprueba cancelación, el lifecycle deberá esperar su finalización o depender de límites externos del runtime.

---

# 58. Cancelación durante streaming

El productor deberá detenerse al detectar:

* cancelación;
* desconexión;
* timeout;
* abort del transporte.

---

# 59. Cancelación después de emisión

La cancelación se tratará como cierre o aborto del transporte.

No se intentará construir una nueva respuesta.

---

# 60. Deadline

El contexto podrá incluir un deadline absoluto.

```php
final readonly class ExecutionDeadline
{
    public function __construct(
        public DateTimeImmutable $expiresAt
    ) {
    }

    public function isExceeded(): bool;
}
```

---

# 61. TimeoutPolicy

```php
final readonly class ControllerTimeoutPolicy
{
    public function __construct(
        public ?float $total,
        public ?float $resolution,
        public ?float $invocation,
        public ?float $transformation,
        public ?float $transport,
    ) {
    }
}
```

---

# 62. Execution guards

Los guards protegen invariantes del lifecycle.

```php
interface ExecutionGuardInterface
{
    public function assert(
        ControllerExecution $execution
    ): void;
}
```

---

# 63. Guards oficiales

```text
StateTransitionGuard
InvocationGuard
TransformationGuard
TransportGuard
ResponseMutationGuard
CleanupGuard
CancellationGuard
ResourceOwnershipGuard
ExecutionRecursionGuard
```

---

# 64. ResponseMutationGuard

Después de iniciar la emisión deberá impedir:

* cambiar status;
* reemplazar headers;
* agregar cookies;
* sustituir el body;
* reiniciar transformación.

---

# 65. ExecutionRecursionGuard

Controlará subrequests o reentradas.

Deberá evitar:

* ciclos infinitos;
* subrequest recursivo;
* dispatch repetido de la misma ruta;
* error handler recursivo.

---

# 66. Subrequests

VoltStack podrá soportar ejecuciones hijas.

```text
Parent ControllerExecution
        │
        ▼
Child ControllerExecution
```

Cada hijo tendrá:

* ID propio;
* cancellation token enlazado;
* contexto independiente;
* recursos propios;
* referencia al padre;
* límite de profundidad.

---

# 67. ParentExecutionReference

```php
final readonly class ParentExecutionReference
{
    public function __construct(
        public ControllerExecutionId $parentId,
        public int $depth,
    ) {
    }
}
```

---

# 68. Propagación de cancelación

La cancelación del padre podrá cancelar a los hijos.

La cancelación de un hijo no deberá cancelar al padre por defecto.

---

# 69. Execution resources

Todos los recursos adquiridos durante la ejecución deberán registrarse.

```php
final class ControllerExecutionResources
{
    public function register(
        ExecutionResourceInterface $resource
    ): void;

    public function releaseAll(
        CleanupContext $context
    ): CleanupResult;
}
```

---

# 70. ExecutionResourceInterface

```php
interface ExecutionResourceInterface
{
    public function id(): string;

    public function type(): ExecutionResourceType;

    public function isReleased(): bool;

    public function release(
        CleanupContext $context
    ): void;
}
```

---

# 71. Tipos de recursos

```php
enum ExecutionResourceType: string
{
    case DatabaseTransaction = 'database_transaction';
    case Lock = 'lock';
    case FileHandle = 'file_handle';
    case Stream = 'stream';
    case TemporaryFile = 'temporary_file';
    case NetworkConnection = 'network_connection';
    case Subscription = 'subscription';
    case Span = 'span';
    case Buffer = 'buffer';
    case ScopedService = 'scoped_service';
    case Custom = 'custom';
}
```

---

# 72. Ownership

Todo recurso deberá tener un propietario.

```text
Execution owns resource
    │
    ▼
Execution cleanup releases resource
```

Un recurso transferido al transporte deberá cambiar formalmente de ownership.

---

# 73. ResourceOwnership

```php
enum ResourceOwnership: string
{
    case Execution = 'execution';
    case Interceptor = 'interceptor';
    case Controller = 'controller';
    case Transformation = 'transformation';
    case Transport = 'transport';
    case External = 'external';
    case Released = 'released';
}
```

---

# 74. Transferencia de ownership

Ejemplo:

```text
Controller opens stream
    │
    ▼
Registers stream
    │
    ▼
Transformation creates StreamResponseBody
    │
    ▼
Ownership transferred to Transport
    │
    ▼
Transport closes stream
```

---

# 75. CleanupCoordinator

```php
interface CleanupCoordinatorInterface
{
    public function cleanup(
        ControllerExecution $execution
    ): CleanupResult;
}
```

---

# 76. CleanupResult

```php
final readonly class CleanupResult
{
    public function __construct(
        public CleanupStatus $status,
        public array $releasedResources,
        public array $failedResources,
        public WorkerDisposition $workerDisposition,
    ) {
    }
}
```

---

# 77. CleanupStatus

```php
enum CleanupStatus: string
{
    case NotStarted = 'not_started';
    case Running = 'running';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
}
```

---

# 78. Orden de cleanup

El cleanup deberá ejecutarse en orden inverso a la adquisición cuando sea posible.

```text
Last acquired
    ↓
First released
```

Esto sigue una semántica LIFO.

---

# 79. Cleanup handlers

```text
TransportCleanupHandler
StreamCleanupHandler
TransactionCleanupHandler
LockCleanupHandler
TemporaryFileCleanupHandler
BufferCleanupHandler
ScopedContainerCleanupHandler
ContextCleanupHandler
TelemetryCleanupHandler
```

---

# 80. Cleanup obligatorio

El cleanup se ejecutará en:

* éxito;
* short-circuit;
* cancelación;
* excepción;
* recuperación;
* emisión parcial;
* shutdown controlado.

---

# 81. Cleanup failure

Un error de cleanup:

* no reemplazará automáticamente el resultado principal;
* se reportará como error secundario;
* podrá afectar la salud del Worker;
* podrá provocar terminación del Worker.

---

# 82. Transaction lifecycle

Las transacciones deberán administrarse principalmente mediante interceptores.

El lifecycle registrará:

* inicio;
* commit;
* rollback;
* ownership;
* estado final.

---

# 83. Transacciones abiertas

Durante cleanup:

```text
Open transaction
    │
    ▼
Rollback
    │
    ▼
Mark resource released
```

Nunca deberá mantenerse una transacción abierta entre peticiones.

---

# 84. Lock lifecycle

Todo lock adquirido deberá:

* registrarse;
* asociarse a un owner;
* liberarse en cleanup;
* soportar timeout;
* registrar fallo de liberación.

---

# 85. Scoped services

Los servicios request-scoped deberán destruirse o resetearse al finalizar.

---

# 86. ExecutionScope

```php
interface ExecutionScopeInterface
{
    public function id(): string;

    public function enter(): void;

    public function leave(): void;

    public function reset(): void;
}
```

---

# 87. Worker safety

En FrankenPHP, la ejecución ocurre dentro de un proceso persistente.

```text
Worker boot
    │
Execution A
    │
Cleanup A
    │
Reset
    │
Execution B
```

El lifecycle deberá garantizar que A no contamine B.

---

# 88. Datos que nunca se compartirán

* Request;
* RouteMatch mutable;
* ControllerExecution;
* argumentos resueltos;
* resultado bruto;
* response;
* transport result;
* Throwable;
* cancellation token;
* resources;
* timeline;
* tenant context;
* user context.

---

# 89. Datos reutilizables

* lifecycle plans compilados;
* registries congelados;
* guards stateless;
* phase handlers stateless;
* metadata compilada;
* políticas inmutables;
* catálogos.

---

# 90. Worker reset sequence

```text
Finish transport
    │
    ▼
Close resources
    │
    ▼
Reset request scope
    │
    ▼
Clear context holders
    │
    ▼
Reset static bridges
    │
    ▼
Flush telemetry
    │
    ▼
Evaluate worker health
```

---

# 91. WorkerDisposition

Se reutilizará:

```php
enum WorkerDisposition: string
{
    case Reuse = 'reuse';
    case Reset = 'reset';
    case RestartRecommended = 'restart_recommended';
    case Terminate = 'terminate';
}
```

---

# 92. WorkerHealthEvaluator

Considerará:

* errores de cleanup;
* recursos abiertos;
* memoria;
* estado del container;
* buffers;
* transacciones;
* locks;
* fatal errors;
* excepciones críticas;
* estado del runtime.

---

# 93. ControllerExecutionTimeline

Registra eventos temporales.

```php
final class ControllerExecutionTimeline
{
    public function record(
        ExecutionTimelineEntry $entry
    ): void;

    public function entries(): array;

    public function duration(): float;
}
```

---

# 94. ExecutionTimelineEntry

```php
final readonly class ExecutionTimelineEntry
{
    public function __construct(
        public ControllerExecutionPhase $phase,
        public string $event,
        public float $timestamp,
        public ?float $duration,
        public array $metadata = [],
    ) {
    }
}
```

---

# 95. Timeline estándar

```text
execution.created
initialization.started
controller_resolution.started
controller_resolution.completed
metadata_resolution.completed
parameter_resolution.completed
interceptors.started
invocation.started
invocation.completed
transformation.started
transformation.completed
transport.started
transport.completed
cleanup.started
cleanup.completed
```

---

# 96. ExecutionSnapshot

Representación inmutable del estado en un momento.

```php
final readonly class ExecutionSnapshot
{
    public function __construct(
        public ControllerExecutionId $id,
        public ControllerExecutionStatus $status,
        public ControllerExecutionPhase $phase,
        public array $flags,
        public array $resourceSummary,
        public float $timestamp,
    ) {
    }
}
```

---

# 97. Uso de snapshots

Los snapshots servirán para:

* debugging;
* tracing;
* pruebas;
* diagnósticos;
* eventos;
* time travel parcial en herramientas de desarrollo.

No deberán contener objetos pesados.

---

# 98. ExecutionRecorder

```php
interface ExecutionRecorderInterface
{
    public function recordTransition(
        ControllerExecution $execution,
        ControllerExecutionTransition $transition
    ): void;

    public function recordSnapshot(
        ExecutionSnapshot $snapshot
    ): void;
}
```

---

# 99. Eventos

```text
ControllerExecutionCreated
ControllerExecutionInitializing
ControllerExecutionStarted
ControllerResolutionStarted
ControllerResolved
MetadataResolutionStarted
MetadataResolved
ParameterResolutionStarted
ParametersResolved
InterceptorsResolved
BeforeInterceptorsStarted
ControllerInvocationStarted
ControllerInvoked
AfterInterceptorsStarted
ResultTransformationStarted
ResultTransformed
TransportStarted
ResponseEmitted
ExecutionShortCircuited
ExecutionCancellationRequested
ExecutionCancelled
ExecutionFailed
ExecutionRecoveryStarted
ExecutionRecovered
ExecutionCompleting
ExecutionCompleted
ExecutionCleanupStarted
ExecutionCleanupCompleted
WorkerDispositionResolved
ControllerExecutionTerminated
```

---

# 100. Event payloads

Los eventos deberán contener referencias seguras:

* execution ID;
* phase;
* status;
* route;
* controller;
* duration;
* trace ID;
* metadata sanitizada.

No deberán contener automáticamente:

* body;
* cookies;
* credentials;
* objetos completos;
* archivos.

---

# 101. Métricas

```text
controller.execution.total
controller.execution.duration
controller.execution.completed
controller.execution.failed
controller.execution.cancelled
controller.execution.short_circuited
controller.execution.cleanup_failed
controller.execution.phase.duration
controller.execution.resources.acquired
controller.execution.resources.released
controller.execution.worker_terminated
```

---

# 102. Métricas por fase

```text
controller.phase.controller_resolution.duration
controller.phase.metadata.duration
controller.phase.parameters.duration
controller.phase.interceptors.duration
controller.phase.invocation.duration
controller.phase.transformation.duration
controller.phase.transport.duration
controller.phase.cleanup.duration
```

---

# 103. Tracing

Span principal:

```text
controller.execution
```

Subspans:

```text
controller.resolve
controller.metadata
controller.parameters
controller.interceptors
controller.invoke
controller.transform
controller.transport
controller.cleanup
```

---

# 104. Context propagation

El contexto de tracing deberá propagarse a:

* eventos;
* logging;
* queries;
* HTTP clients;
* colas;
* streams;
* subrequests.

---

# 105. Error handling integration

Cuando ocurra una excepción:

```text
Lifecycle phase
    │
    ▼
Throwable
    │
    ▼
State → Failed
    │
    ▼
ExceptionHandlingSystem
    │
    ├── Response
    ├── Recovery
    ├── Rethrow
    └── Terminate
```

---

# 106. Recovery integration

Si el sistema de excepciones devuelve una recuperación:

```text
Failed
    │
    ▼
Recovering
    │
    ├── Replacement result
    │       ▼
    │   Transformation
    │
    ├── Replacement response
    │       ▼
    │   Transport
    │
    └── Retry phase
```

---

# 107. Retry de fase

Los retries deberán limitarse a fases explícitamente retryable.

No podrán reintentarse automáticamente:

* emisión iniciada;
* controlador no idempotente;
* transacción parcialmente confirmada;
* side effects desconocidos.

---

# 108. RetryPolicy

```php
final readonly class ControllerPhaseRetryPolicy
{
    public function __construct(
        public int $maxAttempts,
        public array $retryablePhases,
        public bool $requiresIdempotency,
        public ?float $backoff,
    ) {
    }
}
```

---

# 109. Idempotency

El lifecycle podrá consumir metadata de idempotencia.

```text
execution.idempotent
execution.idempotency_key
execution.retryable
```

---

# 110. Completion

La fase de completion ocurre después de obtener un resultado terminal.

Podrá ejecutar:

* eventos finales;
* métricas;
* after callbacks;
* commit tardío permitido;
* finalización de tracing;
* snapshot terminal.

---

# 111. Completion no es cleanup

```text
Completion
    └── Cierra semánticamente la ejecución

Cleanup
    └── Libera recursos técnicos
```

Ambos conceptos deberán mantenerse separados.

---

# 112. Completion hooks

```php
interface ControllerExecutionCompletionHookInterface
{
    public function complete(
        ControllerExecution $execution
    ): void;
}
```

---

# 113. Cleanup hooks

```php
interface ControllerExecutionCleanupHookInterface
{
    public function cleanup(
        ControllerExecution $execution
    ): void;
}
```

---

# 114. Hook failure policy

Un fallo en completion podrá convertir la ejecución en fallida si aún no comenzó la emisión.

Un fallo en cleanup se tratará como fallo secundario y afectará la salud del Worker.

---

# 115. Lifecycle callbacks

Podrán registrarse callbacks:

```php
$execution->onCompletion(...);

$execution->onCleanup(...);

$execution->onCancellation(...);

$execution->onFailure(...);
```

Estos callbacks deberán ser controlados y no sustituirán eventos globales.

---

# 116. Prioridad de callbacks

Los callbacks deberán ejecutarse:

* en orden explícito;
* con aislamiento de errores;
* respetando cancelación;
* sin modificar estados terminales ilegalmente.

---

# 117. Lifecycle metadata

```text
lifecycle.plan
lifecycle.timeout
lifecycle.cancellable
lifecycle.retry
lifecycle.idempotent
lifecycle.cleanup
lifecycle.resources
lifecycle.short_circuit
lifecycle.worker_disposition
```

---

# 118. Attributes potenciales

```php
#[ExecutionTimeout(5)]
#[Cancellable]
#[NonCancellable]
#[Idempotent]
#[RetryPhase(...)]
#[CleanupWith(...)]
#[ShortCircuitPolicy(...)]
#[WorkerDisposition(...)]
```

Los atributos serán procesados por el Metadata Engine.

---

# 119. Lifecycle registry

```php
interface ControllerLifecycleRegistryInterface
{
    public function registerPhaseHandler(
        ControllerLifecyclePhaseHandlerInterface $handler
    ): void;

    public function registerGuard(
        ExecutionGuardInterface $guard
    ): void;

    public function registerCleanupHandler(
        CleanupHandlerInterface $handler
    ): void;

    public function freeze(): void;
}
```

---

# 120. Freeze

El registry deberá congelarse después del bootstrap.

Esto garantiza:

* orden estable;
* planes reproducibles;
* seguridad en Workers;
* compilación consistente.

---

# 121. Lifecycle compiler

```php
interface ControllerLifecycleCompilerInterface
{
    public function compile(): CompiledControllerLifecycleRegistry;
}
```

---

# 122. Compilación

El compiler podrá resolver:

* phases;
* handlers;
* guards;
* cleanup;
* metadata;
* timeout policies;
* short-circuit policies;
* retry policies;
* worker policies.

---

# 123. Cache multinivel

```text
L1 Execution
L2 Request
L3 Worker
L4 Compiled
```

---

# 124. L1 Execution

Contendrá:

* state;
* plan;
* phase results;
* cancellation checks;
* resource registry;
* timeline.

---

# 125. L2 Request

Podrá almacenar información compartida con subrequests controlados.

---

# 126. L3 Worker

Podrá almacenar:

* planes compilados;
* handlers stateless;
* guards stateless;
* registries congelados;
* políticas inmutables.

---

# 127. L4 Compiled

Artefactos PHP optimizados para OPcache.

---

# 128. Invalidation

Los planes se invalidarán por cambios en:

* ruta;
* controlador;
* metadata;
* interceptores;
* configuración;
* guards;
* cleanup handlers;
* runtime;
* versión del framework.

---

# 129. Diagnóstico

El sistema deberá detectar:

* transiciones inválidas;
* recursos abiertos;
* doble invocación;
* doble transformación;
* doble emisión;
* cleanup omitido;
* cancellation ignorada;
* estados terminales incompletos;
* leaks entre Workers.

---

# 130. Diagnostic report

```php
final readonly class ControllerExecutionDiagnosticReport
{
    public function __construct(
        public array $stateIssues,
        public array $resourceLeaks,
        public array $transitionViolations,
        public array $cleanupFailures,
        public array $workerRisks,
    ) {
    }
}
```

---

# 131. Debug mode

En modo Debug se podrá mostrar:

* timeline;
* estados;
* transitions;
* recursos;
* short-circuit origin;
* cancellation;
* phase durations;
* cleanup result.

---

# 132. Producción

En producción se limitará la exposición, pero se mantendrán:

* métricas;
* tracing;
* fingerprints;
* error IDs;
* estados agregados.

---

# 133. Testing

El módulo incluirá:

```text
FakeControllerExecutionManager
FakeLifecyclePhaseHandler
FakeCancellationToken
FakeExecutionResource
FakeCleanupHandler
InMemoryExecutionRecorder
ControllerExecutionTestHarness
ControllerExecutionAssertions
```

---

# 134. Assertions

```php
ControllerExecutionAssert::completed($execution);

ControllerExecutionAssert::phaseVisited(
    $execution,
    ControllerExecutionPhase::Invocation
);

ControllerExecutionAssert::shortCircuited($execution);

ControllerExecutionAssert::cancelled(
    $execution,
    CancellationReason::Timeout
);

ControllerExecutionAssert::allResourcesReleased($execution);
```

---

# 135. Casos de prueba

* flujo normal;
* closure;
* invokable controller;
* short-circuit antes de invocar;
* short-circuit después de interceptor;
* cancelación antes de invocar;
* cancelación durante streaming;
* excepción en parámetros;
* excepción en invocación;
* excepción en transformación;
* excepción antes de emisión;
* excepción después de emisión;
* recuperación;
* retry permitido;
* retry rechazado;
* cleanup fallido;
* Worker no reusable;
* subrequest;
* recursión;
* doble invocación;
* doble emisión.

---

# 136. Benchmarks

```text
Standard controller execution
Compiled execution plan
Dynamic execution plan
Short-circuit execution
Cancelled execution
Exception flow
Streaming execution
FrankenPHP repeated executions
Subrequest execution
```

---

# 137. Performance principles

El lifecycle no deberá introducir reflexión en runtime.

Deberá evitar:

* reconstrucción de fases;
* creación innecesaria de snapshots;
* listeners costosos en producción;
* duplicación de metadata;
* copias grandes de resultados;
* closures persistentes con referencias request-scoped.

---

# 138. Estructura de directorios

```text
src/
└── Quantum/
    └── Controllers/
        └── Lifecycle/
            ├── Contracts/
            │   ├── ControllerExecutionManagerInterface.php
            │   ├── ControllerExecutionStateMachineInterface.php
            │   ├── ControllerLifecyclePlanResolverInterface.php
            │   ├── ControllerLifecyclePhaseHandlerInterface.php
            │   ├── ExecutionGuardInterface.php
            │   ├── CleanupCoordinatorInterface.php
            │   ├── CleanupHandlerInterface.php
            │   ├── ExecutionResourceInterface.php
            │   └── ExecutionRecorderInterface.php
            │
            ├── Engine/
            │   └── ControllerExecutionManager.php
            │
            ├── Execution/
            │   ├── ControllerExecution.php
            │   ├── ControllerExecutionRequest.php
            │   ├── ControllerExecutionResult.php
            │   ├── ControllerExecutionId.php
            │   └── ControllerExecutionContext.php
            │
            ├── State/
            │   ├── ControllerExecutionState.php
            │   ├── ControllerExecutionStatus.php
            │   ├── ControllerExecutionPhase.php
            │   ├── ControllerExecutionTransition.php
            │   ├── ControllerExecutionStateMachine.php
            │   └── InvalidControllerExecutionTransitionException.php
            │
            ├── Planning/
            │   ├── ControllerLifecyclePlan.php
            │   ├── ControllerLifecyclePlanResolver.php
            │   ├── DynamicControllerLifecyclePlanFactory.php
            │   ├── CompiledLifecyclePlan.php
            │   └── LifecyclePlanValidator.php
            │
            ├── Phases/
            │   ├── InitializationPhaseHandler.php
            │   ├── ControllerResolutionPhaseHandler.php
            │   ├── MetadataResolutionPhaseHandler.php
            │   ├── ParameterResolutionPhaseHandler.php
            │   ├── InterceptorResolutionPhaseHandler.php
            │   ├── InvocationPhaseHandler.php
            │   ├── TransformationPhaseHandler.php
            │   ├── TransportPhaseHandler.php
            │   ├── CompletionPhaseHandler.php
            │   └── CleanupPhaseHandler.php
            │
            ├── Guards/
            │   ├── StateTransitionGuard.php
            │   ├── ControllerInvocationGuard.php
            │   ├── TransformationGuard.php
            │   ├── TransportGuard.php
            │   ├── ResponseMutationGuard.php
            │   ├── CleanupGuard.php
            │   ├── CancellationGuard.php
            │   ├── ResourceOwnershipGuard.php
            │   └── ExecutionRecursionGuard.php
            │
            ├── Cancellation/
            │   ├── CancellationToken.php
            │   ├── CancellationSource.php
            │   ├── CancellationReason.php
            │   ├── ExecutionDeadline.php
            │   └── ControllerTimeoutPolicy.php
            │
            ├── ShortCircuit/
            │   ├── ShortCircuitDecision.php
            │   ├── ShortCircuitOrigin.php
            │   ├── ShortCircuitMode.php
            │   └── ShortCircuitHandler.php
            │
            ├── Resources/
            │   ├── ControllerExecutionResources.php
            │   ├── ExecutionResourceType.php
            │   ├── ResourceOwnership.php
            │   ├── ResourceOwnershipTransfer.php
            │   └── Resources/
            │       ├── TransactionExecutionResource.php
            │       ├── LockExecutionResource.php
            │       ├── StreamExecutionResource.php
            │       ├── FileHandleExecutionResource.php
            │       ├── TemporaryFileExecutionResource.php
            │       └── ScopedServiceExecutionResource.php
            │
            ├── Cleanup/
            │   ├── CleanupCoordinator.php
            │   ├── CleanupContext.php
            │   ├── CleanupResult.php
            │   ├── CleanupStatus.php
            │   └── Handlers/
            │       ├── TransportCleanupHandler.php
            │       ├── StreamCleanupHandler.php
            │       ├── TransactionCleanupHandler.php
            │       ├── LockCleanupHandler.php
            │       ├── TemporaryFileCleanupHandler.php
            │       ├── BufferCleanupHandler.php
            │       ├── ScopedContainerCleanupHandler.php
            │       └── TelemetryCleanupHandler.php
            │
            ├── Timeline/
            │   ├── ControllerExecutionTimeline.php
            │   ├── ExecutionTimelineEntry.php
            │   ├── ExecutionSnapshot.php
            │   └── ExecutionRecorder.php
            │
            ├── Scope/
            │   ├── ExecutionScope.php
            │   ├── ExecutionScopeManager.php
            │   └── ExecutionScopeResetter.php
            │
            ├── Worker/
            │   ├── WorkerHealthEvaluator.php
            │   ├── WorkerDisposition.php
            │   └── ControllerExecutionWorkerResetter.php
            │
            ├── Subrequest/
            │   ├── ParentExecutionReference.php
            │   ├── ChildExecutionFactory.php
            │   └── ExecutionDepthGuard.php
            │
            ├── Compiler/
            │   ├── ControllerLifecycleCompiler.php
            │   ├── CompiledControllerLifecycleRegistry.php
            │   ├── CompiledLifecyclePlan.php
            │   └── LifecycleArtifactWriter.php
            │
            ├── Cache/
            ├── Metadata/
            ├── Events/
            ├── Metrics/
            ├── Diagnostics/
            ├── Exceptions/
            ├── Testing/
            └── Providers/
                └── ControllerLifecycleServiceProvider.php
```

---

# 139. Configuración

```php
// config/controller_lifecycle.php

return [
    'mode' => 'auto',

    'compiled' => [
        'enabled' => true,
        'strict' => false,
        'path' => storage_path('framework/controllers/lifecycle'),
    ],

    'cancellation' => [
        'enabled' => true,
        'checkpoints' => true,
        'propagate_to_children' => true,
    ],

    'timeouts' => [
        'enabled' => true,
        'default' => null,
    ],

    'short_circuit' => [
        'enabled' => true,
        'record_origin' => true,
    ],

    'resources' => [
        'track' => true,
        'strict_ownership' => true,
        'release_reverse_order' => true,
    ],

    'cleanup' => [
        'always' => true,
        'fail_on_leak' => false,
    ],

    'workers' => [
        'reset_after_execution' => true,
        'evaluate_health' => true,
        'terminate_on_corruption' => true,
    ],

    'observability' => [
        'events' => true,
        'metrics' => true,
        'tracing' => true,
        'snapshots' => false,
        'timeline' => true,
    ],
];
```

---

# 140. Service Provider

```php
final class ControllerLifecycleServiceProvider
    extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(
            ControllerExecutionManagerInterface::class,
            ControllerExecutionManager::class,
        );

        $this->container->singleton(
            ControllerExecutionStateMachineInterface::class,
            ControllerExecutionStateMachine::class,
        );

        $this->container->singleton(
            CleanupCoordinatorInterface::class,
            CleanupCoordinator::class,
        );

        $this->container->singleton(
            ControllerLifecycleRegistryInterface::class,
            ControllerLifecycleRegistry::class,
        );
    }

    public function boot(
        ControllerLifecycleRegistryInterface $registry
    ): void {
        $registry->freeze();
    }
}
```

---

# 141. Integración con HttpKernel

El `HttpKernel` deberá delegar el ciclo completo.

```php
$result = $controllerExecutionManager->execute(
    new ControllerExecutionRequest(
        request: $request,
        routeMatch: $routeMatch,
        runtime: $runtimeContext,
        metadata: $routeMetadata,
        cancellation: $cancellationToken,
    ),
);
```

El `HttpKernel` no deberá coordinar manualmente cada motor.

---

# 142. ControllerExecutionResult

```php
final readonly class ControllerExecutionResult
{
    public function __construct(
        public ControllerExecutionId $executionId,
        public ControllerExecutionStatus $status,
        public ?ResponseInterface $response,
        public ?TransportResult $transportResult,
        public ?ExceptionHandlingResult $exceptionResult,
        public CleanupResult $cleanup,
        public WorkerDisposition $workerDisposition,
    ) {
    }
}
```

---

# 143. Flujo end-to-end

```text
HttpKernel
    │
    ▼
ControllerExecutionManager
    │
    ▼
Create ControllerExecution
    │
    ▼
Resolve Lifecycle Plan
    │
    ▼
Controller Resolver
    │
    ▼
Metadata Engine
    │
    ▼
Parameter Resolution Engine
    │
    ▼
Interceptor Pipeline
    │
    ▼
Controller Invoker
    │
    ▼
Result Transformation Engine
    │
    ▼
Response Transport System
    │
    ▼
Completion
    │
    ▼
Cleanup
    │
    ▼
Worker Health Evaluation
```

---

# 144. Flujo con short-circuit

```text
Interceptor
    │
    ▼
ShortCircuitDecision
    │
    ▼
Skip invocation
    │
    ▼
Transform result or use response
    │
    ▼
Transport
    │
    ▼
Cleanup
```

---

# 145. Flujo con cancelación

```text
Cancellation requested
    │
    ▼
State → Cancelling
    │
    ▼
Stop safe operations
    │
    ▼
Abort or produce cancellation response
    │
    ▼
State → Cancelled
    │
    ▼
Cleanup
```

---

# 146. Flujo con error recuperable

```text
Phase failure
    │
    ▼
State → Failed
    │
    ▼
Exception Handling
    │
    ▼
Recovery result
    │
    ├── Replacement result
    ├── Replacement response
    ├── Retry phase
    └── Rethrow
```

---

# 147. Flujo con error después de emisión

```text
Response emission started
    │
    ▼
Throwable
    │
    ▼
Abort transport
    │
    ▼
Mark incomplete
    │
    ▼
Cleanup
    │
    ▼
Evaluate Worker
```

---

# 148. ADR-001

**Toda ejecución tendrá un único objeto `ControllerExecution`.**

---

# 149. ADR-002

**El lifecycle será coordinado por una máquina de estados explícita.**

---

# 150. ADR-003

**Las transiciones inválidas deberán fallar inmediatamente.**

---

# 151. ADR-004

**La invocación del controlador será exactamente una vez, salvo retry explícitamente seguro.**

---

# 152. ADR-005

**Short-circuit y cancelación serán conceptos distintos.**

El short-circuit produce una terminación válida; la cancelación detiene la ejecución por una causa externa o interna.

---

# 153. ADR-006

**El cleanup se ejecutará independientemente del resultado funcional.**

---

# 154. ADR-007

**Completion y cleanup serán fases separadas.**

---

# 155. ADR-008

**Todo recurso tendrá ownership explícito.**

---

# 156. ADR-009

**La transferencia de recursos deberá registrarse formalmente.**

---

# 157. ADR-010

**La cancelación será cooperativa.**

---

# 158. ADR-011

**La emisión iniciada constituye una frontera irreversible.**

---

# 159. ADR-012

**Los estados request-scoped nunca se compartirán entre Workers.**

---

# 160. ADR-013

**Los planes compilados no contendrán estado de ejecución.**

---

# 161. ADR-014

**Los errores de cleanup no reemplazarán automáticamente el resultado principal.**

---

# 162. ADR-015

**La salud del Worker se evaluará después de cada ejecución anormal.**

---

# 163. ADR-016

**Los subrequests tendrán ejecuciones independientes enlazadas al padre.**

---

# 164. ADR-017

**La cancelación del padre podrá propagarse a hijos.**

---

# 165. ADR-018

**Los retries deberán respetar idempotencia y fronteras irreversibles.**

---

# 166. ADR-019

**La observabilidad consumirá snapshots seguros, no el objeto mutable completo.**

---

# 167. ADR-020

**El HttpKernel delegará el lifecycle completo al `ControllerExecutionManager`.**

---

# 168. Implementación V1

La V1 deberá incluir:

* ControllerExecutionManager;
* ControllerExecution;
* ControllerExecutionContext;
* ControllerExecutionState;
* máquina de estados;
* fases estándar;
* lifecycle plan;
* guards;
* short-circuit;
* cancellation token;
* deadlines básicos;
* resource registry;
* ownership;
* cleanup coordinator;
* timeline;
* events;
* metrics;
* tracing;
* exception integration;
* transport integration;
* Worker reset;
* FrankenPHP safety;
* compiled lifecycle plans;
* testing utilities.

---

# 169. Fuera de V1

Se aplazarán:

* cancelación forzada;
* suspensión y reanudación;
* workflows distribuidos;
* checkpoint persistente;
* migración de ejecución entre Workers;
* backpressure generalizado;
* time travel completo;
* recuperación transaccional distribuida.

---

# 170. Roadmap V2

Podrá incorporar:

* structured concurrency;
* parent-child execution trees;
* backpressure;
* deadlines propagados;
* phase retries avanzados;
* resource leak detection en tiempo real;
* ejecución asíncrona;
* suspension points;
* mejor integración WebSocket.

---

# 171. Roadmap V3

Podrá incluir:

* ejecución distribuida;
* persistencia de lifecycle;
* recuperación entre nodos;
* planificación adaptativa;
* optimización automática de fases;
* análisis predictivo de fallos;
* replay controlado;
* observabilidad causal completa.

---

# 172. Beneficios de la arquitectura

Este sistema proporciona:

* ciclo de vida predecible;
* invariantes verificables;
* cancelación segura;
* short-circuit formal;
* cleanup garantizado;
* control de recursos;
* Workers persistentes confiables;
* observabilidad completa;
* testing determinista;
* planes compilables;
* extensibilidad por fases;
* separación clara entre motores.

---

# 173. Conclusión

El **Controller Lifecycle and Execution State System** une todos los subsistemas de Controllers en una ejecución coherente.

A partir de este diseño, VoltStack deja de depender de una secuencia implícita de llamadas y adopta un modelo formal basado en:

* estados;
* fases;
* transiciones;
* guards;
* planes;
* recursos;
* cancelación;
* short-circuit;
* cleanup;
* salud del Worker.

Esto permite que cada motor conserve su responsabilidad individual, mientras el framework mantiene control completo sobre el ciclo de ejecución.
