# Controller Security Model - Part 1: Security Foundations & Threat Model

**Versión:** 1.0
**Estado:** Draft arquitectónico
**Módulo:** `VoltStack\Quantum\Controllers\Security`
**Ámbito:** Fundamentos de seguridad, límites de confianza, activos, amenazas, superficie de ataque, políticas y pipeline transversal
**Integraciones principales:** Routing, ControllerResolver, Metadata, Parameters, Interceptors, Invoker, Transformation, Transport, Exceptions, Lifecycle, Observability, Compilation Framework, Workers persistentes y FrankenPHP

---

## 1. Introducción

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

## 2. Objetivo principal

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

## 3. Seguridad como propiedad transversal

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

## 4. Principios fundamentales

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

## 5. Secure by default

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

## 6. Deny by default

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

## 7. Least privilege

Cada componente tendrá acceso únicamente a lo necesario.

Ejemplos:

* un Parameter Resolver no necesita acceso al Transport;
* un Invoker no necesita modificar metadata;
* Observability no necesita argumentos completos;
* un Interceptor de autorización no necesita emitir respuestas directamente;
* un artifact loader no necesita acceso al Request completo.

---

## 8. Defense in depth

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

## 9. Fail closed

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

## 10. Separación entre seguridad y conveniencia

Las abstracciones de conveniencia no deberán reducir garantías.

Ejemplos:

* auto-binding no deberá ignorar scopes;
* atributos no deberán exponer métodos automáticamente;
* alias no deberán saltarse validaciones;
* compiled plans no deberán evitar autorización;
* fakes de testing no deberán cambiar semántica productiva.

---

## 11. Objetivos de seguridad

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

## 12. Confidencialidad

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

## 13. Integridad

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

## 14. Disponibilidad

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

## 15. Autenticidad

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

## 16. Autorización

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

## 17. Aislamiento

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

## 18. Trazabilidad

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

## 19. Modelo de confianza

VoltStack distinguirá entre componentes confiables y no confiables.

```text
Untrusted
Semi-trusted
Trusted
Privileged
```

---

## 20. Entradas no confiables

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

## 21. Componentes semi-confiables

Podrán considerarse semi-confiables:

* application controllers;
* package controllers;
* custom interceptors;
* custom parameter resolvers;
* custom metadata providers;
* third-party extensions.

Aunque formen parte de la aplicación, deberán operar bajo contratos y límites.

---

## 22. Componentes confiables

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

## 23. Componentes privilegiados

Componentes privilegiados:

* build activator;
* manifest signer;
* preload generator;
* artifact deployment manager;
* secret provider;
* worker lifecycle manager.

Su acceso deberá ser limitado.

---

## 24. Trust boundaries

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

## 25. Boundary: Client to Server

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

## 26. Boundary: Server to Request Object

El Request deberá ser una representación normalizada.

No deberá conservar simultáneamente múltiples interpretaciones ambiguas de:

* path;
* host;
* scheme;
* content length;
* transfer encoding;
* headers duplicados.

---

## 27. Boundary: Request to Routing

Routing deberá recibir:

* path normalizado;
* host validado;
* método permitido;
* scheme confiable;
* proxy metadata validada.

Routing no deberá confiar en headers de proxy sin configuración explícita.

---

## 28. Boundary: Routing to Controllers

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

## 29. Boundary: Pipeline to Application Code

Antes de entrar a código de aplicación deberán completarse:

* route validation;
* controller exposure validation;
* security metadata resolution;
* authentication;
* authorization;
* tenant resolution;
* parameter validation.

---

## 30. Boundary: Application Code to Data Layer

Controllers no deberán asumir que el acceso a datos está autorizado únicamente porque el objeto fue resuelto.

El Data Layer deberá mantener:

* tenant filters;
* resource scopes;
* policy enforcement cuando aplique;
* safe queries.

---

## 31. Boundary: Runtime Build

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

