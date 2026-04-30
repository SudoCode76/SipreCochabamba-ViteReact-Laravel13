# API Usage

## Overview

Este documento describe las APIs que actualmente existen en el backend de SIPRE y como usarlas.

Base URL local:

```text
http://localhost:8000
```

Prefijo de version actual:

```text
/api/v1
```

Formato general de respuesta exitosa:

```json
{
  "success": true,
  "message": "Operacion realizada correctamente.",
  "data": {}
}
```

Formato general de respuesta con error:

```json
{
  "success": false,
  "message": "Descripcion del error.",
  "errors": {
    "campo": ["Detalle del error"]
  }
}
```

## Endpoints Disponibles

## 1. Health Check

Sirve para verificar que el backend esta levantado y conectado a una base de datos.

- Metodo: `GET`
- URL: `http://localhost:8000/api/health`
- Autenticacion: no requiere

Ejemplo de respuesta:

```json
{
  "name": "SipreCochabamba",
  "status": "ok",
  "database": "sipre",
  "timestamp": "2026-04-27T13:23:08+00:00"
}
```

## 2. Login

Sirve para autenticar un usuario y devolver un token Bearer de Sanctum.

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/auth/login`
- Autenticacion: no requiere
- Content-Type: `application/json`
- Accept: `application/json`

### Como funciona

- Busca el usuario en la tabla legacy `usuario`.
- El campo `username` no depende de mayusculas o minusculas.
- Valida la contrasena contra el valor guardado en `clave`.
- Si la contrasena legacy esta en MD5 y es correcta, la migra automaticamente a hash moderno de Laravel.
- Si el usuario y su rol estan activos, genera un token de acceso.

### Body

```json
{
  "username": "PRUEBA",
  "clave": "123",
  "device_name": "bruno"
}
```

### Body Para Bruno o Postman

```json
{
  "username": "PRUEBA",
  "clave": "123",
  "device_name": "bruno"
}
```

Campos:

- `username`: nombre de usuario
- `clave`: contrasena
- `device_name`: nombre del cliente que solicita el token, opcional

### Ejemplo de respuesta exitosa

```json
{
  "success": true,
  "message": "Inicio de sesion realizado correctamente.",
  "data": {
    "token": "1|token_generado",
    "token_type": "Bearer",
    "user": {
      "id": 141,
      "full_name": "USUARIO DE PRUEBA",
      "ci": "12345678",
      "username": "PRUEBA",
      "status": "AC",
      "role": {
        "id": 1,
        "name": "ADMINISTRADOR",
        "status": "AC"
      },
      "unit": {
        "id": 4,
        "description": "UNIDAD DE EJEMPLO",
        "status": "AC"
      },
      "permissions": []
    }
  }
}
```

### Posibles errores

- `422`: credenciales invalidas
- `403`: usuario o rol inactivo

### Ejemplo en Bruno

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/auth/login`
- Body: `JSON`

```json
{
  "username": "PRUEBA",
  "clave": "123",
  "device_name": "bruno"
}
```

Headers recomendados:

```text
Content-Type: application/json
Accept: application/json
```

## 3. Perfil Autenticado

Sirve para obtener la informacion del usuario autenticado usando el token Bearer recibido en login.

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/auth/me`
- Autenticacion: `Bearer token`

### Como funciona

- Lee el token de Sanctum enviado en el header `Authorization`.
- Resuelve el usuario autenticado.
- Devuelve datos del usuario, rol, unidad y permisos activos.

### Headers

```text
Authorization: Bearer TU_TOKEN
Accept: application/json
```

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Perfil autenticado obtenido correctamente.",
  "data": {
    "user": {
      "id": 141,
      "full_name": "USUARIO DE PRUEBA",
      "ci": "12345678",
      "username": "PRUEBA",
      "status": "AC",
      "role": {
        "id": 1,
        "name": "ADMINISTRADOR",
        "status": "AC"
      },
      "unit": {
        "id": 4,
        "description": "UNIDAD DE EJEMPLO",
        "status": "AC"
      },
      "permissions": []
    }
  }
}
```

## 4. Logout

Sirve para cerrar la sesion actual eliminando el token con el que se hizo la solicitud.

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/auth/logout`
- Autenticacion: `Bearer token`

### Como funciona

- Toma el token actual asociado a la peticion.
- Elimina ese token de `personal_access_tokens`.
- Despues de eso, el token ya no debe volver a usarse.

### Headers

```text
Authorization: Bearer TU_TOKEN
Accept: application/json
```

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Sesion cerrada correctamente.",
  "data": null
}
```

