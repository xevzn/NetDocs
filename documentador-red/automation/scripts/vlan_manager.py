#!/usr/bin/env python3
import sys
import json
import re
import base64
import pymysql
import time
import warnings
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from cryptography.hazmat.backends import default_backend
from cryptography.hazmat.primitives import padding
from netmiko import ConnectHandler

warnings.filterwarnings("ignore")

DB_HOST = "db"
DB_USER = "root"
DB_PASS = "root"
DB_NAME = "red_infraestructura"
VAULT_MASTER_KEY = "0507_netdocs_master_key_2026"

def decrypt_password(encoded_payload):
    if not encoded_payload: return None
    try:
        key = VAULT_MASTER_KEY.encode('utf-8').ljust(32, b'\0')[:32]
        decoded = base64.b64decode(encoded_payload)
        parts = decoded.split(b'::')
        if len(parts) != 2: return None
        cipher = Cipher(algorithms.AES(key), modes.CBC(parts[1]), backend=default_backend())
        decryptor = cipher.decryptor()
        padded = decryptor.update(base64.b64decode(parts[0])) + decryptor.finalize()
        unpadder = padding.PKCS7(algorithms.AES.block_size).unpadder()
        return (unpadder.update(padded) + unpadder.finalize()).decode('utf-8')
    except: return None

def get_device_credentials(ip):
    try:
        conn = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME, cursorclass=pymysql.cursors.DictCursor)
        with conn.cursor() as cursor:
            cursor.execute("SELECT ssh_user, ssh_password_encrypted, plantilla_conexion FROM equipos WHERE ip_gestion = %s OR hostname = %s", (ip, ip))
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
    if any(x in port for x in ["CPU", "Management", "Vlan", "BVI"]):
        return {"success": False, "error": "Puerto virtual o especial no válido."}
    creds = get_device_credentials(ip)
    if not creds: return {"success": False, "error": f"Sin credenciales en BD para {ip}"}
    try:
        with ConnectHandler(**creds) as net_connect:
            net_connect.enable()
            cmds = [f"interface {port}", f"switchport access vlan {vlan}", "shutdown"]
            net_connect.send_config_set(cmds)
            time.sleep(2) # Simular desconexión
            net_connect.send_config_set([f"interface {port}", "no shutdown"])
            net_connect.send_command("write memory")
            return {"success": True, "msg": f"Puerto {port} asignado a la VLAN {vlan} y reiniciado exitosamente."}
    except Exception as e:
        return {"success": False, "error": str(e)}

if __name__ == '__main__':
    if len(sys.argv) < 4:
        print(json.dumps({"success": False, "error": "Faltan parámetros"}))
        sys.exit(1)
    
    action = sys.argv[1]
    ip = sys.argv[2]
    port = sys.argv[3]
    
    if action == "info":
        print(json.dumps(get_info(ip, port)))
    elif action == "set" and len(sys.argv) >= 5:
        vlan = sys.argv[4]
        print(json.dumps(set_vlan(ip, port, vlan)))
    else:
        print(json.dumps({"success": False, "error": "Acción inválida"}))