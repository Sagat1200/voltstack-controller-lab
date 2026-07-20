# 06_METADATA_ENGINE.md

# Motor unificado de metadatos de VoltStack

**Versión:** 1.0
**Estado:** Draft
**Módulo principal:** `VoltStack\Support\Metadata`
**Consumidor inicial:** `VoltStack\Quantum\Controllers`
**Documento anterior:** `05_PARAMETER_RESOLUTION_ENGINE.md`

---

# 1. Propósito

Este documento define la arquitectura del `Metadata Engine` de VoltStack.

El motor será una infraestructura transversal encargada de descubrir, recolectar, normalizar, combinar, validar, compilar y entregar metadatos a los diferentes módulos del framework.

El sistema no estará limitado a controladores.

Será utilizado por:

* Routing.
* Controllers.
* Parameters.
* Middleware.
* Actions.
* Components.
* SPA Runtime.
* Hydration.
* Validation.
* Authorization.
* ORM.
* Events.
* Commands.
* Queues.
* Serialization.
* Cache.
* Security.
* Testing.
* Paquetes externos.

El motor permitirá que todas estas áreas compartan una única arquitectura para trabajar con:

* Atributos PHP.
* Reflection.
* Configuración.
* Convenciones.
* Definiciones declarativas.
* Metadata aportada por rutas.
* Metadata aportada por paquetes.
* Metadata generada en runtime.
* Metadata compilada.
* Metadata cacheada.

Su resultado será una representación inmutable, tipada y determinista.

---

# 2. Problema que resuelve

Sin un motor común, cada módulo podría implementar su propia lógica:

```text
Routing
    → Reflection
    → Attributes
    → Arrays
    → Cache propia

Controllers
    → Reflection
    → Attributes
    → Arrays
    → Cache propia

ORM
    → Reflection
    → Attributes
    → Arrays
    → Cache propia

Components
    → Reflection
    → Attributes
    → Arrays
    → Cache propia
```

Esto produciría:

* Reflection duplicada.
* Reglas de precedencia inconsistentes.
* Cachés separadas.
* Formatos incompatibles.
* Mayor consumo de memoria.
* Mayor complejidad.
* Errores difíciles de diagnosticar.
* Acoplamiento entre módulos.
* Dificultad para compilar aplicaciones.
* Extensiones poco uniformes.

VoltStack utilizará una sola infraestructura:

```text
Metadata Sources
    ↓
Metadata Providers
    ↓
Metadata Fragments
    ↓
Metadata Normalization
    ↓
Metadata Merge
    ↓
Metadata Validation
    ↓
Metadata Compilation
    ↓
Immutable Metadata
```

---

# 3. Posición arquitectónica

El motor pertenecerá a una capa de soporte compartida.

```text
VoltStack
│
├── Platform
│
├── Support
│   └── Metadata
│
└── Quantum
    ├── Routing
    ├── Controllers
    ├── Actions
    ├── Components
    ├── ORM
    └── Spa
```

Dependencia recomendada:

```text
Quantum Modules
    ↓
Support\Metadata
```

El `Metadata Engine` no deberá depender directamente de módulos funcionales como Controllers, Routing u ORM.

Cada módulo registrará sus propios:

* Providers.
* Schemas.
* Keys.
* Merge policies.
* Validators.
* Compilers.
* Metadata adapters.

---

# 4. Objetivos

El motor deberá:

* Unificar la resolución de metadata.
* Soportar múltiples fuentes.
* Permitir metadata tipada.
* Evitar arrays sin contrato.
* Definir precedencia determinista.
* Combinar metadata de múltiples niveles.
* Detectar conflictos.
* Validar metadata.
* Permitir herencia controlada.
* Soportar atributos repetibles.
* Permitir providers de paquetes.
* Evitar Reflection repetida.
* Generar planes compilados.
* Mantener caché inmutable.
* Ser seguro en FrankenPHP.
* Permitir invalidación precisa.
* Ofrecer debugging detallado.
* Mantener bajo acoplamiento.
* Ser útil para cualquier módulo.
* Producir resultados equivalentes en modo dinámico y compilado.

---

# 5. No responsabilidades

El motor no será responsable de:

* Ejecutar middleware.
* Autorizar solicitudes.
* Validar datos de usuario.
* Ejecutar controladores.
* Resolver parámetros.
* Consultar modelos.
* Renderizar componentes.
* Persistir entidades.
* Ejecutar eventos.
* Construir respuestas HTTP.

El motor únicamente describe comportamiento mediante metadata.

Los consumidores interpretarán esa metadata.

---

# 6. Arquitectura general

```text
MetadataSubject
      │
      ▼
MetadataRequest
      │
      ▼
MetadataEngine
      │
      ├── MetadataCache
      │
      ├── MetadataProviderRegistry
      │
      ├── MetadataSchemaRegistry
      │
      └── MetadataCompiler
      │
      ▼
MetadataProviderPipeline
      │
      ├── CompiledMetadataProvider
      ├── RuntimeMetadataProvider
      ├── ConfigurationMetadataProvider
      ├── RouteMetadataProvider
      ├── AttributeMetadataProvider
      ├── ReflectionMetadataProvider
      ├── ConventionMetadataProvider
      └── PackageMetadataProvider
      │
      ▼
MetadataFragment[]
      │
      ▼
MetadataNormalizer
      │
      ▼
MetadataMerger
      │
      ▼
MetadataValidator
      │
      ▼
MetadataBag
```

---

# 7. Conceptos fundamentales

El motor utilizará los siguientes conceptos:

```text
MetadataSubject
MetadataScope
MetadataRequest
MetadataProvider
MetadataFragment
MetadataKey
MetadataValue
MetadataSchema
MetadataMergePolicy
MetadataBag
MetadataOrigin
MetadataTrace
CompiledMetadataPlan
```

---

# 8. MetadataSubject

`MetadataSubject` identifica el elemento sobre el cual se solicita metadata.

Ejemplos:

* Una clase.
* Un método.
* Un parámetro.
* Una ruta.
* Un componente.
* Una entidad.
* Una propiedad.
* Un evento.
* Una Action.
* Un comando.
* Un handler.
* Una aplicación completa.

Contrato:

```php
namespace VoltStack\Support\Metadata;

interface MetadataSubjectInterface
{
    public function type(): MetadataSubjectType;

    public function identity(): string;

    public function parent(): ?MetadataSubjectInterface;

    public function attributes(): array;
}
```

---

# 9. MetadataSubjectType

```php
enum MetadataSubjectType: string
{
    case Application = 'application';
    case Module = 'module';
    case Package = 'package';
    case Route = 'route';
    case Controller = 'controller';
    case Method = 'method';
    case Parameter = 'parameter';
    case Property = 'property';
    case Action = 'action';
    case Component = 'component';
    case Entity = 'entity';
    case Event = 'event';
    case Listener = 'listener';
    case Command = 'command';
    case Job = 'job';
    case Middleware = 'middleware';
    case Runtime = 'runtime';
    case Custom = 'custom';
}
```

El enum podrá ampliarse mediante un identificador personalizado en versiones futuras.

---

# 10. Implementaciones de sujetos

Implementaciones iniciales:

```text
ApplicationMetadataSubject
ModuleMetadataSubject
RouteMetadataSubject
ClassMetadataSubject
MethodMetadataSubject
ParameterMetadataSubject
PropertyMetadataSubject
ComponentMetadataSubject
EntityMetadataSubject
ActionMetadataSubject
CustomMetadataSubject
```

Ejemplo:

```php
final readonly class MethodMetadataSubject implements
    MetadataSubjectInterface
{
    public function __construct(
        public string $class,
        public string $method,
        public ?MetadataSubjectInterface $parentSubject = null,
        public array $context = [],
    ) {
    }

    public function type(): MetadataSubjectType
    {
        return MetadataSubjectType::Method;
    }

    public function identity(): string
    {
        return $this->class . '::' . $this->method;
    }

    public function parent(): ?MetadataSubjectInterface
    {
        return $this->parentSubject;
    }

    public function attributes(): array
    {
        return $this->context;
    }
}
```

---

# 11. MetadataScope

El scope indica la categoría funcional de metadata solicitada.

```php
final readonly class MetadataScope
{
    public function __construct(
        public string $name
    ) {
    }

    public static function controller(): self
    {
        return new self('controller');
    }

    public static function routing(): self
    {
        return new self('routing');
    }

    public static function parameter(): self
    {
        return new self('parameter');
    }

    public static function component(): self
    {
        return new self('component');
    }

    public static function entity(): self
    {
        return new self('entity');
    }

    public static function spa(): self
    {
        return new self('spa');
    }
}
```

El scope evita que todos los providers participen en todas las solicitudes.

---

# 12. Scopes iniciales

```text
application
module
routing
controller
method
parameter
middleware
authorization
validation
action
component
hydration
spa
entity
serialization
event
command
job
cache
security
runtime
testing
```

Un request podrá incluir varios scopes.

Ejemplo:

```php
MetadataScopeSet::from([
    'controller',
    'authorization',
    'validation',
]);
```

---

# 13. MetadataRequest

```php
final readonly class MetadataRequest
{
    public function __construct(
        public MetadataSubjectInterface $subject,
        public MetadataScopeSet $scopes,
        public MetadataResolutionMode $mode,
        public bool $includeParents = true,
        public bool $includeTrace = false,
        public array $context = [],
    ) {
    }
}
```

---

# 14. MetadataResolutionMode

```php
enum MetadataResolutionMode: string
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
    → usar compilado si existe
    → fallback dinámico

Dynamic
    → ignorar metadata compilada

Compiled
    → preferir compilado
    → permitir fallback

CompiledStrict
    → exigir metadata compilada

Debug
    → resolución dinámica con trazabilidad completa
```

---

# 15. Contrato principal

```php
namespace VoltStack\Support\Metadata\Contracts;

use VoltStack\Support\Metadata\MetadataBag;
use VoltStack\Support\Metadata\MetadataRequest;

interface MetadataEngineInterface
{
    public function resolve(
        MetadataRequest $request
    ): MetadataBag;

    public function has(
        MetadataRequest $request,
        string $key
    ): bool;

    public function get(
        MetadataRequest $request,
        string $key,
        mixed $default = null
    ): mixed;
}
```

---

# 16. Implementación principal

