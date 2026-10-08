# 1. Usamos la imagen oficial de PHP 8.2 con Apache como base
FROM php:8.2-apache

# 2. Variables de entorno para Python (Evita .pyc y logs cacheados)
ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1

# 3. Instalamos Python, dependencias del sistema y herramientas de red
RUN apt-get update && apt-get install -y \
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
RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir \
    pandas \
    netmiko \
    paramiko \
    ansible \
    ansible-runner \
    ansible-pylibssh \
    textfsm \
    ntc-templates \
    napalm \
    networkx \
    pyyaml \
    pysnmp \
    pymysql

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
