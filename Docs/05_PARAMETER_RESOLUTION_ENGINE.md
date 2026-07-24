# Motor de resolución de parámetros de controladores de VoltStack

**Versión:** 1.0
**Estado:** Draft
**Módulo:** `VoltStack\Quantum\Controllers`
**Documento anterior:** `04_CONTROLLER_RESOLVER.md`

---

## 1. Propósito

Este documento define la arquitectura del motor de resolución de parámetros utilizado por los controladores de VoltStack.

El `Parameter Resolution Engine` será responsable de transformar la firma de un método de controlador en una colección ordenada de valores listos para ser entregados al `ControllerInvoker`.

Ejemplo:

```php
public function update(
    Request $request,
    User $user,
    UpdateUserData $data,
    UserRepository $repository,
    #[CurrentUser] AuthenticatableInterface $currentUser,
    #[RouteParameter('organization')] Organization $organization
): ResponseInterface {
}
```

El motor deberá determinar automáticamente:

* Qué valor corresponde a cada parámetro.
* De qué fuente proviene.
* Qué resolver debe utilizarse.
* Si requiere conversión de tipo.
* Si requiere model binding.
* Si requiere validación.
* Si puede utilizar un valor por defecto.
* Si puede ser `null`.
* Si representa un servicio del Container.
* Si debe resolverse desde el contexto de ejecución.
* Si existe un plan compilado para evitar Reflection.

El motor no ejecutará el controlador.

Su salida será un `ResolvedParameterBag`, consumido posteriormente por el `ControllerInvoker`.

---

## 2. Posición dentro del flujo

```text
ControllerDefinition
    ↓
ControllerResolver
    ↓
ResolvedController
    ↓
ControllerMetadataResolver
    ↓
ParameterResolutionEngine
    ↓
ResolvedParameterBag
    ↓
Authorization / Validation
    ↓
ControllerInvoker
```

Dentro del dispatcher será utilizado por:

```text
ResolveArgumentsStage
```

Ejemplo:

```php
$execution->parameters = $parameterEngine->resolve(
    controller: $execution->controller,
    execution: $execution
);

$execution->arguments = $execution
    ->parameters
    ->orderedValues();
```

---

## 3. Objetivos

El motor deberá:

* Resolver todos los parámetros de un método.
* Mantener el orden exacto de la firma.
* Utilizar resolvers especializados.
* Evitar estructuras condicionales monolíticas.
* Resolver parámetros por tipo.
* Resolver parámetros por nombre.
* Resolver parámetros por atributos.
* Resolver parámetros desde la ruta.
* Resolver servicios desde el Container.
* Resolver modelos y entidades.
* Resolver DTOs.
* Resolver enums.
* Resolver archivos.
* Resolver usuario y tenant.
* Resolver valores HTTP.
* Respetar `nullable` y valores por defecto.
* Detectar resoluciones ambiguas.
* Producir errores descriptivos.
* Permitir resolvers de paquetes.
* Compilar planes de resolución.
* Evitar Reflection repetida.
* Ser seguro en procesos persistentes.
* Facilitar autorización, validación y debugging.

---

## 4. Principios arquitectónicos

El sistema seguirá los siguientes principios:

### 4.1 Un resolver por responsabilidad

Cada fuente o estrategia de resolución tendrá su propio resolver.

### 4.2 Resolución explícita antes que inferencia

Los atributos declarativos tendrán prioridad sobre las convenciones implícitas.

### 4.3 Tipado antes que nombre

Cuando no exista un atributo explícito, el tipo declarado tendrá prioridad sobre el nombre del parámetro.

### 4.4 No ocultar ambigüedades

Cuando dos resolvers puedan resolver el mismo parámetro con la misma prioridad, el sistema deberá fallar en lugar de seleccionar uno arbitrariamente.

### 4.5 La compilación no cambia la semántica

El modo compilado deberá producir exactamente los mismos valores que el modo dinámico.

### 4.6 El Container resuelve servicios, no datos HTTP

Los parámetros que representan servicios se delegarán al Container. Los datos provenientes de la petición se resolverán mediante resolvers HTTP especializados.

---

## 5. Arquitectura general

```text
┌─────────────────────────────────┐
│ ParameterResolutionEngine       │
└────────────────┬────────────────┘
                 │
                 ▼
┌─────────────────────────────────┐
│ ParameterDefinitionFactory      │
└────────────────┬────────────────┘
                 │
                 ▼
┌─────────────────────────────────┐
│ ParameterResolverRegistry       │
└────────────────┬────────────────┘
                 │
                 ▼
┌─────────────────────────────────┐
│ ParameterResolverPipeline       │
│                                 │
│ RouteParameterResolver          │
│ RequestResolver                 │
│ CurrentUserResolver             │
│ CurrentTenantResolver           │
│ DTOResolver                     │
│ ModelResolver                   │
│ EnumResolver                    │
│ UploadedFileResolver            │
│ ServiceResolver                 │
│ DefaultValueResolver            │
└────────────────┬────────────────┘
                 │
                 ▼
┌─────────────────────────────────┐
│ ResolvedParameterBag            │
└────────────────┬────────────────┘
                 │
                 ▼
┌─────────────────────────────────┐
│ ControllerInvoker               │
└─────────────────────────────────┘
```

---

## 6. Contrato principal

```php
namespace VoltStack\Quantum\Controllers\Contracts;

use VoltStack\Quantum\Controllers\Definitions\ResolvedController;
use VoltStack\Quantum\Controllers\Execution\ControllerExecution;
use VoltStack\Quantum\Controllers\Parameters\ResolvedParameterBag;

interface ParameterResolutionEngineInterface
{
    public function resolve(
        ResolvedController $controller,
        ControllerExecution $execution
    ): ResolvedParameterBag;
}
```

La implementación principal será:

```php
final class ParameterResolutionEngine implements
    ParameterResolutionEngineInterface
{
    public function __construct(
        private readonly ParameterDefinitionFactoryInterface $definitions,
        private readonly ParameterResolverPipelineInterface $pipeline,
        private readonly ParameterResolutionPlanLoaderInterface $plans,
    ) {
    }

    public function resolve(
        ResolvedController $controller,
        ControllerExecution $execution
    ): ResolvedParameterBag {
        $plan = $this->plans->find($controller);

        if ($plan !== null) {
            return $this->resolveCompiled(
                $plan,
                $controller,
                $execution
            );
        }

        return $this->resolveDynamic(
            $controller,
            $execution
        );
    }
}
```

---

## 7. ParameterDefinition

`ParameterDefinition` representará un parámetro de método de forma independiente de Reflection durante el resto de la ejecución.

```php
namespace VoltStack\Quantum\Controllers\Parameters;

final readonly class ParameterDefinition
{
    public function __construct(
        public string $name,
        public int $position,
        public ?string $declaringClass,
        public ?string $declaringMethod,
        public ParameterTypeDefinition $type,
        public bool $nullable,
        public bool $variadic,
        public bool $hasDefaultValue,
        public mixed $defaultValue,
        public bool $passedByReference,
        public array $attributes,
        public ?ReflectionParameter $reflection = null,
        public array $metadata = [],
    ) {
    }
}
```

En producción compilada, `reflection` podrá ser `null`.

---

## 8. ParameterTypeDefinition

```php
final readonly class ParameterTypeDefinition
{
    public function __construct(
        public ParameterTypeKind $kind,
        public array $types,
        public ?string $primaryType = null,
        public bool $builtin = false,
        public bool $union = false,
        public bool $intersection = false,
    ) {
    }
}
```

Enum:

```php
enum ParameterTypeKind: string
{
    case None = 'none';
    case Named = 'named';
    case Union = 'union';
    case Intersection = 'intersection';
    case Mixed = 'mixed';
}
```

Ejemplos:

```php
string
```

```php
User
```

```php
User|null
```

```php
User|Guest
```

```php
Countable&Iterator
```

---

## 9. ParameterDefinitionFactory

```php
interface ParameterDefinitionFactoryInterface
{
    /**
     * @return list<ParameterDefinition>
     */
    public function createForController(
        ResolvedController $controller
    ): array;
}
```

En modo dinámico utilizará `ReflectionParameter`.

En modo compilado podrá cargar definiciones serializadas.

---

## 10. ResolvedParameter

Cada parámetro resuelto conservará información sobre su origen.

```php
final readonly class ResolvedParameter
{
    public function __construct(
        public ParameterDefinition $definition,
        public mixed $value,
        public ParameterSource $source,
        public string $resolver,
        public bool $defaultUsed = false,
        public bool $nullableResolution = false,
        public bool $coerced = false,
        public array $metadata = [],
    ) {
    }
}
```

