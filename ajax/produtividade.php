<?php
/* Copyright (C) 2024 SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *      \file       htdocs/safra/ajax/produtividade.php
 *      \ingroup    safra
 *      \brief      AJAX helpers for the productivity estimation page.
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', 1);
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', '1');
}

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists('../../main.inc.php')) {
    $res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
    $res = @include '../../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

dol_include_once('/safra/class/cultivar.class.php');
dol_include_once('/safra/class/municipio.class.php');

/** @var DoliDB $db */
/** @var User $user */
/** @var Translate $langs */

if (!$user->hasRight('safra', 'produtividade', 'read')) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$term = trim(GETPOST('term', 'alphanohtml'));
$limit = GETPOST('limit', 'int');
$offset = GETPOST('offset', 'int');
if (!GETPOSTISSET('limit')) {
    $limit = 25;
} elseif ($limit < 0) {
    $limit = 25;
} elseif ($limit > 0 && $limit > 500) {
    $limit = 500;
}

if (!GETPOSTISSET('offset') || $offset < 0) {
    $offset = 0;
}

$items = array();
$hasMore = false;
$nextOffset = $offset;

switch ($action) {
    case 'cultivares':
        $culturaId = GETPOST('idCultura', 'int');
        if ($culturaId <= 0) {
            break;
        }

        if ($term === '' || (dol_strlen($term) < 2 && !ctype_digit($term))) {
            break;
        }

        $cultivar = new Cultivar($db);

        $filters = array('t.cultura' => (int) $culturaId);
        $escapedTerm = $db->escape($db->escapeforlike($term));
        $filters['customsql'] = "(t.label LIKE '%" . $escapedTerm . "%' OR t.ref LIKE '%" . $escapedTerm . "%')";

        $pageSize = $limit > 0 ? $limit : 30;
        $records = $cultivar->fetchAll('ASC', 'label', $pageSize + 1, $offset, $filters);
        if (is_array($records)) {
            if (count($records) > $pageSize) {
                $hasMore = true;
                $records = array_slice($records, 0, $pageSize, true);
            }
            foreach ($records as $record) {
                if (empty($record->id)) {
                    continue;
                }
                $items[] = array(
                    'id' => (int) $record->id,
                    'label' => $record->label ?: $record->ref,
                    'ref' => $record->ref,
                    'cultura' => (int) $record->cultura,
                    'embrapa' => $record->embrapa_id,
                );
            }
            $nextOffset = $offset + count($records);
        }
        break;

    case 'municipios':
        if ($term === '') {
            break;
        }

        $municipio = new Municipio($db);
        $escapedTerm = $db->escape($db->escapeforlike($term));
        $filters = array(
            'customsql' => "(t.label LIKE '%" . $escapedTerm . "%' OR t.cod_ibge LIKE '%" . $escapedTerm . "%' OR t.ref LIKE '%" . $escapedTerm . "%')",
        );

        $records = $municipio->fetchAll('ASC', 'label', $limit, 0, $filters, 'OR');
        if (is_array($records)) {
            foreach ($records as $record) {
                if (empty($record->cod_ibge)) {
                    continue;
                }
                $items[] = array(
                    'code' => (int) $record->cod_ibge,
                    'label' => $record->label ?: $record->ref,
                    'uf' => $record->uf,
                );
            }
        }
        break;
}

top_httphead('application/json');

print json_encode(array(
    'items' => $items,
    'hasMore' => $hasMore,
    'nextOffset' => $nextOffset,
));

$db->close();
