# Framework de compilación del subsistema Controllers de VoltStack


**Versión:** 1.0
**Estado:** Draft arquitectónico
**Módulo:** `VoltStack\Quantum\Controllers\Compilation`
**Ámbito:** Compilación, almacenamiento, carga, validación e invalidación de todos los planes del subsistema Controllers
**Integraciones principales:** Resolver, Parameters, Metadata, Interceptors, Invoker, Transformation, Transport, Exceptions, Lifecycle, Observability, OPcache y FrankenPHP

---

## 1. Introducción

El **Controller Compilation Framework** es la infraestructura central responsable de compilar, almacenar, validar y cargar todos los planes necesarios para ejecutar controladores sin reconstruir dinámicamente su arquitectura durante cada petición.

Los documentos anteriores definieron compiladores especializados para:

* resolución de controladores;
* resolución de parámetros;
* metadata;
* interceptores;
* invocación;
* transformación de resultados;
* transporte;
* manejo de excepciones;
* lifecycle;
* observabilidad.

Sin una infraestructura común, cada subsistema tendría que implementar por separado:

* identificadores de artefactos;
* fingerprints;
* firmas;
* versionado;
* almacenamiento;
* carga;
* validación;
* invalidación;
* warmup;
* preload;
* debugging;
* métricas;
* compatibilidad con Workers.

Este documento unifica esas capacidades.

---

## 2. Objetivo principal

Convertir la definición dinámica de una ejecución de controlador en un conjunto reproducible de artefactos PHP optimizados.

```text
Routes
Controllers
Metadata
Attributes
Configuration
Registries
Policies
Runtime capabilities
        │
        ▼
ControllerCompilationManager
        │
        ▼
Specialized Compilers
        │
        ▼
Compiled Artifacts
        │
        ▼
Artifact Store
        │
        ▼
OPcache / Worker Memory
        │
        ▼
Runtime Execution
```

---

## 3. Resultado esperado

Para cada controlador o ruta compilable, VoltStack podrá generar un grafo de artefactos como:

```text
ControllerExecutionBundle
│
├── ControllerResolutionPlan
├── MetadataPlan
├── ParameterResolutionPlan
├── InterceptorPlan
├── InvocationPlan
├── TransformationPlan
├── TransportPlan
├── ExceptionHandlingPlan
├── LifecyclePlan
└── ObservabilityPlan
```

La ejecución podrá cargar estos planes directamente sin:

* reflexión repetida;
* escaneo de atributos;
* resolución dinámica de registries;
* ordenamiento de interceptores;
* búsqueda de strategies;
* reconstrucción de políticas;
* análisis repetido de tipos.

---

## 4. Principios arquitectónicos

El framework seguirá estos principios:

* La compilación será una optimización, no una semántica distinta.
* El modo dinámico y el compilado deberán producir resultados equivalentes.
* Cada artefacto tendrá identidad, versión, firma y dependencias.
* Los artefactos deberán ser inmutables.
* Los artefactos no contendrán estado de petición.
* La invalidación se basará en dependencias y fingerprints.
* Los despliegues deberán cambiar artefactos de forma atómica.
* La carga deberá ser compatible con OPcache.
* Los Workers nunca conservarán artefactos obsoletos indefinidamente.
* Los compiladores especializados compartirán infraestructura común.
* La compilación deberá ser determinista.
* Los fallos de compilación deberán ser diagnosticables.
* En modo estricto no se permitirá fallback dinámico silencioso.

---

## 5. No responsabilidades

El framework no deberá:

* ejecutar controladores;
* resolver requests concretos;
* almacenar objetos request-scoped;
* cachear respuestas de negocio;
* reemplazar OPcache;
* decidir automáticamente lógica funcional;
* ocultar incompatibilidades;
* compilar código arbitrario no confiable;
* mutar registries después de congelarlos.

---

## 6. Diferencia entre compilación y caché

La compilación transforma definiciones de alto nivel en planes ejecutables.

La caché evita volver a producir o cargar esos planes innecesariamente.

```text
Compilation
    Definitions → Compiled Artifact

Cache
    Compiled Artifact → Reuse
```

Ambos conceptos estarán integrados, pero serán independientes.

---

## 7. Arquitectura general

```text
CompilationRequest
        │
        ▼
CompilationContextFactory
        │
        ▼
ControllerCompilationManager
        │
        ▼
CompilerRegistry
        │
        ▼
Dependency Graph Builder
        │
        ▼
Specialized Artifact Compilers
        │
        ▼
Artifact Validator
        │
        ▼
Artifact Linker
        │
        ▼
Artifact Bundle
        │
        ▼
Artifact Writer
        │
        ▼
Artifact Manifest
```

---

## 8. Componentes principales

```text
ControllerCompilationManager
CompilationRequest
CompilationContext
CompilerRegistry
CompiledArtifactInterface
CompiledArtifactId
CompiledArtifactType
ArtifactCompilerInterface
ArtifactDependency
ArtifactDependencyGraph
ArtifactFingerprint
ArtifactSignature
ArtifactValidator
ArtifactLinker
ArtifactBundle
ArtifactStore
ArtifactLoader
ArtifactWriter
ArtifactManifest
ArtifactInvalidator
ArtifactWarmupManager
ArtifactPreloader
DeploymentArtifactManager
CompilationDiagnostics
```

---

## 9. ControllerCompilationManager

Punto de entrada principal.

```php
interface ControllerCompilationManagerInterface
{
    public function compile(
        ControllerCompilationRequest $request
    ): ControllerCompilationResult;

    public function compileAll(
        CompilationScope $scope
    ): CompilationReport;
}
```

Implementación oficial:

```php
final class ControllerCompilationManager
    implements ControllerCompilationManagerInterface
{
}
```

---

## 10. Responsabilidades del Manager

El Manager deberá:

1. crear el contexto de compilación;
2. resolver las unidades compilables;
3. construir el grafo de dependencias;
4. ordenar compiladores;
5. ejecutar compiladores especializados;
6. validar artefactos;
7. enlazar referencias;
8. construir bundles;
9. escribir artefactos;
10. generar manifests;
11. invalidar artefactos anteriores;
12. registrar métricas y diagnósticos.

---

## 11. ControllerCompilationRequest

```php
final readonly class ControllerCompilationRequest
{
    public function __construct(
        public ControllerCompilationTarget $target,
        public CompilationMode $mode,
        public CompilationOptions $options,
        public RuntimeCapabilities $runtime,
    ) {
    }
}
```

---

## 12. ControllerCompilationTarget

Podrá representar:

```php
enum ControllerCompilationTargetType: string
{
    case Route = 'route';
    case Controller = 'controller';
    case Method = 'method';
    case Module = 'module';
    case Application = 'application';
    case ChangedOnly = 'changed_only';
}
```

---

## 13. CompilationMode

```php
enum CompilationMode: string
{
    case Development = 'development';
    case Production = 'production';
    case Incremental = 'incremental';
    case ValidationOnly = 'validation_only';
    case Warmup = 'warmup';
    case Preload = 'preload';
}
```

---

## 14. CompilationOptions

```php
final readonly class CompilationOptions
{
    public function __construct(
        public bool $strict,
        public bool $optimize,
        public bool $debugSymbols,
        public bool $sourceMaps,
        public bool $incremental,
        public bool $pruneStaleArtifacts,
        public bool $generatePreload,
        public bool $atomicWrite,
    ) {
    }
}
```

