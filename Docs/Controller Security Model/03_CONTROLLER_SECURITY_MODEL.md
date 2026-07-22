# Controller Security Model - Part 03: Compilation & Artifact Security

**Versión:** 1.0
**Estado:** Draft arquitectónico
**Módulo:** `VoltStack\Quantum\Controllers\Security\Compilation`
**Ámbito:** Seguridad del proceso de compilación, generación de código, artefactos, manifests, firmas, almacenamiento, activación, rollback, OPcache, preload, despliegue distribuido y cadena de suministro
**Dependencias principales:**

* `CONTROLLER_SECURITY_MODEL.md`
* `CONTROLLER_SECURITY_MODEL_PART_02.md`
* `CONTROLLER_COMPILATION_FRAMEWORK.md`
* `CONTROLLER_METADATA_ENGINE.md`
* `CONTROLLER_PARAMETER_RESOLUTION_ENGINE.md`
* `CONTROLLER_INTERCEPTOR_SYSTEM.md`
* `CONTROLLER_INVOKER.md`
* `CONTROLLER_LIFECYCLE.md`

---

## 1. Introducción

La compilación del subsistema Controllers transforma definiciones dinámicas en artefactos optimizados para producción.

Estos artefactos podrán contener:

* targets de controladores;
* planes de resolución;
* metadata normalizada;
* planes de parámetros;
* orden de interceptores;
* planes de autorización;
* estrategias de invocación;
* transformadores de resultado;
* mappings de excepciones;
* referencias de transporte;
* fingerprints;
* información de build.

La compilación mejora:

* rendimiento;
* determinismo;
* validación anticipada;
* reducción de reflexión;
* arranque de Workers;
* integración con OPcache;
* preload.

Sin embargo, también introduce una superficie crítica:

```text
Código generado
    +
Artefactos persistentes
    +
Manifests
    +
Caches
    +
Build activation
    =
Código ejecutable confiado por producción
```

Una alteración en esta cadena podría provocar ejecución arbitraria, bypass de autorización, carga de metadata obsoleta o contaminación entre builds.

---

## 2. Objetivo principal

Garantizar que todo artefacto usado por Controllers:

* sea producido por compiladores autorizados;
* corresponda a fuentes válidas;
* pertenezca al build activo;
* mantenga integridad verificable;
* no incluya referencias inseguras;
* sea cargado desde rutas controladas;
* sea compatible con el runtime;
* no haya sido revocado;
* no pueda sustituirse silenciosamente;
* mantenga equivalencia de seguridad con el modo dinámico.

---

## 3. Principios fundamentales

El modelo seguirá:

* trusted build pipeline;
* immutable artifacts;
* content integrity;
* explicit build identity;
* atomic activation;
* deny unknown artifacts;
* no runtime code generation in production;
* reproducibility where possible;
* least-privilege compilers;
* separation of build and runtime;
* safe rollback;
* downgrade protection;
* cache isolation;
* fail closed;
* auditable deployment.

---

## 4. Artefactos como código ejecutable

Todo artefacto PHP generado se tratará como código ejecutable.

No deberá considerarse un simple archivo de caché.

Por ello requerirá controles equivalentes a:

* código fuente;
* dependencias;
* binarios;
* configuración sensible.

---

## 5. Trust boundary principal

```text
Source Code
    ↓
Compiler Inputs
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
Artifact Loader
    ↓
Runtime Execution
```

Cada transición deberá validarse.

---

## 6. Activos protegidos

Los principales activos serán:

* source definitions;
* compiler registry;
* normalized metadata;
* compiled plans;
* generated PHP files;
* artifact fingerprints;
* signatures;
* manifests;
* build IDs;
* active build pointer;
* preload files;
* OPcache state;
* artifact stores;
* remote caches;
* deployment credentials;
* signing keys;
* revocation lists.

---

## 7. Actores

```text
Application developer
Package developer
Compiler implementation
Build process
CI system
Deployment system
Artifact store
Runtime Worker
System operator
Remote cache
Malicious insider
Compromised package
Compromised build agent
```

---

## 8. Categorías de amenazas

Amenazas principales:

* compiler tampering;
* source substitution;
* artifact modification;
* manifest poisoning;
* path traversal;
* arbitrary include;
* build confusion;
* stale artifact loading;
* downgrade;
* replay;
* signature bypass;
* cache poisoning;
* remote cache compromise;
* partial deployment;
* OPcache inconsistency;
* preload mismatch;
* package compiler abuse;
* symlink attacks;
* permission escalation.

---

## 9. Security invariant principal

```text
No artifact shall execute unless:
    artifact is expected
    artifact belongs to active build
    artifact integrity is valid
    artifact schema is valid
    artifact is compatible
    artifact is not revoked
```

---

## 10. Compilation Trust Model

Los componentes se clasificarán por confianza:

```text
Untrusted source-derived data
Semi-trusted application definitions
Trusted core compilers
Privileged build activator
Privileged signer
Trusted runtime loader
```

---

## 11. Source-derived input

Se considerarán potencialmente no confiables:

* attribute arguments;
* package metadata;
* configuration values;
* route aliases;
* service IDs;
* class strings;
* filenames;
* package-provided compiler extensions.

Aunque provengan del código de la aplicación, deberán validarse.

---

## 12. Core compiler trust

Los compiladores core se considerarán confiables, pero estarán sujetos a:

* contracts estrictos;
* deterministic output;
* static analysis;
* security tests;
* versioning;
* fingerprints;
* registry freeze.

---

## 13. Package compiler trust

Un package compiler será semi-confiable.

No deberá poder:

* escribir fuera del build directory;
* activar builds;
* modificar manifests globales directamente;
* acceder a signing keys;
* sobrescribir artifacts core;
* emitir paths arbitrarios;
* inyectar código PHP libre sin validación.

---

## 14. Privileged build components

Serán privilegiados:

* BuildCoordinator;
* ArtifactSigner;
* BuildActivator;
* PreloadGenerator;
* ManifestPublisher;
* RevocationManager.

---

## 15. Separación build-runtime

El runtime de producción no deberá compilar controladores salvo modo explícito de desarrollo.

```text
Build environment
    generates
Immutable artifact set

Runtime environment
    only validates and loads
```

---

## 16. No runtime generation

En producción:

```php
'compilation' => [
    'runtime_generation' => false,
]
```

Si un artefacto falta, el runtime deberá fallar cerrado o usar un fallback dinámico explícitamente autorizado para entornos no productivos.

---

## 17. Secure Compiler Pipeline

Pipeline recomendado:

```text
Discover Sources
    ↓
Normalize Definitions
    ↓
Validate Input
    ↓
Resolve Dependencies
    ↓
Compile Security Plans
    ↓
Generate Intermediate Representation
    ↓
Validate IR
    ↓
Generate Artifact
    ↓
Static Artifact Validation
    ↓
Fingerprint
    ↓
Sign
    ↓
Store
    ↓
Generate Manifest
    ↓
Validate Build
    ↓
Activate Atomically
```

---

## 18. Compiler input boundary

Cada compilador deberá declarar qué entradas acepta.

```php
interface SecureControllerCompilerInterface
{
    public function supports(
        ControllerCompilationUnit $unit
    ): bool;

    public function compile(
        ControllerCompilationUnit $unit,
        SecureCompilationContext $context
    ): CompiledArtifactInterface;
}
```

---

## 19. SecureCompilationContext

```php
final readonly class SecureCompilationContext
{
    public function __construct(
        public string $buildId,
        public string $frameworkVersion,
        public string $applicationFingerprint,
        public CompilerCapabilitySet $capabilities,
        public ArtifactPathPolicy $pathPolicy,
        public CompilationSecurityPolicy $securityPolicy,
    ) {
    }
}
```

---

## 20. Compiler capabilities

```php
enum CompilerCapability: string
{
    case ReadControllerMetadata = 'read_controller_metadata';
    case ReadRouteDefinitions = 'read_route_definitions';
    case ReadParameterPlans = 'read_parameter_plans';
    case EmitPHPArtifact = 'emit_php_artifact';
    case EmitDataArtifact = 'emit_data_artifact';
    case RegisterManifestEntry = 'register_manifest_entry';
    case GeneratePreloadReference = 'generate_preload_reference';
}
```

---

## 21. Forbidden compiler capabilities

Package compilers no deberán recibir:

* activate build;
* access signer private key;
* delete build;
* write arbitrary filesystem;
* invalidate OPcache;
* modify active pointer.

---

## 22. Compiler registry

```php
interface ControllerCompilerRegistryInterface
{
    public function register(
        SecureControllerCompilerInterface $compiler,
        CompilerDescriptor $descriptor
    ): void;

    public function freeze(): void;

    public function all(): iterable;
}
```

