# Dispatcher y motor de ejecución de controladores de VoltStack


**Versión:** 1.0
**Estado:** Draft
**Módulo:** `VoltStack\Quantum\Controllers`
**Documento anterior:** `02_CONTROLLER_BASE_CLASS.md`

---

## 1. Propósito

Este documento define el `ControllerDispatcher`, el `ControllerExecutionPipeline`, las etapas de ejecución y el objeto `ControllerExecution` del sistema de controladores de VoltStack.

El dispatcher representa el punto de entrada público utilizado por Routing y HttpKernel para ejecutar una definición de controlador.

Sin embargo, no concentrará toda la lógica del proceso.

Su función será:

* Crear el contexto de ejecución.
* Iniciar el pipeline.
* Coordinar la finalización.
* Garantizar la liberación de recursos.
* Propagar una respuesta válida al kernel.

La lógica específica de resolución, autorización, validación, invocación y normalización será delegada a etapas independientes.

La arquitectura resultante será:

```text
ControllerDispatcher
    ↓
ControllerExecutionPipeline
    ↓
Execution Stages
    ↓
ControllerInvoker
    ↓
Result Normalizer
    ↓
ResponseInterface
```

Este diseño permite incorporar nuevas capacidades sin modificar el dispatcher ni crear una clase central monolítica.

---

## 2. Objetivos

El sistema deberá:

* Ejecutar controladores de forma determinista.
* Mantener el dispatcher pequeño.
* Permitir agregar, reemplazar o eliminar etapas.
* Separar resolución, invocación y normalización.
* Compartir el estado de ejecución mediante un objeto controlado.
* Integrarse con middleware e interceptores.
* Publicar eventos del ciclo de vida.
* Garantizar limpieza de contexto.
* Funcionar en procesos persistentes.
* Soportar ejecución dinámica y compilada.
* Permitir short-circuiting.
* Facilitar observabilidad y debugging.
* Mantener equivalencia funcional entre desarrollo y producción.

---

## 3. Posición dentro del framework

El dispatcher se ejecutará después de que el router haya encontrado una ruta y después del middleware externo correspondiente.

```text
HTTP Request
    ↓
HttpKernel
    ↓
Global Middleware
    ↓
Router
    ↓
Route Match
    ↓
Route Middleware
    ↓
ControllerDispatcher
    ↓
Controller Execution Pipeline
    ↓
Controller Middleware
    ↓
Controller Invocation
    ↓
Response
    ↓
HttpKernel
```

El dispatcher no será responsable de buscar rutas.

Recibirá una definición de controlador previamente asociada al `RouteMatch`.

---

## 4. Arquitectura general

```text
┌─────────────────────────┐
│ ControllerDispatcher    │
└────────────┬────────────┘
             │
             ▼
┌─────────────────────────┐
│ ControllerExecution     │
└────────────┬────────────┘
             │
             ▼
┌─────────────────────────┐
│ Execution Pipeline      │
└────────────┬────────────┘
             │
             ▼
┌─────────────────────────┐
│ Execution Stages        │
│                         │
│  ResolveController      │
│  ResolveMetadata        │
│  ResolveArguments       │
│  Authorization          │
│  Validation             │
│  Middleware             │
│  Invocation             │
│  Normalization          │
│  Finalization           │
└────────────┬────────────┘
             │
             ▼
┌─────────────────────────┐
│ ResponseInterface       │
└─────────────────────────┘
```

---

## 5. ControllerDispatcher

El `ControllerDispatcher` será la fachada principal del motor de ejecución.

Contrato:

```php
namespace VoltStack\Quantum\Controllers\Contracts;

use VoltStack\Quantum\Controllers\Definitions\ControllerDefinition;
use VoltStack\Quantum\Controllers\Context\ControllerContext;
use VoltStack\Quantum\Http\Contracts\ResponseInterface;

interface ControllerDispatcherInterface
{
    public function dispatch(
        ControllerDefinition $definition,
        ControllerContext $context
    ): ResponseInterface;
}
```

Implementación base:

```php
namespace VoltStack\Quantum\Controllers;

final class ControllerDispatcher implements ControllerDispatcherInterface
{
    public function __construct(
        private readonly ControllerExecutionFactoryInterface $executionFactory,
        private readonly ControllerExecutionPipelineInterface $pipeline,
        private readonly ControllerExecutionFinalizerInterface $finalizer,
    ) {
    }

    public function dispatch(
        ControllerDefinition $definition,
        ControllerContext $context
    ): ResponseInterface {
        $execution = $this->executionFactory->create(
            $definition,
            $context
        );

        try {
            $execution = $this->pipeline->execute($execution);

            return $this->finalizer->response($execution);
        } finally {
            $this->finalizer->release($execution);
        }
    }
}
```

---

## 6. Responsabilidades del dispatcher

El dispatcher deberá:

* Recibir una definición normalizada.
* Recibir un contexto válido.
* Crear el objeto de ejecución.
* Iniciar el pipeline.
* Garantizar una respuesta final.
* Liberar contextos y recursos.
* Mantener la excepción original cuando ocurra un fallo.
* Coordinar la finalización incluso ante errores.

No deberá:

* Resolver directamente el controlador.
* Examinar parámetros mediante Reflection.
* Ejecutar autorización.
* Ejecutar validación.
* Invocar directamente el método.
* Convertir directamente resultados en respuestas.
* Implementar middleware.
* Contener condiciones por tipo de controlador.

---

## 7. ControllerExecution

`ControllerExecution` representa el estado completo de una ejecución de controlador.

Es el objeto que viaja a través de las etapas.

```php
namespace VoltStack\Quantum\Controllers\Execution;

final class ControllerExecution
{
    public function __construct(
        public readonly ControllerDefinition $definition,
        public readonly ControllerContext $context,
        public readonly string $executionId,
        public ControllerExecutionState $state = ControllerExecutionState::Created,
        public ?ResolvedController $controller = null,
        public ?ControllerMetadata $metadata = null,
        public array $arguments = [],
        public array $middleware = [],
        public array $interceptors = [],
        public mixed $result = null,
        public ?ResponseInterface $response = null,
        public ?Throwable $exception = null,
        public array $attributes = [],
        public array $timings = [],
    ) {
    }
}
```

---

## 8. Mutabilidad controlada

`ControllerExecution` será mutable únicamente durante el pipeline.

Esta decisión evita crear un objeto nuevo en cada etapa y reduce sobrecarga en rutas de alto tráfico.

Sin embargo, deberán aplicarse reglas estrictas:

* `definition` será inmutable.
* `context` será inmutable.
* `executionId` será inmutable.
* Cada etapa solo modificará campos bajo su responsabilidad.
* Una etapa no podrá retroceder el estado.
* La respuesta final no podrá reemplazarse después de finalizar.
* Los cambios deberán ser trazables en modo debug.

---

## 9. Estado de ejecución