---

## 11. ParameterSource

```php
enum ParameterSource: string
{
    case Route = 'route';
    case Query = 'query';
    case Body = 'body';
    case Json = 'json';
    case Header = 'header';
    case Cookie = 'cookie';
    case File = 'file';
    case Request = 'request';
    case Session = 'session';
    case Container = 'container';
    case Model = 'model';
    case Dto = 'dto';
    case Enum = 'enum';
    case User = 'user';
    case Tenant = 'tenant';
    case Context = 'context';
    case Metadata = 'metadata';
    case Locale = 'locale';
    case DateTime = 'datetime';
    case Pagination = 'pagination';
    case DefaultValue = 'default';
    case Null = 'null';
    case Custom = 'custom';
}
```

---

## 12. ResolvedParameterBag

```php
final class ResolvedParameterBag implements
    IteratorAggregate,
    Countable
{
    /**
     * @param list<ResolvedParameter> $parameters
     */
    public function __construct(
        private array $parameters = []
    ) {
    }

    public function add(ResolvedParameter $parameter): void
    {
        $this->parameters[$parameter->definition->position] =
            $parameter;
    }

    public function get(string $name): ?ResolvedParameter
    {
        foreach ($this->parameters as $parameter) {
            if ($parameter->definition->name === $name) {
                return $parameter;
            }
        }

        return null;
    }

    public function value(string $name): mixed
    {
        return $this->get($name)?->value;
    }

    public function orderedValues(): array
    {
        ksort($this->parameters);

        return array_map(
            static fn (ResolvedParameter $parameter): mixed =>
                $parameter->value,
            $this->parameters
        );
    }

    public function all(): array
    {
        ksort($this->parameters);

        return array_values($this->parameters);
    }

    public function count(): int
    {
        return count($this->parameters);
    }

    public function getIterator(): Traversable
    {
        yield from $this->all();
    }
}
```

---

## 13. Contrato de resolver de valores

```php
interface ParameterValueResolverInterface
{
    public function supports(
        ParameterDefinition $parameter,
        ControllerExecution $execution
    ): bool;

    public function resolve(
        ParameterDefinition $parameter,
        ControllerExecution $execution
    ): ParameterResolutionResult;

    public function priority(): int;
}
```

Los resolvers deberán ser stateless siempre que sea posible.

---

## 14. ParameterResolutionResult

Un resolver podrá:

* Resolver un valor.
* Indicar que no pudo resolver.
* Producir varios valores para parámetros variádicos.
* Indicar que la resolución debe detenerse.

```php
final readonly class ParameterResolutionResult
{
    private function __construct(
        public ParameterResolutionStatus $status,
        public mixed $value = null,
        public array $values = [],
        public ?ParameterSource $source = null,
        public bool $coerced = false,
        public array $metadata = [],
    ) {
    }

    public static function resolved(
        mixed $value,
        ParameterSource $source,
        bool $coerced = false,
        array $metadata = []
    ): self {
        return new self(
            status: ParameterResolutionStatus::Resolved,
            value: $value,
            source: $source,
            coerced: $coerced,
            metadata: $metadata,
        );
    }

    public static function variadic(
        array $values,
        ParameterSource $source
    ): self {
        return new self(
            status: ParameterResolutionStatus::Variadic,
            values: $values,
            source: $source,
        );
    }

    public static function unresolved(): self
    {
        return new self(
            status: ParameterResolutionStatus::Unresolved
        );
    }
}
```

Enum:

```php
enum ParameterResolutionStatus: string
{
    case Resolved = 'resolved';
    case Variadic = 'variadic';
    case Unresolved = 'unresolved';
}
```

---

## 15. ParameterResolverPipeline

```php
interface ParameterResolverPipelineInterface
{
    public function resolve(
        ParameterDefinition $parameter,
        ControllerExecution $execution
    ): ResolvedParameter;
}
```

Implementación:

```php
final class ParameterResolverPipeline implements
    ParameterResolverPipelineInterface
{
    /**
     * @param iterable<ParameterValueResolverInterface> $resolvers
     */
    public function __construct(
        private readonly iterable $resolvers,
        private readonly ParameterResolutionValidatorInterface $validator,
    ) {
    }

    public function resolve(
        ParameterDefinition $parameter,
        ControllerExecution $execution
    ): ResolvedParameter {
        foreach ($this->resolvers as $resolver) {
            if (! $resolver->supports($parameter, $execution)) {
                continue;
            }

            $result = $resolver->resolve(
                $parameter,
                $execution
            );

            if (
                $result->status ===
                ParameterResolutionStatus::Unresolved
            ) {
                continue;
            }

            return $this->validator->toResolvedParameter(
                $parameter,
                $resolver,
                $result
            );
        }

        throw UnresolvableControllerParameterException::forParameter(
            $parameter,
            $execution
        );
    }
}
```

---

## 16. Registro de resolvers

```php
interface ParameterResolverRegistryInterface
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

    public function freeze(): void;
}
```

Los paquetes podrán registrar nuevos resolvers sin modificar el núcleo.

---

## 17. Orden de precedencia

Orden recomendado:

```text
1000  CompiledParameterResolver
950   ExplicitAttributeResolver
900   RequestResolver
890   ControllerExecutionResolver
880   ControllerContextResolver
870   RouteMatchResolver
860   CurrentUserResolver
850   CurrentTenantResolver
840   SessionResolver
830   UploadedFileResolver
820   DtoResolver
810   ModelResolver
800   EnumResolver
790   DateTimeResolver
780   CollectionResolver
770   PaginationResolver
700   RouteParameterResolver
650   QueryParameterResolver
640   HeaderParameterResolver
630   CookieParameterResolver
620   JsonBodyParameterResolver
610   BodyParameterResolver
500   ServiceResolver
200   ScalarCoercionResolver
100   DefaultValueResolver
50    NullableResolver
```

Las resoluciones explícitas mediante atributos tendrán prioridad sobre las convenciones.

---

## 18. Resolución por atributos

Los atributos podrán indicar la fuente exacta del parámetro.

Ejemplo:

```php
public function search(
    #[Query('q')]
    string $term,

    #[Header('X-Api-Version')]
    string $version,

    #[RouteParameter('organization')]
    Organization $organization,

    #[CurrentUser]
    User $user,

    #[Body]
    SearchFilters $filters
): ResponseInterface {
}
```

Cada atributo deberá implementar o referenciar una estrategia de resolución.

---

## 19. Contrato de atributo de parámetro

```php
interface ParameterAttributeInterface
{
    public function resolver(): string;

    public function metadata(): array;
}
```

Ejemplo:

```php
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Query implements
    ParameterAttributeInterface
{
    public function __construct(
        public ?string $name = null,
        public mixed $default = null,
        public bool $required = true,
    ) {
    }

    public function resolver(): string
    {
        return QueryParameterResolver::class;
    }

    public function metadata(): array
    {
        return [
            'name' => $this->name,
            'default' => $this->default,
            'required' => $this->required,
        ];
    }
}
```

---

## 20. Atributos iniciales

La primera arquitectura podrá contemplar:

```text
#[RouteParameter]
#[Query]
#[Body]
#[Json]
#[Header]
#[Cookie]
#[UploadedFile]
#[CurrentUser]
#[CurrentTenant]
#[FromContainer]
#[FromContext]
#[SessionValue]
#[Locale]
#[MapTo]
#[BindModel]
#[Validate]
#[DateTimeFormat]
#[Pagination]
```

---

## 21. ExplicitAttributeResolver

El `ExplicitAttributeResolver` no necesariamente resolverá el valor directamente.

Actuará como router hacia el resolver declarado por el atributo.

```php
final class ExplicitAttributeResolver implements
    ParameterValueResolverInterface
{
    public function __construct(
        private readonly ParameterResolverLocatorInterface $locator
    ) {
    }

    public function supports(
        ParameterDefinition $parameter,
        ControllerExecution $execution
    ): bool {
        return $parameter->attributes !== [];
    }

    public function resolve(
        ParameterDefinition $parameter,
        ControllerExecution $execution
    ): ParameterResolutionResult {
        $attribute = $this->selectAttribute($parameter);

        $resolver = $this->locator->get(
            $attribute->resolver()
        );

        return $resolver->resolveAttributed(
            parameter: $parameter,
            attribute: $attribute,
            execution: $execution
        );
    }

    public function priority(): int
    {
        return 950;
    }
}
```

---

## 22. Conflictos entre atributos

No se deberán permitir atributos de fuente incompatibles en el mismo parámetro.

Ejemplo inválido:

```php
public function show(
    #[Query]
    #[RouteParameter]
    int $id
): ResponseInterface {
}
```

Esto deberá lanzar:

```text
ConflictingParameterAttributesException
```

Algunos atributos sí podrán combinarse:

```php
#[Body]
#[Validate]
CreateUserData $data
```

---

## 23. RequestResolver

Resolverá la petición HTTP actual.

```php
final class RequestResolver implements
    ParameterValueResolverInterface
{
    public function supports(
        ParameterDefinition $parameter,
        ControllerExecution $execution
    ): bool {
        return $parameter->type->primaryType !== null
            && is_a(
                $execution->context->request,
                $parameter->type->primaryType
            );
    }

    public function resolve(
        ParameterDefinition $parameter,
        ControllerExecution $execution
    ): ParameterResolutionResult {
        return ParameterResolutionResult::resolved(
            $execution->context->request,
            ParameterSource::Request
        );
    }

    public function priority(): int
    {
        return 900;
    }
}
```

Ejemplo:

```php
public function store(RequestInterface $request): ResponseInterface
{
}
```

---

## 24. ControllerExecutionResolver

Permitirá inyectar el objeto de ejecución cuando sea necesario.

```php
public function debug(
    ControllerExecution $execution
): ResponseInterface {
}
```

Este acceso estará orientado principalmente a infraestructura y debugging.

Podrá restringirse mediante configuración.

---

## 25. ControllerContextResolver

```php
public function show(
    ControllerContext $context
): ResponseInterface {
}
```

Permitirá acceder al contexto inmutable sin utilizar la clase base.

---

## 26. RouteMatchResolver

```php
public function show(
    RouteMatch $route
): ResponseInterface {
}
```

Resolverá el match actual desde `ControllerContext`.

---

## 27. RouteParameterResolver

Resolverá valores provenientes de parámetros de ruta.

Ruta:

```php
Route::get('/users/{id}', [
    UserController::class,
    'show',
]);
```

Controlador:

```php
public function show(int $id): ResponseInterface
{
}
```

El nombre del parámetro será utilizado por convención.

---

## 28. Resolución explícita de ruta

```php
public function show(
    #[RouteParameter('user')]
    int $id
): ResponseInterface {
}
```

El atributo permite desacoplar el nombre del método del nombre de la ruta.

---

## 29. Algoritmo de RouteParameterResolver

```text
Read explicit route parameter name
    ↓
Otherwise use PHP parameter name
    ↓
Check RouteMatch parameters
    ↓
Read raw value
    ↓
Apply scalar coercion if required
    ↓
Validate final type
    ↓
Return ResolvedParameter
```

Si no existe el parámetro de ruta, el resolver devolverá `unresolved`, salvo que el atributo lo marque como obligatorio.

---

## 30. QueryParameterResolver

```php
public function index(
    #[Query('page', default: 1)]
    int $page,

    #[Query('search', required: false)]
    ?string $search
): ResponseInterface {
}
```

Permitirá:

* Valores individuales.
* Arrays.
* Booleanos.
* Paginación.
* Defaults.
* Parámetros repetidos.

No deberá resolver automáticamente cualquier parámetro escalar desde query sin configuración, salvo que una política de ruta lo permita.

---

## 31. HeaderParameterResolver

```php
public function index(
    #[Header('Accept-Language')]
    string $language
): ResponseInterface {
}
```

Reglas:

* Los nombres no distinguirán mayúsculas y minúsculas.
* Los valores múltiples podrán resolverse como arrays.
* Los headers sensibles no deberán incluirse en logs.
* Los valores faltantes deberán respetar nullable y default.

---

## 32. CookieParameterResolver

```php
public function dashboard(
    #[Cookie('theme', default: 'system')]
    string $theme
): ResponseInterface {
}
```

El resolver deberá considerar cookies firmadas o cifradas mediante los servicios HTTP correspondientes.

---

## 33. BodyParameterResolver

Resolverá datos de formularios o cuerpo decodificado.

```php
public function store(
    #[Body('name')]
    string $name
): ResponseInterface {
}
```

También podrá resolver un mapa completo:

```php
public function store(
    #[Body]
    array $input
): ResponseInterface {
}
```

---

## 34. JsonBodyParameterResolver

```php
public function store(
    #[Json('profile.name')]
    string $name
): JsonResponse {
}
```

Permitirá usar notación de puntos.

```text
profile.name
profile.address.city
items.0.name
```

Cuando el cuerpo no sea JSON válido, deberá lanzar una excepción HTTP específica antes de invocar el controlador.

---

## 35. UploadedFileResolver

```php
public function upload(
    #[UploadedFile('document')]
    UploadedFile $document
): ResponseInterface {
}
```

También soportará múltiples archivos:

```php
public function upload(
    #[UploadedFile('images')]
    array $images
): ResponseInterface {
}
```

Validaciones iniciales:

* Archivo presente.
* Upload válido.
* Tamaño permitido.
* Tipo esperado cuando se declare.
* Error de carga.
* Cantidad permitida.

La validación avanzada pertenecerá al módulo de Validation.

---

## 36. CurrentUserResolver

```php
public function profile(
    #[CurrentUser]
    User $user
): ResponseInterface {
}
```

También podrá inferirse por tipos de autenticación configurados:

```php
public function profile(
    AuthenticatableInterface $user
): ResponseInterface {
}
```

Reglas:

* Resolver desde el contexto de autenticación.
* Validar compatibilidad de tipo.
* Respetar guard explícito.
* Lanzar excepción de autenticación cuando sea requerido.
* Resolver `null` cuando el parámetro sea nullable.

Atributo:

```php
#[CurrentUser(guard: 'admin', required: true)]
```

---

## 37. CurrentTenantResolver

```php
public function index(
    #[CurrentTenant]
    Tenant $tenant
): ResponseInterface {
}
```

Reglas:

* Resolver desde `ControllerContext`.
* Validar aislamiento.
* Validar tipo.
* Soportar tenant opcional.
* No consultar tenant global estático.
* No conservar tenant entre peticiones.

---

## 38. SessionResolver

Podrá resolver la sesión completa:

```php
public function dashboard(
    SessionInterface $session
): ResponseInterface {
}
```

O un valor:

```php
public function dashboard(
    #[SessionValue('cart')]
    array $cart
): ResponseInterface {
}
```

En rutas stateless deberá fallar de manera explícita.

---

## 39. EnumResolver

```php
enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
```

Ruta:

```php
Route::get('/users/status/{status}', ...);
```

Controlador:

```php
public function index(
    UserStatus $status
): ResponseInterface {
}
```

El resolver deberá detectar `BackedEnum` y utilizar:

```php
UserStatus::from($value);
```

Para errores controlados podrá utilizar:

```php
UserStatus::tryFrom($value);
```

y lanzar una excepción descriptiva.

---

## 40. Unit Enum

Para enums sin valor respaldado:

```php
enum SortDirection
{
    case Ascending;
    case Descending;
}
```

Podrá resolverse por nombre:

```text
Ascending
Descending
```

La comparación sensible o insensible a mayúsculas será configurable.

---

## 41. DateTimeResolver

```php
public function report(
    #[DateTimeFormat('Y-m-d')]
    DateTimeImmutable $date
): ResponseInterface {
}
```

También podrá resolver por formato ISO predeterminado.

Tipos soportados:

* `DateTimeInterface`
* `DateTime`
* `DateTimeImmutable`
* Clases de fecha configuradas.

El timezone deberá provenir del contexto o del atributo.

---

## 42. ModelResolver

Resolverá modelos o entidades a partir de valores de ruta.

Ruta:

```php
Route::get('/users/{user}', [
    UserController::class,
    'show',
]);
```

Controlador:

```php
public function show(User $user): ResponseInterface
{
}
```

Flujo:

```text
Parameter type: User
    ↓
Route parameter: user
    ↓
Binding metadata
    ↓
ModelBindingManager
    ↓
Repository / ORM
    ↓
User instance
```

---

## 43. Model binding explícito

```php
public function show(
    #[BindModel(
        parameter: 'username',
        field: 'username'
    )]
    User $user
): ResponseInterface {
}
```

También podrá declarar scopes:

```php
#[BindModel(
    parameter: 'post',
    scopedBy: 'organization'
)]
Post $post
```

---

## 44. ModelBindingManager

El resolver no deberá implementar directamente consultas del ORM.

```php
interface ModelBindingManagerInterface
{
    public function resolve(
        ModelBindingRequest $request
    ): object|Collection|null;
}
```

Esto permitirá integrar:

* ORM propio de VoltStack.
* Cycle ORM.
* Doctrine.
* Eloquent mediante adaptador.
* Repositories personalizados.

