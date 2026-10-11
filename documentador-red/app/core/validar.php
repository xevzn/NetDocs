<?php
// app/core/validar.php
// Validadores de entrada reutilizables (lista blanca, siempre en el servidor).

class Validar
{
    /** Lee un campo POST como cadena; si falta o es un arreglo devuelve $defecto. */
    public static function post(string $clave, string $defecto = ''): string
    {
        $v = $_POST[$clave] ?? null;
        return is_string($v) ? trim($v) : $defecto;
    }

    /** Lee un campo GET como cadena. */
    public static function get(string $clave, string $defecto = ''): string
    {
        $v = $_GET[$clave] ?? null;
        return is_string($v) ? trim($v) : $defecto;
    }

    /** Entero positivo estricto (solo dígitos) o null. */
    public static function entero($valor): ?int
    {
        if (is_int($valor)) {
            return $valor > 0 ? $valor : null;
        }
        if (is_string($valor) && ctype_digit($valor) && strlen($valor) <= 10) {
            $n = (int)$valor;
            return $n > 0 ? $n : null;
        }
        return null;
    }

    public static function texto(string $s, int $max): string
    {
        return mb_substr(trim($s), 0, $max);
    }

    public static function hostname(string $s): bool
    {
        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.\-]{0,99}\z/', $s) === 1;
    }

    public static function usuario(string $s): bool
    {
        return preg_match('/\A[A-Za-z0-9_.\-]{3,50}\z/', $s) === 1;
    }

    public static function ipv4(string $s): bool
    {
        return filter_var($s, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    public static function cidr(string $s): bool
    {
        $p = explode('/', $s);
        return count($p) === 2
            && self::ipv4($p[0])
            && ctype_digit($p[1]) && (int)$p[1] >= 0 && (int)$p[1] <= 32;
    }

    public static function mac(string $s): bool
    {
        return preg_match('/\A(?:[0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}\z/', $s) === 1
            || preg_match('/\A(?:[0-9A-Fa-f]{4}\.){2}[0-9A-Fa-f]{4}\z/', $s) === 1;
    }

    public static function vlan(string $s): ?int
    {
        if (preg_match('/\A[0-9]{1,4}\z/', $s) !== 1) {
            return null;
        }
        $n = (int)$s;
        return ($n >= 1 && $n <= 4094) ? $n : null;
    }

    public static function enLista(string $s, array $permitidos): bool
    {
        return in_array($s, $permitidos, true);
    }

    /** Escapa para HTML (atributos y contenido). */
    public static function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
