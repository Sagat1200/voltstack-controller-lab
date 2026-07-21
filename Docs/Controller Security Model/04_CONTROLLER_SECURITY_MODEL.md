# Controller Security Model - Part 04: Transport & Response Security


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

## Entrega 1

### 1. Introducción

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

### 2. Objetivo principal

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

### 3. Principio fundamental

```text
A valid controller result
    ≠
A safe HTTP response
```

El resultado del controlador deberá pasar por una capa adicional de seguridad antes de llegar al cliente.

---

### 4. Modelo general

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

### 5. Activos protegidos

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

### 6. Superficie de ataque

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

### 7. Actores

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

### 8. Categorías de amenazas

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

### 9. Security invariant principal

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

### 10. Response Trust Model

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

### 11. Controller result trust

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

### 12. Response object trust

Un objeto que implemente `ResponseInterface` tampoco deberá omitir las validaciones centrales.

El framework deberá distinguir:

```text
Trusted core response
Application response
Third-party response
Raw transport response
```

---

### 13. Raw response bypass

No deberá existir una API pública que permita emitir directamente bytes y headers sin pasar por controles mínimos.

---

### 14. Secure Response Pipeline

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

### 15. ControllerResult

```php
interface ControllerResultInterface
{
    public function resultType(): ControllerResultType;

    public function payload(): mixed;

    public function metadata(): ControllerResultMetadata;
}
```

---

### 16. ControllerResultType

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

### 17. Result normalization

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

### 18. Ambiguous results

Los resultados ambiguos deberán rechazarse o resolverse mediante configuración explícita.

Ejemplo:

```php
return '<script>alert(1)</script>';
```

El framework no deberá asumir silenciosamente que se trata de HTML seguro.

---

### 19. Explicit response types

Se favorecerá:

```php
return Response::html($content);
return Response::json($data);
return Response::download($path);
return Response::redirect($url);
```

sobre retornos ambiguos.

---

### 20. ResponseSecurityContext

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

### 21. Response classification

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

### 22. Public response

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

### 23. Internal response

Destinada a:

* herramientas internas;
* redes privadas;
* paneles administrativos;
* APIs de servicio.

No implica ausencia de autenticación.

---

### 24. Private response

Relacionada con un usuario autenticado.

Por defecto deberá:

* evitar cache pública;
* proteger cookies;
* limitar metadata;
* aplicar `Cache-Control: private`.

---

### 25. Confidential response

Puede incluir:

* datos personales;
* información financiera;
* credenciales temporales;
* información empresarial sensible.

Deberá usar políticas más estrictas.

---

### 26. Restricted response

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

### 27. Classification inheritance

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

### 28. Response classification metadata

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

### 29. Runtime classification escalation

Un transformer podrá elevar la sensibilidad si detecta campos sensibles.

No deberá reducirla sin autorización.

---

### 30. ResponseMetadata

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

### 31. Transport profiles

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

### 32. Browser profile

Deberá priorizar:

* CSP;
* clickjacking protection;
* cookies;
* CSRF;
* MIME safety;
* referrer policy.

---

### 33. API profile

Deberá priorizar:

* JSON consistency;
* no HTML errors;
* authentication headers;
* cache policy;
* CORS;
* rate-limit metadata segura.

---

### 34. SPA profile

Deberá priorizar:

* Volt Protocol integrity;
* state exposure control;
* navigation security;
* CSRF;
* hydration safety;
* frontend cache rules.

---

### 35. Download profile

Deberá priorizar:

* filename sanitation;
* content disposition;
* MIME safety;
* authorization continuity;
* range policy;
* cache rules.

---

### 36. Stream profile

Deberá priorizar:

* bounded execution;
* disconnect handling;
* no header mutation after start;
* rate limiting;
* output encoding;
* sensitive-data restrictions.

---

### 37. Response policy resolution

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

### 38. Policy precedence

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

### 39. ResolvedResponseSecurityPolicy

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

### 40. Response builder

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

### 41. SecureHttpResponse

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

### 42. Response immutability

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

### 43. Freeze point

Después del freeze:

* middleware no podrá alterar headers críticos;
* no podrán añadirse cookies;
* no podrá cambiarse content type;
* no podrá cambiarse cache policy;
* no podrá modificarse redirect target.

---

### 44. Late mutation

Una modificación después del freeze deberá:

* lanzar excepción;
* abortar emisión;
* registrarse como violación.

---

### 45. Status code validation

Solo podrán utilizarse códigos válidos.

---

### 46. Status semantics

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

### 47. Custom status codes

Deberán estar limitados a rangos válidos y perfiles compatibles.

---

### 48. Reason phrases

No deberán aceptar contenido derivado de usuario.

Podrán omitirse o generarse desde un registry seguro.

---

### 49. Response Header Model

Los headers representan una superficie de seguridad crítica.

---

### 50. SecureHeaderBag

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

### 51. HeaderName

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

### 52. HeaderValue

Deberá rechazar:

* CR;
* LF;
* null bytes;
* caracteres de control prohibidos;
* valores demasiado largos.

---

### 53. Response splitting

La inyección de:

```text
\r\n
```

podría crear headers o respuestas adicionales.

VoltStack deberá bloquearla en cualquier valor de header.

---

### 54. Header sources

Los headers podrán provenir de:

* framework core;
* middleware;
* controller;
* package;
* proxy integration;
* security policy.

Todos deberán pasar por validación.

---

### 55. Protected headers

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

### 56. Header ownership

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

### 57. Header precedence

Los headers del framework deberán poder sobrescribir valores inseguros definidos por aplicación.

---

### 58. Security Header Registry

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

### 59. SecurityHeaderDefinition

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

### 60. HeaderMergeStrategy

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

### 61. Header canonicalization

El sistema deberá tratar nombres de headers como case-insensitive.

No deberán existir duplicados ambiguos como:

```text
Content-Type
content-type
CONTENT-TYPE
```

---

### 62. Duplicate headers

La política dependerá del header.

* algunos admiten múltiples valores;
* otros deberán existir una sola vez;
* algunos deberán fusionarse;
* otros deberán provocar rechazo.

---

### 63. Hop-by-hop headers

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

### 64. Content-Length

Deberá ser calculado por la capa de transporte cuando aplique.

No deberá confiarse en un valor definido por controlador.

---

### 65. Transfer-Encoding

Deberá ser responsabilidad del servidor HTTP o transport adapter.

---

### 66. Header limits

Se deberán definir límites para:

* número total de headers;
* longitud de nombre;
* longitud de valor;
* tamaño total.

---

### 67. Header overflow

Una respuesta que exceda límites deberá fallar antes de emisión.

---

### 68. Security headers base

El perfil browser deberá considerar al menos:

* `Content-Security-Policy`;
* `Strict-Transport-Security`;
* `X-Content-Type-Options`;
* `Referrer-Policy`;
* `Permissions-Policy`;
* protección contra framing;
* cache policy.

---

### 69. X-Content-Type-Options

Se utilizará:

```text
X-Content-Type-Options: nosniff
```

para perfiles browser compatibles.

---

### 70. MIME sniffing

La respuesta deberá declarar un content type correcto.

`nosniff` no corrige un tipo MIME incorrecto.

---

### 71. Frame protection

La política moderna deberá priorizar CSP:

```text
frame-ancestors
```

Podrá emitir además `X-Frame-Options` para compatibilidad.

---

### 72. Default frame policy

Por defecto, páginas administrativas y privadas no deberán poder embeberse.

---

### 73. Referrer Policy

Se deberá aplicar una política segura por defecto.

Ejemplo conceptual:

```text
strict-origin-when-cross-origin
```

Podrá endurecerse para respuestas confidenciales.

---

### 74. Restricted referrer policy

Para respuestas restringidas podrá utilizarse:

```text
no-referrer
```

---

### 75. Permissions Policy

Deberá deshabilitar capacidades del navegador no utilizadas.

Ejemplos:

* camera;
* microphone;
* geolocation;
* payment;
* usb;
* fullscreen según contexto.

---

### 76. Header profile resolution

Los security headers deberán resolverse según:

* response type;
* route;
* sensitivity;
* browser/API;
* embedding requirements;
* environment.

---

### 77. Header policy conflicts

Ejemplo:

```text
Controller requests frame embedding
Security profile denies frame embedding
```

Deberá prevalecer la política más restrictiva.

---

### 78. Header validation before emission

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

### 79. Validation categories

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

### 80. Resultado de esta entrega

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


## Entrega 2


