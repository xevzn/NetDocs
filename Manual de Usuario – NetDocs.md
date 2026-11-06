# Manual de Usuario – NetDocs

## Introducción

NetDocs es el sistema web de documentación y automatización de infraestructura de red (routers y switches Cisco, físicos y emulados) que ya tienes desplegado. Este manual explica **cómo usarlo** día a día: dar de alta equipos, documentar VLANs/IPs, ejecutar las automatizaciones de Ansible y leer sus resultados.

No cubre instalación ni despliegue (Docker, `.env`, permisos de servidor) — eso está en el `README.md` del repositorio.

## Roles y acceso

Se entra con usuario y contraseña desde la pantalla de login. Hay tres roles, y cada ruta del sistema solo la pueden usar los roles permitidos para ella (si intentas entrar a algo que tu rol no permite, el sistema lo rechaza):

| Rol | Puede ver (inventario, VLANs/IPs, mapas, reportes) | Puede documentar (alta/edición de equipos, puertos, VLANs, IPs) | Puede eliminar | Puede ejecutar automatización (Ansible/NAPALM) | Administra usuarios y ve bitácora |
| --- | --- | --- | --- | --- | --- |
| **Administrador** | Sí | Sí | Sí | Sí | Sí |
| **Técnico** | Sí | Sí | No | Sí | No |
| **Lector** | Sí | No | No | No | No |

Solo el Administrador puede eliminar equipos o VLANs, y solo el Administrador entra a "Usuarios" y a la bitácora ("Logs"). El Lector tiene acceso de solo consulta a todo lo demás: inventario, VLANs/IPs, mapa de red, estadísticas y monitoreo en vivo.

Si escaneas el código QR de la etiqueta de un equipo sin haber iniciado sesión, el sistema te manda primero al login y, después de entrar, te lleva directo a los detalles de ese equipo (no al dashboard).

## Gestión de equipos

### Dar de alta un equipo

Desde **Inventario → Nuevo equipo**, el formulario pide:

- **Hostname**, **marca** (Cisco Systems u Otro/Genérico) y **modelo**.
- **Tipo**: Switch (L2/L3), Router/Gateway u Otro dispositivo (cámara IP, etc.). Elige esto con cuidado — varias tareas de Ansible agrupan los equipos por este campo, así que un switch marcado como "Router" puede hacer que se le salten o fallen auditorías que sí deberían aplicársele.
- **¿Usa Ansible?**: "Sí" si el equipo se va a administrar por SSH (routers/switches reales o emulados); "No" para equipos de solo documentación (cámaras IP, etc.).
- **Plantilla de conexión** (solo si usa Ansible): Cisco IOS Legacy (equipos antiguos que solo aceptan algoritmos SSH viejos, vía Paramiko) o Cisco IOS-XE (equipos modernos).
- **IP de gestión**, **ubicación física** (rack/sitio) y **comentarios** libres.
- **Usuario y contraseña SSH**: se guardan cifrados en la bóveda de credenciales (AES-256-GCM), nunca en texto plano.

### Editar y eliminar

Desde **Detalles del equipo** puedes actualizar cualquiera de esos campos. Eliminar un equipo es irreversible y solo lo puede hacer el Administrador.

### Puertos y cableado

Dentro de los detalles de un equipo se documentan sus puertos (nombre, estado, descripción) y, en **Cableado**, las conexiones físicas entre puertos de distintos equipos — esto es documentación manual (lo que tú capturaste), separada de lo que Ansible detecta automáticamente (ver la sección de Automatización).

### Etiquetas y códigos QR

Cada equipo y cada cable tienen una etiqueta imprimible con un código QR (**Etiqueta** / **Etiqueta de cable**). Al escanearlo con el celular, te lleva directo a los detalles de ese equipo o cable en el sistema (pidiendo login si no habías iniciado sesión). También puedes escanear manualmente desde **Escáner** usando la cámara del dispositivo.

## Documentar la red: VLANs e IPs

Esto es el **plano documentado**: lo que tú capturas a mano y que el sistema nunca sobrescribe automáticamente (a diferencia del plano observado de Automatización).

### VLANs

Desde **VLANs**, cada registro tiene: número de VLAN (1–4094), nombre, subred en formato CIDR (ej. `192.168.10.0/24`), gateway y una descripción opcional. Solo el Administrador puede eliminar una VLAN.

### Direcciones IP

Desde **Mapa de IPs** se documenta qué dirección dentro de cada subred está asignada a qué equipo o uso, para llevar control de qué está libre y qué ya está ocupado.