## 32. Boundary: Worker Persistence

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

## 33. Boundary: Observability Export

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

## 34. Activos protegidos

El modelo deberá identificar activos de seguridad.

---

## 35. Activos de identidad

* user identity;
* service identity;
* API client identity;
* session identity;
* worker identity;
* tenant identity;
* impersonation context.

---

## 36. Activos de autorización

* roles;
* permissions;
* policies;
* resource scopes;
* security metadata;
* authorization decisions;
* deny reasons;
* delegated capabilities.

---

## 37. Activos de datos

* domain entities;
* DTO contents;
* uploaded files;
* generated documents;
* stream contents;
* internal exceptions;
* private response fields;
* SPA state.

---

## 38. Activos de ejecución

* controller target;
* resolved parameters;
* interceptors;
* invocation plan;
* lifecycle state;
* cancellation state;
* cleanup resources.

---

## 39. Activos compilados

* artifacts;
* manifests;
* fingerprints;
* signatures;
* build pointers;
* preload files;
* dependency graphs;
* route maps.

---

## 40. Activos de infraestructura

* secrets;
* database credentials;
* API keys;
* filesystem paths;
* internal hosts;
* worker configuration;
* OPcache configuration;
* deployment credentials.

---

## 41. Activos de observabilidad

* logs;
* traces;
* metrics;
* profiling data;
* correlation IDs;
* error fingerprints;
* timelines.

---

## 42. Actores del modelo

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

## 43. Anonymous client

Puede intentar:

* descubrir rutas;
* explotar binding;
* generar errores;
* abusar de uploads;
* provocar consumo excesivo;
* obtener información interna.

---

## 44. Authenticated user

Puede intentar:

* escalar privilegios;
* acceder a recursos ajenos;
* modificar tenant IDs;
* explotar DTO hydration;
* abusar de endpoints internos;
* extraer información por errores.

---

## 45. Privileged user

Puede intentar:

* usar permisos fuera de contexto;
* abusar de impersonation;
* acceder a otro tenant;
* ejecutar acciones administrativas no auditadas.

---

## 46. Package author

Un paquete puede introducir:

* resolver inseguro;
* interceptor malicioso;
* metadata provider invasivo;
* exporter que filtre datos;
* controller expuesto accidentalmente.

---

## 47. System operator

Puede afectar:

* artifacts;
* manifests;
* build activation;
* configuration;
* Workers;
* logs.

El modelo deberá minimizar confianza implícita incluso en operaciones.

---

## 48. Build system

El sistema de build es un actor privilegiado.

Puede:

* generar artifacts;
* firmar manifests;
* activar builds;
* producir preload.

Su compromiso comprometería integridad del runtime.

---

## 49. External services

Sus respuestas deberán considerarse no confiables aunque el servicio sea legítimo.

Podrán contener:

* payloads malformados;
* valores inesperados;
* datos manipulados;
* errores sensibles.

---

## 50. Categorías de amenazas

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

## 51. Spoofing

Ejemplos:

* falsificar usuario;
* falsificar tenant;
* falsificar proxy headers;
* falsificar API client;
* reutilizar session token;
* alterar correlation IDs.

---

## 52. Tampering

Ejemplos:

* modificar artifact;
* cambiar manifest;
* alterar metadata;
* manipular route parameters;
* modificar headers de seguridad;
* alterar execution context.

---

## 53. Repudiation

Ejemplos:

* negar acción administrativa;
* falta de audit trail;
* decisiones sin policy ID;
* impersonation no registrada;
* cambios de build sin registro.

---

## 54. Information Disclosure

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

## 55. Denial of Service

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

## 56. Elevation of Privilege

Ejemplos:

* invocar método no expuesto;
* bypass de policy;
* model binding fuera de scope;
* tenant override;
* manipulación de metadata;
* interceptor order abuse;
* compiled plan obsoleto.

---

## 57. Superficie de ataque

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

## 58. Transport surface

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

