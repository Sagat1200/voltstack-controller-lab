# 04_CONTROLLER_RESOLVER.md

# Sistema de resolución de controladores de VoltStack

**Versión:** 1.0
**Estado:** Draft
**Módulo:** `VoltStack\Quantum\Controllers`
**Documento anterior:** `03_CONTROLLER_DISPATCHER.md`

---

# 1. Propósito

Este documento define la arquitectura, contratos, reglas y flujo interno del `ControllerResolver` de VoltStack.

El resolver será responsable de transformar una `ControllerDefinition` en un `ResolvedController` ejecutable.

Su función principal será responder preguntas como:

* ¿Qué clase representa el controlador?
* ¿Qué método debe ejecutarse?
* ¿Debe resolverse mediante el contenedor?
* ¿La referencia representa una closure?
* ¿Es un controlador invocable?
* ¿Es un servicio registrado?
* ¿Es una Action?
* ¿El método existe?
* ¿El método puede ejecutarse?
* ¿La instancia pertenece al scope correcto?
* ¿Existe un plan compilado para este controlador?

El resolver no ejecutará el controlador.

Su responsabilidad termina cuando entrega una representación válida y preparada para que las etapas posteriores resuelvan metadata, argumentos, middleware e invoquen la acción.

---

# 2. Posición dentro del flujo

```text
Route Match
    ↓
ControllerDefinition
    ↓
ResolveControllerStage
    ↓
ControllerResolver
    ↓
ResolvedController
    ↓
Metadata Resolution
    ↓
Argument Resolution
    ↓
Invocation
```

El resolver será utilizado por `ResolveControllerStage`.

```php
$execution->controller = $resolver->resolve(
    $execution->definition,
    $execution->context
);
```

---

# 3. Objetivos

El sistema deberá:

* Resolver controladores de clase y método.
* Resolver controladores invocables.
* Resolver closures.
* Resolver servicios registrados.
* Resolver aliases.
* Resolver Actions.
* Resolver controladores especializados.
* Integrarse con el Container.
* Aplicar scopes correctamente.
* Validar clases y métodos.
* Bloquear referencias inseguras.
* Producir errores descriptivos.
* Evitar Reflection repetida.
* Utilizar metadata compilada cuando exista.
* Permitir resolvers personalizados.
* Funcionar correctamente con FrankenPHP.
* Mantener comportamiento determinista.

---

# 4. Responsabilidades

El resolver será responsable de:

* Validar una `ControllerDefinition`.
* Determinar la estrategia de resolución.
* Resolver aliases.
* Resolver la clase mediante el contenedor.
* Resolver controladores registrados como servicios.
* Detectar métodos invocables.
* Seleccionar el método efectivo.
* Validar que el método exista.
* Validar que el método sea público.
* Validar que el método esté permitido.
* Crear una referencia invocable normalizada.
* Cargar información de Reflection o metadata compilada.
* Establecer el tipo de resolución.
* Preparar información para el `ControllerInvoker`.

No será responsable de:

* Resolver los argumentos del método.
* Ejecutar atributos.
* Ejecutar middleware.
* Ejecutar autorización.
* Ejecutar validación.
* Invocar el método.
* Normalizar el resultado.
* Construir la respuesta HTTP.

---

# 5. Contrato principal

```php
namespace VoltStack\Quantum\Controllers\Contracts;

use VoltStack\Quantum\Controllers\Context\ControllerContext;
use VoltStack\Quantum\Controllers\Definitions\ControllerDefinition;
use VoltStack\Quantum\Controllers\Definitions\ResolvedController;

interface ControllerResolverInterface
{
    public function resolve(
        ControllerDefinition $definition,
        ControllerContext $context
    ): ResolvedController;
}
```

El resultado siempre deberá ser un `ResolvedController`.

Si la definición no puede resolverse, el resolver deberá lanzar una excepción específica.

---

# 6. ResolvedController

`ResolvedController` representa un controlador listo para continuar dentro del pipeline.

```php
namespace VoltStack\Quantum\Controllers\Definitions;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;

final readonly class ResolvedController
{
    public function __construct(
        public ControllerDefinition $definition,
        public ControllerResolutionType $resolutionType,
        public object|Closure $instance,
        public string|Closure $method,
        public string $displayName,
        public ?string $className = null,
        public ?string $methodName = null,
        public ReflectionClass|ReflectionFunction|null $reflection = null,
        public ?ReflectionMethod $methodReflection = null,
        public ?ControllerResolutionPlan $plan = null,
        public array $attributes = [],
    ) {
    }
}
```

---

# 7. ControllerResolutionType

```php
enum ControllerResolutionType: string
{
    case ClassMethod = 'class_method';
    case InvokableClass = 'invokable_class';
    case Closure = 'closure';
    case Service = 'service';
    case Alias = 'alias';
    case Action = 'action';
    case Resource = 'resource';
    case Page = 'page';
    case Component = 'component';
    case Compiled = 'compiled';
}
```

Este tipo permitirá que las etapas posteriores conozcan el origen de la resolución sin depender de condiciones sobre la clase.

---

# 8. ControllerDefinition

La definición deberá contener una representación normalizada del objetivo.

Ejemplo:

```php
final readonly class ControllerDefinition
{
    public function __construct(
        public ControllerType $type,
        public mixed $target,
        public ?string $class = null,
        public ?string $method = null,
        public ?string $service = null,
        public ?string $alias = null,
        public array $metadata = [],
    ) {
    }
}
```

El resolver no deberá reinterpretar referencias públicas complejas.

Esa responsabilidad pertenece al `ControllerReferenceParser`.

El resolver recibirá una definición ya normalizada.

---

# 9. Estrategias de resolución

El sistema utilizará resolvers especializados.

```php
interface ControllerTypeResolverInterface
{
    public function supports(
        ControllerDefinition $definition,
        ControllerContext $context
    ): bool;

    public function resolve(
        ControllerDefinition $definition,
        ControllerContext $context
    ): ResolvedController;

    public function priority(): int;
}
```

Implementaciones previstas:

```text
CompiledControllerResolver
ClosureControllerResolver
AliasControllerResolver
ServiceControllerResolver
ClassMethodControllerResolver
InvokableControllerResolver
ActionControllerResolver
ResourceControllerResolver
PageControllerResolver
ComponentControllerResolver
```

---

# 10. CompositeControllerResolver

El resolver principal será un composite.

```php
final class CompositeControllerResolver implements
    ControllerResolverInterface
{
    /**
     * @param iterable<ControllerTypeResolverInterface> $resolvers
     */
    public function __construct(
        private readonly iterable $resolvers
    ) {
    }

    public function resolve(
        ControllerDefinition $definition,
        ControllerContext $context
    ): ResolvedController {
        foreach ($this->resolvers as $resolver) {
            if (! $resolver->supports($definition, $context)) {
                continue;
            }

            return $resolver->resolve($definition, $context);
        }

        throw UnsupportedControllerDefinitionException::forDefinition(
            $definition
        );
    }
}
```

---

# 11. Prioridad de resolvers

Orden recomendado:

```text
1000  CompiledControllerResolver
900   ClosureControllerResolver
850   AliasControllerResolver
800   ServiceControllerResolver
700   ActionControllerResolver
650   PageControllerResolver
600   ComponentControllerResolver
500   ResourceControllerResolver
400   ClassMethodControllerResolver
300   InvokableControllerResolver
```