## 5. Cambiar Contrasena

Sirve para que el usuario autenticado cambie su propia contrasena.

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/auth/change-password`
- Autenticacion: `Bearer token`

### Como funciona

- Solo funciona para un usuario con sesion iniciada.
- Valida la contrasena actual contra el valor de `clave` del usuario autenticado.
- Soporta validacion de hash moderno y tambien contrasena legacy MD5.
- Si la contrasena actual es correcta, guarda la nueva contrasena con hash moderno de Laravel.
- La nueva contrasena debe ser diferente a la actual.
- La nueva contrasena debe tener al menos 8 caracteres.

### Headers

```text
Authorization: Bearer TU_TOKEN
Content-Type: application/json
Accept: application/json
```

### Body

```json
{
  "current_password": "123",
  "password": "NuevaClave123",
  "password_confirmation": "NuevaClave123"
}
```

### Body Para Bruno o Postman

```json
{
  "current_password": "123",
  "password": "NuevaClave123",
  "password_confirmation": "NuevaClave123"
}
```

### Ejemplo de respuesta exitosa

```json
{
  "success": true,
  "message": "Contrasena actualizada correctamente.",
  "data": null
}
```

### Posibles errores

- `401`: token invalido o ausente
- `422`: contrasena actual incorrecta
- `422`: nueva contrasena igual a la actual
- `422`: validacion fallida, por ejemplo si `password_confirmation` no coincide o si la nueva contrasena tiene menos de 8 caracteres

## 6. Perfil

Sirve para consultar los datos del usuario autenticado.

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/profile`
- Autenticacion: `Bearer token`

### Como funciona

- Usa el token actual de Sanctum.
- Devuelve los datos del propio usuario autenticado.
- Incluye rol, unidad y permisos activos.
- Este endpoint es solo de consulta.

### Headers

```text
Authorization: Bearer TU_TOKEN
Accept: application/json
```

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Perfil obtenido correctamente.",
  "data": {
    "user": {
      "id": 141,
      "full_name": "USUARIO DE PRUEBA",
      "ci": "99999999",
      "username": "PRUEBA",
      "status": "AC"
    }
  }
}
```

## 7. Cambiar Contrasena Desde Perfil

Sirve para que el usuario autenticado cambie solo su propia contrasena desde el modulo de perfil.

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/profile/password`
- Autenticacion: `Bearer token`

### Como funciona

- Solo aplica al usuario autenticado.
- No permite cambiar ningun otro dato del perfil.
- Usa la misma validacion de seguridad que el cambio de contrasena de auth.

### Headers

```text
Authorization: Bearer TU_TOKEN
Content-Type: application/json
Accept: application/json
```

### Body

```json
{
  "current_password": "123",
  "password": "NuevaClave123",
  "password_confirmation": "NuevaClave123"
}
```

### Body Para Bruno o Postman

```json
{
  "current_password": "123",
  "password": "NuevaClave123",
  "password_confirmation": "NuevaClave123"
}
```

## 8. Listar Roles

Sirve para obtener los roles registrados en la tabla legacy `rol`.

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/roles`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

### Headers

```text
Authorization: Bearer TU_TOKEN
Accept: application/json
```

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Roles obtenidos correctamente.",
  "data": {
    "items": [
      {
        "id": 1,
        "name": "ADMINISTRADOR",
        "status": "AC"
      }
    ]
  }
}
```

## 9. Crear Rol

Sirve para registrar un nuevo rol del sistema.

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/roles`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

### Headers

```text
Authorization: Bearer TU_TOKEN
Content-Type: application/json
Accept: application/json
```

### Body

```json
{
  "nombre_rol": "Supervisor",
  "estado": "AC"
}
```

Restricciones:

- `nombre_rol` es obligatorio
- `nombre_rol` debe ser unico
- `nombre_rol` admite hasta `20` caracteres para mantener compatibilidad con la tabla legacy `permiso`
- `estado` debe ser `AC` o `DC`

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Rol creado correctamente.",
  "data": {
    "role": {
      "id": 6,
      "name": "Supervisor",
      "status": "AC"
    }
  }
}
```

## 10. Ver Detalle de Rol