---

## 45. ModelBindingRequest

```php
final readonly class ModelBindingRequest
{
    public function __construct(
        public string $modelClass,
        public mixed $value,
        public string $routeParameter,
        public ?string $field,
        public bool $required,
        public bool $withTrashed,
        public ?string $scope,
        public ?object $parent,
        public ControllerExecution $execution,
    ) {
    }
}
```

---

## 46. Scoped binding

Ruta:

```text
/organizations/{organization}/users/{user}
```

Controlador:

```php
public function show(
    Organization $organization,
    User $user
): ResponseInterface {
}
```

El `User` deberá resolverse dentro de la organización cuando la ruta o metadata lo indiquen.

Esto evita acceso horizontal entre tenants u organizaciones.

---

## 47. Binding faltante

Cuando un modelo requerido no exista:

```text
ModelBindingNotFoundException
```

El Exception Handler podrá convertirla en:

```text
404 Not Found
```

Cuando sea nullable:

```php
public function show(?User $user): ResponseInterface
{
}
```

la política podrá devolver `null`, aunque se recomienda usar nullable solo cuando semánticamente tenga sentido.

---

## 48. DtoResolver

Resolverá DTOs desde distintas fuentes de entrada.

```php
final readonly class CreateUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
    ) {
    }
}
```

Controlador:

```php
public function store(
    CreateUserData $data
): ResponseInterface {
}
```

---

## 49. Estrategias de DTO

El DTO podrá crearse mediante:

* Constructor property mapping.
* Factory estática.
* Hydrator registrado.
* Attribute mapping.
* Serializer.
* Validator.
* Compiled mapper.

Contrato:

```php
interface DtoHydratorInterface
{
    public function supports(string $dtoClass): bool;

    public function hydrate(
        string $dtoClass,
        array $data,
        DtoHydrationContext $context
    ): object;
}
```

---

## 50. Fuente de datos de DTO

La fuente podrá declararse:

```php
#[Body]
CreateUserData $data
```

```php
#[Json]
CreateUserData $data
```

```php
#[Query]
SearchUserData $filters
```

Sin atributo, el resolver podrá usar metadata de ruta:

```php
'input_source' => 'body'
```

o una política predeterminada basada en el verbo HTTP.

---

## 51. DTO y validación

El DTO resolver podrá:

1. Obtener datos.
2. Mapear propiedades.
3. Ejecutar coerción.
4. Validar.
5. Crear la instancia.

Sin embargo, la arquitectura deberá separar conceptualmente:

```text
Hydration
    ≠
Validation
```

El `DtoResolver` podrá delegar a:

```php
ValidationManagerInterface
```

o producir un DTO provisional para el `ValidationStage`.

---

## 52. Estrategias de validación de DTO

### 52.1 Validación durante resolución

```text
Input
    ↓
Validate
    ↓
Create DTO
```

Ventaja: el controlador siempre recibe un DTO válido.

### 52.2 Validación en ValidationStage

```text
Input
    ↓
Create DTO candidate
    ↓
ValidationStage
    ↓
Validated DTO
```

Ventaja: mantiene toda la validación declarativa en una etapa.

Recomendación de VoltStack:

* Validación estructural mínima durante resolución.
* Validación de reglas en `ValidationStage`.
* El valor entregado al invoker deberá estar validado.

---

## 53. ServiceResolver

Resolverá dependencias desde el Container.

```php
public function index(
    UserRepositoryInterface $users,
    LoggerInterface $logger
): ResponseInterface {
}
```

El resolver deberá comprobar:

* Que el tipo sea una clase o interface.
* Que no haya sido resuelto por resolvers de contexto, modelo o DTO.
* Que el Container pueda resolverlo.
* Que el servicio sea compatible con el scope actual.

---

## 54. Regla de prioridad entre Model y Service

Una clase como `User` podría estar registrada en el Container, pero también representar un modelo.

El orden será:

```text
Explicit attribute
    ↓
Contextual resolvers
    ↓
DTO resolver
    ↓
Model resolver
    ↓
Service resolver
```

Así se evita que modelos se resuelvan accidentalmente como servicios.

---

## 55. FromContainer explícito

```php
public function index(
    #[FromContainer]
    UserManager $manager
): ResponseInterface {
}
```

El atributo podrá indicar un ID:

```php
#[FromContainer('users.manager')]
UserManagerInterface $manager
```

Solo IDs permitidos podrán utilizarse.

---

## 56. CollectionResolver

Resolverá colecciones tipadas cuando exista metadata suficiente.

Ejemplo:

```php
public function bulk(
    #[Body('users')]
    UserDataCollection $users
): ResponseInterface {
}
```

O:

```php
public function show(
    #[BindModel(parameter: 'users')]
    Collection $users
): ResponseInterface {
}
```

La semántica de colecciones deberá ser explícita para evitar consultas inesperadas.

---

## 57. PaginationResolver

```php
public function index(
    #[Pagination(
        page: 'page',
        perPage: 'per_page',
        maxPerPage: 100
    )]
    PaginationRequest $pagination
): ResponseInterface {
}
```

Objeto:

```php
final readonly class PaginationRequest
{
    public function __construct(
        public int $page,
        public int $perPage,
        public ?string $cursor = null,
    ) {
    }
}
```

Este resolver podrá vivir inicialmente en un paquete opcional.

---

## 58. LocaleResolver

```php
public function index(
    #[Locale]
    string $locale
): ResponseInterface {
}
```

Resolverá desde:

1. Metadata de ruta.
2. Contexto de localización.
3. Usuario.
4. Header `Accept-Language`.
5. Configuración predeterminada.

La negociación real deberá pertenecer al módulo de Localization.

---

## 59. ScalarCoercionResolver

Permitirá convertir valores HTTP escalares al tipo declarado.

Tipos iniciales:

```text
string
int
float
bool
array
```

Ejemplos:

```text
"15" → 15
"10.5" → 10.5
"true" → true
"false" → false
"1" → true
"0" → false
```

La coerción deberá ser estricta y configurable.

---

## 60. Reglas para enteros

Valores aceptables:

```text
15
"15"
"-10"
```

Valores rechazados:

```text
"15.4"
"15abc"
""
null
```

salvo que nullable o default lo permitan.

---

## 61. Reglas para booleanos

Valores posibles:

```text
true
false
1
0
"1"
"0"
"true"
"false"
"yes"
"no"
"on"
"off"
```

La lista podrá configurarse.

No se deberá utilizar simplemente:

```php
(bool) $value
```

porque `"false"` se convertiría incorrectamente en `true`.

---

## 62. Arrays

Los arrays no deberán construirse automáticamente desde cadenas separadas por comas, salvo atributo explícito.

Ejemplo:

```php
#[Query('tags', separator: ',')]
array $tags
```

Sin atributo, se requerirá una entrada realmente estructurada como array.

---

## 63. Parámetros nullable

```php
public function index(?string $search): ResponseInterface
{
}
```

`NullableResolver` solo deberá devolver `null` cuando:

* El parámetro permita `null`.
* Ningún resolver anterior haya encontrado valor.
* No exista un valor por defecto más específico.
* La fuente no marque el parámetro como obligatorio.

---

## 64. Valores por defecto

```php
public function index(
    int $page = 1
): ResponseInterface {
}
```

El `DefaultValueResolver` se ejecutará casi al final.

El valor por defecto deberá validarse contra el tipo del parámetro.

---

## 65. Orden entre default y nullable

Para:

```php
public function index(
    ?string $search = null
): ResponseInterface {
}
```

Se utilizará el valor por defecto.

Para:

```php
public function index(
    ?string $search
): ResponseInterface {
}
```

se utilizará `null`.

Orden:

```text
DefaultValueResolver
    ↓
NullableResolver
```

---

## 66. Parámetros sin tipo

```php
public function legacy($value): ResponseInterface
{
}
```

Los parámetros sin tipo solo podrán resolverse mediante:

* Atributo explícito.
* Parámetro de ruta con mismo nombre.
* Valor por defecto.
* Configuración heredada permitida.

No deberán resolverse desde el Container.

Se recomendará declarar tipos en todos los controladores.

---

## 67. Parámetros mixed

```php
public function handle(
    #[Body('payload')]
    mixed $payload
): ResponseInterface {
}
```

`mixed` requerirá una fuente explícita.

Sin atributo, el motor deberá considerarlo ambiguo.

---

## 68. Union types

```php
public function show(
    User|Guest $subject
): ResponseInterface {
}
```

Los union types deberán resolverse únicamente cuando exista una estrategia inequívoca.

Ejemplo permitido:

```php
#[BindModel(
    map: [
        'user' => User::class,
        'guest' => Guest::class,
    ]
)]
User|Guest $subject
```

Sin metadata explícita, se deberá lanzar:

```text
AmbiguousUnionParameterException
```

---

## 69. Union con null

```php
User|null
```

se tratará como nullable y no como una unión ambigua.

---

## 70. Intersection types

```php
public function process(
    Countable&Iterator $items
): ResponseInterface {
}
```

Solo podrán resolverse mediante:

* Container.
* Atributo explícito.
* Resolver personalizado.

El valor deberá implementar todos los tipos declarados.

---

## 71. Parámetros variádicos

```php
public function report(
    User ...$users
): ResponseInterface {
}
```

Un resolver podrá devolver:

```php
ParameterResolutionResult::variadic(
    values: $users,
    source: ParameterSource::Model
);
```

El invoker deberá expandirlos correctamente.

---

## 72. Parámetros por referencia

```php
public function legacy(
    string &$value
): ResponseInterface {
}
```

Los parámetros por referencia no serán recomendados.

La V1 podrá rechazarlos:

```text
ReferenceParameterNotSupportedException
```

Esto simplifica resolución, compilación y seguridad.

---

## 73. Named arguments internos

El motor mantendrá resolución por posición para invocación.

También podrá construir un mapa por nombre para:

* Autorización.
* Validación.
* Attributes.
* Debugging.
* Interceptores.

```php
$bag->value('user');
```

---

## 74. Dependencias entre parámetros

Algunos parámetros podrán depender de otros.

Ejemplo:

```php
public function show(
    Organization $organization,
    User $user
): ResponseInterface {
}
```

El binding de `User` puede depender de `Organization`.

El motor deberá resolver de izquierda a derecha por defecto.

Esto coincide con el orden de la firma y permite scoped bindings.

---

## 75. ParameterResolutionContext

Cada resolución podrá recibir un contexto específico.

```php
final readonly class ParameterResolutionContext
{
    public function __construct(
        public ControllerExecution $execution,
        public ResolvedParameterBag $resolved,
        public ParameterDefinition $parameter,
        public int $position,
        public ?CompiledParameterPlan $plan = null,
    ) {
    }
}
```

Esto permitirá que un resolver consulte valores ya resueltos.

---

## 76. Contrato actualizado del resolver

Para soportar dependencias:

```php
interface ParameterValueResolverInterface
{
    public function supports(
        ParameterDefinition $parameter,
        ParameterResolutionContext $context
    ): bool;

    public function resolve(
        ParameterDefinition $parameter,
        ParameterResolutionContext $context
    ): ParameterResolutionResult;

    public function priority(): int;
}
```

---

## 77. Resolución completa del método

```php
public function resolve(
    ResolvedController $controller,
    ControllerExecution $execution
): ResolvedParameterBag {
    $definitions = $this->definitions
        ->createForController($controller);

    $bag = new ResolvedParameterBag();

    foreach ($definitions as $definition) {
        $context = new ParameterResolutionContext(
            execution: $execution,
            resolved: $bag,
            parameter: $definition,
            position: $definition->position,
        );

        $resolved = $this->pipeline->resolve(
            $definition,
            $context
        );

        $bag->add($resolved);
    }

    return $bag;
}
```

---

## 78. Ambigüedad de resolvers

En modo debug, el pipeline podrá identificar todos los resolvers que soportan un parámetro.

Si varios resolvers tienen la misma prioridad efectiva:

```text
AmbiguousParameterResolutionException
```

El error deberá mostrar:

* Controlador.
* Método.
* Parámetro.
* Tipo.
* Resolvers candidatos.
* Prioridades.
* Sugerencia de atributo explícito.

---

## 79. Resolución fallida

Ejemplo de mensaje:

```text
Unable to resolve controller parameter.

Controller:
App\Http\Controllers\UserController@show

Parameter:
$user

Type:
App\Models\User

Position:
0

Resolvers attempted:
- CurrentUserResolver
- DtoResolver
- ModelResolver
- ServiceResolver
- DefaultValueResolver
- NullableResolver

Reason:
Route parameter [user] was not found and the parameter is required.
```

---

## 80. Validación del valor resuelto

Antes de aceptar un valor, deberá verificarse:

* Compatibilidad con el tipo.
* Nullable.
* Union o intersection.
* Variadic.
* Valor por defecto.
* Clase esperada.
* Enum.
* Referencias no soportadas.
* Restricciones del atributo.

Contrato:

```php
interface ParameterResolutionValidatorInterface
{
    public function validate(
        ParameterDefinition $parameter,
        mixed $value
    ): void;

    public function toResolvedParameter(
        ParameterDefinition $parameter,
        ParameterValueResolverInterface $resolver,
        ParameterResolutionResult $result
    ): ResolvedParameter;
}
```

---

## 81. Coerción vs validación

La coerción transforma:

```text
"15" → 15
```

La validación comprueba:

```text
15 es compatible con int
```

No deberán confundirse.

Un resolver deberá marcar:

```php
coerced: true
```

cuando haya transformado el valor.

---

## 82. Plan compilado de parámetros

```php
final readonly class CompiledParameterPlan
{
    public function __construct(
        public string $name,
        public int $position,
        public ParameterTypeDefinition $type,
        public string $resolver,
        public array $resolverOptions,
        public bool $nullable,
        public bool $variadic,
        public bool $hasDefaultValue,
        public mixed $defaultValue,
        public array $attributes,
        public string $signatureHash,
    ) {
    }
}
```

---

## 83. CompiledMethodParameterPlan

```php
final readonly class CompiledMethodParameterPlan
{
    /**
     * @param list<CompiledParameterPlan> $parameters
     */
    public function __construct(
        public string $controller,
        public string $method,
        public array $parameters,
        public string $signatureHash,
    ) {
    }
}
```

---

## 84. Resolución compilada

En producción:

```text
Controller execution
    ↓
Load CompiledMethodParameterPlan
    ↓
For each parameter:
    ↓
Invoke preselected resolver
    ↓
Validate value
    ↓
Build bag
```

No será necesario:

* Leer ReflectionParameter.
* Buscar atributos.
* Probar todos los resolvers.
* Ordenar resolvers.
* Inferir fuente.
* Calcular tipos.

---

## 85. ParameterResolutionPlanCompiler

```php
interface ParameterResolutionPlanCompilerInterface
{
    public function compile(
        ResolvedController $controller
    ): CompiledMethodParameterPlan;
}
```

El compilador deberá:

1. Inspeccionar la firma.
2. Crear definiciones.
3. Seleccionar resolver.
4. Detectar ambigüedades.
5. Validar atributos.
6. Guardar opciones.
7. Generar signature hash.

---

## 86. Verificación de plan compilado

El plan será inválido cuando cambie:

* Clase.
* Método.
* Parámetros.
* Tipos.
* Atributos.
* Valores por defecto.
* Configuración de resolvers.
* Versión del módulo.
* Registro de resolvers.

En producción podrá fallar de forma estricta o hacer fallback dinámico.

---

## 87. Caché de definiciones

Además del plan compilado, el modo dinámico podrá cachear:

```text
ParameterDefinition[]
```

por firma de método.

No deberá cachear:

* Valores resueltos.
* Request.
* Usuario.
* Tenant.
* Modelos.
* DTOs.
* Servicios request-scoped.

---

## 88. ParameterResolutionCache

```php
interface ParameterResolutionCacheInterface
{
    public function getDefinitions(
        string $signature
    ): ?array;

    public function putDefinitions(
        string $signature,
        array $definitions
    ): void;

    public function getPlan(
        string $signature
    ): ?CompiledMethodParameterPlan;

    public function putPlan(
        string $signature,
        CompiledMethodParameterPlan $plan
    ): void;
}
```

---

## 89. Seguridad

El motor deberá:

* No leer fuentes arbitrarias sin una política.
* No resolver servicios internos por nombres de input.
* No incluir valores sensibles en logs.
* Respetar aislamiento de tenant.
* Aplicar scoped model binding.
* Validar archivos.
* Rechazar tipos ambiguos.
* No usar globals directamente.
* No conservar valores entre peticiones.
* No permitir atributos incompatibles.
* Rechazar parámetros por referencia en V1.
* Validar IDs explícitos del Container.
* No ejecutar factories controladas por input.
* No construir clases desde nombres enviados por el cliente.

---

## 90. Protección contra mass assignment

La hidratación de DTOs no deberá asignar automáticamente cualquier propiedad pública.

Se deberán utilizar:

* Constructor definido.
* Metadata compilada.
* Campos permitidos.
* Atributos mapeables.
* Hydrators registrados.

