# Sistema de invocación de controladores de VoltStack

**Versión:** 1.0
**Estado:** Draft
**Módulo:** `VoltStack\Quantum\Controllers\Invocation`

**Documentos relacionados:**

```text
00_CONTROLLER_PROJECT_CONTEXT.md
01_CONTROLLER_ARCHITECTURE.md
02_CONTROLLER_BASE_CLASS.md
03_CONTROLLER_DISPATCHER.md
04_CONTROLLER_RESOLVER.md
05_PARAMETER_RESOLUTION_ENGINE.md
06_METADATA_ENGINE.md
07_CONTROLLER_INTERCEPTOR_SYSTEM.md
```

---

## 1. Propósito

Este documento define la arquitectura del `Controller Invoker` de VoltStack.

El invoker será el componente responsable de ejecutar físicamente un controlador previamente resuelto, utilizando los parámetros producidos por el `Parameter Resolution Engine`.

Su responsabilidad será estricta:

```text
ResolvedController
        +
ResolvedParameterBag
        +
ControllerExecution
        ↓
ControllerInvoker
        ↓
Raw Controller Result
```

El invoker no deberá:

* Resolver controladores.
* Crear metadata.
* Resolver parámetros desde el request.
* Ejecutar autorización.
* Ejecutar validación.
* Ejecutar middleware.
* Ejecutar interceptores.
* Normalizar resultados.
* Construir respuestas HTTP.
* Mapear excepciones globalmente.
* Abrir transacciones.
* Resolver rutas.

Su única función será invocar correctamente el callable asociado al controlador y devolver su resultado bruto.

---

## 2. Posición dentro del flujo

El flujo completo será:

```text
Routing
    ↓
ControllerDefinition
    ↓
ControllerResolver
    ↓
ResolvedController
    ↓
Metadata Engine
    ↓
Parameter Resolution Engine
    ↓
Controller Interceptor Pipeline
    ↓
Controller Invocation Terminal
    ↓
Controller Invoker
    ↓
Raw Result
    ↓
Result Normalization System
    ↓
Response
```

Dentro del sistema de interceptores:

```text
Interceptor A
    ↓
Interceptor B
    ↓
Interceptor C
    ↓
ControllerInvocationTerminal
    ↓
ControllerInvoker
```

---

## 3. Objetivos

El invoker deberá:

* Invocar métodos de clases.
* Invocar controladores invocables.
* Invocar closures.
* Invocar servicios registrados.
* Invocar Actions.
* Invocar Pages.
* Invocar Components cuando corresponda.
* Invocar handlers compilados.
* Respetar el orden de parámetros.
* Soportar argumentos nombrados cuando sea necesario.
* Soportar argumentos variádicos.
* Detectar argumentos faltantes.
* Validar compatibilidad básica de tipos.
* Impedir doble invocación accidental.
* Registrar intentos de invocación.
* Capturar métricas.
* Mantener trazabilidad.
* Eliminar Reflection en producción cuando exista un plan compilado.
* Ser compatible con OPcache.
* Ser seguro en FrankenPHP.
* Mantener equivalencia entre modo dinámico y compilado.
* Permitir extensiones mediante estrategias especializadas.
* Mantener un overhead mínimo.

---

## 4. No responsabilidades

El `ControllerInvoker` no será responsable de determinar:

```text
Qué controlador debe ejecutarse
```

Eso corresponde al:

```text
ControllerResolver
```

Tampoco determinará:

```text
Qué valores deben recibir los parámetros
```

Eso corresponde al:

```text
ParameterResolutionEngine
```

Tampoco decidirá:

```text
Qué comportamiento transversal se aplica
```

Eso corresponde al:

```text
ControllerInterceptorSystem
```

Ni decidirá:

```text
Cómo convertir el resultado a Response
```

Eso corresponde al:

```text
ResultNormalizationSystem
```

---

## 5. Principios de diseño

El sistema seguirá estos principios:

1. El invoker tendrá una única responsabilidad.
2. Toda invocación utilizará un `ResolvedController`.
3. Los parámetros deberán estar resueltos antes de invocar.
4. El orden de argumentos deberá ser determinista.
5. El callable deberá validarse antes de ejecutarse.
6. La invocación accidental duplicada será rechazada.
7. Los retries deberán declararse explícitamente.
8. Reflection será un mecanismo de desarrollo, no una dependencia obligatoria en producción.
9. Los planes compilados deberán ser inmutables.
10. El resultado deberá devolverse sin normalización.
11. Las excepciones del controlador deberán conservar su origen.
12. El invoker no deberá ocultar errores de programación.
13. El invoker no deberá mantener estado entre requests.
14. Los callables dinámicos deberán estar controlados.
15. Las extensiones deberán implementarse mediante estrategias registradas.
16. El modo compilado y dinámico deberán producir el mismo resultado.
17. Las métricas no deberán alterar la semántica de ejecución.
18. Los parámetros sensibles no deberán incluirse en logs o trazas.

---

## 6. Arquitectura general

```text
ControllerInvocationTerminal
        │
        ▼
ControllerInvocationRequest
        │
        ▼
ControllerInvoker
        │
        ├── InvocationGuard
        ├── InvocationPlanResolver
        ├── InvocationStrategyRegistry
        ├── ArgumentAssembler
        ├── CallableValidator
        ├── InvocationRecorder
        └── InvocationLifecycle
        │
        ▼
InvocationStrategy
        │
        ├── CompiledInvocationStrategy
        ├── ClassMethodInvocationStrategy
        ├── InvokableClassInvocationStrategy
        ├── ClosureInvocationStrategy
        ├── ServiceInvocationStrategy
        ├── ActionInvocationStrategy
        ├── PageInvocationStrategy
        ├── ComponentInvocationStrategy
        └── CustomInvocationStrategy
        │
        ▼
Raw Result
```

---

## 7. Componentes fundamentales

El sistema estará compuesto por:

```text
ControllerInvokerInterface
ControllerInvoker
ControllerInvocationRequest
ControllerInvocationResult
ControllerInvocationPlan
CompiledControllerInvocationPlan
ControllerInvocationStrategyInterface
ControllerInvocationStrategyRegistry
ControllerInvocationPlanResolver
ControllerArgumentAssembler
ControllerCallableValidator
ControllerInvocationGuard
ControllerInvocationRecorder
ControllerInvocationLifecycle
ControllerInvocationAttempt
ControllerInvocationMode
ControllerInvocationStatus
ControllerInvocationType
```

---

## 8. Contrato principal

```php
namespace VoltStack\Quantum\Controllers\Invocation\Contracts;

use VoltStack\Quantum\Controllers\Execution\ControllerExecution;
use VoltStack\Quantum\Controllers\Parameters\ResolvedParameterBag;

interface ControllerInvokerInterface
{
    public function invoke(
        ResolvedController $controller,
        ResolvedParameterBag $parameters,
        ControllerExecution $execution
    ): mixed;
}
```

El resultado será `mixed` porque el controlador podrá devolver cualquier valor admitido por VoltStack.

---

## 9. Firma alternativa mediante request object

Para reducir el número de argumentos internos podrá utilizarse:

```php
interface ControllerInvokerInterface
{
    public function invoke(
        ControllerInvocationRequest $request
    ): mixed;
}
```

Implementación recomendada:

```php
final readonly class ControllerInvocationRequest
{
    public function __construct(
        public ResolvedController $controller,
        public ResolvedParameterBag $parameters,
        public ControllerExecution $execution,
        public ControllerInvocationMode $mode =
            ControllerInvocationMode::Auto,
        public ?ControllerInvocationPlan $plan = null,
        public array $attributes = [],
    ) {
    }
}
```

La API pública preferida será el objeto request.

---

## 10. ControllerInvocationMode

```php
enum ControllerInvocationMode: string
{
    case Auto = 'auto';
    case Dynamic = 'dynamic';
    case Compiled = 'compiled';
    case CompiledStrict = 'compiled_strict';
    case Debug = 'debug';
}
```

Comportamiento:

```text
Auto
    → usar plan compilado válido
    → fallback dinámico

Dynamic
    → resolver estrategia en runtime

Compiled
    → preferir plan compilado
    → fallback dinámico

CompiledStrict
    → exigir plan compilado válido

Debug
    → invocación dinámica con validación y trace completo
```

---

## 11. Implementación principal

```php
final class ControllerInvoker implements
    ControllerInvokerInterface
{
    public function __construct(
        private readonly ControllerInvocationGuardInterface $guard,
        private readonly ControllerInvocationPlanResolverInterface $plans,
        private readonly ControllerInvocationStrategyRegistryInterface $strategies,
        private readonly ControllerArgumentAssemblerInterface $arguments,
        private readonly ControllerCallableValidatorInterface $validator,
        private readonly ControllerInvocationRecorderInterface $recorder,
        private readonly ControllerInvocationLifecycleInterface $lifecycle,
    ) {
    }

    public function invoke(
        ControllerInvocationRequest $request
    ): mixed {
        $this->guard->assertCanInvoke($request);

        $plan = $request->plan
            ?? $this->plans->resolve($request);

        $strategy = $this->strategies->resolve(
            $plan->invocationType
        );

        $arguments = $this->arguments->assemble(
            request: $request,
            plan: $plan,
        );

        $this->validator->validate(
            request: $request,
            plan: $plan,
            arguments: $arguments,
        );

        $attempt = $this->lifecycle->starting(
            $request,
            $plan
        );

        try {
            $result = $strategy->invoke(
                request: $request,
                plan: $plan,
                arguments: $arguments,
            );

            $this->lifecycle->succeeded(
                $request,
                $plan,
                $attempt,
                $result
            );

            return $result;
        } catch (\Throwable $exception) {
            $this->lifecycle->failed(
                $request,
                $plan,
                $attempt,
                $exception
            );

            throw $exception;
        } finally {
            $this->lifecycle->completed(
                $request,
                $plan,
                $attempt
            );
        }
    }
}
```

---

## 12. ControllerInvocationType

```php
enum ControllerInvocationType: string
{
    case ClassMethod = 'class_method';
    case InvokableClass = 'invokable_class';
    case Closure = 'closure';
    case ServiceMethod = 'service_method';
    case Action = 'action';
    case Resource = 'resource';
    case Page = 'page';
    case Component = 'component';
    case StaticMethod = 'static_method';
    case CallableObject = 'callable_object';
    case Compiled = 'compiled';
    case Custom = 'custom';
}
```

Este enum estará relacionado con:

```php
ControllerResolutionType
```

pero no necesariamente será idéntico.

El resolver describe cómo fue localizado el controlador.

El invoker describe cómo será ejecutado.

---

## 13. Mapeo entre resolución e invocación

```text
ControllerResolutionType::ClassMethod
    → ControllerInvocationType::ClassMethod

ControllerResolutionType::InvokableClass
    → ControllerInvocationType::InvokableClass

ControllerResolutionType::Closure
    → ControllerInvocationType::Closure

ControllerResolutionType::Service
    → ControllerInvocationType::ServiceMethod

ControllerResolutionType::Action
    → ControllerInvocationType::Action

ControllerResolutionType::Resource
    → ControllerInvocationType::Resource

ControllerResolutionType::Page
    → ControllerInvocationType::Page

ControllerResolutionType::Component
    → ControllerInvocationType::Component

ControllerResolutionType::Compiled
    → ControllerInvocationType::Compiled
```