## 59. Routing surface

Incluye:

* path matching;
* host matching;
* route parameters;
* route names;
* controller references;
* route cache;
* frontend route manifest.

---

## 60. Controller resolution surface

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

## 61. Parameter surface

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

## 62. Metadata surface

Incluye:

* attributes;
* configuration;
* route metadata;
* package metadata;
* dynamic providers;
* merge precedence;
* compiled metadata.

---

## 63. Authorization surface

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

## 64. Invocation surface

Incluye:

* callable construction;
* method visibility;
* argument order;
* controller scope;
* service lifecycle;
* retries;
* transactions.

---

## 65. Transformation surface

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

## 66. Exception surface

Incluye:

* classification;
* mapping;
* rendering;
* reporting;
* recovery;
* emergency fallback;
* public error IDs.

---

## 67. Compilation surface

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

## 68. Worker surface

Incluye:

* request reuse;
* static state;
* singleton contamination;
* mutable caches;
* leaked principals;
* leaked tenant context;
* memory growth.

---

## 69. Observability surface

Incluye:

* logs;
* traces;
* metrics;
* event payloads;
* profiler snapshots;
* exporters;
* dashboards.

---

## 70. Extension surface

Incluye:

* custom resolvers;
* custom interceptors;
* custom compilers;
* custom transport adapters;
* custom exporters;
* third-party packages.

---

## 71. Threat: Dynamic controller injection

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

## 72. Threat: Method exposure

Riesgo:

Un controller contiene métodos públicos auxiliares.

Mitigación:

```text
Public method ≠ exposed action
```

Solo serán invocables métodos registrados explícitamente como acciones.

---

## 73. Threat: Parameter source confusion

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

## 74. Threat: Mass assignment through DTO hydration

Mitigación:

* DTO schemas explícitos;
* propiedades permitidas;
* extra keys policy;
* constructor controlado;
* nested depth limits;
* coerción segura.

---

## 75. Threat: Unauthorized model binding

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

## 76. Threat: Tenant ID manipulation

No se confiará en un tenant ID proporcionado directamente por el cliente.

El tenant deberá derivarse de una fuente autenticada:

* host validado;
* session;
* signed token;
* trusted identity provider;
* server-side mapping.

---

## 77. Threat: Metadata privilege escalation

Un package podría agregar metadata de autorización permisiva.

Mitigación:

* schemas;
* trusted providers;
* immutable security keys;
* merge rules restrictivas;
* deny precedence;
* registry freeze.

---

## 78. Threat: Interceptor order manipulation

Un interceptor malicioso podría ejecutarse antes de autorización.

Mitigación:

* security phases reservadas;
* priorities restringidas;
* mandatory interceptors;
* compiled order validation;
* no bypass.

---

## 79. Threat: Invocation plan tampering

Mitigación:

* build manifests;
* artifact fingerprints;
* signatures;
* read-only files;
* active build pinning;
* runtime validation.

---

## 80. Threat: Response data leakage

Mitigación:

* serializers explícitos;
* hidden fields;
* resource transformers;
* response classification;
* content policy;
* no serialización arbitraria de objetos.

---

## 81. Threat: Open redirect

Mitigación:

* URLs internas por defecto;
* external redirect allowlist;
* normalized destinations;
* signed redirects cuando aplique;
* scheme restrictions.

---

## 82. Threat: Header injection

Mitigación:

* validar nombres;
* rechazar CR/LF;
* valores tipados;
* protected header registry;
* immutable security headers.

---

## 83. Threat: Cookie weakening

Una respuesta no deberá reducir cookies protegidas accidentalmente.

Mitigación:

* secure defaults;
* HttpOnly;
* SameSite;
* path/domain restrictions;
* prefix support;
* policy guard.

---

## 84. Threat: Exception disclosure

Mitigación:

* public vs internal representation;
* sanitization;
* generic production messages;
* public error IDs;
* stack traces solo internos;
* reporter separation.