```php
final class MetadataEngine implements MetadataEngineInterface
{
    public function __construct(
        private readonly MetadataCacheInterface $cache,
        private readonly MetadataProviderPipelineInterface $providers,
        private readonly MetadataNormalizerInterface $normalizer,
        private readonly MetadataMergerInterface $merger,
        private readonly MetadataValidatorInterface $validator,
        private readonly MetadataSchemaRegistryInterface $schemas,
    ) {
    }

    public function resolve(
        MetadataRequest $request
    ): MetadataBag {
        $key = MetadataCacheKey::fromRequest($request);

        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }

        $fragments = $this->providers->collect($request);

        $normalized = $this->normalizer->normalize(
            $fragments,
            $request
        );

        $metadata = $this->merger->merge(
            $normalized,
            $request,
            $this->schemas
        );

        $this->validator->validate(
            $metadata,
            $request
        );

        $this->cache->put($key, $metadata);

        return $metadata;
    }
}
```

---

# 17. MetadataProvider

Cada provider representará una fuente de metadata.

```php
interface MetadataProviderInterface
{
    public function supports(
        MetadataRequest $request
    ): bool;

    /**
     * @return iterable<MetadataFragment>
     */
    public function provide(
        MetadataRequest $request
    ): iterable;

    public function priority(): int;

    public function name(): string;
}
```

---

# 18. Providers iniciales

```text
CompiledMetadataProvider
RuntimeMetadataProvider
ExplicitDefinitionMetadataProvider
RouteMetadataProvider
ConfigurationMetadataProvider
AttributeMetadataProvider
ReflectionMetadataProvider
ConventionMetadataProvider
PackageMetadataProvider
DefaultMetadataProvider
```

No todos deberán ejecutarse en cada request.

---

# 19. Orden recomendado de providers

La prioridad de recolección no será necesariamente la prioridad final de merge, pero se recomienda:

```text
1000  CompiledMetadataProvider
950   RuntimeMetadataProvider
900   ExplicitDefinitionMetadataProvider
850   RouteMetadataProvider
800   ConfigurationMetadataProvider
750   AttributeMetadataProvider
700   ReflectionMetadataProvider
600   PackageMetadataProvider
500   ConventionMetadataProvider
100   DefaultMetadataProvider
```

Las reglas efectivas dependerán del schema de cada key.

---

# 20. CompiledMetadataProvider

Será el primer provider consultado en modo compilado.

```php
final class CompiledMetadataProvider implements
    MetadataProviderInterface
{
    public function __construct(
        private readonly CompiledMetadataRegistryInterface $registry
    ) {
    }

    public function supports(
        MetadataRequest $request
    ): bool {
        return $request->mode !== MetadataResolutionMode::Dynamic
            && $this->registry->has($request);
    }

    public function provide(
        MetadataRequest $request
    ): iterable {
        yield MetadataFragment::compiled(
            $this->registry->get($request)
        );
    }

    public function priority(): int
    {
        return 1000;
    }

    public function name(): string
    {
        return 'compiled';
    }
}
```

Cuando el plan compilado sea completo, el pipeline podrá finalizar anticipadamente.

---

# 21. RuntimeMetadataProvider

Permitirá adjuntar metadata durante una ejecución.

Ejemplo:

```php
$execution->metadata()->set(
    'authorization.subject',
    $user
);
```

Esta metadata:

* Tendrá scope de request.
* No se almacenará en caché global.
* No será compilable.
* Deberá estar aislada por ejecución.
* Tendrá alta precedencia cuando el schema lo permita.

No deberá utilizarse como sustituto de configuración estable.

---

# 22. ExplicitDefinitionMetadataProvider

Leerá metadata incluida directamente en definiciones.

Ejemplos:

```php
new ControllerDefinition(
    metadata: [
        'middleware' => ['auth'],
        'response.format' => 'json',
    ]
);
```

```php
Route::get('/users', ...)
    ->metadata([
        'cache.ttl' => 60,
    ]);
```

Esta fuente tendrá mayor precedencia que las convenciones.

---

# 23. RouteMetadataProvider

Convertirá metadata del sistema de rutas en fragmentos genéricos.

Fuentes:

* Grupo de rutas.
* Ruta concreta.
* Prefijo.
* Dominio.
* Tenant.
* Middleware.
* Defaults.
* Requirements.
* Runtime SPA.
* Cache.
* Security.

Ejemplo:

```php
Route::middleware('auth')
    ->group(function (): void {
        Route::get('/users', UserController::class)
            ->metadata([
                'authorization.required' => true,
            ]);
    });
```

---

# 24. ConfigurationMetadataProvider

Obtendrá metadata desde archivos de configuración.

Ejemplos:

```php
return [
    'controllers' => [
        UserController::class => [
            'middleware' => ['auth'],
        ],
    ],
];
```

También podrá leer configuración por:

* Namespace.
* Módulo.
* Clase.
* Método.
* Ruta.
* Entorno.
* Tenant.
* Paquete.

La configuración se normalizará antes del merge.

---

# 25. AttributeMetadataProvider

Leerá atributos PHP.

Ejemplo:

```php
#[Middleware('auth')]
#[Authorize('viewAny', User::class)]
final class UserController
{
    #[Cache(ttl: 60)]
    public function index(): array
    {
    }
}
```

El provider convertirá atributos a `MetadataFragment`.

No deberá dejar objetos `ReflectionAttribute` dentro del resultado final.

---

# 26. Contrato para atributos de metadata

```php
interface MetadataAttributeInterface
{
    public function metadataKey(): string;

    public function metadataValue(): mixed;

    public function metadataScopes(): array;
}
```

Ejemplo:

```php
#[Attribute(
    Attribute::TARGET_CLASS
    | Attribute::TARGET_METHOD
    | Attribute::IS_REPEATABLE
)]
final readonly class Middleware implements
    MetadataAttributeInterface
{
    public function __construct(
        public string $middleware,
        public array $parameters = [],
    ) {
    }

    public function metadataKey(): string
    {
        return 'controller.middleware';
    }

    public function metadataValue(): mixed
    {
        return new MiddlewareDefinition(
            name: $this->middleware,
            parameters: $this->parameters,
        );
    }

    public function metadataScopes(): array
    {
        return ['controller', 'middleware'];
    }
}
```

---

# 27. MetadataAttributeMapper

No todos los atributos externos implementarán el contrato de VoltStack.

Se permitirá registrar mappers:

```php
interface MetadataAttributeMapperInterface
{
    public function supports(object $attribute): bool;

    /**
     * @return iterable<MetadataFragment>
     */
    public function map(
        object $attribute,
        MetadataAttributeContext $context
    ): iterable;
}
```

Esto permitirá integrar:

* Atributos PSR.
* Doctrine attributes.
* Symfony attributes.
* Paquetes de terceros.
* Atributos heredados de aplicaciones existentes.

---

# 28. ReflectionMetadataProvider

Aportará metadata estructural.

Ejemplos:

* Clase final o abstracta.
* Interfaces implementadas.
* Traits.
* Método público.
* Método estático.
* Tipos de parámetros.
* Tipo de retorno.
* Propiedades.
* Promoted properties.
* Readonly.
* Archivo.
* Línea.
* Namespace.
* Clase padre.

La Reflection estructural deberá normalizarse en objetos simples y compilables.

---

# 29. ConventionMetadataProvider

Aplicará convenciones cuando no exista configuración explícita.

Ejemplos:

```text
*Controller
    → subject type controller

*Action
    → subject type action

store()
    → operation create

update()
    → operation update

destroy()
    → operation delete

index()
    → collection response
```

Las convenciones:

* Tendrán baja prioridad.
* Serán configurables.
* Podrán desactivarse.
* No deberán sobrescribir metadata explícita.
* Deberán ser deterministas.

---

# 30. PackageMetadataProvider

Los paquetes podrán aportar metadata sin modificar clases de aplicación.

Ejemplo:

```php
$metadata->forController(UserController::class)
    ->add('audit.enabled', true);
```

Casos:

```text
Volt Auth
    → authorization metadata

Volt Tenant
    → tenant isolation metadata

Volt Audit
    → audit metadata

Volt Cache
    → response cache metadata

Volt SPA
    → navigation metadata
```

Los packages deberán declarar claramente su prioridad.

---

# 31. DefaultMetadataProvider

Aplicará valores predeterminados definidos por schemas.

Ejemplos:

```text
controller.scope
    → request

authorization.required
    → false

cache.enabled
    → false

spa.preserve_state
    → false
```

Los defaults nunca deberán ocultar la ausencia de metadata requerida.

---

# 32. MetadataFragment

Un provider no devolverá directamente un `MetadataBag`.

Devolverá fragmentos.

```php
final readonly class MetadataFragment
{
    public function __construct(
        public string $key,
        public mixed $value,
        public MetadataOrigin $origin,
        public int $priority,
        public MetadataMergeHint $mergeHint,
        public MetadataScopeSet $scopes,
        public bool $final = false,
        public array $conditions = [],
        public array $attributes = [],
    ) {
    }
}
```

---

# 33. MetadataOrigin

```php
final readonly class MetadataOrigin
{
    public function __construct(
        public string $provider,
        public MetadataOriginType $type,
        public string $location,
        public ?string $file = null,
        public ?int $line = null,
        public array $context = [],
    ) {
    }
}
```

Enum:

```php
enum MetadataOriginType: string
{
    case Compiled = 'compiled';
    case Runtime = 'runtime';
    case Definition = 'definition';
    case Route = 'route';
    case Configuration = 'configuration';
    case Attribute = 'attribute';
    case Reflection = 'reflection';
    case Convention = 'convention';
    case Package = 'package';
    case Default = 'default';
}
```

---

# 34. MetadataMergeHint

```php
enum MetadataMergeHint: string
{
    case Schema = 'schema';
    case Replace = 'replace';
    case Append = 'append';
    case Prepend = 'prepend';
    case Merge = 'merge';
    case Unique = 'unique';
    case DeepMerge = 'deep_merge';
    case Min = 'min';
    case Max = 'max';
    case Deny = 'deny';
    case Allow = 'allow';
}
```

El hint será una sugerencia.

El schema tendrá la autoridad final.

---

# 35. MetadataBag

El resultado será inmutable.