---

## 15. CompilationContext

```php
final readonly class CompilationContext
{
    public function __construct(
        public string $applicationId,
        public string $environment,
        public string $frameworkVersion,
        public string $buildId,
        public RuntimeCapabilities $runtime,
        public FrozenRegistrySet $registries,
        public CompilationOptions $options,
        public CompilationWorkspace $workspace,
    ) {
    }
}
```

---

## 16. FrozenRegistrySet

La compilación solo podrá ejecutarse sobre registries estables.

```php
final readonly class FrozenRegistrySet
{
    public function __construct(
        public ControllerResolverRegistry $controllers,
        public ParameterResolverRegistry $parameters,
        public MetadataProviderRegistry $metadata,
        public InterceptorRegistry $interceptors,
        public InvocationStrategyRegistry $invocation,
        public TransformationStrategyRegistry $transformation,
        public TransportRegistry $transport,
        public ExceptionRegistry $exceptions,
        public LifecycleRegistry $lifecycle,
        public ObservabilityRegistry $observability,
    ) {
    }
}
```

---

## 17. Requisito de freeze

Antes de compilar:

```text
Register services
    │
    ▼
Register strategies
    │
    ▼
Register metadata providers
    │
    ▼
Freeze registries
    │
    ▼
Compile
```

Modificar un registry congelado deberá producir un error.

---

## 18. CompiledArtifactInterface

Contrato común de todos los artefactos.

```php
interface CompiledArtifactInterface
{
    public function id(): CompiledArtifactId;

    public function type(): CompiledArtifactType;

    public function version(): string;

    public function fingerprint(): ArtifactFingerprint;

    public function signature(): ArtifactSignature;

    /**
     * @return list<ArtifactDependency>
     */
    public function dependencies(): array;

    public function metadata(): array;
}
```

---

## 19. CompiledArtifactId

```php
final readonly class CompiledArtifactId
{
    public function __construct(
        public string $namespace,
        public string $name,
        public string $variant,
    ) {
    }

    public function value(): string
    {
        return "{$this->namespace}:{$this->name}:{$this->variant}";
    }
}
```

Ejemplo:

```text
controllers:App\Http\OrderController::show:invocation
```

---

## 20. CompiledArtifactType

```php
enum CompiledArtifactType: string
{
    case ControllerResolution = 'controller_resolution';
    case Metadata = 'metadata';
    case ParameterResolution = 'parameter_resolution';
    case Interceptors = 'interceptors';
    case Invocation = 'invocation';
    case Transformation = 'transformation';
    case Transport = 'transport';
    case ExceptionHandling = 'exception_handling';
    case Lifecycle = 'lifecycle';
    case Observability = 'observability';
    case ExecutionBundle = 'execution_bundle';
    case Manifest = 'manifest';
    case Preload = 'preload';
}
```

---

## 21. Artefactos oficiales

```text
CompiledControllerResolutionPlan
CompiledControllerMetadataPlan
CompiledParameterResolutionPlan
CompiledInterceptorPlan
CompiledControllerInvocationPlan
CompiledTransformationPlan
CompiledTransportPlan
CompiledExceptionHandlingPlan
CompiledLifecyclePlan
CompiledControllerObservabilityPlan
CompiledControllerExecutionBundle
```

---

## 22. ArtifactCompilerInterface

```php
interface ArtifactCompilerInterface
{
    public function type(): CompiledArtifactType;

    public function supports(
        CompilationUnit $unit,
        CompilationContext $context
    ): bool;

    public function dependencies(
        CompilationUnit $unit,
        CompilationContext $context
    ): array;

    public function compile(
        CompilationUnit $unit,
        CompilationContext $context,
        CompiledArtifactCollection $dependencies
    ): CompiledArtifactInterface;
}
```

---

## 23. Specialized compilers

```text
ControllerResolutionCompiler
ControllerMetadataCompiler
ParameterResolutionCompiler
ControllerInterceptorCompiler
ControllerInvocationCompiler
ResultTransformationCompiler
ResponseTransportCompiler
ExceptionHandlingCompiler
ControllerLifecycleCompiler
ControllerObservabilityCompiler
ControllerExecutionBundleCompiler
```

---

## 24. Compiler Registry

```php
interface ControllerCompilerRegistryInterface
{
    public function register(
        ArtifactCompilerInterface $compiler
    ): void;

    public function compilerFor(
        CompiledArtifactType $type
    ): ArtifactCompilerInterface;

    public function all(): array;

    public function freeze(): void;
}
```

---

## 25. Orden de compilación

Orden base:

```text
Controller Resolution
        │
        ├── Metadata
        │       │
        │       ├── Parameters
        │       ├── Interceptors
        │       ├── Invocation
        │       ├── Transformation
        │       ├── Transport
        │       ├── Exceptions
        │       ├── Lifecycle
        │       └── Observability
        │
        ▼
Execution Bundle
```

El orden real será determinado por el grafo de dependencias.

---

## 26. CompilationUnit

Representa una unidad compilable.

```php
final readonly class CompilationUnit
{
    public function __construct(
        public CompilationUnitId $id,
        public RouteDefinition $route,
        public ControllerDescriptor $controller,
        public MetadataBag $metadata,
        public SourceSet $sources,
    ) {
    }
}
```

---

## 27. CompilationUnitId

Normalmente se basará en:

* route name;
* controller class;
* method;
* route variant;
* transport;
* runtime profile.

---

## 28. Variantes de compilación

Una misma acción podrá producir artefactos diferentes según:

* HTTP o CLI;
* SPA o navegación tradicional;
* modo streaming;
* tenant mode;
* runtime FrankenPHP o SAPI;
* feature set;
* configuración de producción.

---

## 29. ArtifactDependency

```php
final readonly class ArtifactDependency
{
    public function __construct(
        public ArtifactDependencyType $type,
        public string $identifier,
        public string $fingerprint,
        public bool $required = true,
    ) {
    }
}
```

---

## 30. ArtifactDependencyType

```php
enum ArtifactDependencyType: string
{
    case SourceFile = 'source_file';
    case Configuration = 'configuration';
    case Registry = 'registry';
    case Route = 'route';
    case Controller = 'controller';
    case Attribute = 'attribute';
    case Metadata = 'metadata';
    case ServiceDefinition = 'service_definition';
    case RuntimeCapability = 'runtime_capability';
    case CompiledArtifact = 'compiled_artifact';
    case FrameworkVersion = 'framework_version';
    case Environment = 'environment';
    case Custom = 'custom';
}
```

---

## 31. Grafo de dependencias

```php
interface ArtifactDependencyGraphInterface
{
    public function addNode(
        CompiledArtifactId $artifact
    ): void;

    public function addDependency(
        CompiledArtifactId $artifact,
        ArtifactDependency $dependency
    ): void;

    public function topologicalOrder(): array;

    public function dependentsOf(
        string $dependencyIdentifier
    ): array;
}
```

---

## 32. Ciclos

Un ciclo entre artefactos deberá considerarse error de compilación.

Ejemplo inválido:

```text
LifecyclePlan
    depends on
ObservabilityPlan
    depends on
LifecyclePlan
```

El compiler deberá mostrar la cadena completa del ciclo.

---

## 33. ArtifactFingerprint

Representa el contenido lógico y las dependencias de un artefacto.