```php
enum ControllerExecutionState: string
{
    case Created = 'created';
    case ResolvingController = 'resolving_controller';
    case ControllerResolved = 'controller_resolved';
    case ResolvingMetadata = 'resolving_metadata';
    case MetadataResolved = 'metadata_resolved';
    case ResolvingArguments = 'resolving_arguments';
    case ArgumentsResolved = 'arguments_resolved';
    case Authorizing = 'authorizing';
    case Authorized = 'authorized';
    case Validating = 'validating';
    case Validated = 'validated';
    case RunningMiddleware = 'running_middleware';
    case Invoking = 'invoking';
    case Invoked = 'invoked';
    case Normalizing = 'normalizing';
    case ResponseCreated = 'response_created';
    case Finalizing = 'finalizing';
    case Finished = 'finished';
    case Failed = 'failed';
    case Released = 'released';
}
```

La transición de estado deberá validarse.

---

## 10. Transiciones válidas

```text
Created
    ↓
ResolvingController
    ↓
ControllerResolved
    ↓
ResolvingMetadata
    ↓
MetadataResolved
    ↓
ResolvingArguments
    ↓
ArgumentsResolved
    ↓
Authorizing
    ↓
Authorized
    ↓
Validating
    ↓
Validated
    ↓
RunningMiddleware
    ↓
Invoking
    ↓
Invoked
    ↓
Normalizing
    ↓
ResponseCreated
    ↓
Finalizing
    ↓
Finished
    ↓
Released
```

Cualquier estado activo podrá pasar a:

```text
Failed
    ↓
Released
```

También podrá existir finalización anticipada cuando una etapa produzca una respuesta.

---

## 11. ControllerExecutionFactory

El factory será responsable de construir el objeto de ejecución.

Contrato:

```php
interface ControllerExecutionFactoryInterface
{
    public function create(
        ControllerDefinition $definition,
        ControllerContext $context
    ): ControllerExecution;
}
```

Implementación:

```php
final class ControllerExecutionFactory implements
    ControllerExecutionFactoryInterface
{
    public function __construct(
        private readonly ExecutionIdGeneratorInterface $ids
    ) {
    }

    public function create(
        ControllerDefinition $definition,
        ControllerContext $context
    ): ControllerExecution {
        return new ControllerExecution(
            definition: $definition,
            context: $context,
            executionId: $this->ids->generate(),
        );
    }
}
```

El `executionId` permitirá correlacionar:

* Logs.
* Eventos.
* Métricas.
* Excepciones.
* Traces.
* Debugging.
* Subprocesos internos.

---

## 12. ControllerExecutionPipeline

El pipeline ejecutará una colección ordenada de etapas.

Contrato:

```php
interface ControllerExecutionPipelineInterface
{
    public function execute(
        ControllerExecution $execution
    ): ControllerExecution;
}
```

Implementación conceptual:

```php
final class ControllerExecutionPipeline implements
    ControllerExecutionPipelineInterface
{
    /**
     * @param iterable<ControllerExecutionStageInterface> $stages
     */
    public function __construct(
        private readonly iterable $stages
    ) {
    }

    public function execute(
        ControllerExecution $execution
    ): ControllerExecution {
        $pipeline = array_reduce(
            array_reverse(iterator_to_array($this->stages)),
            function (Closure $next, ControllerExecutionStageInterface $stage): Closure {
                return fn (ControllerExecution $execution): ControllerExecution =>
                    $stage->handle($execution, $next);
            },
            fn (ControllerExecution $execution): ControllerExecution => $execution
        );

        return $pipeline($execution);
    }
}
```

---

## 13. Contrato de etapa

```php
interface ControllerExecutionStageInterface
{
    public function handle(
        ControllerExecution $execution,
        Closure $next
    ): ControllerExecution;

    public function priority(): int;
}
```

Una etapa podrá:

* Leer información del execution.
* Modificar sus campos autorizados.
* Ejecutar el siguiente stage.
* Rodear la ejecución siguiente.
* Producir una respuesta anticipada.
* Registrar métricas.
* Lanzar una excepción.

---

## 14. Tipos de etapa

### 14.1 Etapas lineales

Ejecutan una operación y continúan.

```php
public function handle(
    ControllerExecution $execution,
    Closure $next
): ControllerExecution {
    $execution->metadata = $this->resolver->resolve(...);

    return $next($execution);
}
```

### 14.2 Etapas envolventes

Ejecutan lógica antes y después del siguiente stage.

```php
public function handle(
    ControllerExecution $execution,
    Closure $next
): ControllerExecution {
    $start = hrtime(true);

    try {
        return $next($execution);
    } finally {
        $execution->timings['total'] =
            hrtime(true) - $start;
    }
}
```

### 14.3 Etapas terminales

Producen un resultado sin ejecutar etapas posteriores.

```php
public function handle(
    ControllerExecution $execution,
    Closure $next
): ControllerExecution {
    $execution->response = $this->cache->get(...);

    return $execution;
}
```

### 14.4 Etapas condicionales

Solo actúan cuando se cumple cierta condición.

```php
if (! $execution->metadata->transactional) {
    return $next($execution);
}
```

---

## 15. Pipeline predeterminado

Orden inicial:

```text
1. InitializeExecutionStage
2. ResolveControllerStage
3. InjectControllerContextStage
4. ResolveControllerMetadataStage
5. ResolveArgumentsStage
6. ResolveControllerMiddlewareStage
7. ResolveInterceptorsStage
8. AuthorizationStage
9. ValidationStage
10. ControllerMiddlewareStage
11. ControllerInterceptorsStage
12. InvokeControllerStage
13. NormalizeControllerResultStage
14. FinalizeControllerResponseStage
15. CompleteExecutionStage
```

Las etapas de observabilidad podrán rodear total o parcialmente este pipeline.

---

## 16. InitializeExecutionStage

Responsabilidades:

* Verificar estado inicial.
* Registrar tiempo de inicio.
* Publicar evento de inicio.
* Incorporar metadata básica.
* Preparar tracing.
* Cambiar el estado.

```php
final class InitializeExecutionStage implements
    ControllerExecutionStageInterface
{
    public function handle(
        ControllerExecution $execution,
        Closure $next
    ): ControllerExecution {
        $execution->state = ControllerExecutionState::ResolvingController;
        $execution->timings['started_at'] = hrtime(true);

        return $next($execution);
    }

    public function priority(): int
    {
        return 1000;
    }
}
```

---

## 17. ResolveControllerStage

Esta etapa utilizará el `ControllerResolver`.

```php
final class ResolveControllerStage implements
    ControllerExecutionStageInterface
{
    public function __construct(
        private readonly ControllerResolverInterface $resolver,
        private readonly ControllerLifecycleInterface $lifecycle,
    ) {
    }

    public function handle(
        ControllerExecution $execution,
        Closure $next
    ): ControllerExecution {
        $this->lifecycle->resolving(
            $execution->definition,
            $execution->context
        );

        $execution->controller = $this->resolver->resolve(
            $execution->definition,
            $execution->context
        );

        $execution->state =
            ControllerExecutionState::ControllerResolved;

        $this->lifecycle->resolved(
            $execution->controller,
            $execution->context
        );

        return $next($execution);
    }

    public function priority(): int
    {
        return 900;
    }
}
```

Al finalizar, deberá existir:

```php
$execution->controller instanceof ResolvedController
```