El resolver compilado tendrá mayor prioridad porque puede evitar análisis dinámico.

---

# 12. Regla de resolución única

Una definición deberá ser soportada por un único resolver efectivo.

En modo debug, el composite podrá comprobar ambigüedad:

```php
$supported = [];

foreach ($resolvers as $resolver) {
    if ($resolver->supports($definition, $context)) {
        $supported[] = $resolver;
    }
}

if (count($supported) > 1) {
    throw AmbiguousControllerResolverException::forDefinition(
        $definition,
        $supported
    );
}
```

En producción, la resolución podrá utilizar una tabla compilada.

---

# 13. Resolución de clase y método

Ejemplo de ruta:

```php
Route::get('/users', [
    UserController::class,
    'index',
]);
```

Definición:

```php
new ControllerDefinition(
    type: ControllerType::ClassMethod,
    target: [UserController::class, 'index'],
    class: UserController::class,
    method: 'index',
);
```

Flujo:

```text
ControllerDefinition
    ↓
Validate class name
    ↓
Resolve instance from Container
    ↓
Validate method
    ↓
Build Reflection metadata
    ↓
Create ResolvedController
```

---

# 14. ClassMethodControllerResolver

```php
final class ClassMethodControllerResolver implements
    ControllerTypeResolverInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ControllerInspectorInterface $inspector,
        private readonly ControllerSecurityValidatorInterface $security,
    ) {
    }

    public function supports(
        ControllerDefinition $definition,
        ControllerContext $context
    ): bool {
        return $definition->type === ControllerType::ClassMethod;
    }

    public function resolve(
        ControllerDefinition $definition,
        ControllerContext $context
    ): ResolvedController {
        $class = $definition->class
            ?? throw MissingControllerClassException::forDefinition(
                $definition
            );

        $method = $definition->method
            ?? throw MissingControllerMethodException::forDefinition(
                $definition
            );

        $this->security->validateClassName($class);

        $instance = $this->container->make($class);

        $inspection = $this->inspector->inspectMethod(
            $instance,
            $method
        );

        return new ResolvedController(
            definition: $definition,
            resolutionType: ControllerResolutionType::ClassMethod,
            instance: $instance,
            method: $method,
            displayName: $class . '@' . $method,
            className: $class,
            methodName: $method,
            reflection: $inspection->classReflection,
            methodReflection: $inspection->methodReflection,
            attributes: $inspection->attributes,
        );
    }

    public function priority(): int
    {
        return 400;
    }
}
```

---

# 15. Resolución mediante Container

Los controladores de clase deberán resolverse mediante el contenedor.

```php
$instance = $container->make(UserController::class);
```

Esto permitirá:

* Constructor injection.
* Bindings por interfaz.
* Decoradores.
* Contextual bindings.
* Proxies.
* Lazy services.
* Scoped services.
* Factories personalizadas.

El resolver no deberá usar directamente:

```php
new UserController();
```

salvo que el Container lo determine internamente.

---

# 16. Scope de controladores

Los controladores serán transient o request-scoped por defecto.

```text
Singleton
    ❌ No recomendado

Request Scoped
    ✅ Recomendado

Transient
    ✅ Permitido
```

Bajo FrankenPHP, una instancia singleton podría conservar datos entre peticiones.

Por ello, el sistema deberá impedir o advertir sobre controladores singleton con estado mutable.

---

# 17. ControllerScope

```php
enum ControllerScope: string
{
    case Transient = 'transient';
    case Request = 'request';
    case Singleton = 'singleton';
}
```

La metadata de resolución podrá incluir:

```php
#[ControllerScope(ControllerScope::Request)]
final class UserController
{
}
```

Configuración predeterminada:

```php
'default_scope' => ControllerScope::Request,
```

---

# 18. Validación de scope

El resolver o un validador especializado deberá comprobar:

* Si el controlador está registrado como singleton.
* Si implementa interfaces request-aware.
* Si contiene propiedades mutables peligrosas.
* Si almacena Request, User, Tenant o Session.
* Si es compatible con el runtime persistente.

En producción, una infracción grave podrá impedir el arranque del framework.

---

# 19. Controladores invocables

Ejemplo:

```php
Route::get('/dashboard', DashboardController::class);
```

Controlador:

```php
final class DashboardController
{
    public function __invoke(): ResponseInterface
    {
    }
}
```

Definición:

```php
new ControllerDefinition(
    type: ControllerType::Invokable,
    target: DashboardController::class,
    class: DashboardController::class,
    method: '__invoke',
);
```

---

# 20. InvokableControllerResolver

```php
final class InvokableControllerResolver implements
    ControllerTypeResolverInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ControllerInspectorInterface $inspector,
    ) {
    }

    public function supports(
        ControllerDefinition $definition,
        ControllerContext $context
    ): bool {
        return $definition->type === ControllerType::Invokable;
    }

    public function resolve(
        ControllerDefinition $definition,
        ControllerContext $context
    ): ResolvedController {
        $class = $definition->class
            ?? throw MissingControllerClassException::forDefinition(
                $definition
            );

        $instance = $this->container->make($class);

        $inspection = $this->inspector->inspectMethod(
            $instance,
            '__invoke'
        );

        return new ResolvedController(
            definition: $definition,
            resolutionType: ControllerResolutionType::InvokableClass,
            instance: $instance,
            method: '__invoke',
            displayName: $class . '::__invoke',
            className: $class,
            methodName: '__invoke',
            reflection: $inspection->classReflection,
            methodReflection: $inspection->methodReflection,
            attributes: $inspection->attributes,
        );
    }

    public function priority(): int
    {
        return 300;
    }
}
```

---

# 21. Reglas de controladores invocables

El resolver deberá validar:

* La clase existe.
* La instancia es invocable.
* Existe `__invoke`.
* `__invoke` es público.
* `__invoke` no es estático, salvo soporte explícito.
* La clase no es abstracta.
* La clase puede instanciarse mediante el Container.

No deberá considerar `__call()` como sustituto de `__invoke()`.

---

# 22. Resolución de closures

Ejemplo:

```php
Route::get('/health', function (): array {
    return ['status' => 'ok'];
});
```

Definición:

```php
new ControllerDefinition(
    type: ControllerType::Closure,
    target: $closure,
);
```

---

# 23. ClosureControllerResolver

```php
final class ClosureControllerResolver implements
    ControllerTypeResolverInterface
{
    public function supports(
        ControllerDefinition $definition,
        ControllerContext $context
    ): bool {
        return $definition->type === ControllerType::Closure
            && $definition->target instanceof Closure;
    }

    public function resolve(
        ControllerDefinition $definition,
        ControllerContext $context
    ): ResolvedController {
        $closure = $definition->target;

        $reflection = new ReflectionFunction($closure);

        return new ResolvedController(
            definition: $definition,
            resolutionType: ControllerResolutionType::Closure,
            instance: $closure,
            method: $closure,
            displayName: $this->displayName($reflection),
            reflection: $reflection,
            attributes: $reflection->getAttributes(),
        );
    }

    public function priority(): int
    {
        return 900;
    }
}
```

---

