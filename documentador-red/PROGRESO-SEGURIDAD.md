# Progreso de correcciones de seguridad (rama fix/security-hardening)

## Hecho (sin probar contra MySQL ni navegador; solo `php -l` limpio en todos los archivos PHP)
- C-01/C-02/C-03/H-03: `app/core/routes.php` (tabla de rutas con roles) + `app/core/auth.php` (Auth::require con lista blanca, usuario activo y rol leídos de la BD en cada petición). `public/index.php` reescrito como enrutador único.
- H-01: eliminado el registro público.
- H-04: login solo con `activo = 1`, actualiza `ultimo_acceso`, `session_regenerate_id`.
- H-05/M-01: CSRF en todos los POST (22 formularios + borrado dinámico de puertos), logout por POST, cookies HttpOnly/SameSite, límite de intentos de login (tabla `login_intentos`, se crea sola), contraseñas de 12+ caracteres.
- H-02: XSS corregido en inventario, VLAN, mapa de IPs, usuarios y detalles (datos en `data-*` + `public/js/app.js`).
- Validación de entradas en equipos, puertos, VLAN, IPs y usuarios (`app/core/validar.php`).
- M-02: errores no se muestran; modelos ya no exponen mensajes de BD.
- M-05 (parcial): bóveda visible solo para Administrador y con registro en bitácora.
- M-06: WAF con correcciones y reactivado. M-10: un solo Ansible a la vez, sin shell, con tiempo límite.
- Log ya no atribuye acciones anónimas al ID 1. Admin inicial sin contraseña por defecto.

## Hecho en la segunda ronda (sin probar en ejecución)
- H-06: `docker/apache-netdocs.conf` (Alias a `public/`, deniega el resto, FallbackResource, ServerTokens Prod) + `docker/php-netdocs.ini`; `Dockerfile` los instala.
- H-07: `.gitignore`, `.dockerignore`, `.env.example`; `git rm --cached` de backups, `ansible.log`, `outputs/`, `__pycache__`.
- M-07/M-08: sin `cisco/cisco` por defecto, validación de IP en `arp_tracert.py`, `snmp_live.py` lee `SNMP_COMMUNITY`, scripts usan `MYSQL_PASSWORD` (usuario no-root).
- `docker-compose.yml`: admin inicial obligatorio, `init.sql` en initdb, phpMyAdmin tras el perfil `tools` y solo en 127.0.0.1.
- M-04: el playbook ya no empuja la comunidad `public`.

## Pendiente
1. M-09: ejecutar `scripts/vendor-js.sh` una vez (con internet) para alojar chart.js, cytoscape y qrcode-generator en `public/js/vendor/`. Mientras falten, se usan CDN con versión fija; con las 3 locales el CSP deja de permitir CDN solo. QR generado en el navegador; URL base con `APP_BASE_URL`.
2. Rotar contraseñas `cisco` y comunidad SNMP; limpiar historial con `git filter-repo`.
3. Volúmenes de BD existentes: crear el usuario MySQL (`CREATE USER` + permisos), `init.sql` solo corre con volumen vacío.
4. No cambiado: verificación de host SSH (M-03), KDF de la bóveda, SNMPv3.
5. Probar con MySQL real: login, roles, CSRF, suspensión, gráficos, mapa, QR, respaldos.

## Aviso importante antes de desplegar
- Define `INIT_ADMIN_USER` e `INIT_ADMIN_PASS` (12+ caracteres) en el `.env`.
- Las rutas ahora exigen rol: el Lector es solo lectura; la bóveda de contraseñas solo la ve el Administrador.
