# Result Transformation Engine

**Versión:** 1.0
**Estado:** Draft
**Módulo:** `VoltStack\Quantum\Http\Transformation`

---

## Documentos relacionados

```text
00_CONTROLLER_PROJECT_CONTEXT.md
01_CONTROLLER_ARCHITECTURE.md
02_CONTROLLER_BASE_CLASS.md
03_CONTROLLER_DISPATCHER.md
04_CONTROLLER_RESOLVER.md
05_PARAMETER_RESOLUTION_ENGINE.md
06_METADATA_ENGINE.md
07_CONTROLLER_INTERCEPTOR_SYSTEM.md
08_CONTROLLER_INVOKER.md
```

---

## 1. Introducción

El **Result Transformation Engine** es el motor responsable de transformar cualquier resultado devuelto por un controlador en una respuesta consumible por el cliente.

A diferencia de otros frameworks donde existe únicamente un *Result Normalizer*, VoltStack implementa un sistema completo de transformación inspirado en la filosofía de sus demás motores internos.

Su responsabilidad no es solamente convertir un objeto en una respuesta HTTP.

También deberá:

* inspeccionar el resultado
* clasificarlo
* resolver la estrategia adecuada
* negociar el contenido
* aplicar metadata
* construir respuestas HTTP
* construir respuestas SPA
* producir respuestas de streaming
* construir respuestas binarias
* generar respuestas del Runtime Volt
* preparar respuestas para APIs
* preparar respuestas SSR
* preparar respuestas híbridas

---

## 2. Objetivos

El Result Transformation Engine deberá:

* ser completamente extensible
* ser determinista
* ser compilable
* eliminar Reflection en producción
* minimizar `instanceof`
* minimizar `switch`
* minimizar lógica condicional
* ser compatible con OPcache
* ser compatible con FrankenPHP
* funcionar con cualquier tipo PHP
* permitir nuevas estrategias mediante paquetes
* reutilizar Metadata Engine
* producir siempre una respuesta válida

---

## 3. Principios

El diseño seguirá los siguientes principios.

### Responsabilidad única

Cada estrategia transforma únicamente un tipo de resultado.

---

### Open / Closed

Nuevos tipos de resultado deberán agregarse sin modificar el núcleo.

---

### Registry Driven

Las estrategias nunca serán descubiertas mediante reflexión dinámica.

Siempre existirán dentro de un Registry.

---

### Compilación

En producción deberá utilizar planes compilados.

---

### Determinismo

El mismo resultado siempre deberá producir exactamente la misma estrategia.

---

### Separación

El Controller Invoker nunca transformará resultados.

---

### Neutralidad

El controlador podrá devolver cualquier objeto.

El framework decidirá cómo transformarlo.

---

## 4. Flujo general

```text
Controller

↓

Raw Result

↓

Result Inspector

↓

Result Definition

↓

Transformation Strategy Resolver

↓

Transformation Pipeline

↓

Transformation Strategy

↓

Normalized Result

↓

Response Builder

↓

Final Response
```

---

## 5. Posición dentro del Framework

```text
Routing

↓

Controller Resolver

↓

Parameter Engine

↓

Interceptors

↓

Controller Invoker

↓

Result Transformation Engine

↓

Render Engine

↓

Http Kernel

↓

Client
```

---

## 6. Arquitectura

```text
Raw Result

↓

ResultInspector

↓

ResultDefinition

↓

ResultMetadataResolver

↓

TransformationPlanResolver

↓

TransformationStrategyRegistry

↓

TransformationPipeline

↓

TransformationStrategy

↓

TransformationResult

↓

ResponseBuilder

↓

ResponseDecoratorPipeline

↓

Final Response
```

---

## 7. Componentes

```text
ResultTransformationEngine

ResultInspector

ResultDefinition

ResultMetadata

ResultType

TransformationContext

TransformationPipeline

TransformationStrategy

TransformationStrategyRegistry

TransformationPlanResolver

TransformationPlan

CompiledTransformationPlan

TransformationCompiler

TransformationCache

TransformationResult

ResponseBuilder

ResponseDecoratorPipeline

ContentNegotiationEngine
```

---

## 8. Responsabilidades

### El Engine

Coordina todo el proceso.

---

### Inspector

Descubre el tipo del resultado.

---

### Registry

Resuelve la estrategia.

---

### Strategy

Transforma el resultado.

---

### Builder

Construye la respuesta.

---

### Decorators

Aplican metadata adicional.

---

## 9. No responsabilidades

Este motor no deberá:

* ejecutar controladores
* resolver rutas
* resolver parámetros
* validar autorización
* renderizar componentes
* hidratar SPA
* ejecutar middleware
* serializar sesiones

---

## 10. Controller Result

Todo controlador podrá devolver:

```php
mixed
```

El framework jamás impondrá un único tipo.

Ejemplos:

```php
return User::find(1);

return "Hola";

return [];

return view(...);

return redirect(...);

return Volt::component(...);

return response()->json(...);

return new JsonResponse(...);

return null;
```

---

## 11. Filosofía

El desarrollador escribe:

```php
return User::find(1);
```

El framework determina:

```text
↓

Model Result

↓

Model Strategy

↓

Resource Strategy

↓

JSON

↓

Response
```

---

## 12. Result Transformation Engine

Contrato principal.

```php
interface ResultTransformationEngineInterface
{
    public function transform(
        mixed $result,
        TransformationContext $context
    ): TransformationResult;
}
```

---

## 13. Implementación

```php
final class ResultTransformationEngine
    implements ResultTransformationEngineInterface
{
}
```

Será completamente stateless.

---

## 14. Pipeline interno

```text
inspect()

↓

resolve metadata

↓

resolve plan

↓

resolve strategy

↓

transform

↓

decorate

↓

build response
```

---

## 15. TransformationContext

Todo proceso utilizará un único contexto.

```php
final readonly class TransformationContext
{
}
```

---

## 16. Contenido del Context

```text
ControllerExecution

MetadataBag

Request

Route

ResponseFactory

Container

Environment

Attributes
```

---

## 17. Objetivo del Context

Evitar pasar veinte argumentos distintos entre estrategias.

---

## 18. Result Inspector

El inspector determina qué tipo de resultado produjo el controlador.

Contrato.

```php
interface ResultInspectorInterface
{
    public function inspect(
        mixed $result,
        TransformationContext $context
    ): ResultDefinition;
}
```

---

## 19. Filosofía del Inspector

El Inspector jamás transforma.

Únicamente clasifica.

---

## 20. ResultDefinition

Representa la descripción del resultado.

```php
final readonly class ResultDefinition
{
}
```

---

## 21. Información del ResultDefinition

```text
ResultType

PHP Type

Strategy

Metadata

Headers

Status

Priority

Attributes
```

---

## 22. Ejemplo

```php
return User::find(1);
```

Produce:

```text
ResultType

↓

Model
```

---

## 23. Otro ejemplo

```php
return "Hola";
```

Produce:

```text
ResultType

↓

String
```

---

## 24. Otro ejemplo

```php
return Volt::component(...)
```

Produce:

```text
VoltComponent
```

---

## 25. ResultType

El framework definirá un enum.

```php
enum ResultType
{
}
```

---

## 26. ResultTypes iniciales

```text
Response

JsonResponse

Redirect

View

VoltView

VoltComponent

VoltPage

SPAProtocol

Model

Collection

Resource

DTO

Array

Object

String

Integer

Float

Boolean

Null

Generator

Stream

Download

BinaryFile

Image

XML

HTML

Markdown

Promise

Future

Custom
```

---

## 27. ResultMetadata

Cada resultado tendrá metadata.

```php
final readonly class ResultMetadata
{
}
```

---

## 28. Metadata

Podrá contener:

```text
Content Type

Charset

Encoding

Compression

Language

Cache

Headers

Cookies

Status

SPA

Streaming
```

---

## 29. Metadata Engine

Toda metadata provendrá del:

```text
Metadata Engine
```

Nunca mediante lógica hardcodeada.

---

## 30. TransformationPlan

Representa cómo transformar un resultado.

```php
final readonly class TransformationPlan
{
}
```

---

## 31. Información del Plan

```text
ResultType

Strategy

Builder

Decorators

Compiled

Metadata

Hash
```

---

## 32. Plan Resolver

```php
interface TransformationPlanResolverInterface
{
    public function resolve(
        ResultDefinition $definition
    ): TransformationPlan;
}
```

---

