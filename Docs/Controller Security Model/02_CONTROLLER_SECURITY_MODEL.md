# CONTROLLER_SECURITY_MODEL_PART_02.md

## Runtime & Controller Security

**Versión:** 1.0
**Estado:** Draft arquitectónico
**Módulo:** `VoltStack\Quantum\Controllers\Security\Runtime`
**Ámbito:** Seguridad de resolución, exposición, parámetros, binding, autorización, interceptores, invocación, lifecycle, subrequests y Workers persistentes
**Dependencias:** `CONTROLLER_SECURITY_MODEL.md — PART 01`

---

# 1. Introducción

Esta segunda parte define los controles de seguridad aplicados durante la ejecución real de un controlador.

La superficie protegida comprende:

```text
Route Match
    ↓
Controller Resolution
    ↓
Action Exposure Validation
    ↓
Security Metadata
    ↓
Pre-Binding Authorization
    ↓
Parameter Resolution
    ↓
DTO Hydration
    ↓
Model Binding
    ↓
Resource Authorization
    ↓
Interceptor Pipeline
    ↓
Invocation Guard
    ↓
Controller Invocation
    ↓
Lifecycle and Cleanup
```

El objetivo es impedir que datos no confiables controlen:

* qué clase se instancia;
* qué método se ejecuta;
* qué servicios se inyectan;
* qué modelos se cargan;
* qué tenant se utiliza;
* qué política se evalúa;
* qué interceptores se omiten;
* qué estado permanece en un Worker.

---

# 2. Objetivos de seguridad del runtime

El runtime deberá garantizar:

* targets de controlador deterministas;
* acciones explícitamente expuestas;
* métodos invocables validados;
* parámetros con fuente conocida;
* coerción estricta;
* hydration limitada;
* model binding tenant-aware;
* autorización antes de efectos secundarios;
* interceptores obligatorios no eludibles;
* invocación única;
* cleanup obligatorio;
* aislamiento completo entre ejecuciones.

---

# 3. Principio de target cerrado

El cliente nunca seleccionará directamente:

* clase;
* método;
* service ID;
* alias;
* strategy;
* resolver;
* interceptor;
* policy.

El target siempre deberá provenir de una definición confiable.

```text
Request path
    ↓
Trusted Route Definition
    ↓
Validated Controller Target
```

---

# 4. ControllerTarget

```php
final readonly class ControllerTarget
{
    public function __construct(
        public ControllerTargetType $type,
        public string $identifier,
        public ?string $method,
        public bool $exposed,
        public string $source,
        public string $signature,
    ) {
    }
}
```

---

# 5. ControllerTargetType

```php
enum ControllerTargetType: string
{
    case ControllerMethod = 'controller_method';
    case InvokableController = 'invokable_controller';
    case Action = 'action';
    case ServiceMethod = 'service_method';
    case Closure = 'closure';
    case Page = 'page';
    case Component = 'component';
    case Resource = 'resource';
}
```

---

# 6. Tipos restringidos

Por defecto se restringirán:

* métodos estáticos;
* callables arbitrarios;
* funciones globales;
* clases construidas desde strings de request;
* métodos mágicos;
* closures serializadas;
* targets no registrados.

---

# 7. Controller Resolution Security

El `ControllerResolver` deberá operar sobre un target ya validado.

```php
interface SecureControllerResolverInterface
{
    public function resolve(
        ControllerTarget $target,
        ControllerResolutionSecurityContext $security
    ): ResolvedController;
}
```

---

# 8. ControllerResolutionSecurityContext

```php
final readonly class ControllerResolutionSecurityContext
{
    public function __construct(
        public string $buildId,
        public string $routeSignature,
        public ControllerSecurityContext $security,
        public ControllerExposureRegistry $exposureRegistry,
        public ControllerResolutionPolicy $policy,
    ) {
    }
}
```

---

# 9. Resolución por class string

Solo se aceptarán class strings que:

* existan;
* pertenezcan a namespaces permitidos;
* estén registradas;
* cumplan el contrato requerido;
* no sean abstractas;
* no sean traits;
* no sean enums;
* no estén bloqueadas.

---

# 10. Namespace allowlist

```php
final readonly class ControllerNamespacePolicy
{
    public function __construct(
        public array $allowedPrefixes,
        public array $deniedClasses,
    ) {
    }
}
```

Ejemplo:

```php
[
    'App\\Http\\Controllers\\',
    'App\\Actions\\',
]
```

---

# 11. Denylist secundaria

La allowlist será la defensa principal.

La denylist podrá bloquear:

* controladores vulnerables;
* clases deprecated;
* targets revocados;
* paquetes comprometidos.

---

# 12. Controller Resolution Registry

```php
interface ControllerResolutionRegistryInterface
{
    public function register(
        ControllerResolutionDefinition $definition
    ): void;

    public function resolve(
        string $identifier
    ): ControllerResolutionDefinition;

    public function freeze(): void;
}
```

---

# 13. Resolución de aliases

Los aliases deberán:

* estar registrados;
* ser inmutables después de `freeze()`;
* apuntar a un target validado;
* no poder encadenarse indefinidamente;
* detectar ciclos;
* no aceptar placeholders del cliente.

---

# 14. Alias cycles

Ejemplo inválido:

```text
admin.users → users.manage
users.manage → admin.users
```

El registry deberá rechazarlo durante boot o compilación.

---

# 15. Service controller security

Un controlador resuelto desde el contenedor deberá:

* utilizar un service ID registrado;
* cumplir el scope permitido;
* no provenir del request;
* no ser reemplazado por binding request-scoped inseguro;
* declarar dependencies válidas.

---

# 16. Controller scopes

```php
enum ControllerScope: string
{
    case Execution = 'execution';
    case Request = 'request';
    case Worker = 'worker';
    case Singleton = 'singleton';
}
```

---

# 17. Scope por defecto

El scope recomendado será:

```text
Execution
```

Esto reduce contaminación entre requests.

---

# 18. Worker-scoped controllers

Solo deberán permitirse cuando:

* sean inmutables;
* no almacenen principal;
* no almacenen tenant;
* no almacenen request;
* no almacenen resultados;
* superen pruebas de Worker safety.

---

# 19. Singleton controllers

Deberán evitarse salvo que:

* sean stateless;
* sus dependencias también sean seguras;
* estén auditados;
* exista justificación explícita.

---

# 20. Closure security

Las closures solo podrán utilizarse cuando:

* estén declaradas en route definitions confiables;
* no sean serializadas desde input;
* no capturen objetos request-scoped persistentes;
* no se usen como artefactos portables sin adaptación.

---

# 21. Static method security

Los métodos estáticos estarán deshabilitados por defecto.

Cuando se habiliten deberán:

* estar expuestos explícitamente;
* pertenecer a una clase permitida;
* ser públicos;
* no depender de estado global mutable;
* cumplir las mismas políticas que otros targets.

---

# 22. Action Exposure Model

Un método público no será automáticamente una acción.

```text
Public method
    ≠
Exposed controller action
```

---

# 23. ControllerExposureRegistry

```php
interface ControllerExposureRegistryInterface
{
    public function expose(
        ExposedControllerAction $action
    ): void;

    public function isExposed(
        string $class,
        string $method
    ): bool;

    public function freeze(): void;
}
```

---

# 24. ExposedControllerAction

```php
final readonly class ExposedControllerAction
{
    public function __construct(
        public string $class,
        public string $method,
        public array $routeSignatures,
        public ControllerExposurePolicy $policy,
        public string $fingerprint,
    ) {
    }
}
```

---

# 25. Fuentes de exposición

La exposición podrá originarse en:

* route definition;
* configuración explícita;
* metadata validada;
* attribute compilado;
* registro de módulo.

Nunca en convenciones ambiguas como “todos los métodos públicos”.

---

# 26. Exposición mediante attributes

Ejemplo conceptual:

```php
#[ControllerAction]
public function show(Order $order): Response
{
}
```

El attribute deberá ser procesado por el sistema de Attributes y convertido en metadata validada.

