# Backend Laravel para SIPRE

## APIs necesarias y trabajo general a realizar

## 1. Objetivo del documento

Este documento define, a nivel funcional y tecnico, todas las APIs que deberian construirse para replicar SIPRE en una nueva arquitectura con:

- frontend en React
- backend en Laravel orientado a APIs
- base de datos PostgreSQL existente como fuente principal de datos

Ademas, resume lo que en general debe hacerse en el backend para que el nuevo sistema pueda reemplazar al legacy sin perder funcionalidad.

## 2. Principios de diseño del nuevo backend

El backend nuevo no debe ser una simple traduccion linea por linea del sistema actual. Debe conservar la logica de negocio, pero reordenarla con criterios modernos.

### Principios recomendados

- API first
- versionado de endpoints
- validaciones centralizadas
- autorizacion por roles y permisos
- respuestas consistentes
- auditoria transversal
- separacion clara entre controlador, servicio y acceso a datos
- soporte para crecimiento por modulos

## 3. Convenciones sugeridas para la API

### Prefijo general

```text
/api/v1
```

### Formato general de respuesta

#### Respuesta exitosa

```json
{
  "success": true,
  "message": "Operacion realizada correctamente.",
  "data": {}
}
```

#### Respuesta con error de validacion

```json
{
  "success": false,
  "message": "Error de validacion.",
  "errors": {
    "campo": ["Mensaje de error"]
  }
}
```

#### Respuesta paginada

```json
{
  "success": true,
  "data": {
    "items": [],
    "meta": {
      "current_page": 1,
      "per_page": 15,
      "total": 120
    }
  }
}
```

## 4. Modulos backend que deben existir

Se recomienda organizar el backend en estos dominios:

- autenticacion
- perfil
- usuarios
- roles
- permisos
- funciones del sistema
- unidades/departamentos
- catalogos maestros
- insumos
- solicitudes de insumo
- cotizaciones e historicos
- items
- archivos de items
- proyectos
- relaciones proyecto-item
- porcentajes y configuraciones de calculo
- reportes
- auditoria
- utilitarios de busqueda

## 5. APIs necesarias por modulo

## 5.1. Autenticacion

### Objetivo

Resolver login, logout, sesion/token, perfil autenticado y cambio de contrasena.

