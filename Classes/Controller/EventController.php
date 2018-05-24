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

use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

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
     * action index
     *
     * @param string $status
     * @param string $search
     * @return void
     */
    public function indexAction($status = null, $search = null)
    {
        $eventOptions = [
            'pending' => 'pending',
            'failed' => 'failed',
            'deferred' => 'deferred',
            'claimed' => 'claimed',
            '' => 'all'
        ];

        if ($status || $search) {
            // Load events with selected status
            $events = $this->searchEventsWithStatus($status === 'all' ? false : $status, $search);
            if ($status && $status !== 'all' && $events->count() === 0) {
                // Widen search to search other statuses than the selected one.
                $searchWidened = true;
                $events = $this->searchEventsWithStatus(false, $search);
                $status = '';
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
        if ($status) {
            $constraints[] = $query->equals('status', $status);
        }

        if ($search) {
            $constraints[] = $query->logicalOr([
                $query->equals('eventId', (integer) $search),
                $query->equals('module.connectorName', $search),
                $query->like('objectId', $search),
                $query->like('eventType', $search),
            ]);
        }

        if (count($constraints)) {
            $query->matching($query->logicalAnd($constraints));
        }

        $query->setOrderings(['tstamp' => 'DESC']);

        return $query->execute();
    }

    /**
     * action index
     *
     * @param \Crossmedia\Fourallportal\Domain\Model\Event $event
     * @return void
     */
    public function checkAction($event)
    {
        $this->view->assign('event', $event);
    }

    /**
     * action index
     *
     * @param \Crossmedia\Fourallportal\Domain\Model\Event $event
     * @return void
     */
    public function resetAction($event)
    {
        $event->setStatus('pending');
        $this->eventRepository->update($event);
        #$this->objectManager->get(PersistenceManager::class)->persistAll());
        $this->redirect('index', null, null, ['status' => 'pending']);
    }
}