# 24. Nombre de una closure

El display name podrá construirse como:

```text
Closure at routes/web.php:42
```

Esto facilitará debugging.

```php
private function displayName(
    ReflectionFunction $reflection
): string {
    return sprintf(
        'Closure at %s:%d',
        $reflection->getFileName() ?: 'unknown',
        $reflection->getStartLine()
    );
}
```

---

# 25. Restricciones de closures

Las closures:

* Funcionarán en desarrollo.
* Podrán resolver argumentos.
* Podrán usar middleware.
* Podrán producir cualquier resultado normalizable.
* No deberán serializarse automáticamente.
* No serán compatibles con route cache estándar.
* No deberán capturar contextos request-scoped persistentes.
* No deberán incluirse en caché de producción sin un mecanismo seguro.

VoltStack recomendará controladores invocables para aplicaciones compiladas.

---

# 26. Resolución de servicios

Una ruta podrá referirse a un servicio registrado.

```php
Route::post('/reports', 'controllers.report.generate');
```

Definición:

```php
new ControllerDefinition(
    type: ControllerType::Service,
    target: 'controllers.report.generate',
    service: 'controllers.report.generate',
    method: 'generate',
);
```

---

# 27. ServiceControllerResolver

```php
final class ServiceControllerResolver implements
    ControllerTypeResolverInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ControllerInspectorInterface $inspector,
        private readonly ControllerServiceRegistryInterface $registry,
    ) {
    }

    public function supports(
        ControllerDefinition $definition,
        ControllerContext $context
    ): bool {
        return $definition->type === ControllerType::Service;
    }

    public function resolve(
        ControllerDefinition $definition,
        ControllerContext $context
    ): ResolvedController {
        $serviceId = $definition->service
            ?? throw MissingControllerServiceException::create();

        if (! $this->registry->has($serviceId)) {
            throw ControllerServiceNotFoundException::forId(
                $serviceId
            );
        }

        $serviceDefinition = $this->registry->get($serviceId);
        $instance = $this->container->get($serviceDefinition->containerId);

        $method = $definition->method
            ?? $serviceDefinition->method
            ?? '__invoke';

        $inspection = $this->inspector->inspectMethod(
            $instance,
            $method
        );

        return new ResolvedController(
            definition: $definition,
            resolutionType: ControllerResolutionType::Service,
            instance: $instance,
            method: $method,
            displayName: $serviceId . '@' . $method,
            className: $instance::class,
            methodName: $method,
            reflection: $inspection->classReflection,
            methodReflection: $inspection->methodReflection,
            attributes: $inspection->attributes,
        );
    }

    public function priority(): int
    {
        return 800;
    }
}
```

---

# 28. ControllerServiceRegistry

```php
interface ControllerServiceRegistryInterface
{
    public function register(
        string $id,
        ControllerServiceDefinition $definition
    ): void;

    public function has(string $id): bool;

    public function get(string $id): ControllerServiceDefinition;

    public function all(): iterable;
}
```

Definición:

```php
final readonly class ControllerServiceDefinition
{
    public function __construct(
        public string $containerId,
        public ?string $method = null,
        public array $metadata = [],
    ) {
    }
}
```

---

# 29. Seguridad de servicios

No se deberá permitir que cualquier ID del Container sea ejecutable como controlador.

Solo podrán ejecutarse servicios registrados explícitamente en `ControllerServiceRegistry`.

Esto evita rutas como:

```text
/{service}/{method}
```

capaces de invocar servicios internos arbitrariamente.

---

# 30. Resolución de aliases

VoltStack podrá registrar aliases de controladores.

```php
$controllers->alias(
    'users.show',
    [UserController::class, 'show']
);
```

Ruta:

```php
Route::get('/users/{user}', 'users.show');
```

---

# 31. AliasControllerResolver

```php
final class AliasControllerResolver implements
    ControllerTypeResolverInterface
{
    public function __construct(
        private readonly ControllerAliasRegistryInterface $aliases,
        private readonly ControllerResolverInterface $resolver,
    ) {
    }

    public function supports(
        ControllerDefinition $definition,
        ControllerContext $context
    ): bool {
        return $definition->type === ControllerType::Alias;
    }

    public function resolve(
        ControllerDefinition $definition,
        ControllerContext $context
    ): ResolvedController {
        $alias = $definition->alias
            ?? throw MissingControllerAliasException::create();

        $target = $this->aliases->get($alias);

        $resolved = $this->resolver->resolve(
            $target,
            $context
        );

        return $resolved->withResolutionType(
            ControllerResolutionType::Alias
        );
    }

    public function priority(): int
    {
        return 850;
    }
}
```

---

# 32. Prevención de alias circulares

Ejemplo inválido:

```text
users.show → profile.show
profile.show → users.show
```

El registry deberá detectar ciclos durante compilación o boot.

Excepción:

```text
CircularControllerAliasException
```

El error deberá mostrar la cadena completa.

---

# 33. Actions

Una Action podrá utilizarse directamente como controlador.

```php
Route::post('/users', CreateUserAction::class);
```

Action:

```php
#[Action]
final class CreateUserAction
{
    public function __invoke(
        CreateUserData $data
    ): User {
    }
}
```

---

# 34. ActionControllerResolver

El resolver de Actions podrá:

* Validar metadata `#[Action]`.
* Resolver la instancia desde el Container.
* Seleccionar `__invoke`, `handle` o método configurado.
* Adjuntar metadata transaccional.
* Adjuntar idempotencia.
* Adjuntar auditoría.
* Identificar la Action para el pipeline.

```php
final class ActionControllerResolver implements
    ControllerTypeResolverInterface
{
    public function supports(
        ControllerDefinition $definition,
        ControllerContext $context
    ): bool {
        return $definition->type === ControllerType::Action;
    }
}
```

La invocación final seguirá utilizando `ControllerInvoker`.

---

# 35. Convención de método para Actions

Orden sugerido:

```text
Método explícito en definición
    ↓
__invoke
    ↓
handle
```

Se recomienda `__invoke()` como convención principal.

```php
final class CreateUserAction
{
    public function __invoke(CreateUserData $data): User
    {
    }
}
```

---

# 36. Resource Controllers

Un resource controller representa varios métodos convencionales.

```php
Route::resource('users', UserController::class);
```

El router determinará el método:

```text
GET /users
    → index

GET /users/create
    → create

POST /users
    → store

GET /users/{user}
    → show

GET /users/{user}/edit
    → edit

PUT /users/{user}
    → update

DELETE /users/{user}
    → destroy
```

El resolver recibirá una definición ya asociada al método concreto.

No será responsable de mapear verbos HTTP a métodos de recurso.

---

# 37. ResourceControllerResolver

El resolver podrá aplicar validaciones adicionales:

* Método permitido dentro del recurso.
* Acciones `only`.
* Acciones `except`.
* Metadata REST.
* Nombres de parámetros.
* Configuración de API Resource.

Al final devolverá un `ResolvedController` compatible con clase y método.

---

# 38. Page Controllers

Los controladores de página estarán orientados al Runtime Volt.

```php
#[Page('users.show')]
final class ShowUserPage
{
    public function __invoke(User $user): array
    {
        return [
            'user' => $user,
        ];
    }
}
```

