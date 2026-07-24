# Arquitectura del sistema de controladores de VoltStack

**Versión:** 1.0
**Estado:** Draft
**Módulo:** `VoltStack\Quantum\Controllers`
**Documento anterior:** `00_CONTROLLER_PROJECT_CONTEXT.md`

---

## 1. Propósito

Este documento define la arquitectura interna del sistema de controladores de VoltStack.

El sistema será responsable de convertir una definición de controlador asociada a una ruta en una ejecución válida, resolver sus dependencias, procesar sus parámetros, aplicar comportamientos adicionales y normalizar su resultado en una respuesta compatible con el kernel HTTP.

La arquitectura debe permitir utilizar una misma infraestructura para:

* Controladores HTTP tradicionales.
* Controladores invocables.
* Controladores de recursos.
* Actions.
* Controladores de API.
* Controladores de páginas Volt.
* Controladores de componentes.
* Controladores SPA.
* Respuestas streaming.
* Server Actions futuras.
* Ejecuciones internas no HTTP.

La experiencia pública deberá mantenerse cercana a Laravel, mientras que la estructura interna seguirá principios de separación de responsabilidades, contratos explícitos y resolución extensible inspirados en Symfony.

---

## 2. Visión arquitectónica

El sistema de controladores se encuentra entre el sistema de rutas y el sistema de respuestas.

```text
HTTP Request
    │
    ▼
HttpKernel
    │
    ▼
Routing System
    │
    ▼
Route Match
    │
    ▼
Middleware Pipeline
    │
    ▼
Controller System
    │
    ├── Controller Definition
    ├── Controller Resolver
    ├── Metadata Resolver
    ├── Argument Resolver
    ├── Controller Dispatcher
    ├── Result Normalizer
    └── Response Factory
    │
    ▼
HTTP Response
```

El sistema de rutas determina qué controlador debe ejecutarse.

El sistema de controladores determina:

1. Cómo interpretar esa definición.
2. Cómo obtener la instancia.
3. Qué método debe invocarse.
4. Qué argumentos deben entregarse.
5. Qué comportamientos rodean la ejecución.
6. Cómo convertir el resultado en una respuesta.

---

## 3. Principios arquitectónicos

### 3.1 Separación de responsabilidades

Cada componente deberá tener una responsabilidad concreta.

El resolver no ejecutará controladores.

El dispatcher no interpretará rutas.

El argument resolver no construirá respuestas.

El response normalizer no resolverá dependencias.

Esta separación facilitará:

* Pruebas unitarias.
* Sustitución de componentes.
* Optimización individual.
* Compilación de metadata.
* Extensión por paquetes.
* Diagnóstico de errores.

---

### 3.2 Contratos antes que implementaciones

Las dependencias internas utilizarán interfaces.

Ejemplo:

```php
interface ControllerResolverInterface
{
    public function resolve(
        ControllerDefinition $definition,
        ControllerContext $context
    ): ResolvedController;
}
```

La implementación predeterminada podrá reemplazarse sin modificar el router, el kernel o el dispatcher.

---

### 3.3 Controladores sin conocimiento del framework interno

Un controlador de aplicación no deberá conocer la arquitectura que lo ejecuta.

```php
final class UserController
{
    public function show(User $user): View
    {
        return view('users.show', [
            'user' => $user,
        ]);
    }
}
```

El controlador no necesita conocer:

* El dispatcher.
* El resolver.
* El pipeline.
* El normalizador.
* El kernel.
* La metadata compilada.

---

### 3.4 Ejecución determinista

La resolución de controladores y parámetros deberá seguir reglas predecibles.

Ante la misma:

* Ruta.
* Petición.
* Definición.
* Metadata.
* Configuración.

El resultado de resolución deberá ser consistente.

No se permitirán reglas ambiguas dependientes del orden accidental de registro.

---

### 3.5 Compilación opcional

El sistema debe funcionar correctamente mediante reflexión durante desarrollo, pero permitir compilar la información necesaria para producción.

```text
Desarrollo
    │
    └── Reflection + descubrimiento dinámico

Producción
    │
    └── Metadata compilada + resoluciones precalculadas
```

---

### 3.6 Neutralidad de transporte

La arquitectura principal no debe estar limitada exclusivamente a HTTP.

Aunque HTTP será el transporte inicial, el mismo núcleo podrá ser utilizado posteriormente desde:

* Consola.
* Colas.
* Scheduler.
* RPC.
* WebSockets.
* Server Actions.
* Procesos internos.
* Testing.

Para ello se utilizará un contexto de ejecución desacoplado.

---

## 4. Límites del módulo

### 4.1 Responsabilidades del sistema

El módulo Controllers será responsable de:

* Interpretar definiciones de controladores.
* Resolver clases desde el contenedor.
* Validar que un controlador sea ejecutable.
* Determinar el método objetivo.
* Resolver argumentos.
* Procesar metadata de controlador y método.
* Aplicar middleware de controlador.
* Ejecutar autorización declarativa.
* Ejecutar validación declarativa.
* Invocar el controlador.
* Capturar y enriquecer errores de ejecución.
* Normalizar el resultado.
* Publicar eventos del ciclo de vida.
* Proporcionar métricas de ejecución.
* Mantener caché de resolución y metadata.

---

### 4.2 Responsabilidades externas

El módulo no será responsable de:

* Encontrar la ruta correspondiente a una URL.
* Ejecutar middleware global del kernel.
* Implementar autenticación.
* Implementar políticas de autorización.
* Renderizar internamente una plantilla Volt.
* Serializar internamente un recurso JSON.
* Gestionar sesiones.
* Persistir modelos.
* Gestionar conexiones de base de datos.
* Emitir directamente la respuesta HTTP.

Estas capacidades serán delegadas a sus respectivos contratos.

---

## 5. Componentes principales

La arquitectura estará formada por los siguientes componentes.

```text
ControllerDefinition
ControllerContext
ControllerReferenceParser
ControllerResolver
ControllerMetadataResolver
ControllerArgumentResolver
ControllerMiddlewareResolver
ControllerDispatcher
ControllerInvoker
ControllerResultNormalizer
ControllerResponseFactory
ControllerLifecycle
ControllerRegistry
ControllerCache
```

---

## 6. ControllerDefinition

`ControllerDefinition` representa una descripción normalizada del controlador que debe ejecutarse.