---

## 14. ControllerInvocationPlan

```php
final readonly class ControllerInvocationPlan
{
    public function __construct(
        public string $controllerIdentity,
        public ControllerInvocationType $invocationType,
        public ?object $instance,
        public ?string $className,
        public ?string $methodName,
        public mixed $callable,
        public array $parameterOrder,
        public array $parameterNames,
        public array $variadicParameters,
        public bool $usesNamedArguments,
        public bool $isStatic,
        public bool $isPublic,
        public bool $returnsByReference,
        public bool $compiled,
        public string $signature,
        public array $attributes = [],
        public array $metadata = [],
    ) {
    }
}
```

---

## 15. Responsabilidad del plan

El plan describe de forma completa cómo ejecutar un controlador.

Ejemplo conceptual:

```php
new ControllerInvocationPlan(
    controllerIdentity:
        'App\Http\Controllers\UserController::show',

    invocationType:
        ControllerInvocationType::ClassMethod,

    instance: $userController,

    className:
        UserController::class,

    methodName:
        'show',

    callable: null,

    parameterOrder: [
        'user',
        'includePosts',
    ],

    parameterNames: [
        0 => 'user',
        1 => 'includePosts',
    ],

    variadicParameters: [],

    usesNamedArguments: false,

    isStatic: false,

    isPublic: true,

    returnsByReference: false,

    compiled: false,

    signature: '...',
);
```

---

## 16. Plan dinámico y plan compilado

### Plan dinámico

Puede incluir:

* Instancia actual.
* ReflectionMethod.
* Callable.
* Información derivada en runtime.
* Metadata debug.

### Plan compilado

Debe incluir únicamente información estable:

* Clase.
* Método.
* Tipo de invocación.
* Orden de parámetros.
* Variadic positions.
* Named argument requirements.
* Hashes.
* Flags.
* Strategy ID.

No deberá contener:

* Request.
* Usuario.
* Tenant.
* Parámetros.
* Resultado.
* Servicios request-scoped.
* Instancias execution-scoped.
* Reflection objects.
* Closures no serializables.

---

## 17. CompiledControllerInvocationPlan

```php
final readonly class CompiledControllerInvocationPlan
{
    public function __construct(
        public string $controllerIdentity,
        public ControllerInvocationType $invocationType,
        public string $strategy,
        public ?string $className,
        public ?string $methodName,
        public array $parameterOrder,
        public array $parameterNames,
        public array $variadicPositions,
        public bool $usesNamedArguments,
        public bool $isStatic,
        public string $controllerSignatureHash,
        public string $parameterPlanHash,
        public string $metadataHash,
        public string $sourceHash,
        public string $registryHash,
        public string $frameworkVersion,
        public array $attributes = [],
    ) {
    }
}
```

---

## 18. Invocation Strategy

Cada tipo de invocación será manejado por una estrategia.

```php
interface ControllerInvocationStrategyInterface
{
    public function supports(
        ControllerInvocationType $type
    ): bool;

    public function invoke(
        ControllerInvocationRequest $request,
        ControllerInvocationPlan $plan,
        ControllerArgumentList $arguments
    ): mixed;

    public function type(): ControllerInvocationType;

    public function priority(): int;
}
```

---

## 19. Estrategias iniciales

```text
CompiledControllerInvocationStrategy
ClassMethodInvocationStrategy
InvokableClassInvocationStrategy
ClosureInvocationStrategy
ServiceMethodInvocationStrategy
ActionInvocationStrategy
ResourceInvocationStrategy
PageInvocationStrategy
ComponentInvocationStrategy
StaticMethodInvocationStrategy
CallableObjectInvocationStrategy
```

---

## 20. Registry de estrategias

```php
interface ControllerInvocationStrategyRegistryInterface
{
    public function register(
        ControllerInvocationStrategyInterface|string $strategy
    ): void;

    public function has(
        ControllerInvocationType $type
    ): bool;

    public function resolve(
        ControllerInvocationType $type
    ): ControllerInvocationStrategyInterface;

    public function replace(
        ControllerInvocationType $type,
        ControllerInvocationStrategyInterface|string $strategy
    ): void;

    public function remove(
        ControllerInvocationType $type
    ): void;

    public function all(): array;

    public function freeze(): void;
}
```

En producción deberá congelarse después del boot.

---

## 21. ClassMethodInvocationStrategy

Responsable de:

```php
[$instance, $method]
```

Implementación:

```php
final class ClassMethodInvocationStrategy implements
    ControllerInvocationStrategyInterface
{
    public function type(): ControllerInvocationType
    {
        return ControllerInvocationType::ClassMethod;
    }

    public function supports(
        ControllerInvocationType $type
    ): bool {
        return $type === $this->type();
    }

    public function invoke(
        ControllerInvocationRequest $request,
        ControllerInvocationPlan $plan,
        ControllerArgumentList $arguments
    ): mixed {
        if ($plan->instance === null) {
            throw MissingControllerInstanceException::forPlan(
                $plan
            );
        }

        if ($plan->methodName === null) {
            throw MissingControllerMethodException::forPlan(
                $plan
            );
        }

        return $plan->instance->{$plan->methodName}(
            ...$arguments->positional()
        );
    }

    public function priority(): int
    {
        return 800;
    }
}
```

---

## 22. InvokableClassInvocationStrategy

Ejemplo:

```php
final class CreateUserController
{
    public function __invoke(
        CreateUserData $data
    ): User {
        return User::create(
            $data->toArray()
        );
    }
}
```

Estrategia:

```php
return ($plan->instance)(
    ...$arguments->positional()
);
```

El método efectivo será:

```text
__invoke
```

---

## 23. ClosureInvocationStrategy

Ejemplo:

```php
Route::get('/health', function (): array {
    return ['status' => 'ok'];
});
```

La estrategia deberá:

* Confirmar que el callable es una `Closure`.
* Evitar serializarla en compilación.
* Permitir closures compilables solamente mediante un identificador estable cuando el framework lo soporte.
* Mantener el binding original.
* No rebindear `$this` automáticamente.
* No guardar la closure en cache persistente externa.

```php
return ($plan->callable)(
    ...$arguments->positional()
);
```

---

## 24. ServiceMethodInvocationStrategy

Permite invocar un servicio ya resuelto por el Container.

Ejemplo:

```php
Route::get('/reports', [
    'report.service',
    'generate',
]);
```

El plan deberá contener:

* Service ID.
* Instancia resuelta para la ejecución.
* Método.
* Scope del servicio.
* Firma validada.

El invoker no resolverá directamente el servicio; deberá recibirlo desde el resolver o desde un `InvocationInstanceResolver`.

---

## 25. ActionInvocationStrategy

Una Action puede usar distintos contratos.

Ejemplo:

```php
final class CreateUserAction
{
    public function execute(
        CreateUserData $data
    ): User {
    }
}
```

o:

```php
final class CreateUserAction
{
    public function handle(
        CreateUserData $data
    ): User {
    }
}
```

o:

```php
final class CreateUserAction
{
    public function __invoke(
        CreateUserData $data
    ): User {
    }
}
```

El `ActionControllerResolver` deberá determinar el método efectivo.

El invoker no deberá buscar entre múltiples convenciones en cada petición.

---

## 26. PageInvocationStrategy

Una Page podrá representar una unidad de ejecución orientada a UI.

Ejemplo:

```php
final class UserIndexPage
{
    public function render(): ViewResult
    {
    }
}
```

El resolver deberá indicar:

```text
methodName = render
```

La estrategia de Page podrá agregar validaciones específicas, pero no renderizará la respuesta final.

---

## 27. ComponentInvocationStrategy

Un componente podrá exponer acciones:

```php
final class CounterComponent
{
    public function increment(): ComponentState
    {
    }
}
```

La estrategia deberá recibir un `ResolvedController` previamente convertido en una definición de invocación válida.

No deberá implementar por sí misma:

* Hidratación.
* Validación de payload SPA.
* Verificación de checksum.
* Resolución de componente.
* Serialización del estado.

Esas responsabilidades pertenecen al sistema de componentes y al SPA Runtime.

---

## 28. StaticMethodInvocationStrategy

VoltStack podrá soportar métodos estáticos, pero no deberá promoverlos como opción principal.

Ejemplo:

```php
final class HealthController
{
    public static function status(): array
    {
        return ['status' => 'ok'];
    }
}
```

Invocación:

```php
return ($plan->className)::{$plan->methodName}(
    ...$arguments->positional()
);
```

Restricciones:

* El método deberá ser público.
* El método deberá ser realmente estático.
* No habrá inyección de dependencias en la instancia.
* Se advertirá sobre menor extensibilidad.
* Podrá desactivarse mediante configuración.

---

## 29. CallableObjectInvocationStrategy

Permite invocar objetos con:

```php
__invoke()
```

que no pertenezcan necesariamente al modelo formal de controlador.

Deberá utilizarse principalmente para integraciones y adaptadores.

---

## 30. CompiledControllerInvocationStrategy

La estrategia compilada deberá evitar:

* Reflection.
* Descubrimiento del método.
* Inspección de parámetros.
* Resolución de estrategia.
* Validación estructural repetida.

Ejemplo:

```php
return $request
    ->controller
    ->instance
    ->store(
        $arguments[0],
        $arguments[1]
    );
```

Para máxima optimización, VoltStack podrá generar invocadores especializados.

---

## 31. Invocador compilado especializado

Ejemplo generado:

```php
final class Invoke_UserController_store implements
    CompiledControllerCallableInterface
{
    public function invoke(
        object $controller,
        array $arguments
    ): mixed {
        return $controller->store(
            $arguments[0],
            $arguments[1]
        );
    }
}
```

Esto evita:

```php
$controller->{$method}(...$arguments);
```

y permite a OPcache optimizar una llamada estable.

---

## 32. Contrato compilado

```php
interface CompiledControllerCallableInterface
{
    public function invoke(
        object $controller,
        array $arguments
    ): mixed;
}
```

Para métodos estáticos:

```php
interface CompiledStaticControllerCallableInterface
{
    public function invoke(
        array $arguments
    ): mixed;
}
```

---

## 33. Consideraciones sobre rendimiento

La llamada:

```php
$controller->method(...$arguments);
```

ya es eficiente para la mayoría de los casos.

La generación de clases especializadas deberá justificarse mediante benchmarks.

VoltStack tendrá tres niveles:

```text
Nivel 1
    Invocación dinámica

Nivel 2
    Plan compilado + llamada dinámica estable

Nivel 3
    Invocador PHP generado y especializado
```

La V1 deberá implementar los niveles 1 y 2.

El nivel 3 podrá añadirse después de obtener métricas reales.

---

## 34. ControllerInvocationPlanResolver

```php
interface ControllerInvocationPlanResolverInterface
{
    public function resolve(
        ControllerInvocationRequest $request
    ): ControllerInvocationPlan;
}
```

Responsabilidades:

