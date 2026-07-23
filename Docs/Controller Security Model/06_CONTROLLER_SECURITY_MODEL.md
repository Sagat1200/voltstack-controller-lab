# CONTROLLER_SECURITY_MODEL_PART_06.md

## Controller Authorization, Policy Enforcement & Resource Access Security

**Documento:** Parte 06
**Entrega:** 1 de varias
**Cobertura:** Secciones **1–100**
**Continuación conceptual de:** `CONTROLLER_SECURITY_MODEL_PART_05.md`

---

# 1. Propósito del documento

`CONTROLLER_SECURITY_MODEL_PART_06.md` define la arquitectura integral de autorización para los controladores, acciones, recursos y operaciones protegidas de VoltStack.

Esta parte establece cómo el framework deberá:

* representar permisos;
* evaluar políticas;
* determinar acceso;
* aplicar decisiones;
* proteger recursos;
* aislar tenants;
* controlar atributos y filas;
* gestionar delegaciones;
* elevar privilegios;
* explicar decisiones;
* observar y auditar autorizaciones;
* prevenir bypasses;
* mantener consistencia distribuida.

La autorización no deberá implementarse como una condición dispersa dentro de controladores, sino como un dominio arquitectónico central.

---

# 2. Alcance de la Parte 06

La Parte 06 cubrirá:

* authorization architecture;
* policy decision points;
* policy enforcement points;
* policy information points;
* policy administration points;
* RBAC;
* ABAC;
* ReBAC;
* capability-based authorization;
* resource ownership;
* contextual authorization;
* tenant authorization;
* row-level security;
* field-level security;
* action-level security;
* delegated authorization;
* privileged authorization;
* authorization caching;
* authorization consistency;
* bulk authorization;
* authorization observability;
* policy testing;
* governance;
* compliance;
* performance.

---

# 3. Objetivos arquitectónicos

La arquitectura deberá garantizar:

* deny by default;
* least privilege;
* explicit authorization;
* tenant isolation;
* context-aware decisions;
* centralized policy evaluation;
* distributed enforcement;
* deterministic behavior;
* explainability;
* auditability;
* revocation;
* scalability;
* extensibility;
* framework independence;
* secure failure modes.

---

# 4. Principio de autorización explícita

Toda operación protegida deberá requerir una decisión de autorización explícita.

La ausencia de una política, permiso o regla aplicable deberá resultar en denegación.

```text
No Policy
   ↓
No Authorization Basis
   ↓
Deny
```

---

# 5. Autenticación y autorización

VoltStack deberá separar estrictamente:

```text
Authentication
Who is the actor?

Authorization
What may the actor do?

Policy Enforcement
How is the decision applied?
```

Una identidad autenticada no deberá recibir acceso implícito a recursos.

---

# 6. Invariante principal de autorización

```text
Ninguna acción protegida podrá ejecutarse
sin una decisión válida, vigente, contextual,
tenant-scoped y aplicada por un enforcement point.
```

---

# 7. Authorization threat model

La arquitectura deberá considerar:

* broken access control;
* privilege escalation;
* insecure direct object references;
* tenant escape;
* authorization bypass;
* policy confusion;
* stale permissions;
* cache poisoning;
* resource substitution;
* context manipulation;
* confused deputy;
* delegation abuse;
* role explosion;
* over-privileged identities;
* hidden administrative paths;
* batch authorization inconsistencies;
* field-level data exposure;
* fail-open behavior.

---

# 8. Authorization trust boundaries

Deberán identificarse límites entre:

* HTTP request y controller;
* controller y authorization service;
* policy engine y data providers;
* tenant context y resource context;
* frontend y backend;
* application y external policy engine;
* cache y source of truth;
* administrator y protected resource;
* user session y elevated session;
* internal services y delegated callers.

---

# 9. Authorization domain model

El dominio deberá representar, como mínimo:

```text
Actor
Action
Resource
Environment
Tenant
Policy
Permission
Relationship
Capability
Decision
Obligation
Enforcement Result
```

---

# 10. Actor

Un actor representa la entidad que solicita ejecutar una acción.

Podrá ser:

* usuario;
* administrador;
* servicio;
* workload;
* API client;
* scheduled process;
* delegated identity;
* impersonated identity;
* anonymous principal;
* system principal.

---

# 11. AuthorizationActor

```php
interface AuthorizationActorInterface
{
    public function identifier(): string;

    public function type(): AuthorizationActorType;

    public function tenantId(): ?string;

    public function attributes(): array;

    public function authenticationContext(): AuthenticationContext;
}
```

---

# 12. AuthorizationActorType

```php
enum AuthorizationActorType: string
{
    case User = 'user';
    case Administrator = 'administrator';
    case Service = 'service';
    case Workload = 'workload';
    case ApiClient = 'api_client';
    case DelegatedIdentity = 'delegated_identity';
    case ImpersonatedIdentity = 'impersonated_identity';
    case Anonymous = 'anonymous';
    case System = 'system';
}
```

---

# 13. Action

Una acción representa la operación solicitada sobre un recurso.

Ejemplos:

* `view`;
* `create`;
* `update`;
* `delete`;
* `approve`;
* `publish`;
* `export`;
* `assign`;
* `impersonate`;
* `execute`;
* `transferOwnership`.

---

# 14. AuthorizationAction

```php
final readonly class AuthorizationAction
{
    public function __construct(
        public string $name,
        public string $namespace,
        public AuthorizationActionRisk $risk,
        public array $requiredCapabilities = [],
    ) {
    }

    public function qualifiedName(): string
    {
        return "{$this->namespace}:{$this->name}";
    }
}
```

---

# 15. AuthorizationActionRisk

```php
enum AuthorizationActionRisk: string
{
    case Low = 'low';
    case Moderate = 'moderate';
    case High = 'high';
    case Critical = 'critical';
    case Irreversible = 'irreversible';
}
```

---

# 16. Resource

Un recurso representa el objeto, entidad, servicio o capacidad sobre el cual se solicita acceso.

Podrá ser:

* modelo;
* aggregate;
* controller;
* action;
* route;
* file;
* tenant;
* field;
* collection;
* report;
* credential;
* account;
* workflow;
* infrastructure resource.

