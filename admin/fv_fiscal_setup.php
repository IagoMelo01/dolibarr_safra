<?php
/*
 * Copyright (C) 2024
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

global $conf, $db, $langs, $user;

if (!$user->admin) {
    accessforbidden();
}

$langs->load('admin');
$langs->load('errors');
$langs->load('fv_fiscal@fv_fiscal');

$action = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

$token = GETPOST('FV_FISCAL_FOCUS_TOKEN', 'alphanohtml');
$env = GETPOST('FV_FISCAL_ENV', 'alpha');
$baseUrl = GETPOST('FV_FISCAL_BASE_URL', 'alphanohtml');
$includeCnpjRoot = GETPOST('FV_FISCAL_IMPORT_INCLUDE_CNPJ_ROOT', 'int');
$scienceAuto = GETPOST('FV_FISCAL_IMPORT_SCIENCE_AUTO', 'int');
$cronMin = GETPOST('FV_FISCAL_IMPORT_CRON_MIN', 'int');
$webhookSecret = GETPOST('FV_FISCAL_WEBHOOK_SECRET', 'alphanohtml');

$message = '';
$messageType = 'mesgs';

if ($action === 'save' && $user->admin && !GETPOST('cancel', 'alpha')) {
    if (empty($token)) {
        setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('FV_FISCAL_FOCUS_TOKEN')), null, 'errors');
    } else {
        dolibarr_set_const($db, 'FV_FISCAL_FOCUS_TOKEN', $token, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'FV_FISCAL_ENV', $env ?: 'homolog', 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'FV_FISCAL_BASE_URL', $baseUrl, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'FV_FISCAL_IMPORT_INCLUDE_CNPJ_ROOT', empty($includeCnpjRoot) ? 0 : 1, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'FV_FISCAL_IMPORT_SCIENCE_AUTO', empty($scienceAuto) ? 0 : 1, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'FV_FISCAL_IMPORT_CRON_MIN', max((int) $cronMin, 5), 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'FV_FISCAL_WEBHOOK_SECRET', $webhookSecret, 'chaine', 0, '', $conf->entity);

        setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
    }
}

if ($action === 'test' && $user->admin) {
    require_once __DIR__ . '/../class/FocusClient.class.php';

    $tokenToUse = $token ?: $conf->global->FV_FISCAL_FOCUS_TOKEN;
    $envToUse = $env ?: $conf->global->FV_FISCAL_ENV;
    $baseUrlToUse = $baseUrl ?: $conf->global->FV_FISCAL_BASE_URL;

    try {
        $client = new FocusClient($db, $tokenToUse, $envToUse, $baseUrlToUse);
        $response = $client->get('/v2/nfe/status');
        setEventMessages($langs->trans('TestConnectionSuccess', dol_escape_htmltag(json_encode($response))), null, 'mesgs');
    } catch (Exception $e) {
        setEventMessages($langs->trans('Error') . ': ' . $e->getMessage(), null, 'errors');
    }
}

llxHeader('', $langs->trans('FvFiscalSetup'));

$form = new Form($db);

print load_fiche_titre($langs->trans('FvFiscalSetup'));

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>' . $langs->trans('Parameter') . '</th>';
print '<th>' . $langs->trans('Value') . '</th>';
print '</tr>';

print '<tr class="oddeven">';
print '<td><span class="fieldrequired">' . $langs->trans('FocusToken') . '</span></td>';
print '<td><input type="text" name="FV_FISCAL_FOCUS_TOKEN" class="flat" value="' . dol_escape_htmltag(GETPOSTISSET('FV_FISCAL_FOCUS_TOKEN') ? $token : $conf->global->FV_FISCAL_FOCUS_TOKEN) . '" size="80"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>' . $langs->trans('Environment') . '</td>';
print '<td><select name="FV_FISCAL_ENV" class="flat">';
$selectedEnv = GETPOSTISSET('FV_FISCAL_ENV') ? $env : ($conf->global->FV_FISCAL_ENV ?: 'homolog');
foreach (array('homolog', 'prod') as $option) {
    print '<option value="' . $option . '"' . ($selectedEnv == $option ? ' selected' : '') . '>' . ucfirst($option) . '</option>';
}
print '</select></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>' . $langs->trans('BaseUrl') . '</td>';
print '<td><input type="text" name="FV_FISCAL_BASE_URL" class="flat" value="' . dol_escape_htmltag(GETPOSTISSET('FV_FISCAL_BASE_URL') ? $baseUrl : $conf->global->FV_FISCAL_BASE_URL) . '" size="80"> ';
print $langs->trans('LeaveEmptyForDefault');
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>' . $langs->trans('ImportIncludeCnpjRoot') . '</td>';
$checked = GETPOSTISSET('FV_FISCAL_IMPORT_INCLUDE_CNPJ_ROOT') ? !empty($includeCnpjRoot) : !empty($conf->global->FV_FISCAL_IMPORT_INCLUDE_CNPJ_ROOT);
print '<td><input type="checkbox" name="FV_FISCAL_IMPORT_INCLUDE_CNPJ_ROOT" value="1"' . ($checked ? ' checked' : '') . '></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>' . $langs->trans('ImportScienceAuto') . '</td>';
$checked = GETPOSTISSET('FV_FISCAL_IMPORT_SCIENCE_AUTO') ? !empty($scienceAuto) : !empty($conf->global->FV_FISCAL_IMPORT_SCIENCE_AUTO);
print '<td><input type="checkbox" name="FV_FISCAL_IMPORT_SCIENCE_AUTO" value="1"' . ($checked ? ' checked' : '') . '></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>' . $langs->trans('ImportCronMinutes') . '</td>';
print '<td><input type="number" name="FV_FISCAL_IMPORT_CRON_MIN" class="flat" min="1" value="' . dol_escape_htmltag(GETPOSTISSET('FV_FISCAL_IMPORT_CRON_MIN') ? $cronMin : ($conf->global->FV_FISCAL_IMPORT_CRON_MIN ?: 30)) . '"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>' . $langs->trans('WebhookSecret') . '</td>';
print '<td><input type="text" name="FV_FISCAL_WEBHOOK_SECRET" class="flat" value="' . dol_escape_htmltag(GETPOSTISSET('FV_FISCAL_WEBHOOK_SECRET') ? $webhookSecret : $conf->global->FV_FISCAL_WEBHOOK_SECRET) . '" size="80"></td>';
print '</tr>';

print '</table>';

print '<div class="center">';
print '<input type="hidden" name="action" value="save">';
print '<input type="submit" class="button" value="' . $langs->trans('Save') . '">';
print ' &nbsp; <button type="submit" name="action" value="test" class="button">' . $langs->trans('TestConnection') . '</button>';
print '</div>';
print '</form>';

llxFooter();

/*
 * Translations fallback
 */
if (!function_exists('dol_escape_htmltag')) {
    function dol_escape_htmltag($string)
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