---

## 18. InjectControllerContextStage

Esta etapa asignará el contexto de ejecución a controladores que lo soporten.

```php
final class InjectControllerContextStage implements
    ControllerExecutionStageInterface
{
    public function __construct(
        private readonly ControllerContextInjectorInterface $injector,
        private readonly ControllerExecutionContextFactoryInterface $factory,
    ) {
    }

    public function handle(
        ControllerExecution $execution,
        Closure $next
    ): ControllerExecution {
        $controller = $execution->controller
            ?? throw MissingResolvedControllerException::create();

        if (! is_object($controller->instance)) {
            return $next($execution);
        }

        $context = $this->factory->create($execution);

        $this->injector->inject(
            $controller->instance,
            $context
        );

        $execution->attributes['controller_context_injected'] = true;

        return $next($execution);
    }

    public function priority(): int
    {
        return 850;
    }
}
```

La liberación se realizará posteriormente en el finalizer.

---

## 19. ResolveControllerMetadataStage

Responsabilidades:

* Resolver atributos de clase.
* Resolver atributos del método.
* Combinar metadata de ruta.
* Aplicar precedencia.
* Cargar metadata compilada cuando exista.

```php
$execution->metadata = $this->metadataResolver->resolve(
    $execution->definition,
    $execution->controller,
    $execution->context
);
```

Al finalizar:

```php
$execution->state =
    ControllerExecutionState::MetadataResolved;
```

---

## 20. ResolveArgumentsStage

Esta etapa resolverá todos los argumentos requeridos.

```php
final class ResolveArgumentsStage implements
    ControllerExecutionStageInterface
{
    public function __construct(
        private readonly ControllerArgumentResolverInterface $resolver,
        private readonly ControllerLifecycleInterface $lifecycle,
    ) {
    }

    public function handle(
        ControllerExecution $execution,
        Closure $next
    ): ControllerExecution {
        $execution->state =
            ControllerExecutionState::ResolvingArguments;

        $execution->arguments = $this->resolver->resolveArguments(
            $execution->controller,
            $execution->context,
            $execution->metadata
        );

        $execution->state =
            ControllerExecutionState::ArgumentsResolved;

        $this->lifecycle->argumentsResolved(
            $execution->controller,
            $execution->arguments,
            $execution->context
        );

        return $next($execution);
    }

    public function priority(): int
    {
        return 700;
    }
}
```

---

## 21. ResolveControllerMiddlewareStage

Esta etapa obtendrá el middleware asociado al controlador.

Fuentes:

* Metadata de ruta.
* Atributos de clase.
* Atributos de método.
* Clase base.
* Constructor.
* Configuración.
* Paquetes.

```php
$execution->middleware =
    $this->middlewareResolver->resolve(
        $execution->controller,
        $execution->metadata,
        $execution->context
    );
```

No ejecutará el middleware.

Solo producirá una lista normalizada y ordenada.

---

## 22. ResolveInterceptorsStage

Esta etapa resolverá interceptores declarativos y globales.

```php
$execution->interceptors =
    $this->interceptorResolver->resolve(
        $execution->controller,
        $execution->metadata,
        $execution->context
    );
```

Posibles interceptores:

* Authorization.
* Validation.
* Transaction.
* Cache.
* Idempotency.
* Tenant isolation.
* Audit.
* Tracing.
* Retry.
* Timeout.
* Feature flags.

---

## 23. AuthorizationStage

La autorización declarativa deberá ejecutarse después de resolver argumentos, ya que puede depender de ellos.

Ejemplo:

```php
#[Authorize('update', subject: 'user')]
public function update(User $user): ResponseInterface
{
}
```

La etapa localizará el argumento `user` y lo enviará al sistema de autorización.

```php
final class AuthorizationStage implements
    ControllerExecutionStageInterface
{
    public function handle(
        ControllerExecution $execution,
        Closure $next
    ): ControllerExecution {
        if ($execution->metadata?->authorization === []) {
            return $next($execution);
        }

        $execution->state =
            ControllerExecutionState::Authorizing;

        $this->authorization->authorizeExecution($execution);

        $execution->state =
            ControllerExecutionState::Authorized;

        return $next($execution);
    }
}
```

Una denegación deberá lanzar una excepción de autorización.

---

## 24. ValidationStage

La validación declarativa también podrá depender de los argumentos resueltos.

Casos:

* Form Request.
* DTO.
* Atributos de parámetros.
* Atributos del método.
* Reglas asociadas a la ruta.

```php
$this->validation->validateExecution($execution);
```

Al finalizar, podrá:

* Reemplazar argumentos DTO por instancias validadas.
* Incorporar datos validados al contexto.
* Añadir errores estructurados.
* Lanzar una excepción de validación.

---

## 25. ControllerMiddlewareStage

Esta etapa ejecutará el middleware específico del controlador reutilizando el pipeline general.

```php
final class ControllerMiddlewareStage implements
    ControllerExecutionStageInterface
{
    public function __construct(
        private readonly ControllerMiddlewarePipelineInterface $pipeline
    ) {
    }

    public function handle(
        ControllerExecution $execution,
        Closure $next
    ): ControllerExecution {
        if ($execution->middleware === []) {
            return $next($execution);
        }

        $execution->state =
            ControllerExecutionState::RunningMiddleware;

        return $this->pipeline->run(
            execution: $execution,
            middleware: $execution->middleware,
            destination: $next
        );
    }
}
```

El middleware podrá devolver una respuesta anticipada.

---

## 26. ControllerInterceptorsStage

Esta etapa envolverá la invocación con interceptores.

```php
final class ControllerInterceptorsStage implements
    ControllerExecutionStageInterface
{
    public function __construct(
        private readonly ControllerInterceptorPipelineInterface $pipeline
    ) {
    }

    public function handle(
        ControllerExecution $execution,
        Closure $next
    ): ControllerExecution {
        if ($execution->interceptors === []) {
            return $next($execution);
        }

        return $this->pipeline->run(
            execution: $execution,
            interceptors: $execution->interceptors,
            destination: $next
        );
    }
}
```

Los interceptores operarán sobre la invocación concreta, no directamente sobre la emisión HTTP.

---

## 27. InvokeControllerStage

Esta etapa será la responsable de ejecutar el método.

```php
final class InvokeControllerStage implements
    ControllerExecutionStageInterface
{
    public function __construct(
        private readonly ControllerInvokerInterface $invoker,
        private readonly ControllerLifecycleInterface $lifecycle,
    ) {
    }

    public function handle(
        ControllerExecution $execution,
        Closure $next
    ): ControllerExecution {
        $execution->state =
            ControllerExecutionState::Invoking;

        $this->lifecycle->invoking(
            $execution->controller,
            $execution->arguments,
            $execution->context
        );

        $execution->result = $this->invoker->invoke(
            $execution->controller,
            $execution->arguments,
            $execution->context
        );

        $execution->state =
            ControllerExecutionState::Invoked;

        $this->lifecycle->invoked(
            $execution->controller,
            $execution->result,
            $execution->context
        );

        return $next($execution);
    }

    public function priority(): int
    {
        return 100;
    }
}
```