El resolver podrá añadir atributos como:

```php
[
    'controller_kind' => 'page',
    'page_name' => 'users.show',
    'preferred_normalizer' => VoltResultNormalizer::class,
]
```

No renderizará la página.

---

# 39. Component Controllers

Ejemplo:

```php
#[Component(UserTable::class)]
final class UserTableController
{
    public function __invoke(): array
    {
        return [
            'sortable' => true,
        ];
    }
}
```

El resolver podrá incluir:

* Componente objetivo.
* Runtime frontend.
* Estrategia de hidratación.
* Metadata de props.
* Normalizador preferido.

---

# 40. Resolución compilada

En producción, el framework podrá disponer de un plan compilado.

```php
final readonly class ControllerResolutionPlan
{
    public function __construct(
        public string $definitionHash,
        public ControllerResolutionType $type,
        public ?string $class,
        public string $method,
        public string $containerId,
        public string $displayName,
        public ControllerScope $scope,
        public array $attributes,
        public string $signatureHash,
    ) {
    }
}
```

---

# 41. CompiledControllerResolver

```php
final class CompiledControllerResolver implements
    ControllerTypeResolverInterface
{
    public function __construct(
        private readonly CompiledControllerRegistryInterface $registry,
        private readonly ContainerInterface $container,
    ) {
    }

    public function supports(
        ControllerDefinition $definition,
        ControllerContext $context
    ): bool {
        return $this->registry->has(
            $definition->signature()
        );
    }

    public function resolve(
        ControllerDefinition $definition,
        ControllerContext $context
    ): ResolvedController {
        $plan = $this->registry->get(
            $definition->signature()
        );

        $instance = $this->container->get(
            $plan->containerId
        );

        return new ResolvedController(
            definition: $definition,
            resolutionType: ControllerResolutionType::Compiled,
            instance: $instance,
            method: $plan->method,
            displayName: $plan->displayName,
            className: $plan->class,
            methodName: $plan->method,
            plan: $plan,
            attributes: $plan->attributes,
        );
    }

    public function priority(): int
    {
        return 1000;
    }
}
```

---

# 42. Ventajas de resolución compilada

* Evita descubrir el resolver.
* Evita validar repetidamente la clase.
* Evita Reflection de clase.
* Evita Reflection de método.
* Evita recomputar atributos.
* Evita determinar el scope.
* Evita construir nombres.
* Reduce objetos temporales.
* Mejora rendimiento bajo FrankenPHP.

---

# 43. Fallback dinámico

Cuando no exista un plan compilado:

```text
Compiled registry miss
    ↓
Dynamic resolver
    ↓
Optional debug warning
```

En producción estricta podrá configurarse:

```php
'compiled_only' => true,
```

En ese modo, una definición no compilada producirá error.

---

# 44. ControllerInspector

El inspector centralizará Reflection y validaciones estructurales.

```php
interface ControllerInspectorInterface
{
    public function inspectMethod(
        object|string $controller,
        string $method
    ): ControllerMethodInspection;

    public function inspectInvokable(
        object|string $controller
    ): ControllerMethodInspection;

    public function inspectClass(
        string $class
    ): ControllerClassInspection;
}
```

---

# 45. ControllerMethodInspection

```php
final readonly class ControllerMethodInspection
{
    public function __construct(
        public ReflectionClass $classReflection,
        public ReflectionMethod $methodReflection,
        public array $classAttributes,
        public array $methodAttributes,
        public array $attributes,
        public string $signatureHash,
    ) {
    }
}
```

El inspector podrá usar caché interna.

---

# 46. Reglas de validación de clase

La clase deberá:

* Existir.
* Poder cargarse mediante autoload.
* No ser abstracta.
* No ser una interface.
* No ser un trait.
* No ser un enum.
* Ser instanciable.
* Pertenecer a namespaces permitidos cuando aplique.
* No estar marcada como deshabilitada.
* No pertenecer a una lista de clases internas prohibidas.
* Poder resolverse mediante el Container.

---

# 47. Reglas de validación de método

El método deberá:

* Existir.
* Ser público.
* No ser constructor.
* No ser destructor.
* No ser abstracto.
* No ser un método mágico bloqueado.
* No ser heredado desde una clase prohibida.
* No estar marcado como `#[NotAction]`.
* Estar permitido por metadata o definición.
* Ser compatible con el modo de invocación.

---

# 48. Métodos mágicos bloqueados

```text
__construct
__destruct
__clone
__get
__set
__isset
__unset
__sleep
__wakeup
__serialize
__unserialize
__toString
__set_state
__call
__callStatic
__debugInfo
```

El único método mágico ejecutable por convención será:

```text
__invoke
```

---

# 49. Atributo NotAction

```php
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class NotAction
{
}
```

Ejemplo:

```php
final class UserController
{
    public function show(User $user): ViewResponse
    {
    }

    #[NotAction]
    public function buildViewData(User $user): array
    {
    }
}
```

Aunque el método sea público, el resolver deberá impedir que se ejecute como acción.

---

# 50. Métodos heredados

Por defecto, VoltStack podrá permitir métodos heredados desde una clase base controlada.

Ejemplo:

```php
abstract class CrudController
{
    public function index(): ResponseInterface
    {
    }
}
```

Sin embargo, deberá impedir ejecutar métodos públicos heredados accidentalmente desde:

* Traits utilitarios.
* Proxies.
* Clases externas.
* Objetos decorados.
* Clases de infraestructura.

La política será configurable.

---

# 51. ControllerSecurityValidator

```php
interface ControllerSecurityValidatorInterface
{
    public function validateDefinition(
        ControllerDefinition $definition
    ): void;

    public function validateClassName(
        string $class
    ): void;

    public function validateClass(
        ReflectionClass $class
    ): void;

    public function validateMethod(
        ReflectionMethod $method
    ): void;

    public function validateResolvedController(
        ResolvedController $controller
    ): void;
}
```

---

# 52. Namespaces permitidos

Configuración sugerida:

```php
'security' => [
    'allowed_namespaces' => [
        'App\\Http\\Controllers\\',
        'App\\Actions\\',
        'VoltStack\\',
    ],

    'denied_namespaces' => [
        'Internal\\',
        'Tests\\Fixtures\\',
    ],
],
```

Esta restricción podrá desactivarse en desarrollo, pero será recomendada en producción.

---

# 53. Resolución de métodos estáticos

Los métodos estáticos no serán la opción recomendada.

Ejemplo no recomendado:

```php
[UserController::class, 'index']
```

donde `index()` sea estático.

La V1 podrá:

* Rechazar métodos estáticos.
* O permitirlos mediante configuración explícita.

Recomendación:

```php
'allow_static_methods' => false,
```

La inyección de dependencias y el scope funcionan mejor con instancias.

---

# 54. Controladores abstractos

Una clase abstracta no podrá resolverse directamente.

```php
abstract class BaseController
{
}
```

Podrá ser utilizada como clase base, pero no como objetivo de ruta.

Excepción:

```text
AbstractControllerResolutionException
```

---

# 55. Decoradores de controlador

El Container podrá resolver un controlador decorado.

```text
UserController
    ↓
TracingUserControllerDecorator
    ↓
AuthorizationUserControllerDecorator
```

El resolver deberá distinguir:

* Clase solicitada.
* Clase real de la instancia.
* Método efectivo.
* Nombre de display.

```php
className: UserController::class
resolvedInstanceClass: TracingUserControllerDecorator::class
```

Esta información podrá incluirse en atributos.

---

# 56. Proxies

El sistema podrá trabajar con proxies lazy.

El inspector no deberá depender siempre de:

```php
$instance::class
```

porque podría devolver la clase proxy.

La definición original será la fuente principal del nombre lógico.

El Container podrá proporcionar:

```php
interface ProxyClassResolverInterface
{
    public function originalClass(object $instance): string;
}
```

---

# 57. ControllerFactory

Cuando una clase requiera construcción especializada, podrá registrarse una factory.

```php
interface ControllerFactoryInterface
{
    public function create(
        ControllerDefinition $definition,
        ControllerContext $context
    ): object;
}
```

El resolver podrá delegar al Container o a una factory asociada.

Casos:

* Controladores generados.
* Controladores remotos.
* Proxies específicos.
* Component controllers dinámicos.
* Controllers de plugins.

---

# 58. Binding contextual

Ejemplo:

```php
$container->when(AdminUserController::class)
    ->needs(UserRepository::class)
    ->give(AdminUserRepository::class);
```

El resolver deberá iniciar la resolución con el contexto de la clase lógica para que el Container aplique correctamente el binding.

---

# 59. Dependencias de constructor

El resolver no analizará ni resolverá manualmente los argumentos del constructor.

Esa responsabilidad pertenece al Container.

```text
ControllerResolver
    ↓
Container::make()
    ↓
Constructor dependency resolution
```

Esto evita duplicar la lógica del IoC Container.

---

# 60. Errores del Container

Las excepciones del Container deberán enriquecerse con contexto de controlador.

Ejemplo:

```text
Unable to resolve controller [UserController].

Constructor dependency [UserRepository] could not be resolved.

Route: users.index
Controller: App\Http\Controllers\UserController@index
```

El resolver deberá preservar la excepción original.

---

# 61. Cache de resolución

La caché podrá almacenar:

* Estrategia de resolución.
* Clase.
* Método.
* ReflectionClass.
* ReflectionMethod.
* Atributos.
* Scope.
* Display name.
* Signature hash.
* Container ID.

No deberá almacenar directamente:

* Instancias request-scoped.
* Request.
* Usuario.
* Tenant.
* Session.
* Closures no serializables.

---

# 62. ControllerResolutionCache

```php
interface ControllerResolutionCacheInterface
{
    public function has(string $key): bool;

    public function get(string $key): ControllerResolutionPlan;

    public function put(
        string $key,
        ControllerResolutionPlan $plan
    ): void;

    public function forget(string $key): void;

    public function clear(): void;
}
```

---

# 63. Clave de caché

La clave podrá construirse con:

```text
ControllerDefinition signature
Framework version
PHP version
Module version
Configuration hash
Source file hash
```

Ejemplo:

```php
$cacheKey = hash('xxh128', implode('|', [
    $definition->signature(),
    $frameworkVersion,
    $configHash,
]));
```

---

# 64. Invalidación

La caché deberá invalidarse cuando cambie:

* Archivo del controlador.
* Método.
* Atributos.
* Configuración.
* Registro de aliases.
* Registro de servicios.
* Scope.
* Versión del módulo.
* Container bindings relevantes.

En desarrollo podrá utilizarse validación por timestamp.

En producción se regenerará durante deploy.

---

# 65. Signature hash

El inspector podrá generar una firma basada en:

* Nombre de clase.
* Nombre de método.
* Parámetros.
* Tipos.
* Valores por defecto.
* Atributos.
* Tipo de retorno.
* Archivo y posición.

Esta firma permitirá detectar planes compilados obsoletos.

---

# 66. Thread safety y procesos persistentes

Aunque PHP tradicionalmente ejecuta una petición por proceso, VoltStack deberá prepararse para runtimes persistentes y concurrencia controlada.

El resolver deberá:

* Ser stateless.
* No guardar contexto actual en propiedades.
* No guardar instancias request-scoped.
* Usar cachés inmutables.
* No modificar registries durante una petición.
* No conservar Reflection ligada a objetos de petición.
* Ser seguro para reutilización.

---

# 67. ControllerResolverRegistry

```php
interface ControllerResolverRegistryInterface
{
    public function add(
        string $resolver,
        int $priority = 0
    ): void;

    public function remove(string $resolver): void;

    public function replace(
        string $resolver,
        string $replacement
    ): void;

    public function ordered(): array;
}
```

Los paquetes podrán añadir resolvers propios.

---

# 68. Ejemplo de resolver personalizado

```php
final class RemoteControllerResolver implements
    ControllerTypeResolverInterface
{
    public function supports(
        ControllerDefinition $definition,
        ControllerContext $context
    ): bool {
        return $definition->type === ControllerType::Remote;
    }

    public function resolve(
        ControllerDefinition $definition,
        ControllerContext $context
    ): ResolvedController {
        $proxy = new RemoteControllerProxy(
            endpoint: $definition->metadata['endpoint']
        );

        return new ResolvedController(
            definition: $definition,
            resolutionType: ControllerResolutionType::Service,
            instance: $proxy,
            method: 'invoke',
            displayName: 'RemoteController:' .
                $definition->metadata['endpoint'],
            className: $proxy::class,
            methodName: 'invoke',
        );
    }

    public function priority(): int
    {
        return 750;
    }
}
```

---

# 69. Resolución por atributos

Una clase podrá declarar metadata de controlador.

```php
#[Controller(
    scope: ControllerScope::Request,
    middleware: ['auth']
)]
final class UserController
{
}
```

El resolver podrá leer únicamente metadata necesaria para la resolución:

* Scope.
* Tipo.
* Alias.
* Factory.
* Service ID.
* Habilitado o deshabilitado.

La metadata funcional completa será procesada posteriormente por `ControllerMetadataResolver`.

---

# 70. Separación entre resolución y metadata

El resolver podrá leer metadata mínima para resolver.

No deberá procesar completamente:

* Autorización.
* Validación.
* Cache.
* Rate limiting.
* Respuestas.
* OpenAPI.
* Serialización.

Esto evita duplicar responsabilidades.

---

# 71. ControllerDefinitionValidator

Antes de elegir un resolver, se deberá validar la definición.

```php
interface ControllerDefinitionValidatorInterface
{
    public function validate(
        ControllerDefinition $definition
    ): void;
}
```

Validaciones:

* Tipo compatible.
* Campos obligatorios presentes.
* Target válido.
* Clase con formato válido.
* Método con formato válido.
* Alias válido.
* Service ID válido.
* No contiene objetos inseguros.
* Metadata estructural correcta.

---

# 72. Formato de nombre de clase

Se deberán rechazar nombres anómalos.

Ejemplos inválidos:

```text
../../UserController
App\Http\Controllers\UserController.php
App\Http\Controllers\UserController@index
```

La clase deberá ser un FQCN válido.

```php
App\Http\Controllers\UserController::class
```

---

# 73. Formato de método

El método deberá cumplir reglas de identificador PHP.

```php
preg_match(
    '/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/',
    $method
);
```

Además deberá pasar las reglas de seguridad del inspector.

