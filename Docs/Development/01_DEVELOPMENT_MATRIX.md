# Volt Controllers - Matriz Ejecutiva (Desarrollo)

## Objetivo

Este documento es el mapa ejecutivo de implementacion del **Controllers Engine**.

Se usa para:

- ubicar rapidamente los archivos relevantes
- entender responsabilidades y dependencias
- marcar progreso (pendiente / en progreso / completado)
- mantener una lista corta de riesgos y pruebas asociadas

Alcance de esta matriz:

- implementacion en `vendor/voltstack/framework`
- ruta de integracion por `Quantum\Routing\Dispatching\ControllerDispatcher`
- excluye temporalmente `Docs/Controller Security Model`

## Convencion De Estado

- `[ ]` pendiente
- `[-]` en progreso
- `[x]` completado
- `[!]` riesgo o pendiente critico

## Matriz (Framework)

| Archivo | Rol | Estado | Notas / Dependencias |
|---|---|---:|---|
| [ControllerDispatcher.php](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/framework/src/Quantum/Routing/Dispatching/ControllerDispatcher.php) | Punto de entrada de dispatch de controllers (Router → DispatcherResolver → ControllerDispatcher) | `[x]` | Delegacion a `Quantum\Controllers\ControllerEngine` |
| [RouteArgumentResolver.php](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/framework/src/Quantum/Routing/Dispatching/RouteArgumentResolver.php) | Resolucion de argumentos por reflection + container + route binding | `[x]` | Se reusa como backend del MVP (sin cambios de reglas) |
| [MissingRouteHandler.php](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/framework/src/Quantum/Routing/Dispatching/MissingRouteHandler.php) | Manejo de binding faltante segun metadata `missing` | `[x]` | Se mantiene como comportamiento contractual |
| [ResponseNormalizer.php](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/framework/src/Quantum/Routing/Dispatching/ResponseNormalizer.php) | Normaliza `mixed` a `Response` | `[x]` | En MVP se reusa para normalizacion de controllers |
| [HttpKernel.php](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/framework/src/Quantum/HttpKernel/HttpKernel.php) | Orquestacion HTTP + middleware + normalizacion global | `[x]` | Debe seguir siendo compatible si ControllerDispatcher empieza a devolver `Response` |
| [DispatcherResolver.php](file:///c:/W4/Packages/VoltStack/app-skeleton/vendor/voltstack/framework/src/Quantum/Routing/Dispatching/DispatcherResolver.php) | Seleccion de dispatcher por tipo de action | `[x]` | No cambia para MVP |

## Matriz (Controllers Engine - MVP-1)

| Archivo | Rol | Estado | Notas / Dependencias |
|---|---|---:|---|
| `src/Quantum/Controllers/ControllerEngine.php` | Orquestador MVP: resolve → args → invoke → normalize | `[x]` | Depende de resolver/argument resolver/invoker/normalizer |
| `src/Quantum/Controllers/ControllerDefinition.php` | Representa la definicion del controller (action) | `[x]` | Se construye desde `RouteMatch->route()->action()` |
| `src/Quantum/Controllers/ControllerContext.php` | Contexto de resolucion (app + request + routeMatch + metadata) | `[x]` | Base para evolucion a lifecycle |
| `src/Quantum/Controllers/ResolvedController.php` | Resultado del resolver: `instance + method` | `[x]` | Se usa para invocacion |
| `src/Quantum/Controllers/ControllerResolver.php` | Resuelve y valida target (`invokable`, `Class@method`, `[Class,method]`) | `[x]` | Debe mantener compatibilidad con reglas actuales |
| `src/Quantum/Controllers/ControllerExecutionContext.php` | Contexto request-scoped para inyeccion/release (worker-safe) | `[x]` | No debe retener referencias globales ni estaticas |
| `src/Quantum/Controllers/ControllerInvoker.php` | Invoca controller y garantiza release en `finally` | `[x]` | No transforma resultado a Response (eso es normalizer) |
| `src/Quantum/Controllers/ControllerContextInjector.php` | Inyecta/libera contexto en controllers aware | `[x]` | Implementa el contrato de base class opcional |
| `src/Quantum/Controllers/Contracts/ControllerExecutionContextAwareInterface.php` | Contrato para controllers que reciben contexto | `[x]` | `set...` y `release...` |

## Matriz (Tests)

| Archivo | Rol | Estado | Notas / Dependencias |
|---|---|---:|---|
| `vendor/voltstack/framework/tests/Unit/*` | Unit tests del engine y compatibilidad de dispatch | `[x]` | Cubrir invocable, class@method, array callable, release, normalizacion |

## Riesgos (Corte MVP-1)

- `[!]` compatibilidad: no romper dispatch actual (rutas invocables existentes)
- `[!]` worker-safety: asegurar `release()` aun cuando el controller falle
- `[!]` consistencia: no duplicar side effects al normalizar (evitar doble render de views/components)

## Evidencia / Pruebas Esperadas

- Ejecutar `phpunit` (suite `framework`) y mantener verde.