---

# 27. Restricciones de método

Un action method deberá ser:

* público;
* no abstracto;
* no constructor;
* no destructor;
* no método mágico;
* no variadic inseguro;
* no generado dinámicamente;
* compatible con el Invocation Strategy.

---

# 28. Métodos mágicos prohibidos

No podrán exponerse:

```text
__construct
__destruct
__call
__callStatic
__get
__set
__invoke
```

`__invoke` será una excepción únicamente para invokable controllers registrados.

---

# 29. Métodos heredados

Por defecto, un método heredado no se considerará expuesto solo porque la subclase esté registrada.

La exposición deberá indicar:

* declaring class;
* effective class;
* inheritance policy.

---

# 30. Traits

Los métodos introducidos por traits deberán someterse a las mismas reglas.

La exposición no se heredará implícitamente desde el trait.

---

# 31. Interfaces

Una interface podrá definir un contrato de action, pero no deberá exponer implementaciones automáticamente.

---

# 32. ControllerExposurePolicy

```php
final readonly class ControllerExposurePolicy
{
    public function __construct(
        public bool $explicitOnly,
        public bool $allowInherited,
        public bool $allowStatic,
        public bool $allowInvokable,
        public array $requiredPolicies,
    ) {
    }
}
```

---

# 33. Exposure fingerprint

La exposición deberá formar parte del fingerprint del artifact.

Un cambio en:

* método;
* route;
* policy;
* visibility;
* metadata;

invalidará el plan compilado.

---

# 34. Method Visibility Guard

```php
interface ControllerMethodVisibilityGuardInterface
{
    public function assertExposed(
        ResolvedController $controller,
        ControllerTarget $target
    ): void;
}
```

---

# 35. Reflection usage

La reflexión podrá utilizarse:

* durante boot;
* desarrollo;
* compilación;
* validación.

No deberá utilizarse repetidamente en la ruta caliente de producción.

---

# 36. Compiled exposure plan

```php
final readonly class CompiledControllerExposurePlan
{
    public function __construct(
        public string $controllerClass,
        public string $method,
        public bool $public,
        public bool $static,
        public bool $invokable,
        public array $allowedRouteSignatures,
        public array $requiredPolicies,
        public string $fingerprint,
    ) {
    }
}
```

---

# 37. Route-to-action binding

Una acción podrá restringirse a rutas específicas.

Esto evita que un alias o una ruta nueva reutilice accidentalmente una acción con políticas diferentes.

---

# 38. Route signature validation

Antes de invocar:

```text
execution.route_signature
    must belong to
action.allowed_route_signatures
```

---

# 39. Internal actions

Podrá existir clasificación:

```php
enum ControllerActionExposure: string
{
    case Public = 'public';
    case Authenticated = 'authenticated';
    case Internal = 'internal';
    case System = 'system';
    case Disabled = 'disabled';
}
```

---

# 40. Internal action security

Una acción `Internal` deberá requerir:

* red interna confiable, o
* service principal, o
* signed internal request, o
* ejecución directa server-side.

No bastará un header libre como:

```text
X-Internal: true
```

---

# 41. System actions

Solo podrán invocarse desde contextos privilegiados del framework.

Ejemplos:

* health internals;
* maintenance operations;
* build lifecycle hooks.

---

# 42. Disabled actions

Una acción deshabilitada deberá permanecer rechazada aunque:

* exista route cache antigua;
* exista artifact antiguo;
* el método siga presente;
* un alias todavía apunte a ella.

---

# 43. Target revocation

El sistema podrá mantener un registry de targets revocados.

```php
interface ControllerTargetRevocationRegistryInterface
{
    public function isRevoked(
        string $targetSignature
    ): bool;
}
```

---

# 44. Parameter Injection Security

Los parámetros representan una de las superficies más sensibles.

El engine deberá distinguir:

```text
Untrusted input values
Trusted context values
Container services
Resolved domain resources
Framework internals
```

---

# 45. ParameterSecurityDefinition

```php
final readonly class ParameterSecurityDefinition
{
    public function __construct(
        public string $name,
        public ParameterSource $source,
        public ?string $type,
        public ParameterTrustLevel $trust,
        public array $validators,
        public array $normalizers,
        public bool $sensitive,
    ) {
    }
}
```

---

# 46. ParameterSource

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
    case Session = 'session';
    case Principal = 'principal';
    case Tenant = 'tenant';
    case Container = 'container';
    case Model = 'model';
    case DTO = 'dto';
    case Context = 'context';
}
```

---

# 47. ParameterTrustLevel

```php
enum ParameterTrustLevel: string
{
    case Untrusted = 'untrusted';
    case Validated = 'validated';
    case Authenticated = 'authenticated';
    case TrustedFramework = 'trusted_framework';
}
```

---

# 48. Explicit source binding

Cada parámetro deberá tener una fuente determinista.

Ejemplo:

```php
public function update(
    #[FromRoute] int $id,
    #[FromJson] UpdateOrderData $data
): Response
```

---

# 49. Source ambiguity

Si un nombre aparece en varias fuentes:

```text
route.id
query.id
body.id
```

el engine no deberá elegir silenciosamente salvo que exista una política explícita.

---

# 50. Source precedence

Podrá existir precedencia para compatibilidad, pero deberá estar:

* documentada;
* compilada;
* observable;
* deshabilitable en strict mode.

---

# 51. Strict parameter mode

En strict mode:

* toda fuente deberá ser explícita;
* no se admitirán claves desconocidas cuando el schema lo prohíba;
* no se realizarán coerciones ambiguas;
* los conflictos producirán error.

---

# 52. Route parameter security

Los route parameters deberán:

* venir del match normalizado;
* cumplir regex o constraint;
* tener longitud limitada;
* decodificarse una sola vez;
* no contener bytes inválidos;
* no redefinirse desde query o body.

---

# 53. Query parameter security

Controles:

* máximo de claves;
* máximo de profundidad;
* máximo de longitud;
* manejo definido de claves duplicadas;
* normalización Unicode;
* validación de arrays;
* rechazo de estructuras inesperadas.

---

# 54. Body security

El body deberá protegerse con:

* tamaño máximo;
* parser por content type;
* timeout;
* profundidad máxima;
* field count;
* schema validation;
* parser errors seguros.

---

# 55. JSON security

El parser deberá considerar:

* profundidad;
* números extremos;
* duplicate keys;
* invalid UTF-8;
* trailing data;
* top-level type;
* payload size.

---

# 56. Duplicate JSON keys

La política recomendada será rechazar claves duplicadas.

Esto evita interpretaciones diferentes entre componentes.

---

# 57. Header parameter security

Solo podrán inyectarse headers explícitamente permitidos.

No deberán inyectarse directamente:

* Authorization completo;
* Cookie completo;
* proxy headers;
* Host;
* Content-Length;
* Transfer-Encoding.

Para ellos deberán existir value objects o contextos especializados.

---

# 58. Cookie parameter security

Las cookies se considerarán no confiables aunque estén firmadas, salvo después de validación criptográfica y de contexto.

---

# 59. Session injection

El controller no deberá recibir almacenamiento de sesión mutable completo por defecto.

Se favorecerán:

* value objects;
* session context limitado;
* interfaces con capabilities mínimas.

---

# 60. Principal injection

La identidad deberá inyectarse desde el `ControllerSecurityContext`, nunca desde parámetros del request.

---

# 61. Tenant injection

El tenant deberá inyectarse desde un resolver confiable y verificado.

Nunca desde:

```text
body.tenant_id
query.tenant
header.X-Tenant
```

salvo que ese valor sea solo una señal y se verifique contra una identidad autenticada.

---

# 62. Container injection

Solo se inyectarán servicios permitidos.

```php
interface ControllerInjectableServiceRegistryInterface
{
    public function isInjectable(
        string $serviceId
    ): bool;
}
```

---

# 63. Arbitrary service injection

No deberá permitirse que un controller solicite cualquier servicio del contenedor sin restricciones.

Se podrán bloquear:

* secret stores;
* build activators;
* raw database connection;
* root container;
* deployment manager;
* filesystem root access.

---

# 64. Injection capabilities

Los servicios podrán declarar capabilities:

```text
ReadData
WriteData
SendMail
AccessSecrets
ManageBuilds
EmitResponse
WriteFilesystem
```

El compilador podrá advertir o bloquear combinaciones peligrosas.

---

# 65. Scalar coercion

La coerción deberá ser explícita.

Ejemplos riesgosos:

```text
"false" → true
"01" → 1
"1e3" → 1000
"" → 0
```

El modo estricto deberá rechazarlos cuando sean ambiguos.

---

# 66. Boolean coercion

Valores permitidos recomendados:

```text
true
false
1
0
"true"
"false"
```

La política exacta deberá configurarse y compilarse.

---

# 67. Integer coercion

Deberá validar:

* formato decimal;
* signo;
* overflow;
* rango;
* leading zeros según política;
* notación científica no permitida salvo solicitud explícita.

---

# 68. Float coercion

Deberá considerar:

* `NaN`;
* infinities;
* overflow;
* locale;
* scientific notation;
* precision.

---

# 69. String normalization

Podrá incluir:

* UTF-8 validation;
* Unicode normalization;
* trimming explícito;
* control character rejection;
* length limits.

La normalización no deberá alterar identificadores de forma insegura.

---

# 70. Enum resolution

Solo se resolverán enums declarados en el parameter plan.

No se permitirá elegir el enum class desde el request.

---

# 71. Union types

Los union types pueden producir ambigüedad.

Ejemplo:

```php
int|string $id
```

La política deberá definir:

* orden;
* discriminador;
* strictness;
* rechazo cuando múltiples tipos coincidan.

---

# 72. Variadic parameters

Los variadics deberán tener:

* fuente explícita;
* máximo de elementos;
* validación individual;
* tipo homogéneo;
* límites de memoria.

---

# 73. Sensitive parameters

Un parámetro marcado sensible:

* no se registrará;
* no se incluirá en exception context;
* no se incluirá en traces;
* deberá redactarse en debug dumps;
* podrá limpiarse tempranamente.

---

# 74. SensitiveParameter attribute

Podrá integrarse con:

```php
#[\SensitiveParameter]
string $password
```

y con metadata propia de VoltStack.

---

# 75. Parameter Security Guard

```php
interface ParameterSecurityGuardInterface
{
    public function validate(
        ResolvedParameter $parameter,
        ParameterSecurityDefinition $definition,
        ControllerSecurityContext $security
    ): void;
}
```

---

# 76. Parameter budgets

```php
final readonly class ParameterResolutionBudget
{
    public function __construct(
        public int $maxParameters,
        public int $maxDepth,
        public int $maxCollectionItems,
        public int $maxTotalBytes,
    ) {
    }
}
```

---

# 77. DTO Hydration Security

La hydration deberá ser schema-driven.

```text
Input map
    ↓
