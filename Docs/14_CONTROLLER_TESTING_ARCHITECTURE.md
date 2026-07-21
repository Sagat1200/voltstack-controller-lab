# Arquitectura de pruebas del subsistema Controllers de VoltStack


**Versión:** 1.0
**Estado:** Draft arquitectónico
**Módulo:** `VoltStack\Quantum\Controllers\Testing`
**Ámbito:** Pruebas unitarias, integración, contratos, compilación, lifecycle, Workers persistentes, concurrencia y rendimiento
**Integraciones principales:** Resolver, Parameters, Metadata, Interceptors, Invoker, Transformation, Transport, Exceptions, Lifecycle, Observability y Compilation Framework

---

## 1. Introducción

El **Controller Testing Architecture** define la estrategia completa de verificación del subsistema Controllers de VoltStack.

El pipeline de controladores está compuesto por múltiples motores especializados:

```text
Routing
    ↓
ControllerResolver
    ↓
MetadataEngine
    ↓
ParameterResolutionEngine
    ↓
InterceptorPipeline
    ↓
ControllerInvoker
    ↓
ResultTransformationEngine
    ↓
ResponseTransportSystem
    ↓
ExceptionHandlingSystem
    ↓
Lifecycle and Execution State
    ↓
Observability
```

Además, el sistema puede operar mediante:

* resolución dinámica;
* planes compilados;
* cachés multinivel;
* Workers persistentes;
* FrankenPHP;
* short-circuit;
* cancelación;
* streaming;
* subrequests;
* recuperación de errores.

La estrategia de pruebas deberá verificar no solo cada componente, sino también las relaciones e invariantes existentes entre ellos.

---

## 2. Objetivo principal

Garantizar que el subsistema Controllers sea:

* correcto;
* determinista;
* extensible;
* seguro;
* observable;
* compatible con Workers persistentes;
* equivalente en modo dinámico y compilado;
* resistente ante errores;
* estable bajo concurrencia;
* medible en rendimiento.

---

## 3. Principios de testing

La arquitectura seguirá estos principios:

* Cada responsabilidad tendrá pruebas aisladas.
* Cada contrato público tendrá contract tests.
* Las integraciones críticas tendrán pruebas end-to-end.
* El modo dinámico y el compilado deberán ser equivalentes.
* Los estados terminales deberán verificarse explícitamente.
* El cleanup deberá probarse incluso en escenarios fallidos.
* Los tests no dependerán del orden global.
* Los tests deberán ser reproducibles.
* Los fixtures deberán ser pequeños y expresivos.
* Los fakes deberán respetar los contratos reales.
* Los Workers persistentes deberán probar aislamiento entre ejecuciones.
* Las métricas y eventos deberán probarse sin depender de backends externos.
* El rendimiento deberá medirse separadamente de la corrección funcional.
* Los errores de observabilidad no deberán romper pruebas funcionales.

---

## 4. Objetivos de cobertura

La cobertura deberá verificarse en varias dimensiones:

```text
Code coverage
Branch coverage
State coverage
Transition coverage
Contract coverage
Pipeline coverage
Failure coverage
Worker isolation coverage
Compilation equivalence coverage
```

La cobertura de líneas no será suficiente por sí sola.

---

## 5. Pirámide de pruebas

```text
                 End-to-End
                    ▲
              Integration Tests
                    ▲
               Contract Tests
                    ▲
                 Unit Tests
```

Distribución orientativa:

* muchas pruebas unitarias;
* numerosas pruebas de contrato;
* pruebas de integración selectivas;
* pocas pruebas end-to-end completas;
* benchmarks separados.

---

## 6. Tipos principales de pruebas

```text
Unit Tests
Contract Tests
Integration Tests
Pipeline Tests
Lifecycle Tests
State Machine Tests
Failure Tests
Compilation Tests
Cache Tests
Worker Tests
Concurrency Tests
Security Tests
Observability Tests
Performance Tests
Mutation Tests
Regression Tests
End-to-End Tests
```

---

## 7. Organización del módulo Testing

El módulo deberá proporcionar:

* fakes;
* spies;
* stubs;
* fixtures;
* builders;
* harnesses;
* assertions;
* datasets;
* contract suites;
* worker simulators;
* compilation sandboxes;
* test clocks;
* deterministic ID generators.

---

## 8. Estructura general de pruebas

```text
tests/
├── Unit/
├── Contracts/
├── Integration/
├── Pipeline/
├── Lifecycle/
├── Compilation/
├── Workers/
├── Concurrency/
├── Security/
├── Observability/
├── Performance/
├── Mutation/
├── Regression/
├── EndToEnd/
├── Fixtures/
└── Support/
```

---

## 9. Convenciones de nombres

Clases de prueba:

```text
ControllerResolverTest
ParameterResolutionEngineTest
ControllerExecutionStateMachineTest
CompiledControllerExecutionBundleTest
FrankenPhpWorkerIsolationTest
```

Métodos:

```text
it_resolves_a_controller_from_a_compiled_plan
it_rejects_an_invalid_state_transition
it_releases_resources_after_a_failed_transport
```

---

## 10. Estilo de pruebas

El estilo recomendado será:

```php
public function test_it_resolves_route_parameters(): void
{
    $engine = $this->makeEngine();

    $result = $engine->resolve(
        $this->request('/users/42'),
        $this->parameterPlan(),
    );

    self::assertSame(42, $result->get('id'));
}
```

Las pruebas deberán expresar:

* contexto;
* acción;
* resultado esperado.

---

## 11. TestCase base

```php
abstract class ControllerTestCase extends TestCase
{
    protected ControllerTestEnvironment $environment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->environment = ControllerTestEnvironment::create();
    }
}
```

---

## 12. ControllerTestEnvironment

```php
final class ControllerTestEnvironment
{
    public function __construct(
        public TestContainer $container,
        public FakeEventDispatcher $events,
        public FakeMetricRecorder $metrics,
        public FakeTraceRecorder $traces,
        public FakeClock $clock,
        public DeterministicIdGenerator $ids,
        public InMemoryArtifactStore $artifacts,
    ) {
    }
}
```

---

## 13. Determinismo

Los tests deberán controlar:

* tiempo;
* IDs;
* aleatoriedad;
* fingerprints;
* orden de registries;
* filesystem temporal;
* runtime capabilities;
* entorno.

---

## 14. FakeClock

