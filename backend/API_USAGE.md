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

Nota general de autenticacion:

- todos los endpoints protegidos con `Bearer token` requieren tambien que el usuario y su rol sigan activos
- si el usuario o el rol fueron deshabilitados despues del login, la API responde `403`
- en ese caso el token actual deja de ser util para seguir operando

## Endpoints Disponibles

## 1. Health Check

Sirve para verificar que el backend esta levantado y conectado a una base de datos.

- Metodo: `GET`
- URL: `http://localhost:8000/api/health`
- Autenticacion: no requiere

Ejemplo de respuesta:

```json
{
  "success": true,
  "message": null,
  "data": {
    "name": "SipreCochabamba",
    "status": "ok",
    "database": "ok",
    "timestamp": "2026-04-27T13:23:08+00:00"
  }
}
```

Observacion:

- esta API ya no expone el nombre real de la base de datos
- `database` solo devuelve `ok` cuando la conexion responde correctamente

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

Sirve para cargar el listado administrativo principal de la pantalla `insumo`.

### Enriquecimiento aplicado

Cada registro sale enriquecido con joins equivalentes a:

- `insumo.*`
- `tipo_insumo.descripcion as nombre_tipo`
- `unidad_medida.descripcion as nombre_unidad_medida`
- `unidad_medida.abreviatura`

### Orden legacy respetado

La API lista exactamente con:

1. `tipo ASC`
2. `descripcion ASC`

### Campos principales por registro

- `id_insumo`
- `descripcion`
- `precio`
- `nombre_unidad_medida`
- `abreviatura`
- `nombre_tipo`
- `fecha_cotiz`
- `estado`
- `observacion`
- `tipo`
- `unidad_medida`

### Filtros soportados

- `search`
- `description` como alias de `search`
- `status`
- `type_id`
- `unit_measure_id`
- `page`
- `per_page`

Ejemplo:

```text
GET /api/v1/inputs?search=acero&status=AC&type_id=1&unit_measure_id=1&page=1&per_page=100
```

### 13.2 Contexto de Insumos

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/inputs/context`
- Autenticacion: `Bearer token`

Devuelve:

- tipos de insumo activos
- unidades de medida activas
- estados disponibles `AC` y `DC`
- permisos de la pantalla

### 13.3 Crear Insumo

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/inputs`
- Autenticacion: `Bearer token`

### Campos aceptados

La API acepta tanto nombres legacy como aliases actuales:

- `descripcion` o `description`
- `precio` o `price`
- `unidad_medida` o `unit_measure_id`
- `tipo` o `type_id`
- `fecha_cotiz` o `quote_date`
- `observacion` o `observation`
- `estado` o `status`
- `cod` o `code`
- `solicitud` o `request_id`
- `fecha` o `date`

### Validaciones minimas

- `descripcion` requerida
- `precio` requerido
- `unidad_medida` requerida
- `tipo` requerido
- `fecha_cotiz` requerida

### Regla legacy mantenida

No se crea si ya existe otro insumo con la misma `descripcion` y estado distinto de `DP`.

Si el alta es valida:

1. se inserta en `insumo`
2. se inserta en `log_insumo` con `accion = RG`
3. se inserta en `historial_insumo` con `accion = REGISTRADOR`

### 13.4 Ver Detalle de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/inputs/{input}`

Devuelve detalle enriquecido con:

- datos base del insumo
- `nombre_tipo`
- `nombre_unidad_medida`
- `id_tipo`
- `id_unidad_medida`
- `abreviatura`
- `fecha_cotiz`
- `observacion`
- `estado`

### 13.5 Obtener Nombre Simple de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/inputs/{input}/name`

Devuelve una respuesta liviana con:

- `id_insumo`
- `descripcion`

### 13.6 Editar Insumo

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/inputs/{input}`

Validaciones minimas:

- `descripcion` requerida
- `precio` requerido
- `unidad_medida` requerida
- `tipo` requerido
- `fecha_cotiz` requerida
- `estado` requerido

### Regla legacy mantenida al editar

- si cambia la descripcion, se valida duplicado contra otros insumos con estado distinto de `DP`
- se inserta en `log_insumo` con `accion = MD`
- se inserta en `historial_insumo` con `accion = MODIFICADO`

Nota:

- la logica `actualizar_precios(id_insumo)` no fue reintroducida porque no existe en el backend actual como dependencia activa del modulo

### 13.7 Eliminar Insumo con Autorizacion

- Metodo: `DELETE`
- URL: `http://localhost:8000/api/v1/inputs/{input}`

Body:

```json
{
  "autorizacion": "AUTH-001"
}
```

### Reglas legacy mantenidas

Solo elimina logicamente si:

1. no existe en `item_insumo` con `estado = AC`
2. existe autorizacion aprobada en `autorizaciones` con:
   - `id_elemento = id_insumo`
   - `nro_autorizacion = codigo enviado`
   - `tabla = insumo`
   - `estado = AP`

Si cumple:

- `insumo.estado = DP`
- se inserta un `log_insumo` con `estado = DP`

### 13.8 Solicitar Autorizacion de Eliminacion

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/inputs/{input}/delete-authorization-request`

La API crea una solicitud con:

- `id_elemento`
- `elemento`
- `tipo_elemento = insumo`
- `tabla = insumo`
- `solicitante`
- `estado = PE`

### 13.9 Consultar Estado de Autorizacion de Eliminacion

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/inputs/{input}/delete-authorization-status`

Responde si:

- no existe autorizacion
- existe autorizacion pendiente
- existe autorizacion aprobada y usable

### 13.10 Cambiar Estado de Insumo

- Metodo: `PATCH`
- URL: `http://localhost:8000/api/v1/inputs/{input}/status`

Body ejemplo:

```json
{
  "status": "DC"
}
```

Estados soportados:

- `AC`
- `DC`
- `DP`

### 13.11 Ver Historico de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/inputs/{input}/history`

Devuelve:

- accion
- fecha
- usuario
- nombre_usuario
- ip
- estado
- precio
- unidad de medida
- tipo

### 13.12 Ver Logs de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/inputs/{input}/logs`

Orden legacy respetado:

- `id_log DESC`

### 13.13 Ver Cotizaciones de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/inputs/{input}/quotes`

Devuelve el historico total de cotizaciones usando el mismo orden del historico completo.

### 13.14 Registrar Cotizacion de Insumo

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/inputs/{input}/quotes`

Soporta archivos:

- `valido`
- `propuesto_1`
- `propuesto_2`

Tambien sigue aceptando aliases legacy/string:

- `file`
- `file_1`
- `file_2`

### 13.15 Obtener Cotizacion Vigente

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/inputs/{input}/quotes/current`

Devuelve:

- descripcion del insumo
- archivos vigentes
- `id_cotizacion`
- `id_log_insumo`

### 13.16 Obtener Historico Completo de Cotizaciones

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/inputs/{input}/quotes/history`

Orden legacy respetado:

1. `fecha DESC`
2. `condicion DESC`
3. `id_cotizacion DESC`
4. `id_log_insumo ASC`

### 13.17 Obtener Historico de Cotizaciones por Log

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/inputs/{input}/quotes/log-history`

Orden legacy respetado:

1. `fecha DESC`
2. `id_log_insumo ASC`
3. `id_cotizacion ASC`

### 13.18 Ver Archivos de Cotizacion por Log

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/input-logs/{log}/files`

### 13.19 Busqueda para Selects

#### 13.19.1 Buscar Unidades de Medida

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/search/unit-measures`

Devuelve:

- `id`
- `text`

#### 13.19.2 Buscar Insumos

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/search/inputs`
- Autenticacion: `Bearer token`

Devuelve:

- `id`
- `text`
- `tipo`
- `precio`

Filtros soportados:

- `search`: texto libre
- `type`: id numerico de `tipo_insumo`

## 13.20 Tipos de Insumo

Estas APIs soportan la pantalla React `tipo-insumo`.

Es un modulo simple de parametrizacion sobre la tabla `tipo_insumo`.

### 13.20.1 Listar Tipos de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/input-types`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

Campos principales por registro:

- `id_tipo`
- `descripcion`
- `estado`
- `status_label`
- `available_actions`

Orden legacy respetado:

- `id_tipo DESC`

Conversion de estado para frontend:

- `AC` -> `ACTIVO`
- `DC` -> `INACTIVO`

Acciones disponibles:

```json
{
  "available_actions": {
    "edit": true
  }
}
```

### 13.20.2 Contexto de Tipos de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/input-types/context`
- Autenticacion: `Bearer token`

Devuelve:

- estados disponibles `AC` y `DC`
- permisos del usuario autenticado

### 13.20.3 Crear Tipo de Insumo

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/input-types`
- Autenticacion: `Bearer token`

Campos esperados:

- `descripcion`
- `estado`

Validaciones minimas:

- `descripcion` requerida
- `estado` requerido

Reglas funcionales mantenidas:

- `descripcion` se limpia con `trim()`
- si ya existe otro registro con la misma descripcion, no se inserta
- en duplicado responde error funcional claro
- si no es duplicado, inserta en `tipo_insumo`
- registra auditoria

### 13.20.4 Obtener Detalle de Tipo de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/input-types/{id}`
- Autenticacion: `Bearer token`

Devuelve:

- `id_tipo`
- `descripcion`
- `estado`

### 13.20.5 Editar Tipo de Insumo

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/input-types/{id}`
- Autenticacion: `Bearer token`

Campos esperados:

- `descripcion`
- `estado`

Reglas funcionales mantenidas:

- la nueva descripcion se limpia con `trim()`
- si la descripcion cambia, valida duplicado contra `tipo_insumo.descripcion`
- si la descripcion no cambia, actualiza directamente
- registra auditoria

### Ejemplo de error por duplicado

```json
{
  "success": false,
  "message": "Error de validacion.",
  "errors": {
    "descripcion": [
      "Ya existe un tipo de insumo con la misma descripcion."
    ]
  }
}
```

## 13.21 Unidades de Medida

Estas APIs soportan la pantalla React `unidad-de-medida`.

Es un modulo de parametrizacion sobre la tabla `unidad_medida`.

### 13.21.1 Listar Unidades de Medida

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/unit-measures`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

Campos principales por registro:

- `id_unidad_medida`
- `descripcion`
- `abreviatura`
- `estado`
- `status_label`
- `available_actions`

Regla legacy respetada:

- el listado no devuelve registros con `estado = DP`
- orden exacto: `id_unidad_medida DESC`

Conversion de estado para frontend:

- `AC` -> `ACTIVO`
- `DC` -> `INACTIVO`

Acciones disponibles:

```json
{
  "available_actions": {
    "edit": true,
    "delete": true
  }
}
```

### 13.21.2 Contexto de Unidades de Medida

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/unit-measures/context`
- Autenticacion: `Bearer token`

Devuelve:

- estados disponibles `AC` y `DC`
- permisos del usuario autenticado

### 13.21.3 Crear Unidad de Medida

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/unit-measures`
- Autenticacion: `Bearer token`

Campos esperados:

- `descripcion`
- `abreviatura`
- `estado`

Validaciones minimas:

- `descripcion` requerida
- `abreviatura` requerida
- `estado` requerido

Reglas funcionales mantenidas:

- `descripcion` se limpia con `trim()`
- `abreviatura` se limpia con `trim()`
- `estado` se guarda en mayusculas
- valida duplicado por `descripcion` con `estado != DP`
- valida duplicado por `abreviatura` con `estado != DP`
- si cualquiera existe, rechaza el alta
- si no existe, inserta en `unidad_medida`
- registra auditoria

### 13.21.4 Obtener Detalle de Unidad de Medida

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/unit-measures/{id}`
- Autenticacion: `Bearer token`

Devuelve:

- `id_unidad_medida`
- `descripcion`
- `abreviatura`
- `estado`

### 13.21.5 Editar Unidad de Medida

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/unit-measures/{id}`
- Autenticacion: `Bearer token`

Campos esperados:

- `descripcion`
- `abreviatura`
- `estado`

Reglas funcionales mantenidas:

- si cambia `descripcion`, valida duplicado contra `unidad_medida.descripcion` con `estado != DP`
- si cambia `abreviatura`, valida duplicado contra `unidad_medida.abreviatura` con `estado != DP`
- si no hay conflicto, actualiza registro
- registra auditoria

### 13.21.6 Eliminar Unidad de Medida con Autorizacion

- Metodo: `DELETE`
- URL: `http://localhost:8000/api/v1/unit-measures/{id}`
- Autenticacion: `Bearer token`

Body:

```json
{
  "autorizacion": "AUTH-UM-1"
}
```

Regla funcional mantenida:

- no elimina directamente sin autorizacion aprobada
- exige codigo de autorizacion valido
- al eliminar, cambia a `estado = DP`
- registra auditoria

### 13.21.7 Solicitar Autorizacion de Eliminacion

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/unit-measures/{id}/delete-authorization-request`
- Autenticacion: `Bearer token`

La solicitud creada usa el flujo funcional de autorizaciones con:

- `id_elemento`
- `elemento`
- `tipo_elemento = unidad_medida`
- `tabla = unidad_medida`
- `solicitante`
- `estado = PE`

### 13.21.8 Consultar Estado de Autorizacion

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/unit-measures/{id}/delete-authorization-status`
- Autenticacion: `Bearer token`

Responde si:

- no existe autorizacion
- existe autorizacion pendiente
- existe autorizacion aprobada y usable

### Ejemplo de error por duplicado

```json
{
  "success": false,
  "message": "Error de validacion.",
  "errors": {
    "abreviatura": [
      "Ya existe una unidad de medida con la misma abreviatura."
    ]
  }
}
```

## 14. Solicitudes de Insumo

Estas APIs soportan la pantalla React `solicitud-insumo`.

Trabajan sobre solicitudes previas al insumo aprobado final.

### 14.1 Listar Solicitudes de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/input-requests`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