```php
final readonly class MetadataBag implements
    IteratorAggregate,
    Countable
{
    public function __construct(
        private array $values,
        private array $origins = [],
        private ?MetadataTrace $trace = null,
    ) {
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(
        string $key,
        mixed $default = null
    ): mixed {
        return $this->values[$key] ?? $default;
    }

    public function require(string $key): mixed
    {
        if (! $this->has($key)) {
            throw MissingMetadataException::forKey($key);
        }

        return $this->values[$key];
    }

    public function string(
        string $key,
        ?string $default = null
    ): ?string {
        $value = $this->get($key, $default);

        if ($value !== null && ! is_string($value)) {
            throw InvalidMetadataAccessException::expected(
                $key,
                'string',
                get_debug_type($value)
            );
        }

        return $value;
    }

    public function bool(
        string $key,
        bool $default = false
    ): bool {
        $value = $this->get($key, $default);

        if (! is_bool($value)) {
            throw InvalidMetadataAccessException::expected(
                $key,
                'bool',
                get_debug_type($value)
            );
        }

        return $value;
    }

    public function all(): array
    {
        return $this->values;
    }

    public function origin(string $key): array
    {
        return $this->origins[$key] ?? [];
    }

    public function trace(): ?MetadataTrace
    {
        return $this->trace;
    }
}
```

---

# 36. Acceso tipado

Además de los métodos básicos, podrán existir:

```php
$metadata->int('cache.ttl');
$metadata->float('rate_limit.weight');
$metadata->array('controller.middleware');
$metadata->enum('controller.scope', ControllerScope::class);
$metadata->instance(
    'serialization.context',
    SerializationContext::class
);
```

Para metadata compleja se recomendarán objetos especializados.

---

# 37. Metadata Views

Cada módulo podrá exponer una vista tipada sobre `MetadataBag`.

Ejemplo:

```php
final readonly class ControllerMetadata
{
    public function __construct(
        private MetadataBag $metadata
    ) {
    }

    public function middleware(): array
    {
        return $this->metadata->get(
            'controller.middleware',
            []
        );
    }

    public function scope(): ControllerScope
    {
        return $this->metadata->get(
            'controller.scope',
            ControllerScope::Request
        );
    }

    public function authorization(): ?AuthorizationDefinition
    {
        return $this->metadata->get(
            'controller.authorization'
        );
    }
}
```

Esto permite mantener el motor genérico y ofrecer APIs específicas.

---

# 38. Metadata View Factory

```php
interface MetadataViewFactoryInterface
{
    public function create(
        string $view,
        MetadataBag $metadata
    ): object;
}
```

Ejemplos:

```text
ControllerMetadata
RouteMetadata
ParameterMetadata
ActionMetadata
ComponentMetadata
EntityMetadata
SpaMetadata
```

---

# 39. MetadataKey

Las keys no deberán ser cadenas dispersas sin control.

```php
final readonly class MetadataKey
{
    public function __construct(
        public string $name
    ) {
        if (! self::isValid($name)) {
            throw InvalidMetadataKeyException::forKey($name);
        }
    }

    private static function isValid(string $name): bool
    {
        return preg_match(
            '/^[a-z][a-z0-9]*(\.[a-z][a-z0-9_]*)+$/',
            $name
        ) === 1;
    }
}
```

Ejemplos:

```text
controller.middleware
controller.scope
controller.interceptors
authorization.policy
validation.rules
cache.ttl
spa.preserve_state
entity.table
serialization.groups
```

---

# 40. Namespaces de keys

Convención:

```text
<domain>.<name>
```

Ejemplos:

```text
routing.name
routing.middleware
controller.scope
controller.middleware
controller.result_normalizer
parameter.source
action.transaction
action.idempotency
component.hydration
component.events
spa.navigation
spa.partial_reload
entity.table
entity.connection
security.csrf
cache.ttl
```

Los paquetes deberán usar un namespace propio cuando no pertenezcan al núcleo.

```text
package_name.feature
```

---

# 41. MetadataSchema

Cada key deberá registrar un schema.

```php
final readonly class MetadataSchema
{
    public function __construct(
        public string $key,
        public MetadataValueType $type,
        public MetadataMergeStrategy $mergeStrategy,
        public mixed $defaultValue = null,
        public bool $required = false,
        public bool $nullable = false,
        public bool $repeatable = false,
        public bool $inheritable = true,
        public bool $compilable = true,
        public array $allowedScopes = [],
        public ?string $valueClass = null,
        public array $validators = [],
        public array $normalizers = [],
    ) {
    }
}
```

---

# 42. MetadataValueType

```php
enum MetadataValueType: string
{
    case Mixed = 'mixed';
    case String = 'string';
    case Integer = 'integer';
    case Float = 'float';
    case Boolean = 'boolean';
    case Array = 'array';
    case List = 'list';
    case Map = 'map';
    case Enum = 'enum';
    case Object = 'object';
    case Definition = 'definition';
}
```

---

# 43. MetadataMergeStrategy

```php
enum MetadataMergeStrategy: string
{
    case Replace = 'replace';
    case FirstWins = 'first_wins';
    case LastWins = 'last_wins';
    case Append = 'append';
    case Prepend = 'prepend';
    case UniqueAppend = 'unique_append';
    case ShallowMerge = 'shallow_merge';
    case DeepMerge = 'deep_merge';
    case BooleanAnd = 'boolean_and';
    case BooleanOr = 'boolean_or';
    case Minimum = 'minimum';
    case Maximum = 'maximum';
    case Custom = 'custom';
}
```

---

# 44. Ejemplos de schemas

## Middleware

```php
new MetadataSchema(
    key: 'controller.middleware',
    type: MetadataValueType::List,
    mergeStrategy: MetadataMergeStrategy::UniqueAppend,
    defaultValue: [],
    repeatable: true,
    inheritable: true,
);
```

## Scope

```php
new MetadataSchema(
    key: 'controller.scope',
    type: MetadataValueType::Enum,
    mergeStrategy: MetadataMergeStrategy::LastWins,
    defaultValue: ControllerScope::Request,
    valueClass: ControllerScope::class,
);
```

## Cache TTL

```php
new MetadataSchema(
    key: 'cache.ttl',
    type: MetadataValueType::Integer,
    mergeStrategy: MetadataMergeStrategy::LastWins,
    nullable: true,
);
```

## Seguridad

```php
new MetadataSchema(
    key: 'security.csrf',
    type: MetadataValueType::Boolean,
    mergeStrategy: MetadataMergeStrategy::BooleanAnd,
    defaultValue: true,
);
```

---

# 45. MetadataSchemaRegistry

```php
interface MetadataSchemaRegistryInterface
{
    public function register(
        MetadataSchema $schema
    ): void;

    public function has(string $key): bool;

    public function get(string $key): MetadataSchema;

    public function all(): iterable;

    public function freeze(): void;
}
```

En producción se congelará después del boot.

---

# 46. Keys no registradas

Políticas posibles:

```text
strict
warn
allow
```

Recomendación:

```php
'unknown_keys' => env('APP_DEBUG')
    ? 'warn'
    : 'strict',
```

Los paquetes deberán registrar sus schemas durante boot.

---

# 47. MetadataNormalizer

Los providers podrán devolver distintas representaciones.

El normalizador convertirá:

* Strings.
* Enums.
* Arrays.
* Atributos.
* Objetos de configuración.
* Definiciones heredadas.

a los tipos esperados por el schema.

Contrato:

```php
interface MetadataNormalizerInterface
{
    /**
     * @param iterable<MetadataFragment> $fragments
     *
     * @return list<MetadataFragment>
     */
    public function normalize(
        iterable $fragments,
        MetadataRequest $request
    ): array;
}
```

---

# 48. Value Normalizers

```php
interface MetadataValueNormalizerInterface
{
    public function supports(
        MetadataSchema $schema,
        mixed $value
    ): bool;

    public function normalize(
        MetadataSchema $schema,
        mixed $value,
        MetadataNormalizationContext $context
    ): mixed;

    public function priority(): int;
}
```

Normalizadores iniciales:

```text
EnumMetadataNormalizer
ListMetadataNormalizer
MapMetadataNormalizer
BooleanMetadataNormalizer
ClassNameMetadataNormalizer
MiddlewareMetadataNormalizer
DurationMetadataNormalizer
CallableMetadataNormalizer
DefinitionMetadataNormalizer
```

---

# 49. Ejemplo de normalización

Input:

```php
#[Cache(ttl: '5 minutes')]
```

Fragmento inicial:

```php
[
    'key' => 'cache.ttl',
    'value' => '5 minutes',
]
```

Resultado normalizado:

```php
[
    'key' => 'cache.ttl',
    'value' => 300,
]
```

El valor final será compilable y consistente.

---

# 50. MetadataMerger

```php
interface MetadataMergerInterface
{
    /**
     * @param list<MetadataFragment> $fragments
     */
    public function merge(
        array $fragments,
        MetadataRequest $request,
        MetadataSchemaRegistryInterface $schemas
    ): MetadataBag;
}
```

El merger deberá:

1. Agrupar por key.
2. Filtrar por scope.
3. Aplicar condiciones.
4. Ordenar por precedencia.
5. Respetar fragments finales.
6. Aplicar estrategia de merge.
7. Registrar orígenes.
8. Producir trace cuando se solicite.
9. Crear bag inmutable.

---

# 51. Precedencia

La precedencia predeterminada será:

```text
Runtime
    ↓
Explicit Definition
    ↓
Route or Subject-specific Configuration
    ↓
Method Attributes
    ↓
Class Attributes
    ↓
Parent Method
    ↓
Parent Class
    ↓
Package Metadata
    ↓
Global Configuration
    ↓
Convention
    ↓
Default
```

Sin embargo, cada schema podrá personalizarla.

---

# 52. Precedencia por nivel

Para un método de controlador:

```text
Application
    ↓
Module
    ↓
Route Group
    ↓
Route
    ↓
Parent Controller
    ↓
Controller Class
    ↓
Controller Method
    ↓
Runtime Override
```

Por defecto, el nivel más específico tendrá mayor precedencia en estrategias `LastWins`.

---

# 53. MetadataHierarchy

```php
final readonly class MetadataHierarchy
{
    /**
     * @param list<MetadataSubjectInterface> $subjects
     */
    public function __construct(
        public array $subjects
    ) {
    }
}
```

Ejemplo:

```text
ApplicationSubject
ModuleSubject
RouteGroupSubject
RouteSubject
ControllerClassSubject
ControllerMethodSubject
ControllerParameterSubject
```

El engine podrá resolver toda la jerarquía en una sola operación.

---

# 54. Herencia de metadata

El schema determinará si una key es heredable.

Ejemplo heredable:

```text
controller.middleware
authorization.guard
serialization.groups
```

Ejemplo no heredable:

```text
routing.name
entity.primary_key
parameter.source
```

La herencia deberá ser explícita en el schema.

---

# 55. Herencia de atributos PHP

Los atributos PHP no siempre se heredan automáticamente.

El engine implementará su propia política:

```php
#[MetadataInheritance(
    fromParentClass: true,
    fromInterfaces: false,
    fromTraits: true,
)]
```

Fuentes posibles:

* Clase padre.
* Método sobrescrito.
* Interfaces.
* Traits.
* Método de trait.
* Atributos repetibles.

---

# 56. Traits

Los traits pueden aportar metadata.

Ejemplo:

```php
#[Middleware('audit')]
trait AuditableController
{
}
```

Política recomendada:

* Metadata de trait con menor prioridad que la clase.
* Detectar conflictos entre traits.
* Mantener el origen real.
* Permitir desactivar metadata heredada de traits.

---

# 57. Interfaces

Una interfaz podrá declarar contratos de metadata.

```php
#[Authorize('authenticated')]
interface ProtectedController
{
}
```

La herencia desde interfaces estará desactivada por defecto para evitar efectos inesperados.

Podrá activarse por schema o configuración.

---

# 58. Métodos sobrescritos

Ejemplo:

```php
abstract class BaseController
{
    #[Cache(ttl: 60)]
    public function index(): array
    {
    }
}

final class UserController extends BaseController
{
    #[Cache(ttl: 10)]
    public function index(): array
    {
    }
}
```

La metadata del método hijo tendrá precedencia.

Según la estrategia:

```text
Replace
    → 10

Append
    → parent + child

Minimum
    → 10

Maximum
    → 60
```

---

# 59. Metadata final

Un fragmento podrá marcarse como final:

```php
new MetadataFragment(
    key: 'security.csrf',
    value: true,
    final: true,
);
```

Una key final no podrá ser sobrescrita por niveles posteriores.

Casos:

* Políticas de seguridad.
* Restricciones de plataforma.
* Configuración empresarial.
* Compliance.
* Aislamiento multi-tenant.

Intentar sobrescribirla lanzará:

```text
FinalMetadataOverrideException
```

---

# 60. Metadata condicional

Un fragmento podrá tener condiciones:

```php
[
    'environment' => 'production',
]
```

```php
[
    'route_method' => ['POST', 'PUT'],
]
```

```php
[
    'tenant_plan' => 'enterprise',
]
```

Contrato:

```php
interface MetadataConditionInterface
{
    public function matches(
        MetadataRequest $request
    ): bool;
}
```

Condiciones iniciales:

```text
EnvironmentCondition
HttpMethodCondition
RouteNameCondition
TenantCondition
FeatureFlagCondition
RuntimeCondition
PackageInstalledCondition
```

---

# 61. Restricción de condiciones dinámicas

Las condiciones que dependen de datos request-scoped:

* No se incluirán en caché global.
* No serán compiladas como valores definitivos.
* Se compilarán como condición ejecutable segura.
* Deberán ser deterministas dentro de una petición.

No se permitirán closures arbitrarias en planes compilados.

---

# 62. MetadataValidator

```php
interface MetadataValidatorInterface
{
    public function validate(
        MetadataBag $metadata,
        MetadataRequest $request
    ): void;
}
```

Validará:

* Tipos.
* Keys requeridas.
* Scopes permitidos.
* Valores enum.
* Clases válidas.
* Conflictos.
* Reglas de seguridad.
* Dependencias entre keys.
* Metadata incompatible.
* Valores no compilables en modo estricto.

---

# 63. Metadata Rule Validators

```php
interface MetadataRuleValidatorInterface
{
    public function supports(
        MetadataSchema $schema,
        MetadataBag $metadata
    ): bool;

    public function validate(
        MetadataSchema $schema,
        MetadataBag $metadata,
        MetadataValidationContext $context
    ): void;
}
```

Ejemplos:

```text
MetadataTypeValidator
MetadataRequiredValidator
MetadataScopeValidator
MetadataClassExistsValidator
MetadataMutualExclusionValidator
MetadataDependencyValidator
MetadataSecurityValidator
MetadataCompilabilityValidator
```

---

# 64. Dependencias entre keys

Ejemplo:

```text
cache.enabled = true
```

puede requerir:

```text
cache.key_strategy
```

Otro ejemplo:

```text
action.idempotency = true
```

puede requerir:

```text
action.idempotency.key
```

Schema extendido:

```php
dependencies: [
    'cache.enabled' => [
        'requires' => ['cache.key_strategy'],
    ],
],
```

---

# 65. Keys mutuamente excluyentes

Ejemplo:

```text
response.streaming
response.cache
```

podrían ser incompatibles.

Otro ejemplo:

```text
controller.scope = singleton
controller.request_aware = true
```

deberá rechazarse bajo FrankenPHP.

---

# 66. MetadataTrace

En modo debug se generará trazabilidad completa.

```php
final readonly class MetadataTrace
{
    /**
     * @param list<MetadataTraceEntry> $entries
     */
    public function __construct(
        public array $entries
    ) {
    }
}
```

Entrada:

```php
final readonly class MetadataTraceEntry
{
    public function __construct(
        public string $key,
        public mixed $inputValue,
        public mixed $normalizedValue,
        public MetadataOrigin $origin,
        public bool $accepted,
        public ?string $rejectionReason,
        public MetadataMergeStrategy $strategy,
        public mixed $resultAfterMerge,
    ) {
    }
}
```

---

# 67. Ejemplo de trace

```text
Key: controller.middleware

1. Global configuration
   Value: ["web"]
   Accepted: yes

2. Controller attribute
   Value: ["auth"]
   Accepted: yes

3. Method attribute
   Value: ["verified"]
   Accepted: yes

Merge strategy:
UniqueAppend

Result:
["web", "auth", "verified"]
```

---

# 68. Metadata debugging API

```php
$metadata = $engine->resolve(
    new MetadataRequest(
        subject: $subject,
        scopes: MetadataScopeSet::from(['controller']),
        mode: MetadataResolutionMode::Debug,
        includeTrace: true,
    )
);

dump($metadata->trace());
```

También podrá existir:

```bash
php volt metadata:inspect \
    App\\Http\\Controllers\\UserController@index
```

---

# 69. Metadata Compiler

```php
interface MetadataCompilerInterface
{
    public function compile(
        MetadataRequest $request
    ): CompiledMetadataPlan;
}
```

El compilador deberá:

1. Resolver metadata dinámicamente.
2. Validar schemas.
3. Eliminar objetos no serializables.
4. Convertir valores a representaciones compilables.
5. Generar hashes.
6. Registrar dependencias.
7. Guardar orígenes opcionales.
8. Escribir un plan PHP optimizado.

---

# 70. CompiledMetadataPlan

```php
final readonly class CompiledMetadataPlan
{
    public function __construct(
        public string $subjectIdentity,
        public MetadataSubjectType $subjectType,
        public array $scopes,
        public array $values,
        public array $origins,
        public string $schemaHash,
        public string $sourceHash,
        public string $registryHash,
        public string $frameworkVersion,
        public array $dependencies = [],
    ) {
    }
}
```

---

# 71. Formato compilado

Se recomienda generar PHP nativo:

```php
<?php

return [
    'subject' => 'App\Http\Controllers\UserController::index',

    'scopes' => [
        'controller',
        'authorization',
        'validation',
    ],

    'values' => [
        'controller.scope' => 'request',

        'controller.middleware' => [
            ['name' => 'web', 'parameters' => []],
            ['name' => 'auth', 'parameters' => []],
        ],

        'authorization.policy' => [
            'ability' => 'viewAny',
            'subject' => 'App\Models\User',
        ],
    ],

    'schema_hash' => '...',
    'source_hash' => '...',
];
```

Esto permitirá utilizar OPcache.

---

# 72. Compilación por dominio

Los planes podrán agruparse:

```text
metadata/controllers.php
metadata/routes.php
metadata/components.php
metadata/entities.php
metadata/actions.php
metadata/parameters.php
```

O por módulo:

```text
metadata/Quantum.Controllers.php
metadata/Quantum.Routing.php
metadata/Quantum.ORM.php
```

---

# 73. Metadata Registry compilado

```php
interface CompiledMetadataRegistryInterface
{
    public function has(
        MetadataRequest $request
    ): bool;

    public function get(
        MetadataRequest $request
    ): CompiledMetadataPlan;

    public function register(
        CompiledMetadataPlan $plan
    ): void;

    public function clear(): void;
}
```

En producción será inmutable.

---

# 74. Short circuit compilado

Si un plan compilado:

* Coincide con el subject.
* Incluye todos los scopes solicitados.
* Tiene hashes válidos.
* No contiene condiciones runtime pendientes.

el pipeline podrá omitir todos los demás providers.

```text
CompiledMetadataProvider
    ↓
Complete plan found
    ↓
Return metadata
```

---

# 75. Plan compilado parcial

Algunas keys pueden depender del request.

Ejemplo:

```text
tenant.current
feature.enabled
locale.current
```

El plan podrá incluir:

```php
[
    'static_values' => [...],
    'dynamic_resolvers' => [
        'authorization.guard' => TenantGuardMetadataResolver::class,
    ],
]
```

Así se mantiene la mayor parte compilada.

---

# 76. Metadata dynamic resolver

```php
interface DynamicMetadataValueResolverInterface
{
    public function resolve(
        CompiledDynamicMetadataDefinition $definition,
        MetadataRequest $request
    ): mixed;
}
```

Estos resolvers deberán estar registrados y ser seguros.

No se almacenarán closures arbitrarias.

---

# 77. Metadata Cache

```php
interface MetadataCacheInterface
{
    public function has(
        MetadataCacheKey $key
    ): bool;

    public function get(
        MetadataCacheKey $key
    ): MetadataBag;

    public function put(
        MetadataCacheKey $key,
        MetadataBag $metadata
    ): void;

    public function forget(
        MetadataCacheKey $key
    ): void;

    public function clear(): void;
}
```

---

# 78. Niveles de caché

```text
L1 Request Cache
L2 Worker Memory Cache
L3 Compiled PHP Cache
L4 Optional Distributed Cache
```

## L1

Evita resolver varias veces el mismo subject dentro de una petición.

## L2

Mantiene metadata inmutable entre peticiones en FrankenPHP.

## L3