Sirve para obtener un rol especifico por su `id_rol` legacy.

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/roles/{role}`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

### Headers

```text
Authorization: Bearer TU_TOKEN
Accept: application/json
```

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Rol obtenido correctamente.",
  "data": {
    "role": {
      "id": 1,
      "name": "ADMINISTRADOR",
      "status": "AC"
    }
  }
}
```

## 11. Editar Rol

Sirve para actualizar el nombre o estado de un rol existente.

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/roles/{role}`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

### Importante

- Si cambia el nombre del rol, tambien se actualiza `nombre_rol` en la tabla legacy `permiso` para mantener consistencia.

### Headers

```text
Authorization: Bearer TU_TOKEN
Content-Type: application/json
Accept: application/json
```

### Body

```json
{
  "nombre_rol": "Supervisor Tecnico",
  "estado": "DC"
}
```

Restricciones:

- `nombre_rol` es obligatorio
- `nombre_rol` debe ser unico
- `nombre_rol` admite hasta `20` caracteres para mantener compatibilidad con la tabla legacy `permiso`
- `estado` debe ser `AC` o `DC`

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Rol actualizado correctamente.",
  "data": {
    "role": {
      "id": 2,
      "name": "Supervisor Tecnico",
      "status": "DC"
    },
    "previous_name": "Tecnico"
  }
}
```

## 12. Activar o Desactivar Rol

Sirve para cambiar solamente el estado de un rol existente.

- Metodo: `PATCH`
- URL: `http://localhost:8000/api/v1/roles/{role}/status`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

### Headers

```text
Authorization: Bearer TU_TOKEN
Content-Type: application/json
Accept: application/json
```

### Body

```json
{
  "estado": "DC"
}
```

Restricciones:

- `estado` debe ser `AC` o `DC`

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Estado del rol actualizado correctamente.",
  "data": {
    "role": {
      "id": 2,
      "name": "Supervisor Tecnico",
      "status": "DC"
    }
  }
}
```

## 8. Ver Permisos de un Rol

Sirve para obtener los permisos activos asignados actualmente a un rol especifico.

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/roles/{role}/permissions`
- Autenticacion: `Bearer token`

### Importante

- Este endpoint no devuelve todos los permisos existentes del sistema.
- Devuelve solo los permisos activos del rol solicitado.
- Cada permiso incluye la funcion legacy asociada.

### Headers

```text
Authorization: Bearer TU_TOKEN
Accept: application/json
```

### Ejemplo

```text
GET http://localhost:8000/api/v1/roles/1/permissions
```

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Permisos del rol obtenidos correctamente.",
  "data": {
    "role": {
      "id": 1,
      "name": "ADMINISTRADOR",
      "status": "AC"
    },
    "permissions": [
      {
        "id": 1,
        "description": "ADMINISTRAR USUARIOS",
        "status": "AC",
        "function": {
          "id": 4,
          "name": "USUARIOS",
          "description": "ADMINISTRAR USUARIOS",
          "class": "ADMINISTRADOR",
          "status": "AC"
        }
      }
    ]
  }
}
```

## 9. Actualizar Permisos de un Rol

Sirve para sincronizar los permisos de un rol a partir de una lista completa de funciones permitidas.

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/roles/{role}/permissions`
- Autenticacion: `Bearer token`
- Content-Type: `application/json`

### Como funciona

- Recibe una lista de `function_ids`.
- Los permisos del rol que no esten en esa lista se desactivan.
- Los permisos ya existentes en la lista se reactivan o actualizan.
- Los permisos que no existian para ese rol se crean.

### Como agregar un permiso a un rol

Ahora existen dos formas:

#### Opcion recomendada: agregar uno solo con `attach`