La respuesta sale enriquecida con joins sobre:

- `tipo_insumo`
- `unidad_medida`
- `usuario`

Campos principales por registro:

- `id_solicitud`
- `descripcion`
- `precio`
- `nombre_unidad_medida`
- `abreviatura`
- `nombre_tipo`
- `fecha`
- `nombre_completo`
- `ubicacion`
- `justificacion`
- `estado_aprobacion`
- `approval_status_label`
- `notificacion`
- `archivo`
- `archivo1`
- `archivo2`
- `usuario_solicitante`
- `available_actions`

Orden legacy respetado:

- `descripcion ASC`

Filtros soportados:

- `search`
- `approval_status`
- `type_id`
- `unit_measure_id`
- `requester_id`
- `page`
- `per_page`

### Acciones disponibles por solicitud

La API devuelve `available_actions` para que frontend no tenga que inferir reglas desde el estado.

- si `estado_aprobacion = PD`
  - `edit = true`
  - `view_quotes = true`
- si `estado_aprobacion = AP` o `RC`
  - `edit = false`
  - `view_quotes = true`

### 14.2 Contexto de Solicitudes de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/input-requests/context`
- Autenticacion: `Bearer token`

Devuelve:

- tipos de insumo activos
- unidades de medida activas
- estados de aprobacion disponibles `PD`, `AP`, `RC`
- permisos del usuario autenticado

### 14.3 Crear Solicitud de Insumo

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/input-requests`
- Autenticacion: `Bearer token`

Campos aceptados:

- `descripcion`
- `precio`
- `unidad_medida`
- `tipo`
- `ubicacion`
- `justificacion`
- `usuario_solicitante`
- `estado_aprobacion`
- `notificacion`
- `fecha`
- `valido`
- `propuesto_1`
- `propuesto_2`

Validaciones minimas:

- `descripcion` requerida
- `precio` requerido
- `unidad_medida` requerido
- `tipo` requerido
- `ubicacion` requerida
- `justificacion` requerida
- `usuario_solicitante` requerido

Reglas heredadas mantenidas:

- `descripcion`, `ubicacion` y `justificacion` se convierten a mayusculas
- soporta hasta 3 archivos:
  - `valido`
  - `propuesto_1`
  - `propuesto_2`
- si el upload falla, falla toda la operacion
- se inserta en `solicitud_insumo`
- se registra una entrada en `cotizaciones` con `condicion = VALIDO`
- se registra auditoria funcional

Los archivos se guardan bajo el disk `public` en:

- `archivos/cotizaciones/valido/`
- `archivos/cotizaciones/propuesto_1/`
- `archivos/cotizaciones/propuesto_2/`

### 14.4 Editar Solicitud de Insumo

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/input-requests/{id}`
- Autenticacion: `Bearer token`

Permite editar contenido y, si corresponde, reemplazar archivos actuales.

Si se envia `adj = SI` o nuevos archivos:

- reprocesa `valido`, `propuesto_1`, `propuesto_2`
- actualiza `solicitud_insumo`
- registra una nueva entrada historica en `cotizaciones`
- actualiza `fecha_modificacion`
- registra auditoria

### 14.5 Obtener Detalle de Solicitud

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/input-requests/{id}`
- Autenticacion: `Bearer token`

Devuelve:

- datos base de la solicitud
- `nombre_tipo`
- `nombre_unidad_medida`
- `abreviatura`
- `nombre_completo`
- archivos asociados
- `estado_aprobacion`
- `approval_status_label`
- `ubicacion`
- `justificacion`
- `available_actions`

### 14.6 Historico de Cotizaciones / Adjuntos de Solicitud

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/input-requests/{id}/quotes/history`
- Autenticacion: `Bearer token`

Orden legacy respetado:

1. `fecha DESC`
2. `condicion DESC`
3. `id_cotizacion DESC`
4. `id_log_insumo ASC`

Devuelve por registro:

- `fecha`
- `archivo`
- `archivo1`
- `archivo2`
- `estado`
- `condicion`
- `id_cotizacion`
- `id_log_insumo`

### 14.7 Resumen de Cotizacion para Modal

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/input-requests/{id}/quote-summary`
- Autenticacion: `Bearer token`

Devuelve:

- `id_solicitud`
- `descripcion`
- `archivo`
- `archivo1`
- `archivo2`
- `id_log`
- `fecha`

### 14.8 Buscar Tipos de Insumo para Select

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/search/input-types`
- Autenticacion: `Bearer token`

Filtros soportados:

- `search`: texto libre

Devuelve:

- `id`
- `text`

## 13.20 Tipos de Insumo

Estas APIs soportan la pantalla React `tipo-insumo`.

Es un modulo simple de parametrizacion sobre la tabla `tipo_insumo`.

### 13.20.1 Listar Tipos de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/input-types`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

Campos principales por registro:

- `id_tipo`
- `descripcion`
- `estado`
- `status_label`
- `available_actions`

Orden legacy respetado:

- `id_tipo DESC`

Conversion de estado para frontend:

- `AC` -> `ACTIVO`
- `DC` -> `INACTIVO`

Acciones disponibles:

```json
{
  "available_actions": {
    "edit": true
  }
}
```

### 13.20.2 Contexto de Tipos de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/input-types/context`
- Autenticacion: `Bearer token`

Devuelve:

- estados disponibles `AC` y `DC`
- permisos del usuario autenticado

### 13.20.3 Crear Tipo de Insumo

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/input-types`
- Autenticacion: `Bearer token`

Campos esperados:

- `descripcion`
- `estado`

Validaciones minimas:

- `descripcion` requerida
- `estado` requerido

Reglas funcionales mantenidas:

- `descripcion` se limpia con `trim()`
- si ya existe otro registro con la misma descripcion, no se inserta
- en duplicado responde error funcional claro
- si no es duplicado, inserta en `tipo_insumo`
- registra auditoria

### 13.20.4 Obtener Detalle de Tipo de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/input-types/{id}`
- Autenticacion: `Bearer token`

Devuelve:

- `id_tipo`
- `descripcion`
- `estado`

### 13.20.5 Editar Tipo de Insumo

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/input-types/{id}`
- Autenticacion: `Bearer token`

Campos esperados:

- `descripcion`
- `estado`

Reglas funcionales mantenidas:

- la nueva descripcion se limpia con `trim()`
- si la descripcion cambia, valida duplicado contra `tipo_insumo.descripcion`
- si la descripcion no cambia, actualiza directamente
- registra auditoria

### Ejemplo de error por duplicado

```json
{
  "success": false,
  "message": "Error de validacion.",
  "errors": {
    "descripcion": [
      "Ya existe un tipo de insumo con la misma descripcion."
    ]
  }
}
```

## 13.21 Unidades de Medida

Estas APIs soportan la pantalla React `unidad-de-medida`.

Es un modulo de parametrizacion sobre la tabla `unidad_medida`.

### 13.21.1 Listar Unidades de Medida

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/unit-measures`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

Campos principales por registro:

- `id_unidad_medida`
- `descripcion`
- `abreviatura`
- `estado`
- `status_label`
- `available_actions`

Regla legacy respetada:

- el listado no devuelve registros con `estado = DP`
- orden exacto: `id_unidad_medida DESC`

Conversion de estado para frontend:

- `AC` -> `ACTIVO`
- `DC` -> `INACTIVO`

Acciones disponibles:

```json
{
  "available_actions": {
    "edit": true,
    "delete": true
  }
}
```

### 13.21.2 Contexto de Unidades de Medida

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/unit-measures/context`
- Autenticacion: `Bearer token`

Devuelve:

- estados disponibles `AC` y `DC`
- permisos del usuario autenticado

### 13.21.3 Crear Unidad de Medida

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/unit-measures`
- Autenticacion: `Bearer token`

Campos esperados:

- `descripcion`
- `abreviatura`
- `estado`

Validaciones minimas:

- `descripcion` requerida
- `abreviatura` requerida
- `estado` requerido

Reglas funcionales mantenidas:

- `descripcion` se limpia con `trim()`
- `abreviatura` se limpia con `trim()`
- `estado` se guarda en mayusculas
- valida duplicado por `descripcion` con `estado != DP`
- valida duplicado por `abreviatura` con `estado != DP`
- si cualquiera existe, rechaza el alta
- si no existe, inserta en `unidad_medida`
- registra auditoria

### 13.21.4 Obtener Detalle de Unidad de Medida

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/unit-measures/{id}`
- Autenticacion: `Bearer token`

Devuelve:

- `id_unidad_medida`
- `descripcion`
- `abreviatura`
- `estado`

### 13.21.5 Editar Unidad de Medida

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/unit-measures/{id}`
- Autenticacion: `Bearer token`

Campos esperados:

- `descripcion`
- `abreviatura`
- `estado`

Reglas funcionales mantenidas:

- si cambia `descripcion`, valida duplicado contra `unidad_medida.descripcion` con `estado != DP`
- si cambia `abreviatura`, valida duplicado contra `unidad_medida.abreviatura` con `estado != DP`
- si no hay conflicto, actualiza registro
- registra auditoria

### 13.21.6 Eliminar Unidad de Medida con Autorizacion

- Metodo: `DELETE`
- URL: `http://localhost:8000/api/v1/unit-measures/{id}`
- Autenticacion: `Bearer token`

Body:

```json
{
  "autorizacion": "AUTH-UM-1"
}
```

Regla funcional mantenida:

- no elimina directamente sin autorizacion aprobada
- exige codigo de autorizacion valido
- al eliminar, cambia a `estado = DP`
- registra auditoria

### 13.21.7 Solicitar Autorizacion de Eliminacion

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/unit-measures/{id}/delete-authorization-request`
- Autenticacion: `Bearer token`

La solicitud creada usa el flujo funcional de autorizaciones con:

- `id_elemento`
- `elemento`
- `tipo_elemento = unidad_medida`
- `tabla = unidad_medida`
- `solicitante`
- `estado = PE`

### 13.21.8 Consultar Estado de Autorizacion

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/unit-measures/{id}/delete-authorization-status`
- Autenticacion: `Bearer token`

Responde si:

- no existe autorizacion
- existe autorizacion pendiente
- existe autorizacion aprobada y usable

### Ejemplo de error por duplicado

```json
{
  "success": false,
  "message": "Error de validacion.",
  "errors": {
    "abreviatura": [
      "Ya existe una unidad de medida con la misma abreviatura."
    ]
  }
}
```

## 14. Solicitudes de Insumo

Estas APIs soportan la pantalla React `solicitud-insumo`.

Trabajan sobre solicitudes previas al insumo aprobado final.

### 14.1 Listar Solicitudes de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/input-requests`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

La respuesta sale enriquecida con joins sobre:

- `tipo_insumo`
- `unidad_medida`
- `usuario`

Campos principales por registro:

- `id_solicitud`
- `descripcion`
- `precio`
- `nombre_unidad_medida`
- `abreviatura`
- `nombre_tipo`
- `fecha`
- `nombre_completo`
- `ubicacion`
- `justificacion`
- `estado_aprobacion`
- `approval_status_label`
- `notificacion`
- `archivo`
- `archivo1`
- `archivo2`
- `usuario_solicitante`
- `available_actions`

Orden legacy respetado:

- `descripcion ASC`

Filtros soportados:

- `search`
- `approval_status`
- `type_id`
- `unit_measure_id`
- `requester_id`
- `page`
- `per_page`

### Acciones disponibles por solicitud

La API devuelve `available_actions` para que frontend no tenga que inferir reglas desde el estado.

- si `estado_aprobacion = PD`
  - `edit = true`
  - `view_quotes = true`
- si `estado_aprobacion = AP` o `RC`
  - `edit = false`
  - `view_quotes = true`

### 14.2 Contexto de Solicitudes de Insumo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/input-requests/context`
- Autenticacion: `Bearer token`

Devuelve:

- tipos de insumo activos
- unidades de medida activas
- estados de aprobacion disponibles `PD`, `AP`, `RC`
- permisos del usuario autenticado

### 14.3 Crear Solicitud de Insumo

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/input-requests`
- Autenticacion: `Bearer token`

Campos aceptados:

- `descripcion`
- `precio`
- `unidad_medida`
- `tipo`
- `ubicacion`
- `justificacion`
- `usuario_solicitante`
- `estado_aprobacion`
- `notificacion`
- `fecha`
- `valido`
- `propuesto_1`
- `propuesto_2`

Validaciones minimas:

- `descripcion` requerida
- `precio` requerido
- `unidad_medida` requerido
- `tipo` requerido
- `ubicacion` requerida
- `justificacion` requerida
- `usuario_solicitante` requerido

Reglas heredadas mantenidas:

- `descripcion`, `ubicacion` y `justificacion` se convierten a mayusculas
- soporta hasta 3 archivos:
  - `valido`
  - `propuesto_1`
  - `propuesto_2`
- si el upload falla, falla toda la operacion
- se inserta en `solicitud_insumo`
- se registra una entrada en `cotizaciones` con `condicion = VALIDO`
- se registra auditoria funcional

Los archivos se guardan bajo el disk `public` en:

- `archivos/cotizaciones/valido/`
- `archivos/cotizaciones/propuesto_1/`
- `archivos/cotizaciones/propuesto_2/`

### 14.4 Editar Solicitud de Insumo

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/input-requests/{id}`
- Autenticacion: `Bearer token`

Permite editar contenido y, si corresponde, reemplazar archivos actuales.

Si se envia `adj = SI` o nuevos archivos:

- reprocesa `valido`, `propuesto_1`, `propuesto_2`
- actualiza `solicitud_insumo`
- registra una nueva entrada historica en `cotizaciones`
- actualiza `fecha_modificacion`
- registra auditoria

