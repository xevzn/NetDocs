<?php
require_once __DIR__ . '/../models/log.php';

class AutomatizacionController {

    // ========================================================
    // RUTAS DINÁMICAS PORTABLES (Funciona en cualquier Docker/Servidor)
    // ========================================================
    private static function baseDir(): string {
        return realpath(__DIR__ . '/../../') ?: '/var/www/html/documentador-red';
    }

    // ========================================================
    // HELPER INTERNO: Ejecuta scripts Python y blinda JSON/Null en PHP 8.3
    // ========================================================
    private static function ejecutarScriptJson(string $cmd): array {
        $raw_output = shell_exec($cmd . " 2>&1");
        $str_output = is_string($raw_output) ? trim($raw_output) : '';

        if ($str_output === '') {
            return [
                'ok' => false,
                'data' => null,
                'raw' => 'El script de Python no devolvió respuesta (salida vacía).'
            ];
        }

        // 1. Intentar decodificar directamente si la salida es JSON puro
        $decoded = json_decode($str_output, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return ['ok' => true, 'data' => $decoded, 'raw' => $str_output];
        }

        // 2. Si Paramiko/Netmiko imprimió advertencias antes del JSON, extraer solo el bloque {...}
        $inicio = strpos($str_output, '{');
        $fin = strrpos($str_output, '}');
        if ($inicio !== false && $fin !== false && $fin > $inicio) {
            $sub_json = substr($str_output, $inicio, ($fin - $inicio) + 1);
            $decoded = json_decode($sub_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return ['ok' => true, 'data' => $decoded, 'raw' => $str_output];
            }
        }

        return [
            'ok' => false,
            'data' => null,
            'raw' => $str_output
        ];
    }
    