* Consultar plan compilado.
* Validar hashes.
* Determinar `ControllerInvocationType`.
* Seleccionar strategy ID.
* Construir plan dinámico si es necesario.
* Incorporar orden de parámetros.
* Incorporar flags de invocación.
* Aplicar metadata relevante.
* Cachear planes estáticos.

---

## 35. Flujo del plan resolver

```text
ControllerInvocationRequest
        ↓
Invocation Plan Cache
        ├── hit → return plan
        └── miss
              ↓
Compiled Plan Registry
        ├── valid → hydrate runtime plan
        └── unavailable/stale
              ↓
Dynamic Plan Factory
              ↓
Plan Validator
              ↓
Cache static plan
              ↓
Return plan
```

---

## 36. DynamicInvocationPlanFactory

```php
interface DynamicInvocationPlanFactoryInterface
{
    public function create(
        ControllerInvocationRequest $request
    ): ControllerInvocationPlan;
}
```

Podrá utilizar Reflection ya cacheada desde:

```text
ResolvedController
```

No deberá volver a inspeccionar la clase si la información ya está disponible.

---

## 37. Reutilización de ControllerResolutionPlan

El `ControllerResolver` ya produce:

```php
ControllerResolutionPlan
```

El invoker deberá reutilizarlo.

Ejemplo:

```text
ControllerResolutionPlan
    class = UserController
    method = show
    callable type = class-method
    reflection signature = abc123
```

Luego:

```text
ControllerInvocationPlan
    parameter order
    variadic rules
    named arguments
    invocation strategy
```

No se deberá duplicar información sin necesidad.

---

## 38. Argument Assembly

El invoker deberá convertir:

```php
ResolvedParameterBag
```

en una lista válida para invocación.

Contrato:

```php
interface ControllerArgumentAssemblerInterface
{
    public function assemble(
        ControllerInvocationRequest $request,
        ControllerInvocationPlan $plan
    ): ControllerArgumentList;
}
```

---

## 39. ControllerArgumentList

```php
final readonly class ControllerArgumentList
{
    public function __construct(
        private array $positional,
        private array $named,
        private array $metadata = [],
    ) {
    }

    public function positional(): array
    {
        return $this->positional;
    }

    public function named(): array
    {
        return $this->named;
    }

    public function count(): int
    {
        return count($this->positional);
    }

    public function metadata(): array
    {
        return $this->metadata;
    }
}
```

---

## 40. Argumentos posicionales

Por defecto, VoltStack utilizará argumentos posicionales.

Ejemplo:

```php
public function show(
    User $user,
    bool $includePosts = false
): UserResource
```

Bag:

```php
[
    'user' => $user,
    'includePosts' => true,
]
```

Invocación:

```php
$controller->show(
    $user,
    true
);
```

---

## 41. Orden de argumentos

El orden deberá provenir del:

```php
ParameterDefinition[]
```

o del:

```php
CompiledMethodParameterPlan
```

Nunca deberá depender del orden de inserción accidental de un array externo.

```php
$plan->parameterOrder = [
    'user',
    'includePosts',
];
```

---

## 42. Argumentos nombrados

PHP soporta argumentos nombrados:

```php
$controller->show(
    includePosts: true,
    user: $user,
);
```

VoltStack podrá utilizarlos cuando:

* El plan lo requiera.
* Existan parámetros omitidos intermedios.
* Un adaptador externo entregue argumentos por nombre.
* Se invoquen signatures especiales.

Sin embargo, no serán el modo predeterminado.

Razones:

* Mayor acoplamiento al nombre del parámetro.
* Cambiar nombres puede romper planes.
* Los argumentos posicionales son más predecibles.
* La compilación es más sencilla.

---

## 43. Named argument mode

```php
enum ControllerArgumentMode: string
{
    case Positional = 'positional';
    case Named = 'named';
    case Hybrid = 'hybrid';
}
```

El plan podrá declarar:

```php
public ControllerArgumentMode $argumentMode;
```

---

## 44. Restricciones de named arguments

En PHP:

* No se pueden pasar argumentos posicionales después de argumentos nombrados.
* Los nombres deben coincidir con la firma.
* Los nombres desconocidos generan error.
* El cambio de nombre de un parámetro puede romper compatibilidad.

El compiler deberá incluir los nombres en el hash de firma.

---

## 45. Parámetros variádicos

Ejemplo:

```php
public function report(
    string $format,
    int ...$ids
): Report
```

`ResolvedParameterBag` podrá contener:

```php
[
    'format' => 'pdf',
    'ids' => [10, 20, 30],
]
```

El assembler producirá:

```php
[
    'pdf',
    10,
    20,
    30,
]
```

---

## 46. VariadicParameterDefinition

```php
final readonly class VariadicParameterDefinition
{
    public function __construct(
        public string $name,
        public int $position,
        public ?string $elementType,
        public bool $allowsEmpty = true,
    ) {
    }
}
```

---

## 47. Reglas variádicas

El assembler deberá:

* Confirmar que el valor sea iterable.
* Expandirlo en orden.
* Validar cada elemento cuando sea posible.
* Permitir lista vacía.
* Evitar arrays asociativos ambiguos.
* Registrar el número de valores expandidos.
* No mezclar named arguments posteriores.

---

## 48. Parámetros opcionales

Ejemplo:

```php
public function index(
    int $page = 1,
    int $limit = 20
): array
```

El `Parameter Resolution Engine` ya deberá producir:

```php
page = 1
limit = 20
```

El invoker no deberá volver a aplicar valores predeterminados salvo como defensa de consistencia.

---

## 49. Parámetros omitidos

No se permitirá que el invoker omita parámetros requeridos silenciosamente.

Si falta un parámetro:

```text
MissingControllerInvocationArgumentException
```

Ejemplo:

```text
Controller:
UserController::show

Missing parameter:
$user

Position:
0

Expected type:
App\Models\User
```

---

## 50. Valores extra

Si el bag contiene valores que no pertenecen a la firma:

```text
UnexpectedControllerInvocationArgumentException
```

Esto podrá configurarse:

```text
strict
ignore
warn
```

Recomendación:

```text
strict
```

para planes compilados y desarrollo.

---

## 51. Parámetros por referencia

Ejemplo:

```php
public function mutate(
    string &$value
): void
```

Los parámetros por referencia no estarán soportados en V1.

Razones:

* Complican el `ResolvedParameterBag`.
* Introducen efectos laterales implícitos.
* Dificultan retries.
* Dificultan compilación.
* Dificultan trazabilidad.
* Son poco adecuados para controladores.

Se lanzará:

```text
UnsupportedControllerReferenceParameterException
```

---

## 52. Retorno por referencia

Los métodos que devuelven por referencia tampoco estarán soportados en V1.

```text
UnsupportedControllerReferenceReturnException
```

El resolver o compiler deberá detectarlos antes de runtime.

---

## 53. ControllerCallableValidator

```php
interface ControllerCallableValidatorInterface
{
    public function validate(
        ControllerInvocationRequest $request,
        ControllerInvocationPlan $plan,
        ControllerArgumentList $arguments
    ): void;
}
```

---

## 54. Validaciones estructurales

El validator deberá comprobar:

* El tipo de invocación está registrado.
* La instancia existe cuando es necesaria.
* La clase coincide.
* El método existe.
* El método es público.
* El método es estático cuando corresponde.
* El callable es invocable.
* El número de argumentos es correcto.
* Los parámetros requeridos están presentes.
* Los variádicos están correctamente expandidos.
* No existen argumentos extra.
* No existen referencias no soportadas.
* El plan no está obsoleto.
* La firma coincide con el plan compilado.
* El controlador no fue invocado de forma accidental previamente.

---

## 55. Validación de tipos

El `Parameter Resolution Engine` es responsable principal de producir valores compatibles.

El invoker podrá realizar validación defensiva.

Niveles:

```php
enum InvocationTypeValidationMode: string
{
    case None = 'none';
    case Basic = 'basic';
    case Strict = 'strict';
    case Debug = 'debug';
}
```

---

## 56. Basic type validation

Validará:

* Clases mediante `instanceof`.
* Enums.
* Tipos escalares básicos.
* Nullability.
* Variadic element type.

No intentará coercionar valores.

---

## 57. Strict type validation

Además podrá validar:

* Union types.
* Intersection types.
* Colecciones tipadas mediante metadata.
* DTO contracts.
* Generic-like metadata.
* Objetos proxy.
* Contratos especiales.

El modo estricto podrá tener mayor costo y se recomienda en desarrollo o compilación.

---

## 58. No coerción en el invoker

El invoker nunca deberá convertir:

```php
'15'
```

a:

```php
15
```

Esa responsabilidad pertenece a:

```text
ScalarCoercionResolver
```

Si llega un tipo incorrecto, el invoker deberá fallar.

---

## 59. ControllerInvocationGuard

```php
interface ControllerInvocationGuardInterface
{
    public function assertCanInvoke(
        ControllerInvocationRequest $request
    ): void;
}
```

Responsabilidades:

* Detectar doble invocación accidental.
* Detectar ejecución ya completada.
* Detectar ejecución cancelada.
* Detectar controlador no resuelto.
* Detectar parámetros no resueltos.
* Detectar plan incompatible.
* Validar permiso explícito de retry.
* Detectar recursión prohibida.

---

## 60. Estados de invocación

```php
enum ControllerInvocationStatus: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Invoking = 'invoking';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case ShortCircuited = 'short_circuited';
}
```

---

## 61. ControllerInvocationState

```php
final class ControllerInvocationState
{
    public ControllerInvocationStatus $status =
        ControllerInvocationStatus::Pending;

    public int $attempts = 0;

    public bool $retryAllowed = false;

    public ?int $startedAt = null;

    public ?int $finishedAt = null;

    public ?string $strategy = null;

    public ?string $planSignature = null;

    public array $records = [];

    public ?\Throwable $exception = null;
}
```

Este estado pertenecerá a:

```php
ControllerExecution
```

---

## 62. Doble invocación

Por defecto:

```php
if ($state->attempts > 0) {
    throw DuplicateControllerInvocationException::forExecution(
        $execution
    );
}
```

Una invocación repetida será válida únicamente cuando:

```text
retryAllowed = true
```

y exista un interceptor autorizado que haya iniciado un nuevo intento.

---

## 63. Retry permit

Se utilizará un objeto explícito:

```php
final readonly class ControllerRetryPermit
{
    public function __construct(
        public string $issuedBy,
        public int $maximumAttempts,
        public array $retryableExceptions,
        public string $reason,
    ) {
    }
}
```

El `RetryInterceptor` podrá adjuntarlo al execution state.

---

## 64. Invocation attempt

```php
final readonly class ControllerInvocationAttempt
{
    public function __construct(
        public int $number,
        public int $startedAt,
        public ?int $finishedAt,
        public ControllerInvocationStatus $status,
        public ?string $exceptionType = null,
        public ?int $durationNanoseconds = null,
        public array $metadata = [],
    ) {
    }
}
```

---

## 65. Lifecycle

