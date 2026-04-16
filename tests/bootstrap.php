<?php
declare(strict_types=1);

if (!defined('DOL_DOCUMENT_ROOT')) {
    define('DOL_DOCUMENT_ROOT', __DIR__.'/stubs');
}
if (!defined('MAIN_DB_PREFIX')) {
    define('MAIN_DB_PREFIX', 'llx_');
}
if (!defined('LOG_WARNING')) {
    define('LOG_WARNING', 'LOG_WARNING');
}
if (!defined('LOG_DEBUG')) {
    define('LOG_DEBUG', 'LOG_DEBUG');
}
if (!defined('LOG_ERR')) {
    define('LOG_ERR', 'LOG_ERR');
}
if (!defined('LOG_INFO')) {
    define('LOG_INFO', 'LOG_INFO');
}

if (!function_exists('dol_now')) {
    function dol_now(): int
    {
        return time();
    }
}

if (!function_exists('dol_syslog')) {
    function dol_syslog(string $message, $level = null): void
    {
        // Intentionally left blank for tests.
    }
}

if (!function_exists('dol_include_once')) {
    function dol_include_once(string $relativePath): bool
    {
        $relativePath = ltrim($relativePath, '/');

        $candidates = array(
            DOL_DOCUMENT_ROOT.'/'.$relativePath,
            dirname(__DIR__).'/'.$relativePath,
        );

        if (strpos($relativePath, 'safra/') === 0) {
            $candidates[] = dirname(__DIR__).'/'.substr($relativePath, 6);
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                require_once $candidate;
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('dol_print_error')) {
    function dol_print_error($db = null, string $message = ''): void
    {
        // Intentionally left blank for tests.
    }
}

if (!function_exists('dol_escape_htmltag')) {
    function dol_escape_htmltag(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('dol_string_nohtmltag')) {
    function dol_string_nohtmltag(string $value): string
    {
        return strip_tags($value);
    }
}

if (!function_exists('dol_print_date')) {
    function dol_print_date($timestamp = null, string $format = '%Y-%m-%d %H:%M:%S'): string
    {
        $timestamp = (int) ($timestamp ?? time());
        if ($format === 'dayhourlog') {
            return date('Y-m-d H:i:s', $timestamp);
        }
        return date('Y-m-d H:i:s', $timestamp);
    }
}

if (!function_exists('newToken')) {
    function newToken(): string
    {
        return 'test-token';
    }
}

if (!function_exists('checkToken')) {
    function checkToken(string $token): bool
    {
        return $token === 'test-token';
    }
}

if (!function_exists('dol_sanitizeFileName')) {
    function dol_sanitizeFileName(string $filename): string
    {
        return preg_replace('/[^A-Za-z0-9_.-]/', '', $filename);
    }
}

if (!function_exists('dol_dir_list')) {
    function dol_dir_list($path, $type = 'files', $recursive = 0, $filter = ''): array
    {
        return array();
    }
}

if (!function_exists('dol_buildpath')) {
    function dol_buildpath(string $relativePath, int $type = 0): string
    {
        $relativePath = ltrim($relativePath, '/');
        return DOL_DOCUMENT_ROOT.'/'.$relativePath;
    }
}

if (!function_exists('getDolGlobalInt')) {
    function getDolGlobalInt(string $key, int $default = 0): int
    {
        global $conf;
        if (isset($conf->global->{$key})) {
            return (int) $conf->global->{$key};
        }
        return $default;
    }
}

if (!function_exists('getDolGlobalString')) {
    function getDolGlobalString(string $key, string $default = ''): string
    {
        global $conf;
        if (isset($conf->global->{$key})) {
            return (string) $conf->global->{$key};
        }
        return $default;
    }
}

if (!function_exists('isModEnabled')) {
    function isModEnabled(string $module): bool
    {
        return false;
    }
}

if (!function_exists('getEntity')) {
    function getEntity($element): string
    {
        return '1';
    }
}

if (!function_exists('dolGetStatus')) {
    function dolGetStatus($label, $shortLabel, $alt, $statusType, $mode)
    {
        return array(
            'label' => $label,
            'short' => $shortLabel,
            'status' => $statusType,
            'mode' => $mode,
        );
    }
}

if (!class_exists('DoliDB')) {
    class DoliDB
    {
        public $transaction_opened = 0;
        public $lasterror = '';
        public $mockQueryMap = array();
        public $stockMovements = array();

        public function addMockQuery(string $needle, array $rows): void
        {
            $this->mockQueryMap[] = array(
                'needle' => strtolower($needle),
                'rows' => $rows,
            );
        }

        public function clearMockQueries(): void
        {
            $this->mockQueryMap = array();
        }

        public function begin()
        {
            $this->transaction_opened = 1;
            return true;
        }

        public function commit()
        {
            $this->transaction_opened = 0;
            return true;
        }

        public function rollback()
        {
            $this->transaction_opened = 0;
            return true;
        }

        public function escape(string $value): string
        {
            return addslashes($value);
        }

        public function idate($timestamp): string
        {
            return date('Y-m-d H:i:s', (int) $timestamp);
        }

        public function order(string $field, string $order = 'ASC'): string
        {
            return ' ORDER BY '.$field.' '.$order;
        }

        public function plimit(int $limit, int $offset = 0): string
        {
            return ' LIMIT '.$offset.', '.$limit;
        }

        public function query(string $sql)
        {
            $lowerSql = strtolower($sql);
            foreach ($this->mockQueryMap as $mock) {
                if (strpos($lowerSql, $mock['needle']) !== false) {
                    return (object) array(
                        'rows' => $mock['rows'],
                        'index' => 0,
                    );
                }
            }

            return (object) array(
                'rows' => array(),
                'index' => 0,
            );
        }

        public function fetch_object($result)
        {
            if (!is_object($result) || !isset($result->rows, $result->index)) {
                return false;
            }

            if ($result->index >= count($result->rows)) {
                return false;
            }

            $row = $result->rows[$result->index];
            $result->index++;

            if (is_object($row)) {
                return $row;
            }

            return (object) $row;
        }

        public function num_rows($result): int
        {
            if (!is_object($result) || !isset($result->rows) || !is_array($result->rows)) {
                return 0;
            }

            return count($result->rows);
        }

        public function lasterror(): string
        {
            return (string) $this->lasterror;
        }
    }
}

class Translate
{
    public $loaded = array();

    public function load(string $key): void
    {
        $this->loaded[$key] = true;
    }

    public function loadLangs(array $keys): void
    {
        foreach ($keys as $key) {
            $this->load($key);
        }
    }

    public function trans(string $key): string
    {
        $args = func_get_args();
        array_shift($args);
        if (!empty($args)) {
            $normalized = array_map(function ($value) {
                return is_scalar($value) ? (string) $value : json_encode($value);
            }, $args);
            return $key.'('.implode('|', $normalized).')';
        }
        return $key;
    }

    public function transnoentitiesnoconv(string $key): string
    {
        $args = func_get_args();
        array_shift($args);
        if (!empty($args)) {
            $normalized = array_map(function ($value) {
                return is_scalar($value) ? (string) $value : json_encode($value);
            }, $args);
            return $key.'('.implode('|', $normalized).')';
        }
        return $key;
    }
}

class User
{
    public $id;
    public $fk_warehouse = 0;

    public function __construct(int $id = 0)
    {
        $this->id = $id;
    }

    public function hasRight(...$args): bool
    {
        return true;
    }
}

class Conf
{
    public $entity = 1;
    public $global;
    public $safra;

    public function __construct()
    {
        $this->global = new stdClass();
        $this->safra = new stdClass();
    }
}

if (!isset($conf) || !($conf instanceof Conf)) {
    $conf = new Conf();
}
if (empty($conf->safra->dir_output)) {
    $conf->safra->dir_output = sys_get_temp_dir().'/safra';
}
if (!is_dir($conf->safra->dir_output)) {
    @mkdir($conf->safra->dir_output, 0777, true);
}

if (!isset($langs) || !($langs instanceof Translate)) {
    $langs = new Translate();
}

if (!isset($db) || !($db instanceof DoliDB)) {
    $db = new DoliDB();
}

require_once __DIR__.'/stubs/core/lib/price.lib.php';
require_once __DIR__.'/stubs/api/class/api.class.php';
