# Sistema de interceptores de controladores de VoltStack


**Versión:** 1.0
**Estado:** Draft
**Módulo:** `VoltStack\Quantum\Controllers\Interceptors`
**Documentos relacionados:**

```text
00_CONTROLLER_PROJECT_CONTEXT.md
01_CONTROLLER_ARCHITECTURE.md
02_CONTROLLER_BASE_CLASS.md
03_CONTROLLER_DISPATCHER.md
04_CONTROLLER_RESOLVER.md
05_PARAMETER_RESOLUTION_ENGINE.md
06_METADATA_ENGINE.md
```

---

## 1. Propósito

Este documento define la arquitectura del sistema de interceptores de controladores de VoltStack.

Los interceptores permiten ejecutar comportamiento transversal antes, alrededor y después de la invocación de un controlador sin introducir esa lógica directamente en:

* El controlador.
* El dispatcher.
* El invoker.
* El router.
* El middleware HTTP global.
* El motor de resolución de parámetros.
* El normalizador de resultados.

El sistema permitirá implementar capacidades como:

* Autorización.
* Validación.
* Transacciones.
* Idempotencia.
* Auditoría.
* Caché.
* Métricas.
* Logging.
* Rate limiting.
* Retries.
* Locks.
* Feature flags.
* Eventos.
* Transformación de argumentos.
* Transformación de resultados.
* Circuit breakers.
* Telemetría.
* Seguridad.
* Políticas multi-tenant.

Los interceptores operarán sobre una ejecución concreta representada por:

```php
ControllerExecution
```

y podrán envolver la invocación del controlador mediante un pipeline especializado.

---

## 2. Problema que resuelve

Sin interceptores, las responsabilidades transversales terminarían distribuidas en lugares incorrectos.

Ejemplo:

```php
final class UserController
{
    public function store(Request $request): Response
    {
        if (! $request->user()->can('create', User::class)) {
            throw new AuthorizationException();
        }

        $validator = Validator::make(
            $request->all(),
            [...]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        DB::beginTransaction();

        try {
            $user = User::create(...);

            Audit::record(...);

            DB::commit();

            return response()->json($user);
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }
    }
}
```

Este enfoque mezcla:

* Transporte HTTP.
* Autorización.
* Validación.
* Persistencia.
* Auditoría.
* Manejo transaccional.
* Construcción de respuesta.
* Dominio.

VoltStack separará esas responsabilidades:

```text
AuthorizationInterceptor
        ↓
ValidationInterceptor
        ↓
TransactionInterceptor
        ↓
AuditInterceptor
        ↓
Controller Invocation
```

El controlador podrá concentrarse en su operación:

```php
final class UserController
{
    public function store(CreateUserData $data): User
    {
        return User::create($data->toArray());
    }
}
```

---

## 3. Diferencia entre middleware e interceptores

VoltStack diferenciará explícitamente:

```text
HTTP Middleware
```

de:

```text
Controller Interceptors
```

Aunque ambos utilizan una estructura de pipeline, tienen responsabilidades y contextos distintos.

---

## 4. Middleware HTTP

El middleware HTTP opera sobre:

```text
Request
    ↓
RequestHandler
    ↓
Response
```

Está orientado al transporte y al ciclo HTTP.

Casos habituales:

* CORS.
* Compresión.
* Trusted proxies.
* Session startup.
* Cookies.
* CSRF.
* Mantenimiento global.
* HTTP authentication.
* Request body limits.
* Response headers.
* Content negotiation global.

Contrato conceptual:

```php
interface MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface;
}
```

---

## 5. Controller Interceptor

Un interceptor opera sobre:

```text
ControllerExecution
```

y controla la invocación del controlador.

Casos habituales:

* Autorización del método.
* Validación de parámetros.
* Transacción del caso de uso.
* Idempotencia.
* Auditoría de una operación.
* Caché del resultado.
* Transformación de argumentos.
* Transformación del resultado.
* Eventos alrededor del controlador.
* Locks por recurso.
* Reintentos de ejecución.
* Medición del tiempo de ejecución.
* Políticas multi-tenant.

Contrato conceptual:

```php
interface ControllerInterceptorInterface
{
    public function intercept(
        ControllerExecution $execution,
        ControllerInterceptorChainInterface $chain
    ): mixed;
}
```

---

## 6. Comparación

| Característica                        | HTTP Middleware                | Controller Interceptor    |
| ------------------------------------- | ------------------------------ | ------------------------- |
| Contexto principal                    | Request/Response               | ControllerExecution       |
| Nivel                                 | Transporte HTTP                | Ejecución del controlador |
| Conoce el controlador                 | No necesariamente              | Sí                        |
| Conoce parámetros resueltos           | No                             | Sí                        |
| Conoce metadata                       | Limitado                       | Sí                        |
| Puede envolver la invocación          | Indirectamente                 | Directamente              |
| Puede transformar argumentos          | No recomendado                 | Sí                        |
| Puede transformar resultado bruto     | No                             | Sí                        |
| Puede abrir transacciones             | Posible, pero demasiado amplio | Sí                        |
| Puede aplicar autorización por método | Limitado                       | Sí                        |
| Puede reutilizarse fuera de HTTP      | Difícil                        | Sí                        |
| Compatible con comandos/jobs          | No directamente                | Potencialmente sí         |

---

## 7. Principios de diseño

El sistema seguirá estos principios:

1. Un interceptor tendrá una responsabilidad claramente definida.
2. El dispatcher no contendrá lógica transversal.
3. El invoker únicamente invocará el callable.
4. El orden será determinista.
5. Los interceptores serán registrables y reemplazables.
6. Los interceptores podrán provenir de metadata.
7. El sistema soportará short circuit.
8. El resultado dinámico y compilado será equivalente.
9. El estado request-scoped nunca se compartirá entre ejecuciones.
10. Los interceptores globales deberán ser inmutables o stateless.
11. La resolución de interceptores será separada de su ejecución.
12. El pipeline podrá inspeccionarse y depurarse.
13. La recursión y los ciclos serán detectables.
14. Los interceptores no deberán depender obligatoriamente de HTTP.
15. El sistema será compatible con FrankenPHP y workers persistentes.

---

## 8. Posición dentro del pipeline

El pipeline general del controlador se mantiene así:

```text
InitializeExecutionStage

↓

ResolveControllerStage

↓

InjectControllerContextStage

↓

ResolveControllerMetadataStage

↓

ResolveArgumentsStage

↓

ResolveControllerMiddlewareStage

↓

ResolveInterceptorsStage

↓

AuthorizationStage

↓

ValidationStage

↓

ControllerMiddlewareStage

↓

ControllerInterceptorsStage

↓

InvokeControllerStage

↓

NormalizeControllerResultStage

↓

FinalizeControllerResponseStage

↓

CompleteExecutionStage
```

La etapa:

```text
ResolveInterceptorsStage
```

construye el plan de interceptores.

La etapa:

```text
ControllerInterceptorsStage
```

ejecuta el pipeline.

La invocación real ocurre dentro del eslabón terminal:

```text
InvokeControllerTerminal
```

Por tanto, conceptualmente:

```text
ControllerInterceptorsStage
        │
        ▼
ControllerInterceptorPipeline
        │
        ├── Interceptor A
        ├── Interceptor B
        ├── Interceptor C
        └── InvokeControllerTerminal
```

---

## 9. Arquitectura general

```text
ControllerExecution
        │
        ▼
ControllerInterceptorResolver
        │
        ├── Global Registry
        ├── Module Registry
        ├── Route Metadata
        ├── Controller Metadata
        ├── Method Metadata
        ├── Runtime Metadata
        └── Compiled Plan
        │
        ▼
InterceptorDefinition[]
        │
        ▼
InterceptorPlanBuilder
        │
        ├── Normalize
        ├── Filter
        ├── Deduplicate
        ├── Apply exclusions
        ├── Sort
        ├── Validate dependencies
        └── Compile
        │
        ▼
ControllerInterceptorPlan
        │
        ▼
ControllerInterceptorPipeline
        │
        ▼
ControllerInterceptorChain
        │
        ▼
InvokeControllerTerminal
        │
        ▼
Raw Controller Result
```

---

## 10. Conceptos fundamentales

El sistema estará formado por:

```text
ControllerInterceptorInterface
ControllerInterceptorChainInterface
InterceptorDefinition
InterceptorDescriptor
InterceptorScope
InterceptorPhase
InterceptorPriority
InterceptorSource
InterceptorRegistry
InterceptorResolver
InterceptorPlanBuilder
ControllerInterceptorPlan
ControllerInterceptorPipeline
ControllerInterceptorChain
ControllerInvocationTerminal
InterceptorCondition
InterceptorExclusion
InterceptorExecutionRecord
```

---

## 11. Contrato principal

```php
namespace VoltStack\Quantum\Controllers\Interceptors\Contracts;

use VoltStack\Quantum\Controllers\Execution\ControllerExecution;

interface ControllerInterceptorInterface
{
    public function intercept(
        ControllerExecution $execution,
        ControllerInterceptorChainInterface $chain
    ): mixed;
}
```

Un interceptor podrá:

* Ejecutar lógica antes del siguiente eslabón.
* Modificar la ejecución.
* Invocar al siguiente eslabón.
* Inspeccionar el resultado.
* Transformar el resultado.
* Capturar excepciones.
* Reintentar la cadena.
* Evitar la invocación.
* Devolver una respuesta o resultado directo.

---

## 12. Contrato de la cadena

```php
interface ControllerInterceptorChainInterface
{
    public function proceed(
        ControllerExecution $execution
    ): mixed;
}
```

El uso típico será:

```php
final class MetricsInterceptor implements
    ControllerInterceptorInterface
{
    public function intercept(
        ControllerExecution $execution,
        ControllerInterceptorChainInterface $chain
    ): mixed {
        $startedAt = hrtime(true);

        try {
            return $chain->proceed($execution);
        } finally {
            $duration = hrtime(true) - $startedAt;

            $execution->timings->record(
                'controller.interceptor.metrics',
                $duration
            );
        }
    }
}
```

---

## 13. Semántica around

Los interceptores utilizarán semántica `around`.

```text
Interceptor A before
    Interceptor B before
        Controller
    Interceptor B after
Interceptor A after
```

Ejemplo:

```php
public function intercept(
    ControllerExecution $execution,
    ControllerInterceptorChainInterface $chain
): mixed {
    $this->before($execution);

    try {
        $result = $chain->proceed($execution);

        return $this->after(
            $execution,
            $result
        );
    } catch (Throwable $exception) {
        return $this->onException(
            $execution,
            $exception
        );
    } finally {
        $this->finally($execution);
    }
}
```

---

## 14. Resultado del interceptor

La primera versión utilizará `mixed` como retorno para mantener compatibilidad con el resultado bruto del controlador.

El valor podrá ser:

* `ResponseInterface`.
* DTO.
* Entidad.
* Colección.
* Array.
* String.
* Integer.
* Boolean.
* Null.
* Stream.
* Generator.
* Componente.
* SPA payload.
* Resultado personalizado.

Posteriormente, el `Result Normalization System` convertirá el resultado a una respuesta válida.

---

## 15. ControllerExecution como contexto

Todos los interceptores recibirán el mismo:

```php
ControllerExecution
```

Este objeto contendrá:

```text
definition
context
resolvedController
arguments
parameters
metadata
middleware
interceptors
result
response
exception
timings
attributes
```

El interceptor no deberá crear un contexto paralelo cuando la información pertenezca a la ejecución.

---

## 16. Mutabilidad controlada

`ControllerExecution` será mutable durante el pipeline, pero la mutabilidad deberá estar controlada.

Un interceptor podrá modificar:

* Argumentos.
* Atributos de ejecución.
* Resultado bruto.
* Estado transaccional.
* Metadata runtime permitida.
* Registros de auditoría.
* Timings.
* Excepción activa.

No deberá modificar:

* La identidad original de la ruta.
* El controlador resuelto después de validarse.
* Metadata compilada inmutable.
* Registries globales.
* Servicios singleton.
* Estado de otra ejecución.

---

## 17. InterceptorDefinition

Los interceptores no se representarán únicamente mediante nombres de clase.

```php
final readonly class InterceptorDefinition
{
    public function __construct(
        public string $interceptor,
        public array $arguments = [],
        public int $priority = 0,
        public InterceptorPhase $phase = InterceptorPhase::Around,
        public InterceptorScope $scope = InterceptorScope::Execution,
        public ?string $alias = null,
        public array $tags = [],
        public array $before = [],
        public array $after = [],
        public array $conditions = [],
        public bool $enabled = true,
        public bool $repeatable = false,
        public bool $terminal = false,
        public ?InterceptorSource $source = null,
        public array $metadata = [],
    ) {
    }
}
```

---

## 18. Propiedades de InterceptorDefinition

### interceptor

Clase o identificador registrado.

```php
TransactionInterceptor::class
```

### arguments

Configuración del interceptor.

```php
[
    'connection' => 'default',
    'retry' => 3,
]
```

### priority

Prioridad numérica.

### phase

Momento lógico de ejecución.

### scope

Ciclo de vida de la instancia.

### alias

Nombre legible.

```text
transaction
audit
cache
```

### before / after

Dependencias de orden.

### conditions

Condiciones de activación.

### enabled

Permite deshabilitar la definición.

### repeatable

Permite múltiples instancias equivalentes.

### terminal

Indica que puede impedir continuar intencionalmente.

### source

Origen de la definición.

---

## 19. InterceptorPhase

Aunque el contrato sea around, las fases facilitarán orden y semántica.

```php
enum InterceptorPhase: string
{
    case Guard = 'guard';
    case Input = 'input';
    case Before = 'before';
    case Around = 'around';
    case Invocation = 'invocation';
    case After = 'after';
    case Output = 'output';
    case Error = 'error';
    case Finalize = 'finalize';
}
```

---

## 20. Fases recomendadas

```text
Guard
    autorización
    rate limiting
    feature flags
    tenant policy
    idempotency lookup

Input
    validación adicional
    transformación de argumentos
    sanitización
    binding contextual

Before
    locks
    audit start
    eventos before
    transaction begin

Around
    transaction
    retry
    circuit breaker
    tracing
    metrics

Invocation
    terminal del controlador

After
    audit success
    domain events
    cache store

Output
    transformación de resultado
    envelope
    pagination metadata

Error
    recovery
    exception mapping local
    audit failure

Finalize
    release lock
    cleanup
    close span
```

La fase no reemplaza la prioridad; ambos se combinarán.

---

## 21. Orden de fases

```text
Guard
    ↓
Input
    ↓
Before
    ↓
Around
    ↓
Invocation
    ↓
After
    ↓
Output
    ↓
Error
    ↓
Finalize
```

Debido a la semántica around, algunos interceptores ejecutarán su parte posterior en orden inverso.

---

## 22. Prioridad

VoltStack definirá rangos sugeridos:

```text
10000–9000  Seguridad crítica
8999–8000   Tenant y contexto
7999–7000   Rate limit e idempotencia
6999–6000   Validación y transformación de entrada
5999–5000   Locks y transacciones
4999–4000   Retry y resiliencia
3999–3000   Auditoría y eventos
2999–2000   Caché y resultados
1999–1000   Métricas y tracing
999–0       Extensiones generales
```

Los números más altos se ejecutarán antes.

---

## 23. Orden antes/después

La prioridad no será suficiente para todos los casos.

Ejemplo:

```php
new InterceptorDefinition(
    interceptor: AuditInterceptor::class,
    after: [AuthorizationInterceptor::class],
    before: [TransactionInterceptor::class],
);
```

El `InterceptorPlanBuilder` construirá un grafo de dependencias.

---

## 24. Detección de ciclos

Ejemplo inválido:

```text
A before B
B before C
C before A
```

El plan builder lanzará:

```text
CircularInterceptorDependencyException
```

El mensaje deberá mostrar:

```text
A
    before B

B
    before C

C
    before A
```

---

## 25. InterceptorScope

```php
enum InterceptorScope: string
{
    case Singleton = 'singleton';
    case Worker = 'worker';
    case Request = 'request';
    case Execution = 'execution';
    case Transient = 'transient';
}
```

---

## 26. Significado de scopes

### Singleton

Una sola instancia durante la vida de la aplicación.

Solo para interceptores:

* Inmutables.
* Stateless.
* Thread-safe.
* Sin referencias al request.

### Worker

Una instancia por worker persistente.

Debe cumplir reglas similares a singleton.

### Request

Una instancia por petición.

Puede recibir dependencias request-scoped.

### Execution

Una instancia por ejecución de controlador.

Será el scope predeterminado.

### Transient

Una nueva instancia cada vez que sea solicitada.

---

## 27. Scope predeterminado

Se recomienda:

```text
Execution
```

porque ofrece:

* Aislamiento.
* Seguridad con FrankenPHP.
* Menor riesgo de memory leaks.
* Capacidad de mantener estado temporal.
* Comportamiento determinista.

Los interceptores completamente stateless podrán optimizarse a singleton.

---

## 28. InterceptorDescriptor

El registry almacenará descriptores.

```php
final readonly class InterceptorDescriptor
{
    public function __construct(
        public string $id,
        public string $interceptor,
        public InterceptorScope $scope,
        public int $defaultPriority,
        public InterceptorPhase $defaultPhase,
        public array $tags = [],
        public bool $repeatable = false,
        public bool $compilable = true,
        public bool $stateless = false,
        public array $supportedContexts = ['controller'],
    ) {
    }
}
```

---

## 29. Registry

```php
interface ControllerInterceptorRegistryInterface
{
    public function register(
        InterceptorDescriptor $descriptor
    ): void;

    public function alias(
        string $alias,
        string $interceptor
    ): void;

    public function has(string $id): bool;

    public function get(string $id): InterceptorDescriptor;

    public function resolveAlias(string $alias): string;

    public function remove(string $id): void;

    public function replace(
        string $id,
        InterceptorDescriptor $replacement
    ): void;

    public function tagged(string $tag): array;

    public function all(): array;

    public function freeze(): void;
}
```

---

## 30. Registro de interceptores

```php
$registry->register(
    new InterceptorDescriptor(
        id: 'transaction',
        interceptor: TransactionInterceptor::class,
        scope: InterceptorScope::Execution,
        defaultPriority: 5500,
        defaultPhase: InterceptorPhase::Around,
        tags: ['database', 'atomicity'],
        repeatable: false,
        compilable: true,
    )
);
```

Alias:

```php
$registry->alias(
    'tx',
    'transaction'
);
```

---

## 31. Fuentes de interceptores

Los interceptores podrán provenir de:

```text
Framework global defaults
Module registration
Application configuration
Route groups
Route definition
Controller class metadata
Controller method metadata
Runtime execution metadata
Package providers
Compiled interceptor plan
```

---

## 32. InterceptorSource

```php
final readonly class InterceptorSource
{
    public function __construct(
        public InterceptorSourceType $type,
        public string $location,
        public int $precedence,
        public array $context = [],
    ) {
    }
}
```

```php
enum InterceptorSourceType: string
{
    case Framework = 'framework';
    case Module = 'module';
    case Configuration = 'configuration';
    case RouteGroup = 'route_group';
    case Route = 'route';
    case Controller = 'controller';
    case Method = 'method';
    case Attribute = 'attribute';
    case Metadata = 'metadata';
    case Package = 'package';
    case Runtime = 'runtime';
    case Compiled = 'compiled';
}
```

---

## 33. Precedencia de fuentes

Orden recomendado:

```text
Runtime
    ↓
Method
    ↓
Controller
    ↓
Route
    ↓
Route Group
    ↓
Application Configuration
    ↓
Module
    ↓
Package
    ↓
Framework Default
```

La precedencia se utilizará principalmente para:

* Reemplazos.
* Exclusiones.
* Configuración.
* Deshabilitación.
* Resolución de conflictos.

No implica necesariamente el orden de ejecución.

---

## 34. Metadata del sistema

El `Metadata Engine` expondrá keys como:

```text
controller.interceptors
controller.interceptor_exclusions
controller.interceptor_groups
controller.interceptor_order
controller.interceptor_config
```

Schemas iniciales:

```php
new MetadataSchema(
    key: 'controller.interceptors',
    type: MetadataValueType::List,
    mergeStrategy: MetadataMergeStrategy::UniqueAppend,
    defaultValue: [],
    repeatable: true,
    inheritable: true,
);
```

```php
new MetadataSchema(
    key: 'controller.interceptor_exclusions',
    type: MetadataValueType::List,
    mergeStrategy: MetadataMergeStrategy::UniqueAppend,
    defaultValue: [],
    repeatable: true,
    inheritable: true,
);
```

---

## 35. Atributos de interceptores

Aunque el catálogo detallado pertenecerá a:

```text
CONTROLLER_ATTRIBUTES_REFERENCE.md
```

el sistema deberá soportar al menos:

```php
#[Intercept(TransactionInterceptor::class)]
#[WithoutInterceptor(AuditInterceptor::class)]
#[InterceptorGroup('write-operation')]
#[Transactional]
#[Idempotent]
#[Auditable]
#[Retry]
#[RateLimited]
#[CacheResult]
```

Los atributos específicos serán convertidos a metadata.

---

## 36. Atributo genérico

```php
#[Attribute(
    Attribute::TARGET_CLASS
    | Attribute::TARGET_METHOD
    | Attribute::IS_REPEATABLE
)]
final readonly class Intercept implements
    MetadataAttributeInterface
{
    public function __construct(
        public string $interceptor,
        public array $arguments = [],
        public int $priority = 0,
        public ?string $phase = null,
        public array $before = [],
        public array $after = [],
    ) {
    }

    public function metadataKey(): string
    {
        return 'controller.interceptors';
    }

    public function metadataValue(): mixed
    {
        return new InterceptorDefinition(
            interceptor: $this->interceptor,
            arguments: $this->arguments,
            priority: $this->priority,
            before: $this->before,
            after: $this->after,
            phase: $this->phase !== null
                ? InterceptorPhase::from($this->phase)
                : InterceptorPhase::Around,
        );
    }

    public function metadataScopes(): array
    {
        return ['controller'];
    }
}
```

---

## 37. Atributos semánticos

Los atributos semánticos serán preferibles para capacidades comunes.

Ejemplo:

```php
#[Transactional(
    connection: 'default',
    retry: 3,
)]
```

se convertirá internamente en:

```php
new InterceptorDefinition(
    interceptor: TransactionInterceptor::class,
    arguments: [
        'connection' => 'default',
        'retry' => 3,
    ],
);
```

El controlador no necesitará conocer la clase concreta.

---

## 38. Grupos de interceptores

Se podrán registrar grupos.

```php
$groups->register(
    'write-operation',
    [
        'authorization',
        'validation',
        'transaction',
        'audit',
        'metrics',
    ]
);
```

Uso:

```php
#[InterceptorGroup('write-operation')]
public function store(CreateUserData $data): User
{
}
```

---

## 39. Contrato del registry de grupos

```php
interface InterceptorGroupRegistryInterface
{
    public function register(
        string $name,
        array $definitions
    ): void;

    public function has(string $name): bool;

    public function get(string $name): array;

    public function remove(string $name): void;

    public function all(): array;

    public function freeze(): void;
}
```

---

## 40. Grupos parametrizables

```php
$groups->registerFactory(
    'transactional-write',
    static function (array $arguments): array {
        return [
            new InterceptorDefinition(
                interceptor: AuthorizationInterceptor::class
            ),
            new InterceptorDefinition(
                interceptor: TransactionInterceptor::class,
                arguments: [
                    'connection' =>
                        $arguments['connection'] ?? 'default',
                ],
            ),
            new InterceptorDefinition(
                interceptor: AuditInterceptor::class,
                arguments: [
                    'event' => $arguments['audit_event'] ?? null,
                ],
            ),
        ];
    }
);
```

