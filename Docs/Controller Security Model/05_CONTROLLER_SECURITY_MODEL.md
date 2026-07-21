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

# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 6 de varias
**Cobertura:** Secciones **501–600**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 5`

---

# 501. Session Validation Pipeline

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

# 502. Session validation principle

La existencia de un identificador válido no implica que la sesión siga siendo confiable.

---

# 503. SessionValidator

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

# 504. PresentedSessionCredential

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

# 505. SessionTransport

```php
enum SessionTransport: string
{
    case SecureCookie = 'secure_cookie';
    case AuthorizationHeader = 'authorization_header';
    case DeviceCredential = 'device_credential';
}
```

---

# 506. Query-string sessions prohibition

VoltStack no deberá aceptar identificadores de sesión desde:

* query strings;
* fragments;
* URL paths;
* formularios ocultos;

salvo protocolos legacy explícitamente aislados y deshabilitados por defecto.

---

# 507. SessionValidationContext

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

# 508. SessionValidationResult

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

# 509. SessionValidationStatus

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

# 510. Generic invalid-session behavior

La respuesta externa no deberá revelar si la sesión:

* no existe;
* expiró;
* fue revocada;
* pertenece a otro tenant;
* fue reemplazada;
* falló por riesgo.

---

# 511. Session lifecycle states

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

# 512. Pending session

Una sesión `Pending` podrá existir durante:

* MFA incompleto;
* verificación de dispositivo;
* bootstrap;
* recuperación;
* consentimiento requerido.

---

# 513. Pending session restrictions

No deberá acceder a Controllers ordinarios autenticados.

Solo podrá acceder a rutas explícitamente autorizadas para completar el flujo.

---

# 514. Active session

Una sesión `Active` será elegible para reconstruir un contexto autenticado completo.

---

# 515. Restricted session

Una sesión `Restricted` tendrá una lista explícita de capacidades permitidas.

---

# 516. RestrictedSessionCapabilities

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

# 517. Suspended session

Una sesión podrá suspenderse temporalmente por:

* riesgo;
* investigación;
* dispositivo comprometido;
* anomalía;
* acción administrativa.

---

# 518. Revoked session

Una sesión revocada deberá rechazarse permanentemente.

---

# 519. Expired session

La expiración deberá establecerse de forma explícita y auditable.

---

# 520. Replaced session

Una sesión rotada podrá conservar un registro `Replaced` para detectar reutilización del identificador anterior.

---

# 521. Session state transition rules

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

# 522. Session state machine

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

# 523. Illegal transitions

No se permitirá:

* reactivar una sesión revocada;
* volver a activar una sesión expirada;
* convertir una sesión reemplazada en activa;
* omitir auditoría en transiciones administrativas.

---

# 524. Session expiration validation

La validación deberá comprobar:

* idle expiration;
* absolute expiration;
* policy expiration;
* credential expiration;
* emergency invalidation.

---

# 525. Server-side expiration authority

La fecha almacenada en cliente nunca será fuente de verdad para la expiración.

---

# 526. Idle activity update

La actualización de `lastActivityAt` deberá ser:

* limitada;
* atómica;
* tolerante a concurrencia;
* no ejecutada en cada request innecesariamente.

---

# 527. Activity write throttling

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

# 528. Absolute expiration enforcement

La expiración absoluta deberá aplicarse aunque existan requests concurrentes o refreshes.

---

# 529. Session revocation architecture

La revocación deberá ser una operación centralizada, auditable y propagable entre nodos.

---

# 530. SessionRevocationService

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

# 531. SessionRevocationReason

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

# 532. Revocation context

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

# 533. Revocation propagation

En despliegues distribuidos, la revocación deberá propagarse mediante:

* store compartido;
* eventos;
* invalidation bus;
* cache eviction;
* revocation registry.

---

# 534. Revocation latency

Los perfiles de seguridad deberán definir el máximo retraso aceptable.

---

# 535. Fail-closed revocation

En operaciones críticas, si no puede confirmarse el estado de revocación, la sesión deberá rechazarse.

---

# 536. Global logout

El logout global deberá invalidar todas las sesiones elegibles de una identidad.

---

# 537. Global logout filters

La política podrá excluir o incluir:

* sesión actual;
* sesiones de servicio;
* sesiones administrativas;
* dispositivos administrados;
* impersonaciones;
* tokens persistentes.

---

# 538. SessionRevocationFilter

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

# 539. Logout semantics

Logout deberá significar más que borrar una cookie.

---

# 540. Logout flow

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

# 541. Cookie deletion

La cookie deberá eliminarse usando los mismos atributos relevantes con los que fue emitida:

* name;
* path;
* domain;
* secure context.

---

# 542. Logout idempotency

Repetir logout deberá ser seguro y producir una respuesta consistente.

---

# 543. Logout CSRF protection

El endpoint de logout deberá protegerse contra logout CSRF cuando la aplicación lo requiera.

---

# 544. GET logout prohibition

Logout mediante `GET` deberá evitarse por defecto.

---

# 545. Post-logout redirect

El redirect deberá pasar por la política de redirects seguros.

---

# 546. Logout event

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

# 547. Concurrent session management

El usuario deberá poder revisar y administrar sesiones activas.

---

# 548. Session inventory

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

# 549. Inventory data minimization

El inventario no deberá exponer:

* session ID real;
* IP completa por defecto;
* fingerprint interno;
* tokens;
* cookie values;
* datos de red precisos innecesarios.

---

# 550. Session display identifier

El `displayId` deberá ser un identificador seguro separado del session ID autenticante.

---

# 551. Session device inventory

Cada sesión podrá asociarse a un dispositivo reconocido.

---

# 552. SessionDeviceBinding

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

# 553. DeviceBindingStrength

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

# 554. Passive binding limitations

Un user agent o IP no constituye una vinculación criptográfica.

---

# 555. IP binding risks

Vincular estrictamente una sesión a IP puede causar problemas por:

* redes móviles;
* NAT;
* proxies;
* IPv6 privacy addresses;
* roaming;
* VPN;
* cambios corporativos.

---

# 556. Recommended IP usage

La IP deberá utilizarse como señal de riesgo, no como identidad rígida por defecto.

---

# 557. User-Agent binding risks

El User-Agent puede:

* cambiar;
* falsificarse;
* reducirse por privacidad;
* ser idéntico en muchos dispositivos.

---

# 558. Request fingerprint

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

# 559. Fingerprint classification

Cada señal deberá marcarse como:

* stable;
* semi-stable;
* volatile;
* untrusted;
* privacy-sensitive.

---

# 560. Binding mismatch response

Un cambio de fingerprint podrá provocar:

* continuar;
* elevar riesgo;
* requerir step-up;
* rotar sesión;
* restringir;
* revocar.

---

# 561. Cryptographic session binding

VoltStack podrá soportar sesiones vinculadas a una clave del cliente.

---

# 562. Proof-of-possession session

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

# 563. Proof-of-possession benefits

Reduce el valor de un session ID robado al requerir prueba adicional de clave.

---

# 564. Proof-of-possession limitations

Puede aumentar:

* complejidad;
* incompatibilidad;
* problemas de recuperación;
* dependencia del cliente;
* costo operacional.

---

# 565. Credential versioning

La identidad deberá mantener una versión de credenciales.

---

# 566. CredentialVersion

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

# 567. CredentialVersionReason

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

# 568. Credential version validation

La versión almacenada en sesión deberá compararse con la versión actual de la identidad.

---

# 569. Version mismatch

Una discrepancia podrá:

* revocar la sesión;
* exigir reautenticación;
* restringir operaciones;
* permitir continuidad limitada según política.

---

# 570. Authorization versioning

La sesión deberá poder detectar cambios en:

* roles;
* permisos;
* tenant membership;
* policies;
* resource scopes;
* account status.

---

# 571. AuthorizationVersion

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

# 572. Authorization cache invalidation

Los permisos derivados dentro de la sesión deberán invalidarse cuando cambie la versión.

---

# 573. Stale authorization prevention

Una sesión no deberá conservar privilegios indefinidamente después de:

* quitar un rol;
* eliminar tenant membership;
* revocar una policy;
* cambiar resource scope.

---

# 574. Authorization refresh strategy

El framework podrá:

* reconstruir contexto;
* invalidar cache;
* rotar sesión;
* revocar;
* exigir nueva autenticación para cambios sensibles.

---

# 575. Session refresh

Session refresh significa renovar estado de sesión sin repetir necesariamente la autenticación primaria.

---

# 576. Refresh eligibility

Solo una sesión:

* activa;
* no expirada;
* no revocada;
* compatible con policy;
* sin riesgo crítico;

podrá renovarse.

---

# 577. Refresh operation

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

# 578. Refresh rotation

La renovación deberá rotar el identificador cuando el perfil lo requiera.

---

# 579. Refresh does not reset absolute lifetime

La renovación no deberá extender la expiración absoluta más allá de la política original, salvo reautenticación explícita.

---

# 580. Remember-Me Architecture

“Remember me” no deberá significar una sesión ordinaria de duración indefinida.

---

# 581. Persistent login model

VoltStack deberá separar:

* sesión interactiva corta;
* credential persistente de reautenticación;
* nueva sesión emitida después de validar esa credential.

---

# 582. PersistentLoginToken

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

# 583. PersistentTokenState

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

# 584. Persistent token properties

El token deberá ser:

* aleatorio;
* opaco;
* de alta entropía;
* revocable;
* rotatable;
* vinculado a una familia;
* almacenado de forma derivada.

---

# 585. Selector-validator split

El token podrá representarse mediante:

```text
selector.validator
```

Donde:

* el selector permite lookup;
* el validator actúa como secreto;
* solo se almacena su digest.

---

# 586. Persistent cookie profile

La cookie deberá ser:

* `Secure`;
* `HttpOnly`;
* `SameSite` apropiado;
* scope reducido;
* separada de la cookie de sesión;
* revocable.

---

# 587. Remember-me authentication strength

Una sesión creada desde persistent login deberá iniciar con menor freshness que una autenticación interactiva reciente.

---

# 588. Sensitive route step-up

Las operaciones sensibles deberán exigir nueva autenticación aunque la sesión provenga de remember-me.

---

# 589. Persistent token rotation

Cada uso válido deberá emitir un nuevo token y retirar el anterior.

---

# 590. Token family

Una familia representa la cadena de rotaciones derivadas de un mismo enrolamiento persistente.

---

# 591. PersistentTokenFamily

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

# 592. PersistentTokenFamilyState

```php
enum PersistentTokenFamilyState: string
{
    case Active = 'active';
    case Revoked = 'revoked';
    case Compromised = 'compromised';
}
```

---

# 593. Rotation transaction

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

# 594. Refresh token reuse detection

Si un token marcado como `Rotated` vuelve a utilizarse, deberá considerarse replay.

---

# 595. Family revocation on replay

Ante reuse confirmado deberá:

* marcar la familia como comprometida;
* revocar tokens descendientes;
* revocar sesiones asociadas según política;
* elevar riesgo;
* notificar al usuario;
* generar incidente.

---

# 596. Concurrent request tolerance

El diseño deberá diferenciar entre:

* race legítimo;
* retry de red;
* replay malicioso.

---

# 597. Rotation grace record

Podrá conservarse una ventana muy corta de idempotencia vinculada al mismo request context.

No deberá permitir emitir múltiples descendientes válidos.

---

# 598. Session anomaly detection

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

# 599. SessionAnomalyEngine

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

# 600. Resultado de esta entrega

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

# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 7 de varias
**Cobertura:** Secciones **601–700**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 6`

---

# 601. OAuth 2.0 Security Architecture

OAuth 2.0 permitirá que una aplicación obtenga acceso limitado a recursos protegidos en nombre de:

* un usuario;
* una organización;
* un tenant;
* un servicio;
* una workload identity.

OAuth no deberá utilizarse como autenticación de usuario por sí solo.

Para autenticación federada deberá combinarse con OpenID Connect u otro protocolo de identidad explícito.

---

# 602. OAuth security goals

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

# 603. OAuth threat model

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

# 604. OAuth actors

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

# 605. Authorization server boundary

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

# 606. Resource server boundary

El Resource Server deberá:

* validar tokens;
* validar audience;
* validar scopes;
* validar sender constraint;
* aplicar autorización;
* rechazar tokens expirados o revocados.

---

# 607. OAuth client types

```php
enum OAuthClientType: string
{
    case Public = 'public';
    case Confidential = 'confidential';
}
```

---

# 608. Public clients

Los clientes públicos no podrán mantener un secreto de forma confiable.

Ejemplos:

* SPA;
* aplicación móvil;
* aplicación desktop;
* CLI distribuido.

---

# 609. Confidential clients

Los clientes confidenciales podrán autenticarse mediante:

* client secret;
* private key JWT;
* mTLS;
* mecanismo equivalente.

---

# 610. OAuthClient

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

# 611. OAuthClientStatus

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

# 612. Client registration

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

# 613. Dynamic client registration

Solo deberá habilitarse con política explícita y controles estrictos.

---

# 614. Client metadata immutability

Los cambios críticos deberán versionarse y auditarse.

---

# 615. Authorization grant types

VoltStack podrá soportar:

* Authorization Code;
* Client Credentials;
* Device Authorization;
* Token Exchange;
* Refresh Token.

---

# 616. Deprecated grant types

Deberán deshabilitarse por defecto:

* Implicit Grant;
* Resource Owner Password Credentials.

---

# 617. Authorization Code flow

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

# 618. AuthorizationRequest

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

# 619. Authorization request validation

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

# 620. Redirect URI exact matching

La URI deberá coincidir exactamente con una URI registrada.

---

# 621. Redirect URI normalization prohibition

No deberán realizarse normalizaciones permisivas que cambien la semántica.

---

# 622. Redirect URI restrictions

No deberán permitirse:

* fragments;
* wildcards generales;
* credenciales embebidas;
* esquemas inseguros;
* open redirects;
* hosts no registrados.

---

# 623. Localhost redirects

Para aplicaciones nativas podrán permitirse loopback redirects con reglas específicas.

---

# 624. Custom URI schemes

Los custom schemes deberán seguir políticas estrictas y preferir esquemas reclamados de forma verificable cuando sea posible.

---

# 625. State parameter

`state` deberá proteger la correlación del flujo y reducir CSRF.

---

# 626. State generation

Deberá ser:

* aleatorio;
* opaco;
* de alta entropía;
* de un solo uso;
* vinculado a sesión;
* temporal.

---

# 627. State storage

El cliente deberá correlacionarlo con el flujo iniciado.

El Authorization Server también podrá mantener binding adicional.

---

# 628. State mismatch

Un mismatch deberá cancelar completamente el flujo.

---

# 629. PKCE

PKCE deberá ser obligatorio para clientes públicos y recomendado para todos los clientes.

---

# 630. Code challenge methods

VoltStack deberá permitir:

```php
enum PkceCodeChallengeMethod: string
{
    case S256 = 'S256';
}
```

El método `plain` deberá deshabilitarse por defecto.

---

# 631. Code verifier

El verifier deberá:

* ser aleatorio;
* poseer suficiente entropía;
* respetar longitud permitida;
* nunca enviarse en la autorización inicial.

---

# 632. Code challenge binding

El authorization code deberá vincularse al challenge emitido.

---

# 633. AuthorizationCode

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

# 634. Authorization code properties

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

# 635. Authorization code storage

Se deberá almacenar solo una representación derivada cuando sea posible.

---

# 636. Code consumption

El consumo deberá ser atómico.

---

# 637. Code replay

Reutilizar un code consumido deberá generar un evento de seguridad.

---

# 638. Code lifetime

La vida útil deberá ser corta.

---

# 639. Token endpoint

El endpoint deberá aceptar únicamente:

* TLS;
* métodos permitidos;
* content type esperado;
* client authentication cuando aplique;
* parámetros estrictamente validados.

---

# 640. TokenRequest

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

# 641. OAuthGrantType

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

# 642. Client authentication

Clientes confidenciales deberán autenticarse en el token endpoint.

---

# 643. ClientSecretBasic

Podrá soportarse para compatibilidad controlada.

---

# 644. Client secret storage

Los secretos deberán almacenarse:

* como digest;
* en secret manager;
* con versionado;
* con rotación;
* con auditoría.

---

# 645. Private key JWT

Deberá preferirse para clientes confidenciales de alto valor.

---

# 646. Client assertion validation

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

# 647. Client assertion replay registry

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

# 648. mTLS client authentication

VoltStack podrá soportar autenticación del cliente mediante certificado TLS.

---

# 649. Client authentication downgrade

Un cliente registrado con método fuerte no deberá usar silenciosamente uno más débil.

---

# 650. Access token

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

# 651. Access token formats

VoltStack podrá soportar:

* opaque tokens;
* JWT access tokens.

---

# 652. Opaque token advantages

Ventajas:

* revocación inmediata;
* menor exposición;
* introspection central;
* claims dinámicos;
* menor riesgo de uso fuera de contexto.

---

# 653. JWT access token advantages

Ventajas:

* validación local;
* menor dependencia en tiempo de request;
* escalabilidad;
* interoperabilidad.

---

# 654. JWT access token risks

Riesgos:

* revocación difícil;
* claims obsoletos;
* exposición de metadata;
* confusion entre tipos de token;
* dependencia de claves.

---

# 655. Access token type separation

Los access tokens deberán distinguirse claramente de:

* ID tokens;
* refresh tokens;
* authorization codes;
* session tokens.

---

# 656. typ header

Los JWT deberán usar un `typ` explícito cuando el perfil lo requiera.

---

# 657. Token issuer validation

El Resource Server deberá validar el issuer exacto esperado.

---

# 658. Audience validation

Un token deberá ser válido únicamente para audiences autorizados.

---

# 659. Audience confusion prevention

Un servicio no deberá aceptar un token emitido para otro recurso.

---

# 660. Scope model

Los scopes deberán representar privilegios delegados de forma clara y limitada.

---

# 661. Scope naming

Ejemplo:

```text
profile.read
billing.read
billing.write
files.download
tenant.admin
```

---

# 662. Scope hierarchy

Las jerarquías implícitas deberán evitarse o documentarse claramente.

---

# 663. ScopeRegistry

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

# 664. OAuthScopeDefinition

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

# 665. Scope minimization

Solo deberán emitirse scopes:

* solicitados;
* permitidos al client;
* autorizados por el usuario;
* compatibles con la policy;
* compatibles con el audience.

---

# 666. Incremental authorization

VoltStack podrá solicitar scopes adicionales solo cuando sean necesarios.

---

# 667. Consent architecture

El consentimiento deberá ser:

* comprensible;
* específico;
* vinculado al client;
* vinculado a scopes;
* revocable;
* auditable.

---

# 668. ConsentRecord

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

# 669. ConsentStatus

```php
enum ConsentStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
```

---

# 670. First-party clients

El consentimiento podrá omitirse solo para clientes first-party explícitamente confiables y gobernados.

---

# 671. Administrative consent

En entornos empresariales podrá existir consentimiento otorgado por administrador.

---

# 672. Consent phishing protection

La interfaz deberá mostrar claramente:

* nombre del client;
* publisher;
* scopes;
* tenant;
* destino de los datos;
* riesgos.

---

# 673. Refresh tokens

Los refresh tokens deberán tratarse como credenciales de alta sensibilidad.

---

# 674. RefreshToken

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

# 675. RefreshTokenState

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

# 676. Refresh token rotation

Los refresh tokens deberán rotarse en clientes públicos.

---

# 677. Refresh token family

