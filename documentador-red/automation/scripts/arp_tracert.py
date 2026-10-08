#!/usr/bin/env python3
import sys
import json
import os
import re
import pymysql
import warnings
import paramiko
from netmiko import ConnectHandler, NetmikoTimeoutException, NetmikoAuthenticationException
from vault_crypto import decrypt_password

warnings.filterwarnings("ignore")

# Habilitar compatibilidad universal en Paramiko (Catalyst 3650 modernos + GNS3/IOU/ISR legacy)
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
# CONFIGURACIÓN DE BASE DE DATOS
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

def log_msg(trace_log, msg):
    """Guarda los pasos para que PHP los muestre al usuario"""
    trace_log.append(msg)

def get_device_credentials(identifier):
    """Busca credenciales por IP de gestión, Hostname (sin dominio) o IP de subinterfaz L3."""
    if not identifier:
        return None
    clean_host = identifier.split('.')[0].strip() if not re.match(r'^\d+\.\d+\.\d+\.\d+$', identifier) else identifier.strip()

    try:
        conn = conectar_db()
        if not conn:
            return None
        eq = None
        with conn.cursor() as cursor:
            # 1. Búsqueda directa por ip_gestion o hostname
            cursor.execute(
                "SELECT hostname, ip_gestion, ssh_user, ssh_password_encrypted, plantilla_conexion "
                "FROM equipos WHERE ip_gestion = %s OR hostname = %s OR hostname = %s LIMIT 1",
                (identifier, identifier, clean_host)
            )
            eq = cursor.fetchone()

            # 2. Si el usuario ingresó una IP de subinterfaz (ej. 192.168.10.1), buscar a qué equipo pertenece
            if not eq and re.match(r'^\d+\.\d+\.\d+\.\d+$', identifier):
                cursor.execute(
                    "SELECT e.hostname, e.ip_gestion, e.ssh_user, e.ssh_password_encrypted, e.plantilla_conexion "
                    "FROM puertos_operativos p JOIN equipos e ON p.id_equipo = e.id "
                    "WHERE p.destino LIKE %s LIMIT 1",
                    (f"%{identifier}%",)
                )
                eq = cursor.fetchone()
        conn.close()

        if eq:
            pwd = decrypt_password(eq['ssh_password_encrypted']) or "cisco"
            os_type = "cisco_ios"
            if "nxos" in (eq.get('plantilla_conexion') or '').lower():
                os_type = "cisco_nxos"

            return {
                'device_type': os_type,
                'host': eq['ip_gestion'],
                'username': eq['ssh_user'] or 'cisco',
                'password': pwd,
                'secret': pwd,
                'global_delay_factor': 2,
                'disabled_algorithms': {'pubkeys': ['rsa-sha2-256', 'rsa-sha2-512']},
                '_hostname': eq['hostname']
            }
    except Exception:
        pass
    return None

def extraer_vecino_cdp(cdp_out):
    """Extrae la IP o el Device ID del vecino desde 'show cdp neighbors <port> detail'."""
    ip_match = re.search(r"IP(?:v4)?\s+address:\s*(\d+\.\d+\.\d+\.\d+)", cdp_out, re.IGNORECASE)
    dev_match = re.search(r"Device ID:\s*([^\s\r\n]+)", cdp_out, re.IGNORECASE)
    ip_vecino = ip_match.group(1) if ip_match else None
    nombre_vecino = dev_match.group(1).split('.')[0] if dev_match else None
    return ip_vecino, nombre_vecino