## 33. Pipeline del Resolver

```text
Definition

↓

Compiled Plan

↓

Metadata

↓

Registry

↓

Strategy

↓

Plan
```

---

## 34. Plan compilado

Existirá:

```php
CompiledTransformationPlan
```

---

## 35. Objetivo

Eliminar trabajo repetitivo.

---

## 36. Registry

Las estrategias vivirán aquí.

```php
TransformationStrategyRegistry
```

---

## 37. Contrato

```php
interface TransformationStrategyRegistryInterface
{
}
```

---

## 38. Funciones

```text
register()

replace()

remove()

resolve()

freeze()

all()
```

---

## 39. Freeze

En producción el Registry será inmutable.

---

## 40. Transformation Strategy

Contrato principal.

```php
interface TransformationStrategyInterface
{
}
```

---

## 41. Responsabilidad

Cada estrategia transforma únicamente un tipo.

---

## 42. Ejemplo

```php
ModelStrategy
```

Nunca transformará:

```text
Generator
```

---

## 43. Otro ejemplo

```php
ViewStrategy
```

Nunca construirá un Redirect.

---

## 44. Métodos

```php
supports()

transform()

priority()
```

---

## 45. Transformación

Entrada:

```text
mixed
```

Salida:

```text
TransformationResult
```

---

## 46. TransformationResult

Objeto intermedio.

```php
final readonly class TransformationResult
{
}
```

---

## 47. Contenido

```text
Payload

Headers

Status

Cookies

Metadata

ResponseType
```

---

## 48. ResponseBuilder

Su trabajo será construir la respuesta final.

---

## 49. Contrato

```php
interface ResponseBuilderInterface
{
}
```

---

## 50. Builder

Entrada:

```text
TransformationResult
```

Salida:

```text
Response
```

---

## 51. Builder no transforma

Toda transformación ocurre antes.

---

## 52. Decorators

Después del Builder.

```text
Headers

↓

Cookies

↓

Cache

↓

Compression

↓

Tracing

↓

Metrics
```

---

## 53. ResponseDecoratorPipeline

```php
ResponseDecoratorPipeline
```

---

## 54. Decorator

Contrato.

```php
interface ResponseDecoratorInterface
{
}
```

---

## 55. Ejemplos

```text
HeaderDecorator

CookieDecorator

CacheDecorator

CorsDecorator

CompressionDecorator

SecurityDecorator
```

---

## 56. Content Negotiation

Motor independiente.

```php
ContentNegotiationEngine
```

---

## 57. Objetivo

Resolver:

```text
Accept

Accept-Language

Accept-Encoding
```

---

## 58. Nunca en las estrategias

Las estrategias no deberán inspeccionar directamente:

```text
Accept Header
```

---

## 59. Negotiation Context

Será parte del:

```text
TransformationContext
```

---

## 60. ResponseFactory

Toda respuesta será creada por una Factory.

Nunca mediante:

```php
new Response(...)
```

directamente dentro de las estrategias.

---

## 61. ResponseFactoryInterface

```php
interface ResponseFactoryInterface
{
}
```

---

## 62. Beneficios

Permite:

* Testing
* Decoración
* Instrumentación
* Adaptadores HTTP

---

## 63. Response Types

```text
HttpResponse

JsonResponse

SPAResponse

StreamResponse

BinaryResponse

RedirectResponse
```

---

## 64. Builder Pipeline

```text
TransformationResult

↓

ResponseFactory

↓

Response

↓

Decorators

↓

Response
```

---

## 65. Estrategias iniciales

```text
ResponseStrategy

JsonStrategy

RedirectStrategy

ArrayStrategy

StringStrategy

ModelStrategy

CollectionStrategy

ResourceStrategy

DTOStrategy

ViewStrategy

VoltViewStrategy

VoltComponentStrategy

SPAProtocolStrategy

StreamStrategy

GeneratorStrategy

BinaryStrategy

DownloadStrategy

NullStrategy
```

---

## 66. Prioridad

Cada estrategia tendrá prioridad.

```php
priority()
```

---

## 67. Estrategias específicas primero

Ejemplo.

```text
VoltComponent

↓

antes de

Object
```

---

## 68. Strategy Resolver

Nunca hará:

```php
instanceof

instanceof

instanceof
```

para todo.

Utilizará:

```text
Registry
```

---

## 69. Registry congelado

Esto elimina búsquedas dinámicas costosas.

---

## 70. Cache

El Engine tendrá cache.

Capas:

```text
Execution

↓

Request

↓

Worker

↓

Compiled
```

---

## 71. Cache compilado

Existirá:

```php
CompiledTransformationRegistry
```

---

## 72. Objetivo

Eliminar:

```text
Reflection

instanceof

switch
```

---

## 73. Compilación

Cada tipo podrá tener:

```php
CompiledTransformationPlan
```

---

## 74. Ejemplo

```text
UserResource

↓

ResourceStrategy

↓

JsonResponse
```

---

## 75. Sin inspección

En producción.

---

## 76. Metadata

El Engine consumirá metadata.

Nunca atributos directamente.

---

## 77. Metadata Keys

Ejemplos.

```text
response.status

response.cache

response.headers

response.cookies

response.type
```

---

## 78. TransformationContext

También contendrá:

```text
Execution Metrics

Trace

Telemetry

Request Id
```

---

## 79. Objetivo

Evitar servicios globales.

---

## 80. Integración

El siguiente bloque del documento definirá completamente:

* Sistema de estrategias.
* Registro.
* Clasificación avanzada.
* Inspección.
* Compilación.
* Caché.
* Response Builder.
* Content Negotiation.
* Transformación de cada tipo de resultado.
* Integración con Volt Runtime y SPA Protocol.

---

## 81. Transformation Strategy System

El sistema de estrategias constituye el núcleo del Result Transformation Engine.

Su objetivo es desacoplar completamente la lógica de transformación del Engine principal.

```text
Engine

↓

Strategy Resolver

↓

Transformation Strategy

↓

Transformation Result
```

El Engine nunca conocerá implementaciones concretas.

---

## 82. Principios del Strategy System

Cada estrategia deberá cumplir los siguientes principios:

* Una única responsabilidad.
* Transformar un solo tipo de resultado.
* Ser completamente stateless.
* Ser reutilizable.
* Ser registrable dinámicamente.
* Ser compilable.
* Ser fácilmente reemplazable.
* No depender de Reflection en tiempo de ejecución.

---

## 83. Contrato base

```php
interface TransformationStrategyInterface
{
    public function supports(
        ResultDefinition $definition
    ): bool;

    public function transform(
        mixed $result,
        TransformationContext $context
    ): TransformationResult;

    public function priority(): int;
}
```

---

## 84. Filosofía

El método `supports()` únicamente determina si la estrategia puede transformar el resultado.

Nunca realizará la transformación.

---

## 85. Transformación

El método `transform()` será el único responsable de producir un `TransformationResult`.

---

## 86. Prioridad

Cada estrategia tendrá una prioridad numérica.

```text
Mayor número
↓

Mayor prioridad
```

---

## 87. Ejemplo

```text
VoltComponentStrategy

priority 9000

↓

ObjectStrategy

priority 100
```

Siempre se ejecutará la estrategia más específica.

---

## 88. Transformation Strategy Registry

Todas las estrategias vivirán en un Registry central.

```php
TransformationStrategyRegistry
```

---

## 89. Objetivos del Registry

* Registrar estrategias.
* Resolver estrategias.
* Ordenarlas.
* Compilar el registro.
* Congelarlo en producción.

---

## 90. Contrato

```php
interface TransformationStrategyRegistryInterface
{
    public function register(
        TransformationStrategyInterface $strategy
    ): void;

    public function resolve(
        ResultDefinition $definition
    ): TransformationStrategyInterface;

    public function all(): iterable;

    public function freeze(): void;
}
```

---

## 91. Registro dinámico

Durante el bootstrap del framework los paquetes podrán registrar nuevas estrategias.

```php
$registry->register(
    new CustomStrategy()
);
```

---

## 92. Freeze

Una vez iniciado el runtime:

```php
$registry->freeze();
```

No podrán registrarse nuevas estrategias.

---

## 93. Beneficios

* Thread-safe.
* Compatible con FrankenPHP.
* Compatible con Workers persistentes.
* Compatible con OPcache.

---

## 94. Strategy Resolver

El Resolver será el encargado de encontrar la estrategia correcta.

Nunca el Engine.

---

## 95. Contrato