```php
interface ControllerInvocationLifecycleInterface
{
    public function starting(
        ControllerInvocationRequest $request,
        ControllerInvocationPlan $plan
    ): ControllerInvocationAttempt;

    public function succeeded(
        ControllerInvocationRequest $request,
        ControllerInvocationPlan $plan,
        ControllerInvocationAttempt $attempt,
        mixed $result
    ): void;

    public function failed(
        ControllerInvocationRequest $request,
        ControllerInvocationPlan $plan,
        ControllerInvocationAttempt $attempt,
        \Throwable $exception
    ): void;

    public function completed(
        ControllerInvocationRequest $request,
        ControllerInvocationPlan $plan,
        ControllerInvocationAttempt $attempt
    ): void;
}
```

---

## 66. Lifecycle responsibilities

El lifecycle deberá:

* Cambiar el estado a `Invoking`.
* Incrementar intentos.
* Registrar hora de inicio.
* Registrar estrategia.
* Registrar firma del plan.
* Emitir eventos.
* Iniciar métricas.
* Registrar resultado exitoso.
* Registrar excepción.
* Calcular duración.
* Liberar referencias temporales.
* Actualizar `ControllerExecution`.

No deberá modificar el resultado.

---

## 67. ControllerInvocationRecorder

```php
interface ControllerInvocationRecorderInterface
{
    public function record(
        ControllerInvocationRecord $record
    ): void;
}
```

---

## 68. ControllerInvocationRecord

```php
final readonly class ControllerInvocationRecord
{
    public function __construct(
        public string $controllerIdentity,
        public ControllerInvocationType $type,
        public string $strategy,
        public int $attempt,
        public int $startedAt,
        public int $finishedAt,
        public int $durationNanoseconds,
        public ControllerInvocationStatus $status,
        public string $resultType,
        public ?string $exceptionType,
        public bool $compiled,
        public int $argumentCount,
        public array $metadata = [],
    ) {
    }
}
```

No deberá almacenar valores de argumentos.

---

## 69. Excepciones del controlador

El invoker deberá propagar la excepción original.

Ejemplo:

```php
throw new UserAlreadyExistsException();
```

No deberá envolverse automáticamente en:

```text
ControllerInvocationException
```

porque eso podría ocultar el tipo real.

---

## 70. Errores internos del invoker

Solo se utilizará una excepción de infraestructura cuando el fallo pertenezca al mecanismo de invocación.

Ejemplos:

* Método inexistente.
* Instancia incorrecta.
* Estrategia no registrada.
* Plan obsoleto.
* Argumentos faltantes.
* Callable inválido.
* Doble invocación.
* Scope incorrecto.

---

## 71. Enriquecimiento de excepciones

La información contextual deberá adjuntarse sin sustituir la excepción original.

Opciones:

* Exception context registry.
* Execution attributes.
* Telemetry span.
* Error handler context.
* Previous exception únicamente cuando sea necesario.

Ejemplo:

```php
$execution->attributes->set(
    'controller.invocation_context',
    new InvocationExceptionContext(
        controller: $plan->controllerIdentity,
        attempt: $attempt->number,
        strategy: $plan->invocationType->value,
    )
);
```

---

## 72. Errores nativos de PHP

Errores como:

```text
TypeError
ArgumentCountError
Error
```

deberán propagarse.

El invoker podrá enriquecer su contexto, pero no convertirlos silenciosamente en respuestas HTTP.

El `Controller Exception System` decidirá la representación final.

---

## 73. Async-like results

PHP no tiene un único modelo async estándar.

El invoker deberá devolver sin consumir:

* Generators.
* Fibers.
* Promises de librerías soportadas.
* Awaitables registrados.
* Streams.
* Lazy results.

Ejemplo:

```php
public function stream(): Generator
{
    yield from $this->source;
}
```

El invoker devolverá el `Generator`.

El normalizador decidirá cómo procesarlo.

---

## 74. Awaitable detection

Podrá existir:

```php
interface AwaitableResultInterface
{
    public function await(): mixed;
}
```

La decisión de hacer `await` no pertenecerá necesariamente al invoker.

Recomendación:

```text
Invoker
    devuelve awaitable

Result Normalizer o Runtime Adapter
    decide cómo consumirlo
```

Esto mantiene el invoker neutral.

---

## 75. Fiber support

Una `Fiber` no deberá iniciarse automáticamente salvo que el plan o runtime lo indique.

Metadata posible:

```text
controller.invocation.fiber = true
```

Esta capacidad se pospone para una versión avanzada.

---

## 76. Generator support

El invoker no deberá convertir un Generator a array.

Esto preserva:

* Streaming.
* Lazy evaluation.
* Bajo consumo de memoria.
* Respuestas progresivas.

---

## 77. Result classification

El lifecycle podrá registrar únicamente:

```php
get_debug_type($result)
```

Ejemplos:

```text
array
App\Models\User
Generator
VoltStack\Http\Response
null
```

No deberá serializar ni inspeccionar profundamente el resultado.

---

## 78. Invocation metadata

Keys propuestas:

```text
controller.invocation.mode
controller.invocation.strategy
controller.invocation.named_arguments
controller.invocation.type_validation
controller.invocation.allow_static
controller.invocation.allow_retry
controller.invocation.max_attempts
controller.invocation.timeout
controller.invocation.fiber
controller.invocation.debug
```

Estas keys serán definidas mediante el `Metadata Engine`.

---

## 79. ControllerInvocationMetadata

```php
final readonly class ControllerInvocationMetadata
{
    public function __construct(
        private MetadataBag $metadata
    ) {
    }

    public function mode(): ControllerInvocationMode
    {
        return $this->metadata->get(
            'controller.invocation.mode',
            ControllerInvocationMode::Auto
        );
    }

    public function argumentMode(): ControllerArgumentMode
    {
        return $this->metadata->get(
            'controller.invocation.argument_mode',
            ControllerArgumentMode::Positional
        );
    }

    public function typeValidation():
        InvocationTypeValidationMode
    {
        return $this->metadata->get(
            'controller.invocation.type_validation',
            InvocationTypeValidationMode::Basic
        );
    }

    public function allowStatic(): bool
    {
        return $this->metadata->get(
            'controller.invocation.allow_static',
            false
        );
    }
}
```

---

## 80. Plan validation

```php
interface ControllerInvocationPlanValidatorInterface
{
    public function validate(
        ControllerInvocationPlan $plan,
        ControllerInvocationRequest $request
    ): void;
}
```

Validadores:

```text
InvocationStrategyExistsValidator
ControllerInstanceValidator
ControllerMethodValidator
ControllerVisibilityValidator
StaticInvocationValidator
ParameterOrderValidator
VariadicParameterValidator
NamedArgumentValidator
ReferenceParameterValidator
CompiledPlanHashValidator
ControllerSignatureValidator
InvocationScopeValidator
```

---

## 81. Firma del controlador

La firma deberá representar:

* Clase.
* Método.
* Visibilidad.
* Static flag.
* Parámetros.
* Nombres.
* Posiciones.
* Tipos.
* Nullability.
* Defaults.
* Variadic.
* By-reference.
* Return type.
* Return-by-reference.
* Atributos relevantes.

Ejemplo conceptual:

```text
App\Http\Controllers\UserController::show
(
    App\Models\User $user,
    bool $includePosts = false
): App\Http\Resources\UserResource
```

---

## 82. ControllerSignatureHasher

```php
interface ControllerSignatureHasherInterface
{
    public function hash(
        ResolvedController $controller
    ): string;
}
```

El hash permitirá detectar que un plan compilado ya no coincide con el código.

---

## 83. Hash estable

El hash no deberá depender de:

* Dirección de memoria.
* Orden de objetos no determinista.
* Timestamps cuando se use compilación reproducible.
* Datos del request.
* Entorno no relevante.

Deberá depender de una representación normalizada de la firma.

---

## 84. Compilador

```php
interface ControllerInvocationCompilerInterface
{
    public function compile(
        ControllerInvocationCompilationRequest $request
    ): CompiledControllerInvocationPlan;
}
```

---

## 85. Compilation request

```php
final readonly class ControllerInvocationCompilationRequest
{
    public function __construct(
        public ResolvedController $controller,
        public CompiledMethodParameterPlan $parameters,
        public MetadataBag $metadata,
        public bool $generateSpecializedInvoker = false,
        public array $attributes = [],
    ) {
    }
}
```

---

## 86. Flujo de compilación

```text
ResolvedController
        ↓
Controller signature validation
        ↓
Parameter plan validation
        ↓
Invocation type selection
        ↓
Strategy selection
        ↓
Argument mode selection
        ↓
Build compiled plan
        ↓
Calculate hashes
        ↓
Optional specialized invoker generation
        ↓
Write PHP plan
        ↓
Register plan
```

---

## 87. Formato de plan compilado

```php
<?php

return [
    'controller' =>
        'App\Http\Controllers\UserController::show',

    'invocation_type' => 'class_method',

    'strategy' => 'class_method',

    'class' =>
        App\Http\Controllers\UserController::class,

    'method' => 'show',

    'parameter_order' => [
        'user',
        'includePosts',
    ],

    'parameter_names' => [
        0 => 'user',
        1 => 'includePosts',
    ],

    'variadic_positions' => [],

    'argument_mode' => 'positional',

    'static' => false,

    'controller_signature_hash' => '...',

    'parameter_plan_hash' => '...',

    'metadata_hash' => '...',

    'source_hash' => '...',

    'registry_hash' => '...',

    'framework_version' => '1.0.0',
];
```

---

## 88. Compiled plan registry

```php
interface CompiledControllerInvocationRegistryInterface
{
    public function has(
        string $controllerIdentity
    ): bool;

    public function get(
        string $controllerIdentity
    ): CompiledControllerInvocationPlan;

    public function register(
        CompiledControllerInvocationPlan $plan
    ): void;

    public function remove(
        string $controllerIdentity
    ): void;

    public function clear(): void;
}
```

En producción será inmutable.

---

## 89. Compiled plan hydration

Un plan compilado no deberá contener una instancia del controlador.

Durante runtime:

```text
Compiled plan
    +
ResolvedController instance
    ↓
ControllerInvocationPlan
```

Ejemplo:

```php
$runtimePlan = new ControllerInvocationPlan(
    controllerIdentity:
        $compiled->controllerIdentity,

    invocationType:
        $compiled->invocationType,

    instance:
        $request->controller->instance,

    className:
        $compiled->className,

    methodName:
        $compiled->methodName,

    callable:
        null,

    parameterOrder:
        $compiled->parameterOrder,

    parameterNames:
        $compiled->parameterNames,

    variadicParameters:
        $compiled->variadicPositions,

    usesNamedArguments:
        $compiled->usesNamedArguments,

    isStatic:
        $compiled->isStatic,

    isPublic:
        true,

    returnsByReference:
        false,

    compiled:
        true,

    signature:
        $compiled->controllerSignatureHash,
);
```

---

## 90. Plan cache

Niveles:

```text
L1 Execution Plan
L2 Request Cache
L3 Worker Memory Cache
L4 Compiled PHP Cache
```

---

## 91. L1 Execution Plan

El plan resuelto se almacenará en:

```php
$execution->invocationState->plan
```

Esto evita resolverlo varias veces durante interceptores o retries.

---

## 92. L2 Request Cache