---

## 28. NormalizeControllerResultStage

Esta etapa convertirá el resultado en una respuesta.

```php
final class NormalizeControllerResultStage implements
    ControllerExecutionStageInterface
{
    public function __construct(
        private readonly ControllerResultNormalizerInterface $normalizer
    ) {
    }

    public function handle(
        ControllerExecution $execution,
        Closure $next
    ): ControllerExecution {
        if ($execution->response !== null) {
            return $next($execution);
        }

        $execution->state =
            ControllerExecutionState::Normalizing;

        $execution->response = $this->normalizer->normalize(
            $execution->result,
            $execution->context,
            $execution->metadata
        );

        $execution->state =
            ControllerExecutionState::ResponseCreated;

        return $next($execution);
    }
}
```

Si una etapa previa ya produjo una respuesta, no deberá normalizarse de nuevo.

---

## 29. FinalizeControllerResponseStage

Esta etapa aplicará transformaciones finales controladas.

Posibles operaciones:

* Añadir headers de seguridad.
* Incorporar metadata SPA.
* Aplicar cookies.
* Añadir información de tracing.
* Resolver redirects SPA.
* Preparar respuesta parcial.
* Ejecutar transformadores registrados.
* Validar compatibilidad con el kernel.

```php
$execution->response = $this->responseFinalizer->finalize(
    $execution->response,
    $execution
);
```

No deberá emitir la respuesta.

---

## 30. CompleteExecutionStage

Esta etapa marcará la ejecución como finalizada.

```php
final class CompleteExecutionStage implements
    ControllerExecutionStageInterface
{
    public function __construct(
        private readonly ControllerLifecycleInterface $lifecycle
    ) {
    }

    public function handle(
        ControllerExecution $execution,
        Closure $next
    ): ControllerExecution {
        $execution = $next($execution);

        if ($execution->response === null) {
            throw MissingControllerResponseException::create(
                $execution->executionId
            );
        }

        $execution->state =
            ControllerExecutionState::Finished;

        $execution->timings['finished_at'] = hrtime(true);

        $this->lifecycle->finished(
            $execution->response,
            $execution->context
        );

        return $execution;
    }
}
```

---

## 31. Short-circuiting

Una etapa podrá detener la ejecución produciendo directamente una respuesta.

Ejemplos:

* Respuesta desde caché.
* Middleware que rechaza acceso.
* Rate limit.
* Modo mantenimiento.
* Idempotency key ya procesada.
* Feature flag deshabilitado.
* Preflight request.
* Redirect de autenticación.

Ejemplo:

```php
if ($cached = $this->cache->find($execution)) {
    $execution->response = $cached;
    $execution->attributes['short_circuited'] = true;
    $execution->attributes['short_circuit_stage'] =
        static::class;

    return $execution;
}
```

---

## 32. Reglas de short-circuiting

Cuando una etapa produzca una respuesta anticipada:

* No deberá ejecutarse el controlador.
* No deberá ejecutarse la normalización normal.
* Sí deberán ejecutarse finalización y limpieza.
* Sí deberán publicarse eventos de respuesta.
* Deberá registrarse la causa.
* La respuesta deberá validar `ResponseInterface`.
* El estado deberá mantenerse coherente.

El pipeline podrá distinguir etapas que siempre deben ejecutarse.

---

## 33. Etapas garantizadas

Algunas etapas deberán ejecutarse incluso después de un short-circuit:

```text
Response finalization
Lifecycle completion
Telemetry finalization
Context release
Resource cleanup
```

Para ello se utilizarán etapas envolventes o un finalizer externo al pipeline.

---

## 34. Manejo de excepciones

El dispatcher deberá preservar la excepción original.

```php
try {
    return $this->pipeline->execute($execution);
} catch (Throwable $exception) {
    $execution->exception = $exception;
    $execution->state = ControllerExecutionState::Failed;

    $this->lifecycle->failed(
        $exception,
        $execution->context
    );

    throw $this->exceptionEnricher->enrich(
        $exception,
        $execution
    );
}
```

El sistema no deberá envolver toda excepción indiscriminadamente si esto pierde su tipo original.

---

## 35. Enriquecimiento de excepciones

En lugar de reemplazar siempre la excepción, se podrá añadir contexto mediante una interfaz.

```php
interface ControllerExceptionContextInterface
{
    public function withControllerExecution(
        ControllerExecutionDebugInfo $debugInfo
    ): static;
}
```

Para excepciones externas, podrá utilizarse `ControllerExecutionException` con la excepción original como `previous`.

Información disponible:

* Execution ID.
* Clase.
* Método.
* Ruta.
* Estado.
* Stage actual.
* Parámetro problemático.
* Tenant.
* Correlation ID.
* Duraciones.
* Short-circuit.
* Metadata relevante.

---

## 36. ErrorBoundaryStage

Opcionalmente podrá existir una etapa envolvente que capture excepciones traducibles a respuesta.

Ejemplos:

* ValidationException.
* AuthorizationException.
* ModelNotFoundException.
* HttpException.
* CsrfException.
* RateLimitException.

```php
final class ErrorBoundaryStage implements
    ControllerExecutionStageInterface
{
    public function handle(
        ControllerExecution $execution,
        Closure $next
    ): ControllerExecution {
        try {
            return $next($execution);
        } catch (Throwable $exception) {
            if (! $this->mapper->supports($exception)) {
                throw $exception;
            }

            $execution->exception = $exception;
            $execution->response = $this->mapper->toResponse(
                $exception,
                $execution->context
            );

            return $execution;
        }
    }
}
```

La política general deberá coordinarse con el Exception Handler del framework.

---

## 37. Relación con el Exception Handler

Se recomienda:

```text
Excepciones esperadas del controlador
    └── Pueden mapearse dentro del execution pipeline

Excepciones inesperadas o fatales
    └── Se propagan al Exception Handler central
```

El pipeline no deberá duplicar completamente el manejo global de excepciones.

---

## 38. ControllerExecutionFinalizer

El finalizer será responsable de verificar, devolver y liberar la ejecución.

Contrato:

```php
interface ControllerExecutionFinalizerInterface
{
    public function response(
        ControllerExecution $execution
    ): ResponseInterface;

    public function release(
        ControllerExecution $execution
    ): void;
}
```

Implementación:

```php
final class ControllerExecutionFinalizer implements
    ControllerExecutionFinalizerInterface
{
    public function __construct(
        private readonly ControllerContextInjectorInterface $injector,
        private readonly ScopedResourceReleaserInterface $resources,
    ) {
    }

    public function response(
        ControllerExecution $execution
    ): ResponseInterface {
        return $execution->response
            ?? throw MissingControllerResponseException::create(
                $execution->executionId
            );
    }

    public function release(
        ControllerExecution $execution
    ): void {
        if (
            $execution->controller !== null
            && is_object($execution->controller->instance)
        ) {
            $this->injector->release(
                $execution->controller->instance
            );
        }

        $this->resources->releaseExecution($execution);

        $execution->state =
            ControllerExecutionState::Released;
    }
}
```

---

## 39. Liberación obligatoria