Usa este endpoint cuando solo quieras sumar una funcion puntual a un rol sin reenviar toda la lista.

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/roles/{role}/permissions/attach`

Body:

```json
{
  "function_id": 31
}
```

Ejemplo de respuesta:

```json
{
  "success": true,
  "message": "Permiso agregado correctamente al rol.",
  "data": {
    "role": {
      "id": 1,
      "name": "ADMINISTRADOR",
      "status": "AC"
    },
    "function_id": 31
  }
}
```

#### Opcion alternativa: sincronizacion completa con `PUT`

Si prefieres trabajar con una lista total de funciones activas del rol, puedes seguir usando `PUT /api/v1/roles/{role}/permissions`.

En ese caso, para agregar un nuevo permiso a un rol debes:

1. Consultar primero los permisos actuales del rol con:

```text
GET /api/v1/roles/{role}/permissions
```

2. Tomar los `id` de las funciones que ya tiene asignadas ese rol.
3. Agregar a esa lista el `id_funcion` nuevo que quieres habilitar.
4. Enviar la lista completa en:

```text
PUT /api/v1/roles/{role}/permissions
```

### Ejemplo practico

Si el rol `1` actualmente tiene funciones `4`, `8` y `20`, y quieres agregarle tambien la funcion `31`, debes enviar:

```json
{
  "function_ids": [4, 8, 20, 31]
}
```

Si envias solo:

```json
{
  "function_ids": [31]
}
```

entonces el sistema interpretara que quieres dejar activo solamente ese permiso y desactivar los demas.

### Recomendacion de uso

- Para agregar una sola funcion, usa `POST /api/v1/roles/{role}/permissions/attach`.
- Para reemplazar toda la lista del rol, usa `PUT /api/v1/roles/{role}/permissions`.

### Como quitar un permiso de un rol

Ahora existen dos formas:

#### Opcion recomendada: quitar uno solo con `detach`

Usa este endpoint cuando solo quieras quitar una funcion puntual sin reenviar toda la lista.

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/roles/{role}/permissions/detach`

Body:

```json
{
  "function_id": 31
}
```

Ejemplo de respuesta:

```json
{
  "success": true,
  "message": "Permiso quitado correctamente del rol.",
  "data": {
    "role": {
      "id": 1,
      "name": "ADMINISTRADOR",
      "status": "AC"
    },
    "function_id": 31
  }
}
```

#### Opcion alternativa: sincronizacion completa con `PUT`

Para quitar un permiso tambien puedes seguir usando sincronizacion completa, excluyendo de la lista final el `id_funcion` que ya no debe quedar activo.

1. Consultar permisos actuales del rol con:

```text
GET /api/v1/roles/{role}/permissions
```

2. Identificar el `id` de la funcion que quieres quitar.
3. Construir la lista final sin ese `id_funcion`.
4. Enviar la lista completa restante en:

```text
PUT /api/v1/roles/{role}/permissions
```

### Ejemplo practico

Si el rol `1` actualmente tiene funciones `4`, `8`, `20` y `31`, y quieres quitar la funcion `31`, debes enviar:

```json
{
  "function_ids": [4, 8, 20]
}
```

Con eso, la funcion `31` quedara desactivada para ese rol y las otras seguiran activas.

### Importante

- `attach` agrega una funcion puntual al rol.
- `detach` quita una funcion puntual del rol.
- `PUT /roles/{role}/permissions` sigue siendo util para sincronizacion masiva.
- Siempre piensa el `PUT` como: "esta es la lista final exacta de permisos que debe tener el rol".

### Como obtener el `id_funcion` correcto para usar en el `PUT`

Para agregar o quitar permisos necesitas conocer el `id_funcion` de cada funcion del sistema.

Hoy puedes obtenerlo de dos formas practicas:

1. Consultando la matriz completa:

```text
GET /api/v1/permissions/matrix
```

Ese endpoint devuelve todas las funciones activas con esta estructura:

```json
{
  "id": 4,
  "name": "USUARIOS",
  "description": "ADMINISTRAR USUARIOS",
  "class": "ADMINISTRADOR",
  "status": "AC"
}
```

En ese caso, el valor de `id` es el `id_funcion` que debes usar en `function_ids`.

2. Consultando los permisos actuales de un rol:

```text
GET /api/v1/roles/{role}/permissions
```

Ese endpoint devuelve cada permiso con su funcion asociada:

```json
{
  "id": 1,
  "description": "ADMINISTRAR USUARIOS",
  "status": "AC",
  "function": {
    "id": 4,
    "name": "USUARIOS",
    "description": "ADMINISTRAR USUARIOS",
    "class": "ADMINISTRADOR",
    "status": "AC"
  }
}
```

De nuevo, `function.id` es el `id_funcion` que debes usar en el `PUT`.

### Recomendacion practica

- Si quieres ver todas las funciones posibles del sistema, usa `GET /api/v1/permissions/matrix`.
- Si solo quieres partir del estado actual de un rol, usa `GET /api/v1/roles/{role}/permissions`.
- Luego construye el array `function_ids` con esos `id`.
- Si solo quieres agregar o quitar una funcion puntual, usa `attach` o `detach` con `function_id`.

### Headers