> Como VLANs/IPs y los datos que trae Ansible (interfaces L3 reales, rutas) viven en tablas separadas, puede haber diferencias entre lo documentado aquí y lo que un equipo realmente tiene configurado si alguien hizo un cambio directo en el equipo sin actualizarlo en NetDocs. Revisa periódicamente **Estadísticas**/**Auditoría** para detectar esas diferencias a simple vista.

## Automatización con Ansible

Solo Administrador y Técnico pueden lanzar estas acciones, porque abren sesiones SSH reales hacia los equipos. Aparecen bajo el menú de Automatización:

| Acción | Qué hace |
| --- | --- |
| **Orquestador** | Aprovisiona la comunidad SNMP en los equipos y ejecuta respaldos de configuración. |
| **Auditoría** | Recolecta el estado real de los equipos: puertos operativos, interfaces L3, tabla de enrutamiento y hallazgos de seguridad. Esto llena el "plano observado", no modifica tu documentación. |
| **Descubrimiento NAPALM** | Consulta vecinos vía CDP/LLDP para descubrir la topología física. |
| **Buscador ARP** | Rastrea en qué puerto/equipo está conectada una dirección IP o MAC específica. |
| **Gestión de VLANs** | Consulta, aplica cambios o rastrea VLANs directamente en los equipos. |
| **Respaldos** | Guarda y lista las copias de configuración generadas por el Orquestador. |

Al ejecutar cualquiera de estas, puedes apuntar a **todos los equipos**, a un **grupo** o a **equipos específicos**. La salida completa del comando se muestra en pantalla al terminar.

### Solo una ejecución a la vez

El sistema no permite correr dos automatizaciones de Ansible al mismo tiempo (para no abrir varias sesiones SSH concurrentes sobre los mismos equipos). Si alguien ya está ejecutando una y tratas de lanzar otra —incluso desde otra pestaña del mismo navegador— verás el aviso **"Ya hay una ejecución de Ansible en curso. Espere a que termine e inténtelo de nuevo."** Espera a que termine la primera y vuelve a intentarlo.

### Equipos legacy

Si un equipo usa la plantilla "Cisco IOS Legacy", es porque solo acepta algoritmos SSH antiguos (SHA-1). El servidor debe tener instalada una versión compatible de Paramiko para esos equipos; si una ejecución falla con un error de "no acceptable kex algorithm", es un tema de versión de Paramiko en el servidor, no de tu configuración del equipo (ver README).

## Monitoreo y reportes

Esto es el **plano observado**: datos que solo escribe Ansible/Python, nunca capturados a mano.

- **Estadísticas**: panel con gráficas del estado general de la red (equipos activos, puertos arriba/abajo, etc.), alimentado por los resultados de la última auditoría.
- **Monitor en vivo**: estado SNMP de los equipos en tiempo real.
- **Mapa de red**: topología física descubierta automáticamente (CDP/LLDP vía NAPALM), distinta del cableado que documentaste tú a mano.
- **Auditoría de seguridad** (dentro de los resultados de la auditoría Ansible): hallazgos como contraseñas por defecto, servicios inseguros habilitados, etc.
- **Logs** (solo Administrador): bitácora de todo lo que pasa en el sistema — inicios de sesión, altas/bajas de equipos, ejecuciones de Ansible — para auditoría interna.

## Diagrama de arquitectura

&#91;embedded content: arquitectura de NetDocs · documentación manual vs. automatización\]

El navegador solo habla con NetDocs; NetDocs es el único que escribe en el "plano documentado" (lo que tu capturas) y el único que lanza Ansible, que a su vez es el único que escribe en el "plano observado" (lo que detecta en los equipos reales).

## Solución de problemas (uso diario)

| Síntoma | Qué significa / qué hacer |
| --- | --- |
| "Ya hay una ejecución de Ansible en curso" | Alguien más (o tú en otra pestaña) ya está corriendo una automatización. Espera a que termine. |
| Login correcto pero error de "Servicio no disponible" | Problema de conexión con la base de datos, no de tu usuario/contraseña. Avísale a quien administra el servidor. |
| Después de varios intentos fallidos de login ya no te deja entrar aunque la contraseña sea correcta | Es el bloqueo temporal por intentos fallidos (protección contra ataques de fuerza bruta). Espera unos minutos e inténtalo de nuevo. |
| Al escanear un QR te manda al dashboard en vez de al equipo | Si ya tenías sesión iniciada antes de escanear, es el comportamiento normal (te deja en el dashboard); si no tenías sesión, debería llevarte directo al equipo después del login — si no pasa así, es un tema de versión desplegada, no de tu celular. |
| Un equipo sale con datos de auditoría incompletos o tareas marcadas como omitidas | Revisa que el campo "Tipo" (Switch/Router/Otro) del equipo sea el correcto: varias tareas de Ansible solo aplican a un tipo específico. |
| Una automatización falla con error de conexión SSH en un equipo antiguo | Problema de compatibilidad de Paramiko en el servidor con algoritmos SSH legacy, no de tu configuración del equipo. |

Para errores al **instalar o desplegar** el sistema (Docker, `.env`, permisos de archivos, certificados), consulta la sección de solución de problemas del `README.md`, que cubre esos casos con más detalle técnico.
