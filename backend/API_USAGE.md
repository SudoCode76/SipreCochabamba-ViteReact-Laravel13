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

## Flujo Recomendado de Uso

1. Verificar que el backend esta disponible con `GET /api/health`.
2. Iniciar sesion con `POST /api/v1/auth/login`.
3. Guardar el valor de `data.token`.
4. Enviar ese token como `Bearer` para consumir `GET /api/v1/auth/me`.
5. Cuando el usuario termine, llamar a `POST /api/v1/auth/logout` con el mismo token.

## Endpoints Aun No Implementados

Estos endpoints estan definidos en el plan del proyecto, pero no existen todavia en el backend actual:

- `POST /api/v1/auth/refresh`
- `POST /api/v1/auth/change-password`

## Notas

- La autenticacion actual usa Sanctum con token Bearer.
- No se esta usando JWT ni refresh token por ahora.
- La base funcional real del sistema se restaura desde el dump PostgreSQL legacy.