```php
interface StrategyResolverInterface
{
    public function resolve(
        ResultDefinition $definition
    ): TransformationStrategyInterface;
}
```

---

## 96. Flujo

```text
ResultDefinition

↓

Registry

↓

Candidate Strategies

↓

Priority Sort

↓

Selected Strategy
```

---

## 97. Resolución

Nunca utilizará grandes bloques de:

```php
if (...)

elseif (...)

elseif (...)
```

---

## 98. Selección

El Resolver únicamente evaluará estrategias registradas.

---

## 99. Strategy Match

Cada estrategia devolverá:

```php
true
```

o

```php
false
```

mediante `supports()`.

---

## 100. Result Classifier

Antes de resolver una estrategia se clasificará el resultado.

```php
ResultClassifier
```

---

## 101. Objetivo

Reducir el número de estrategias candidatas.

---

## 102. Ejemplo

```php
return User::find(1);
```

Clasificación:

```text
Model
```

No:

```text
Object
```

---

## 103. Otro ejemplo

```php
return [];
```

Clasificación:

```text
Array
```

---

## 104. Clasificación temprana

La clasificación ocurre una sola vez.

---

## 105. Cache de clasificación

El tipo detectado podrá almacenarse durante la ejecución.

---

## 106. Pipeline interno

```text
Raw Result

↓

Inspector

↓

Classifier

↓

Strategy Resolver

↓

Transformation
```

---

## 107. Strategy Priority

Las prioridades recomendadas:

```text
10000 Native Response

9500 SPA

9000 Volt Components

8500 Volt Views

8000 API Resources

7500 Models

7000 Collections

6500 DTO

6000 Streams

5500 Files

5000 Arrays

4500 Scalars

100 Object

0 Fallback
```

---

## 108. Native Response Strategy

Será la estrategia de mayor prioridad.

---

## 109. Objetivo

Si el controlador ya devuelve una respuesta válida:

```php
return new Response(...);
```

No deberá transformarse nuevamente.

---

## 110. ResponseStrategy

Responsabilidad:

```text
Response

↓

TransformationResult
```

Sin modificaciones.

---

## 111. JsonStrategy

Transformará:

```text
JsonResponse

JsonSerializable

JsonResource
```

---

## 112. Resultado

```text
JSON Response
```

---

## 113. ArrayStrategy

Transformará:

```php
return [
    'name' => 'VoltStack'
];
```

---

## 114. Política

Por defecto un arreglo se convertirá en JSON.

---

## 115. Metadata

La metadata podrá cambiar este comportamiento.

Ejemplo:

```text
response.type = xml
```

---

## 116. StringStrategy

Transformará:

```php
return "Hola Mundo";
```

---

## 117. Resultado

```text
text/plain
```

Por defecto.

---

## 118. Metadata

Podrá indicar:

```text
text/html

text/markdown

text/xml
```

---

## 119. ScalarStrategy

Agrupa:

```text
int

float

bool
```

---

## 120. Conversión

Los escalares serán convertidos a texto salvo que exista una política distinta.

---

## 121. NullStrategy

Responsabilidad:

```php
return null;
```

---

## 122. Resultado por defecto

```text
204 No Content
```

---

## 123. Configurable

Podrá configurarse para responder:

```text
200 OK
```

con cuerpo vacío.

---

## 124. ObjectStrategy

Será una estrategia de último recurso.

---

## 125. Filosofía

Nunca debe ejecutarse si existe una estrategia más específica.

---

## 126. CollectionStrategy

Transformará:

* Collections del framework.
* Collections compatibles.
* Iterables enriquecidos.

---

## 127. Política

La representación por defecto será JSON.

---

## 128. ModelStrategy

Responsabilidad:

Transformar entidades del ORM.

---

## 129. Filosofía

Nunca expondrá automáticamente todas las propiedades del modelo.

---

## 130. Integración

Consultará el Metadata Engine para determinar:

* Campos visibles.
* Campos ocultos.
* Relaciones.
* Transformadores.
* Recursos asociados.

---

## 131. DTOStrategy

Transformará cualquier DTO registrado.

---

## 132. Beneficio

Evita lógica repetitiva dentro de los controladores.

---

## 133. ResourceStrategy

Será utilizada por los recursos de API.

Inspirada en Laravel Resources, pero desacoplada del HTTP.

---

## 134. Transformación

```text
Resource

↓

Payload

↓

TransformationResult
```

---

## 135. Strategy Pipeline

```text
supports()

↓

transform()

↓

TransformationResult
```

---

## 136. Nunca

Una estrategia no podrá invocar otra estrategia directamente.

Toda coordinación pertenece al Engine.

---

## 137. Strategy Decorators

Las estrategias podrán decorarse.

Ejemplo:

```text
Metrics

↓

Tracing

↓

Logging

↓

Strategy
```

---

## 138. Beneficio

No contaminar la lógica principal.

---

## 139. Instrumentación

Cada estrategia podrá registrar:

* Tiempo.
* Memoria.
* Resultado.
* Errores.

---

## 140. Eventos

Se emitirán eventos durante la transformación:

```text
StrategyResolving

StrategyResolved

TransformationStarting

TransformationSucceeded

TransformationFailed
```

---

## 141. Cache de estrategias

Una vez resuelta una estrategia podrá reutilizarse durante toda la ejecución.

---

## 142. Compiled Strategy Registry

Durante el proceso de compilación se generará un registro optimizado.

```php
CompiledTransformationStrategyRegistry
```

---

## 143. Objetivo

Eliminar búsquedas dinámicas.

---

## 144. Ejemplo

```text
Model

↓

ModelStrategy
```

Sin recorrer todo el Registry.

---

## 145. Transformation Compiler

Será responsable de generar:

```text
CompiledTransformationPlan
CompiledStrategyRegistry
CompiledMappings
```

---

## 146. Compatibilidad con OPcache

Todos los artefactos compilados deberán ser simples clases PHP para maximizar el rendimiento.

---

## 147. Compatibilidad con FrankenPHP

Los planes compilados serán completamente inmutables y seguros para procesos persistentes.

---

## 148. Integración con Metadata Engine

Las estrategias nunca leerán atributos PHP directamente.

Toda la información deberá obtenerse desde:

```text
MetadataBag
```

---

## 149. Beneficios

Esto permite:

* Compilación.
* Cache.
* Plugins.
* Overrides.
* Consistencia entre módulos.

---

## 150. Próxima sección

La Parte 3 desarrollará el sistema avanzado de construcción de respuestas:

* ResponseBuilder.
* ResponseFactory.
* ResponseDecoratorPipeline.
* Content Negotiation Engine.
* HTTP Responses.
* SPA Responses.
* Redirect Responses.
* Stream Responses.
* Download Responses.
* Binary Responses.
* Integración con Volt Render Engine.
* Integración con Volt SPA Runtime.

---

## 151. Response Construction Layer

Una vez que una estrategia produce un `TransformationResult`, comienza la segunda fase del motor: la **construcción de la respuesta**.

Esta capa transforma un resultado normalizado en una respuesta final compatible con el protocolo de salida (HTTP, SPA, SSE, CLI, etc.).

```text
TransformationResult
        │
        ▼
Response Builder
        │
        ▼
Response Decorator Pipeline
        │
        ▼
Final Response
```

---

## 152. Objetivos

La Response Construction Layer deberá:

* Construir cualquier tipo de respuesta.
* Permanecer desacoplada de las estrategias.
* Permitir múltiples protocolos de salida.
* Ser completamente extensible.
* Ser compilable.
* No depender del tipo original del resultado.

---

## 153. Responsabilidad

Las estrategias producen información.

Los Builders producen respuestas.

Nunca al revés.

---

## 154. ResponseBuilder

Contrato principal.

```php
interface ResponseBuilderInterface
{
    public function supports(
        TransformationResult $result
    ): bool;

    public function build(
        TransformationResult $result,
        TransformationContext $context
    ): ResponseInterface;
}
```

---

## 155. Filosofía

El Builder jamás inspecciona el resultado original.

Únicamente recibe un `TransformationResult`.

---

## 156. Builder Registry

Todos los builders vivirán dentro de:

```php
ResponseBuilderRegistry
```

---

## 157. Contrato

```php
interface ResponseBuilderRegistryInterface
{
    public function register(
        ResponseBuilderInterface $builder
    ): void;

    public function resolve(
        TransformationResult $result
    ): ResponseBuilderInterface;

    public function freeze(): void;
}
```