```php
interface TestClockInterface
{
    public function now(): float;

    public function advance(float $seconds): void;
}
```

Esto permitirá probar:

* deadlines;
* timeouts;
* durations;
* timelines;
* métricas;
* retries.

---

## 15. DeterministicIdGenerator

```php
final class DeterministicIdGenerator
{
    private int $sequence = 0;

    public function next(string $prefix): string
    {
        return $prefix.'_'.++$this->sequence;
    }
}
```

---

## 16. Fixtures

Los fixtures oficiales deberán incluir:

```text
SimpleController
InvokableController
StaticController
ServiceController
ResourceController
StreamingController
FailingController
CancellableController
RecursiveController
ShortCircuitController
AsyncLikeController
```

---

## 17. Fixture controllers

Ejemplo:

```php
final class SimpleController
{
    public function show(int $id): array
    {
        return ['id' => $id];
    }
}
```

---

## 18. Fixture routes

Deberán existir definiciones pequeñas para:

* ruta simple;
* parámetros;
* middleware;
* interceptores;
* SPA;
* streaming;
* descarga;
* error;
* tenant;
* subrequest.

---

## 19. Builders

La arquitectura deberá incluir builders para reducir ruido.

```php
$execution = ControllerExecutionBuilder::new()
    ->withRoute('orders.show')
    ->withController(OrderController::class, 'show')
    ->withArgument('id', 42)
    ->build();
```

---

## 20. Builders oficiales

```text
ControllerExecutionBuilder
ControllerCompilationRequestBuilder
CompiledArtifactBuilder
RouteDefinitionBuilder
MetadataBagBuilder
InterceptorPlanBuilder
TransportPlanBuilder
ExceptionPlanBuilder
ObservabilityContextBuilder
```

---

## 21. Assertions especializadas

```text
ControllerExecutionAssert
ControllerLifecycleAssert
ControllerCompilationAssert
CompiledArtifactAssert
ControllerObservabilityAssert
ControllerTransportAssert
ControllerExceptionAssert
ControllerResourceAssert
WorkerIsolationAssert
```

---

## 22. ControllerExecutionAssert

```php
ControllerExecutionAssert::completed($execution);

ControllerExecutionAssert::invokedOnce($execution);

ControllerExecutionAssert::phaseVisited(
    $execution,
    ControllerExecutionPhase::Invocation
);

ControllerExecutionAssert::allResourcesReleased($execution);
```

---

## 23. Unit testing

Las pruebas unitarias deberán verificar una clase o unidad pequeña usando dependencias controladas.

Ejemplos:

* resolver individual;
* guard;
* state transition;
* fingerprint generator;
* sanitizer;
* policy resolver;
* artifact validator;
* cache key generator.

---

## 24. Contract testing

Las contract suites deberán validar implementaciones intercambiables de una interfaz.

Ejemplo:

```php
abstract class ControllerResolverContractTest extends TestCase
{
    abstract protected function resolver(): ControllerResolverInterface;
}
```

Implementaciones:

* dynamic resolver;
* compiled resolver;
* cached resolver.

---

## 25. Contratos prioritarios

```text
ControllerResolverInterface
ParameterResolverInterface
MetadataProviderInterface
ControllerInterceptorInterface
ControllerInvocationStrategyInterface
ResultTransformationStrategyInterface
TransportAdapterInterface
TransportEmitterInterface
ExceptionMapperInterface
ExceptionRendererInterface
ExecutionGuardInterface
ArtifactCompilerInterface
CompiledArtifactStoreInterface
ObservabilityExporterInterface
```

---

## 26. Contract tests del Resolver

Deberán verificar:

* resolución soportada;
* error cuando no soporta;
* prioridad;
* alias;
* servicios;
* invokable;
* static;
* compiled plan;
* ausencia de estado residual.

---

## 27. Pruebas de ControllerResolver

Casos mínimos:

```text
Controller class
Controller method
Invokable controller
Closure
Service
Alias
Action
Page
Component
Resource
Static method
Invalid callable
Missing service
Non-public method
```

---

## 28. Pruebas de ParameterResolutionEngine

Deberán cubrir:

```text
Route parameters
Query parameters
Body
JSON
Headers
Cookies
Files
DTOs
Models
Enums
Container services
Session
User
Tenant
Locale
Default values
Nullable values
Variadic values
Union types
Invalid values
Missing values
```

---

## 29. Pruebas de orden de resolvers

Se verificará:

* prioridad;
* first-match;
* fallback;
* ambigüedad;
* conflicto entre attributes;
* resolver no registrado.

---

## 30. DTO Hydrator tests

Casos:

* DTO simple;
* DTO anidado;
* valores opcionales;
* coerción;
* validación;
* claves extra;
* constructor inválido;
* propiedad readonly;
* enum interno.

---

## 31. Model Binding tests

Deberán cubrir:

* ID válido;
* modelo inexistente;
* soft deleted;
* scoped binding;
* tenant binding;
* custom key;
* autorización;
* binding explícito;
* cache por request.

---

## 32. Metadata Engine tests

Se verificará:

* providers;
* merge;
* precedencia;
* schema;
* validación;
* mappers;
* attributes;
* metadata dinámica;
* plan compilado;
* registry congelado.

---

## 33. Metadata precedence

Ejemplo:

```text
Global
    ↓
Module
    ↓
Route
    ↓
Controller
    ↓
Method
    ↓
Parameter
```

Los tests deberán verificar la precedencia oficial.

---

## 34. Attribute system integration tests

Deberán cubrir:

* discovery;
* mapper;
* compiler;
* cache;
* validación;
* atributos repetibles;
* conflictos;
* targets inválidos;
* herencia;
* interfaces;
* traits cuando aplique.

---

## 35. Interceptor tests

Cada interceptor deberá probar:

* before;
* next;
* after;
* short-circuit;
* excepción;
* cleanup;
* scope;
* prioridad;
* metadata;
* compatibilidad con retry.

---

## 36. Interceptor pipeline tests

Casos:

```text
No interceptors
Single interceptor
Multiple interceptors
Priority ordering
Nested execution order
Short-circuit
Exception before next
Exception after next
Retry
Transaction rollback
Cleanup after failure
```

---

## 37. Orden esperado de interceptores

```text
A.before
B.before
Controller
B.after
A.after
```

La prueba deberá verificarlo explícitamente.

---

## 38. ControllerInvoker tests