En modo compilado no se permitirán factories arbitrarias no registradas.

---

## 41. Exclusiones

Un controlador o método podrá excluir interceptores heredados.

```php
#[WithoutInterceptor(CacheResultInterceptor::class)]
public function fresh(): array
{
}
```

La exclusión deberá representarse explícitamente:

```php
final readonly class InterceptorExclusion
{
    public function __construct(
        public string $target,
        public InterceptorExclusionMode $mode,
        public ?InterceptorSource $source = null,
    ) {
    }
}
```

---

## 42. Modos de exclusión

```php
enum InterceptorExclusionMode: string
{
    case Exact = 'exact';
    case Alias = 'alias';
    case Tag = 'tag';
    case Group = 'group';
    case All = 'all';
}
```

Ejemplos:

```php
new InterceptorExclusion(
    target: TransactionInterceptor::class,
    mode: InterceptorExclusionMode::Exact,
);
```

```php
new InterceptorExclusion(
    target: 'audit',
    mode: InterceptorExclusionMode::Alias,
);
```

```php
new InterceptorExclusion(
    target: 'observability',
    mode: InterceptorExclusionMode::Tag,
);
```

---

## 43. Interceptores protegidos

Algunos interceptores no podrán excluirse desde niveles inferiores.

Ejemplos:

* Tenant isolation.
* Compliance audit.
* Security policy.
* Enterprise authorization.
* Mandatory tracing.

Descriptor:

```php
public bool $removable = true;
```

Intentar eliminar uno protegido lanzará:

```text
ProtectedInterceptorRemovalException
```

---

## 44. ControllerInterceptorResolver

```php
interface ControllerInterceptorResolverInterface
{
    public function resolve(
        ControllerExecution $execution
    ): ControllerInterceptorPlan;
}
```

Responsabilidades:

* Leer metadata.
* Incorporar interceptores globales.
* Expandir grupos.
* Resolver aliases.
* Aplicar exclusiones.
* Normalizar definiciones.
* Consultar el registry.
* Validar scopes.
* Construir el plan.
* Utilizar plan compilado cuando exista.

---

## 45. Implementación conceptual

```php
final class ControllerInterceptorResolver implements
    ControllerInterceptorResolverInterface
{
    public function __construct(
        private readonly ControllerInterceptorRegistryInterface $registry,
        private readonly InterceptorGroupRegistryInterface $groups,
        private readonly InterceptorPlanBuilderInterface $builder,
        private readonly ControllerInterceptorCacheInterface $cache,
    ) {
    }

    public function resolve(
        ControllerExecution $execution
    ): ControllerInterceptorPlan {
        $cacheKey = InterceptorPlanCacheKey::fromExecution(
            $execution
        );

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $metadata = $execution->metadata;

        $definitions = $metadata->get(
            'controller.interceptors',
            []
        );

        $groups = $metadata->get(
            'controller.interceptor_groups',
            []
        );

        $exclusions = $metadata->get(
            'controller.interceptor_exclusions',
            []
        );

        $plan = $this->builder->build(
            execution: $execution,
            definitions: $definitions,
            groups: $groups,
            exclusions: $exclusions,
        );

        if ($plan->isStatic()) {
            $this->cache->put($cacheKey, $plan);
        }

        return $plan;
    }
}
```

---

## 46. InterceptorPlanBuilder

```php
interface InterceptorPlanBuilderInterface
{
    public function build(
        ControllerExecution $execution,
        array $definitions,
        array $groups = [],
        array $exclusions = []
    ): ControllerInterceptorPlan;
}
```

---

## 47. Pasos del builder

```text
Collect definitions
    ↓
Expand groups
    ↓
Resolve aliases
    ↓
Load descriptors
    ↓
Apply defaults
    ↓
Normalize arguments
    ↓
Apply exclusions
    ↓
Evaluate static conditions
    ↓
Deduplicate
    ↓
Validate repeatability
    ↓
Build dependency graph
    ↓
Sort by phase
    ↓
Sort by priority
    ↓
Apply before/after constraints
    ↓
Validate scopes
    ↓
Validate terminal behavior
    ↓
Produce plan
```

---

## 48. ControllerInterceptorPlan

```php
final readonly class ControllerInterceptorPlan
{
    /**
     * @param list<ResolvedInterceptorDefinition> $interceptors
     */
    public function __construct(
        public string $controllerIdentity,
        public array $interceptors,
        public string $signature,
        public bool $compiled = false,
        public bool $static = true,
        public array $dynamicConditions = [],
        public array $sources = [],
        public array $metadata = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->interceptors === [];
    }

    public function isStatic(): bool
    {
        return $this->static;
    }
}
```

---

## 49. ResolvedInterceptorDefinition

```php
final readonly class ResolvedInterceptorDefinition
{
    public function __construct(
        public InterceptorDescriptor $descriptor,
        public array $arguments,
        public int $priority,
        public InterceptorPhase $phase,
        public array $conditions,
        public InterceptorSource $source,
        public int $position,
        public bool $dynamic = false,
    ) {
    }
}
```

---

## 50. Deduplicación

Por defecto, un interceptor no repeatable aparecerá una sola vez.

Ejemplo:

```text
Global:
    transaction

Controller:
    transaction

Method:
    transaction(connection=reporting)
```

La definición más específica podrá:

* Reemplazar.
* Fusionar argumentos.
* Generar conflicto.

La política dependerá del descriptor.

---

## 51. InterceptorMergePolicy

```php
enum InterceptorMergePolicy: string
{
    case Replace = 'replace';
    case MergeArguments = 'merge_arguments';
    case KeepFirst = 'keep_first';
    case KeepLast = 'keep_last';
    case RejectDuplicate = 'reject_duplicate';
    case AllowRepeat = 'allow_repeat';
}
```

El descriptor podrá declarar:

```php
public InterceptorMergePolicy $mergePolicy;
```

---

## 52. Ejemplo de fusión

Definición global:

```php
TransactionInterceptor::class => [
    'connection' => 'default',
    'retry' => 0,
]
```

Definición del método:

```php
TransactionInterceptor::class => [
    'retry' => 3,
]
```

Con `MergeArguments`:

```php
[
    'connection' => 'default',
    'retry' => 3,
]
```

---

## 53. InterceptorCondition

```php
interface InterceptorConditionInterface
{
    public function matches(
        ControllerExecution $execution,
        ResolvedInterceptorDefinition $definition
    ): bool;

    public function isStatic(): bool;
}
```

---

## 54. Condiciones iniciales

```text
EnvironmentInterceptorCondition
HttpMethodInterceptorCondition
RouteNameInterceptorCondition
ControllerTypeInterceptorCondition
MetadataValueInterceptorCondition
TenantInterceptorCondition
FeatureFlagInterceptorCondition
ArgumentInterceptorCondition
ResultTypeInterceptorCondition
ExceptionTypeInterceptorCondition
```

---

## 55. Condiciones estáticas y dinámicas

### Estática

Puede evaluarse al construir o compilar el plan.

Ejemplos:

* Entorno.
* Nombre de controlador.
* Nombre del método.
* Metadata fija.
* Ruta compilada.

### Dinámica

Debe evaluarse durante cada ejecución.

Ejemplos:

* Tenant actual.
* Usuario actual.
* Valor de argumentos.
* Feature flag runtime.
* Tipo del resultado.
* Excepción producida.

Los planes con condiciones dinámicas podrán cachearse parcialmente, pero no como secuencia final absoluta.

---

## 56. ControllerInterceptorPipeline

```php
interface ControllerInterceptorPipelineInterface
{
    public function execute(
        ControllerExecution $execution,
        ControllerInterceptorPlan $plan,
        ControllerInvocationTerminalInterface $terminal
    ): mixed;
}
```

---

## 57. Implementación conceptual

```php
final class ControllerInterceptorPipeline implements
    ControllerInterceptorPipelineInterface
{
    public function __construct(
        private readonly InterceptorInstanceResolverInterface $instances
    ) {
    }

    public function execute(
        ControllerExecution $execution,
        ControllerInterceptorPlan $plan,
        ControllerInvocationTerminalInterface $terminal
    ): mixed {
        $chain = new ControllerInterceptorChain(
            execution: $execution,
            definitions: $plan->interceptors,
            instances: $this->instances,
            terminal: $terminal,
        );

        return $chain->proceed($execution);
    }
}
```

---

## 58. ControllerInterceptorChain

```php
final class ControllerInterceptorChain implements
    ControllerInterceptorChainInterface
{
    private int $index = 0;

    public function __construct(
        private readonly array $definitions,
        private readonly InterceptorInstanceResolverInterface $instances,
        private readonly ControllerInvocationTerminalInterface $terminal,
    ) {
    }

    public function proceed(
        ControllerExecution $execution
    ): mixed {
        if (! isset($this->definitions[$this->index])) {
            return $this->terminal->invoke($execution);
        }

        $definition = $this->definitions[
            $this->index++
        ];

        if (! $this->matches(
            $execution,
            $definition
        )) {
            return $this->proceed($execution);
        }

        $interceptor = $this->instances->resolve(
            $definition,
            $execution
        );

        return $interceptor->intercept(
            $execution,
            $this
        );
    }
}
```

---

## 59. Reutilización de la cadena

Por defecto, cada cadena será de un solo uso.

No deberá reutilizarse entre:

* Requests.
* Controladores.
* Retries independientes.
* Invocaciones concurrentes.

Un interceptor de retry no deberá reiniciar el mismo índice mutable de forma insegura.

---

## 60. Fork de cadena

Para soportar retries se definirá:

```php
interface ForkableControllerInterceptorChainInterface extends
    ControllerInterceptorChainInterface
{
    public function forkFromCurrent(): self;

    public function restartFromTerminal(): self;
}
```

El interceptor de retry podrá crear una cadena segura.

---

## 61. Retry semantics

```php
final class RetryInterceptor implements
    ControllerInterceptorInterface
{
    public function intercept(
        ControllerExecution $execution,
        ControllerInterceptorChainInterface $chain
    ): mixed {
        $attempts = 0;
        $retryChain = $chain->forkFromCurrent();

        beginning:

        try {
            return $retryChain
                ->forkFromCurrent()
                ->proceed($execution);
        } catch (Throwable $exception) {
            $attempts++;

            if (! $this->shouldRetry(
                $exception,
                $attempts
            )) {
                throw $exception;
            }

            goto beginning;
        }
    }
}
```

La implementación real evitará `goto`; el ejemplo representa la semántica.

---

## 62. Reglas de retry

Un retry no deberá repetir indiscriminadamente:

* Auditoría already committed.
* Side effects externos.
* Envío de correo.
* Publicación de eventos.
* Escrituras no idempotentes.
* Respuestas streaming.
* Transacciones parcialmente confirmadas.

El orden deberá asegurar que:

```text
Retry
    envuelva
Transaction
    envuelva
Controller
```

cuando se necesite una transacción nueva por intento.

---

## 63. ControllerInvocationTerminal

```php
interface ControllerInvocationTerminalInterface
{
    public function invoke(
        ControllerExecution $execution
    ): mixed;
}
```

Implementación:

```php
final class ControllerInvocationTerminal implements
    ControllerInvocationTerminalInterface
{
    public function __construct(
        private readonly ControllerInvokerInterface $invoker
    ) {
    }

    public function invoke(
        ControllerExecution $execution
    ): mixed {
        return $this->invoker->invoke(
            controller: $execution->resolvedController,
            parameters: $execution->parameters,
            execution: $execution,
        );
    }
}
```

---

## 64. Relación con InvokeControllerStage

Existen dos opciones arquitectónicas.

### Opción A

`ControllerInterceptorsStage` ejecuta todo el pipeline y el terminal usa directamente `ControllerInvoker`.

En ese caso `InvokeControllerStage` deja de ser una etapa independiente.

### Opción B