---

# 74. Controladores anónimos

Las clases anónimas podrán funcionar en desarrollo:

```php
new class {
    public function __invoke(): string
    {
        return 'OK';
    }
};
```

Sin embargo:

* No serán compilables fácilmente.
* No serán recomendadas para rutas.
* No serán cacheables.
* Podrán deshabilitarse en producción.

---

# 75. Resolución de callable objects

Un objeto invocable podrá aceptarse como target:

```php
Route::get('/health', new HealthCheckController());
```

Esta sintaxis no será recomendada porque:

* La instancia queda creada antes del request.
* Puede conservar estado.
* Puede escapar al Container.
* Puede romper scopes.
* Puede contaminar FrankenPHP.

Por defecto se deberá rechazar o advertir.

La referencia recomendada será la clase.

---

# 76. Instancias explícitas

Configuración:

```php
'allow_controller_instances' => false,
```

Cuando esté habilitada, el resolver deberá validar:

* Que la instancia sea segura.
* Que no sea singleton accidental.
* Que no contenga contexto previo.
* Que implemente un contrato permitido.
* Que no se compile.

---

# 77. Resolución lazy

La creación de la instancia podrá diferirse hasta que sea necesaria.

Ejemplo futuro:

```php
final readonly class LazyResolvedController
{
    public function instance(): object;
}
```

No obstante, para V1 el resolver podrá crear la instancia durante `ResolveControllerStage`.

La resolución lazy será útil cuando:

* Un stage de caché termine anticipadamente.
* Una autorización basada solo en metadata evite invocación.
* Se procese un preflight.
* Exista un short-circuit previo.

---

# 78. Optimización futura: metadata antes de instancia

En una versión avanzada, el pipeline podrá resolver metadata compilada antes de crear la instancia.

```text
ControllerDefinition
    ↓
Compiled metadata
    ↓
Cache / authorization short-circuit
    ↓
Controller instance only if needed
```

Esto reduciría instanciaciones innecesarias.

La arquitectura deberá permitir mover la etapa de creación sin rediseñar todo el sistema.

---

# 79. DisplayName

Todo controlador resuelto deberá tener un nombre legible.

Ejemplos:

```text
App\Http\Controllers\UserController@index
App\Actions\CreateUserAction::__invoke
Service[reports.generator]@generate
Closure at routes/web.php:42
Page[users.show]
Component[UserTable]
```

El display name será utilizado en:

* Logs.
* Debug toolbar.
* Excepciones.
* Métricas.
* Tracing.
* Auditoría.

---

# 80. ControllerIdentity

Podrá existir una identidad estable separada del display name.

```php
final readonly class ControllerIdentity
{
    public function __construct(
        public string $id,
        public string $class,
        public string $method,
        public string $displayName,
        public ControllerResolutionType $type,
    ) {
    }
}
```

El ID podrá ser:

```text
controller:app.http.user:index
```

Esto facilitará métricas y caché.

---

# 81. Eventos de resolución

Eventos propuestos:

```text
ControllerResolutionStarting
ControllerResolverSelected
ControllerInstanceResolving
ControllerInstanceResolved
ControllerMethodInspecting
ControllerMethodValidated
ControllerResolved
ControllerResolutionFailed
ControllerResolutionCacheHit
ControllerResolutionCacheMiss
```

Los eventos detallados podrán desactivarse en producción.

---

# 82. Observabilidad

Métricas:

```text
controller.resolve.duration
controller.resolve.total
controller.resolve.failures
controller.resolve.cache_hit
controller.resolve.cache_miss
controller.resolve.container_duration
controller.resolve.reflection_duration
```

Tags:

```text
controller.type
controller.class
controller.method
resolver.class
resolution.mode
scope
cache.hit
```

---

# 83. Errores y excepciones

Excepciones previstas:

```text
ControllerResolutionException
UnsupportedControllerDefinitionException
InvalidControllerDefinitionException
MissingControllerClassException
MissingControllerMethodException
ControllerClassNotFoundException
ControllerClassNotInstantiableException
AbstractControllerResolutionException
ControllerMethodNotFoundException
ControllerMethodNotPublicException
ControllerMethodNotAllowedException
StaticControllerMethodNotAllowedException
ControllerNotInvokableException
ControllerServiceNotFoundException
MissingControllerServiceException
ControllerAliasNotFoundException
CircularControllerAliasException
AmbiguousControllerResolverException
ControllerContainerResolutionException
UnsafeControllerReferenceException
ControllerScopeViolationException
StaleControllerResolutionPlanException
```

---

# 84. Ejemplo de excepción descriptiva

```text
Unable to resolve controller.

Controller:
App\Http\Controllers\UserController@show

Reason:
Method [show] does not exist.

Route:
users.show

Request:
GET /users/15

Resolver:
ClassMethodControllerResolver
```

En producción se ocultarán datos sensibles.

---

# 85. ControllerResolutionException

```php
class ControllerResolutionException extends ControllerException
{
    public static function forController(
        string $controller,
        string $reason,
        ?Throwable $previous = null
    ): self {
        return new self(
            sprintf(
                'Unable to resolve controller [%s]: %s',
                $controller,
                $reason
            ),
            previous: $previous
        );
    }
}
```

---

# 86. Integración con debugging

La debug toolbar podrá mostrar:

```text
Controller Resolution

Definition Type:
    class_method

Requested:
    App\Http\Controllers\UserController@show

Resolver:
    ClassMethodControllerResolver

Container ID:
    App\Http\Controllers\UserController

Scope:
    request

Resolved Instance:
    App\Http\Controllers\UserController

Method:
    show

Cache:
    hit

Duration:
    0.09 ms
```

---

# 87. Pruebas unitarias

Casos mínimos:

* Resuelve clase y método.
* Resuelve invokable.
* Resuelve closure.
* Resuelve alias.
* Resuelve servicio registrado.
* Resuelve Action.
* Usa Container.
* Respeta scope.
* Rechaza clase inexistente.
* Rechaza clase abstracta.
* Rechaza método inexistente.
* Rechaza método protegido.
* Rechaza método privado.
* Rechaza método mágico.
* Rechaza método `#[NotAction]`.
* Rechaza service ID no registrado.
* Detecta alias circular.
* Detecta resolvers ambiguos.
* Usa plan compilado.
* Hace fallback dinámico.
* Invalida plan obsoleto.
* No conserva instancia entre requests.

---

# 88. Pruebas de integración

* Route Match → Definition → Resolver.
* Resolver → Container.
* Constructor injection.
* Contextual binding.
* Decorated controller.
* Proxy controller.
* Request scope.
* FrankenPHP multiple requests.
* Compiled route cache.
* Action metadata.
* Page controller.
* Component controller.
* Alias registrado por paquete.

---

# 89. Prueba de scope

```php
public function test_controller_instances_are_not_shared_between_requests(): void
{
    $first = $this->resolver->resolve(
        $definition,
        $firstContext
    );

    $this->container->endScope('request');

    $second = $this->resolver->resolve(
        $definition,
        $secondContext
    );

    expect($first->instance)
        ->not()
        ->toBe($second->instance);
}
```

---

# 90. Prueba de seguridad