```text
Authorization: Bearer TU_TOKEN
Content-Type: application/json
Accept: application/json
```

### Body

```json
{
  "function_ids": [4, 8, 20]
}
```

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Permisos del rol actualizados correctamente.",
  "data": {
    "role": {
      "id": 1,
      "name": "ADMINISTRADOR",
      "status": "AC"
    },
    "function_ids": [4, 8, 20],
    "permissions_count": 3
  }
}
```

## 10. Sincronizar Permisos de un Rol

Sirve para sincronizar permisos usando `POST` en lugar de `PUT`, manteniendo el mismo comportamiento de reemplazo total de la lista final.

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/roles/{role}/permissions/sync`
- Autenticacion: `Bearer token`
- Content-Type: `application/json`

### Headers

```text
Authorization: Bearer TU_TOKEN
Content-Type: application/json
Accept: application/json
```

### Body

```json
{
  "function_ids": [4, 8, 20]
}
```

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Permisos del rol sincronizados correctamente.",
  "data": {
    "role": {
      "id": 1,
      "name": "ADMINISTRADOR",
      "status": "AC"
    },
    "function_ids": [4, 8, 20],
    "permissions_count": 3
  }
}
```

## 11. Clonar Permisos Desde Otro Rol

Sirve para copiar al rol destino la lista activa de permisos de otro rol origen.

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/roles/{role}/permissions/clone-from/{sourceRoleId}`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

### Como funciona

- Toma los permisos activos del rol origen `sourceRoleId`.
- Reemplaza la lista activa del rol destino `{role}` con esa lista.
- Si el rol destino tenia permisos extras, se desactivan.

### Headers

```text
Authorization: Bearer TU_TOKEN
Accept: application/json
```

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Permisos del rol clonados correctamente.",
  "data": {
    "role": {
      "id": 2,
      "name": "TECNICO",
      "status": "AC"
    },
    "source_role": {
      "id": 1,
      "name": "ADMINISTRADOR",
      "status": "AC"
    },
    "function_ids": [4, 8, 20],
    "permissions_count": 3
  }
}
```

## 12. Ver Matriz de Permisos

Sirve para obtener una matriz completa de permisos por roles y funciones, optimizada para construir una UI tipo tabla o checkboxes.

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/permissions/matrix`
- Autenticacion: `Bearer token`

### Importante

- Este endpoint devuelve todos los roles activos.
- Devuelve todas las funciones activas del sistema.
- Para cada funcion indica que roles la tienen asignada.
- Este es el endpoint correcto si quieres ver la relacion completa rol-funcion.

### Headers

```text
Authorization: Bearer TU_TOKEN
Accept: application/json
```

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Matriz de permisos obtenida correctamente.",
  "data": {
    "roles": [
      {
        "id": 1,
        "name": "ADMINISTRADOR",
        "status": "AC"
      },
      {
        "id": 2,
        "name": "TECNICO",
        "status": "AC"
      }
    ],
    "functions": [
      {
        "id": 4,
        "name": "USUARIOS",
        "description": "ADMINISTRAR USUARIOS",
        "class": "ADMINISTRADOR",
        "status": "AC",
        "assigned_role_ids": [1],
        "permissions": [
          {
            "role_id": 1,
            "allowed": true
          },
          {
            "role_id": 2,
            "allowed": false
          }
        ]
      }
    ]
  }
}
```

### Resumen practico

- `GET /api/v1/roles/{role}/permissions`: devuelve solo los permisos activos del rol pedido.
- `PUT /api/v1/roles/{role}/permissions`: reemplaza/sincroniza los permisos de ese rol.
- `GET /api/v1/permissions/matrix`: devuelve la vista completa de roles y funciones del sistema.

### Restriccion funcional esperada

- Estos endpoints requieren `Bearer token`.
- Ademas, solo pueden ser usados por usuarios cuyo rol activo sea `ADMINISTRADOR`.
- Si un usuario autenticado no administrador intenta usarlos, el backend responde `403 Forbidden`.

## 13. Funciones del Sistema

Este modulo administra la tabla legacy `funcion`.

### Que es una funcion del sistema

Una funcion del sistema es una accion o modulo que puede ser autorizado a un rol.

Ejemplos reales observados en la base legacy:

- `USUARIOS`
- `ROLES`
- `INSUMO`
- `LISTA_INSUMO`

Cada funcion define:

- el identificador funcional `nombre_funcion`
- la descripcion visible
- la clase o modulo al que pertenece
- su estado

### Diferencia entre `functions` y `permissions`

Esto es importante para frontend y backend:

- `functions` = catalogo maestro de acciones disponibles del sistema
- `permissions` = asignaciones de esas funciones a cada rol

En otras palabras:

- primero existe una fila en `funcion`
- luego un rol recibe acceso a esa funcion mediante una fila en `permiso`

Ejemplo:

- `function`: `INSUMO`
- `permission`: el rol `ADMINISTRADOR` tiene asignada la funcion `INSUMO`

Por eso:

- si quieres crear o editar el catalogo base de acciones del sistema, usa `/api/v1/functions`
- si quieres asignar o quitar acceso a un rol, usa `/api/v1/roles/{role}/permissions`

### Regla importante de unicidad

`nombre_funcion` se valida como unico global en toda la tabla `funcion`.

Esto significa que no se permite repetir el mismo `nombre_funcion` aunque cambie la `clase`.

### 13.1 Listar Funciones del Sistema

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/functions`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