Utiliza archivos PHP y OPcache.

## L4

Opcional para despliegues distribuidos, aunque no será la primera opción.

---

# 79. Reglas para Worker Memory Cache

Solo podrá almacenar:

* `MetadataBag` inmutable.
* Schemas inmutables.
* Planes compilados.
* Definiciones sin objetos request-scoped.
* Valores serializables o readonly.

No podrá almacenar:

* Request.
* Usuario actual.
* Tenant actual.
* Sesión.
* Modelos.
* Servicios request-scoped.
* Closures.
* Recursos.
* Conexiones.

---

# 80. MetadataCacheKey

```php
final readonly class MetadataCacheKey
{
    public function __construct(
        public string $value
    ) {
    }

    public static function fromRequest(
        MetadataRequest $request
    ): self {
        return new self(
            hash('xxh128', implode('|', [
                $request->subject->type()->value,
                $request->subject->identity(),
                $request->scopes->signature(),
                $request->mode->value,
                MetadataContextHasher::hash(
                    $request->context
                ),
            ]))
        );
    }
}
```

Solo deberá incluir contexto que altere realmente la metadata.

---

# 81. Invalidación

La metadata deberá invalidarse cuando cambie:

* Clase.
* Método.
* Parámetro.
* Propiedad.
* Atributos.
* Archivo de configuración.
* Ruta.
* Grupo de rutas.
* Schema.
* Provider registry.
* Merge policy.
* Paquete.
* Convención.
* Versión del framework.
* Versión del módulo.
* Feature configuration.
* Archivo compilado.

---

# 82. Source hash

El hash podrá incluir:

```text
File path
File modification time
File content hash
Reflection signature
Attributes hash
Configuration hash
Route definition hash
Provider registry hash
Schema registry hash
Framework version
PHP version
```

En desarrollo podrá usarse timestamp.

En producción se recomienda hash de contenido durante build.

---

# 83. Dependency Graph

El compiler podrá construir un grafo:

```text
UserController@index metadata
    ├── UserController.php
    ├── BaseController.php
    ├── routes/web.php
    ├── config/controllers.php
    ├── VoltAuth package metadata
    └── controller schemas
```

Esto permitirá invalidación precisa.

---

# 84. Metadata Provider Registry

```php
interface MetadataProviderRegistryInterface
{
    public function add(
        string $provider,
        int $priority = 0
    ): void;

    public function remove(
        string $provider
    ): void;

    public function replace(
        string $provider,
        string $replacement
    ): void;

    public function forScope(
        string $scope
    ): array;

    public function all(): array;

    public function freeze(): void;
}
```

Los providers podrán limitarse a scopes específicos.

---

# 85. Provider descriptor

```php
final readonly class MetadataProviderDescriptor
{
    public function __construct(
        public string $provider,
        public int $priority,
        public array $scopes,
        public array $subjectTypes,
        public bool $compilable,
    ) {
    }
}
```

Esto permitirá omitir providers incompatibles sin instanciarlos.

---

# 86. MetadataProviderPipeline

```php
interface MetadataProviderPipelineInterface
{
    /**
     * @return iterable<MetadataFragment>
     */
    public function collect(
        MetadataRequest $request
    ): iterable;
}
```

Implementación conceptual:

```php
final class MetadataProviderPipeline implements
    MetadataProviderPipelineInterface
{
    public function __construct(
        private readonly MetadataProviderRegistryInterface $registry,
        private readonly ContainerInterface $container,
    ) {
    }

    public function collect(
        MetadataRequest $request
    ): iterable {
        foreach ($this->registry->matching($request) as $descriptor) {
            $provider = $this->container->get(
                $descriptor->provider
            );

            if (! $provider->supports($request)) {
                continue;
            }

            yield from $provider->provide($request);
        }
    }
}
```

---

# 87. Integración con Controllers

El módulo Controllers utilizará un adaptador.

```php
interface ControllerMetadataResolverInterface
{
    public function resolve(
        ResolvedController $controller,
        ControllerExecution $execution
    ): ControllerMetadata;
}
```

Implementación:

```php
final class ControllerMetadataResolver implements
    ControllerMetadataResolverInterface
{
    public function __construct(
        private readonly MetadataEngineInterface $metadata
    ) {
    }

    public function resolve(
        ResolvedController $controller,
        ControllerExecution $execution
    ): ControllerMetadata {
        $subject = new MethodMetadataSubject(
            class: $controller->className,
            method: $controller->methodName,
            parentSubject: new ClassMetadataSubject(
                $controller->className
            ),
            context: [
                'route' => $execution->context->route,
            ],
        );

        $bag = $this->metadata->resolve(
            new MetadataRequest(
                subject: $subject,
                scopes: MetadataScopeSet::from([
                    'controller',
                    'middleware',
                    'authorization',
                    'validation',
                    'cache',
                    'spa',
                ]),
                mode: MetadataResolutionMode::Auto,
                context: [
                    'execution' => $execution,
                ],
            )
        );

        return new ControllerMetadata($bag);
    }
}
```

---

# 88. ControllerMetadata

```php
final readonly class ControllerMetadata
{
    public function __construct(
        private MetadataBag $metadata
    ) {
    }

    public function middleware(): array
    {
        return $this->metadata->get(
            'controller.middleware',
            []
        );
    }

    public function interceptors(): array
    {
        return $this->metadata->get(
            'controller.interceptors',
            []
        );
    }

    public function scope(): ControllerScope
    {
        return $this->metadata->get(
            'controller.scope',
            ControllerScope::Request
        );
    }

    public function authorization(): array
    {
        return $this->metadata->get(
            'authorization.requirements',
            []
        );
    }

    public function validation(): array
    {
        return $this->metadata->get(
            'validation.rules',
            []
        );
    }

    public function resultNormalizer(): ?string
    {
        return $this->metadata->get(
            'controller.result_normalizer'
        );
    }

    public function raw(): MetadataBag
    {
        return $this->metadata;
    }
}
```

---

# 89. Metadata de controladores

Keys iniciales:

```text
controller.type
controller.scope
controller.middleware
controller.interceptors
controller.result_normalizer
controller.response_format
controller.transaction
controller.streaming
controller.timeout
controller.priority
controller.tags
controller.deprecated
```

---

# 90. Metadata de autorización

```text
authorization.required
authorization.guard
authorization.policy
authorization.ability
authorization.subject
authorization.roles
authorization.permissions
authorization.voters
authorization.failure_strategy
```

Estas keys serán consumidas por `AuthorizationStage`.

---

# 91. Metadata de validación

```text
validation.enabled
validation.rules
validation.groups
validation.stop_on_first_failure
validation.error_bag
validation.failure_strategy
validation.dto
```

Serán consumidas por `ValidationStage`.

---

# 92. Metadata de middleware

```text
middleware.before
middleware.controller
middleware.after
middleware.priority
middleware.excluded
middleware.groups
```

El schema deberá definir si las listas se anexan, sustituyen o eliminan.

---

# 93. Exclusión de middleware

Ejemplo:

```php
#[WithoutMiddleware('csrf')]
public function webhook(): ResponseInterface
{
}
```

El fragmento podrá representar una operación:

```php
MiddlewareMutation::remove('csrf')
```

No se recomienda representar eliminaciones mediante strings especiales.

---

# 94. Metadata Operations

Para merges complejos podrán utilizarse operaciones.

```php
interface MetadataOperationInterface
{
    public function apply(
        mixed $current,
        MetadataMergeContext $context
    ): mixed;
}
```

Ejemplos:

```text
AppendMetadataOperation
RemoveMetadataOperation
ReplaceMetadataOperation
ClearMetadataOperation
MergeMetadataOperation
```

---

# 95. Integración con Routing

Routing utilizará metadata para:

```text
routing.name
routing.methods
routing.host
routing.scheme
routing.middleware
routing.defaults
routing.requirements
routing.priority
routing.cache
routing.spa
routing.tenant
routing.locale
routing.security
```

El `RouteCompiler` podrá consumir planes compilados del mismo motor.

---

# 96. Integración con Actions

Keys:

```text
action.transaction
action.idempotency
action.audit
action.retry
action.timeout
action.queue
action.authorization
action.validation
action.events
```

Ejemplo:

```php
#[Transactional]
#[Idempotent(key: 'request.id')]
#[Auditable('user.created')]
final class CreateUserAction
{
}
```

---

# 97. Integración con Components

Keys:

```text
component.name
component.props
component.state
component.events
component.hydration
component.lazy
component.cache
component.island
component.client
component.security
```

El Component Runtime podrá usar un `ComponentMetadata` tipado.

---

# 98. Integración con SPA Runtime

Keys:

```text
spa.enabled
spa.navigation
spa.partial_reload
spa.preserve_state
spa.preserve_scroll
spa.prefetch
spa.transition
spa.history
spa.transport
spa.hydration
spa.client_component
```

Esta metadata podrá combinar:

* Ruta.
* Controlador.
* Método.
* Componente.
* Runtime.
* Request.

---

# 99. Integración con ORM

Keys:

```text
entity.table
entity.connection
entity.primary_key
entity.columns
entity.relations
entity.casts
entity.events
entity.scopes
entity.soft_delete
entity.timestamps
entity.repository
entity.identity_map
```

El ORM podrá utilizar el mismo motor, pero con schemas y providers propios.

---

# 100. Integración con Parameters

El `Parameter Resolution Engine` podrá solicitar metadata para cada parámetro.

Keys:

```text
parameter.source
parameter.name
parameter.required
parameter.default
parameter.coercion
parameter.validation
parameter.binding
parameter.model
parameter.dto
parameter.sensitive
parameter.container_id
```

Ejemplo:

```php
$metadata = $engine->resolve(
    new MetadataRequest(
        subject: new ParameterMetadataSubject(
            class: UserController::class,
            method: 'show',
            parameter: 'user',
        ),
        scopes: MetadataScopeSet::from([
            'parameter',
            'validation',
        ]),
        mode: MetadataResolutionMode::Auto,
    )
);
```

---

# 101. Integración con Events

Keys:

```text
event.name
event.listeners
event.async
event.priority
event.transactional
event.broadcast
event.serialize
event.retry
```

---

# 102. Integración con Commands y Jobs

```text
command.name
command.description
command.arguments
command.options
command.schedule

job.queue
job.connection
job.retry
job.timeout
job.backoff
job.unique
job.middleware
```

---

# 103. Integración con Serialization