```php
final readonly class ArtifactFingerprint
{
    public function __construct(
        public string $algorithm,
        public string $value,
    ) {
    }
}
```

---

## 34. Generación del fingerprint

Podrá considerar:

* fuente normalizada;
* metadata;
* configuración relevante;
* versiones de registries;
* dependencias;
* versión del framework;
* capacidades del runtime;
* versión del compilador.

---

## 35. Determinismo

Dos compilaciones con los mismos inputs deberán producir:

* mismo fingerprint;
* misma firma;
* mismo contenido funcional;
* mismo orden de dependencias.

No deberán incluirse directamente:

* timestamps;
* paths temporales;
* IDs aleatorios;
* orden no estable de arrays;
* estado del proceso.

---

## 36. ArtifactSignature

La firma valida integridad y compatibilidad.

```php
final readonly class ArtifactSignature
{
    public function __construct(
        public string $schemaVersion,
        public string $compilerVersion,
        public string $runtimeProfile,
        public string $hash,
    ) {
    }
}
```

---

## 37. Diferencia entre fingerprint y signature

```text
Fingerprint
    ¿Cambió la definición lógica?

Signature
    ¿Es válido y compatible el artefacto almacenado?
```

---

## 38. Artifact metadata

Podrá contener:

```text
artifact_id
artifact_type
schema_version
compiler_version
framework_version
build_id
runtime_profile
source_fingerprint
dependency_fingerprint
compiled_at
debug_symbols
```

`compiled_at` será informativo y no formará parte del fingerprint funcional.

---

## 39. CompiledControllerResolutionPlan

Contendrá:

* controller class;
* method;
* callable type;
* service ID;
* instantiation strategy;
* controller scope;
* aliases resueltos;
* invokable flags;
* validation flags.

---

## 40. CompiledControllerMetadataPlan

Contendrá:

* metadata final fusionada;
* origen de metadata;
* schema version;
* keys normalizadas;
* políticas resueltas;
* referencias inmutables.

---

## 41. CompiledParameterResolutionPlan

Contendrá una lista ordenada de parámetros.

```php
final readonly class CompiledParameterDefinition
{
    public function __construct(
        public string $name,
        public string $resolver,
        public ?string $type,
        public bool $nullable,
        public bool $variadic,
        public bool $hasDefault,
        public mixed $default,
        public array $options,
    ) {
    }
}
```

---

## 42. CompiledInterceptorPlan

Contendrá:

* interceptores ordenados;
* prioridades;
* scopes;
* before/after behavior;
* short-circuit capabilities;
* retry capabilities;
* lifecycle hooks;
* container references.

---

## 43. CompiledControllerInvocationPlan

Contendrá:

* invocation strategy;
* callable reference;
* argument assembly;
* method visibility validada;
* static or instance mode;
* return hints;
* invocation guards.

---

## 44. CompiledTransformationPlan

Contendrá:

* result classifiers;
* strategies;
* content negotiation rules;
* builders;
* decorators;
* fallback;
* runtime integration;
* SPA behavior.

---

## 45. CompiledTransportPlan

Contendrá:

* transport;
* adapter;
* emitter;
* policies;
* headers pipeline;
* compression;
* cache rules;
* security rules;
* streaming configuration.

---

## 46. CompiledExceptionHandlingPlan

Contendrá:

* classifier;
* mapper;
* renderer;
* reporters;
* sanitizers;
* recovery policy;
* worker disposition policy;
* emergency fallback.

---

## 47. CompiledLifecyclePlan

Contendrá:

* fases;
* handlers;
* transitions;
* guards;
* timeout policy;
* cancellation policy;
* cleanup handlers;
* retry policy;
* resource policies.

---

## 48. CompiledControllerObservabilityPlan

Contendrá:

* eventos habilitados;
* métricas;
* labels;
* spans;
* sampling;
* exporters;
* sanitizers;
* profiling;
* thresholds.

---

## 49. CompiledControllerExecutionBundle

Artefacto raíz consumido por runtime.

```php
final readonly class CompiledControllerExecutionBundle
    implements CompiledArtifactInterface
{
    public function __construct(
        public CompiledArtifactId $artifactId,
        public CompiledControllerResolutionPlan $controller,
        public CompiledControllerMetadataPlan $metadata,
        public CompiledParameterResolutionPlan $parameters,
        public CompiledInterceptorPlan $interceptors,
        public CompiledControllerInvocationPlan $invocation,
        public CompiledTransformationPlan $transformation,
        public CompiledTransportPlan $transport,
        public CompiledExceptionHandlingPlan $exceptions,
        public CompiledLifecyclePlan $lifecycle,
        public CompiledControllerObservabilityPlan $observability,
        public ArtifactFingerprint $artifactFingerprint,
        public ArtifactSignature $artifactSignature,
        public array $artifactDependencies,
    ) {
    }
}
```

---

## 50. Bundle linking

El bundle no deberá duplicar necesariamente todo el contenido.

Podrá contener referencias a artefactos compartidos:

```text
ExecutionBundle
│
├── artifact ref: metadata/orders-show
├── artifact ref: parameters/orders-show
├── artifact ref: transport/http-default
└── artifact ref: observability/default
```

---

## 51. Artifact Linker

```php
interface ArtifactLinkerInterface
{
    public function link(
        CompiledArtifactCollection $artifacts,
        CompilationContext $context
    ): LinkedArtifactCollection;
}
```

---

## 52. Compartición de artefactos

Podrán compartirse:

* transport plans globales;
* observability plans;
* exception policies;
* metadata común;
* interceptor groups;
* parameter subplans;
* security policies.

Esto reduce duplicación en disco y memoria.

---

## 53. ArtifactValidator

```php
interface CompiledArtifactValidatorInterface
{
    public function validate(
        CompiledArtifactInterface $artifact,
        CompilationContext $context
    ): ArtifactValidationResult;
}
```

---

## 54. Validaciones

El validator deberá comprobar:

* schema compatible;
* firma válida;
* dependencias presentes;
* referencias resolubles;
* clases existentes;
* servicios registrados;
* registries compatibles;
* runtime compatible;
* ausencia de estado mutable peligroso;
* serialización válida;
* invariantes por tipo.

---

## 55. Strict mode

En `strict = true`:

* cualquier artefacto ausente fallará;
* cualquier firma incompatible fallará;
* no habrá fallback dinámico;
* cualquier dependency mismatch fallará;
* los stale artifacts no serán utilizados.

Este modo será recomendado en producción.

---

## 56. Fallback dinámico

En modo no estricto:

```text
Compiled artifact missing
        │
        ▼
Dynamic plan resolution
        │
        ▼
Optional local cache
        │
        ▼
Diagnostic warning
```

El fallback deberá ser observable.

---

## 57. ArtifactStore

```php
interface CompiledArtifactStoreInterface
{
    public function has(
        CompiledArtifactId $id
    ): bool;

    public function load(
        CompiledArtifactId $id
    ): CompiledArtifactInterface;

    public function write(
        CompiledArtifactInterface $artifact
    ): void;

    public function delete(
        CompiledArtifactId $id
    ): void;
}
```

---

## 58. Implementaciones del Store

```text
PhpFileArtifactStore
InMemoryArtifactStore
CompositeArtifactStore
ReadOnlyDeploymentArtifactStore
TestingArtifactStore
```

La V1 utilizará principalmente archivos PHP.

---

## 59. Formato PHP

Un artefacto podrá almacenarse como:

```php
<?php

declare(strict_types=1);

return new CompiledControllerInvocationPlan(
    // argumentos escalares y arrays inmutables
);
```

Ventajas:

* compatible con OPcache;
* carga mediante `require`;
* sin unserialize;
* inspeccionable;
* fácil de preload;
* rápido en Workers.

---

## 60. Restricciones del formato

Los artefactos no deberán contener:

* closures no serializables;
* resources;
* request objects;
* container instances;
* conexiones;
* generators;
* mutable global state.

Deberán utilizar:

* class strings;
* service IDs;
* enums;
* escalares;
* arrays;
* value objects compilables.

---

## 61. ArtifactSerializer

Aunque PHP sea el formato principal, existirá una abstracción.

```php
interface CompiledArtifactSerializerInterface
{
    public function serialize(
        CompiledArtifactInterface $artifact
    ): string;

    public function extension(): string;
}
```

Implementación oficial:

```text
PhpArtifactSerializer
```

---

## 62. ArtifactWriter

```php
interface CompiledArtifactWriterInterface
{
    public function write(
        CompiledArtifactInterface $artifact,
        CompilationWorkspace $workspace
    ): WrittenArtifact;
}
```

---

## 63. Escritura atómica

El proceso recomendado:

```text
Generate temporary file
        │
        ▼
Validate generated PHP
        │
        ▼
Load test
        │
        ▼
fsync when supported
        │
        ▼
Atomic rename
```

Nunca se sobrescribirá directamente un artefacto activo.

---

## 64. Artifact paths

Estructura sugerida:

```text
storage/framework/controllers/
├── builds/
│   ├── build_01J.../
│   │   ├── artifacts/
│   │   │   ├── controller-resolution/
│   │   │   ├── metadata/
│   │   │   ├── parameters/
│   │   │   ├── interceptors/
│   │   │   ├── invocation/
│   │   │   ├── transformation/
│   │   │   ├── transport/
│   │   │   ├── exceptions/
│   │   │   ├── lifecycle/
│   │   │   ├── observability/
│   │   │   └── bundles/
│   │   ├── manifest.php
│   │   └── preload.php
│   └── build_02K.../
│
└── current
```

`current` podrá ser symlink, pointer file o resolución gestionada por plataforma.

---

## 65. Build ID

Cada compilación desplegable tendrá un ID.

```php
final readonly class CompilationBuildId
{
    public function __construct(
        public string $value
    ) {
    }
}
```

---

## 66. Artifact Manifest

El manifest será el índice de un build.

```php
final readonly class ControllerArtifactManifest
{
    public function __construct(
        public string $buildId,
        public string $frameworkVersion,
        public string $schemaVersion,
        public string $runtimeProfile,
        public array $artifacts,
        public array $routeMap,
        public array $dependencies,
        public string $signature,
    ) {
    }
}
```

---

## 67. Route map

Permitirá resolver rápidamente:

```text
route signature → execution bundle artifact
```

Ejemplo:

```php
return [
    'orders.show|GET|http' =>
        'controllers:App\Http\OrderController::show:http',
];
```

---

## 68. ArtifactLoader

```php
interface CompiledArtifactLoaderInterface
{
    public function load(
        CompiledArtifactId $id
    ): CompiledArtifactInterface;

    public function loadBundleFor(
        RouteMatch $route,
        RuntimeContext $runtime
    ): CompiledControllerExecutionBundle;
}
```

---

## 69. Runtime load path

```text
RouteMatch
    │
    ▼
Route Signature
    │
    ▼
Manifest Lookup
    │
    ▼
Worker Cache
    │
    ├── Hit → Bundle
    │
    └── Miss
            │
            ▼
        PHP Artifact Store
            │
            ▼
        Signature Validation
            │
            ▼
        Worker Cache
```

---

## 70. Cache hierarchy

El framework utilizará:

```text
L0 Local references
L1 Execution cache
L2 Request cache
L3 Worker cache
L4 Artifact store
L5 OPcache
```

---

## 71. L0 Local references

Referencias locales dentro del pipeline actual.

Ejemplo:

```php
$execution->compiledBundle
```

Evita búsquedas repetidas dentro de una ejecución.

---

## 72. L1 Execution cache

Almacena resultados derivados del bundle durante una ejecución.

Ejemplos:

* strategy seleccionada;
* mapper seleccionado;
* transport adapter;
* phase handler lookup.

Se destruye al terminar.

---

## 73. L2 Request cache

Puede compartirse con subrequests controlados.

No deberá sobrevivir a la petición.

---

## 74. L3 Worker cache

Almacena artefactos inmutables cargados.

```php
interface WorkerArtifactCacheInterface
{
    public function get(
        CompiledArtifactId $id
    ): ?CompiledArtifactInterface;

    public function put(
        CompiledArtifactInterface $artifact
    ): void;

    public function clear(): void;
}
```

---

## 75. L4 Artifact store

Archivos PHP persistentes generados por el compiler.

---

## 76. L5 OPcache

OPcache almacena el bytecode de los archivos PHP de artefactos.

El framework no controlará internamente su implementación, pero generará artefactos compatibles.

---

## 77. Cache keys

Una key deberá incluir:

* artifact ID;
* build ID;
* schema version;
* runtime profile;
* variant.

Nunca se basará únicamente en nombre de clase.

---

## 78. Cache invalidation

La invalidación podrá ocurrir por:

* cambio de archivo;
* cambio de ruta;
* cambio de configuración;
* cambio de metadata;
* cambio de registry;
* cambio de service definition;
* cambio de runtime profile;
* cambio de framework;
* cambio de compiler schema.

---

## 79. ArtifactInvalidator

```php
interface CompiledArtifactInvalidatorInterface
{
    public function affectedArtifacts(
        DependencyChangeSet $changes,
        ArtifactDependencyGraphInterface $graph
    ): array;

    public function invalidate(
        array $artifactIds
    ): InvalidationResult;
}
```

---

## 80. Invalidación transitiva

Si cambia un Parameter Resolver:

```text
Parameter Resolver Registry
        │
        ▼
Parameter Plans
        │
        ▼
Lifecycle Plans
        │
        ▼
Execution Bundles
```

Todos los dependientes deberán invalidarse.

---

## 81. DependencyChangeSet

```php
final readonly class DependencyChangeSet
{
    public function __construct(
        public array $added,
        public array $modified,
        public array $removed,
    ) {
    }
}
```

---

## 82. Incremental compilation

```text
Read previous manifest
        │
        ▼
Scan dependency fingerprints
        │
        ▼
Detect changes
        │
        ▼
Resolve affected artifacts
        │
        ▼
Compile only affected graph
        │
        ▼
Reuse unchanged artifacts
```

---

## 83. Reutilización entre builds

Los artefactos inmutables sin cambios podrán:

* copiarse;
* enlazarse;
* referenciarse;
* conservarse mediante content-addressed storage.

---

## 84. Content-addressed storage futuro

Un artefacto podrá almacenarse por fingerprint:

```text
artifacts/sha256/ab/cd/abcdef...
```

Esto facilitaría deduplicación entre builds.

No será obligatorio en V1.

---

## 85. Warmup

El warmup carga o compila artefactos antes del tráfico.

```php
interface ControllerCompilationWarmupManagerInterface
{
    public function warm(
        ControllerArtifactManifest $manifest,
        WarmupPolicy $policy
    ): WarmupReport;
}
```