El router puede aceptar diferentes formas públicas:

```php
[UserController::class, 'show']
```

```php
UserController::class
```

```php
'App\Http\Controllers\UserController@show'
```

```php
fn (Request $request) => response('OK')
```

```php
new ControllerReference(
    class: UserController::class,
    method: 'show'
)
```

Todas deberán transformarse en una única estructura interna.

```php
final readonly class ControllerDefinition
{
    public function __construct(
        public ControllerType $type,
        public mixed $target,
        public ?string $class,
        public ?string $method,
        public array $metadata = [],
    ) {
    }
}
```

### 6.1 Tipos de definición

```php
enum ControllerType: string
{
    case ClassMethod = 'class_method';
    case Invokable = 'invokable';
    case Closure = 'closure';
    case Service = 'service';
    case Action = 'action';
    case Resource = 'resource';
    case Page = 'page';
    case Component = 'component';
}
```

### 6.2 Responsabilidades

`ControllerDefinition` deberá:

* Representar el objetivo sin ejecutarlo.
* Ser serializable para caché de rutas.
* Evitar depender de objetos no serializables en producción.
* Contener metadata mínima de resolución.
* Ser inmutable.

No deberá:

* Resolver instancias.
* Ejecutar métodos.
* Resolver argumentos.
* Acceder al contenedor.

---

## 7. ControllerReferenceParser

El `ControllerReferenceParser` convertirá las distintas sintaxis públicas en un `ControllerDefinition`.

Contrato:

```php
interface ControllerReferenceParserInterface
{
    public function parse(mixed $reference): ControllerDefinition;

    public function supports(mixed $reference): bool;
}
```

Implementaciones iniciales:

```text
ArrayControllerReferenceParser
InvokableControllerReferenceParser
StringControllerReferenceParser
ClosureControllerReferenceParser
ServiceControllerReferenceParser
ActionControllerReferenceParser
```

El sistema podrá utilizar un parser compuesto.

```php
final class CompositeControllerReferenceParser
{
    /**
     * @param iterable<ControllerReferenceParserInterface> $parsers
     */
    public function __construct(
        private iterable $parsers
    ) {
    }
}
```

### 7.1 Regla de prioridad

Cada parser tendrá una prioridad explícita.

```text
Closure
Explicit ControllerReference
Array class-method
Invokable class
Legacy string
Service identifier
```

La sintaxis legacy basada en cadenas podrá mantenerse como compatibilidad, pero no será la opción recomendada.

---

## 8. ControllerContext

`ControllerContext` representa la información disponible durante la resolución y ejecución.

```php
final readonly class ControllerContext
{
    public function __construct(
        public ExecutionContextInterface $execution,
        public RouteMatch $route,
        public RequestInterface $request,
        public ?UserInterface $user = null,
        public ?TenantInterface $tenant = null,
        public array $attributes = [],
    ) {
    }
}
```

El contexto permitirá que los resolvers obtengan información sin depender directamente de servicios globales.

### 8.1 Información posible

* Petición actual.
* Ruta resuelta.
* Parámetros de ruta.
* Usuario autenticado.
* Tenant actual.
* Locale.
* Formato esperado.
* Canal de transporte.
* Correlation ID.
* Trace ID.
* Estado de navegación SPA.
* Metadata del kernel.

### 8.2 Inmutabilidad

El contexto deberá ser inmutable.

Cualquier enriquecimiento generará una nueva instancia o un contexto derivado.

Esto evitará mutaciones laterales difíciles de rastrear.

---

## 9. ControllerResolver

El `ControllerResolver` transforma una definición en un controlador ejecutable.

Contrato:

```php
interface ControllerResolverInterface
{
    public function resolve(
        ControllerDefinition $definition,
        ControllerContext $context
    ): ResolvedController;
}
```

Resultado:

```php
final readonly class ResolvedController
{
    public function __construct(
        public object|Closure $instance,
        public string|Closure $method,
        public ControllerDefinition $definition,
        public ControllerMetadata $metadata,
    ) {
    }
}
```

### 9.1 Responsabilidades

* Validar la definición.
* Resolver la clase mediante el contenedor.
* Detectar controladores invocables.
* Resolver servicios registrados.
* Confirmar que el método existe.
* Confirmar que el método es público.
* Cargar metadata asociada.
* Devolver una representación ejecutable.

### 9.2 Resolución mediante Container

```php
$instance = $container->make($definition->class);
```

Esto permitirá:

* Constructor injection.
* Bindings por interfaz.
* Decoradores.
* Proxies.
* Lazy services.
* Contextual bindings.
* Scoped services.

### 9.3 Controladores request-scoped

Por defecto, cada controlador será resuelto dentro del scope de la petición.

No deberá registrarse como singleton salvo configuración explícita y validada.

```php
#[ControllerScope(ControllerScopeType::Request)]
final class UserController
{
}
```

---

## 10. ControllerMetadataResolver

El `ControllerMetadataResolver` será responsable de leer y combinar metadata procedente de:

* Clase.
* Método.
* Ruta.
* Grupos de rutas.
* Configuración.
* Atributos PHP.
* Interfaces implementadas.
* Convenciones.
* Metadata compilada.

Contrato:

```php
interface ControllerMetadataResolverInterface
{
    public function resolve(
        ControllerDefinition $definition,
        ReflectionClass $class,
        ?ReflectionMethod $method
    ): ControllerMetadata;
}
```

### 10.1 Metadata posible

```php
final readonly class ControllerMetadata
{
    public function __construct(
        public array $middleware = [],
        public array $authorization = [],
        public array $validation = [],
        public array $cache = [],
        public array $rateLimits = [],
        public array $response = [],
        public array $bindings = [],
        public array $tags = [],
        public array $extensions = [],
    ) {
    }
}
```

### 10.2 Precedencia

La precedencia recomendada será:

```text
Configuración global
    ↓
Grupo de rutas
    ↓
Clase del controlador
    ↓
Ruta concreta
    ↓
Método del controlador
```

La metadata más específica podrá:

* Añadir.
* Reemplazar.
* Eliminar.
* Desactivar.

La estrategia dependerá del tipo de metadata.

Ejemplo:

```php
#[Middleware('auth')]
final class UserController
{
    #[WithoutMiddleware('auth')]
    public function publicProfile(): View
    {
    }
}
```