---

## 23. Registry freeze

El registry deberá congelarse antes de compilar.

No podrán añadirse compiladores durante la generación de un build.

---

## 24. CompilerDescriptor

```php
final readonly class CompilerDescriptor
{
    public function __construct(
        public string $id,
        public string $version,
        public string $provider,
        public CompilerTrustLevel $trustLevel,
        public CompilerCapabilitySet $capabilities,
        public string $fingerprint,
    ) {
    }
}
```

---

## 25. CompilerTrustLevel

```php
enum CompilerTrustLevel: string
{
    case Core = 'core';
    case OfficialPackage = 'official_package';
    case Application = 'application';
    case ThirdParty = 'third_party';
    case Untrusted = 'untrusted';
}
```

---

## 26. Untrusted compilers

No deberán ejecutarse dentro del pipeline productivo.

Podrán requerir:

* proceso aislado;
* revisión manual;
* explicit allowlist;
* disabled-by-default policy.

---

## 27. Compiler identity

Cada artifact deberá registrar:

* compiler ID;
* compiler version;
* compiler fingerprint;
* trust level;
* compilation timestamp;
* build ID.

---

## 28. Determinismo

Siempre que sea posible, el mismo input deberá producir el mismo artifact.

Esto facilita:

* reproducibility;
* diffing;
* cache reuse;
* tamper detection;
* incident investigation.

---

## 29. Non-deterministic data

No deberá incluirse sin necesidad:

* timestamps variables dentro del contenido ejecutable;
* random IDs;
* paths absolutos;
* hostname del build agent;
* temporary directories.

Estos valores podrán residir en metadata separada.

---

## 30. Source normalization

Antes de compilar deberán normalizarse:

* class names;
* namespaces;
* paths;
* route names;
* metadata keys;
* parameter definitions;
* interceptor order;
* policy references.

---

## 31. Path canonicalization

Todo path de source deberá convertirse a una representación canónica antes de:

* hashing;
* manifest registration;
* comparison;
* cache lookup.

---

## 32. Source root policy

El compiler solo podrá leer fuentes dentro de roots permitidos.

```php
final readonly class CompilationSourcePolicy
{
    public function __construct(
        public array $allowedRoots,
        public bool $allowSymlinks,
        public array $deniedPatterns,
    ) {
    }
}
```

---

## 33. Symlink policy

Los symlinks deberán:

* estar deshabilitados por defecto en builds estrictos; o
* resolverse completamente;
* permanecer dentro de allowed roots;
* no cambiar entre discovery y read.

---

## 34. TOCTOU en sources

Entre validación y lectura, un source podría cambiar.

Mitigaciones:

* abrir descriptor de archivo;
* hash después de leer;
* snapshot del workspace;
* build container inmutable;
* comparar metadata antes y después.

---

## 35. Source fingerprint

Cada unidad deberá registrar:

```text
Canonical path
Content hash
Source type
Package identity
Dependency fingerprints
```

---

## 36. ControllerCompilationUnit

```php
final readonly class ControllerCompilationUnit
{
    public function __construct(
        public string $controllerClass,
        public string $sourcePath,
        public string $sourceFingerprint,
        public ControllerMetadata $metadata,
        public array $dependencies,
    ) {
    }
}
```

---

## 37. Input validation

El compiler deberá rechazar:

* clases inexistentes;
* metadata inválida;
* methods no expuestos;
* policies desconocidas;
* resolvers desconocidos;
* rutas inconsistentes;
* tipos no soportados;
* referencias circulares;
* service IDs no registrados.

---

## 38. Intermediate Representation

VoltStack deberá utilizar una representación intermedia segura.

```text
Source definitions
    ↓
Controller Compilation IR
    ↓
Artifact generator
```

---

## 39. ControllerCompilationIR

```php
final readonly class ControllerCompilationIR
{
    public function __construct(
        public string $controllerClass,
        public array $actions,
        public array $parameterPlans,
        public array $securityPlans,
        public array $interceptorPlans,
        public array $resultPlans,
        public string $sourceFingerprint,
    ) {
    }
}
```

---

## 40. IR security benefits

La IR permite:

* separar análisis y generación;
* validar referencias;
* prohibir código libre;
* aplicar schemas;
* comparar resultados;
* soportar múltiples formatos de artifact.

---

## 41. IR validation

Antes de generar código deberá validarse:

* schema;
* allowed node types;
* reference integrity;
* graph acyclicity;
* capability requirements;
* security invariants.

---

## 42. No raw PHP injection

Ningún valor derivado de metadata deberá insertarse directamente como PHP sin escaping y validación.

Ejemplo inseguro:

```php
$code = "<?php return '{$metadataValue}';";
```

---

## 43. Safe code generation

El generador deberá usar:

* AST;
* templates controladas;
* `var_export()` seguro para datos;
* literal encoders;
* identifier validators;
* generated symbol registry.

---

## 44. Generated identifiers

Nombres de:

* classes;
* methods;
* constants;
* namespaces;

deberán generarse desde identificadores normalizados, no desde strings arbitrarios.

---

## 45. PHP AST generation

La opción recomendada para artifacts complejos será generar un AST propio o usar un builder tipado.

```text
IR
    ↓
PHP AST
    ↓
Printer
    ↓
Syntax validation
```

---

## 46. Artifact syntax validation

Todo PHP generado deberá validarse con:

* parser;
* lint;
* optional static analysis;
* restricted token inspection.

---

## 47. Restricted PHP constructs

Los artifacts generados no deberán contener salvo necesidad explícita:

* `eval`;
* `include` dinámico;
* `require` dinámico;
* shell execution;
* variable variables;
* dynamic class construction;
* deserialization;
* filesystem writes;
* network calls.

---

## 48. Artifact token validator

```php
interface PHPArtifactSecurityValidatorInterface
{
    public function validate(
        string $artifactPath
    ): ArtifactSecurityValidationResult;
}
```

---

## 49. Static artifact validation

Podrá comprobar:

* syntax;
* prohibited tokens;
* expected namespace;
* expected class;
* expected interfaces;
* no top-level side effects;
* no unexpected dependencies;
* no dynamic includes.

---

## 50. No top-level side effects

Un artifact deberá limitarse a:

* declarations;
* immutable data returns;
* class definitions;
* generated factory definitions.

No deberá ejecutar queries o modificar estado al cargarse.

---

## 51. Artifact types

```php
enum ControllerArtifactType: string
{
    case MetadataPlan = 'metadata_plan';
    case ParameterPlan = 'parameter_plan';
    case ExposurePlan = 'exposure_plan';
    case SecurityPlan = 'security_plan';
    case InterceptorPlan = 'interceptor_plan';
    case InvocationPlan = 'invocation_plan';
    case ResultPlan = 'result_plan';
    case ExceptionPlan = 'exception_plan';
    case ExecutionBundle = 'execution_bundle';
    case PreloadFile = 'preload_file';
}
```

---

## 52. CompiledArtifactInterface

```php
interface CompiledArtifactInterface
{
    public function artifactId(): string;

    public function artifactType(): ControllerArtifactType;

    public function buildId(): string;

    public function fingerprint(): ArtifactFingerprint;

    public function schemaVersion(): string;

    public function dependencies(): array;
}
```

---

## 53. Artifact ID

El ID deberá ser:

* estable;
* normalizado;
* libre de path traversal;
* único dentro del build;
* derivable de identidad lógica o contenido.

---

## 54. Artifact naming

Ejemplo:

```text
controllers/security-plan/4f/4f8a...php
```

Se favorecerá naming content-addressed.

---

## 55. Content-addressed artifacts

El path podrá derivarse del hash del contenido.

Ventajas:

* evita sustitución silenciosa;
* facilita deduplicación;
* soporta caches remotos;
* simplifica validación;
* detecta corrupción.

---

## 56. ArtifactFingerprint

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

## 57. Hash algorithm

Se utilizarán algoritmos criptográficamente seguros.

Ejemplos:

* SHA-256;
* SHA-384;
* SHA-512;
* BLAKE2 cuando esté soportado y estandarizado por la implementación.

No se usarán:

* MD5;
* SHA-1;
* CRC como control de seguridad.

---

## 58. Fingerprint scope

El hash deberá cubrir:

* artifact content;
* artifact type;
* schema version;
* build ID;
* dependency fingerprints;
* compiler identity cuando corresponda.

---

## 59. Canonical fingerprint payload

```text
artifact_type
schema_version
build_id
compiler_id
compiler_version
dependency_hashes
content_hash
```

con serialización canónica.

---

## 60. Canonical serialization

La representación deberá ser:

* deterministic;
* sorted;
* encoding-defined;
* no dependiente de locale;
* no dependiente de PHP object serialization.

