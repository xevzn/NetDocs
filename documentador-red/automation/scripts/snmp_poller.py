#!/usr/bin/env python3
import socket
import random
import os
import pymysql
from datetime import datetime

# ==========================================
# CONFIGURACIÓN DE BASE DE DATOS
# ==========================================
DB_HOSTS = [os.getenv("MYSQL_HOST", "db"), "netdocs_db", "127.0.0.1"]
DB_USER = os.getenv("MYSQL_USER")
DB_PASS = os.getenv("MYSQL_ROOT_PASSWORD")
DB_NAME = os.getenv("MYSQL_DATABASE", "red_infraestructura")
if not DB_USER or not DB_PASS:
    raise RuntimeError("Faltan las variables MYSQL_USER y MYSQL_ROOT_PASSWORD.")

# ==========================================
# FAMILIAS DE OIDs UNIVERSALES CISCO (IOS-XE 3650 / ISR + IOS Clásico / GNS3 IOU)
# ==========================================
OIDS_CPU = [
    '1.3.6.1.4.1.9.9.109.1.1.1.1.6',  # cpmCPUTotal5secRev (IOS-XE / Catalyst 3650)
    '1.3.6.1.4.1.9.9.109.1.1.1.1.8',  # cpmCPUTotal1minRev (IOS-XE / Catalyst 3650)
    '1.3.6.1.4.1.9.9.109.1.1.1.1.3',  # cpmCPUTotal5sec
    '1.3.6.1.4.1.9.9.109.1.1.1.1.5',  # cpmCPUTotal1min
    '1.3.6.1.4.1.9.2.1.56',           # busyPer 5-sec (OLD-CISCO-CPU-MIB - GNS3 IOU / IOS Legacy)
    '1.3.6.1.4.1.9.2.1.57'            # avgBusy1 1-min (OLD-CISCO-CPU-MIB - GNS3 IOU / IOS Legacy)
]

OIDS_MEM_USED = [
    '1.3.6.1.4.1.9.9.48.1.1.1.5',     # ciscoMemoryPoolUsed (Catalyst / ISR)
    '1.3.6.1.4.1.9.9.221.1.1.1.1.7'   # cempMemPoolUsed (IOS-XE)
]

OIDS_MEM_FREE = [
    '1.3.6.1.4.1.9.9.48.1.1.1.6',     # ciscoMemoryPoolFree (Catalyst / ISR)
    '1.3.6.1.4.1.9.9.221.1.1.1.1.8',  # cempMemPoolFree (IOS-XE)
    '1.3.6.1.4.1.9.2.1.8'             # freeMem (OLD-CISCO-SYS-MIB - GNS3 IOU)
]

OID_LEGACY_TOTAL_RAM = '1.3.6.1.4.1.9.3.6.6'  # processorRam (Total RAM bytes)
OID_LEGACY_FREE_RAM  = '1.3.6.1.4.1.9.2.1.8'  # freeMem (Free RAM bytes)

OIDS_TEMP = [
    '1.3.6.1.4.1.9.9.13.1.3.1.3',     # ciscoEnvMonTemperatureStatusValue (Catalyst 3650/2960/ISR)
    '1.3.6.1.4.1.9.9.91.1.1.1.1.4'    # entSensorValue (IOS-XE / Nexus)
]

# ==========================================
# MOTOR SNMPv2c NATIVO
# ==========================================
def _encode_length(length):
    if length < 0x80:
        return bytes([length])
    length_bytes = []
    while length > 0:
        length_bytes.insert(0, length & 0xFF)
        length >>= 8
    return bytes([0x80 | len(length_bytes)] + length_bytes)

def _encode_tlv(tag, value_bytes):
    return bytes([tag]) + _encode_length(len(value_bytes)) + value_bytes

def _encode_oid(oid_str):
    parts = [int(x) for x in oid_str.strip('.').split('.')]
    oid_bytes = [parts[0] * 40 + parts[1]]
    for sub_id in parts[2:]:
        if sub_id == 0:
            oid_bytes.append(0)
        else:
            sub_bytes = []
            while sub_id > 0:
                sub_bytes.insert(0, (sub_id & 0x7F) | (0x80 if sub_bytes else 0x00))
                sub_id >>= 7
            oid_bytes.extend(sub_bytes)
    return _encode_tlv(0x06, bytes(oid_bytes))

