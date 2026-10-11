<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validar.php';
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
    private static function ejecutarScriptJson(array $cmd, array $envExtra = []): array {
        $process = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['redirect', 1]
        ], $pipes, null, $envExtra ? array_merge(getenv(), $envExtra) : null);

        $raw_output = null;
        if (is_resource($process)) {
            fclose($pipes[0]);
            $raw_output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            proc_close($process);
        }

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
    
    // Ejecuta un comando (arreglo, sin shell) y devuelve su salida combinada.
    private static function salidaDeProceso(array $cmd): ?string {
        $p = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
        if (!is_resource($p)) {
            return null;
        }
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($p);
        return $out === false ? null : $out;
    }

    // Muestra la vista principal del Centro de Operaciones (Solo lectura)
    public static function auditoriaAnsible() {
        Auth::require(Auth::STAFF);

        require_once __DIR__ . '/../core/database.php';
        $db = Database::conectar();
        $stmt = $db->query("SELECT hostname FROM equipos ORDER BY hostname ASC");
        $equipos_lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

        require_once __DIR__ . '/../views/automatizacion/auditoria.php';
    }

    // ========================================================
    //  1. EJECUCIÓN DEL ORQUESTADOR (Aprovisionamiento)
    // ========================================================



    // ========================================================
    //  2. EJECUCIÓN DE AUDITORÍA (Extracción a MySQL)
    // ========================================================



    // ========================================================
    //  EJECUCIÓN DE TELEMETRÍA (Python + SNMP)
    // ========================================================
    public static function ejecutarSnmp() {
        Auth::require(Auth::STAFF);

        $script_python = self::baseDir() . "/automation/scripts/snmp_poller.py";

        if (!file_exists($script_python)) {
            $_SESSION['snmp_output'] = "Error: No se encontró el script snmp_poller.py.";
            header("Location: /documentador-red/auditoria-ansible");
            exit();
        }

        $res = self::ejecutarScriptJson(['python3', $script_python]);

        $_SESSION['snmp_output'] = $res['raw'] !== '' ? $res['raw'] : "Ejecución finalizada sin salida.";
        Log::registrar('Python Automation', 'Telemetría SNMP Manual', "Se forzó un ciclo manual de recolección de telemetría por SNMP en toda la red.");

        header("Location: /documentador-red/auditoria-ansible");
        exit();
    }

    
    // ========================================================
    // EJECUCIÓN GENÉRICA DE PLAYBOOKS (sin shell, con bloqueo y tiempo límite)
    // ========================================================
    private static function ejecutarPlaybook(string $playbook, string $sufijo, string $claveSalida,
                                             string $modulo, string $accion, string $descripcion,
                                             string $redirect, ?string $claveEstado = null): void {
        Auth::require(Auth::STAFF);

        $base = self::baseDir();
        $ansible_dir = "{$base}/automation/ansible_project";
        $inventory_script = "{$base}/automation/scripts/dynamic_inventory.py";

        // Objetivo: todos, un grupo o equipos específicos (solo caracteres seguros)
        $target = "Todos los equipos";
        $limite = [];
        $modo = Validar::post('modo_ejecucion' . $sufijo);
        if ($modo !== '') {
            $candidato = '';
            if ($modo === 'grupo') {
                $candidato = Validar::post('grupo_objetivo' . $sufijo);
            } elseif ($modo === 'especifico') {
                $lista = $_POST['equipos_especificos' . $sufijo] ?? [];
                $lista = is_array($lista) ? array_filter($lista, 'is_string') : [];
                $candidato = implode(',', $lista);
            }
            if ($candidato !== '' && preg_match('/\A[a-zA-Z0-9_\-\.,]+\z/', $candidato) === 1) {
                $target = $candidato;
                $limite = ['--limit', $candidato];
            }
        }

        // Una sola ejecución de Ansible a la vez (evita sesiones SSH concurrentes sobre los mismos equipos)
        $lock = fopen(sys_get_temp_dir() . '/netdocs_ansible.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            $_SESSION[$claveSalida] = "Ya hay una ejecución de Ansible en curso. Espere a que termine e inténtelo de nuevo.";
            header("Location: {$redirect}");
            exit();
        }

        $cmd = array_merge(
            ['timeout', '--kill-after=30', '1500', 'ansible-playbook', '-i', $inventory_script, $playbook],
            $limite
        );
        $env = array_merge(getenv(), [
            'ANSIBLE_HOST_KEY_CHECKING'       => 'False',
            'ANSIBLE_NETWORK_CLI_SSH_TYPE'    => 'paramiko',
            'ANSIBLE_PERSISTENT_COMMAND_TIMEOUT' => '60',
            'ANSIBLE_PERSISTENT_CONNECT_TIMEOUT' => '60',
        ]);

        $salida = '';
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, $ansible_dir, $env);
        if (is_resource($proc)) {
            fclose($pipes[0]);
            $salida = (string)stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            proc_close($proc);
        } else {
            $salida = 'No se pudo iniciar ansible-playbook.';
        }
        flock($lock, LOCK_UN);
        fclose($lock);

        $_SESSION[$claveSalida] = mb_substr($salida, 0, 200000);

        $detalle = "{$descripcion} Objetivo: {$target}.";
        if ($claveEstado !== null) {
            preg_match_all('/failed=(\d+)/', $salida, $m);
            $_SESSION[$claveEstado] = (!empty($m[1]) && array_sum(array_map('intval', $m[1])) === 0) ? 'exito' : 'advertencia';
            $detalle .= " Estado: " . $_SESSION[$claveEstado];
        }
        Log::registrar($modulo, $accion, $detalle);

        header("Location: {$redirect}");
        exit();
    }

    public static function ejecutarOrquestador() {
        self::ejecutarPlaybook('playbooks/netdocs_orquestador.yml', '_orq', 'orquestador_output',
            'Orquestador Ansible', 'Aprovisionamiento de Red', 'Se ejecutó el orquestador principal.',
            '/documentador-red/auditoria-ansible');
    }

    public static function ejecutarAuditoria() {
        self::ejecutarPlaybook('playbooks/network_info.yml', '_aud', 'auditoria_output',
            'Orquestador Ansible', 'Auditoría de Red', 'Se ejecutó la recolección de configuración de red.',
            '/documentador-red/auditoria-ansible');
    }

    public static function ejecutarBackup() {
        self::ejecutarPlaybook('playbooks/backup.yml', '', 'backup_output',
            'Orquestador Ansible', 'Respaldo de Configuración', 'Se disparó la tarea de Backup.',
            '/documentador-red/respaldos', 'backup_status');
    }

    
    // =======================================================
    // MOTOR DE DESCUBRIMIENTO NAPALM
    // =======================================================
    public static function ejecutarNapalm() {
        Auth::require(Auth::STAFF);

        $script = self::baseDir() . "/automation/scripts/napalm_discovery.py";
        $res = self::ejecutarScriptJson(['python3', $script]);
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
        Auth::require(Auth::STAFF);

        $target_ip = $_POST['target_ip'] ?? '';
        $gateway_ip = $_POST['gateway_ip'] ?? '';
        if (!is_string($target_ip) || !is_string($gateway_ip)) {
            $_SESSION['arp_error'] = "Formato de IP inválido.";
            header("Location: /documentador-red/buscador-arp");
            exit();
        }

        $target_ip = trim($target_ip);
        $gateway_ip = trim($gateway_ip);

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

        $res = self::ejecutarScriptJson(['python3', $script, $target_ip, $gateway_ip]);

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
        Auth::require(Auth::STAFF);

        $ip = Validar::post('switch_ip');
        $port = Validar::post('port');

        if ($ip === '' || $port === '') {
            $_SESSION['vlan_error'] = "La IP del switch y el puerto son obligatorios.";
            header("Location: /documentador-red/gestion-vlans?tab=manual");
            exit();
        }

        if (!Validar::ipv4($ip) || preg_match('~\A[A-Za-z][A-Za-z\-]*[0-9]+(?:/[0-9]+){0,3}\z~', $port) !== 1) {
            $_SESSION['vlan_error'] = "IP o interfaz fuera del formato permitido.";
            header("Location: /documentador-red/gestion-vlans?tab=manual");
            exit();
        }

        $script = self::baseDir() . "/automation/scripts/vlan_manager.py";
        $res = self::ejecutarScriptJson(['python3', $script, 'info', $ip, $port]);
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
        Auth::start();
        if (!isset($_SESSION['usuario_id']) || !in_array((int)($_SESSION['rol_id'] ?? 0), [1, 2], true)) {
            http_response_code(403);
            die("Acceso denegado.");
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            die("Método no permitido.");
        }

        $request_token = $_POST['csrf_vlan_token'] ?? null;
        $session_token = $_SESSION['csrf_vlan_token'] ?? null;
        if (!is_string($request_token) || !is_string($session_token) || !hash_equals($session_token, $request_token)) {
            http_response_code(403);
            die("Solicitud no válida.");
        }

        $tab_origen = $_POST['tab_origen'] ?? 'manual';
        $tab = is_string($tab_origen) && in_array($tab_origen, ['manual', 'auto'], true)
            ? $tab_origen
            : 'manual';
        $ip = $_POST['switch_ip'] ?? null;
        $port = $_POST['port'] ?? null;
        $vlan = $_POST['new_vlan'] ?? null;

        if (!is_string($ip) || !is_string($port) || !is_string($vlan)) {
            $_SESSION['vlan_error'] = "Formato de IP, puerto o VLAN inválido.";
            header("Location: /documentador-red/gestion-vlans?tab=" . urlencode($tab));
            exit();
        }

        $ip = trim($ip);
        $port = trim($port);
        $vlan = trim($vlan);

        if (empty($ip) || empty($port) || empty($vlan)) {
            $_SESSION['vlan_error'] = "Todos los campos (Switch IP, Puerto y Nueva VLAN) son obligatorios.";
            header("Location: /documentador-red/gestion-vlans?tab=" . urlencode($tab));
            exit();
        }

        $valid_port = preg_match('~\A(?:Et|Ethernet|Gi|GigabitEthernet|Fa|FastEthernet|Te|TenGigabitEthernet|Twe|TwentyFiveGigE|Fo|FortyGigabitEthernet|Hu|HundredGigE|Po|Port-channel)[0-9]+(?:/[0-9]+){0,3}\z~i', $port) === 1;
        $valid_vlan = preg_match('/\A[0-9]{1,4}\z/', $vlan) === 1 && (int)$vlan >= 1 && (int)$vlan <= 4094;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false || !$valid_port || !$valid_vlan) {
            $_SESSION['vlan_error'] = "IP, puerto físico o VLAN fuera del formato permitido.";
            header("Location: /documentador-red/gestion-vlans?tab=" . urlencode($tab));
            exit();
        }

        $script = self::baseDir() . "/automation/scripts/vlan_manager.py";
        $res = self::ejecutarScriptJson(['python3', $script, 'set', $ip, $port, (string)(int)$vlan]);
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
        Auth::require(Auth::STAFF);

        $target_ip = Validar::post('target_ip');
        $gateway_ip = Validar::post('gateway_ip');

        if ($target_ip === '' || $gateway_ip === '') {
            $_SESSION['vlan_error'] = "La IP del dispositivo y la IP del Gateway son obligatorias.";
            header("Location: /documentador-red/gestion-vlans?tab=auto");
            exit();
        }

        if (!Validar::ipv4($target_ip) || !Validar::ipv4($gateway_ip)) {
            $_SESSION['vlan_error'] = "Formato de IP inválido.";
            header("Location: /documentador-red/gestion-vlans?tab=auto");
            exit();
        }

        $script = self::baseDir() . "/automation/scripts/arp_tracert.py";
        if (!file_exists($script)) {
            $script = self::baseDir() . "/automation/scripts/arp_tracer.py";
        }

        $res = self::ejecutarScriptJson(['python3', $script, $target_ip, $gateway_ip]);
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
        Auth::start();
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
        Auth::start();
        header('Content-Type: application/json');

        $id_equipo = Validar::entero($_GET['id_equipo'] ?? null);
        if ($id_equipo === null) {
            echo json_encode(["error" => "ID de equipo no proporcionado"]);
            exit();
        }

        require_once __DIR__ . '/../core/database.php';
        $db = Database::conectar();

        $stmt = $db->prepare("SELECT ip_gestion, snmp_community FROM equipos WHERE id = :id");
        $stmt->execute([':id' => $id_equipo]);
        $equipo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$equipo || empty($equipo['snmp_community']) || !Validar::ipv4((string)$equipo['ip_gestion'])) {
            echo json_encode(["error" => "El equipo no existe o no tiene comunidad SNMP configurada"]);
            exit();
        }

        $script_python = self::baseDir() . "/automation/scripts/snmp_live.py";

        // La comunidad viaja por variable de entorno (no por argumentos visibles en `ps`)
        $res = self::ejecutarScriptJson(['python3', $script_python, $equipo['ip_gestion']], ['SNMP_COMMUNITY' => $equipo['snmp_community']]);

        if ($res['ok']) {
            echo json_encode($res['data']);
        } else {
            echo json_encode(["error" => "Fallo al ejecutar el script snmp_live.py"]);
        }
        exit();
    }


    // ========================================================
    // VISTA: MONITOR SNMP EN VIVO
    // ========================================================
    public static function monitorVivo() {
        Auth::start();
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
        Auth::start();
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: /documentador-red/dashboard");
            exit();
        }

        require_once __DIR__ . '/../models/reporteansible.php';

        $filtro_equipo = Validar::get('equipo', 'todos');
        $filtro_marca = Validar::get('marca', 'todas');

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
        Auth::require(Auth::STAFF);

        $backup_dir = self::baseDir() . "/automation/backups";
        if (!is_dir($backup_dir)) {
            $backup_dir = self::baseDir() . "/automation/ansible_project/backups";
        }

        // 1. Descarga directa de archivos con registro de auditoría
        $nombreSeguro = fn(string $v): string => preg_replace('/[^A-Za-z0-9._\-]/', '_', basename($v));

        if (Validar::get('descargar') !== '' && Validar::get('equipo') !== '') {
            $file = $nombreSeguro(Validar::get('descargar'));
            $equipo = $nombreSeguro(Validar::get('equipo'));
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
        $equipo_sel = Validar::get('equipo') !== '' ? Validar::get('equipo') : (empty($equipos) ? null : array_key_first($equipos));
        $archivos_equipo = ($equipo_sel && isset($equipos[$equipo_sel])) ? $equipos[$equipo_sel] : [];
        $archivo_actual = Validar::get('ver_archivo') !== '' ? Validar::get('ver_archivo') : ($archivos_equipo[0] ?? null);

        $contenido_archivo = "";
        if ($equipo_sel && $archivo_actual) {
            $ruta_lectura = "$backup_dir/" . basename($equipo_sel) . "/" . basename($archivo_actual);
            if (is_file($ruta_lectura)) { 
                $contenido_archivo = file_get_contents($ruta_lectura); 
            }
        }

        // 5. Lógica de comparación de archivos (Diff / Time Machine)
        $diff_html = "";
        if (isset($_POST['comparar']) && Validar::post('file_a') !== '' && Validar::post('file_b') !== '') {
            $resolverRuta = function($input, $eq_default) use ($backup_dir) {
                if (strpos($input, '/') !== false) {
                    [$eq, $fn] = explode('/', $input, 2);
                    return "$backup_dir/" . basename($eq) . "/" . basename($fn);
                }
                return "$backup_dir/" . basename($eq_default) . "/" . basename($input);
            };

            $ruta_a = $resolverRuta(Validar::post('file_a'), (string)$equipo_sel);
            $ruta_b = $resolverRuta(Validar::post('file_b'), (string)$equipo_sel);

            if (is_file($ruta_a) && is_file($ruta_b)) {
                $diff_output = (string)self::salidaDeProceso(['diff', '-u', $ruta_a, $ruta_b]);
                
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
                    Log::registrar('Auditoría', 'Comparación de Backups', "Se ejecutó la herramienta Diff entre " . basename($ruta_a) . " y " . basename($ruta_b) . ".");
                }
            }
        }

        require_once __DIR__ . '/../views/automatizacion/respaldos.php';
    }
}