---

## 85. Threat: Log injection

Mitigación:

* structured logs;
* encoding;
* newline sanitization;
* field size limits;
* no concatenación directa;
* controlled correlation IDs.

---

## 86. Threat: Worker state leakage

Mitigación:

* request scopes;
* execution scopes;
* reset contracts;
* immutable worker caches;
* leak detection;
* worker disposition policy.

---

## 87. Threat: Build downgrade

Un operador o atacante podría activar un build antiguo vulnerable.

Mitigación:

* build policy;
* minimum allowed version;
* signed manifest;
* deployment audit;
* rollback restrictions;
* revoked build list.

---

## 88. Threat: Artifact substitution

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

## 89. Threat: Cache poisoning

Mitigación:

* build-aware keys;
* typed entries;
* immutable values;
* no user-controlled cache keys sin normalización;
* validation on load;
* namespace isolation.

---

## 90. Threat: Recursive execution

Mitigación:

* maximum depth;
* recursion detection;
* execution graph;
* cancellation;
* per-execution budget.

---

## 91. Threat: Retry amplification

Mitigación:

* retry limits;
* idempotency checks;
* retry budget;
* non-retryable categories;
* timeout propagation;
* observability.

---

## 92. Threat: Streaming abuse

Mitigación:

* duration limits;
* idle timeouts;
* byte limits;
* chunk limits;
* cancellation;
* disconnect detection;
* resource ownership.

---

## 93. Threat: Upload abuse

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

## 94. Threat: Content-type confusion

Mitigación:

* declared type validation;
* actual format inspection;
* no trust en extensión;
* explicit parser selection;
* strict negotiation.

---

## 95. Threat: Request smuggling

Principalmente se mitigará en servidor y proxy, pero VoltStack deberá:

* rechazar inconsistencias;
* no reinterpretar framing;
* confiar en una única request normalizada;
* validar proxy chain.

---

## 96. Threat: Host header attacks

Mitigación:

* allowed hosts;
* trusted proxies;
* canonical host;
* safe URL generation;
* tenant host mapping;
* reject unknown hosts.

---

## 97. Threat: CSRF

Aplicable a acciones autenticadas basadas en cookies.

Mitigación:

* CSRF tokens;
* SameSite cookies;
* origin validation;
* method restrictions;
* idempotency awareness.

---

## 98. Threat: CORS misconfiguration

Mitigación:

* explicit origins;
* no wildcard con credentials;
* method allowlist;
* header allowlist;
* preflight validation;
* route-aware policies.

---

## 99. Threat: SSRF through controllers

Mitigación transversal:

* URL validation;
* network policy;
* hostname/IP restrictions;
* metadata service protection;
* redirect limits;
* DNS rebinding awareness.

---

## 100. Threat: Deserialization attacks

VoltStack no deberá usar deserialización insegura para input de usuario.

Se evitará:

* `unserialize()` sobre input;
* object instantiation arbitraria;
* polymorphic DTOs no permitidos;
* class names provenientes del cliente.

---

## 101. Threat: Prototype-like property pollution

Aunque PHP no tenga prototype chain como JavaScript, deberá evitarse:

* hydration de propiedades arbitrarias;
* magic setters sin allowlist;
* keys reservadas;
* metadata injection.

---

## 102. Security Context

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

## 103. Principal

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

## 104. Principal types

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

## 105. Authentication strength

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

## 106. Tenant identity

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

## 107. Security attributes

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

## 108. Security Context Factory

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

## 109. Security context lifecycle

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

## 110. Impersonation context

Cuando exista impersonation deberán conservarse:

* actor original;
* actor efectivo;
* reason;
* authorization;
* start time;
* audit reference.

---

## 111. Security Decision

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

## 112. Decision effects

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

## 113. Abstain semantics

`Abstain` no equivaldrá a `Allow`.

La política final deberá resolver:

```text
Any explicit deny → deny
No allow → deny
At least one allow and no deny → allow
```

salvo estrategia explícita distinta.

---

## 114. Security obligations

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

## 115. Security Policy Registry

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

## 116. Security Policy

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

## 117. Security evaluation request

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

## 118. Security Decision Engine

```php
interface ControllerSecurityDecisionEngineInterface
{
    public function decide(
        SecurityEvaluationRequest $request
    ): SecurityDecision;
}
```

---

## 119. Pipeline de seguridad

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

## 120. Security stages

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

## 121. Pre-binding authorization

Algunas decisiones deberán realizarse antes de resolver modelos o DTOs costosos.

Ejemplo:

```text
Can user access administration module?
```

Si no, no deberá ejecutarse model binding.

---

## 122. Resource authorization

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

## 123. Invocation guard

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

## 124. Result security

Antes de transformar una respuesta deberán aplicarse políticas:

* field filtering;
* PII masking;
* tenant verification;
* cache restrictions;
* classification labels;
* export restrictions.

---

## 125. Transport security

Antes de emitir:

* security headers;
* cookie policies;
* cache control;
* content type;
* download policies;
* redirect policies;
* framing policies.

---

## 126. Security metadata

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

## 127. Security metadata immutability

Las keys críticas no podrán ser debilitadas por metadata de menor prioridad.

Ejemplo:

```text
Global policy: MFA required
Method metadata: MFA false
```

El override deberá rechazarse.

---

## 128. Security merge rules

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

## 129. Most restrictive wins

Aplicable a:

* authentication strength;
* transport security;
* tenant requirement;
* cache restrictions;
* response sensitivity.

---

## 130. Union

Aplicable a obligaciones:

```text
audit + mask + no-cache
```

---

## 131. Intersection

Aplicable a allowlists:

```text
Global allowed methods
∩ Module allowed methods
∩ Route allowed methods
```

---

## 132. Immutable security metadata

Algunas keys solo podrán definirse en niveles confiables:

* system policies;
* artifact trust;
* privileged routes;
* emergency bypass;
* internal-only classification.

---

## 133. Security Metadata Validator

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

## 134. Security policy compilation

Las políticas estáticas podrán convertirse en:

```text
CompiledControllerSecurityPlan
```

Este plan deberá formar parte del execution bundle.

---

## 135. CompiledControllerSecurityPlan

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

## 136. Seguridad dinámica y compilada

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

## 137. Decision cache

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

## 138. Restricciones del decision cache

Nunca deberá compartirse entre:

* usuarios;
* tenants;
* executions;
* impersonation contexts;
* builds incompatibles.

---

## 139. SecurityDecisionKey

Deberá considerar:

* principal;
* tenant;
* policy;
* action;
* resource identity;
* security context version;
* execution ID cuando aplique.

---

## 140. Security budgets

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

## 141. Policy evaluation limits

Esto evita:

* loops;
* recursive policies;
* expensive policy graphs;
* malicious extensions;
* accidental N+1 authorization.

---

## 142. Security failure model

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

## 143. Authentication failure

Debe producir una representación adecuada sin revelar si un recurso existe cuando eso sea sensible.

---

## 144. Authorization denial

No deberá incluir:

* nombre interno de policy;
* roles faltantes;
* condiciones exactas;
* tenant objetivo;
* detalles de resource ownership.

Estos datos podrán registrarse internamente de forma sanitizada.

---

## 145. Tenant violation

Deberá tratarse como evento de alta relevancia.

Podrá responder:

* 404 para ocultación;
* 403 cuando sea seguro;
* 401 si falta autenticación.

La política será configurable.

---

## 146. Security infrastructure failure

Ejemplos:

* policy registry unavailable;
* tenant resolver failed;
* security metadata corrupta;
* signer unavailable.

En producción deberá fallar cerrado.

---

## 147. Security events

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

## 148. Security metrics

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

## 149. Cardinalidad segura

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