Ejemplo:

```php
#[MapInput([
    'name',
    'email',
    'phone',
])]
final readonly class CreateUserData
{
}
```

---

## 91. Protección de datos sensibles

Parámetros como:

```text
password
token
secret
authorization
cookie
credit_card
```

deberán marcarse automáticamente como sensibles.

También se respetará:

```php
#[SensitiveParameter]
string $password
```

Estos valores no aparecerán en:

* Excepciones.
* Debug toolbar.
* Logs.
* Traces.
* Dumps.
* Eventos de resolución.

---

## 92. Integración con autorización

El `ResolvedParameterBag` permitirá atributos como:

```php
#[Authorize('update', subject: 'user')]
```

El `AuthorizationStage` podrá obtener:

```php
$execution->parameters->value('user');
```

Esto evita volver a resolver modelos o datos.

---

## 93. Integración con validación

El `ValidationStage` podrá consultar:

* Parámetros DTO.
* Fuentes HTTP.
* Metadata `#[Validate]`.
* Datos originales.
* Datos convertidos.

El `ResolvedParameter` podrá incluir:

```php
metadata: [
    'raw_value' => $raw,
    'validation_rules' => $rules,
]
```

Los valores sensibles deberán omitirse.

---

## 94. Integración con model binding

Los modelos resueltos deberán almacenarse en el bag.

Esto permite:

* Autorización.
* Breadcrumbs.
* Auditoría.
* Transacciones.
* Scoped bindings posteriores.
* Serialización de respuesta.
* Debugging.

---

## 95. Integración con SPA Runtime

El motor podrá incluir resolvers opcionales para:

```text
SpaNavigationContext
PartialRequest
PrefetchContext
HydrationPayload
ComponentState
ClientMetadata
```

Ejemplo:

```php
public function update(
    HydrationPayload $payload
): SpaResponse {
}
```

Estos resolvers pertenecerán al módulo SPA o Component Runtime.

---

## 96. Integración con Server Actions

Las Actions podrán recibir:

```php
public function __invoke(
    ActionPayload $payload,
    ActionContext $context,
    CurrentUser $user
): ActionResult {
}
```

El paquete de Actions registrará sus resolvers especializados.

---

## 97. Integración con WebSockets

Un adaptador futuro podrá registrar:

```text
WebSocketMessageResolver
WebSocketConnectionResolver
ChannelResolver
```

El motor no deberá estar acoplado exclusivamente a HTTP, aunque la V1 se enfoque en controladores HTTP.

---

## 98. Observabilidad

Métricas:

```text
controller.parameters.resolve.total
controller.parameters.resolve.duration
controller.parameter.resolve.duration
controller.parameter.resolve.failure
controller.parameter.coercion.total
controller.parameter.cache_hit
controller.parameter.cache_miss
controller.parameter.model_binding.duration
controller.parameter.dto_hydration.duration
controller.parameter.container.duration
```

Tags:

```text
controller.class
controller.method
parameter.name
parameter.type
parameter.source
resolver.class
resolution.mode
coerced
nullable
default_used
```

No se deberán usar valores de parámetros como tags.

---

## 99. Eventos

Eventos propuestos:

```text
ParameterResolutionStarting
ParameterResolverSelected
ParameterResolving
ParameterResolved
ParameterResolutionFailed
ParameterCoerced
ParameterDefaultUsed
ParameterModelBindingStarting
ParameterModelBound
ParameterDtoHydrated
MethodParametersResolved
```

Los eventos por parámetro podrán desactivarse en producción.

---

## 100. Debugging

La debug toolbar podrá mostrar:

```text
Controller Parameters

$request
    Type: RequestInterface
    Source: request
    Resolver: RequestResolver

$user
    Type: App\Models\User
    Source: model
    Route parameter: user
    Resolver: ModelResolver
    Binding field: id

$data
    Type: CreateUserData
    Source: body
    Resolver: DtoResolver
    Validated: yes

$repository
    Type: UserRepositoryInterface
    Source: container
    Resolver: ServiceResolver
```

Los valores sensibles no se mostrarán.

---

## 101. Excepciones

Excepciones previstas:

```text
ParameterResolutionException
UnresolvableControllerParameterException
AmbiguousParameterResolutionException
InvalidResolvedParameterTypeException
ConflictingParameterAttributesException
UnsupportedParameterTypeException
AmbiguousUnionParameterException
UnsupportedIntersectionParameterException
ReferenceParameterNotSupportedException
MissingRouteParameterException
MissingQueryParameterException
MissingHeaderParameterException
MissingCookieParameterException
InvalidJsonBodyException
UploadedFileNotFoundException
UploadedFileResolutionException
CurrentUserUnavailableException
CurrentTenantUnavailableException
SessionUnavailableForParameterException
EnumParameterResolutionException
DateTimeParameterResolutionException
ModelBindingException
ModelBindingNotFoundException
DtoHydrationException
ContainerParameterResolutionException
InvalidScalarCoercionException
StaleParameterResolutionPlanException
```

---

## 102. Ejemplo de excepción

```text
Unable to resolve controller parameter.

Controller:
App\Http\Controllers\UserController@update

Parameter:
$status

Declared type:
App\Enums\UserStatus

Source:
Route parameter [status]

Raw value:
archived

Reason:
The value does not correspond to any case of UserStatus.

Allowed values:
active, suspended
```

En producción, el raw value podrá ocultarse según sensibilidad.

---

## 103. Configuración

Archivo sugerido:

```php
return [
    'parameters' => [
        'engine' => ParameterResolutionEngine::class,

        'compiled' => [
            'enabled' => env('APP_ENV') === 'production',
            'strict' => false,
            'validate_signature' => true,
        ],

        'reflection' => [
            'cache_definitions' => true,
        ],

        'resolution' => [
            'detect_ambiguity' => env('APP_DEBUG'),
            'allow_untyped_route_parameters' => true,
            'allow_mixed_without_attribute' => false,
            'allow_reference_parameters' => false,
            'resolve_left_to_right' => true,
        ],

        'coercion' => [
            'strict' => true,
            'booleans' => [
                'true' => ['true', '1', 'yes', 'on'],
                'false' => ['false', '0', 'no', 'off'],
            ],
        ],

        'models' => [
            'enabled' => true,
            'implicit_binding' => true,
            'scoped_binding' => true,
            'nullable_missing_model' => false,
        ],

        'dtos' => [
            'enabled' => true,
            'implicit_body_mapping' => true,
            'validate_during_resolution' => false,
        ],

        'container' => [
            'enabled' => true,
            'allow_explicit_ids' => true,
        ],

        'security' => [
            'hide_sensitive_values' => true,
            'block_dynamic_class_names' => true,
        ],
    ],
];
```

---

## 104. Registro en el Container

```php
$container->singleton(
    ParameterResolutionEngineInterface::class,
    ParameterResolutionEngine::class
);

$container->singleton(
    ParameterDefinitionFactoryInterface::class,
    ParameterDefinitionFactory::class
);

$container->singleton(
    ParameterResolverPipelineInterface::class,
    ParameterResolverPipeline::class
);

$container->singleton(
    ParameterResolverRegistryInterface::class,
    ParameterResolverRegistry::class
);

$container->singleton(
    ParameterResolutionValidatorInterface::class,
    ParameterResolutionValidator::class
);

$container->singleton(
    ParameterResolutionCacheInterface::class,
    ParameterResolutionCache::class
);
```

Los resolvers stateless podrán registrarse como singleton.

---

## 105. Bootstrapping

Durante `register`:

* Registrar contratos.
* Registrar el engine.
* Registrar pipeline.
* Registrar resolvers del núcleo.
* Registrar cache.
* Registrar compilador.
* Registrar configuración.

Durante `boot`:

* Incorporar resolvers de módulos.
* Ordenar resolvers.
* Validar prioridades.
* Detectar conflictos.
* Cargar planes compilados.
* Congelar registry en producción.

---

## 106. Resolvers iniciales del núcleo

La V1 deberá registrar:

```text
RequestResolver
ControllerContextResolver
RouteMatchResolver
RouteParameterResolver
QueryParameterResolver
HeaderParameterResolver
CookieParameterResolver
BodyParameterResolver
JsonBodyParameterResolver
UploadedFileResolver
CurrentUserResolver
CurrentTenantResolver
SessionResolver
DtoResolver
ModelResolver
EnumResolver
DateTimeResolver
ServiceResolver
ScalarCoercionResolver
DefaultValueResolver
NullableResolver
```

Algunos podrán pertenecer a módulos opcionales y cargarse solo cuando estén instalados.