`InvokeControllerStage` se representa como terminal interno del pipeline.

VoltStack utilizará conceptualmente la opción B, pero podrá implementar el invoker como terminal para evitar doble recorrido.

Pipeline lógico:

```text
ControllerInterceptorsStage
    ↓
Interceptor pipeline
    ↓
InvokeControllerTerminal
```

El nombre `InvokeControllerStage` se conservará en trazabilidad y documentación aunque su ejecución sea terminal.

---

## 65. Short circuit

Un interceptor puede no llamar:

```php
$chain->proceed($execution)
```

y devolver un resultado directo.

Ejemplo:

```php
final class CacheResultInterceptor implements
    ControllerInterceptorInterface
{
    public function intercept(
        ControllerExecution $execution,
        ControllerInterceptorChainInterface $chain
    ): mixed {
        $key = $this->keyFactory->make($execution);

        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }

        $result = $chain->proceed($execution);

        $this->cache->put(
            $key,
            $result,
            $this->ttl
        );

        return $result;
    }
}
```

En cache hit, el controlador no será invocado.

---

## 66. Short circuit explícito

Para mejorar observabilidad podrá utilizarse:

```php
final readonly class InterceptorShortCircuit
{
    public function __construct(
        public mixed $result,
        public string $reason,
        public string $interceptor,
        public array $metadata = [],
    ) {
    }
}
```

Sin embargo, el pipeline no deberá obligar a devolver este wrapper.

El interceptor podrá registrar:

```php
$execution->attributes->set(
    'interceptor.short_circuit',
    new InterceptorShortCircuit(...)
);
```

y devolver el resultado normal.

---

## 67. Interceptores terminales

Un interceptor marcado como terminal declara que puede finalizar la cadena de manera normal.

Ejemplos:

* Cache lookup.
* Idempotency replay.
* Feature disabled.
* Maintenance response.
* Mock en testing.

El plan builder podrá advertir si un interceptor inesperadamente terminal aparece en una fase incorrecta.

---

## 68. Transformación de argumentos

Un interceptor podrá modificar argumentos antes de la invocación.

```php
final class ArgumentTransformationInterceptor implements
    ControllerInterceptorInterface
{
    public function intercept(
        ControllerExecution $execution,
        ControllerInterceptorChainInterface $chain
    ): mixed {
        $execution->parameters = $this->transformer->transform(
            $execution->parameters,
            $execution
        );

        return $chain->proceed($execution);
    }
}
```

Reglas:

* Mantener orden posicional.
* Mantener definición original.
* Registrar transformaciones.
* Validar tipos antes de invocar.
* No eliminar argumentos requeridos silenciosamente.
* No alterar parámetros sensibles sin trace segura.

---

## 69. Transformación de resultado

```php
final class ResultEnvelopeInterceptor implements
    ControllerInterceptorInterface
{
    public function intercept(
        ControllerExecution $execution,
        ControllerInterceptorChainInterface $chain
    ): mixed {
        $result = $chain->proceed($execution);

        return new ApiEnvelope(
            data: $result,
            metadata: [
                'request_id' =>
                    $execution->context->requestId,
            ],
        );
    }
}
```

Esta transformación ocurre antes del `Result Normalization System`.

---

## 70. Excepciones

Un interceptor puede capturar excepciones.

```php
final class DomainExceptionInterceptor implements
    ControllerInterceptorInterface
{
    public function intercept(
        ControllerExecution $execution,
        ControllerInterceptorChainInterface $chain
    ): mixed {
        try {
            return $chain->proceed($execution);
        } catch (DomainException $exception) {
            throw ControllerDomainException::from(
                $exception,
                $execution
            );
        }
    }
}
```

No deberá convertir todas las excepciones de manera indiscriminada.

El sistema global de excepciones seguirá siendo responsable del mapping final.

---

## 71. Registro de excepción

Cuando una excepción atraviese el pipeline:

```php
$execution->exception = $exception;
```

El dispatcher o pipeline de ejecución deberá garantizar que:

* La excepción sea registrada.
* Los interceptores `finally` se ejecuten.
* El finalizer libere recursos.
* La excepción se propague al exception system.

---

## 72. Interceptores de error

Un interceptor podrá ejecutar lógica solamente al fallar.

```php
final class AuditFailureInterceptor implements
    ControllerInterceptorInterface
{
    public function intercept(
        ControllerExecution $execution,
        ControllerInterceptorChainInterface $chain
    ): mixed {
        try {
            return $chain->proceed($execution);
        } catch (Throwable $exception) {
            $this->audit->failure(
                $execution,
                $exception
            );

            throw $exception;
        }
    }
}
```

La fase `Error` es organizativa; la semántica se implementa mediante `try/catch`.

---

## 73. Finalización

Un interceptor que adquiere recursos debe liberarlos en `finally`.

```php
final class LockInterceptor implements
    ControllerInterceptorInterface
{
    public function intercept(
        ControllerExecution $execution,
        ControllerInterceptorChainInterface $chain
    ): mixed {
        $lock = $this->locks->acquire(
            $this->key($execution)
        );

        try {
            return $chain->proceed($execution);
        } finally {
            $lock->release();
        }
    }
}
```

El `ControllerExecutionFinalizer` seguirá actuando como última barrera de limpieza.

---

## 74. InterceptorInstanceResolver

```php
interface InterceptorInstanceResolverInterface
{
    public function resolve(
        ResolvedInterceptorDefinition $definition,
        ControllerExecution $execution
    ): ControllerInterceptorInterface;

    public function release(
        ControllerInterceptorInterface $interceptor,
        ResolvedInterceptorDefinition $definition
    ): void;
}
```

---

## 75. Resolución mediante Container

La implementación utilizará el Container.

```php
final class InterceptorInstanceResolver implements
    InterceptorInstanceResolverInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ScopedInstanceStoreInterface $scopes,
    ) {
    }

    public function resolve(
        ResolvedInterceptorDefinition $definition,
        ControllerExecution $execution
    ): ControllerInterceptorInterface {
        return $this->scopes->resolve(
            descriptor: $definition->descriptor,
            execution: $execution,
            factory: fn () => $this->container->make(
                $definition->descriptor->interceptor,
                $definition->arguments
            ),
        );
    }
}
```

---

## 76. Argumentos de constructor y configuración

Los argumentos de definición no deberán pasarse ciegamente al constructor.

Se podrán utilizar dos mecanismos:

### Constructor injection

Para dependencias registradas.

```php
public function __construct(
    TransactionManagerInterface $transactions
)
```

### Runtime configuration

Para valores declarativos.

```php
interface ConfigurableControllerInterceptorInterface
{
    public function configure(
        array $arguments,
        ControllerExecution $execution
    ): void;
}
```

Se prefiere crear un objeto de configuración tipado.

---

## 77. Interceptor Factory

```php
interface ControllerInterceptorFactoryInterface
{
    public function create(
        ResolvedInterceptorDefinition $definition,
        ControllerExecution $execution
    ): ControllerInterceptorInterface;
}
```

Cada interceptor complejo podrá registrar una factory especializada.

Ejemplo:

```text
TransactionInterceptorFactory
CacheResultInterceptorFactory
RateLimitInterceptorFactory
```

---

## 78. Configuración tipada

Ejemplo:

```php
final readonly class TransactionInterceptorConfig
{
    public function __construct(
        public string $connection,
        public int $retry,
        public bool $rollbackOnThrowable,
    ) {
    }
}
```

Factory:

```php
$config = $this->hydrator->hydrate(
    TransactionInterceptorConfig::class,
    $definition->arguments
);
```

Esto evita arrays sin contrato dentro del interceptor.

---

## 79. Validación de configuración

Durante build o compilación se deberá validar:

* Argumentos desconocidos.
* Tipos inválidos.
* Valores requeridos.
* Enums.
* Dependencias.
* Clases.
* Configuración incompatible.
* Valores no compilables.

Error:

```text
InvalidInterceptorConfigurationException
```

---

## 80. Interceptores globales iniciales

El framework podrá registrar:

```text
ControllerTracingInterceptor
ControllerMetricsInterceptor
ControllerTimeoutInterceptor
ControllerContextSafetyInterceptor
```

No todos deberán estar habilitados por defecto.

---

## 81. AuthorizationInterceptor

Aunque exista `AuthorizationStage`, VoltStack deberá decidir dónde reside la ejecución final.

La recomendación es:

* `AuthorizationStage` coordina la autorización estándar.
* `AuthorizationInterceptor` permite autorización alrededor de casos especiales.
* En V1 se evitará ejecutar ambos para la misma política.

La metadata determinará el mecanismo.

---

## 82. Separación recomendada

```text
AuthorizationStage
    autorización obligatoria y estándar

AuthorizationInterceptor
    autorización contextual, reusable o alrededor de ejecución
```

Ejemplos de interceptor:

* Revalidación después de cargar un recurso.
* Autorización dependiente de parámetros transformados.
* Autorización temporal.
* Delegación multi-tenant.
* Elevación controlada de privilegios.

---

## 83. ValidationInterceptor

El `ValidationStage` ejecutará validación declarativa estándar antes del pipeline.

El interceptor podrá servir para:

* Validación contextual.
* Validación post-hidratación.
* Validación de invariantes.
* Validación dependiente de servicios.
* Validación alrededor de retry.
* Validación del resultado.

Debe evitarse duplicar reglas.

---

## 84. TransactionInterceptor

Responsabilidades:

* Seleccionar conexión.
* Abrir transacción.
* Invocar la cadena.
* Confirmar en éxito.
* Hacer rollback en excepción.
* Manejar nested transactions.
* Registrar timings.
* Integrarse con events after commit.
* Evitar transacciones en streaming.

Contrato conceptual:

```php
final class TransactionInterceptor implements
    ControllerInterceptorInterface
{
    public function intercept(
        ControllerExecution $execution,
        ControllerInterceptorChainInterface $chain
    ): mixed {
        return $this->transactions->run(
            connection: $this->config->connection,
            callback: fn () =>
                $chain->proceed($execution),
            attempts: $this->config->retry,
        );
    }
}
```

---

## 85. Nested transactions

Políticas:

```php
enum NestedTransactionPolicy: string
{
    case Join = 'join';
    case Savepoint = 'savepoint';
    case RequiresNew = 'requires_new';
    case Reject = 'reject';
}
```

La metadata podrá indicar:

```text
transaction.nested_policy
```

---

## 86. IdempotencyInterceptor

Responsabilidades:

1. Obtener clave idempotente.
2. Reservar la ejecución.
3. Detectar request repetido.
4. Reproducir resultado anterior.
5. Evitar ejecución duplicada.
6. Guardar resultado exitoso.
7. Liberar reserva en error según política.

Flujo:

```text
Read idempotency key
    ↓
Existing completed record?
    ├── yes → return stored result
    └── no
         ↓
Acquire execution lock
         ↓
Invoke chain
         ↓
Persist result
         ↓
Release lock
```

---

## 87. Restricciones de idempotencia

No deberá guardar:

* Recursos PHP.
* Streams abiertos.
* Objetos no serializables.
* Referencias request-scoped.
* Respuestas con secretos.
* Resultados marcados como no replayable.

El resultado deberá pasar por un serializer controlado.

---

## 88. AuditInterceptor

El interceptor de auditoría registrará:

* Actor.
* Tenant.
* Operación.
* Controlador.
* Método.
* Recurso.
* Resultado.
* Estado.
* Duración.
* Correlation ID.
* Cambios relevantes.

No deberá registrar automáticamente:

* Passwords.
* Tokens.
* Datos sensibles.
* Request completo.
* Objetos arbitrarios.

Utilizará metadata de redacción.

---

## 89. CacheResultInterceptor

Responsabilidades:

* Construir clave.
* Aplicar tags.
* Detectar hit.
* Ejecutar short circuit.
* Guardar resultado.
* Respetar TTL.
* Aplicar invalidación.
* Aislar tenant.
* Evitar cachear excepciones salvo configuración explícita.