La liberación deberá realizarse dentro de `finally`.

Esto garantiza limpieza cuando:

* El controlador lanza una excepción.
* La autorización falla.
* La validación falla.
* Un middleware termina anticipadamente.
* La normalización falla.
* La respuesta final es inválida.
* Un listener lanza una excepción.

---

## 40. Recursos liberables

El finalizer podrá liberar:

* Contexto del controlador.
* Referencias a Request.
* Usuario actual.
* Tenant actual.
* Sesión request-scoped.
* Buffers temporales.
* Streams no entregados.
* Recursos de profiling.
* Contextos de tracing.
* Unit of Work request-scoped.
* Transacciones abiertas accidentalmente.
* Listeners temporales.
* Closures capturadas.

Esto es especialmente importante para FrankenPHP.

---

## 41. Prioridades de etapas

Las etapas usarán prioridades explícitas.

Convención propuesta:

```text
1000–900  Inicialización y resolución
899–700   Metadata y argumentos
699–500   Seguridad y validación
499–300   Middleware e interceptores
299–100   Invocación
99–0      Normalización y finalización
```

Ejemplo:

```php
public function priority(): int
{
    return 700;
}
```

La colección será ordenada de mayor a menor prioridad.

---

## 42. Posiciones semánticas

Además de prioridad numérica, podrá soportarse posicionamiento declarativo.

```php
$stages->add(
    stage: TenantIsolationStage::class,
    after: AuthorizationStage::class,
    before: ValidationStage::class
);
```

Esto facilita extensiones de paquetes.

Reglas:

* `before` y `after` tendrán preferencia sobre prioridad.
* Las dependencias circulares producirán error.
* La resolución deberá compilarse.
* El orden final deberá ser visible en debug.

---

## 43. StageRegistry

```php
interface ControllerExecutionStageRegistryInterface
{
    public function add(
        string $stage,
        int $priority = 0,
        ?string $before = null,
        ?string $after = null
    ): void;

    public function remove(string $stage): void;

    public function replace(
        string $stage,
        string $replacement
    ): void;

    /**
     * @return list<ControllerExecutionStageInterface>
     */
    public function ordered(): array;
}
```

---

## 44. Extensión desde paquetes

Ejemplo:

```php
$controllers->stages()->add(
    stage: FeatureFlagStage::class,
    after: AuthorizationStage::class,
    before: ValidationStage::class
);
```

```php
$controllers->stages()->replace(
    AuthorizationStage::class,
    EnterpriseAuthorizationStage::class
);
```

```php
$controllers->stages()->remove(
    LegacyControllerHookStage::class
);
```

Los paquetes no deberán modificar directamente el dispatcher.

---

## 45. Compilación del pipeline

En producción, el pipeline podrá compilarse como una lista ordenada.

```php
return [
    InitializeExecutionStage::class,
    ResolveControllerStage::class,
    InjectControllerContextStage::class,
    ResolveControllerMetadataStage::class,
    ResolveArgumentsStage::class,
    AuthorizationStage::class,
    ValidationStage::class,
    ControllerMiddlewareStage::class,
    ControllerInterceptorsStage::class,
    InvokeControllerStage::class,
    NormalizeControllerResultStage::class,
    FinalizeControllerResponseStage::class,
    CompleteExecutionStage::class,
];
```

El framework no deberá ordenar etapas por petición.

---

## 46. CompiledControllerExecutionPlan

Además del pipeline global, cada controlador podrá tener un plan compilado.

```php
final readonly class CompiledControllerExecutionPlan
{
    public function __construct(
        public string $controllerClass,
        public string $method,
        public array $argumentResolvers,
        public array $middleware,
        public array $interceptors,
        public array $metadata,
        public ?string $resultNormalizer,
        public string $signatureHash,
    ) {
    }
}
```

Este plan podrá guardarse en:

```text
storage/framework/controllers/
```

o en la ubicación general de caché compilada de VoltStack.

---

## 47. Modo dinámico

Durante desarrollo:

```text
Reference parsing
    ↓
Reflection
    ↓
Attribute reading
    ↓
Resolver selection
    ↓
Dynamic execution
```

Ventajas:

* Hot reload.
* Mensajes descriptivos.
* Fácil extensión.
* Sin compilación obligatoria.

---

## 48. Modo compilado

En producción:

```text
Route Match
    ↓
Compiled execution plan
    ↓
Preselected resolvers
    ↓
Preordered middleware
    ↓
Preselected normalizer
    ↓
Execution
```

Ventajas:

* Menos Reflection.
* Menos iteraciones.
* Menos objetos temporales.
* Mejor rendimiento con FrankenPHP.
* Mayor previsibilidad.

---

## 49. Equivalencia funcional

El modo dinámico y el compilado deberán producir:

* Los mismos argumentos.
* El mismo orden de middleware.
* La misma autorización.
* La misma validación.
* La misma respuesta.
* Las mismas excepciones públicas.
* Los mismos eventos principales.

La compilación solo podrá optimizar, no cambiar semántica.

---

## 50. Soporte para closures

Las closures podrán ejecutarse mediante el mismo pipeline.

```php
Route::get('/health', function (): array {
    return ['status' => 'ok'];
});
```

El resolver producirá:

```php
ResolvedController(
    instance: $closure,
    method: $closure,
    definition: $definition,
    metadata: ...
);
```

Limitaciones:

* No podrán incluirse directamente en caché serializada de rutas.
* Deberán deshabilitar route cache o utilizar referencias compilables.
* No deberán capturar servicios request-scoped de forma persistente.
* Se recomendarán controladores invocables para producción.

---

## 51. Soporte para controladores invocables

```php
Route::get('/dashboard', DashboardController::class);
```

Plan:

```text
Controller class
    ↓
Container resolution
    ↓
Method: __invoke
    ↓
Argument resolution
    ↓
Invocation
```

No requerirá una etapa especial; será una variante de `ResolvedController`.

---

## 52. Soporte para Actions

```php
Route::post('/users', CreateUserAction::class);
```

El pipeline podrá detectar metadata `Action` y añadir interceptores específicos:

* Transaction.
* Idempotency.
* Audit.
* Domain event collection.
* Async dispatch.

La ejecución seguirá utilizando el mismo invoker.

---

## 53. Soporte para Page Controllers

```php
#[Page('dashboard')]
final class DashboardController
{
    public function __invoke(): array
    {
        return [
            'metrics' => [],
        ];
    }
}
```

El plan compilado podrá seleccionar directamente el normalizador Volt o SPA.

---

## 54. Soporte para API Controllers

Las rutas API podrán incluir metadata:

```php
[
    'response_format' => 'json',
    'stateless' => true,
    'api_version' => 'v1',
]
```

El pipeline seguirá siendo el mismo.

Cambiarán:

* Middleware.
* Validadores.
* Normalizador.
* Error mapper.
* Headers finales.

---

## 55. Soporte para streaming

Los controladores streaming podrán devolver:

```php
StreamResponse
Generator
Traversable
EventStreamResponse
```

El normalizador deberá conservar la naturaleza lazy del resultado.

El dispatcher no deberá consumir el stream anticipadamente.

