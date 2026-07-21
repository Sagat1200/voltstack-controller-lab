# Clase base de controladores de VoltStack


**Versión:** 1.0
**Estado:** Draft
**Módulo:** `VoltStack\Quantum\Controllers`
**Documento anterior:** `01_CONTROLLER_ARCHITECTURE.md`

---

## 1. Propósito

Este documento define la clase base de controladores de VoltStack, sus responsabilidades, capacidades, límites, contratos y mecanismos de extensión.

La clase base deberá proporcionar una experiencia de desarrollo cómoda, similar a Laravel, sin convertirse en una dependencia obligatoria ni en un objeto monolítico acoplado a todos los servicios del framework.

Un controlador de VoltStack podrá extender la clase base:

```php
use VoltStack\Quantum\Controllers\Controller;

final class UserController extends Controller
{
    public function index()
    {
        return $this->view('users.index');
    }
}
```

Pero también podrá ser una clase PHP completamente independiente:

```php
final class UserController
{
    public function index(UserRepository $users): ViewResponse
    {
        return view('users.index', [
            'users' => $users->all(),
        ]);
    }
}
```

Ambos estilos deberán ser compatibles con el mismo dispatcher, resolver y ciclo de vida.

---

## 2. Objetivos

La clase base deberá:

* Reducir código repetitivo.
* Proporcionar acceso cómodo a operaciones frecuentes.
* Mantener una API pública coherente.
* Facilitar validación y autorización.
* Facilitar creación de respuestas.
* Permitir middleware por controlador.
* Integrarse con el runtime Volt.
* Integrarse con navegación SPA.
* Ser segura en procesos persistentes.
* Ser extensible mediante traits, concerns y macros controladas.
* Mantener compatibilidad con controladores sin herencia.

No deberá:

* Resolver rutas.
* Ejecutar middleware.
* Mantener estado entre peticiones.
* Acceder directamente a variables globales.
* Contener lógica de negocio.
* Convertirse en un service locator universal.
* Exponer todo el contenedor de forma indiscriminada.
* Renderizar directamente vistas o componentes.

---

## 3. Principio de uso opcional

Extender `Controller` será una conveniencia, no una obligación.

```text
Controlador con clase base
    └── DX optimizada

Controlador independiente
    └── Máximo desacoplamiento
```

El sistema interno no deberá asumir que todo controlador:

* Extiende una clase concreta.
* Implementa una interfaz determinada.
* Utiliza traits del framework.
* Define un constructor específico.

La compatibilidad se basará en que la referencia resuelta sea invocable.

---

## 4. Ubicación

```text
src/
└── Quantum/
    └── Controllers/
        ├── Controller.php
        ├── Contracts/
        ├── Concerns/
        ├── Traits/
        ├── Support/
        └── Exceptions/
```

Namespace:

```php
namespace VoltStack\Quantum\Controllers;
```

---

## 5. Diseño inicial

```php
namespace VoltStack\Quantum\Controllers;

use VoltStack\Quantum\Controllers\Concerns\AuthorizesRequests;
use VoltStack\Quantum\Controllers\Concerns\BuildsResponses;
use VoltStack\Quantum\Controllers\Concerns\DispatchesEvents;
use VoltStack\Quantum\Controllers\Concerns\InteractsWithMiddleware;
use VoltStack\Quantum\Controllers\Concerns\ValidatesRequests;

abstract class Controller
{
    use AuthorizesRequests;
    use BuildsResponses;
    use DispatchesEvents;
    use InteractsWithMiddleware;
    use ValidatesRequests;
}
```

La clase base deberá permanecer pequeña.

La mayoría de sus capacidades se implementarán mediante concerns reutilizables.

---

## 6. Arquitectura basada en concerns

Las capacidades de la clase base se dividirán en traits especializados.

```text
Controller
    ├── AuthorizesRequests
    ├── ValidatesRequests
    ├── BuildsResponses
    ├── InteractsWithMiddleware
    ├── DispatchesEvents
    ├── InteractsWithSession
    ├── InteractsWithRouting
    └── InteractsWithRuntime
```

Cada concern deberá:

* Tener una única responsabilidad.
* Depender de contratos.
* Evitar estado mutable persistente.
* Ser reutilizable fuera de la clase base cuando sea razonable.
* Tener pruebas independientes.

---

## 7. Dependencias internas

La clase base no recibirá automáticamente todos los servicios posibles mediante constructor.

En su lugar, utilizará un contexto de ejecución controlado.

```php
interface ControllerExecutionContextAwareInterface
{
    public function setControllerExecutionContext(
        ControllerExecutionContext $context
    ): void;
}
```

La clase base podrá implementar este contrato internamente:

```php
abstract class Controller implements ControllerExecutionContextAwareInterface
{
    private ?ControllerExecutionContext $controllerContext = null;

    final public function setControllerExecutionContext(
        ControllerExecutionContext $context
    ): void {
        $this->controllerContext = $context;
    }

    final protected function controllerContext(): ControllerExecutionContext
    {
        return $this->controllerContext
            ?? throw ControllerContextUnavailableException::create(static::class);
    }
}
```

El contexto se asignará después de resolver el controlador y antes de invocarlo.

---

## 8. ControllerExecutionContext

El contexto de ejecución proporcionará acceso limitado y tipado a los servicios necesarios.