Una familia deberá permitir detectar reuse.

---

# 678. Refresh token replay

Ante reuse confirmado deberá:

* revocarse la familia;
* invalidar descendientes;
* elevar riesgo;
* notificar;
* registrar incidente.

---

# 679. Refresh token scope

Un refresh no deberá ampliar scopes.

---

# 680. Refresh token audience

El refresh no deberá producir tokens para audiences no autorizados originalmente.

---

# 681. Refresh token expiration

Deberán existir:

* expiración absoluta;
* posible expiración por inactividad;
* revocación administrativa;
* revocación por consentimiento.

---

# 682. Token introspection

Los tokens opacos deberán poder validarse mediante introspection.

---

# 683. TokenIntrospectionService

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

# 684. TokenIntrospectionResult

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

# 685. Introspection authorization

Solo Resource Servers autorizados deberán poder consultar tokens relevantes.

---

# 686. Introspection minimization

La respuesta deberá exponer únicamente claims necesarios.

---

# 687. Token revocation

VoltStack deberá ofrecer revocación para:

* access tokens cuando aplique;
* refresh tokens;
* token families;
* consent;
* client sessions.

---

# 688. TokenRevocationService

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

# 689. Revocation endpoint privacy

El endpoint deberá responder de forma idempotente y evitar revelar si el token existía.

---

# 690. Sender-constrained tokens

Un token podrá vincularse criptográficamente al cliente que lo presenta.

---

# 691. SenderConstraint

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

# 692. SenderConstraintType

```php
enum SenderConstraintType: string
{
    case Dpop = 'dpop';
    case MutualTls = 'mutual_tls';
}
```

---

# 693. DPoP

DPoP permitirá vincular el token a una clave pública controlada por el cliente.

---

# 694. DPoP proof validation

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

# 695. DPoP replay registry

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

# 696. mTLS-bound tokens

Los tokens vinculados a mTLS deberán verificar el certificado presentado por el cliente.

---

# 697. OAuth mix-up prevention

Se deberá validar estrictamente:

* issuer;
* authorization endpoint;
* token endpoint;
* client;
* state;
* redirect URI;
* metadata del proveedor.

---

# 698. Native application security

Aplicaciones nativas deberán usar:

* Authorization Code;
* PKCE;
* external user agent;
* redirect seguro;
* no embedded browser;
* almacenamiento protegido.

---

# 699. OAuth security events

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

# 700. Resultado de esta entrega

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

# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 8 de varias
**Cobertura:** Secciones **701–800**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 7`

---

# 701. OpenID Connect Security Architecture

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

# 702. OIDC security goals

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

# 703. OIDC threat model

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

# 704. OIDC actors

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

# 705. OpenID Provider

El OpenID Provider deberá:

* autenticar al usuario;
* emitir ID Tokens;
* publicar metadata;
* publicar claves;
* exponer UserInfo cuando corresponda;
* soportar logout según capacidades;
* declarar assurance y métodos utilizados.

---

# 706. Relying Party

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

# 707. OpenIdProviderDefinition

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

# 708. OpenIdProviderStatus

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

# 709. Provider registration

Un proveedor deberá registrarse mediante configuración confiable o discovery validado.

---

# 710. Provider trust modes

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

# 711. Static provider configuration

La configuración estática deberá preferirse cuando:

* el proveedor sea conocido;
* exista alta sensibilidad;
* se requiera control estricto;
* el entorno sea empresarial.

---

# 712. Discovery architecture

OIDC Discovery podrá resolver metadata desde el issuer.

---

# 713. OpenIdProviderDiscoveryService

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

# 714. Discovery validation

La metadata deberá validarse antes de aceptarse.

---

# 715. Issuer exact match

El valor `issuer` del documento descubierto deberá coincidir exactamente con el issuer solicitado.

---

# 716. Discovery transport security

La discovery deberá usar:

* HTTPS;
* validación TLS;
* redirects controlados;
* límites de tamaño;
* timeouts;
* protección SSRF.

---

# 717. Discovery SSRF protection

No deberán permitirse issuers que resuelvan hacia:

* localhost;
* loopback;
* link-local;
* metadata services;
* redes privadas no autorizadas;
* hosts internos.

---

# 718. Discovery cache

La metadata podrá cachearse con:

* expiración;
* versionado;
* revalidación;
* invalidación;
* fallback seguro.

---

# 719. Metadata change detection

Cambios en endpoints o algoritmos deberán producir:

* evento;
* revisión;
* posible suspensión;
* invalidación de cache.

---

# 720. OIDC Authentication Request

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

# 721. Required scopes

Para autenticación OIDC deberá solicitarse:

```text
openid
```

Sin ese scope, el flujo deberá tratarse como OAuth ordinario.

---

# 722. Scope minimization

Scopes adicionales deberán solicitarse solo cuando sean necesarios.

Ejemplos:

* `profile`;
* `email`;
* `phone`;
* scopes empresariales específicos.

---

# 723. Response type

El flujo recomendado será:

```text
response_type=code
```

junto con PKCE.

---

# 724. Hybrid and implicit flows

Los flujos hybrid e implicit deberán deshabilitarse por defecto.

---

# 725. Authorization Code with OIDC

El flujo deberá reutilizar todas las protecciones OAuth:

* exact redirect URI;
* state;
* PKCE;
* client authentication;
* code binding;
* token endpoint security.

---

# 726. Nonce

El nonce vincula el ID Token con la autenticación iniciada.

---

# 727. Nonce generation

El nonce deberá ser:

* aleatorio;
* opaco;
* de alta entropía;
* temporal;
* de un solo uso;
* vinculado al flujo.

---

# 728. Nonce storage

Deberá almacenarse en el estado de autenticación local.

---

# 729. Nonce validation

El nonce del ID Token deberá coincidir exactamente con el emitido.

---

# 730. Nonce reuse

La reutilización deberá considerarse replay o corrupción del flujo.

---

# 731. OIDC flow state

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

# 732. Flow state consumption

El estado deberá consumirse atómicamente después del callback.

---

# 733. Prompt parameter

VoltStack podrá utilizar:

* `none`;
* `login`;
* `consent`;
* `select_account`.

La selección deberá depender de policy.

---

# 734. Silent authentication

`prompt=none` deberá manejar cuidadosamente errores como:

* login required;
* consent required;
* interaction required.

---

# 735. max_age

`max_age` permitirá exigir autenticación reciente en el proveedor.

---

# 736. auth_time validation

Cuando se solicite `max_age`, el ID Token deberá incluir y validar `auth_time`.

---

# 737. Authentication Context Class Reference

`acr` representa el nivel o contexto de autenticación aplicado por el proveedor.

---

# 738. AcrValue

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

# 739. acr mapping

Cada proveedor deberá tener un mapping explícito entre `acr` externo y assurance interno.

---

# 740. Unknown acr

Un valor desconocido no deberá mapearse automáticamente al nivel más alto.

---

# 741. Authentication Methods References

`amr` describe métodos utilizados durante autenticación.

Ejemplos:

* password;
* OTP;
* MFA;
* hardware key;
* biometric;
* federated.

---

# 742. AmrMapper

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

# 743. amr limitations

`amr` deberá tratarse como declaración del proveedor, no como evidencia criptográfica directa de cada factor.

---

# 744. OIDC ID Token

El ID Token representa una declaración firmada sobre un evento de autenticación.

---

# 745. IdTokenClaims

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

# 746. ID Token type separation

Un ID Token no deberá utilizarse como access token.

---

# 747. ID Token verifier

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

# 748. IdTokenVerificationContext

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

# 749. ID Token validation pipeline

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

# 750. Algorithm allowlist

Solo deberán aceptarse algoritmos registrados para ese proveedor y cliente.

---

# 751. none algorithm prohibition

`alg=none` deberá rechazarse.

---

# 752. Symmetric algorithm restrictions

Los algoritmos simétricos deberán evitarse para proveedores externos salvo configuración explícita y justificada.

---

# 753. Algorithm confusion prevention

La clave y algoritmo deberán validarse como una combinación permitida.

---

# 754. Issuer validation

El issuer del token deberá coincidir exactamente con el proveedor configurado.

---

# 755. Audience validation

El `aud` deberá contener el client ID de VoltStack.

---

# 756. Multiple audiences

Cuando existan múltiples audiences, deberá validarse `azp` cuando el perfil lo requiera.

---

# 757. Authorized party

`azp` deberá coincidir con el cliente autorizado esperado.

---

# 758. Expiration validation

El ID Token deberá rechazarse después de `exp`.

---

# 759. Issued-at validation

`iat` no deberá ubicarse irrazonablemente en el futuro.

---

# 760. Clock skew

La tolerancia deberá ser pequeña, configurable y consistente.

---

# 761. Token replay registry

Para flujos de alto riesgo podrá registrarse el hash del ID Token o identificador derivado.

---

# 762. Subject identifier

`sub` será el identificador principal del usuario dentro del issuer.

---

# 763. External identity key

Una identidad externa deberá identificarse mediante:

```text
issuer + subject
```

Nunca solo mediante email.

---

# 764. ExternalIdentityIdentifier

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

# 765. Subject stability

El subject deberá considerarse opaco y estable según el contrato del proveedor.

---

# 766. Public subject identifiers

Un public subject puede ser igual para múltiples clientes del mismo proveedor.

---

# 767. Pairwise subject identifiers

Un pairwise subject puede variar por sector o cliente para reducir correlación.

---

# 768. Pairwise subject handling

VoltStack no deberá intentar derivar relaciones entre pairwise subjects sin soporte explícito del proveedor.

---

# 769. Sector identifier

Cuando aplique, el sector identifier deberá validarse conforme a la configuración registrada.

---

# 770. UserInfo endpoint

El UserInfo endpoint podrá proporcionar claims adicionales autorizados.

---

# 771. UserInfo request

La petición deberá usar un access token válido destinado al proveedor correspondiente.

---

# 772. UserInfo response validation

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

# 773. UserInfo subject match

El `sub` de UserInfo deberá coincidir con el `sub` del ID Token.

---

# 774. Subject mismatch

Una discrepancia deberá considerarse un evento crítico.

---

# 775. Signed UserInfo

Cuando el proveedor soporte respuestas firmadas, la política podrá exigirlas.

---

# 776. Claims mapping architecture

Los claims externos deberán transformarse mediante un mapper específico por proveedor.

---

# 777. ExternalClaimsMapper

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

# 778. ExternalIdentityProfile

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

# 779. Claims trust classification

Cada claim deberá clasificarse como:

* verified;
* provider-asserted;
* derived;
* untrusted;
* informational.

---

# 780. Email claim risk

El email no deberá utilizarse por sí solo para vincular automáticamente una identidad externa con una cuenta existente.

---

# 781. email_verified limitations

`email_verified=true` solo indica que el proveedor afirma haber verificado el email bajo su propio proceso.

---

# 782. Email authority policy

VoltStack deberá definir qué proveedores son autoridades aceptadas para cada dominio o tenant.

---

# 783. Claim conflict handling

Cuando un claim externo contradiga datos locales, la policy deberá decidir si:

* preservar dato local;
* actualizar dato;
* solicitar confirmación;
* abrir revisión;
* rechazar login.

---

# 784. Group and role claims

Los grupos externos no deberán convertirse directamente en roles internos sin mapping autorizado.

---

# 785. ExternalGroupMapper

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

# 786. Authorization mapping restrictions

El mapping deberá:

* usar allowlists;
* estar versionado;
* ser auditable;
* evitar comodines peligrosos;
* respetar tenant boundaries.

---

# 787. External identity record

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

# 788. ExternalIdentityStatus

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

# 789. Account linking architecture

Account linking conecta una identidad externa con una identidad local existente.

---

# 790. Account linking prerequisites

El linking deberá requerir:

* sesión local autenticada;
* autenticación reciente;
* assurance suficiente;
* autenticación exitosa con el proveedor;
* confirmación explícita;
* ausencia de conflictos;
* riesgo aceptable.

---

# 791. AccountLinkingService

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

# 792. Unsafe implicit linking

No deberá vincularse automáticamente una cuenta únicamente porque ambos perfiles compartan email.

---

# 793. Existing external identity collision

Una identidad externa ya vinculada a otra cuenta deberá provocar rechazo y evento de seguridad.

---

# 794. Link uniqueness

La combinación:

```text
provider + issuer + subject
```

deberá ser única dentro del ámbito aplicable.

---

# 795. Account unlinking

Desvincular un proveedor deberá requerir:

* autenticación reciente;
* factor alternativo viable;
* protección contra self-lockout;
* auditoría;
* notificación.

---

# 796. Federated login session issuance

Una autenticación federada válida deberá convertirse en un `AuthenticationResult` interno antes de emitir sesión.

---

# 797. FederatedAuthenticationResult

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

# 798. OIDC logout architecture

VoltStack deberá soportar, según capacidades del proveedor:

* local logout;
* RP-initiated logout;
* front-channel logout;
* back-channel logout;
* global federated logout.

---

# 799. Provider compromise response

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

# 800. Resultado de esta entrega

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

# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 9 de varias
**Cobertura:** Secciones **801–900**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 8`

---

# 801. OIDC Cryptographic Key Architecture

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

# 802. OIDC key trust model

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

# 803. JSON Web Key Set

Un JWKS representa una colección de claves públicas expresadas como objetos JWK.

El parámetro `kid` puede utilizarse para seleccionar una clave dentro del conjunto durante procesos como la rotación, aunque su estructura interna no está estandarizada y no deberá interpretarse como prueba de confianza.

---

# 804. JwkSet

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

# 805. JsonWebKey

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

# 806. Supported key types

VoltStack deberá mantener un registry explícito de tipos de clave permitidos.

Ejemplos:

* RSA;
* EC;
* OKP;
* tipos adicionales aprobados por el perfil criptográfico.

---

# 807. JwkTypeRegistry

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

# 808. Unknown key types

Una clave con tipo desconocido deberá:

* ignorarse para selección;
* generar una advertencia interna;
* no provocar fallback inseguro;
* no deshabilitar la validación del resto del conjunto.

---

# 809. JWK structural validation

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

# 810. Private key material prohibition

Un JWKS remoto utilizado para validación no deberá contener ni aceptar material de clave privada.

Si aparece, deberá:

* rechazarse;
* registrarse como incidente;
* impedir la incorporación de la clave;
* elevar la severidad del proveedor.

---

# 811. JWK use validation

Cuando esté presente, `use` deberá ser compatible con:

```text
sig
```

para validación de firmas.

---

# 812. JWK key operations

Cuando exista `key_ops`, deberá incluir una operación compatible con verificación.

Por ejemplo:

```text
verify
```

---

# 813. Conflicting key metadata

Una clave deberá rechazarse cuando:

* `use` indique cifrado;
* `key_ops` no permita verificación;
* `alg` contradiga el algoritmo del token;
* el tipo de clave sea incompatible con el algoritmo.

---

# 814. Algorithm-to-key compatibility

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

# 815. Algorithm confusion prevention

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

# 816. JWK Set retrieval

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

# 817. JwkSetRetrievalPolicy

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

# 818. JWKS URI trust

El `jwks_uri` deberá obtenerse de:

* configuración estática confiable;
* discovery validado;
* federation metadata verificada.

Nunca deberá aceptarse directamente desde un parámetro de token.

---

# 819. Embedded JWK headers

Headers como `jwk`, `jku` o referencias equivalentes no deberán habilitar selección arbitraria de claves.

---

# 820. Remote key URL restrictions

VoltStack deberá rechazar URLs de claves dirigidas hacia:

* localhost;
* loopback;
* link-local;
* servicios de metadata;
* redes internas no permitidas;
* esquemas distintos de HTTPS;
* hosts no asociados al proveedor.

---

# 821. JWKS SSRF protection

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

# 822. Redirect validation

Cada redirect deberá volver a validar:

* scheme;
* hostname;
* IP resuelta;
* red de destino;
* relación con el issuer;
* límite de redirects.

---

# 823. DNS rebinding protection

La resolución deberá proteger contra cambios entre:

* validación inicial;
* conexión;
* redirects;
* reutilización de conexión.

---

# 824. JWKS response validation

La respuesta deberá comprobar:

* código HTTP;
* content type;
* tamaño;
* JSON válido;
* propiedad `keys`;
* número máximo de claves;
* ausencia de duplicados peligrosos.

---

# 825. Maximum key count

El límite de claves evitará:

* consumo excesivo de memoria;
* ataques de CPU;
* búsquedas no acotadas;
* key flooding.

---

# 826. Duplicate kid handling

Dos claves con el mismo `kid` no deberán provocar selección arbitraria.

---

# 827. Duplicate key selection policy

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

# 828. Key identifier semantics

`kid` deberá considerarse un selector, no un identificador criptográfico único global.

---

# 829. JWK thumbprint

VoltStack podrá calcular un thumbprint canónico para identificar material criptográfico independientemente del `kid`.

El procedimiento deberá seguir un algoritmo estable de canonicalización y hash.

---

# 830. JwkThumbprint

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

# 831. JwkThumbprintCalculator

```php
interface JwkThumbprintCalculatorInterface
{
    public function calculate(
        JsonWebKey $key
    ): JwkThumbprint;
}
```

---

# 832. Key selection input

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

# 833. JwkSelector

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

# 834. JwkSelectionResult

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

# 835. JwkSelectionStatus

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

# 836. Unknown kid handling

Cuando el token incluya un `kid` desconocido, VoltStack podrá:

1. consultar el cache actual;
2. ejecutar un refresh controlado;
3. volver a intentar la selección una sola vez;
4. rechazar el token si la clave continúa ausente.

---

# 837. Refresh amplification protection

Un atacante no deberá poder provocar una descarga de JWKS por cada token con `kid` aleatorio.

---

# 838. Unknown key rate limiting

El refresh deberá limitarse por:

* issuer;
* provider;
* tenant;
* ventana temporal;
* último refresh;
* circuit breaker.

---

# 839. Negative key cache

VoltStack podrá mantener temporalmente un registro de `kid` desconocidos para evitar consultas repetidas.

---

# 840. JwksCache

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

# 841. JWKS cache lifetime

El cache deberá considerar:

* headers HTTP;
* política del proveedor;
* máximo interno;
* mínimo de seguridad;
* historial de rotación;
* criticidad del tenant.

---

# 842. Stale key policy

Una clave cacheada vencida no deberá utilizarse indefinidamente.

---

# 843. Stale-while-revalidate

Podrá permitirse una ventana corta cuando:

* el proveedor esté temporalmente indisponible;
* la clave ya sea conocida;
* el token haya sido emitido antes de la expiración del cache;
* la política lo permita;
* no exista señal de compromiso.

---

# 844. Fail-open prohibition

Un error al descargar JWKS no deberá provocar:

* omitir la firma;
* aceptar cualquier clave;
* aceptar algoritmos alternativos;
* ignorar issuer o audience.

---

# 845. Key rotation architecture

Los proveedores podrán mantener simultáneamente:

* clave activa;
* clave siguiente;
* claves anteriores en periodo de validación;
* claves revocadas.

---

# 846. Key lifecycle states

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

# 847. Pre-published keys

Una clave futura podrá publicarse antes de utilizarse para permitir propagación de cache.

---

# 848. Overlapping rotation window

Durante una rotación ordinaria podrán coexistir claves anteriores y nuevas.

---

# 849. Retiring keys

Una clave retirada podrá seguir siendo necesaria para validar tokens no expirados emitidos previamente.

---

# 850. Key retention calculation

La retención deberá considerar:

* vida máxima del ID Token;
* clock skew;
* retraso de entrega;
* cache intermedio;
* logout tokens;
* tokens emitidos antes del rollover.

---

# 851. KeyRotationTracker

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

# 852. Key rotation assessment

Deberá detectar:

* nuevas claves;
* claves eliminadas;
* cambio de algoritmo;
* cambio de tipo;
* reutilización de `kid`;
* reemplazo de material con mismo `kid`;
* reducción inesperada del conjunto.

---

# 853. Same kid with different key material

El reemplazo de material criptográfico manteniendo el mismo `kid` deberá generar una alerta de alta severidad.

---

# 854. Emergency key rollover

Una rotación de emergencia podrá ocurrir por:

* compromiso;
* exposición;
* vulnerabilidad del algoritmo;
* error operacional;
* revocación de certificado.

---

# 855. Emergency rollover response

VoltStack deberá poder:

* invalidar el cache;
* descargar el conjunto nuevo;
* marcar claves comprometidas;
* rechazar tokens firmados con ellas;
* revocar sesiones derivadas;
* requerir nueva autenticación;
* notificar a tenants.

---

# 856. Key compromise registry

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

# 857. Session provenance

Las sesiones federadas deberán conservar:

* issuer;
* subject;
* provider ID;
* key thumbprint;
* ID Token issuance time;
* authentication time;
* `sid` cuando exista.

---

# 858. Federated session registry

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

# 859. FederatedSessionBinding

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

# 860. OIDC sid claim

El claim `sid` podrá representar una sesión del usuario en el proveedor.

Deberá tratarse como un identificador opaco dentro del contexto del issuer y cliente.

---

# 861. sid uniqueness

`sid` no deberá asumirse globalmente único.

La clave interna deberá incorporar:

```text
issuer + client + sid
```

---

# 862. Local-to-federated session mapping

Una sesión del proveedor podrá corresponder a:

* una sesión local;
* varias pestañas;
* varias sesiones locales;
* varias aplicaciones del mismo ecosistema.

La política de revocación deberá definir el alcance.

---

# 863. RP-Initiated Logout

VoltStack podrá solicitar al proveedor que finalice la sesión del usuario mediante el endpoint de terminación registrado.

Este perfil permite que un Relying Party solicite explícitamente al OpenID Provider el logout del usuario.

---

# 864. RpInitiatedLogoutRequest

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

# 865. id_token_hint handling

Cuando se utilice, deberá corresponder a:

* provider esperado;
* client esperado;
* identidad autenticada;
* sesión federada relacionada.

---

# 866. Post-logout redirect URI

La URI deberá estar registrada y validarse mediante coincidencia exacta.

---

# 867. Logout state

El parámetro `state` deberá:

* ser aleatorio;
* vincularse al logout;
* expirar;
* consumirse una sola vez;
* proteger el retorno al RP.

---

# 868. Local-first logout

VoltStack deberá definir si:

* revoca primero la sesión local;
* solicita primero logout al proveedor;
* ejecuta ambas operaciones transaccionalmente cuando sea posible.

Por defecto, la sesión local deberá revocarse incluso si el proveedor no responde.

---

# 869. Front-Channel Logout

El front-channel utiliza el navegador del usuario para transmitir la solicitud de logout entre el proveedor y los Relying Parties.

---

# 870. Front-channel risks

Este mecanismo depende de:

* navegador;
* cookies;
* iframes o navegación;
* políticas de terceros;
* disponibilidad del RP;
* restricciones de tracking.

---

# 871. FrontChannelLogoutRequest

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

# 872. Front-channel validation

VoltStack deberá validar:

* issuer;
* client context;
* `sid` cuando exista;
* origen esperado;
* parámetros permitidos;
* correlación con sesiones federadas.

---

# 873. Front-channel idempotency

El logout deberá ser idempotente.

Una sesión ya revocada deberá producir una respuesta segura sin error sensible.

---

# 874. Third-party cookie limitations

VoltStack no deberá asumir que los navegadores siempre enviarán cookies en contextos cross-site.

---

# 875. Front-channel fallback

Cuando el front-channel no pueda resolver la sesión local, podrá:

* mostrar una página de confirmación;
* requerir navegación top-level;
* depender del back-channel;
* invalidar mediante subject cuando la política lo permita.

---

# 876. Back-Channel Logout

El back-channel utiliza comunicación directa entre el proveedor y el RP, sin depender del navegador.

Después de validar un Logout Token, el RP debe localizar y limpiar las sesiones identificadas por `iss`, `sub` y, opcionalmente, `sid`.

---

# 877. BackChannelLogoutEndpoint

```php
interface BackChannelLogoutEndpointInterface
{
    public function handle(
        BackChannelLogoutRequest $request
    ): BackChannelLogoutResult;
}
```

---

# 878. Logout Token

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

# 879. Logout Token validation

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

# 880. Logout Token replay registry

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

# 881. Back-channel logout scope

Cuando exista `sid`, deberán revocarse las sesiones vinculadas a esa sesión del proveedor.

Cuando solo exista `sub`, la política podrá revocar todas las sesiones federadas del sujeto para ese issuer y client.

---

# 882. Back-channel endpoint security

El endpoint deberá:

* aceptar únicamente POST;
* limitar tamaño;
* validar content type;
* aplicar rate limiting;
* no requerir cookie del usuario;
* responder idempotentemente.

---

# 883. Logout delivery failure

Los fallos de logout federado deberán registrarse sin restaurar una sesión local ya revocada.

---

# 884. SAML 2.0 Security Foundations

VoltStack podrá soportar SAML 2.0 como protocolo empresarial de federación.

SAML utiliza assertions XML y define perfiles para Single Sign-On y Single Logout mediante distintos bindings.

---

# 885. SAML actors

```php
enum SamlActorRole: string
{
    case IdentityProvider = 'identity_provider';
    case ServiceProvider = 'service_provider';
    case Principal = 'principal';
}
```

---

# 886. SamlIdentityProviderDefinition

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

# 887. SAML metadata trust

La metadata deberá obtenerse mediante:

* configuración estática;
* archivo firmado;
* endpoint HTTPS aprobado;
* federation registry confiable.

---

# 888. SAML metadata validation

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

# 889. XML parser security

El parser deberá deshabilitar:

* entidades externas;
* DTD;
* expansión ilimitada;
* acceso a red;
* referencias externas;
* procesamiento XML innecesario.

---

# 890. SAML assertion validation

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

# 891. XML Signature Wrapping protection

VoltStack deberá:

* verificar el elemento exacto firmado;
* resolver IDs de manera única;
* impedir referencias duplicadas;
* no confiar en búsquedas XML ambiguas;
* consumir únicamente la assertion validada.

---

# 892. SAML replay registry

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

# 893. SAML account binding

La identidad externa deberá resolverse mediante:

```text
IdP entity ID + persistent NameID
```

o mediante un atributo estable explícitamente gobernado.

---

# 894. Email-based SAML linking prohibition

El email no deberá utilizarse automáticamente como identificador único de federación.

---

# 895. Just-In-Time Provisioning

JIT provisioning permitirá crear o actualizar una identidad local durante una autenticación federada válida.

---

# 896. JitProvisioningService

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

# 897. JIT provisioning policy

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

# 898. JIT least privilege

Una identidad creada mediante JIT deberá recibir el mínimo privilegio inicial.

Los grupos o atributos externos no deberán transformarse en privilegios administrativos sin mapping explícito.

---

# 899. SCIM Identity Lifecycle Foundations

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

# 900. Resultado de esta entrega

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

# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 10 de varias
**Cobertura:** Secciones **901–1000**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 9`

---

# 901. SCIM Service Provider Architecture

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

# 902. SCIM security goals

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

# 903. SCIM threat model

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

# 904. SCIM architectural components

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

# 905. ScimServiceProvider

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

# 906. ScimRequest

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

# 907. ScimHttpMethod

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

# 908. SCIM endpoint structure

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

# 909. Endpoint tenant isolation

Cada request deberá resolverse dentro de un tenant explícito.

La resolución podrá basarse en:

* hostname;
* client credential;
* endpoint dedicado;
* metadata del cliente;
* route binding.

---

# 910. Cross-tenant prohibition

Un cliente SCIM no deberá poder seleccionar libremente otro tenant mediante parámetros manipulables.

---

# 911. ScimClient

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

# 912. ScimClientStatus

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

# 913. SCIM authentication methods

VoltStack podrá soportar:

* OAuth 2.0 bearer tokens;
* sender-constrained access tokens;
* mTLS;
* private key JWT;
* static bearer tokens para compatibilidad limitada.

---

# 914. Static bearer token restrictions

Los tokens estáticos deberán:

* generarse con alta entropía;
* almacenarse como digest;
* poseer expiración;
* rotarse;
* limitarse a un tenant;
* limitarse a scopes;
* revocarse individualmente.

---

# 915. ScimClientCredential

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

# 916. ScimCredentialStatus

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

# 917. Credential rotation overlap

Durante una rotación podrá permitirse una ventana corta en la que dos credentials sean válidas.

La ventana deberá:

* ser explícita;
* expirar;
* auditarse;
* no extenderse indefinidamente.

---

# 918. SCIM authorization

La autenticación del cliente no implica autorización completa.

---

# 919. SCIM scopes

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

# 920. ScimAuthorizationPolicy

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

# 921. Operation-level authorization

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

# 922. Attribute-level authorization

Algunos clientes podrán editar solo atributos específicos.

Ejemplos:

* departamento;
* puesto;
* manager;
* número de empleado;
* estado activo.

---

# 923. Protected attributes

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

# 924. ScimAttributePolicy

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

# 925. SCIM schema architecture

VoltStack deberá mantener un registry de schemas soportados.

---

# 926. ScimSchemaRegistry

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

# 927. ScimSchemaDefinition

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

# 928. ScimAttributeDefinition

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

# 929. ScimAttributeType

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

# 930. ScimMutability

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

# 931. ScimReturnedBehavior

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

# 932. ScimUniqueness

```php
enum ScimUniqueness: string
{
    case None = 'none';
    case Server = 'server';
    case Global = 'global';
}
```

---

# 933. Schema validation pipeline

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

# 934. Unknown attributes

La política podrá:

* rechazarlos;
* ignorarlos;
* almacenarlos en extensión controlada;
* registrar advertencia.

No deberán incorporarse arbitrariamente al modelo interno.

---

# 935. Extension schemas

VoltStack podrá soportar extensiones:

* empresariales;
* específicas de tenant;
* específicas de industria;
* personalizadas por integración.

---

# 936. Extension governance

Toda extensión deberá:

* tener URN única;
* estar versionada;
* definir mutabilidad;
* definir sensibilidad;
* mapearse explícitamente al dominio.

---

# 937. Resource types

VoltStack deberá publicar los tipos de recurso disponibles.

---

# 938. ScimResourceTypeDefinition

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

# 939. Core resource types

La primera implementación deberá soportar:

* `User`;
* `Group`.

---

# 940. ServiceProviderConfig

El endpoint deberá declarar capacidades reales del servidor.

---

# 941. ScimServiceProviderConfig

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

# 942. Capability honesty

VoltStack no deberá anunciar capacidades que no implemente completamente.

---

# 943. SCIM User resource

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

# 944. userName semantics

`userName` deberá:

* ser único dentro del ámbito configurado;
* respetar normalización definida;
* no asumirse necesariamente igual al email;
* tratarse como identificador de login solo si la política lo establece.

---

# 945. externalId

`externalId` representa el identificador del recurso en el sistema del cliente SCIM.

---

# 946. External identifier scope

`externalId` deberá considerarse único dentro de:

```text
tenant + SCIM client + resource type
```

No deberá asumirse único globalmente.

---

# 947. ProvisioningCorrelationKey

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

# 948. Resource correlation order

La resolución de un usuario podrá considerar:

1. SCIM resource ID;
2. provisioning correlation key;
3. identidad externa previamente vinculada;
4. username según política;
5. email únicamente en flujo de revisión explícito.

---

# 949. Unsafe email correlation

No deberá fusionarse automáticamente una cuenta local por coincidencia de email.

---

# 950. Correlation conflicts

Cuando varias identidades coincidan, la operación deberá:

* detenerse;
* generar conflicto;
* evitar actualización parcial;
* requerir resolución administrativa;
* auditarse.

---

# 951. ProvisioningConflict

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

# 952. ProvisioningConflictReason

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

# 953. User creation workflow

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

# 954. Initial account state

Una cuenta aprovisionada podrá iniciar como:

* active;
* inactive;
* pending;
* invitation-required;
* approval-required.

---

# 955. ProvisionedIdentityState

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

# 956. Credential creation prohibition

SCIM no deberá crear contraseñas, passkeys ni recovery codes directamente salvo un perfil separado y explícitamente autorizado.

---

# 957. Invitation workflow

Una identidad creada mediante SCIM podrá recibir una invitación segura para:

* verificar contacto;
* registrar passkey;
* configurar MFA;
* aceptar términos;
* completar perfil.

---

# 958. User replacement with PUT

`PUT` deberá interpretarse como reemplazo completo del recurso editable.

---

# 959. PUT preservation rules

Los atributos:

* read-only;
* server-managed;
* protected;
* no incluidos por diseño;

no deberán eliminarse accidentalmente.

---

# 960. Lost attribute prevention

La transformación de `PUT` deberá diferenciar:

* atributo omitido;
* atributo vacío;
* atributo nulo;
* atributo no retornado al cliente;
* atributo no editable.

---

# 961. SCIM PATCH architecture

PATCH deberá ejecutarse mediante operaciones estructuradas.

---

# 962. ScimPatchOperation

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

# 963. ScimPatchOperationType

```php
enum ScimPatchOperationType: string
{
    case Add = 'add';
    case Replace = 'replace';
    case Remove = 'remove';
}
```

---

# 964. PATCH validation

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

# 965. SCIM path parser

```php
interface ScimPathParserInterface
{
    public function parse(string $path): ScimPath;
}
```

---

# 966. SCIM path safety

El parser deberá impedir:

* acceso a atributos no registrados;
* traversal conceptual;
* filtros no acotados;
* expresiones ambiguas;
* modificación de atributos protegidos.

---

# 967. PATCH atomicity

Todas las operaciones de un PATCH deberán aplicarse atómicamente.

---

# 968. PATCH rollback

Si una operación falla:

* no deberá persistirse ninguna modificación;
* deberá devolverse error consistente;
* deberá registrarse la causa.

---

# 969. Multi-valued attributes

La modificación de colecciones deberá preservar:

* claves internas;
* atributos primary;
* valores únicos;
* orden cuando sea significativo;
* referencias válidas.

---

# 970. Primary attribute enforcement

Solo un elemento podrá marcarse como `primary=true` dentro de una colección aplicable.

---

# 971. SCIM filtering

VoltStack podrá soportar búsqueda mediante filtros SCIM.

---

# 972. ScimFilterParser

```php
interface ScimFilterParserInterface
{
    public function parse(
        string $expression
    ): ScimFilterExpression;
}
```

---

# 973. Filter allowlist

Solo podrán filtrarse atributos declarados como filtrables.

---

# 974. Filter query compilation

Los filtros deberán compilarse mediante expresiones parametrizadas.

Nunca deberán concatenarse directamente en SQL.

---

# 975. Filter complexity limits

La política deberá limitar:

* profundidad;
* número de operadores;
* longitud;
* cantidad de atributos;
* complejidad lógica;
* costo estimado.

---

# 976. Pagination

Las respuestas de colección deberán soportar:

* `startIndex`;
* `count`;
* `totalResults`.

---

# 977. Pagination limits

El servidor deberá imponer un máximo de resultados por página.

---

# 978. Sorting

Si se habilita, solo deberá permitirse sobre atributos autorizados e indexados.

---

# 979. ETag architecture

VoltStack podrá utilizar ETags para control de concurrencia.

---

# 980. Resource version

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

# 981. Conditional updates

La política podrá exigir `If-Match` para prevenir lost updates.

---

# 982. Group resource

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

# 983. Group member references

Cada miembro deberá referenciar un recurso válido y permitido.

---

# 984. Group membership synchronization

La sincronización deberá distinguir:

* membresía administrada por SCIM;
* membresía local;
* membresía heredada;
* membresía dinámica;
* membresía protegida.

---

# 985. Membership source tracking

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

# 986. GroupMembershipSource

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

# 987. Membership ownership

Un cliente SCIM solo deberá poder retirar membresías que administra, salvo policy explícita.

---

# 988. Group-to-role mapping

Los grupos SCIM no deberán convertirse automáticamente en roles privilegiados.

---

# 989. Provisioning role mapper

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

# 990. Bulk operations

VoltStack podrá soportar operaciones masivas controladas.

---

# 991. ScimBulkRequest

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

# 992. Bulk limits

La política deberá limitar:

* número de operaciones;
* tamaño total;
* recursos por tipo;
* tiempo de ejecución;
* errores permitidos;
* concurrencia por cliente.

---

# 993. Bulk authorization

Cada operación deberá autorizarse individualmente.

La autorización del endpoint Bulk no implica autorización sobre todos los recursos.

---

# 994. Bulk transaction strategy

El sistema deberá declarar si el bulk es:

* completamente atómico;
* atómico por operación;
* atómico por grupo;
* best effort controlado.

---

# 995. Bulk reference resolution

Las referencias internas entre operaciones deberán:

* resolverse de forma determinista;
* evitar ciclos;
* respetar orden;
* impedir referencias cross-tenant.

---

# 996. User activation and deactivation

El atributo `active` deberá mapearse a una transición controlada del ciclo de vida.

---

# 997. Deactivation semantics

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

# 998. Deprovisioning workflow

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

# 999. SCIM audit events

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

# 1000. Resultado de esta entrega

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

# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 11 de varias
**Cobertura:** Secciones **1001–1100**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 10`

---

# 1001. Directory Synchronization Architecture

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

# 1002. Synchronization security goals

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

# 1003. Directory synchronization threat model

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

# 1004. Synchronization architectural components

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

# 1005. DirectoryConnector

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

# 1006. DirectoryConnectorCapabilities

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

# 1007. Directory source definition

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

# 1008. DirectorySourceType

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

# 1009. DirectorySourceStatus

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

# 1010. DirectoryTrustLevel

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

# 1011. Full synchronization

Una sincronización completa deberá enumerar el estado total conocido de una fuente.

---

# 1012. Full sync use cases

Deberá utilizarse para:

* carga inicial;
* recuperación después de pérdida de cursor;
* auditoría de consistencia;
* reconciliación periódica;
* migraciones;
* cambio de connector;
* investigación de drift.

---

# 1013. Full sync safeguards

Una sincronización completa deberá aplicar:

* snapshot ID;
* tenant binding;
* límites de volumen;
* detección de cambios anómalos;
* dry-run opcional;
* confirmación para operaciones destructivas;
* commit por fases.

---

# 1014. Incremental synchronization

La sincronización incremental deberá procesar únicamente cambios desde un checkpoint confiable.

---

# 1015. Incremental change types

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

# 1016. DirectoryChange

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

# 1017. Change ordering

Cuando la fuente proporcione orden causal, VoltStack deberá preservarlo.

---

# 1018. Out-of-order changes

El sistema deberá detectar cambios:

* anteriores al estado actual;
* duplicados;
* incompatibles con la versión;
* recibidos fuera de secuencia;
* referidos a recursos aún inexistentes.

---

# 1019. DirectorySyncCursor

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

# 1020. Cursor integrity

Los cursores deberán:

* almacenarse de forma protegida;
* vincularse a source y tenant;
* poseer integrity check;
* no ser manipulables por clientes;
* rotarse cuando corresponda.

---

# 1021. Cursor loss

Si el cursor se pierde o invalida, VoltStack deberá:

* detener incremental sync;
* evitar asumir continuidad;
* ejecutar full reconciliation;
* generar evento;
* reconstruir checkpoint.

---

# 1022. Change tokens

Una fuente podrá emitir change tokens opacos.

VoltStack no deberá interpretar su estructura interna.

---

# 1023. DirectorySyncCheckpoint

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

# 1024. DirectorySyncCheckpointState

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

# 1025. Checkpoint commit rule

El cursor nuevo no deberá persistirse como definitivo hasta que el batch haya sido aplicado correctamente.

---

# 1026. At-least-once delivery

VoltStack deberá diseñarse para tolerar que un cambio sea recibido más de una vez.

---

# 1027. Change idempotency

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

# 1028. Batch processing

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

# 1029. Batch atomicity

La estrategia deberá declararse como:

* transacción completa;
* transacción por recurso;
* transacción por subgrupo;
* best effort controlado.

---

# 1030. Partial batch failure

Ante fallo parcial, el sistema deberá evitar avanzar el checkpoint sobre cambios no aplicados.

---

# 1031. Retry policy

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

# 1032. Poison change handling

Un cambio que falla repetidamente deberá:

* aislarse;
* enviarse a dead-letter storage;
* no bloquear indefinidamente todo el directorio;
* generar alerta;
* requerir resolución.

---

# 1033. Identity reconciliation

La reconciliación determinará qué identidad local corresponde a un recurso externo.

---

# 1034. IdentityReconciliationService

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

# 1035. Correlation priorities

La correlación deberá priorizar:

1. vínculo persistente existente;
2. source ID más external ID;
3. identificador estable del proveedor;
4. identidad federada conocida;
5. regla administrativa explícita;
6. revisión manual.

---

# 1036. Email correlation restrictions

El email no deberá ser un criterio automático suficiente para fusionar identidades.

---

# 1037. IdentityReconciliationResult

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

# 1038. IdentityReconciliationStatus

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

# 1039. Source-of-truth policies

VoltStack deberá definir qué sistema es autoridad para cada tipo de dato.

---

# 1040. AttributeAuthority

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

# 1041. AttributeAuthorityMode

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

# 1042. Attribute ownership

Cada atributo deberá registrar:

* fuente propietaria;
* último escritor;
* versión;
* fecha de modificación;
* política;
* estado de conflicto.

---

# 1043. AttributeOwnershipRecord

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

# 1044. Protected local fields

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

# 1045. Field-level conflict resolution

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

# 1046. AttributeConflict

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

# 1047. Conflict resolution strategies

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

# 1048. Latest-write limitations

“Última escritura gana” no deberá usarse para atributos críticos sin versionado y autoridad explícita.

---

# 1049. Merge policies

La combinación deberá ser semántica y específica por tipo de atributo.

Ejemplos:

* teléfonos;
* direcciones;
* aliases;
* grupos;
* entitlements.

---

# 1050. Conflict auditability

Toda resolución automática deberá registrar:

* valores considerados;
* fuentes;
* política aplicada;
* resultado;
* versión resultante.

---

# 1051. Tombstones

Un tombstone representa la desaparición lógica de un recurso externo.

---

# 1052. DirectoryTombstone

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

# 1053. TombstoneState

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

# 1054. Tombstone retention

Los tombstones deberán conservarse durante un periodo suficiente para:

* evitar recreación accidental;
* detectar replay;
* soportar reprovisioning;
* reconciliar borrados tardíos;
* auditar.

---

# 1055. Soft delete

El soft delete deberá preservar el registro local y bloquear acceso.

---

# 1056. Hard delete

El hard delete deberá quedar reservado para:

* políticas legales;
* retención concluida;
* anonimización;
* datos no sujetos a conservación;
* aprobación administrativa.

---

# 1057. Delete mapping policy

Una eliminación externa no deberá traducirse automáticamente en hard delete local.

---

# 1058. Deletion safety threshold

VoltStack deberá detectar un volumen de eliminaciones superior al patrón esperado.

---

# 1059. Mass deletion protection

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

# 1060. Destructive sync pause

Al superar el umbral, el sync deberá:

* pausar;
* no avanzar cursor;
* generar alerta crítica;
* producir preview;
* requerir aprobación.

---

# 1061. Reprovisioning

Una identidad previamente desactivada podrá reaparecer en la fuente.

---

# 1062. Reprovisioning policy

La política deberá decidir si:

* reactiva la identidad existente;
* crea nueva membresía;
* conserva historial;
* exige revisión;
* bloquea por tombstone;
* restaura grupos administrados.

---

# 1063. Identity continuity

Cuando el identificador externo estable sea el mismo, deberá preferirse continuidad controlada sobre crear identidades duplicadas.

---

# 1064. Reprovisioning security review

Deberá requerirse revisión adicional si la identidad previa fue marcada como:

* comprometida;
* despedida por causa;
* bloqueada legalmente;
* revocada administrativamente;
* objeto de incidente.

---

# 1065. Orphan identity detection

Una identidad huérfana existe cuando conserva estado local pero ya no está respaldada por una fuente esperada.

---

# 1066. OrphanIdentityDetector

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

# 1067. Orphan classifications

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

# 1068. Orphan grace period

Una identidad no deberá desactivarse por una única ausencia temporal sin considerar:

* full snapshot completo;
* errores de fuente;
* filtros;
* paginación;
* grace period;
* excepciones.

---

# 1069. Stale account detection

Una cuenta stale podrá identificarse por:

* ausencia prolongada de sync;
* falta de login;
* membership expirada;
* fuente retirada;
* manager inválido;
* status inconsistente.

---

# 1070. StaleAccountAssessment

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

# 1071. Stale account remediation

Las acciones podrán incluir:

* notificar;
* restringir;
* requerir recertificación;
* desactivar;
* revocar sesiones;
* eliminar memberships;
* enviar a revisión.

---

# 1072. Manager hierarchy synchronization

VoltStack podrá sincronizar relaciones de manager-subordinate.

---

# 1073. ManagerRelationship

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

# 1074. Manager hierarchy validation

Deberá impedirse:

* auto-referencia;
* ciclos;
* manager inexistente;
* relación cross-tenant;
* manager desactivado según política;
* profundidad no acotada.

---

# 1075. Manager hierarchy security use

Una relación de manager no deberá otorgar automáticamente privilegios sensibles.

---

# 1076. Approval chain derivation

Cuando se use para aprobaciones, la jerarquía deberá:

* congelarse por versión;
* auditarse;
* validar vigencia;
* permitir excepciones;
* detectar conflictos de interés.

---

# 1077. Nested group synchronization

VoltStack podrá sincronizar grupos que contienen otros grupos.

---

# 1078. Group graph model

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

# 1079. Cyclic group protection

El sistema deberá detectar ciclos antes de persistir relaciones.

---

# 1080. Group cycle detection

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

# 1081. Nested group depth limit

La política deberá limitar la profundidad de expansión.

---

# 1082. Membership explosion protection

Deberán establecerse límites para:

* miembros efectivos;
* grupos anidados;
* profundidad;
* tiempo de cálculo;
* fan-out;
* recomputación.

---

# 1083. Effective membership

La membresía efectiva deberá distinguir:

* directa;
* heredada;
* dinámica;
* local;
* federada;
* SCIM.

---

# 1084. Authorization cache invalidation

Los cambios en membresía efectiva deberán incrementar la versión de autorización correspondiente.

---

# 1085. Sync drift

Drift es la diferencia no esperada entre el estado externo y el estado local.

---

# 1086. DirectoryDriftDetector

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

# 1087. Drift categories

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

# 1088. DirectoryDriftReport

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

# 1089. Drift remediation modes

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

# 1090. Safe auto-repair

Solo deberán repararse automáticamente diferencias:

* no destructivas;
* deterministas;
* respaldadas por una autoridad clara;
* sin impacto de privilegios;
* auditables.

---

# 1091. Provisioning health monitoring

VoltStack deberá medir la salud operacional del subsistema.

---

# 1092. DirectorySyncHealthMetrics

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

# 1093. DirectorySyncHealthStatus

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

# 1094. Health threshold policies

Cada tenant podrá definir tolerancias según:

* criticidad;
* frecuencia esperada;
* volumen;
* impacto de desprovisionamiento;
* requisitos regulatorios.

---

# 1095. Provisioning alerts

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

# 1096. Disaster recovery foundations

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

# 1097. DirectorySyncRecoveryService

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

# 1098. Recovery strategies

VoltStack podrá ejecutar:

* replay desde checkpoint;
* reconstrucción desde full snapshot;
* rollback lógico;
* rehidratación de mappings;
* reparación de drift;
* reconstrucción de membresías.

---

# 1099. Directory synchronization audit events

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

# 1100. Resultado de esta entrega

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

# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 12 de varias
**Cobertura:** Secciones **1101–1200**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 11`

---

# 1101. Identity Governance Architecture

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

# 1102. Governance security goals

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

# 1103. Governance threat model

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

# 1104. Governance architectural components

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

# 1105. IdentityGovernanceService

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

# 1106. GovernanceSubject

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

# 1107. GovernanceAssessment

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

# 1108. GovernanceRiskLevel

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

# 1109. Entitlement catalog

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

# 1110. EntitlementDefinition

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

# 1111. EntitlementType

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

# 1112. EntitlementRiskLevel

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

# 1113. Entitlement ownership

Cada entitlement deberá tener un propietario responsable de:

* descripción;
* clasificación;
* aprobación;
* recertificación;
* revisión de uso;
* remediación;
* retiro.

---

# 1114. Entitlement lifecycle

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

# 1115. EntitlementCatalog

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

# 1116. Requestable entitlements

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

# 1117. Entitlement metadata integrity

La metadata crítica deberá:

* versionarse;
* firmarse o protegerse por integridad;
* auditarse;
* requerir aprobación para cambios sensibles.

---

# 1118. Role architecture

Los roles deberán representar agrupaciones gobernadas de acceso.

---

# 1119. Role types

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

# 1120. GovernanceRole

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

# 1121. GovernanceRoleState

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

# 1122. Role explosion prevention

VoltStack deberá detectar:

* roles duplicados;
* roles excesivamente específicos;
* roles sin miembros;
* roles de un solo usuario;
* roles con combinaciones equivalentes;
* jerarquías innecesarias.

---

# 1123. Role mining

Role mining deberá analizar patrones de acceso existentes para proponer roles.

---

# 1124. RoleMiningService

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

# 1125. Role mining limitations

Las recomendaciones no deberán aplicarse automáticamente a roles privilegiados.

---

# 1126. RoleMiningReport

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

# 1127. Access request architecture

VoltStack deberá ofrecer un flujo gobernado para solicitar acceso.

---

# 1128. AccessRequest

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

# 1129. AccessRequestState

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

# 1130. Access request validation

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

# 1131. Self-request restrictions

Un usuario podrá solicitar acceso para sí mismo, pero no deberá autoaprobarlo cuando el entitlement sea sensible.

---

# 1132. Request-on-behalf-of

Solicitar acceso para otra persona deberá requerir:

* autoridad delegada;
* relación organizacional válida;
* justificación;
* auditoría.

---

# 1133. Access justification

La justificación deberá:

* ser obligatoria para accesos sensibles;
* tener longitud mínima;
* evitar valores genéricos;
* vincularse al propósito;
* conservarse como evidencia.

---

# 1134. Access duration

Todo acceso temporal deberá declarar una expiración.

---

# 1135. Maximum access duration

La duración máxima deberá depender de:

* riesgo;
* tipo de entitlement;
* rol;
* tenant;
* regulación;
* contrato;
* contexto laboral.

---

# 1136. Approval policy architecture

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

# 1137. AccessApprovalPlan

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

# 1138. Approval stages

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

# 1139. ApprovalStage

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

# 1140. ApprovalStageType

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

# 1141. Self-approval prohibition

El solicitante, beneficiario o actor con conflicto directo no deberá aprobar el request.

---

# 1142. Approval delegation

Una delegación de aprobación deberá ser:

* temporal;
* explícita;
* limitada por tipo de solicitud;
* auditada;
* no transitiva por defecto.

---

# 1143. ApprovalDecision

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

# 1144. ApprovalDecisionType

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

# 1145. Approval timeout

Las aprobaciones pendientes deberán expirar.

---

# 1146. Approval escalation

La policy podrá:

* reasignar;
* escalar;
* solicitar segundo aprobador;
* cancelar;
* denegar por timeout.

---

# 1147. Approval evidence

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

# 1148. Separation of Duties

SoD deberá impedir combinaciones de acceso que creen riesgo operativo o fraude.

---

# 1149. SoD rule types

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

# 1150. Static SoD

Una regla estática impide poseer simultáneamente determinados accesos.

Ejemplo:

```text
vendor.create + payment.approve
```

---

# 1151. Dynamic SoD

Una regla dinámica podrá permitir ambos accesos, pero no utilizarlos dentro de una misma transacción o proceso.

---

# 1152. Transactional SoD

La separación deberá aplicarse al momento de ejecutar acciones relacionadas.

---

# 1153. Temporal SoD

Una identidad no deberá realizar dos acciones incompatibles dentro de una ventana temporal definida.

---

# 1154. Contextual SoD

La regla podrá depender de:

* tenant;
* monto;
* región;
* recurso;
* unidad organizacional;
* nivel de riesgo;
* tipo de operación.

---

# 1155. SeparationOfDutiesRule

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

# 1156. SoD evaluator

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

# 1157. Toxic access combinations

Una toxic combination representa una combinación de acceso con riesgo elevado.

---

# 1158. ToxicCombination

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

# 1159. Toxic access detection

La detección deberá ejecutarse:

* al solicitar acceso;
* al cambiar roles;
* al sincronizar grupos;
* durante access reviews;
* antes de operaciones críticas;
* periódicamente.

---

# 1160. Compensating controls

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

# 1161. SoD exception

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

# 1162. SoD exception expiry

Toda excepción deberá expirar y no renovarse automáticamente.

---

# 1163. Access review architecture

VoltStack deberá permitir revisiones periódicas y event-driven.

---

# 1164. AccessReviewCampaign

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

# 1165. AccessReviewCampaignState

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

# 1166. AccessReviewScope

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

# 1167. Review item

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

# 1168. AccessReviewItemState

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

# 1169. Reviewer selection

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

# 1170. Reviewer conflict prevention

No deberá permitirse que una identidad certifique su propio acceso sensible.

---

# 1171. Review evidence

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

# 1172. Usage-aware review

El sistema podrá incorporar señales de uso, pero ausencia de uso no deberá ser la única base para revocar automáticamente acceso crítico.

---

# 1173. AccessReviewDecision

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

# 1174. AccessReviewDecisionType

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

# 1175. Access recertification

La recertificación deberá exigir una decisión explícita.

La ausencia de respuesta no deberá equivaler automáticamente a aprobación.

---

# 1176. Default-deny review policy

Para accesos privilegiados, la política podrá revocar por falta de certificación.

---

# 1177. Review reminders

El sistema deberá emitir recordatorios antes de la fecha límite.

---

# 1178. Review escalation

Los items no revisados podrán escalar a:

* manager superior;
* owner;
* seguridad;
* compliance;
* tenant administrator.

---

# 1179. Review remediation

Una decisión de revocación deberá generar una acción verificable.

---

# 1180. Remediation tracking

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

# 1181. GovernanceRemediationState

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

# 1182. Remediation verification

Una tarea no deberá marcarse como completada hasta confirmar el estado real en el sistema objetivo.

---

# 1183. Joiner workflow

El flujo Joiner deberá gobernar la incorporación de una nueva identidad.

---

# 1184. Joiner workflow stages

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

# 1185. Birthright access

El acceso base deberá derivarse de reglas claras y mínimas.

---

# 1186. BirthrightAccessPolicy

```php
interface BirthrightAccessPolicyInterface
{
    public function calculate(
        JoinerContext $context
    ): BirthrightAccessPlan;
}
```

---

# 1187. Birthright restrictions

El acceso base no deberá incluir privilegios administrativos salvo excepción documentada.

---

# 1188. Mover workflow

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

# 1189. Mover access recomputation

Un cambio organizacional deberá recalcular:

* birthright access;
* roles;
* grupos;
* SoD;
* approvals;
* accesos temporales;
* ownerships.

---

# 1190. Add-before-remove risk

Agregar acceso nuevo antes de retirar acceso anterior podrá crear toxic combinations temporales.

La secuencia deberá planificarse según riesgo.

---

# 1191. Mover transition plan

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

# 1192. Leaver workflow

El flujo Leaver deberá retirar acceso de forma oportuna y verificable.

---

# 1193. Leaver trigger types

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

# 1194. Leaver actions

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

# 1195. Immediate termination

Una salida inmediata deberá priorizar:

1. bloquear autenticación;
2. revocar sesiones;
3. revocar credenciales;
4. retirar privilegios;
5. preservar evidencia;
6. transferir ownership.

---

# 1196. Access expiration engine

```php
interface AccessExpirationServiceInterface
{
    public function expireDueAccess(
        DateTimeImmutable $now
    ): AccessExpirationResult;
}
```

---

# 1197. Expiration sources

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

# 1198. Just-in-time privileged access

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

# 1199. Break-glass identity governance

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

# 1200. Resultado de esta entrega

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

# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 13 de varias
**Cobertura:** Secciones **1201–1300**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 12`

---

# 1201. Privileged Access Management Architecture

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

# 1202. PAM security goals

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

# 1203. PAM threat model

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

# 1204. PAM architectural components

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

# 1205. Privileged identity

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

# 1206. PrivilegedIdentityProfile

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

# 1207. PrivilegedIdentityState

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

# 1208. PrivilegedRiskLevel

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

# 1209. Personal and administrative identity separation

VoltStack deberá permitir separar:

* identidad personal;
* identidad administrativa;
* identidad break-glass;
* identidad de servicio.

Una identidad administrativa no deberá utilizarse para tareas ordinarias.

---

# 1210. Linked privileged identities

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

# 1211. Standing privilege reduction

Los privilegios permanentes deberán minimizarse.

El estado preferido será:

```text
eligible but inactive
```

---

# 1212. Privileged eligibility

Una identidad eligible puede solicitar activación, pero no posee privilegios efectivos hasta completar el workflow.

---

# 1213. Privileged access request

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

# 1214. PrivilegedAccessRequestState

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

# 1215. Privileged request requirements

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

# 1216. Strong authentication requirement

Toda elevación privilegiada deberá requerir:

* autenticación reciente;
* MFA;
* assurance elevado;
* preferencia por método phishing-resistant;
* verificación de dispositivo cuando aplique.

---

# 1217. Privileged device policy

El acceso podrá limitarse a:

* dispositivos administrados;
* posture compatible;
* cifrado habilitado;
* agente de seguridad activo;
* red autorizada;
* navegador o cliente aprobado.

---

# 1218. PrivilegedAccessPolicy

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

# 1219. PrivilegedAccessDecision

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

# 1220. Just-in-time elevation

La elevación JIT deberá activar privilegios solo durante una ventana corta y explícita.

---

# 1221. Privilege elevation grant

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

# 1222. PrivilegeElevationGrantState

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

# 1223. Automatic expiration

Toda elevación deberá expirar automáticamente sin depender de logout o acción manual.

---

# 1224. Maximum elevation duration

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

