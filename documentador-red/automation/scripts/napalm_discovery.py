#!/usr/bin/env python3
import sys
import json
import os
import re
import pymysql
import warnings
import paramiko
from napalm import get_network_driver
from vault_crypto import decrypt_password

# Suprimir warnings criptográficos molestos en la salida JSON
warnings.filterwarnings("ignore")

# Compatibilidad SSH Universal (Catalyst 3650/9300 modernos + ISR/2960/IOU legacy)
paramiko.Transport._preferred_kex = (
    'curve25519-sha256', 'curve25519-sha256@libssh.org',
    'ecdh-sha2-nistp256', 'ecdh-sha2-nistp384', 'ecdh-sha2-nistp521',
    'diffie-hellman-group16-sha512', 'diffie-hellman-group18-sha512',
    'diffie-hellman-group14-sha256', 'diffie-hellman-group-exchange-sha256',
    'diffie-hellman-group14-sha1', 'diffie-hellman-group-exchange-sha1',
    'diffie-hellman-group1-sha1'
)
paramiko.Transport._preferred_keys = (
    'ecdsa-sha2-nistp256', 'ecdsa-sha2-nistp384', 'ecdsa-sha2-nistp521',
    'ssh-ed25519', 'rsa-sha2-512', 'rsa-sha2-256', 'ssh-rsa'
)

# ==========================================
# CONFIGURACIÓN
# ==========================================
DB_HOSTS = [os.getenv("MYSQL_HOST", "db"), "netdocs_db", "127.0.0.1"]
DB_USER = os.getenv("MYSQL_USER")
DB_PASS = os.getenv("MYSQL_ROOT_PASSWORD")
DB_NAME = os.getenv("MYSQL_DATABASE", "red_infraestructura")
if not DB_USER or not DB_PASS:
    raise RuntimeError("Faltan las variables MYSQL_USER y MYSQL_ROOT_PASSWORD.")
def conectar_db():
    for host in DB_HOSTS:
        try:
            return pymysql.connect(
                host=host, user=DB_USER, password=DB_PASS,
                database=DB_NAME, cursorclass=pymysql.cursors.DictCursor,
                connect_timeout=3
            )
        except Exception:
            continue
    return None

def normalizar_puerto(puerto):
    """Estandariza nombres de interfaces para Catalyst (Gi/Te/Fa), Routers (Se/Gi) y Virtuales (Et)."""
    if not puerto:
        return "Desconocido"
    p = puerto.strip()
    reemplazos = [
        (r'^TenGigabitEthernet', 'Te'),
        (r'^TwentyFiveGigE', 'Twe'),
        (r'^FortyGigabitEthernet', 'Fo'),
        (r'^HundredGigE', 'Hu'),
        (r'^GigabitEthernet', 'Gi'),
        (r'^FastEthernet', 'Fa'),
        (r'^AppGigabitEthernet', 'Ap'),
        (r'^Ethernet', 'Et'),
        (r'^Port-channel', 'Po'),
        (r'^Serial', 'Se'),
        (r'^MgmtEth', 'Mg'),
        (r'^Eth', 'Et')
    ]
    for patron, corto in reemplazos:
        p = re.sub(patron, corto, p, flags=re.IGNORECASE)
    return p

def resolver_hostname_inventario(raw_host, ip_vecino, mapa_hosts, mapa_ips):
    """Cruza el nombre o IP del vecino con el inventario real en MySQL para garantizar coincidencia exacta."""
    if ip_vecino and ip_vecino in mapa_ips:
        return mapa_ips[ip_vecino]
    if not raw_host:
        return "Desconocido"
    limpio = raw_host.strip().split('(')[0].split('.')[0].strip()
    if limpio.lower() in mapa_hosts:
        return mapa_hosts[limpio.lower()]
    return limpio

def parsear_cdp_detail(cdp_raw, mapa_hosts, mapa_ips):
    """Extrae vecinos de 'show cdp neighbors detail' (Universal para IOS, IOS-XE y NX-OS)."""
    vecinos = []
    if not cdp_raw or "Invalid input" in cdp_raw or "CDP is not enabled" in cdp_raw:
        return vecinos

    bloques = re.split(r'-{10,}', cdp_raw)
    for bloque in bloques:
        sys_match = re.search(r'System Name:\s*([^\r\n]+)', bloque, re.IGNORECASE)
        dev_match = re.search(r'Device ID:\s*([^\r\n]+)', bloque, re.IGNORECASE)
        raw_name = sys_match.group(1).strip() if (sys_match and sys_match.group(1).strip()) else (dev_match.group(1).strip() if dev_match else None)

        ip_match = re.search(r'IP(?:v4)?\s+address:\s*(\d+\.\d+\.\d+\.\d+)', bloque, re.IGNORECASE)
        ip_vecino = ip_match.group(1) if ip_match else None

        intf_match = re.search(
            r'Interface:\s*([a-zA-Z0-9/\.\-]+)\s*,\s*Port ID \(outgoing port\):\s*([a-zA-Z0-9/\.\-]+)',
            bloque, re.IGNORECASE
        )

        if (raw_name or ip_vecino) and intf_match:
            destino_final = resolver_hostname_inventario(raw_name, ip_vecino, mapa_hosts, mapa_ips)
            vecinos.append({
                "puerto_local": normalizar_puerto(intf_match.group(1)),
                "destino": destino_final,
                "puerto_remoto": normalizar_puerto(intf_match.group(2)),
                "protocolo": "CDP"
            })
    return vecinos

