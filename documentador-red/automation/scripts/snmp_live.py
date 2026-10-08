#!/usr/bin/env python3
import sys
import json
from snmp_poller import get_all_health_metrics

def main():
    if len(sys.argv) < 3:
        print(json.dumps({"error": "Faltan argumentos. Uso: snmp_live.py <IP> <COMUNIDAD>"}))
        sys.exit(1)

    ip = sys.argv[1].strip()
    comunidad = sys.argv[2].strip()

    cpu, mem_used, mem_free, temp = get_all_health_metrics(ip, comunidad)

    if cpu is None and mem_used is None and mem_free is None:
        print(json.dumps({"error": "El equipo no responde o SNMP no está configurado", "status": "offline"}))
        sys.exit(0)

    cpu = cpu if cpu is not None else 0
    mem_total = 0
    mem_percent = 0
    if mem_used is not None and mem_free is not None:
        mem_total = mem_used + mem_free
        if mem_total > 0:
            mem_percent = round((mem_used / mem_total) * 100, 2)
    else:
        mem_used, mem_free, mem_percent = 0, 0, 0

    if temp is None or temp <= 0 or temp >= 200:
        temp = 0

    respuesta = {
        "status": "online",
        "cpu_load": cpu,
        "memory_used_bytes": mem_used,
        "memory_free_bytes": mem_free,
        "memory_used_percent": mem_percent,
        "temperatura": temp
    }

    print(json.dumps(respuesta))

if __name__ == "__main__":
    main()