DTO Schema
    ↓
Field validation
    ↓
Safe coercion
    ↓
Constructor invocation
    ↓
Immutable DTO
```

---

# 78. DTO schema

```php
final readonly class DTOHydrationSchema
{
    public function __construct(
        public string $class,
        public array $fields,
        public UnknownFieldPolicy $unknownFields,
        public int $maxDepth,
        public bool $allowPolymorphism,
    ) {
    }
}
```

---

# 79. UnknownFieldPolicy

```php
enum UnknownFieldPolicy: string
{
    case Reject = 'reject';
    case Ignore = 'ignore';
    case Capture = 'capture';
}
```

---

# 80. Política recomendada

Para input sensible o comandos:

```text
Reject
```

Para filtros flexibles:

```text
Ignore
```

solo cuando esté claramente documentado.

---

# 81. Constructor-only hydration

La V1 deberá favorecer DTOs construidos por constructor.

Evitará:

* mutación posterior;
* magic setters;
* propiedades dinámicas;
* estados parcialmente inicializados.

---

# 82. Property hydration

Cuando se permita deberá limitarse a propiedades:

* públicas;
* declaradas;
* allowlisted;
* no estáticas;
* no readonly ya inicializadas;
* compatibles con schema.

---

# 83. Magic methods

No se deberán usar automáticamente:

```text
__set
__call
__unserialize
```

durante hydration.

---

# 84. DTO polymorphism

La selección de subclase desde input estará deshabilitada por defecto.

Nunca se confiará en campos como:

```json
{
  "class": "App\\Admin\\DangerousCommand"
}
```

---

# 85. Discriminated unions

Cuando se necesite polimorfismo deberá existir una allowlist explícita.

```php
[
    'card' => CardPaymentData::class,
    'bank' => BankPaymentData::class,
]
```

---

# 86. Nested DTO depth

La profundidad deberá limitarse para evitar:

* consumo de memoria;
* recursion;
* payload bombs;
* ciclos accidentales.

---

# 87. Collection hydration

Las colecciones deberán tener:

* máximo de elementos;
* tipo de elemento;
* validación por elemento;
* fail-fast o aggregate errors definido.

---

# 88. DTO validation order

```text
Structural validation
    ↓
Type validation
    ↓
Normalization
    ↓
Constraint validation
    ↓
Cross-field validation
    ↓
Construction
```

---

# 89. Cross-field validation

Deberá ejecutarse antes de invocar código de dominio cuando sea posible.

---

# 90. DTO side effects

Constructores de DTO utilizados para hydration no deberán producir:

* queries;
* network calls;
* filesystem writes;
* events;
* container resolution.

---

# 91. DTO allowlist

Solo clases registradas como hydratable podrán construirse desde input.

```php
interface HydratableDTORegistryInterface
{
    public function isHydratable(string $class): bool;
}
```

---

# 92. DTO compiler

El compiler deberá producir:

```text
CompiledDTOHydrationPlan
```

sin reflexión en runtime.

---

# 93. CompiledDTOHydrationPlan

```php
final readonly class CompiledDTOHydrationPlan
{
    public function __construct(
        public string $class,
        public array $fields,
        public array $constructorArguments,
        public UnknownFieldPolicy $unknownFields,
        public int $maxDepth,
        public string $fingerprint,
    ) {
    }
}
```

---

# 94. Model Binding Security

El model binding no deberá ser solo una búsqueda por ID.

Deberá integrar:

```text
Identifier validation
    ↓
Tenant scope
    ↓
Visibility scope
    ↓
Model lookup
    ↓
Resource authorization
```

---

# 95. ModelBindingDefinition

```php
final readonly class ModelBindingDefinition
{
    public function __construct(
        public string $modelClass,
        public string $routeKey,
        public bool $tenantScoped,
        public array $scopes,
        public ?string $policy,
        public MissingModelPolicy $missingPolicy,
    ) {
    }
}
```

---

# 96. Model class allowlist

Solo modelos registrados podrán resolverse automáticamente.

El model class no podrá provenir del cliente.

---

# 97. Route key restrictions

El route key deberá ser:

* declarado;
* indexable cuando corresponda;
* no sensible;
* no construido dinámicamente desde input;
* validado.

---

# 98. Tenant-first binding

Para modelos tenant-bound:

```text
tenant_id = verified tenant
AND route_key = request value
```

No se deberá cargar primero globalmente y filtrar después.

---

# 99. Cross-tenant hiding

La política recomendada será devolver semántica de no encontrado para evitar enumeración.

---

# 100. Parent-scoped binding

Ejemplo:

```text
/projects/{project}/tasks/{task}
```

El task deberá resolverse dentro del project:

```text
task.project_id = resolved project.id
```

---

# 101. Scoped binding graph

Los bindings deberán poder declarar relaciones:

```text
Tenant
  └── Project
       └── Task