Deberán cubrir:

* controlador instanciado;
* service controller;
* closure;
* static;
* invokable;
* argumentos ordenados;
* valor retornado;
* void;
* excepción;
* invocation plan;
* doble invocación bloqueada.

---

## 39. Invocation strategies contract

Cada strategy deberá cumplir:

* soporta solo su tipo;
* no transforma resultados;
* no resuelve parámetros;
* propaga excepciones;
* no conserva el controlador entre requests salvo scope válido.

---

## 40. ResultTransformationEngine tests

Casos:

```text
Response
Array
JSON serializable
DTO
Model
Collection
Resource
String
Null
View
VoltView
VoltComponent
VoltPage
SPAProtocol
Redirect
Stream
Download
Binary
Image
Markdown
XML
CSV
Future
Promise
Unsupported result
```

---

## 41. Content negotiation tests

Deberán cubrir:

* Accept JSON;
* Accept HTML;
* wildcard;
* content type explícito;
* fallback;
* tipo no soportado;
* SPA request;
* API route;
* prioridad de negotiators.

---

## 42. Response builder tests

Se verificará:

* status;
* headers;
* cookies;
* body;
* content type;
* charset;
* cache;
* CORS;
* compression;
* security decorators.

---

## 43. Transport tests

Casos:

```text
HTTP response
Empty body
Text body
Structured body
Binary body
File
Stream
Iterable
SSE
Range
Conditional request
Compression
Client disconnect
Partial emission
```

---

## 44. Emitter contract tests

Cada emitter deberá verificar:

* orden de status y headers;
* no doble emisión;
* body emitido una vez;
* flush;
* desconexión;
* errores;
* cleanup;
* estado final.

---

## 45. SAPI emitter tests

Deberán aislar efectos globales usando adapters o procesos separados.

No se dependerá directamente de headers reales en unit tests.

---

## 46. FrankenPHP emitter tests

Deberán validar:

* Worker reuse;
* contexto por request;
* flush;
* streaming;
* desconexión;
* reset;
* no contaminación entre peticiones.

---

## 47. Exception Handling tests

Casos:

```text
Validation exception
Authentication exception
Authorization exception
Not found
Conflict
Rate limit
Domain exception
Database exception
Timeout
Transport exception
Unknown throwable
Nested throwable
Reporter failure
Renderer failure
Emergency renderer
```

---

## 48. Error mapping tests

Se verificará:

* categoría;
* severidad;
* status;
* representación;
* sanitización;
* fingerprint;
* public error ID;
* worker disposition.

---

## 49. Recovery tests

Casos:

* fallback value;
* cached value;
* retry;
* graceful degradation;
* replacement response;
* worker reset;
* recovery failure;
* non-recoverable exception.

---

## 50. Emergency mode tests

Se deberá probar cuando fallen:

* mapper;
* renderer;
* container;
* logger;
* metadata;
* transport preparation.

El emergency renderer deberá producir una salida mínima segura.

---

## 51. Lifecycle tests

Deberán verificar el pipeline completo de estados.

Casos:

```text
Created → Completed → Cleaned
Created → ShortCircuited → Completed → Cleaned
Created → Cancelling → Cancelled → Cleaned
Created → Failed → Recovering → Completed → Cleaned
Created → Failed → Terminated → Cleaned
```

---

## 52. State machine tests

Cada transición válida e inválida deberá probarse.

Se recomienda una tabla de transición.

---

## 53. Transition dataset

```php
yield 'created to initializing' => [
    ControllerExecutionStatus::Created,
    ControllerExecutionTransition::StartInitialization,
    ControllerExecutionStatus::Initializing,
];
```

---

## 54. State coverage

La suite deberá cubrir todos los estados:

```text
Created
Initializing
Running
ShortCircuited
Cancelling
Cancelled
Failed
Recovering
Completing
Completed
CleaningUp
Cleaned
Terminated
```

---

## 55. Phase coverage

También todas las fases:

```text
Initialization
ControllerResolution
MetadataResolution
ParameterResolution
InterceptorResolution
BeforeInterceptors
Invocation
AfterInterceptors
ResultTransformation
ResponsePreparation
Transport
ExceptionHandling
Completion
Cleanup
```

---

## 56. Guard tests

Cada guard deberá probar:

* aceptación válida;
* rechazo;
* excepción exacta;
* mensaje;
* estado preservado;
* ausencia de side effects.

---

## 57. Short-circuit tests

Casos:

* desde routing;
* parameter resolution;
* middleware;
* authorization;
* cache;
* rate limiting;
* feature flag;
* mantenimiento;
* interceptor personalizado.

---

## 58. Short-circuit invariants

Se verificará:

* controlador no invocado;
* resultado transformado cuando corresponde;
* response reutilizada cuando corresponde;
* after interceptors compatibles;
* cleanup ejecutado;
* eventos emitidos.

---

## 59. Cancellation tests

Casos:

```text
Cancellation before resolution
Cancellation before invocation
Cancellation during invocation
Cancellation during transformation
Cancellation before transport
Cancellation during streaming
Cancellation after emission
Parent cancellation
Deadline exceeded
```

---

## 60. Cancellation token contract

Deberá probar:

* estado inicial;
* cancelación única;
* razón;
* listeners;
* listener tardío;
* propagación;
* throwIfCancellationRequested.

---

## 61. Resource ownership tests

Deberán verificar:

* registro;
* ownership inicial;
* transferencia;
* liberación;
* doble liberación;
* recurso externo;
* recurso filtrado;
* error de liberación.

---

## 62. Cleanup tests

Casos:

```text
Success cleanup
Failure cleanup
Short-circuit cleanup
Cancellation cleanup
Partial transport cleanup
LIFO release order
Transaction rollback
Lock release
Stream close
Temporary file deletion
```

---

## 63. Cleanup invariants

Después de toda ejecución terminal:

```text
No open transaction
No owned lock
No open stream
No request scope
No active span
No current correlation context
```

---

## 64. Subrequest tests

Deberán verificar:

* execution ID independiente;
* trace compartido;
* span hijo;
* cancellation propagation;
* depth;
* cleanup independiente;
* error aislado;
* parent result preservado.

---

## 65. Recursion tests

Casos:

* misma ruta;
* mismo controlador;
* ciclo A → B → A;
* profundidad máxima;
* error handler recursivo;
* component recursion.

---

## 66. Observability tests