---

## 158. Builders iniciales

```text
HttpResponseBuilder

JsonResponseBuilder

RedirectResponseBuilder

BinaryResponseBuilder

StreamResponseBuilder

DownloadResponseBuilder

SpaResponseBuilder

ServerSentEventsBuilder

FileResponseBuilder
```

---

## 159. Builder Resolver

El Builder Resolver selecciona el Builder correcto según el `ResponseType` definido en el `TransformationResult`.

---

## 160. ResponseType

Nuevo enum del sistema.

```php
enum ResponseType
{
}
```

---

## 161. Response Types

```text
Http

Json

Spa

Redirect

Stream

Binary

Download

File

SSE

Custom
```

---

## 162. HTTP Response Builder

Builder por defecto.

Construye respuestas HTTP estándar.

---

## 163. JsonResponseBuilder

Especializado para respuestas JSON.

No realiza serialización.

La serialización ya ocurrió durante la transformación.

---

## 164. RedirectResponseBuilder

Construye respuestas 301, 302, 303, 307 y 308.

---

## 165. StreamResponseBuilder

Construye respuestas basadas en flujos continuos.

Compatible con:

* Generators.
* Streams.
* Recursos.
* Lectores incrementales.

---

## 166. DownloadResponseBuilder

Construye respuestas de descarga.

Configurará automáticamente:

* Content-Disposition.
* Content-Length.
* MIME Type.

---

## 167. BinaryResponseBuilder

Especializado en contenido binario.

Ejemplos:

* PDF.
* ZIP.
* Imágenes.
* Audio.
* Video.

---

## 168. SPAResponseBuilder

Builder exclusivo del Runtime Volt.

Su salida no necesariamente será una respuesta HTML.

Podrá producir directamente un **Volt Protocol Response**.

---

## 169. SSEBuilder

Builder para **Server-Sent Events**.

Permitirá integrar streaming reactivo sin WebSockets.

---

## 170. ResponseFactory

Todos los Builders utilizarán una Factory común.

Nunca instanciarán respuestas directamente.

---

## 171. Contrato

```php
interface ResponseFactoryInterface
{
}
```

---

## 172. Responsabilidad

Centralizar la creación de:

* Response.
* JsonResponse.
* RedirectResponse.
* StreamResponse.
* BinaryResponse.
* SpaResponse.

---

## 173. Beneficios

Permite:

* Decoración.
* Instrumentación.
* Testing.
* Sustitución de implementaciones.

---

## 174. Response Decorator Pipeline

Una respuesta recién creada aún no está lista para enviarse.

Debe pasar por un pipeline de decoración.

```text
Response

↓

Decorators

↓

Final Response
```

---

## 175. Objetivos

Aplicar modificaciones transversales sin alterar el Builder.

---

## 176. Contrato

```php
interface ResponseDecoratorInterface
{
    public function decorate(
        ResponseInterface $response,
        TransformationContext $context
    ): ResponseInterface;
}
```

---

## 177. Decorator Pipeline

```php
ResponseDecoratorPipeline
```

---

## 178. Decorators iniciales

```text
HeaderDecorator

CookieDecorator

CacheDecorator

CorsDecorator

CompressionDecorator

ETagDecorator

SecurityDecorator

TelemetryDecorator

TracingDecorator
```

---

## 179. Orden recomendado

```text
Headers

↓

Cookies

↓

Caching

↓

Compression

↓

Security

↓

Telemetry
```

---

## 180. HeaderDecorator

Añade encabezados provenientes del `TransformationResult` y del `MetadataBag`.

---

## 181. CookieDecorator

Gestiona todas las cookies pendientes.

---

## 182. CacheDecorator

Aplica políticas como:

* Cache-Control.
* Expires.
* Last-Modified.
* Immutable.
* Vary.

---

## 183. CompressionDecorator

Permite aplicar compresión de salida cuando la política lo indique.

---

## 184. SecurityDecorator

Aplica encabezados de seguridad.

Ejemplos:

* CSP.
* X-Frame-Options.
* X-Content-Type-Options.
* Referrer-Policy.

---

## 185. TelemetryDecorator

Inserta información utilizada por observabilidad.

---

## 186. Content Negotiation Engine

El Engine de negociación decide el formato final de la respuesta.

---

## 187. Filosofía

Las estrategias producen datos.

La negociación decide cómo entregarlos.

---

## 188. Entrada

```text
TransformationResult

+

Request

+

Metadata
```

---

## 189. Salida

```text
Negotiated Response Type
```

---

## 190. Factores considerados

* Accept.
* Accept-Language.
* Accept-Encoding.
* Route Metadata.
* Controller Metadata.
* Runtime Mode.
* SPA Mode.

---

## 191. Accept Resolver

```php
AcceptHeaderResolver
```

Especializado en interpretar el encabezado `Accept`.

---

## 192. Language Resolver

Resolverá el idioma preferido del cliente.

---

## 193. Encoding Resolver

Resolverá:

* gzip.
* br.
* deflate.
* identity.

---

## 194. Negotiation Policy

Las políticas serán configurables.

Ejemplo:

```text
PreferJson

PreferHtml

PreferSpa

PreferXml
```

---

## 195. Negotiation Result

Se almacenará dentro del `TransformationContext`.

---

## 196. Volt View Integration

Cuando el resultado sea una `VoltView`, el motor no renderizará directamente.

Delegará al **Volt Render Engine**.

---

## 197. Flujo

```text
VoltView

↓

Volt Render Engine

↓

HTML

↓

Response Builder
```

---

## 198. Volt Component Integration

Para componentes interactivos:

```text
VoltComponent

↓

Volt Runtime

↓

Volt Protocol

↓

SPAResponseBuilder
```

---

## 199. SPA Protocol

El Result Transformation Engine nunca construirá manualmente el protocolo.

Delegará esa responsabilidad al Runtime SPA.

---

## 200. Server Side Rendering

Cuando la ruta utilice SSR:

```text
Volt Component

↓

SSR Renderer

↓

HTML

↓

HTTP Response
```

---

## 201. Client Navigation

Cuando la petición sea SPA:

```text
Volt Component

↓

Volt Protocol

↓

SPA Response
```

---

## 202. Render Modes

```text
SSR

CSR

SPA

Hybrid
```

---

## 203. Render Policy

El modo podrá definirse mediante metadata.

---

## 204. Response Metadata

Toda respuesta tendrá un objeto de metadata asociado.

```php
ResponseMetadata
```

---

## 205. Contenido

* Status.
* Headers.
* Cookies.
* Cache.
* Language.
* Encoding.
* Runtime Flags.

---

## 206. Response Policies

El sistema permitirá definir políticas reutilizables.

Ejemplos:

```text
ApiPolicy

SpaPolicy

DownloadPolicy

StaticFilePolicy
```

---

## 207. Integración con Metadata Engine

Toda política será obtenida desde el `MetadataBag`.

Nunca mediante atributos consultados directamente.

---

## 208. Error Responses

Los errores también pasarán por el Result Transformation Engine.

Esto garantiza un único mecanismo de construcción de respuestas.

---

## 209. Beneficio

Las respuestas de éxito y de error compartirán exactamente el mismo pipeline.

---

## 210. Próxima sección

La Parte 4 desarrollará los tipos avanzados de resultados y la integración con el Runtime Volt:

* ModelStrategy avanzada.
* ResourceStrategy avanzada.
* VoltViewStrategy.
* VoltComponentStrategy.
* SPAProtocolStrategy.
* StreamStrategy.
* GeneratorStrategy.
* DownloadStrategy.
* BinaryStrategy.
* Async Results.
* Future y Promise.
* Integración completa con el Render Engine y el Hydration Engine.

---

## 211. Advanced Transformation Strategies

Las estrategias avanzadas son responsables de transformar resultados complejos producidos por el ecosistema de VoltStack.

A diferencia de las estrategias básicas (String, Array, Null, Scalar), estas interactúan con otros motores del framework.

```text
ORM
Render Engine
SPA Runtime
Hydration Engine
Resources
Streaming
Concurrency
```

---

## 212. Principios

Las estrategias avanzadas deberán:

* Ser completamente desacopladas.
* No conocer el protocolo HTTP.
* Delegar responsabilidades especializadas.
* Reutilizar motores existentes.
* Ser compatibles con compilación.

---

## 213. ModelStrategy

La `ModelStrategy` transforma entidades del ORM de VoltStack.

Nunca serializa directamente el modelo.