```text
serialization.groups
serialization.format
serialization.depth
serialization.circular_reference
serialization.naming_strategy
serialization.date_format
serialization.visible
serialization.hidden
```

---

# 104. Integración con seguridad

```text
security.csrf
security.cors
security.rate_limit
security.signed
security.encryption
security.headers
security.tenant_isolation
security.audit
```

Las keys sensibles podrán declararse `final`.

---

# 105. Seguridad del motor

El motor deberá:

* Rechazar keys no autorizadas en modo estricto.
* Validar tipos.
* Bloquear clases dinámicas derivadas de input.
* No ejecutar closures arbitrarias desde configuración.
* No instanciar clases no registradas.
* Mantener aislamiento por tenant.
* No cachear metadata request-scoped globalmente.
* Ocultar valores sensibles.
* Congelar registries en producción.
* Validar planes compilados.
* Proteger metadata final.
* Registrar orígenes de cambios.
* Limitar providers externos.
* Evitar ciclos de resolución.

---

# 106. Ciclos de metadata

Un provider podría solicitar metadata durante su propia ejecución.

Ejemplo:

```text
Provider A
    → MetadataEngine
        → Provider A
```

El motor deberá mantener una pila de resolución.

```php
final class MetadataResolutionStack
{
    public function push(
        MetadataRequest $request
    ): void;

    public function pop(): void;

    public function contains(
        MetadataRequest $request
    ): bool;
}
```

Cuando se detecte un ciclo:

```text
CircularMetadataResolutionException
```

---

# 107. Reentrancia permitida

Se podrá solicitar metadata de otro subject.

Ejemplo:

```text
Controller metadata
    → requiere route metadata
```

Esto será válido mientras no forme un ciclo.

La pila deberá mostrar la cadena completa en debug.

---

# 108. Protección de valores sensibles

Schemas podrán declarar:

```php
sensitive: true
```

Ejemplos:

```text
security.secret
integration.api_key
authorization.token
database.credentials
```

La trace mostrará:

```text
[REDACTED]
```

---

# 109. Compatibilidad con FrankenPHP

El motor deberá:

* Ser stateless durante resolución.
* Mantener registries inmutables.
* Mantener caches de objetos inmutables.
* Separar caché global y request cache.
* No guardar `MetadataRequest` actual en propiedades compartidas.
* No guardar Request en fragmentos compilados.
* Limpiar stacks de resolución.
* No mantener referencias a ejecuciones finalizadas.
* Permitir reset de componentes request-scoped.
* Ser seguro para workers persistentes.

---

# 110. MetadataContext

La metadata dependiente de request deberá viajar explícitamente.

```php
final readonly class MetadataContext
{
    public function __construct(
        public ?string $environment = null,
        public ?string $routeName = null,
        public ?string $httpMethod = null,
        public ?string $tenantId = null,
        public ?string $runtime = null,
        public array $features = [],
        public array $attributes = [],
    ) {
    }
}
```

No se deberán consultar globals.

---

# 111. Observabilidad

Métricas:

```text
metadata.resolve.total
metadata.resolve.duration
metadata.resolve.failure
metadata.cache.hit
metadata.cache.miss
metadata.provider.duration
metadata.provider.fragments
metadata.merge.duration
metadata.validation.duration
metadata.compile.duration
metadata.plan.stale
metadata.conflict.total
```

Tags:

```text
subject.type
scope
provider
resolution.mode
cache.level
compiled
fragment.count
```

No se usarán identities de alta cardinalidad como tags globales.

---

# 112. Eventos

Eventos propuestos:

```text
MetadataResolutionStarting
MetadataCacheHit
MetadataCacheMiss
MetadataProviderStarting
MetadataProviderCompleted
MetadataFragmentCollected
MetadataNormalizationCompleted
MetadataMergeStarting
MetadataMerged
MetadataValidationStarting
MetadataValidated
MetadataResolved
MetadataResolutionFailed
MetadataCompilationStarting
MetadataCompiled
MetadataPlanInvalidated
```

Los eventos por fragmento se desactivarán en producción.

---

# 113. Excepciones

```text
MetadataException
MetadataResolutionException
MetadataProviderException
UnsupportedMetadataSubjectException
InvalidMetadataKeyException
UnknownMetadataKeyException
InvalidMetadataValueException
MissingMetadataException
InvalidMetadataAccessException
MetadataConflictException
FinalMetadataOverrideException
MetadataValidationException
MetadataNormalizationException
MetadataMergeException
MetadataCompilationException
StaleMetadataPlanException
NonCompilableMetadataException
CircularMetadataResolutionException
MetadataRegistryFrozenException
MetadataSchemaNotFoundException
ConflictingMetadataAttributesException
InvalidMetadataScopeException
```

---

# 114. Ejemplo de excepción de conflicto

```text
Metadata conflict detected.

Subject:
App\Http\Controllers\UserController::index

Key:
controller.scope

Current value:
request

Conflicting value:
singleton

Current origin:
#[ControllerScope(Request)] at UserController.php:14

Conflicting origin:
config/controllers.php

Merge strategy:
replace

Reason:
The key was marked as final by the controller attribute.
```

---

# 115. Debug Toolbar

Ejemplo:

```text
Metadata

Subject:
App\Http\Controllers\UserController::index

Mode:
compiled

Scopes:
controller, authorization, validation, spa

Cache:
L2 hit

Keys:
24

Providers skipped:
7

Dynamic values:
2

Compilation status:
valid
```

Detalle:

```text
controller.middleware
    Result:
        web
        auth
        verified

    Sources:
        config/controllers.php
        UserController attribute
        index method attribute
```

---

# 116. Comandos de consola

```bash
php volt metadata:inspect <subject>
```

```bash
php volt metadata:compile
```

```bash
php volt metadata:clear
```

```bash
php volt metadata:validate
```

```bash
php volt metadata:providers
```

```bash
php volt metadata:schemas
```

```bash
php volt metadata:trace <subject>
```

---

# 117. Metadata subject locator

La CLI podrá aceptar:

```text
controller:App\Http\Controllers\UserController@index
route:users.index
component:UserTable
entity:App\Models\User
action:App\Actions\CreateUserAction
```

Contrato:

```php
interface MetadataSubjectLocatorInterface
{
    public function locate(
        string $reference
    ): MetadataSubjectInterface;
}
```

---

# 118. Configuración

```php
return [
    'metadata' => [
        'engine' => MetadataEngine::class,

        'mode' => env('APP_ENV') === 'production'
            ? MetadataResolutionMode::Auto
            : MetadataResolutionMode::Dynamic,

        'compiled' => [
            'enabled' => env('APP_ENV') === 'production',
            'strict' => false,
            'path' => storage_path('framework/metadata'),
            'validate_hashes' => true,
            'partial_plans' => true,
        ],

        'cache' => [
            'request' => true,
            'worker' => true,
            'compiled' => true,
            'distributed' => false,
        ],

        'reflection' => [
            'enabled' => true,
            'cache' => true,
        ],

        'providers' => [
            'detect_conflicts' => env('APP_DEBUG'),
            'freeze_in_production' => true,
        ],

        'schemas' => [
            'unknown_keys' => env('APP_DEBUG')
                ? 'warn'
                : 'strict',
            'freeze_in_production' => true,
        ],

        'inheritance' => [
            'parent_classes' => true,
            'parent_methods' => true,
            'traits' => true,
            'interfaces' => false,
        ],

        'debug' => [
            'trace' => env('APP_DEBUG'),
            'include_origins' => env('APP_DEBUG'),
            'include_rejected_fragments' => env('APP_DEBUG'),
        ],

        'security' => [
            'allow_runtime_overrides' => true,
            'allow_arbitrary_closures' => false,
            'redact_sensitive_values' => true,
        ],
    ],
];
```

---

# 119. Registro en el Container

```php
$container->singleton(
    MetadataEngineInterface::class,
    MetadataEngine::class
);

$container->singleton(
    MetadataProviderRegistryInterface::class,
    MetadataProviderRegistry::class
);

$container->singleton(
    MetadataSchemaRegistryInterface::class,
    MetadataSchemaRegistry::class
);

$container->singleton(
    MetadataProviderPipelineInterface::class,
    MetadataProviderPipeline::class
);

$container->singleton(
    MetadataNormalizerInterface::class,
    MetadataNormalizer::class
);

$container->singleton(
    MetadataMergerInterface::class,
    MetadataMerger::class
);

$container->singleton(
    MetadataValidatorInterface::class,
    MetadataValidator::class
);

$container->singleton(
    MetadataCompilerInterface::class,
    MetadataCompiler::class
);

$container->singleton(
    MetadataCacheInterface::class,
    LayeredMetadataCache::class
);
```

Todos estos servicios deberán ser seguros para workers persistentes.

---

# 120. Bootstrapping

Durante `register`:

* Registrar contratos.
* Registrar engine.
* Registrar cache.
* Registrar registries.
* Registrar providers base.
* Registrar normalizers.
* Registrar validators.
* Registrar compiler.
* Registrar configuración.

Durante `boot`:

* Registrar schemas del núcleo.
* Incorporar providers de módulos.
* Incorporar schemas de módulos.
* Ordenar providers.
* Validar keys.
* Detectar prioridades conflictivas.
* Cargar planes compilados.
* Validar hashes.
* Congelar registries en producción.

---

# 121. Registro desde módulos

Ejemplo del módulo Controllers:

```php
public function boot(
    MetadataSchemaRegistryInterface $schemas,
    MetadataProviderRegistryInterface $providers
): void {
    $schemas->register(
        ControllerMetadataSchemas::scope()
    );

    $schemas->register(
        ControllerMetadataSchemas::middleware()
    );

    $providers->add(
        ControllerConventionMetadataProvider::class,
        priority: 500
    );
}
```

Ejemplo de Volt Auth:

```php
$schemas->register(
    AuthorizationMetadataSchemas::policy()
);

$providers->add(
    AuthorizationAttributeMetadataProvider::class,
    priority: 760
);
```

---

# 122. Registry freezing

Después del boot:

```php
$providers->freeze();
$schemas->freeze();
$normalizers->freeze();
$validators->freeze();
```

Un intento posterior de modificación lanzará:

```text
MetadataRegistryFrozenException
```

En desarrollo podrá permitirse recarga.

---

# 123. Estructura de directorios

