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
        $this->description = 'Sync Safra activity status and lifecycle with project tasks.';
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
            case 'TASK_CREATE':
            case 'TASK_MODIFY':
            case 'TASK_CLOSE':
                if ($object instanceof Task) {
                    return $this->handleTaskUpdate($object, $user);
                }
                break;

            case 'TASK_DELETE':
                if ($object instanceof Task) {
                    return $this->handleTaskDelete($object, $user);
                }
                break;
        }

        return 0;
    }

    /**
     * Mirror task status and relation data on linked Safra activity.
     *
     * @param Task $task
     * @param User $user
     * @return int
     */
    private function handleTaskUpdate(Task $task, User $user)
    {
        if (empty($task->id)) {
            return 0;
        }

        $activity = FvActivity::fetchByTaskId($this->db, (int) $task->id);
        if (!$activity) {
            return 0;
        }

        $mustUpdate = false;

        if ((int) $activity->fk_task !== (int) $task->id) {
            $activity->fk_task = (int) $task->id;
            $mustUpdate = true;
        }

        if (!empty($task->fk_project) && (int) $activity->fk_project !== (int) $task->fk_project) {
            $activity->fk_project = (int) $task->fk_project;
            $mustUpdate = true;
        }

        $mappedStatus = FvActivity::mapTaskToActivityStatus((int) $task->status, (int) $task->progress);
        if ((int) $activity->status !== (int) $mappedStatus) {
            $activity->status = (int) $mappedStatus;
            $mustUpdate = true;
        }

        if (!$mustUpdate) {
            return 0;
        }

        $activity->context['skip_task_sync'] = 1;
        $updateResult = $activity->update($user);
        unset($activity->context['skip_task_sync']);

        if ($updateResult < 0) {
            $this->error = $activity->error ?: $activity->errorsToString();

            return -1;
        }

        return 1;
    }

    /**
     * Ensure deletion is mirrored from task to activity.
     *
     * @param Task $task
     * @param User $user
     * @return int
     */
    private function handleTaskDelete(Task $task, User $user)
    {
        if (empty($task->id)) {
            return 0;
        }
        if (!empty($task->context['skip_activity_delete'])) {
            return 0;
        }

        $activity = FvActivity::fetchByTaskId($this->db, (int) $task->id);
        if (!$activity) {
            return 0;
        }

        $activity->context['skip_task_delete'] = 1;
        $deleteResult = $activity->delete($user, false);
        unset($activity->context['skip_task_delete']);

        if ($deleteResult < 0) {
            $this->error = $activity->error ?: $activity->errorsToString();

            return -1;
        }

        return 1;
    }
}