Se verificará:

* eventos;
* orden;
* sequence;
* métricas;
* labels;
* spans;
* status;
* logging context;
* timeline;
* profiling;
* sampling;
* sanitización;
* reset.

---

## 67. FakeEventDispatcher

```php
final class FakeControllerEventDispatcher
{
    private array $events = [];

    public function dispatch(
        ControllerEventInterface $event
    ): void {
        $this->events[] = $event;
    }

    public function events(): array
    {
        return $this->events;
    }
}
```

---

## 68. Event assertions

```php
ControllerObservabilityAssert::eventsInOrder([
    'controllers.execution.created',
    'controllers.execution.started',
    'controllers.invocation.started',
    'controllers.invocation.completed',
    'controllers.execution.completed',
]);
```

---

## 69. Metrics tests

Deberán verificar:

* nombre;
* valor;
* labels;
* cardinality guard;
* aggregation;
* contador activo;
* durations;
* dropped signals.

---

## 70. Tracing tests

Casos:

* root span;
* child spans;
* exception;
* cancellation;
* short-circuit;
* subrequest;
* streaming;
* span leak;
* sampling disabled.

---

## 71. Sanitization tests

Se deberán incluir payloads con:

* passwords;
* authorization;
* cookies;
* tokens;
* API keys;
* session IDs;
* DSN;
* paths;
* controller arguments.

Ningún valor sensible deberá sobrevivir.

---

## 72. Sampling tests

Casos:

* always;
* never;
* probability determinista;
* parent based;
* error biased;
* latency biased;
* tail sampling;
* child consistency.

---

## 73. Compilation tests

El framework de compilación deberá probar:

* discovery;
* dependency graph;
* order;
* fingerprints;
* signatures;
* validation;
* linking;
* serialization;
* storage;
* manifests;
* loading;
* invalidation;
* warmup;
* preload;
* deployment.

---

## 74. Compiler contract tests

Cada compiler especializado deberá:

* declarar tipo;
* declarar dependencias;
* producir artefacto válido;
* ser determinista;
* no contener estado request-scoped;
* rechazar inputs incompatibles.

---

## 75. Deterministic compilation test

```php
$first = $compiler->compile($unit, $context, $dependencies);
$second = $compiler->compile($unit, $context, $dependencies);

self::assertSame(
    $first->fingerprint()->value,
    $second->fingerprint()->value,
);
```

---

## 76. Dynamic vs compiled equivalence

Esta será una suite crítica.

```text
Same request
    │
    ├── Dynamic pipeline
    └── Compiled pipeline
            │
            ▼
Equivalent result
```

---

## 77. Equivalence dimensions

Se comparará:

* response status;
* headers;
* body;
* cookies;
* exception category;
* lifecycle status;
* short-circuit;
* events esenciales;
* resource cleanup.

No será necesario que durations o IDs sean iguales.

---

## 78. Equivalence harness

```php
final class ControllerExecutionEquivalenceHarness
{
    public function compare(
        ControllerScenario $scenario
    ): ExecutionEquivalenceResult;
}
```

---

## 79. Equivalence scenarios

Deberán incluir:

* controller simple;
* parámetros;
* middleware;
* interceptor;
* DTO;
* model binding;
* SPA;
* stream;
* short-circuit;
* exception;
* recovery;
* cancellation.

---

## 80. Artifact serialization tests

Deberán validar:

* archivo PHP válido;
* carga;
* tipos preservados;
* enums;
* arrays;
* strings escapadas;
* class strings;
* ausencia de closures;
* ausencia de resources.

---

## 81. Artifact security tests

Casos:

* path traversal;
* class name malicioso;
* string con código PHP;
* metadata con caracteres de control;
* manifest alterado;
* firma inválida;
* artifact fuera del build activo.

---

## 82. Dependency graph tests

Deberán cubrir:

* orden topológico;
* dependencia directa;
* transitiva;
* ciclo;
* nodo huérfano;
* dependencia opcional;
* dependencia removida.

---

## 83. Incremental compilation tests

Casos:

```text
No changes → all reused
Controller changed → affected plans recompiled
Global transport config changed → transport dependents recompiled
Unrelated controller changed → others reused
Removed route → stale bundle pruned
```

---

## 84. Cache tests

Niveles:

```text
L0 local
L1 execution
L2 request
L3 worker
L4 artifact store
```

Cada nivel deberá probar:

* hit;
* miss;
* put;
* clear;
* invalidation;
* isolation;
* key correctness.

---

## 85. Worker cache tests

Deberán verificar:

* solo objetos inmutables;
* límite de tamaño;
* eviction;
* build-aware keys;
* clear en restart;
* no mezcla de builds.

---

## 86. Build pinning tests

Una ejecución iniciada con build A deberá terminar con A aunque build B sea activado durante su ejecución.

---

## 87. Atomic deployment tests

Proceso a validar:

```text
Build A active
Build B generated
Build B validated
Pointer switched
New execution uses B
Existing execution remains on A
```

---

## 88. Rollback tests

Se verificará:

* activación de build anterior;
* manifest válido;
* Worker recycle;
* ejecución nueva sobre build restaurado.

---

## 89. Warmup tests

Casos:

* validate all;
* preload hot routes;
* artifact corrupto;
* presupuesto;
* report;
* OPcache adapter fake.

---

## 90. Worker persistence tests

Se deberá simular un Worker que atiende múltiples requests.

```php
$worker->handle($requestA);
$worker->handle($requestB);
$worker->handle($requestC);
```

---

## 91. Worker isolation invariants

Entre peticiones no deberá persistir:

* Request anterior;
* usuario;
* tenant;
* route match;
* arguments;
* raw result;
* response;
* Throwable;
* cancellation;
* execution resources;
* spans;
* buffers;
* mutable metadata.

---

## 92. WorkerLeakDetector

```php
final class WorkerLeakDetector
{
    public function snapshot(): WorkerStateSnapshot;

    public function compare(
        WorkerStateSnapshot $before,
        WorkerStateSnapshot $after
    ): WorkerLeakReport;
}
```

---

## 93. Worker tests con errores

Se probará reutilización después de:

* excepción de dominio;
* excepción de infraestructura;
* cleanup parcial;
* stream abortado;
* reporter fallido;
* cancellation;
* fatal simulation.

---

## 94. WorkerDisposition tests

Se verificará:

```text
Reuse
Reset
RestartRecommended
Terminate
```