---

## 61. Artifact signatures

Las firmas serán opcionales en perfil estándar y obligatorias en perfiles estrictos o distribuidos.

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

## 62. Signature coverage

La firma deberá cubrir el payload canónico del artifact y su identidad.

No bastará firmar solo el contenido del archivo.

---

## 63. Asymmetric signatures

Para entornos distribuidos se preferirán firmas asimétricas:

* private key solo en build system;
* public key en runtime;
* separación clara de privilegios.

---

## 64. Signing key isolation

La clave privada:

* no deberá estar en runtime;
* no deberá almacenarse en el repositorio;
* deberá residir en secret manager o HSM cuando aplique;
* deberá tener rotación;
* deberá tener access audit.

---

## 65. Key IDs

Cada firma deberá indicar `keyId`.

El runtime deberá resolverlo en un trust store.

---

## 66. ArtifactTrustStore

```php
interface ArtifactTrustStoreInterface
{
    public function publicKey(string $keyId): TrustedPublicKey;

    public function isRevoked(string $keyId): bool;

    public function minimumAcceptedKeyVersion(): int;
}
```

---

## 67. Key rotation

El sistema deberá soportar:

* multiple active verification keys;
* one current signing key;
* graceful rotation;
* revoked key list;
* expiration;
* build re-signing policy.

---

## 68. Revoked signing key

Un artifact firmado por una clave revocada deberá rechazarse aunque su firma sea matemáticamente válida.

---

## 69. Unsigned artifacts

La política deberá declarar:

```php
enum UnsignedArtifactPolicy: string
{
    case Allow = 'allow';
    case AllowLocalOnly = 'allow_local_only';
    case Warn = 'warn';
    case Reject = 'reject';
}
```

---

## 70. Production strict mode

En modo estricto:

```text
Unsigned artifact → reject
Unknown key → reject
Revoked key → reject
Invalid signature → terminate or fail startup
```

---

## 71. Manifest Security

El manifest representa la lista autorizada de artifacts del build.

---

## 72. ArtifactManifest

```php
final readonly class ArtifactManifest
{
    public function __construct(
        public string $buildId,
        public string $frameworkVersion,
        public string $applicationFingerprint,
        public string $schemaVersion,
        public array $artifacts,
        public array $dependencies,
        public ?ArtifactSignature $signature,
    ) {
    }
}
```

---

## 73. Manifest authority

El runtime no deberá buscar libremente archivos en el directorio.

Solo podrá cargar artifacts presentes en el manifest activo.

---

## 74. Manifest membership

```text
Requested artifact ID
    ↓
Manifest lookup
    ↓
Expected path, type, fingerprint and signature
    ↓
Load and validate
```

---

## 75. Manifest entry

```php
final readonly class ArtifactManifestEntry
{
    public function __construct(
        public string $artifactId,
        public ControllerArtifactType $type,
        public string $relativePath,
        public ArtifactFingerprint $fingerprint,
        public ?ArtifactSignature $signature,
        public array $dependencies,
        public int $size,
    ) {
    }
}
```

---

## 76. Relative paths only

El manifest deberá almacenar paths relativos al build root.

No paths absolutos dependientes del host.

---

## 77. Manifest path validation

Todo path deberá:

* ser relativo;
* no contener `..`;
* no contener null bytes;
* usar separadores normalizados;
* permanecer dentro del build root;
* no apuntar a symlink inesperado.

---

## 78. Manifest signing

En perfiles estrictos, el manifest completo deberá estar firmado.

Esto protege:

* lista de artifacts;
* fingerprints;
* build identity;
* dependency graph;
* preload references.

---

## 79. Manifest before artifacts

El runtime deberá validar primero el manifest antes de confiar en sus entradas.

---

## 80. Manifest schema version

Los manifests deberán declarar schema version.

El runtime deberá rechazar versiones:

* desconocidas;
* incompatibles;
* deprecated por seguridad;
* menores que la versión mínima aceptada.

---

## 81. Manifest completeness

El build validator deberá asegurar que:

* todos los artifacts referenciados existan;
* no existan referencias rotas;
* las dependencias estén presentes;
* no haya IDs duplicados;
* no haya paths duplicados incompatibles;
* no haya artifacts huérfanos ejecutables cuando la política lo prohíba.

---

## 82. Orphan artifact policy

Artefactos no listados en el manifest deberán ignorarse y podrán causar rechazo del build en strict mode.

---

## 83. Build Identity

Cada compilación producirá una identidad de build.

```php
final readonly class ControllerBuildIdentity
{
    public function __construct(
        public string $buildId,
        public string $applicationFingerprint,
        public string $frameworkVersion,
        public string $compilerSetFingerprint,
        public string $createdAt,
    ) {
    }
}
```

---

## 84. Build ID

El `buildId` deberá ser:

* único;
* no controlado por request;
* estable para el conjunto de artifacts;
* trazable;
* seguro para uso en paths.

---

## 85. Build ID strategies

Opciones:

* UUID;
* content hash;
* timestamp + random suffix;
* deployment release ID.

Para builds reproducibles se favorecerá un hash del build descriptor.

---

## 86. Application fingerprint

Podrá incluir:

* composer lock hash;
* source tree hash;
* config compilation hash;
* framework version;
* package versions;
* compiler set fingerprint.

---

## 87. Compiler set fingerprint

Debe cambiar cuando:

* se agrega compiler;
* cambia versión;
* cambia configuración;
* cambia trust level;
* cambia output schema.

---

## 88. Build descriptor

```php
final readonly class BuildDescriptor
{
    public function __construct(
        public ControllerBuildIdentity $identity,
        public array $sourceFingerprints,
        public array $compilerDescriptors,
        public array $configurationFingerprints,
        public array $platformRequirements,
    ) {
    }
}
```

---

## 89. Build chain

Podrá mantenerse relación:

```text
Previous build
    ↓
Current build
    ↓
Next build
```

para auditoría y rollback.

---

## 90. Build provenance

El build deberá registrar:

* source revision;
* repository reference;
* CI job;
* builder identity;
* compiler versions;
* dependency lock hash;
* signing key ID.

---

## 91. Provenance trust

La provenance podrá firmarse separadamente y almacenarse fuera del runtime.

---

## 92. Build activation

Un build no deberá usarse hasta completar:

* artifact validation;
* manifest validation;
* compatibility validation;
* security validation;
* warmup;
* optional smoke tests.

---

## 93. Atomic activation

La activación deberá ser atómica.

```text
Build N active
    ↓
Prepare Build N+1
    ↓
Validate N+1
    ↓
Atomically switch active pointer
```

Nunca deberá existir un estado con manifest de un build y artifacts de otro.

---

## 94. Active build pointer

Podrá implementarse mediante:

* symlink atómico validado;
* pointer file atómico;
* release directory switch;
* environment-level release ID.

---

## 95. Active pointer security

El pointer deberá:

* ser writable solo por deployer;
* apuntar dentro de allowed build root;
* contener un build ID válido;
* no aceptar paths arbitrarios;
* verificarse al boot.

---

## 96. Symlink activation

Si se usa symlink:

* resolver target real;
* validar build root;
* evitar symlink chain;
* cambiar atómicamente;
* verificar propietario y permisos.

---

## 97. Pointer file activation

La escritura deberá usar:

```text
write temporary file
fsync
rename atomically
```

cuando la plataforma lo soporte.

---

## 98. Partial deployment

El sistema deberá impedir activación cuando faltan artifacts.

Un nodo no deberá cargar parcialmente un build.

---

## 99. Distributed activation

En despliegues multinodo:

```text
Upload build
    ↓
Validate on each node or trusted shared store
    ↓
Mark ready
    ↓
Activate version
    ↓
Workers pin execution to build
```

---

## 100. Execution build pinning

Cada request o execution deberá quedar asociado a un `buildId`.

Aunque se active un build nuevo durante la petición, esa ejecución continuará usando el build anterior.

---

## 101. BuildReference

```php
final readonly class BuildReference
{
    public function __construct(
        public string $buildId,
        public string $manifestFingerprint,
        public string $activatedAt,
    ) {
    }
}
```

---

## 102. Worker build pinning

Un Worker podrá:

* cargar un build al inicio;
* cambiar build entre requests;
* nunca cambiarlo a mitad de execution.

---

## 103. Mixed-build prevention

No deberá permitirse:

```text
Exposure plan from Build A
Security plan from Build B
Parameter plan from Build A
```

Todos los artifacts de un execution bundle deberán compartir `buildId`.

---

## 104. ExecutionBundle security