---

## 214. Flujo

```text
Model

↓

Metadata Engine

↓

Serialization Profile

↓

Resource Resolver

↓

Payload

↓

TransformationResult
```

---

## 215. Objetivo

Separar completamente el modelo persistente de su representación pública.

---

## 216. Resource Resolution

Antes de serializar un modelo, la estrategia consultará si existe un recurso asociado.

Ejemplo:

```php
User
```

↓

```php
UserResource
```

---

## 217. Resource Resolver

```php
interface ResourceResolverInterface
{
    public function resolve(
        object $model
    ): ?ResourceDefinition;
}
```

---

## 218. Beneficios

Permite:

* Transformaciones consistentes.
* Versionado de API.
* Campos condicionales.
* Inclusión de relaciones.

---

## 219. CollectionStrategy

Transforma colecciones del ORM y colecciones genéricas.

---

## 220. Tipos soportados

```text
ModelCollection

LazyCollection

Iterator

Traversable

GeneratorCollection

Custom Collections
```

---

## 221. Lazy Collections

Las colecciones perezosas nunca serán materializadas automáticamente.

La política de transformación decidirá si:

* Mantener streaming.
* Materializar.
* Paginar.

---

## 222. Pagination

Si la colección contiene metadatos de paginación, estos se preservarán en el `TransformationResult`.

---

## 223. DTOStrategy

Responsable de transformar DTOs registrados.

---

## 224. Filosofía

El DTO representa un contrato de datos, no un mecanismo de serialización.

---

## 225. Metadata

El `Metadata Engine` podrá definir:

* Alias.
* Campos visibles.
* Campos ocultos.
* Conversores.
* Formatos.

---

## 226. ResourceStrategy

Especializada en recursos de API.

Inspirada en Laravel Resources, pero desacoplada del transporte.

---

## 227. Flujo

```text
Resource

↓

Payload Builder

↓

TransformationResult
```

---

## 228. Relaciones

Los recursos podrán incluir relaciones bajo demanda.

---

## 229. Versionado

El recurso podrá variar según:

* API Version.
* Tenant.
* Feature Flags.
* Metadata.

---

## 230. VoltViewStrategy

Especializada en vistas Volt.

---

## 231. Responsabilidad

Nunca renderiza HTML directamente.

Delega el proceso al Render Engine.

---

## 232. Flujo

```text
VoltView

↓

Render Engine

↓

Rendered Document

↓

TransformationResult
```

---

## 233. Beneficio

El Result Transformation Engine permanece independiente del sistema de plantillas.

---

## 234. VoltComponentStrategy

Especializada en componentes interactivos.

---

## 235. Filosofía

Un componente Volt no representa HTML.

Representa una unidad de UI reactiva.

---

## 236. Delegación

La estrategia delegará al Runtime SPA.

---

## 237. Flujo

```text
Volt Component

↓

Component Compiler

↓

Hydration Plan

↓

Volt Protocol

↓

TransformationResult
```

---

## 238. Component Metadata

La metadata podrá definir:

* Layout.
* Lazy Loading.
* Hydration Mode.
* Islands.
* Cache.

---

## 239. VoltPageStrategy

Especializada en páginas completas.

---

## 240. Diferencia

Un `VoltPage` puede contener múltiples componentes.

---

## 241. Flujo

```text
Volt Page

↓

Layout Resolver

↓

Render Engine

↓

Volt Runtime

↓

TransformationResult
```

---

## 242. SPAProtocolStrategy

Produce directamente una respuesta compatible con el protocolo Volt.

---

## 243. Resultado

```text
Volt Protocol

↓

SPAResponseBuilder
```

---

## 244. Integración

No conoce HTTP.

Solo produce un protocolo de aplicación.

---

## 245. StreamStrategy

Especializada en flujos continuos.

---

## 246. Tipos soportados

```text
StreamInterface

Resource

Generator

ReadableStream

Custom Stream
```

---

## 247. Filosofía

El contenido nunca deberá cargarse completamente en memoria.

---

## 248. GeneratorStrategy

Especializada en objetos `Generator`.

---

## 249. Beneficio

Permite:

* Streaming.
* Procesamiento incremental.
* Grandes volúmenes de datos.

---

## 250. DownloadStrategy

Especializada en descargas.

---

## 251. Metadata

La metadata podrá definir:

* Nombre de archivo.
* MIME Type.
* Disposition.
* Cache.
* Range Requests.

---

## 252. BinaryStrategy

Responsable de archivos binarios.

Ejemplos:

* ZIP.
* PDF.
* Video.
* Audio.

---

## 253. FileStrategy

Especializada en archivos locales o remotos.

---

## 254. ImageStrategy

Especializada en imágenes.

---

## 255. Capacidades

Podrá integrar:

* Resize.
* Cache.
* WebP.
* AVIF.
* Responsive Images.

Siempre mediante motores especializados.

---

## 256. HtmlStrategy

Produce documentos HTML.

---

## 257. MarkdownStrategy

Transforma Markdown utilizando el motor configurado.

---

## 258. XmlStrategy

Produce documentos XML.

---

## 259. CsvStrategy

Especializada en exportaciones tabulares.

---

## 260. AsyncResultStrategy

Responsable de resultados asíncronos.

---

## 261. Tipos soportados

```text
Future

Promise

Task

Awaitable

Fiber Result
```

---

## 262. Filosofía

El Engine no bloqueará innecesariamente la ejecución.

---

## 263. Await Resolver

Resolverá el momento adecuado para materializar el resultado.

---

## 264. Streaming Asíncrono

Permitirá combinar:

```text
Future

+

Stream

+

SSE
```

---

## 265. Hydration Integration

El Result Transformation Engine nunca hidrata componentes.

Delega completamente al:

```text
Hydration Engine
```

---

## 266. Render Integration

Toda generación de HTML será responsabilidad del:

```text
Volt Render Engine
```

---

## 267. Runtime Integration

Toda construcción del protocolo SPA será responsabilidad del:

```text
Volt Runtime
```

---

## 268. Component Manifest

Cuando un componente requiera un manifiesto, éste será resuelto por el Runtime.

---

## 269. SSR Integration

En modo SSR el flujo será:

```text
Component

↓

Render Engine

↓

HTML

↓

TransformationResult
```

---

## 270. SPA Integration

En modo SPA:

```text
Component

↓

Hydration Plan

↓

Volt Protocol

↓

TransformationResult
```

---

## 271. Hybrid Mode

Permitirá combinar SSR con hidratación progresiva.

---

## 272. Islands Architecture

Los componentes marcados como "Island" conservarán su metadata durante toda la transformación.

---

## 273. Deferred Components

Los componentes diferidos no serán materializados inmediatamente.

---

## 274. Progressive Rendering

El motor podrá producir resultados parciales cuando la política de renderizado lo permita.

---

## 275. Transformation Policies

Cada estrategia podrá consultar políticas declarativas.

Ejemplos:

```text
ApiPolicy

SpaPolicy

RenderPolicy

StreamingPolicy

CachePolicy
```

---

## 276. Strategy Composition

Una estrategia podrá delegar en motores especializados.

Nunca en otra estrategia.

---

## 277. Beneficio

Se evita crear dependencias circulares entre estrategias.

---

## 278. Observabilidad

Cada estrategia avanzada emitirá métricas específicas.

Ejemplos:

* Tiempo de render.
* Tiempo de hidratación.
* Bytes transmitidos.
* Tamaño del payload.

---

## 279. Compatibilidad con FrankenPHP

Todas las estrategias deberán ser:

* Stateless.
* Reutilizables.
* Thread-safe.
* Libres de estado compartido mutable.

---

## 280. Próxima sección

La Parte 5 desarrollará:

* Compiled Transformation Plans.
* Transformation Compiler.
* Cache Architecture.
* Metadata Integration.
* Observabilidad completa.
* Eventos.
* Benchmarks.
* Testing.
* Directorios finales.
* ADRs.
* Roadmap V1/V2.
* Integración con HttpKernel y Runtime Bootstrap.

---

## 281. Transformation Compiler

El **Transformation Compiler** es responsable de convertir la configuración dinámica del Result Transformation Engine en estructuras compiladas e inmutables optimizadas para producción.

Durante el proceso de compilación se eliminarán búsquedas dinámicas, reflexión innecesaria y resolución repetitiva de estrategias.

Su objetivo es que la transformación de resultados en producción sea esencialmente una operación de acceso a estructuras previamente calculadas.