## 150. Auditoría

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

## 151. Audit record

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

## 152. Pseudonimización

Los identificadores en auditoría podrán transformarse mediante:

* hashing con salt;
* tokenización;
* stable pseudonyms;
* external identity references.

---

## 153. Security observability separation

Se distinguirán:

```text
Operational logs
Security logs
Audit logs
Application logs
```

No deberán mezclarse sin política.

---

## 154. Security log integrity

Podrán utilizarse:

* append-only storage;
* restricted access;
* retention policies;
* external export;
* signed batches.

---

## 155. Security configuration

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

## 156. Componentes del módulo

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

## 157. ControllerSecurityManager

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

## 158. Security registry freeze

Antes de comenzar a servir requests:

```text
Register policies
Register guards
Register sanitizers
Register tenant resolvers
Freeze registries
```

---

## 159. Extensiones de seguridad

Las extensiones deberán declarar:

* capabilities;
* required privileges;
* data accessed;
* lifecycle;
* thread/worker safety;
* deterministic behavior;
* failure mode.

---

## 160. Extension capability model

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

## 161. Extension sandbox conceptual

PHP no provee sandbox real dentro del mismo proceso.

Por ello se aplicarán:

* contratos;
* capabilities;
* static analysis;
* package trust;
* code review;
* isolation mediante procesos cuando sea necesario.

---

## 162. Security invariants

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

## 163. Invariante de autenticación

Si una acción requiere autenticación:

```text
principal.authenticated() must be true
```

antes de resolver recursos protegidos.

---

## 164. Invariante de tenant

Si una acción requiere tenant:

```text
security.tenant != null
security.tenant.verified == true
```

---

## 165. Invariante de invocación

```text
target.exposed == true
target.validated == true
authorization.effect == Allow
```

---

## 166. Invariante de artifact

```text
artifact.build_id == execution.build_id
artifact.signature.valid == true
artifact.manifest_member == true
```

---

## 167. Invariante de Worker

Después de cleanup:

```text
current_principal == null
current_tenant == null
current_security_context == null
decision_cache.empty == true
```

---

## 168. Invariante de observabilidad

```text
exported_signal.sanitized == true
```

---

## 169. Security testing requirements

Cada control deberá tener:

* unit test;
* denial test;
* bypass attempt;
* Worker isolation test cuando aplique;
* compiled equivalence test;
* observability sanitization test.

---

## 170. Threat-model-driven testing

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

## 171. Threat register

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

## 172. Risk levels

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

## 173. Risk evaluation

Podrá considerar:

```text
Likelihood × Impact × Exposure
```

con ajustes por detectabilidad y controles compensatorios.

---

## 174. Critical risks

Ejemplos:

* arbitrary controller execution;
* cross-tenant access;
* artifact code injection;
* authentication bypass;
* Worker context leakage;
* secret disclosure;
* unsafe file execution.

---

## 175. Security hardening profiles

VoltStack podrá ofrecer:

```text
Development
Standard
Strict
High Security
```

---

## 176. Development profile

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

## 177. Standard profile

Adecuado para aplicaciones generales.

Incluye:

* secure defaults;
* strict headers;
* authorization;
* safe artifacts;
* Worker reset.

---

## 178. Strict profile

Incluye:

* no dynamic fallback;
* explicit policies;
* strict tenants;
* signed manifests opcionales;
* limited extensions;
* enhanced audit;
* aggressive sanitization.

---

## 179. High Security profile

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

## 180. Secure deployment assumptions

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

## 181. Security ownership

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

## 182. Security review gates

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

## 183. Deprecación segura

Una capacidad insegura deberá:

1. marcarse deprecated;
2. generar warning;
3. ofrecer migración;
4. deshabilitarse en versión mayor;
5. documentar riesgo.

---

## 184. Compatibility and security

La compatibilidad hacia atrás no deberá mantener vulnerabilidades críticas.