según los escenarios definidos.

---

## 95. Concurrency tests

PHP no siempre ejecutará threads internos, pero deberán probarse condiciones concurrentes relevantes:

* compilaciones simultáneas;
* activación de build;
* lectura durante escritura;
* locks;
* cache stampede;
* shared artifact store;
* parallel requests en Workers.

---

## 96. Compilation lock tests

Casos:

* adquisición;
* espera;
* timeout;
* liberación;
* proceso fallido;
* lock stale;
* scopes distintos.

---

## 97. Cache stampede tests

Cuando varios Workers no tengan el mismo artefacto en L3:

* la carga desde L4 podrá ocurrir concurrentemente;
* no deberá corromperse el estado;
* el artefacto seguirá siendo inmutable.

---

## 98. Process-based tests

Los escenarios que involucren:

* OPcache;
* SAPI;
* señales;
* filesystem locking;
* memory isolation;

deberán ejecutarse en procesos separados.

---

## 99. Property-based testing

Podrá utilizarse para:

* state transitions;
* parameter coercion;
* metadata merge;
* fingerprint determinism;
* cache key generation;
* sanitizer inputs;
* dependency graphs.

---

## 100. Property de state machine

Propiedad:

```text
Ninguna secuencia válida puede volver desde un estado terminal a Running.
```

---

## 101. Property de fingerprints

```text
Mismos inputs normalizados → mismo fingerprint
Cambio relevante → fingerprint diferente
Cambio irrelevante → fingerprint estable
```

---

## 102. Fuzz testing

Aplicable a:

* headers;
* route parameters;
* query strings;
* metadata;
* artifact serializer;
* manifest loader;
* correlation IDs;
* content negotiation.

---

## 103. Security testing

Deberá cubrir:

* injection;
* unsafe reflection;
* private method invocation;
* path traversal;
* header injection;
* log injection;
* response splitting;
* sensitive telemetry;
* artifact tampering;
* unauthorized model binding.

---

## 104. Mutation testing

Mutation testing deberá usarse principalmente en:

* state machine;
* guards;
* parameter validation;
* exception mapping;
* sanitizers;
* dependency invalidation;
* cache keys.

---

## 105. Objetivo de mutation score

No se establecerá únicamente un porcentaje global.

Se definirán umbrales más altos para componentes críticos:

```text
State Machine
Guards
Security
Compilation Validation
Sanitization
```

---

## 106. Regression tests

Todo bug corregido deberá añadir una prueba que:

* falle antes del fix;
* pase después;
* describa el caso;
* evite depender de detalles accidentales.

---

## 107. Snapshot testing

Podrá usarse para:

* manifests;
* compiled PHP artifacts;
* diagnostic reports;
* event catalogs;
* execution timelines.

No deberá sustituir assertions semánticas.

---

## 108. Normalización de snapshots

Antes de guardar snapshots deberán eliminarse:

* timestamps;
* paths absolutos;
* build IDs aleatorios;
* IDs no deterministas;
* duraciones variables.

---

## 109. End-to-end tests

Deberán cubrir el pipeline real desde route match hasta transport result.

Casos prioritarios:

* HTML;
* JSON;
* SPA;
* redirect;
* stream;
* validation error;
* authorization error;
* short-circuit;
* compiled execution;
* Worker repeated requests.

---

## 110. E2E con servidor real

Podrá existir una suite opcional usando:

* servidor PHP;
* FrankenPHP;
* cliente HTTP;
* filesystem temporal;
* build compilado.

Esta suite será más lenta y se ejecutará separadamente.

---

## 111. Pruebas de streaming

Se verificará:

* primer chunk;
* múltiples chunks;
* flush;
* cancelación;
* desconexión;
* error antes del primer chunk;
* error después del primer chunk;
* cierre de stream.

---

## 112. Pruebas de SSE

Deberán cubrir:

* formato;
* event names;
* retry;
* heartbeat;
* error event seguro;
* desconexión;
* cleanup.

---

## 113. Pruebas de subrequests

Se deberá verificar el comportamiento end-to-end de:

```text
Controller A
    ↓
Subrequest B
    ↓
Controller B
    ↓
Result returned to A
```

---

## 114. Performance testing

Las pruebas de rendimiento estarán separadas de las funcionales.

Tipos:

```text
Microbenchmarks
Pipeline benchmarks
Compilation benchmarks
Worker benchmarks
Memory benchmarks
Load tests
Regression benchmarks
```

---

## 115. Microbenchmarks

Medirán:

* state transition;
* manifest lookup;
* worker cache lookup;
* compiled parameter plan;
* invocation;
* strategy lookup;
* event recording no-op.

---

## 116. Pipeline benchmarks

Compararán:

```text
Dynamic pipeline
Compiled cold pipeline
Compiled warm pipeline
Compiled Worker cache hit
```

---

## 117. Compilation benchmarks

Medirán:

* full build;
* incremental build;
* fingerprinting;
* dependency graph;
* serialization;
* manifest generation;
* warmup.

---

## 118. Memory benchmarks

Medirán:

* memoria por ejecución;
* peak memory;
* Worker growth;
* artifact cache;
* preload;
* streaming;
* timeline;
* profiling.

---

## 119. Benchmark environment

Todo benchmark deberá registrar:

* PHP version;
* extensions;
* JIT;
* OPcache;
* runtime;
* OS;
* hardware;
* framework build;
* scenario.

---

## 120. Performance baselines

Los resultados deberán compararse contra baselines versionados.

Un cambio significativo deberá producir:

* advertencia;
* reporte;
* revisión manual.

---

## 121. Load testing

Los load tests deberán evaluar:

* throughput;
* latency percentiles;
* error rate;
* memory growth;
* Worker restarts;
* cache hit ratio;
* tail latency.

---

## 122. Percentiles

Se deberán observar al menos:

```text
p50
p90
p95
p99
```

No solo promedio.

---

## 123. CI pipeline

Pipeline recomendado:

```text
Static Analysis
    ↓
Coding Standards
    ↓
Unit Tests
    ↓
Contract Tests
    ↓
Integration Tests
    ↓
Compilation Equivalence
    ↓
Security Tests
    ↓
Worker Tests
    ↓
Mutation Tests
    ↓
Benchmarks
    ↓
Optional E2E
```

---

## 124. Matriz de versiones

La CI deberá probar versiones soportadas de:

