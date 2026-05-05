# Contexto del Proyecto para Frontend React + Vite

## 1. Objetivo de este documento

Este documento esta pensado para entregarse al equipo o repositorio donde se desarrollara el nuevo frontend en React + Vite para SIPRE.

Su objetivo es explicar el contexto funcional del sistema, los modulos que debe tener, la logica general que debe soportar, el tipo de datos que consume y las consideraciones clave para integrarse con el backend en Laravel API.

Este documento no define diseño visual. No entra en colores, estilos, branding ni layout estetico. Solo define contexto funcional, estructura esperada y necesidades del frontend.

## 2. Que es SIPRE

SIPRE es un sistema administrativo y tecnico legacy actualmente implementado sobre CodeIgniter 3 y PostgreSQL.

El sistema trabaja principalmente sobre estos dominios:

- autenticacion de usuarios
- administracion de usuarios
- roles y permisos
- catalogos y parametros maestros
- insumos
- items
- proyectos
- reportes
- auditoria

En el sistema actual gran parte de la logica esta distribuida en controladores PHP grandes, con vistas server-rendered. En la nueva version, el frontend sera una aplicacion independiente en React + Vite que consumira un backend Laravel API.

## 3. Objetivo del nuevo frontend

El frontend debe convertirse en la interfaz principal del sistema y reemplazar la experiencia legacy basada en vistas PHP.

Debe permitir al usuario:

- autenticarse
- navegar por modulos segun permisos
- listar, crear, editar y consultar informacion
- ejecutar procesos funcionales clave
- trabajar con formularios complejos
- revisar estados, historicos y relaciones entre entidades
- consumir reportes y exportaciones

## 4. Enfoque general del frontend

El frontend debe pensarse como una SPA administrativa moderna con React + Vite.

Debe priorizar:

- claridad funcional
- mantenibilidad
- modularidad
- integracion limpia con APIs
- manejo consistente de permisos
- formularios robustos
- tablas con filtros y paginacion
- experiencia estable para usuarios administrativos

## 5. Perfil de usuario y comportamiento esperado

Los usuarios del sistema no son consumidores publicos, sino operadores internos o institucionales.

Por eso el frontend debe estar preparado para:

- usuarios que trabajan muchas horas dentro del sistema
- flujos repetitivos de carga y consulta
- formularios largos o detallados
- filtros y busquedas frecuentes
- diferentes niveles de permiso segun rol

## 6. Modulos que tendra el frontend

## 6.1. Autenticacion

### Debe incluir

- login
- cierre de sesion
- carga de perfil autenticado
- cambio de contrasena
- manejo de sesion expirada

### Comportamientos esperados

- proteger rutas privadas
- redirigir si no existe sesion valida
- cargar permisos del usuario autenticado desde el backend

## 6.2. Dashboard o inicio

### Debe incluir

- pantalla inicial despues del login
- accesos rapidos a modulos
- resumen basico segun permisos

### Objetivo

No necesita ser analitico al inicio. Su funcion principal es servir como punto de entrada del sistema y acceso operativo.

## 6.3. Administracion

### Submodulos

- usuarios
- roles
- permisos por rol
- funciones del sistema
- unidades o departamentos
- autorizaciones especiales

### Necesidades funcionales

- tablas de listado
- filtros
- formularios de alta y edicion
- cambio de estado
- asignacion de permisos
- visualizacion clara de relaciones entre rol y funcion

## 6.4. Permisos por rol

Este es un modulo especialmente importante para la nueva version.

### El frontend debe permitir

- ver todos los roles
- ver todas las funciones o acciones del sistema
- visualizar una matriz rol-funcion
- asignar permisos
- quitar permisos
- sincronizar permisos
- clonar permisos entre roles

### Recomendacion funcional

La UX funcional deberia apoyarse en una matriz o tabla de permisos donde sea facil identificar:

- modulo
- accion
- rol
- estado del permiso

## 6.5. Catalogos y parametros

### Submodulos

- tipos de insumo
- unidades de medida
- grupos
- subgrupos
- porcentajes de calculo
- variantes de porcentajes por categoria o programa

### El frontend debe permitir

