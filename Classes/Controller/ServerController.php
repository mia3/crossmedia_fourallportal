<?php
namespace Crossmedia\Fourallportal\Controller;

use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Repository\ModuleRepository;
use Crossmedia\Fourallportal\Error\ApiException;
use Crossmedia\Fourallportal\Service\ApiClient;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

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
     * @param Module $module
     * @param string $uuid
     */
    public function moduleAction(Module $module = null, $uuid = null)
    {
        if ($module) {
            $response = $module->getServer()->getClient()->getBeans($uuid, $module->getConnectorName());
            $pretty = json_encode($response, JSON_PRETTY_PRINT);
            $this->view->assign('prettyResponse', $pretty);
            $this->view->assign('response', $response);
            $this->view->assign('uuid', $uuid);
            $this->view->assign('module', $module);
            $this->view->assign('verifyRelations', true);
        }
        $this->view->assign('modules', GeneralUtility::makeInstance(ObjectManager::class)->get(ModuleRepository::class)->findAll(true));
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
        $client = $server->getClient();
        try {
            $loginSuccessfull = $client->login();
            $status[] = [
                'title' => 'login',
                'class' => $loginSuccessfull ? 'success' : 'danger',
                'description' => $loginSuccessfull ? 'Login Successfull' : 'Login failed',
            ];
            foreach ($server->getModules() as $module) {
                /** @var Module $module */
                try {
                    $config = $client->getConnectorConfig($module->getConnectorName());
                    $currentConfigurationHash = $module->getConfigHash();
                    if ($config['config_hash'] !== $currentConfigurationHash) {
                        $description = '
                            <h2 class="text-danger">WARNING</h2>
                            <p>The config hash "' . $config['config_hash'] . '" does not match the persisted config hash
                            "' . $currentConfigurationHash . '" which indicates the PIM schema has changed. To update
                            compatibility you can use the CLI command <code>fourallportal:pinschema</code>. 
                        ';
                    } else {
                        $description = '
                            <strong>Module Name:</strong> ' . $config['moduleConfig']['module_name'] . '<br />
                            <strong>Config Hash:</strong> ' . $config['config_hash'] . '<br />
                        ';
                    }
                    $description .= '<h4>Fields</h4><table>';
                    foreach ($config['fieldsToLoad'] as $field) {
                        $description .= '
                            <tr>
                                <th>' . (isset($field['name']) ? $field['name'] : $field['fieldName']) . ': </th>
                                <td>' . (isset($field['type']) ? $field['type'] : $field['fieldType']) . '</td>
                            </tr>
                        ';
                    }
                    $description .= '</table>';

                    $moduleStatus = [
                        'title' => 'connector: ' . $module->getConnectorName(),
                        'class' => 'success',
                        'description' => $description,
                    ];

                    $mappingClass = $module->getMappingClass();
                    $mapping = new $mappingClass();
                    $moduleStatus = $mapping->check($client, $module, $moduleStatus);
                    $status[] = $moduleStatus;
                } catch (ApiException $exception) {
                    $status[] = [
                        'title' => 'connector: ' . $module->getConnectorName(),
                        'class' => 'danger',
                        'description' => $exception->getMessage(),
                    ];
                }
            }
        } catch (ApiException $exception) {
            $status[] = [
                'title' => 'login',
                'class' => 'danger',
                'description' => 'Login failed (' . $exception->getMessage() . ')',
            ];
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
        foreach ($this->serverRepository->findAll() as $persistedServer) {
            $persistedServer->setActive($server === $persistedServer);
            $this->serverRepository->update($persistedServer);
        }

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
