<?php
namespace Crossmedia\Fourallportal\Controller;

use Crossmedia\Fourallportal\Error\ApiException;
use Crossmedia\Fourallportal\Service\ApiClient;
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
 * ServerController
 */
class ServerController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * serverRepository
     *
     * @var \Crossmedia\Fourallportal\Domain\Repository\ServerRepository
     * @inject
     */
    protected $serverRepository = null;

    /**
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
        $this->view->assign('servers', $this->serverRepository->findAll());
    }

    /**
     * action check
     *
     * @param \Crossmedia\Fourallportal\Domain\Model\Server $server
     * @return void
     */
    public function checkAction(\Crossmedia\Fourallportal\Domain\Model\Server $server)
    {
        $status = [];
        $client = $this->objectManager->get(ApiClient::class, $server);
        $loginSuccessfull = $client->login();
        $status[] = [
            'title' => 'login',
            'class' => $loginSuccessfull ? 'success' : 'danger',
            'description' => $loginSuccessfull ? 'Login Successfull' : 'Login failed'
        ];
        foreach ($server->getModules() as $module) {
            /** @var Module $module */
            try {    $config = $client->getConnectorConfig($module->getConnectorName());
                $description = '
                <strong>Module Name:</strong> ' . $config['moduleConfig']['module_name'] . '<br />
                <strong>Config Hash:</strong> ' . $config['config_hash'] . '<br />
                <h4>Fields</h4>
                ';
                foreach ($config['fieldsToLoad'] as $field) {
                    $description .= '<strong>' . (isset($field['name']) ? $field['name'] : $field['fieldName']) . ': </strong>' . (isset($field['type']) ? $field['type'] : $field['fieldType']) . '<br />';
                }
                $status[] = [
                    'title' => 'connector: ' . $module->getConnectorName(),
                    'class' => 'success',
                    'description' => $description
                ];
            } catch (ApiException $exception) {    $status[] = [
                    'title' => 'connector: ' . $module->getConnectorName(),
                    'class' => 'danger',
                    'description' => $exception->getMessage()
                ];
            }
        }
        $this->view->assign('status', $status);
        $this->view->assign('server', $server);
    }

    /**
     * action disable
     *
     * @param \Crossmedia\Fourallportal\Domain\Model\Server $server
     * @return void
     */
    public function disableAction(\Crossmedia\Fourallportal\Domain\Model\Server $server)
    {
        $server->setActive(false);
        $this->serverRepository->update($server);
        $this->redirect('index');
    }

    /**
     * action enable
     *
     * @param \Crossmedia\Fourallportal\Domain\Model\Server $server
     * @return void
     */
    public function enableAction(\Crossmedia\Fourallportal\Domain\Model\Server $server)
    {
        $server->setActive(true);
        $this->serverRepository->update($server);
        $this->redirect('index');
    }

    /**
     * action delete
     *
     * @param \Crossmedia\Fourallportal\Domain\Model\Server $server
     * @return void
     */
    public function deleteAction(\Crossmedia\Fourallportal\Domain\Model\Server $server)
    {
        $this->serverRepository->remove($server);
        $this->redirect('index');
    }

    /**
     * action restartSynchronisation
     *
     * @param \Crossmedia\Fourallportal\Domain\Model\Server $server
     * @return void
     */
    public function restartSynchronisationAction(\Crossmedia\Fourallportal\Domain\Model\Server $server)
    {
        foreach ($server->getModules() as $module) {
            $module->setLastEventId(0);
            $events = $this->eventRepository->findByModule($module);
            foreach ($events as $event) {
                $this->eventRepository->remove($event);
            }
        }
        $this->serverRepository->update($server);
        $this->redirect('index');
    }
}