---

## 11. ControllerArgumentResolver

El `ControllerArgumentResolver` construirá la lista ordenada de argumentos que necesita el método.

Contrato:

```php
interface ControllerArgumentResolverInterface
{
    /**
     * @return list<mixed>
     */
    public function resolveArguments(
        ResolvedController $controller,
        ControllerContext $context
    ): array;
}
```

La implementación principal utilizará resolvers especializados.

```php
interface ArgumentValueResolverInterface
{
    public function supports(
        ArgumentMetadata $argument,
        ControllerContext $context
    ): bool;

    public function resolve(
        ArgumentMetadata $argument,
        ControllerContext $context
    ): mixed;

    public function priority(): int;
}
```

### 11.1 Resolvers iniciales

```text
RequestArgumentResolver
RouteParameterArgumentResolver
ContainerServiceArgumentResolver
ModelBindingArgumentResolver
AuthenticatedUserArgumentResolver
TenantArgumentResolver
SessionArgumentResolver
DtoArgumentResolver
EnumArgumentResolver
UploadedFileArgumentResolver
VariadicArgumentResolver
DefaultValueArgumentResolver
NullableArgumentResolver
```

### 11.2 Orden de resolución

El orden general será:

1. Valor explícito del contexto.
2. Binding registrado por la ruta.
3. Tipo especial del framework.
4. Modelo asociado a parámetro de ruta.
5. DTO construido desde la petición.
6. Servicio del contenedor.
7. Enum.
8. Valor predeterminado.
9. Valor nullable.
10. Error de resolución.

### 11.3 Ejemplo

```php
public function update(
    Request $request,
    User $user,
    UpdateUserData $data,
    UserRepository $repository,
    CurrentTenant $tenant
): Response {
}
```

Resolución:

```text
Request
    └── RequestArgumentResolver

User
    └── ModelBindingArgumentResolver

UpdateUserData
    └── DtoArgumentResolver

UserRepository
    └── ContainerServiceArgumentResolver

CurrentTenant
    └── TenantArgumentResolver
```

---

## 12. ArgumentMetadata

Cada parámetro se representará mediante metadata normalizada.

```php
final readonly class ArgumentMetadata
{
    public function __construct(
        public string $name,
        public ?string $type,
        public bool $builtin,
        public bool $nullable,
        public bool $variadic,
        public bool $hasDefaultValue,
        public mixed $defaultValue,
        public array $attributes,
        public int $position,
    ) {
    }
}
```

En producción, esta estructura podrá generarse durante la compilación para evitar reflexión repetida.

---

## 13. ControllerMiddlewareResolver

El middleware asociado al controlador podrá proceder de:

* Ruta.
* Grupo de rutas.
* Clase.
* Método.
* Convenciones.
* Interfaces.
* Configuración del módulo.

Contrato:

```php
interface ControllerMiddlewareResolverInterface
{
    /**
     * @return list<ResolvedMiddleware>
     */
    public function resolve(
        ResolvedController $controller,
        ControllerContext $context
    ): array;
}
```

### 13.1 Tipos de middleware

```text
Before middleware
Around middleware
After middleware
Terminable middleware
```

El sistema deberá reutilizar el pipeline general de middleware del framework.

No se creará un segundo motor de middleware exclusivo para controladores.

### 13.2 Alcance

El middleware de controlador se ejecutará después del middleware de ruta externo y antes de la invocación final.

```text
Global Middleware
    ↓
Route Middleware
    ↓
Controller Middleware
    ↓
Controller Invocation
```

---

## 14. ControllerDispatcher

El `ControllerDispatcher` coordinará la ejecución del sistema.

Contrato:

```php
interface ControllerDispatcherInterface
{
    public function dispatch(
        ControllerDefinition $definition,
        ControllerContext $context
    ): ResponseInterface;
}
```

El dispatcher no deberá contener toda la lógica directamente.

Será un orquestador de componentes.

```php
final class ControllerDispatcher implements ControllerDispatcherInterface
{
    public function __construct(
        private ControllerResolverInterface $resolver,
        private ControllerArgumentResolverInterface $argumentResolver,
        private ControllerMiddlewareResolverInterface $middlewareResolver,
        private ControllerInvokerInterface $invoker,
        private ControllerResultNormalizerInterface $normalizer,
        private ControllerLifecycleInterface $lifecycle,
    ) {
    }
}
```

### 14.1 Flujo interno

```text
ControllerDefinition
    │
    ▼
Lifecycle: resolving
    │
    ▼
ControllerResolver
    │
    ▼
Lifecycle: resolved
    │
    ▼
ArgumentResolver
    │
    ▼
MiddlewareResolver
    │
    ▼
Controller Pipeline
    │
    ▼
ControllerInvoker
    │
    ▼
Raw Controller Result
    │
    ▼
ResultNormalizer
    │
    ▼
ResponseInterface
    │
    ▼
Lifecycle: finished
```

---

## 15. ControllerInvoker

El `ControllerInvoker` será el único componente autorizado para invocar el controlador.

Contrato:

```php
interface ControllerInvokerInterface
{
    public function invoke(
        ResolvedController $controller,
        array $arguments,
        ControllerContext $context
    ): mixed;
}
```

### 15.1 Implementación base

La implementación inicial podrá utilizar:

```php
$result = $controller->instance->{$controller->method}(...$arguments);
```

O:

```php
$result = ($controller->instance)(...$arguments);
```

Para closures:

```php
$result = ($controller->method)(...$arguments);
```

### 15.2 Razón de separación

Separar la invocación permitirá implementar posteriormente:

* Proxies.
* Instrumentación.
* Fibers.
* Coroutines.
* Ejecución asíncrona.
* Timeouts.
* Profiling.
* Aislamiento.
* Sandboxing.
* Ejecución remota.
* Interceptores.

---

## 16. ControllerResultNormalizer

Un controlador podrá devolver diferentes tipos de resultados.

```php
return response('OK');
```

```php
return ['status' => 'ok'];
```

```php
return view('users.index');
```

```php
return redirect('/login');
```

```php
return UserResource::collection($users);
```

```php
return component(UserTable::class);
```

```php
return spa('Users/Index', $props);
```

```php
return 'Hello';
```

El `ControllerResultNormalizer` transformará estos valores en una respuesta reconocida por el kernel.

Contrato:

```php
interface ControllerResultNormalizerInterface
{
    public function normalize(
        mixed $result,
        ControllerContext $context
    ): ResponseInterface;
}
```

### 16.1 Normalizadores especializados

```php
interface ResultNormalizerInterface
{
    public function supports(
        mixed $result,
        ControllerContext $context
    ): bool;

    public function normalize(
        mixed $result,
        ControllerContext $context
    ): ResponseInterface;

    public function priority(): int;
}
```

Implementaciones previstas:

```text
ResponseResultNormalizer
ViewResultNormalizer
VoltResultNormalizer
SpaResultNormalizer
ComponentResultNormalizer
JsonResultNormalizer
ApiResourceResultNormalizer
RedirectResultNormalizer
StreamResultNormalizer
BinaryFileResultNormalizer
IterableResultNormalizer
StringResultNormalizer
NullResultNormalizer
```

### 16.2 Prioridad

Los tipos más explícitos tendrán mayor prioridad.

```text
ResponseInterface
    ↓
Specialized responses
    ↓
View / Volt / SPA / Component
    ↓
API resource
    ↓
Array / object JSON
    ↓
String
    ↓
Null
```

La conversión implícita de arreglos a JSON dependerá del contexto de ruta.

Una ruta web podría interpretar un arreglo como datos de vista, mientras que una ruta API lo interpretará como JSON.

Esta conducta deberá ser explícita y configurable para evitar ambigüedad.

---

## 17. ResponseFactory

El `ResponseFactory` construirá respuestas concretas.

```php
interface ControllerResponseFactoryInterface
{
    public function json(
        mixed $data,
        int $status = 200,
        array $headers = []
    ): ResponseInterface;

    public function view(
        string $view,
        array $data = [],
        int $status = 200
    ): ResponseInterface;

    public function redirect(
        string $location,
        int $status = 302
    ): ResponseInterface;

    public function noContent(
        int $status = 204
    ): ResponseInterface;
}
```

El sistema de controladores dependerá del contrato, no de implementaciones HTTP concretas.

---

## 18. ControllerLifecycle

El ciclo de vida encapsulará eventos, hooks e instrumentación.

Contrato:

```php
interface ControllerLifecycleInterface
{
    public function resolving(
        ControllerDefinition $definition,
        ControllerContext $context
    ): void;

    public function resolved(
        ResolvedController $controller,
        ControllerContext $context
    ): void;

    public function invoking(
        ResolvedController $controller,
        array $arguments,
        ControllerContext $context
    ): void;

    public function invoked(
        ResolvedController $controller,
        mixed $result,
        ControllerContext $context
    ): void;

    public function failed(
        Throwable $exception,
        ControllerContext $context
    ): void;

    public function finished(
        ResponseInterface $response,
        ControllerContext $context
    ): void;
}
```

### 18.1 Eventos previstos

```text
ControllerResolving
ControllerResolved
ControllerArgumentsResolved
ControllerInvoking
ControllerInvoked
ControllerResultNormalizing
ControllerResponseCreated
ControllerFailed
ControllerFinished
```

### 18.2 Uso

Estos eventos permitirán:

* Logging.
* Telemetría.
* Profiling.
* Debug toolbar.
* Auditoría.
* Métricas.
* Extensiones.
* Pruebas.
* Tracing distribuido.

Los listeners no deberán alterar silenciosamente el resultado salvo que un evento esté diseñado expresamente como transformable.

---

## 19. ControllerRegistry

El `ControllerRegistry` mantendrá información conocida sobre controladores.

Contrato:

```php
interface ControllerRegistryInterface
{
    public function register(
        string $identifier,
        ControllerDefinition $definition
    ): void;

    public function has(string $identifier): bool;

    public function get(string $identifier): ControllerDefinition;

    public function all(): iterable;
}
```

### 19.1 Casos de uso

* Service controllers.
* Aliases.
* Controladores generados.
* Controladores de paquetes.
* Actions nombradas.
* Páginas registradas.
* Component controllers.
* Metadata precargada.

Ejemplo:

```php
$registry->register(
    'users.show',
    new ControllerDefinition(
        type: ControllerType::ClassMethod,
        target: [UserController::class, 'show'],
        class: UserController::class,
        method: 'show',
    )
);
```

---

## 20. Arquitectura de pipelines

El sistema utilizará pipelines en dos niveles.

### 20.1 Pipeline HTTP externo

```text
Global Middleware
    ↓
Application Middleware
    ↓
Route Middleware
```

### 20.2 Pipeline del controlador

```text
Controller Metadata
    ↓
Controller Middleware
    ↓
Authorization
    ↓
Validation
    ↓
Invocation
    ↓
Result Transformation
```

Estos pipelines no deben duplicar responsabilidades.

La autorización y validación podrán implementarse como interceptores internos o middleware especializados, pero deberán compartir contratos comunes con los módulos principales.

---

## 21. Interceptores del controlador

Además del middleware tradicional, la arquitectura podrá incorporar interceptores especializados.

```php
interface ControllerInterceptorInterface
{
    public function intercept(
        ControllerInvocation $invocation,
        Closure $next
    ): mixed;
}
```

Ejemplos:

```text
AuthorizationInterceptor
ValidationInterceptor
TransactionInterceptor
CacheInterceptor
IdempotencyInterceptor
RateLimitInterceptor
AuditInterceptor
TracingInterceptor
TimeoutInterceptor
```

### 21.1 Diferencia entre middleware e interceptor

El middleware opera principalmente sobre request y response.

El interceptor opera sobre la invocación concreta del controlador.

```text
Middleware
    └── Request → Response

Interceptor
    └── ControllerInvocation → Result
```

Ejemplo de información disponible para un interceptor:

```php
final readonly class ControllerInvocation
{
    public function __construct(
        public ResolvedController $controller,
        public array $arguments,
        public ControllerContext $context,
    ) {
    }
}
```

---

## 22. Integración con autorización

El sistema no implementará directamente las políticas, pero sí coordinará su ejecución.

Ejemplo declarativo:

```php
#[Authorize('update', subject: 'user')]
public function update(
    User $user,
    UpdateUserData $data
): Response {
}
```

Flujo:

```text
Controller metadata
    ↓
Authorization metadata resolver
    ↓
Arguments resolved
    ↓
Subject extracted
    ↓
Authorization manager
    ↓
Allow / Deny
```