- listar
- crear
- editar
- activar o desactivar
- seleccionar catalogos desde formularios de otros modulos

## 6.6. Insumos

### Debe incluir

- listado de insumos
- filtros
- alta y edicion de insumos
- cambio de estado
- detalle de insumo
- historico
- cotizaciones
- logs o trazabilidad operativa

### Datos relacionados

- tipo de insumo
- unidad de medida
- estado
- fecha de cotizacion
- historicos
- cotizaciones

## 6.7. Solicitudes de insumo

### Debe incluir

- listado de solicitudes
- alta de solicitud
- edicion
- cambio de estado
- revision
- aceptacion o rechazo
- reversion si aplica

### El frontend debe considerar

- multiples estados de negocio
- mensajes claros de transicion
- relacion entre solicitud e insumo

## 6.8. Items

### Debe incluir

- listado de items
- alta y edicion
- detalle del item
- relacion item-insumo
- visualizacion de composicion
- carga y consulta de archivos asociados
- variantes por tipo de calculo

### Procesos asociados

- asociar insumos al item
- editar cantidades o composicion
- disparar recalculos
- consultar soporte tecnico o PDF asociado

## 6.9. Recalculos de items

El sistema actual tiene procesos especiales de recalculo en items.

### El frontend debe estar preparado para

- ejecutar acciones de recalculo
- mostrar resultado o confirmacion
- refrescar el detalle del item despues del recalculo
- distinguir variantes como:
  - general
  - upre
  - fps
  - fndr
  - obras

## 6.10. Proyectos

### Debe incluir

- listado de proyectos
- alta y edicion
- detalle del proyecto
- asignacion de items al proyecto
- gestion de responsable y solicitante
- estados y condicion del proyecto

### El frontend debe poder mostrar

- resumen del proyecto
- items asociados
- informacion calculada
- vistas de desglose y resumen

## 6.11. Proyecto-Item

### Debe incluir

- agregar item a proyecto
- editar relacion o cantidades si aplica
- quitar item del proyecto
- consultar detalle del item dentro del proyecto

## 6.12. Calculos y resumenes de proyecto

### Debe incluir

- resumen de incidencia
- desglose
- calculo o recalculo
- vista de apoyo para precios unitarios

### Observacion

Estos flujos pueden tener mayor complejidad funcional. El frontend debe tratarlos como vistas operativas especializadas, no solo como CRUD comun.

## 6.13. Reportes

### Debe incluir

- acceso a reportes por modulo
- descarga o apertura de PDF
- exportaciones si el backend las provee

### Casos probables

- reportes de insumos
- historico de insumos
- precios unitarios de proyecto
- desgloses
- reportes de item

## 6.14. Perfil

### Debe incluir

- ver datos basicos del usuario autenticado
- cambio de contrasena
- cierre de sesion

## 7. Informacion de permisos que debe manejar el frontend

El frontend no solo debe autenticar. Tambien debe conocer que puede hacer el usuario.

Debe contemplar al menos estos niveles:

- acceso a modulo
- acceso a accion dentro del modulo
- acceso a botones o acciones especificas

### Ejemplos

- ver listado
- crear registro
- editar registro
- cambiar estado
- asignar permisos
- ejecutar recalculo
- descargar reporte

El backend debe proveer informacion suficiente para que el frontend pueda:

- ocultar acciones no permitidas
- bloquear rutas no autorizadas
- mostrar navegacion segun rol

## 8. Comportamientos funcionales comunes que debe soportar el frontend

## 8.1. Listados

La mayoria de modulos necesitara:

- tabla
- paginacion
- busqueda
- filtros
- ordenamiento si aplica
- accion de ver, editar o cambiar estado

## 8.2. Formularios

Los formularios del sistema son una pieza central.

El frontend debe soportar:

- validaciones de cliente
- errores de backend mostrados claramente
- campos dependientes
- combos cargados desde metadata
- formularios de alta y edicion reutilizables

## 8.3. Estados de negocio

El sistema actual usa estados cortos que el frontend debera mapear correctamente.

Ejemplos detectados:

- `AC` = activo
- `DC` = inactivo
- `DP` = descartado o eliminado logico segun contexto
- `PD` = pendiente
- `RV` = revisado
- `AP` = aceptado

