<?php
/*
 * Shared UI and document helpers for Safra activities.
 */

/**
 * Build the URL for an activity tab.
 *
 * @param int    $activityId Activity identifier.
 * @param string $tab        Tab code.
 *
 * @return string
 */
function safra_activity_tab_url($activityId, $tab)
{
    if ($tab === 'documents') {
        return dol_buildpath('/safra/activity/activity_documents.php', 1) . '?id=' . ((int) $activityId);
    }

    return dol_buildpath('/safra/activity/activity_card.php', 1) . '?id=' . ((int) $activityId) . '&tab=' . urlencode($tab);
}

/**
 * Prepare the tabs displayed on an activity.
 *
 * @param FvActivity $activity Activity object.
 * @param Translate  $langs    Translation service.
 *
 * @return array
 */
function safra_activity_prepare_head(FvActivity $activity, $langs)
{
    if (empty($activity->id)) {
        return array();
    }

    return array(
        array(safra_activity_tab_url($activity->id, 'card'), $langs->trans('SafraActivityGeneralTab'), 'card'),
        array(safra_activity_tab_url($activity->id, 'inputs'), $langs->trans('SafraActivityInputs'), 'inputs'),
        array(safra_activity_tab_url($activity->id, 'mixture'), $langs->trans('SafraAplicacaoCaldaCalculation'), 'mixture'),
        array(safra_activity_tab_url($activity->id, 'team'), $langs->trans('SafraActivityTeam'), 'team'),
        array(safra_activity_tab_url($activity->id, 'vehicles'), $langs->trans('SafraVehicleLabel'), 'vehicles'),
        array(safra_activity_tab_url($activity->id, 'implements'), $langs->trans('SafraImplementsLabel'), 'implements'),
        array(safra_activity_tab_url($activity->id, 'documents'), $langs->trans('SafraActivityDocuments'), 'documents'),
    );
}

/**
 * Return the relative document path for an activity.
 *
 * @param FvActivity $activity Activity object.
 *
 * @return string
 */
function safra_activity_documents_relative_path(FvActivity $activity)
{
    $reference = trim((string) $activity->ref);
    if ($reference === '') {
        $reference = (string) ((int) $activity->id);
    }

    return 'safra_activity/' . dol_sanitizeFileName($reference);
}

/**
 * Return the absolute document directory for an activity.
 *
 * @param FvActivity $activity Activity object.
 *
 * @return string
 */
function safra_activity_documents_upload_dir(FvActivity $activity)
{
    global $conf;

    $entity = !empty($activity->entity) ? (int) $activity->entity : (int) $conf->entity;
    $root = '';
    if (!empty($conf->safra->multidir_output[$entity])) {
        $root = $conf->safra->multidir_output[$entity];
    } elseif (!empty($conf->safra->dir_output)) {
        $root = $conf->safra->dir_output;
    } elseif (defined('DOL_DATA_ROOT')) {
        $root = rtrim(DOL_DATA_ROOT, '/\\') . '/safra';
    } else {
        $root = rtrim(sys_get_temp_dir(), '/\\') . '/safra';
    }

    return rtrim((string) $root, '/\\') . '/' . safra_activity_documents_relative_path($activity);
}
