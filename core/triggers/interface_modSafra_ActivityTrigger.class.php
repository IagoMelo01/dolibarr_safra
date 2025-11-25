<?php
/*
 * Trigger to keep Safra activity and project tasks in sync.
 */

require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';
dol_include_once('/safra/class/FvActivity.class.php');
dol_include_once('/projet/class/task.class.php');

class InterfaceModSafraActivityTrigger extends DolibarrTriggers
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = 'safra';
        $this->description = 'Sync activity completion and stock movements with project tasks.';
        $this->version = 'dolibarr';
        $this->picto = 'safra@safra';
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDesc()
    {
        return $this->description;
    }

    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if (!isModEnabled('safra')) {
            return 0;
        }

        switch ($action) {
            case 'SAFRA_ACTIVITY_CLOSE':
                if ($object instanceof FvActivity) {
                    return $this->handleActivityClose($object, $user);
                }
                break;
            case 'TASK_MODIFY':
            case 'TASK_CLOSE':
                if ($object instanceof Task) {
                    return $this->handleTaskCompletion($object, $user);
                }
                break;
        }

        return 0;
    }

    private function handleActivityClose(FvActivity $activity, User $user)
    {
        if ($activity->hasStockMovements()) {
            return 0;
        }

        return $activity->createStockMovements($user);
    }

    private function handleTaskCompletion(Task $task, User $user)
    {
        if ((int) $task->progress !== 100 && (int) $task->fk_statut !== Task::STATUS_CLOSED) {
            return 0;
        }

        $activity = FvActivity::fetchByTaskId($this->db, $task->id);
        if (!$activity) {
            return 0;
        }

        $this->db->begin();

        if (!$activity->isCompleted()) {
            $activity->status = FvActivity::STATUS_COMPLETED;
            $activity->fk_user_modif = $user->id;

            $updateResult = $activity->update($user);
            if ($updateResult < 0) {
                $this->db->rollback();

                return -1;
            }
        }

        $stockResult = $activity->createStockMovements($user);
        if ($stockResult < 0) {
            $this->db->rollback();

            return -1;
        }

        $this->db->commit();

        return 1;
    }
}
