# Controller Security Model - Part 05: Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 1 de varias
**Cobertura:** Secciones **1–100**
**Estado:** En desarrollo
**Continuación conceptual de:** `CONTROLLER_SECURITY_MODEL_PART_04.md`

---

## Entrega 1

### 1. Introducción

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

### 2. Principio fundamental

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

### 3. Separación de responsabilidades

VoltStack distinguirá claramente:

* identificación;
* autenticación;
* autorización;
* gestión de sesión;
* elevación de privilegios;
* federación de identidad;
* auditoría.

---

### 4. Identification

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

### 5. Authentication

La autenticación responde:

> ¿Qué evidencia existe de que el actor controla esa identidad?

---

### 6. Authorization

La autorización responde:

> ¿Qué acciones puede realizar la identidad autenticada?

Autenticación y autorización deberán permanecer desacopladas.

---

### 7. Identity Security Pipeline

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

### 8. Security goals

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

### 9. Threat model

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

### 10. Protected assets

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

### 11. Trust boundaries

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

### 12. Identity types

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

### 13. IdentityIdentifier

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

### 14. Stable subject identifiers

El identificador interno deberá ser:

* estable;
* opaco;
* no reciclable;
* independiente del email;
* independiente del username;
* único dentro del provider.

---

### 15. Mutable identity attributes

No deberán utilizarse como clave primaria de identidad:

* email;
* teléfono;
* username;
* display name;
* nombre legal.

---

### 16. Identity provider

```php
interface IdentityProviderInterface
{
    public function resolve(
        IdentityLookup $lookup
    ): ?IdentityRecord;
}
```

---

### 17. IdentityRecord

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

### 18. IdentityStatus

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

### 19. Status validation

Solo identidades `Active` podrán autenticarse normalmente.

---

### 20. Pending identities

Las cuentas pendientes podrán acceder únicamente a flujos limitados como:

* verificación de email;
* activación;
* aceptación de invitación;
* configuración inicial.

---

### 21. Suspended identities

Una cuenta suspendida deberá:

* rechazar nuevas autenticaciones;
* invalidar sesiones según política;
* impedir refresh;
* generar evento de seguridad.

---

### 22. Locked identities

El bloqueo podrá ser:

* temporal;
* administrativo;
* por riesgo;
* por intentos fallidos;
* por incidente.

---

### 23. Disabled identities

Una identidad deshabilitada deberá considerarse no autenticable.

---

### 24. Deleted identities

Los identificadores eliminados no deberán reutilizarse automáticamente.

---

### 25. Identity context

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

### 26. Actor versus subject

VoltStack distinguirá:

* actor: quien ejecuta la acción;
* subject: identidad sobre la que actúa.

Esto será esencial para:

* impersonación;
* administración;
* automatización;
* delegación.

---

### 27. ActorContext

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

### 28. Authentication methods

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

### 29. AuthenticationMethod

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

### 30. Authentication factor categories

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

### 31. Factor strength

No todos los métodos poseen el mismo nivel de confianza.

---

### 32. Authentication strength

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

### 33. Authentication Assurance Level

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

### 34. Assurance calculation

El assurance level deberá derivarse de:

* método;
* cantidad de factores;
* independencia de factores;
* dispositivo;
* autenticador;
* riesgo;
* antigüedad de autenticación.

---

### 35. AuthenticationState

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

### 36. Anonymous identity

Las peticiones sin autenticación deberán usar una identidad anónima explícita.

---

### 37. Anonymous context

```php
IdentityIdentifier(
    type: IdentityType::Anonymous,
    provider: 'voltstack',
    subject: 'anonymous',
);
```

---

### 38. No nullable identity context

El sistema deberá evitar representar el anonimato mediante `null` disperso en toda la aplicación.

---

### 39. Authentication policy

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

### 40. AuthenticationAttempt

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

### 41. CredentialEnvelope

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

### 42. SensitiveValue

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

### 43. Credential redaction

Las credenciales nunca deberán aparecer en:

* logs;
* excepciones;
* traces;
* dumps;
* profiler;
* telemetry;
* serialization.

---

### 44. Credential lifetime

Las credenciales en memoria deberán mantenerse únicamente durante la verificación.

---

### 45. Credential zeroization

Cuando el runtime lo permita, los buffers sensibles deberán limpiarse después del uso.

---

### 46. Credential extraction

La extracción deberá depender del método:

* body;
* header;
* cookie;
* TLS metadata;
* authorization header;
* WebAuthn payload.

---

### 47. CredentialExtractor

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

### 48. Multiple credentials

Una petición con múltiples esquemas incompatibles deberá rechazarse o resolverse mediante política explícita.

---

### 49. Credential precedence

No deberá existir precedencia implícita entre:

* session cookie;
* bearer token;
* API key;
* client certificate.

---

### 50. Authentication scheme selection

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

### 51. AuthenticationScheme

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

### 52. Route authentication metadata

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

### 53. Controller requirements

Los Controllers podrán declarar:

* autenticación requerida;
* assurance mínimo;
* métodos aceptados;
* frescura;
* step-up;
* dispositivo confiable.

---

### 54. Authentication freshness

Algunas operaciones deberán exigir autenticación reciente.

---

### 55. FreshAuthenticationRequirement

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

### 56. Sensitive operations

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

### 57. Step-up authentication

Una sesión válida podrá necesitar elevar temporalmente su assurance.

---

### 58. StepUpAuthenticationService

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

### 59. Step-up scope

La elevación podrá estar vinculada a:

* sesión;
* operación;
* recurso;
* tenant;
* ventana temporal.

---

### 60. Global elevation risk

Una elevación no deberá aumentar indefinidamente todos los privilegios de la sesión.

---

### 61. Authentication challenge

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

### 62. Challenge properties

Todo challenge deberá ser:

* impredecible;
* temporal;
* vinculado al flujo;
* limitado a una identidad;
* de un solo uso cuando aplique.

---

### 63. Challenge registry

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

### 64. Challenge replay

Los challenges consumidos deberán rechazarse.

---

### 65. Challenge expiration

Los challenges expirados deberán eliminarse o invalidarse.

---

### 66. Authentication result

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

### 67. AuthenticationResultStatus

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

### 68. Generic authentication failures

La respuesta externa no deberá revelar si falló:

* username;
* password;
* tenant;
* MFA;
* estado de cuenta.

---

### 69. Internal failure classification

Internamente sí deberán diferenciarse las causas para:

* auditoría;
* seguridad;
* soporte;
* métricas;
* respuesta automática.

---

### 70. AuthenticationFailure

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

### 71. Account enumeration prevention

El sistema deberá evitar diferencias observables en:

* mensaje;
* status;
* tiempo;
* redirects;
* respuesta;
* rate limit.

---

### 72. Timing normalization

Los intentos con identidad inexistente deberán ejecutar una operación de verificación simulada cuando sea razonable.

---

### 73. Dummy password hash

El framework podrá mantener un hash dummy para reducir diferencias de timing.

---

### 74. Authentication rate limiting

Los límites deberán considerar:

* IP;
* identidad reclamada;
* dispositivo;
* tenant;
* ASN;
* patrón global.

---

### 75. AuthRateLimitKey

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

### 76. IP-only rate limiting risk

Limitar únicamente por IP puede afectar redes compartidas y ser evadido mediante botnets.

---

### 77. Identity-only rate limiting risk

Limitar únicamente por identidad puede permitir bloquear cuentas mediante ataques externos.

---

### 78. Composite throttling

VoltStack deberá combinar múltiples señales.

---

### 79. Password spraying detection

Se deberá detectar el patrón:

```text
Una contraseña
    ↓
Muchas identidades
```

---

### 80. Credential stuffing detection

Se deberá detectar:

```text
Muchas credenciales conocidas
    ↓
Muchas identidades
```

---

### 81. Brute-force detection

Se deberá detectar:

```text
Muchas contraseñas
    ↓
Una identidad
```

---

### 82. AuthenticationRiskEngine

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

### 83. Risk signals

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

### 84. Risk score

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

### 85. AuthenticationRiskLevel

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

### 86. AuthenticationRiskAction

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

### 87. Risk engine limitations

El motor de riesgo deberá ser una señal complementaria.

No deberá reemplazar verificaciones criptográficas.

---

### 88. Explainable risk decisions

Las decisiones deberán conservar razones internas auditables.

---

### 89. User-facing risk messages

Los mensajes externos deberán evitar revelar reglas de detección.

---

### 90. Device identity

VoltStack podrá asociar sesiones a dispositivos conocidos.

---

### 91. DeviceIdentifier

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

### 92. DeviceTrustLevel

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

### 93. Device fingerprinting

El fingerprinting pasivo deberá utilizarse con precaución debido a:

* privacidad;
* falsos positivos;
* volatilidad;
* evasión;
* regulación.

---

### 94. Device cookie

Una cookie de dispositivo deberá:

* estar firmada;
* ser opaca;
* no contener datos sensibles;
* poder revocarse;
* tener scope limitado.

---

### 95. Trusted device

Un dispositivo confiable no deberá eliminar completamente la necesidad de autenticación.

---

### 96. Managed devices

Los dispositivos administrados podrán aportar señales como:

* certificado;
* posture;
* enrollment;
* workload identity;
* attestation.

---

### 97. Compromised device

Una señal de compromiso podrá provocar:

* revocación de sesiones;
* MFA obligatorio;
* bloqueo temporal;
* alerta;
* denial.

---

### 98. Authentication events

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

### 99. Principios arquitectónicos de esta entrega

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

### 100. Resultado de esta entrega

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

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 2

- Password security architecture
- Password policy engine
- Password hashing
- Argon2id profiles
- Pepper management
- Password verification
- Rehashing
- Breached password detection
- Password history
- Password change workflow
- Password reset security
- Email verification
- Magic links
- One-time passwords
- Passwordless authentication foundations
```

## Entrega 2

**Documento:** Parte 05
**Entrega:** 2 de varias
**Cobertura:** Secciones **101–200**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 1`

---

### 101. Password Security Architecture

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

### 102. Password subsystem boundaries

El subsistema de contraseñas deberá permanecer separado de:

* Controllers;
* formularios;
* modelos ORM;
* logs;
* serializadores;
* sesiones;
* autorización.

---

### 103. PasswordCredential

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

### 104. Password value restrictions

Una contraseña no deberá:

* convertirse automáticamente a string;
* serializarse;
* exportarse;
* incluirse en excepciones;
* persistirse en texto plano;
* almacenarse en request attributes duraderos.

---

### 105. Password lifecycle

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

### 106. PasswordPolicyEngine

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

### 107. PasswordPolicyContext

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

### 108. PasswordOperation

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

### 109. Policy composition

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

### 110. Length-first policy

VoltStack deberá priorizar longitud y resistencia real sobre reglas arbitrarias de composición.

---

### 111. Minimum password length

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

### 112. Maximum password length

Deberá existir un máximo razonable para evitar:

* agotamiento de memoria;
* ataques de CPU;
* cuerpos excesivos;
* abuso de parsers.

El límite no deberá ser tan bajo que impida passphrases.

---

### 113. Unicode passwords

VoltStack deberá definir una política explícita para Unicode.

---

### 114. Unicode normalization

La normalización puede cambiar la semántica de una contraseña.

Por defecto, el framework no deberá aplicar transformaciones invisibles sin una decisión documentada.

---

### 115. Whitespace preservation

No deberán eliminarse automáticamente:

* espacios iniciales;
* espacios finales;
* múltiples espacios internos.

---

### 116. Password composition rules

No deberán exigirse obligatoriamente combinaciones como:

* una mayúscula;
* una minúscula;
* un número;
* un símbolo;

salvo requerimiento regulatorio explícito.

---

### 117. Password strength estimation

El framework podrá integrar un estimador de resistencia basado en:

* longitud;
* patrones;
* secuencias;
* palabras comunes;
* datos personales;
* repeticiones.

---

### 118. PasswordStrengthEstimator

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

### 119. PasswordStrengthAssessment

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

### 120. PasswordStrengthLevel

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

### 121. Identity similarity checks

Las contraseñas podrán compararse contra:

* username;
* email local part;
* display name;
* tenant name;
* nombre de la aplicación.

---

### 122. Sensitive comparison data

Los atributos de identidad utilizados para estas comparaciones deberán permanecer protegidos y no registrarse junto a la contraseña.

---

### 123. Password hashing architecture

El hash deberá calcularse exclusivamente mediante un servicio dedicado.

---

### 124. PasswordHasher

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

### 125. PasswordHash

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

### 126. Preferred algorithm

VoltStack deberá preferir `Argon2id` cuando esté disponible de forma segura.

---

### 127. PasswordHashingProfile

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

### 128. PasswordHashAlgorithm

```php
enum PasswordHashAlgorithm: string
{
    case Argon2id = 'argon2id';
    case Bcrypt = 'bcrypt';
    case Pbkdf2Sha256 = 'pbkdf2-sha256';
}
```

---

### 129. Algorithm fallback

Los algoritmos alternativos deberán existir solo para:

* compatibilidad;
* migraciones;
* entornos limitados;
* cumplimiento específico.

---

### 130. Argon2id profile selection

Los parámetros deberán calibrarse según:

* hardware;
* concurrencia;
* memoria disponible;
* latencia objetivo;
* entorno;
* riesgo.

---

### 131. Runtime calibration

VoltStack podrá incluir un comando de calibración.

```text
volt security:password-calibrate
```

---

### 132. Calibration goal

La calibración deberá buscar un costo suficientemente alto sin provocar:

* denegación de servicio;
* latencia excesiva;
* saturación de workers;
* incompatibilidad con el runtime.

---

### 133. Profile versioning

Los perfiles deberán versionarse para permitir evolución gradual.

---

### 134. Per-environment profiles

Podrán existir perfiles distintos para:

* desarrollo;
* pruebas;
* producción;
* producción estricta.

Los perfiles de desarrollo nunca deberán migrarse accidentalmente a producción.

---

### 135. Salt management

Cada hash deberá utilizar un salt único generado por el algoritmo.

---

### 136. Manual salts

Los Controllers y la aplicación no deberán proporcionar salts manualmente.

---

### 137. Pepper architecture

VoltStack podrá soportar un pepper adicional almacenado fuera de la base de datos.

---

### 138. PasswordPepperProvider

```php
interface PasswordPepperProviderInterface
{
    public function active(): PasswordPepper;

    public function byVersion(int $version): ?PasswordPepper;
}
```

---

### 139. PasswordPepper

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

### 140. Pepper storage

El pepper deberá almacenarse en:

* secret manager;
* KMS;
* HSM;
* variable segura de runtime;
* mecanismo equivalente.

No en la misma base de datos que los hashes.

---

### 141. Pepper rotation

La rotación deberá permitir verificar hashes creados con versiones anteriores.

---

### 142. Pepper compromise

Ante compromiso deberá evaluarse:

* rotación;
* invalidación de sesiones;
* rehash progresivo;
* reset forzado;
* análisis de exposición.

---

### 143. Pepper usage modes

El pepper podrá aplicarse:

* antes del hashing;
* como derivación separada;
* mediante HMAC sobre el resultado.

La estrategia deberá ser única y versionada.

---

### 144. Password verification

La verificación deberá usar funciones resistentes a timing attacks.

---

### 145. PasswordVerificationResult

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

### 146. Verification flow

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

### 147. Rehash on authentication

Después de una autenticación válida, el sistema podrá actualizar el hash si:

* cambió el algoritmo;
* aumentó el costo;
* cambió el pepper;
* cambió la versión del perfil.

---

### 148. Atomic rehash

El rehash deberá actualizarse atómicamente y evitar sobrescribir cambios concurrentes.

---

### 149. Rehash failure

Un fallo al rehash no deberá convertir una autenticación válida en una autenticación inválida, salvo política estricta.

Deberá registrarse para reintento.

---

### 150. Legacy hash migration

VoltStack podrá reconocer hashes heredados mediante adapters limitados.

---

### 151. LegacyHashVerifier

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

### 152. Legacy migration policy

Todo hash heredado validado deberá migrarse al perfil actual tan pronto como sea posible.

---

### 153. Plaintext migration prohibition

Nunca deberá almacenarse temporalmente una contraseña en texto plano para migración futura.

---

### 154. Breached Password Detection

VoltStack deberá poder rechazar contraseñas conocidas por filtraciones.

---

### 155. BreachedPasswordChecker

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

### 156. BreachCheckPolicy

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

### 157. BreachCheckMode

```php
enum BreachCheckMode: string
{
    case LocalDataset = 'local_dataset';
    case PrivacyPreservingRemote = 'privacy_preserving_remote';
    case Hybrid = 'hybrid';
}
```

---

### 158. Password disclosure prohibition

La contraseña completa nunca deberá enviarse a un proveedor externo.

---

### 159. Privacy-preserving lookup

Las consultas remotas deberán usar un mecanismo que reduzca la exposición, como prefijos de hash o un protocolo equivalente.

---

### 160. Breach provider failure

La política deberá decidir entre:

* permitir;
* rechazar;
* degradar a dataset local;
* solicitar cambio posterior.

---

### 161. Breached password result

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

### 162. Existing breached passwords

Cuando una contraseña existente aparezca en una nueva filtración, VoltStack podrá:

* marcar la cuenta;
* requerir step-up;
* pedir cambio;
* revocar sesiones de riesgo;
* notificar al usuario.

---

### 163. Password history

El sistema podrá impedir reutilización reciente.

---

### 164. PasswordHistoryEntry

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

### 165. History comparison

La nueva contraseña deberá verificarse contra hashes históricos usando sus perfiles originales.

---

### 166. History length

La cantidad de hashes retenidos deberá ser configurable y proporcional al riesgo.

---

### 167. History retention risk

Retener demasiados hashes incrementa el material disponible ante una filtración.

---

### 168. Periodic password expiration

VoltStack no deberá forzar cambios periódicos sin evidencia de compromiso, salvo requisito regulatorio.

---

### 169. Password compromise event

Un cambio deberá exigirse cuando exista:

* evidencia de filtración;
* compromiso del proveedor;
* exposición administrativa;
* ataque confirmado;
* incidente de sesión.

---

### 170. Password change workflow

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

### 171. PasswordChangeService

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

### 172. PasswordChangeCommand

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

### 173. Current password verification

La contraseña actual podrá requerirse, salvo que exista una autenticación fuerte y reciente equivalente.

---

### 174. Session handling after change

Después del cambio deberá:

* rotarse la sesión actual;
* revocarse refresh tokens según política;
* revocarse otras sesiones cuando corresponda;
* invalidarse remember-me.

---

### 175. Password change notification

Se deberá notificar por un canal independiente cuando sea posible.

---

### 176. Notification content

La notificación no deberá incluir:

* contraseña;
* reset token;
* hash;
* datos sensibles innecesarios.

---

### 177. Administrative password setting

Los administradores no deberán conocer ni definir contraseñas permanentes de usuarios.

---

### 178. Temporary administrative credentials

Cuando sea indispensable, deberán ser:

* temporales;
* de un solo uso;
* forzar cambio;
* auditadas;
* entregadas por canal seguro.

---

### 179. Password Reset Security

La recuperación de contraseña es un flujo de autenticación y deberá tratarse como tal.

---

### 180. Reset flow

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

### 181. PasswordResetService

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

### 182. Generic reset response

La respuesta al solicitar un reset deberá ser equivalente exista o no la cuenta.

---

### 183. ResetToken

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

### 184. Reset token storage

El secreto completo no deberá almacenarse.

Se persistirá una representación derivada verificable.

---

### 185. Reset token properties

El token deberá ser:

* aleatorio;
* de alta entropía;
* de un solo uso;
* temporal;
* vinculado a identidad;
* vinculado a propósito;
* revocable.

---

### 186. Reset token binding

Podrá vincularse adicionalmente a:

* tenant;
* canal;
* request;
* dispositivo;
* riesgo;
* versión de credenciales.

---

### 187. Credential version binding

Si la contraseña cambia después de emitir el token, los tokens anteriores deberán invalidarse.

---

### 188. Reset token URL

La URL deberá generarse usando:

* host validado;
* HTTPS;
* ruta registrada;
* parámetros codificados;
* origen canónico.

---

### 189. Referrer leakage protection

La página de reset deberá aplicar una política de referrer restrictiva.

---

### 190. Third-party content restriction

Las páginas de recuperación no deberán cargar recursos de terceros innecesarios que puedan observar el token.

---

### 191. Reset completion

Antes de aceptar la nueva contraseña deberán validarse:

* token;
* expiración;
* consumo;
* identidad;
* estado;
* política;
* riesgo.

---

### 192. Reset token consumption

El consumo deberá ser atómico para evitar uso concurrente.

---

### 193. Post-reset actions

Después del reset deberán considerarse:

* revocar todas las sesiones;
* revocar refresh tokens;
* eliminar trusted devices;
* invalidar recovery links;
* notificar al usuario;
* registrar evento de seguridad.

---

### 194. Email Verification

La verificación de email no equivale necesariamente a autenticación completa.

---

### 195. EmailVerificationToken

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

### 196. Email version binding

Si cambia el email antes de verificarse, el token anterior deberá quedar inválido.

---

### 197. Magic Links

Un magic link actúa como credencial temporal.

---

### 198. MagicLinkPolicy

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

### 199. Magic link security

Los magic links deberán:

* expirar rápidamente;
* ser de un solo uso;
* evitar exposición en logs;
* usar HTTPS;
* vincularse a propósito;
* limitar el assurance resultante;
* requerir confirmación cuando el riesgo lo justifique.

---

### 200. Resultado de esta entrega

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

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 3

- One-time password architecture
- Email OTP
- SMS OTP
- TOTP
- HOTP
- Recovery codes
- MFA enrollment
- MFA verification
- MFA fatigue protection
- Factor independence
- Step-up authentication internals
- WebAuthn foundations
- Passkey registration
- Passkey authentication
- Phishing-resistant authentication
```

## Entrega 3

**Documento:** Parte 05
**Entrega:** 3 de varias
**Cobertura:** Secciones **201–300**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 2`

---

### 201. One-Time Password Architecture

Los códigos de un solo uso son credenciales temporales diseñadas para autenticar una operación o identidad durante una ventana limitada.

VoltStack distinguirá entre:

* códigos enviados por canal;
* códigos generados por autenticador;
* códigos basados en tiempo;
* códigos basados en contador;
* códigos de recuperación;
* códigos de aprobación transaccional.

---

### 202. OTP security goals

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

### 203. OTP threat model

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

### 204. OtpType

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

### 205. OtpPurpose

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

### 206. OtpChallenge

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

### 207. OTP challenge binding

Todo código deberá vincularse al menos a:

* challenge;
* identidad;
* propósito;
* expiración;
* tenant cuando aplique.

---

### 208. Contextual binding

Los perfiles de alto riesgo podrán añadir vinculación a:

* sesión;
* dispositivo;
* operación;
* monto;
* destinatario;
* recurso;
* request ID.

---

### 209. OTP generation

Los códigos deberán generarse mediante un CSPRNG cuando no deriven de un algoritmo estandarizado como TOTP u HOTP.

---

### 210. Numeric OTP length

Los códigos numéricos deberán poseer suficiente longitud según:

* vida útil;
* límites de intentos;
* riesgo;
* canal;
* propósito.

---

### 211. Short code risk

Un código corto solo será aceptable cuando se combine con:

* expiración reducida;
* pocos intentos;
* rate limiting;
* challenge específico;
* detección de abuso.

---

### 212. Alphanumeric OTP

Los códigos alfanuméricos podrán utilizarse para aumentar entropía sin incrementar demasiado la longitud.

---

### 213. Human-readable alphabet

El alfabeto podrá excluir caracteres ambiguos como:

* `0` y `O`;
* `1`, `I` y `l`;
* caracteres visualmente similares.

---

### 214. OtpGenerator

```php
interface OtpGeneratorInterface
{
    public function generate(
        OtpGenerationPolicy $policy
    ): GeneratedOtp;
}
```

---

### 215. OtpGenerationPolicy

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

### 216. GeneratedOtp

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

### 217. OTP storage

Los códigos enviados por canal no deberán almacenarse en texto plano.

---

### 218. OTP verifier representation

Se almacenará una representación derivada mediante:

* hash resistente;
* HMAC con clave segregada;
* derivación específica de OTP.

---

### 219. OtpSecretHasher

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

### 220. OTP comparison

La comparación deberá ser resistente a timing attacks.

---

### 221. Atomic OTP consumption

El consumo deberá ser atómico.

Dos requests simultáneos no podrán validar exitosamente el mismo código de un solo uso.

---

### 222. OtpChallengeStore

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

### 223. OtpConsumptionResult

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

### 224. Attempt counting

Cada intento inválido deberá incrementar un contador atómico asociado al challenge.

---

### 225. Maximum attempts

Al alcanzar el máximo permitido, el challenge deberá invalidarse.

---

### 226. OTP rate limiting

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

### 227. Resend behavior

Solicitar un nuevo código podrá:

* invalidar el anterior;
* reutilizar el mismo challenge con nueva versión;
* mantener una ventana controlada.

La política deberá ser explícita.

---

### 228. Recommended resend policy

Por defecto, emitir un nuevo código deberá invalidar los anteriores para el mismo propósito.

---

### 229. OTP response privacy

La respuesta externa no deberá revelar si:

* el email existe;
* el teléfono existe;
* el canal está registrado;
* el código fue enviado realmente.

---

### 230. Email OTP

Los OTP por correo dependen de la seguridad de la cuenta de email del usuario.

---

### 231. Email OTP assurance

Un OTP por email deberá considerarse un factor de posesión de assurance limitado.

No deberá clasificarse automáticamente como phishing-resistant.

---

### 232. Email delivery security

La entrega deberá usar:

* proveedor autenticado;
* TLS cuando esté disponible;
* plantillas controladas;
* links y códigos sin datos sensibles adicionales;
* anti-abuse.

---

### 233. Email OTP content

El mensaje deberá indicar:

* propósito;
* expiración;
* contexto básico;
* advertencia de no compartir;
* canal para reportar actividad no reconocida.

---

### 234. OTP code in email subject

El código no deberá colocarse en el subject por defecto, debido a exposición en:

* notificaciones;
* previews;
* logs del cliente;
* dispositivos bloqueados.

---

### 235. Email OTP and magic links

Email OTP y magic link deberán tratarse como mecanismos distintos, aunque compartan canal.

---

### 236. SMS OTP

Los OTP por SMS deberán considerarse vulnerables a:

* SIM swapping;
* interceptación;
* redirección;
* malware;
* recuperación insegura del operador.

---

### 237. SMS assurance ceiling

El assurance resultante deberá limitarse y no considerarse resistente al phishing.

---

### 238. SMS fallback policy

SMS no deberá ser el único mecanismo de recuperación para cuentas de alto valor.

---

### 239. Phone number change

Cambiar el número registrado deberá requerir:

* autenticación reciente;
* factor adicional;
* notificación al canal anterior;
* ventana de observación cuando el riesgo lo justifique.

---

### 240. SMS content minimization

El mensaje no deberá incluir:

* contraseña;
* nombre completo innecesario;
* información financiera;
* datos del tenant;
* enlaces inseguros.

---

### 241. OTP delivery provider abstraction

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

### 242. Delivery result confidentiality

Un error del proveedor no deberá exponer detalles internos al usuario.

---

### 243. Delivery telemetry

Se deberán registrar:

* intento;
* provider;
* resultado;
* latencia;
* throttling;
* error clasificado.

Nunca el código completo.

---

### 244. TOTP Architecture

TOTP genera códigos derivados de:

* secreto compartido;
* tiempo;
* algoritmo;
* intervalo.

---

### 245. TotpCredential

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

### 246. TotpProfile

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

### 247. TotpAlgorithm

```php
enum TotpAlgorithm: string
{
    case Sha1 = 'sha1';
    case Sha256 = 'sha256';
    case Sha512 = 'sha512';
}
```

---

### 248. TOTP interoperability

El soporte de algoritmos deberá considerar compatibilidad con autenticadores existentes.

---

### 249. TOTP secret generation

El secreto deberá generarse mediante un CSPRNG y poseer entropía suficiente.

---

### 250. TOTP secret storage

El secreto deberá:

* cifrarse en reposo;
* protegerse con clave segregada;
* no aparecer en logs;
* no serializarse;
* estar aislado por tenant.

---

### 251. TOTP secret access

Solo el verificador MFA deberá poder descifrarlo.

---

### 252. TOTP enrollment

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

### 253. Provisional enrollment

El secreto no deberá activarse hasta que el usuario demuestre que puede generar un código válido.

---

### 254. Provisioning URI

La URI de aprovisionamiento deberá construirse mediante un builder estructurado.

---

### 255. TotpProvisioningUriBuilder

```php
interface TotpProvisioningUriBuilderInterface
{
    public function build(
        TotpEnrollment $enrollment
    ): SensitiveProvisioningUri;
}
```

---

### 256. QR code security

El QR contiene el secreto TOTP y deberá tratarse como material sensible.

---

### 257. QR exposure restrictions

La pantalla de enrolamiento deberá:

* usar `no-store`;
* impedir caching compartido;
* aplicar CSP estricta;
* evitar recursos de terceros;
* limitar referrer;
* expirar.

---

### 258. Re-display prohibition

Después de completar el enrolamiento, el secreto no deberá volver a mostrarse normalmente.

---

### 259. TOTP verification

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

### 260. TOTP clock window

La ventana permitida deberá ser pequeña.

Ventanas amplias aumentan la posibilidad de replay y adivinación.

---

### 261. TOTP replay protection

Un código aceptado no deberá volver a aceptarse dentro de la misma ventana cuando el perfil exija protección fuerte.

---

### 262. TOTP usage registry

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

### 263. Distributed TOTP replay protection

En despliegues multinodo, el registro de consumo deberá ser distribuido y atómico.

---

### 264. Clock synchronization

Los nodos deberán mantener sincronización horaria confiable.

---

### 265. Clock drift handling

La corrección de drift no deberá ampliar permanentemente la ventana de aceptación.

---

### 266. HOTP Architecture

HOTP deriva códigos de:

* secreto;
* contador;
* algoritmo.

---

### 267. HotpCredential

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

### 268. HOTP counter updates

El contador deberá actualizarse atómicamente después de una verificación válida.

---

### 269. HOTP look-ahead window

La ventana de búsqueda futura deberá mantenerse limitada.

---

### 270. HOTP resynchronization

La resincronización deberá requerir un flujo específico y auditado.

---

### 271. Recovery Codes

Los códigos de recuperación son credenciales de emergencia.

---

### 272. RecoveryCodeSet

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

### 273. Recovery code generation

Los códigos deberán:

* ser aleatorios;
* tener alta entropía;
* ser independientes;
* ser de un solo uso;
* poseer formato humano razonable.

---

### 274. Recovery code storage

Los códigos se almacenarán únicamente como representaciones derivadas verificables.

---

### 275. Recovery code display

Solo deberán mostrarse una vez, inmediatamente después de generarse.

---

### 276. Recovery code delivery

El usuario deberá poder:

* descargarlos;
* imprimirlos;
* copiarlos;
* confirmar que los guardó.

La descarga deberá usar un response profile sensible.

---

### 277. Recovery code regeneration

Generar un nuevo set deberá invalidar todo el set anterior.

---

### 278. Recovery code use

El uso deberá:

* consumir el código;
* generar evento;
* notificar al usuario;
* elevar el riesgo de la sesión;
* recomendar regeneración.

---

### 279. Recovery code assurance

Un recovery code podrá autenticar, pero deberá marcar la sesión como recuperada mediante un factor de emergencia.

---

### 280. Post-recovery restrictions

Después de usar un recovery code, ciertas operaciones podrán requerir:

* factor adicional;
* espera;
* autenticación reciente;
* revisión manual;
* reconfiguración MFA.

---

### 281. MFA Enrollment Architecture

MFA enrollment es una operación de seguridad de alto impacto.

---

### 282. MfaEnrollmentService

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

### 283. Enrollment prerequisites

El enrolamiento deberá requerir:

* sesión autenticada;
* autenticación reciente;
* nivel de assurance suficiente;
* riesgo aceptable;
* identidad activa.

---

### 284. Factor replacement

Reemplazar un factor existente deberá ser más estricto que añadir uno adicional.

---

### 285. Existing factor confirmation

Cuando sea posible, el usuario deberá confirmar un factor ya registrado antes de eliminarlo o sustituirlo.

---

### 286. MFA method registry

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

### 287. MfaMethodDefinition

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

### 288. Factor independence

Dos mecanismos no deberán contarse automáticamente como dos factores si dependen del mismo canal o dispositivo comprometible.

---

### 289. Non-independent examples

Podrán no considerarse independientes:

* contraseña y PIN almacenados en el mismo gestor comprometido;
* email OTP y magic link enviados al mismo buzón;
* SMS OTP y recuperación vía el mismo número;
* dos aplicaciones dentro del mismo dispositivo comprometido.

---

### 290. MFA policy engine

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

### 291. MFA requirements

La política podrá exigir:

* número de factores;
* categorías independientes;
* strength mínimo;
* factor phishing-resistant;
* hardware binding;
* freshness;
* dispositivo administrado.

---

### 292. MfaRequirement

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

### 293. MFA verification flow

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

### 294. MFA fatigue protection

Los sistemas de aprobación push deberán protegerse contra solicitudes repetidas.

---

### 295. Push approval limitations

Una aprobación simple de “Aceptar” podrá ser vulnerable a fatiga y consentimiento accidental.

---

### 296. Number matching

Cuando exista push MFA, deberá preferirse:

* number matching;
* contexto visible;
* ubicación aproximada;
* dispositivo solicitante;
* operación solicitada.

---

### 297. Challenge frequency limits

Se deberán limitar:

* cantidad de prompts;
* reintentos;
* prompts por sesión;
* prompts por identidad;
* prompts por dispositivo.

---

### 298. Suspicious MFA denial

Rechazos repetidos o reportes de “no fui yo” deberán:

* elevar riesgo;
* cerrar el flujo;
* revocar desafíos;
* alertar;
* considerar bloqueo temporal.

---

### 299. WebAuthn and Passkey Foundations

WebAuthn y passkeys permiten autenticación basada en criptografía de clave pública.

El servidor almacena una clave pública, mientras el autenticador conserva la clave privada.

---

### 300. Resultado de esta entrega

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

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 4

- WebAuthn architecture
- Relying Party model
- Origin and RP ID validation
- Registration ceremonies
- Authentication ceremonies
- Challenge generation and binding
- Authenticator data validation
- Client data validation
- Attestation policy
- Assertion verification
- Signature counters
- Discoverable credentials
- Passkey synchronization
- User verification
- Resident keys
- Credential lifecycle
- Credential revocation
- Phishing-resistant authentication policies
```

## Entrega 4

**Documento:** Parte 05
**Entrega:** 4 de varias
**Cobertura:** Secciones **301–400**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 3`

---

### 301. WebAuthn Security Architecture

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

### 302. WebAuthn trust model

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

### 303. WebAuthn security goals

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

### 304. WebAuthn threat model

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

### 305. WebAuthnRelyingParty

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

### 306. Relying Party ID

El RP ID deberá ser un dominio registrable válido o un subdominio permitido.

No deberá derivarse directamente de un `Host` no validado.

---

### 307. RP ID resolution

```php
interface RelyingPartyResolverInterface
{
    public function resolve(
        AuthenticationSecurityContext $context
    ): WebAuthnRelyingParty;
}
```

---

### 308. Multi-tenant RP model

VoltStack deberá soportar al menos dos estrategias:

* RP compartido para todos los tenants;
* RP separado por dominio de tenant.

La estrategia deberá configurarse explícitamente.

---

### 309. Shared RP risk

Cuando múltiples tenants compartan RP ID, la credencial deberá vincularse además al tenant dentro del modelo interno.

---

### 310. Per-tenant RP risk

Los dominios personalizados deberán validarse antes de poder utilizarse como RP ID.

---

### 311. Allowed origins

Toda ceremonia deberá validar el origen exacto contra un registry confiable.

---

### 312. Origin registry

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

### 313. Origin normalization

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

### 314. HTTPS requirement

Las ceremonias WebAuthn deberán ejecutarse sobre HTTPS, salvo excepciones estrictamente limitadas para desarrollo local.

---

### 315. Local development exception

La excepción de localhost no deberá trasladarse a producción.

---

### 316. WebAuthnPolicyProfile

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

### 317. UserVerificationRequirement

```php
enum UserVerificationRequirement: string
{
    case Required = 'required';
    case Preferred = 'preferred';
    case Discouraged = 'discouraged';
}
```

---

### 318. ResidentKeyRequirement

```php
enum ResidentKeyRequirement: string
{
    case Required = 'required';
    case Preferred = 'preferred';
    case Discouraged = 'discouraged';
}
```

---

### 319. AuthenticatorAttachmentPreference

```php
enum AuthenticatorAttachmentPreference: string
{
    case Platform = 'platform';
    case CrossPlatform = 'cross_platform';
    case Any = 'any';
}
```

---

### 320. AttestationConveyancePreference

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

### 321. Ceremony separation

VoltStack deberá diferenciar claramente:

* registration ceremony;
* authentication ceremony.

---

### 322. Registration ceremony

La ceremonia de registro crea una nueva credencial pública vinculada a una identidad.

---

### 323. Authentication ceremony

La ceremonia de autenticación demuestra control sobre una credencial previamente registrada.

---

### 324. WebAuthn challenge

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

### 325. WebAuthnCeremonyType

```php
enum WebAuthnCeremonyType: string
{
    case Registration = 'registration';
    case Authentication = 'authentication';
}
```

---

### 326. Challenge generation

El challenge deberá:

* usar CSPRNG;
* tener alta entropía;
* ser único;
* expirar rápidamente;
* vincularse a ceremonia;
* vincularse a RP;
* vincularse a identidad cuando aplique.

---

### 327. Challenge storage

El challenge completo podrá almacenarse cifrado o como estado seguro de corta duración.

---

### 328. Challenge consumption

Un challenge válido deberá consumirse atómicamente después de completar la ceremonia.

---

### 329. Challenge reuse

Un challenge no podrá reutilizarse en:

* otra ceremonia;
* otro RP;
* otra identidad;
* otro tenant;
* otro propósito.

---

### 330. WebAuthnCeremonyStore

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

### 331. Ceremony state

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

### 332. Registration options builder

```php
interface PublicKeyCreationOptionsBuilderInterface
{
    public function build(
        WebAuthnRegistrationContext $context
    ): PublicKeyCredentialCreationOptions;
}
```

---

### 333. Registration prerequisites

Registrar una credencial deberá requerir:

* sesión autenticada;
* autenticación reciente;
* identidad activa;
* riesgo aceptable;
* autorización para administrar factores.

---

### 334. Registration step-up

Para añadir una passkey a una cuenta existente deberá exigirse step-up cuando el riesgo lo justifique.

---

### 335. User entity

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

### 336. User entity ID

El `id` deberá ser:

* opaco;
* estable;
* no derivado del email;
* no reciclable;
* limitado en tamaño;
* independiente del display name.

---

### 337. User handle privacy

El user handle no deberá revelar información personal innecesaria.

---

### 338. User name mutability

El username mostrado al autenticador podrá cambiar sin alterar el identificador interno.

---

### 339. Credential parameters

VoltStack deberá publicar únicamente algoritmos criptográficos permitidos.

---

### 340. PublicKeyCredentialParametersRegistry

```php
interface PublicKeyCredentialParametersRegistryInterface
{
    public function allowedAlgorithms(
        WebAuthnPolicyProfile $profile
    ): array;
}
```

---

### 341. Supported algorithms

El framework deberá priorizar algoritmos modernos y ampliamente soportados.

---

### 342. Algorithm downgrade

No deberán aceptarse algoritmos no anunciados durante la ceremonia.

---

### 343. Exclude credentials

Durante el registro podrán enviarse credenciales existentes para evitar duplicados.

---

### 344. Credential exclusion privacy

La lista de exclusión deberá limitarse a la identidad autenticada y no revelar credenciales de otros usuarios.

---

### 345. Authenticator selection

La política podrá solicitar:

* autenticador de plataforma;
* llave física;
* resident key;
* user verification;
* passkey sincronizable.

---

### 346. Timeout

El timeout del cliente no sustituye la expiración del challenge en el servidor.

---

### 347. Registration response parser

```php
interface WebAuthnRegistrationResponseParserInterface
{
    public function parse(
        array $payload
    ): ParsedRegistrationResponse;
}
```

---

### 348. ClientDataJSON validation

Deberán validarse:

* estructura JSON;
* tipo;
* challenge;
* origin;
* crossOrigin;
* token binding cuando exista.

---

### 349. Client data type

Para registro deberá esperarse:

```text
webauthn.create
```

---

### 350. Challenge comparison

La comparación deberá hacerse sobre los bytes decodificados de forma segura.

---

### 351. Origin validation

El origin recibido deberá coincidir exactamente con uno permitido.

---

### 352. Cross-origin registration

El registro cross-origin deberá rechazarse salvo protocolo y política explícitamente soportados.

---

### 353. AuthenticatorData

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

### 354. RP ID hash validation

El hash del RP ID deberá coincidir con el RP esperado.

---

### 355. User presence

La bandera de presencia deberá validarse cuando la política lo requiera.

---

### 356. User verification

Cuando la política exija verificación, `userVerified` deberá ser verdadero.

---

### 357. AttestedCredentialData

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

### 358. Credential ID uniqueness

El credential ID deberá ser único dentro del registry de credenciales aplicable.

---

### 359. Credential collision

Una colisión deberá considerarse un evento de alta severidad.

---

### 360. Public key validation

La clave pública deberá:

* usar algoritmo permitido;
* poseer parámetros válidos;
* cumplir longitud;
* no contener puntos inválidos;
* no usar formatos ambiguos.

---

### 361. Attestation Object

El objeto de attestation deberá analizarse mediante parsers estrictos y limitados.

---

### 362. Attestation verifier

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

### 363. Attestation policy

Por defecto, las aplicaciones generales deberán preferir `none` salvo necesidad real.

---

### 364. Direct attestation

La attestation directa podrá aumentar:

* control empresarial;
* trazabilidad de autenticador;
* privacidad;
* complejidad;
* dependencia de metadata.

---

### 365. Enterprise attestation

Solo deberá habilitarse en entornos administrados y con consentimiento o base legal apropiada.

---

### 366. AAGUID handling

El AAGUID podrá utilizarse para:

* metadata;
* policy;
* detección de autenticadores;
* posture empresarial.

No deberá asumirse como prueba suficiente de seguridad por sí solo.

---

### 367. Metadata service

```php
interface AuthenticatorMetadataProviderInterface
{
    public function lookup(
        string $aaguid
    ): ?AuthenticatorMetadata;
}
```

---

### 368. Metadata trust

La metadata externa deberá:

* verificarse;
* actualizarse;
* versionarse;
* manejar expiración;
* tratarse como señal adicional.

---

### 369. Attestation trust anchors

Los trust anchors deberán gestionarse mediante un registry controlado.

---

### 370. Attestation revocation

El sistema deberá poder rechazar autenticadores revocados o comprometidos.

---

### 371. Registration verification result

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

### 372. WebAuthnCredential

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

### 373. WebAuthnCredentialStatus

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

### 374. Credential nickname

El usuario podrá asignar un nombre descriptivo como:

* teléfono personal;
* laptop de trabajo;
* llave física principal.

El nickname no formará parte de la identidad criptográfica.

---

### 375. Credential registration commit

La credencial solo deberá persistirse después de completar todas las validaciones.

---

### 376. Registration transaction

El commit deberá incluir atómicamente:

* credential;
* identity binding;
* tenant binding;
* audit record;
* ceremony consumption;
* factor enrollment.

---

### 377. Authentication options builder

```php
interface PublicKeyRequestOptionsBuilderInterface
{
    public function build(
        WebAuthnAuthenticationContext $context
    ): PublicKeyCredentialRequestOptions;
}
```

---

### 378. AllowCredentials

Para autenticación identificada podrá enviarse una lista de credenciales permitidas.

---

### 379. Discoverable authentication

Para passkeys descubribles podrá omitirse `allowCredentials`.

---

### 380. Username-less authentication

La autenticación sin username deberá resolver la identidad mediante:

* credential ID;
* user handle;
* RP ID;
* tenant context.

---

### 381. Authentication response parser

```php
interface WebAuthnAuthenticationResponseParserInterface
{
    public function parse(
        array $payload
    ): ParsedAuthenticationResponse;
}
```

---

### 382. Authentication client data type

Durante autenticación deberá esperarse:

```text
webauthn.get
```

---

### 383. Assertion verification

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

### 384. Assertion verifier

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

### 385. Credential lookup

El credential ID deberá resolverse en un registry aislado por RP y tenant.

---

### 386. Unknown credential

Una credencial desconocida deberá producir una respuesta externa genérica.

---

### 387. User handle validation

Cuando se reciba user handle deberá coincidir con la identidad vinculada a la credencial.

---

### 388. Signature verification

La firma deberá verificarse sobre:

```text
authenticatorData
+
SHA-256(clientDataJSON)
```

usando la clave pública registrada.

---

### 389. Invalid signature

Una firma inválida deberá:

* rechazar la autenticación;
* incrementar señales de riesgo;
* generar evento;
* no revelar detalles criptográficos.

---

### 390. Signature counter

El contador podrá ayudar a detectar clonación, pero no todos los autenticadores lo incrementan.

---

### 391. Counter evaluation

VoltStack deberá distinguir:

* contador incrementado;
* contador sin soporte;
* contador sin cambio;
* contador reducido;
* contador inconsistente.

---

### 392. Counter rollback

Un rollback podrá indicar:

* clonación;
* restauración;
* sincronización;
* comportamiento del proveedor;
* error de implementación.

---

### 393. Counter policy

El rollback no deberá producir siempre bloqueo inmediato.

La decisión dependerá de:

* tipo de autenticador;
* backup state;
* metadata;
* historial;
* riesgo;
* política del tenant.

---

### 394. Passkey synchronization

Las passkeys sincronizadas pueden existir en múltiples dispositivos bajo el ecosistema del proveedor.

---

### 395. Backup eligibility

La bandera de elegibilidad indicará si la credencial puede respaldarse o sincronizarse.

---

### 396. Backup state

La bandera de estado podrá indicar que la credencial fue respaldada.

---

### 397. Synced passkey assurance

Una passkey sincronizada podrá ser resistente al phishing, aunque no necesariamente hardware-bound a un único dispositivo.

---

### 398. Credential revocation

El usuario y los administradores autorizados deberán poder:

* suspender;
* revocar;
* marcar como perdida;
* marcar como comprometida;
* eliminar una credencial.

---

### 399. Phishing-resistant authentication policy

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

### 400. Resultado de esta entrega

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

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 5

- Passkey lifecycle management
- Credential inventory
- Credential naming and user management
- Factor deletion and replacement
- Recovery after passkey loss
- Passwordless account bootstrap
- Passwordless-only accounts
- Authentication method downgrade prevention
- Session architecture
- Session identifiers
- Session creation
- Session fixation protection
- Session rotation
- Session storage
- Session expiration
- Idle and absolute timeouts
- Concurrent session controls
- Session revocation foundations
```

## Entrega 5

**Documento:** Parte 05
**Entrega:** 5 de varias
**Cobertura:** Secciones **401–500**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 4`

---

### 401. Passkey Lifecycle Management

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

### 402. Credential lifecycle states

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

### 403. Pending credentials

Una credencial permanecerá en estado `Pending` durante la ceremonia de registro.

No podrá utilizarse para autenticación hasta que:

* se valide la attestation cuando corresponda;
* se verifique la primera assertion requerida;
* se consuma el challenge;
* se complete la transacción de enrolamiento.

---

### 404. Active credentials

Solo una credencial `Active` podrá participar normalmente en autenticación.

---

### 405. Suspended credentials

La suspensión permitirá deshabilitar temporalmente una credencial sin eliminar su historial.

---

### 406. Lost credentials

Una credencial marcada como perdida deberá:

* dejar de ser elegible;
* generar un evento de seguridad;
* incrementar el riesgo de sesiones relacionadas;
* activar recomendaciones de recuperación.

---

### 407. Compromised credentials

Una passkey comprometida deberá considerarse no confiable incluso si su firma sigue siendo criptográficamente válida.

---

### 408. Revoked credentials

Una credencial revocada no deberá reactivarse mediante una operación ordinaria.

---

### 409. Retired credentials

El estado `Retired` podrá utilizarse cuando una credencial sea reemplazada de forma controlada.

---

### 410. Passkey inventory

VoltStack deberá exponer un inventario seguro de credenciales por identidad.

---

### 411. PasskeyInventoryItem

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

### 412. Inventory privacy

El inventario no deberá exponer:

* clave pública completa;
* AAGUID innecesario;
* datos de attestation detallados;
* identificadores internos del proveedor;
* metadata sensible del dispositivo.

---

### 413. Credential naming

Los usuarios podrán asignar nombres descriptivos a sus passkeys.

---

### 414. Default credential names

Cuando no exista un nombre explícito, el framework podrá generar uno aproximado a partir de:

* tipo de autenticador;
* plataforma;
* fecha;
* contexto del enrolamiento.

---

### 415. Name sanitization

Los nombres de credenciales deberán:

* validarse;
* limitar longitud;
* evitar HTML;
* evitar caracteres de control;
* almacenarse como texto plano seguro.

---

### 416. Duplicate names

Se podrán permitir nombres duplicados, pero el UI deberá mostrar información adicional para distinguir credenciales.

---

### 417. Last-used tracking

El sistema podrá registrar la última utilización para ayudar al usuario a identificar credenciales activas.

---

### 418. Usage tracking privacy

El tracking no deberá convertirse en un mecanismo invasivo de seguimiento de dispositivos.

---

### 419. Credential management authorization

Administrar passkeys deberá requerir:

* sesión autenticada;
* autenticación reciente;
* assurance suficiente;
* autorización explícita;
* riesgo aceptable.

---

### 420. CredentialManagementService

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

### 421. Deletion semantics

Eliminar una credencial desde el UI deberá traducirse internamente a una revocación auditable.

---

### 422. Hard deletion

La eliminación física podrá realizarse después del periodo de retención definido.

---

### 423. Self-lockout protection

Antes de revocar una credencial, el sistema deberá comprobar si quedará al menos un método de recuperación viable.

---

### 424. Last-factor removal

Eliminar el último factor fuerte deberá requerir:

* factor alternativo;
* recuperación verificada;
* intervención administrativa controlada;
* o confirmación reforzada según política.

---

### 425. Factor replacement

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

### 426. Replace-before-remove

VoltStack deberá preferir registrar primero la nueva credencial antes de retirar la anterior.

---

### 427. Credential replacement transaction

La operación deberá mantener consistencia si falla cualquiera de las etapas.

---

### 428. Authenticator policy changes

Si una credencial deja de cumplir una nueva política, podrá:

* permanecer temporalmente;
* requerir reemplazo;
* restringirse a operaciones de bajo riesgo;
* suspenderse.

---

### 429. Credential policy evaluator

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

### 430. Credential inventory events

Eventos recomendados:

* `PasskeyRegistered`;
* `PasskeyRenamed`;
* `PasskeySuspended`;
* `PasskeyRevoked`;
* `PasskeyMarkedLost`;
* `PasskeyMarkedCompromised`;
* `PasskeyReplaced`.

---

### 431. Recovery after passkey loss

La pérdida de una passkey deberá activar un flujo separado del login ordinario.

---

### 432. Recovery options

La recuperación podrá apoyarse en:

* otra passkey;
* recovery codes;
* factor MFA alternativo;
* identidad federada confiable;
* soporte administrativo;
* verificación manual.

---

### 433. Recovery assurance

El assurance resultante dependerá del método utilizado.

---

### 434. Low-assurance recovery

Una recuperación de assurance bajo deberá producir una sesión restringida.

---

### 435. Restricted recovery session

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

### 436. Post-recovery restrictions

Una sesión recuperada podrá impedir temporalmente:

* cambio de email;
* retiro de fondos;
* modificación de métodos de pago;
* eliminación de cuenta;
* nueva impersonación;
* creación de API keys.

---

### 437. Mandatory credential re-enrollment

Después de perder todas las passkeys, el sistema podrá exigir enrolar una nueva antes de restaurar acceso completo.

---

### 438. Recovery notifications

Se deberá notificar:

* inicio de recuperación;
* finalización;
* cambios de factores;
* revocación de credenciales.

---

### 439. Recovery abuse protection

Los flujos deberán protegerse contra:

* account takeover;
* social engineering;
* SIM swapping;
* abuso del soporte;
* enumeración;
* automatización.

---

### 440. Passwordless Account Bootstrap

VoltStack deberá soportar creación de cuentas sin contraseña.

---

### 441. Bootstrap methods

El bootstrap podrá realizarse mediante:

* passkey;
* invitación firmada;
* magic link;
* identidad federada;
* dispositivo administrado;
* provisioning empresarial.

---

### 442. Bootstrap identity binding

La identidad deberá verificarse antes de activar la cuenta.

---

### 443. Passwordless bootstrap flow

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

### 444. Incomplete bootstrap

Una cuenta incompleta deberá permanecer en estado `Pending`.

---

### 445. Bootstrap timeout

El proceso deberá expirar y limpiar estado provisional.

---

### 446. Passwordless-only accounts

Una cuenta podrá operar sin contraseña almacenada.

---

### 447. Authentication method declaration

El perfil de identidad deberá indicar explícitamente si la contraseña existe.

---

### 448. Credential capability profile

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

### 449. Password fallback prohibition

Una cuenta passwordless no deberá crear silenciosamente una contraseña como fallback.

---

### 450. Password enrollment

Añadir una contraseña a una cuenta passwordless será una operación de seguridad de alto impacto.

---

### 451. Passwordless recovery

El sistema deberá exigir al menos un mecanismo de recuperación compatible con la política.

---

### 452. Recovery method independence

El mecanismo de recuperación no deberá depender completamente del mismo dispositivo que contiene la única passkey.

---

### 453. Method downgrade prevention

VoltStack deberá impedir que un atacante degrade una cuenta desde un método fuerte a uno débil.

---

### 454. Authentication downgrade examples

Ataques posibles:

* cambiar passkey por SMS;
* habilitar contraseña débil;
* eliminar MFA;
* añadir email no verificado;
* cambiar recovery channel;
* forzar login por legacy API.

---

### 455. AuthenticationMethodPolicy

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

### 456. Downgrade evaluation

Una transición deberá comparar:

* assurance actual;
* assurance objetivo;
* factor independence;
* resistencia al phishing;
* hardware binding;
* recuperación disponible.

---

### 457. Downgrade authorization

Una reducción deliberada deberá requerir:

* autenticación fuerte y reciente;
* explicación;
* confirmación;
* notificación;
* auditoría.

---

### 458. Legacy client restrictions

Clientes antiguos no deberán forzar métodos menos seguros para toda la cuenta.

---

### 459. Per-client method policy

La política podrá permitir métodos específicos por:

* canal;
* aplicación;
* tenant;
* route group;
* device posture.

---

### 460. Session Security Architecture

Una sesión representa continuidad autenticada entre múltiples requests.

No deberá confundirse con la identidad misma.

---

### 461. Session trust model

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

### 462. Session security goals

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

### 463. Session types

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

### 464. SessionRecord

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

### 465. SessionIdentifier

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

### 466. Session ID generation

El identificador deberá:

* generarse con CSPRNG;
* poseer alta entropía;
* ser opaco;
* no contener user ID;
* no contener tenant ID;
* no ser secuencial;
* no ser predecible.

---

### 467. Session ID storage

El cliente recibirá el valor opaco.

El servidor podrá almacenar únicamente un digest de lookup cuando el diseño lo permita.

---

### 468. Session ID hashing

El digest deberá usar una función adecuada para búsquedas rápidas y resistencia ante exposición de la base de sesiones.

---

### 469. Session ID secrecy

El session ID deberá tratarse como bearer credential.

---

### 470. Session identifier exposure

No deberá aparecer en:

* URLs;
* logs;
* analytics;
* referrers;
* HTML;
* errores;
* traces.

---

### 471. Cookie transport

Las sesiones web deberán transportarse mediante cookies seguras.

---

### 472. Session cookie profile

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

### 473. Host-prefixed session cookies

Cuando sea posible, la cookie deberá usar prefijo:

```text
__Host-
```

---

### 474. Domain-scoped session risk

Las cookies compartidas entre subdominios aumentan la superficie de ataque.

---

### 475. Session creation

Una sesión solo deberá crearse después de una autenticación válida.

---

### 476. SessionIssuer

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

### 477. SessionIssueContext

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

### 478. Session fixation protection

Toda autenticación exitosa deberá emitir un nuevo session ID.

---

### 479. Pre-authentication sessions

Los datos almacenados antes del login deberán migrarse cuidadosamente a la nueva sesión.

---

### 480. Session data migration

Solo deberán transferirse atributos permitidos como:

* locale;
* UI preferences;
* CSRF flow state;
* carrito anónimo cuando esté autorizado.

---

### 481. Unsafe pre-auth data

No deberán migrarse automáticamente:

* identity claims;
* authorization state;
* MFA status;
* tenant privileges;
* impersonation state.

---

### 482. Session rotation

La sesión deberá rotarse ante eventos relevantes.

---

### 483. Session rotation triggers

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

### 484. SessionRotator

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

### 485. SessionRotationReason

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

### 486. Rotation transaction

La rotación deberá:

* crear nuevo ID;
* invalidar el anterior;
* transferir datos permitidos;
* preservar audit linkage;
* emitir nueva cookie.

---

### 487. Rotation grace window

Una ventana mínima podrá tolerarse para requests concurrentes legítimos.

---

### 488. Grace window restrictions

La ventana deberá:

* ser corta;
* permitir transición única;
* evitar reutilización prolongada;
* registrar uso del identificador anterior.

---

### 489. Session store

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

### 490. Session store security

El store deberá proporcionar:

* acceso autenticado;
* cifrado en tránsito;
* aislamiento;
* expiración;
* atomicidad;
* protección contra enumeración;
* auditoría.

---

### 491. Centralized versus stateless sessions

VoltStack podrá soportar:

* sesiones stateful;
* tokens de sesión firmados;
* modelos híbridos.

---

### 492. Stateful session advantages

Ventajas:

* revocación inmediata;
* menor exposición de datos;
* control de concurrencia;
* actualización centralizada;
* gestión de riesgo.

---

### 493. Stateless session risks

Riesgos:

* revocación compleja;
* claims obsoletos;
* payload expuesto;
* mayor dependencia de claves;
* replay hasta expiración.

---

### 494. Default session model

Para aplicaciones web interactivas, VoltStack deberá preferir sesiones stateful con identificador opaco.

---

### 495. SessionPolicy

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

### 496. Idle timeout

La expiración por inactividad deberá basarse en actividad válida y controlada.

---

### 497. Activity refresh

No todo request deberá renovar la actividad.

Podrán excluirse:

* polling automático;
* assets;
* health checks;
* background requests;
* telemetry.

---

### 498. Absolute timeout

Toda sesión deberá tener una expiración absoluta que no pueda extenderse indefinidamente mediante actividad.

---

### 499. Concurrent session controls

El sistema podrá limitar sesiones por:

* identidad;
* tipo;
* dispositivo;
* tenant;
* assurance;
* perfil.

La política deberá definir si al exceder el límite se rechaza la nueva sesión o se revoca la más antigua.

---

### 500. Resultado de esta entrega

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

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 6

- Session validation pipeline
- Session lifecycle states
- Session revocation
- Global logout
- Logout semantics
- Concurrent session management
- Session device inventory
- Session binding
- IP and user-agent binding risks
- Credential and authorization versioning
- Session refresh
- Remember-me architecture
- Persistent login tokens
- Refresh token rotation
- Token families
- Replay detection
- Session anomaly detection
```

## Entrega 6

**Documento:** Parte 05
**Entrega:** 6 de varias
**Cobertura:** Secciones **501–600**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 5`

---

### 501. Session Validation Pipeline

Toda petición autenticada mediante sesión deberá atravesar una validación completa antes de reconstruir el `IdentityContext`.

```text
Incoming Request
      ↓
Session Credential Extraction
      ↓
Cookie Security Validation
      ↓
Session Identifier Parsing
      ↓
Session Store Lookup
      ↓
Lifecycle Validation
      ↓
Expiration Validation
      ↓
Credential Version Validation
      ↓
Authorization Version Validation
      ↓
Tenant Binding Validation
      ↓
Device and Risk Evaluation
      ↓
Activity Update
      ↓
Identity Context Reconstruction
```

---

### 502. Session validation principle

La existencia de un identificador válido no implica que la sesión siga siendo confiable.

---

### 503. SessionValidator

```php
interface SessionValidatorInterface
{
    public function validate(
        PresentedSessionCredential $credential,
        SessionValidationContext $context
    ): SessionValidationResult;
}
```

---

### 504. PresentedSessionCredential

```php
final readonly class PresentedSessionCredential
{
    public function __construct(
        public SensitiveValue $identifier,
        public SessionTransport $transport,
        public string $cookieName,
    ) {
    }
}
```

---

### 505. SessionTransport

```php
enum SessionTransport: string
{
    case SecureCookie = 'secure_cookie';
    case AuthorizationHeader = 'authorization_header';
    case DeviceCredential = 'device_credential';
}
```

---

### 506. Query-string sessions prohibition

VoltStack no deberá aceptar identificadores de sesión desde:

* query strings;
* fragments;
* URL paths;
* formularios ocultos;

salvo protocolos legacy explícitamente aislados y deshabilitados por defecto.

---

### 507. SessionValidationContext

```php
final readonly class SessionValidationContext
{
    public function __construct(
        public RequestFingerprint $request,
        public RouteAuthenticationMetadata $route,
        public ?string $expectedTenantId,
        public DateTimeImmutable $now,
    ) {
    }
}
```

---

### 508. SessionValidationResult

```php
final readonly class SessionValidationResult
{
    public function __construct(
        public SessionValidationStatus $status,
        public ?SessionRecord $session,
        public ?IdentityContext $identity,
        public array $securityEvents,
        public bool $rotateRequired,
    ) {
    }
}
```

---

### 509. SessionValidationStatus

```php
enum SessionValidationStatus: string
{
    case Valid = 'valid';
    case Missing = 'missing';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Suspended = 'suspended';
    case ContextMismatch = 'context_mismatch';
    case ReauthenticationRequired = 'reauthentication_required';
    case RiskRejected = 'risk_rejected';
}
```

---

### 510. Generic invalid-session behavior

La respuesta externa no deberá revelar si la sesión:

* no existe;
* expiró;
* fue revocada;
* pertenece a otro tenant;
* fue reemplazada;
* falló por riesgo.

---

### 511. Session lifecycle states

```php
enum SessionLifecycleState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Restricted = 'restricted';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
    case Expired = 'expired';
    case Replaced = 'replaced';
}
```

---

### 512. Pending session

Una sesión `Pending` podrá existir durante:

* MFA incompleto;
* verificación de dispositivo;
* bootstrap;
* recuperación;
* consentimiento requerido.

---

### 513. Pending session restrictions

No deberá acceder a Controllers ordinarios autenticados.

Solo podrá acceder a rutas explícitamente autorizadas para completar el flujo.

---

### 514. Active session

Una sesión `Active` será elegible para reconstruir un contexto autenticado completo.

---

### 515. Restricted session

Una sesión `Restricted` tendrá una lista explícita de capacidades permitidas.

---

### 516. RestrictedSessionCapabilities

```php
final readonly class RestrictedSessionCapabilities
{
    public function __construct(
        public array $allowedRouteNames,
        public array $allowedOperations,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 517. Suspended session

Una sesión podrá suspenderse temporalmente por:

* riesgo;
* investigación;
* dispositivo comprometido;
* anomalía;
* acción administrativa.

---

### 518. Revoked session

Una sesión revocada deberá rechazarse permanentemente.

---

### 519. Expired session

La expiración deberá establecerse de forma explícita y auditable.

---

### 520. Replaced session

Una sesión rotada podrá conservar un registro `Replaced` para detectar reutilización del identificador anterior.

---

### 521. Session state transition rules

```text
Pending → Active
Pending → Revoked
Active → Restricted
Active → Suspended
Active → Replaced
Active → Revoked
Active → Expired
Restricted → Active
Restricted → Revoked
Suspended → Active
Suspended → Revoked
```

Las transiciones deberán ejecutarse mediante servicios controlados.

---

### 522. Session state machine

```php
interface SessionStateMachineInterface
{
    public function transition(
        SessionRecord $session,
        SessionLifecycleState $target,
        SessionTransitionContext $context
    ): SessionRecord;
}
```

---

### 523. Illegal transitions

No se permitirá:

* reactivar una sesión revocada;
* volver a activar una sesión expirada;
* convertir una sesión reemplazada en activa;
* omitir auditoría en transiciones administrativas.

---

### 524. Session expiration validation

La validación deberá comprobar:

* idle expiration;
* absolute expiration;
* policy expiration;
* credential expiration;
* emergency invalidation.

---

### 525. Server-side expiration authority

La fecha almacenada en cliente nunca será fuente de verdad para la expiración.

---

### 526. Idle activity update

La actualización de `lastActivityAt` deberá ser:

* limitada;
* atómica;
* tolerante a concurrencia;
* no ejecutada en cada request innecesariamente.

---

### 527. Activity write throttling

VoltStack podrá actualizar actividad solo cuando haya transcurrido un intervalo mínimo.

```php
final readonly class SessionActivityPolicy
{
    public function __construct(
        public int $minimumWriteIntervalSeconds,
        public array $ignoredRequestTypes,
    ) {
    }
}
```

---

### 528. Absolute expiration enforcement

La expiración absoluta deberá aplicarse aunque existan requests concurrentes o refreshes.

---

### 529. Session revocation architecture

La revocación deberá ser una operación centralizada, auditable y propagable entre nodos.

---

### 530. SessionRevocationService

```php
interface SessionRevocationServiceInterface
{
    public function revoke(
        SessionIdentifier $session,
        SessionRevocationReason $reason,
        RevocationContext $context
    ): SessionRevocationResult;

    public function revokeAll(
        IdentityIdentifier $identity,
        SessionRevocationFilter $filter,
        SessionRevocationReason $reason
    ): BulkSessionRevocationResult;
}
```

---

### 531. SessionRevocationReason

```php
enum SessionRevocationReason: string
{
    case UserLogout = 'user_logout';
    case GlobalLogout = 'global_logout';
    case PasswordChanged = 'password_changed';
    case CredentialCompromised = 'credential_compromised';
    case MfaChanged = 'mfa_changed';
    case DeviceLost = 'device_lost';
    case RiskDetected = 'risk_detected';
    case AdministrativeAction = 'administrative_action';
    case AccountSuspended = 'account_suspended';
    case TokenReplay = 'token_replay';
    case SecurityIncident = 'security_incident';
}
```

---

### 532. Revocation context

```php
final readonly class RevocationContext
{
    public function __construct(
        public IdentityIdentifier $actor,
        public DateTimeImmutable $occurredAt,
        public ?string $incidentId,
        public ?string $reasonDetail,
    ) {
    }
}
```

---

### 533. Revocation propagation

En despliegues distribuidos, la revocación deberá propagarse mediante:

* store compartido;
* eventos;
* invalidation bus;
* cache eviction;
* revocation registry.

---

### 534. Revocation latency

Los perfiles de seguridad deberán definir el máximo retraso aceptable.

---

### 535. Fail-closed revocation

En operaciones críticas, si no puede confirmarse el estado de revocación, la sesión deberá rechazarse.

---

### 536. Global logout

El logout global deberá invalidar todas las sesiones elegibles de una identidad.

---

### 537. Global logout filters

La política podrá excluir o incluir:

* sesión actual;
* sesiones de servicio;
* sesiones administrativas;
* dispositivos administrados;
* impersonaciones;
* tokens persistentes.

---

### 538. SessionRevocationFilter

```php
final readonly class SessionRevocationFilter
{
    public function __construct(
        public bool $includeCurrent,
        public array $sessionTypes,
        public ?string $tenantId,
        public ?DateTimeImmutable $issuedBefore,
    ) {
    }
}
```

---

### 539. Logout semantics

Logout deberá significar más que borrar una cookie.

---

### 540. Logout flow

```text
Authenticated Request
      ↓
Validate Session
      ↓
Revoke Server-Side Session
      ↓
Revoke or Detach Persistent Tokens
      ↓
Invalidate CSRF State
      ↓
Expire Client Cookie
      ↓
Audit
      ↓
Return Safe Response
```

---

### 541. Cookie deletion

La cookie deberá eliminarse usando los mismos atributos relevantes con los que fue emitida:

* name;
* path;
* domain;
* secure context.

---

### 542. Logout idempotency

Repetir logout deberá ser seguro y producir una respuesta consistente.

---

### 543. Logout CSRF protection

El endpoint de logout deberá protegerse contra logout CSRF cuando la aplicación lo requiera.

---

### 544. GET logout prohibition

Logout mediante `GET` deberá evitarse por defecto.

---

### 545. Post-logout redirect

El redirect deberá pasar por la política de redirects seguros.

---

### 546. Logout event

```php
final readonly class SessionLoggedOut
{
    public function __construct(
        public IdentityIdentifier $identity,
        public SessionIdentifier $session,
        public SessionRevocationReason $reason,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
```

---

### 547. Concurrent session management

El usuario deberá poder revisar y administrar sesiones activas.

---

### 548. Session inventory

```php
final readonly class SessionInventoryItem
{
    public function __construct(
        public string $displayId,
        public SessionType $type,
        public SessionLifecycleState $state,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $lastActivityAt,
        public ?string $deviceName,
        public ?string $approximateLocation,
        public bool $current,
        public AuthenticationAssuranceLevel $assurance,
    ) {
    }
}
```

---

### 549. Inventory data minimization

El inventario no deberá exponer:

* session ID real;
* IP completa por defecto;
* fingerprint interno;
* tokens;
* cookie values;
* datos de red precisos innecesarios.

---

### 550. Session display identifier

El `displayId` deberá ser un identificador seguro separado del session ID autenticante.

---

### 551. Session device inventory

Cada sesión podrá asociarse a un dispositivo reconocido.

---

### 552. SessionDeviceBinding

```php
final readonly class SessionDeviceBinding
{
    public function __construct(
        public string $sessionId,
        public ?DeviceIdentifier $device,
        public DeviceBindingStrength $strength,
        public DateTimeImmutable $boundAt,
    ) {
    }
}
```

---

### 553. DeviceBindingStrength

```php
enum DeviceBindingStrength: string
{
    case None = 'none';
    case Observed = 'observed';
    case CookieBound = 'cookie_bound';
    case Cryptographic = 'cryptographic';
    case Managed = 'managed';
}
```

---

### 554. Passive binding limitations

Un user agent o IP no constituye una vinculación criptográfica.

---

### 555. IP binding risks

Vincular estrictamente una sesión a IP puede causar problemas por:

* redes móviles;
* NAT;
* proxies;
* IPv6 privacy addresses;
* roaming;
* VPN;
* cambios corporativos.

---

### 556. Recommended IP usage

La IP deberá utilizarse como señal de riesgo, no como identidad rígida por defecto.

---

### 557. User-Agent binding risks

El User-Agent puede:

* cambiar;
* falsificarse;
* reducirse por privacidad;
* ser idéntico en muchos dispositivos.

---

### 558. Request fingerprint

```php
final readonly class RequestFingerprint
{
    public function __construct(
        public ?string $ipPrefix,
        public ?string $userAgentFamily,
        public ?string $deviceCookieId,
        public ?string $tlsFingerprint,
        public ?string $clientInstanceId,
    ) {
    }
}
```

---

### 559. Fingerprint classification

Cada señal deberá marcarse como:

* stable;
* semi-stable;
* volatile;
* untrusted;
* privacy-sensitive.

---

### 560. Binding mismatch response

Un cambio de fingerprint podrá provocar:

* continuar;
* elevar riesgo;
* requerir step-up;
* rotar sesión;
* restringir;
* revocar.

---

### 561. Cryptographic session binding

VoltStack podrá soportar sesiones vinculadas a una clave del cliente.

---

### 562. Proof-of-possession session

```php
final readonly class ProofOfPossessionSessionBinding
{
    public function __construct(
        public string $publicKeyThumbprint,
        public string $algorithm,
        public DateTimeImmutable $boundAt,
    ) {
    }
}
```

---

### 563. Proof-of-possession benefits

Reduce el valor de un session ID robado al requerir prueba adicional de clave.

---

### 564. Proof-of-possession limitations

Puede aumentar:

* complejidad;
* incompatibilidad;
* problemas de recuperación;
* dependencia del cliente;
* costo operacional.

---

### 565. Credential versioning

La identidad deberá mantener una versión de credenciales.

---

### 566. CredentialVersion

```php
final readonly class CredentialVersion
{
    public function __construct(
        public int $value,
        public DateTimeImmutable $updatedAt,
        public CredentialVersionReason $reason,
    ) {
    }
}
```

---

### 567. CredentialVersionReason

```php
enum CredentialVersionReason: string
{
    case PasswordChanged = 'password_changed';
    case PasswordReset = 'password_reset';
    case MfaChanged = 'mfa_changed';
    case PasskeyRevoked = 'passkey_revoked';
    case RecoveryCompleted = 'recovery_completed';
    case SecurityIncident = 'security_incident';
}
```

---

### 568. Credential version validation

La versión almacenada en sesión deberá compararse con la versión actual de la identidad.

---

### 569. Version mismatch

Una discrepancia podrá:

* revocar la sesión;
* exigir reautenticación;
* restringir operaciones;
* permitir continuidad limitada según política.

---

### 570. Authorization versioning

La sesión deberá poder detectar cambios en:

* roles;
* permisos;
* tenant membership;
* policies;
* resource scopes;
* account status.

---

### 571. AuthorizationVersion

```php
final readonly class AuthorizationVersion
{
    public function __construct(
        public int $value,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
```

---

### 572. Authorization cache invalidation

Los permisos derivados dentro de la sesión deberán invalidarse cuando cambie la versión.

---

### 573. Stale authorization prevention

Una sesión no deberá conservar privilegios indefinidamente después de:

* quitar un rol;
* eliminar tenant membership;
* revocar una policy;
* cambiar resource scope.

---

### 574. Authorization refresh strategy

El framework podrá:

* reconstruir contexto;
* invalidar cache;
* rotar sesión;
* revocar;
* exigir nueva autenticación para cambios sensibles.

---

### 575. Session refresh

Session refresh significa renovar estado de sesión sin repetir necesariamente la autenticación primaria.

---

### 576. Refresh eligibility

Solo una sesión:

* activa;
* no expirada;
* no revocada;
* compatible con policy;
* sin riesgo crítico;

podrá renovarse.

---

### 577. Refresh operation

```php
interface SessionRefreshServiceInterface
{
    public function refresh(
        SessionRecord $session,
        SessionRefreshContext $context
    ): SessionRefreshResult;
}
```

---

### 578. Refresh rotation

La renovación deberá rotar el identificador cuando el perfil lo requiera.

---

### 579. Refresh does not reset absolute lifetime

La renovación no deberá extender la expiración absoluta más allá de la política original, salvo reautenticación explícita.

---

### 580. Remember-Me Architecture

“Remember me” no deberá significar una sesión ordinaria de duración indefinida.

---

### 581. Persistent login model

VoltStack deberá separar:

* sesión interactiva corta;
* credential persistente de reautenticación;
* nueva sesión emitida después de validar esa credential.

---

### 582. PersistentLoginToken

```php
final readonly class PersistentLoginToken
{
    public function __construct(
        public string $tokenId,
        public string $familyId,
        public IdentityIdentifier $identity,
        public string $secretDigest,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public PersistentTokenState $state,
        public ?string $deviceId,
        public ?string $tenantId,
    ) {
    }
}
```

---

### 583. PersistentTokenState

```php
enum PersistentTokenState: string
{
    case Active = 'active';
    case Rotated = 'rotated';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
    case Expired = 'expired';
}
```

---

### 584. Persistent token properties

El token deberá ser:

* aleatorio;
* opaco;
* de alta entropía;
* revocable;
* rotatable;
* vinculado a una familia;
* almacenado de forma derivada.

---

### 585. Selector-validator split

El token podrá representarse mediante:

```text
selector.validator
```

Donde:

* el selector permite lookup;
* el validator actúa como secreto;
* solo se almacena su digest.

---

### 586. Persistent cookie profile

La cookie deberá ser:

* `Secure`;
* `HttpOnly`;
* `SameSite` apropiado;
* scope reducido;
* separada de la cookie de sesión;
* revocable.

---

### 587. Remember-me authentication strength

Una sesión creada desde persistent login deberá iniciar con menor freshness que una autenticación interactiva reciente.

---

### 588. Sensitive route step-up

Las operaciones sensibles deberán exigir nueva autenticación aunque la sesión provenga de remember-me.

---

### 589. Persistent token rotation

Cada uso válido deberá emitir un nuevo token y retirar el anterior.

---

### 590. Token family

Una familia representa la cadena de rotaciones derivadas de un mismo enrolamiento persistente.

---

### 591. PersistentTokenFamily

```php
final readonly class PersistentTokenFamily
{
    public function __construct(
        public string $familyId,
        public IdentityIdentifier $identity,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $revokedAt,
        public PersistentTokenFamilyState $state,
    ) {
    }
}
```

---

### 592. PersistentTokenFamilyState

```php
enum PersistentTokenFamilyState: string
{
    case Active = 'active';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
}
```

---

### 593. Rotation transaction

El uso deberá ejecutar atómicamente:

```text
Validate Current Token
      ↓
Mark Current as Rotated
      ↓
Create Successor Token
      ↓
Issue New Session
      ↓
Issue New Persistent Cookie
```

---

### 594. Refresh token reuse detection

Si un token marcado como `Rotated` vuelve a utilizarse, deberá considerarse replay.

---

### 595. Family revocation on replay

Ante reuse confirmado deberá:

* marcar la familia como comprometida;
* revocar tokens descendientes;
* revocar sesiones asociadas según política;
* elevar riesgo;
* notificar al usuario;
* generar incidente.

---

### 596. Concurrent request tolerance

El diseño deberá diferenciar entre:

* race legítimo;
* retry de red;
* replay malicioso.

---

### 597. Rotation grace record

Podrá conservarse una ventana muy corta de idempotencia vinculada al mismo request context.

No deberá permitir emitir múltiples descendientes válidos.

---

### 598. Session anomaly detection

El motor deberá evaluar anomalías como:

* cambio abrupto de geografía;
* uso simultáneo incompatible;
* token reutilizado;
* dispositivo comprometido;
* user agent imposible;
* velocidad anormal;
* sesión antigua reactivada;
* sesión reemplazada reutilizada.

---

### 599. SessionAnomalyEngine

```php
interface SessionAnomalyEngineInterface
{
    public function assess(
        SessionRecord $session,
        SessionValidationContext $context
    ): SessionAnomalyAssessment;
}
```

Las acciones posibles incluirán:

* permitir;
* rotar;
* requerir step-up;
* restringir;
* suspender;
* revocar;
* abrir incidente.

---

### 600. Resultado de esta entrega

Esta entrega establece:

```text
Session Validation Pipeline
Explicit Session Lifecycle States
Session State Transitions
Idle and Absolute Expiration Enforcement
Distributed Session Revocation
Global Logout
Secure Logout Semantics
Concurrent Session Inventory
Device and Request Binding
IP and User-Agent Risk Treatment
Proof-of-Possession Session Foundations
Credential Versioning
Authorization Versioning
Stale Privilege Prevention
Session Refresh
Remember-Me Architecture
Persistent Login Tokens
Token Families
Rotation and Replay Detection
Session Anomaly Detection
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 7

- OAuth 2.0 security architecture
- Authorization server and client roles
- Authorization Code flow
- PKCE
- State and nonce protection
- Redirect URI validation
- Client authentication
- Access tokens
- Refresh tokens
- Token introspection
- Token revocation
- Scope design
- Audience binding
- Sender-constrained tokens
- DPoP
- mTLS
- OAuth mix-up prevention
- Native application security
```

## Entrega 7

**Documento:** Parte 05
**Entrega:** 7 de varias
**Cobertura:** Secciones **601–700**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 6`

---

### 601. OAuth 2.0 Security Architecture

OAuth 2.0 permitirá que una aplicación obtenga acceso limitado a recursos protegidos en nombre de:

* un usuario;
* una organización;
* un tenant;
* un servicio;
* una workload identity.

OAuth no deberá utilizarse como autenticación de usuario por sí solo.

Para autenticación federada deberá combinarse con OpenID Connect u otro protocolo de identidad explícito.

---

### 602. OAuth security goals

El subsistema deberá garantizar:

* autorización delegada;
* minimización de privilegios;
* vinculación de tokens;
* protección contra replay;
* validación estricta de redirect URIs;
* separación entre clientes;
* aislamiento multi-tenant;
* revocación;
* trazabilidad;
* compatibilidad con clientes públicos y confidenciales.

---

### 603. OAuth threat model

El modelo deberá considerar:

* authorization code interception;
* authorization code injection;
* token theft;
* token replay;
* redirect URI manipulation;
* mix-up attacks;
* CSRF;
* open redirects;
* malicious clients;
* client impersonation;
* scope escalation;
* audience confusion;
* refresh token theft;
* downgrade;
* consent phishing;
* device code abuse.

---

### 604. OAuth actors

```php
enum OAuthActorRole: string
{
    case ResourceOwner = 'resource_owner';
    case Client = 'client';
    case AuthorizationServer = 'authorization_server';
    case ResourceServer = 'resource_server';
}
```

---

### 605. Authorization server boundary

El Authorization Server deberá ser responsable de:

* autenticación del usuario;
* consentimiento;
* autorización;
* emisión de códigos;
* emisión de tokens;
* refresh;
* revocación;
* introspection;
* metadata.

---

### 606. Resource server boundary

El Resource Server deberá:

* validar tokens;
* validar audience;
* validar scopes;
* validar sender constraint;
* aplicar autorización;
* rechazar tokens expirados o revocados.

---

### 607. OAuth client types

```php
enum OAuthClientType: string
{
    case Public = 'public';
    case Confidential = 'confidential';
}
```

---

### 608. Public clients

Los clientes públicos no podrán mantener un secreto de forma confiable.

Ejemplos:

* SPA;
* aplicación móvil;
* aplicación desktop;
* CLI distribuido.

---

### 609. Confidential clients

Los clientes confidenciales podrán autenticarse mediante:

* client secret;
* private key JWT;
* mTLS;
* mecanismo equivalente.

---

### 610. OAuthClient

```php
final readonly class OAuthClient
{
    public function __construct(
        public string $clientId,
        public OAuthClientType $type,
        public string $displayName,
        public array $redirectUris,
        public array $allowedGrantTypes,
        public array $allowedScopes,
        public array $allowedAudiences,
        public OAuthClientStatus $status,
        public ?string $tenantId,
    ) {
    }
}
```

---

### 611. OAuthClientStatus

```php
enum OAuthClientStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
    case Retired = 'retired';
}
```

---

### 612. Client registration

El registro deberá validar:

* ownership;
* redirect URIs;
* application type;
* contacts;
* scopes solicitados;
* tenant;
* grant types;
* token endpoint auth method.

---

### 613. Dynamic client registration

Solo deberá habilitarse con política explícita y controles estrictos.

---

### 614. Client metadata immutability

Los cambios críticos deberán versionarse y auditarse.

---

### 615. Authorization grant types

VoltStack podrá soportar:

* Authorization Code;
* Client Credentials;
* Device Authorization;
* Token Exchange;
* Refresh Token.

---

### 616. Deprecated grant types

Deberán deshabilitarse por defecto:

* Implicit Grant;
* Resource Owner Password Credentials.

---

### 617. Authorization Code flow

El Authorization Code será el flujo principal para aplicaciones interactivas.

```text
Client
  ↓ Authorization Request
Authorization Server
  ↓ User Authentication
  ↓ Consent
  ↓ Authorization Code
Client
  ↓ Code + PKCE Verifier
Token Endpoint
  ↓ Access Token
  ↓ Optional Refresh Token
```

---

### 618. AuthorizationRequest

```php
final readonly class AuthorizationRequest
{
    public function __construct(
        public string $clientId,
        public string $redirectUri,
        public array $scopes,
        public string $state,
        public string $responseType,
        public ?string $codeChallenge,
        public ?string $codeChallengeMethod,
        public ?string $nonce,
        public ?string $audience,
    ) {
    }
}
```

---

### 619. Authorization request validation

Se deberá validar antes de iniciar autenticación:

* client;
* status;
* redirect URI;
* response type;
* grant type;
* scopes;
* PKCE;
* audience;
* tenant;
* request object cuando exista.

---

### 620. Redirect URI exact matching

La URI deberá coincidir exactamente con una URI registrada.

---

### 621. Redirect URI normalization prohibition

No deberán realizarse normalizaciones permisivas que cambien la semántica.

---

### 622. Redirect URI restrictions

No deberán permitirse:

* fragments;
* wildcards generales;
* credenciales embebidas;
* esquemas inseguros;
* open redirects;
* hosts no registrados.

---

### 623. Localhost redirects

Para aplicaciones nativas podrán permitirse loopback redirects con reglas específicas.

---

### 624. Custom URI schemes

Los custom schemes deberán seguir políticas estrictas y preferir esquemas reclamados de forma verificable cuando sea posible.

---

### 625. State parameter

`state` deberá proteger la correlación del flujo y reducir CSRF.

---

### 626. State generation

Deberá ser:

* aleatorio;
* opaco;
* de alta entropía;
* de un solo uso;
* vinculado a sesión;
* temporal.

---

### 627. State storage

El cliente deberá correlacionarlo con el flujo iniciado.

El Authorization Server también podrá mantener binding adicional.

---

### 628. State mismatch

Un mismatch deberá cancelar completamente el flujo.

---

### 629. PKCE

PKCE deberá ser obligatorio para clientes públicos y recomendado para todos los clientes.

---

### 630. Code challenge methods

VoltStack deberá permitir:

```php
enum PkceCodeChallengeMethod: string
{
    case S256 = 'S256';
}
```

El método `plain` deberá deshabilitarse por defecto.

---

### 631. Code verifier

El verifier deberá:

* ser aleatorio;
* poseer suficiente entropía;
* respetar longitud permitida;
* nunca enviarse en la autorización inicial.

---

### 632. Code challenge binding

El authorization code deberá vincularse al challenge emitido.

---

### 633. AuthorizationCode

```php
final readonly class AuthorizationCode
{
    public function __construct(
        public string $id,
        public string $secretDigest,
        public string $clientId,
        public string $redirectUri,
        public IdentityIdentifier $subject,
        public array $scopes,
        public ?string $codeChallenge,
        public ?string $codeChallengeMethod,
        public DateTimeImmutable $expiresAt,
        public bool $consumed,
        public ?string $tenantId,
    ) {
    }
}
```

---

### 634. Authorization code properties

El código deberá ser:

* opaco;
* temporal;
* de un solo uso;
* vinculado a client;
* vinculado a redirect URI;
* vinculado a PKCE;
* vinculado a subject;
* vinculado a tenant;
* vinculado a scopes.

---

### 635. Authorization code storage

Se deberá almacenar solo una representación derivada cuando sea posible.

---

### 636. Code consumption

El consumo deberá ser atómico.

---

### 637. Code replay

Reutilizar un code consumido deberá generar un evento de seguridad.

---

### 638. Code lifetime

La vida útil deberá ser corta.

---

### 639. Token endpoint

El endpoint deberá aceptar únicamente:

* TLS;
* métodos permitidos;
* content type esperado;
* client authentication cuando aplique;
* parámetros estrictamente validados.

---

### 640. TokenRequest

```php
final readonly class TokenRequest
{
    public function __construct(
        public OAuthGrantType $grantType,
        public string $clientId,
        public ?SensitiveValue $clientCredential,
        public ?SensitiveValue $authorizationCode,
        public ?SensitiveValue $codeVerifier,
        public ?SensitiveValue $refreshToken,
        public array $requestedScopes,
    ) {
    }
}
```

---

### 641. OAuthGrantType

```php
enum OAuthGrantType: string
{
    case AuthorizationCode = 'authorization_code';
    case RefreshToken = 'refresh_token';
    case ClientCredentials = 'client_credentials';
    case DeviceCode = 'urn:ietf:params:oauth:grant-type:device_code';
    case TokenExchange = 'urn:ietf:params:oauth:grant-type:token-exchange';
}
```

---

### 642. Client authentication

Clientes confidenciales deberán autenticarse en el token endpoint.

---

### 643. ClientSecretBasic

Podrá soportarse para compatibilidad controlada.

---

### 644. Client secret storage

Los secretos deberán almacenarse:

* como digest;
* en secret manager;
* con versionado;
* con rotación;
* con auditoría.

---

### 645. Private key JWT

Deberá preferirse para clientes confidenciales de alto valor.

---

### 646. Client assertion validation

Se deberá validar:

* issuer;
* subject;
* audience;
* expiration;
* issued-at;
* JWT ID;
* signature;
* key status;
* replay.

---

### 647. Client assertion replay registry

```php
interface ClientAssertionReplayRegistryInterface
{
    public function consume(
        string $clientId,
        string $jwtId,
        DateTimeImmutable $expiresAt
    ): bool;
}
```

---

### 648. mTLS client authentication

VoltStack podrá soportar autenticación del cliente mediante certificado TLS.

---

### 649. Client authentication downgrade

Un cliente registrado con método fuerte no deberá usar silenciosamente uno más débil.

---

### 650. Access token

```php
final readonly class AccessToken
{
    public function __construct(
        public string $tokenId,
        public string $issuer,
        public string $subject,
        public array $audiences,
        public array $scopes,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public string $clientId,
        public ?string $tenantId,
        public ?SenderConstraint $senderConstraint,
    ) {
    }
}
```

---

### 651. Access token formats

VoltStack podrá soportar:

* opaque tokens;
* JWT access tokens.

---

### 652. Opaque token advantages

Ventajas:

* revocación inmediata;
* menor exposición;
* introspection central;
* claims dinámicos;
* menor riesgo de uso fuera de contexto.

---

### 653. JWT access token advantages

Ventajas:

* validación local;
* menor dependencia en tiempo de request;
* escalabilidad;
* interoperabilidad.

---

### 654. JWT access token risks

Riesgos:

* revocación difícil;
* claims obsoletos;
* exposición de metadata;
* confusion entre tipos de token;
* dependencia de claves.

---

### 655. Access token type separation

Los access tokens deberán distinguirse claramente de:

* ID tokens;
* refresh tokens;
* authorization codes;
* session tokens.

---

### 656. typ header

Los JWT deberán usar un `typ` explícito cuando el perfil lo requiera.

---

### 657. Token issuer validation

El Resource Server deberá validar el issuer exacto esperado.

---

### 658. Audience validation

Un token deberá ser válido únicamente para audiences autorizados.

---

### 659. Audience confusion prevention

Un servicio no deberá aceptar un token emitido para otro recurso.

---

### 660. Scope model

Los scopes deberán representar privilegios delegados de forma clara y limitada.

---

### 661. Scope naming

Ejemplo:

```text
profile.read
billing.read
billing.write
files.download
tenant.admin
```

---

### 662. Scope hierarchy

Las jerarquías implícitas deberán evitarse o documentarse claramente.

---

### 663. ScopeRegistry

```php
interface OAuthScopeRegistryInterface
{
    public function resolve(string $scope): OAuthScopeDefinition;

    public function validateForClient(
        string $clientId,
        array $scopes
    ): ScopeValidationResult;
}
```

---

### 664. OAuthScopeDefinition

```php
final readonly class OAuthScopeDefinition
{
    public function __construct(
        public string $name,
        public string $description,
        public bool $requiresConsent,
        public bool $highRisk,
        public array $allowedAudiences,
    ) {
    }
}
```

---

### 665. Scope minimization

Solo deberán emitirse scopes:

* solicitados;
* permitidos al client;
* autorizados por el usuario;
* compatibles con la policy;
* compatibles con el audience.

---

### 666. Incremental authorization

VoltStack podrá solicitar scopes adicionales solo cuando sean necesarios.

---

### 667. Consent architecture

El consentimiento deberá ser:

* comprensible;
* específico;
* vinculado al client;
* vinculado a scopes;
* revocable;
* auditable.

---

### 668. ConsentRecord

```php
final readonly class ConsentRecord
{
    public function __construct(
        public string $consentId,
        public IdentityIdentifier $subject,
        public string $clientId,
        public array $scopes,
        public array $audiences,
        public DateTimeImmutable $grantedAt,
        public ?DateTimeImmutable $expiresAt,
        public ConsentStatus $status,
    ) {
    }
}
```

---

### 669. ConsentStatus

```php
enum ConsentStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
```

---

### 670. First-party clients

El consentimiento podrá omitirse solo para clientes first-party explícitamente confiables y gobernados.

---

### 671. Administrative consent

En entornos empresariales podrá existir consentimiento otorgado por administrador.

---

### 672. Consent phishing protection

La interfaz deberá mostrar claramente:

* nombre del client;
* publisher;
* scopes;
* tenant;
* destino de los datos;
* riesgos.

---

### 673. Refresh tokens

Los refresh tokens deberán tratarse como credenciales de alta sensibilidad.

---

### 674. RefreshToken

```php
final readonly class RefreshToken
{
    public function __construct(
        public string $tokenId,
        public string $familyId,
        public string $secretDigest,
        public string $clientId,
        public IdentityIdentifier $subject,
        public array $scopes,
        public array $audiences,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public RefreshTokenState $state,
        public ?string $tenantId,
        public ?SenderConstraint $senderConstraint,
    ) {
    }
}
```

---

### 675. RefreshTokenState

```php
enum RefreshTokenState: string
{
    case Active = 'active';
    case Rotated = 'rotated';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
    case Expired = 'expired';
}
```

---

### 676. Refresh token rotation

Los refresh tokens deberán rotarse en clientes públicos.

---

### 677. Refresh token family

Una familia deberá permitir detectar reuse.

---

### 678. Refresh token replay

Ante reuse confirmado deberá:

* revocarse la familia;
* invalidar descendientes;
* elevar riesgo;
* notificar;
* registrar incidente.

---

### 679. Refresh token scope

Un refresh no deberá ampliar scopes.

---

### 680. Refresh token audience

El refresh no deberá producir tokens para audiences no autorizados originalmente.

---

### 681. Refresh token expiration

Deberán existir:

* expiración absoluta;
* posible expiración por inactividad;
* revocación administrativa;
* revocación por consentimiento.

---

### 682. Token introspection

Los tokens opacos deberán poder validarse mediante introspection.

---

### 683. TokenIntrospectionService

```php
interface TokenIntrospectionServiceInterface
{
    public function introspect(
        SensitiveValue $token,
        IntrospectionClientContext $client
    ): TokenIntrospectionResult;
}
```

---

### 684. TokenIntrospectionResult

```php
final readonly class TokenIntrospectionResult
{
    public function __construct(
        public bool $active,
        public ?string $subject,
        public array $scopes,
        public array $audiences,
        public ?DateTimeImmutable $expiresAt,
        public ?string $clientId,
        public ?string $tenantId,
        public ?SenderConstraint $senderConstraint,
    ) {
    }
}
```

---

### 685. Introspection authorization

Solo Resource Servers autorizados deberán poder consultar tokens relevantes.

---

### 686. Introspection minimization

La respuesta deberá exponer únicamente claims necesarios.

---

### 687. Token revocation

VoltStack deberá ofrecer revocación para:

* access tokens cuando aplique;
* refresh tokens;
* token families;
* consent;
* client sessions.

---

### 688. TokenRevocationService

```php
interface TokenRevocationServiceInterface
{
    public function revoke(
        SensitiveValue $token,
        TokenRevocationContext $context
    ): TokenRevocationResult;
}
```

---

### 689. Revocation endpoint privacy

El endpoint deberá responder de forma idempotente y evitar revelar si el token existía.

---

### 690. Sender-constrained tokens

Un token podrá vincularse criptográficamente al cliente que lo presenta.

---

### 691. SenderConstraint

```php
final readonly class SenderConstraint
{
    public function __construct(
        public SenderConstraintType $type,
        public string $thumbprint,
    ) {
    }
}
```

---

### 692. SenderConstraintType

```php
enum SenderConstraintType: string
{
    case Dpop = 'dpop';
    case MutualTls = 'mutual_tls';
}
```

---

### 693. DPoP

DPoP permitirá vincular el token a una clave pública controlada por el cliente.

---

### 694. DPoP proof validation

Se deberá validar:

* signature;
* public key;
* HTTP method;
* target URI;
* issued-at;
* JWT ID;
* token hash cuando aplique;
* replay.

---

### 695. DPoP replay registry

```php
interface DpopReplayRegistryInterface
{
    public function consume(
        string $thumbprint,
        string $jwtId,
        DateTimeImmutable $expiresAt
    ): bool;
}
```

---

### 696. mTLS-bound tokens

Los tokens vinculados a mTLS deberán verificar el certificado presentado por el cliente.

---

### 697. OAuth mix-up prevention

Se deberá validar estrictamente:

* issuer;
* authorization endpoint;
* token endpoint;
* client;
* state;
* redirect URI;
* metadata del proveedor.

---

### 698. Native application security

Aplicaciones nativas deberán usar:

* Authorization Code;
* PKCE;
* external user agent;
* redirect seguro;
* no embedded browser;
* almacenamiento protegido.

---

### 699. OAuth security events

Eventos recomendados:

* `OAuthAuthorizationRequested`;
* `OAuthConsentGranted`;
* `OAuthConsentRevoked`;
* `AuthorizationCodeIssued`;
* `AuthorizationCodeReplayed`;
* `AccessTokenIssued`;
* `RefreshTokenRotated`;
* `RefreshTokenReused`;
* `TokenRevoked`;
* `ClientAuthenticationFailed`;
* `DpopReplayDetected`;
* `OAuthMixUpDetected`.

---

### 700. Resultado de esta entrega

Esta entrega establece:

```text
OAuth 2.0 Security Architecture
Authorization Server and Resource Server Boundaries
Public and Confidential Clients
Authorization Code Flow
Strict Redirect URI Validation
State Protection
PKCE
Authorization Code Binding
Token Endpoint Security
Client Authentication
Private Key JWT
mTLS Client Authentication
Opaque and JWT Access Tokens
Issuer and Audience Validation
Scope Design
Consent Architecture
Refresh Token Rotation
Token Families
Replay Detection
Token Introspection
Token Revocation
Sender-Constrained Tokens
DPoP
mTLS-Bound Tokens
OAuth Mix-Up Prevention
Native Application Security
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 8

- OpenID Connect architecture
- ID tokens
- Authentication requests
- nonce
- acr and amr
- Authentication Context
- UserInfo endpoint
- Claims mapping
- Federation trust
- Provider discovery
- JWKS validation
- Key rotation
- Pairwise subject identifiers
- Front-channel and back-channel logout
- OIDC session management
- External identity linking
- Account linking security
- Identity provider compromise response
```

## Entrega 8

**Documento:** Parte 05
**Entrega:** 8 de varias
**Cobertura:** Secciones **701–800**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 7`

---

### 701. OpenID Connect Security Architecture

OpenID Connect permitirá autenticar identidades sobre OAuth 2.0 mediante un protocolo explícito de identidad.

La arquitectura deberá separar:

* OpenID Provider;
* Relying Party;
* Authorization Server;
* UserInfo endpoint;
* ID Token verifier;
* federation registry;
* external identity store;
* account linking service;
* session bridge.

---

### 702. OIDC security goals

El subsistema deberá garantizar:

* autenticación verificable;
* validación estricta del issuer;
* audience binding;
* protección mediante nonce;
* correlación con el flujo OAuth;
* validación criptográfica;
* mapeo seguro de claims;
* aislamiento multi-tenant;
* prevención de account takeover;
* revocación y logout federado.

---

### 703. OIDC threat model

El modelo deberá considerar:

* issuer confusion;
* token substitution;
* ID Token replay;
* nonce reuse;
* malicious discovery documents;
* JWKS poisoning;
* key confusion;
* algorithm downgrade;
* audience confusion;
* subject collision;
* email-based account takeover;
* unsafe account linking;
* federation mix-up;
* stale claims;
* compromised identity provider;
* logout abuse.

---

### 704. OIDC actors

```php
enum OpenIdConnectActorRole: string
{
    case EndUser = 'end_user';
    case RelyingParty = 'relying_party';
    case OpenIdProvider = 'open_id_provider';
    case UserInfoProvider = 'userinfo_provider';
}
```

---

### 705. OpenID Provider

El OpenID Provider deberá:

* autenticar al usuario;
* emitir ID Tokens;
* publicar metadata;
* publicar claves;
* exponer UserInfo cuando corresponda;
* soportar logout según capacidades;
* declarar assurance y métodos utilizados.

---

### 706. Relying Party

VoltStack, actuando como Relying Party, deberá:

* iniciar authentication requests;
* validar discovery;
* validar issuer;
* validar ID Tokens;
* correlacionar state y nonce;
* mapear claims;
* resolver identidad externa;
* emitir sesión local.

---

### 707. OpenIdProviderDefinition

```php
final readonly class OpenIdProviderDefinition
{
    public function __construct(
        public string $providerId,
        public string $issuer,
        public string $authorizationEndpoint,
        public string $tokenEndpoint,
        public string $jwksUri,
        public ?string $userInfoEndpoint,
        public ?string $endSessionEndpoint,
        public array $supportedAlgorithms,
        public array $supportedScopes,
        public OpenIdProviderStatus $status,
        public ?string $tenantId,
    ) {
    }
}
```

---

### 708. OpenIdProviderStatus

```php
enum OpenIdProviderStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Compromised = 'compromised';
    case Retired = 'retired';
}
```

---

### 709. Provider registration

Un proveedor deberá registrarse mediante configuración confiable o discovery validado.

---

### 710. Provider trust modes

```php
enum FederationTrustMode: string
{
    case Static = 'static';
    case Discovery = 'discovery';
    case Federation = 'federation';
    case EnterpriseManaged = 'enterprise_managed';
}
```

---

### 711. Static provider configuration

La configuración estática deberá preferirse cuando:

* el proveedor sea conocido;
* exista alta sensibilidad;
* se requiera control estricto;
* el entorno sea empresarial.

---

### 712. Discovery architecture

OIDC Discovery podrá resolver metadata desde el issuer.

---

### 713. OpenIdProviderDiscoveryService

```php
interface OpenIdProviderDiscoveryServiceInterface
{
    public function discover(
        string $issuer,
        ProviderDiscoveryPolicy $policy
    ): OpenIdProviderMetadata;
}
```

---

### 714. Discovery validation

La metadata deberá validarse antes de aceptarse.

---

### 715. Issuer exact match

El valor `issuer` del documento descubierto deberá coincidir exactamente con el issuer solicitado.

---

### 716. Discovery transport security

La discovery deberá usar:

* HTTPS;
* validación TLS;
* redirects controlados;
* límites de tamaño;
* timeouts;
* protección SSRF.

---

### 717. Discovery SSRF protection

No deberán permitirse issuers que resuelvan hacia:

* localhost;
* loopback;
* link-local;
* metadata services;
* redes privadas no autorizadas;
* hosts internos.

---

### 718. Discovery cache

La metadata podrá cachearse con:

* expiración;
* versionado;
* revalidación;
* invalidación;
* fallback seguro.

---

### 719. Metadata change detection

Cambios en endpoints o algoritmos deberán producir:

* evento;
* revisión;
* posible suspensión;
* invalidación de cache.

---

### 720. OIDC Authentication Request

```php
final readonly class OpenIdAuthenticationRequest
{
    public function __construct(
        public string $providerId,
        public string $clientId,
        public string $redirectUri,
        public array $scopes,
        public string $state,
        public string $nonce,
        public string $responseType,
        public ?string $codeChallenge,
        public ?string $codeChallengeMethod,
        public ?string $prompt,
        public ?string $loginHint,
        public ?string $acrValues,
        public ?int $maxAge,
    ) {
    }
}
```

---

### 721. Required scopes

Para autenticación OIDC deberá solicitarse:

```text
openid
```

Sin ese scope, el flujo deberá tratarse como OAuth ordinario.

---

### 722. Scope minimization

Scopes adicionales deberán solicitarse solo cuando sean necesarios.

Ejemplos:

* `profile`;
* `email`;
* `phone`;
* scopes empresariales específicos.

---

### 723. Response type

El flujo recomendado será:

```text
response_type=code
```

junto con PKCE.

---

### 724. Hybrid and implicit flows

Los flujos hybrid e implicit deberán deshabilitarse por defecto.

---

### 725. Authorization Code with OIDC

El flujo deberá reutilizar todas las protecciones OAuth:

* exact redirect URI;
* state;
* PKCE;
* client authentication;
* code binding;
* token endpoint security.

---

### 726. Nonce

El nonce vincula el ID Token con la autenticación iniciada.

---

### 727. Nonce generation

El nonce deberá ser:

* aleatorio;
* opaco;
* de alta entropía;
* temporal;
* de un solo uso;
* vinculado al flujo.

---

### 728. Nonce storage

Deberá almacenarse en el estado de autenticación local.

---

### 729. Nonce validation

El nonce del ID Token deberá coincidir exactamente con el emitido.

---

### 730. Nonce reuse

La reutilización deberá considerarse replay o corrupción del flujo.

---

### 731. OIDC flow state

```php
final readonly class OpenIdAuthenticationState
{
    public function __construct(
        public string $flowId,
        public string $providerId,
        public string $stateDigest,
        public string $nonceDigest,
        public string $redirectUri,
        public DateTimeImmutable $expiresAt,
        public ?string $tenantId,
        public ?string $returnRoute,
    ) {
    }
}
```

---

### 732. Flow state consumption

El estado deberá consumirse atómicamente después del callback.

---

### 733. Prompt parameter

VoltStack podrá utilizar:

* `none`;
* `login`;
* `consent`;
* `select_account`.

La selección deberá depender de policy.

---

### 734. Silent authentication

`prompt=none` deberá manejar cuidadosamente errores como:

* login required;
* consent required;
* interaction required.

---

### 735. max_age

`max_age` permitirá exigir autenticación reciente en el proveedor.

---

### 736. auth_time validation

Cuando se solicite `max_age`, el ID Token deberá incluir y validar `auth_time`.

---

### 737. Authentication Context Class Reference

`acr` representa el nivel o contexto de autenticación aplicado por el proveedor.

---

### 738. AcrValue

```php
final readonly class AcrValue
{
    public function __construct(
        public string $value,
        public AuthenticationAssuranceLevel $mappedAssurance,
        public bool $phishingResistant,
    ) {
    }
}
```

---

### 739. acr mapping

Cada proveedor deberá tener un mapping explícito entre `acr` externo y assurance interno.

---

### 740. Unknown acr

Un valor desconocido no deberá mapearse automáticamente al nivel más alto.

---

### 741. Authentication Methods References

`amr` describe métodos utilizados durante autenticación.

Ejemplos:

* password;
* OTP;
* MFA;
* hardware key;
* biometric;
* federated.

---

### 742. AmrMapper

```php
interface AmrMapperInterface
{
    public function map(
        string $providerId,
        array $amrValues
    ): AuthenticationMethodSet;
}
```

---

### 743. amr limitations

`amr` deberá tratarse como declaración del proveedor, no como evidencia criptográfica directa de cada factor.

---

### 744. OIDC ID Token

El ID Token representa una declaración firmada sobre un evento de autenticación.

---

### 745. IdTokenClaims

```php
final readonly class IdTokenClaims
{
    public function __construct(
        public string $issuer,
        public string $subject,
        public array $audiences,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $authenticationTime,
        public ?string $nonce,
        public ?string $authorizedParty,
        public ?string $acr,
        public array $amr,
        public array $additionalClaims,
    ) {
    }
}
```

---

### 746. ID Token type separation

Un ID Token no deberá utilizarse como access token.

---

### 747. ID Token verifier

```php
interface IdTokenVerifierInterface
{
    public function verify(
        SensitiveValue $idToken,
        IdTokenVerificationContext $context
    ): IdTokenVerificationResult;
}
```

---

### 748. IdTokenVerificationContext

```php
final readonly class IdTokenVerificationContext
{
    public function __construct(
        public OpenIdProviderDefinition $provider,
        public string $clientId,
        public string $expectedNonce,
        public DateTimeImmutable $now,
        public AllowedClockSkew $clockSkew,
    ) {
    }
}
```

---

### 749. ID Token validation pipeline

```text
Parse Compact JWT
      ↓
Validate Structure
      ↓
Validate typ Policy
      ↓
Validate Algorithm
      ↓
Resolve Signing Key
      ↓
Verify Signature
      ↓
Validate Issuer
      ↓
Validate Audience
      ↓
Validate azp
      ↓
Validate Expiration
      ↓
Validate Issued-At
      ↓
Validate Nonce
      ↓
Validate auth_time
      ↓
Validate acr/amr
      ↓
Produce Verified Identity Assertion
```

---

### 750. Algorithm allowlist

Solo deberán aceptarse algoritmos registrados para ese proveedor y cliente.

---

### 751. none algorithm prohibition

`alg=none` deberá rechazarse.

---

### 752. Symmetric algorithm restrictions

Los algoritmos simétricos deberán evitarse para proveedores externos salvo configuración explícita y justificada.

---

### 753. Algorithm confusion prevention

La clave y algoritmo deberán validarse como una combinación permitida.

---

### 754. Issuer validation

El issuer del token deberá coincidir exactamente con el proveedor configurado.

---

### 755. Audience validation

El `aud` deberá contener el client ID de VoltStack.

---

### 756. Multiple audiences

Cuando existan múltiples audiences, deberá validarse `azp` cuando el perfil lo requiera.

---

### 757. Authorized party

`azp` deberá coincidir con el cliente autorizado esperado.

---

### 758. Expiration validation

El ID Token deberá rechazarse después de `exp`.

---

### 759. Issued-at validation

`iat` no deberá ubicarse irrazonablemente en el futuro.

---

### 760. Clock skew

La tolerancia deberá ser pequeña, configurable y consistente.

---

### 761. Token replay registry

Para flujos de alto riesgo podrá registrarse el hash del ID Token o identificador derivado.

---

### 762. Subject identifier

`sub` será el identificador principal del usuario dentro del issuer.

---

### 763. External identity key

Una identidad externa deberá identificarse mediante:

```text
issuer + subject
```

Nunca solo mediante email.

---

### 764. ExternalIdentityIdentifier

```php
final readonly class ExternalIdentityIdentifier
{
    public function __construct(
        public string $issuer,
        public string $subject,
    ) {
    }
}
```

---

### 765. Subject stability

El subject deberá considerarse opaco y estable según el contrato del proveedor.

---

### 766. Public subject identifiers

Un public subject puede ser igual para múltiples clientes del mismo proveedor.

---

### 767. Pairwise subject identifiers

Un pairwise subject puede variar por sector o cliente para reducir correlación.

---

### 768. Pairwise subject handling

VoltStack no deberá intentar derivar relaciones entre pairwise subjects sin soporte explícito del proveedor.

---

### 769. Sector identifier

Cuando aplique, el sector identifier deberá validarse conforme a la configuración registrada.

---

### 770. UserInfo endpoint

El UserInfo endpoint podrá proporcionar claims adicionales autorizados.

---

### 771. UserInfo request

La petición deberá usar un access token válido destinado al proveedor correspondiente.

---

### 772. UserInfo response validation

Deberán validarse:

* TLS;
* content type;
* tamaño;
* estructura;
* subject;
* firma cuando aplique;
* issuer cuando exista;
* claims permitidos.

---

### 773. UserInfo subject match

El `sub` de UserInfo deberá coincidir con el `sub` del ID Token.

---

### 774. Subject mismatch

Una discrepancia deberá considerarse un evento crítico.

---

### 775. Signed UserInfo

Cuando el proveedor soporte respuestas firmadas, la política podrá exigirlas.

---

### 776. Claims mapping architecture

Los claims externos deberán transformarse mediante un mapper específico por proveedor.

---

### 777. ExternalClaimsMapper

```php
interface ExternalClaimsMapperInterface
{
    public function map(
        OpenIdProviderDefinition $provider,
        VerifiedExternalClaims $claims
    ): ExternalIdentityProfile;
}
```

---

### 778. ExternalIdentityProfile

```php
final readonly class ExternalIdentityProfile
{
    public function __construct(
        public ExternalIdentityIdentifier $identifier,
        public ?string $email,
        public bool $emailVerified,
        public ?string $displayName,
        public ?string $givenName,
        public ?string $familyName,
        public ?string $locale,
        public array $groups,
        public array $attributes,
    ) {
    }
}
```

---

### 779. Claims trust classification

Cada claim deberá clasificarse como:

* verified;
* provider-asserted;
* derived;
* untrusted;
* informational.

---

### 780. Email claim risk

El email no deberá utilizarse por sí solo para vincular automáticamente una identidad externa con una cuenta existente.

---

### 781. email_verified limitations

`email_verified=true` solo indica que el proveedor afirma haber verificado el email bajo su propio proceso.

---

### 782. Email authority policy

VoltStack deberá definir qué proveedores son autoridades aceptadas para cada dominio o tenant.

---

### 783. Claim conflict handling

Cuando un claim externo contradiga datos locales, la policy deberá decidir si:

* preservar dato local;
* actualizar dato;
* solicitar confirmación;
* abrir revisión;
* rechazar login.

---

### 784. Group and role claims

Los grupos externos no deberán convertirse directamente en roles internos sin mapping autorizado.

---

### 785. ExternalGroupMapper

```php
interface ExternalGroupMapperInterface
{
    public function map(
        string $providerId,
        array $externalGroups,
        TenantContext $tenant
    ): ExternalAuthorizationMapping;
}
```

---

### 786. Authorization mapping restrictions

El mapping deberá:

* usar allowlists;
* estar versionado;
* ser auditable;
* evitar comodines peligrosos;
* respetar tenant boundaries.

---

### 787. External identity record

```php
final readonly class ExternalIdentityRecord
{
    public function __construct(
        public string $externalIdentityId,
        public ExternalIdentityIdentifier $identifier,
        public IdentityIdentifier $localIdentity,
        public string $providerId,
        public ExternalIdentityStatus $status,
        public DateTimeImmutable $linkedAt,
        public ?DateTimeImmutable $lastAuthenticatedAt,
        public ?string $tenantId,
    ) {
    }
}
```

---

### 788. ExternalIdentityStatus

```php
enum ExternalIdentityStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
}
```

---

### 789. Account linking architecture

Account linking conecta una identidad externa con una identidad local existente.

---

### 790. Account linking prerequisites

El linking deberá requerir:

* sesión local autenticada;
* autenticación reciente;
* assurance suficiente;
* autenticación exitosa con el proveedor;
* confirmación explícita;
* ausencia de conflictos;
* riesgo aceptable.

---

### 791. AccountLinkingService

```php
interface AccountLinkingServiceInterface
{
    public function begin(
        IdentityContext $localIdentity,
        string $providerId,
        AccountLinkingContext $context
    ): AccountLinkingChallenge;

    public function complete(
        AccountLinkingCompletion $completion
    ): AccountLinkingResult;
}
```

---

### 792. Unsafe implicit linking

No deberá vincularse automáticamente una cuenta únicamente porque ambos perfiles compartan email.

---

### 793. Existing external identity collision

Una identidad externa ya vinculada a otra cuenta deberá provocar rechazo y evento de seguridad.

---

### 794. Link uniqueness

La combinación:

```text
provider + issuer + subject
```

deberá ser única dentro del ámbito aplicable.

---

### 795. Account unlinking

Desvincular un proveedor deberá requerir:

* autenticación reciente;
* factor alternativo viable;
* protección contra self-lockout;
* auditoría;
* notificación.

---

### 796. Federated login session issuance

Una autenticación federada válida deberá convertirse en un `AuthenticationResult` interno antes de emitir sesión.

---

### 797. FederatedAuthenticationResult

```php
final readonly class FederatedAuthenticationResult
{
    public function __construct(
        public IdentityIdentifier $identity,
        public ExternalIdentityIdentifier $externalIdentity,
        public AuthenticationAssuranceLevel $assurance,
        public AuthenticationMethodSet $methods,
        public DateTimeImmutable $authenticatedAt,
        public array $claims,
        public array $riskSignals,
    ) {
    }
}
```

---

### 798. OIDC logout architecture

VoltStack deberá soportar, según capacidades del proveedor:

* local logout;
* RP-initiated logout;
* front-channel logout;
* back-channel logout;
* global federated logout.

---

### 799. Provider compromise response

Si un proveedor se marca como comprometido, VoltStack deberá poder:

* suspender nuevos logins;
* bloquear nuevos links;
* invalidar metadata y claves;
* elevar riesgo de sesiones existentes;
* revocar sesiones federadas;
* requerir autenticación local o alternativa;
* notificar a tenants afectados;
* abrir incidente.

---

### 800. Resultado de esta entrega

Esta entrega establece:

```text
OpenID Connect Security Architecture
OpenID Provider and Relying Party Boundaries
Provider Registration and Trust Modes
Secure Discovery
Discovery SSRF Protection
OIDC Authentication Requests
Authorization Code with PKCE
State and Nonce Protection
prompt and max_age Handling
acr and amr Mapping
ID Token Validation Pipeline
Algorithm Confusion Prevention
Issuer, Audience and azp Validation
Subject Identifier Security
Pairwise Subject Identifiers
UserInfo Validation
Claims Mapping
Claims Trust Classification
Email Claim Risk Controls
External Group Mapping
External Identity Records
Secure Account Linking
Account Unlinking
Federated Session Issuance
OIDC Logout Foundations
Identity Provider Compromise Response
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 9

- OIDC key management
- JWKS architecture
- Key selection
- kid validation
- JWK validation
- JWKS caching
- Key rotation
- Emergency key rollover
- Unknown signing keys
- Front-channel logout
- Back-channel logout
- RP-initiated logout
- sid claim
- Federated session registry
- SAML security foundations
- Enterprise identity federation
- Just-in-time provisioning
- SCIM identity lifecycle foundations
```

## Entrega 9

**Documento:** Parte 05
**Entrega:** 9 de varias
**Cobertura:** Secciones **801–900**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 8`

---

### 801. OIDC Cryptographic Key Architecture

VoltStack deberá implementar un subsistema independiente para administrar las claves criptográficas utilizadas por OpenID Connect.

El subsistema deberá separar:

* descubrimiento de claves;
* descarga de JWKS;
* validación estructural;
* selección de clave;
* verificación de firma;
* caching;
* rotación;
* revocación;
* respuesta ante compromiso.

---

### 802. OIDC key trust model

```text
Configured Issuer
      ↓
Trusted Discovery Metadata
      ↓
Pinned JWKS URI
      ↓
Authenticated HTTPS Retrieval
      ↓
JWK Set Validation
      ↓
Key Selection
      ↓
Algorithm Compatibility
      ↓
Signature Verification
      ↓
Verified Token
```

Ninguna clave deberá considerarse confiable únicamente porque aparezca referenciada dentro de un token.

---

### 803. JSON Web Key Set

Un JWKS representa una colección de claves públicas expresadas como objetos JWK.

El parámetro `kid` puede utilizarse para seleccionar una clave dentro del conjunto durante procesos como la rotación, aunque su estructura interna no está estandarizada y no deberá interpretarse como prueba de confianza.

---

### 804. JwkSet

```php
final readonly class JwkSet
{
    public function __construct(
        public string $issuer,
        public array $keys,
        public DateTimeImmutable $retrievedAt,
        public DateTimeImmutable $expiresAt,
        public string $sourceUri,
        public string $contentDigest,
    ) {
    }
}
```

---

### 805. JsonWebKey

```php
final readonly class JsonWebKey
{
    public function __construct(
        public string $keyType,
        public ?string $keyId,
        public ?string $algorithm,
        public ?string $use,
        public array $keyOperations,
        public PublicKeyMaterial $publicKey,
        public ?array $certificateChain,
        public ?string $certificateThumbprint,
    ) {
    }
}
```

---

### 806. Supported key types

VoltStack deberá mantener un registry explícito de tipos de clave permitidos.

Ejemplos:

* RSA;
* EC;
* OKP;
* tipos adicionales aprobados por el perfil criptográfico.

---

### 807. JwkTypeRegistry

```php
interface JwkTypeRegistryInterface
{
    public function supports(string $keyType): bool;

    public function parse(
        array $jwk
    ): JsonWebKey;
}
```

---

### 808. Unknown key types

Una clave con tipo desconocido deberá:

* ignorarse para selección;
* generar una advertencia interna;
* no provocar fallback inseguro;
* no deshabilitar la validación del resto del conjunto.

---

### 809. JWK structural validation

Cada JWK deberá validarse antes de ser incorporada al key store.

La validación deberá comprobar:

* `kty`;
* material público requerido;
* parámetros criptográficos;
* codificación Base64URL;
* longitud;
* curvas permitidas;
* ausencia de material privado;
* operaciones declaradas.

---

### 810. Private key material prohibition

Un JWKS remoto utilizado para validación no deberá contener ni aceptar material de clave privada.

Si aparece, deberá:

* rechazarse;
* registrarse como incidente;
* impedir la incorporación de la clave;
* elevar la severidad del proveedor.

---

### 811. JWK use validation

Cuando esté presente, `use` deberá ser compatible con:

```text
sig
```

para validación de firmas.

---

### 812. JWK key operations

Cuando exista `key_ops`, deberá incluir una operación compatible con verificación.

Por ejemplo:

```text
verify
```

---

### 813. Conflicting key metadata

Una clave deberá rechazarse cuando:

* `use` indique cifrado;
* `key_ops` no permita verificación;
* `alg` contradiga el algoritmo del token;
* el tipo de clave sea incompatible con el algoritmo.

---

### 814. Algorithm-to-key compatibility

```php
interface JwkAlgorithmCompatibilityCheckerInterface
{
    public function supports(
        JsonWebKey $key,
        string $algorithm
    ): bool;
}
```

---

### 815. Algorithm confusion prevention

VoltStack no deberá seleccionar una clave únicamente porque su `kid` coincida.

También deberá validar:

* algoritmo permitido;
* tipo de clave;
* uso;
* operaciones;
* issuer;
* proveedor;
* política criptográfica.

---

### 816. JWK Set retrieval

```php
interface JwkSetProviderInterface
{
    public function get(
        OpenIdProviderDefinition $provider,
        JwkSetRetrievalPolicy $policy
    ): JwkSet;
}
```

---

### 817. JwkSetRetrievalPolicy

```php
final readonly class JwkSetRetrievalPolicy
{
    public function __construct(
        public int $connectionTimeoutMilliseconds,
        public int $requestTimeoutMilliseconds,
        public int $maximumResponseBytes,
        public int $maximumKeys,
        public bool $allowRedirects,
        public int $maximumRedirects,
        public bool $requireHttps,
    ) {
    }
}
```

---

### 818. JWKS URI trust

El `jwks_uri` deberá obtenerse de:

* configuración estática confiable;
* discovery validado;
* federation metadata verificada.

Nunca deberá aceptarse directamente desde un parámetro de token.

---

### 819. Embedded JWK headers

Headers como `jwk`, `jku` o referencias equivalentes no deberán habilitar selección arbitraria de claves.

---

### 820. Remote key URL restrictions

VoltStack deberá rechazar URLs de claves dirigidas hacia:

* localhost;
* loopback;
* link-local;
* servicios de metadata;
* redes internas no permitidas;
* esquemas distintos de HTTPS;
* hosts no asociados al proveedor.

---

### 821. JWKS SSRF protection

La descarga deberá reutilizar la política SSRF del framework.

```php
interface SecureRemoteResourceFetcherInterface
{
    public function fetch(
        TrustedRemoteResource $resource,
        RemoteFetchPolicy $policy
    ): RemoteResourceResponse;
}
```

---

### 822. Redirect validation

Cada redirect deberá volver a validar:

* scheme;
* hostname;
* IP resuelta;
* red de destino;
* relación con el issuer;
* límite de redirects.

---

### 823. DNS rebinding protection

La resolución deberá proteger contra cambios entre:

* validación inicial;
* conexión;
* redirects;
* reutilización de conexión.

---

### 824. JWKS response validation

La respuesta deberá comprobar:

* código HTTP;
* content type;
* tamaño;
* JSON válido;
* propiedad `keys`;
* número máximo de claves;
* ausencia de duplicados peligrosos.

---

### 825. Maximum key count

El límite de claves evitará:

* consumo excesivo de memoria;
* ataques de CPU;
* búsquedas no acotadas;
* key flooding.

---

### 826. Duplicate kid handling

Dos claves con el mismo `kid` no deberán provocar selección arbitraria.

---

### 827. Duplicate key selection policy

Ante múltiples coincidencias, VoltStack deberá filtrar por:

* algoritmo;
* tipo de clave;
* uso;
* operaciones;
* thumbprint;
* estado;
* periodo de vigencia.

Si persiste la ambigüedad, la validación deberá fallar.

---

### 828. Key identifier semantics

`kid` deberá considerarse un selector, no un identificador criptográfico único global.

---

### 829. JWK thumbprint

VoltStack podrá calcular un thumbprint canónico para identificar material criptográfico independientemente del `kid`.

El procedimiento deberá seguir un algoritmo estable de canonicalización y hash.

---

### 830. JwkThumbprint

```php
final readonly class JwkThumbprint
{
    public function __construct(
        public string $algorithm,
        public string $value,
    ) {
    }
}
```

---

### 831. JwkThumbprintCalculator

```php
interface JwkThumbprintCalculatorInterface
{
    public function calculate(
        JsonWebKey $key
    ): JwkThumbprint;
}
```

---

### 832. Key selection input

```php
final readonly class JwkSelectionContext
{
    public function __construct(
        public string $issuer,
        public ?string $keyId,
        public string $algorithm,
        public string $tokenType,
        public ?string $x509Thumbprint,
    ) {
    }
}
```

---

### 833. JwkSelector

```php
interface JwkSelectorInterface
{
    public function select(
        JwkSet $set,
        JwkSelectionContext $context
    ): JwkSelectionResult;
}
```

---

### 834. JwkSelectionResult

```php
final readonly class JwkSelectionResult
{
    public function __construct(
        public JwkSelectionStatus $status,
        public ?JsonWebKey $key,
        public array $candidates,
        public array $warnings,
    ) {
    }
}
```

---

### 835. JwkSelectionStatus

```php
enum JwkSelectionStatus: string
{
    case Selected = 'selected';
    case NotFound = 'not_found';
    case Ambiguous = 'ambiguous';
    case Incompatible = 'incompatible';
    case Revoked = 'revoked';
}
```

---

### 836. Unknown kid handling

Cuando el token incluya un `kid` desconocido, VoltStack podrá:

1. consultar el cache actual;
2. ejecutar un refresh controlado;
3. volver a intentar la selección una sola vez;
4. rechazar el token si la clave continúa ausente.

---

### 837. Refresh amplification protection

Un atacante no deberá poder provocar una descarga de JWKS por cada token con `kid` aleatorio.

---

### 838. Unknown key rate limiting

El refresh deberá limitarse por:

* issuer;
* provider;
* tenant;
* ventana temporal;
* último refresh;
* circuit breaker.

---

### 839. Negative key cache

VoltStack podrá mantener temporalmente un registro de `kid` desconocidos para evitar consultas repetidas.

---

### 840. JwksCache

```php
interface JwksCacheInterface
{
    public function get(string $providerId): ?JwkSet;

    public function store(
        string $providerId,
        JwkSet $set
    ): void;

    public function invalidate(
        string $providerId,
        JwksInvalidationReason $reason
    ): void;
}
```

---

### 841. JWKS cache lifetime

El cache deberá considerar:

* headers HTTP;
* política del proveedor;
* máximo interno;
* mínimo de seguridad;
* historial de rotación;
* criticidad del tenant.

---

### 842. Stale key policy

Una clave cacheada vencida no deberá utilizarse indefinidamente.

---

### 843. Stale-while-revalidate

Podrá permitirse una ventana corta cuando:

* el proveedor esté temporalmente indisponible;
* la clave ya sea conocida;
* el token haya sido emitido antes de la expiración del cache;
* la política lo permita;
* no exista señal de compromiso.

---

### 844. Fail-open prohibition

Un error al descargar JWKS no deberá provocar:

* omitir la firma;
* aceptar cualquier clave;
* aceptar algoritmos alternativos;
* ignorar issuer o audience.

---

### 845. Key rotation architecture

Los proveedores podrán mantener simultáneamente:

* clave activa;
* clave siguiente;
* claves anteriores en periodo de validación;
* claves revocadas.

---

### 846. Key lifecycle states

```php
enum FederatedSigningKeyState: string
{
    case PrePublished = 'pre_published';
    case Active = 'active';
    case Retiring = 'retiring';
    case Retired = 'retired';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
}
```

---

### 847. Pre-published keys

Una clave futura podrá publicarse antes de utilizarse para permitir propagación de cache.

---

### 848. Overlapping rotation window

Durante una rotación ordinaria podrán coexistir claves anteriores y nuevas.

---

### 849. Retiring keys

Una clave retirada podrá seguir siendo necesaria para validar tokens no expirados emitidos previamente.

---

### 850. Key retention calculation

La retención deberá considerar:

* vida máxima del ID Token;
* clock skew;
* retraso de entrega;
* cache intermedio;
* logout tokens;
* tokens emitidos antes del rollover.

---

### 851. KeyRotationTracker

```php
interface KeyRotationTrackerInterface
{
    public function observe(
        string $providerId,
        JwkSet $previous,
        JwkSet $current
    ): KeyRotationAssessment;
}
```

---

### 852. Key rotation assessment

Deberá detectar:

* nuevas claves;
* claves eliminadas;
* cambio de algoritmo;
* cambio de tipo;
* reutilización de `kid`;
* reemplazo de material con mismo `kid`;
* reducción inesperada del conjunto.

---

### 853. Same kid with different key material

El reemplazo de material criptográfico manteniendo el mismo `kid` deberá generar una alerta de alta severidad.

---

### 854. Emergency key rollover

Una rotación de emergencia podrá ocurrir por:

* compromiso;
* exposición;
* vulnerabilidad del algoritmo;
* error operacional;
* revocación de certificado.

---

### 855. Emergency rollover response

VoltStack deberá poder:

* invalidar el cache;
* descargar el conjunto nuevo;
* marcar claves comprometidas;
* rechazar tokens firmados con ellas;
* revocar sesiones derivadas;
* requerir nueva autenticación;
* notificar a tenants.

---

### 856. Key compromise registry

```php
interface CompromisedKeyRegistryInterface
{
    public function markCompromised(
        string $issuer,
        JwkThumbprint $thumbprint,
        KeyCompromiseContext $context
    ): void;

    public function isCompromised(
        string $issuer,
        JwkThumbprint $thumbprint
    ): bool;
}
```

---

### 857. Session provenance

Las sesiones federadas deberán conservar:

* issuer;
* subject;
* provider ID;
* key thumbprint;
* ID Token issuance time;
* authentication time;
* `sid` cuando exista.

---

### 858. Federated session registry

```php
interface FederatedSessionRegistryInterface
{
    public function register(
        FederatedSessionBinding $binding
    ): void;

    public function findByProviderSession(
        string $issuer,
        string $providerSessionId
    ): array;

    public function findBySubject(
        string $issuer,
        string $subject
    ): array;
}
```

---

### 859. FederatedSessionBinding

```php
final readonly class FederatedSessionBinding
{
    public function __construct(
        public SessionIdentifier $localSession,
        public string $providerId,
        public string $issuer,
        public string $subject,
        public ?string $providerSessionId,
        public ?JwkThumbprint $signingKey,
        public DateTimeImmutable $authenticatedAt,
    ) {
    }
}
```

---

### 860. OIDC sid claim

El claim `sid` podrá representar una sesión del usuario en el proveedor.

Deberá tratarse como un identificador opaco dentro del contexto del issuer y cliente.

---

### 861. sid uniqueness

`sid` no deberá asumirse globalmente único.

La clave interna deberá incorporar:

```text
issuer + client + sid
```

---

### 862. Local-to-federated session mapping

Una sesión del proveedor podrá corresponder a:

* una sesión local;
* varias pestañas;
* varias sesiones locales;
* varias aplicaciones del mismo ecosistema.

La política de revocación deberá definir el alcance.

---

### 863. RP-Initiated Logout

VoltStack podrá solicitar al proveedor que finalice la sesión del usuario mediante el endpoint de terminación registrado.

Este perfil permite que un Relying Party solicite explícitamente al OpenID Provider el logout del usuario.

---

### 864. RpInitiatedLogoutRequest

```php
final readonly class RpInitiatedLogoutRequest
{
    public function __construct(
        public string $providerId,
        public ?SensitiveValue $idTokenHint,
        public ?string $logoutHint,
        public ?string $clientId,
        public ?string $postLogoutRedirectUri,
        public string $state,
    ) {
    }
}
```

---

### 865. id_token_hint handling

Cuando se utilice, deberá corresponder a:

* provider esperado;
* client esperado;
* identidad autenticada;
* sesión federada relacionada.

---

### 866. Post-logout redirect URI

La URI deberá estar registrada y validarse mediante coincidencia exacta.

---

### 867. Logout state

El parámetro `state` deberá:

* ser aleatorio;
* vincularse al logout;
* expirar;
* consumirse una sola vez;
* proteger el retorno al RP.

---

### 868. Local-first logout

VoltStack deberá definir si:

* revoca primero la sesión local;
* solicita primero logout al proveedor;
* ejecuta ambas operaciones transaccionalmente cuando sea posible.

Por defecto, la sesión local deberá revocarse incluso si el proveedor no responde.

---

### 869. Front-Channel Logout

El front-channel utiliza el navegador del usuario para transmitir la solicitud de logout entre el proveedor y los Relying Parties.

---

### 870. Front-channel risks

Este mecanismo depende de:

* navegador;
* cookies;
* iframes o navegación;
* políticas de terceros;
* disponibilidad del RP;
* restricciones de tracking.

---

### 871. FrontChannelLogoutRequest

```php
final readonly class FrontChannelLogoutRequest
{
    public function __construct(
        public string $issuer,
        public ?string $providerSessionId,
        public ?string $sessionState,
        public RequestFingerprint $request,
    ) {
    }
}
```

---

### 872. Front-channel validation

VoltStack deberá validar:

* issuer;
* client context;
* `sid` cuando exista;
* origen esperado;
* parámetros permitidos;
* correlación con sesiones federadas.

---

### 873. Front-channel idempotency

El logout deberá ser idempotente.

Una sesión ya revocada deberá producir una respuesta segura sin error sensible.

---

### 874. Third-party cookie limitations

VoltStack no deberá asumir que los navegadores siempre enviarán cookies en contextos cross-site.

---

### 875. Front-channel fallback

Cuando el front-channel no pueda resolver la sesión local, podrá:

* mostrar una página de confirmación;
* requerir navegación top-level;
* depender del back-channel;
* invalidar mediante subject cuando la política lo permita.

---

### 876. Back-Channel Logout

El back-channel utiliza comunicación directa entre el proveedor y el RP, sin depender del navegador.

Después de validar un Logout Token, el RP debe localizar y limpiar las sesiones identificadas por `iss`, `sub` y, opcionalmente, `sid`.

---

### 877. BackChannelLogoutEndpoint

```php
interface BackChannelLogoutEndpointInterface
{
    public function handle(
        BackChannelLogoutRequest $request
    ): BackChannelLogoutResult;
}
```

---

### 878. Logout Token

```php
final readonly class LogoutTokenClaims
{
    public function __construct(
        public string $issuer,
        public array $audiences,
        public DateTimeImmutable $issuedAt,
        public string $jwtId,
        public ?string $subject,
        public ?string $providerSessionId,
        public array $events,
    ) {
    }
}
```

---

### 879. Logout Token validation

El pipeline deberá validar:

* firma;
* issuer;
* audience;
* issued-at;
* JWT ID;
* evento de logout;
* subject o `sid`;
* ausencia de nonce;
* replay;
* algoritmo;
* clave.

El perfil de back-channel exige identificar la sesión mediante `sub`, `sid` o ambos, e incluye un evento específico de logout.

---

### 880. Logout Token replay registry

```php
interface LogoutTokenReplayRegistryInterface
{
    public function consume(
        string $issuer,
        string $jwtId,
        DateTimeImmutable $expiresAt
    ): bool;
}
```

---

### 881. Back-channel logout scope

Cuando exista `sid`, deberán revocarse las sesiones vinculadas a esa sesión del proveedor.

Cuando solo exista `sub`, la política podrá revocar todas las sesiones federadas del sujeto para ese issuer y client.

---

### 882. Back-channel endpoint security

El endpoint deberá:

* aceptar únicamente POST;
* limitar tamaño;
* validar content type;
* aplicar rate limiting;
* no requerir cookie del usuario;
* responder idempotentemente.

---

### 883. Logout delivery failure

Los fallos de logout federado deberán registrarse sin restaurar una sesión local ya revocada.

---

### 884. SAML 2.0 Security Foundations

VoltStack podrá soportar SAML 2.0 como protocolo empresarial de federación.

SAML utiliza assertions XML y define perfiles para Single Sign-On y Single Logout mediante distintos bindings.

---

### 885. SAML actors

```php
enum SamlActorRole: string
{
    case IdentityProvider = 'identity_provider';
    case ServiceProvider = 'service_provider';
    case Principal = 'principal';
}
```

---

### 886. SamlIdentityProviderDefinition

```php
final readonly class SamlIdentityProviderDefinition
{
    public function __construct(
        public string $providerId,
        public string $entityId,
        public array $singleSignOnServices,
        public array $singleLogoutServices,
        public array $signingCertificates,
        public array $encryptionCertificates,
        public SamlProviderStatus $status,
        public ?string $tenantId,
    ) {
    }
}
```

---

### 887. SAML metadata trust

La metadata deberá obtenerse mediante:

* configuración estática;
* archivo firmado;
* endpoint HTTPS aprobado;
* federation registry confiable.

---

### 888. SAML metadata validation

Deberá comprobarse:

* firma cuando se requiera;
* entity ID;
* certificados;
* endpoints;
* bindings;
* expiración;
* cache duration;
* tamaño;
* XML seguro.

---

### 889. XML parser security

El parser deberá deshabilitar:

* entidades externas;
* DTD;
* expansión ilimitada;
* acceso a red;
* referencias externas;
* procesamiento XML innecesario.

---

### 890. SAML assertion validation

La validación deberá incluir:

* firma;
* issuer;
* audience;
* destination;
* recipient;
* subject confirmation;
* `InResponseTo`;
* `NotBefore`;
* `NotOnOrAfter`;
* replay;
* tenant;
* authentication context.

---

### 891. XML Signature Wrapping protection

VoltStack deberá:

* verificar el elemento exacto firmado;
* resolver IDs de manera única;
* impedir referencias duplicadas;
* no confiar en búsquedas XML ambiguas;
* consumir únicamente la assertion validada.

---

### 892. SAML replay registry

```php
interface SamlReplayRegistryInterface
{
    public function consumeAssertion(
        string $issuer,
        string $assertionId,
        DateTimeImmutable $expiresAt
    ): bool;

    public function consumeResponse(
        string $issuer,
        string $responseId,
        DateTimeImmutable $expiresAt
    ): bool;
}
```

---

### 893. SAML account binding

La identidad externa deberá resolverse mediante:

```text
IdP entity ID + persistent NameID
```

o mediante un atributo estable explícitamente gobernado.

---

### 894. Email-based SAML linking prohibition

El email no deberá utilizarse automáticamente como identificador único de federación.

---

### 895. Just-In-Time Provisioning

JIT provisioning permitirá crear o actualizar una identidad local durante una autenticación federada válida.

---

### 896. JitProvisioningService

```php
interface JitProvisioningServiceInterface
{
    public function provision(
        VerifiedFederatedIdentity $identity,
        JitProvisioningPolicy $policy
    ): JitProvisioningResult;
}
```

---

### 897. JIT provisioning policy

La política deberá definir:

* proveedores autorizados;
* tenants permitidos;
* dominios aceptados;
* atributos requeridos;
* roles iniciales;
* grupos mapeables;
* estado inicial;
* necesidad de aprobación.

---

### 898. JIT least privilege

Una identidad creada mediante JIT deberá recibir el mínimo privilegio inicial.

Los grupos o atributos externos no deberán transformarse en privilegios administrativos sin mapping explícito.

---

### 899. SCIM Identity Lifecycle Foundations

SCIM permitirá aprovisionar y administrar recursos de identidad mediante un protocolo HTTP estandarizado.

Sus modelos base contemplan recursos como usuarios y grupos, mientras que el protocolo permite creación, consulta, modificación y administración del ciclo de vida.

VoltStack deberá preparar abstracciones para:

* Users;
* Groups;
* schemas;
* resource types;
* service-provider configuration;
* filtering;
* PATCH;
* bulk operations;
* deprovisioning.

---

### 900. Resultado de esta entrega

Esta entrega establece:

```text
OIDC Cryptographic Key Architecture
JWKS Retrieval and Validation
JWK Structural Validation
Algorithm and Key Compatibility
kid Selection Security
JWK Thumbprints
Unknown Key Handling
JWKS Cache Architecture
Key Rotation Tracking
Emergency Key Rollover
Compromised Key Registry
Federated Session Registry
OIDC sid Claim Handling
RP-Initiated Logout
Front-Channel Logout
Back-Channel Logout
Logout Token Validation
Federated Logout Replay Protection
SAML 2.0 Security Foundations
SAML Metadata Trust
Secure XML Parsing
SAML Assertion Validation
XML Signature Wrapping Protection
SAML Replay Registry
Secure SAML Account Binding
Just-In-Time Provisioning
SCIM Identity Lifecycle Foundations
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 10

- SCIM service provider architecture
- SCIM Users and Groups
- SCIM schemas
- Resource types
- ServiceProviderConfig
- SCIM authentication
- SCIM authorization
- SCIM filtering
- SCIM PATCH semantics
- Bulk operations
- Provisioning correlation
- External IDs
- User activation and deactivation
- Group membership synchronization
- Deprovisioning
- Session revocation after deprovisioning
- Directory synchronization
- Provisioning conflict resolution
- SCIM audit events
```

## Entrega 10

**Documento:** Parte 05
**Entrega:** 10 de varias
**Cobertura:** Secciones **901–1000**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 9`

---

### 901. SCIM Service Provider Architecture

VoltStack podrá actuar como SCIM Service Provider para recibir operaciones de aprovisionamiento desde:

* proveedores de identidad;
* directorios corporativos;
* plataformas IAM;
* sistemas HRIS;
* herramientas de administración empresarial;
* servicios de gestión de ciclo de vida.

El subsistema SCIM deberá mantenerse separado de:

* autenticación interactiva;
* autorización de Controllers;
* administración ordinaria de usuarios;
* sincronización interna del dominio;
* sesiones web.

---

### 902. SCIM security goals

La implementación deberá garantizar:

* autenticación fuerte del cliente SCIM;
* autorización granular;
* aislamiento multi-tenant;
* validación estricta de schemas;
* idempotencia;
* protección contra replay;
* consistencia de recursos;
* desprovisionamiento inmediato;
* auditoría;
* control de operaciones masivas.

---

### 903. SCIM threat model

El modelo deberá considerar:

* clientes SCIM comprometidos;
* bearer tokens robados;
* aprovisionamiento de cuentas privilegiadas;
* escalamiento mediante grupos;
* desactivación maliciosa;
* modificación masiva;
* conflictos de identidad;
* inyección en filtros;
* abuso de PATCH;
* replay;
* enumeración;
* cross-tenant provisioning;
* agotamiento de recursos;
* desincronización de directorios.

---

### 904. SCIM architectural components

```text
SCIM Client
      ↓
SCIM Transport Security
      ↓
Client Authentication
      ↓
Tenant Resolution
      ↓
SCIM Authorization
      ↓
Request Validation
      ↓
Schema Resolution
      ↓
Resource Mapping
      ↓
Provisioning Command
      ↓
Identity Lifecycle Engine
      ↓
Persistence
      ↓
Security Side Effects
      ↓
Audit Response
```

---

### 905. ScimServiceProvider

```php
interface ScimServiceProviderInterface
{
    public function handle(
        ScimRequest $request,
        ScimClientContext $client
    ): ScimResponse;
}
```

---

### 906. ScimRequest

```php
final readonly class ScimRequest
{
    public function __construct(
        public ScimHttpMethod $method,
        public string $resourceType,
        public ?string $resourceId,
        public array $query,
        public array $headers,
        public mixed $body,
        public RequestFingerprint $request,
    ) {
    }
}
```

---

### 907. ScimHttpMethod

```php
enum ScimHttpMethod: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Put = 'PUT';
    case Patch = 'PATCH';
    case Delete = 'DELETE';
}
```

---

### 908. SCIM endpoint structure

VoltStack podrá exponer endpoints como:

```text
/scim/v2/Users
/scim/v2/Groups
/scim/v2/Schemas
/scim/v2/ResourceTypes
/scim/v2/ServiceProviderConfig
/scim/v2/Bulk
```

---

### 909. Endpoint tenant isolation

Cada request deberá resolverse dentro de un tenant explícito.

La resolución podrá basarse en:

* hostname;
* client credential;
* endpoint dedicado;
* metadata del cliente;
* route binding.

---

### 910. Cross-tenant prohibition

Un cliente SCIM no deberá poder seleccionar libremente otro tenant mediante parámetros manipulables.

---

### 911. ScimClient

```php
final readonly class ScimClient
{
    public function __construct(
        public string $clientId,
        public string $displayName,
        public string $tenantId,
        public ScimClientStatus $status,
        public array $allowedResources,
        public array $allowedOperations,
        public array $allowedSchemas,
        public ScimAuthenticationMethod $authenticationMethod,
    ) {
    }
}
```

---

### 912. ScimClientStatus

```php
enum ScimClientStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
    case Retired = 'retired';
}
```

---

### 913. SCIM authentication methods

VoltStack podrá soportar:

* OAuth 2.0 bearer tokens;
* sender-constrained access tokens;
* mTLS;
* private key JWT;
* static bearer tokens para compatibilidad limitada.

---

### 914. Static bearer token restrictions

Los tokens estáticos deberán:

* generarse con alta entropía;
* almacenarse como digest;
* poseer expiración;
* rotarse;
* limitarse a un tenant;
* limitarse a scopes;
* revocarse individualmente.

---

### 915. ScimClientCredential

```php
final readonly class ScimClientCredential
{
    public function __construct(
        public string $credentialId,
        public string $clientId,
        public string $secretDigest,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public ScimCredentialStatus $status,
        public array $scopes,
    ) {
    }
}
```

---

### 916. ScimCredentialStatus

```php
enum ScimCredentialStatus: string
{
    case Active = 'active';
    case Rotating = 'rotating';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
    case Expired = 'expired';
}
```

---

### 917. Credential rotation overlap

Durante una rotación podrá permitirse una ventana corta en la que dos credentials sean válidas.

La ventana deberá:

* ser explícita;
* expirar;
* auditarse;
* no extenderse indefinidamente.

---

### 918. SCIM authorization

La autenticación del cliente no implica autorización completa.

---

### 919. SCIM scopes

Ejemplos:

```text
scim.users.read
scim.users.write
scim.groups.read
scim.groups.write
scim.bulk.execute
scim.schemas.read
```

---

### 920. ScimAuthorizationPolicy

```php
interface ScimAuthorizationPolicyInterface
{
    public function authorize(
        ScimClientContext $client,
        ScimOperation $operation,
        ScimResourceContext $resource
    ): ScimAuthorizationDecision;
}
```

---

### 921. Operation-level authorization

La política deberá distinguir:

* crear;
* consultar;
* actualizar;
* reemplazar;
* desactivar;
* eliminar;
* modificar membresías;
* ejecutar bulk.

---

### 922. Attribute-level authorization

Algunos clientes podrán editar solo atributos específicos.

Ejemplos:

* departamento;
* puesto;
* manager;
* número de empleado;
* estado activo.

---

### 923. Protected attributes

No deberán ser modificables directamente mediante SCIM sin política explícita:

* roles administrativos internos;
* flags de superusuario;
* credenciales;
* MFA;
* passkeys;
* recovery codes;
* security clearance;
* tenant ownership.

---

### 924. ScimAttributePolicy

```php
final readonly class ScimAttributePolicy
{
    public function __construct(
        public array $readableAttributes,
        public array $writableAttributes,
        public array $immutableAttributes,
        public array $sensitiveAttributes,
    ) {
    }
}
```

---

### 925. SCIM schema architecture

VoltStack deberá mantener un registry de schemas soportados.

---

### 926. ScimSchemaRegistry

```php
interface ScimSchemaRegistryInterface
{
    public function resolve(string $schemaUrn): ScimSchemaDefinition;

    public function schemasForResource(
        string $resourceType
    ): array;
}
```

---

### 927. ScimSchemaDefinition

```php
final readonly class ScimSchemaDefinition
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public array $attributes,
        public bool $extension,
    ) {
    }
}
```

---

### 928. ScimAttributeDefinition

```php
final readonly class ScimAttributeDefinition
{
    public function __construct(
        public string $name,
        public ScimAttributeType $type,
        public bool $multiValued,
        public bool $required,
        public ScimMutability $mutability,
        public ScimReturnedBehavior $returned,
        public ScimUniqueness $uniqueness,
        public bool $caseExact,
        public array $subAttributes,
    ) {
    }
}
```

---

### 929. ScimAttributeType

```php
enum ScimAttributeType: string
{
    case String = 'string';
    case Boolean = 'boolean';
    case Decimal = 'decimal';
    case Integer = 'integer';
    case DateTime = 'dateTime';
    case Reference = 'reference';
    case Complex = 'complex';
    case Binary = 'binary';
}
```

---

### 930. ScimMutability

```php
enum ScimMutability: string
{
    case ReadOnly = 'readOnly';
    case ReadWrite = 'readWrite';
    case Immutable = 'immutable';
    case WriteOnly = 'writeOnly';
}
```

---

### 931. ScimReturnedBehavior

```php
enum ScimReturnedBehavior: string
{
    case Always = 'always';
    case Never = 'never';
    case Default = 'default';
    case Request = 'request';
}
```

---

### 932. ScimUniqueness

```php
enum ScimUniqueness: string
{
    case None = 'none';
    case Server = 'server';
    case Global = 'global';
}
```

---

### 933. Schema validation pipeline

```text
Parse JSON
      ↓
Resolve schemas
      ↓
Reject unknown required schema
      ↓
Validate attribute types
      ↓
Validate mutability
      ↓
Validate required attributes
      ↓
Validate uniqueness
      ↓
Validate tenant policy
      ↓
Normalize allowed values
      ↓
Produce Resource Command
```

---

### 934. Unknown attributes

La política podrá:

* rechazarlos;
* ignorarlos;
* almacenarlos en extensión controlada;
* registrar advertencia.

No deberán incorporarse arbitrariamente al modelo interno.

---

### 935. Extension schemas

VoltStack podrá soportar extensiones:

* empresariales;
* específicas de tenant;
* específicas de industria;
* personalizadas por integración.

---

### 936. Extension governance

Toda extensión deberá:

* tener URN única;
* estar versionada;
* definir mutabilidad;
* definir sensibilidad;
* mapearse explícitamente al dominio.

---

### 937. Resource types

VoltStack deberá publicar los tipos de recurso disponibles.

---

### 938. ScimResourceTypeDefinition

```php
final readonly class ScimResourceTypeDefinition
{
    public function __construct(
        public string $id,
        public string $name,
        public string $endpoint,
        public string $coreSchema,
        public array $schemaExtensions,
    ) {
    }
}
```

---

### 939. Core resource types

La primera implementación deberá soportar:

* `User`;
* `Group`.

---

### 940. ServiceProviderConfig

El endpoint deberá declarar capacidades reales del servidor.

---

### 941. ScimServiceProviderConfig

```php
final readonly class ScimServiceProviderConfig
{
    public function __construct(
        public bool $patchSupported,
        public bool $bulkSupported,
        public bool $filterSupported,
        public bool $changePasswordSupported,
        public bool $sortSupported,
        public bool $etagSupported,
        public int $maximumOperations,
        public int $maximumPayloadSize,
    ) {
    }
}
```

---

### 942. Capability honesty

VoltStack no deberá anunciar capacidades que no implemente completamente.

---

### 943. SCIM User resource

```php
final readonly class ScimUserResource
{
    public function __construct(
        public string $id,
        public string $externalId,
        public string $userName,
        public bool $active,
        public ?ScimName $name,
        public array $emails,
        public array $phoneNumbers,
        public array $addresses,
        public array $groups,
        public array $roles,
        public array $entitlements,
        public array $extensions,
        public ScimResourceMetadata $meta,
    ) {
    }
}
```

---

### 944. userName semantics

`userName` deberá:

* ser único dentro del ámbito configurado;
* respetar normalización definida;
* no asumirse necesariamente igual al email;
* tratarse como identificador de login solo si la política lo establece.

---

### 945. externalId

`externalId` representa el identificador del recurso en el sistema del cliente SCIM.

---

### 946. External identifier scope

`externalId` deberá considerarse único dentro de:

```text
tenant + SCIM client + resource type
```

No deberá asumirse único globalmente.

---

### 947. ProvisioningCorrelationKey

```php
final readonly class ProvisioningCorrelationKey
{
    public function __construct(
        public string $tenantId,
        public string $clientId,
        public string $resourceType,
        public string $externalId,
    ) {
    }
}
```

---

### 948. Resource correlation order

La resolución de un usuario podrá considerar:

1. SCIM resource ID;
2. provisioning correlation key;
3. identidad externa previamente vinculada;
4. username según política;
5. email únicamente en flujo de revisión explícito.

---

### 949. Unsafe email correlation

No deberá fusionarse automáticamente una cuenta local por coincidencia de email.

---

### 950. Correlation conflicts

Cuando varias identidades coincidan, la operación deberá:

* detenerse;
* generar conflicto;
* evitar actualización parcial;
* requerir resolución administrativa;
* auditarse.

---

### 951. ProvisioningConflict

```php
final readonly class ProvisioningConflict
{
    public function __construct(
        public string $conflictId,
        public ProvisioningCorrelationKey $key,
        public array $candidateIdentities,
        public ProvisioningConflictReason $reason,
        public DateTimeImmutable $detectedAt,
    ) {
    }
}
```

---

### 952. ProvisioningConflictReason

```php
enum ProvisioningConflictReason: string
{
    case DuplicateExternalId = 'duplicate_external_id';
    case DuplicateUserName = 'duplicate_user_name';
    case MultipleLocalMatches = 'multiple_local_matches';
    case CrossTenantIdentity = 'cross_tenant_identity';
    case ProtectedAccount = 'protected_account';
    case ImmutableAttributeMismatch = 'immutable_attribute_mismatch';
}
```

---

### 953. User creation workflow

```text
Authenticate SCIM Client
      ↓
Authorize Create User
      ↓
Validate Schema
      ↓
Resolve Correlation
      ↓
Check Uniqueness
      ↓
Apply Provisioning Policy
      ↓
Create Identity
      ↓
Create Tenant Membership
      ↓
Apply Safe Attributes
      ↓
Set Initial Active State
      ↓
Emit Audit Event
      ↓
Return SCIM Resource
```

---

### 954. Initial account state

Una cuenta aprovisionada podrá iniciar como:

* active;
* inactive;
* pending;
* invitation-required;
* approval-required.

---

### 955. ProvisionedIdentityState

```php
enum ProvisionedIdentityState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Deprovisioned = 'deprovisioned';
}
```

---

### 956. Credential creation prohibition

SCIM no deberá crear contraseñas, passkeys ni recovery codes directamente salvo un perfil separado y explícitamente autorizado.

---

### 957. Invitation workflow

Una identidad creada mediante SCIM podrá recibir una invitación segura para:

* verificar contacto;
* registrar passkey;
* configurar MFA;
* aceptar términos;
* completar perfil.

---

### 958. User replacement with PUT

`PUT` deberá interpretarse como reemplazo completo del recurso editable.

---

### 959. PUT preservation rules

Los atributos:

* read-only;
* server-managed;
* protected;
* no incluidos por diseño;

no deberán eliminarse accidentalmente.

---

### 960. Lost attribute prevention

La transformación de `PUT` deberá diferenciar:

* atributo omitido;
* atributo vacío;
* atributo nulo;
* atributo no retornado al cliente;
* atributo no editable.

---

### 961. SCIM PATCH architecture

PATCH deberá ejecutarse mediante operaciones estructuradas.

---

### 962. ScimPatchOperation

```php
final readonly class ScimPatchOperation
{
    public function __construct(
        public ScimPatchOperationType $operation,
        public ?ScimPath $path,
        public mixed $value,
    ) {
    }
}
```

---

### 963. ScimPatchOperationType

```php
enum ScimPatchOperationType: string
{
    case Add = 'add';
    case Replace = 'replace';
    case Remove = 'remove';
}
```

---

### 964. PATCH validation

Cada operación deberá validar:

* verbo;
* path;
* schema;
* tipo;
* mutabilidad;
* valor;
* cardinalidad;
* autorización;
* precondiciones.

---

### 965. SCIM path parser

```php
interface ScimPathParserInterface
{
    public function parse(string $path): ScimPath;
}
```

---

### 966. SCIM path safety

El parser deberá impedir:

* acceso a atributos no registrados;
* traversal conceptual;
* filtros no acotados;
* expresiones ambiguas;
* modificación de atributos protegidos.

---

### 967. PATCH atomicity

Todas las operaciones de un PATCH deberán aplicarse atómicamente.

---

### 968. PATCH rollback

Si una operación falla:

* no deberá persistirse ninguna modificación;
* deberá devolverse error consistente;
* deberá registrarse la causa.

---

### 969. Multi-valued attributes

La modificación de colecciones deberá preservar:

* claves internas;
* atributos primary;
* valores únicos;
* orden cuando sea significativo;
* referencias válidas.

---

### 970. Primary attribute enforcement

Solo un elemento podrá marcarse como `primary=true` dentro de una colección aplicable.

---

### 971. SCIM filtering

VoltStack podrá soportar búsqueda mediante filtros SCIM.

---

### 972. ScimFilterParser

```php
interface ScimFilterParserInterface
{
    public function parse(
        string $expression
    ): ScimFilterExpression;
}
```

---

### 973. Filter allowlist

Solo podrán filtrarse atributos declarados como filtrables.

---

### 974. Filter query compilation

Los filtros deberán compilarse mediante expresiones parametrizadas.

Nunca deberán concatenarse directamente en SQL.

---

### 975. Filter complexity limits

La política deberá limitar:

* profundidad;
* número de operadores;
* longitud;
* cantidad de atributos;
* complejidad lógica;
* costo estimado.

---

### 976. Pagination

Las respuestas de colección deberán soportar:

* `startIndex`;
* `count`;
* `totalResults`.

---

### 977. Pagination limits

El servidor deberá imponer un máximo de resultados por página.

---

### 978. Sorting

Si se habilita, solo deberá permitirse sobre atributos autorizados e indexados.

---

### 979. ETag architecture

VoltStack podrá utilizar ETags para control de concurrencia.

---

### 980. Resource version

```php
final readonly class ScimResourceVersion
{
    public function __construct(
        public string $etag,
        public int $version,
        public DateTimeImmutable $lastModified,
    ) {
    }
}
```

---

### 981. Conditional updates

La política podrá exigir `If-Match` para prevenir lost updates.

---

### 982. Group resource

```php
final readonly class ScimGroupResource
{
    public function __construct(
        public string $id,
        public string $externalId,
        public string $displayName,
        public array $members,
        public array $extensions,
        public ScimResourceMetadata $meta,
    ) {
    }
}
```

---

### 983. Group member references

Cada miembro deberá referenciar un recurso válido y permitido.

---

### 984. Group membership synchronization

La sincronización deberá distinguir:

* membresía administrada por SCIM;
* membresía local;
* membresía heredada;
* membresía dinámica;
* membresía protegida.

---

### 985. Membership source tracking

```php
final readonly class GroupMembershipRecord
{
    public function __construct(
        public string $groupId,
        public IdentityIdentifier $identity,
        public GroupMembershipSource $source,
        public ?string $provisioningClientId,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
```

---

### 986. GroupMembershipSource

```php
enum GroupMembershipSource: string
{
    case Local = 'local';
    case Scim = 'scim';
    case Federated = 'federated';
    case Dynamic = 'dynamic';
    case Inherited = 'inherited';
}
```

---

### 987. Membership ownership

Un cliente SCIM solo deberá poder retirar membresías que administra, salvo policy explícita.

---

### 988. Group-to-role mapping

Los grupos SCIM no deberán convertirse automáticamente en roles privilegiados.

---

### 989. Provisioning role mapper

```php
interface ProvisioningRoleMapperInterface
{
    public function map(
        ScimGroupResource $group,
        TenantContext $tenant,
        ScimClientContext $client
    ): ProvisioningRoleMappingResult;
}
```

---

### 990. Bulk operations

VoltStack podrá soportar operaciones masivas controladas.

---

### 991. ScimBulkRequest

```php
final readonly class ScimBulkRequest
{
    public function __construct(
        public array $operations,
        public ?int $failOnErrors,
    ) {
    }
}
```

---

### 992. Bulk limits

La política deberá limitar:

* número de operaciones;
* tamaño total;
* recursos por tipo;
* tiempo de ejecución;
* errores permitidos;
* concurrencia por cliente.

---

### 993. Bulk authorization

Cada operación deberá autorizarse individualmente.

La autorización del endpoint Bulk no implica autorización sobre todos los recursos.

---

### 994. Bulk transaction strategy

El sistema deberá declarar si el bulk es:

* completamente atómico;
* atómico por operación;
* atómico por grupo;
* best effort controlado.

---

### 995. Bulk reference resolution

Las referencias internas entre operaciones deberán:

* resolverse de forma determinista;
* evitar ciclos;
* respetar orden;
* impedir referencias cross-tenant.

---

### 996. User activation and deactivation

El atributo `active` deberá mapearse a una transición controlada del ciclo de vida.

---

### 997. Deactivation semantics

Establecer:

```json
{
  "active": false
}
```

deberá poder provocar:

* bloqueo de nuevos logins;
* revocación de sesiones;
* revocación de persistent tokens;
* suspensión de API credentials;
* eliminación de memberships temporales;
* invalidación de caches de autorización.

---

### 998. Deprovisioning workflow

```text
Validated SCIM Deactivation
      ↓
Resolve Identity and Tenant
      ↓
Mark Membership Inactive
      ↓
Increment Credential or Authorization Version
      ↓
Revoke Sessions
      ↓
Revoke Persistent Tokens
      ↓
Revoke OAuth Grants
      ↓
Disable API Keys
      ↓
Remove Managed Group Memberships
      ↓
Apply Data Retention Policy
      ↓
Emit Security Events
      ↓
Return Updated SCIM Resource
```

---

### 999. SCIM audit events

Eventos recomendados:

* `ScimClientAuthenticated`;
* `ScimAuthenticationFailed`;
* `ScimUserCreated`;
* `ScimUserUpdated`;
* `ScimUserActivated`;
* `ScimUserDeactivated`;
* `ScimUserDeprovisioned`;
* `ScimGroupCreated`;
* `ScimGroupUpdated`;
* `ScimMembershipAdded`;
* `ScimMembershipRemoved`;
* `ScimBulkExecuted`;
* `ScimProvisioningConflictDetected`;
* `ScimProtectedAttributeModificationRejected`;
* `ScimCredentialRotated`;
* `ScimClientRevoked`.

---

### 1000. Resultado de esta entrega

Esta entrega establece:

```text
SCIM Service Provider Architecture
SCIM Client Authentication
Tenant-Isolated Provisioning
SCIM Authorization
Operation-Level Authorization
Attribute-Level Authorization
Protected Attribute Policies
SCIM Schema Registry
Schema Extensions
Resource Types
ServiceProviderConfig
SCIM User Resources
External ID Correlation
Provisioning Conflict Detection
Safe User Creation
PUT Replacement Semantics
SCIM PATCH Architecture
Atomic PATCH Processing
SCIM Filter Parsing
Filter Complexity Limits
Pagination and Sorting
ETag Concurrency Control
SCIM Group Resources
Membership Source Tracking
Safe Group-to-Role Mapping
Bulk Operations
User Activation and Deactivation
Deprovisioning Workflow
Session Revocation After Deprovisioning
SCIM Audit Events
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 11

- Directory synchronization architecture
- Full and incremental synchronization
- Provisioning cursors
- Change tokens
- Sync checkpoints
- Identity reconciliation
- Source-of-truth policies
- Attribute ownership
- Field-level conflict resolution
- Tombstones
- Soft delete and hard delete
- Reprovisioning
- Orphan identity detection
- Stale account detection
- Manager hierarchy synchronization
- Nested group synchronization
- Cyclic group protection
- Sync drift detection
- Provisioning health monitoring
- Disaster recovery for identity provisioning
```

## Entrega 11

**Documento:** Parte 05
**Entrega:** 11 de varias
**Cobertura:** Secciones **1001–1100**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 10`

---

### 1001. Directory Synchronization Architecture

VoltStack deberá soportar sincronización de directorios externos para mantener consistencia entre:

* proveedores de identidad;
* directorios corporativos;
* plataformas HRIS;
* servicios SCIM;
* bases de empleados;
* sistemas IAM;
* estructuras organizacionales;
* identidades locales.

La sincronización deberá considerarse un subsistema de identidad independiente y no una simple importación de registros.

---

### 1002. Synchronization security goals

El subsistema deberá garantizar:

* reconciliación determinista;
* aislamiento por tenant;
* idempotencia;
* trazabilidad;
* control de conflictos;
* prevención de borrados masivos accidentales;
* recuperación ante fallos;
* revocación oportuna;
* consistencia eventual controlada;
* protección de atributos locales.

---

### 1003. Directory synchronization threat model

El modelo deberá considerar:

* fuente externa comprometida;
* cursor manipulado;
* replay de cambios;
* borrados masivos;
* corrupción de jerarquías;
* duplicación de identidades;
* cross-tenant contamination;
* escalamiento mediante grupos;
* modificación de atributos protegidos;
* drift silencioso;
* pérdida de cambios;
* sincronización parcial;
* rollback incompleto;
* inconsistencia entre nodos.

---

### 1004. Synchronization architectural components

```text
External Directory
      ↓
Directory Connector
      ↓
Authentication and Transport Security
      ↓
Change Enumeration
      ↓
Checkpoint Validation
      ↓
Normalization
      ↓
Identity Correlation
      ↓
Attribute Ownership Resolution
      ↓
Conflict Resolution
      ↓
Provisioning Commands
      ↓
Lifecycle Side Effects
      ↓
Checkpoint Commit
      ↓
Audit and Health Metrics
```

---

### 1005. DirectoryConnector

```php
interface DirectoryConnectorInterface
{
    public function capabilities(): DirectoryConnectorCapabilities;

    public function fetchFullSnapshot(
        DirectorySyncContext $context
    ): DirectorySnapshot;

    public function fetchChanges(
        DirectorySyncCursor $cursor,
        DirectorySyncContext $context
    ): DirectoryChangeBatch;
}
```

---

### 1006. DirectoryConnectorCapabilities

```php
final readonly class DirectoryConnectorCapabilities
{
    public function __construct(
        public bool $supportsIncrementalSync,
        public bool $supportsDeletions,
        public bool $supportsGroups,
        public bool $supportsNestedGroups,
        public bool $supportsManagerHierarchy,
        public bool $supportsStableChangeTokens,
        public bool $supportsSnapshotVersioning,
    ) {
    }
}
```

---

### 1007. Directory source definition

```php
final readonly class DirectorySourceDefinition
{
    public function __construct(
        public string $sourceId,
        public string $tenantId,
        public DirectorySourceType $type,
        public DirectorySourceStatus $status,
        public DirectoryTrustLevel $trustLevel,
        public DirectorySyncMode $syncMode,
        public array $enabledResourceTypes,
    ) {
    }
}
```

---

### 1008. DirectorySourceType

```php
enum DirectorySourceType: string
{
    case Scim = 'scim';
    case Ldap = 'ldap';
    case ActiveDirectory = 'active_directory';
    case CloudDirectory = 'cloud_directory';
    case Hris = 'hris';
    case Custom = 'custom';
}
```

---

### 1009. DirectorySourceStatus

```php
enum DirectorySourceStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Suspended = 'suspended';
    case Compromised = 'compromised';
    case Retired = 'retired';
}
```

---

### 1010. DirectoryTrustLevel

```php
enum DirectoryTrustLevel: string
{
    case Informational = 'informational';
    case Authoritative = 'authoritative';
    case SharedAuthority = 'shared_authority';
    case Restricted = 'restricted';
}
```

---

### 1011. Full synchronization

Una sincronización completa deberá enumerar el estado total conocido de una fuente.

---

### 1012. Full sync use cases

Deberá utilizarse para:

* carga inicial;
* recuperación después de pérdida de cursor;
* auditoría de consistencia;
* reconciliación periódica;
* migraciones;
* cambio de connector;
* investigación de drift.

---

### 1013. Full sync safeguards

Una sincronización completa deberá aplicar:

* snapshot ID;
* tenant binding;
* límites de volumen;
* detección de cambios anómalos;
* dry-run opcional;
* confirmación para operaciones destructivas;
* commit por fases.

---

### 1014. Incremental synchronization

La sincronización incremental deberá procesar únicamente cambios desde un checkpoint confiable.

---

### 1015. Incremental change types

```php
enum DirectoryChangeType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Activated = 'activated';
    case Deactivated = 'deactivated';
    case MembershipAdded = 'membership_added';
    case MembershipRemoved = 'membership_removed';
    case Moved = 'moved';
}
```

---

### 1016. DirectoryChange

```php
final readonly class DirectoryChange
{
    public function __construct(
        public string $changeId,
        public DirectoryChangeType $type,
        public string $resourceType,
        public string $externalResourceId,
        public mixed $payload,
        public DateTimeImmutable $occurredAt,
        public ?string $version,
    ) {
    }
}
```

---

### 1017. Change ordering

Cuando la fuente proporcione orden causal, VoltStack deberá preservarlo.

---

### 1018. Out-of-order changes

El sistema deberá detectar cambios:

* anteriores al estado actual;
* duplicados;
* incompatibles con la versión;
* recibidos fuera de secuencia;
* referidos a recursos aún inexistentes.

---

### 1019. DirectorySyncCursor

```php
final readonly class DirectorySyncCursor
{
    public function __construct(
        public string $sourceId,
        public string $tenantId,
        public SensitiveValue $cursorValue,
        public DateTimeImmutable $issuedAt,
        public ?DateTimeImmutable $expiresAt,
        public string $integrityDigest,
    ) {
    }
}
```

---

### 1020. Cursor integrity

Los cursores deberán:

* almacenarse de forma protegida;
* vincularse a source y tenant;
* poseer integrity check;
* no ser manipulables por clientes;
* rotarse cuando corresponda.

---

### 1021. Cursor loss

Si el cursor se pierde o invalida, VoltStack deberá:

* detener incremental sync;
* evitar asumir continuidad;
* ejecutar full reconciliation;
* generar evento;
* reconstruir checkpoint.

---

### 1022. Change tokens

Una fuente podrá emitir change tokens opacos.

VoltStack no deberá interpretar su estructura interna.

---

### 1023. DirectorySyncCheckpoint

```php
final readonly class DirectorySyncCheckpoint
{
    public function __construct(
        public string $checkpointId,
        public string $sourceId,
        public string $tenantId,
        public ?DirectorySyncCursor $cursor,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $completedAt,
        public DirectorySyncCheckpointState $state,
        public int $processedChanges,
        public int $failedChanges,
    ) {
    }
}
```

---

### 1024. DirectorySyncCheckpointState

```php
enum DirectorySyncCheckpointState: string
{
    case Started = 'started';
    case Applying = 'applying';
    case Completed = 'completed';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';
    case Superseded = 'superseded';
}
```

---

### 1025. Checkpoint commit rule

El cursor nuevo no deberá persistirse como definitivo hasta que el batch haya sido aplicado correctamente.

---

### 1026. At-least-once delivery

VoltStack deberá diseñarse para tolerar que un cambio sea recibido más de una vez.

---

### 1027. Change idempotency

```php
interface DirectoryChangeLedgerInterface
{
    public function hasProcessed(
        string $sourceId,
        string $changeId
    ): bool;

    public function markProcessed(
        string $sourceId,
        string $changeId,
        DateTimeImmutable $processedAt
    ): void;
}
```

---

### 1028. Batch processing

```php
final readonly class DirectoryChangeBatch
{
    public function __construct(
        public array $changes,
        public ?DirectorySyncCursor $nextCursor,
        public bool $hasMore,
        public ?string $snapshotVersion,
    ) {
    }
}
```

---

### 1029. Batch atomicity

La estrategia deberá declararse como:

* transacción completa;
* transacción por recurso;
* transacción por subgrupo;
* best effort controlado.

---

### 1030. Partial batch failure

Ante fallo parcial, el sistema deberá evitar avanzar el checkpoint sobre cambios no aplicados.

---

### 1031. Retry policy

```php
final readonly class DirectorySyncRetryPolicy
{
    public function __construct(
        public int $maximumAttempts,
        public int $initialDelayMilliseconds,
        public int $maximumDelayMilliseconds,
        public bool $useExponentialBackoff,
        public bool $useJitter,
    ) {
    }
}
```

---

### 1032. Poison change handling

Un cambio que falla repetidamente deberá:

* aislarse;
* enviarse a dead-letter storage;
* no bloquear indefinidamente todo el directorio;
* generar alerta;
* requerir resolución.

---

### 1033. Identity reconciliation

La reconciliación determinará qué identidad local corresponde a un recurso externo.

---

### 1034. IdentityReconciliationService

```php
interface IdentityReconciliationServiceInterface
{
    public function reconcile(
        ExternalDirectoryResource $resource,
        IdentityReconciliationContext $context
    ): IdentityReconciliationResult;
}
```

---

### 1035. Correlation priorities

La correlación deberá priorizar:

1. vínculo persistente existente;
2. source ID más external ID;
3. identificador estable del proveedor;
4. identidad federada conocida;
5. regla administrativa explícita;
6. revisión manual.

---

### 1036. Email correlation restrictions

El email no deberá ser un criterio automático suficiente para fusionar identidades.

---

### 1037. IdentityReconciliationResult

```php
final readonly class IdentityReconciliationResult
{
    public function __construct(
        public IdentityReconciliationStatus $status,
        public ?IdentityIdentifier $identity,
        public array $conflicts,
        public bool $requiresReview,
    ) {
    }
}
```

---

### 1038. IdentityReconciliationStatus

```php
enum IdentityReconciliationStatus: string
{
    case Matched = 'matched';
    case NewIdentity = 'new_identity';
    case Ambiguous = 'ambiguous';
    case Conflict = 'conflict';
    case Rejected = 'rejected';
}
```

---

### 1039. Source-of-truth policies

VoltStack deberá definir qué sistema es autoridad para cada tipo de dato.

---

### 1040. AttributeAuthority

```php
final readonly class AttributeAuthority
{
    public function __construct(
        public string $attribute,
        public string $sourceId,
        public AttributeAuthorityMode $mode,
        public int $priority,
    ) {
    }
}
```

---

### 1041. AttributeAuthorityMode

```php
enum AttributeAuthorityMode: string
{
    case Authoritative = 'authoritative';
    case Preferred = 'preferred';
    case Shared = 'shared';
    case LocalOnly = 'local_only';
    case ExternalReadOnly = 'external_read_only';
}
```

---

### 1042. Attribute ownership

Cada atributo deberá registrar:

* fuente propietaria;
* último escritor;
* versión;
* fecha de modificación;
* política;
* estado de conflicto.

---

### 1043. AttributeOwnershipRecord

```php
final readonly class AttributeOwnershipRecord
{
    public function __construct(
        public IdentityIdentifier $identity,
        public string $attribute,
        public string $ownerSource,
        public string $lastWriter,
        public int $version,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
```

---

### 1044. Protected local fields

Los siguientes datos no deberán sobrescribirse sin política explícita:

* credenciales;
* MFA;
* passkeys;
* recovery methods;
* security flags;
* local legal holds;
* break-glass status;
* internal risk classification.

---

### 1045. Field-level conflict resolution

```php
interface AttributeConflictResolverInterface
{
    public function resolve(
        AttributeConflict $conflict,
        AttributeConflictPolicy $policy
    ): AttributeConflictResolution;
}
```

---

### 1046. AttributeConflict

```php
final readonly class AttributeConflict
{
    public function __construct(
        public IdentityIdentifier $identity,
        public string $attribute,
        public mixed $localValue,
        public mixed $externalValue,
        public string $externalSource,
        public DateTimeImmutable $detectedAt,
    ) {
    }
}
```

---

### 1047. Conflict resolution strategies

```php
enum AttributeConflictStrategy: string
{
    case ExternalWins = 'external_wins';
    case LocalWins = 'local_wins';
    case HighestPrioritySourceWins = 'highest_priority_source_wins';
    case LatestVersionWins = 'latest_version_wins';
    case Merge = 'merge';
    case ManualReview = 'manual_review';
    case RejectChange = 'reject_change';
}
```

---

### 1048. Latest-write limitations

“Última escritura gana” no deberá usarse para atributos críticos sin versionado y autoridad explícita.

---

### 1049. Merge policies

La combinación deberá ser semántica y específica por tipo de atributo.

Ejemplos:

* teléfonos;
* direcciones;
* aliases;
* grupos;
* entitlements.

---

### 1050. Conflict auditability

Toda resolución automática deberá registrar:

* valores considerados;
* fuentes;
* política aplicada;
* resultado;
* versión resultante.

---

### 1051. Tombstones

Un tombstone representa la desaparición lógica de un recurso externo.

---

### 1052. DirectoryTombstone

```php
final readonly class DirectoryTombstone
{
    public function __construct(
        public string $sourceId,
        public string $externalResourceId,
        public string $resourceType,
        public DateTimeImmutable $deletedAt,
        public string $deletionVersion,
        public TombstoneState $state,
    ) {
    }
}
```

---

### 1053. TombstoneState

```php
enum TombstoneState: string
{
    case Observed = 'observed';
    case Applied = 'applied';
    case Reverted = 'reverted';
    case Expired = 'expired';
}
```

---

### 1054. Tombstone retention

Los tombstones deberán conservarse durante un periodo suficiente para:

* evitar recreación accidental;
* detectar replay;
* soportar reprovisioning;
* reconciliar borrados tardíos;
* auditar.

---

### 1055. Soft delete

El soft delete deberá preservar el registro local y bloquear acceso.

---

### 1056. Hard delete

El hard delete deberá quedar reservado para:

* políticas legales;
* retención concluida;
* anonimización;
* datos no sujetos a conservación;
* aprobación administrativa.

---

### 1057. Delete mapping policy

Una eliminación externa no deberá traducirse automáticamente en hard delete local.

---

### 1058. Deletion safety threshold

VoltStack deberá detectar un volumen de eliminaciones superior al patrón esperado.

---

### 1059. Mass deletion protection

```php
final readonly class MassDeletionProtectionPolicy
{
    public function __construct(
        public int $maximumAbsoluteDeletes,
        public float $maximumPercentage,
        public bool $requireApproval,
        public bool $pauseOnThreshold,
    ) {
    }
}
```

---

### 1060. Destructive sync pause

Al superar el umbral, el sync deberá:

* pausar;
* no avanzar cursor;
* generar alerta crítica;
* producir preview;
* requerir aprobación.

---

### 1061. Reprovisioning

Una identidad previamente desactivada podrá reaparecer en la fuente.

---

### 1062. Reprovisioning policy

La política deberá decidir si:

* reactiva la identidad existente;
* crea nueva membresía;
* conserva historial;
* exige revisión;
* bloquea por tombstone;
* restaura grupos administrados.

---

### 1063. Identity continuity

Cuando el identificador externo estable sea el mismo, deberá preferirse continuidad controlada sobre crear identidades duplicadas.

---

### 1064. Reprovisioning security review

Deberá requerirse revisión adicional si la identidad previa fue marcada como:

* comprometida;
* despedida por causa;
* bloqueada legalmente;
* revocada administrativamente;
* objeto de incidente.

---

### 1065. Orphan identity detection

Una identidad huérfana existe cuando conserva estado local pero ya no está respaldada por una fuente esperada.

---

### 1066. OrphanIdentityDetector

```php
interface OrphanIdentityDetectorInterface
{
    public function detect(
        string $tenantId,
        string $sourceId,
        DirectorySnapshot $snapshot
    ): array;
}
```

---

### 1067. Orphan classifications

```php
enum OrphanIdentityStatus: string
{
    case Suspected = 'suspected';
    case Confirmed = 'confirmed';
    case Exempt = 'exempt';
    case Remediated = 'remediated';
}
```

---

### 1068. Orphan grace period

Una identidad no deberá desactivarse por una única ausencia temporal sin considerar:

* full snapshot completo;
* errores de fuente;
* filtros;
* paginación;
* grace period;
* excepciones.

---

### 1069. Stale account detection

Una cuenta stale podrá identificarse por:

* ausencia prolongada de sync;
* falta de login;
* membership expirada;
* fuente retirada;
* manager inválido;
* status inconsistente.

---

### 1070. StaleAccountAssessment

```php
final readonly class StaleAccountAssessment
{
    public function __construct(
        public IdentityIdentifier $identity,
        public StaleAccountRiskLevel $risk,
        public array $signals,
        public array $recommendedActions,
    ) {
    }
}
```

---

### 1071. Stale account remediation

Las acciones podrán incluir:

* notificar;
* restringir;
* requerir recertificación;
* desactivar;
* revocar sesiones;
* eliminar memberships;
* enviar a revisión.

---

### 1072. Manager hierarchy synchronization

VoltStack podrá sincronizar relaciones de manager-subordinate.

---

### 1073. ManagerRelationship

```php
final readonly class ManagerRelationship
{
    public function __construct(
        public IdentityIdentifier $employee,
        public IdentityIdentifier $manager,
        public string $sourceId,
        public DateTimeImmutable $effectiveAt,
    ) {
    }
}
```

---

### 1074. Manager hierarchy validation

Deberá impedirse:

* auto-referencia;
* ciclos;
* manager inexistente;
* relación cross-tenant;
* manager desactivado según política;
* profundidad no acotada.

---

### 1075. Manager hierarchy security use

Una relación de manager no deberá otorgar automáticamente privilegios sensibles.

---

### 1076. Approval chain derivation

Cuando se use para aprobaciones, la jerarquía deberá:

* congelarse por versión;
* auditarse;
* validar vigencia;
* permitir excepciones;
* detectar conflictos de interés.

---

### 1077. Nested group synchronization

VoltStack podrá sincronizar grupos que contienen otros grupos.

---

### 1078. Group graph model

```php
final readonly class DirectoryGroupEdge
{
    public function __construct(
        public string $parentGroupId,
        public string $childGroupId,
        public string $sourceId,
    ) {
    }
}
```

---

### 1079. Cyclic group protection

El sistema deberá detectar ciclos antes de persistir relaciones.

---

### 1080. Group cycle detection

```php
interface GroupCycleDetectorInterface
{
    public function detect(
        string $parentGroupId,
        string $childGroupId,
        DirectoryGroupGraph $graph
    ): GroupCycleDetectionResult;
}
```

---

### 1081. Nested group depth limit

La política deberá limitar la profundidad de expansión.

---

### 1082. Membership explosion protection

Deberán establecerse límites para:

* miembros efectivos;
* grupos anidados;
* profundidad;
* tiempo de cálculo;
* fan-out;
* recomputación.

---

### 1083. Effective membership

La membresía efectiva deberá distinguir:

* directa;
* heredada;
* dinámica;
* local;
* federada;
* SCIM.

---

### 1084. Authorization cache invalidation

Los cambios en membresía efectiva deberán incrementar la versión de autorización correspondiente.

---

### 1085. Sync drift

Drift es la diferencia no esperada entre el estado externo y el estado local.

---

### 1086. DirectoryDriftDetector

```php
interface DirectoryDriftDetectorInterface
{
    public function compare(
        DirectorySnapshot $external,
        LocalDirectoryProjection $local
    ): DirectoryDriftReport;
}
```

---

### 1087. Drift categories

```php
enum DirectoryDriftCategory: string
{
    case MissingLocalResource = 'missing_local_resource';
    case MissingExternalResource = 'missing_external_resource';
    case AttributeMismatch = 'attribute_mismatch';
    case MembershipMismatch = 'membership_mismatch';
    case StatusMismatch = 'status_mismatch';
    case OwnershipMismatch = 'ownership_mismatch';
    case VersionMismatch = 'version_mismatch';
}
```

---

### 1088. DirectoryDriftReport

```php
final readonly class DirectoryDriftReport
{
    public function __construct(
        public string $sourceId,
        public string $tenantId,
        public DateTimeImmutable $generatedAt,
        public array $differences,
        public DirectoryDriftSeverity $severity,
    ) {
    }
}
```

---

### 1089. Drift remediation modes

```php
enum DriftRemediationMode: string
{
    case ReportOnly = 'report_only';
    case AutoRepairSafe = 'auto_repair_safe';
    case RequireApproval = 'require_approval';
    case PauseSynchronization = 'pause_synchronization';
}
```

---

### 1090. Safe auto-repair

Solo deberán repararse automáticamente diferencias:

* no destructivas;
* deterministas;
* respaldadas por una autoridad clara;
* sin impacto de privilegios;
* auditables.

---

### 1091. Provisioning health monitoring

VoltStack deberá medir la salud operacional del subsistema.

---

### 1092. DirectorySyncHealthMetrics

Métricas recomendadas:

* tiempo desde último sync exitoso;
* duración de sync;
* changes procesados;
* failures;
* retries;
* poison changes;
* conflictos;
* drift;
* cuentas desactivadas;
* membresías modificadas;
* cursor age;
* API latency;
* rate-limit responses.

---

### 1093. DirectorySyncHealthStatus

```php
enum DirectorySyncHealthStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Delayed = 'delayed';
    case Failed = 'failed';
    case Suspended = 'suspended';
}
```

---

### 1094. Health threshold policies

Cada tenant podrá definir tolerancias según:

* criticidad;
* frecuencia esperada;
* volumen;
* impacto de desprovisionamiento;
* requisitos regulatorios.

---

### 1095. Provisioning alerts

VoltStack deberá alertar ante:

* sync retrasado;
* cursor inválido;
* volumen anómalo;
* borrado masivo;
* conflicto creciente;
* fuente no autenticable;
* drift crítico;
* fallos repetidos.

---

### 1096. Disaster recovery foundations

La recuperación deberá contemplar:

* checkpoints;
* cursores;
* change ledger;
* mappings externos;
* tombstones;
* attribute ownership;
* group graph;
* audit logs.

---

### 1097. DirectorySyncRecoveryService

```php
interface DirectorySyncRecoveryServiceInterface
{
    public function recover(
        DirectorySourceDefinition $source,
        DirectoryRecoveryPoint $point,
        DirectoryRecoveryPolicy $policy
    ): DirectoryRecoveryResult;
}
```

---

### 1098. Recovery strategies

VoltStack podrá ejecutar:

* replay desde checkpoint;
* reconstrucción desde full snapshot;
* rollback lógico;
* rehidratación de mappings;
* reparación de drift;
* reconstrucción de membresías.

---

### 1099. Directory synchronization audit events

Eventos recomendados:

* `DirectorySyncStarted`;
* `DirectorySyncCompleted`;
* `DirectorySyncFailed`;
* `DirectoryCursorInvalidated`;
* `DirectoryCheckpointCommitted`;
* `DirectoryChangeRejected`;
* `DirectoryPoisonChangeDetected`;
* `IdentityReconciliationConflict`;
* `AttributeConflictResolved`;
* `DirectoryTombstoneCreated`;
* `MassDeletionThresholdExceeded`;
* `OrphanIdentityDetected`;
* `DirectoryDriftDetected`;
* `DirectoryRecoveryStarted`;
* `DirectoryRecoveryCompleted`.

---

### 1100. Resultado de esta entrega

Esta entrega establece:

```text
Directory Synchronization Architecture
Full and Incremental Synchronization
Directory Connectors
Change Tokens and Cursors
Sync Checkpoints
Idempotent Change Processing
Retry and Poison Change Handling
Identity Reconciliation
Source-of-Truth Policies
Attribute Ownership
Field-Level Conflict Resolution
Tombstones
Soft and Hard Delete Policies
Mass Deletion Protection
Reprovisioning
Orphan Identity Detection
Stale Account Detection
Manager Hierarchy Synchronization
Nested Group Synchronization
Cyclic Group Protection
Membership Explosion Protection
Directory Drift Detection
Safe Drift Remediation
Provisioning Health Monitoring
Provisioning Alerts
Disaster Recovery Foundations
Directory Synchronization Audit Events
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 12

- Identity governance architecture
- Access reviews
- Access recertification
- Entitlement catalog
- Role mining
- Separation of duties
- Toxic access combinations
- Joiner, mover and leaver workflows
- Temporary access
- Just-in-time privileged access
- Access expiration
- Break-glass identities
- Dormant privileged accounts
- Delegated administration
- Approval policies
- Identity lifecycle orchestration
- Governance evidence
- Compliance reporting
```

## Entrega 12

**Documento:** Parte 05
**Entrega:** 12 de varias
**Cobertura:** Secciones **1101–1200**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 11`

---

### 1101. Identity Governance Architecture

VoltStack deberá incorporar un subsistema de Identity Governance and Administration para gobernar:

* identidades;
* memberships;
* roles;
* permisos;
* entitlements;
* accesos temporales;
* cuentas privilegiadas;
* delegaciones;
* revisiones de acceso;
* evidencia de cumplimiento.

Este subsistema deberá operar por encima de autenticación, autorización y aprovisionamiento, sin reemplazarlos.

---

### 1102. Governance security goals

La arquitectura deberá garantizar:

* mínimo privilegio;
* acceso justificable;
* expiración automática;
* separación de funciones;
* trazabilidad;
* recertificación;
* delegación limitada;
* remediación verificable;
* reducción de privilegios acumulados;
* evidencia auditable.

---

### 1103. Governance threat model

El modelo deberá considerar:

* privilege creep;
* acceso huérfano;
* aprobaciones automáticas inseguras;
* conflictos de interés;
* cuentas privilegiadas inactivas;
* accesos temporales que no expiran;
* delegaciones excesivas;
* manipulación de revisiones;
* autoaprobación;
* ocultamiento de toxic combinations;
* acceso residual después de transferencias;
* abuso de break-glass;
* evidencia incompleta.

---

### 1104. Governance architectural components

```text
Identity Sources
      ↓
Entitlement Catalog
      ↓
Role and Policy Model
      ↓
Access Request Engine
      ↓
Approval Workflow
      ↓
Provisioning and Authorization
      ↓
Access Review Engine
      ↓
SoD Analysis
      ↓
Remediation
      ↓
Evidence and Compliance Reporting
```

---

### 1105. IdentityGovernanceService

```php
interface IdentityGovernanceServiceInterface
{
    public function evaluate(
        GovernanceSubject $subject,
        GovernanceContext $context
    ): GovernanceAssessment;

    public function remediate(
        GovernanceRemediationCommand $command
    ): GovernanceRemediationResult;
}
```

---

### 1106. GovernanceSubject

```php
final readonly class GovernanceSubject
{
    public function __construct(
        public IdentityIdentifier $identity,
        public string $tenantId,
        public array $roles,
        public array $entitlements,
        public array $memberships,
        public array $delegations,
    ) {
    }
}
```

---

### 1107. GovernanceAssessment

```php
final readonly class GovernanceAssessment
{
    public function __construct(
        public GovernanceRiskLevel $risk,
        public array $violations,
        public array $recommendations,
        public bool $requiresImmediateAction,
    ) {
    }
}
```

---

### 1108. GovernanceRiskLevel

```php
enum GovernanceRiskLevel: string
{
    case Low = 'low';
    case Moderate = 'moderate';
    case High = 'high';
    case Critical = 'critical';
}
```

---

### 1109. Entitlement catalog

VoltStack deberá mantener un catálogo central de derechos de acceso.

Un entitlement podrá representar:

* permiso;
* rol;
* grupo;
* licencia;
* acceso a aplicación;
* capacidad administrativa;
* acceso a dataset;
* operación privilegiada;
* membership organizacional.

---

### 1110. EntitlementDefinition

```php
final readonly class EntitlementDefinition
{
    public function __construct(
        public string $entitlementId,
        public string $name,
        public string $description,
        public EntitlementType $type,
        public EntitlementRiskLevel $riskLevel,
        public string $resourceId,
        public string $ownerIdentityId,
        public bool $requestable,
        public bool $reviewable,
        public bool $temporaryAllowed,
        public bool $privileged,
        public array $tags,
    ) {
    }
}
```

---

### 1111. EntitlementType

```php
enum EntitlementType: string
{
    case Permission = 'permission';
    case Role = 'role';
    case Group = 'group';
    case ApplicationAccess = 'application_access';
    case License = 'license';
    case DataAccess = 'data_access';
    case AdministrativeCapability = 'administrative_capability';
    case ResourceMembership = 'resource_membership';
}
```

---

### 1112. EntitlementRiskLevel

```php
enum EntitlementRiskLevel: string
{
    case Standard = 'standard';
    case Sensitive = 'sensitive';
    case High = 'high';
    case Privileged = 'privileged';
}
```

---

### 1113. Entitlement ownership

Cada entitlement deberá tener un propietario responsable de:

* descripción;
* clasificación;
* aprobación;
* recertificación;
* revisión de uso;
* remediación;
* retiro.

---

### 1114. Entitlement lifecycle

```php
enum EntitlementLifecycleState: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Deprecated = 'deprecated';
    case Suspended = 'suspended';
    case Retired = 'retired';
}
```

---

### 1115. EntitlementCatalog

```php
interface EntitlementCatalogInterface
{
    public function resolve(
        string $entitlementId
    ): EntitlementDefinition;

    public function search(
        EntitlementSearchCriteria $criteria
    ): array;
}
```

---

### 1116. Requestable entitlements

Un entitlement requestable deberá declarar:

* quién puede solicitarlo;
* quién puede recibirlo;
* duración máxima;
* aprobadores;
* requisitos de training;
* conflictos SoD;
* assurance requerido;
* justificación requerida.

---

### 1117. Entitlement metadata integrity

La metadata crítica deberá:

* versionarse;
* firmarse o protegerse por integridad;
* auditarse;
* requerir aprobación para cambios sensibles.

---

### 1118. Role architecture

Los roles deberán representar agrupaciones gobernadas de acceso.

---

### 1119. Role types

```php
enum GovernanceRoleType: string
{
    case Business = 'business';
    case Technical = 'technical';
    case Application = 'application';
    case Privileged = 'privileged';
    case Dynamic = 'dynamic';
    case Emergency = 'emergency';
}
```

---

### 1120. GovernanceRole

```php
final readonly class GovernanceRole
{
    public function __construct(
        public string $roleId,
        public string $name,
        public GovernanceRoleType $type,
        public array $entitlements,
        public string $ownerIdentityId,
        public RoleRiskProfile $riskProfile,
        public GovernanceRoleState $state,
    ) {
    }
}
```

---

### 1121. GovernanceRoleState

```php
enum GovernanceRoleState: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Frozen = 'frozen';
    case Deprecated = 'deprecated';
    case Retired = 'retired';
}
```

---

### 1122. Role explosion prevention

VoltStack deberá detectar:

* roles duplicados;
* roles excesivamente específicos;
* roles sin miembros;
* roles de un solo usuario;
* roles con combinaciones equivalentes;
* jerarquías innecesarias.

---

### 1123. Role mining

Role mining deberá analizar patrones de acceso existentes para proponer roles.

---

### 1124. RoleMiningService

```php
interface RoleMiningServiceInterface
{
    public function analyze(
        RoleMiningDataset $dataset,
        RoleMiningPolicy $policy
    ): RoleMiningReport;
}
```

---

### 1125. Role mining limitations

Las recomendaciones no deberán aplicarse automáticamente a roles privilegiados.

---

### 1126. RoleMiningReport

```php
final readonly class RoleMiningReport
{
    public function __construct(
        public array $candidateRoles,
        public array $outliers,
        public array $duplicatePatterns,
        public array $riskFindings,
    ) {
    }
}
```

---

### 1127. Access request architecture

VoltStack deberá ofrecer un flujo gobernado para solicitar acceso.

---

### 1128. AccessRequest

```php
final readonly class AccessRequest
{
    public function __construct(
        public string $requestId,
        public IdentityIdentifier $requester,
        public IdentityIdentifier $beneficiary,
        public string $tenantId,
        public array $requestedEntitlements,
        public string $justification,
        public ?DateTimeImmutable $requestedStartAt,
        public ?DateTimeImmutable $requestedEndAt,
        public AccessRequestState $state,
    ) {
    }
}
```

---

### 1129. AccessRequestState

```php
enum AccessRequestState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Evaluating = 'evaluating';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Denied = 'denied';
    case Provisioning = 'provisioning';
    case Fulfilled = 'fulfilled';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Failed = 'failed';
}
```

---

### 1130. Access request validation

El request deberá validar:

* identidad solicitante;
* beneficiario;
* tenant;
* elegibilidad;
* entitlement;
* vigencia;
* justificación;
* conflictos;
* riesgo;
* approvals necesarios.

---

### 1131. Self-request restrictions

Un usuario podrá solicitar acceso para sí mismo, pero no deberá autoaprobarlo cuando el entitlement sea sensible.

---

### 1132. Request-on-behalf-of

Solicitar acceso para otra persona deberá requerir:

* autoridad delegada;
* relación organizacional válida;
* justificación;
* auditoría.

---

### 1133. Access justification

La justificación deberá:

* ser obligatoria para accesos sensibles;
* tener longitud mínima;
* evitar valores genéricos;
* vincularse al propósito;
* conservarse como evidencia.

---

### 1134. Access duration

Todo acceso temporal deberá declarar una expiración.

---

### 1135. Maximum access duration

La duración máxima deberá depender de:

* riesgo;
* tipo de entitlement;
* rol;
* tenant;
* regulación;
* contrato;
* contexto laboral.

---

### 1136. Approval policy architecture

```php
interface AccessApprovalPolicyInterface
{
    public function resolve(
        AccessRequest $request,
        AccessApprovalContext $context
    ): AccessApprovalPlan;
}
```

---

### 1137. AccessApprovalPlan

```php
final readonly class AccessApprovalPlan
{
    public function __construct(
        public array $stages,
        public bool $parallelAllowed,
        public bool $unanimousRequired,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 1138. Approval stages

Una aprobación podrá requerir:

* manager;
* resource owner;
* entitlement owner;
* security;
* compliance;
* data owner;
* application owner;
* tenant administrator.

---

### 1139. ApprovalStage

```php
final readonly class ApprovalStage
{
    public function __construct(
        public string $stageId,
        public ApprovalStageType $type,
        public array $eligibleApprovers,
        public int $requiredApprovals,
        public bool $denyOverrides,
    ) {
    }
}
```

---

### 1140. ApprovalStageType

```php
enum ApprovalStageType: string
{
    case Manager = 'manager';
    case ResourceOwner = 'resource_owner';
    case EntitlementOwner = 'entitlement_owner';
    case Security = 'security';
    case Compliance = 'compliance';
    case DataOwner = 'data_owner';
    case TenantAdministrator = 'tenant_administrator';
}
```

---

### 1141. Self-approval prohibition

El solicitante, beneficiario o actor con conflicto directo no deberá aprobar el request.

---

### 1142. Approval delegation

Una delegación de aprobación deberá ser:

* temporal;
* explícita;
* limitada por tipo de solicitud;
* auditada;
* no transitiva por defecto.

---

### 1143. ApprovalDecision

```php
final readonly class ApprovalDecision
{
    public function __construct(
        public string $decisionId,
        public string $requestId,
        public IdentityIdentifier $approver,
        public ApprovalDecisionType $decision,
        public string $reason,
        public DateTimeImmutable $decidedAt,
        public ?string $delegationId,
    ) {
    }
}
```

---

### 1144. ApprovalDecisionType

```php
enum ApprovalDecisionType: string
{
    case Approved = 'approved';
    case Denied = 'denied';
    case Returned = 'returned';
    case Abstained = 'abstained';
    case Expired = 'expired';
}
```

---

### 1145. Approval timeout

Las aprobaciones pendientes deberán expirar.

---

### 1146. Approval escalation

La policy podrá:

* reasignar;
* escalar;
* solicitar segundo aprobador;
* cancelar;
* denegar por timeout.

---

### 1147. Approval evidence

Toda aprobación deberá conservar:

* actor;
* timestamp;
* decisión;
* razón;
* policy;
* delegación;
* risk assessment;
* versión del request.

---

### 1148. Separation of Duties

SoD deberá impedir combinaciones de acceso que creen riesgo operativo o fraude.

---

### 1149. SoD rule types

```php
enum SeparationOfDutiesRuleType: string
{
    case Static = 'static';
    case Dynamic = 'dynamic';
    case Transactional = 'transactional';
    case Temporal = 'temporal';
    case Contextual = 'contextual';
}
```

---

### 1150. Static SoD

Una regla estática impide poseer simultáneamente determinados accesos.

Ejemplo:

```text
vendor.create + payment.approve
```

---

### 1151. Dynamic SoD

Una regla dinámica podrá permitir ambos accesos, pero no utilizarlos dentro de una misma transacción o proceso.

---

### 1152. Transactional SoD

La separación deberá aplicarse al momento de ejecutar acciones relacionadas.

---

### 1153. Temporal SoD

Una identidad no deberá realizar dos acciones incompatibles dentro de una ventana temporal definida.

---

### 1154. Contextual SoD

La regla podrá depender de:

* tenant;
* monto;
* región;
* recurso;
* unidad organizacional;
* nivel de riesgo;
* tipo de operación.

---

### 1155. SeparationOfDutiesRule

```php
final readonly class SeparationOfDutiesRule
{
    public function __construct(
        public string $ruleId,
        public string $name,
        public SeparationOfDutiesRuleType $type,
        public array $incompatibleEntitlements,
        public GovernanceRiskLevel $severity,
        public bool $compensatingControlAllowed,
    ) {
    }
}
```

---

### 1156. SoD evaluator

```php
interface SeparationOfDutiesEvaluatorInterface
{
    public function evaluate(
        IdentityIdentifier $identity,
        array $proposedEntitlements,
        GovernanceContext $context
    ): SeparationOfDutiesAssessment;
}
```

---

### 1157. Toxic access combinations

Una toxic combination representa una combinación de acceso con riesgo elevado.

---

### 1158. ToxicCombination

```php
final readonly class ToxicCombination
{
    public function __construct(
        public string $combinationId,
        public array $entitlements,
        public string $riskDescription,
        public GovernanceRiskLevel $severity,
        public array $requiredControls,
    ) {
    }
}
```

---

### 1159. Toxic access detection

La detección deberá ejecutarse:

* al solicitar acceso;
* al cambiar roles;
* al sincronizar grupos;
* durante access reviews;
* antes de operaciones críticas;
* periódicamente.

---

### 1160. Compensating controls

Una excepción SoD podrá requerir:

* aprobación ejecutiva;
* monitoreo;
* doble control;
* acceso temporal;
* logging reforzado;
* revisión frecuente;
* limitación de monto;
* session recording.

---

### 1161. SoD exception

```php
final readonly class SeparationOfDutiesException
{
    public function __construct(
        public string $exceptionId,
        public IdentityIdentifier $identity,
        public string $ruleId,
        public string $justification,
        public array $controls,
        public DateTimeImmutable $expiresAt,
        public string $approvedBy,
    ) {
    }
}
```

---

### 1162. SoD exception expiry

Toda excepción deberá expirar y no renovarse automáticamente.

---

### 1163. Access review architecture

VoltStack deberá permitir revisiones periódicas y event-driven.

---

### 1164. AccessReviewCampaign

```php
final readonly class AccessReviewCampaign
{
    public function __construct(
        public string $campaignId,
        public string $name,
        public string $tenantId,
        public AccessReviewScope $scope,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public AccessReviewCampaignState $state,
        public AccessReviewPolicy $policy,
    ) {
    }
}
```

---

### 1165. AccessReviewCampaignState

```php
enum AccessReviewCampaignState: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Remediation = 'remediation';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
```

---

### 1166. AccessReviewScope

La campaña podrá revisar:

* todos los usuarios;
* identidades privilegiadas;
* aplicación;
* tenant;
* departamento;
* grupos;
* roles;
* entitlements sensibles;
* cuentas dormidas;
* excepciones SoD.

---

### 1167. Review item

```php
final readonly class AccessReviewItem
{
    public function __construct(
        public string $itemId,
        public string $campaignId,
        public IdentityIdentifier $identity,
        public string $entitlementId,
        public IdentityIdentifier $reviewer,
        public AccessReviewItemState $state,
        public array $evidence,
    ) {
    }
}
```

---

### 1168. AccessReviewItemState

```php
enum AccessReviewItemState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Revoke = 'revoke';
    case Modify = 'modify';
    case Escalated = 'escalated';
    case Expired = 'expired';
    case Remediated = 'remediated';
}
```

---

### 1169. Reviewer selection

El reviewer podrá ser:

* manager;
* resource owner;
* role owner;
* application owner;
* security;
* compliance;
* data owner;
* independent reviewer.

---

### 1170. Reviewer conflict prevention

No deberá permitirse que una identidad certifique su propio acceso sensible.

---

### 1171. Review evidence

La interfaz deberá mostrar:

* acceso actual;
* origen;
* fecha de asignación;
* último uso;
* frecuencia;
* justificación;
* riesgo;
* SoD conflicts;
* fecha de expiración;
* owner.

---

### 1172. Usage-aware review

El sistema podrá incorporar señales de uso, pero ausencia de uso no deberá ser la única base para revocar automáticamente acceso crítico.

---

### 1173. AccessReviewDecision

```php
final readonly class AccessReviewDecision
{
    public function __construct(
        public string $itemId,
        public AccessReviewDecisionType $decision,
        public string $reason,
        public IdentityIdentifier $reviewer,
        public DateTimeImmutable $decidedAt,
    ) {
    }
}
```

---

### 1174. AccessReviewDecisionType

```php
enum AccessReviewDecisionType: string
{
    case Certify = 'certify';
    case Revoke = 'revoke';
    case Modify = 'modify';
    case Delegate = 'delegate';
    case Escalate = 'escalate';
}
```

---

### 1175. Access recertification

La recertificación deberá exigir una decisión explícita.

La ausencia de respuesta no deberá equivaler automáticamente a aprobación.

---

### 1176. Default-deny review policy

Para accesos privilegiados, la política podrá revocar por falta de certificación.

---

### 1177. Review reminders

El sistema deberá emitir recordatorios antes de la fecha límite.

---

### 1178. Review escalation

Los items no revisados podrán escalar a:

* manager superior;
* owner;
* seguridad;
* compliance;
* tenant administrator.

---

### 1179. Review remediation

Una decisión de revocación deberá generar una acción verificable.

---

### 1180. Remediation tracking

```php
final readonly class GovernanceRemediationTask
{
    public function __construct(
        public string $taskId,
        public string $sourceType,
        public string $sourceId,
        public IdentityIdentifier $identity,
        public array $actions,
        public GovernanceRemediationState $state,
        public DateTimeImmutable $dueAt,
    ) {
    }
}
```

---

### 1181. GovernanceRemediationState

```php
enum GovernanceRemediationState: string
{
    case Pending = 'pending';
    case Applying = 'applying';
    case Completed = 'completed';
    case Failed = 'failed';
    case Escalated = 'escalated';
}
```

---

### 1182. Remediation verification

Una tarea no deberá marcarse como completada hasta confirmar el estado real en el sistema objetivo.

---

### 1183. Joiner workflow

El flujo Joiner deberá gobernar la incorporación de una nueva identidad.

---

### 1184. Joiner workflow stages

```text
Authoritative Identity Created
      ↓
Identity Correlation
      ↓
Tenant Membership
      ↓
Birthright Access Calculation
      ↓
SoD Evaluation
      ↓
Required Approvals
      ↓
Provisioning
      ↓
Authentication Enrollment
      ↓
Security Training
      ↓
Activation
```

---

### 1185. Birthright access

El acceso base deberá derivarse de reglas claras y mínimas.

---

### 1186. BirthrightAccessPolicy

```php
interface BirthrightAccessPolicyInterface
{
    public function calculate(
        JoinerContext $context
    ): BirthrightAccessPlan;
}
```

---

### 1187. Birthright restrictions

El acceso base no deberá incluir privilegios administrativos salvo excepción documentada.

---

### 1188. Mover workflow

El flujo Mover deberá gestionar cambios como:

* puesto;
* manager;
* departamento;
* ubicación;
* tenant;
* función;
* contrato;
* nivel de riesgo.

---

### 1189. Mover access recomputation

Un cambio organizacional deberá recalcular:

* birthright access;
* roles;
* grupos;
* SoD;
* approvals;
* accesos temporales;
* ownerships.

---

### 1190. Add-before-remove risk

Agregar acceso nuevo antes de retirar acceso anterior podrá crear toxic combinations temporales.

La secuencia deberá planificarse según riesgo.

---

### 1191. Mover transition plan

```php
final readonly class MoverTransitionPlan
{
    public function __construct(
        public array $accessToAdd,
        public array $accessToRemove,
        public array $accessToRetain,
        public array $requiredApprovals,
        public array $detectedConflicts,
        public DateTimeImmutable $effectiveAt,
    ) {
    }
}
```

---

### 1192. Leaver workflow

El flujo Leaver deberá retirar acceso de forma oportuna y verificable.

---

### 1193. Leaver trigger types

```php
enum LeaverTriggerType: string
{
    case Scheduled = 'scheduled';
    case Immediate = 'immediate';
    case ContractEnd = 'contract_end';
    case Administrative = 'administrative';
    case SecurityIncident = 'security_incident';
}
```

---

### 1194. Leaver actions

El workflow deberá poder:

* bloquear login;
* revocar sesiones;
* revocar tokens;
* desactivar credenciales;
* retirar roles;
* retirar groups;
* transferir ownership;
* retirar delegaciones;
* cancelar access requests;
* preservar evidencia;
* aplicar retención.

---

### 1195. Immediate termination

Una salida inmediata deberá priorizar:

1. bloquear autenticación;
2. revocar sesiones;
3. revocar credenciales;
4. retirar privilegios;
5. preservar evidencia;
6. transferir ownership.

---

### 1196. Access expiration engine

```php
interface AccessExpirationServiceInterface
{
    public function expireDueAccess(
        DateTimeImmutable $now
    ): AccessExpirationResult;
}
```

---

### 1197. Expiration sources

La expiración podrá provenir de:

* request temporal;
* contrato;
* excepción SoD;
* delegación;
* JIT access;
* access review;
* policy;
* licencia;
* proyecto.

---

### 1198. Just-in-time privileged access

El acceso privilegiado JIT deberá:

* estar desactivado por defecto;
* requerir request;
* tener duración corta;
* exigir MFA fuerte;
* aplicar SoD;
* registrar uso;
* expirar automáticamente;
* revocar sesión privilegiada.

---

### 1199. Break-glass identity governance

Las identidades break-glass deberán:

* existir en número mínimo;
* almacenarse de forma protegida;
* excluirse de uso cotidiano;
* monitorearse continuamente;
* requerir revisión después de uso;
* rotar credenciales;
* emitir alertas inmediatas;
* tener owners explícitos.

---

### 1200. Resultado de esta entrega

Esta entrega establece:

```text
Identity Governance Architecture
Entitlement Catalog
Entitlement Ownership
Governance Roles
Role Mining
Access Request Architecture
Approval Policies
Approval Delegation
Self-Approval Prevention
Separation of Duties
Static, Dynamic and Transactional SoD
Toxic Access Combinations
Compensating Controls
SoD Exceptions
Access Review Campaigns
Access Recertification
Review Evidence
Remediation Tracking
Joiner Workflows
Mover Workflows
Leaver Workflows
Birthright Access
Access Expiration
Just-in-Time Privileged Access
Break-Glass Identity Governance
Governance Audit Foundations
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 13

- Privileged access management
- Privileged identities
- Privileged sessions
- Elevation workflows
- Just-in-time elevation
- Just-enough administration
- Privileged credential vaulting
- Credential checkout
- Session recording
- Command and action restrictions
- Dual control
- Emergency access
- Break-glass execution
- Dormant privileged accounts
- Service accounts
- Machine identities
- Workload identities
- Non-human identity governance
- Secret rotation
- Privileged access analytics
```

## Entrega 13

**Documento:** Parte 05
**Entrega:** 13 de varias
**Cobertura:** Secciones **1201–1300**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 12`

---

### 1201. Privileged Access Management Architecture

VoltStack deberá incorporar un subsistema de Privileged Access Management para gobernar:

* identidades administrativas;
* elevaciones temporales;
* credenciales privilegiadas;
* sesiones administrativas;
* cuentas de servicio;
* identidades de carga de trabajo;
* secretos;
* accesos de emergencia;
* actividades de alto impacto.

El subsistema deberá mantenerse separado de la autorización ordinaria de aplicaciones.

---

### 1202. PAM security goals

La arquitectura deberá garantizar:

* privilegio mínimo;
* elevación temporal;
* acceso justificado;
* separación entre identidad personal y administrativa;
* control de credenciales;
* trazabilidad completa;
* revocación rápida;
* protección de secretos;
* supervisión de sesiones;
* reducción de privilegios permanentes.

---

### 1203. PAM threat model

El modelo deberá considerar:

* robo de credenciales administrativas;
* privilegios permanentes excesivos;
* cuentas compartidas;
* abuso interno;
* elevaciones sin aprobación;
* secretos no rotados;
* cuentas de servicio huérfanas;
* session hijacking;
* bypass de controles;
* desactivación de auditoría;
* abuso de break-glass;
* persistencia después de compromiso;
* identidades de máquina sin owner.

---

### 1204. PAM architectural components

```text
Privileged Access Request
      ↓
Identity and Device Verification
      ↓
Risk and SoD Evaluation
      ↓
Approval and Policy
      ↓
Temporary Entitlement Issuance
      ↓
Privileged Session Broker
      ↓
Command and Action Enforcement
      ↓
Recording and Telemetry
      ↓
Automatic Expiration
      ↓
Review and Evidence
```

---

### 1205. Privileged identity

Una identidad privilegiada es cualquier identidad capaz de:

* alterar seguridad;
* administrar usuarios;
* conceder acceso;
* modificar infraestructura;
* acceder a secretos;
* cambiar configuración crítica;
* ejecutar operaciones irreversibles;
* desactivar controles.

---

### 1206. PrivilegedIdentityProfile

```php
final readonly class PrivilegedIdentityProfile
{
    public function __construct(
        public IdentityIdentifier $identity,
        public string $tenantId,
        public array $privilegedRoles,
        public array $managedResources,
        public PrivilegedIdentityState $state,
        public PrivilegedRiskLevel $riskLevel,
        public ?IdentityIdentifier $owner,
    ) {
    }
}
```

---

### 1207. PrivilegedIdentityState

```php
enum PrivilegedIdentityState: string
{
    case Eligible = 'eligible';
    case Active = 'active';
    case Suspended = 'suspended';
    case Dormant = 'dormant';
    case Revoked = 'revoked';
}
```

---

### 1208. PrivilegedRiskLevel

```php
enum PrivilegedRiskLevel: string
{
    case Standard = 'standard';
    case Elevated = 'elevated';
    case High = 'high';
    case Critical = 'critical';
}
```

---

### 1209. Personal and administrative identity separation

VoltStack deberá permitir separar:

* identidad personal;
* identidad administrativa;
* identidad break-glass;
* identidad de servicio.

Una identidad administrativa no deberá utilizarse para tareas ordinarias.

---

### 1210. Linked privileged identities

```php
final readonly class PrivilegedIdentityLink
{
    public function __construct(
        public IdentityIdentifier $personalIdentity,
        public IdentityIdentifier $privilegedIdentity,
        public string $relationshipType,
        public DateTimeImmutable $linkedAt,
    ) {
    }
}
```

---

### 1211. Standing privilege reduction

Los privilegios permanentes deberán minimizarse.

El estado preferido será:

```text
eligible but inactive
```

---

### 1212. Privileged eligibility

Una identidad eligible puede solicitar activación, pero no posee privilegios efectivos hasta completar el workflow.

---

### 1213. Privileged access request

```php
final readonly class PrivilegedAccessRequest
{
    public function __construct(
        public string $requestId,
        public IdentityIdentifier $requester,
        public string $tenantId,
        public array $requestedEntitlements,
        public array $targetResources,
        public string $justification,
        public DateTimeImmutable $requestedStartAt,
        public DateTimeImmutable $requestedEndAt,
        public PrivilegedAccessRequestState $state,
    ) {
    }
}
```

---

### 1214. PrivilegedAccessRequestState

```php
enum PrivilegedAccessRequestState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Evaluating = 'evaluating';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Denied = 'denied';
    case Failed = 'failed';
}
```

---

### 1215. Privileged request requirements

Una solicitud deberá incluir:

* propósito;
* recursos;
* duración;
* ticket o incidente relacionado;
* nivel de privilegio;
* ventana temporal;
* riesgo;
* comandos o acciones previstas cuando aplique.

---

### 1216. Strong authentication requirement

Toda elevación privilegiada deberá requerir:

* autenticación reciente;
* MFA;
* assurance elevado;
* preferencia por método phishing-resistant;
* verificación de dispositivo cuando aplique.

---

### 1217. Privileged device policy

El acceso podrá limitarse a:

* dispositivos administrados;
* posture compatible;
* cifrado habilitado;
* agente de seguridad activo;
* red autorizada;
* navegador o cliente aprobado.

---

### 1218. PrivilegedAccessPolicy

```php
interface PrivilegedAccessPolicyInterface
{
    public function evaluate(
        PrivilegedAccessRequest $request,
        PrivilegedAccessContext $context
    ): PrivilegedAccessDecision;
}
```

---

### 1219. PrivilegedAccessDecision

```php
final readonly class PrivilegedAccessDecision
{
    public function __construct(
        public bool $allowed,
        public array $requiredApprovals,
        public array $requiredControls,
        public ?DateInterval $maximumDuration,
        public array $restrictions,
        public array $denialReasons,
    ) {
    }
}
```

---

### 1220. Just-in-time elevation

La elevación JIT deberá activar privilegios solo durante una ventana corta y explícita.

---

### 1221. Privilege elevation grant

```php
final readonly class PrivilegeElevationGrant
{
    public function __construct(
        public string $grantId,
        public IdentityIdentifier $identity,
        public string $tenantId,
        public array $entitlements,
        public array $resources,
        public DateTimeImmutable $activatedAt,
        public DateTimeImmutable $expiresAt,
        public PrivilegeElevationGrantState $state,
        public string $requestId,
    ) {
    }
}
```

---

### 1222. PrivilegeElevationGrantState

```php
enum PrivilegeElevationGrantState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
```

---

### 1223. Automatic expiration

Toda elevación deberá expirar automáticamente sin depender de logout o acción manual.

---

### 1224. Maximum elevation duration

La duración máxima deberá depender de:

* criticidad;
* recurso;
* entitlement;
* tipo de operación;
* riesgo;
* horario;
* tenant;
* regulación.

---

### 1225. Renewal restrictions

Una elevación no deberá renovarse silenciosamente.

Toda extensión deberá volver a evaluar:

* necesidad;
* riesgo;
* aprobación;
* duración;
* conflictos.

---

### 1226. Just-enough administration

JEA deberá otorgar únicamente las acciones necesarias para completar una tarea.

---

### 1227. Action-level privilege model

```php
final readonly class PrivilegedActionDefinition
{
    public function __construct(
        public string $actionId,
        public string $resourceType,
        public string $operation,
        public array $constraints,
        public PrivilegedRiskLevel $risk,
    ) {
    }
}
```

---

### 1228. Resource-scoped elevation

Un grant deberá limitarse a recursos concretos.

Ejemplos:

* servidor específico;
* base de datos;
* tenant;
* cluster;
* secret path;
* aplicación;
* conjunto de usuarios.

---

### 1229. Command-scoped elevation

Cuando sea técnicamente posible, VoltStack deberá restringir:

* comandos;
* argumentos;
* rutas;
* operaciones;
* APIs;
* verbos administrativos.

---

### 1230. Privileged command policy

```php
interface PrivilegedCommandPolicyInterface
{
    public function authorize(
        PrivilegeElevationGrant $grant,
        PrivilegedCommand $command,
        PrivilegedSessionContext $context
    ): PrivilegedCommandDecision;
}
```

---

### 1231. PrivilegedCommand

```php
final readonly class PrivilegedCommand
{
    public function __construct(
        public string $command,
        public array $arguments,
        public string $targetResource,
        public DateTimeImmutable $requestedAt,
    ) {
    }
}
```

---

### 1232. Dangerous command restrictions

Operaciones destructivas podrán requerir:

* aprobación adicional;
* dual control;
* confirmación explícita;
* ventana de mantenimiento;
* backup verificado;
* ticket válido.

---

### 1233. Dual control

Dual control exige participación de al menos dos actores independientes.

---

### 1234. DualControlPolicy

```php
final readonly class DualControlPolicy
{
    public function __construct(
        public int $requiredActors,
        public array $eligibleActorRoles,
        public bool $sameSessionProhibited,
        public bool $sameManagerChainProhibited,
    ) {
    }
}
```

---

### 1235. Four-eyes principle

Para operaciones críticas deberá impedirse que una sola persona:

* solicite;
* apruebe;
* ejecute;
* certifique;

la misma acción completa.

---

### 1236. Privileged session architecture

Una sesión privilegiada deberá ser distinta de la sesión ordinaria.

---

### 1237. PrivilegedSession

```php
final readonly class PrivilegedSession
{
    public function __construct(
        public string $privilegedSessionId,
        public SessionIdentifier $parentSession,
        public string $grantId,
        public IdentityIdentifier $identity,
        public array $resources,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $expiresAt,
        public PrivilegedSessionState $state,
    ) {
    }
}
```

---

### 1238. PrivilegedSessionState

```php
enum PrivilegedSessionState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Restricted = 'restricted';
    case Suspended = 'suspended';
    case Terminated = 'terminated';
    case Expired = 'expired';
}
```

---

### 1239. Session isolation

Una sesión privilegiada deberá:

* usar identificador separado;
* tener timeout menor;
* no heredar privilegios indefinidamente;
* exigir step-up;
* registrar provenance;
* poder revocarse de forma independiente.

---

### 1240. Privileged session broker

```php
interface PrivilegedSessionBrokerInterface
{
    public function open(
        PrivilegeElevationGrant $grant,
        PrivilegedSessionOpenContext $context
    ): PrivilegedSession;

    public function terminate(
        string $privilegedSessionId,
        PrivilegedSessionTerminationReason $reason
    ): void;
}
```

---

### 1241. Session proxying

VoltStack podrá intermediar acceso hacia:

* SSH;
* RDP;
* bases de datos;
* Kubernetes;
* paneles administrativos;
* APIs;
* consolas cloud.

---

### 1242. Direct credential exposure reduction

Siempre que sea posible, el usuario no deberá recibir directamente la credencial privilegiada.

---

### 1243. Session recording

Las sesiones de alto riesgo podrán registrar:

* comandos;
* acciones;
* terminal;
* requests;
* respuestas;
* cambios de configuración;
* timestamps;
* recursos afectados.

---

### 1244. PrivilegedSessionRecorder

```php
interface PrivilegedSessionRecorderInterface
{
    public function begin(
        PrivilegedSession $session
    ): SessionRecordingHandle;

    public function record(
        SessionRecordingHandle $handle,
        PrivilegedActivity $activity
    ): void;

    public function complete(
        SessionRecordingHandle $handle
    ): SessionRecordingManifest;
}
```

---

### 1245. Recording integrity

Las grabaciones deberán:

* ser append-only;
* incluir integrity hashes;
* poseer timestamp confiable;
* cifrarse;
* controlar acceso;
* aplicar retención.

---

### 1246. Recording privacy

La grabación deberá minimizar exposición de:

* secretos;
* datos personales;
* tokens;
* información regulada;
* contenido no relacionado.

---

### 1247. Sensitive output redaction

VoltStack deberá redactar valores sensibles cuando sea posible sin destruir la evidencia operacional.

---

### 1248. Live session monitoring

Sesiones críticas podrán ser supervisadas en tiempo real por seguridad o un aprobador autorizado.

---

### 1249. Session intervention

El supervisor autorizado podrá:

* advertir;
* pausar;
* restringir;
* terminar;
* elevar incidente.

---

### 1250. Privileged credential vault

VoltStack deberá abstraer almacenamiento seguro de credenciales privilegiadas.

---

### 1251. PrivilegedCredentialVault

```php
interface PrivilegedCredentialVaultInterface
{
    public function store(
        PrivilegedCredential $credential
    ): CredentialVaultReference;

    public function checkout(
        CredentialVaultReference $reference,
        CredentialCheckoutContext $context
    ): CredentialCheckoutLease;

    public function rotate(
        CredentialVaultReference $reference,
        CredentialRotationContext $context
    ): CredentialRotationResult;
}
```

---

### 1252. Vault storage requirements

El vault deberá utilizar:

* encryption at rest;
* KMS o HSM cuando corresponda;
* access control;
* audit logging;
* key rotation;
* tamper detection;
* separación de duties.

---

### 1253. PrivilegedCredential

```php
final readonly class PrivilegedCredential
{
    public function __construct(
        public string $credentialId,
        public PrivilegedCredentialType $type,
        public string $resourceId,
        public SensitiveValue $secret,
        public PrivilegedCredentialState $state,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 1254. PrivilegedCredentialType

```php
enum PrivilegedCredentialType: string
{
    case Password = 'password';
    case ApiKey = 'api_key';
    case SshKey = 'ssh_key';
    case Certificate = 'certificate';
    case DatabaseCredential = 'database_credential';
    case CloudCredential = 'cloud_credential';
    case Token = 'token';
}
```

---

### 1255. PrivilegedCredentialState

```php
enum PrivilegedCredentialState: string
{
    case Active = 'active';
    case CheckedOut = 'checked_out';
    case Rotating = 'rotating';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
}
```

---

### 1256. Credential checkout

El checkout deberá:

* requerir grant activo;
* limitar duración;
* vincularse a identidad;
* vincularse a recurso;
* registrarse;
* impedir reutilización no autorizada.

---

### 1257. CredentialCheckoutLease

```php
final readonly class CredentialCheckoutLease
{
    public function __construct(
        public string $leaseId,
        public CredentialVaultReference $reference,
        public IdentityIdentifier $holder,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public CredentialCheckoutLeaseState $state,
    ) {
    }
}
```

---

### 1258. CredentialCheckoutLeaseState

```php
enum CredentialCheckoutLeaseState: string
{
    case Active = 'active';
    case Returned = 'returned';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
```

---

### 1259. Post-checkout rotation

Las credenciales compartidas deberán rotarse después de:

* checkout;
* expiración;
* incidente;
* cambio de owner;
* uso break-glass;
* sospecha de exposición.

---

### 1260. Secret exposure minimization

El sistema deberá preferir:

* injection temporal;
* brokered sessions;
* short-lived credentials;
* workload identity federation;
* dynamic database credentials;

sobre revelar secretos estáticos.

---

### 1261. Emergency access

El acceso de emergencia deberá estar disponible para incidentes donde el flujo normal sea insuficiente.

---

### 1262. EmergencyAccessRequest

```php
final readonly class EmergencyAccessRequest
{
    public function __construct(
        public string $requestId,
        public IdentityIdentifier $requester,
        public string $incidentId,
        public array $resources,
        public string $reason,
        public DateTimeImmutable $requestedAt,
    ) {
    }
}
```

---

### 1263. Emergency access controls

El flujo deberá:

* exigir razón;
* registrar incidente;
* limitar duración;
* generar alerta inmediata;
* aplicar máxima observabilidad;
* ejecutar revisión posterior obligatoria.

---

### 1264. Break-glass execution

El uso de una identidad break-glass deberá considerarse un evento de seguridad crítico.

---

### 1265. BreakGlassExecutionRecord

```php
final readonly class BreakGlassExecutionRecord
{
    public function __construct(
        public string $executionId,
        public IdentityIdentifier $breakGlassIdentity,
        public IdentityIdentifier $operator,
        public string $incidentId,
        public array $resources,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $endedAt,
    ) {
    }
}
```

---

### 1266. Break-glass post-use actions

Después del uso deberá:

* revocarse la sesión;
* rotarse la credencial;
* preservar evidencia;
* revisar acciones;
* validar recursos;
* cerrar o actualizar incidente;
* recertificar la identidad.

---

### 1267. Dormant privileged accounts

Una cuenta privilegiada dormida representa alto riesgo aunque no tenga uso reciente.

---

### 1268. DormantPrivilegedAccountPolicy

```php
final readonly class DormantPrivilegedAccountPolicy
{
    public function __construct(
        public DateInterval $inactivityThreshold,
        public bool $suspendAutomatically,
        public bool $requireRecertification,
        public bool $revokeStandingPrivileges,
    ) {
    }
}
```

---

### 1269. Dormant account detection

Deberá considerar:

* último login;
* última elevación;
* última acción;
* owner vigente;
* fuente de aprovisionamiento;
* estado laboral;
* recursos asignados.

---

### 1270. Dormant account remediation

Las acciones podrán incluir:

* suspender;
* retirar roles;
* revocar credentials;
* exigir revisión;
* eliminar account binding;
* marcar para investigación.

---

### 1271. Service accounts

Una service account es una identidad no humana utilizada por aplicaciones o procesos.

---

### 1272. ServiceAccountProfile

```php
final readonly class ServiceAccountProfile
{
    public function __construct(
        public IdentityIdentifier $identity,
        public string $tenantId,
        public string $purpose,
        public IdentityIdentifier $owner,
        public array $allowedWorkloads,
        public array $entitlements,
        public ServiceAccountState $state,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 1273. ServiceAccountState

```php
enum ServiceAccountState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Orphaned = 'orphaned';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
```

---

### 1274. Service account requirements

Toda service account deberá tener:

* propósito;
* owner;
* tenant;
* recursos autorizados;
* método de autenticación;
* fecha de revisión;
* rotación;
* expiración cuando sea viable.

---

### 1275. Shared service account restrictions

Las service accounts no deberán utilizarse como cuentas humanas compartidas.

---

### 1276. Interactive login prohibition

Por defecto, una service account no deberá permitir login interactivo.

---

### 1277. Service account owner lifecycle

Si el owner deja la organización o cambia de función, deberá iniciarse:

* reasignación;
* revisión;
* suspensión;
* revocación si no existe nuevo owner.

---

### 1278. Orphan service accounts

Una service account sin owner válido deberá marcarse como huérfana.

---

### 1279. Machine identities

VoltStack deberá tratar como identidades de máquina a:

* servidores;
* dispositivos;
* agentes;
* runners;
* nodos;
* appliances;
* workloads.

---

### 1280. MachineIdentityProfile

```php
final readonly class MachineIdentityProfile
{
    public function __construct(
        public string $machineIdentityId,
        public string $tenantId,
        public string $machineType,
        public array $attestationClaims,
        public array $entitlements,
        public MachineIdentityState $state,
    ) {
    }
}
```

---

### 1281. MachineIdentityState

```php
enum MachineIdentityState: string
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Quarantined = 'quarantined';
    case Compromised = 'compromised';
    case Retired = 'retired';
}
```

---

### 1282. Workload identity

Una workload identity deberá representar una carga de trabajo concreta, no una infraestructura compartida genérica.

---

### 1283. WorkloadIdentityProfile

```php
final readonly class WorkloadIdentityProfile
{
    public function __construct(
        public string $workloadIdentityId,
        public string $tenantId,
        public string $platform,
        public string $namespace,
        public string $workloadName,
        public array $claims,
        public array $allowedAudiences,
        public WorkloadIdentityState $state,
    ) {
    }
}
```

---

### 1284. WorkloadIdentityState

```php
enum WorkloadIdentityState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Compromised = 'compromised';
    case Retired = 'retired';
}
```

---

### 1285. Workload identity federation

VoltStack deberá preferir federación de identidad sobre secretos estáticos cuando la plataforma lo permita.

---

### 1286. Workload assertion validation

Las assertions deberán validar:

* issuer;
* audience;
* subject;
* platform;
* namespace;
* workload;
* expiration;
* signature;
* attestation;
* replay.

---

### 1287. Short-lived workload credentials

Las credenciales emitidas a workloads deberán tener vida corta y scope mínimo.

---

### 1288. Non-human identity governance

Toda identidad no humana deberá someterse a:

* owner;
* propósito;
* revisión;
* expiración;
* inventario;
* entitlement mapping;
* rotación;
* detección de uso anómalo.

---

### 1289. NonHumanIdentityRecord

```php
final readonly class NonHumanIdentityRecord
{
    public function __construct(
        public string $identityId,
        public NonHumanIdentityType $type,
        public string $tenantId,
        public IdentityIdentifier $owner,
        public string $purpose,
        public array $entitlements,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 1290. NonHumanIdentityType

```php
enum NonHumanIdentityType: string
{
    case ServiceAccount = 'service_account';
    case Machine = 'machine';
    case Workload = 'workload';
    case Integration = 'integration';
    case Bot = 'bot';
    case Automation = 'automation';
}
```

---

### 1291. Secret rotation architecture

```php
interface SecretRotationServiceInterface
{
    public function rotate(
        SecretReference $secret,
        SecretRotationPolicy $policy,
        SecretRotationContext $context
    ): SecretRotationResult;
}
```

---

### 1292. Rotation triggers

La rotación deberá activarse por:

* calendario;
* checkout;
* despliegue;
* cambio de owner;
* incidente;
* sospecha de exposición;
* cambio de algoritmo;
* cumplimiento;
* uso break-glass.

---

### 1293. Zero-downtime rotation

Cuando sea posible, la rotación deberá soportar:

1. crear nueva credencial;
2. distribuirla;
3. validar adopción;
4. retirar la anterior;
5. verificar ausencia de uso;
6. revocar definitivamente.

---

### 1294. Secret versioning

```php
final readonly class SecretVersion
{
    public function __construct(
        public string $secretId,
        public string $versionId,
        public SecretVersionState $state,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $retiredAt,
    ) {
    }
}
```

---

### 1295. SecretVersionState

```php
enum SecretVersionState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Retiring = 'retiring';
    case Retired = 'retired';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
}
```

---

### 1296. Privileged access analytics

VoltStack deberá analizar:

* frecuencia de elevaciones;
* duración;
* recursos;
* comandos;
* horario;
* fallos;
* denegaciones;
* anomalías;
* uso de break-glass;
* secretos consultados;
* cuentas inactivas.

---

### 1297. PrivilegedAccessAnalyticsEngine

```php
interface PrivilegedAccessAnalyticsEngineInterface
{
    public function analyze(
        PrivilegedActivityDataset $dataset,
        PrivilegedAnalyticsPolicy $policy
    ): PrivilegedAccessAnalyticsReport;
}
```

---

### 1298. Privileged anomaly signals

Señales recomendadas:

* elevación fuera de horario;
* duración inusual;
* recurso no habitual;
* volumen elevado;
* comandos destructivos;
* múltiples denegaciones;
* credential checkout atípico;
* movimiento lateral;
* uso desde dispositivo nuevo;
* uso simultáneo.

---

### 1299. PAM audit events

Eventos recomendados:

* `PrivilegedAccessRequested`;
* `PrivilegedAccessApproved`;
* `PrivilegedAccessDenied`;
* `PrivilegeElevationActivated`;
* `PrivilegeElevationExpired`;
* `PrivilegeElevationRevoked`;
* `PrivilegedSessionOpened`;
* `PrivilegedSessionTerminated`;
* `PrivilegedCommandDenied`;
* `CredentialCheckedOut`;
* `CredentialReturned`;
* `PrivilegedCredentialRotated`;
* `BreakGlassAccessUsed`;
* `DormantPrivilegedAccountDetected`;
* `ServiceAccountOrphaned`;
* `WorkloadIdentityCompromised`;
* `SecretRotationFailed`;
* `PrivilegedAnomalyDetected`.

---

### 1300. Resultado de esta entrega

Esta entrega establece:

```text
Privileged Access Management Architecture
Privileged Identity Separation
Standing Privilege Reduction
Privileged Eligibility
Privileged Access Requests
Strong Authentication for Elevation
Device-Aware Privileged Access
Just-in-Time Elevation
Just-Enough Administration
Resource-Scoped Privileges
Command-Level Restrictions
Dual Control
Four-Eyes Principle
Privileged Session Isolation
Privileged Session Broker
Session Recording
Live Session Monitoring
Privileged Credential Vault
Credential Checkout
Post-Checkout Rotation
Emergency Access
Break-Glass Execution
Dormant Privileged Accounts
Service Account Governance
Machine Identities
Workload Identities
Workload Identity Federation
Non-Human Identity Governance
Secret Rotation
Privileged Access Analytics
PAM Audit Events
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 14

- Identity threat detection and response
- Authentication anomaly correlation
- Session risk scoring
- Impossible travel
- Token replay detection
- Credential compromise detection
- Password spray correlation
- MFA fatigue detection
- Device compromise signals
- Identity behavior analytics
- User and entity behavior analytics
- Risk-based access decisions
- Continuous access evaluation
- Session restriction and quarantine
- Automated identity containment
- Incident response playbooks
- Identity evidence preservation
- Security operations integration
- SIEM and SOAR integration
- Identity security metrics
```

## Entrega 14

**Documento:** Parte 05
**Entrega:** 14 de varias
**Cobertura:** Secciones **1301–1400**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 13`

---

### 1301. Identity Threat Detection and Response (ITDR) Architecture

VoltStack deberá incorporar un subsistema de **Identity Threat Detection and Response (ITDR)** encargado de detectar, correlacionar y responder a amenazas relacionadas con identidades humanas y no humanas.

El objetivo será reducir el tiempo entre:

* detección;
* evaluación;
* contención;
* remediación;
* recuperación.

---

### 1302. ITDR security goals

La arquitectura deberá garantizar:

* detección temprana;
* correlación de señales;
* reducción de falsos positivos;
* respuesta proporcional al riesgo;
* preservación de evidencia;
* automatización controlada;
* integración con operaciones de seguridad;
* reevaluación continua del riesgo.

---

### 1303. ITDR threat model

El modelo deberá contemplar, entre otros:

* robo de credenciales;
* secuestro de sesiones;
* replay de tokens;
* password spraying;
* credential stuffing;
* MFA fatigue;
* phishing resistente e intento de bypass;
* compromiso de dispositivos;
* abuso de cuentas privilegiadas;
* movimiento lateral;
* uso anómalo de workloads;
* abuso de cuentas de servicio;
* escalamiento silencioso de privilegios.

---

### 1304. ITDR architecture

```text
Identity Signals
        ↓
Signal Collection
        ↓
Normalization
        ↓
Correlation Engine
        ↓
Risk Engine
        ↓
Policy Evaluation
        ↓
Containment
        ↓
Response Actions
        ↓
Evidence Preservation
        ↓
SOC / SIEM / SOAR
```

---

### 1305. IdentityThreatDetectionService

```php
interface IdentityThreatDetectionServiceInterface
{
    public function analyze(
        IdentitySecuritySignal $signal
    ): ThreatAssessment;
}
```

---

### 1306. IdentitySecuritySignal

```php
final readonly class IdentitySecuritySignal
{
    public function __construct(
        public string $signalId,
        public IdentityIdentifier $identity,
        public IdentitySignalType $type,
        public DateTimeImmutable $occurredAt,
        public array $attributes,
        public SignalConfidence $confidence,
    ) {
    }
}
```

---

### 1307. IdentitySignalType

```php
enum IdentitySignalType: string
{
    case Authentication;
    case Session;
    case Device;
    case Token;
    case Credential;
    case Authorization;
    case Behavioral;
    case Network;
    case ThreatIntel;
}
```

---

### 1308. Signal confidence

Cada señal deberá indicar un nivel de confianza que refleje la calidad de la evidencia y permita ponderar adecuadamente el riesgo.

---

### 1309. Signal normalization

Todas las señales deberán convertirse a un formato interno común antes de iniciar la correlación.

---

### 1310. ThreatAssessment

```php
final readonly class ThreatAssessment
{
    public function __construct(
        public ThreatSeverity $severity,
        public ThreatConfidence $confidence,
        public array $matchedSignals,
        public array $recommendedResponses,
        public bool $requiresImmediateContainment,
    ) {
    }
}
```

---

### 1311. Authentication anomaly correlation

VoltStack deberá correlacionar:

* múltiples fallos consecutivos;
* cambios bruscos de ubicación;
* nuevos dispositivos;
* horarios inusuales;
* cambios de navegador;
* autenticaciones simultáneas;
* cambios de método MFA.

---

### 1312. Multi-signal correlation

Una única señal de bajo riesgo no deberá provocar automáticamente una respuesta severa.

La decisión deberá basarse en múltiples evidencias consistentes.

---

### 1313. Correlation engine

```php
interface IdentityCorrelationEngineInterface
{
    public function correlate(
        array $signals
    ): IdentityThreatCorrelation;
}
```

---

### 1314. Correlation windows

La correlación podrá utilizar ventanas:

* segundos;
* minutos;
* horas;
* días;

según el tipo de amenaza.

---

### 1315. Risk accumulation

El riesgo podrá acumularse mediante múltiples eventos pequeños en lugar de depender únicamente de un evento crítico.

---

### 1316. Session risk scoring

Cada sesión activa deberá mantener un puntaje dinámico de riesgo.

---

### 1317. SessionRiskScore

```php
final readonly class SessionRiskScore
{
    public function __construct(
        public SessionIdentifier $session,
        public int $score,
        public SessionRiskLevel $level,
        public array $signals,
        public DateTimeImmutable $evaluatedAt,
    ) {
    }
}
```

---

### 1318. Risk contributors

Factores recomendados:

* dispositivo;
* geografía;
* horario;
* assurance;
* privilegios;
* comportamiento;
* reputación IP;
* anomalías recientes;
* inteligencia externa.

---

### 1319. Continuous risk recomputation

El riesgo no deberá calcularse únicamente durante el login.

---

### 1320. Continuous evaluation triggers

Reevaluar cuando ocurra:

* cambio de IP;
* cambio de dispositivo;
* elevación privilegiada;
* modificación de roles;
* nueva inteligencia de amenazas;
* revocación de credenciales;
* señal de compromiso.

---

### 1321. Impossible travel detection

VoltStack podrá detectar desplazamientos físicamente improbables entre autenticaciones.

---

### 1322. ImpossibleTravelAssessment

```php
final readonly class ImpossibleTravelAssessment
{
    public function __construct(
        public bool $detected,
        public float $estimatedVelocityKmPerHour,
        public array $locations,
        public array $supportingSignals,
    ) {
    }
}
```

---

### 1323. Impossible travel limitations

La detección deberá considerar:

* VPN;
* proxies corporativos;
* roaming móvil;
* redes satelitales;
* cambios ASN legítimos.

---

### 1324. Token replay detection

Los tokens reutilizados fuera de su patrón esperado deberán generar alertas.

---

### 1325. Replay registry correlation

La detección deberá apoyarse en:

* jti;
* nonce;
* DPoP;
* sender constraints;
* Session Identifier;
* refresh token family.

---

### 1326. Credential compromise detection

El sistema deberá identificar indicios como:

* uso simultáneo;
* autenticaciones incompatibles;
* password recientemente filtrado;
* cambio inesperado de MFA;
* bypass de controles.

---

### 1327. Password spray detection

VoltStack deberá correlacionar intentos distribuidos contra múltiples usuarios utilizando la misma contraseña o patrón.

---

### 1328. Credential stuffing detection

El sistema deberá detectar autenticaciones derivadas de listas masivas de credenciales comprometidas.

---

### 1329. MFA fatigue detection

Se deberán identificar patrones de:

* múltiples solicitudes consecutivas;
* rechazo repetido;
* aceptación tardía sospechosa;
* solicitudes desde origen inesperado.

---

### 1330. Push approval abuse

Una aprobación MFA no deberá interpretarse automáticamente como ausencia de compromiso.

---

### 1331. Device compromise signals

Señales recomendadas:

* jailbreak;
* root;
* boot inseguro;
* attestation fallida;
* EDR comprometido;
* certificado inválido;
* postura degradada.

---

### 1332. DeviceRiskAssessment

```php
final readonly class DeviceRiskAssessment
{
    public function __construct(
        public DeviceIdentifier $device,
        public DeviceRiskLevel $risk,
        public array $signals,
        public bool $trusted,
    ) {
    }
}
```

---

### 1333. Identity Behavior Analytics (IBA)

VoltStack podrá mantener perfiles de comportamiento normal por identidad.

---

### 1334. Behavioral baseline

El perfil podrá considerar:

* horario habitual;
* recursos utilizados;
* frecuencia;
* comandos;
* dispositivos;
* regiones;
* duración de sesión.

---

### 1335. Behavior profile

```php
final readonly class IdentityBehaviorProfile
{
    public function __construct(
        public IdentityIdentifier $identity,
        public array $baselineCharacteristics,
        public DateTimeImmutable $lastUpdated,
    ) {
    }
}
```

---

### 1336. UEBA foundations

El motor de User and Entity Behavior Analytics deberá analizar:

* usuarios;
* cuentas privilegiadas;
* cuentas de servicio;
* workloads;
* dispositivos.

---

### 1337. UEBA risk enrichment

Las anomalías de comportamiento deberán enriquecer, no reemplazar, el motor principal de riesgo.

---

### 1338. Risk-based access decisions

Las decisiones de acceso podrán adaptarse dinámicamente al riesgo.

---

### 1339. Adaptive responses

Ejemplos:

* permitir;
* requerir MFA adicional;
* exigir passkey;
* limitar permisos;
* reducir duración de sesión;
* bloquear.

---

### 1340. Risk decision engine

```php
interface RiskBasedAccessDecisionEngineInterface
{
    public function decide(
        RiskEvaluationContext $context
    ): AdaptiveAccessDecision;
}
```

---

### 1341. AdaptiveAccessDecision

```php
final readonly class AdaptiveAccessDecision
{
    public function __construct(
        public AdaptiveDecisionType $decision,
        public array $requiredActions,
        public SessionRestrictionLevel $restrictionLevel,
    ) {
    }
}
```

---

### 1342. Continuous Access Evaluation

VoltStack deberá reevaluar sesiones activas durante toda su vida útil.

---

### 1343. Continuous evaluation events

Eventos relevantes:

* cambio de contraseña;
* revocación de roles;
* señal de compromiso;
* dispositivo comprometido;
* sesión paralela;
* logout remoto.

---

### 1344. Session restriction

Una sesión podrá pasar dinámicamente a estado restringido.

---

### 1345. Restriction actions

Una sesión restringida podrá:

* impedir operaciones privilegiadas;
* bloquear escritura;
* permitir solo lectura;
* exigir reautenticación;
* impedir descarga;
* impedir exportación.

---

### 1346. Session quarantine

```php
interface SessionQuarantineServiceInterface
{
    public function quarantine(
        SessionIdentifier $session,
        QuarantineReason $reason
    ): QuarantineResult;
}
```

---

### 1347. Automated identity containment

Las respuestas automáticas podrán incluir:

* revocar sesiones;
* invalidar tokens;
* suspender identidad;
* bloquear dispositivo;
* revocar elevaciones;
* suspender service account.

---

### 1348. Containment proportionality

La respuesta deberá ser proporcional al nivel de confianza y severidad.

---

### 1349. Automated playbooks

```php
interface IdentityIncidentPlaybookInterface
{
    public function execute(
        IdentityIncident $incident
    ): PlaybookExecutionResult;
}
```

---

### 1350. Playbook examples

Ejemplos:

* Credential Compromise;
* Break-glass Misuse;
* Impossible Travel;
* Privileged Session Abuse;
* Password Spray;
* Service Account Exposure.

---

### 1351. Identity incident

```php
final readonly class IdentityIncident
{
    public function __construct(
        public string $incidentId,
        public IdentityIdentifier $identity,
        public IdentityIncidentSeverity $severity,
        public array $signals,
        public array $affectedResources,
        public DateTimeImmutable $detectedAt,
    ) {
    }
}
```

---

### 1352. Evidence preservation

Toda respuesta deberá preservar:

* señales;
* decisiones;
* timestamps;
* sesiones;
* tokens;
* dispositivos;
* acciones automáticas.

---

### 1353. Chain of custody

La evidencia deberá mantener:

* integridad;
* inmutabilidad;
* trazabilidad;
* control de acceso;
* auditoría.

---

### 1354. Identity forensic package

```php
final readonly class IdentityForensicPackage
{
    public function __construct(
        public string $packageId,
        public array $events,
        public array $logs,
        public array $tokens,
        public array $sessions,
        public array $deviceEvidence,
    ) {
    }
}
```

---

### 1355. SIEM integration

VoltStack deberá exportar eventos mediante formatos estructurados compatibles con plataformas SIEM.

---

### 1356. SOAR integration

Las respuestas automáticas podrán iniciarse desde plataformas SOAR autorizadas.

---

### 1357. Threat intelligence enrichment

El motor podrá enriquecer señales utilizando:

* reputación IP;
* dominios maliciosos;
* indicadores de compromiso;
* inteligencia sobre credenciales comprometidas.

---

### 1358. Threat intelligence trust

La inteligencia externa deberá clasificarse según nivel de confianza y antigüedad.

---

### 1359. Identity security metrics

Métricas recomendadas:

* tiempo medio de detección;
* tiempo medio de contención;
* sesiones restringidas;
* sesiones revocadas;
* cuentas comprometidas;
* elevaciones bloqueadas;
* incidentes cerrados.

---

### 1360. Security KPI dashboard

El dashboard podrá mostrar:

* tendencias;
* riesgo por tenant;
* riesgo por aplicación;
* identidades privilegiadas;
* cuentas huérfanas;
* MFA coverage;
* anomalías.

---

### 1361. ThreatSeverity

```php
enum ThreatSeverity: string
{
    case Low;
    case Medium;
    case High;
    case Critical;
}
```

---

### 1362. ThreatConfidence

```php
enum ThreatConfidence: string
{
    case Low;
    case Medium;
    case High;
}
```

---

### 1363. IdentityIncidentSeverity

```php
enum IdentityIncidentSeverity: string
{
    case Informational;
    case Low;
    case Medium;
    case High;
    case Critical;
}
```

---

### 1364. SessionRiskLevel

```php
enum SessionRiskLevel: string
{
    case Low;
    case Elevated;
    case High;
    case Critical;
}
```

---

### 1365. DeviceRiskLevel

```php
enum DeviceRiskLevel: string
{
    case Trusted;
    case Elevated;
    case High;
    case Compromised;
}
```

---

### 1366. SignalConfidence

```php
enum SignalConfidence: string
{
    case Weak;
    case Moderate;
    case Strong;
}
```

---

### 1367. AdaptiveDecisionType

```php
enum AdaptiveDecisionType: string
{
    case Allow;
    case Challenge;
    case Restrict;
    case Quarantine;
    case Deny;
}
```

---

### 1368. SessionRestrictionLevel

```php
enum SessionRestrictionLevel: string
{
    case None;
    case ReadOnly;
    case Limited;
    case HighlyRestricted;
}
```

---

### 1369. QuarantineReason

```php
enum QuarantineReason: string
{
    case CredentialCompromise;
    case DeviceCompromise;
    case ImpossibleTravel;
    case PrivilegedMisuse;
    case ThreatIntelligence;
}
```

---

### 1370. Identity incident lifecycle

Estados recomendados:

* Detected;
* Investigating;
* Contained;
* Recovering;
* Closed.

---

### 1371. False positive handling

Toda alerta deberá poder clasificarse como falso positivo sin perder la trazabilidad histórica.

---

### 1372. Analyst feedback loop

Las decisiones de analistas podrán retroalimentar el motor de correlación para mejorar futuras evaluaciones.

---

### 1373. Multi-tenant isolation

Las señales de un tenant nunca deberán influir directamente en el riesgo calculado para otro tenant.

---

### 1374. Privacy-aware analytics

Los modelos analíticos deberán minimizar el tratamiento de datos personales cuando no sean necesarios.

---

### 1375. Retention policy

Las señales deberán conservarse únicamente durante el período definido por la política de seguridad y regulación aplicable.

---

### 1376. Secure signal storage

Las señales deberán almacenarse con:

* cifrado;
* control de acceso;
* integridad;
* versionado cuando aplique.

---

### 1377. Replay-resistant telemetry

La telemetría utilizada por el motor ITDR deberá protegerse contra inyección y replay.

---

### 1378. Threat model updates

El catálogo de amenazas deberá revisarse periódicamente para incorporar nuevas técnicas de ataque.

---

### 1379. Detection testing

VoltStack deberá permitir pruebas controladas de reglas de detección sin afectar producción.

---

### 1380. Simulation mode

Las políticas podrán ejecutarse en modo simulación para medir impacto antes de activarse.

---

### 1381. Explainable decisions

Las decisiones automáticas deberán incluir una explicación auditable de los factores que las motivaron.

---

### 1382. Response override

Un operador autorizado podrá cancelar una respuesta automática cuando exista justificación documentada.

---

### 1383. Override audit

Toda anulación manual deberá quedar registrada con:

* actor;
* motivo;
* hora;
* incidente asociado.

---

### 1384. Identity security reporting

Los reportes deberán poder agrupar incidentes por:

* tenant;
* aplicación;
* tipo de amenaza;
* criticidad;
* identidad;
* periodo.

---

### 1385. Operational resilience

El fallo del motor analítico no deberá impedir la aplicación de controles de autenticación básicos.

---

### 1386. Graceful degradation

Si un componente analítico deja de estar disponible, VoltStack deberá degradar funcionalidades no esenciales sin comprometer la seguridad mínima.

---

### 1387. ITDR extensibility

El motor deberá permitir incorporar nuevos detectores sin modificar el núcleo del framework.

---

### 1388. Detector plugin model

```php
interface IdentityThreatDetectorInterface
{
    public function detect(
        IdentitySecuritySignal $signal
    ): ?ThreatAssessment;
}
```

---

### 1389. Detector isolation

Un detector defectuoso no deberá impedir la ejecución del resto de detectores.

---

### 1390. Detector prioritization

Los detectores podrán ejecutarse según prioridad y costo computacional.

---

### 1391. Threat response policy

Las respuestas deberán gobernarse mediante políticas configurables por tenant.

---

### 1392. Tenant risk profile

Cada tenant podrá definir umbrales de riesgo acordes con sus requisitos regulatorios y operativos.

---

### 1393. Incident notification

Las alertas críticas podrán notificarse a:

* SOC;
* administradores;
* propietarios del tenant;
* equipos de respuesta.

---

### 1394. Notification throttling

Las notificaciones deberán limitarse para evitar tormentas de alertas.

---

### 1395. Security evidence export

La evidencia deberá poder exportarse preservando integridad y metadatos.

---

### 1396. Compliance support

El subsistema deberá facilitar evidencia para auditorías de seguridad y cumplimiento.

---

### 1397. Identity resilience

La arquitectura deberá asumir que una identidad puede verse comprometida en cualquier momento y reaccionar en consecuencia.

---

### 1398. Zero Trust alignment

Todas las decisiones deberán alinearse con el principio de **"never trust, always verify"**.

---

### 1399. Security events

Eventos recomendados:

* `IdentityThreatDetected`
* `SessionRiskElevated`
* `ImpossibleTravelDetected`
* `CredentialCompromiseSuspected`
* `PasswordSprayDetected`
* `MfaFatigueDetected`
* `IdentityQuarantined`
* `AdaptiveAccessDecisionApplied`
* `ThreatPlaybookExecuted`
* `IdentityIncidentClosed`

---

### 1400. Resultado de esta entrega

Esta entrega establece:

```text
Identity Threat Detection and Response (ITDR)
Authentication Anomaly Correlation
Continuous Session Risk Scoring
Impossible Travel Detection
Token Replay Detection
Credential Compromise Detection
Password Spray Correlation
MFA Fatigue Detection
Device Compromise Signals
Identity Behavior Analytics
User and Entity Behavior Analytics
Risk-Based Access Decisions
Continuous Access Evaluation
Session Restriction and Quarantine
Automated Identity Containment
Incident Response Playbooks
Identity Evidence Preservation
SIEM and SOAR Integration
Identity Security Metrics
Zero Trust Identity Response
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 15

- Cryptographic Architecture
- Cryptographic Agility
- Key Management Architecture
- Envelope Encryption
- Data Encryption Policies
- Field-Level Encryption
- Key Hierarchies
- HSM Integration
- KMS Integration
- Secret Derivation
- Secure Random Generation
- Digital Signatures
- MAC Validation
- Hashing Policies
- Secure Key Rotation
- Cryptographic Auditing
- Post-Quantum Readiness
- FIPS Compliance
- Cryptographic Governance
```

## Entrega 15

**Documento:** Parte 05
**Entrega:** 15 de varias
**Cobertura:** Secciones **1401–1500**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 14`

---

### 1401. Cryptographic Architecture

VoltStack deberá incorporar una arquitectura criptográfica centralizada para proteger:

* credenciales;
* sesiones;
* tokens;
* secretos;
* claves;
* datos personales;
* evidencia de auditoría;
* comunicaciones;
* artefactos firmados;
* información multi-tenant.

La criptografía no deberá implementarse de forma dispersa dentro de Controllers, Services o paquetes individuales.

---

### 1402. Cryptographic security goals

La arquitectura deberá garantizar:

* confidencialidad;
* integridad;
* autenticidad;
* no reutilización insegura de claves;
* separación de propósitos;
* rotación;
* revocación;
* trazabilidad;
* agilidad criptográfica;
* recuperación controlada.

---

### 1403. Cryptographic threat model

El modelo deberá considerar:

* robo de claves;
* algoritmos obsoletos;
* configuración insegura;
* nonce reuse;
* weak randomness;
* downgrade attacks;
* key confusion;
* cross-tenant key reuse;
* exposición de plaintext;
* errores de implementación;
* side channels;
* replay;
* key substitution;
* pérdida de material criptográfico;
* compromiso de KMS o HSM.

---

### 1404. Cryptographic architectural components

```text id="zdl2tw"
Application Security Operation
        ↓
Cryptographic Policy Resolver
        ↓
Purpose and Tenant Resolution
        ↓
Key Resolution
        ↓
Algorithm Selection
        ↓
Cryptographic Provider
        ↓
Protected Operation
        ↓
Metadata and Versioning
        ↓
Audit and Telemetry
```

---

### 1405. CryptographicService

```php id="u0rrha"
interface CryptographicServiceInterface
{
    public function encrypt(
        SensitiveValue $plaintext,
        EncryptionContext $context
    ): EncryptedPayload;

    public function decrypt(
        EncryptedPayload $payload,
        DecryptionContext $context
    ): SensitiveValue;

    public function sign(
        string $message,
        SignatureContext $context
    ): DigitalSignature;

    public function verify(
        string $message,
        DigitalSignature $signature,
        VerificationContext $context
    ): SignatureVerificationResult;
}
```

---

### 1406. Cryptographic operation purposes

Toda operación deberá declarar un propósito explícito.

Ejemplos:

* session protection;
* token signing;
* credential encryption;
* audit integrity;
* tenant data encryption;
* secret wrapping;
* password reset tokens;
* webhook signatures.

---

### 1407. CryptographicPurpose

```php id="pqkt3b"
enum CryptographicPurpose: string
{
    case DataEncryption = 'data_encryption';
    case FieldEncryption = 'field_encryption';
    case KeyWrapping = 'key_wrapping';
    case DigitalSignature = 'digital_signature';
    case MessageAuthentication = 'message_authentication';
    case TokenProtection = 'token_protection';
    case AuditIntegrity = 'audit_integrity';
    case SecretDerivation = 'secret_derivation';
}
```

---

### 1408. Purpose separation

Una misma clave no deberá reutilizarse para propósitos criptográficos distintos.

No deberá utilizarse una clave de:

* cifrado para firmar;
* firma para MAC;
* sesión para cifrado de datos;
* tenant A para tenant B;
* producción para desarrollo.

---

### 1409. Cryptographic policy engine

```php id="zfxl8i"
interface CryptographicPolicyEngineInterface
{
    public function resolve(
        CryptographicOperationContext $context
    ): CryptographicPolicyDecision;
}
```

---

### 1410. CryptographicPolicyDecision

```php id="5j1m9c"
final readonly class CryptographicPolicyDecision
{
    public function __construct(
        public string $algorithm,
        public string $keyProfile,
        public int $minimumKeyStrength,
        public bool $hardwareProtectionRequired,
        public bool $rotationRequired,
        public array $restrictions,
    ) {
    }
}
```

---

### 1411. Cryptographic agility

VoltStack deberá poder sustituir algoritmos, proveedores y formatos sin reescribir el dominio.

---

### 1412. Algorithm registry

```php id="gzp2j3"
interface CryptographicAlgorithmRegistryInterface
{
    public function resolve(
        string $algorithmId
    ): CryptographicAlgorithmDefinition;

    public function isAllowed(
        string $algorithmId,
        CryptographicPurpose $purpose
    ): bool;
}
```

---

### 1413. CryptographicAlgorithmDefinition

```php id="yk2o0d"
final readonly class CryptographicAlgorithmDefinition
{
    public function __construct(
        public string $algorithmId,
        public CryptographicAlgorithmType $type,
        public int $securityStrengthBits,
        public CryptographicAlgorithmState $state,
        public array $allowedPurposes,
    ) {
    }
}
```

---

### 1414. CryptographicAlgorithmType

```php id="gwdrng"
enum CryptographicAlgorithmType: string
{
    case SymmetricEncryption = 'symmetric_encryption';
    case AsymmetricEncryption = 'asymmetric_encryption';
    case DigitalSignature = 'digital_signature';
    case Hash = 'hash';
    case Mac = 'mac';
    case KeyDerivation = 'key_derivation';
    case PasswordHashing = 'password_hashing';
}
```

---

### 1415. CryptographicAlgorithmState

```php id="cjmhk7"
enum CryptographicAlgorithmState: string
{
    case Preferred = 'preferred';
    case Allowed = 'allowed';
    case LegacyVerifyOnly = 'legacy_verify_only';
    case Deprecated = 'deprecated';
    case Prohibited = 'prohibited';
}
```

---

### 1416. Algorithm allowlists

VoltStack deberá utilizar allowlists explícitas.

Nunca deberá aceptar automáticamente cualquier algoritmo declarado por input externo.

---

### 1417. Algorithm downgrade prevention

Cuando un artefacto declare un algoritmo inferior al requerido, la operación deberá rechazarse aunque el algoritmo todavía exista en el runtime.

---

### 1418. Legacy verification mode

Algoritmos antiguos podrán conservarse únicamente para:

* verificar datos existentes;
* migrar formatos;
* reemitir artefactos;
* rotar claves.

No deberán utilizarse para nuevas operaciones.

---

### 1419. Cryptographic provider abstraction

```php id="32nuv8"
interface CryptographicProviderInterface
{
    public function supports(
        CryptographicAlgorithmDefinition $algorithm
    ): bool;

    public function execute(
        CryptographicOperation $operation
    ): CryptographicOperationResult;
}
```

---

### 1420. Provider types

VoltStack podrá integrar:

* OpenSSL;
* libsodium;
* platform crypto APIs;
* cloud KMS;
* HSM;
* PKCS#11;
* remote signing services.

---

### 1421. Provider selection policy

La selección deberá considerar:

* algoritmo;
* tenant;
* sensibilidad;
* compliance;
* latencia;
* disponibilidad;
* hardware requirement;
* key residency.

---

### 1422. Key management architecture

Todas las claves deberán gestionarse mediante un subsistema central.

---

### 1423. KeyManagementService

```php id="sau45d"
interface KeyManagementServiceInterface
{
    public function create(
        KeyCreationRequest $request
    ): ManagedKey;

    public function resolve(
        KeyReference $reference,
        KeyUsageContext $context
    ): ResolvedKey;

    public function rotate(
        KeyReference $reference,
        KeyRotationPolicy $policy
    ): KeyRotationResult;

    public function revoke(
        KeyReference $reference,
        KeyRevocationReason $reason
    ): void;
}
```

---

### 1424. ManagedKey

```php id="xg5czx"
final readonly class ManagedKey
{
    public function __construct(
        public string $keyId,
        public string $versionId,
        public CryptographicPurpose $purpose,
        public string $algorithm,
        public ManagedKeyState $state,
        public KeyProtectionLevel $protectionLevel,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 1425. ManagedKeyState

```php id="mr3c8h"
enum ManagedKeyState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rotating = 'rotating';
    case Retiring = 'retiring';
    case Retired = 'retired';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
    case Destroyed = 'destroyed';
}
```

---

### 1426. KeyProtectionLevel

```php id="6ugklx"
enum KeyProtectionLevel: string
{
    case Software = 'software';
    case OperatingSystem = 'operating_system';
    case CloudKms = 'cloud_kms';
    case HardwareSecurityModule = 'hardware_security_module';
}
```

---

### 1427. Key references

El dominio deberá trabajar con referencias opacas, no con material de clave directamente.

---

### 1428. KeyReference

```php id="2zjo8l"
final readonly class KeyReference
{
    public function __construct(
        public string $keyId,
        public ?string $versionId,
        public string $provider,
        public string $tenantId,
    ) {
    }
}
```

---

### 1429. Key material isolation

El material de clave no deberá:

* aparecer en logs;
* serializarse;
* incluirse en excepciones;
* exponerse a Controllers;
* persistirse sin protección;
* permanecer más tiempo del necesario en memoria.

---

### 1430. Key hierarchy

VoltStack deberá utilizar jerarquías de claves para evitar cifrar todo directamente con una clave raíz.

---

### 1431. Recommended key hierarchy

```text id="b8ih3l"
Root Key
   ↓
Key Encryption Key
   ↓
Tenant Master Key
   ↓
Data Encryption Key
   ↓
Protected Data
```

---

### 1432. Root key

La root key deberá:

* permanecer fuera del almacenamiento ordinario;
* utilizarse solo para proteger claves inferiores;
* residir preferentemente en HSM o KMS;
* tener acceso extremadamente restringido.

---

### 1433. Key Encryption Key

Una KEK deberá proteger:

* tenant keys;
* data encryption keys;
* backup keys;
* signing key material exportable.

---

### 1434. Tenant master keys

Cada tenant podrá poseer una o más claves maestras separadas.

---

### 1435. Tenant cryptographic isolation

Nunca deberá utilizarse la misma DEK para cifrar datos de tenants diferentes.

---

### 1436. Data Encryption Key

```php id="4o7kss"
final readonly class DataEncryptionKey
{
    public function __construct(
        public string $keyId,
        public string $tenantId,
        public string $algorithm,
        public SensitiveValue $keyMaterial,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 1437. Envelope encryption

VoltStack deberá utilizar envelope encryption para datos sensibles de alto volumen.

---

### 1438. Envelope encryption flow

```text id="v0f2d6"
Generate DEK
     ↓
Encrypt Data with DEK
     ↓
Wrap DEK with KEK
     ↓
Store Ciphertext
     ↓
Store Wrapped DEK
     ↓
Store Algorithm and Version Metadata
```

---

### 1439. EncryptedPayload

```php id="d8zgpt"
final readonly class EncryptedPayload
{
    public function __construct(
        public string $ciphertext,
        public string $algorithm,
        public string $keyId,
        public string $keyVersion,
        public string $nonce,
        public ?string $authenticationTag,
        public ?string $wrappedKey,
        public array $authenticatedMetadata,
    ) {
    }
}
```

---

### 1440. Authenticated encryption

Para nuevo cifrado simétrico deberá preferirse AEAD.

Ejemplos de familias permitidas según policy:

* AES-GCM;
* ChaCha20-Poly1305;
* XChaCha20-Poly1305.

---

### 1441. AEAD associated data

El associated data podrá incluir:

* tenant ID;
* resource type;
* resource ID;
* field name;
* schema version;
* key purpose.

---

### 1442. Ciphertext swapping prevention

Los metadatos autenticados deberán impedir mover ciphertext válidos entre:

* tenants;
* recursos;
* campos;
* contextos;
* versiones incompatibles.

---

### 1443. Nonce generation

Los nonces deberán:

* generarse correctamente según el algoritmo;
* tener longitud válida;
* no reutilizarse con la misma clave;
* no depender de timestamps únicamente.

---

### 1444. Nonce reuse protection

Para algoritmos sensibles a nonce reuse, VoltStack deberá:

* utilizar generación segura;
* registrar estrategia;
* aplicar contadores cuando corresponda;
* rotar claves ante riesgo de repetición.

---

### 1445. Data encryption policy

```php id="spm8jw"
final readonly class DataEncryptionPolicy
{
    public function __construct(
        public DataClassification $minimumClassification,
        public CryptographicPurpose $purpose,
        public string $algorithmProfile,
        public KeyProtectionLevel $minimumProtection,
        public bool $fieldLevelRequired,
        public bool $searchableEncryptionAllowed,
    ) {
    }
}
```

---

### 1446. Data classification

```php id="79yiax"
enum DataClassification: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Confidential = 'confidential';
    case Restricted = 'restricted';
    case HighlyRestricted = 'highly_restricted';
}
```

---

### 1447. Encryption at rest

VoltStack deberá considerar múltiples capas:

* disk encryption;
* database encryption;
* object storage encryption;
* application-level encryption;
* field-level encryption.

Una capa no deberá asumirse sustituto automático de las demás.

---

### 1448. Field-level encryption

Los campos especialmente sensibles deberán cifrarse antes de llegar al storage.

---

### 1449. FieldEncryptionService

```php id="eh7ry5"
interface FieldEncryptionServiceInterface
{
    public function encryptField(
        string $fieldName,
        SensitiveValue $value,
        FieldEncryptionContext $context
    ): EncryptedFieldValue;

    public function decryptField(
        string $fieldName,
        EncryptedFieldValue $value,
        FieldDecryptionContext $context
    ): SensitiveValue;
}
```

---

### 1450. EncryptedFieldValue

```php id="b3vo48"
final readonly class EncryptedFieldValue
{
    public function __construct(
        public string $ciphertext,
        public string $keyId,
        public string $keyVersion,
        public string $algorithm,
        public string $nonce,
        public string $authenticationTag,
        public int $formatVersion,
    ) {
    }
}
```

---

### 1451. Field encryption candidates

Ejemplos:

* government identifiers;
* recovery secrets;
* private keys;
* phone numbers según policy;
* medical or financial attributes;
* identity provider tokens;
* sensitive external identifiers.

---

### 1452. Search limitations

Los campos cifrados no deberán hacerse buscables mediante cifrado determinista sin una evaluación explícita del riesgo de pattern leakage.

---

### 1453. Blind indexing

Cuando sea indispensable buscar datos protegidos, VoltStack podrá utilizar blind indexes separados.

---

### 1454. BlindIndexService

```php id="2o6l8d"
interface BlindIndexServiceInterface
{
    public function create(
        SensitiveValue $value,
        BlindIndexContext $context
    ): BlindIndexValue;
}
```

---

### 1455. Blind index restrictions

Los blind indexes deberán:

* utilizar clave separada;
* normalizarse cuidadosamente;
* limitar colisiones;
* no revelar el plaintext;
* rotarse;
* tratarse como información sensible.

---

### 1456. Deterministic encryption restrictions

El cifrado determinista deberá estar deshabilitado por defecto y solo habilitarse con:

* caso de uso documentado;
* baja entropía evaluada;
* mitigaciones;
* aprobación de seguridad.

---

### 1457. HSM integration

VoltStack deberá poder integrar Hardware Security Modules para operaciones de alta sensibilidad.

---

### 1458. HSM use cases

El HSM podrá utilizarse para:

* root keys;
* signing keys;
* code signing;
* certificate authority keys;
* privileged token signing;
* key wrapping;
* regulated workloads.

---

### 1459. HsmProvider

```php id="v2feg1"
interface HsmProviderInterface
{
    public function generateKey(
        HsmKeyGenerationRequest $request
    ): HsmKeyReference;

    public function sign(
        HsmKeyReference $key,
        string $message,
        HsmSignatureContext $context
    ): DigitalSignature;

    public function unwrap(
        HsmKeyReference $key,
        string $wrappedPayload
    ): SensitiveValue;
}
```

---

### 1460. HSM extraction prohibition

Las claves marcadas como non-exportable nunca deberán salir del HSM en plaintext.

---

### 1461. KMS integration

VoltStack deberá abstraer servicios de gestión de claves cloud y privados.

---

### 1462. KmsProvider

```php id="fa0ie6"
interface KmsProviderInterface
{
    public function encrypt(
        KeyReference $key,
        SensitiveValue $plaintext,
        array $encryptionContext
    ): KmsEncryptedPayload;

    public function decrypt(
        KmsEncryptedPayload $payload,
        array $encryptionContext
    ): SensitiveValue;

    public function generateDataKey(
        KeyReference $key,
        DataKeyGenerationContext $context
    ): GeneratedDataKey;
}
```

---

### 1463. KMS encryption context

El encryption context deberá vincular la operación a:

* tenant;
* application;
* purpose;
* resource;
* environment.

---

### 1464. KMS provider compromise

La arquitectura deberá asumir que un proveedor KMS puede sufrir:

* indisponibilidad;
* revocación;
* configuración incorrecta;
* compromiso de credenciales;
* restricciones regionales.

---

### 1465. Multi-provider key strategy

Para cargas críticas, VoltStack podrá permitir:

* primary KMS;
* backup KMS;
* HSM local;
* recovery key escrow.

La recuperación no deberá debilitar el control ordinario.

---

### 1466. Key derivation

La derivación de claves deberá utilizar algoritmos diseñados para ese propósito.

---

### 1467. KeyDerivationService

```php id="dc9cbm"
interface KeyDerivationServiceInterface
{
    public function derive(
        SensitiveValue $inputKeyMaterial,
        KeyDerivationContext $context
    ): DerivedKey;
}
```

---

### 1468. Key derivation use cases

Ejemplos:

* subkeys por tenant;
* subkeys por propósito;
* session keys;
* blind index keys;
* MAC keys;
* temporary encryption keys.

---

### 1469. HKDF policy

HKDF podrá utilizarse para derivar subkeys cuando el input key material posea entropía suficiente.

---

### 1470. Password-derived keys

Las claves derivadas de contraseñas deberán utilizar KDFs específicas como:

* Argon2id;
* scrypt;
* PBKDF2 cuando exista requerimiento de compatibilidad.

---

### 1471. Salt requirements

Los salts deberán:

* ser únicos;
* generarse aleatoriamente;
* almacenarse junto al resultado;
* no reutilizarse como secretos.

---

### 1472. Pepper requirements

Los peppers deberán:

* almacenarse fuera de la base de datos;
* rotarse;
* versionarse;
* protegerse mediante KMS o HSM cuando corresponda.

---

### 1473. Secure random generation

Toda entropía criptográfica deberá provenir de un CSPRNG confiable.

---

### 1474. SecureRandomGenerator

```php id="6sfcn6"
interface SecureRandomGeneratorInterface
{
    public function bytes(int $length): SensitiveValue;

    public function token(int $entropyBits): SensitiveValue;

    public function integer(int $minimum, int $maximum): int;
}
```

---

### 1475. Randomness use cases

Se utilizará para:

* session identifiers;
* CSRF tokens;
* nonces;
* salts;
* reset tokens;
* authorization codes;
* API keys;
* recovery codes;
* data encryption keys.

---

### 1476. Predictable randomness prohibition

No deberán utilizarse para seguridad:

* timestamps;
* incremental IDs;
* UUID no aleatorios sin análisis;
* pseudo-random generators generales;
* hashes de datos predecibles.

---

### 1477. Entropy health

El runtime deberá detectar y fallar de forma segura si el sistema no puede proporcionar entropía confiable.

---

### 1478. Digital signatures

VoltStack deberá centralizar las operaciones de firma y verificación.

---

### 1479. DigitalSignature

```php id="2xsod1"
final readonly class DigitalSignature
{
    public function __construct(
        public string $value,
        public string $algorithm,
        public string $keyId,
        public string $keyVersion,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
```

---

### 1480. Signature use cases

Ejemplos:

* JWT;
* OIDC tokens;
* audit manifests;
* software artifacts;
* federation metadata;
* signed webhooks;
* policy bundles;
* configuration packages.

---

### 1481. Signature algorithm policy

La política deberá definir:

* algoritmos permitidos;
* tamaño mínimo de clave;
* formatos;
* curve allowlist;
* hashing;
* key provenance;
* maximum artifact lifetime.

---

### 1482. Signature verification pipeline

```text id="b9g5n2"
Parse Signature Metadata
      ↓
Resolve Trusted Key
      ↓
Validate Algorithm Allowlist
      ↓
Validate Key Purpose
      ↓
Validate Key State
      ↓
Validate Signature
      ↓
Validate Artifact Context
      ↓
Validate Replay or Freshness
```

---

### 1483. Key confusion prevention

Una clave deberá validarse contra:

* tipo;
* algoritmo;
* uso;
* issuer;
* tenant;
* lifecycle state.

---

### 1484. Signature context binding

Una firma válida criptográficamente no deberá aceptarse si pertenece a otro:

* tenant;
* issuer;
* audience;
* purpose;
* environment;
* artifact type.

---

### 1485. Message authentication codes

VoltStack deberá utilizar MAC cuando ambas partes compartan un secreto y no sea necesaria verificación pública.

---

### 1486. MacService

```php id="5y3jvi"
interface MacServiceInterface
{
    public function create(
        string $message,
        MacContext $context
    ): MessageAuthenticationCode;

    public function verify(
        string $message,
        MessageAuthenticationCode $mac,
        MacVerificationContext $context
    ): bool;
}
```

---

### 1487. MAC key separation

Cada integración deberá tener una clave MAC distinta y separada de las claves de cifrado.

---

### 1488. Constant-time comparison

La comparación de:

* MACs;
* hashes secretos;
* tokens;
* signatures codificadas;

deberá utilizar operaciones constant-time cuando corresponda.

---

### 1489. Hashing policies

VoltStack deberá distinguir claramente entre:

* hashing general;
* password hashing;
* keyed hashing;
* integrity hashing;
* content addressing.

---

### 1490. HashPolicy

```php id="jdjnvl"
final readonly class HashPolicy
{
    public function __construct(
        public string $algorithm,
        public int $minimumOutputBits,
        public bool $keyedRequired,
        public bool $legacyVerificationAllowed,
    ) {
    }
}
```

---

### 1491. General-purpose hash usage

Los hashes generales podrán utilizarse para:

* integrity digests;
* cache keys;
* artifact fingerprints;
* content addressing;
* audit chaining.

No deberán usarse directamente para almacenar contraseñas.

---

### 1492. Cryptographic key rotation

Toda clase de clave deberá tener una política de rotación.

---

### 1493. KeyRotationPolicy

```php id="b2cu7g"
final readonly class KeyRotationPolicy
{
    public function __construct(
        public DateInterval $maximumAge,
        public DateInterval $overlapPeriod,
        public bool $rotateOnUseThreshold,
        public bool $rotateOnIncident,
        public bool $reencryptExistingData,
    ) {
    }
}
```

---

### 1494. Rotation stages

```text id="1c1g80"
Create New Key Version
      ↓
Publish New Active Version
      ↓
Use New Version for Writes
      ↓
Accept Previous Version for Reads
      ↓
Re-encrypt or Re-sign as Needed
      ↓
Retire Previous Version
      ↓
Destroy When Retention Allows
```

---

### 1495. Emergency key rotation

Ante sospecha de compromiso deberá:

* marcarse la clave como compromised;
* impedir nuevos usos;
* activar nueva versión;
* identificar artefactos afectados;
* revocar tokens;
* reemitir firmas;
* recifrar datos según riesgo;
* preservar evidencia.

---

### 1496. Cryptographic audit events

Eventos recomendados:

* `CryptographicOperationDenied`;
* `KeyCreated`;
* `KeyActivated`;
* `KeyRotationStarted`;
* `KeyRotationCompleted`;
* `KeyRetired`;
* `KeyRevoked`;
* `KeyCompromiseDetected`;
* `KeyDestroyed`;
* `DecryptionFailed`;
* `SignatureVerificationFailed`;
* `AlgorithmDowngradeRejected`;
* `LegacyAlgorithmUsed`;
* `HsmOperationFailed`;
* `KmsProviderUnavailable`.

---

### 1497. Cryptographic governance

VoltStack deberá mantener un inventario de:

* algoritmos;
* claves;
* certificados;
* proveedores;
* owners;
* propósitos;
* dependencias;
* fechas de expiración;
* excepciones.

---

### 1498. Compliance and FIPS mode

VoltStack podrá ofrecer perfiles criptográficos orientados a entornos regulados.

Un perfil FIPS deberá:

* utilizar proveedores validados cuando sea requerido;
* restringir algoritmos;
* bloquear configuraciones incompatibles;
* registrar el modo activo;
* impedir fallback silencioso.

---

### 1499. Post-quantum readiness

VoltStack deberá prepararse para migraciones post-cuánticas mediante:

* inventario criptográfico;
* versionado de algoritmos;
* formatos extensibles;
* crypto-agility;
* separación entre identidad y algoritmo;
* soporte futuro para esquemas híbridos;
* políticas de transición.

No deberán adoptarse algoritmos experimentales en producción sin estandarización, soporte del proveedor y evaluación de seguridad.

---

### 1500. Resultado de esta entrega

Esta entrega establece:

```text id="xp6kpg"
Cryptographic Architecture
Cryptographic Policy Engine
Purpose Separation
Cryptographic Agility
Algorithm Registry
Algorithm Downgrade Prevention
Legacy Verification Mode
Cryptographic Provider Abstraction
Key Management Architecture
Managed Key Lifecycle
Key References
Key Material Isolation
Key Hierarchies
Tenant Cryptographic Isolation
Envelope Encryption
Authenticated Encryption
Associated Data Binding
Nonce Reuse Protection
Data Encryption Policies
Field-Level Encryption
Blind Indexing
HSM Integration
KMS Integration
Key Derivation
Salt and Pepper Policies
Secure Random Generation
Digital Signatures
Signature Context Binding
Message Authentication Codes
Constant-Time Comparison
Hashing Policies
Secure Key Rotation
Emergency Key Rotation
Cryptographic Auditing
Cryptographic Governance
FIPS-Oriented Profiles
Post-Quantum Readiness
```

La siguiente entrega continuará con:

```text id="6989cs"
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 16

- Certificate and PKI architecture
- Certificate authorities
- Trust stores
- Certificate profiles
- Certificate issuance
- Certificate signing requests
- Certificate lifecycle
- Certificate rotation
- Certificate revocation
- CRL and OCSP
- mTLS identity
- Client certificate authentication
- Service-to-service authentication
- SPIFFE and SPIRE foundations
- Workload certificates
- Certificate pinning
- Trust anchor rotation
- Code signing certificates
- Timestamping authorities
- PKI audit and governance
```

## Entrega 16

**Documento:** Parte 05
**Entrega:** 16 de varias
**Cobertura:** Secciones **1501–1600**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 15`

---

### 1501. Public Key Infrastructure Architecture

VoltStack deberá incorporar una arquitectura central de Public Key Infrastructure para gestionar:

* autoridades certificadoras;
* certificados X.509;
* identidades de servicio;
* identidades de workload;
* autenticación mTLS;
* firmas de código;
* sellado de tiempo;
* trust stores;
* revocación;
* validación de cadenas;
* políticas de confianza.

La infraestructura PKI deberá mantenerse separada del código de aplicación y de los Controllers.

---

### 1502. PKI security goals

La arquitectura deberá garantizar:

* autenticidad de identidades;
* integridad de certificados;
* validación correcta de cadenas;
* separación de autoridades;
* emisión controlada;
* rotación;
* revocación oportuna;
* aislamiento por tenant y entorno;
* auditabilidad;
* reducción de confianza implícita.

---

### 1503. PKI threat model

El modelo deberá considerar:

* compromiso de una CA;
* emisión no autorizada;
* certificados fraudulentos;
* claves privadas robadas;
* trust stores manipulados;
* certificados expirados;
* revocación no consultada;
* validación incompleta de hostname;
* path building inseguro;
* algoritmo débil;
* cross-tenant trust;
* abuso de certificados cliente;
* sustitución de trust anchors;
* downgrade de TLS;
* abuso de certificados de firma de código.

---

### 1504. PKI architectural components

```text
Certificate Request
       ↓
Identity and Policy Validation
       ↓
Certificate Profile Resolution
       ↓
Certificate Authority Selection
       ↓
Key Proof Validation
       ↓
Certificate Issuance
       ↓
Distribution
       ↓
Usage Monitoring
       ↓
Renewal or Rotation
       ↓
Revocation and Audit
```

---

### 1505. PublicKeyInfrastructureService

```php
interface PublicKeyInfrastructureServiceInterface
{
    public function issue(
        CertificateIssuanceRequest $request
    ): IssuedCertificate;

    public function renew(
        CertificateRenewalRequest $request
    ): IssuedCertificate;

    public function revoke(
        CertificateReference $certificate,
        CertificateRevocationReason $reason
    ): CertificateRevocationResult;

    public function validate(
        PresentedCertificate $certificate,
        CertificateValidationContext $context
    ): CertificateValidationResult;
}
```

---

### 1506. Certificate authority hierarchy

VoltStack deberá soportar jerarquías compuestas por:

* offline root CA;
* intermediate CAs;
* issuing CAs;
* tenant-specific CAs;
* workload CAs;
* code-signing CAs;
* recovery authorities.

---

### 1507. Root certificate authority

La root CA deberá:

* permanecer offline cuando sea viable;
* utilizarse únicamente para firmar intermediarias;
* residir en HSM;
* requerir quorum operacional;
* poseer procedimientos de recuperación;
* mantenerse fuera del tráfico ordinario.

---

### 1508. Intermediate certificate authority

Una intermediate CA deberá separar dominios de confianza como:

* producción;
* desarrollo;
* workloads;
* usuarios;
* dispositivos;
* firma de código;
* tenants regulados.

---

### 1509. Issuing certificate authority

Una issuing CA deberá emitir certificados finales bajo políticas limitadas y perfiles explícitos.

---

### 1510. CertificateAuthorityDefinition

```php
final readonly class CertificateAuthorityDefinition
{
    public function __construct(
        public string $authorityId,
        public string $name,
        public CertificateAuthorityType $type,
        public CertificateAuthorityState $state,
        public CertificateReference $certificate,
        public KeyReference $signingKey,
        public array $allowedProfiles,
        public ?string $parentAuthorityId,
        public string $trustDomain,
    ) {
    }
}
```

---

### 1511. CertificateAuthorityType

```php
enum CertificateAuthorityType: string
{
    case Root = 'root';
    case Intermediate = 'intermediate';
    case Issuing = 'issuing';
    case Workload = 'workload';
    case Device = 'device';
    case CodeSigning = 'code_signing';
    case Timestamping = 'timestamping';
}
```

---

### 1512. CertificateAuthorityState

```php
enum CertificateAuthorityState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rotating = 'rotating';
    case Suspended = 'suspended';
    case Retiring = 'retiring';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
    case Retired = 'retired';
}
```

---

### 1513. CA purpose separation

Una CA no deberá emitir certificados para propósitos no incluidos expresamente en su política.

Una CA de workloads no deberá emitir certificados para:

* usuarios humanos;
* firma de código;
* correo;
* documentos;
* certificados públicos web.

---

### 1514. CA private key protection

Las claves privadas de CA deberán:

* ser non-exportable;
* residir preferentemente en HSM;
* requerir autorización reforzada;
* tener controles duales;
* emitir auditoría por cada uso;
* rotarse bajo ceremonia controlada.

---

### 1515. Certificate profile architecture

Todo certificado deberá emitirse a partir de un perfil versionado.

---

### 1516. CertificateProfile

```php
final readonly class CertificateProfile
{
    public function __construct(
        public string $profileId,
        public int $version,
        public string $name,
        public CertificatePurpose $purpose,
        public DateInterval $maximumLifetime,
        public array $allowedSubjectAttributes,
        public array $requiredExtensions,
        public array $allowedKeyAlgorithms,
        public array $allowedSignatureAlgorithms,
        public bool $subjectAlternativeNameRequired,
    ) {
    }
}
```

---

### 1517. CertificatePurpose

```php
enum CertificatePurpose: string
{
    case ServerAuthentication = 'server_authentication';
    case ClientAuthentication = 'client_authentication';
    case MutualTls = 'mutual_tls';
    case WorkloadIdentity = 'workload_identity';
    case DeviceIdentity = 'device_identity';
    case CodeSigning = 'code_signing';
    case DocumentSigning = 'document_signing';
    case Timestamping = 'timestamping';
    case EmailProtection = 'email_protection';
}
```

---

### 1518. Certificate profile controls

Un perfil deberá definir:

* Extended Key Usage;
* Key Usage;
* Subject Alternative Names;
* basic constraints;
* path length;
* name constraints;
* algorithm;
* key size;
* validez máxima;
* política de revocación.

---

### 1519. Extended Key Usage restrictions

VoltStack deberá validar que el certificado posea el EKU correcto para la operación solicitada.

Un certificado de server authentication no deberá aceptarse automáticamente como client authentication.

---

### 1520. Key Usage restrictions

La validación deberá comprobar usos como:

* digitalSignature;
* keyEncipherment;
* keyAgreement;
* keyCertSign;
* cRLSign;
* contentCommitment.

---

### 1521. Certificate signing request

La emisión podrá iniciarse mediante un Certificate Signing Request.

---

### 1522. CertificateSigningRequest

```php
final readonly class CertificateSigningRequest
{
    public function __construct(
        public string $encodedRequest,
        public string $publicKeyAlgorithm,
        public string $signatureAlgorithm,
        public array $requestedSubjectAttributes,
        public array $requestedExtensions,
    ) {
    }
}
```

---

### 1523. CSR validation

La validación deberá comprobar:

* estructura ASN.1;
* firma del CSR;
* proof of possession;
* algoritmo permitido;
* tamaño de clave;
* extensiones solicitadas;
* identidad solicitante;
* tenant;
* perfil.

---

### 1524. CSR extension filtering

Las extensiones solicitadas no deberán copiarse directamente al certificado.

La CA deberá reconstruirlas desde la policy.

---

### 1525. Subject validation

Los datos del Subject deberán derivarse de fuentes confiables y no únicamente de input del solicitante.

---

### 1526. Subject Alternative Name

SAN deberá utilizarse para representar identidades modernas como:

* DNS names;
* IP addresses;
* URI identities;
* email identities;
* SPIFFE IDs;
* device identifiers.

---

### 1527. Common Name limitations

El Common Name no deberá utilizarse como sustituto de SAN para validación de identidad de red.

---

### 1528. Certificate issuance request

```php
final readonly class CertificateIssuanceRequest
{
    public function __construct(
        public string $requestId,
        public string $tenantId,
        public IdentityIdentifier|string $subject,
        public string $profileId,
        public CertificateSigningRequest $csr,
        public DateInterval $requestedLifetime,
        public array $requestedNames,
        public CertificateIssuanceContext $context,
    ) {
    }
}
```

---

### 1529. Certificate issuance policy

```php
interface CertificateIssuancePolicyInterface
{
    public function evaluate(
        CertificateIssuanceRequest $request
    ): CertificateIssuanceDecision;
}
```

---

### 1530. CertificateIssuanceDecision

```php
final readonly class CertificateIssuanceDecision
{
    public function __construct(
        public bool $allowed,
        public string $authorityId,
        public string $profileId,
        public DateInterval $approvedLifetime,
        public array $approvedNames,
        public array $requiredApprovals,
        public array $denialReasons,
    ) {
    }
}
```

---

### 1531. Proof of possession

La CA deberá verificar que el solicitante controla la clave privada correspondiente a la clave pública del CSR.

---

### 1532. IssuedCertificate

```php
final readonly class IssuedCertificate
{
    public function __construct(
        public CertificateReference $reference,
        public string $encodedCertificate,
        public array $certificateChain,
        public DateTimeImmutable $notBefore,
        public DateTimeImmutable $notAfter,
        public string $serialNumber,
        public string $fingerprint,
        public string $profileId,
    ) {
    }
}
```

---

### 1533. CertificateReference

```php
final readonly class CertificateReference
{
    public function __construct(
        public string $certificateId,
        public string $serialNumber,
        public string $authorityId,
        public string $tenantId,
    ) {
    }
}
```

---

### 1534. Certificate serial numbers

Los serial numbers deberán:

* ser únicos dentro de la CA;
* poseer entropía suficiente;
* no exponer secuencias sensibles;
* soportar indexación de revocación;
* conservarse como metadata auditable.

---

### 1535. Certificate lifetime policy

La validez deberá reducirse conforme aumente:

* sensibilidad;
* exposición;
* automatización;
* alcance;
* privilegio.

Los certificados de workload deberán tener vidas significativamente menores que certificados de infraestructura estática.

---

### 1536. Backdating restrictions

La fecha `notBefore` podrá incluir una tolerancia mínima para clock skew, pero no deberá utilizar backdating amplio.

---

### 1537. Certificate distribution

La distribución deberá:

* proteger la integridad;
* usar canales autenticados;
* evitar exponer private keys;
* confirmar identidad del receptor;
* registrar entrega.

---

### 1538. Private key generation models

VoltStack deberá soportar:

* generación local;
* generación dentro de HSM;
* generación dentro de TPM;
* generación en KMS;
* generación mediante agente de workload.

---

### 1539. Preferred private key model

Siempre que sea posible, la clave privada deberá generarse y permanecer en el componente que la utilizará.

---

### 1540. Certificate lifecycle

```php
enum CertificateLifecycleState: string
{
    case Requested = 'requested';
    case Issued = 'issued';
    case Active = 'active';
    case RenewalPending = 'renewal_pending';
    case Rotating = 'rotating';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
    case Retired = 'retired';
}
```

---

### 1541. Certificate inventory

VoltStack deberá mantener inventario de:

* certificado;
* serial;
* subject;
* SAN;
* issuer;
* owner;
* tenant;
* resource;
* purpose;
* expiration;
* state;
* key location.

---

### 1542. Certificate discovery

El sistema podrá descubrir certificados en:

* load balancers;
* web servers;
* API gateways;
* Kubernetes Secrets;
* service meshes;
* databases;
* object storage;
* repositories;
* devices.

---

### 1543. Unknown certificate detection

Todo certificado no registrado pero encontrado en infraestructura administrada deberá generar una finding.

---

### 1544. Certificate ownership

Cada certificado deberá tener:

* owner técnico;
* owner de negocio cuando aplique;
* recurso asociado;
* mecanismo de renovación;
* contacto de incidente.

---

### 1545. Certificate renewal

La renovación deberá iniciarse antes de la expiración mediante una ventana definida por policy.

---

### 1546. CertificateRenewalRequest

```php
final readonly class CertificateRenewalRequest
{
    public function __construct(
        public CertificateReference $currentCertificate,
        public CertificateSigningRequest $newCsr,
        public array $requestedNames,
        public CertificateRenewalReason $reason,
    ) {
    }
}
```

---

### 1547. CertificateRenewalReason

```php
enum CertificateRenewalReason: string
{
    case Scheduled = 'scheduled';
    case ExpirationApproaching = 'expiration_approaching';
    case KeyRotation = 'key_rotation';
    case ProfileMigration = 'profile_migration';
    case AlgorithmMigration = 'algorithm_migration';
    case IncidentResponse = 'incident_response';
}
```

---

### 1548. Renewal authorization

Renovar un certificado no deberá omitir:

* validación de identidad;
* validación de ownership;
* policy vigente;
* entitlement actual;
* SAN actuales;
* estado de la CA.

---

### 1549. Certificate key rotation

La renovación deberá preferir una nueva key pair cuando:

* el certificado expira;
* cambia de algoritmo;
* existe sospecha de exposición;
* la policy lo exige;
* el recurso cambia de owner.

---

### 1550. Overlapping certificate rotation

VoltStack deberá permitir una ventana de coexistencia para:

1. emitir nuevo certificado;
2. distribuirlo;
3. activar confianza;
4. migrar tráfico;
5. retirar el anterior;
6. validar ausencia de uso.

---

### 1551. Zero-downtime certificate rotation

La rotación automatizada deberá evitar interrupciones mediante:

* dual certificate support;
* trust overlap;
* hot reload;
* health verification;
* rollback controlado.

---

### 1552. Certificate revocation

Un certificado deberá revocarse cuando:

* la clave privada se comprometa;
* el subject deje de ser válido;
* cambie el ownership;
* se retire el workload;
* exista emisión incorrecta;
* se comprometa la CA;
* se incumpla policy.

---

### 1553. CertificateRevocationReason

```php
enum CertificateRevocationReason: string
{
    case Unspecified = 'unspecified';
    case KeyCompromise = 'key_compromise';
    case CaCompromise = 'ca_compromise';
    case AffiliationChanged = 'affiliation_changed';
    case Superseded = 'superseded';
    case CessationOfOperation = 'cessation_of_operation';
    case CertificateHold = 'certificate_hold';
    case PrivilegeWithdrawn = 'privilege_withdrawn';
    case IssuanceError = 'issuance_error';
}
```

---

### 1554. CertificateRevocationRecord

```php
final readonly class CertificateRevocationRecord
{
    public function __construct(
        public CertificateReference $certificate,
        public CertificateRevocationReason $reason,
        public DateTimeImmutable $revokedAt,
        public IdentityIdentifier|string $revokedBy,
        public ?DateTimeImmutable $invalidityDate,
    ) {
    }
}
```

---

### 1555. Revocation publication

La revocación deberá publicarse mediante mecanismos compatibles con el perfil, como:

* Certificate Revocation Lists;
* Online Certificate Status Protocol;
* internal revocation registries;
* service mesh control planes.

---

### 1556. Certificate Revocation List

```php
final readonly class CertificateRevocationList
{
    public function __construct(
        public string $issuerId,
        public int $crlNumber,
        public DateTimeImmutable $thisUpdate,
        public DateTimeImmutable $nextUpdate,
        public array $revokedCertificates,
        public DigitalSignature $signature,
    ) {
    }
}
```

---

### 1557. CRL generation

Las CRLs deberán:

* firmarse;
* versionarse;
* incluir número secuencial;
* publicarse de forma autenticada;
* regenerarse al ocurrir revocaciones críticas;
* mantener intervalos apropiados.

---

### 1558. Delta CRLs

VoltStack podrá soportar delta CRLs para reducir el tamaño de actualizaciones frecuentes.

---

### 1559. OCSP architecture

```php
interface OcspResponderInterface
{
    public function respond(
        OcspRequest $request
    ): OcspResponse;
}
```

---

### 1560. OCSP response states

```php
enum OcspCertificateStatus: string
{
    case Good = 'good';
    case Revoked = 'revoked';
    case Unknown = 'unknown';
}
```

---

### 1561. OCSP responder security

El responder deberá:

* firmar respuestas;
* limitar replay;
* usar tiempos de validez cortos;
* proteger su signing key;
* registrar consultas anómalas;
* evitar filtrar metadata innecesaria.

---

### 1562. OCSP stapling

Para servicios TLS, VoltStack deberá favorecer OCSP stapling cuando el entorno y los clientes lo soporten.

---

### 1563. Revocation checking policy

```php
enum RevocationCheckingMode: string
{
    case Required = 'required';
    case SoftFail = 'soft_fail';
    case CachedOnly = 'cached_only';
    case Disabled = 'disabled';
}
```

---

### 1564. Revocation fail behavior

Para certificados privilegiados o internos de alto riesgo, la indisponibilidad del mecanismo de revocación deberá tender a fail-closed.

---

### 1565. Certificate validation pipeline

```text
Parse Certificate
      ↓
Validate Encoding
      ↓
Build Trust Path
      ↓
Validate Signatures
      ↓
Validate Time
      ↓
Validate Basic Constraints
      ↓
Validate Key Usage and EKU
      ↓
Validate Names
      ↓
Validate Policies
      ↓
Check Revocation
      ↓
Bind to Runtime Identity
```

---

### 1566. CertificateValidationContext

```php
final readonly class CertificateValidationContext
{
    public function __construct(
        public string $tenantId,
        public CertificatePurpose $expectedPurpose,
        public array $expectedNames,
        public string $trustStoreId,
        public RevocationCheckingMode $revocationMode,
        public DateTimeImmutable $validationTime,
    ) {
    }
}
```

---

### 1567. CertificateValidationResult

```php
final readonly class CertificateValidationResult
{
    public function __construct(
        public CertificateValidationStatus $status,
        public ?CertificateIdentity $identity,
        public array $validatedChain,
        public array $warnings,
        public array $failures,
    ) {
    }
}
```

---

### 1568. CertificateValidationStatus

```php
enum CertificateValidationStatus: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Untrusted = 'untrusted';
    case PolicyRejected = 'policy_rejected';
    case Indeterminate = 'indeterminate';
}
```

---

### 1569. Trust store architecture

VoltStack deberá administrar trust stores como recursos versionados y gobernados.

---

### 1570. TrustStore

```php
final readonly class TrustStore
{
    public function __construct(
        public string $trustStoreId,
        public string $tenantId,
        public string $environment,
        public array $trustAnchors,
        public array $intermediates,
        public int $version,
        public TrustStoreState $state,
    ) {
    }
}
```

---

### 1571. TrustStoreState

```php
enum TrustStoreState: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Rotating = 'rotating';
    case Suspended = 'suspended';
    case Retired = 'retired';
}
```

---

### 1572. Trust anchor governance

Agregar o retirar un trust anchor deberá requerir:

* aprobación;
* validación de fingerprint;
* verificación fuera de banda;
* evaluación de impacto;
* versión nueva;
* auditoría.

---

### 1573. Trust anchor pinning

Los trust anchors críticos deberán identificarse mediante fingerprints esperados y metadata protegida.

---

### 1574. Trust store isolation

Deberán existir trust stores separados para:

* producción;
* desarrollo;
* test;
* tenants;
* workloads;
* integraciones externas;
* code signing.

---

### 1575. Cross-environment trust prohibition

Producción no deberá confiar automáticamente en CAs de desarrollo o testing.

---

### 1576. Trust anchor rotation

La rotación deberá permitir coexistencia temporal entre:

* anchor anterior;
* anchor nuevo;
* cadenas emitidas por ambas jerarquías.

---

### 1577. Trust rotation stages

```text
Create New Trust Anchor
      ↓
Distribute New Anchor
      ↓
Enable Dual Trust
      ↓
Issue from New Hierarchy
      ↓
Migrate Certificates
      ↓
Remove Old Anchor
      ↓
Monitor Validation Failures
```

---

### 1578. Trust store rollback

Toda actualización deberá conservar capacidad de rollback mientras no exista evidencia de compromiso del anchor anterior.

---

### 1579. Mutual TLS architecture

VoltStack deberá soportar mTLS para autenticar ambos extremos de una conexión.

---

### 1580. MutualTlsIdentity

```php
final readonly class MutualTlsIdentity
{
    public function __construct(
        public string $certificateFingerprint,
        public string $subject,
        public array $subjectAlternativeNames,
        public string $issuer,
        public string $trustDomain,
        public string $tenantId,
    ) {
    }
}
```

---

### 1581. mTLS authentication flow

```text
TLS Handshake
      ↓
Peer Certificate Presentation
      ↓
Chain Validation
      ↓
Revocation Validation
      ↓
Name and Purpose Validation
      ↓
Certificate-to-Identity Mapping
      ↓
Authorization Policy
      ↓
Secure Channel Established
```

---

### 1582. Client certificate authentication

Un certificado cliente deberá mapearse a una identidad mediante datos estables y gobernados.

---

### 1583. ClientCertificateIdentityMapper

```php
interface ClientCertificateIdentityMapperInterface
{
    public function map(
        PresentedCertificate $certificate,
        CertificateValidationResult $validation
    ): ClientCertificateIdentityMappingResult;
}
```

---

### 1584. Unsafe certificate mapping prohibition

No deberá mapearse una identidad únicamente mediante:

* Common Name ambiguo;
* display name;
* email no verificado;
* subject string parcial;
* issuer no confiable.

---

### 1585. Certificate-to-identity binding

El binding deberá considerar:

* issuer;
* serial;
* SAN URI;
* trust domain;
* certificate profile;
* tenant;
* lifecycle state.

---

### 1586. Service-to-service authentication

Los servicios internos deberán autenticarse mediante identidades propias y no compartir credenciales humanas.

---

### 1587. ServiceIdentity

```php
final readonly class ServiceIdentity
{
    public function __construct(
        public string $serviceIdentityId,
        public string $tenantId,
        public string $serviceName,
        public string $environment,
        public string $trustDomain,
        public array $allowedAudiences,
    ) {
    }
}
```

---

### 1588. Service identity authorization

La validación del certificado deberá completarse con autorización basada en:

* servicio;
* namespace;
* tenant;
* environment;
* operation;
* destination;
* audience.

---

### 1589. SPIFFE foundations

VoltStack podrá soportar SPIFFE para representar identidades de workloads mediante URI SAN.

Formato conceptual:

```text
spiffe://trust-domain/path/to/workload
```

---

### 1590. SpiffeIdentity

```php
final readonly class SpiffeIdentity
{
    public function __construct(
        public string $trustDomain,
        public string $path,
        public string $spiffeId,
        public string $tenantId,
        public array $selectors,
    ) {
    }
}
```

---

### 1591. SPIFFE ID validation

Un SPIFFE ID deberá validar:

* esquema URI;
* trust domain;
* path autorizado;
* tenant binding;
* selector mapping;
* workload state.

---

### 1592. SPIRE integration foundations

VoltStack podrá integrarse con SPIRE para:

* node attestation;
* workload attestation;
* SVID issuance;
* trust bundle distribution;
* rotation;
* workload identity discovery.

---

### 1593. Workload attestation

Antes de emitir identidad, el sistema deberá validar atributos como:

* cluster;
* namespace;
* service account;
* process;
* node;
* image;
* deployment;
* environment.

---

### 1594. Workload certificate

```php
final readonly class WorkloadCertificate
{
    public function __construct(
        public string $spiffeId,
        public IssuedCertificate $certificate,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public array $attestationEvidence,
    ) {
    }
}
```

---

### 1595. Short-lived workload certificates

Los certificados de workload deberán:

* tener vida corta;
* rotarse automáticamente;
* no depender de renovación manual;
* utilizar claves efímeras cuando sea viable;
* revocarse al desaparecer el workload.

---

### 1596. Certificate pinning

El pinning podrá emplearse en contextos controlados, pero deberá diseñarse con:

* múltiples pins;
* backup keys;
* fecha de expiración;
* rotation plan;
* recovery mechanism;
* observabilidad.

El pinning rígido sin plan de rotación deberá evitarse.

---

### 1597. Code signing certificates

Los certificados de firma de código deberán:

* usar claves protegidas por hardware;
* requerir autorización reforzada;
* limitarse a pipelines confiables;
* registrar cada firma;
* prohibir exportación de clave;
* soportar revocación rápida.

---

### 1598. Timestamping authority

Una Timestamping Authority deberá permitir demostrar que una firma existía durante la validez del certificado firmante.

```php
interface TimestampingAuthorityInterface
{
    public function timestamp(
        string $artifactDigest,
        TimestampingContext $context
    ): TrustedTimestampToken;
}
```

---

### 1599. PKI audit events

Eventos recomendados:

* `CertificateRequested`;
* `CertificateIssued`;
* `CertificateRenewed`;
* `CertificateRotationStarted`;
* `CertificateRotationCompleted`;
* `CertificateRevoked`;
* `CertificateExpired`;
* `CertificateValidationFailed`;
* `UnknownCertificateDetected`;
* `CertificateAuthorityActivated`;
* `CertificateAuthorityCompromised`;
* `TrustAnchorAdded`;
* `TrustAnchorRemoved`;
* `TrustStoreUpdated`;
* `MutualTlsAuthenticationSucceeded`;
* `MutualTlsAuthenticationFailed`;
* `WorkloadCertificateIssued`;
* `WorkloadAttestationFailed`;
* `CodeArtifactSigned`;
* `TrustedTimestampIssued`.

---

### 1600. Resultado de esta entrega

Esta entrega establece:

```text
Public Key Infrastructure Architecture
Certificate Authority Hierarchies
Offline Root Authorities
Intermediate and Issuing CAs
CA Purpose Separation
CA Key Protection
Certificate Profiles
Key Usage and Extended Key Usage
Certificate Signing Requests
Proof of Possession
Subject and SAN Validation
Certificate Issuance Policies
Certificate Inventory
Certificate Ownership
Certificate Renewal
Certificate Key Rotation
Zero-Downtime Certificate Rotation
Certificate Revocation
CRL Architecture
OCSP Architecture
Revocation Checking Policies
Certificate Validation Pipeline
Trust Store Architecture
Trust Anchor Governance
Trust Anchor Rotation
Mutual TLS Authentication
Client Certificate Identity Mapping
Service-to-Service Authentication
SPIFFE Identity Foundations
SPIRE Integration Foundations
Workload Attestation
Short-Lived Workload Certificates
Certificate Pinning Governance
Code Signing Certificates
Timestamping Authorities
PKI Audit Events
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 17

- Secrets management architecture
- Secret classification
- Secret stores
- Secret references
- Dynamic secrets
- Secret leasing
- Secret injection
- Secret zeroization
- Secret scanning
- Secret exposure detection
- Repository secret protection
- CI/CD secret security
- Environment variable risks
- Configuration secret separation
- Secret rotation orchestration
- Secret revocation
- Secret recovery
- Backup secret protection
- Secret access auditing
- Secret governance
```

## Entrega 17

**Documento:** Parte 05
**Entrega:** 17 de varias
**Cobertura:** Secciones **1601–1700**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 16`

---

### 1601. Secrets Management Architecture

VoltStack deberá incorporar una arquitectura centralizada de gestión de secretos para proteger:

* contraseñas de infraestructura;
* API keys;
* tokens de integración;
* claves privadas;
* credenciales de bases de datos;
* certificados y material asociado;
* secretos de CI/CD;
* credenciales de service accounts;
* secretos de workloads;
* material de recuperación.

Los secretos no deberán administrarse como configuración ordinaria.

---

### 1602. Secrets management security goals

La arquitectura deberá garantizar:

* mínima exposición;
* acceso por referencia;
* entrega temporal;
* rotación;
* revocación;
* separación por tenant;
* trazabilidad;
* clasificación;
* detección de filtraciones;
* recuperación controlada.

---

### 1603. Secrets threat model

El modelo deberá considerar:

* secretos hardcoded;
* exposición en logs;
* filtración en repositorios;
* variables de entorno visibles;
* secretos persistidos en imágenes;
* credenciales compartidas;
* acceso excesivo;
* secretos huérfanos;
* rotación fallida;
* backup inseguro;
* lectura desde memoria;
* inyección en procesos no autorizados;
* abuso interno;
* acceso cross-tenant.

---

### 1604. Secrets architecture components

```text
Secret Producer
      ↓
Secret Classification
      ↓
Secret Policy Evaluation
      ↓
Secret Store
      ↓
Opaque Reference
      ↓
Lease or Injection
      ↓
Usage Monitoring
      ↓
Rotation / Revocation
      ↓
Audit and Exposure Detection
```

---

### 1605. SecretsManagementService

```php
interface SecretsManagementServiceInterface
{
    public function store(
        SecretMaterial $secret,
        SecretStorageContext $context
    ): SecretReference;

    public function resolve(
        SecretReference $reference,
        SecretAccessContext $context
    ): SecretLease;

    public function rotate(
        SecretReference $reference,
        SecretRotationPolicy $policy
    ): SecretRotationResult;

    public function revoke(
        SecretReference $reference,
        SecretRevocationReason $reason
    ): SecretRevocationResult;
}
```

---

### 1606. SecretMaterial

```php
final readonly class SecretMaterial
{
    public function __construct(
        public SensitiveValue $value,
        public SecretType $type,
        public SecretClassification $classification,
        public array $metadata,
    ) {
    }
}
```

---

### 1607. SecretType

```php
enum SecretType: string
{
    case Password = 'password';
    case ApiKey = 'api_key';
    case AccessToken = 'access_token';
    case RefreshToken = 'refresh_token';
    case PrivateKey = 'private_key';
    case CertificateBundle = 'certificate_bundle';
    case DatabaseCredential = 'database_credential';
    case SshCredential = 'ssh_credential';
    case WebhookSecret = 'webhook_secret';
    case RecoverySecret = 'recovery_secret';
    case EncryptionKeyMaterial = 'encryption_key_material';
}
```

---

### 1608. Secret classification

Toda entrada deberá clasificarse antes de almacenarse.

---

### 1609. SecretClassification

```php
enum SecretClassification: string
{
    case Internal = 'internal';
    case Sensitive = 'sensitive';
    case Restricted = 'restricted';
    case Privileged = 'privileged';
    case Critical = 'critical';
}
```

---

### 1610. Classification factors

La clasificación deberá considerar:

* impacto de exposición;
* alcance;
* privilegios;
* tenant;
* entorno;
* capacidad de rotación;
* uso humano o no humano;
* regulación;
* persistencia.

---

### 1611. Secret ownership

Todo secreto deberá tener:

* owner técnico;
* owner de negocio cuando aplique;
* tenant;
* propósito;
* consumidor;
* sistema origen;
* política de rotación;
* fecha de revisión.

---

### 1612. SecretRecord

```php
final readonly class SecretRecord
{
    public function __construct(
        public string $secretId,
        public string $tenantId,
        public SecretType $type,
        public SecretClassification $classification,
        public IdentityIdentifier|string $owner,
        public string $purpose,
        public SecretLifecycleState $state,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 1613. SecretLifecycleState

```php
enum SecretLifecycleState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rotating = 'rotating';
    case Expiring = 'expiring';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
    case Retired = 'retired';
    case Destroyed = 'destroyed';
}
```

---

### 1614. Secret store abstraction

VoltStack deberá soportar múltiples proveedores sin acoplar el dominio a uno específico.

---

### 1615. SecretStore

```php
interface SecretStoreInterface
{
    public function put(
        SecretMaterial $secret,
        SecretStoreContext $context
    ): SecretReference;

    public function lease(
        SecretReference $reference,
        SecretLeaseContext $context
    ): SecretLease;

    public function delete(
        SecretReference $reference
    ): void;
}
```

---

### 1616. Supported secret stores

La arquitectura podrá integrar:

* Vault;
* cloud secret managers;
* KMS-backed stores;
* HSM-backed stores;
* operating system keyrings;
* Kubernetes secret providers;
* encrypted local development stores.

---

### 1617. Secret store selection policy

La selección deberá considerar:

* entorno;
* tenant;
* clasificación;
* latencia;
* disponibilidad;
* región;
* compliance;
* hardware protection;
* rotation support.

---

### 1618. SecretReference

```php
final readonly class SecretReference
{
    public function __construct(
        public string $secretId,
        public string $provider,
        public string $tenantId,
        public ?string $versionId,
        public string $purpose,
    ) {
    }
}
```

---

### 1619. Opaque secret references

La referencia no deberá contener:

* valor secreto;
* credencial embebida;
* token;
* información reversible;
* metadatos innecesarios.

---

### 1620. Reference-only domain model

Controllers, Commands y Services deberán transportar referencias y no secretos cuando no sea indispensable resolverlos.

---

### 1621. Secret access policy

```php
interface SecretAccessPolicyInterface
{
    public function evaluate(
        SecretReference $reference,
        SecretAccessContext $context
    ): SecretAccessDecision;
}
```

---

### 1622. SecretAccessDecision

```php
final readonly class SecretAccessDecision
{
    public function __construct(
        public bool $allowed,
        public DateInterval $maximumLeaseDuration,
        public bool $stepUpRequired,
        public bool $deviceTrustRequired,
        public bool $approvalRequired,
        public array $restrictions,
        public array $denialReasons,
    ) {
    }
}
```

---

### 1623. Secret access context

La evaluación deberá considerar:

* identidad;
* workload;
* tenant;
* propósito;
* recurso;
* entorno;
* assurance;
* device posture;
* horario;
* riesgo;
* ticket;
* sesión privilegiada.

---

### 1624. Least privilege secret access

El acceso deberá limitarse por:

* secreto;
* versión;
* operación;
* duración;
* entorno;
* workload;
* audiencia;
* recurso.

---

### 1625. Secret leasing

La entrega preferida será mediante leases de corta duración.

---

### 1626. SecretLease

```php
final readonly class SecretLease
{
    public function __construct(
        public string $leaseId,
        public SecretReference $reference,
        public SensitiveValue $value,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public SecretLeaseState $state,
        public array $restrictions,
    ) {
    }
}
```

---

### 1627. SecretLeaseState

```php
enum SecretLeaseState: string
{
    case Active = 'active';
    case Renewing = 'renewing';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Returned = 'returned';
}
```

---

### 1628. Lease duration

La duración deberá ser la mínima compatible con el caso de uso.

---

### 1629. Lease renewal

La renovación deberá reevaluar:

* identidad;
* workload;
* riesgo;
* propósito;
* estado del secreto;
* entitlement;
* tenant;
* policy vigente.

---

### 1630. Lease revocation

VoltStack deberá poder revocar un lease sin esperar su expiración natural.

---

### 1631. Dynamic secrets

El framework deberá favorecer secretos generados bajo demanda.

Ejemplos:

* credenciales temporales de base de datos;
* tokens cloud;
* certificados cortos;
* credenciales SSH efímeras;
* tokens de acceso scoped.

---

### 1632. DynamicSecretProvider

```php
interface DynamicSecretProviderInterface
{
    public function issue(
        DynamicSecretRequest $request
    ): DynamicSecretLease;

    public function revoke(
        DynamicSecretLease $lease
    ): void;
}
```

---

### 1633. DynamicSecretRequest

```php
final readonly class DynamicSecretRequest
{
    public function __construct(
        public string $tenantId,
        public IdentityIdentifier|string $subject,
        public string $resourceId,
        public array $requestedCapabilities,
        public DateInterval $requestedLifetime,
        public DynamicSecretContext $context,
    ) {
    }
}
```

---

### 1634. Dynamic secret advantages

Los secretos dinámicos reducen:

* reutilización;
* exposición prolongada;
* distribución manual;
* credenciales compartidas;
* impacto de filtración.

---

### 1635. Dynamic database credentials

Las credenciales de base de datos dinámicas deberán:

* crear principal temporal;
* limitar permisos;
* limitar esquema;
* limitar duración;
* revocarse al expirar;
* registrar consultas administrativas cuando aplique.

---

### 1636. Dynamic cloud credentials

Las credenciales cloud temporales deberán limitar:

* account;
* role;
* region;
* service;
* resource;
* action;
* duration.

---

### 1637. Secret injection architecture

VoltStack deberá permitir inyectar secretos sin persistirlos innecesariamente.

---

### 1638. SecretInjectionService

```php
interface SecretInjectionServiceInterface
{
    public function inject(
        SecretReference $reference,
        SecretInjectionTarget $target,
        SecretInjectionContext $context
    ): SecretInjectionResult;
}
```

---

### 1639. Secret injection targets

Se podrá inyectar en:

* proceso;
* archivo temporal;
* memory-backed filesystem;
* sidecar;
* service mesh;
* runtime container;
* socket;
* API request;
* database connection factory.

---

### 1640. Preferred injection models

Orden recomendado:

1. brokered use sin exposición;
2. in-memory injection;
3. ephemeral file;
4. environment variable solo cuando sea necesario.

---

### 1641. Brokered secret usage

Cuando sea posible, el consumidor deberá pedir la operación y no recibir directamente el secreto.

---

### 1642. Environment variable risks

Las variables de entorno pueden exponerse mediante:

* process inspection;
* crash dumps;
* debug tools;
* logs;
* child processes;
* platform metadata;
* accidental diagnostics.

---

### 1643. Environment variable policy

Su uso deberá:

* ser explícito;
* limitarse por entorno;
* evitar secretos críticos cuando existan alternativas;
* impedir logging;
* limpiar el entorno de procesos hijos;
* documentar la exposición.

---

### 1644. Ephemeral secret files

Los archivos temporales deberán:

* almacenarse en filesystem en memoria cuando sea viable;
* usar permisos mínimos;
* tener nombre no predecible;
* eliminarse al finalizar;
* no incluirse en backups;
* no persistirse en imágenes.

---

### 1645. Secret zeroization

VoltStack deberá minimizar el tiempo durante el cual un secreto permanece en memoria.

---

### 1646. SecretZeroizer

```php
interface SecretZeroizerInterface
{
    public function zeroize(
        SensitiveValue $value
    ): void;
}
```

---

### 1647. Zeroization limitations

PHP no garantiza control absoluto sobre copias internas, garbage collection o optimizaciones del runtime.

Por ello, VoltStack deberá complementar zeroization con:

* vida útil corta;
* menor número de copias;
* tipos sensibles;
* aislamiento de proceso;
* brokered operations;
* no serialización.

---

### 1648. SensitiveValue restrictions

`SensitiveValue` deberá:

* impedir conversión implícita a string;
* prohibir serialización;
* redactar `var_dump`;
* evitar inclusión en excepciones;
* exigir acceso explícito;
* limitar clonación.

---

### 1649. Secret redaction

VoltStack deberá registrar patrones conocidos para redactar secretos en:

* logs;
* traces;
* exceptions;
* profiler;
* debug output;
* HTTP dumps;
* job payloads.

---

### 1650. SecretRedactionService

```php
interface SecretRedactionServiceInterface
{
    public function redact(
        string|array $payload,
        SecretRedactionContext $context
    ): string|array;
}
```

---

### 1651. Redaction strategies

Se podrán utilizar:

* key-name matching;
* schema metadata;
* token pattern detection;
* exact secret fingerprints;
* structured field policies;
* entropy heuristics.

---

### 1652. Redaction false confidence

La redacción no deberá considerarse sustituto de evitar que el secreto llegue al sistema de logging.

---

### 1653. Configuration and secret separation

La configuración deberá referenciar secretos, no contenerlos.

Ejemplo recomendado:

```php
return [
    'database' => [
        'password' => Secret::reference('database.production.password'),
    ],
];
```

---

### 1654. Secret configuration resolver

```php
interface SecretConfigurationResolverInterface
{
    public function resolve(
        SecretReference $reference,
        ConfigurationResolutionContext $context
    ): SecretLease;
}
```

---

### 1655. Configuration cache restrictions

Un secreto resuelto no deberá persistirse en:

* cache de configuración;
* archivos compilados;
* manifests;
* OPcache preload files;
* build artifacts.

---

### 1656. Build-time versus runtime secrets

VoltStack deberá distinguir:

* secretos necesarios durante build;
* secretos necesarios durante deploy;
* secretos necesarios en runtime.

---

### 1657. Build secret policy

Un secreto de build deberá:

* montarse temporalmente;
* no copiarse a capas;
* no quedar en historial;
* revocarse después;
* limitarse al job.

---

### 1658. Container image protection

No deberán incluirse secretos en:

* Dockerfile;
* image layers;
* image labels;
* build arguments persistentes;
* package metadata;
* startup scripts.

---

### 1659. CI/CD secret security

Los pipelines deberán:

* usar identidades federadas;
* evitar credenciales permanentes;
* limitar logs;
* restringir forks;
* separar entornos;
* aplicar approvals;
* rotar secretos tras incidentes.

---

### 1660. CiCdSecretPolicy

```php
final readonly class CiCdSecretPolicy
{
    public function __construct(
        public bool $federationPreferred,
        public DateInterval $maximumLeaseDuration,
        public bool $protectedBranchRequired,
        public bool $approvalRequired,
        public bool $forkAccessDenied,
        public array $allowedPipelines,
    ) {
    }
}
```

---

### 1661. Pull request secret protection

Pipelines originados desde código no confiable no deberán recibir secretos privilegiados.

---

### 1662. Environment separation

Los secretos de producción deberán estar aislados de:

* desarrollo;
* testing;
* preview environments;
* forks;
* personal sandboxes.

---

### 1663. Repository secret protection

VoltStack deberá incluir controles para impedir secretos en repositorios.

---

### 1664. RepositorySecretScanner

```php
interface RepositorySecretScannerInterface
{
    public function scan(
        RepositorySnapshot $snapshot,
        SecretScanningPolicy $policy
    ): SecretScanningReport;
}
```

---

### 1665. Secret scanning scopes

El scanner deberá analizar:

* working tree;
* staged changes;
* commits;
* branches;
* tags;
* pull requests;
* release artifacts;
* generated files;
* Git history.

---

### 1666. Secret detection techniques

Se deberán combinar:

* patrones;
* prefijos conocidos;
* checksums;
* entropy analysis;
* provider validation;
* contextual heuristics;
* exact fingerprint matching.

---

### 1667. SecretScanningFinding

```php
final readonly class SecretScanningFinding
{
    public function __construct(
        public string $findingId,
        public SecretFindingType $type,
        public string $location,
        public int $line,
        public SecretFindingSeverity $severity,
        public string $fingerprint,
        public bool $validated,
    ) {
    }
}
```

---

### 1668. SecretFindingSeverity

```php
enum SecretFindingSeverity: string
{
    case Informational = 'informational';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
```

---

### 1669. Validated secret detection

Cuando sea seguro, el sistema podrá validar con el proveedor si una credencial sigue activa.

La validación no deberá aumentar la exposición.

---

### 1670. Pre-commit scanning

VoltStack deberá ofrecer integración para bloquear commits que contengan secretos.

---

### 1671. Server-side repository scanning

La protección no deberá depender únicamente del desarrollador local.

---

### 1672. Historical secret exposure

Eliminar un secreto del commit más reciente no elimina su exposición histórica.

---

### 1673. Repository exposure response

Ante una filtración deberán ejecutarse:

1. revocar o rotar;
2. identificar uso;
3. preservar evidencia;
4. limpiar historial cuando corresponda;
5. actualizar consumidores;
6. revisar logs;
7. cerrar incidente.

---

### 1674. Secret exposure detection

VoltStack deberá detectar exposición en:

* repositorios;
* logs;
* traces;
* tickets;
* chat;
* artifacts;
* object storage;
* container registries;
* backups;
* dumps.

---

### 1675. SecretExposureDetector

```php
interface SecretExposureDetectorInterface
{
    public function analyze(
        ExposureSource $source,
        SecretExposureDetectionContext $context
    ): SecretExposureReport;
}
```

---

### 1676. Secret fingerprinting

El sistema podrá mantener fingerprints no reversibles para identificar secretos expuestos sin conservar el plaintext.

---

### 1677. Fingerprint key separation

Los fingerprints keyed deberán utilizar una clave dedicada, separada de cifrado y MAC de aplicaciones.

---

### 1678. Secret exposure event

```php
final readonly class SecretExposureEvent
{
    public function __construct(
        public string $eventId,
        public ?SecretReference $secret,
        public string $source,
        public SecretExposureSeverity $severity,
        public DateTimeImmutable $detectedAt,
        public array $evidence,
    ) {
    }
}
```

---

### 1679. Exposure severity

La severidad deberá considerar:

* secreto activo;
* privilegio;
* alcance;
* entorno;
* visibilidad;
* tiempo expuesto;
* uso detectado;
* capacidad de revocación.

---

### 1680. Automated secret containment

Para findings de alta confianza, el sistema podrá:

* revocar;
* rotar;
* deshabilitar integración;
* bloquear pipeline;
* suspender workload;
* abrir incidente;
* notificar owner.

---

### 1681. Secret rotation orchestration

La rotación deberá coordinar productor, store y consumidores.

---

### 1682. SecretRotationOrchestrator

```php
interface SecretRotationOrchestratorInterface
{
    public function plan(
        SecretReference $reference,
        SecretRotationContext $context
    ): SecretRotationPlan;

    public function execute(
        SecretRotationPlan $plan
    ): SecretRotationResult;
}
```

---

### 1683. SecretRotationPlan

```php
final readonly class SecretRotationPlan
{
    public function __construct(
        public string $planId,
        public SecretReference $currentSecret,
        public array $consumers,
        public array $stages,
        public DateTimeImmutable $scheduledAt,
        public SecretRotationStrategy $strategy,
    ) {
    }
}
```

---

### 1684. SecretRotationStrategy

```php
enum SecretRotationStrategy: string
{
    case ImmediateCutover = 'immediate_cutover';
    case DualVersion = 'dual_version';
    case Rolling = 'rolling';
    case ConsumerByConsumer = 'consumer_by_consumer';
    case Emergency = 'emergency';
}
```

---

### 1685. Dual-secret rotation

Cuando el sistema lo permita:

1. crear nueva versión;
2. habilitar ambas;
3. actualizar consumidores;
4. verificar adopción;
5. deshabilitar anterior;
6. revocar anterior.

---

### 1686. Rotation dependency graph

VoltStack deberá conocer qué consumidores dependen de cada secreto.

---

### 1687. SecretConsumerBinding

```php
final readonly class SecretConsumerBinding
{
    public function __construct(
        public string $consumerId,
        public SecretReference $secret,
        public string $environment,
        public string $tenantId,
        public SecretConsumptionMode $mode,
        public DateTimeImmutable $lastObservedUse,
    ) {
    }
}
```

---

### 1688. SecretConsumptionMode

```php
enum SecretConsumptionMode: string
{
    case DirectRead = 'direct_read';
    case Lease = 'lease';
    case Injection = 'injection';
    case Brokered = 'brokered';
    case Federated = 'federated';
}
```

---

### 1689. Rotation verification

Una rotación no deberá marcarse como completa hasta validar:

* nuevos consumidores;
* ausencia de errores;
* ausencia de uso de versión antigua;
* revocación efectiva;
* actualización de inventario.

---

### 1690. Secret revocation

La revocación deberá:

* invalidar nuevos accesos;
* cancelar leases;
* notificar consumidores;
* actualizar estado;
* generar evidencia;
* iniciar rotación cuando corresponda.

---

### 1691. SecretRevocationReason

```php
enum SecretRevocationReason: string
{
    case Compromise = 'compromise';
    case Exposure = 'exposure';
    case OwnerChanged = 'owner_changed';
    case ConsumerRetired = 'consumer_retired';
    case PolicyViolation = 'policy_violation';
    case ScheduledRetirement = 'scheduled_retirement';
    case ProviderRevocation = 'provider_revocation';
}
```

---

### 1692. Secret recovery

La recuperación deberá diseñarse para evitar que el mecanismo de contingencia se convierta en una puerta trasera.

---

### 1693. SecretRecoveryPolicy

```php
final readonly class SecretRecoveryPolicy
{
    public function __construct(
        public int $requiredApprovers,
        public bool $dualControlRequired,
        public bool $hardwareTokenRequired,
        public bool $incidentRequired,
        public DateInterval $recoveryAccessLifetime,
    ) {
    }
}
```

---

### 1694. Recovery controls

La recuperación podrá requerir:

* quorum;
* break-glass;
* identidad fuerte;
* dispositivo administrado;
* ticket;
* sesión grabada;
* acceso temporal;
* revisión posterior.

---

### 1695. Backup secret protection

Los backups deberán proteger:

* secretos almacenados;
* metadata;
* claves de wrapping;
* índices;
* audit trails;
* recovery material.

---

### 1696. Backup isolation

Los backups de secretos deberán:

* cifrarse con claves separadas;
* almacenarse en dominio distinto;
* limitar acceso;
* probar restauración;
* aplicar retención;
* registrar lecturas.

---

### 1697. Secret access auditing

Toda lectura o uso deberá registrar:

* actor;
* workload;
* secreto;
* versión;
* propósito;
* recurso;
* lease;
* timestamp;
* decisión;
* resultado.

El valor secreto nunca deberá incluirse en el evento.

---

### 1698. Secret governance

VoltStack deberá mantener inventario y métricas de:

* secretos activos;
* owners;
* rotaciones vencidas;
* secretos sin consumidor;
* secretos sin owner;
* exposiciones;
* leases;
* accesos denegados;
* credenciales estáticas;
* secretos de alta antigüedad.

---

### 1699. Secrets audit events

Eventos recomendados:

* `SecretStored`;
* `SecretAccessRequested`;
* `SecretAccessGranted`;
* `SecretAccessDenied`;
* `SecretLeaseIssued`;
* `SecretLeaseRenewed`;
* `SecretLeaseRevoked`;
* `DynamicSecretIssued`;
* `SecretInjected`;
* `SecretRotationStarted`;
* `SecretRotationCompleted`;
* `SecretRotationFailed`;
* `SecretRevoked`;
* `SecretCompromised`;
* `SecretExposureDetected`;
* `RepositorySecretDetected`;
* `SecretRecoveryInitiated`;
* `SecretRecoveryCompleted`;
* `OrphanSecretDetected`;
* `SecretDestroyed`.

---

### 1700. Resultado de esta entrega

Esta entrega establece:

```text
Secrets Management Architecture
Secret Classification
Secret Ownership
Secret Lifecycle
Secret Store Abstraction
Opaque Secret References
Reference-Only Domain Model
Secret Access Policies
Secret Leasing
Dynamic Secrets
Dynamic Database Credentials
Dynamic Cloud Credentials
Secret Injection
Brokered Secret Usage
Environment Variable Risk Controls
Ephemeral Secret Files
Secret Zeroization
Sensitive Value Restrictions
Secret Redaction
Configuration and Secret Separation
Build-Time and Runtime Secret Separation
Container Image Secret Protection
CI/CD Secret Security
Pull Request Secret Protection
Environment Isolation
Repository Secret Scanning
Historical Exposure Response
Secret Exposure Detection
Secret Fingerprinting
Automated Secret Containment
Secret Rotation Orchestration
Rotation Dependency Graph
Dual-Version Rotation
Rotation Verification
Secret Revocation
Secret Recovery
Backup Secret Protection
Secret Access Auditing
Secret Governance
Secrets Audit Events
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 18

- Security token architecture
- Token classification
- Token formats
- Opaque tokens
- Structured tokens
- JWT governance
- Token claims policies
- Token audience restriction
- Token issuer validation
- Token lifetime policies
- Token binding
- Proof-of-possession tokens
- Token exchange
- Delegation tokens
- Impersonation tokens
- Downscoping
- Token revocation
- Token replay protection
- Token introspection
- Token audit and governance
```

## Entrega 18

**Documento:** Parte 05
**Entrega:** 18 de varias
**Cobertura:** Secciones **1701–1800**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 17`

---

### 1701. Security Token Architecture

VoltStack deberá incorporar una arquitectura centralizada para emisión, validación, intercambio, restricción, revocación y auditoría de tokens de seguridad.

Esta arquitectura deberá cubrir:

* access tokens;
* refresh tokens;
* identity tokens;
* session tokens;
* delegation tokens;
* impersonation tokens;
* proof-of-possession tokens;
* service tokens;
* workload tokens;
* one-time tokens;
* recovery tokens.

Los Controllers no deberán construir ni validar tokens directamente.

---

### 1702. Token security goals

La arquitectura deberá garantizar:

* autenticidad;
* integridad;
* audiencia restringida;
* issuer confiable;
* lifetime mínimo;
* mínima exposición;
* revocación;
* replay resistance;
* scope mínimo;
* trazabilidad;
* separación entre tipos de token;
* crypto-agility.

---

### 1703. Token threat model

El modelo deberá considerar:

* robo de bearer tokens;
* token replay;
* token substitution;
* audience confusion;
* issuer confusion;
* algorithm confusion;
* key confusion;
* claim injection;
* excessive lifetime;
* excessive scope;
* token leakage;
* token chaining abuse;
* impersonation abuse;
* delegation escalation;
* refresh token reuse;
* cross-tenant token acceptance;
* introspection bypass.

---

### 1704. Token architecture components

```text
Token Request
      ↓
Subject and Actor Resolution
      ↓
Token Policy Evaluation
      ↓
Claims Construction
      ↓
Scope and Audience Reduction
      ↓
Signing or Opaque Token Issuance
      ↓
Token Registry
      ↓
Validation / Introspection
      ↓
Revocation / Expiration
      ↓
Audit and Analytics
```

---

### 1705. SecurityTokenService

```php
interface SecurityTokenServiceInterface
{
    public function issue(
        TokenIssuanceRequest $request
    ): IssuedSecurityToken;

    public function validate(
        PresentedSecurityToken $token,
        TokenValidationContext $context
    ): TokenValidationResult;

    public function revoke(
        TokenReference $token,
        TokenRevocationReason $reason
    ): TokenRevocationResult;

    public function exchange(
        TokenExchangeRequest $request
    ): TokenExchangeResult;
}
```

---

### 1706. Token classification

Todo token deberá clasificarse por:

* purpose;
* format;
* bearer model;
* subject type;
* audience;
* lifetime;
* revocability;
* sensitivity;
* delegation semantics.

---

### 1707. SecurityTokenType

```php
enum SecurityTokenType: string
{
    case Access = 'access';
    case Refresh = 'refresh';
    case Identity = 'identity';
    case Session = 'session';
    case Delegation = 'delegation';
    case Impersonation = 'impersonation';
    case Service = 'service';
    case Workload = 'workload';
    case Recovery = 'recovery';
    case OneTime = 'one_time';
}
```

---

### 1708. TokenFormat

```php
enum TokenFormat: string
{
    case Opaque = 'opaque';
    case Jwt = 'jwt';
    case Paseto = 'paseto';
    case Macaroon = 'macaroon';
    case CustomStructured = 'custom_structured';
}
```

---

### 1709. TokenBearerModel

```php
enum TokenBearerModel: string
{
    case Bearer = 'bearer';
    case ProofOfPossession = 'proof_of_possession';
    case SenderConstrained = 'sender_constrained';
    case OneTime = 'one_time';
}
```

---

### 1710. Token format selection policy

La selección de formato deberá depender de:

* entorno;
* arquitectura;
* necesidad de validación offline;
* revocación;
* sensibilidad;
* interoperabilidad;
* tamaño;
* latencia;
* trust boundary.

---

### 1711. Opaque tokens

Los tokens opacos deberán contener únicamente un identificador aleatorio sin significado interpretable por el cliente.

---

### 1712. OpaqueTokenRecord

```php
final readonly class OpaqueTokenRecord
{
    public function __construct(
        public string $tokenId,
        public string $tokenHash,
        public SecurityTokenType $type,
        public IdentityIdentifier|string $subject,
        public ?IdentityIdentifier $actor,
        public array $audiences,
        public array $scopes,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public TokenLifecycleState $state,
    ) {
    }
}
```

---

### 1713. Opaque token storage

El valor del token opaco no deberá almacenarse en plaintext.

El registro deberá conservar:

* hash;
* metadata;
* estado;
* referencias;
* expiración;
* bindings.

---

### 1714. Opaque token advantages

Los tokens opacos facilitan:

* revocación inmediata;
* introspection centralizada;
* menor exposición de claims;
* políticas dinámicas;
* control de sesión;
* ocultamiento de metadata.

---

### 1715. Opaque token limitations

Sus desventajas incluyen:

* dependencia del issuer;
* mayor latencia;
* necesidad de cache;
* riesgo de indisponibilidad del servicio de introspection.

---

### 1716. Structured tokens

Los tokens estructurados deberán utilizar formatos estandarizados y políticas estrictas.

---

### 1717. Structured token restrictions

Nunca deberá confiarse en claims antes de:

* validar estructura;
* validar firma o autenticación;
* validar algoritmo;
* validar issuer;
* validar audience;
* validar expiración;
* validar propósito;
* validar key state.

---

### 1718. JWT governance

VoltStack deberá tratar JWT como un contenedor firmado, no como un mecanismo completo de autorización.

---

### 1719. JwtTokenProfile

```php
final readonly class JwtTokenProfile
{
    public function __construct(
        public string $profileId,
        public SecurityTokenType $type,
        public array $allowedAlgorithms,
        public array $requiredClaims,
        public DateInterval $maximumLifetime,
        public bool $encryptionRequired,
        public bool $revocationCheckRequired,
        public bool $proofOfPossessionRequired,
    ) {
    }
}
```

---

### 1720. JWT algorithm allowlist

El algoritmo deberá resolverse desde policy y nunca aceptarse únicamente desde el header del token.

---

### 1721. JWT unsecured mode prohibition

Los JWT con:

```text
alg = none
```

deberán rechazarse siempre.

---

### 1722. Symmetric and asymmetric separation

VoltStack deberá evitar utilizar una clave pública como secreto HMAC o mezclar claves simétricas y asimétricas dentro del mismo perfil.

---

### 1723. JWT header policy

Los headers permitidos deberán limitarse y validarse.

Ejemplos:

* `alg`;
* `kid`;
* `typ`;
* `cty`;
* `crit`.

---

### 1724. Critical header validation

Los headers declarados en `crit` deberán ser comprendidos y procesados explícitamente o el token deberá rechazarse.

---

### 1725. Token type header

`typ` deberá utilizarse para reducir token confusion entre:

* access token;
* ID token;
* logout token;
* recovery token;
* delegation token.

---

### 1726. Nested tokens

Los tokens anidados deberán declarar explícitamente:

* formato externo;
* formato interno;
* orden de firma y cifrado;
* propósito;
* maximum nesting depth.

---

### 1727. Sign-then-encrypt policy

Para tokens confidenciales, VoltStack podrá aplicar:

```text
Claims
  ↓
Signed Token
  ↓
Encrypted Token
```

Esto protege integridad interna y confidencialidad externa.

---

### 1728. Token encryption

La firma no oculta claims.

Los tokens con información sensible deberán:

* cifrarse;
* reemplazarse por opaque tokens;
* minimizar claims;
* evitarse en frontends no confiables.

---

### 1729. Token claims policy

```php
interface TokenClaimsPolicyInterface
{
    public function evaluate(
        TokenClaimsContext $context
    ): TokenClaimsDecision;
}
```

---

### 1730. TokenClaimsDecision

```php
final readonly class TokenClaimsDecision
{
    public function __construct(
        public array $includedClaims,
        public array $excludedClaims,
        public array $requiredClaims,
        public array $transformations,
        public array $restrictions,
    ) {
    }
}
```

---

### 1731. Claim minimization

Los tokens deberán incluir únicamente los claims necesarios para el consumidor.

---

### 1732. Registered claims

Claims comunes:

* `iss`;
* `sub`;
* `aud`;
* `exp`;
* `nbf`;
* `iat`;
* `jti`.

Cada uno deberá validarse según el perfil.

---

### 1733. Issuer validation

El issuer deberá compararse mediante coincidencia exacta contra una configuración confiable.

---

### 1734. Issuer confusion prevention

No deberá seleccionarse automáticamente una configuración de validación basada únicamente en el `iss` recibido sin antes aplicar una allowlist segura.

---

### 1735. Audience restriction

Todo access token deberá tener una audiencia concreta.

---

### 1736. Audience validation

El recurso deberá verificar que su identificador esté incluido explícitamente en `aud`.

No deberá aceptar tokens destinados a otro servicio.

---

### 1737. Multi-audience token restrictions

Los tokens con múltiples audiencias deberán utilizarse solo cuando exista una necesidad documentada.

---

### 1738. Authorized party

Cuando existan múltiples audiencias, deberá validarse `azp` o un mecanismo equivalente cuando el protocolo lo requiera.

---

### 1739. Subject claim

`sub` deberá ser:

* estable dentro del issuer;
* no ambiguo;
* no reasignable;
* compatible con aislamiento multi-tenant.

---

### 1740. Tenant claim

Si el token incluye tenant, dicho claim deberá validarse contra:

* issuer;
* subject;
* audience;
* request context;
* resource tenancy.

---

### 1741. Cross-tenant token prohibition

Un token emitido para un tenant no deberá utilizarse en otro, aunque el subject comparta un identificador externo.

---

### 1742. Token lifetime policy

```php
final readonly class TokenLifetimePolicy
{
    public function __construct(
        public DateInterval $maximumLifetime,
        public DateInterval $maximumClockSkew,
        public bool $notBeforeRequired,
        public bool $issuedAtRequired,
        public bool $absoluteExpirationRequired,
    ) {
    }
}
```

---

### 1743. Short-lived access tokens

Los access tokens deberán tener vidas cortas, especialmente cuando sean bearer tokens.

---

### 1744. Refresh token separation

Un refresh token no deberá aceptarse como access token ni presentarse a resource servers.

---

### 1745. One-time token lifetime

Los tokens de:

* recuperación;
* verificación;
* autorización;
* enlace mágico;

deberán tener expiración corta y consumo único.

---

### 1746. Clock skew

La tolerancia de reloj deberá:

* ser limitada;
* configurarse por perfil;
* no extender de forma significativa la validez;
* registrarse en validaciones anómalas.

---

### 1747. TokenLifecycleState

```php
enum TokenLifecycleState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Consumed = 'consumed';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
    case Superseded = 'superseded';
}
```

---

### 1748. Token identifier

Todo token revocable o replay-sensitive deberá poseer un identificador único.

---

### 1749. TokenReference

```php
final readonly class TokenReference
{
    public function __construct(
        public string $tokenId,
        public SecurityTokenType $type,
        public string $issuer,
        public string $tenantId,
    ) {
    }
}
```

---

### 1750. Token binding

VoltStack deberá poder vincular tokens a:

* certificado;
* public key;
* device;
* session;
* client;
* workload;
* request proof.

---

### 1751. TokenBinding

```php
final readonly class TokenBinding
{
    public function __construct(
        public TokenBindingType $type,
        public string $bindingIdentifier,
        public string $confirmationMethod,
        public array $metadata,
    ) {
    }
}
```

---

### 1752. TokenBindingType

```php
enum TokenBindingType: string
{
    case MtlsCertificate = 'mtls_certificate';
    case DpopKey = 'dpop_key';
    case Device = 'device';
    case Session = 'session';
    case Workload = 'workload';
    case ClientInstance = 'client_instance';
}
```

---

### 1753. Proof-of-possession tokens

Un proof-of-possession token deberá exigir evidencia de control de una clave adicional.

---

### 1754. PoP validation

La validación deberá comprobar:

* token binding;
* firma del proof;
* freshness;
* HTTP method;
* target URI;
* nonce cuando aplique;
* replay registry;
* key thumbprint.

---

### 1755. DPoP proof

```php
final readonly class DpopProof
{
    public function __construct(
        public string $jwt,
        public string $publicKeyThumbprint,
        public string $httpMethod,
        public string $httpUri,
        public string $proofId,
        public DateTimeImmutable $issuedAt,
    ) {
    }
}
```

---

### 1756. DPoP URI normalization

La comparación del target URI deberá seguir una canonicalización estricta para evitar mismatches y bypasses.

---

### 1757. DPoP replay registry

```php
interface DpopReplayRegistryInterface
{
    public function consume(
        string $proofId,
        string $keyThumbprint,
        DateTimeImmutable $expiresAt
    ): bool;
}
```

---

### 1758. mTLS-bound access tokens

Un token ligado a mTLS deberá incluir o referenciar el thumbprint del certificado cliente.

---

### 1759. Certificate rotation and bound tokens

La rotación del certificado deberá contemplar cómo se renuevan o invalidan tokens ligados a la clave anterior.

---

### 1760. Sender constraint enforcement

El resource server deberá verificar el sender constraint; no basta con que el authorization server lo haya emitido.

---

### 1761. Token exchange architecture

VoltStack deberá soportar intercambio controlado de tokens entre dominios o servicios.

---

### 1762. TokenExchangeRequest

```php
final readonly class TokenExchangeRequest
{
    public function __construct(
        public PresentedSecurityToken $subjectToken,
        public ?PresentedSecurityToken $actorToken,
        public SecurityTokenType $requestedTokenType,
        public array $requestedAudiences,
        public array $requestedScopes,
        public TokenExchangeContext $context,
    ) {
    }
}
```

---

### 1763. Token exchange policy

```php
interface TokenExchangePolicyInterface
{
    public function evaluate(
        TokenExchangeRequest $request
    ): TokenExchangeDecision;
}
```

---

### 1764. TokenExchangeDecision

```php
final readonly class TokenExchangeDecision
{
    public function __construct(
        public bool $allowed,
        public array $approvedAudiences,
        public array $approvedScopes,
        public DateInterval $maximumLifetime,
        public bool $actorChainRequired,
        public array $denialReasons,
    ) {
    }
}
```

---

### 1765. No privilege amplification

El token resultante no deberá tener más privilegios que:

* el subject token;
* el actor token;
* la policy;
* el client entitlement;
* el target resource policy.

---

### 1766. Token downscoping

Todo intercambio deberá preferir reducción de:

* audience;
* scope;
* lifetime;
* resource set;
* action set.

---

### 1767. DownscopedTokenRequest

```php
final readonly class DownscopedTokenRequest
{
    public function __construct(
        public TokenReference $sourceToken,
        public array $targetResources,
        public array $allowedActions,
        public DateInterval $requestedLifetime,
    ) {
    }
}
```

---

### 1768. Delegation tokens

Un delegation token representa autoridad delegada por un subject a otro actor o servicio.

---

### 1769. DelegationTokenClaims

```php
final readonly class DelegationTokenClaims
{
    public function __construct(
        public IdentityIdentifier|string $subject,
        public IdentityIdentifier|string $actor,
        public array $delegatedScopes,
        public array $resources,
        public array $delegationChain,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 1770. Delegation semantics

La delegación deberá distinguir:

* quién es el propietario de la autoridad;
* quién ejecuta la acción;
* qué autoridad fue delegada;
* para qué recursos;
* durante cuánto tiempo.

---

### 1771. Delegation chain

Toda delegación encadenada deberá conservar provenance completo.

---

### 1772. Delegation depth

VoltStack deberá limitar la profundidad de delegación para evitar cadenas incontrolables.

---

### 1773. Delegation cycle prevention

No deberá permitirse una cadena de delegación que regrese a una identidad previa o genere ciclos.

---

### 1774. Delegation revocation

Revocar una delegación superior deberá invalidar delegaciones descendientes derivadas de ella.

---

### 1775. Impersonation tokens

Un impersonation token permite que un actor opere temporalmente como otro subject bajo controles reforzados.

---

### 1776. ImpersonationTokenClaims

```php
final readonly class ImpersonationTokenClaims
{
    public function __construct(
        public IdentityIdentifier $subject,
        public IdentityIdentifier $actor,
        public string $reason,
        public string $ticketId,
        public array $allowedActions,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 1777. Impersonation requirements

Toda impersonación deberá requerir:

* permiso explícito;
* MFA reciente;
* justificación;
* ticket;
* duración corta;
* logging reforzado;
* aviso visible;
* prohibición de acciones sensibles cuando aplique.

---

### 1778. Actor and subject preservation

Durante impersonación, los logs y decisiones deberán conservar tanto:

* actor real;
* subject representado.

Nunca deberá sustituirse uno por otro.

---

### 1779. Impersonation restrictions

La impersonación podrá bloquear:

* cambio de credenciales;
* modificación de MFA;
* creación de nuevos tokens;
* acceso a secretos;
* aprobación de accesos propios;
* eliminación de auditoría;
* elevaciones privilegiadas adicionales.

---

### 1780. Support impersonation

Para soporte técnico deberá preferirse:

* read-only;
* scoped;
* time-limited;
* customer-visible;
* explicitly approved.

---

### 1781. Token chaining

Cuando un servicio llama a otro utilizando tokens derivados, VoltStack deberá conservar:

* original subject;
* current actor;
* delegation chain;
* source token reference;
* target audience.

---

### 1782. Actor token

Un actor token deberá representar la identidad del componente que ejecuta la operación, no sustituir al subject token.

---

### 1783. Service-to-service token policy

Los tokens de servicio deberán:

* tener audience específica;
* scope mínimo;
* lifetime corto;
* sender constraint cuando sea viable;
* identidad de workload;
* rotación automática.

---

### 1784. Workload token issuance

Los tokens de workload deberán emitirse a partir de:

* attestation;
* SPIFFE identity;
* mTLS identity;
* cloud workload federation;
* trusted runtime identity.

---

### 1785. User token reuse prohibition

Un servicio no deberá reutilizar directamente un token frontend para múltiples servicios internos sin token exchange o audience restriction adecuada.

---

### 1786. Token revocation

VoltStack deberá admitir revocación por:

* token;
* session;
* subject;
* client;
* device;
* family;
* tenant;
* issuer;
* key;
* delegation chain.

---

### 1787. TokenRevocationReason

```php
enum TokenRevocationReason: string
{
    case UserLogout = 'user_logout';
    case AdministratorAction = 'administrator_action';
    case CredentialCompromise = 'credential_compromise';
    case SessionCompromise = 'session_compromise';
    case ClientCompromise = 'client_compromise';
    case ConsentRevoked = 'consent_revoked';
    case ScopeWithdrawn = 'scope_withdrawn';
    case KeyCompromise = 'key_compromise';
    case TokenReuse = 'token_reuse';
    case PolicyViolation = 'policy_violation';
}
```

---

### 1788. Token revocation registry

```php
interface TokenRevocationRegistryInterface
{
    public function revoke(
        TokenReference $token,
        TokenRevocationReason $reason,
        DateTimeImmutable $revokedAt
    ): void;

    public function isRevoked(
        TokenReference $token
    ): bool;
}
```

---

### 1789. Revocation propagation

La revocación deberá propagarse a:

* authorization servers;
* resource servers;
* gateways;
* session stores;
* token caches;
* introspection caches;
* downstream services.

---

### 1790. Revocation latency

Las políticas deberán definir cuánto tiempo máximo puede tardar una revocación en ser efectiva.

Para operaciones críticas, la propagación deberá ser casi inmediata.

---

### 1791. Token replay protection

La protección deberá considerar:

* `jti`;
* one-time consumption;
* sender constraints;
* nonce;
* replay registries;
* token family state;
* request binding.

---

### 1792. OneTimeTokenRegistry

```php
interface OneTimeTokenRegistryInterface
{
    public function consume(
        string $tokenId,
        DateTimeImmutable $expiresAt
    ): OneTimeTokenConsumptionResult;
}
```

---

### 1793. Refresh token reuse detection

Cuando un refresh token rotado vuelva a utilizarse, deberá asumirse posible compromiso de toda la familia.

---

### 1794. Token introspection

Los resource servers podrán consultar el estado de tokens opacos o revocables.

---

### 1795. TokenIntrospectionService

```php
interface TokenIntrospectionServiceInterface
{
    public function introspect(
        PresentedSecurityToken $token,
        TokenIntrospectionContext $context
    ): TokenIntrospectionResult;
}
```

---

### 1796. TokenIntrospectionResult

```php
final readonly class TokenIntrospectionResult
{
    public function __construct(
        public bool $active,
        public SecurityTokenType $type,
        public ?IdentityIdentifier $subject,
        public ?IdentityIdentifier $actor,
        public array $audiences,
        public array $scopes,
        public ?DateTimeImmutable $expiresAt,
        public array $confirmation,
        public array $metadata,
    ) {
    }
}
```

---

### 1797. Introspection authorization

No cualquier cliente deberá poder introspectar cualquier token.

La respuesta deberá limitarse según:

* resource server;
* audience;
* tenant;
* client;
* token type;
* policy.

---

### 1798. Introspection caching

El cache deberá considerar:

* expiración;
* revocación;
* token sensitivity;
* maximum staleness;
* issuer state;
* key state.

Las respuestas inactivas críticas deberán evitar caches prolongados.

---

### 1799. Token audit events

Eventos recomendados:

* `SecurityTokenIssued`;
* `OpaqueTokenIssued`;
* `StructuredTokenIssued`;
* `TokenValidationSucceeded`;
* `TokenValidationFailed`;
* `TokenIssuerRejected`;
* `TokenAudienceRejected`;
* `TokenExpired`;
* `TokenReplayDetected`;
* `ProofOfPossessionFailed`;
* `TokenExchangeRequested`;
* `TokenExchangeCompleted`;
* `TokenExchangeDenied`;
* `DelegationTokenIssued`;
* `DelegationChainRejected`;
* `ImpersonationTokenIssued`;
* `ImpersonationTokenUsed`;
* `TokenDownscoped`;
* `TokenRevoked`;
* `TokenFamilyCompromised`;
* `TokenIntrospected`.

---

### 1800. Resultado de esta entrega

Esta entrega establece:

```text
Security Token Architecture
Token Classification
Token Format Selection
Opaque Tokens
Structured Tokens
JWT Governance
JWT Algorithm Allowlists
Token Type Confusion Prevention
Token Encryption
Claims Minimization
Issuer Validation
Audience Restriction
Tenant Binding
Token Lifetime Policies
Short-Lived Access Tokens
One-Time Tokens
Token Binding
Proof-of-Possession Tokens
DPoP Validation
mTLS-Bound Tokens
Token Exchange
No Privilege Amplification
Token Downscoping
Delegation Tokens
Delegation Chains
Delegation Cycle Prevention
Impersonation Tokens
Actor and Subject Preservation
Service-to-Service Tokens
Workload Token Issuance
Token Revocation
Revocation Propagation
Token Replay Protection
Refresh Token Reuse Detection
Token Introspection
Introspection Authorization
Token Governance and Audit Events
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 19

- API authentication architecture
- API client identities
- API key architecture
- API key hashing
- API key prefixes
- API key scoping
- API key rotation
- HMAC request authentication
- Signed request canonicalization
- Timestamp and nonce validation
- Webhook authentication
- Webhook signature verification
- mTLS API authentication
- OAuth-protected APIs
- Internal API trust
- API gateway authentication
- Service mesh authentication
- API abuse detection
- API credential governance
- API authentication audit
```

## Entrega 19

**Documento:** Parte 05
**Entrega:** 19 de varias
**Cobertura:** Secciones **1801–1900**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 18`

---

### 1801. API Authentication Architecture

VoltStack deberá incorporar una arquitectura centralizada de autenticación para APIs públicas, privadas, internas y machine-to-machine.

La arquitectura deberá cubrir:

* API keys;
* OAuth 2.0 access tokens;
* proof-of-possession tokens;
* HMAC request signing;
* mutual TLS;
* workload identities;
* signed webhooks;
* service mesh identities;
* gateway-mediated authentication.

Los Controllers no deberán validar credenciales de API directamente.

---

### 1802. API authentication security goals

La arquitectura deberá garantizar:

* identificación inequívoca del cliente;
* autenticación fuerte;
* scope mínimo;
* restricción de audiencia;
* resistencia a replay;
* rotación;
* revocación;
* aislamiento multi-tenant;
* trazabilidad;
* protección contra abuso;
* separación entre autenticación y autorización.

---

### 1803. API authentication threat model

El modelo deberá considerar:

* API keys filtradas;
* bearer token theft;
* replay de requests;
* credential stuffing;
* key guessing;
* signature bypass;
* canonicalization confusion;
* timestamp manipulation;
* nonce reuse;
* webhook spoofing;
* downgrade de mecanismos;
* confianza implícita en redes internas;
* abuso de service accounts;
* cross-tenant access;
* exceso de scopes;
* credenciales sin expiración;
* gateway bypass.

---

### 1804. API authentication pipeline

```text
Incoming API Request
        ↓
Credential Extraction
        ↓
Authentication Scheme Resolution
        ↓
Credential Validation
        ↓
Replay and Freshness Validation
        ↓
Client Identity Resolution
        ↓
Tenant and Audience Binding
        ↓
Authorization Context Creation
        ↓
Rate Limit and Abuse Evaluation
        ↓
Controller Dispatch
```

---

### 1805. ApiAuthenticationService

```php
interface ApiAuthenticationServiceInterface
{
    public function authenticate(
        ApiAuthenticationRequest $request
    ): ApiAuthenticationResult;
}
```

---

### 1806. ApiAuthenticationRequest

```php
final readonly class ApiAuthenticationRequest
{
    public function __construct(
        public string $method,
        public string $uri,
        public array $headers,
        public string $body,
        public string $remoteAddress,
        public DateTimeImmutable $receivedAt,
        public ApiAuthenticationContext $context,
    ) {
    }
}
```

---

### 1807. ApiAuthenticationResult

```php
final readonly class ApiAuthenticationResult
{
    public function __construct(
        public ApiAuthenticationStatus $status,
        public ?ApiClientIdentity $client,
        public ?IdentityIdentifier $subject,
        public ?IdentityIdentifier $actor,
        public array $grantedScopes,
        public AuthenticationAssuranceLevel $assurance,
        public array $bindings,
        public array $failures,
    ) {
    }
}
```

---

### 1808. ApiAuthenticationStatus

```php
enum ApiAuthenticationStatus: string
{
    case Authenticated = 'authenticated';
    case Challenged = 'challenged';
    case Denied = 'denied';
    case InvalidCredential = 'invalid_credential';
    case ExpiredCredential = 'expired_credential';
    case RevokedCredential = 'revoked_credential';
    case ReplayDetected = 'replay_detected';
}
```

---

### 1809. API authentication scheme registry

VoltStack deberá registrar explícitamente los mecanismos soportados.

---

### 1810. ApiAuthenticationScheme

```php
enum ApiAuthenticationScheme: string
{
    case ApiKey = 'api_key';
    case BearerToken = 'bearer_token';
    case Dpop = 'dpop';
    case HmacSignature = 'hmac_signature';
    case MutualTls = 'mutual_tls';
    case WorkloadIdentity = 'workload_identity';
    case SignedWebhook = 'signed_webhook';
}
```

---

### 1811. Scheme resolution

La selección del esquema deberá considerar:

* ruta;
* API version;
* tenant;
* cliente;
* trust boundary;
* sensibilidad;
* transport;
* metadata de ruta.

---

### 1812. Route authentication metadata

```php
#[ApiAuthentication(
    schemes: [
        ApiAuthenticationScheme::Dpop,
        ApiAuthenticationScheme::MutualTls,
    ],
    requireAll: false,
    minimumAssurance: AuthenticationAssuranceLevel::High,
)]
```

---

### 1813. Multiple authentication schemes

Una ruta podrá:

* aceptar cualquiera de varios esquemas;
* requerir combinación de esquemas;
* exigir un mecanismo concreto;
* prohibir mecanismos heredados.

---

### 1814. Downgrade prevention

Un cliente configurado para mTLS o proof-of-possession no deberá degradarse silenciosamente a una API key bearer.

---

### 1815. API client identities

Todo consumidor deberá representarse mediante una identidad de cliente explícita.

---

### 1816. ApiClientIdentity

```php
final readonly class ApiClientIdentity
{
    public function __construct(
        public string $clientId,
        public string $tenantId,
        public string $name,
        public ApiClientType $type,
        public ApiClientState $state,
        public array $allowedAuthenticationSchemes,
        public array $allowedAudiences,
        public array $defaultScopes,
        public IdentityIdentifier|string $owner,
    ) {
    }
}
```

---

### 1817. ApiClientType

```php
enum ApiClientType: string
{
    case PublicApplication = 'public_application';
    case ConfidentialApplication = 'confidential_application';
    case FirstPartyService = 'first_party_service';
    case ThirdPartyIntegration = 'third_party_integration';
    case Workload = 'workload';
    case Device = 'device';
    case Automation = 'automation';
    case WebhookProducer = 'webhook_producer';
}
```

---

### 1818. ApiClientState

```php
enum ApiClientState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Restricted = 'restricted';
    case Compromised = 'compromised';
    case Retired = 'retired';
}
```

---

### 1819. Client ownership

Cada cliente deberá poseer:

* owner técnico;
* owner de negocio cuando aplique;
* tenant;
* propósito;
* entorno;
* contacto de seguridad;
* fecha de revisión;
* mecanismo de autenticación;
* política de rotación.

---

### 1820. Client registration

El registro de clientes deberá validar:

* identidad del solicitante;
* caso de uso;
* redirect URIs cuando aplique;
* audiences;
* scopes;
* entorno;
* tenant;
* owner;
* nivel de riesgo.

---

### 1821. API key architecture

Las API keys deberán utilizarse únicamente cuando mecanismos más fuertes no sean viables o cuando el riesgo sea aceptable.

---

### 1822. ApiKeyCredential

```php
final readonly class ApiKeyCredential
{
    public function __construct(
        public string $keyId,
        public string $clientId,
        public string $tenantId,
        public string $prefix,
        public string $secretHash,
        public string $hashAlgorithm,
        public ApiKeyState $state,
        public array $scopes,
        public array $allowedResources,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 1823. ApiKeyState

```php
enum ApiKeyState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rotating = 'rotating';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
    case Retired = 'retired';
}
```

---

### 1824. API key format

Una API key podrá estructurarse conceptualmente como:

```text
vsk_live_<prefix>_<secret>
```

El formato deberá permitir identificar:

* proveedor o framework;
* entorno;
* tipo de credencial;
* clave candidata;

sin exponer material sensible.

---

### 1825. API key prefixes

El prefix deberá:

* facilitar lookup;
* no ser secreto;
* no contener permisos;
* no revelar tenant sensible;
* no permitir reconstruir la key;
* ser suficientemente único.

---

### 1826. API key secret generation

La parte secreta deberá generarse mediante CSPRNG y contener entropía suficiente para resistir ataques de guessing.

---

### 1827. API key one-time display

El valor completo deberá mostrarse una sola vez durante creación o rotación.

---

### 1828. API key hashing

VoltStack no deberá almacenar API keys completas en plaintext.

---

### 1829. ApiKeyHasher

```php
interface ApiKeyHasherInterface
{
    public function hash(
        SensitiveValue $apiKey
    ): ApiKeyHash;

    public function verify(
        SensitiveValue $presentedKey,
        ApiKeyHash $storedHash
    ): bool;
}
```

---

### 1830. API key hash strategy

Podrá utilizarse:

* HMAC con pepper protegido;
* hash criptográfico keyed;
* password hashing en contextos específicos.

La selección deberá considerar volumen, latencia y riesgo de enumeración.

---

### 1831. API key lookup

El prefix podrá localizar un conjunto reducido de candidatos y la parte secreta deberá verificarse mediante comparación segura.

---

### 1832. API key enumeration protection

Las respuestas no deberán revelar si:

* el prefix existe;
* el cliente existe;
* la key expiró;
* la key fue revocada.

---

### 1833. API key scopes

Toda API key deberá poseer scopes explícitos.

---

### 1834. API key resource restrictions

Además de scopes, una key podrá restringirse a:

* endpoints;
* recursos;
* tenant;
* región;
* environment;
* IP ranges;
* methods;
* horario.

---

### 1835. API key wildcard restrictions

Los scopes globales o wildcards deberán requerir aprobación reforzada.

---

### 1836. API key expiration

Las API keys deberán expirar salvo excepciones documentadas.

---

### 1837. API key inactivity policy

Las claves sin uso durante un período definido deberán:

* marcarse como dormant;
* notificarse;
* suspenderse;
* revocarse según policy.

---

### 1838. API key rotation

La rotación deberá admitir coexistencia controlada entre versión antigua y nueva.

---

### 1839. ApiKeyRotationService

```php
interface ApiKeyRotationServiceInterface
{
    public function start(
        ApiKeyReference $currentKey,
        ApiKeyRotationContext $context
    ): ApiKeyRotationPlan;

    public function complete(
        ApiKeyRotationPlan $plan
    ): ApiKeyRotationResult;
}
```

---

### 1840. API key rotation stages

```text
Generate Replacement Key
        ↓
Activate New Key
        ↓
Allow Controlled Overlap
        ↓
Observe Client Migration
        ↓
Disable Previous Key
        ↓
Revoke Previous Key
```

---

### 1841. API key usage telemetry

VoltStack deberá registrar:

* primer uso;
* último uso;
* frecuencia;
* origen;
* endpoints;
* errores;
* anomalías;
* versión utilizada.

---

### 1842. HMAC request authentication

VoltStack deberá soportar autenticación de requests mediante firma HMAC.

---

### 1843. HmacApiCredential

```php
final readonly class HmacApiCredential
{
    public function __construct(
        public string $credentialId,
        public string $clientId,
        public SecretReference $secret,
        public string $algorithm,
        public HmacCredentialState $state,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 1844. HmacCredentialState

```php
enum HmacCredentialState: string
{
    case Active = 'active';
    case Rotating = 'rotating';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
}
```

---

### 1845. Signed request components

La firma deberá cubrir, según policy:

* HTTP method;
* canonical URI;
* query parameters;
* selected headers;
* timestamp;
* nonce;
* body digest;
* client identifier;
* content type.

---

### 1846. Canonical request

```php
final readonly class CanonicalApiRequest
{
    public function __construct(
        public string $method,
        public string $canonicalUri,
        public string $canonicalQuery,
        public string $canonicalHeaders,
        public string $signedHeaders,
        public string $payloadDigest,
        public string $timestamp,
        public string $nonce,
    ) {
    }
}
```

---

### 1847. Canonicalization service

```php
interface ApiRequestCanonicalizerInterface
{
    public function canonicalize(
        ApiAuthenticationRequest $request,
        SignedRequestProfile $profile
    ): CanonicalApiRequest;
}
```

---

### 1848. Canonicalization determinism

Cliente y servidor deberán producir exactamente la misma representación para un request equivalente.

---

### 1849. URI canonicalization

La canonicalización deberá definir:

* encoding;
* path normalization;
* trailing slash;
* duplicate separators;
* dot segments;
* case sensitivity;
* percent-encoding.

---

### 1850. Query canonicalization

La policy deberá definir:

* ordenamiento;
* parámetros repetidos;
* valores vacíos;
* encoding;
* exclusiones;
* arrays.

---

### 1851. Header canonicalization

Deberá especificarse:

* nombres en lowercase;
* trimming;
* whitespace normalization;
* orden;
* headers obligatorios;
* headers prohibidos;
* combinación de valores repetidos.

---

### 1852. Request body digest

El body deberá resumirse mediante un hash criptográfico permitido.

---

### 1853. Streaming body verification

Para payloads grandes, VoltStack deberá permitir cálculo incremental sin cargar todo el contenido en memoria.

---

### 1854. SignedRequestProfile

```php
final readonly class SignedRequestProfile
{
    public function __construct(
        public string $profileId,
        public string $algorithm,
        public array $requiredHeaders,
        public DateInterval $maximumAge,
        public bool $nonceRequired,
        public bool $bodyDigestRequired,
        public bool $contentTypeSigned,
    ) {
    }
}
```

---

### 1855. HMAC signature creation

```php
interface ApiRequestSignerInterface
{
    public function sign(
        CanonicalApiRequest $request,
        SensitiveValue $secret,
        SignedRequestProfile $profile
    ): ApiRequestSignature;
}
```

---

### 1856. ApiRequestSignature

```php
final readonly class ApiRequestSignature
{
    public function __construct(
        public string $credentialId,
        public string $algorithm,
        public string $signature,
        public string $signedHeaders,
        public DateTimeImmutable $createdAt,
        public string $nonce,
    ) {
    }
}
```

---

### 1857. Signature verification

La verificación deberá:

1. resolver la credencial;
2. validar estado;
3. validar algoritmo;
4. reconstruir canonical request;
5. validar timestamp;
6. consumir nonce;
7. verificar firma;
8. validar client policy.

---

### 1858. Timestamp validation

El timestamp deberá estar dentro de una ventana pequeña definida por policy.

---

### 1859. Timestamp source

El servidor deberá utilizar su propio reloj confiable para validar freshness.

---

### 1860. Clock synchronization

Los componentes críticos deberán mantener sincronización de reloj y alertar ante drift excesivo.

---

### 1861. Nonce validation

Cada request firmado deberá incluir un nonce único cuando la policy lo requiera.

---

### 1862. ApiRequestNonceRegistry

```php
interface ApiRequestNonceRegistryInterface
{
    public function consume(
        string $credentialId,
        string $nonce,
        DateTimeImmutable $expiresAt
    ): NonceConsumptionResult;
}
```

---

### 1863. Nonce replay prevention

Un nonce reutilizado dentro de la ventana de validez deberá provocar rechazo.

---

### 1864. Replay registry availability

Para operaciones críticas, la indisponibilidad del registry deberá producir fail-closed.

---

### 1865. Signature comparison

La firma deberá compararse mediante una operación constant-time.

---

### 1866. Key rotation for signed requests

Durante rotación, el cliente podrá indicar key version o credential ID, pero el servidor deberá limitar las versiones aceptadas.

---

### 1867. Asymmetric request signing

VoltStack podrá soportar firmas asimétricas para clientes que necesiten evitar secretos compartidos.

---

### 1868. AsymmetricApiCredential

```php
final readonly class AsymmetricApiCredential
{
    public function __construct(
        public string $credentialId,
        public string $clientId,
        public JsonWebKey|CertificateReference $publicCredential,
        public string $algorithm,
        public ApiKeyState $state,
    ) {
    }
}
```

---

### 1869. Asymmetric request proof

El cliente deberá demostrar control de la clave privada sin enviarla al servidor.

---

### 1870. Webhook authentication architecture

Todo webhook entrante deberá autenticarse.

---

### 1871. WebhookEndpointDefinition

```php
final readonly class WebhookEndpointDefinition
{
    public function __construct(
        public string $endpointId,
        public string $tenantId,
        public string $providerId,
        public WebhookAuthenticationScheme $scheme,
        public array $allowedEventTypes,
        public WebhookReplayPolicy $replayPolicy,
        public WebhookEndpointState $state,
    ) {
    }
}
```

---

### 1872. WebhookAuthenticationScheme

```php
enum WebhookAuthenticationScheme: string
{
    case HmacSignature = 'hmac_signature';
    case AsymmetricSignature = 'asymmetric_signature';
    case MutualTls = 'mutual_tls';
    case OAuthBearer = 'oauth_bearer';
    case Combined = 'combined';
}
```

---

### 1873. WebhookEndpointState

```php
enum WebhookEndpointState: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Rotating = 'rotating';
    case Retired = 'retired';
}
```

---

### 1874. Webhook signature verification

La verificación deberá realizarse sobre el body original recibido antes de:

* parsearlo;
* normalizar JSON;
* transformar encoding;
* modificar whitespace;
* deserializar.

---

### 1875. WebhookSignatureVerifier

```php
interface WebhookSignatureVerifierInterface
{
    public function verify(
        IncomingWebhookRequest $request,
        WebhookEndpointDefinition $endpoint
    ): WebhookVerificationResult;
}
```

---

### 1876. IncomingWebhookRequest

```php
final readonly class IncomingWebhookRequest
{
    public function __construct(
        public array $headers,
        public string $rawBody,
        public string $remoteAddress,
        public DateTimeImmutable $receivedAt,
    ) {
    }
}
```

---

### 1877. Webhook freshness

La firma deberá incorporar:

* timestamp;
* nonce;
* event ID;

cuando el proveedor lo soporte.

---

### 1878. Webhook replay registry

```php
interface WebhookReplayRegistryInterface
{
    public function consume(
        string $providerId,
        string $eventId,
        DateTimeImmutable $expiresAt
    ): WebhookReplayResult;
}
```

---

### 1879. Duplicate webhook delivery

La entrega duplicada legítima deberá diferenciarse de replay malicioso mediante procesamiento idempotente.

---

### 1880. Webhook idempotency

El event ID deberá utilizarse para impedir efectos de negocio duplicados.

---

### 1881. Webhook secret rotation

La rotación deberá permitir verificar temporalmente con:

* secret actual;
* secret anterior;
* version metadata.

---

### 1882. Webhook source IP restrictions

Las allowlists de IP podrán usarse como señal adicional, pero no deberán sustituir la verificación criptográfica.

---

### 1883. Outgoing webhook signing

VoltStack deberá firmar sus propios webhooks emitidos a terceros.

---

### 1884. OutgoingWebhookSigner

```php
interface OutgoingWebhookSignerInterface
{
    public function sign(
        OutgoingWebhookMessage $message,
        WebhookSigningContext $context
    ): SignedWebhookMessage;
}
```

---

### 1885. Outgoing webhook metadata

Un webhook emitido deberá incluir:

* event ID;
* event type;
* timestamp;
* signature version;
* key ID;
* retry attempt;
* payload digest.

---

### 1886. mTLS API authentication

Las APIs de alta sensibilidad deberán poder exigir certificados cliente.

---

### 1887. mTLS API validation

La autenticación deberá validar:

* certificate chain;
* revocation;
* EKU;
* SAN;
* trust domain;
* client mapping;
* tenant;
* certificate state.

---

### 1888. OAuth-protected APIs

Las APIs protegidas por OAuth deberán validar:

* issuer;
* audience;
* scope;
* token type;
* client;
* subject;
* proof-of-possession;
* revocation;
* tenant.

---

### 1889. Scope-to-route mapping

```php
#[RequiresScopes(
    all: ['orders.read'],
    any: ['tenant.orders', 'admin.orders'],
)]
```

---

### 1890. Scope authorization limitations

Poseer un scope no deberá sustituir:

* ownership checks;
* tenant checks;
* resource policies;
* record-level authorization;
* business rules.

---

### 1891. Internal API trust

VoltStack no deberá considerar confiable una request únicamente porque proviene de una red interna.

---

### 1892. Internal service authentication

Los servicios internos deberán usar:

* workload identity;
* mTLS;
* sender-constrained tokens;
* signed requests;
* short-lived credentials.

---

### 1893. API gateway authentication

El gateway podrá realizar autenticación inicial, pero el backend deberá recibir evidencia verificable.

---

### 1894. Gateway authentication evidence

```php
final readonly class GatewayAuthenticationEvidence
{
    public function __construct(
        public string $gatewayId,
        public string $clientId,
        public ?IdentityIdentifier $subject,
        public array $scopes,
        public DateTimeImmutable $authenticatedAt,
        public string $requestBinding,
        public DigitalSignature $signature,
    ) {
    }
}
```

---

### 1895. Gateway bypass prevention

Los servicios protegidos por gateway deberán impedir acceso directo no autenticado mediante:

* network policy;
* mTLS;
* signed gateway assertions;
* private ingress;
* service mesh policy.

---

### 1896. Service mesh authentication

VoltStack podrá consumir identidades proporcionadas por un service mesh, siempre que:

* la conexión esté autenticada;
* el workload esté attested;
* el trust domain sea válido;
* la identidad sea autorizada;
* no se confíe en headers sin protección.

---

### 1897. API abuse detection

El framework deberá detectar:

* request bursts;
* key sharing;
* impossible geography;
* enumeration;
* scraping;
* unusual endpoints;
* scope probing;
* replay;
* error pattern abuse;
* token cycling.

---

### 1898. API credential governance

VoltStack deberá mantener inventario de:

* clientes;
* API keys;
* certificados;
* signing credentials;
* owners;
* scopes;
* audiences;
* expiración;
* última actividad;
* rotaciones;
* excepciones.

---

### 1899. API authentication audit events

Eventos recomendados:

* `ApiAuthenticationAttempted`;
* `ApiAuthenticationSucceeded`;
* `ApiAuthenticationFailed`;
* `ApiClientRegistered`;
* `ApiClientSuspended`;
* `ApiKeyCreated`;
* `ApiKeyRotated`;
* `ApiKeyRevoked`;
* `DormantApiKeyDetected`;
* `SignedApiRequestAccepted`;
* `SignedApiRequestRejected`;
* `ApiRequestReplayDetected`;
* `WebhookSignatureValidated`;
* `WebhookSignatureRejected`;
* `WebhookReplayDetected`;
* `MutualTlsApiAuthenticationSucceeded`;
* `MutualTlsApiAuthenticationFailed`;
* `GatewayAssertionRejected`;
* `ApiAbuseDetected`;
* `ApiCredentialCompromised`.

---

### 1900. Resultado de esta entrega

Esta entrega establece:

```text
API Authentication Architecture
API Authentication Pipeline
API Client Identities
API Client Registration
API Key Architecture
API Key Prefixes
API Key Hashing
API Key Enumeration Protection
API Key Scoping
API Key Resource Restrictions
API Key Expiration
API Key Rotation
API Key Usage Telemetry
HMAC Request Authentication
Canonical Request Construction
URI, Query and Header Canonicalization
Request Body Digests
Timestamp Validation
Nonce Validation
Replay Protection
Asymmetric Request Signing
Webhook Authentication
Webhook Signature Verification
Webhook Freshness Validation
Webhook Replay Protection
Webhook Idempotency
Webhook Secret Rotation
Outgoing Webhook Signing
mTLS API Authentication
OAuth-Protected APIs
Scope-to-Route Mapping
Internal API Zero Trust
API Gateway Authentication
Gateway Bypass Prevention
Service Mesh Authentication
API Abuse Detection
API Credential Governance
API Authentication Audit Events
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 20

- Authorization context integrity
- Security context propagation
- Actor and subject propagation
- Tenant context propagation
- Request identity envelopes
- Signed security contexts
- Trusted proxy identity
- Gateway identity assertions
- Service-to-service identity propagation
- Delegation chain propagation
- Context attenuation
- Context freshness
- Context replay protection
- Context boundary validation
- Async identity propagation
- Queue and job identities
- Scheduled task identities
- Event consumer identities
- Distributed tracing security
- Security context governance
```

## Entrega 20

**Documento:** Parte 05
**Entrega:** 20 de varias
**Cobertura:** Secciones **1901–2000**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 19`

---

### 1901. Security Context Architecture

VoltStack deberá incorporar una arquitectura formal para construir, proteger, propagar, atenuar y validar contextos de seguridad.

El contexto de seguridad deberá representar, como mínimo:

* identidad autenticada;
* actor real;
* subject efectivo;
* tenant;
* sesión;
* cliente;
* workload;
* nivel de assurance;
* scopes;
* roles;
* delegaciones;
* restricciones;
* origen de confianza;
* vigencia;
* evidencia de autenticación.

---

### 1902. Security context security goals

La arquitectura deberá garantizar:

* integridad;
* autenticidad;
* mínima propagación;
* aislamiento multi-tenant;
* conservación de provenance;
* resistencia a replay;
* expiración;
* atenuación de privilegios;
* validación por frontera;
* auditabilidad;
* compatibilidad síncrona y asíncrona.

---

### 1903. Security context threat model

El modelo deberá considerar:

* headers falsificados;
* identidad inyectada por proxies;
* pérdida de actor original;
* confusión actor-subject;
* tenant swapping;
* propagación excesiva de privilegios;
* replay de contextos;
* contextos expirados;
* manipulación en colas;
* trust boundary bypass;
* suplantación de gateway;
* uso de metadata no autenticada;
* contaminación de contextos entre requests;
* exposición de claims sensibles;
* propagación transitive trust.

---

### 1904. Security context pipeline

```text
Authentication Evidence
        ↓
Identity Resolution
        ↓
Security Context Construction
        ↓
Policy-Based Attenuation
        ↓
Cryptographic Protection
        ↓
Boundary Propagation
        ↓
Receiving-Side Validation
        ↓
Local Authorization Context
        ↓
Audit and Trace Correlation
```

---

### 1905. SecurityContext

```php
final readonly class SecurityContext
{
    public function __construct(
        public string $contextId,
        public IdentityIdentifier|string $subject,
        public IdentityIdentifier|string $actor,
        public string $tenantId,
        public ?string $sessionId,
        public ?string $clientId,
        public ?string $workloadId,
        public AuthenticationAssuranceLevel $assurance,
        public array $scopes,
        public array $roles,
        public array $restrictions,
        public array $delegationChain,
        public SecurityContextProvenance $provenance,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 1906. SecurityContextProvenance

```php
final readonly class SecurityContextProvenance
{
    public function __construct(
        public string $issuer,
        public SecurityContextSource $source,
        public array $authenticationMethods,
        public ?string $gatewayId,
        public ?string $originService,
        public array $evidenceReferences,
    ) {
    }
}
```

---

### 1907. SecurityContextSource

```php
enum SecurityContextSource: string
{
    case DirectAuthentication = 'direct_authentication';
    case Session = 'session';
    case ApiToken = 'api_token';
    case GatewayAssertion = 'gateway_assertion';
    case ServiceIdentity = 'service_identity';
    case Delegated = 'delegated';
    case Impersonated = 'impersonated';
    case QueueEnvelope = 'queue_envelope';
    case ScheduledExecution = 'scheduled_execution';
    case SystemOperation = 'system_operation';
}
```

---

### 1908. Context immutability

Una vez creado, el contexto deberá ser inmutable.

Cualquier reducción, transformación o delegación deberá producir una nueva instancia.

---

### 1909. Context identity separation

El contexto deberá distinguir siempre:

* actor;
* subject;
* client;
* workload;
* tenant.

Estos valores no deberán colapsarse en un único identificador genérico.

---

### 1910. Actor identity

El actor representa quién ejecuta realmente la acción.

Ejemplos:

* usuario;
* administrador;
* servicio;
* job;
* agente automatizado;
* soporte técnico.

---

### 1911. Subject identity

El subject representa la identidad o recurso en cuyo nombre se actúa.

En una operación ordinaria, actor y subject pueden ser iguales.

---

### 1912. Actor-subject preservation

En delegación o impersonación, ambos deberán mantenerse durante toda la cadena.

---

### 1913. Effective identity

VoltStack podrá calcular una identidad efectiva para simplificar policies, pero no deberá eliminar actor ni subject originales.

---

### 1914. SecurityContextFactory

```php
interface SecurityContextFactoryInterface
{
    public function create(
        AuthenticationResult $authentication,
        SecurityContextCreationContext $context
    ): SecurityContext;
}
```

---

### 1915. Security context creation policy

La creación deberá resolver:

* subject;
* actor;
* tenant;
* authentication assurance;
* sesión;
* cliente;
* workload;
* scopes;
* restricciones;
* vigencia;
* provenance.

---

### 1916. Context creation from sessions

Un contexto derivado de sesión deberá validar:

* estado;
* expiración;
* credential version;
* authorization version;
* tenant;
* device binding;
* anomaly state.

---

### 1917. Context creation from access tokens

Un contexto derivado de token deberá validar:

* issuer;
* audience;
* scopes;
* sender constraints;
* subject;
* actor;
* tenant;
* expiration;
* revocation.

---

### 1918. Context creation from mTLS

El certificado cliente deberá mapearse a:

* workload;
* service;
* client;
* tenant;
* trust domain;
* assurance.

---

### 1919. AuthorizationContext

```php
final readonly class AuthorizationContext
{
    public function __construct(
        public SecurityContext $security,
        public string $resourceType,
        public ?string $resourceId,
        public string $action,
        public array $environment,
        public array $runtimeRestrictions,
    ) {
    }
}
```

---

### 1920. Authorization context integrity

El contexto utilizado por el motor de autorización deberá derivarse de fuentes validadas y no de parámetros libres del Controller.

---

### 1921. Route-derived authorization metadata

La acción y el tipo de recurso podrán resolverse desde metadata compilada de ruta.

```php
#[Authorize(
    action: 'invoice.approve',
    resource: 'invoice',
)]
```

---

### 1922. Request-derived resource identifiers

Los IDs de recursos podrán originarse en parámetros de ruta, pero deberán validarse contra:

* tenant;
* ownership;
* tipo esperado;
* policy;
* scope.

---

### 1923. RequestIdentityEnvelope

```php
final readonly class RequestIdentityEnvelope
{
    public function __construct(
        public string $envelopeId,
        public SecurityContext $context,
        public string $requestId,
        public string $method,
        public string $target,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
        public string $nonce,
        public DigitalSignature $signature,
    ) {
    }
}
```

---

### 1924. Identity envelope purpose

El envelope deberá proteger la identidad cuando atraviese una frontera donde headers ordinarios no sean confiables.

---

### 1925. Signed security contexts

Los contextos propagados deberán firmarse o autenticarse criptográficamente cuando no viajen dentro de un canal que proporcione identidad end-to-end suficiente.

---

### 1926. SecurityContextSigner

```php
interface SecurityContextSignerInterface
{
    public function sign(
        SecurityContext $context,
        SecurityContextSigningContext $signingContext
    ): SignedSecurityContext;

    public function verify(
        SignedSecurityContext $context,
        SecurityContextVerificationContext $verificationContext
    ): SecurityContextVerificationResult;
}
```

---

### 1927. SignedSecurityContext

```php
final readonly class SignedSecurityContext
{
    public function __construct(
        public string $payload,
        public string $format,
        public string $issuer,
        public string $audience,
        public string $keyId,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public string $contextId,
        public string $nonce,
        public string $signature,
    ) {
    }
}
```

---

### 1928. Context serialization format

El formato deberá:

* ser canónico;
* versionarse;
* limitar tamaño;
* evitar serialización PHP nativa;
* soportar validación estricta;
* mantener tipos explícitos;
* rechazar campos desconocidos según profile.

---

### 1929. Context format versioning

Toda representación propagada deberá incluir versión de esquema.

---

### 1930. Context claim allowlist

Solo deberán propagarse campos incluidos en una allowlist para la frontera específica.

---

### 1931. Sensitive context data

No deberán propagarse salvo necesidad:

* credenciales;
* secretos;
* factores MFA;
* hashes;
* información personal extensa;
* atributos administrativos innecesarios;
* evidencia biométrica.

---

### 1932. SecurityContextPropagationPolicy

```php
interface SecurityContextPropagationPolicyInterface
{
    public function evaluate(
        SecurityContext $context,
        SecurityBoundary $boundary
    ): SecurityContextPropagationDecision;
}
```

---

### 1933. SecurityContextPropagationDecision

```php
final readonly class SecurityContextPropagationDecision
{
    public function __construct(
        public bool $allowed,
        public array $includedFields,
        public array $removedScopes,
        public array $addedRestrictions,
        public DateInterval $maximumLifetime,
        public bool $signatureRequired,
        public bool $encryptionRequired,
        public array $denialReasons,
    ) {
    }
}
```

---

### 1934. SecurityBoundary

```php
final readonly class SecurityBoundary
{
    public function __construct(
        public string $sourceService,
        public string $destinationService,
        public string $sourceTrustDomain,
        public string $destinationTrustDomain,
        public string $environment,
        public string $tenantId,
        public SecurityBoundaryType $type,
    ) {
    }
}
```

---

### 1935. SecurityBoundaryType

```php
enum SecurityBoundaryType: string
{
    case InProcess = 'in_process';
    case InternalService = 'internal_service';
    case ExternalService = 'external_service';
    case Queue = 'queue';
    case EventBus = 'event_bus';
    case Scheduler = 'scheduler';
    case Gateway = 'gateway';
    case CrossTenant = 'cross_tenant';
    case CrossRegion = 'cross_region';
}
```

---

### 1936. Boundary-specific validation

Cada receptor deberá reevaluar el contexto según su propia frontera y no asumir que la validación previa es suficiente.

---

### 1937. Context attenuation

Propagar un contexto deberá reducir privilegios por defecto.

---

### 1938. SecurityContextAttenuator

```php
interface SecurityContextAttenuatorInterface
{
    public function attenuate(
        SecurityContext $context,
        ContextAttenuationPolicy $policy
    ): SecurityContext;
}
```

---

### 1939. ContextAttenuationPolicy

```php
final readonly class ContextAttenuationPolicy
{
    public function __construct(
        public array $allowedScopes,
        public array $allowedRoles,
        public array $requiredRestrictions,
        public array $allowedAudiences,
        public DateInterval $maximumLifetime,
        public int $maximumDelegationDepth,
    ) {
    }
}
```

---

### 1940. Attenuation invariants

La atenuación no deberá:

* agregar scopes;
* elevar assurance;
* ampliar audiences;
* extender expiration;
* remover restricciones;
* eliminar provenance.

---

### 1941. No privilege amplification

Un servicio downstream no deberá recibir más autoridad que la poseída por el contexto upstream y la permitida por su policy local.

---

### 1942. Context audience restriction

Todo contexto propagado deberá declarar destinatario específico.

---

### 1943. Context target binding

El envelope podrá ligarse a:

* service ID;
* endpoint;
* HTTP method;
* queue;
* topic;
* job type;
* operation.

---

### 1944. Context freshness

Los contextos propagados deberán tener vida corta.

---

### 1945. SecurityContextLifetimePolicy

```php
final readonly class SecurityContextLifetimePolicy
{
    public function __construct(
        public DateInterval $maximumLifetime,
        public DateInterval $maximumClockSkew,
        public bool $singleUseRequired,
        public bool $renewalAllowed,
    ) {
    }
}
```

---

### 1946. Context renewal

La renovación deberá reconstruir el contexto desde evidencia vigente y no limitarse a cambiar la fecha de expiración.

---

### 1947. Stale authorization prevention

Un contexto deberá invalidarse cuando cambien:

* permisos;
* roles;
* tenant membership;
* credential version;
* session state;
* risk status;
* account state.

---

### 1948. Authorization version binding

El contexto podrá incluir la versión de autorización utilizada al crearse.

---

### 1949. Context replay protection

Los contextos sensibles deberán incluir:

* context ID;
* nonce;
* issue time;
* expiration;
* audience;
* request binding.

---

### 1950. SecurityContextReplayRegistry

```php
interface SecurityContextReplayRegistryInterface
{
    public function consume(
        string $contextId,
        string $nonce,
        string $audience,
        DateTimeImmutable $expiresAt
    ): SecurityContextReplayResult;
}
```

---

### 1951. Single-use contexts

Deberán considerarse para:

* operaciones privilegiadas;
* approvals;
* cambios de credenciales;
* recuperación;
* ejecución de comandos críticos;
* workflows financieros.

---

### 1952. Context reuse policy

Los contextos reutilizables deberán limitarse por:

* vida;
* audience;
* method;
* operation;
* resource;
* session;
* sender.

---

### 1953. Trusted proxy identity

VoltStack no deberá confiar automáticamente en headers de identidad añadidos por proxies.

---

### 1954. TrustedProxyDefinition

```php
final readonly class TrustedProxyDefinition
{
    public function __construct(
        public string $proxyId,
        public array $networkRanges,
        public array $allowedHeaders,
        public CertificateReference|JsonWebKey $verificationCredential,
        public TrustedProxyState $state,
        public array $allowedDestinations,
    ) {
    }
}
```

---

### 1955. TrustedProxyState

```php
enum TrustedProxyState: string
{
    case Active = 'active';
    case Rotating = 'rotating';
    case Suspended = 'suspended';
    case Compromised = 'compromised';
    case Retired = 'retired';
}
```

---

### 1956. Proxy identity header sanitation

Antes de aceptar una request desde un proxy confiable, el proxy deberá eliminar cualquier header de identidad enviado por el cliente.

---

### 1957. Direct access protection

El servicio deberá rechazar headers de identidad cuando la request no provenga del proxy autorizado.

---

### 1958. Gateway identity assertions

Los gateways deberán emitir assertions firmadas y ligadas al request.

---

### 1959. GatewayIdentityAssertion

```php
final readonly class GatewayIdentityAssertion
{
    public function __construct(
        public string $assertionId,
        public string $gatewayId,
        public SecurityContext $context,
        public string $requestMethod,
        public string $requestTarget,
        public string $bodyDigest,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public string $nonce,
        public DigitalSignature $signature,
    ) {
    }
}
```

---

### 1960. Gateway assertion verification

El backend deberá validar:

* gateway;
* firma;
* key state;
* audience;
* método;
* target;
* body digest;
* timestamp;
* nonce;
* tenant;
* contexto.

---

### 1961. Header-based identity limitations

Los headers podrán transportar assertions, pero su seguridad dependerá de la firma y del canal, no del nombre del header.

---

### 1962. Service-to-service context propagation

Entre servicios deberá propagarse únicamente la información necesaria para la operación downstream.

---

### 1963. Service actor preservation

Cuando un servicio actúe en nombre de un usuario, el contexto deberá conservar:

* user subject;
* calling service actor;
* original actor cuando exista;
* delegation chain.

---

### 1964. Service identity addition

Cada salto deberá añadir su propia identidad como actor actual o elemento de la cadena, sin eliminar actores previos.

---

### 1965. DelegationChainEntry

```php
final readonly class DelegationChainEntry
{
    public function __construct(
        public IdentityIdentifier|string $delegator,
        public IdentityIdentifier|string $delegate,
        public string $serviceId,
        public array $scopes,
        public array $resources,
        public DateTimeImmutable $delegatedAt,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 1966. Delegation chain integrity

La cadena deberá protegerse criptográficamente para impedir:

* eliminación de saltos;
* inserción;
* reordenamiento;
* cambio de scopes;
* cambio de recursos.

---

### 1967. Delegation depth enforcement

Cada servicio deberá validar la profundidad antes de aceptar o continuar una delegación.

---

### 1968. Delegation provenance

La cadena deberá permitir determinar:

* quién inició;
* qué servicios intervinieron;
* qué privilegios se redujeron;
* qué operación se solicitó;
* cuándo ocurrió cada salto.

---

### 1969. Context propagation over HTTP

La propagación HTTP podrá usar:

* sender-constrained token;
* signed identity envelope;
* mTLS plus signed context;
* token exchange;
* gateway assertion.

---

### 1970. Context propagation over RPC

Los protocolos RPC deberán utilizar metadata protegida y contracts tipados.

---

### 1971. Context propagation over message brokers

Los mensajes asíncronos deberán incluir un envelope de seguridad independiente del envelope de negocio.

---

### 1972. AsyncSecurityEnvelope

```php
final readonly class AsyncSecurityEnvelope
{
    public function __construct(
        public string $envelopeId,
        public SecurityContext $context,
        public string $messageId,
        public string $destination,
        public string $messageType,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public string $payloadDigest,
        public DigitalSignature $signature,
    ) {
    }
}
```

---

### 1973. Async context lifetime

La expiración deberá considerar:

* delay;
* retry;
* dead-letter queues;
* scheduling;
* offline consumers.

No deberá asignarse una vida ilimitada por conveniencia.

---

### 1974. Expired async contexts

Cuando un mensaje llegue con contexto expirado, el consumidor deberá:

* rechazarlo;
* reautorizarlo;
* moverlo a quarantine;
* solicitar nueva autorización;

según la policy.

---

### 1975. Async authorization reevaluation

Para operaciones sensibles, el consumidor deberá reevaluar permisos en el momento de ejecución.

---

### 1976. Queue identity architecture

Cada job deberá tener identidad de ejecución explícita.

---

### 1977. JobExecutionIdentity

```php
final readonly class JobExecutionIdentity
{
    public function __construct(
        public string $jobId,
        public JobIdentityType $type,
        public IdentityIdentifier|string $initiator,
        public IdentityIdentifier|string $executor,
        public string $tenantId,
        public array $scopes,
        public array $restrictions,
    ) {
    }
}
```

---

### 1978. JobIdentityType

```php
enum JobIdentityType: string
{
    case UserInitiated = 'user_initiated';
    case ServiceInitiated = 'service_initiated';
    case Scheduled = 'scheduled';
    case SystemMaintenance = 'system_maintenance';
    case EventTriggered = 'event_triggered';
    case Administrative = 'administrative';
}
```

---

### 1979. Job initiator and executor

El job deberá distinguir:

* quién solicitó;
* qué worker ejecutó;
* bajo qué servicio;
* para qué tenant;
* con qué autoridad.

---

### 1980. Job identity attenuation

Un job no deberá heredar automáticamente todos los permisos interactivos del usuario iniciador.

---

### 1981. JobAuthorizationPolicy

```php
interface JobAuthorizationPolicyInterface
{
    public function evaluate(
        JobExecutionIdentity $identity,
        JobDefinition $job,
        JobExecutionContext $context
    ): JobAuthorizationDecision;
}
```

---

### 1982. Delayed job authorization

Cuando exista un retraso significativo, deberán reevaluarse:

* account state;
* tenant membership;
* role;
* resource ownership;
* approval state;
* policy version.

---

### 1983. Job payload identity protection

El contexto de identidad no deberá almacenarse como array PHP libre ni mezclarse sin firma con el payload.

---

### 1984. Job retry identity

Cada retry deberá conservar el contexto original y registrar el intento actual.

---

### 1985. Dead-letter queue security

Los mensajes enviados a DLQ deberán conservar:

* contexto;
* motivo;
* historial;
* integridad;
* clasificación.

El acceso a la DLQ deberá estar restringido.

---

### 1986. Scheduled task identities

Las tareas programadas deberán ejecutar bajo identidades de sistema específicas y no bajo una identidad global omnipotente.

---

### 1987. ScheduledTaskIdentity

```php
final readonly class ScheduledTaskIdentity
{
    public function __construct(
        public string $taskId,
        public string $serviceIdentityId,
        public string $tenantScope,
        public array $allowedOperations,
        public array $allowedResources,
        public ScheduledTaskState $state,
    ) {
    }
}
```

---

### 1988. ScheduledTaskState

```php
enum ScheduledTaskState: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Restricted = 'restricted';
    case Compromised = 'compromised';
    case Retired = 'retired';
}
```

---

### 1989. Scheduled task tenant scope

Una tarea podrá ser:

* global control plane;
* single tenant;
* tenant batch;
* shard-specific;
* region-specific.

El alcance deberá declararse explícitamente.

---

### 1990. Event consumer identities

Cada consumidor de eventos deberá poseer identidad propia y autorización por topic y event type.

---

### 1991. EventConsumerIdentity

```php
final readonly class EventConsumerIdentity
{
    public function __construct(
        public string $consumerId,
        public string $serviceIdentityId,
        public array $allowedTopics,
        public array $allowedEventTypes,
        public array $tenantScopes,
        public EventConsumerState $state,
    ) {
    }
}
```

---

### 1992. Event authenticity

El consumidor deberá validar:

* producer;
* event signature;
* schema;
* topic;
* event type;
* tenant;
* freshness;
* replay;
* payload digest.

---

### 1993. Event actor propagation

Los eventos derivados de una acción de usuario deberán conservar actor y subject cuando sean relevantes para auditoría o autorización.

---

### 1994. Event context minimization

Los eventos de dominio no deberán transformarse en contenedores completos de identidad.

Solo deberá propagarse la información necesaria.

---

### 1995. Distributed tracing security

Las trazas distribuidas deberán correlacionar operaciones sin exponer secretos ni claims sensibles.

---

### 1996. SecurityTraceContext

```php
final readonly class SecurityTraceContext
{
    public function __construct(
        public string $traceId,
        public string $spanId,
        public string $securityContextId,
        public string $tenantId,
        public ?string $subjectReference,
        public ?string $actorReference,
        public string $classification,
    ) {
    }
}
```

---

### 1997. Trace identity minimization

Las trazas deberán utilizar identificadores opacos o pseudónimos cuando no sea necesario incluir identidades directas.

---

### 1998. Security context governance

VoltStack deberá mantener políticas e inventario sobre:

* issuers;
* formatos;
* audiences;
* trust boundaries;
* propagation rules;
* maximum lifetimes;
* delegation depth;
* queue identities;
* scheduled identities;
* trusted proxies;
* gateway assertions;
* exceptions.

---

### 1999. Security context audit events

Eventos recomendados:

* `SecurityContextCreated`;
* `SecurityContextAttenuated`;
* `SecurityContextPropagated`;
* `SecurityContextPropagationDenied`;
* `SecurityContextValidated`;
* `SecurityContextValidationFailed`;
* `SecurityContextExpired`;
* `SecurityContextReplayDetected`;
* `GatewayIdentityAssertionAccepted`;
* `GatewayIdentityAssertionRejected`;
* `TrustedProxyIdentityAccepted`;
* `UntrustedIdentityHeaderDetected`;
* `DelegationChainExtended`;
* `DelegationChainRejected`;
* `AsyncSecurityEnvelopeCreated`;
* `AsyncSecurityEnvelopeRejected`;
* `JobIdentityCreated`;
* `JobAuthorizationDenied`;
* `ScheduledTaskIdentityUsed`;
* `EventConsumerIdentityRejected`.

---

### 2000. Resultado de esta entrega

Esta entrega establece:

```text
Security Context Architecture
Security Context Immutability
Actor and Subject Separation
Security Context Provenance
Authorization Context Integrity
Request Identity Envelopes
Signed Security Contexts
Canonical Context Serialization
Context Claim Allowlists
Security Context Propagation Policies
Security Boundaries
Context Attenuation
No Privilege Amplification
Audience and Target Binding
Context Freshness
Authorization Version Binding
Context Replay Protection
Trusted Proxy Identity
Gateway Identity Assertions
Gateway Assertion Request Binding
Service-to-Service Identity Propagation
Delegation Chain Propagation
Delegation Chain Integrity
HTTP and RPC Context Propagation
Async Security Envelopes
Queue and Job Identities
Delayed Authorization Reevaluation
Job Retry Identity
Dead-Letter Queue Security
Scheduled Task Identities
Event Consumer Identities
Event Authenticity
Distributed Tracing Security
Security Context Governance
Security Context Audit Events
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 21

- Identity-aware messaging architecture
- Secure command envelopes
- Secure event envelopes
- Message producer identities
- Message consumer authorization
- Topic and queue authorization
- Message integrity
- Message confidentiality
- Message signatures
- Message replay prevention
- Message ordering security
- Idempotency architecture
- Poison message handling
- Dead-letter security
- Delayed message authorization
- Cross-tenant message isolation
- Event provenance
- Event chain integrity
- Messaging incident response
- Messaging security governance
```

## Entrega 21

**Documento:** Parte 05
**Entrega:** 21 de varias
**Cobertura:** Secciones **2001–2100**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 20`

---

### 2001. Identity-Aware Messaging Architecture

VoltStack deberá incorporar una arquitectura de mensajería consciente de identidad para commands, events, jobs, notifications y mensajes de integración.

La arquitectura deberá asegurar que cada mensaje pueda asociarse con:

* producer;
* initiator;
* actor;
* subject;
* tenant;
* workload;
* service;
* trust domain;
* authorization context;
* security policy;
* provenance.

---

### 2002. Messaging security goals

La arquitectura deberá garantizar:

* autenticidad del productor;
* integridad del mensaje;
* confidencialidad cuando corresponda;
* autorización por destino;
* aislamiento multi-tenant;
* resistencia a replay;
* idempotencia;
* trazabilidad;
* propagación mínima de identidad;
* validación por consumidor;
* recuperación segura.

---

### 2003. Messaging threat model

El modelo deberá considerar:

* mensajes falsificados;
* producer spoofing;
* modificación de payload;
* replay;
* duplicate delivery;
* queue poisoning;
* cross-tenant routing;
* topic injection;
* consumer impersonation;
* unauthorized subscription;
* metadata manipulation;
* reordering malicioso;
* delayed privilege abuse;
* exposure en dead-letter queues;
* signature stripping;
* downgrade a mensajes no firmados;
* event-chain tampering.

---

### 2004. Messaging security pipeline

```text
Business Operation
        ↓
Producer Identity Resolution
        ↓
Message Authorization
        ↓
Security Envelope Construction
        ↓
Payload Digest / Encryption
        ↓
Signature
        ↓
Broker Publication
        ↓
Consumer Identity Validation
        ↓
Envelope Verification
        ↓
Replay and Idempotency Checks
        ↓
Consumer Authorization
        ↓
Business Handler
```

---

### 2005. SecureMessagingService

```php
interface SecureMessagingServiceInterface
{
    public function publish(
        SecureMessage $message,
        MessagePublicationContext $context
    ): MessagePublicationResult;

    public function consume(
        ReceivedMessage $message,
        MessageConsumptionContext $context
    ): MessageConsumptionResult;
}
```

---

### 2006. SecureMessage

```php
final readonly class SecureMessage
{
    public function __construct(
        public string $messageId,
        public string $messageType,
        public string $destination,
        public string $tenantId,
        public MessagePayload $payload,
        public MessageSecurityEnvelope $security,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 2007. MessagePayload

```php
final readonly class MessagePayload
{
    public function __construct(
        public string $contentType,
        public string $schemaId,
        public int $schemaVersion,
        public string $encodedPayload,
        public string $digest,
        public MessageClassification $classification,
    ) {
    }
}
```

---

### 2008. MessageClassification

```php
enum MessageClassification: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Sensitive = 'sensitive';
    case Restricted = 'restricted';
    case Privileged = 'privileged';
}
```

---

### 2009. Message category separation

VoltStack deberá distinguir al menos:

* commands;
* domain events;
* integration events;
* jobs;
* notifications;
* control messages;
* security events.

Cada categoría deberá tener políticas propias.

---

### 2010. MessageCategory

```php
enum MessageCategory: string
{
    case Command = 'command';
    case DomainEvent = 'domain_event';
    case IntegrationEvent = 'integration_event';
    case Job = 'job';
    case Notification = 'notification';
    case Control = 'control';
    case Security = 'security';
}
```

---

### 2011. Command versus event semantics

Un command representa una intención dirigida.

Un event representa un hecho ocurrido.

La arquitectura de seguridad deberá impedir tratar events como commands implícitos sin autorización adicional.

---

### 2012. Secure command envelopes

Todo command sensible deberá incluir un envelope de seguridad verificable.

---

### 2013. SecureCommandEnvelope

```php
final readonly class SecureCommandEnvelope
{
    public function __construct(
        public string $commandId,
        public string $commandType,
        public string $targetService,
        public string $targetOperation,
        public SecurityContext $securityContext,
        public string $payloadDigest,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public string $nonce,
        public DigitalSignature $signature,
    ) {
    }
}
```

---

### 2014. Command authorization

Antes de publicar un command deberá validarse:

* producer identity;
* initiator;
* tenant;
* target service;
* target operation;
* scopes;
* resource;
* business policy;
* command lifetime.

---

### 2015. CommandPublicationPolicy

```php
interface CommandPublicationPolicyInterface
{
    public function evaluate(
        SecureCommandEnvelope $command,
        MessagePublicationContext $context
    ): CommandPublicationDecision;
}
```

---

### 2016. CommandPublicationDecision

```php
final readonly class CommandPublicationDecision
{
    public function __construct(
        public bool $allowed,
        public array $requiredScopes,
        public array $addedRestrictions,
        public DateInterval $maximumLifetime,
        public bool $encryptionRequired,
        public bool $singleUseRequired,
        public array $denialReasons,
    ) {
    }
}
```

---

### 2017. Command target binding

Un command deberá ligarse explícitamente a:

* service;
* operation;
* resource;
* tenant;
* destination;
* schema version.

---

### 2018. Command replay protection

Los commands sensibles deberán ser single-use o utilizar controles equivalentes.

---

### 2019. CommandReplayRegistry

```php
interface CommandReplayRegistryInterface
{
    public function consume(
        string $commandId,
        string $targetService,
        DateTimeImmutable $expiresAt
    ): CommandReplayResult;
}
```

---

### 2020. Command expiration

Un command expirado no deberá ejecutarse salvo que una policy explícita permita reautorización.

---

### 2021. Secure event envelopes

Los events deberán incluir metadata de autenticidad y provenance.

---

### 2022. SecureEventEnvelope

```php
final readonly class SecureEventEnvelope
{
    public function __construct(
        public string $eventId,
        public string $eventType,
        public string $producerId,
        public string $tenantId,
        public ?SecurityContext $originatingContext,
        public string $payloadDigest,
        public DateTimeImmutable $occurredAt,
        public DateTimeImmutable $publishedAt,
        public EventProvenance $provenance,
        public DigitalSignature $signature,
    ) {
    }
}
```

---

### 2023. EventProvenance

```php
final readonly class EventProvenance
{
    public function __construct(
        public string $originService,
        public string $originInstance,
        public string $trustDomain,
        public ?string $correlationId,
        public ?string $causationId,
        public array $parentEventIds,
        public array $evidenceReferences,
    ) {
    }
}
```

---

### 2024. Event origin integrity

El origin de un event deberá derivarse de credenciales del productor y no de un campo de payload controlado por la aplicación.

---

### 2025. Message producer identity

Todo productor deberá poseer identidad registrada.

---

### 2026. MessageProducerIdentity

```php
final readonly class MessageProducerIdentity
{
    public function __construct(
        public string $producerId,
        public string $serviceIdentityId,
        public string $tenantScope,
        public array $allowedDestinations,
        public array $allowedMessageTypes,
        public MessageProducerState $state,
    ) {
    }
}
```

---

### 2027. MessageProducerState

```php
enum MessageProducerState: string
{
    case Active = 'active';
    case Restricted = 'restricted';
    case Suspended = 'suspended';
    case Compromised = 'compromised';
    case Retired = 'retired';
}
```

---

### 2028. Producer authentication

El broker o gateway de mensajería deberá autenticar productores mediante:

* mTLS;
* workload identity;
* sender-constrained token;
* signed request;
* short-lived service credential.

---

### 2029. Producer authorization

Un productor no deberá publicar libremente en cualquier destination.

---

### 2030. ProducerAuthorizationPolicy

```php
interface ProducerAuthorizationPolicyInterface
{
    public function evaluate(
        MessageProducerIdentity $producer,
        MessagePublicationRequest $request
    ): ProducerAuthorizationDecision;
}
```

---

### 2031. Producer destination restrictions

La policy deberá limitar:

* queues;
* topics;
* exchanges;
* partitions;
* tenant namespaces;
* message types;
* schema versions.

---

### 2032. Producer spoofing prevention

El valor `producerId` deberá ser establecido o validado por infraestructura confiable.

---

### 2033. Message consumer identity

Todo consumidor deberá poseer identidad propia.

---

### 2034. MessageConsumerIdentity

```php
final readonly class MessageConsumerIdentity
{
    public function __construct(
        public string $consumerId,
        public string $serviceIdentityId,
        public array $subscriptions,
        public array $allowedMessageTypes,
        public array $tenantScopes,
        public MessageConsumerState $state,
    ) {
    }
}
```

---

### 2035. MessageConsumerState

```php
enum MessageConsumerState: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Restricted = 'restricted';
    case Suspended = 'suspended';
    case Compromised = 'compromised';
    case Retired = 'retired';
}
```

---

### 2036. Consumer authentication

El broker deberá autenticar al consumidor antes de permitir:

* subscription;
* read;
* acknowledge;
* reject;
* dead-letter access;
* replay.

---

### 2037. Consumer authorization

Un consumidor deberá autorizarse por:

* destination;
* message type;
* tenant;
* schema;
* operation;
* classification.

---

### 2038. ConsumerAuthorizationPolicy

```php
interface ConsumerAuthorizationPolicyInterface
{
    public function evaluate(
        MessageConsumerIdentity $consumer,
        ReceivedMessageMetadata $message
    ): ConsumerAuthorizationDecision;
}
```

---

### 2039. Consumer least privilege

El consumidor deberá recibir únicamente mensajes necesarios para su función.

---

### 2040. Topic and queue authorization

VoltStack deberá modelar autorización por recursos de mensajería.

---

### 2041. MessagingDestination

```php
final readonly class MessagingDestination
{
    public function __construct(
        public string $destinationId,
        public MessagingDestinationType $type,
        public string $tenantScope,
        public MessageClassification $maximumClassification,
        public array $allowedProducers,
        public array $allowedConsumers,
        public MessagingDestinationState $state,
    ) {
    }
}
```

---

### 2042. MessagingDestinationType

```php
enum MessagingDestinationType: string
{
    case Queue = 'queue';
    case Topic = 'topic';
    case Exchange = 'exchange';
    case Stream = 'stream';
    case DeadLetterQueue = 'dead_letter_queue';
    case ControlChannel = 'control_channel';
}
```

---

### 2043. MessagingDestinationState

```php
enum MessagingDestinationState: string
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Restricted = 'restricted';
    case Quarantined = 'quarantined';
    case Retired = 'retired';
}
```

---

### 2044. Destination naming

Los nombres deberán:

* evitar colisiones;
* incluir environment;
* incluir tenant scope cuando corresponda;
* impedir injection;
* usar formatos canónicos;
* evitar confiar en nombres suministrados libremente.

---

### 2045. Dynamic destination creation

La creación dinámica de queues o topics deberá requerir policy y límites explícitos.

---

### 2046. Wildcard subscription restrictions

Las subscriptions con wildcards deberán:

* restringirse;
* auditarse;
* limitarse por namespace;
* prohibirse para datos privilegiados cuando sea viable.

---

### 2047. Cross-tenant subscription prohibition

Un consumidor single-tenant no deberá suscribirse a destinos de otros tenants.

---

### 2048. Message integrity

Cada mensaje deberá protegerse contra modificación.

---

### 2049. Payload digest

El digest deberá calcularse sobre una representación canónica o sobre bytes exactos claramente definidos.

---

### 2050. MessageDigestService

```php
interface MessageDigestServiceInterface
{
    public function digest(
        string $payload,
        MessageDigestProfile $profile
    ): string;

    public function verify(
        string $payload,
        string $expectedDigest,
        MessageDigestProfile $profile
    ): bool;
}
```

---

### 2051. Metadata integrity

La protección deberá cubrir también metadata crítica:

* message ID;
* type;
* producer;
* tenant;
* destination;
* timestamps;
* schema;
* classification;
* causation ID.

---

### 2052. Message signatures

Los mensajes que atraviesen trust boundaries deberán firmarse.

---

### 2053. MessageSigner

```php
interface MessageSignerInterface
{
    public function sign(
        MessageSigningInput $input,
        MessageSigningContext $context
    ): MessageSignature;

    public function verify(
        MessageSigningInput $input,
        MessageSignature $signature,
        MessageVerificationContext $context
    ): MessageSignatureVerificationResult;
}
```

---

### 2054. MessageSigningInput

```php
final readonly class MessageSigningInput
{
    public function __construct(
        public string $messageId,
        public string $messageType,
        public string $destination,
        public string $tenantId,
        public string $payloadDigest,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 2055. MessageSignature

```php
final readonly class MessageSignature
{
    public function __construct(
        public string $algorithm,
        public string $keyId,
        public string $signature,
        public DateTimeImmutable $signedAt,
        public string $profileId,
    ) {
    }
}
```

---

### 2056. Signature key resolution

El consumidor deberá resolver claves desde una fuente confiable y validar:

* issuer;
* key ID;
* algorithm;
* key state;
* rotation status;
* compromise status.

---

### 2057. Signature downgrade prevention

Un destination configurado para mensajes firmados no deberá aceptar mensajes unsigned.

---

### 2058. Message confidentiality

Los mensajes sensibles deberán cifrarse cuando el broker o la infraestructura no proporcionen protección end-to-end suficiente.

---

### 2059. MessageEncryptionService

```php
interface MessageEncryptionServiceInterface
{
    public function encrypt(
        MessagePayload $payload,
        MessageEncryptionContext $context
    ): EncryptedMessagePayload;

    public function decrypt(
        EncryptedMessagePayload $payload,
        MessageDecryptionContext $context
    ): MessagePayload;
}
```

---

### 2060. EncryptedMessagePayload

```php
final readonly class EncryptedMessagePayload
{
    public function __construct(
        public string $algorithm,
        public string $keyReference,
        public string $ciphertext,
        public string $nonce,
        public string $authenticationTag,
        public array $associatedData,
    ) {
    }
}
```

---

### 2061. Associated data

El cifrado deberá autenticar metadata crítica mediante associated data.

---

### 2062. Per-message data keys

Para mensajes de alta sensibilidad podrá utilizarse un DEK único por mensaje protegido mediante envelope encryption.

---

### 2063. Broker confidentiality limitations

TLS entre cliente y broker no protege necesariamente el mensaje:

* dentro del broker;
* en storage;
* en replicas;
* en backups;
* frente a administradores del broker.

---

### 2064. End-to-end encryption

Cuando sea requerido, solo productores y consumidores autorizados deberán poder descifrar el payload.

---

### 2065. Consumer group key access

Los permisos criptográficos deberán alinearse con consumer groups y tenant scope.

---

### 2066. Message replay prevention

VoltStack deberá detectar reenvío no autorizado del mismo mensaje.

---

### 2067. MessageReplayRegistry

```php
interface MessageReplayRegistryInterface
{
    public function register(
        string $messageId,
        string $destination,
        string $consumerId,
        DateTimeImmutable $expiresAt
    ): MessageReplayResult;
}
```

---

### 2068. Replay versus redelivery

La arquitectura deberá distinguir:

* redelivery legítima;
* retry;
* consumer failover;
* manual replay autorizado;
* replay malicioso.

---

### 2069. Replay decision inputs

La decisión deberá considerar:

* message ID;
* delivery attempt;
* broker metadata;
* producer;
* destination;
* signature;
* expiration;
* prior processing state.

---

### 2070. Manual replay authorization

Reprocesar mensajes históricos deberá requerir:

* autorización;
* rango definido;
* justificación;
* actor;
* tenant;
* dry-run cuando aplique;
* auditoría.

---

### 2071. Message ordering security

El orden deberá protegerse cuando afecte seguridad o integridad de negocio.

---

### 2072. SequenceNumber

```php
final readonly class SequenceNumber
{
    public function __construct(
        public string $streamId,
        public int $sequence,
        public string $producerId,
    ) {
    }
}
```

---

### 2073. Sequence validation

El consumidor podrá detectar:

* gaps;
* duplicates;
* rollback;
* out-of-order messages;
* producer reset.

---

### 2074. Ordering trust scope

El orden deberá definirse por:

* aggregate;
* tenant;
* partition;
* stream;
* producer;
* resource.

---

### 2075. Ordering attack prevention

No deberá confiarse en sequence numbers no autenticados.

---

### 2076. Idempotency architecture

Los consumidores deberán diseñarse para soportar entrega at-least-once.

---

### 2077. IdempotencyKey

```php
final readonly class IdempotencyKey
{
    public function __construct(
        public string $key,
        public string $operation,
        public string $tenantId,
        public string $producerId,
    ) {
    }
}
```

---

### 2078. IdempotencyRegistry

```php
interface IdempotencyRegistryInterface
{
    public function begin(
        IdempotencyKey $key,
        DateTimeImmutable $expiresAt
    ): IdempotencyBeginResult;

    public function complete(
        IdempotencyKey $key,
        string $resultDigest
    ): void;

    public function fail(
        IdempotencyKey $key,
        string $failureCode
    ): void;
}
```

---

### 2079. Idempotency scope

La key deberá ligarse a:

* operation;
* tenant;
* producer;
* target resource;
* message type.

---

### 2080. Idempotency retention

La retención deberá superar la ventana máxima de redelivery esperada.

---

### 2081. Exactly-once claims

VoltStack no deberá asumir exactly-once delivery como garantía universal.

La consistencia deberá construirse con:

* idempotencia;
* transactions;
* outbox;
* inbox;
* deduplication;
* reconciliation.

---

### 2082. Secure outbox pattern

El outbox deberá persistir:

* business change;
* message metadata;
* security metadata;
* payload digest;
* publication state;

dentro de una frontera transaccional apropiada.

---

### 2083. OutboxMessageRecord

```php
final readonly class OutboxMessageRecord
{
    public function __construct(
        public string $outboxId,
        public string $aggregateId,
        public SecureMessage $message,
        public OutboxMessageState $state,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $publishedAt,
    ) {
    }
}
```

---

### 2084. Secure inbox pattern

El consumidor deberá registrar mensajes procesados antes o junto con efectos de negocio críticos.

---

### 2085. InboxMessageRecord

```php
final readonly class InboxMessageRecord
{
    public function __construct(
        public string $messageId,
        public string $consumerId,
        public string $tenantId,
        public InboxMessageState $state,
        public DateTimeImmutable $receivedAt,
        public ?DateTimeImmutable $processedAt,
    ) {
    }
}
```

---

### 2086. Poison message detection

Un poison message es aquel que falla repetidamente de forma no transitoria.

---

### 2087. PoisonMessageAssessment

```php
final readonly class PoisonMessageAssessment
{
    public function __construct(
        public bool $poison,
        public PoisonMessageReason $reason,
        public int $failureCount,
        public array $evidence,
        public PoisonMessageAction $recommendedAction,
    ) {
    }
}
```

---

### 2088. PoisonMessageReason

```php
enum PoisonMessageReason: string
{
    case InvalidSchema = 'invalid_schema';
    case InvalidSignature = 'invalid_signature';
    case UnauthorizedProducer = 'unauthorized_producer';
    case DecryptionFailure = 'decryption_failure';
    case UnsupportedVersion = 'unsupported_version';
    case PersistentBusinessFailure = 'persistent_business_failure';
    case MaliciousPayload = 'malicious_payload';
}
```

---

### 2089. PoisonMessageAction

```php
enum PoisonMessageAction: string
{
    case Retry = 'retry';
    case Quarantine = 'quarantine';
    case DeadLetter = 'dead_letter';
    case Discard = 'discard';
    case Escalate = 'escalate';
}
```

---

### 2090. Retry policy

Los retries deberán distinguir fallos:

* transitorios;
* permanentes;
* de seguridad;
* de autorización;
* de schema;
* de dependencia.

Los fallos de firma o tenant no deberán resolverse mediante retries indefinidos.

---

### 2091. Dead-letter queue security

Las DLQs deberán considerarse repositorios de datos sensibles.

---

### 2092. DeadLetterRecord

```php
final readonly class DeadLetterRecord
{
    public function __construct(
        public string $deadLetterId,
        public SecureMessage $originalMessage,
        public string $consumerId,
        public string $failureCode,
        public array $failureMetadata,
        public DateTimeImmutable $failedAt,
        public DeadLetterState $state,
    ) {
    }
}
```

---

### 2093. DeadLetterState

```php
enum DeadLetterState: string
{
    case Quarantined = 'quarantined';
    case UnderReview = 'under_review';
    case ApprovedForReplay = 'approved_for_replay';
    case Replayed = 'replayed';
    case Discarded = 'discarded';
    case Archived = 'archived';
}
```

---

### 2094. DLQ access controls

El acceso deberá limitarse por:

* role;
* tenant;
* classification;
* operation;
* approval;
* incident;
* environment.

---

### 2095. DLQ payload handling

Las interfaces administrativas deberán:

* redactar secretos;
* evitar renderizado inseguro;
* limitar descargas;
* registrar lecturas;
* impedir ejecución accidental;
* mostrar provenance.

---

### 2096. Delayed message authorization

Un mensaje válido al publicarse puede dejar de estar autorizado al ejecutarse.

---

### 2097. DelayedAuthorizationPolicy

```php
interface DelayedAuthorizationPolicyInterface
{
    public function requiresReevaluation(
        SecureMessage $message,
        MessageConsumptionContext $context
    ): bool;
}
```

---

### 2098. Authorization reevaluation triggers

Se deberá reevaluar cuando:

* expiró el contexto;
* cambió la policy;
* cambió el tenant membership;
* cambió el resource owner;
* fue revocada la delegación;
* cambió el riesgo;
* pasó una ventana temporal significativa;
* el mensaje proviene de DLQ o replay manual.

---

### 2099. Delayed command rejection

Si la autorización ya no es válida, el command deberá:

* rechazarse;
* registrarse;
* notificarse;
* enviarse a una cola de revisión;

sin ejecutar efectos parciales.

---

### 2100. Resultado de esta entrega

Esta entrega establece:

```text
Identity-Aware Messaging Architecture
Messaging Security Pipeline
Secure Command Envelopes
Command Publication Authorization
Command Target Binding
Command Replay Protection
Secure Event Envelopes
Event Provenance
Producer Identities
Producer Authentication
Producer Destination Authorization
Consumer Identities
Consumer Authentication
Consumer Authorization
Topic and Queue Authorization
Destination Isolation
Cross-Tenant Subscription Protection
Message Integrity
Payload and Metadata Digests
Message Signatures
Signature Downgrade Prevention
Message Confidentiality
End-to-End Encryption
Per-Message Data Keys
Message Replay Prevention
Replay versus Redelivery
Manual Replay Authorization
Message Ordering Security
Sequence Validation
Idempotency Architecture
Secure Outbox Pattern
Secure Inbox Pattern
Poison Message Detection
Security-Aware Retry Policies
Dead-Letter Queue Security
DLQ Access Governance
Delayed Message Authorization
Runtime Authorization Reevaluation
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 22

- Cross-tenant messaging isolation
- Tenant-bound destinations
- Tenant-aware partitioning
- Cross-tenant event routing
- Shared broker security
- Messaging namespace policies
- Broker credential isolation
- Broker administration security
- Event chain integrity
- Event hash chains
- Event provenance verification
- Event sourcing security
- Event store access control
- Event redaction
- Crypto-shredding
- Event replay governance
- Messaging incident response
- Broker compromise response
- Messaging observability
- Messaging security governance
```

## Entrega 22

**Documento:** Parte 05
**Entrega:** 22 de varias
**Cobertura:** Secciones **2101–2200**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 21`

---

### 2101. Cross-Tenant Messaging Isolation

VoltStack deberá garantizar aislamiento estricto entre tenants dentro de:

* queues;
* topics;
* streams;
* exchanges;
* partitions;
* consumer groups;
* dead-letter queues;
* retry queues;
* event stores;
* outbox e inbox stores.

El aislamiento no deberá depender únicamente de convenciones de nombres.

---

### 2102. Multi-tenant messaging security goals

La arquitectura deberá garantizar:

* separación lógica o física;
* tenant binding verificable;
* autorización por destino;
* prevención de cross-tenant routing;
* aislamiento de credenciales;
* trazabilidad;
* límites de consumo;
* cifrado por tenant cuando aplique;
* recuperación independiente;
* control de administración.

---

### 2103. Multi-tenant messaging threat model

El modelo deberá considerar:

* publicación en topic incorrecto;
* consumo entre tenants;
* partition leakage;
* wildcard subscriptions;
* tenant spoofing;
* metadata manipulation;
* shared credential abuse;
* broker administrator overreach;
* DLQ cross-tenant exposure;
* event-store query leakage;
* retry routing incorrecto;
* topic naming collisions;
* encryption key reuse;
* replay sobre tenant distinto.

---

### 2104. Tenant-bound messaging pipeline

```text
Producer Identity
       ↓
Tenant Resolution
       ↓
Tenant Binding Validation
       ↓
Destination Resolution
       ↓
Publication Authorization
       ↓
Partition Selection
       ↓
Broker Delivery
       ↓
Consumer Tenant Validation
       ↓
Message Tenant Verification
       ↓
Business Processing
```

---

### 2105. TenantMessagingContext

```php
final readonly class TenantMessagingContext
{
    public function __construct(
        public string $tenantId,
        public string $environment,
        public string $region,
        public TenantMessagingIsolationMode $isolationMode,
        public array $allowedNamespaces,
        public array $restrictions,
    ) {
    }
}
```

---

### 2106. TenantMessagingIsolationMode

```php
enum TenantMessagingIsolationMode: string
{
    case SharedLogical = 'shared_logical';
    case SharedPartitioned = 'shared_partitioned';
    case DedicatedNamespace = 'dedicated_namespace';
    case DedicatedBroker = 'dedicated_broker';
    case DedicatedCluster = 'dedicated_cluster';
}
```

---

### 2107. Isolation mode selection

La selección deberá considerar:

* clasificación de datos;
* tamaño del tenant;
* compliance;
* volumen;
* riesgo;
* residencia de datos;
* tolerancia a fallos;
* coste operativo;
* necesidad de administración separada.

---

### 2108. Dedicated broker requirements

Tenants de alto riesgo podrán requerir:

* broker dedicado;
* red dedicada;
* credenciales dedicadas;
* claves dedicadas;
* monitoring separado;
* administración aislada;
* backups separados.

---

### 2109. Tenant-bound destinations

Cada destination deberá declarar de forma explícita su tenant scope.

---

### 2110. TenantBoundDestination

```php
final readonly class TenantBoundDestination
{
    public function __construct(
        public string $destinationId,
        public MessagingDestinationType $type,
        public TenantScope $tenantScope,
        public string $namespace,
        public string $environment,
        public string $region,
        public DestinationIsolationPolicy $isolationPolicy,
    ) {
    }
}
```

---

### 2111. TenantScope

```php
final readonly class TenantScope
{
    public function __construct(
        public TenantScopeType $type,
        public array $tenantIds,
    ) {
    }
}
```

---

### 2112. TenantScopeType

```php
enum TenantScopeType: string
{
    case SingleTenant = 'single_tenant';
    case TenantGroup = 'tenant_group';
    case ControlPlane = 'control_plane';
    case GlobalShared = 'global_shared';
}
```

---

### 2113. Global shared destination restrictions

Los destinos globales deberán reservarse para:

* control plane;
* metadata no sensible;
* eventos agregados;
* señales de salud;
* operaciones explícitamente globales.

No deberán transportar datos de tenant sin encapsulación y policy específica.

---

### 2114. Tenant destination resolution

```php
interface TenantDestinationResolverInterface
{
    public function resolve(
        string $logicalDestination,
        TenantMessagingContext $context
    ): TenantBoundDestination;
}
```

---

### 2115. Destination resolution integrity

El tenant no deberá seleccionar libremente el nombre físico del destino.

La resolución deberá realizarse desde metadata confiable.

---

### 2116. Messaging namespace policies

VoltStack deberá aplicar namespaces por:

* framework;
* environment;
* region;
* tenant;
* domain;
* message category;
* version.

---

### 2117. MessagingNamespace

```php
final readonly class MessagingNamespace
{
    public function __construct(
        public string $framework,
        public string $environment,
        public string $region,
        public string $tenantScope,
        public string $domain,
        public string $category,
        public int $version,
    ) {
    }
}
```

---

### 2118. Canonical namespace format

Un formato conceptual podrá ser:

```text
voltstack.<environment>.<region>.<tenant>.<domain>.<category>.v<version>
```

La implementación deberá escapar y validar todos los componentes.

---

### 2119. Namespace injection prevention

Los componentes del namespace no deberán aceptar:

* separators arbitrarios;
* wildcards;
* control characters;
* path traversal;
* broker-specific syntax no autorizada.

---

### 2120. Reserved namespaces

VoltStack deberá reservar namespaces para:

* system;
* security;
* audit;
* control;
* provisioning;
* dead-letter;
* quarantine;
* recovery.

---

### 2121. Tenant-aware partitioning

La selección de partition deberá preservar afinidad e aislamiento.

---

### 2122. TenantPartitionKey

```php
final readonly class TenantPartitionKey
{
    public function __construct(
        public string $tenantId,
        public string $aggregateId,
        public ?string $shardId,
        public string $normalizedKey,
    ) {
    }
}
```

---

### 2123. Partition key integrity

La partition key deberá construirse desde valores validados y no desde headers libres.

---

### 2124. Partition leakage prevention

La lógica deberá evitar que datos de un tenant compartan inadvertidamente una partition reservada para otro.

---

### 2125. Hot tenant protection

Un tenant con alto volumen no deberá degradar de forma desproporcionada a otros.

Se deberán aplicar:

* quotas;
* partition allocation;
* backpressure;
* rate limits;
* throughput reservations;
* queue depth limits.

---

### 2126. Tenant message quotas

```php
final readonly class TenantMessagingQuota
{
    public function __construct(
        public int $maximumMessagesPerSecond,
        public int $maximumBytesPerSecond,
        public int $maximumQueueDepth,
        public int $maximumConcurrentConsumers,
        public int $maximumRetainedMessages,
    ) {
    }
}
```

---

### 2127. Quota enforcement location

Los límites deberán aplicarse en:

* producer SDK;
* gateway;
* broker;
* consumer;
* control plane.

No deberá dependerse de un único punto.

---

### 2128. Cross-tenant event routing

Todo routing entre tenants deberá considerarse operación privilegiada.

---

### 2129. CrossTenantRoutingRequest

```php
final readonly class CrossTenantRoutingRequest
{
    public function __construct(
        public string $sourceTenantId,
        public string $targetTenantId,
        public string $eventType,
        public string $purpose,
        public IdentityIdentifier|string $actor,
        public array $requestedFields,
        public DateTimeImmutable $requestedAt,
    ) {
    }
}
```

---

### 2130. CrossTenantRoutingPolicy

```php
interface CrossTenantRoutingPolicyInterface
{
    public function evaluate(
        CrossTenantRoutingRequest $request
    ): CrossTenantRoutingDecision;
}
```

---

### 2131. CrossTenantRoutingDecision

```php
final readonly class CrossTenantRoutingDecision
{
    public function __construct(
        public bool $allowed,
        public array $includedFields,
        public array $removedFields,
        public bool $pseudonymizationRequired,
        public bool $approvalRequired,
        public DateInterval $maximumLifetime,
        public array $denialReasons,
    ) {
    }
}
```

---

### 2132. Cross-tenant routing requirements

La operación deberá requerir:

* finalidad legítima;
* authorization explícita;
* minimización;
* consentimiento o base jurídica cuando aplique;
* auditoría;
* target binding;
* expiración;
* owner definido.

---

### 2133. Tenant data minimization

No deberá propagarse un event completo cuando solo se necesiten algunos campos.

---

### 2134. Tenant pseudonymization

Los identificadores podrán transformarse antes de cruzar fronteras de tenant.

---

### 2135. Cross-tenant correlation identifiers

Los IDs de correlación no deberán permitir enumerar o inferir recursos internos de otro tenant.

---

### 2136. Shared broker security

Los brokers compartidos deberán considerarse infraestructura multi-tenant crítica.

---

### 2137. Shared broker control objectives

Deberán implementarse:

* ACLs;
* namespace isolation;
* quotas;
* encryption;
* tenant-aware logging;
* admin separation;
* resource limits;
* secret rotation;
* broker hardening.

---

### 2138. BrokerCredential

```php
final readonly class BrokerCredential
{
    public function __construct(
        public string $credentialId,
        public string $principalId,
        public string $tenantScope,
        public array $allowedOperations,
        public array $allowedDestinations,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public BrokerCredentialState $state,
    ) {
    }
}
```

---

### 2139. BrokerCredentialState

```php
enum BrokerCredentialState: string
{
    case Active = 'active';
    case Rotating = 'rotating';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
}
```

---

### 2140. Broker credential isolation

No deberán compartirse credenciales entre:

* tenants;
* environments;
* services;
* producer y consumer roles;
* administration y application access.

---

### 2141. Short-lived broker credentials

Cuando sea viable, el broker deberá aceptar credenciales efímeras emitidas mediante:

* workload federation;
* mTLS;
* OIDC;
* short-lived certificates;
* dynamic credentials.

---

### 2142. Broker ACL model

Las ACLs deberán limitar:

* connect;
* publish;
* consume;
* subscribe;
* create;
* delete;
* alter;
* inspect;
* replay;
* administer.

---

### 2143. Producer and consumer role separation

Un principal productor no deberá consumir mensajes salvo necesidad documentada.

---

### 2144. Broker administration security

Las operaciones administrativas deberán usar identidades privilegiadas separadas.

---

### 2145. BrokerAdministratorIdentity

```php
final readonly class BrokerAdministratorIdentity
{
    public function __construct(
        public string $administratorId,
        public IdentityIdentifier $identity,
        public array $allowedOperations,
        public array $allowedClusters,
        public PrivilegedRiskLevel $riskLevel,
        public BrokerAdministratorState $state,
    ) {
    }
}
```

---

### 2146. BrokerAdministratorState

```php
enum BrokerAdministratorState: string
{
    case Eligible = 'eligible';
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
}
```

---

### 2147. Broker administrative elevation

La administración deberá requerir:

* JIT access;
* MFA reciente;
* approval;
* ticket;
* sesión privilegiada;
* duración corta;
* recording cuando sea viable.

---

### 2148. Broker admin operation restrictions

Operaciones críticas incluyen:

* eliminar topics;
* cambiar retención;
* alterar ACLs;
* reconfigurar replicas;
* leer payloads;
* forzar replay;
* purgar queues;
* modificar encryption.

---

### 2149. Four-eyes broker controls

Las acciones destructivas podrán requerir dual control.

---

### 2150. Broker configuration integrity

La configuración deberá:

* versionarse;
* firmarse;
* revisarse;
* desplegarse por pipeline;
* evitar cambios manuales no auditados.

---

### 2151. Broker hardening

VoltStack deberá exigir:

* protocolos seguros;
* TLS actualizado;
* autenticación fuerte;
* network segmentation;
* patching;
* deshabilitar cuentas default;
* limitar plugins;
* proteger management interfaces.

---

### 2152. Broker metadata confidentiality

Los nombres de topics, tenants y schemas pueden revelar información y deberán protegerse.

---

### 2153. Broker audit logging

Deberán registrarse:

* logins;
* publications;
* subscriptions;
* ACL changes;
* topic changes;
* retention changes;
* replays;
* purges;
* credential changes;
* admin access.

---

### 2154. Event chain integrity

VoltStack deberá poder demostrar que una secuencia de events no fue alterada.

---

### 2155. EventChainEntry

```php
final readonly class EventChainEntry
{
    public function __construct(
        public string $eventId,
        public string $streamId,
        public int $sequence,
        public string $eventDigest,
        public ?string $previousChainHash,
        public string $chainHash,
        public DateTimeImmutable $recordedAt,
    ) {
    }
}
```

---

### 2156. Event chain hash

El hash podrá calcularse conceptualmente como:

```text
H(
    stream_id
    || sequence
    || event_digest
    || previous_chain_hash
)
```

---

### 2157. Chain domain separation

El cálculo deberá incluir un identificador de propósito para evitar reutilización del mismo esquema criptográfico en otros dominios.

---

### 2158. EventHashChainService

```php
interface EventHashChainServiceInterface
{
    public function append(
        EventChainEntryInput $input,
        ?EventChainEntry $previous
    ): EventChainEntry;

    public function verify(
        iterable $entries
    ): EventChainVerificationResult;
}
```

---

### 2159. EventChainVerificationResult

```php
final readonly class EventChainVerificationResult
{
    public function __construct(
        public bool $valid,
        public ?int $firstInvalidSequence,
        public ?string $failureReason,
        public array $evidence,
    ) {
    }
}
```

---

### 2160. Event chain limitations

Una hash chain detecta alteraciones, pero no impide que un actor con control total reescriba toda la cadena.

---

### 2161. External checkpoints

Para reforzar integridad, VoltStack podrá publicar checkpoints en:

* append-only ledger;
* external timestamp service;
* immutable object storage;
* audit system independiente;
* transparency log.

---

### 2162. EventChainCheckpoint

```php
final readonly class EventChainCheckpoint
{
    public function __construct(
        public string $streamId,
        public int $sequence,
        public string $chainHash,
        public DateTimeImmutable $createdAt,
        public string $checkpointProvider,
        public DigitalSignature $signature,
    ) {
    }
}
```

---

### 2163. Checkpoint frequency

La frecuencia deberá depender de:

* criticidad;
* volumen;
* RPO;
* compliance;
* capacidad operativa.

---

### 2164. Event provenance verification

El consumidor deberá poder verificar el origen de events sensibles.

---

### 2165. EventProvenanceVerifier

```php
interface EventProvenanceVerifierInterface
{
    public function verify(
        SecureEventEnvelope $event,
        EventProvenanceVerificationContext $context
    ): EventProvenanceVerificationResult;
}
```

---

### 2166. Provenance verification checks

La validación deberá comprobar:

* producer identity;
* trust domain;
* signature;
* key state;
* destination;
* tenant;
* causation;
* schema;
* timestamps;
* payload digest.

---

### 2167. Causation chain integrity

Los campos `causationId` y `parentEventIds` deberán autenticarse para impedir reconstrucciones falsas del flujo.

---

### 2168. Correlation ID trust

Un correlation ID ayuda a observabilidad, pero no deberá considerarse evidencia de autenticidad por sí mismo.

---

### 2169. Event sourcing security architecture

En event sourcing, el event store se convierte en sistema crítico de registro y reconstrucción.

---

### 2170. EventStoreRecord

```php
final readonly class EventStoreRecord
{
    public function __construct(
        public string $eventId,
        public string $streamId,
        public string $aggregateId,
        public string $tenantId,
        public int $sequence,
        public string $eventType,
        public MessagePayload|EncryptedMessagePayload $payload,
        public EventProvenance $provenance,
        public EventChainEntry $chainEntry,
        public DateTimeImmutable $recordedAt,
    ) {
    }
}
```

---

### 2171. Event store append-only policy

Los events confirmados no deberán modificarse in-place.

Correcciones deberán representarse mediante nuevos events.

---

### 2172. Event store write authorization

Solo producers autorizados deberán poder append events a streams específicos.

---

### 2173. Event store read authorization

La lectura deberá limitarse por:

* tenant;
* aggregate;
* stream;
* event type;
* classification;
* purpose;
* retention policy.

---

### 2174. EventStoreAuthorizationPolicy

```php
interface EventStoreAuthorizationPolicyInterface
{
    public function authorizeAppend(
        EventStoreAppendRequest $request
    ): EventStoreAuthorizationDecision;

    public function authorizeRead(
        EventStoreReadRequest $request
    ): EventStoreAuthorizationDecision;
}
```

---

### 2175. Stream ownership

Cada stream deberá declarar:

* tenant;
* aggregate type;
* owning service;
* authorized writers;
* authorized readers;
* classification;
* retention.

---

### 2176. Stream identifier security

El stream ID no deberá permitir acceso sin authorization ni revelar información sensible innecesaria.

---

### 2177. Optimistic concurrency security

La expected version deberá verificarse para evitar:

* lost updates;
* reordering;
* race conditions;
* unauthorized overwrite attempts.

---

### 2178. Snapshot security

Los snapshots deberán:

* derivarse de event streams verificados;
* incluir versión;
* incluir digest;
* respetar tenant;
* cifrarse cuando corresponda;
* invalidarse ante inconsistencias.

---

### 2179. SnapshotRecord

```php
final readonly class SnapshotRecord
{
    public function __construct(
        public string $snapshotId,
        public string $streamId,
        public string $tenantId,
        public int $lastSequence,
        public string $stateDigest,
        public string $encodedState,
        public DateTimeImmutable $createdAt,
        public DigitalSignature $signature,
    ) {
    }
}
```

---

### 2180. Snapshot trust

Un snapshot no deberá aceptarse si la cadena de events previa no puede validarse hasta el checkpoint requerido.

---

### 2181. Event redaction

Los sistemas event-sourced deberán evitar registrar secretos o datos innecesarios desde el origen.

---

### 2182. Redactable event fields

Los schemas deberán marcar explícitamente campos:

* public;
* internal;
* sensitive;
* redactable;
* encrypted;
* non-exportable.

---

### 2183. EventFieldSecurityMetadata

```php
final readonly class EventFieldSecurityMetadata
{
    public function __construct(
        public string $field,
        public MessageClassification $classification,
        public bool $encrypted,
        public bool $redactable,
        public bool $exportable,
        public ?string $retentionPolicyId,
    ) {
    }
}
```

---

### 2184. Event redaction strategy

Cuando exista obligación de supresión, VoltStack podrá aplicar:

* crypto-shredding;
* tokenization;
* detached personal data;
* redaction events;
* access restriction;
* projection rebuild.

---

### 2185. Immutable history and privacy

La inmutabilidad no deberá interpretarse como permiso para conservar indefinidamente datos personales.

---

### 2186. Detached sensitive payloads

Los events podrán almacenar referencias a datos sensibles mantenidos en un store separado y eliminable.

---

### 2187. Crypto-shredding

El cifrado por sujeto, tenant o data domain podrá permitir inutilizar datos destruyendo la clave asociada.

---

### 2188. CryptoShreddingRequest

```php
final readonly class CryptoShreddingRequest
{
    public function __construct(
        public string $keyScopeId,
        public string $tenantId,
        public string $reason,
        public IdentityIdentifier $authorizedBy,
        public DateTimeImmutable $requestedAt,
    ) {
    }
}
```

---

### 2189. Crypto-shredding controls

La destrucción de claves deberá requerir:

* authorization;
* approval;
* scope validation;
* backup consideration;
* legal hold check;
* audit;
* irreversible action warning.

---

### 2190. Projection security

Las proyecciones deberán heredar:

* tenant isolation;
* field-level controls;
* redaction;
* retention;
* authorization.

---

### 2191. Projection rebuild authorization

Reconstruir proyecciones puede exponer grandes volúmenes de datos y deberá considerarse operación privilegiada.

---

### 2192. Event replay governance

El replay deberá ser una operación controlada y no una capacidad libre del consumidor.

---

### 2193. EventReplayRequest

```php
final readonly class EventReplayRequest
{
    public function __construct(
        public string $replayId,
        public string $streamOrTopic,
        public string $tenantScope,
        public ?DateTimeImmutable $from,
        public ?DateTimeImmutable $to,
        public array $eventTypes,
        public ReplayMode $mode,
        public IdentityIdentifier $requestedBy,
        public string $reason,
    ) {
    }
}
```

---

### 2194. ReplayMode

```php
enum ReplayMode: string
{
    case DryRun = 'dry_run';
    case RebuildProjection = 'rebuild_projection';
    case Reprocess = 'reprocess';
    case CompensatingReplay = 'compensating_replay';
    case Forensic = 'forensic';
}
```

---

### 2195. EventReplayPolicy

```php
interface EventReplayPolicyInterface
{
    public function evaluate(
        EventReplayRequest $request
    ): EventReplayDecision;
}
```

---

### 2196. Replay safeguards

Un replay deberá incluir:

* bounded range;
* tenant scope;
* target consumer;
* idempotency strategy;
* rate limits;
* side-effect mode;
* approval;
* monitoring;
* rollback plan.

---

### 2197. Replay side-effect controls

Para rebuilds o análisis deberán poder deshabilitarse:

* emails;
* payments;
* external webhooks;
* notifications;
* irreversible integrations.

---

### 2198. Messaging incident response

VoltStack deberá definir playbooks para incidentes de mensajería.

---

### 2199. MessagingSecurityIncident

```php
final readonly class MessagingSecurityIncident
{
    public function __construct(
        public string $incidentId,
        public MessagingIncidentType $type,
        public ThreatSeverity $severity,
        public array $affectedTenants,
        public array $affectedDestinations,
        public array $affectedCredentials,
        public DateTimeImmutable $detectedAt,
        public MessagingIncidentState $state,
    ) {
    }
}
```

---

### 2200. Resultado de esta entrega

Esta entrega establece:

```text
Cross-Tenant Messaging Isolation
Tenant-Bound Destinations
Tenant-Aware Namespaces
Tenant-Aware Partitioning
Hot Tenant Protection
Messaging Quotas
Cross-Tenant Event Routing
Cross-Tenant Data Minimization
Shared Broker Security
Broker Credential Isolation
Short-Lived Broker Credentials
Producer and Consumer Role Separation
Broker Administration Security
JIT Broker Administration
Four-Eyes Broker Controls
Broker Configuration Integrity
Broker Hardening
Event Chain Integrity
Event Hash Chains
External Chain Checkpoints
Event Provenance Verification
Causation Chain Integrity
Event Sourcing Security
Append-Only Event Stores
Event Store Access Control
Stream Ownership
Optimistic Concurrency Security
Snapshot Security
Event Field Classification
Event Redaction
Detached Sensitive Payloads
Crypto-Shredding
Projection Security
Event Replay Governance
Replay Side-Effect Controls
Messaging Incident Foundations
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 23

- Messaging incident classification
- Broker compromise response
- Producer compromise response
- Consumer compromise response
- Malicious message containment
- Queue quarantine
- Topic quarantine
- Credential emergency rotation
- Message forensic preservation
- Messaging chain of custody
- Messaging observability
- Security metrics
- Broker health security signals
- Messaging anomaly detection
- Cross-tenant leakage detection
- Messaging SIEM integration
- Messaging SOAR playbooks
- Compliance evidence
- Messaging security governance
- Messaging security audit events
```

## Entrega 23

**Documento:** Parte 05
**Entrega:** 23 de varias
**Cobertura:** Secciones **2201–2300**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 22`

---

### 2201. Messaging Incident Response Architecture

VoltStack deberá incorporar una arquitectura formal para detectar, clasificar, contener, investigar, remediar y cerrar incidentes relacionados con mensajería.

La arquitectura deberá cubrir incidentes en:

* brokers;
* productores;
* consumidores;
* topics;
* queues;
* streams;
* event stores;
* dead-letter queues;
* retry queues;
* credenciales;
* firmas;
* cifrado;
* rutas cross-tenant;
* sistemas de replay.

---

### 2202. Messaging incident response goals

La respuesta deberá garantizar:

* detección temprana;
* clasificación consistente;
* contención rápida;
* reducción del impacto;
* preservación de evidencia;
* rotación de credenciales;
* aislamiento de tenants;
* continuidad controlada;
* trazabilidad;
* recuperación verificable;
* aprendizaje posterior.

---

### 2203. Messaging incident threat model

La arquitectura deberá contemplar:

* broker comprometido;
* credencial filtrada;
* producer malicioso;
* consumer comprometido;
* inyección de mensajes;
* replay masivo;
* cross-tenant leakage;
* alteración de eventos;
* eliminación de mensajes;
* queue poisoning;
* abuso de administración;
* exfiltración desde DLQ;
* manipulación de offsets;
* replay no autorizado;
* bypass de firmas;
* degradación de cifrado.

---

### 2204. Messaging incident lifecycle

```text
Security Signal
      ↓
Initial Triage
      ↓
Incident Classification
      ↓
Scope Determination
      ↓
Containment
      ↓
Evidence Preservation
      ↓
Credential and Access Remediation
      ↓
Safe Recovery
      ↓
Post-Incident Review
      ↓
Control Improvement
```

---

### 2205. MessagingIncidentResponseService

```php
interface MessagingIncidentResponseServiceInterface
{
    public function open(
        MessagingIncidentSignal $signal
    ): MessagingSecurityIncident;

    public function contain(
        MessagingSecurityIncident $incident,
        MessagingContainmentPlan $plan
    ): MessagingContainmentResult;

    public function recover(
        MessagingSecurityIncident $incident,
        MessagingRecoveryPlan $plan
    ): MessagingRecoveryResult;

    public function close(
        MessagingSecurityIncident $incident,
        MessagingIncidentClosure $closure
    ): void;
}
```

---

### 2206. MessagingIncidentSignal

```php
final readonly class MessagingIncidentSignal
{
    public function __construct(
        public string $signalId,
        public MessagingIncidentSignalType $type,
        public ThreatSeverity $severity,
        public array $affectedResources,
        public array $affectedTenants,
        public array $evidence,
        public DateTimeImmutable $detectedAt,
        public string $detectorId,
    ) {
    }
}
```

---

### 2207. MessagingIncidentSignalType

```php
enum MessagingIncidentSignalType: string
{
    case BrokerCompromise = 'broker_compromise';
    case ProducerCompromise = 'producer_compromise';
    case ConsumerCompromise = 'consumer_compromise';
    case CredentialLeak = 'credential_leak';
    case MaliciousMessage = 'malicious_message';
    case ReplayAttack = 'replay_attack';
    case CrossTenantLeakage = 'cross_tenant_leakage';
    case SignatureFailure = 'signature_failure';
    case DecryptionFailure = 'decryption_failure';
    case UnauthorizedSubscription = 'unauthorized_subscription';
    case AdministrativeAbuse = 'administrative_abuse';
}
```

---

### 2208. Messaging incident classification

Todo incidente deberá clasificarse por:

* tipo;
* severidad;
* confianza;
* alcance;
* tenant impact;
* data classification;
* control plane impact;
* availability impact;
* integrity impact;
* confidentiality impact.

---

### 2209. MessagingIncidentType

```php
enum MessagingIncidentType: string
{
    case BrokerCompromise = 'broker_compromise';
    case ProducerCompromise = 'producer_compromise';
    case ConsumerCompromise = 'consumer_compromise';
    case MessageForgery = 'message_forgery';
    case MessageTampering = 'message_tampering';
    case MessageReplay = 'message_replay';
    case CrossTenantExposure = 'cross_tenant_exposure';
    case CredentialCompromise = 'credential_compromise';
    case UnauthorizedAdministration = 'unauthorized_administration';
    case EventStoreIntegrityFailure = 'event_store_integrity_failure';
    case DeadLetterExposure = 'dead_letter_exposure';
    case RoutingPolicyFailure = 'routing_policy_failure';
}
```

---

### 2210. MessagingIncidentState

```php
enum MessagingIncidentState: string
{
    case Detected = 'detected';
    case Triaged = 'triaged';
    case Confirmed = 'confirmed';
    case Containing = 'containing';
    case Contained = 'contained';
    case Investigating = 'investigating';
    case Recovering = 'recovering';
    case Monitoring = 'monitoring';
    case Closed = 'closed';
}
```

---

### 2211. Incident severity model

La severidad deberá considerar:

* número de tenants afectados;
* clasificación de datos;
* capacidad de expansión;
* integridad del broker;
* persistencia;
* abuso privilegiado;
* impacto regulatorio;
* impacto financiero;
* tiempo de exposición.

---

### 2212. Incident confidence

La confianza deberá expresarse de forma separada a la severidad.

Un evento de alta severidad y baja confianza podrá requerir contención preventiva limitada.

---

### 2213. MessagingIncidentAssessment

```php
final readonly class MessagingIncidentAssessment
{
    public function __construct(
        public MessagingIncidentType $type,
        public ThreatSeverity $severity,
        public ThreatConfidence $confidence,
        public array $affectedTenants,
        public array $affectedDestinations,
        public array $affectedPrincipals,
        public array $affectedCredentials,
        public array $recommendedActions,
    ) {
    }
}
```

---

### 2214. Initial triage

El triage deberá responder:

* qué ocurrió;
* cuándo ocurrió;
* qué componente detectó;
* qué tenants están afectados;
* qué mensajes están involucrados;
* qué credenciales fueron usadas;
* si el incidente continúa activo;
* si existe riesgo de propagación.

---

### 2215. Incident ownership

Todo incidente deberá asignarse a:

* incident commander;
* security lead;
* messaging platform owner;
* tenant liaison cuando aplique;
* forensic owner;
* recovery owner.

---

### 2216. Broker compromise response

Un broker comprometido deberá considerarse una pérdida potencial de:

* confidencialidad;
* integridad;
* disponibilidad;
* metadata;
* credenciales;
* offsets;
* routing state;
* retained messages.

---

### 2217. Broker compromise indicators

Indicadores posibles:

* cambios no autorizados de ACL;
* subscriptions desconocidas;
* plugins inesperados;
* login administrativo anómalo;
* lectura masiva;
* eliminación de topics;
* alteración de retention;
* configuración no versionada;
* certificados desconocidos;
* discrepancias de cluster state.

---

### 2218. BrokerCompromiseAssessment

```php
final readonly class BrokerCompromiseAssessment
{
    public function __construct(
        public string $brokerId,
        public bool $controlPlaneAffected,
        public bool $dataPlaneAffected,
        public bool $credentialStoreAffected,
        public bool $messageIntegrityTrusted,
        public array $affectedClusters,
        public array $affectedTenants,
        public array $recommendedContainment,
    ) {
    }
}
```

---

### 2219. Broker containment options

La contención podrá incluir:

* bloquear acceso administrativo;
* revocar credenciales;
* aislar nodos;
* suspender publishers;
* suspender consumers;
* congelar cambios de configuración;
* redirigir tráfico;
* activar broker de contingencia;
* colocar destinations en modo read-only.

---

### 2220. Broker isolation mode

```php
enum BrokerIsolationMode: string
{
    case None = 'none';
    case AdministrativeFreeze = 'administrative_freeze';
    case ReadOnly = 'read_only';
    case ProducerSuspension = 'producer_suspension';
    case ConsumerSuspension = 'consumer_suspension';
    case FullIsolation = 'full_isolation';
}
```

---

### 2221. Broker failover security

El failover no deberá trasladar automáticamente:

* credenciales comprometidas;
* ACLs no verificadas;
* configuraciones alteradas;
* offsets corruptos;
* mensajes no confiables.

---

### 2222. Broker recovery validation

Antes de reanudar operaciones deberá verificarse:

* integridad de configuración;
* estado de credenciales;
* confianza en certificados;
* ACLs;
* plugins;
* retained messages;
* event chain checkpoints;
* observabilidad.

---

### 2223. Producer compromise response

Un producer comprometido puede:

* publicar mensajes falsos;
* abusar de destinos autorizados;
* generar alto volumen;
* insertar payloads maliciosos;
* falsificar eventos de negocio;
* intentar cross-tenant routing.

---

### 2224. Producer compromise indicators

Indicadores:

* incremento súbito de volumen;
* nuevos message types;
* destinations no habituales;
* cambios geográficos;
* firmas inválidas;
* payloads atípicos;
* tenant mismatches;
* uso fuera de horario;
* alta tasa de retries.

---

### 2225. Producer containment

La contención deberá permitir:

* suspender identidad;
* revocar credenciales;
* bloquear destinos;
* limitar message types;
* reducir throughput;
* quarantine de mensajes recientes;
* invalidar firmas futuras;
* rotar keys.

---

### 2226. ProducerContainmentPlan

```php
final readonly class ProducerContainmentPlan
{
    public function __construct(
        public string $producerId,
        public bool $suspendIdentity,
        public bool $revokeCredentials,
        public array $blockedDestinations,
        public array $quarantineMessageTypes,
        public ?DateTimeImmutable $quarantineFrom,
        public array $tenantScopes,
    ) {
    }
}
```

---

### 2227. Producer message retrospective analysis

Los mensajes emitidos durante la ventana sospechosa deberán analizarse por:

* firma;
* schema;
* destination;
* tenant;
* payload;
* causalidad;
* efectos de negocio;
* downstream propagation.

---

### 2228. Producer compromise blast radius

El blast radius deberá incluir mensajes derivados y no solo publicaciones directas.

---

### 2229. Consumer compromise response

Un consumer comprometido puede:

* exfiltrar mensajes;
* manipular acknowledgements;
* provocar redelivery;
* ejecutar efectos maliciosos;
* leer otros tenants;
* alterar offsets;
* abusar de DLQ.

---

### 2230. Consumer compromise indicators

Indicadores:

* subscriptions nuevas;
* lectura masiva;
* cambios de consumer group;
* offsets anómalos;
* aumento de nack;
* acceso a tenants no habituales;
* descarga de DLQ;
* ejecución desde workload desconocido.

---

### 2231. Consumer containment

La respuesta podrá:

* suspender consumer;
* remover subscriptions;
* revocar credenciales;
* invalidar workload identity;
* detener acknowledgements;
* aislar consumer group;
* rotar encryption grants;
* revisar datos accedidos.

---

### 2232. ConsumerContainmentPlan

```php
final readonly class ConsumerContainmentPlan
{
    public function __construct(
        public string $consumerId,
        public bool $suspendIdentity,
        public bool $revokeCredentials,
        public bool $removeSubscriptions,
        public array $affectedConsumerGroups,
        public array $tenantScopes,
        public bool $rotateDecryptionAccess,
    ) {
    }
}
```

---

### 2233. Consumer offset recovery

Los offsets deberán restaurarse desde un punto confiable cuando exista evidencia de manipulación.

---

### 2234. Malicious message containment

Un mensaje malicioso deberá aislarse sin permitir ejecución, renderizado o propagación adicional.

---

### 2235. MaliciousMessageAssessment

```php
final readonly class MaliciousMessageAssessment
{
    public function __construct(
        public string $messageId,
        public MaliciousMessageType $type,
        public ThreatSeverity $severity,
        public array $indicators,
        public array $affectedConsumers,
        public array $derivedMessages,
        public MaliciousMessageAction $recommendedAction,
    ) {
    }
}
```

---

### 2236. MaliciousMessageType

```php
enum MaliciousMessageType: string
{
    case Forged = 'forged';
    case Tampered = 'tampered';
    case Replay = 'replay';
    case SchemaExploit = 'schema_exploit';
    case DeserializationExploit = 'deserialization_exploit';
    case PayloadBomb = 'payload_bomb';
    case CrossTenantInjection = 'cross_tenant_injection';
    case CommandAbuse = 'command_abuse';
}
```

---

### 2237. MaliciousMessageAction

```php
enum MaliciousMessageAction: string
{
    case Reject = 'reject';
    case Quarantine = 'quarantine';
    case DeadLetter = 'dead_letter';
    case BlockProducer = 'block_producer';
    case SuspendDestination = 'suspend_destination';
    case EscalateIncident = 'escalate_incident';
}
```

---

### 2238. Payload bomb protection

La infraestructura deberá limitar:

* payload size;
* decompression ratio;
* nested depth;
* collection size;
* processing time;
* memory allocation;
* schema complexity.

---

### 2239. Safe message inspection

La inspección forense deberá realizarse en entornos aislados y sin deserialización insegura.

---

### 2240. Queue quarantine

VoltStack deberá permitir colocar una queue en cuarentena.

---

### 2241. QueueQuarantinePolicy

```php
final readonly class QueueQuarantinePolicy
{
    public function __construct(
        public string $queueId,
        public QueueQuarantineMode $mode,
        public array $allowedOperations,
        public array $authorizedReviewers,
        public DateTimeImmutable $activatedAt,
        public string $incidentId,
    ) {
    }
}
```

---

### 2242. QueueQuarantineMode

```php
enum QueueQuarantineMode: string
{
    case StopConsumers = 'stop_consumers';
    case StopProducers = 'stop_producers';
    case FreezeAll = 'freeze_all';
    case DivertIncoming = 'divert_incoming';
    case ReviewOnly = 'review_only';
}
```

---

### 2243. Queue quarantine invariants

Durante quarantine:

* no deberán ejecutarse handlers normales;
* no deberán perderse evidencias;
* no deberá alterarse el orden sin registro;
* el acceso deberá ser privilegiado;
* toda extracción deberá auditarse.

---

### 2244. Topic quarantine

Los topics podrán requerir:

* bloqueo de nuevos publishers;
* suspensión de subscribers;
* duplicación hacia forensic sink;
* retención ampliada;
* bloqueo de replay;
* inspección de partitions.

---

### 2245. DestinationQuarantineService

```php
interface DestinationQuarantineServiceInterface
{
    public function quarantine(
        MessagingDestination $destination,
        DestinationQuarantineRequest $request
    ): DestinationQuarantineResult;

    public function release(
        MessagingDestination $destination,
        DestinationReleaseRequest $request
    ): DestinationReleaseResult;
}
```

---

### 2246. Quarantine release requirements

La liberación deberá exigir:

* incidente contenido;
* evidencia preservada;
* credenciales rotadas;
* configuración validada;
* mensajes afectados identificados;
* approval;
* monitoring reforzado.

---

### 2247. Emergency credential rotation

VoltStack deberá soportar rotación urgente de:

* broker credentials;
* producer keys;
* consumer credentials;
* signing keys;
* encryption keys;
* webhook secrets;
* mTLS certificates.

---

### 2248. MessagingCredentialRotationPlan

```php
final readonly class MessagingCredentialRotationPlan
{
    public function __construct(
        public string $planId,
        public array $credentialIds,
        public RotationUrgency $urgency,
        public array $affectedPrincipals,
        public array $affectedDestinations,
        public bool $revokeImmediately,
        public bool $allowOverlap,
        public DateTimeImmutable $deadline,
    ) {
    }
}
```

---

### 2249. Emergency rotation modes

```php
enum RotationUrgency: string
{
    case Planned = 'planned';
    case Accelerated = 'accelerated';
    case Emergency = 'emergency';
    case ImmediateRevocation = 'immediate_revocation';
}
```

---

### 2250. Emergency rotation trade-offs

La rotación inmediata podrá afectar disponibilidad, pero deberá preferirse cuando exista compromiso confirmado de alta severidad.

---

### 2251. Signing key compromise response

Si una signing key fue comprometida, deberán considerarse no confiables todos los mensajes firmados dentro de la ventana afectada.

---

### 2252. Encryption key compromise response

La respuesta deberá determinar:

* qué mensajes fueron cifrados;
* qué tenants están afectados;
* qué backups contienen ciphertext;
* si hubo acceso al key material;
* si es necesaria re-encryption;
* si debe destruirse la versión comprometida.

---

### 2253. Credential dependency graph

VoltStack deberá mantener relaciones entre:

* credencial;
* producer;
* consumer;
* broker;
* destination;
* tenant;
* message type;
* environment.

---

### 2254. MessagingCredentialDependencyGraph

```php
interface MessagingCredentialDependencyGraphInterface
{
    public function dependentsOf(
        string $credentialId
    ): MessagingCredentialDependents;
}
```

---

### 2255. Forensic preservation

Toda investigación deberá preservar:

* mensajes;
* headers;
* broker metadata;
* offsets;
* ACLs;
* configurations;
* signatures;
* keys metadata;
* access logs;
* admin logs;
* trace references.

---

### 2256. MessagingForensicPackage

```php
final readonly class MessagingForensicPackage
{
    public function __construct(
        public string $packageId,
        public string $incidentId,
        public array $messageReferences,
        public array $configurationSnapshots,
        public array $accessLogs,
        public array $credentialMetadata,
        public array $chainCheckpoints,
        public DateTimeImmutable $createdAt,
        public string $packageDigest,
        public DigitalSignature $signature,
    ) {
    }
}
```

---

### 2257. Evidence immutability

La evidencia deberá almacenarse en un sistema:

* append-only;
* access-controlled;
* integrity-protected;
* retention-managed;
* independently monitored.

---

### 2258. Messaging chain of custody

Toda transferencia de evidencia deberá registrarse.

---

### 2259. MessagingEvidenceTransfer

```php
final readonly class MessagingEvidenceTransfer
{
    public function __construct(
        public string $evidenceId,
        public IdentityIdentifier|string $fromCustodian,
        public IdentityIdentifier|string $toCustodian,
        public string $purpose,
        public DateTimeImmutable $transferredAt,
        public string $evidenceDigest,
        public DigitalSignature $acknowledgement,
    ) {
    }
}
```

---

### 2260. Forensic clock integrity

Los timestamps deberán correlacionarse con fuentes de tiempo confiables y documentar drift conocido.

---

### 2261. Evidence minimization

La preservación deberá ser suficiente para investigación sin recolectar datos irrelevantes de otros tenants.

---

### 2262. Legal hold integration

La evidencia podrá quedar sujeta a legal hold, bloqueando eliminación o crypto-shredding hasta autorización.

---

### 2263. Messaging observability architecture

VoltStack deberá proporcionar observabilidad de seguridad sobre todo el ciclo de mensajería.

---

### 2264. MessagingSecurityTelemetry

```php
final readonly class MessagingSecurityTelemetry
{
    public function __construct(
        public string $telemetryId,
        public string $source,
        public string $tenantId,
        public string $destinationId,
        public string $principalId,
        public MessagingTelemetryType $type,
        public array $metrics,
        public DateTimeImmutable $observedAt,
    ) {
    }
}
```

---

### 2265. MessagingTelemetryType

```php
enum MessagingTelemetryType: string
{
    case Publication = 'publication';
    case Consumption = 'consumption';
    case Authentication = 'authentication';
    case Authorization = 'authorization';
    case SignatureValidation = 'signature_validation';
    case Encryption = 'encryption';
    case Replay = 'replay';
    case Administration = 'administration';
    case Routing = 'routing';
    case Quarantine = 'quarantine';
}
```

---

### 2266. Security-relevant metrics

Métricas recomendadas:

* failed authentication rate;
* failed authorization rate;
* invalid signatures;
* replay detections;
* tenant mismatches;
* unauthorized subscriptions;
* DLQ access;
* admin changes;
* key rotation age;
* queue depth anomalies;
* message size anomalies;
* schema rejection rate.

---

### 2267. MessagingSecurityMetric

```php
final readonly class MessagingSecurityMetric
{
    public function __construct(
        public string $metricName,
        public float|int $value,
        public array $dimensions,
        public DateTimeImmutable $windowStart,
        public DateTimeImmutable $windowEnd,
        public SecurityMetricClassification $classification,
    ) {
    }
}
```

---

### 2268. Metric cardinality protection

Las dimensiones deberán limitarse para evitar:

* cardinality explosion;
* observability denial of service;
* costos excesivos;
* exposición de identificadores sensibles.

---

### 2269. Tenant-aware telemetry

Toda métrica multi-tenant deberá preservar aislamiento y evitar mezclar datos no autorizados.

---

### 2270. Broker health security signals

El estado del broker deberá producir señales cuando existan:

* replica divergence;
* storage corruption;
* configuration drift;
* auth backend failures;
* certificate expiration;
* ACL inconsistencies;
* unexpected leadership changes;
* clock drift;
* unusual rebalance activity.

---

### 2271. BrokerSecurityHealthAssessment

```php
final readonly class BrokerSecurityHealthAssessment
{
    public function __construct(
        public string $brokerId,
        public BrokerSecurityHealthState $state,
        public array $signals,
        public array $degradedControls,
        public array $recommendedActions,
        public DateTimeImmutable $assessedAt,
    ) {
    }
}
```

---

### 2272. BrokerSecurityHealthState

```php
enum BrokerSecurityHealthState: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case AtRisk = 'at_risk';
    case Compromised = 'compromised';
    case Isolated = 'isolated';
}
```

---

### 2273. Messaging anomaly detection

VoltStack deberá detectar desviaciones respecto al comportamiento esperado.

---

### 2274. MessagingAnomalyDetector

```php
interface MessagingAnomalyDetectorInterface
{
    public function analyze(
        MessagingSecurityTelemetry $telemetry
    ): MessagingAnomalyAssessment;
}
```

---

### 2275. MessagingAnomalyAssessment

```php
final readonly class MessagingAnomalyAssessment
{
    public function __construct(
        public bool $anomalous,
        public MessagingAnomalyType $type,
        public ThreatSeverity $severity,
        public ThreatConfidence $confidence,
        public array $evidence,
        public array $recommendedActions,
    ) {
    }
}
```

---

### 2276. MessagingAnomalyType

```php
enum MessagingAnomalyType: string
{
    case VolumeSpike = 'volume_spike';
    case DestinationDeviation = 'destination_deviation';
    case TenantDeviation = 'tenant_deviation';
    case MessageTypeDeviation = 'message_type_deviation';
    case ConsumerBehaviorDeviation = 'consumer_behavior_deviation';
    case ReplayPattern = 'replay_pattern';
    case AdministrativeDeviation = 'administrative_deviation';
    case GeographicDeviation = 'geographic_deviation';
    case CredentialSharing = 'credential_sharing';
}
```

---

### 2277. Producer behavior baselines

Las baselines podrán incluir:

* volumen habitual;
* destinations;
* message types;
* tenants;
* horario;
* payload size;
* error rate;
* geographic origin;
* workload identity.

---

### 2278. Consumer behavior baselines

Las baselines podrán incluir:

* subscriptions;
* throughput;
* acknowledgement rate;
* processing latency;
* DLQ access;
* tenant scope;
* event types;
* replay activity.

---

### 2279. Adaptive containment

Las anomalías de alta confianza podrán activar:

* rate limiting;
* restricted mode;
* credential challenge;
* producer suspension;
* consumer isolation;
* destination quarantine.

---

### 2280. Cross-tenant leakage detection

VoltStack deberá detectar señales de datos o rutas que crucen tenants sin autorización.

---

### 2281. CrossTenantLeakageDetector

```php
interface CrossTenantLeakageDetectorInterface
{
    public function inspect(
        SecureMessage $message,
        TenantBoundDestination $destination,
        MessageConsumptionContext $context
    ): CrossTenantLeakageAssessment;
}
```

---

### 2282. CrossTenantLeakageAssessment

```php
final readonly class CrossTenantLeakageAssessment
{
    public function __construct(
        public bool $leakageDetected,
        public string $sourceTenantId,
        public ?string $targetTenantId,
        public array $conflictingEvidence,
        public ThreatSeverity $severity,
        public array $containmentActions,
    ) {
    }
}
```

---

### 2283. Leakage detection signals

Se deberá comparar:

* message tenant;
* destination tenant;
* producer tenant scope;
* consumer tenant scope;
* payload references;
* encryption key scope;
* partition;
* schema ownership.

---

### 2284. Cross-tenant containment

Ante leakage confirmado deberá:

* detener consumo;
* bloquear producer;
* aislar destination;
* preservar evidencia;
* identificar mensajes derivados;
* notificar incident response;
* evaluar exposición regulatoria.

---

### 2285. Messaging SIEM integration

Los eventos de seguridad deberán enviarse a SIEM mediante formatos estructurados y estables.

---

### 2286. MessagingSecurityEventExporter

```php
interface MessagingSecurityEventExporterInterface
{
    public function export(
        MessagingSecurityAuditEvent $event,
        SecurityEventExportContext $context
    ): SecurityEventExportResult;
}
```

---

### 2287. SIEM event fields

Los eventos deberán incluir:

* event ID;
* timestamp;
* severity;
* confidence;
* tenant;
* principal;
* destination;
* message ID;
* incident ID;
* action;
* outcome;
* evidence references;
* trace ID.

---

### 2288. SIEM data minimization

Los payloads completos no deberán enviarse al SIEM salvo necesidad justificada.

---

### 2289. SIEM delivery assurance

La exportación deberá soportar:

* buffering;
* retry;
* integrity;
* backpressure;
* dead-lettering;
* delivery monitoring.

---

### 2290. Messaging SOAR playbooks

VoltStack deberá exponer acciones controladas para automatización de respuesta.

---

### 2291. MessagingSoarAction

```php
enum MessagingSoarAction: string
{
    case SuspendProducer = 'suspend_producer';
    case SuspendConsumer = 'suspend_consumer';
    case RevokeCredential = 'revoke_credential';
    case QuarantineDestination = 'quarantine_destination';
    case BlockReplay = 'block_replay';
    case RotateKey = 'rotate_key';
    case SnapshotConfiguration = 'snapshot_configuration';
    case PreserveEvidence = 'preserve_evidence';
}
```

---

### 2292. SOAR action authorization

Las acciones automáticas deberán limitarse por:

* severity;
* confidence;
* environment;
* tenant;
* business criticality;
* blast radius;
* approval requirements.

---

### 2293. Human-in-the-loop controls

Acciones de alto impacto podrán requerir aprobación humana antes de ejecutarse.

---

### 2294. Automated playbook safety

Todo playbook deberá tener:

* preconditions;
* maximum scope;
* timeout;
* rollback;
* audit;
* idempotency;
* dry-run;
* manual override.

---

### 2295. Messaging compliance evidence

VoltStack deberá producir evidencia sobre:

* access controls;
* key rotation;
* broker configuration;
* message integrity;
* tenant isolation;
* incident handling;
* replay governance;
* administrative access;
* retention;
* legal hold.

---

### 2296. MessagingComplianceEvidencePackage

```php
final readonly class MessagingComplianceEvidencePackage
{
    public function __construct(
        public string $packageId,
        public string $controlFramework,
        public array $controlMappings,
        public array $auditEvents,
        public array $configurationSnapshots,
        public array $rotationRecords,
        public array $incidentRecords,
        public DateTimeImmutable $generatedAt,
        public string $packageDigest,
    ) {
    }
}
```

---

### 2297. Messaging security governance

La gobernanza deberá definir:

* owners;
* supported brokers;
* approved protocols;
* credential policies;
* encryption requirements;
* tenant isolation models;
* retention;
* replay controls;
* incident SLAs;
* exception process.

---

### 2298. Messaging security exception management

Toda excepción deberá incluir:

* motivo;
* owner;
* riesgo aceptado;
* compensating controls;
* tenant scope;
* expiration;
* approval;
* review date.

---

### 2299. Messaging security audit events

Eventos recomendados:

* `MessagingIncidentOpened`;
* `MessagingIncidentClassified`;
* `BrokerCompromiseDetected`;
* `BrokerIsolated`;
* `ProducerCompromiseDetected`;
* `ProducerSuspended`;
* `ConsumerCompromiseDetected`;
* `ConsumerSuspended`;
* `MaliciousMessageDetected`;
* `MessageQuarantined`;
* `QueueQuarantined`;
* `TopicQuarantined`;
* `MessagingCredentialEmergencyRotationStarted`;
* `MessagingCredentialEmergencyRotationCompleted`;
* `MessagingForensicPackageCreated`;
* `MessagingEvidenceTransferred`;
* `MessagingAnomalyDetected`;
* `CrossTenantLeakageDetected`;
* `MessagingSoarActionExecuted`;
* `MessagingIncidentClosed`.

---

### 2300. Resultado de esta entrega

Esta entrega establece:

```text
Messaging Incident Response Architecture
Messaging Incident Classification
Incident Severity and Confidence
Broker Compromise Response
Broker Isolation and Recovery
Producer Compromise Response
Consumer Compromise Response
Malicious Message Containment
Payload Bomb Protection
Safe Message Inspection
Queue Quarantine
Topic Quarantine
Destination Release Controls
Emergency Credential Rotation
Signing Key Compromise Response
Encryption Key Compromise Response
Credential Dependency Graphs
Messaging Forensic Preservation
Messaging Chain of Custody
Legal Hold Integration
Messaging Security Observability
Security Metrics
Broker Health Security Signals
Messaging Anomaly Detection
Behavior Baselines
Adaptive Containment
Cross-Tenant Leakage Detection
Cross-Tenant Containment
SIEM Integration
SOAR Playbooks
Human-in-the-Loop Controls
Compliance Evidence
Messaging Security Governance
Messaging Security Exception Management
Messaging Security Audit Events
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 24

- Identity lifecycle orchestration
- Identity state machines
- Identity activation
- Identity suspension
- Identity disablement
- Identity deprovisioning
- Identity reactivation
- Identity archival
- Identity deletion
- Identity merge
- Identity split
- Duplicate identity resolution
- Identity migration
- Identity portability
- Tenant transfer
- Identity ownership transfer
- Identity lifecycle approvals
- Identity lifecycle eventing
- Lifecycle rollback
- Lifecycle governance
- Lifecycle audit events
```

## Entrega 24

**Documento:** Parte 05
**Entrega:** 24 de varias
**Cobertura:** Secciones **2301–2400**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 23`

---

### 2301. Identity Lifecycle Orchestration Architecture

VoltStack deberá incorporar una arquitectura formal para orquestar el ciclo de vida completo de identidades humanas, técnicas, externas, privilegiadas y no humanas.

La arquitectura deberá cubrir:

* creación;
* verificación;
* activación;
* suspensión;
* restricción;
* deshabilitación;
* reactivación;
* transferencia;
* fusión;
* separación;
* migración;
* archivado;
* eliminación;
* recuperación;
* deprovisioning.

---

### 2302. Identity lifecycle security goals

La arquitectura deberá garantizar:

* transiciones controladas;
* consistencia entre sistemas;
* mínima permanencia de acceso;
* preservación de evidencia;
* prevención de reactivaciones indebidas;
* aislamiento multi-tenant;
* integridad de estado;
* idempotencia;
* reversibilidad controlada;
* auditabilidad;
* cumplimiento de retención y privacidad.

---

### 2303. Identity lifecycle threat model

El modelo deberá considerar:

* activación sin verificación;
* cuentas huérfanas;
* deprovisioning incompleto;
* reactivación no autorizada;
* transferencia de identidad entre tenants;
* colisión de identidades;
* account takeover durante migración;
* fusión incorrecta;
* pérdida de provenance;
* eliminación prematura;
* persistencia de sesiones;
* credenciales activas tras baja;
* race conditions entre conectores;
* rollback parcial;
* abuso administrativo.

---

### 2304. Identity lifecycle pipeline

```text
Lifecycle Trigger
      ↓
Identity Resolution
      ↓
Current State Validation
      ↓
Policy Evaluation
      ↓
Approval Resolution
      ↓
Transition Plan
      ↓
Credential and Access Actions
      ↓
Downstream Provisioning
      ↓
Verification
      ↓
Commit State
      ↓
Audit and Notification
```

---

### 2305. IdentityLifecycleOrchestrator

```php
interface IdentityLifecycleOrchestratorInterface
{
    public function plan(
        IdentityLifecycleCommand $command
    ): IdentityLifecyclePlan;

    public function execute(
        IdentityLifecyclePlan $plan
    ): IdentityLifecycleResult;

    public function rollback(
        IdentityLifecycleExecution $execution,
        IdentityLifecycleRollbackContext $context
    ): IdentityLifecycleRollbackResult;
}
```

---

### 2306. IdentityLifecycleCommand

```php
final readonly class IdentityLifecycleCommand
{
    public function __construct(
        public string $commandId,
        public IdentityIdentifier $identityId,
        public IdentityLifecycleAction $action,
        public IdentityIdentifier|string $requestedBy,
        public string $tenantId,
        public string $reason,
        public array $parameters,
        public DateTimeImmutable $requestedAt,
    ) {
    }
}
```

---

### 2307. IdentityLifecycleAction

```php
enum IdentityLifecycleAction: string
{
    case Create = 'create';
    case Verify = 'verify';
    case Activate = 'activate';
    case Restrict = 'restrict';
    case Suspend = 'suspend';
    case Disable = 'disable';
    case Reactivate = 'reactivate';
    case Transfer = 'transfer';
    case Merge = 'merge';
    case Split = 'split';
    case Migrate = 'migrate';
    case Archive = 'archive';
    case Delete = 'delete';
    case Restore = 'restore';
    case Deprovision = 'deprovision';
}
```

---

### 2308. Identity lifecycle state machine

Toda identidad deberá operar bajo una máquina de estados explícita.

---

### 2309. IdentityLifecycleState

```php
enum IdentityLifecycleState: string
{
    case Draft = 'draft';
    case PendingVerification = 'pending_verification';
    case PendingActivation = 'pending_activation';
    case Active = 'active';
    case Restricted = 'restricted';
    case Suspended = 'suspended';
    case Disabled = 'disabled';
    case PendingTransfer = 'pending_transfer';
    case PendingMerge = 'pending_merge';
    case PendingMigration = 'pending_migration';
    case Deprovisioning = 'deprovisioning';
    case Archived = 'archived';
    case PendingDeletion = 'pending_deletion';
    case Deleted = 'deleted';
}
```

---

### 2310. State transition invariants

Una transición no deberá:

* saltar validaciones obligatorias;
* preservar privilegios incompatibles;
* reactivar credenciales revocadas;
* cambiar tenant implícitamente;
* eliminar provenance;
* ignorar legal hold;
* extender acceso sin policy;
* producir estados imposibles.

---

### 2311. IdentityStateTransition

```php
final readonly class IdentityStateTransition
{
    public function __construct(
        public IdentityLifecycleState $from,
        public IdentityLifecycleState $to,
        public IdentityLifecycleAction $action,
        public array $requiredConditions,
        public array $requiredApprovals,
        public array $sideEffects,
    ) {
    }
}
```

---

### 2312. IdentityStateMachine

```php
interface IdentityStateMachineInterface
{
    public function canTransition(
        IdentityLifecycleState $from,
        IdentityLifecycleState $to,
        IdentityLifecycleContext $context
    ): bool;

    public function transition(
        IdentityRecord $identity,
        IdentityLifecycleState $target,
        IdentityLifecycleContext $context
    ): IdentityRecord;
}
```

---

### 2313. Transition authorization

Toda transición deberá autorizarse según:

* actor;
* tenant;
* identity type;
* current state;
* target state;
* risk;
* environment;
* business owner;
* legal constraints.

---

### 2314. Identity lifecycle policy engine

```php
interface IdentityLifecyclePolicyEngineInterface
{
    public function evaluate(
        IdentityLifecycleCommand $command,
        IdentityLifecycleSnapshot $snapshot
    ): IdentityLifecycleDecision;
}
```

---

### 2315. IdentityLifecycleDecision

```php
final readonly class IdentityLifecycleDecision
{
    public function __construct(
        public bool $allowed,
        public array $requiredApprovals,
        public array $requiredChecks,
        public array $requiredActions,
        public array $restrictions,
        public array $denialReasons,
    ) {
    }
}
```

---

### 2316. Identity creation

La creación deberá distinguir:

* identidad solicitada;
* identidad preprovisionada;
* identidad federada;
* identidad importada;
* identidad técnica;
* identidad temporal;
* identidad privilegiada.

---

### 2317. Identity creation validation

La creación deberá validar:

* uniqueness;
* tenant;
* owner;
* identity type;
* source;
* required attributes;
* duplicate candidates;
* authorization;
* retention profile.

---

### 2318. IdentityDraft

```php
final readonly class IdentityDraft
{
    public function __construct(
        public string $draftId,
        public string $tenantId,
        public IdentityType $type,
        public array $attributes,
        public IdentitySource $source,
        public IdentityIdentifier|string $createdBy,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
```

---

### 2319. Identity verification

La verificación deberá comprobar que la identidad representa legítimamente al sujeto o workload esperado.

---

### 2320. Verification methods

Podrán incluirse:

* email verification;
* phone verification;
* document verification;
* federation assertion;
* manager approval;
* HR source confirmation;
* device attestation;
* workload attestation;
* administrator validation.

---

### 2321. IdentityVerificationRecord

```php
final readonly class IdentityVerificationRecord
{
    public function __construct(
        public string $verificationId,
        public IdentityIdentifier $identityId,
        public IdentityVerificationMethod $method,
        public IdentityVerificationStatus $status,
        public array $evidenceReferences,
        public DateTimeImmutable $verifiedAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 2322. IdentityVerificationStatus

```php
enum IdentityVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
```

---

### 2323. Identity activation

Una identidad no deberá activarse hasta cumplir:

* verification;
* required approvals;
* owner assignment;
* tenant binding;
* credential enrollment;
* policy acceptance;
* risk evaluation.

---

### 2324. IdentityActivationRequest

```php
final readonly class IdentityActivationRequest
{
    public function __construct(
        public IdentityIdentifier $identityId,
        public string $tenantId,
        public array $requestedAccess,
        public array $verificationReferences,
        public IdentityIdentifier|string $requestedBy,
        public DateTimeImmutable $requestedAt,
    ) {
    }
}
```

---

### 2325. Activation policy

La activación deberá aplicar least privilege y no asignar privilegios amplios por defecto.

---

### 2326. Activation side effects

Podrán incluir:

* emisión de credenciales;
* habilitación de sesión;
* creación de perfiles;
* asignación de access packages;
* provisionamiento downstream;
* notificación;
* enrollment MFA.

---

### 2327. Activation verification

La transición solo deberá completarse cuando los sistemas críticos confirmen provisionamiento exitoso.

---

### 2328. Partial activation

Si algunos sistemas fallan, la identidad deberá permanecer:

* pending activation;
* restricted;
* quarantined;

según la criticidad del fallo.

---

### 2329. Identity restriction

Una identidad restringida conserva existencia y autenticación limitada, pero pierde capacidades específicas.

---

### 2330. IdentityRestrictionProfile

```php
final readonly class IdentityRestrictionProfile
{
    public function __construct(
        public string $profileId,
        public array $blockedActions,
        public array $allowedActions,
        public array $blockedResources,
        public bool $newSessionsAllowed,
        public bool $credentialChangesAllowed,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 2331. Restriction use cases

La restricción podrá utilizarse para:

* riesgo elevado;
* investigación;
* transición laboral;
* compliance;
* deuda de verificación;
* anomalías;
* recovery session;
* acceso temporal.

---

### 2332. Identity suspension

La suspensión deberá detener actividad sin eliminar la identidad.

---

### 2333. IdentitySuspensionRequest

```php
final readonly class IdentitySuspensionRequest
{
    public function __construct(
        public IdentityIdentifier $identityId,
        public IdentitySuspensionReason $reason,
        public IdentityIdentifier|string $requestedBy,
        public bool $revokeSessions,
        public bool $revokeCredentials,
        public ?DateTimeImmutable $until,
    ) {
    }
}
```

---

### 2334. IdentitySuspensionReason

```php
enum IdentitySuspensionReason: string
{
    case SecurityIncident = 'security_incident';
    case AdministrativeReview = 'administrative_review';
    case EmploymentLeave = 'employment_leave';
    case PolicyViolation = 'policy_violation';
    case PaymentOrContractIssue = 'payment_or_contract_issue';
    case Inactivity = 'inactivity';
    case VerificationFailure = 'verification_failure';
    case LegalRequirement = 'legal_requirement';
}
```

---

### 2335. Suspension side effects

La suspensión podrá:

* revocar sesiones;
* bloquear autenticación;
* suspender tokens;
* detener jobs;
* bloquear API clients;
* pausar workflows;
* remover acceso privilegiado;
* conservar evidencia.

---

### 2336. Temporary suspension

Toda suspensión temporal deberá incluir:

* expiration;
* review owner;
* automatic review;
* restoration policy;
* notification rules.

---

### 2337. Identity disablement

La deshabilitación representa una interrupción más fuerte y generalmente prolongada.

---

### 2338. Disablement requirements

Deberá:

* impedir autenticación;
* invalidar sesiones;
* revocar credenciales;
* bloquear delegaciones;
* detener impersonation;
* deshabilitar tokens;
* bloquear nuevas asignaciones.

---

### 2339. Disabled identity access

Una identidad deshabilitada solo podrá acceder a flujos explícitos de:

* appeal;
* recovery;
* compliance;
* data export;
* support verification.

---

### 2340. Identity reactivation

La reactivación no deberá restaurar automáticamente el estado previo completo.

---

### 2341. IdentityReactivationRequest

```php
final readonly class IdentityReactivationRequest
{
    public function __construct(
        public IdentityIdentifier $identityId,
        public IdentityIdentifier|string $requestedBy,
        public string $reason,
        public array $requestedRestorations,
        public DateTimeImmutable $requestedAt,
    ) {
    }
}
```

---

### 2342. Reactivation checks

Deberán reevaluarse:

* identity ownership;
* tenant membership;
* employment or contract state;
* verification;
* device trust;
* credentials;
* MFA;
* current policies;
* dormant access;
* risk signals.

---

### 2343. Reactivation credential policy

Las credenciales comprometidas, expiradas o revocadas no deberán reactivarse.

---

### 2344. Reactivation access reconstruction

El acceso deberá reconstruirse desde fuentes vigentes y no desde snapshots históricos no verificados.

---

### 2345. Identity deprovisioning

El deprovisioning deberá retirar acceso de forma coordinada y verificable.

---

### 2346. IdentityDeprovisioningPlan

```php
final readonly class IdentityDeprovisioningPlan
{
    public function __construct(
        public string $planId,
        public IdentityIdentifier $identityId,
        public string $tenantId,
        public array $credentialActions,
        public array $sessionActions,
        public array $entitlementActions,
        public array $downstreamActions,
        public array $dataOwnershipActions,
        public DeprovisioningMode $mode,
    ) {
    }
}
```

---

### 2347. DeprovisioningMode

```php
enum DeprovisioningMode: string
{
    case Immediate = 'immediate';
    case Graceful = 'graceful';
    case Scheduled = 'scheduled';
    case Emergency = 'emergency';
    case LegalHold = 'legal_hold';
}
```

---

### 2348. Deprovisioning sequence

```text
Freeze New Access
      ↓
Revoke Sessions
      ↓
Revoke Credentials
      ↓
Remove Privileged Access
      ↓
Remove Entitlements
      ↓
Disable Downstream Accounts
      ↓
Transfer Ownership
      ↓
Verify Completion
      ↓
Archive Identity
```

---

### 2349. Deprovisioning idempotency

Repetir el proceso no deberá:

* recrear acceso;
* duplicar transferencias;
* fallar por recursos ya removidos;
* corromper ownership;
* omitir validaciones.

---

### 2350. Downstream deprovisioning verification

Cada conector deberá confirmar:

* request accepted;
* operation completed;
* final state;
* timestamp;
* remote identifier;
* failure reason.

---

### 2351. Deprovisioning failure handling

Los fallos deberán clasificarse como:

* retryable;
* permanent;
* authorization failure;
* connector unavailable;
* object not found;
* policy conflict;
* manual remediation required.

---

### 2352. Orphaned access detection

Tras deprovisioning deberá buscarse acceso residual en:

* applications;
* databases;
* cloud roles;
* certificates;
* API keys;
* shared accounts;
* secrets;
* jobs;
* service principals.

---

### 2353. DeprovisioningCompletionReport

```php
final readonly class DeprovisioningCompletionReport
{
    public function __construct(
        public IdentityIdentifier $identityId,
        public bool $complete,
        public array $completedActions,
        public array $failedActions,
        public array $residualAccess,
        public DateTimeImmutable $completedAt,
    ) {
    }
}
```

---

### 2354. Identity archival

El archivado conserva registro histórico sin permitir uso operativo.

---

### 2355. Archived identity restrictions

Una identidad archivada no deberá:

* autenticarse;
* recibir nuevas asignaciones;
* iniciar workflows;
* poseer sesiones;
* emitir tokens;
* actuar como delegada.

---

### 2356. IdentityArchiveRecord

```php
final readonly class IdentityArchiveRecord
{
    public function __construct(
        public IdentityIdentifier $identityId,
        public string $tenantId,
        public string $archiveReason,
        public array $retainedAttributes,
        public array $redactedAttributes,
        public DateTimeImmutable $archivedAt,
        public ?DateTimeImmutable $retentionUntil,
    ) {
    }
}
```

---

### 2357. Archive minimization

Solo deberán conservarse atributos necesarios para:

* auditoría;
* legal hold;
* seguridad;
* contabilidad;
* trazabilidad;
* obligaciones contractuales.

---

### 2358. Identity deletion

La eliminación deberá diferenciar:

* logical deletion;
* anonymization;
* crypto-shredding;
* irreversible physical deletion.

---

### 2359. IdentityDeletionRequest

```php
final readonly class IdentityDeletionRequest
{
    public function __construct(
        public string $requestId,
        public IdentityIdentifier $identityId,
        public IdentityDeletionMode $mode,
        public IdentityIdentifier|string $requestedBy,
        public string $reason,
        public DateTimeImmutable $requestedAt,
    ) {
    }
}
```

---

### 2360. IdentityDeletionMode

```php
enum IdentityDeletionMode: string
{
    case Logical = 'logical';
    case Anonymize = 'anonymize';
    case CryptoShred = 'crypto_shred';
    case Physical = 'physical';
}
```

---

### 2361. Deletion preconditions

Antes de eliminar deberán verificarse:

* legal hold;
* retention;
* active incidents;
* financial obligations;
* shared ownership;
* delegated resources;
* audit requirements;
* downstream dependencies.

---

### 2362. Deletion tombstones

Podrá conservarse un tombstone mínimo para evitar:

* accidental recreation;
* duplicate identity import;
* identifier reuse;
* reconciliation ambiguity.

---

### 2363. IdentityDeletionTombstone

```php
final readonly class IdentityDeletionTombstone
{
    public function __construct(
        public string $tombstoneId,
        public string $tenantId,
        public string $identityFingerprint,
        public string $deletionReason,
        public DateTimeImmutable $deletedAt,
        public ?DateTimeImmutable $retainUntil,
    ) {
    }
}
```

---

### 2364. Identity restoration

La restauración solo deberá ser posible cuando el modo de eliminación y la policy lo permitan.

---

### 2365. Restoration safeguards

Deberá requerir:

* approval;
* identity re-verification;
* tenant validation;
* credential re-enrollment;
* access reconstruction;
* duplicate detection;
* audit.

---

### 2366. Identity merge

La fusión combina dos o más registros que representan al mismo sujeto.

---

### 2367. IdentityMergeRequest

```php
final readonly class IdentityMergeRequest
{
    public function __construct(
        public string $mergeId,
        public array $sourceIdentityIds,
        public IdentityIdentifier $targetIdentityId,
        public string $tenantId,
        public IdentityIdentifier|string $requestedBy,
        public string $reason,
        public array $mergeRules,
    ) {
    }
}
```

---

### 2368. Merge preconditions

La fusión deberá verificar:

* same subject;
* tenant compatibility;
* identity type compatibility;
* no legal conflict;
* no security incident conflict;
* credential ownership;
* entitlement collisions;
* data ownership.

---

### 2369. Merge authority

La decisión no deberá basarse únicamente en coincidencias débiles como nombre o email.

---

### 2370. Merge evidence

La fusión deberá documentar:

* matching attributes;
* authoritative sources;
* approvals;
* confidence;
* conflicts;
* resolution decisions.

---

### 2371. Merge conflict categories

Conflictos posibles:

* attributes;
* credentials;
* sessions;
* roles;
* groups;
* tenant membership;
* recovery methods;
* privileged status;
* data ownership.

---

### 2372. IdentityMergePlan

```php
final readonly class IdentityMergePlan
{
    public function __construct(
        public IdentityIdentifier $target,
        public array $sources,
        public array $attributeResolutions,
        public array $credentialActions,
        public array $entitlementActions,
        public array $ownershipTransfers,
        public array $conflicts,
    ) {
    }
}
```

---

### 2373. Credential handling during merge

Las credenciales deberán:

* reasociarse solo si su ownership es verificable;
* revocarse si existe duda;
* deduplicarse;
* mantener provenance;
* conservar historial.

---

### 2374. Session handling during merge

Las sesiones existentes deberán revocarse por defecto para evitar contextos inconsistentes.

---

### 2375. Merge rollback

La fusión deberá soportar rollback únicamente mientras no existan efectos irreversibles.

---

### 2376. Merge tombstones

Las identidades fuente deberán conservar tombstones que apunten a la identidad canónica.

---

### 2377. Identity split

La separación divide una identidad incorrectamente fusionada o compartida.

---

### 2378. IdentitySplitRequest

```php
final readonly class IdentitySplitRequest
{
    public function __construct(
        public string $splitId,
        public IdentityIdentifier $sourceIdentityId,
        public array $targetDefinitions,
        public IdentityIdentifier|string $requestedBy,
        public string $reason,
        public array $allocationRules,
    ) {
    }
}
```

---

### 2379. Split complexity

La separación deberá resolver:

* attributes;
* credentials;
* access;
* events;
* ownership;
* sessions;
* audit records;
* external identifiers.

---

### 2380. Split security policy

Cuando no exista evidencia suficiente para asignar una credencial, deberá revocarse en lugar de duplicarse.

---

### 2381. Duplicate identity resolution

VoltStack deberá detectar identidades potencialmente duplicadas.

---

### 2382. DuplicateIdentityCandidate

```php
final readonly class DuplicateIdentityCandidate
{
    public function __construct(
        public IdentityIdentifier $left,
        public IdentityIdentifier $right,
        public float $confidence,
        public array $matchingSignals,
        public array $conflictingSignals,
        public DuplicateResolutionRecommendation $recommendation,
    ) {
    }
}
```

---

### 2383. Duplicate detection signals

Podrán incluir:

* authoritative external ID;
* verified email;
* employee number;
* legal identifier hash;
* device history;
* manager relationship;
* recovery methods;
* federation subject.

---

### 2384. Duplicate detection safeguards

No deberán utilizarse atributos sensibles de manera indiscriminada ni realizar merges automáticos con señales ambiguas.

---

### 2385. DuplicateResolutionRecommendation

```php
enum DuplicateResolutionRecommendation: string
{
    case NoAction = 'no_action';
    case ManualReview = 'manual_review';
    case Link = 'link';
    case Merge = 'merge';
    case InvestigateFraud = 'investigate_fraud';
}
```

---

### 2386. Identity migration

La migración deberá mover identidades entre:

* stores;
* identity providers;
* tenants;
* regions;
* framework versions;
* credential systems;
* schemas.

---

### 2387. IdentityMigrationPlan

```php
final readonly class IdentityMigrationPlan
{
    public function __construct(
        public string $migrationId,
        public array $identityIds,
        public string $sourceSystem,
        public string $targetSystem,
        public IdentityMigrationMode $mode,
        public array $mappingRules,
        public array $validationRules,
        public array $rollbackRules,
    ) {
    }
}
```

---

### 2388. IdentityMigrationMode

```php
enum IdentityMigrationMode: string
{
    case Online = 'online';
    case Offline = 'offline';
    case DualWrite = 'dual_write';
    case ReadThrough = 'read_through';
    case Incremental = 'incremental';
    case Cutover = 'cutover';
}
```

---

### 2389. Migration integrity

La migración deberá preservar:

* identity IDs o mappings;
* provenance;
* credential state;
* authorization version;
* tenant;
* verification status;
* lifecycle state;
* audit references.

---

### 2390. Password migration

Los passwords no deberán descifrarse ni exportarse en plaintext.

Podrán utilizarse:

* legacy hash verification;
* rehash on login;
* password reset;
* staged migration.

---

### 2391. MFA migration

Los factores MFA deberán migrarse únicamente si:

* el formato es seguro;
* el secret permanece protegido;
* la policy lo permite;
* el usuario es notificado;
* existe rollback.

---

### 2392. Migration cutover validation

Antes del cutover deberán verificarse:

* record counts;
* state consistency;
* credential validation;
* authorization equivalence;
* tenant isolation;
* reconciliation results;
* rollback readiness.

---

### 2393. Identity portability

La portabilidad deberá diferenciar entre:

* exportar datos;
* transferir control;
* migrar credenciales;
* recrear identidad;
* mover tenant membership.

---

### 2394. Portable identity package

```php
final readonly class PortableIdentityPackage
{
    public function __construct(
        public string $packageId,
        public IdentityIdentifier $identityId,
        public string $schemaVersion,
        public array $portableAttributes,
        public array $nonPortableReferences,
        public DateTimeImmutable $generatedAt,
        public string $packageDigest,
        public DigitalSignature $signature,
    ) {
    }
}
```

---

### 2395. Portability restrictions

No deberán exportarse:

* password hashes salvo caso controlado;
* private keys;
* recovery secrets;
* internal risk scores;
* confidential administrative notes;
* unrelated tenant data.

---

### 2396. Tenant transfer

Mover una identidad entre tenants deberá considerarse una operación sensible y no una simple actualización de campo.

---

### 2397. TenantTransferPlan

```php
final readonly class TenantTransferPlan
{
    public function __construct(
        public IdentityIdentifier $identityId,
        public string $sourceTenantId,
        public string $targetTenantId,
        public array $attributesToTransfer,
        public array $credentialsToReissue,
        public array $accessToRemove,
        public array $accessToRequest,
        public array $ownershipTransfers,
    ) {
    }
}
```

---

### 2398. Tenant transfer invariants

La transferencia deberá:

* revocar acceso del tenant origen;
* impedir sesiones cruzadas;
* reemitir credenciales tenant-bound;
* reevaluar roles;
* separar datos;
* preservar audit trail;
* evitar leakage.

---

### 2399. Identity lifecycle audit events

Eventos recomendados:

* `IdentityLifecycleCommandRequested`;
* `IdentityCreated`;
* `IdentityVerificationCompleted`;
* `IdentityActivated`;
* `IdentityRestricted`;
* `IdentitySuspended`;
* `IdentityDisabled`;
* `IdentityReactivationRequested`;
* `IdentityReactivated`;
* `IdentityDeprovisioningStarted`;
* `IdentityDeprovisioningCompleted`;
* `ResidualAccessDetected`;
* `IdentityArchived`;
* `IdentityDeletionRequested`;
* `IdentityDeleted`;
* `IdentityRestored`;
* `IdentityMergeRequested`;
* `IdentityMerged`;
* `IdentitySplitRequested`;
* `IdentitySplitCompleted`;
* `DuplicateIdentityDetected`;
* `IdentityMigrationStarted`;
* `IdentityMigrationCompleted`;
* `TenantTransferStarted`;
* `TenantTransferCompleted`;
* `IdentityLifecycleRollbackExecuted`.

---

### 2400. Resultado de esta entrega

Esta entrega establece:

```text
Identity Lifecycle Orchestration
Identity State Machines
Transition Invariants
Lifecycle Policy Evaluation
Identity Creation
Identity Verification
Identity Activation
Partial Activation Handling
Identity Restriction
Identity Suspension
Identity Disablement
Identity Reactivation
Credential Re-enrollment
Identity Deprovisioning
Downstream Deprovisioning Verification
Residual Access Detection
Identity Archival
Identity Deletion
Identity Tombstones
Identity Restoration
Identity Merge
Merge Conflict Resolution
Identity Split
Duplicate Identity Detection
Identity Migration
Password and MFA Migration
Migration Cutover Validation
Identity Portability
Tenant Transfer
Lifecycle Audit Events
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 25

- Identity ownership transfer
- Resource ownership reassignment
- Delegation transfer
- Manager transition
- Tenant transfer execution
- Cross-tenant identity migration
- Lifecycle approval workflows
- Dual-control lifecycle actions
- Lifecycle scheduling
- Effective-date transitions
- Grace periods
- Lifecycle cancellation
- Lifecycle rollback architecture
- Compensating lifecycle actions
- Lifecycle reconciliation
- Lifecycle drift detection
- Lifecycle health monitoring
- Lifecycle governance
- Lifecycle compliance evidence
- Lifecycle exception management
```

## Entrega 25

**Documento:** Parte 05
**Entrega:** 25 de varias
**Cobertura:** Secciones **2401–2500**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 24`

---

### 2401. Identity Ownership Transfer Architecture

VoltStack deberá incorporar una arquitectura formal para transferir ownership de identidades, recursos, delegaciones, relaciones jerárquicas y responsabilidades operativas.

La transferencia deberá diferenciar entre:

* ownership de identidad;
* ownership de recursos;
* ownership administrativo;
* ownership de datos;
* ownership de credenciales;
* ownership de workflows;
* ownership de servicios;
* ownership de entitlements.

---

### 2402. Ownership transfer security goals

La arquitectura deberá garantizar:

* continuidad operativa;
* mínimo acceso residual;
* validación del nuevo owner;
* conservación de provenance;
* separación de funciones;
* protección multi-tenant;
* revocación del owner anterior;
* consistencia entre sistemas;
* reversibilidad controlada;
* auditabilidad.

---

### 2403. Ownership transfer threat model

El modelo deberá considerar:

* transferencia no autorizada;
* owner inexistente;
* self-assignment privilegiado;
* transferencia cross-tenant;
* access inheritance excesivo;
* ownership duplicado;
* recursos huérfanos;
* delegaciones persistentes;
* transferencia durante incidentes;
* race conditions;
* pérdida de accountability;
* reactivación del owner anterior.

---

### 2404. Ownership transfer pipeline

```text
Transfer Trigger
      ↓
Ownership Discovery
      ↓
Current Owner Validation
      ↓
Target Owner Validation
      ↓
Policy Evaluation
      ↓
Approval Resolution
      ↓
Dependency Analysis
      ↓
Transfer Execution
      ↓
Access Reconciliation
      ↓
Verification
      ↓
Audit and Closure
```

---

### 2405. IdentityOwnershipTransferService

```php
interface IdentityOwnershipTransferServiceInterface
{
    public function plan(
        IdentityOwnershipTransferRequest $request
    ): IdentityOwnershipTransferPlan;

    public function execute(
        IdentityOwnershipTransferPlan $plan
    ): IdentityOwnershipTransferResult;
}
```

---

### 2406. IdentityOwnershipTransferRequest

```php
final readonly class IdentityOwnershipTransferRequest
{
    public function __construct(
        public string $requestId,
        public IdentityIdentifier $subjectIdentityId,
        public IdentityIdentifier|string $currentOwner,
        public IdentityIdentifier|string $targetOwner,
        public string $tenantId,
        public OwnershipTransferReason $reason,
        public IdentityIdentifier|string $requestedBy,
        public DateTimeImmutable $requestedAt,
    ) {
    }
}
```

---

### 2407. OwnershipTransferReason

```php
enum OwnershipTransferReason: string
{
    case OrganizationalChange = 'organizational_change';
    case ManagerChange = 'manager_change';
    case RoleChange = 'role_change';
    case Offboarding = 'offboarding';
    case TenantTransfer = 'tenant_transfer';
    case ServiceMigration = 'service_migration';
    case IncidentResponse = 'incident_response';
    case AdministrativeCorrection = 'administrative_correction';
}
```

---

### 2408. Ownership types

VoltStack deberá modelar al menos:

```php
enum OwnershipType: string
{
    case Identity = 'identity';
    case Resource = 'resource';
    case Data = 'data';
    case Credential = 'credential';
    case Workflow = 'workflow';
    case Service = 'service';
    case Entitlement = 'entitlement';
    case Administrative = 'administrative';
}
```

---

### 2409. OwnershipRecord

```php
final readonly class OwnershipRecord
{
    public function __construct(
        public string $ownershipId,
        public OwnershipType $type,
        public string $resourceId,
        public IdentityIdentifier|string $ownerId,
        public string $tenantId,
        public OwnershipState $state,
        public DateTimeImmutable $effectiveFrom,
        public ?DateTimeImmutable $effectiveUntil,
    ) {
    }
}
```

---

### 2410. OwnershipState

```php
enum OwnershipState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Transferring = 'transferring';
    case Superseded = 'superseded';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
}
```

---

### 2411. Target owner validation

El nuevo owner deberá cumplir:

* identidad activa;
* tenant compatible;
* business relationship válida;
* capacidad para administrar el recurso;
* ausencia de conflicto SoD;
* assurance suficiente;
* no estar suspendido;
* no estar bajo incidente relevante.

---

### 2412. Ownership transfer policy engine

```php
interface OwnershipTransferPolicyInterface
{
    public function evaluate(
        IdentityOwnershipTransferRequest $request,
        OwnershipTransferContext $context
    ): OwnershipTransferDecision;
}
```

---

### 2413. OwnershipTransferDecision

```php
final readonly class OwnershipTransferDecision
{
    public function __construct(
        public bool $allowed,
        public array $requiredApprovals,
        public array $requiredChecks,
        public array $resourcesIncluded,
        public array $resourcesExcluded,
        public array $restrictions,
        public array $denialReasons,
    ) {
    }
}
```

---

### 2414. Resource ownership reassignment

La reasignación deberá descubrir todos los recursos vinculados al owner anterior.

---

### 2415. OwnedResourceInventory

```php
final readonly class OwnedResourceInventory
{
    public function __construct(
        public IdentityIdentifier|string $ownerId,
        public string $tenantId,
        public array $resources,
        public array $sharedResources,
        public array $privilegedResources,
        public array $unresolvedResources,
    ) {
    }
}
```

---

### 2416. Resource categories

La búsqueda deberá incluir:

* files;
* records;
* queues;
* dashboards;
* reports;
* API clients;
* service accounts;
* secrets;
* certificates;
* cloud resources;
* workflows;
* approval responsibilities.

---

### 2417. Resource transfer classification

Cada recurso deberá clasificarse como:

* transferable;
* shared;
* non-transferable;
* privileged;
* regulated;
* orphan candidate;
* manual review required.

---

### 2418. ResourceOwnershipTransferPlan

```php
final readonly class ResourceOwnershipTransferPlan
{
    public function __construct(
        public string $planId,
        public IdentityIdentifier|string $sourceOwner,
        public IdentityIdentifier|string $targetOwner,
        public array $directTransfers,
        public array $sharedOwnershipChanges,
        public array $manualReviewItems,
        public array $revocations,
    ) {
    }
}
```

---

### 2419. Shared ownership

Los recursos compartidos deberán definir:

* primary owner;
* co-owners;
* delegates;
* approval rights;
* revocation rules;
* succession policy.

---

### 2420. Orphan resource prevention

No deberá completarse un offboarding si permanecen recursos críticos sin owner válido.

---

### 2421. Delegation transfer

Las delegaciones no deberán transferirse automáticamente sin reevaluación.

---

### 2422. DelegationTransferRequest

```php
final readonly class DelegationTransferRequest
{
    public function __construct(
        public string $delegationId,
        public IdentityIdentifier|string $currentDelegate,
        public IdentityIdentifier|string $targetDelegate,
        public array $scopes,
        public array $resources,
        public DateTimeImmutable $effectiveAt,
        public string $reason,
    ) {
    }
}
```

---

### 2423. Delegation transfer invariants

La transferencia deberá:

* preservar al delegator original;
* validar el nuevo delegate;
* reducir scopes cuando sea necesario;
* mantener expiración;
* no ampliar recursos;
* registrar provenance;
* revocar la delegación anterior.

---

### 2424. Delegation succession

Las delegaciones críticas podrán definir succession rules para ausencia, baja o cambio de rol.

---

### 2425. Manager transition architecture

Los cambios de manager deberán actualizar:

* approval chains;
* access review ownership;
* birthright access;
* delegation relationships;
* reporting hierarchy;
* workflow routing;
* escalation paths.

---

### 2426. ManagerTransitionPlan

```php
final readonly class ManagerTransitionPlan
{
    public function __construct(
        public IdentityIdentifier $employeeIdentityId,
        public ?IdentityIdentifier $previousManager,
        public IdentityIdentifier $newManager,
        public array $approvalAssignments,
        public array $delegations,
        public array $governanceResponsibilities,
        public DateTimeImmutable $effectiveAt,
    ) {
    }
}
```

---

### 2427. Manager transition validation

El nuevo manager deberá:

* pertenecer al tenant;
* estar activo;
* no crear ciclos jerárquicos;
* tener authority suficiente;
* no violar SoD;
* pertenecer a una estructura autorizada.

---

### 2428. Hierarchy cycle prevention

```php
interface ManagementHierarchyValidatorInterface
{
    public function validateTransition(
        IdentityIdentifier $subject,
        IdentityIdentifier $newManager
    ): ManagementHierarchyValidationResult;
}
```

---

### 2429. Tenant transfer execution

La transferencia entre tenants deberá ejecutarse como workflow coordinado y no como actualización directa.

---

### 2430. Tenant transfer phases

```text
Prepare
  ↓
Freeze Source Access
  ↓
Snapshot Source State
  ↓
Remove Source Entitlements
  ↓
Transfer Approved Data
  ↓
Reissue Credentials
  ↓
Provision Target Access
  ↓
Validate Isolation
  ↓
Activate Target Membership
  ↓
Close Source Membership
```

---

### 2431. TenantTransferExecution

```php
final readonly class TenantTransferExecution
{
    public function __construct(
        public string $executionId,
        public TenantTransferPlan $plan,
        public TenantTransferPhase $phase,
        public array $completedSteps,
        public array $pendingSteps,
        public array $failures,
        public DateTimeImmutable $startedAt,
    ) {
    }
}
```

---

### 2432. TenantTransferPhase

```php
enum TenantTransferPhase: string
{
    case Prepared = 'prepared';
    case SourceFrozen = 'source_frozen';
    case SourceAccessRemoved = 'source_access_removed';
    case DataTransferred = 'data_transferred';
    case TargetProvisioned = 'target_provisioned';
    case IsolationValidated = 'isolation_validated';
    case Completed = 'completed';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';
}
```

---

### 2433. Cross-tenant identity migration

La migración cross-tenant deberá tratar atributos, credenciales, recursos y relaciones como dominios separados.

---

### 2434. CrossTenantIdentityMigrationPolicy

```php
interface CrossTenantIdentityMigrationPolicyInterface
{
    public function evaluate(
        TenantTransferPlan $plan,
        CrossTenantMigrationContext $context
    ): CrossTenantMigrationDecision;
}
```

---

### 2435. Cross-tenant migration restrictions

No deberán migrarse automáticamente:

* privileged roles;
* tenant-specific API keys;
* tenant-bound certificates;
* private tenant notes;
* internal risk flags no portables;
* shared secrets;
* ownerless resources.

---

### 2436. Credential reissuance

Las credenciales ligadas al tenant origen deberán revocarse y reemitirse para el tenant destino.

---

### 2437. Session isolation during transfer

Todas las sesiones existentes deberán revocarse antes de activar el nuevo tenant context.

---

### 2438. Tenant transfer data controls

Los datos deberán clasificarse como:

* transferable;
* export-only;
* retain-in-source;
* redactable;
* prohibited;
* legal-review-required.

---

### 2439. Lifecycle approval workflows

Las operaciones de alto riesgo deberán utilizar workflows de aprobación.

---

### 2440. LifecycleApprovalPlan

```php
final readonly class LifecycleApprovalPlan
{
    public function __construct(
        public string $planId,
        public IdentityLifecycleAction $action,
        public array $stages,
        public bool $sequential,
        public DateTimeImmutable $expiresAt,
        public ApprovalEscalationPolicy $escalationPolicy,
    ) {
    }
}
```

---

### 2441. Lifecycle approval stages

Podrán incluir:

* manager;
* resource owner;
* tenant administrator;
* security;
* compliance;
* HR;
* legal;
* data protection;
* system owner.

---

### 2442. Approval context integrity

Los approvers deberán recibir:

* acción;
* identidad;
* current state;
* target state;
* riesgo;
* recursos afectados;
* tenant;
* side effects;
* compensating controls.

---

### 2443. Self-approval prevention

El solicitante no deberá aprobar su propia operación salvo excepción explícita y control compensatorio.

---

### 2444. Approval independence

Para acciones críticas, los approvers deberán pertenecer a funciones independientes.

---

### 2445. Dual-control lifecycle actions

Deberán considerarse dual-control:

* eliminación irreversible;
* tenant transfer;
* identity merge;
* identity split;
* privileged reactivation;
* crypto-shredding;
* ownership transfer de recursos críticos.

---

### 2446. DualControlLifecyclePolicy

```php
final readonly class DualControlLifecyclePolicy
{
    public function __construct(
        public int $minimumApprovers,
        public array $requiredFunctions,
        public bool $requesterExcluded,
        public bool $sameManagerExcluded,
        public DateInterval $approvalWindow,
    ) {
    }
}
```

---

### 2447. Approval expiry

Una aprobación expirada no deberá reutilizarse para ejecutar una transición posterior.

---

### 2448. Approval revocation

Un approver deberá poder retirar una aprobación antes de la ejecución cuando la policy lo permita.

---

### 2449. Approval evidence

Toda aprobación deberá registrar:

* approver;
* decision;
* timestamp;
* rationale;
* authentication assurance;
* conflicts disclosed;
* policy version.

---

### 2450. Lifecycle scheduling

VoltStack deberá soportar transiciones programadas.

---

### 2451. ScheduledLifecycleTransition

```php
final readonly class ScheduledLifecycleTransition
{
    public function __construct(
        public string $scheduleId,
        public IdentityIdentifier $identityId,
        public IdentityLifecycleAction $action,
        public DateTimeImmutable $effectiveAt,
        public string $timezone,
        public array $preconditions,
        public ScheduledTransitionState $state,
    ) {
    }
}
```

---

### 2452. ScheduledTransitionState

```php
enum ScheduledTransitionState: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Executing = 'executing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
```

---

### 2453. Effective-date transitions

Las transiciones deberán poder activarse en:

* fecha de contratación;
* fecha de baja;
* inicio de contrato;
* expiración de acceso;
* cambio organizacional;
* fin de licencia;
* fecha regulatoria.

---

### 2454. Effective time validation

Antes de ejecutar deberá reevaluarse:

* identity state;
* approvals;
* policy version;
* tenant;
* risk;
* owner;
* schedule validity;
* external source status.

---

### 2455. Timezone consistency

Las fechas deberán almacenarse en UTC y conservar la timezone de negocio para interpretación.

---

### 2456. Clock drift tolerance

Las ejecuciones distribuidas deberán definir tolerancia de clock skew.

---

### 2457. Grace periods

Las operaciones podrán incluir grace periods para:

* offboarding;
* access expiration;
* ownership transfer;
* credential rotation;
* contract termination;
* archival.

---

### 2458. LifecycleGracePeriod

```php
final readonly class LifecycleGracePeriod
{
    public function __construct(
        public DateInterval $duration,
        public array $allowedActions,
        public array $blockedActions,
        public bool $newSessionsAllowed,
        public bool $privilegedAccessAllowed,
        public DateTimeImmutable $endsAt,
    ) {
    }
}
```

---

### 2459. Grace period restrictions

Un grace period no deberá preservar privilegios críticos salvo autorización explícita.

---

### 2460. Grace period expiration

Al finalizar deberá ejecutarse automáticamente la transición pendiente o escalarse el fallo.

---

### 2461. Lifecycle cancellation

Las transiciones programadas o pendientes deberán poder cancelarse cuando no hayan producido efectos irreversibles.

---

### 2462. LifecycleCancellationRequest

```php
final readonly class LifecycleCancellationRequest
{
    public function __construct(
        public string $executionId,
        public IdentityIdentifier|string $requestedBy,
        public string $reason,
        public DateTimeImmutable $requestedAt,
    ) {
    }
}
```

---

### 2463. Cancellation policy

La cancelación deberá verificar:

* execution state;
* irreversible steps;
* tenant;
* actor authority;
* pending approvals;
* external side effects;
* compensating actions.

---

### 2464. Partial cancellation

Cuando no pueda revertirse completamente, deberá producirse un plan compensatorio.

---

### 2465. Lifecycle rollback architecture

El rollback deberá restaurar un estado seguro, no necesariamente el estado histórico exacto.

---

### 2466. IdentityLifecycleRollbackPlan

```php
final readonly class IdentityLifecycleRollbackPlan
{
    public function __construct(
        public string $rollbackId,
        public string $executionId,
        public array $reversibleActions,
        public array $compensatingActions,
        public array $irreversibleEffects,
        public array $requiredApprovals,
        public RollbackRiskLevel $riskLevel,
    ) {
    }
}
```

---

### 2467. RollbackRiskLevel

```php
enum RollbackRiskLevel: string
{
    case Low = 'low';
    case Moderate = 'moderate';
    case High = 'high';
    case Critical = 'critical';
}
```

---

### 2468. Rollback invariants

El rollback no deberá:

* reactivar credenciales comprometidas;
* restaurar privilegios expirados;
* ignorar policies actuales;
* reconstruir sesiones antiguas;
* revertir legal holds;
* mezclar tenants;
* borrar evidencia.

---

### 2469. Compensating lifecycle actions

Cuando una acción no sea reversible, deberán ejecutarse compensaciones.

---

### 2470. CompensatingLifecycleAction

```php
final readonly class CompensatingLifecycleAction
{
    public function __construct(
        public string $actionId,
        public string $originalActionId,
        public CompensatingActionType $type,
        public array $parameters,
        public array $expectedOutcomes,
        public bool $manualApprovalRequired,
    ) {
    }
}
```

---

### 2471. CompensatingActionType

```php
enum CompensatingActionType: string
{
    case RevokeAccess = 'revoke_access';
    case ReissueCredential = 'reissue_credential';
    case RestoreOwnership = 'restore_ownership';
    case CreateReplacementIdentity = 'create_replacement_identity';
    case CorrectAttribute = 'correct_attribute';
    case ReconcileDownstream = 'reconcile_downstream';
    case NotifyStakeholders = 'notify_stakeholders';
}
```

---

### 2472. Saga-based lifecycle orchestration

VoltStack podrá modelar workflows distribuidos como sagas con:

* forward actions;
* compensating actions;
* checkpoints;
* retries;
* timeouts;
* human intervention.

---

### 2473. LifecycleExecutionCheckpoint

```php
final readonly class LifecycleExecutionCheckpoint
{
    public function __construct(
        public string $checkpointId,
        public string $executionId,
        public string $stepId,
        public array $completedEffects,
        public array $pendingEffects,
        public DateTimeImmutable $createdAt,
        public string $stateDigest,
    ) {
    }
}
```

---

### 2474. Checkpoint integrity

Cada checkpoint deberá protegerse contra modificación y duplicación.

---

### 2475. Lifecycle reconciliation

VoltStack deberá reconciliar el estado interno con sistemas externos.

---

### 2476. IdentityLifecycleReconciliationService

```php
interface IdentityLifecycleReconciliationServiceInterface
{
    public function reconcile(
        IdentityIdentifier $identityId,
        LifecycleReconciliationContext $context
    ): LifecycleReconciliationReport;
}
```

---

### 2477. LifecycleReconciliationReport

```php
final readonly class LifecycleReconciliationReport
{
    public function __construct(
        public IdentityIdentifier $identityId,
        public IdentityLifecycleState $expectedState,
        public array $observedStates,
        public array $driftItems,
        public array $recommendedActions,
        public DateTimeImmutable $generatedAt,
    ) {
    }
}
```

---

### 2478. Reconciliation scopes

La reconciliación deberá revisar:

* identity state;
* credentials;
* sessions;
* roles;
* groups;
* downstream accounts;
* ownership;
* delegations;
* tenant memberships;
* scheduled actions.

---

### 2479. Authoritative lifecycle state

VoltStack deberá definir qué sistema es authoritative para cada aspecto.

---

### 2480. Lifecycle drift detection

El drift ocurre cuando el estado efectivo difiere del estado esperado.

---

### 2481. LifecycleDriftDetector

```php
interface LifecycleDriftDetectorInterface
{
    public function detect(
        IdentityLifecycleSnapshot $expected,
        array $observedStates
    ): LifecycleDriftReport;
}
```

---

### 2482. LifecycleDriftCategory

```php
enum LifecycleDriftCategory: string
{
    case StateMismatch = 'state_mismatch';
    case ResidualCredential = 'residual_credential';
    case ResidualSession = 'residual_session';
    case ResidualEntitlement = 'residual_entitlement';
    case OwnershipMismatch = 'ownership_mismatch';
    case TenantMismatch = 'tenant_mismatch';
    case MissingDownstreamAccount = 'missing_downstream_account';
    case UnexpectedReactivation = 'unexpected_reactivation';
}
```

---

### 2483. High-risk drift

Deberán considerarse críticos:

* identidad disabled con sesión activa;
* acceso privilegiado residual;
* tenant mismatch;
* credencial activa después de deletion;
* owner anterior aún autorizado;
* cuenta downstream reactivada.

---

### 2484. Automated drift remediation

La remediación automática podrá:

* revocar sesiones;
* revocar credentials;
* remover roles;
* suspender downstream accounts;
* corregir ownership;
* colocar identidad en restricted state.

---

### 2485. Manual remediation

Los casos ambiguos deberán generar tareas para revisión humana.

---

### 2486. Lifecycle health monitoring

VoltStack deberá medir la salud operacional del ciclo de vida.

---

### 2487. IdentityLifecycleHealthStatus

```php
final readonly class IdentityLifecycleHealthStatus
{
    public function __construct(
        public LifecycleHealthState $state,
        public array $degradedConnectors,
        public array $stuckExecutions,
        public array $driftSummary,
        public array $overdueApprovals,
        public DateTimeImmutable $assessedAt,
    ) {
    }
}
```

---

### 2488. LifecycleHealthState

```php
enum LifecycleHealthState: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case AtRisk = 'at_risk';
    case Critical = 'critical';
    case Recovering = 'recovering';
}
```

---

### 2489. Lifecycle health indicators

Indicadores recomendados:

* activation latency;
* deprovisioning latency;
* residual access rate;
* failed transition rate;
* rollback rate;
* stuck workflow count;
* approval expiration rate;
* reconciliation drift;
* orphan resource count;
* reactivation anomalies.

---

### 2490. Lifecycle SLA policies

Deberán definirse SLA por:

* joiner;
* mover;
* leaver;
* emergency disablement;
* privileged deprovisioning;
* tenant transfer;
* ownership transfer;
* incident suspension.

---

### 2491. Lifecycle governance architecture

La gobernanza deberá definir:

* owners;
* authoritative sources;
* transition policies;
* approval models;
* retention;
* exception handling;
* reconciliation frequency;
* incident escalation;
* compliance mappings.

---

### 2492. Lifecycle policy versioning

Toda transición deberá registrar la versión de policy utilizada.

---

### 2493. Lifecycle compliance evidence

VoltStack deberá producir evidencia sobre:

* solicitudes;
* approvals;
* transitions;
* credential revocation;
* session invalidation;
* access removal;
* ownership transfer;
* downstream verification;
* rollback;
* exceptions.

---

### 2494. LifecycleComplianceEvidencePackage

```php
final readonly class LifecycleComplianceEvidencePackage
{
    public function __construct(
        public string $packageId,
        public IdentityIdentifier $identityId,
        public array $transitionRecords,
        public array $approvalRecords,
        public array $reconciliationReports,
        public array $deprovisioningEvidence,
        public array $exceptions,
        public DateTimeImmutable $generatedAt,
        public string $packageDigest,
    ) {
    }
}
```

---

### 2495. Lifecycle exception management

Toda excepción deberá incluir:

* action;
* identity;
* tenant;
* owner;
* justification;
* risk acceptance;
* compensating controls;
* expiration;
* review date;
* approval.

---

### 2496. LifecycleException

```php
final readonly class LifecycleException
{
    public function __construct(
        public string $exceptionId,
        public IdentityIdentifier $identityId,
        public IdentityLifecycleAction $action,
        public string $tenantId,
        public string $justification,
        public array $compensatingControls,
        public IdentityIdentifier|string $approvedBy,
        public DateTimeImmutable $expiresAt,
        public LifecycleExceptionState $state,
    ) {
    }
}
```

---

### 2497. LifecycleExceptionState

```php
enum LifecycleExceptionState: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Closed = 'closed';
}
```

---

### 2498. Exception expiry enforcement

Una excepción expirada deberá dejar de influir inmediatamente en nuevas decisiones.

---

### 2499. Identity lifecycle audit events

Eventos recomendados:

* `IdentityOwnershipTransferRequested`;
* `IdentityOwnershipTransferred`;
* `ResourceOwnershipReassigned`;
* `OrphanResourceDetected`;
* `DelegationTransferRequested`;
* `DelegationTransferred`;
* `ManagerTransitionStarted`;
* `ManagerTransitionCompleted`;
* `TenantTransferExecutionStarted`;
* `TenantTransferExecutionCompleted`;
* `TenantTransferExecutionFailed`;
* `LifecycleApprovalRequested`;
* `LifecycleApprovalGranted`;
* `LifecycleApprovalDenied`;
* `LifecycleTransitionScheduled`;
* `LifecycleTransitionCancelled`;
* `LifecycleGracePeriodStarted`;
* `LifecycleGracePeriodExpired`;
* `LifecycleRollbackPlanned`;
* `LifecycleRollbackExecuted`;
* `CompensatingLifecycleActionExecuted`;
* `LifecycleDriftDetected`;
* `LifecycleDriftRemediated`;
* `LifecycleHealthDegraded`;
* `LifecycleExceptionApproved`;
* `LifecycleExceptionExpired`.

---

### 2500. Resultado de esta entrega

Esta entrega establece:

```text
Identity Ownership Transfer Architecture
Resource Ownership Reassignment
Shared Ownership Governance
Orphan Resource Prevention
Delegation Transfer
Delegation Succession
Manager Transition
Hierarchy Cycle Prevention
Tenant Transfer Execution
Cross-Tenant Identity Migration
Credential Reissuance
Session Isolation During Transfer
Lifecycle Approval Workflows
Self-Approval Prevention
Dual-Control Lifecycle Actions
Lifecycle Scheduling
Effective-Date Transitions
Grace Periods
Lifecycle Cancellation
Lifecycle Rollback Architecture
Compensating Lifecycle Actions
Saga-Based Lifecycle Orchestration
Execution Checkpoints
Lifecycle Reconciliation
Lifecycle Drift Detection
Automated Drift Remediation
Lifecycle Health Monitoring
Lifecycle SLA Policies
Lifecycle Governance
Lifecycle Compliance Evidence
Lifecycle Exception Management
Lifecycle Audit Events
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 26

- Identity proofing architecture
- Proofing assurance levels
- Evidence collection
- Evidence verification
- Authoritative source checks
- Document verification
- Biometric verification boundaries
- Remote identity proofing
- In-person proofing
- Liveness detection
- Fraud signal correlation
- Synthetic identity detection
- Identity proofing vendors
- Proofing result portability
- Proofing evidence retention
- Re-proofing
- Proofing revocation
- Proofing incident response
- Proofing governance
- Proofing audit events
```

## Entrega 26

**Documento:** Parte 05
**Entrega:** 26 de varias
**Cobertura:** Secciones **2501–2600**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 25`

---

### 2501. Identity Proofing Architecture

VoltStack deberá incorporar un subsistema especializado para **Identity Proofing**, responsable de establecer el nivel de confianza inicial y continuo sobre una identidad antes de habilitar autenticación, autorización o asignación de privilegios.

Este subsistema deberá ser independiente del mecanismo de autenticación y reutilizable por:

* onboarding;
* recuperación de cuenta;
* elevación de privilegios;
* acceso privilegiado;
* federación;
* migraciones;
* transferencias entre tenants;
* reactivaciones;
* operaciones regulatorias.

---

### 2502. Identity proofing objectives

El sistema deberá garantizar:

* verificación de identidad;
* autenticidad del sujeto;
* unicidad razonable;
* resistencia al fraude;
* trazabilidad;
* evidencia verificable;
* cumplimiento regulatorio;
* mínima fricción compatible con el riesgo;
* independencia de proveedores.

---

### 2503. Identity proofing threat model

Deberán contemplarse amenazas como:

* identidad sintética;
* robo de identidad;
* documentos falsificados;
* deepfakes;
* biometría manipulada;
* cuentas alquiladas;
* ataques de ingeniería social;
* collusion;
* insiders;
* reutilización de evidencias;
* replay de verificaciones;
* fraude documental.

---

### 2504. Identity proofing pipeline

```text
Identity Request
      ↓
Evidence Collection
      ↓
Evidence Normalization
      ↓
Authenticity Validation
      ↓
Authoritative Source Verification
      ↓
Risk Evaluation
      ↓
Fraud Detection
      ↓
Assurance Scoring
      ↓
Approval Decision
      ↓
Evidence Preservation
```

---

### 2505. IdentityProofingService

```php
interface IdentityProofingServiceInterface
{
    public function start(
        IdentityProofingRequest $request
    ): IdentityProofingSession;

    public function evaluate(
        IdentityProofingSession $session
    ): IdentityProofingDecision;

    public function revoke(
        IdentityProofingRecord $record,
        IdentityProofingRevocationRequest $request
    ): void;
}
```

---

### 2506. IdentityProofingRequest

```php
final readonly class IdentityProofingRequest
{
    public function __construct(
        public IdentityIdentifier $identityId,
        public string $tenantId,
        public IdentityProofingLevel $requiredLevel,
        public array $acceptedEvidenceTypes,
        public string $purpose,
        public IdentityIdentifier|string $requestedBy,
        public DateTimeImmutable $requestedAt,
    ) {}
}
```

---

### 2507. IdentityProofingLevel

```php
enum IdentityProofingLevel: string
{
    case Basic='basic';
    case Standard='standard';
    case High='high';
    case VeryHigh='very_high';
    case Regulated='regulated';
}
```

---

### 2508. Assurance philosophy

El nivel de assurance deberá depender del riesgo de la operación y no únicamente del tipo de usuario.

---

### 2509. Proofing domains

La evaluación podrá abarcar:

* identidad legal;
* identidad laboral;
* identidad académica;
* identidad técnica;
* identidad federada;
* identidad gubernamental;
* identidad organizacional.

---

### 2510. Assurance requirements

Cada nivel deberá definir:

* evidencias mínimas;
* verificaciones obligatorias;
* score mínimo;
* duración;
* revisiones periódicas.

---

### 2511. IdentityProofingSession

```php
final readonly class IdentityProofingSession
{
    public function __construct(
        public string $sessionId,
        public IdentityIdentifier $identityId,
        public IdentityProofingLevel $requiredLevel,
        public IdentityProofingState $state,
        public array $collectedEvidence,
        public DateTimeImmutable $startedAt,
    ) {}
}
```

---

### 2512. IdentityProofingState

```php
enum IdentityProofingState:string
{
    case Pending='pending';
    case CollectingEvidence='collecting';
    case Evaluating='evaluating';
    case Approved='approved';
    case Rejected='rejected';
    case Expired='expired';
    case Revoked='revoked';
}
```

---

### 2513. Evidence collection principles

La recolección deberá cumplir:

* minimización;
* consentimiento cuando aplique;
* cifrado;
* provenance;
* clasificación;
* integridad;
* expiración.

---

### 2514. IdentityEvidence

```php
final readonly class IdentityEvidence
{
    public function __construct(
        public string $evidenceId,
        public IdentityEvidenceType $type,
        public MessageClassification $classification,
        public string $storageReference,
        public string $digest,
        public DateTimeImmutable $collectedAt,
    ) {}
}
```

---

### 2515. IdentityEvidenceType

```php
enum IdentityEvidenceType:string
{
    case Email='email';
    case Phone='phone';
    case Passport='passport';
    case NationalId='national_id';
    case DriverLicense='driver_license';
    case EmployeeRecord='employee_record';
    case GovernmentAssertion='government_assertion';
    case FederationAssertion='federation_assertion';
    case OrganizationRecord='organization_record';
    case ManualReview='manual_review';
}
```

---

### 2516. Evidence normalization

Las evidencias deberán convertirse a un modelo interno uniforme antes de evaluarse.

---

### 2517. Evidence integrity

Toda evidencia deberá poseer:

* digest;
* provenance;
* timestamp;
* collector;
* classification;
* retention profile.

---

### 2518. Evidence authenticity

La autenticidad deberá evaluarse independientemente de la validez del documento.

---

### 2519. Evidence provenance

Cada evidencia deberá indicar:

* origen;
* método de captura;
* canal;
* dispositivo;
* operador;
* proveedor.

---

### 2520. Evidence trust model

No todas las evidencias tendrán el mismo peso dentro del score final.

---

### 2521. IdentityEvidenceWeight

```php
final readonly class IdentityEvidenceWeight
{
    public function __construct(
        public IdentityEvidenceType $type,
        public float $weight,
        public bool $requiresSecondaryValidation,
    ) {}
}
```

---

### 2522. Evidence expiration

Determinadas evidencias deberán expirar automáticamente.

---

### 2523. Reusable evidence

Algunas evidencias podrán reutilizarse mientras permanezcan vigentes y no hayan sido revocadas.

---

### 2524. IdentityEvidenceRepository

```php
interface IdentityEvidenceRepositoryInterface
{
    public function save(IdentityEvidence $evidence): void;

    public function findValidEvidence(
        IdentityIdentifier $identity
    ): array;
}
```

---

### 2525. Authoritative sources

Las verificaciones deberán poder realizarse contra:

* HR;
* ERP;
* directorios;
* proveedores federados;
* registros oficiales;
* bases regulatorias.

---

### 2526. AuthoritativeSourceType

```php
enum AuthoritativeSourceType:string
{
    case InternalHR='internal_hr';
    case Government='government';
    case Federation='federation';
    case EnterpriseDirectory='enterprise_directory';
    case RegulatoryAuthority='regulatory_authority';
}
```

---

### 2527. Authoritative source validation

Las respuestas deberán validarse mediante:

* autenticidad;
* freshness;
* firma;
* canal seguro;
* integridad.

---

### 2528. Source availability

La indisponibilidad de una fuente no deberá generar automáticamente aprobación.

---

### 2529. Multi-source verification

Las operaciones críticas podrán requerir múltiples fuentes independientes.

---

### 2530. Evidence correlation

El motor deberá correlacionar inconsistencias entre evidencias.

---

### 2531. IdentityEvidenceCorrelation

```php
final readonly class IdentityEvidenceCorrelation
{
    public function __construct(
        public array $matchedEvidence,
        public array $conflicts,
        public float $confidence,
    ) {}
}
```

---

### 2532. Document verification

Los documentos deberán verificarse respecto a:

* formato;
* integridad;
* vigencia;
* autenticidad;
* alteraciones.

---

### 2533. Document fraud indicators

Ejemplos:

* OCR inconsistente;
* metadatos alterados;
* imágenes recompuestas;
* zonas manipuladas;
* firmas inválidas.

---

### 2534. DocumentVerificationDecision

```php
final readonly class DocumentVerificationDecision
{
    public function __construct(
        public bool $valid,
        public float $confidence,
        public array $fraudIndicators,
    ) {}
}
```

---

### 2535. Manual verification

La revisión manual deberá quedar completamente auditada.

---

### 2536. Reviewer independence

El revisor no deberá ser el solicitante.

---

### 2537. Manual review evidence

Las decisiones manuales deberán registrar:

* revisor;
* evidencia consultada;
* razonamiento;
* timestamp;
* herramientas utilizadas.

---

### 2538. Biometric verification boundaries

VoltStack no impondrá biometría.

La biometría será un proveedor opcional.

---

### 2539. Biometric abstraction

```php
interface BiometricVerificationProviderInterface
{
    public function verify(
        BiometricVerificationRequest $request
    ): BiometricVerificationResult;
}
```

---

### 2540. Biometric privacy

Nunca deberán almacenarse plantillas biométricas salvo política explícita.

---

### 2541. Remote identity proofing

La verificación remota deberá minimizar riesgo de spoofing.

---

### 2542. Remote proofing controls

Podrán utilizarse:

* videoconferencia;
* documentos dinámicos;
* challenge-response;
* certificados;
* firmas;
* canales secundarios.

---

### 2543. Remote session integrity

La sesión remota deberá estar protegida contra replay.

---

### 2544. In-person proofing

La verificación presencial podrá registrar:

* operador;
* ubicación;
* evidencias revisadas;
* resultado.

---

### 2545. InPersonProofingRecord

```php
final readonly class InPersonProofingRecord
{
    public function __construct(
        public string $recordId,
        public string $operatorId,
        public string $locationId,
        public array $reviewedEvidence,
        public DateTimeImmutable $verifiedAt,
    ) {}
}
```

---

### 2546. Liveness detection

La detección de presencia deberá desacoplarse del proveedor biométrico.

---

### 2547. LivenessAssessment

```php
final readonly class LivenessAssessment
{
    public function __construct(
        public bool $passed,
        public float $confidence,
        public array $signals,
    ) {}
}
```

---

### 2548. Anti-spoofing

Los proveedores deberán informar:

* replay attempts;
* deepfake suspicion;
* synthetic media indicators.

---

### 2549. Fraud signal correlation

Las señales deberán combinar:

* dispositivo;
* geografía;
* comportamiento;
* documentos;
* directorios;
* identidad histórica.

---

### 2550. FraudCorrelationEngine

```php
interface FraudCorrelationEngineInterface
{
    public function correlate(
        IdentityProofingSession $session
    ): FraudCorrelationResult;
}
```

---

### 2551. FraudCorrelationResult

```php
final readonly class FraudCorrelationResult
{
    public function __construct(
        public float $riskScore,
        public array $signals,
        public array $recommendations,
    ) {}
}
```

---

### 2552. Synthetic identity detection

El sistema deberá identificar patrones compatibles con identidades sintéticas.

---

### 2553. Synthetic identity indicators

Ejemplos:

* atributos incompatibles;
* historiales imposibles;
* documentos inconsistentes;
* múltiples dispositivos;
* múltiples identidades relacionadas.

---

### 2554. Proofing risk engine

La decisión final deberá considerar:

* fraude;
* assurance;
* evidencia;
* contexto;
* tenant;
* operación solicitada.

---

### 2555. IdentityProofingDecision

```php
final readonly class IdentityProofingDecision
{
    public function __construct(
        public bool $approved,
        public IdentityProofingLevel $achievedLevel,
        public float $confidence,
        public array $conditions,
        public array $evidence,
        public array $denialReasons,
    ) {}
}
```

---

### 2556. Conditional approvals

La aprobación podrá requerir:

* MFA obligatorio;
* revisión posterior;
* acceso restringido;
* expiración reducida.

---

### 2557. Vendor abstraction

VoltStack deberá desacoplar completamente proveedores de proofing.

---

### 2558. IdentityProofingProvider

```php
interface IdentityProofingProviderInterface
{
    public function execute(
        IdentityProofingSession $session
    ): IdentityProofingProviderResult;
}
```

---

### 2559. Multi-vendor strategy

Podrán utilizarse múltiples proveedores para incrementar confianza.

---

### 2560. Vendor independence

La sustitución de proveedor no deberá afectar el dominio interno.

---

### 2561. Proofing portability

Los resultados deberán poder exportarse mediante un formato canónico.

---

### 2562. PortableProofingRecord

```php
final readonly class PortableProofingRecord
{
    public function __construct(
        public string $proofingId,
        public IdentityProofingLevel $level,
        public array $evidenceReferences,
        public float $confidence,
        public DateTimeImmutable $verifiedAt,
        public ?DateTimeImmutable $expiresAt,
    ) {}
}
```

---

### 2563. Proofing evidence retention

Cada tipo de evidencia deberá definir:

* retención;
* destrucción;
* archivado;
* anonimización.

---

### 2564. Retention minimization

No deberá conservarse evidencia más allá de lo requerido.

---

### 2565. Re-proofing

Las identidades podrán requerir nueva verificación por:

* expiración;
* cambio de riesgo;
* incidente;
* privilegio elevado;
* cambio regulatorio.

---

### 2566. ReProofingRequest

```php
final readonly class ReProofingRequest
{
    public function __construct(
        public IdentityIdentifier $identityId,
        public string $reason,
        public IdentityProofingLevel $requiredLevel,
        public DateTimeImmutable $requestedAt,
    ) {}
}
```

---

### 2567. Continuous proofing

La confianza podrá disminuir con el tiempo.

---

### 2568. Proofing score decay

El assurance podrá degradarse progresivamente cuando no existan nuevas verificaciones.

---

### 2569. Proofing revocation

Los resultados podrán revocarse por:

* fraude;
* error;
* evidencia falsa;
* proveedor comprometido;
* incidente.

---

### 2570. IdentityProofingRevocationRequest

```php
final readonly class IdentityProofingRevocationRequest
{
    public function __construct(
        public string $reason,
        public IdentityIdentifier|string $requestedBy,
        public DateTimeImmutable $requestedAt,
    ) {}
}
```

---

### 2571. Revocation side effects

La revocación podrá:

* suspender identidad;
* invalidar privilegios;
* requerir re-proofing;
* iniciar investigación.

---

### 2572. Revocation propagation

La revocación deberá propagarse a sistemas dependientes.

---

### 2573. Proofing incident response

Los incidentes deberán poder iniciarse automáticamente.

---

### 2574. Incident triggers

Ejemplos:

* proveedor comprometido;
* fraude detectado;
* evidencia manipulada;
* identidad sintética;
* deepfake confirmado.

---

### 2575. Vendor compromise

Un proveedor comprometido podrá invalidar verificaciones emitidas durante una ventana temporal.

---

### 2576. Proofing reassessment

Las verificaciones afectadas deberán reevaluarse.

---

### 2577. Evidence chain of custody

Toda evidencia deberá mantener cadena de custodia.

---

### 2578. Evidence transfer audit

Toda transferencia deberá auditarse.

---

### 2579. Proofing governance

La gobernanza deberá definir:

* niveles;
* proveedores;
* políticas;
* retención;
* excepciones;
* métricas.

---

### 2580. Governance ownership

Deberán existir responsables claros para:

* proofing;
* fraude;
* privacidad;
* auditoría.

---

### 2581. Proofing metrics

Métricas sugeridas:

* approval rate;
* rejection rate;
* fraud detection;
* false positives;
* re-proofing rate.

---

### 2582. SLA

Cada nivel de proofing podrá definir SLA diferentes.

---

### 2583. Compliance mappings

El sistema deberá mapear controles hacia:

* NIST 800-63;
* ISO 27001;
* ISO 29115;
* SOC2;
* GDPR;
* HIPAA cuando aplique.

---

### 2584. Privacy by design

El proofing deberá diseñarse bajo principios de minimización.

---

### 2585. Explainability

Las decisiones automatizadas deberán ser explicables cuando sea posible.

---

### 2586. Human override

Las decisiones automáticas podrán revisarse manualmente bajo políticas definidas.

---

### 2587. Transparency

El sujeto podrá conocer, cuando la regulación lo permita:

* estado;
* resultado;
* expiración;
* revisiones.

---

### 2588. Appeals

Las decisiones negativas deberán admitir procesos de apelación.

---

### 2589. Appeal workflow

La apelación deberá ser independiente del evaluador inicial.

---

### 2590. Appeal evidence

La evidencia adicional deberá conservar provenance.

---

### 2591. Audit logging

Toda operación deberá auditarse.

---

### 2592. Audit events

Eventos recomendados:

* `IdentityProofingStarted`
* `EvidenceCollected`
* `EvidenceVerified`
* `DocumentValidated`
* `FraudDetected`
* `IdentityApproved`
* `IdentityRejected`
* `IdentityReProofingStarted`
* `IdentityProofingRevoked`

---

### 2593. Long-term integrity

Los registros deberán protegerse mediante hashes y firmas.

---

### 2594. Cryptographic verification

Las evidencias firmadas deberán verificarse antes de aceptarse.

---

### 2595. Future algorithm migration

El modelo deberá soportar migración criptográfica.

---

### 2596. Testing strategy

El sistema deberá incluir:

* pruebas unitarias;
* integración;
* fraude simulado;
* chaos testing;
* fuzzing documental.

---

### 2597. Extensibility

Nuevos proveedores deberán integrarse mediante plugins.

---

### 2598. Performance considerations

El proofing deberá ser asincrónico cuando la operación lo permita.

---

### 2599. Security recommendations

Nunca confiar en una única evidencia ni en un único proveedor para operaciones de alto riesgo.

---

### 2600. Resultado de esta entrega

Esta entrega incorpora:

```text
Identity Proofing Architecture
Proofing Assurance Levels
Evidence Collection
Evidence Verification
Evidence Correlation
Authoritative Source Validation
Document Verification
Biometric Provider Abstraction
Remote Identity Proofing
In-Person Proofing
Liveness Detection
Fraud Correlation
Synthetic Identity Detection
Vendor Abstraction
Portable Proofing Records
Evidence Retention
Continuous Re-Proofing
Proofing Revocation
Proofing Incident Response
Proofing Governance
Compliance Mapping
Appeal Workflows
Audit Events
Performance Model
Testing Strategy
```

**La Entrega 27 continuará con:**

* Identity Recovery Architecture
* Secure Account Recovery
* Recovery Assurance Levels
* Recovery Tokens
* Recovery Secrets
* Recovery Contacts
* Recovery Devices
* Recovery Escrow
* Identity Resurrection Prevention
* Recovery Fraud Detection
* Recovery Session Security
* Recovery Approval Workflows
* Privileged Account Recovery
* Break Glass Recovery
* Recovery Governance
* Recovery Compliance
* Recovery Incident Response
* Recovery Audit Events
* Recovery Metrics
* Recovery Roadmap

## Entrega 27

**Documento:** Parte 05
**Entrega:** 27 de varias
**Cobertura:** Secciones **2601–2700**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 26`

---

### 2601. Identity Recovery Architecture

VoltStack deberá incorporar una arquitectura formal para recuperar identidades cuando el sujeto legítimo pierde acceso a:

* credenciales;
* factores MFA;
* dispositivos confiables;
* cuentas federadas;
* recovery secrets;
* certificados;
* passkeys;
* canales de contacto;
* cuentas privilegiadas.

La recuperación deberá tratarse como una nueva verificación de confianza y no como un simple restablecimiento de contraseña.

---

### 2602. Identity recovery security goals

El subsistema deberá garantizar:

* recuperación del sujeto legítimo;
* resistencia a account takeover;
* assurance proporcional al riesgo;
* aislamiento de sesiones;
* invalidación de credenciales comprometidas;
* trazabilidad;
* reversibilidad limitada;
* mínima exposición de información;
* notificación independiente;
* cumplimiento regulatorio.

---

### 2603. Recovery threat model

Deberán contemplarse:

* robo de correo;
* SIM swapping;
* ingeniería social;
* abuso de soporte;
* recovery token theft;
* recovery contact compromise;
* insider threat;
* bypass de MFA;
* deepfake;
* recuperación de cuentas eliminadas;
* credential stuffing;
* session fixation;
* explotación de respuestas de seguridad;
* takeover de cuentas privilegiadas;
* abuso de break-glass.

---

### 2604. Recovery trust principle

Una recuperación no deberá tener menos assurance que el nivel requerido para operar la identidad recuperada.

---

### 2605. Recovery pipeline

```text
Recovery Request
      ↓
Identity Discovery
      ↓
Risk and Context Assessment
      ↓
Recovery Method Resolution
      ↓
Evidence Collection
      ↓
Recovery Proofing
      ↓
Approval and Policy Evaluation
      ↓
Credential Reset
      ↓
Session Revocation
      ↓
Access Restriction
      ↓
Post-Recovery Monitoring
```

---

### 2606. IdentityRecoveryService

```php
interface IdentityRecoveryServiceInterface
{
    public function start(
        IdentityRecoveryRequest $request
    ): IdentityRecoverySession;

    public function verify(
        IdentityRecoverySession $session,
        RecoveryEvidenceBundle $evidence
    ): IdentityRecoveryDecision;

    public function complete(
        IdentityRecoverySession $session,
        IdentityRecoveryDecision $decision
    ): IdentityRecoveryResult;
}
```

---

### 2607. IdentityRecoveryRequest

```php
final readonly class IdentityRecoveryRequest
{
    public function __construct(
        public string $requestId,
        public IdentityIdentifier|string $claimedIdentity,
        public string $tenantId,
        public RecoveryReason $reason,
        public RecoveryChannel $initiatedThrough,
        public array $context,
        public DateTimeImmutable $requestedAt,
    ) {
    }
}
```

---

### 2608. RecoveryReason

```php
enum RecoveryReason: string
{
    case PasswordLost = 'password_lost';
    case MfaDeviceLost = 'mfa_device_lost';
    case PasskeyLost = 'passkey_lost';
    case AccountLocked = 'account_locked';
    case FederatedAccessLost = 'federated_access_lost';
    case DeviceCompromised = 'device_compromised';
    case CredentialCompromise = 'credential_compromise';
    case AdministrativeRecovery = 'administrative_recovery';
    case PrivilegedRecovery = 'privileged_recovery';
}
```

---

### 2609. RecoveryChannel

```php
enum RecoveryChannel: string
{
    case SelfService = 'self_service';
    case Support = 'support';
    case Administrator = 'administrator';
    case InPerson = 'in_person';
    case Federated = 'federated';
    case Emergency = 'emergency';
}
```

---

### 2610. Recovery assurance levels

VoltStack deberá definir niveles explícitos de assurance de recuperación.

---

### 2611. RecoveryAssuranceLevel

```php
enum RecoveryAssuranceLevel: string
{
    case Low = 'low';
    case Standard = 'standard';
    case High = 'high';
    case Privileged = 'privileged';
    case Emergency = 'emergency';
}
```

---

### 2612. Recovery assurance inputs

El nivel requerido deberá depender de:

* identity type;
* tenant;
* privilegios;
* data sensitivity;
* recovery reason;
* risk score;
* last trusted authentication;
* compromised factors;
* active incident state;
* regulatory requirements.

---

### 2613. Recovery policy engine

```php
interface IdentityRecoveryPolicyEngineInterface
{
    public function evaluate(
        IdentityRecoveryRequest $request,
        IdentityRecoveryContext $context
    ): IdentityRecoveryPolicyDecision;
}
```

---

### 2614. IdentityRecoveryPolicyDecision

```php
final readonly class IdentityRecoveryPolicyDecision
{
    public function __construct(
        public bool $allowed,
        public RecoveryAssuranceLevel $requiredAssurance,
        public array $allowedMethods,
        public array $prohibitedMethods,
        public array $requiredApprovals,
        public array $postRecoveryRestrictions,
        public array $denialReasons,
    ) {
    }
}
```

---

### 2615. Identity discovery security

El proceso no deberá revelar si una identidad existe mediante respuestas distinguibles.

---

### 2616. Enumeration resistance

Las respuestas públicas deberán evitar revelar:

* cuenta existente;
* email registrado;
* teléfono registrado;
* tenant membership;
* identity state;
* MFA methods;
* privileged status.

---

### 2617. Recovery session

Toda operación deberá ejecutarse dentro de una sesión de recuperación aislada.

---

### 2618. IdentityRecoverySession

```php
final readonly class IdentityRecoverySession
{
    public function __construct(
        public string $sessionId,
        public IdentityIdentifier $identityId,
        public RecoveryAssuranceLevel $requiredAssurance,
        public IdentityRecoveryState $state,
        public array $allowedMethods,
        public array $completedChallenges,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 2619. IdentityRecoveryState

```php
enum IdentityRecoveryState: string
{
    case Initiated = 'initiated';
    case Challenging = 'challenging';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completing = 'completing';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Compromised = 'compromised';
}
```

---

### 2620. Recovery session isolation

La recovery session no deberá compartir:

* session cookie principal;
* authentication context;
* CSRF token;
* authorization state;
* device trust;
* privileged session.

---

### 2621. Recovery session binding

La sesión deberá ligarse, cuando sea seguro, a:

* browser instance;
* device;
* network risk context;
* nonce;
* proofing transaction;
* requested identity;
* tenant.

---

### 2622. Recovery session expiration

Las sesiones deberán ser breves y expirar por:

* tiempo;
* inactividad;
* cambio de dispositivo;
* cambio de red de alto riesgo;
* exceso de intentos;
* incidente;
* completion.

---

### 2623. Recovery session rotation

El identificador de sesión deberá rotarse después de cada transición de assurance significativa.

---

### 2624. Recovery token architecture

Los recovery tokens deberán ser:

* aleatorios;
* de un solo uso;
* con expiración corta;
* vinculados a propósito;
* vinculados a identidad;
* revocables;
* almacenados mediante hash;
* resistentes a replay.

---

### 2625. RecoveryToken

```php
final readonly class RecoveryToken
{
    public function __construct(
        public string $tokenId,
        public IdentityIdentifier $identityId,
        public RecoveryTokenPurpose $purpose,
        public string $tokenHash,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public RecoveryTokenState $state,
    ) {
    }
}
```

---

### 2626. RecoveryTokenPurpose

```php
enum RecoveryTokenPurpose: string
{
    case StartRecovery = 'start_recovery';
    case VerifyChannel = 'verify_channel';
    case ResetCredential = 'reset_credential';
    case EnrollNewFactor = 'enroll_new_factor';
    case ApproveRecovery = 'approve_recovery';
    case CancelRecovery = 'cancel_recovery';
}
```

---

### 2627. RecoveryTokenState

```php
enum RecoveryTokenState: string
{
    case Active = 'active';
    case Used = 'used';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
}
```

---

### 2628. Token lookup resistance

El almacenamiento deberá impedir recuperar el token plaintext desde la base de datos.

---

### 2629. Token replay prevention

El consumo deberá ser atómico y marcar el token como usado antes de ejecutar efectos sensibles.

---

### 2630. Recovery link security

Los enlaces no deberán incluir:

* datos personales;
* tenant names sensibles;
* privilegios;
* estado de cuenta;
* tokens reutilizables;
* credenciales.

---

### 2631. Recovery secrets

Los recovery secrets podrán utilizarse como factor complementario, nunca como única prueba para operaciones críticas.

---

### 2632. RecoverySecret

```php
final readonly class RecoverySecret
{
    public function __construct(
        public string $secretId,
        public IdentityIdentifier $identityId,
        public RecoverySecretType $type,
        public string $secretHash,
        public RecoverySecretState $state,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 2633. RecoverySecretType

```php
enum RecoverySecretType: string
{
    case BackupCode = 'backup_code';
    case RecoveryPhrase = 'recovery_phrase';
    case EscrowedSecret = 'escrowed_secret';
    case OrganizationIssuedCode = 'organization_issued_code';
}
```

---

### 2634. Security questions prohibition

VoltStack no deberá recomendar preguntas de seguridad basadas en información personal fácilmente descubrible.

---

### 2635. Backup codes

Los códigos deberán:

* ser de un solo uso;
* almacenarse con hash;
* poder revocarse;
* regenerarse como conjunto;
* invalidar el conjunto anterior;
* auditar su consumo.

---

### 2636. Recovery contacts

Una identidad podrá registrar contactos de recuperación bajo policy.

---

### 2637. RecoveryContact

```php
final readonly class RecoveryContact
{
    public function __construct(
        public string $contactId,
        public IdentityIdentifier $ownerIdentityId,
        public IdentityIdentifier|string $contactIdentity,
        public RecoveryContactType $type,
        public RecoveryContactState $state,
        public DateTimeImmutable $verifiedAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 2638. RecoveryContactType

```php
enum RecoveryContactType: string
{
    case Personal = 'personal';
    case Manager = 'manager';
    case Administrator = 'administrator';
    case SecurityOfficer = 'security_officer';
    case Organization = 'organization';
}
```

---

### 2639. Recovery contact safeguards

El contacto no deberá:

* recuperar su propia cuenta mediante sí mismo;
* aprobar si tiene conflicto;
* conocer credenciales;
* recibir acceso automático;
* ampliar el alcance solicitado.

---

### 2640. Recovery contact verification

Los contactos deberán verificarse antes de ser aceptados y periódicamente después.

---

### 2641. Contact change cooling period

Cambiar un recovery contact deberá activar un periodo de enfriamiento antes de poder usarlo.

---

### 2642. Recovery devices

Los dispositivos previamente confiables podrán aportar señales, pero no garantizar recuperación por sí solos.

---

### 2643. RecoveryDeviceRecord

```php
final readonly class RecoveryDeviceRecord
{
    public function __construct(
        public string $deviceId,
        public IdentityIdentifier $identityId,
        public DeviceTrustLevel $trustLevel,
        public DateTimeImmutable $lastTrustedAt,
        public RecoveryDeviceState $state,
    ) {
    }
}
```

---

### 2644. RecoveryDeviceState

```php
enum RecoveryDeviceState: string
{
    case Trusted = 'trusted';
    case Unknown = 'unknown';
    case Lost = 'lost';
    case Compromised = 'compromised';
    case Revoked = 'revoked';
}
```

---

### 2645. Device signal limitations

La posesión de una cookie o fingerprint no deberá interpretarse como prueba suficiente de identidad.

---

### 2646. Recovery escrow

VoltStack podrá soportar escrow institucional para identidades empresariales.

---

### 2647. RecoveryEscrowRecord

```php
final readonly class RecoveryEscrowRecord
{
    public function __construct(
        public string $escrowId,
        public IdentityIdentifier $identityId,
        public string $custodianId,
        public string $encryptedMaterialReference,
        public array $releaseConditions,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
```

---

### 2648. Escrow release policy

La liberación deberá requerir:

* identidad del custodio;
* approvals;
* purpose binding;
* incident check;
* audit;
* dual control cuando aplique.

---

### 2649. Recovery evidence bundle

```php
final readonly class RecoveryEvidenceBundle
{
    public function __construct(
        public array $proofingEvidence,
        public array $possessionEvidence,
        public array $organizationalEvidence,
        public array $approvalEvidence,
        public array $riskSignals,
    ) {
    }
}
```

---

### 2650. Recovery proofing

La recuperación deberá reutilizar la arquitectura de identity proofing definida en la Entrega 26.

---

### 2651. Recovery proofing differences

El proofing de recuperación deberá considerar:

* factores posiblemente comprometidos;
* información histórica;
* comportamiento previo;
* último acceso confiable;
* cambios recientes;
* riesgo de coerción;
* canales perdidos.

---

### 2652. Evidence independence

Para assurance alto deberán utilizarse pruebas independientes entre sí.

---

### 2653. Channel independence

Dos desafíos enviados al mismo correo comprometido no deberán contarse como dos factores independientes.

---

### 2654. Recovery risk engine

```php
interface IdentityRecoveryRiskEngineInterface
{
    public function assess(
        IdentityRecoverySession $session,
        RecoveryEvidenceBundle $evidence
    ): IdentityRecoveryRiskAssessment;
}
```

---

### 2655. IdentityRecoveryRiskAssessment

```php
final readonly class IdentityRecoveryRiskAssessment
{
    public function __construct(
        public float $riskScore,
        public ThreatSeverity $severity,
        public ThreatConfidence $confidence,
        public array $signals,
        public array $requiredEscalations,
    ) {
    }
}
```

---

### 2656. Recovery fraud signals

Se deberán analizar:

* geografía inusual;
* nuevo dispositivo;
* proxy o anonymizer;
* cambio reciente de contacto;
* múltiples intentos;
* identidad privilegiada;
* soporte manipulado;
* comportamiento automatizado;
* deepfake suspicion;
* inconsistencias documentales.

---

### 2657. SIM swap risk

La verificación por SMS deberá degradarse cuando existan señales de:

* cambio reciente de SIM;
* portabilidad reciente;
* número reciclado;
* operador desconocido;
* geografía inconsistente.

---

### 2658. Email compromise risk

El email no deberá considerarse confiable si:

* su contraseña fue restablecida recientemente;
* existe forwarding desconocido;
* el dominio está comprometido;
* la sesión proviene de riesgo alto;
* el correo es el factor perdido.

---

### 2659. Social engineering resistance

Los operadores de soporte deberán utilizar scripts, políticas y límites que reduzcan decisiones improvisadas.

---

### 2660. Support recovery controls

El soporte no deberá poder:

* desactivar MFA unilateralmente;
* revelar atributos sensibles;
* cambiar tenant;
* asignar roles;
* omitir proofing;
* aprobar su propia solicitud.

---

### 2661. SupportRecoveryCase

```php
final readonly class SupportRecoveryCase
{
    public function __construct(
        public string $caseId,
        public IdentityIdentifier $identityId,
        public string $assignedOperatorId,
        public RecoveryAssuranceLevel $requiredAssurance,
        public array $evidenceReferences,
        public SupportRecoveryCaseState $state,
        public DateTimeImmutable $openedAt,
    ) {
    }
}
```

---

### 2662. SupportRecoveryCaseState

```php
enum SupportRecoveryCaseState: string
{
    case Open = 'open';
    case Investigating = 'investigating';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Escalated = 'escalated';
    case Closed = 'closed';
}
```

---

### 2663. Recovery approval workflows

Las recuperaciones de riesgo alto deberán requerir approvals independientes.

---

### 2664. RecoveryApprovalPlan

```php
final readonly class RecoveryApprovalPlan
{
    public function __construct(
        public string $planId,
        public array $requiredApprovers,
        public int $minimumApprovals,
        public bool $sequential,
        public DateTimeImmutable $expiresAt,
        public array $conflictRules,
    ) {
    }
}
```

---

### 2665. Approval functions

Los approvers podrán incluir:

* manager;
* tenant administrator;
* security;
* identity operations;
* HR;
* compliance;
* privileged access owner.

---

### 2666. Recovery self-approval prevention

El sujeto, solicitante, operador y approver no deberán concentrarse en una sola identidad para operaciones críticas.

---

### 2667. Recovery decision

```php
final readonly class IdentityRecoveryDecision
{
    public function __construct(
        public bool $approved,
        public RecoveryAssuranceLevel $achievedAssurance,
        public array $approvedActions,
        public array $requiredRestrictions,
        public array $credentialActions,
        public array $sessionActions,
        public array $denialReasons,
    ) {
    }
}
```

---

### 2668. Recovery completion sequence

```text
Approve Recovery
      ↓
Revoke Existing Sessions
      ↓
Revoke Compromised Credentials
      ↓
Rotate Recovery Secrets
      ↓
Issue Temporary Recovery Context
      ↓
Enroll New Authentication Factors
      ↓
Apply Restricted Mode
      ↓
Notify Trusted Channels
      ↓
Start Enhanced Monitoring
```

---

### 2669. Credential reset security

El restablecimiento deberá:

* impedir reutilización inmediata;
* validar políticas actuales;
* revocar tokens relacionados;
* rotar secrets;
* invalidar password reset tokens;
* registrar provenance.

---

### 2670. MFA recovery

La recuperación de MFA deberá diferenciar:

* factor perdido;
* factor comprometido;
* factor temporalmente inaccesible;
* todos los factores perdidos;
* dispositivo principal comprometido.

---

### 2671. MFA factor replacement

Un nuevo factor no deberá activarse plenamente hasta completar el assurance requerido.

---

### 2672. Temporary recovery factor

```php
final readonly class TemporaryRecoveryFactor
{
    public function __construct(
        public string $factorId,
        public IdentityIdentifier $identityId,
        public array $allowedActions,
        public DateTimeImmutable $expiresAt,
        public bool $singleUse,
    ) {
    }
}
```

---

### 2673. Temporary factor restrictions

No deberá permitir:

* cambios administrativos;
* creación de API keys;
* acceso privilegiado;
* modificación de recovery contacts;
* transferencia de tenant;
* eliminación de evidencia.

---

### 2674. Post-recovery restricted mode

Toda recuperación de alto riesgo deberá poder activar un modo restringido.

---

### 2675. PostRecoveryRestrictionProfile

```php
final readonly class PostRecoveryRestrictionProfile
{
    public function __construct(
        public DateInterval $duration,
        public array $blockedActions,
        public array $requiredStepUpActions,
        public bool $privilegedAccessBlocked,
        public bool $recoverySettingsLocked,
    ) {
    }
}
```

---

### 2676. Recovery cooling period

Durante el cooling period deberán bloquearse cambios como:

* recovery contacts;
* email principal;
* teléfono;
* MFA removal;
* passkey removal;
* tenant transfer;
* privileged elevation;
* payout or financial destination.

---

### 2677. Trusted-channel notification

La recuperación deberá notificarse por canales previamente registrados que no hayan participado en la recuperación.

---

### 2678. Recovery cancellation

La notificación deberá permitir cancelar o disputar la recuperación cuando todavía sea reversible.

---

### 2679. Recovery dispute

```php
final readonly class RecoveryDispute
{
    public function __construct(
        public string $disputeId,
        public string $recoverySessionId,
        public IdentityIdentifier|string $reportedBy,
        public string $reason,
        public DateTimeImmutable $reportedAt,
    ) {
    }
}
```

---

### 2680. Identity resurrection prevention

VoltStack deberá impedir que una recuperación reactive identidades:

* eliminadas;
* archivadas irreversiblemente;
* legalmente bloqueadas;
* transferidas;
* merged como source;
* deshabilitadas permanentemente.

---

### 2681. Identity state check

Antes de completar deberá reevaluarse el estado lifecycle actual y no confiar en el snapshot inicial.

---

### 2682. Deleted identity recovery

Una identidad eliminada solo podrá restaurarse mediante el workflow de restoration, no mediante recuperación ordinaria.

---

### 2683. Merged identity recovery

Las solicitudes para identidades fusionadas deberán redirigirse de forma segura hacia la identidad canónica sin revelar detalles internos.

---

### 2684. Privileged account recovery

Las identidades privilegiadas deberán operar bajo requisitos reforzados.

---

### 2685. PrivilegedRecoveryPolicy

```php
final readonly class PrivilegedRecoveryPolicy
{
    public function __construct(
        public RecoveryAssuranceLevel $minimumAssurance,
        public int $minimumApprovals,
        public bool $inPersonVerificationRequired,
        public bool $privilegesRevokedDuringRecovery,
        public DateInterval $restrictedPeriod,
    ) {
    }
}
```

---

### 2686. Privilege revocation during recovery

Los roles privilegiados deberán suspenderse antes de reemitir credenciales.

---

### 2687. Privilege restoration

La recuperación de identidad y la restauración de privilegios deberán ser decisiones separadas.

---

### 2688. Break-glass recovery

VoltStack podrá soportar recuperación de emergencia para continuidad operacional.

---

### 2689. BreakGlassRecoveryRequest

```php
final readonly class BreakGlassRecoveryRequest
{
    public function __construct(
        public string $requestId,
        public IdentityIdentifier $identityId,
        public string $emergencyReason,
        public array $requestedCapabilities,
        public IdentityIdentifier|string $requestedBy,
        public DateTimeImmutable $requestedAt,
    ) {
    }
}
```

---

### 2690. Break-glass safeguards

La recuperación break-glass deberá exigir:

* incident reference;
* dual control;
* scope mínimo;
* expiración corta;
* recording;
* notification;
* after-action review;
* automatic revocation.

---

### 2691. Break-glass credential

La credencial de emergencia deberá ser:

* temporal;
* purpose-bound;
* scope-bound;
* tenant-bound;
* no renovable;
* monitorizada;
* revocable inmediatamente.

---

### 2692. Recovery incident response

Toda sospecha de abuso deberá poder abrir un incidente de seguridad.

---

### 2693. Recovery incident triggers

Ejemplos:

* múltiples recuperaciones;
* recovery contact recién cambiado;
* operator override;
* deepfake detectado;
* token replay;
* privileged account targeted;
* recovery disputed;
* impossible travel;
* support collusion.

---

### 2694. Recovery containment

La contención podrá incluir:

* cancelar recovery session;
* suspender identidad;
* revocar nuevos factores;
* invalidar nuevos credentials;
* bloquear soporte;
* congelar recovery settings;
* preservar evidencia.

---

### 2695. Recovery forensic package

```php
final readonly class RecoveryForensicPackage
{
    public function __construct(
        public string $packageId,
        public string $recoverySessionId,
        public array $evidenceReferences,
        public array $challengeResults,
        public array $operatorActions,
        public array $approvalRecords,
        public array $riskSignals,
        public string $digest,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
```

---

### 2696. Recovery governance

La gobernanza deberá definir:

* métodos aprobados;
* assurance levels;
* privileged recovery;
* support capabilities;
* contact policies;
* escrow policies;
* cooling periods;
* break-glass rules;
* retention;
* incident escalation.

---

### 2697. Recovery metrics

Métricas recomendadas:

* recovery success rate;
* recovery denial rate;
* fraudulent recovery attempts;
* mean recovery time;
* disputed recovery rate;
* support override rate;
* privileged recovery count;
* token replay detections;
* post-recovery compromise rate.

---

### 2698. Recovery compliance evidence

VoltStack deberá producir evidencia sobre:

* request;
* risk assessment;
* proofing;
* challenges;
* approvals;
* credential rotation;
* session revocation;
* restrictions;
* notifications;
* disputes;
* incident handling.

---

### 2699. Recovery audit events

Eventos recomendados:

* `IdentityRecoveryRequested`;
* `IdentityRecoverySessionStarted`;
* `RecoveryChallengeIssued`;
* `RecoveryChallengeCompleted`;
* `RecoveryTokenIssued`;
* `RecoveryTokenConsumed`;
* `RecoveryTokenReplayDetected`;
* `RecoveryContactUsed`;
* `RecoveryProofingCompleted`;
* `RecoveryApprovalRequested`;
* `RecoveryApproved`;
* `RecoveryRejected`;
* `ExistingSessionsRevokedForRecovery`;
* `AuthenticationFactorReplaced`;
* `PostRecoveryRestrictionActivated`;
* `RecoveryDisputed`;
* `RecoveryCancelled`;
* `PrivilegedRecoveryRequested`;
* `BreakGlassRecoveryActivated`;
* `RecoveryFraudDetected`;
* `RecoveryIncidentOpened`;
* `IdentityRecoveryCompleted`.

---

### 2700. Resultado de esta entrega

Esta entrega establece:

```text
Identity Recovery Architecture
Recovery Assurance Levels
Recovery Policy Engine
Enumeration Resistance
Recovery Session Isolation
Recovery Session Binding
Recovery Tokens
Recovery Secrets
Backup Codes
Recovery Contacts
Recovery Contact Cooling Periods
Recovery Devices
Recovery Escrow
Recovery Evidence Bundles
Recovery Proofing
Evidence Independence
Recovery Risk Engine
SIM Swap Risk
Email Compromise Risk
Social Engineering Resistance
Support Recovery Controls
Recovery Approval Workflows
Credential Reset Security
MFA Recovery
Temporary Recovery Factors
Post-Recovery Restricted Mode
Recovery Cooling Periods
Trusted-Channel Notifications
Recovery Disputes
Identity Resurrection Prevention
Privileged Account Recovery
Privilege Restoration Separation
Break-Glass Recovery
Recovery Incident Response
Recovery Forensic Preservation
Recovery Governance
Recovery Metrics
Recovery Compliance Evidence
Recovery Audit Events
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 28

- Account security operations
- Account lockout architecture
- Adaptive lockout
- Suspicious login handling
- Credential compromise workflows
- Session compromise workflows
- Device compromise workflows
- Forced logout
- Forced credential rotation
- Security holds
- Protective account restriction
- Account takeover detection
- Account takeover containment
- User security notifications
- Security action confirmations
- User-visible security history
- Account security center
- Security operations governance
- Account security metrics
- Account security audit events
```

## Entrega 28

**Documento:** Parte 05
**Entrega:** 28 de varias
**Cobertura:** Secciones **2701–2800**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 27`

---

### 2701. Account Security Operations Architecture

VoltStack deberá incorporar un subsistema especializado de **Account Security Operations**, responsable de coordinar acciones defensivas sobre cuentas activas cuando se detecten señales de riesgo, compromiso o abuso.

Este subsistema deberá operar sobre:

* autenticación;
* credenciales;
* sesiones;
* dispositivos;
* factores MFA;
* recovery methods;
* privilegios;
* tokens;
* API clients;
* delegaciones;
* tenant memberships.

---

### 2702. Account security operations objectives

La arquitectura deberá garantizar:

* contención rápida;
* mínima interrupción legítima;
* proporcionalidad;
* reversibilidad controlada;
* preservación de evidencia;
* aislamiento multi-tenant;
* comunicación segura;
* protección contra abuso administrativo;
* trazabilidad;
* automatización gobernada.

---

### 2703. Account security operations threat model

Deberán considerarse:

* account takeover;
* credential stuffing;
* password spraying;
* session theft;
* token replay;
* passkey compromise;
* MFA fatigue;
* SIM swapping;
* device theft;
* malicious administrator;
* insider abuse;
* automated lockout attacks;
* recovery takeover;
* privilege escalation;
* persistent access after containment.

---

### 2704. Account security operations pipeline

```text
Security Signal
      ↓
Account Resolution
      ↓
Context Enrichment
      ↓
Risk Correlation
      ↓
Policy Evaluation
      ↓
Containment Planning
      ↓
Protective Actions
      ↓
User and Operator Notification
      ↓
Verification
      ↓
Recovery or Closure
```

---

### 2705. AccountSecurityOperationsService

```php
interface AccountSecurityOperationsServiceInterface
{
    public function assess(
        AccountSecuritySignal $signal
    ): AccountSecurityAssessment;

    public function plan(
        AccountSecurityAssessment $assessment
    ): AccountSecurityActionPlan;

    public function execute(
        AccountSecurityActionPlan $plan
    ): AccountSecurityActionResult;
}
```

---

### 2706. AccountSecuritySignal

```php
final readonly class AccountSecuritySignal
{
    public function __construct(
        public string $signalId,
        public IdentityIdentifier $identityId,
        public string $tenantId,
        public AccountSecuritySignalType $type,
        public ThreatSeverity $severity,
        public ThreatConfidence $confidence,
        public array $evidence,
        public DateTimeImmutable $detectedAt,
    ) {
    }
}
```

---

### 2707. AccountSecuritySignalType

```php
enum AccountSecuritySignalType: string
{
    case FailedAuthenticationBurst = 'failed_authentication_burst';
    case CredentialCompromise = 'credential_compromise';
    case SessionCompromise = 'session_compromise';
    case DeviceCompromise = 'device_compromise';
    case AccountTakeover = 'account_takeover';
    case MfaAbuse = 'mfa_abuse';
    case RecoveryAbuse = 'recovery_abuse';
    case PrivilegeAnomaly = 'privilege_anomaly';
    case TokenReplay = 'token_replay';
    case ImpossibleTravel = 'impossible_travel';
    case AdministrativeRisk = 'administrative_risk';
}
```

---

### 2708. AccountSecurityAssessment

```php
final readonly class AccountSecurityAssessment
{
    public function __construct(
        public IdentityIdentifier $identityId,
        public float $riskScore,
        public ThreatSeverity $severity,
        public ThreatConfidence $confidence,
        public array $affectedAssets,
        public array $recommendedActions,
        public array $restrictions,
        public bool $humanReviewRequired,
    ) {
    }
}
```

---

### 2709. Account security policy engine

```php
interface AccountSecurityPolicyEngineInterface
{
    public function evaluate(
        AccountSecurityAssessment $assessment,
        AccountSecurityContext $context
    ): AccountSecurityPolicyDecision;
}
```

---

### 2710. AccountSecurityPolicyDecision

```php
final readonly class AccountSecurityPolicyDecision
{
    public function __construct(
        public bool $actionRequired,
        public array $mandatoryActions,
        public array $optionalActions,
        public array $prohibitedActions,
        public array $requiredApprovals,
        public array $notificationRules,
    ) {
    }
}
```

---

### 2711. Account lockout architecture

VoltStack deberá soportar bloqueo de cuenta sin depender exclusivamente de un contador fijo de intentos fallidos.

---

### 2712. Lockout security goals

El bloqueo deberá:

* reducir ataques automatizados;
* evitar denial of service intencional;
* considerar contexto;
* diferenciar credenciales;
* preservar recovery access;
* limitar impactos cross-tenant;
* mantener auditabilidad.

---

### 2713. AccountLockoutPolicy

```php
final readonly class AccountLockoutPolicy
{
    public function __construct(
        public int $failureThreshold,
        public DateInterval $observationWindow,
        public DateInterval $lockDuration,
        public bool $adaptive,
        public bool $tenantScoped,
        public array $riskModifiers,
        public array $exemptions,
    ) {
    }
}
```

---

### 2714. AccountLockoutState

```php
enum AccountLockoutState: string
{
    case None = 'none';
    case SoftLocked = 'soft_locked';
    case Challenged = 'challenged';
    case TemporarilyLocked = 'temporarily_locked';
    case AdministrativelyLocked = 'administratively_locked';
    case SecurityLocked = 'security_locked';
    case PermanentlyDisabled = 'permanently_disabled';
}
```

---

### 2715. Soft lockout

Un soft lockout podrá:

* incrementar latencia;
* requerir CAPTCHA;
* exigir MFA;
* bloquear un canal concreto;
* limitar intentos;
* cambiar a autenticación supervisada.

---

### 2716. Hard lockout

Un hard lockout deberá impedir autenticación hasta:

* expiración;
* intervención administrativa;
* recovery validado;
* eliminación de security hold;
* resolución de incidente.

---

### 2717. Lockout scope

El bloqueo podrá aplicarse a:

* identidad completa;
* tenant membership;
* credencial específica;
* factor;
* dispositivo;
* aplicación;
* red;
* región;
* canal de autenticación.

---

### 2718. Lockout keying strategy

El sistema no deberá utilizar exclusivamente el username como clave de rate limiting.

Deberá correlacionar:

* identidad;
* IP;
* network range;
* device;
* credential;
* tenant;
* application;
* geografía;
* behavioral fingerprint.

---

### 2719. Distributed lockout coordination

En despliegues distribuidos, los contadores y estados deberán ser consistentes entre nodos.

---

### 2720. Lockout atomicity

La evaluación, incremento y transición de lockout deberán ejecutarse de forma atómica.

---

### 2721. Adaptive lockout

El bloqueo adaptativo deberá ajustar controles según riesgo.

---

### 2722. AdaptiveLockoutEngine

```php
interface AdaptiveLockoutEngineInterface
{
    public function assess(
        AuthenticationAttemptSeries $attempts,
        AccountSecurityContext $context
    ): AdaptiveLockoutDecision;
}
```

---

### 2723. AdaptiveLockoutDecision

```php
final readonly class AdaptiveLockoutDecision
{
    public function __construct(
        public AccountLockoutState $targetState,
        public DateInterval $duration,
        public array $requiredChallenges,
        public array $blockedChannels,
        public array $reasonCodes,
    ) {
    }
}
```

---

### 2724. Adaptive lockout inputs

Deberán considerarse:

* password spray patterns;
* distributed sources;
* trusted device;
* successful recent login;
* user behavior;
* tenant criticality;
* privilege level;
* breached credential status;
* network reputation;
* recovery activity.

---

### 2725. Denial-of-service resistance

VoltStack deberá evitar que un atacante bloquee permanentemente una cuenta mediante intentos deliberados.

---

### 2726. Progressive friction

Antes del hard lockout podrán aplicarse:

* rate limiting;
* proof-of-work;
* challenge;
* MFA;
* email confirmation;
* trusted device validation;
* support escalation.

---

### 2727. Lockout notification

Las notificaciones deberán indicar actividad sospechosa sin revelar información útil al atacante.

---

### 2728. Lockout release

La liberación deberá verificar:

* reason;
* actor;
* recovery assurance;
* active incidents;
* current policy;
* compromised credentials;
* pending security holds.

---

### 2729. Suspicious login handling

Un login sospechoso no deberá tratarse siempre como exitoso o fallido.

Podrá quedar en estado:

* challenged;
* restricted;
* pending verification;
* quarantined;
* denied;
* observed.

---

### 2730. SuspiciousLoginAssessment

```php
final readonly class SuspiciousLoginAssessment
{
    public function __construct(
        public string $attemptId,
        public IdentityIdentifier $identityId,
        public float $riskScore,
        public array $signals,
        public SuspiciousLoginDisposition $disposition,
        public array $requiredActions,
    ) {
    }
}
```

---

### 2731. SuspiciousLoginDisposition

```php
enum SuspiciousLoginDisposition: string
{
    case Allow = 'allow';
    case AllowRestricted = 'allow_restricted';
    case StepUp = 'step_up';
    case Quarantine = 'quarantine';
    case Deny = 'deny';
    case Escalate = 'escalate';
}
```

---

### 2732. Login quarantine

Una sesión en cuarentena deberá:

* poseer scopes mínimos;
* no acceder a datos sensibles;
* no cambiar credenciales;
* no modificar recovery methods;
* no elevar privilegios;
* no crear tokens;
* expirar rápidamente.

---

### 2733. Credential compromise workflow

VoltStack deberá coordinar acciones específicas cuando una credencial sea sospechosa o confirmada como comprometida.

---

### 2734. CredentialCompromiseAssessment

```php
final readonly class CredentialCompromiseAssessment
{
    public function __construct(
        public string $credentialId,
        public IdentityIdentifier $identityId,
        public CredentialCompromiseState $state,
        public array $evidence,
        public array $affectedSessions,
        public array $dependentCredentials,
    ) {
    }
}
```

---

### 2735. CredentialCompromiseState

```php
enum CredentialCompromiseState: string
{
    case Suspected = 'suspected';
    case Likely = 'likely';
    case Confirmed = 'confirmed';
    case Contained = 'contained';
    case Remediated = 'remediated';
    case FalsePositive = 'false_positive';
}
```

---

### 2736. Credential compromise actions

Podrán incluir:

* revocar credential;
* bloquear autenticación;
* invalidar sessions;
* revocar refresh tokens;
* rotar dependent secrets;
* forzar recovery;
* notificar trusted channels;
* iniciar incident response.

---

### 2737. Breached password handling

Una contraseña detectada en corpus de credenciales comprometidas deberá:

* rechazarse durante creación;
* marcarse para rotación;
* elevar riesgo;
* impedir reutilización;
* activar notificación según policy.

---

### 2738. Password compromise privacy

La verificación contra servicios externos no deberá exponer el password completo ni identificadores innecesarios.

---

### 2739. Passkey compromise

Una passkey comprometida deberá revocarse por credential ID sin eliminar automáticamente otras passkeys independientes.

---

### 2740. Certificate compromise

La respuesta deberá coordinar:

* revocación;
* actualización de CRL u OCSP;
* rotación;
* dependencia;
* sesiones emitidas;
* workload impact.

---

### 2741. API key compromise

Una API key comprometida deberá:

* revocarse inmediatamente;
* identificar usos recientes;
* analizar scopes;
* detectar exfiltration;
* rotar secrets dependientes;
* conservar evidencia.

---

### 2742. Forced credential rotation

VoltStack deberá soportar rotación obligatoria iniciada por:

* usuario;
* administrador;
* policy;
* incidente;
* proveedor;
* expiración;
* detección automática.

---

### 2743. ForcedCredentialRotationPlan

```php
final readonly class ForcedCredentialRotationPlan
{
    public function __construct(
        public IdentityIdentifier $identityId,
        public array $credentialIds,
        public CredentialRotationUrgency $urgency,
        public bool $revokeBeforeReplacement,
        public DateTimeImmutable $deadline,
        public array $restrictionsUntilCompletion,
    ) {
    }
}
```

---

### 2744. CredentialRotationUrgency

```php
enum CredentialRotationUrgency: string
{
    case Routine = 'routine';
    case Elevated = 'elevated';
    case Immediate = 'immediate';
    case Emergency = 'emergency';
}
```

---

### 2745. Rotation deadline enforcement

Si una rotación no se completa antes del deadline, deberán activarse restricciones progresivas o bloqueo.

---

### 2746. Rotation grace period

Los periodos de gracia no deberán permitirse para credenciales confirmadas como comprometidas.

---

### 2747. Session compromise workflow

La arquitectura deberá distinguir compromiso de:

* sesión individual;
* familia de sesiones;
* browser;
* dispositivo;
* refresh token family;
* tenant session;
* privileged session.

---

### 2748. SessionCompromiseAssessment

```php
final readonly class SessionCompromiseAssessment
{
    public function __construct(
        public string $sessionId,
        public IdentityIdentifier $identityId,
        public SessionCompromiseScope $scope,
        public ThreatConfidence $confidence,
        public array $relatedSessions,
        public array $evidence,
    ) {
    }
}
```

---

### 2749. SessionCompromiseScope

```php
enum SessionCompromiseScope: string
{
    case SingleSession = 'single_session';
    case DeviceSessions = 'device_sessions';
    case ApplicationSessions = 'application_sessions';
    case TenantSessions = 'tenant_sessions';
    case AllSessions = 'all_sessions';
    case PrivilegedSessions = 'privileged_sessions';
}
```

---

### 2750. Session compromise actions

Podrán incluir:

* revoke session;
* revoke session family;
* rotate session secrets;
* invalidate refresh tokens;
* block device;
* require reauthentication;
* restrict account;
* preserve telemetry.

---

### 2751. Forced logout architecture

VoltStack deberá soportar logout remoto inmediato y verificable.

---

### 2752. ForcedLogoutRequest

```php
final readonly class ForcedLogoutRequest
{
    public function __construct(
        public IdentityIdentifier $identityId,
        public SessionCompromiseScope $scope,
        public IdentityIdentifier|string $requestedBy,
        public ForcedLogoutReason $reason,
        public DateTimeImmutable $requestedAt,
    ) {
    }
}
```

---

### 2753. ForcedLogoutReason

```php
enum ForcedLogoutReason: string
{
    case UserRequested = 'user_requested';
    case CredentialChanged = 'credential_changed';
    case SecurityIncident = 'security_incident';
    case DeviceLost = 'device_lost';
    case AdministratorAction = 'administrator_action';
    case PolicyChange = 'policy_change';
    case TenantTransfer = 'tenant_transfer';
}
```

---

### 2754. Logout propagation

La revocación deberá propagarse a:

* web sessions;
* mobile sessions;
* refresh tokens;
* websocket connections;
* SSE streams;
* background workers;
* delegated sessions;
* privileged consoles.

---

### 2755. Session revocation verification

El sistema deberá verificar que los nodos y aplicaciones relevantes hayan aplicado la revocación.

---

### 2756. Offline session handling

Los clientes offline deberán recibir revocation state al reconectarse antes de sincronizar datos sensibles.

---

### 2757. Device compromise workflow

Un dispositivo comprometido deberá afectar solo las relaciones y credenciales asociadas, salvo evidencia de mayor alcance.

---

### 2758. DeviceCompromiseAssessment

```php
final readonly class DeviceCompromiseAssessment
{
    public function __construct(
        public string $deviceId,
        public IdentityIdentifier $identityId,
        public DeviceCompromiseState $state,
        public array $credentials,
        public array $sessions,
        public array $signals,
        public array $recommendedActions,
    ) {
    }
}
```

---

### 2759. DeviceCompromiseState

```php
enum DeviceCompromiseState: string
{
    case Suspected = 'suspected';
    case Lost = 'lost';
    case Stolen = 'stolen';
    case MalwareDetected = 'malware_detected';
    case RootedOrJailbroken = 'rooted_or_jailbroken';
    case ConfirmedCompromised = 'confirmed_compromised';
    case Remediated = 'remediated';
}
```

---

### 2760. Device compromise actions

Podrán incluir:

* revoke device trust;
* revoke device-bound sessions;
* revoke passkeys;
* revoke certificates;
* remove push factor;
* block synchronization;
* wipe organizational material;
* force recovery.

---

### 2761. Device trust downgrade

El dispositivo podrá degradarse de trusted a restricted sin eliminarlo inmediatamente.

---

### 2762. Remote wipe boundary

VoltStack solo deberá solicitar borrado sobre datos y contenedores bajo control organizacional y policy aplicable.

---

### 2763. Security hold architecture

Un security hold deberá congelar operaciones de alto riesgo mientras se investiga una cuenta.

---

### 2764. AccountSecurityHold

```php
final readonly class AccountSecurityHold
{
    public function __construct(
        public string $holdId,
        public IdentityIdentifier $identityId,
        public SecurityHoldReason $reason,
        public array $blockedActions,
        public IdentityIdentifier|string $imposedBy,
        public DateTimeImmutable $imposedAt,
        public ?DateTimeImmutable $expiresAt,
        public SecurityHoldState $state,
    ) {
    }
}
```

---

### 2765. SecurityHoldReason

```php
enum SecurityHoldReason: string
{
    case AccountTakeoverInvestigation = 'account_takeover_investigation';
    case CredentialCompromise = 'credential_compromise';
    case RecoveryDispute = 'recovery_dispute';
    case FraudInvestigation = 'fraud_investigation';
    case LegalRequest = 'legal_request';
    case InsiderThreat = 'insider_threat';
    case PrivilegeAbuse = 'privilege_abuse';
}
```

---

### 2766. SecurityHoldState

```php
enum SecurityHoldState: string
{
    case Active = 'active';
    case PartiallyReleased = 'partially_released';
    case Released = 'released';
    case Expired = 'expired';
    case Superseded = 'superseded';
}
```

---

### 2767. Security hold blocked operations

Podrán bloquearse:

* credential changes;
* recovery changes;
* money movement;
* data export;
* tenant transfer;
* role elevation;
* API key creation;
* ownership transfer;
* account deletion.

---

### 2768. Security hold authorization

La imposición y liberación deberán requerir authority explícita y quedar auditadas.

---

### 2769. Protective account restriction

Una cuenta podrá continuar parcialmente operativa bajo restricciones protectoras.

---

### 2770. ProtectiveRestrictionProfile

```php
final readonly class ProtectiveRestrictionProfile
{
    public function __construct(
        public string $profileId,
        public array $allowedActions,
        public array $blockedActions,
        public array $stepUpRequiredActions,
        public DateTimeImmutable $effectiveAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 2771. Protective restriction use cases

Podrá utilizarse cuando:

* el riesgo sea alto pero no concluyente;
* el usuario necesite acceso básico;
* exista investigación;
* se complete recovery;
* se rote una credencial;
* se revalide un dispositivo.

---

### 2772. Account takeover detection

VoltStack deberá correlacionar señales técnicas, conductuales y lifecycle para detectar account takeover.

---

### 2773. AccountTakeoverDetector

```php
interface AccountTakeoverDetectorInterface
{
    public function assess(
        IdentityIdentifier $identityId,
        AccountActivityWindow $activity
    ): AccountTakeoverAssessment;
}
```

---

### 2774. AccountTakeoverAssessment

```php
final readonly class AccountTakeoverAssessment
{
    public function __construct(
        public AccountTakeoverState $state,
        public float $riskScore,
        public ThreatConfidence $confidence,
        public array $indicators,
        public array $affectedAssets,
        public array $recommendedActions,
    ) {
    }
}
```

---

### 2775. AccountTakeoverState

```php
enum AccountTakeoverState: string
{
    case NoEvidence = 'no_evidence';
    case Suspicious = 'suspicious';
    case Likely = 'likely';
    case Confirmed = 'confirmed';
    case Contained = 'contained';
    case Recovered = 'recovered';
}
```

---

### 2776. Account takeover indicators

Deberán considerarse:

* impossible travel;
* new device;
* MFA reset;
* recovery change;
* password change;
* privilege elevation;
* unusual data access;
* token creation;
* forwarding rules;
* session concurrency;
* user dispute;
* behavioral deviation.

---

### 2777. Account takeover correlation window

Las señales deberán analizarse como secuencias temporales y no como eventos aislados.

---

### 2778. High-confidence takeover pattern

Un patrón crítico podrá incluir:

```text
New Device
    +
Recovery Method Change
    +
MFA Removal
    +
Credential Rotation
    +
Data Export
```

---

### 2779. Account takeover containment

La contención deberá ejecutarse según blast radius y confianza.

---

### 2780. AccountTakeoverContainmentPlan

```php
final readonly class AccountTakeoverContainmentPlan
{
    public function __construct(
        public IdentityIdentifier $identityId,
        public array $sessionsToRevoke,
        public array $credentialsToRevoke,
        public array $devicesToBlock,
        public array $restrictions,
        public bool $securityHoldRequired,
        public bool $identitySuspensionRequired,
        public array $notifications,
    ) {
    }
}
```

---

### 2781. Containment ordering

El orden recomendado será:

```text
Freeze Sensitive Actions
      ↓
Revoke Attacker Sessions
      ↓
Revoke Compromised Credentials
      ↓
Lock Recovery Settings
      ↓
Block Compromised Devices
      ↓
Notify Trusted Channels
      ↓
Start Recovery
      ↓
Investigate Persistence
```

---

### 2782. Persistence search

Tras contener un takeover deberán revisarse:

* new API keys;
* new passkeys;
* new recovery contacts;
* forwarding rules;
* delegated access;
* created service accounts;
* changed webhooks;
* trusted devices;
* OAuth grants.

---

### 2783. Account restoration after takeover

La restauración deberá reconstruir confianza y acceso desde fuentes vigentes.

---

### 2784. Post-takeover recovery requirements

Podrán exigirse:

* re-proofing;
* credential re-enrollment;
* full session revocation;
* device review;
* access recertification;
* privilege reapproval;
* recovery method reset;
* enhanced monitoring.

---

### 2785. Security action confirmations

Las acciones sensibles iniciadas por el usuario deberán requerir confirmación explícita.

---

### 2786. SecurityActionConfirmation

```php
final readonly class SecurityActionConfirmation
{
    public function __construct(
        public string $confirmationId,
        public IdentityIdentifier $identityId,
        public SecurityActionType $action,
        public string $actionDigest,
        public AuthenticationAssuranceLevel $assurance,
        public DateTimeImmutable $confirmedAt,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 2787. SecurityActionType

```php
enum SecurityActionType: string
{
    case RevokeSession = 'revoke_session';
    case RevokeCredential = 'revoke_credential';
    case ChangePassword = 'change_password';
    case RemoveMfaFactor = 'remove_mfa_factor';
    case AddRecoveryContact = 'add_recovery_contact';
    case DisableAccount = 'disable_account';
    case ExportSecurityHistory = 'export_security_history';
}
```

---

### 2788. Confirmation binding

La confirmación deberá ligarse al digest exacto de la acción para impedir sustitución.

---

### 2789. User security notifications

VoltStack deberá notificar eventos relevantes mediante canales seguros.

---

### 2790. Security notification categories

Deberán incluir:

* new login;
* suspicious login;
* password change;
* MFA change;
* recovery attempt;
* new device;
* session revocation;
* account lockout;
* security hold;
* takeover containment.

---

### 2791. Notification channel independence

Para eventos críticos deberá preferirse un canal distinto al canal potencialmente comprometido.

---

### 2792. Notification privacy

Las notificaciones no deberán incluir:

* tokens;
* secrets;
* password hints;
* sensitive resource names;
* internal risk scores;
* unnecessary tenant data.

---

### 2793. User-visible security history

El usuario deberá poder consultar un historial comprensible de seguridad.

---

### 2794. UserSecurityHistoryEntry

```php
final readonly class UserSecurityHistoryEntry
{
    public function __construct(
        public string $entryId,
        public IdentityIdentifier $identityId,
        public UserSecurityEventType $type,
        public string $summary,
        public array $safeContext,
        public DateTimeImmutable $occurredAt,
        public bool $actionRequired,
    ) {
    }
}
```

---

### 2795. User security history safeguards

El historial no deberá exponer:

* IP completa cuando no sea necesario;
* device fingerprints internos;
* investigation notes;
* detection rules;
* identities de operadores;
* información de otros tenants.

---

### 2796. Account security center

VoltStack deberá ofrecer un dominio lógico de **Account Security Center** para:

* revisar sesiones;
* revisar dispositivos;
* administrar credenciales;
* revisar MFA;
* revisar recovery methods;
* consultar eventos;
* revocar acceso;
* reportar actividad;
* iniciar recovery;
* descargar evidencia permitida.

---

### 2797. AccountSecurityCenterService

```php
interface AccountSecurityCenterServiceInterface
{
    public function getOverview(
        IdentityIdentifier $identityId,
        AccountSecurityCenterContext $context
    ): AccountSecurityOverview;

    public function executeAction(
        AccountSecurityCenterAction $action
    ): AccountSecurityActionResult;
}
```

---

### 2798. Security operations governance and metrics

La gobernanza deberá definir:

* action owners;
* automation limits;
* lockout policies;
* containment thresholds;
* approval requirements;
* notification rules;
* retention;
* incident handoff;
* exception management.

Métricas recomendadas:

* account lockout rate;
* false lockout rate;
* suspicious login challenge rate;
* forced logout latency;
* credential compromise response time;
* session revocation propagation time;
* takeover detection rate;
* containment success rate;
* user dispute rate;
* residual persistence rate.

---

### 2799. Account security audit events

Eventos recomendados:

* `AccountSecuritySignalDetected`;
* `AccountSecurityAssessmentCompleted`;
* `AccountSoftLocked`;
* `AccountSecurityLocked`;
* `AccountLockoutReleased`;
* `SuspiciousLoginDetected`;
* `SuspiciousLoginQuarantined`;
* `CredentialCompromiseSuspected`;
* `CredentialCompromiseConfirmed`;
* `CredentialRevokedForSecurity`;
* `ForcedCredentialRotationRequested`;
* `SessionCompromiseDetected`;
* `ForcedLogoutRequested`;
* `ForcedLogoutCompleted`;
* `DeviceCompromiseDetected`;
* `DeviceTrustRevoked`;
* `AccountSecurityHoldImposed`;
* `AccountSecurityHoldReleased`;
* `ProtectiveRestrictionActivated`;
* `AccountTakeoverSuspected`;
* `AccountTakeoverConfirmed`;
* `AccountTakeoverContained`;
* `SecurityActionConfirmed`;
* `SecurityNotificationDispatched`;
* `UserSecurityHistoryViewed`;
* `AccountSecurityCenterActionExecuted`.

---

### 2800. Resultado de esta entrega

Esta entrega establece:

```text
Account Security Operations Architecture
Account Security Policy Engine
Account Lockout Architecture
Soft and Hard Lockout
Scoped Account Lockout
Distributed Lockout Coordination
Adaptive Lockout
Denial-of-Service Resistance
Progressive Authentication Friction
Suspicious Login Handling
Login Quarantine
Credential Compromise Workflows
Breached Password Handling
Passkey and Certificate Compromise
API Key Compromise
Forced Credential Rotation
Session Compromise Workflows
Forced Logout
Session Revocation Propagation
Offline Session Revocation
Device Compromise Workflows
Device Trust Downgrade
Security Holds
Protective Account Restrictions
Account Takeover Detection
Temporal Signal Correlation
Account Takeover Containment
Persistence Search
Post-Takeover Recovery
Security Action Confirmations
User Security Notifications
User-Visible Security History
Account Security Center
Security Operations Governance
Security Metrics
Account Security Audit Events
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 29

- Identity security observability
- Authentication telemetry
- Session telemetry
- Credential telemetry
- Identity lifecycle telemetry
- Recovery telemetry
- Account takeover telemetry
- Identity security metrics
- Security dashboards
- Tenant-aware observability
- Privacy-preserving telemetry
- Telemetry cardinality controls
- Detection engineering
- Identity behavior analytics
- Risk baselines
- Drift and anomaly detection
- SIEM integration
- SOAR integration
- Alert governance
- Identity security audit events
```

## Entrega 29

**Documento:** Parte 05
**Entrega:** 29 de varias
**Cobertura:** Secciones **2801–2900**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 28`

---

### 2801. Identity Security Observability Architecture

VoltStack deberá incorporar una arquitectura especializada de **Identity Security Observability**, responsable de recopilar, normalizar, correlacionar y analizar señales relacionadas con:

* autenticación;
* sesiones;
* credenciales;
* dispositivos;
* recovery;
* lifecycle;
* privilegios;
* federación;
* account takeover;
* operaciones administrativas;
* acciones de seguridad.

La observabilidad deberá permitir detectar riesgos sin exponer información sensible innecesaria.

---

### 2802. Identity security observability objectives

La arquitectura deberá garantizar:

* visibilidad de extremo a extremo;
* trazabilidad;
* detección temprana;
* aislamiento multi-tenant;
* integridad temporal;
* baja latencia;
* minimización de datos;
* resistencia a manipulación;
* correlación distribuida;
* soporte forense;
* explicabilidad.

---

### 2803. Observability threat model

Deberán considerarse amenazas como:

* eliminación de logs;
* log forging;
* telemetry poisoning;
* replay de eventos;
* pérdida de contexto;
* cardinality explosion;
* leakage cross-tenant;
* timestamps manipulados;
* alert fatigue;
* suppression maliciosa;
* bypass de detecciones;
* acceso excesivo a telemetría;
* exfiltración mediante atributos de observabilidad.

---

### 2804. Identity observability pipeline

```text
Identity Security Event
      ↓
Local Validation
      ↓
Normalization
      ↓
Context Enrichment
      ↓
Tenant Isolation
      ↓
Privacy Filtering
      ↓
Correlation
      ↓
Detection and Scoring
      ↓
Storage and Export
      ↓
Alerting and Response
```

---

### 2805. IdentitySecurityTelemetryService

```php
interface IdentitySecurityTelemetryServiceInterface
{
    public function record(
        IdentitySecurityTelemetryEvent $event
    ): void;

    public function query(
        IdentitySecurityTelemetryQuery $query
    ): IdentitySecurityTelemetryResult;

    public function correlate(
        IdentitySecurityCorrelationRequest $request
    ): IdentitySecurityCorrelationResult;
}
```

---

### 2806. IdentitySecurityTelemetryEvent

```php
final readonly class IdentitySecurityTelemetryEvent
{
    public function __construct(
        public string $eventId,
        public IdentitySecurityTelemetryType $type,
        public ?IdentityIdentifier $identityId,
        public string $tenantId,
        public array $attributes,
        public ThreatSeverity $severity,
        public DateTimeImmutable $occurredAt,
        public DateTimeImmutable $observedAt,
        public string $source,
        public string $schemaVersion,
    ) {
    }
}
```

---

### 2807. IdentitySecurityTelemetryType

```php
enum IdentitySecurityTelemetryType: string
{
    case Authentication = 'authentication';
    case Session = 'session';
    case Credential = 'credential';
    case Device = 'device';
    case Recovery = 'recovery';
    case Lifecycle = 'lifecycle';
    case Authorization = 'authorization';
    case PrivilegedAccess = 'privileged_access';
    case Federation = 'federation';
    case AccountTakeover = 'account_takeover';
    case AdministrativeAction = 'administrative_action';
    case SecurityResponse = 'security_response';
}
```

---

### 2808. Telemetry event invariants

Todo evento deberá incluir:

* event ID único;
* tenant;
* source;
* schema version;
* occurred time;
* observed time;
* integrity metadata;
* classification;
* retention profile;
* correlation identifiers cuando apliquen.

---

### 2809. Event provenance

La procedencia deberá identificar:

* componente emisor;
* node;
* application;
* environment;
* runtime;
* deployment;
* connector;
* authentication domain;
* collector version.

---

### 2810. Event integrity

Los eventos críticos deberán protegerse mediante:

* hashes;
* signatures;
* append-only storage;
* immutable sequences;
* remote replication;
* integrity checkpoints.

---

### 2811. Authentication telemetry

La telemetría de autenticación deberá capturar el ciclo completo de cada intento.

---

### 2812. AuthenticationTelemetryEvent

```php
final readonly class AuthenticationTelemetryEvent
{
    public function __construct(
        public string $attemptId,
        public ?IdentityIdentifier $identityId,
        public string $tenantId,
        public AuthenticationTelemetryOutcome $outcome,
        public array $methods,
        public AuthenticationAssuranceLevel $assurance,
        public array $riskSignals,
        public array $safeNetworkContext,
        public array $safeDeviceContext,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
```

---

### 2813. AuthenticationTelemetryOutcome

```php
enum AuthenticationTelemetryOutcome: string
{
    case Started = 'started';
    case Challenged = 'challenged';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Denied = 'denied';
    case Quarantined = 'quarantined';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';
}
```

---

### 2814. Authentication telemetry fields

Podrán registrarse:

* method;
* credential type;
* assurance achieved;
* failure category;
* challenge type;
* tenant;
* application;
* source reputation;
* device trust;
* session outcome;
* policy version;
* risk score band.

---

### 2815. Authentication privacy boundaries

No deberán registrarse:

* passwords;
* OTP completos;
* private keys;
* recovery secrets;
* raw biometric data;
* full authentication tokens;
* answers de security questions;
* secret challenge payloads.

---

### 2816. Failed authentication telemetry

Los fallos deberán diferenciar:

* unknown identity;
* invalid credential;
* expired credential;
* revoked credential;
* policy denial;
* tenant mismatch;
* locked account;
* failed MFA;
* risk denial;
* provider failure.

---

### 2817. Enumeration-safe telemetry

Aunque la respuesta pública sea uniforme, la telemetría interna podrá conservar una clasificación más precisa bajo controles de acceso estrictos.

---

### 2818. Authentication sequence correlation

Los eventos deberán correlacionarse por:

* attempt ID;
* session bootstrap ID;
* device ID;
* identity;
* tenant;
* application;
* network cluster;
* challenge chain.

---

### 2819. Password spray detection support

La telemetría deberá permitir identificar intentos distribuidos contra múltiples identidades con un conjunto reducido de passwords o patrones.

---

### 2820. Credential stuffing detection support

La arquitectura deberá correlacionar:

* credenciales filtradas;
* intentos automatizados;
* múltiples tenants;
* múltiples aplicaciones;
* successful takeover indicators;
* breached password signals.

---

### 2821. MFA telemetry

Deberán observarse:

* factor type;
* challenge issuance;
* approval;
* denial;
* timeout;
* repeated prompts;
* fatigue patterns;
* factor replacement;
* enrollment;
* removal.

---

### 2822. MFA fatigue analytics

El sistema deberá detectar:

* múltiples challenges;
* aprobaciones después de repetidos rechazos;
* prompts desde geografías distintas;
* aprobaciones anormalmente rápidas;
* operator-assisted bypass.

---

### 2823. Passwordless telemetry

Passkeys y autenticadores passwordless deberán registrar:

* credential ID pseudonimizado;
* authenticator class;
* user verification result;
* attestation state;
* device binding;
* counter anomalies;
* clone suspicion.

---

### 2824. Federated authentication telemetry

Los eventos federados deberán capturar:

* identity provider;
* issuer;
* audience;
* assertion age;
* assurance;
* federation policy;
* mapping result;
* signature validation;
* clock skew;
* replay indicators.

---

### 2825. Session telemetry

VoltStack deberá observar el ciclo completo de una sesión.

---

### 2826. SessionTelemetryEvent

```php
final readonly class SessionTelemetryEvent
{
    public function __construct(
        public string $sessionId,
        public IdentityIdentifier $identityId,
        public string $tenantId,
        public SessionTelemetryAction $action,
        public SessionTrustLevel $trustLevel,
        public array $safeContext,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
```

---

### 2827. SessionTelemetryAction

```php
enum SessionTelemetryAction: string
{
    case Created = 'created';
    case Refreshed = 'refreshed';
    case Rotated = 'rotated';
    case Elevated = 'elevated';
    case Restricted = 'restricted';
    case Revoked = 'revoked';
    case Expired = 'expired';
    case Migrated = 'migrated';
    case Quarantined = 'quarantined';
    case Terminated = 'terminated';
}
```

---

### 2828. Session correlation

Las sesiones deberán poder correlacionarse con:

* authentication attempt;
* credential;
* device;
* refresh token family;
* application;
* tenant;
* privilege elevation;
* recovery event;
* incident.

---

### 2829. Session anomaly indicators

Deberán observarse:

* concurrent geography;
* token replay;
* context changes;
* rapid privilege elevation;
* unusual lifetime;
* refresh anomalies;
* impossible client transitions;
* post-revocation use;
* session fixation indicators.

---

### 2830. Session revocation telemetry

La revocación deberá registrar:

* requested at;
* enforced at;
* scope;
* reason;
* actor;
* propagation status;
* failed consumers;
* residual activity.

---

### 2831. Revocation latency metric

VoltStack deberá medir el tiempo entre:

```text
Revocation Requested
        ↓
Revocation Persisted
        ↓
Revocation Propagated
        ↓
Last Unauthorized Use Prevented
```

---

### 2832. Credential telemetry

La telemetría deberá cubrir el ciclo de vida de cada credencial.

---

### 2833. CredentialTelemetryEvent

```php
final readonly class CredentialTelemetryEvent
{
    public function __construct(
        public string $credentialId,
        public IdentityIdentifier $identityId,
        public CredentialType $credentialType,
        public CredentialTelemetryAction $action,
        public CredentialSecurityState $securityState,
        public array $safeMetadata,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
```

---

### 2834. CredentialTelemetryAction

```php
enum CredentialTelemetryAction: string
{
    case Created = 'created';
    case Enrolled = 'enrolled';
    case Used = 'used';
    case Rotated = 'rotated';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
    case Expired = 'expired';
    case Compromised = 'compromised';
    case Recovered = 'recovered';
    case Deleted = 'deleted';
}
```

---

### 2835. Credential telemetry minimization

Solo deberá registrarse metadata segura como:

* credential type;
* creation time;
* last use;
* issuer;
* policy version;
* trust binding;
* revocation reason;
* assurance capability.

---

### 2836. Credential compromise analytics

El sistema deberá identificar:

* uso después de revocación;
* uso simultáneo imposible;
* anomalías de counter;
* inesperada región;
* uso fuera de purpose;
* credential sharing;
* excessive failures;
* dependent credential creation.

---

### 2837. Credential inventory observability

Las métricas deberán permitir conocer:

* credenciales activas;
* credenciales huérfanas;
* credenciales expiradas;
* credenciales sin uso;
* credenciales privilegiadas;
* credenciales sin owner;
* credenciales pendientes de rotación.

---

### 2838. Device telemetry

La observabilidad de dispositivos deberá cubrir:

* registration;
* trust assignment;
* trust degradation;
* credential binding;
* session binding;
* posture changes;
* compromise indicators;
* revocation.

---

### 2839. Device privacy controls

Los fingerprints deberán:

* minimizar atributos;
* evitar tracking cross-context;
* rotarse cuando sea posible;
* permanecer tenant-scoped;
* no utilizarse como identidad autónoma.

---

### 2840. Identity lifecycle telemetry

Cada transición lifecycle deberá emitir eventos correlacionables.

---

### 2841. LifecycleTelemetryEvent

```php
final readonly class LifecycleTelemetryEvent
{
    public function __construct(
        public string $executionId,
        public IdentityIdentifier $identityId,
        public IdentityLifecycleState $fromState,
        public IdentityLifecycleState $toState,
        public IdentityLifecycleAction $action,
        public LifecycleTelemetryOutcome $outcome,
        public array $safeContext,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
```

---

### 2842. LifecycleTelemetryOutcome

```php
enum LifecycleTelemetryOutcome: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Started = 'started';
    case Completed = 'completed';
    case PartiallyCompleted = 'partially_completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case RolledBack = 'rolled_back';
}
```

---

### 2843. Lifecycle observability goals

Deberá permitir detectar:

* stuck transitions;
* missed deprovisioning;
* unexpected reactivation;
* partial activation;
* orphan resources;
* stale approvals;
* overdue actions;
* state drift;
* cross-tenant anomalies.

---

### 2844. Joiner telemetry

El onboarding deberá medir:

* request-to-activation time;
* verification latency;
* provisioning completeness;
* excessive initial access;
* activation failures;
* missing MFA enrollment.

---

### 2845. Mover telemetry

Los cambios internos deberán observar:

* old access removal;
* new access assignment;
* manager transition;
* SoD conflicts;
* ownership transfer;
* stale delegations;
* tenant changes.

---

### 2846. Leaver telemetry

El offboarding deberá medir:

* disablement latency;
* session revocation latency;
* credential revocation completeness;
* downstream deprovisioning;
* residual access;
* orphan ownership;
* archive completion.

---

### 2847. Recovery telemetry

Las operaciones de recuperación deberán producir señales especializadas.

---

### 2848. RecoveryTelemetryEvent

```php
final readonly class RecoveryTelemetryEvent
{
    public function __construct(
        public string $recoverySessionId,
        public IdentityIdentifier $identityId,
        public RecoveryTelemetryAction $action,
        public RecoveryAssuranceLevel $assurance,
        public array $riskSignals,
        public array $safeContext,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
```

---

### 2849. RecoveryTelemetryAction

```php
enum RecoveryTelemetryAction: string
{
    case Requested = 'requested';
    case ChallengeIssued = 'challenge_issued';
    case ChallengePassed = 'challenge_passed';
    case ChallengeFailed = 'challenge_failed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Disputed = 'disputed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case FraudDetected = 'fraud_detected';
}
```

---

### 2850. Recovery anomaly indicators

Deberán observarse:

* contact recently changed;
* new device;
* failed proofing;
* repeated requests;
* support overrides;
* unusual recovery channel;
* privileged target;
* SIM swap signal;
* token replay;
* dispute after completion.

---

### 2851. Account takeover telemetry

El sistema deberá agregar señales de takeover provenientes de múltiples dominios.

---

### 2852. AccountTakeoverTelemetrySnapshot

```php
final readonly class AccountTakeoverTelemetrySnapshot
{
    public function __construct(
        public IdentityIdentifier $identityId,
        public array $authenticationSignals,
        public array $sessionSignals,
        public array $credentialSignals,
        public array $recoverySignals,
        public array $lifecycleSignals,
        public array $privilegeSignals,
        public DateTimeImmutable $windowStart,
        public DateTimeImmutable $windowEnd,
    ) {
    }
}
```

---

### 2853. Cross-domain correlation

Una detección de takeover deberá poder relacionar:

```text
Suspicious Authentication
        +
New Session
        +
Recovery Change
        +
New Credential
        +
Privilege Elevation
        +
Sensitive Action
```

---

### 2854. Identity security metrics architecture

VoltStack deberá distinguir entre:

* counters;
* gauges;
* histograms;
* distributions;
* rates;
* service-level indicators;
* security key risk indicators.

---

### 2855. IdentitySecurityMetric

```php
final readonly class IdentitySecurityMetric
{
    public function __construct(
        public string $name,
        public IdentitySecurityMetricType $type,
        public float|int $value,
        public array $dimensions,
        public DateTimeImmutable $observedAt,
    ) {
    }
}
```

---

### 2856. IdentitySecurityMetricType

```php
enum IdentitySecurityMetricType: string
{
    case Counter = 'counter';
    case Gauge = 'gauge';
    case Histogram = 'histogram';
    case Rate = 'rate';
    case Sli = 'sli';
    case Kri = 'kri';
}
```

---

### 2857. Core authentication metrics

Métricas recomendadas:

* authentication success rate;
* authentication failure rate;
* challenge rate;
* step-up rate;
* lockout rate;
* false lockout rate;
* MFA failure rate;
* passwordless adoption;
* federated failure rate;
* risk denial rate.

---

### 2858. Core session metrics

Deberán incluir:

* active sessions;
* privileged sessions;
* quarantined sessions;
* revocation latency;
* refresh failure rate;
* concurrent session anomalies;
* session lifetime distribution;
* post-revocation attempts.

---

### 2859. Core credential metrics

Deberán incluir:

* active credentials;
* stale credentials;
* compromised credentials;
* overdue rotations;
* credential reuse;
* orphan credentials;
* privileged credentials;
* average credential age.

---

### 2860. Core lifecycle metrics

Deberán incluir:

* joiner completion time;
* mover access drift;
* leaver deprovisioning latency;
* residual access rate;
* orphan ownership count;
* stuck transition count;
* rollback rate;
* manual remediation rate.

---

### 2861. Core recovery metrics

Deberán incluir:

* recovery success rate;
* recovery denial rate;
* average recovery duration;
* fraud detection rate;
* dispute rate;
* support override rate;
* privileged recovery rate;
* post-recovery incident rate.

---

### 2862. Core account takeover metrics

Deberán incluir:

* suspected takeovers;
* confirmed takeovers;
* detection latency;
* containment latency;
* recovery latency;
* persistent access findings;
* false positive rate;
* repeat compromise rate.

---

### 2863. Security dashboards

VoltStack deberá soportar dashboards diferenciados para:

* security operations;
* identity operations;
* tenant administrators;
* compliance;
* application owners;
* privileged access teams;
* executive risk reporting.

---

### 2864. Dashboard access control

La visualización deberá limitarse según:

* tenant;
* role;
* purpose;
* data classification;
* investigation assignment;
* legal authorization;
* need-to-know.

---

### 2865. SecurityOperationsDashboard

```php
final readonly class SecurityOperationsDashboard
{
    public function __construct(
        public array $activeIncidents,
        public array $highRiskIdentities,
        public array $authenticationAnomalies,
        public array $sessionCompromises,
        public array $recoveryRisks,
        public array $criticalMetrics,
        public DateTimeImmutable $generatedAt,
    ) {
    }
}
```

---

### 2866. Identity operations dashboard

Deberá mostrar:

* pending lifecycle actions;
* failed provisioning;
* residual access;
* stale accounts;
* overdue reviews;
* ownership gaps;
* recovery queues;
* connector health.

---

### 2867. Tenant security dashboard

Cada tenant solo deberá visualizar:

* sus identidades;
* sus métricas;
* sus incidentes;
* sus políticas;
* sus aplicaciones;
* sus conectores;
* sus riesgos agregados.

---

### 2868. Cross-tenant dashboard protection

Las vistas globales deberán requerir privilegios especiales y aplicar agregación o pseudonimización cuando sea posible.

---

### 2869. Privacy-preserving telemetry

La telemetría deberá aplicar:

* minimización;
* pseudonimización;
* tokenización;
* redaction;
* hashing con scope;
* aggregation;
* retention limits;
* purpose limitation.

---

### 2870. Sensitive telemetry classification

Deberán clasificarse especialmente:

* authentication factors;
* recovery data;
* network context;
* device data;
* biometrics;
* investigation notes;
* risk scores;
* privileged actions;
* identity proofing evidence.

---

### 2871. Telemetry access logging

Toda consulta sensible deberá registrar:

* requester;
* tenant;
* purpose;
* query scope;
* time range;
* exported fields;
* result count;
* approval reference.

---

### 2872. Telemetry retention

La retención deberá variar según:

* event type;
* severity;
* tenant policy;
* regulation;
* incident linkage;
* legal hold;
* forensic value;
* privacy risk.

---

### 2873. Telemetry deletion

La eliminación deberá considerar:

* retention expiry;
* legal hold;
* identity deletion;
* tenant termination;
* anonymization;
* cryptographic erasure;
* backup lifecycle.

---

### 2874. Telemetry cardinality controls

VoltStack deberá limitar dimensiones de alta cardinalidad para evitar:

* consumo excesivo;
* costos impredecibles;
* degradación;
* alert evasion;
* telemetry denial of service.

---

### 2875. Cardinality risk fields

Deberán manejarse cuidadosamente:

* identity ID;
* session ID;
* credential ID;
* device ID;
* IP;
* user agent;
* route;
* application ID;
* error details;
* arbitrary metadata.

---

### 2876. CardinalityControlPolicy

```php
final readonly class CardinalityControlPolicy
{
    public function __construct(
        public int $maximumSeriesPerTenant,
        public int $maximumDistinctValuesPerDimension,
        public array $allowedHighCardinalityFields,
        public array $aggregationRules,
        public array $dropRules,
    ) {
    }
}
```

---

### 2877. Cardinality overflow handling

Cuando se excedan límites, el sistema deberá:

* aggregate;
* sample;
* bucket;
* pseudonymize;
* drop low-value dimensions;
* preserve security-critical events;
* emit health alert.

---

### 2878. Telemetry sampling

El sampling no deberá eliminar eventos críticos como:

* confirmed compromise;
* privileged changes;
* recovery completion;
* credential revocation;
* tenant transfer;
* identity deletion;
* security hold;
* break-glass access.

---

### 2879. Detection engineering architecture

VoltStack deberá proporcionar una capa formal para desarrollar, versionar, probar y desplegar detecciones.

---

### 2880. IdentityDetectionRule

```php
final readonly class IdentityDetectionRule
{
    public function __construct(
        public string $ruleId,
        public string $name,
        public string $version,
        public array $requiredSignals,
        public array $conditions,
        public ThreatSeverity $severity,
        public array $responseRecommendations,
        public DetectionRuleState $state,
    ) {
    }
}
```

---

### 2881. DetectionRuleState

```php
enum DetectionRuleState: string
{
    case Draft = 'draft';
    case Testing = 'testing';
    case Shadow = 'shadow';
    case Active = 'active';
    case Disabled = 'disabled';
    case Deprecated = 'deprecated';
}
```

---

### 2882. Detection rule lifecycle

Las reglas deberán pasar por:

```text
Design
  ↓
Peer Review
  ↓
Historical Testing
  ↓
Shadow Mode
  ↓
Limited Deployment
  ↓
Production
  ↓
Continuous Tuning
  ↓
Retirement
```

---

### 2883. Detection test requirements

Toda regla deberá probarse contra:

* true positive scenarios;
* false positive scenarios;
* malformed data;
* missing context;
* delayed events;
* duplicate events;
* cross-tenant boundaries;
* adversarial inputs.

---

### 2884. Detection rule provenance

Cada alerta deberá indicar:

* rule ID;
* rule version;
* input events;
* score;
* confidence;
* threshold;
* suppression state;
* enrichment sources.

---

### 2885. Identity behavior analytics

VoltStack podrá construir perfiles de comportamiento para detectar desviaciones.

---

### 2886. IdentityBehaviorProfile

```php
final readonly class IdentityBehaviorProfile
{
    public function __construct(
        public IdentityIdentifier $identityId,
        public string $tenantId,
        public array $authenticationBaseline,
        public array $sessionBaseline,
        public array $deviceBaseline,
        public array $resourceBaseline,
        public array $privilegeBaseline,
        public DateTimeImmutable $generatedAt,
    ) {
    }
}
```

---

### 2887. Behavior baseline dimensions

Podrán incluirse:

* usual login times;
* common devices;
* typical applications;
* normal geographies;
* expected session duration;
* common authentication methods;
* resource access patterns;
* privilege usage;
* recovery frequency.

---

### 2888. Baseline safeguards

Los perfiles deberán:

* ser tenant-scoped;
* excluir atributos sensibles innecesarios;
* considerar estacionalidad;
* tolerar cambios legítimos;
* tener expiración;
* permitir recalibración;
* evitar decisiones exclusivamente automatizadas de alto impacto.

---

### 2889. Risk baseline adaptation

La adaptación deberá ser gradual para impedir que un atacante normalice actividad maliciosa rápidamente.

---

### 2890. Identity anomaly detection

```php
interface IdentitySecurityAnomalyDetectorInterface
{
    public function detect(
        IdentityBehaviorProfile $baseline,
        IdentityActivityWindow $activity
    ): IdentitySecurityAnomalyAssessment;
}
```

---

### 2891. IdentitySecurityAnomalyAssessment

```php
final readonly class IdentitySecurityAnomalyAssessment
{
    public function __construct(
        public IdentityIdentifier $identityId,
        public IdentitySecurityAnomalyType $type,
        public float $deviationScore,
        public ThreatConfidence $confidence,
        public array $signals,
        public array $recommendedActions,
    ) {
    }
}
```

---

### 2892. IdentitySecurityAnomalyType

```php
enum IdentitySecurityAnomalyType: string
{
    case Authentication = 'authentication';
    case Session = 'session';
    case Credential = 'credential';
    case Device = 'device';
    case Recovery = 'recovery';
    case Lifecycle = 'lifecycle';
    case Privilege = 'privilege';
    case Tenant = 'tenant';
    case Behavioral = 'behavioral';
}
```

---

### 2893. Identity security drift detection

Además del drift lifecycle, VoltStack deberá detectar:

* policy drift;
* assurance drift;
* credential drift;
* session drift;
* device trust drift;
* role drift;
* tenant binding drift;
* recovery configuration drift.

---

### 2894. SIEM integration

VoltStack deberá exportar eventos hacia plataformas SIEM mediante formatos canónicos y contratos versionados.

---

### 2895. IdentitySecurityEventExporter

```php
interface IdentitySecurityEventExporterInterface
{
    public function export(
        array $events,
        IdentitySecurityExportContext $context
    ): IdentitySecurityExportResult;
}
```

---

### 2896. SIEM export requirements

La integración deberá soportar:

* delivery acknowledgement;
* retry;
* batching;
* ordering cuando sea necesario;
* schema versioning;
* tenant metadata;
* event signing;
* dead-letter handling;
* backpressure;
* privacy filtering.

---

### 2897. SOAR integration

VoltStack deberá exponer acciones gobernadas para automatización de respuesta.

Acciones posibles:

* revoke session;
* revoke credential;
* restrict identity;
* impose security hold;
* block device;
* initiate recovery;
* suspend privileged access;
* open incident;
* notify trusted channel.

---

### 2898. SOAR safety controls

Toda acción automatizada deberá respetar:

* authorization;
* tenant scope;
* severity thresholds;
* confidence thresholds;
* approval requirements;
* rate limits;
* idempotency;
* rollback or compensation;
* human-in-the-loop.

---

### 2899. Alert governance and audit events

La gobernanza de alertas deberá definir:

* ownership;
* severity model;
* confidence model;
* routing;
* escalation;
* suppression;
* deduplication;
* acknowledgement;
* closure criteria;
* retention;
* quality review.

Eventos recomendados:

* `IdentityTelemetryRecorded`;
* `AuthenticationAnomalyDetected`;
* `SessionAnomalyDetected`;
* `CredentialAnomalyDetected`;
* `RecoveryAnomalyDetected`;
* `LifecycleDriftObserved`;
* `IdentityBehaviorBaselineCreated`;
* `IdentityBehaviorDeviationDetected`;
* `DetectionRuleActivated`;
* `DetectionRuleSuppressed`;
* `IdentitySecurityAlertCreated`;
* `IdentitySecurityAlertAcknowledged`;
* `IdentitySecurityAlertEscalated`;
* `IdentitySecurityAlertClosed`;
* `IdentitySecurityEventExported`;
* `IdentitySecurityExportFailed`;
* `IdentitySoarActionRequested`;
* `IdentitySoarActionExecuted`;
* `TelemetryCardinalityLimitExceeded`;
* `SensitiveTelemetryAccessed`.

---

### 2900. Resultado de esta entrega

Esta entrega establece:

```text
Identity Security Observability Architecture
Identity Security Telemetry Service
Telemetry Event Integrity
Authentication Telemetry
MFA and Passwordless Telemetry
Federated Authentication Telemetry
Session Telemetry
Session Revocation Metrics
Credential Telemetry
Credential Inventory Observability
Device Telemetry
Identity Lifecycle Telemetry
Joiner-Mover-Leaver Metrics
Recovery Telemetry
Account Takeover Telemetry
Cross-Domain Security Correlation
Identity Security Metrics
Security Dashboards
Tenant-Aware Observability
Privacy-Preserving Telemetry
Telemetry Access Auditing
Telemetry Retention and Deletion
Cardinality Controls
Security-Critical Sampling Rules
Detection Engineering
Detection Rule Lifecycle
Identity Behavior Analytics
Risk Baselines
Identity Anomaly Detection
Identity Security Drift Detection
SIEM Integration
SOAR Integration
Alert Governance
Identity Security Audit Events
```

La siguiente entrega continuará con:

```text
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 30

- Identity security testing architecture
- Authentication security testing
- Session security testing
- Credential security testing
- Recovery security testing
- Identity lifecycle testing
- Federation security testing
- Multi-tenant identity testing
- Abuse-case testing
- Adversarial testing
- Fuzzing
- Property-based testing
- Mutation testing
- Chaos engineering
- Failure injection
- Security regression suites
- Red team scenarios
- Purple team validation
- Compliance control testing
- Identity security testing governance
```

## Entrega 30

**Documento:** Parte 05
**Entrega:** 30 de varias
**Cobertura:** Secciones **2901–3000**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 29`

---

### 2901. Identity Security Testing Architecture

VoltStack deberá incorporar una arquitectura formal de **Identity Security Testing**, responsable de verificar continuamente la seguridad de:

* autenticación;
* sesiones;
* credenciales;
* MFA;
* passkeys;
* recovery;
* lifecycle;
* federación;
* dispositivos;
* tenants;
* privilegios;
* observabilidad;
* respuesta ante incidentes.

Las pruebas deberán considerarse parte del diseño del sistema y no una actividad posterior al desarrollo.

---

### 2902. Identity security testing objectives

La arquitectura deberá garantizar:

* verificación de invariantes;
* detección temprana de regresiones;
* resistencia a abuso;
* aislamiento multi-tenant;
* reproducibilidad;
* cobertura de escenarios adversariales;
* trazabilidad;
* automatización;
* evidencia de cumplimiento;
* validación de degradación segura.

---

### 2903. Identity security testing threat model

Las pruebas deberán contemplar:

* credential stuffing;
* password spraying;
* session fixation;
* session hijacking;
* MFA fatigue;
* token replay;
* recovery takeover;
* privilege escalation;
* identity confusion;
* tenant escape;
* federation replay;
* forged assertions;
* race conditions;
* downgrade attacks;
* stale policy usage;
* lifecycle drift;
* log tampering;
* alert suppression;
* fail-open behavior.

---

### 2904. Testing domains

El subsistema deberá dividir las pruebas en:

```text id="hjgqaa"
Authentication Testing
Session Testing
Credential Testing
Recovery Testing
Lifecycle Testing
Federation Testing
Multi-Tenant Testing
Abuse-Case Testing
Adversarial Testing
Resilience Testing
Compliance Testing
Regression Testing
```

---

### 2905. IdentitySecurityTestSuite

```php id="l36l4z"
interface IdentitySecurityTestSuiteInterface
{
    public function execute(
        IdentitySecurityTestPlan $plan
    ): IdentitySecurityTestReport;

    public function verifyInvariant(
        IdentitySecurityInvariant $invariant,
        IdentitySecurityTestContext $context
    ): IdentitySecurityInvariantResult;
}
```

---

### 2906. IdentitySecurityTestPlan

```php id="l5rn5x"
final readonly class IdentitySecurityTestPlan
{
    public function __construct(
        public string $planId,
        public array $testCases,
        public array $invariants,
        public array $environments,
        public array $requiredFixtures,
        public IdentitySecurityTestRiskLevel $riskLevel,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
```

---

### 2907. IdentitySecurityTestRiskLevel

```php id="g2w8wc"
enum IdentitySecurityTestRiskLevel: string
{
    case Low = 'low';
    case Standard = 'standard';
    case Elevated = 'elevated';
    case Destructive = 'destructive';
    case ProductionSafe = 'production_safe';
}
```

---

### 2908. Test environment separation

Las pruebas destructivas no deberán ejecutarse en producción salvo que hayan sido diseñadas explícitamente como:

* production-safe;
* non-destructive;
* tenant-isolated;
* rate-limited;
* observable;
* reversible.

---

### 2909. Security test fixtures

Las fixtures deberán incluir:

* identidades sintéticas;
* credenciales sintéticas;
* tenants aislados;
* sesiones temporales;
* dispositivos de prueba;
* proveedores federados simulados;
* recovery contacts sintéticos;
* permisos controlados;
* registros lifecycle temporales.

---

### 2910. Test data safety

Nunca deberán utilizarse credenciales reales, secretos de producción o PII innecesaria en entornos de prueba.

---

### 2911. Identity security invariant model

Una prueba de seguridad deberá poder validar invariantes arquitectónicos.

---

### 2912. IdentitySecurityInvariant

```php id="d5b75h"
final readonly class IdentitySecurityInvariant
{
    public function __construct(
        public string $invariantId,
        public string $description,
        public IdentitySecurityInvariantType $type,
        public array $preconditions,
        public array $expectedProperties,
    ) {
    }
}
```

---

### 2913. IdentitySecurityInvariantType

```php id="4qqt7c"
enum IdentitySecurityInvariantType: string
{
    case Authentication = 'authentication';
    case Session = 'session';
    case Credential = 'credential';
    case Recovery = 'recovery';
    case Lifecycle = 'lifecycle';
    case Federation = 'federation';
    case TenantIsolation = 'tenant_isolation';
    case Authorization = 'authorization';
    case Audit = 'audit';
    case Resilience = 'resilience';
}
```

---

### 2914. Core security invariants

VoltStack deberá verificar al menos:

* una identidad deshabilitada no puede autenticarse;
* una sesión revocada no puede reutilizarse;
* una credencial revocada no puede emitir nuevas sesiones;
* una recuperación no restaura privilegios automáticamente;
* un tenant no puede acceder a otro;
* un factor MFA no puede aprobarse dos veces;
* un token one-time no puede reutilizarse;
* un evento crítico siempre genera audit trail;
* una política denegatoria no puede degradarse a allow por error técnico;
* un fallo de dependencia crítica debe resultar en fail-secure.

---

### 2915. Authentication security testing

Las pruebas de autenticación deberán verificar:

* identificación;
* credential validation;
* policy evaluation;
* risk evaluation;
* MFA;
* passkeys;
* federation;
* lockout;
* challenge escalation;
* failure handling.

---

### 2916. AuthenticationTestCase

```php id="6ypzo7"
final readonly class AuthenticationSecurityTestCase
{
    public function __construct(
        public string $testId,
        public AuthenticationScenario $scenario,
        public array $inputs,
        public AuthenticationTelemetryOutcome $expectedOutcome,
        public array $expectedSecurityEffects,
    ) {
    }
}
```

---

### 2917. Authentication success tests

Deberán comprobar:

* credencial válida;
* identidad activa;
* tenant válido;
* policy permitida;
* assurance suficiente;
* session creation segura;
* audit event;
* telemetry emission.

---

### 2918. Authentication failure tests

Deberán comprobar:

* credencial inválida;
* identidad inexistente;
* identity disabled;
* tenant mismatch;
* expired credential;
* revoked credential;
* locked account;
* MFA failure;
* policy denial;
* provider failure.

---

### 2919. Enumeration resistance testing

Las respuestas deberán compararse respecto a:

* status code;
* body shape;
* response size;
* timing;
* headers;
* retry behavior;
* error language.

No deberán permitir inferir la existencia de una identidad.

---

### 2920. Timing side-channel testing

VoltStack deberá medir variaciones significativas entre:

* identity exists;
* identity absent;
* wrong password;
* locked account;
* disabled account;
* tenant mismatch.

---

### 2921. Password policy testing

Las pruebas deberán validar:

* longitud;
* entropy;
* breached password rejection;
* history;
* reuse prevention;
* normalization;
* Unicode handling;
* maximum length safety;
* hashing cost;
* migration de algoritmos.

---

### 2922. Password hashing tests

Deberán verificar:

* salt único;
* cost configurado;
* algoritmo permitido;
* rehash al autenticar;
* resistencia a truncation;
* ausencia de plaintext;
* ausencia de logs sensibles.

---

### 2923. Credential stuffing simulation

El sistema deberá probar ataques con:

* múltiples identidades;
* múltiples IP;
* credenciales filtradas;
* bajo volumen distribuido;
* rotación de user agents;
* intentos cross-tenant.

---

### 2924. Password spraying simulation

Deberán probarse patrones de:

* una contraseña contra muchas identidades;
* pocas contraseñas;
* intervalos lentos;
* múltiples aplicaciones;
* múltiples tenants;
* fuentes distribuidas.

---

### 2925. Adaptive lockout testing

Las pruebas deberán comprobar que:

* el atacante no pueda bloquear permanentemente a un usuario;
* el riesgo incremente fricción;
* los trusted devices no eliminen controles;
* los privileged accounts reciban protección reforzada;
* los contadores sean consistentes entre nodos.

---

### 2926. MFA security testing

Las pruebas deberán cubrir:

* enrollment;
* challenge;
* verification;
* replay;
* timeout;
* removal;
* replacement;
* recovery;
* factor downgrade;
* fatigue.

---

### 2927. OTP testing

Deberá verificarse:

* expiración;
* uso único;
* clock drift;
* rate limiting;
* retry threshold;
* secret rotation;
* no disclosure;
* rejection after successful use.

---

### 2928. Push MFA fatigue testing

Deberán simularse:

* repeated prompts;
* prompt flooding;
* delayed approval;
* approval after denials;
* attacker-controlled metadata;
* ambiguous prompts.

---

### 2929. Passkey testing

Deberán cubrirse:

* registration;
* assertion validation;
* origin binding;
* RP ID validation;
* challenge uniqueness;
* user verification;
* credential cloning indicators;
* counter anomalies;
* revoked credential use.

---

### 2930. WebAuthn downgrade testing

El sistema deberá impedir degradar silenciosamente desde un factor resistente a phishing hacia uno de menor assurance.

---

### 2931. Federation authentication testing

Las pruebas deberán verificar:

* issuer;
* audience;
* signature;
* assertion expiration;
* nonce;
* state;
* relay state;
* clock skew;
* replay;
* subject mapping;
* tenant mapping.

---

### 2932. Forged assertion testing

Deberán rechazarse:

* unsigned assertions;
* invalid signatures;
* wrong issuer;
* wrong audience;
* modified claims;
* expired assertions;
* future-dated assertions;
* reused assertions.

---

### 2933. Authentication failover testing

Si un proveedor externo falla, VoltStack deberá verificar que el sistema no:

* omita MFA;
* ignore policy;
* acepte assertions no verificadas;
* utilice cache stale insegura;
* permita bypass administrativo.

---

### 2934. Session security testing

Las pruebas deberán cubrir todo el ciclo de vida de sesiones.

---

### 2935. SessionSecurityTestCase

```php id="bsi0lu"
final readonly class SessionSecurityTestCase
{
    public function __construct(
        public string $testId,
        public SessionTelemetryAction $action,
        public array $preconditions,
        public array $operations,
        public array $expectedSessionProperties,
    ) {
    }
}
```

---

### 2936. Session creation tests

Deberán comprobar:

* session ID aleatorio;
* rotation after login;
* secure cookie;
* HttpOnly;
* SameSite;
* domain scope;
* path scope;
* lifetime;
* tenant binding;
* authentication context binding.

---

### 2937. Session fixation testing

Las pruebas deberán garantizar que un session ID previo a autenticación no conserve validez después del login.

---

### 2938. Session hijacking simulation

Deberán probarse:

* stolen cookie;
* stolen refresh token;
* changed device;
* changed geography;
* token replay;
* concurrent use;
* post-revocation usage.

---

### 2939. Session rotation tests

La sesión deberá rotarse tras:

* login;
* privilege elevation;
* MFA completion;
* recovery;
* tenant switch;
* sensitive profile change;
* administrator impersonation start.

---

### 2940. Session revocation tests

Deberán validar revocación de:

* single session;
* device sessions;
* application sessions;
* tenant sessions;
* privileged sessions;
* all sessions;
* refresh token family.

---

### 2941. Revocation propagation testing

La prueba deberá medir:

* persist latency;
* cache invalidation;
* node propagation;
* websocket closure;
* background consumer enforcement;
* offline client handling.

---

### 2942. Session expiry testing

Deberán comprobarse:

* idle timeout;
* absolute timeout;
* privilege timeout;
* recovery session timeout;
* quarantine timeout;
* token family expiration.

---

### 2943. Session concurrency testing

El sistema deberá comportarse correctamente ante:

* múltiples logins;
* concurrent refresh;
* simultaneous logout;
* revocation during request;
* tenant switch during refresh;
* privilege downgrade during active session.

---

### 2944. Credential security testing

Las credenciales deberán probarse según su tipo y ciclo de vida.

---

### 2945. Credential lifecycle test matrix

```text id="wy7jbw"
Create
Enroll
Use
Rotate
Suspend
Revoke
Expire
Compromise
Recover
Delete
```

---

### 2946. Credential uniqueness tests

Cuando aplique, el sistema deberá impedir:

* duplicate credential IDs;
* passkey binding duplicated incorrectly;
* API key collisions;
* certificate serial collisions;
* recovery code reuse;
* shared ownership no autorizado.

---

### 2947. Credential revocation testing

Una credencial revocada no deberá:

* autenticar;
* refresh sessions;
* sign requests;
* authorize recovery;
* enroll new credentials;
* restore itself.

---

### 2948. Credential dependency testing

La revocación de una credencial deberá evaluar correctamente:

* sessions;
* refresh tokens;
* derived credentials;
* linked devices;
* delegated access;
* issued API tokens.

---

### 2949. API key security testing

Las pruebas deberán verificar:

* entropy;
* prefix safety;
* secret hashing;
* scope enforcement;
* tenant binding;
* rotation;
* revocation;
* last-used tracking;
* leakage prevention.

---

### 2950. Certificate security testing

Deberán comprobarse:

* validity period;
* chain validation;
* key usage;
* revocation;
* issuer trust;
* rotation;
* expired certificate rejection;
* compromised issuer handling.

---

### 2951. Recovery security testing

La recuperación deberá someterse a pruebas adversariales independientes.

---

### 2952. RecoverySecurityTestCase

```php id="pbzpuu"
final readonly class RecoverySecurityTestCase
{
    public function __construct(
        public string $testId,
        public RecoveryReason $reason,
        public RecoveryAssuranceLevel $requiredAssurance,
        public array $evidence,
        public array $attackSignals,
        public bool $expectedApproval,
    ) {
    }
}
```

---

### 2953. Recovery token testing

Deberán verificarse:

* randomness;
* expiration;
* single use;
* purpose binding;
* identity binding;
* tenant binding;
* atomic consumption;
* replay rejection;
* revocation.

---

### 2954. Recovery enumeration testing

El sistema no deberá revelar:

* account existence;
* recovery channel;
* MFA type;
* privileged status;
* recovery contact;
* tenant membership.

---

### 2955. Recovery contact testing

Deberán validarse:

* verified contact;
* cooling period;
* conflict of interest;
* self-approval prevention;
* expired contact;
* compromised contact;
* contact removal.

---

### 2956. Recovery channel independence testing

Dos pruebas dependientes del mismo canal comprometido no deberán contabilizarse como assurance independiente.

---

### 2957. SIM swap scenario testing

Se deberán simular:

* recent SIM change;
* recent port;
* recycled number;
* carrier mismatch;
* phone unavailable;
* attacker-controlled SMS.

---

### 2958. Support-assisted recovery testing

Deberán verificarse controles contra:

* operator bypass;
* social engineering;
* forged evidence;
* self-approval;
* incomplete audit;
* excessive operator privileges;
* out-of-band policy exceptions.

---

### 2959. Privileged recovery testing

Las cuentas privilegiadas deberán exigir:

* mayor assurance;
* approvals;
* privilege suspension;
* restricted mode;
* reapproval;
* enhanced monitoring.

---

### 2960. Break-glass recovery testing

Deberán comprobarse:

* incident reference;
* dual control;
* short expiration;
* limited capabilities;
* automatic revocation;
* after-action review;
* no renewal;
* complete audit.

---

### 2961. Identity lifecycle testing

Los workflows lifecycle deberán probarse como máquinas de estado.

---

### 2962. Lifecycle state transition tests

Deberán validarse:

* allowed transitions;
* prohibited transitions;
* scheduled transitions;
* approval requirements;
* rollback;
* cancellation;
* partial execution;
* retries;
* reconciliation.

---

### 2963. Joiner testing

El onboarding deberá probar:

* proofing;
* duplicate detection;
* tenant assignment;
* default access;
* MFA enrollment;
* activation;
* incomplete provisioning;
* rollback.

---

### 2964. Mover testing

Los cambios deberán probar:

* old access removal;
* new access assignment;
* SoD enforcement;
* manager changes;
* ownership transfer;
* delegation transfer;
* scheduled effective date;
* rollback.

---

### 2965. Leaver testing

El offboarding deberá validar:

* immediate disablement;
* session revocation;
* credential revocation;
* downstream deprovisioning;
* ownership reassignment;
* archive;
* residual access detection;
* legal hold preservation.

---

### 2966. Identity resurrection testing

Una recuperación, importación o reconciliación no deberá reactivar identidades eliminadas o bloqueadas irreversiblemente.

---

### 2967. Lifecycle race-condition testing

Deberán probarse:

* disable during login;
* delete during recovery;
* tenant transfer during session refresh;
* role removal during authorization;
* reactivation during offboarding;
* concurrent ownership transfer.

---

### 2968. Federation security testing

Las relaciones federadas deberán probarse end-to-end.

---

### 2969. Federation trust tests

Deberán validarse:

* metadata source;
* signing keys;
* key rotation;
* issuer trust;
* audience restrictions;
* tenant mapping;
* assurance mapping;
* logout propagation.

---

### 2970. Federation key rollover testing

Las pruebas deberán contemplar:

* old and new key overlap;
* expired key rejection;
* emergency rollover;
* stale metadata;
* compromised key;
* cache invalidation.

---

### 2971. Identity mapping tests

El sistema deberá impedir:

* subject collision;
* email-only unsafe matching;
* cross-tenant subject reuse;
* identifier normalization mismatch;
* duplicate canonical identity;
* privilege injection through claims.

---

### 2972. Federation logout testing

Deberán probarse:

* local logout;
* upstream logout;
* downstream logout;
* partial provider outage;
* replayed logout message;
* incorrect session mapping.

---

### 2973. Multi-tenant identity testing

El aislamiento tenant deberá probarse en cada subsistema.

---

### 2974. TenantIsolationTestCase

```php id="z3tdsq"
final readonly class TenantIsolationTestCase
{
    public function __construct(
        public string $testId,
        public string $sourceTenantId,
        public string $targetTenantId,
        public array $attemptedOperations,
        public array $expectedDenials,
    ) {
    }
}
```

---

### 2975. Tenant isolation test coverage

Deberán probarse:

* identity lookup;
* authentication;
* session usage;
* recovery;
* credential listing;
* device listing;
* telemetry;
* security history;
* lifecycle operations;
* administration.

---

### 2976. Cross-tenant identifier collision tests

Dos tenants podrán tener identificadores humanos iguales sin producir identity confusion.

---

### 2977. Tenant context tampering tests

Deberán probarse:

* manipulated headers;
* modified route parameters;
* forged token claims;
* stale tenant session;
* tenant switch without step-up;
* cross-tenant cache poisoning.

---

### 2978. Tenant data export testing

Un export deberá incluir únicamente datos autorizados del tenant correspondiente.

---

### 2979. Abuse-case testing architecture

VoltStack deberá mantener un catálogo explícito de abuse cases.

---

### 2980. IdentitySecurityAbuseCase

```php id="0v6r3m"
final readonly class IdentitySecurityAbuseCase
{
    public function __construct(
        public string $abuseCaseId,
        public string $actor,
        public string $goal,
        public array $preconditions,
        public array $attackSteps,
        public array $expectedControls,
        public array $expectedEvidence,
    ) {
    }
}
```

---

### 2981. Core abuse cases

El catálogo deberá incluir:

* attacker locks victim account;
* attacker takes over recovery;
* support agent bypasses MFA;
* user reuses revoked session;
* tenant admin accesses another tenant;
* federated claim grants excessive privileges;
* attacker normalizes malicious behavior;
* operator suppresses security alert;
* compromised device enrolls new factor;
* deleted identity is resurrected.

---

### 2982. Adversarial testing

Las pruebas adversariales deberán asumir que el atacante:

* conoce el diseño público;
* controla clientes;
* modifica requests;
* repite tokens;
* manipula tiempos;
* induce fallos;
* intenta carreras;
* combina flujos legítimos;
* explota diferencias de error.

---

### 2983. Fuzzing architecture

VoltStack deberá aplicar fuzzing a parsers, protocolos y entradas de identidad.

---

### 2984. IdentitySecurityFuzzer

```php id="u5sbkg"
interface IdentitySecurityFuzzerInterface
{
    public function fuzz(
        IdentitySecurityFuzzTarget $target,
        IdentitySecurityFuzzConfiguration $configuration
    ): IdentitySecurityFuzzReport;
}
```

---

### 2985. Fuzz targets

Deberán incluirse:

* login payloads;
* JWT;
* SAML;
* WebAuthn;
* cookies;
* session headers;
* recovery tokens;
* identity claims;
* tenant identifiers;
* redirect URIs;
* metadata documents;
* audit event payloads.

---

### 2986. Fuzzing properties

El fuzzing deberá verificar:

* no crash;
* no secret leakage;
* no authentication bypass;
* no tenant escape;
* bounded memory;
* bounded CPU;
* deterministic rejection;
* safe error handling.

---

### 2987. Property-based testing

VoltStack deberá utilizar property-based testing para validar invariantes con amplios espacios de entrada.

---

### 2988. Property-based security properties

Ejemplos:

* todo token usado queda inválido;
* toda sesión revocada rechaza requests posteriores;
* toda transición prohibida permanece prohibida;
* todo tenant context se mantiene aislado;
* toda credential expirada falla;
* todo approval vencido se rechaza;
* todo evento crítico produce audit record.

---

### 2989. Mutation testing

La suite deberá comprobar que detecta modificaciones inseguras como:

* eliminar un authorization check;
* invertir una condición;
* ignorar expiración;
* omitir tenant filter;
* aceptar signature inválida;
* desactivar revocation;
* convertir fail-closed en fail-open.

---

### 2990. Chaos engineering

La seguridad deberá probarse bajo fallos parciales y degradación.

---

### 2991. Identity security chaos scenarios

Deberán simularse:

* identity store unavailable;
* policy engine timeout;
* MFA provider outage;
* federation metadata outage;
* revocation cache failure;
* telemetry pipeline delay;
* clock skew;
* message duplication;
* message reordering;
* connector partial failure.

---

### 2992. Failure injection principles

La inyección de fallos deberá:

* estar autorizada;
* tener scope limitado;
* ser reversible;
* generar observabilidad;
* preservar tenant isolation;
* evitar datos reales;
* contar con abort conditions.

---

### 2993. Fail-secure verification

Ante fallos críticos, el sistema deberá demostrar que:

* no omite autenticación;
* no omite MFA;
* no amplía privilegios;
* no pierde tenant scope;
* no ignora revocación;
* no completa recovery sin assurance;
* no permite lifecycle inconsistente.

---

### 2994. Security regression suites

VoltStack deberá mantener suites de regresión para vulnerabilidades corregidas.

Cada vulnerabilidad deberá producir:

* test reproducible;
* issue reference;
* fix version;
* affected components;
* regression identifier;
* long-term ownership.

---

### 2995. Red team scenarios

Los ejercicios red team deberán incluir:

* account takeover;
* privileged identity compromise;
* support desk compromise;
* federation abuse;
* tenant escape;
* recovery manipulation;
* API credential theft;
* persistent session access;
* insider lifecycle abuse.

---

### 2996. Purple team validation

Red team y blue team deberán validar conjuntamente:

* detectability;
* telemetry completeness;
* alert quality;
* response latency;
* containment effectiveness;
* evidence preservation;
* rule tuning;
* missed detections.

---

### 2997. Compliance control testing

Los controles deberán mapearse a pruebas verificables.

Ejemplos:

* MFA enforcement;
* session timeout;
* privileged review;
* credential rotation;
* offboarding latency;
* audit immutability;
* tenant isolation;
* recovery approval;
* access recertification.

---

### 2998. Security test evidence

Cada ejecución deberá producir:

* plan;
* environment;
* version;
* test cases;
* inputs;
* results;
* failures;
* evidence;
* timestamps;
* responsible actor;
* remediation linkage.

---

### 2999. Identity security testing governance

La gobernanza deberá definir:

* test ownership;
* minimum coverage;
* release gates;
* destructive testing rules;
* production-safe testing;
* red team cadence;
* exception process;
* evidence retention;
* remediation SLA;
* control mapping;
* test review.

Eventos recomendados:

* `IdentitySecurityTestPlanCreated`;
* `IdentitySecurityTestStarted`;
* `IdentitySecurityInvariantVerified`;
* `IdentitySecurityInvariantFailed`;
* `AuthenticationSecurityTestFailed`;
* `SessionSecurityTestFailed`;
* `CredentialSecurityTestFailed`;
* `RecoverySecurityTestFailed`;
* `TenantIsolationTestFailed`;
* `IdentitySecurityFuzzingCompleted`;
* `IdentitySecurityMutationSurvived`;
* `IdentitySecurityChaosExperimentStarted`;
* `IdentitySecurityChaosExperimentAborted`;
* `IdentitySecurityRegressionDetected`;
* `IdentityRedTeamFindingCreated`;
* `IdentityPurpleTeamValidationCompleted`;
* `IdentityComplianceControlTested`.

---

### 3000. Resultado de esta entrega

Esta entrega establece:

```text id="a6d7ww"
Identity Security Testing Architecture
Security Test Plans
Security Test Risk Levels
Synthetic Test Fixtures
Identity Security Invariants
Authentication Security Testing
Enumeration Resistance Testing
Timing Side-Channel Testing
Password Security Testing
Credential Stuffing Simulation
Password Spraying Simulation
Adaptive Lockout Testing
MFA Security Testing
OTP Replay Testing
MFA Fatigue Testing
Passkey Security Testing
WebAuthn Downgrade Testing
Federation Authentication Testing
Forged Assertion Testing
Authentication Failover Testing
Session Security Testing
Session Fixation Testing
Session Hijacking Simulation
Session Rotation Testing
Session Revocation Testing
Revocation Propagation Testing
Credential Lifecycle Testing
API Key Testing
Certificate Testing
Recovery Security Testing
Recovery Token Testing
Support Recovery Testing
Privileged Recovery Testing
Break-Glass Testing
Identity Lifecycle Testing
Joiner-Mover-Leaver Testing
Identity Resurrection Testing
Lifecycle Race-Condition Testing
Federation Trust Testing
Federation Key Rollover Testing
Multi-Tenant Identity Testing
Tenant Context Tampering Testing
Abuse-Case Testing
Adversarial Testing
Fuzzing
Property-Based Testing
Mutation Testing
Chaos Engineering
Failure Injection
Fail-Secure Verification
Security Regression Suites
Red Team Scenarios
Purple Team Validation
Compliance Control Testing
Security Test Evidence
Identity Security Testing Governance
```

La siguiente entrega continuará con:

```text id="4wud9i"
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 31

- Identity security compliance architecture
- Control framework mapping
- NIST identity assurance alignment
- ISO identity security controls
- SOC 2 control mapping
- GDPR identity protections
- Privacy rights handling
- Data residency
- Identity records retention
- Legal holds
- Evidence preservation
- Access certification
- Privileged access reviews
- Segregation of duties
- Policy attestations
- Compliance exceptions
- Continuous control monitoring
- Audit readiness
- Regulatory reporting
- Identity compliance audit events
```

## Entrega 31

**Documento:** Parte 05
**Entrega:** 31 de varias
**Cobertura:** Secciones **3001–3100**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 30`

---

### 3001. Identity Security Compliance Architecture

VoltStack deberá incorporar una arquitectura formal de **Identity Security Compliance**, responsable de traducir requisitos legales, regulatorios, contractuales y organizacionales en controles verificables sobre:

* identidades;
* autenticación;
* sesiones;
* credenciales;
* privilegios;
* recovery;
* lifecycle;
* federación;
* dispositivos;
* auditoría;
* observabilidad;
* respuesta a incidentes.

El cumplimiento deberá diseñarse como una capacidad continua del framework y no como una colección aislada de reportes.

---

### 3002. Compliance architecture objectives

La arquitectura deberá garantizar:

* trazabilidad entre requisitos y controles;
* evidencia verificable;
* separación entre política y regulación;
* soporte multi-jurisdicción;
* aislamiento multi-tenant;
* automatización;
* versionado;
* explicabilidad;
* monitoreo continuo;
* preparación para auditoría;
* gestión de excepciones;
* mínima exposición de datos.

---

### 3003. Compliance architecture principles

VoltStack deberá aplicar los siguientes principios:

* control as code;
* policy as code;
* evidence by design;
* least privilege;
* purpose limitation;
* data minimization;
* defense in depth;
* separation of duties;
* continuous assurance;
* immutable auditability.

---

### 3004. Compliance threat model

Deberán contemplarse:

* controles declarados pero no implementados;
* evidencia incompleta;
* manipulación de reportes;
* scope incorrecto;
* auditoría cross-tenant;
* excepción permanente;
* policy drift;
* accesos no certificados;
* legal holds omitidos;
* retención excesiva;
* eliminación anticipada;
* privileged access no revisado;
* falsos positivos de cumplimiento;
* regulatory mapping obsoleto.

---

### 3005. Compliance processing pipeline

```text id="dkty19"
Regulatory Requirement
      ↓
Control Mapping
      ↓
Policy Binding
      ↓
Technical Enforcement
      ↓
Evidence Collection
      ↓
Continuous Monitoring
      ↓
Exception Evaluation
      ↓
Attestation
      ↓
Audit Reporting
```

---

### 3006. IdentityComplianceService

```php id="oo54nw"
interface IdentityComplianceServiceInterface
{
    public function evaluate(
        IdentityComplianceEvaluationRequest $request
    ): IdentityComplianceEvaluationResult;

    public function collectEvidence(
        IdentityComplianceEvidenceRequest $request
    ): IdentityComplianceEvidencePackage;

    public function generateReport(
        IdentityComplianceReportRequest $request
    ): IdentityComplianceReport;
}
```

---

### 3007. IdentityComplianceEvaluationRequest

```php id="ind3eh"
final readonly class IdentityComplianceEvaluationRequest
{
    public function __construct(
        public string $evaluationId,
        public string $tenantId,
        public array $frameworks,
        public array $controlIds,
        public DateTimeImmutable $windowStart,
        public DateTimeImmutable $windowEnd,
        public IdentityIdentifier|string $requestedBy,
    ) {
    }
}
```

---

### 3008. IdentityComplianceEvaluationResult

```php id="8ayj3p"
final readonly class IdentityComplianceEvaluationResult
{
    public function __construct(
        public string $evaluationId,
        public ComplianceEvaluationState $state,
        public array $controlResults,
        public array $exceptions,
        public array $evidenceReferences,
        public DateTimeImmutable $evaluatedAt,
    ) {
    }
}
```

---

### 3009. ComplianceEvaluationState

```php id="isppq7"
enum ComplianceEvaluationState: string
{
    case Compliant = 'compliant';
    case PartiallyCompliant = 'partially_compliant';
    case NonCompliant = 'non_compliant';
    case NotApplicable = 'not_applicable';
    case Indeterminate = 'indeterminate';
}
```

---

### 3010. Control framework abstraction

VoltStack deberá desacoplar controles técnicos de marcos regulatorios concretos.

Un mismo control técnico podrá satisfacer múltiples requisitos.

---

### 3011. ComplianceFramework

```php id="6re4ry"
final readonly class ComplianceFramework
{
    public function __construct(
        public string $frameworkId,
        public string $name,
        public string $version,
        public string $jurisdiction,
        public array $requirements,
        public DateTimeImmutable $effectiveAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 3012. IdentityComplianceControl

```php id="z9t2ed"
final readonly class IdentityComplianceControl
{
    public function __construct(
        public string $controlId,
        public string $name,
        public string $description,
        public ComplianceControlType $type,
        public array $technicalRequirements,
        public array $evidenceRequirements,
        public array $frameworkMappings,
        public ComplianceControlState $state,
    ) {
    }
}
```

---

### 3013. ComplianceControlType

```php id="tf923b"
enum ComplianceControlType: string
{
    case Preventive = 'preventive';
    case Detective = 'detective';
    case Corrective = 'corrective';
    case Compensating = 'compensating';
    case Administrative = 'administrative';
    case Technical = 'technical';
}
```

---

### 3014. ComplianceControlState

```php id="nkow72"
enum ComplianceControlState: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Suspended = 'suspended';
    case Deprecated = 'deprecated';
    case Retired = 'retired';
}
```

---

### 3015. Control mapping model

Cada mapping deberá relacionar:

* framework;
* requirement;
* control;
* implementation;
* evidence;
* owner;
* frequency;
* applicability;
* exceptions.

---

### 3016. ComplianceControlMapping

```php id="hsv9ae"
final readonly class ComplianceControlMapping
{
    public function __construct(
        public string $mappingId,
        public string $frameworkId,
        public string $requirementId,
        public string $controlId,
        public array $implementationReferences,
        public array $evidenceTypes,
        public ComplianceApplicability $applicability,
        public DateTimeImmutable $effectiveAt,
    ) {
    }
}
```

---

### 3017. ComplianceApplicability

```php id="qfsnii"
enum ComplianceApplicability: string
{
    case Required = 'required';
    case Conditional = 'conditional';
    case Optional = 'optional';
    case NotApplicable = 'not_applicable';
}
```

---

### 3018. Control ownership

Todo control deberá tener:

* business owner;
* technical owner;
* evidence owner;
* remediation owner;
* approver;
* review cadence.

---

### 3019. Control versioning

Cambios en controles deberán conservar:

* previous version;
* effective date;
* rationale;
* approver;
* affected tenants;
* migration requirements;
* evidence continuity.

---

### 3020. Control implementation status

El sistema deberá diferenciar:

* designed;
* implemented;
* operating;
* monitored;
* failing;
* remediating;
* retired.

---

### 3021. NIST identity assurance alignment

VoltStack deberá permitir mapear sus niveles internos hacia modelos de assurance reconocidos.

---

### 3022. Assurance mapping boundaries

El framework no deberá declarar equivalencia normativa automática.

El mapping deberá documentar:

* requisitos cubiertos;
* requisitos parciales;
* gaps;
* compensating controls;
* tenant-specific assumptions.

---

### 3023. IdentityAssuranceMapping

```php id="09ivnj"
final readonly class IdentityAssuranceMapping
{
    public function __construct(
        public IdentityProofingLevel $internalProofingLevel,
        public AuthenticationAssuranceLevel $internalAuthenticationLevel,
        public FederationAssuranceLevel $internalFederationLevel,
        public string $externalFramework,
        public string $externalLevel,
        public array $coverage,
        public array $gaps,
    ) {
    }
}
```

---

### 3024. Proofing compliance evidence

La evidencia de proofing deberá demostrar:

* proceso aplicado;
* assurance requerido;
* assurance alcanzado;
* fuentes verificadas;
* decision;
* reviewer cuando aplique;
* expiration;
* revocation status.

---

### 3025. Authentication compliance evidence

Deberá demostrar:

* autenticación exitosa;
* método utilizado;
* assurance alcanzado;
* policy version;
* MFA enforcement;
* risk decision;
* session issuance;
* tenant context.

---

### 3026. Session compliance evidence

Deberá demostrar:

* creation;
* lifetime;
* idle timeout;
* absolute timeout;
* privilege elevation;
* revocation;
* termination;
* propagation;
* residual use detection.

---

### 3027. ISO-aligned identity controls

VoltStack deberá soportar controles relacionados con:

* identity management;
* authentication information;
* access rights;
* privileged access;
* segregation of duties;
* logging;
* monitoring;
* lifecycle management;
* supplier identity access.

---

### 3028. SOC-aligned control evidence

El framework deberá poder demostrar:

* control design;
* operating effectiveness;
* test period;
* exceptions;
* remediation;
* reviewer;
* evidence integrity;
* population completeness.

---

### 3029. GDPR identity protection architecture

Cuando aplique, VoltStack deberá soportar:

* lawful basis;
* purpose limitation;
* minimization;
* accuracy;
* storage limitation;
* security;
* transparency;
* data subject rights;
* accountability.

---

### 3030. Lawful basis metadata

Toda operación que procese datos personales sensibles deberá poder asociarse a:

* lawful basis;
* processing purpose;
* controller;
* processor;
* retention rule;
* jurisdiction;
* data category.

---

### 3031. IdentityProcessingPurpose

```php id="8x8ib4"
final readonly class IdentityProcessingPurpose
{
    public function __construct(
        public string $purposeId,
        public string $name,
        public string $lawfulBasis,
        public array $allowedDataCategories,
        public array $allowedOperations,
        public DateInterval $retention,
        public array $jurisdictions,
    ) {
    }
}
```

---

### 3032. Purpose-bound identity access

El acceso a datos de identidad deberá validar:

* actor;
* purpose;
* tenant;
* data category;
* retention state;
* legal restrictions;
* authorization;
* audit requirement.

---

### 3033. Identity data minimization

Los controladores, servicios y reportes deberán solicitar solo atributos necesarios para la operación.

---

### 3034. Field-level identity policies

El sistema deberá permitir controlar acceso por atributo.

Ejemplos:

* legal name;
* email;
* phone;
* government identifier;
* recovery contact;
* biometric reference;
* risk score;
* investigation status.

---

### 3035. Privacy rights handling architecture

VoltStack deberá soportar workflows para derechos de privacidad aplicables.

---

### 3036. IdentityPrivacyRightsRequest

```php id="3f8py6"
final readonly class IdentityPrivacyRightsRequest
{
    public function __construct(
        public string $requestId,
        public IdentityIdentifier $identityId,
        public PrivacyRightType $type,
        public string $jurisdiction,
        public IdentityIdentifier|string $requestedBy,
        public DateTimeImmutable $requestedAt,
    ) {
    }
}
```

---

### 3037. PrivacyRightType

```php id="blh5y4"
enum PrivacyRightType: string
{
    case Access = 'access';
    case Rectification = 'rectification';
    case Erasure = 'erasure';
    case Restriction = 'restriction';
    case Portability = 'portability';
    case Objection = 'objection';
    case ConsentWithdrawal = 'consent_withdrawal';
}
```

---

### 3038. Privacy request verification

Antes de ejecutar un derecho deberá verificarse la identidad del solicitante con assurance proporcional al riesgo.

---

### 3039. Privacy request scope

El sistema deberá resolver:

* identity records;
* credentials metadata;
* session history;
* recovery history;
* device records;
* audit events;
* lifecycle records;
* support cases;
* federation mappings;
* derived risk profiles.

---

### 3040. Privacy rights limitations

Una solicitud podrá limitarse por:

* legal obligation;
* fraud prevention;
* security investigation;
* legal hold;
* contractual requirement;
* rights of others;
* system integrity.

---

### 3041. Identity data portability

Los exports deberán ser:

* machine-readable;
* scoped;
* integrity-protected;
* encrypted;
* time-limited;
* audited;
* tenant-isolated.

---

### 3042. PortableIdentityPackage

```php id="myollf"
final readonly class PortableIdentityPackage
{
    public function __construct(
        public string $packageId,
        public IdentityIdentifier $identityId,
        public array $includedCategories,
        public string $format,
        public string $digest,
        public string $encryptionReference,
        public DateTimeImmutable $generatedAt,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 3043. Identity rectification

La corrección de datos deberá preservar:

* previous value;
* new value;
* source;
* reason;
* approver cuando aplique;
* effective date;
* downstream propagation.

---

### 3044. Identity erasure architecture

La eliminación deberá coordinar:

* lifecycle state;
* legal holds;
* credentials;
* sessions;
* tenant memberships;
* devices;
* recovery data;
* derived profiles;
* downstream systems;
* backups.

---

### 3045. Erasure vs anonymization

VoltStack deberá distinguir entre:

* logical deletion;
* physical deletion;
* anonymization;
* pseudonymization;
* cryptographic erasure;
* archival restriction.

---

### 3046. IdentityErasurePlan

```php id="4y68c6"
final readonly class IdentityErasurePlan
{
    public function __construct(
        public IdentityIdentifier $identityId,
        public array $dataStores,
        public array $blockedByLegalHolds,
        public array $anonymizationActions,
        public array $deletionActions,
        public array $verificationSteps,
        public DateTimeImmutable $scheduledAt,
    ) {
    }
}
```

---

### 3047. Data residency architecture

VoltStack deberá permitir restringir almacenamiento y procesamiento por región.

---

### 3048. IdentityDataResidencyPolicy

```php id="f01jkg"
final readonly class IdentityDataResidencyPolicy
{
    public function __construct(
        public string $policyId,
        public string $tenantId,
        public array $allowedRegions,
        public array $prohibitedRegions,
        public array $dataCategories,
        public array $approvedTransferMechanisms,
        public DateTimeImmutable $effectiveAt,
    ) {
    }
}
```

---

### 3049. Residency enforcement points

La residencia deberá validarse en:

* storage selection;
* backup placement;
* telemetry export;
* identity proofing providers;
* federation connectors;
* support tooling;
* analytics;
* disaster recovery;
* data export.

---

### 3050. Cross-border identity transfers

Toda transferencia deberá registrar:

* source region;
* destination region;
* data categories;
* purpose;
* transfer mechanism;
* processor;
* authorization;
* retention;
* encryption state.

---

### 3051. Data transfer policy engine

```php id="rkjwdt"
interface IdentityDataTransferPolicyEngineInterface
{
    public function evaluate(
        IdentityDataTransferRequest $request
    ): IdentityDataTransferDecision;
}
```

---

### 3052. Identity records retention

Cada registro deberá tener un perfil de retención explícito.

---

### 3053. IdentityRetentionProfile

```php id="b3g1kn"
final readonly class IdentityRetentionProfile
{
    public function __construct(
        public string $profileId,
        public IdentityRecordCategory $category,
        public DateInterval $activeRetention,
        public DateInterval $archiveRetention,
        public RetentionDisposition $disposition,
        public array $jurisdictionOverrides,
    ) {
    }
}
```

---

### 3054. IdentityRecordCategory

```php id="tt9zvb"
enum IdentityRecordCategory: string
{
    case CoreIdentity = 'core_identity';
    case CredentialMetadata = 'credential_metadata';
    case SessionHistory = 'session_history';
    case RecoveryEvidence = 'recovery_evidence';
    case ProofingEvidence = 'proofing_evidence';
    case LifecycleHistory = 'lifecycle_history';
    case AuditEvidence = 'audit_evidence';
    case SecurityIncident = 'security_incident';
    case DeviceRecord = 'device_record';
    case FederationRecord = 'federation_record';
}
```

---

### 3055. RetentionDisposition

```php id="6p59sq"
enum RetentionDisposition: string
{
    case Delete = 'delete';
    case Anonymize = 'anonymize';
    case Archive = 'archive';
    case CryptographicErase = 'cryptographic_erase';
    case ManualReview = 'manual_review';
}
```

---

### 3056. Retention precedence

Cuando existan múltiples reglas, deberá aplicarse una resolución explícita basada en:

* jurisdiction;
* legal hold;
* contract;
* regulation;
* tenant policy;
* incident;
* privacy request;
* record category.

---

### 3057. Retention scheduler

```php id="78jyli"
interface IdentityRetentionSchedulerInterface
{
    public function schedule(
        IdentityRetentionSubject $subject
    ): IdentityRetentionSchedule;

    public function executeDueActions(
        DateTimeImmutable $asOf
    ): IdentityRetentionExecutionReport;
}
```

---

### 3058. Retention execution safeguards

Toda eliminación deberá:

* reevaluar legal holds;
* verificar record ownership;
* confirmar tenant scope;
* preservar audit evidence;
* generar digest;
* emitir event;
* validar downstream completion.

---

### 3059. Legal hold architecture

Un legal hold deberá suspender eliminación o alteración de registros específicos.

---

### 3060. IdentityLegalHold

```php id="ouees9"
final readonly class IdentityLegalHold
{
    public function __construct(
        public string $holdId,
        public string $tenantId,
        public array $subjects,
        public array $recordCategories,
        public string $reason,
        public IdentityIdentifier|string $issuedBy,
        public DateTimeImmutable $effectiveAt,
        public ?DateTimeImmutable $expiresAt,
        public IdentityLegalHoldState $state,
    ) {
    }
}
```

---

### 3061. IdentityLegalHoldState

```php id="x24fme"
enum IdentityLegalHoldState: string
{
    case Draft = 'draft';
    case Active = 'active';
    case PartiallyReleased = 'partially_released';
    case Released = 'released';
    case Expired = 'expired';
}
```

---

### 3062. Legal hold targeting

Un hold podrá aplicarse a:

* una identidad;
* múltiples identidades;
* tenant;
* incident;
* credential family;
* session set;
* lifecycle window;
* recovery case;
* data category.

---

### 3063. Legal hold enforcement

El hold deberá bloquear:

* deletion;
* anonymization;
* cryptographic erasure;
* destructive compaction;
* retention expiration;
* evidence overwrite;
* backup disposal cuando aplique.

---

### 3064. Legal hold release

La liberación deberá:

* verificar authority;
* registrar reason;
* conservar evidence;
* reevaluar retention;
* reprogramar disposition;
* notificar owners.

---

### 3065. Evidence preservation architecture

La evidencia de cumplimiento deberá ser:

* completa;
* reproducible;
* integrity-protected;
* attributable;
* time-bounded;
* tenant-scoped;
* reviewable.

---

### 3066. IdentityComplianceEvidencePackage

```php id="0hdz9m"
final readonly class IdentityComplianceEvidencePackage
{
    public function __construct(
        public string $packageId,
        public string $tenantId,
        public array $controlIds,
        public array $evidenceItems,
        public string $digest,
        public string $signatureReference,
        public DateTimeImmutable $generatedAt,
    ) {
    }
}
```

---

### 3067. ComplianceEvidenceItem

```php id="3q2xdg"
final readonly class ComplianceEvidenceItem
{
    public function __construct(
        public string $evidenceId,
        public string $controlId,
        public ComplianceEvidenceType $type,
        public string $reference,
        public DateTimeImmutable $periodStart,
        public DateTimeImmutable $periodEnd,
        public string $collector,
        public string $digest,
    ) {
    }
}
```

---

### 3068. ComplianceEvidenceType

```php id="edm93z"
enum ComplianceEvidenceType: string
{
    case Configuration = 'configuration';
    case Policy = 'policy';
    case AuditLog = 'audit_log';
    case TestResult = 'test_result';
    case AccessReview = 'access_review';
    case Attestation = 'attestation';
    case IncidentRecord = 'incident_record';
    case Metric = 'metric';
    case Approval = 'approval';
}
```

---

### 3069. Evidence population completeness

La evidencia deberá demostrar que la población analizada es completa y no una muestra arbitraria no documentada.

---

### 3070. Evidence chain of custody

Toda evidencia deberá registrar:

* collector;
* source;
* timestamp;
* transformation;
* storage location;
* access history;
* digest;
* signature;
* export history.

---

### 3071. Access certification architecture

VoltStack deberá soportar campañas periódicas para certificar acceso.

---

### 3072. AccessCertificationCampaign

```php id="xmx4pq"
final readonly class AccessCertificationCampaign
{
    public function __construct(
        public string $campaignId,
        public string $tenantId,
        public AccessCertificationScope $scope,
        public array $reviewers,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public AccessCertificationCampaignState $state,
    ) {
    }
}
```

---

### 3073. AccessCertificationScope

```php id="ri3bgp"
enum AccessCertificationScope: string
{
    case AllIdentities = 'all_identities';
    case PrivilegedIdentities = 'privileged_identities';
    case Application = 'application';
    case Role = 'role';
    case Resource = 'resource';
    case Tenant = 'tenant';
    case HighRiskAccess = 'high_risk_access';
}
```

---

### 3074. AccessCertificationCampaignState

```php id="lpc7a9"
enum AccessCertificationCampaignState: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Overdue = 'overdue';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
```

---

### 3075. Certification review item

```php id="4pudcx"
final readonly class AccessCertificationReviewItem
{
    public function __construct(
        public string $itemId,
        public IdentityIdentifier $identityId,
        public string $accessReference,
        public AccessCertificationDecision $decision,
        public ?string $justification,
        public ?IdentityIdentifier $reviewedBy,
        public ?DateTimeImmutable $reviewedAt,
    ) {
    }
}
```

---

### 3076. AccessCertificationDecision

```php id="grtcb3"
enum AccessCertificationDecision: string
{
    case Pending = 'pending';
    case Approve = 'approve';
    case Revoke = 'revoke';
    case Modify = 'modify';
    case Escalate = 'escalate';
    case NotApplicable = 'not_applicable';
}
```

---

### 3077. Certification reviewer selection

Los reviewers deberán seleccionarse según:

* manager;
* resource owner;
* application owner;
* role owner;
* data owner;
* privileged access owner;
* compliance;
* independent reviewer.

---

### 3078. Reviewer conflict prevention

Un usuario no deberá certificar:

* su propio acceso privilegiado;
* acceso que él mismo asignó;
* acceso bajo investigación;
* excepciones que él aprobó;
* recursos que no controla.

---

### 3079. Certification evidence

Cada decisión deberá registrar:

* reviewer;
* current access;
* usage history;
* risk indicators;
* entitlement source;
* decision;
* justification;
* timestamp;
* policy version.

---

### 3080. Certification remediation

Las decisiones de revoke o modify deberán generar acciones rastreables con SLA.

---

### 3081. Overdue certification handling

Las campañas vencidas podrán activar:

* escalation;
* temporary restriction;
* automatic revocation para acceso de alto riesgo;
* compliance exception;
* executive notification.

---

### 3082. Privileged access review architecture

Los accesos privilegiados deberán revisarse con mayor frecuencia y evidencia reforzada.

---

### 3083. PrivilegedAccessReview

```php id="wl5922"
final readonly class PrivilegedAccessReview
{
    public function __construct(
        public string $reviewId,
        public IdentityIdentifier $identityId,
        public array $privileges,
        public array $usageEvidence,
        public array $riskSignals,
        public AccessCertificationDecision $decision,
        public IdentityIdentifier $reviewedBy,
        public DateTimeImmutable $reviewedAt,
    ) {
    }
}
```

---

### 3084. Privileged review requirements

Deberán considerarse:

* actual usage;
* last use;
* business need;
* role owner;
* break-glass usage;
* SoD conflicts;
* incidents;
* inactive privileges;
* temporary grants;
* standing privilege.

---

### 3085. Standing privilege reduction

VoltStack deberá favorecer:

* just-in-time access;
* time-bound privilege;
* approval-based elevation;
* session-bound privilege;
* purpose-bound privilege;
* automatic expiration.

---

### 3086. Segregation of duties architecture

El sistema deberá detectar y prevenir combinaciones de acceso incompatibles.

---

### 3087. SegregationOfDutiesRule

```php id="5jb58a"
final readonly class SegregationOfDutiesRule
{
    public function __construct(
        public string $ruleId,
        public string $name,
        public array $conflictingEntitlements,
        public SegregationOfDutiesRuleType $type,
        public ThreatSeverity $severity,
        public array $allowedCompensatingControls,
        public SegregationOfDutiesRuleState $state,
    ) {
    }
}
```

---

### 3088. SegregationOfDutiesRuleType

```php id="snqg16"
enum SegregationOfDutiesRuleType: string
{
    case Static = 'static';
    case Dynamic = 'dynamic';
    case Transactional = 'transactional';
    case CrossApplication = 'cross_application';
    case CrossTenant = 'cross_tenant';
}
```

---

### 3089. SoD evaluation points

Las reglas deberán evaluarse durante:

* access request;
* role assignment;
* lifecycle change;
* tenant transfer;
* privilege elevation;
* delegation;
* access certification;
* policy change;
* recovery;
* break-glass activation.

---

### 3090. SegregationOfDutiesViolation

```php id="id3e2k"
final readonly class SegregationOfDutiesViolation
{
    public function __construct(
        public string $violationId,
        public IdentityIdentifier $identityId,
        public string $ruleId,
        public array $conflictingAccess,
        public ThreatSeverity $severity,
        public SoDViolationState $state,
        public DateTimeImmutable $detectedAt,
    ) {
    }
}
```

---

### 3091. SoDViolationState

```php id="rh4uo6"
enum SoDViolationState: string
{
    case Detected = 'detected';
    case Blocked = 'blocked';
    case UnderReview = 'under_review';
    case ExceptionApproved = 'exception_approved';
    case Remediating = 'remediating';
    case Resolved = 'resolved';
}
```

---

### 3092. Dynamic SoD

La segregación dinámica deberá considerar acciones dentro de una transacción o ventana temporal.

Ejemplo:

```text id="xqtxu6"
Create Vendor
      +
Approve Vendor
      +
Authorize Payment
```

---

### 3093. Compensating controls for SoD

Una excepción podrá requerir:

* secondary approval;
* enhanced logging;
* session recording;
* transaction limits;
* independent review;
* short expiration;
* continuous monitoring.

---

### 3094. Policy attestation architecture

Los responsables deberán poder atestiguar:

* conocimiento;
* aceptación;
* revisión;
* cumplimiento;
* exception ownership;
* remediation status.

---

### 3095. IdentityPolicyAttestation

```php id="z9u1vn"
final readonly class IdentityPolicyAttestation
{
    public function __construct(
        public string $attestationId,
        public string $policyId,
        public string $policyVersion,
        public IdentityIdentifier $attestedBy,
        public IdentityPolicyAttestationType $type,
        public DateTimeImmutable $attestedAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 3096. IdentityPolicyAttestationType

```php id="7go5kz"
enum IdentityPolicyAttestationType: string
{
    case Acknowledged = 'acknowledged';
    case Accepted = 'accepted';
    case Reviewed = 'reviewed';
    case Compliant = 'compliant';
    case ExceptionOwned = 'exception_owned';
}
```

---

### 3097. Compliance exception architecture

Toda desviación deberá gestionarse formalmente.

---

### 3098. IdentityComplianceException

```php id="3s559o"
final readonly class IdentityComplianceException
{
    public function __construct(
        public string $exceptionId,
        public string $tenantId,
        public array $controlIds,
        public string $reason,
        public ThreatSeverity $risk,
        public array $compensatingControls,
        public IdentityIdentifier $owner,
        public IdentityIdentifier $approvedBy,
        public DateTimeImmutable $effectiveAt,
        public DateTimeImmutable $expiresAt,
        public ComplianceExceptionState $state,
    ) {
    }
}
```

---

### 3099. Continuous control monitoring and audit events

VoltStack deberá monitorizar continuamente:

* control configuration;
* enforcement status;
* evidence freshness;
* exception expiry;
* certification completion;
* privileged review status;
* SoD violations;
* retention execution;
* legal hold enforcement;
* policy drift;
* residency violations;
* privacy request SLA.

Eventos recomendados:

* `IdentityComplianceEvaluationStarted`;
* `IdentityComplianceEvaluationCompleted`;
* `ComplianceControlActivated`;
* `ComplianceControlFailed`;
* `ComplianceEvidenceCollected`;
* `ComplianceEvidenceIntegrityVerified`;
* `IdentityPrivacyRightsRequestReceived`;
* `IdentityPrivacyRightsRequestVerified`;
* `IdentityDataExportGenerated`;
* `IdentityRectificationCompleted`;
* `IdentityErasureScheduled`;
* `IdentityErasureCompleted`;
* `IdentityDataResidencyViolationDetected`;
* `IdentityRetentionActionScheduled`;
* `IdentityRetentionActionExecuted`;
* `IdentityLegalHoldApplied`;
* `IdentityLegalHoldReleased`;
* `AccessCertificationCampaignStarted`;
* `AccessCertificationDecisionRecorded`;
* `AccessCertificationRemediationOpened`;
* `PrivilegedAccessReviewCompleted`;
* `SegregationOfDutiesViolationDetected`;
* `SegregationOfDutiesExceptionApproved`;
* `IdentityPolicyAttested`;
* `IdentityComplianceExceptionCreated`;
* `IdentityComplianceExceptionExpired`;
* `IdentityContinuousControlFailureDetected`;
* `IdentityRegulatoryReportGenerated`.

---

### 3100. Resultado de esta entrega

Esta entrega establece:

```text id="ntr1gg"
Identity Security Compliance Architecture
Compliance Control Framework Abstraction
Control Mapping and Ownership
Control Versioning
NIST Identity Assurance Alignment
Identity Proofing Compliance Evidence
Authentication and Session Compliance Evidence
ISO-Aligned Identity Controls
SOC-Aligned Evidence
GDPR Identity Protection Architecture
Lawful Basis Metadata
Purpose-Bound Identity Access
Field-Level Identity Policies
Privacy Rights Handling
Identity Data Portability
Identity Rectification
Identity Erasure Architecture
Anonymization and Cryptographic Erasure
Data Residency Enforcement
Cross-Border Identity Transfers
Identity Records Retention
Retention Scheduling
Legal Hold Architecture
Evidence Preservation
Evidence Chain of Custody
Access Certification Campaigns
Reviewer Conflict Prevention
Certification Remediation
Privileged Access Reviews
Standing Privilege Reduction
Segregation of Duties
Dynamic SoD
Compensating Controls
Policy Attestations
Compliance Exceptions
Continuous Control Monitoring
Identity Compliance Audit Events
```

La siguiente entrega continuará con:

```text id="lmjbd2"
CONTROLLER_SECURITY_MODEL_PART_05
Entrega 32

- Identity security governance architecture
- Security ownership model
- Policy hierarchy
- Security decision rights
- Identity risk acceptance
- Exception governance
- Identity architecture review
- Security design review
- Threat modeling governance
- Identity change management
- Security release gates
- Identity security maturity model
- Capability assessment
- Governance metrics
- Executive reporting
- Tenant governance boundaries
- Third-party identity governance
- Security roadmap
- Final architectural principles
- Part 05 closure
```

## Entrega 32

**Documento:** Parte 05
**Entrega:** 32 de 32
**Cobertura:** Secciones **3101–3200**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 31`

---

### 3101. Identity Security Governance Architecture

VoltStack deberá incorporar una arquitectura formal de **Identity Security Governance**, responsable de definir cómo se toman, aprueban, implementan, revisan y auditan las decisiones relacionadas con:

* identidad;
* autenticación;
* sesiones;
* credenciales;
* recovery;
* lifecycle;
* federación;
* privilegios;
* observabilidad;
* cumplimiento;
* incident response;
* excepciones;
* riesgo residual.

El gobierno deberá separar claramente autoridad, responsabilidad, ejecución y supervisión.

---

### 3102. Governance objectives

La arquitectura deberá garantizar:

* decisiones consistentes;
* ownership explícito;
* trazabilidad;
* separación de funciones;
* reducción de excepciones permanentes;
* control multi-tenant;
* evolución segura;
* accountability;
* gestión de riesgo;
* revisión independiente;
* alineación con negocio;
* mejora continua.

---

### 3103. Governance principles

VoltStack deberá aplicar:

* explicit ownership;
* decision rights;
* least authority;
* dual control;
* independent review;
* policy supremacy;
* evidence-based decisions;
* time-bound exceptions;
* risk transparency;
* continuous reassessment;
* tenant isolation;
* secure defaults.

---

### 3104. Governance threat model

Deberán contemplarse:

* policy capture;
* concentration of authority;
* shadow exceptions;
* undocumented overrides;
* stale ownership;
* bypass de revisión;
* release sin seguridad;
* conflict of interest;
* tenant overreach;
* third-party dependency risk;
* risk acceptance indefinida;
* métricas manipuladas;
* architecture drift;
* governance fatigue.

---

### 3105. Governance operating model

```text
Business Requirement
      ↓
Identity Security Policy
      ↓
Architecture Decision
      ↓
Technical Control
      ↓
Implementation
      ↓
Verification
      ↓
Operational Monitoring
      ↓
Risk Review
      ↓
Governance Attestation
```

---

### 3106. IdentitySecurityGovernanceService

```php
interface IdentitySecurityGovernanceServiceInterface
{
    public function evaluateDecision(
        IdentitySecurityGovernanceDecisionRequest $request
    ): IdentitySecurityGovernanceDecision;

    public function registerException(
        IdentitySecurityGovernanceExceptionRequest $request
    ): IdentitySecurityGovernanceException;

    public function assessMaturity(
        IdentitySecurityMaturityAssessmentRequest $request
    ): IdentitySecurityMaturityAssessment;
}
```

---

### 3107. IdentitySecurityGovernanceDecisionRequest

```php
final readonly class IdentitySecurityGovernanceDecisionRequest
{
    public function __construct(
        public string $decisionId,
        public string $tenantId,
        public IdentitySecurityGovernanceDecisionType $type,
        public array $affectedCapabilities,
        public array $riskContext,
        public IdentityIdentifier|string $requestedBy,
        public DateTimeImmutable $requestedAt,
    ) {
    }
}
```

---

### 3108. IdentitySecurityGovernanceDecisionType

```php
enum IdentitySecurityGovernanceDecisionType: string
{
    case PolicyApproval = 'policy_approval';
    case ArchitectureApproval = 'architecture_approval';
    case RiskAcceptance = 'risk_acceptance';
    case ExceptionApproval = 'exception_approval';
    case ReleaseApproval = 'release_approval';
    case CapabilityRetirement = 'capability_retirement';
    case TenantOverride = 'tenant_override';
    case EmergencyDecision = 'emergency_decision';
}
```

---

### 3109. IdentitySecurityGovernanceDecision

```php
final readonly class IdentitySecurityGovernanceDecision
{
    public function __construct(
        public string $decisionId,
        public bool $approved,
        public array $conditions,
        public array $requiredActions,
        public array $approvers,
        public array $evidenceReferences,
        public DateTimeImmutable $decidedAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 3110. Security ownership model

Cada capability deberá tener ownership explícito.

---

### 3111. Identity security ownership roles

Deberán definirse, como mínimo:

* business owner;
* security owner;
* architecture owner;
* technical owner;
* operations owner;
* compliance owner;
* data owner;
* tenant owner;
* incident owner;
* control owner.

---

### 3112. IdentitySecurityOwnershipAssignment

```php
final readonly class IdentitySecurityOwnershipAssignment
{
    public function __construct(
        public string $assignmentId,
        public string $capabilityId,
        public IdentitySecurityOwnershipRole $role,
        public IdentityIdentifier|string $owner,
        public DateTimeImmutable $effectiveAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 3113. IdentitySecurityOwnershipRole

```php
enum IdentitySecurityOwnershipRole: string
{
    case Business = 'business';
    case Security = 'security';
    case Architecture = 'architecture';
    case Technical = 'technical';
    case Operations = 'operations';
    case Compliance = 'compliance';
    case Data = 'data';
    case Tenant = 'tenant';
    case Incident = 'incident';
    case Control = 'control';
}
```

---

### 3114. Ownership lifecycle

Todo ownership deberá soportar:

* assignment;
* delegation;
* temporary substitution;
* transfer;
* expiration;
* revocation;
* recertification.

---

### 3115. Ownership vacancy prevention

Una capability crítica no deberá permanecer sin owner activo.

---

### 3116. Ownership conflict detection

El sistema deberá detectar combinaciones incompatibles, como:

* requester y approver;
* implementer y auditor;
* exception owner y final reviewer;
* control owner y evidence validator;
* tenant administrator y global reviewer.

---

### 3117. Policy hierarchy

VoltStack deberá implementar una jerarquía explícita de políticas.

---

### 3118. Policy precedence model

```text
Law and Regulation
      ↓
Platform Security Policy
      ↓
Framework Security Baseline
      ↓
Environment Policy
      ↓
Tenant Policy
      ↓
Application Policy
      ↓
Resource Policy
      ↓
Session or Transaction Policy
```

---

### 3119. Policy supremacy

Una política inferior no podrá debilitar una política superior salvo excepción formalmente autorizada.

---

### 3120. IdentitySecurityPolicyLayer

```php
enum IdentitySecurityPolicyLayer: int
{
    case Regulation = 700;
    case Platform = 600;
    case Framework = 500;
    case Environment = 400;
    case Tenant = 300;
    case Application = 200;
    case Resource = 100;
    case Transaction = 50;
}
```

---

### 3121. Policy resolution

```php
interface IdentitySecurityPolicyResolverInterface
{
    public function resolve(
        IdentitySecurityPolicyResolutionRequest $request
    ): IdentitySecurityEffectivePolicy;
}
```

---

### 3122. IdentitySecurityEffectivePolicy

```php
final readonly class IdentitySecurityEffectivePolicy
{
    public function __construct(
        public array $appliedPolicies,
        public array $resolvedRequirements,
        public array $denials,
        public array $mandatoryControls,
        public array $conflicts,
        public string $effectivePolicyDigest,
    ) {
    }
}
```

---

### 3123. Policy conflict resolution

Los conflictos deberán resolverse mediante:

* higher-layer precedence;
* deny-overrides;
* stronger-assurance preference;
* narrower scope;
* explicit legal override;
* governance escalation.

---

### 3124. Policy change governance

Todo cambio deberá registrar:

* author;
* rationale;
* security impact;
* affected tenants;
* migration plan;
* approvers;
* test evidence;
* effective date;
* rollback plan.

---

### 3125. Emergency policy changes

Los cambios de emergencia deberán:

* limitar alcance;
* expirar;
* generar alertas;
* requerir revisión posterior;
* conservar evidencia;
* no convertirse en configuración permanente automáticamente.

---

### 3126. Security decision rights

VoltStack deberá definir quién puede decidir cada categoría de seguridad.

---

### 3127. Decision rights matrix

La matriz deberá cubrir:

* policy approval;
* architecture approval;
* exception approval;
* risk acceptance;
* emergency override;
* privileged recovery;
* tenant override;
* control retirement;
* release approval;
* incident closure.

---

### 3128. IdentitySecurityDecisionRight

```php
final readonly class IdentitySecurityDecisionRight
{
    public function __construct(
        public string $rightId,
        public IdentitySecurityGovernanceDecisionType $decisionType,
        public array $eligibleRoles,
        public int $minimumApprovers,
        public bool $independentReviewRequired,
        public bool $tenantScoped,
        public ThreatSeverity $maximumRiskAuthority,
    ) {
    }
}
```

---

### 3129. Decision authority limits

Una identidad no deberá aprobar decisiones fuera de:

* su tenant;
* su riesgo autorizado;
* su capability;
* su periodo de delegación;
* su conflicto de interés;
* su jurisdiction.

---

### 3130. Delegated governance authority

La delegación deberá ser:

* explícita;
* temporal;
* scope-bound;
* auditable;
* revocable;
* no subdelegable por defecto.

---

### 3131. Identity risk governance

Todo riesgo relevante deberá registrarse y evaluarse formalmente.

---

### 3132. IdentitySecurityRisk

```php
final readonly class IdentitySecurityRisk
{
    public function __construct(
        public string $riskId,
        public string $tenantId,
        public string $title,
        public string $description,
        public ThreatSeverity $inherentSeverity,
        public ThreatSeverity $residualSeverity,
        public array $affectedCapabilities,
        public array $controls,
        public IdentityIdentifier|string $owner,
        public IdentitySecurityRiskState $state,
    ) {
    }
}
```

---

### 3133. IdentitySecurityRiskState

```php
enum IdentitySecurityRiskState: string
{
    case Identified = 'identified';
    case Assessed = 'assessed';
    case Treating = 'treating';
    case Accepted = 'accepted';
    case Transferred = 'transferred';
    case Avoided = 'avoided';
    case Closed = 'closed';
    case Reopened = 'reopened';
}
```

---

### 3134. Risk treatment options

El tratamiento deberá diferenciar:

* mitigate;
* avoid;
* transfer;
* accept;
* defer;
* monitor.

---

### 3135. Identity risk acceptance

La aceptación deberá ser excepcional y explícita.

---

### 3136. IdentitySecurityRiskAcceptance

```php
final readonly class IdentitySecurityRiskAcceptance
{
    public function __construct(
        public string $acceptanceId,
        public string $riskId,
        public ThreatSeverity $acceptedResidualRisk,
        public string $businessJustification,
        public array $conditions,
        public IdentityIdentifier $acceptedBy,
        public DateTimeImmutable $effectiveAt,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

### 3137. Risk acceptance requirements

Deberá incluir:

* risk owner;
* business rationale;
* residual risk;
* affected assets;
* compensating controls;
* review date;
* expiration;
* revocation conditions.

---

### 3138. Risk acceptance boundaries

No deberán aceptarse sin escalamiento especial riesgos que impliquen:

* cross-tenant exposure;
* authentication bypass;
* unrestricted privilege escalation;
* secret disclosure;
* legal violation;
* irreversible data loss;
* systemic fail-open behavior.

---

### 3139. Expired risk acceptance

Una aceptación vencida deberá volver automáticamente a estado no resuelto.

---

### 3140. Exception governance

Toda excepción deberá administrarse como un objeto lifecycle.

---

### 3141. IdentitySecurityGovernanceException

```php
final readonly class IdentitySecurityGovernanceException
{
    public function __construct(
        public string $exceptionId,
        public string $tenantId,
        public array $policyReferences,
        public string $justification,
        public ThreatSeverity $risk,
        public array $compensatingControls,
        public IdentityIdentifier $owner,
        public IdentityIdentifier $approvedBy,
        public DateTimeImmutable $effectiveAt,
        public DateTimeImmutable $expiresAt,
        public GovernanceExceptionState $state,
    ) {
    }
}
```

---

### 3142. GovernanceExceptionState

```php
enum GovernanceExceptionState: string
{
    case Requested = 'requested';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Remediated = 'remediated';
}
```

---

### 3143. Exception minimum requirements

Toda excepción deberá contener:

* scope;
* affected controls;
* rationale;
* risk;
* owner;
* approver;
* expiration;
* compensating controls;
* monitoring;
* remediation plan.

---

### 3144. Exception anti-patterns

VoltStack deberá detectar:

* exceptions without expiry;
* repeatedly renewed exceptions;
* broad tenant-wide scope;
* missing compensating controls;
* self-approved exceptions;
* inactive owners;
* absent remediation.

---

### 3145. Exception renewal

La renovación deberá tratarse como una nueva decisión, no como extensión automática.

---

### 3146. Exception revocation

Una excepción deberá revocarse ante:

* incident;
* scope change;
* control availability;
* regulatory change;
* owner departure;
* compensating control failure;
* risk increase.

---

### 3147. Identity architecture review

Los cambios relevantes deberán someterse a revisión arquitectónica.

---

### 3148. IdentityArchitectureReviewRequest

```php
final readonly class IdentityArchitectureReviewRequest
{
    public function __construct(
        public string $reviewId,
        public string $changeId,
        public array $affectedDomains,
        public array $architectureArtifacts,
        public array $threatModels,
        public array $controlMappings,
        public IdentityIdentifier|string $submittedBy,
        public DateTimeImmutable $submittedAt,
    ) {
    }
}
```

---

### 3149. Architecture review scope

Deberá revisar:

* trust boundaries;
* identity flows;
* authentication;
* sessions;
* credentials;
* tenant isolation;
* recovery;
* lifecycle;
* failure modes;
* observability;
* compliance;
* rollback.

---

### 3150. IdentityArchitectureReviewDecision

```php
final readonly class IdentityArchitectureReviewDecision
{
    public function __construct(
        public string $reviewId,
        public ArchitectureReviewState $state,
        public array $findings,
        public array $conditions,
        public array $requiredChanges,
        public array $reviewers,
        public DateTimeImmutable $decidedAt,
    ) {
    }
}
```

---

### 3151. ArchitectureReviewState

```php
enum ArchitectureReviewState: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case ChangesRequired = 'changes_required';
    case ConditionallyApproved = 'conditionally_approved';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
```

---

### 3152. Independent architecture review

Cambios de alto riesgo deberán incluir un reviewer que no haya diseñado ni implementado la solución.

---

### 3153. Security design review

Toda capability nueva deberá pasar por security design review antes de implementación completa.

---

### 3154. Security design review checklist

Deberá cubrir:

* assets;
* actors;
* entry points;
* trust boundaries;
* abuse cases;
* privilege model;
* data classification;
* tenant scope;
* secret handling;
* failure behavior;
* auditability;
* recovery.

---

### 3155. Security design review outputs

La revisión deberá producir:

* findings;
* required controls;
* residual risks;
* test requirements;
* logging requirements;
* release conditions;
* owner assignments.

---

### 3156. Threat modeling governance

VoltStack deberá mantener threat models versionados para cada dominio crítico.

---

### 3157. IdentityThreatModel

```php
final readonly class IdentityThreatModel
{
    public function __construct(
        public string $modelId,
        public string $capabilityId,
        public string $version,
        public array $assets,
        public array $actors,
        public array $trustBoundaries,
        public array $threats,
        public array $controls,
        public DateTimeImmutable $reviewedAt,
    ) {
    }
}
```

---

### 3158. Threat model review triggers

Deberá revisarse cuando exista:

* new authentication method;
* new identity provider;
* tenant model change;
* credential format change;
* recovery workflow change;
* privileged access change;
* incident;
* architectural boundary change;
* regulatory change.

---

### 3159. Threat model ownership

Cada modelo deberá tener:

* author;
* security reviewer;
* technical owner;
* business owner;
* next review date.

---

### 3160. Threat scenario traceability

Cada amenaza deberá vincularse con:

* control;
* test;
* telemetry;
* alert;
* response playbook;
* residual risk.

---

### 3161. Identity change management

Los cambios deberán seguir un lifecycle gobernado.

---

### 3162. IdentitySecurityChange

```php
final readonly class IdentitySecurityChange
{
    public function __construct(
        public string $changeId,
        public IdentitySecurityChangeType $type,
        public array $affectedCapabilities,
        public ThreatSeverity $risk,
        public array $dependencies,
        public array $rollbackActions,
        public IdentitySecurityChangeState $state,
        public DateTimeImmutable $scheduledAt,
    ) {
    }
}
```

---

### 3163. IdentitySecurityChangeType

```php
enum IdentitySecurityChangeType: string
{
    case Policy = 'policy';
    case Configuration = 'configuration';
    case Architecture = 'architecture';
    case Credential = 'credential';
    case Federation = 'federation';
    case TenantModel = 'tenant_model';
    case Cryptography = 'cryptography';
    case Emergency = 'emergency';
}
```

---

### 3164. IdentitySecurityChangeState

```php
enum IdentitySecurityChangeState: string
{
    case Proposed = 'proposed';
    case Assessed = 'assessed';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Deploying = 'deploying';
    case Validating = 'validating';
    case Completed = 'completed';
    case RolledBack = 'rolled_back';
    case Failed = 'failed';
}
```

---

### 3165. Change risk assessment

Deberá considerar:

* blast radius;
* tenant count;
* privilege impact;
* authentication impact;
* rollback complexity;
* irreversible effects;
* dependency maturity;
* observability;
* compliance impact.

---

### 3166. Change segregation of duties

El autor de un cambio crítico no deberá ser el único approver ni validador.

---

### 3167. Change deployment strategy

Cambios de alto riesgo deberán favorecer:

* shadow mode;
* feature flags;
* canary tenants;
* staged rollout;
* dual validation;
* automatic rollback;
* kill switch.

---

### 3168. Emergency change governance

Los emergency changes deberán incluir:

* incident linkage;
* narrow scope;
* temporary implementation;
* explicit owner;
* post-implementation review;
* retrospective approval;
* remediation plan.

---

### 3169. Security release gates

Ninguna release crítica deberá avanzar sin cumplir gates definidos.

---

### 3170. IdentitySecurityReleaseGate

```php
final readonly class IdentitySecurityReleaseGate
{
    public function __construct(
        public string $gateId,
        public string $name,
        public array $requiredChecks,
        public ThreatSeverity $minimumRiskLevel,
        public bool $blocking,
        public ReleaseGateState $state,
    ) {
    }
}
```

---

### 3171. ReleaseGateState

```php
enum ReleaseGateState: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';
    case Waived = 'waived';
    case Expired = 'expired';
}
```

---

### 3172. Mandatory security release gates

Podrán incluir:

* architecture review;
* threat model;
* security tests;
* tenant isolation tests;
* regression suite;
* compliance mapping;
* telemetry verification;
* rollback validation;
* owner approval;
* residual risk acceptance.

---

### 3173. Release waiver governance

Un waiver deberá:

* ser excepcional;
* tener owner;
* indicar riesgo;
* definir compensating controls;
* expirar;
* requerir remediation;
* quedar auditado.

---

### 3174. Release evidence package

```php
final readonly class IdentitySecurityReleaseEvidencePackage
{
    public function __construct(
        public string $releaseId,
        public array $gateResults,
        public array $testReports,
        public array $reviewDecisions,
        public array $riskAcceptances,
        public string $digest,
        public DateTimeImmutable $generatedAt,
    ) {
    }
}
```

---

### 3175. Post-release verification

Después del despliegue deberán verificarse:

* authentication success;
* denial behavior;
* session integrity;
* tenant isolation;
* telemetry;
* alerting;
* policy resolution;
* error rate;
* rollback readiness.

---

### 3176. Identity security maturity model

VoltStack deberá definir un modelo de madurez para evaluar capacidades.

---

### 3177. IdentitySecurityMaturityLevel

```php
enum IdentitySecurityMaturityLevel: int
{
    case Initial = 1;
    case Managed = 2;
    case Defined = 3;
    case Measured = 4;
    case Adaptive = 5;
}
```

---

### 3178. Maturity level: Initial

Características:

* controles reactivos;
* procesos manuales;
* ownership informal;
* evidencia incompleta;
* alta dependencia de individuos;
* baja consistencia.

---

### 3179. Maturity level: Managed

Características:

* procesos repetibles;
* owners identificados;
* controles básicos;
* documentación parcial;
* métricas operativas iniciales;
* excepciones registradas.

---

### 3180. Maturity level: Defined

Características:

* políticas versionadas;
* arquitectura establecida;
* procesos estandarizados;
* threat models;
* testing formal;
* governance multi-tenant.

---

### 3181. Maturity level: Measured

Características:

* métricas confiables;
* continuous control monitoring;
* risk quantification;
* automated evidence;
* release gates;
* maturity tracking.

---

### 3182. Maturity level: Adaptive

Características:

* detección dinámica;
* policy adaptation gobernada;
* automated response;
* predictive risk;
* continuous optimization;
* closed-loop governance.

---

### 3183. Identity security capability domains

La madurez deberá evaluarse por dominio:

* authentication;
* session security;
* credential security;
* recovery;
* lifecycle;
* federation;
* privileged access;
* observability;
* testing;
* compliance;
* governance;
* incident response.

---

### 3184. IdentitySecurityMaturityAssessment

```php
final readonly class IdentitySecurityMaturityAssessment
{
    public function __construct(
        public string $assessmentId,
        public string $tenantId,
        public array $domainScores,
        public IdentitySecurityMaturityLevel $overallLevel,
        public array $gaps,
        public array $recommendedInitiatives,
        public DateTimeImmutable $assessedAt,
    ) {
    }
}
```

---

### 3185. Capability assessment evidence

La evaluación deberá utilizar:

* policy coverage;
* control operation;
* test results;
* incidents;
* metrics;
* exceptions;
* certifications;
* architecture reviews;
* audit findings.

---

### 3186. Maturity scoring safeguards

El score no deberá ocultar riesgos críticos mediante promedios.

Una falla crítica deberá destacarse independientemente del promedio general.

---

### 3187. Governance metrics

Métricas recomendadas:

* policies without owner;
* overdue policy reviews;
* active exceptions;
* expired exceptions;
* accepted risks;
* overdue risk treatments;
* architecture review lead time;
* release gate failures;
* waiver rate;
* control ownership gaps;
* remediation SLA compliance;
* maturity progression.

---

### 3188. Governance key risk indicators

KRIs recomendados:

* privileged access without review;
* high-risk exception concentration;
* emergency change frequency;
* repeated waiver usage;
* unresolved cross-tenant findings;
* stale threat models;
* failed compensating controls;
* missing incident owners.

---

### 3189. Executive reporting

VoltStack deberá producir reportes ejecutivos enfocados en riesgo y tendencia.

---

### 3190. IdentitySecurityExecutiveReport

```php
final readonly class IdentitySecurityExecutiveReport
{
    public function __construct(
        public string $reportId,
        public string $tenantId,
        public array $topRisks,
        public array $materialIncidents,
        public array $governanceMetrics,
        public array $maturityTrends,
        public array $requiredDecisions,
        public DateTimeImmutable $generatedAt,
    ) {
    }
}
```

---

### 3191. Executive report boundaries

El reporte deberá evitar:

* detalles técnicos innecesarios;
* PII;
* raw secrets;
* investigation-sensitive data;
* cross-tenant exposure;
* métricas sin contexto.

---

### 3192. Tenant governance boundaries

Cada tenant deberá poder gobernar su configuración sin debilitar la baseline global.

---

### 3193. Tenant governance capabilities

Podrán incluir:

* stronger authentication;
* shorter session lifetimes;
* stricter recovery;
* additional approvals;
* local access reviews;
* regional retention;
* tenant-specific notifications;
* custom risk thresholds.

---

### 3194. Tenant governance restrictions

Un tenant no deberá poder:

* desactivar controles globales obligatorios;
* acceder a otro tenant;
* alterar audit evidence global;
* cambiar trust anchors compartidos;
* reducir cryptographic minimums;
* omitir incident reporting obligatorio.

---

### 3195. Third-party identity governance

Los proveedores externos deberán gestionarse bajo gobierno explícito.

---

### 3196. ThirdPartyIdentityProviderGovernanceRecord

```php
final readonly class ThirdPartyIdentityProviderGovernanceRecord
{
    public function __construct(
        public string $providerId,
        public ThirdPartyIdentityProviderType $type,
        public array $supportedCapabilities,
        public array $securityRequirements,
        public array $dataRegions,
        public array $contractualControls,
        public ThirdPartyGovernanceState $state,
        public DateTimeImmutable $lastReviewedAt,
    ) {
    }
}
```

---

### 3197. Third-party governance requirements

Deberán evaluarse:

* security posture;
* assurance support;
* breach notification;
* data residency;
* subcontractors;
* key management;
* availability;
* exit strategy;
* portability;
* audit rights;
* incident cooperation.

---

### 3198. Identity security roadmap

La evolución recomendada deberá organizarse en fases:

```text
Phase 1 — Baseline Security
Authentication, sessions, credentials, tenant isolation

Phase 2 — Assurance and Recovery
MFA, proofing, recovery, lifecycle controls

Phase 3 — Detection and Response
Telemetry, account takeover detection, incident containment

Phase 4 — Continuous Assurance
Testing, compliance automation, access certification

Phase 5 — Adaptive Governance
Risk-driven policy, maturity optimization, predictive controls
```

---

### 3199. Final architectural principles and audit events

Principios finales de la Parte 05:

1. La identidad es un dominio de seguridad, no un simple registro de usuario.
2. Autenticación no equivale automáticamente a autorización.
3. Toda sesión representa confianza temporal y revocable.
4. Toda credencial debe tener lifecycle, owner y propósito.
5. Recovery debe ser tan seguro como la autenticación que reemplaza.
6. La recuperación de identidad no restaura privilegios automáticamente.
7. El tenant context forma parte de la identidad efectiva.
8. Toda acción sensible debe ser policy-driven.
9. Todo control crítico debe fallar de forma segura.
10. Toda decisión relevante debe producir evidencia.
11. Las excepciones deben expirar.
12. El riesgo aceptado debe ser explícito.
13. La observabilidad no debe comprometer la privacidad.
14. La seguridad debe probarse bajo condiciones adversariales.
15. La gobernanza debe separar decisión, ejecución y supervisión.
16. La arquitectura debe ser portable, extensible y crypto-agile.
17. La seguridad de identidad debe mejorar continuamente.

Eventos recomendados:

* `IdentitySecurityOwnerAssigned`;
* `IdentitySecurityOwnershipTransferred`;
* `IdentitySecurityPolicyApproved`;
* `IdentitySecurityPolicyConflictDetected`;
* `IdentitySecurityGovernanceDecisionRequested`;
* `IdentitySecurityGovernanceDecisionApproved`;
* `IdentitySecurityGovernanceDecisionRejected`;
* `IdentitySecurityRiskIdentified`;
* `IdentitySecurityRiskAccepted`;
* `IdentitySecurityRiskAcceptanceExpired`;
* `IdentitySecurityExceptionRequested`;
* `IdentitySecurityExceptionApproved`;
* `IdentitySecurityExceptionRevoked`;
* `IdentityArchitectureReviewStarted`;
* `IdentityArchitectureReviewCompleted`;
* `IdentityThreatModelUpdated`;
* `IdentitySecurityChangeApproved`;
* `IdentitySecurityEmergencyChangeExecuted`;
* `IdentitySecurityReleaseGatePassed`;
* `IdentitySecurityReleaseGateFailed`;
* `IdentitySecurityReleaseWaiverApproved`;
* `IdentitySecurityMaturityAssessed`;
* `IdentitySecurityExecutiveReportGenerated`;
* `TenantIdentityGovernancePolicyChanged`;
* `ThirdPartyIdentityProviderReviewed`.

---

### 3200. Cierre de CONTROLLER_SECURITY_MODEL_PART_05

La Parte 05 establece una arquitectura integral para proteger el dominio de identidad dentro de VoltStack.

Incluye:

```text
Authentication Security
Session Security
Credential Security
Identity Assurance
Identity Proofing
Secure Recovery
Account Security Operations
Account Takeover Detection
Identity Lifecycle Security
Federation Security
Privileged Identity Security
Multi-Tenant Identity Isolation
Security Observability
Detection Engineering
Identity Security Testing
Compliance Architecture
Privacy and Retention
Access Certification
Segregation of Duties
Risk Governance
Exception Governance
Architecture Reviews
Threat Modeling
Change Management
Security Release Gates
Identity Security Maturity
Executive Governance
Third-Party Identity Governance
```

## Resultado arquitectónico final

VoltStack dispondrá de un modelo donde:

```text
Identity
   ↓
Proofing
   ↓
Authentication
   ↓
Session
   ↓
Authorization Context
   ↓
Privileged or Sensitive Action
   ↓
Telemetry
   ↓
Detection
   ↓
Response
   ↓
Recovery
   ↓
Governance
   ↓
Continuous Assurance
```

## Invariante central

```text
Ninguna identidad, sesión, credencial, recuperación,
elevación de privilegios o decisión administrativa
deberá operar fuera de policy, tenant context,
assurance, trazabilidad y gobierno explícito.
```

## Estado del documento

```text
CONTROLLER_SECURITY_MODEL_PART_05.md
Status: COMPLETE
Sections: 1–3200
Final Delivery: 32
Domain: Authentication, Session & Identity Security
```