```text
src/
├── Support/
│   └── Metadata/
│       ├── MetadataEngine.php
│       ├── MetadataBag.php
│       ├── MetadataRequest.php
│       ├── MetadataScope.php
│       ├── MetadataScopeSet.php
│       ├── MetadataSubjectType.php
│       ├── MetadataResolutionMode.php
│       │
│       ├── Contracts/
│       │   ├── MetadataEngineInterface.php
│       │   ├── MetadataSubjectInterface.php
│       │   ├── MetadataProviderInterface.php
│       │   ├── MetadataProviderPipelineInterface.php
│       │   ├── MetadataProviderRegistryInterface.php
│       │   ├── MetadataSchemaRegistryInterface.php
│       │   ├── MetadataNormalizerInterface.php
│       │   ├── MetadataValueNormalizerInterface.php
│       │   ├── MetadataMergerInterface.php
│       │   ├── MetadataValidatorInterface.php
│       │   ├── MetadataRuleValidatorInterface.php
│       │   ├── MetadataCompilerInterface.php
│       │   ├── MetadataCacheInterface.php
│       │   ├── MetadataAttributeInterface.php
│       │   ├── MetadataAttributeMapperInterface.php
│       │   ├── MetadataConditionInterface.php
│       │   ├── MetadataOperationInterface.php
│       │   ├── MetadataViewFactoryInterface.php
│       │   └── DynamicMetadataValueResolverInterface.php
│       │
│       ├── Subjects/
│       │   ├── ApplicationMetadataSubject.php
│       │   ├── ModuleMetadataSubject.php
│       │   ├── RouteMetadataSubject.php
│       │   ├── ClassMetadataSubject.php
│       │   ├── MethodMetadataSubject.php
│       │   ├── ParameterMetadataSubject.php
│       │   ├── PropertyMetadataSubject.php
│       │   ├── ActionMetadataSubject.php
│       │   ├── ComponentMetadataSubject.php
│       │   ├── EntityMetadataSubject.php
│       │   └── CustomMetadataSubject.php
│       │
│       ├── Definitions/
│       │   ├── MetadataFragment.php
│       │   ├── MetadataOrigin.php
│       │   ├── MetadataOriginType.php
│       │   ├── MetadataMergeHint.php
│       │   ├── MetadataKey.php
│       │   ├── MetadataSchema.php
│       │   ├── MetadataValueType.php
│       │   ├── MetadataMergeStrategy.php
│       │   ├── MetadataHierarchy.php
│       │   ├── MetadataContext.php
│       │   ├── CompiledMetadataPlan.php
│       │   ├── CompiledDynamicMetadataDefinition.php
│       │   ├── MetadataProviderDescriptor.php
│       │   ├── MetadataTrace.php
│       │   ├── MetadataTraceEntry.php
│       │   ├── MetadataCacheKey.php
│       │   └── MetadataDependencyGraph.php
│       │
│       ├── Providers/
│       │   ├── CompiledMetadataProvider.php
│       │   ├── RuntimeMetadataProvider.php
│       │   ├── ExplicitDefinitionMetadataProvider.php
│       │   ├── RouteMetadataProvider.php
│       │   ├── ConfigurationMetadataProvider.php
│       │   ├── AttributeMetadataProvider.php
│       │   ├── ReflectionMetadataProvider.php
│       │   ├── ConventionMetadataProvider.php
│       │   ├── PackageMetadataProvider.php
│       │   └── DefaultMetadataProvider.php
│       │
│       ├── Registry/
│       │   ├── MetadataProviderRegistry.php
│       │   ├── MetadataSchemaRegistry.php
│       │   ├── MetadataNormalizerRegistry.php
│       │   ├── MetadataValidatorRegistry.php
│       │   ├── MetadataAttributeMapperRegistry.php
│       │   ├── DynamicMetadataResolverRegistry.php
│       │   ├── CompiledMetadataRegistry.php
│       │   └── FreezableMetadataRegistry.php
│       │
│       ├── Pipeline/
│       │   ├── MetadataProviderPipeline.php
│       │   ├── MetadataProviderMatcher.php
│       │   ├── MetadataFragmentCollector.php
│       │   └── MetadataResolutionStack.php
│       │
│       ├── Normalization/
│       │   ├── MetadataNormalizer.php
│       │   ├── EnumMetadataNormalizer.php
│       │   ├── ListMetadataNormalizer.php
│       │   ├── MapMetadataNormalizer.php
│       │   ├── BooleanMetadataNormalizer.php
│       │   ├── ClassNameMetadataNormalizer.php
│       │   ├── MiddlewareMetadataNormalizer.php
│       │   ├── DurationMetadataNormalizer.php
│       │   └── DefinitionMetadataNormalizer.php
│       │
│       ├── Merge/
│       │   ├── MetadataMerger.php
│       │   ├── MetadataMergeContext.php
│       │   ├── MetadataMergeStrategyResolver.php
│       │   ├── ReplaceMetadataMerger.php
│       │   ├── AppendMetadataMerger.php
│       │   ├── UniqueAppendMetadataMerger.php
│       │   ├── DeepMetadataMerger.php
│       │   └── CustomMetadataMerger.php
│       │
│       ├── Operations/
│       │   ├── AppendMetadataOperation.php
│       │   ├── RemoveMetadataOperation.php
│       │   ├── ReplaceMetadataOperation.php
│       │   ├── ClearMetadataOperation.php
│       │   └── MergeMetadataOperation.php
│       │
│       ├── Conditions/
│       │   ├── EnvironmentCondition.php
│       │   ├── HttpMethodCondition.php
│       │   ├── RouteNameCondition.php
│       │   ├── TenantCondition.php
│       │   ├── FeatureFlagCondition.php
│       │   ├── RuntimeCondition.php
│       │   └── PackageInstalledCondition.php
│       │
│       ├── Validation/
│       │   ├── MetadataValidator.php
│       │   ├── MetadataValidationContext.php
│       │   ├── MetadataTypeValidator.php
│       │   ├── MetadataRequiredValidator.php
│       │   ├── MetadataScopeValidator.php
│       │   ├── MetadataClassExistsValidator.php
│       │   ├── MetadataMutualExclusionValidator.php
│       │   ├── MetadataDependencyValidator.php
│       │   ├── MetadataSecurityValidator.php
│       │   └── MetadataCompilabilityValidator.php
│       │
│       ├── Cache/
│       │   ├── LayeredMetadataCache.php
│       │   ├── RequestMetadataCache.php
│       │   ├── WorkerMetadataCache.php
│       │   ├── CompiledMetadataCache.php
│       │   ├── MetadataContextHasher.php
│       │   └── MetadataCacheInvalidator.php
│       │
│       ├── Compiler/
│       │   ├── MetadataCompiler.php
│       │   ├── MetadataPlanBuilder.php
│       │   ├── MetadataPlanWriter.php
│       │   ├── MetadataPlanLoader.php
│       │   ├── MetadataSourceHasher.php
│       │   ├── MetadataSchemaHasher.php
│       │   ├── MetadataRegistryHasher.php
│       │   └── MetadataDependencyGraphBuilder.php
│       │
│       ├── Attributes/
│       │   ├── MetadataInheritance.php
│       │   ├── MetadataFinal.php
│       │   └── MetadataOverride.php
│       │
│       ├── Events/
│       │   ├── MetadataResolutionStarting.php
│       │   ├── MetadataCacheHit.php
│       │   ├── MetadataCacheMiss.php
│       │   ├── MetadataProviderStarting.php
│       │   ├── MetadataProviderCompleted.php
│       │   ├── MetadataFragmentCollected.php
│       │   ├── MetadataMerged.php
│       │   ├── MetadataValidated.php
│       │   ├── MetadataResolved.php
│       │   ├── MetadataResolutionFailed.php
│       │   ├── MetadataCompilationStarting.php
│       │   ├── MetadataCompiled.php
│       │   └── MetadataPlanInvalidated.php
│       │
│       ├── Exceptions/
│       │   ├── MetadataException.php
│       │   ├── MetadataResolutionException.php
│       │   ├── MetadataProviderException.php
│       │   ├── UnsupportedMetadataSubjectException.php
│       │   ├── InvalidMetadataKeyException.php
│       │   ├── UnknownMetadataKeyException.php
│       │   ├── InvalidMetadataValueException.php
│       │   ├── MissingMetadataException.php
│       │   ├── InvalidMetadataAccessException.php
│       │   ├── MetadataConflictException.php
│       │   ├── FinalMetadataOverrideException.php
│       │   ├── MetadataValidationException.php
│       │   ├── MetadataNormalizationException.php
│       │   ├── MetadataMergeException.php
│       │   ├── MetadataCompilationException.php
│       │   ├── StaleMetadataPlanException.php
│       │   ├── NonCompilableMetadataException.php
│       │   ├── CircularMetadataResolutionException.php
│       │   ├── MetadataRegistryFrozenException.php
│       │   ├── MetadataSchemaNotFoundException.php
│       │   ├── ConflictingMetadataAttributesException.php
│       │   └── InvalidMetadataScopeException.php
│       │
│       └── Testing/
│           ├── FakeMetadataEngine.php
│           ├── FakeMetadataProvider.php
│           ├── InMemoryMetadataCache.php
│           ├── MetadataTestBuilder.php
│           ├── MetadataBagAssertions.php
│           ├── MetadataTraceAssertions.php
│           └── MetadataProviderRecorder.php
│
└── Quantum/
    └── Controllers/
        └── Metadata/
            ├── ControllerMetadata.php
            ├── ControllerMetadataResolver.php
            ├── ControllerMetadataSchemas.php
            ├── ControllerConventionMetadataProvider.php
            └── ControllerMetadataViewFactory.php
```

---

# 124. Implementación mínima V1

La primera versión deberá incluir:

* `MetadataEngineInterface`.
* `MetadataEngine`.
* `MetadataRequest`.
* `MetadataSubjectInterface`.
* Sujetos de clase, método y parámetro.
* `MetadataScope`.
* `MetadataFragment`.
* `MetadataOrigin`.
* `MetadataBag`.
* `MetadataProviderInterface`.
* `MetadataProviderPipeline`.
* `MetadataProviderRegistry`.
* `MetadataSchema`.
* `MetadataSchemaRegistry`.
* `MetadataNormalizer`.
* `MetadataMerger`.
* `MetadataValidator`.
* Attribute provider.
* Reflection provider.
* Configuration provider.
* Explicit definition provider.
* Convention provider.
* Default provider.
* Request cache.
* Worker cache inmutable.
* Trace en modo debug.
* Controller metadata adapter.
* Schemas iniciales de Controllers.
* Pruebas de herencia.
* Pruebas de precedencia.
* Pruebas de aislamiento entre requests.

