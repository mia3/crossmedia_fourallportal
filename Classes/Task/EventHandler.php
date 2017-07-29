<?php
namespace Crossmedia\Fourallportal\Task;

use Crossmedia\Fourallportal\Service\MamClient;
use Crossmedia\Fourallportal\Task\EventHandlerState;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * This Scheduler Task fetches events from the MAM API and queues them into
 * the tx_falmam_event_queue table.
 */
class EventHandler extends AbstractTask
{

    /**
     * @var \Crossmedia\Fourallportal\Service\MamClient
     */
    protected $client;

    /**
     * @var \TYPO3\CMS\Core\DataHandling\DataHandler
     */
    protected $dataHandler;

    /**
     * @var \Crossmedia\Fourallportal\Task\EventHandlerState
     */
    protected $state;

    /**
     * @var \Crossmedia\Fourallportal\Service\Configuration
     */
    protected $configuration;

    /**
     * @var \Crossmedia\Fourallportal\Service\Logger
     */
    protected $logger;

    /**
     * @param  \Crossmedia\Fourallportal\Service\MamClient $client
     * @return void
     */
    public function injectClient(\Crossmedia\Fourallportal\Service\MamClient $client)
    {
        $this->client = $client;
    }

    /**
     * @param  \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
     * @return void
     */
    public function injectDataHandler(\TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler)
    {
        $this->dataHandler = $dataHandler;
    }


    /**
     * @param  \Crossmedia\Fourallportal\Task\EventHandlerState $state
     * @return void
     */
    public function injectState(\Crossmedia\Fourallportal\Task\EventHandlerState $state)
    {
        $this->state = $state;
    }

    /**
     * @param  \Crossmedia\Fourallportal\Service\Configuration $configuration
     * @return void
     */
    public function injectConfiguration(\Crossmedia\Fourallportal\Service\Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * This Scheduler Task fetches events from the MAM API and queues them into
     * the tx_falmam_event_queue table.
     *
     * Additionally it checks if the configuration in MAM has changed and notifies
     * the administrator through e-mail that he may have to update the mapping
     * of new fields and start a full sync to pull in old metadata.
     *
     * @return [type] [description]
     */
    public function execute()
    {
        $this->initialize();
        $this->logger = new \Crossmedia\Fourallportal\Service\Logger();

        if ($this->hasConfigurationChanged() && $this->state->getNotified() < (time() - (60 * 60 * 24))) {
            // notify someone to update the configuration

            $mail = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Mail\\MailMessage');
            $from = strlen($GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress']) > 0 ? $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] : 'no-reply@foo.bar';
            $mail->setFrom($from)
                ->setTo($this->configuration->admin_mail)
                ->setSubject('MAM API Configuration has changed!')
                ->setContentType("text/html")
                ->setBody('
			     	<p><strong>Please check the field mapping configuration to map new/changed fields</strong></p>

			     	<p>
			     		<strong>Sitename:</strong> ' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] . '<br />
			     		<strong>Connector Name</strong> ' . $this->configuration->connector_name . '<br />
			     		<strong>Customer Name</strong> ' . $this->configuration->customer . '<br />
			     	</p>
			     ')
                ->send();

            $this->state->setNotified(time());
            $this->state->save();
        }

        $eventTypes = array(
            0 => 'delete',
            1 => 'update',
            2 => 'create',
        );
        $targetTypes = array(
            0 => 'metadata',
            1 => 'file',
            2 => 'both',
        );

        // flush "outdated" pending events
        $GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_falmam_event_queue',
            'status = "DONE" AND tstamp < ' . (time() - 60 * 60 * 24 * 14));
//        echo 'fetching events starting from: ' . ($this->state->getEventId() + 1) . chr(10);
        while (count($events = $this->client->getEvents($this->state->getEventId() + 1)) > 0) {
            $start = microtime(true);
            $data = array();
            $typeCounter = array(
                'delete' => 0,
                'update' => 0,
                'create' => 0,
            );
            foreach ($events as $key => $event) {
                $data['tx_falmam_event_queue']['NEW' . $event['id']] = array(
                    'pid' => $this->configuration->storage_pid,
                    'event_id' => $event['id'],
                    'event_type' => $eventTypes[$event['event_type']],
                    'target' => $targetTypes[$event['object_type']],
                    'object_id' => $event['object_id'],
                    'status' => 'NEW',
                );
                $typeCounter[$eventTypes[$event['event_type']]]++;
            }

            $this->saveEvents($data);
            // echo count($events) . ': ' . (microtime(TRUE) - $start) . chr(10);
            if (count($events) > 0) {
                $this->logger->warning('Received new Events', array(
                    'total' => count($events),
                    'counter per type' => $typeCounter,
                    'events' => $events,
                ));
            }

            $this->state->setEventId($event['id']);
            $this->state->save();
        }

        $this->client->logout();

        return true;
    }

    /**
     * We need to inject by ourself, because the automatic dependency injection
     * doesn't seem to work for Scheduler Tasks.
     *
     * @return void
     */
    public function initialize()
    {
        $objectManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
        if ($this->client === null) {
            $this->injectClient($objectManager->get('Crossmedia\Fourallportal\Service\MamClient'));
        }
        if ($this->dataHandler === null) {
            $this->injectDataHandler($objectManager->get('TYPO3\CMS\Core\DataHandling\DataHandler'));
        }
        if ($this->state === null) {
            $this->injectState($objectManager->get('Crossmedia\Fourallportal\Task\EventHandlerState'));
        }
        if ($this->configuration === null) {
            $this->injectConfiguration($objectManager->get('Crossmedia\Fourallportal\Service\Configuration'));
        }
    }

    /**
     * bulk saves the events to the tx_falmam_event_queue table for better
     * performance.
     *
     * @param  array $data
     * @return void
     */
    public function saveEvents($data)
    {
        $this->dataHandler->start($data, array());
        $this->dataHandler->process_datamap();
    }

    /**
     * check if the configuration has changed since the last time the task has
     * run
     *
     * @return boolean
     */
    public function hasConfigurationChanged()
    {
        return $this->state->getConfigHash() !== $this->client->getConfigHash();
    }
}