* PHP;
* FrankenPHP;
* sistemas operativos relevantes;
* OPcache enabled/disabled;
* desarrollo/producción.

---

## 125. Test groups

```text
unit
contract
integration
pipeline
lifecycle
compilation
worker
concurrency
security
observability
performance
e2e
slow
```

---

## 126. Ejecución local

Comandos potenciales:

```text
volt test controllers
volt test controllers --group=unit
volt test controllers --group=worker
volt test controllers --compiled
volt test controllers --equivalence
volt test controllers --mutation
volt test controllers --benchmark
```

---

## 127. Parallel test execution

Los tests paralelos deberán usar:

* directorios aislados;
* build IDs separados;
* puertos separados;
* caches separadas;
* bases de datos independientes;
* locks namespaced.

---

## 128. Test sandbox

```php
final class ControllerTestSandbox
{
    public string $root;
    public string $storage;
    public string $builds;
    public string $cache;
}
```

---

## 129. Cleanup de pruebas

Toda prueba deberá eliminar:

* archivos temporales;
* builds;
* locks;
* sockets;
* streams;
* procesos hijos;
* contextos estáticos.

---

## 130. Failure injection

El sistema de testing deberá permitir inyectar errores.

```php
$transport->failAt(TransportFailurePoint::BodyEmission);

$compiler->failFor(CompiledArtifactType::Lifecycle);

$resource->failOnRelease();
```

---

## 131. Failure points

```text
Controller resolution
Metadata
Parameter resolver
Interceptor before
Controller invocation
Interceptor after
Transformation
Transport prepare
Headers emission
Body emission
Cleanup
Reporter
Exporter
Artifact write
Manifest activation
```

---

## 132. Chaos-style tests

Podrán simular:

* excepciones aleatorias;
* cancelaciones;
* disconnects;
* cache misses;
* artifact corruption;
* low-memory policy;
* Worker recycle.

Se usarán con seeds reproducibles.

---

## 133. Test doubles

Clasificación:

```text
Stub
Fake
Spy
Mock
Simulator
Harness
```

---

## 134. Uso recomendado

* Stub: devolver valor simple.
* Fake: implementación funcional ligera.
* Spy: registrar interacciones.
* Mock: verificar colaboración específica.
* Simulator: reproducir runtime complejo.
* Harness: coordinar escenario completo.

---

## 135. Evitar mocks excesivos

Los motores con múltiples componentes deberán probarse preferentemente con fakes reales y pequeñas integraciones.

Mocks profundamente encadenados producen pruebas frágiles.

---

## 136. Fakes oficiales

```text
FakeControllerResolver
FakeParameterResolver
FakeMetadataProvider
FakeInterceptor
FakeControllerInvoker
FakeTransformationEngine
FakeTransportAdapter
FakeTransportEmitter
FakeExceptionHandler
FakeCancellationToken
FakeExecutionResource
FakeArtifactCompiler
FakeArtifactStore
FakeObservabilityExporter
FakeWorkerRuntime
```

---

## 137. InMemoryArtifactStore

Deberá comportarse de forma equivalente al store PHP en contratos básicos:

* write;
* load;
* has;
* delete;
* build namespace;
* manifest.

---

## 138. FakeWorkerRuntime

```php
final class FakeWorkerRuntime
{
    public function handle(
        RequestInterface $request
    ): ControllerExecutionResult;

    public function reset(): void;

    public function snapshot(): WorkerStateSnapshot;
}
```

---

## 139. Test harness principal

```php
final class ControllerPipelineTestHarness
{
    public function execute(
        ControllerScenario $scenario,
        ExecutionMode $mode
    ): ControllerExecutionResult;
}
```

---

## 140. ExecutionMode

```php
enum ExecutionMode: string
{
    case Dynamic = 'dynamic';
    case CompiledCold = 'compiled_cold';
    case CompiledWarm = 'compiled_warm';
    case FrankenPhpWorker = 'frankenphp_worker';
}
```

---

## 141. ControllerScenario

```php
final readonly class ControllerScenario
{
    public function __construct(
        public RouteDefinition $route,
        public RequestInterface $request,
        public object|string $controller,
        public array $services = [],
        public array $metadata = [],
        public array $expectations = [],
    ) {
    }
}
```

---

## 142. Scenario catalog

Se mantendrá un catálogo reutilizable:

```text
SimpleJsonScenario
HtmlViewScenario
ModelBindingScenario
AuthorizationFailureScenario
CacheShortCircuitScenario
StreamingScenario
CancellationScenario
RecoverableFailureScenario
CompiledScenario
SubrequestScenario
```

---

## 143. Contract test package

VoltStack podrá publicar una suite para paquetes externos.

Un paquete que registre:

* resolvers;
* interceptors;
* strategies;
* emitters;
* exporters;

podrá ejecutar contract tests oficiales.

---

## 144. Extension certification

La suite podrá generar un reporte:

```text
Compatible
Compatible with warnings
Incompatible
```

según contratos del framework.

---

## 145. Backward compatibility tests

Para versiones futuras deberán conservarse fixtures de versiones anteriores de:

* compiled artifacts;
* manifests;
* event payloads;
* metadata schemas;
* configuration.

---

## 146. Schema migration tests

Cuando cambie un schema compilado se deberá verificar:

* rechazo explícito;
* migración soportada;
* rebuild requerido;
* error comprensible.

---

## 147. Event compatibility tests

Eventos versionados deberán probar:

* nombre estable;
* versión;
* payload mínimo;
* campos sensibles ausentes;
* sequence.

---

## 148. Test data privacy

Los fixtures no deberán contener:

* credenciales reales;
* tokens;
* datos personales reales;
* URLs privadas;
* claves.

---

## 149. Documentation tests

Los ejemplos de código de documentación crítica deberán ejecutarse cuando sea viable.

Esto evitará divergencia entre arquitectura, API y comportamiento real.

---

## 150. Static analysis

El módulo deberá cumplir un nivel estricto de análisis estático.

Se verificarán especialmente:

* generics;
* arrays tipados;
* enums exhaustivos;
* `mixed`;
* nullability;
* readonly;
* unreachable states.

---

## 151. Exhaustive enum tests

Los tests deberán fallar cuando se agregue:

* nuevo estado;
* nueva fase;
* nuevo artifact type;
* nueva category;

sin actualizar los datasets correspondientes.

---

## 152. Architectural tests