```

---

# 102. Binding order

El engine deberá resolver en orden topológico.

Un child binding no deberá ejecutarse antes de validar su parent.

---

# 103. Soft-deleted models

Por defecto no se incluirán.

Cuando se permitan deberán requerir:

* metadata explícita;
* policy especial;
* posible permiso administrativo;
* audit.

---

# 104. Global scopes

No deberán eliminarse automáticamente durante binding.

Bypasses como `withoutGlobalScopes()` deberán estar prohibidos salvo definición privilegiada.

---

# 105. Model binding authorization

La existencia del modelo no implica permiso.

Después del lookup deberá evaluarse una policy de recurso.

---

# 106. Timing disclosure

Cuando sea relevante, el sistema podrá normalizar respuestas para evitar diferenciar fácilmente:

* recurso inexistente;
* recurso de otro tenant;
* recurso no autorizado.

---

# 107. Binding cache

Solo podrá existir por ejecución o request.

La key deberá incluir:

* model class;
* route key;
* tenant;
* scopes;
* principal cuando influya.

---

# 108. Binding side effects

El binding no deberá:

* mutar modelos;
* disparar comandos;
* guardar datos;
* publicar eventos de dominio;
* ejecutar lazy loading indiscriminado.

---

# 109. Lazy loading

Podrá deshabilitarse durante autorización para evitar:

* queries inesperadas;
* exposición indirecta;
* N+1;
* side channels.

---

# 110. File Parameter Security

Los archivos subidos requieren tratamiento separado.

---

# 111. UploadedFileDescriptor

```php
final readonly class UploadedFileDescriptor
{
    public function __construct(
        public string $temporaryPath,
        public string $clientFilename,
        public string $detectedMimeType,
        public int $size,
        public string $uploadId,
        public bool $validated,
    ) {
    }
}
```

---

# 112. Client filename

Se considerará metadata no confiable.

No deberá utilizarse directamente como path de destino.

---

# 113. Filename normalization

Deberá:

* eliminar path segments;
* rechazar null bytes;
* limitar longitud;
* normalizar Unicode;
* evitar nombres reservados;
* separar nombre y extensión.

---

# 114. MIME validation

Se distinguirá:

```text
Declared MIME
Detected MIME
Allowed MIME
```

La decisión deberá usar detección server-side cuando sea posible.

---

# 115. Extension validation

La extensión nunca será la única prueba del tipo de archivo.

---

# 116. Upload limits

Por ruta podrán configurarse:

* tamaño por archivo;
* tamaño total;
* cantidad;
* tipos;
* dimensiones;
* duración;
* profundidad de archivo comprimido.

---

# 117. Archive bombs

Si se inspeccionan archivos comprimidos deberán limitarse:

* ratio;
* profundidad;
* cantidad de archivos;
* tamaño expandido;
* tiempo de análisis.

---

# 118. Temporary storage

Los uploads deberán almacenarse:

* fuera de web root;
* con nombre generado;
* con permisos mínimos;
* aislados por execution;
* con cleanup garantizado.

---

# 119. Upload scanning

La arquitectura permitirá integrar:

```text
Malware scanner
Content scanner
DLP scanner
Image validator
Document sanitizer
```

---

# 120. Quarantine

Un archivo no validado no deberá moverse directamente a storage permanente público.

---

# 121. Image security

Las imágenes podrán requerir:

* decode/re-encode;
* dimension limits;
* pixel limits;
* metadata stripping;
* format allowlist.

---

# 122. File execution prevention

Los uploads nunca deberán ejecutarse como PHP u otro código.

El storage público deberá impedir interpretación de scripts.

---

# 123. Authorization Pipeline

La autorización se dividirá en fases.

```text
Authentication
    ↓
Route-level authorization
    ↓
Pre-binding authorization
    ↓
Parameter/resource resolution
    ↓
Resource-level authorization
    ↓
Invocation authorization
    ↓