def rastrear_mac_recursivo(device_identifier, mac_target, visitados, trace_log):
    creds = get_device_credentials(device_identifier)
    if not creds:
        log_msg(trace_log, f"❌ No hay credenciales registradas para '{device_identifier}' en la Base de Datos.")
        return None

    real_ip = creds['host']
    hostname = creds.pop('_hostname', real_ip)

    if real_ip in visitados:
        log_msg(trace_log, f"🔄 Bucle o enlace de retorno detectado hacia {hostname} ({real_ip}). Fin de rama.")
        return None
    visitados.add(real_ip)

    log_msg(trace_log, f"🕵️ Conectando a Switch L2: {hostname} ({real_ip})...")

    try:
        with ConnectHandler(**creds) as net_connect:
            net_connect.enable()

            # Buscar MAC en la tabla CAM (Soporta formato IOS clásico, IOS-XE 3650 e IOU)
            mac_table = net_connect.send_command(f"show mac address-table address {mac_target}")
            if "Invalid input" in mac_table or not mac_table.strip():
                mac_table = net_connect.send_command("show mac address-table")

            mac_clean = re.sub(r'[^0-9a-fA-F]', '', mac_target).lower()
            found_line = None
            vlan_line = "1"

            for line in mac_table.splitlines():
                line_hex = re.sub(r'[^0-9a-fA-F]', '', line).lower()
                if mac_clean in line_hex and not line.strip().startswith("Total"):
                    found_line = line
                    stripped = line.lstrip()
                    if stripped.startswith("*"):
                        stripped = stripped[1:].lstrip()
                    v_m = re.match(r"(\d+)", stripped)
                    if v_m:
                        vlan_line = v_m.group(1)
                    break

            if not found_line:
                log_msg(trace_log, f"⚠️ La MAC {mac_target} no figura activa en la tabla CAM de {hostname}.")
                return None

            # Regex universal: Soporta Gi, Fa, Te, Twe, Hu, Po, Eth y Et (GNS3 IOU)
            port_match = re.search(r"\b(Gi|Fa|Te|Twe|Hu|Eth|Et|Po)[a-zA-Z\-]*\d+(?:/\d+)*(?:\.\d+)?\b", found_line, re.IGNORECASE)
            if not port_match:
                if any(x in found_line.upper() for x in ["CPU", "SELF", "ROUTER", "SUP-ETH"]):
                    log_msg(trace_log, f"🎯 ¡DESTINO ENCONTRADO! La MAC corresponde a la propia SVI/CPU de {hostname} ({real_ip}).")
                    return {
                        "switch_ip": real_ip,
                        "switch_hostname": hostname,
                        "port": "CPU / SVI",
                        "current_vlan": vlan_line,
                        "mac": mac_target
                    }
                log_msg(trace_log, f"⚠️ No se pudo interpretar el puerto en la línea: '{found_line.strip()}'")
                return None

            port_full = port_match.group(0)
            log_msg(trace_log, f"📍 MAC aprendida en {hostname} por el puerto: {port_full} (VLAN {vlan_line})")

            # Evaluar si hay otro switch conectado a ese puerto mediante CDP
            int_fisica = port_full.split('.')[0]
            cdp_out = net_connect.send_command(f"show cdp neighbors {int_fisica} detail")
            neighbor_ip, neighbor_name = extraer_vecino_cdp(cdp_out)

            next_target = neighbor_ip or neighbor_name
            if next_target:
                # Verificar que el vecino no sea el Router/Switch del que ya venimos
                next_creds = get_device_credentials(next_target)
                if next_creds and next_creds['host'] in visitados:
                    log_msg(trace_log, f"ℹ️ El vecino en {port_full} ({neighbor_name or neighbor_ip}) ya fue auditado.")
                else:
                    log_msg(trace_log, f"➡ El puerto {port_full} es un enlace Trunk hacia {neighbor_name or ''} ({neighbor_ip or 'vía Hostname'}). ¡Saltando!")
                    return rastrear_mac_recursivo(next_target, mac_target, visitados, trace_log)

            # Si no hay vecinos CDP en ese puerto, hemos llegado al puerto físico de acceso final
            log_msg(trace_log, f"🎯 ¡PUERTO FÍSICO LOCALIZADO! Equipo final conectado en {hostname} ({real_ip}) -> Puerto {port_full}.")
            vlan_out = net_connect.send_command(f"show interfaces {int_fisica} switchport")
            v_match = re.search(r"Access Mode VLAN:\s*(\d+)", vlan_out)
            vl = v_match.group(1) if v_match else vlan_line

            return {
                "switch_ip": real_ip,
                "switch_hostname": hostname,
                "port": port_full,
                "current_vlan": vl,
                "mac": mac_target
            }

    except Exception as e:
        log_msg(trace_log, f"❌ Error conectando a {hostname} ({real_ip}): {str(e)}")
        return None