```php
final readonly class SecureExecutionBundle
{
    public function __construct(
        public string $buildId,
        public CompiledControllerExposurePlan $exposure,
        public CompiledParameterResolutionPlan $parameters,
        public CompiledControllerSecurityPlan $security,
        public CompiledInterceptorPlan $interceptors,
        public CompiledInvocationPlan $invocation,
        public string $fingerprint,
    ) {
    }
}
```

---

## 105. Bundle validation

El bundle deberá comprobar:

* same build ID;
* dependency fingerprints;
* schema compatibility;
* target identity;
* route signature;
* security plan presence;
* no revoked artifact.

---

## 106. Artifact Loader Security

El loader será el único componente autorizado para cargar artifacts.

---

## 107. SecureArtifactLoader

```php
interface SecureArtifactLoaderInterface
{
    public function load(
        string $artifactId,
        BuildReference $build
    ): CompiledArtifactInterface;
}
```

---

## 108. Loader responsibilities

* manifest lookup;
* path resolution;
* build membership;
* size validation;
* fingerprint validation;
* signature validation;
* schema validation;
* revocation validation;
* safe include;
* type validation;
* cache registration.

---

## 109. No arbitrary paths

La API pública del loader aceptará `artifactId`, no filesystem path.

---

## 110. ArtifactPathPolicy

```php
final readonly class ArtifactPathPolicy
{
    public function __construct(
        public string $buildRoot,
        public bool $allowSymlinks,
        public int $maxPathLength,
        public array $allowedExtensions,
    ) {
    }
}
```

---

## 111. Canonical path check

```text
manifest relative path
    ↓
join build root
    ↓
canonicalize
    ↓
assert inside build root
```

---

## 112. File type check

Los artifacts PHP deberán:

* usar extensión permitida;
* ser archivos regulares;
* no devices;
* no sockets;
* no directories;
* no writable by untrusted users.

---

## 113. Permission validation

En strict mode se verificará:

* owner;
* group;
* mode;
* no world-writable;
* build directory read-only para runtime;
* manifest read-only.

---

## 114. Size validation

El tamaño real deberá coincidir con el manifest o estar dentro de política antes de leer.

Esto ayuda a detectar:

* truncation;
* replacement;
* oversized malicious file.

---

## 115. Hash before include

El artifact deberá validarse antes de incluirse.

```text
read
hash
verify signature
validate schema/code
include
```

---

## 116. Race between hash and include

Para evitar sustitución entre validación e include:

* usar build directory inmutable;
* verificar inode/metadata;
* abrir descriptor estable;
* cargar desde contenido validado cuando sea viable;
* no permitir escrituras concurrentes.

---

## 117. Include policy

El include deberá ser:

* desde path canónico;
* una única vez cuando corresponda;
* sin valores derivados de request;
* bajo error handling seguro.

---

## 118. Artifact return validation

Si un artifact devuelve datos:

```php
$artifact = require $path;
```

el loader deberá validar su tipo.

---

## 119. Generated class validation

Si define una clase, deberá comprobar:

* expected class exists;
* implements expected interface;
* expected artifact ID;
* expected build ID;
* no unexpected class collision.

---

## 120. Class collision

Los nombres generados deberán incluir build-aware or content-aware namespaces cuando sea necesario.

---

## 121. Artifact cache

El loader podrá cachear artifacts validados en memoria del Worker.

La key deberá incluir:

* build ID;
* artifact ID;
* fingerprint.

---

## 122. Cache trust

Solo se cacheará después de validación completa.

---

## 123. Negative cache

Podrá cachearse una falla por ejecución o corto periodo, pero no deberá impedir recuperación tras activación de un build correcto.

---

## 124. Artifact Store Security

El Artifact Store administra persistencia de builds.

---

## 125. ArtifactStoreInterface

```php
interface ArtifactStoreInterface
{
    public function write(
        string $buildId,
        CompiledArtifactPayload $artifact
    ): ArtifactStoreWriteResult;

    public function read(
        string $buildId,
        string $relativePath
    ): ArtifactStoreReadResult;

    public function sealBuild(string $buildId): void;
}
```

---

## 126. Build staging

Los artifacts deberán escribirse primero en:

```text
staging/build-id
```

y luego sellarse.

---

## 127. Sealed build

Un build sellado no podrá modificarse.

Cualquier cambio deberá crear un build nuevo.

---

## 128. Write-once semantics

El Artifact Store deberá evitar sobrescribir un artifact existente con fingerprint diferente.

---

## 129. Staging cleanup

Los builds incompletos deberán limpiarse sin afectar builds activos.

---

## 130. Build root separation

```text
artifacts/
    staging/
    builds/
    active/
    revoked/
```

---

## 131. Filesystem permissions

Ejemplo conceptual:

```text
builder: write staging
deployer: promote/activate
runtime: read builds and active pointer
```

---

## 132. Privilege separation

Idealmente:

* build user;
* deploy user;
* runtime user;

serán distintos.

---

## 133. Artifact store traversal

Toda operación deberá resolver mediante build ID y relative path validados.

---

## 134. Build ID validation

Se aplicará regex estricta o value object.

No aceptar:

* slashes;
* backslashes;
* dots ambiguos;
* null bytes;
* control characters.

---

## 135. Remote Artifact Stores

Podrán usarse stores remotos:

* object storage;
* shared filesystem;
* artifact registry;
* internal CDN.

---

## 136. Remote content trust

TLS no sustituye firma e integridad.

Todo artifact remoto deberá verificarse igual que uno local.

---

## 137. Remote cache threat

Un cache remoto comprometido podría devolver:

* artifact antiguo;
* artifact de otro build;
* contenido modificado;
* manifest falso;
* respuesta truncada.

---

## 138. Remote cache validation

Siempre verificar:

* build ID;
* artifact ID;
* expected hash;
* signature;
* size;
* schema.

---

## 139. Cache key design

```text
framework-version/
application-fingerprint/
build-id/
artifact-type/
artifact-fingerprint
```

---

## 140. Cache poisoning prevention

No utilizar keys basadas en input del cliente.

No confiar en metadata del cache sin validación local.

---

## 141. Remote cache write permissions

Solo CI o build system autorizado deberá publicar.

Runtime será read-only.

---

## 142. Cache provenance

Podrá registrarse:

* uploader identity;
* upload timestamp;
* source build;
* signing key;
* checksum.

---

## 143. Cache eviction

La ausencia por eviction no deberá activar generación dinámica en producción.

---

## 144. Rollback Security

Rollback es necesario, pero puede reintroducir vulnerabilidades.

---

## 145. Safe rollback

Un build será elegible para rollback si:

* no está revocado;
* cumple minimum security version;
* sus firmas siguen válidas;
* es compatible con runtime;
* sus migrations/state son compatibles;
* está aprobado por policy.

---

## 146. BuildRevocationRegistry

```php
interface BuildRevocationRegistryInterface
{
    public function isRevoked(string $buildId): bool;

    public function reason(string $buildId): ?BuildRevocationReason;
}
```

---

## 147. Revocation reasons

```php
enum BuildRevocationReason: string
{
    case Vulnerability = 'vulnerability';
    case CompromisedKey = 'compromised_key';
    case CorruptedArtifacts = 'corrupted_artifacts';
    case InvalidCompiler = 'invalid_compiler';
    case OperationalIncident = 'operational_incident';
}
```

---

## 148. Revoked build behavior

Un build revocado:

* no podrá activarse;
* no podrá usarse para nuevas requests;
* podrá provocar reciclaje de Workers;
* deberá generar alerta;
* deberá conservarse para forensics según política.

---

## 149. Downgrade protection

El runtime podrá mantener:

```text
minimum_allowed_build_security_version
```

---

## 150. Build security version

```php
final readonly class BuildSecurityVersion
{
    public function __construct(
        public int $epoch,
        public int $revision,
    ) {
    }
}
```

---

## 151. Security epoch

Un cambio incompatible de seguridad podrá incrementar `epoch`.

Builds de epochs anteriores serán rechazados.

---

## 152. Rollback authorization

La activación de rollback deberá requerir:

* operator authorization;
* reason;
* audit;
* build eligibility validation;
* optional multi-party approval.

---

## 153. Automatic rollback

Podrá permitirse ante fallo operacional, pero nunca hacia un build revocado o bajo la versión mínima.

---

## 154. Replay attacks

Un attacker podría reintroducir un manifest válido pero antiguo.

Mitigaciones:

* active security epoch;
* revocation;
* minimum build version;
* deployment sequence;
* signed activation record.

---

## 155. Activation record

```php
final readonly class BuildActivationRecord
{
    public function __construct(
        public string $buildId,
        public string $previousBuildId,
        public string $activatedBy,
        public string $reason,
        public string $sequence,
        public string $timestamp,
        public ?ArtifactSignature $signature,
    ) {
    }
}
```

---