```php
final readonly class ControllerExecutionContext
{
    public function __construct(
        public ControllerContext $context,
        public ControllerResponseFactoryInterface $responses,
        public ValidationManagerInterface $validation,
        public AuthorizationManagerInterface $authorization,
        public EventDispatcherInterface $events,
        public UrlGeneratorInterface $urls,
        public RedirectorInterface $redirector,
        public SessionInterface|null $session,
        public RuntimeManagerInterface|null $runtime,
    ) {
    }
}
```

No deberá exponer directamente el contenedor completo.

### 8.1 Razones

Esto permite:

* Evitar service locator.
* Conocer las dependencias reales.
* Facilitar pruebas.
* Limitar capacidades.
* Mantener seguridad.
* Reducir acoplamiento.
* Reemplazar servicios por contratos.
* Evitar contaminación en FrankenPHP.

---

## 9. Ciclo de vida de la clase base

```text
Container crea controlador
    ↓
ControllerResolver obtiene instancia
    ↓
ControllerExecutionContextFactory crea contexto
    ↓
ContextAwareInjector asigna contexto
    ↓
ArgumentResolver prepara argumentos
    ↓
ControllerInvoker ejecuta método
    ↓
Controller context se libera
```

En runtimes persistentes, el contexto deberá limpiarse incluso cuando ocurra una excepción.

```php
try {
    $injector->inject($controller, $context);

    return $invoker->invoke($controller, $arguments);
} finally {
    $injector->release($controller);
}
```

---

## 10. Protección frente a procesos persistentes

FrankenPHP y otros runtimes persistentes reutilizan el proceso entre peticiones.

Por ello, la clase base no deberá almacenar:

* Request actual.
* Usuario autenticado.
* Tenant actual.
* Session.
* Route match.
* Datos de vista.
* Estado de autorización.
* Resultado de validación.
* Respuestas anteriores.

Como propiedades persistentes ordinarias.

Ejemplo incorrecto:

```php
abstract class Controller
{
    protected Request $request;
    protected User $user;
}
```

Ejemplo permitido:

```php
protected function request(): RequestInterface
{
    return $this->controllerContext()->context->request;
}
```

El contexto será request-scoped y deberá liberarse al finalizar la ejecución.

---

## 11. Concern BuildsResponses

Este concern proporcionará helpers para construir respuestas.

```php
trait BuildsResponses
{
    protected function response(
        string $content = '',
        int $status = 200,
        array $headers = []
    ): ResponseInterface {
        return $this->controllerContext()
            ->responses
            ->make($content, $status, $headers);
    }
}
```

### 11.1 Métodos previstos

```php
protected function response(
    string $content = '',
    int $status = 200,
    array $headers = []
): ResponseInterface;
```

```php
protected function json(
    mixed $data,
    int $status = 200,
    array $headers = [],
    int $options = 0
): JsonResponse;
```

```php
protected function view(
    string $view,
    array $data = [],
    int $status = 200,
    array $headers = []
): ViewResponse;
```

```php
protected function volt(
    string $view,
    array $data = [],
    array $options = []
): VoltResponse;
```

```php
protected function spa(
    string $page,
    array $props = [],
    array $metadata = []
): SpaResponse;
```

```php
protected function component(
    string $component,
    array $props = [],
    array $options = []
): ComponentResponse;
```

```php
protected function redirect(
    string $location,
    int $status = 302,
    array $headers = []
): RedirectResponse;
```

```php
protected function redirectToRoute(
    string $route,
    array $parameters = [],
    int $status = 302,
    array $headers = []
): RedirectResponse;
```

```php
protected function back(
    int $status = 302,
    array $headers = []
): RedirectResponse;
```

```php
protected function noContent(
    int $status = 204,
    array $headers = []
): ResponseInterface;
```

```php
protected function download(
    string $path,
    ?string $name = null,
    array $headers = []
): BinaryFileResponse;
```

```php
protected function stream(
    callable $callback,
    int $status = 200,
    array $headers = []
): StreamResponse;
```

```php
protected function eventStream(
    iterable|callable $events,
    array $headers = []
): EventStreamResponse;
```

---

## 12. Respuestas explícitas

Los helpers deberán devolver objetos de respuesta explícitos.

```php
return $this->json([
    'status' => 'ok',
]);
```

Será preferible a depender exclusivamente de conversiones implícitas.

No obstante, el normalizador seguirá aceptando resultados simples cuando el contexto lo permita:

```php
return [
    'status' => 'ok',
];
```

La clase base deberá favorecer expresividad sin imponer verbosidad.

---

## 13. Concern AuthorizesRequests

Este concern conectará el controlador con el módulo de autorización.

```php
trait AuthorizesRequests
{
    protected function authorize(
        string $ability,
        mixed $subject = null,
        array $context = []
    ): AuthorizationDecision {
        return $this->controllerContext()
            ->authorization
            ->authorize($ability, $subject, $context);
    }
}
```

### 13.1 Métodos previstos

```php
protected function authorize(
    string $ability,
    mixed $subject = null,
    array $context = []
): AuthorizationDecision;
```

```php
protected function authorizeAny(
    array $abilities,
    mixed $subject = null,
    array $context = []
): AuthorizationDecision;
```

```php
protected function check(
    string $ability,
    mixed $subject = null,
    array $context = []
): bool;
```

```php
protected function deny(
    string $message = 'This action is unauthorized.',
    ?string $code = null
): never;
```

```php
protected function currentUser(): ?AuthenticatableInterface;
```

### 13.2 Comportamiento

`authorize()` deberá lanzar una excepción si el acceso es rechazado.