---

## 90. Posición de caché

La posición es crítica.

Ejemplo:

```text
Authorization
    ↓
Cache
    ↓
Controller
```

evita servir datos sin autorización.

Para datos públicos:

```text
Cache
    ↓
Controller
```

puede ser válido.

El plan builder podrá validar políticas inseguras.

---

## 91. RateLimitInterceptor

A diferencia del rate limiter HTTP global, este interceptor podrá limitar por:

* Controlador.
* Método.
* Usuario.
* Tenant.
* Recurso.
* Argumento.
* Operación de dominio.
* Cost weight.

Ejemplo:

```php
#[RateLimited(
    key: 'tenant:{tenant.id}:report:{report.id}',
    limit: 10,
    window: '1 minute',
    weight: 2,
)]
```

---

## 92. TimeoutInterceptor

El timeout lógico podrá:

* Establecer deadline.
* Propagar cancellation token.
* Verificar tiempo restante.
* Cancelar operaciones cooperativas.
* Lanzar excepción.

PHP no puede interrumpir de forma segura cualquier operación arbitraria; por tanto, el timeout será principalmente cooperativo.

---

## 93. CircuitBreakerInterceptor

Útil cuando el controlador o Action invoca servicios externos.

Estados:

```text
Closed
Open
HalfOpen
```

La clave del circuit breaker deberá basarse en una dependencia estable, no únicamente en el método del controlador.

---

## 94. MetricsInterceptor

Medirá:

* Duración total.
* Duración del controlador.
* Resultado.
* Excepción.
* Short circuit.
* Retry count.
* Cache hit.
* Transaction duration.
* Interceptor duration.

No deberá utilizar valores de alta cardinalidad como etiquetas.

---

## 95. TracingInterceptor

Creará un span:

```text
controller.execute
```

Atributos:

```text
controller.class
controller.method
route.name
tenant.present
interceptor.count
compiled.plan
short_circuit
```

No incluirá:

* Parámetros sensibles.
* Query completo.
* Body completo.
* IDs arbitrarios como tags no controlados.

---

## 96. EventInterceptor

Podrá emitir:

```text
ControllerExecuting
ControllerExecuted
ControllerFailed
```

Pero deberá evitar duplicación con los eventos nativos del dispatcher.

Recomendación:

* Eventos de lifecycle generales: dispatcher.
* Eventos específicos de una operación: interceptor.
* Eventos de dominio: dominio o Action.

---

## 97. FeatureFlagInterceptor

Podrá:

* Rechazar operación.
* Redirigir a implementación alterna.
* Devolver fallback.
* Cambiar argumentos.
* Aplicar otro interceptor group.

Las decisiones runtime no deberán contaminar el plan global.

---

## 98. TenantIsolationInterceptor

Responsabilidades:

* Confirmar tenant activo.
* Validar que argumentos pertenecen al tenant.
* Establecer contexto de persistencia.
* Evitar cross-tenant access.
* Limpiar contexto en `finally`.

Este interceptor podrá marcarse como protegido.

---

## 99. LockInterceptor

Tipos de lock:

```text
Request lock
Resource lock
Tenant lock
Distributed lock
Database advisory lock
```

La clave podrá construirse desde parámetros ya resueltos.

El lock debe adquirirse después de autorización y antes de mutaciones.

---

## 100. Interceptor de eventos after commit

Cuando se use transacción:

```text
TransactionInterceptor
    ↓
Controller
    ↓
Collect events
    ↓
Commit
    ↓
Publish after commit
```

Los eventos no deberán publicarse antes de confirmar la transacción, salvo declaración explícita.

---

## 101. Orden recomendado de interceptores

Ejemplo para escritura:

```text
TenantIsolationInterceptor

↓

AuthorizationInterceptor

↓

RateLimitInterceptor

↓

IdempotencyInterceptor

↓

InputSanitizationInterceptor

↓

BusinessValidationInterceptor

↓

ResourceLockInterceptor

↓

RetryInterceptor

↓

TransactionInterceptor

↓

AuditInterceptor

↓

MetricsInterceptor

↓

TracingInterceptor

↓

Controller Invocation

↓

DomainEventsInterceptor

↓

ResultTransformationInterceptor
```

El orden real dependerá de dependencias.

---

## 102. Interacciones críticas

### Retry y Transaction

Recomendado:

```text
Retry
    ↓
Transaction
    ↓
Controller
```

Cada intento obtiene una transacción nueva.

### Audit y Transaction

Depende del objetivo:

```text
Audit dentro de Transaction
```

si el audit debe revertirse.

```text
Audit fuera de Transaction
```

si debe registrar también fallos.

### Cache y Authorization

Autorización debe ejecutarse primero cuando el resultado depende del usuario.

### Idempotency y Rate Limit

Puede convenir:

```text
Rate Limit
    ↓
Idempotency
```

o permitir que replays idempotentes no consuman cuota.

La política deberá ser configurable.

---

## 103. Validación del plan

El sistema deberá detectar:

* Cache antes de autorización sensible.
* Transaction alrededor de streaming.
* Retry alrededor de side effects no idempotentes.
* Singleton interceptor con dependencias request-scoped.
* Interceptor duplicado no repeatable.
* Dependencias circulares.
* Interceptor desconocido.
* Alias ambiguo.
* Configuración inválida.
* Interceptor protegido excluido.
* Terminal interceptor en orden inseguro.
* Scope incompatible.
* Interceptor no compilable en modo estricto.

---

## 104. InterceptorPlanValidator

```php
interface InterceptorPlanValidatorInterface
{
    public function validate(
        ControllerInterceptorPlan $plan,
        ControllerExecution $execution
    ): void;
}
```

Validadores iniciales:

```text
UnknownInterceptorValidator
InterceptorConfigurationValidator
InterceptorScopeValidator
InterceptorDependencyValidator
InterceptorCycleValidator
InterceptorDuplicateValidator
ProtectedInterceptorValidator
TransactionStreamingValidator
CacheAuthorizationOrderValidator
RetryIdempotencyValidator
CompiledInterceptorValidator
```

---

## 105. Compilación

El sistema permitirá compilar planes de interceptores.

```php
interface ControllerInterceptorCompilerInterface
{
    public function compile(
        ControllerExecutionDefinition $definition
    ): CompiledControllerInterceptorPlan;
}
```

---

## 106. CompiledControllerInterceptorPlan

```php
final readonly class CompiledControllerInterceptorPlan
{
    public function __construct(
        public string $controllerIdentity,
        public array $definitions,
        public array $dynamicConditions,
        public string $metadataHash,
        public string $registryHash,
        public string $sourceHash,
        public string $frameworkVersion,
    ) {
    }
}
```

---

## 107. Formato compilado

```php
<?php

return [
    'controller' =>
        'App\Http\Controllers\UserController::store',

    'interceptors' => [
        [
            'id' => 'tenant_isolation',
            'class' =>
                TenantIsolationInterceptor::class,
            'scope' => 'execution',
            'phase' => 'guard',
            'priority' => 9000,
            'arguments' => [],
        ],
        [
            'id' => 'transaction',
            'class' =>
                TransactionInterceptor::class,
            'scope' => 'execution',
            'phase' => 'around',
            'priority' => 5500,
            'arguments' => [
                'connection' => 'default',
                'retry' => 0,
            ],
        ],
    ],

    'dynamic_conditions' => [],

    'metadata_hash' => '...',
    'registry_hash' => '...',
    'source_hash' => '...',
];
```

---

## 108. Beneficios de compilación

En producción podrá evitarse:

* Reflection de atributos.
* Expansión de grupos.
* Resolución de aliases.
* Fusión repetida.
* Ordenamiento.
* Construcción del grafo.
* Validación estructural.
* Normalización de argumentos.
* Detección de duplicados.

Solo quedará:

* Cargar plan.
* Evaluar condiciones dinámicas.
* Resolver instancias.
* Ejecutar cadena.

---

## 109. Plan parcial

Un plan podrá contener:

```php
[
    'static_interceptors' => [...],

    'conditional_interceptors' => [
        [
            'definition' => [...],
            'condition' => [
                'resolver' =>
                    FeatureFlagConditionResolver::class,
                'arguments' => [
                    'flag' => 'new-user-flow',
                ],
            ],
        ],
    ],
]
```

No se compilarán closures arbitrarias.

---

## 110. Cache

Niveles:

```text
L1 Execution Cache
L2 Request Cache
L3 Worker Plan Cache
L4 Compiled PHP Plan
```

### L1

El plan ya resuelto dentro de `ControllerExecution`.

### L2

Evita resolver el mismo controlador varias veces en una petición.

### L3

Mantiene planes estáticos e inmutables en workers.

### L4

Archivo PHP compatible con OPcache.

---

## 111. Reglas de cache

Puede cachearse globalmente:

* Definiciones.
* Descriptores.
* Planes estáticos.
* Configuración normalizada.
* Grafos de orden.
* Hashes.

No puede cachearse globalmente:

* Instancias execution-scoped.
* Request.
* Usuario.
* Tenant.
* Argumentos resueltos.
* Resultados.
* Excepciones.
* Locks.
* Transacciones.
* Spans.

---

## 112. InterceptorPlanCacheKey

```php
final readonly class InterceptorPlanCacheKey
{
    public function __construct(
        public string $value
    ) {
    }

    public static function fromExecution(
        ControllerExecution $execution
    ): self {
        return new self(
            hash('xxh128', implode('|', [
                $execution->resolvedController->displayName,
                $execution->metadata->signature(),
                $execution->context->route?->name ?? '',
            ]))
        );
    }
}
```

No incluirá valores request-scoped si el plan no depende de ellos.

---

## 113. Invalidación

El plan se invalidará cuando cambie:

* Metadata.
* Atributos.
* Configuración.
* Ruta.
* Grupos.
* Registry.
* Alias.
* Descriptor.
* Prioridad.
* Fases.
* Dependencias.
* Argument schema.
* Clase del interceptor.
* Versión del framework.
* Paquete proveedor.

---

## 114. Compatibilidad con FrankenPHP

El sistema deberá:

* No compartir cadenas entre requests.
* No compartir instancias execution-scoped.
* Limpiar scopes al finalizar.
* Evitar interceptores singleton mutables.
* Validar dependencias request-scoped.
* No conservar ControllerExecution.
* No conservar Request.
* No conservar parámetros.
* No conservar resultado.
* Liberar locks y transacciones.
* Finalizar spans.
* Limpiar stores request-scoped.
* Congelar registries en producción.

---

## 115. InterceptorLifecycleManager

```php
interface InterceptorLifecycleManagerInterface
{
    public function starting(
        ControllerExecution $execution,
        ControllerInterceptorPlan $plan
    ): void;

    public function interceptorResolved(
        ControllerInterceptorInterface $interceptor,
        ResolvedInterceptorDefinition $definition
    ): void;

    public function interceptorCompleted(
        ControllerInterceptorInterface $interceptor,
        ResolvedInterceptorDefinition $definition
    ): void;

    public function complete(
        ControllerExecution $execution
    ): void;

    public function reset(): void;
}
```

Será responsable de liberar instancias y recursos temporales.

---

## 116. Finalización segura

El dispatcher deberá usar:

```php
try {
    return $pipeline->execute(...);
} finally {
    $lifecycle->complete($execution);
}
```

El `ControllerExecutionFinalizer` será la última defensa.

---

## 117. Observabilidad

Métricas:

```text
controller.interceptors.resolve.total
controller.interceptors.resolve.duration
controller.interceptors.plan.cache.hit
controller.interceptors.plan.cache.miss
controller.interceptor.execute.total
controller.interceptor.execute.duration
controller.interceptor.failure.total
controller.interceptor.short_circuit.total
controller.interceptor.retry.total
controller.interceptor.condition.skipped
controller.interceptor.instance.created
controller.interceptor.instance.reused
```

Tags recomendados:

```text
interceptor.id
interceptor.phase
interceptor.scope
controller.type
compiled
short_circuit
outcome
```