    // Muestra la vista principal del Centro de Operaciones (Solo lectura)
    public static function auditoriaAnsible() {
        session_start();
        if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] > 2) {
            header("Location: /documentador-red/dashboard");
            exit();
        }

        require_once __DIR__ . '/../core/database.php';
        $db = Database::conectar();
        $stmt = $db->query("SELECT hostname FROM equipos ORDER BY hostname ASC");
        $equipos_lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

        require_once __DIR__ . '/../views/automatizacion/auditoria.php';
    }

    // ========================================================
    //  1. EJECUCIÓN DEL ORQUESTADOR (Aprovisionamiento)
    // ========================================================
    public static function ejecutarOrquestador() {
        session_start();
        if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] > 2) {
            die("Acceso denegado.");
        }

        $base = self::baseDir();
        $ansible_dir = "{$base}/automation/ansible_project";
        $inventory_script = "{$base}/automation/scripts/dynamic_inventory.py";
        $playbook = "playbooks/netdocs_orquestador.yml"; 

        $env_vars = "ANSIBLE_HOST_KEY_CHECKING=False " .
                    "ANSIBLE_NETWORK_CLI_SSH_TYPE=paramiko " .
                    "ANSIBLE_PERSISTENT_COMMAND_TIMEOUT=60 " .
                    "ANSIBLE_PERSISTENT_CONNECT_TIMEOUT=60 ";

        $limite_cmd = "";
        $target = "Todos los equipos";
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['modo_ejecucion_orq'])) {
            $modo = $_POST['modo_ejecucion_orq'];
            $target = "";
            
            if ($modo === 'grupo' && !empty($_POST['grupo_objetivo_orq'])) {
                $target = $_POST['grupo_objetivo_orq'];
            } elseif ($modo === 'especifico' && !empty($_POST['equipos_especificos_orq'])) {
                $target = implode(',', $_POST['equipos_especificos_orq']);
            }

            if (!empty($target) && preg_match('/^[a-zA-Z0-9_\-\.,]+$/', $target)) {
                $limite_cmd = "--limit " . escapeshellarg($target);
            } else {
                $target = "Todos los equipos";
            }
        }

        $comando = "cd " . escapeshellarg($ansible_dir) . " && {$env_vars} ansible-playbook -i " . escapeshellarg($inventory_script) . " {$playbook} {$limite_cmd} 2>&1";
        $salida_terminal = shell_exec($comando);

        $_SESSION['orquestador_output'] = $salida_terminal;
        Log::registrar('Orquestador Ansible', 'Aprovisionamiento de Red', "Se ejecutó el orquestador principal. Objetivo: {$target}.");

        header("Location: /documentador-red/auditoria-ansible"); 
        exit();
    }

    // ========================================================
    //  2. EJECUCIÓN DE AUDITORÍA (Extracción a MySQL)
    // ========================================================
    public static function ejecutarAuditoria() {
        session_start();
        if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] > 2) {
            die("Acceso denegado.");
        }

        $base = self::baseDir();
        $ansible_dir = "{$base}/automation/ansible_project";
        $inventory_script = "{$base}/automation/scripts/dynamic_inventory.py";
        $playbook = "playbooks/network_info.yml"; 

        $env_vars = "ANSIBLE_HOST_KEY_CHECKING=False " .
                    "ANSIBLE_NETWORK_CLI_SSH_TYPE=paramiko " .
                    "ANSIBLE_PERSISTENT_COMMAND_TIMEOUT=60 " .
                    "ANSIBLE_PERSISTENT_CONNECT_TIMEOUT=60 ";

        $limite_cmd = "";
        $target = "Todos los equipos";
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['modo_ejecucion_aud'])) {
            $modo = $_POST['modo_ejecucion_aud'];
            $target = "";
            
            if ($modo === 'grupo' && !empty($_POST['grupo_objetivo_aud'])) {
                $target = $_POST['grupo_objetivo_aud'];
            } elseif ($modo === 'especifico' && !empty($_POST['equipos_especificos_aud'])) {
                $target = implode(',', $_POST['equipos_especificos_aud']);
            }

            if (!empty($target) && preg_match('/^[a-zA-Z0-9_\-\.,]+$/', $target)) {
                $limite_cmd = "--limit " . escapeshellarg($target);
            } else {
                $target = "Todos los equipos";
            }
        }

        $comando = "cd " . escapeshellarg($ansible_dir) . " && {$env_vars} ansible-playbook -i " . escapeshellarg($inventory_script) . " {$playbook} {$limite_cmd} 2>&1";
        $salida_terminal = shell_exec($comando);

        $_SESSION['auditoria_output'] = $salida_terminal;
        Log::registrar('Orquestador Ansible', 'Auditoría de Red', "Se ejecutó la recolección de configuración de red. Objetivo: {$target}.");

        header("Location: /documentador-red/auditoria-ansible");
        exit();
    }

    // ========================================================
    //  EJECUCIÓN DE TELEMETRÍA (Python + SNMP)
    // ========================================================
    public static function ejecutarSnmp() {
        session_start();
        if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] > 2) {
            die("Acceso denegado.");
        }

        $script_python = self::baseDir() . "/automation/scripts/snmp_poller.py";

        if (!file_exists($script_python)) {
            $_SESSION['snmp_output'] = "Error: No se encontró el script snmp_poller.py en {$script_python}.";
            header("Location: /documentador-red/auditoria-ansible");
            exit();
        }

        $comando = "python3 " . escapeshellarg($script_python) . " 2>&1";
        $salida_terminal = shell_exec($comando);

        $_SESSION['snmp_output'] = $salida_terminal ?: "Ejecución finalizada sin salida.";
        Log::registrar('Python Automation', 'Telemetría SNMP Manual', "Se forzó un ciclo manual de recolección de telemetría por SNMP en toda la red.");

        header("Location: /documentador-red/auditoria-ansible");
        exit();
    }
    
    public static function ejecutarBackup() {
        session_start();
        if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] > 2) {
            die("Acceso denegado.");
        }

        $base = self::baseDir();
        $ansible_dir = "{$base}/automation/ansible_project";
        $inventory_script = "{$base}/automation/scripts/dynamic_inventory.py";
        $playbook = "playbooks/backup.yml"; 

        $env_vars = "ANSIBLE_HOST_KEY_CHECKING=False " .
                    "ANSIBLE_NETWORK_CLI_SSH_TYPE=paramiko " .
                    "ANSIBLE_PERSISTENT_COMMAND_TIMEOUT=60 " .
                    "ANSIBLE_PERSISTENT_CONNECT_TIMEOUT=60 ";

        $limite_cmd = "";
        $target = "Todos los equipos";
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['modo_ejecucion'])) {
            $modo = $_POST['modo_ejecucion'];
            $target = "";
            
            if ($modo === 'grupo' && !empty($_POST['grupo_objetivo'])) {
                $target = $_POST['grupo_objetivo'];
            } elseif ($modo === 'especifico' && !empty($_POST['equipos_especificos'])) {
                $target = implode(',', $_POST['equipos_especificos']);
            }

            if (!empty($target) && preg_match('/^[a-zA-Z0-9_\-\.,]+$/', $target)) {
                $limite_cmd = "--limit " . escapeshellarg($target);
            } else {
                $target = "Todos los equipos";
            }
        }

        $comando = "cd " . escapeshellarg($ansible_dir) . " && {$env_vars} ansible-playbook -i " . escapeshellarg($inventory_script) . " {$playbook} {$limite_cmd} 2>&1";
        $salida_terminal = shell_exec($comando);

        $_SESSION['backup_output'] = $salida_terminal;
        $_SESSION['backup_status'] = strpos((string)$salida_terminal, 'failed=0') !== false ? 'exito' : 'advertencia';

        Log::registrar('Orquestador Ansible', 'Respaldo de Configuración', "Se disparó la tarea de Backup. Objetivo: {$target}. Estado: " . $_SESSION['backup_status']);

        header("Location: /documentador-red/respaldos");
        exit();
    }
    
    // =======================================================
    // MOTOR DE DESCUBRIMIENTO NAPALM
    // =======================================================
    public static function ejecutarNapalm() {
        session_start();
        if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] > 2) { die("Acceso denegado."); }

        $script = self::baseDir() . "/automation/scripts/napalm_discovery.py";
        $res = self::ejecutarScriptJson("python3 " . escapeshellarg($script));
        $data = $res['data'];

        if ($res['ok'] && isset($data['edges'])) {
            require_once __DIR__ . '/../core/database.php';
            $db = Database::conectar();
            
            $db->query("TRUNCATE TABLE topologia_enlaces");
            $stmt = $db->prepare("INSERT INTO topologia_enlaces (origen, puerto_local, destino, puerto_remoto, protocolo) VALUES (?, ?, ?, ?, ?)");
            
            $enlaces_guardados = 0;
            foreach ($data['edges'] as $edge) {
                $stmt->execute([$edge['origen'], $edge['puerto_local'], $edge['destino'], $edge['puerto_remoto'], $edge['protocolo']]);
                $enlaces_guardados++;
            }
            
            $_SESSION['napalm_success'] = "¡Éxito! Se descubrieron y guardaron {$enlaces_guardados} enlaces físicos.";
            $_SESSION['napalm_errors'] = $data['errors'] ?? [];
            
            Log::registrar('Python Automation', 'Descubrimiento NAPALM', "Se reescribió la topología de red LLDP/CDP. Enlaces descubiertos: {$enlaces_guardados}.");
            
        } else {
            $_SESSION['napalm_error_fatal'] = "Fallo en la ejecución del script Python. Salida: " . htmlspecialchars($res['raw']);
            Log::registrar('Python Automation', 'Fallo NAPALM', "El script de descubrimiento falló al intentar ejecutarse.");
        }

        header("Location: /documentador-red/descubrimiento-napalm");
        exit();
    }

    // =======================================================
    // BUSCADOR ARP / MAC (Conectado a arp_tracert.py)
    // =======================================================
    public static function ejecutarRastreoArp() {
        session_start();
        if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] > 2) { die("Acceso denegado."); }

        $target_ip = trim($_POST['target_ip'] ?? '');
        $gateway_ip = trim($_POST['gateway_ip'] ?? '');

        if (empty($target_ip) || empty($gateway_ip)) {
            $_SESSION['arp_error'] = "Ambas IPs son obligatorias.";
            header("Location: /documentador-red/buscador-arp");
            exit();
        }

        if (!filter_var($target_ip, FILTER_VALIDATE_IP) || !filter_var($gateway_ip, FILTER_VALIDATE_IP)) {
            $_SESSION['arp_error'] = "Formato de IP inválido.";
            header("Location: /documentador-red/buscador-arp");
            exit();
        }

        // Soporta arp_tracert.py (nombre actual en tu carpeta) o arp_tracer.py
        $script = self::baseDir() . "/automation/scripts/arp_tracert.py";
        if (!file_exists($script)) {
            $script = self::baseDir() . "/automation/scripts/arp_tracer.py";
        }

        $cmd = "python3 " . escapeshellarg($script) . " " . escapeshellarg($target_ip) . " " . escapeshellarg($gateway_ip);
        $res = self::ejecutarScriptJson($cmd);

        if ($res['ok'] && !empty($res['data'])) {
            $_SESSION['arp_result'] = $res['data'];
        } else {
            $_SESSION['arp_error'] = "Error ejecutando el script. Detalle: " . htmlspecialchars($res['raw']);
        }

        header("Location: /documentador-red/buscador-arp");
        exit();
    }

    // =======================================================
    // GESTIÓN DE VLANs
    // =======================================================
    public static function consultarVlan() {
        session_start();
        $ip = trim($_POST['switch_ip'] ?? '');
        $port = trim($_POST['port'] ?? '');

        if (empty($ip) || empty($port)) {
            $_SESSION['vlan_error'] = "La IP del switch y el puerto son obligatorios.";
            header("Location: /documentador-red/gestion-vlans?tab=manual");
            exit();
        }

        $script = self::baseDir() . "/automation/scripts/vlan_manager.py";
        $cmd = "python3 " . escapeshellarg($script) . " info " . escapeshellarg($ip) . " " . escapeshellarg($port);
        
        $res = self::ejecutarScriptJson($cmd);
        $output = $res['data'];

        if ($res['ok'] && !empty($output['success'])) {
            $_SESSION['vlan_info'] = $output['data'];
        } else {
            $detalle_err = $output['error'] ?? ("Error al consultar el puerto. Detalle: " . htmlspecialchars($res['raw']));
            $_SESSION['vlan_error'] = $detalle_err;
        }
        header("Location: /documentador-red/gestion-vlans?tab=manual");
        exit();
    }

    // CRÍTICO: Modifica configuración en la red
    public static function aplicarCambioVlan() {
        session_start();
        $ip = trim($_POST['switch_ip'] ?? '');
        $port = trim($_POST['port'] ?? '');
        $vlan = trim($_POST['new_vlan'] ?? '');
        $tab = $_POST['tab_origen'] ?? 'manual';

        if (empty($ip) || empty($port) || empty($vlan)) {
            $_SESSION['vlan_error'] = "Todos los campos (Switch IP, Puerto y Nueva VLAN) son obligatorios.";
            header("Location: /documentador-red/gestion-vlans?tab=" . urlencode($tab));
            exit();
        }

        $script = self::baseDir() . "/automation/scripts/vlan_manager.py";
        $cmd = "python3 " . escapeshellarg($script) . " set " . escapeshellarg($ip) . " " . escapeshellarg($port) . " " . escapeshellarg($vlan);
        
        $res = self::ejecutarScriptJson($cmd);
        $output = $res['data'];

        if ($res['ok'] && !empty($output['success'])) {
            $_SESSION['vlan_success'] = $output['msg'] ?? "VLAN actualizada correctamente.";
            unset($_SESSION['vlan_info']); 
            unset($_SESSION['vlan_trace']); 
            
            Log::registrar('Gestión de Red', 'Cambio de VLAN (Netmiko)', "Se asignó vía automatización la VLAN {$vlan} al puerto {$port} en el equipo IP {$ip}.");
            
        } else {
            $detalle_err = $output['error'] ?? ("Error al aplicar el cambio de VLAN. Detalle: " . htmlspecialchars($res['raw']));
            $_SESSION['vlan_error'] = $detalle_err;
            
            Log::registrar('Gestión de Red', 'Fallo al cambiar VLAN', "Se intentó fallidamente asignar la VLAN {$vlan} al puerto {$port} en el equipo IP {$ip}.");
        }
        header("Location: /documentador-red/gestion-vlans?tab=" . urlencode($tab));
        exit();
    }

    public static function rastrearParaVlan() {
        session_start();
        $target_ip = trim($_POST['target_ip'] ?? '');
        $gateway_ip = trim($_POST['gateway_ip'] ?? '');

        if (empty($target_ip) || empty($gateway_ip)) {
            $_SESSION['vlan_error'] = "La IP del dispositivo y la IP del Gateway son obligatorias.";
            header("Location: /documentador-red/gestion-vlans?tab=auto");
            exit();
        }

        $script = self::baseDir() . "/automation/scripts/arp_tracert.py";
        if (!file_exists($script)) {
            $script = self::baseDir() . "/automation/scripts/arp_tracer.py";
        }

        $cmd = "python3 " . escapeshellarg($script) . " " . escapeshellarg($target_ip) . " " . escapeshellarg($gateway_ip);
        
        $res = self::ejecutarScriptJson($cmd);
        $output = $res['data'];

        if ($res['ok'] && !empty($output['success'])) {
            $_SESSION['vlan_trace'] = $output['final_location'];
        } else {
            $detalle_err = $output['error'] ?? ("No se pudo localizar el dispositivo en la Capa 2. Detalle: " . htmlspecialchars($res['raw']));
            $_SESSION['vlan_error'] = $detalle_err;
        }
        header("Location: /documentador-red/gestion-vlans?tab=auto");
        exit();
    }

    public static function obtenerEstadisticas() {
        session_start();
        if (!isset($_SESSION['usuario_id'])) die();
        
        require_once __DIR__ . '/../core/database.php';
        $db = Database::conectar();

        $data = [
            'salud' => $db->query("SELECT h.* FROM historial_salud h 
                                  INNER JOIN (SELECT id_equipo, MAX(timestamp) as ts FROM historial_salud GROUP BY id_equipo) ult 
                                  ON h.id_equipo = ult.id_equipo AND h.timestamp = ult.ts")->fetchAll(PDO::FETCH_ASSOC),
            'seguridad' => $db->query("SELECT * FROM auditoria_seguridad")->fetchAll(PDO::FETCH_ASSOC),
            'infra' => $db->query("SELECT estado, count(*) as total FROM puertos_operativos GROUP BY estado")->fetchAll(PDO::FETCH_ASSOC)
        ];

        header('Content-Type: application/json');
        echo json_encode($data);
    }

    // ========================================================
    // API PARA EL MONITOR EN VIVO (Ruta snmp_live.py corregida)
    // ========================================================
    public static function apiLiveSnmp() {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['usuario_id'])) {
            echo json_encode(["error" => "No autorizado"]);
            exit();
        }

        $id_equipo = $_GET['id_equipo'] ?? null;
        if (!$id_equipo) {
            echo json_encode(["error" => "ID de equipo no proporcionado"]);
            exit();
        }

        require_once __DIR__ . '/../core/database.php';
        $db = Database::conectar();
        
        $stmt = $db->prepare("SELECT ip_gestion, snmp_community FROM equipos WHERE id = :id");
        $stmt->execute([':id' => $id_equipo]);
        $equipo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$equipo || empty($equipo['snmp_community'])) {
            echo json_encode(["error" => "El equipo no existe o no tiene comunidad SNMP configurada"]);
            exit();
        }

        $ip = $equipo['ip_gestion'];
        $comunidad = $equipo['snmp_community'];
        $script_python = self::baseDir() . "/automation/scripts/snmp_live.py";

        $comando = "python3 " . escapeshellarg($script_python) . " " . escapeshellarg($ip) . " " . escapeshellarg($comunidad);
        $salida = shell_exec($comando);

        if ($salida) {
            echo $salida;
        } else {
            echo json_encode(["error" => "Fallo al ejecutar el script snmp_live.py"]);
        }
        exit();
    }

    // ========================================================
    // VISTA: MONITOR SNMP EN VIVO
    // ========================================================
    public static function monitorVivo() {
        session_start();
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: /documentador-red/login");
            exit();
        }

        require_once __DIR__ . '/../core/database.php';
        $db = Database::conectar();
        
        $stmt = $db->query("SELECT id, hostname, ip_gestion FROM equipos WHERE snmp_community IS NOT NULL ORDER BY hostname");
        $equipos_snmp = $stmt->fetchAll(PDO::FETCH_ASSOC);

        require_once __DIR__ . '/../views/automatizacion/monitor_vivo.php';
    }

    // ========================================================
    // VISTA: REPORTE DE AUDITORÍA (Estadísticas MVC)
    // ========================================================
    public static function verEstadisticas() {
        session_start();
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: /documentador-red/dashboard");
            exit();
        }

        require_once __DIR__ . '/../models/reporteansible.php';

        $filtro_equipo = $_GET['equipo'] ?? 'todos';
        $filtro_marca = $_GET['marca'] ?? 'todas';

        $filtros_listas = ReporteAnsible::obtenerFiltros();
        $equipos_list = $filtros_listas['equipos'];
        $marcas_list = $filtros_listas['marcas'];

        $datos = ReporteAnsible::obtenerEstadisticas($filtro_equipo, $filtro_marca);
        extract($datos); 

        require_once __DIR__ . '/../views/automatizacion/estadisticas.php';
    }

    // ========================================================
    // VISTA: GESTIÓN DE RESPALDOS Y COMPARACIÓN (DIFF)
    // ========================================================
    public static function gestionRespaldos() {
        session_start();
        if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] > 2) {
            header("Location: /documentador-red/dashboard");
            exit();
        }

        $backup_dir = self::baseDir() . "/automation/backups";
        if (!is_dir($backup_dir)) {
            $backup_dir = self::baseDir() . "/automation/ansible_project/backups";
        }

        // 1. Descarga directa de archivos con registro de auditoría
        if (isset($_GET['descargar']) && isset($_GET['equipo'])) {
            $file = basename($_GET['descargar']);
            $equipo = basename($_GET['equipo']);
            $path = "$backup_dir/$equipo/$file";
            
            if (is_file($path)) {
                require_once __DIR__ . '/../models/log.php';
                Log::registrar('Seguridad', 'Descarga de Configuración', "Se descargó el respaldo {$file} del equipo {$equipo}.");
                
                header('Content-Description: File Transfer');
                header('Content-Type: text/plain');
                header('Content-Disposition: attachment; filename="'.$equipo.'_'.$file.'"');
                readfile($path);
                exit();
            }
        }

        // 2. Obtener lista de equipos para el selector de aprovisionamiento
        require_once __DIR__ . '/../core/database.php';
        $db = Database::conectar();
        $stmt = $db->query("SELECT hostname FROM equipos ORDER BY hostname ASC");
        $equipos_lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Lectura dinámica de directorios (Backups existentes por Hostname)
        $equipos = [];
        if (is_dir($backup_dir)) {
            $carpetas = array_diff(scandir($backup_dir), array('..', '.'));
            foreach ($carpetas as $carpeta) {
                $ruta_equipo = "$backup_dir/$carpeta";
                if (is_dir($ruta_equipo)) {
                    $archivos_raw = array_diff(scandir($ruta_equipo), array('..', '.'));
                    $archivos = [];
                    foreach ($archivos_raw as $arc) {
                        if (is_file("$ruta_equipo/$arc") && preg_match('/\.(txt|cfg|conf)$/i', $arc)) {
                            $archivos[] = $arc;
                        }
                    }
                    rsort($archivos);
                    if (!empty($archivos)) {
                        $equipos[$carpeta] = $archivos;
                    }
                }
            }
        }
        ksort($equipos);

        // 4. Seleccionar el equipo actual a inspeccionar
        $equipo_sel = $_GET['equipo'] ?? (empty($equipos) ? null : array_key_first($equipos));
        $archivos_equipo = ($equipo_sel && isset($equipos[$equipo_sel])) ? $equipos[$equipo_sel] : [];
        $archivo_actual = $_GET['ver_archivo'] ?? ($archivos_equipo[0] ?? null);

        $contenido_archivo = "";
        if ($equipo_sel && $archivo_actual) {
            $ruta_lectura = "$backup_dir/" . basename($equipo_sel) . "/" . basename($archivo_actual);
            if (is_file($ruta_lectura)) { 
                $contenido_archivo = file_get_contents($ruta_lectura); 
            }
        }

        // 5. Lógica de comparación de archivos (Diff / Time Machine)
        $diff_html = "";
        if (isset($_POST['comparar']) && !empty($_POST['file_a']) && !empty($_POST['file_b'])) {
            $resolverRuta = function($input, $eq_default) use ($backup_dir) {
                if (strpos($input, '/') !== false) {
                    [$eq, $fn] = explode('/', $input, 2);
                    return "$backup_dir/" . basename($eq) . "/" . basename($fn);
                }
                return "$backup_dir/" . basename($eq_default) . "/" . basename($input);
            };

            $ruta_a = $resolverRuta($_POST['file_a'], $equipo_sel);
            $ruta_b = $resolverRuta($_POST['file_b'], $equipo_sel);

            if (is_file($ruta_a) && is_file($ruta_b)) {
                $file_a = escapeshellarg($ruta_a);
                $file_b = escapeshellarg($ruta_b);
                
                $diff_output = shell_exec("diff -u $file_a $file_b");
                
                if (empty($diff_output)) {
                    $diff_html = "<div style='color: var(--port-up); font-weight:bold; padding: 15px; background: rgba(16, 185, 129, 0.1); border: 1px solid var(--port-up); border-radius: 4px;'>[OK] Las configuraciones seleccionadas son idénticas. No se detectaron alteraciones.</div>";
                } else {
                    $diff_html = "<div style='font-family: monospace; background-color: #000; padding: 15px; border-radius: 6px; border: 1px solid var(--border-color); font-size: 0.9em; line-height: 1.5; max-height: 550px; overflow-y: auto;'>";
                    $lines = explode("\n", htmlspecialchars($diff_output));
                    foreach ($lines as $line) {
                        if (strpos($line, '---') === 0 || strpos($line, '+++') === 0) {
                            $diff_html .= "<div style='color: var(--text-muted); font-weight: bold;'>$line</div>";
                        } elseif (strpos($line, '@@') === 0) {
                            $diff_html .= "<div style='color: #3b82f6; margin-top: 10px; font-weight: bold;'>$line</div>";
                        } elseif (strpos($line, '+') === 0) {
                            $diff_html .= "<div style='color: #10b981; background: rgba(16, 185, 129, 0.12); padding: 1px 6px;'>$line</div>";
                        } elseif (strpos($line, '-') === 0) {
                            $diff_html .= "<div style='color: #ef4444; background: rgba(239, 68, 68, 0.12); padding: 1px 6px;'>$line</div>";
                        } else {
                            $diff_html .= "<div style='color: var(--text-main); padding: 1px 6px;'>$line</div>";
                        }
                    }
                    $diff_html .= "</div>";
                    
                    require_once __DIR__ . '/../models/log.php';
                    Log::registrar('Auditoría', 'Comparación de Backups', "Se ejecutó la herramienta Diff entre {$_POST['file_a']} y {$_POST['file_b']}.");
                }
            }
        }

        require_once __DIR__ . '/../views/automatizacion/respaldos.php';
    }
}