```php
$this->authorize('update', $user);
```

`check()` devolverá un booleano.

```php
if ($this->check('delete', $user)) {
    // ...
}
```

---

## 14. Autorización declarativa vs imperativa

VoltStack deberá permitir ambos estilos.

### 14.1 Declarativa

```php
#[Authorize('update', subject: 'user')]
public function update(User $user): ResponseInterface
{
}
```

### 14.2 Imperativa

```php
public function update(User $user): ResponseInterface
{
    $this->authorize('update', $user);
}
```

La autorización declarativa será procesada por un interceptor antes de ejecutar el método.

La autorización imperativa se utilizará dentro del cuerpo del controlador.

---

## 15. Concern ValidatesRequests

Este concern ofrecerá acceso conveniente al módulo de validación.

```php
trait ValidatesRequests
{
    protected function validate(
        array|object $input,
        array|string $rules,
        array $messages = [],
        array $attributes = []
    ): ValidatedData {
        return $this->controllerContext()
            ->validation
            ->validate($input, $rules, $messages, $attributes);
    }
}
```

### 15.1 Métodos previstos

```php
protected function validate(
    array|object $input,
    array|string $rules,
    array $messages = [],
    array $attributes = []
): ValidatedData;
```

```php
protected function validateRequest(
    array|string $rules,
    array $messages = [],
    array $attributes = []
): ValidatedData;
```

```php
protected function validateWith(
    string $validator,
    mixed $input = null
): ValidatedData;
```

```php
protected function validatedDto(
    string $dtoClass,
    mixed $input = null
): object;
```

```php
protected function validationFactory(): ValidationFactoryInterface;
```

---

## 16. Ejemplos de validación

### 16.1 Reglas directas

```php
public function store(Request $request): RedirectResponse
{
    $data = $this->validateRequest([
        'name' => ['required', 'string', 'max:150'],
        'email' => ['required', 'email', 'unique:users,email'],
    ]);

    return $this->redirectToRoute('users.index');
}
```

### 16.2 DTO

```php
public function store(Request $request): RedirectResponse
{
    $data = $this->validatedDto(
        CreateUserData::class,
        $request->all()
    );

    return $this->redirectToRoute('users.index');
}
```

### 16.3 Resolución automática

```php
public function store(CreateUserData $data): RedirectResponse
{
}
```

Este último estilo será responsabilidad del `DtoArgumentResolver`.

---

## 17. Concern InteractsWithMiddleware

La clase base podrá declarar middleware de forma programática.

```php
trait InteractsWithMiddleware
{
    /**
     * @var list<ControllerMiddlewareDefinition>
     */
    private array $controllerMiddleware = [];

    protected function middleware(
        string|object|Closure $middleware,
        array $options = []
    ): ControllerMiddlewareRegistration {
        // ...
    }
}
```

### 17.1 Uso desde constructor

```php
final class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('verified')
            ->only(['create', 'store']);

        $this->middleware('throttle:users')
            ->except(['index']);
    }
}
```

### 17.2 API prevista

```php
protected function middleware(
    string|object|Closure $middleware,
    array $options = []
): ControllerMiddlewareRegistration;
```

```php
protected function withoutMiddleware(
    string|array $middleware
): void;
```

```php
final public function controllerMiddlewareDefinitions(): array;
```

---

## 18. ControllerMiddlewareRegistration

```php
final class ControllerMiddlewareRegistration
{
    public function only(string|array $methods): static;

    public function except(string|array $methods): static;

    public function priority(int $priority): static;

    public function before(string $middleware): static;

    public function after(string $middleware): static;

    public function when(callable|bool $condition): static;
}
```

Ejemplo:

```php
$this->middleware('audit')
    ->only(['store', 'update', 'destroy'])
    ->priority(300);
```

---

## 19. Middleware declarativo

El estilo mediante atributos también estará disponible.

```php
#[Middleware('auth')]
final class UserController extends Controller
{
    #[Middleware('verified')]
    public function store(): ResponseInterface
    {
    }

    #[WithoutMiddleware('auth')]
    public function publicProfile(): ResponseInterface
    {
    }
}
```

La metadata declarativa y programática deberá combinarse de forma determinista.

---

## 20. Precedencia de middleware

Orden recomendado:

```text
Middleware global
    ↓
Middleware de grupo
    ↓
Middleware de ruta
    ↓
Middleware de clase
    ↓
Middleware registrado en constructor
    ↓
Middleware de método
```

La eliminación de middleware deberá aplicarse después de combinar todas las fuentes.

---

## 21. Concern DispatchesEvents

Este concern permitirá publicar eventos de aplicación.

```php
trait DispatchesEvents
{
    protected function dispatch(object $event): object
    {
        return $this->controllerContext()
            ->events
            ->dispatch($event);
    }
}
```

### 21.1 Métodos previstos

```php
protected function dispatch(object $event): object;
```

```php
protected function dispatchAfterResponse(object $event): void;
```

```php
protected function dispatchAsync(object $event): void;
```

```php
protected function eventDispatcher(): EventDispatcherInterface;
```

La disponibilidad de ejecución asíncrona dependerá del módulo correspondiente.

---

## 22. Concern InteractsWithRouting

Este concern proporcionará acceso al sistema de rutas y URL.

```php
trait InteractsWithRouting
{
    protected function route(
        string $name,
        array $parameters = [],
        bool $absolute = true
    ): string {
        return $this->controllerContext()
            ->urls
            ->route($name, $parameters, $absolute);
    }
}
```