Evitar:

* User ID.
* Tenant ID.
* Route params.
* Valores de argumentos.
* Exception messages como tag.

---

## 118. Eventos

```text
ControllerInterceptorsResolving
ControllerInterceptorsResolved
ControllerInterceptorPlanBuilt
ControllerInterceptorPlanCacheHit
ControllerInterceptorPlanCacheMiss
ControllerInterceptorStarting
ControllerInterceptorCompleted
ControllerInterceptorSkipped
ControllerInterceptorShortCircuited
ControllerInterceptorFailed
ControllerInterceptorsCompleted
ControllerInterceptorPlanCompiled
ControllerInterceptorPlanInvalidated
```

Los eventos por interceptor podrán desactivarse en producción.

---

## 119. InterceptorExecutionRecord

```php
final readonly class InterceptorExecutionRecord
{
    public function __construct(
        public string $interceptor,
        public InterceptorPhase $phase,
        public int $position,
        public int $startedAt,
        public int $finishedAt,
        public string $outcome,
        public bool $shortCircuited,
        public ?string $exceptionType = null,
        public array $metadata = [],
    ) {
    }
}
```

Podrá almacenarse en:

```php
$execution->timings
```

o en un recorder especializado.

---

## 120. Trace de ejecución

Ejemplo:

```text
Controller:
UserController::store

Interceptor plan:
1. tenant_isolation
2. authorization
3. rate_limit
4. transaction
5. audit
6. metrics

Execution:

tenant_isolation
    completed
    0.12 ms

authorization
    completed
    0.45 ms

rate_limit
    completed
    0.20 ms

transaction
    completed
    4.21 ms

audit
    completed
    1.15 ms

metrics
    completed
    5.92 ms

controller
    completed
    3.31 ms
```

---

## 121. Debugging del orden

La CLI podrá mostrar:

```bash
php volt controller:interceptors \
    App\\Http\\Controllers\\UserController@store
```

Salida:

```text
Resolved interceptor plan

1. tenant_isolation
   Phase: guard
   Priority: 9200
   Source: application security policy
   Removable: no

2. authorization
   Phase: guard
   Priority: 8500
   Source: #[Authorize]

3. transaction
   Phase: around
   Priority: 5500
   Source: #[Transactional]

4. audit
   Phase: around
   Priority: 3400
   Source: controller configuration

5. metrics
   Phase: around
   Priority: 1200
   Source: global default
```

---

## 122. Debug de dependencias

```text
transaction
    after:
        authorization
        validation

    before:
        domain_events

retry
    before:
        transaction
```

Orden final:

```text
authorization
validation
retry
transaction
domain_events
```

---

## 123. Excepciones

```text
ControllerInterceptorException
UnknownControllerInterceptorException
InvalidInterceptorDefinitionException
InvalidInterceptorConfigurationException
InterceptorResolutionException
InterceptorExecutionException
InterceptorPlanException
InterceptorPlanValidationException
CircularInterceptorDependencyException
DuplicateInterceptorException
NonRepeatableInterceptorException
ProtectedInterceptorRemovalException
InvalidInterceptorScopeException
InterceptorScopeViolationException
InterceptorAliasConflictException
InterceptorGroupNotFoundException
CircularInterceptorGroupException
InterceptorConditionException
NonCompilableInterceptorException
StaleInterceptorPlanException
TerminalInterceptorOrderException
UnsafeInterceptorOrderException
InterceptorLifecycleException
```

---

## 124. Ejemplo de error de orden

```text
Unsafe controller interceptor order detected.

Controller:
App\Http\Controllers\ReportController::show

Interceptor:
cache_result

Problem:
The cache interceptor is executed before an authorization
interceptor, but the cached result is user-sensitive.

Current order:
1. cache_result
2. authorization

Required order:
1. authorization
2. cache_result

Source:
#[CacheResult(scope: "user")]
```

---

## 125. Configuración

```php
return [
    'controllers' => [
        'interceptors' => [
            'enabled' => true,

            'compiled' => [
                'enabled' =>
                    env('APP_ENV') === 'production',

                'strict' => false,

                'path' => storage_path(
                    'framework/controllers/interceptors'
                ),

                'validate_hashes' => true,
            ],

            'cache' => [
                'request' => true,
                'worker' => true,
                'compiled' => true,
            ],

            'registry' => [
                'freeze_in_production' => true,
                'allow_runtime_registration' =>
                    env('APP_DEBUG'),
            ],

            'resolution' => [
                'expand_groups' => true,
                'resolve_aliases' => true,
                'deduplicate' => true,
                'validate_order' => true,
                'detect_cycles' => true,
            ],

            'execution' => [
                'record_timings' => true,
                'record_trace' => env('APP_DEBUG'),
                'allow_short_circuit' => true,
                'allow_argument_mutation' => true,
                'allow_result_mutation' => true,
            ],

            'security' => [
                'protect_mandatory_interceptors' => true,
                'reject_unsafe_order' => true,
                'allow_arbitrary_closures' => false,
                'validate_singleton_dependencies' => true,
            ],

            'defaults' => [
                'scope' => InterceptorScope::Execution,
                'phase' => InterceptorPhase::Around,
            ],

            'global' => [
                ControllerTracingInterceptor::class,
                ControllerMetricsInterceptor::class,
            ],
        ],
    ],
];
```

---

## 126. Registro en Container

```php
$container->singleton(
    ControllerInterceptorRegistryInterface::class,
    ControllerInterceptorRegistry::class
);

$container->singleton(
    InterceptorGroupRegistryInterface::class,
    InterceptorGroupRegistry::class
);

$container->singleton(
    ControllerInterceptorResolverInterface::class,
    ControllerInterceptorResolver::class
);

$container->singleton(
    InterceptorPlanBuilderInterface::class,
    InterceptorPlanBuilder::class
);

$container->singleton(
    InterceptorPlanValidatorInterface::class,
    InterceptorPlanValidator::class
);

$container->singleton(
    ControllerInterceptorPipelineInterface::class,
    ControllerInterceptorPipeline::class
);

$container->singleton(
    InterceptorInstanceResolverInterface::class,
    InterceptorInstanceResolver::class
);

$container->singleton(
    ControllerInterceptorCompilerInterface::class,
    ControllerInterceptorCompiler::class
);

$container->singleton(
    ControllerInterceptorCacheInterface::class,
    LayeredControllerInterceptorCache::class
);
```

---

## 127. Bootstrapping

Durante `register`:

* Registrar contratos.
* Registrar registries.
* Registrar resolver.
* Registrar plan builder.
* Registrar pipeline.
* Registrar instance resolver.
* Registrar compiler.
* Registrar cache.
* Registrar validadores.

Durante `boot`:

* Registrar interceptores del núcleo.
* Registrar aliases.
* Registrar grupos.
* Registrar metadata schemas.
* Registrar atributos y mappers.
* Incorporar interceptores de módulos.
* Incorporar interceptores de paquetes.
* Validar descriptores.
* Cargar planes compilados.
* Congelar registries en producción.

---

## 128. Registro desde paquetes

Ejemplo de paquete de auditoría:

```php
public function boot(
    ControllerInterceptorRegistryInterface $registry,
    MetadataSchemaRegistryInterface $metadata
): void {
    $registry->register(
        new InterceptorDescriptor(
            id: 'audit',
            interceptor: AuditInterceptor::class,
            scope: InterceptorScope::Execution,
            defaultPriority: 3400,
            defaultPhase: InterceptorPhase::Around,
            tags: ['audit', 'compliance'],
            repeatable: false,
            compilable: true,
        )
    );

    $metadata->register(
        AuditMetadataSchemas::controllerAudit()
    );
}
```

---

## 129. API de registro ergonómica

Podrá existir:

```php
Volt::controllers()
    ->interceptor('audit', AuditInterceptor::class)
    ->scope(InterceptorScope::Execution)
    ->phase(InterceptorPhase::Around)
    ->priority(3400)
    ->tag('audit')
    ->register();
```

Grupos:

```php
Volt::controllers()
    ->interceptorGroup('write')
    ->use('authorization')
    ->use('validation')
    ->use('transaction')
    ->use('audit')
    ->register();
```

---

## 130. Integración con ControllerExecution

Se añadirán propiedades:

```php
final class ControllerExecution
{
    public ?ControllerInterceptorPlan $interceptorPlan = null;

    public array $interceptors = [];

    public ?string $shortCircuitReason = null;

    public array $interceptorRecords = [];
}
```

O preferiblemente stores tipados:

```php
public InterceptorExecutionState $interceptorState;
```

---

## 131. InterceptorExecutionState

```php
final class InterceptorExecutionState
{
    public ?ControllerInterceptorPlan $plan = null;

    public array $resolvedInstances = [];

    public array $records = [];

    public bool $shortCircuited = false;

    public ?string $shortCircuitReason = null;

    public int $currentPosition = -1;

    public int $retryCount = 0;
}
```

Este estado será request/execution-scoped.

---

## 132. ResolveInterceptorsStage

```php
final class ResolveInterceptorsStage implements
    ControllerExecutionStageInterface
{
    public function __construct(
        private readonly ControllerInterceptorResolverInterface $resolver
    ) {
    }

    public function process(
        ControllerExecution $execution,
        ControllerExecutionStageChainInterface $chain
    ): mixed {
        $execution->interceptorState->plan =
            $this->resolver->resolve($execution);

        return $chain->proceed($execution);
    }
}
```

---

## 133. ControllerInterceptorsStage

```php
final class ControllerInterceptorsStage implements
    ControllerExecutionStageInterface
{
    public function __construct(
        private readonly ControllerInterceptorPipelineInterface $pipeline,
        private readonly ControllerInvocationTerminalInterface $terminal
    ) {
    }

    public function process(
        ControllerExecution $execution,
        ControllerExecutionStageChainInterface $chain
    ): mixed {
        $plan = $execution->interceptorState->plan;

        if ($plan === null || $plan->isEmpty()) {
            $execution->result = $this->terminal->invoke(
                $execution
            );
        } else {
            $execution->result = $this->pipeline->execute(
                execution: $execution,
                plan: $plan,
                terminal: $this->terminal,
            );
        }

        return $chain->proceed($execution);
    }
}
```

El pipeline exterior deberá omitir una segunda invocación del controlador.

---

## 134. Garantía de invocación única

`ControllerExecution` tendrá:

```php
public bool $controllerInvoked = false;
```

El terminal hará:

```php
if ($execution->controllerInvoked) {
    throw DuplicateControllerInvocationException::forExecution(
        $execution
    );
}

$execution->controllerInvoked = true;
```

Excepción: retries explícitos utilizarán un contador autorizado y una semántica especial.

---

## 135. InvocationAttempt

```php
final readonly class InvocationAttempt
{
    public function __construct(
        public int $number,
        public int $startedAt,
        public ?int $finishedAt = null,
        public ?string $outcome = null,
    ) {
    }
}
```

El retry interceptor registrará múltiples intentos sin confundirlos con una invocación duplicada accidental.

---

## 136. Testing

El sistema deberá ofrecer:

```text
FakeControllerInterceptor
RecordingControllerInterceptor
PassthroughControllerInterceptor
ShortCircuitControllerInterceptor
FailingControllerInterceptor
FakeInterceptorRegistry
InMemoryInterceptorPlanCache
InterceptorPlanTestBuilder
InterceptorExecutionAssertions
```

---

## 137. Fake interceptor

```php
final class FakeControllerInterceptor implements
    ControllerInterceptorInterface
{
    public array $executions = [];

    public function __construct(
        private mixed $result = null,
        private bool $proceed = true,
    ) {
    }

    public function intercept(
        ControllerExecution $execution,
        ControllerInterceptorChainInterface $chain
    ): mixed {
        $this->executions[] = $execution;

        if (! $this->proceed) {
            return $this->result;
        }

        return $chain->proceed($execution);
    }
}
```

---

## 138. Assertions

