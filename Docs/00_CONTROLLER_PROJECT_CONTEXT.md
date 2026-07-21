# VoltStack Controller System


### Project Context

**Versión del documento:** 1.0  
**Estado:** Draft  
**Módulo:** Quantum\Controllers  
**Autor:** VoltStack Team

---

## 1. Introducción

El sistema de controladores de VoltStack constituye la capa responsable de orquestar la ejecución de la lógica de aplicación entre el sistema de enrutamiento (Routing), el Http Kernel, el Contenedor de Dependencias (IoC Container), el Runtime SPA y el sistema de respuestas del framework.

A diferencia de los frameworks tradicionales, donde un controlador únicamente representa una clase que recibe una petición HTTP y devuelve una respuesta, VoltStack redefine este concepto para convertirlo en un punto central de coordinación capaz de interactuar de forma transparente con aplicaciones SSR, SPA reactivas, APIs, Componentes, Actions y futuros sistemas distribuidos.

El objetivo es ofrecer la simplicidad de desarrollo característica de Laravel, combinada con la arquitectura desacoplada y altamente extensible de Symfony, permitiendo además una integración nativa con el Runtime Reactivo de VoltStack.

---

## 2. Filosofía

El sistema de controladores se diseña bajo cinco principios fundamentales.

### Developer Experience First

El desarrollador debe escribir la menor cantidad de código posible.

Ejemplo:

```php
class UserController extends Controller
{
    public function index(UserRepository $users)
    {
        return view('users.index', [
            'users' => $users->all()
        ]);
    }
}
```

Toda la resolución de dependencias deberá ocurrir automáticamente.

---

### Convention over Configuration

El framework deberá inferir el mayor número posible de comportamientos.

Por ejemplo:

- Resolver parámetros automáticamente.
- Resolver modelos automáticamente.
- Resolver DTOs automáticamente.
- Resolver Enums automáticamente.
- Resolver usuarios autenticados automáticamente.
- Resolver Tenant automáticamente.

---

### Arquitectura desacoplada

Ningún componente del sistema deberá depender directamente de otro.

Por ejemplo:

```
Controller

NO conoce

↓

Router
Request
Response
Container
View Engine
Volt Runtime
SPA Runtime
```

Toda comunicación se realizará mediante contratos (Contracts) y servicios del Container.

---

### Extensibilidad

Cada parte del sistema deberá poder reemplazarse completamente.

Ejemplo:

```
Controller Resolver

↓

Puede sustituirse

↓

Custom Resolver
```

Lo mismo aplica para:

- Dispatcher
- Parameter Resolver
- Response Factory
- Authorization
- Validation
- Metadata
- Lifecycle

---

### Alto rendimiento

El sistema deberá minimizar Reflection durante Runtime.

Siempre que sea posible deberá utilizar:

- Metadata compilada
- Caché
- Preloading
- Opcache
- Lazy Loading
- Compiled Controllers

---

## 3. Objetivos

El sistema busca proporcionar:

- Controladores tradicionales.
- Controladores invocables.
- Action Controllers.
- API Controllers.
- Resource Controllers.
- Page Controllers.
- Component Controllers.
- SPA Controllers.
- Streaming Controllers.
- Async Controllers (futuro).

Todo utilizando una única arquitectura.

---

## 4. Alcance

El módulo Controller será responsable de:

- Resolver controladores.
- Crear instancias.
- Resolver parámetros.
- Ejecutar middleware del controlador.
- Ejecutar autorización.
- Ejecutar validaciones.
- Crear respuestas.
- Publicar eventos.
- Gestionar el ciclo de vida del controlador.

No será responsable de:

- Resolver rutas.
- Renderizar vistas.
- Ejecutar middleware global.
- Manejar sesiones.
- Manejar autenticación.

Estas responsabilidades pertenecen a otros módulos del framework.

---

## 5. Integración con el Framework

El sistema Controller interactúa con prácticamente todos los subsistemas de VoltStack.

```
Http Request
      │
      ▼
Routing
      │
      ▼
Route Match
      │
      ▼
Middleware Pipeline
      │
      ▼
Controller Resolver
      │
      ▼
Parameter Resolver
      │
      ▼
Controller Dispatcher
      │
      ▼
Controller
      │
      ▼
Response Factory
      │
      ▼
Http Response
```

---

## 6. Integración con Quantum Modules

El sistema Controller deberá integrarse de forma nativa con:

### Routing

- Route Metadata
- Route Parameters
- Route Attributes
- Named Routes

---

### Container

Resolución automática de:

- Servicios
- Interfaces
- Repositorios
- DTO
- Eventos
- Actions

---

### Http

Integración con:

- Request
- Response
- Uploaded Files
- Cookies
- Headers
- Streams

---

### Validation

Integración automática con:

- Form Request
- DTO Validation
- Validation Attributes

---

### Authorization

Integración con:

- Policies
- Gates
- Permissions
- Roles

---

### Authentication

Resolución automática de:

```php
User $user
```

---

### Views

Soporte para:

```
Volt Views

SSR

SPA

Components

Fragments
```

---

### Events

Disparo automático de eventos durante el ciclo de vida.

---

### Cache

Metadata Cache

Reflection Cache

Resolver Cache

Controller Cache

---

### Logging

Registro automático de:

- Tiempo de ejecución
- Errores
- Excepciones
- Performance

---

## 7. Tipos de Controladores

VoltStack soportará múltiples especializaciones.

### Standard Controller

```php
class UserController
{
}
```

---

### Invokable Controller

```php
class DashboardController
{
    public function __invoke()
    {
    }
}
```

---

### Resource Controller

```php
class UserController
{
    public function index(){}

    public function store(){}

    public function show(){}

    public function update(){}

    public function destroy(){}
}
```

---

### Action Controller

```php
class CreateUserAction
{
    public function __invoke()
    {
    }
}
```

---

### API Controller

Especializado en respuestas JSON.

---

### Page Controller

Especializado para Volt Runtime.

---

### Component Controller

Especializado para renderizar componentes.

---

### SPA Controller

Especializado para navegación reactiva.

---

### Stream Controller

Especializado para Streaming HTTP.

---

## 8. Principios de Diseño

Todos los controladores deberán cumplir los siguientes principios.

### Stateless

No deberán mantener estado interno.

---

### Request Scoped

Cada petición genera una instancia nueva.

---

### Dependency Injection

Toda dependencia será inyectada automáticamente.

---

### Testable

No deberán depender de estado global.

---

### Replaceable

Todo controlador puede ser sustituido mediante contratos.

---

## 9. Integración con el Runtime SPA

El Controller System será completamente consciente del Runtime Reactivo.

Un controlador podrá devolver indistintamente:

```php
return view(...);

return component(...);

return volt(...);

return json(...);

return redirect(...);

return stream(...);

return spa(...);
```

El Dispatcher seleccionará automáticamente el tipo de respuesta apropiado.

---

## 10. Compatibilidad

El sistema será compatible con:

- SSR
- SPA
- REST
- GraphQL
- RPC
- WebSockets (futuro)
- SSE
- Streaming
- Server Actions

---

## 11. Objetivos de Rendimiento

El sistema deberá minimizar:

- Reflection
- Instanciación
- Resolución de atributos
- Parsing de Metadata

Mediante:

- Metadata compilada
- Caché
- Lazy Loading
- Opcache
- Preloading
- Compiled Metadata

---

## 12. Extensibilidad

Todo el sistema será extensible mediante contratos.

Los desarrolladores podrán reemplazar:

- Controller Resolver
- Dispatcher
- Parameter Resolver
- Response Factory
- Lifecycle
- Metadata Reader
- Authorization Resolver
- Validation Resolver

Sin modificar el núcleo del framework.

---

## 13. Relación con otros módulos

El sistema Controller depende de:

- Http
- Routing
- Container
- Middleware
- Events
- Validation
- Authorization
- Authentication
- Views
- Runtime
- SPA Runtime

Y será utilizado por:

- Http Kernel
- Console
- Queue Workers
- Scheduler
- Testing Framework

---

## 14. Roadmap

### V1

- Standard Controllers
- Invokable Controllers
- Resource Controllers
- Dispatcher
- Resolver
- Base Controller

---

### V2

- Attributes
- Action Controllers
- Parameter Resolver avanzado
- Metadata Cache
- Controller Events

---

### V3

- Page Controllers
- Component Controllers
- SPA Controllers
- API Controllers

---

### V4

- Async Controllers
- Fiber Controllers
- Streaming avanzado
- Compiled Controllers
- Server Actions

---

## 15. Conclusión

El sistema de controladores de VoltStack no será únicamente un mecanismo para ejecutar métodos asociados a una ruta, sino una capa de orquestación desacoplada y altamente extensible que actuará como puente entre el Router, el Kernel, el Contenedor de Dependencias, el Runtime SPA y el sistema de respuestas del framework.

Su diseño combina la experiencia de desarrollo de Laravel con la arquitectura modular de Symfony, incorporando desde su concepción soporte nativo para aplicaciones SSR, SPA reactivas, APIs, componentes y futuras capacidades distribuidas. Esto permitirá que VoltStack ofrezca un modelo de desarrollo coherente, moderno y preparado para evolucionar sin comprometer el rendimiento ni la mantenibilidad.
