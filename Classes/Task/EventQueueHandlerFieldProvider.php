<?php
namespace Crossmedia\Fourallportal\Task;

use TYPO3\CMS\Scheduler\AdditionalFieldProviderInterface;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

class EventQueueHandlerFieldProvider implements AdditionalFieldProviderInterface
{

    /**
     * creates the markup of the additional field to show in the scheduler configuration
     *
     * @param  array &$taskInfo
     * @param  object $task
     * @param  SchedulerModuleController $parentObject
     * @return array
     */
    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $parentObject)
    {
        if (empty($taskInfo['items'])) {
            if ($parentObject->CMD == 'edit') {
                $taskInfo['items'] = $task->getItems();
            } else {
                $taskInfo['items'] = '';
            }
        }

        // Write the code for the field
        $fieldID = 'items';
        $fieldCode = '<input type="text" name="tx_scheduler[items]" id="' . $fieldID . '" value="' . $taskInfo['items'] . '" size="30" />';
        $additionalFields = array();
        $additionalFields[$fieldID] = array(
            'code' => $fieldCode,
            'label' => 'Amount of queue items to process in each run',
        );

        return $additionalFields;
    }

    /**
     * validates the input of the custom field
     *
     * @param  array &$submittedData
     * @param  SchedulerModuleController $parentObject
     * @return boolean
     */
    public function validateAdditionalFields(array &$submittedData, SchedulerModuleController $parentObject)
    {
        $submittedData['items'] = trim($submittedData['items']);

        return true;
    }

    /**
     * saves the input of the custom field
     *
     * @param  array $submittedData
     * @param  AbstractTask $task
     * @return void
     */
    public function saveAdditionalFields(array $submittedData, AbstractTask $task)
    {
        $task->setItems($submittedData['items']);
    }
}