### 22.1 Métodos previstos

```php
protected function route(
    string $name,
    array $parameters = [],
    bool $absolute = true
): string;
```

```php
protected function url(
    string $path,
    array $query = [],
    bool $absolute = true
): string;
```

```php
protected function signedRoute(
    string $name,
    array $parameters = [],
    ?DateTimeInterface $expiration = null
): string;
```

```php
protected function currentRoute(): RouteMatch;
```

```php
protected function routeParameter(
    string $name,
    mixed $default = null
): mixed;
```

---

## 23. Concern InteractsWithSession

Este concern será opcional.

```php
trait InteractsWithSession
{
    protected function session(): SessionInterface
    {
        return $this->controllerContext()->session
            ?? throw SessionUnavailableException::forController(static::class);
    }
}
```

### 23.1 Métodos previstos

```php
protected function session(): SessionInterface;
```

```php
protected function flash(string $key, mixed $value): void;
```

```php
protected function flashInput(array $input): void;
```

```php
protected function old(
    string $key,
    mixed $default = null
): mixed;
```

```php
protected function withErrors(
    MessageBag|array $errors,
    string $bag = 'default'
): void;
```

Estos métodos fallarán de forma explícita cuando la sesión no esté disponible, por ejemplo, en rutas stateless.

---

## 24. Concern InteractsWithRuntime

Este concern integrará el controlador con Volt Runtime y SPA Runtime.

```php
trait InteractsWithRuntime
{
    protected function runtime(): RuntimeManagerInterface
    {
        return $this->controllerContext()->runtime
            ?? throw RuntimeUnavailableException::forController(static::class);
    }
}
```

### 24.1 Métodos previstos

```php
protected function isSpaNavigation(): bool;
```

```php
protected function isPartialRequest(): bool;
```

```php
protected function isPrefetchRequest(): bool;
```

```php
protected function preserveState(bool $preserve = true): void;
```

```php
protected function preserveScroll(bool $preserve = true): void;
```

```php
protected function replaceHistory(bool $replace = true): void;
```

```php
protected function runtimeMetadata(
    string $key,
    mixed $value
): void;
```

---

## 25. Helpers de navegación SPA

Ejemplo:

```php
public function update(
    User $user,
    UpdateUserData $data
): RedirectResponse {
    $user->update($data->toArray());

    $this->preserveScroll();

    return $this->redirectToRoute('users.show', [
        'user' => $user,
    ]);
}
```

El redirector podrá convertir la respuesta automáticamente en una instrucción de navegación SPA cuando corresponda.

---

## 26. Acceso a request

La clase base podrá proporcionar acceso a la petición actual.

```php
protected function request(): RequestInterface
{
    return $this->controllerContext()->context->request;
}
```

No obstante, se recomendará inyectarla explícitamente cuando sea una dependencia relevante.

Preferido:

```php
public function store(Request $request): ResponseInterface
{
}
```

Permitido:

```php
public function store(): ResponseInterface
{
    $request = $this->request();
}
```

Esto conserva ergonomía sin ocultar demasiado las dependencias.

---

## 27. Acceso a usuario y tenant

```php
protected function user(): ?AuthenticatableInterface
{
    return $this->controllerContext()
        ->context
        ->user;
}
```

```php
protected function tenant(): ?TenantInterface
{
    return $this->controllerContext()
        ->context
        ->tenant;
}
```

Versiones estrictas:

```php
protected function authenticatedUser(): AuthenticatableInterface;
```

```php
protected function currentTenant(): TenantInterface;
```

Estas versiones lanzarán excepciones cuando no exista contexto válido.

---

## 28. API pública recomendada

La clase base inicial podrá ofrecer:

```php
abstract class Controller
{
    protected function request(): RequestInterface;

    protected function user(): ?AuthenticatableInterface;

    protected function authenticatedUser(): AuthenticatableInterface;

    protected function tenant(): ?TenantInterface;

    protected function currentTenant(): TenantInterface;

    protected function response(
        string $content = '',
        int $status = 200,
        array $headers = []
    ): ResponseInterface;

    protected function json(
        mixed $data,
        int $status = 200,
        array $headers = [],
        int $options = 0
    ): JsonResponse;

    protected function view(
        string $view,
        array $data = [],
        int $status = 200,
        array $headers = []
    ): ViewResponse;

    protected function volt(
        string $view,
        array $data = [],
        array $options = []
    ): VoltResponse;

    protected function spa(
        string $page,
        array $props = [],
        array $metadata = []
    ): SpaResponse;

    protected function component(
        string $component,
        array $props = [],
        array $options = []
    ): ComponentResponse;

    protected function redirect(
        string $location,
        int $status = 302,
        array $headers = []
    ): RedirectResponse;

    protected function redirectToRoute(
        string $route,
        array $parameters = [],
        int $status = 302,
        array $headers = []
    ): RedirectResponse;

    protected function back(
        int $status = 302,
        array $headers = []
    ): RedirectResponse;

    protected function noContent(
        int $status = 204,
        array $headers = []
    ): ResponseInterface;

    protected function authorize(
        string $ability,
        mixed $subject = null,
        array $context = []
    ): AuthorizationDecision;

    protected function check(
        string $ability,
        mixed $subject = null,
        array $context = []
    ): bool;

    protected function validate(
        array|object $input,
        array|string $rules,
        array $messages = [],
        array $attributes = []
    ): ValidatedData;

    protected function validateRequest(
        array|string $rules,
        array $messages = [],
        array $attributes = []
    ): ValidatedData;

    protected function dispatch(object $event): object;

    protected function route(
        string $name,
        array $parameters = [],
        bool $absolute = true
    ): string;

    protected function middleware(
        string|object|Closure $middleware,
        array $options = []
    ): ControllerMiddlewareRegistration;
}
```

