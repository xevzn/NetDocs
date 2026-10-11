# NetDocs — Documentador de infraestructura de red

Aplicación web para **documentar, auditar y automatizar una red de equipos Cisco** (routers y switches).
Combina un inventario en base de datos con automatización (Ansible, Netmiko, NAPALM, SNMP) que lee y modifica los equipos reales.

- **Backend:** PHP 8.2 (MVC sin framework) + Apache
- **Base de datos:** MySQL 8 (`red_infraestructura`)
- **Automatización:** Ansible, Python (Netmiko, NAPALM, Paramiko, pysnmp, pandas)
- **Despliegue:** Docker Compose (contenedores `app` y `db`; `phpMyAdmin` opcional)

> El código de la aplicación está en [`documentador-red/`](documentador-red/).

---

## 1. Qué hace el sistema

| Área | Funciones |
|---|---|
| **Inventario** | Alta, edición y baja de equipos; detalle por equipo con puertos, VLAN, IPs y modo de puerto (acceso/troncal). |
| **Etiquetas QR** | Etiquetas imprimibles para equipos y cables. El QR abre `/escaner?id=…` desde el móvil. |
| **Cableado** | Registro de enlaces entre puertos y etiqueta de cable. |
| **VLAN y direccionamiento** | Catálogo de VLAN con subred y gateway, mapa de IPs por VLAN. |
| **Topología y búsqueda** | Mapa de red interactivo (Cytoscape) y buscador global (equipos, puertos, IPs, VLAN). |
| **Monitoreo** | Monitor en vivo por SNMP (CPU/RAM con gráficos) y página de estadísticas. |
| **Automatización** | Orquestador Ansible (recolecta información y la sincroniza con la BD), auditoría de seguridad, respaldos de configuración con comparación (diff) y descarga, descubrimiento NAPALM, rastreo ARP, consulta y cambio de VLAN en equipos. |
| **Bóveda** | Credenciales de los equipos cifradas con AES-256-GCM. |
| **Administración** | Usuarios y roles, bitácora de acciones y registro del WAF. |

### Roles

| Rol | Puede |
|---|---|
| **1 Administrador** | Todo, incluida la gestión de usuarios y ver las contraseñas de la bóveda. |
| **2 Técnico** | Leer y modificar documentación, ejecutar automatizaciones. No gestiona usuarios ni ve la bóveda. |
| **3 Lector** | Solo lectura. |

---

## 2. Arquitectura

```
Navegador ──HTTP──► Apache (contenedor app)
                      └─ public/index.php   ← único punto de entrada
                           1. cabeceras de seguridad + WAF
                           2. sesión + verificación CSRF (todo POST)
                           3. tabla de rutas (app/core/routes.php) → Auth::require(roles)
                           4. controlador → modelo (PDO) → vista
                                   │
                                   ├──► MySQL (contenedor db)
                                   └──► proc_open (sin shell): Ansible / scripts Python
                                              └──► equipos de red por SSH / SNMP
```

### Estructura del repositorio

```
documentador-red/
├── public/                 Única carpeta expuesta por Apache
│   ├── index.php           Enrutador
│   └── js/                 app.js (eventos) y vendor/ (librerías de terceros)
├── app/
│   ├── core/               auth, routes, validar, vault, database, assets
│   ├── controllers/        Lógica de cada módulo + waf.php
│   ├── models/             Acceso a datos (PDO, consultas preparadas)
│   └── views/              Plantillas PHP
├── config/database.php     Lee variables de entorno (o .env)
├── automation/
│   ├── ansible_project/    ansible.cfg y playbooks
│   ├── scripts/            Python: inventario dinámico, sincronización, SNMP, NAPALM, ARP, VLAN
│   └── backups/            Respaldos generados (no se versionan)
├── docker/                 Configuración de Apache y PHP
├── Dockerfile
├── docker-compose.yml
├── init.sql                Esquema y datos iniciales
├── .env.example
└── scripts/vendor-js.sh    Descarga las librerías JS a public/js/vendor/
```

### Cómo fluye la automatización

1. Un Técnico/Administrador pulsa un botón (por ejemplo «Ejecutar orquestador»).
2. PHP toma un candado (solo un Ansible a la vez) y lanza `ansible-playbook` con un arreglo de argumentos, sin shell y con tiempo límite de 25 min.
3. El inventario es **dinámico**: `dynamic_inventory.py` lee los equipos y sus credenciales (descifradas desde la bóveda) de MySQL.
4. Los playbooks recolectan datos por SSH y `db_sync.py` / `snmp_poller.py` los vuelcan en la base de datos.
5. El resultado y la acción quedan en la bitácora.

---

## 3. Seguridad

- **Autorización central:** cada ruta declara los roles permitidos; el rol y el estado «activo» se leen de la BD en cada petición, de modo que suspender una cuenta surte efecto al instante.
- **CSRF** en todos los POST; cierre de sesión por POST.
- **Sesión:** cookies `HttpOnly`, `SameSite=Lax`, `Secure` si hay HTTPS, ID regenerado al iniciar sesión, cierre por inactividad (2 h).
- **Login:** límite de intentos (5 por usuario+IP y 30 por IP cada 15 min) y contraseñas de **12+ caracteres**.
- **Entradas validadas** (hostname, IP, CIDR, VLAN, MAC…) y salida escapada contra XSS; **CSP** y demás cabeceras.
- **SQL:** solo consultas preparadas.
- **Bóveda:** AES-256-GCM con clave derivada de `VAULT_MASTER_KEY`.
- **Docker/Apache:** solo `public/` es accesible; el resto del proyecto está denegado.
- **Base de datos:** la app usa un usuario propio con permisos solo sobre `red_infraestructura`, no root.
- **WAF** propio como capa adicional (no sustituye a las defensas anteriores).