---

## 86. WarmupPolicy

```php
final readonly class WarmupPolicy
{
    public function __construct(
        public bool $validateAll,
        public bool $loadBundles,
        public bool $touchOpcache,
        public bool $prioritizeHotRoutes,
        public int $maxArtifacts,
    ) {
    }
}
```

---

## 87. Tipos de warmup

```text
Validation warmup
Artifact load warmup
OPcache warmup
Hot-route warmup
Worker-local warmup
```

---

## 88. Hot routes

Podrán definirse mediante:

* configuración;
* métricas históricas;
* lista de despliegue;
* rutas críticas;
* preload policy.

---

## 89. Preload

VoltStack podrá generar un archivo compatible con `opcache.preload`.

```php
<?php

require __DIR__.'/artifacts/transport/http-default.php';
require __DIR__.'/artifacts/exceptions/default.php';
require __DIR__.'/artifacts/observability/default.php';
```

---

## 90. Preload policy

No todos los bundles deberán preloaded.

Se priorizarán:

* clases base;
* value objects;
* artifacts globales;
* planes compartidos;
* rutas críticas;
* registries compilados.

---

## 91. Riesgo de preload excesivo

Preload indiscriminado puede:

* aumentar memoria;
* cargar rutas poco usadas;
* dificultar despliegues;
* retener artefactos obsoletos hasta restart.

Por ello deberá existir presupuesto de memoria.

---

## 92. PreloadBudget

```php
final readonly class PreloadBudget
{
    public function __construct(
        public int $maxFiles,
        public int $maxEstimatedBytes,
    ) {
    }
}
```

---

## 93. FrankenPHP integration

En FrankenPHP:

```text
Worker boots
    │
    ▼
Load active manifest
    │
    ▼
Load shared artifacts
    │
    ▼
Serve multiple requests
    │
    ▼
Detect build change
    │
    ├── Keep current build until safe restart
    │
    └── Reload according to deployment policy
```

---

## 94. Artifact immutability in Workers

Un Worker no deberá mezclar artefactos de diferentes builds dentro de una misma ejecución.

Cada ejecución tendrá un:

```text
build_id
```

estable desde inicio hasta cleanup.

---

## 95. Build pinning

```php
final readonly class ExecutionBuildReference
{
    public function __construct(
        public string $buildId,
        public ControllerArtifactManifest $manifest,
    ) {
    }
}
```

---

## 96. Despliegues atómicos

Proceso:

```text
Compile new build
    │
    ▼
Validate all artifacts
    │
    ▼
Run smoke tests
    │
    ▼
Generate manifest
    │
    ▼
Warmup
    │
    ▼
Switch current pointer atomically
    │
    ▼
Restart or recycle Workers
```

---

## 97. DeploymentArtifactManager

```php
interface ControllerArtifactDeploymentManagerInterface
{
    public function activate(
        string $buildId
    ): ActivationResult;

    public function rollback(
        string $buildId
    ): ActivationResult;

    public function currentBuild(): string;
}
```

---

## 98. Rollback

Un build anterior no deberá eliminarse inmediatamente.

Se podrá conservar:

```text
current build
previous build
last known good build
```

---

## 99. Garbage collection

```php
interface ArtifactGarbageCollectorInterface
{
    public function collect(
        ArtifactRetentionPolicy $policy
    ): ArtifactGarbageCollectionReport;
}
```

---

## 100. Retention policy

Podrá conservar:

* build activo;
* N builds anteriores;
* builds fijados;
* último build válido;
* builds recientes por tiempo.

---

## 101. Desarrollo

En desarrollo se favorecerá:

* compilación incremental;
* invalidación rápida;
* debug symbols;
* mensajes detallados;
* fallback dinámico;
* verificación frecuente de fingerprints.

---

## 102. Producción

En producción se favorecerá:

* build previo al despliegue;
* modo estricto;
* artefactos de solo lectura;
* OPcache;
* warmup;
* manifest inmutable;
* Workers fijados a un build;
* sin compilación durante requests.

---

## 103. Runtime compilation

La compilación durante una petición estará deshabilitada por defecto en producción.

Razones:

* latencia impredecible;
* condiciones de carrera;
* permisos de escritura;
* artefactos parciales;
* inconsistencia entre Workers.

---

## 104. Compilation lock

Para procesos de desarrollo o CLI:

```php
interface CompilationLockInterface
{
    public function acquire(
        string $scope
    ): CompilationLockHandle;
}
```

Evitará compilaciones concurrentes sobre el mismo workspace.

---

## 105. Workspace

Cada compilación utilizará un directorio temporal aislado.

```php
final readonly class CompilationWorkspace
{
    public function __construct(
        public string $path,
        public string $buildId,
    ) {
    }
}
```

---

## 106. Compilation pipeline

```text
Initialize
    │
Discover Units
    │
Resolve Dependencies
    │
Fingerprint Inputs
    │
Reuse Valid Artifacts
    │
Compile Missing Artifacts
    │
Validate
    │
Link
    │
Write
    │
Generate Manifest
    │
Warm
    │
Finalize
```

---

## 107. Compilation stages

```text
InitializeCompilationStage
DiscoverCompilationUnitsStage
BuildDependencyGraphStage
FingerprintDependenciesStage
ResolveReusableArtifactsStage
CompileArtifactsStage
ValidateArtifactsStage
LinkArtifactsStage
WriteArtifactsStage
GenerateManifestStage
GeneratePreloadStage
WarmupArtifactsStage
FinalizeCompilationStage
```

---

## 108. CompilationResult

```php
final readonly class ControllerCompilationResult
{
    public function __construct(
        public CompilationStatus $status,
        public string $buildId,
        public array $compiledArtifacts,
        public array $reusedArtifacts,
        public array $invalidatedArtifacts,
        public array $warnings,
        public CompilationDiagnostics $diagnostics,
    ) {
    }
}
```

---

## 109. CompilationStatus

```php
enum CompilationStatus: string
{
    case Success = 'success';
    case SuccessWithWarnings = 'success_with_warnings';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case ValidationFailed = 'validation_failed';
}
```

---

## 110. CompilationDiagnostics

Podrá incluir:

* unidad;
* compiler;
* fase;
* dependency chain;
* source location;
* conflicting metadata;
* invalid registry entry;
* cycle;
* compatibility issue;
* suggested remediation.

---

## 111. Source maps

En Debug podrán generarse referencias entre artefactos y fuentes.

```php
final readonly class ArtifactSourceMap
{
    public function __construct(
        public string $artifactId,
        public array $sources,
        public array $metadataOrigins,
    ) {
    }
}
```

---

## 112. Debugging

Comandos potenciales:

```text
volt controllers:compile
volt controllers:compile --changed
volt controllers:validate
volt controllers:inspect route.name
volt controllers:dependencies route.name
volt controllers:invalidate route.name
volt controllers:warmup
volt controllers:preload
volt controllers:builds
volt controllers:activate <build>
volt controllers:rollback
```

---

## 113. Artifact inspector

```php
interface CompiledArtifactInspectorInterface
{
    public function inspect(
        CompiledArtifactId $id
    ): ArtifactInspectionReport;
}
```

---

## 114. Inspection report

Podrá mostrar:

* ID;
* tipo;
* fingerprint;
* firma;
* build;
* dependencias;
* dependientes;
* source map;
* reuse status;
* memory estimate;
* runtime compatibility.