---

## 29. Método beforeAction

VoltStack podrá soportar hooks ligeros de ciclo de vida.

```php
protected function beforeAction(
    ControllerInvocation $invocation
): void {
}
```

```php
protected function afterAction(
    ControllerInvocation $invocation,
    mixed $result
): mixed {
    return $result;
}
```

Sin embargo, estos hooks no deberán reemplazar middleware o interceptores.

Su uso estará limitado a necesidades locales del controlador.

---

## 30. Recomendación sobre hooks

Los hooks deberán:

* Ser opcionales.
* Tener firmas explícitas.
* Ejecutarse mediante un interceptor.
* No modificar silenciosamente argumentos.
* No capturar excepciones globales.
* No depender del orden accidental de traits.
* Poder desactivarse en configuración.

Ejemplo:

```php
interface ControllerHooksInterface
{
    public function beforeAction(
        ControllerInvocation $invocation
    ): void;

    public function afterAction(
        ControllerInvocation $invocation,
        mixed $result
    ): mixed;
}
```

La clase base podrá implementar métodos vacíos protegidos, detectados por el invoker o por un interceptor.

---

## 31. No incluir lógica de negocio

Ejemplo incorrecto:

```php
final class UserController extends Controller
{
    public function store(Request $request): ResponseInterface
    {
        $user = new User();
        $user->name = $request->input('name');
        $user->email = $request->input('email');
        $user->save();

        Mail::send(...);
        Cache::forget(...);
        Audit::log(...);

        return $this->redirectToRoute('users.index');
    }
}
```

Ejemplo recomendado:

```php
final class UserController extends Controller
{
    public function store(
        CreateUserData $data,
        CreateUserAction $createUser
    ): RedirectResponse {
        $user = $createUser->execute($data);

        return $this->redirectToRoute('users.show', [
            'user' => $user,
        ]);
    }
}
```

El controlador deberá coordinar la petición, no implementar el dominio completo.

---

## 32. Constructor injection

Los controladores podrán utilizar constructor injection.

```php
final class UserController extends Controller
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPresenter $presenter
    ) {
    }
}
```

También method injection:

```php
public function show(
    User $user,
    UserPresenter $presenter
): ViewResponse {
}
```

### 32.1 Recomendación

Usar constructor injection para dependencias utilizadas por varios métodos.

Usar method injection para dependencias específicas de una acción.

---

## 33. Restricciones de constructor

El constructor deberá:

* Ser público.
* No recibir la request actual como dependencia persistente, salvo que sea un proxy request-scoped.
* No ejecutar consultas.
* No iniciar transacciones.
* No despachar eventos.
* No depender de valores de ruta.
* No realizar lógica de autorización.
* No producir respuestas.
* No mutar estado global.

Su función será declarar dependencias.

---

## 34. Clase base minimalista

Una implementación inicial podría ser:

```php
namespace VoltStack\Quantum\Controllers;

abstract class Controller implements
    ControllerExecutionContextAwareInterface
{
    use AuthorizesRequests;
    use BuildsResponses;
    use DispatchesEvents;
    use InteractsWithMiddleware;
    use InteractsWithRouting;
    use InteractsWithRuntime;
    use InteractsWithSession;
    use ValidatesRequests;

    private ?ControllerExecutionContext $controllerContext = null;

    final public function setControllerExecutionContext(
        ControllerExecutionContext $context
    ): void {
        $this->controllerContext = $context;
    }

    final public function releaseControllerExecutionContext(): void
    {
        $this->controllerContext = null;
    }

    final protected function controllerContext(): ControllerExecutionContext
    {
        return $this->controllerContext
            ?? throw ControllerContextUnavailableException::create(
                static::class
            );
    }

    protected function request(): RequestInterface
    {
        return $this->controllerContext()
            ->context
            ->request;
    }

    protected function user(): ?AuthenticatableInterface
    {
        return $this->controllerContext()
            ->context
            ->user;
    }

    protected function tenant(): ?TenantInterface
    {
        return $this->controllerContext()
            ->context
            ->tenant;
    }
}
```

---

## 35. Inyección del contexto

El dispatcher no deberá implementar directamente la inyección.

Se utilizará un componente especializado.

```php
interface ControllerContextInjectorInterface
{
    public function inject(
        object $controller,
        ControllerExecutionContext $context
    ): void;

    public function release(object $controller): void;
}
```

Implementación:

```php
final class ControllerContextInjector implements
    ControllerContextInjectorInterface
{
    public function inject(
        object $controller,
        ControllerExecutionContext $context
    ): void {
        if ($controller instanceof ControllerExecutionContextAwareInterface) {
            $controller->setControllerExecutionContext($context);
        }
    }

    public function release(object $controller): void
    {
        if ($controller instanceof ControllerExecutionContextAwareInterface) {
            $controller->releaseControllerExecutionContext();
        }
    }
}
```

Contrato actualizado:

```php
interface ControllerExecutionContextAwareInterface
{
    public function setControllerExecutionContext(
        ControllerExecutionContext $context
    ): void;

    public function releaseControllerExecutionContext(): void;
}
```

---

## 36. Contexto lazy