Pendientes conocidos: verificación de clave de host SSH desactivada y algoritmos antiguos habilitados para equipos legacy; derivación de la clave de la bóveda sin KDF; SNMP v2c.

---

## 4. Despliegue

### Requisitos
- Docker y Docker Compose (en Windows: Docker Desktop con integración WSL, o Docker Engine dentro de WSL).
- Acceso de red desde el contenedor a los equipos (SSH y SNMP).
- Clona o descomprime el proyecto en el sistema de archivos de Linux/WSL (por ejemplo `~/`), **no** en `/mnt/c/…`.

### Pasos

```bash
cd documentador-red
cp .env.example .env
openssl rand -base64 48        # úsalo como VAULT_MASTER_KEY
nano .env                      # completa los valores (ver tabla)

docker compose up -d --build
docker compose logs -f db      # espera "ready for connections"
```

Abre **http://localhost/documentador-red/** e inicia sesión con `INIT_ADMIN_USER` / `INIT_ADMIN_PASS`.

### Variables de entorno (`.env`)

| Variable | Obligatoria | Descripción |
|---|---|---|
| `MYSQL_ROOT_PASSWORD` | Sí | Contraseña de root de MySQL (solo administración). |
| `MYSQL_USER` / `MYSQL_PASSWORD` | Sí | Usuario de la aplicación; se crea solo al inicializar la BD. |
| `VAULT_MASTER_KEY` | Sí | Clave de la bóveda. **Si se cambia, las contraseñas guardadas dejan de descifrarse.** |
| `INIT_ADMIN_USER` / `INIT_ADMIN_PASS` | Sí | Primer administrador (contraseña de 12+ caracteres). Solo se crea si no existe. |
| `INIT_ADMIN_EMAIL` | No | Correo del administrador inicial. |
| `APP_BASE_URL` | No | URL que usan los QR, alcanzable desde los móviles (ej. `http://192.168.1.10`, sin barra final). Si falta, se usa el host de la petición. |
| `SESSION_COOKIE_SECURE` | No | `1` si hay HTTPS (por ejemplo, tras un proxy con TLS). |
| `BIND_ADDR` | No | `127.0.0.1` para publicar solo en localhost. Por defecto `0.0.0.0`. |

> Si una contraseña contiene `$`, escríbela como `$$` en el `.env` (Docker Compose lo interpreta como variable).

### Librerías JavaScript locales (recomendado)

Chart.js, Cytoscape y el generador de QR se sirven desde `public/js/vendor/` cuando existen; si no, se usan CDN con versión fija. Para dejarlas locales (y que el CSP deje de permitir CDN), con internet:

```bash
./scripts/vendor-js.sh
docker compose restart app
```

### phpMyAdmin (opcional)

```bash
docker compose --profile tools up -d
# http://127.0.0.1:8081  (solo accesible desde la propia máquina)
```

### Operación

```bash
docker compose ps                  # estado
docker compose logs -f app         # errores de la aplicación
docker compose restart app
docker compose down                # detiene (conserva datos)
docker compose down -v             # detiene y BORRA la base de datos
```

**Respaldo de la base de datos**
```bash
docker compose exec db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" red_infraestructura' > respaldo.sql
```

### Solución de problemas

| Síntoma | Causa y solución |
|---|---|
| «Error crítico: No se pudo conectar a la base de datos» | Suele ser un volumen de BD anterior: `MYSQL_USER`, `MYSQL_PASSWORD` e `init.sql` solo se aplican con el volumen vacío. Ejecuta `docker compose down -v && docker compose up -d --build` (borra datos) o crea el usuario a mano. La causa exacta está en `docker compose logs app \| grep "Conexión BD"`. |
| `docker compose` dice «Set … in the environment» | Falta una variable obligatoria en `.env`. |
| «Solicitud no válida o sesión expirada» | Token CSRF caducado: recarga la página. |
| Los QR no se dibujan | Falta la librería local: ejecuta `./scripts/vendor-js.sh`. |
| Cuenta bloqueada al iniciar sesión | Se superó el límite de intentos; espera 15 minutos. |
| Un Lector no puede crear/editar | Es el comportamiento esperado de ese rol. |

### Actualizar una BD existente (sin borrar datos)

```bash
docker compose exec db mysql -uroot -p
```
```sql
CREATE USER 'netdocs'@'%' IDENTIFIED BY 'TU_MYSQL_PASSWORD';
GRANT ALL ON red_infraestructura.* TO 'netdocs'@'%';
FLUSH PRIVILEGES;
```
(usa el usuario y la contraseña de tu `.env`). La tabla `login_intentos` se crea sola.

---

## 5. Recomendaciones para producción

- Poner la aplicación detrás de un proxy inverso con **HTTPS** y activar `SESSION_COOKIE_SECURE=1`.
- No exponer el puerto 80 a internet; limitar con firewall o `BIND_ADDR=127.0.0.1`.
- Rotar las contraseñas SSH de los equipos y la comunidad SNMP; preferir SNMPv3.
- Hacer respaldos periódicos de la base de datos y guardar `VAULT_MASTER_KEY` en un lugar seguro, aparte.
- No subir `.env`, respaldos de configuraciones ni logs al repositorio (ya están en `.gitignore`).
- Revisar la bitácora (`/logs`) y el registro del WAF periódicamente.