**Entrega:** 2 de varias
**Cobertura de esta entrega:** Secciones 81–170
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_04.md — Entrega 1`

---

### 81. Content-Type Security

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

### 82. Content type resolution

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

### 83. ResolvedContentType

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

### 84. MediaType

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

### 85. Canonical media types

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

### 86. MIME Registry

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

### 87. Trusted MIME mappings

Los mappings críticos deberán provenir del framework o configuración confiable.

No deberá permitirse que un usuario final registre arbitrariamente:

```text
.php → text/html
```

---

### 88. Extension trust

La extensión de un archivo no será evidencia suficiente del tipo real.

Para descargas sensibles podrá combinarse:

* extensión;
* metadata conocida;
* detección limitada;
* allowlist;
* tipo declarado por storage confiable.

---

### 89. MIME detection limits

La detección heurística no deberá utilizarse como única garantía.

Podrá confundirse con archivos políglotas.

---

### 90. Polyglot files

Un archivo podrá ser válido para más de un parser.

Ejemplos conceptuales:

* imagen con contenido HTML;
* PDF con payload adicional;
* SVG con scripts;
* archivo comprimido con nombres engañosos.

Por ello deberán existir políticas específicas por tipo.

---

### 91. Charset security

Las respuestas textuales deberán declarar charset cuando corresponda.

Valor recomendado:

```text
UTF-8
```

---

### 92. Charset normalization

No deberán aceptarse charsets arbitrarios derivados de input.

---

### 93. UTF-7

No deberá emitirse ni negociarse UTF-7.

---

### 94. Invalid byte sequences

El encoder deberá definir una política:

* reject;
* replace;
* sanitize;
* binary response.

Para contenido HTML y JSON se recomienda rechazar o normalizar de forma segura.

---

### 95. Content-Type ownership

`Content-Type` será propiedad de la capa de contenido.

Un controlador podrá solicitar un tipo, pero deberá validarse.

---

### 96. Content-Type override

La aplicación no deberá cambiar el tipo después de codificar el body.

---

### 97. Body-content coherence

El validator deberá comprobar:

```text
HTML body          → text/html
JSON encoder       → application/json
SSE stream         → text/event-stream
Binary download    → approved binary media type
```

---

### 98. Missing Content-Type

Una respuesta con body no vacío deberá tener un tipo explícito, salvo protocolos especiales controlados.

---

### 99. Empty responses

Las respuestas `204` y `304` deberán seguir reglas específicas y no incluir cuerpos inconsistentes.

---

### 100. Content Negotiation

La negociación deberá ser explícita, limitada y determinista.

---

### 101. Negotiation inputs

Podrá considerar:

* route capabilities;
* `Accept`;
* transport profile;
* controller declaration;
* API version;
* SPA protocol header.

---

### 102. Accept header trust

`Accept` es input no confiable.

Deberá limitarse:

* tamaño;
* cantidad de media ranges;
* parámetros;
* wildcards;
* complejidad.

---

### 103. Negotiation algorithm

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

### 104. No implicit HTML fallback

Una API que no soporte el tipo solicitado no deberá devolver una página HTML de error por defecto.

---

### 105. Negotiation failure

Deberá producir una respuesta controlada, normalmente equivalente a:

```text
406 Not Acceptable
```

con formato seguro y coherente.

---

### 106. Wildcard handling

`*/*` podrá resolverse al tipo por defecto de la ruta.

No deberá activar formatos experimentales o inseguros.

---

### 107. Format aliases

Aliases como:

```text
json
html
xml
```

deberán resolverse mediante registry, no concatenación.

---

### 108. URL format parameters

Parámetros como:

```text
/resource.json
```

deberán validarse contra formatos soportados.

---

### 109. Query format parameters

El uso de:

```text
?format=json
```

deberá estar deshabilitado por defecto o controlado por ruta.

---

### 110. Negotiation ambiguity

Si múltiples tipos tienen igual prioridad, se utilizará una regla determinista.

---

### 111. API version negotiation

No deberá depender únicamente de media types arbitrarios.

El framework deberá validar versiones conocidas.

---

### 112. Vary header

Cuando la representación dependa de `Accept`, deberá considerarse:

```text
Vary: Accept
```

sin crear combinaciones de cache incontrolables.

---

### 113. Vary limits

No deberán añadirse valores derivados arbitrariamente del request.

---

### 114. HTML Response Security

Las respuestas HTML tienen el mayor riesgo de ejecución activa en navegador.

---

### 115. HTML response types

Se distinguirán:

```text
Framework-rendered HTML
Template-rendered HTML
Trusted static HTML
Sanitized rich text
Raw HTML
```

---

### 116. Raw HTML

El raw HTML deberá ser una capacidad restringida.

---

### 117. Safe HTML wrapper

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

### 118. HTML escaping

Todo dato insertado en templates deberá escaparse según contexto.

---

### 119. Contextual escaping

Los contextos no son equivalentes:

* HTML text;
* HTML attribute;
* JavaScript string;
* CSS value;
* URL;
* JSON embedded in HTML.

---

### 120. Template compiler security

El compilador Volt deberá emitir escapes contextuales cuando sea posible.

---

### 121. Explicit unescaped output

Una directiva de output sin escape deberá:

* ser explícita;
* requerir valor trusted;
* producir advertencia o error con string normal;
* integrarse con CSP.

---

### 122. Rich text sanitation

Contenido HTML de usuarios deberá pasar por un sanitizer basado en allowlist.

---

### 123. HtmlSanitizerInterface

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

### 124. HtmlSanitizationPolicy

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

### 125. Event handler attributes

Deberán eliminarse atributos como:

```text
onclick
onerror
onload
```

salvo una capacidad muy restringida que no debería existir para contenido de usuario.

---

### 126. URL attributes

Atributos como:

* `href`;
* `src`;
* `action`;
* `formaction`;
* `poster`;

deberán validar schemes y destinos.

---

### 127. Dangerous URL schemes

Se deberán rechazar o restringir:

```text
javascript:
vbscript:
data:
file:
```

---

### 128. Data URLs

Solo podrán permitirse para tipos concretos y tamaños limitados.

---

### 129. SVG security

SVG deberá considerarse contenido activo.

Puede contener:

* scripts;
* external references;
* event handlers;
* embedded HTML;
* animation.

---

### 130. Inline SVG

Solo deberá permitirse desde fuentes confiables o tras sanitización específica.

---

### 131. Uploaded SVG

Por defecto deberá servirse como descarga o desde un origen aislado, no inline en la aplicación principal.

---

### 132. HTML base tag

El tag `<base>` podrá alterar resolución de URLs.

Deberá estar prohibido en contenido sanitizado.

---

### 133. Meta refresh

Deberá prohibirse en contenido de usuario.

---

### 134. Embedded forms

Los formularios dentro de rich text deberán eliminarse por defecto.

---

### 135. Iframes

Deberán estar prohibidos salvo allowlist explícita de orígenes.

---

### 136. Sandboxed iframe

Cuando se permita embedding, deberá preferirse `sandbox` con capacidades mínimas.

---

### 137. HTML comments

Pueden revelar información interna.

El build de producción podrá eliminar comentarios no necesarios.

---

### 138. Source maps y HTML

No deberán exponerse referencias a source maps internos en producción si contienen rutas o código sensible.

---

### 139. Debug toolbar

No deberá inyectarse en respuestas:

* privadas sensibles;
* descargas;
* streams;
* APIs;
* producción.

---

### 140. Content Security Policy

CSP será el principal mecanismo browser-side para limitar ejecución de contenido.

---

### 141. CSP Engine

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

### 142. CSP directives

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

### 143. Secure CSP baseline

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

### 144. CSP policy composition

Las políticas podrán provenir de:

* framework baseline;
* application profile;
* route metadata;
* component requirements;
* asset pipeline;
* SPA runtime.

---

### 145. CSP conflict resolution

La combinación deberá producir la intersección o la opción más restrictiva cuando sea posible.

---

### 146. CSP widening

Una ruta no deberá ampliar una política global sin una capability explícita.

---

### 147. CSP nonce model

Los scripts inline autorizados deberán usar nonce por respuesta.

---

### 148. CspNonce

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

### 149. Nonce generation

El nonce deberá:

* usar aleatoriedad criptográfica;
* ser único por respuesta;
* tener entropía suficiente;
* no derivarse de request data;
* no reutilizarse entre respuestas.

---

### 150. Nonce exposure

Podrá insertarse en HTML generado, pero no deberá persistirse ni registrarse.

---

### 151. Nonce propagation

El renderer Volt y el asset manager deberán recibir el nonce mediante contexto seguro.

---

### 152. Nonce misuse

No deberá permitirse que contenido de usuario controle el atributo nonce.

---

### 153. CSP hashes

Para scripts o estilos estáticos inline podrán utilizarse hashes aprobados.

---

### 154. Hash generation

Los hashes deberán corresponder exactamente al contenido emitido.

---

### 155. Dynamic inline code

No deberá depender de hashes si el contenido cambia por request.

---

### 156. unsafe-inline

Deberá estar prohibido en perfiles Strict y High Security.

---

### 157. unsafe-eval

Deberá evitarse.

El frontend runtime de VoltStack deberá diseñarse sin requerirlo.

---

### 158. strict-dynamic

Podrá utilizarse cuando la estrategia de carga y compatibilidad lo permitan.

---

### 159. Script source allowlist

Los dominios externos deberán declararse explícitamente.

---

### 160. Wildcard sources

No deberán permitirse wildcards amplios como:

```text
https:
*
```

en perfiles estrictos.

---

### 161. CDN scripts

Scripts de CDN deberán requerir:

* origen aprobado;
* versión fijada;
* SRI cuando aplique;
* política de fallback segura.

---

### 162. Subresource Integrity

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

### 163. SRI requirements

Deberá usarse en assets externos estáticos cuando sea viable.

---

### 164. CSP report mode

VoltStack deberá soportar:

```text
Content-Security-Policy-Report-Only
```

para despliegue gradual.

---

### 165. CSP reports

Los reportes son input no confiable.

Deberán:

* validarse;
* limitarse;
* rate-limitarse;
* evitar log injection;
* no generar respuestas detalladas.

---

### 166. CSP violation endpoint

Deberá estar aislado de la lógica normal de Controllers cuando sea posible.

---

### 167. CSP per route

Rutas especiales podrán declarar requirements adicionales.

```php
#[Csp(
    scriptSources: ['self'],
    frameAncestors: ['none'],
)]
```

---

### 168. Component CSP requirements

Los componentes podrán declarar necesidades, pero no URLs arbitrarias en runtime.

---

### 169. Asset manifest integration

El asset pipeline deberá aportar:

* scripts;
* styles;
* SRI;
* hashes;
* origins;
* nonce requirements.

---

### 170. Resultado de esta entrega

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

## Entrega 3


**Entrega:** 3 de varias
**Cobertura de esta entrega:** Secciones 171–260
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_04.md — Entrega 2`

---

### 171. Trusted Types

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

### 172. Trusted Types scope

Trusted Types aplicará principalmente a:

* VoltStack SPA Runtime;
* adaptadores React, Vue y Svelte;
* runtime de hidratación;
* sistema de componentes;
* plugins frontend;
* código JavaScript propio;
* integraciones de terceros.

---

### 173. Trusted Types CSP directive

El CSP Engine deberá soportar:

```text
require-trusted-types-for 'script'
```

y:

```text
trusted-types
```

---

### 174. Trusted Types enforcement profiles

```php
enum TrustedTypesMode: string
{
    case Disabled = 'disabled';
    case ReportOnly = 'report_only';
    case Enforced = 'enforced';
}
```

---

### 175. Report-only migration

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

### 176. Trusted Types policies

VoltStack deberá usar políticas con nombres controlados.

Ejemplos:

```text
voltstack-runtime
voltstack-hydration
voltstack-sanitized-html
voltstack-assets
```

---

### 177. Policy creation restrictions

La aplicación no deberá crear políticas arbitrarias sin registro previo.

---

### 178. TrustedTypesPolicyRegistry

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

### 179. TrustedTypesPolicyDefinition

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

### 180. Trusted Types capabilities

```php
enum TrustedTypesCapability: string
{
    case CreateHtml = 'create_html';
    case CreateScript = 'create_script';
    case CreateScriptUrl = 'create_script_url';
}
```

---

### 181. HTML policy

La política para crear HTML deberá depender de:

* sanitizer confiable;
* templates compilados;
* contenido estático aprobado;
* hydration payload validado.

---

### 182. Script policy

La creación dinámica de scripts deberá estar prohibida por defecto.

---

### 183. Script URL policy

Solo deberá aceptar URLs provenientes del asset manifest o de una allowlist congelada.

---

### 184. Runtime sinks

El frontend runtime deberá centralizar todos los sinks peligrosos.

No deberán existir asignaciones dispersas a `innerHTML`.

---

### 185. Safe DOM update API

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

### 186. Unsafe plugin behavior

Un plugin frontend que escriba HTML arbitrario deberá:

* declarar capability;
* pasar auditoría;
* ejecutar bajo policy específica;
* poder deshabilitarse por perfil.

---

### 187. Trusted Types violations

Deberán integrarse con:

* CSP reporting;
* frontend telemetry;
* incident classification;
* build provenance.

---

### 188. Hydration and Trusted Types

Los payloads de hidratación no deberán convertirse directamente en HTML.

---

### 189. Hydration payload model

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

### 190. Server-provided HTML fragments

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

### 191. Fragment fingerprints

Un fragment podrá vincularse a:

* component ID;
* build ID;
* rendering version;
* content fingerprint.

---

### 192. Fragment policy

Los fragments no deberán incluir:

* scripts ejecutables;
* event handlers inline;
* iframes no aprobados;
* forms ocultos inesperados;
* URLs no validadas.

---

### 193. Inline Asset Security

Los assets inline incluyen:

* scripts;
* styles;
* SVG;
* JSON embebido;
* preload hints;
* import maps.

---

### 194. Inline script policy

Los scripts inline deberán usar:

* nonce;
* hash CSP;
* archivo externo;

en ese orden de preferencia según el caso.

---

### 195. Dynamic inline scripts

No deberán construirse concatenando datos de usuario.

---

### 196. Inline JSON

El JSON embebido en HTML deberá codificarse de forma segura.

---

### 197. JSON script blocks

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

### 198. Script closing sequence

El encoder deberá impedir que el payload introduzca:

```text
</script>
```

de forma ejecutable.

---

### 199. Hydration script data

Se recomienda emitir estado hidratado mediante:

* JSON seguro;
* atributos `data-*` limitados;
* referencias a endpoint;
* binary protocol futuro.

---

### 200. Inline styles

Los estilos inline deberán evitarse en perfiles estrictos.

---

### 201. Style nonces

Cuando se requieran estilos inline, podrán usar nonces CSP.

---

### 202. User-controlled CSS

CSS controlado por usuarios deberá tratarse como riesgoso.

Puede provocar:

* data exfiltration;
* UI redressing;
* tracking;
* layout manipulation;
* loading de recursos externos.

---

### 203. CSS sanitizer

Contenido CSS permitido deberá pasar por un parser y allowlist.

No por expresiones regulares simples.

---

### 204. Dangerous CSS features

Deberán restringirse:

* `url()`;
* `@import`;
* behavior legacy;
* external fonts;
* custom properties utilizadas como sinks;
* expresiones no soportadas.

---

### 205. Inline SVG assets

Los SVG propios podrán compilarse como assets confiables.

Los SVG dinámicos deberán sanitizarse.

---

### 206. Import Maps

Si VoltStack soporta import maps, estos deberán:

* generarse desde el asset manifest;
* estar protegidos por nonce o hash;
* no aceptar módulos arbitrarios del request;
* usar URLs confiables.

---

### 207. Module scripts

El CSP Engine deberá contemplar:

* `script-src`;
* module graph;
* dynamic import;
* worker scripts;
* crossorigin requirements.

---

### 208. Preload hints

Headers como:

```text
Link: <...>; rel=preload
```

deberán construirse desde recursos validados.

---

### 209. Link header injection

Las URLs y parámetros del header `Link` deberán serializarse con un builder seguro.

---

### 210. Resource hints

Se controlarán:

* preload;
* prefetch;
* preconnect;
* dns-prefetch;
* modulepreload.

---

### 211. Cross-origin resource hints

No deberán generarse para dominios no aprobados.

---

### 212. Clickjacking Protection

Clickjacking intenta cargar una aplicación dentro de un frame para engañar al usuario.

---

### 213. Primary control

La defensa principal será:

```text
Content-Security-Policy: frame-ancestors
```

---

### 214. Compatibility control

Podrá emitirse además:

```text
X-Frame-Options
```

---

### 215. FramePolicy

```php
enum FramePolicy: string
{
    case Deny = 'deny';
    case SameOrigin = 'same_origin';
    case AllowListedOrigins = 'allow_listed_origins';
}
```

---

### 216. Default frame policy

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

### 217. Same-origin framing

Solo deberá habilitarse cuando una funcionalidad real lo requiera.

---

### 218. Allowed frame origins

Los orígenes deberán ser:

* exactos;
* normalizados;
* con scheme explícito;
* sin wildcards amplios;
* definidos por configuración confiable.

---

### 219. Dynamic frame origins

No deberán derivarse directamente de parámetros de request.

---

### 220. Embeddable routes

Una ruta embebible deberá declarar explícitamente:

```php
#[Embeddable(
    origins: ['https://portal.example.com']
)]
```

---

### 221. Embed token

Para widgets embebibles podrá requerirse un token scoped.

---

### 222. Embedded application profile

El perfil embebido deberá ajustar:

* cookies;
* SameSite;
* CORS;
* CSP;
* postMessage;
* navigation;
* referrer policy.

---

### 223. UI redressing

Además de framing, se deberán considerar:

* overlays;
* transparent controls;
* fullscreen abuse;
* pointer lock;
* popups.

---

### 224. Frame busting JavaScript

No deberá considerarse una defensa suficiente.

---

### 225. postMessage security

Los componentes embebidos deberán validar:

* `event.origin`;
* message schema;
* message type;
* sender window;
* replay context.

---

### 226. Wildcard postMessage

No deberá usarse:

```javascript
postMessage(data, '*')
```

para datos sensibles.

---

### 227. HTTPS Enforcement

La seguridad del transporte dependerá de HTTPS correctamente aplicado.

---

### 228. Secure scheme resolution

La aplicación deberá determinar el scheme real únicamente después de validar trusted proxies.

---

### 229. Direct request scheme

Sin proxy confiable, se utilizará la información directa de la conexión.

---

### 230. Proxy-provided scheme

Headers como:

* `Forwarded`;
* `X-Forwarded-Proto`;

solo serán confiables desde proxies registrados.

---

### 231. HTTPS redirect

Cuando se requiera HTTPS, la redirección deberá usar:

* host validado;
* path normalizado;
* status code apropiado;
* query controlado.

---

### 232. HTTPS redirect loops

La configuración deberá detectar:

* proxy no confiable;
* scheme incorrecto;
* forwarded headers inconsistentes;
* múltiples capas de proxy.

---

### 233. Sensitive route HTTPS requirement

Rutas sensibles no deberán ejecutarse sobre HTTP.

Ejemplos:

* login;
* tokens;
* pagos;
* administración;
* datos confidenciales.

---

### 234. Secure transport guard

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

### 235. Strict-Transport-Security

HSTS indica al navegador que el dominio deberá utilizar HTTPS.

---

### 236. HstsPolicy

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

### 237. HSTS emission

Solo deberá emitirse sobre conexiones HTTPS consideradas seguras.

---

### 238. HSTS default

Un perfil de producción podrá usar un `max-age` amplio después de validar la infraestructura.

---

### 239. includeSubDomains

Solo deberá activarse cuando todos los subdominios relevantes soporten HTTPS.

---

### 240. HSTS preload

La opción `preload` implica compromisos operativos adicionales.

No deberá activarse automáticamente.

---

### 241. HSTS rollback risk

Una política larga puede dificultar recuperar subdominios que no soporten HTTPS.

---

### 242. Development environment

HSTS deberá deshabilitarse o aislarse en desarrollo local.

---

### 243. HTTPS downgrade

VoltStack deberá impedir enlaces y redirects que degraden de HTTPS a HTTP en contextos protegidos.

---

### 244. Mixed content

El CSP Engine podrá emitir:

```text
upgrade-insecure-requests
```

y, según compatibilidad:

```text
block-all-mixed-content
```

---

### 245. Secure URL generation

El URL Generator deberá conocer:

* trusted scheme;
* validated host;
* route requirements;
* proxy context.

---

### 246. Absolute URL security

Las URLs absolutas no deberán depender de un `Host` no validado.

---

### 247. Referrer Policy avanzada

La política deberá adaptarse a la sensibilidad de la respuesta.

---

### 248. ReferrerPolicy

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

### 249. Default referrer policy

Para navegación general:

```text
strict-origin-when-cross-origin
```

será un baseline razonable.

---

### 250. Confidential response policy

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

### 251. URL sensitivity

Datos sensibles no deberán colocarse en URLs.

La referrer policy reduce exposición, pero no corrige una URL insegura.

---

### 252. Query string leakage

Tokens, correos, IDs sensibles y secretos no deberán persistir en query strings.

---

### 253. Per-route referrer policy

Las rutas podrán endurecer la política, pero no debilitar un hard baseline sin capability.

---

### 254. Meta referrer

La política deberá emitirse preferentemente como header.

El meta tag no sustituye la política de transporte.

---

### 255. Permissions Policy avanzada

Permissions Policy limitará capacidades del navegador por documento y frames.

---

### 256. PermissionsPolicyEngine

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

### 257. Browser capabilities

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

### 258. Default deny

Las capacidades no utilizadas deberían denegarse.

---

### 259. Capability allowlists

Las allowlists podrán incluir:

* `self`;
* orígenes exactos;
* ningún origen.

No deberán aceptar input directo del cliente.

---

### 260. Resultado de esta entrega

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


## Entrega 4


**Entrega:** 4 de varias
**Cobertura de esta entrega:** Secciones 261–350
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_04.md — Entrega 3`

---

### 261. Permissions Policy enforcement

Permissions Policy no deberá tratarse como un header decorativo.

Su resolución deberá estar conectada con:

* requerimientos reales de la ruta;
* capacidades de componentes;
* iframes embebidos;
* frontend runtime;
* perfil de seguridad;
* clasificación de sensibilidad.

---

### 262. Capability declaration

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

### 263. BrowserCapabilityScope

```php
enum BrowserCapabilityScope: string
{
    case None = 'none';
    case Self = 'self';
    case AllowedOrigins = 'allowed_origins';
}
```

---

### 264. Capability aggregation

Las capacidades requeridas podrán provenir de:

* route metadata;
* controller metadata;
* component manifests;
* embed configuration;
* frontend asset manifest.

La política final deberá aplicar la unión de requerimientos permitidos y posteriormente limitarla mediante el perfil global.

---

### 265. Capability escalation

Una ruta podrá solicitar una capacidad adicional, pero no deberá recibirla cuando:

* el perfil la prohíba;
* el origen no esté autorizado;
* la respuesta sea restricted;
* el componente no esté firmado o registrado;
* el transporte no sea seguro.

---

### 266. Capability downgrade

El framework podrá reducir permisos en tiempo de ejecución según:

* autenticación;
* tenant;
* tipo de dispositivo;
* contexto de embedding;
* clasificación de respuesta.

---

### 267. Runtime capability request

El frontend no deberá poder ampliar la política enviando metadata en el request.

---

### 268. Permissions Policy builder

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

### 269. Canonical serialization

La política deberá serializarse de forma determinista.

Esto facilita:

* testing;
* cache;
* auditoría;
* comparación entre builds.

---

### 270. Unsupported directives

El framework podrá omitir directivas no soportadas por el navegador, pero no deberá sustituirlas por permisos más amplios.

---

### 271. Iframe permission delegation

Un documento padre deberá delegar explícitamente capacidades a iframes autorizados.

---

### 272. allow attribute

El atributo `allow` de un iframe deberá generarse desde el mismo modelo de capabilities que el header.

---

### 273. Header-iframe consistency

No deberá existir una delegación en el iframe que contradiga la política global.

---

### 274. Sensitive capabilities

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

### 275. Permission audit events

Se deberán generar eventos cuando:

* una ruta solicite una capability prohibida;
* una capability sea reducida;
* un iframe intente delegación no permitida;
* un plugin frontend requiera permisos adicionales.

---

### 276. Cross-Origin Isolation

Cross-origin isolation permite habilitar capacidades avanzadas del navegador con mayor separación de procesos y recursos.

Puede ser necesaria para:

* `SharedArrayBuffer`;
* temporizadores de alta precisión;
* ciertos runtimes intensivos;
* procesamiento multimedia;
* herramientas de desarrollo especializadas.

---

### 277. Isolation components

El aislamiento moderno combina principalmente:

* COOP;
* COEP;
* CORP;
* CORS correcto;
* resource loading compatible.

---

### 278. CrossOriginIsolationMode

```php
enum CrossOriginIsolationMode: string
{
    case Disabled = 'disabled';
    case ReportOnly = 'report_only';
    case Enforced = 'enforced';
}
```

---

### 279. Isolation eligibility

No todas las rutas deberán usar aislamiento.

Será apropiado para:

* aplicaciones cerradas;
* herramientas internas;
* editores;
* runtimes que controlan todos sus assets.

Podrá ser problemático para páginas con múltiples integraciones externas.

---

### 280. Isolation profile

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

### 281. Cross-Origin-Opener-Policy

COOP controla la relación entre ventanas y browsing context groups.

---

### 282. COOP values

```php
enum CrossOriginOpenerPolicy: string
{
    case UnsafeNone = 'unsafe-none';
    case SameOriginAllowPopups = 'same-origin-allow-popups';
    case SameOrigin = 'same-origin';
}
```

---

### 283. COOP default

Para rutas que requieran aislamiento fuerte:

```text
same-origin
```

---

### 284. Popup compatibility

`same-origin` puede romper integraciones que dependen de `window.opener`.

Ejemplos:

* OAuth popup;
* payment popup;
* external identity providers.

---

### 285. OAuth routes

Las rutas involucradas en autenticación mediante popup podrán requerir:

```text
same-origin-allow-popups
```

o un flujo de navegación distinto.

---

### 286. Opener isolation

La aplicación no deberá depender de `window.opener` sin validar:

* origin;
* flow ID;
* nonce;
* expected window relationship.

---

### 287. Cross-Origin-Embedder-Policy

COEP controla si el documento puede cargar recursos cross-origin que no concedan permiso explícito.

---

### 288. COEP values

```php
enum CrossOriginEmbedderPolicy: string
{
    case UnsafeNone = 'unsafe-none';
    case RequireCorp = 'require-corp';
    case Credentialless = 'credentialless';
}
```

---

### 289. Require-Corp

`require-corp` exigirá que recursos cross-origin:

* utilicen CORS;
* o declaren CORP compatible.

---

### 290. Credentialless

`credentialless` podrá facilitar ciertos recursos cross-origin al omitir credenciales, pero deberá evaluarse cuidadosamente.

---

### 291. COEP compatibility

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

### 292. Cross-Origin-Resource-Policy

CORP permite que un recurso declare quién puede cargarlo.

---

### 293. CORP values

```php
enum CrossOriginResourcePolicy: string
{
    case CrossOrigin = 'cross-origin';
    case SameSite = 'same-site';
    case SameOrigin = 'same-origin';
}
```

---

### 294. Default resource policy

Para recursos privados:

```text
same-origin
```

Para assets compartidos entre subdominios confiables podrá considerarse:

```text
same-site
```

---

### 295. Public assets

Un asset público destinado a múltiples orígenes podrá declarar:

```text
cross-origin
```

solo cuando esa distribución sea intencional.

---

### 296. Resource classification

El CORP deberá resolverse según:

* sensibilidad;
* asset visibility;
* authentication;
* CDN strategy;
* tenant isolation.

---

### 297. API responses and CORP

Las respuestas API privadas no deberán quedar cargables como recursos cross-origin no autorizados.

---

### 298. Cross-origin isolation validator

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

### 299. Isolation consistency

El validator deberá comprobar:

* COOP presente cuando sea requerido;
* COEP compatible;
* CORP adecuado;
* CSP compatible;
* resources declarados;
* CORS coherente;
* iframes permitidos.

---

### 300. Report-only headers

VoltStack podrá emitir variantes report-only durante migración cuando el navegador las soporte.

---

### 301. Isolation reports

Los reportes deberán tratarse como input no confiable y pasar por límites de tamaño y frecuencia.

---

### 302. Origin-Agent-Cluster

El header:

```text
Origin-Agent-Cluster: ?1
```

puede solicitar aislamiento por origen.

---

### 303. Origin agent cluster policy

Podrá habilitarse para aplicaciones que quieran evitar compartir ciertos recursos de proceso entre orígenes relacionados.

---

### 304. Agent cluster compatibility

No deberá asumirse que todos los navegadores aplicarán exactamente el mismo comportamiento.

---

### 305. Browser isolation profiles

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

### 306. Standard profile

Podrá usar:

* COOP moderado;
* CORP para recursos privados;
* sin COEP estricto;
* CSP fuerte.

---

### 307. Embedded profile

Deberá coordinar:

* frame ancestors;
* permissions delegation;
* CORS;
* postMessage;
* cookies;
* CORP.

---

### 308. CrossOriginIsolated profile

Requerirá:

* COOP compatible;
* COEP;
* recursos compatibles con CORP o CORS;
* workers compatibles;
* validación de assets.

---

### 309. OAuthPopup profile

Deberá preservar compatibilidad con el popup sin debilitar el resto de la aplicación.

---

### 310. LegacyCompatible profile

Podrá omitir mecanismos no soportados, pero mantendrá:

* CSP;
* MIME protection;
* frame protection;
* secure cookies;
* HTTPS.

---

### 311. CORS Security Model

CORS controla qué orígenes pueden leer respuestas desde navegadores.

No es un mecanismo general de autenticación.

---

### 312. CORS misconception

Permitir un origen no implica que ese origen sea confiable para todas las operaciones.

---

### 313. CORS trust boundary

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

### 314. CorsPolicy

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

### 315. CORS policy resolution

Las políticas podrán definirse por:

* application;
* route group;
* route;
* API version;
* tenant;
* environment.

---

### 316. Default CORS policy

Por defecto:

```text
No cross-origin access
```

---

### 317. Origin header trust

`Origin` es input no confiable.

Deberá:

* parsearse estrictamente;
* normalizarse;
* validar scheme;
* validar host;
* validar port;
* impedir valores ambiguos.

---

### 318. Origin structure

Un origin válido estará formado por:

```text
scheme + host + port
```

No incluye path, query ni fragment.

---

### 319. Null origin

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

### 320. Exact origin matching

La estrategia preferida será coincidencia exacta.

---

### 321. Origin allowlist

Ejemplo:

```php
[
    'https://app.example.com',
    'https://admin.example.com',
]
```

---

### 322. Wildcard origins

El uso de:

```text
*
```

solo deberá permitirse para recursos públicos sin credenciales.

---

### 323. Wildcard with credentials

No deberá combinarse wildcard origin con credenciales.

---

### 324. Reflected origins

No deberá reflejarse cualquier `Origin` recibido.

Solo podrá reflejarse después de validarlo contra la política.

---

### 325. Regex origin matchers

Deberán evitarse cuando una lista exacta sea suficiente.

---

### 326. Safe subdomain matcher

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

### 327. Suffix matching risk

No deberá validarse mediante:

```text
endsWith("example.com")
```

porque permitiría dominios como:

```text
evil-example.com
```

---

### 328. Internationalized domains

Los hosts deberán normalizarse de forma segura antes de comparar.

---

### 329. Port handling

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

### 330. Dynamic tenant origins

En sistemas multi-tenant, los origins podrán resolverse desde configuración confiable del tenant.

No desde parámetros arbitrarios de la petición.

---

### 331. Tenant origin registry

```php
interface TenantOriginRegistryInterface
{
    public function allowedOrigins(string $tenantId): OriginMatcherSet;
}
```

---

### 332. Tenant context validation

El tenant utilizado para resolver CORS deberá provenir del routing y tenant resolution confiable.

---

### 333. CORS and authentication

CORS no sustituye:

* authentication;
* authorization;
* CSRF protection;
* tenant validation.

---

### 334. Credentialed CORS

Cuando `allowCredentials` sea verdadero:

* el origin deberá ser explícito;
* no podrá usarse wildcard;
* cookies deberán cumplir SameSite y Secure;
* CSRF deberá evaluarse;
* cache deberá incluir `Vary: Origin`.

---

### 335. Access-Control-Allow-Credentials

Solo deberá emitirse cuando la ruta realmente admita credenciales cross-origin.

---

### 336. Credential minimization

Una API debería preferir tokens explícitos antes que cookies cross-site cuando la arquitectura lo permita.

---

### 337. Allowed methods

Los métodos deberán declararse explícitamente.

Ejemplo:

```php
['GET', 'POST', 'PUT', 'DELETE']
```

---

### 338. Method normalization

Los métodos deberán compararse mediante representación canónica.

---

### 339. Unsafe methods

Operaciones mutables requerirán controles adicionales.

---

### 340. Allowed request headers

Solo deberán permitirse headers necesarios.

---

### 341. Arbitrary request headers

No deberá reflejarse ciegamente:

```text
Access-Control-Request-Headers
```

---

### 342. Header name validation

Cada header solicitado deberá:

* ser válido;
* estar normalizado;
* pertenecer a allowlist;
* no ser hop-by-hop;
* no ser reservado.

---

### 343. Exposed response headers

Los headers visibles al frontend cross-origin deberán limitarse.

---

### 344. Sensitive exposed headers

No deberán exponerse:

* internal trace IDs sensibles;
* server internals;
* authorization metadata;
* session identifiers;
* stack data.

---

### 345. CORS preflight

Las peticiones `OPTIONS` de preflight deberán resolverse antes de ejecutar lógica de negocio.

---

### 346. Preflight validator

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

### 347. Preflight inputs

Deberá validar:

* origin;
* requested method;
* requested headers;
* private network request;
* route existence;
* route CORS policy.

---

### 348. Preflight authentication

Normalmente el preflight no deberá requerir sesión de aplicación para responder, pero tampoco deberá revelar información sensible sobre rutas internas.

---

### 349. Route disclosure

VoltStack deberá evitar diferencias excesivas que permitan enumerar rutas protegidas mediante preflight.

---

### 350. Resultado de esta entrega

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

## Entrega 5


**Entrega:** 5 de varias
**Cobertura:** Secciones **351–440**

---

### 351. Preflight Response Generation

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

### 352. OPTIONS Short-Circuit

Cuando una petición corresponda a un **Preflight válido**, el framework deberá finalizar inmediatamente el procesamiento.

No deberán ejecutarse:

* Controllers
* Middlewares de negocio
* Renderizadores
* ORM
* Sistema de eventos
* Hydration Runtime

---

### 353. Preflight Response Builder

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

### 354. Invalid Preflight

Las solicitudes inválidas deberán responder con un error controlado.

Nunca deberán revelar:

* existencia de rutas
* nombres de controladores
* métodos soportados internamente
* políticas de autorización

---

### 355. Missing Origin

Una petición OPTIONS sin Origin no deberá tratarse automáticamente como Preflight.

---

### 356. Missing Access-Control-Request-Method

La ausencia del encabezado correspondiente impedirá considerar la petición como Preflight.

---

### 357. Unsupported Requested Method

Si el método solicitado no pertenece a la política CORS:

```text
Origin ✔
Method ✘
```

la petición deberá rechazarse.

---

### 358. Unsupported Requested Headers

Los encabezados solicitados deberán validarse individualmente.

---

### 359. Canonical Header Comparison

La comparación utilizará nombres normalizados.

```text
Content-Type
content-type
CONTENT-TYPE
```

representan el mismo encabezado.

---

### 360. Access-Control-Max-Age

El tiempo de cache del Preflight deberá configurarse cuidadosamente.

No deberá exceder políticas corporativas.

---

### 361. Preflight Cache

Los navegadores pueden reutilizar la respuesta.

El framework deberá generar respuestas deterministas.

---

### 362. Vary Header

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

### 363. Cache Poisoning Prevention

Nunca deberá mezclarse una respuesta CORS entre distintos orígenes.

---

### 364. Response Reuse

Las respuestas cacheadas deberán depender de:

* Origin
* Método
* Encabezados solicitados
* Perfil de seguridad

---

### 365. Preflight Metrics

Se recomienda registrar:

* total
* permitidos
* rechazados
* origen
* tenant
* endpoint

---

### 366. Private Network Access

Los navegadores modernos introducen restricciones para recursos de redes privadas.

---

### 367. PNA Policy

```php
enum PrivateNetworkPolicy
{
    case Disabled;
    case AllowTrusted;
    case AllowAll;
}
```

---

### 368. Access-Control-Allow-Private-Network

Solo deberá emitirse cuando:

* exista una política explícita
* el origen sea confiable
* el endpoint realmente lo requiera

---

### 369. Internal APIs

Las APIs administrativas no deberán habilitar PNA por defecto.

---

### 370. Local Network Exposure

El framework deberá impedir exponer accidentalmente servicios internos mediante configuraciones CORS incorrectas.

---

### 371. CSRF Security Model

Cross Site Request Forgery sigue siendo una amenaza para aplicaciones basadas en cookies.

---

### 372. Threat Model

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

### 373. CSRF Protection Scope

La protección deberá aplicarse sobre:

* formularios
* SPA
* AJAX
* Fetch API
* Component Runtime
* Volt Protocol
* Uploads

---

### 374. Safe Methods

Por defecto:

```text
GET
HEAD
OPTIONS
TRACE
```

no deberán modificar estado.

---

### 375. Unsafe Methods

Se consideran:

* POST
* PUT
* PATCH
* DELETE

y cualquier método personalizado que modifique recursos.

---

### 376. CsrfProtectionMode

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

### 377. Default Strategy

VoltStack utilizará tokens sincronizados como estrategia principal.

---

### 378. CsrfToken

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

### 379. Token Entropy

Los tokens deberán generarse mediante un RNG criptográficamente seguro.

---

### 380. Token Length

Se recomienda una longitud mínima equivalente a 256 bits de entropía.

---

### 381. Token Rotation

Podrá configurarse:

* nunca
* por sesión
* por autenticación
* por request crítico

---

### 382. Token Lifetime

Los tokens expirados deberán rechazarse.

---

### 383. Session Binding

El token deberá estar asociado a la sesión correspondiente.

---

### 384. User Binding

Opcionalmente podrá asociarse también al usuario autenticado.

---

### 385. Double Submit Cookies

VoltStack soportará esta estrategia para escenarios específicos.

---

### 386. Double Submit Validation

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

### 387. Constant-Time Comparison

Las comparaciones de tokens deberán evitar ataques por timing.

---

### 388. Missing Token

La ausencia del token deberá producir rechazo inmediato.

---

### 389. Invalid Token

Los tokens inválidos no deberán indicar cuál parte falló.

---

### 390. Replay Protection

Podrá habilitarse protección contra reutilización de tokens en operaciones críticas.

---

### 391. SPA CSRF

El runtime SPA deberá transportar automáticamente el token.

---

### 392. Volt Protocol CSRF

El protocolo Volt incorporará el token dentro del contexto de hidratación.

Nunca en URLs.

---

### 393. Hydration Requests

Todas las solicitudes de hidratación mutables deberán validar CSRF.

---

### 394. AJAX Protection

Las solicitudes AJAX deberán incluir:

```text
X-CSRF-TOKEN
```

o el encabezado configurado.

---

### 395. API Tokens

Las APIs autenticadas mediante Bearer Tokens podrán deshabilitar CSRF cuando no utilicen cookies.

---

### 396. Stateless APIs

Las APIs verdaderamente stateless no requerirán protección CSRF.

---

### 397. Mixed Authentication

Aplicaciones híbridas deberán evaluar individualmente cada endpoint.

---

### 398. Cookie Security

Las cookies representan uno de los activos más sensibles del transporte HTTP.

---

### 399. SecureCookie

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

### 400. Cookie Validation

Toda cookie deberá validarse antes de emitirse.

---

### 401. Cookie Name Rules

Los nombres deberán cumplir RFC y evitar caracteres ambiguos.

---

### 402. Cookie Prefixes

Se soportarán:

```text
__Host-
__Secure-
```

---

### 403. __Host-

Una cookie con este prefijo deberá cumplir:

* Secure
* Path=/
* Sin Domain

---

### 404. __Secure-

Requerirá:

```text
Secure=true
```

---

### 405. Secure Attribute

Las cookies sensibles deberán marcarse siempre como Secure.

---

### 406. HttpOnly

Las cookies de sesión deberán utilizar HttpOnly.

---

### 407. SameSite

```php
enum SameSitePolicy
{
    case Strict;
    case Lax;
    case None;
}
```

---

### 408. Default SameSite

Se recomienda:

```text
Lax
```

para la mayoría de aplicaciones.

---

### 409. Strict Mode

Ideal para operaciones altamente sensibles.

---

### 410. None Mode

Solo deberá utilizarse junto con:

```text
Secure
```

---

### 411. Cookie Path

El Path deberá limitar el alcance cuando sea posible.

---

### 412. Cookie Domain

El Domain deberá minimizarse.

---

### 413. Cookie Lifetime

Se distinguirán:

* sesión
* persistentes
* temporales

---

### 414. Session Cookie

Las cookies de sesión deberán expirar al finalizar la sesión del navegador cuando así se configure.

---

### 415. Session Fixation

VoltStack deberá regenerar el identificador de sesión tras autenticación.

---

### 416. Session Rotation

También podrá regenerarse después de:

* elevación de privilegios
* cambio de tenant
* MFA
* recuperación de contraseña

---

### 417. Session Identifier

Nunca deberá exponerse en:

* URLs
* HTML
* logs

---

### 418. Cookie Encryption

Las cookies podrán cifrarse mediante el sistema criptográfico del framework.

---

### 419. Cookie Signing

Adicionalmente podrán firmarse.

---

### 420. Tampering Detection

Una cookie modificada deberá invalidarse completamente.

---

### 421. Cookie Serialization

El serializador deberá ser determinista.

---

### 422. Oversized Cookies

Se impondrán límites para evitar problemas de interoperabilidad.

---

### 423. Cookie Limits

Se controlarán:

* número
* tamaño
* longitud del nombre
* longitud del valor

---

### 424. Third-Party Cookies

Su utilización deberá minimizarse.

---

### 425. Browser Compatibility

Las políticas deberán adaptarse a diferencias conocidas entre navegadores.

---

### 426. Cookie Audit

Se registrarán:

* creación
* modificación
* eliminación
* expiración

---

### 427. SecureCookieBag

```php
interface SecureCookieBagInterface
{
    public function add(SecureCookie $cookie): void;

    public function freeze(): void;
}
```

---

### 428. Cookie Freeze

Después del freeze ninguna cookie podrá modificarse.

---

### 429. Immutable Response Cookies

Las cookies formarán parte de la respuesta inmutable.

---

### 430. Cookie Validation Pipeline

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

### 431. Session Security Context

El contexto de transporte deberá conocer:

* Session ID
* User ID
* Tenant
* Authentication State

---

### 432. Tenant Isolation

Las cookies de un tenant no deberán interferir con otro.

---

### 433. Multi-Domain Deployments

Se soportarán políticas independientes por dominio.

---

### 434. Logout

El cierre de sesión deberá invalidar:

* sesión
* cookies
* tokens CSRF
* identificadores derivados

---

### 435. Forced Logout

El sistema podrá invalidar sesiones comprometidas globalmente.

---

### 436. Cookie Metrics

Se recopilarán métricas sobre:

* emisión
* rechazo
* expiración
* rotación

---

### 437. Security Events

Eventos relevantes:

* InvalidCsrfToken
* CookieTampered
* SessionRotated
* SessionFixationPrevented

---

### 438. Testing Strategy

Se incluirán pruebas para:

* CSRF
* Cookies
* SameSite
* Secure
* HttpOnly
* Rotation

---

### 439. ADR

**ADR-093**

> Todas las cookies emitidas por VoltStack pasarán por un pipeline criptográfico y de validación antes de ser enviadas al cliente.

---

### 440. Resultado de esta entrega

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

## Entrega 6


**Entrega:** 6 de varias
**Cobertura:** Secciones **441–540**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_04.md — Entrega 5`

---

### 441. Redirect Security Model

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

### 442. Redirect pipeline

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

### 443. RedirectResponse

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

### 444. RedirectTarget

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

### 445. RedirectTargetType

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

### 446. Internal routes

Las redirecciones internas deberán generarse preferentemente mediante nombres de ruta.

```php
return Response::redirectToRoute(
    name: 'dashboard',
    parameters: ['tenant' => $tenant->id]
);
```

---

### 447. Route-based redirects

El URL Generator deberá:

* validar parámetros;
* aplicar encoding;
* usar host confiable;
* resolver HTTPS;
* respetar tenant;
* evitar path traversal.

---

### 448. Raw redirect URLs

Las URLs crudas deberán considerarse no confiables hasta validación.

---

### 449. Open redirect

Nunca deberá utilizarse directamente un valor como:

```text
?next=https://evil.example
```

para construir el header `Location`.

---

### 450. Safe return URL

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

### 451. Relative redirects

Las redirecciones relativas internas serán preferibles cuando no se necesite una URL absoluta.

---

### 452. Scheme validation

Solo deberán permitirse schemes explícitamente soportados.

Por defecto:

```text
https
http en desarrollo controlado
```

---

### 453. Dangerous redirect schemes

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

### 454. Protocol-relative URLs

Las URLs como:

```text
//example.com/path
```

deberán rechazarse o normalizarse explícitamente.

No deberán heredarse silenciosamente del scheme actual.

---

### 455. Backslash normalization

Los parsers deberán controlar diferencias entre:

```text
/
\
```

para evitar interpretaciones divergentes entre servidor y navegador.

---

### 456. Encoded separators

Deberán validarse secuencias como:

```text
%2f
%5c
%2e
```

cuando puedan cambiar la interpretación del destino.

---

### 457. User information in URLs

No deberán permitirse destinos con credenciales embebidas.

Ejemplo prohibido:

```text
https://trusted.example@evil.example
```

---

### 458. Host normalization

El host deberá:

* convertirse a forma canónica;
* validar IDN;
* eliminar ambigüedades;
* comparar puerto;
* comparar scheme;
* rechazar caracteres inválidos.

---

### 459. Same-origin redirect

Una URL solo será same-origin si coinciden:

```text
scheme
host
port
```

---

### 460. Same-site redirect

Same-site no deberá confundirse con same-origin.

Podrá involucrar subdominios distintos y riesgos diferentes.

---

### 461. Trusted external redirects

Las redirecciones externas deberán requerir una política explícita.

---

### 462. Redirect allowlist

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

### 463. Redirect purpose

Una entrada de allowlist deberá indicar su propósito.

Ejemplos:

* OAuth;
* pagos;
* documentación;
* soporte;
* portal corporativo.

---

### 464. Redirect capability

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

### 465. Controller redirect permissions

Los Controllers no deberán emitir redirecciones externas salvo que posean la capability correspondiente.

---

### 466. Signed redirect state

Flujos externos deberán utilizar state firmado cuando sea necesario.

---

### 467. OAuth state

Los callbacks OAuth deberán validar:

* state;
* sesión;
* provider;
* redirect URI;
* expiración;
* nonce cuando aplique.

---

### 468. Redirect parameter leakage

No deberán enviarse secretos en query strings de redirecciones.

---

### 469. Fragment handling

Los fragments no se envían al servidor, pero pueden contener información visible al frontend.

Deberán utilizarse con precaución.

---

### 470. Redirect status codes

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

### 471. Method preservation

`307` y `308` preservan el método y body.

No deberán utilizarse inadvertidamente después de operaciones sensibles.

---

### 472. Post-Redirect-Get

Después de formularios mutables se recomienda:

```text
POST
  ↓
303 See Other
  ↓
GET
```

---

### 473. Permanent redirect safety

Los redirects permanentes pueden ser cacheados por clientes e intermediarios.

Deberán utilizarse solo cuando el cambio sea estable.

---

### 474. Redirect loop detection

El framework podrá detectar:

* destino igual a origen;
* ciclos conocidos;
* exceso de saltos internos;
* normalizaciones equivalentes.

---

### 475. Redirect audit

Las redirecciones externas deberán poder auditarse con:

* origen;
* destino;
* route;
* user;
* tenant;
* purpose;
* execution ID.

---

### 476. Redirect response body

El body opcional de una redirección no deberá reflejar sin escape la URL destino.

---

### 477. Location header security

`Location` deberá generarse exclusivamente mediante un serializador de URI seguro.

---

### 478. File Response Security

Las respuestas de archivo pueden exponer:

* archivos arbitrarios;
* rutas internas;
* secretos;
* contenido activo;
* metadata;
* enlaces simbólicos;
* archivos temporales.

---

### 479. File response model

```php
interface FileResponseInterface
{
    public function source(): SecureFileSource;

    public function disposition(): ContentDisposition;

    public function mediaType(): MediaType;
}
```

---

### 480. SecureFileSource

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

### 481. File source types

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

### 482. Raw filesystem paths

Los Controllers no deberán devolver rutas arbitrarias directamente.

---

### 483. Storage abstraction

El framework deberá resolver archivos mediante:

* disk;
* object identifier;
* tenant scope;
* access policy;
* storage adapter.

---

### 484. Path traversal

Deberán bloquearse secuencias como:

```text
../
..\
%2e%2e
```

y sus variantes normalizadas.

---

### 485. Canonical path validation

La ruta final deberá resolverse y comprobarse dentro de una raíz autorizada.

---

### 486. Symlink policy

Los enlaces simbólicos deberán:

* estar prohibidos;
* o resolverse y revalidarse;
* o limitarse a roots confiables.

---

### 487. Time-of-check to time-of-use

La validación y apertura del archivo deberán minimizar condiciones TOCTOU.

---

### 488. File descriptors

Cuando sea posible, el sistema deberá validar y servir el mismo descriptor abierto.

---

### 489. Tenant-scoped files

Todo archivo multi-tenant deberá validar:

```text
Requested tenant
    =
Authenticated tenant context
    =
File ownership tenant
```

---

### 490. Authorization continuity

La autorización deberá mantenerse desde la resolución hasta la apertura del stream.

---

### 491. Temporary files

Los archivos temporales deberán:

* tener permisos mínimos;
* nombres no predecibles;
* cleanup definido;
* storage aislado;
* lifetime limitado.

---

### 492. Remote files

No deberá convertirse una URL arbitraria en descarga proxy.

---

### 493. Server-side request forgery

Los archivos remotos deberán pasar por controles SSRF:

* schemes permitidos;
* DNS validation;
* IP range policy;
* redirect limits;
* timeout;
* size limits.

---

### 494. MIME validation for files

El tipo MIME deberá resolverse según una política confiable.

---

### 495. Dangerous inline file types

Deberán tratarse con especial cuidado:

* HTML;
* SVG;
* XML;
* JavaScript;
* PDFs con contenido activo;
* documentos ofimáticos;
* archivos multimedia complejos.

---

### 496. Inline vs attachment

```php
enum ContentDispositionType: string
{
    case Inline = 'inline';
    case Attachment = 'attachment';
}
```

---

### 497. Default disposition

Los uploads de usuario deberán servirse como attachment por defecto, especialmente cuando sean contenido activo.

---

### 498. Safe inline rendering

Solo deberá permitirse inline cuando:

* el MIME sea aprobado;
* el contenido sea confiable;
* la ruta esté autorizada;
* CSP y sandbox sean compatibles.

---

### 499. Content-Disposition builder

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

### 500. Download filename security

Los nombres deberán limpiarse para impedir:

* CRLF injection;
* path separators;
* null bytes;
* nombres reservados;
* extensiones engañosas;
* caracteres de control.

---

### 501. SafeDownloadFilename

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

### 502. Filename normalization

Se deberá aplicar normalización Unicode consistente.

---

### 503. Double extensions

Nombres como:

```text
invoice.pdf.exe
```

deberán tratarse como sospechosos.

---

### 504. Extension-MIME coherence

La extensión presentada deberá ser coherente con el MIME emitido.

---

### 505. International filenames

Se deberá soportar correctamente `filename*` con encoding seguro.

---

### 506. Header injection in filenames

Todo salto de línea deberá provocar rechazo.

---

### 507. File size limits

Se deberán definir límites por:

* ruta;
* tenant;
* user;
* tipo;
* storage profile.

---

### 508. Large file handling

Los archivos grandes deberán transmitirse mediante streaming controlado.

---

### 509. Memory safety

No deberán cargarse archivos grandes completamente en memoria.

---

### 510. Download authorization token

Las descargas temporales podrán utilizar URLs firmadas.

---

### 511. Signed download URL

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

### 512. Signed URL scope

La firma deberá incluir:

* recurso;
* expiración;
* tenant;
* usuario cuando aplique;
* disposition;
* allowed range policy.

---

### 513. Signed URL replay

Podrá permitirse reutilización limitada o uso único según sensibilidad.

---

### 514. Download audit events

Se deberán registrar:

* resource ID;
* user;
* tenant;
* bytes enviados;
* completion status;
* disconnect;
* range usage.

---

### 515. Streaming Response Security

Streaming modifica el modelo normal porque la respuesta comienza antes de completarse.

---

### 516. Streaming pipeline

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

### 517. Header freeze before stream

Una vez enviado el primer byte:

* no podrán cambiarse headers;
* no podrán añadirse cookies;
* no podrá cambiarse status;
* no podrá cambiarse content type.

---

### 518. StreamResponse

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

### 519. StreamSecurityPolicy

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

### 520. Bounded streams

Todo stream deberá tener límites o una justificación explícita de operación prolongada.

---

### 521. Stream cancellation

El sistema deberá detectar desconexión del cliente y cancelar trabajo innecesario.

---

### 522. Worker protection

Una conexión lenta no deberá monopolizar indefinidamente un worker.

---

### 523. Backpressure

El adapter de transporte deberá respetar backpressure cuando sea posible.

---

### 524. Slow client attack

Se deberán implementar:

* timeouts;
* límites de buffers;
* límites de duración;
* concurrency limits;
* cancelación.

---

### 525. Output chunk validation

Los chunks deberán ser compatibles con el tipo de contenido.

---

### 526. Text stream encoding

Los streams textuales deberán preservar límites válidos de encoding.

---

### 527. JSON streaming

No deberá emitirse una secuencia JSON inválida.

Podrán usarse formatos explícitos como:

* JSON Lines;
* NDJSON;
* arrays incrementales controlados.

---

### 528. NDJSON response

```text
Content-Type: application/x-ndjson
```

Cada línea deberá codificarse de forma independiente.

---

### 529. Stream exceptions

Una excepción después de iniciar el body no podrá transformarse en una respuesta de error convencional.

---

### 530. Post-start failures

El sistema deberá:

* cerrar el stream;
* registrar el error;
* marcar la respuesta incompleta;
* evitar emitir stack traces;
* notificar métricas.

---

### 531. Stream completion state

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

### 532. Output buffering

El buffering deberá ser explícito y limitado.

---

### 533. Nested output buffers

Los buffers PHP preexistentes deberán inspeccionarse antes del streaming.

---

### 534. Accidental output

Whitespace, warnings o debug output antes de los headers deberán considerarse errores de transporte.

---

### 535. OutputCaptureGuard

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

### 536. Warning leakage

Warnings, notices y deprecations no deberán mezclarse con el response body en producción.

---

### 537. Binary stream integrity

Los bytes no deberán alterarse mediante:

* encoding textual;
* output transformations;
* debug toolbars;
* template middleware.

---

### 538. Chunk audit policy

No deberán registrarse cuerpos sensibles completos.

El audit deberá limitarse a:

* conteo;
* tamaño;
* timing;
* hash opcional;
* estado.

---

### 539. Stream rate limiting

Podrán aplicarse límites por:

* usuario;
* IP;
* tenant;
* ruta;
* tipo de recurso;
* concurrencia.

---

### 540. Resultado de esta entrega

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


## Entrega 7


**Entrega:** 7 de varias
**Cobertura:** Secciones **541–640**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_04.md — Entrega 6`

---

### 541. Server-Sent Events Security

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

### 542. SSE transport profile

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

### 543. SSE response requirements

Una respuesta SSE deberá emitir como mínimo:

```text
Content-Type: text/event-stream
Cache-Control: no-cache
Connection: keep-alive
```

La responsabilidad final de `Connection` dependerá del servidor y del adapter de transporte.

---

### 544. SSE content type

El content type deberá ser exactamente:

```text
text/event-stream
```

con charset compatible cuando aplique.

---

### 545. SSE cache policy

Los streams SSE autenticados no deberán almacenarse en caches compartidas.

---

### 546. SSE response builder

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

### 547. EventStreamResponse

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

### 548. EventStreamSecurityPolicy

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

### 549. Event source trust

Un event source deberá considerarse semiconfiable.

Sus eventos deberán pasar por:

* validación;
* autorización;
* encoding;
* tamaño máximo;
* tenant scope;
* rate limiting.

---

### 550. SSE event model

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

### 551. SSE field validation

Los campos permitidos serán:

* `id`;
* `event`;
* `data`;
* `retry`.

No deberán emitirse campos arbitrarios.

---

### 552. Event name validation

El nombre del evento deberá:

* tener longitud limitada;
* excluir caracteres de control;
* pertenecer a un registry cuando se trate de eventos internos;
* no derivarse directamente de input del usuario.

---

### 553. Event ID validation

El ID deberá ser:

* opaco;
* limitado;
* sin saltos de línea;
* no sensible;
* compatible con reconexión.

---

### 554. Event data encoding

Cada línea del payload deberá emitirse como una línea `data:` independiente.

---

### 555. Newline normalization

El encoder deberá normalizar:

```text
CR
LF
CRLF
```

para impedir que un payload introduzca campos SSE adicionales.

---

### 556. SSE injection

Un valor como:

```text
data: safe
event: privileged
```

no deberá poder inyectarse como estructura de protocolo desde el contenido de usuario.

---

### 557. SseEventEncoder

```php
interface SseEventEncoderInterface
{
    public function encode(
        ServerSentEvent $event
    ): EncodedEventStreamChunk;
}
```

---

### 558. Event payload format

Aunque SSE transmite texto, el contenido podrá usar JSON seguro.

---

### 559. JSON event data

```php
$event = new ServerSentEvent(
    id: $cursor,
    event: 'invoice.updated',
    data: $jsonEncoder->encode($payload),
);
```

---

### 560. Sensitive data filtering

Antes de codificar un evento deberán aplicarse:

* transformers;
* field policies;
* tenant filters;
* authorization filters;
* redaction.

---

### 561. Event-level authorization

No será suficiente autorizar solo al abrir la conexión.

Cada evento podrá requerir validación adicional.

---

### 562. Long-lived authorization

Los permisos pueden cambiar mientras la conexión permanece abierta.

---

### 563. Reauthorization interval

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

### 564. Session expiration during SSE

Cuando expire la sesión, el stream deberá:

* cerrar la conexión;
* emitir un evento controlado no sensible;
* o exigir reconexión autenticada.

---

### 565. User revocation

Una revocación de usuario o sesión deberá poder finalizar streams activos.

---

### 566. Tenant context persistence

El tenant context deberá fijarse al abrir la conexión.

No podrá cambiarse mediante mensajes del cliente.

---

### 567. Cross-tenant event leakage

Todo evento deberá comprobar que pertenece al tenant vinculado al stream.

---

### 568. SSE connection registry

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

### 569. Heartbeats

Los heartbeats permiten:

* detectar desconexiones;
* mantener infraestructura activa;
* evitar timeouts intermediarios.

---

### 570. Heartbeat data

El heartbeat no deberá incluir información sensible.

Podrá emitirse como comentario:

```text
: heartbeat
```

---

### 571. Heartbeat limits

Una frecuencia demasiado alta podrá causar carga innecesaria.

---

### 572. Reconnection security

El navegador puede reconectar automáticamente una conexión SSE.

---

### 573. Last-Event-ID

El cliente podrá enviar:

```text
Last-Event-ID
```

para continuar desde un cursor.

---

### 574. Last-Event-ID trust

Este valor será input no confiable.

---

### 575. Cursor validation

El cursor deberá:

* tener formato estricto;
* estar firmado cuando sea necesario;
* pertenecer al feed correcto;
* respetar tenant;
* no permitir acceso a eventos anteriores no autorizados.

---

### 576. Signed event cursor

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

### 577. Cursor replay

La reutilización podrá permitirse solo dentro de la ventana de retención definida.

---

### 578. Cursor enumeration

Los cursores no deberán ser secuenciales cuando eso permita inferir volumen o acceder a eventos ajenos.

---

### 579. Retry directive

El valor `retry` deberá limitarse a un rango permitido.

---

### 580. Reconnect storm protection

El sistema deberá mitigar reconexiones masivas mediante:

* retry mínimo;
* jitter en cliente;
* rate limits;
* circuit breakers;
* límites por usuario.

---

### 581. SSE connection limits

Se definirán límites por:

* IP;
* sesión;
* usuario;
* tenant;
* ruta;
* nodo.

---

### 582. Worker model

En FrankenPHP u otros runtimes persistentes, las conexiones SSE deberán diseñarse para no bloquear recursos desproporcionadamente.

---

### 583. SSE buffering

Algunos proxies pueden acumular eventos antes de enviarlos.

---

### 584. Proxy buffering control

El adapter podrá emitir headers específicos del proxy cuando estén autorizados.

No deberán emitirse headers dependientes de infraestructura desde Controllers.

---

### 585. SSE compression

La compresión puede incrementar latencia por buffering.

Deberá estar deshabilitada por defecto salvo validación específica.

---

### 586. SSE error handling

Una vez iniciado el stream, los errores deberán convertirse en:

* cierre controlado;
* evento de error genérico;
* métrica;
* audit event.

Nunca en stack trace.

---

### 587. SSE completion states

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

### 588. Range Request Security

Las solicitudes de rango permiten obtener partes de un recurso.

Se utilizan para:

* video;
* audio;
* archivos grandes;
* reanudación de descargas;
* PDF;
* almacenamiento de objetos.

---

### 589. Range header

El cliente podrá enviar:

```text
Range: bytes=0-1023
```

---

### 590. Range trust

`Range` será input no confiable.

---

### 591. RangeRequestParser

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

### 592. Range units

VoltStack soportará inicialmente:

```text
bytes
```

Otros units deberán rechazarse salvo soporte explícito.

---

### 593. Single-range default

Por seguridad y simplicidad podrá permitirse únicamente un rango por request.

---

### 594. Multi-range requests

Las respuestas multipart requieren mayor complejidad.

Podrán deshabilitarse por defecto.

---

### 595. Range limits

Se deberán controlar:

* número de rangos;
* tamaño total;
* rango mínimo;
* rango máximo;
* solapamientos;
* orden.

---

### 596. Overlapping ranges

Los rangos solapados deberán rechazarse o normalizarse.

---

### 597. Range amplification

Un atacante podría solicitar múltiples rangos redundantes para aumentar la respuesta.

---

### 598. Unsatisfiable range

Un rango inválido deberá responder de forma controlada con semántica equivalente a:

```text
416 Range Not Satisfiable
```

---

### 599. Content-Range

El header deberá construirse exclusivamente desde valores validados.

---

### 600. Partial response status

Una respuesta parcial válida utilizará:

```text
206 Partial Content
```

---

### 601. Accept-Ranges

Solo deberá emitirse cuando el recurso y la política permitan rangos.

---

### 602. Restricted resources

Los recursos restricted podrán deshabilitar rangos para simplificar:

* auditoría;
* autorización;
* integridad;
* uso único.

---

### 603. Range authorization continuity

Cada rango deberá mantener la misma autorización del recurso completo.

---

### 604. Signed URL and range binding

Una URL firmada podrá limitar:

* rangos permitidos;
* tamaño máximo;
* número de requests;
* expiración.

---

### 605. Range cache interaction

Las respuestas parciales deberán tener políticas coherentes de cache y validators.

---

### 606. If-Range

El cliente podrá enviar `If-Range` para condicionar el rango a un validator.

---

### 607. If-Range validation

Solo deberá procesarse con:

* ETag válido;
* fecha válida;
* recurso compatible.

---

### 608. Stale range protection

Si el validator no coincide, deberá enviarse la representación completa según política.

---

### 609. Compression Security

La compresión reduce tamaño de respuestas, pero modifica:

* content length;
* cache variants;
* side channels;
* streaming;
* CPU usage.

---

### 610. CompressionPolicy

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

### 611. CompressionMode

```php
enum CompressionMode: string
{
    case Disabled = 'disabled';
    case Automatic = 'automatic';
    case Force = 'force';
}
```

---

### 612. Compression ownership

La compresión deberá pertenecer a:

* servidor HTTP;
* reverse proxy;
* transport adapter;
* response compression middleware central.

No al Controller.

---

### 613. Accept-Encoding trust

El header `Accept-Encoding` será input no confiable y deberá parsearse con límites.

---

### 614. Supported encodings

Podrán soportarse:

* gzip;
* br;
* zstd cuando la infraestructura lo permita;
* identity.

---

### 615. Encoding negotiation

La selección deberá considerar:

* soporte del cliente;
* disponibilidad del servidor;
* tipo de contenido;
* sensibilidad;
* tamaño;
* carga.

---

### 616. Incompressible content

No deberá comprimirse contenido ya comprimido como:

* ZIP;
* JPEG;
* PNG;
* MP4;
* archivos cifrados.

---

### 617. BREACH-style side channels

La compresión de respuestas que mezclan secretos y input reflejado puede filtrar información mediante diferencias de tamaño.

---

### 618. Sensitive compression policy

Las respuestas que contengan:

* CSRF tokens;
* secrets;
* tokens temporales;
* información restringida;

podrán deshabilitar compresión.

---

### 619. Reflection-aware compression

El clasificador podrá detectar respuestas que combinen:

```text
Secret
+
Attacker-controlled reflection
```

y elevar la política a `Disabled`.

---

### 620. Compression oracle mitigation

Las defensas podrán incluir:

* no comprimir;
* separar secretos;
* rotar tokens;
* añadir padding;
* evitar reflexión;
* limitar requests.

---

### 621. Compression CPU exhaustion

La compresión podrá utilizarse para consumir CPU.

---

### 622. Compression limits

Se deberán establecer:

* tamaño máximo comprimible;
* nivel máximo;
* concurrencia;
* tiempo de CPU;
* algoritmos permitidos.

---

### 623. Vary Accept-Encoding

Cuando existan variantes comprimidas deberá emitirse:

```text
Vary: Accept-Encoding
```

---

### 624. Precompressed assets

El asset pipeline podrá generar:

* `.gz`;
* `.br`;
* otras variantes.

Estas deberán vincularse al mismo fingerprint lógico.

---

### 625. Compression integrity

El `Content-Encoding` emitido deberá corresponder exactamente a los bytes transportados.

---

### 626. Content-Length Integrity

`Content-Length` define el tamaño del body transportado cuando aplica.

Una inconsistencia puede provocar:

* truncamiento;
* desincronización;
* smuggling;
* cache poisoning;
* respuestas corruptas.

---

### 627. Content-Length ownership

Solo la capa de transporte podrá definirlo.

---

### 628. Content-Length calculation

Deberá calcularse después de:

* encoding;
* compresión;
* transformación final;
* serialización.

---

### 629. Unknown length

Para streams de longitud desconocida no deberá inventarse un valor.

---

### 630. Duplicate Content-Length

Una respuesta no deberá contener múltiples valores ambiguos.

---

### 631. Conflicting Content-Length

Valores diferentes deberán provocar fallo cerrado.

---

### 632. Transfer-Encoding Integrity

`Transfer-Encoding` será controlado exclusivamente por el servidor o adapter.

---

### 633. Content-Length and Transfer-Encoding conflict

No deberán emitirse ambos cuando la semántica sea ambigua.

---

### 634. Response Splitting Defenses

Response splitting ocurre cuando input no confiable altera la estructura de headers o status line.

---

### 635. CRLF rejection

Se rechazarán:

* `\r`;
* `\n`;
* null bytes;
* controles prohibidos;

en todos los valores estructurales.

---

### 636. Structured header builders

Headers complejos deberán construirse mediante tipos dedicados:

* `Location`;
* `Content-Disposition`;
* `Link`;
* `Set-Cookie`;
* `Content-Range`;
* CSP;
* CORS.

---

### 637. No string concatenation

No se deberá construir un header crítico mediante concatenación directa de strings.

---

### 638. Header serialization boundary

La serialización final deberá ocurrir una sola vez dentro del adapter de transporte.

---

### 639. Response smuggling interaction

Aunque el request smuggling suele originarse en la petición, una respuesta mal delimitada puede desincronizar conexiones persistentes e intermediarios.

---

### 640. Resultado de esta entrega

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


## Entrega 8


**Entrega:** 8 de varias
**Cobertura:** Secciones **641–740**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_04.md — Entrega 7`

---

### 641. HTTP Smuggling Defense Model

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

### 642. Smuggling trust boundary

VoltStack no controla todas las capas de infraestructura, pero deberá:

* rechazar representaciones ambiguas;
* no generar respuestas ambiguas;
* validar metadata de proxy;
* usar adapters compatibles;
* documentar configuraciones seguras;
* auditar inconsistencias.

---

### 643. Request framing validation

Antes de ejecutar Controllers, la capa HTTP deberá validar la coherencia del framing del request.

---

### 644. Content-Length and Transfer-Encoding

Una petición que contenga ambos deberá:

* ser rechazada;
* o ser normalizada únicamente por una capa confiable con semántica inequívoca.

El comportamiento por defecto deberá ser fail closed.

---

### 645. Duplicate Content-Length

Múltiples headers `Content-Length` deberán aceptarse únicamente si la infraestructura los normaliza de manera segura y todos los valores son idénticos.

VoltStack deberá preferir rechazo.

---

### 646. Conflicting body lengths

Valores diferentes deberán provocar el cierre de la petición antes del routing.

---

### 647. Transfer-Encoding canonicalization

El valor deberá parsearse como una lista estructurada.

No mediante comparaciones parciales de strings.

---

### 648. Unsupported transfer codings

Los codings no soportados deberán rechazarse.

---

### 649. Obfuscated transfer encodings

Deberán detectarse variantes ambiguas con:

* espacios inesperados;
* casing;
* delimitadores inválidos;
* valores duplicados;
* caracteres de control.

---

### 650. Chunked request validation

Los cuerpos chunked deberán ser procesados por el servidor HTTP o adapter confiable.

Los Controllers nunca deberán interpretar chunks manualmente.

---

### 651. Chunk extension policy

Las extensiones de chunks deberán ignorarse o rechazarse según las capacidades del servidor.

No deberán exponerse a la aplicación.

---

### 652. Trailer headers

Los trailers deberán estar deshabilitados por defecto para la lógica de aplicación.

---

### 653. Request trailer trust

Un trailer no deberá sobrescribir headers ya validados.

---

### 654. Header whitespace normalization

Whitespace ambiguo deberá normalizarse antes de interpretar headers críticos.

---

### 655. Obsolete line folding

La continuación de headers mediante line folding obsoleto deberá rechazarse.

---

### 656. Null-byte rejection

Los null bytes deberán rechazarse en:

* nombres;
* valores;
* path;
* query;
* host;
* forwarded headers.

---

### 657. Connection reuse after parse error

Ante errores de framing, la conexión deberá cerrarse cuando la infraestructura lo permita.

---

### 658. Response desynchronization

VoltStack deberá garantizar que cada respuesta tenga:

* framing inequívoco;
* body compatible;
* longitud consistente;
* headers congelados;
* cierre controlado del stream.

---

### 659. SmugglingSecurityGuard

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

### 660. Validation outcomes

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

### 661. Infrastructure mismatch

VoltStack deberá poder detectar configuraciones incompatibles entre:

* proxy;
* servidor;
* runtime;
* adapter;
* middleware.

---

### 662. Deployment health check

El sistema podrá ejecutar pruebas de salud específicas para framing HTTP en entornos controlados.

---

### 663. Reverse Proxy Trust Model

Los reverse proxies pueden aportar información necesaria sobre:

* IP original;
* scheme;
* host;
* port;
* protocolo;
* cadena de proxies.

Esta información será no confiable hasta validar el emisor.

---

### 664. Direct peer identity

La primera decisión deberá basarse en la IP o identidad de la conexión directa.

---

### 665. Trusted proxy definition

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

### 666. Proxy capabilities

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

### 667. Trusted Proxy Registry

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

### 668. Registry freeze

El registry deberá congelarse durante el bootstrap de producción.

---

### 669. Network matchers

Podrán soportarse:

* IP exacta;
* CIDR;
* Unix socket identity;
* network interface;
* cloud load balancer ranges administrados.

---

### 670. Broad trust ranges

No deberán configurarse redes demasiado amplias sin una justificación explícita.

---

### 671. Trust all proxies

Una opción equivalente a:

```text
0.0.0.0/0
::/0
```

deberá prohibirse en producción por defecto.

---

### 672. Cloud proxy ranges

Los rangos administrados por proveedores deberán:

* actualizarse de forma controlada;
* validarse;
* versionarse;
* tener fallback seguro.

---

### 673. Proxy identity beyond IP

En infraestructuras avanzadas podrán utilizarse:

* mTLS;
* private network;
* signed headers;
* workload identity;
* proxy protocol.

---

### 674. Forwarded Header

VoltStack deberá soportar el header estándar:

```text
Forwarded
```

mediante un parser estructurado.

---

### 675. Forwarded element

Un elemento podrá incluir:

```text
for=
by=
host=
proto=
```

---

### 676. ForwardedHeaderParser

```php
interface ForwardedHeaderParserInterface
{
    public function parse(
        HeaderValue $value
    ): ForwardedHeaderChain;
}
```

---

### 677. Parsing limits

Se deberán limitar:

* cantidad de elementos;
* longitud total;
* longitud por parámetro;
* cantidad de parámetros;
* nesting o quoting.

---

### 678. Quoted values

Los valores quoted deberán procesarse según una gramática estricta.

---

### 679. Obfuscated identifiers

Los identificadores obfuscados podrán conservarse para auditoría, pero no deberán convertirse en IPs.

---

### 680. IPv6 forwarded values

Los literales IPv6 deberán parsearse correctamente, incluidos brackets y port cuando corresponda.

---

### 681. Unknown forwarded values

El valor `unknown` no deberá utilizarse para tomar decisiones de seguridad.

---

### 682. Forwarded chain direction

VoltStack deberá definir con claridad el orden en que interpreta la cadena.

---

### 683. Trust chain walking

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

### 684. Stop at first untrusted hop

No deberán utilizarse valores anteriores al primer hop no confiable.

---

### 685. X-Forwarded-* support

Por compatibilidad podrán procesarse:

* `X-Forwarded-For`;
* `X-Forwarded-Host`;
* `X-Forwarded-Proto`;
* `X-Forwarded-Port`;
* `X-Forwarded-Prefix`.

---

### 686. Header precedence

No deberán combinarse automáticamente `Forwarded` y `X-Forwarded-*` si producen resultados distintos.

---

### 687. Forwarding strategy

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

### 688. Recommended conflict strategy

En perfiles estrictos se recomienda:

```text
RejectOnConflict
```

---

### 689. X-Forwarded-For parsing

Cada elemento deberá:

* limpiarse;
* parsearse;
* validarse;
* clasificarse como trusted o untrusted.

---

### 690. Client IP Resolution

La IP del cliente no deberá resolverse tomando simplemente el primer valor del header.

---

### 691. ClientAddressResolver

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

### 692. ResolvedClientAddress

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

### 693. ClientAddressConfidence

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

### 694. IP-based security limits

La IP no deberá ser el único factor para:

* autenticación;
* tenant selection;
* high-risk authorization;
* identity resolution.

---

### 695. Rate limiting

Sí podrá utilizarse como una señal dentro de rate limiting y detección de abuso.

---

### 696. IP privacy

Las direcciones deberán almacenarse y registrarse conforme a la política de privacidad de la aplicación.

---

### 697. Scheme Resolution

El scheme efectivo deberá resolverse desde:

1. conexión directa;
2. proxy confiable;
3. configuración de infraestructura.

---

### 698. SchemeResolver

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

### 699. Allowed schemes

Para HTTP web se permitirán:

* `http`;
* `https`.

Cualquier otro valor deberá rechazarse.

---

### 700. Forwarded proto validation

No deberán aceptarse valores como:

```text
https,http
javascript
https:
```

sin parsing y normalización estrictos.

---

### 701. Secure scheme confidence

El sistema deberá conocer si HTTPS fue:

* directo;
* terminado en proxy confiable;
* inferido;
* desconocido.

---

### 702. EffectiveScheme

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

### 703. HSTS dependency

HSTS solo deberá emitirse cuando el scheme efectivo seguro sea confiable.

---

### 704. Secure cookie dependency

Las cookies `Secure` podrán emitirse detrás de TLS termination únicamente si la cadena de proxy ha sido validada.

---

### 705. Port Resolution

El puerto efectivo deberá derivarse de forma coherente con el scheme y host.

---

### 706. Forwarded port validation

El valor deberá:

* ser numérico;
* estar entre 1 y 65535;
* provenir de proxy confiable;
* ser coherente con el host cuando este incluya puerto.

---

### 707. Default ports

Se normalizarán:

```text
http  → 80
https → 443
```

---

### 708. Port mismatch

Una discrepancia entre:

* `Forwarded host`;
* `X-Forwarded-Port`;
* conexión directa;

deberá registrarse o rechazarse según política.

---

### 709. Host Header Security

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

### 710. Host poisoning

Un `Host` no validado puede provocar:

* password reset poisoning;
* redirect poisoning;
* cache poisoning;
* tenant confusion;
* generación de enlaces maliciosos.

---

### 711. ValidatedHost

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

### 712. HostTrustSource

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

### 713. Host syntax validation

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

### 714. IDN normalization

Los hosts internacionales deberán normalizarse a una forma ASCII canónica antes de comparar.

---

### 715. Trailing dot

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

### 716. Mixed-case host

La comparación deberá ser case-insensitive.

---

### 717. Duplicate Host headers

Múltiples headers `Host` deberán provocar rechazo.

---

### 718. HTTP/2 authority

En HTTP/2 y HTTP/3 deberá validarse `:authority` de forma equivalente.

---

### 719. Host and authority conflict

Si ambos están presentes y difieren, la petición deberá rechazarse.

---

### 720. Allowed Host Registry

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

### 721. AllowedHostDefinition

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

### 722. Host purposes

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

### 723. Default host policy

Toda petición con host no reconocido deberá rechazarse antes del routing de aplicación.

---

### 724. Wildcard hosts

Los wildcard deberán utilizar matchers estructurados.

---

### 725. Subdomain tenant matching

Un host como:

```text
tenant.example.com
```

deberá resolver el tenant mediante una regla registrada y no mediante extracción arbitraria.

---

### 726. Registrable domain validation

El sistema deberá evitar tratar como subdominio válido un host fuera del dominio registrable esperado.

---

### 727. Reserved subdomains

Se podrán reservar:

* `www`;
* `api`;
* `admin`;
* `assets`;
* `auth`;
* `support`.

---

### 728. Tenant host collision

No podrán coexistir tenants con hosts canónicamente equivalentes.

---

### 729. Custom tenant domains

Los dominios personalizados deberán pasar por:

* verificación de propiedad;
* validación DNS;
* emisión TLS;
* registro;
* activación atómica.

---

### 730. Canonical Host Enforcement

Una aplicación podrá definir un host canónico para evitar variantes ambiguas.

---

### 731. Canonical host redirect

La redirección deberá:

* validar host origen;
* preservar path seguro;
* preservar query según política;
* forzar HTTPS;
* evitar loops.

---

### 732. Unsafe method canonicalization

Las peticiones mutables no deberán redirigirse automáticamente a otro host sin evaluar preservación del método y del body.

---

### 733. Canonical host for APIs

Las APIs podrán rechazar en lugar de redirigir.

---

### 734. Absolute URL Poisoning Prevention

Toda URL absoluta generada deberá usar:

* host validado;
* scheme confiable;
* port resuelto;
* route segura.

---

### 735. Password reset URLs

Nunca deberán derivarse de un host no validado.

---

### 736. Email link generation

Los enlaces enviados por email deberán preferir un origen configurado o una identidad de tenant validada.

---

### 737. Signed URL host binding

Las firmas deberán incluir el host cuando el enlace sea host-specific.

---

### 738. Proxy Chain Audit

VoltStack deberá producir una representación auditable de la cadena de transporte sin exponerla al cliente.

---

### 739. ProxyChainAuditRecord

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

### 740. Resultado de esta entrega

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


## Entrega 9


**Entrega:** 9 de varias
**Cobertura:** Secciones **741–840**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_04.md — Entrega 8`

---

### 741. Cache-Control Security Model

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

### 742. Cache trust boundaries

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

### 743. Cache ownership

Los Controllers no deberán construir manualmente encabezados `Cache-Control`.

La decisión pertenecerá al motor de seguridad de respuestas.

---

### 744. CachePolicy

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

### 745. CacheVisibility

```php
enum CacheVisibility: string
{
    case Public = 'public';
    case Private = 'private';
    case Restricted = 'restricted';
}
```

---

### 746. CacheStorageMode

```php
enum CacheStorageMode: string
{
    case NoStore = 'no-store';
    case Store = 'store';
    case Conditional = 'conditional';
}
```

---

### 747. Default policy

Toda respuesta autenticada utilizará por defecto:

```text
private
no-store
```

salvo que exista una política explícita diferente.

---

### 748. Public responses

Una respuesta pública deberá cumplir:

* ausencia de datos personalizados;
* ausencia de secretos;
* ausencia de sesión;
* ausencia de tokens;
* independencia del usuario.

---

### 749. Private responses

Una respuesta privada podrá almacenarse únicamente por el navegador del usuario.

---

### 750. Restricted responses

Las respuestas clasificadas como **Restricted** no deberán almacenarse en ningún nivel.

---

### 751. Cache-Control Builder

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

### 752. Header normalization

Los encabezados de cache deberán serializarse siempre en el mismo orden.

---

### 753. Immutable resources

Los recursos versionados podrán declararse como:

```text
immutable
```

cuando el fingerprint garantice unicidad.

---

### 754. Fingerprinted assets

Los assets generados por el pipeline podrán utilizar:

```text
app.f31a9f2.js
```

como identificador inmutable.

---

### 755. Runtime responses

Las respuestas generadas dinámicamente no deberán declararse `immutable`.

---

### 756. Sensitive endpoints

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

### 757. Authentication responses

Nunca deberán almacenarse respuestas que contengan:

* cookies nuevas;
* tokens;
* credenciales;
* cambios de autenticación.

---

### 758. Redirect cache policy

Las redirecciones permanentes deberán revisarse cuidadosamente antes de permitir cache compartido.

---

### 759. Download cache policy

Las descargas protegidas deberán especificar una política explícita.

---

### 760. Streaming cache policy

Los streams:

* SSE;
* NDJSON;
* streams binarios;

normalmente no deberán almacenarse.

---

### 761. CDN Security Model

Los CDN representan un cache compartido.

Por tanto, deberán considerarse no confiables para información privada.

---

### 762. Shared cache eligibility

Una respuesta solo podrá almacenarse en cache compartido cuando sea completamente independiente del usuario.

---

### 763. Personalized responses

Las respuestas personalizadas nunca deberán publicarse mediante:

```text
Cache-Control: public
```

---

### 764. Tenant-aware caching

En aplicaciones multi-tenant, el tenant formará parte del contexto de cache.

---

### 765. Cache partitioning

La clave lógica incluirá:

* tenant;
* locale;
* representación;
* perfil;
* versión.

---

### 766. Authorization-aware cache

Las respuestas cuyo contenido dependa de permisos deberán excluirse del cache compartido.

---

### 767. Capability-aware cache

Si el contenido cambia según capacidades del usuario, dichas capacidades deberán formar parte del contexto de variación o la respuesta deberá marcarse como privada.

---

### 768. Vary Security Model

El encabezado `Vary` controla la selección de representaciones.

---

### 769. VaryBuilder

```php
interface VaryBuilderInterface
{
    public function build(
        ResponseVariationPolicy $policy
    ): HeaderValue;
}
```

---

### 770. Supported Vary headers

Se admitirán únicamente encabezados explícitamente aprobados, por ejemplo:

* Accept
* Accept-Encoding
* Accept-Language
* Origin

---

### 771. Unsafe Vary

No deberá variarse por encabezados arbitrarios proporcionados por el usuario.

---

### 772. Vary normalization

Los nombres incluidos en `Vary` deberán:

* normalizarse;
* eliminar duplicados;
* ordenarse canónicamente.

---

### 773. Excessive variation

Una cantidad excesiva de dimensiones de variación puede degradar significativamente la eficiencia del cache.

---

### 774. Cache key integrity

La clave utilizada para cache deberá construirse mediante componentes estructurados.

---

### 775. CacheKeyContext

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

### 776. Cache poisoning

Una representación no deberá sobrescribir otra representación incompatible.

---

### 777. Variant confusion

Dos respuestas distintas no deberán producir la misma clave de cache.

---

### 778. Cache metadata

Cada entrada deberá almacenar:

* fecha;
* política;
* ETag;
* Last-Modified;
* clasificación;
* tenant;
* versión.

---

### 779. Cache invalidation

Toda invalidación deberá ser explícita y auditable.

---

### 780. Invalidation events

Eventos típicos:

* publicación;
* actualización;
* eliminación;
* cambio de permisos;
* cambio de tenant;
* despliegue.

---

### 781. ETag Security Model

ETag permite validar representaciones sin retransmitir el contenido completo.

---

### 782. ETag ownership

El cálculo de ETag no pertenecerá al Controller.

---

### 783. ETagStrategy

```php
enum ETagStrategy: string
{
    case Strong = 'strong';
    case Weak = 'weak';
}
```

---

### 784. Strong validators

Los ETag fuertes representan exactamente los bytes transmitidos.

---

### 785. Weak validators

Los ETag débiles representan equivalencia semántica.

---

### 786. ETagBuilder

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

### 787. Stable generation

El algoritmo de generación deberá ser determinista.

---

### 788. Secret leakage

El ETag no deberá revelar:

* IDs internos;
* rutas;
* hashes reversibles;
* timestamps sensibles.

---

### 789. EntityTag

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

### 790. Representation scope

Cada representación tendrá su propio ETag.

---

### 791. Compression variants

Una representación comprimida podrá requerir un validator distinto.

---

### 792. Content negotiation

Los distintos formatos:

* HTML;
* JSON;
* XML;

no compartirán el mismo validator.

---

### 793. Tenant isolation

Dos tenants nunca deberán compartir un ETag cuando el contenido sea diferente.

---

### 794. Personalized responses

Las respuestas personalizadas podrán omitir ETag cuando el beneficio sea mínimo o exista riesgo de reutilización indebida.

---

### 795. If-None-Match

El parser deberá soportar correctamente:

```text
If-None-Match
```

---

### 796. EntityTag comparison

La comparación distinguirá correctamente:

* fuerte;
* débil.

---

### 797. Wildcard entity tag

El valor:

```text
*
```

deberá interpretarse conforme a la semántica del estándar.

---

### 798. Conditional request validator

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

### 799. Last-Modified Security

El encabezado deberá representar el instante real de modificación de la representación.

---

### 800. Trusted timestamps

Las fechas deberán derivarse de una fuente confiable y consistente.

---

### 801. LastModifiedBuilder

```php
interface LastModifiedBuilderInterface
{
    public function build(
        ResponseRepresentation $representation
    ): DateTimeImmutable;
}
```

---

### 802. Future timestamps

No deberán emitirse fechas futuras salvo casos excepcionalmente documentados.

---

### 803. Clock consistency

Todos los nodos del cluster deberán mantener sincronización horaria adecuada.

---

### 804. If-Modified-Since

Las fechas recibidas deberán validarse estrictamente.

---

### 805. If-Unmodified-Since

Podrá utilizarse para proteger operaciones concurrentes.

---

### 806. Date parsing

Las fechas inválidas deberán rechazarse silenciosamente según el estándar, sin provocar errores internos.

---

### 807. Conditional evaluation order

La evaluación seguirá un orden determinista entre:

* If-Match;
* If-None-Match;
* If-Modified-Since;
* If-Unmodified-Since;
* If-Range.

---

### 808. Precondition failure

Cuando una precondición falle, la respuesta deberá indicar el estado correspondiente sin revelar información adicional.

---

### 809. Not Modified responses

Una respuesta equivalente a:

```text
304 Not Modified
```

no deberá incluir un cuerpo de contenido.

---

### 810. 304 metadata

Los encabezados emitidos deberán ser coherentes con la representación validada.

---

### 811. Validator consistency

No deberán emitirse simultáneamente validadores incompatibles.

---

### 812. Weak validator usage

Los validadores débiles no deberán emplearse para operaciones que requieran igualdad byte a byte.

---

### 813. Cache validator registry

```php
interface CacheValidatorRegistryInterface
{
    public function register(
        RepresentationValidator $validator
    ): void;
}
```

---

### 814. Validator lifecycle

Los validadores deberán invalidarse cuando cambie la representación.

---

### 815. Deployment awareness

Un despliegue podrá invalidar representaciones si cambia su semántica.

---

### 816. Multi-node consistency

Todos los nodos deberán calcular el mismo validator para la misma representación.

---

### 817. Conditional GET

Las peticiones GET condicionales deberán evitar trabajo innecesario cuando la representación permanezca válida.

---

### 818. Conditional HEAD

HEAD seguirá las mismas reglas de validación que GET.

---

### 819. Cache audit

El framework registrará:

* hits;
* misses;
* revalidaciones;
* invalidaciones;
* respuestas 304;
* conflictos.

---

### 820. Cache metrics

Métricas recomendadas:

* ratio de aciertos;
* tamaño;
* variantes;
* TTL medio;
* invalidaciones;
* colisiones.

---

### 821. Stale content policy

Las respuestas obsoletas deberán controlarse mediante políticas explícitas.

---

### 822. Stale revalidation

Cuando la política lo permita, el cache podrá revalidar antes de servir una representación.

---

### 823. Stale-if-error

Podrá configurarse para mejorar disponibilidad, siempre que la sensibilidad de la respuesta lo permita.

---

### 824. Stale-while-revalidate

Los recursos públicos podrán aprovechar esta estrategia bajo límites definidos.

---

### 825. Sensitive stale data

Nunca deberán servirse respuestas obsoletas que contengan datos sensibles o personalizados.

---

### 826. Cache poisoning defense

Toda representación deberá comprobar:

* contexto;
* tenant;
* usuario cuando aplique;
* política;
* variante.

---

### 827. Header confusion

Los encabezados utilizados para construir la clave no deberán aceptar variantes ambiguas.

---

### 828. Response classification

La clasificación de seguridad formará parte del modelo de cache.

---

### 829. Runtime cache isolation

Los caches internos del runtime deberán respetar el mismo aislamiento que los caches HTTP.

---

### 830. Cache security events

Eventos relevantes:

* CachePoisoningDetected;
* InvalidValidator;
* CachePolicyViolation;
* VariantMismatch;
* SharedCacheRejected.

---

### 831. Testing strategy

Las pruebas deberán cubrir:

* Vary;
* ETag;
* Last-Modified;
* 304;
* cache compartido;
* multi-tenant;
* compresión.

---

### 832. Security audit

Las decisiones de cache deberán ser completamente auditables.

---

### 833. Deployment verification

Durante el despliegue podrán ejecutarse verificaciones de coherencia de validadores y políticas.

---

### 834. Backward compatibility

Los cambios en estrategias de cache deberán poder versionarse para evitar comportamientos inconsistentes.

---

### 835. Performance considerations

La seguridad del cache no deberá depender de optimizaciones específicas del servidor HTTP.

---

### 836. Documentation requirements

Toda política personalizada de cache deberá documentarse indicando:

* finalidad;
* riesgos;
* alcance;
* responsables.

---

### 837. ADR-111

**Separación entre clasificación de seguridad y política de cache.**

---

### 838. ADR-112

**Los Controllers nunca construirán manualmente encabezados Cache-Control, ETag o Last-Modified.**

---

### 839. ADR-113

**Toda representación cacheable deberá poseer una política de variación determinista.**

---

### 840. Resultado de esta entrega

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


## Entrega 10


**Entrega:** 10 de 10
**Cobertura:** Secciones **841–950**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_04.md — Entrega 9`
**Estado:** Cierre de `CONTROLLER_SECURITY_MODEL_PART_04`

---

### 841. Response Integrity Security Model

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

### 842. Integrity dimensions

VoltStack distinguirá entre:

* integridad lógica;
* integridad de representación;
* integridad de transporte;
* autenticidad;
* frescura;
* vinculación contextual.

---

### 843. Logical integrity

La integridad lógica asegura que el contenido corresponde al resultado autorizado del Controller.

---

### 844. Representation integrity

La integridad de representación asegura que los bytes serializados coinciden con:

* content type;
* encoding;
* locale;
* variante negociada;
* perfil de seguridad.

---

### 845. Transport integrity

La integridad de transporte depende principalmente de:

* TLS;
* HTTP framing;
* proxy confiable;
* ausencia de response splitting;
* delimitación correcta del body.

---

### 846. Authenticity

La autenticidad permite verificar que la respuesta fue producida por una entidad autorizada.

---

### 847. Freshness

La frescura evita aceptar respuestas:

* expiradas;
* reproducidas;
* pertenecientes a otro request;
* generadas para otro usuario o tenant.

---

### 848. ResponseIntegrityPolicy

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

### 849. IntegrityMode

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

### 850. Default integrity mode

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

### 851. High-integrity scenarios

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

### 852. ResponseIntegrityEngine

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

### 853. Integrity processing order

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

### 854. Representation versus transfer integrity

VoltStack deberá diferenciar:

* digest de contenido sin compresión;
* digest de bytes transferidos;
* firma de campos semánticos;
* firma de headers y body final.

---

### 855. Digest Security Model

Un digest permite detectar modificaciones accidentales o maliciosas del contenido.

No demuestra por sí solo quién produjo la respuesta.

---

### 856. DigestAlgorithm

```php
enum DigestAlgorithm: string
{
    case Sha256 = 'sha-256';
    case Sha384 = 'sha-384';
    case Sha512 = 'sha-512';
}
```

---

### 857. Deprecated digest algorithms

No deberán utilizarse para integridad de seguridad:

* MD5;
* SHA-1;
* algoritmos propietarios débiles.

---

### 858. DigestPolicy

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

### 859. DigestScope

```php
enum DigestScope: string
{
    case Representation = 'representation';
    case TransferredContent = 'transferred_content';
    case DownloadArtifact = 'download_artifact';
}
```

---

### 860. Digest builder

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

### 861. Streaming digest

Para streams, el digest deberá calcularse incrementalmente.

---

### 862. Digest and unknown streams

Cuando no sea posible conocer el digest antes de emitir headers, podrán utilizarse:

* trailers confiables;
* digest externo del artefacto;
* firma del manifest;
* verificación posterior.

Los trailers permanecerán deshabilitados salvo soporte seguro de toda la cadena.

---

### 863. Download digest

Las descargas sensibles podrán publicar un digest separado mediante metadata segura.

---

### 864. Digest mismatch

Una discrepancia deberá producir:

* rechazo del artefacto;
* evento de seguridad;
* invalidación de cache;
* posible aislamiento del nodo;
* investigación de infraestructura.

---

### 865. Digest confidentiality

Un digest no deberá utilizarse como sustituto de autorización.

---

### 866. Low-entropy content risk

Los hashes de contenido predecible pueden permitir inferencias.

No deberán exponerse innecesariamente para recursos privados.

---

### 867. HTTP Message Signatures

VoltStack podrá soportar firmas estructuradas de mensajes HTTP mediante un módulo especializado.

---

### 868. SignaturePolicy

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

### 869. SignatureAlgorithm

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

### 870. Algorithm selection

Se preferirán firmas asimétricas cuando múltiples consumidores deban verificar sin conocer la clave privada.

---

### 871. HMAC usage

HMAC podrá utilizarse en relaciones cerradas entre sistemas confiables.

No deberá compartirse una clave global entre múltiples tenants o integraciones independientes.

---

### 872. Signed components

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

### 873. SignatureComponentSet

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

### 874. Mandatory signed components

Toda respuesta firmada deberá incluir como mínimo:

* identificador de clave;
* fecha de creación;
* expiración;
* content digest cuando exista body;
* contexto suficiente para impedir sustitución.

---

### 875. Request-response binding

Una respuesta firmada podrá vincularse al request mediante:

* request ID;
* challenge;
* nonce;
* method;
* route;
* client identifier.

---

### 876. Cross-request substitution

Sin binding, una respuesta válida podría reutilizarse en otro contexto.

---

### 877. Tenant binding

En sistemas multi-tenant, la identidad del tenant deberá formar parte del material firmado cuando sea relevante.

---

### 878. User binding

Las respuestas personalizadas de alto valor podrán firmarse para un usuario o sesión concreta.

---

### 879. SignatureInputBuilder

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

### 880. Canonicalization

La canonicalización deberá ser:

* determinista;
* versionada;
* independiente de orden accidental;
* resistente a whitespace ambiguo;
* compatible entre lenguajes.

---

### 881. Signature header ownership

Solo el motor de integridad podrá emitir headers de firma.

---

### 882. Signature key identifiers

El identificador de clave no deberá exponer:

* rutas internas;
* nombres de archivos;
* secretos;
* detalles de HSM;
* nombres de usuarios.

---

### 883. Key lifecycle

Las claves deberán tener:

* fecha de activación;
* fecha de expiración;
* estado;
* propósito;
* algoritmo;
* propietario;
* versión.

---

### 884. Key rotation

La rotación deberá permitir una ventana de verificación de respuestas previamente emitidas.

---

### 885. Key revocation

Una clave comprometida deberá poder revocarse inmediatamente.

---

### 886. Verification key publication

Las claves públicas podrán distribuirse mediante:

* endpoint controlado;
* JWKS;
* manifest firmado;
* configuración out-of-band.

---

### 887. Key isolation

Las claves de respuesta deberán separarse de:

* claves de sesión;
* claves CSRF;
* claves de cifrado de cookies;
* claves de firma de URLs;
* claves de autenticación.

---

### 888. Hardware-backed keys

Perfiles de alta seguridad podrán utilizar:

* HSM;
* KMS;
* secure enclave;
* servicio de firma remoto.

---

### 889. Signing failures

Si una respuesta requiere firma y el servicio de firma falla, la respuesta no deberá emitirse sin protección.

---

### 890. Signature downgrade

No deberá degradarse silenciosamente de `Signed` a `DigestOnly`.

---

### 891. Signature verification telemetry

Los consumidores integrados podrán reportar:

* firma válida;
* expiración;
* clave desconocida;
* digest incorrecto;
* contexto incorrecto;
* replay.

---

### 892. Replay Protection

Una firma válida no siempre impide replay.

---

### 893. FreshnessPolicy

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

### 894. Created and expires

Las respuestas firmadas deberán incluir una ventana temporal explícita.

---

### 895. Clock skew

La tolerancia de reloj deberá ser limitada y configurable.

---

### 896. Nonce registry

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

### 897. Single-use responses

Podrán utilizarse para:

* confirmaciones financieras;
* enlaces de descarga;
* entrega de secretos;
* autorizaciones temporales.

---

### 898. Replay storage

El registro de nonces deberá:

* ser distribuido cuando aplique;
* expirar;
* ser atómico;
* resistir carreras;
* aislar tenants.

---

### 899. Context Binding Policy

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

### 900. Response Provenance

Toda respuesta deberá poder asociarse internamente a su origen de ejecución.

---

### 901. ResponseProvenance

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

### 902. Provenance exposure

No toda metadata de procedencia deberá enviarse al cliente.

---

### 903. Public provenance

Podrán exponerse únicamente identificadores no sensibles y necesarios para soporte.

---

### 904. Internal provenance

El detalle completo permanecerá en:

* tracing;
* audit logs;
* security events;
* incident records.

---

### 905. Release binding

Las respuestas firmadas de alto valor podrán vincularse al release que las produjo.

---

### 906. Node anomaly detection

Si respuestas equivalentes difieren entre nodos, deberá investigarse:

* configuración divergente;
* despliegue parcial;
* cache inconsistente;
* compromiso;
* errores de serialización.

---

### 907. Transport Audit System

VoltStack deberá mantener un sistema de auditoría específico para seguridad de transporte y respuesta.

---

### 908. TransportAuditRecord

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

### 909. Audit objectives

El sistema deberá permitir responder:

* qué política se aplicó;
* por qué se aplicó;
* qué componente la decidió;
* si existieron overrides;
* si hubo conflictos;
* qué headers fueron emitidos;
* si la respuesta se completó.

---

### 910. Audit data minimization

Los registros no deberán incluir automáticamente:

* bodies;
* tokens;
* cookies completas;
* headers de autorización;
* datos personales.

---

### 911. Header audit representation

Los headers sensibles deberán representarse mediante:

* presencia;
* clasificación;
* hash seguro cuando sea necesario;
* valor redactado.

---

### 912. Audit immutability

Los registros de seguridad deberán protegerse contra modificación no autorizada.

---

### 913. Audit retention

La retención dependerá de:

* clasificación;
* regulación;
* capacidad de almacenamiento;
* necesidades forenses;
* privacidad.

---

### 914. Audit correlation

Los registros deberán correlacionarse con:

* request tracing;
* authentication;
* authorization;
* tenant;
* deployment;
* proxy chain;
* incident ID.

---

### 915. Security Telemetry

La telemetría deberá detectar desviaciones antes de convertirse en incidentes.

---

### 916. Transport metrics

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

### 917. Security metric labels

Las labels deberán evitar cardinalidad excesiva.

---

### 918. Safe metric dimensions

Podrán utilizarse:

* route group;
* response profile;
* status family;
* tenant tier;
* violation type;
* deployment region.

---

### 919. Unsafe metric dimensions

Deberán evitarse:

* URL completa;
* user ID sin anonimizar;
* token;
* query string arbitraria;
* filename libre;
* stack trace.

---

### 920. SecurityEventBus

```php
interface SecurityEventBusInterface
{
    public function publish(
        SecurityEvent $event
    ): void;
}
```

---

### 921. Transport security events

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

### 922. Security event severity

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

### 923. Severity resolution

La severidad deberá considerar:

* sensibilidad;
* repetición;
* endpoint;
* autenticación;
* tenant;
* impacto;
* evidencia de explotación.

---

### 924. Alert thresholds

No todo evento deberá producir una alerta inmediata.

El sistema deberá soportar:

* agregación;
* ventanas temporales;
* rate thresholds;
* anomaly detection;
* suppression controlada.

---

### 925. Threat Intelligence Hooks

VoltStack podrá exponer hooks para enriquecer eventos con inteligencia externa.

---

### 926. ThreatIntelligenceProvider

```php
interface ThreatIntelligenceProviderInterface
{
    public function assess(
        ThreatObservation $observation
    ): ThreatAssessment;
}
```

---

### 927. Threat observations

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

### 928. Threat intelligence trust

La inteligencia externa será una señal, no una verdad absoluta.

---

### 929. Automated response

Las respuestas automáticas podrán incluir:

* rate limit;
* challenge;
* bloqueo temporal;
* aislamiento de sesión;
* cierre de stream;
* revocación de token.

No deberán realizar acciones destructivas irreversibles sin política explícita.

---

### 930. Incident Reporting

Los incidentes de transporte deberán generar un expediente estructurado.

---

### 931. TransportSecurityIncident

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

### 932. IncidentStatus

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

### 933. Incident containment

Las acciones de contención podrán incluir:

* deshabilitar rutas;
* revocar claves;
* desactivar cache;
* cerrar sesiones;
* bloquear origins;
* retirar un nodo;
* forzar un perfil estricto.

---

### 934. Emergency security profile

VoltStack deberá soportar activar un perfil de emergencia sin modificar cada Controller.

---

### 935. EmergencyTransportProfile

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

### 936. Runtime Observability

El sistema deberá proporcionar observabilidad sin debilitar la seguridad.

---

### 937. Response trace span

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

### 938. Sensitive trace attributes

Los valores sensibles deberán redactarse antes de enviarse a sistemas de tracing.

---

### 939. Debug mode restrictions

El modo debug nunca deberá:

* omitir headers de seguridad;
* exponer cookies;
* mostrar claves;
* desactivar validación de host;
* permitir framing ambiguo.

---

### 940. Production Hardening Profile

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

### 941. Standard production profile

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

### 942. Strict production profile

Añadirá:

* conflicto de forwarded headers como error;
* external redirects por capability;
* compression restringida;
* cross-origin policies estrictas;
* auditoría extendida;
* fail closed en inconsistencias;
* validación de integridad reforzada.

---

### 943. Regulated profile

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

### 944. Compliance Mapping

VoltStack podrá incluir mapas de cumplimiento hacia controles externos.

Estos mapas serán ayudas de ingeniería, no certificaciones automáticas.

---

### 945. OWASP ASVS mapping

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

### 946. NIST mapping

Podrán documentarse relaciones con funciones como:

* Identify;
* Protect;
* Detect;
* Respond;
* Recover.

---

### 947. PCI-oriented profile

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

### 948. Production Hardening Checklist

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

### 949. Security ADRs

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

### 950. Conclusión de CONTROLLER_SECURITY_MODEL_PART_04

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