Orden actual de salida:

- primero por `class`
- luego por `name`

Ejemplo de respuesta:

```json
{
  "success": true,
  "message": "Funciones del sistema obtenidas correctamente.",
  "data": {
    "items": [
      {
        "id": 7,
        "name": "FUNCIONES",
        "description": "MENU FUNCIONES",
        "class": "ADMINISTRADOR",
        "status": "AC"
      }
    ]
  }
}
```

### 13.2 Crear Funcion del Sistema

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/functions`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

Body ejemplo:

```json
{
  "nombre_funcion": "INPUT_QUOTES",
  "descripcion": "Gestionar cotizaciones",
  "clase": "INSUMO",
  "estado": "AC"
}
```

Restricciones:

- `nombre_funcion` es obligatorio
- `nombre_funcion` debe ser unico globalmente
- `nombre_funcion` maximo `100` caracteres
- `descripcion` maximo `50` caracteres
- `clase` maximo `30` caracteres
- `estado` debe ser `AC` o `DC`

### 13.3 Ver Detalle de Funcion

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/functions/{function}`

### 13.4 Editar Funcion del Sistema

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/functions/{function}`

Usa el mismo body de creacion.

### 13.5 Activar o Desactivar Funcion del Sistema

- Metodo: `PATCH`
- URL: `http://localhost:8000/api/v1/functions/{function}/status`

Body ejemplo:

```json
{
  "estado": "DC"
}
```

### Como se relaciona esto con permisos por rol

Flujo recomendado:

1. Crear o actualizar la funcion en `/api/v1/functions`
2. Consultar la matriz en `/api/v1/permissions/matrix`
3. Asignar esa funcion a uno o varios roles con:

```text
PUT /api/v1/roles/{role}/permissions
POST /api/v1/roles/{role}/permissions/attach
POST /api/v1/roles/{role}/permissions/sync
```

Si una funcion existe pero no esta asignada a un rol, el rol no tiene acceso.

## 14. Gestion de Insumos

Estos endpoints permiten administrar la tabla legacy `insumo`, su historico, trazabilidad y cotizaciones.

### 13.1 Listar Insumos

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/inputs`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

### Paginacion

Este endpoint no devuelve todos los insumos en una sola respuesta.

- Por defecto devuelve `15` registros por pagina.
- Puedes cambiar la cantidad con `per_page`.
- El maximo actual permitido es `100` por pagina.
- Para recorrer todo el listado debes avanzar por `page=1`, `page=2`, `page=3`, etc.

Ejemplo:

```text
GET /api/v1/inputs?page=1&per_page=100
```

La respuesta incluye metadatos para seguir paginando:

```json
{
  "success": true,
  "data": {
    "items": [],
    "meta": {
      "current_page": 1,
      "per_page": 100,
      "total": 3821
    }
  }
}
```

Si necesitas ver todos los insumos desde frontend, debes consumir todas las paginas usando esos metadatos.

Filtros disponibles:

- `description`
- `type_id`
- `unit_measure_id`
- `status`
- `quote_date`
- `per_page`

### 13.2 Crear Insumo

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/inputs`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

Body ejemplo:

```json
{
  "description": "Cemento Portland",
  "unit_measure_id": 54,
  "price": 65.5,
  "type_id": 1,
  "status": "AC",
  "code": "INS-100",
  "quote_date": "2026-04-29",
  "observation": "Cotizacion base"
}
```

