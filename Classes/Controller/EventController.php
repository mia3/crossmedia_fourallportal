<?php
namespace Crossmedia\Fourallportal\Controller;

/***
 *
 * This file is part of the "4AllPortal Connector" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Marc Neuhaus <marc@mia3.com>, MIA3 GmbH & Co. KG
 *
 ***/

use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Response\CollectingResponse;
use Crossmedia\Fourallportal\Service\EventExecutionService;

/**
 * EventController
 */
class EventController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * eventRepository
     *
     * @var \Crossmedia\Fourallportal\Domain\Repository\EventRepository
     * @inject
     */
    protected $eventRepository = null;

    /**
     * @var EventExecutionService
     */
    protected $eventExecutionService = null;

    /**
     * @param EventExecutionService $eventExecutionService
     */
    public function injectEventExecutionService(EventExecutionService $eventExecutionService)
    {
        $this->eventExecutionService = $eventExecutionService;
    }

    /**
     * action index
     *
     * @param string $status
     * @param string $search
     * @param string $objectId
     * @param Event $modifiedEvent
     * @return void
     */
    public function indexAction($status = null, $search = null, $objectId = null, Event $modifiedEvent = null)
    {
        $eventOptions = [
            'pending' => 'Status: pending',
            'failed' => 'Status: failed',
            'deferred' => 'Status: deferred',
            'claimed' => 'Status: claimed',
            'all' => 'Status: all'
        ];

        if ($objectId) {
            $events = $this->eventRepository->findByObjectId($objectId);
            $status = 'all';
        } elseif ($status || $search) {
            // Load events with selected status
            $events = $this->searchEventsWithStatus($status, $search);
            if ($status !== 'all' && $events->count() === 0) {
                // Widen search to search other statuses than the selected one.
                $searchWidened = true;
                $events = $this->searchEventsWithStatus(false, $search);
                $status = 'all';
            }
        } else {
            // Find first status from prioritised list above which yields results
            do {
                $status = current($eventOptions);
                $events = $this->searchEventsWithStatus($status, $search);
            } while ($events->count() === 0 && next($eventOptions));
        }

        $this->view->assign('searchWidened', $searchWidened);
        $this->view->assign('status', $status);
        $this->view->assign('events', $events);
        $this->view->assign('search', $search);
        $this->view->assign('objectId', $objectId);
        $this->view->assign('modifiedEvent', $modifiedEvent);
        $this->view->assign('eventStatusOptions', $eventOptions);
    }

    /**
     * @param string $status
     * @param string $search
     * @return \TYPO3\CMS\Extbase\Persistence\QueryResultInterface
     */
    protected function searchEventsWithStatus($status, $search)
    {
        $query = $this->eventRepository->createQuery();
        $constraints = [];
        if ($status !== 'all') {
            $constraints[] = $query->equals('status', $status);
        }

        if ($search) {
            $constraints[] = $query->logicalOr([
                $query->equals('eventId', (integer) $search),
                $query->like('module.connectorName', '%' . $search . '%'),
                $query->like('objectId', '%' . $search . '%'),
                $query->like('eventType', '%' . $search . '%'),
            ]);
        }

        if (count($constraints)) {
            $query->matching($query->logicalAnd($constraints));
        }

        $query->setOrderings(['module.sorting' => 'ASC', 'event_id' => 'ASC']);

        return $query->execute();
    }

    /**
     * @param \Crossmedia\Fourallportal\Domain\Model\Event $event
     * @return void
     */
    public function checkAction($event)
    {
        $events = $this->eventRepository->findByObjectId($event->getObjectId());
        $this->view->assign('event', $event);
        $this->view->assign('events', $events);
        foreach ($events as $historicalEvent) {
            if ($historicalEvent->getEventType() === 'delete') {
                $this->view->assign('deleted', ($historicalEvent->getStatus() === 'claimed'));
                $this->view->assign('deletedScheduled', ($historicalEvent->getStatus() === 'pending'));
                break;
            }
        }
    }

    /**
     * @param \Crossmedia\Fourallportal\Domain\Model\Event $event
     * @return void
     */
    public function resetAction($event)
    {
        $event->setStatus('pending');
        $event->setNextRetry(0);
        $event->setRetries(0);
        $this->eventRepository->update($event);
        $this->redirect('index', null, null, ['status' => 'pending']);
    }

    /**
     * @param \Crossmedia\Fourallportal\Domain\Model\Event $event
     * @return void
     */
    public function executeAction($event)
    {

        $fakeResponse = new CollectingResponse();
        $this->eventExecutionService->setResponse($fakeResponse);
        $this->eventExecutionService->processEvent($event, false);

        $message = $fakeResponse->getCollected() ?: 'No output from action';

        $this->addFlashMessage($message, 'Executed event ' . $event->getEventId());

        $this->redirect('index', null, null, ['modifiedEvent' => $event->getUid()]);
    }

    public function syncAction()
    {
        $fakeResponse = new CollectingResponse();
        $this->eventExecutionService->setResponse($fakeResponse);
        $this->eventExecutionService->sync();
        $this->addFlashMessage(nl2br($fakeResponse->getCollected()) ?: 'No new events to fetch', 'Executed');
        $this->redirect('index');
    }
}