## 156. Monotonic activation sequence

Cuando sea viable, cada activación tendrá un sequence creciente.

Esto dificulta replay.

---

## 157. OPcache Security

OPcache mejora rendimiento pero puede mantener código obsoleto.

---

## 158. OPcache threats

* artifact replaced while cached;
* stale code after activation;
* mixed builds;
* invalidation failure;
* shared cache across releases;
* path reuse;
* timestamp validation disabled.

---

## 159. Immutable path strategy

La mejor defensa será usar paths únicos por build.

```text
/builds/{buildId}/...
```

Así, OPcache distingue archivos por path.

---

## 160. No in-place artifact replacement

Nunca sobrescribir artifacts de un build activo.

---

## 161. OPcache validation strategy

En producción con immutable releases podrá usarse configuración agresiva, siempre que:

* cada build tenga path nuevo;
* la activación sea atómica;
* Workers se reciclen o cambien de build de forma segura.

---

## 162. OPcache invalidation

Solo deberá usarse como medida complementaria.

No depender exclusivamente de invalidar paths reutilizados.

---

## 163. OPcache preload

El preload puede cargar artifacts antes de requests.

Su seguridad es crítica.

---

## 164. Preload generation

El preload file deberá generarse desde el manifest validado.

Nunca mediante scanning libre de directorios.

---

## 165. Preload allowlist

Solo incluir:

* classes inmutables;
* registries congelados;
* compiled plans seguros;
* framework internals;
* artifacts explícitamente preloadable.

---

## 166. Non-preloadable artifacts

No deberán preloaded:

* request-scoped objects;
* principal contexts;
* tenant contexts;
* mutable execution state;
* user-specific decisions;
* active streams.

---

## 167. Preload manifest

```php
final readonly class PreloadManifest
{
    public function __construct(
        public string $buildId,
        public array $artifactIds,
        public ArtifactFingerprint $fingerprint,
        public ?ArtifactSignature $signature,
    ) {
    }
}
```

---

## 168. Preload validation

Antes de iniciar el proceso:

* manifest valid;
* artifacts valid;
* same build;
* no revoked items;
* expected runtime version;
* expected PHP version.

---

## 169. Preload build pinning

Un proceso con preload de Build A no deberá mezclar artifacts ejecutables de Build B sin reinicio cuando las clases colisionen.

---

## 170. Worker restart on preload change

Un cambio de preload generalmente requerirá reiniciar Workers o proceso maestro.

---

## 171. Preloaded class collision

Los nombres de clases generadas deberán evitar colisiones entre releases.

---

## 172. Preload side effects

El preload file no deberá:

* abrir conexiones;
* resolver request context;
* cargar secrets innecesarios;
* iniciar transacciones;
* registrar estado mutable de usuario.

---

## 173. OPcache poisoning

Un atacante con escritura sobre artifact path podría afectar procesos.

La defensa principal será:

* runtime read-only;
* immutable build;
* ownership;
* unique paths;
* integrity validation before activation.

---

## 174. Deployment Security

El deploy deberá tratar los artifacts como release units.

---

## 175. Secure deployment pipeline

```text
Build
    ↓
Validate
    ↓
Sign
    ↓
Publish
    ↓
Download
    ↓
Verify
    ↓
Stage
    ↓
Warmup
    ↓
Smoke Test
    ↓
Activate
    ↓
Recycle Workers
    ↓
Monitor
```

---

## 176. Deployment artifact package

Podrá contener:

* manifest;
* artifacts;
* preload manifest;
* provenance;
* activation metadata;
* checksums.

---

## 177. Package-level signature

Además de signatures individuales, podrá firmarse el paquete completo.

---

## 178. Transport security

La transferencia deberá usar canales autenticados y cifrados, pero la integridad se verificará independientemente.

---

## 179. Extraction security

Si se distribuyen archives:

* reject absolute paths;
* reject `..`;
* reject symlinks según policy;
* limit size;
* limit file count;
* validate destination;
* extract to staging.

---

## 180. Archive bomb protection

Aplicar límites de:

* compressed size;
* expanded size;
* ratio;
* depth;
* count.

---

## 181. Deployment permissions

El deployer deberá tener capacidad limitada a:

* stage;
* validate;
* activate;
* rollback.

No deberá modificar source code o signing keys salvo arquitectura específica.

---

## 182. Runtime permissions

El usuario de runtime deberá ser read-only sobre builds.

---

## 183. Secret separation

Las keys de signing no deberán estar disponibles en hosts runtime.

---

## 184. Build host trust

El build agent deberá:

* usar imagen controlada;
* dependencias locked;
* workspace limpio;
* network policy;
* secret access mínimo;
* ephemeral environment cuando sea posible.

---

## 185. Reproducible builds

Objetivo deseable:

```text
Same source + same config + same compiler set
    =
Same artifact fingerprints
```

---

## 186. Reproducibility verification

Podrá compilarse en dos agentes y comparar hashes para builds críticos.

---

## 187. Supply Chain Security

La cadena incluye:

* Composer packages;
* compiler packages;
* build images;
* CI actions;
* PHP extensions;
* code generators;
* artifact stores.

---

## 188. Dependency locking

La compilación deberá basarse en lockfiles.

---

## 189. Composer integrity

Se deberán validar:

* package versions;
* dist checksums;
* repository trust;
* plugin allowlist;
* scripts;
* abandoned or vulnerable packages.

---

## 190. Composer plugins

Los plugins ejecutan código durante install.

Deberán estar explícitamente permitidos.

---

## 191. Composer scripts

Scripts de packages deberán revisarse y limitarse en entornos sensibles.

---

## 192. Compiler package allowlist

Los paquetes que registran compilers deberán estar allowlisted.

---

## 193. Package provenance

Idealmente registrar:

* package name;
* version;
* source reference;
* distribution hash;
* signer cuando exista.

---

## 194. Dependency vulnerability policy

Un build podrá bloquearse si contiene dependencias con vulnerabilidades que superen el umbral configurado.

---

## 195. Security advisory snapshot

La decisión deberá basarse en un snapshot auditable del momento de build.

---

## 196. Build image pinning

Las imágenes de CI deberán fijarse por digest, no solo por tag mutable.

---

## 197. CI workflow trust

Cambios en workflows deberán requerir revisión.

---

## 198. Pull request security

Código de PR no confiable no deberá tener acceso a signing keys o secrets productivos.

---

## 199. Secretless validation builds

Los builds de validación podrán compilar y verificar sin firmar.

La firma final ocurrirá en pipeline privilegiado.

---

## 200. Two-stage build

```text
Unprivileged compilation
    ↓
Artifact validation
    ↓
Privileged signing
```

---

## 201. Signing service

La firma podrá delegarse a un servicio con API limitada.

Entrada:

* manifest fingerprint;
* artifact fingerprints;
* build identity.

Salida:

* signature.

---

## 202. Signing policy

El signer deberá verificar que el build cumple políticas antes de firmar.

---

## 203. Compromised compiler

Si un compiler se compromete:

* revocar compiler fingerprint;
* revocar builds afectados;
* bloquear package version;
* rotar signing key si fue expuesta;
* rebuild;
* recycle Workers.

---

## 204. Compiler revocation registry

```php
interface CompilerRevocationRegistryInterface
{
    public function isRevoked(
        string $compilerId,
        string $compilerFingerprint
    ): bool;
}
```

---

## 205. Artifact Revocation

La revocación podrá operar en varios niveles:

```text
Artifact
Build
Compiler
Package
Signing key
Schema version
```

---

## 206. ArtifactRevocationRegistry

```php
interface ArtifactRevocationRegistryInterface
{
    public function isRevoked(
        string $artifactFingerprint
    ): bool;
}
```

---

## 207. Runtime revocation refresh

El runtime podrá refrescar revocation data:

* al boot;
* entre requests;
* por intervalos;
* tras incidentes.

No deberá depender de acceso remoto para cada artifact load.

---

## 208. Revocation cache

Deberá:

* estar firmada o venir de fuente confiable;
* tener expiración;
* fallar según policy;
* soportar last-known-good.

---

## 209. Offline revocation policy

En entornos sin conectividad:

* usar snapshot firmado;
* declarar freshness máxima;
* fallar cerrado si expira en high-security mode.

---

## 210. Artifact quarantine

Artifacts sospechosos deberán moverse fuera del path cargable o marcarse revocados.

---

## 211. Forensic preservation

Antes de eliminar un artifact comprometido podrá conservarse:

* hash;
* copia aislada;
* manifest;
* build provenance;
* logs de activación.

---

## 212. Compatibility Security

No toda incompatibilidad es solo técnica.

Un artifact antiguo puede carecer de controles nuevos.

---

## 213. Runtime compatibility matrix