La liberación de recursos deberá considerar que algunos recursos permanecen activos hasta terminar la emisión.

---

## 56. Finalización diferida para streams

En respuestas streaming, parte de la limpieza podrá diferirse hasta cerrar el stream.

```php
$response->onClose(
    fn () => $resourceReleaser->releaseStreamResources(
        $executionId
    )
);
```

No deberán mantenerse innecesariamente:

* Controller instance.
* Request completa.
* Session.
* Metadata extensa.
* Objetos de dominio.

Solo los recursos indispensables para el stream.

---

## 57. Soporte futuro para async y Fibers

La separación entre dispatcher, stages e invoker permitirá agregar:

```text
AsyncControllerInvoker
FiberControllerInvoker
CoroutineControllerInvoker
RemoteControllerInvoker
```

Sin cambiar la API pública del dispatcher.

Posible contrato futuro:

```php
interface AsyncControllerDispatcherInterface
{
    public function dispatchAsync(
        ControllerDefinition $definition,
        ControllerContext $context
    ): AwaitableResponse;
}
```

No formará parte de V1.

---

## 58. Middleware vs stages vs interceptores

### Stage

Forma parte del motor interno de ejecución.

Ejemplos:

* Resolver controlador.
* Resolver argumentos.
* Normalizar respuesta.

### Middleware

Opera principalmente sobre request y response.

Ejemplos:

* Authentication.
* CSRF.
* CORS.
* Locale.
* Maintenance.

### Interceptor

Rodea la invocación concreta.

Ejemplos:

* Transaction.
* Audit.
* Cache.
* Retry.
* Idempotency.

Regla:

```text
No usar middleware para resolver argumentos.
No usar stages para implementar lógica de aplicación.
No usar interceptores para buscar rutas.
```

---

## 59. Eventos del dispatcher

Eventos propuestos:

```text
ControllerDispatching
ControllerExecutionCreated
ControllerStageStarting
ControllerStageFinished
ControllerStageFailed
ControllerShortCircuited
ControllerResponseCreated
ControllerExecutionFinished
ControllerExecutionFailed
ControllerExecutionReleased
```

Los eventos de stage detallados podrán desactivarse en producción para reducir sobrecarga.

---

## 60. Observabilidad

Métricas:

```text
controller.dispatch.total
controller.dispatch.duration
controller.stage.duration
controller.stage.failures
controller.short_circuit.total
controller.execution.memory_delta
controller.execution.success
controller.execution.failure
controller.execution.release_duration
```

Tags:

```text
controller.class
controller.method
controller.type
route.name
stage.name
execution.state
short_circuit
response.status
runtime.mode
transport.type
```

---

## 61. Timings

El execution podrá registrar tiempos por stage.

```php
$execution->timings = [
    'started_at' => 0,
    'resolve_controller' => 0,
    'metadata' => 0,
    'arguments' => 0,
    'authorization' => 0,
    'validation' => 0,
    'middleware' => 0,
    'invocation' => 0,
    'normalization' => 0,
    'finalization' => 0,
    'total' => 0,
];
```

En producción, podrá utilizarse una implementación ligera o muestreo.

---

## 62. Debugging

La debug toolbar podrá mostrar:

```text
Controller
    App\Http\Controllers\UserController@show

Execution ID
    ctrl_01J...

Route
    users.show

Arguments
    User: model binding
    Request: request resolver
    UserRepository: container resolver

Stages
    ResolveController       0.08 ms
    ResolveMetadata         0.04 ms
    ResolveArguments        0.31 ms
    Authorization           0.12 ms
    Invocation              1.20 ms
    Normalization           0.07 ms

Response
    ViewResponse 200
```

Los valores sensibles deberán ocultarse.

---

## 63. Seguridad

El dispatcher deberá:

* Aceptar solo `ControllerDefinition` válida.
* No resolver nombres de clase desde input directo.
* Validar métodos públicos.
* Bloquear métodos mágicos no permitidos.
* Evitar ejecución duplicada.
* Evitar normalización duplicada.
* No exponer argumentos sensibles en logs.
* Aplicar autorización antes de invocación.
* Mantener aislamiento de tenant.
* Liberar estado request-scoped.
* Evitar reuse inseguro de controladores.
* Validar respuestas anticipadas.
* Respetar rutas stateless.
* No serializar closures inseguras.

---

## 64. Protección contra doble dispatch

Una misma ejecución no podrá procesarse dos veces.

```php
if ($execution->state !== ControllerExecutionState::Created) {
    throw ControllerExecutionAlreadyStartedException::create(
        $execution->executionId,
        $execution->state
    );
}
```

Esto evita:

* Doble invocación.
* Eventos duplicados.
* Transacciones repetidas.
* Respuestas inconsistentes.
* Reutilización accidental en workers persistentes.

---

## 65. Protección contra doble invocación

`InvokeControllerStage` deberá verificar:

```php
if ($execution->state === ControllerExecutionState::Invoked) {
    throw ControllerAlreadyInvokedException::create(...);
}
```

Además, podrá usar:

```php
$execution->attributes['controller_invoked'] = true;
```

como verificación interna adicional.

---

## 66. Compatibilidad con FrankenPHP

El dispatcher deberá asumir que el proceso continuará después de la respuesta.

Reglas:

* No guardar `ControllerExecution` en propiedades estáticas.
* No registrar listeners por petición sin removerlos.
* No reutilizar instancias de controladores por defecto.
* Liberar contexto en `finally`.
* Vaciar referencias grandes.
* No conservar request en registries globales.
* Usar scopes del container correctamente.
* Finalizar transacciones.
* Limpiar tracing local.
* Reiniciar servicios reseteables.

---

## 67. Reset de servicios

Servicios request-scoped que implementen:

```php
interface ResettableInterface
{
    public function reset(): void;
}
```

podrán limpiarse al terminar la ejecución.

El finalizer podrá coordinar:

```php
$this->resetter->resetScope(
    Scope::Request,
    $execution->executionId
);
```

Esto deberá integrarse con el bootstrap y runtime de FrankenPHP.

---

## 68. Configuración

Archivo sugerido:

```php
return [
    'dispatcher' => [
        'implementation' => ControllerDispatcher::class,

        'pipeline' => [
            'compiled' => env('APP_ENV') === 'production',
            'validate_stage_order' => true,
            'allow_short_circuit' => true,
        ],

        'execution' => [
            'track_state' => true,
            'track_timings' => env('APP_DEBUG'),
            'generate_id' => true,
            'release_context' => true,
        ],

        'stages' => [
            InitializeExecutionStage::class,
            ResolveControllerStage::class,
            InjectControllerContextStage::class,
            ResolveControllerMetadataStage::class,
            ResolveArgumentsStage::class,
            ResolveControllerMiddlewareStage::class,
            ResolveInterceptorsStage::class,
            AuthorizationStage::class,
            ValidationStage::class,
            ControllerMiddlewareStage::class,
            ControllerInterceptorsStage::class,
            InvokeControllerStage::class,
            NormalizeControllerResultStage::class,
            FinalizeControllerResponseStage::class,
            CompleteExecutionStage::class,
        ],

        'exceptions' => [
            'map_expected' => true,
            'preserve_original_type' => true,
            'include_debug_context' => env('APP_DEBUG'),
        ],
    ],
];
```