Útil cuando el mismo controlador es inspeccionado varias veces durante una petición.

La key deberá incluir:

* Controller identity.
* Signature hash.
* Parameter plan hash.
* Metadata hash.
* Invocation mode.

---

## 93. L3 Worker Cache

Podrá almacenar:

* Planes inmutables.
* Strategy mappings.
* Parameter orders.
* Signature hashes.
* Compiled plan descriptors.

No podrá almacenar:

* Controller instance request-scoped.
* Execution.
* Request.
* Parameters.
* Result.
* Exception.

---

## 94. L4 PHP compiled cache

Los archivos PHP podrán cargarse con:

```php
require $path;
```

y aprovechar OPcache.

No se recomienda serialización PHP de objetos arbitrarios.

---

## 95. InvocationPlanCacheKey

```php
final readonly class InvocationPlanCacheKey
{
    public function __construct(
        public string $value
    ) {
    }

    public static function fromRequest(
        ControllerInvocationRequest $request
    ): self {
        return new self(
            hash('xxh128', implode('|', [
                $request->controller->displayName,
                $request->parameters->signature(),
                $request->execution->metadata->signature(),
                $request->mode->value,
            ]))
        );
    }
}
```

---

## 96. Invalidación

Un plan deberá invalidarse cuando cambie:

* Clase.
* Método.
* Firma.
* Nombre de parámetros.
* Tipos.
* Nullability.
* Defaults.
* Variadic status.
* Static flag.
* Visibility.
* Return type.
* Resolver plan.
* Parameter resolution plan.
* Metadata de invocación.
* Strategy registry.
* Strategy implementation version.
* Framework version.
* Configuration.
* Compiler version.

---

## 97. Stale plan behavior

En `Auto`:

```text
Plan stale
    → invalidate
    → dynamic fallback
```

En `Compiled`:

```text
Plan stale
    → warning
    → dynamic fallback
```

En `CompiledStrict`:

```text
Plan stale
    → StaleControllerInvocationPlanException
```

---

## 98. Compatibilidad con OPcache

El sistema deberá favorecer:

* Arrays PHP inmutables.
* Clases generadas deterministas.
* Rutas de archivos estables.
* Ausencia de `eval`.
* Ausencia de closures generadas dinámicamente.
* Calls directas cuando sea posible.
* Precarga opcional.

---

## 99. Preloading

En despliegues compatibles podrá generarse:

```php
opcache_compile_file(
    storage_path(
        'framework/controllers/invocation.php'
    )
);
```

La integración real dependerá del sistema de bootstrap y despliegue.

---

## 100. FrankenPHP

El invoker deberá ser seguro para workers persistentes.

Reglas:

* No almacenar `ControllerExecution` en singletons.
* No almacenar controller instances request-scoped en cache global.
* No mantener argumentos después de completar.
* No mantener resultados después de normalizarlos.
* No mantener excepciones globalmente.
* No reutilizar invocation state.
* No reutilizar attempts mutables.
* No compartir parameter bags.
* No guardar Request.
* Limpiar references en lifecycle.
* Validar scopes de instancias.
* Permitir reset entre requests.

---

## 101. Controller instance scope

El invoker no determinará el scope del controlador.

Esto corresponde al:

```text
ControllerResolver
+
Container
```

Sin embargo, deberá validar que el plan y la instancia sean compatibles.

Ejemplo:

```text
Plan:
UserController

Instance:
ReportController
```

Resultado:

```text
ControllerInstanceMismatchException
```

---

## 102. Singleton controller risks

Si un controlador singleton contiene estado mutable:

```php
final class UserController
{
    private ?User $currentUser = null;
}
```

puede contaminar peticiones.

El resolver y el scope validator deberán impedirlo cuando sea posible.

El invoker no deberá limpiar internamente controladores mal diseñados.

---

## 103. Invocation scope validation

El invoker podrá consultar:

```php
ControllerScope
```

desde metadata.

Políticas:

```text
Request
    permitido

Execution
    permitido

Transient
    permitido

Worker
    solo si stateless

Singleton
    solo si stateless y explícito
```

---

## 104. Static controller policy

Configuración:

```php
'allow_static_methods' => false,
```

En modo estricto, una ruta hacia un método estático deberá rechazarse durante compilación.

---

## 105. Visibility

Solo métodos públicos podrán invocarse.

No se utilizará:

```php
ReflectionMethod::setAccessible(true)
```

para ejecutar métodos privados o protegidos.

Razones:

* Rompe encapsulación.
* Amplía superficie de ataque.
* Dificulta análisis.
* Genera comportamiento inesperado.
* Puede cambiar entre versiones de PHP.

---

## 106. Magic methods

No se permitirán métodos mágicos como targets normales, excepto:

```text
__invoke
```

Se rechazarán:

```text
__call
__callStatic
__get
__set
```

como mecanismo de resolución de controladores.

El método deberá existir explícitamente.

---

## 107. Dynamic method names

Los nombres de métodos no deberán derivarse directamente de input del usuario.

Ejemplo inseguro:

```php
$method = $request->input('action');

$controller->{$method}();
```

El método deberá provenir de una definición de ruta validada o un registry seguro.

---

## 108. Callable security validator

```php
interface ControllerCallableSecurityValidatorInterface
{
    public function validate(
        ControllerInvocationPlan $plan,
        ControllerInvocationRequest $request
    ): void;
}
```

Validará:

* Clase autorizada.
* Namespace permitido.
* Método permitido.
* Método público.
* No magic dispatch.
* No callable arbitrario desde input.
* No static si está prohibido.
* No closure no confiable.
* No clase interna bloqueada.
* No método bloqueado.
* Scope permitido.

---

## 109. Namespace allowlist

Configuración posible:

```php
'security' => [
    'allowed_namespaces' => [
        'App\\Http\\Controllers\\',
        'App\\Actions\\',
    ],

    'blocked_namespaces' => [
        'Internal\\',
        'Tests\\Fixtures\\Unsafe\\',
    ],
],
```

---

## 110. Métodos bloqueados

Ejemplos:

```text
__construct
__destruct
__clone
serialize
unserialize
```

La lista podrá configurarse.

---

## 111. Parámetros sensibles

El invoker no deberá registrar valores de parámetros.

Podrá registrar únicamente:

```text
Parameter count
Parameter names en debug
Parameter source
Parameter types
Sensitive flag
```

Cuando un parámetro tenga:

```php
#[SensitiveParameter]
```

su nombre también podrá ocultarse en trazas detalladas.

---

## 112. Resultados sensibles

El invoker no deberá registrar el contenido del resultado.

Solo podrá registrar:

```php
get_debug_type($result)
```

y metadata segura.

---

## 113. Observabilidad

Métricas:

```text
controller.invoke.total
controller.invoke.duration
controller.invoke.failure
controller.invoke.success
controller.invoke.attempts
controller.invoke.retry
controller.invoke.plan.cache.hit
controller.invoke.plan.cache.miss
controller.invoke.compiled
controller.invoke.dynamic
controller.invoke.arguments.count
controller.invoke.strategy.duration
controller.invoke.validation.duration
controller.invoke.stale_plan
```

---

## 114. Tags recomendados

```text
controller.type
invocation.strategy
invocation.mode
compiled
outcome
attempt
argument.mode
result.category
```

Evitar:

* User ID.
* Tenant ID.
* Valores de ruta.
* Argumentos.
* Resultado serializado.
* Exception message.
* URLs completas con parámetros.

---

## 115. Eventos

```text
ControllerInvocationPreparing
ControllerInvocationPlanResolving
ControllerInvocationPlanResolved
ControllerInvocationPlanCacheHit
ControllerInvocationPlanCacheMiss
ControllerInvocationStarting
ControllerInvocationSucceeded
ControllerInvocationFailed
ControllerInvocationCompleted
ControllerInvocationRetrying
ControllerInvocationPlanCompiled
ControllerInvocationPlanInvalidated
ControllerInvocationRejected
```

---

## 116. Eventos y rendimiento

Los eventos detallados podrán desactivarse en producción.

Eventos mínimos recomendados:

```text
ControllerInvocationSucceeded
ControllerInvocationFailed
```

o integrarse directamente con telemetría para evitar overhead adicional.

---

## 117. Trace de invocación

Ejemplo:

```text
Controller Invocation

Controller:
App\Http\Controllers\UserController::show

Type:
class_method

Strategy:
ClassMethodInvocationStrategy

Mode:
compiled

Arguments:
2 positional

Variadic:
no

Attempt:
1

Duration:
0.84 ms

Result type:
App\Http\Resources\UserResource

Status:
succeeded
```

---

## 118. Debug de parámetros

En debug:

```text
Arguments

0. user
   Expected:
   App\Models\User

   Received:
   App\Models\User

   Source:
   route model binding

1. includePosts
   Expected:
   bool

   Received:
   bool

   Source:
   query

   Value:
   [not displayed]
```

---

## 119. CLI

Comandos propuestos:

```bash
php volt controller:invocation \
    App\\Http\\Controllers\\UserController@show
```

```bash
php volt controller:invocation-plan \
    App\\Http\\Controllers\\UserController@show
```

```bash
php volt controller:compile-invokers
```

```bash
php volt controller:clear-invocation-cache
```

```bash
php volt controller:validate-invocations
```

---

## 120. Ejemplo de CLI

```text
Controller invocation plan

Controller:
App\Http\Controllers\UserController::show

Invocation type:
class_method

Strategy:
class_method

Method:
show

Static:
no

Arguments:
1. user
2. includePosts

Argument mode:
positional

Variadic:
none

Compiled:
yes

Signature:
valid

Plan cache:
L3 hit
```

---

## 121. Excepciones

```text
ControllerInvocationException
ControllerInvocationPlanException
ControllerInvocationPlanValidationException
ControllerInvocationStrategyNotFoundException
ControllerInvocationStrategyConflictException
ControllerInvocationRejectedException
MissingControllerInstanceException
ControllerInstanceMismatchException
MissingControllerMethodException
InvalidControllerCallableException
NonPublicControllerMethodException
StaticControllerInvocationNotAllowedException
InvalidControllerStaticMethodException
MissingControllerInvocationArgumentException
UnexpectedControllerInvocationArgumentException
InvalidControllerInvocationArgumentException
ControllerArgumentTypeMismatchException
UnsupportedControllerReferenceParameterException
UnsupportedControllerReferenceReturnException
InvalidVariadicControllerArgumentException
InvalidNamedControllerArgumentException
DuplicateControllerInvocationException
ControllerInvocationRetryNotAllowedException
ControllerInvocationAttemptsExceededException
ControllerInvocationCancelledException
StaleControllerInvocationPlanException
NonCompilableControllerInvocationException
ControllerInvocationRegistryFrozenException
ControllerInvocationSecurityException
BlockedControllerNamespaceException
BlockedControllerMethodException
```

---

## 122. Ejemplo de error de argumento

```text
Controller invocation failed before execution.

Controller:
App\Http\Controllers\UserController::show

Parameter:
$user

Position:
0

Expected:
App\Models\User

Received:
string

Source:
RouteParameterResolver

Invocation mode:
compiled

The Parameter Resolution Engine produced a value that is not
compatible with the controller signature.
```