```php
public function test_magic_methods_cannot_be_resolved_as_actions(): void
{
    $definition = ControllerDefinition::classMethod(
        UnsafeController::class,
        '__destruct'
    );

    expect(
        fn () => $this->resolver->resolve(
            $definition,
            $context
        )
    )->toThrow(
        ControllerMethodNotAllowedException::class
    );
}
```

---

# 91. Benchmarks

Escenarios:

```text
Class-method dynamic resolution
Invokable dynamic resolution
Compiled resolution
Service resolution
Alias resolution
Controller with constructor dependencies
Decorated controller
Controller cache hit
Controller cache miss
```

Métricas:

* Tiempo total.
* Tiempo de Container.
* Tiempo de Reflection.
* Memoria.
* Objetos creados.
* Resoluciones por segundo.
* Comparación dinámica vs compilada.

---

# 92. Configuración

Archivo sugerido:

```php
return [
    'resolver' => [
        'implementation' => CompositeControllerResolver::class,

        'default_scope' => ControllerScope::Request,

        'compiled' => [
            'enabled' => env('APP_ENV') === 'production',
            'strict' => false,
            'validate_signature' => true,
        ],

        'reflection' => [
            'cache' => true,
        ],

        'closures' => [
            'enabled' => true,
            'allow_in_compiled_routes' => false,
        ],

        'instances' => [
            'enabled' => false,
        ],

        'methods' => [
            'allow_static' => false,
            'allow_inherited' => true,
            'require_explicit_action' => false,
        ],

        'security' => [
            'validate_namespaces' => true,
            'allowed_namespaces' => [
                'App\\Http\\Controllers\\',
                'App\\Actions\\',
            ],
            'denied_namespaces' => [],
        ],
    ],
];
```

---

# 93. Registro en el Container

```php
$container->singleton(
    ControllerResolverInterface::class,
    CompositeControllerResolver::class
);

$container->singleton(
    ControllerResolverRegistryInterface::class,
    ControllerResolverRegistry::class
);

$container->singleton(
    ControllerInspectorInterface::class,
    ControllerInspector::class
);

$container->singleton(
    ControllerSecurityValidatorInterface::class,
    ControllerSecurityValidator::class
);

$container->singleton(
    ControllerDefinitionValidatorInterface::class,
    ControllerDefinitionValidator::class
);

$container->singleton(
    ControllerResolutionCacheInterface::class,
    ControllerResolutionCache::class
);
```

Los resolvers especializados deberán ser stateless.

---

# 94. Bootstrapping

Durante `register`:

* Registrar contrato principal.
* Registrar resolvers especializados.
* Registrar registries.
* Registrar inspector.
* Registrar validador.
* Registrar caché.
* Registrar configuración.

Durante `boot`:

* Cargar aliases.
* Cargar servicios de controlador.
* Cargar planes compilados.
* Ordenar resolvers.
* Validar ambigüedades.
* Detectar aliases circulares.
* Validar scopes.
* Congelar registries en producción.

---

# 95. Registry freezing

En producción, los registries deberán volverse inmutables después del boot.

```php
$registry->freeze();
```

Después de congelarse:

* No se podrán añadir resolvers.
* No se podrán añadir aliases.
* No se podrán registrar servicios.
* No se podrá cambiar prioridad.
* Las modificaciones lanzarán excepción.

Esto mejora seguridad y concurrencia.

---

# 96. Estructura de directorios

```text
src/
└── Quantum/
    └── Controllers/
        ├── ControllerResolver.php
        │
        ├── Contracts/
        │   ├── ControllerResolverInterface.php
        │   ├── ControllerTypeResolverInterface.php
        │   ├── ControllerResolverRegistryInterface.php
        │   ├── ControllerInspectorInterface.php
        │   ├── ControllerSecurityValidatorInterface.php
        │   ├── ControllerDefinitionValidatorInterface.php
        │   ├── ControllerResolutionCacheInterface.php
        │   ├── ControllerAliasRegistryInterface.php
        │   ├── ControllerServiceRegistryInterface.php
        │   └── ControllerFactoryInterface.php
        │
        ├── Definitions/
        │   ├── ResolvedController.php
        │   ├── ControllerResolutionType.php
        │   ├── ControllerResolutionPlan.php
        │   ├── ControllerIdentity.php
        │   ├── ControllerServiceDefinition.php
        │   ├── ControllerMethodInspection.php
        │   └── ControllerClassInspection.php
        │
        ├── Resolvers/
        │   ├── CompositeControllerResolver.php
        │   ├── CompiledControllerResolver.php
        │   ├── ClosureControllerResolver.php
        │   ├── AliasControllerResolver.php
        │   ├── ServiceControllerResolver.php
        │   ├── ActionControllerResolver.php
        │   ├── PageControllerResolver.php
        │   ├── ComponentControllerResolver.php
        │   ├── ResourceControllerResolver.php
        │   ├── ClassMethodControllerResolver.php
        │   └── InvokableControllerResolver.php
        │
        ├── Registry/
        │   ├── ControllerResolverRegistry.php
        │   ├── ControllerAliasRegistry.php
        │   ├── ControllerServiceRegistry.php
        │   ├── CompiledControllerRegistry.php
        │   └── FreezableRegistry.php
        │
        ├── Inspection/
        │   ├── ControllerInspector.php
        │   ├── ControllerMethodInspector.php
        │   ├── ControllerClassInspector.php
        │   ├── ControllerSignatureGenerator.php
        │   └── ProxyClassResolver.php
        │
        ├── Security/
        │   ├── ControllerSecurityValidator.php
        │   ├── ControllerDefinitionValidator.php
        │   ├── ControllerNamespacePolicy.php
        │   ├── ControllerMethodPolicy.php
        │   └── ControllerScopeValidator.php
        │
        ├── Scope/
        │   ├── ControllerScope.php
        │   ├── ControllerScopeResolver.php
        │   └── ControllerScopeInspector.php
        │
        ├── Cache/
        │   ├── ControllerResolutionCache.php
        │   ├── CompiledControllerRegistry.php
        │   ├── ControllerResolutionPlanLoader.php
        │   └── ControllerResolutionPlanWriter.php
        │
        ├── Attributes/
        │   ├── Controller.php
        │   ├── ControllerScope.php
        │   ├── Action.php
        │   ├── Page.php
        │   ├── Component.php
        │   └── NotAction.php
        │
        ├── Events/
        │   ├── ControllerResolutionStarting.php
        │   ├── ControllerResolverSelected.php
        │   ├── ControllerInstanceResolving.php
        │   ├── ControllerInstanceResolved.php
        │   ├── ControllerMethodValidated.php
        │   ├── ControllerResolutionCacheHit.php
        │   ├── ControllerResolutionCacheMiss.php
        │   ├── ControllerResolved.php
        │   └── ControllerResolutionFailed.php
        │
        ├── Exceptions/
        │   ├── ControllerResolutionException.php
        │   ├── UnsupportedControllerDefinitionException.php
        │   ├── InvalidControllerDefinitionException.php
        │   ├── MissingControllerClassException.php
        │   ├── MissingControllerMethodException.php
        │   ├── ControllerClassNotFoundException.php
        │   ├── ControllerClassNotInstantiableException.php
        │   ├── AbstractControllerResolutionException.php
        │   ├── ControllerMethodNotFoundException.php
        │   ├── ControllerMethodNotPublicException.php
        │   ├── ControllerMethodNotAllowedException.php
        │   ├── StaticControllerMethodNotAllowedException.php
        │   ├── ControllerNotInvokableException.php
        │   ├── ControllerServiceNotFoundException.php
        │   ├── ControllerAliasNotFoundException.php
        │   ├── CircularControllerAliasException.php
        │   ├── AmbiguousControllerResolverException.php
        │   ├── ControllerContainerResolutionException.php
        │   ├── UnsafeControllerReferenceException.php
        │   ├── ControllerScopeViolationException.php
        │   └── StaleControllerResolutionPlanException.php
        │
        ├── Compiler/
        │   ├── ControllerResolutionCompiler.php
        │   ├── ControllerResolutionPlanCompiler.php
        │   ├── ControllerAliasCompiler.php
        │   ├── ControllerServiceCompiler.php
        │   └── ControllerResolutionCacheWriter.php
        │
        └── Testing/
            ├── FakeControllerResolver.php
            ├── FakeControllerInspector.php
            ├── FakeControllerAliasRegistry.php
            ├── ControllerResolutionTestBuilder.php
            └── ControllerResolutionAssertions.php
```