---

# 17. AuthorizationResource

```php
interface AuthorizationResourceInterface
{
    public function resourceType(): string;

    public function resourceId(): string;

    public function tenantId(): ?string;

    public function ownerId(): ?string;

    public function attributes(): array;

    public function securityClassification(): ResourceSecurityClassification;
}
```

---

# 18. ResourceSecurityClassification

```php
enum ResourceSecurityClassification: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Confidential = 'confidential';
    case Restricted = 'restricted';
    case HighlyRestricted = 'highly_restricted';
}
```

---

# 19. Environment

El entorno representa las condiciones externas bajo las cuales se solicita acceso.

Podrá incluir:

* tiempo;
* ubicación;
* red;
* dispositivo;
* aplicación;
* canal;
* riesgo;
* incident state;
* deployment environment;
* business period;
* request origin.

---

# 20. AuthorizationEnvironment

```php
final readonly class AuthorizationEnvironment
{
    public function __construct(
        public DateTimeImmutable $requestedAt,
        public string $application,
        public string $environment,
        public ?string $networkZone,
        public ?string $deviceId,
        public ?string $region,
        public float $riskScore,
        public array $attributes = [],
    ) {
    }
}
```

---

# 21. AuthorizationContext

El contexto deberá unir actor, acción, recurso, tenant y entorno.

---

# 22. AuthorizationRequest

```php
final readonly class AuthorizationRequest
{
    public function __construct(
        public string $requestId,
        public AuthorizationActorInterface $actor,
        public AuthorizationAction $action,
        public AuthorizationResourceInterface $resource,
        public AuthorizationEnvironment $environment,
        public TenantAuthorizationContext $tenantContext,
        public array $additionalContext = [],
    ) {
    }
}
```

---

# 23. TenantAuthorizationContext

```php
final readonly class TenantAuthorizationContext
{
    public function __construct(
        public ?string $tenantId,
        public TenantContextSource $source,
        public bool $verified,
        public array $membershipAttributes = [],
    ) {
    }
}
```

---

# 24. TenantContextSource

```php
enum TenantContextSource: string
{
    case Route = 'route';
    case Session = 'session';
    case Token = 'token';
    case Domain = 'domain';
    case Header = 'header';
    case ServiceContext = 'service_context';
    case Explicit = 'explicit';
}
```

---

# 25. Tenant context verification

El tenant context deberá validarse antes de la evaluación de acceso.

La validación deberá comprobar:

* existencia del tenant;
* membership del actor;
* estado del tenant;
* consistencia con la sesión;
* consistencia con el token;
* relación con el recurso;
* permiso de tenant switching.

---

# 26. Authorization architecture components

La arquitectura deberá separar:

```text
Policy Administration Point
Policy Information Point
Policy Decision Point
Policy Enforcement Point
Policy Audit Point
```

---

# 27. Policy Administration Point

El **Policy Administration Point**, o PAP, será responsable de:

* crear políticas;
* editarlas;
* validarlas;
* versionarlas;
* publicarlas;
* desactivarlas;
* administrar ownership;
* controlar lifecycle;
* mantener mappings.

---

# 28. Policy Information Point

El **Policy Information Point**, o PIP, proporcionará atributos necesarios para evaluar políticas.

Podrá obtener información de:

* identity store;
* role repository;
* relationship graph;
* resource repository;
* tenant directory;
* risk engine;
* device trust service;
* lifecycle service;
* compliance service;
* external providers.

---

# 29. Policy Decision Point

El **Policy Decision Point**, o PDP, evaluará políticas y producirá una decisión.

No deberá ejecutar directamente la acción protegida.

---

# 30. Policy Enforcement Point

El **Policy Enforcement Point**, o PEP, aplicará la decisión antes, durante o después de la operación.

Ejemplos:

* router;
* middleware;
* controller invoker;
* action dispatcher;
* ORM query builder;
* serializer;
* event subscriber;
* frontend manifest generator.

---

# 31. Policy Audit Point

El **Policy Audit Point** registrará:

* solicitud;
* políticas evaluadas;
* decision;
* obligations;
* enforcement;
* actor;
* resource;
* tenant;
* timestamp;
* policy version;
* decision latency.

---

# 32. Authorization flow

```text
Request
   ↓
Actor Resolution
   ↓
Tenant Resolution
   ↓
Resource Resolution
   ↓
Context Enrichment
   ↓
Policy Retrieval
   ↓
Policy Evaluation
   ↓
Decision
   ↓
Obligation Execution
   ↓
Enforcement
   ↓
Audit
```

---

# 33. AuthorizationService

```php
interface AuthorizationServiceInterface
{
    public function decide(
        AuthorizationRequest $request
    ): AuthorizationDecision;

    public function authorize(
        AuthorizationRequest $request
    ): AuthorizationEnforcementResult;

    public function authorizeMany(
        AuthorizationBatchRequest $request
    ): AuthorizationBatchResult;
}
```

---

# 34. AuthorizationDecision

