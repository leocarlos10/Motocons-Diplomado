# MOTOCONS

Sistema web para el almacén de motocicletas MOTOCONS, desarrollado en **PHP puro** con **MySQL**, ejecutado en contenedores **Docker**.

Proyecto del Diplomado en Desarrollo Web en la Nube - Universidad de Córdoba, sede Lorica.

> **Alcance de esta entrega:** base de datos completa + módulo de **mantenimiento y garantías** (HU-08, HU-09 y HU-10).

---


Se puede usar con docker o xamp. 

## Configuracion rapida con xamp.
No es nesesario guardar el proyecto en la carpeta de xamp. descargalo donde veas conveniente. 

```
git clone <URL_DEL_REPOSITORIO>
cd motocons
```

Crear la base de datos. 
```
C:\xampp\php\php.exe db\setup.php
```

Correr la app 
```
C:\xampp\php\php.exe -S localhost:8000 -t src/public
```




### Configuracion de docker. 

Requisitos previos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows/Mac) o Docker Engine + Docker Compose (Linux)
- Git

Verifica que funcionan:

```bash
docker --version
docker compose version
```

---

## Puesta en marcha (primera vez)

1. Clona el repositorio y entra a la carpeta:

   ```bash
   git clone <URL_DEL_REPOSITORIO>
   cd motocons
   ```

2. Crea tu archivo de variables de entorno a partir del ejemplo:

   ```bash
   cp .env.example .env
   ```

   (En Windows PowerShell: `copy .env.example .env`)

3. Construye y levanta los servicios:

   ```bash
   docker compose up -d --build
   ```

4. Comprueba que los 3 servicios estén corriendo:

   ```bash
   docker compose ps
   ```

5. Abre en el navegador:

   | Servicio | URL | Credenciales |
   |---|---|---|
   | Aplicación | http://localhost:8080 | - |
   | phpMyAdmin | http://localhost:8081 | usuario `root`, clave la de `MYSQL_ROOT_PASSWORD` en tu `.env` |

Si en http://localhost:8080 ves el mensaje de PHP conectado a MySQL, todo está bien.

---

## Comandos del día a día

| Qué quieres hacer | Comando |
|---|---|
| Levantar el proyecto | `docker compose up -d` |
| Apagarlo (conserva los datos) | `docker compose down` |
| Ver logs de todo | `docker compose logs -f` |
| Ver logs solo de PHP/Apache | `docker compose logs -f web` |
| Entrar a la terminal del contenedor PHP | `docker compose exec web bash` |
| Entrar a MySQL por consola | `docker compose exec db mysql -u root -p` |
| **Reiniciar la BD desde cero (borra los datos)** | `docker compose down -v` y luego `docker compose up -d` |

Los cambios en el código de `src/` se ven al recargar el navegador. **No hace falta reconstruir** nada.
Solo se usa `--build` cuando cambia el `Dockerfile`.

---

## Estructura del proyecto

```
motocons/
├── docker-compose.yml
├── Dockerfile
├── .env                  ← NO se sube a Git (contraseñas locales)
├── .env.example          ← plantilla que sí se sube
├── .gitignore
├── README.md
├── db/
│   └── init/
│       ├── 01_schema.sql
│       └── 02_datos_prueba.sql
└── src/
    ├── config/                 ← conexión y configuración
    ├── controllers/            ← control de solicitudes y coordinación MVC
    ├── helpers/                ← utilidades de presentación
    ├── models/                 ← consultas y reglas de negocio
    ├── views/                  ← plantillas HTML/PHP
    └── public/                 ← único punto de acceso web y recursos estáticos
```

### Qué hace cada archivo

**`Dockerfile`**: Define la imagen del servidor web. Parte de `php:8.3-apache`, instala la extensión `pdo_mysql` (para que PHP pueda hablar con MySQL), activa `mod_rewrite` y configura Apache para que sirva desde `src/public`.

**`docker-compose.yml`**: Orquesta los tres servicios del proyecto:
- `web`: PHP + Apache, expuesto en el puerto 8080. Monta `./src` dentro del contenedor, por eso los cambios se ven al instante.
- `db`: MySQL 8. Guarda sus datos en un volumen (`db_data`) para que sobrevivan al apagar los contenedores. Ejecuta los scripts de `db/init/` al crearse.
- `phpmyadmin`: interfaz gráfica para ver y editar la base de datos, en el puerto 8081.

**`.env`**: Contraseñas y nombre de la base de datos. Cada integrante tiene el suyo y **nunca se sube a Git**.

**`.env.example`**: Copia de `.env` con valores de ejemplo. Sí se sube a Git para que los demás sepan qué variables crear.