---

## 282. Objetivos

El compilador deberá:

* Compilar planes de transformación.
* Compilar el registro de estrategias.
* Compilar metadata relevante.
* Generar artefactos PHP compatibles con OPcache.
* Eliminar trabajo repetitivo en tiempo de ejecución.
* Ser incremental cuando sea posible.
* Permitir recompilación parcial.

---

## 283. Principios

El compilador seguirá estos principios:

* Determinista.
* Reproducible.
* Idempotente.
* Independiente del Request.
* Libre de estado mutable.
* Seguro para Workers persistentes.

---

## 284. Pipeline del Compilador

```text
Transformation Definitions
        │
        ▼
Metadata Extraction
        │
        ▼
Strategy Analysis
        │
        ▼
Plan Generation
        │
        ▼
Registry Optimization
        │
        ▼
PHP Artifact Generation
        │
        ▼
Compiled Cache
```

---

## 285. TransformationCompilerInterface

```php
interface TransformationCompilerInterface
{
    public function compile(): CompiledTransformationRegistry;
}
```

---

## 286. Implementación

```php
final class TransformationCompiler
    implements TransformationCompilerInterface
{
}
```

La implementación oficial será completamente stateless.

---

## 287. CompiledTransformationRegistry

Representa el conjunto completo de estrategias compiladas.

```php
final readonly class CompiledTransformationRegistry
{
}
```

---

## 288. Responsabilidades

El registro compilado contendrá:

* Estrategias disponibles.
* Mapeos por tipo.
* Prioridades resueltas.
* Planes compilados.
* Hashes de consistencia.
* Versión del compilador.

---

## 289. Filosofía

Durante producción el Engine consultará preferentemente este registro antes que el registro dinámico.

---

## 290. CompiledTransformationPlan

Cada tipo de resultado podrá disponer de un plan compilado.

```php
final readonly class CompiledTransformationPlan
{
}
```

---

## 291. Contenido

Un plan compilado contendrá, entre otros:

* Identificador del tipo.
* Estrategia seleccionada.
* Builder asociado.
* Decoradores.
* Política de negociación.
* Metadata consolidada.
* Hash de validación.

---

## 292. Objetivo

Evitar la reconstrucción del pipeline para cada petición.

---

## 293. TransformationPlanFactory

Responsable de construir planes dinámicos cuando no exista un plan compilado.

```php
interface TransformationPlanFactoryInterface
{
    public function create(
        ResultDefinition $definition
    ): TransformationPlan;
}
```

---

## 294. Estrategia de resolución

```text
Compiled Plan
      │
      ├── Existe → utilizar
      │
      └── No existe
              │
              ▼
TransformationPlanFactory
```

---

## 295. Dynamic Fallback

Si el plan compilado no es válido o está ausente, el sistema recurrirá automáticamente a la resolución dinámica.

Este comportamiento será transparente para el desarrollador.

---

## 296. Plan Versioning

Cada plan compilado incluirá:

* Versión del framework.
* Versión del compilador.
* Hash de metadata.
* Hash de estrategias.
* Hash de configuración.

Esto permitirá invalidaciones precisas.

---

## 297. Strategy Compilation

Durante la compilación se resolverán todas las prioridades entre estrategias.

Ejemplo:

```text
VoltComponentStrategy
    priority: 9000

ObjectStrategy
    priority: 100
```

El resultado compilado almacenará directamente la estrategia ganadora.

---

## 298. Strategy Mapping

El compilador generará tablas optimizadas similares a:

```text
ResultType
        │
        ▼
TransformationStrategy
```

El Engine evitará recorrer el Registry completo.

---

## 299. Optimización de búsquedas

Las búsquedas pasarán de una resolución lineal a una resolución prácticamente constante.

---

## 300. Registry Freeze

Una vez compilado:

```php
$registry->freeze();
```

El registro será completamente inmutable.

---

## 301. Cache Architecture

El Result Transformation Engine utilizará una arquitectura multinivel.

```text
L1 → Execution Cache
L2 → Request Cache
L3 → Worker Cache
L4 → Compiled Cache
```

---

## 302. L1 - Execution Cache

Vive únicamente durante la ejecución de un controlador.

Almacena:

* ResultDefinition.
* TransformationPlan.
* Strategy resuelta.

Se destruye al finalizar la ejecución.

---

## 303. L2 - Request Cache

Comparte información durante todo el ciclo de vida de una petición HTTP.

Evita recalcular datos cuando múltiples componentes transforman resultados dentro de la misma petición.

---

## 304. L3 - Worker Cache

Disponible únicamente en entornos con procesos persistentes (por ejemplo, FrankenPHP).

Almacena:

* Planes compilados reutilizados.
* Metadata consolidada.
* Builders.
* Decoradores.
* Tablas de resolución.

---

## 305. L4 - Compiled Cache

Representa los artefactos PHP generados durante la compilación.

Está diseñado para aprovechar OPcache al máximo.

---

## 306. Cache Invalidation

La invalidación podrá dispararse por:

* Cambio de configuración.
* Cambio de metadata.
* Registro de nuevas estrategias.
* Actualización del framework.
* Cambio de versión de paquetes.

---

## 307. Metadata Integration

El Result Transformation Engine nunca leerá atributos PHP directamente.

Toda la información será obtenida desde el **Metadata Engine**.

---

## 308. MetadataBag

Cada transformación recibirá un `MetadataBag` previamente resuelto.

Esto elimina múltiples consultas repetidas durante el pipeline.

---

## 309. Metadata Keys

Ejemplos de claves consumidas:

```text
response.type
response.status
response.headers
response.cookies
response.cache
response.language
response.encoding
response.streaming
response.spa
response.render
```

---

## 310. Metadata Merge

Cuando existan múltiples fuentes de metadata (controlador, ruta, componente, configuración), el Engine utilizará las estrategias de mezcla definidas por el `Metadata Engine`.

---

## 311. Performance Model

El objetivo principal es minimizar:

* Reflection.
* `instanceof`.
* Resoluciones repetidas.
* Construcción dinámica de pipelines.
* Serializaciones innecesarias.

---

## 312. Diseño para OPcache

Todos los artefactos compilados serán clases PHP simples, favoreciendo:

* Precarga.
* OPcache.
* Warmup.
* Baja fragmentación de memoria.

---

## 313. Compatibilidad con FrankenPHP

Todos los objetos almacenados en el Worker Cache deberán ser:

* Inmutables.
* Reutilizables.
* Libres de referencias al Request.
* Libres de referencias al Response.
* Libres de referencias a ControllerExecution.

---

## 314. Seguridad de Memoria

Nunca se almacenarán en caché:

* Request.
* Response.
* ControllerExecution.
* TransformationContext.
* Payloads específicos de una petición.

---

## 315. Objetos compartibles

Sí podrán compartirse:

* Planes compilados.
* Builders stateless.
* Decoradores stateless.
* Metadata compilada.
* Tablas de resolución.
* Registros congelados.

---

## 316. Warmup

El compilador podrá ejecutarse durante el proceso de despliegue para generar todos los artefactos antes de recibir tráfico.

---

## 317. Recompilación

El sistema permitirá recompilar únicamente los módulos afectados cuando cambien:

* Estrategias.
* Metadata.
* Configuración.

Sin necesidad de reconstruir todo el registro.

---

## 318. Resultado

Al finalizar esta fase, el Result Transformation Engine dispondrá de una infraestructura optimizada para producción, preparada para aprovechar OPcache y los Workers persistentes de FrankenPHP sin sacrificar extensibilidad ni mantenibilidad.

---

## 319. Próxima sección

La **Parte 05B** desarrollará:

* Observabilidad.
* Sistema completo de eventos.
* Métricas.
* Tracing distribuido.
* Performance Model avanzado.
* Testing Strategy.
* Integración con el sistema de diagnóstico de VoltStack.

---

## 320. Observabilidad

El **Result Transformation Engine** deberá integrarse completamente con el sistema de observabilidad de VoltStack.

Toda transformación será observable sin modificar las estrategias ni los builders.

La observabilidad será transversal y desacoplada.

---

## 321. Objetivos

El sistema permitirá:

* Diagnosticar transformaciones.
* Medir rendimiento.
* Detectar cuellos de botella.
* Analizar errores.
* Auditar decisiones del motor.
* Alimentar herramientas de profiling.

---

## 322. Arquitectura