### 14.5 Obtener Detalle de Solicitud

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/input-requests/{id}`
- Autenticacion: `Bearer token`

Devuelve:

- datos base de la solicitud
- `nombre_tipo`
- `nombre_unidad_medida`
- `abreviatura`
- `nombre_completo`
- archivos asociados
- `estado_aprobacion`
- `approval_status_label`
- `ubicacion`
- `justificacion`
- `available_actions`

### 14.6 Historico de Cotizaciones / Adjuntos de Solicitud

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/input-requests/{id}/quotes/history`
- Autenticacion: `Bearer token`

Orden legacy respetado:

1. `fecha DESC`
2. `condicion DESC`
3. `id_cotizacion DESC`
4. `id_log_insumo ASC`

Devuelve por registro:

- `fecha`
- `archivo`
- `archivo1`
- `archivo2`
- `estado`
- `condicion`
- `id_cotizacion`
- `id_log_insumo`

### 14.7 Resumen de Cotizacion para Modal

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/input-requests/{id}/quote-summary`
- Autenticacion: `Bearer token`

Devuelve:

- `id_solicitud`
- `descripcion`
- `archivo`
- `archivo1`
- `archivo2`
- `id_log`
- `fecha`

### 14.8 Buscar Tipos de Insumo para Select

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/search/input-types`
- Autenticacion: `Bearer token`

Devuelve:

- `id`
- `text`

## 15. Gestion de Usuarios

Estos endpoints son administrativos y permiten listar, crear y actualizar usuarios del sistema legacy.

Importante para frontend:

- el campo `unit_id` de usuarios corresponde a `unidad` administrativa
- no corresponde a `unidad_medida`
- para poblar el combo de unidades del formulario de usuario se debe usar la API `GET /api/v1/units/context` o `GET /api/v1/units`
- el flujo legacy principal no obliga a elegir la unidad desde un select
- normalmente la unidad llega como texto en `unidad.descripcion` despues de buscar el funcionario por CI
- al guardar, backend busca esa descripcion en tabla `unidad`
- si la unidad existe, usa su `id_unidad`
- si no existe, la crea automaticamente y luego la asigna al usuario
- la UI puede dejar que el usuario escriba la unidad manualmente
- mientras escribe, puede consultar `GET /api/v1/units?search=...` para sugerir coincidencias existentes
- si el usuario elige una sugerencia existente, debe reutilizarse esa unidad y no crear una nueva

### 15.1 Listar Usuarios

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/users`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

Filtros disponibles:

- `search`
- `name`
- `username`
- `ci`
- `status`
- `role_id`
- `unit_id`
- `per_page`

Comportamiento de `search`:

- funciona como busqueda global del listado
- busca por cualquiera de estos campos:
  - nombre completo
  - C.I.
  - usuario
  - rol
  - unidad administrativa

Ejemplo:

```text
GET /api/v1/users?name=juan&status=AC&per_page=10
```

Ejemplos de busqueda global:

```text
GET /api/v1/users?search=maria&per_page=10
GET /api/v1/users?search=22222222&per_page=10
GET /api/v1/users?search=administrador&per_page=10
GET /api/v1/users?search=unidad tecnica&per_page=10
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
  "unidad": {
    "descripcion": "DIRECCION DE AUDITORIA INTERNA"
  },
  "item": null,
  "subalcaldia": null
}
```

Tambien se acepta esta variante si frontend ya conoce el id:

```json
{
  "funcionario": "Nuevo Usuario",
  "ci": "33333333",
  "username": "nuevo",
  "password": "newSecret123",
  "password_confirmation": "newSecret123",
  "estado": "AC",
  "role_id": 1,
  "unit_id": 1
}
```

Para el campo de unidad se puede usar:

- `GET /api/v1/units/context`
- o `GET /api/v1/units`

Para autocompletado manual se recomienda:

- `GET /api/v1/units?search=texto`

Pero en el flujo legacy real lo mas comun es enviar:

- `unidad.descripcion`

En ese caso backend:

- busca la unidad por descripcion
- si la encuentra, usa su `id_unidad`
- si no la encuentra, crea una nueva unidad activa y luego la asigna al usuario
- la comparacion se hace normalizando mayusculas/minusculas y espacios repetidos para evitar duplicados triviales

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
  "unidad": {
    "descripcion": "UNIDAD OPERATIVA"
  },
  "item": 10,
  "subalcaldia": 20
}
```

Tambien se acepta `unit_id` si frontend ya lo conoce.

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

Tambien se acepta:

```json
{
  "unidad": {
    "descripcion": "UNIDAD OPERATIVA"
  }
}
```

En ese caso backend resuelve o crea la unidad automaticamente.

## 15.8 Unidades Administrativas para Usuarios

Estas APIs exponen la tabla `unidad` administrativa usada por usuarios.

No confundir con:

- `unidad` administrativa: usada en usuarios
- `unidad_medida`: usada en insumos, items y solicitudes

### 15.8.1 Listar Unidades

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/units`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

Devuelve:

- `id_unidad`
- `descripcion`
- `estado`
- `status_label`
- `available_actions`

Uso recomendado:

- se puede usar para mostrar todas las unidades en una pantalla administrativa o para edición de usuarios
- incluye unidades activas e inactivas
- soporta `search` para sugerencias o autocompletado por descripcion

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Unidades obtenidas correctamente.",
  "data": {
    "items": [
      {
        "id_unidad": 1,
        "descripcion": "Unidad Central",
        "estado": "AC",
        "status_label": "ACTIVO",
        "available_actions": {
          "select": true
        }
      }
    ]
  }
}
```

### 15.8.2 Contexto de Unidades para Combo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/units/context`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

Devuelve:

- `units`: solo unidades activas
- `statuses`
- `permissions`

Uso recomendado:

- para el formulario de crear o editar usuario
- usar `data.units` para poblar el select de unidad

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Contexto de unidades obtenido correctamente.",
  "data": {
    "units": [
      {
        "id_unidad": 1,
        "descripcion": "Unidad Central",
        "estado": "AC"
      }
    ],
    "statuses": [
      { "code": "AC", "label": "ACTIVO" },
      { "code": "DC", "label": "INACTIVO" }
    ],
    "permissions": {
      "can_view": true,
      "can_select": true
    }
  }
}
```

### 15.8.3 Ver Unidad

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/units/{id}`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

## 14. Grupos

Estas APIs soportan la pantalla React `parametros/grupos`.

Es un modulo de parametrizacion sobre la tabla `grupo`.

### 14.1 Listar Grupos

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/groups`
- Autenticacion: `Bearer token`
- Restriccion: solo `ADMINISTRADOR`

Campos principales por registro:

- `id_grupo`
- `codigo_grupo`
- `nombre_grupo`
- `estado`
- `status_label`
- `available_actions`

Reglas legacy respetadas:

- el listado no devuelve registros con `estado = DP`
- orden exacto: `id_grupo ASC`

Conversion de estado para frontend:

- `AC` -> `ACTIVO`
- `DC` -> `INACTIVO`

Acciones disponibles:

```json
{
  "available_actions": {
    "edit": true,
    "delete": true
  }
}
```

### 14.2 Contexto de Grupos

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/groups/context`
- Autenticacion: `Bearer token`

Devuelve:

- estados disponibles `AC` y `DC`
- permisos del usuario autenticado

### 14.3 Crear Grupo

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/groups`
- Autenticacion: `Bearer token`

Campos esperados:

- `codigo_grupo`
- `nombre_grupo`
- `estado`

Validaciones minimas:

- `codigo_grupo` requerido
- `nombre_grupo` requerido
- `estado` requerido

Reglas funcionales mantenidas:

- valida duplicado por `codigo_grupo` con `estado != DP`
- valida duplicado por `nombre_grupo` con `estado != DP`
- si cualquiera existe, rechaza el alta
- si no existe, inserta en `grupo`
- registra auditoria

### 14.4 Obtener Detalle de Grupo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/groups/{id}`
- Autenticacion: `Bearer token`

Devuelve:

- `id_grupo`
- `codigo_grupo`
- `nombre_grupo`
- `estado`

### 14.5 Editar Grupo

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/groups/{id}`
- Autenticacion: `Bearer token`

Campos esperados:

- `codigo_grupo`
- `nombre_grupo`
- `estado`

Reglas funcionales mantenidas:

- si cambia `nombre_grupo`, valida duplicado contra `grupo.nombre_grupo` con `estado != DP`
- si cambia `codigo_grupo`, valida duplicado contra `grupo.codigo_grupo` con `estado != DP`
- si no hay conflicto, actualiza registro
- registra auditoria

### 14.6 Eliminar Grupo con Autorizacion

- Metodo: `DELETE`
- URL: `http://localhost:8000/api/v1/groups/{id}`
- Autenticacion: `Bearer token`

Body:

```json
{
  "autorizacion": "AUTH-GR-1"
}
```

Reglas funcionales mantenidas:

- no elimina si existe algun item activo usando ese grupo
  - `item.grupo = id_grupo`
  - `item.estado = AC`
- exige autorizacion aprobada valida con:
  - `id_elemento = id_grupo`
  - `nro_autorizacion = codigo enviado`
  - `tabla = grupo`
  - `estado = AP`
- si cumple, cambia `grupo.estado = DP`
- registra auditoria

### 14.7 Solicitar Autorizacion de Eliminacion

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/groups/{id}/delete-authorization-request`
- Autenticacion: `Bearer token`

La solicitud creada usa el flujo funcional de autorizaciones con:

- `id_elemento`
- `elemento`
- `tipo_elemento = grupo`
- `tabla = grupo`
- `solicitante`
- `estado = PE`

### 14.8 Consultar Estado de Autorizacion

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/groups/{id}/delete-authorization-status`
- Autenticacion: `Bearer token`

Responde si:

- no existe autorizacion
- existe autorizacion pendiente
- existe autorizacion aprobada y usable

### Ejemplo de error funcional por items activos

```json
{
  "success": false,
  "message": "Error de validacion.",
  "errors": {
    "group": [
      "El grupo no puede eliminarse porque tiene items activos asociados."
    ]
  }
}
```

## 15. Proyectos

Estas APIs soportan la pantalla React de `nuevo-proyecto`.

Importante:

- el mapa se resuelve en frontend
- el backend no dibuja capas ni interactua con OpenLayers
- el backend solo recibe, valida y guarda los datos territoriales capturados por frontend

Autorizacion del modulo:

- no basta con tener `Bearer token`
- el backend aplica permisos funcionales del modulo `PROYECTO`
- ejemplos: `can_view`, `can_create`, `can_edit`, `can_sync_items`, `can_recalculate_budget`, `can_view_reports`
- el listado y el detalle requieren permisos de visualizacion
- crear requiere permiso de creacion
- editar requiere permiso de edicion
- sincronizacion de items requiere permiso especifico de sincronizacion
- reportes y recalculos requieren permisos de reportes o recalculo segun la operacion

### 15.1 Contexto de Creacion de Proyecto

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/projects/create-context`
- Autenticacion: `Bearer token`
- Restriccion funcional: requiere al menos permiso para ver o crear proyectos

Tambien existe por compatibilidad:

- `GET /api/v1/projects/context`

### Para que sirve

Devuelve todo lo necesario para cargar la pagina `nuevo-proyecto`:

- personas activas para `responsable`
- personas activas para `solicitante`
- estados disponibles `AC` y `DC`
- condiciones disponibles `PD`, `RV`, `AP`
- permisos funcionales del usuario autenticado
- metadata de apoyo para el formulario

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Contexto de proyectos obtenido correctamente.",
  "data": {
    "responsible_options": [
      {
        "id_usuario": 1,
        "funcionario": "Usuario Demo",
        "username": "demo",
        "estado": "AC"
      }
    ],
    "requester_options": [
      {
        "id_usuario": 1,
        "funcionario": "Usuario Demo",
        "username": "demo",
        "estado": "AC"
      }
    ],
    "statuses": [
      { "code": "AC", "label": "ACTIVO" },
      { "code": "DC", "label": "INACTIVO" }
    ],
    "conditions": [
      { "code": "PD", "label": "PENDIENTE" },
      { "code": "RV", "label": "REVISADO" },
      { "code": "AP", "label": "APROBADO" }
    ],
    "permissions": {
      "can_create": true
    },
    "metadata": {
      "creator_user_id": 1,
      "location_fields": ["latitud", "longitud", "distrito", "zona", "otb", "ubicacion"],
      "defaults": {
        "estado": "AC",
        "aprobado": "PD"
      }
    }
  }
}
```