# 1225. Renewal restrictions

Una elevación no deberá renovarse silenciosamente.

Toda extensión deberá volver a evaluar:

* necesidad;
* riesgo;
* aprobación;
* duración;
* conflictos.

---

# 1226. Just-enough administration

JEA deberá otorgar únicamente las acciones necesarias para completar una tarea.

---

# 1227. Action-level privilege model

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

# 1228. Resource-scoped elevation

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

# 1229. Command-scoped elevation

Cuando sea técnicamente posible, VoltStack deberá restringir:

* comandos;
* argumentos;
* rutas;
* operaciones;
* APIs;
* verbos administrativos.

---

# 1230. Privileged command policy

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

# 1231. PrivilegedCommand

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

# 1232. Dangerous command restrictions

Operaciones destructivas podrán requerir:

* aprobación adicional;
* dual control;
* confirmación explícita;
* ventana de mantenimiento;
* backup verificado;
* ticket válido.

---

# 1233. Dual control

Dual control exige participación de al menos dos actores independientes.

---

# 1234. DualControlPolicy

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

# 1235. Four-eyes principle

Para operaciones críticas deberá impedirse que una sola persona:

* solicite;
* apruebe;
* ejecute;
* certifique;

la misma acción completa.

---

# 1236. Privileged session architecture

Una sesión privilegiada deberá ser distinta de la sesión ordinaria.

---

# 1237. PrivilegedSession

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

# 1238. PrivilegedSessionState

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

# 1239. Session isolation

Una sesión privilegiada deberá:

* usar identificador separado;
* tener timeout menor;
* no heredar privilegios indefinidamente;
* exigir step-up;
* registrar provenance;
* poder revocarse de forma independiente.

---

# 1240. Privileged session broker

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

# 1241. Session proxying

VoltStack podrá intermediar acceso hacia:

* SSH;
* RDP;
* bases de datos;
* Kubernetes;
* paneles administrativos;
* APIs;
* consolas cloud.

---

# 1242. Direct credential exposure reduction

Siempre que sea posible, el usuario no deberá recibir directamente la credencial privilegiada.

---

# 1243. Session recording

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

# 1244. PrivilegedSessionRecorder

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

# 1245. Recording integrity

Las grabaciones deberán:

* ser append-only;
* incluir integrity hashes;
* poseer timestamp confiable;
* cifrarse;
* controlar acceso;
* aplicar retención.

---

# 1246. Recording privacy

La grabación deberá minimizar exposición de:

* secretos;
* datos personales;
* tokens;
* información regulada;
* contenido no relacionado.

---

# 1247. Sensitive output redaction

VoltStack deberá redactar valores sensibles cuando sea posible sin destruir la evidencia operacional.

---

# 1248. Live session monitoring

Sesiones críticas podrán ser supervisadas en tiempo real por seguridad o un aprobador autorizado.

---

# 1249. Session intervention

El supervisor autorizado podrá:

* advertir;
* pausar;
* restringir;
* terminar;
* elevar incidente.

---

# 1250. Privileged credential vault

VoltStack deberá abstraer almacenamiento seguro de credenciales privilegiadas.

---

# 1251. PrivilegedCredentialVault

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

# 1252. Vault storage requirements

El vault deberá utilizar:

* encryption at rest;
* KMS o HSM cuando corresponda;
* access control;
* audit logging;
* key rotation;
* tamper detection;
* separación de duties.

---

# 1253. PrivilegedCredential

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

# 1254. PrivilegedCredentialType

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

# 1255. PrivilegedCredentialState

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

# 1256. Credential checkout

El checkout deberá:

* requerir grant activo;
* limitar duración;
* vincularse a identidad;
* vincularse a recurso;
* registrarse;
* impedir reutilización no autorizada.

---

# 1257. CredentialCheckoutLease

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

# 1258. CredentialCheckoutLeaseState

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

# 1259. Post-checkout rotation

Las credenciales compartidas deberán rotarse después de:

* checkout;
* expiración;
* incidente;
* cambio de owner;
* uso break-glass;
* sospecha de exposición.

---

# 1260. Secret exposure minimization

El sistema deberá preferir:

* injection temporal;
* brokered sessions;
* short-lived credentials;
* workload identity federation;
* dynamic database credentials;

sobre revelar secretos estáticos.

---

# 1261. Emergency access

El acceso de emergencia deberá estar disponible para incidentes donde el flujo normal sea insuficiente.

---

# 1262. EmergencyAccessRequest

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

# 1263. Emergency access controls

El flujo deberá:

* exigir razón;
* registrar incidente;
* limitar duración;
* generar alerta inmediata;
* aplicar máxima observabilidad;
* ejecutar revisión posterior obligatoria.

---

# 1264. Break-glass execution

El uso de una identidad break-glass deberá considerarse un evento de seguridad crítico.

---

# 1265. BreakGlassExecutionRecord

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

# 1266. Break-glass post-use actions

Después del uso deberá:

* revocarse la sesión;
* rotarse la credencial;
* preservar evidencia;
* revisar acciones;
* validar recursos;
* cerrar o actualizar incidente;
* recertificar la identidad.

---

# 1267. Dormant privileged accounts

Una cuenta privilegiada dormida representa alto riesgo aunque no tenga uso reciente.

---

# 1268. DormantPrivilegedAccountPolicy

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

# 1269. Dormant account detection

Deberá considerar:

* último login;
* última elevación;
* última acción;
* owner vigente;
* fuente de aprovisionamiento;
* estado laboral;
* recursos asignados.

---

# 1270. Dormant account remediation

Las acciones podrán incluir:

* suspender;
* retirar roles;
* revocar credentials;
* exigir revisión;
* eliminar account binding;
* marcar para investigación.

---

# 1271. Service accounts

Una service account es una identidad no humana utilizada por aplicaciones o procesos.

---

# 1272. ServiceAccountProfile

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

# 1273. ServiceAccountState

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

# 1274. Service account requirements

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

# 1275. Shared service account restrictions

Las service accounts no deberán utilizarse como cuentas humanas compartidas.

---

# 1276. Interactive login prohibition

Por defecto, una service account no deberá permitir login interactivo.

---

# 1277. Service account owner lifecycle

Si el owner deja la organización o cambia de función, deberá iniciarse:

* reasignación;
* revisión;
* suspensión;
* revocación si no existe nuevo owner.

---

# 1278. Orphan service accounts

Una service account sin owner válido deberá marcarse como huérfana.

---

# 1279. Machine identities

VoltStack deberá tratar como identidades de máquina a:

* servidores;
* dispositivos;
* agentes;
* runners;
* nodos;
* appliances;
* workloads.

---

# 1280. MachineIdentityProfile

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

# 1281. MachineIdentityState

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

# 1282. Workload identity

Una workload identity deberá representar una carga de trabajo concreta, no una infraestructura compartida genérica.

---

# 1283. WorkloadIdentityProfile

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

# 1284. WorkloadIdentityState

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

# 1285. Workload identity federation

VoltStack deberá preferir federación de identidad sobre secretos estáticos cuando la plataforma lo permita.

---

# 1286. Workload assertion validation

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

# 1287. Short-lived workload credentials

Las credenciales emitidas a workloads deberán tener vida corta y scope mínimo.

---

# 1288. Non-human identity governance

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

# 1289. NonHumanIdentityRecord

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

# 1290. NonHumanIdentityType

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

# 1291. Secret rotation architecture

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

# 1292. Rotation triggers

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

# 1293. Zero-downtime rotation

Cuando sea posible, la rotación deberá soportar:

1. crear nueva credencial;
2. distribuirla;
3. validar adopción;
4. retirar la anterior;
5. verificar ausencia de uso;
6. revocar definitivamente.

---

# 1294. Secret versioning

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

# 1295. SecretVersionState

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

# 1296. Privileged access analytics

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

# 1297. PrivilegedAccessAnalyticsEngine

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

# 1298. Privileged anomaly signals

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

# 1299. PAM audit events

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

# 1300. Resultado de esta entrega

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

# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 14 de varias
**Cobertura:** Secciones **1301–1400**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 13`

---

# 1301. Identity Threat Detection and Response (ITDR) Architecture

VoltStack deberá incorporar un subsistema de **Identity Threat Detection and Response (ITDR)** encargado de detectar, correlacionar y responder a amenazas relacionadas con identidades humanas y no humanas.

El objetivo será reducir el tiempo entre:

* detección;
* evaluación;
* contención;
* remediación;
* recuperación.

---

# 1302. ITDR security goals

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

# 1303. ITDR threat model

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

# 1304. ITDR architecture

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

# 1305. IdentityThreatDetectionService

```php
interface IdentityThreatDetectionServiceInterface
{
    public function analyze(
        IdentitySecuritySignal $signal
    ): ThreatAssessment;
}
```

---

# 1306. IdentitySecuritySignal

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

# 1307. IdentitySignalType

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

# 1308. Signal confidence

Cada señal deberá indicar un nivel de confianza que refleje la calidad de la evidencia y permita ponderar adecuadamente el riesgo.

---

# 1309. Signal normalization

Todas las señales deberán convertirse a un formato interno común antes de iniciar la correlación.

---

# 1310. ThreatAssessment

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

# 1311. Authentication anomaly correlation

VoltStack deberá correlacionar:

* múltiples fallos consecutivos;
* cambios bruscos de ubicación;
* nuevos dispositivos;
* horarios inusuales;
* cambios de navegador;
* autenticaciones simultáneas;
* cambios de método MFA.

---

# 1312. Multi-signal correlation

Una única señal de bajo riesgo no deberá provocar automáticamente una respuesta severa.

La decisión deberá basarse en múltiples evidencias consistentes.

---

# 1313. Correlation engine

```php
interface IdentityCorrelationEngineInterface
{
    public function correlate(
        array $signals
    ): IdentityThreatCorrelation;
}
```

---

# 1314. Correlation windows

La correlación podrá utilizar ventanas:

* segundos;
* minutos;
* horas;
* días;

según el tipo de amenaza.

---

# 1315. Risk accumulation

El riesgo podrá acumularse mediante múltiples eventos pequeños en lugar de depender únicamente de un evento crítico.

---

# 1316. Session risk scoring

Cada sesión activa deberá mantener un puntaje dinámico de riesgo.

---

# 1317. SessionRiskScore

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

# 1318. Risk contributors

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

# 1319. Continuous risk recomputation

El riesgo no deberá calcularse únicamente durante el login.

---

# 1320. Continuous evaluation triggers

Reevaluar cuando ocurra:

* cambio de IP;
* cambio de dispositivo;
* elevación privilegiada;
* modificación de roles;
* nueva inteligencia de amenazas;
* revocación de credenciales;
* señal de compromiso.

---

# 1321. Impossible travel detection

VoltStack podrá detectar desplazamientos físicamente improbables entre autenticaciones.

---

# 1322. ImpossibleTravelAssessment

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

# 1323. Impossible travel limitations

La detección deberá considerar:

* VPN;
* proxies corporativos;
* roaming móvil;
* redes satelitales;
* cambios ASN legítimos.

---

# 1324. Token replay detection

Los tokens reutilizados fuera de su patrón esperado deberán generar alertas.

---

# 1325. Replay registry correlation

La detección deberá apoyarse en:

* jti;
* nonce;
* DPoP;
* sender constraints;
* Session Identifier;
* refresh token family.

---

# 1326. Credential compromise detection

El sistema deberá identificar indicios como:

* uso simultáneo;
* autenticaciones incompatibles;
* password recientemente filtrado;
* cambio inesperado de MFA;
* bypass de controles.

---

# 1327. Password spray detection

VoltStack deberá correlacionar intentos distribuidos contra múltiples usuarios utilizando la misma contraseña o patrón.

---

# 1328. Credential stuffing detection

El sistema deberá detectar autenticaciones derivadas de listas masivas de credenciales comprometidas.

---

# 1329. MFA fatigue detection

Se deberán identificar patrones de:

* múltiples solicitudes consecutivas;
* rechazo repetido;
* aceptación tardía sospechosa;
* solicitudes desde origen inesperado.

---

# 1330. Push approval abuse

Una aprobación MFA no deberá interpretarse automáticamente como ausencia de compromiso.

---

# 1331. Device compromise signals

Señales recomendadas:

* jailbreak;
* root;
* boot inseguro;
* attestation fallida;
* EDR comprometido;
* certificado inválido;
* postura degradada.

---

# 1332. DeviceRiskAssessment

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

# 1333. Identity Behavior Analytics (IBA)

VoltStack podrá mantener perfiles de comportamiento normal por identidad.

---

# 1334. Behavioral baseline

El perfil podrá considerar:

* horario habitual;
* recursos utilizados;
* frecuencia;
* comandos;
* dispositivos;
* regiones;
* duración de sesión.

---

# 1335. Behavior profile

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

# 1336. UEBA foundations

El motor de User and Entity Behavior Analytics deberá analizar:

* usuarios;
* cuentas privilegiadas;
* cuentas de servicio;
* workloads;
* dispositivos.

---

# 1337. UEBA risk enrichment

Las anomalías de comportamiento deberán enriquecer, no reemplazar, el motor principal de riesgo.

---

# 1338. Risk-based access decisions

Las decisiones de acceso podrán adaptarse dinámicamente al riesgo.

---

# 1339. Adaptive responses

Ejemplos:

* permitir;
* requerir MFA adicional;
* exigir passkey;
* limitar permisos;
* reducir duración de sesión;
* bloquear.

---

# 1340. Risk decision engine

```php
interface RiskBasedAccessDecisionEngineInterface
{
    public function decide(
        RiskEvaluationContext $context
    ): AdaptiveAccessDecision;
}
```

---

# 1341. AdaptiveAccessDecision

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

# 1342. Continuous Access Evaluation

VoltStack deberá reevaluar sesiones activas durante toda su vida útil.

---

# 1343. Continuous evaluation events

Eventos relevantes:

* cambio de contraseña;
* revocación de roles;
* señal de compromiso;
* dispositivo comprometido;
* sesión paralela;
* logout remoto.

---

# 1344. Session restriction

Una sesión podrá pasar dinámicamente a estado restringido.

---

# 1345. Restriction actions

Una sesión restringida podrá:

* impedir operaciones privilegiadas;
* bloquear escritura;
* permitir solo lectura;
* exigir reautenticación;
* impedir descarga;
* impedir exportación.

---

# 1346. Session quarantine

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

# 1347. Automated identity containment

Las respuestas automáticas podrán incluir:

* revocar sesiones;
* invalidar tokens;
* suspender identidad;
* bloquear dispositivo;
* revocar elevaciones;
* suspender service account.

---

# 1348. Containment proportionality

La respuesta deberá ser proporcional al nivel de confianza y severidad.

---

# 1349. Automated playbooks

```php
interface IdentityIncidentPlaybookInterface
{
    public function execute(
        IdentityIncident $incident
    ): PlaybookExecutionResult;
}
```

---

# 1350. Playbook examples

Ejemplos:

* Credential Compromise;
* Break-glass Misuse;
* Impossible Travel;
* Privileged Session Abuse;
* Password Spray;
* Service Account Exposure.

---

# 1351. Identity incident

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

# 1352. Evidence preservation

Toda respuesta deberá preservar:

* señales;
* decisiones;
* timestamps;
* sesiones;
* tokens;
* dispositivos;
* acciones automáticas.

---

# 1353. Chain of custody

La evidencia deberá mantener:

* integridad;
* inmutabilidad;
* trazabilidad;
* control de acceso;
* auditoría.

---

# 1354. Identity forensic package

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

# 1355. SIEM integration

VoltStack deberá exportar eventos mediante formatos estructurados compatibles con plataformas SIEM.

---

# 1356. SOAR integration

Las respuestas automáticas podrán iniciarse desde plataformas SOAR autorizadas.

---

# 1357. Threat intelligence enrichment

El motor podrá enriquecer señales utilizando:

* reputación IP;
* dominios maliciosos;
* indicadores de compromiso;
* inteligencia sobre credenciales comprometidas.

---

# 1358. Threat intelligence trust

La inteligencia externa deberá clasificarse según nivel de confianza y antigüedad.

---

# 1359. Identity security metrics

Métricas recomendadas:

* tiempo medio de detección;
* tiempo medio de contención;
* sesiones restringidas;
* sesiones revocadas;
* cuentas comprometidas;
* elevaciones bloqueadas;
* incidentes cerrados.

---

# 1360. Security KPI dashboard

El dashboard podrá mostrar:

* tendencias;
* riesgo por tenant;
* riesgo por aplicación;
* identidades privilegiadas;
* cuentas huérfanas;
* MFA coverage;
* anomalías.

---

# 1361. ThreatSeverity

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

# 1362. ThreatConfidence

```php
enum ThreatConfidence: string
{
    case Low;
    case Medium;
    case High;
}
```

---

# 1363. IdentityIncidentSeverity

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

# 1364. SessionRiskLevel

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

# 1365. DeviceRiskLevel

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

# 1366. SignalConfidence

```php
enum SignalConfidence: string
{
    case Weak;
    case Moderate;
    case Strong;
}
```

---

# 1367. AdaptiveDecisionType

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

# 1368. SessionRestrictionLevel

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

# 1369. QuarantineReason

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

# 1370. Identity incident lifecycle

Estados recomendados:

* Detected;
* Investigating;
* Contained;
* Recovering;
* Closed.

---

# 1371. False positive handling

Toda alerta deberá poder clasificarse como falso positivo sin perder la trazabilidad histórica.

---

# 1372. Analyst feedback loop

Las decisiones de analistas podrán retroalimentar el motor de correlación para mejorar futuras evaluaciones.

---

# 1373. Multi-tenant isolation

Las señales de un tenant nunca deberán influir directamente en el riesgo calculado para otro tenant.

---

# 1374. Privacy-aware analytics

Los modelos analíticos deberán minimizar el tratamiento de datos personales cuando no sean necesarios.

---

# 1375. Retention policy

Las señales deberán conservarse únicamente durante el período definido por la política de seguridad y regulación aplicable.

---

# 1376. Secure signal storage

Las señales deberán almacenarse con:

* cifrado;
* control de acceso;
* integridad;
* versionado cuando aplique.

---

# 1377. Replay-resistant telemetry

La telemetría utilizada por el motor ITDR deberá protegerse contra inyección y replay.

---

# 1378. Threat model updates

El catálogo de amenazas deberá revisarse periódicamente para incorporar nuevas técnicas de ataque.

---

# 1379. Detection testing

VoltStack deberá permitir pruebas controladas de reglas de detección sin afectar producción.

---

# 1380. Simulation mode

Las políticas podrán ejecutarse en modo simulación para medir impacto antes de activarse.

---

# 1381. Explainable decisions

Las decisiones automáticas deberán incluir una explicación auditable de los factores que las motivaron.

---

# 1382. Response override

Un operador autorizado podrá cancelar una respuesta automática cuando exista justificación documentada.

---

# 1383. Override audit

Toda anulación manual deberá quedar registrada con:

* actor;
* motivo;
* hora;
* incidente asociado.

---

# 1384. Identity security reporting

Los reportes deberán poder agrupar incidentes por:

* tenant;
* aplicación;
* tipo de amenaza;
* criticidad;
* identidad;
* periodo.

---

# 1385. Operational resilience

El fallo del motor analítico no deberá impedir la aplicación de controles de autenticación básicos.

---

# 1386. Graceful degradation

Si un componente analítico deja de estar disponible, VoltStack deberá degradar funcionalidades no esenciales sin comprometer la seguridad mínima.

---

# 1387. ITDR extensibility

El motor deberá permitir incorporar nuevos detectores sin modificar el núcleo del framework.

---

# 1388. Detector plugin model

```php
interface IdentityThreatDetectorInterface
{
    public function detect(
        IdentitySecuritySignal $signal
    ): ?ThreatAssessment;
}
```

---

# 1389. Detector isolation

Un detector defectuoso no deberá impedir la ejecución del resto de detectores.

---

# 1390. Detector prioritization

Los detectores podrán ejecutarse según prioridad y costo computacional.

---

# 1391. Threat response policy

Las respuestas deberán gobernarse mediante políticas configurables por tenant.

---

# 1392. Tenant risk profile

Cada tenant podrá definir umbrales de riesgo acordes con sus requisitos regulatorios y operativos.

---

# 1393. Incident notification

Las alertas críticas podrán notificarse a:

* SOC;
* administradores;
* propietarios del tenant;
* equipos de respuesta.

---

# 1394. Notification throttling

Las notificaciones deberán limitarse para evitar tormentas de alertas.

---

# 1395. Security evidence export

La evidencia deberá poder exportarse preservando integridad y metadatos.

---

# 1396. Compliance support

El subsistema deberá facilitar evidencia para auditorías de seguridad y cumplimiento.

---

# 1397. Identity resilience

La arquitectura deberá asumir que una identidad puede verse comprometida en cualquier momento y reaccionar en consecuencia.

---

# 1398. Zero Trust alignment

Todas las decisiones deberán alinearse con el principio de **"never trust, always verify"**.

---

# 1399. Security events

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

# 1400. Resultado de esta entrega

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

# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 15 de varias
**Cobertura:** Secciones **1401–1500**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 14`