La autorización deberá ejecutarse antes de entrar al cuerpo del método.

---

## 23. Integración con validación

El sistema permitirá varios estilos.

### 23.1 Form Request

```php
public function store(CreateUserRequest $request): Response
{
}
```

### 23.2 DTO validado

```php
public function store(CreateUserData $data): Response
{
}
```

### 23.3 Atributos

```php
public function store(
    #[Required]
    #[Email]
    string $email
): Response {
}
```

### 23.4 Metadata de método

```php
#[Validate(CreateUserRules::class)]
public function store(Request $request): Response
{
}
```

La validación será coordinada por resolvers e interceptores, no directamente por el dispatcher.

---

## 24. Integración con transacciones

Los controladores podrán declarar transacciones.

```php
#[Transactional]
public function transfer(
    TransferData $data,
    TransferService $service
): Response {
}
```

El `TransactionInterceptor` envolverá la invocación.

```text
Begin transaction
    ↓
Invoke controller
    ↓
Commit
```

Ante una excepción:

```text
Exception
    ↓
Rollback
    ↓
Rethrow
```

La lógica transaccional pertenecerá al módulo de persistencia o database, no al módulo Controllers.

---

## 25. Integración con Volt Runtime

Los controladores de páginas podrán devolver respuestas Volt.

```php
final class DashboardController
{
    public function __invoke(
        DashboardService $dashboard
    ): VoltResponse {
        return volt('dashboard', [
            'metrics' => $dashboard->metrics(),
        ]);
    }
}
```

Flujo:

```text
Controller result
    ↓
VoltResultNormalizer
    ↓
Volt render descriptor
    ↓
Volt Runtime
    ↓
SSR, SPA payload o fragment
    ↓
Response
```

El sistema de controladores no renderizará las plantillas directamente.

---

## 26. Integración con SPA Runtime

La respuesta podrá variar según el tipo de navegación.

```text
Petición HTTP normal
    └── Documento HTML completo

Navegación SPA
    └── Volt Protocol payload

Petición de fragmento
    └── Partial response

Prefetch
    └── Datos y metadata precargables
```

El normalizador recibirá esta información desde `ControllerContext`.

```php
if ($context->execution->isSpaNavigation()) {
    return $spaResponseFactory->fromPage($result);
}
```

El controlador no necesitará contener condicionales específicos del transporte.

---

## 27. Integración con componentes

Un controlador podrá devolver un descriptor de componente.

```php
public function table(): ComponentResponse
{
    return component(UserTable::class, [
        'sortable' => true,
    ]);
}
```

El `ComponentResultNormalizer` determinará:

* Registro del componente.
* Props.
* Estado inicial.
* Estrategia de hidratación.
* Runtime frontend.
* Formato de respuesta.

---

## 28. Integración con APIs

Las rutas de API proporcionarán metadata de formato.

```php
Route::api('/users/{user}', [UserApiController::class, 'show']);
```

El contexto incluirá:

```php
[
    'response_format' => 'json',
    'api_version' => 'v1',
    'serialization_group' => 'user.detail',
]
```

El normalizador podrá convertir automáticamente:

* Modelos.
* DTOs.
* Resources.
* Colecciones.
* Paginadores.
* Errores.

El sistema deberá evitar serializar accidentalmente propiedades sensibles.

---

## 29. Manejo de excepciones

Las excepciones se propagarán hacia el sistema central de manejo de errores, después de enriquecer su contexto.

```php
try {
    $result = $invoker->invoke(
        $controller,
        $arguments,
        $context
    );
} catch (Throwable $exception) {
    $lifecycle->failed($exception, $context);

    throw ControllerExecutionException::from(
        exception: $exception,
        controller: $controller,
        context: $context,
    );
}
```

### 29.1 Excepciones del módulo

```text
InvalidControllerDefinitionException
ControllerNotFoundException
ControllerMethodNotFoundException
ControllerMethodNotPublicException
ControllerResolutionException
ArgumentResolutionException
AmbiguousArgumentResolverException
UnsupportedControllerResultException
ControllerExecutionException
ControllerMetadataException
```

### 29.2 Contexto de error

Las excepciones deberán proporcionar:

* Clase del controlador.
* Método.
* Ruta.
* Parámetro problemático.
* Resolver utilizado.
* Tipo esperado.
* Tipo recibido.
* Correlation ID.
* Fase del ciclo de vida.

No deberán exponer información sensible en producción.

---

## 30. Caché y compilación

La arquitectura admitirá varios niveles de caché.

### 30.1 Controller definition cache

Almacena la definición normalizada asociada a una ruta.

### 30.2 Metadata cache

Almacena atributos y configuración combinada.

### 30.3 Argument metadata cache

Almacena información de parámetros.

### 30.4 Resolver plan cache

Almacena qué resolver corresponde a cada argumento.

### 30.5 Invocation plan cache

Almacena el plan completo de ejecución.

Ejemplo conceptual:

```php
final readonly class CompiledControllerPlan
{
    public function __construct(
        public string $controllerClass,
        public string $method,
        public array $argumentResolvers,
        public array $middleware,
        public array $interceptors,
        public string $resultStrategy,
    ) {
    }
}
```

### 30.6 Flujo compilado

```text
Route Match
    ↓
Compiled Controller Plan
    ↓
Container resolution
    ↓
Preselected argument resolvers
    ↓
Invocation
    ↓
Preselected normalization strategy
```

La compilación no deberá cambiar el comportamiento observable respecto al modo dinámico.

---

## 31. Estructura de directorios

