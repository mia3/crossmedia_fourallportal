<?php
namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\Error\ApiException;
use Crossmedia\Fourallportal\Service\ApiClient;
use \TYPO3\CMS\Extbase\Mvc\Controller\CommandController;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Form\Domain\Runtime\Exception\PropertyMappingException;

class FourallportalCommandController extends CommandController
{
    /**
     * @var \Crossmedia\Fourallportal\Domain\Repository\ServerRepository
     * */
    protected $serverRepository = null;

    /**
     * @var \Crossmedia\Fourallportal\Domain\Repository\EventRepository
     * */
    protected $eventRepository = null;

    /**
     * @var \Crossmedia\Fourallportal\Domain\Repository\ModuleRepository
     * */
    protected $moduleRepository = null;


    public function injectEventRepository(\Crossmedia\Fourallportal\Domain\Repository\EventRepository $eventRepository)
    {
        $this->eventRepository = $eventRepository;
    }

    public function injectModuleRepository(\Crossmedia\Fourallportal\Domain\Repository\ModuleRepository $moduleRepository)
    {
        $this->moduleRepository = $moduleRepository;
    }

    public function injectServerRepository(\Crossmedia\Fourallportal\Domain\Repository\ServerRepository $serverRepository)
    {
        $this->serverRepository = $serverRepository;
    }

    /**
     * @param boolean $sync
     */
    public function syncCommand($sync = false)
    {
        foreach ($this->serverRepository->findByActive(true) as $server) {
            $client = $this->getClientByServer($server);
            foreach ($server->getModules() as $module) {
                /** @var Module $module */
                if (!$sync && $module->getLastEventId() > 0) {
                    $results = $client->getEvents($module->getConnectorName(), $module->getLastEventId());
                } else {
                    $this->eventRepository->removeAll();
                    $results = $client->synchronize($module->getConnectorName());
                }
                foreach ($results as $result) {
                    $this->queueEvent($module, $result);
                }
                $this->moduleRepository->update($module);
            }
        }

        $this->objectManager->get(PersistenceManagerInterface::class)->persistAll();

        // Handle new, pending events first, which may cause some to be deferred:
        foreach ($this->eventRepository->findByStatus('pending') as $event) {
            $this->processEvent($event);
        }

        // Then handle any events that were deferred - which may cause some to be deferred again:
        foreach ($this->eventRepository->findByStatus('deferred') as $event) {
            $this->processEvent($event);
        }
    }

    /**
     * @param Event $event
     */
    public function processEvent($event)
    {
        $client = $this->getClientByServer($event->getModule()->getServer());
        try {
            $mapper = $event->getModule()->getMapper();
            $responseData = $client->getBeans(
                [
                    $event->getObjectId()
                ],
                $event->getModule()->getConnectorName()
            );
            $mapper->import($responseData, $event);
            $event->setStatus('claimed');
            // Update the Module's last recorded event ID, but only if the event ID was higher. This allows
            // deferred events to execute without lowering the last recorded event ID which would cause
            // duplicate event processing on the next run.
            $event->getModule()->setLastEventId(max($event->getEventId(), $event->getModule()->getLastEventId()));
        } catch (PropertyMappingException $error) {
            // The system was unable to map properties, most likely because of an unresolvable relation.
            // Skip the event for now; process it later.
            $event->setStatus('deferred');
        } catch(\Exception $exception) {
            $event->setStatus('failed');
        }
        $responseMetadata = $client->getLastResponse();
        $event->setHeaders($responseMetadata['headers']);
        $event->setUrl($responseMetadata['url']);
        $event->setResponse($responseMetadata['response']);
        $event->setPayload($responseMetadata['payload']);
        $this->eventRepository->update($event);
        $this->objectManager->get(PersistenceManagerInterface::class)->persistAll();
    }

    /**
     * @param Module $module
     * @param array $result
     * @return Event
     */
    protected function queueEvent($module, $result)
    {
        $event = new Event();
        $event->setModule($module);
        $event->setEventId($result['event_id']);
        $event->setObjectId($result['object_id']);
        $event->setEventType(Event::resolveEventType($result['event_type']));
        $this->eventRepository->add($event);

        return $event;
    }

    /**
     * @param Server $server
     * @return ApiClient
     */
    protected function getClientByServer(Server $server)
    {
        static $clients = [];
        $serverId = $server->getUid();
        if (isset($clients[$serverId])) {
            return $clients[$serverId];
        }
        $client = $this->objectManager->get(ApiClient::class, $server);
        $client->login();
        $clients[$serverId] = $client;
        return $client;
    }
}