Se deberá validar:

* framework version;
* artifact schema;
* PHP version;
* compiler API version;
* security epoch;
* package ABI cuando aplique.

---

## 214. Minimum security schema

El runtime podrá rechazar schemas técnicamente parseables pero inseguros.

---

## 215. Schema migration

No se deberán migrar artifacts ejecutables arbitrariamente en runtime productivo.

Se deberán recompilar desde source.

---

## 216. Legacy artifact policy

```php
enum LegacyArtifactPolicy: string
{
    case Reject = 'reject';
    case WarnDevelopmentOnly = 'warn_development_only';
    case AllowSignedMigration = 'allow_signed_migration';
}
```

---

## 217. Build validation

Antes de activación deberá ejecutarse `ControllerBuildSecurityValidator`.

---

## 218. ControllerBuildSecurityValidator

```php
interface ControllerBuildSecurityValidatorInterface
{
    public function validate(
        ArtifactManifest $manifest,
        BuildValidationContext $context
    ): BuildSecurityValidationReport;
}
```

---

## 219. Validation phases

```text
Identity
Manifest signature
Schema
Artifact existence
Artifact fingerprints
Artifact signatures
Dependency graph
Compiler trust
Revocations
Compatibility
Security invariants
Preload
Permissions
```

---

## 220. BuildSecurityValidationReport

```php
final readonly class BuildSecurityValidationReport
{
    public function __construct(
        public bool $valid,
        public array $errors,
        public array $warnings,
        public SecurityRiskLevel $risk,
        public string $reportFingerprint,
    ) {
    }
}
```

---

## 221. Warning policy

Warnings no deberán permitir activación si corresponden a controles obligatorios.

---

## 222. Build approval

En entornos regulados podrá requerirse aprobación del validation report.

---

## 223. Warmup Security

Warmup deberá cargar y validar artifacts sin ejecutar lógica de negocio.

---

## 224. Warmup responsibilities

* load manifests;
* validate signatures;
* build indexes;
* populate immutable caches;
* validate class loading;
* validate bundles;
* optional OPcache compile.

---

## 225. Warmup isolation

Deberá ejecutarse con:

* sin request user;
* sin tenant;
* sin production secrets innecesarios;
* no side effects;
* filesystem limitado.

---

## 226. Warmup failure

Un build con warmup fallido no deberá activarse.

---

## 227. Smoke testing

Podrá validar rutas sintéticas usando principals y tenants de prueba aislados.

---

## 228. No production data

Los smoke tests pre-activation no deberán usar datos productivos salvo entorno controlado y políticas estrictas.

---

## 229. Distributed Deployment Security

Los nodos deberán converger en un build consistente.

---

## 230. Node readiness

Cada nodo deberá publicar:

* build downloaded;
* manifest valid;
* artifacts valid;
* warmup complete;
* preload compatible;
* ready.

---

## 231. Quorum activation

En sistemas críticos podrá requerirse quorum de nodos listos antes de activar.

---

## 232. Node drift

Se deberá detectar cuando nodos ejecutan builds diferentes fuera de una ventana de rollout permitida.

---

## 233. Build ID observability

Logs y traces deberán incluir `buildId` seguro.

---

## 234. Rolling deployment

Durante rollout pueden coexistir builds, pero cada request permanecerá pinned a uno.

---

## 235. Shared state compatibility

La activación deberá considerar:

* database schema;
* queue payloads;
* session formats;
* cache formats;
* SPA protocol versions.

---

## 236. Artifact Store consistency

En object storage se deberá considerar consistencia de lectura y publicación atómica mediante manifests/versioning.

---

## 237. Manifest-last publication

Estrategia recomendada:

```text
Upload artifacts
    ↓
Verify uploads
    ↓
Upload signed manifest last
```

El manifest actúa como señal de completitud.

---

## 238. Immutable object keys

No sobrescribir objetos existentes.

Publicar nuevos keys por build.

---

## 239. Remote build activation

El pointer activo podrá ser un objeto versionado y firmado.

---

## 240. Split-brain activation

Se deberá detectar cuando distintos coordinadores activan builds diferentes.

Mitigaciones:

* leader election;
* compare-and-swap;
* monotonic sequence;
* deployment lock.

---

## 241. Build lock

El lock deberá tener:

* owner;
* expiration;
* fencing token;
* audit.

---

## 242. Fencing token

Evita que un deployer antiguo continúe activando después de perder el lock.

---

## 243. Security Events

Eventos principales:

```text
controllers.compilation.security.build.started
controllers.compilation.security.source.rejected
controllers.compilation.security.compiler.rejected
controllers.compilation.security.artifact.generated
controllers.compilation.security.artifact.validation_failed
controllers.compilation.security.artifact.signed
controllers.compilation.security.manifest.signed
controllers.compilation.security.build.validated
controllers.compilation.security.build.rejected
controllers.compilation.security.build.activated
controllers.compilation.security.build.rollback
controllers.compilation.security.build.revoked
controllers.compilation.security.key.revoked
controllers.compilation.security.loader.rejected
controllers.compilation.security.opcache.mismatch
```

---

## 244. Metrics

```text
voltstack.controllers.compilation.security.build_failures
voltstack.controllers.compilation.security.artifact_validation_failures
voltstack.controllers.compilation.security.signature_failures
voltstack.controllers.compilation.security.manifest_failures
voltstack.controllers.compilation.security.revoked_artifact_attempts
voltstack.controllers.compilation.security.rollback_attempts
voltstack.controllers.compilation.security.downgrade_rejections
voltstack.controllers.compilation.security.loader_duration
voltstack.controllers.compilation.security.build_validation_duration
```

---

## 245. Cardinality

Labels permitidos:

* artifact type;
* compiler ID controlado;
* failure category;
* build security epoch;
* signature algorithm;
* environment.

No usar como labels:

* full artifact path;
* arbitrary source path;
* raw exception;
* complete fingerprint cuando genere cardinalidad excesiva.

---

## 246. Audit Records

Se auditarán:

* signing;
* activation;
* rollback;
* revocation;
* key rotation;
* compiler registration;
* remote publication;
* security policy override.

---

## 247. BuildSecurityAuditRecord

```php
final readonly class BuildSecurityAuditRecord
{
    public function __construct(
        public string $event,
        public string $buildId,
        public string $actor,
        public string $decision,
        public string $reason,
        public string $timestamp,
        public array $safeMetadata,
    ) {
    }
}
```

---

## 248. Incident Handling

Incidentes críticos:

* invalid signature;
* manifest substitution;
* unknown active build;
* artifact outside build root;
* compiler revocation match;
* OPcache serving revoked code;
* mixed-build execution.

---

## 249. Invalid signature response

En runtime productivo:

* reject artifact;
* reject build;
* terminate affected Worker;
* alert;
* mark node unhealthy;
* preserve evidence.

---

## 250. Unknown active build

Si el active pointer apunta a build no registrado:

* startup failure;
* no dynamic fallback;
* audit;
* health check failure.

---

## 251. Mixed-build incident

Deberá cancelar execution antes de controller invocation.

El Worker podrá terminarse.

---

## 252. Security configuration

```php
// config/controller_compilation_security.php

return [
    'enabled' => true,

    'environment' => [
        'runtime_generation' => false,
        'require_prebuilt_artifacts' => true,
        'fail_closed' => true,
    ],

    'sources' => [
        'allowed_roots' => [
            base_path('app'),
            base_path('src'),
        ],
        'allow_symlinks' => false,
        'snapshot_before_compile' => true,
    ],

    'compilers' => [
        'freeze_registry' => true,
        'allow_third_party' => false,
        'require_allowlist' => true,
        'reject_revoked' => true,
    ],

    'artifacts' => [
        'content_addressed' => true,
        'hash_algorithm' => 'sha256',
        'require_manifest_membership' => true,
        'validate_before_include' => true,
        'reject_orphans' => true,
        'max_size' => 4 * 1024 * 1024,
    ],

    'signatures' => [
        'required' => true,
        'algorithm' => 'ed25519',
        'allow_unsigned_local' => false,
        'reject_revoked_keys' => true,
    ],

    'manifests' => [
        'require_signature' => true,
        'validate_schema' => true,
        'reject_unknown_schema' => true,
    ],

    'builds' => [
        'immutable' => true,
        'atomic_activation' => true,
        'pin_per_execution' => true,
        'minimum_security_epoch' => 1,
        'reject_revoked' => true,
    ],

    'rollback' => [
        'require_authorization' => true,
        'require_reason' => true,
        'reject_downgrade' => true,
        'reject_revoked_builds' => true,
    ],

    'store' => [
        'runtime_read_only' => true,
        'separate_staging' => true,
        'seal_before_activation' => true,
        'reject_symlinks' => true,
    ],

    'opcache' => [
        'unique_paths_per_build' => true,
        'replace_in_place' => false,
        'restart_on_preload_change' => true,
    ],

    'remote_cache' => [
        'enabled' => false,
        'verify_every_download' => true,
        'runtime_write_access' => false,
    ],

    'supply_chain' => [
        'require_lockfile' => true,
        'allow_composer_plugins' => false,
        'block_known_vulnerabilities' => true,
        'pin_build_images_by_digest' => true,
    ],
];
```