def discover_network():
    result = {"edges": [], "errors": []}

    conn = conectar_db()
    if not conn:
        result["errors"].append("Error BD: No se pudo conectar al servidor MySQL.")
        return result

    try:
        with conn.cursor() as cursor:
            cursor.execute("SELECT * FROM equipos ORDER BY hostname ASC")
            equipos = cursor.fetchall()
        conn.close()
    except Exception as e:
        result["errors"].append(f"Error BD: {str(e)}")
        return result

    # Mapas de referencia rápida para emparejar nombres e IPs con el inventario
    mapa_hosts = {eq['hostname'].strip().lower(): eq['hostname'].strip() for eq in equipos if eq.get('hostname')}
    mapa_ips = {eq['ip_gestion'].strip(): eq['hostname'].strip() for eq in equipos if eq.get('ip_gestion') and eq.get('hostname')}

    # Guarda enlaces direccionales únicos: (origen, puerto_local, destino, puerto_remoto)
    enlaces_registrados = set()

    for eq in equipos:
        hostname = eq.get('hostname', '').strip()
        ip = eq.get('ip_gestion', '').strip()
        plantilla = (eq.get('plantilla_conexion') or 'cisco_ios_legacy').lower()
        user = eq.get('ssh_user') or 'cisco'
        pass_enc = eq.get('ssh_password_encrypted')

        if not ip or not pass_enc:
            continue

        password = decrypt_password(pass_enc)
        if not password:
            result["errors"].append(f"⚠️ {hostname}: No se pudo desencriptar la contraseña SSH.")
            continue

        driver_name = "ios"
        if "nxos" in plantilla:
            driver_name = "nxos_ssh"
        elif "huawei" in plantilla:
            result["errors"].append(f"⚠️ {hostname}: NAPALM no soporta Huawei de forma nativa sin drivers comunitarios.")
            continue

        try:
            driver = get_network_driver(driver_name)
            opt_args = {
                'secret': password,
                'global_delay_factor': 2,
                'disabled_algorithms': {'pubkeys': ['rsa-sha2-256', 'rsa-sha2-512']}
            }

            device = driver(hostname=ip, username=user, password=password, timeout=30, optional_args=opt_args)
            device.open()

            enlaces_equipo = 0

            # 1. Extraer CDP mediante NAPALM CLI (Universal para Catalyst, ISR, Nexus e IOU)
            try:
                cmd_cdp = "show cdp neighbors detail"
                cli_out = device.cli([cmd_cdp])
                cdp_vecinos = parsear_cdp_detail(cli_out.get(cmd_cdp, ""), mapa_hosts, mapa_ips)

                for v in cdp_vecinos:
                    enlaces_equipo += 1
                    llave_direccional = (
                        hostname.lower(),
                        v['puerto_local'].lower(),
                        v['destino'].lower(),
                        v['puerto_remoto'].lower()
                    )
                    if llave_direccional not in enlaces_registrados:
                        enlaces_registrados.add(llave_direccional)
                        result["edges"].append({
                            "origen": hostname,
                            "puerto_local": v["puerto_local"],
                            "destino": v["destino"],
                            "puerto_remoto": v["puerto_remoto"],
                            "protocolo": "CDP"
                        })
            except Exception:
                pass

            # 2. Extraer LLDP mediante getter nativo de NAPALM (Equipos multimarca o con LLDP activo)
            try:
                lldp_neighbors = device.get_lldp_neighbors()
                if lldp_neighbors:
                    for local_port, remotes in lldp_neighbors.items():
                        p_local = normalizar_puerto(local_port)
                        for r in remotes:
                            enlaces_equipo += 1
                            r_host = resolver_hostname_inventario(r.get('hostname', ''), None, mapa_hosts, mapa_ips)
                            p_remoto = normalizar_puerto(r.get('port', ''))
                            llave_direccional = (
                                hostname.lower(),
                                p_local.lower(),
                                r_host.lower(),
                                p_remoto.lower()
                            )
                            if llave_direccional not in enlaces_registrados:
                                enlaces_registrados.add(llave_direccional)
                                result["edges"].append({
                                    "origen": hostname,
                                    "puerto_local": p_local,
                                    "destino": r_host,
                                    "puerto_remoto": p_remoto,
                                    "protocolo": "LLDP"
                                })
            except Exception:
                pass

            if enlaces_equipo == 0:
                result["errors"].append(f"ℹ️ {hostname}: Conectado, pero tablas CDP/LLDP vacías.")

            device.close()

        except Exception as e:
            result["errors"].append(f"❌ Falló {hostname} ({ip}): {str(e)}")

    return result

if __name__ == '__main__':
    data = discover_network()
    print(json.dumps(data))