```text
Transformation Engine
        │
        ▼
Event Dispatcher
        │
        ├── Metrics
        ├── Tracing
        ├── Logging
        ├── Profiling
        └── Debug Tools
```

---

## 323. Principios

La observabilidad deberá ser:

* Pasiva.
* No intrusiva.
* Configurable.
* Asíncrona cuando sea posible.
* Compatible con producción.

---

## 324. Transformation Events

El Engine emitirá eventos durante todo el ciclo de vida.

Estos eventos nunca modificarán el resultado de la transformación.

---

## 325. Eventos principales

```text
TransformationStarted

ResultInspected

ResultClassified

PlanResolved

StrategyResolved

TransformationSucceeded

TransformationFailed

BuilderResolved

ResponseBuilt

TransformationCompleted
```

---

## 326. Strategy Events

Cada estrategia podrá emitir eventos específicos.

Ejemplo:

```text
ModelTransformationStarted

ModelTransformationFinished

VoltComponentTransformationStarted

VoltComponentTransformationFinished
```

---

## 327. Builder Events

Los builders también serán observables.

```text
ResponseBuilderResolved

ResponseBuilding

ResponseBuilt
```

---

## 328. Decorator Events

Cada decorador podrá registrar su ejecución.

Ejemplos:

```text
HeadersApplied

CookiesApplied

CompressionApplied

CacheHeadersApplied

SecurityHeadersApplied
```

---

## 329. Error Events

Cuando ocurra una excepción durante la transformación:

```text
TransformationExceptionRaised
```

El evento contendrá:

* Excepción.
* Strategy.
* Plan.
* Metadata relevante.
* Tiempo transcurrido.

---

## 330. Telemetry Context

Toda la información de telemetría viajará dentro de un contexto compartido.

```php
final readonly class TransformationTelemetryContext
{
}
```

---

## 331. Contenido

El contexto podrá incluir:

* TraceId.
* SpanId.
* RequestId.
* RouteName.
* Controller.
* Strategy.
* Builder.
* ResultType.

---

## 332. Metrics System

El motor publicará métricas estandarizadas.

---

## 333. Métricas básicas

```text
transformations.total

transformations.success

transformations.failed

transformations.duration

builders.total

decorators.total
```

---

## 334. Métricas por estrategia

Ejemplos:

```text
strategy.model.duration

strategy.resource.duration

strategy.json.duration

strategy.component.duration
```

---

## 335. Métricas por Builder

```text
builder.json.duration

builder.http.duration

builder.spa.duration

builder.stream.duration
```

---

## 336. Métricas por Decorator

```text
decorator.headers.duration

decorator.cookies.duration

decorator.cache.duration

decorator.compression.duration
```

---

## 337. Histogramas

El sistema deberá soportar histogramas para:

* Tiempo.
* Tamaño del payload.
* Número de decoradores.
* Número de estrategias evaluadas.

---

## 338. Counters

Se utilizarán contadores para:

* Transformaciones.
* Errores.
* Reintentos.
* Fallos de compilación.

---

## 339. Gauges

Ejemplos:

* Builders registrados.
* Estrategias registradas.
* Planes compilados.

---

## 340. Tracing

El Engine se integrará con el sistema de tracing del framework.

---

## 341. Span Principal

Cada transformación abrirá un span principal.

```text
Result Transformation
```

---

## 342. Subspans

```text
Inspection

Classification

Plan Resolution

Strategy Execution

Response Builder

Decorators
```

---

## 343. Beneficio

Permite detectar rápidamente qué fase consume más tiempo.

---

## 344. Profiling

En modo Debug el motor podrá generar perfiles detallados.

---

## 345. Información del Perfil

* Tiempo por etapa.
* Tiempo por estrategia.
* Builder utilizado.
* Decoradores ejecutados.
* Metadata aplicada.
* Tamaño del payload.

---

## 346. Timeline

El profiler podrá representar la transformación como una línea temporal.

```text
Inspect
  │
Classify
  │
Resolve Strategy
  │
Transform
  │
Build Response
  │
Decorate
```

---

## 347. Logging

El motor nunca escribirá directamente en un logger concreto.

Delegará al sistema de logging del framework.

---

## 348. Niveles recomendados

```text
DEBUG

INFO

NOTICE

WARNING

ERROR

CRITICAL
```

---

## 349. Debug Mode

Cuando el framework opere en modo Debug se registrará información adicional.

Ejemplos:

* Estrategia elegida.
* Builder elegido.
* Metadata utilizada.
* Plan compilado o dinámico.

---

## 350. Production Mode

En producción la observabilidad minimizará el impacto sobre el rendimiento.

---

## 351. Benchmarking

El Engine incluirá benchmarks oficiales.

---

## 352. Casos de Benchmark

```text
Scalar

Array

DTO

Model

Collection

Volt View

Volt Component

SPA

Stream
```

---

## 353. Indicadores

Se medirán:

* Tiempo medio.
* Tiempo máximo.
* Desviación estándar.
* Memoria utilizada.

---

## 354. Objetivo

Comparar el rendimiento entre:

* Resolución dinámica.
* Planes compilados.
* Distintas estrategias.

---

## 355. Testing Strategy

El Result Transformation Engine contará con una estrategia de pruebas específica.

---

## 356. Niveles de prueba

```text
Unit

Integration

Functional

Performance

Concurrency
```

---

## 357. Unit Tests

Cada estrategia tendrá pruebas independientes.

---

## 358. Integration Tests

Validarán el pipeline completo.

---

## 359. Functional Tests

Verificarán la integración con:

* HttpKernel.
* Runtime.
* Render Engine.
* Metadata Engine.

---

## 360. Performance Tests

Compararán:

* Runtime dinámico.
* Runtime compilado.

---

## 361. Concurrency Tests

Especialmente relevantes para FrankenPHP.

---

## 362. Casos

Se validará que:

* No exista estado compartido mutable.
* Los registros congelados sean seguros.
* Los planes compilados sean reutilizables.

---

## 363. Failure Tests

El sistema deberá probar:

* Builders inexistentes.
* Estrategias inválidas.
* Metadata corrupta.
* Planes incompatibles.

---

## 364. Recovery Tests

Verificarán que el motor pueda volver a la resolución dinámica cuando falle un plan compilado.

---

## 365. Compatibilidad

Las pruebas deberán ejecutarse sobre:

* PHP CLI.
* FrankenPHP.
* FPM.
* Entornos de integración continua.

---

## 366. Diagnóstico

El Engine expondrá un modo diagnóstico para desarrolladores.

Permitirá visualizar:

* Registry.
* Planes.
* Estrategias.
* Builders.
* Decoradores.

---

## 367. Health Checks

El sistema podrá ejecutar verificaciones de consistencia antes del arranque de la aplicación.

---

## 368. Validaciones

Ejemplos:

* Estrategias duplicadas.
* Builders faltantes.
* Metadata inválida.
* Planes obsoletos.

---

## 369. Objetivo

Detectar problemas durante el despliegue y no en tiempo de ejecución.

---

## 370. Próxima sección

La **Parte 05C** cerrará el documento con:

* Estructura completa de directorios.
* Organización de namespaces.
* Configuración.
* Integración con el Container.
* ADRs.
* Roadmap V1/V2/V3.
* Recomendaciones de implementación.
* Conclusiones finales.

## Part 05C

> Continuación de **09_RESULT_TRANSFORMATION_ENGINE_PART_05B.md**

---

## 371. Organización del módulo

El **Result Transformation Engine** se organizará siguiendo la arquitectura modular de VoltStack.

Cada responsabilidad deberá vivir en un namespace independiente, evitando clases monolíticas y dependencias circulares.

---

## 372. Objetivos de la estructura

La organización deberá:

* Facilitar la navegación del código.
* Favorecer la compilación independiente.
* Reducir el acoplamiento.
* Permitir reemplazos parciales.
* Facilitar la incorporación de nuevos tipos de resultado.

---

## 373. Estructura de directorios

```text
Quantum/
└── Http/
    └── Transformation/
        ├── Contracts/
        ├── Engine/
        ├── Inspector/
        ├── Classification/
        ├── Definitions/
        ├── Context/
        ├── Strategies/
        │   ├── Basic/
        │   ├── Api/
        │   ├── Volt/
        │   ├── Streaming/
        │   ├── Files/
        │   └── Async/
        ├── Registry/
        ├── Planning/
        ├── Compiler/
        ├── Cache/
        ├── Builders/
        ├── Decorators/
        ├── Negotiation/
        ├── Metadata/
        ├── Events/
        ├── Metrics/
        ├── Diagnostics/
        ├── Benchmark/
        ├── Exceptions/
        ├── Support/
        ├── Testing/
        └── Providers/
```