---

## 253. Componentes principales

```text
SecureCompilationCoordinator
ControllerCompilerRegistry
ControllerCompilationIR
CompilationSecurityPolicy
ArtifactFingerprintGenerator
ArtifactSigner
ArtifactSignatureVerifier
ArtifactManifestBuilder
ArtifactManifestValidator
SecureArtifactLoader
ArtifactPathPolicy
ImmutableArtifactStore
ControllerBuildSecurityValidator
BuildActivator
BuildRevocationRegistry
ArtifactRevocationRegistry
CompilerRevocationRegistry
PreloadSecurityValidator
OPcacheBuildCoordinator
```

---

## 254. Estructura del módulo

```text
src/
└── Quantum/
    └── Controllers/
        └── Security/
            └── Compilation/
                ├── Contracts/
                │   ├── SecureControllerCompilerInterface.php
                │   ├── SecureArtifactLoaderInterface.php
                │   ├── ArtifactTrustStoreInterface.php
                │   ├── ControllerBuildSecurityValidatorInterface.php
                │   ├── ArtifactRevocationRegistryInterface.php
                │   ├── BuildRevocationRegistryInterface.php
                │   └── CompilerRevocationRegistryInterface.php
                │
                ├── Compiler/
                │   ├── SecureCompilationCoordinator.php
                │   ├── ControllerCompilerRegistry.php
                │   ├── CompilerDescriptor.php
                │   ├── CompilerCapability.php
                │   ├── CompilerCapabilitySet.php
                │   ├── CompilerTrustLevel.php
                │   └── SecureCompilationContext.php
                │
                ├── Source/
                │   ├── CompilationSourcePolicy.php
                │   ├── SourceFingerprint.php
                │   ├── SourceSnapshot.php
                │   ├── SourcePathValidator.php
                │   └── SymlinkSecurityPolicy.php
                │
                ├── IR/
                │   ├── ControllerCompilationIR.php
                │   ├── ControllerCompilationIRValidator.php
                │   ├── IRNode.php
                │   └── IRSchema.php
                │
                ├── Generation/
                │   ├── SecurePHPArtifactGenerator.php
                │   ├── PHPArtifactSecurityValidator.php
                │   ├── GeneratedIdentifierRegistry.php
                │   ├── PHPTokenPolicy.php
                │   └── ArtifactSyntaxValidator.php
                │
                ├── Artifact/
                │   ├── CompiledArtifactInterface.php
                │   ├── ControllerArtifactType.php
                │   ├── ArtifactFingerprint.php
                │   ├── ArtifactFingerprintGenerator.php
                │   ├── ArtifactSignature.php
                │   ├── ArtifactSigner.php
                │   ├── ArtifactSignatureVerifier.php
                │   └── ArtifactSecurityValidationResult.php
                │
                ├── Manifest/
                │   ├── ArtifactManifest.php
                │   ├── ArtifactManifestEntry.php
                │   ├── ArtifactManifestBuilder.php
                │   ├── ArtifactManifestValidator.php
                │   └── ManifestSchema.php
                │
                ├── Build/
                │   ├── ControllerBuildIdentity.php
                │   ├── BuildDescriptor.php
                │   ├── BuildReference.php
                │   ├── BuildSecurityVersion.php
                │   ├── BuildSecurityValidationReport.php
                │   ├── ControllerBuildSecurityValidator.php
                │   └── BuildProvenance.php
                │
                ├── Store/
                │   ├── ImmutableArtifactStore.php
                │   ├── ArtifactStoreWriteResult.php
                │   ├── ArtifactStoreReadResult.php
                │   ├── ArtifactPathPolicy.php
                │   ├── BuildSealer.php
                │   └── ArtifactStorePermissionValidator.php
                │
                ├── Loader/
                │   ├── SecureArtifactLoader.php
                │   ├── ArtifactLoadRequest.php
                │   ├── ArtifactLoadResult.php
                │   ├── ArtifactReturnTypeValidator.php
                │   └── WorkerArtifactCache.php
                │
                ├── Activation/
                │   ├── BuildActivator.php
                │   ├── BuildActivationRecord.php
                │   ├── ActiveBuildPointer.php
                │   ├── ActivationLock.php
                │   ├── FencingToken.php
                │   └── DistributedBuildActivationCoordinator.php
                │
                ├── Revocation/
                │   ├── BuildRevocationRegistry.php
                │   ├── ArtifactRevocationRegistry.php
                │   ├── CompilerRevocationRegistry.php
                │   ├── RevocationSnapshot.php
                │   └── RevocationPolicy.php
                │
                ├── Rollback/
                │   ├── SecureRollbackManager.php
                │   ├── RollbackEligibilityValidator.php
                │   ├── DowngradeProtectionPolicy.php
                │   └── RollbackAuthorization.php
                │
                ├── Preload/
                │   ├── PreloadManifest.php
                │   ├── PreloadGenerator.php
                │   ├── PreloadSecurityValidator.php
                │   ├── PreloadEligibilityPolicy.php
                │   └── OPcacheBuildCoordinator.php
                │
                ├── Remote/
                │   ├── RemoteArtifactCache.php
                │   ├── RemoteArtifactValidator.php
                │   ├── RemoteCacheKey.php
                │   └── RemoteBuildPublisher.php
                │
                ├── SupplyChain/
                │   ├── DependencyLockValidator.php
                │   ├── ComposerPluginPolicy.php
                │   ├── BuildImagePolicy.php
                │   ├── BuildProvenanceValidator.php
                │   └── PackageCompilerAllowlist.php
                │
                ├── Audit/
                │   ├── BuildSecurityAuditRecord.php
                │   └── BuildSecurityAuditRecorder.php
                │
                ├── Events/
                ├── Metrics/
                ├── Exceptions/
                └── Testing/
```

---

## 255. Excepciones

```text
UntrustedCompilerException
CompilationSourceViolationException
InvalidCompilationIRException
UnsafeGeneratedArtifactException
ArtifactFingerprintMismatchException
ArtifactSignatureException
UnknownSigningKeyException
RevokedSigningKeyException
ManifestIntegrityException
ArtifactNotInManifestException
ArtifactPathViolationException
BuildIdentityMismatchException
MixedBuildArtifactsException
RevokedBuildException
DowngradeRejectedException
BuildActivationException
PreloadSecurityException
OPcacheBuildMismatchException
RemoteArtifactTrustException
SupplyChainPolicyViolationException
```

---

## 256. Public behavior

Estas excepciones no deberán llegar al cliente con detalles.

Normalmente producirán:

* startup failure;
* node unhealthy;
* generic 500;
* maintenance response;
* Worker termination.

---

## 257. Testing Strategy

La seguridad deberá probarse en todas las fases.

---

## 258. Compiler unit tests

* invalid metadata;
* unsafe identifier;
* raw PHP injection;
* forbidden token;
* cycle detection;
* capability violation;
* deterministic output.

---

## 259. Artifact integrity tests

* modified bytes;
* truncated files;
* wrong size;
* wrong fingerprint;
* wrong signature;
* unknown key;
* revoked key;
* mismatched type.

---

## 260. Manifest tests

* missing artifact;
* duplicate ID;
* duplicate path;
* traversal path;
* unknown schema;
* wrong build ID;
* unsigned manifest;
* orphan artifact.

---

## 261. Loader tests

* arbitrary path attempt;
* symlink escape;
* race simulation;
* wrong return type;
* class collision;
* artifact from another build;
* revoked artifact.

---

## 262. Activation tests

* partial build;
* invalid active pointer;
* atomic switch;
* concurrent activation;
* fencing token;
* rollback authorization;
* revoked rollback target.

---

## 263. OPcache tests

* unique build paths;
* stale build;
* preload mismatch;
* Worker restart requirement;
* class collision.

---

## 264. Remote cache tests

* poisoned response;
* stale manifest;
* cross-application artifact;
* modified body;
* wrong content length;
* replayed build.

---

## 265. Supply-chain tests

* unapproved compiler;
* changed lockfile;
* unauthorized Composer plugin;
* vulnerable dependency threshold;
* mutable image tag;
* PR build requesting signer access.

---

## 266. Reproducibility tests

