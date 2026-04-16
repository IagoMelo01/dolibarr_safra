<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/class/FvActivity.class.php';
require_once dirname(__DIR__) . '/class/FvActivityLine.class.php';
require_once dirname(__DIR__) . '/class/api_sfactivities.class.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$requiredMethods = array(
    'index',
    'get',
    'post',
    'put',
    'delete',
    'postStart',
    'postComplete',
    'postCancel',
);

foreach ($requiredMethods as $method) {
    $assert(method_exists('Sfactivities', $method), 'API method missing: ' . $method);
}

$db = new DoliDB();
DolibarrApiAccess::$user = new User(1);

$api = new Sfactivities();
$result = $api->index('t.rowid', 'ASC', 10, 0, '', 0);
$assert(is_array($result), 'API index should return array');
$assert(count($result) === 0, 'API index should be empty with mocked DB');

class ApiDenyUser extends User
{
    public function hasRight(...$args): bool
    {
        return false;
    }
}

DolibarrApiAccess::$user = new ApiDenyUser(2);

$accessDenied = false;
try {
    $api->index('t.rowid', 'ASC', 10, 0, '', 0);
} catch (\Luracast\Restler\RestException $e) {
    $accessDenied = ((int) $e->getCode()) === 403;
}

$assert($accessDenied, 'API must deny access without SafraActivity read permission');

return true;