### 15.2 Crear Proyecto

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/projects`
- Autenticacion: `Bearer token`
- Restriccion funcional: requiere permiso para crear proyectos

### Campos que acepta

- `nombre_proyecto`
- `fecha`
- `latitud`
- `longitud`
- `distrito`
- `zona`
- `otb`
- `ubicacion`
- `responsable`
- `solicitante`
- `observaciones`
- `estado`
- `aprobado`

Nota:

- `id_usuario` se toma del usuario autenticado
- no es necesario enviarlo desde frontend

### Body ejemplo

```json
{
  "nombre_proyecto": "NUEVO PROYECTO ZONA SUR",
  "fecha": "2026-05-04",
  "latitud": "-17.3935",
  "longitud": "-66.1570",
  "distrito": "2",
  "zona": "Zona Sur",
  "otb": "OTB Central",
  "ubicacion": "Av. Principal esquina Calle 5",
  "responsable": 1,
  "solicitante": 1,
  "observaciones": "Proyecto registrado desde la nueva pantalla React.",
  "estado": "AC",
  "aprobado": "PD"
}
```

### Validaciones aplicadas

- `nombre_proyecto`: requerido
- `fecha`: requerida
- `latitud`: requerida
- `longitud`: requerida
- `responsable`: requerido
- `solicitante`: requerido
- `observaciones`: requerida
- `estado`: requerido y debe ser `AC` o `DC`
- `aprobado`: requerido y debe ser `PD`, `RV` o `AP`
- `ubicacion`: requerida
- `distrito`: nullable
- `zona`: nullable
- `otb`: nullable

### Regla legacy mantenida

- los campos textuales del proyecto se convierten a mayusculas antes de guardar
- se valida duplicado por `nombre_proyecto`
- la comparacion de duplicado se hace de forma normalizada con `UPPER(TRIM(nombre_proyecto))`
- si ya existe un proyecto con el mismo nombre, no se inserta

### Campos de ubicacion que guarda el backend

- `latitud`
- `longitud`
- `distrito`
- `zona`
- `otb`
- `ubicacion`

### Ejemplo de respuesta exitosa

```json
{
  "success": true,
  "message": "Proyecto creado correctamente.",
  "data": {
    "project": {
      "id_proyecto": 15,
      "nombre_proyecto": "NUEVO PROYECTO ZONA SUR",
      "ubicacion": "AV. PRINCIPAL ESQUINA CALLE 5",
      "fecha": "2026-05-04",
      "responsable": "1",
      "solicitante": 1,
      "observaciones": "PROYECTO REGISTRADO DESDE LA NUEVA PANTALLA REACT.",
      "aprobado": "PD",
      "estado": "AC",
      "id_usuario": 1,
      "nombre_responsable": "Usuario Demo",
      "latitud": "-17.3935",
      "longitud": "-66.1570",
      "distrito": "2",
      "zona": "ZONA SUR",
      "otb": "OTB CENTRAL"
    }
  }
}
```

### Ejemplo de error por duplicado

```json
{
  "success": false,
  "message": "Error de validacion.",
  "errors": {
    "nombre_proyecto": [
      "Ya existe un proyecto con el mismo nombre."
    ]
  }
}
```

### 15.3 Nombre Visible de Usuario

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/users/{id}/display-name`
- Autenticacion: `Bearer token`

### Para que sirve

Replica el comportamiento del legacy cuando frontend selecciona responsable o solicitante y necesita recuperar el nombre visible del usuario.

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Nombre visible del usuario obtenido correctamente.",
  "data": {
    "id_usuario": 1,
    "funcionario": "Usuario Demo",
    "username": "demo",
    "estado": "AC"
  }
}
```

## Flujo Recomendado de Uso

Estas APIs soportan la pantalla React de `nuevo-proyecto`.

Importante:

- el mapa se resuelve en frontend
- el backend no dibuja capas ni interactua con OpenLayers
- el backend solo recibe, valida y guarda los datos territoriales capturados por frontend

### 15.1 Contexto de Creacion de Proyecto

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/projects/create-context`
- Autenticacion: `Bearer token`

Tambien existe por compatibilidad:

- `GET /api/v1/projects/context`

### Para que sirve

Devuelve todo lo necesario para cargar la pagina `nuevo-proyecto`:

- personas activas para `responsable`
- personas activas para `solicitante`
- estados disponibles `AC` y `DC`
- condiciones disponibles `PD`, `RV`, `AP`
- permisos funcionales del usuario autenticado
- metadata de apoyo para el formulario

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Contexto de proyectos obtenido correctamente.",
  "data": {
    "responsible_options": [
      {
        "id_usuario": 1,
        "funcionario": "Usuario Demo",
        "username": "demo",
        "estado": "AC"
      }
    ],
    "requester_options": [
      {
        "id_usuario": 1,
        "funcionario": "Usuario Demo",
        "username": "demo",
        "estado": "AC"
      }
    ],
    "statuses": [
      { "code": "AC", "label": "ACTIVO" },
      { "code": "DC", "label": "INACTIVO" }
    ],
    "conditions": [
      { "code": "PD", "label": "PENDIENTE" },
      { "code": "RV", "label": "REVISADO" },
      { "code": "AP", "label": "APROBADO" }
    ],
    "permissions": {
      "can_create": true
    },
    "metadata": {
      "creator_user_id": 1,
      "location_fields": ["latitud", "longitud", "distrito", "zona", "otb", "ubicacion"],
      "defaults": {
        "estado": "AC",
        "aprobado": "PD"
      }
    }
  }
}
```

### 15.2 Crear Proyecto

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/projects`
- Autenticacion: `Bearer token`

### Campos que acepta

- `nombre_proyecto`
- `fecha`
- `latitud`
- `longitud`
- `distrito`
- `zona`
- `otb`
- `ubicacion`
- `responsable`
- `solicitante`
- `observaciones`
- `estado`
- `aprobado`

Nota:

- `id_usuario` se toma del usuario autenticado
- no es necesario enviarlo desde frontend

### Body ejemplo

```json
{
  "nombre_proyecto": "NUEVO PROYECTO ZONA SUR",
  "fecha": "2026-05-04",
  "latitud": "-17.3935",
  "longitud": "-66.1570",
  "distrito": "2",
  "zona": "Zona Sur",
  "otb": "OTB Central",
  "ubicacion": "Av. Principal esquina Calle 5",
  "responsable": 1,
  "solicitante": 1,
  "observaciones": "Proyecto registrado desde la nueva pantalla React.",
  "estado": "AC",
  "aprobado": "PD"
}
```

### Validaciones aplicadas

- `nombre_proyecto`: requerido
- `fecha`: requerida
- `latitud`: requerida
- `longitud`: requerida
- `responsable`: requerido
- `solicitante`: requerido
- `observaciones`: requerida
- `estado`: requerido y debe ser `AC` o `DC`
- `aprobado`: requerido y debe ser `PD`, `RV` o `AP`
- `ubicacion`: requerida
- `distrito`: nullable
- `zona`: nullable
- `otb`: nullable

### Regla legacy mantenida

- los campos textuales del proyecto se convierten a mayusculas antes de guardar
- se valida duplicado por `nombre_proyecto`
- la comparacion de duplicado se hace de forma normalizada con `UPPER(TRIM(nombre_proyecto))`
- si ya existe un proyecto con el mismo nombre, no se inserta

### Campos de ubicacion que guarda el backend

- `latitud`
- `longitud`
- `distrito`
- `zona`
- `otb`
- `ubicacion`

### Ejemplo de respuesta exitosa

```json
{
  "success": true,
  "message": "Proyecto creado correctamente.",
  "data": {
    "project": {
      "id_proyecto": 15,
      "nombre_proyecto": "NUEVO PROYECTO ZONA SUR",
      "ubicacion": "AV. PRINCIPAL ESQUINA CALLE 5",
      "fecha": "2026-05-04",
      "responsable": "1",
      "solicitante": 1,
      "observaciones": "PROYECTO REGISTRADO DESDE LA NUEVA PANTALLA REACT.",
      "aprobado": "PD",
      "estado": "AC",
      "id_usuario": 1,
      "nombre_responsable": "Usuario Demo",
      "latitud": "-17.3935",
      "longitud": "-66.1570",
      "distrito": "2",
      "zona": "ZONA SUR",
      "otb": "OTB CENTRAL"
    }
  }
}
```

### Ejemplo de error por duplicado

```json
{
  "success": false,
  "message": "Error de validacion.",
  "errors": {
    "nombre_proyecto": [
      "Ya existe un proyecto con el mismo nombre."
    ]
  }
}
```

### 15.3 Nombre Visible de Usuario

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/users/{id}/display-name`
- Autenticacion: `Bearer token`

### Para que sirve

Replica el comportamiento del legacy cuando frontend selecciona responsable o solicitante y necesita recuperar el nombre visible del usuario.

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Nombre visible del usuario obtenido correctamente.",
  "data": {
    "id_usuario": 1,
    "funcionario": "Usuario Demo",
    "username": "demo",
    "estado": "AC"
  }
}
```

## Flujo Recomendado de Uso

Estas APIs soportan la pantalla React de `nuevo-proyecto`.

Importante:

- el mapa se resuelve en frontend
- el backend no dibuja capas ni interactua con OpenLayers
- el backend solo recibe, valida y guarda los datos territoriales capturados por frontend

### 15.1 Contexto de Creacion de Proyecto

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/projects/create-context`
- Autenticacion: `Bearer token`

Tambien existe por compatibilidad:

- `GET /api/v1/projects/context`

### Para que sirve

Devuelve todo lo necesario para cargar la pagina `nuevo-proyecto`:

- personas activas para `responsable`
- personas activas para `solicitante`
- estados disponibles `AC` y `DC`
- condiciones disponibles `PD`, `RV`, `AP`
- permisos funcionales del usuario autenticado
- metadata de apoyo para el formulario

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Contexto de proyectos obtenido correctamente.",
  "data": {
    "responsible_options": [
      {
        "id_usuario": 1,
        "funcionario": "Usuario Demo",
        "username": "demo",
        "estado": "AC"
      }
    ],
    "requester_options": [
      {
        "id_usuario": 1,
        "funcionario": "Usuario Demo",
        "username": "demo",
        "estado": "AC"
      }
    ],
    "statuses": [
      { "code": "AC", "label": "ACTIVO" },
      { "code": "DC", "label": "INACTIVO" }
    ],
    "conditions": [
      { "code": "PD", "label": "PENDIENTE" },
      { "code": "RV", "label": "REVISADO" },
      { "code": "AP", "label": "APROBADO" }
    ],
    "permissions": {
      "can_create": true
    },
    "metadata": {
      "creator_user_id": 1,
      "location_fields": ["latitud", "longitud", "distrito", "zona", "otb", "ubicacion"],
      "defaults": {
        "estado": "AC",
        "aprobado": "PD"
      }
    }
  }
}
```

### 15.2 Crear Proyecto

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/projects`
- Autenticacion: `Bearer token`

### Campos que acepta

- `nombre_proyecto`
- `fecha`
- `latitud`
- `longitud`
- `distrito`
- `zona`
- `otb`
- `ubicacion`
- `responsable`
- `solicitante`
- `observaciones`
- `estado`
- `aprobado`

Nota:

- `id_usuario` se toma del usuario autenticado
- no es necesario enviarlo desde frontend

### Body ejemplo

```json
{
  "nombre_proyecto": "NUEVO PROYECTO ZONA SUR",
  "fecha": "2026-05-04",
  "latitud": "-17.3935",
  "longitud": "-66.1570",
  "distrito": "2",
  "zona": "Zona Sur",
  "otb": "OTB Central",
  "ubicacion": "Av. Principal esquina Calle 5",
  "responsable": 1,
  "solicitante": 1,
  "observaciones": "Proyecto registrado desde la nueva pantalla React.",
  "estado": "AC",
  "aprobado": "PD"
}
```

### Validaciones aplicadas

- `nombre_proyecto`: requerido
- `fecha`: requerida
- `latitud`: requerida
- `longitud`: requerida
- `responsable`: requerido
- `solicitante`: requerido
- `observaciones`: requerida
- `estado`: requerido y debe ser `AC` o `DC`
- `aprobado`: requerido y debe ser `PD`, `RV` o `AP`
- `ubicacion`: requerida
- `distrito`: nullable
- `zona`: nullable
- `otb`: nullable

### Regla legacy mantenida

- los campos textuales del proyecto se convierten a mayusculas antes de guardar
- se valida duplicado por `nombre_proyecto`
- la comparacion de duplicado se hace de forma normalizada con `UPPER(TRIM(nombre_proyecto))`
- si ya existe un proyecto con el mismo nombre, no se inserta

### Campos de ubicacion que guarda el backend

- `latitud`
- `longitud`
- `distrito`
- `zona`
- `otb`
- `ubicacion`

### Ejemplo de respuesta exitosa

```json
{
  "success": true,
  "message": "Proyecto creado correctamente.",
  "data": {
    "project": {
      "id_proyecto": 15,
      "nombre_proyecto": "NUEVO PROYECTO ZONA SUR",
      "ubicacion": "AV. PRINCIPAL ESQUINA CALLE 5",
      "fecha": "2026-05-04",
      "responsable": "1",
      "solicitante": 1,
      "observaciones": "PROYECTO REGISTRADO DESDE LA NUEVA PANTALLA REACT.",
      "aprobado": "PD",
      "estado": "AC",
      "id_usuario": 1,
      "nombre_responsable": "Usuario Demo",
      "latitud": "-17.3935",
      "longitud": "-66.1570",
      "distrito": "2",
      "zona": "ZONA SUR",
      "otb": "OTB CENTRAL"
    }
  }
}
```

### Ejemplo de error por duplicado

```json
{
  "success": false,
  "message": "Error de validacion.",
  "errors": {
    "nombre_proyecto": [
      "Ya existe un proyecto con el mismo nombre."
    ]
  }
}
```

### 15.3 Nombre Visible de Usuario

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/users/{id}/display-name`
- Autenticacion: `Bearer token`

### Para que sirve

Replica el comportamiento del legacy cuando frontend selecciona responsable o solicitante y necesita recuperar el nombre visible del usuario.

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Nombre visible del usuario obtenido correctamente.",
  "data": {
    "id_usuario": 1,
    "funcionario": "Usuario Demo",
    "username": "demo",
    "estado": "AC"
  }
}
```

## Flujo Recomendado de Uso

1. Verificar que el backend esta disponible con `GET /api/health`.
2. Iniciar sesion con `POST /api/v1/auth/login`.
3. Guardar el valor de `data.token`.
4. Enviar ese token como `Bearer` para consumir `GET /api/v1/auth/me` o `GET /api/v1/profile`.
5. Si necesitas inspeccionar la matriz completa de permisos, consumir `GET /api/v1/permissions/matrix`.
6. Si necesitas ver o actualizar permisos de un rol, usar `GET` o `PUT /api/v1/roles/{role}/permissions`.

## 16. Items FNDR

Estas APIs reemplazan la logica de la pantalla legacy `items/fndr`.

El objetivo de este modulo es soportar completamente la pantalla React de analisis FNDR sin usar vistas server-side.

### Diferencia entre estas APIs

- `GET /api/v1/items/fndr/context`: carga el contexto inicial completo de la pantalla
- `GET /api/v1/items/fndr`: lista paginada y filtrable de items FNDR
- `POST /api/v1/items`: crea un item nuevo
- `GET /api/v1/items/{id}/price-analysis?mode=fndr`: devuelve el analisis FNDR actual del item
- `POST /api/v1/items/{id}/price-recalculation?mode=fndr`: recalcula el analisis usando `log_insumo` hasta una fecha dada
- `GET /api/v1/subgroups?group_id=...`: llena el combo dependiente de subgrupos

### Autorizacion funcional

Este modulo no usa solamente el criterio de administrador.

Internamente se resuelven permisos funcionales basados en la tabla `funcion` y las asignaciones en `permiso`.

Los booleanos que devuelve el contexto son:

- `can_view`: acceso a la pantalla FNDR
- `can_create`: crear items
- `can_view_price_analysis`: ver analisis de precio FNDR
- `can_recalculate`: recalcular analisis FNDR por fecha

### 16.1 Contexto FNDR

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/items/fndr/context`
- Autenticacion: `Bearer token`