VoltStack podrá introducir cambios incompatibles cuando sean necesarios para corregir riesgos graves.

---

## 185. Incident response hooks

El sistema podrá emitir señales para:

* revoke session;
* terminate Worker;
* disable build;
* block principal;
* quarantine tenant;
* trigger alert;
* escalate audit event.

---

## 186. SecurityIncident

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

## 187. Worker disposition por seguridad

```text
Reuse
Reset
RestartRecommended
Terminate
Quarantine
```

---

## 188. Terminate scenarios

Un Worker deberá terminar ante:

* artifact trust failure;
* leaked security context no reparable;
* compromised registry;
* memory corruption signal;
* repeated cleanup failure;
* impossible state transition.

---

## 189. Security documentation requirements

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

## 190. Estructura inicial del módulo

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

## 191. ADR-001

**La seguridad será una propiedad transversal del pipeline, no únicamente middleware.**

---

## 192. ADR-002

**VoltStack utilizará deny-by-default para decisiones de seguridad.**

---

## 193. ADR-003

**Los métodos públicos no serán automáticamente acciones invocables.**

---

## 194. ADR-004

**Los controller targets nunca podrán derivarse directamente de input del cliente.**

---

## 195. ADR-005

**Las políticas de seguridad se resolverán mediante un engine central.**

---

## 196. ADR-006

**La metadata crítica utilizará reglas de merge restrictivas.**

---

## 197. ADR-007

**La autorización previa y la autorización de recurso serán fases distintas.**

---

## 198. ADR-008

**El tenant deberá provenir de una fuente verificada.**

---

## 199. ADR-009

**El binding de modelos siempre respetará tenant y resource scopes.**

---

## 200. ADR-010

**Los artifacts compilados no contendrán decisiones finales dependientes del usuario.**

---

## 201. ADR-011

**Cada ejecución tendrá un contexto de seguridad inmutable.**

---

## 202. ADR-012

**Los Workers no compartirán contextos ni decisiones de seguridad entre requests.**

---

## 203. ADR-013

**Los artifacts deberán pertenecer al build activo de la ejecución.**

---

## 204. ADR-014

**Las fallas de infraestructura de seguridad producirán fail-closed en producción.**

---

## 205. ADR-015

**Las señales de observabilidad deberán sanitizarse antes de exportarse.**

---

## 206. ADR-016

**Las decisiones Abstain no se interpretarán como Allow.**

---

## 207. ADR-017

**Los controles de seguridad deberán ser equivalentes en modo dinámico y compilado.**

---

## 208. ADR-018

**Los custom security extensions deberán declarar capabilities.**

---

## 209. ADR-019

**Las violaciones de tenant se tratarán como incidentes de seguridad.**

---

## 210. ADR-020

**Los builds inseguros podrán revocarse aunque sean técnicamente compatibles.**

---

## 211. ADR-021

**El Worker podrá ser terminado ante una pérdida de confianza interna.**

---

## 212. ADR-022

**La compatibilidad no tendrá prioridad sobre la corrección de vulnerabilidades críticas.**

---

## 213. Implementación V1 de fundamentos

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

## 214. Fuera de PART 01

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

## 215. Flujo conceptual completo

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

## 216. Conclusión de PART 01

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

## 217. Siguiente parte

```text
CONTROLLER_SECURITY_MODEL_PART_02.md
```

## Runtime & Controller Security

Incluirá:

* Controller Resolution Security;
* Action Exposure Model;
* Method Visibility;
* Alias and Service Resolution;
* Parameter Injection Security;
* Route, Query, Body and Header Sources;
* DTO Hydration Security;
* Model Binding Security;
* File Parameter Security;
* Authorization Pipeline;
* Tenant Isolation;
* Interceptor Security;
* Invocation Security;
* Lifecycle Security;
* Subrequests;
* Recursion;
* Retries;
* Cancellation;
* FrankenPHP Worker Isolation.