```php
final readonly class AuthorizationDecision
{
    public function __construct(
        public string $decisionId,
        public AuthorizationDecisionEffect $effect,
        public array $matchedPolicies,
        public array $obligations,
        public array $advice,
        public array $reasonCodes,
        public string $policyDigest,
        public DateTimeImmutable $decidedAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

# 35. AuthorizationDecisionEffect

```php
enum AuthorizationDecisionEffect: string
{
    case Permit = 'permit';
    case Deny = 'deny';
    case NotApplicable = 'not_applicable';
    case Indeterminate = 'indeterminate';
    case Challenge = 'challenge';
    case PermitWithRestrictions = 'permit_with_restrictions';
}
```

---

# 36. Deny by default

Los efectos:

* `NotApplicable`;
* `Indeterminate`;
* ausencia de políticas;
* error de dependencia;
* conflicto no resuelto;

deberán convertirse en denegación, salvo una política explícita que determine un tratamiento más restrictivo.

---

# 37. Permit decision

Una decisión `Permit` deberá ser válida únicamente para:

* actor evaluado;
* acción evaluada;
* recurso evaluado;
* tenant evaluado;
* contexto evaluado;
* policy version evaluada;
* periodo de vigencia.

---

# 38. PermitWithRestrictions

Una decisión podrá permitir acceso bajo restricciones como:

* ocultar campos;
* limitar filas;
* restringir operaciones;
* exigir logging adicional;
* impedir exportación;
* aplicar watermark;
* reducir duración;
* prohibir delegación.

---

# 39. Challenge decision

Una decisión `Challenge` podrá requerir:

* MFA;
* reauthentication;
* approval;
* device verification;
* justification;
* elevated session;
* manager confirmation;
* security review.

---

# 40. Authorization obligations

Una obligación es una acción obligatoria asociada a la decisión.

Ejemplos:

* redactar un campo;
* registrar auditoría;
* limitar resultados;
* aplicar encryption;
* solicitar step-up;
* emitir notificación;
* añadir watermark;
* restringir descarga.

---

# 41. AuthorizationObligation

```php
final readonly class AuthorizationObligation
{
    public function __construct(
        public string $type,
        public array $parameters,
        public ObligationExecutionPhase $phase,
        public bool $mandatory,
    ) {
    }
}
```

---

# 42. ObligationExecutionPhase

```php
enum ObligationExecutionPhase: string
{
    case BeforeAction = 'before_action';
    case DuringAction = 'during_action';
    case AfterAction = 'after_action';
    case OnResponse = 'on_response';
    case OnFailure = 'on_failure';
}
```

---

# 43. Obligation failure

Si una obligación obligatoria no puede ejecutarse, la acción deberá denegarse o compensarse según policy.

Las obligaciones opcionales podrán producir advice sin bloquear la operación.

---

# 44. Authorization advice

El advice deberá representar recomendaciones no obligatorias.

Ejemplos:

* sugerir MFA;
* mostrar advertencia;
* recomendar revisión;
* indicar próxima expiración;
* sugerir reducción de privilegio.

---

# 45. Authorization reason codes

Las decisiones deberán utilizar códigos estables y machine-readable.

---

# 46. AuthorizationReasonCode

```php
enum AuthorizationReasonCode: string
{
    case ExplicitPermit = 'explicit_permit';
    case ExplicitDeny = 'explicit_deny';
    case MissingPermission = 'missing_permission';
    case MissingRole = 'missing_role';
    case TenantMismatch = 'tenant_mismatch';
    case ResourceOwnershipRequired = 'resource_ownership_required';
    case InsufficientAssurance = 'insufficient_assurance';
    case RiskTooHigh = 'risk_too_high';
    case PolicyNotApplicable = 'policy_not_applicable';
    case PolicyEvaluationFailed = 'policy_evaluation_failed';
    case RelationshipMissing = 'relationship_missing';
    case CapabilityMissing = 'capability_missing';
    case ResourceRestricted = 'resource_restricted';
    case SoDConflict = 'sod_conflict';
}
```

---

# 47. Public authorization response

Las respuestas públicas no deberán revelar:

* políticas internas;
* roles sensibles;
* relaciones ocultas;
* risk scores;
* tenant existence;
* resource existence;
* administrative structure;
* control configuration.

---

# 48. Internal decision explanation

Los operadores autorizados podrán acceder a una explicación más detallada para:

* troubleshooting;
* auditoría;
* testing;
* policy design;
* incident response;
* compliance.

---

# 49. AuthorizationDecisionExplanation

```php
final readonly class AuthorizationDecisionExplanation
{
    public function __construct(
        public string $decisionId,
        public array $evaluatedPolicies,
        public array $matchedRules,
        public array $failedConditions,
        public array $attributeSources,
        public array $conflicts,
        public array $finalReasonCodes,
    ) {
    }
}
```

---

# 50. Explanation security

La explicación deberá estar protegida por autorización propia y nunca incluir secretos, credenciales o atributos innecesarios.

---

# 51. Policy model

Una política deberá declarar:

* subject applicability;
* action applicability;
* resource applicability;
* conditions;
* effect;
* obligations;
* priority;
* combining algorithm;
* version;
* lifecycle state.

---

# 52. AuthorizationPolicy

```php
final readonly class AuthorizationPolicy
{
    public function __construct(
        public string $policyId,
        public string $name,
        public string $version,
        public AuthorizationPolicyTarget $target,
        public array $rules,
        public PolicyCombiningAlgorithm $combiningAlgorithm,
        public int $priority,
        public AuthorizationPolicyState $state,
        public DateTimeImmutable $effectiveAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

# 53. AuthorizationPolicyState

```php
enum AuthorizationPolicyState: string
{
    case Draft = 'draft';
    case Testing = 'testing';
    case Shadow = 'shadow';
    case Active = 'active';
    case Suspended = 'suspended';
    case Deprecated = 'deprecated';
    case Retired = 'retired';
}
```

---

# 54. AuthorizationPolicyTarget

```php
final readonly class AuthorizationPolicyTarget
{
    public function __construct(
        public array $actorTypes = [],
        public array $actions = [],
        public array $resourceTypes = [],
        public array $tenantScopes = [],
        public array $environmentConditions = [],
    ) {
    }
}
```

---

# 55. Policy target matching

Una política solo deberá evaluarse completamente cuando su target sea aplicable.

El target deberá permitir filtrar por:

* actor;
* action;
* resource;
* tenant;
* application;
* environment;
* risk level;
* assurance level.

---

# 56. Authorization rule

```php
final readonly class AuthorizationRule
{
    public function __construct(
        public string $ruleId,
        public AuthorizationDecisionEffect $effect,
        public AuthorizationConditionInterface $condition,
        public array $obligations = [],
        public array $advice = [],
        public int $priority = 0,
    ) {
    }
}
```

---

# 57. AuthorizationCondition

```php
interface AuthorizationConditionInterface
{
    public function evaluate(
        AuthorizationEvaluationContext $context
    ): AuthorizationConditionResult;
}
```

---

# 58. AuthorizationConditionResult

```php
final readonly class AuthorizationConditionResult
{
    public function __construct(
        public bool $matched,
        public array $reasonCodes = [],
        public array $evidence = [],
        public bool $indeterminate = false,
    ) {
    }
}
```

---

# 59. AuthorizationEvaluationContext

```php
final readonly class AuthorizationEvaluationContext
{
    public function __construct(
        public AuthorizationRequest $request,
        public array $actorAttributes,
        public array $resourceAttributes,
        public array $environmentAttributes,
        public array $relationships,
        public array $capabilities,
        public array $resolvedPolicies,
    ) {
    }
}
```

---

# 60. Policy combining algorithms

VoltStack deberá soportar distintos algoritmos para combinar reglas y políticas.

---

# 61. PolicyCombiningAlgorithm

```php
enum PolicyCombiningAlgorithm: string
{
    case DenyOverrides = 'deny_overrides';
    case PermitOverrides = 'permit_overrides';
    case FirstApplicable = 'first_applicable';
    case OnlyOneApplicable = 'only_one_applicable';
    case OrderedDenyOverrides = 'ordered_deny_overrides';
    case OrderedPermitOverrides = 'ordered_permit_overrides';
    case HighestPriority = 'highest_priority';
}
```

---

# 62. DenyOverrides

`DenyOverrides` deberá utilizarse como algoritmo predeterminado para dominios sensibles.

Cualquier regla aplicable con efecto `Deny` deberá prevalecer.

---

# 63. PermitOverrides

`PermitOverrides` solo deberá utilizarse cuando el dominio lo permita explícitamente y el riesgo esté documentado.

---

# 64. FirstApplicable

`FirstApplicable` deberá depender de un orden determinista y versionado.

No deberá utilizarse cuando la precedencia pueda resultar ambigua.

---

# 65. HighestPriority

Las prioridades deberán:

* estar acotadas;
* ser explícitas;
* ser auditables;
* evitar colisiones;
* respetar policy hierarchy.

---

# 66. Policy conflict

Existe conflicto cuando dos políticas aplicables producen efectos incompatibles sin resolución determinista.

---

# 67. PolicyConflict

```php
final readonly class PolicyConflict
{
    public function __construct(
        public string $conflictId,
        public array $policyIds,
        public array $effects,
        public PolicyConflictSeverity $severity,
        public array $resolutionCandidates,
    ) {
    }
}
```

---

# 68. PolicyConflictSeverity

```php
enum PolicyConflictSeverity: string
{
    case Informational = 'informational';
    case Warning = 'warning';
    case Blocking = 'blocking';
    case Critical = 'critical';
}
```

---

# 69. Conflict resolution

Los conflictos no resueltos deberán producir:

* `Deny`;
* audit event;
* telemetry;
* governance finding;
* policy owner notification.

---

# 70. Policy lifecycle

```text
Draft
  ↓
Validate
  ↓
Test
  ↓
Shadow
  ↓
Approve
  ↓
Publish
  ↓
Monitor
  ↓
Deprecate
  ↓
Retire
```

---

# 71. Policy validation

Antes de publicarse, una política deberá validarse respecto a:

* syntax;
* schema;
* references;
* target;
* conditions;
* conflicts;
* unreachable rules;
* effect;
* obligations;
* tenant scope;
* performance;
* security invariants.

---

# 72. AuthorizationPolicyValidator

```php
interface AuthorizationPolicyValidatorInterface
{
    public function validate(
        AuthorizationPolicy $policy,
        AuthorizationPolicyValidationContext $context
    ): AuthorizationPolicyValidationResult;
}
```

---

# 73. AuthorizationPolicyValidationResult

```php
final readonly class AuthorizationPolicyValidationResult
{
    public function __construct(
        public bool $valid,
        public array $errors,
        public array $warnings,
        public array $conflicts,
        public array $performanceRisks,
        public array $securityFindings,
    ) {
    }
}
```

---

# 74. Policy immutability

Una versión publicada de una política deberá ser inmutable.

Toda modificación deberá producir una nueva versión.

---

# 75. Policy version identity

La identidad de una policy version deberá incluir:

* policy ID;
* semantic version;
* content digest;
* publication timestamp;
* approver;
* effective period.

---

# 76. Policy publication

La publicación deberá ser:

* transaccional;
* auditable;
* reversible;
* propagable;
* tenant-aware;
* cache-aware;
* compatible con rollback.

---

# 77. Shadow policy evaluation

Una política en estado `Shadow` podrá evaluarse sin afectar la decisión final.

Deberá utilizarse para:

* validar impacto;
* medir falsos positivos;
* detectar conflictos;
* comparar versiones;
* preparar migraciones.

---

# 78. Shadow evaluation safety

Los resultados shadow no deberán:

* conceder acceso;
* bloquear operaciones;
* ejecutar obligaciones destructivas;
* modificar recursos;
* alterar sesiones.

---

# 79. Policy rollback

VoltStack deberá poder regresar a una versión anterior cuando:

* una policy bloquee acceso legítimo crítico;
* permita acceso indebido;
* produzca degradación;
* provoque conflictos;
* genere errores sistémicos.

---

# 80. RBAC architecture

VoltStack deberá soportar **Role-Based Access Control** como modelo base de asignación de permisos.

---

# 81. Role

Un rol agrupa permisos relacionados con una función organizacional o técnica.

---

# 82. AuthorizationRole

```php
final readonly class AuthorizationRole
{
    public function __construct(
        public string $roleId,
        public string $tenantId,
        public string $name,
        public array $permissions,
        public array $parentRoles,
        public AuthorizationRoleType $type,
        public AuthorizationRoleState $state,
    ) {
    }
}
```

---

# 83. AuthorizationRoleType

```php
enum AuthorizationRoleType: string
{
    case Business = 'business';
    case Technical = 'technical';
    case Administrative = 'administrative';
    case Privileged = 'privileged';
    case Service = 'service';
    case Temporary = 'temporary';
    case System = 'system';
}
```

---

# 84. AuthorizationRoleState

```php
enum AuthorizationRoleState: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Suspended = 'suspended';
    case Deprecated = 'deprecated';
    case Retired = 'retired';
}
```

---

# 85. Permission

Un permiso deberá representar una autorización atómica o suficientemente específica.

---

# 86. AuthorizationPermission

```php
final readonly class AuthorizationPermission
{
    public function __construct(
        public string $permissionId,
        public string $action,
        public string $resourceType,
        public array $constraints = [],
        public AuthorizationPermissionRisk $risk = AuthorizationPermissionRisk::Standard,
    ) {
    }
}
```

---

# 87. AuthorizationPermissionRisk

```php
enum AuthorizationPermissionRisk: string
{
    case Standard = 'standard';
    case Sensitive = 'sensitive';
    case Privileged = 'privileged';
    case Critical = 'critical';
}
```

---

# 88. Permission naming

La convención recomendada será:

```text
<domain>.<resource>.<action>
```

Ejemplos:

```text
billing.invoice.view
billing.invoice.approve
identity.user.disable
security.policy.publish
tenant.member.assign-role
```

---

# 89. Role assignment

```php
final readonly class RoleAssignment
{
    public function __construct(
        public string $assignmentId,
        public string $roleId,
        public string $actorId,
        public string $tenantId,
        public RoleAssignmentSource $source,
        public DateTimeImmutable $effectiveAt,
        public ?DateTimeImmutable $expiresAt,
        public array $constraints = [],
    ) {
    }
}
```

---

# 90. RoleAssignmentSource

```php
enum RoleAssignmentSource: string
{
    case Direct = 'direct';
    case Group = 'group';
    case Lifecycle = 'lifecycle';
    case Delegation = 'delegation';
    case TemporaryElevation = 'temporary_elevation';
    case Federation = 'federation';
    case System = 'system';
}
```

---

# 91. Time-bound role assignments

Los roles sensibles deberán favorecer asignaciones:

* temporales;
* aprobadas;
* purpose-bound;
* session-bound;
* automáticamente revocables.

---

# 92. Role hierarchy

VoltStack podrá soportar herencia de roles.

La jerarquía deberá:

* impedir ciclos;
* limitar profundidad;
* conservar tenant scope;
* detectar privilege amplification;
* ser auditable.

---

# 93. RoleHierarchyResolver

```php
interface RoleHierarchyResolverInterface
{
    public function resolve(
        string $roleId,
        string $tenantId
    ): ResolvedRoleHierarchy;
}
```

---

# 94. ResolvedRoleHierarchy

```php
final readonly class ResolvedRoleHierarchy
{
    public function __construct(
        public string $rootRoleId,
        public array $roles,
        public array $permissions,
        public int $depth,
        public string $digest,
    ) {
    }
}
```

---

# 95. Role cycle detection

La publicación o modificación de roles deberá rechazar ciclos como:

```text
Role A → Role B → Role C → Role A
```

---

# 96. Role explosion prevention

VoltStack deberá evitar que RBAC se convierta en una proliferación incontrolada de roles.

Deberá favorecer:

* roles base;
* constraints;
* attributes;
* relationships;
* capabilities;
* policy composition.

---

# 97. Static and contextual roles

Los roles deberán distinguirse entre:

* static assignments;
* computed roles;
* tenant roles;
* resource-scoped roles;
* session roles;
* temporary roles;
* contextual roles.

---

# 98. RBAC limitations

RBAC no deberá utilizarse como único modelo cuando la decisión dependa de:

* ownership;
* resource attributes;
* risk;
* environment;
* relationship;
* transaction value;
* geographic restrictions;
* dynamic tenant context.

---

# 99. Authorization audit events

Eventos iniciales recomendados:

* `AuthorizationRequested`;
* `AuthorizationDecisionProduced`;
* `AuthorizationPermitted`;
* `AuthorizationDenied`;
* `AuthorizationChallengeRequired`;
* `AuthorizationObligationExecuted`;
* `AuthorizationObligationFailed`;
* `AuthorizationPolicyCreated`;
* `AuthorizationPolicyValidated`;
* `AuthorizationPolicyPublished`;
* `AuthorizationPolicyRolledBack`;
* `AuthorizationPolicyConflictDetected`;
* `AuthorizationRoleCreated`;
* `AuthorizationRoleAssigned`;
* `AuthorizationRoleRevoked`;
* `AuthorizationPermissionGranted`;
* `AuthorizationPermissionRevoked`;
* `TenantAuthorizationMismatchDetected`.

---

# 100. Resultado de esta entrega

Esta entrega establece:

```text
Authorization Security Foundations
Authorization Threat Model
Actor-Action-Resource-Environment Model
Tenant Authorization Context
Policy Administration Point
Policy Information Point
Policy Decision Point
Policy Enforcement Point
Policy Audit Point
Authorization Requests
Authorization Decisions
Deny-by-Default
Permit With Restrictions
Authorization Challenges
Obligations and Advice
Reason Codes
Decision Explanations
Authorization Policies
Policy Targets
Authorization Rules
Combining Algorithms
Policy Conflict Resolution
Policy Lifecycle
Policy Validation
Policy Immutability
Shadow Evaluation
Policy Rollback
RBAC Architecture
Roles
Permissions
Role Assignments
Role Hierarchy
Role Cycle Detection
Role Explosion Prevention
Authorization Audit Events
```

# CONTROLLER_SECURITY_MODEL_PART_06.md

## Controller Authorization, Policy Enforcement & Resource Access Security

**Documento:** Parte 06
**Entrega:** 2 de varias
**Cobertura:** Secciones **101–200**

---

# 101. Advanced RBAC Architecture

El RBAC básico basado únicamente en:

```text
User → Role → Permission
```

no es suficiente para aplicaciones empresariales modernas.

VoltStack deberá implementar un modelo RBAC avanzado capaz de manejar:

* roles dinámicos;
* roles temporales;
* roles condicionados;
* roles jerárquicos;
* roles delegados;
* roles por tenant;
* roles por recurso;
* activación bajo demanda;
* restricciones contextuales.

---

# 102. Advanced RBAC model

El modelo extendido será:

```text
Identity
    ↓
Role Assignment
    ↓
Role Activation
    ↓
Role Constraints
    ↓
Permission Set
    ↓
Policy Evaluation
    ↓
Authorization Decision
```

---

# 103. RBAC entities

El sistema deberá separar:

* Role Definition;
* Role Assignment;
* Role Activation;
* Role Constraint;
* Permission Binding;
* Role Review;
* Role Ownership;
* Role Lifecycle.

---

# 104. Role Definition

Un rol define una agrupación lógica de permisos.

No representa directamente una autorización final.

---

# 105. Role Definition structure

```php
final readonly class RoleDefinition
{
    public function __construct(
        public string $roleId,
        public string $name,
        public string $tenantScope,
        public array $permissions,
        public array $constraints,
        public RoleCriticality $criticality,
        public RoleLifecycleState $state,
    ) {
    }
}
```

---

# 106. Role criticality

Los roles deberán clasificarse según impacto.

```php
enum RoleCriticality: string
{
    case Standard = 'standard';
    case Sensitive = 'sensitive';
    case Privileged = 'privileged';
    case Critical = 'critical';
}
```

---

# 107. Critical role examples

Ejemplos:

```text
security.admin
identity.admin
tenant.owner
billing.approver
database.operator
deployment.manager
audit.viewer
```

---

# 108. Critical role restrictions

Los roles críticos deberán requerir:

* aprobación;
* MFA reforzado;
* revisión periódica;
* justificación;
* monitoreo aumentado;
* expiración;
* segregación de funciones.

---

# 109. Role assignment constraints

Una asignación de rol podrá contener restricciones.

Ejemplos:

* solo horario laboral;
* solo determinado tenant;
* solo determinada aplicación;
* solo cierto rango de IP;
* solo dispositivos confiables;
* solo durante una emergencia.

---

# 110. RoleConstraint

```php
final readonly class RoleConstraint
{
    public function __construct(
        public string $constraintId,
        public string $type,
        public array $parameters,
        public bool $mandatory,
    ) {
    }
}
```

---

# 111. Constraint types

VoltStack deberá soportar:

```text
Temporal Constraint
Location Constraint
Network Constraint
Device Constraint
Tenant Constraint
Risk Constraint
Approval Constraint
Usage Constraint
Transaction Constraint
```

---

# 112. Temporal role constraint

Ejemplo:

```text
User
 └── Database Operator

Valid:
09:00 - 18:00

Invalid:
Outside schedule
```

---

# 113. Device-based role constraint

Un rol podrá requerir:

* dispositivo registrado;
* dispositivo confiable;
* posture válido;
* cifrado activo;
* versión mínima del cliente.

---

# 114. Network-based role constraint

Ejemplos:

Permitir:

```text
Corporate Network
VPN
Secure Gateway
```

Denegar:

```text
Unknown Public Network
Anonymous Proxy
```

---

# 115. Tenant scoped roles

Los roles deberán estar asociados al contexto donde tienen validez.

Ejemplo:

```text
Tenant A

Admin
 └── Users

Tenant B

Admin
 └── Users
```

Aunque el nombre sea igual, son roles diferentes.

---

# 116. Cross-tenant role prevention

Un rol asignado en:

```text
Tenant A
```

no deberá otorgar acceso automático en:

```text
Tenant B
```

---

# 117. Role activation model

Los roles privilegiados no deberán estar permanentemente activos.

---

# 118. Just-In-Time Role Activation

Modelo:

```text
User
 ↓
Request Elevation
 ↓
Approval
 ↓
Temporary Role Activation
 ↓
Session Privilege
 ↓
Automatic Expiration
```

---

# 119. RoleActivationRequest

```php
final readonly class RoleActivationRequest
{
    public function __construct(
        public string $requestId,
        public string $actorId,
        public string $roleId,
        public string $tenantId,
        public string $reason,
        public DateTimeImmutable $requestedAt,
        public ?DateTimeImmutable $requestedExpiration,
    ) {
    }
}
```

---

# 120. RoleActivationDecision

```php
final readonly class RoleActivationDecision
{
    public function __construct(
        public bool $approved,
        public string $activationId,
        public array $conditions,
        public DateTimeImmutable $expiresAt,
        public array $approvers,
    ) {
    }
}
```

---

# 121. Activation requirements

Una activación privilegiada podrá requerir:

* MFA;
* approval;
* ticket reference;
* business justification;
* security approval;
* manager approval;
* device verification.

---

# 122. Activation lifetime

Toda activación deberá tener:

* inicio;
* expiración;
* owner;
* purpose;
* audit trail;
* automatic revocation.

---

# 123. Role session binding

Un rol temporal podrá vincularse a una sesión específica.

Ejemplo:

```text
Session X

Has:
database.operator

Session Y

Does not have:
database.operator
```

---

# 124. Session role model

```php
final readonly class SessionRole
{
    public function __construct(
        public string $sessionId,
        public string $roleId,
        public DateTimeImmutable $activatedAt,
        public DateTimeImmutable $expiresAt,
        public string $purpose,
    ) {
    }
}
```

---

# 125. Session role revocation

Debe ocurrir cuando:

* termina la sesión;
* expira el tiempo;
* cambia el contexto;
* cambia el riesgo;
* se revoca el privilegio;
* ocurre incidente.

---

# 126. Dynamic roles

Los roles dinámicos son roles calculados mediante atributos.

Ejemplo:

```text
Employee
+
Department = Finance
+
Location = Mexico
+
EmploymentStatus = Active

=
Finance Analyst Role
```

---

# 127. DynamicRoleResolver

```php
interface DynamicRoleResolverInterface
{
    public function resolve(
        AuthorizationActorInterface $actor,
        AuthorizationEnvironment $environment
    ): array;
}
```

---

# 128. Dynamic role risks

Los roles dinámicos deberán controlar:

* cambios de atributos;
* inconsistencias;
* atributos falsificados;
* cache stale;
* propagación retrasada;
* privilege accumulation.

---

# 129. Dynamic role freshness

Los atributos usados para calcular roles deberán tener:

* timestamp;
* source;
* confidence;
* expiration;
* verification status.

---

# 130. Role expiration

Los roles deberán poder expirar por:

* tiempo;
* cambio organizacional;
* lifecycle event;
* risk change;
* policy update;
* tenant change.

---

# 131. Role inheritance

La herencia permite reutilizar permisos.

Ejemplo:

```text
Senior Developer

inherits:

Developer

inherits:

Employee
```

---

# 132. Role inheritance restrictions

La herencia deberá impedir:

* ciclos;
* escalamiento accidental;
* herencia cross-tenant;
* privilegios ocultos;
* profundidad excesiva.

---

# 133. Role inheritance analysis

Antes de activar un rol:

VoltStack deberá poder responder:

```text
¿Qué permisos efectivos obtiene este rol?
```

---

# 134. Effective Permission Resolver

```php
interface EffectivePermissionResolverInterface
{
    public function resolve(
        string $roleId,
        string $tenantId
    ): EffectivePermissionSet;
}
```

---

# 135. EffectivePermissionSet

```php
final readonly class EffectivePermissionSet
{
    public function __construct(
        public array $permissions,
        public array $inheritedRoles,
        public array $constraints,
        public string $digest,
    ) {
    }
}
```

---

# 136. Permission composition

Los permisos deberán poder combinar:

* acciones;
* recursos;
* condiciones;
* restricciones;
* obligaciones.

---

# 137. Permission expression

Ejemplo:

```text
invoice.update

where:

invoice.owner == user.id

AND

tenant == user.tenant

AND

risk < medium
```

---

# 138. Negative permissions

VoltStack deberá soportar denegaciones explícitas.

Ejemplo:

```text
Role:

document.viewer

Permission:

document.view


Deny:

document.view.confidential
```

---

# 139. Negative permission precedence

Las denegaciones deberán prevalecer cuando exista conflicto.

---

# 140. Permission conflict

Ejemplo:

```text
Role A

Allow:
delete.user


Role B

Deny:
delete.user
```

Resultado:

```text
DENY
```

---

# 141. Permission governance

Los permisos deberán tener:

* owner;
* descripción;
* riesgo;
* clasificación;
* uso esperado;
* fecha creación;
* última revisión.

---

# 142. Permission lifecycle

```text
Created
 ↓
Reviewed
 ↓
Active
 ↓
Restricted
 ↓
Deprecated
 ↓
Removed
```

---

# 143. Permission discovery

VoltStack deberá permitir analizar:

* permisos sin uso;
* permisos excesivos;
* permisos duplicados;
* permisos peligrosos;
* permisos heredados.

---

# 144. Role mining

El framework podrá analizar patrones para descubrir roles.

---

# 145. Role mining inputs

Podrán utilizarse:

* usage patterns;
* existing assignments;
* resource access;
* departments;
* applications;
* workflows;
* historical approvals.

---

# 146. Role mining output

Ejemplo:

```text
Detected Role:

Financial Reviewer

Permissions:

invoice.view
invoice.comment
report.export
```

---

# 147. Role mining safeguards

El resultado deberá ser:

* sugerencia;
* no asignación automática;
* revisado;
* aprobado;
* auditado.

---

# 148. Role optimization

El sistema deberá detectar:

* roles demasiado amplios;
* roles duplicados;
* permisos nunca usados;
* privilege creep;
* toxic combinations.

---

# 149. Privilege creep detection

Ejemplo:

```text
Employee

2024:
10 permissions

2026:
120 permissions

Usage:
5 permissions
```

Debe generar revisión.

---

# 150. Role governance model

Cada rol deberá tener:

* owner;
* purpose;
* permissions;
* risk;
* reviewers;
* lifecycle;
* certification cycle.

---

# 151. Role owner

El owner deberá ser responsable de:

* mantener definición;
* revisar permisos;
* justificar existencia;
* aprobar cambios;
* retirar roles obsoletos.

---

# 152. Role certification

Los roles críticos deberán revisarse periódicamente.

---

# 153. Role certification event

```php
final readonly class RoleCertification
{
    public function __construct(
        public string $roleId,
        public string $reviewer,
        public bool $approved,
        public ?string $justification,
        public DateTimeImmutable $reviewedAt,
    ) {
    }
}
```

---

# 154. Role approval workflow

```text
Request
 ↓
Owner Review
 ↓
Security Review
 ↓
Approval
 ↓
Publication
```

---

# 155. RBAC limitations

RBAC no resuelve completamente:

* ownership;
* dynamic context;
* risk;
* relationships;
* resource state;
* business rules.

Por eso VoltStack deberá soportar ABAC.

---

# 156. ABAC Architecture Introduction

ABAC permite tomar decisiones usando atributos.

Modelo:

```text
Subject Attributes
+
Resource Attributes
+
Action Attributes
+
Environment Attributes

=
Authorization Decision
```

---

# 157. ABAC components

VoltStack deberá implementar:

* attribute providers;
* attribute normalization;
* attribute resolver;
* attribute trust management;
* attribute policies;
* condition evaluator;
* decision engine.

---

# 158. ABAC model

```text
Subject
    |
Attributes
    |
Policy Engine
    |
Decision
    |
Resource Enforcement
```

---

# 159. Subject attributes

Representan características del actor.

Ejemplos:

* department;
* clearance;
* employment status;
* tenant;
* location;
* risk level;
* assurance level;
* device trust.

---

# 160. SubjectAttribute

```php
final readonly class SubjectAttribute
{
    public function __construct(
        public string $name,
        public mixed $value,
        public string $source,
        public AttributeTrustLevel $trust,
        public DateTimeImmutable $issuedAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
```

---

# 161. Resource attributes

Representan características del recurso.

Ejemplos:

* owner;
* classification;
* tenant;
* sensitivity;
* lifecycle state;
* business domain.

---

# 162. ResourceAttribute

```php
final readonly class ResourceAttribute
{
    public function __construct(
        public string $name,
        public mixed $value,
        public string $source,
        public AttributeTrustLevel $trust,
    ) {
    }
}
```

---

# 163. Action attributes

Representan características de la operación.

Ejemplos:

* destructive;
* financial;
* export;
* privileged;
* requires approval;
* irreversible.

---

# 164. Environment attributes

Representan contexto.

Ejemplos:

* time;
* location;
* network;
* device;
* threat level;
* incident state.

---

# 165. AttributeTrustLevel

```php
enum AttributeTrustLevel: string
{
    case Unknown = 'unknown';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Verified = 'verified';
}
```

---

# 166. Attribute source governance

Cada atributo deberá conocer:

* origen;
* propietario;
* fecha;
* confianza;
* método de validación;
* expiración.

---

# 167. Attribute freshness

Los atributos sensibles deberán tener TTL.

Ejemplo:

```text
DeviceTrust

TTL:
15 minutes
```

---

# 168. Stale attribute handling

Un atributo expirado podrá:

* ignorarse;
* refrescarse;
* degradar confianza;
* bloquear acceso.

---

# 169. Attribute normalization

Diferentes fuentes deberán normalizar:

```text
country
MX

México

Mexico
```

a una representación común.

---

# 170. Attribute conflict resolution

Cuando existan múltiples valores:

```text
HR System:
active

Identity Provider:
disabled
```

deberá existir una política de resolución.

---

# 171. Attribute conflict rules

Podrán usar:

* source priority;
* timestamp;
* trust level;
* manual review;
* deny on conflict.

---

# 172. Attribute resolver

```php
interface AttributeResolverInterface
{
    public function resolve(
        string $attribute,
        AuthorizationContext $context
    ): ResolvedAttribute;
}
```

---

# 173. ResolvedAttribute

```php
final readonly class ResolvedAttribute
{
    public function __construct(
        public string $name,
        public mixed $value,
        public string $source,
        public AttributeTrustLevel $trust,
        public bool $fresh,
    ) {
    }
}
```

---

# 174. ABAC condition language

VoltStack deberá definir una representación segura de condiciones.

---

# 175. Condition example

```text
subject.department == "finance"

AND

resource.classification <= subject.clearance

AND

environment.deviceTrust == "verified"
```

---

# 176. Condition operators

Soportar:

* equals;
* not equals;
* contains;
* starts with;
* in;
* greater than;
* less than;
* exists;
* matches;
* temporal operators.

---

# 177. Condition security

El lenguaje deberá impedir:

* ejecución arbitraria;
* acceso directo a memoria;
* llamadas externas;
* side effects;
* loops infinitos;
* data exfiltration.

---

# 178. ABAC Policy Example

```text
Permit

Action:
document.view

When:

subject.clearance >= resource.classification

AND

subject.tenant == resource.tenant
```

---

# 179. ABAC evaluation

El motor deberá:

1. Resolver atributos.
2. Validar confianza.
3. Evaluar condiciones.
4. Generar decisión.
5. Ejecutar obligaciones.
6. Registrar evidencia.

---

# 180. ABAC failure behavior

Si falta un atributo crítico:

```text
Unknown Attribute
        ↓
Indeterminate
        ↓
Deny
```

---

# 181. ABAC performance considerations

Deberá soportar:

* attribute caching;
* prefetch;
* batching;
* lazy evaluation;
* short circuit;
* compiled policies.

---

# 182. Attribute cache security

El cache deberá incluir:

* tenant scope;
* TTL;
* source version;
* integrity;
* invalidation events.

---

# 183. ABAC observability

Debe registrar:

* atributos utilizados;
* fuentes;
* decisión;
* tiempo;
* policy version;
* failures.

---

# 184. ABAC testing

Las pruebas deberán cubrir:

* missing attributes;
* conflicting attributes;
* stale attributes;
* manipulated attributes;
* boundary conditions.

---

# 185. RBAC + ABAC hybrid model

VoltStack deberá permitir combinar:

```text
Role Permission

+

Attribute Conditions

+

Resource Rules

=

Final Decision
```

---

# 186. Hybrid authorization example

```text
Role:
manager

Permission:
invoice.approve


Condition:

invoice.amount < 10000

AND

device.trust == high
```

---

# 187. Hybrid evaluation order

Orden recomendado:

```text
Tenant Check
 ↓
Identity Check
 ↓
Role Check
 ↓
Permission Check
 ↓
Attribute Check
 ↓
Relationship Check
 ↓
Final Policy Decision
```

---

# 188. Authorization model selection

El framework deberá permitir elegir:

* RBAC;
* ABAC;
* ReBAC;
* capability;
* hybrid.

---

# 189. Authorization model metadata

Cada recurso podrá declarar:

```php
AuthorizationModel::Hybrid
```

o:

```php
AuthorizationModel::ABAC
```

---

# 190. AuthorizationModel

```php
enum AuthorizationModel: string
{
    case RBAC = 'rbac';
    case ABAC = 'abac';
    case ReBAC = 'rebac';
    case Capability = 'capability';
    case Hybrid = 'hybrid';
}
```

---

# 191. Resource authorization strategy

Cada recurso deberá definir:

* model;
* policies;
* required attributes;
* ownership rules;
* tenant rules;
* audit requirements.

---

# 192. AuthorizationStrategy

```php
final readonly class AuthorizationStrategy
{
    public function __construct(
        public AuthorizationModel $model,
        public array $policies,
        public array $requiredAttributes,
        public array $obligations,
    ) {
    }
}
```

---

# 193. Future extensions

La arquitectura permitirá añadir:

* ReBAC;
* graph authorization;
* capability security;
* contextual authorization;
* AI-assisted policy analysis;
* continuous authorization.

---

# 194. Authorization audit events

Eventos adicionales:

```text
RoleActivated
RoleExpired
RoleConstraintFailed
RoleElevationRequested
RoleElevationApproved
RoleElevationDenied
PermissionResolved
PermissionConflictDetected
AttributeResolved
AttributeTrustChanged
AttributeExpired
ABACPolicyEvaluated
ABACConditionFailed
HybridAuthorizationEvaluated
```

---

# 195. Security invariants

Debe mantenerse:

```text
No Role
+
No Permission
+
No Valid Attribute Context

=

No Access
```

---

# 196. Arquitectural outcome

Esta entrega establece:

```text
Advanced RBAC
Role Constraints
JIT Privilege Activation
Dynamic Roles
Role Governance
Permission Composition
Negative Permissions
Role Mining
Privilege Creep Detection
ABAC Architecture
Attribute Management
Attribute Trust
Attribute Freshness
Attribute Resolution
ABAC Conditions
Hybrid Authorization
```

---

# 197. Próxima entrega

`CONTROLLER_SECURITY_MODEL_PART_06 Entrega 3`

Continuará con:

```text
- ReBAC architecture
- Relationship graph security
- Relationship tuples
- Graph authorization engine
- Ownership authorization
- Delegated authorization
- Capability-based security
- Resource ownership models
- Field-level authorization
- Row-level authorization
- Data filtering authorization
```