### Para que sirve

Esta API existe para que frontend pueda cargar toda la pantalla FNDR con una sola solicitud inicial.

Devuelve:

- grupos activos
- subgrupos activos en lista plana
- subgrupos agrupados por grupo para combos dependientes
- estados disponibles para filtros y formulario
- unidades de medida activas para crear item
- permisos funcionales del usuario autenticado
- metadata de la pantalla y endpoints relacionados

### Como usarla desde frontend

Flujo recomendado:

1. Llamar a `GET /api/v1/items/fndr/context` al montar la pantalla.
2. Poblar el combo de grupos con `data.groups`.
3. Poblar el combo dependiente de subgrupos con:
   - `data.subgroups_by_group[groupId]` si ya quieres tener todo cargado en memoria, o
   - `GET /api/v1/subgroups?group_id=...` si quieres pedirlos bajo demanda.
4. Leer `data.permissions` para habilitar o deshabilitar botones de crear, analizar y recalcular.
5. Consumir `GET /api/v1/items/fndr` para la tabla principal.

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Contexto FNDR obtenido correctamente.",
  "data": {
    "groups": [
      {
        "id": 6,
        "name": "1.- OBRAS PRELIMINARES",
        "code": "001-OPR",
        "status": "AC"
      }
    ],
    "subgroups": [
      {
        "id": 9,
        "group_id": 6,
        "description": "PRELIMINARES",
        "code": "PRE",
        "status": "AC"
      }
    ],
    "subgroups_by_group": {
      "6": [
        {
          "id": 9,
          "group_id": 6,
          "description": "PRELIMINARES",
          "code": "PRE",
          "status": "AC"
        }
      ]
    },
    "statuses": [
      {
        "code": "AC",
        "label": "ACTIVO"
      },
      {
        "code": "DC",
        "label": "INACTIVO"
      }
    ],
    "unit_measures": [
      {
        "id": 54,
        "description": "pieza",
        "abbreviation": "pza.",
        "status": "AC"
      }
    ],
    "permissions": {
      "can_view": true,
      "can_create": true,
      "can_view_price_analysis": true,
      "can_recalculate": true
    },
    "meta": {
      "screen": "items/fndr",
      "mode": "fndr",
      "price_formula": "FNDR",
      "supports_dependent_subgroup_filter": true,
      "endpoints": {
        "list": "/api/v1/items/fndr",
        "create": "/api/v1/items",
        "subgroups": "/api/v1/subgroups?group_id={group_id}",
        "price_analysis": "/api/v1/items/{id}/price-analysis?mode=fndr",
        "price_recalculation": "/api/v1/items/{id}/price-recalculation?mode=fndr"
      },
      "filters": ["search", "group_id", "subgroup_id", "status", "page", "per_page"]
    }
  }
}
```

### 16.2 Listar Items FNDR

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/items/fndr`
- Autenticacion: `Bearer token`

### Filtros soportados

- `search`
- `group_id`
- `subgroup_id`
- `status`
- `page`
- `per_page`

Ejemplo:

```text
GET /api/v1/items/fndr?search=ACERO&group_id=7&subgroup_id=10&status=AC&page=1&per_page=20
```

### Paginacion

- usa paginacion clasica por `page`
- `per_page` maximo actual: `100`
- la respuesta devuelve `meta.current_page`, `meta.per_page` y `meta.total`

### Precio calculado del listado FNDR

`calculated_price` no sale directamente de `item.precio`.

Se calcula en backend usando la logica FNDR del legacy sobre los insumos activos del item.

La regla implementada es esta:

1. materiales = suma de insumos tipo `1`
2. mano de obra base = suma de insumos tipo `2`
3. cargas sociales = porcentaje FNDR sobre mano de obra base
4. IVA = porcentaje FNDR sobre mano de obra base + cargas sociales
5. herramientas base = suma de insumos tipo `3`
6. herramientas menores = porcentaje FNDR sobre el subtotal de mano de obra ajustada
7. costo directo = materiales + mano de obra ajustada + herramientas ajustadas
8. gastos generales y administrativos = porcentaje FNDR sobre costo directo
9. utilidad = porcentaje FNDR sobre costo directo + gastos generales
10. subtotal = costo directo + gastos generales + utilidad
11. IT = porcentaje FNDR sobre subtotal
12. total final = subtotal + IT

La API devuelve ese valor ya calculado en `calculated_price`.

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Items FNDR obtenidos correctamente.",
  "data": {
    "items": [
      {
        "id_item": 3,
        "name": "ACERO ESTRUCTURAL S/D",
        "calculated_price": 62.2994,
        "status": "AC",
        "status_label": "HABILITADO",
        "group": {
          "id": 7,
          "name": "2.- OBRA GRUESA",
          "code": "002-OGR"
        },
        "subgroup": {
          "id": 10,
          "description": "ESTRUCTURAS",
          "code": "EST"
        },
        "unit_measure": {
          "id": 58,
          "description": "kilogramo",
          "abbreviation": "kg"
        },
        "available_actions": {
          "edit": true,
          "materials": true,
          "labor": true,
          "machinery": true,
          "files": true,
          "price_analysis": true,
          "price_recalculation": true,
          "material_breakdown": true,
          "labor_breakdown": true,
          "tools_breakdown": true,
          "breakdown_recalculation": true
        }
      }
    ],
    "meta": {
      "current_page": 1,
      "per_page": 20,
      "total": 2486
    }
  }
}
```

### Acciones disponibles por item

El frontend no debe inferir manualmente las operaciones desde `status`.

Cada item del listado ahora devuelve tambien:

- `status_label`
- `available_actions`

Regla funcional aplicada:

- si `status = AC`
  - `status_label = HABILITADO`
  - todas las acciones operativas salen en `true`
- si `status = DC`
  - `status_label = INHABILITADO`
  - solo `edit = true`
  - todas las demas acciones salen en `false`

Esto aplica al menos a:

- `GET /api/v1/items`
- `GET /api/v1/items/fndr`
- `GET /api/v1/items/upre`
- `GET /api/v1/items/fps`
- `GET /api/v1/items/obras`
- `GET /api/v1/items/proman`

### Ejemplo de item inhabilitado

```json
{
  "id_item": 2720,
  "name": "CINTA DE ALUMINIO",
  "calculated_price": 6.7303,
  "status": "DC",
  "status_label": "INHABILITADO",
  "available_actions": {
    "edit": true,
    "materials": false,
    "labor": false,
    "machinery": false,
    "files": false,
    "price_analysis": false,
    "price_recalculation": false,
    "material_breakdown": false,
    "labor_breakdown": false,
    "tools_breakdown": false,
    "breakdown_recalculation": false
  }
}
```

### 16.3 Crear Item

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/items`
- Autenticacion: `Bearer token`

Body ejemplo:

```json
{
  "group_id": 7,
  "subgroup_id": 10,
  "item": "ACERO ESTRUCTURAL S/D",
  "unit_measure_id": 58,
  "status": "AC",
  "code": "ITM-001"
}
```

### Validaciones importantes

- `group_id` obligatorio
- `subgroup_id` obligatorio
- `item` obligatorio
- `unit_measure_id` obligatorio
- `status` obligatorio
- el subgrupo debe pertenecer al grupo seleccionado
- el item se guarda asociando `id_usuario` del autenticado
- `fecha_item` se guarda en formato PostgreSQL `Y-m-d`

### Regla de duplicados aplicada

La regla fue endurecida respecto al legacy.

Ahora no se permite repetir la combinacion:

- `group_id`
- `subgroup_id`
- `item`

Eso significa:

- mismo nombre de item en el mismo grupo y subgrupo: rechazado
- mismo nombre de item en otro subgrupo: permitido

### 16.4 Ver Analisis de Precio FNDR

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/items/{id}/price-analysis?mode=fndr`
- Autenticacion: `Bearer token`

### Para que sirve

Devuelve el analisis actual del item usando precios actuales de `insumo`.

Incluye:

- datos base del item
- materiales
- mano de obra
- herramientas
- porcentajes activos de `porcentaje_calculo_fndr`
- subtotales y total final

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Analisis de precio FNDR obtenido correctamente.",
  "data": {
    "item": {
      "id_item": 3,
      "name": "ACERO ESTRUCTURAL S/D"
    },
    "materials": [],
    "labor": [],
    "tools": [],
    "percentages": [],
    "totals": {
      "materials_total": 0,
      "labor_total": 0,
      "tools_total": 0,
      "total_price": 0
    },
    "meta": {
      "mode": "fndr",
      "source": "current_inputs",
      "reference_date": null
    }
  }
}
```

### 16.5 Recalcular Analisis FNDR por Fecha

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/items/{id}/price-recalculation?mode=fndr`
- Autenticacion: `Bearer token`

Body:

```json
{
  "fecha": "2026-04-30"
}
```

### Regla usada para el recalculo

Para cada insumo asociado al item, la API busca el ultimo `log_insumo` disponible hasta la fecha enviada.

No suma todos los logs historicos del mismo insumo.

La regla aplicada es:

- tomar un solo precio historico por insumo
- elegir el ultimo log valido `<= fecha`
- recalcular materiales, mano de obra y herramientas con ese precio historico
- volver a aplicar los porcentajes activos FNDR

### Cuando usar esta API

Usala cuando frontend necesite reproducir el comportamiento legacy de recalculo por fecha de impresion o corte historico.

### 16.6 Listar Subgrupos por Grupo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/subgroups?group_id=7`
- Autenticacion: `Bearer token`

Este modo del endpoint devuelve solo subgrupos activos del grupo solicitado y ordena por `descripcion ASC`.

### Para que sirve esta API si ya existe `subgroups_by_group` en el contexto

Hay dos formas de poblar el combo dependiente:

1. cargar todo de una vez con `context` y usar `subgroups_by_group`
2. pedir subgrupos solo cuando el usuario cambia el grupo usando `GET /api/v1/subgroups?group_id=...`

Ambas son validas.

Recomendacion practica:

- si la pantalla ya carga bastante metadata, usa `subgroups_by_group`
- si prefieres bajar menos datos al inicio, usa este endpoint bajo demanda

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Subgrupos obtenidos correctamente.",
  "data": {
    "items": [
      {
        "id": 10,
        "group_id": 7,
        "description": "ESTRUCTURAS",
        "code": "EST",
        "status": "AC"
      }
    ]
  }
}
```

## 20. Parametros Subgrupos

Estas APIs replican la pantalla administrativa `parametros/subgrupos`.

### 20.1 Listado Administrativo de Subgrupos

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/subgroups`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve:

- `id_subgrupo`
- `codigo`
- `descripcion`
- `id_grupo`
- `nombre_grupo`
- `estado`
- `status_label`
- `available_actions`

Reglas funcionales:

- hace join con `grupo`
- excluye registros con `estado = 'DP'`
- ordena por `id_subgrupo ASC`

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Subgrupos obtenidos correctamente.",
  "data": {
    "items": [
      {
        "id_subgrupo": 1,
        "codigo": "PRE",
        "descripcion": "PRELIMINARES",
        "id_grupo": 1,
        "nombre_grupo": "OBRAS PRELIMINARES",
        "estado": "AC",
        "status_label": "ACTIVO",
        "available_actions": {
          "edit": true,
          "delete": true
        }
      }
    ]
  }
}
```

### 20.2 Contexto de Pantalla

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/subgroups/context`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve:

- grupos activos para el combo
- estados `AC` y `DC`
- permisos funcionales de la pantalla

### 20.3 Crear Subgrupo

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/subgroups`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Body esperado:

```json
{
  "codigo": "PRE",
  "descripcion": "PRELIMINARES",
  "id_grupo": 1,
  "estado": "AC"
}
```

Reglas funcionales:

- `codigo`, `descripcion`, `id_grupo` y `estado` son obligatorios
- si ya existe otro subgrupo no eliminado con el mismo `codigo`, responde error funcional
- si ya existe otro subgrupo no eliminado con la misma `descripcion`, responde error funcional
- registra auditoria al crear

### 20.4 Obtener Detalle de Subgrupo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/subgroups/{id}`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve el detalle para cargar el modal o formulario de edicion, incluyendo `nombre_grupo`.

### 20.5 Editar Subgrupo

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/subgroups/{id}`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Body esperado:

```json
{
  "codigo": "PRE-01",
  "descripcion": "PRELIMINARES AJUSTADO",
  "id_grupo": 1,
  "estado": "DC"
}
```

Reglas funcionales:

- backend compara contra el registro actual
- solo valida duplicado de `codigo` si `codigo` cambio
- solo valida duplicado de `descripcion` si `descripcion` cambio
- registra auditoria al actualizar

### 20.6 Solicitar Autorizacion de Eliminacion

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/subgroups/{id}/delete-authorization-request`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Body opcional:

```json
{
  "nro_autorizacion": "AUTH-SG-001"
}
```

