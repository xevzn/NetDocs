import pymysql
import sys
import json

DB_HOST = "netdocs_db"
DB_USER = 'root'
DB_PASS = 'root'
DB_NAME = 'red_infraestructura'

def conectar_bd():
    return pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME)

def obtener_equipo(cursor, hostname):
    cursor.execute("SELECT id, tipo FROM equipos WHERE hostname = %s", (hostname,))
    return cursor.fetchone()

def extraer_valor(diccionario, llaves, por_defecto=''):
    """Busca un valor en múltiples llaves posibles de TextFSM/IOS."""
    for k in llaves:
        val = diccionario.get(k)
        if val is not None and str(val).strip() != '':
            return str(val).strip()
    return por_defecto

# --- CAPA 2: PUERTOS FÍSICOS DE SWITCHES (Catalyst 3650 / 2960 / IOU) ---
def sync_puertos_l2(cursor, id_equipo, interfaces):
    cursor.execute("DELETE FROM puertos_operativos WHERE id_equipo = %s", (id_equipo,))
    
    for item in interfaces:
        nombre_puerto = extraer_valor(item, ['port', 'interface', 'intf'], 'Desconocido')
        estado_raw = extraer_valor(item, ['status'], 'down').lower()
        estado = 'up' if estado_raw in ['connected', 'up'] else 'down'
        
        modo_duplex = extraer_valor(item, ['duplex'], 'auto')
        velocidad = extraer_valor(item, ['speed'], 'auto')
        tipo_interfaz = extraer_valor(item, ['type'], 'RJ45/Virtual')
        if tipo_interfaz.lower() == 'unknown':
            tipo_interfaz = 'Virtual/IOU'
            
        destino = extraer_valor(item, ['name', 'description'], '')
        vlan_raw = extraer_valor(item, ['vlan_id', 'vlan'], '1').lower()
        
        if 'trunk' in vlan_raw or 'troncal' in vlan_raw:
            modo_puerto = 'Troncal'
            vlan = 'Trunk (802.1Q)'
        elif 'routed' in vlan_raw:
            modo_puerto = 'Capa 3 (Enrutado)'
            vlan = 'N/A'
        else:
            modo_puerto = 'Acceso'
            vlan = vlan_raw if vlan_raw.isdigit() else '1'

        sql = """INSERT INTO puertos_operativos 
                 (id_equipo, nombre_puerto, estado, modo_duplex, velocidad, tipo_interfaz, modo_puerto, vlan, destino) 
                 VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)"""
        cursor.execute(sql, (id_equipo, nombre_puerto, estado, modo_duplex, velocidad, tipo_interfaz, modo_puerto, vlan, destino))

# --- CAPA 3: INTERFACES DE ROUTERS, SUBINTERFACES Y SVIs DE SWITCHES ---
def sync_puertos_l3(cursor, id_equipo, tipo_equipo, interfaces):
    es_router = 'router' in str(tipo_equipo).lower()
    
    # Si es Router, limpiamos la tabla antes de insertar. Si es Switch, conservamos los puertos L2 ya insertados.
    if es_router:
        cursor.execute("DELETE FROM puertos_operativos WHERE id_equipo = %s", (id_equipo,))
    
    for item in interfaces:
        nombre_puerto = extraer_valor(item, ['interface', 'intf', 'port'], 'Desconocido')
        status_admin = extraer_valor(item, ['status'], 'down').lower()
        proto_line = extraer_valor(item, ['proto', 'protocol'], status_admin).lower()
        ip_addr = extraer_valor(item, ['ip_address', 'ipaddr', 'ip'], 'unassigned')

        # Ignorar subinterfaces fantasma borradas en NVRAM
        if status_admin == 'deleted':
            continue

        # En switches, solo agregamos SVIs (VlanX), Loopbacks o puertos con IP configurada
        if not es_router:
            es_svi = nombre_puerto.lower().startswith('vlan')
            tiene_ip = ip_addr.lower() != 'unassigned'
            if not (es_svi or tiene_ip):
                continue

        estado = 'up' if (status_admin == 'up' and proto_line == 'up') else 'down'

        # Clasificación universal del tipo de interfaz L3
        if '.' in nombre_puerto:
            vlan = nombre_puerto.split('.')[1]
            modo_puerto = 'Gateway Inter-VLAN'
            tipo_int = 'Subinterfaz 802.1Q'
        elif nombre_puerto.lower().startswith('vlan'):
            vlan = ''.join(filter(str.isdigit, nombre_puerto)) or 'N/A'
            modo_puerto = 'SVI (Switch Virtual)'
            tipo_int = 'Interfaz Virtual L3'
        elif nombre_puerto.lower().startswith('serial') or nombre_puerto.lower().startswith('se'):
            vlan = 'N/A'
            modo_puerto = 'Enlace WAN Serial'
            tipo_int = 'Serial WAN'
        elif nombre_puerto.lower().startswith('loopback') or nombre_puerto.lower().startswith('lo'):
            vlan = 'N/A'
            modo_puerto = 'Loopback'
            tipo_int = 'Interfaz Lógica'
        else:
            vlan = 'N/A'
            modo_puerto = 'Capa 3 (Enrutado)'
            tipo_int = 'Puerto Físico L3'

        destino = f"IP: {ip_addr}" if ip_addr.lower() != 'unassigned' else 'Sin IP asignada'

        # Si es un puerto enrutado en un switch que ya existía en L2, lo actualizamos con su IP
        cursor.execute("SELECT id FROM puertos_operativos WHERE id_equipo = %s AND nombre_puerto = %s", (id_equipo, nombre_puerto))
        existente = cursor.fetchone()

        if existente:
            sql = """UPDATE puertos_operativos 
                     SET estado=%s, modo_puerto=%s, vlan=%s, destino=%s, tipo_interfaz=%s 
                     WHERE id=%s"""
            cursor.execute(sql, (estado, modo_puerto, vlan, destino, tipo_int, existente[0]))
        else:
            sql = """INSERT INTO puertos_operativos 
                     (id_equipo, nombre_puerto, estado, modo_duplex, velocidad, tipo_interfaz, modo_puerto, vlan, destino) 
                     VALUES (%s, %s, %s, '-', '-', %s, %s, %s, %s)"""
            cursor.execute(sql, (id_equipo, nombre_puerto, estado, tipo_int, modo_puerto, vlan, destino))