```text
src/
└── Quantum/
    └── Controllers/
        ├── Controller.php
        ├── ControllerManager.php
        ├── ControllerDispatcher.php
        ├── ControllerResolver.php
        ├── ControllerInvoker.php
        ├── ControllerResultNormalizer.php
        ├── ControllerResponseFactory.php
        ├── ControllerRegistry.php
        │
        ├── Contracts/
        │   ├── ControllerInterface.php
        │   ├── ControllerDispatcherInterface.php
        │   ├── ControllerResolverInterface.php
        │   ├── ControllerInvokerInterface.php
        │   ├── ControllerReferenceParserInterface.php
        │   ├── ControllerMetadataResolverInterface.php
        │   ├── ControllerArgumentResolverInterface.php
        │   ├── ArgumentValueResolverInterface.php
        │   ├── ControllerMiddlewareResolverInterface.php
        │   ├── ControllerResultNormalizerInterface.php
        │   ├── ResultNormalizerInterface.php
        │   ├── ControllerLifecycleInterface.php
        │   ├── ControllerRegistryInterface.php
        │   └── ControllerResponseFactoryInterface.php
        │
        ├── Definitions/
        │   ├── ControllerDefinition.php
        │   ├── ControllerReference.php
        │   ├── ControllerType.php
        │   ├── ResolvedController.php
        │   ├── ControllerInvocation.php
        │   └── CompiledControllerPlan.php
        │
        ├── Context/
        │   ├── ControllerContext.php
        │   ├── ExecutionContext.php
        │   └── ControllerContextFactory.php
        │
        ├── Metadata/
        │   ├── ControllerMetadata.php
        │   ├── ArgumentMetadata.php
        │   ├── ControllerMetadataResolver.php
        │   ├── MetadataMerger.php
        │   ├── MetadataCache.php
        │   └── CompiledMetadataLoader.php
        │
        ├── Parsing/
        │   ├── CompositeControllerReferenceParser.php
        │   ├── ArrayControllerReferenceParser.php
        │   ├── InvokableControllerReferenceParser.php
        │   ├── ClosureControllerReferenceParser.php
        │   ├── StringControllerReferenceParser.php
        │   └── ServiceControllerReferenceParser.php
        │
        ├── Arguments/
        │   ├── ControllerArgumentResolver.php
        │   ├── ArgumentResolverRegistry.php
        │   ├── RequestArgumentResolver.php
        │   ├── RouteParameterArgumentResolver.php
        │   ├── ContainerServiceArgumentResolver.php
        │   ├── ModelBindingArgumentResolver.php
        │   ├── AuthenticatedUserArgumentResolver.php
        │   ├── TenantArgumentResolver.php
        │   ├── DtoArgumentResolver.php
        │   ├── EnumArgumentResolver.php
        │   ├── UploadedFileArgumentResolver.php
        │   ├── DefaultValueArgumentResolver.php
        │   └── NullableArgumentResolver.php
        │
        ├── Middleware/
        │   ├── ControllerMiddlewareResolver.php
        │   ├── ResolvedMiddleware.php
        │   └── ControllerMiddlewarePipeline.php
        │
        ├── Interceptors/
        │   ├── ControllerInterceptorRegistry.php
        │   ├── AuthorizationInterceptor.php
        │   ├── ValidationInterceptor.php
        │   ├── TransactionInterceptor.php
        │   ├── CacheInterceptor.php
        │   ├── AuditInterceptor.php
        │   └── TracingInterceptor.php
        │
        ├── Normalizers/
        │   ├── CompositeControllerResultNormalizer.php
        │   ├── ResponseResultNormalizer.php
        │   ├── ViewResultNormalizer.php
        │   ├── VoltResultNormalizer.php
        │   ├── SpaResultNormalizer.php
        │   ├── ComponentResultNormalizer.php
        │   ├── JsonResultNormalizer.php
        │   ├── ApiResourceResultNormalizer.php
        │   ├── RedirectResultNormalizer.php
        │   ├── StreamResultNormalizer.php
        │   ├── StringResultNormalizer.php
        │   └── NullResultNormalizer.php
        │
        ├── Attributes/
        │   ├── Controller.php
        │   ├── Middleware.php
        │   ├── WithoutMiddleware.php
        │   ├── Authorize.php
        │   ├── Validate.php
        │   ├── Transactional.php
        │   ├── Cache.php
        │   ├── Throttle.php
        │   ├── Api.php
        │   ├── Page.php
        │   ├── Component.php
        │   └── Action.php
        │
        ├── Events/
        │   ├── ControllerResolving.php
        │   ├── ControllerResolved.php
        │   ├── ControllerArgumentsResolved.php
        │   ├── ControllerInvoking.php
        │   ├── ControllerInvoked.php
        │   ├── ControllerFailed.php
        │   └── ControllerFinished.php
        │
        ├── Exceptions/
        │   ├── ControllerException.php
        │   ├── InvalidControllerDefinitionException.php
        │   ├── ControllerNotFoundException.php
        │   ├── ControllerMethodNotFoundException.php
        │   ├── ControllerResolutionException.php
        │   ├── ArgumentResolutionException.php
        │   ├── AmbiguousArgumentResolverException.php
        │   ├── UnsupportedControllerResultException.php
        │   └── ControllerExecutionException.php
        │
        ├── Compiler/
        │   ├── ControllerCompiler.php
        │   ├── ControllerPlanCompiler.php
        │   ├── ControllerMetadataCompiler.php
        │   ├── ControllerCacheWriter.php
        │   └── CompiledControllerLoader.php
        │
        ├── Support/
        │   ├── ControllerName.php
        │   ├── MethodSignature.php
        │   ├── ControllerInspector.php
        │   └── ControllerDebugInfo.php
        │
        └── Testing/
            ├── FakeControllerResolver.php
            ├── FakeControllerDispatcher.php
            ├── ControllerTestResponse.php
            └── InteractsWithControllers.php
```

---

## 32. Dependencias del módulo

### 32.1 Dependencias obligatorias

```text
Container
Http
Routing
Middleware
Events
Contracts
Support
```

### 32.2 Dependencias opcionales

```text
Authentication
Authorization
Validation
Database
Views
Volt
SPA Runtime
Components
Cache
Telemetry
Tenancy
```

Las dependencias opcionales deberán detectarse mediante contratos o capacidades registradas.

El núcleo no deberá fallar porque un módulo opcional no se encuentre instalado, salvo que un controlador solicite explícitamente una capacidad inexistente.

---

## 33. Registro en el contenedor

El módulo registrará sus implementaciones predeterminadas.

```php
$container->singleton(
    ControllerReferenceParserInterface::class,
    CompositeControllerReferenceParser::class
);

$container->singleton(
    ControllerMetadataResolverInterface::class,
    ControllerMetadataResolver::class
);

$container->singleton(
    ControllerArgumentResolverInterface::class,
    ControllerArgumentResolver::class
);

$container->singleton(
    ControllerResolverInterface::class,
    ControllerResolver::class
);

$container->singleton(
    ControllerInvokerInterface::class,
    ControllerInvoker::class
);

$container->singleton(
    ControllerResultNormalizerInterface::class,
    CompositeControllerResultNormalizer::class
);

$container->singleton(
    ControllerDispatcherInterface::class,
    ControllerDispatcher::class
);
```