Registra una solicitud en `autorizaciones` con:

- `id_elemento = id_subgrupo`
- `elemento = descripcion`
- `tipo_elemento = subgrupo`
- `tabla = sub_grupo`
- `solicitante = usuario autenticado`
- `estado = PE`

### 20.7 Consultar Estado de Autorizacion

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/subgroups/{id}/delete-authorization-status`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Responde alguno de estos estados:

- `not_found`
- `pending`
- `approved`
- `not_usable`

### 20.8 Eliminar Subgrupo

- Metodo: `DELETE`
- URL: `http://localhost:8000/api/v1/subgroups/{id}`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Body esperado:

```json
{
  "autorizacion": "AUTH-SG-001"
}
```

Reglas funcionales:

- no permite eliminar si existe algun `item` activo con `item.subgrupo = id_subgrupo`
- exige una autorizacion aprobada `AP` en `autorizaciones` con `tabla = 'sub_grupo'`
- la eliminacion es logica y actualiza `sub_grupo.estado = 'DP'`
- registra auditoria al eliminar

### 20.9 Subgrupos para Combos Dependientes

Se soportan dos variantes equivalentes:

- `GET /api/v1/subgroups?group_id={groupId}`
- `GET /api/v1/subgroups/by-group/{groupId}`

Ambas devuelven subgrupos activos del grupo y ordenan por `descripcion ASC`.

## 21. Parametros Porcentaje de Calculo

Estas APIs replican la pantalla administrativa `parametros/porcentaje_calculo`.

### 21.1 Listado Administrativo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/calculation-percentages`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve:

- `id_porcentaje`
- `codigo`
- `descripcion`
- `porcentaje`
- `observacion`
- `estado`
- `status_label`
- `available_actions`

Reglas funcionales:

- usa `porcentaje_calculo`
- ordena por `id_porcentaje DESC`
- no expone eliminacion en esta pantalla
- `available_actions` solo incluye `edit`

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Porcentajes de calculo obtenidos correctamente.",
  "data": {
    "items": [
      {
        "id_porcentaje": 2,
        "codigo": "PC-002",
        "descripcion": "IVA",
        "porcentaje": 14.94,
        "observacion": "Obs 2",
        "estado": "DC",
        "status_label": "INACTIVO",
        "available_actions": {
          "edit": true
        }
      }
    ]
  }
}
```

### 21.2 Contexto de Pantalla

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/calculation-percentages/context`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve:

- estados `AC` y `DC`
- permisos funcionales de la pantalla

### 21.3 Crear Porcentaje de Calculo

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/calculation-percentages`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Body esperado:

```json
{
  "codigo": "PC-003",
  "descripcion": "HERRAMIENTAS MENORES",
  "porcentaje": 5,
  "observacion": "Observacion opcional",
  "estado": "AC"
}
```

Reglas funcionales:

- `codigo`, `descripcion`, `porcentaje` y `estado` son obligatorios
- `codigo` debe ser unico
- `descripcion` no puede duplicarse
- registra auditoria al crear

### 21.4 Obtener Detalle

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/calculation-percentages/{id}`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve el detalle del porcentaje para cargar el formulario de edicion.

### 21.5 Editar Porcentaje de Calculo

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/calculation-percentages/{id}`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Body esperado:

```json
{
  "codigo": "PC-002A",
  "descripcion": "IVA ACTUALIZADO",
  "porcentaje": 15.5,
  "observacion": "Obs editada",
  "estado": "AC"
}
```

Reglas funcionales:

- backend compara contra el registro actual
- si cambia `codigo`, revalida unicidad por `codigo`
- si cambia `descripcion`, valida duplicado por `descripcion`
- `porcentaje` sigue siendo requerido funcionalmente
- registra auditoria al actualizar

## 22. Parametros Porcentaje de Calculo UPRE

Estas APIs replican la pantalla administrativa `parametros/porcentaje_calculo_upre`.

### 22.1 Listado Administrativo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/calculation-percentages/upre`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve:

- `id_porcentaje`
- `codigo`
- `descripcion`
- `porcentaje`
- `observacion`
- `estado`
- `status_label`
- `available_actions`

Reglas funcionales:

- usa `porcentaje_calculo_upre`
- ordena por `id_porcentaje DESC`
- no expone eliminacion en esta pantalla
- `available_actions` solo incluye `edit`

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Porcentajes de calculo UPRE obtenidos correctamente.",
  "data": {
    "items": [
      {
        "id_porcentaje": 2,
        "codigo": "UPRE-002",
        "descripcion": "IVA",
        "porcentaje": 14.94,
        "observacion": "Obs 2",
        "estado": "DC",
        "status_label": "INACTIVO",
        "available_actions": {
          "edit": true
        }
      }
    ]
  }
}
```

### 22.2 Contexto de Pantalla

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/calculation-percentages/upre/context`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve:

- estados `AC` y `DC`
- permisos funcionales de la pantalla

### 22.3 Crear Porcentaje de Calculo UPRE

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/calculation-percentages/upre`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Body esperado:

```json
{
  "codigo": "UPRE-003",
  "descripcion": "HERRAMIENTAS MENORES",
  "porcentaje": 5,
  "observacion": "Observacion opcional",
  "estado": "AC"
}
```

Reglas funcionales:

- `codigo`, `descripcion`, `porcentaje` y `estado` son obligatorios
- `codigo` debe ser unico
- `descripcion` no puede duplicarse
- registra auditoria al crear

### Decision de compatibilidad sobre unicidad de `codigo`

En el legacy se observaba validacion de unicidad contra `porcentaje_calculo.codigo` incluso para el modulo UPRE.

En esta API nueva se normalizo la regla para validar unicidad dentro de `porcentaje_calculo_upre.codigo`, porque:

- el alta y la edicion operan sobre `porcentaje_calculo_upre`
- evita rechazos cruzados entre modulos distintos
- hace consistente la regla con la tabla realmente administrada por esta pantalla

### 22.4 Obtener Detalle

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/calculation-percentages/upre/{id}`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve el detalle del porcentaje UPRE para cargar el formulario de edicion.

### 22.5 Editar Porcentaje de Calculo UPRE

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/calculation-percentages/upre/{id}`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Body esperado:

```json
{
  "codigo": "UPRE-002A",
  "descripcion": "IVA ACTUALIZADO",
  "porcentaje": 15.5,
  "observacion": "Obs editada",
  "estado": "AC"
}
```

Reglas funcionales:

- backend compara contra el registro actual
- si cambia `codigo`, revalida unicidad por `codigo` dentro de `porcentaje_calculo_upre`
- si cambia `descripcion`, valida duplicado por `descripcion`
- `porcentaje` sigue siendo requerido funcionalmente
- registra auditoria al actualizar

## 23. Parametros Porcentaje de Calculo FPS

Estas APIs replican la pantalla administrativa `parametros/porcentaje_calculo_fps`.

### 23.1 Listado Administrativo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/calculation-percentages/fps`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve:

- `id_porcentaje`
- `codigo`
- `descripcion`
- `porcentaje`
- `observacion`
- `estado`
- `status_label`
- `available_actions`

Reglas funcionales:

- usa `porcentaje_calculo_fps`
- ordena por `id_porcentaje DESC`
- no expone eliminacion en esta pantalla
- `available_actions` solo incluye `edit`

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Porcentajes de calculo FPS obtenidos correctamente.",
  "data": {
    "items": [
      {
        "id_porcentaje": 2,
        "codigo": "FPS-002",
        "descripcion": "IVA",
        "porcentaje": 14.94,
        "observacion": "Obs 2",
        "estado": "DC",
        "status_label": "INACTIVO",
        "available_actions": {
          "edit": true
        }
      }
    ]
  }
}
```

### 23.2 Contexto de Pantalla

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/calculation-percentages/fps/context`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve:

- estados `AC` y `DC`
- permisos funcionales de la pantalla

### 23.3 Crear Porcentaje de Calculo FPS

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/calculation-percentages/fps`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Body esperado:

```json
{
  "codigo": "FPS-003",
  "descripcion": "HERRAMIENTAS MENORES",
  "porcentaje": 5,
  "observacion": "Observacion opcional",
  "estado": "AC"
}
```

Reglas funcionales:

- `codigo`, `descripcion`, `porcentaje` y `estado` son obligatorios
- `codigo` debe ser unico
- `descripcion` no puede duplicarse
- registra auditoria al crear

### Decision de compatibilidad sobre unicidad de `codigo`

En el legacy se observaba validacion de unicidad contra `porcentaje_calculo.codigo` incluso para el modulo FPS.

En esta API nueva se normalizo la regla para validar unicidad dentro de `porcentaje_calculo_fps.codigo`, porque:

- el alta y la edicion operan sobre `porcentaje_calculo_fps`
- evita rechazos cruzados entre modulos distintos
- hace consistente la regla con la tabla realmente administrada por esta pantalla

### 23.4 Obtener Detalle

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/calculation-percentages/fps/{id}`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve el detalle del porcentaje FPS para cargar el formulario de edicion.

### 23.5 Editar Porcentaje de Calculo FPS

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/calculation-percentages/fps/{id}`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Body esperado:

```json
{
  "codigo": "FPS-002A",
  "descripcion": "IVA ACTUALIZADO",
  "porcentaje": 15.5,
  "observacion": "Obs editada",
  "estado": "AC"
}
```

Reglas funcionales:

- backend compara contra el registro actual
- si cambia `codigo`, revalida unicidad por `codigo` dentro de `porcentaje_calculo_fps`
- si cambia `descripcion`, valida duplicado por `descripcion`
- `porcentaje` sigue siendo requerido funcionalmente
- registra auditoria al actualizar

## 24. Parametros Porcentaje de Calculo FNDR

Estas APIs replican la pantalla administrativa `parametros/porcentaje_calculo_fndr`.

### 24.1 Listado Administrativo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/calculation-percentages/fndr`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve:

- `id_porcentaje`
- `codigo`
- `descripcion`
- `porcentaje`
- `observacion`
- `estado`
- `status_label`
- `available_actions`

Reglas funcionales:

- usa `porcentaje_calculo_fndr`
- ordena por `id_porcentaje DESC`
- no expone eliminacion en esta pantalla
- `available_actions` solo incluye `edit`

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Porcentajes de calculo FNDR obtenidos correctamente.",
  "data": {
    "items": [
      {
        "id_porcentaje": 2,
        "codigo": "FNDR-002",
        "descripcion": "IVA",
        "porcentaje": 10,
        "observacion": "Obs 2",
        "estado": "DC",
        "status_label": "INACTIVO",
        "available_actions": {
          "edit": true
        }
      }
    ]
  }
}
```

### 24.2 Contexto de Pantalla

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/calculation-percentages/fndr/context`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve:

- estados `AC` y `DC`
- permisos funcionales de la pantalla

### 24.3 Crear Porcentaje de Calculo FNDR

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/calculation-percentages/fndr`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Body esperado:

```json
{
  "codigo": "FNDR-003",
  "descripcion": "HERRAMIENTAS MENORES",
  "porcentaje": 5,
  "observacion": "Observacion opcional",
  "estado": "AC"
}
```

Reglas funcionales:

- `codigo`, `descripcion`, `porcentaje` y `estado` son obligatorios
- `codigo` debe ser unico
- `descripcion` no puede duplicarse
- registra auditoria al crear

### Decision de compatibilidad sobre unicidad de `codigo`

En el legacy se observaba validacion de unicidad contra `porcentaje_calculo.codigo` incluso para el modulo FNDR.

En esta API nueva se normalizo la regla para validar unicidad dentro de `porcentaje_calculo_fndr.codigo`, porque:

- el alta y la edicion operan sobre `porcentaje_calculo_fndr`
- evita rechazos cruzados entre modulos distintos
- hace consistente la regla con la tabla realmente administrada por esta pantalla

### 24.4 Obtener Detalle

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/calculation-percentages/fndr/{id}`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve el detalle del porcentaje FNDR para cargar el formulario de edicion.

### 24.5 Editar Porcentaje de Calculo FNDR

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/calculation-percentages/fndr/{id}`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Body esperado:

```json
{
  "codigo": "FNDR-002A",
  "descripcion": "IVA ACTUALIZADO",
  "porcentaje": 15.5,
  "observacion": "Obs editada",
  "estado": "AC"
}
```

Reglas funcionales:

- backend compara contra el registro actual
- si cambia `codigo`, revalida unicidad por `codigo` dentro de `porcentaje_calculo_fndr`
- si cambia `descripcion`, valida duplicado por `descripcion`
- `porcentaje` sigue siendo requerido funcionalmente
- registra auditoria al actualizar

## 25. Parametros Porcentaje de Calculo Obras

Estas APIs replican la pantalla administrativa `parametros/porcentaje_calculo_obras`.

### 25.1 Listado Administrativo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/calculation-percentages/obras`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve:

- `id_porcentaje`
- `codigo`
- `descripcion`
- `porcentaje`
- `observacion`
- `estado`
- `status_label`
- `available_actions`

Reglas funcionales:

- usa `porcentaje_calculo_obras`
- ordena por `id_porcentaje DESC`
- no expone eliminacion en esta pantalla
- `available_actions` solo incluye `edit`

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Porcentajes de calculo Obras obtenidos correctamente.",
  "data": {
    "items": [
      {
        "id_porcentaje": 2,
        "codigo": "OBR-002",
        "descripcion": "IVA",
        "porcentaje": 0,
        "observacion": "Obs 2",
        "estado": "DC",
        "status_label": "INACTIVO",
        "available_actions": {
          "edit": true
        }
      }
    ]
  }
}
```

### 25.2 Contexto de Pantalla

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/calculation-percentages/obras/context`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve:

- estados `AC` y `DC`
- permisos funcionales de la pantalla