---

## 69. Registro en el contenedor

```php
$container->singleton(
    ControllerDispatcherInterface::class,
    ControllerDispatcher::class
);

$container->singleton(
    ControllerExecutionFactoryInterface::class,
    ControllerExecutionFactory::class
);

$container->singleton(
    ControllerExecutionPipelineInterface::class,
    ControllerExecutionPipeline::class
);

$container->singleton(
    ControllerExecutionStageRegistryInterface::class,
    ControllerExecutionStageRegistry::class
);

$container->singleton(
    ControllerExecutionFinalizerInterface::class,
    ControllerExecutionFinalizer::class
);
```

Las etapas podrán ser singletons siempre que sean stateless.

---

## 70. Integración con Routing

El router producirá o recuperará una definición.

```php
$routeMatch->controllerDefinition();
```

El HttpKernel ejecutará:

```php
$response = $dispatcher->dispatch(
    $routeMatch->controllerDefinition(),
    $controllerContextFactory->fromRouteMatch(
        request: $request,
        route: $routeMatch
    )
);
```

Routing no deberá conocer las etapas internas.

---

## 71. Integración con HttpKernel

Flujo:

```php
final class HttpKernel
{
    public function handle(
        RequestInterface $request
    ): ResponseInterface {
        return $this->middleware->handle(
            $request,
            function (RequestInterface $request): ResponseInterface {
                $routeMatch = $this->router->match($request);

                return $this->controllers->dispatch(
                    $routeMatch->controllerDefinition(),
                    $this->controllerContexts->create(
                        $request,
                        $routeMatch
                    )
                );
            }
        );
    }
}
```

---

## 72. Integración con el runtime SPA

El `ControllerContext` incluirá información como:

```php
$context->execution->isSpaNavigation();
$context->execution->isPartialRequest();
$context->execution->isPrefetchRequest();
```

El pipeline podrá añadir etapas opcionales:

```text
SpaRequestDetectionStage
SpaStateRestorationStage
SpaResponseMetadataStage
```

El dispatcher seguirá devolviendo `ResponseInterface`.

---

## 73. Integración con testing

El módulo Testing deberá proporcionar:

```text
FakeControllerDispatcher
FakeControllerExecutionPipeline
RecordingControllerStage
ControllerExecutionTestBuilder
ControllerExecutionAssertions
```

Ejemplo:

```php
$execution = ControllerExecutionTestBuilder::create()
    ->withController(UserController::class, 'show')
    ->withRouteParameter('user', 10)
    ->build();

$result = $pipeline->execute($execution);

expect($result)
    ->toHaveState(ControllerExecutionState::Finished)
    ->toHaveResponseStatus(200)
    ->toHaveExecutedStage(AuthorizationStage::class)
    ->toHaveInvokedControllerOnce();
```

---

## 74. Pruebas unitarias

Casos mínimos:

* Crea una ejecución.
* Ejecuta stages en orden.
* Permite stages envolventes.
* Permite short-circuit.
* No invoca controlador tras short-circuit.
* Normaliza resultado.
* Devuelve respuesta.
* Libera contexto.
* Libera contexto tras excepción.
* Conserva excepción original.
* Evita doble dispatch.
* Evita doble invocación.
* Detecta orden circular.
* Respeta prioridades.
* Compiled pipeline equivale a dynamic pipeline.

---

## 75. Pruebas de integración

* Routing → Dispatcher.
* Dispatcher → Resolver.
* Resolver → Container.
* Argument resolver → Invocation.
* Middleware → Short-circuit.
* Authorization → Denial response.
* Validation → Error response.
* Controller → View response.
* Controller → SPA response.
* Controller → Stream response.
* Exception → Global handler.
* FrankenPHP → Multiple requests.

---

## 76. Prueba de contaminación entre peticiones

```php
public function test_controller_context_is_not_shared_between_requests(): void
{
    $first = $this->dispatchRequest(
        user: User::fake(id: 1)
    );

    $second = $this->dispatchRequest(
        user: User::fake(id: 2)
    );

    expect($first->userId)->toBe(1);
    expect($second->userId)->toBe(2);
}
```

La prueba deberá ejecutar ambas peticiones en el mismo proceso y reutilizando el mismo dispatcher.

---

## 77. Benchmark inicial

Escenarios:

```text
Invokable controller sin argumentos
Controller con tres servicios
Controller con model binding
Controller con DTO validado
Controller con cinco middleware
Controller con SPA response
Controller con metadata compilada
```

Mediciones:

* Tiempo de dispatch.
* Tiempo por stage.
* Objetos creados.
* Memoria.
* Cache hits.
* Reflection calls.
* Rendimiento dinámico vs compilado.

---

## 78. Estructura de directorios

```text
src/
└── Quantum/
    └── Controllers/
        ├── ControllerDispatcher.php
        │
        ├── Contracts/
        │   ├── ControllerDispatcherInterface.php
        │   ├── ControllerExecutionFactoryInterface.php
        │   ├── ControllerExecutionPipelineInterface.php
        │   ├── ControllerExecutionStageInterface.php
        │   ├── ControllerExecutionStageRegistryInterface.php
        │   └── ControllerExecutionFinalizerInterface.php
        │
        ├── Execution/
        │   ├── ControllerExecution.php
        │   ├── ControllerExecutionState.php
        │   ├── ControllerExecutionFactory.php
        │   ├── ControllerExecutionPipeline.php
        │   ├── ControllerExecutionFinalizer.php
        │   ├── ExecutionIdGenerator.php
        │   └── CompiledControllerExecutionPlan.php
        │
        ├── Stages/
        │   ├── InitializeExecutionStage.php
        │   ├── ResolveControllerStage.php
        │   ├── InjectControllerContextStage.php
        │   ├── ResolveControllerMetadataStage.php
        │   ├── ResolveArgumentsStage.php
        │   ├── ResolveControllerMiddlewareStage.php
        │   ├── ResolveInterceptorsStage.php
        │   ├── AuthorizationStage.php
        │   ├── ValidationStage.php
        │   ├── ControllerMiddlewareStage.php
        │   ├── ControllerInterceptorsStage.php
        │   ├── InvokeControllerStage.php
        │   ├── NormalizeControllerResultStage.php
        │   ├── FinalizeControllerResponseStage.php
        │   ├── CompleteExecutionStage.php
        │   └── ErrorBoundaryStage.php
        │
        ├── Pipeline/
        │   ├── ControllerExecutionStageRegistry.php
        │   ├── StageOrderResolver.php
        │   ├── StageDependencyGraph.php
        │   └── CompiledStageLoader.php
        │
        ├── Exceptions/
        │   ├── ControllerDispatchException.php
        │   ├── ControllerExecutionAlreadyStartedException.php
        │   ├── InvalidControllerExecutionStateException.php
        │   ├── ControllerAlreadyInvokedException.php
        │   ├── MissingResolvedControllerException.php
        │   ├── MissingControllerResponseException.php
        │   ├── InvalidStageOrderException.php
        │   ├── CircularStageDependencyException.php
        │   └── ControllerStageExecutionException.php
        │
        ├── Events/
        │   ├── ControllerDispatching.php
        │   ├── ControllerExecutionCreated.php
        │   ├── ControllerStageStarting.php
        │   ├── ControllerStageFinished.php
        │   ├── ControllerStageFailed.php
        │   ├── ControllerShortCircuited.php
        │   ├── ControllerExecutionFinished.php
        │   ├── ControllerExecutionFailed.php
        │   └── ControllerExecutionReleased.php
        │
        ├── Compiler/
        │   ├── ControllerPipelineCompiler.php
        │   ├── ControllerExecutionPlanCompiler.php
        │   ├── CompiledExecutionPlanLoader.php
        │   └── ExecutionPlanCacheWriter.php
        │
        └── Testing/
            ├── FakeControllerDispatcher.php
            ├── FakeControllerExecutionPipeline.php
            ├── RecordingControllerStage.php
            ├── ControllerExecutionTestBuilder.php
            └── ControllerExecutionAssertions.php
```

