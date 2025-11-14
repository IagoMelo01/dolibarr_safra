<?php
/* Copyright (C) 2024 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lib/safra_activity.lib.php
 * \ingroup safra
 * \brief   Helper functions for Safra activities.
 */

/**
 * Prepare array of tabs for Safra activities.
 *
 * @param SafraActivity $object Activity instance
 * @return array
 */
function safraactivityPrepareHead($object)
{
        global $db, $conf, $langs;

        $langs->load('safra@safra');

        $head = array();
        $h = 0;

        $head[$h][0] = dol_buildpath('/safra/safraactivity_card.php', 1) . '?id=' . $object->id;
        $head[$h][1] = $langs->trans('SafraActivityGeneralTab');
        $head[$h][2] = 'card';
        $h++;

        if (isset($object->fields['note_public']) || isset($object->fields['note_private'])) {
                $nbNote = 0;
                if (!empty($object->note_private)) {
                        $nbNote++;
                }
                if (!empty($object->note_public)) {
                        $nbNote++;
                }

                $head[$h][0] = dol_buildpath('/safra/safraactivity_note.php', 1) . '?id=' . $object->id;
                $head[$h][1] = $langs->trans('Notes');
                if ($nbNote > 0) {
                        $head[$h][1] .= (!getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER') ? '<span class="badge marginleftonlyshort">' . $nbNote . '</span>' : '');
                }
                $head[$h][2] = 'note';
                $h++;
        }

        $upload_dir = $conf->safra->multidir_output[isset($object->entity) ? $object->entity : $conf->entity] . '/safraactivity/' . dol_sanitizeFileName($object->ref);
        require_once DOL_DOCUMENT_ROOT . '/core/class/link.class.php';
        require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
        $nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
        $nbLinks = Link::count($db, $object->element, $object->id);

        $head[$h][0] = dol_buildpath('/safra/safraactivity_document.php', 1) . '?id=' . $object->id;
        $head[$h][1] = $langs->trans('Documents');
        if (($nbFiles + $nbLinks) > 0) {
                $head[$h][1] .= '<span class="badge marginleftonlyshort">' . ($nbFiles + $nbLinks) . '</span>';
        }
        $head[$h][2] = 'document';
        $h++;

        $head[$h][0] = dol_buildpath('/safra/safraactivity_agenda.php', 1) . '?id=' . $object->id;
        $head[$h][1] = $langs->trans('Events');
        $head[$h][2] = 'agenda';
        $h++;

        complete_head_from_modules($conf, $langs, $object, $head, $h, 'safraactivity@safra');
        complete_head_from_modules($conf, $langs, $object, $head, $h, 'safraactivity@safra', 'remove');

        return $head;
}