### 25.3 Crear Porcentaje de Calculo Obras

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/calculation-percentages/obras`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Body esperado:

```json
{
  "codigo": "OBR-003",
  "descripcion": "HERRAMIENTAS MENORES",
  "porcentaje": 5,
  "observacion": "Observacion opcional",
  "estado": "AC"
}
```

Reglas funcionales:

- `codigo`, `descripcion`, `porcentaje` y `estado` son obligatorios
- `codigo` debe ser unico
- `descripcion` no puede duplicarse
- registra auditoria al crear

### Decision de compatibilidad sobre unicidad de `codigo`

En el legacy se observaba validacion de unicidad contra `porcentaje_calculo.codigo` incluso para el modulo Obras.

En esta API nueva se normalizo la regla para validar unicidad dentro de `porcentaje_calculo_obras.codigo`, porque:

- el alta y la edicion operan sobre `porcentaje_calculo_obras`
- evita rechazos cruzados entre modulos distintos
- hace consistente la regla con la tabla realmente administrada por esta pantalla

### 25.4 Obtener Detalle

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/calculation-percentages/obras/{id}`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve el detalle del porcentaje Obras para cargar el formulario de edicion.

### 25.5 Editar Porcentaje de Calculo Obras

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/calculation-percentages/obras/{id}`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Body esperado:

```json
{
  "codigo": "OBR-002A",
  "descripcion": "IVA ACTUALIZADO",
  "porcentaje": 15.5,
  "observacion": "Obs editada",
  "estado": "AC"
}
```

Reglas funcionales:

- backend compara contra el registro actual
- si cambia `codigo`, revalida unicidad por `codigo` dentro de `porcentaje_calculo_obras`
- si cambia `descripcion`, valida duplicado por `descripcion`
- `porcentaje` sigue siendo requerido funcionalmente
- registra auditoria al actualizar

## 26. Parametros Porcentaje de Calculo PROMAN

Estas APIs replican la pantalla administrativa `parametros/porcentaje_calculo_proman`.

### 26.1 Listado Administrativo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/calculation-percentages/proman`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve:

- `id_porcentaje`
- `codigo`
- `descripcion`
- `porcentaje`
- `observacion`
- `estado`
- `status_label`
- `available_actions`

Reglas funcionales:

- usa `porcentaje_calculo_proman`
- ordena por `id_porcentaje DESC`
- no expone eliminacion en esta pantalla
- `available_actions` solo incluye `edit`

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Porcentajes de calculo PROMAN obtenidos correctamente.",
  "data": {
    "items": [
      {
        "id_porcentaje": 2,
        "codigo": "PROM-002",
        "descripcion": "IVA",
        "porcentaje": 0,
        "observacion": "Obs 2",
        "estado": "DC",
        "status_label": "INACTIVO",
        "available_actions": {
          "edit": true
        }
      }
    ]
  }
}
```

### 26.2 Contexto de Pantalla

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/calculation-percentages/proman/context`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve:

- estados `AC` y `DC`
- permisos funcionales de la pantalla

### 26.3 Crear Porcentaje de Calculo PROMAN

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/calculation-percentages/proman`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Body esperado:

```json
{
  "codigo": "PROM-003",
  "descripcion": "CARGAS SOCIALES",
  "porcentaje": 57,
  "observacion": "Observacion opcional",
  "estado": "AC"
}
```

Reglas funcionales:

- `codigo`, `descripcion`, `porcentaje` y `estado` son obligatorios
- `codigo` debe ser unico
- `descripcion` no puede duplicarse
- registra auditoria al crear

### Decision de compatibilidad sobre unicidad de `codigo`

En el legacy se observaba validacion de unicidad contra `porcentaje_calculo.codigo` incluso para el modulo PROMAN.

En esta API nueva se normalizo la regla para validar unicidad dentro de `porcentaje_calculo_proman.codigo`, porque:

- el alta y la edicion operan sobre `porcentaje_calculo_proman`
- evita rechazos cruzados entre modulos distintos
- hace consistente la regla con la tabla realmente administrada por esta pantalla

### 26.4 Obtener Detalle

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/calculation-percentages/proman/{id}`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Devuelve el detalle del porcentaje PROMAN para cargar el formulario de edicion.

### 26.5 Editar Porcentaje de Calculo PROMAN

- Metodo: `PUT`
- URL: `http://localhost:8000/api/v1/calculation-percentages/proman/{id}`
- Autenticacion: `Bearer token`
- Permiso actual: administrador

Body esperado:

```json
{
  "codigo": "PROM-002A",
  "descripcion": "IVA ACTUALIZADO",
  "porcentaje": 1.5,
  "observacion": "Obs editada",
  "estado": "AC"
}
```

Reglas funcionales:

- backend compara contra el registro actual
- si cambia `codigo`, revalida unicidad por `codigo` dentro de `porcentaje_calculo_proman`
- si cambia `descripcion`, valida duplicado por `descripcion`
- `porcentaje` sigue siendo requerido funcionalmente
- registra auditoria al actualizar

### 16.7 Composicion Operativa del Item

Estas APIs permiten replicar los modales y pantallas operativas de composicion del item por bloque.

#### Contexto del item

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/items/{id}/composition/context`

Devuelve:

- datos base del item
- unidad
- grupo
- subgrupo
- estado
- precio actual
- permisos funcionales
- `available_actions`
- metadata operativa como `can_edit`, `can_add_inputs`, `can_recalculate`

#### Composicion completa

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/items/{id}/composition`

Devuelve:

- `materials`
- `labor`
- `machinery`
- `totals.materials`
- `totals.labor`
- `totals.machinery`
- `totals.global`

#### Materiales

- `GET /api/v1/items/{id}/materials`
- `POST /api/v1/items/{id}/materials`
- `PUT /api/v1/items/{id}/materials/{itemInputId}`
- `DELETE /api/v1/items/{id}/materials/{itemInputId}`
- `GET /api/v1/items/{id}/materials/total`

Body minimo para agregar:

```json
{
  "id_insumo": 1,
  "cantidad": 2
}
```

#### Mano de obra

- `GET /api/v1/items/{id}/labor`
- `POST /api/v1/items/{id}/labor`
- `PUT /api/v1/items/{id}/labor/{itemInputId}`
- `DELETE /api/v1/items/{id}/labor/{itemInputId}`
- `GET /api/v1/items/{id}/labor/total`

#### Maquinaria / herramienta

- `GET /api/v1/items/{id}/machinery`
- `POST /api/v1/items/{id}/machinery`
- `PUT /api/v1/items/{id}/machinery/{itemInputId}`
- `DELETE /api/v1/items/{id}/machinery/{itemInputId}`
- `GET /api/v1/items/{id}/machinery/total`

#### Total global del item

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/items/{id}/total`

#### Regla aplicada al agregar duplicados

Si el mismo insumo ya existe relacionado al item:

- no se crea una fila duplicada nueva
- se actualiza la relacion existente con la nueva `cantidad`
- se mantiene una sola relacion activa por item + insumo

#### Precio unitario y parcial

- el precio unitario usado por defecto sale del `insumo` actual
- `parcial = cantidad * precio_unitario`
- en la implementacion actual la API permite editar `cantidad`
- no se persiste un precio unitario manual por separado en `item_insumo`

#### Restriccion por estado del item

Si el item esta `DC`:

- no permite agregar materiales
- no permite agregar mano de obra
- no permite agregar maquinaria
- no permite recalcular
- si permite consulta del contexto y composicion

#### Busqueda de insumos para selects por tipo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/search/inputs?type=1`

Tipos esperados:

- `1` material
- `2` mano de obra
- `3` maquinaria / herramienta

La respuesta devuelve:

- `id`
- `text`
- `precio`
- `tipo`

#### Analisis general del item sin modo explicito

- `GET /api/v1/items/{id}/price-analysis`
- `POST /api/v1/items/{id}/price-recalculation`
- `POST /api/v1/items/{id}/breakdowns/recalculate`

Para compatibilidad, si no se envia `mode`, backend usa `general` por defecto.

### 16.8 Acciones disponibles por item

Cada item listado y el contexto de composicion devuelven acciones explicitas.

- si `status = AC`
  - `edit = true`
  - `materials = true`
  - `labor = true`
  - `machinery = true`
  - `files = true`
  - `price_analysis = true`
  - `price_recalculation = true`
  - `material_breakdown = true`
  - `labor_breakdown = true`
  - `tools_breakdown = true`
  - `breakdown_recalculation = true`
- si `status = DC`
  - `edit = true`
  - todas las demas acciones en `false`

Tambien se devuelve `status_label`:

- `AC` -> `HABILITADO`
- `DC` -> `INHABILITADO`

## 17. Items UPRE

Estas APIs reemplazan la logica de la pantalla legacy `items/upre`.

La estructura general es la misma que FNDR, pero el modo `upre` cambia la fuente de porcentajes y por tanto el resultado del `calculated_price` y del analisis/recalculo.

### Diferencia respecto a FNDR

La diferencia principal esta en la tabla de porcentajes usada por backend:

- FNDR usa `porcentaje_calculo_fndr`
- UPRE usa `porcentaje_calculo_upre`

La formula base del analisis es la misma familia de calculo, pero los porcentajes activos del modo UPRE son distintos y eso cambia el total final.

### 17.1 Contexto UPRE

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/items/upre/context`
- Autenticacion: `Bearer token`

### Para que sirve

Sirve para cargar toda la pantalla UPRE con una sola llamada inicial.

Devuelve:

- grupos activos
- subgrupos activos
- `subgroups_by_group`
- estados disponibles
- unidades de medida activas
- permisos funcionales del usuario autenticado
- metadata de la pantalla y endpoints relacionados

### Como usarla desde frontend

1. Llamar a `GET /api/v1/items/upre/context` al entrar a la pantalla.
2. Usar `groups` para el combo principal.
3. Usar `subgroups_by_group[groupId]` para poblar subgrupos localmente, o usar `GET /api/v1/subgroups?group_id=...` bajo demanda.
4. Leer `permissions` para habilitar botones y acciones.
5. Consumir el listado con `GET /api/v1/items/upre`.

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Contexto UPRE obtenido correctamente.",
  "data": {
    "groups": [],
    "subgroups": [],
    "subgroups_by_group": {},
    "statuses": [
      { "code": "AC", "label": "ACTIVO" },
      { "code": "DC", "label": "INACTIVO" }
    ],
    "unit_measures": [],
    "permissions": {
      "can_view": true,
      "can_create": true,
      "can_view_price_analysis": true,
      "can_recalculate": true
    },
    "meta": {
      "screen": "items/upre",
      "mode": "upre",
      "price_formula": "UPRE"
    }
  }
}
```

### 17.2 Listar Items UPRE

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/items/upre`
- Autenticacion: `Bearer token`

### Filtros soportados

- `search`
- `group_id`
- `subgroup_id`
- `status`
- `page`
- `per_page`

Ejemplo:

```text
GET /api/v1/items/upre?search=ACERO&group_id=7&subgroup_id=10&status=AC&page=1&per_page=20
```

### Paginacion

- usa `page` y `per_page`
- `per_page` maximo actual: `100`
- responde con `meta.current_page`, `meta.per_page` y `meta.total`

### Precio calculado del listado UPRE

`calculated_price` no sale directamente de `item.precio`.

Se calcula en backend usando los insumos activos del item y los porcentajes de `porcentaje_calculo_upre`.

La secuencia aplicada es:

1. materiales = suma de insumos tipo `1`
2. mano de obra base = suma de insumos tipo `2`
3. cargas sociales = porcentaje UPRE sobre mano de obra base
4. IVA = porcentaje UPRE sobre mano de obra base + cargas sociales
5. herramientas base = suma de insumos tipo `3`
6. herramientas menores = porcentaje UPRE sobre mano de obra ajustada
7. costo directo = materiales + mano de obra ajustada + herramientas ajustadas
8. gastos generales = porcentaje UPRE sobre costo directo
9. utilidad = porcentaje UPRE sobre costo directo + gastos generales
10. subtotal = costo directo + gastos generales + utilidad
11. IT = porcentaje UPRE sobre subtotal
12. total final = subtotal + IT

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Items UPRE obtenidos correctamente.",
  "data": {
    "items": [
      {
        "id_item": 3,
        "name": "ACERO ESTRUCTURAL S/D",
        "calculated_price": 56.1033,
        "status": "AC",
        "group": {
          "id": 7,
          "name": "2.- OBRA GRUESA",
          "code": "002-OGR"
        },
        "subgroup": {
          "id": 10,
          "description": "ESTRUCTURAS",
          "code": "EST"
        },
        "unit_measure": {
          "id": 58,
          "description": "kilogramo",
          "abbreviation": "kg"
        }
      }
    ],
    "meta": {
      "current_page": 1,
      "per_page": 20,
      "total": 2486
    }
  }
}
```

### 17.3 Crear Item

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/items`
- Autenticacion: `Bearer token`

Se reutiliza exactamente la misma API de creacion de items usada por FNDR.

Reglas importantes:

- `group_id` obligatorio
- `subgroup_id` obligatorio
- `item` obligatorio
- `unit_measure_id` obligatorio
- `status` obligatorio
- el subgrupo debe pertenecer al grupo seleccionado
- se asocia `id_usuario` del autenticado
- `fecha_item` se guarda en formato PostgreSQL

### Regla de duplicados

Se aplica la misma regla endurecida:

- no se permite repetir `group_id + subgroup_id + item`

### 17.4 Ver Analisis de Precio UPRE

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/items/{id}/price-analysis?mode=upre`
- Autenticacion: `Bearer token`

### Para que sirve

Devuelve el analisis actual del item usando precios actuales de `insumo` y porcentajes de `porcentaje_calculo_upre`.

Incluye:

- datos base del item
- materiales
- mano de obra
- herramientas
- porcentajes activos UPRE
- subtotales y total final

