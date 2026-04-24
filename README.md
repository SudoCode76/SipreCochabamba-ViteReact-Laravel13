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

Para dejarlo en segundo plano:

```bash
docker compose up -d --build
```

Para detener los servicios:

```bash
docker compose down
```

Para eliminar volumenes y restaurar el dump desde cero:

```bash
docker compose down -v
docker compose up --build
```

## Servicios disponibles

- Frontend: `http://localhost:5173`
- Backend: `http://localhost:8000`
- API health: `http://localhost:8000/api/health`
- PostgreSQL Docker: `localhost:5433`

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