Result obligations
```

---

# 124. Route-level authorization

Adecuada para:

* módulo;
* feature;
* role general;
* authentication strength;
* internal access.

---

# 125. Pre-binding authorization

Evita trabajo costoso y reduce exposición.

Ejemplo:

```text
permission: orders.read
```

antes de resolver la orden.

---

# 126. Resource-level authorization

Evalúa:

* ownership;
* tenant;
* estado;
* relaciones;
* field-level permissions;
* action.

---

# 127. Invocation authorization

Último guard antes de ejecutar.

Deberá verificar que la decisión:

* pertenece a la ejecución actual;
* no está expirada;
* corresponde al target;
* corresponde al resource;
* corresponde al tenant.

---

# 128. AuthorizationDecisionToken

```php
final readonly class AuthorizationDecisionToken
{
    public function __construct(
        public string $executionId,
        public string $targetSignature,
        public string $policyFingerprint,
        public SecurityDecisionEffect $effect,
        public string $issuedAt,
    ) {
    }
}
```

---

# 129. Decision token purpose

Evita que una decisión calculada para una acción sea reutilizada accidentalmente en otra.

---

# 130. Time-sensitive authorization

Algunas decisiones podrán tener TTL corto.

Ejemplos:

* high-risk operations;
* MFA confirmation;
* signed approval;
* impersonation.

---

# 131. TOCTOU

Existe riesgo entre autorización y mutación.

Mitigaciones:

* transacción;
* version checks;
* database constraints;
* policy revalidation;
* locks cuando sea necesario.

---

# 132. Field-level authorization

Podrá aplicarse a:

* input fields;
* output fields;
* patch operations;
* exports;
* sensitive attributes.

---

# 133. Input field filtering

No deberá limitarse a ignorar campos no autorizados.

Para operaciones sensibles se deberá rechazar explícitamente el intento.

---

# 134. Authorization obligations

Ejemplos:

```text
Allow + audit
Allow + mask fields
Allow + no cache
Allow + force MFA next time
Allow + restrict transport
```

---

# 135. Policy composition

Estrategias:

```text
All must allow
Any may allow
Deny overrides
First applicable
Weighted or contextual
```

La estrategia deberá ser explícita.

---

# 136. Deny overrides

Será la estrategia recomendada para políticas de seguridad acumulativas.

---

# 137. Policy purity

Las policies deberán ser idealmente libres de side effects.

No deberán:

* modificar recursos;
* enviar respuestas;
* escribir logs manuales;
* cambiar tenant;
* persistir decisiones globalmente.

---

# 138. Policy query limits

Se deberán detectar o limitar evaluaciones excesivas.

---

# 139. Authorization N+1

En colecciones deberá evitarse evaluar una policy con queries individuales por elemento.

Podrán existir:

* scoped queries;
* batch authorization;
* precomputed permissions;
* collection policies.

---

# 140. Tenant Isolation

El tenant será parte del contexto de seguridad, no solo un filtro de base de datos.

---

# 141. TenantResolutionResult

```php
final readonly class TenantResolutionResult
{
    public function __construct(
        public ?TenantIdentity $tenant,
        public TenantResolutionSource $source,
        public bool $verified,
        public array $evidence,
    ) {
    }
}
```

---

# 142. TenantResolutionSource

```php
enum TenantResolutionSource: string
{
    case Host = 'host';
    case AuthenticatedClaim = 'authenticated_claim';
    case Session = 'session';
    case SignedHeader = 'signed_header';
    case InternalContext = 'internal_context';
}
```

---

# 143. Tenant source trust

Cada source tendrá un nivel de confianza.

Un header simple enviado por el cliente no será suficiente.

---

# 144. Host-based tenant resolution

Deberá validar:

* allowed hosts;
* canonicalization;
* punycode;
* port;
* trusted proxy;
* wildcard rules;
* mapping exacto.

---

# 145. Signed tenant headers

Solo serán aceptables cuando:

* provengan de proxy confiable;
* estén firmados;
* incluyan timestamp;
* prevengan replay;
* el header original del cliente sea eliminado.

---

# 146. TenantContext

```php
final readonly class TenantContext
{
    public function __construct(
        public TenantIdentity $tenant,
        public string $executionId,
        public TenantIsolationMode $mode,
    ) {
    }
}
```

---

# 147. TenantIsolationMode

```php
enum TenantIsolationMode: string
{
    case SharedDatabase = 'shared_database';
    case SeparateSchema = 'separate_schema';
    case SeparateDatabase = 'separate_database';
    case ExternalPartition = 'external_partition';
}
```

---

# 148. Tenant invariant

Toda operación tenant-bound deberá portar el tenant context hasta:

* repository;
* query builder;
* cache;
* storage;
* queue dispatch;
* event dispatch;
* observability.

---

# 149. Tenant cache isolation

Las keys deberán incluir tenant identity validada.

No deberá aceptarse un tenant ID derivado directamente del request.

---

# 150. Tenant storage isolation

Las rutas de storage deberán resolverse desde un componente confiable.

No concatenar manualmente:

```php
$path = $tenantId.'/'.$filename;
```

sin normalización y aislamiento.

---

# 151. Tenant event isolation

Los eventos deberán incluir tenant context cuando correspondan.

Los listeners no deberán usar un contexto global persistente fuera de la ejecución.

---

# 152. Cross-tenant admin operations

Deberán requerir:

* policy privilegiada;
* target tenant explícito;
* reason;
* audit;
* UI o API diferenciada;
* protección contra tenant confusion.

---

# 153. Tenant switching

Cambiar tenant dentro de una ejecución estará prohibido por defecto.

Cuando se permita deberá crear un contexto hijo explícito y auditable.

---

# 154. Interceptor Security

Los interceptores pueden modificar el flujo y son altamente privilegiados.

---

# 155. Interceptor trust levels

```php
enum InterceptorTrustLevel: string
{
    case Application = 'application';
    case Package = 'package';
    case Framework = 'framework';
    case SecurityCritical = 'security_critical';
}
```

---

# 156. Security-critical interceptors

Ejemplos:

* authentication;
* authorization;
* tenant isolation;
* CSRF;
* rate limiting;
* audit;
* invocation guard.

---

# 157. Mandatory interceptors

No podrán eliminarse desde metadata de menor confianza.

---

# 158. Reserved phases

```text
SecurityInitialize
Authentication
TenantResolution
PreAuthorization
ParameterSecurity
ResourceAuthorization
InvocationGuard
ResultSecurity
TransportSecurity
SecurityFinalize
```

---

# 159. Priority restrictions

Un interceptor de aplicación no podrá colocarse antes de ciertos interceptores del framework.

---

# 160. Interceptor ordering policy

```php
final readonly class InterceptorOrderingPolicy
{
    public function __construct(
        public array $reservedRanges,
        public array $mandatoryInterceptors,
        public array $forbiddenBefore,
        public array $forbiddenAfter,
    ) {
    }
}
```

---

# 161. Short-circuit security

Un interceptor podrá hacer short-circuit, pero no deberá saltarse:

* audit final;
* cleanup;
* response security;
* transport security;
* lifecycle finalization.

---

# 162. Authorization short-circuit

Cuando deniegue:

* controller no invocado;
* resource mutation no iniciada;
* safe response;
* denial audit;
* cleanup.

---

# 163. Interceptor capabilities

Podrán declararse:

```text
ReadRequest
ModifyRequestContext
ReadPrincipal
EvaluatePolicy
ShortCircuit
ModifyResult
ModifyResponse
RegisterCleanup
```

---

# 164. Capability enforcement

El runtime no puede sandboxear completamente PHP, pero podrá:

* limitar APIs entregadas;
* usar contextos read-only;
* analizar estáticamente;
* validar metadata;
* certificar paquetes.

---

# 165. Interceptor state

El scope por defecto deberá ser `Execution`.

Un interceptor Worker-scoped deberá ser inmutable.

---

# 166. Interceptor exception handling

Una excepción en un interceptor de seguridad deberá producir fail-closed.

---

# 167. Retry interceptor security

No deberá reejecutar:

* acciones no idempotentes;
* uploads consumidos;
* streams emitidos;
* authorization con contexto expirado;
* operaciones después de partial commit.

---

# 168. Transaction interceptor security

Deberá asegurar rollback en:

* denial tardío;
* exception;
* cancellation;
* cleanup failure relevante.

---

# 169. Cache interceptor security

No deberá cachear respuestas sensibles sin verificar:

* principal scope;
* tenant;
* authorization;
* `Vary`;
* obligations;
* no-cache policies.

---

# 170. Invocation Security

El `ControllerInvoker` será una primitive mínima, pero deberá estar protegido por guards externos e internos.

---

# 171. Secure invocation request

```php
final readonly class SecureControllerInvocationRequest
{
    public function __construct(
        public ResolvedController $controller,
        public string $method,
        public ResolvedArgumentCollection $arguments,
        public AuthorizationDecisionToken $authorization,
        public CompiledControllerExposurePlan $exposure,
        public ControllerSecurityContext $security,
    ) {
    }
}
```

---

# 172. Invocation preconditions

Antes de invocar:

```text
Target validated
Action exposed
Route signature allowed
Authorization token valid
Tenant context valid
Arguments complete
No cancellation
Invocation count = 0
```

---

# 173. Invocation count

Cada execution deberá registrar el número de invocaciones.

Por defecto:

```text
max = 1
```

Retries controlados requerirán un execution attempt distinto.

---

# 174. Argument integrity

Los argumentos deberán estar sellados después de la resolución.

```php
interface SealableArgumentCollectionInterface
{
    public function seal(): void;

    public function isSealed(): bool;
}
```

---

# 175. Post-authorization mutation

Después de autorizar un recurso no deberá reemplazarse silenciosamente por otro argumento.

---

# 176. Invocation strategy allowlist

Solo strategies registradas y congeladas podrán ejecutarse.

---

# 177. Dynamic call functions

No deberán usarse con input no validado:

* `call_user_func`;
* `call_user_func_array`;
* variable functions;
* variable methods.

El plan deberá contener referencias ya validadas.

---

# 178. Reflection invocation

Si se utiliza `ReflectionMethod::invokeArgs`, deberá hacerse sobre:

* clase validada;
* método validado;
* visibility validada;
* arguments sellados.

---

# 179. Controller construction

El constructor deberá resolverse por el Container siguiendo dependencies permitidas.

No se deberá hidratar el controller desde request.

---

# 180. Controller properties

El framework no deberá asignar automáticamente request data a propiedades del controller.

---

# 181. Controller state reset

Los controllers no execution-scoped deberán implementar un contrato de reset o demostrar inmutabilidad.

---

# 182. ResettableControllerInterface

```php
interface ResettableControllerInterface
{
    public function resetControllerState(): void;
}
```

---

# 183. Static state

El uso de propiedades estáticas mutables en controllers deberá detectarse mediante análisis arquitectónico o testing.

---

# 184. Global state

Se deberá evitar:

* superglobals directas;
* current user global;
* current tenant global persistente;
* static request;
* static response.

---

# 185. Environment access

Los controllers no deberían acceder directamente a secretos mediante `getenv()`.

Deberán recibir abstracciones con capabilities limitadas.

---

# 186. Command execution

Servicios que ejecutan procesos del sistema deberán estar bloqueados para inyección automática general.

---

# 187. Serialization during invocation

No deberá serializarse arbitrariamente el controller o arguments para retries internos.

---

# 188. Lifecycle Security

La máquina de estados debe impedir bypasses y estados imposibles.

---

# 189. Security-relevant states

```text
SecurityInitializing
Authenticated
TenantResolved
PreAuthorized
ParametersValidated
ResourceAuthorized
InvocationApproved
Invoked
SecurityFinalizing
SecurityCleaned
```

Estos pueden ser flags o milestones vinculados al lifecycle general.

---

# 190. Security milestone guard

```php
interface SecurityMilestoneGuardInterface
{
    public function assertReached(
        SecurityMilestone $milestone,
        ControllerExecutionState $state
    ): void;
}
```

---

# 191. Required milestones

Antes de `Invocation` deberán haberse alcanzado:

```text
ContextCreated
TargetValidated
ExposureValidated
AuthenticationSatisfied
TenantSatisfied
PreAuthorizationAllowed
ParametersSecured
ResourceAuthorizationAllowed
```

---

# 192. State tampering

El execution state deberá ser mutable solo por el Lifecycle Manager.

Controllers e interceptores no deberán cambiarlo directamente.

---

# 193. Cancellation security

La cancelación no deberá omitir cleanup ni audit.

---

# 194. Deadline security

Los deadlines deberán propagarse a:

* policies;
* binding;
* database;
* external services;
* streaming;
* cleanup con límites razonables.

---

# 195. Cleanup security

Cleanup deberá eliminar:

* security context;
* decision cache;
* principal reference;
* tenant context;
* temporary files;
* upload quarantine handles;
* impersonation context;
* audit buffers.

---

# 196. Cleanup failure

Un fallo al limpiar datos sensibles puede requerir:

```text
Worker Reset
Restart Recommended
Terminate
```

---

# 197. Resource ownership

Todo recurso sensible registrado deberá tener owner:

* execution;
* request;
* external;
* Worker.

---

# 198. Secret lifetime

Los secretos temporales deberán mantenerse el menor tiempo posible y no copiarse innecesariamente.

---

# 199. Subrequest Security

Los subrequests pueden introducir confusión de identidad y autorización.

---

# 200. Subrequest identity inheritance

Por defecto heredarán:

* principal;
* tenant;
* authentication strength;
* trace.

Pero deberán obtener:

* nuevo execution ID;
* nuevo decision cache;
* nuevo lifecycle;
* nueva autorización para el target hijo.

---

# 201. No authorization inheritance

Una decisión `Allow` del parent no autoriza automáticamente el child.

---

# 202. SubrequestSecurityContext

```php
final readonly class SubrequestSecurityContext
{
    public function __construct(
        public ControllerSecurityContext $parent,
        public ControllerSecurityContext $child,
        public int $depth,
        public string $reason,
    ) {
    }
}
```

---

# 203. Subrequest depth

Se limitará para prevenir recursion y DoS.

---

# 204. Internal subrequests

No deberán convertirse automáticamente en trusted system calls.

Continuarán sujetos a políticas, salvo un canal interno explícito y restringido.

---

# 205. Principal elevation in subrequests

Estará prohibida por defecto.

---

# 206. Tenant switching in subrequests

Requerirá:

* policy explícita;
* trusted internal source;
* audit;
* child context separado.

---

# 207. Response isolation

La respuesta del subrequest no deberá emitirse directamente al cliente salvo que el parent la adopte mediante una operación explícita.

---

# 208. Recursion Security

El sistema deberá mantener un execution graph.

---

# 209. Execution graph

```php
final class ControllerExecutionGraph
{
    public function enter(
        ControllerTarget $target,
        string $executionId
    ): void;