```php
expect($execution)
    ->toHaveRunInterceptor('authorization')
    ->toHaveRunInterceptorBefore(
        'transaction',
        'audit'
    )
    ->not->toHaveRunInterceptor('cache')
    ->not->toHaveShortCircuited();
```

---

## 139. Unit tests mínimos

* Registry registra interceptor.
* Registry resuelve alias.
* Registry se congela.
* Groups se expanden.
* Groups detectan ciclos.
* Metadata produce definiciones.
* Exclusiones funcionan.
* Interceptores protegidos no se eliminan.
* Duplicados se fusionan.
* No repeatable genera error.
* Prioridades se respetan.
* Fases se respetan.
* before/after se respetan.
* Ciclos se detectan.
* Condiciones estáticas se evalúan.
* Condiciones dinámicas se evalúan.
* Scope execution crea instancia aislada.
* Scope singleton reutiliza instancia válida.
* Singleton mutable es rechazado.
* Short circuit evita controlador.
* Transformación de argumentos funciona.
* Transformación de resultado funciona.
* Excepciones se propagan.
* Finally libera recursos.
* Retry crea intentos separados.
* Controller se invoca una vez.
* Plan compilado equivale al dinámico.

---

## 140. Integration tests

* Routing aporta interceptores.
* Metadata Engine combina interceptores.
* Controller class hereda interceptores.
* Method excluye interceptor.
* Parameter resolution ocurre antes.
* Authorization se ejecuta antes de transacción.
* Cache short circuit evita invocación.
* Transaction rollback en excepción.
* Audit registra éxito y fallo.
* Result normalizer recibe resultado transformado.
* Exception system recibe excepción final.
* Finalizer libera scopes.
* FrankenPHP no comparte estado.
* Multi-tenant mantiene aislamiento.
* Plan cache se invalida.
* Paquete externo registra interceptor.

---

## 141. Prueba de orden

```php
public function test_interceptors_run_in_expected_order(): void
{
    $execution = $this->executionFor(
        TestController::class,
        'store'
    );

    $this->dispatcher->dispatch($execution);

    expect($execution->interceptorState->records)
        ->toContainInOrder([
            'authorization',
            'validation',
            'transaction',
            'audit',
            'metrics',
        ]);
}
```

---

## 142. Prueba de short circuit

```php
public function test_cache_interceptor_can_skip_controller(): void
{
    $controller = new SpyController();

    $this->cache->put(
        'users.index',
        ['cached']
    );

    $result = $this->dispatch(
        $controller,
        'index'
    );

    expect($result)->toBe(['cached']);

    expect($controller->invocations)
        ->toBe(0);
}
```

---

## 143. Prueba de rollback

```php
public function test_transaction_rolls_back_when_controller_fails(): void
{
    $this->expectException(
        DomainOperationException::class
    );

    try {
        $this->dispatch(
            FailingController::class,
            'store'
        );
    } finally {
        expect($this->transactions->rolledBack())
            ->toBeTrue();

        expect($this->transactions->committed())
            ->toBeFalse();
    }
}
```

---

## 144. Prueba FrankenPHP

```php
public function test_execution_scoped_interceptor_is_not_shared(): void
{
    $first = $this->dispatchRequest(
        tenant: 'tenant-a'
    );

    $second = $this->dispatchRequest(
        tenant: 'tenant-b'
    );

    expect(
        $first->interceptorInstanceId
    )->not->toBe(
        $second->interceptorInstanceId
    );

    expect($first->tenant)
        ->toBe('tenant-a');

    expect($second->tenant)
        ->toBe('tenant-b');
}
```

---

## 145. Benchmarks

Escenarios:

```text
No interceptors
One interceptor
Five interceptors
Ten interceptors
Twenty interceptors
Short circuit first
Short circuit middle
All conditions static
Five dynamic conditions
Request cache hit
Worker cache hit
Compiled plan hit
Execution scope instances
Singleton instances
Transaction interceptor
Metrics interceptor
Trace enabled
Trace disabled
```

Métricas:

* Tiempo de resolución.
* Tiempo de construcción del plan.
* Tiempo por interceptor.
* Overhead total.
* Instancias creadas.
* Memoria temporal.
* Cache hit ratio.
* Invocaciones por segundo.
* Dynamic vs compiled.
* Pipeline sin interceptores vs invocación directa.

---

## 146. Objetivos de rendimiento

Objetivos iniciales orientativos:

```text
Plan cache hit:
    overhead mínimo y sin Reflection

Empty plan:
    near-direct invocation

Compiled plan:
    sin sorting runtime
    sin group expansion
    sin dependency graph runtime

Singleton stateless interceptor:
    sin nueva instancia

Execution interceptor:
    creación controlada por Container
```

Los valores numéricos definitivos deberán obtenerse mediante benchmarks reales.

---

## 147. Estructura de directorios

```text
src/
└── Quantum/
    └── Controllers/
        └── Interceptors/
            ├── Contracts/
            │   ├── ControllerInterceptorInterface.php
            │   ├── ControllerInterceptorChainInterface.php
            │   ├── ForkableControllerInterceptorChainInterface.php
            │   ├── ControllerInterceptorRegistryInterface.php
            │   ├── InterceptorGroupRegistryInterface.php
            │   ├── ControllerInterceptorResolverInterface.php
            │   ├── InterceptorPlanBuilderInterface.php
            │   ├── InterceptorPlanValidatorInterface.php
            │   ├── ControllerInterceptorPipelineInterface.php
            │   ├── ControllerInvocationTerminalInterface.php
            │   ├── InterceptorInstanceResolverInterface.php
            │   ├── ControllerInterceptorFactoryInterface.php
            │   ├── ControllerInterceptorCompilerInterface.php
            │   ├── ControllerInterceptorCacheInterface.php
            │   ├── InterceptorConditionInterface.php
            │   ├── ConfigurableControllerInterceptorInterface.php
            │   └── InterceptorLifecycleManagerInterface.php
            │
            ├── Definitions/
            │   ├── InterceptorDefinition.php
            │   ├── ResolvedInterceptorDefinition.php
            │   ├── InterceptorDescriptor.php
            │   ├── ControllerInterceptorPlan.php
            │   ├── CompiledControllerInterceptorPlan.php
            │   ├── InterceptorSource.php
            │   ├── InterceptorSourceType.php
            │   ├── InterceptorPhase.php
            │   ├── InterceptorScope.php
            │   ├── InterceptorMergePolicy.php
            │   ├── InterceptorExclusion.php
            │   ├── InterceptorExclusionMode.php
            │   ├── InterceptorShortCircuit.php
            │   ├── InterceptorExecutionRecord.php
            │   ├── InterceptorExecutionState.php
            │   ├── InvocationAttempt.php
            │   └── InterceptorPlanCacheKey.php
            │
            ├── Registry/
            │   ├── ControllerInterceptorRegistry.php
            │   ├── InterceptorGroupRegistry.php
            │   ├── InterceptorAliasRegistry.php
            │   ├── InterceptorConditionRegistry.php
            │   └── InterceptorFactoryRegistry.php
            │
            ├── Resolution/
            │   ├── ControllerInterceptorResolver.php
            │   ├── InterceptorDefinitionCollector.php
            │   ├── InterceptorAliasResolver.php
            │   ├── InterceptorGroupExpander.php
            │   ├── InterceptorExclusionResolver.php
            │   ├── InterceptorDefinitionNormalizer.php
            │   └── InterceptorDefinitionDeduplicator.php
            │
            ├── Planning/
            │   ├── InterceptorPlanBuilder.php
            │   ├── InterceptorDependencyGraph.php
            │   ├── InterceptorDependencySorter.php
            │   ├── InterceptorPhaseSorter.php
            │   ├── InterceptorPrioritySorter.php
            │   └── InterceptorPlanSignature.php
            │
            ├── Validation/
            │   ├── InterceptorPlanValidator.php
            │   ├── UnknownInterceptorValidator.php
            │   ├── InterceptorConfigurationValidator.php
            │   ├── InterceptorScopeValidator.php
            │   ├── InterceptorDependencyValidator.php
            │   ├── InterceptorCycleValidator.php
            │   ├── InterceptorDuplicateValidator.php
            │   ├── ProtectedInterceptorValidator.php
            │   ├── TransactionStreamingValidator.php
            │   ├── CacheAuthorizationOrderValidator.php
            │   ├── RetryIdempotencyValidator.php
            │   └── CompiledInterceptorValidator.php
            │
            ├── Pipeline/
            │   ├── ControllerInterceptorPipeline.php
            │   ├── ControllerInterceptorChain.php
            │   ├── ControllerInvocationTerminal.php
            │   ├── InterceptorExecutionRecorder.php
            │   └── InterceptorLifecycleManager.php
            │
            ├── Instances/
            │   ├── InterceptorInstanceResolver.php
            │   ├── ScopedInterceptorInstanceStore.php
            │   ├── SingletonInterceptorStore.php
            │   ├── WorkerInterceptorStore.php
            │   ├── RequestInterceptorStore.php
            │   └── ExecutionInterceptorStore.php
            │
            ├── Conditions/
            │   ├── EnvironmentInterceptorCondition.php
            │   ├── HttpMethodInterceptorCondition.php
            │   ├── RouteNameInterceptorCondition.php
            │   ├── ControllerTypeInterceptorCondition.php
            │   ├── MetadataValueInterceptorCondition.php
            │   ├── TenantInterceptorCondition.php
            │   ├── FeatureFlagInterceptorCondition.php
            │   ├── ArgumentInterceptorCondition.php
            │   ├── ResultTypeInterceptorCondition.php
            │   └── ExceptionTypeInterceptorCondition.php
            │
            ├── Core/
            │   ├── AuthorizationInterceptor.php
            │   ├── ValidationInterceptor.php
            │   ├── TransactionInterceptor.php
            │   ├── IdempotencyInterceptor.php
            │   ├── AuditInterceptor.php
            │   ├── CacheResultInterceptor.php
            │   ├── RateLimitInterceptor.php
            │   ├── RetryInterceptor.php
            │   ├── LockInterceptor.php
            │   ├── TimeoutInterceptor.php
            │   ├── CircuitBreakerInterceptor.php
            │   ├── MetricsInterceptor.php
            │   ├── TracingInterceptor.php
            │   ├── FeatureFlagInterceptor.php
            │   ├── TenantIsolationInterceptor.php
            │   ├── DomainEventsInterceptor.php
            │   └── ResultTransformationInterceptor.php
            │
            ├── Configuration/
            │   ├── InterceptorConfigurationHydrator.php
            │   ├── InterceptorConfigurationSchema.php
            │   ├── TransactionInterceptorConfig.php
            │   ├── CacheResultInterceptorConfig.php
            │   ├── RetryInterceptorConfig.php
            │   └── RateLimitInterceptorConfig.php
            │
            ├── Attributes/
            │   ├── Intercept.php
            │   ├── WithoutInterceptor.php
            │   ├── InterceptorGroup.php
            │   ├── Transactional.php
            │   ├── Idempotent.php
            │   ├── Auditable.php
            │   ├── Retry.php
            │   ├── RateLimited.php
            │   └── CacheResult.php
            │
            ├── Compiler/
            │   ├── ControllerInterceptorCompiler.php
            │   ├── CompiledInterceptorPlanWriter.php
            │   ├── CompiledInterceptorPlanLoader.php
            │   ├── InterceptorSourceHasher.php
            │   └── InterceptorRegistryHasher.php
            │
            ├── Cache/
            │   ├── LayeredControllerInterceptorCache.php
            │   ├── RequestControllerInterceptorCache.php
            │   ├── WorkerControllerInterceptorCache.php
            │   └── CompiledControllerInterceptorCache.php
            │
            ├── Events/
            │   ├── ControllerInterceptorsResolving.php
            │   ├── ControllerInterceptorsResolved.php
            │   ├── ControllerInterceptorPlanBuilt.php
            │   ├── ControllerInterceptorStarting.php
            │   ├── ControllerInterceptorCompleted.php
            │   ├── ControllerInterceptorSkipped.php
            │   ├── ControllerInterceptorShortCircuited.php
            │   ├── ControllerInterceptorFailed.php
            │   ├── ControllerInterceptorsCompleted.php
            │   ├── ControllerInterceptorPlanCompiled.php
            │   └── ControllerInterceptorPlanInvalidated.php
            │
            ├── Exceptions/
            │   ├── ControllerInterceptorException.php
            │   ├── UnknownControllerInterceptorException.php
            │   ├── InvalidInterceptorDefinitionException.php
            │   ├── InvalidInterceptorConfigurationException.php
            │   ├── InterceptorResolutionException.php
            │   ├── InterceptorExecutionException.php
            │   ├── InterceptorPlanException.php
            │   ├── InterceptorPlanValidationException.php
            │   ├── CircularInterceptorDependencyException.php
            │   ├── DuplicateInterceptorException.php
            │   ├── NonRepeatableInterceptorException.php
            │   ├── ProtectedInterceptorRemovalException.php
            │   ├── InvalidInterceptorScopeException.php
            │   ├── InterceptorScopeViolationException.php
            │   ├── InterceptorAliasConflictException.php
            │   ├── InterceptorGroupNotFoundException.php
            │   ├── CircularInterceptorGroupException.php
            │   ├── InterceptorConditionException.php
            │   ├── NonCompilableInterceptorException.php
            │   ├── StaleInterceptorPlanException.php
            │   ├── TerminalInterceptorOrderException.php
            │   ├── UnsafeInterceptorOrderException.php
            │   ├── InterceptorLifecycleException.php
            │   └── DuplicateControllerInvocationException.php
            │
            └── Testing/
                ├── FakeControllerInterceptor.php
                ├── RecordingControllerInterceptor.php
                ├── PassthroughControllerInterceptor.php
                ├── ShortCircuitControllerInterceptor.php
                ├── FailingControllerInterceptor.php
                ├── FakeInterceptorRegistry.php
                ├── InMemoryInterceptorPlanCache.php
                ├── InterceptorPlanTestBuilder.php
                └── InterceptorExecutionAssertions.php
```