Para evitar crear servicios innecesarios, el contexto podrá utilizar accessors lazy.

```php
final class ControllerExecutionContext
{
    public function responses(): ControllerResponseFactoryInterface;

    public function validation(): ValidationManagerInterface;

    public function authorization(): AuthorizationManagerInterface;

    public function events(): EventDispatcherInterface;

    public function urls(): UrlGeneratorInterface;

    public function session(): ?SessionInterface;

    public function runtime(): ?RuntimeManagerInterface;
}
```

Esto permitirá resolver únicamente los servicios realmente utilizados por el controlador.

---

## 37. Controller capabilities

Los controladores podrán declarar capacidades explícitas.

```php
interface SupportsAuthorization
{
}
```

```php
interface SupportsValidation
{
}
```

```php
interface SupportsRuntime
{
}
```

Sin embargo, en la primera versión estas interfaces se utilizarán principalmente para:

* Optimización.
* Compilación.
* Documentación.
* Análisis estático.

No serán necesarias para usar los helpers de la clase base.

---

## 38. Traits personalizados

Los desarrolladores podrán crear traits propios.

```php
trait RespondsWithSuccess
{
    protected function success(
        mixed $data = null,
        string $message = 'OK'
    ): JsonResponse {
        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }
}
```

Uso:

```php
final class UserApiController extends Controller
{
    use RespondsWithSuccess;
}
```

Los traits personalizados no deberán acceder a propiedades internas privadas de `Controller`.

Deberán utilizar métodos protegidos estables.

---

## 39. Superficie protegida estable

VoltStack deberá definir qué métodos protegidos forman parte de la API estable.

Ejemplo:

```text
controllerContext()
request()
user()
tenant()
response()
json()
view()
volt()
spa()
component()
redirect()
redirectToRoute()
authorize()
validate()
dispatch()
route()
middleware()
```

Cambiar sus firmas requerirá una versión mayor del framework.

Los métodos internos deberán marcarse como:

```php
/** @internal */
```

---

## 40. Macros

Podrá existir un sistema de macros, pero no deberá formar parte del núcleo inicial.

Ejemplo futuro:

```php
Controller::macro('success', function (
    mixed $data = null
): JsonResponse {
    return $this->json([
        'success' => true,
        'data' => $data,
    ]);
});
```

### 40.1 Riesgos

Las macros pueden:

* Reducir análisis estático.
* Crear colisiones de nombres.
* Ocultar dependencias.
* Complicar compilación.
* Generar incompatibilidades entre paquetes.

Por ello, se recomienda priorizar:

* Traits.
* Composición.
* Response factories.
* Clases base especializadas.

---

## 41. Clases base especializadas

VoltStack podrá proporcionar clases opcionales.

```text
Controller
ApiController
PageController
ComponentController
ActionController
```

### 41.1 ApiController

```php
abstract class ApiController extends Controller
{
    protected function success(
        mixed $data = null,
        int $status = 200
    ): JsonResponse {
        return $this->json([
            'data' => $data,
        ], $status);
    }
}
```

### 41.2 PageController

```php
abstract class PageController extends Controller
{
    protected function page(
        string $name,
        array $data = []
    ): VoltResponse {
        return $this->volt($name, $data);
    }
}
```

Estas clases no deberán ser necesarias para usar APIs, páginas o componentes.

Solo ofrecerán convenciones adicionales.

---

## 42. Controladores finales

Se recomendará declarar los controladores como `final`.

```php
final class UserController extends Controller
{
}
```

Ventajas:

* Evita herencia accidental.
* Mejora claridad.
* Facilita optimización.
* Reduce extensión no controlada.
* Favorece composición.
* Mejora análisis estático.

Las clases base abstractas podrán extenderse; los controladores concretos preferentemente no.

---

## 43. Métodos de acción públicos

Solo los métodos públicos registrados explícitamente deberán poder ejecutarse.

No se deberá permitir resolver automáticamente cualquier método público de una clase por nombre recibido del usuario.

Ejemplo seguro:

```php
Route::get('/users', [
    UserController::class,
    'index',
]);
```

El dispatcher validará que:

* La clase exista.
* El método exista.
* El método sea público.
* El método no sea constructor.
* El método no sea destructor.
* El método no sea mágico no autorizado.
* La referencia provenga de metadata confiable.

---

## 44. Métodos protegidos y privados

Los métodos auxiliares del controlador deberán ser protegidos o privados.

```php
final class UserController extends Controller
{
    public function show(User $user): ViewResponse
    {
        return $this->view('users.show', [
            'user' => $this->present($user),
        ]);
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }
}
```

El sistema nunca deberá exponer métodos protegidos o privados como acciones.

---

## 45. Métodos mágicos

El único método mágico ejecutable por convención será:

```php
__invoke()
```

Para controladores invocables.

Otros métodos mágicos deberán bloquearse:

```text
__call
__callStatic
__get
__set
__serialize
__unserialize
__destruct
```

El resolver no deberá considerar `__call()` como sustituto de un método inexistente.

---

## 46. Controladores invocables

Ejemplo:

```php
final class ShowDashboardController extends Controller
{
    public function __invoke(
        DashboardService $dashboard
    ): VoltResponse {
        return $this->volt('dashboard', [
            'metrics' => $dashboard->metrics(),
        ]);
    }
}
```

Ruta:

```php
Route::get('/dashboard', ShowDashboardController::class);
```

No requerirá configuración adicional.

---

## 47. Controladores sin clase base

