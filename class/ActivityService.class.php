<?php
/* Copyright (C) 2024 SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

require_once __DIR__.'/SfActivity.class.php';

/**
 * Helper service that centralizes the workflow transitions for Safra activities.
 */
class ActivityService
{
    /**
     * @var DoliDB
     */
    private $db;

    /**
     * @param DoliDB $db
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Validate an activity (schedule the execution).
     *
     * @param SfActivity $activity
     * @param User       $user
     * @param int        $notrigger
     *
     * @return int
     */
    public function validate(SfActivity $activity, User $user, $notrigger = 0)
    {
        $langs = $this->loadLangs();

        if (!$this->canTransition($user)) {
            $activity->error = $langs->trans('ErrorSafraActivityNoRights');
            return -1;
        }

        if ($activity->status !== SfActivity::STATUS_DRAFT) {
            $activity->error = $langs->trans('ErrorSafraActivityInvalidTransitionState', $activity->getLibStatut(0));
            return -1;
        }

        if (empty($activity->lines)) {
            $activity->fetchLines();
        }
        if (empty($activity->lines)) {
            $activity->error = $langs->trans('SafraActivityNoLines');
            return -1;
        }

        $missingWarehouse = false;
        $summary = $activity->buildStockSummary($user, $missingWarehouse);
        if ($missingWarehouse) {
            foreach ($summary as $item) {
                if (empty($item['fk_entrepot'])) {
                    $activity->error = $langs->trans('SafraAplicacaoMissingWarehouse');
                    return -1;
                }
            }
        }

        $result = $activity->validate($user, $notrigger);
        if ($result <= 0) {
            return $result;
        }

        $activity->syncTask($user);

        return $result;
    }

    /**
     * Transition a validated activity to in-progress.
     *
     * @param SfActivity $activity
     * @param User       $user
     * @param int        $notrigger
     *
     * @return int
     */
    public function start(SfActivity $activity, User $user, $notrigger = 0)
    {
        $langs = $this->loadLangs();

        if (!$this->canTransition($user)) {
            $activity->error = $langs->trans('ErrorSafraActivityNoRights');
            return -1;
        }

        if ($activity->status !== SfActivity::STATUS_VALIDATED) {
            $activity->error = $langs->trans('ErrorSafraActivityInvalidTransitionState', $activity->getLibStatut(0));
            return -1;
        }

        if (empty($activity->lines)) {
            $activity->fetchLines();
        }
        if (empty($activity->lines)) {
            $activity->error = $langs->trans('SafraActivityNoLines');
            return -1;
        }

        $missingWarehouse = false;
        $summary = $activity->buildStockSummary($user, $missingWarehouse);
        if ($missingWarehouse) {
            foreach ($summary as $item) {
                if (empty($item['fk_entrepot'])) {
                    $activity->error = $langs->trans('SafraAplicacaoMissingWarehouse');
                    return -1;
                }
            }
        }

        return $activity->markAsInProgress($user, $notrigger);
    }

    /**
     * Complete an activity consuming and producing stock movements.
     *
     * @param SfActivity $activity
     * @param User       $user
     *
     * @return int
     */
    public function complete(SfActivity $activity, User $user)
    {
        $langs = $this->loadLangs();

        if (!$this->canTransition($user)) {
            $activity->error = $langs->trans('ErrorSafraActivityNoRights');
            return -1;
        }

        if (!in_array($activity->status, array(SfActivity::STATUS_VALIDATED, SfActivity::STATUS_IN_PROGRESS), true)) {
            $activity->error = $langs->trans('ErrorSafraActivityInvalidTransitionState', $activity->getLibStatut(0));
            return -1;
        }

        if (empty($activity->lines)) {
            $activity->fetchLines();
        }
        if (empty($activity->lines)) {
            $activity->error = $langs->trans('SafraActivityNoLines');
            return -1;
        }

        $missingWarehouse = false;
        $summary = $activity->buildStockSummary($user, $missingWarehouse);
        if ($missingWarehouse) {
            foreach ($summary as $item) {
                if (empty($item['fk_entrepot'])) {
                    $activity->error = $langs->trans('SafraAplicacaoMissingWarehouse');
                    return -1;
                }
            }
        }

        return $activity->markAsCompleted($user);
    }

    /**
     * Cancel a scheduled or in-progress activity.
     *
     * @param SfActivity $activity
     * @param User       $user
     * @param int        $notrigger
     *
     * @return int
     */
    public function cancel(SfActivity $activity, User $user, $notrigger = 0)
    {
        $langs = $this->loadLangs();

        if (!$this->canTransition($user)) {
            $activity->error = $langs->trans('ErrorSafraActivityNoRights');
            return -1;
        }

        if (!in_array($activity->status, array(SfActivity::STATUS_VALIDATED, SfActivity::STATUS_IN_PROGRESS), true)) {
            $activity->error = $langs->trans('ErrorSafraActivityInvalidTransitionState', $activity->getLibStatut(0));
            return -1;
        }

        return $activity->cancel($user, $notrigger);
    }

    /**
     * Ensure module translations are loaded.
     *
     * @return Translate|null
     */
    protected function loadLangs()
    {
        global $langs;
        if ($langs instanceof Translate) {
            $langs->loadLangs(array('safra@safra'));
        }

        return $langs;
    }

    /**
     * Check whether user can perform workflow transitions.
     *
     * @param User $user
     *
     * @return bool
     */
    protected function canTransition(User $user)
    {
        if (!($user instanceof User)) {
            return false;
        }

        if (getDolGlobalInt('MAIN_USE_ADVANCED_PERMS')) {
            if (!empty($user->rights->safra->aplicacao_advance) && !empty($user->rights->safra->aplicacao_advance->validate)) {
                return true;
            }

            return false;
        }

        return !empty($user->rights->safra->aplicacao->write);
    }
}
