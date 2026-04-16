<?php
/**
 * Safra custom helpers.
 *
 * @package safra
 */

/**
 * Normalize rights array/object and fetch specific Safra Activity privilege.
 *
 * @param object $user    Dolibarr user object
 * @param string $type    Right key to read (e.g. 'read', 'write', 'delete')
 * @param int    $default Fallback when key missing
 *
 * @return int
 */
function getSafraRightValue($user, $type, $default = 1)
{
    $rights = $user->rights ?? null;
    if (is_object($rights)) {
        $rights = (array) $rights;
    }
    if (!is_array($rights)) {
        return $default;
    }
    $module = $rights['safra'] ?? null;
    if (is_object($module)) {
        $module = (array) $module;
    }
    if (!is_array($module)) {
        return $default;
    }
    $activity = $module['SafraActivity'] ?? null;
    if (is_object($activity)) {
        $activity = (array) $activity;
    }
    if (!is_array($activity)) {
        return $default;
    }
    return $activity[$type] ?? $default;
}