---

## 115. Diff de artefactos

El sistema podrá comparar:

```text
build A vs build B
```

y mostrar:

* artefactos agregados;
* eliminados;
* modificados;
* reutilizados;
* dependencias causantes;
* cambio estimado de memoria.

---

## 116. Observabilidad de compilación

Eventos:

```text
controllers.compilation.started
controllers.compilation.unit_discovered
controllers.compilation.artifact_reused
controllers.compilation.artifact_started
controllers.compilation.artifact_completed
controllers.compilation.artifact_failed
controllers.compilation.validation_failed
controllers.compilation.manifest_generated
controllers.compilation.warmup_completed
controllers.compilation.completed
```

---

## 117. Métricas de compilación

```text
voltstack.controllers.compilation.duration
voltstack.controllers.compilation.artifacts
voltstack.controllers.compilation.reused
voltstack.controllers.compilation.invalidated
voltstack.controllers.compilation.failed
voltstack.controllers.compilation.cache_hit_ratio
voltstack.controllers.compilation.artifact_size
voltstack.controllers.compilation.warmup_duration
```

---

## 118. Runtime metrics

```text
voltstack.controllers.compiled_bundle.hit
voltstack.controllers.compiled_bundle.miss
voltstack.controllers.compiled_bundle.load_duration
voltstack.controllers.compiled_bundle.validation_failed
voltstack.controllers.dynamic_fallback
voltstack.controllers.worker_artifact_cache.size
```

---

## 119. Tracing

Spans:

```text
controllers.compile
controllers.compile.discover
controllers.compile.dependencies
controllers.compile.artifact
controllers.compile.validate
controllers.compile.link
controllers.compile.write
controllers.compile.warmup
```

---

## 120. Seguridad

Los archivos generados deberán:

* escribirse en directorios controlados;
* usar nombres derivados de hashes seguros;
* evitar path traversal;
* no incorporar input no confiable como código;
* validar class strings;
* restringir permisos;
* ser de solo lectura en producción.

---

## 121. Code generation safety

El serializer deberá escapar correctamente:

* strings;
* paths;
* nombres;
* metadata;
* caracteres de control.

Se favorecerá `var_export` controlado o un generador AST seguro.

---

## 122. Artifact trust model

En producción solo se cargarán artefactos:

* generados por el compiler oficial;
* pertenecientes al build activo;
* presentes en manifest;
* con firma válida;
* compatibles con runtime.

---

## 123. Firma criptográfica opcional

En despliegues distribuidos podrá añadirse firma criptográfica del manifest.

```php
interface ArtifactManifestSignerInterface
{
    public function sign(
        ControllerArtifactManifest $manifest
    ): string;

    public function verify(
        ControllerArtifactManifest $manifest
    ): bool;
}
```

No será obligatoria en V1.

---

## 124. Errores y excepciones

Excepciones principales:

```text
ControllerCompilationException
ArtifactCompilationException
ArtifactValidationException
ArtifactDependencyCycleException
ArtifactNotFoundException
ArtifactSignatureMismatchException
ArtifactSchemaMismatchException
ArtifactWriteException
ManifestValidationException
CompilationRegistryNotFrozenException
RuntimeProfileMismatchException
```

---

## 125. Error handling durante runtime

Si falla la carga:

```text
Load artifact
    │
    ▼
Validation failure
    │
    ├── Strict mode → fail execution
    └── Non-strict → dynamic fallback + report
```

---

## 126. Error handling durante build

Una compilación fallida no deberá modificar el build activo.

El workspace fallido podrá conservarse temporalmente para diagnóstico.

---

## 127. Testing

El módulo incluirá:

```text
FakeArtifactCompiler
FakeArtifactStore
FakeArtifactLoader
FakeArtifactInvalidator
FakeCompilationWorkspace
InMemoryArtifactStore
CompilationTestHarness
CompiledArtifactAssertions
```

---

## 128. Assertions

```php
CompiledArtifactAssert::type(
    $artifact,
    CompiledArtifactType::Invocation
);

CompiledArtifactAssert::dependsOn(
    $artifact,
    'controller:App\Http\OrderController'
);

CompiledArtifactAssert::deterministic(
    $firstBuild,
    $secondBuild
);

CompiledArtifactAssert::validSignature($artifact);

CompilationAssert::reused(
    $result,
    $artifactId
);
```

---

## 129. Casos de prueba

* compilación completa;
* compilación incremental;
* compilación determinista;
* registry no congelado;
* ciclo de dependencias;
* cambio de archivo;
* cambio de configuración;
* cambio de metadata;
* artefacto reutilizado;
* firma inválida;
* schema incompatible;
* manifest corrupto;
* escritura atómica;
* rollback;
* Worker cache;
* strict mode;
* fallback dinámico;
* preload;
* warmup;
* build pinning.

---

## 130. Benchmarks

```text
Dynamic controller execution
Compiled cold execution
Compiled warm execution
Worker cache hit
Artifact file load
OPcache hit
Full application compile
Incremental compile
Warmup
Manifest lookup
FrankenPHP repeated execution
```

---

## 131. Objetivos de rendimiento

La arquitectura buscará:

* lookup de bundle en tiempo constante;
* cero reflexión en ruta caliente;
* cero escaneo de atributos por request;
* mínima asignación de objetos derivados;
* alta reutilización en Worker;
* carga compatible con OPcache;
* invalidación incremental precisa.

Los objetivos numéricos deberán establecerse mediante benchmarks reales.

---

## 132. Estructura de directorios

