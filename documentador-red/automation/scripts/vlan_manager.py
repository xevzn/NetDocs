#!/usr/bin/env python3
import ipaddress
import os
import sys
import json
import re
import pymysql
import time
import warnings
from netmiko import ConnectHandler
from vault_crypto import decrypt_password

warnings.filterwarnings("ignore")

DB_HOST = os.getenv("MYSQL_HOST", "db")
DB_USER = os.getenv("MYSQL_USER")
DB_PASS = os.getenv("MYSQL_ROOT_PASSWORD")
DB_NAME = os.getenv("MYSQL_DATABASE", "red_infraestructura")
if not DB_USER or not DB_PASS:
    raise RuntimeError("Faltan las variables MYSQL_USER y MYSQL_ROOT_PASSWORD.")

INTERFACE_PATTERN = re.compile(
    r"(?:Et|Ethernet|Gi|GigabitEthernet|Fa|FastEthernet|Te|TenGigabitEthernet|"
    r"Twe|TwentyFiveGigE|Fo|FortyGigabitEthernet|Hu|HundredGigE|Po|Port-channel)"
    r"[0-9]{1,3}(?:/[0-9]{1,3}){0,3}",
    re.IGNORECASE
)

def validar_ip_puerto(ip, port):
    if not isinstance(ip, str) or not isinstance(port, str):
        return False
    try:
        ipaddress.IPv4Address(ip)
    except ipaddress.AddressValueError:
        return False
    return INTERFACE_PATTERN.fullmatch(port) is not None

def validar_vlan(vlan):
    if not isinstance(vlan, str) or re.fullmatch(r"[0-9]{1,4}", vlan) is None:
        return None
    vlan_id = int(vlan)
    return vlan_id if 1 <= vlan_id <= 4094 else None

def get_device_credentials(ip):
    try:
        ipaddress.IPv4Address(ip)
    except (ipaddress.AddressValueError, TypeError):
        return None

    try:
        conn = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME, cursorclass=pymysql.cursors.DictCursor)
        with conn.cursor() as cursor:
            cursor.execute("SELECT ssh_user, ssh_password_encrypted, plantilla_conexion FROM equipos WHERE ip_gestion = %s", (ip,))
            eq = cursor.fetchone()
        conn.close()
        
        if eq:
            pwd = decrypt_password(eq['ssh_password_encrypted'])
            os_type = "cisco_ios"
            if "nxos" in eq.get('plantilla_conexion', '').lower(): os_type = "cisco_nxos"
            return {'device_type': os_type, 'host': ip, 'username': eq['ssh_user'], 'password': pwd, 'secret': pwd, 'global_delay_factor': 2}
    except: pass
    return None

def get_info(ip, port):
    if not validar_ip_puerto(ip, port):
        return {"success": False, "error": "IP o interfaz física no válida."}
    creds = get_device_credentials(ip)
    if not creds: return {"success": False, "error": f"Sin credenciales en BD para {ip}"}
    try:
        with ConnectHandler(**creds) as net_connect:
            net_connect.enable()
            output = net_connect.send_command(f"show interfaces {port} switchport")
            if "Invalid input" in output or "incomplete" in output:
                return {"success": False, "error": "Interfaz inválida o comando no soportado."}
            vlan_match = re.search(r"Access Mode VLAN:\s*(\d+)", output)
            vlan = vlan_match.group(1) if vlan_match else "Trunk/Unknown"
            return {"success": True, "data": {"switch_ip": ip, "port": port, "current_vlan": vlan}}
    except Exception as e:
        return {"success": False, "error": str(e)}

def set_vlan(ip, port, vlan):
    if not validar_ip_puerto(ip, port):
        return {"success": False, "error": "IP o interfaz física no válida."}
    vlan_id = validar_vlan(vlan)
    if vlan_id is None:
        return {"success": False, "error": "La VLAN debe ser un número entre 1 y 4094."}
    creds = get_device_credentials(ip)
    if not creds: return {"success": False, "error": f"Sin credenciales en BD para {ip}"}
    try:
        with ConnectHandler(**creds) as net_connect:
            net_connect.enable()
            cmds = [f"interface {port}", f"switchport access vlan {vlan_id}", "shutdown"]
            net_connect.send_config_set(cmds)
            time.sleep(2) # Simular desconexión
            net_connect.send_config_set([f"interface {port}", "no shutdown"])
            net_connect.send_command("write memory")
            return {"success": True, "msg": f"Puerto {port} asignado a la VLAN {vlan} y reiniciado exitosamente."}
    except Exception as e:
        return {"success": False, "error": str(e)}

if __name__ == '__main__':
    if len(sys.argv) >= 2 and sys.argv[1] == "info" and len(sys.argv) == 4:
        ip, port = sys.argv[2:]
        print(json.dumps(get_info(ip, port)))
    elif len(sys.argv) >= 2 and sys.argv[1] == "set" and len(sys.argv) == 5:
        ip, port, vlan = sys.argv[2:]
        print(json.dumps(set_vlan(ip, port, vlan)))
    else:
        print(json.dumps({"success": False, "error": "Acción o cantidad de parámetros inválida."}))
        sys.exit(1)