    public function leave(
        string $executionId
    ): void;
}
```

---

# 210. Recursion keys

Podrán incluir:

* route signature;
* controller action;
* resource identity;
* tenant;
* execution parent.

---

# 211. Recursion policies

```text
Deny same action recursion
Limit total depth
Limit repeated target count
Allow explicit recursive components
```

---

# 212. Error handler recursion

Se deberá detectar cuando el error handler vuelve a provocar el mismo error.

Deberá activarse emergency fallback.

---

# 213. Authorization recursion

Policies que se invocan mutuamente deberán tener detection y budget.

---

# 214. Retry Security

Todo retry deberá crear un `ExecutionAttempt`.

---

# 215. ExecutionAttempt

```php
final readonly class ExecutionAttempt
{
    public function __construct(
        public int $number,
        public string $reason,
        public string $startedAt,
        public bool $authorizationRevalidated,
    ) {
    }
}
```

---

# 216. Retry eligibility

Solo será elegible si:

* la operación es idempotente, o
* existe idempotency key válida, o
* no hubo side effect observable, o
* la strategy puede compensar.

---

# 217. Authorization revalidation

Deberá revalidarse cuando:

* cambia el resource;
* expira la decisión;
* cambia principal;
* cambia tenant;
* cambia attempt context;
* pasa suficiente tiempo.

---

# 218. Retry budgets

```php
final readonly class RetrySecurityBudget
{
    public function __construct(
        public int $maxAttempts,
        public int $maxTotalDurationMs,
        public bool $requireIdempotency,
    ) {
    }
}
```

---

# 219. Idempotency key security

Una idempotency key deberá:

* tener longitud limitada;
* asociarse a principal;
* tenant;
* route;
* payload fingerprint;
* expiration.

---

# 220. Replay protection

La misma key con payload diferente deberá rechazarse.

---

# 221. Cancellation Security

La cancellation token deberá ser controlada por framework o runtime confiable.

---

# 222. Client disconnect

Podrá provocar cancelación, pero no deberá impedir:

* rollback;
* cleanup;
* audit crítico;
* release de locks.

---

# 223. User-triggered cancellation

Solo podrá afectar la ejecución propia, salvo permisos administrativos.

---

# 224. Cancellation race

El lifecycle deberá resolver de forma determinista carreras entre:

* completion;
* cancellation;
* exception;
* timeout.

---

# 225. Partial response cancellation

Si ya se emitieron bytes:

* no podrá reemplazarse la respuesta;
* se registrará partial emission;
* se cerrará stream;
* se ejecutará cleanup;
* se evaluará Worker disposition.

---

# 226. FrankenPHP Worker Isolation

Los Workers persistentes amplifican cualquier fuga de estado.

---

# 227. Worker trust model

El Worker será reutilizable únicamente mientras se mantengan invariantes de aislamiento.

---

# 228. Request scope

Cada request deberá crear un scope nuevo que contenga:

* request;
* route match;
* principal;
* tenant;
* security context;
* execution;
* response;
* temporary resources.

---

# 229. Execution scope

Un request podrá contener múltiples executions o subrequests.

Cada una tendrá scope propio.

---

# 230. Worker-safe services

Un servicio Worker-scoped deberá ser:

* inmutable, o
* explícitamente resettable;
* thread/concurrency aware cuando corresponda;
* libre de referencias request-scoped.

---

# 231. Forbidden Worker state

No deberá persistir:

```text
Current user
Current tenant
Current request
Current route
Current arguments
Current response
Current exception
Authorization decisions
CSRF state
Upload descriptors
```

---

# 232. WorkerSecurityResetter

```php
interface WorkerSecurityResetterInterface
{
    public function reset(
        WorkerSecurityResetContext $context
    ): WorkerSecurityResetResult;
}
```

---

# 233. Reset order

```text
Stop active work
    ↓
Close streams
    ↓
Rollback transactions
    ↓
Release locks
    ↓
Clear security contexts
    ↓
Clear request scopes
    ↓
Clear sensitive buffers
    ↓
Validate worker state
```

---

# 234. Worker state validation

Después del reset se deberá verificar:

* no principal;
* no tenant;
* no active execution;
* no decision cache;
* no active spans;
* no temporary uploads;
* no open transaction.

---

# 235. Leak detection

Podrá ejecutarse:

* siempre en desarrollo;
* por sampling en producción;
* siempre después de incidentes;
* durante tests.

---

# 236. WorkerSecuritySnapshot

```php
final readonly class WorkerSecuritySnapshot
{
    public function __construct(
        public bool $hasPrincipal,
        public bool $hasTenant,
        public int $activeExecutions,
        public int $securityDecisionEntries,
        public int $openSensitiveResources,
    ) {
    }
}
```

---

# 237. Worker disposition

```php
enum WorkerSecurityDisposition: string
{
    case Reuse = 'reuse';
    case Reset = 'reset';
    case RestartRecommended = 'restart_recommended';
    case Terminate = 'terminate';
    case Quarantine = 'quarantine';
}
```

---

# 238. Reuse

Solo cuando todas las invariantes se cumplen.

---

# 239. Reset

Cuando existe estado residual recuperable.

---

# 240. RestartRecommended

Cuando hay incertidumbre razonable, por ejemplo:

* cleanup failure;
* memory growth;
* exporter failure con buffers;
* repeated timeouts.

---

# 241. Terminate

Ante pérdida de confianza:

* artifact mismatch;
* leaked principal no eliminable;
* corrupted registry;
* impossible state;
* secret exposure interna.

---

# 242. Quarantine

Podrá utilizarse en plataformas con supervisión avanzada para retirar el Worker del pool y conservar diagnóstico.

---

# 243. Worker cache security

Solo podrán almacenarse:

* artifacts inmutables;
* compiled plans;
* stateless registries congelados;
* immutable policy definitions.

---

# 244. No security decision caching across requests

Las decisiones de autorización no podrán permanecer en Worker cache general.

---

# 245. Static variable audit

Las clases runtime deberán revisarse para detectar static mutable state.

---

# 246. Global context adapters

Si existen helpers como:

```php
current_user()
tenant()
request()
```

deberán resolver desde un scope actual seguro y fallar si no existe.

No podrán almacenar directamente el objeto en una propiedad estática persistente.

---

# 247. Async and concurrency awareness

FrankenPHP puede atender Workers con patrones concurrentes según configuración futura.

Los contextos deberán ser localizables por execution, no por global mutable state.

---

# 248. Context carrier

```php
interface ExecutionContextCarrierInterface
{
    public function current(): ?ControllerExecutionContext;