---

## 107. Estructura de directorios

```text
src/
└── Quantum/
    └── Controllers/
        └── Parameters/
            ├── ParameterResolutionEngine.php
            │
            ├── Contracts/
            │   ├── ParameterResolutionEngineInterface.php
            │   ├── ParameterDefinitionFactoryInterface.php
            │   ├── ParameterResolverPipelineInterface.php
            │   ├── ParameterResolverRegistryInterface.php
            │   ├── ParameterValueResolverInterface.php
            │   ├── AttributedParameterResolverInterface.php
            │   ├── ParameterResolutionValidatorInterface.php
            │   ├── ParameterResolutionCacheInterface.php
            │   ├── ParameterResolutionPlanCompilerInterface.php
            │   ├── DtoHydratorInterface.php
            │   └── ModelBindingManagerInterface.php
            │
            ├── Definitions/
            │   ├── ParameterDefinition.php
            │   ├── ParameterTypeDefinition.php
            │   ├── ParameterTypeKind.php
            │   ├── ParameterSource.php
            │   ├── ResolvedParameter.php
            │   ├── ResolvedParameterBag.php
            │   ├── ParameterResolutionResult.php
            │   ├── ParameterResolutionStatus.php
            │   ├── ParameterResolutionContext.php
            │   ├── CompiledParameterPlan.php
            │   ├── CompiledMethodParameterPlan.php
            │   ├── ModelBindingRequest.php
            │   └── DtoHydrationContext.php
            │
            ├── Factory/
            │   ├── ParameterDefinitionFactory.php
            │   ├── ParameterTypeFactory.php
            │   └── ParameterAttributeFactory.php
            │
            ├── Pipeline/
            │   ├── ParameterResolverPipeline.php
            │   ├── ParameterResolverRegistry.php
            │   ├── ParameterResolverLocator.php
            │   ├── ParameterResolverOrderer.php
            │   └── ParameterResolutionValidator.php
            │
            ├── Resolvers/
            │   ├── ExplicitAttributeResolver.php
            │   ├── RequestResolver.php
            │   ├── ControllerExecutionResolver.php
            │   ├── ControllerContextResolver.php
            │   ├── RouteMatchResolver.php
            │   ├── RouteParameterResolver.php
            │   ├── QueryParameterResolver.php
            │   ├── HeaderParameterResolver.php
            │   ├── CookieParameterResolver.php
            │   ├── BodyParameterResolver.php
            │   ├── JsonBodyParameterResolver.php
            │   ├── UploadedFileResolver.php
            │   ├── CurrentUserResolver.php
            │   ├── CurrentTenantResolver.php
            │   ├── SessionResolver.php
            │   ├── DtoResolver.php
            │   ├── ModelResolver.php
            │   ├── EnumResolver.php
            │   ├── DateTimeResolver.php
            │   ├── CollectionResolver.php
            │   ├── PaginationResolver.php
            │   ├── LocaleResolver.php
            │   ├── ServiceResolver.php
            │   ├── ScalarCoercionResolver.php
            │   ├── DefaultValueResolver.php
            │   └── NullableResolver.php
            │
            ├── Attributes/
            │   ├── RouteParameter.php
            │   ├── Query.php
            │   ├── Body.php
            │   ├── Json.php
            │   ├── Header.php
            │   ├── Cookie.php
            │   ├── UploadedFile.php
            │   ├── CurrentUser.php
            │   ├── CurrentTenant.php
            │   ├── FromContainer.php
            │   ├── FromContext.php
            │   ├── SessionValue.php
            │   ├── Locale.php
            │   ├── BindModel.php
            │   ├── MapTo.php
            │   ├── Validate.php
            │   ├── DateTimeFormat.php
            │   └── Pagination.php
            │
            ├── Binding/
            │   ├── ModelBindingManager.php
            │   ├── ModelBindingResolver.php
            │   ├── ScopedModelBindingResolver.php
            │   └── ModelBindingMetadata.php
            │
            ├── Dto/
            │   ├── DtoHydrator.php
            │   ├── ConstructorDtoHydrator.php
            │   ├── CompiledDtoHydrator.php
            │   ├── DtoPropertyMapper.php
            │   └── DtoMetadata.php
            │
            ├── Coercion/
            │   ├── ScalarCoercer.php
            │   ├── BooleanCoercer.php
            │   ├── IntegerCoercer.php
            │   ├── FloatCoercer.php
            │   ├── ArrayCoercer.php
            │   └── CoercionResult.php
            │
            ├── Cache/
            │   ├── ParameterResolutionCache.php
            │   ├── ParameterDefinitionCache.php
            │   ├── CompiledParameterPlanLoader.php
            │   └── CompiledParameterPlanWriter.php
            │
            ├── Compiler/
            │   ├── ParameterResolutionPlanCompiler.php
            │   ├── ParameterDefinitionCompiler.php
            │   ├── ParameterResolverSelector.php
            │   └── ParameterSignatureGenerator.php
            │
            ├── Events/
            │   ├── ParameterResolutionStarting.php
            │   ├── ParameterResolverSelected.php
            │   ├── ParameterResolving.php
            │   ├── ParameterResolved.php
            │   ├── ParameterResolutionFailed.php
            │   ├── ParameterCoerced.php
            │   ├── ParameterModelBound.php
            │   ├── ParameterDtoHydrated.php
            │   └── MethodParametersResolved.php
            │
            ├── Exceptions/
            │   ├── ParameterResolutionException.php
            │   ├── UnresolvableControllerParameterException.php
            │   ├── AmbiguousParameterResolutionException.php
            │   ├── InvalidResolvedParameterTypeException.php
            │   ├── ConflictingParameterAttributesException.php
            │   ├── UnsupportedParameterTypeException.php
            │   ├── AmbiguousUnionParameterException.php
            │   ├── ReferenceParameterNotSupportedException.php
            │   ├── MissingRouteParameterException.php
            │   ├── MissingQueryParameterException.php
            │   ├── MissingHeaderParameterException.php
            │   ├── MissingCookieParameterException.php
            │   ├── InvalidJsonBodyException.php
            │   ├── UploadedFileNotFoundException.php
            │   ├── CurrentUserUnavailableException.php
            │   ├── CurrentTenantUnavailableException.php
            │   ├── EnumParameterResolutionException.php
            │   ├── DateTimeParameterResolutionException.php
            │   ├── ModelBindingException.php
            │   ├── ModelBindingNotFoundException.php
            │   ├── DtoHydrationException.php
            │   ├── ContainerParameterResolutionException.php
            │   ├── InvalidScalarCoercionException.php
            │   └── StaleParameterResolutionPlanException.php
            │
            └── Testing/
                ├── FakeParameterResolutionEngine.php
                ├── FakeParameterValueResolver.php
                ├── RecordingParameterResolver.php
                ├── ParameterResolutionTestBuilder.php
                ├── ResolvedParameterBagAssertions.php
                └── FakeModelBindingManager.php
```

---

## 108. Implementación mínima para V1

La V1 deberá incluir:

* `ParameterResolutionEngineInterface`.
* `ParameterResolutionEngine`.
* `ParameterDefinition`.
* `ParameterTypeDefinition`.
* `ResolvedParameter`.
* `ResolvedParameterBag`.
* `ParameterValueResolverInterface`.
* `ParameterResolverPipeline`.
* `ParameterResolverRegistry`.
* Request resolver.
* Route parameter resolver.
* Service resolver.
* Model resolver.
* DTO resolver básico.
* Enum resolver.
* Current user resolver.
* Current tenant resolver.
* Uploaded file resolver.
* Default value resolver.
* Nullable resolver.
* Coerción escalar estricta.
* Atributos principales.
* Reflection cache.
* Excepciones descriptivas.
* Pruebas de aislamiento entre peticiones.

Podrán posponerse:

* Intersection types avanzados.
* Union types complejos.
* Collections automáticas.
* Pagination resolver.
* Locale resolver.
* Compilación estricta.
* DTO compiler.
* WebSocket resolvers.
* Server Action resolvers.
* Parámetros por referencia.
* Resolución lazy de modelos.

---

## 109. Ejemplo completo

Ruta:

```php
Route::put(
    '/organizations/{organization}/users/{user}',
    [UserController::class, 'update']
);
```

Controlador:

```php
final class UserController extends Controller
{
    public function update(
        Organization $organization,
        User $user,
        #[Json]
        UpdateUserData $data,
        #[CurrentUser]
        AuthenticatableInterface $actor,
        UserRepositoryInterface $users
    ): RedirectResponse {
        $this->authorize('update', $user);

        $users->update(
            organization: $organization,
            user: $user,
            data: $data,
            actor: $actor
        );

        return $this->redirectToRoute('users.show', [
            'organization' => $organization,
            'user' => $user,
        ]);
    }
}
```