---

## 123. Ejemplo de plan obsoleto

```text
Compiled controller invocation plan is stale.

Controller:
App\Http\Controllers\UserController::show

Compiled signature:
c97fd0...

Current signature:
a423e1...

Detected change:
Parameter "$includePosts" was renamed to "$withPosts".

Action:
Rebuild the controller invocation cache.
```

---

## 124. Configuración

```php
return [
    'controllers' => [
        'invocation' => [
            'mode' => env('APP_ENV') === 'production'
                ? ControllerInvocationMode::Auto
                : ControllerInvocationMode::Dynamic,

            'arguments' => [
                'mode' =>
                    ControllerArgumentMode::Positional,

                'reject_unknown' => true,

                'validate_types' => env('APP_DEBUG')
                    ? InvocationTypeValidationMode::Strict
                    : InvocationTypeValidationMode::Basic,

                'support_variadic' => true,

                'support_references' => false,
            ],

            'methods' => [
                'allow_static' => false,

                'allow_magic' => [
                    '__invoke',
                ],

                'require_public' => true,
            ],

            'compiled' => [
                'enabled' =>
                    env('APP_ENV') === 'production',

                'strict' => false,

                'path' => storage_path(
                    'framework/controllers/invocation'
                ),

                'validate_hashes' => true,

                'generate_specialized_invokers' => false,

                'preload' => false,
            ],

            'cache' => [
                'execution' => true,
                'request' => true,
                'worker' => true,
                'compiled' => true,
            ],

            'retry' => [
                'allowed' => true,
                'require_permit' => true,
                'maximum_attempts' => 3,
            ],

            'observability' => [
                'record_timings' => true,
                'record_arguments' => false,
                'record_argument_types' =>
                    env('APP_DEBUG'),
                'record_result_type' => true,
                'emit_events' => env('APP_DEBUG'),
            ],

            'security' => [
                'allow_dynamic_methods' => false,
                'allow_arbitrary_callables' => false,
                'allow_untrusted_closures' => false,

                'allowed_namespaces' => [
                    'App\\Http\\Controllers\\',
                    'App\\Actions\\',
                ],

                'blocked_methods' => [
                    '__construct',
                    '__destruct',
                    '__clone',
                    '__call',
                    '__callStatic',
                    'serialize',
                    'unserialize',
                ],
            ],
        ],
    ],
];
```

---

## 125. Registro en el Container

```php
$container->singleton(
    ControllerInvokerInterface::class,
    ControllerInvoker::class
);

$container->singleton(
    ControllerInvocationPlanResolverInterface::class,
    ControllerInvocationPlanResolver::class
);

$container->singleton(
    DynamicInvocationPlanFactoryInterface::class,
    DynamicInvocationPlanFactory::class
);

$container->singleton(
    ControllerInvocationStrategyRegistryInterface::class,
    ControllerInvocationStrategyRegistry::class
);

$container->singleton(
    ControllerArgumentAssemblerInterface::class,
    ControllerArgumentAssembler::class
);

$container->singleton(
    ControllerCallableValidatorInterface::class,
    ControllerCallableValidator::class
);

$container->singleton(
    ControllerInvocationGuardInterface::class,
    ControllerInvocationGuard::class
);

$container->singleton(
    ControllerInvocationLifecycleInterface::class,
    ControllerInvocationLifecycle::class
);

$container->singleton(
    ControllerInvocationPlanValidatorInterface::class,
    ControllerInvocationPlanValidator::class
);

$container->singleton(
    ControllerInvocationCompilerInterface::class,
    ControllerInvocationCompiler::class
);

$container->singleton(
    CompiledControllerInvocationRegistryInterface::class,
    CompiledControllerInvocationRegistry::class
);

$container->singleton(
    ControllerInvocationPlanCacheInterface::class,
    LayeredControllerInvocationPlanCache::class
);
```

---

## 126. Registro de estrategias

```php
$registry->register(
    ClassMethodInvocationStrategy::class
);

$registry->register(
    InvokableClassInvocationStrategy::class
);

$registry->register(
    ClosureInvocationStrategy::class
);

$registry->register(
    ServiceMethodInvocationStrategy::class
);

$registry->register(
    ActionInvocationStrategy::class
);

$registry->register(
    PageInvocationStrategy::class
);

$registry->register(
    ComponentInvocationStrategy::class
);

$registry->register(
    StaticMethodInvocationStrategy::class
);

$registry->register(
    CompiledControllerInvocationStrategy::class
);
```

---

## 127. Bootstrapping

Durante `register`:

* Registrar invoker.
* Registrar plan resolver.
* Registrar assembler.
* Registrar validators.
* Registrar lifecycle.
* Registrar compiler.
* Registrar cache.
* Registrar registry de estrategias.

Durante `boot`:

* Registrar estrategias del núcleo.
* Registrar estrategias de módulos.
* Registrar metadata schemas.
* Cargar planes compilados.
* Validar hashes.
* Validar configuración.
* Validar estrategias duplicadas.
* Congelar registry en producción.

---

## 128. Extensión por paquetes

Un paquete podrá agregar una estrategia.

Ejemplo:

```php
final class GraphqlControllerInvocationStrategy implements
    ControllerInvocationStrategyInterface
{
}
```

Registro:

```php
$registry->register(
    GraphqlControllerInvocationStrategy::class
);
```

También podrá registrar un tipo custom mediante un identificador extensible en una versión futura.

---

## 129. Estrategias personalizadas

El enum nativo limita extensiones externas.

Por ello, una evolución posible será utilizar:

```php
final readonly class ControllerInvocationTypeId
{
    public function __construct(
        public string $value
    ) {
    }
}
```

V1 podrá mantener el enum para el núcleo y permitir:

```text
Custom
```

con:

```php
$plan->attributes['custom_type']
```

---

## 130. Integración con ControllerInvocationTerminal

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
        if ($execution->resolvedController === null) {
            throw MissingResolvedControllerException::forExecution(
                $execution
            );
        }

        if ($execution->parameters === null) {
            throw MissingResolvedParametersException::forExecution(
                $execution
            );
        }

        return $this->invoker->invoke(
            new ControllerInvocationRequest(
                controller:
                    $execution->resolvedController,

                parameters:
                    $execution->parameters,

                execution:
                    $execution,

                mode:
                    $execution->metadata
                        ->invocation()
                        ->mode(),
            )
        );
    }
}
```

---

## 131. Integración con ControllerExecution

Se añadirá:

```php
final class ControllerExecution
{
    public ControllerInvocationState $invocationState;
}
```

Inicialización:

```php
$this->invocationState =
    new ControllerInvocationState();
```

---

## 132. Integración con interceptores

El invoker será invocado únicamente desde el terminal.

```text
ControllerInterceptorPipeline
        ↓
ControllerInvocationTerminal
        ↓
ControllerInvoker
```

Ningún interceptor deberá llamar directamente al método del controlador.

Esto garantiza:

* Métricas uniformes.
* Detección de doble invocación.
* Planes compilados.
* Validación.
* Trace.
* Lifecycle.
* Seguridad.

---

## 133. RetryInterceptor integration

El retry interceptor deberá solicitar un permiso:

```php
$permit = new ControllerRetryPermit(
    issuedBy: RetryInterceptor::class,
    maximumAttempts: 3,
    retryableExceptions: [
        DeadlockException::class,
    ],
    reason: 'database deadlock retry',
);

$execution->invocationState
    ->allowRetry($permit);
```

Luego cada intento pasará nuevamente por el terminal.

---

## 134. Short circuit

Si un interceptor devuelve un resultado sin invocar el terminal:

```text
invocationState.status = ShortCircuited
```

No deberá registrarse como invocación exitosa.

Ejemplo:

```php
$execution->invocationState->status =
    ControllerInvocationStatus::ShortCircuited;
```

---

## 135. Integración con Result Normalizer

El invoker devuelve:

```php
mixed
```

Luego:

```php
$execution->result = $rawResult;
```

Posteriormente:

```text
NormalizeControllerResultStage
```

invocará el sistema de normalización.

El invoker no deberá convertir:

```php
User
```

en:

```php
JsonResponse
```

---

## 136. Integración con Exception System

Las excepciones atravesarán:

```text
ControllerInvoker
    ↓
Interceptor pipeline
    ↓
Controller Dispatcher
    ↓
