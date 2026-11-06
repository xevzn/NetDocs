#!/usr/bin/env python3
import os
import sys
import json
from snmp_poller import get_all_health_metrics

def main():
    # La comunidad se recibe por la variable SNMP_COMMUNITY (no aparece en la lista de procesos);
    # se mantiene el 2.º argumento solo por compatibilidad.
    comunidad = os.environ.get("SNMP_COMMUNITY") or (sys.argv[2].strip() if len(sys.argv) > 2 else "")
    if len(sys.argv) < 2 or not comunidad:
        print(json.dumps({"error": "Faltan argumentos. Uso: SNMP_COMMUNITY=... snmp_live.py <IP>"}))
        sys.exit(1)

    ip = sys.argv[1].strip()

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
