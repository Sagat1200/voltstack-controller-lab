# CONTROLLER_SECURITY_MODEL.md

## PART 01 — Security Foundations & Threat Model

**Versión:** 1.0
**Estado:** Draft arquitectónico
**Módulo:** `VoltStack\Quantum\Controllers\Security`
**Ámbito:** Fundamentos de seguridad, límites de confianza, activos, amenazas, superficie de ataque, políticas y pipeline transversal
**Integraciones principales:** Routing, ControllerResolver, Metadata, Parameters, Interceptors, Invoker, Transformation, Transport, Exceptions, Lifecycle, Observability, Compilation Framework, Workers persistentes y FrankenPHP

---

# 1. Introducción

El **Controller Security Model** define el modelo integral de seguridad del subsistema Controllers de VoltStack.

Su propósito no es agregar una única capa de autorización alrededor de los controladores, sino establecer una arquitectura transversal que proteja todo el ciclo de ejecución:

```text
Request
    ↓
Routing
    ↓
Controller Resolution
    ↓
Metadata Resolution
    ↓
Parameter Resolution
    ↓
Interceptor Pipeline
    ↓
Controller Invocation
    ↓
Result Transformation
    ↓
Response Transport
    ↓
Cleanup
```

Cada fase introduce distintos riesgos:

* invocación de métodos no autorizados;
* inyección de parámetros;
* escalamiento de privilegios;
* acceso cruzado entre tenants;
* contaminación entre requests;
* filtración de información;
* carga de artefactos compilados alterados;
* manipulación de headers;
* abuso de streaming;
* persistencia de estado en Workers.

El modelo deberá proteger tanto el modo dinámico como el modo compilado.

---

# 2. Objetivo principal

Garantizar que una ejecución de controlador:

* invoque únicamente código permitido;
* utilice únicamente parámetros autorizados;
* respete autenticación y autorización;
* mantenga aislamiento entre usuarios, tenants y requests;
* no exponga información sensible;
* cargue únicamente artefactos confiables;
* produzca respuestas seguras;
* mantenga trazabilidad suficiente;
* falle de forma segura;
* libere recursos en todos los escenarios.

---

# 3. Seguridad como propiedad transversal

La seguridad no deberá residir únicamente en middleware.

```text
Routing Security
Controller Security
Parameter Security
Authorization Security
Execution Security
Result Security
Transport Security
Artifact Security
Worker Security
Observability Security
```

Cada subsistema será responsable de proteger sus propios límites.

---

# 4. Principios fundamentales

VoltStack seguirá estos principios:

* Secure by default.
* Deny by default.
* Explicit over implicit.
* Least privilege.
* Defense in depth.
* Fail closed.
* Immutable security decisions.
* Request isolation.
* Tenant isolation.
* Build integrity.
* Trusted compilation.
* Safe observability.
* Minimal information disclosure.
* Deterministic enforcement.
* Security policy centralization.
* Runtime-independent semantics.

---

# 5. Secure by default

Una capacidad insegura no deberá habilitarse implícitamente.

Ejemplos:

* métodos privados nunca invocables;
* binding automático restringido;
* payloads no confiables nunca convertidos arbitrariamente en objetos;
* redirects externos deshabilitados por defecto;
* cookies seguras por defecto;
* headers sensibles no sobrescribibles;
* artifacts externos no cargables;
* debugging detallado deshabilitado en producción.

---

# 6. Deny by default

Cuando no exista una decisión explícita:

```text
No controller exposure → deny
No authorization rule → deny when protected
No tenant scope → deny tenant-bound access
No artifact trust → reject
No serializer support → reject
No transport policy → use hardened default
```

---

# 7. Least privilege

Cada componente tendrá acceso únicamente a lo necesario.

Ejemplos:

* un Parameter Resolver no necesita acceso al Transport;
* un Invoker no necesita modificar metadata;
* Observability no necesita argumentos completos;
* un Interceptor de autorización no necesita emitir respuestas directamente;
* un artifact loader no necesita acceso al Request completo.

---

# 8. Defense in depth

Una misma amenaza deberá ser contenida en varias capas.

Ejemplo: invocación de método no permitido.

```text
Route compiler
    ↓
Controller Resolver
    ↓
Metadata policy
    ↓
Invocation guard
    ↓
Compiled artifact validator
```

Aunque falle una capa, las demás deberán impedir la ejecución.

---

# 9. Fail closed

Cuando una decisión de seguridad no pueda completarse:

```text
Authorization provider unavailable
Tenant context unresolved
Security metadata invalid
Artifact signature mismatch
Sanitizer failure
```

El sistema deberá rechazar la operación, salvo políticas explícitas y seguras de degradación.

---

# 10. Separación entre seguridad y conveniencia

Las abstracciones de conveniencia no deberán reducir garantías.

Ejemplos:

* auto-binding no deberá ignorar scopes;
* atributos no deberán exponer métodos automáticamente;
* alias no deberán saltarse validaciones;
* compiled plans no deberán evitar autorización;
* fakes de testing no deberán cambiar semántica productiva.

---

# 11. Objetivos de seguridad

El modelo protegerá:

```text
Confidentiality
Integrity
Availability
Authenticity
Authorization
Isolation
Traceability
Non-repudiation when applicable
```

---

# 12. Confidencialidad

Se deberá evitar exposición de:

* credenciales;
* tokens;
* cookies;
* session IDs;
* datos personales;
* datos de otros tenants;
* argumentos internos;
* stack traces;
* paths internos;
* configuración;
* secretos de infraestructura;
* artefactos internos;
* información de compilación sensible.

---

# 13. Integridad

Se deberá impedir modificación no autorizada de:

* route definitions;
* metadata;
* authorization policies;
* parameter plans;
* compiled artifacts;
* manifests;
* transport policies;
* headers de seguridad;
* execution state;
* observability context.

---

# 14. Disponibilidad

El sistema deberá resistir:

* requests excesivamente grandes;
* payloads profundamente anidados;
* binding costoso;
* reflexión repetitiva;
* rutas recursivas;
* streams infinitos;
* subrequests recursivos;
* cache stampede;
* compilaciones concurrentes;
* abuso de retries;
* interceptores bloqueantes;
* exhaustión de Workers.

---

# 15. Autenticidad

La identidad utilizada para decisiones deberá provenir de fuentes confiables.

```text
Authentication system
    ↓
AuthenticatedPrincipal
    ↓
SecurityContext
    ↓
Authorization decision
```

Nunca deberá confiarse directamente en:

* headers arbitrarios;
* body parameters;
* query strings;
* client-provided tenant IDs;
* metadata enviada por frontend.

---

# 16. Autorización

Toda acción protegida deberá validar:

* principal;
* acción;
* recurso;
* tenant;
* contexto;
* política;
* condiciones;
* resultado.

---

# 17. Aislamiento

El aislamiento se aplicará entre:

* requests;
* usuarios;
* sesiones;
* tenants;
* subrequests;
* ejecuciones;
* builds;
* Workers;
* streams;
* contextos de observabilidad.

---

# 18. Trazabilidad

Las decisiones críticas deberán poder auditarse sin registrar información sensible.

Se podrá registrar:

* policy ID;
* decision;
* principal pseudonimizado;
* resource type;
* action;
* execution ID;
* tenant ID seguro;
* reason code.

---

# 19. Modelo de confianza

VoltStack distinguirá entre componentes confiables y no confiables.

```text
Untrusted
Semi-trusted
Trusted
Privileged
```

---

# 20. Entradas no confiables

Se considerarán no confiables:

* URL;
* route parameters;
* query;
* headers;
* cookies;
* body;
* JSON;
* multipart;
* archivos;
* client hints;
* SPA payload;
* WebSocket messages;
* SSE reconnection data;
* user-controlled metadata;
* external service responses.

---

# 21. Componentes semi-confiables

Podrán considerarse semi-confiables:

* application controllers;
* package controllers;
* custom interceptors;
* custom parameter resolvers;
* custom metadata providers;
* third-party extensions.

Aunque formen parte de la aplicación, deberán operar bajo contratos y límites.

---

# 22. Componentes confiables

Componentes de confianza:

* Controller Security Engine;
* Authorization Engine;
* Artifact Validator;
* Security Policy Registry;
* Tenant Scope Engine;
* Security Context Factory;
* Core Sanitizers;
* Core Transport Guards.

---

# 23. Componentes privilegiados

Componentes privilegiados:

* build activator;
* manifest signer;
* preload generator;
* artifact deployment manager;
* secret provider;
* worker lifecycle manager.

Su acceso deberá ser limitado.

---

# 24. Trust boundaries

Los límites principales serán:

```text
Client → HTTP Server
HTTP Server → Request Object
Request → Routing
Routing → Controller Pipeline
Pipeline → Application Code
Application Code → Data Layer
Pipeline → Transport
Build Process → Runtime
Worker N → Worker M
Tenant A → Tenant B
Observability → External Backend
```

---

# 25. Boundary: Client to Server

Amenazas:

* malformed requests;
* request smuggling;
* header injection;
* oversized payloads;
* invalid encoding;
* protocol confusion;
* slow clients;
* path ambiguity.

Controles:

* servidor endurecido;
* límites;
* normalización;
* validación;
* timeouts;
* parsing estricto.

---

# 26. Boundary: Server to Request Object

El Request deberá ser una representación normalizada.

No deberá conservar simultáneamente múltiples interpretaciones ambiguas de:

* path;
* host;
* scheme;
* content length;
* transfer encoding;
* headers duplicados.

---

# 27. Boundary: Request to Routing

Routing deberá recibir:

* path normalizado;
* host validado;
* método permitido;
* scheme confiable;
* proxy metadata validada.

Routing no deberá confiar en headers de proxy sin configuración explícita.

---

# 28. Boundary: Routing to Controllers

Routing únicamente podrá producir targets validados.

```text
Route Match
    ↓
Validated Controller Target
    ↓
Controller Resolver
```

No deberá aceptar controller class o method directamente desde input del usuario.

---

# 29. Boundary: Pipeline to Application Code

Antes de entrar a código de aplicación deberán completarse:

* route validation;
* controller exposure validation;
* security metadata resolution;
* authentication;
* authorization;
* tenant resolution;
* parameter validation.

---

# 30. Boundary: Application Code to Data Layer

Controllers no deberán asumir que el acceso a datos está autorizado únicamente porque el objeto fue resuelto.

El Data Layer deberá mantener:

* tenant filters;
* resource scopes;
* policy enforcement cuando aplique;
* safe queries.

---

# 31. Boundary: Runtime Build

Los artifacts compilados deberán provenir de un build confiable.

```text
Build
    ↓
Validation
    ↓
Signing optional
    ↓
Atomic activation
    ↓
Runtime loading
```

---

# 32. Boundary: Worker Persistence

El Worker es confiable únicamente mientras mantenga aislamiento.

Después de una ejecución deberá eliminar:

* principal;
* tenant;
* request;
* response;
* arguments;
* result;
* exception;
* trace context;
* temporary security decisions.

---

# 33. Boundary: Observability Export

Antes de exportar datos:

```text
Signal
    ↓
Sanitization
    ↓
Cardinality Guard
    ↓
Policy Filter
    ↓
Exporter
```

---

# 34. Activos protegidos

El modelo deberá identificar activos de seguridad.

---

# 35. Activos de identidad

* user identity;
* service identity;
* API client identity;
* session identity;
* worker identity;
* tenant identity;
* impersonation context.

---

# 36. Activos de autorización

* roles;
* permissions;
* policies;
* resource scopes;
* security metadata;
* authorization decisions;
* deny reasons;
* delegated capabilities.

---

# 37. Activos de datos

* domain entities;
* DTO contents;
* uploaded files;
* generated documents;
* stream contents;
* internal exceptions;
* private response fields;
* SPA state.

---

# 38. Activos de ejecución

* controller target;
* resolved parameters;
* interceptors;
* invocation plan;
* lifecycle state;
* cancellation state;
* cleanup resources.

---

# 39. Activos compilados

* artifacts;
* manifests;
* fingerprints;
* signatures;
* build pointers;
* preload files;
* dependency graphs;
* route maps.

---

# 40. Activos de infraestructura

* secrets;
* database credentials;
* API keys;
* filesystem paths;
* internal hosts;
* worker configuration;
* OPcache configuration;
* deployment credentials.

---

# 41. Activos de observabilidad

* logs;
* traces;
* metrics;
* profiling data;
* correlation IDs;
* error fingerprints;
* timelines.

---

# 42. Actores del modelo

```text
Anonymous client
Authenticated user
Privileged user
Tenant administrator
Application developer
Package author
System operator
Build system
Worker process
External service
Malicious insider
Compromised dependency
```

---

# 43. Anonymous client

Puede intentar:

* descubrir rutas;
* explotar binding;
* generar errores;
* abusar de uploads;
* provocar consumo excesivo;
* obtener información interna.

---

# 44. Authenticated user

Puede intentar:

* escalar privilegios;
* acceder a recursos ajenos;
* modificar tenant IDs;
* explotar DTO hydration;
* abusar de endpoints internos;
* extraer información por errores.

---

# 45. Privileged user

Puede intentar:

* usar permisos fuera de contexto;
* abusar de impersonation;
* acceder a otro tenant;
* ejecutar acciones administrativas no auditadas.

---

# 46. Package author

Un paquete puede introducir:

* resolver inseguro;
* interceptor malicioso;
* metadata provider invasivo;
* exporter que filtre datos;
* controller expuesto accidentalmente.

---

# 47. System operator

Puede afectar:

* artifacts;
* manifests;
* build activation;
* configuration;
* Workers;
* logs.

El modelo deberá minimizar confianza implícita incluso en operaciones.

---

# 48. Build system

El sistema de build es un actor privilegiado.

Puede:

* generar artifacts;
* firmar manifests;
* activar builds;
* producir preload.

Su compromiso comprometería integridad del runtime.

---

# 49. External services

Sus respuestas deberán considerarse no confiables aunque el servicio sea legítimo.

Podrán contener:

* payloads malformados;
* valores inesperados;
* datos manipulados;
* errores sensibles.

---

# 50. Categorías de amenazas

Se utilizará una clasificación inspirada en STRIDE:

```text
Spoofing
Tampering
Repudiation
Information Disclosure
Denial of Service
Elevation of Privilege
```

---

# 51. Spoofing

Ejemplos:

* falsificar usuario;
* falsificar tenant;
* falsificar proxy headers;
* falsificar API client;
* reutilizar session token;
* alterar correlation IDs.

---

# 52. Tampering

Ejemplos:

* modificar artifact;
* cambiar manifest;
* alterar metadata;
* manipular route parameters;
* modificar headers de seguridad;
* alterar execution context.

---

# 53. Repudiation

Ejemplos:

* negar acción administrativa;
* falta de audit trail;
* decisiones sin policy ID;
* impersonation no registrada;
* cambios de build sin registro.

---

# 54. Information Disclosure

Ejemplos:

* stack traces;
* secrets en logs;
* datos de tenant;
* headers internos;
* exception messages;
* paths;
* model attributes ocultos;
* SPA state sensible.

---

# 55. Denial of Service

Ejemplos:

* body gigante;
* JSON profundo;
* múltiples uploads;
* route recursion;
* retries infinitos;
* stream infinito;
* exception storm;
* expensive binding;
* artifact cache flooding.

---

# 56. Elevation of Privilege

Ejemplos:

* invocar método no expuesto;
* bypass de policy;
* model binding fuera de scope;
* tenant override;
* manipulación de metadata;
* interceptor order abuse;
* compiled plan obsoleto.

---

# 57. Superficie de ataque

La superficie se divide en:

```text
Transport surface
Routing surface
Resolution surface
Parameter surface
Metadata surface
Authorization surface
Invocation surface
Transformation surface
Exception surface
Compilation surface
Worker surface
Observability surface
Extension surface
```

---

# 58. Transport surface

Incluye:

* HTTP parser;
* headers;
* cookies;
* body;
* multipart;
* compression;
* range;
* streaming;
* SSE;
* WebSocket future support.

---

# 59. Routing surface

Incluye:

* path matching;
* host matching;
* route parameters;
* route names;
* controller references;
* route cache;
* frontend route manifest.

---

# 60. Controller resolution surface

Incluye:

* aliases;
* service IDs;
* class strings;
* methods;
* closures;
* invokable controllers;
* static calls;
* compiled plans.

---

# 61. Parameter surface

Incluye:

* coerción;
* hydration;
* model binding;
* service injection;
* enum resolution;
* variadics;
* union types;
* files;
* tenant context.

---

# 62. Metadata surface

Incluye:

* attributes;
* configuration;
* route metadata;
* package metadata;
* dynamic providers;
* merge precedence;
* compiled metadata.

---

# 63. Authorization surface

Incluye:

* policies;
* permissions;
* roles;
* tenant scopes;
* ownership;
* feature permissions;
* impersonation;
* delegation.

---

# 64. Invocation surface

Incluye:

* callable construction;
* method visibility;
* argument order;
* controller scope;
* service lifecycle;
* retries;
* transactions.

---

# 65. Transformation surface

Incluye:

* serialization;
* views;
* SPA protocol;
* resources;
* redirects;
* downloads;
* binary responses;
* XML;
* Markdown.

---

# 66. Exception surface

Incluye:

* classification;
* mapping;
* rendering;
* reporting;
* recovery;
* emergency fallback;
* public error IDs.

---

# 67. Compilation surface

Incluye:

* generated PHP;
* manifests;
* fingerprints;
* signatures;
* artifact paths;
* preload;
* build activation;
* rollback.

---

# 68. Worker surface

Incluye:

* request reuse;
* static state;
* singleton contamination;
* mutable caches;
* leaked principals;
* leaked tenant context;
* memory growth.

---

# 69. Observability surface

Incluye:

* logs;
* traces;
* metrics;
* event payloads;
* profiler snapshots;
* exporters;
* dashboards.

---

# 70. Extension surface

Incluye:

* custom resolvers;
* custom interceptors;
* custom compilers;
* custom transport adapters;
* custom exporters;
* third-party packages.

---

# 71. Threat: Dynamic controller injection

Ataque:

```text
Client input
    ↓
Controller class selection
    ↓
Arbitrary code invocation
```

Mitigación:

* controller targets únicamente desde route definitions;
* allowlist;
* metadata exposure;
* compiled target validation;
* no class names desde request.

---

# 72. Threat: Method exposure

Riesgo:

Un controller contiene métodos públicos auxiliares.

Mitigación:

```text
Public method ≠ exposed action
```

Solo serán invocables métodos registrados explícitamente como acciones.

---

# 73. Threat: Parameter source confusion

Ejemplo:

```text
$id from route
$id from body
$id from query
```

Mitigación:

* source explícita;
* precedence fija;
* conflicto detectado;
* binding plan compilado;
* no mezcla silenciosa.

---

# 74. Threat: Mass assignment through DTO hydration

Mitigación:

* DTO schemas explícitos;
* propiedades permitidas;
* extra keys policy;
* constructor controlado;
* nested depth limits;
* coerción segura.

---

# 75. Threat: Unauthorized model binding

Ejemplo:

```text
/orders/123
```

El modelo existe, pero pertenece a otro tenant.

Mitigación:

```text
Route binding
    ↓
Tenant scope
    ↓
Authorization scope
    ↓
Model resolution
```

---

# 76. Threat: Tenant ID manipulation

No se confiará en un tenant ID proporcionado directamente por el cliente.

El tenant deberá derivarse de una fuente autenticada:

* host validado;
* session;
* signed token;
* trusted identity provider;
* server-side mapping.

---

# 77. Threat: Metadata privilege escalation

Un package podría agregar metadata de autorización permisiva.

Mitigación:

* schemas;
* trusted providers;
* immutable security keys;
* merge rules restrictivas;
* deny precedence;
* registry freeze.

---

# 78. Threat: Interceptor order manipulation

Un interceptor malicioso podría ejecutarse antes de autorización.

Mitigación:

* security phases reservadas;
* priorities restringidas;
* mandatory interceptors;
* compiled order validation;
* no bypass.

---

# 79. Threat: Invocation plan tampering

Mitigación:

* build manifests;
* artifact fingerprints;
* signatures;
* read-only files;
* active build pinning;
* runtime validation.

---

# 80. Threat: Response data leakage

Mitigación:

* serializers explícitos;
* hidden fields;
* resource transformers;
* response classification;
* content policy;
* no serialización arbitraria de objetos.

---

# 81. Threat: Open redirect

Mitigación:

* URLs internas por defecto;
* external redirect allowlist;
* normalized destinations;
* signed redirects cuando aplique;
* scheme restrictions.

---

# 82. Threat: Header injection

Mitigación:

* validar nombres;
* rechazar CR/LF;
* valores tipados;
* protected header registry;
* immutable security headers.

---

# 83. Threat: Cookie weakening

Una respuesta no deberá reducir cookies protegidas accidentalmente.

Mitigación:

* secure defaults;
* HttpOnly;
* SameSite;
* path/domain restrictions;
* prefix support;
* policy guard.

---

# 84. Threat: Exception disclosure

Mitigación:

* public vs internal representation;
* sanitization;
* generic production messages;
* public error IDs;
* stack traces solo internos;
* reporter separation.

---

# 85. Threat: Log injection

Mitigación:

* structured logs;
* encoding;
* newline sanitization;
* field size limits;
* no concatenación directa;
* controlled correlation IDs.

---

# 86. Threat: Worker state leakage

Mitigación:

* request scopes;
* execution scopes;
* reset contracts;
* immutable worker caches;
* leak detection;
* worker disposition policy.

---

# 87. Threat: Build downgrade

Un operador o atacante podría activar un build antiguo vulnerable.

Mitigación:

* build policy;
* minimum allowed version;
* signed manifest;
* deployment audit;
* rollback restrictions;
* revoked build list.

---

# 88. Threat: Artifact substitution

Mitigación:

```text
Artifact ID
+ Build ID
+ Fingerprint
+ Signature
+ Manifest membership
```

Todos deberán coincidir.

---

# 89. Threat: Cache poisoning

Mitigación:

* build-aware keys;
* typed entries;
* immutable values;
* no user-controlled cache keys sin normalización;
* validation on load;
* namespace isolation.

---

# 90. Threat: Recursive execution

Mitigación:

* maximum depth;
* recursion detection;
* execution graph;
* cancellation;
* per-execution budget.

---

# 91. Threat: Retry amplification

Mitigación:

* retry limits;
* idempotency checks;
* retry budget;
* non-retryable categories;
* timeout propagation;
* observability.

---

# 92. Threat: Streaming abuse

Mitigación:

* duration limits;
* idle timeouts;
* byte limits;
* chunk limits;
* cancellation;
* disconnect detection;
* resource ownership.

---

# 93. Threat: Upload abuse

Mitigación:

* file size limits;
* count limits;
* MIME verification;
* temporary isolation;
* filename normalization;
* extension policy;
* malware scanning integration;
* cleanup.

---

# 94. Threat: Content-type confusion

Mitigación:

* declared type validation;
* actual format inspection;
* no trust en extensión;
* explicit parser selection;
* strict negotiation.

---

# 95. Threat: Request smuggling

Principalmente se mitigará en servidor y proxy, pero VoltStack deberá:

* rechazar inconsistencias;
* no reinterpretar framing;
* confiar en una única request normalizada;
* validar proxy chain.

---

# 96. Threat: Host header attacks

Mitigación:

* allowed hosts;
* trusted proxies;
* canonical host;
* safe URL generation;
* tenant host mapping;
* reject unknown hosts.

---

# 97. Threat: CSRF

Aplicable a acciones autenticadas basadas en cookies.

Mitigación:

* CSRF tokens;
* SameSite cookies;
* origin validation;
* method restrictions;
* idempotency awareness.

---

# 98. Threat: CORS misconfiguration

Mitigación:

* explicit origins;
* no wildcard con credentials;
* method allowlist;
* header allowlist;
* preflight validation;
* route-aware policies.

---

# 99. Threat: SSRF through controllers

Mitigación transversal:

* URL validation;
* network policy;
* hostname/IP restrictions;
* metadata service protection;
* redirect limits;
* DNS rebinding awareness.

---

# 100. Threat: Deserialization attacks

VoltStack no deberá usar deserialización insegura para input de usuario.

Se evitará:

* `unserialize()` sobre input;
* object instantiation arbitraria;
* polymorphic DTOs no permitidos;
* class names provenientes del cliente.

---

# 101. Threat: Prototype-like property pollution

Aunque PHP no tenga prototype chain como JavaScript, deberá evitarse:

* hydration de propiedades arbitrarias;
* magic setters sin allowlist;
* keys reservadas;
* metadata injection.

---

# 102. Security Context

Toda ejecución tendrá un contexto de seguridad.

```php
final readonly class ControllerSecurityContext
{
    public function __construct(
        public Principal $principal,
        public ?TenantIdentity $tenant,
        public AuthenticationStrength $authenticationStrength,
        public SecurityAttributes $attributes,
        public SecurityDecisionCache $decisions,
        public string $executionId,
    ) {
    }
}
```

---

# 103. Principal

```php
interface PrincipalInterface
{
    public function id(): string;

    public function type(): PrincipalType;

    public function authenticated(): bool;

    public function claims(): SafeClaimSet;
}
```

---

# 104. Principal types

```php
enum PrincipalType: string
{
    case Anonymous = 'anonymous';
    case User = 'user';
    case Service = 'service';
    case ApiClient = 'api_client';
    case System = 'system';
    case ImpersonatedUser = 'impersonated_user';
}
```

---

# 105. Authentication strength

```php
enum AuthenticationStrength: int
{
    case Anonymous = 0;
    case Password = 10;
    case Token = 20;
    case MultiFactor = 30;
    case HardwareBacked = 40;
}
```

Las políticas podrán requerir un nivel mínimo.

---

# 106. Tenant identity

```php
final readonly class TenantIdentity
{
    public function __construct(
        public string $id,
        public string $source,
        public bool $verified,
    ) {
    }
}
```

---

# 107. Security attributes

Podrán incluir:

* IP classification;
* device trust;
* authentication age;
* session risk;
* geo risk;
* API client;
* impersonation;
* feature policy;
* network zone.

No deberán aceptar valores directos sin validación.

---

# 108. Security Context Factory

```php
interface ControllerSecurityContextFactoryInterface
{
    public function create(
        RequestInterface $request,
        ControllerExecutionContext $execution
    ): ControllerSecurityContext;
}
```

---

# 109. Security context lifecycle

```text
Create
    ↓
Validate
    ↓
Freeze
    ↓
Use during execution
    ↓
Destroy on cleanup
```

No deberá mutarse durante una decisión crítica.

---

# 110. Impersonation context

Cuando exista impersonation deberán conservarse:

* actor original;
* actor efectivo;
* reason;
* authorization;
* start time;
* audit reference.

---

# 111. Security Decision

```php
final readonly class SecurityDecision
{
    public function __construct(
        public SecurityDecisionEffect $effect,
        public string $policyId,
        public string $reasonCode,
        public array $obligations,
    ) {
    }
}
```

---

# 112. Decision effects

```php
enum SecurityDecisionEffect: string
{
    case Allow = 'allow';
    case Deny = 'deny';
    case Abstain = 'abstain';
    case Challenge = 'challenge';
}
```

---

# 113. Abstain semantics

`Abstain` no equivaldrá a `Allow`.

La política final deberá resolver:

```text
Any explicit deny → deny
No allow → deny
At least one allow and no deny → allow
```

salvo estrategia explícita distinta.

---

# 114. Security obligations

Una decisión podrá incluir obligaciones:

* redact fields;
* require MFA;
* apply tenant scope;
* audit action;
* mask response;
* disable caching;
* force secure transport;
* set security headers.

---

# 115. Security Policy Registry

```php
interface ControllerSecurityPolicyRegistryInterface
{
    public function register(
        ControllerSecurityPolicyInterface $policy
    ): void;

    public function resolve(
        string $policyId
    ): ControllerSecurityPolicyInterface;

    public function freeze(): void;
}
```

---

# 116. Security Policy

```php
interface ControllerSecurityPolicyInterface
{
    public function id(): string;

    public function evaluate(
        SecurityEvaluationRequest $request
    ): SecurityDecision;
}
```

---

# 117. Security evaluation request

```php
final readonly class SecurityEvaluationRequest
{
    public function __construct(
        public ControllerSecurityContext $security,
        public ControllerTarget $target,
        public string $action,
        public mixed $resource,
        public MetadataBag $metadata,
    ) {
    }
}
```

---

# 118. Security Decision Engine

```php
interface ControllerSecurityDecisionEngineInterface
{
    public function decide(
        SecurityEvaluationRequest $request
    ): SecurityDecision;
}
```

---

# 119. Pipeline de seguridad

```text
Request
    ↓
Request Security Validation
    ↓
Identity Resolution
    ↓
Tenant Resolution
    ↓
Controller Exposure Validation
    ↓
Security Metadata Resolution
    ↓
Pre-Binding Authorization
    ↓
Secure Parameter Resolution
    ↓
Resource Authorization
    ↓
Invocation Guard
    ↓
Result Security Policy
    ↓
Transport Security Policy
    ↓
Audit and Cleanup
```

---

# 120. Security stages

```text
ValidateRequestSecurityStage
ResolvePrincipalStage
ResolveTenantStage
ValidateControllerExposureStage
ResolveSecurityMetadataStage
EvaluatePreBindingAuthorizationStage
EnforceParameterSecurityStage
EvaluateResourceAuthorizationStage
ValidateInvocationStage
ApplyResultSecurityStage
ApplyTransportSecurityStage
FinalizeSecurityAuditStage
```

---

# 121. Pre-binding authorization

Algunas decisiones deberán realizarse antes de resolver modelos o DTOs costosos.

Ejemplo:

```text
Can user access administration module?
```

Si no, no deberá ejecutarse model binding.

---

# 122. Resource authorization

Después de resolver un recurso:

```text
Can principal update Order #123?
```

Esta autorización deberá validar:

* tenant;
* ownership;
* status;
* policy;
* requested action.

---

# 123. Invocation guard

Última defensa antes del controller:

```php
interface ControllerInvocationSecurityGuardInterface
{
    public function assertInvocable(
        ControllerInvocationRequest $request,
        ControllerSecurityContext $security
    ): void;
}
```

---

# 124. Result security

Antes de transformar una respuesta deberán aplicarse políticas:

* field filtering;
* PII masking;
* tenant verification;
* cache restrictions;
* classification labels;
* export restrictions.

---

# 125. Transport security

Antes de emitir:

* security headers;
* cookie policies;
* cache control;
* content type;
* download policies;
* redirect policies;
* framing policies.

---

# 126. Security metadata

Las rutas y controladores podrán declarar:

```text
authentication_required
minimum_authentication_strength
policies
permissions
tenant_required
resource_scope
csrf
cors
rate_limit
sensitive_response
audit
allowed_transports
```

---

# 127. Security metadata immutability

Las keys críticas no podrán ser debilitadas por metadata de menor prioridad.

Ejemplo:

```text
Global policy: MFA required
Method metadata: MFA false
```

El override deberá rechazarse.

---

# 128. Security merge rules

Tipos de merge:

```text
Most restrictive wins
Union
Intersection
Append
Immutable
Exact match required
```

---

# 129. Most restrictive wins

Aplicable a:

* authentication strength;
* transport security;
* tenant requirement;
* cache restrictions;
* response sensitivity.

---

# 130. Union

Aplicable a obligaciones:

```text
audit + mask + no-cache
```

---

# 131. Intersection

Aplicable a allowlists:

```text
Global allowed methods
∩ Module allowed methods
∩ Route allowed methods
```

---

# 132. Immutable security metadata

Algunas keys solo podrán definirse en niveles confiables:

* system policies;
* artifact trust;
* privileged routes;
* emergency bypass;
* internal-only classification.

---

# 133. Security Metadata Validator

```php
interface ControllerSecurityMetadataValidatorInterface
{
    public function validate(
        SecurityMetadata $metadata,
        SecurityMetadataOrigin $origin
    ): SecurityMetadataValidationResult;
}
```

---

# 134. Security policy compilation

Las políticas estáticas podrán convertirse en:

```text
CompiledControllerSecurityPlan
```

Este plan deberá formar parte del execution bundle.

---

# 135. CompiledControllerSecurityPlan

```php
final readonly class CompiledControllerSecurityPlan
{
    public function __construct(
        public bool $authenticationRequired,
        public AuthenticationStrength $minimumStrength,
        public bool $tenantRequired,
        public array $preBindingPolicies,
        public array $resourcePolicies,
        public array $invocationGuards,
        public array $resultPolicies,
        public array $transportPolicies,
        public string $fingerprint,
    ) {
    }
}
```

---

# 136. Seguridad dinámica y compilada

El modo compilado no deberá precalcular decisiones dependientes del usuario.

Podrá compilar:

* policy references;
* guards;
* metadata;
* scopes;
* obligations;
* decision strategy.

No podrá compilar:

* resultado final de autorización por usuario;
* tenant actual;
* resource ownership;
* authentication state.

---

# 137. Decision cache

Podrá existir caché por ejecución.

```php
interface SecurityDecisionCacheInterface
{
    public function get(
        SecurityDecisionKey $key
    ): ?SecurityDecision;

    public function put(
        SecurityDecisionKey $key,
        SecurityDecision $decision
    ): void;
}
```

---

# 138. Restricciones del decision cache

Nunca deberá compartirse entre:

* usuarios;
* tenants;
* executions;
* impersonation contexts;
* builds incompatibles.

---

# 139. SecurityDecisionKey

Deberá considerar:

* principal;
* tenant;
* policy;
* action;
* resource identity;
* security context version;
* execution ID cuando aplique.

---

# 140. Security budgets

Cada ejecución podrá tener límites:

```php
final readonly class ControllerSecurityBudget
{
    public function __construct(
        public int $maxPolicyEvaluations,
        public int $maxBindingDepth,
        public int $maxSubrequestDepth,
        public int $maxSecurityEvents,
    ) {
    }
}
```

---

# 141. Policy evaluation limits

Esto evita:

* loops;
* recursive policies;
* expensive policy graphs;
* malicious extensions;
* accidental N+1 authorization.

---

# 142. Security failure model

Errores se clasificarán en:

```text
AuthenticationFailure
AuthorizationDenied
TenantViolation
InputSecurityViolation
ControllerExposureViolation
ArtifactTrustViolation
TransportSecurityViolation
SecurityInfrastructureFailure
```

---

# 143. Authentication failure

Debe producir una representación adecuada sin revelar si un recurso existe cuando eso sea sensible.

---

# 144. Authorization denial

No deberá incluir:

* nombre interno de policy;
* roles faltantes;
* condiciones exactas;
* tenant objetivo;
* detalles de resource ownership.

Estos datos podrán registrarse internamente de forma sanitizada.

---

# 145. Tenant violation

Deberá tratarse como evento de alta relevancia.

Podrá responder:

* 404 para ocultación;
* 403 cuando sea seguro;
* 401 si falta autenticación.

La política será configurable.

---

# 146. Security infrastructure failure

Ejemplos:

* policy registry unavailable;
* tenant resolver failed;
* security metadata corrupta;
* signer unavailable.

En producción deberá fallar cerrado.

---

# 147. Security events

Eventos principales:

```text
controllers.security.context.created
controllers.security.authentication.succeeded
controllers.security.authentication.failed
controllers.security.tenant.resolved
controllers.security.tenant.violation
controllers.security.policy.evaluated
controllers.security.authorization.allowed
controllers.security.authorization.denied
controllers.security.controller.rejected
controllers.security.parameter.rejected
controllers.security.artifact.rejected
controllers.security.transport.hardened
controllers.security.worker.reset
```

---

# 148. Security metrics

Prefijo:

```text
voltstack.controllers.security.*
```

Métricas:

```text
authentication_failures
authorization_denials
tenant_violations
controller_rejections
parameter_rejections
artifact_validation_failures
security_policy_duration
security_context_creation_duration
worker_security_resets
```

---

# 149. Cardinalidad segura

No deberán usarse como labels:

* user ID;
* email;
* token;
* full route parameter;
* resource ID arbitrario;
* exception message.

Sí podrán usarse:

* policy ID controlado;
* route name;
* denial category;
* principal type;
* tenant mode;
* build version.

---

# 150. Auditoría

Acciones auditables:

* cambios administrativos;
* impersonation;
* exportación de datos;
* acceso privilegiado;
* cambio de tenant;
* build activation;
* rollback;
* policy changes;
* security override.

---

# 151. Audit record

```php
final readonly class ControllerSecurityAuditRecord
{
    public function __construct(
        public string $event,
        public string $actorReference,
        public ?string $effectivePrincipalReference,
        public ?string $tenantReference,
        public string $action,
        public string $targetType,
        public string $decision,
        public string $executionId,
        public string $timestamp,
    ) {
    }
}
```

---

# 152. Pseudonimización

Los identificadores en auditoría podrán transformarse mediante:

* hashing con salt;
* tokenización;
* stable pseudonyms;
* external identity references.

---

# 153. Security observability separation

Se distinguirán:

```text
Operational logs
Security logs
Audit logs
Application logs
```

No deberán mezclarse sin política.

---

# 154. Security log integrity

Podrán utilizarse:

* append-only storage;
* restricted access;
* retention policies;
* external export;
* signed batches.

---

# 155. Security configuration

```php
// config/controller_security.php

return [
    'enabled' => true,

    'defaults' => [
        'deny_by_default' => true,
        'fail_closed' => true,
        'authentication_required' => false,
        'tenant_required' => false,
    ],

    'controllers' => [
        'explicit_exposure' => true,
        'allow_static_methods' => false,
        'allow_dynamic_targets' => false,
        'allow_non_public_methods' => false,
    ],

    'metadata' => [
        'freeze' => true,
        'most_restrictive_wins' => true,
        'reject_unsafe_overrides' => true,
    ],

    'authorization' => [
        'cache_per_execution' => true,
        'max_policy_evaluations' => 64,
        'abstain_as_deny' => true,
    ],

    'tenant' => [
        'strict_isolation' => true,
        'trust_client_tenant_id' => false,
        'hide_cross_tenant_resources' => true,
    ],

    'artifacts' => [
        'validate_manifest_membership' => true,
        'validate_fingerprints' => true,
        'require_read_only_builds' => true,
        'allow_runtime_generation' => false,
    ],

    'workers' => [
        'reset_security_context' => true,
        'detect_context_leaks' => true,
        'terminate_on_trust_failure' => true,
    ],

    'observability' => [
        'sanitize' => true,
        'audit_denials' => true,
        'record_sensitive_values' => false,
    ],
];
```

---

# 156. Componentes del módulo

```text
ControllerSecurityManager
ControllerSecurityContext
ControllerSecurityContextFactory
ControllerSecurityDecisionEngine
ControllerSecurityPolicyRegistry
ControllerSecurityMetadataValidator
ControllerInvocationSecurityGuard
ControllerSecurityAuditRecorder
ControllerSecurityBudget
CompiledControllerSecurityPlan
```

---

# 157. ControllerSecurityManager

```php
interface ControllerSecurityManagerInterface
{
    public function initialize(
        RequestInterface $request,
        ControllerExecutionContext $execution
    ): ControllerSecurityContext;

    public function authorize(
        SecurityEvaluationRequest $request
    ): SecurityDecision;

    public function finalize(
        ControllerSecurityContext $context
    ): void;
}
```

---

# 158. Security registry freeze

Antes de comenzar a servir requests:

```text
Register policies
Register guards
Register sanitizers
Register tenant resolvers
Freeze registries
```

---

# 159. Extensiones de seguridad

Las extensiones deberán declarar:

* capabilities;
* required privileges;
* data accessed;
* lifecycle;
* thread/worker safety;
* deterministic behavior;
* failure mode.

---

# 160. Extension capability model

Ejemplo:

```php
enum SecurityExtensionCapability: string
{
    case ReadPrincipal = 'read_principal';
    case ReadTenant = 'read_tenant';
    case ReadMetadata = 'read_metadata';
    case EvaluatePolicy = 'evaluate_policy';
    case AddObligation = 'add_obligation';
    case WriteAudit = 'write_audit';
}
```

---

# 161. Extension sandbox conceptual

PHP no provee sandbox real dentro del mismo proceso.

Por ello se aplicarán:

* contratos;
* capabilities;
* static analysis;
* package trust;
* code review;
* isolation mediante procesos cuando sea necesario.

---

# 162. Security invariants

Invariantes globales:

```text
No controller invocation without validated target
No protected action without authorization
No tenant-bound resource without tenant scope
No compiled artifact outside active build
No security context shared between requests
No sensitive value emitted to observability
No response emitted after fatal security violation
```

---

# 163. Invariante de autenticación

Si una acción requiere autenticación:

```text
principal.authenticated() must be true
```

antes de resolver recursos protegidos.

---

# 164. Invariante de tenant

Si una acción requiere tenant:

```text
security.tenant != null
security.tenant.verified == true
```

---

# 165. Invariante de invocación

```text
target.exposed == true
target.validated == true
authorization.effect == Allow
```

---

# 166. Invariante de artifact

```text
artifact.build_id == execution.build_id
artifact.signature.valid == true
artifact.manifest_member == true
```

---

# 167. Invariante de Worker

Después de cleanup:

```text
current_principal == null
current_tenant == null
current_security_context == null
decision_cache.empty == true
```

---

# 168. Invariante de observabilidad

```text
exported_signal.sanitized == true
```

---

# 169. Security testing requirements

Cada control deberá tener:

* unit test;
* denial test;
* bypass attempt;
* Worker isolation test cuando aplique;
* compiled equivalence test;
* observability sanitization test.

---

# 170. Threat-model-driven testing

Cada amenaza identificada deberá mapearse a:

```text
Threat
    ↓
Control
    ↓
Test
    ↓
Metric
    ↓
Operational response
```

---

# 171. Threat register

El proyecto mantendrá un registro:

```text
Threat ID
Description
Asset
Likelihood
Impact
Controls
Residual risk
Owner
Status
```

---

# 172. Risk levels

```php
enum SecurityRiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
```

---

# 173. Risk evaluation

Podrá considerar:

```text
Likelihood × Impact × Exposure
```

con ajustes por detectabilidad y controles compensatorios.

---

# 174. Critical risks

Ejemplos:

* arbitrary controller execution;
* cross-tenant access;
* artifact code injection;
* authentication bypass;
* Worker context leakage;
* secret disclosure;
* unsafe file execution.

---

# 175. Security hardening profiles

VoltStack podrá ofrecer:

```text
Development
Standard
Strict
High Security
```

---

# 176. Development profile

Permite:

* debug details;
* dynamic compilation;
* relaxed local origin;
* additional diagnostics.

Nunca deberá deshabilitar controles estructurales como:

* controller exposure;
* tenant isolation;
* artifact path validation.

---

# 177. Standard profile

Adecuado para aplicaciones generales.

Incluye:

* secure defaults;
* strict headers;
* authorization;
* safe artifacts;
* Worker reset.

---

# 178. Strict profile

Incluye:

* no dynamic fallback;
* explicit policies;
* strict tenants;
* signed manifests opcionales;
* limited extensions;
* enhanced audit;
* aggressive sanitization.

---

# 179. High Security profile

Podrá incluir:

* mandatory signed artifacts;
* immutable deployments;
* no runtime writes;
* isolated exporters;
* strong authentication;
* mandatory audit;
* short sessions;
* restrictive outbound network.

---

# 180. Secure deployment assumptions

Producción deberá usar:

* `APP_DEBUG=false`;
* filesystem permissions mínimos;
* build directory read-only;
* secrets fuera del repositorio;
* trusted proxies configurados;
* HTTPS obligatorio;
* Workers reciclables;
* OPcache configurado;
* logs restringidos.

---

# 181. Security ownership

Responsabilidades:

```text
Framework core → secure primitives
Application → correct policies
Packages → safe extensions
Operations → secure deployment
Developers → safe controllers
Security team → threat review
```

---

# 182. Security review gates

Cambios que requieren revisión:

* nuevos parameter resolvers;
* nuevos controller target types;
* artifact format changes;
* authorization merge changes;
* tenant resolution changes;
* new transport;
* new file handling;
* new observability exporter.

---

# 183. Deprecación segura

Una capacidad insegura deberá:

1. marcarse deprecated;
2. generar warning;
3. ofrecer migración;
4. deshabilitarse en versión mayor;
5. documentar riesgo.

---

# 184. Compatibility and security

La compatibilidad hacia atrás no deberá mantener vulnerabilidades críticas.

VoltStack podrá introducir cambios incompatibles cuando sean necesarios para corregir riesgos graves.

---

# 185. Incident response hooks

El sistema podrá emitir señales para:

* revoke session;
* terminate Worker;
* disable build;
* block principal;
* quarantine tenant;
* trigger alert;
* escalate audit event.

---

# 186. SecurityIncident

```php
final readonly class ControllerSecurityIncident
{
    public function __construct(
        public string $type,
        public SecurityRiskLevel $severity,
        public string $executionId,
        public array $safeContext,
        public array $recommendedActions,
    ) {
    }
}
```

---

# 187. Worker disposition por seguridad

```text
Reuse
Reset
RestartRecommended
Terminate
Quarantine
```

---

# 188. Terminate scenarios

Un Worker deberá terminar ante:

* artifact trust failure;
* leaked security context no reparable;
* compromised registry;
* memory corruption signal;
* repeated cleanup failure;
* impossible state transition.

---

# 189. Security documentation requirements

Toda capacidad deberá documentar:

* trust assumptions;
* accepted inputs;
* denied inputs;
* authorization requirements;
* tenant behavior;
* failure mode;
* observability behavior;
* Worker safety.

---

# 190. Estructura inicial del módulo

```text
src/
└── Quantum/
    └── Controllers/
        └── Security/
            ├── Contracts/
            │   ├── ControllerSecurityManagerInterface.php
            │   ├── ControllerSecurityContextFactoryInterface.php
            │   ├── ControllerSecurityDecisionEngineInterface.php
            │   ├── ControllerSecurityPolicyInterface.php
            │   ├── ControllerSecurityPolicyRegistryInterface.php
            │   ├── ControllerSecurityMetadataValidatorInterface.php
            │   └── ControllerInvocationSecurityGuardInterface.php
            │
            ├── Engine/
            │   ├── ControllerSecurityManager.php
            │   └── ControllerSecurityDecisionEngine.php
            │
            ├── Context/
            │   ├── ControllerSecurityContext.php
            │   ├── Principal.php
            │   ├── PrincipalType.php
            │   ├── AuthenticationStrength.php
            │   ├── TenantIdentity.php
            │   ├── SecurityAttributes.php
            │   └── ImpersonationContext.php
            │
            ├── Decision/
            │   ├── SecurityDecision.php
            │   ├── SecurityDecisionEffect.php
            │   ├── SecurityEvaluationRequest.php
            │   ├── SecurityDecisionCache.php
            │   └── SecurityDecisionKey.php
            │
            ├── Policy/
            │   ├── ControllerSecurityPolicy.php
            │   ├── ControllerSecurityPolicyRegistry.php
            │   ├── PolicyEvaluationStrategy.php
            │   └── SecurityObligation.php
            │
            ├── Metadata/
            │   ├── ControllerSecurityMetadata.php
            │   ├── ControllerSecurityMetadataValidator.php
            │   ├── SecurityMetadataSchema.php
            │   ├── SecurityMetadataOrigin.php
            │   └── SecurityMetadataMerger.php
            │
            ├── Pipeline/
            │   ├── ControllerSecurityPipeline.php
            │   └── Stages/
            │
            ├── Invocation/
            │   ├── ControllerInvocationSecurityGuard.php
            │   └── ControllerExposurePolicy.php
            │
            ├── Tenant/
            │   ├── TenantSecurityResolver.php
            │   ├── TenantIsolationGuard.php
            │   └── TenantViolationPolicy.php
            │
            ├── Compilation/
            │   ├── CompiledControllerSecurityPlan.php
            │   ├── ControllerSecurityCompiler.php
            │   └── ControllerSecurityArtifactValidator.php
            │
            ├── Budget/
            │   ├── ControllerSecurityBudget.php
            │   └── SecurityBudgetGuard.php
            │
            ├── Audit/
            │   ├── ControllerSecurityAuditRecorder.php
            │   ├── ControllerSecurityAuditRecord.php
            │   └── SecurityAuditSanitizer.php
            │
            ├── Incident/
            │   ├── ControllerSecurityIncident.php
            │   ├── SecurityIncidentManager.php
            │   └── WorkerSecurityDisposition.php
            │
            ├── Events/
            ├── Metrics/
            ├── Exceptions/
            ├── Testing/
            └── Providers/
                └── ControllerSecurityServiceProvider.php
```

---

# 191. ADR-001

**La seguridad será una propiedad transversal del pipeline, no únicamente middleware.**

---

# 192. ADR-002

**VoltStack utilizará deny-by-default para decisiones de seguridad.**

---

# 193. ADR-003

**Los métodos públicos no serán automáticamente acciones invocables.**

---

# 194. ADR-004

**Los controller targets nunca podrán derivarse directamente de input del cliente.**

---

# 195. ADR-005

**Las políticas de seguridad se resolverán mediante un engine central.**

---

# 196. ADR-006

**La metadata crítica utilizará reglas de merge restrictivas.**

---

# 197. ADR-007

**La autorización previa y la autorización de recurso serán fases distintas.**

---

# 198. ADR-008

**El tenant deberá provenir de una fuente verificada.**

---

# 199. ADR-009

**El binding de modelos siempre respetará tenant y resource scopes.**

---

# 200. ADR-010

**Los artifacts compilados no contendrán decisiones finales dependientes del usuario.**

---

# 201. ADR-011

**Cada ejecución tendrá un contexto de seguridad inmutable.**

---

# 202. ADR-012

**Los Workers no compartirán contextos ni decisiones de seguridad entre requests.**

---

# 203. ADR-013

**Los artifacts deberán pertenecer al build activo de la ejecución.**

---

# 204. ADR-014

**Las fallas de infraestructura de seguridad producirán fail-closed en producción.**

---

# 205. ADR-015

**Las señales de observabilidad deberán sanitizarse antes de exportarse.**

---

# 206. ADR-016

**Las decisiones Abstain no se interpretarán como Allow.**

---

# 207. ADR-017

**Los controles de seguridad deberán ser equivalentes en modo dinámico y compilado.**

---

# 208. ADR-018

**Los custom security extensions deberán declarar capabilities.**

---

# 209. ADR-019

**Las violaciones de tenant se tratarán como incidentes de seguridad.**

---

# 210. ADR-020

**Los builds inseguros podrán revocarse aunque sean técnicamente compatibles.**

---

# 211. ADR-021

**El Worker podrá ser terminado ante una pérdida de confianza interna.**

---

# 212. ADR-022

**La compatibilidad no tendrá prioridad sobre la corrección de vulnerabilidades críticas.**

---

# 213. Implementación V1 de fundamentos

La V1 deberá incluir:

* Security Context;
* Principal;
* Tenant Identity;
* Security Decision Engine;
* Policy Registry;
* Security Metadata;
* Invocation Guard;
* secure merge rules;
* deny by default;
* execution decision cache;
* security events;
* security metrics;
* audit básico;
* Worker reset;
* compiled security plan;
* threat register;
* hardening profiles.

---

# 214. Fuera de PART 01

Las siguientes áreas se detallarán en partes posteriores:

* controller resolution security;
* method exposure;
* parameter injection;
* DTO hydration;
* model binding;
* authorization avanzada;
* tenant enforcement;
* Workers persistentes;
* artifact signing;
* build trust;
* HTTP security;
* cookies;
* headers;
* CORS;
* CSRF;
* uploads;
* streaming;
* secure observability;
* security testing;
* incident response completo.

---

# 215. Flujo conceptual completo

```text
Untrusted Request
        │
        ▼
Request Security Validation
        │
        ▼
Trusted Request Representation
        │
        ▼
Principal and Tenant Resolution
        │
        ▼
Security Context
        │
        ▼
Controller Exposure Validation
        │
        ▼
Pre-Binding Authorization
        │
        ▼
Secure Parameter Resolution
        │
        ▼
Resource Authorization
        │
        ▼
Invocation Guard
        │
        ▼
Controller Execution
        │
        ▼
Result Security
        │
        ▼
Transport Security
        │
        ▼
Audit and Cleanup
```

---

# 216. Conclusión de PART 01

La primera parte del **Controller Security Model** establece las bases que gobernarán todo el subsistema Controllers.

Los elementos centrales son:

```text
ControllerSecurityContext
ControllerSecurityDecisionEngine
ControllerSecurityPolicyRegistry
ControllerSecurityMetadata
ControllerInvocationSecurityGuard
CompiledControllerSecurityPlan
```

La seguridad se aplicará de manera transversal desde la entrada del request hasta la emisión de la respuesta y el cleanup del Worker.

La siguiente parte profundizará en la protección concreta del runtime de controladores.

---

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

# CONTROLLER_SECURITY_MODEL_PART_03.md

## Compilation & Artifact Security

**Versión:** 1.0
**Estado:** Draft arquitectónico
**Módulo:** `VoltStack\Quantum\Controllers\Security\Compilation`
**Ámbito:** Seguridad del pipeline de compilación, generación de código, artefactos, manifests, fingerprints, firmas, activación de builds, rollback, OPcache, preload, caches remotos, despliegues distribuidos y cadena de suministro
**Dependencias:**

* `CONTROLLER_SECURITY_MODEL.md — PART 01`
* `CONTROLLER_SECURITY_MODEL_PART_02.md`
* `CONTROLLER_COMPILATION_FRAMEWORK.md`

---

# 1. Introducción

Esta tercera parte define el modelo de seguridad para la compilación y distribución de artefactos del subsistema Controllers.

VoltStack podrá convertir definiciones dinámicas en estructuras optimizadas como:

* controller resolution plans;
* parameter resolution plans;
* metadata plans;
* security plans;
* interceptor pipelines;
* invocation plans;
* result transformation plans;
* route-to-controller maps;
* execution bundles;
* preload scripts.

Esta optimización introduce una nueva frontera de confianza.

```text
Source Code
    ↓
Compiler
    ↓
Generated Artifact
    ↓
Artifact Store
    ↓
Manifest
    ↓
Build Activation
    ↓
Runtime Loader
    ↓
OPcache / Preload
    ↓
Controller Execution
```

Un artefacto manipulado puede convertir una aplicación segura en una ejecución arbitraria de código.

Por ello, los artefactos compilados deberán tratarse como código ejecutable privilegiado.

---

# 2. Objetivo principal

Garantizar que todo artefacto utilizado por el runtime:

* provenga de fuentes permitidas;
* haya sido generado por compiladores confiables;
* pertenezca al build activo;
* mantenga integridad;
* corresponda a su manifest;
* sea compatible con el runtime;
* no pueda sustituirse por otro;
* no sea vulnerable a path traversal;
* no pueda degradarse silenciosamente;
* no se reutilice fuera de su contexto;
* pueda revocarse;
* se active de forma atómica.

---

# 3. Principio de confianza explícita

La existencia de un archivo en disco no implica confianza.

```text
File exists
    ≠
Artifact trusted
```

La confianza deberá derivarse de:

```text
Known build identity
+ Manifest membership
+ Schema validation
+ Fingerprint validation
+ Signature policy
+ Compatibility validation
+ Secure loading path
```

---

# 4. Artefactos como código ejecutable

Los artefactos podrán contener:

* arrays PHP;
* clases generadas;
* closures generadas;
* factories;
* lookup tables;
* serialized metadata segura;
* preload declarations;
* dispatch maps.

Aunque un artefacto contenga solo arrays, podrá modificar decisiones críticas.

Ejemplos:

* exponer un método;
* omitir autorización;
* cambiar una clase;
* alterar el orden de interceptores;
* reemplazar un parameter resolver;
* desactivar tenant scope.

---

# 5. Principios de seguridad de compilación

VoltStack seguirá:

* trusted compiler pipeline;
* deterministic builds;
* immutable artifacts;
* atomic activation;
* content integrity;
* build isolation;
* secure defaults;
* least privilege;
* no runtime mutation;
* no implicit fallback;
* revocable trust;
* reproducibility when possible;
* strict compatibility.

---

# 6. Deterministic builds

Dadas las mismas entradas confiables:

```text
Source
Configuration
Compiler version
Dependencies
Build profile
```

el compilador debería producir artefactos equivalentes.

Esto facilita:

* auditoría;
* comparación;
* reproducibilidad;
* detección de manipulación;
* despliegues consistentes.

---

# 7. Build inputs

Los inputs deberán registrarse explícitamente:

* source tree;
* configuration;
* environment-independent settings;
* package manifests;
* compiler plugins;
* route definitions;
* controller metadata;
* policy definitions;
* framework version;
* PHP version target;
* feature flags compilables.

---

# 8. Inputs no deterministas

Deberán evitarse durante compilación:

* hora actual;
* hostname;
* process ID;
* random values;
* paths absolutos no normalizados;
* orden de filesystem no estable;
* environment secrets;
* estado de red;
* respuestas externas no fijadas.

---

# 9. Secrets en compilación

Los secretos no deberán incorporarse a artefactos.

Ejemplos prohibidos:

* database passwords;
* API keys;
* signing private keys;
* session secrets;
* cloud credentials;
* encryption keys.

El artefacto podrá contener referencias lógicas, no valores secretos.

---

# 10. Trust boundaries

Los límites principales serán:

```text
Developer Source → Build System
Build System → Compiler
Compiler → Artifact Store
Artifact Store → Manifest
Manifest → Deployment
Deployment → Active Build
Active Build → Runtime Loader
Runtime Loader → OPcache
OPcache → Worker
Remote Cache → Local Artifact Store
Package Compiler → Core Compiler
```

---

# 11. Boundary: Source to Build

Amenazas:

* source tampering;
* malicious dependency;
* generated source injection;
* unreviewed package compiler;
* unsafe build hooks;
* configuration poisoning.

---

# 12. Boundary: Build to Compiler

El compiler deberá recibir un contexto congelado.

```php
final readonly class ControllerCompilationContext
{
    public function __construct(
        public string $buildId,
        public string $frameworkVersion,
        public string $phpTarget,
        public CompilationProfile $profile,
        public TrustedCompilationInputSet $inputs,
    ) {
    }
}
```

---

# 13. Boundary: Compiler to Artifact Store

Los artefactos deberán escribirse mediante una API segura, no mediante paths arbitrarios.

---

# 14. Boundary: Artifact Store to Manifest

Todo artefacto activable deberá aparecer en el manifest.

Un archivo huérfano no deberá cargarse.

---

# 15. Boundary: Deployment to Runtime

El runtime deberá recibir un build activo completo e inmutable.

No deberá observar estados parciales.

---

# 16. Boundary: Remote Cache to Local Store

Un cache remoto será no confiable hasta validar:

* build identity;
* fingerprint;
* signature;
* artifact schema;
* manifest membership.

---

# 17. Activos protegidos

* compiler definitions;
* compiler registry;
* artifact schemas;
* build manifests;
* fingerprints;
* signatures;
* build pointers;
* active build marker;
* preload scripts;
* OPcache entries;
* remote cache credentials;
* revocation registry;
* deployment history.

---

# 18. Actores

```text
Developer
Application package
Compiler plugin
Build runner
CI system
Artifact store
Deployment operator
Runtime process
Remote cache
Attacker with filesystem access
Compromised dependency
Malicious insider
```

---

# 19. Threat categories

Amenazas principales:

* compiler injection;
* generated code injection;
* artifact substitution;
* manifest tampering;
* build downgrade;
* partial activation;
* path traversal;
* symlink attacks;
* cache poisoning;
* signature bypass;
* preload poisoning;
* OPcache stale code;
* cross-build loading;
* dependency confusion;
* malicious compiler plugins.

---

# 20. Compilador confiable

Un compilador será confiable cuando:

* esté registrado;
* tenga identidad;
* declare sus inputs;
* declare artefactos generados;
* sea compatible con el schema;
* no acceda a capacidades no requeridas;
* produzca resultados validables;
* esté congelado antes del build.

---

# 21. Compiler identity

```php
final readonly class CompilerIdentity
{
    public function __construct(
        public string $name,
        public string $version,
        public string $provider,
        public string $fingerprint,
        public CompilerTrustLevel $trustLevel,
    ) {
    }
}
```

---

# 22. CompilerTrustLevel

```php
enum CompilerTrustLevel: string
{
    case Core = 'core';
    case Official = 'official';
    case Application = 'application';
    case ThirdParty = 'third_party';
    case Untrusted = 'untrusted';
}
```

---

# 23. Compiler capabilities

```php
enum CompilerCapability: string
{
    case ReadControllerMetadata = 'read_controller_metadata';
    case ReadRouteMetadata = 'read_route_metadata';
    case ReadSecurityMetadata = 'read_security_metadata';
    case GeneratePhp = 'generate_php';
    case GenerateManifestEntries = 'generate_manifest_entries';
    case RegisterPreload = 'register_preload';
    case ReadPackageGraph = 'read_package_graph';
}
```

---

# 24. Capability restrictions

Un compiler de aplicación no deberá:

* modificar artifacts de otro compiler;
* sobrescribir core manifests;
* cambiar build identity;
* firmar builds;
* activar builds;
* acceder a secrets;
* escribir fuera del artifact workspace.

---

# 25. Compiler Registry

```php
interface ControllerCompilerRegistryInterface
{
    public function register(
        ControllerCompilerInterface $compiler,
        CompilerIdentity $identity
    ): void;

    public function all(): iterable;

    public function freeze(): void;
}
```

---

# 26. Registry freeze

Después de `freeze()`:

* no podrán añadirse compilers;
* no podrán cambiarse prioridades;
* no podrán alterarse capabilities;
* no podrán reemplazarse aliases.

---

# 27. Compiler ordering

El orden deberá ser:

* explícito;
* determinista;
* validado;
* libre de ciclos.

---

# 28. Compiler dependency graph

```php
interface CompilerDependencyGraphInterface
{
    public function addDependency(
        string $compiler,
        string $dependsOn
    ): void;

    public function topologicalOrder(): array;
}
```

---

# 29. Compiler cycles

Un ciclo deberá abortar el build.

```text
SecurityCompiler
    → MetadataCompiler
    → SecurityCompiler
```

---

# 30. Trusted compilation workspace

Cada build tendrá un workspace aislado.

```text
var/voltstack/builds/.staging/{build-id}/
```

No deberá compilarse directamente dentro del build activo.

---

# 31. Workspace requirements

El workspace deberá:

* tener permisos mínimos;
* pertenecer al proceso de build;
* no ser web-accessible;
* impedir path escape;
* estar vacío al iniciar;
* limpiarse tras fallo;
* activarse solo después de validación.

---

# 32. Path canonicalization

Todo path deberá canonicalizarse antes de escribir o leer.

```php
interface SecureArtifactPathResolverInterface
{
    public function resolve(
        BuildIdentity $build,
        ArtifactIdentifier $artifact
    ): SafeArtifactPath;
}
```

---

# 33. Path traversal

Se rechazarán identificadores que contengan:

* `..`;
* null bytes;
* separators inesperados;
* absolute paths;
* stream wrappers;
* URL schemes;
* encoded traversal.

---

# 34. Stream wrapper restrictions

No deberán permitirse wrappers arbitrarios:

```text
phar://
data://
http://
ftp://
zip://
```

El artifact loader deberá trabajar únicamente con storage adapters aprobados.

---

# 35. Symlink security

Por defecto:

* no seguir symlinks;
* verificar cada segmento;
* rechazar symlinks dentro del build;
* validar el realpath final;
* comprobar que permanece en el build root.

---

# 36. Race conditions de filesystem

Cuando sea relevante se deberán utilizar:

* file descriptors seguros;
* creación exclusiva;
* atomic rename;
* directory ownership;
* post-write validation.

---

# 37. Secure code generation

El código PHP generado deberá construirse mediante un emitter estructurado.

No mediante concatenación de input no confiable.

---

# 38. Code emitter

```php
interface SecurePhpCodeEmitterInterface
{
    public function emit(
        PhpArtifactAst $ast
    ): GeneratedPhpArtifact;
}
```

---

# 39. Artifact AST

La generación basada en AST reduce riesgos de:

* quote injection;
* malformed code;
* namespace escape;
* accidental statements;
* executable user values.

---

# 40. Input escaping

Los valores de metadata deberán emitirse como literales seguros.

No deberán interpolarse directamente en código.

---

# 41. Generated identifiers

Nombres de clases, métodos y namespaces deberán derivarse de identificadores validados.

---

# 42. Arbitrary code fragments

No se aceptarán fragments PHP provenientes de:

* route metadata;
* attributes de usuario;
* configuration strings;
* package manifests;
* client input.

---

# 43. Generated class namespace

Las clases generadas deberán vivir en un namespace reservado.

```text
VoltStack\Compiled\Controllers\Build_{BuildHash}
```

---

# 44. Build-specific namespaces

Incluir build identity reduce colisiones entre builds cargados en el mismo proceso.

---

# 45. Generated class collisions

El compiler deberá detectar:

* nombres duplicados;
* case-insensitive collisions;
* namespace collisions;
* artifacts duplicados.

---

# 46. Case sensitivity

El build validator deberá considerar diferencias entre filesystems.

Una build válida en Linux no deberá depender de colisiones que fallen en otros sistemas soportados.

---

# 47. Artifact model

```php
interface CompiledControllerArtifactInterface
{
    public function id(): ArtifactIdentifier;

    public function type(): ControllerArtifactType;

    public function buildId(): string;

    public function schemaVersion(): string;

    public function fingerprint(): ArtifactFingerprint;

    public function dependencies(): array;
}
```

---

# 48. ControllerArtifactType

```php
enum ControllerArtifactType: string
{
    case ResolutionPlan = 'resolution_plan';
    case ExposurePlan = 'exposure_plan';
    case ParameterPlan = 'parameter_plan';
    case MetadataPlan = 'metadata_plan';
    case SecurityPlan = 'security_plan';
    case InterceptorPlan = 'interceptor_plan';
    case InvocationPlan = 'invocation_plan';
    case TransformationPlan = 'transformation_plan';
    case ExecutionBundle = 'execution_bundle';
    case PreloadScript = 'preload_script';
}
```

---

# 49. ArtifactIdentifier

```php
final readonly class ArtifactIdentifier
{
    public function __construct(
        public ControllerArtifactType $type,
        public string $logicalName,
        public string $version,
    ) {
    }
}
```

---

# 50. Artifact schema

Cada tipo deberá tener un schema versionado.

---

# 51. Schema validation

El validator deberá comprobar:

* campos requeridos;
* tipos;
* versiones;
* rangos;
* enums;
* referencias;
* dependencies;
* fingerprint;
* build ID.

---

# 52. Unknown fields

En artifacts críticos se recomienda:

```text
Reject unknown fields
```

Esto evita que runtimes antiguos ignoren semántica nueva de seguridad.

---

# 53. Schema compatibility

Podrán existir:

```text
Backward compatible
Forward compatible
Exact version required
Migration required
```

Los security artifacts deberían requerir compatibilidad exacta o explícitamente aprobada.

---

# 54. Artifact immutability

Después de finalizar:

* no podrá modificarse;
* deberá cerrarse el writer;
* se calculará fingerprint;
* se establecerán permisos read-only;
* se añadirá al manifest.

---

# 55. ArtifactWriter

```php
interface SecureArtifactWriterInterface
{
    public function write(
        GeneratedControllerArtifact $artifact,
        BuildWorkspace $workspace
    ): StoredArtifact;

    public function finalize(
        StoredArtifact $artifact
    ): FinalizedArtifact;
}
```

---

# 56. Atomic file creation

Se deberá escribir en archivo temporal y luego renombrar.

```text
artifact.tmp
    ↓ validate
artifact.php
```

---

# 57. File permissions

Permisos recomendados:

* build process: write durante staging;
* runtime: read-only;
* web server: sin capacidad de modificación;
* otros usuarios: sin acceso.

---

# 58. Directory permissions

El directorio activo deberá impedir:

* escritura por runtime;
* creación de archivos;
* sustitución de manifests;
* symlink injection.

---

# 59. Artifact fingerprints

El fingerprint representará el contenido y contexto.

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

# 60. Algoritmos

Se utilizarán algoritmos criptográficos modernos aprobados por la configuración del framework.

Ejemplos conceptuales:

* SHA-256;
* SHA-384;
* BLAKE2b cuando esté soportado y aprobado.

---

# 61. Fingerprint scope

Podrá incluir:

* content bytes;
* artifact type;
* schema version;
* build ID;
* dependency fingerprints;
* compiler identity.

---

# 62. Canonical serialization

Los fingerprints de estructuras deberán usar representación canónica.

```text
Stable key order
Stable encoding
Stable numeric format
Stable newline policy
No runtime-specific values
```

---

# 63. Dependency fingerprinting

Un artifact deberá invalidarse si cambia una dependencia relevante.

---

# 64. Merkle-like build graph

El build podrá representar dependencias de fingerprints.

```text
Route metadata hash
    ├── controller plan hash
    ├── security plan hash
    └── execution bundle hash
```

---

# 65. Artifact signatures

Las firmas serán opcionales por perfil, pero recomendadas para producción estricta.

```php
final readonly class ArtifactSignature
{
    public function __construct(
        public string $algorithm,
        public string $keyId,
        public string $signature,
    ) {
    }
}
```

---

# 66. Firma del manifest

La estrategia preferida será firmar el manifest completo y encadenar fingerprints de artifacts.

Esto reduce el número de firmas requeridas.

---

# 67. Firma individual

Podrá utilizarse además para:

* remote caches;
* artifacts compartidos;
* distribución parcial;
* verificación independiente.

---

# 68. Private key security

La private key:

* no deberá estar en el runtime;
* no deberá estar en artifacts;
* deberá residir en secret manager o sistema de firma;
* deberá tener acceso restringido;
* deberá poder rotarse.

---

# 69. Public key trust store

El runtime podrá contener claves públicas confiables.

```php
interface BuildSignatureTrustStoreInterface
{
    public function resolve(
        string $keyId
    ): TrustedPublicKey;
}
```

---

# 70. Key rotation

Los manifests deberán indicar `keyId`.

El runtime podrá confiar temporalmente en múltiples claves durante rotación.

---

# 71. Key revocation

Una clave comprometida deberá poder revocarse.

---

# 72. Signature timestamp

Podrá incluirse:

* build timestamp;
* signing timestamp;
* key version;
* expiration policy.

El tiempo no deberá ser la única garantía de validez.

---

# 73. Offline verification

La validación básica deberá poder realizarse sin acceso de red.

---

# 74. Manifest model

```php
final readonly class ControllerArtifactManifest
{
    public function __construct(
        public BuildIdentity $build,
        public string $schemaVersion,
        public array $artifacts,
        public array $compilers,
        public array $dependencies,
        public array $compatibility,
        public ?ArtifactSignature $signature,
        public string $manifestFingerprint,
    ) {
    }
}
```

---

# 75. Manifest contents

Deberá incluir:

* build ID;
* artifact list;
* artifact paths lógicos;
* fingerprints;
* sizes;
* types;
* schema versions;
* compiler identities;
* dependency graph;
* framework version;
* PHP target;
* required extensions;
* preload entries;
* security profile.

---

# 76. Manifest trust

Un manifest no será confiable solo por estar dentro del build.

Deberá validarse antes de usarlo.

---

# 77. Manifest validator

```php
interface ControllerArtifactManifestValidatorInterface
{
    public function validate(
        ControllerArtifactManifest $manifest,
        RuntimeCompatibilityContext $runtime
    ): ManifestValidationResult;
}
```

---

# 78. Manifest validation order

```text
Parse safely
    ↓
Schema validation
    ↓
Build identity validation
    ↓
Signature validation
    ↓
Compatibility validation
    ↓
Artifact inventory validation
    ↓
Dependency graph validation
    ↓
Revocation checks
```

---

# 79. Manifest parsing

No se utilizará deserialización insegura.

Formatos posibles:

* PHP array generado seguro;
* JSON estricto;
* binary schema específico futuro.

---

# 80. PHP manifest

Si se utiliza PHP deberá devolver exclusivamente datos.

No deberá ejecutar side effects.

---

# 81. Manifest size limits

Se deberán limitar:

* cantidad de artifacts;
* path lengths;
* dependency count;
* metadata depth;
* total bytes.

---

# 82. Duplicate manifest entries

Deberán rechazarse.

---

# 83. Dependency graph validation

Se comprobarán:

* artifacts existentes;
* ciclos;
* missing dependencies;
* incompatible types;
* cross-build references.

---

# 84. Cross-build dependency

Por defecto estará prohibida.

Un artifact del build A no deberá depender de uno del build B.

---

# 85. Build identity

```php
final readonly class BuildIdentity
{
    public function __construct(
        public string $id,
        public string $applicationId,
        public string $frameworkVersion,
        public string $sourceRevision,
        public string $profile,
    ) {
    }
}
```

---

# 86. Build ID generation

El build ID deberá ser:

* único;
* no controlado por usuario;
* path-safe;
* validable;
* preferiblemente relacionado con contenido o revisión.

---

# 87. Content-addressed builds

Podrá utilizarse un ID derivado de:

* source revision;
* manifest fingerprint;
* compilation inputs.

---

# 88. Application identity

Evita cargar artifacts de otra aplicación que comparte storage.

---

# 89. Environment identity

El environment no debería cambiar contenido funcional del artifact, pero el manifest podrá declarar restricciones de despliegue.

---

# 90. Build profile

Ejemplos:

```text
development
production
strict
high_security
```

El runtime deberá impedir cargar un build de desarrollo en producción cuando la política lo prohíba.

---

# 91. Build state machine

```text
Created
    ↓
Compiling
    ↓
Validating
    ↓
Signed
    ↓
Ready
    ↓
Active
    ↓
Retired
    ↓
Revoked
```

---

# 92. BuildState

```php
enum BuildState: string
{
    case Created = 'created';
    case Compiling = 'compiling';
    case Validating = 'validating';
    case Signed = 'signed';
    case Ready = 'ready';
    case Active = 'active';
    case Retired = 'retired';
    case Revoked = 'revoked';
    case Failed = 'failed';
}
```

---

# 93. Invalid transitions

No deberá permitirse:

```text
Compiling → Active
Failed → Active
Revoked → Active
Retired → Compiling
```

---

# 94. Build activation

La activación deberá ser atómica.

---

# 95. Active build pointer

Podrá utilizarse:

```text
var/voltstack/builds/current
```

pero el pointer deberá gestionarse de forma segura.

---

# 96. Pointer strategies

* atomic symlink swap;
* atomic file replacement;
* deployment metadata service;
* immutable release directory.

---

# 97. Symlink swap

Solo cuando:

* el sistema operativo lo soporte;
* el parent directory sea seguro;
* el runtime valide el destino;
* no siga links fuera del build root.

---

# 98. ActiveBuildDescriptor

```php
final readonly class ActiveBuildDescriptor
{
    public function __construct(
        public string $buildId,
        public string $manifestFingerprint,
        public string $activatedAt,
        public string $activationId,
    ) {
    }
}
```

---

# 99. Runtime pinning

Cada request o Worker deberá fijar un build ID.

```text
Request starts
    ↓
Read active build
    ↓
Pin build ID
    ↓
Use only that build
    ↓
Request ends
```

---

# 100. Mid-request activation

Un request en curso no deberá mezclar artifacts del build anterior y el nuevo.

---

# 101. Worker build pinning

Un Worker podrá:

* fijar build por request;
* reiniciarse al cambiar build;
* mantener múltiples builds cargados de forma controlada.

La estrategia dependerá de OPcache y preload.

---

# 102. ExecutionBundle

```php
final readonly class ControllerExecutionBundle
{
    public function __construct(
        public BuildIdentity $build,
        public ArtifactReference $resolution,
        public ArtifactReference $exposure,
        public ArtifactReference $parameters,
        public ArtifactReference $security,
        public ArtifactReference $interceptors,
        public ArtifactReference $invocation,
        public ArtifactFingerprint $fingerprint,
    ) {
    }
}
```

---

# 103. Bundle consistency

Todos sus artifacts deberán:

* pertenecer al mismo build;
* ser compatibles;
* tener dependencies satisfechas;
* corresponder al target correcto.

---

# 104. Bundle loader

```php
interface SecureExecutionBundleLoaderInterface
{
    public function load(
        ControllerBundleIdentifier $identifier,
        PinnedBuildContext $build
    ): ControllerExecutionBundle;
}
```

---

# 105. Artifact loader security

El loader deberá:

* resolver paths seguros;
* validar build;
* validar manifest membership;
* comprobar fingerprint;
* comprobar size;
* cargar una sola vez;
* rechazar artifacts revocados.

---

# 106. ArtifactReference

```php
final readonly class ArtifactReference
{
    public function __construct(
        public ArtifactIdentifier $id,
        public ArtifactFingerprint $fingerprint,
        public int $size,
        public string $logicalPath,
    ) {
    }
}
```

---

# 107. Include security

Antes de `require` o `include`:

* path canonicalizado;
* artifact validado;
* build fijado;
* manifest validado;
* fingerprint confirmado;
* extension permitida.

---

# 108. `require_once`

No resuelve por sí mismo la seguridad ni las colisiones entre builds.

---

# 109. Loader cache

Podrá cachear objetos ya validados, pero la key deberá incluir:

* application ID;
* build ID;
* artifact ID;
* fingerprint.

---

# 110. Negative cache

Los fallos podrán cachearse brevemente para evitar ataques repetitivos, sin impedir recuperación tras activación de un build válido.

---

# 111. Fail closed

Si un artifact falta o no valida:

* no ejecutar fallback dinámico en producción estricta;
* no buscar archivos similares;
* no usar artifact anterior;
* no continuar parcialmente.

---

# 112. Dynamic fallback

Solo podrá habilitarse en desarrollo o perfil explícito.

Deberá generar una señal visible.

---

# 113. Artifact store

```php
interface ControllerArtifactStoreInterface
{
    public function put(
        BuildIdentity $build,
        FinalizedArtifact $artifact
    ): StoredArtifactReference;

    public function get(
        BuildIdentity $build,
        ArtifactIdentifier $artifact
    ): ArtifactStream;

    public function exists(
        BuildIdentity $build,
        ArtifactIdentifier $artifact
    ): bool;
}
```

---

# 114. Store implementations

* local filesystem;
* read-only package;
* object storage;
* remote cache;
* distributed artifact repository.

---

# 115. Local store security

Deberá verificar:

* ownership;
* permissions;
* directory root;
* symlinks;
* mount options;
* free space;
* atomic writes.

---

# 116. Object storage security

Deberá usar:

* authenticated transport;
* bucket isolation;
* server-side access control;
* versioning;
* immutable objects cuando sea posible;
* checksum validation.

---

# 117. Artifact confidentiality

Los artifacts generalmente no deberán contener secretos, pero podrán revelar:

* namespaces;
* routes;
* policy names;
* internal architecture.

Por ello el store no deberá ser público.

---

# 118. Encryption at rest

Podrá utilizarse según entorno, pero no sustituye firmas ni fingerprints.

---

# 119. Remote cache security

Un cache remoto deberá considerarse un acelerador, no una autoridad absoluta.

---

# 120. Remote cache lookup

```text
Request artifact
    ↓
Fetch candidate
    ↓
Validate signature
    ↓
Validate fingerprint
    ↓
Validate manifest
    ↓
Store locally
```

---

# 121. Cache poisoning

Mitigaciones:

* content-addressed keys;
* signature verification;
* TLS;
* authentication;
* immutable objects;
* local revalidation;
* namespace isolation.

---

# 122. Cache keys

No deberán contener paths proporcionados por usuario.

---

# 123. Shared cache isolation

La key deberá incluir:

* application ID;
* compiler version;
* framework version;
* source fingerprint;
* profile;
* artifact schema.

---

# 124. Cache eviction

La eliminación del cache no deberá comprometer corrección, solo rendimiento.

---

# 125. Stale cache

Un artifact antiguo no deberá aceptarse si no coincide con el manifest solicitado.

---

# 126. Distributed compilation

Cuando múltiples nodos compilen deberán evitar:

* build ID collisions;
* partial manifests;
* competing activation;
* inconsistent signatures;
* different compiler sets.

---

# 127. Build coordinator

```php
interface DistributedBuildCoordinatorInterface
{
    public function acquireBuildLease(
        BuildIdentity $build
    ): BuildLease;

    public function publishReadyBuild(
        BuildIdentity $build
    ): void;
}
```

---

# 128. Build lease

Deberá incluir:

* owner;
* expiration;
* fencing token;
* build ID.

---

# 129. Fencing tokens

Evitan que un compilador antiguo publique después de perder el lease.

---

# 130. Multi-node activation

La activación podrá ser:

* simultánea;
* rolling;
* blue-green;
* canary.

Cada nodo deberá validar el build localmente.

---

# 131. Activation acknowledgement

Podrá registrarse:

```text
Node
Build ID
Manifest fingerprint
Validation result
Activation time
```

---

# 132. Partial fleet activation

La arquitectura deberá soportar temporalmente dos builds activos en distintos nodos sin mezclar artifacts dentro de una ejecución.

---

# 133. Session compatibility

Si un cambio de build altera protocolos o sesión, deberá gestionarse mediante versionado externo al artifact loader.

---

# 134. Rollback security

Rollback no deberá equivaler a cargar cualquier build antiguo.

---

# 135. Rollback eligibility

Un build deberá:

* estar validado;
* no estar revocado;
* ser compatible;
* cumplir minimum security version;
* estar autorizado para rollback.

---

# 136. Build history

```php
interface BuildHistoryRepositoryInterface
{
    public function recordActivation(
        BuildActivationRecord $record
    ): void;

    public function previousEligibleBuilds(): iterable;
}
```

---

# 137. BuildActivationRecord

```php
final readonly class BuildActivationRecord
{
    public function __construct(
        public string $activationId,
        public string $buildId,
        public string $manifestFingerprint,
        public string $actor,
        public string $reason,
        public string $timestamp,
    ) {
    }
}
```

---

# 138. Downgrade protection

El sistema podrá definir:

```text
minimum_allowed_build
minimum_framework_version
minimum_security_schema
minimum_compiler_version
```

---

# 139. Security epoch

```php
final readonly class SecurityEpoch
{
    public function __construct(
        public int $value
    ) {
    }
}
```

Un build con epoch inferior al mínimo no podrá activarse.

---

# 140. Revoked builds

Un build revocado no podrá:

* activarse;
* cargarse;
* usarse como rollback;
* recuperarse desde cache remoto.

---

# 141. Artifact revocation

También podrán revocarse artifacts específicos cuando el sistema lo soporte.

---

# 142. Revocation registry

```php
interface ArtifactRevocationRegistryInterface
{
    public function isBuildRevoked(string $buildId): bool;

    public function isArtifactRevoked(
        string $buildId,
        ArtifactIdentifier $artifact
    ): bool;
}
```

---

# 143. Revocation sources

* local configuration;
* signed revocation list;
* deployment control plane;
* emergency security update.

---

# 144. Revocation freshness

En sistemas desconectados deberá existir una política clara sobre cuánto tiempo puede utilizarse una lista antigua.

---

# 145. Emergency revocation

Podrá provocar:

* detener nuevas requests;
* retirar Workers;
* activar build seguro;
* deshabilitar controlador afectado;
* invalidar OPcache.

---

# 146. OPcache security

OPcache puede conservar código incluso después de cambios de filesystem.

---

# 147. OPcache assumptions

El sistema deberá conocer:

* `validate_timestamps`;
* deployment strategy;
* Worker lifecycle;
* preload;
* immutable paths;
* cache reset capabilities.

---

# 148. Immutable deployment

En producción se recomienda:

```text
New build → new path
```

en lugar de sobrescribir archivos existentes.

---

# 149. Path versioning

Cada build deberá tener paths únicos.

Esto reduce riesgo de bytecode obsoleto.

---

# 150. OPcache invalidation

Cuando se reemplace un archivo en el mismo path, deberá invalidarse explícitamente.

Sin embargo, la estrategia preferida será no reutilizar paths.

---

# 151. OPcache poisoning

Amenazas:

* escribir código malicioso antes de cachearlo;
* sustituir archivo después de validación;
* reutilizar path entre builds;
* cargar artifact fuera del manifest.

---

# 152. TOCTOU en carga

Mitigaciones:

* build read-only;
* validación previa;
* file ownership;
* immutable path;
* opcionalmente verificar tras apertura;
* no permitir escritura del runtime.

---

# 153. Preload security

Los scripts de preload se ejecutan en un contexto privilegiado al iniciar el servidor.

---

# 154. Preload artifact

Deberá:

* pertenecer al build;
* estar en manifest;
* estar firmado según perfil;
* contener solo paths validados;
* no ejecutar lógica de aplicación;
* no leer secrets;
* no iniciar conexiones.

---

# 155. Preload generator

```php
interface SecureControllerPreloadGeneratorInterface
{
    public function generate(
        ValidatedBuild $build
    ): PreloadArtifact;
}
```

---

# 156. Preload allowlist

Solo artifacts seguros para preload deberán incluirse.

---

# 157. No request-scoped preload

No deberán preloaded:

* request contexts;
* principals;
* tenants;
* mutable controllers;
* runtime decision caches.

---

# 158. Preload build switching

Cambiar el build preloaded normalmente requerirá reiniciar Workers o servidor.

---

# 159. Preload mismatch

El runtime deberá detectar si:

```text
preloaded build != active build
```

y seguir una política definida.

---

# 160. Preload policies

Opciones:

* refuse startup;
* continue with pinned preloaded build;
* require restart;
* disable compiled runtime.

En producción estricta deberá evitarse mezclar.

---

# 161. Worker restart coordination

La activación de un build con cambios preloaded deberá coordinar reinicio.

---

# 162. Supply-chain security

La compilación depende de:

* Composer packages;
* framework packages;
* compiler plugins;
* CI images;
* PHP extensions;
* build scripts.

---

# 163. Dependency pinning

Se deberán usar versiones bloqueadas mediante lockfiles.

---

# 164. Package integrity

Composer deberá validar integridad según sus mecanismos disponibles.

---

# 165. Trusted package sources

Se deberán restringir repositories no confiables.

---

# 166. Dependency confusion

Mitigaciones:

* nombres reservados;
* private repository priorities;
* exact package ownership;
* lockfiles;
* source validation.

---

# 167. Compiler plugin review

Un plugin compiler deberá considerarse código privilegiado.

---

# 168. Build hooks

Los hooks como post-install o custom scripts deberán revisarse y limitarse en CI.

---

# 169. CI hardening

El build runner deberá:

* usar credenciales mínimas;
* aislar jobs;
* evitar secretos innecesarios;
* limpiar workspace;
* producir logs sanitizados;
* no permitir forks no confiables con secretos.

---

# 170. Reproducible environment

Se recomienda fijar:

* PHP version;
* extensions;
* Composer version;
* operating system image;
* compiler versions;
* locale;
* timezone cuando influya.

---

# 171. Build provenance

```php
final readonly class BuildProvenance
{
    public function __construct(
        public string $sourceRevision,
        public string $ciRunId,
        public string $builderIdentity,
        public array $compilerIdentities,
        public array $dependencyFingerprints,
    ) {
    }
}
```

---

# 172. Provenance attestation

Podrá firmarse junto al manifest.

---

# 173. Artifact SBOM

VoltStack podrá generar un inventario de:

* compilers;
* packages;
* artifacts;
* schemas;
* source revisions.

---

# 174. Package compiler isolation

Un package compiler no deberá poder registrar artifacts bajo la identidad de otro package.

---

# 175. Compiler namespace ownership

Cada compiler tendrá un namespace lógico de artifacts.

---

# 176. Artifact collision policy

Una colisión deberá abortar el build, no usar “last writer wins”.

---

# 177. Build validation pipeline

```text
Compile
    ↓
Schema validation
    ↓
Static security validation
    ↓
Dependency validation
    ↓
Fingerprint generation
    ↓
Manifest generation
    ↓
Signature
    ↓
Load test
    ↓
Activation eligibility
```

---

# 178. Static security validation

Deberá verificar:

* no métodos no expuestos;
* no policies omitidas;
* no tenant scope perdido;
* no resolver no confiable;
* no interceptor order inválido;
* no arbitrary class strings;
* no dynamic code fragments.

---

# 179. Generated PHP lint

Todo PHP generado deberá pasar parse validation antes de incluirse.

---

# 180. Artifact smoke load

Podrá cargarse en un proceso aislado para comprobar:

* parse;
* class declarations;
* schema;
* dependencies;
* no side effects inesperados.

---

# 181. Isolated build validation

Se recomienda validar artifacts ejecutables en un proceso separado del runtime principal.

---

# 182. Load-time side effects

Un artifact deberá evitar:

* network access;
* database queries;
* file writes;
* event dispatch;
* service resolution global.

---

# 183. Artifact purity

Preferiblemente deberá retornar estructuras inmutables o definir clases sin ejecutar lógica.

---

# 184. Safe artifact formats

Preferencia:

1. PHP arrays generados;
2. clases readonly generadas;
3. formatos estructurados estrictos;
4. formatos serializados seguros definidos por schema.

---

# 185. PHP serialization

No deberá usarse `unserialize()` sobre artifacts no autenticados.

Incluso autenticados, se recomienda evitar object serialization.

---

# 186. PHAR security

No se deberá cargar PHAR como formato de artifact salvo diseño específico y controles adicionales.

---

# 187. Compression

Si artifacts se distribuyen comprimidos deberá validarse:

* tamaño comprimido;
* tamaño expandido;
* paths internos;
* cantidad de entries;
* fingerprints después de extraer.

---

# 188. Archive extraction

Nunca deberá permitir Zip Slip.

---

# 189. Artifact size limits

Por tipo deberán existir límites razonables.

---

# 190. Artifact count limits

Evitan manifest bombs y agotamiento de filesystem.

---

# 191. Build quotas

```php
final readonly class BuildSecurityQuota
{
    public function __construct(
        public int $maxArtifacts,
        public int $maxTotalBytes,
        public int $maxDependencyEdges,
        public int $maxPreloadEntries,
    ) {
    }
}
```

---

# 192. Disk exhaustion

El builder deberá verificar espacio y limpiar staging fallido.

---

# 193. Concurrent builds

Cada build deberá usar workspace separado.

---

# 194. Concurrent activation

Solo un actor podrá modificar el active pointer mediante lock o CAS.

---

# 195. Activation compare-and-swap

La activación podrá exigir que el build activo esperado no haya cambiado.

---

# 196. Deployment permissions

El runtime no deberá tener permisos para:

* compilar;
* firmar;
* activar builds;
* eliminar history;
* modificar revocations.

---

# 197. Separation of duties

Idealmente:

```text
Builder → generate
Signer → attest
Deployer → activate
Runtime → read
```

---

# 198. Local development exception

En desarrollo estas funciones podrán residir en un proceso, pero las APIs deberán conservar separación conceptual.

---

# 199. Build activation authorization

La activación deberá requerir una capability administrativa.

---

# 200. Deployment audit

Se registrará:

* actor;
* build;
* source revision;
* manifest fingerprint;
* previous build;
* reason;
* result.

---

# 201. Rollback audit

Deberá registrar además la causa y riesgo aceptado.

---

# 202. Manifest transparency

Opcionalmente se podrá mantener un log append-only de manifests activados.

---

# 203. Artifact retention

Las políticas deberán definir:

* builds activos;
* rollback candidates;
* revoked builds;
* staging;
* failed builds;
* audit retention.

---

# 204. Secure deletion

No siempre es necesaria para artifacts sin secretos, pero los staging temporales deberán limpiarse.

---

# 205. Build garbage collection

Nunca deberá eliminar:

* build activo;
* build pinneado por Worker;
* build usado por request;
* rollback protegido;
* build bajo auditoría.

---

# 206. Build lease for runtime

Podrá existir una referencia activa mientras un Worker utiliza el build.

---

# 207. Reference counting

En sistemas avanzados se podrá llevar cuenta para eliminación segura.

---

# 208. Cross-platform consistency

La validación deberá considerar:

* separators;
* case sensitivity;
* path length;
* reserved names;
* newline differences;
* permission semantics.

---

# 209. Runtime compatibility context

```php
final readonly class RuntimeCompatibilityContext
{
    public function __construct(
        public string $frameworkVersion,
        public string $phpVersion,
        public array $extensions,
        public string $osFamily,
        public string $architecture,
        public string $securityProfile,
    ) {
    }
}
```

---

# 210. Compatibility validation

El runtime deberá rechazar builds incompatibles.

---

# 211. PHP version mismatch

Un artifact generado para una versión superior no deberá cargarse.

---

# 212. Extension requirements

El manifest deberá declarar extensiones necesarias cuando influyan.

---

# 213. Architecture-specific artifacts

Deberán marcarse cuando no sean portables.

---

# 214. Framework compatibility

El artifact schema deberá vincularse a una versión o rango seguro del framework.

---

# 215. Security profile compatibility

Un runtime `high_security` no deberá aceptar un build `development`.

---

# 216. Feature compatibility

Las feature flags compiladas deberán compararse con el runtime cuando afecten semántica.

---

# 217. Artifact migration

La migración automática deberá evitarse para artifacts de seguridad durante load.

Es preferible recompilar.

---

# 218. Runtime generation

En producción estricta estará deshabilitada.

---

# 219. Runtime generation risks

* proceso con permisos de escritura;
* race conditions;
* incomplete builds;
* code injection;
* latency;
* inconsistent Workers.

---

# 220. Development hot reload

Podrá permitirse mediante:

* build efímero;
* workspace separado;
* no firma;
* validación completa;
* Worker restart.

---

# 221. Development warnings

El runtime deberá indicar claramente cuando utiliza artifacts no firmados o fallback dinámico.

---

# 222. Security failure classes

```text
CompilerTrustViolation
ArtifactGenerationViolation
ArtifactSchemaViolation
ArtifactFingerprintMismatch
ArtifactSignatureMismatch
ManifestValidationFailure
BuildIdentityMismatch
BuildCompatibilityFailure
ArtifactPathViolation
ArtifactRevokedException
BuildRevokedException
UnsafeRollbackException
PreloadBuildMismatch
OPcacheTrustViolation
RemoteCacheTrustViolation
```

---

# 223. Failure handling

En producción:

* rechazar build;
* no ejecutar artifact;
* registrar incidente;
* mantener build activo anterior si sigue siendo seguro;
* terminar Worker si ya cargó código no confiable.

---

# 224. Artifact validation incident

Una discrepancia de fingerprint deberá tratarse como incidente de integridad, no como cache miss normal.

---

# 225. Worker disposition

Si un Worker incluyó un artifact luego declarado inválido:

```text
Terminate
```

El código ya pudo haberse cargado en memoria.

---

# 226. Emergency safe mode

VoltStack podrá iniciar con capacidades limitadas:

* health endpoint;
* maintenance response;
* no controllers de aplicación;
* audit;
* deployment recovery.

---

# 227. Safe mode artifacts

Deberán formar parte del framework core y tener una cadena de confianza independiente.

---

# 228. Security events

```text
controllers.compilation.started
controllers.compilation.compiler.rejected
controllers.compilation.artifact.generated
controllers.compilation.artifact.rejected
controllers.compilation.manifest.generated
controllers.compilation.manifest.signed
controllers.compilation.build.validated
controllers.compilation.build.activated
controllers.compilation.build.revoked
controllers.compilation.rollback.rejected
controllers.compilation.cache.artifact.rejected
controllers.compilation.preload.mismatch
controllers.compilation.opcache.violation
```

---

# 229. Metrics

```text
voltstack.controllers.compilation.builds
voltstack.controllers.compilation.failures
voltstack.controllers.compilation.artifact_validation_failures
voltstack.controllers.compilation.signature_failures
voltstack.controllers.compilation.manifest_failures
voltstack.controllers.compilation.cache_rejections
voltstack.controllers.compilation.activation_failures
voltstack.controllers.compilation.revocations
voltstack.controllers.compilation.rollback_rejections
```

---

# 230. Metric labels

Labels seguros:

* compiler ID controlado;
* artifact type;
* failure category;
* build profile;
* activation strategy.

No incluir:

* paths completos;
* source code;
* signature bytes;
* secret key IDs sensibles;
* arbitrary artifact names.

---

# 231. Security audit records

Se deberán auditar:

* compiler registration;
* build signing;
* activation;
* rollback;
* revocation;
* key rotation;
* remote cache trust changes;
* preload policy changes.

---

# 232. BuildSecurityAuditRecord

```php
final readonly class BuildSecurityAuditRecord
{
    public function __construct(
        public string $event,
        public string $buildId,
        public string $manifestFingerprint,
        public string $actor,
        public string $result,
        public string $reason,
        public string $timestamp,
    ) {
    }
}
```

---

# 233. Configuration

```php
// config/controller_compilation_security.php

return [
    'enabled' => true,

    'compiler' => [
        'freeze_registry' => true,
        'allow_third_party' => true,
        'require_capabilities' => true,
        'reject_dependency_cycles' => true,
        'deterministic_builds' => true,
    ],

    'workspace' => [
        'root' => storage_path('voltstack/builds/.staging'),
        'isolated' => true,
        'follow_symlinks' => false,
        'clean_on_failure' => true,
    ],

    'artifacts' => [
        'immutable' => true,
        'manifest_membership_required' => true,
        'validate_fingerprint_on_load' => true,
        'reject_unknown_fields' => true,
        'allow_runtime_generation' => false,
        'max_artifacts' => 10000,
        'max_total_bytes' => 512 * 1024 * 1024,
    ],

    'signatures' => [
        'required' => env('VOLTSTACK_REQUIRE_SIGNED_BUILDS', true),
        'sign_manifest' => true,
        'sign_individual_artifacts' => false,
        'trusted_keys' => [],
        'reject_revoked_keys' => true,
    ],

    'manifest' => [
        'strict_schema' => true,
        'reject_cross_build_dependencies' => true,
        'validate_compiler_identities' => true,
        'validate_provenance' => true,
    ],

    'activation' => [
        'atomic' => true,
        'pin_per_request' => true,
        'allow_partial_builds' => false,
        'require_audit' => true,
    ],

    'rollback' => [
        'enabled' => true,
        'reject_revoked_builds' => true,
        'enforce_security_epoch' => true,
        'require_authorization' => true,
    ],

    'opcache' => [
        'immutable_paths' => true,
        'reuse_paths_between_builds' => false,
        'validate_preloaded_build' => true,
        'restart_workers_on_preload_change' => true,
    ],

    'remote_cache' => [
        'enabled' => false,
        'trust_as_authority' => false,
        'verify_signatures' => true,
        'verify_fingerprints' => true,
        'require_tls' => true,
    ],

    'runtime' => [
        'fail_closed' => true,
        'dynamic_fallback' => false,
        'terminate_worker_on_loaded_trust_failure' => true,
    ],
];
```

---

# 234. Hardening profiles

## Development

* signatures opcionales;
* dynamic fallback permitido;
* artifacts regenerables;
* warnings visibles;
* no reducción de path validation.

## Standard

* immutable builds;
* manifest validation;
* fingerprint validation;
* atomic activation;
* read-only runtime.

## Strict

* signed manifests;
* no dynamic fallback;
* no runtime writes;
* build revocation;
* downgrade protection;
* trusted compiler allowlist.

## High Security

* external signing;
* separation of duties;
* immutable artifact repository;
* provenance attestation;
* restricted supply chain;
* mandatory Worker restart;
* append-only deployment audit.

---

# 235. Testing architecture

La seguridad de compilación deberá probarse en varios niveles.

---

# 236. Unit tests

* path resolver;
* fingerprints;
* canonical serialization;
* manifest parser;
* signature verifier;
* compatibility validator;
* revocation registry;
* state transitions.

---

# 237. Compiler contract tests

Todo compiler deberá superar:

* deterministic output;
* declared capabilities;
* no path escape;
* valid artifact schema;
* dependency declaration;
* collision detection.

---

# 238. Malicious compiler tests

Se deberá probar un compiler que intente:

* escribir fuera del workspace;
* modificar artifacts ajenos;
* inyectar PHP;
* registrar paths absolutos;
* omitir security metadata;
* sobrescribir manifest.

---

# 239. Artifact tampering tests

Modificar:

* un byte;
* build ID;
* schema;
* dependency;
* path;
* size;
* signature.

Todos deberán producir rechazo.

---

# 240. Manifest tampering tests

* agregar artifact;
* eliminar artifact;
* cambiar fingerprint;
* cambiar compiler identity;
* alterar preload;
* alterar security profile.

---

# 241. Path attack tests

* `../`;
* encoded traversal;
* null bytes;
* symlinks;
* absolute paths;
* Windows drive paths;
* UNC paths;
* stream wrappers.

---

# 242. Cache poisoning tests

* artifact de otra app;
* artifact de otro build;
* artifact con key correcta y contenido incorrecto;
* artifact firmado con clave revocada;
* stale manifest.

---

# 243. Activation tests

* crash antes del swap;
* crash después del swap;
* concurrent activations;
* invalid active pointer;
* partial upload;
* unavailable manifest.

---

# 244. Rollback tests

* revoked build;
* lower security epoch;
* incompatible framework;
* unsigned old build;
* missing artifact;
* unauthorized actor.

---

# 245. OPcache tests

* mismo path con contenido nuevo;
* unique paths;
* stale Workers;
* preload mismatch;
* Worker restart behavior.

---

# 246. Distributed tests

* lost lease;
* fencing token stale;
* split activation;
* delayed node;
* remote cache inconsistency;
* manifest propagation delay.

---

# 247. Reproducibility tests

Compilar dos veces y comparar:

* artifact inventory;
* fingerprints;
* manifests;
* generated PHP;
* dependency graph.

---

# 248. Security property tests

Propiedades:

```text
No artifact outside manifest loads
No revoked build activates
No build mixes artifacts
No path escapes build root
No signature failure degrades to allow
No runtime writes active build
```

---

# 249. Fuzzing

Aplicable a:

* manifest parser;
* artifact metadata;
* path resolver;
* schema validator;
* dependency graph;
* generated identifiers.

---

# 250. Static analysis

Deberá buscar:

* dynamic include paths;
* `eval`;
* unsafe `unserialize`;
* arbitrary filesystem writes;
* global mutable compiler state;
* environment secret capture;
* unrestricted shell execution.

---

# 251. Build security checklist

Antes de marcar un build como `Ready`:

```text
[ ] Compiler registry frozen
[ ] Compiler graph valid
[ ] Workspace isolated
[ ] Artifacts schema-valid
[ ] Generated PHP parses
[ ] No collisions
[ ] Fingerprints valid
[ ] Manifest complete
[ ] Signature valid
[ ] Compatibility valid
[ ] Provenance recorded
[ ] Load smoke test passed
[ ] Build not revoked
```

---

# 252. Runtime loading checklist

```text
[ ] Active build resolved
[ ] Build pinned
[ ] Manifest validated
[ ] Signature trusted
[ ] Build compatible
[ ] Artifact belongs to manifest
[ ] Fingerprint matches
[ ] Path remains inside build root
[ ] Artifact not revoked
[ ] Dependencies belong to same build
```

---

# 253. Module structure

```text
src/
└── Quantum/
    └── Controllers/
        └── Security/
            └── Compilation/
                ├── Contracts/
                │   ├── ControllerCompilerRegistryInterface.php
                │   ├── SecureArtifactWriterInterface.php
                │   ├── ControllerArtifactStoreInterface.php
                │   ├── ControllerArtifactManifestValidatorInterface.php
                │   ├── SecureExecutionBundleLoaderInterface.php
                │   ├── BuildSignatureTrustStoreInterface.php
                │   ├── ArtifactRevocationRegistryInterface.php
                │   └── DistributedBuildCoordinatorInterface.php
                │
                ├── Compiler/
                │   ├── CompilerIdentity.php
                │   ├── CompilerTrustLevel.php
                │   ├── CompilerCapability.php
                │   ├── ControllerCompilerRegistry.php
                │   ├── CompilerDependencyGraph.php
                │   ├── CompilerCapabilityGuard.php
                │   └── CompilerTrustValidator.php
                │
                ├── Context/
                │   ├── ControllerCompilationContext.php
                │   ├── TrustedCompilationInputSet.php
                │   ├── CompilationProfile.php
                │   └── RuntimeCompatibilityContext.php
                │
                ├── Workspace/
                │   ├── BuildWorkspace.php
                │   ├── BuildWorkspaceFactory.php
                │   ├── SecureArtifactPathResolver.php
                │   ├── WorkspaceIsolationGuard.php
                │   └── WorkspaceCleaner.php
                │
                ├── Generation/
                │   ├── SecurePhpCodeEmitter.php
                │   ├── PhpArtifactAst.php
                │   ├── GeneratedPhpArtifact.php
                │   ├── GeneratedIdentifierValidator.php
                │   └── ArtifactCollisionDetector.php
                │
                ├── Artifact/
                │   ├── CompiledControllerArtifactInterface.php
                │   ├── ControllerArtifactType.php
                │   ├── ArtifactIdentifier.php
                │   ├── ArtifactReference.php
                │   ├── ArtifactFingerprint.php
                │   ├── ArtifactSignature.php
                │   ├── ArtifactSchemaRegistry.php
                │   ├── ArtifactSchemaValidator.php
                │   └── ArtifactDependencyGraph.php
                │
                ├── Manifest/
                │   ├── ControllerArtifactManifest.php
                │   ├── ControllerArtifactManifestBuilder.php
                │   ├── ControllerArtifactManifestValidator.php
                │   ├── ManifestSignatureVerifier.php
                │   ├── ManifestCanonicalizer.php
                │   └── ManifestInventoryValidator.php
                │
                ├── Build/
                │   ├── BuildIdentity.php
                │   ├── BuildState.php
                │   ├── BuildStateMachine.php
                │   ├── ValidatedBuild.php
                │   ├── BuildSecurityQuota.php
                │   ├── BuildProvenance.php
                │   └── SecurityEpoch.php
                │
                ├── Activation/
                │   ├── ActiveBuildDescriptor.php
                │   ├── AtomicBuildActivator.php
                │   ├── PinnedBuildContext.php
                │   ├── BuildActivationRecord.php
                │   ├── BuildHistoryRepository.php
                │   └── BuildActivationAuthorizationGuard.php
                │
                ├── Loading/
                │   ├── SecureArtifactLoader.php
                │   ├── SecureExecutionBundleLoader.php
                │   ├── ControllerExecutionBundle.php
                │   ├── ArtifactLoadCache.php
                │   └── ArtifactLoadSecurityGuard.php
                │
                ├── Store/
                │   ├── LocalControllerArtifactStore.php
                │   ├── RemoteControllerArtifactStore.php
                │   ├── ReadOnlyArtifactStore.php
                │   └── ArtifactStoreSecurityGuard.php
                │
                ├── Signing/
                │   ├── BuildSignerInterface.php
                │   ├── ManifestSigner.php
                │   ├── BuildSignatureTrustStore.php
                │   ├── SigningKeyReference.php
                │   └── SigningKeyRevocationRegistry.php
                │
                ├── Revocation/
                │   ├── ArtifactRevocationRegistry.php
                │   ├── BuildRevocationRecord.php
                │   ├── SignedRevocationList.php
                │   └── EmergencyBuildRevoker.php
                │
                ├── Rollback/
                │   ├── SecureBuildRollbackManager.php
                │   ├── RollbackEligibilityPolicy.php
                │   ├── DowngradeProtectionGuard.php
                │   └── MinimumSecurityEpochPolicy.php
                │
                ├── Opcache/
                │   ├── OpcacheBuildValidator.php
                │   ├── OpcacheInvalidationCoordinator.php
                │   ├── PreloadArtifact.php
                │   ├── SecureControllerPreloadGenerator.php
                │   └── PreloadBuildMismatchPolicy.php
                │
                ├── Cache/
                │   ├── RemoteArtifactCache.php
                │   ├── RemoteCacheTrustValidator.php
                │   ├── ContentAddressedArtifactKey.php
                │   └── CachePoisoningGuard.php
                │
                ├── Distributed/
                │   ├── DistributedBuildCoordinator.php
                │   ├── BuildLease.php
                │   ├── BuildFencingToken.php
                │   ├── FleetActivationCoordinator.php
                │   └── NodeActivationAcknowledgement.php
                │
                ├── SupplyChain/
                │   ├── BuildProvenanceAttestor.php
                │   ├── ArtifactSbomGenerator.php
                │   ├── PackageCompilerTrustPolicy.php
                │   └── BuildEnvironmentFingerprint.php
                │
                ├── Audit/
                │   ├── BuildSecurityAuditRecord.php
                │   ├── BuildSecurityAuditRecorder.php
                │   └── DeploymentTransparencyLog.php
                │
                ├── Events/
                ├── Metrics/
                ├── Exceptions/
                └── Testing/
```

---

# 254. ADR-041

**Los artefactos compilados se tratarán como código ejecutable privilegiado.**

---

# 255. ADR-042

**La presencia de un archivo no será evidencia suficiente de confianza.**

---

# 256. ADR-043

**Todo artifact cargable deberá pertenecer al manifest del build fijado.**

---

# 257. ADR-044

**Los builds se generarán en workspaces de staging aislados.**

---

# 258. ADR-045

**Los artifacts activos serán inmutables y read-only para el runtime.**

---

# 259. ADR-046

**La generación de PHP utilizará un emitter estructurado y no fragmentos arbitrarios.**

---

# 260. ADR-047

**Los artifacts incluirán fingerprints criptográficos.**

---

# 261. ADR-048

**Los perfiles Strict y High Security requerirán manifests firmados.**

---

# 262. ADR-049

**Las private keys de firma no estarán disponibles para el runtime.**

---

# 263. ADR-050

**Los manifests utilizarán serialización canónica para fingerprint y firma.**

---

# 264. ADR-051

**Los artifacts de un execution bundle deberán pertenecer al mismo build.**

---

# 265. ADR-052

**El runtime fijará un build por request o ejecución.**

---

# 266. ADR-053

**La activación de builds será atómica.**

---

# 267. ADR-054

**El runtime no observará builds parcialmente generados.**

---

# 268. ADR-055

**Los paths de artifacts se resolverán desde identificadores lógicos validados.**

---

# 269. ADR-056

**El loader rechazará symlinks y paths fuera del build root por defecto.**

---

# 270. ADR-057

**Los artifacts no incluidos en el manifest nunca se cargarán.**

---

# 271. ADR-058

**La falla de un artifact no activará fallback dinámico en producción estricta.**

---

# 272. ADR-059

**Los caches remotos no serán una autoridad de confianza.**

---

# 273. ADR-060

**Todo artifact remoto será revalidado localmente.**

---

# 274. ADR-061

**Las colisiones de artifact abortarán el build.**

---

# 275. ADR-062

**Los compiler registries se congelarán antes de compilar.**

---

# 276. ADR-063

**Los compilers declararán identidad, trust level y capabilities.**

---

# 277. ADR-064

**Un compiler no podrá modificar artifacts fuera de su namespace lógico.**

---

# 278. ADR-065

**Los ciclos en el compiler graph abortarán la compilación.**

---

# 279. ADR-066

**Los builds revocados no serán elegibles para activación ni rollback.**

---

# 280. ADR-067

**El rollback respetará una security epoch mínima.**

---

# 281. ADR-068

**Los paths de artifacts serán únicos por build para reducir riesgos de OPcache obsoleto.**

---

# 282. ADR-069

**Los cambios de preload requerirán coordinación de reinicio de Workers.**

---

# 283. ADR-070

**El preload solo incluirá artifacts explícitamente aprobados.**

---

# 284. ADR-071

**El build manifest declarará compatibilidad con framework, PHP, extensiones y perfil.**

---

# 285. ADR-072

**Los artifacts incompatibles deberán recompilarse en lugar de migrarse implícitamente durante runtime.**

---

# 286. ADR-073

**El runtime no tendrá permisos para firmar ni activar builds.**

---

# 287. ADR-074

**La activación y rollback generarán registros de auditoría.**

---

# 288. ADR-075

**La eliminación de builds respetará referencias activas de Workers y requests.**

---

# 289. ADR-076

**El build runner no incorporará secretos en artifacts.**

---

# 290. ADR-077

**Los compilers de paquetes se considerarán componentes privilegiados de la cadena de suministro.**

---

# 291. ADR-078

**VoltStack permitirá provenance y SBOM de artifacts.**

---

# 292. ADR-079

**Los builds distribuidos utilizarán leases y fencing tokens.**

---

# 293. ADR-080

**Una discrepancia de fingerprint será tratada como incidente de integridad.**

---

# 294. Implementación V1

La V1 deberá incluir:

* compiler identity;
* compiler registry freeze;
* compiler dependency graph;
* isolated staging workspace;
* secure path resolver;
* structured PHP emitter;
* artifact schemas;
* artifact fingerprints;
* controller artifact manifest;
* strict manifest validation;
* atomic build activation;
* per-request build pinning;
* same-build execution bundles;
* secure artifact loader;
* read-only active builds;
* rollback eligibility;
* build revocation;
* OPcache-safe unique build paths;
* preload validation;
* security audits;
* dynamic vs compiled equivalence tests.

---

# 295. Implementación V2

Podrá agregar:

* signed manifests;
* external key management;
* remote artifact cache;
* build provenance;
* artifact SBOM;
* distributed build coordination;
* fleet activation;
* signed revocation lists;
* security epochs;
* content-addressed artifacts.

---

# 296. Implementación V3

Podrá incorporar:

* transparency log;
* reproducible build verification;
* isolated compiler processes;
* compiler sandboxing externo;
* policy-as-code para deployment;
* hardware-backed signing;
* remote attestation;
* artifact promotion entre environments.

---

# 297. Flujo completo de compilación segura

```text
Trusted Source Revision
        │
        ▼
Create Isolated Build Workspace
        │
        ▼
Freeze Compiler Registry
        │
        ▼
Validate Compiler Identities and Capabilities
        │
        ▼
Compile Controller Artifacts
        │
        ▼
Validate Schemas and Generated PHP
        │
        ▼
Calculate Artifact Fingerprints
        │
        ▼
Build Artifact Dependency Graph
        │
        ▼
Generate Canonical Manifest
        │
        ▼
Sign Manifest
        │
        ▼
Validate Full Build
        │
        ▼
Smoke Load in Isolated Process
        │
        ▼
Mark Build Ready
        │
        ▼
Atomic Activation
        │
        ▼
Pin Build per Request
        │
        ▼
Secure Artifact Loading
```

---

# 298. Flujo de carga segura

```text
Resolve Active Build
        │
        ▼
Pin Build Identity
        │
        ▼
Load Manifest
        │
        ▼
Validate Schema
        │
        ▼
Validate Signature
        │
        ▼
Validate Compatibility
        │
        ▼
Check Build Revocation
        │
        ▼
Resolve Artifact Reference
        │
        ▼
Validate Manifest Membership
        │
        ▼
Resolve Canonical Path
        │
        ▼
Validate Fingerprint
        │
        ▼
Load Artifact
        │
        ▼
Cache by Build + Artifact + Fingerprint
```

---

# 299. Flujo de rollback seguro

```text
Rollback Requested
        │
        ▼
Authorize Operator
        │
        ▼
Resolve Candidate Build
        │
        ▼
Check Revocation
        │
        ▼
Check Security Epoch
        │
        ▼
Check Runtime Compatibility
        │
        ▼
Validate Manifest and Signature
        │
        ▼
Record Audit Reason
        │
        ▼
Atomic Activation
        │
        ▼
Restart Workers if Required
```

---

# 300. Conclusión

El modelo de seguridad de compilación protege la frontera entre la arquitectura declarativa de VoltStack y el código optimizado que finalmente ejecutará el runtime.

Los componentes centrales serán:

```text
ControllerCompilerRegistry
BuildWorkspace
SecurePhpCodeEmitter
ArtifactSchemaValidator
ControllerArtifactManifest
ManifestSignatureVerifier
AtomicBuildActivator
SecureArtifactLoader
ArtifactRevocationRegistry
OpcacheBuildValidator
```

La propiedad más importante es:

```text
Un artifact solo puede ejecutarse cuando su identidad,
contenido, build, manifest, compatibilidad y cadena de
confianza han sido validados.
```

De esta forma VoltStack podrá aprovechar compilación agresiva, OPcache, preload y Workers persistentes sin convertir las optimizaciones en un mecanismo de bypass de seguridad.

---

# CONTROLLER_SECURITY_MODEL_PART_04.md

## Transport & Response Security

**Versión:** 1.0
**Estado:** Draft arquitectónico
**Módulo:** `VoltStack\Quantum\Controllers\Security\Transport`
**Entrega:** 1 de varias
**Cobertura de esta entrega:** Secciones 1–80
**Ámbito general:** Seguridad del transporte HTTP, normalización de respuestas, encabezados, cookies, sesiones, CORS, CSRF, CSP, cache, redirecciones, descargas, streaming, SSE, JSON, XML, SPA Protocol, Volt Protocol, proxies, hosts, compresión y defensa contra manipulación del protocolo.

**Dependencias principales:**

* `CONTROLLER_SECURITY_MODEL.md`
* `CONTROLLER_SECURITY_MODEL_PART_02.md`
* `CONTROLLER_SECURITY_MODEL_PART_03.md`
* `CONTROLLER_RESULT_NORMALIZATION_SYSTEM.md`
* `CONTROLLER_RESULT_TRANSFORMATION_ENGINE.md`
* `HTTP_RESPONSE_SYSTEM.md`
* `RESPONSE_TRANSPORT_SYSTEM.md`
* `SPA_PROTOCOL.md`
* `VOLT_PROTOCOL.md`
* `SECURITY_MODEL.md`

---

# 1. Introducción

La capa de transporte constituye la última frontera entre la ejecución interna de un controlador y el cliente.

Una aplicación puede:

* resolver correctamente el controlador;
* autorizar la acción;
* validar parámetros;
* ejecutar el método esperado;
* transformar el resultado correctamente;

y aun así quedar comprometida si la respuesta HTTP se construye o transporta de forma insegura.

Ejemplos:

* encabezados manipulables;
* cookies débiles;
* redirecciones abiertas;
* contenido sensible cacheado;
* tipos MIME incorrectos;
* inyección de saltos de línea;
* archivos descargables con nombres maliciosos;
* eventos SSE con datos sin codificar;
* metadata interna expuesta al frontend;
* errores detallados enviados al cliente;
* respuestas SPA sin integridad contextual.

Por ello, la seguridad del controlador no termina en la invocación.

```text
Controller Execution
    ↓
Result Normalization
    ↓
Security Classification
    ↓
Response Construction
    ↓
Header Policy
    ↓
Cookie Policy
    ↓
Content Encoding
    ↓
Transport Validation
    ↓
Emission
```

---

# 2. Objetivo principal

El sistema deberá garantizar que toda respuesta producida por Controllers:

* corresponda al contexto de seguridad de la ejecución;
* tenga un tipo de contenido correcto;
* utilice encabezados seguros;
* no exponga metadata interna innecesaria;
* no permita inyección de encabezados;
* no genere redirecciones abiertas;
* aplique políticas de cache apropiadas;
* proteja cookies y sesiones;
* respete clasificación de sensibilidad;
* codifique correctamente el contenido;
* limite streaming y SSE;
* preserve seguridad en SPA;
* falle cerrado ante inconsistencias críticas.

---

# 3. Principio fundamental

```text
A valid controller result
    ≠
A safe HTTP response
```

El resultado del controlador deberá pasar por una capa adicional de seguridad antes de llegar al cliente.

---

# 4. Modelo general

```text
ControllerResult
    ↓
ResultNormalizer
    ↓
ResponseSecurityClassifier
    ↓
SecureResponseBuilder
    ↓
HeaderPolicyEngine
    ↓
CookiePolicyEngine
    ↓
ContentSecurityProcessor
    ↓
TransportSecurityValidator
    ↓
ResponseEmitter
```

---

# 5. Activos protegidos

Los principales activos serán:

* datos personales;
* tokens;
* cookies;
* sesiones;
* información de autorización;
* metadata del controlador;
* rutas internas;
* nombres de clases;
* stack traces;
* identificadores de tenant;
* archivos;
* streams;
* protocolo SPA;
* estado hidratado;
* headers de seguridad;
* reglas de cache;
* redirects;
* información de errores.

---

# 6. Superficie de ataque

La superficie incluye:

* status line;
* response headers;
* cookies;
* body;
* content negotiation;
* redirects;
* cache intermediaries;
* reverse proxies;
* CDN;
* browsers;
* service workers;
* SPA runtime;
* streaming;
* event streams;
* file downloads;
* compression;
* range requests.

---

# 7. Actores

```text
Controller
Result Transformer
Response Builder
Middleware
Security Policy
Reverse Proxy
CDN
Browser
SPA Runtime
API Client
Attacker
Compromised Package
Misconfigured Application
```

---

# 8. Categorías de amenazas

Amenazas principales:

* response splitting;
* header injection;
* cache poisoning;
* cache leakage;
* MIME confusion;
* XSS;
* clickjacking;
* cookie theft;
* session fixation;
* CSRF;
* open redirect;
* host header poisoning;
* proxy confusion;
* sensitive data exposure;
* content sniffing;
* malicious filenames;
* stream abuse;
* SPA state leakage;
* protocol downgrade;
* compression side channels.

---

# 9. Security invariant principal

```text
No response shall be emitted unless:
    status is valid
    headers are valid
    content type is explicit
    body matches content type
    sensitive data policy is satisfied
    cookies satisfy security policy
    redirect target is trusted
    transport context is valid
```

---

# 10. Response Trust Model

Los elementos del pipeline se clasificarán así:

```text
Controller result          Semi-trusted
Framework response object  Trusted structure
Application headers        Untrusted until validated
Package headers            Semi-trusted
Core security headers      Trusted policy output
Request-derived values     Untrusted
Proxy-derived values       Untrusted until trusted-proxy validation
```

---

# 11. Controller result trust

El resultado de un controlador no deberá considerarse automáticamente seguro.

Podrá contener:

* strings sin escapar;
* rutas no validadas;
* URLs externas;
* objetos serializables inesperados;
* datos sensibles;
* streams sin límites;
* nombres de archivo inseguros;
* headers personalizados.

---

# 12. Response object trust

Un objeto que implemente `ResponseInterface` tampoco deberá omitir las validaciones centrales.

El framework deberá distinguir:

```text
Trusted core response
Application response
Third-party response
Raw transport response
```

---

# 13. Raw response bypass

No deberá existir una API pública que permita emitir directamente bytes y headers sin pasar por controles mínimos.

---

# 14. Secure Response Pipeline

Pipeline recomendado:

```text
Receive Controller Result
    ↓
Normalize Result
    ↓
Resolve Response Type
    ↓
Classify Sensitivity
    ↓
Resolve Content Type
    ↓
Encode Body
    ↓
Apply Header Policies
    ↓
Apply Cookie Policies
    ↓
Apply Cache Policies
    ↓
Apply Transport Policies
    ↓
Validate Final Response
    ↓
Emit
```

---

# 15. ControllerResult

```php
interface ControllerResultInterface
{
    public function resultType(): ControllerResultType;

    public function payload(): mixed;

    public function metadata(): ControllerResultMetadata;
}
```

---

# 16. ControllerResultType

```php
enum ControllerResultType: string
{
    case Html = 'html';
    case Json = 'json';
    case Xml = 'xml';
    case Text = 'text';
    case Redirect = 'redirect';
    case File = 'file';
    case Download = 'download';
    case Stream = 'stream';
    case EventStream = 'event_stream';
    case Spa = 'spa';
    case Empty = 'empty';
    case Custom = 'custom';
}
```

---

# 17. Result normalization

El normalizador deberá convertir resultados arbitrarios en representaciones explícitas.

Ejemplos:

```text
array      → JSON or ViewData, según contexto explícito
string     → Text or HTML explícito
object     → Transformer obligatorio
null       → EmptyResponse
generator  → StreamResponse
```

---

# 18. Ambiguous results

Los resultados ambiguos deberán rechazarse o resolverse mediante configuración explícita.

Ejemplo:

```php
return '<script>alert(1)</script>';
```

El framework no deberá asumir silenciosamente que se trata de HTML seguro.

---

# 19. Explicit response types

Se favorecerá:

```php
return Response::html($content);
return Response::json($data);
return Response::download($path);
return Response::redirect($url);
```

sobre retornos ambiguos.

---

# 20. ResponseSecurityContext

```php
final readonly class ResponseSecurityContext
{
    public function __construct(
        public string $executionId,
        public string $routeName,
        public string $controller,
        public string $method,
        public ResponseSensitivity $sensitivity,
        public TransportProfile $transportProfile,
        public bool $authenticated,
        public ?string $tenantId,
    ) {
    }
}
```

---

# 21. Response classification

Toda respuesta deberá clasificarse.

```php
enum ResponseSensitivity: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Private = 'private';
    case Confidential = 'confidential';
    case Restricted = 'restricted';
}
```

---

# 22. Public response

Podrá:

* ser cacheada públicamente;
* exponerse sin autenticación;
* distribuirse por CDN;
* incluir metadata no sensible.

Siempre deberá respetar:

* content type;
* CSP;
* MIME protection;
* header integrity.

---

# 23. Internal response

Destinada a:

* herramientas internas;
* redes privadas;
* paneles administrativos;
* APIs de servicio.

No implica ausencia de autenticación.

---

# 24. Private response

Relacionada con un usuario autenticado.

Por defecto deberá:

* evitar cache pública;
* proteger cookies;
* limitar metadata;
* aplicar `Cache-Control: private`.

---

# 25. Confidential response

Puede incluir:

* datos personales;
* información financiera;
* credenciales temporales;
* información empresarial sensible.

Deberá usar políticas más estrictas.

---

# 26. Restricted response

La clasificación más alta.

Podrá requerir:

* `no-store`;
* auditoría;
* cifrado adicional de aplicación;
* no inclusión en logs;
* prohibición de compresión;
* prohibición de streaming;
* no persistencia en frontend.

---

# 27. Classification inheritance

La clasificación podrá derivarse de:

```text
Route metadata
Controller metadata
Method metadata
Authorization policy
Returned data classification
Transport profile
```

Se aplicará la clasificación más restrictiva.

---

# 28. Response classification metadata

```php
#[ResponseSecurity(
    sensitivity: ResponseSensitivity::Confidential,
    cache: ResponseCachePolicy::NoStore
)]
public function profile(): UserProfile
{
}
```

---

# 29. Runtime classification escalation

Un transformer podrá elevar la sensibilidad si detecta campos sensibles.

No deberá reducirla sin autorización.

---

# 30. ResponseMetadata

```php
final readonly class SecureResponseMetadata
{
    public function __construct(
        public ResponseSensitivity $sensitivity,
        public string $contentType,
        public ResponseCachePolicy $cachePolicy,
        public bool $allowEmbedding,
        public bool $allowCompression,
        public bool $allowRangeRequests,
        public bool $allowFrontendPersistence,
    ) {
    }
}
```

---

# 31. Transport profiles

```php
enum TransportProfile: string
{
    case Browser = 'browser';
    case Api = 'api';
    case Spa = 'spa';
    case InternalService = 'internal_service';
    case Download = 'download';
    case Stream = 'stream';
    case EventStream = 'event_stream';
}
```

---

# 32. Browser profile

Deberá priorizar:

* CSP;
* clickjacking protection;
* cookies;
* CSRF;
* MIME safety;
* referrer policy.

---

# 33. API profile

Deberá priorizar:

* JSON consistency;
* no HTML errors;
* authentication headers;
* cache policy;
* CORS;
* rate-limit metadata segura.

---

# 34. SPA profile

Deberá priorizar:

* Volt Protocol integrity;
* state exposure control;
* navigation security;
* CSRF;
* hydration safety;
* frontend cache rules.

---

# 35. Download profile

Deberá priorizar:

* filename sanitation;
* content disposition;
* MIME safety;
* authorization continuity;
* range policy;
* cache rules.

---

# 36. Stream profile

Deberá priorizar:

* bounded execution;
* disconnect handling;
* no header mutation after start;
* rate limiting;
* output encoding;
* sensitive-data restrictions.

---

# 37. Response policy resolution

```php
interface ResponseSecurityPolicyResolverInterface
{
    public function resolve(
        ControllerExecutionContext $execution,
        NormalizedControllerResult $result
    ): ResolvedResponseSecurityPolicy;
}
```

---

# 38. Policy precedence

Orden recomendado:

```text
Framework hard security rules
    ↓
Environment profile
    ↓
Route security metadata
    ↓
Controller metadata
    ↓
Method metadata
    ↓
Result-specific escalation
```

Una capa inferior no podrá desactivar una regla hard.

---

# 39. ResolvedResponseSecurityPolicy

```php
final readonly class ResolvedResponseSecurityPolicy
{
    public function __construct(
        public ResponseSensitivity $sensitivity,
        public HeaderSecurityPolicy $headers,
        public CookieSecurityPolicy $cookies,
        public ResponseCachePolicy $cache,
        public ContentSecurityPolicy $contentSecurity,
        public RedirectSecurityPolicy $redirects,
        public StreamSecurityPolicy $streams,
    ) {
    }
}
```

---

# 40. Response builder

```php
interface SecureResponseBuilderInterface
{
    public function build(
        NormalizedControllerResult $result,
        ResolvedResponseSecurityPolicy $policy,
        ResponseSecurityContext $context
    ): SecureHttpResponse;
}
```

---

# 41. SecureHttpResponse

```php
final class SecureHttpResponse
{
    public function __construct(
        private int $status,
        private SecureHeaderBag $headers,
        private SecureCookieBag $cookies,
        private ResponseBodyInterface $body,
        private SecureResponseMetadata $metadata,
    ) {
    }
}
```

---

# 42. Response immutability

Después de completar la fase de seguridad, la respuesta deberá congelarse.

```text
Mutable construction
    ↓
Security validation
    ↓
Frozen response
    ↓
Emission
```

---

# 43. Freeze point

Después del freeze:

* middleware no podrá alterar headers críticos;
* no podrán añadirse cookies;
* no podrá cambiarse content type;
* no podrá cambiarse cache policy;
* no podrá modificarse redirect target.

---

# 44. Late mutation

Una modificación después del freeze deberá:

* lanzar excepción;
* abortar emisión;
* registrarse como violación.

---

# 45. Status code validation

Solo podrán utilizarse códigos válidos.

---

# 46. Status semantics

El sistema deberá validar coherencia entre:

* status;
* body;
* headers;
* response type.

Ejemplos:

* `204` no deberá contener body;
* `304` deberá respetar semántica de cache;
* redirects deberán incluir location válida;
* errores de autenticación deberán usar códigos consistentes.

---

# 47. Custom status codes

Deberán estar limitados a rangos válidos y perfiles compatibles.

---

# 48. Reason phrases

No deberán aceptar contenido derivado de usuario.

Podrán omitirse o generarse desde un registry seguro.

---

# 49. Response Header Model

Los headers representan una superficie de seguridad crítica.

---

# 50. SecureHeaderBag

```php
interface SecureHeaderBagInterface
{
    public function set(
        HeaderName $name,
        HeaderValue $value
    ): void;

    public function add(
        HeaderName $name,
        HeaderValue $value
    ): void;

    public function freeze(): void;
}
```

---

# 51. HeaderName

```php
final readonly class HeaderName
{
    public function __construct(
        public string $value
    ) {
    }
}
```

Deberá validar:

* caracteres permitidos;
* longitud;
* canonicalización;
* ausencia de controles.

---

# 52. HeaderValue

Deberá rechazar:

* CR;
* LF;
* null bytes;
* caracteres de control prohibidos;
* valores demasiado largos.

---

# 53. Response splitting

La inyección de:

```text
\r\n
```

podría crear headers o respuestas adicionales.

VoltStack deberá bloquearla en cualquier valor de header.

---

# 54. Header sources

Los headers podrán provenir de:

* framework core;
* middleware;
* controller;
* package;
* proxy integration;
* security policy.

Todos deberán pasar por validación.

---

# 55. Protected headers

Algunos headers no podrán ser definidos directamente por controladores.

Ejemplos:

* `Content-Length`;
* `Transfer-Encoding`;
* `Connection`;
* `Set-Cookie`;
* `Content-Security-Policy`;
* `Strict-Transport-Security`;
* `Access-Control-Allow-Origin`;
* `Location` sin redirect validator.

---

# 56. Header ownership

```php
enum HeaderOwner: string
{
    case Transport = 'transport';
    case Security = 'security';
    case Cache = 'cache';
    case Content = 'content';
    case Application = 'application';
}
```

---

# 57. Header precedence

Los headers del framework deberán poder sobrescribir valores inseguros definidos por aplicación.

---

# 58. Security Header Registry

```php
interface SecurityHeaderRegistryInterface
{
    public function register(
        SecurityHeaderDefinition $definition
    ): void;

    public function resolve(
        TransportProfile $profile
    ): iterable;
}
```

---

# 59. SecurityHeaderDefinition

```php
final readonly class SecurityHeaderDefinition
{
    public function __construct(
        public HeaderName $name,
        public HeaderValueFactoryInterface $factory,
        public HeaderMergeStrategy $mergeStrategy,
        public bool $mandatory,
    ) {
    }
}
```

---

# 60. HeaderMergeStrategy

```php
enum HeaderMergeStrategy: string
{
    case Replace = 'replace';
    case Append = 'append';
    case Intersect = 'intersect';
    case MostRestrictive = 'most_restrictive';
    case RejectConflict = 'reject_conflict';
}
```

---

# 61. Header canonicalization

El sistema deberá tratar nombres de headers como case-insensitive.

No deberán existir duplicados ambiguos como:

```text
Content-Type
content-type
CONTENT-TYPE
```

---

# 62. Duplicate headers

La política dependerá del header.

* algunos admiten múltiples valores;
* otros deberán existir una sola vez;
* algunos deberán fusionarse;
* otros deberán provocar rechazo.

---

# 63. Hop-by-hop headers

No deberán ser controlados por aplicaciones normales:

* `Connection`;
* `Keep-Alive`;
* `Proxy-Authenticate`;
* `Proxy-Authorization`;
* `TE`;
* `Trailer`;
* `Transfer-Encoding`;
* `Upgrade`.

---

# 64. Content-Length

Deberá ser calculado por la capa de transporte cuando aplique.

No deberá confiarse en un valor definido por controlador.

---

# 65. Transfer-Encoding

Deberá ser responsabilidad del servidor HTTP o transport adapter.

---

# 66. Header limits

Se deberán definir límites para:

* número total de headers;
* longitud de nombre;
* longitud de valor;
* tamaño total.

---

# 67. Header overflow

Una respuesta que exceda límites deberá fallar antes de emisión.

---

# 68. Security headers base

El perfil browser deberá considerar al menos:

* `Content-Security-Policy`;
* `Strict-Transport-Security`;
* `X-Content-Type-Options`;
* `Referrer-Policy`;
* `Permissions-Policy`;
* protección contra framing;
* cache policy.

---

# 69. X-Content-Type-Options

Se utilizará:

```text
X-Content-Type-Options: nosniff
```

para perfiles browser compatibles.

---

# 70. MIME sniffing

La respuesta deberá declarar un content type correcto.

`nosniff` no corrige un tipo MIME incorrecto.

---

# 71. Frame protection

La política moderna deberá priorizar CSP:

```text
frame-ancestors
```

Podrá emitir además `X-Frame-Options` para compatibilidad.

---

# 72. Default frame policy

Por defecto, páginas administrativas y privadas no deberán poder embeberse.

---

# 73. Referrer Policy

Se deberá aplicar una política segura por defecto.

Ejemplo conceptual:

```text
strict-origin-when-cross-origin
```

Podrá endurecerse para respuestas confidenciales.

---

# 74. Restricted referrer policy

Para respuestas restringidas podrá utilizarse:

```text
no-referrer
```

---

# 75. Permissions Policy

Deberá deshabilitar capacidades del navegador no utilizadas.

Ejemplos:

* camera;
* microphone;
* geolocation;
* payment;
* usb;
* fullscreen según contexto.

---

# 76. Header profile resolution

Los security headers deberán resolverse según:

* response type;
* route;
* sensitivity;
* browser/API;
* embedding requirements;
* environment.

---

# 77. Header policy conflicts

Ejemplo:

```text
Controller requests frame embedding
Security profile denies frame embedding
```

Deberá prevalecer la política más restrictiva.

---

# 78. Header validation before emission

```php
interface FinalResponseSecurityValidatorInterface
{
    public function validate(
        SecureHttpResponse $response,
        ResponseSecurityContext $context
    ): FinalResponseSecurityValidationResult;
}
```

---

# 79. Validation categories

El validator deberá revisar:

* status;
* header syntax;
* header ownership;
* duplicate headers;
* content type;
* content length;
* security headers;
* cookies;
* cache policy;
* body compatibility;
* transport profile.

---

# 80. Resultado de esta entrega

Esta entrega establece:

```text
Transport Security Foundations
Response Trust Model
Secure Response Pipeline
Result Classification
Transport Profiles
Response Policy Resolution
Response Immutability
Status Validation
Secure Header Model
Base Security Header Architecture
```

# CONTROLLER_SECURITY_MODEL_PART_04.md

## Transport & Response Security

**Entrega:** 2 de varias
**Cobertura de esta entrega:** Secciones 81–170
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_04.md — Entrega 1`

---

# 81. Content-Type Security

El tipo de contenido deberá resolverse de forma explícita y coherente con el cuerpo.

```text
Declared Content-Type
        =
Actual Response Representation
```

Una discrepancia podrá provocar:

* ejecución de contenido inesperado;
* MIME sniffing;
* XSS;
* interpretación incorrecta por proxies;
* cache poisoning;
* descargas inseguras.

---

# 82. Content type resolution

```php
interface ResponseContentTypeResolverInterface
{
    public function resolve(
        NormalizedControllerResult $result,
        ResolvedResponseSecurityPolicy $policy,
        RequestNegotiationContext $request
    ): ResolvedContentType;
}
```

---

# 83. ResolvedContentType

```php
final readonly class ResolvedContentType
{
    public function __construct(
        public MediaType $mediaType,
        public ?string $charset,
        public bool $nosniffRequired,
        public ContentEncodingPolicy $encodingPolicy,
    ) {
    }
}
```

---

# 84. MediaType

```php
final readonly class MediaType
{
    public function __construct(
        public string $type,
        public string $subtype,
        public array $parameters = [],
    ) {
    }
}
```

---

# 85. Canonical media types

VoltStack deberá mantener un registro canónico.

Ejemplos:

```text
text/html
text/plain
application/json
application/problem+json
application/xml
application/problem+xml
application/octet-stream
text/event-stream
application/pdf
image/png
image/jpeg
```

---

# 86. MIME Registry

```php
interface MimeTypeRegistryInterface
{
    public function resolveByExtension(string $extension): ?MediaType;

    public function resolveByResponseType(
        ControllerResultType $type
    ): MediaType;

    public function isAllowed(MediaType $type): bool;
}
```

---

# 87. Trusted MIME mappings

Los mappings críticos deberán provenir del framework o configuración confiable.

No deberá permitirse que un usuario final registre arbitrariamente:

```text
.php → text/html
```

---

# 88. Extension trust

La extensión de un archivo no será evidencia suficiente del tipo real.

Para descargas sensibles podrá combinarse:

* extensión;
* metadata conocida;
* detección limitada;
* allowlist;
* tipo declarado por storage confiable.

---

# 89. MIME detection limits

La detección heurística no deberá utilizarse como única garantía.

Podrá confundirse con archivos políglotas.

---

# 90. Polyglot files

Un archivo podrá ser válido para más de un parser.

Ejemplos conceptuales:

* imagen con contenido HTML;
* PDF con payload adicional;
* SVG con scripts;
* archivo comprimido con nombres engañosos.

Por ello deberán existir políticas específicas por tipo.

---

# 91. Charset security

Las respuestas textuales deberán declarar charset cuando corresponda.

Valor recomendado:

```text
UTF-8
```

---

# 92. Charset normalization

No deberán aceptarse charsets arbitrarios derivados de input.

---

# 93. UTF-7

No deberá emitirse ni negociarse UTF-7.

---

# 94. Invalid byte sequences

El encoder deberá definir una política:

* reject;
* replace;
* sanitize;
* binary response.

Para contenido HTML y JSON se recomienda rechazar o normalizar de forma segura.

---

# 95. Content-Type ownership

`Content-Type` será propiedad de la capa de contenido.

Un controlador podrá solicitar un tipo, pero deberá validarse.

---

# 96. Content-Type override

La aplicación no deberá cambiar el tipo después de codificar el body.

---

# 97. Body-content coherence

El validator deberá comprobar:

```text
HTML body          → text/html
JSON encoder       → application/json
SSE stream         → text/event-stream
Binary download    → approved binary media type
```

---

# 98. Missing Content-Type

Una respuesta con body no vacío deberá tener un tipo explícito, salvo protocolos especiales controlados.

---

# 99. Empty responses

Las respuestas `204` y `304` deberán seguir reglas específicas y no incluir cuerpos inconsistentes.

---

# 100. Content Negotiation

La negociación deberá ser explícita, limitada y determinista.

---

# 101. Negotiation inputs

Podrá considerar:

* route capabilities;
* `Accept`;
* transport profile;
* controller declaration;
* API version;
* SPA protocol header.

---

# 102. Accept header trust

`Accept` es input no confiable.

Deberá limitarse:

* tamaño;
* cantidad de media ranges;
* parámetros;
* wildcards;
* complejidad.

---

# 103. Negotiation algorithm

```text
Supported representations
    ∩
Client accepted representations
    ∩
Route security policy
    =
Selected representation
```

---

# 104. No implicit HTML fallback

Una API que no soporte el tipo solicitado no deberá devolver una página HTML de error por defecto.

---

# 105. Negotiation failure

Deberá producir una respuesta controlada, normalmente equivalente a:

```text
406 Not Acceptable
```

con formato seguro y coherente.

---

# 106. Wildcard handling

`*/*` podrá resolverse al tipo por defecto de la ruta.

No deberá activar formatos experimentales o inseguros.

---

# 107. Format aliases

Aliases como:

```text
json
html
xml
```

deberán resolverse mediante registry, no concatenación.

---

# 108. URL format parameters

Parámetros como:

```text
/resource.json
```

deberán validarse contra formatos soportados.

---

# 109. Query format parameters

El uso de:

```text
?format=json
```

deberá estar deshabilitado por defecto o controlado por ruta.

---

# 110. Negotiation ambiguity

Si múltiples tipos tienen igual prioridad, se utilizará una regla determinista.

---

# 111. API version negotiation

No deberá depender únicamente de media types arbitrarios.

El framework deberá validar versiones conocidas.

---

# 112. Vary header

Cuando la representación dependa de `Accept`, deberá considerarse:

```text
Vary: Accept
```

sin crear combinaciones de cache incontrolables.

---

# 113. Vary limits

No deberán añadirse valores derivados arbitrariamente del request.

---

# 114. HTML Response Security

Las respuestas HTML tienen el mayor riesgo de ejecución activa en navegador.

---

# 115. HTML response types

Se distinguirán:

```text
Framework-rendered HTML
Template-rendered HTML
Trusted static HTML
Sanitized rich text
Raw HTML
```

---

# 116. Raw HTML

El raw HTML deberá ser una capacidad restringida.

---

# 117. Safe HTML wrapper

```php
final readonly class TrustedHtml
{
    private function __construct(
        public string $content,
        public TrustedHtmlOrigin $origin,
    ) {
    }
}
```

La construcción deberá estar limitada a servicios autorizados.

---

# 118. HTML escaping

Todo dato insertado en templates deberá escaparse según contexto.

---

# 119. Contextual escaping

Los contextos no son equivalentes:

* HTML text;
* HTML attribute;
* JavaScript string;
* CSS value;
* URL;
* JSON embedded in HTML.

---

# 120. Template compiler security

El compilador Volt deberá emitir escapes contextuales cuando sea posible.

---

# 121. Explicit unescaped output

Una directiva de output sin escape deberá:

* ser explícita;
* requerir valor trusted;
* producir advertencia o error con string normal;
* integrarse con CSP.

---

# 122. Rich text sanitation

Contenido HTML de usuarios deberá pasar por un sanitizer basado en allowlist.

---

# 123. HtmlSanitizerInterface

```php
interface HtmlSanitizerInterface
{
    public function sanitize(
        string $html,
        HtmlSanitizationPolicy $policy
    ): SanitizedHtml;
}
```

---

# 124. HtmlSanitizationPolicy

Deberá controlar:

* tags;
* attributes;
* URL schemes;
* CSS;
* embedded media;
* SVG;
* forms;
* iframes.

---

# 125. Event handler attributes

Deberán eliminarse atributos como:

```text
onclick
onerror
onload
```

salvo una capacidad muy restringida que no debería existir para contenido de usuario.

---

# 126. URL attributes

Atributos como:

* `href`;
* `src`;
* `action`;
* `formaction`;
* `poster`;

deberán validar schemes y destinos.

---

# 127. Dangerous URL schemes

Se deberán rechazar o restringir:

```text
javascript:
vbscript:
data:
file:
```

---

# 128. Data URLs

Solo podrán permitirse para tipos concretos y tamaños limitados.

---

# 129. SVG security

SVG deberá considerarse contenido activo.

Puede contener:

* scripts;
* external references;
* event handlers;
* embedded HTML;
* animation.

---

# 130. Inline SVG

Solo deberá permitirse desde fuentes confiables o tras sanitización específica.

---

# 131. Uploaded SVG

Por defecto deberá servirse como descarga o desde un origen aislado, no inline en la aplicación principal.

---

# 132. HTML base tag

El tag `<base>` podrá alterar resolución de URLs.

Deberá estar prohibido en contenido sanitizado.

---

# 133. Meta refresh

Deberá prohibirse en contenido de usuario.

---

# 134. Embedded forms

Los formularios dentro de rich text deberán eliminarse por defecto.

---

# 135. Iframes

Deberán estar prohibidos salvo allowlist explícita de orígenes.

---

# 136. Sandboxed iframe

Cuando se permita embedding, deberá preferirse `sandbox` con capacidades mínimas.

---

# 137. HTML comments

Pueden revelar información interna.

El build de producción podrá eliminar comentarios no necesarios.

---

# 138. Source maps y HTML

No deberán exponerse referencias a source maps internos en producción si contienen rutas o código sensible.

---

# 139. Debug toolbar

No deberá inyectarse en respuestas:

* privadas sensibles;
* descargas;
* streams;
* APIs;
* producción.

---

# 140. Content Security Policy

CSP será el principal mecanismo browser-side para limitar ejecución de contenido.

---

# 141. CSP Engine

```php
interface ContentSecurityPolicyEngineInterface
{
    public function resolve(
        ResponseSecurityContext $context,
        CspRequirements $requirements
    ): ResolvedContentSecurityPolicy;
}
```

---

# 142. CSP directives

El motor deberá soportar al menos:

* `default-src`;
* `script-src`;
* `style-src`;
* `img-src`;
* `font-src`;
* `connect-src`;
* `media-src`;
* `object-src`;
* `frame-src`;
* `frame-ancestors`;
* `base-uri`;
* `form-action`;
* `worker-src`;
* `manifest-src`;
* `upgrade-insecure-requests`.

---

# 143. Secure CSP baseline

Baseline conceptual:

```text
default-src 'self'
object-src 'none'
base-uri 'self'
frame-ancestors 'none'
form-action 'self'
```

Deberá adaptarse a las necesidades reales.

---

# 144. CSP policy composition

Las políticas podrán provenir de:

* framework baseline;
* application profile;
* route metadata;
* component requirements;
* asset pipeline;
* SPA runtime.

---

# 145. CSP conflict resolution

La combinación deberá producir la intersección o la opción más restrictiva cuando sea posible.

---

# 146. CSP widening

Una ruta no deberá ampliar una política global sin una capability explícita.

---

# 147. CSP nonce model

Los scripts inline autorizados deberán usar nonce por respuesta.

---

# 148. CspNonce

```php
final readonly class CspNonce
{
    public function __construct(
        public string $value
    ) {
    }
}
```

---

# 149. Nonce generation

El nonce deberá:

* usar aleatoriedad criptográfica;
* ser único por respuesta;
* tener entropía suficiente;
* no derivarse de request data;
* no reutilizarse entre respuestas.

---

# 150. Nonce exposure

Podrá insertarse en HTML generado, pero no deberá persistirse ni registrarse.

---

# 151. Nonce propagation

El renderer Volt y el asset manager deberán recibir el nonce mediante contexto seguro.

---

# 152. Nonce misuse

No deberá permitirse que contenido de usuario controle el atributo nonce.

---

# 153. CSP hashes

Para scripts o estilos estáticos inline podrán utilizarse hashes aprobados.

---

# 154. Hash generation

Los hashes deberán corresponder exactamente al contenido emitido.

---

# 155. Dynamic inline code

No deberá depender de hashes si el contenido cambia por request.

---

# 156. unsafe-inline

Deberá estar prohibido en perfiles Strict y High Security.

---

# 157. unsafe-eval

Deberá evitarse.

El frontend runtime de VoltStack deberá diseñarse sin requerirlo.

---

# 158. strict-dynamic

Podrá utilizarse cuando la estrategia de carga y compatibilidad lo permitan.

---

# 159. Script source allowlist

Los dominios externos deberán declararse explícitamente.

---

# 160. Wildcard sources

No deberán permitirse wildcards amplios como:

```text
https:
*
```

en perfiles estrictos.

---

# 161. CDN scripts

Scripts de CDN deberán requerir:

* origen aprobado;
* versión fijada;
* SRI cuando aplique;
* política de fallback segura.

---

# 162. Subresource Integrity

```php
final readonly class SubresourceIntegrity
{
    public function __construct(
        public string $algorithm,
        public string $digest,
    ) {
    }
}
```

---

# 163. SRI requirements

Deberá usarse en assets externos estáticos cuando sea viable.

---

# 164. CSP report mode

VoltStack deberá soportar:

```text
Content-Security-Policy-Report-Only
```

para despliegue gradual.

---

# 165. CSP reports

Los reportes son input no confiable.

Deberán:

* validarse;
* limitarse;
* rate-limitarse;
* evitar log injection;
* no generar respuestas detalladas.

---

# 166. CSP violation endpoint

Deberá estar aislado de la lógica normal de Controllers cuando sea posible.

---

# 167. CSP per route

Rutas especiales podrán declarar requirements adicionales.

```php
#[Csp(
    scriptSources: ['self'],
    frameAncestors: ['none'],
)]
```

---

# 168. Component CSP requirements

Los componentes podrán declarar necesidades, pero no URLs arbitrarias en runtime.

---

# 169. Asset manifest integration

El asset pipeline deberá aportar:

* scripts;
* styles;
* SRI;
* hashes;
* origins;
* nonce requirements.

---

# 170. Resultado de esta entrega

Esta entrega establece:

```text
Content-Type Security
MIME Registry
Charset Security
Content Negotiation
HTML Response Security
Contextual Escaping
Rich Text Sanitization
SVG and Embedded Content Security
CSP Engine
CSP Nonces
CSP Hashes
SRI Integration
CSP Reporting
```

# CONTROLLER_SECURITY_MODEL_PART_04.md

## Transport & Response Security

**Entrega:** 3 de varias
**Cobertura de esta entrega:** Secciones 171–260
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_04.md — Entrega 2`

---

# 171. Trusted Types

Trusted Types deberá considerarse una defensa adicional contra DOM-based XSS.

Su objetivo será reducir la asignación de strings arbitrarios a sinks peligrosos del navegador.

Ejemplos:

* `innerHTML`;
* `outerHTML`;
* `insertAdjacentHTML`;
* `document.write`;
* creación dinámica de scripts;
* URLs de scripts;
* contenido HTML hidratado.

---

# 172. Trusted Types scope

Trusted Types aplicará principalmente a:

* VoltStack SPA Runtime;
* adaptadores React, Vue y Svelte;
* runtime de hidratación;
* sistema de componentes;
* plugins frontend;
* código JavaScript propio;
* integraciones de terceros.

---

# 173. Trusted Types CSP directive

El CSP Engine deberá soportar:

```text
require-trusted-types-for 'script'
```

y:

```text
trusted-types
```

---

# 174. Trusted Types enforcement profiles

```php
enum TrustedTypesMode: string
{
    case Disabled = 'disabled';
    case ReportOnly = 'report_only';
    case Enforced = 'enforced';
}
```

---

# 175. Report-only migration

La adopción recomendada será:

```text
Disabled
    ↓
Report Only
    ↓
Policy cleanup
    ↓
Enforced
```

---

# 176. Trusted Types policies

VoltStack deberá usar políticas con nombres controlados.

Ejemplos:

```text
voltstack-runtime
voltstack-hydration
voltstack-sanitized-html
voltstack-assets
```

---

# 177. Policy creation restrictions

La aplicación no deberá crear políticas arbitrarias sin registro previo.

---

# 178. TrustedTypesPolicyRegistry

```php
interface TrustedTypesPolicyRegistryInterface
{
    public function register(
        TrustedTypesPolicyDefinition $definition
    ): void;

    public function freeze(): void;

    public function all(): iterable;
}
```

---

# 179. TrustedTypesPolicyDefinition

```php
final readonly class TrustedTypesPolicyDefinition
{
    public function __construct(
        public string $name,
        public TrustedTypesCapabilitySet $capabilities,
        public TrustedTypesTrustLevel $trustLevel,
    ) {
    }
}
```

---

# 180. Trusted Types capabilities

```php
enum TrustedTypesCapability: string
{
    case CreateHtml = 'create_html';
    case CreateScript = 'create_script';
    case CreateScriptUrl = 'create_script_url';
}
```

---

# 181. HTML policy

La política para crear HTML deberá depender de:

* sanitizer confiable;
* templates compilados;
* contenido estático aprobado;
* hydration payload validado.

---

# 182. Script policy

La creación dinámica de scripts deberá estar prohibida por defecto.

---

# 183. Script URL policy

Solo deberá aceptar URLs provenientes del asset manifest o de una allowlist congelada.

---

# 184. Runtime sinks

El frontend runtime deberá centralizar todos los sinks peligrosos.

No deberán existir asignaciones dispersas a `innerHTML`.

---

# 185. Safe DOM update API

```text
Volt DOM Patcher
    ↓
Trusted Content Resolver
    ↓
Trusted Types Policy
    ↓
DOM Sink
```

---

# 186. Unsafe plugin behavior

Un plugin frontend que escriba HTML arbitrario deberá:

* declarar capability;
* pasar auditoría;
* ejecutar bajo policy específica;
* poder deshabilitarse por perfil.

---

# 187. Trusted Types violations

Deberán integrarse con:

* CSP reporting;
* frontend telemetry;
* incident classification;
* build provenance.

---

# 188. Hydration and Trusted Types

Los payloads de hidratación no deberán convertirse directamente en HTML.

---

# 189. Hydration payload model

```text
JSON State
    ↓
Schema Validation
    ↓
Component Resolution
    ↓
Safe DOM Operations
```

---

# 190. Server-provided HTML fragments

Si el protocolo permite fragments HTML, deberán envolverse como:

```php
final readonly class TrustedHydrationFragment
{
    public function __construct(
        public string $componentId,
        public SanitizedHtml $html,
        public string $fingerprint,
    ) {
    }
}
```

---

# 191. Fragment fingerprints

Un fragment podrá vincularse a:

* component ID;
* build ID;
* rendering version;
* content fingerprint.

---

# 192. Fragment policy

Los fragments no deberán incluir:

* scripts ejecutables;
* event handlers inline;
* iframes no aprobados;
* forms ocultos inesperados;
* URLs no validadas.

---

# 193. Inline Asset Security

Los assets inline incluyen:

* scripts;
* styles;
* SVG;
* JSON embebido;
* preload hints;
* import maps.

---

# 194. Inline script policy

Los scripts inline deberán usar:

* nonce;
* hash CSP;
* archivo externo;

en ese orden de preferencia según el caso.

---

# 195. Dynamic inline scripts

No deberán construirse concatenando datos de usuario.

---

# 196. Inline JSON

El JSON embebido en HTML deberá codificarse de forma segura.

---

# 197. JSON script blocks

Podrá utilizarse:

```html
<script type="application/json">
```

siempre que:

* el contenido se escape para contexto HTML;
* no contenga cierre prematuro de `script`;
* tenga tamaño limitado;
* esté vinculado a un schema.

---

# 198. Script closing sequence

El encoder deberá impedir que el payload introduzca:

```text
</script>
```

de forma ejecutable.

---

# 199. Hydration script data

Se recomienda emitir estado hidratado mediante:

* JSON seguro;
* atributos `data-*` limitados;
* referencias a endpoint;
* binary protocol futuro.

---

# 200. Inline styles

Los estilos inline deberán evitarse en perfiles estrictos.

---

# 201. Style nonces

Cuando se requieran estilos inline, podrán usar nonces CSP.

---

# 202. User-controlled CSS

CSS controlado por usuarios deberá tratarse como riesgoso.

Puede provocar:

* data exfiltration;
* UI redressing;
* tracking;
* layout manipulation;
* loading de recursos externos.

---

# 203. CSS sanitizer

Contenido CSS permitido deberá pasar por un parser y allowlist.

No por expresiones regulares simples.

---

# 204. Dangerous CSS features

Deberán restringirse:

* `url()`;
* `@import`;
* behavior legacy;
* external fonts;
* custom properties utilizadas como sinks;
* expresiones no soportadas.

---

# 205. Inline SVG assets

Los SVG propios podrán compilarse como assets confiables.

Los SVG dinámicos deberán sanitizarse.

---

# 206. Import Maps

Si VoltStack soporta import maps, estos deberán:

* generarse desde el asset manifest;
* estar protegidos por nonce o hash;
* no aceptar módulos arbitrarios del request;
* usar URLs confiables.

---

# 207. Module scripts

El CSP Engine deberá contemplar:

* `script-src`;
* module graph;
* dynamic import;
* worker scripts;
* crossorigin requirements.

---

# 208. Preload hints

Headers como:

```text
Link: <...>; rel=preload
```

deberán construirse desde recursos validados.

---

# 209. Link header injection

Las URLs y parámetros del header `Link` deberán serializarse con un builder seguro.

---

# 210. Resource hints

Se controlarán:

* preload;
* prefetch;
* preconnect;
* dns-prefetch;
* modulepreload.

---

# 211. Cross-origin resource hints

No deberán generarse para dominios no aprobados.

---

# 212. Clickjacking Protection

Clickjacking intenta cargar una aplicación dentro de un frame para engañar al usuario.

---

# 213. Primary control

La defensa principal será:

```text
Content-Security-Policy: frame-ancestors
```

---

# 214. Compatibility control

Podrá emitirse además:

```text
X-Frame-Options
```

---

# 215. FramePolicy

```php
enum FramePolicy: string
{
    case Deny = 'deny';
    case SameOrigin = 'same_origin';
    case AllowListedOrigins = 'allow_listed_origins';
}
```

---

# 216. Default frame policy

El default recomendado será:

```text
Deny
```

para:

* paneles administrativos;
* páginas privadas;
* autenticación;
* operaciones financieras;
* configuraciones de cuenta.

---

# 217. Same-origin framing

Solo deberá habilitarse cuando una funcionalidad real lo requiera.

---

# 218. Allowed frame origins

Los orígenes deberán ser:

* exactos;
* normalizados;
* con scheme explícito;
* sin wildcards amplios;
* definidos por configuración confiable.

---

# 219. Dynamic frame origins

No deberán derivarse directamente de parámetros de request.

---

# 220. Embeddable routes

Una ruta embebible deberá declarar explícitamente:

```php
#[Embeddable(
    origins: ['https://portal.example.com']
)]
```

---

# 221. Embed token

Para widgets embebibles podrá requerirse un token scoped.

---

# 222. Embedded application profile

El perfil embebido deberá ajustar:

* cookies;
* SameSite;
* CORS;
* CSP;
* postMessage;
* navigation;
* referrer policy.

---

# 223. UI redressing

Además de framing, se deberán considerar:

* overlays;
* transparent controls;
* fullscreen abuse;
* pointer lock;
* popups.

---

# 224. Frame busting JavaScript

No deberá considerarse una defensa suficiente.

---

# 225. postMessage security

Los componentes embebidos deberán validar:

* `event.origin`;
* message schema;
* message type;
* sender window;
* replay context.

---

# 226. Wildcard postMessage

No deberá usarse:

```javascript
postMessage(data, '*')
```

para datos sensibles.

---

# 227. HTTPS Enforcement

La seguridad del transporte dependerá de HTTPS correctamente aplicado.

---

# 228. Secure scheme resolution

La aplicación deberá determinar el scheme real únicamente después de validar trusted proxies.

---

# 229. Direct request scheme

Sin proxy confiable, se utilizará la información directa de la conexión.

---

# 230. Proxy-provided scheme

Headers como:

* `Forwarded`;
* `X-Forwarded-Proto`;

solo serán confiables desde proxies registrados.

---

# 231. HTTPS redirect

Cuando se requiera HTTPS, la redirección deberá usar:

* host validado;
* path normalizado;
* status code apropiado;
* query controlado.

---

# 232. HTTPS redirect loops

La configuración deberá detectar:

* proxy no confiable;
* scheme incorrecto;
* forwarded headers inconsistentes;
* múltiples capas de proxy.

---

# 233. Sensitive route HTTPS requirement

Rutas sensibles no deberán ejecutarse sobre HTTP.

Ejemplos:

* login;
* tokens;
* pagos;
* administración;
* datos confidenciales.

---

# 234. Secure transport guard

```php
interface SecureTransportGuardInterface
{
    public function assertSecure(
        RequestTransportContext $transport,
        RouteSecurityMetadata $route
    ): void;
}
```

---

# 235. Strict-Transport-Security

HSTS indica al navegador que el dominio deberá utilizar HTTPS.

---

# 236. HstsPolicy

```php
final readonly class HstsPolicy
{
    public function __construct(
        public int $maxAge,
        public bool $includeSubDomains,
        public bool $preload,
    ) {
    }
}
```

---

# 237. HSTS emission

Solo deberá emitirse sobre conexiones HTTPS consideradas seguras.

---

# 238. HSTS default

Un perfil de producción podrá usar un `max-age` amplio después de validar la infraestructura.

---

# 239. includeSubDomains

Solo deberá activarse cuando todos los subdominios relevantes soporten HTTPS.

---

# 240. HSTS preload

La opción `preload` implica compromisos operativos adicionales.

No deberá activarse automáticamente.

---

# 241. HSTS rollback risk

Una política larga puede dificultar recuperar subdominios que no soporten HTTPS.

---

# 242. Development environment

HSTS deberá deshabilitarse o aislarse en desarrollo local.

---

# 243. HTTPS downgrade

VoltStack deberá impedir enlaces y redirects que degraden de HTTPS a HTTP en contextos protegidos.

---

# 244. Mixed content

El CSP Engine podrá emitir:

```text
upgrade-insecure-requests
```

y, según compatibilidad:

```text
block-all-mixed-content
```

---

# 245. Secure URL generation

El URL Generator deberá conocer:

* trusted scheme;
* validated host;
* route requirements;
* proxy context.

---

# 246. Absolute URL security

Las URLs absolutas no deberán depender de un `Host` no validado.

---

# 247. Referrer Policy avanzada

La política deberá adaptarse a la sensibilidad de la respuesta.

---

# 248. ReferrerPolicy

```php
enum ReferrerPolicy: string
{
    case NoReferrer = 'no-referrer';
    case SameOrigin = 'same-origin';
    case Origin = 'origin';
    case StrictOrigin = 'strict-origin';
    case StrictOriginWhenCrossOrigin = 'strict-origin-when-cross-origin';
}
```

---

# 249. Default referrer policy

Para navegación general:

```text
strict-origin-when-cross-origin
```

será un baseline razonable.

---

# 250. Confidential response policy

Para respuestas confidenciales:

```text
no-referrer
```

o:

```text
same-origin
```

según funcionalidad.

---

# 251. URL sensitivity

Datos sensibles no deberán colocarse en URLs.

La referrer policy reduce exposición, pero no corrige una URL insegura.

---

# 252. Query string leakage

Tokens, correos, IDs sensibles y secretos no deberán persistir en query strings.

---

# 253. Per-route referrer policy

Las rutas podrán endurecer la política, pero no debilitar un hard baseline sin capability.

---

# 254. Meta referrer

La política deberá emitirse preferentemente como header.

El meta tag no sustituye la política de transporte.

---

# 255. Permissions Policy avanzada

Permissions Policy limitará capacidades del navegador por documento y frames.

---

# 256. PermissionsPolicyEngine

```php
interface PermissionsPolicyEngineInterface
{
    public function resolve(
        ResponseSecurityContext $context,
        BrowserCapabilityRequirements $requirements
    ): ResolvedPermissionsPolicy;
}
```

---

# 257. Browser capabilities

El registry deberá contemplar capacidades como:

* camera;
* microphone;
* geolocation;
* payment;
* usb;
* serial;
* bluetooth;
* clipboard-read;
* clipboard-write;
* fullscreen;
* display-capture;
* screen-wake-lock;
* publickey-credentials-get.

---

# 258. Default deny

Las capacidades no utilizadas deberían denegarse.

---

# 259. Capability allowlists

Las allowlists podrán incluir:

* `self`;
* orígenes exactos;
* ningún origen.

No deberán aceptar input directo del cliente.

---

# 260. Resultado de esta entrega

Esta entrega establece:

```text
Trusted Types Architecture
Trusted Types Policy Registry
Hydration Sink Protection
Inline Script and Style Security
Embedded JSON Security
CSS and SVG Safety
Import Map Security
Resource Hint Security
Clickjacking Protection
Frame Policies
postMessage Security
HTTPS Enforcement
HSTS
Mixed Content Protection
Advanced Referrer Policy
Permissions Policy Foundations
```

# CONTROLLER_SECURITY_MODEL_PART_04.md

## Transport & Response Security

**Entrega:** 4 de varias
**Cobertura de esta entrega:** Secciones 261–350
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_04.md — Entrega 3`

---

# 261. Permissions Policy enforcement

Permissions Policy no deberá tratarse como un header decorativo.

Su resolución deberá estar conectada con:

* requerimientos reales de la ruta;
* capacidades de componentes;
* iframes embebidos;
* frontend runtime;
* perfil de seguridad;
* clasificación de sensibilidad.

---

# 262. Capability declaration

Toda funcionalidad que requiera una capacidad sensible deberá declararla explícitamente.

```php
#[BrowserCapability(
    name: 'camera',
    scope: BrowserCapabilityScope::Self
)]
public function scanDocument(): Response
{
}
```

---

# 263. BrowserCapabilityScope

```php
enum BrowserCapabilityScope: string
{
    case None = 'none';
    case Self = 'self';
    case AllowedOrigins = 'allowed_origins';
}
```

---

# 264. Capability aggregation

Las capacidades requeridas podrán provenir de:

* route metadata;
* controller metadata;
* component manifests;
* embed configuration;
* frontend asset manifest.

La política final deberá aplicar la unión de requerimientos permitidos y posteriormente limitarla mediante el perfil global.

---

# 265. Capability escalation

Una ruta podrá solicitar una capacidad adicional, pero no deberá recibirla cuando:

* el perfil la prohíba;
* el origen no esté autorizado;
* la respuesta sea restricted;
* el componente no esté firmado o registrado;
* el transporte no sea seguro.

---

# 266. Capability downgrade

El framework podrá reducir permisos en tiempo de ejecución según:

* autenticación;
* tenant;
* tipo de dispositivo;
* contexto de embedding;
* clasificación de respuesta.

---

# 267. Runtime capability request

El frontend no deberá poder ampliar la política enviando metadata en el request.

---

# 268. Permissions Policy builder

```php
interface PermissionsPolicyBuilderInterface
{
    public function build(
        BrowserCapabilitySet $capabilities,
        ResponseSecurityContext $context
    ): HeaderValue;
}
```

---

# 269. Canonical serialization

La política deberá serializarse de forma determinista.

Esto facilita:

* testing;
* cache;
* auditoría;
* comparación entre builds.

---

# 270. Unsupported directives

El framework podrá omitir directivas no soportadas por el navegador, pero no deberá sustituirlas por permisos más amplios.

---

# 271. Iframe permission delegation

Un documento padre deberá delegar explícitamente capacidades a iframes autorizados.

---

# 272. allow attribute

El atributo `allow` de un iframe deberá generarse desde el mismo modelo de capabilities que el header.

---

# 273. Header-iframe consistency

No deberá existir una delegación en el iframe que contradiga la política global.

---

# 274. Sensitive capabilities

Deberán considerarse especialmente sensibles:

* camera;
* microphone;
* geolocation;
* display capture;
* clipboard read;
* payment;
* USB;
* serial;
* Bluetooth.

---

# 275. Permission audit events

Se deberán generar eventos cuando:

* una ruta solicite una capability prohibida;
* una capability sea reducida;
* un iframe intente delegación no permitida;
* un plugin frontend requiera permisos adicionales.

---

# 276. Cross-Origin Isolation

Cross-origin isolation permite habilitar capacidades avanzadas del navegador con mayor separación de procesos y recursos.

Puede ser necesaria para:

* `SharedArrayBuffer`;
* temporizadores de alta precisión;
* ciertos runtimes intensivos;
* procesamiento multimedia;
* herramientas de desarrollo especializadas.

---

# 277. Isolation components

El aislamiento moderno combina principalmente:

* COOP;
* COEP;
* CORP;
* CORS correcto;
* resource loading compatible.

---

# 278. CrossOriginIsolationMode

```php
enum CrossOriginIsolationMode: string
{
    case Disabled = 'disabled';
    case ReportOnly = 'report_only';
    case Enforced = 'enforced';
}
```

---

# 279. Isolation eligibility

No todas las rutas deberán usar aislamiento.

Será apropiado para:

* aplicaciones cerradas;
* herramientas internas;
* editores;
* runtimes que controlan todos sus assets.

Podrá ser problemático para páginas con múltiples integraciones externas.

---

# 280. Isolation profile

```php
final readonly class CrossOriginIsolationProfile
{
    public function __construct(
        public CrossOriginOpenerPolicy $coop,
        public CrossOriginEmbedderPolicy $coep,
        public CrossOriginResourcePolicy $corp,
        public CrossOriginIsolationMode $mode,
    ) {
    }
}
```

---

# 281. Cross-Origin-Opener-Policy

COOP controla la relación entre ventanas y browsing context groups.

---

# 282. COOP values

```php
enum CrossOriginOpenerPolicy: string
{
    case UnsafeNone = 'unsafe-none';
    case SameOriginAllowPopups = 'same-origin-allow-popups';
    case SameOrigin = 'same-origin';
}
```

---

# 283. COOP default

Para rutas que requieran aislamiento fuerte:

```text
same-origin
```

---

# 284. Popup compatibility

`same-origin` puede romper integraciones que dependen de `window.opener`.

Ejemplos:

* OAuth popup;
* payment popup;
* external identity providers.

---

# 285. OAuth routes

Las rutas involucradas en autenticación mediante popup podrán requerir:

```text
same-origin-allow-popups
```

o un flujo de navegación distinto.

---

# 286. Opener isolation

La aplicación no deberá depender de `window.opener` sin validar:

* origin;
* flow ID;
* nonce;
* expected window relationship.

---

# 287. Cross-Origin-Embedder-Policy

COEP controla si el documento puede cargar recursos cross-origin que no concedan permiso explícito.

---

# 288. COEP values

```php
enum CrossOriginEmbedderPolicy: string
{
    case UnsafeNone = 'unsafe-none';
    case RequireCorp = 'require-corp';
    case Credentialless = 'credentialless';
}
```

---

# 289. Require-Corp

`require-corp` exigirá que recursos cross-origin:

* utilicen CORS;
* o declaren CORP compatible.

---

# 290. Credentialless

`credentialless` podrá facilitar ciertos recursos cross-origin al omitir credenciales, pero deberá evaluarse cuidadosamente.

---

# 291. COEP compatibility

Antes de activar COEP se deberán verificar:

* CDN;
* fuentes;
* imágenes;
* scripts;
* workers;
* iframes;
* APIs;
* assets de terceros.

---

# 292. Cross-Origin-Resource-Policy

CORP permite que un recurso declare quién puede cargarlo.

---

# 293. CORP values

```php
enum CrossOriginResourcePolicy: string
{
    case CrossOrigin = 'cross-origin';
    case SameSite = 'same-site';
    case SameOrigin = 'same-origin';
}
```

---

# 294. Default resource policy

Para recursos privados:

```text
same-origin
```

Para assets compartidos entre subdominios confiables podrá considerarse:

```text
same-site
```

---

# 295. Public assets

Un asset público destinado a múltiples orígenes podrá declarar:

```text
cross-origin
```

solo cuando esa distribución sea intencional.

---

# 296. Resource classification

El CORP deberá resolverse según:

* sensibilidad;
* asset visibility;
* authentication;
* CDN strategy;
* tenant isolation.

---

# 297. API responses and CORP

Las respuestas API privadas no deberán quedar cargables como recursos cross-origin no autorizados.

---

# 298. Cross-origin isolation validator

```php
interface CrossOriginIsolationValidatorInterface
{
    public function validate(
        SecureHttpResponse $response,
        CrossOriginIsolationProfile $profile
    ): CrossOriginIsolationValidationResult;
}
```

---

# 299. Isolation consistency

El validator deberá comprobar:

* COOP presente cuando sea requerido;
* COEP compatible;
* CORP adecuado;
* CSP compatible;
* resources declarados;
* CORS coherente;
* iframes permitidos.

---

# 300. Report-only headers

VoltStack podrá emitir variantes report-only durante migración cuando el navegador las soporte.

---

# 301. Isolation reports

Los reportes deberán tratarse como input no confiable y pasar por límites de tamaño y frecuencia.

---

# 302. Origin-Agent-Cluster

El header:

```text
Origin-Agent-Cluster: ?1
```

puede solicitar aislamiento por origen.

---

# 303. Origin agent cluster policy

Podrá habilitarse para aplicaciones que quieran evitar compartir ciertos recursos de proceso entre orígenes relacionados.

---

# 304. Agent cluster compatibility

No deberá asumirse que todos los navegadores aplicarán exactamente el mismo comportamiento.

---

# 305. Browser isolation profiles

```php
enum BrowserIsolationProfile: string
{
    case Standard = 'standard';
    case Embedded = 'embedded';
    case CrossOriginIsolated = 'cross_origin_isolated';
    case OAuthPopup = 'oauth_popup';
    case LegacyCompatible = 'legacy_compatible';
}
```

---

# 306. Standard profile

Podrá usar:

* COOP moderado;
* CORP para recursos privados;
* sin COEP estricto;
* CSP fuerte.

---

# 307. Embedded profile

Deberá coordinar:

* frame ancestors;
* permissions delegation;
* CORS;
* postMessage;
* cookies;
* CORP.

---

# 308. CrossOriginIsolated profile

Requerirá:

* COOP compatible;
* COEP;
* recursos compatibles con CORP o CORS;
* workers compatibles;
* validación de assets.

---

# 309. OAuthPopup profile

Deberá preservar compatibilidad con el popup sin debilitar el resto de la aplicación.

---

# 310. LegacyCompatible profile

Podrá omitir mecanismos no soportados, pero mantendrá:

* CSP;
* MIME protection;
* frame protection;
* secure cookies;
* HTTPS.

---

# 311. CORS Security Model

CORS controla qué orígenes pueden leer respuestas desde navegadores.

No es un mecanismo general de autenticación.

---

# 312. CORS misconception

Permitir un origen no implica que ese origen sea confiable para todas las operaciones.

---

# 313. CORS trust boundary

```text
Browser Origin
    ↓
Origin Validation
    ↓
Route CORS Policy
    ↓
Credential Policy
    ↓
Preflight Validation
    ↓
Response Header Emission
```

---

# 314. CorsPolicy

```php
final readonly class CorsPolicy
{
    public function __construct(
        public OriginMatcherSet $allowedOrigins,
        public array $allowedMethods,
        public array $allowedHeaders,
        public array $exposedHeaders,
        public bool $allowCredentials,
        public int $maxAge,
        public bool $allowPrivateNetwork,
    ) {
    }
}
```

---

# 315. CORS policy resolution

Las políticas podrán definirse por:

* application;
* route group;
* route;
* API version;
* tenant;
* environment.

---

# 316. Default CORS policy

Por defecto:

```text
No cross-origin access
```

---

# 317. Origin header trust

`Origin` es input no confiable.

Deberá:

* parsearse estrictamente;
* normalizarse;
* validar scheme;
* validar host;
* validar port;
* impedir valores ambiguos.

---

# 318. Origin structure

Un origin válido estará formado por:

```text
scheme + host + port
```

No incluye path, query ni fragment.

---

# 319. Null origin

El valor:

```text
Origin: null
```

puede aparecer desde:

* sandboxed iframes;
* local files;
* data URLs;
* contexts opacos.

Deberá rechazarse por defecto.

---

# 320. Exact origin matching

La estrategia preferida será coincidencia exacta.

---

# 321. Origin allowlist

Ejemplo:

```php
[
    'https://app.example.com',
    'https://admin.example.com',
]
```

---

# 322. Wildcard origins

El uso de:

```text
*
```

solo deberá permitirse para recursos públicos sin credenciales.

---

# 323. Wildcard with credentials

No deberá combinarse wildcard origin con credenciales.

---

# 324. Reflected origins

No deberá reflejarse cualquier `Origin` recibido.

Solo podrá reflejarse después de validarlo contra la política.

---

# 325. Regex origin matchers

Deberán evitarse cuando una lista exacta sea suficiente.

---

# 326. Safe subdomain matcher

Cuando sea necesario permitir subdominios, deberá utilizarse un matcher estructurado.

```php
final readonly class SubdomainOriginMatcher
{
    public function __construct(
        public string $scheme,
        public string $registrableDomain,
        public ?PortPolicy $portPolicy,
    ) {
    }
}
```

---

# 327. Suffix matching risk

No deberá validarse mediante:

```text
endsWith("example.com")
```

porque permitiría dominios como:

```text
evil-example.com
```

---

# 328. Internationalized domains

Los hosts deberán normalizarse de forma segura antes de comparar.

---

# 329. Port handling

El puerto forma parte del origin.

```text
https://example.com
```

y:

```text
https://example.com:8443
```

son orígenes distintos.

---

# 330. Dynamic tenant origins

En sistemas multi-tenant, los origins podrán resolverse desde configuración confiable del tenant.

No desde parámetros arbitrarios de la petición.

---

# 331. Tenant origin registry

```php
interface TenantOriginRegistryInterface
{
    public function allowedOrigins(string $tenantId): OriginMatcherSet;
}
```

---

# 332. Tenant context validation

El tenant utilizado para resolver CORS deberá provenir del routing y tenant resolution confiable.

---

# 333. CORS and authentication

CORS no sustituye:

* authentication;
* authorization;
* CSRF protection;
* tenant validation.

---

# 334. Credentialed CORS

Cuando `allowCredentials` sea verdadero:

* el origin deberá ser explícito;
* no podrá usarse wildcard;
* cookies deberán cumplir SameSite y Secure;
* CSRF deberá evaluarse;
* cache deberá incluir `Vary: Origin`.

---

# 335. Access-Control-Allow-Credentials

Solo deberá emitirse cuando la ruta realmente admita credenciales cross-origin.

---

# 336. Credential minimization

Una API debería preferir tokens explícitos antes que cookies cross-site cuando la arquitectura lo permita.

---

# 337. Allowed methods

Los métodos deberán declararse explícitamente.

Ejemplo:

```php
['GET', 'POST', 'PUT', 'DELETE']
```

---

# 338. Method normalization

Los métodos deberán compararse mediante representación canónica.

---

# 339. Unsafe methods

Operaciones mutables requerirán controles adicionales.

---

# 340. Allowed request headers

Solo deberán permitirse headers necesarios.

---

# 341. Arbitrary request headers

No deberá reflejarse ciegamente:

```text
Access-Control-Request-Headers
```

---

# 342. Header name validation

Cada header solicitado deberá:

* ser válido;
* estar normalizado;
* pertenecer a allowlist;
* no ser hop-by-hop;
* no ser reservado.

---

# 343. Exposed response headers

Los headers visibles al frontend cross-origin deberán limitarse.

---

# 344. Sensitive exposed headers

No deberán exponerse:

* internal trace IDs sensibles;
* server internals;
* authorization metadata;
* session identifiers;
* stack data.

---

# 345. CORS preflight

Las peticiones `OPTIONS` de preflight deberán resolverse antes de ejecutar lógica de negocio.

---

# 346. Preflight validator

```php
interface CorsPreflightValidatorInterface
{
    public function validate(
        CorsPreflightRequest $request,
        CorsPolicy $policy
    ): CorsPreflightResult;
}
```

---

# 347. Preflight inputs

Deberá validar:

* origin;
* requested method;
* requested headers;
* private network request;
* route existence;
* route CORS policy.

---

# 348. Preflight authentication

Normalmente el preflight no deberá requerir sesión de aplicación para responder, pero tampoco deberá revelar información sensible sobre rutas internas.

---

# 349. Route disclosure

VoltStack deberá evitar diferencias excesivas que permitan enumerar rutas protegidas mediante preflight.

---

# 350. Resultado de esta entrega

Esta entrega establece:

```text
Permissions Policy Enforcement
Browser Capability Declarations
Cross-Origin Isolation
COOP
COEP
CORP
Origin-Agent-Cluster
Browser Isolation Profiles
CORS Trust Model
Origin Validation
Credentialed CORS
Tenant-Aware CORS
Preflight Foundations
```

# CONTROLLER_SECURITY_MODEL_PART_04.md

## Transport & Response Security

**Entrega:** 5 de varias
**Cobertura:** Secciones **351–440**

---

# 351. Preflight Response Generation

Las respuestas a solicitudes **CORS Preflight** deberán generarse completamente antes de que el request alcance el pipeline de Controllers.

```text
HTTP Request
      │
      ▼
Origin Validation
      │
      ▼
Preflight Detection
      │
      ▼
Policy Resolution
      │
      ▼
Preflight Response
      │
      ▼
Terminate Request
```

---

# 352. OPTIONS Short-Circuit

Cuando una petición corresponda a un **Preflight válido**, el framework deberá finalizar inmediatamente el procesamiento.

No deberán ejecutarse:

* Controllers
* Middlewares de negocio
* Renderizadores
* ORM
* Sistema de eventos
* Hydration Runtime

---

# 353. Preflight Response Builder

```php
interface CorsPreflightResponseBuilderInterface
{
    public function build(
        CorsPolicy $policy,
        CorsPreflightRequest $request
    ): SecureHttpResponse;
}
```

---

# 354. Invalid Preflight

Las solicitudes inválidas deberán responder con un error controlado.

Nunca deberán revelar:

* existencia de rutas
* nombres de controladores
* métodos soportados internamente
* políticas de autorización

---

# 355. Missing Origin

Una petición OPTIONS sin Origin no deberá tratarse automáticamente como Preflight.

---

# 356. Missing Access-Control-Request-Method

La ausencia del encabezado correspondiente impedirá considerar la petición como Preflight.

---

# 357. Unsupported Requested Method

Si el método solicitado no pertenece a la política CORS:

```text
Origin ✔
Method ✘
```

la petición deberá rechazarse.

---

# 358. Unsupported Requested Headers

Los encabezados solicitados deberán validarse individualmente.

---

# 359. Canonical Header Comparison

La comparación utilizará nombres normalizados.

```text
Content-Type
content-type
CONTENT-TYPE
```

representan el mismo encabezado.

---

# 360. Access-Control-Max-Age

El tiempo de cache del Preflight deberá configurarse cuidadosamente.

No deberá exceder políticas corporativas.

---

# 361. Preflight Cache

Los navegadores pueden reutilizar la respuesta.

El framework deberá generar respuestas deterministas.

---

# 362. Vary Header

Cuando corresponda deberá emitirse:

```text
Vary: Origin
```

y cuando aplique:

```text
Vary:
Origin,
Access-Control-Request-Method,
Access-Control-Request-Headers
```

---

# 363. Cache Poisoning Prevention

Nunca deberá mezclarse una respuesta CORS entre distintos orígenes.

---

# 364. Response Reuse

Las respuestas cacheadas deberán depender de:

* Origin
* Método
* Encabezados solicitados
* Perfil de seguridad

---

# 365. Preflight Metrics

Se recomienda registrar:

* total
* permitidos
* rechazados
* origen
* tenant
* endpoint

---

# 366. Private Network Access

Los navegadores modernos introducen restricciones para recursos de redes privadas.

---

# 367. PNA Policy

```php
enum PrivateNetworkPolicy
{
    case Disabled;
    case AllowTrusted;
    case AllowAll;
}
```

---

# 368. Access-Control-Allow-Private-Network

Solo deberá emitirse cuando:

* exista una política explícita
* el origen sea confiable
* el endpoint realmente lo requiera

---

# 369. Internal APIs

Las APIs administrativas no deberán habilitar PNA por defecto.

---

# 370. Local Network Exposure

El framework deberá impedir exponer accidentalmente servicios internos mediante configuraciones CORS incorrectas.

---

# 371. CSRF Security Model

Cross Site Request Forgery sigue siendo una amenaza para aplicaciones basadas en cookies.

---

# 372. Threat Model

```text
Victim Browser
        │
Authenticated Session
        │
Attacker Site
        │
Cross-Origin Request
        │
Protected Endpoint
```

---

# 373. CSRF Protection Scope

La protección deberá aplicarse sobre:

* formularios
* SPA
* AJAX
* Fetch API
* Component Runtime
* Volt Protocol
* Uploads

---

# 374. Safe Methods

Por defecto:

```text
GET
HEAD
OPTIONS
TRACE
```

no deberán modificar estado.

---

# 375. Unsafe Methods

Se consideran:

* POST
* PUT
* PATCH
* DELETE

y cualquier método personalizado que modifique recursos.

---

# 376. CsrfProtectionMode

```php
enum CsrfProtectionMode
{
    case Disabled;
    case Token;
    case DoubleSubmit;
    case Custom;
}
```

---

# 377. Default Strategy

VoltStack utilizará tokens sincronizados como estrategia principal.

---

# 378. CsrfToken

```php
final readonly class CsrfToken
{
    public function __construct(
        public string $value,
        public DateTimeImmutable $issuedAt,
        public string $sessionId,
    ) {}
}
```

---

# 379. Token Entropy

Los tokens deberán generarse mediante un RNG criptográficamente seguro.

---

# 380. Token Length

Se recomienda una longitud mínima equivalente a 256 bits de entropía.

---

# 381. Token Rotation

Podrá configurarse:

* nunca
* por sesión
* por autenticación
* por request crítico

---

# 382. Token Lifetime

Los tokens expirados deberán rechazarse.

---

# 383. Session Binding

El token deberá estar asociado a la sesión correspondiente.

---

# 384. User Binding

Opcionalmente podrá asociarse también al usuario autenticado.

---

# 385. Double Submit Cookies

VoltStack soportará esta estrategia para escenarios específicos.

---

# 386. Double Submit Validation

```text
Cookie
      │
      ▼
Request Header
      │
      ▼
Constant Time Compare
```

---

# 387. Constant-Time Comparison

Las comparaciones de tokens deberán evitar ataques por timing.

---

# 388. Missing Token

La ausencia del token deberá producir rechazo inmediato.

---

# 389. Invalid Token

Los tokens inválidos no deberán indicar cuál parte falló.

---

# 390. Replay Protection

Podrá habilitarse protección contra reutilización de tokens en operaciones críticas.

---

# 391. SPA CSRF

El runtime SPA deberá transportar automáticamente el token.

---

# 392. Volt Protocol CSRF

El protocolo Volt incorporará el token dentro del contexto de hidratación.

Nunca en URLs.

---

# 393. Hydration Requests

Todas las solicitudes de hidratación mutables deberán validar CSRF.

---

# 394. AJAX Protection

Las solicitudes AJAX deberán incluir:

```text
X-CSRF-TOKEN
```

o el encabezado configurado.

---

# 395. API Tokens

Las APIs autenticadas mediante Bearer Tokens podrán deshabilitar CSRF cuando no utilicen cookies.

---

# 396. Stateless APIs

Las APIs verdaderamente stateless no requerirán protección CSRF.

---

# 397. Mixed Authentication

Aplicaciones híbridas deberán evaluar individualmente cada endpoint.

---

# 398. Cookie Security

Las cookies representan uno de los activos más sensibles del transporte HTTP.

---

# 399. SecureCookie

```php
final readonly class SecureCookie
{
    public function __construct(
        public string $name,
        public string $value,
        public CookieSecurityPolicy $policy,
    ) {}
}
```

---

# 400. Cookie Validation

Toda cookie deberá validarse antes de emitirse.

---

# 401. Cookie Name Rules

Los nombres deberán cumplir RFC y evitar caracteres ambiguos.

---

# 402. Cookie Prefixes

Se soportarán:

```text
__Host-
__Secure-
```

---

# 403. __Host-

Una cookie con este prefijo deberá cumplir:

* Secure
* Path=/
* Sin Domain

---

# 404. __Secure-

Requerirá:

```text
Secure=true
```

---

# 405. Secure Attribute

Las cookies sensibles deberán marcarse siempre como Secure.

---

# 406. HttpOnly

Las cookies de sesión deberán utilizar HttpOnly.

---

# 407. SameSite

```php
enum SameSitePolicy
{
    case Strict;
    case Lax;
    case None;
}
```

---

# 408. Default SameSite

Se recomienda:

```text
Lax
```

para la mayoría de aplicaciones.

---

# 409. Strict Mode

Ideal para operaciones altamente sensibles.

---

# 410. None Mode

Solo deberá utilizarse junto con:

```text
Secure
```

---

# 411. Cookie Path

El Path deberá limitar el alcance cuando sea posible.

---

# 412. Cookie Domain

El Domain deberá minimizarse.

---

# 413. Cookie Lifetime

Se distinguirán:

* sesión
* persistentes
* temporales

---

# 414. Session Cookie

Las cookies de sesión deberán expirar al finalizar la sesión del navegador cuando así se configure.

---

# 415. Session Fixation

VoltStack deberá regenerar el identificador de sesión tras autenticación.

---

# 416. Session Rotation

También podrá regenerarse después de:

* elevación de privilegios
* cambio de tenant
* MFA
* recuperación de contraseña

---

# 417. Session Identifier

Nunca deberá exponerse en:

* URLs
* HTML
* logs

---

# 418. Cookie Encryption

Las cookies podrán cifrarse mediante el sistema criptográfico del framework.

---

# 419. Cookie Signing

Adicionalmente podrán firmarse.

---

# 420. Tampering Detection

Una cookie modificada deberá invalidarse completamente.

---

# 421. Cookie Serialization

El serializador deberá ser determinista.

---

# 422. Oversized Cookies

Se impondrán límites para evitar problemas de interoperabilidad.

---

# 423. Cookie Limits

Se controlarán:

* número
* tamaño
* longitud del nombre
* longitud del valor

---

# 424. Third-Party Cookies

Su utilización deberá minimizarse.

---

# 425. Browser Compatibility

Las políticas deberán adaptarse a diferencias conocidas entre navegadores.

---

# 426. Cookie Audit

Se registrarán:

* creación
* modificación
* eliminación
* expiración

---

# 427. SecureCookieBag

```php
interface SecureCookieBagInterface
{
    public function add(SecureCookie $cookie): void;

    public function freeze(): void;
}
```

---

# 428. Cookie Freeze

Después del freeze ninguna cookie podrá modificarse.

---

# 429. Immutable Response Cookies

Las cookies formarán parte de la respuesta inmutable.

---

# 430. Cookie Validation Pipeline

```text
Create
    ↓
Validate
    ↓
Encrypt
    ↓
Sign
    ↓
Freeze
    ↓
Emit
```

---

# 431. Session Security Context

El contexto de transporte deberá conocer:

* Session ID
* User ID
* Tenant
* Authentication State

---

# 432. Tenant Isolation

Las cookies de un tenant no deberán interferir con otro.

---

# 433. Multi-Domain Deployments

Se soportarán políticas independientes por dominio.

---

# 434. Logout

El cierre de sesión deberá invalidar:

* sesión
* cookies
* tokens CSRF
* identificadores derivados

---

# 435. Forced Logout

El sistema podrá invalidar sesiones comprometidas globalmente.

---

# 436. Cookie Metrics

Se recopilarán métricas sobre:

* emisión
* rechazo
* expiración
* rotación

---

# 437. Security Events

Eventos relevantes:

* InvalidCsrfToken
* CookieTampered
* SessionRotated
* SessionFixationPrevented

---

# 438. Testing Strategy

Se incluirán pruebas para:

* CSRF
* Cookies
* SameSite
* Secure
* HttpOnly
* Rotation

---

# 439. ADR

**ADR-093**

> Todas las cookies emitidas por VoltStack pasarán por un pipeline criptográfico y de validación antes de ser enviadas al cliente.

---

# 440. Resultado de esta entrega

Esta entrega introduce:

```text
✓ Preflight Response System
✓ Private Network Access
✓ Complete CSRF Architecture
✓ SPA CSRF
✓ Volt Protocol CSRF
✓ Cookie Security Model
✓ SecureCookie Pipeline
✓ Session Protection
✓ Cookie Encryption
✓ Cookie Signing
✓ Multi-Tenant Cookie Isolation
```

# CONTROLLER_SECURITY_MODEL_PART_04.md

## Transport & Response Security

**Entrega:** 6 de varias
**Cobertura:** Secciones **441–540**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_04.md — Entrega 5`

---

# 441. Redirect Security Model

Las redirecciones representan una transición de confianza entre:

* la aplicación;
* el navegador;
* un destino interno;
* un origen externo;
* un flujo de autenticación;
* un proveedor externo.

Una redirección incorrectamente validada puede provocar:

* open redirect;
* phishing;
* robo de tokens;
* filtración de parámetros;
* downgrade a HTTP;
* evasión de controles de navegación;
* manipulación de callbacks.

---

# 442. Redirect pipeline

```text
Redirect Request
      ↓
Target Resolution
      ↓
URL Parsing
      ↓
Origin Validation
      ↓
Scheme Validation
      ↓
Policy Resolution
      ↓
Status Resolution
      ↓
Location Header Construction
      ↓
Final Validation
      ↓
Emission
```

---

# 443. RedirectResponse

```php
final class RedirectResponse extends SecureHttpResponse
{
    public function __construct(
        RedirectTarget $target,
        RedirectStatus $status,
        RedirectSecurityMetadata $security,
    ) {
    }
}
```

---

# 444. RedirectTarget

```php
final readonly class RedirectTarget
{
    public function __construct(
        public UriInterface $uri,
        public RedirectTargetType $type,
    ) {
    }
}
```

---

# 445. RedirectTargetType

```php
enum RedirectTargetType: string
{
    case InternalRoute = 'internal_route';
    case InternalPath = 'internal_path';
    case SameOriginUrl = 'same_origin_url';
    case TrustedExternalUrl = 'trusted_external_url';
    case SignedCallback = 'signed_callback';
}
```

---

# 446. Internal routes

Las redirecciones internas deberán generarse preferentemente mediante nombres de ruta.

```php
return Response::redirectToRoute(
    name: 'dashboard',
    parameters: ['tenant' => $tenant->id]
);
```

---

# 447. Route-based redirects

El URL Generator deberá:

* validar parámetros;
* aplicar encoding;
* usar host confiable;
* resolver HTTPS;
* respetar tenant;
* evitar path traversal.

---

# 448. Raw redirect URLs

Las URLs crudas deberán considerarse no confiables hasta validación.

---

# 449. Open redirect

Nunca deberá utilizarse directamente un valor como:

```text
?next=https://evil.example
```

para construir el header `Location`.

---

# 450. Safe return URL

Los parámetros de retorno deberán transformarse en un objeto validado.

```php
final readonly class SafeReturnUrl
{
    private function __construct(
        public string $path,
        public array $query,
    ) {
    }
}
```

---

# 451. Relative redirects

Las redirecciones relativas internas serán preferibles cuando no se necesite una URL absoluta.

---

# 452. Scheme validation

Solo deberán permitirse schemes explícitamente soportados.

Por defecto:

```text
https
http en desarrollo controlado
```

---

# 453. Dangerous redirect schemes

Deberán rechazarse:

```text
javascript:
data:
file:
vbscript:
blob:
```

salvo capacidades internas extraordinarias y aisladas.

---

# 454. Protocol-relative URLs

Las URLs como:

```text
//example.com/path
```

deberán rechazarse o normalizarse explícitamente.

No deberán heredarse silenciosamente del scheme actual.

---

# 455. Backslash normalization

Los parsers deberán controlar diferencias entre:

```text
/
\
```

para evitar interpretaciones divergentes entre servidor y navegador.

---

# 456. Encoded separators

Deberán validarse secuencias como:

```text
%2f
%5c
%2e
```

cuando puedan cambiar la interpretación del destino.

---

# 457. User information in URLs

No deberán permitirse destinos con credenciales embebidas.

Ejemplo prohibido:

```text
https://trusted.example@evil.example
```

---

# 458. Host normalization

El host deberá:

* convertirse a forma canónica;
* validar IDN;
* eliminar ambigüedades;
* comparar puerto;
* comparar scheme;
* rechazar caracteres inválidos.

---

# 459. Same-origin redirect

Una URL solo será same-origin si coinciden:

```text
scheme
host
port
```

---

# 460. Same-site redirect

Same-site no deberá confundirse con same-origin.

Podrá involucrar subdominios distintos y riesgos diferentes.

---

# 461. Trusted external redirects

Las redirecciones externas deberán requerir una política explícita.

---

# 462. Redirect allowlist

```php
interface TrustedRedirectOriginRegistryInterface
{
    public function contains(
        Origin $origin,
        RedirectSecurityContext $context
    ): bool;
}
```

---

# 463. Redirect purpose

Una entrada de allowlist deberá indicar su propósito.

Ejemplos:

* OAuth;
* pagos;
* documentación;
* soporte;
* portal corporativo.

---

# 464. Redirect capability

```php
enum RedirectCapability: string
{
    case Internal = 'internal';
    case ExternalTrusted = 'external_trusted';
    case AuthenticationCallback = 'authentication_callback';
    case PaymentProvider = 'payment_provider';
}
```

---

# 465. Controller redirect permissions

Los Controllers no deberán emitir redirecciones externas salvo que posean la capability correspondiente.

---

# 466. Signed redirect state

Flujos externos deberán utilizar state firmado cuando sea necesario.

---

# 467. OAuth state

Los callbacks OAuth deberán validar:

* state;
* sesión;
* provider;
* redirect URI;
* expiración;
* nonce cuando aplique.

---

# 468. Redirect parameter leakage

No deberán enviarse secretos en query strings de redirecciones.

---

# 469. Fragment handling

Los fragments no se envían al servidor, pero pueden contener información visible al frontend.

Deberán utilizarse con precaución.

---

# 470. Redirect status codes

```php
enum RedirectStatus: int
{
    case MovedPermanently = 301;
    case Found = 302;
    case SeeOther = 303;
    case TemporaryRedirect = 307;
    case PermanentRedirect = 308;
}
```

---

# 471. Method preservation

`307` y `308` preservan el método y body.

No deberán utilizarse inadvertidamente después de operaciones sensibles.

---

# 472. Post-Redirect-Get

Después de formularios mutables se recomienda:

```text
POST
  ↓
303 See Other
  ↓
GET
```

---

# 473. Permanent redirect safety

Los redirects permanentes pueden ser cacheados por clientes e intermediarios.

Deberán utilizarse solo cuando el cambio sea estable.

---

# 474. Redirect loop detection

El framework podrá detectar:

* destino igual a origen;
* ciclos conocidos;
* exceso de saltos internos;
* normalizaciones equivalentes.

---

# 475. Redirect audit

Las redirecciones externas deberán poder auditarse con:

* origen;
* destino;
* route;
* user;
* tenant;
* purpose;
* execution ID.

---

# 476. Redirect response body

El body opcional de una redirección no deberá reflejar sin escape la URL destino.

---

# 477. Location header security

`Location` deberá generarse exclusivamente mediante un serializador de URI seguro.

---

# 478. File Response Security

Las respuestas de archivo pueden exponer:

* archivos arbitrarios;
* rutas internas;
* secretos;
* contenido activo;
* metadata;
* enlaces simbólicos;
* archivos temporales.

---

# 479. File response model

```php
interface FileResponseInterface
{
    public function source(): SecureFileSource;

    public function disposition(): ContentDisposition;

    public function mediaType(): MediaType;
}
```

---

# 480. SecureFileSource

```php
interface SecureFileSource
{
    public function identifier(): string;

    public function size(): ?int;

    public function open(): ReadableStreamInterface;

    public function securityMetadata(): FileSecurityMetadata;
}
```

---

# 481. File source types

```php
enum FileSourceType: string
{
    case LocalStorage = 'local_storage';
    case ObjectStorage = 'object_storage';
    case Generated = 'generated';
    case Temporary = 'temporary';
    case RemoteTrusted = 'remote_trusted';
}
```

---

# 482. Raw filesystem paths

Los Controllers no deberán devolver rutas arbitrarias directamente.

---

# 483. Storage abstraction

El framework deberá resolver archivos mediante:

* disk;
* object identifier;
* tenant scope;
* access policy;
* storage adapter.

---

# 484. Path traversal

Deberán bloquearse secuencias como:

```text
../
..\
%2e%2e
```

y sus variantes normalizadas.

---

# 485. Canonical path validation

La ruta final deberá resolverse y comprobarse dentro de una raíz autorizada.

---

# 486. Symlink policy

Los enlaces simbólicos deberán:

* estar prohibidos;
* o resolverse y revalidarse;
* o limitarse a roots confiables.

---

# 487. Time-of-check to time-of-use

La validación y apertura del archivo deberán minimizar condiciones TOCTOU.

---

# 488. File descriptors

Cuando sea posible, el sistema deberá validar y servir el mismo descriptor abierto.

---

# 489. Tenant-scoped files

Todo archivo multi-tenant deberá validar:

```text
Requested tenant
    =
Authenticated tenant context
    =
File ownership tenant
```

---

# 490. Authorization continuity

La autorización deberá mantenerse desde la resolución hasta la apertura del stream.

---

# 491. Temporary files

Los archivos temporales deberán:

* tener permisos mínimos;
* nombres no predecibles;
* cleanup definido;
* storage aislado;
* lifetime limitado.

---

# 492. Remote files

No deberá convertirse una URL arbitraria en descarga proxy.

---

# 493. Server-side request forgery

Los archivos remotos deberán pasar por controles SSRF:

* schemes permitidos;
* DNS validation;
* IP range policy;
* redirect limits;
* timeout;
* size limits.

---

# 494. MIME validation for files

El tipo MIME deberá resolverse según una política confiable.

---

# 495. Dangerous inline file types

Deberán tratarse con especial cuidado:

* HTML;
* SVG;
* XML;
* JavaScript;
* PDFs con contenido activo;
* documentos ofimáticos;
* archivos multimedia complejos.

---

# 496. Inline vs attachment

```php
enum ContentDispositionType: string
{
    case Inline = 'inline';
    case Attachment = 'attachment';
}
```

---

# 497. Default disposition

Los uploads de usuario deberán servirse como attachment por defecto, especialmente cuando sean contenido activo.

---

# 498. Safe inline rendering

Solo deberá permitirse inline cuando:

* el MIME sea aprobado;
* el contenido sea confiable;
* la ruta esté autorizada;
* CSP y sandbox sean compatibles.

---

# 499. Content-Disposition builder

```php
interface ContentDispositionBuilderInterface
{
    public function build(
        ContentDispositionType $type,
        SafeDownloadFilename $filename
    ): HeaderValue;
}
```

---

# 500. Download filename security

Los nombres deberán limpiarse para impedir:

* CRLF injection;
* path separators;
* null bytes;
* nombres reservados;
* extensiones engañosas;
* caracteres de control.

---

# 501. SafeDownloadFilename

```php
final readonly class SafeDownloadFilename
{
    public function __construct(
        public string $asciiFallback,
        public ?string $utf8Name,
        public string $extension,
    ) {
    }
}
```

---

# 502. Filename normalization

Se deberá aplicar normalización Unicode consistente.

---

# 503. Double extensions

Nombres como:

```text
invoice.pdf.exe
```

deberán tratarse como sospechosos.

---

# 504. Extension-MIME coherence

La extensión presentada deberá ser coherente con el MIME emitido.

---

# 505. International filenames

Se deberá soportar correctamente `filename*` con encoding seguro.

---

# 506. Header injection in filenames

Todo salto de línea deberá provocar rechazo.

---

# 507. File size limits

Se deberán definir límites por:

* ruta;
* tenant;
* user;
* tipo;
* storage profile.

---

# 508. Large file handling

Los archivos grandes deberán transmitirse mediante streaming controlado.

---

# 509. Memory safety

No deberán cargarse archivos grandes completamente en memoria.

---

# 510. Download authorization token

Las descargas temporales podrán utilizar URLs firmadas.

---

# 511. Signed download URL

```php
final readonly class SignedDownloadGrant
{
    public function __construct(
        public string $resourceId,
        public DateTimeImmutable $expiresAt,
        public string $signature,
        public ?string $userId,
        public ?string $tenantId,
    ) {
    }
}
```

---

# 512. Signed URL scope

La firma deberá incluir:

* recurso;
* expiración;
* tenant;
* usuario cuando aplique;
* disposition;
* allowed range policy.

---

# 513. Signed URL replay

Podrá permitirse reutilización limitada o uso único según sensibilidad.

---

# 514. Download audit events

Se deberán registrar:

* resource ID;
* user;
* tenant;
* bytes enviados;
* completion status;
* disconnect;
* range usage.

---

# 515. Streaming Response Security

Streaming modifica el modelo normal porque la respuesta comienza antes de completarse.

---

# 516. Streaming pipeline

```text
Authorize
   ↓
Resolve Stream
   ↓
Validate Headers
   ↓
Freeze Response Metadata
   ↓
Start Emission
   ↓
Emit Chunks
   ↓
Monitor Limits
   ↓
Close or Abort
```

---

# 517. Header freeze before stream

Una vez enviado el primer byte:

* no podrán cambiarse headers;
* no podrán añadirse cookies;
* no podrá cambiarse status;
* no podrá cambiarse content type.

---

# 518. StreamResponse

```php
final class StreamResponse extends SecureHttpResponse
{
    public function __construct(
        private ReadableStreamInterface $stream,
        private StreamSecurityPolicy $policy,
        private StreamLifecycle $lifecycle,
    ) {
    }
}
```

---

# 519. StreamSecurityPolicy

```php
final readonly class StreamSecurityPolicy
{
    public function __construct(
        public int $maxBytes,
        public int $maxDurationSeconds,
        public int $idleTimeoutSeconds,
        public bool $allowCompression,
        public bool $allowRanges,
        public bool $auditChunks,
    ) {
    }
}
```

---

# 520. Bounded streams

Todo stream deberá tener límites o una justificación explícita de operación prolongada.

---

# 521. Stream cancellation

El sistema deberá detectar desconexión del cliente y cancelar trabajo innecesario.

---

# 522. Worker protection

Una conexión lenta no deberá monopolizar indefinidamente un worker.

---

# 523. Backpressure

El adapter de transporte deberá respetar backpressure cuando sea posible.

---

# 524. Slow client attack

Se deberán implementar:

* timeouts;
* límites de buffers;
* límites de duración;
* concurrency limits;
* cancelación.

---

# 525. Output chunk validation

Los chunks deberán ser compatibles con el tipo de contenido.

---

# 526. Text stream encoding

Los streams textuales deberán preservar límites válidos de encoding.

---

# 527. JSON streaming

No deberá emitirse una secuencia JSON inválida.

Podrán usarse formatos explícitos como:

* JSON Lines;
* NDJSON;
* arrays incrementales controlados.

---

# 528. NDJSON response

```text
Content-Type: application/x-ndjson
```

Cada línea deberá codificarse de forma independiente.

---

# 529. Stream exceptions

Una excepción después de iniciar el body no podrá transformarse en una respuesta de error convencional.

---

# 530. Post-start failures

El sistema deberá:

* cerrar el stream;
* registrar el error;
* marcar la respuesta incompleta;
* evitar emitir stack traces;
* notificar métricas.

---

# 531. Stream completion state

```php
enum StreamCompletionState: string
{
    case Completed = 'completed';
    case ClientDisconnected = 'client_disconnected';
    case TimedOut = 'timed_out';
    case LimitExceeded = 'limit_exceeded';
    case Failed = 'failed';
}
```

---

# 532. Output buffering

El buffering deberá ser explícito y limitado.

---

# 533. Nested output buffers

Los buffers PHP preexistentes deberán inspeccionarse antes del streaming.

---

# 534. Accidental output

Whitespace, warnings o debug output antes de los headers deberán considerarse errores de transporte.

---

# 535. OutputCaptureGuard

```php
interface OutputCaptureGuardInterface
{
    public function begin(): OutputCaptureSession;

    public function assertClean(
        OutputCaptureSession $session
    ): void;
}
```

---

# 536. Warning leakage

Warnings, notices y deprecations no deberán mezclarse con el response body en producción.

---

# 537. Binary stream integrity

Los bytes no deberán alterarse mediante:

* encoding textual;
* output transformations;
* debug toolbars;
* template middleware.

---

# 538. Chunk audit policy

No deberán registrarse cuerpos sensibles completos.

El audit deberá limitarse a:

* conteo;
* tamaño;
* timing;
* hash opcional;
* estado.

---

# 539. Stream rate limiting

Podrán aplicarse límites por:

* usuario;
* IP;
* tenant;
* ruta;
* tipo de recurso;
* concurrencia.

---

# 540. Resultado de esta entrega

Esta entrega establece:

```text
Redirect Security Model
Open Redirect Prevention
Trusted External Redirects
OAuth Redirect Integrity
Redirect Status Semantics
File Response Security
Path Traversal Protection
Symlink and TOCTOU Controls
Tenant-Scoped Downloads
Content-Disposition Security
Safe Download Filenames
Signed Download Grants
Streaming Response Security
Backpressure
Slow Client Protection
Output Buffer Integrity
Stream Lifecycle Auditing
```

# CONTROLLER_SECURITY_MODEL_PART_04.md

## Transport & Response Security

**Entrega:** 7 de varias
**Cobertura:** Secciones **541–640**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_04.md — Entrega 6`

---

# 541. Server-Sent Events Security

Server-Sent Events permiten mantener una conexión HTTP abierta para enviar eventos unidireccionales desde el servidor al cliente.

Este modelo introduce riesgos particulares:

* conexiones prolongadas;
* autenticación expirada;
* fuga de eventos entre tenants;
* reconexiones no autorizadas;
* agotamiento de workers;
* eventos mal codificados;
* abuso del campo `Last-Event-ID`;
* buffering de proxies;
* exposición involuntaria de datos sensibles.

---

# 542. SSE transport profile

Las respuestas SSE deberán usar un perfil específico.

```php
enum EventStreamProfile: string
{
    case PublicFeed = 'public_feed';
    case AuthenticatedFeed = 'authenticated_feed';
    case TenantFeed = 'tenant_feed';
    case RestrictedFeed = 'restricted_feed';
}
```

---

# 543. SSE response requirements

Una respuesta SSE deberá emitir como mínimo:

```text
Content-Type: text/event-stream
Cache-Control: no-cache
Connection: keep-alive
```

La responsabilidad final de `Connection` dependerá del servidor y del adapter de transporte.

---

# 544. SSE content type

El content type deberá ser exactamente:

```text
text/event-stream
```

con charset compatible cuando aplique.

---

# 545. SSE cache policy

Los streams SSE autenticados no deberán almacenarse en caches compartidas.

---

# 546. SSE response builder

```php
interface EventStreamResponseBuilderInterface
{
    public function build(
        EventStreamSourceInterface $source,
        EventStreamSecurityPolicy $policy,
        ResponseSecurityContext $context
    ): EventStreamResponse;
}
```

---

# 547. EventStreamResponse

```php
final class EventStreamResponse extends SecureHttpResponse
{
    public function __construct(
        private EventStreamSourceInterface $source,
        private EventStreamSecurityPolicy $policy,
        private EventStreamLifecycle $lifecycle,
    ) {
    }
}
```

---

# 548. EventStreamSecurityPolicy

```php
final readonly class EventStreamSecurityPolicy
{
    public function __construct(
        public int $maxDurationSeconds,
        public int $idleTimeoutSeconds,
        public int $heartbeatIntervalSeconds,
        public int $maxEvents,
        public int $maxEventBytes,
        public bool $allowReconnect,
        public bool $requireReauthorization,
    ) {
    }
}
```

---

# 549. Event source trust

Un event source deberá considerarse semiconfiable.

Sus eventos deberán pasar por:

* validación;
* autorización;
* encoding;
* tamaño máximo;
* tenant scope;
* rate limiting.

---

# 550. SSE event model

```php
final readonly class ServerSentEvent
{
    public function __construct(
        public ?string $id,
        public ?string $event,
        public string $data,
        public ?int $retryMilliseconds = null,
    ) {
    }
}
```

---

# 551. SSE field validation

Los campos permitidos serán:

* `id`;
* `event`;
* `data`;
* `retry`.

No deberán emitirse campos arbitrarios.

---

# 552. Event name validation

El nombre del evento deberá:

* tener longitud limitada;
* excluir caracteres de control;
* pertenecer a un registry cuando se trate de eventos internos;
* no derivarse directamente de input del usuario.

---

# 553. Event ID validation

El ID deberá ser:

* opaco;
* limitado;
* sin saltos de línea;
* no sensible;
* compatible con reconexión.

---

# 554. Event data encoding

Cada línea del payload deberá emitirse como una línea `data:` independiente.

---

# 555. Newline normalization

El encoder deberá normalizar:

```text
CR
LF
CRLF
```

para impedir que un payload introduzca campos SSE adicionales.

---

# 556. SSE injection

Un valor como:

```text
data: safe
event: privileged
```

no deberá poder inyectarse como estructura de protocolo desde el contenido de usuario.

---

# 557. SseEventEncoder

```php
interface SseEventEncoderInterface
{
    public function encode(
        ServerSentEvent $event
    ): EncodedEventStreamChunk;
}
```

---

# 558. Event payload format

Aunque SSE transmite texto, el contenido podrá usar JSON seguro.

---

# 559. JSON event data

```php
$event = new ServerSentEvent(
    id: $cursor,
    event: 'invoice.updated',
    data: $jsonEncoder->encode($payload),
);
```

---

# 560. Sensitive data filtering

Antes de codificar un evento deberán aplicarse:

* transformers;
* field policies;
* tenant filters;
* authorization filters;
* redaction.

---

# 561. Event-level authorization

No será suficiente autorizar solo al abrir la conexión.

Cada evento podrá requerir validación adicional.

---

# 562. Long-lived authorization

Los permisos pueden cambiar mientras la conexión permanece abierta.

---

# 563. Reauthorization interval

```php
final readonly class ReauthorizationPolicy
{
    public function __construct(
        public int $intervalSeconds,
        public bool $reauthorizeOnEvent,
        public bool $closeOnFailure,
    ) {
    }
}
```

---

# 564. Session expiration during SSE

Cuando expire la sesión, el stream deberá:

* cerrar la conexión;
* emitir un evento controlado no sensible;
* o exigir reconexión autenticada.

---

# 565. User revocation

Una revocación de usuario o sesión deberá poder finalizar streams activos.

---

# 566. Tenant context persistence

El tenant context deberá fijarse al abrir la conexión.

No podrá cambiarse mediante mensajes del cliente.

---

# 567. Cross-tenant event leakage

Todo evento deberá comprobar que pertenece al tenant vinculado al stream.

---

# 568. SSE connection registry

```php
interface EventStreamConnectionRegistryInterface
{
    public function register(
        EventStreamConnection $connection
    ): void;

    public function revokeBySession(string $sessionId): void;

    public function revokeByUser(string $userId): void;

    public function revokeByTenant(string $tenantId): void;
}
```

---

# 569. Heartbeats

Los heartbeats permiten:

* detectar desconexiones;
* mantener infraestructura activa;
* evitar timeouts intermediarios.

---

# 570. Heartbeat data

El heartbeat no deberá incluir información sensible.

Podrá emitirse como comentario:

```text
: heartbeat
```

---

# 571. Heartbeat limits

Una frecuencia demasiado alta podrá causar carga innecesaria.

---

# 572. Reconnection security

El navegador puede reconectar automáticamente una conexión SSE.

---

# 573. Last-Event-ID

El cliente podrá enviar:

```text
Last-Event-ID
```

para continuar desde un cursor.

---

# 574. Last-Event-ID trust

Este valor será input no confiable.

---

# 575. Cursor validation

El cursor deberá:

* tener formato estricto;
* estar firmado cuando sea necesario;
* pertenecer al feed correcto;
* respetar tenant;
* no permitir acceso a eventos anteriores no autorizados.

---

# 576. Signed event cursor

```php
final readonly class SignedEventCursor
{
    public function __construct(
        public string $streamId,
        public string $position,
        public string $tenantId,
        public DateTimeImmutable $expiresAt,
        public string $signature,
    ) {
    }
}
```

---

# 577. Cursor replay

La reutilización podrá permitirse solo dentro de la ventana de retención definida.

---

# 578. Cursor enumeration

Los cursores no deberán ser secuenciales cuando eso permita inferir volumen o acceder a eventos ajenos.

---

# 579. Retry directive

El valor `retry` deberá limitarse a un rango permitido.

---

# 580. Reconnect storm protection

El sistema deberá mitigar reconexiones masivas mediante:

* retry mínimo;
* jitter en cliente;
* rate limits;
* circuit breakers;
* límites por usuario.

---

# 581. SSE connection limits

Se definirán límites por:

* IP;
* sesión;
* usuario;
* tenant;
* ruta;
* nodo.

---

# 582. Worker model

En FrankenPHP u otros runtimes persistentes, las conexiones SSE deberán diseñarse para no bloquear recursos desproporcionadamente.

---

# 583. SSE buffering

Algunos proxies pueden acumular eventos antes de enviarlos.

---

# 584. Proxy buffering control

El adapter podrá emitir headers específicos del proxy cuando estén autorizados.

No deberán emitirse headers dependientes de infraestructura desde Controllers.

---

# 585. SSE compression

La compresión puede incrementar latencia por buffering.

Deberá estar deshabilitada por defecto salvo validación específica.

---

# 586. SSE error handling

Una vez iniciado el stream, los errores deberán convertirse en:

* cierre controlado;
* evento de error genérico;
* métrica;
* audit event.

Nunca en stack trace.

---

# 587. SSE completion states

```php
enum EventStreamCompletionState: string
{
    case Completed = 'completed';
    case SessionExpired = 'session_expired';
    case AuthorizationRevoked = 'authorization_revoked';
    case ClientDisconnected = 'client_disconnected';
    case IdleTimeout = 'idle_timeout';
    case DurationExceeded = 'duration_exceeded';
    case EventLimitExceeded = 'event_limit_exceeded';
    case Failed = 'failed';
}
```

---

# 588. Range Request Security

Las solicitudes de rango permiten obtener partes de un recurso.

Se utilizan para:

* video;
* audio;
* archivos grandes;
* reanudación de descargas;
* PDF;
* almacenamiento de objetos.

---

# 589. Range header

El cliente podrá enviar:

```text
Range: bytes=0-1023
```

---

# 590. Range trust

`Range` será input no confiable.

---

# 591. RangeRequestParser

```php
interface RangeRequestParserInterface
{
    public function parse(
        HeaderValue $value,
        int $resourceSize
    ): ParsedRangeRequest;
}
```

---

# 592. Range units

VoltStack soportará inicialmente:

```text
bytes
```

Otros units deberán rechazarse salvo soporte explícito.

---

# 593. Single-range default

Por seguridad y simplicidad podrá permitirse únicamente un rango por request.

---

# 594. Multi-range requests

Las respuestas multipart requieren mayor complejidad.

Podrán deshabilitarse por defecto.

---

# 595. Range limits

Se deberán controlar:

* número de rangos;
* tamaño total;
* rango mínimo;
* rango máximo;
* solapamientos;
* orden.

---

# 596. Overlapping ranges

Los rangos solapados deberán rechazarse o normalizarse.

---

# 597. Range amplification

Un atacante podría solicitar múltiples rangos redundantes para aumentar la respuesta.

---

# 598. Unsatisfiable range

Un rango inválido deberá responder de forma controlada con semántica equivalente a:

```text
416 Range Not Satisfiable
```

---

# 599. Content-Range

El header deberá construirse exclusivamente desde valores validados.

---

# 600. Partial response status

Una respuesta parcial válida utilizará:

```text
206 Partial Content
```

---

# 601. Accept-Ranges

Solo deberá emitirse cuando el recurso y la política permitan rangos.

---

# 602. Restricted resources

Los recursos restricted podrán deshabilitar rangos para simplificar:

* auditoría;
* autorización;
* integridad;
* uso único.

---

# 603. Range authorization continuity

Cada rango deberá mantener la misma autorización del recurso completo.

---

# 604. Signed URL and range binding

Una URL firmada podrá limitar:

* rangos permitidos;
* tamaño máximo;
* número de requests;
* expiración.

---

# 605. Range cache interaction

Las respuestas parciales deberán tener políticas coherentes de cache y validators.

---

# 606. If-Range

El cliente podrá enviar `If-Range` para condicionar el rango a un validator.

---

# 607. If-Range validation

Solo deberá procesarse con:

* ETag válido;
* fecha válida;
* recurso compatible.

---

# 608. Stale range protection

Si el validator no coincide, deberá enviarse la representación completa según política.

---

# 609. Compression Security

La compresión reduce tamaño de respuestas, pero modifica:

* content length;
* cache variants;
* side channels;
* streaming;
* CPU usage.

---

# 610. CompressionPolicy

```php
final readonly class CompressionPolicy
{
    public function __construct(
        public CompressionMode $mode,
        public int $minimumBytes,
        public int $maximumBytes,
        public array $allowedAlgorithms,
        public bool $allowSensitiveResponses,
    ) {
    }
}
```

---

# 611. CompressionMode

```php
enum CompressionMode: string
{
    case Disabled = 'disabled';
    case Automatic = 'automatic';
    case Force = 'force';
}
```

---

# 612. Compression ownership

La compresión deberá pertenecer a:

* servidor HTTP;
* reverse proxy;
* transport adapter;
* response compression middleware central.

No al Controller.

---

# 613. Accept-Encoding trust

El header `Accept-Encoding` será input no confiable y deberá parsearse con límites.

---

# 614. Supported encodings

Podrán soportarse:

* gzip;
* br;
* zstd cuando la infraestructura lo permita;
* identity.

---

# 615. Encoding negotiation

La selección deberá considerar:

* soporte del cliente;
* disponibilidad del servidor;
* tipo de contenido;
* sensibilidad;
* tamaño;
* carga.

---

# 616. Incompressible content

No deberá comprimirse contenido ya comprimido como:

* ZIP;
* JPEG;
* PNG;
* MP4;
* archivos cifrados.

---

# 617. BREACH-style side channels

La compresión de respuestas que mezclan secretos y input reflejado puede filtrar información mediante diferencias de tamaño.

---

# 618. Sensitive compression policy

Las respuestas que contengan:

* CSRF tokens;
* secrets;
* tokens temporales;
* información restringida;

podrán deshabilitar compresión.

---

# 619. Reflection-aware compression

El clasificador podrá detectar respuestas que combinen:

```text
Secret
+
Attacker-controlled reflection
```

y elevar la política a `Disabled`.

---

# 620. Compression oracle mitigation

Las defensas podrán incluir:

* no comprimir;
* separar secretos;
* rotar tokens;
* añadir padding;
* evitar reflexión;
* limitar requests.

---

# 621. Compression CPU exhaustion

La compresión podrá utilizarse para consumir CPU.

---

# 622. Compression limits

Se deberán establecer:

* tamaño máximo comprimible;
* nivel máximo;
* concurrencia;
* tiempo de CPU;
* algoritmos permitidos.

---

# 623. Vary Accept-Encoding

Cuando existan variantes comprimidas deberá emitirse:

```text
Vary: Accept-Encoding
```

---

# 624. Precompressed assets

El asset pipeline podrá generar:

* `.gz`;
* `.br`;
* otras variantes.

Estas deberán vincularse al mismo fingerprint lógico.

---

# 625. Compression integrity

El `Content-Encoding` emitido deberá corresponder exactamente a los bytes transportados.

---

# 626. Content-Length Integrity

`Content-Length` define el tamaño del body transportado cuando aplica.

Una inconsistencia puede provocar:

* truncamiento;
* desincronización;
* smuggling;
* cache poisoning;
* respuestas corruptas.

---

# 627. Content-Length ownership

Solo la capa de transporte podrá definirlo.

---

# 628. Content-Length calculation

Deberá calcularse después de:

* encoding;
* compresión;
* transformación final;
* serialización.

---

# 629. Unknown length

Para streams de longitud desconocida no deberá inventarse un valor.

---

# 630. Duplicate Content-Length

Una respuesta no deberá contener múltiples valores ambiguos.

---

# 631. Conflicting Content-Length

Valores diferentes deberán provocar fallo cerrado.

---

# 632. Transfer-Encoding Integrity

`Transfer-Encoding` será controlado exclusivamente por el servidor o adapter.

---

# 633. Content-Length and Transfer-Encoding conflict

No deberán emitirse ambos cuando la semántica sea ambigua.

---

# 634. Response Splitting Defenses

Response splitting ocurre cuando input no confiable altera la estructura de headers o status line.

---

# 635. CRLF rejection

Se rechazarán:

* `\r`;
* `\n`;
* null bytes;
* controles prohibidos;

en todos los valores estructurales.

---

# 636. Structured header builders

Headers complejos deberán construirse mediante tipos dedicados:

* `Location`;
* `Content-Disposition`;
* `Link`;
* `Set-Cookie`;
* `Content-Range`;
* CSP;
* CORS.

---

# 637. No string concatenation

No se deberá construir un header crítico mediante concatenación directa de strings.

---

# 638. Header serialization boundary

La serialización final deberá ocurrir una sola vez dentro del adapter de transporte.

---

# 639. Response smuggling interaction

Aunque el request smuggling suele originarse en la petición, una respuesta mal delimitada puede desincronizar conexiones persistentes e intermediarios.

---

# 640. Resultado de esta entrega

Esta entrega establece:

```text
Server-Sent Events Security
SSE Encoding and Injection Protection
Long-Lived Authorization
SSE Reconnection Security
Signed Event Cursors
Range Request Security
Partial Content Validation
Compression Security
BREACH-Style Mitigations
Content-Length Integrity
Transfer-Encoding Integrity
Response Splitting Defenses
Response Smuggling Foundations
```

# CONTROLLER_SECURITY_MODEL_PART_04.md

## Transport & Response Security

**Entrega:** 8 de varias
**Cobertura:** Secciones **641–740**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_04.md — Entrega 7`

---

# 641. HTTP Smuggling Defense Model

HTTP smuggling aparece cuando dos componentes interpretan de forma diferente los límites de una petición o respuesta.

Un flujo típico puede involucrar:

```text
Client
  ↓
CDN
  ↓
Reverse Proxy
  ↓
Load Balancer
  ↓
FrankenPHP
  ↓
VoltStack
```

Si cualquiera de estas capas interpreta de manera distinta:

* `Content-Length`;
* `Transfer-Encoding`;
* cuerpos chunked;
* headers duplicados;
* whitespace;
* conexiones persistentes;

podrá producirse desincronización.

---

# 642. Smuggling trust boundary

VoltStack no controla todas las capas de infraestructura, pero deberá:

* rechazar representaciones ambiguas;
* no generar respuestas ambiguas;
* validar metadata de proxy;
* usar adapters compatibles;
* documentar configuraciones seguras;
* auditar inconsistencias.

---

# 643. Request framing validation

Antes de ejecutar Controllers, la capa HTTP deberá validar la coherencia del framing del request.

---

# 644. Content-Length and Transfer-Encoding

Una petición que contenga ambos deberá:

* ser rechazada;
* o ser normalizada únicamente por una capa confiable con semántica inequívoca.

El comportamiento por defecto deberá ser fail closed.

---

# 645. Duplicate Content-Length

Múltiples headers `Content-Length` deberán aceptarse únicamente si la infraestructura los normaliza de manera segura y todos los valores son idénticos.

VoltStack deberá preferir rechazo.

---

# 646. Conflicting body lengths

Valores diferentes deberán provocar el cierre de la petición antes del routing.

---

# 647. Transfer-Encoding canonicalization

El valor deberá parsearse como una lista estructurada.

No mediante comparaciones parciales de strings.

---

# 648. Unsupported transfer codings

Los codings no soportados deberán rechazarse.

---

# 649. Obfuscated transfer encodings

Deberán detectarse variantes ambiguas con:

* espacios inesperados;
* casing;
* delimitadores inválidos;
* valores duplicados;
* caracteres de control.

---

# 650. Chunked request validation

Los cuerpos chunked deberán ser procesados por el servidor HTTP o adapter confiable.

Los Controllers nunca deberán interpretar chunks manualmente.

---

# 651. Chunk extension policy

Las extensiones de chunks deberán ignorarse o rechazarse según las capacidades del servidor.

No deberán exponerse a la aplicación.

---

# 652. Trailer headers

Los trailers deberán estar deshabilitados por defecto para la lógica de aplicación.

---

# 653. Request trailer trust

Un trailer no deberá sobrescribir headers ya validados.

---

# 654. Header whitespace normalization

Whitespace ambiguo deberá normalizarse antes de interpretar headers críticos.

---

# 655. Obsolete line folding

La continuación de headers mediante line folding obsoleto deberá rechazarse.

---

# 656. Null-byte rejection

Los null bytes deberán rechazarse en:

* nombres;
* valores;
* path;
* query;
* host;
* forwarded headers.

---

# 657. Connection reuse after parse error

Ante errores de framing, la conexión deberá cerrarse cuando la infraestructura lo permita.

---

# 658. Response desynchronization

VoltStack deberá garantizar que cada respuesta tenga:

* framing inequívoco;
* body compatible;
* longitud consistente;
* headers congelados;
* cierre controlado del stream.

---

# 659. SmugglingSecurityGuard

```php
interface SmugglingSecurityGuardInterface
{
    public function validateRequest(
        ServerRequestInterface $request,
        RequestTransportMetadata $transport
    ): SmugglingValidationResult;

    public function validateResponse(
        SecureHttpResponse $response
    ): SmugglingValidationResult;
}
```

---

# 660. Validation outcomes

```php
enum SmugglingValidationStatus: string
{
    case Valid = 'valid';
    case Ambiguous = 'ambiguous';
    case Invalid = 'invalid';
    case InfrastructureMismatch = 'infrastructure_mismatch';
}
```

---

# 661. Infrastructure mismatch

VoltStack deberá poder detectar configuraciones incompatibles entre:

* proxy;
* servidor;
* runtime;
* adapter;
* middleware.

---

# 662. Deployment health check

El sistema podrá ejecutar pruebas de salud específicas para framing HTTP en entornos controlados.

---

# 663. Reverse Proxy Trust Model

Los reverse proxies pueden aportar información necesaria sobre:

* IP original;
* scheme;
* host;
* port;
* protocolo;
* cadena de proxies.

Esta información será no confiable hasta validar el emisor.

---

# 664. Direct peer identity

La primera decisión deberá basarse en la IP o identidad de la conexión directa.

---

# 665. Trusted proxy definition

```php
final readonly class TrustedProxyDefinition
{
    public function __construct(
        public string $id,
        public NetworkMatcher $network,
        public ProxyCapabilitySet $capabilities,
        public int $priority,
    ) {
    }
}
```

---

# 666. Proxy capabilities

```php
enum ProxyCapability: string
{
    case ForwardClientIp = 'forward_client_ip';
    case ForwardHost = 'forward_host';
    case ForwardPort = 'forward_port';
    case ForwardScheme = 'forward_scheme';
    case ForwardProtocol = 'forward_protocol';
    case ForwardTlsMetadata = 'forward_tls_metadata';
}
```

---

# 667. Trusted Proxy Registry

```php
interface TrustedProxyRegistryInterface
{
    public function register(
        TrustedProxyDefinition $proxy
    ): void;

    public function resolve(
        NetworkAddress $directPeer
    ): ?TrustedProxyDefinition;

    public function freeze(): void;
}
```

---

# 668. Registry freeze

El registry deberá congelarse durante el bootstrap de producción.

---

# 669. Network matchers

Podrán soportarse:

* IP exacta;
* CIDR;
* Unix socket identity;
* network interface;
* cloud load balancer ranges administrados.

---

# 670. Broad trust ranges

No deberán configurarse redes demasiado amplias sin una justificación explícita.

---

# 671. Trust all proxies

Una opción equivalente a:

```text
0.0.0.0/0
::/0
```

deberá prohibirse en producción por defecto.

---

# 672. Cloud proxy ranges

Los rangos administrados por proveedores deberán:

* actualizarse de forma controlada;
* validarse;
* versionarse;
* tener fallback seguro.

---

# 673. Proxy identity beyond IP

En infraestructuras avanzadas podrán utilizarse:

* mTLS;
* private network;
* signed headers;
* workload identity;
* proxy protocol.

---

# 674. Forwarded Header

VoltStack deberá soportar el header estándar:

```text
Forwarded
```

mediante un parser estructurado.

---

# 675. Forwarded element

Un elemento podrá incluir:

```text
for=
by=
host=
proto=
```

---

# 676. ForwardedHeaderParser

```php
interface ForwardedHeaderParserInterface
{
    public function parse(
        HeaderValue $value
    ): ForwardedHeaderChain;
}
```

---

# 677. Parsing limits

Se deberán limitar:

* cantidad de elementos;
* longitud total;
* longitud por parámetro;
* cantidad de parámetros;
* nesting o quoting.

---

# 678. Quoted values

Los valores quoted deberán procesarse según una gramática estricta.

---

# 679. Obfuscated identifiers

Los identificadores obfuscados podrán conservarse para auditoría, pero no deberán convertirse en IPs.

---

# 680. IPv6 forwarded values

Los literales IPv6 deberán parsearse correctamente, incluidos brackets y port cuando corresponda.

---

# 681. Unknown forwarded values

El valor `unknown` no deberá utilizarse para tomar decisiones de seguridad.

---

# 682. Forwarded chain direction

VoltStack deberá definir con claridad el orden en que interpreta la cadena.

---

# 683. Trust chain walking

La resolución recomendada será:

```text
Direct peer
    ↓ trusted?
Read nearest forwarded hop
    ↓ trusted?
Continue
    ↓
First untrusted client address
```

---

# 684. Stop at first untrusted hop

No deberán utilizarse valores anteriores al primer hop no confiable.

---

# 685. X-Forwarded-* support

Por compatibilidad podrán procesarse:

* `X-Forwarded-For`;
* `X-Forwarded-Host`;
* `X-Forwarded-Proto`;
* `X-Forwarded-Port`;
* `X-Forwarded-Prefix`.

---

# 686. Header precedence

No deberán combinarse automáticamente `Forwarded` y `X-Forwarded-*` si producen resultados distintos.

---

# 687. Forwarding strategy

```php
enum ForwardingHeaderStrategy: string
{
    case ForwardedOnly = 'forwarded_only';
    case XForwardedOnly = 'x_forwarded_only';
    case PreferForwarded = 'prefer_forwarded';
    case RejectOnConflict = 'reject_on_conflict';
}
```

---

# 688. Recommended conflict strategy

En perfiles estrictos se recomienda:

```text
RejectOnConflict
```

---

# 689. X-Forwarded-For parsing

Cada elemento deberá:

* limpiarse;
* parsearse;
* validarse;
* clasificarse como trusted o untrusted.

---

# 690. Client IP Resolution

La IP del cliente no deberá resolverse tomando simplemente el primer valor del header.

---

# 691. ClientAddressResolver

```php
interface ClientAddressResolverInterface
{
    public function resolve(
        NetworkAddress $directPeer,
        ProxyHeaderContext $headers,
        TrustedProxyRegistryInterface $registry
    ): ResolvedClientAddress;
}
```

---

# 692. ResolvedClientAddress

```php
final readonly class ResolvedClientAddress
{
    public function __construct(
        public NetworkAddress $address,
        public array $trustedProxyChain,
        public ClientAddressConfidence $confidence,
    ) {
    }
}
```

---

# 693. ClientAddressConfidence

```php
enum ClientAddressConfidence: string
{
    case Direct = 'direct';
    case TrustedProxyChain = 'trusted_proxy_chain';
    case PartialChain = 'partial_chain';
    case Unknown = 'unknown';
}
```

---

# 694. IP-based security limits

La IP no deberá ser el único factor para:

* autenticación;
* tenant selection;
* high-risk authorization;
* identity resolution.

---

# 695. Rate limiting

Sí podrá utilizarse como una señal dentro de rate limiting y detección de abuso.

---

# 696. IP privacy

Las direcciones deberán almacenarse y registrarse conforme a la política de privacidad de la aplicación.

---

# 697. Scheme Resolution

El scheme efectivo deberá resolverse desde:

1. conexión directa;
2. proxy confiable;
3. configuración de infraestructura.

---

# 698. SchemeResolver

```php
interface EffectiveSchemeResolverInterface
{
    public function resolve(
        DirectTransportContext $direct,
        TrustedProxyContext $proxy
    ): EffectiveScheme;
}
```

---

# 699. Allowed schemes

Para HTTP web se permitirán:

* `http`;
* `https`.

Cualquier otro valor deberá rechazarse.

---

# 700. Forwarded proto validation

No deberán aceptarse valores como:

```text
https,http
javascript
https:
```

sin parsing y normalización estrictos.

---

# 701. Secure scheme confidence

El sistema deberá conocer si HTTPS fue:

* directo;
* terminado en proxy confiable;
* inferido;
* desconocido.

---

# 702. EffectiveScheme

```php
final readonly class EffectiveScheme
{
    public function __construct(
        public string $value,
        public SchemeConfidence $confidence,
        public bool $secure,
    ) {
    }
}
```

---

# 703. HSTS dependency

HSTS solo deberá emitirse cuando el scheme efectivo seguro sea confiable.

---

# 704. Secure cookie dependency

Las cookies `Secure` podrán emitirse detrás de TLS termination únicamente si la cadena de proxy ha sido validada.

---

# 705. Port Resolution

El puerto efectivo deberá derivarse de forma coherente con el scheme y host.

---

# 706. Forwarded port validation

El valor deberá:

* ser numérico;
* estar entre 1 y 65535;
* provenir de proxy confiable;
* ser coherente con el host cuando este incluya puerto.

---

# 707. Default ports

Se normalizarán:

```text
http  → 80
https → 443
```

---

# 708. Port mismatch

Una discrepancia entre:

* `Forwarded host`;
* `X-Forwarded-Port`;
* conexión directa;

deberá registrarse o rechazarse según política.

---

# 709. Host Header Security

El header `Host` influye en:

* routing;
* URLs absolutas;
* cookies;
* redirects;
* cache;
* tenant resolution;
* enlaces de recuperación;
* callbacks.

---

# 710. Host poisoning

Un `Host` no validado puede provocar:

* password reset poisoning;
* redirect poisoning;
* cache poisoning;
* tenant confusion;
* generación de enlaces maliciosos.

---

# 711. ValidatedHost

```php
final readonly class ValidatedHost
{
    public function __construct(
        public string $asciiHost,
        public ?string $unicodeHost,
        public ?int $port,
        public HostTrustSource $source,
    ) {
    }
}
```

---

# 712. HostTrustSource

```php
enum HostTrustSource: string
{
    case DirectHeader = 'direct_header';
    case TrustedForwardedHeader = 'trusted_forwarded_header';
    case RouteBinding = 'route_binding';
    case ApplicationConfiguration = 'application_configuration';
}
```

---

# 713. Host syntax validation

El host deberá rechazar:

* controles;
* espacios;
* slashes;
* backslashes;
* userinfo;
* path;
* query;
* fragment;
* puertos inválidos.

---

# 714. IDN normalization

Los hosts internacionales deberán normalizarse a una forma ASCII canónica antes de comparar.

---

# 715. Trailing dot

La política deberá decidir si normaliza:

```text
example.com.
```

a:

```text
example.com
```

de forma consistente.

---

# 716. Mixed-case host

La comparación deberá ser case-insensitive.

---

# 717. Duplicate Host headers

Múltiples headers `Host` deberán provocar rechazo.

---

# 718. HTTP/2 authority

En HTTP/2 y HTTP/3 deberá validarse `:authority` de forma equivalente.

---

# 719. Host and authority conflict

Si ambos están presentes y difieren, la petición deberá rechazarse.

---

# 720. Allowed Host Registry

```php
interface AllowedHostRegistryInterface
{
    public function match(
        ValidatedHost $host,
        RequestSecurityContext $context
    ): ?AllowedHostDefinition;
}
```

---

# 721. AllowedHostDefinition

```php
final readonly class AllowedHostDefinition
{
    public function __construct(
        public string $id,
        public HostMatcher $matcher,
        public HostPurpose $purpose,
        public bool $canonical,
        public ?string $tenantResolver,
    ) {
    }
}
```

---

# 722. Host purposes

```php
enum HostPurpose: string
{
    case PrimaryApplication = 'primary_application';
    case Tenant = 'tenant';
    case Api = 'api';
    case Assets = 'assets';
    case Admin = 'admin';
    case Callback = 'callback';
}
```

---

# 723. Default host policy

Toda petición con host no reconocido deberá rechazarse antes del routing de aplicación.

---

# 724. Wildcard hosts

Los wildcard deberán utilizar matchers estructurados.

---

# 725. Subdomain tenant matching

Un host como:

```text
tenant.example.com
```

deberá resolver el tenant mediante una regla registrada y no mediante extracción arbitraria.

---

# 726. Registrable domain validation

El sistema deberá evitar tratar como subdominio válido un host fuera del dominio registrable esperado.

---

# 727. Reserved subdomains

Se podrán reservar:

* `www`;
* `api`;
* `admin`;
* `assets`;
* `auth`;
* `support`.

---

# 728. Tenant host collision

No podrán coexistir tenants con hosts canónicamente equivalentes.

---

# 729. Custom tenant domains

Los dominios personalizados deberán pasar por:

* verificación de propiedad;
* validación DNS;
* emisión TLS;
* registro;
* activación atómica.

---

# 730. Canonical Host Enforcement

Una aplicación podrá definir un host canónico para evitar variantes ambiguas.

---

# 731. Canonical host redirect

La redirección deberá:

* validar host origen;
* preservar path seguro;
* preservar query según política;
* forzar HTTPS;
* evitar loops.

---

# 732. Unsafe method canonicalization

Las peticiones mutables no deberán redirigirse automáticamente a otro host sin evaluar preservación del método y del body.

---

# 733. Canonical host for APIs

Las APIs podrán rechazar en lugar de redirigir.

---

# 734. Absolute URL Poisoning Prevention

Toda URL absoluta generada deberá usar:

* host validado;
* scheme confiable;
* port resuelto;
* route segura.

---

# 735. Password reset URLs

Nunca deberán derivarse de un host no validado.

---

# 736. Email link generation

Los enlaces enviados por email deberán preferir un origen configurado o una identidad de tenant validada.

---

# 737. Signed URL host binding

Las firmas deberán incluir el host cuando el enlace sea host-specific.

---

# 738. Proxy Chain Audit

VoltStack deberá producir una representación auditable de la cadena de transporte sin exponerla al cliente.

---

# 739. ProxyChainAuditRecord

```php
final readonly class ProxyChainAuditRecord
{
    public function __construct(
        public NetworkAddress $directPeer,
        public array $trustedHops,
        public ?NetworkAddress $resolvedClient,
        public EffectiveScheme $scheme,
        public ValidatedHost $host,
        public ?int $port,
        public array $conflicts,
    ) {
    }
}
```

---

# 740. Resultado de esta entrega

Esta entrega establece:

```text
HTTP Smuggling Defense Model
Request and Response Framing Validation
Duplicate Length Protection
Transfer-Encoding Validation
Reverse Proxy Trust Model
Trusted Proxy Registry
Forwarded Header Parsing
X-Forwarded Header Compatibility
Client IP Resolution
Scheme and Port Resolution
Host Header Security
Allowed Host Registry
Multi-Tenant Host Validation
Canonical Host Enforcement
Absolute URL Poisoning Prevention
Proxy Chain Auditing
```

# CONTROLLER_SECURITY_MODEL_PART_04.md

## Transport & Response Security

**Entrega:** 9 de varias
**Cobertura:** Secciones **741–840**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_04.md — Entrega 8`

---

# 741. Cache-Control Security Model

El sistema de cache HTTP deberá diseñarse bajo el principio de que **una respuesta incorrectamente cacheada constituye una fuga de información**.

El objetivo no es únicamente mejorar el rendimiento, sino garantizar:

* confidencialidad;
* integridad;
* aislamiento entre usuarios;
* aislamiento entre tenants;
* consistencia;
* invalidez controlada;
* comportamiento determinista.

---

# 742. Cache trust boundaries

VoltStack deberá considerar los siguientes niveles de cache:

```text
Controller
        │
Application Cache
        │
Runtime Cache
        │
Reverse Proxy Cache
        │
CDN
        │
Browser Cache
```

Cada uno tendrá un nivel de confianza diferente.

---

# 743. Cache ownership

Los Controllers no deberán construir manualmente encabezados `Cache-Control`.

La decisión pertenecerá al motor de seguridad de respuestas.

---

# 744. CachePolicy

```php
final readonly class CachePolicy
{
    public function __construct(
        public CacheVisibility $visibility,
        public CacheStorageMode $storage,
        public CacheRevalidationPolicy $revalidation,
        public CacheLifetime $lifetime,
    ) {
    }
}
```

---

# 745. CacheVisibility

```php
enum CacheVisibility: string
{
    case Public = 'public';
    case Private = 'private';
    case Restricted = 'restricted';
}
```

---

# 746. CacheStorageMode

```php
enum CacheStorageMode: string
{
    case NoStore = 'no-store';
    case Store = 'store';
    case Conditional = 'conditional';
}
```

---

# 747. Default policy

Toda respuesta autenticada utilizará por defecto:

```text
private
no-store
```

salvo que exista una política explícita diferente.

---

# 748. Public responses

Una respuesta pública deberá cumplir:

* ausencia de datos personalizados;
* ausencia de secretos;
* ausencia de sesión;
* ausencia de tokens;
* independencia del usuario.

---

# 749. Private responses

Una respuesta privada podrá almacenarse únicamente por el navegador del usuario.

---

# 750. Restricted responses

Las respuestas clasificadas como **Restricted** no deberán almacenarse en ningún nivel.

---

# 751. Cache-Control Builder

```php
interface CacheControlBuilderInterface
{
    public function build(
        CachePolicy $policy,
        ResponseSecurityContext $context
    ): HeaderValue;
}
```

---

# 752. Header normalization

Los encabezados de cache deberán serializarse siempre en el mismo orden.

---

# 753. Immutable resources

Los recursos versionados podrán declararse como:

```text
immutable
```

cuando el fingerprint garantice unicidad.

---

# 754. Fingerprinted assets

Los assets generados por el pipeline podrán utilizar:

```text
app.f31a9f2.js
```

como identificador inmutable.

---

# 755. Runtime responses

Las respuestas generadas dinámicamente no deberán declararse `immutable`.

---

# 756. Sensitive endpoints

Los siguientes endpoints deberán utilizar `no-store`:

* login;
* logout;
* MFA;
* password reset;
* billing;
* administración;
* perfiles;
* datos personales.

---

# 757. Authentication responses

Nunca deberán almacenarse respuestas que contengan:

* cookies nuevas;
* tokens;
* credenciales;
* cambios de autenticación.

---

# 758. Redirect cache policy

Las redirecciones permanentes deberán revisarse cuidadosamente antes de permitir cache compartido.

---

# 759. Download cache policy

Las descargas protegidas deberán especificar una política explícita.

---

# 760. Streaming cache policy

Los streams:

* SSE;
* NDJSON;
* streams binarios;

normalmente no deberán almacenarse.

---

# 761. CDN Security Model

Los CDN representan un cache compartido.

Por tanto, deberán considerarse no confiables para información privada.

---

# 762. Shared cache eligibility

Una respuesta solo podrá almacenarse en cache compartido cuando sea completamente independiente del usuario.

---

# 763. Personalized responses

Las respuestas personalizadas nunca deberán publicarse mediante:

```text
Cache-Control: public
```

---

# 764. Tenant-aware caching

En aplicaciones multi-tenant, el tenant formará parte del contexto de cache.

---

# 765. Cache partitioning

La clave lógica incluirá:

* tenant;
* locale;
* representación;
* perfil;
* versión.

---

# 766. Authorization-aware cache

Las respuestas cuyo contenido dependa de permisos deberán excluirse del cache compartido.

---

# 767. Capability-aware cache

Si el contenido cambia según capacidades del usuario, dichas capacidades deberán formar parte del contexto de variación o la respuesta deberá marcarse como privada.

---

# 768. Vary Security Model

El encabezado `Vary` controla la selección de representaciones.

---

# 769. VaryBuilder

```php
interface VaryBuilderInterface
{
    public function build(
        ResponseVariationPolicy $policy
    ): HeaderValue;
}
```

---

# 770. Supported Vary headers

Se admitirán únicamente encabezados explícitamente aprobados, por ejemplo:

* Accept
* Accept-Encoding
* Accept-Language
* Origin

---

# 771. Unsafe Vary

No deberá variarse por encabezados arbitrarios proporcionados por el usuario.

---

# 772. Vary normalization

Los nombres incluidos en `Vary` deberán:

* normalizarse;
* eliminar duplicados;
* ordenarse canónicamente.

---

# 773. Excessive variation

Una cantidad excesiva de dimensiones de variación puede degradar significativamente la eficiencia del cache.

---

# 774. Cache key integrity

La clave utilizada para cache deberá construirse mediante componentes estructurados.

---

# 775. CacheKeyContext

```php
final readonly class CacheKeyContext
{
    public function __construct(
        public string $route,
        public string $representation,
        public string $tenant,
        public string $locale,
        public string $profile,
    ) {
    }
}
```

---

# 776. Cache poisoning

Una representación no deberá sobrescribir otra representación incompatible.

---

# 777. Variant confusion

Dos respuestas distintas no deberán producir la misma clave de cache.

---

# 778. Cache metadata

Cada entrada deberá almacenar:

* fecha;
* política;
* ETag;
* Last-Modified;
* clasificación;
* tenant;
* versión.

---

# 779. Cache invalidation

Toda invalidación deberá ser explícita y auditable.

---

# 780. Invalidation events

Eventos típicos:

* publicación;
* actualización;
* eliminación;
* cambio de permisos;
* cambio de tenant;
* despliegue.

---

# 781. ETag Security Model

ETag permite validar representaciones sin retransmitir el contenido completo.

---

# 782. ETag ownership

El cálculo de ETag no pertenecerá al Controller.

---

# 783. ETagStrategy

```php
enum ETagStrategy: string
{
    case Strong = 'strong';
    case Weak = 'weak';
}
```

---

# 784. Strong validators

Los ETag fuertes representan exactamente los bytes transmitidos.

---

# 785. Weak validators

Los ETag débiles representan equivalencia semántica.

---

# 786. ETagBuilder

```php
interface ETagBuilderInterface
{
    public function build(
        ResponseRepresentation $representation,
        ETagStrategy $strategy
    ): EntityTag;
}
```

---

# 787. Stable generation

El algoritmo de generación deberá ser determinista.

---

# 788. Secret leakage

El ETag no deberá revelar:

* IDs internos;
* rutas;
* hashes reversibles;
* timestamps sensibles.

---

# 789. EntityTag

```php
final readonly class EntityTag
{
    public function __construct(
        public string $value,
        public bool $weak,
    ) {
    }
}
```

---

# 790. Representation scope

Cada representación tendrá su propio ETag.

---

# 791. Compression variants

Una representación comprimida podrá requerir un validator distinto.

---

# 792. Content negotiation

Los distintos formatos:

* HTML;
* JSON;
* XML;

no compartirán el mismo validator.

---

# 793. Tenant isolation

Dos tenants nunca deberán compartir un ETag cuando el contenido sea diferente.

---

# 794. Personalized responses

Las respuestas personalizadas podrán omitir ETag cuando el beneficio sea mínimo o exista riesgo de reutilización indebida.

---

# 795. If-None-Match

El parser deberá soportar correctamente:

```text
If-None-Match
```

---

# 796. EntityTag comparison

La comparación distinguirá correctamente:

* fuerte;
* débil.

---

# 797. Wildcard entity tag

El valor:

```text
*
```

deberá interpretarse conforme a la semántica del estándar.

---

# 798. Conditional request validator

```php
interface ConditionalRequestValidatorInterface
{
    public function validate(
        ServerRequestInterface $request,
        ResponseRepresentation $representation
    ): ConditionalRequestResult;
}
```

---

# 799. Last-Modified Security

El encabezado deberá representar el instante real de modificación de la representación.

---

# 800. Trusted timestamps

Las fechas deberán derivarse de una fuente confiable y consistente.

---

# 801. LastModifiedBuilder

```php
interface LastModifiedBuilderInterface
{
    public function build(
        ResponseRepresentation $representation
    ): DateTimeImmutable;
}
```

---

# 802. Future timestamps

No deberán emitirse fechas futuras salvo casos excepcionalmente documentados.

---

# 803. Clock consistency

Todos los nodos del cluster deberán mantener sincronización horaria adecuada.

---

# 804. If-Modified-Since

Las fechas recibidas deberán validarse estrictamente.

---

# 805. If-Unmodified-Since

Podrá utilizarse para proteger operaciones concurrentes.

---

# 806. Date parsing

Las fechas inválidas deberán rechazarse silenciosamente según el estándar, sin provocar errores internos.

---

# 807. Conditional evaluation order

La evaluación seguirá un orden determinista entre:

* If-Match;
* If-None-Match;
* If-Modified-Since;
* If-Unmodified-Since;
* If-Range.

---

# 808. Precondition failure

Cuando una precondición falle, la respuesta deberá indicar el estado correspondiente sin revelar información adicional.

---

# 809. Not Modified responses

Una respuesta equivalente a:

```text
304 Not Modified
```

no deberá incluir un cuerpo de contenido.

---

# 810. 304 metadata

Los encabezados emitidos deberán ser coherentes con la representación validada.

---

# 811. Validator consistency

No deberán emitirse simultáneamente validadores incompatibles.

---

# 812. Weak validator usage

Los validadores débiles no deberán emplearse para operaciones que requieran igualdad byte a byte.

---

# 813. Cache validator registry

```php
interface CacheValidatorRegistryInterface
{
    public function register(
        RepresentationValidator $validator
    ): void;
}
```

---

# 814. Validator lifecycle

Los validadores deberán invalidarse cuando cambie la representación.

---

# 815. Deployment awareness

Un despliegue podrá invalidar representaciones si cambia su semántica.

---

# 816. Multi-node consistency

Todos los nodos deberán calcular el mismo validator para la misma representación.

---

# 817. Conditional GET

Las peticiones GET condicionales deberán evitar trabajo innecesario cuando la representación permanezca válida.

---

# 818. Conditional HEAD

HEAD seguirá las mismas reglas de validación que GET.

---

# 819. Cache audit

El framework registrará:

* hits;
* misses;
* revalidaciones;
* invalidaciones;
* respuestas 304;
* conflictos.

---

# 820. Cache metrics

Métricas recomendadas:

* ratio de aciertos;
* tamaño;
* variantes;
* TTL medio;
* invalidaciones;
* colisiones.

---

# 821. Stale content policy

Las respuestas obsoletas deberán controlarse mediante políticas explícitas.

---

# 822. Stale revalidation

Cuando la política lo permita, el cache podrá revalidar antes de servir una representación.

---

# 823. Stale-if-error

Podrá configurarse para mejorar disponibilidad, siempre que la sensibilidad de la respuesta lo permita.

---

# 824. Stale-while-revalidate

Los recursos públicos podrán aprovechar esta estrategia bajo límites definidos.

---

# 825. Sensitive stale data

Nunca deberán servirse respuestas obsoletas que contengan datos sensibles o personalizados.

---

# 826. Cache poisoning defense

Toda representación deberá comprobar:

* contexto;
* tenant;
* usuario cuando aplique;
* política;
* variante.

---

# 827. Header confusion

Los encabezados utilizados para construir la clave no deberán aceptar variantes ambiguas.

---

# 828. Response classification

La clasificación de seguridad formará parte del modelo de cache.

---

# 829. Runtime cache isolation

Los caches internos del runtime deberán respetar el mismo aislamiento que los caches HTTP.

---

# 830. Cache security events

Eventos relevantes:

* CachePoisoningDetected;
* InvalidValidator;
* CachePolicyViolation;
* VariantMismatch;
* SharedCacheRejected.

---

# 831. Testing strategy

Las pruebas deberán cubrir:

* Vary;
* ETag;
* Last-Modified;
* 304;
* cache compartido;
* multi-tenant;
* compresión.

---

# 832. Security audit

Las decisiones de cache deberán ser completamente auditables.

---

# 833. Deployment verification

Durante el despliegue podrán ejecutarse verificaciones de coherencia de validadores y políticas.

---

# 834. Backward compatibility

Los cambios en estrategias de cache deberán poder versionarse para evitar comportamientos inconsistentes.

---

# 835. Performance considerations

La seguridad del cache no deberá depender de optimizaciones específicas del servidor HTTP.

---

# 836. Documentation requirements

Toda política personalizada de cache deberá documentarse indicando:

* finalidad;
* riesgos;
* alcance;
* responsables.

---

# 837. ADR-111

**Separación entre clasificación de seguridad y política de cache.**

---

# 838. ADR-112

**Los Controllers nunca construirán manualmente encabezados Cache-Control, ETag o Last-Modified.**

---

# 839. ADR-113

**Toda representación cacheable deberá poseer una política de variación determinista.**

---

# 840. Resultado de esta entrega

Esta entrega establece:

```text
Complete Cache-Control Security Model
Shared Cache Protection
Tenant-Aware Caching
Authorization-Aware Caching
Cache Key Integrity
Vary Security
ETag Architecture
Strong and Weak Validators
Last-Modified Validation
Conditional Requests
304 Security
Stale Content Policies
Cache Poisoning Defenses
Validator Lifecycle
Cache Auditing and Metrics
```

# CONTROLLER_SECURITY_MODEL_PART_04.md

## Transport & Response Security

**Entrega:** 10 de 10
**Cobertura:** Secciones **841–950**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_04.md — Entrega 9`
**Estado:** Cierre de `CONTROLLER_SECURITY_MODEL_PART_04`

---

# 841. Response Integrity Security Model

La integridad de una respuesta garantiza que:

* el contenido emitido corresponde a la representación autorizada;
* no fue alterado por middleware no autorizado;
* sus headers describen correctamente el body;
* la respuesta pertenece al request y contexto esperados;
* los intermediarios no modificaron silenciosamente datos protegidos;
* el cliente puede verificar autenticidad cuando el protocolo lo requiera.

La integridad deberá evaluarse en varias capas:

```text
Controller Result
      ↓
Normalized Representation
      ↓
Security Transformation
      ↓
Final Response
      ↓
Transport Encoding
      ↓
Network
      ↓
Client Verification
```

---

# 842. Integrity dimensions

VoltStack distinguirá entre:

* integridad lógica;
* integridad de representación;
* integridad de transporte;
* autenticidad;
* frescura;
* vinculación contextual.

---

# 843. Logical integrity

La integridad lógica asegura que el contenido corresponde al resultado autorizado del Controller.

---

# 844. Representation integrity

La integridad de representación asegura que los bytes serializados coinciden con:

* content type;
* encoding;
* locale;
* variante negociada;
* perfil de seguridad.

---

# 845. Transport integrity

La integridad de transporte depende principalmente de:

* TLS;
* HTTP framing;
* proxy confiable;
* ausencia de response splitting;
* delimitación correcta del body.

---

# 846. Authenticity

La autenticidad permite verificar que la respuesta fue producida por una entidad autorizada.

---

# 847. Freshness

La frescura evita aceptar respuestas:

* expiradas;
* reproducidas;
* pertenecientes a otro request;
* generadas para otro usuario o tenant.

---

# 848. ResponseIntegrityPolicy

```php
final readonly class ResponseIntegrityPolicy
{
    public function __construct(
        public IntegrityMode $mode,
        public DigestPolicy $digest,
        public SignaturePolicy $signature,
        public FreshnessPolicy $freshness,
        public ContextBindingPolicy $contextBinding,
    ) {
    }
}
```

---

# 849. IntegrityMode

```php
enum IntegrityMode: string
{
    case Disabled = 'disabled';
    case DigestOnly = 'digest_only';
    case Signed = 'signed';
    case SignedAndEncryptedTransport = 'signed_and_encrypted_transport';
}
```

---

# 850. Default integrity mode

Las respuestas web convencionales utilizarán normalmente:

```text
TLS
+
HTTP framing validation
+
internal representation integrity
```

Las firmas criptográficas externas deberán reservarse para escenarios que realmente las necesiten.

---

# 851. High-integrity scenarios

Podrán requerir firmas de respuesta:

* webhooks;
* APIs interorganizacionales;
* documentos regulatorios;
* comprobantes;
* paquetes de configuración;
* manifests;
* respuestas offline;
* sincronización entre nodos;
* artefactos descargables.

---

# 852. ResponseIntegrityEngine

```php
interface ResponseIntegrityEngineInterface
{
    public function protect(
        SecureHttpResponse $response,
        ResponseIntegrityPolicy $policy,
        ResponseSecurityContext $context
    ): IntegrityProtectedResponse;
}
```

---

# 853. Integrity processing order

La protección deberá aplicarse después de completar la representación final.

```text
Serialize
   ↓
Transform
   ↓
Compress when applicable
   ↓
Calculate digest
   ↓
Build signature input
   ↓
Sign
   ↓
Freeze
   ↓
Emit
```

El orden exacto podrá variar según si la firma protege la representación o el mensaje transportado.

---

# 854. Representation versus transfer integrity

VoltStack deberá diferenciar:

* digest de contenido sin compresión;
* digest de bytes transferidos;
* firma de campos semánticos;
* firma de headers y body final.

---

# 855. Digest Security Model

Un digest permite detectar modificaciones accidentales o maliciosas del contenido.

No demuestra por sí solo quién produjo la respuesta.

---

# 856. DigestAlgorithm

```php
enum DigestAlgorithm: string
{
    case Sha256 = 'sha-256';
    case Sha384 = 'sha-384';
    case Sha512 = 'sha-512';
}
```

---

# 857. Deprecated digest algorithms

No deberán utilizarse para integridad de seguridad:

* MD5;
* SHA-1;
* algoritmos propietarios débiles.

---

# 858. DigestPolicy

```php
final readonly class DigestPolicy
{
    public function __construct(
        public bool $enabled,
        public DigestAlgorithm $algorithm,
        public DigestScope $scope,
        public bool $requiredForClient,
    ) {
    }
}
```

---

# 859. DigestScope

```php
enum DigestScope: string
{
    case Representation = 'representation';
    case TransferredContent = 'transferred_content';
    case DownloadArtifact = 'download_artifact';
}
```

---

# 860. Digest builder

```php
interface ResponseDigestBuilderInterface
{
    public function build(
        ReadableContentInterface $content,
        DigestAlgorithm $algorithm
    ): ContentDigest;
}
```

---

# 861. Streaming digest

Para streams, el digest deberá calcularse incrementalmente.

---

# 862. Digest and unknown streams

Cuando no sea posible conocer el digest antes de emitir headers, podrán utilizarse:

* trailers confiables;
* digest externo del artefacto;
* firma del manifest;
* verificación posterior.

Los trailers permanecerán deshabilitados salvo soporte seguro de toda la cadena.

---

# 863. Download digest

Las descargas sensibles podrán publicar un digest separado mediante metadata segura.

---

# 864. Digest mismatch

Una discrepancia deberá producir:

* rechazo del artefacto;
* evento de seguridad;
* invalidación de cache;
* posible aislamiento del nodo;
* investigación de infraestructura.

---

# 865. Digest confidentiality

Un digest no deberá utilizarse como sustituto de autorización.

---

# 866. Low-entropy content risk

Los hashes de contenido predecible pueden permitir inferencias.

No deberán exponerse innecesariamente para recursos privados.

---

# 867. HTTP Message Signatures

VoltStack podrá soportar firmas estructuradas de mensajes HTTP mediante un módulo especializado.

---

# 868. SignaturePolicy

```php
final readonly class SignaturePolicy
{
    public function __construct(
        public bool $enabled,
        public SignatureAlgorithm $algorithm,
        public SignatureComponentSet $components,
        public KeyReference $key,
        public int $lifetimeSeconds,
    ) {
    }
}
```

---

# 869. SignatureAlgorithm

```php
enum SignatureAlgorithm: string
{
    case Ed25519 = 'ed25519';
    case EcdsaP256Sha256 = 'ecdsa-p256-sha256';
    case RsaPssSha512 = 'rsa-pss-sha512';
    case HmacSha256 = 'hmac-sha256';
}
```

---

# 870. Algorithm selection

Se preferirán firmas asimétricas cuando múltiples consumidores deban verificar sin conocer la clave privada.

---

# 871. HMAC usage

HMAC podrá utilizarse en relaciones cerradas entre sistemas confiables.

No deberá compartirse una clave global entre múltiples tenants o integraciones independientes.

---

# 872. Signed components

Una firma podrá cubrir:

* status;
* method original;
* authority;
* path;
* content type;
* content digest;
* date;
* request ID;
* tenant;
* nonce;
* expiración.

---

# 873. SignatureComponentSet

```php
final readonly class SignatureComponentSet
{
    public function __construct(
        public array $derivedComponents,
        public array $headers,
        public bool $includeContentDigest,
    ) {
    }
}
```

---

# 874. Mandatory signed components

Toda respuesta firmada deberá incluir como mínimo:

* identificador de clave;
* fecha de creación;
* expiración;
* content digest cuando exista body;
* contexto suficiente para impedir sustitución.

---

# 875. Request-response binding

Una respuesta firmada podrá vincularse al request mediante:

* request ID;
* challenge;
* nonce;
* method;
* route;
* client identifier.

---

# 876. Cross-request substitution

Sin binding, una respuesta válida podría reutilizarse en otro contexto.

---

# 877. Tenant binding

En sistemas multi-tenant, la identidad del tenant deberá formar parte del material firmado cuando sea relevante.

---

# 878. User binding

Las respuestas personalizadas de alto valor podrán firmarse para un usuario o sesión concreta.

---

# 879. SignatureInputBuilder

```php
interface SignatureInputBuilderInterface
{
    public function build(
        SecureHttpResponse $response,
        SignaturePolicy $policy,
        ResponseSecurityContext $context
    ): CanonicalSignatureInput;
}
```

---

# 880. Canonicalization

La canonicalización deberá ser:

* determinista;
* versionada;
* independiente de orden accidental;
* resistente a whitespace ambiguo;
* compatible entre lenguajes.

---

# 881. Signature header ownership

Solo el motor de integridad podrá emitir headers de firma.

---

# 882. Signature key identifiers

El identificador de clave no deberá exponer:

* rutas internas;
* nombres de archivos;
* secretos;
* detalles de HSM;
* nombres de usuarios.

---

# 883. Key lifecycle

Las claves deberán tener:

* fecha de activación;
* fecha de expiración;
* estado;
* propósito;
* algoritmo;
* propietario;
* versión.

---

# 884. Key rotation

La rotación deberá permitir una ventana de verificación de respuestas previamente emitidas.

---

# 885. Key revocation

Una clave comprometida deberá poder revocarse inmediatamente.

---

# 886. Verification key publication

Las claves públicas podrán distribuirse mediante:

* endpoint controlado;
* JWKS;
* manifest firmado;
* configuración out-of-band.

---

# 887. Key isolation

Las claves de respuesta deberán separarse de:

* claves de sesión;
* claves CSRF;
* claves de cifrado de cookies;
* claves de firma de URLs;
* claves de autenticación.

---

# 888. Hardware-backed keys

Perfiles de alta seguridad podrán utilizar:

* HSM;
* KMS;
* secure enclave;
* servicio de firma remoto.

---

# 889. Signing failures

Si una respuesta requiere firma y el servicio de firma falla, la respuesta no deberá emitirse sin protección.

---

# 890. Signature downgrade

No deberá degradarse silenciosamente de `Signed` a `DigestOnly`.

---

# 891. Signature verification telemetry

Los consumidores integrados podrán reportar:

* firma válida;
* expiración;
* clave desconocida;
* digest incorrecto;
* contexto incorrecto;
* replay.

---

# 892. Replay Protection

Una firma válida no siempre impide replay.

---

# 893. FreshnessPolicy

```php
final readonly class FreshnessPolicy
{
    public function __construct(
        public int $maxAgeSeconds,
        public bool $requireNonce,
        public bool $singleUse,
        public ClockSkewPolicy $clockSkew,
    ) {
    }
}
```

---

# 894. Created and expires

Las respuestas firmadas deberán incluir una ventana temporal explícita.

---

# 895. Clock skew

La tolerancia de reloj deberá ser limitada y configurable.

---

# 896. Nonce registry

```php
interface ResponseNonceRegistryInterface
{
    public function issue(
        ResponseSecurityContext $context
    ): ResponseNonce;

    public function consume(
        ResponseNonce $nonce
    ): NonceConsumptionResult;
}
```

---

# 897. Single-use responses

Podrán utilizarse para:

* confirmaciones financieras;
* enlaces de descarga;
* entrega de secretos;
* autorizaciones temporales.

---

# 898. Replay storage

El registro de nonces deberá:

* ser distribuido cuando aplique;
* expirar;
* ser atómico;
* resistir carreras;
* aislar tenants.

---

# 899. Context Binding Policy

```php
final readonly class ContextBindingPolicy
{
    public function __construct(
        public bool $bindRequestId,
        public bool $bindTenant,
        public bool $bindUser,
        public bool $bindRoute,
        public bool $bindOrigin,
    ) {
    }
}
```

---

# 900. Response Provenance

Toda respuesta deberá poder asociarse internamente a su origen de ejecución.

---

# 901. ResponseProvenance

```php
final readonly class ResponseProvenance
{
    public function __construct(
        public string $applicationId,
        public string $releaseId,
        public string $nodeId,
        public string $requestId,
        public string $routeId,
        public ?string $controllerId,
        public DateTimeImmutable $generatedAt,
    ) {
    }
}
```

---

# 902. Provenance exposure

No toda metadata de procedencia deberá enviarse al cliente.

---

# 903. Public provenance

Podrán exponerse únicamente identificadores no sensibles y necesarios para soporte.

---

# 904. Internal provenance

El detalle completo permanecerá en:

* tracing;
* audit logs;
* security events;
* incident records.

---

# 905. Release binding

Las respuestas firmadas de alto valor podrán vincularse al release que las produjo.

---

# 906. Node anomaly detection

Si respuestas equivalentes difieren entre nodos, deberá investigarse:

* configuración divergente;
* despliegue parcial;
* cache inconsistente;
* compromiso;
* errores de serialización.

---

# 907. Transport Audit System

VoltStack deberá mantener un sistema de auditoría específico para seguridad de transporte y respuesta.

---

# 908. TransportAuditRecord

```php
final readonly class TransportAuditRecord
{
    public function __construct(
        public string $requestId,
        public string $routeId,
        public string $responseProfile,
        public int $status,
        public ResponseSensitivity $sensitivity,
        public array $securityHeaders,
        public string $cacheDecision,
        public string $integrityDecision,
        public string $transportState,
        public array $violations,
    ) {
    }
}
```

---

# 909. Audit objectives

El sistema deberá permitir responder:

* qué política se aplicó;
* por qué se aplicó;
* qué componente la decidió;
* si existieron overrides;
* si hubo conflictos;
* qué headers fueron emitidos;
* si la respuesta se completó.

---

# 910. Audit data minimization

Los registros no deberán incluir automáticamente:

* bodies;
* tokens;
* cookies completas;
* headers de autorización;
* datos personales.

---

# 911. Header audit representation

Los headers sensibles deberán representarse mediante:

* presencia;
* clasificación;
* hash seguro cuando sea necesario;
* valor redactado.

---

# 912. Audit immutability

Los registros de seguridad deberán protegerse contra modificación no autorizada.

---

# 913. Audit retention

La retención dependerá de:

* clasificación;
* regulación;
* capacidad de almacenamiento;
* necesidades forenses;
* privacidad.

---

# 914. Audit correlation

Los registros deberán correlacionarse con:

* request tracing;
* authentication;
* authorization;
* tenant;
* deployment;
* proxy chain;
* incident ID.

---

# 915. Security Telemetry

La telemetría deberá detectar desviaciones antes de convertirse en incidentes.

---

# 916. Transport metrics

Métricas recomendadas:

* respuestas por perfil;
* fallos de header validation;
* redirects rechazados;
* cookies rechazadas;
* fallos CSRF;
* CORS denegados;
* framing inválido;
* errores de streaming;
* cache policy violations;
* firmas fallidas.

---

# 917. Security metric labels

Las labels deberán evitar cardinalidad excesiva.

---

# 918. Safe metric dimensions

Podrán utilizarse:

* route group;
* response profile;
* status family;
* tenant tier;
* violation type;
* deployment region.

---

# 919. Unsafe metric dimensions

Deberán evitarse:

* URL completa;
* user ID sin anonimizar;
* token;
* query string arbitraria;
* filename libre;
* stack trace.

---

# 920. SecurityEventBus

```php
interface SecurityEventBusInterface
{
    public function publish(
        SecurityEvent $event
    ): void;
}
```

---

# 921. Transport security events

El framework deberá incluir eventos como:

* `InvalidResponseHeaderDetected`;
* `UnsafeRedirectBlocked`;
* `CorsPolicyViolation`;
* `CsrfValidationFailed`;
* `CookiePolicyViolation`;
* `StreamAborted`;
* `SmugglingAttemptDetected`;
* `HostValidationFailed`;
* `CacheIsolationViolation`;
* `ResponseSignatureFailed`;
* `IntegrityMismatchDetected`.

---

# 922. Security event severity

```php
enum SecurityEventSeverity: string
{
    case Informational = 'informational';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
```

---

# 923. Severity resolution

La severidad deberá considerar:

* sensibilidad;
* repetición;
* endpoint;
* autenticación;
* tenant;
* impacto;
* evidencia de explotación.

---

# 924. Alert thresholds

No todo evento deberá producir una alerta inmediata.

El sistema deberá soportar:

* agregación;
* ventanas temporales;
* rate thresholds;
* anomaly detection;
* suppression controlada.

---

# 925. Threat Intelligence Hooks

VoltStack podrá exponer hooks para enriquecer eventos con inteligencia externa.

---

# 926. ThreatIntelligenceProvider

```php
interface ThreatIntelligenceProviderInterface
{
    public function assess(
        ThreatObservation $observation
    ): ThreatAssessment;
}
```

---

# 927. Threat observations

Podrán incluir:

* IP;
* ASN;
* user agent;
* firma de payload;
* patrón de headers;
* origen;
* frecuencia;
* proxy anomalies.

---

# 928. Threat intelligence trust

La inteligencia externa será una señal, no una verdad absoluta.

---

# 929. Automated response

Las respuestas automáticas podrán incluir:

* rate limit;
* challenge;
* bloqueo temporal;
* aislamiento de sesión;
* cierre de stream;
* revocación de token.

No deberán realizar acciones destructivas irreversibles sin política explícita.

---

# 930. Incident Reporting

Los incidentes de transporte deberán generar un expediente estructurado.

---

# 931. TransportSecurityIncident

```php
final readonly class TransportSecurityIncident
{
    public function __construct(
        public string $incidentId,
        public SecurityEventSeverity $severity,
        public string $category,
        public DateTimeImmutable $detectedAt,
        public array $affectedRequests,
        public array $affectedTenants,
        public array $evidenceReferences,
        public IncidentStatus $status,
    ) {
    }
}
```

---

# 932. IncidentStatus

```php
enum IncidentStatus: string
{
    case Detected = 'detected';
    case Investigating = 'investigating';
    case Contained = 'contained';
    case Remediated = 'remediated';
    case Closed = 'closed';
}
```

---

# 933. Incident containment

Las acciones de contención podrán incluir:

* deshabilitar rutas;
* revocar claves;
* desactivar cache;
* cerrar sesiones;
* bloquear origins;
* retirar un nodo;
* forzar un perfil estricto.

---

# 934. Emergency security profile

VoltStack deberá soportar activar un perfil de emergencia sin modificar cada Controller.

---

# 935. EmergencyTransportProfile

```php
final readonly class EmergencyTransportProfile
{
    public function __construct(
        public bool $disableSharedCache,
        public bool $disableExternalRedirects,
        public bool $disableCompression,
        public bool $forceNoStore,
        public bool $requireStrictHeaders,
        public bool $terminateLongLivedStreams,
    ) {
    }
}
```

---

# 936. Runtime Observability

El sistema deberá proporcionar observabilidad sin debilitar la seguridad.

---

# 937. Response trace span

Cada respuesta podrá generar spans para:

* normalization;
* security classification;
* header resolution;
* cache decision;
* serialization;
* compression;
* signature;
* emission.

---

# 938. Sensitive trace attributes

Los valores sensibles deberán redactarse antes de enviarse a sistemas de tracing.

---

# 939. Debug mode restrictions

El modo debug nunca deberá:

* omitir headers de seguridad;
* exponer cookies;
* mostrar claves;
* desactivar validación de host;
* permitir framing ambiguo.

---

# 940. Production Hardening Profile

VoltStack deberá proporcionar un perfil endurecido para producción.

```php
enum TransportHardeningProfile: string
{
    case Development = 'development';
    case StandardProduction = 'standard_production';
    case StrictProduction = 'strict_production';
    case Regulated = 'regulated';
}
```

---

# 941. Standard production profile

Deberá habilitar como mínimo:

* HTTPS confiable;
* secure cookies;
* host allowlist;
* trusted proxy validation;
* response header guard;
* CSP baseline;
* no-sniff;
* cache classification;
* CSRF para sesiones;
* CORS deny-by-default;
* debug output suppression.

---

# 942. Strict production profile

Añadirá:

* conflicto de forwarded headers como error;
* external redirects por capability;
* compression restringida;
* cross-origin policies estrictas;
* auditoría extendida;
* fail closed en inconsistencias;
* validación de integridad reforzada.

---

# 943. Regulated profile

Podrá añadir:

* firmas de respuesta;
* retención de auditoría;
* HSM/KMS;
* segregación de claves;
* evidence logging;
* control de cambios;
* revisión de políticas;
* trazabilidad de despliegue.

---

# 944. Compliance Mapping

VoltStack podrá incluir mapas de cumplimiento hacia controles externos.

Estos mapas serán ayudas de ingeniería, no certificaciones automáticas.

---

# 945. OWASP ASVS mapping

El modelo deberá mapear, entre otros:

* validación HTTP;
* gestión de sesiones;
* control de acceso;
* seguridad de archivos;
* protección de datos;
* comunicaciones;
* configuración;
* logging.

---

# 946. NIST mapping

Podrán documentarse relaciones con funciones como:

* Identify;
* Protect;
* Detect;
* Respond;
* Recover.

---

# 947. PCI-oriented profile

Aplicaciones que procesen datos de pago deberán reforzar:

* no-store;
* TLS;
* logging protegido;
* segregación;
* sesiones;
* claves;
* integridad;
* incident response.

---

# 948. Production Hardening Checklist

Antes de producción deberá verificarse:

```text
[ ] HTTPS obligatorio
[ ] HSTS configurado
[ ] Proxies confiables registrados
[ ] Forwarded headers validados
[ ] Hosts permitidos definidos
[ ] URLs absolutas no dependen de Host arbitrario
[ ] Cookies Secure, HttpOnly y SameSite
[ ] CSRF activo para autenticación por cookies
[ ] CORS deny-by-default
[ ] CSP activa
[ ] Permissions Policy mínima
[ ] COOP, COEP y CORP evaluados
[ ] Cache privado y público correctamente clasificado
[ ] Respuestas sensibles con no-store
[ ] Redirects externos restringidos
[ ] Descargas con rutas y nombres validados
[ ] Streams con límites y cancelación
[ ] SSE con reautorización
[ ] Range requests limitados
[ ] Compresión evaluada para secretos
[ ] Content-Length y Transfer-Encoding controlados
[ ] Errores y warnings fuera del body
[ ] Auditoría y métricas activas
[ ] Claves rotables y segregadas
[ ] Perfil de emergencia disponible
[ ] Pruebas de seguridad automatizadas
```

---

# 949. Security ADRs

## ADR-114 — Response integrity is a transport concern

La integridad final deberá resolverse después de serializar y transformar la representación.

## ADR-115 — Digests do not replace authentication

Un digest solo demuestra igualdad, no identidad.

## ADR-116 — Signed responses require contextual binding

Toda firma de alto valor deberá vincularse a su contexto.

## ADR-117 — Key purposes must remain isolated

Las claves de respuesta no se reutilizarán para sesiones, cookies o URLs.

## ADR-118 — Signature downgrade is forbidden

Una respuesta que requiera firma no podrá emitirse sin ella.

## ADR-119 — Audit logs must minimize sensitive data

La observabilidad nunca justificará registrar secretos.

## ADR-120 — Transport policies are centrally resolved

Los Controllers no deberán reconstruir políticas de transporte.

## ADR-121 — Emergency controls operate above Controllers

El framework deberá poder endurecer respuestas globalmente.

## ADR-122 — Compliance mappings are not certifications

Las matrices de controles serán herramientas documentales.

## ADR-123 — Debug mode cannot weaken critical transport controls

El entorno de desarrollo podrá aumentar visibilidad, no reducir garantías esenciales.

## ADR-124 — Security telemetry must be bounded

Métricas y eventos deberán controlar cardinalidad y volumen.

## ADR-125 — Threat intelligence is advisory

Las señales externas no sustituirán validación local.

## ADR-126 — Integrity violations fail closed

Una discrepancia crítica deberá impedir la entrega.

## ADR-127 — Long-lived connections remain continuously authorized

La autorización inicial no será suficiente para streams prolongados.

## ADR-128 — Response provenance is internal by default

Solo se expondrá metadata mínima y deliberada.

## ADR-129 — Production hardening is profile-driven

Los controles deberán activarse mediante perfiles consistentes y verificables.

## ADR-130 — Transport security is verified before emission

Ninguna respuesta será emitida antes de completar la validación final aplicable.

---

# 950. Conclusión de CONTROLLER_SECURITY_MODEL_PART_04

`CONTROLLER_SECURITY_MODEL_PART_04.md` define el modelo completo de seguridad de transporte y respuestas de VoltStack.

La arquitectura resultante establece el flujo:

```text
Controller Result
        ↓
Result Normalization
        ↓
Response Classification
        ↓
Security Policy Resolution
        ↓
Content-Type and Negotiation
        ↓
HTML, CSP and Trusted Types
        ↓
Cross-Origin Security
        ↓
CORS and CSRF
        ↓
Cookie Protection
        ↓
Redirect and File Security
        ↓
Streaming and SSE Controls
        ↓
Compression and Framing
        ↓
Proxy, Host and Scheme Validation
        ↓
Cache and Conditional Requests
        ↓
Integrity and Signature Protection
        ↓
Audit and Observability
        ↓
Final Response Freeze
        ↓
Secure Transport Emission
```

Los principios definitivos de esta parte son:

```text
1. Toda respuesta se considera insegura hasta ser clasificada.
2. Los Controllers expresan intención, no construyen transporte crítico.
3. Los headers sensibles tienen propietarios únicos.
4. Toda metadata externa se trata como no confiable.
5. Las respuestas privadas nunca deben entrar en caches compartidos.
6. El host, scheme, port e IP efectiva requieren resolución confiable.
7. Los streams mantienen autorización durante toda su vida.
8. La integridad se calcula sobre la representación correcta.
9. Las firmas requieren contexto, frescura y rotación de claves.
10. La observabilidad nunca debe filtrar secretos.
11. Las inconsistencias críticas fallan de forma cerrada.
12. La seguridad final se valida antes de emitir el primer byte.
```

## Estado final

```text
CONTROLLER_SECURITY_MODEL_PART_04
Transport & Response Security

Secciones: 1–950
Entregas: 10
Estado: COMPLETADO
```

# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 1 de varias
**Cobertura:** Secciones **1–100**
**Estado:** En desarrollo
**Continuación conceptual de:** `CONTROLLER_SECURITY_MODEL_PART_04.md`

---

# 1. Introducción

`CONTROLLER_SECURITY_MODEL_PART_05.md` define la arquitectura de seguridad para:

* identidad;
* autenticación;
* credenciales;
* sesiones;
* dispositivos;
* autenticación multifactor;
* recuperación de cuentas;
* federación;
* tokens;
* identidades de servicio;
* impersonación;
* auditoría de acceso.

El objetivo es que los Controllers de VoltStack nunca tengan que implementar directamente lógica criptográfica, validación de credenciales o administración de sesiones.

---

# 2. Principio fundamental

Un Controller no autentica usuarios.

Un Controller declara el nivel de identidad y autenticación requerido para ejecutar una acción.

```text
Request
   ↓
Identity Resolution
   ↓
Authentication
   ↓
Session Validation
   ↓
Risk Evaluation
   ↓
Authorization
   ↓
Controller
```

---

# 3. Separación de responsabilidades

VoltStack distinguirá claramente:

* identificación;
* autenticación;
* autorización;
* gestión de sesión;
* elevación de privilegios;
* federación de identidad;
* auditoría.

---

# 4. Identification

La identificación responde:

> ¿Qué identidad afirma representar el actor?

Ejemplos:

* usuario;
* dispositivo;
* servicio;
* aplicación;
* proceso;
* tenant;
* API client.

---

# 5. Authentication

La autenticación responde:

> ¿Qué evidencia existe de que el actor controla esa identidad?

---

# 6. Authorization

La autorización responde:

> ¿Qué acciones puede realizar la identidad autenticada?

Autenticación y autorización deberán permanecer desacopladas.

---

# 7. Identity Security Pipeline

```text
Incoming Request
      ↓
Credential Extraction
      ↓
Credential Classification
      ↓
Identity Lookup
      ↓
Credential Verification
      ↓
Authentication Policy Evaluation
      ↓
Risk Analysis
      ↓
Authentication Result
      ↓
Session or Token Binding
      ↓
Authorization Context
      ↓
Controller Dispatch
```

---

# 8. Security goals

El sistema deberá garantizar:

* autenticidad razonable;
* resistencia a robo de credenciales;
* resistencia a replay;
* protección de sesiones;
* aislamiento multi-tenant;
* trazabilidad;
* revocación;
* mínima exposición de identidad;
* extensibilidad controlada.

---

# 9. Threat model

El modelo considerará ataques como:

* credential stuffing;
* password spraying;
* brute force;
* phishing;
* session fixation;
* session hijacking;
* token theft;
* token replay;
* MFA fatigue;
* recovery abuse;
* account enumeration;
* identity confusion;
* tenant confusion;
* OAuth mix-up;
* redirect URI manipulation;
* forged service identities.

---

# 10. Protected assets

Los activos protegidos incluyen:

* contraseñas;
* hashes;
* session IDs;
* refresh tokens;
* access tokens;
* recovery codes;
* MFA secrets;
* device credentials;
* signing keys;
* authentication state;
* identity mappings;
* audit trails.

---

# 11. Trust boundaries

```text
Browser
   ↓
Edge / Proxy
   ↓
HTTP Runtime
   ↓
Authentication System
   ↓
Identity Provider
   ↓
Session Store
   ↓
Application
   ↓
Protected Resources
```

---

# 12. Identity types

```php
enum IdentityType: string
{
    case HumanUser = 'human_user';
    case ServiceAccount = 'service_account';
    case Application = 'application';
    case Device = 'device';
    case Anonymous = 'anonymous';
    case SystemProcess = 'system_process';
}
```

---

# 13. IdentityIdentifier

```php
final readonly class IdentityIdentifier
{
    public function __construct(
        public IdentityType $type,
        public string $provider,
        public string $subject,
    ) {
    }
}
```

---

# 14. Stable subject identifiers

El identificador interno deberá ser:

* estable;
* opaco;
* no reciclable;
* independiente del email;
* independiente del username;
* único dentro del provider.

---

# 15. Mutable identity attributes

No deberán utilizarse como clave primaria de identidad:

* email;
* teléfono;
* username;
* display name;
* nombre legal.

---

# 16. Identity provider

```php
interface IdentityProviderInterface
{
    public function resolve(
        IdentityLookup $lookup
    ): ?IdentityRecord;
}
```

---

# 17. IdentityRecord

```php
final readonly class IdentityRecord
{
    public function __construct(
        public IdentityIdentifier $identifier,
        public IdentityStatus $status,
        public array $attributes,
        public array $authenticationMethods,
        public ?string $tenantId,
    ) {
    }
}
```

---

# 18. IdentityStatus

```php
enum IdentityStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Suspended = 'suspended';
    case Locked = 'locked';
    case Disabled = 'disabled';
    case Deleted = 'deleted';
}
```

---

# 19. Status validation

Solo identidades `Active` podrán autenticarse normalmente.

---

# 20. Pending identities

Las cuentas pendientes podrán acceder únicamente a flujos limitados como:

* verificación de email;
* activación;
* aceptación de invitación;
* configuración inicial.

---

# 21. Suspended identities

Una cuenta suspendida deberá:

* rechazar nuevas autenticaciones;
* invalidar sesiones según política;
* impedir refresh;
* generar evento de seguridad.

---

# 22. Locked identities

El bloqueo podrá ser:

* temporal;
* administrativo;
* por riesgo;
* por intentos fallidos;
* por incidente.

---

# 23. Disabled identities

Una identidad deshabilitada deberá considerarse no autenticable.

---

# 24. Deleted identities

Los identificadores eliminados no deberán reutilizarse automáticamente.

---

# 25. Identity context

```php
final readonly class IdentityContext
{
    public function __construct(
        public IdentityIdentifier $identity,
        public AuthenticationState $authentication,
        public ?string $tenantId,
        public ActorContext $actor,
    ) {
    }
}
```

---

# 26. Actor versus subject

VoltStack distinguirá:

* actor: quien ejecuta la acción;
* subject: identidad sobre la que actúa.

Esto será esencial para:

* impersonación;
* administración;
* automatización;
* delegación.

---

# 27. ActorContext

```php
final readonly class ActorContext
{
    public function __construct(
        public IdentityIdentifier $actor,
        public IdentityIdentifier $subject,
        public bool $impersonating,
        public ?string $delegationId,
    ) {
    }
}
```

---

# 28. Authentication methods

VoltStack podrá soportar:

* password;
* passkey;
* WebAuthn;
* TOTP;
* email link;
* SMS OTP;
* recovery code;
* OAuth;
* OpenID Connect;
* client certificate;
* API key;
* signed request;
* workload identity.

---

# 29. AuthenticationMethod

```php
enum AuthenticationMethod: string
{
    case Password = 'password';
    case Passkey = 'passkey';
    case WebAuthn = 'webauthn';
    case Totp = 'totp';
    case EmailOtp = 'email_otp';
    case SmsOtp = 'sms_otp';
    case MagicLink = 'magic_link';
    case RecoveryCode = 'recovery_code';
    case OAuth = 'oauth';
    case OpenIdConnect = 'openid_connect';
    case ApiKey = 'api_key';
    case ClientCertificate = 'client_certificate';
    case SignedRequest = 'signed_request';
}
```

---

# 30. Authentication factor categories

```php
enum AuthenticationFactorCategory: string
{
    case Knowledge = 'knowledge';
    case Possession = 'possession';
    case Inherence = 'inherence';
    case Device = 'device';
    case Federation = 'federation';
}
```

---

# 31. Factor strength

No todos los métodos poseen el mismo nivel de confianza.

---

# 32. Authentication strength

```php
enum AuthenticationStrength: int
{
    case Anonymous = 0;
    case Low = 10;
    case Standard = 20;
    case Strong = 30;
    case PhishingResistant = 40;
    case HardwareBound = 50;
}
```

---

# 33. Authentication Assurance Level

```php
enum AuthenticationAssuranceLevel: string
{
    case Aal0 = 'aal0';
    case Aal1 = 'aal1';
    case Aal2 = 'aal2';
    case Aal3 = 'aal3';
}
```

---

# 34. Assurance calculation

El assurance level deberá derivarse de:

* método;
* cantidad de factores;
* independencia de factores;
* dispositivo;
* autenticador;
* riesgo;
* antigüedad de autenticación.

---

# 35. AuthenticationState

```php
final readonly class AuthenticationState
{
    public function __construct(
        public bool $authenticated,
        public AuthenticationAssuranceLevel $assurance,
        public AuthenticationStrength $strength,
        public array $methods,
        public DateTimeImmutable $authenticatedAt,
        public ?DateTimeImmutable $elevatedAt,
    ) {
    }
}
```

---

# 36. Anonymous identity

Las peticiones sin autenticación deberán usar una identidad anónima explícita.

---

# 37. Anonymous context

```php
IdentityIdentifier(
    type: IdentityType::Anonymous,
    provider: 'voltstack',
    subject: 'anonymous',
);
```

---

# 38. No nullable identity context

El sistema deberá evitar representar el anonimato mediante `null` disperso en toda la aplicación.

---

# 39. Authentication policy

```php
interface AuthenticationPolicyInterface
{
    public function evaluate(
        AuthenticationAttempt $attempt,
        AuthenticationSecurityContext $context
    ): AuthenticationPolicyDecision;
}
```

---

# 40. AuthenticationAttempt

```php
final readonly class AuthenticationAttempt
{
    public function __construct(
        public AuthenticationMethod $method,
        public CredentialEnvelope $credentials,
        public RequestFingerprint $request,
        public ?IdentityLookup $claimedIdentity,
    ) {
    }
}
```

---

# 41. CredentialEnvelope

```php
final readonly class CredentialEnvelope
{
    public function __construct(
        public CredentialType $type,
        private SensitiveValue $value,
        public array $metadata,
    ) {
    }
}
```

---

# 42. SensitiveValue

Las credenciales deberán almacenarse temporalmente en un tipo sensible.

```php
final class SensitiveValue
{
    public function reveal(
        SensitiveValueAccessToken $token
    ): string;
}
```

---

# 43. Credential redaction

Las credenciales nunca deberán aparecer en:

* logs;
* excepciones;
* traces;
* dumps;
* profiler;
* telemetry;
* serialization.

---

# 44. Credential lifetime

Las credenciales en memoria deberán mantenerse únicamente durante la verificación.

---

# 45. Credential zeroization

Cuando el runtime lo permita, los buffers sensibles deberán limpiarse después del uso.

---

# 46. Credential extraction

La extracción deberá depender del método:

* body;
* header;
* cookie;
* TLS metadata;
* authorization header;
* WebAuthn payload.

---

# 47. CredentialExtractor

```php
interface CredentialExtractorInterface
{
    public function supports(
        ServerRequestInterface $request
    ): bool;

    public function extract(
        ServerRequestInterface $request
    ): CredentialEnvelope;
}
```

---

# 48. Multiple credentials

Una petición con múltiples esquemas incompatibles deberá rechazarse o resolverse mediante política explícita.

---

# 49. Credential precedence

No deberá existir precedencia implícita entre:

* session cookie;
* bearer token;
* API key;
* client certificate.

---

# 50. Authentication scheme selection

```php
interface AuthenticationSchemeResolverInterface
{
    public function resolve(
        ServerRequestInterface $request,
        RouteAuthenticationMetadata $metadata
    ): AuthenticationScheme;
}
```

---

# 51. AuthenticationScheme

```php
final readonly class AuthenticationScheme
{
    public function __construct(
        public string $name,
        public AuthenticationMethod $method,
        public CredentialExtractorInterface $extractor,
        public CredentialVerifierInterface $verifier,
    ) {
    }
}
```

---

# 52. Route authentication metadata

```php
#[RequiresAuthentication(
    assurance: AuthenticationAssuranceLevel::Aal2,
    methods: [
        AuthenticationMethod::Passkey,
        AuthenticationMethod::Password,
    ]
)]
public function billing(): Response
{
}
```

---

# 53. Controller requirements

Los Controllers podrán declarar:

* autenticación requerida;
* assurance mínimo;
* métodos aceptados;
* frescura;
* step-up;
* dispositivo confiable.

---

# 54. Authentication freshness

Algunas operaciones deberán exigir autenticación reciente.

---

# 55. FreshAuthenticationRequirement

```php
final readonly class FreshAuthenticationRequirement
{
    public function __construct(
        public int $maxAgeSeconds,
        public AuthenticationAssuranceLevel $minimumAssurance,
    ) {
    }
}
```

---

# 56. Sensitive operations

Podrán requerir autenticación reciente:

* cambio de contraseña;
* cambio de email;
* gestión de MFA;
* pagos;
* retiro de fondos;
* exportación de datos;
* impersonación;
* eliminación de cuenta.

---

# 57. Step-up authentication

Una sesión válida podrá necesitar elevar temporalmente su assurance.

---

# 58. StepUpAuthenticationService

```php
interface StepUpAuthenticationServiceInterface
{
    public function begin(
        IdentityContext $identity,
        StepUpRequirement $requirement
    ): StepUpChallenge;

    public function verify(
        StepUpResponse $response
    ): StepUpResult;
}
```

---

# 59. Step-up scope

La elevación podrá estar vinculada a:

* sesión;
* operación;
* recurso;
* tenant;
* ventana temporal.

---

# 60. Global elevation risk

Una elevación no deberá aumentar indefinidamente todos los privilegios de la sesión.

---

# 61. Authentication challenge

```php
final readonly class AuthenticationChallenge
{
    public function __construct(
        public string $challengeId,
        public AuthenticationMethod $method,
        public DateTimeImmutable $expiresAt,
        public string $purpose,
        public array $metadata,
    ) {
    }
}
```

---

# 62. Challenge properties

Todo challenge deberá ser:

* impredecible;
* temporal;
* vinculado al flujo;
* limitado a una identidad;
* de un solo uso cuando aplique.

---

# 63. Challenge registry

```php
interface AuthenticationChallengeRegistryInterface
{
    public function issue(
        AuthenticationChallenge $challenge
    ): void;

    public function consume(
        string $challengeId
    ): ChallengeConsumptionResult;
}
```

---

# 64. Challenge replay

Los challenges consumidos deberán rechazarse.

---

# 65. Challenge expiration

Los challenges expirados deberán eliminarse o invalidarse.

---

# 66. Authentication result

```php
final readonly class AuthenticationResult
{
    public function __construct(
        public AuthenticationResultStatus $status,
        public ?IdentityContext $identity,
        public ?AuthenticationFailure $failure,
        public array $securityEvents,
    ) {
    }
}
```

---

# 67. AuthenticationResultStatus

```php
enum AuthenticationResultStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case ChallengeRequired = 'challenge_required';
    case StepUpRequired = 'step_up_required';
    case Locked = 'locked';
    case Suspended = 'suspended';
}
```

---

# 68. Generic authentication failures

La respuesta externa no deberá revelar si falló:

* username;
* password;
* tenant;
* MFA;
* estado de cuenta.

---

# 69. Internal failure classification

Internamente sí deberán diferenciarse las causas para:

* auditoría;
* seguridad;
* soporte;
* métricas;
* respuesta automática.

---

# 70. AuthenticationFailure

```php
enum AuthenticationFailure: string
{
    case IdentityNotFound = 'identity_not_found';
    case CredentialMismatch = 'credential_mismatch';
    case AccountLocked = 'account_locked';
    case AccountSuspended = 'account_suspended';
    case FactorRequired = 'factor_required';
    case ChallengeExpired = 'challenge_expired';
    case ReplayDetected = 'replay_detected';
    case RiskRejected = 'risk_rejected';
    case ProviderUnavailable = 'provider_unavailable';
}
```

---

# 71. Account enumeration prevention

El sistema deberá evitar diferencias observables en:

* mensaje;
* status;
* tiempo;
* redirects;
* respuesta;
* rate limit.

---

# 72. Timing normalization

Los intentos con identidad inexistente deberán ejecutar una operación de verificación simulada cuando sea razonable.

---

# 73. Dummy password hash

El framework podrá mantener un hash dummy para reducir diferencias de timing.

---

# 74. Authentication rate limiting

Los límites deberán considerar:

* IP;
* identidad reclamada;
* dispositivo;
* tenant;
* ASN;
* patrón global.

---

# 75. AuthRateLimitKey

```php
final readonly class AuthRateLimitKey
{
    public function __construct(
        public ?string $identityHash,
        public ?string $ipPrefix,
        public ?string $deviceId,
        public ?string $tenantId,
    ) {
    }
}
```

---

# 76. IP-only rate limiting risk

Limitar únicamente por IP puede afectar redes compartidas y ser evadido mediante botnets.

---

# 77. Identity-only rate limiting risk

Limitar únicamente por identidad puede permitir bloquear cuentas mediante ataques externos.

---

# 78. Composite throttling

VoltStack deberá combinar múltiples señales.

---

# 79. Password spraying detection

Se deberá detectar el patrón:

```text
Una contraseña
    ↓
Muchas identidades
```

---

# 80. Credential stuffing detection

Se deberá detectar:

```text
Muchas credenciales conocidas
    ↓
Muchas identidades
```

---

# 81. Brute-force detection

Se deberá detectar:

```text
Muchas contraseñas
    ↓
Una identidad
```

---

# 82. AuthenticationRiskEngine

```php
interface AuthenticationRiskEngineInterface
{
    public function assess(
        AuthenticationAttempt $attempt,
        AuthenticationSecurityContext $context
    ): AuthenticationRiskAssessment;
}
```

---

# 83. Risk signals

El motor podrá considerar:

* IP reputation;
* geolocation inconsistency;
* device novelty;
* impossible travel;
* request velocity;
* breached credential signal;
* user agent anomalies;
* proxy or Tor usage;
* authentication method;
* previous incidents.

---

# 84. Risk score

```php
final readonly class AuthenticationRiskAssessment
{
    public function __construct(
        public int $score,
        public AuthenticationRiskLevel $level,
        public array $signals,
        public AuthenticationRiskAction $action,
    ) {
    }
}
```

---

# 85. AuthenticationRiskLevel

```php
enum AuthenticationRiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
```

---

# 86. AuthenticationRiskAction

```php
enum AuthenticationRiskAction: string
{
    case Allow = 'allow';
    case Challenge = 'challenge';
    case RequireMfa = 'require_mfa';
    case RequirePhishingResistantFactor = 'require_phishing_resistant_factor';
    case Deny = 'deny';
    case LockTemporarily = 'lock_temporarily';
}
```

---

# 87. Risk engine limitations

El motor de riesgo deberá ser una señal complementaria.

No deberá reemplazar verificaciones criptográficas.

---

# 88. Explainable risk decisions

Las decisiones deberán conservar razones internas auditables.

---

# 89. User-facing risk messages

Los mensajes externos deberán evitar revelar reglas de detección.

---

# 90. Device identity

VoltStack podrá asociar sesiones a dispositivos conocidos.

---

# 91. DeviceIdentifier

```php
final readonly class DeviceIdentifier
{
    public function __construct(
        public string $id,
        public DeviceTrustLevel $trust,
        public DateTimeImmutable $firstSeenAt,
        public DateTimeImmutable $lastSeenAt,
    ) {
    }
}
```

---

# 92. DeviceTrustLevel

```php
enum DeviceTrustLevel: string
{
    case Unknown = 'unknown';
    case Recognized = 'recognized';
    case Trusted = 'trusted';
    case Managed = 'managed';
    case Compromised = 'compromised';
}
```

---

# 93. Device fingerprinting

El fingerprinting pasivo deberá utilizarse con precaución debido a:

* privacidad;
* falsos positivos;
* volatilidad;
* evasión;
* regulación.

---

# 94. Device cookie

Una cookie de dispositivo deberá:

* estar firmada;
* ser opaca;
* no contener datos sensibles;
* poder revocarse;
* tener scope limitado.

---

# 95. Trusted device

Un dispositivo confiable no deberá eliminar completamente la necesidad de autenticación.

---

# 96. Managed devices

Los dispositivos administrados podrán aportar señales como:

* certificado;
* posture;
* enrollment;
* workload identity;
* attestation.

---

# 97. Compromised device

Una señal de compromiso podrá provocar:

* revocación de sesiones;
* MFA obligatorio;
* bloqueo temporal;
* alerta;
* denial.

---

# 98. Authentication events

Eventos iniciales del sistema:

* `AuthenticationAttempted`;
* `AuthenticationSucceeded`;
* `AuthenticationFailed`;
* `AuthenticationChallenged`;
* `StepUpRequired`;
* `IdentityLocked`;
* `RiskAuthenticationRejected`;
* `DeviceRecognized`;
* `DeviceCompromised`.

---

# 99. Principios arquitectónicos de esta entrega

```text
1. Identificación, autenticación y autorización son procesos distintos.
2. Los Controllers declaran requisitos de autenticación.
3. Las credenciales se encapsulan como valores sensibles.
4. Las respuestas de error evitan enumeración de cuentas.
5. El nivel de autenticación se representa explícitamente.
6. La autenticación reciente puede exigirse por operación.
7. El riesgo complementa, pero no sustituye, la criptografía.
8. Los challenges son temporales, vinculados y consumibles.
9. La identidad anónima se representa explícitamente.
10. Actor y subject permanecen separados.
```

---

# 100. Resultado de esta entrega

Esta primera entrega establece:

```text
Identity Trust Model
Identity Types
Identity Providers
Identity Status Lifecycle
Actor and Subject Separation
Authentication Methods
Authentication Strength
Authentication Assurance Levels
Credential Envelopes
Sensitive Credential Handling
Authentication Scheme Resolution
Route Authentication Metadata
Fresh Authentication
Step-Up Authentication
Authentication Challenges
Generic Failure Responses
Account Enumeration Defenses
Rate Limiting Foundations
Credential Stuffing Detection
Risk-Based Authentication
Device Identity Foundations
Authentication Security Events
```

# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 2 de varias
**Cobertura:** Secciones **101–200**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 1`

---

# 101. Password Security Architecture

VoltStack tratará las contraseñas como credenciales de alto riesgo.

El sistema deberá diseñarse para reducir:

* robo de credenciales;
* reutilización;
* cracking offline;
* password spraying;
* credential stuffing;
* filtraciones accidentales;
* abuso de recuperación;
* downgrade criptográfico.

---

# 102. Password subsystem boundaries

El subsistema de contraseñas deberá permanecer separado de:

* Controllers;
* formularios;
* modelos ORM;
* logs;
* serializadores;
* sesiones;
* autorización.

---

# 103. PasswordCredential

```php
final class PasswordCredential
{
    public function __construct(
        private SensitiveValue $value,
    ) {
    }

    public function reveal(
        SensitiveValueAccessToken $token
    ): string {
        return $this->value->reveal($token);
    }
}
```

---

# 104. Password value restrictions

Una contraseña no deberá:

* convertirse automáticamente a string;
* serializarse;
* exportarse;
* incluirse en excepciones;
* persistirse en texto plano;
* almacenarse en request attributes duraderos.

---

# 105. Password lifecycle

```text
Input
  ↓
Sensitive Capture
  ↓
Policy Validation
  ↓
Breach Evaluation
  ↓
Hashing
  ↓
Persistence
  ↓
Zeroization
```

---

# 106. PasswordPolicyEngine

```php
interface PasswordPolicyEngineInterface
{
    public function evaluate(
        PasswordCredential $password,
        PasswordPolicyContext $context
    ): PasswordPolicyDecision;
}
```

---

# 107. PasswordPolicyContext

```php
final readonly class PasswordPolicyContext
{
    public function __construct(
        public PasswordOperation $operation,
        public ?IdentityIdentifier $identity,
        public ?string $tenantId,
        public array $identityAttributes,
        public AuthenticationRiskAssessment $risk,
    ) {
    }
}
```

---

# 108. PasswordOperation

```php
enum PasswordOperation: string
{
    case Registration = 'registration';
    case Change = 'change';
    case Reset = 'reset';
    case AdministrativeSet = 'administrative_set';
    case Migration = 'migration';
}
```

---

# 109. Policy composition

La política deberá poder componerse mediante reglas como:

* longitud mínima;
* longitud máxima;
* resistencia estimada;
* breached password;
* similitud con identidad;
* historial;
* contexto del tenant;
* tipo de operación.

---

# 110. Length-first policy

VoltStack deberá priorizar longitud y resistencia real sobre reglas arbitrarias de composición.

---

# 111. Minimum password length

El mínimo deberá ser configurable por perfil.

Ejemplo:

```php
final readonly class PasswordLengthPolicy
{
    public function __construct(
        public int $minimum,
        public int $maximum,
    ) {
    }
}
```

---

# 112. Maximum password length

Deberá existir un máximo razonable para evitar:

* agotamiento de memoria;
* ataques de CPU;
* cuerpos excesivos;
* abuso de parsers.

El límite no deberá ser tan bajo que impida passphrases.

---

# 113. Unicode passwords

VoltStack deberá definir una política explícita para Unicode.

---

# 114. Unicode normalization

La normalización puede cambiar la semántica de una contraseña.

Por defecto, el framework no deberá aplicar transformaciones invisibles sin una decisión documentada.

---

# 115. Whitespace preservation

No deberán eliminarse automáticamente:

* espacios iniciales;
* espacios finales;
* múltiples espacios internos.

---

# 116. Password composition rules

No deberán exigirse obligatoriamente combinaciones como:

* una mayúscula;
* una minúscula;
* un número;
* un símbolo;

salvo requerimiento regulatorio explícito.

---

# 117. Password strength estimation

El framework podrá integrar un estimador de resistencia basado en:

* longitud;
* patrones;
* secuencias;
* palabras comunes;
* datos personales;
* repeticiones.

---

# 118. PasswordStrengthEstimator

```php
interface PasswordStrengthEstimatorInterface
{
    public function estimate(
        PasswordCredential $password,
        PasswordStrengthContext $context
    ): PasswordStrengthAssessment;
}
```

---

# 119. PasswordStrengthAssessment

```php
final readonly class PasswordStrengthAssessment
{
    public function __construct(
        public int $score,
        public PasswordStrengthLevel $level,
        public array $reasons,
    ) {
    }
}
```

---

# 120. PasswordStrengthLevel

```php
enum PasswordStrengthLevel: string
{
    case VeryWeak = 'very_weak';
    case Weak = 'weak';
    case Acceptable = 'acceptable';
    case Strong = 'strong';
    case VeryStrong = 'very_strong';
}
```

---

# 121. Identity similarity checks

Las contraseñas podrán compararse contra:

* username;
* email local part;
* display name;
* tenant name;
* nombre de la aplicación.

---

# 122. Sensitive comparison data

Los atributos de identidad utilizados para estas comparaciones deberán permanecer protegidos y no registrarse junto a la contraseña.

---

# 123. Password hashing architecture

El hash deberá calcularse exclusivamente mediante un servicio dedicado.

---

# 124. PasswordHasher

```php
interface PasswordHasherInterface
{
    public function hash(
        PasswordCredential $password,
        PasswordHashingProfile $profile
    ): PasswordHash;

    public function verify(
        PasswordCredential $password,
        PasswordHash $hash
    ): PasswordVerificationResult;

    public function needsRehash(
        PasswordHash $hash,
        PasswordHashingProfile $target
    ): bool;
}
```

---

# 125. PasswordHash

```php
final readonly class PasswordHash
{
    public function __construct(
        public string $encoded,
        public string $algorithm,
        public array $parameters,
        public string $profileId,
        public int $version,
    ) {
    }
}
```

---

# 126. Preferred algorithm

VoltStack deberá preferir `Argon2id` cuando esté disponible de forma segura.

---

# 127. PasswordHashingProfile

```php
final readonly class PasswordHashingProfile
{
    public function __construct(
        public string $id,
        public PasswordHashAlgorithm $algorithm,
        public int $memoryCost,
        public int $timeCost,
        public int $threads,
        public int $version,
    ) {
    }
}
```

---

# 128. PasswordHashAlgorithm

```php
enum PasswordHashAlgorithm: string
{
    case Argon2id = 'argon2id';
    case Bcrypt = 'bcrypt';
    case Pbkdf2Sha256 = 'pbkdf2-sha256';
}
```

---

# 129. Algorithm fallback

Los algoritmos alternativos deberán existir solo para:

* compatibilidad;
* migraciones;
* entornos limitados;
* cumplimiento específico.

---

# 130. Argon2id profile selection

Los parámetros deberán calibrarse según:

* hardware;
* concurrencia;
* memoria disponible;
* latencia objetivo;
* entorno;
* riesgo.

---

# 131. Runtime calibration

VoltStack podrá incluir un comando de calibración.

```text
volt security:password-calibrate
```

---

# 132. Calibration goal

La calibración deberá buscar un costo suficientemente alto sin provocar:

* denegación de servicio;
* latencia excesiva;
* saturación de workers;
* incompatibilidad con el runtime.

---

# 133. Profile versioning

Los perfiles deberán versionarse para permitir evolución gradual.

---

# 134. Per-environment profiles

Podrán existir perfiles distintos para:

* desarrollo;
* pruebas;
* producción;
* producción estricta.

Los perfiles de desarrollo nunca deberán migrarse accidentalmente a producción.

---

# 135. Salt management

Cada hash deberá utilizar un salt único generado por el algoritmo.

---

# 136. Manual salts

Los Controllers y la aplicación no deberán proporcionar salts manualmente.

---

# 137. Pepper architecture

VoltStack podrá soportar un pepper adicional almacenado fuera de la base de datos.

---

# 138. PasswordPepperProvider

```php
interface PasswordPepperProviderInterface
{
    public function active(): PasswordPepper;

    public function byVersion(int $version): ?PasswordPepper;
}
```

---

# 139. PasswordPepper

```php
final class PasswordPepper
{
    public function __construct(
        public readonly int $version,
        private SensitiveValue $value,
    ) {
    }
}
```

---

# 140. Pepper storage

El pepper deberá almacenarse en:

* secret manager;
* KMS;
* HSM;
* variable segura de runtime;
* mecanismo equivalente.

No en la misma base de datos que los hashes.

---

# 141. Pepper rotation

La rotación deberá permitir verificar hashes creados con versiones anteriores.

---

# 142. Pepper compromise

Ante compromiso deberá evaluarse:

* rotación;
* invalidación de sesiones;
* rehash progresivo;
* reset forzado;
* análisis de exposición.

---

# 143. Pepper usage modes

El pepper podrá aplicarse:

* antes del hashing;
* como derivación separada;
* mediante HMAC sobre el resultado.

La estrategia deberá ser única y versionada.

---

# 144. Password verification

La verificación deberá usar funciones resistentes a timing attacks.

---

# 145. PasswordVerificationResult

```php
final readonly class PasswordVerificationResult
{
    public function __construct(
        public bool $valid,
        public bool $needsRehash,
        public ?string $matchedProfileId,
        public ?int $matchedPepperVersion,
    ) {
    }
}
```

---

# 146. Verification flow

```text
Credential
   ↓
Resolve Stored Hash
   ↓
Resolve Algorithm
   ↓
Resolve Pepper Version
   ↓
Verify
   ↓
Evaluate Rehash
   ↓
Return Generic Result
```

---

# 147. Rehash on authentication

Después de una autenticación válida, el sistema podrá actualizar el hash si:

* cambió el algoritmo;
* aumentó el costo;
* cambió el pepper;
* cambió la versión del perfil.

---

# 148. Atomic rehash

El rehash deberá actualizarse atómicamente y evitar sobrescribir cambios concurrentes.

---

# 149. Rehash failure

Un fallo al rehash no deberá convertir una autenticación válida en una autenticación inválida, salvo política estricta.

Deberá registrarse para reintento.

---

# 150. Legacy hash migration

VoltStack podrá reconocer hashes heredados mediante adapters limitados.

---

# 151. LegacyHashVerifier

```php
interface LegacyHashVerifierInterface
{
    public function supports(
        PasswordHash $hash
    ): bool;

    public function verify(
        PasswordCredential $password,
        PasswordHash $hash
    ): bool;
}
```

---

# 152. Legacy migration policy

Todo hash heredado validado deberá migrarse al perfil actual tan pronto como sea posible.

---

# 153. Plaintext migration prohibition

Nunca deberá almacenarse temporalmente una contraseña en texto plano para migración futura.

---

# 154. Breached Password Detection

VoltStack deberá poder rechazar contraseñas conocidas por filtraciones.

---

# 155. BreachedPasswordChecker

```php
interface BreachedPasswordCheckerInterface
{
    public function check(
        PasswordCredential $password,
        BreachCheckPolicy $policy
    ): BreachedPasswordResult;
}
```

---

# 156. BreachCheckPolicy

```php
final readonly class BreachCheckPolicy
{
    public function __construct(
        public bool $enabled,
        public BreachCheckMode $mode,
        public int $minimumOccurrences,
        public bool $failClosed,
    ) {
    }
}
```

---

# 157. BreachCheckMode

```php
enum BreachCheckMode: string
{
    case LocalDataset = 'local_dataset';
    case PrivacyPreservingRemote = 'privacy_preserving_remote';
    case Hybrid = 'hybrid';
}
```

---

# 158. Password disclosure prohibition

La contraseña completa nunca deberá enviarse a un proveedor externo.

---

# 159. Privacy-preserving lookup

Las consultas remotas deberán usar un mecanismo que reduzca la exposición, como prefijos de hash o un protocolo equivalente.

---

# 160. Breach provider failure

La política deberá decidir entre:

* permitir;
* rechazar;
* degradar a dataset local;
* solicitar cambio posterior.

---

# 161. Breached password result

```php
final readonly class BreachedPasswordResult
{
    public function __construct(
        public bool $breached,
        public ?int $estimatedOccurrences,
        public BreachResultConfidence $confidence,
    ) {
    }
}
```

---

# 162. Existing breached passwords

Cuando una contraseña existente aparezca en una nueva filtración, VoltStack podrá:

* marcar la cuenta;
* requerir step-up;
* pedir cambio;
* revocar sesiones de riesgo;
* notificar al usuario.

---

# 163. Password history

El sistema podrá impedir reutilización reciente.

---

# 164. PasswordHistoryEntry

```php
final readonly class PasswordHistoryEntry
{
    public function __construct(
        public PasswordHash $hash,
        public DateTimeImmutable $retiredAt,
    ) {
    }
}
```

---

# 165. History comparison

La nueva contraseña deberá verificarse contra hashes históricos usando sus perfiles originales.

---

# 166. History length

La cantidad de hashes retenidos deberá ser configurable y proporcional al riesgo.

---

# 167. History retention risk

Retener demasiados hashes incrementa el material disponible ante una filtración.

---

# 168. Periodic password expiration

VoltStack no deberá forzar cambios periódicos sin evidencia de compromiso, salvo requisito regulatorio.

---

# 169. Password compromise event

Un cambio deberá exigirse cuando exista:

* evidencia de filtración;
* compromiso del proveedor;
* exposición administrativa;
* ataque confirmado;
* incidente de sesión.

---

# 170. Password change workflow

```text
Authenticated User
      ↓
Fresh Authentication
      ↓
Current Password or Strong Factor
      ↓
New Password Policy
      ↓
Breach Check
      ↓
History Check
      ↓
Hash
      ↓
Atomic Update
      ↓
Session Rotation
      ↓
Notification
```

---

# 171. PasswordChangeService

```php
interface PasswordChangeServiceInterface
{
    public function change(
        IdentityContext $identity,
        PasswordChangeCommand $command
    ): PasswordChangeResult;
}
```

---

# 172. PasswordChangeCommand

```php
final readonly class PasswordChangeCommand
{
    public function __construct(
        public PasswordCredential $newPassword,
        public AuthenticationEvidence $evidence,
        public bool $revokeOtherSessions,
    ) {
    }
}
```

---

# 173. Current password verification

La contraseña actual podrá requerirse, salvo que exista una autenticación fuerte y reciente equivalente.

---

# 174. Session handling after change

Después del cambio deberá:

* rotarse la sesión actual;
* revocarse refresh tokens según política;
* revocarse otras sesiones cuando corresponda;
* invalidarse remember-me.

---

# 175. Password change notification

Se deberá notificar por un canal independiente cuando sea posible.

---

# 176. Notification content

La notificación no deberá incluir:

* contraseña;
* reset token;
* hash;
* datos sensibles innecesarios.

---

# 177. Administrative password setting

Los administradores no deberán conocer ni definir contraseñas permanentes de usuarios.

---

# 178. Temporary administrative credentials

Cuando sea indispensable, deberán ser:

* temporales;
* de un solo uso;
* forzar cambio;
* auditadas;
* entregadas por canal seguro.

---

# 179. Password Reset Security

La recuperación de contraseña es un flujo de autenticación y deberá tratarse como tal.

---

# 180. Reset flow

```text
Reset Request
   ↓
Generic Response
   ↓
Identity Resolution
   ↓
Risk Evaluation
   ↓
Token Issuance
   ↓
Out-of-Band Delivery
   ↓
Token Verification
   ↓
New Password Policy
   ↓
Credential Update
   ↓
Session Revocation
```

---

# 181. PasswordResetService

```php
interface PasswordResetServiceInterface
{
    public function request(
        PasswordResetRequest $request
    ): PasswordResetRequestResult;

    public function complete(
        PasswordResetCompletion $completion
    ): PasswordResetResult;
}
```

---

# 182. Generic reset response

La respuesta al solicitar un reset deberá ser equivalente exista o no la cuenta.

---

# 183. ResetToken

```php
final readonly class PasswordResetToken
{
    public function __construct(
        public string $id,
        public SensitiveValue $secret,
        public DateTimeImmutable $expiresAt,
        public string $purpose,
    ) {
    }
}
```

---

# 184. Reset token storage

El secreto completo no deberá almacenarse.

Se persistirá una representación derivada verificable.

---

# 185. Reset token properties

El token deberá ser:

* aleatorio;
* de alta entropía;
* de un solo uso;
* temporal;
* vinculado a identidad;
* vinculado a propósito;
* revocable.

---

# 186. Reset token binding

Podrá vincularse adicionalmente a:

* tenant;
* canal;
* request;
* dispositivo;
* riesgo;
* versión de credenciales.

---

# 187. Credential version binding

Si la contraseña cambia después de emitir el token, los tokens anteriores deberán invalidarse.

---

# 188. Reset token URL

La URL deberá generarse usando:

* host validado;
* HTTPS;
* ruta registrada;
* parámetros codificados;
* origen canónico.

---

# 189. Referrer leakage protection

La página de reset deberá aplicar una política de referrer restrictiva.

---

# 190. Third-party content restriction

Las páginas de recuperación no deberán cargar recursos de terceros innecesarios que puedan observar el token.

---

# 191. Reset completion

Antes de aceptar la nueva contraseña deberán validarse:

* token;
* expiración;
* consumo;
* identidad;
* estado;
* política;
* riesgo.

---

# 192. Reset token consumption

El consumo deberá ser atómico para evitar uso concurrente.

---

# 193. Post-reset actions

Después del reset deberán considerarse:

* revocar todas las sesiones;
* revocar refresh tokens;
* eliminar trusted devices;
* invalidar recovery links;
* notificar al usuario;
* registrar evento de seguridad.

---

# 194. Email Verification

La verificación de email no equivale necesariamente a autenticación completa.

---

# 195. EmailVerificationToken

```php
final readonly class EmailVerificationToken
{
    public function __construct(
        public string $identityId,
        public string $emailVersion,
        public SensitiveValue $secret,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

# 196. Email version binding

Si cambia el email antes de verificarse, el token anterior deberá quedar inválido.

---

# 197. Magic Links

Un magic link actúa como credencial temporal.

---

# 198. MagicLinkPolicy

```php
final readonly class MagicLinkPolicy
{
    public function __construct(
        public int $lifetimeSeconds,
        public bool $singleUse,
        public AuthenticationAssuranceLevel $resultingAssurance,
        public bool $bindDevice,
        public bool $requireConfirmation,
    ) {
    }
}
```

---

# 199. Magic link security

Los magic links deberán:

* expirar rápidamente;
* ser de un solo uso;
* evitar exposición en logs;
* usar HTTPS;
* vincularse a propósito;
* limitar el assurance resultante;
* requerir confirmación cuando el riesgo lo justifique.

---

# 200. Resultado de esta entrega

Esta entrega establece:

```text
Password Security Architecture
Password Policy Engine
Length and Strength Rules
Unicode and Whitespace Policy
Argon2id Hashing Profiles
Hash Calibration
Salt and Pepper Management
Password Verification
Automatic Rehashing
Legacy Hash Migration
Breached Password Detection
Password History
Password Change Workflow
Administrative Credential Restrictions
Password Reset Security
Reset Token Binding
Email Verification Foundations
Magic Link Security Foundations
```

# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 3 de varias
**Cobertura:** Secciones **201–300**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 2`

---

# 201. One-Time Password Architecture

Los códigos de un solo uso son credenciales temporales diseñadas para autenticar una operación o identidad durante una ventana limitada.

VoltStack distinguirá entre:

* códigos enviados por canal;
* códigos generados por autenticador;
* códigos basados en tiempo;
* códigos basados en contador;
* códigos de recuperación;
* códigos de aprobación transaccional.

---

# 202. OTP security goals

El subsistema OTP deberá garantizar:

* alta entropía suficiente;
* expiración corta;
* consumo único;
* limitación de intentos;
* vinculación a identidad;
* vinculación a propósito;
* resistencia a replay;
* auditoría;
* aislamiento multi-tenant.

---

# 203. OTP threat model

El modelo deberá considerar:

* adivinación;
* interceptación;
* SIM swapping;
* compromiso del correo;
* replay;
* race conditions;
* brute force distribuido;
* social engineering;
* MFA fatigue;
* redirección de mensajes;
* abuso de recuperación.

---

# 204. OtpType

```php
enum OtpType: string
{
    case Email = 'email';
    case Sms = 'sms';
    case Totp = 'totp';
    case Hotp = 'hotp';
    case Recovery = 'recovery';
    case Transaction = 'transaction';
}
```

---

# 205. OtpPurpose

```php
enum OtpPurpose: string
{
    case Login = 'login';
    case StepUp = 'step_up';
    case PasswordReset = 'password_reset';
    case EmailVerification = 'email_verification';
    case PhoneVerification = 'phone_verification';
    case TransactionApproval = 'transaction_approval';
    case MfaEnrollment = 'mfa_enrollment';
    case AccountRecovery = 'account_recovery';
}
```

---

# 206. OtpChallenge

```php
final readonly class OtpChallenge
{
    public function __construct(
        public string $challengeId,
        public IdentityIdentifier $identity,
        public OtpType $type,
        public OtpPurpose $purpose,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public int $maximumAttempts,
        public ?string $tenantId,
    ) {
    }
}
```

---

# 207. OTP challenge binding

Todo código deberá vincularse al menos a:

* challenge;
* identidad;
* propósito;
* expiración;
* tenant cuando aplique.

---

# 208. Contextual binding

Los perfiles de alto riesgo podrán añadir vinculación a:

* sesión;
* dispositivo;
* operación;
* monto;
* destinatario;
* recurso;
* request ID.

---

# 209. OTP generation

Los códigos deberán generarse mediante un CSPRNG cuando no deriven de un algoritmo estandarizado como TOTP u HOTP.

---

# 210. Numeric OTP length

Los códigos numéricos deberán poseer suficiente longitud según:

* vida útil;
* límites de intentos;
* riesgo;
* canal;
* propósito.

---

# 211. Short code risk

Un código corto solo será aceptable cuando se combine con:

* expiración reducida;
* pocos intentos;
* rate limiting;
* challenge específico;
* detección de abuso.

---

# 212. Alphanumeric OTP

Los códigos alfanuméricos podrán utilizarse para aumentar entropía sin incrementar demasiado la longitud.

---

# 213. Human-readable alphabet

El alfabeto podrá excluir caracteres ambiguos como:

* `0` y `O`;
* `1`, `I` y `l`;
* caracteres visualmente similares.

---

# 214. OtpGenerator

```php
interface OtpGeneratorInterface
{
    public function generate(
        OtpGenerationPolicy $policy
    ): GeneratedOtp;
}
```

---

# 215. OtpGenerationPolicy

```php
final readonly class OtpGenerationPolicy
{
    public function __construct(
        public int $length,
        public OtpAlphabet $alphabet,
        public int $lifetimeSeconds,
        public int $maximumAttempts,
        public bool $singleUse,
    ) {
    }
}
```

---

# 216. GeneratedOtp

```php
final class GeneratedOtp
{
    public function __construct(
        public readonly OtpChallenge $challenge,
        private SensitiveValue $code,
    ) {
    }

    public function reveal(
        SensitiveValueAccessToken $token
    ): string {
        return $this->code->reveal($token);
    }
}
```

---

# 217. OTP storage

Los códigos enviados por canal no deberán almacenarse en texto plano.

---

# 218. OTP verifier representation

Se almacenará una representación derivada mediante:

* hash resistente;
* HMAC con clave segregada;
* derivación específica de OTP.

---

# 219. OtpSecretHasher

```php
interface OtpSecretHasherInterface
{
    public function derive(
        SensitiveValue $code,
        OtpChallenge $challenge
    ): OtpVerificationSecret;

    public function verify(
        SensitiveValue $candidate,
        OtpVerificationSecret $stored,
        OtpChallenge $challenge
    ): bool;
}
```

---

# 220. OTP comparison

La comparación deberá ser resistente a timing attacks.

---

# 221. Atomic OTP consumption

El consumo deberá ser atómico.

Dos requests simultáneos no podrán validar exitosamente el mismo código de un solo uso.

---

# 222. OtpChallengeStore

```php
interface OtpChallengeStoreInterface
{
    public function issue(
        OtpChallenge $challenge,
        OtpVerificationSecret $secret
    ): void;

    public function verifyAndConsume(
        string $challengeId,
        SensitiveValue $candidate
    ): OtpConsumptionResult;
}
```

---

# 223. OtpConsumptionResult

```php
enum OtpConsumptionResult: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case Consumed = 'consumed';
    case AttemptsExceeded = 'attempts_exceeded';
    case ContextMismatch = 'context_mismatch';
    case NotFound = 'not_found';
}
```

---

# 224. Attempt counting

Cada intento inválido deberá incrementar un contador atómico asociado al challenge.

---

# 225. Maximum attempts

Al alcanzar el máximo permitido, el challenge deberá invalidarse.

---

# 226. OTP rate limiting

Deberán aplicarse límites separados para:

* emisión;
* reenvío;
* validación;
* identidad;
* IP;
* dispositivo;
* tenant;
* canal.

---

# 227. Resend behavior

Solicitar un nuevo código podrá:

* invalidar el anterior;
* reutilizar el mismo challenge con nueva versión;
* mantener una ventana controlada.

La política deberá ser explícita.

---

# 228. Recommended resend policy

Por defecto, emitir un nuevo código deberá invalidar los anteriores para el mismo propósito.

---

# 229. OTP response privacy

La respuesta externa no deberá revelar si:

* el email existe;
* el teléfono existe;
* el canal está registrado;
* el código fue enviado realmente.

---

# 230. Email OTP

Los OTP por correo dependen de la seguridad de la cuenta de email del usuario.

---

# 231. Email OTP assurance

Un OTP por email deberá considerarse un factor de posesión de assurance limitado.

No deberá clasificarse automáticamente como phishing-resistant.

---

# 232. Email delivery security

La entrega deberá usar:

* proveedor autenticado;
* TLS cuando esté disponible;
* plantillas controladas;
* links y códigos sin datos sensibles adicionales;
* anti-abuse.

---

# 233. Email OTP content

El mensaje deberá indicar:

* propósito;
* expiración;
* contexto básico;
* advertencia de no compartir;
* canal para reportar actividad no reconocida.

---

# 234. OTP code in email subject

El código no deberá colocarse en el subject por defecto, debido a exposición en:

* notificaciones;
* previews;
* logs del cliente;
* dispositivos bloqueados.

---

# 235. Email OTP and magic links

Email OTP y magic link deberán tratarse como mecanismos distintos, aunque compartan canal.

---

# 236. SMS OTP

Los OTP por SMS deberán considerarse vulnerables a:

* SIM swapping;
* interceptación;
* redirección;
* malware;
* recuperación insegura del operador.

---

# 237. SMS assurance ceiling

El assurance resultante deberá limitarse y no considerarse resistente al phishing.

---

# 238. SMS fallback policy

SMS no deberá ser el único mecanismo de recuperación para cuentas de alto valor.

---

# 239. Phone number change

Cambiar el número registrado deberá requerir:

* autenticación reciente;
* factor adicional;
* notificación al canal anterior;
* ventana de observación cuando el riesgo lo justifique.

---

# 240. SMS content minimization

El mensaje no deberá incluir:

* contraseña;
* nombre completo innecesario;
* información financiera;
* datos del tenant;
* enlaces inseguros.

---

# 241. OTP delivery provider abstraction

```php
interface OtpDeliveryProviderInterface
{
    public function supports(
        OtpDeliveryChannel $channel
    ): bool;

    public function deliver(
        OtpDeliveryMessage $message
    ): OtpDeliveryResult;
}
```

---

# 242. Delivery result confidentiality

Un error del proveedor no deberá exponer detalles internos al usuario.

---

# 243. Delivery telemetry

Se deberán registrar:

* intento;
* provider;
* resultado;
* latencia;
* throttling;
* error clasificado.

Nunca el código completo.

---

# 244. TOTP Architecture

TOTP genera códigos derivados de:

* secreto compartido;
* tiempo;
* algoritmo;
* intervalo.

---

# 245. TotpCredential

```php
final class TotpCredential
{
    public function __construct(
        public readonly string $credentialId,
        private SensitiveValue $secret,
        public readonly TotpProfile $profile,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
```

---

# 246. TotpProfile

```php
final readonly class TotpProfile
{
    public function __construct(
        public TotpAlgorithm $algorithm,
        public int $digits,
        public int $periodSeconds,
        public int $allowedPastWindows,
        public int $allowedFutureWindows,
    ) {
    }
}
```

---

# 247. TotpAlgorithm

```php
enum TotpAlgorithm: string
{
    case Sha1 = 'sha1';
    case Sha256 = 'sha256';
    case Sha512 = 'sha512';
}
```

---

# 248. TOTP interoperability

El soporte de algoritmos deberá considerar compatibilidad con autenticadores existentes.

---

# 249. TOTP secret generation

El secreto deberá generarse mediante un CSPRNG y poseer entropía suficiente.

---

# 250. TOTP secret storage

El secreto deberá:

* cifrarse en reposo;
* protegerse con clave segregada;
* no aparecer en logs;
* no serializarse;
* estar aislado por tenant.

---

# 251. TOTP secret access

Solo el verificador MFA deberá poder descifrarlo.

---

# 252. TOTP enrollment

El proceso de enrolamiento deberá incluir:

```text
Authenticated Session
      ↓
Fresh Authentication
      ↓
Secret Generation
      ↓
Provisioning Presentation
      ↓
User Scans or Enters Secret
      ↓
Verification Code
      ↓
Enrollment Commit
      ↓
Recovery Codes
      ↓
Notification
```

---

# 253. Provisional enrollment

El secreto no deberá activarse hasta que el usuario demuestre que puede generar un código válido.

---

# 254. Provisioning URI

La URI de aprovisionamiento deberá construirse mediante un builder estructurado.

---

# 255. TotpProvisioningUriBuilder

```php
interface TotpProvisioningUriBuilderInterface
{
    public function build(
        TotpEnrollment $enrollment
    ): SensitiveProvisioningUri;
}
```

---

# 256. QR code security

El QR contiene el secreto TOTP y deberá tratarse como material sensible.

---

# 257. QR exposure restrictions

La pantalla de enrolamiento deberá:

* usar `no-store`;
* impedir caching compartido;
* aplicar CSP estricta;
* evitar recursos de terceros;
* limitar referrer;
* expirar.

---

# 258. Re-display prohibition

Después de completar el enrolamiento, el secreto no deberá volver a mostrarse normalmente.

---

# 259. TOTP verification

```php
interface TotpVerifierInterface
{
    public function verify(
        TotpCredential $credential,
        SensitiveValue $candidate,
        DateTimeImmutable $now
    ): TotpVerificationResult;
}
```

---

# 260. TOTP clock window

La ventana permitida deberá ser pequeña.

Ventanas amplias aumentan la posibilidad de replay y adivinación.

---

# 261. TOTP replay protection

Un código aceptado no deberá volver a aceptarse dentro de la misma ventana cuando el perfil exija protección fuerte.

---

# 262. TOTP usage registry

```php
interface TotpUsageRegistryInterface
{
    public function consume(
        string $credentialId,
        int $timeStep
    ): TotpUsageResult;
}
```

---

# 263. Distributed TOTP replay protection

En despliegues multinodo, el registro de consumo deberá ser distribuido y atómico.

---

# 264. Clock synchronization

Los nodos deberán mantener sincronización horaria confiable.

---

# 265. Clock drift handling

La corrección de drift no deberá ampliar permanentemente la ventana de aceptación.

---

# 266. HOTP Architecture

HOTP deriva códigos de:

* secreto;
* contador;
* algoritmo.

---

# 267. HotpCredential

```php
final class HotpCredential
{
    public function __construct(
        public readonly string $credentialId,
        private SensitiveValue $secret,
        public readonly int $counter,
        public readonly HotpProfile $profile,
    ) {
    }
}
```

---

# 268. HOTP counter updates

El contador deberá actualizarse atómicamente después de una verificación válida.

---

# 269. HOTP look-ahead window

La ventana de búsqueda futura deberá mantenerse limitada.

---

# 270. HOTP resynchronization

La resincronización deberá requerir un flujo específico y auditado.

---

# 271. Recovery Codes

Los códigos de recuperación son credenciales de emergencia.

---

# 272. RecoveryCodeSet

```php
final readonly class RecoveryCodeSet
{
    public function __construct(
        public string $setId,
        public IdentityIdentifier $identity,
        public array $codeReferences,
        public DateTimeImmutable $issuedAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

# 273. Recovery code generation

Los códigos deberán:

* ser aleatorios;
* tener alta entropía;
* ser independientes;
* ser de un solo uso;
* poseer formato humano razonable.

---

# 274. Recovery code storage

Los códigos se almacenarán únicamente como representaciones derivadas verificables.

---

# 275. Recovery code display

Solo deberán mostrarse una vez, inmediatamente después de generarse.

---

# 276. Recovery code delivery

El usuario deberá poder:

* descargarlos;
* imprimirlos;
* copiarlos;
* confirmar que los guardó.

La descarga deberá usar un response profile sensible.

---

# 277. Recovery code regeneration

Generar un nuevo set deberá invalidar todo el set anterior.

---

# 278. Recovery code use

El uso deberá:

* consumir el código;
* generar evento;
* notificar al usuario;
* elevar el riesgo de la sesión;
* recomendar regeneración.

---

# 279. Recovery code assurance

Un recovery code podrá autenticar, pero deberá marcar la sesión como recuperada mediante un factor de emergencia.

---

# 280. Post-recovery restrictions

Después de usar un recovery code, ciertas operaciones podrán requerir:

* factor adicional;
* espera;
* autenticación reciente;
* revisión manual;
* reconfiguración MFA.

---

# 281. MFA Enrollment Architecture

MFA enrollment es una operación de seguridad de alto impacto.

---

# 282. MfaEnrollmentService

```php
interface MfaEnrollmentServiceInterface
{
    public function begin(
        IdentityContext $identity,
        AuthenticationMethod $method
    ): MfaEnrollmentChallenge;

    public function complete(
        MfaEnrollmentCompletion $completion
    ): MfaEnrollmentResult;
}
```

---

# 283. Enrollment prerequisites

El enrolamiento deberá requerir:

* sesión autenticada;
* autenticación reciente;
* nivel de assurance suficiente;
* riesgo aceptable;
* identidad activa.

---

# 284. Factor replacement

Reemplazar un factor existente deberá ser más estricto que añadir uno adicional.

---

# 285. Existing factor confirmation

Cuando sea posible, el usuario deberá confirmar un factor ya registrado antes de eliminarlo o sustituirlo.

---

# 286. MFA method registry

```php
interface MfaMethodRegistryInterface
{
    public function register(
        MfaMethodDefinition $definition
    ): void;

    public function resolve(
        AuthenticationMethod $method
    ): MfaMethodDefinition;
}
```

---

# 287. MfaMethodDefinition

```php
final readonly class MfaMethodDefinition
{
    public function __construct(
        public AuthenticationMethod $method,
        public AuthenticationFactorCategory $category,
        public AuthenticationStrength $strength,
        public bool $phishingResistant,
        public bool $hardwareBound,
    ) {
    }
}
```

---

# 288. Factor independence

Dos mecanismos no deberán contarse automáticamente como dos factores si dependen del mismo canal o dispositivo comprometible.

---

# 289. Non-independent examples

Podrán no considerarse independientes:

* contraseña y PIN almacenados en el mismo gestor comprometido;
* email OTP y magic link enviados al mismo buzón;
* SMS OTP y recuperación vía el mismo número;
* dos aplicaciones dentro del mismo dispositivo comprometido.

---

# 290. MFA policy engine

```php
interface MfaPolicyEngineInterface
{
    public function evaluate(
        IdentityContext $identity,
        MfaRequirementContext $context
    ): MfaPolicyDecision;
}
```

---

# 291. MFA requirements

La política podrá exigir:

* número de factores;
* categorías independientes;
* strength mínimo;
* factor phishing-resistant;
* hardware binding;
* freshness;
* dispositivo administrado.

---

# 292. MfaRequirement

```php
final readonly class MfaRequirement
{
    public function __construct(
        public int $minimumFactors,
        public AuthenticationStrength $minimumStrength,
        public bool $requireIndependentFactors,
        public bool $requirePhishingResistant,
        public int $freshnessSeconds,
    ) {
    }
}
```

---

# 293. MFA verification flow

```text
Primary Authentication
      ↓
Resolve MFA Requirement
      ↓
Select Eligible Factors
      ↓
Issue Challenge
      ↓
Verify Factor
      ↓
Check Replay
      ↓
Risk Reassessment
      ↓
Elevate Authentication State
      ↓
Rotate Session
```

---

# 294. MFA fatigue protection

Los sistemas de aprobación push deberán protegerse contra solicitudes repetidas.

---

# 295. Push approval limitations

Una aprobación simple de “Aceptar” podrá ser vulnerable a fatiga y consentimiento accidental.

---

# 296. Number matching

Cuando exista push MFA, deberá preferirse:

* number matching;
* contexto visible;
* ubicación aproximada;
* dispositivo solicitante;
* operación solicitada.

---

# 297. Challenge frequency limits

Se deberán limitar:

* cantidad de prompts;
* reintentos;
* prompts por sesión;
* prompts por identidad;
* prompts por dispositivo.

---

# 298. Suspicious MFA denial

Rechazos repetidos o reportes de “no fui yo” deberán:

* elevar riesgo;
* cerrar el flujo;
* revocar desafíos;
* alertar;
* considerar bloqueo temporal.

---

# 299. WebAuthn and Passkey Foundations

WebAuthn y passkeys permiten autenticación basada en criptografía de clave pública.

El servidor almacena una clave pública, mientras el autenticador conserva la clave privada.

---

# 300. Resultado de esta entrega

Esta entrega establece:

```text
One-Time Password Architecture
OTP Challenge Binding
Secure OTP Generation and Storage
Atomic OTP Consumption
Email OTP Security
SMS OTP Risk Model
TOTP Architecture
TOTP Enrollment and Verification
TOTP Replay Protection
HOTP Architecture
Recovery Code Security
MFA Enrollment
Factor Independence
MFA Policy Engine
MFA Verification Flow
MFA Fatigue Protection
Push Approval Restrictions
WebAuthn and Passkey Foundations
```

# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 4 de varias
**Cobertura:** Secciones **301–400**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 3`

---

# 301. WebAuthn Security Architecture

WebAuthn permitirá autenticar usuarios mediante credenciales criptográficas asociadas a un autenticador.

La arquitectura deberá separar:

* Relying Party;
* navegador o cliente;
* autenticador;
* credential store;
* ceremony state;
* identity provider;
* policy engine;
* verifier.

---

# 302. WebAuthn trust model

```text
User
  ↓
Browser / Client
  ↓
WebAuthn API
  ↓
Authenticator
  ↓
Public-Key Credential
  ↓
VoltStack Relying Party
```

El servidor confiará únicamente en evidencia criptográfica validada.

---

# 303. WebAuthn security goals

El subsistema deberá garantizar:

* resistencia al phishing;
* vinculación al origen;
* vinculación al RP ID;
* resistencia a replay;
* autenticación mediante clave pública;
* protección del challenge;
* aislamiento de credenciales;
* validación estricta de la ceremonia;
* revocación y auditoría.

---

# 304. WebAuthn threat model

El modelo deberá considerar:

* phishing;
* origin spoofing;
* RP ID confusion;
* replay;
* challenge substitution;
* credential substitution;
* cloned authenticators;
* counter rollback;
* malicious client data;
* attestation abuse;
* user handle confusion;
* tenant confusion;
* downgrade a métodos débiles.

---

# 305. WebAuthnRelyingParty

```php
final readonly class WebAuthnRelyingParty
{
    public function __construct(
        public string $id,
        public string $name,
        public array $allowedOrigins,
        public WebAuthnPolicyProfile $policy,
    ) {
    }
}
```

---

# 306. Relying Party ID

El RP ID deberá ser un dominio registrable válido o un subdominio permitido.

No deberá derivarse directamente de un `Host` no validado.

---

# 307. RP ID resolution

```php
interface RelyingPartyResolverInterface
{
    public function resolve(
        AuthenticationSecurityContext $context
    ): WebAuthnRelyingParty;
}
```

---

# 308. Multi-tenant RP model

VoltStack deberá soportar al menos dos estrategias:

* RP compartido para todos los tenants;
* RP separado por dominio de tenant.

La estrategia deberá configurarse explícitamente.

---

# 309. Shared RP risk

Cuando múltiples tenants compartan RP ID, la credencial deberá vincularse además al tenant dentro del modelo interno.

---

# 310. Per-tenant RP risk

Los dominios personalizados deberán validarse antes de poder utilizarse como RP ID.

---

# 311. Allowed origins

Toda ceremonia deberá validar el origen exacto contra un registry confiable.

---

# 312. Origin registry

```php
interface WebAuthnOriginRegistryInterface
{
    public function isAllowed(
        string $origin,
        WebAuthnRelyingParty $relyingParty
    ): bool;
}
```

---

# 313. Origin normalization

Los origins deberán normalizarse considerando:

* scheme;
* host;
* port;
* punycode;
* lowercase;
* default ports;
* ausencia de path;
* ausencia de fragment.

---

# 314. HTTPS requirement

Las ceremonias WebAuthn deberán ejecutarse sobre HTTPS, salvo excepciones estrictamente limitadas para desarrollo local.

---

# 315. Local development exception

La excepción de localhost no deberá trasladarse a producción.

---

# 316. WebAuthnPolicyProfile

```php
final readonly class WebAuthnPolicyProfile
{
    public function __construct(
        public UserVerificationRequirement $userVerification,
        public ResidentKeyRequirement $residentKey,
        public AttestationConveyancePreference $attestation,
        public AuthenticatorAttachmentPreference $attachment,
        public bool $requireDiscoverableCredential,
        public bool $requireBackupEligibility,
    ) {
    }
}
```

---

# 317. UserVerificationRequirement

```php
enum UserVerificationRequirement: string
{
    case Required = 'required';
    case Preferred = 'preferred';
    case Discouraged = 'discouraged';
}
```

---

# 318. ResidentKeyRequirement

```php
enum ResidentKeyRequirement: string
{
    case Required = 'required';
    case Preferred = 'preferred';
    case Discouraged = 'discouraged';
}
```

---

# 319. AuthenticatorAttachmentPreference

```php
enum AuthenticatorAttachmentPreference: string
{
    case Platform = 'platform';
    case CrossPlatform = 'cross_platform';
    case Any = 'any';
}
```

---

# 320. AttestationConveyancePreference

```php
enum AttestationConveyancePreference: string
{
    case None = 'none';
    case Indirect = 'indirect';
    case Direct = 'direct';
    case Enterprise = 'enterprise';
}
```

---

# 321. Ceremony separation

VoltStack deberá diferenciar claramente:

* registration ceremony;
* authentication ceremony.

---

# 322. Registration ceremony

La ceremonia de registro crea una nueva credencial pública vinculada a una identidad.

---

# 323. Authentication ceremony

La ceremonia de autenticación demuestra control sobre una credencial previamente registrada.

---

# 324. WebAuthn challenge

```php
final readonly class WebAuthnChallenge
{
    public function __construct(
        public string $id,
        public SensitiveValue $value,
        public WebAuthnCeremonyType $type,
        public IdentityIdentifier $identity,
        public string $relyingPartyId,
        public DateTimeImmutable $expiresAt,
        public ?string $tenantId,
    ) {
    }
}
```

---

# 325. WebAuthnCeremonyType

```php
enum WebAuthnCeremonyType: string
{
    case Registration = 'registration';
    case Authentication = 'authentication';
}
```

---

# 326. Challenge generation

El challenge deberá:

* usar CSPRNG;
* tener alta entropía;
* ser único;
* expirar rápidamente;
* vincularse a ceremonia;
* vincularse a RP;
* vincularse a identidad cuando aplique.

---

# 327. Challenge storage

El challenge completo podrá almacenarse cifrado o como estado seguro de corta duración.

---

# 328. Challenge consumption

Un challenge válido deberá consumirse atómicamente después de completar la ceremonia.

---

# 329. Challenge reuse

Un challenge no podrá reutilizarse en:

* otra ceremonia;
* otro RP;
* otra identidad;
* otro tenant;
* otro propósito.

---

# 330. WebAuthnCeremonyStore

```php
interface WebAuthnCeremonyStoreInterface
{
    public function issue(
        WebAuthnChallenge $challenge,
        WebAuthnCeremonyState $state
    ): void;

    public function consume(
        string $challengeId
    ): WebAuthnCeremonyState;
}
```

---

# 331. Ceremony state

El estado podrá incluir:

* RP ID;
* allowed origins;
* user verification requirement;
* excluded credentials;
* allowed credentials;
* timeout;
* extensions;
* identity;
* tenant;
* policy version.

---

# 332. Registration options builder

```php
interface PublicKeyCreationOptionsBuilderInterface
{
    public function build(
        WebAuthnRegistrationContext $context
    ): PublicKeyCredentialCreationOptions;
}
```

---

# 333. Registration prerequisites

Registrar una credencial deberá requerir:

* sesión autenticada;
* autenticación reciente;
* identidad activa;
* riesgo aceptable;
* autorización para administrar factores.

---

# 334. Registration step-up

Para añadir una passkey a una cuenta existente deberá exigirse step-up cuando el riesgo lo justifique.

---

# 335. User entity

```php
final readonly class WebAuthnUserEntity
{
    public function __construct(
        public string $id,
        public string $name,
        public string $displayName,
    ) {
    }
}
```

---

# 336. User entity ID

El `id` deberá ser:

* opaco;
* estable;
* no derivado del email;
* no reciclable;
* limitado en tamaño;
* independiente del display name.

---

# 337. User handle privacy

El user handle no deberá revelar información personal innecesaria.

---

# 338. User name mutability

El username mostrado al autenticador podrá cambiar sin alterar el identificador interno.

---

# 339. Credential parameters

VoltStack deberá publicar únicamente algoritmos criptográficos permitidos.

---

# 340. PublicKeyCredentialParametersRegistry

```php
interface PublicKeyCredentialParametersRegistryInterface
{
    public function allowedAlgorithms(
        WebAuthnPolicyProfile $profile
    ): array;
}
```

---

# 341. Supported algorithms

El framework deberá priorizar algoritmos modernos y ampliamente soportados.

---

# 342. Algorithm downgrade

No deberán aceptarse algoritmos no anunciados durante la ceremonia.

---

# 343. Exclude credentials

Durante el registro podrán enviarse credenciales existentes para evitar duplicados.

---

# 344. Credential exclusion privacy

La lista de exclusión deberá limitarse a la identidad autenticada y no revelar credenciales de otros usuarios.

---

# 345. Authenticator selection

La política podrá solicitar:

* autenticador de plataforma;
* llave física;
* resident key;
* user verification;
* passkey sincronizable.

---

# 346. Timeout

El timeout del cliente no sustituye la expiración del challenge en el servidor.

---

# 347. Registration response parser

```php
interface WebAuthnRegistrationResponseParserInterface
{
    public function parse(
        array $payload
    ): ParsedRegistrationResponse;
}
```

---

# 348. ClientDataJSON validation

Deberán validarse:

* estructura JSON;
* tipo;
* challenge;
* origin;
* crossOrigin;
* token binding cuando exista.

---

# 349. Client data type

Para registro deberá esperarse:

```text
webauthn.create
```

---

# 350. Challenge comparison

La comparación deberá hacerse sobre los bytes decodificados de forma segura.

---

# 351. Origin validation

El origin recibido deberá coincidir exactamente con uno permitido.

---

# 352. Cross-origin registration

El registro cross-origin deberá rechazarse salvo protocolo y política explícitamente soportados.

---

# 353. AuthenticatorData

```php
final readonly class AuthenticatorData
{
    public function __construct(
        public string $rpIdHash,
        public bool $userPresent,
        public bool $userVerified,
        public bool $backupEligible,
        public bool $backupState,
        public int $signatureCounter,
        public ?AttestedCredentialData $attestedCredentialData,
        public array $extensions,
    ) {
    }
}
```

---

# 354. RP ID hash validation

El hash del RP ID deberá coincidir con el RP esperado.

---

# 355. User presence

La bandera de presencia deberá validarse cuando la política lo requiera.

---

# 356. User verification

Cuando la política exija verificación, `userVerified` deberá ser verdadero.

---

# 357. AttestedCredentialData

```php
final readonly class AttestedCredentialData
{
    public function __construct(
        public string $aaguid,
        public string $credentialId,
        public PublicKeyMaterial $publicKey,
    ) {
    }
}
```

---

# 358. Credential ID uniqueness

El credential ID deberá ser único dentro del registry de credenciales aplicable.

---

# 359. Credential collision

Una colisión deberá considerarse un evento de alta severidad.

---

# 360. Public key validation

La clave pública deberá:

* usar algoritmo permitido;
* poseer parámetros válidos;
* cumplir longitud;
* no contener puntos inválidos;
* no usar formatos ambiguos.

---

# 361. Attestation Object

El objeto de attestation deberá analizarse mediante parsers estrictos y limitados.

---

# 362. Attestation verifier

```php
interface AttestationVerifierInterface
{
    public function verify(
        ParsedAttestationObject $attestation,
        WebAuthnRegistrationContext $context
    ): AttestationVerificationResult;
}
```

---

# 363. Attestation policy

Por defecto, las aplicaciones generales deberán preferir `none` salvo necesidad real.

---

# 364. Direct attestation

La attestation directa podrá aumentar:

* control empresarial;
* trazabilidad de autenticador;
* privacidad;
* complejidad;
* dependencia de metadata.

---

# 365. Enterprise attestation

Solo deberá habilitarse en entornos administrados y con consentimiento o base legal apropiada.

---

# 366. AAGUID handling

El AAGUID podrá utilizarse para:

* metadata;
* policy;
* detección de autenticadores;
* posture empresarial.

No deberá asumirse como prueba suficiente de seguridad por sí solo.

---

# 367. Metadata service

```php
interface AuthenticatorMetadataProviderInterface
{
    public function lookup(
        string $aaguid
    ): ?AuthenticatorMetadata;
}
```

---

# 368. Metadata trust

La metadata externa deberá:

* verificarse;
* actualizarse;
* versionarse;
* manejar expiración;
* tratarse como señal adicional.

---

# 369. Attestation trust anchors

Los trust anchors deberán gestionarse mediante un registry controlado.

---

# 370. Attestation revocation

El sistema deberá poder rechazar autenticadores revocados o comprometidos.

---

# 371. Registration verification result

```php
final readonly class WebAuthnRegistrationResult
{
    public function __construct(
        public bool $valid,
        public ?WebAuthnCredential $credential,
        public array $warnings,
        public array $securityEvents,
    ) {
    }
}
```

---

# 372. WebAuthnCredential

```php
final readonly class WebAuthnCredential
{
    public function __construct(
        public string $credentialId,
        public IdentityIdentifier $identity,
        public string $relyingPartyId,
        public PublicKeyMaterial $publicKey,
        public int $signatureCounter,
        public bool $discoverable,
        public bool $backupEligible,
        public bool $backupState,
        public string $aaguid,
        public DateTimeImmutable $createdAt,
        public WebAuthnCredentialStatus $status,
    ) {
    }
}
```

---

# 373. WebAuthnCredentialStatus

```php
enum WebAuthnCredentialStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
    case Lost = 'lost';
}
```

---

# 374. Credential nickname

El usuario podrá asignar un nombre descriptivo como:

* teléfono personal;
* laptop de trabajo;
* llave física principal.

El nickname no formará parte de la identidad criptográfica.

---

# 375. Credential registration commit

La credencial solo deberá persistirse después de completar todas las validaciones.

---

# 376. Registration transaction

El commit deberá incluir atómicamente:

* credential;
* identity binding;
* tenant binding;
* audit record;
* ceremony consumption;
* factor enrollment.

---

# 377. Authentication options builder

```php
interface PublicKeyRequestOptionsBuilderInterface
{
    public function build(
        WebAuthnAuthenticationContext $context
    ): PublicKeyCredentialRequestOptions;
}
```

---

# 378. AllowCredentials

Para autenticación identificada podrá enviarse una lista de credenciales permitidas.

---

# 379. Discoverable authentication

Para passkeys descubribles podrá omitirse `allowCredentials`.

---

# 380. Username-less authentication

La autenticación sin username deberá resolver la identidad mediante:

* credential ID;
* user handle;
* RP ID;
* tenant context.

---

# 381. Authentication response parser

```php
interface WebAuthnAuthenticationResponseParserInterface
{
    public function parse(
        array $payload
    ): ParsedAuthenticationResponse;
}
```

---

# 382. Authentication client data type

Durante autenticación deberá esperarse:

```text
webauthn.get
```

---

# 383. Assertion verification

La assertion deberá validar:

* challenge;
* origin;
* RP ID hash;
* user presence;
* user verification;
* credential;
* signature;
* counter;
* extensions.

---

# 384. Assertion verifier

```php
interface WebAuthnAssertionVerifierInterface
{
    public function verify(
        ParsedAuthenticationResponse $response,
        WebAuthnAuthenticationContext $context
    ): WebAuthnAuthenticationResult;
}
```

---

# 385. Credential lookup

El credential ID deberá resolverse en un registry aislado por RP y tenant.

---

# 386. Unknown credential

Una credencial desconocida deberá producir una respuesta externa genérica.

---

# 387. User handle validation

Cuando se reciba user handle deberá coincidir con la identidad vinculada a la credencial.

---

# 388. Signature verification

La firma deberá verificarse sobre:

```text
authenticatorData
+
SHA-256(clientDataJSON)
```

usando la clave pública registrada.

---

# 389. Invalid signature

Una firma inválida deberá:

* rechazar la autenticación;
* incrementar señales de riesgo;
* generar evento;
* no revelar detalles criptográficos.

---

# 390. Signature counter

El contador podrá ayudar a detectar clonación, pero no todos los autenticadores lo incrementan.

---

# 391. Counter evaluation

VoltStack deberá distinguir:

* contador incrementado;
* contador sin soporte;
* contador sin cambio;
* contador reducido;
* contador inconsistente.

---

# 392. Counter rollback

Un rollback podrá indicar:

* clonación;
* restauración;
* sincronización;
* comportamiento del proveedor;
* error de implementación.

---

# 393. Counter policy

El rollback no deberá producir siempre bloqueo inmediato.

La decisión dependerá de:

* tipo de autenticador;
* backup state;
* metadata;
* historial;
* riesgo;
* política del tenant.

---

# 394. Passkey synchronization

Las passkeys sincronizadas pueden existir en múltiples dispositivos bajo el ecosistema del proveedor.

---

# 395. Backup eligibility

La bandera de elegibilidad indicará si la credencial puede respaldarse o sincronizarse.

---

# 396. Backup state

La bandera de estado podrá indicar que la credencial fue respaldada.

---

# 397. Synced passkey assurance

Una passkey sincronizada podrá ser resistente al phishing, aunque no necesariamente hardware-bound a un único dispositivo.

---

# 398. Credential revocation

El usuario y los administradores autorizados deberán poder:

* suspender;
* revocar;
* marcar como perdida;
* marcar como comprometida;
* eliminar una credencial.

---

# 399. Phishing-resistant authentication policy

VoltStack deberá permitir declarar:

```php
#[RequiresAuthentication(
    assurance: AuthenticationAssuranceLevel::Aal2,
    phishingResistant: true
)]
public function changePayoutAccount(): Response
{
}
```

La política deberá aceptar únicamente métodos que cumplan la propiedad requerida.

---

# 400. Resultado de esta entrega

Esta entrega establece:

```text
WebAuthn Security Architecture
Relying Party Model
Origin and RP ID Validation
Multi-Tenant RP Strategies
WebAuthn Policy Profiles
Registration Ceremonies
Authentication Ceremonies
Challenge Generation and Binding
ClientDataJSON Validation
Authenticator Data Validation
User Presence and Verification
Attestation Policy
Authenticator Metadata
Credential Registration
Discoverable Credentials
Username-less Authentication
Assertion Verification
Signature Counter Evaluation
Passkey Synchronization
Backup Eligibility and State
Credential Revocation
Phishing-Resistant Route Policies
```
# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 5 de varias
**Cobertura:** Secciones **401–500**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 4`

---

# 401. Passkey Lifecycle Management

Las passkeys deberán administrarse como credenciales completas con:

* creación;
* identificación;
* clasificación;
* uso;
* actualización;
* suspensión;
* revocación;
* recuperación;
* auditoría.

Una credencial WebAuthn no deberá tratarse como un simple registro de clave pública.

---

# 402. Credential lifecycle states

```php
enum PasskeyLifecycleState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Lost = 'lost';
    case Compromised = 'compromised';
    case Revoked = 'revoked';
    case Retired = 'retired';
}
```

---

# 403. Pending credentials

Una credencial permanecerá en estado `Pending` durante la ceremonia de registro.

No podrá utilizarse para autenticación hasta que:

* se valide la attestation cuando corresponda;
* se verifique la primera assertion requerida;
* se consuma el challenge;
* se complete la transacción de enrolamiento.

---

# 404. Active credentials

Solo una credencial `Active` podrá participar normalmente en autenticación.

---

# 405. Suspended credentials

La suspensión permitirá deshabilitar temporalmente una credencial sin eliminar su historial.

---

# 406. Lost credentials

Una credencial marcada como perdida deberá:

* dejar de ser elegible;
* generar un evento de seguridad;
* incrementar el riesgo de sesiones relacionadas;
* activar recomendaciones de recuperación.

---

# 407. Compromised credentials

Una passkey comprometida deberá considerarse no confiable incluso si su firma sigue siendo criptográficamente válida.

---

# 408. Revoked credentials

Una credencial revocada no deberá reactivarse mediante una operación ordinaria.

---

# 409. Retired credentials

El estado `Retired` podrá utilizarse cuando una credencial sea reemplazada de forma controlada.

---

# 410. Passkey inventory

VoltStack deberá exponer un inventario seguro de credenciales por identidad.

---

# 411. PasskeyInventoryItem

```php
final readonly class PasskeyInventoryItem
{
    public function __construct(
        public string $credentialId,
        public string $displayName,
        public PasskeyLifecycleState $state,
        public AuthenticatorAttachmentPreference $attachment,
        public bool $discoverable,
        public bool $backupEligible,
        public bool $backupState,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $lastUsedAt,
        public ?string $lastKnownDevice,
    ) {
    }
}
```

---

# 412. Inventory privacy

El inventario no deberá exponer:

* clave pública completa;
* AAGUID innecesario;
* datos de attestation detallados;
* identificadores internos del proveedor;
* metadata sensible del dispositivo.

---

# 413. Credential naming

Los usuarios podrán asignar nombres descriptivos a sus passkeys.

---

# 414. Default credential names

Cuando no exista un nombre explícito, el framework podrá generar uno aproximado a partir de:

* tipo de autenticador;
* plataforma;
* fecha;
* contexto del enrolamiento.

---

# 415. Name sanitization

Los nombres de credenciales deberán:

* validarse;
* limitar longitud;
* evitar HTML;
* evitar caracteres de control;
* almacenarse como texto plano seguro.

---

# 416. Duplicate names

Se podrán permitir nombres duplicados, pero el UI deberá mostrar información adicional para distinguir credenciales.

---

# 417. Last-used tracking

El sistema podrá registrar la última utilización para ayudar al usuario a identificar credenciales activas.

---

# 418. Usage tracking privacy

El tracking no deberá convertirse en un mecanismo invasivo de seguimiento de dispositivos.

---

# 419. Credential management authorization

Administrar passkeys deberá requerir:

* sesión autenticada;
* autenticación reciente;
* assurance suficiente;
* autorización explícita;
* riesgo aceptable.

---

# 420. CredentialManagementService

```php
interface CredentialManagementServiceInterface
{
    public function list(
        IdentityContext $identity
    ): array;

    public function rename(
        IdentityContext $identity,
        string $credentialId,
        string $name
    ): CredentialManagementResult;

    public function revoke(
        IdentityContext $identity,
        string $credentialId
    ): CredentialManagementResult;
}
```

---

# 421. Deletion semantics

Eliminar una credencial desde el UI deberá traducirse internamente a una revocación auditable.

---

# 422. Hard deletion

La eliminación física podrá realizarse después del periodo de retención definido.

---

# 423. Self-lockout protection

Antes de revocar una credencial, el sistema deberá comprobar si quedará al menos un método de recuperación viable.

---

# 424. Last-factor removal

Eliminar el último factor fuerte deberá requerir:

* factor alternativo;
* recuperación verificada;
* intervención administrativa controlada;
* o confirmación reforzada según política.

---

# 425. Factor replacement

El reemplazo de passkey deberá ejecutarse como:

```text
Fresh Authentication
      ↓
Register New Credential
      ↓
Verify New Credential
      ↓
Activate New Credential
      ↓
Retire or Revoke Old Credential
      ↓
Rotate Session
      ↓
Notify User
```

---

# 426. Replace-before-remove

VoltStack deberá preferir registrar primero la nueva credencial antes de retirar la anterior.

---

# 427. Credential replacement transaction

La operación deberá mantener consistencia si falla cualquiera de las etapas.

---

# 428. Authenticator policy changes

Si una credencial deja de cumplir una nueva política, podrá:

* permanecer temporalmente;
* requerir reemplazo;
* restringirse a operaciones de bajo riesgo;
* suspenderse.

---

# 429. Credential policy evaluator

```php
interface WebAuthnCredentialPolicyEvaluatorInterface
{
    public function evaluate(
        WebAuthnCredential $credential,
        WebAuthnPolicyProfile $targetPolicy
    ): CredentialPolicyAssessment;
}
```

---

# 430. Credential inventory events

Eventos recomendados:

* `PasskeyRegistered`;
* `PasskeyRenamed`;
* `PasskeySuspended`;
* `PasskeyRevoked`;
* `PasskeyMarkedLost`;
* `PasskeyMarkedCompromised`;
* `PasskeyReplaced`.

---

# 431. Recovery after passkey loss

La pérdida de una passkey deberá activar un flujo separado del login ordinario.

---

# 432. Recovery options

La recuperación podrá apoyarse en:

* otra passkey;
* recovery codes;
* factor MFA alternativo;
* identidad federada confiable;
* soporte administrativo;
* verificación manual.

---

# 433. Recovery assurance

El assurance resultante dependerá del método utilizado.

---

# 434. Low-assurance recovery

Una recuperación de assurance bajo deberá producir una sesión restringida.

---

# 435. Restricted recovery session

```php
final readonly class RestrictedRecoverySessionPolicy
{
    public function __construct(
        public int $lifetimeSeconds,
        public array $allowedOperations,
        public bool $requirePasskeyEnrollment,
        public bool $blockSensitiveChanges,
    ) {
    }
}
```

---

# 436. Post-recovery restrictions

Una sesión recuperada podrá impedir temporalmente:

* cambio de email;
* retiro de fondos;
* modificación de métodos de pago;
* eliminación de cuenta;
* nueva impersonación;
* creación de API keys.

---

# 437. Mandatory credential re-enrollment

Después de perder todas las passkeys, el sistema podrá exigir enrolar una nueva antes de restaurar acceso completo.

---

# 438. Recovery notifications

Se deberá notificar:

* inicio de recuperación;
* finalización;
* cambios de factores;
* revocación de credenciales.

---

# 439. Recovery abuse protection

Los flujos deberán protegerse contra:

* account takeover;
* social engineering;
* SIM swapping;
* abuso del soporte;
* enumeración;
* automatización.

---

# 440. Passwordless Account Bootstrap

VoltStack deberá soportar creación de cuentas sin contraseña.

---

# 441. Bootstrap methods

El bootstrap podrá realizarse mediante:

* passkey;
* invitación firmada;
* magic link;
* identidad federada;
* dispositivo administrado;
* provisioning empresarial.

---

# 442. Bootstrap identity binding

La identidad deberá verificarse antes de activar la cuenta.

---

# 443. Passwordless bootstrap flow

```text
Registration Intent
      ↓
Identity Claim Verification
      ↓
Risk Evaluation
      ↓
WebAuthn Registration
      ↓
Credential Verification
      ↓
Recovery Method Setup
      ↓
Account Activation
      ↓
Session Creation
```

---

# 444. Incomplete bootstrap

Una cuenta incompleta deberá permanecer en estado `Pending`.

---

# 445. Bootstrap timeout

El proceso deberá expirar y limpiar estado provisional.

---

# 446. Passwordless-only accounts

Una cuenta podrá operar sin contraseña almacenada.

---

# 447. Authentication method declaration

El perfil de identidad deberá indicar explícitamente si la contraseña existe.

---

# 448. Credential capability profile

```php
final readonly class IdentityCredentialProfile
{
    public function __construct(
        public bool $hasPassword,
        public bool $hasPasskey,
        public bool $hasRecoveryCodes,
        public bool $hasFederatedIdentity,
        public array $activeMethods,
    ) {
    }
}
```

---

# 449. Password fallback prohibition

Una cuenta passwordless no deberá crear silenciosamente una contraseña como fallback.

---

# 450. Password enrollment

Añadir una contraseña a una cuenta passwordless será una operación de seguridad de alto impacto.

---

# 451. Passwordless recovery

El sistema deberá exigir al menos un mecanismo de recuperación compatible con la política.

---

# 452. Recovery method independence

El mecanismo de recuperación no deberá depender completamente del mismo dispositivo que contiene la única passkey.

---

# 453. Method downgrade prevention

VoltStack deberá impedir que un atacante degrade una cuenta desde un método fuerte a uno débil.

---

# 454. Authentication downgrade examples

Ataques posibles:

* cambiar passkey por SMS;
* habilitar contraseña débil;
* eliminar MFA;
* añadir email no verificado;
* cambiar recovery channel;
* forzar login por legacy API.

---

# 455. AuthenticationMethodPolicy

```php
final readonly class AuthenticationMethodPolicy
{
    public function __construct(
        public array $allowedMethods,
        public array $deprecatedMethods,
        public AuthenticationStrength $minimumStrength,
        public bool $preventDowngrade,
    ) {
    }
}
```

---

# 456. Downgrade evaluation

Una transición deberá comparar:

* assurance actual;
* assurance objetivo;
* factor independence;
* resistencia al phishing;
* hardware binding;
* recuperación disponible.

---

# 457. Downgrade authorization

Una reducción deliberada deberá requerir:

* autenticación fuerte y reciente;
* explicación;
* confirmación;
* notificación;
* auditoría.

---

# 458. Legacy client restrictions

Clientes antiguos no deberán forzar métodos menos seguros para toda la cuenta.

---

# 459. Per-client method policy

La política podrá permitir métodos específicos por:

* canal;
* aplicación;
* tenant;
* route group;
* device posture.

---

# 460. Session Security Architecture

Una sesión representa continuidad autenticada entre múltiples requests.

No deberá confundirse con la identidad misma.

---

# 461. Session trust model

```text
Authentication Result
      ↓
Session Issuance
      ↓
Session Identifier
      ↓
Client Storage
      ↓
Request Presentation
      ↓
Session Lookup
      ↓
Security Validation
      ↓
Identity Context
```

---

# 462. Session security goals

El sistema deberá proteger contra:

* fixation;
* hijacking;
* replay;
* leakage;
* privilege persistence;
* stale authorization;
* tenant confusion;
* session cloning;
* session store compromise.

---

# 463. Session types

```php
enum SessionType: string
{
    case Browser = 'browser';
    case ApiInteractive = 'api_interactive';
    case Recovery = 'recovery';
    case Impersonation = 'impersonation';
    case Administrative = 'administrative';
    case DeviceBound = 'device_bound';
}
```

---

# 464. SessionRecord

```php
final readonly class SessionRecord
{
    public function __construct(
        public SessionIdentifier $id,
        public IdentityIdentifier $identity,
        public SessionType $type,
        public AuthenticationState $authentication,
        public SessionLifecycleState $state,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $lastActivityAt,
        public DateTimeImmutable $idleExpiresAt,
        public DateTimeImmutable $absoluteExpiresAt,
        public ?string $tenantId,
        public int $credentialVersion,
        public int $authorizationVersion,
    ) {
    }
}
```

---

# 465. SessionIdentifier

```php
final readonly class SessionIdentifier
{
    public function __construct(
        public string $publicId,
        public string $lookupDigest,
    ) {
    }
}
```

---

# 466. Session ID generation

El identificador deberá:

* generarse con CSPRNG;
* poseer alta entropía;
* ser opaco;
* no contener user ID;
* no contener tenant ID;
* no ser secuencial;
* no ser predecible.

---

# 467. Session ID storage

El cliente recibirá el valor opaco.

El servidor podrá almacenar únicamente un digest de lookup cuando el diseño lo permita.

---

# 468. Session ID hashing

El digest deberá usar una función adecuada para búsquedas rápidas y resistencia ante exposición de la base de sesiones.

---

# 469. Session ID secrecy

El session ID deberá tratarse como bearer credential.

---

# 470. Session identifier exposure

No deberá aparecer en:

* URLs;
* logs;
* analytics;
* referrers;
* HTML;
* errores;
* traces.

---

# 471. Cookie transport

Las sesiones web deberán transportarse mediante cookies seguras.

---

# 472. Session cookie profile

```php
final readonly class SessionCookieProfile
{
    public function __construct(
        public string $name,
        public bool $secure,
        public bool $httpOnly,
        public SameSitePolicy $sameSite,
        public string $path,
        public ?string $domain,
        public bool $hostPrefix,
    ) {
    }
}
```

---

# 473. Host-prefixed session cookies

Cuando sea posible, la cookie deberá usar prefijo:

```text
__Host-
```

---

# 474. Domain-scoped session risk

Las cookies compartidas entre subdominios aumentan la superficie de ataque.

---

# 475. Session creation

Una sesión solo deberá crearse después de una autenticación válida.

---

# 476. SessionIssuer

```php
interface SessionIssuerInterface
{
    public function issue(
        AuthenticationResult $authentication,
        SessionIssueContext $context
    ): IssuedSession;
}
```

---

# 477. SessionIssueContext

```php
final readonly class SessionIssueContext
{
    public function __construct(
        public SessionType $type,
        public RequestFingerprint $request,
        public ?DeviceIdentifier $device,
        public ?string $tenantId,
        public SessionPolicy $policy,
    ) {
    }
}
```

---

# 478. Session fixation protection

Toda autenticación exitosa deberá emitir un nuevo session ID.

---

# 479. Pre-authentication sessions

Los datos almacenados antes del login deberán migrarse cuidadosamente a la nueva sesión.

---

# 480. Session data migration

Solo deberán transferirse atributos permitidos como:

* locale;
* UI preferences;
* CSRF flow state;
* carrito anónimo cuando esté autorizado.

---

# 481. Unsafe pre-auth data

No deberán migrarse automáticamente:

* identity claims;
* authorization state;
* MFA status;
* tenant privileges;
* impersonation state.

---

# 482. Session rotation

La sesión deberá rotarse ante eventos relevantes.

---

# 483. Session rotation triggers

Se deberá rotar cuando ocurra:

* login;
* step-up;
* cambio de contraseña;
* cambio de MFA;
* inicio de impersonación;
* fin de impersonación;
* cambio de tenant sensible;
* elevación administrativa;
* recuperación.

---

# 484. SessionRotator

```php
interface SessionRotatorInterface
{
    public function rotate(
        SessionRecord $current,
        SessionRotationReason $reason
    ): RotatedSession;
}
```

---

# 485. SessionRotationReason

```php
enum SessionRotationReason: string
{
    case Authentication = 'authentication';
    case StepUp = 'step_up';
    case CredentialChange = 'credential_change';
    case PrivilegeChange = 'privilege_change';
    case TenantSwitch = 'tenant_switch';
    case ImpersonationStart = 'impersonation_start';
    case ImpersonationEnd = 'impersonation_end';
    case RiskResponse = 'risk_response';
}
```

---

# 486. Rotation transaction

La rotación deberá:

* crear nuevo ID;
* invalidar el anterior;
* transferir datos permitidos;
* preservar audit linkage;
* emitir nueva cookie.

---

# 487. Rotation grace window

Una ventana mínima podrá tolerarse para requests concurrentes legítimos.

---

# 488. Grace window restrictions

La ventana deberá:

* ser corta;
* permitir transición única;
* evitar reutilización prolongada;
* registrar uso del identificador anterior.

---

# 489. Session store

```php
interface SessionStoreInterface
{
    public function create(SessionRecord $session): void;

    public function find(SessionIdentifier $id): ?SessionRecord;

    public function update(SessionRecord $session): void;

    public function revoke(
        SessionIdentifier $id,
        SessionRevocationReason $reason
    ): void;
}
```

---

# 490. Session store security

El store deberá proporcionar:

* acceso autenticado;
* cifrado en tránsito;
* aislamiento;
* expiración;
* atomicidad;
* protección contra enumeración;
* auditoría.

---

# 491. Centralized versus stateless sessions

VoltStack podrá soportar:

* sesiones stateful;
* tokens de sesión firmados;
* modelos híbridos.

---

# 492. Stateful session advantages

Ventajas:

* revocación inmediata;
* menor exposición de datos;
* control de concurrencia;
* actualización centralizada;
* gestión de riesgo.

---

# 493. Stateless session risks

Riesgos:

* revocación compleja;
* claims obsoletos;
* payload expuesto;
* mayor dependencia de claves;
* replay hasta expiración.

---

# 494. Default session model

Para aplicaciones web interactivas, VoltStack deberá preferir sesiones stateful con identificador opaco.

---

# 495. SessionPolicy

```php
final readonly class SessionPolicy
{
    public function __construct(
        public int $idleTimeoutSeconds,
        public int $absoluteTimeoutSeconds,
        public int $rotationIntervalSeconds,
        public int $maximumConcurrentSessions,
        public bool $bindDevice,
        public bool $revokeOnCredentialChange,
    ) {
    }
}
```

---

# 496. Idle timeout

La expiración por inactividad deberá basarse en actividad válida y controlada.

---

# 497. Activity refresh

No todo request deberá renovar la actividad.

Podrán excluirse:

* polling automático;
* assets;
* health checks;
* background requests;
* telemetry.

---

# 498. Absolute timeout

Toda sesión deberá tener una expiración absoluta que no pueda extenderse indefinidamente mediante actividad.

---

# 499. Concurrent session controls

El sistema podrá limitar sesiones por:

* identidad;
* tipo;
* dispositivo;
* tenant;
* assurance;
* perfil.

La política deberá definir si al exceder el límite se rechaza la nueva sesión o se revoca la más antigua.

---

# 500. Resultado de esta entrega

Esta entrega establece:

```text
Passkey Lifecycle Management
Credential Inventory
Credential Naming
Factor Revocation and Replacement
Self-Lockout Protection
Recovery After Passkey Loss
Restricted Recovery Sessions
Passwordless Account Bootstrap
Passwordless-Only Accounts
Authentication Downgrade Prevention
Session Security Architecture
Session Types
Opaque Session Identifiers
Secure Session Cookies
Session Creation
Session Fixation Protection
Session Rotation
Session Store Security
Stateful and Stateless Session Models
Idle and Absolute Timeouts
Concurrent Session Control Foundations
```