Ejemplo completo:

```php
final class ShowUserController
{
    public function __invoke(
        User $user,
        ViewFactoryInterface $views
    ): ViewResponse {
        return $views->make('users.show', [
            'user' => $user,
        ]);
    }
}
```

Esto deberá funcionar exactamente con el mismo resolver.

La diferencia será únicamente ergonómica.

---

## 48. Testabilidad

La clase base deberá ser fácil de probar sin iniciar todo el framework.

Ejemplo:

```php
$context = ControllerExecutionContextFake::create()
    ->withUser($user)
    ->withResponses(new FakeResponseFactory());

$controller = new UserController();

$injector->inject($controller, $context);

$response = $controller->index();
```

El módulo Testing podrá proporcionar:

```text
ControllerExecutionContextFake
FakeAuthorizationManager
FakeValidationManager
FakeResponseFactory
FakeRuntimeManager
InteractsWithControllerContext
```

---

## 49. Pruebas unitarias de concerns

Cada concern deberá tener pruebas independientes.

```text
BuildsResponsesTest
AuthorizesRequestsTest
ValidatesRequestsTest
InteractsWithMiddlewareTest
DispatchesEventsTest
InteractsWithRoutingTest
InteractsWithRuntimeTest
InteractsWithSessionTest
```

Casos mínimos:

* Funciona con dependencia disponible.
* Falla de forma clara cuando falta una dependencia.
* No conserva estado entre contextos.
* Libera correctamente el contexto.
* No accede al contenedor global.
* Devuelve el tipo esperado.

---

## 50. Excepciones

Excepciones previstas:

```text
ControllerContextUnavailableException
ControllerContextAlreadyAssignedException
SessionUnavailableException
RuntimeUnavailableException
AuthenticatedUserUnavailableException
TenantUnavailableException
InvalidControllerMiddlewareException
ControllerHelperUnavailableException
```

Ejemplo:

```php
final class ControllerContextUnavailableException
    extends ControllerException
{
    public static function create(string $controller): self
    {
        return new self(
            sprintf(
                'The controller context is not available for [%s]. '
                . 'Controller helpers can only be used during an active execution.',
                $controller
            )
        );
    }
}
```

---

## 51. Seguridad

La clase base deberá cumplir:

* No exponer el contenedor completo.
* No conservar datos entre peticiones.
* No serializar el contexto.
* No permitir modificar el contexto una vez asignado.
* No exponer credenciales o secretos.
* No incluir datos sensibles en excepciones.
* No permitir middleware arbitrario desde input del usuario.
* No convertir automáticamente objetos desconocidos en respuestas.
* No permitir acceso a sesión en rutas stateless sin error explícito.
* No ocultar fallos de autorización.

---

## 52. Rendimiento

La clase base deberá tener un coste mínimo.

Medidas:

* Traits sin inicialización costosa.
* Contexto creado únicamente para controladores que lo necesiten.
* Servicios lazy.
* Sin reflexión en cada helper.
* Sin acceso repetido al contenedor.
* Sin propiedades dinámicas.
* Sin closures capturando el contenedor.
* Sin registros globales por instancia.
* Liberación explícita del contexto.
* Compatible con OPcache preloading.

---

## 53. Compatibilidad con análisis estático

La API deberá proporcionar tipos de retorno concretos.

Ejemplo:

```php
protected function json(...): JsonResponse;
```

En lugar de:

```php
protected function json(...): mixed;
```

Se deberán proporcionar stubs o extensiones para:

* PHPStan.
* Psalm.
* IDEs.
* Autocompletado.
* Generics de respuestas y DTOs cuando sea posible.

---

## 54. Compatibilidad con FrankenPHP

Reglas específicas:

* Ningún contexto persistirá después de la respuesta.
* Los controladores no serán singleton por defecto.
* Todo estado request-scoped deberá liberarse en `finally`.
* Los services lazy deberán respetar el scope actual.
* La sesión deberá estar asociada a la petición.
* El usuario y tenant deberán resolverse por contexto, no por propiedad global.
* Las pruebas deberán ejecutar múltiples requests en el mismo proceso.
* Se deberán detectar fugas de memoria y referencias circulares.

---

## 55. Configuración

Archivo sugerido:

```php
return [
    'base_controller' => [
        'enabled' => true,

        'context' => [
            'lazy' => true,
            'release_after_invocation' => true,
            'prevent_reassignment' => true,
        ],

        'helpers' => [
            'responses' => true,
            'authorization' => true,
            'validation' => true,
            'middleware' => true,
            'events' => true,
            'routing' => true,
            'session' => true,
            'runtime' => true,
        ],

        'macros' => [
            'enabled' => false,
        ],

        'hooks' => [
            'enabled' => true,
        ],
    ],
];
```

---

## 56. Estructura de archivos