```text
src/
└── Quantum/
    └── Controllers/
        └── Compilation/
            ├── Contracts/
            │   ├── ControllerCompilationManagerInterface.php
            │   ├── ArtifactCompilerInterface.php
            │   ├── CompiledArtifactInterface.php
            │   ├── ControllerCompilerRegistryInterface.php
            │   ├── CompiledArtifactStoreInterface.php
            │   ├── CompiledArtifactLoaderInterface.php
            │   ├── CompiledArtifactWriterInterface.php
            │   ├── CompiledArtifactSerializerInterface.php
            │   ├── CompiledArtifactValidatorInterface.php
            │   ├── ArtifactLinkerInterface.php
            │   ├── CompiledArtifactInvalidatorInterface.php
            │   └── ControllerCompilationWarmupManagerInterface.php
            │
            ├── Engine/
            │   └── ControllerCompilationManager.php
            │
            ├── Context/
            │   ├── CompilationContext.php
            │   ├── CompilationOptions.php
            │   ├── RuntimeCapabilities.php
            │   ├── FrozenRegistrySet.php
            │   └── CompilationWorkspace.php
            │
            ├── Request/
            │   ├── ControllerCompilationRequest.php
            │   ├── ControllerCompilationTarget.php
            │   ├── ControllerCompilationTargetType.php
            │   ├── CompilationMode.php
            │   └── CompilationScope.php
            │
            ├── Unit/
            │   ├── CompilationUnit.php
            │   ├── CompilationUnitId.php
            │   ├── CompilationUnitDiscoverer.php
            │   └── SourceSet.php
            │
            ├── Artifact/
            │   ├── CompiledArtifactId.php
            │   ├── CompiledArtifactType.php
            │   ├── ArtifactFingerprint.php
            │   ├── ArtifactSignature.php
            │   ├── ArtifactDependency.php
            │   ├── ArtifactDependencyType.php
            │   ├── CompiledArtifactCollection.php
            │   └── LinkedArtifactCollection.php
            │
            ├── Compilers/
            │   ├── ControllerResolutionCompiler.php
            │   ├── ControllerMetadataCompiler.php
            │   ├── ParameterResolutionCompiler.php
            │   ├── ControllerInterceptorCompiler.php
            │   ├── ControllerInvocationCompiler.php
            │   ├── ResultTransformationCompiler.php
            │   ├── ResponseTransportCompiler.php
            │   ├── ExceptionHandlingCompiler.php
            │   ├── ControllerLifecycleCompiler.php
            │   ├── ControllerObservabilityCompiler.php
            │   └── ControllerExecutionBundleCompiler.php
            │
            ├── Plans/
            │   ├── CompiledControllerResolutionPlan.php
            │   ├── CompiledControllerMetadataPlan.php
            │   ├── CompiledParameterResolutionPlan.php
            │   ├── CompiledInterceptorPlan.php
            │   ├── CompiledControllerInvocationPlan.php
            │   ├── CompiledTransformationPlan.php
            │   ├── CompiledTransportPlan.php
            │   ├── CompiledExceptionHandlingPlan.php
            │   ├── CompiledLifecyclePlan.php
            │   ├── CompiledControllerObservabilityPlan.php
            │   └── CompiledControllerExecutionBundle.php
            │
            ├── Registry/
            │   └── ControllerCompilerRegistry.php
            │
            ├── Dependency/
            │   ├── ArtifactDependencyGraph.php
            │   ├── DependencyGraphBuilder.php
            │   ├── DependencyChangeSet.php
            │   └── DependencyCycleDetector.php
            │
            ├── Fingerprint/
            │   ├── ArtifactFingerprintGenerator.php
            │   ├── SourceFingerprintGenerator.php
            │   ├── RegistryFingerprintGenerator.php
            │   └── ConfigurationFingerprintGenerator.php
            │
            ├── Validation/
            │   ├── CompiledArtifactValidator.php
            │   ├── ArtifactValidationResult.php
            │   ├── ManifestValidator.php
            │   └── RuntimeCompatibilityValidator.php
            │
            ├── Linking/
            │   ├── ArtifactLinker.php
            │   ├── ArtifactReference.php
            │   └── SharedArtifactResolver.php
            │
            ├── Serialization/
            │   ├── PhpArtifactSerializer.php
            │   ├── PhpValueExporter.php
            │   └── SafePhpCodeGenerator.php
            │
            ├── Storage/
            │   ├── PhpFileArtifactStore.php
            │   ├── InMemoryArtifactStore.php
            │   ├── CompositeArtifactStore.php
            │   ├── CompiledArtifactWriter.php
            │   └── AtomicArtifactWriter.php
            │
            ├── Loading/
            │   ├── CompiledArtifactLoader.php
            │   ├── ControllerExecutionBundleLoader.php
            │   ├── ActiveManifestResolver.php
            │   └── ExecutionBuildReference.php
            │
            ├── Manifest/
            │   ├── ControllerArtifactManifest.php
            │   ├── ArtifactManifestGenerator.php
            │   ├── RouteArtifactMap.php
            │   └── ArtifactManifestSigner.php
            │
            ├── Cache/
            │   ├── WorkerArtifactCache.php
            │   ├── RequestArtifactCache.php
            │   ├── ArtifactCacheKey.php
            │   └── NullArtifactCache.php
            │
            ├── Invalidation/
            │   ├── CompiledArtifactInvalidator.php
            │   ├── IncrementalCompilationPlanner.php
            │   ├── InvalidationResult.php
            │   └── StaleArtifactDetector.php
            │
            ├── Warmup/
            │   ├── ControllerCompilationWarmupManager.php
            │   ├── WarmupPolicy.php
            │   ├── WarmupReport.php
            │   └── HotRouteSelector.php
            │
            ├── Preload/
            │   ├── ControllerPreloadGenerator.php
            │   ├── PreloadPolicy.php
            │   ├── PreloadBudget.php
            │   └── PreloadArtifactSelector.php
            │
            ├── Deployment/
            │   ├── ControllerArtifactDeploymentManager.php
            │   ├── CompilationBuildId.php
            │   ├── BuildActivator.php
            │   ├── BuildRollbackManager.php
            │   ├── ArtifactGarbageCollector.php
            │   └── ArtifactRetentionPolicy.php
            │
            ├── Pipeline/
            │   ├── ControllerCompilationPipeline.php
            │   └── Stages/
            │
            ├── Diagnostics/
            │   ├── CompilationDiagnostics.php
            │   ├── ArtifactInspector.php
            │   ├── ArtifactInspectionReport.php
            │   ├── ArtifactDiff.php
            │   └── ArtifactSourceMap.php
            │
            ├── Events/
            ├── Metrics/
            ├── Exceptions/
            ├── Testing/
            └── Providers/
                └── ControllerCompilationServiceProvider.php
```

---

## 133. Configuración

```php
// config/controller_compilation.php

return [
    'enabled' => true,

    'mode' => env('APP_ENV') === 'production'
        ? 'production'
        : 'development',

    'strict' => env('APP_ENV') === 'production',

    'paths' => [
        'root' => storage_path('framework/controllers'),
        'builds' => storage_path('framework/controllers/builds'),
        'current' => storage_path('framework/controllers/current'),
    ],

    'artifacts' => [
        'format' => 'php',
        'atomic_write' => true,
        'validate_after_write' => true,
        'shared_artifacts' => true,
    ],

    'incremental' => [
        'enabled' => true,
        'reuse_unchanged' => true,
        'prune_stale' => true,
    ],

    'cache' => [
        'execution' => true,
        'request' => true,
        'worker' => true,
        'worker_max_artifacts' => 2048,
    ],

    'fallback' => [
        'dynamic' => env('APP_ENV') !== 'production',
        'report' => true,
    ],

    'warmup' => [
        'enabled' => true,
        'validate_all' => true,
        'hot_routes' => [],
    ],

    'preload' => [
        'enabled' => false,
        'max_files' => 500,
        'max_estimated_bytes' => 32 * 1024 * 1024,
    ],

    'deployment' => [
        'atomic_activation' => true,
        'retain_builds' => 3,
        'rollback_enabled' => true,
    ],

    'workers' => [
        'pin_build_per_execution' => true,
        'reload_strategy' => 'restart',
    ],

    'debug' => [
        'source_maps' => env('APP_DEBUG', false),
        'debug_symbols' => env('APP_DEBUG', false),
        'preserve_failed_workspace' => env('APP_DEBUG', false),
    ],
];
```

---

## 134. Service Provider

```php
final class ControllerCompilationServiceProvider
    extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(
            ControllerCompilationManagerInterface::class,
            ControllerCompilationManager::class,
        );

        $this->container->singleton(
            ControllerCompilerRegistryInterface::class,
            ControllerCompilerRegistry::class,
        );

        $this->container->singleton(
            CompiledArtifactStoreInterface::class,
            PhpFileArtifactStore::class,
        );

        $this->container->singleton(
            CompiledArtifactLoaderInterface::class,
            CompiledArtifactLoader::class,
        );
    }

    public function boot(
        ControllerCompilerRegistryInterface $registry
    ): void {
        $registry->freeze();
    }
}
```

---

## 135. Integración con Routing

Routing deberá producir una firma estable de ruta.