Compilar dos veces y comparar fingerprints cuando el artifact sea determinista.

---

## 267. Property-based testing

Adecuado para:

* path validation;
* identifier generation;
* manifest canonicalization;
* cache keys;
* build IDs.

---

## 268. Fuzzing

Aplicar a:

* manifests;
* relative paths;
* artifact metadata;
* PHP generated tokens;
* serialized canonical payloads;
* archive extraction.

---

## 269. Chaos testing

Simular:

* node failure during activation;
* store partial upload;
* revocation during rollout;
* remote cache outage;
* OPcache invalidation failure;
* signing service unavailable.

---

## 270. Dynamic-compilation equivalence

Los artifacts compilados deberán producir la misma semántica de seguridad que el runtime dinámico.

---

## 271. Golden artifact tests

Podrán mantenerse outputs esperados para detectar cambios no intencionales.

No deberán sustituir validación semántica.

---

## 272. Security acceptance gates

Un build no podrá publicarse si falla:

* manifest integrity;
* signature validation;
* prohibited token scan;
* dependency trust;
* security epoch;
* build validator;
* critical tests.

---

## 273. ADR-041

**Los artefactos compilados serán tratados como código ejecutable, no como caché desechable.**

---

## 274. ADR-042

**La compilación productiva estará separada del runtime de producción.**

---

## 275. ADR-043

**La generación dinámica de artifacts estará deshabilitada por defecto en producción.**

---

## 276. ADR-044

**Los compiladores deberán operar mediante capabilities explícitas.**

---

## 277. ADR-045

**Los package compilers no tendrán acceso a signing keys ni activación de builds.**

---

## 278. ADR-046

**El pipeline utilizará una representación intermedia validable antes de generar PHP.**

---

## 279. ADR-047

**Los valores de metadata no se insertarán como PHP crudo.**

---

## 280. ADR-048

**Todo artifact generado será validado sintáctica y estructuralmente.**

---

## 281. ADR-049

**Los artifacts no podrán producir efectos secundarios al cargarse.**

---

## 282. ADR-050

**Los fingerprints utilizarán algoritmos criptográficamente seguros.**

---

## 283. ADR-051

**La identidad del artifact incluirá build, tipo, schema y dependencias.**

---

## 284. ADR-052

**En perfiles estrictos, artifacts y manifests deberán estar firmados.**

---

## 285. ADR-053

**Las claves privadas de firma no estarán disponibles en runtime.**

---

## 286. ADR-054

**El runtime cargará artifacts únicamente mediante manifest membership.**

---

## 287. ADR-055

**Los manifests almacenarán paths relativos y normalizados.**

---

## 288. ADR-056

**Los builds serán inmutables después de ser sellados.**

---

## 289. ADR-057

**La activación de builds será atómica.**

---

## 290. ADR-058

**Cada execution quedará fijada a un único build.**

---

## 291. ADR-059

**No se permitirá mezclar artifacts de builds distintos.**

---

## 292. ADR-060

**La API del loader aceptará artifact IDs, no paths arbitrarios.**

---

## 293. ADR-061

**Los artifacts se validarán antes de ejecutarse o incluirse.**

---

## 294. ADR-062

**Los runtime users tendrán acceso de solo lectura a los builds.**

---

## 295. ADR-063

**Los artifacts se almacenarán en paths únicos por build.**

---

## 296. ADR-064

**No se reemplazarán artifacts in-place en producción.**

---

## 297. ADR-065

**Los cambios de preload requerirán reinicio controlado del proceso cuando sea necesario.**

---

## 298. ADR-066

**Un rollback no podrá activar un build revocado o bajo el security epoch mínimo.**

---

## 299. ADR-067

**Las activaciones y rollbacks serán auditables.**

---

## 300. ADR-068

**TLS no sustituirá la validación criptográfica de artifacts remotos.**

---

## 301. ADR-069

**Los caches remotos serán read-only para el runtime.**

---

## 302. ADR-070

**Los manifests se publicarán después de los artifacts como señal de completitud.**

---

## 303. ADR-071

**Los Composer plugins estarán bloqueados salvo allowlist explícita.**

---

## 304. ADR-072

**Las imágenes de build deberán fijarse por digest en perfiles estrictos.**

---

## 305. ADR-073

**Los builds de código no confiable no tendrán acceso al signer productivo.**

---

## 306. ADR-074

**La revocación podrá aplicarse a artifact, build, compiler, package, key o schema.**

---

## 307. ADR-075

**Un artifact firmado por una clave revocada será rechazado.**

---

## 308. ADR-076

**Los artifacts legacy deberán recompilarse; no se migrarán arbitrariamente en runtime.**

---

## 309. ADR-077

**El warmup no ejecutará lógica de negocio ni requerirá contexto de usuario.**

---

## 310. ADR-078

**Un build con warmup o smoke validation fallida no podrá activarse.**

---

## 311. ADR-079

**El Build ID será incluido en observabilidad y contexto de ejecución.**

---

## 312. ADR-080

**Una falla de integridad de artifacts podrá marcar el nodo como no saludable y terminar Workers afectados.**

---

## 313. Implementación V1

La V1 deberá incluir:

* secure compiler registry;
* compiler descriptors;
* compiler capability model;
* source root validation;
* source fingerprints;
* compilation IR;
* safe PHP generation;
* artifact token validation;
* SHA-256 fingerprints;
* artifact manifest;
* manifest membership;
* immutable local Artifact Store;
* build identities;
* atomic active pointer;
* execution build pinning;
* secure artifact loader;
* mixed-build validation;
* build revocation;
* downgrade protection;
* unique paths per build;
* OPcache-safe deployment;
* preload manifest;
* build validation report;
* activation audit.

---

## 314. Implementación V2

Podrá incluir:

* asymmetric signatures;
* key rotation;
* signing service;
* remote artifact cache;
* distributed activation;
* build provenance;
* reproducible build verification;
* package compiler allowlist;
* vulnerability gates;
* revocation snapshots.

---

## 315. Implementación V3

Podrá incluir:

* HSM integration;
* transparency logs;
* multi-party rollback approval;
* content-addressed global artifact registry;
* isolated compiler processes;
* SLSA-like provenance;
* quorum deployments;
* automatic compromised-build quarantine.

---

## 316. Flujo seguro completo

```text
Controller Sources
        │
        ▼
Source Root Validation
        │
        ▼
Source Fingerprinting
        │
        ▼
Trusted Compiler Selection
        │
        ▼
Normalized Compilation IR
        │
        ▼
IR Security Validation
        │
        ▼
Safe Artifact Generation
        │
        ▼
Syntax and Token Validation
        │
        ▼
Fingerprint Generation
        │
        ▼
Artifact Signing
        │
        ▼
Immutable Staging Store
        │
        ▼
Signed Manifest Generation
        │
        ▼
Full Build Validation
        │
        ▼
Seal Build
        │
        ▼
Warmup and Smoke Test
        │
        ▼
Atomic Build Activation
        │
        ▼
Worker Pins Build
        │
        ▼
Manifest-Governed Artifact Loading
        │
        ▼
Runtime Integrity Verification
```

---

## 317. Conclusión

La seguridad de compilación no puede limitarse a generar archivos PHP y almacenarlos en una carpeta de caché.

En VoltStack, el sistema deberá considerar cada build como una unidad de ejecución confiable compuesta por:

```text
Build Identity
Artifact Set
Fingerprints
Signatures
Manifest
Provenance
Activation Record
Revocation State
```

Las piezas centrales serán:

```text
SecureCompilationCoordinator
ControllerCompilationIR
ArtifactFingerprint
ArtifactSignature
ArtifactManifest
ImmutableArtifactStore
SecureArtifactLoader
ControllerBuildSecurityValidator
BuildActivator
BuildRevocationRegistry
PreloadSecurityValidator
```

El resultado será una arquitectura donde el runtime pueda demostrar que el código compilado:

* proviene del build esperado;
* no fue modificado;
* fue generado por compilers aceptados;
* pertenece al manifest activo;
* es compatible con el runtime;
* no ha sido revocado;
* puede ejecutarse sin mezclar estados entre releases.

---

## 318. Siguiente parte

```text
CONTROLLER_SECURITY_MODEL_PART_04.md
```

## Transport & Response Security

Incluirá:

* HTTP request boundary;
* response classification;
* secure headers;
* cookies;
* sessions;
* CSRF;
* CORS;
* CSP;
* HSTS;
* content-type security;
* cache-control;
* redirects;
* downloads;
* uploads transport;
* streaming;
* SSE;
* range requests;
* compression;
* request smuggling defenses;
* trusted proxies;
* host validation;
* response splitting;
* SPA protocol security;
* frontend state exposure;
* exception sanitization;
* ADRs.