```text
Controllers/
├── Controller.php
│
├── Contracts/
│   ├── ControllerExecutionContextAwareInterface.php
│   ├── ControllerContextInjectorInterface.php
│   └── ControllerHooksInterface.php
│
├── Context/
│   ├── ControllerExecutionContext.php
│   ├── ControllerExecutionContextFactory.php
│   └── ControllerContextInjector.php
│
├── Concerns/
│   ├── AuthorizesRequests.php
│   ├── BuildsResponses.php
│   ├── DispatchesEvents.php
│   ├── InteractsWithMiddleware.php
│   ├── InteractsWithRouting.php
│   ├── InteractsWithRuntime.php
│   ├── InteractsWithSession.php
│   └── ValidatesRequests.php
│
├── Middleware/
│   ├── ControllerMiddlewareDefinition.php
│   └── ControllerMiddlewareRegistration.php
│
├── Exceptions/
│   ├── ControllerContextUnavailableException.php
│   ├── ControllerContextAlreadyAssignedException.php
│   ├── SessionUnavailableException.php
│   ├── RuntimeUnavailableException.php
│   ├── AuthenticatedUserUnavailableException.php
│   └── TenantUnavailableException.php
│
└── Testing/
    ├── ControllerExecutionContextFake.php
    ├── FakeResponseFactory.php
    ├── FakeAuthorizationManager.php
    ├── FakeValidationManager.php
    └── InteractsWithControllerContext.php
```

---

## 57. Implementación mínima para V1

La primera versión deberá incluir:

* Clase `Controller`.
* Contexto de ejecución.
* Inyector y liberador de contexto.
* `BuildsResponses`.
* `InteractsWithMiddleware`.
* `AuthorizesRequests`.
* `ValidatesRequests`.
* Acceso a request.
* Acceso a user.
* Acceso a tenant.
* Helpers de rutas y redirect.
* Pruebas en proceso persistente.

Podrán posponerse:

* Macros.
* Event streams.
* Runtime metadata avanzada.
* Hooks complejos.
* Controller capabilities.
* Async events.
* Clases base especializadas.

---

## 58. Ejemplo completo

```php
namespace App\Http\Controllers;

use App\Actions\Users\CreateUserAction;
use App\Data\Users\CreateUserData;
use App\Models\User;
use VoltStack\Quantum\Controllers\Controller;
use VoltStack\Quantum\Http\Response\RedirectResponse;
use VoltStack\Quantum\Http\Response\ViewResponse;

final class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('verified')
            ->only(['create', 'store']);
    }

    public function index(): ViewResponse
    {
        return $this->view('users.index');
    }

    public function show(User $user): ViewResponse
    {
        $this->authorize('view', $user);

        return $this->view('users.show', [
            'user' => $user,
        ]);
    }

    public function store(
        CreateUserData $data,
        CreateUserAction $createUser
    ): RedirectResponse {
        $user = $createUser->execute($data);

        $this->dispatch(new UserCreated($user));

        $this->flash(
            'status',
            'Usuario creado correctamente.'
        );

        return $this->redirectToRoute('users.show', [
            'user' => $user,
        ]);
    }
}
```

---

## 59. Criterios de aceptación

La clase base estará correctamente implementada cuando:

* Sea completamente opcional.
* Un controlador sin herencia funcione con el mismo dispatcher.
* Los helpers dependan de contratos.
* No se exponga el contenedor completo.
* El contexto solo exista durante la ejecución.
* El contexto se libere incluso ante excepciones.
* Los helpers de respuesta devuelvan tipos concretos.
* Autorización y validación puedan usarse de forma imperativa.
* El middleware pueda registrarse en el constructor.
* Los atributos de middleware puedan coexistir con el registro programático.
* El controlador no conserve request, user o tenant entre peticiones.
* Las pruebas bajo FrankenPHP simulado no detecten contaminación de estado.
* Los concerns puedan probarse de forma independiente.
* La API protegida esté documentada y tipada.
* Los errores por contexto ausente sean claros.
* El coste de usar la clase base sea mínimo.

---

## 60. Decisiones arquitectónicas

### ADR-CTRL-BASE-001

**Decisión:** Extender la clase base será opcional.

**Razón:** Permite controladores desacoplados y evita imponer herencia.

---

### ADR-CTRL-BASE-002

**Decisión:** La clase base no expondrá el contenedor.

**Razón:** Evita service locator y dependencias ocultas.

---

### ADR-CTRL-BASE-003

**Decisión:** Las capacidades se implementarán mediante concerns.

**Razón:** Mantiene pequeña la clase base y facilita pruebas.

---

### ADR-CTRL-BASE-004

**Decisión:** El contexto será asignado y liberado por cada ejecución.

**Razón:** Previene contaminación en procesos persistentes.

---

### ADR-CTRL-BASE-005

**Decisión:** Los helpers devolverán objetos explícitos de respuesta.

**Razón:** Mejora tipado, análisis estático y previsibilidad.

---

### ADR-CTRL-BASE-006

**Decisión:** Middleware, autorización y validación seguirán siendo sistemas externos.

**Razón:** La clase base solo proporcionará una fachada tipada hacia sus contratos.

---

### ADR-CTRL-BASE-007

**Decisión:** Los controladores concretos deberán preferentemente ser `final`.

**Razón:** Favorece composición y reduce herencia accidental.

---

### ADR-CTRL-BASE-008

**Decisión:** El contexto utilizará servicios lazy cuando sea posible.

**Razón:** Reduce instanciación y consumo de memoria por petición.

---

## 61. Conclusión

La clase base de VoltStack deberá ofrecer una experiencia cómoda sin comprometer la arquitectura interna del framework.

Su función será proporcionar una capa ergonómica sobre contratos ya existentes, no convertirse en el centro del sistema ni concentrar responsabilidades de routing, rendering, autorización, validación o sesión.

Al mantenerla pequeña, opcional, tipada y basada en un contexto request-scoped, VoltStack podrá ofrecer una experiencia similar a Laravel, preservar la modularidad interna inspirada en Symfony y funcionar de forma segura bajo FrankenPHP y otros runtimes persistentes.
