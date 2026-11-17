<?php
/**
 * Request inspection helpers used by the router (App.php) and the base Controller.
 * Pattern checks are a coarse first line; real protection comes from prepared statements,
 * output escaping and CSRF tokens in the application code.
 */
class Security {

    public static function detect_sqli($input) {
        if (is_array($input)) {
            foreach ($input as $v) if (self::detect_sqli($v)) return true;
            return false;
        }
        if (!is_string($input) || strlen($input) < 4) return false;
        $patterns = [
            '/\b(select\s+[`a-zA-Z0-9_*,.\s]+\s+from\s+[`a-zA-Z0-9_.]+)/i',
            '/\b(union\s+(all\s+)?select\b)/i',
            '/\b(insert\s+into\s+[`a-zA-Z0-9_.]+)/i',
            '/\b(update\s+[`a-zA-Z0-9_.]+\s+set\s+[`a-zA-Z0-9_.]+\s*=)/i',
            '/\b(delete\s+from\s+[`a-zA-Z0-9_.]+)/i',
            '/\b(drop\s+(table|database|view|trigger))\b/i',
            '/\b(alter\s+table)\b/i',
            '/\b(exec(\s|\+)+(s|x)p\w+)/i',
            '/\b(benchmark\s*\(|sleep\s*\(|waitfor\s+delay)\b/i',
            '/(\'|\%27)\s*(\-\-|\#|\/\*)/i',
            '/(\'|\%27)\s*(or|and)\s+(\'|")?[0-9a-zA-Z]+(\'|")?\s*=\s*(\'|")?[0-9a-zA-Z]+/i',
            '/\b(information_schema\.|sys\.tables|sys\.databases)\b/i',
        ];
        foreach ($patterns as $pattern) if (preg_match($pattern, $input)) return true;
        return false;
    }

    public static function detect_rce_payload($input) {
        if (is_array($input)) {
            foreach ($input as $v) if (self::detect_rce_payload($v)) return true;
            return false;
        }
        if (!is_string($input) || strlen($input) < 3) return false;
        $patterns = [
            '/\b(system|exec|passthru|shell_exec|proc_open|popen|pcntl_exec)\s*\(/i',
            '/\b(eval|assert|create_function)\s*\(|`[^`]+`/i',
            '/(php:\/\/filter|php:\/\/input|data:\/\/text\/plain|expect:\/\/)/i',
            '/<\?(php|=)/i',
            '/\b(base64_decode|gzinflate|str_rot13)\s*\(/i',
            '/;\s*(cat\s+\/etc|whoami|id|uname|powershell|cmd\.exe|bash|sh|curl\s+http|wget\s+http)\b/i',
            '/\|\|\s*(whoami|id|powershell|cmd\.exe|bash|sh)\b/i',
            '/O:\d+:"[^"]+":\d+:/i', // serialized PHP object
        ];
        foreach ($patterns as $pattern) if (preg_match($pattern, $input)) return true;
        return false;
    }

    public static function detect_path_traversal($input) {
        if (is_array($input)) {
            foreach ($input as $v) if (self::detect_path_traversal($v)) return true;
            return false;
        }
        if (!is_string($input)) return false;
        $decoded = rawurldecode(rawurldecode($input));
        foreach ([$input, $decoded] as $value) {
            if ($value === '..'
                || preg_match('/(\.\.[\/\\\\]|%2e%2e[\/\\\\]|\.\.%2f|\.\.%5c|%2e%2e)/i', $value)
                || preg_match('/(^|[\/\\\\])\.\.($|[\/\\\\])/i', $value)) {
                return true;
            }
        }
        return false;
    }

    public static function detect_xss($input) {
        if (is_array($input)) {
            foreach ($input as $v) if (self::detect_xss($v)) return true;
            return false;
        }
        if (!is_string($input) || strlen($input) < 5) return false;
        $patterns = ['/<script\b[^>]*>/i', '/<\/script>/i', '/javascript\s*:/i', '/vbscript\s*:/i', '/<iframe\b[^>]*>/i',
                     '/<object\b[^>]*>/i', '/<embed\b[^>]*>/i', '/onload\s*=/i', '/onerror\s*=/i', '/onclick\s*=/i',
                     '/onmouseover\s*=/i', '/data\s*:\s*text\/html/i'];
        foreach ($patterns as $p) if (preg_match($p, $input)) return true;
        return false;
    }

    public static function sanitize_string($input) {
        if (!is_string($input)) return $input;
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        if (function_exists('mb_convert_encoding')) $input = mb_convert_encoding($input, 'UTF-8', 'UTF-8');
        return trim($input);
    }

    public static function hash_password($plainPassword) {
        return password_hash($plainPassword, PASSWORD_DEFAULT);
    }

    public static function verify_password($plainPassword, $hash) {
        return is_string($hash) && password_verify((string)$plainPassword, $hash);
    }

    /** Blocked requests go to the PHP error log (no database table needed). */
    public static function log_incident($type, $payloadSnippet, $actionTaken = 'Blocked') {
        error_log(sprintf('[security] %s %s %s %s: %s', $actionTaken, $type, $_SERVER['REMOTE_ADDR'] ?? '-',
            $_SERVER['REQUEST_URI'] ?? '-', substr((string)$payloadSnippet, 0, 300)));
    }
}