---

# 97. Implementación mínima V1

La primera versión deberá incluir:

* `ControllerResolverInterface`.
* `CompositeControllerResolver`.
* `ControllerTypeResolverInterface`.
* `ResolvedController`.
* `ControllerResolutionType`.
* `ClassMethodControllerResolver`.
* `InvokableControllerResolver`.
* `ClosureControllerResolver`.
* `ControllerInspector`.
* Validación de clase.
* Validación de método.
* Resolución mediante Container.
* Scope request o transient.
* Excepciones básicas.
* Reflection cache.
* Pruebas de aislamiento entre requests.

Podrán posponerse:

* Aliases.
* Service controllers.
* Actions especializadas.
* Page resolver.
* Component resolver.
* Resolución lazy.
* Controller factories.
* Proxies avanzados.
* Planes compilados estrictos.
* Registry freezing.

---

# 98. Flujo completo de resolución

```text
ControllerDefinition
    ↓
ControllerDefinitionValidator
    ↓
Compiled plan lookup
    ↓
Select ControllerTypeResolver
    ↓
Validate class or target
    ↓
Resolve instance from Container
    ↓
Inspect class
    ↓
Inspect method
    ↓
Validate method security
    ↓
Resolve scope
    ↓
Build ControllerIdentity
    ↓
Create ResolvedController
    ↓
Return to ResolveControllerStage
```

---

# 99. Ejemplo completo

Ruta:

```php
Route::get('/users/{user}', [
    UserController::class,
    'show',
]);
```

Controlador:

```php
final class UserController extends Controller
{
    public function __construct(
        private readonly UserPresenter $presenter
    ) {
    }

    public function show(User $user): ViewResponse
    {
        return $this->view('users.show', [
            'user' => $this->presenter->present($user),
        ]);
    }
}
```

Definición:

```php
$definition = ControllerDefinition::classMethod(
    UserController::class,
    'show'
);
```

Resolución:

```text
ClassMethodControllerResolver supports definition
    ↓
Validate UserController class
    ↓
Container resolves UserPresenter
    ↓
Container creates UserController
    ↓
Inspector validates show()
    ↓
ResolvedController created
```

Resultado:

```php
new ResolvedController(
    definition: $definition,
    resolutionType: ControllerResolutionType::ClassMethod,
    instance: $controller,
    method: 'show',
    displayName: UserController::class . '@show',
    className: UserController::class,
    methodName: 'show',
    reflection: $classReflection,
    methodReflection: $methodReflection,
);
```

---

# 100. Decisiones arquitectónicas

## ADR-CTRL-RES-001

**Decisión:** El resolver devolverá siempre un `ResolvedController`.

**Razón:** Proporciona una representación uniforme para cualquier tipo de controlador.

---

## ADR-CTRL-RES-002

**Decisión:** La resolución se dividirá por estrategias especializadas.

**Razón:** Evita condicionales monolíticos y facilita extensiones.

---

## ADR-CTRL-RES-003

**Decisión:** Las clases se resolverán mediante el Container.

**Razón:** Permite inyección, scopes, proxies y bindings contextuales.

---

## ADR-CTRL-RES-004

**Decisión:** Los controladores serán request-scoped o transient por defecto.

**Razón:** Evita contaminación de estado en procesos persistentes.

---

## ADR-CTRL-RES-005

**Decisión:** Solo los servicios registrados explícitamente podrán actuar como controladores.

**Razón:** Impide la ejecución arbitraria de servicios internos.

---

## ADR-CTRL-RES-006

**Decisión:** Solo `__invoke()` será aceptado como método mágico ejecutable.

**Razón:** Reduce superficie de ataque y comportamiento impredecible.

---

## ADR-CTRL-RES-007

**Decisión:** La resolución compilada tendrá prioridad sobre la dinámica.

**Razón:** Evita Reflection y validaciones repetidas en producción.

---

## ADR-CTRL-RES-008

**Decisión:** El resolver no resolverá argumentos del método.

**Razón:** Esa responsabilidad pertenece al `MethodParameterResolver`.

---

## ADR-CTRL-RES-009

**Decisión:** El resolver procesará únicamente metadata estructural mínima.

**Razón:** Evita solapamiento con el sistema completo de metadata.

---

## ADR-CTRL-RES-010

**Decisión:** Las instancias explícitas de controladores estarán deshabilitadas por defecto.

**Razón:** Pueden escapar al Container y conservar estado entre peticiones.

---

# 101. Criterios de aceptación

El sistema se considerará correctamente implementado cuando:

* Resuelva controladores de clase y método.
* Resuelva controladores invocables.
* Resuelva closures.
* Utilice el Container para crear instancias.
* Resuelva constructor injection.
* Respete scopes.
* No comparta instancias entre peticiones.
* Valide existencia de clase y método.
* Rechace métodos no públicos.
* Rechace métodos mágicos no permitidos.
* Rechace clases abstractas.
* Produzca un `ResolvedController` uniforme.
* Permita añadir resolvers personalizados.
* Detecte resolvers ambiguos.
* Pueda cargar un plan compilado.
* Mantenga fallback dinámico.
* Sea compatible con route cache.
* No almacene contextos request-scoped.
* Genere errores descriptivos.
* Sea observable y medible.
* Sea seguro bajo FrankenPHP.

---

# 102. Conclusión

El `ControllerResolver` de VoltStack será el componente encargado de transformar una definición declarativa en una unidad ejecutable, validada y contextualizada.

Su arquitectura basada en resolvers especializados permitirá soportar controladores tradicionales, invocables, closures, servicios, aliases, Actions, páginas y componentes sin introducir condicionales rígidos en el núcleo.

La integración con el Container garantizará inyección de dependencias y scopes correctos, mientras que el inspector y las políticas de seguridad evitarán la ejecución accidental o maliciosa de clases y métodos no autorizados.

Finalmente, la resolución compilada permitirá reducir Reflection y trabajo repetido en producción, haciendo que el sistema sea apropiado para aplicaciones de alto rendimiento y procesos persistentes ejecutados sobre FrankenPHP.