---

## 79. Implementación mínima V1

La V1 deberá incluir:

* `ControllerDispatcherInterface`.
* `ControllerDispatcher`.
* `ControllerExecution`.
* `ControllerExecutionState`.
* `ControllerExecutionFactory`.
* `ControllerExecutionPipeline`.
* Contrato de stage.
* Registro ordenado de stages.
* `ResolveControllerStage`.
* `ResolveArgumentsStage`.
* `InvokeControllerStage`.
* `NormalizeControllerResultStage`.
* `CompleteExecutionStage`.
* Finalizer.
* Liberación en `finally`.
* Excepciones básicas.
* Pruebas de short-circuit.
* Pruebas de múltiples requests.

Podrán posponerse:

* Stage dependency graph avanzado.
* Async dispatch.
* Fibers.
* Error boundary complejo.
* Timings detallados.
* Events por stage.
* Compiled execution plans por controlador.
* Finalización diferida de streams.

---

## 80. Ejemplo completo de dispatch

```php
$definition = new ControllerDefinition(
    type: ControllerType::ClassMethod,
    target: [UserController::class, 'show'],
    class: UserController::class,
    method: 'show',
);

$context = $contextFactory->create(
    request: $request,
    route: $routeMatch,
    user: $authenticatedUser,
    tenant: $tenant
);

$response = $dispatcher->dispatch(
    $definition,
    $context
);
```

Pipeline:

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

---

## 81. Ejemplo de extensión

Paquete empresarial:

```php
final class TenantIsolationStage implements
    ControllerExecutionStageInterface
{
    public function __construct(
        private readonly TenantIsolationManagerInterface $isolation
    ) {
    }

    public function handle(
        ControllerExecution $execution,
        Closure $next
    ): ControllerExecution {
        $this->isolation->assertExecutionIsIsolated(
            $execution
        );

        return $next($execution);
    }

    public function priority(): int
    {
        return 575;
    }
}
```

Registro:

```php
$controllers->stages()->add(
    stage: TenantIsolationStage::class,
    after: AuthorizationStage::class,
    before: ValidationStage::class
);
```

---

## 82. Decisiones arquitectónicas

### ADR-CTRL-DISP-001

**Decisión:** El dispatcher será una fachada pequeña.

**Razón:** Evita concentrar toda la lógica de ejecución en una sola clase.

---

### ADR-CTRL-DISP-002

**Decisión:** La ejecución se modelará mediante `ControllerExecution`.

**Razón:** Permite compartir estado de forma explícita y reduce parámetros entre componentes.

---

### ADR-CTRL-DISP-003

**Decisión:** La lógica se dividirá en stages.

**Razón:** Facilita extensibilidad, pruebas y compilación.

---

### ADR-CTRL-DISP-004

**Decisión:** Los stages podrán rodear la ejecución.

**Razón:** Permite métricas, transacciones, tracing y limpieza mediante composición.

---

### ADR-CTRL-DISP-005

**Decisión:** Se permitirá short-circuiting controlado.

**Razón:** Middleware, caché y seguridad pueden producir respuestas sin invocar el controlador.

---

### ADR-CTRL-DISP-006

**Decisión:** La limpieza se realizará en `finally`.

**Razón:** Garantiza seguridad en procesos persistentes incluso ante excepciones.

---

### ADR-CTRL-DISP-007

**Decisión:** El orden de stages será explícito y compilable.

**Razón:** Evita comportamiento accidental y trabajo repetido por petición.

---

### ADR-CTRL-DISP-008

**Decisión:** El modo compilado será equivalente al dinámico.

**Razón:** Las optimizaciones no deberán alterar el comportamiento de la aplicación.

---

### ADR-CTRL-DISP-009

**Decisión:** Middleware, stages e interceptores serán conceptos separados.

**Razón:** Cada uno opera en una capa distinta y evita mezclar responsabilidades.

---

### ADR-CTRL-DISP-010

**Decisión:** El dispatcher siempre devolverá `ResponseInterface`.

**Razón:** El HttpKernel necesita una salida uniforme independientemente del tipo de controlador.

---

## 83. Criterios de aceptación

El dispatcher se considerará correctamente implementado cuando:

* Pueda ejecutar una definición de clase y método.
* Pueda ejecutar un controlador invocable.
* Pueda ejecutar una closure.
* Los stages se ejecuten en orden determinista.
* Puedan añadirse stages sin modificar el dispatcher.
* El controlador solo se invoque una vez.
* Un stage pueda terminar anticipadamente.
* Una respuesta anticipada llegue al kernel.
* Los argumentos estén resueltos antes de autorización e invocación.
* El resultado se normalice a `ResponseInterface`.
* El contexto se libere en toda circunstancia.
* Una excepción preserve su causa original.
* El estado de ejecución sea coherente.
* No pueda reutilizarse una ejecución finalizada.
* El modo compilado y dinámico sean equivalentes.
* No exista contaminación entre peticiones.
* La arquitectura sea compatible con FrankenPHP.
* El orden de etapas pueda inspeccionarse.
* Las métricas puedan asociarse a un Execution ID.
* El dispatcher no conozca detalles de Volt, SPA o API.

---

## 84. Conclusión

El `ControllerDispatcher` de VoltStack no será una clase encargada de ejecutar directamente métodos de controlador.

Será la entrada a un motor de ejecución modular compuesto por:

* Un objeto de ejecución.
* Un pipeline ordenado.
* Etapas independientes.
* Interceptores.
* Middleware.
* Un invoker.
* Un normalizador.
* Un finalizer.

Esta arquitectura permite que VoltStack incorpore autorización, validación, transacciones, caché, tenancy, telemetría, SPA, APIs, streaming y futuras capacidades asíncronas sin transformar el dispatcher en una clase monolítica.

Además, la limpieza obligatoria del contexto y la ausencia de estado global hacen que el sistema sea seguro para runtimes persistentes como FrankenPHP.

El dispatcher será, por tanto, el punto central de coordinación del sistema de controladores, pero no el propietario de todas sus responsabilidades.