Controller Exception System
```

El invoker únicamente:

* Registra.
* Enriquece contexto.
* Actualiza estado.
* Propaga.

---

## 137. Integración con Metadata Engine

Schemas iniciales:

```php
new MetadataSchema(
    key: 'controller.invocation.mode',
    type: MetadataValueType::Enum,
    mergeStrategy: MetadataMergeStrategy::LastWins,
    defaultValue: ControllerInvocationMode::Auto,
    valueClass: ControllerInvocationMode::class,
);
```

```php
new MetadataSchema(
    key: 'controller.invocation.argument_mode',
    type: MetadataValueType::Enum,
    mergeStrategy: MetadataMergeStrategy::LastWins,
    defaultValue: ControllerArgumentMode::Positional,
    valueClass: ControllerArgumentMode::class,
);
```

```php
new MetadataSchema(
    key: 'controller.invocation.allow_static',
    type: MetadataValueType::Boolean,
    mergeStrategy: MetadataMergeStrategy::BooleanAnd,
    defaultValue: false,
);
```

---

## 138. Estructura de directorios

```text
src/
└── Quantum/
    └── Controllers/
        └── Invocation/
            ├── ControllerInvoker.php
            ├── ControllerInvocationTerminal.php
            │
            ├── Contracts/
            │   ├── ControllerInvokerInterface.php
            │   ├── ControllerInvocationTerminalInterface.php
            │   ├── ControllerInvocationStrategyInterface.php
            │   ├── ControllerInvocationStrategyRegistryInterface.php
            │   ├── ControllerInvocationPlanResolverInterface.php
            │   ├── DynamicInvocationPlanFactoryInterface.php
            │   ├── ControllerArgumentAssemblerInterface.php
            │   ├── ControllerCallableValidatorInterface.php
            │   ├── ControllerInvocationGuardInterface.php
            │   ├── ControllerInvocationLifecycleInterface.php
            │   ├── ControllerInvocationRecorderInterface.php
            │   ├── ControllerInvocationPlanValidatorInterface.php
            │   ├── ControllerInvocationCompilerInterface.php
            │   ├── ControllerInvocationPlanCacheInterface.php
            │   ├── CompiledControllerInvocationRegistryInterface.php
            │   ├── ControllerSignatureHasherInterface.php
            │   ├── ControllerCallableSecurityValidatorInterface.php
            │   ├── CompiledControllerCallableInterface.php
            │   └── CompiledStaticControllerCallableInterface.php
            │
            ├── Definitions/
            │   ├── ControllerInvocationRequest.php
            │   ├── ControllerInvocationPlan.php
            │   ├── CompiledControllerInvocationPlan.php
            │   ├── ControllerInvocationCompilationRequest.php
            │   ├── ControllerInvocationType.php
            │   ├── ControllerInvocationMode.php
            │   ├── ControllerInvocationStatus.php
            │   ├── ControllerArgumentMode.php
            │   ├── InvocationTypeValidationMode.php
            │   ├── ControllerArgumentList.php
            │   ├── ControllerInvocationState.php
            │   ├── ControllerInvocationAttempt.php
            │   ├── ControllerInvocationRecord.php
            │   ├── ControllerRetryPermit.php
            │   ├── VariadicParameterDefinition.php
            │   └── InvocationPlanCacheKey.php
            │
            ├── Strategies/
            │   ├── CompiledControllerInvocationStrategy.php
            │   ├── ClassMethodInvocationStrategy.php
            │   ├── InvokableClassInvocationStrategy.php
            │   ├── ClosureInvocationStrategy.php
            │   ├── ServiceMethodInvocationStrategy.php
            │   ├── ActionInvocationStrategy.php
            │   ├── ResourceInvocationStrategy.php
            │   ├── PageInvocationStrategy.php
            │   ├── ComponentInvocationStrategy.php
            │   ├── StaticMethodInvocationStrategy.php
            │   └── CallableObjectInvocationStrategy.php
            │
            ├── Registry/
            │   ├── ControllerInvocationStrategyRegistry.php
            │   └── CompiledControllerInvocationRegistry.php
            │
            ├── Planning/
            │   ├── ControllerInvocationPlanResolver.php
            │   ├── DynamicInvocationPlanFactory.php
            │   ├── CompiledInvocationPlanHydrator.php
            │   ├── ControllerInvocationPlanValidator.php
            │   ├── ControllerInvocationTypeResolver.php
            │   └── ControllerInvocationPlanSignature.php
            │
            ├── Arguments/
            │   ├── ControllerArgumentAssembler.php
            │   ├── PositionalArgumentAssembler.php
            │   ├── NamedArgumentAssembler.php
            │   ├── HybridArgumentAssembler.php
            │   ├── VariadicArgumentExpander.php
            │   └── ControllerArgumentValidator.php
            │
            ├── Validation/
            │   ├── ControllerCallableValidator.php
            │   ├── InvocationStrategyExistsValidator.php
            │   ├── ControllerInstanceValidator.php
            │   ├── ControllerMethodValidator.php
            │   ├── ControllerVisibilityValidator.php
            │   ├── StaticInvocationValidator.php
            │   ├── ParameterOrderValidator.php
            │   ├── VariadicParameterValidator.php
            │   ├── NamedArgumentValidator.php
            │   ├── ReferenceParameterValidator.php
            │   ├── ControllerSignatureValidator.php
            │   ├── ControllerArgumentTypeValidator.php
            │   └── CompiledPlanHashValidator.php
            │
            ├── Security/
            │   ├── ControllerCallableSecurityValidator.php
            │   ├── ControllerNamespaceValidator.php
            │   ├── ControllerMethodSecurityValidator.php
            │   └── ControllerCallablePolicy.php
            │
            ├── Lifecycle/
            │   ├── ControllerInvocationGuard.php
            │   ├── ControllerInvocationLifecycle.php
            │   ├── ControllerInvocationRecorder.php
            │   └── ControllerInvocationStateManager.php
            │
            ├── Compiler/
            │   ├── ControllerInvocationCompiler.php
            │   ├── ControllerInvocationPlanBuilder.php
            │   ├── ControllerInvocationPlanWriter.php
            │   ├── ControllerInvocationPlanLoader.php
            │   ├── ControllerSignatureHasher.php
            │   ├── ControllerInvocationSourceHasher.php
            │   ├── ControllerInvocationRegistryHasher.php
            │   ├── SpecializedInvokerGenerator.php
            │   └── SpecializedInvokerClassNameGenerator.php
            │
            ├── Cache/
            │   ├── LayeredControllerInvocationPlanCache.php
            │   ├── RequestControllerInvocationPlanCache.php
            │   ├── WorkerControllerInvocationPlanCache.php
            │   ├── CompiledControllerInvocationPlanCache.php
            │   └── ControllerInvocationPlanInvalidator.php
            │
            ├── Metadata/
            │   ├── ControllerInvocationMetadata.php
            │   └── ControllerInvocationMetadataSchemas.php
            │
            ├── Events/
            │   ├── ControllerInvocationPreparing.php
            │   ├── ControllerInvocationPlanResolving.php
            │   ├── ControllerInvocationPlanResolved.php
            │   ├── ControllerInvocationPlanCacheHit.php
            │   ├── ControllerInvocationPlanCacheMiss.php
            │   ├── ControllerInvocationStarting.php
            │   ├── ControllerInvocationSucceeded.php
            │   ├── ControllerInvocationFailed.php
            │   ├── ControllerInvocationCompleted.php
            │   ├── ControllerInvocationRetrying.php
            │   ├── ControllerInvocationPlanCompiled.php
            │   ├── ControllerInvocationPlanInvalidated.php
            │   └── ControllerInvocationRejected.php
            │
            ├── Exceptions/
            │   ├── ControllerInvocationException.php
            │   ├── ControllerInvocationPlanException.php
            │   ├── ControllerInvocationPlanValidationException.php
            │   ├── ControllerInvocationStrategyNotFoundException.php
            │   ├── ControllerInvocationStrategyConflictException.php
            │   ├── ControllerInvocationRejectedException.php
            │   ├── MissingControllerInstanceException.php
            │   ├── ControllerInstanceMismatchException.php
            │   ├── MissingControllerMethodException.php
            │   ├── InvalidControllerCallableException.php
            │   ├── NonPublicControllerMethodException.php
            │   ├── StaticControllerInvocationNotAllowedException.php
            │   ├── InvalidControllerStaticMethodException.php
            │   ├── MissingControllerInvocationArgumentException.php
            │   ├── UnexpectedControllerInvocationArgumentException.php
            │   ├── InvalidControllerInvocationArgumentException.php
            │   ├── ControllerArgumentTypeMismatchException.php
            │   ├── UnsupportedControllerReferenceParameterException.php
            │   ├── UnsupportedControllerReferenceReturnException.php
            │   ├── InvalidVariadicControllerArgumentException.php
            │   ├── InvalidNamedControllerArgumentException.php
            │   ├── DuplicateControllerInvocationException.php
            │   ├── ControllerInvocationRetryNotAllowedException.php
            │   ├── ControllerInvocationAttemptsExceededException.php
            │   ├── ControllerInvocationCancelledException.php
            │   ├── StaleControllerInvocationPlanException.php
            │   ├── NonCompilableControllerInvocationException.php
            │   ├── ControllerInvocationRegistryFrozenException.php
            │   ├── ControllerInvocationSecurityException.php
            │   ├── BlockedControllerNamespaceException.php
            │   └── BlockedControllerMethodException.php
            │
            └── Testing/
                ├── FakeControllerInvoker.php
                ├── SpyControllerInvoker.php
                ├── FakeInvocationStrategy.php
                ├── RecordingInvocationStrategy.php
                ├── InMemoryInvocationPlanCache.php
                ├── InvocationPlanTestBuilder.php
                ├── ControllerInvocationAssertions.php
                └── ControllerInvocationFixtureFactory.php
```

---

## 139. Implementación mínima V1

La V1 deberá incluir:

* `ControllerInvokerInterface`.
* `ControllerInvoker`.
* `ControllerInvocationRequest`.
* `ControllerInvocationPlan`.
* `ControllerInvocationType`.
* `ControllerInvocationMode`.
* `ControllerArgumentList`.
* `ControllerInvocationState`.
* `ControllerInvocationGuard`.
* `ControllerInvocationLifecycle`.
* `ControllerInvocationPlanResolver`.
* `DynamicInvocationPlanFactory`.
* `ControllerArgumentAssembler`.
* `ControllerCallableValidator`.
* Registry de estrategias.
* `ClassMethodInvocationStrategy`.
* `InvokableClassInvocationStrategy`.
* `ClosureInvocationStrategy`.
* `ServiceMethodInvocationStrategy`.
* `ActionInvocationStrategy`.
* Soporte posicional.
* Soporte variádico.
* Validación de argumentos faltantes.
* Rechazo de parámetros por referencia.
* Detección de doble invocación.
* Soporte explícito de retries.
* Request cache.
* Worker plan cache.
* Planes compilados básicos.
* Hash de firma.
* Métricas.
* Eventos básicos.
* Trace debug.
* Compatibilidad con FrankenPHP.
* Integración con interceptor terminal.
* Integración con result normalizer.
* Pruebas unitarias.
* Pruebas de integración.

Podrán posponerse:

* Invocadores PHP especializados.
* Fibers.
* Awaitables automáticos.
* Argumentos híbridos.
* Estrategias GraphQL.
* Component invocation avanzada.
* Page invocation avanzada.
* Cache distribuida.
* Preloading automático.
* Hot reload de planes.
* Validación avanzada de generics.
* Strategy IDs completamente extensibles.

---

## 140. Ejemplo completo

Controlador:

```php
final class UserController
{
    public function show(
        User $user,
        bool $includePosts = false
    ): UserResource {
        if ($includePosts) {
            $user->load('posts');
        }

        return new UserResource($user);
    }
}
```

Controlador resuelto:

```php
$resolvedController = new ResolvedController(
    definition: $definition,
    resolutionType:
        ControllerResolutionType::ClassMethod,
    instance: $controller,
    method: 'show',
    displayName:
        UserController::class . '::show',
    className:
        UserController::class,
    methodName:
        'show',
    resolutionPlan: $resolutionPlan,
);
```

Parámetros resueltos:

```php
$parameters = new ResolvedParameterBag([
    new ResolvedParameter(
        definition: $userDefinition,
        value: $user,
        source: ParameterSource::Model,
        resolver: ModelResolver::class,
    ),

    new ResolvedParameter(
        definition: $includePostsDefinition,
        value: true,
        source: ParameterSource::Query,
        resolver: QueryParameterResolver::class,
    ),
]);
```

Plan:

```php
$plan = new ControllerInvocationPlan(
    controllerIdentity:
        UserController::class . '::show',

    invocationType:
        ControllerInvocationType::ClassMethod,

    instance:
        $controller,

    className:
        UserController::class,

    methodName:
        'show',

    callable:
        null,

    parameterOrder: [
        'user',
        'includePosts',
    ],

    parameterNames: [
        0 => 'user',
        1 => 'includePosts',
    ],

    variadicParameters: [],

    usesNamedArguments: false,

    isStatic: false,

    isPublic: true,

    returnsByReference: false,

    compiled: true,

    signature: '...',
);
```

Argumentos:

```php
$arguments = new ControllerArgumentList(
    positional: [
        $user,
        true,
    ],

    named: [
        'user' => $user,
        'includePosts' => true,
    ],
);
```

Invocación:

```php
$result = $controller->show(
    $user,
    true
);
```

Resultado bruto:

```php
UserResource
```

Posteriormente:

```text
ResultNormalizationSystem
    ↓
