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