---

# 1401. Cryptographic Architecture

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

# 1402. Cryptographic security goals

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

# 1403. Cryptographic threat model

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

# 1404. Cryptographic architectural components

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

# 1405. CryptographicService

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

# 1406. Cryptographic operation purposes

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

# 1407. CryptographicPurpose

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

# 1408. Purpose separation

Una misma clave no deberá reutilizarse para propósitos criptográficos distintos.

No deberá utilizarse una clave de:

* cifrado para firmar;
* firma para MAC;
* sesión para cifrado de datos;
* tenant A para tenant B;
* producción para desarrollo.

---

# 1409. Cryptographic policy engine

```php id="zfxl8i"
interface CryptographicPolicyEngineInterface
{
    public function resolve(
        CryptographicOperationContext $context
    ): CryptographicPolicyDecision;
}
```

---

# 1410. CryptographicPolicyDecision

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

# 1411. Cryptographic agility

VoltStack deberá poder sustituir algoritmos, proveedores y formatos sin reescribir el dominio.

---

# 1412. Algorithm registry

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

# 1413. CryptographicAlgorithmDefinition

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

# 1414. CryptographicAlgorithmType

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

# 1415. CryptographicAlgorithmState

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

# 1416. Algorithm allowlists

VoltStack deberá utilizar allowlists explícitas.

Nunca deberá aceptar automáticamente cualquier algoritmo declarado por input externo.

---

# 1417. Algorithm downgrade prevention

Cuando un artefacto declare un algoritmo inferior al requerido, la operación deberá rechazarse aunque el algoritmo todavía exista en el runtime.

---

# 1418. Legacy verification mode

Algoritmos antiguos podrán conservarse únicamente para:

* verificar datos existentes;
* migrar formatos;
* reemitir artefactos;
* rotar claves.

No deberán utilizarse para nuevas operaciones.

---

# 1419. Cryptographic provider abstraction

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

# 1420. Provider types

VoltStack podrá integrar:

* OpenSSL;
* libsodium;
* platform crypto APIs;
* cloud KMS;
* HSM;
* PKCS#11;
* remote signing services.

---

# 1421. Provider selection policy

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

# 1422. Key management architecture

Todas las claves deberán gestionarse mediante un subsistema central.

---

# 1423. KeyManagementService

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

# 1424. ManagedKey

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

# 1425. ManagedKeyState

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

# 1426. KeyProtectionLevel

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

# 1427. Key references

El dominio deberá trabajar con referencias opacas, no con material de clave directamente.

---

# 1428. KeyReference

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

# 1429. Key material isolation

El material de clave no deberá:

* aparecer en logs;
* serializarse;
* incluirse en excepciones;
* exponerse a Controllers;
* persistirse sin protección;
* permanecer más tiempo del necesario en memoria.

---

# 1430. Key hierarchy

VoltStack deberá utilizar jerarquías de claves para evitar cifrar todo directamente con una clave raíz.

---

# 1431. Recommended key hierarchy

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

# 1432. Root key

La root key deberá:

* permanecer fuera del almacenamiento ordinario;
* utilizarse solo para proteger claves inferiores;
* residir preferentemente en HSM o KMS;
* tener acceso extremadamente restringido.

---

# 1433. Key Encryption Key

Una KEK deberá proteger:

* tenant keys;
* data encryption keys;
* backup keys;
* signing key material exportable.

---

# 1434. Tenant master keys

Cada tenant podrá poseer una o más claves maestras separadas.

---

# 1435. Tenant cryptographic isolation

Nunca deberá utilizarse la misma DEK para cifrar datos de tenants diferentes.

---

# 1436. Data Encryption Key

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

# 1437. Envelope encryption

VoltStack deberá utilizar envelope encryption para datos sensibles de alto volumen.

---

# 1438. Envelope encryption flow

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

# 1439. EncryptedPayload

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

# 1440. Authenticated encryption

Para nuevo cifrado simétrico deberá preferirse AEAD.

Ejemplos de familias permitidas según policy:

* AES-GCM;
* ChaCha20-Poly1305;
* XChaCha20-Poly1305.

---

# 1441. AEAD associated data

El associated data podrá incluir:

* tenant ID;
* resource type;
* resource ID;
* field name;
* schema version;
* key purpose.

---

# 1442. Ciphertext swapping prevention

Los metadatos autenticados deberán impedir mover ciphertext válidos entre:

* tenants;
* recursos;
* campos;
* contextos;
* versiones incompatibles.

---

# 1443. Nonce generation

Los nonces deberán:

* generarse correctamente según el algoritmo;
* tener longitud válida;
* no reutilizarse con la misma clave;
* no depender de timestamps únicamente.

---

# 1444. Nonce reuse protection

Para algoritmos sensibles a nonce reuse, VoltStack deberá:

* utilizar generación segura;
* registrar estrategia;
* aplicar contadores cuando corresponda;
* rotar claves ante riesgo de repetición.

---

# 1445. Data encryption policy

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

# 1446. Data classification

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

# 1447. Encryption at rest

VoltStack deberá considerar múltiples capas:

* disk encryption;
* database encryption;
* object storage encryption;
* application-level encryption;
* field-level encryption.

Una capa no deberá asumirse sustituto automático de las demás.

---

# 1448. Field-level encryption

Los campos especialmente sensibles deberán cifrarse antes de llegar al storage.

---

# 1449. FieldEncryptionService

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

# 1450. EncryptedFieldValue

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

# 1451. Field encryption candidates

Ejemplos:

* government identifiers;
* recovery secrets;
* private keys;
* phone numbers según policy;
* medical or financial attributes;
* identity provider tokens;
* sensitive external identifiers.

---

# 1452. Search limitations

Los campos cifrados no deberán hacerse buscables mediante cifrado determinista sin una evaluación explícita del riesgo de pattern leakage.

---

# 1453. Blind indexing

Cuando sea indispensable buscar datos protegidos, VoltStack podrá utilizar blind indexes separados.

---

# 1454. BlindIndexService

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

# 1455. Blind index restrictions

Los blind indexes deberán:

* utilizar clave separada;
* normalizarse cuidadosamente;
* limitar colisiones;
* no revelar el plaintext;
* rotarse;
* tratarse como información sensible.

---

# 1456. Deterministic encryption restrictions

El cifrado determinista deberá estar deshabilitado por defecto y solo habilitarse con:

* caso de uso documentado;
* baja entropía evaluada;
* mitigaciones;
* aprobación de seguridad.

---

# 1457. HSM integration

VoltStack deberá poder integrar Hardware Security Modules para operaciones de alta sensibilidad.

---

# 1458. HSM use cases

El HSM podrá utilizarse para:

* root keys;
* signing keys;
* code signing;
* certificate authority keys;
* privileged token signing;
* key wrapping;
* regulated workloads.

---

# 1459. HsmProvider

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

# 1460. HSM extraction prohibition

Las claves marcadas como non-exportable nunca deberán salir del HSM en plaintext.

---

# 1461. KMS integration

VoltStack deberá abstraer servicios de gestión de claves cloud y privados.

---

# 1462. KmsProvider

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

# 1463. KMS encryption context

El encryption context deberá vincular la operación a:

* tenant;
* application;
* purpose;
* resource;
* environment.

---

# 1464. KMS provider compromise

La arquitectura deberá asumir que un proveedor KMS puede sufrir:

* indisponibilidad;
* revocación;
* configuración incorrecta;
* compromiso de credenciales;
* restricciones regionales.

---

# 1465. Multi-provider key strategy

Para cargas críticas, VoltStack podrá permitir:

* primary KMS;
* backup KMS;
* HSM local;
* recovery key escrow.

La recuperación no deberá debilitar el control ordinario.

---

# 1466. Key derivation

La derivación de claves deberá utilizar algoritmos diseñados para ese propósito.

---

# 1467. KeyDerivationService

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

# 1468. Key derivation use cases

Ejemplos:

* subkeys por tenant;
* subkeys por propósito;
* session keys;
* blind index keys;
* MAC keys;
* temporary encryption keys.

---

# 1469. HKDF policy

HKDF podrá utilizarse para derivar subkeys cuando el input key material posea entropía suficiente.

---

# 1470. Password-derived keys

Las claves derivadas de contraseñas deberán utilizar KDFs específicas como:

* Argon2id;
* scrypt;
* PBKDF2 cuando exista requerimiento de compatibilidad.

---

# 1471. Salt requirements

Los salts deberán:

* ser únicos;
* generarse aleatoriamente;
* almacenarse junto al resultado;
* no reutilizarse como secretos.

---

# 1472. Pepper requirements

Los peppers deberán:

* almacenarse fuera de la base de datos;
* rotarse;
* versionarse;
* protegerse mediante KMS o HSM cuando corresponda.

---

# 1473. Secure random generation

Toda entropía criptográfica deberá provenir de un CSPRNG confiable.

---

# 1474. SecureRandomGenerator

```php id="6sfcn6"
interface SecureRandomGeneratorInterface
{
    public function bytes(int $length): SensitiveValue;

    public function token(int $entropyBits): SensitiveValue;

    public function integer(int $minimum, int $maximum): int;
}
```

---

# 1475. Randomness use cases

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

# 1476. Predictable randomness prohibition

No deberán utilizarse para seguridad:

* timestamps;
* incremental IDs;
* UUID no aleatorios sin análisis;
* pseudo-random generators generales;
* hashes de datos predecibles.

---

# 1477. Entropy health

El runtime deberá detectar y fallar de forma segura si el sistema no puede proporcionar entropía confiable.

---

# 1478. Digital signatures

VoltStack deberá centralizar las operaciones de firma y verificación.

---

# 1479. DigitalSignature

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

# 1480. Signature use cases

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

# 1481. Signature algorithm policy

La política deberá definir:

* algoritmos permitidos;
* tamaño mínimo de clave;
* formatos;
* curve allowlist;
* hashing;
* key provenance;
* maximum artifact lifetime.

---

# 1482. Signature verification pipeline

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

# 1483. Key confusion prevention

Una clave deberá validarse contra:

* tipo;
* algoritmo;
* uso;
* issuer;
* tenant;
* lifecycle state.

---

# 1484. Signature context binding

Una firma válida criptográficamente no deberá aceptarse si pertenece a otro:

* tenant;
* issuer;
* audience;
* purpose;
* environment;
* artifact type.

---

# 1485. Message authentication codes

VoltStack deberá utilizar MAC cuando ambas partes compartan un secreto y no sea necesaria verificación pública.

---

# 1486. MacService

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

# 1487. MAC key separation

Cada integración deberá tener una clave MAC distinta y separada de las claves de cifrado.

---

# 1488. Constant-time comparison

La comparación de:

* MACs;
* hashes secretos;
* tokens;
* signatures codificadas;

deberá utilizar operaciones constant-time cuando corresponda.

---

# 1489. Hashing policies

VoltStack deberá distinguir claramente entre:

* hashing general;
* password hashing;
* keyed hashing;
* integrity hashing;
* content addressing.

---

# 1490. HashPolicy

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

# 1491. General-purpose hash usage

Los hashes generales podrán utilizarse para:

* integrity digests;
* cache keys;
* artifact fingerprints;
* content addressing;
* audit chaining.

No deberán usarse directamente para almacenar contraseñas.

---

# 1492. Cryptographic key rotation

Toda clase de clave deberá tener una política de rotación.

---

# 1493. KeyRotationPolicy

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

# 1494. Rotation stages

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

# 1495. Emergency key rotation

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

# 1496. Cryptographic audit events

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

# 1497. Cryptographic governance

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

# 1498. Compliance and FIPS mode

VoltStack podrá ofrecer perfiles criptográficos orientados a entornos regulados.

Un perfil FIPS deberá:

* utilizar proveedores validados cuando sea requerido;
* restringir algoritmos;
* bloquear configuraciones incompatibles;
* registrar el modo activo;
* impedir fallback silencioso.

---

# 1499. Post-quantum readiness

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

# 1500. Resultado de esta entrega

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

# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 16 de varias
**Cobertura:** Secciones **1501–1600**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 15`

---

# 1501. Public Key Infrastructure Architecture

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

# 1502. PKI security goals

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

# 1503. PKI threat model

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

# 1504. PKI architectural components

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

# 1505. PublicKeyInfrastructureService

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

# 1506. Certificate authority hierarchy

VoltStack deberá soportar jerarquías compuestas por:

* offline root CA;
* intermediate CAs;
* issuing CAs;
* tenant-specific CAs;
* workload CAs;
* code-signing CAs;
* recovery authorities.

---

# 1507. Root certificate authority

La root CA deberá:

* permanecer offline cuando sea viable;
* utilizarse únicamente para firmar intermediarias;
* residir en HSM;
* requerir quorum operacional;
* poseer procedimientos de recuperación;
* mantenerse fuera del tráfico ordinario.

---

# 1508. Intermediate certificate authority

Una intermediate CA deberá separar dominios de confianza como:

* producción;
* desarrollo;
* workloads;
* usuarios;
* dispositivos;
* firma de código;
* tenants regulados.

---

# 1509. Issuing certificate authority

Una issuing CA deberá emitir certificados finales bajo políticas limitadas y perfiles explícitos.

---

# 1510. CertificateAuthorityDefinition

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

# 1511. CertificateAuthorityType

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

# 1512. CertificateAuthorityState

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

# 1513. CA purpose separation

Una CA no deberá emitir certificados para propósitos no incluidos expresamente en su política.

Una CA de workloads no deberá emitir certificados para:

* usuarios humanos;
* firma de código;
* correo;
* documentos;
* certificados públicos web.

---

# 1514. CA private key protection

Las claves privadas de CA deberán:

* ser non-exportable;
* residir preferentemente en HSM;
* requerir autorización reforzada;
* tener controles duales;
* emitir auditoría por cada uso;
* rotarse bajo ceremonia controlada.

---

# 1515. Certificate profile architecture

Todo certificado deberá emitirse a partir de un perfil versionado.

---

# 1516. CertificateProfile

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

# 1517. CertificatePurpose

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

# 1518. Certificate profile controls

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

# 1519. Extended Key Usage restrictions

VoltStack deberá validar que el certificado posea el EKU correcto para la operación solicitada.

Un certificado de server authentication no deberá aceptarse automáticamente como client authentication.

---

# 1520. Key Usage restrictions

La validación deberá comprobar usos como:

* digitalSignature;
* keyEncipherment;
* keyAgreement;
* keyCertSign;
* cRLSign;
* contentCommitment.

---

# 1521. Certificate signing request

La emisión podrá iniciarse mediante un Certificate Signing Request.

---

# 1522. CertificateSigningRequest

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

# 1523. CSR validation

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

# 1524. CSR extension filtering

Las extensiones solicitadas no deberán copiarse directamente al certificado.

La CA deberá reconstruirlas desde la policy.

---

# 1525. Subject validation

Los datos del Subject deberán derivarse de fuentes confiables y no únicamente de input del solicitante.

---

# 1526. Subject Alternative Name

SAN deberá utilizarse para representar identidades modernas como:

* DNS names;
* IP addresses;
* URI identities;
* email identities;
* SPIFFE IDs;
* device identifiers.

---

# 1527. Common Name limitations

El Common Name no deberá utilizarse como sustituto de SAN para validación de identidad de red.

---

# 1528. Certificate issuance request

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

# 1529. Certificate issuance policy

```php
interface CertificateIssuancePolicyInterface
{
    public function evaluate(
        CertificateIssuanceRequest $request
    ): CertificateIssuanceDecision;
}
```

---

# 1530. CertificateIssuanceDecision

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

# 1531. Proof of possession

La CA deberá verificar que el solicitante controla la clave privada correspondiente a la clave pública del CSR.

---

# 1532. IssuedCertificate

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

# 1533. CertificateReference

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

# 1534. Certificate serial numbers

Los serial numbers deberán:

* ser únicos dentro de la CA;
* poseer entropía suficiente;
* no exponer secuencias sensibles;
* soportar indexación de revocación;
* conservarse como metadata auditable.

---

# 1535. Certificate lifetime policy

La validez deberá reducirse conforme aumente:

* sensibilidad;
* exposición;
* automatización;
* alcance;
* privilegio.

Los certificados de workload deberán tener vidas significativamente menores que certificados de infraestructura estática.

---

# 1536. Backdating restrictions

La fecha `notBefore` podrá incluir una tolerancia mínima para clock skew, pero no deberá utilizar backdating amplio.

---

# 1537. Certificate distribution

La distribución deberá:

* proteger la integridad;
* usar canales autenticados;
* evitar exponer private keys;
* confirmar identidad del receptor;
* registrar entrega.

---

# 1538. Private key generation models

VoltStack deberá soportar:

* generación local;
* generación dentro de HSM;
* generación dentro de TPM;
* generación en KMS;
* generación mediante agente de workload.

---

# 1539. Preferred private key model

Siempre que sea posible, la clave privada deberá generarse y permanecer en el componente que la utilizará.

---

# 1540. Certificate lifecycle

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

# 1541. Certificate inventory

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

# 1542. Certificate discovery

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

# 1543. Unknown certificate detection

Todo certificado no registrado pero encontrado en infraestructura administrada deberá generar una finding.

---

# 1544. Certificate ownership

Cada certificado deberá tener:

* owner técnico;
* owner de negocio cuando aplique;
* recurso asociado;
* mecanismo de renovación;
* contacto de incidente.

---

# 1545. Certificate renewal

La renovación deberá iniciarse antes de la expiración mediante una ventana definida por policy.

---

# 1546. CertificateRenewalRequest

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

# 1547. CertificateRenewalReason

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

# 1548. Renewal authorization

Renovar un certificado no deberá omitir:

* validación de identidad;
* validación de ownership;
* policy vigente;
* entitlement actual;
* SAN actuales;
* estado de la CA.

---

# 1549. Certificate key rotation

La renovación deberá preferir una nueva key pair cuando:

* el certificado expira;
* cambia de algoritmo;
* existe sospecha de exposición;
* la policy lo exige;
* el recurso cambia de owner.

---

# 1550. Overlapping certificate rotation

VoltStack deberá permitir una ventana de coexistencia para:

1. emitir nuevo certificado;
2. distribuirlo;
3. activar confianza;
4. migrar tráfico;
5. retirar el anterior;
6. validar ausencia de uso.

---

# 1551. Zero-downtime certificate rotation

La rotación automatizada deberá evitar interrupciones mediante:

* dual certificate support;
* trust overlap;
* hot reload;
* health verification;
* rollback controlado.

---

# 1552. Certificate revocation

Un certificado deberá revocarse cuando:

* la clave privada se comprometa;
* el subject deje de ser válido;
* cambie el ownership;
* se retire el workload;
* exista emisión incorrecta;
* se comprometa la CA;
* se incumpla policy.

---

# 1553. CertificateRevocationReason

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

# 1554. CertificateRevocationRecord

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

# 1555. Revocation publication

La revocación deberá publicarse mediante mecanismos compatibles con el perfil, como:

* Certificate Revocation Lists;
* Online Certificate Status Protocol;
* internal revocation registries;
* service mesh control planes.

---

# 1556. Certificate Revocation List

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

# 1557. CRL generation

Las CRLs deberán:

* firmarse;
* versionarse;
* incluir número secuencial;
* publicarse de forma autenticada;
* regenerarse al ocurrir revocaciones críticas;
* mantener intervalos apropiados.

---

# 1558. Delta CRLs

VoltStack podrá soportar delta CRLs para reducir el tamaño de actualizaciones frecuentes.

---

# 1559. OCSP architecture

```php
interface OcspResponderInterface
{
    public function respond(
        OcspRequest $request
    ): OcspResponse;
}
```

---

# 1560. OCSP response states

```php
enum OcspCertificateStatus: string
{
    case Good = 'good';
    case Revoked = 'revoked';
    case Unknown = 'unknown';
}
```

---

# 1561. OCSP responder security

El responder deberá:

* firmar respuestas;
* limitar replay;
* usar tiempos de validez cortos;
* proteger su signing key;
* registrar consultas anómalas;
* evitar filtrar metadata innecesaria.

---

# 1562. OCSP stapling

Para servicios TLS, VoltStack deberá favorecer OCSP stapling cuando el entorno y los clientes lo soporten.

---

# 1563. Revocation checking policy

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

# 1564. Revocation fail behavior

Para certificados privilegiados o internos de alto riesgo, la indisponibilidad del mecanismo de revocación deberá tender a fail-closed.

---

# 1565. Certificate validation pipeline

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

# 1566. CertificateValidationContext

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

# 1567. CertificateValidationResult

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

# 1568. CertificateValidationStatus

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

# 1569. Trust store architecture

VoltStack deberá administrar trust stores como recursos versionados y gobernados.

---

# 1570. TrustStore

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

# 1571. TrustStoreState

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

# 1572. Trust anchor governance

Agregar o retirar un trust anchor deberá requerir:

* aprobación;
* validación de fingerprint;
* verificación fuera de banda;
* evaluación de impacto;
* versión nueva;
* auditoría.

---

# 1573. Trust anchor pinning

Los trust anchors críticos deberán identificarse mediante fingerprints esperados y metadata protegida.

---

# 1574. Trust store isolation

Deberán existir trust stores separados para:

* producción;
* desarrollo;
* test;
* tenants;
* workloads;
* integraciones externas;
* code signing.

---

# 1575. Cross-environment trust prohibition

Producción no deberá confiar automáticamente en CAs de desarrollo o testing.

---

# 1576. Trust anchor rotation

La rotación deberá permitir coexistencia temporal entre:

* anchor anterior;
* anchor nuevo;
* cadenas emitidas por ambas jerarquías.

---

# 1577. Trust rotation stages

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

# 1578. Trust store rollback

Toda actualización deberá conservar capacidad de rollback mientras no exista evidencia de compromiso del anchor anterior.

---

# 1579. Mutual TLS architecture

VoltStack deberá soportar mTLS para autenticar ambos extremos de una conexión.

---

# 1580. MutualTlsIdentity

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

# 1581. mTLS authentication flow

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

# 1582. Client certificate authentication

Un certificado cliente deberá mapearse a una identidad mediante datos estables y gobernados.

---

# 1583. ClientCertificateIdentityMapper

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

# 1584. Unsafe certificate mapping prohibition

No deberá mapearse una identidad únicamente mediante:

* Common Name ambiguo;
* display name;
* email no verificado;
* subject string parcial;
* issuer no confiable.

---

# 1585. Certificate-to-identity binding

El binding deberá considerar:

* issuer;
* serial;
* SAN URI;
* trust domain;
* certificate profile;
* tenant;
* lifecycle state.

---

# 1586. Service-to-service authentication

Los servicios internos deberán autenticarse mediante identidades propias y no compartir credenciales humanas.

---

# 1587. ServiceIdentity

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

# 1588. Service identity authorization

La validación del certificado deberá completarse con autorización basada en:

* servicio;
* namespace;
* tenant;
* environment;
* operation;
* destination;
* audience.

---

# 1589. SPIFFE foundations

VoltStack podrá soportar SPIFFE para representar identidades de workloads mediante URI SAN.

Formato conceptual:

```text
spiffe://trust-domain/path/to/workload
```

---

# 1590. SpiffeIdentity

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

# 1591. SPIFFE ID validation

Un SPIFFE ID deberá validar:

* esquema URI;
* trust domain;
* path autorizado;
* tenant binding;
* selector mapping;
* workload state.

---

# 1592. SPIRE integration foundations

VoltStack podrá integrarse con SPIRE para:

* node attestation;
* workload attestation;
* SVID issuance;
* trust bundle distribution;
* rotation;
* workload identity discovery.

---

# 1593. Workload attestation

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

# 1594. Workload certificate

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

# 1595. Short-lived workload certificates

Los certificados de workload deberán:

* tener vida corta;
* rotarse automáticamente;
* no depender de renovación manual;
* utilizar claves efímeras cuando sea viable;
* revocarse al desaparecer el workload.

---

# 1596. Certificate pinning

El pinning podrá emplearse en contextos controlados, pero deberá diseñarse con:

* múltiples pins;
* backup keys;
* fecha de expiración;
* rotation plan;
* recovery mechanism;
* observabilidad.

El pinning rígido sin plan de rotación deberá evitarse.

---

# 1597. Code signing certificates

Los certificados de firma de código deberán:

* usar claves protegidas por hardware;
* requerir autorización reforzada;
* limitarse a pipelines confiables;
* registrar cada firma;
* prohibir exportación de clave;
* soportar revocación rápida.

---

# 1598. Timestamping authority

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

# 1599. PKI audit events

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

# 1600. Resultado de esta entrega

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

# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 17 de varias
**Cobertura:** Secciones **1601–1700**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 16`

---

# 1601. Secrets Management Architecture

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

# 1602. Secrets management security goals

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

# 1603. Secrets threat model

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

# 1604. Secrets architecture components

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

# 1605. SecretsManagementService

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

# 1606. SecretMaterial

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

# 1607. SecretType

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

# 1608. Secret classification

Toda entrada deberá clasificarse antes de almacenarse.

---

# 1609. SecretClassification

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

# 1610. Classification factors

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

# 1611. Secret ownership

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

# 1612. SecretRecord

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

# 1613. SecretLifecycleState

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

# 1614. Secret store abstraction

VoltStack deberá soportar múltiples proveedores sin acoplar el dominio a uno específico.

---

# 1615. SecretStore

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

# 1616. Supported secret stores

La arquitectura podrá integrar:

* Vault;
* cloud secret managers;
* KMS-backed stores;
* HSM-backed stores;
* operating system keyrings;
* Kubernetes secret providers;
* encrypted local development stores.

---

# 1617. Secret store selection policy

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

# 1618. SecretReference

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

# 1619. Opaque secret references

La referencia no deberá contener:

* valor secreto;
* credencial embebida;
* token;
* información reversible;
* metadatos innecesarios.

---

# 1620. Reference-only domain model

Controllers, Commands y Services deberán transportar referencias y no secretos cuando no sea indispensable resolverlos.

---

# 1621. Secret access policy

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

# 1622. SecretAccessDecision

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

# 1623. Secret access context

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

# 1624. Least privilege secret access

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

# 1625. Secret leasing

La entrega preferida será mediante leases de corta duración.

---

# 1626. SecretLease

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

# 1627. SecretLeaseState

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

# 1628. Lease duration

La duración deberá ser la mínima compatible con el caso de uso.

---

# 1629. Lease renewal

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

# 1630. Lease revocation

VoltStack deberá poder revocar un lease sin esperar su expiración natural.

---

# 1631. Dynamic secrets

El framework deberá favorecer secretos generados bajo demanda.

Ejemplos:

* credenciales temporales de base de datos;
* tokens cloud;
* certificados cortos;
* credenciales SSH efímeras;
* tokens de acceso scoped.

---

# 1632. DynamicSecretProvider

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

# 1633. DynamicSecretRequest

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

# 1634. Dynamic secret advantages

Los secretos dinámicos reducen:

* reutilización;
* exposición prolongada;
* distribución manual;
* credenciales compartidas;
* impacto de filtración.

---

# 1635. Dynamic database credentials

Las credenciales de base de datos dinámicas deberán:

* crear principal temporal;
* limitar permisos;
* limitar esquema;
* limitar duración;
* revocarse al expirar;
* registrar consultas administrativas cuando aplique.

---

# 1636. Dynamic cloud credentials

Las credenciales cloud temporales deberán limitar:

* account;
* role;
* region;
* service;
* resource;
* action;
* duration.

---

# 1637. Secret injection architecture

VoltStack deberá permitir inyectar secretos sin persistirlos innecesariamente.

---

# 1638. SecretInjectionService

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

# 1639. Secret injection targets

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

# 1640. Preferred injection models

Orden recomendado:

1. brokered use sin exposición;
2. in-memory injection;
3. ephemeral file;
4. environment variable solo cuando sea necesario.

---

# 1641. Brokered secret usage

Cuando sea posible, el consumidor deberá pedir la operación y no recibir directamente el secreto.

---

# 1642. Environment variable risks

Las variables de entorno pueden exponerse mediante:

* process inspection;
* crash dumps;
* debug tools;
* logs;
* child processes;
* platform metadata;
* accidental diagnostics.

---

# 1643. Environment variable policy

Su uso deberá:

* ser explícito;
* limitarse por entorno;
* evitar secretos críticos cuando existan alternativas;
* impedir logging;
* limpiar el entorno de procesos hijos;
* documentar la exposición.

---

# 1644. Ephemeral secret files

Los archivos temporales deberán:

* almacenarse en filesystem en memoria cuando sea viable;
* usar permisos mínimos;
* tener nombre no predecible;
* eliminarse al finalizar;
* no incluirse en backups;
* no persistirse en imágenes.

---

# 1645. Secret zeroization

VoltStack deberá minimizar el tiempo durante el cual un secreto permanece en memoria.

---

# 1646. SecretZeroizer

```php
interface SecretZeroizerInterface
{
    public function zeroize(
        SensitiveValue $value
    ): void;
}
```

---

# 1647. Zeroization limitations

PHP no garantiza control absoluto sobre copias internas, garbage collection o optimizaciones del runtime.

Por ello, VoltStack deberá complementar zeroization con:

* vida útil corta;
* menor número de copias;
* tipos sensibles;
* aislamiento de proceso;
* brokered operations;
* no serialización.

---

# 1648. SensitiveValue restrictions

`SensitiveValue` deberá:

* impedir conversión implícita a string;
* prohibir serialización;
* redactar `var_dump`;
* evitar inclusión en excepciones;
* exigir acceso explícito;
* limitar clonación.

---

# 1649. Secret redaction

VoltStack deberá registrar patrones conocidos para redactar secretos en:

* logs;
* traces;
* exceptions;
* profiler;
* debug output;
* HTTP dumps;
* job payloads.

---

# 1650. SecretRedactionService

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

# 1651. Redaction strategies

Se podrán utilizar:

* key-name matching;
* schema metadata;
* token pattern detection;
* exact secret fingerprints;
* structured field policies;
* entropy heuristics.

---

# 1652. Redaction false confidence

La redacción no deberá considerarse sustituto de evitar que el secreto llegue al sistema de logging.

---

# 1653. Configuration and secret separation

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

# 1654. Secret configuration resolver

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

# 1655. Configuration cache restrictions

Un secreto resuelto no deberá persistirse en:

* cache de configuración;
* archivos compilados;
* manifests;
* OPcache preload files;
* build artifacts.

---

# 1656. Build-time versus runtime secrets

VoltStack deberá distinguir:

* secretos necesarios durante build;
* secretos necesarios durante deploy;
* secretos necesarios en runtime.

---

# 1657. Build secret policy

Un secreto de build deberá:

* montarse temporalmente;
* no copiarse a capas;
* no quedar en historial;
* revocarse después;
* limitarse al job.

---

# 1658. Container image protection

No deberán incluirse secretos en:

* Dockerfile;
* image layers;
* image labels;
* build arguments persistentes;
* package metadata;
* startup scripts.

---

# 1659. CI/CD secret security

Los pipelines deberán:

* usar identidades federadas;
* evitar credenciales permanentes;
* limitar logs;
* restringir forks;
* separar entornos;
* aplicar approvals;
* rotar secretos tras incidentes.

---

# 1660. CiCdSecretPolicy

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

# 1661. Pull request secret protection

Pipelines originados desde código no confiable no deberán recibir secretos privilegiados.

---

# 1662. Environment separation

Los secretos de producción deberán estar aislados de:

* desarrollo;
* testing;
* preview environments;
* forks;
* personal sandboxes.

---

# 1663. Repository secret protection

VoltStack deberá incluir controles para impedir secretos en repositorios.

---

# 1664. RepositorySecretScanner

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

# 1665. Secret scanning scopes

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

# 1666. Secret detection techniques

Se deberán combinar:

* patrones;
* prefijos conocidos;
* checksums;
* entropy analysis;
* provider validation;
* contextual heuristics;
* exact fingerprint matching.

---

# 1667. SecretScanningFinding

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

# 1668. SecretFindingSeverity

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

# 1669. Validated secret detection

Cuando sea seguro, el sistema podrá validar con el proveedor si una credencial sigue activa.

La validación no deberá aumentar la exposición.

---

# 1670. Pre-commit scanning

VoltStack deberá ofrecer integración para bloquear commits que contengan secretos.

---

# 1671. Server-side repository scanning

La protección no deberá depender únicamente del desarrollador local.

---

# 1672. Historical secret exposure

Eliminar un secreto del commit más reciente no elimina su exposición histórica.

---

# 1673. Repository exposure response

Ante una filtración deberán ejecutarse:

1. revocar o rotar;
2. identificar uso;
3. preservar evidencia;
4. limpiar historial cuando corresponda;
5. actualizar consumidores;
6. revisar logs;
7. cerrar incidente.

---

# 1674. Secret exposure detection

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

# 1675. SecretExposureDetector

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

# 1676. Secret fingerprinting

El sistema podrá mantener fingerprints no reversibles para identificar secretos expuestos sin conservar el plaintext.

---

# 1677. Fingerprint key separation

Los fingerprints keyed deberán utilizar una clave dedicada, separada de cifrado y MAC de aplicaciones.

---

# 1678. Secret exposure event

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

# 1679. Exposure severity

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

# 1680. Automated secret containment

Para findings de alta confianza, el sistema podrá:

* revocar;
* rotar;
* deshabilitar integración;
* bloquear pipeline;
* suspender workload;
* abrir incidente;
* notificar owner.

---

# 1681. Secret rotation orchestration

La rotación deberá coordinar productor, store y consumidores.

---

# 1682. SecretRotationOrchestrator

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

# 1683. SecretRotationPlan

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

# 1684. SecretRotationStrategy

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

# 1685. Dual-secret rotation

Cuando el sistema lo permita:

1. crear nueva versión;
2. habilitar ambas;
3. actualizar consumidores;
4. verificar adopción;
5. deshabilitar anterior;
6. revocar anterior.

---

# 1686. Rotation dependency graph

VoltStack deberá conocer qué consumidores dependen de cada secreto.

---

# 1687. SecretConsumerBinding

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

# 1688. SecretConsumptionMode

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

# 1689. Rotation verification

Una rotación no deberá marcarse como completa hasta validar:

* nuevos consumidores;
* ausencia de errores;
* ausencia de uso de versión antigua;
* revocación efectiva;
* actualización de inventario.

---

# 1690. Secret revocation

La revocación deberá:

* invalidar nuevos accesos;
* cancelar leases;
* notificar consumidores;
* actualizar estado;
* generar evidencia;
* iniciar rotación cuando corresponda.

---

# 1691. SecretRevocationReason

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

# 1692. Secret recovery

La recuperación deberá diseñarse para evitar que el mecanismo de contingencia se convierta en una puerta trasera.

---

# 1693. SecretRecoveryPolicy

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

# 1694. Recovery controls

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

# 1695. Backup secret protection

Los backups deberán proteger:

* secretos almacenados;
* metadata;
* claves de wrapping;
* índices;
* audit trails;
* recovery material.

---

# 1696. Backup isolation

Los backups de secretos deberán:

* cifrarse con claves separadas;
* almacenarse en dominio distinto;
* limitar acceso;
* probar restauración;
* aplicar retención;
* registrar lecturas.

---

# 1697. Secret access auditing

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

# 1698. Secret governance

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

# 1699. Secrets audit events

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

# 1700. Resultado de esta entrega

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

# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 18 de varias
**Cobertura:** Secciones **1701–1800**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 17`

---

# 1701. Security Token Architecture

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

# 1702. Token security goals

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

# 1703. Token threat model

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

# 1704. Token architecture components

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

# 1705. SecurityTokenService

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

# 1706. Token classification

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

# 1707. SecurityTokenType

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

# 1708. TokenFormat

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

# 1709. TokenBearerModel

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

# 1710. Token format selection policy

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

# 1711. Opaque tokens

Los tokens opacos deberán contener únicamente un identificador aleatorio sin significado interpretable por el cliente.

---

# 1712. OpaqueTokenRecord

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

# 1713. Opaque token storage

El valor del token opaco no deberá almacenarse en plaintext.

El registro deberá conservar:

* hash;
* metadata;
* estado;
* referencias;
* expiración;
* bindings.

---

# 1714. Opaque token advantages

Los tokens opacos facilitan:

* revocación inmediata;
* introspection centralizada;
* menor exposición de claims;
* políticas dinámicas;
* control de sesión;
* ocultamiento de metadata.

---

# 1715. Opaque token limitations

Sus desventajas incluyen:

* dependencia del issuer;
* mayor latencia;
* necesidad de cache;
* riesgo de indisponibilidad del servicio de introspection.

---

# 1716. Structured tokens

Los tokens estructurados deberán utilizar formatos estandarizados y políticas estrictas.

---

# 1717. Structured token restrictions

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

# 1718. JWT governance

VoltStack deberá tratar JWT como un contenedor firmado, no como un mecanismo completo de autorización.

---

# 1719. JwtTokenProfile

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

# 1720. JWT algorithm allowlist

El algoritmo deberá resolverse desde policy y nunca aceptarse únicamente desde el header del token.

---

# 1721. JWT unsecured mode prohibition

Los JWT con:

```text
alg = none
```

deberán rechazarse siempre.

---

# 1722. Symmetric and asymmetric separation

VoltStack deberá evitar utilizar una clave pública como secreto HMAC o mezclar claves simétricas y asimétricas dentro del mismo perfil.

---

# 1723. JWT header policy

Los headers permitidos deberán limitarse y validarse.

Ejemplos:

* `alg`;
* `kid`;
* `typ`;
* `cty`;
* `crit`.

---

# 1724. Critical header validation

Los headers declarados en `crit` deberán ser comprendidos y procesados explícitamente o el token deberá rechazarse.

---

# 1725. Token type header

`typ` deberá utilizarse para reducir token confusion entre:

* access token;
* ID token;
* logout token;
* recovery token;
* delegation token.

---

# 1726. Nested tokens

Los tokens anidados deberán declarar explícitamente:

* formato externo;
* formato interno;
* orden de firma y cifrado;
* propósito;
* maximum nesting depth.

---

# 1727. Sign-then-encrypt policy

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

# 1728. Token encryption

La firma no oculta claims.

Los tokens con información sensible deberán:

* cifrarse;
* reemplazarse por opaque tokens;
* minimizar claims;
* evitarse en frontends no confiables.

---

# 1729. Token claims policy

```php
interface TokenClaimsPolicyInterface
{
    public function evaluate(
        TokenClaimsContext $context
    ): TokenClaimsDecision;
}
```

---

# 1730. TokenClaimsDecision

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

# 1731. Claim minimization

Los tokens deberán incluir únicamente los claims necesarios para el consumidor.

---

# 1732. Registered claims

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

# 1733. Issuer validation

El issuer deberá compararse mediante coincidencia exacta contra una configuración confiable.

---

# 1734. Issuer confusion prevention

No deberá seleccionarse automáticamente una configuración de validación basada únicamente en el `iss` recibido sin antes aplicar una allowlist segura.

---

# 1735. Audience restriction

Todo access token deberá tener una audiencia concreta.

---

# 1736. Audience validation

El recurso deberá verificar que su identificador esté incluido explícitamente en `aud`.

No deberá aceptar tokens destinados a otro servicio.

---

# 1737. Multi-audience token restrictions

Los tokens con múltiples audiencias deberán utilizarse solo cuando exista una necesidad documentada.

---

# 1738. Authorized party

Cuando existan múltiples audiencias, deberá validarse `azp` o un mecanismo equivalente cuando el protocolo lo requiera.

---

# 1739. Subject claim

`sub` deberá ser:

* estable dentro del issuer;
* no ambiguo;
* no reasignable;
* compatible con aislamiento multi-tenant.

---

# 1740. Tenant claim

Si el token incluye tenant, dicho claim deberá validarse contra:

* issuer;
* subject;
* audience;
* request context;
* resource tenancy.

---

# 1741. Cross-tenant token prohibition

Un token emitido para un tenant no deberá utilizarse en otro, aunque el subject comparta un identificador externo.

---

# 1742. Token lifetime policy

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

# 1743. Short-lived access tokens

Los access tokens deberán tener vidas cortas, especialmente cuando sean bearer tokens.

---

# 1744. Refresh token separation

Un refresh token no deberá aceptarse como access token ni presentarse a resource servers.

---

# 1745. One-time token lifetime

Los tokens de:

* recuperación;
* verificación;
* autorización;
* enlace mágico;

deberán tener expiración corta y consumo único.

---

# 1746. Clock skew

La tolerancia de reloj deberá:

* ser limitada;
* configurarse por perfil;
* no extender de forma significativa la validez;
* registrarse en validaciones anómalas.

---

# 1747. TokenLifecycleState

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

# 1748. Token identifier

Todo token revocable o replay-sensitive deberá poseer un identificador único.

---

# 1749. TokenReference

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

# 1750. Token binding

VoltStack deberá poder vincular tokens a:

* certificado;
* public key;
* device;
* session;
* client;
* workload;
* request proof.

---

# 1751. TokenBinding

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

# 1752. TokenBindingType

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

# 1753. Proof-of-possession tokens

Un proof-of-possession token deberá exigir evidencia de control de una clave adicional.

---

# 1754. PoP validation

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

# 1755. DPoP proof

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

# 1756. DPoP URI normalization

La comparación del target URI deberá seguir una canonicalización estricta para evitar mismatches y bypasses.

---

# 1757. DPoP replay registry

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

# 1758. mTLS-bound access tokens

Un token ligado a mTLS deberá incluir o referenciar el thumbprint del certificado cliente.

---

# 1759. Certificate rotation and bound tokens

La rotación del certificado deberá contemplar cómo se renuevan o invalidan tokens ligados a la clave anterior.

---

# 1760. Sender constraint enforcement

El resource server deberá verificar el sender constraint; no basta con que el authorization server lo haya emitido.

---

# 1761. Token exchange architecture

VoltStack deberá soportar intercambio controlado de tokens entre dominios o servicios.

---

# 1762. TokenExchangeRequest

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

# 1763. Token exchange policy

```php
interface TokenExchangePolicyInterface
{
    public function evaluate(
        TokenExchangeRequest $request
    ): TokenExchangeDecision;
}
```

---

# 1764. TokenExchangeDecision

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

# 1765. No privilege amplification

El token resultante no deberá tener más privilegios que:

* el subject token;
* el actor token;
* la policy;
* el client entitlement;
* el target resource policy.

---

# 1766. Token downscoping

Todo intercambio deberá preferir reducción de:

* audience;
* scope;
* lifetime;
* resource set;
* action set.

---

# 1767. DownscopedTokenRequest

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

# 1768. Delegation tokens

Un delegation token representa autoridad delegada por un subject a otro actor o servicio.

---

# 1769. DelegationTokenClaims

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

# 1770. Delegation semantics

La delegación deberá distinguir:

* quién es el propietario de la autoridad;
* quién ejecuta la acción;
* qué autoridad fue delegada;
* para qué recursos;
* durante cuánto tiempo.

---

# 1771. Delegation chain

Toda delegación encadenada deberá conservar provenance completo.

---

# 1772. Delegation depth

VoltStack deberá limitar la profundidad de delegación para evitar cadenas incontrolables.

---

# 1773. Delegation cycle prevention

No deberá permitirse una cadena de delegación que regrese a una identidad previa o genere ciclos.

---

# 1774. Delegation revocation

Revocar una delegación superior deberá invalidar delegaciones descendientes derivadas de ella.

---

# 1775. Impersonation tokens

Un impersonation token permite que un actor opere temporalmente como otro subject bajo controles reforzados.

---

# 1776. ImpersonationTokenClaims

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

# 1777. Impersonation requirements

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

# 1778. Actor and subject preservation

Durante impersonación, los logs y decisiones deberán conservar tanto:

* actor real;
* subject representado.

Nunca deberá sustituirse uno por otro.

---

# 1779. Impersonation restrictions

La impersonación podrá bloquear:

* cambio de credenciales;
* modificación de MFA;
* creación de nuevos tokens;
* acceso a secretos;
* aprobación de accesos propios;
* eliminación de auditoría;
* elevaciones privilegiadas adicionales.

---

# 1780. Support impersonation

Para soporte técnico deberá preferirse:

* read-only;
* scoped;
* time-limited;
* customer-visible;
* explicitly approved.

---

# 1781. Token chaining

Cuando un servicio llama a otro utilizando tokens derivados, VoltStack deberá conservar:

* original subject;
* current actor;
* delegation chain;
* source token reference;
* target audience.

---

# 1782. Actor token

Un actor token deberá representar la identidad del componente que ejecuta la operación, no sustituir al subject token.

---

# 1783. Service-to-service token policy

Los tokens de servicio deberán:

* tener audience específica;
* scope mínimo;
* lifetime corto;
* sender constraint cuando sea viable;
* identidad de workload;
* rotación automática.

---

# 1784. Workload token issuance

Los tokens de workload deberán emitirse a partir de:

* attestation;
* SPIFFE identity;
* mTLS identity;
* cloud workload federation;
* trusted runtime identity.

---

# 1785. User token reuse prohibition

Un servicio no deberá reutilizar directamente un token frontend para múltiples servicios internos sin token exchange o audience restriction adecuada.

---

# 1786. Token revocation

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

# 1787. TokenRevocationReason

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

# 1788. Token revocation registry

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

# 1789. Revocation propagation

La revocación deberá propagarse a:

* authorization servers;
* resource servers;
* gateways;
* session stores;
* token caches;
* introspection caches;
* downstream services.

---

# 1790. Revocation latency

Las políticas deberán definir cuánto tiempo máximo puede tardar una revocación en ser efectiva.

Para operaciones críticas, la propagación deberá ser casi inmediata.

---

# 1791. Token replay protection

La protección deberá considerar:

* `jti`;
* one-time consumption;
* sender constraints;
* nonce;
* replay registries;
* token family state;
* request binding.

---

# 1792. OneTimeTokenRegistry

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

# 1793. Refresh token reuse detection

Cuando un refresh token rotado vuelva a utilizarse, deberá asumirse posible compromiso de toda la familia.

---

# 1794. Token introspection

Los resource servers podrán consultar el estado de tokens opacos o revocables.

---

# 1795. TokenIntrospectionService

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

# 1796. TokenIntrospectionResult

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

# 1797. Introspection authorization

No cualquier cliente deberá poder introspectar cualquier token.

La respuesta deberá limitarse según:

* resource server;
* audience;
* tenant;
* client;
* token type;
* policy.

---

# 1798. Introspection caching

El cache deberá considerar:

* expiración;
* revocación;
* token sensitivity;
* maximum staleness;
* issuer state;
* key state.

Las respuestas inactivas críticas deberán evitar caches prolongados.

---

# 1799. Token audit events

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

# 1800. Resultado de esta entrega

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

# CONTROLLER_SECURITY_MODEL_PART_05.md

## Authentication, Session & Identity Security

**Documento:** Parte 05
**Entrega:** 19 de varias
**Cobertura:** Secciones **1801–1900**
**Continuación de:** `CONTROLLER_SECURITY_MODEL_PART_05.md — Entrega 18`

---

# 1801. API Authentication Architecture

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

# 1802. API authentication security goals

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

# 1803. API authentication threat model

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

# 1804. API authentication pipeline

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

# 1805. ApiAuthenticationService

```php
interface ApiAuthenticationServiceInterface
{
    public function authenticate(
        ApiAuthenticationRequest $request
    ): ApiAuthenticationResult;
}
```

---

# 1806. ApiAuthenticationRequest

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

# 1807. ApiAuthenticationResult

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

# 1808. ApiAuthenticationStatus

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

# 1809. API authentication scheme registry

VoltStack deberá registrar explícitamente los mecanismos soportados.

---

# 1810. ApiAuthenticationScheme

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

# 1811. Scheme resolution

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

# 1812. Route authentication metadata

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

# 1813. Multiple authentication schemes

Una ruta podrá:

* aceptar cualquiera de varios esquemas;
* requerir combinación de esquemas;
* exigir un mecanismo concreto;
* prohibir mecanismos heredados.

---

# 1814. Downgrade prevention

Un cliente configurado para mTLS o proof-of-possession no deberá degradarse silenciosamente a una API key bearer.

---

# 1815. API client identities

Todo consumidor deberá representarse mediante una identidad de cliente explícita.

---

# 1816. ApiClientIdentity

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

# 1817. ApiClientType

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

# 1818. ApiClientState

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

# 1819. Client ownership

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

# 1820. Client registration

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

# 1821. API key architecture

Las API keys deberán utilizarse únicamente cuando mecanismos más fuertes no sean viables o cuando el riesgo sea aceptable.

---

# 1822. ApiKeyCredential

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

# 1823. ApiKeyState

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

# 1824. API key format

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

# 1825. API key prefixes

El prefix deberá:

* facilitar lookup;
* no ser secreto;
* no contener permisos;
* no revelar tenant sensible;
* no permitir reconstruir la key;
* ser suficientemente único.

---

# 1826. API key secret generation

La parte secreta deberá generarse mediante CSPRNG y contener entropía suficiente para resistir ataques de guessing.

---

# 1827. API key one-time display

El valor completo deberá mostrarse una sola vez durante creación o rotación.

---

# 1828. API key hashing

VoltStack no deberá almacenar API keys completas en plaintext.

---

# 1829. ApiKeyHasher

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

# 1830. API key hash strategy

Podrá utilizarse:

* HMAC con pepper protegido;
* hash criptográfico keyed;
* password hashing en contextos específicos.

La selección deberá considerar volumen, latencia y riesgo de enumeración.

---

# 1831. API key lookup

El prefix podrá localizar un conjunto reducido de candidatos y la parte secreta deberá verificarse mediante comparación segura.

---

# 1832. API key enumeration protection

Las respuestas no deberán revelar si:

* el prefix existe;
* el cliente existe;
* la key expiró;
* la key fue revocada.

---

# 1833. API key scopes

Toda API key deberá poseer scopes explícitos.

---

# 1834. API key resource restrictions

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

# 1835. API key wildcard restrictions

Los scopes globales o wildcards deberán requerir aprobación reforzada.

---

# 1836. API key expiration

Las API keys deberán expirar salvo excepciones documentadas.

---

# 1837. API key inactivity policy

Las claves sin uso durante un período definido deberán:

* marcarse como dormant;
* notificarse;
* suspenderse;
* revocarse según policy.

---

# 1838. API key rotation

La rotación deberá admitir coexistencia controlada entre versión antigua y nueva.

---

# 1839. ApiKeyRotationService

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

# 1840. API key rotation stages

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

# 1841. API key usage telemetry

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

# 1842. HMAC request authentication

VoltStack deberá soportar autenticación de requests mediante firma HMAC.

---

# 1843. HmacApiCredential

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

# 1844. HmacCredentialState

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

# 1845. Signed request components

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

# 1846. Canonical request

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

# 1847. Canonicalization service

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

# 1848. Canonicalization determinism

Cliente y servidor deberán producir exactamente la misma representación para un request equivalente.

---

# 1849. URI canonicalization

La canonicalización deberá definir:

* encoding;
* path normalization;
* trailing slash;
* duplicate separators;
* dot segments;
* case sensitivity;
* percent-encoding.

---

# 1850. Query canonicalization

La policy deberá definir:

* ordenamiento;
* parámetros repetidos;
* valores vacíos;
* encoding;
* exclusiones;
* arrays.

---

# 1851. Header canonicalization

Deberá especificarse:

* nombres en lowercase;
* trimming;
* whitespace normalization;
* orden;
* headers obligatorios;
* headers prohibidos;
* combinación de valores repetidos.

---

# 1852. Request body digest

El body deberá resumirse mediante un hash criptográfico permitido.

---

# 1853. Streaming body verification

Para payloads grandes, VoltStack deberá permitir cálculo incremental sin cargar todo el contenido en memoria.

---

# 1854. SignedRequestProfile

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

# 1855. HMAC signature creation

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

# 1856. ApiRequestSignature

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

# 1857. Signature verification

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

# 1858. Timestamp validation

El timestamp deberá estar dentro de una ventana pequeña definida por policy.

---

# 1859. Timestamp source

El servidor deberá utilizar su propio reloj confiable para validar freshness.

---

# 1860. Clock synchronization

Los componentes críticos deberán mantener sincronización de reloj y alertar ante drift excesivo.

---

# 1861. Nonce validation

Cada request firmado deberá incluir un nonce único cuando la policy lo requiera.

---

# 1862. ApiRequestNonceRegistry

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

# 1863. Nonce replay prevention

Un nonce reutilizado dentro de la ventana de validez deberá provocar rechazo.

---

# 1864. Replay registry availability

Para operaciones críticas, la indisponibilidad del registry deberá producir fail-closed.

---

# 1865. Signature comparison

La firma deberá compararse mediante una operación constant-time.

---

# 1866. Key rotation for signed requests

Durante rotación, el cliente podrá indicar key version o credential ID, pero el servidor deberá limitar las versiones aceptadas.

---

# 1867. Asymmetric request signing

VoltStack podrá soportar firmas asimétricas para clientes que necesiten evitar secretos compartidos.

---

# 1868. AsymmetricApiCredential

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

# 1869. Asymmetric request proof

El cliente deberá demostrar control de la clave privada sin enviarla al servidor.

---

# 1870. Webhook authentication architecture

Todo webhook entrante deberá autenticarse.

---

# 1871. WebhookEndpointDefinition

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

# 1872. WebhookAuthenticationScheme

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

# 1873. WebhookEndpointState

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

# 1874. Webhook signature verification

La verificación deberá realizarse sobre el body original recibido antes de:

* parsearlo;
* normalizar JSON;
* transformar encoding;
* modificar whitespace;
* deserializar.

---

# 1875. WebhookSignatureVerifier

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

# 1876. IncomingWebhookRequest

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

# 1877. Webhook freshness

La firma deberá incorporar:

* timestamp;
* nonce;
* event ID;

cuando el proveedor lo soporte.

---

# 1878. Webhook replay registry

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

# 1879. Duplicate webhook delivery

La entrega duplicada legítima deberá diferenciarse de replay malicioso mediante procesamiento idempotente.

---

# 1880. Webhook idempotency

El event ID deberá utilizarse para impedir efectos de negocio duplicados.

---

# 1881. Webhook secret rotation

La rotación deberá permitir verificar temporalmente con:

* secret actual;
* secret anterior;
* version metadata.

---

# 1882. Webhook source IP restrictions

Las allowlists de IP podrán usarse como señal adicional, pero no deberán sustituir la verificación criptográfica.

---

# 1883. Outgoing webhook signing

VoltStack deberá firmar sus propios webhooks emitidos a terceros.

---

# 1884. OutgoingWebhookSigner

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

# 1885. Outgoing webhook metadata

Un webhook emitido deberá incluir:

* event ID;
* event type;
* timestamp;
* signature version;
* key ID;
* retry attempt;
* payload digest.

---

# 1886. mTLS API authentication

Las APIs de alta sensibilidad deberán poder exigir certificados cliente.

---

# 1887. mTLS API validation

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

# 1888. OAuth-protected APIs

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

# 1889. Scope-to-route mapping

```php
#[RequiresScopes(
    all: ['orders.read'],
    any: ['tenant.orders', 'admin.orders'],
)]
```

---

# 1890. Scope authorization limitations

Poseer un scope no deberá sustituir:

* ownership checks;
* tenant checks;
* resource policies;
* record-level authorization;
* business rules.

---

# 1891. Internal API trust

VoltStack no deberá considerar confiable una request únicamente porque proviene de una red interna.

---

# 1892. Internal service authentication

Los servicios internos deberán usar:

* workload identity;
* mTLS;
* sender-constrained tokens;
* signed requests;
* short-lived credentials.

---

# 1893. API gateway authentication

El gateway podrá realizar autenticación inicial, pero el backend deberá recibir evidencia verificable.

---

# 1894. Gateway authentication evidence

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

# 1895. Gateway bypass prevention

Los servicios protegidos por gateway deberán impedir acceso directo no autenticado mediante:

* network policy;
* mTLS;
* signed gateway assertions;
* private ingress;
* service mesh policy.

---

# 1896. Service mesh authentication

VoltStack podrá consumir identidades proporcionadas por un service mesh, siempre que:

* la conexión esté autenticada;
* el workload esté attested;
* el trust domain sea válido;
* la identidad sea autorizada;
* no se confíe en headers sin protección.

---

# 1897. API abuse detection

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

# 1898. API credential governance

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

# 1899. API authentication audit events

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

# 1900. Resultado de esta entrega

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
