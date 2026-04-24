# Sipre Cochabamba

Proyecto base en un solo repositorio con arquitectura desacoplada:

- `backend/`: Laravel 13 para la API y la integracion con PostgreSQL.
- `frontend/`: React + Vite `8.0.10` + shadcn/ui + TanStack Query.
- `docker-compose.yml`: orquesta frontend, backend y base de datos.
- `sipre-202604201330_pg_backup.dmp`: backup versionado para inicializar la BD.

## Requisitos

- Docker Desktop con Docker Compose.
- Git.

## Inicializacion del repositorio

```bash
git init
```

Este proyecto usa un solo repositorio para versionar `backend`, `frontend`, Docker y documentacion juntos.

## Levantar el entorno

Desde la raiz del proyecto:

```bash
docker compose up --build
```

Ese comando levanta:

- `backend` en modo desarrollo con el codigo montado desde `./backend`
- `frontend` en modo desarrollo con Vite y recarga en caliente desde `./frontend`
- `db` con PostgreSQL y restauracion automatica del dump en la primera inicializacion

Por defecto, el proyecto usa el frontend en modo desarrollo.

Para dejarlo en segundo plano:

```bash
docker compose up -d --build
```

Si cambias archivos en `backend/` o `frontend/`, los contenedores ya ven esos cambios sin reconstruir imagenes.

- Backend: refleja el codigo montado localmente.
- Frontend: Vite recompila y recarga automaticamente en `http://localhost:5173`.

Para detener los servicios:

```bash
docker compose down
```

Si cambias dependencias del frontend o Dockerfile del frontend, si conviene recrear el servicio:

```bash
docker compose up -d --build frontend
```

Si cambias dependencias del backend o Dockerfile del backend:

```bash
docker compose up -d --build backend
```

## Frontend en produccion

Tambien existe un archivo separado `docker-compose.prod.yml` para levantar el frontend compilado y servido por Nginx.

Levantar frontend compilado en produccion:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build frontend-prod
```

Ese comando agrega el servicio `frontend-prod`, que usa el target `production` del `frontend/Dockerfile`.

Disponible en:

- Frontend produccion: `http://localhost:4173`

Notas importantes:

- `docker-compose.yml` es el flujo de desarrollo.
- `docker-compose.prod.yml` es para validar o ejecutar el frontend compilado.
- Puedes tener `frontend` y `frontend-prod` al mismo tiempo porque usan puertos distintos.
- Si prefieres dejar solo el frontend compilado, deten el dev server antes:

```bash
docker compose stop frontend
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build frontend-prod
```

Para eliminar volumenes y restaurar el dump desde cero:

```bash
docker compose down -v
docker compose up --build
```

## Servicios disponibles

- Frontend Vite dev server: `http://localhost:5173`
- Frontend build produccion: `http://localhost:4173`
- Backend: `http://localhost:8000`
- API health: `http://localhost:8000/api/health`
- PostgreSQL Docker: `localhost:5433`

## Flujo de desarrollo recomendado

1. Levanta el stack:

```bash
docker compose up -d --build
```

2. Abre el frontend en `http://localhost:5173`
3. Abre la API en `http://localhost:8000`
4. Edita archivos en `frontend/src/` o `backend/` normalmente desde tu editor
5. Para revisar logs:

```bash
docker compose logs -f frontend
docker compose logs -f backend
```

6. Si el frontend deja de reflejar cambios, recrealo:

```bash
docker compose up -d --build frontend
```

7. Si quieres validar como queda el frontend compilado para produccion:

```bash
docker compose stop frontend
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build frontend-prod
```

8. Para volver al frontend de desarrollo:

```bash
docker compose stop frontend
docker compose up -d --build frontend
```

## Conexion a PostgreSQL desde DataGrip u otro cliente

Usa estos datos:

- Host: `localhost`
- Port: `5433`
- Database: `sipre`
- User: `sipre`
- Password: `sipre`

El puerto `5433` se usa para no chocar con el PostgreSQL local de tu maquina.

## Base de datos

El backup `sipre-202604201330_pg_backup.dmp` se restaura automaticamente la primera vez que se inicializa el volumen de PostgreSQL.

El archivo del backup se sube al repositorio por decision del proyecto para que cualquier entorno nuevo pueda levantar la misma base de arranque.

## Convencion de commits

Se recomienda usar mensajes tipo Conventional Commits con alcance por modulo.

Formato:

```text
tipo(scope): descripcion corta
```

Scopes sugeridos:

- `backend`
- `frontend`
- `docker`
- `docs`
- `db`
- `fullstack`

Tipos sugeridos:

- `feat`: nueva funcionalidad
- `fix`: correccion de error
- `chore`: cambios de soporte o configuracion
- `refactor`: reorganizacion sin cambiar comportamiento esperado
- `docs`: documentacion
- `test`: pruebas

Ejemplos:

```text
feat(frontend): agregar tanstack query al cliente react
feat(backend): crear endpoint de health para la api
chore(docker): exponer postgresql en el puerto 5433
docs(docs): documentar conexion desde datagrip
feat(fullstack): conectar dashboard inicial con api health
```

## Estructura

```text
.
|- .docker/
|- backend/
|- frontend/
|- docker-compose.yml
|- README.md
|- sipre-202604201330_pg_backup.dmp
```