    public function runWith(
        ControllerExecutionContext $context,
        callable $callback
    ): mixed;
}
```

---

# 249. Context restoration

`runWith()` deberá restaurar el contexto anterior incluso ante excepción.

---

# 250. Fiber safety

Cuando se utilicen Fibers, el contexto deberá ser Fiber-local o propagado explícitamente.

---

# 251. Security exceptions

Excepciones principales:

```text
ControllerTargetRejectedException
ControllerNotExposedException
ControllerMethodNotInvocableException
UnsafeServiceInjectionException
ParameterSourceConflictException
ParameterSecurityViolationException
DTOHydrationSecurityException
UnauthorizedModelBindingException
TenantIsolationViolationException
InterceptorSecurityViolationException
InvocationSecurityViolationException
SubrequestSecurityViolationException
RetrySecurityViolationException
WorkerSecurityLeakException
```

---

# 252. Public error mapping

Estas excepciones no deberán exponer detalles internos.

Ejemplo:

```text
ControllerNotExposedException
    → 404 or 403 according to policy
```

---

# 253. Security events del runtime

```text
controllers.security.target.validated
controllers.security.target.rejected
controllers.security.action.exposed
controllers.security.action.rejected
controllers.security.parameter.validated
controllers.security.parameter.rejected
controllers.security.dto.hydrated
controllers.security.dto.rejected
controllers.security.model.bound
controllers.security.model.denied
controllers.security.interceptor.rejected
controllers.security.invocation.approved
controllers.security.invocation.denied
controllers.security.worker.leak_detected
controllers.security.worker.terminated
```

---

# 254. Runtime security metrics

```text
voltstack.controllers.security.target_rejections
voltstack.controllers.security.parameter_conflicts
voltstack.controllers.security.dto_rejections
voltstack.controllers.security.model_binding_denials
voltstack.controllers.security.interceptor_violations
voltstack.controllers.security.invocation_denials
voltstack.controllers.security.worker_leaks
voltstack.controllers.security.worker_terminations
```

---

# 255. Audit requirements

Se auditarán especialmente:

* invocación administrativa;
* cross-tenant operations;
* impersonation;
* soft-deleted binding;
* privileged service injection;
* internal actions;
* retries de operaciones sensibles.

---

# 256. Runtime security configuration

```php
// config/controller_security_runtime.php

return [
    'resolution' => [
        'explicit_targets_only' => true,
        'allowed_namespaces' => [
            'App\\Http\\Controllers\\',
            'App\\Actions\\',
        ],
        'allow_static' => false,
        'allow_global_functions' => false,
        'max_alias_depth' => 4,
    ],

    'exposure' => [
        'explicit_actions_only' => true,
        'allow_inherited_actions' => false,
        'bind_actions_to_routes' => true,
        'reject_magic_methods' => true,
    ],

    'parameters' => [
        'strict_sources' => true,
        'reject_source_conflicts' => true,
        'max_parameters' => 64,
        'max_depth' => 16,
        'max_collection_items' => 1000,
        'max_total_bytes' => 2 * 1024 * 1024,
    ],

    'dto' => [
        'allowlist_only' => true,
        'unknown_fields' => 'reject',
        'allow_polymorphism' => false,
        'constructor_only' => true,
        'max_depth' => 8,
    ],

    'models' => [
        'allowlist_only' => true,
        'tenant_first' => true,
        'require_resource_authorization' => true,
        'hide_cross_tenant_resources' => true,
        'allow_soft_deleted' => false,
    ],

    'uploads' => [
        'max_files' => 10,
        'max_file_size' => 10 * 1024 * 1024,
        'max_total_size' => 25 * 1024 * 1024,
        'temporary_isolation' => true,
        'scan_before_persist' => true,
    ],

    'interceptors' => [
        'reserve_security_phases' => true,
        'mandatory' => [
            'authentication',
            'tenant',
            'authorization',
            'invocation_guard',
            'security_finalize',
        ],
    ],

    'invocation' => [
        'max_invocations_per_execution' => 1,
        'seal_arguments' => true,
        'require_authorization_token' => true,
    ],

    'subrequests' => [
        'max_depth' => 8,
        'inherit_identity' => true,
        'inherit_authorization' => false,
        'allow_tenant_switch' => false,
    ],

    'retries' => [
        'max_attempts' => 3,
        'require_idempotency_for_writes' => true,
        'revalidate_authorization' => true,
    ],

    'workers' => [
        'validate_after_request' => true,
        'clear_security_context' => true,
        'clear_decision_cache' => true,
        'terminate_on_irrecoverable_leak' => true,
    ],
];
```

---

# 257. Estructura del módulo Runtime

```text
src/
└── Quantum/
    └── Controllers/
        └── Security/
            └── Runtime/
                ├── Resolution/
                │   ├── SecureControllerResolver.php
                │   ├── ControllerResolutionSecurityContext.php
                │   ├── ControllerResolutionRegistry.php
                │   ├── ControllerNamespacePolicy.php
                │   ├── ControllerTargetRevocationRegistry.php
                │   └── AliasCycleDetector.php
                │
                ├── Exposure/
                │   ├── ControllerExposureRegistry.php
                │   ├── ExposedControllerAction.php
                │   ├── ControllerExposurePolicy.php
                │   ├── ControllerMethodVisibilityGuard.php
                │   └── CompiledControllerExposurePlan.php
                │
                ├── Parameters/
                │   ├── ParameterSecurityDefinition.php
                │   ├── ParameterSecurityGuard.php
                │   ├── ParameterTrustLevel.php
                │   ├── ParameterResolutionBudget.php
                │   ├── ScalarCoercionPolicy.php
                │   └── ControllerInjectableServiceRegistry.php
                │
                ├── DTO/
                │   ├── DTOHydrationSchema.php
                │   ├── DTOHydrationSecurityGuard.php
                │   ├── HydratableDTORegistry.php
                │   ├── UnknownFieldPolicy.php
                │   └── CompiledDTOHydrationPlan.php
                │
                ├── Binding/
                │   ├── SecureModelBinder.php
                │   ├── ModelBindingDefinition.php
                │   ├── ModelBindingRegistry.php
                │   ├── BindingScopeGraph.php
                │   └── ModelBindingAuthorizationGuard.php
                │
                ├── Uploads/
                │   ├── UploadedFileDescriptor.php
                │   ├── UploadSecurityPolicy.php
                │   ├── UploadScannerInterface.php
                │   ├── UploadQuarantine.php
                │   └── UploadCleanupResource.php
                │
                ├── Authorization/
                │   ├── RuntimeAuthorizationPipeline.php
                │   ├── AuthorizationDecisionToken.php
                │   ├── AuthorizationTokenValidator.php
                │   ├── FieldAuthorizationPolicy.php
                │   └── AuthorizationBudget.php
                │
                ├── Tenant/
                │   ├── TenantResolutionResult.php
                │   ├── TenantResolutionSource.php
                │   ├── TenantContext.php
                │   ├── TenantIsolationMode.php
                │   ├── TenantContextGuard.php
                │   └── TenantSwitchPolicy.php
                │
                ├── Interceptors/
                │   ├── InterceptorTrustLevel.php
                │   ├── InterceptorSecurityValidator.php
                │   ├── InterceptorOrderingPolicy.php
                │   ├── MandatoryInterceptorRegistry.php
                │   └── InterceptorCapability.php
                │
                ├── Invocation/
                │   ├── SecureControllerInvocationRequest.php
                │   ├── ControllerInvocationSecurityGuard.php
                │   ├── SealableArgumentCollection.php
                │   ├── InvocationCountGuard.php
                │   └── ResettableControllerInterface.php
                │
                ├── Lifecycle/
                │   ├── SecurityMilestone.php
                │   ├── SecurityMilestoneGuard.php
                │   ├── SecurityCleanupCoordinator.php
                │   └── SensitiveResourceRegistry.php
                │
                ├── Subrequests/
                │   ├── SubrequestSecurityContext.php
                │   ├── SubrequestSecurityPolicy.php
                │   └── SubrequestDepthGuard.php
                │
                ├── Recursion/
                │   ├── ControllerExecutionGraph.php
                │   ├── RecursionSecurityPolicy.php
                │   └── RecursionBudgetGuard.php
                │
                ├── Retry/
                │   ├── ExecutionAttempt.php
                │   ├── RetrySecurityBudget.php
                │   ├── IdempotencySecurityGuard.php
                │   └── AuthorizationRevalidationPolicy.php
                │
                ├── Cancellation/
                │   ├── CancellationSecurityGuard.php
                │   └── PartialEmissionSecurityPolicy.php
                │
                ├── Workers/
                │   ├── WorkerSecurityResetter.php
                │   ├── WorkerSecuritySnapshot.php
                │   ├── WorkerSecurityLeakDetector.php
                │   ├── WorkerSecurityDisposition.php
                │   └── WorkerSecurityResetResult.php
                │
                ├── Events/
                ├── Metrics/
                ├── Exceptions/
                └── Testing/