Los controladores de aplicación serán request-scoped o transient.

---

## 34. Bootstrapping

El módulo seguirá dos fases.

### 34.1 Register

Durante `register`:

* Registrar contratos.
* Registrar parsers.
* Registrar argument resolvers.
* Registrar normalizadores.
* Registrar interceptores.
* Registrar compiladores.
* Registrar configuración.

### 34.2 Boot

Durante `boot`:

* Cargar metadata compilada.
* Registrar extensiones de paquetes.
* Validar prioridades.
* Preparar caché.
* Conectar eventos.
* Integrar el dispatcher con Routing y HttpKernel.

---

## 35. Flujo completo de ejecución

```text
1. HttpKernel recibe Request
2. Router encuentra RouteMatch
3. RouteMatch contiene referencia de controlador
4. ControllerReferenceParser crea ControllerDefinition
5. ControllerContextFactory crea ControllerContext
6. ControllerDispatcher inicia ejecución
7. ControllerLifecycle publica ControllerResolving
8. ControllerResolver crea ResolvedController
9. ControllerMetadataResolver obtiene metadata
10. ControllerLifecycle publica ControllerResolved
11. ControllerArgumentResolver resuelve argumentos
12. ControllerLifecycle publica ControllerArgumentsResolved
13. ControllerMiddlewareResolver crea pipeline
14. Interceptores preparan autorización y validación
15. ControllerLifecycle publica ControllerInvoking
16. ControllerInvoker ejecuta el método
17. ControllerLifecycle publica ControllerInvoked
18. ControllerResultNormalizer transforma resultado
19. ResponseFactory genera ResponseInterface
20. ControllerLifecycle publica ControllerFinished
21. Response regresa al pipeline HTTP
22. HttpKernel emite la respuesta
```

---

## 36. Ejemplo de ejecución

Ruta:

```php
Route::put('/users/{user}', [
    UserController::class,
    'update',
]);
```

Controlador:

```php
final class UserController
{
    #[Authorize('update', subject: 'user')]
    #[Transactional]
    public function update(
        User $user,
        UpdateUserData $data,
        UserRepository $users
    ): RedirectResponse {
        $users->update($user, $data);

        return redirect()
            ->route('users.show', $user)
            ->with('status', 'Usuario actualizado.');
    }
}
```

Resolución:

```text
Route parameter "user"
    ↓
ModelBindingArgumentResolver
    ↓
User instance

Request payload
    ↓
DtoArgumentResolver
    ↓
UpdateUserData

Container
    ↓
ContainerServiceArgumentResolver
    ↓
UserRepository
```

Interceptores:

```text
AuthorizationInterceptor
    ↓
TransactionInterceptor
    ↓
Controller invocation
```

Resultado:

```text
RedirectResponse
    ↓
ResponseResultNormalizer
    ↓
HTTP 302 Response
```

---

## 37. Reglas de extensibilidad

Los paquetes podrán registrar:

* Nuevos parsers de referencias.
* Nuevos resolvers de argumentos.
* Nuevos normalizadores de resultados.
* Nuevos interceptores.
* Nuevos atributos.
* Nuevos tipos de controlador.
* Nuevos compiladores de metadata.
* Nuevas estrategias de respuesta.

Ejemplo:

```php
$controllers->arguments()->extend(
    resolver: GraphQlContextArgumentResolver::class,
    priority: 200
);
```

```php
$controllers->results()->extend(
    normalizer: CsvResultNormalizer::class,
    priority: 150
);
```

```php
$controllers->interceptors()->extend(
    interceptor: FeatureFlagInterceptor::class,
    priority: 300
);
```

Las extensiones deberán declarar prioridad y soporte de manera explícita.

---

## 38. Reglas de seguridad

La arquitectura deberá garantizar:

* Solo métodos públicos ejecutables.
* Prohibición de métodos mágicos no autorizados.
* Validación de referencias de controlador.
* Bloqueo de controladores fuera de namespaces permitidos cuando se configure.
* Protección contra resolución arbitraria de servicios.
* Protección contra mass assignment en DTOs.
* Control de serialización de resultados.
* Aislamiento de tenant.
* Validación de atributos compilados.
* No exposición de trazas en producción.
* Prevención de deserialización insegura de closures.
* Exclusión de closures en caché de producción.

---

## 39. Reglas de rendimiento

El sistema deberá:

* Evitar reflexión repetida.
* Reutilizar metadata inmutable.
* Preseleccionar resolvers cuando sea posible.
* Evitar iterar todos los normalizadores en producción.
* Utilizar arrays compactos en caché compilada.
* Permitir precarga mediante OPcache.
* Mantener controladores sin estado.
* Evitar creación innecesaria de contextos.
* Resolver servicios de forma lazy.
* No realizar escaneo de directorios por petición.
* Invalidar caché únicamente cuando cambien archivos relevantes.

---

## 40. Observabilidad

Cada ejecución deberá poder generar métricas como:

```text
controller.resolve.duration
controller.arguments.duration
controller.middleware.duration
controller.invoke.duration
controller.normalize.duration
controller.total.duration
controller.memory.delta
controller.exceptions.total
controller.cache.hit
controller.cache.miss
```

Atributos de tracing:

```text
controller.class
controller.method
controller.type
route.name
route.path
request.method
response.status
tenant.id
user.id
transport.type
spa.navigation
```

Los valores sensibles deberán anonimizarse o excluirse.

---

## 41. Estrategia de pruebas

### 41.1 Pruebas unitarias

* Parsing de referencias.
* Resolución de controladores.
* Resolución de argumentos.
* Combinación de metadata.
* Prioridad de normalizadores.
* Prioridad de interceptores.
* Excepciones.

### 41.2 Pruebas de integración

* Router → Dispatcher.
* Container → Controller.
* Controller → Response.
* Authorization → Invocation.
* Validation → DTO.
* Volt → SPA response.
* Model binding.
* Middleware.

### 41.3 Pruebas de contrato

Cada implementación sustituible deberá ejecutar una suite común.

```php
abstract class ControllerResolverContractTest extends TestCase
{
    abstract protected function resolver(): ControllerResolverInterface;
}
```

