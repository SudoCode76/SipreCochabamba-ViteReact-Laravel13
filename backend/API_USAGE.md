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

## 6. Ver Permisos de un Rol

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

## 7. Actualizar Permisos de un Rol

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

## 8. Ver Matriz de Permisos

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

## Flujo Recomendado de Uso

1. Verificar que el backend esta disponible con `GET /api/health`.
2. Iniciar sesion con `POST /api/v1/auth/login`.
3. Guardar el valor de `data.token`.
4. Enviar ese token como `Bearer` para consumir `GET /api/v1/auth/me`.
5. Si necesitas inspeccionar la matriz completa de permisos, consumir `GET /api/v1/permissions/matrix`.
6. Si necesitas ver o actualizar permisos de un rol, usar `GET` o `PUT /api/v1/roles/{role}/permissions`.
7. Si el usuario desea actualizar su contrasena, llamar a `POST /api/v1/auth/change-password` con el mismo token.
8. Cuando el usuario termine, llamar a `POST /api/v1/auth/logout` con el mismo token.

## Endpoints Aun No Implementados

Estos endpoints estan definidos en el plan del proyecto, pero no existen todavia en el backend actual:

- `POST /api/v1/auth/refresh`

## Notas

- La autenticacion actual usa Sanctum con token Bearer.
- No se esta usando JWT ni refresh token por ahora.
- La base funcional real del sistema se restaura desde el dump PostgreSQL legacy.