---

## 148. Implementación V1

La primera versión deberá incluir:

* `ControllerInterceptorInterface`.
* `ControllerInterceptorChainInterface`.
* `InterceptorDefinition`.
* `InterceptorDescriptor`.
* `InterceptorScope`.
* `InterceptorPhase`.
* `ControllerInterceptorRegistry`.
* `InterceptorGroupRegistry`.
* `ControllerInterceptorResolver`.
* `InterceptorPlanBuilder`.
* Orden por prioridad.
* Dependencias `before` y `after`.
* Detección de ciclos.
* Exclusiones.
* Deduplicación.
* `ControllerInterceptorPlan`.
* `ControllerInterceptorPipeline`.
* `ControllerInterceptorChain`.
* `ControllerInvocationTerminal`.
* Resolución mediante Container.
* Scope execution.
* Scope singleton para interceptores stateless.
* Short circuit.
* Transformación de resultado.
* Manejo de excepciones.
* Metadata integration.
* Request cache.
* Worker plan cache.
* Trace en debug.
* Compatibilidad FrankenPHP.
* Pruebas unitarias y de integración.

Interceptores iniciales V1:

```text
TransactionInterceptor
AuditInterceptor
CacheResultInterceptor
MetricsInterceptor
TracingInterceptor
TenantIsolationInterceptor
ResultTransformationInterceptor
```

Podrán posponerse:

* Retry avanzado.
* Circuit breaker.
* Timeout cooperativo.
* Locks distribuidos.
* Idempotencia distribuida.
* Plan compilado parcial.
* Factories parametrizables complejas.
* Conditions por resultado.
* Conditions por excepción.
* Hot reload de registry.
* Visualización gráfica del pipeline.

---

## 149. Ejemplo completo

```php
#[InterceptorGroup('secured-write')]
#[Transactional(
    connection: 'default',
    retry: 2,
)]
#[Auditable('user.created')]
final class UserController
{
    #[RateLimited(
        limit: 20,
        window: '1 minute',
    )]
    #[Idempotent(
        key: 'header:Idempotency-Key',
    )]
    public function store(
        CreateUserData $data
    ): User {
        return User::create(
            $data->toArray()
        );
    }
}
```

Grupo:

```php
$groups->register(
    'secured-write',
    [
        new InterceptorDefinition(
            interceptor:
                TenantIsolationInterceptor::class,
            priority: 9200,
            phase: InterceptorPhase::Guard,
        ),

        new InterceptorDefinition(
            interceptor:
                AuthorizationInterceptor::class,
            priority: 8500,
            phase: InterceptorPhase::Guard,
        ),

        new InterceptorDefinition(
            interceptor:
                ValidationInterceptor::class,
            priority: 6800,
            phase: InterceptorPhase::Input,
        ),

        new InterceptorDefinition(
            interceptor:
                MetricsInterceptor::class,
            priority: 1200,
            phase: InterceptorPhase::Around,
        ),
    ]
);
```

Plan resultante:

```text
1. TenantIsolationInterceptor
2. AuthorizationInterceptor
3. RateLimitInterceptor
4. IdempotencyInterceptor
5. ValidationInterceptor
6. TransactionInterceptor
7. AuditInterceptor
8. MetricsInterceptor
9. ControllerInvocationTerminal
```

Ejecución:

```text
Tenant isolation check
    ↓
Authorization check
    ↓
Rate limit check
    ↓
Idempotency lookup
    ↓
Validate input
    ↓
Begin transaction
    ↓
Start audit
    ↓
Start metrics
    ↓
Invoke UserController::store()
    ↓
Finish metrics
    ↓
Complete audit
    ↓
Commit transaction
    ↓
Store idempotency result
    ↓
Return raw User result
```

El resultado será entregado posteriormente a:

```text
Result Normalization System
```

---

## 150. Decisiones arquitectónicas

### ADR-CTRL-INT-001

**Decisión:** Los interceptores serán distintos del middleware HTTP.

**Razón:** Operan sobre `ControllerExecution`, parámetros, metadata y resultado bruto.

---

### ADR-CTRL-INT-002

**Decisión:** El contrato utilizará semántica around.

**Razón:** Permite ejecutar lógica antes, después, en excepción y en finalización.

---

### ADR-CTRL-INT-003

**Decisión:** Los interceptores se resolverán antes de ejecutarse.

**Razón:** Permite validación, ordenamiento, cache y compilación.

---

### ADR-CTRL-INT-004

**Decisión:** El orden se definirá mediante fase, prioridad y dependencias.

**Razón:** La prioridad numérica sola no representa todas las restricciones.

---

### ADR-CTRL-INT-005

**Decisión:** El plan será inmutable.

**Razón:** Facilita cache, concurrencia y compatibilidad con workers persistentes.

---

### ADR-CTRL-INT-006

**Decisión:** Los interceptores utilizarán el Container.

**Razón:** Permite inyección de dependencias, factories y scopes.

---

### ADR-CTRL-INT-007

**Decisión:** El scope predeterminado será `Execution`.

**Razón:** Reduce contaminación entre requests y riesgos en FrankenPHP.

---

### ADR-CTRL-INT-008

**Decisión:** Los interceptores singleton deberán ser stateless.

**Razón:** Evita memory leaks y contaminación entre ejecuciones.

---

### ADR-CTRL-INT-009

**Decisión:** El pipeline permitirá short circuit.

**Razón:** Es necesario para cache, idempotencia, feature flags y mantenimiento.

---

### ADR-CTRL-INT-010

**Decisión:** El invoker será el terminal del pipeline.

**Razón:** Garantiza que los interceptores realmente envuelvan la invocación.

---

### ADR-CTRL-INT-011

**Decisión:** Los grupos de interceptores serán registrables.

**Razón:** Permite reutilizar políticas comunes de ejecución.

---

### ADR-CTRL-INT-012

**Decisión:** Las exclusiones serán objetos explícitos.

**Razón:** Evita convenciones ambiguas basadas en strings especiales.

---

### ADR-CTRL-INT-013

**Decisión:** Los interceptores críticos podrán marcarse como protegidos.

**Razón:** Evita eliminar políticas obligatorias de seguridad o compliance.

---

### ADR-CTRL-INT-014

**Decisión:** Los planes podrán compilarse.

**Razón:** Elimina sorting, expansión, merge y validación repetida en producción.

---

### ADR-CTRL-INT-015

**Decisión:** Las condiciones runtime no formarán parte de la cache global definitiva.

**Razón:** Evita compartir decisiones entre usuarios, tenants o requests.

---

### ADR-CTRL-INT-016

**Decisión:** La transformación del resultado ocurrirá antes de la normalización.

**Razón:** Los interceptores trabajan con el resultado semántico bruto del controlador.

---

### ADR-CTRL-INT-017

**Decisión:** El sistema detectará órdenes inseguras.

**Razón:** La combinación incorrecta de cache, autorización, retry y transacciones puede producir vulnerabilidades o inconsistencias.

---

### ADR-CTRL-INT-018

**Decisión:** Los retries deberán crear intentos explícitos.

**Razón:** Evita confundir reintentos autorizados con invocaciones duplicadas accidentales.

---

### ADR-CTRL-INT-019

**Decisión:** La metadata declarará interceptores, pero no almacenará instancias.

**Razón:** Las instancias pertenecen al ciclo de ejecución y al Container.

---

### ADR-CTRL-INT-020

**Decisión:** El sistema general de excepciones seguirá siendo responsable del mapping final.

**Razón:** Los interceptores pueden enriquecer o capturar errores locales, pero no deben reemplazar la política global.

---

## 151. Criterios de aceptación

El sistema se considerará correctamente implementado cuando:

* Pueda registrar interceptores.
* Pueda resolver aliases.
* Pueda registrar grupos.
* Pueda expandir grupos.
* Pueda leer interceptores desde metadata.
* Pueda excluir interceptores heredados.
* Pueda proteger interceptores obligatorios.
* Pueda fusionar definiciones.
* Detecte duplicados inválidos.
* Ordene por fases.
* Ordene por prioridad.
* Respete dependencias before/after.
* Detecte ciclos.
* Valide configuración.
* Construya planes inmutables.
* Ejecute semántica around.
* Permita short circuit.
* Permita transformar argumentos.
* Permita transformar resultados.
* Propague excepciones correctamente.
* Ejecute lógica finally.
* Resuelva instancias desde el Container.
* Respete scopes.
* Aísle ejecuciones.
* Sea seguro en FrankenPHP.
* Registre timings.
* Genere traces.
* Cachee planes estáticos.
* Permita planes compilados.
* Invalide planes obsoletos.
* Garantice invocación única.
* Permita retries explícitos.
* Se integre con `ControllerExecution`.
* Se integre con `MetadataEngine`.
* Se integre con `ControllerInvoker`.
* Entregue el resultado al sistema de normalización.
* Mantenga equivalencia entre modo dinámico y compilado.

---

## 152. Conclusión

El `Controller Interceptor System` será la capa encargada de aplicar comportamiento transversal alrededor de la invocación de los controladores de VoltStack.

Su diseño permitirá separar completamente:

* La resolución del controlador.
* La resolución de parámetros.
* La metadata.
* Las políticas transversales.
* La invocación.
* La normalización del resultado.
* El manejo global de excepciones.

La combinación de:

```text
Interceptor Registry
Interceptor Definitions
Metadata Integration
Groups
Exclusions
Dependency Graph
Execution Scopes
Interceptor Plans
Around Pipeline
Short Circuit
Compilation
Observability
```

permitirá implementar autorización, validación contextual, transacciones, auditoría, caché, idempotencia, métricas, locks, retries y otras capacidades sin contaminar los controladores ni el dispatcher.

El sistema será especialmente importante para la integración de VoltStack con FrankenPHP, ya que establece reglas estrictas de aislamiento, lifecycle y scopes para procesos persistentes.
