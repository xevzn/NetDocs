# 1. Usamos la imagen oficial de PHP 8.2 con Apache como base
FROM php:8.2-apache

# 2. Variables de entorno para Python (Evita .pyc y logs cacheados)
ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1

# 3. Instalamos Python, dependencias del sistema y herramientas de red
RUN apt-get update && apt-get install -y --no-install-recommends \
    python3 \
    python3-pip \
    python3-venv \
    sshpass \
    curl \
    iputils-ping \
    gcc \
    libffi-dev \
    git \
    snmp \
    diffutils \
    libsnmp-dev \
    && rm -rf /var/lib/apt/lists/*

# 4. Instalamos extensiones de PHP para conectarse a MySQL y SNMP
RUN docker-php-ext-install pdo pdo_mysql snmp

# 5. Habilitamos URLs amigables en Apache
RUN a2enmod rewrite

# 6. Creamos un Entorno Virtual de Python (Estándar de seguridad en Debian/Ubuntu)
ENV VIRTUAL_ENV=/opt/venv
RUN python3 -m venv $VIRTUAL_ENV
ENV PATH="$VIRTUAL_ENV/bin:$PATH"

# 7. Instalamos TU MÚSCULO DE REDES (Incluyendo pysnmp para el monitor en vivo)
# Versiones fijadas a un rango compatible: una actualización mayor sin aviso (p. ej.
# Paramiko 5, que quitó los algoritmos SSH que necesitan los equipos legacy) ya nos
# rompió un despliegue una vez; con el rango fijo, "docker compose build" nunca vuelve
# a traer una versión mayor nueva sin que lo decidamos a propósito.
RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir \
    "pandas>=2,<3" \
    "netmiko>=4,<5" \
    "paramiko>=3.5,<5" \
    "ansible>=9,<11" \
    "ansible-runner>=2,<3" \
    "ansible-pylibssh>=1,<2" \
    "textfsm>=1,<2" \
    "ntc-templates>=4,<5" \
    "napalm>=5,<6" \
    "networkx>=3,<4" \
    "pyyaml>=6,<7" \
    "pysnmp>=4,<5" \
    "pymysql>=1,<2"

# 8. Instalamos las colecciones de Cisco para Ansible
RUN ansible-galaxy collection install cisco.ios

# 9. Configuración crítica de permisos para Ansible y PHP
# Ansible necesita escribir en el directorio 'home' del usuario que lo ejecuta.
# En Apache, ese usuario es 'www-data' y su home es '/var/www'.
RUN mkdir -p /var/www/.ansible && \
    mkdir -p /var/www/.ssh && \
    chown -R www-data:www-data /var/www

# Ajustamos permisos de la carpeta web
RUN chown -R www-data:www-data /var/www/html

# 10. Endurecimiento: solo public/ se publica (Alias + Require all denied) y php.ini de producción
COPY docker/apache-netdocs.conf /etc/apache2/conf-available/netdocs.conf
COPY docker/php-netdocs.ini /usr/local/etc/php/conf.d/zz-netdocs.ini
RUN a2enconf netdocs && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