Resolución:

```text
$organization
    ↓
ModelResolver
    ↓
Route parameter [organization]
    ↓
Organization model

$user
    ↓
ModelResolver
    ↓
Route parameter [user]
    ↓
Scoped by resolved Organization
    ↓
User model

$data
    ↓
Explicit #[Json]
    ↓
DtoResolver
    ↓
JSON body
    ↓
UpdateUserData

$actor
    ↓
#[CurrentUser]
    ↓
CurrentUserResolver
    ↓
Authenticated user

$users
    ↓
ServiceResolver
    ↓
Container
    ↓
UserRepositoryInterface implementation
```

Bag resultante:

```php
[
    0 => ResolvedParameter(
        source: ParameterSource::Model,
        resolver: ModelResolver::class,
        value: $organization,
    ),

    1 => ResolvedParameter(
        source: ParameterSource::Model,
        resolver: ModelResolver::class,
        value: $user,
    ),

    2 => ResolvedParameter(
        source: ParameterSource::Dto,
        resolver: DtoResolver::class,
        value: $data,
    ),

    3 => ResolvedParameter(
        source: ParameterSource::User,
        resolver: CurrentUserResolver::class,
        value: $actor,
    ),

    4 => ResolvedParameter(
        source: ParameterSource::Container,
        resolver: ServiceResolver::class,
        value: $users,
    ),
]
```

El invoker recibirá:

```php
$bag->orderedValues();
```

---

## 110. Pruebas unitarias

Casos mínimos:

* Resuelve Request.
* Resuelve ControllerContext.
* Resuelve RouteMatch.
* Resuelve parámetro de ruta.
* Convierte ruta a `int`.
* Rechaza coerción inválida.
* Resuelve query explícito.
* Resuelve header.
* Resuelve cookie.
* Resuelve JSON.
* Resuelve archivo.
* Resuelve usuario actual.
* Resuelve tenant actual.
* Resuelve enum backed.
* Resuelve fecha.
* Resuelve servicio.
* Resuelve modelo.
* Resuelve model binding scoped.
* Resuelve DTO.
* Usa valor por defecto.
* Usa `null` cuando corresponde.
* Rechaza parámetro requerido.
* Rechaza atributos incompatibles.
* Detecta resolvers ambiguos.
* Respeta orden de parámetros.
* Resuelve dependencias de izquierda a derecha.
* Oculta parámetros sensibles.
* No conserva valores entre requests.
* El plan compilado equivale al dinámico.

---

## 111. Pruebas de integración

* Routing → Parameter Engine.
* Container → ServiceResolver.
* Auth → CurrentUserResolver.
* Tenant → CurrentTenantResolver.
* ORM → ModelResolver.
* Validation → DTO.
* Uploaded files → Controller.
* Authorization → ResolvedParameterBag.
* SPA payload → Resolver opcional.
* FrankenPHP → Múltiples peticiones.
* Route cache → Plan compilado.
* Scoped model binding multi-tenant.

---

## 112. Prueba de contaminación

```php
public function test_resolved_parameters_are_not_shared_between_requests(): void
{
    $first = $this->resolveParameters(
        routeParameters: ['user' => 1],
        user: User::fake(id: 10)
    );

    $second = $this->resolveParameters(
        routeParameters: ['user' => 2],
        user: User::fake(id: 20)
    );

    expect($first->value('user')->id)->toBe(1);
    expect($second->value('user')->id)->toBe(2);

    expect($first->value('actor')->id)->toBe(10);
    expect($second->value('actor')->id)->toBe(20);
}
```

---

## 113. Benchmarks

Escenarios:

```text
No parameters
Single route scalar
Five route scalars
Request + service
Implicit model binding
Scoped model binding
DTO hydration
Enum binding
Ten mixed parameters
Dynamic plan
Compiled plan
Cache hit
Cache miss
```

Métricas:

* Tiempo total.
* Tiempo por parámetro.
* Reflection calls.
* Container calls.
* ORM calls.
* Objetos temporales.
* Memoria.
* Resoluciones por segundo.
* Dynamic vs compiled.

---

## 114. Decisiones arquitectónicas

### ADR-CTRL-PARAM-001

**Decisión:** La resolución se dividirá en resolvers especializados.

**Razón:** Evita un resolver monolítico y permite extensiones independientes.

---

### ADR-CTRL-PARAM-002

**Decisión:** La salida será un `ResolvedParameterBag`.

**Razón:** Conserva valores, fuentes y metadata para etapas posteriores.

---

### ADR-CTRL-PARAM-003

**Decisión:** Los atributos explícitos tendrán prioridad.

**Razón:** Reducen ambigüedad y hacen visible la fuente de datos.

---

### ADR-CTRL-PARAM-004

**Decisión:** Los parámetros se resolverán de izquierda a derecha.

**Razón:** Permite dependencias y scoped model binding entre parámetros.

---

### ADR-CTRL-PARAM-005

**Decisión:** ModelResolver tendrá prioridad sobre ServiceResolver.

**Razón:** Evita que entidades y modelos se resuelvan accidentalmente desde el Container.

---

### ADR-CTRL-PARAM-006

**Decisión:** El Container resolverá servicios, no datos HTTP.

**Razón:** Mantiene separadas las responsabilidades de infraestructura y entrada de usuario.

---

### ADR-CTRL-PARAM-007

**Decisión:** La coerción escalar será estricta.

**Razón:** Evita conversiones silenciosas e inconsistentes.

---

### ADR-CTRL-PARAM-008

**Decisión:** Los parámetros ambiguos producirán error.

**Razón:** Es preferible una configuración explícita a una resolución arbitraria.

---

### ADR-CTRL-PARAM-009

**Decisión:** Los parámetros por referencia no se soportarán en V1.

**Razón:** Complican invocación, compilación y seguridad sin aportar valor significativo.

---

### ADR-CTRL-PARAM-010

**Decisión:** La compilación preseleccionará el resolver por parámetro.

**Razón:** Elimina Reflection y búsquedas repetidas en producción.

---

### ADR-CTRL-PARAM-011

**Decisión:** Los valores resueltos nunca se almacenarán en caché global.

**Razón:** Son datos request-scoped y podrían contaminar procesos persistentes.

---

### ADR-CTRL-PARAM-012

**Decisión:** La hidratación de DTO y su validación permanecerán conceptualmente separadas.

**Razón:** Permite reemplazar estrategias de validación sin acoplarlas al mapper.

---

## 115. Criterios de aceptación

El motor se considerará correctamente implementado cuando:

* Resuelva todos los parámetros en orden.
* Produzca un `ResolvedParameterBag`.
* Identifique la fuente de cada valor.
* Permita resolvers personalizados.
* Resuelva parámetros mediante atributos.
* Resuelva parámetros de ruta.
* Resuelva query, body, JSON, headers y cookies.
* Resuelva Request y contexto.
* Resuelva usuario y tenant.
* Resuelva servicios desde el Container.
* Resuelva modelos y bindings scoped.
* Resuelva DTOs.
* Resuelva enums.
* Resuelva archivos.
* Aplique coerción estricta.
* Respete defaults y nullable.
* Detecte ambigüedades.
* Produzca errores descriptivos.
* Oculte valores sensibles.
* Permita planes compilados.
* Mantenga equivalencia dinámica y compilada.
* No conserve valores entre peticiones.
* Sea compatible con FrankenPHP.
* Permita que autorización y validación reutilicen los parámetros resueltos.
* No requiera modificar el núcleo para añadir nuevas fuentes.

---

## 116. Conclusión

El `Parameter Resolution Engine` será uno de los motores centrales del sistema de controladores de VoltStack.

Su arquitectura permitirá transformar firmas PHP expresivas en invocaciones totalmente resueltas, sin obligar al desarrollador a extraer manualmente datos de la petición o acceder directamente al Container.

La combinación de:

* `ParameterDefinition`
* resolvers especializados
* atributos declarativos
* `ResolvedParameterBag`
* model binding
* DTO hydration
* coerción estricta
* planes compilados

permitirá ofrecer una experiencia de desarrollo cómoda, segura y extensible.

Este diseño supera el concepto tradicional de un simple argument resolver, convirtiendo la firma del método en una declaración formal de dependencias, contexto y datos de entrada.

Al mismo tiempo, el uso de caché inmutable, planes compilados y ausencia de valores globales permitirá que el sistema mantenga un coste reducido y funcione correctamente en runtimes persistentes como FrankenPHP.
