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
     * @return void
     */
    public function indexAction()
    {
        $this->view->assign('events', $this->eventRepository->findAll());
    }

    /**
     * action index
     *
     * @param Crossmedia\Fourallportal\Domain\Model\Event $event
     * @return void
     */
    public function checkAction($event)
    {
        $this->view->assign('event', $event);
    }
}