def _decode_oid(oid_bytes):
    if not oid_bytes:
        return ""
    parts = [str(oid_bytes[0] // 40), str(oid_bytes[0] % 40)]
    val = 0
    for b in oid_bytes[1:]:
        val = (val << 7) | (b & 0x7F)
        if not (b & 0x80):
            parts.append(str(val))
            val = 0
    return ".".join(parts)

def _read_tlv(data, offset=0):
    if offset >= len(data):
        return None, b"", offset
    tag = data[offset]
    length = data[offset + 1]
    if length & 0x80:
        num_bytes = length & 0x7F
        length = int.from_bytes(data[offset + 2 : offset + 2 + num_bytes], 'big')
        val_start = offset + 2 + num_bytes
    else:
        val_start = offset + 2
    val_end = val_start + length
    return tag, data[val_start:val_end], val_end

def _snmp_request(ip, community, oid_str, pdu_type=0xA0, timeout=1.5):
    req_id = random.randint(10000, 999999)
    req_id_bytes = _encode_tlv(0x02, req_id.to_bytes(4, 'big'))
    err_status = _encode_tlv(0x02, b'\x00')
    err_index = _encode_tlv(0x02, b'\x00')

    varbind = _encode_tlv(0x30, _encode_oid(oid_str) + b'\x05\x00')
    varbind_list = _encode_tlv(0x30, varbind)
    pdu = _encode_tlv(pdu_type, req_id_bytes + err_status + err_index + varbind_list)

    version = _encode_tlv(0x02, b'\x01')
    comm = _encode_tlv(0x04, community.encode('utf-8'))
    packet = _encode_tlv(0x30, version + comm + pdu)

    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.settimeout(timeout)
    try:
        sock.sendto(packet, (ip, 161))
        resp, _ = sock.recvfrom(4096)
    except Exception:
        return None, None
    finally:
        sock.close()

    try:
        tag, seq_val, _ = _read_tlv(resp, 0)
        if tag != 0x30:
            return None, None
        _, _, pos = _read_tlv(seq_val, 0)
        _, _, pos = _read_tlv(seq_val, pos)
        pdu_tag, pdu_val, _ = _read_tlv(seq_val, pos)
        if pdu_tag != 0xA2:
            return None, None

        _, _, p_pos = _read_tlv(pdu_val, 0)
        _, err_val, p_pos = _read_tlv(pdu_val, p_pos)
        if int.from_bytes(err_val, 'big', signed=True) != 0:
            return None, None
        _, _, p_pos = _read_tlv(pdu_val, p_pos)
        _, vbl_val, _ = _read_tlv(pdu_val, p_pos)
        _, vb_val, _ = _read_tlv(vbl_val, 0)

        _, oid_raw, vb_pos = _read_tlv(vb_val, 0)
        val_tag, val_raw, _ = _read_tlv(vb_val, vb_pos)

        resp_oid = _decode_oid(oid_raw)
        if val_tag in (0x02, 0x41, 0x42, 0x43, 0x46):
            return resp_oid, int.from_bytes(val_raw, 'big', signed=(val_tag == 0x02))
        return resp_oid, None
    except Exception:
        return None, None

def get_universal_metric(ip, community, base_oids):
    for base_oid in base_oids:
        for suffix in ('.0', '.1'):
            _, val = _snmp_request(ip, community, f"{base_oid}{suffix}", pdu_type=0xA0)
            if val is not None:
                return val
        resp_oid, val = _snmp_request(ip, community, base_oid, pdu_type=0xA1)
        if resp_oid and resp_oid.startswith(base_oid + ".") and val is not None:
            return val
    return None

def get_all_health_metrics(ip, community):
    """Retorna (cpu_pct, mem_used_bytes, mem_free_bytes, temp_celsius)."""
    cpu = get_universal_metric(ip, community, OIDS_CPU)
    mem_used = get_universal_metric(ip, community, OIDS_MEM_USED)
    mem_free = get_universal_metric(ip, community, OIDS_MEM_FREE)
    temp = get_universal_metric(ip, community, OIDS_TEMP)

    if mem_used is None:
        total_ram = get_universal_metric(ip, community, [OID_LEGACY_TOTAL_RAM])
        free_ram = mem_free if mem_free is not None else get_universal_metric(ip, community, [OID_LEGACY_FREE_RAM])
        if total_ram is not None and free_ram is not None and total_ram >= free_ram:
            mem_used = total_ram - free_ram
            mem_free = free_ram

    return cpu, mem_used, mem_free, temp

def conectar_bd():
    for host in DB_HOSTS:
        try:
            return pymysql.connect(host=host, user=DB_USER, password=DB_PASS, database=DB_NAME, connect_timeout=3)
        except Exception:
            continue
    return None

def main():
    print(f"[{datetime.now()}] Iniciando recolección SNMP NOC...")

    conexion = conectar_bd()
    if not conexion:
        print("Error conectando a la BD MySQL.")
        return

    cursor = conexion.cursor(pymysql.cursors.DictCursor)
    cursor.execute("SELECT id, hostname, ip_gestion, snmp_community FROM equipos WHERE snmp_community IS NOT NULL AND TRIM(snmp_community) != ''")
    equipos = cursor.fetchall()

    if not equipos:
        print("No hay equipos con comunidad SNMP configurada en la BD.")
        conexion.close()
        return

    for eq in equipos:
        eq_id = eq['id']
        hostname = eq['hostname']
        ip = eq['ip_gestion']
        comunidad = eq['snmp_community'].strip()

        print(f" Consultando {hostname} ({ip})...", end="", flush=True)

        cpu, mem_used_bytes, mem_free_bytes, temp = get_all_health_metrics(ip, comunidad)

        if cpu is None and mem_used_bytes is None and mem_free_bytes is None:
            print(" [TIMEOUT / OFFLINE]")
            continue

        cpu = cpu if cpu is not None else 0
        temp = temp if (temp is not None and 0 < temp < 200) else 0

        # Calcular porcentaje de RAM (0 a 100) para que el Mapa de Topología y Alertas lo evalúen correctamente
        mem_used_pct = 0
        mem_free_pct = 100
        if mem_used_bytes is not None and mem_free_bytes is not None:
            total_mem = mem_used_bytes + mem_free_bytes
            if total_mem > 0:
                mem_used_pct = int(round((mem_used_bytes / total_mem) * 100))
                mem_free_pct = 100 - mem_used_pct

        try:
            sql = """
                INSERT INTO historial_salud (id_equipo, cpu_load, memory_used, memory_free, temperatura, timestamp)
                VALUES (%s, %s, %s, %s, %s, NOW())
            """
            cursor.execute(sql, (eq_id, cpu, mem_used_pct, mem_free_pct, temp))
            conexion.commit()
            print(f" [OK] CPU: {cpu}% | RAM Usada: {mem_used_pct}% ({round((mem_used_bytes or 0) / 1048576, 1)} MB) | Temp: {temp}°C")
        except Exception as e:
            print(f" [ERROR BD] {e}")

    conexion.close()
    print(f"[{datetime.now()}] Recolección finalizada exitosamente.")

if __name__ == "__main__":
    main()