Podrán posponerse:

* Cache distribuida.
* Planes compilados parciales.
* Conditions complejas.
* Dependency graph completo.
* Attribute mappers externos.
* Metadata de ORM.
* Metadata de Components.
* Metadata de Events.
* Custom merge strategies avanzadas.
* CLI visual.
* Recarga incremental.
* Integración con IDE.

---

# 125. Flujo completo para un controlador

Controlador:

```php
#[Middleware('web')]
#[Middleware('auth')]
#[ControllerScope(ControllerScope::Request)]
final class UserController
{
    #[Middleware('verified')]
    #[Authorize('viewAny', User::class)]
    #[Cache(ttl: 60)]
    public function index(): array
    {
        return [];
    }
}
```

Ruta:

```php
Route::get('/users', [
    UserController::class,
    'index',
])
    ->name('users.index')
    ->middleware('tenant')
    ->metadata([
        'spa.preserve_state' => true,
    ]);
```

Configuración:

```php
return [
    UserController::class => [
        'controller.middleware' => ['metrics'],
    ],
];
```

Flujo:

```text
MetadataRequest
    ↓
Subject: UserController::index
    ↓
Scopes:
controller, middleware, authorization, cache, spa
    ↓
ConfigurationMetadataProvider
    → metrics
    ↓
RouteMetadataProvider
    → tenant
    → spa.preserve_state = true
    ↓
AttributeMetadataProvider
    → web
    → auth
    → verified
    → authorization policy
    → cache.ttl = 60
    ↓
ReflectionMetadataProvider
    → public method
    → return type array
    ↓
ConventionMetadataProvider
    → operation collection
    ↓
MetadataMerger
    ↓
MetadataValidator
    ↓
ControllerMetadata
```

Resultado conceptual:

```php
[
    'controller.scope' => ControllerScope::Request,

    'controller.middleware' => [
        new MiddlewareDefinition('metrics'),
        new MiddlewareDefinition('tenant'),
        new MiddlewareDefinition('web'),
        new MiddlewareDefinition('auth'),
        new MiddlewareDefinition('verified'),
    ],

    'authorization.policy' => new AuthorizationDefinition(
        ability: 'viewAny',
        subject: User::class,
    ),

    'cache.ttl' => 60,

    'spa.preserve_state' => true,

    'controller.operation' => 'collection',
]
```

---

# 126. Pruebas unitarias

Casos mínimos:

* Resuelve metadata de clase.
* Resuelve metadata de método.
* Resuelve metadata de parámetro.
* Combina metadata de padre e hijo.
* Combina atributos repetibles.
* Aplica configuración.
* Aplica metadata de ruta.
* Aplica convenciones.
* Aplica defaults.
* Respeta precedencia.
* Respeta schemas.
* Normaliza enums.
* Normaliza listas.
* Detecta keys desconocidas.
* Detecta tipos inválidos.
* Detecta conflictos.
* Respeta metadata final.
* Genera trace.
* Oculta valores sensibles.
* Cachea resultados inmutables.
* No cachea runtime metadata globalmente.
* Detecta ciclos.
* Congela registries.
* Mantiene equivalencia dinámica y compilada.

---

# 127. Pruebas de integración

* Routing → Metadata Engine.
* Controllers → ControllerMetadata.
* Parameters → Parameter metadata.
* Authorization → policy metadata.
* Validation → rule metadata.
* Middleware → merged middleware.
* Actions → transaction metadata.
* SPA → navigation metadata.
* FrankenPHP → worker cache.
* Route compilation → compiled metadata.
* Packages → custom providers.
* Config cache → metadata cache.
* Debug toolbar → metadata trace.

---

# 128. Prueba de precedencia

```php
public function test_method_metadata_overrides_class_metadata(): void
{
    $metadata = $this->resolveMetadata(
        TestController::class,
        'index'
    );

    expect($metadata->get('cache.ttl'))
        ->toBe(10);
}
```

---

# 129. Prueba de middleware acumulativo

```php
public function test_middleware_is_merged_from_all_levels(): void
{
    $metadata = $this->resolveMetadata(
        TestController::class,
        'index'
    );

    expect(
        array_map(
            fn (MiddlewareDefinition $middleware) =>
                $middleware->name,
            $metadata->get('controller.middleware')
        )
    )->toBe([
        'web',
        'auth',
        'verified',
    ]);
}
```

---

# 130. Prueba de aislamiento en FrankenPHP

```php
public function test_runtime_metadata_is_not_shared_between_requests(): void
{
    $first = $this->resolveForRequest([
        'tenant' => 'tenant-a',
    ]);

    $second = $this->resolveForRequest([
        'tenant' => 'tenant-b',
    ]);

    expect($first->get('tenant.current'))
        ->toBe('tenant-a');

    expect($second->get('tenant.current'))
        ->toBe('tenant-b');
}
```

---

# 131. Benchmarks

Escenarios:

```text
Class metadata without attributes
Class with ten attributes
Method with inherited metadata
Controller + route metadata
Parameter metadata
Twenty providers registered
Five matching providers
Dynamic resolution
Request cache hit
Worker cache hit
Compiled plan hit
Compiled partial plan
Metadata trace enabled
Metadata trace disabled
```

Métricas:

* Tiempo total.
* Tiempo por provider.
* Fragmentos generados.
* Reflection calls.
* Objetos temporales.
* Memoria.
* Cache hit ratio.
* Merge duration.
* Validation duration.
* Resoluciones por segundo.
* Dynamic vs compiled.

---

# 132. Decisiones arquitectónicas

## ADR-META-001

**Decisión:** VoltStack utilizará un motor de metadata transversal.

**Razón:** Evita que cada módulo implemente Reflection, atributos, merge y cache de forma independiente.

---

## ADR-META-002

**Decisión:** Los providers devolverán `MetadataFragment`.

**Razón:** Permite combinar fuentes conservando prioridad, origen y estrategia.

---

## ADR-META-003

**Decisión:** El resultado será un `MetadataBag` inmutable.

**Razón:** Facilita caché, concurrencia, seguridad y procesos persistentes.

---

## ADR-META-004

**Decisión:** Cada key deberá tener un schema.

**Razón:** Evita metadata sin contrato y permite validación y compilación.

---

## ADR-META-005

**Decisión:** Las reglas de merge pertenecerán al schema.

**Razón:** Diferentes tipos de metadata requieren semánticas distintas.

---

## ADR-META-006

**Decisión:** Los módulos expondrán vistas tipadas.

**Razón:** Mantiene el motor genérico sin sacrificar ergonomía.

---

## ADR-META-007

**Decisión:** Los providers podrán registrarse por paquetes.

**Razón:** Permite ampliar el framework sin modificar el núcleo.

---

## ADR-META-008

**Decisión:** La metadata compilada tendrá prioridad.

**Razón:** Reduce Reflection y trabajo repetitivo en producción.

---

## ADR-META-009

**Decisión:** La metadata runtime no se almacenará en cache global.

**Razón:** Evita contaminación entre peticiones y tenants.

---

## ADR-META-010

**Decisión:** La herencia será controlada por schema.

**Razón:** No todas las keys deben propagarse entre niveles.

---

## ADR-META-011

**Decisión:** Se conservará el origen de cada fragmento.

**Razón:** Facilita debugging, auditoría y resolución de conflictos.

---

## ADR-META-012

**Decisión:** Los registries se congelarán en producción.

**Razón:** Mejora determinismo, seguridad y compatibilidad con concurrencia.

---

## ADR-META-013

**Decisión:** Las closures arbitrarias no serán compilables.

**Razón:** Evita serialización insegura y comportamiento no determinista.

---

## ADR-META-014

**Decisión:** La compilación deberá mantener equivalencia semántica.

**Razón:** El comportamiento no debe variar entre desarrollo y producción.

---

## ADR-META-015

**Decisión:** ControllerMetadataResolver será un adaptador del motor general.

**Razón:** Controllers no deberá mantener una infraestructura de metadata independiente.

---

# 133. Criterios de aceptación

El motor se considerará correctamente implementado cuando:

* Resuelva metadata para distintos tipos de subjects.
* Permita scopes funcionales.
* Recolecte metadata desde múltiples providers.
* Conserve el origen de cada fragmento.
* Normalice valores.
* Combine metadata según schemas.
* Valide tipos y reglas.
* Produzca un `MetadataBag` inmutable.
* Permita vistas tipadas.
* Soporte metadata de clase, método y parámetro.
* Soporte herencia configurable.
* Detecte conflictos.
* Proteja metadata final.
* Permita providers de paquetes.
* Permita schemas de paquetes.
* Genere trazabilidad en debug.
* Oculte valores sensibles.
* Utilice cache por request.
* Utilice cache segura por worker.
* Permita planes compilados.
* Invalide planes obsoletos.
* Detecte ciclos.
* Sea compatible con FrankenPHP.
* Integre Controllers sin duplicar Reflection.
* Permita que Routing, Actions, Components y ORM utilicen la misma infraestructura.
* Mantenga equivalencia entre resolución dinámica y compilada.

---

# 134. Conclusión

El `Metadata Engine` será uno de los pilares internos de VoltStack.

Su propósito no será únicamente leer atributos PHP, sino ofrecer una infraestructura completa para describir comportamiento de forma declarativa, tipada, extensible y compilable.

La combinación de:

* sujetos de metadata;
* scopes;
* providers;
* fragmentos;
* schemas;
* normalización;
* reglas de merge;
* validación;
* trazabilidad;
* caché inmutable;
* planes compilados;
* vistas tipadas;

permitirá que todos los módulos del framework compartan el mismo lenguaje de configuración y descubrimiento.

Controllers utilizará este motor para middleware, autorización, validación, interceptores y normalización de resultados, pero la misma infraestructura podrá emplearse en Routing, Actions, Components, SPA Runtime, ORM, Events, Commands y cualquier paquete futuro.

Esta arquitectura reducirá Reflection duplicada, eliminará sistemas de configuración incompatibles y permitirá que VoltStack compile gran parte de su comportamiento antes de iniciar la aplicación.

El siguiente paso dentro del sistema de controladores será utilizar esta metadata para definir el sistema de middleware específico de controladores, interceptores y etapas de ejecución.