# --- TABLA: tabla_enrutamiento ---
def sync_rutas(cursor, id_equipo, rutas):
    cursor.execute("DELETE FROM tabla_enrutamiento WHERE id_equipo = %s", (id_equipo,))
    for item in rutas:
        proto = extraer_valor(item, ['protocol'], 'S')
        net = extraer_valor(item, ['network'], '0.0.0.0')
        mask = extraer_valor(item, ['prefix_length', 'mask'], '0')
        
        red_destino = f"{net}/{mask}" if mask else net
        next_hop = extraer_valor(item, ['nexthop_ip', 'nexthop'], '')
        interfaz_salida = extraer_valor(item, ['nexthop_if', 'interface'], '')

        sql = "INSERT INTO tabla_enrutamiento (id_equipo, protocolo, red_destino, next_hop, interfaz_salida) VALUES (%s, %s, %s, %s, %s)"
        cursor.execute(sql, (id_equipo, proto, red_destino, next_hop, interfaz_salida))

# --- TABLA: auditoria_seguridad ---
def sync_seguridad(cursor, id_equipo, seguridad):
    cursor.execute("DELETE FROM auditoria_seguridad WHERE id_equipo = %s", (id_equipo,))
    ssh = extraer_valor(seguridad, ['ssh_version'], 'Desconocido')
    telnet = extraer_valor(seguridad, ['telnet_enabled'], 'False')
    usuarios = extraer_valor(seguridad, ['users_list'], '0_users')
    
    try:
        acls = int(seguridad.get('acl_count', 0))
    except (ValueError, TypeError):
        acls = 0

    sql = "INSERT INTO auditoria_seguridad (id_equipo, ssh_version, telnet_enabled, users_list, acl_count) VALUES (%s, %s, %s, %s, %s)"
    cursor.execute(sql, (id_equipo, ssh, telnet, usuarios, acls))

def main():
    if len(sys.argv) < 4:
        sys.exit(1)

    hostname = sys.argv[1]
    tipo_dato = sys.argv[2]
    json_data = sys.argv[3]

    try:
        datos = json.loads(json_data)
        conexion = conectar_bd()
        cursor = conexion.cursor()

        equipo = obtener_equipo(cursor, hostname)
        if equipo:
            id_equipo, tipo_equipo = equipo[0], equipo[1]
            if tipo_dato == "puertos_l2": sync_puertos_l2(cursor, id_equipo, datos)
            elif tipo_dato == "puertos_l3": sync_puertos_l3(cursor, id_equipo, tipo_equipo, datos)
            elif tipo_dato == "rutas": sync_rutas(cursor, id_equipo, datos)
            elif tipo_dato == "seguridad": sync_seguridad(cursor, id_equipo, datos)
            conexion.commit()
            print(f"[{hostname}] -> {tipo_dato.upper()} sincronizado en BD.")
        conexion.close()
    except Exception as e:
        print(f"Error procesando {tipo_dato} para {hostname}: {e}")

if __name__ == "__main__":
    main()