**`src/`**: Código de la aplicación organizado con el patrón MVC:
- `src/models/`: acceso a datos y reglas del dominio, como los mantenimientos y la consulta de garantías.
- `src/controllers/`: recibe las solicitudes, coordina modelos y selecciona la vista que se debe renderizar.
- `src/views/`: plantillas HTML/PHP; no procesa directamente solicitudes ni realiza consultas a la base de datos.
- `src/config/`: configuración y conexión a la base de datos.
- `src/helpers/`: funciones reutilizables de presentación.
- `src/public/`: la **única** carpeta accesible desde el navegador (DocumentRoot de Apache). `index.php` y `mi_garantia.php` son los puntos de entrada públicos; el resto del código queda fuera de `public/`.

El recorrido de una solicitud es: `public/` → `controllers/` → `models/` → `views/`. Los archivos públicos existentes conservan sus rutas para no romper enlaces.

---

## La carpeta `db/init/` (importante)

Esta carpeta es la forma en que el proyecto **crea la base de datos automáticamente**, sin importar nada a mano.

### Cómo funciona

La imagen oficial de MySQL, al arrancar con un volumen **vacío**, busca la carpeta `/docker-entrypoint-initdb.d` y ejecuta todos los archivos `.sql` que encuentre. En el `docker-compose.yml` esa carpeta está enlazada con `./db/init`:

```yaml
volumes:
  - ./db/init:/docker-entrypoint-initdb.d
```

Por eso, todo `.sql` que pongas en `db/init/` se ejecuta solo.

### Reglas que debes conocer

1. **Se ejecutan solo la primera vez**, cuando el volumen `db_data` aún no existe. Si ya levantaste el proyecto antes y luego cambias o agregas un `.sql`, **no pasará nada** al hacer `docker compose up`.

2. **Para volver a ejecutarlos hay que borrar el volumen:**

   ```bash
   docker compose down -v
   docker compose up -d
   ```

   El `-v` es lo que borra el volumen. **Esto elimina todos los datos de la BD** y la recrea desde los scripts.

3. **El orden es alfabético.** Por eso los archivos llevan prefijo numérico:
   - `01_schema.sql` crea las tablas (va primero).
   - `02_datos_prueba.sql` inserta datos de ejemplo (necesita que las tablas ya existan).

4. **Si un script tiene un error, MySQL se detiene ahí** y la base queda a medias. Revisa el problema con:

   ```bash
   docker compose logs db
   ```

   Corrige el `.sql`, y repite el paso 2.

5. **Los nombres de tablas y columnas deben coincidir** entre `01_schema.sql` y `02_datos_prueba.sql`. Si cambias algo en el schema, ajusta también los datos de prueba.

El script de datos de prueba inserta las ventas y sus detalles dentro de una transacción. Al terminar, muestra un resumen de motos, ventas, detalles y motos enlazadas con cliente. Para que los datos actualizados aparezcan en una base existente, el volumen debe inicializarse de nuevo; primero exporta cualquier dato que quieras conservar. `docker compose down -v` elimina **todos** los datos guardados en ese volumen.

### Flujo de trabajo cuando alguien cambia la base de datos

1. Modificar el `.sql` correspondiente en `db/init/`.
2. Subirlo a Git.
3. Cada integrante hace `git pull` y ejecuta `docker compose down -v && docker compose up -d`.

> Nunca modifiquen la estructura solo desde phpMyAdmin: ese cambio vive únicamente en su máquina y el resto del equipo no lo tendrá. Todo cambio de estructura debe quedar reflejado en `db/init/`.

---

## Trabajo en equipo con Git

- `.env` está en `.gitignore`: nunca se sube. Si agregas una variable nueva, agrégala también a `.env.example`.
- Antes de empezar a trabajar: `git pull`.
- Si alguien cambió algo en `db/init/`, recrea la BD como se explica arriba.
- Haz commits pequeños y con mensajes claros.

Contenido mínimo del `.gitignore`:

```
.env
```

---

## Solución de problemas

| Problema | Causa probable | Solución |
|---|---|---|
| `port is already allocated` | El puerto 8080, 8081 o 3306 está ocupado (otro XAMPP, otro proyecto) | Cambia el número de la izquierda en `ports` del `docker-compose.yml` (ej. `"8082:80"`) |
| La página muestra error de conexión a la BD | MySQL todavía estaba arrancando | Espera unos segundos y recarga. Si persiste: `docker compose logs db` |
| No aparecen las tablas en phpMyAdmin | Los scripts no se ejecutaron (el volumen ya existía) o un script falló | `docker compose down -v` y `docker compose up -d`; revisa `docker compose logs db` |
| `Access denied for user` | El `.env` cambió después de crear la BD | Las credenciales se fijan al crear el volumen: `docker compose down -v` y vuelve a levantar |
| Cambié el código y no se ve | Caché del navegador | Recarga forzada (Ctrl + F5) |
| Cambié el `Dockerfile` y no se aplica | La imagen no se reconstruyó | `docker compose up -d --build` |

---

## Equipo

- Leocarlos Ospina Causil
- Caleb Seña Melo
- Jhonatan David Cantero Hernández
- Miguel Andrés De Aguas Martínez
- Joel David Toscano Cafiel
- Adrian Ricardo Villazon Ascanio

**Instructor:** Harold Dionisio Bula Herazo