### Endpoints

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/refresh` si se usa JWT con refresh token
- `POST /api/v1/auth/change-password`

### Reglas importantes

- definir estrategia de migracion desde contraseñas legacy MD5
- no mantener MD5 como esquema definitivo
- registrar auditoria de login/logout si el negocio lo requiere

## 5.2. Perfil de usuario

### Objetivo

Permitir al usuario autenticado consultar y actualizar parte de su informacion operativa.

### Endpoints

- `GET /api/v1/profile`
- `PUT /api/v1/profile`
- `PUT /api/v1/profile/password`

## 5.3. Usuarios

### Objetivo

Administrar usuarios del sistema, su estado, unidad y rol.

### Endpoints

- `GET /api/v1/users`
- `POST /api/v1/users`
- `GET /api/v1/users/{id}`
- `PUT /api/v1/users/{id}`
- `PATCH /api/v1/users/{id}/status`
- `PATCH /api/v1/users/{id}/role`
- `PATCH /api/v1/users/{id}/unit`
- `POST /api/v1/users/{id}/reset-password`

### Filtros sugeridos

- por nombre
- por username
- por estado
- por rol
- por unidad

## 5.4. Roles

### Objetivo

Administrar roles del sistema.

### Endpoints

- `GET /api/v1/roles`
- `POST /api/v1/roles`
- `GET /api/v1/roles/{id}`
- `PUT /api/v1/roles/{id}`
- `PATCH /api/v1/roles/{id}/status`

## 5.5. Permisos por rol

### Objetivo

Implementar el modulo extra solicitado: gestion facil de permisos por roles para asignar o quitar acceso sin tocar codigo.

Este modulo debe ser uno de los mas importantes del backend nuevo.

### Lo que debe permitir

- listar todos los modulos y acciones registradas
- ver permisos actuales por rol
- asignar permisos de forma masiva
- quitar permisos de forma masiva
- clonar permisos de un rol a otro
- consultar matriz rol-funcion

### Endpoints

- `GET /api/v1/roles/{id}/permissions`
- `PUT /api/v1/roles/{id}/permissions`
- `POST /api/v1/roles/{id}/permissions/attach`
- `POST /api/v1/roles/{id}/permissions/detach`
- `POST /api/v1/roles/{id}/permissions/sync`
- `POST /api/v1/roles/{id}/permissions/clone-from/{sourceRoleId}`
- `GET /api/v1/permissions/matrix`

### Recomendacion funcional

La UI React deberia mostrar una matriz con:

- filas = modulos o funciones
- columnas = roles
- checkboxes o toggles por permiso

Para soportar eso, el backend debe exponer una respuesta optimizada para matriz, no solo listas planas.

## 5.6. Funciones del sistema

### Objetivo

Administrar el catalogo de funciones/acciones sobre las que se asignan permisos.

### Endpoints

- `GET /api/v1/functions`
- `POST /api/v1/functions`
- `GET /api/v1/functions/{id}`
- `PUT /api/v1/functions/{id}`
- `PATCH /api/v1/functions/{id}/status`

### Campos esperados

- clase o modulo
- nombre de funcion
- descripcion
- estado

## 5.7. Unidades o departamentos

### Objetivo

Administrar unidades/departamentos vinculados a usuarios.

### Endpoints

- `GET /api/v1/units`
- `POST /api/v1/units`
- `GET /api/v1/units/{id}`
- `PUT /api/v1/units/{id}`
- `PATCH /api/v1/units/{id}/status`

## 5.8. Autorizaciones especiales

### Objetivo

Resolver procesos como autorizaciones de baja o eliminacion, visibles en el sistema actual.

### Endpoints

- `GET /api/v1/authorizations`
- `POST /api/v1/authorizations`
- `GET /api/v1/authorizations/{id}`
- `PATCH /api/v1/authorizations/{id}/approve`
- `PATCH /api/v1/authorizations/{id}/reject`

## 5.9. Tipos de insumo

### Objetivo

Administrar catalogo de tipos de insumo.

### Endpoints

- `GET /api/v1/item-types`
- `POST /api/v1/item-types`
- `GET /api/v1/item-types/{id}`
- `PUT /api/v1/item-types/{id}`
- `PATCH /api/v1/item-types/{id}/status`

## 5.10. Unidades de medida

### Endpoints

- `GET /api/v1/unit-measures`
- `POST /api/v1/unit-measures`
- `GET /api/v1/unit-measures/{id}`
- `PUT /api/v1/unit-measures/{id}`
- `PATCH /api/v1/unit-measures/{id}/status`

## 5.11. Grupos

### Endpoints

- `GET /api/v1/groups`
- `POST /api/v1/groups`
- `GET /api/v1/groups/{id}`
- `PUT /api/v1/groups/{id}`
- `PATCH /api/v1/groups/{id}/status`

## 5.12. Subgrupos

### Objetivo

Replicar completamente la pantalla legacy `parametros/subgrupos` con soporte para administracion, combos dependientes y eliminacion logica con autorizacion.

### Endpoints

- `GET /api/v1/subgroups`
- `POST /api/v1/subgroups`
- `GET /api/v1/subgroups/{id}`
- `PUT /api/v1/subgroups/{id}`
- `DELETE /api/v1/subgroups/{id}`
- `GET /api/v1/subgroups/context`
- `POST /api/v1/subgroups/{id}/delete-authorization-request`
- `GET /api/v1/subgroups/{id}/delete-authorization-status`
- `GET /api/v1/subgroups/by-group/{groupId}`

### Reglas implementadas

- el listado administrativo hace join con `grupo`
- el listado administrativo filtra `sub_grupo.estado != 'DP'`
- el listado administrativo ordena por `sub_grupo.id_subgrupo ASC`
- el listado para combos filtra `id_grupo = ?` y `estado = 'AC'`
- el listado para combos ordena por `descripcion ASC`
- la API devuelve `status_label` con `AC -> ACTIVO` y `DC -> INACTIVO`
- la API devuelve `available_actions` por registro con `edit` y `delete`
- la creacion valida duplicados por `codigo` y `descripcion` ignorando registros `DP`
- la edicion solo revalida duplicados si el valor realmente cambio
- la eliminacion exige que no existan `item` activos vinculados al subgrupo
- la eliminacion exige una autorizacion aprobada en `autorizaciones` con `tabla = 'sub_grupo'`
- crear, editar y eliminar registran auditoria

### Diferencia de uso del endpoint `GET /api/v1/subgroups`

- sin `group_id`: devuelve el listado administrativo de `parametros/subgrupos`
- con `group_id`: devuelve subgrupos activos para combos dependientes

## 5.13. Porcentajes de calculo

### Objetivo

Administrar configuraciones de calculo base y variantes del sistema.

### Submodulos observados

- general
- upre
- fps
- fndr
- obras
- proman

### Endpoints sugeridos

- `GET /api/v1/calculation-percentages`
- `POST /api/v1/calculation-percentages`
- `GET /api/v1/calculation-percentages/{id}`
- `PUT /api/v1/calculation-percentages/{id}`
- `PATCH /api/v1/calculation-percentages/{id}/status`

### Variante por categoria

Se puede resolver con:

- un solo endpoint con filtro `type`

o con rutas mas explicitas:

- `GET /api/v1/calculation-percentages/upre`
- `GET /api/v1/calculation-percentages/fps`
- `GET /api/v1/calculation-percentages/fndr`
- `GET /api/v1/calculation-percentages/obras`
- `GET /api/v1/calculation-percentages/proman`

## 5.14. Insumos

### Objetivo

Administrar insumos, su estado, cotizacion, historico y relaciones de catalogo.

### Endpoints base

- `GET /api/v1/inputs`
- `POST /api/v1/inputs`
- `GET /api/v1/inputs/{id}`
- `PUT /api/v1/inputs/{id}`
- `PATCH /api/v1/inputs/{id}/status`

### Endpoints complementarios

- `GET /api/v1/inputs/{id}/history`
- `GET /api/v1/inputs/{id}/logs`
- `GET /api/v1/inputs/{id}/quotes`
- `POST /api/v1/inputs/{id}/quotes`

### Filtros sugeridos

- descripcion
- tipo
- unidad de medida
- estado
- fecha de cotizacion

## 5.15. Solicitudes de insumo

### Objetivo

Gestionar solicitudes nuevas o modificaciones relacionadas con insumos.

### Endpoints

- `GET /api/v1/input-requests`
- `POST /api/v1/input-requests`
- `GET /api/v1/input-requests/{id}`
- `PUT /api/v1/input-requests/{id}`
- `PATCH /api/v1/input-requests/{id}/status`
- `PATCH /api/v1/input-requests/{id}/revert`
- `PATCH /api/v1/input-requests/{id}/review`

### Estados sugeridos

- pendiente
- revisado
- aceptado
- rechazado
- revertido

## 5.16. Historiales y cotizaciones de insumo

### Endpoints

- `GET /api/v1/input-histories`
- `GET /api/v1/input-histories/{id}`
- `GET /api/v1/input-quotes`
- `POST /api/v1/input-quotes`
- `GET /api/v1/input-quotes/{id}`
- `PUT /api/v1/input-quotes/{id}`

## 5.17. Items

### Objetivo

Administrar items, sus relaciones con insumos, variantes y archivos tecnicos.

### Endpoints base

- `GET /api/v1/items`
- `POST /api/v1/items`
- `GET /api/v1/items/{id}`
- `PUT /api/v1/items/{id}`
- `PATCH /api/v1/items/{id}/status`

### Endpoints complementarios

- `GET /api/v1/items/search`
- `GET /api/v1/items/{id}/inputs`
- `POST /api/v1/items/{id}/inputs`
- `PUT /api/v1/items/{id}/inputs/{itemInputId}`
- `DELETE /api/v1/items/{id}/inputs/{itemInputId}`

### Endpoints por variante de calculo

- `GET /api/v1/items?mode=general`
- `GET /api/v1/items?mode=upre`
- `GET /api/v1/items?mode=fps`
- `GET /api/v1/items?mode=fndr`
- `GET /api/v1/items?mode=obras`

## 5.18. Archivos de items

### Objetivo

Resolver carga y consulta de PDFs o soportes tecnicos asociados a items.

### Endpoints

- `GET /api/v1/items/{id}/files`
- `POST /api/v1/items/{id}/files`
- `DELETE /api/v1/items/{id}/files/{fileId}`
- `GET /api/v1/items/{id}/files/{fileId}/download`

### Recomendaciones tecnicas

- validar mimetype
- validar tamaño
- registrar quien subio el archivo
- guardar metadata separada del archivo fisico

## 5.19. Recalculos de items

### Objetivo

Encapsular formulas hoy dispersas en el controller legacy `Items.php`.

### Endpoints

- `POST /api/v1/items/{id}/recalculate`
- `POST /api/v1/items/{id}/recalculate/upre`
- `POST /api/v1/items/{id}/recalculate/fps`
- `POST /api/v1/items/{id}/recalculate/fndr`
- `POST /api/v1/items/{id}/recalculate/obras`
- `POST /api/v1/items/{id}/recalculate/breakdowns`

### Observacion clave

Este es uno de los puntos mas sensibles del backend. Antes de implementarlo, hay que extraer y documentar bien las formulas actuales.

## 5.20. Proyectos

### Objetivo

Administrar proyectos, responsables, solicitantes, estado y condicion.

### Endpoints base

- `GET /api/v1/projects`
- `POST /api/v1/projects`
- `GET /api/v1/projects/{id}`
- `PUT /api/v1/projects/{id}`
- `PATCH /api/v1/projects/{id}/status`

### Filtros sugeridos

- nombre de proyecto
- responsable
- solicitante
- estado
- condicion
- fecha

## 5.21. Proyecto-Item

### Objetivo

Administrar la asignacion de items a proyectos.

### Endpoints

- `GET /api/v1/projects/{id}/items`
- `POST /api/v1/projects/{id}/items`
- `PUT /api/v1/projects/{id}/items/{projectItemId}`
- `DELETE /api/v1/projects/{id}/items/{projectItemId}`

## 5.22. Calculos y resumenes de proyecto

### Endpoints

- `POST /api/v1/projects/{id}/recalculate`
- `GET /api/v1/projects/{id}/summary-incidence`
- `GET /api/v1/projects/{id}/breakdowns`
- `POST /api/v1/projects/{id}/breakdowns/calculate`
- `GET /api/v1/projects/{id}/unit-prices`

### Observacion

Al igual que en items, aqui la prioridad es aislar las reglas de negocio complejas en servicios testeables.

## 5.23. Busquedas auxiliares

El sistema actual tiene varios controladores `Search_*`. En el backend nuevo conviene unificar esto en endpoints de soporte.

### Endpoints sugeridos

- `GET /api/v1/search/functions`
- `GET /api/v1/search/inputs`
- `GET /api/v1/search/items`
- `GET /api/v1/search/projects`
- `GET /api/v1/search/users`

## 5.24. Reportes

### Objetivo

Generar salidas PDF y consultas exportables.

### Endpoints sugeridos

- `GET /api/v1/reports/inputs`
- `GET /api/v1/reports/input-history`
- `GET /api/v1/reports/projects/{id}/unit-prices`
- `GET /api/v1/reports/projects/{id}/breakdowns`
- `GET /api/v1/reports/items/{id}`
- `POST /api/v1/reports/pdf/merge`

### Recomendacion

Los reportes deberian separarse del CRUD comun. Si la generacion es pesada, conviene usar colas.

## 5.25. Auditoria

### Objetivo

Registrar operaciones relevantes del sistema.

### Endpoints

- `GET /api/v1/audit-logs`
- `GET /api/v1/audit-logs/{id}`

### Recomendacion

La escritura de auditoria no deberia depender de que el desarrollador recuerde hacerlo en cada endpoint. Conviene centralizarla por eventos, observers o middleware de dominio cuando sea posible.

## 5.26. Combos y metadata para frontend

### Objetivo

Evitar que React tenga que llamar 10 endpoints para cargar un formulario complejo.

### Endpoints sugeridos

- `GET /api/v1/meta/users-form`
- `GET /api/v1/meta/inputs-form`
- `GET /api/v1/meta/items-form`
- `GET /api/v1/meta/projects-form`
- `GET /api/v1/meta/permissions-form`

## 6. Lo que hay que hacer en general para el backend

No basta con listar APIs. El backend necesita una base de arquitectura limpia.

## 6.1. Definir arquitectura interna de Laravel

Se recomienda separar al menos en:

- Controllers
- Form Requests
- Resources
- Services o Actions
- Repositories si el equipo decide usarlos
- Policies o Gates
- Jobs para procesos pesados
- Events/Listeners para auditoria y acciones derivadas

## 6.2. Modelar autenticacion moderna

Hay que resolver:

- estrategia de login segura
- migracion progresiva desde hashes antiguos
- expiracion de tokens o sesiones
- revocacion de acceso
- proteccion de endpoints privados

## 6.3. Modelar permisos de forma centralizada

Esto es obligatorio para el nuevo sistema.

Debe existir una capa clara para responder preguntas como:

- que roles existen
- que funciones tiene cada rol
- que puede hacer el usuario autenticado
- como se asignan permisos sin tocar codigo

## 6.4. Extraer reglas de negocio del legacy

Los puntos mas complejos a extraer son:

- formulas de items
- recalculos por variante (`upre`, `fps`, `fndr`, `obras`, `proman`)
- armado de proyecto-item
- condiciones de solicitudes
- generacion de reportes

## 6.5. Definir estrategia de migracion de contraseñas

El sistema actual usa MD5 en mayusculas. El nuevo backend no debe mantener esto como solucion final.

Opciones posibles:

- migracion al primer login exitoso
- proceso administrativo de reseteo inicial
- migracion controlada de usuarios activos

## 6.6. Reforzar auditoria

La auditoria nueva debe registrar al menos:

- usuario
- accion
- recurso afectado
- fecha/hora
- IP
- payload resumido o cambios relevantes

## 6.7. Normalizar estados y catalogos

El backend debe documentar y centralizar estados hoy dispersos, por ejemplo:

- `AC`
- `DC`
- `DP`
- `PD`
- `RV`
- `AP`

## 6.8. Resolver archivos y almacenamiento

Hay que decidir:

- si los archivos van a disco local o storage externo
- como se guardan metadata y versiones
- como se protege descarga por permisos

## 6.9. Preparar backend para React

El backend debe entregar:

- respuestas consistentes
- errores de validacion claros
- combos y metadata
- paginacion uniforme
- filtros previsibles
- payloads estables

## 6.10. Preparar reportes y procesos pesados

Hay procesos que probablemente no deberian ejecutarse inline si son costosos. Conviene prever:

- Jobs
- Colas
- almacenamiento temporal de reportes
- polling o estado de generacion

## 7. Orden recomendado de construccion del backend

### Fase 1

- autenticacion
- perfil
- usuarios
- roles
- permisos por rol
- funciones del sistema

### Fase 2

- unidades
- catalogos maestros
- porcentajes de calculo
- metadata para formularios

### Fase 3

- insumos
- solicitudes de insumo
- cotizaciones
- historicos

### Fase 4

- items
- item-insumo
- archivos de item
- recalculos de item

### Fase 5

- proyectos
- proyecto-item
- recalculos y resumenes
- reportes

### Fase 6

- auditoria avanzada
- optimizaciones
- jobs y procesos pesados

## 8. Riesgos backend que deben vigilarse

- formulas no documentadas en items y proyectos
- diferencias entre comportamiento legacy y nuevo
- datos historicos inconsistentes
- permisos mal interpretados al migrar
- sobrecarga de endpoints sin estandar comun

## 9. Recomendacion final

El backend nuevo debe construirse como una plataforma ordenada y extensible, no solo como un CRUD grande.

Los dos pilares funcionales mas importantes son:

1. conservar correctamente la logica del negocio actual
2. crear un sistema moderno de permisos por rol facil de administrar

Si eso se resuelve bien, React podra consumir una API consistente y el nuevo SIPRE tendra una base solida para crecer.