### 41.4 Pruebas de equivalencia

El modo dinámico y el modo compilado deberán producir resultados equivalentes.

---

## 42. Fases de implementación

### Fase 1: núcleo mínimo

* `ControllerDefinition`.
* Parsers básicos.
* `ControllerResolver`.
* `ControllerInvoker`.
* Resolución básica de argumentos.
* `ControllerDispatcher`.
* Normalización de `ResponseInterface`.

### Fase 2: integración HTTP

* Request resolver.
* Route parameter resolver.
* Container service resolver.
* Middleware de controlador.
* Manejo de excepciones.
* Lifecycle events.

### Fase 3: resolución avanzada

* Model binding.
* DTOs.
* Enums.
* Usuario autenticado.
* Tenant.
* Uploaded files.
* Argument metadata cache.

### Fase 4: resultados especializados

* Views.
* Volt.
* SPA.
* Components.
* API Resources.
* Streams.
* Downloads.

### Fase 5: metadata e interceptores

* Atributos.
* Autorización.
* Validación.
* Transacciones.
* Cache.
* Rate limiting.
* Auditoría.

### Fase 6: compilación

* Metadata compiler.
* Argument resolver plans.
* Invocation plans.
* Route-controller cache.
* Preloading.
* Optimización para FrankenPHP.

---

## 43. Decisiones arquitectónicas iniciales

### ADR-CTRL-001

**Decisión:** El router almacenará referencias de controlador, no instancias.

**Razón:** Permite caché, resolución scoped y constructor injection.

---

### ADR-CTRL-002

**Decisión:** El dispatcher será un orquestador sin lógica de negocio específica.

**Razón:** Evita una clase central monolítica y facilita reemplazos.

---

### ADR-CTRL-003

**Decisión:** La resolución de argumentos se realizará mediante resolvers especializados.

**Razón:** Facilita extensibilidad y elimina grandes bloques condicionales.

---

### ADR-CTRL-004

**Decisión:** Los resultados se normalizarán después de ejecutar el controlador.

**Razón:** Mantiene controladores simples y desacopla los tipos de respuesta.

---

### ADR-CTRL-005

**Decisión:** Los controladores serán request-scoped por defecto.

**Razón:** Evita contaminación de estado, especialmente bajo FrankenPHP.

---

### ADR-CTRL-006

**Decisión:** Los interceptores operarán sobre la invocación y el middleware sobre request/response.

**Razón:** Separa preocupaciones HTTP de comportamientos propios del método.

---

### ADR-CTRL-007

**Decisión:** El modo compilado deberá ser funcionalmente equivalente al dinámico.

**Razón:** Las optimizaciones no deben modificar la semántica de la aplicación.

---

### ADR-CTRL-008

**Decisión:** Volt, SPA y Components serán integraciones mediante normalizadores.

**Razón:** Evita acoplar el núcleo de controladores al frontend del framework.

---

## 44. Criterios de aceptación arquitectónicos

La arquitectura se considerará correctamente implementada cuando:

* Un controlador pueda resolverse desde una clase y método.
* Un controlador invocable funcione sin configuración adicional.
* Las dependencias de constructor se resuelvan desde el contenedor.
* Los parámetros del método se resuelvan mediante resolvers independientes.
* Los parámetros de ruta puedan transformarse en modelos.
* Un controlador pueda devolver diferentes tipos de resultado.
* Todo resultado válido se transforme en `ResponseInterface`.
* Los controladores puedan tener middleware propio.
* La metadata pueda declararse mediante atributos.
* Los componentes principales puedan sustituirse por contratos.
* El modo compilado evite reflexión repetida.
* El sistema funcione correctamente en procesos persistentes.
* Las extensiones puedan registrarse sin modificar el núcleo.
* Los errores indiquen claramente la fase y el controlador afectados.
* Las pruebas cubran los modos dinámico y compilado.

---

## 45. Arquitectura resumida

```text
                    ┌─────────────────────┐
                    │    HttpKernel       │
                    └──────────┬──────────┘
                               │
                    ┌──────────▼──────────┐
                    │     RouteMatch      │
                    └──────────┬──────────┘
                               │
                    ┌──────────▼──────────┐
                    │ Reference Parser    │
                    └──────────┬──────────┘
                               │
                    ┌──────────▼──────────┐
                    │ControllerDefinition │
                    └──────────┬──────────┘
                               │
                    ┌──────────▼──────────┐
                    │ControllerDispatcher │
                    └──────────┬──────────┘
                               │
              ┌────────────────┼────────────────┐
              │                │                │
     ┌────────▼────────┐ ┌─────▼──────┐ ┌──────▼────────┐
     │ControllerResolver│ │  Metadata  │ │ArgumentResolver│
     └────────┬────────┘ └─────┬──────┘ └──────┬────────┘
              │                │                │
              └────────────────┼────────────────┘
                               │
                    ┌──────────▼──────────┐
                    │ Middleware and      │
                    │ Interceptor Pipeline│
                    └──────────┬──────────┘
                               │
                    ┌──────────▼──────────┐
                    │ ControllerInvoker   │
                    └──────────┬──────────┘
                               │
                    ┌──────────▼──────────┐
                    │    Raw Result       │
                    └──────────┬──────────┘
                               │
                    ┌──────────▼──────────┐
                    │ Result Normalizer   │
                    └──────────┬──────────┘
                               │
                    ┌──────────▼──────────┐
                    │ ResponseInterface   │
                    └─────────────────────┘
```

---

## 46. Conclusión

La arquitectura del sistema de controladores de VoltStack se basa en una cadena de componentes pequeños, especializados y sustituibles.

El router identifica qué debe ejecutarse, pero no conoce cómo construirlo.

El resolver obtiene la instancia, pero no la invoca.

El argument resolver prepara los valores, pero no implementa autorización ni validación.

Los interceptores rodean la ejecución sin contaminar el controlador.

El invoker ejecuta el método sin interpretar su resultado.

El normalizador convierte el resultado en una respuesta adecuada para HTTP, Volt, SPA, componentes o APIs.

Esta separación permite conservar una experiencia de desarrollo simple, mientras el núcleo mantiene una arquitectura robusta, compilable y preparada para aplicaciones tradicionales, runtimes persistentes con FrankenPHP y experiencias SPA reactivas nativas.