```php
$routeSignature = $routeSignatureGenerator->generate(
    $routeDefinition,
);
```

Esta firma permitirá obtener directamente el bundle compilado.

---

## 136. Integración con ControllerExecutionManager

```php
$bundle = $compiledBundleLoader->loadBundleFor(
    $request->routeMatch,
    $request->runtime,
);

$execution = $executionFactory->create(
    request: $request,
    compiledBundle: $bundle,
);
```

---

## 137. Integración con Metadata Engine

El Metadata Engine será responsable de producir metadata estable y compilable.

La compilación no deberá volver a interpretar atributos fuera del Metadata Engine.

---

## 138. Integración con Exception Handling

Los fallos de carga o validación serán enviados al sistema de excepciones.

Deberán clasificarse como:

```text
Framework
Compilation
Infrastructure
```

según su origen.

---

## 139. Integración con Observability

La ejecución deberá registrar:

* bundle hit;
* bundle miss;
* build ID;
* artifact load;
* dynamic fallback;
* validation failure;
* cache level.

---

## 140. Integración con FrankenPHP

La estrategia recomendada será:

```text
Compile before deployment
Activate build
Restart or recycle Workers
Pin build per execution
Serve using immutable artifacts
```

---

## 141. ADR-001

**Todos los planes compilados implementarán un contrato común de artefacto.**

---

## 142. ADR-002

**Los compiladores especializados compartirán infraestructura de almacenamiento, invalidación y carga.**

---

## 143. ADR-003

**La compilación no modificará la semántica funcional del pipeline.**

---

## 144. ADR-004

**Los artefactos serán inmutables y libres de estado request-scoped.**

---

## 145. ADR-005

**El artefacto raíz de runtime será `CompiledControllerExecutionBundle`.**

---

## 146. ADR-006

**La invalidación será dependency-aware y transitiva.**

---

## 147. ADR-007

**Los fingerprints deberán ser deterministas.**

---

## 148. ADR-008

**La firma se utilizará para validar integridad y compatibilidad.**

---

## 149. ADR-009

**Los registries deberán estar congelados antes de compilar.**

---

## 150. ADR-010

**El formato oficial V1 será PHP cargable mediante `require`.**

---

## 151. ADR-011

**Los artefactos se escribirán de forma atómica.**

---

## 152. ADR-012

**Cada despliegue utilizará un build inmutable y versionado.**

---

## 153. ADR-013

**Cada ejecución quedará fijada a un único build.**

---

## 154. ADR-014

**En producción no se compilará durante la petición.**

---

## 155. ADR-015

**El modo estricto no permitirá fallback dinámico silencioso.**

---

## 156. ADR-016

**El Worker cache solo almacenará objetos inmutables.**

---

## 157. ADR-017

**OPcache será una capa complementaria, no una abstracción interna del framework.**

---

## 158. ADR-018

**Warmup y preload serán capacidades independientes.**

---

## 159. ADR-019

**La activación de builds será atómica y reversible.**

---

## 160. ADR-020

**Los artefactos anteriores se conservarán temporalmente para rollback.**

---

## 161. ADR-021

**Los source maps y debug symbols estarán separados del artefacto funcional.**

---

## 162. ADR-022

**Los artefactos compartidos podrán referenciarse para reducir duplicación.**

---

## 163. ADR-023

**Los ciclos de dependencias serán errores de compilación.**

---

## 164. ADR-024

**El runtime no mezclará artefactos pertenecientes a builds diferentes.**

---

## 165. ADR-025

**El sistema medirá cache hits, misses y fallback dinámico.**

---

## 166. Implementación V1

La V1 deberá incluir:

* contrato común de artefactos;
* compiler registry;
* todos los compiladores de Controllers;
* dependency graph;
* fingerprints;
* signatures;
* validation;
* linking;
* execution bundles;
* PHP artifact serializer;
* artifact store;
* manifest;
* runtime loader;
* Worker cache;
* incremental compilation;
* invalidation;
* warmup;
* preload generator básico;
* atomic builds;
* rollback;
* observabilidad;
* CLI;
* testing utilities.

---

## 167. Fuera de V1

Se aplazarán:

* almacenamiento distribuido;
* firma criptográfica obligatoria;
* remote build cache;
* content-addressed storage completo;
* compilación paralela distribuida;
* preload adaptativo;
* optimización basada en perfiles;
* generación nativa de extensiones.

---

## 168. Roadmap V2

Podrá incluir:

* compilación paralela;
* remote artifact cache;
* content-addressed artifacts;
* build sharing entre nodos;
* preload guiado por métricas;
* deduplicación avanzada;
* artifact compression;
* verificaciones de supply chain;
* dashboards de compilación.

---

## 169. Roadmap V3

Podrá incorporar:

* compilación predictiva;
* optimización profile-guided;
* planes especializados por patrones de tráfico;
* adaptive artifact loading;
* compilación distribuida;
* artefactos firmados entre nodos;
* optimización automática del grafo;
* generación parcial de código nativo.

---

## 170. Flujo de compilación completo

```text
Application definitions
        │
        ▼
Freeze registries
        │
        ▼
Discover compilation units
        │
        ▼
Build dependency graph
        │
        ▼
Fingerprint inputs
        │
        ▼
Reuse unchanged artifacts
        │
        ▼
Compile changed artifacts
        │
        ▼
Validate
        │
        ▼
Link
        │
        ▼
Build execution bundles
        │
        ▼
Write atomic build
        │
        ▼
Generate manifest
        │
        ▼
Warmup and preload
        │
        ▼
Activate build
```

---

## 171. Flujo de runtime compilado

```text
Route matched
        │
        ▼
Resolve active build
        │
        ▼
Lookup execution bundle
        │
        ▼
Worker cache
        │
        ├── Hit
        │     ▼
        │   Execute
        │
        └── Miss
              │
              ▼
          Load PHP artifact
              │
              ▼
          Validate signature
              │
              ▼
          Store in Worker cache
              │
              ▼
          Execute
```

---

## 172. Flujo de invalidación incremental

```text
Source or configuration changed
        │
        ▼
Generate change set
        │
        ▼
Find direct artifacts
        │
        ▼
Find transitive dependents
        │
        ▼
Recompile affected artifacts
        │
        ▼
Reuse unaffected artifacts
        │
        ▼
Generate new build
```

---

## 173. Beneficios arquitectónicos

Este framework proporciona:

* una única infraestructura de compilación;
* eliminación de reflexión en runtime;
* menor duplicación entre módulos;
* invalidación precisa;
* builds reproducibles;
* artefactos compatibles con OPcache;
* seguridad para FrankenPHP;
* despliegues atómicos;
* rollback;
* debugging claro;
* pruebas deterministas;
* capacidad de evolución transversal.

---

## 174. Conclusión

El **Controller Compilation Framework** convierte toda la arquitectura del subsistema Controllers en un conjunto coherente de artefactos compilados, versionados e inmutables.

La pieza central será:

```text
CompiledControllerExecutionBundle
```

Este bundle permitirá al runtime ejecutar un controlador utilizando planes previamente resueltos para:

* resolución;
* metadata;
* parámetros;
* interceptores;
* invocación;
* transformación;
* transporte;
* excepciones;
* lifecycle;
* observabilidad.

Con ello, VoltStack podrá mantener una arquitectura altamente extensible durante desarrollo y una ruta de ejecución mínima, estable y optimizada en producción.

---