def iniciar_busqueda(target_ip, gateway_ip):
    result = {"success": False, "trace": [], "final_location": None, "error": None}

    # 1. CONECTAR AL GATEWAY
    log_msg(result["trace"], f"🔌 Conectando al Gateway ({gateway_ip})...")
    creds = get_device_credentials(gateway_ip)

    if not creds:
        result["error"] = f"No se encontró el Gateway ({gateway_ip}) en la Base de Datos. Ingresa su IP de gestión o regístralo en el inventario."
        return result

    gw_real_ip = creds['host']
    gw_hostname = creds.pop('_hostname', gw_real_ip)

    mac_address = None
    salida_int = None
    start_target = gw_real_ip
    visitados = set()

    try:
        with ConnectHandler(**creds) as net_connect:
            net_connect.enable()

            # Ping para despertar la tabla ARP del host y refrescar la tabla MAC en los switches
            log_msg(result["trace"], f"📡 Haciendo ping a {target_ip} desde {gw_hostname} para despertar tablas ARP y MAC...")
            net_connect.send_command(f"ping {target_ip} repeat 3 timeout 1", delay_factor=2)

            # Leer tabla ARP
            arp_out = net_connect.send_command(f"show ip arp {target_ip}")
            match = re.search(r'([0-9a-fA-F]{4}\.[0-9a-fA-F]{4}\.[0-9a-fA-F]{4})\s+\S+\s+([a-zA-Z0-9/\.\-]+)', arp_out)

            if match:
                mac_address = match.group(1).lower()
                salida_int = match.group(2)
                log_msg(result["trace"], f"✅ MAC encontrada en ARP: {mac_address} (Vía: {salida_int})")
            else:
                result["error"] = f"La IP {target_ip} no respondió en la tabla ARP del Gateway ({gw_hostname}). Verifica que el host esté encendido y responda ping."
                return result

            # SALTO L3 -> L2 (Router-on-a-Stick o Puerto Enrutado hacia Switch Core)
            if not salida_int.lower().startswith("vlan") and not salida_int.lower().startswith("bvi"):
                int_fisica = salida_int.split('.')[0]
                log_msg(result["trace"], f"🛣️ Subinterfaz/Puerto L3 detectado ({salida_int}). Buscando vecino CDP en interfaz física {int_fisica}...")
                cdp_out = net_connect.send_command(f"show cdp neighbors {int_fisica} detail")
                neighbor_ip, neighbor_name = extraer_vecino_cdp(cdp_out)

                if neighbor_ip or neighbor_name:
                    start_target = neighbor_ip or neighbor_name
                    visitados.add(gw_real_ip)
                    log_msg(result["trace"], f"➡ Salto L3 a L2 exitoso hacia {neighbor_name or ''} ({neighbor_ip or start_target}).")
                else:
                    log_msg(result["trace"], f"⚠️ No se detectó vecino CDP en {int_fisica}. Evaluando tabla MAC local...")

    except NetmikoTimeoutException:
        result["error"] = f"Timeout intentando conectar por SSH al Gateway {gw_hostname} ({gw_real_ip})."
        return result
    except NetmikoAuthenticationException:
        result["error"] = f"Fallo de Autenticación SSH en el Gateway {gw_hostname} ({gw_real_ip})."
        return result
    except Exception as e:
        result["error"] = f"Error en el Gateway: {str(e)}"
        return result

    # 2. INICIAR RASTREO RECURSIVO EN CAPA 2
    ubicacion = rastrear_mac_recursivo(start_target, mac_address, visitados, result["trace"])

    if ubicacion:
        result["success"] = True
        result["final_location"] = ubicacion
    else:
        result["error"] = "Se encontró la MAC en Capa 3, pero se perdió el rastro en Capa 2. Verifica que CDP esté activo ('cdp run') y que la PC haya enviado tráfico reciente."

    return result

if __name__ == '__main__':
    if len(sys.argv) < 3:
        print(json.dumps({"success": False, "error": "Faltan parámetros IP Objetivo o Gateway."}))
        sys.exit(1)

    t_ip = sys.argv[1]
    g_ip = sys.argv[2]

    output = iniciar_busqueda(t_ip, g_ip)
    print(json.dumps(output))
