<?php
namespace Crossmedia\Fourallportal\Mapping;

use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Service\ApiClient;

class FalMapping extends AbstractMapping
{

    /**
     * @param ApiClient $client
     * @param Module $module
     * @param array $status
     * @return array
     */
    public function check(ApiClient $client, Module $module, array $status)
    {
        if (empty($module->getShellPath())) {
            $status['class'] = 'danger';
            $status['description'] .= '
                <h3>FalMapping</h3>
            ';
            $events = $client->getEvents($module->getConnectorName(), 0);
            $ids = [];
            foreach($events as $event) {
                $ids[] = $event['object_id'];
                if (count($ids) == 3) {
                    break;
                }
            }
            $messages = [];
            $beans = $client->getBeans($ids, $module->getConnectorName());
            $paths = [];
            foreach($beans['result'] as $result) {
                if (!isset($result['properties']['data_name'])) {
                    $messages['data_name'] = '<p><strong class="text-danger">Connector does not provide required "data_name" property</strong></p>';
                }
                if (!isset($result['properties']['data_shellpath'])) {
                    $messages['data_shellpath'] = '<p><strong class="text-danger">Connector does not provide required "data_shellpath" property</strong></p>';
                }
                $paths[] = $result['properties']['data_shellpath'] . $result['properties']['data_name'];
            }
            $messages['shellpath_missing'] = '
                <p>
                    <strong class="text-danger">Missing ShellPath in ModuleConfig</strong><br />
                </p>
                <p>
                    <strong>Paths of the 3 first Files:</strong><br />
                    ' . implode('<br />', $paths) . '
                </p>
            ';

            $status['description'] .= implode(chr(10), $messages);
        }
        return $status;
    }

}