### 13.3 Ver Detalle de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/inputs/{input}`

### 13.4 Editar Insumo

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/inputs/{input}`

Usa el mismo body de creacion, con `status` obligatorio.

### 13.5 Cambiar Estado de Insumo

- Metodo: `PATCH`
- URL: `http://localhost:8000/api/v1/inputs/{input}/status`

Body ejemplo:

```json
{
  "status": "DC"
}
```

Estados soportados segun datos legacy observados:

- `AC`
- `DC`
- `DP`

### 13.6 Ver Historico de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/inputs/{input}/history`

### 13.7 Ver Logs de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/inputs/{input}/logs`

### 13.8 Ver Cotizaciones de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/inputs/{input}/quotes`

### 13.9 Registrar Cotizacion de Insumo

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/inputs/{input}/quotes`

Body ejemplo:

```json
{
  "condition": "CONTADO",
  "status": "AC",
  "log_id": 10,
  "file": "public/cotizaciones/cotizacion.pdf",
  "date": "2026-04-29",
  "file_1": "public/cotizaciones/anexo1.pdf",
  "file_2": "public/cotizaciones/anexo2.pdf",
  "request_id": 8
}
```

## 15. Gestion de Usuarios

Estos endpoints son administrativos y permiten listar, crear y actualizar usuarios del sistema legacy.

### 15.1 Listar Usuarios

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/users`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

Filtros disponibles:

- `name`
- `username`
- `status`
- `role_id`
- `unit_id`
- `per_page`

Ejemplo:

```text
GET /api/v1/users?name=juan&status=AC&per_page=10
```

### 15.2 Crear Usuario

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/users`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

### Body Para Bruno o Postman

```json
{
  "funcionario": "Nuevo Usuario",
  "ci": "33333333",
  "username": "nuevo",
  "password": "newSecret123",
  "password_confirmation": "newSecret123",
  "estado": "AC",
  "role_id": 1,
  "unit_id": 1,
  "item": null,
  "subalcaldia": null
}
```

### 11.3 Ver Usuario

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/users/{id}`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

Ejemplo:

```text
GET /api/v1/users/143
```

### 11.4 Editar Usuario

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/users/{id}`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

### Body Para Bruno o Postman

```json
{
  "funcionario": "Usuario Editado",
  "ci": "55555555",
  "username": "editado",
  "estado": "AC",
  "role_id": 1,
  "unit_id": 1,
  "item": 10,
  "subalcaldia": 20
}
```

### 11.5 Cambiar Estado de Usuario

- Metodo: `PATCH`
- URL: `http://localhost:8000/api/v1/users/{id}/status`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

### Body Para Bruno o Postman

```json
{
  "estado": "DC"
}
```

### 11.6 Cambiar Rol de Usuario

- Metodo: `PATCH`
- URL: `http://localhost:8000/api/v1/users/{id}/role`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

### Body Para Bruno o Postman

```json
{
  "role_id": 2
}
```

### 11.7 Cambiar Unidad de Usuario

- Metodo: `PATCH`
- URL: `http://localhost:8000/api/v1/users/{id}/unit`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

### Body Para Bruno o Postman

```json
{
  "unit_id": 2
}
```

## Flujo Recomendado de Uso

1. Verificar que el backend esta disponible con `GET /api/health`.
2. Iniciar sesion con `POST /api/v1/auth/login`.
3. Guardar el valor de `data.token`.
4. Enviar ese token como `Bearer` para consumir `GET /api/v1/auth/me` o `GET /api/v1/profile`.
5. Si necesitas inspeccionar la matriz completa de permisos, consumir `GET /api/v1/permissions/matrix`.
6. Si necesitas ver o actualizar permisos de un rol, usar `GET` o `PUT /api/v1/roles/{role}/permissions`.
7. Si el usuario desea actualizar su contrasena, llamar a `PUT /api/v1/profile/password` o `POST /api/v1/auth/change-password` con el mismo token.
8. Cuando el usuario termine, llamar a `POST /api/v1/auth/logout` con el mismo token.

## Endpoints Aun No Implementados

Estos endpoints estan definidos en el plan del proyecto, pero no existen todavia en el backend actual:

- `POST /api/v1/auth/refresh`

## Notas

- La autenticacion actual usa Sanctum con token Bearer.
- No se esta usando JWT ni refresh token por ahora.
- La base funcional real del sistema se restaura desde el dump PostgreSQL legacy.
