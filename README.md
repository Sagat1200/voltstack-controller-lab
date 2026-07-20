# VoltStack Controller Lab

Paquete de laboratorio para definir y evolucionar la arquitectura del subsistema de controladores de VoltStack.

## Estado Actual

Este repositorio se encuentra orientado principalmente a documentacion y diseno arquitectonico.

Hoy su valor principal esta en `Docs/`, donde se describe la vision, contratos, pipeline, seguridad, testing y compilacion del sistema de controladores.

El codigo visible del paquete todavia no refleja todo el alcance descrito en la documentacion, por lo que este repositorio debe entenderse como un blueprint tecnico en evolucion y no como un paquete funcional completo.

## Objetivo Del Repositorio

Este laboratorio busca definir una arquitectura de controladores para VoltStack que sea:

- simple de usar desde la capa publica
- desacoplada internamente
- extensible por contratos
- compatible con SSR, SPA, API y futuros transportes
- segura para procesos persistentes y workers
- optimizable mediante metadata, cache y compilacion

## Que Hay En Este Repositorio

- `Docs/`: especificacion funcional y arquitectonica del subsistema Controllers
- `src/`: espacio del paquete PHP para la implementacion progresiva
- `composer.json`: definicion del paquete `voltstack/controller-lab`

## Punto De Entrada Recomendado

Si es tu primera vez en este repositorio, sigue este orden:

1. `Docs/00_CONTROLLER_PROJECT_CONTEXT.md`
2. `Docs/01_CONTROLLER_ARCHITECTURE.md`
3. `Docs/03_CONTROLLER_DISPATCHER.md`
4. `Docs/04_CONTROLLER_RESOLVER.md`
5. `Docs/05_PARAMETER_RESOLUTION_ENGINE.md`
6. `Docs/08_CONTROLLER_INVOKER.md`
7. `Docs/09_RESULT_TRANSFORMATION_ENGINE.md`
8. `Docs/14_CONTROLLER_TESTING_ARCHITECTURE.md`
9. `Docs/15_CONTROLLER_SECURITY_MODEL.md`

## Mapa De Documentos

### Base

- `00_CONTROLLER_PROJECT_CONTEXT.md`: contexto, filosofia, alcance y roadmap general
- `01_CONTROLLER_ARCHITECTURE.md`: arquitectura general, componentes, fases y criterios de aceptacion
- `02_CONTROLLER_BASE_CLASS.md`: base conceptual de la clase controlador

### Pipeline Core

- `03_CONTROLLER_DISPATCHER.md`: orquestacion de la ejecucion del controlador
- `04_CONTROLLER_RESOLVER.md`: resolucion de definiciones y controladores ejecutables
- `05_PARAMETER_RESOLUTION_ENGINE.md`: resolucion de argumentos y parametros
- `06_METADATA_ENGINE.md`: modelo y resolucion de metadata transversal
- `07_CONTROLLER_INTERCEPTOR_SYSTEM.md`: interceptores alrededor de la invocacion
- `08_CONTROLLER_INVOKER.md`: invocacion efectiva del controlador
- `09_RESULT_TRANSFORMATION_ENGINE.md`: transformacion del resultado en respuestas consumibles

### Capacidades Transversales

- `10_CONTROLLER_LIFECYCLE_AND_EXECUTION_STATE.md`: ciclo de vida y estado de ejecucion
- `11_CONTROLLER_EVENTS_AND_OBSERVABILITY.md`: eventos, telemetria y observabilidad
- `13_CONTROLLER_COMPILATION_FRAMEWORK.md`: compilacion de planes, metadata y artefactos
- `14_CONTROLLER_TESTING_ARCHITECTURE.md`: estrategia de pruebas del subsistema
- `15_CONTROLLER_SECURITY_MODEL.md`: modelo de seguridad integral

## Estado Documental

La mayoria de documentos estan marcados como `Draft` o `Draft arquitectonico`.

Esto implica que:

- las decisiones pueden refinarse
- puede existir desfase entre documentacion e implementacion
- el repositorio necesita consolidar primero contratos y limites antes de cerrar codigo productivo

## Convenciones Del Paquete

- Runtime objetivo: `PHP ^8.3`
- Dependencia principal: `voltstack/framework`
- Namespace base: `VoltStack\ControllerLab\`

El `composer.json` usa un repositorio local por ruta hacia `../voltstack-framework`, lo que indica que este paquete esta pensado para desarrollarse junto al framework principal.

## Expectativas De Implementacion

Antes de ampliar funcionalidad, conviene alinear el trabajo con estas prioridades:

1. consolidar contratos y limites del subsistema
2. aterrizar una implementacion minima del pipeline core
3. validar equivalencia entre modo dinamico y modo compilado
4. blindar testing, observabilidad y seguridad

## Vacios Actuales A Tener En Cuenta

- el `README` original estaba vacio
- la documentacion es extensa pero no existia una ruta de lectura desde la raiz
- la implementacion visible aun es minima respecto al alcance descrito
- la serie documental actual pasa de `11` a `13`, por lo que conviene revisar numeracion y particionado en futuras iteraciones

## Uso Local

Este repositorio no parece estar preparado todavia como paquete autocontenido para demostrar el subsistema completo de forma aislada.

Si vas a trabajar en el:

1. mantenlo sincronizado con `voltstack/framework`
2. usa `Docs/` como fuente de verdad arquitectonica
3. implementa en pasos pequenos y verificables
4. acompana cada avance importante con pruebas y actualizacion documental

## Siguiente Paso Sugerido

Para ordenar mejor el repositorio, el siguiente bloque recomendable es:

- completar una implementacion minima navegable del pipeline core
- agregar un roadmap ejecutivo de implementacion
- enlazar cada documento con su estado real de avance en codigo