El frontend no debe asumir significados arbitrarios; estos codigos deben centralizarse en constantes o catálogos de apoyo.

## 8.4. Auditoria visible

Aunque la escritura de auditoria corresponde al backend, el frontend puede necesitar mostrar:

- historicos
- logs
- acciones realizadas
- nombre del usuario que hizo un cambio

## 8.5. Confirmaciones

Se requeriran confirmaciones para acciones como:

- eliminar logicamente
- desactivar
- revertir
- asignar permisos masivos
- recalcular

## 9. Datos y entidades principales que debe conocer el frontend

El frontend debe trabajar al menos con estas entidades:

- usuario
- rol
- permiso
- funcion
- unidad
- autorizacion
- tipo_insumo
- unidad_medida
- grupo
- sub_grupo
- porcentaje_calculo
- insumo
- cotizacion
- historial_insumo
- solicitud_insumo
- item
- item_insumo
- proyecto
- proyecto_item
- auditoria

## 10. Integracion esperada con el backend Laravel API

El frontend debe consumir una API versionada, idealmente bajo:

```text
/api/v1
```

### El frontend debe esperar del backend

- respuestas consistentes
- validaciones claras
- endpoints paginados
- metadata para formularios
- permisos del usuario autenticado
- endpoints separados por modulo

### El frontend no debe asumir

- nombres de campos ambiguos sin contrato
- estructuras cambiantes por endpoint
- errores no estandarizados

## 11. Estructura sugerida del frontend

No es una regla obligatoria, pero el proyecto React + Vite deberia organizarse de forma modular.

### Dominios sugeridos

- `auth`
- `dashboard`
- `administracion`
- `catalogos`
- `insumos`
- `items`
- `proyectos`
- `reportes`
- `perfil`
- `shared`

### Capas sugeridas

- pages
- components
- services o api clients
- hooks
- routes
- guards o wrappers de autorizacion
- schemas o validators
- constants
- utils

## 12. Necesidades tecnicas del frontend

Sin entrar en diseño, el frontend deberia contemplar estas necesidades tecnicas:

- manejo centralizado de autenticacion
- manejo centralizado de permisos
- cliente HTTP comun
- manejo uniforme de errores
- manejo de loading states
- invalidacion o refresco de datos tras acciones
- soporte para formularios complejos
- soporte para carga y descarga de archivos

## 13. Casos delicados que el frontend debe tratar con cuidado

### Permisos

No basta con ocultar botones. Tambien hay que proteger rutas y manejar respuestas `403` del backend.

### Recalculos

Acciones de recalculo pueden tomar tiempo o afectar mucha informacion. El frontend debe mostrar claramente:

- accion ejecutada
- estado de procesamiento
- resultado o error

### Archivos

La carga de archivos en items debe manejar:

- validacion de archivo
- progreso o estado de carga si aplica
- error de formato o tamaño

### Formularios dependientes

Varios modulos dependen de catalogos previos. El frontend debe poder resolver bien combos dependientes y recarga de metadata.

## 14. Lo que no debe hacer el frontend

- no debe replicar logica compleja de negocio que debe vivir en backend
- no debe asumir permisos que el backend no confirme
- no debe hardcodear demasiados catalogos que provienen de base de datos
- no debe depender de estructuras del sistema legacy renderizado en PHP

## 15. Resultado esperado del nuevo frontend

Cuando este proyecto este bien implementado, el resultado esperado es una SPA administrativa moderna que:

- reemplace la navegacion legacy
- permita operar los modulos principales del sistema
- trabaje sobre permisos reales por rol
- consuma el backend Laravel API de forma consistente
- facilite el mantenimiento futuro

## 16. Resumen final

El nuevo frontend React + Vite debe construirse como la capa operativa principal de SIPRE.

Debe centrarse en:

- modulos funcionales claros
- integracion fuerte con backend API
- manejo riguroso de autenticacion y permisos
- formularios y tablas administrativas
- soporte a procesos de negocio como insumos, items, proyectos y reportes

No es un proyecto de marketing ni una web publica. Es una herramienta de trabajo institucional y debe desarrollarse con esa mentalidad.
