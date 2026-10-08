#!/usr/bin/env python3
import os
import json
import sys
import pymysql
from vault_crypto import decrypt_password


# ==============================================================================
# CONFIGURACIÓN DE BASE DE DATOS Y BÓVEDA
# ==============================================================================
DB_HOST = os.getenv("MYSQL_HOST", "db")
DB_USER = os.getenv("MYSQL_USER")
DB_PASS = os.getenv("MYSQL_ROOT_PASSWORD")
DB_NAME = os.getenv("MYSQL_DATABASE", "red_infraestructura")
if not DB_USER or not DB_PASS:
    raise RuntimeError("Faltan las variables MYSQL_USER y MYSQL_ROOT_PASSWORD.")

def get_inventory():
    """Conecta a MySQL, extrae equipos, desencripta y genera JSON para Ansible."""
    
    # 1. Estructura Base que Ansible espera leer
    inventory = {
        "_meta": {
            "hostvars": {}
        },
        "all": {
            "children": ["switches", "routers", "firewalls", "servidores", "otros"]
        },
        "switches": {"hosts": []},
        "routers": {"hosts": []},
        "firewalls": {"hosts": []},
        "servidores": {"hosts": []},
        "otros": {"hosts": []}
    }

    # 2. Conexión a Base de Datos
    try:
        conn = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME, cursorclass=pymysql.cursors.DictCursor)
        with conn.cursor() as cursor:
            cursor.execute("SELECT * FROM equipos")
            equipos = cursor.fetchall()
        conn.close()
    except Exception as e:
        sys.stderr.write(f"Error conectando a BD: {str(e)}\n")
        return inventory

    # 3. Procesar cada equipo
    for eq in equipos:
        hostname = eq.get('hostname')
        ip = eq.get('ip_gestion')
        tipo = str(eq.get('tipo')).lower()
        plantilla = eq.get('plantilla_conexion', 'cisco_ios_legacy')
        ssh_user = eq.get('ssh_user')
        ssh_pass_enc = eq.get('ssh_password_encrypted')
        
        if not hostname or not ip:
            continue

        # A. Asignar el equipo al grupo correcto de Ansible según su Tipo
        if 'switch' in tipo:
            inventory['switches']['hosts'].append(hostname)
        elif 'router' in tipo:
            inventory['routers']['hosts'].append(hostname)
        elif 'firewall' in tipo:
            inventory['firewalls']['hosts'].append(hostname)
        elif 'servidor' in tipo:
            inventory['servidores']['hosts'].append(hostname)
        else:
            inventory['otros']['hosts'].append(hostname)

        # B. Inicializar variables del Host
        hostvars = {
            'ansible_host': ip,
        }

        # C. Si el equipo tiene credenciales guardadas, las desencriptamos en memoria
        if ssh_user and ssh_pass_enc:
            decrypted_pass = decrypt_password(ssh_pass_enc)
            if decrypted_pass:
                hostvars['ansible_user'] = ssh_user
                hostvars['ansible_password'] = decrypted_pass
                # Asumimos que la pass de Enable es la misma por comodidad
                hostvars['ansible_become_password'] = decrypted_pass 
                hostvars['ansible_become'] = True
                hostvars['ansible_become_method'] = 'enable'

        # D. Inyectar variables específicas según la Plantilla (OS) elegida en el Dashboard
        if plantilla == 'cisco_ios_legacy':
            hostvars['ansible_network_os'] = 'cisco.ios.ios'
            hostvars['ansible_connection'] = 'network_cli'
            hostvars['ansible_ssh_common_args'] = '-o StrictHostKeyChecking=no -o KexAlgorithms=+diffie-hellman-group1-sha1 -o HostKeyAlgorithms=+ssh-rsa -o Ciphers=+aes128-cbc,3des-cbc'
        
        elif plantilla == 'cisco_ios_modern':
            hostvars['ansible_network_os'] = 'cisco.ios.ios'
            hostvars['ansible_connection'] = 'network_cli'
            hostvars['ansible_ssh_common_args'] = '-o StrictHostKeyChecking=no'
        
        elif plantilla == 'huawei_vrp':
            hostvars['ansible_network_os'] = 'community.network.ce'
            hostvars['ansible_connection'] = 'network_cli'
            hostvars['ansible_ssh_common_args'] = '-o StrictHostKeyChecking=no'
        
        elif plantilla == 'mikrotik_routeros':
            hostvars['ansible_network_os'] = 'community.routeros.api'
            hostvars['ansible_connection'] = 'network_cli'
            hostvars['ansible_ssh_common_args'] = '-o StrictHostKeyChecking=no'
        
        else:
            hostvars['ansible_connection'] = 'network_cli'
            hostvars['ansible_ssh_common_args'] = '-o StrictHostKeyChecking=no'

        # E. Guardar las variables en el JSON general
        inventory['_meta']['hostvars'][hostname] = hostvars

        # F. NUEVO: Crear un grupo dinámico basado en la plantilla de conexión
        if plantilla not in inventory:
            inventory[plantilla] = {"hosts": []}
            if plantilla not in inventory["all"]["children"]:
                inventory["all"]["children"].append(plantilla)
        
        inventory[plantilla]['hosts'].append(hostname)

    return inventory


if __name__ == '__main__':
    # Ansible siempre llama a los inventarios dinámicos pasándoles el parámetro --list
    if len(sys.argv) > 1 and sys.argv[1] == '--list':
        print(json.dumps(get_inventory(), indent=2))
    elif len(sys.argv) > 1 and sys.argv[1] == '--host':
        print(json.dumps({})) # Ansible lee los hosts desde _meta, por lo que esto se devuelve vacío
    else:
        # Modo de prueba por si corres el script manualmente en terminal
        print(json.dumps(get_inventory(), indent=2))