Podrán verificar reglas como:

```text
Invoker no depende de Transformation
Resolver no depende de Transport
Observability no modifica Response
Compilation artifacts no dependen de Request
Testing no se carga en producción
```

---

## 153. Dependency rule tests

Ejemplo conceptual:

```php
ArchitectureAssert::module('Controllers/Invocation')
    ->mustNotDependOn('Controllers/Transport');
```

---

## 154. Production package isolation

Las clases bajo `Testing` no deberán incluirse en preload ni registrarse en producción salvo solicitud explícita.

---

## 155. Testability requirements

Toda nueva capacidad del subsistema Controllers deberá proporcionar:

* contrato;
* fake o stub razonable;
* assertions cuando aplique;
* unit tests;
* integration test;
* failure test;
* compiled equivalence test si interviene en runtime.

---

## 156. Definition of Done

Una funcionalidad no se considerará completa hasta cumplir:

```text
Implementation
Unit tests
Contract tests
Integration tests
Failure cases
Observability assertions
Compiled equivalence
Documentation
Static analysis
```

---

## 157. Quality gates

Gates mínimos recomendados:

* unit y contract tests exitosos;
* cero errores de análisis estático;
* compiled equivalence exitosa;
* mutation score crítico aceptable;
* sin leaks en Worker tests;
* sin datos sensibles en observabilidad;
* sin regresiones severas de rendimiento.

---

## 158. Reportes de CI

La CI deberá producir:

* test report;
* coverage;
* mutation report;
* benchmark diff;
* Worker leak report;
* compilation equivalence report;
* artifact determinism report.

---

## 159. Estructura de directorios del módulo

```text
src/
└── Quantum/
    └── Controllers/
        └── Testing/
            ├── Contracts/
            │   ├── ControllerTestHarnessInterface.php
            │   ├── WorkerSimulatorInterface.php
            │   ├── FailureInjectorInterface.php
            │   └── ExecutionEquivalenceComparatorInterface.php
            │
            ├── Environment/
            │   ├── ControllerTestEnvironment.php
            │   ├── ControllerTestSandbox.php
            │   ├── TestContainer.php
            │   └── TestRuntimeCapabilities.php
            │
            ├── Builders/
            │   ├── ControllerExecutionBuilder.php
            │   ├── ControllerCompilationRequestBuilder.php
            │   ├── CompiledArtifactBuilder.php
            │   ├── RouteDefinitionBuilder.php
            │   ├── MetadataBagBuilder.php
            │   └── ControllerScenarioBuilder.php
            │
            ├── Assertions/
            │   ├── ControllerExecutionAssert.php
            │   ├── ControllerLifecycleAssert.php
            │   ├── ControllerCompilationAssert.php
            │   ├── CompiledArtifactAssert.php
            │   ├── ControllerObservabilityAssert.php
            │   ├── ControllerTransportAssert.php
            │   ├── ControllerExceptionAssert.php
            │   ├── ControllerResourceAssert.php
            │   └── WorkerIsolationAssert.php
            │
            ├── Fakes/
            │   ├── FakeControllerResolver.php
            │   ├── FakeParameterResolver.php
            │   ├── FakeMetadataProvider.php
            │   ├── FakeInterceptor.php
            │   ├── FakeControllerInvoker.php
            │   ├── FakeTransformationEngine.php
            │   ├── FakeTransportAdapter.php
            │   ├── FakeTransportEmitter.php
            │   ├── FakeExceptionHandler.php
            │   ├── FakeCancellationToken.php
            │   ├── FakeExecutionResource.php
            │   ├── FakeArtifactCompiler.php
            │   ├── FakeArtifactStore.php
            │   ├── FakeEventDispatcher.php
            │   ├── FakeMetricRecorder.php
            │   ├── FakeTraceRecorder.php
            │   ├── FakeProfiler.php
            │   ├── FakeObservabilityExporter.php
            │   └── FakeWorkerRuntime.php
            │
            ├── Spies/
            │   ├── ControllerInvocationSpy.php
            │   ├── InterceptorSpy.php
            │   ├── TransportEmitterSpy.php
            │   ├── CleanupSpy.php
            │   └── ArtifactCompilerSpy.php
            │
            ├── Fixtures/
            │   ├── Controllers/
            │   ├── Routes/
            │   ├── DTOs/
            │   ├── Models/
            │   ├── Interceptors/
            │   ├── Exceptions/
            │   ├── Artifacts/
            │   └── Manifests/
            │
            ├── Scenarios/
            │   ├── ControllerScenario.php
            │   ├── ScenarioCatalog.php
            │   ├── SimpleJsonScenario.php
            │   ├── StreamingScenario.php
            │   ├── CancellationScenario.php
            │   └── RecoverableFailureScenario.php
            │
            ├── Harness/
            │   ├── ControllerPipelineTestHarness.php
            │   ├── ControllerExecutionEquivalenceHarness.php
            │   ├── ControllerCompilationTestHarness.php
            │   ├── WorkerIsolationTestHarness.php
            │   └── StreamingTestHarness.php
            │
            ├── Worker/
            │   ├── WorkerLeakDetector.php
            │   ├── WorkerStateSnapshot.php
            │   ├── WorkerLeakReport.php
            │   └── PersistentWorkerSimulator.php
            │
            ├── Failure/
            │   ├── FailureInjector.php
            │   ├── FailurePoint.php
            │   ├── TransportFailurePoint.php
            │   └── CompilationFailurePoint.php
            │
            ├── Determinism/
            │   ├── FakeClock.php
            │   ├── DeterministicIdGenerator.php
            │   ├── DeterministicRandom.php
            │   └── FingerprintDeterminismVerifier.php
            │
            ├── Equivalence/
            │   ├── ExecutionEquivalenceComparator.php
            │   ├── ExecutionEquivalenceResult.php
            │   └── ResponseSemanticComparator.php
            │
            ├── ContractsSuites/
            │   ├── ControllerResolverContractTest.php
            │   ├── ParameterResolverContractTest.php
            │   ├── ControllerInterceptorContractTest.php
            │   ├── InvocationStrategyContractTest.php
            │   ├── TransformationStrategyContractTest.php
            │   ├── TransportAdapterContractTest.php
            │   ├── ArtifactCompilerContractTest.php
            │   ├── ArtifactStoreContractTest.php
            │   └── ObservabilityExporterContractTest.php
            │
            ├── Property/
            ├── Mutation/
            ├── Benchmarks/
            ├── Reports/
            └── Providers/
                └── ControllerTestingServiceProvider.php
```