---

## 374. Contracts

Contendrá únicamente interfaces públicas del módulo.

Ejemplos:

* ResultTransformationEngineInterface
* ResultInspectorInterface
* TransformationStrategyInterface
* ResponseBuilderInterface
* ResponseDecoratorInterface
* StrategyResolverInterface

---

## 375. Engine

Implementación del orquestador principal.

Responsable exclusivamente del pipeline de transformación.

---

## 376. Inspector

Contendrá:

* ResultInspector
* ResultClassifier
* ResultTypeResolver

---

## 377. Definitions

Modelos inmutables del dominio.

Ejemplos:

* ResultDefinition
* TransformationResult
* TransformationPlan
* ResponseMetadata

---

## 378. Context

Agrupará los objetos de contexto.

* TransformationContext
* TelemetryContext
* NegotiationContext

---

## 379. Strategies

Las estrategias se organizarán por dominio.

Ejemplo:

```text
Strategies/
    Basic/
    Api/
    Volt/
    Streaming/
    Files/
    Async/
```

Esto evita un único directorio con decenas de clases.

---

## 380. Registry

Contendrá:

* StrategyRegistry
* BuilderRegistry
* DecoratorRegistry

Todos implementarán el mismo patrón de registro utilizado en otros módulos de VoltStack.

---

## 381. Planning

Responsable de:

* TransformationPlan
* PlanResolver
* PlanFactory
* PlanValidator

---

## 382. Compiler

Contendrá exclusivamente la lógica de compilación.

Nunca será utilizada directamente por el runtime de producción.

---

## 383. Cache

Implementará los distintos niveles de caché definidos anteriormente.

---

## 384. Builders

Contendrá todos los Response Builders oficiales.

---

## 385. Decorators

Contendrá todos los Response Decorators oficiales.

---

## 386. Negotiation

Motor especializado en negociación de contenido.

Responsable de:

* Accept
* Encoding
* Language
* Media Types

---

## 387. Metadata

Adaptadores hacia el Metadata Engine.

Nunca contendrá lógica de reflexión.

---

## 388. Events

Definición de eventos emitidos por el módulo.

---

## 389. Metrics

Implementaciones de métricas internas.

---

## 390. Diagnostics

Herramientas de depuración y diagnóstico.

---

## 391. Benchmark

Casos oficiales de medición de rendimiento.

---

## 392. Exceptions

Excepciones específicas del módulo.

Ejemplos:

* StrategyNotFoundException
* BuilderNotFoundException
* InvalidTransformationPlanException
* UnsupportedResultException

---

## 393. Providers

Registro de servicios del contenedor.

---

## 394. Configuración

El módulo expondrá una configuración dedicada.

Ejemplo:

```php
return [

    'compiled' => true,

    'cache' => true,

    'content_negotiation' => true,

    'telemetry' => true,

    'benchmark' => false,

    'fallback_dynamic' => true,

];
```

---

## 395. Integración con el Container

Todos los servicios serán registrados mediante el Container de VoltStack.

El Engine nunca instanciará dependencias manualmente.

---

## 396. Service Registration

Durante el bootstrap se registrarán:

* Engine.
* Registry.
* Builders.
* Decorators.
* Negotiation Engine.
* Compiler.
* Cache.

---

## 397. Boot Sequence

```text
Framework Boot

↓

Metadata Engine

↓

Transformation Registry

↓

Builders

↓

Decorators

↓

Negotiation

↓

Compiler

↓

Ready
```

---

## 398. Integración con HttpKernel

El `HttpKernel` interactuará con un único punto de entrada:

```php
ResultTransformationEngineInterface
```

Nunca conocerá estrategias ni builders concretos.

---

## 399. Integración con ControllerInvoker

El `ControllerInvoker` devolverá un resultado bruto (`mixed`).

La transformación será responsabilidad exclusiva del Result Transformation Engine.

---

## 400. Integración con Render Engine

Las estrategias relacionadas con vistas delegarán el renderizado al Render Engine.

Esta separación evita duplicar responsabilidades.

---

## 401. Integración con Hydration Engine

Las estrategias SPA delegarán la hidratación al Hydration Engine.

---

## 402. Integración con Runtime SPA

La construcción del Volt Protocol permanecerá centralizada en el Runtime.

El Result Transformation Engine únicamente coordinará el flujo.

---

## 403. Compatibilidad con paquetes

Los paquetes podrán registrar:

* Nuevas estrategias.
* Builders personalizados.
* Decoradores.
* Políticas de negociación.

Sin modificar el núcleo del framework.

---

## 404. Extensibilidad

Toda la infraestructura deberá permanecer abierta para:

* GraphQL.
* gRPC.
* WebSockets.
* Protocolos propietarios.
* Renderizadores alternativos.

---

## 405. ADR-001

**El Engine nunca transforma directamente.**

Toda transformación pertenece a estrategias especializadas.

---

## 406. ADR-002

**Los Builders nunca inspeccionan resultados originales.**

Solo consumen `TransformationResult`.

---

## 407. ADR-003

**Las estrategias son stateless.**

Esto garantiza compatibilidad con FrankenPHP y procesos persistentes.

---

## 408. ADR-004

**Toda metadata proviene del Metadata Engine.**

Se prohíbe la lectura directa de atributos PHP durante la transformación.

---

## 409. ADR-005

**La compilación es una optimización, no un requisito funcional.**

El motor siempre podrá recurrir a la resolución dinámica.

---

## 410. ADR-006

**La observabilidad es transversal.**

Nunca deberá mezclarse con la lógica de transformación.

---

## 411. ADR-007

**El Engine debe ser agnóstico del protocolo.**

Las respuestas HTTP, SPA o futuras variantes serán responsabilidad de Builders especializados.

---

## 412. Roadmap V1

La primera versión incluirá:

* Pipeline completo.
* Registry.
* Strategies básicas.
* Builders HTTP.
* JSON.
* Redirect.
* Streams.
* Files.
* Content Negotiation.
* Compiler.
* Cache.
* Observabilidad.
* Integración con Volt Runtime.

---

## 413. Roadmap V2

Se añadirán:

* GraphQL.
* gRPC.
* WebSockets.
* Compilación incremental.
* Streaming avanzado.
* Serialización distribuida.

---

## 414. Roadmap V3

Se contemplan:

* Optimización asistida por IA.
* Generación automática de planes.
* Negotiation adaptativa.
* Serialización predictiva.
* Compilación distribuida.

---

## 415. Beneficios del diseño

Esta arquitectura proporciona:

* Alta cohesión.
* Bajo acoplamiento.
* Extensibilidad.
* Seguridad para Workers persistentes.
* Compatibilidad con OPcache.
* Integración natural con FrankenPHP.
* Excelente capacidad de prueba.
* Observabilidad completa.

---

## 416. Relación con otros motores

El Result Transformation Engine se convierte en el punto de unión entre:

```text
Controller Invoker
        │
Metadata Engine
        │
Render Engine
        │
Hydration Engine
        │
SPA Runtime
        │
HttpKernel
```

Con ello se mantiene una clara separación de responsabilidades entre todos los motores del framework.

---

## 417. Estado del documento

Con esta parte se considera finalizada la especificación funcional de **09_RESULT_TRANSFORMATION_ENGINE.md** para la versión 1 de VoltStack.

Las futuras versiones ampliarán capacidades sin alterar la arquitectura base aquí definida.

---

## 418. Próximo documento recomendado

Una vez concluido el Result Transformation Engine, el siguiente paso natural dentro del pipeline de ejecución es definir el sistema encargado de convertir la respuesta construida en la salida final del framework.

Se recomienda continuar con:

```text
10_HTTP_RESPONSE_SYSTEM.md
```

Este documento abarcará:

* Response Interfaces.
* ResponseFactory.
* HttpResponse.
* JsonResponse.
* StreamResponse.
* BinaryResponse.
* RedirectResponse.
* Cookie System.
* Header System.
* Content Negotiation final.
* ResponseEmitter.
* HTTP Cache.
* ETags.
* Conditional Requests.
* Compression.
* CORS.
* Security Headers.
* HTTP/2 y HTTP/3.
* Integración con FrankenPHP.
* Response Pipeline.
* Observabilidad.
* Testing.
* ADRs.