JsonResponse
```

---

## 141. Pruebas unitarias

Casos mínimos:

* Invoca método de clase.
* Invoca controlador invocable.
* Invoca closure.
* Invoca servicio.
* Invoca Action.
* Respeta orden de parámetros.
* Aplica argumentos posicionales.
* Aplica argumentos nombrados.
* Expande variádicos.
* Permite variádico vacío.
* Detecta parámetros faltantes.
* Detecta parámetros extra.
* Detecta tipos incompatibles.
* Rechaza parámetros por referencia.
* Rechaza retorno por referencia.
* Rechaza método no público.
* Rechaza método estático cuando está deshabilitado.
* Rechaza callable inválido.
* Rechaza instancia incorrecta.
* Detecta doble invocación.
* Permite retry autorizado.
* Rechaza retry sin permiso.
* Registra intentos.
* Registra duración.
* Propaga excepción original.
* No normaliza resultado.
* No consume Generator.
* Usa plan compilado.
* Invalida plan obsoleto.
* Mantiene equivalencia dinámica y compilada.

---

## 142. Pruebas de integración

* ControllerResolver → ControllerInvoker.
* ParameterEngine → ArgumentAssembler.
* MetadataEngine → InvocationMetadata.
* InterceptorPipeline → InvocationTerminal.
* RetryInterceptor → Invocation attempts.
* ResultNormalizer recibe resultado bruto.
* ExceptionSystem recibe excepción original.
* FrankenPHP no comparte invocation state.
* Worker cache no almacena controller instance.
* Plan compilado se hidrata con instancia correcta.
* Route closure funciona.
* Action controller funciona.
* Invokable controller funciona.
* Variadic route parameters funcionan.
* Plan se invalida al cambiar firma.
* Security validator bloquea namespace.
* Static controller policy funciona.

---

## 143. Prueba de invocación simple

```php
public function test_it_invokes_a_class_method_controller(): void
{
    $controller = new TestController();

    $result = $this->invoker->invoke(
        new ControllerInvocationRequest(
            controller: $this->resolvedController(
                instance: $controller,
                method: 'show',
            ),
            parameters: $this->parameters([
                'id' => 10,
            ]),
            execution: $this->execution(),
        )
    );

    expect($result)->toBe('user-10');
}
```

---

## 144. Prueba de variádicos

```php
public function test_it_expands_variadic_arguments(): void
{
    $result = $this->invoke(
        controller: ReportController::class,
        method: 'generate',
        parameters: [
            'format' => 'pdf',
            'ids' => [10, 20, 30],
        ],
    );

    expect($result->ids)
        ->toBe([10, 20, 30]);
}
```

---

## 145. Prueba de doble invocación

```php
public function test_it_rejects_accidental_double_invocation(): void
{
    $request = $this->invocationRequest();

    $this->invoker->invoke($request);

    $this->expectException(
        DuplicateControllerInvocationException::class
    );

    $this->invoker->invoke($request);
}
```

---

## 146. Prueba de excepción original

```php
public function test_it_preserves_controller_exception_type(): void
{
    $this->expectException(
        UserAlreadyExistsException::class
    );

    $this->invoke(
        controller: UserController::class,
        method: 'store',
        parameters: $this->validParameters(),
    );
}
```

---

## 147. Prueba de aislamiento FrankenPHP

```php
public function test_invocation_state_is_not_shared_between_requests(): void
{
    $first = $this->executeRequest(
        controller: StatefulTestController::class
    );

    $second = $this->executeRequest(
        controller: StatefulTestController::class
    );

    expect($first->invocationState)
        ->not->toBe($second->invocationState);

    expect($first->invocationState->attempts)
        ->toBe(1);

    expect($second->invocationState->attempts)
        ->toBe(1);
}
```

---

## 148. Benchmarks

Escenarios:

```text
Class method without parameters
Class method with five scalar parameters
Class method with five objects
Invokable controller
Closure
Service method
Action
Variadic invocation
Named arguments
Dynamic plan
Request cache hit
Worker cache hit
Compiled plan hit
Compiled specialized invoker
Type validation disabled
Basic validation
Strict validation
Successful invocation
Exception invocation
Retry with two attempts
Generator return
```

Métricas:

* Tiempo del plan resolver.
* Tiempo del assembler.
* Tiempo del validator.
* Tiempo de la strategy.
* Overhead total del invoker.
* Memoria temporal.
* Objetos creados.
* Invocaciones por segundo.
* Dynamic vs compiled.
* Dynamic method call vs specialized invoker.
* Positional vs named arguments.
* Reflection calls.
* Cache hit ratio.

---

## 149. Objetivos de rendimiento

Metas conceptuales:

```text
Compiled plan hit
    sin Reflection
    sin inspección de parámetros
    sin selección dinámica compleja

Positional arguments
    construcción lineal

Class method invocation
    overhead mínimo sobre llamada directa

Empty validation mode
    near-direct invocation

Worker cache
    únicamente planes inmutables
```

Los objetivos numéricos deberán establecerse después de benchmarks reales.

---

## 150. Decisiones arquitectónicas

### ADR-CTRL-INV-001

**Decisión:** El invoker tendrá una única responsabilidad: ejecutar el controlador.

**Razón:** Mantiene separación entre resolución, parámetros, interceptores y normalización.

---

### ADR-CTRL-INV-002

**Decisión:** Toda invocación partirá de un `ResolvedController`.

**Razón:** Evita duplicar descubrimiento y validación del target.

---

### ADR-CTRL-INV-003

**Decisión:** El invoker recibirá un `ResolvedParameterBag`.

**Razón:** No deberá leer directamente Request, Route o Container para resolver argumentos.

---

### ADR-CTRL-INV-004

**Decisión:** La invocación utilizará estrategias especializadas.

**Razón:** Permite soportar distintos tipos de controlador sin condicionales monolíticos.

---

### ADR-CTRL-INV-005

**Decisión:** Los argumentos posicionales serán el modo predeterminado.

**Razón:** Reducen acoplamiento a nombres de parámetros y simplifican compilación.

---

### ADR-CTRL-INV-006

**Decisión:** El orden de argumentos procederá del plan de parámetros.

**Razón:** Evita depender del orden accidental de arrays.

---

### ADR-CTRL-INV-007

**Decisión:** El invoker no realizará coerción.

**Razón:** La coerción pertenece al Parameter Resolution Engine.

---

### ADR-CTRL-INV-008

**Decisión:** Los parámetros por referencia no estarán soportados en V1.

**Razón:** Introducen efectos laterales y complejidad incompatible con retries y compilación.

---

### ADR-CTRL-INV-009

**Decisión:** El resultado se devolverá sin normalización.

**Razón:** El Result Normalization System será responsable de crear la respuesta.

---

### ADR-CTRL-INV-010

**Decisión:** Las excepciones del controlador conservarán su tipo original.

**Razón:** Evita ocultar errores de dominio o programación.

---

### ADR-CTRL-INV-011

**Decisión:** La doble invocación será rechazada por defecto.

**Razón:** Evita efectos laterales duplicados y errores del pipeline.

---

### ADR-CTRL-INV-012

**Decisión:** Los retries requerirán un permiso explícito.

**Razón:** Diferencia reintentos intencionales de invocaciones accidentales.

---

### ADR-CTRL-INV-013

**Decisión:** Los métodos deberán ser públicos.

**Razón:** Mantiene encapsulación y reduce superficie de ataque.

---

### ADR-CTRL-INV-014

**Decisión:** No se utilizará magic method dispatch salvo `__invoke`.

**Razón:** El target debe ser explícito y compilable.

---

### ADR-CTRL-INV-015

**Decisión:** Los planes compilados no almacenarán instancias.

**Razón:** Las instancias pueden ser request-scoped y no deben compartirse entre workers.

---

### ADR-CTRL-INV-016

**Decisión:** El invoker utilizará planes compilados cuando estén disponibles.

**Razón:** Elimina Reflection y trabajo repetitivo en producción.

---

### ADR-CTRL-INV-017

**Decisión:** La generación de invocadores especializados será opcional.

**Razón:** Su beneficio deberá demostrarse mediante benchmarks.

---

### ADR-CTRL-INV-018

**Decisión:** Generators, streams y awaitables no serán consumidos automáticamente.

**Razón:** El invoker debe preservar la semántica del resultado bruto.

---

### ADR-CTRL-INV-019

**Decisión:** El registry de estrategias se congelará en producción.

**Razón:** Mejora determinismo y seguridad en procesos persistentes.

---

### ADR-CTRL-INV-020

**Decisión:** El invoker no conservará estado entre requests.

**Razón:** Garantiza compatibilidad con FrankenPHP y evita memory leaks.

---

## 151. Criterios de aceptación

El sistema se considerará correctamente implementado cuando:

* Pueda invocar métodos de clase.
* Pueda invocar controladores invocables.
* Pueda invocar closures.
* Pueda invocar servicios.
* Pueda invocar Actions.
* Utilice estrategias registradas.
* Construya planes de invocación.
* Reutilice información del ControllerResolver.
* Reutilice información del Parameter Resolution Engine.
* Respete el orden de parámetros.
* Soporte argumentos posicionales.
* Soporte argumentos nombrados configurables.
* Soporte parámetros variádicos.
* Detecte argumentos faltantes.
* Detecte argumentos extra.
* Valide tipos de forma defensiva.
* No realice coerción.
* Rechace parámetros por referencia.
* Rechace métodos no públicos.
* Aplique políticas sobre métodos estáticos.
* Detecte callable inválido.
* Detecte instancia incompatible.
* Detecte doble invocación.
* Permita retries explícitos.
* Registre intentos.
* Preserve excepciones originales.
* Devuelva resultados sin normalizar.
* Preserve Generators y Streams.
* Genere métricas y trazas.
* Utilice cache por ejecución.
* Utilice cache por request.
* Utilice planes seguros por worker.
* Soporte planes compilados.
* Detecte planes obsoletos.
* Sea compatible con OPcache.
* Sea seguro con FrankenPHP.
* No comparta instancias request-scoped.
* Se integre con ControllerInvocationTerminal.
* Se integre con el sistema de interceptores.
* Se integre con el sistema de excepciones.
* Entregue el resultado al normalizador.
* Mantenga equivalencia dinámica y compilada.

---

## 152. Conclusión

El `Controller Invoker` será el componente final encargado de realizar la ejecución física de un controlador en VoltStack.

Su diseño deliberadamente limitado garantiza que la invocación permanezca separada de:

* Routing.
* Resolución de controladores.
* Resolución de parámetros.
* Metadata.
* Middleware.
* Interceptores.
* Autorización.
* Validación.
* Normalización de resultados.
* Manejo global de excepciones.

El flujo final será:

```text
ResolvedController
        +
ResolvedParameterBag
        +
ControllerExecution
        ↓
ControllerInvocationPlan
        ↓
ControllerArgumentAssembler
        ↓
ControllerCallableValidator
        ↓
ControllerInvocationStrategy
        ↓
Raw Controller Result
```

La utilización de estrategias, planes inmutables, argumentos ordenados, hashes de firma, protección contra doble invocación y compilación permitirá que VoltStack ejecute distintos estilos de controladores mediante una infraestructura uniforme.

En producción, los planes compilados podrán eliminar Reflection y descubrimiento repetitivo, mientras que la integración con OPcache y FrankenPHP permitirá mantener un camino de ejecución estable y seguro para workers persistentes.