---

## 160. Configuración de testing

```php
// config/controller_testing.php

return [
    'deterministic' => true,

    'clock' => 'fake',

    'ids' => 'deterministic',

    'artifacts' => [
        'store' => 'memory',
        'validate_serialization' => true,
    ],

    'equivalence' => [
        'enabled' => true,
        'compare_headers' => true,
        'compare_events' => true,
        'ignore_volatile_values' => true,
    ],

    'workers' => [
        'simulate_persistence' => true,
        'detect_leaks' => true,
        'requests_per_test' => 3,
    ],

    'observability' => [
        'record_events' => true,
        'record_metrics' => true,
        'record_traces' => true,
        'assert_sanitization' => true,
    ],

    'failure_injection' => [
        'enabled' => true,
    ],

    'snapshots' => [
        'normalize_paths' => true,
        'normalize_timestamps' => true,
        'normalize_ids' => true,
    ],
];
```

---

## 161. Testing Service Provider

```php
final class ControllerTestingServiceProvider
    extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(
            ControllerPipelineTestHarness::class
        );

        $this->container->singleton(
            ControllerExecutionEquivalenceHarness::class
        );

        $this->container->singleton(
            WorkerLeakDetector::class
        );
    }
}
```

Este provider solo deberá cargarse en entorno de pruebas.

---

## 162. ADR-001

**La cobertura de líneas no será la única medida de calidad.**

---

## 163. ADR-002

**Todo contrato extensible tendrá una suite de contract tests.**

---

## 164. ADR-003

**El modo dinámico y el compilado deberán demostrar equivalencia semántica.**

---

## 165. ADR-004

**Los Workers persistentes tendrán una suite dedicada de aislamiento.**

---

## 166. ADR-005

**El cleanup se verificará en todos los caminos terminales.**

---

## 167. ADR-006

**El tiempo, IDs y aleatoriedad serán controlables en tests.**

---

## 168. ADR-007

**Los fakes deberán implementar los contratos reales.**

---

## 169. ADR-008

**Se evitará el mocking profundo de pipelines completos.**

---

## 170. ADR-009

**La máquina de estados deberá probarse mediante cobertura de estados y transiciones.**

---

## 171. ADR-010

**Los errores podrán inyectarse en puntos formales del pipeline.**

---

## 172. ADR-011

**Los artefactos compilados deberán probar determinismo.**

---

## 173. ADR-012

**Los tests de OPcache, SAPI y locking se ejecutarán en procesos aislados.**

---

## 174. ADR-013

**Los eventos, métricas y traces se probarán mediante recorders en memoria.**

---

## 175. ADR-014

**Los datos sensibles deberán tener pruebas negativas explícitas.**

---

## 176. ADR-015

**Los benchmarks no formarán parte de la suite funcional rápida.**

---

## 177. ADR-016

**Todo bug corregido deberá añadir una prueba de regresión.**

---

## 178. ADR-017

**Los snapshots se usarán como apoyo, no como única validación.**

---

## 179. ADR-018

**Las extensiones externas podrán reutilizar contract tests oficiales.**

---

## 180. ADR-019

**Las clases de Testing no se cargarán en producción por defecto.**

---

## 181. ADR-020

**Una nueva capacidad no estará completa sin pruebas de fallo y Worker safety cuando correspondan.**

---

## 182. Implementación V1

La V1 deberá incluir:

* ControllerTestCase;
* ControllerTestEnvironment;
* fakes principales;
* spies;
* builders;
* fixtures;
* assertions;
* pipeline harness;
* equivalence harness;
* worker simulator;
* leak detector;
* failure injection;
* contract suites;
* lifecycle tests;
* compilation tests;
* observability tests;
* Worker tests;
* benchmarks básicos;
* CI groups.

---

## 183. Fuera de V1

Se aplazarán:

* model checking formal;
* distributed chaos testing;
* automatic test generation;
* continuous fuzzing;
* production traffic replay;
* eBPF-based profiling tests;
* formal verification completa.

---

## 184. Roadmap V2

Podrá incorporar:

* property-based testing extendido;
* fuzzing continuo;
* mutation testing automatizado;
* dashboards de calidad;
* artifact compatibility matrix;
* load testing oficial;
* paquetes de certificación para extensiones.

---

## 185. Roadmap V3

Podrá incorporar:

* model checking de state machine;
* generación automática de escenarios;
* replay sanitizado;
* differential testing entre versiones;
* análisis predictivo de regresiones;
* fault injection distribuido.

---

## 186. Flujo de prueba recomendado

```text
Build scenario
    │
    ▼
Execute dynamic
    │
    ▼
Compile artifacts
    │
    ▼
Execute compiled cold
    │
    ▼
Execute compiled warm
    │
    ▼
Execute in persistent Worker
    │
    ▼
Compare semantics
    │
    ▼
Assert lifecycle
    │
    ▼
Assert observability
    │
    ▼
Assert cleanup and isolation
```

---

## 187. Resultado arquitectónico

Esta arquitectura permitirá verificar preguntas críticas como:

* ¿El controlador correcto fue resuelto?
* ¿Los parámetros fueron obtenidos de la fuente correcta?
* ¿Los interceptores se ejecutaron en orden?
* ¿El controlador se invocó exactamente una vez?
* ¿El resultado dinámico coincide con el compilado?
* ¿Una excepción fue clasificada correctamente?
* ¿El short-circuit evitó la invocación?
* ¿La cancelación liberó todos los recursos?
* ¿El Worker quedó limpio?
* ¿Los eventos y spans tienen correlación?
* ¿Los artefactos son deterministas?
* ¿Un cambio invalida únicamente lo necesario?
* ¿El pipeline mantiene su rendimiento?

---

## 188. Conclusión

El **Controller Testing Architecture** convierte la calidad del subsistema Controllers en una responsabilidad estructural y verificable.

La estrategia no se limita a comprobar resultados finales. También valida:

* contratos;
* estados;
* transiciones;
* recursos;
* eventos;
* compilación;
* invalidación;
* Workers;
* seguridad;
* rendimiento.

Esto permite que VoltStack mantenga un pipeline extensible durante el desarrollo y, al mismo tiempo, seguro, determinista y optimizado en producción.

---