```

---

# 258. Testing requirements

La suite deberá cubrir:

* target injection;
* alias cycles;
* non-exposed methods;
* inherited actions;
* parameter source confusion;
* scalar coercion edge cases;
* DTO extra fields;
* polymorphic DTO attacks;
* cross-tenant model binding;
* upload filename traversal;
* interceptor reordering;
* authorization token reuse;
* subrequest privilege inheritance;
* retry replay;
* Worker state leakage.

---

# 259. Dynamic vs compiled equivalence

Todos los controles deberán producir el mismo resultado en ambos modos:

```text
Dynamic security plan
Compiled security plan
```

---

# 260. Worker testing sequence

```text
Request A: User A / Tenant A
    ↓
Cleanup
    ↓
Request B: User B / Tenant B
    ↓
Assert no A state visible
```

---

# 261. Security fuzzing

Se recomienda fuzzing para:

* route parameters;
* JSON hydration;
* headers;
* filenames;
* nested DTOs;
* alias graphs;
* parameter source maps.

---

# 262. ADR-023

**La resolución de controladores operará únicamente sobre targets confiables y registrados.**

---

# 263. ADR-024

**Los métodos públicos no estarán expuestos salvo declaración explícita.**

---

# 264. ADR-025

**La exposición de una acción podrá vincularse a firmas de ruta específicas.**

---

# 265. ADR-026

**Las fuentes de parámetros serán explícitas en strict mode.**

---

# 266. ADR-027

**Los conflictos entre fuentes de parámetros producirán rechazo, no precedencia silenciosa.**

---

# 267. ADR-028

**La hydration de DTOs será schema-driven y allowlist-only.**

---

# 268. ADR-029

**El polimorfismo de DTO estará deshabilitado por defecto.**

---

# 269. ADR-030

**El model binding aplicará tenant scope antes del lookup efectivo.**

---

# 270. ADR-031

**Todo modelo resuelto requerirá autorización de recurso cuando la ruta esté protegida.**

---

# 271. ADR-032

**Los uploads permanecerán en cuarentena hasta completar validación.**

---

# 272. ADR-033

**Los interceptores de seguridad ocuparán fases reservadas no reordenables por extensiones comunes.**

---

# 273. ADR-034

**Los argumentos serán sellados antes de la invocación.**

---

# 274. ADR-035

**Toda invocación requerirá un token de decisión vinculado al target y execution.**

---

# 275. ADR-036

**Las decisiones de autorización no se heredarán automáticamente en subrequests.**

---

# 276. ADR-037

**Los retries de escritura requerirán idempotencia o garantía equivalente.**

---

# 277. ADR-038

**Los Workers validarán sus invariantes de seguridad después de cada request.**

---

# 278. ADR-039

**Un Worker con fuga de contexto no recuperable será terminado.**

---

# 279. ADR-040

**Los contextos globales se resolverán desde execution scope y nunca mediante estado estático persistente.**

---

# 280. Implementación V1

La V1 deberá incluir:

* secure controller resolution;
* explicit action exposure;
* namespace policies;
* source-aware parameter security;
* strict scalar coercion;
* DTO allowlist;
* compiled hydration plans;
* tenant-aware model binding;
* upload quarantine;
* phased authorization;
* tenant context;
* mandatory security interceptors;
* sealed arguments;
* invocation decision tokens;
* subrequest depth guard;
* recursion detection;
* retry budgets;
* Worker reset and leak detection.

---

# 281. Fuera de PART 02

Se desarrollará posteriormente:

* artifact signing;
* build trust;
* manifest security;
* preload security;
* deployment hardening;
* supply chain;
* OPcache trust;
* HTTP response hardening;
* CSRF;
* CORS;
* cookies;
* CSP;
* streaming transport;
* observability;
* security testing avanzado;
* incident response completo.

---

# 282. Flujo seguro de runtime

```text
Trusted Route Match
        │
        ▼
Resolve Registered Controller Target
        │
        ▼
Validate Explicit Action Exposure
        │
        ▼
Create Security Context
        │
        ▼
Pre-Binding Authorization
        │
        ▼
Resolve and Validate Parameters
        │
        ├── DTO Hydration
        │
        ├── Model Binding
        │
        └── File Quarantine
        │
        ▼
Resource Authorization
        │
        ▼
Validate Mandatory Interceptors
        │
        ▼
Seal Arguments
        │
        ▼
Issue Authorization Decision Token
        │
        ▼
Invocation Guard
        │
        ▼
Controller Invocation
        │
        ▼
Lifecycle Finalization
        │
        ▼
Worker Security Reset
```

---

# 283. Conclusión de PART 02

Esta parte define las defensas operativas que impiden que una petición no confiable controle el runtime de Controllers.

Las piezas principales serán:

```text
SecureControllerResolver
ControllerExposureRegistry
ParameterSecurityGuard
DTOHydrationSecurityGuard
SecureModelBinder
RuntimeAuthorizationPipeline
TenantContextGuard
InterceptorSecurityValidator
ControllerInvocationSecurityGuard
WorkerSecurityResetter
```

El diseño mantiene las mismas garantías en:

* ejecución dinámica;
* artifacts compilados;
* subrequests;
* retries;
* FrankenPHP Workers persistentes.

---

# 284. Siguiente parte

```text
CONTROLLER_SECURITY_MODEL_PART_03.md
```

## Compilation & Artifact Security

Incluirá:

* compilation trust boundaries;
* secure code generation;
* artifact schemas;
* fingerprints;
* signatures;
* manifest trust;
* build identity;
* build activation;
* rollback security;
* downgrade protection;
* content-addressed artifacts;
* artifact loaders;
* path security;
* OPcache;
* preload;
* cache poisoning;
* deployment permissions;
* supply-chain controls;
* package compiler trust;
* remote caches;
* distributed deployments;
* artifact revocation;
* security testing;
* ADRs.