### 17.5 Recalcular Analisis UPRE por Fecha

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/items/{id}/price-recalculation?mode=upre`
- Autenticacion: `Bearer token`

Body:

```json
{
  "fecha": "2026-04-30"
}
```

### Regla usada para el recalculo

Para cada insumo del item, backend busca el ultimo `log_insumo` valido hasta la fecha indicada y usa ese precio historico para recalcular el analisis completo.

No suma todos los logs del mismo insumo.

La regla es:

- un solo precio historico por insumo
- el ultimo `log_insumo` con `fecha <= fecha enviada`
- recalculo completo con porcentajes de `porcentaje_calculo_upre`

### 17.6 Listar Subgrupos por Grupo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/subgroups?group_id=7`
- Autenticacion: `Bearer token`

Esta API es compartida por FNDR y UPRE.

Sirve para el combo dependiente cuando no quieras cargar todos los subgrupos en memoria desde el contexto.

## 18. Items FPS

Estas APIs reemplazan la logica de la pantalla legacy `items/fps`.

El comportamiento general es el mismo que en FNDR y UPRE, pero el modo `fps` usa su propia tabla de porcentajes y por eso cambia el resultado de `calculated_price` y del analisis/recalculo.

### Diferencia respecto a otros modos

La diferencia principal es la fuente de porcentajes:

- FNDR usa `porcentaje_calculo_fndr`
- UPRE usa `porcentaje_calculo_upre`
- FPS usa `porcentaje_calculo_fps`

### 18.1 Contexto FPS

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/items/fps/context`
- Autenticacion: `Bearer token`

### Para que sirve

Sirve para cargar toda la pantalla FPS con una sola llamada inicial.

Devuelve:

- grupos activos
- subgrupos activos
- `subgroups_by_group`
- estados disponibles
- unidades de medida activas
- permisos funcionales del usuario autenticado
- metadata de la pantalla y endpoints relacionados

### Como usarla desde frontend

1. Llamar a `GET /api/v1/items/fps/context` al entrar a la pantalla.
2. Usar `groups` para el combo principal.
3. Usar `subgroups_by_group[groupId]` para resolver subgrupos localmente, o usar `GET /api/v1/subgroups?group_id=...` si quieres carga bajo demanda.
4. Leer `permissions` para habilitar acciones.
5. Consumir el listado con `GET /api/v1/items/fps`.

### 18.2 Listar Items FPS

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/items/fps`
- Autenticacion: `Bearer token`

### Filtros soportados

- `search`
- `group_id`
- `subgroup_id`
- `status`
- `page`
- `per_page`

Ejemplo:

```text
GET /api/v1/items/fps?search=ACERO&group_id=7&subgroup_id=10&status=AC&page=1&per_page=20
```

### Precio calculado del listado FPS

`calculated_price` se calcula en backend usando los insumos activos del item y los porcentajes activos de `porcentaje_calculo_fps`.

La secuencia aplicada es:

1. materiales = suma de insumos tipo `1`
2. mano de obra base = suma de insumos tipo `2`
3. cargas sociales = porcentaje FPS sobre mano de obra base
4. IVA = porcentaje FPS sobre mano de obra base + cargas sociales
5. herramientas base = suma de insumos tipo `3`
6. herramientas menores = porcentaje FPS sobre mano de obra ajustada
7. costo directo = materiales + mano de obra ajustada + herramientas ajustadas
8. gastos generales = porcentaje FPS sobre costo directo
9. utilidad = porcentaje FPS sobre costo directo + gastos generales
10. subtotal = costo directo + gastos generales + utilidad
11. IT = porcentaje FPS sobre subtotal
12. total final = subtotal + IT

### Ejemplo de respuesta

```json
{
  "success": true,
  "message": "Items FPS obtenidos correctamente.",
  "data": {
    "items": [
      {
        "id_item": 3,
        "name": "ACERO ESTRUCTURAL S/D",
        "calculated_price": 61.9885,
        "status": "AC",
        "status_label": "HABILITADO",
        "group": {
          "id": 7,
          "name": "2.- OBRA GRUESA",
          "code": "002-OGR"
        },
        "subgroup": {
          "id": 10,
          "description": "ESTRUCTURAS",
          "code": "EST"
        },
        "unit_measure": {
          "id": 58,
          "description": "kilogramo",
          "abbreviation": "kg"
        },
        "available_actions": {
          "edit": true,
          "materials": true,
          "labor": true,
          "machinery": true,
          "files": true,
          "price_analysis": true,
          "price_recalculation": true,
          "material_breakdown": true,
          "labor_breakdown": true,
          "tools_breakdown": true,
          "breakdown_recalculation": true
        }
      }
    ],
    "meta": {
      "current_page": 1,
      "per_page": 20,
      "total": 2486
    }
  }
}
```

### Acciones disponibles por item

El frontend no debe inferir manualmente las operaciones desde `status`.

Cada item del listado ahora devuelve tambien:

- `status_label`
- `available_actions`

Regla funcional aplicada:

- si `status = AC`
  - `status_label = HABILITADO`
  - todas las acciones operativas salen en `true`
- si `status = DC`
  - `status_label = INHABILITADO`
  - solo `edit = true`
  - todas las demas acciones salen en `false`

Esto aplica al menos a:

- `GET /api/v1/items`
- `GET /api/v1/items/fndr`
- `GET /api/v1/items/upre`
- `GET /api/v1/items/fps`
- `GET /api/v1/items/obras`
- `GET /api/v1/items/proman`

### Ejemplo de item inhabilitado

```json
{
  "id_item": 2720,
  "name": "CINTA DE ALUMINIO",
  "calculated_price": 6.7303,
  "status": "DC",
  "status_label": "INHABILITADO",
  "available_actions": {
    "edit": true,
    "materials": false,
    "labor": false,
    "machinery": false,
    "files": false,
    "price_analysis": false,
    "price_recalculation": false,
    "material_breakdown": false,
    "labor_breakdown": false,
    "tools_breakdown": false,
    "breakdown_recalculation": false
  }
}
```

### 16.3 Crear Item

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/items`
- Autenticacion: `Bearer token`

Se reutiliza exactamente la misma API de creacion de items usada por FNDR y UPRE.

Reglas importantes:

- `group_id` obligatorio
- `subgroup_id` obligatorio
- `item` obligatorio
- `unit_measure_id` obligatorio
- `status` obligatorio
- el subgrupo debe pertenecer al grupo seleccionado
- se asocia `id_usuario` del autenticado
- `fecha_item` se guarda en formato PostgreSQL

### Regla de duplicados

Se aplica la misma regla endurecida:

- no se permite repetir `group_id + subgroup_id + item`

### 18.4 Ver Analisis de Precio FPS

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/items/{id}/price-analysis?mode=fps`
- Autenticacion: `Bearer token`

### Para que sirve

Devuelve el analisis actual del item usando precios actuales de `insumo` y porcentajes de `porcentaje_calculo_fps`.

Incluye:

- datos base del item
- materiales
- mano de obra
- herramientas
- porcentajes activos FPS
- subtotales y total final

### 18.5 Recalcular Analisis FPS por Fecha

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/items/{id}/price-recalculation?mode=fps`
- Autenticacion: `Bearer token`

Body:

```json
{
  "fecha": "2026-04-30"
}
```

### Regla usada para el recalculo

Para cada insumo del item, backend busca el ultimo `log_insumo` valido hasta la fecha indicada y usa ese precio historico para recalcular el analisis completo.

No suma todos los logs del mismo insumo.

La regla es:

- un solo precio historico por insumo
- el ultimo `log_insumo` con `fecha <= fecha enviada`
- recalculo completo con porcentajes de `porcentaje_calculo_fps`

### 18.6 Listar Subgrupos por Grupo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/subgroups?group_id=7`
- Autenticacion: `Bearer token`

Esta API es compartida por FNDR, UPRE y FPS.

Sirve para el combo dependiente cuando no quieras cargar todos los subgrupos en memoria desde el contexto.

## 19. Items PROMAN

Estas APIs reemplazan la logica de la pantalla legacy `items/proman`.

La estructura funcional es la misma familia de `items/fndr`, `items/upre`, `items/fps` y `items/obras`, pero el modo `proman` usa su propia tabla de porcentajes.

### Fuente de porcentajes

PROMAN usa exclusivamente:

- `porcentaje_calculo_proman`

No mezcla:

- `porcentaje_calculo`
- `porcentaje_calculo_fndr`
- `porcentaje_calculo_upre`
- `porcentaje_calculo_fps`
- `porcentaje_calculo_obras`

### 19.1 Contexto PROMAN

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/items/proman/context`
- Autenticacion: `Bearer token`

### Para que sirve

Sirve para cargar toda la pantalla PROMAN con una sola llamada inicial.

Devuelve:

- grupos activos
- subgrupos activos
- `subgroups_by_group`
- estados disponibles
- unidades de medida activas
- permisos funcionales del usuario autenticado
- metadata de la pantalla y endpoints relacionados

### Como usarla desde frontend

1. Llamar a `GET /api/v1/items/proman/context` al entrar a la pantalla.
2. Usar `groups` para el combo principal.
3. Usar `subgroups_by_group[groupId]` para resolver subgrupos localmente, o `GET /api/v1/subgroups?group_id=...` si prefieres carga bajo demanda.
4. Leer `permissions` para habilitar acciones.
5. Consumir el listado con `GET /api/v1/items/proman`.

### 19.2 Listar Items PROMAN

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/items/proman`
- Autenticacion: `Bearer token`

### Filtros soportados

- `search`
- `group_id`
- `subgroup_id`
- `status`
- `page`
- `per_page`

Ejemplo:

```text
GET /api/v1/items/proman?search=ACERO&group_id=7&subgroup_id=10&status=AC&page=1&per_page=20
```

### Orden exacto aplicado en el listado

Se respeta exactamente el orden legacy:

1. `grupo.nombre_grupo ASC`
2. `sub_grupo.descripcion ASC`
3. `item.item ASC`
4. `item.id_item ASC`

### Precio calculado del listado PROMAN

`calculated_price` se calcula en backend usando los insumos activos del item y los porcentajes activos de `porcentaje_calculo_proman`.

La secuencia aplicada es:

1. materiales = suma de insumos tipo `1`
2. mano de obra base = suma de insumos tipo `2`
3. cargas sociales = porcentaje PROMAN sobre mano de obra base
4. IVA = porcentaje PROMAN sobre mano de obra base + cargas sociales
5. herramientas base = suma de insumos tipo `3`
6. herramientas menores = porcentaje PROMAN sobre mano de obra ajustada
7. costo directo = materiales + mano de obra ajustada + herramientas ajustadas
8. gastos generales = porcentaje PROMAN sobre costo directo
9. utilidad = porcentaje PROMAN sobre costo directo + gastos generales
10. subtotal = costo directo + gastos generales + utilidad
11. IT = porcentaje PROMAN sobre subtotal
12. total final = subtotal + IT

### 19.3 Crear Item

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/items`
- Autenticacion: `Bearer token`

Se reutiliza exactamente la misma API de creación de items usada por los demás modos.

Reglas importantes:

- `group_id` obligatorio
- `subgroup_id` obligatorio
- `item` obligatorio
- `unit_measure_id` obligatorio
- `status` obligatorio
- el subgrupo debe pertenecer al grupo seleccionado
- se asocia `id_usuario` del autenticado
- `fecha_item` se guarda en formato PostgreSQL `Y-m-d`

### Duplicados

Se mantiene la regla endurecida aplicada al nuevo backend:

- no se permite repetir `group_id + subgroup_id + item`

### 19.4 Ver Analisis de Precio PROMAN

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/items/{id}/price-analysis?mode=proman`
- Autenticacion: `Bearer token`

### Para que sirve

Devuelve el análisis actual del item usando precios actuales de `insumo` y porcentajes de `porcentaje_calculo_proman`.

Incluye:

- datos base del item
- materiales
- mano de obra
- herramientas
- porcentajes activos PROMAN
- subtotales y total final

### Orden exacto aplicado en el análisis

Cada bloque de materiales, mano de obra y herramientas se devuelve respetando el orden legacy efectivo:

1. `nombre_grupo ASC`
2. `subgrupo ASC`

### 19.5 Recalcular Analisis PROMAN por Fecha

- Metodo: `POST`
- URL: `http://localhost:8000/api/v1/items/{id}/price-recalculation?mode=proman`
- Autenticacion: `Bearer token`

Body:

```json
{
  "fecha": "2026-04-30"
}
```

### Regla usada para el recálculo

Para cada insumo del item, backend busca el último `log_insumo` válido hasta la fecha indicada y usa ese precio histórico para recalcular el análisis completo.

No suma todos los logs del mismo insumo.

### Orden exacto aplicado en el recálculo

Se respeta exactamente el orden legacy:

1. `id_insumo DESC`
2. `id_log DESC`

### 19.6 Listar Subgrupos por Grupo

- Metodo: `GET`
- URL: `http://localhost:8000/api/v1/subgroups?group_id=7`
- Autenticacion: `Bearer token`

Esta API es compartida por `general`, `fndr`, `upre`, `fps`, `obras` y `proman`.

Sirve para el combo dependiente cuando no quieras cargar todos los subgrupos en memoria desde el contexto.

7. Si el usuario desea actualizar su contrasena, llamar a `PUT /api/v1/profile/password` o `POST /api/v1/auth/change-password` con el mismo token.
8. Cuando el usuario termine, llamar a `POST /api/v1/auth/logout` con el mismo token.

## Endpoints Aun No Implementados

Estos endpoints estan definidos en el plan del proyecto, pero no existen todavia en el backend actual:

- `POST /api/v1/auth/refresh`

## Notas

- La autenticacion actual usa Sanctum con token Bearer.
- No se esta usando JWT ni refresh token por ahora.
- La base funcional real del sistema se restaura desde el dump PostgreSQL legacy.
