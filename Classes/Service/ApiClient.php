<?php
namespace Crossmedia\Fourallportal\Service;

use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\Error\ApiException;
use Crossmedia\Fourallportal\Error\MamApiException;


class ApiClient
{

    /**
     * @var Server
     */
    protected $server;

    /**
     * @var string
     */
    protected $customer;

    /**
     * @var string
     */
    protected $sessionId;

    /**
     * @var array
     */
    protected static $sessionPool = array();

    /**
     * @var \Crossmedia\Fourallportal\Service\Logger
     * @inject
     */
    protected $logger;

    /**
     * @var int
     */
    protected $folderCreateMask;

    /**
     * @var int
     */
    protected $fileCreateMask;

    /**
     * @param Server $server
     */
    public function __construct($server)
    {
        $this->server = $server;
        if (empty(self::$sessionPool)) {
            register_shutdown_function(function () {
                foreach (self::$sessionPool as $session) {
                    $session->logout();
                }
            });
        }
        self::$sessionPool[] = $this;
        $this->initializeCreateMasks();
    }

    public function login()
    {
        $response = $this->doPostRequest(
            $uri = $this->server->getRestUrl() . 'LoginRemoteService/login',
            [
                $this->server->getUsername(),
                urlencode($this->server->getPassword()),
                $this->server->getCustomerName(),
            ]
        );
        $this->logger->debug('API Login', $response);
        if (isset($response['result']['sessionID'])) {
            $this->sessionId = $response['result']['sessionID'];

            return true;
        }

        return false;
    }

    public function logout()
    {
        if ($this->sessionId !== null) {
            $this->logger->debug('API Logout', $this->sessionId);
            $this->doPostRequest(
                $uri = $this->server->getRestUrl() . 'LoginRemoteService/logout',
                [
                    $this->sessionId,
                ]
            );
            $this->sessionId = null;
        }
    }

    /**
     * Get configuration for a connector from MAM
     *
     * @apiparam session_id - Usersession
     * @apiparam connector_name - Name des Connectors
     *
     * @param string $connectorName
     * @return array $configuration
     * @throws ApiException
     */
    public function getConnectorConfig($connectorName = null)
    {
        $response = $this->doPostRequest(
            $uri = $this->server->getRestUrl() . 'PAPRemoteService/getConnectorConfig',
            [
                $this->sessionId,
                $connectorName,
            ]
        );
        switch ($response['code']) {
            case 0:
                return $response['result'];
                break;

            default:
                throw new ApiException($response['code'] . ': ' . $response['message']);
        }
    }

    /**
     * Get events from MAM starting from a specific event id
     *
     * Dieser Service liefert nicht alle IDs aus maximal 1000.
     * Es sind alle Events ausgeliefert, sobald 0 Werte zurückgegeben werden.
     *
     * @apiparam session_id - Usersession
     * @apiparam connector_name - Name des Connectors
     * @apiparam event_id - Die Id des ersten Events
     * @apiparam config_hash - MD5. Hash der Konfiguration, um Änderungen an der Konfiguration zu erkennen.
     *
     * @param  integer $eventId
     * @return array $events
     *
     * id - event id
     * create_time - time of creation
     * object_id - id of the relevant object
     * object_type - type of the relevant object (0 = bean, 1 = derivate, 2 = both)
     * field_name - derivate type
     * event_type - type of event (0 = delete, 1 = update, 2 = create)
     */
    public function getEvents($eventId)
    {
        $events = $this->getRequest('getEvents', array(
            $this->sessionId,
            $this->connectorName,
            $eventId,
            $this->configHash,
        ));

        return $events;
    }

    /**
     * Start a synchronization
     *
     * Dieser Service liefert nicht alle IDs aus maximal 1000.
     * Es sind alle Events ausgeliefert, sobald 0 Werte zurückgegeben werden.
     *
     * @apiparam session_id - Usersession
     * @apiparam connector_name - Name des Connectors
     * @apiparam event_id - Die Id des ersten Events
     * @apiparam offset - offset der IDs
     *
     * @param string $connectorName
     * @param integer $lastEventId
     * @return array $events
     * @throws ApiException
     */
    public function synchronize($connectorName = null)
    {
        $response = $this->doPostRequest(
            $uri = $this->server->getRestUrl() . 'PAPRemoteService/synchronize',
            [
                $this->sessionId,
                $connectorName,
                0,
                0,
            ]
        );
        switch ($response['code']) {
            case 0:
                return $response['result'];
                break;

            default:
                throw new ApiException($response['code'] . ': ' . $response['message']);
        }
    }

    /**
     * Get events from MAM starting from a specific event id
     *
     * Dieser Service liefert nicht alle IDs aus maximal 1000.
     * Es sind alle Events ausgeliefert, sobald 0 Werte zurückgegeben werden.
     *
     * @apiparam session_id - Usersession
     * @apiparam connector_name - Name des Connectors
     * @apiparam ids - Die Ids des Beans
     *
     * @param string $connectorName
     * @param integer|array $objectIds
     * @param string $connectorName
     * @return array $beans
     */
    public function getBeans($objectIds, $connectorName = null)
    {
        if (!is_array($objectIds)) {
            $objectIds = array($objectIds);
        }

        $beans = $this->doPostRequest(
            $this->server->getRestUrl() . 'PAPRemoteService/getBeans',
            array(
                $this->sessionId,
                $connectorName ? $connectorName : $this->connectorName,
                $objectIds,
            )
        );

        $beans = $this->normalizeArray($beans);
        foreach ($beans as $key => $bean) {
            #$beans[$key]['properties']['data_shellpath'] = $this->normalizePath($beans[$key]['properties']['data_shellpath']);
        }

        return $beans;
    }

    /**
     * build a remote request towards the MAM API
     *
     * @param string $method
     * @param $parameter
     * @return array
     * @throws ApiException
     * @internal param array $parameters
     */
    public function getRequest($method, $parameter)
    {
        $uri = $this->server->getRestUrl() . $method . '?' . http_build_query(['parameter' => json_encode($parameter)]);
        $response = $this->doGetRequest($uri);
        $result = json_decode($response, true);
        if (!isset($result['code']) || $result['code'] !== 0) {
            $message = isset($result['message']) ? $result['message'] : 'MamClient: could not communicate with mam api. please try again later';
            throw new ApiException($message . ' - ' . $response);
        }

        return $result['result'];
    }

    /**
     * @param string $uri
     * @param array $data
     * @return array
     */
    public function doPostRequest($uri, $data)
    {
        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_POST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);
        $result = json_decode($response, true);

        if (!isset($result['code']) || $result['code'] !== 0) {
            $message = isset($result['message']) ? $result['message'] : 'MamClient: could not communicate with mam api. please try again later';
            throw new ApiException($message . ' - ' . $response);
        }

        return $result;
    }

    /**
     * execute a remote request towards the MAM API
     *
     * @param string $uri
     * @return array
     */
    public function doGetRequest($uri)
    {
        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);

        return $result;
    }

    /**
     * normalizes an MAM result array into a flatter php array
     *
     * example:
     *
     * input:                 =>     output:
     * array (                       array (
     *   'foo' => array(               'foo' => 'bar'
     *     'value' => 'bar'          )
     *   )
     * )
     *
     * @param array $input
     * @return array
     */
    public function normalizeArray($input)
    {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $input[$key] = $this->normalizeArray($value);
            }
            if (count($input) == 1 && array_key_exists('value', $input)) {
                $input = $input['value'];
            }
            if (is_array($input) && count($input) == 0) {
                $input = null;
            }
        }

        return $input;
    }

    /**
     * normalizes a shell_path by removing the remote base shell_path to receive
     * a "relative" shell_path
     *
     * Example (configuration['mam_shell_path'] = '/usr/local/mam/wanzl/'):
     *
     * /usr/local/mam/wanzl/data/foo.png   => data/foo.png
     *
     * @param string $path
     * @return string
     */
    public function normalizePath($path)
    {
        if (strlen($this->configuration['mam_shell_path']) > 0) {
            $path = rtrim($this->configuration['base_path'],
                    '/') . '/' . ltrim(str_replace($this->configuration['mam_shell_path'], '', $path), '/');
        }
        $path = ltrim($path, '/\\');

        return $path;
    }

    protected function initializeCreateMasks()
    {
        if (isset($GLOBALS['TYPO3_CONF_VARS']['BE']['folderCreateMask'])) {
            $this->folderCreateMask = octdec($GLOBALS['TYPO3_CONF_VARS']['BE']['folderCreateMask']);
        } else {
            if (isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask'])) {
                $this->folderCreateMask = octdec($GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask']);
            } else {
                $this->folderCreateMask = octdec(2775);
            }
        }

        if (isset($GLOBALS['TYPO3_CONF_VARS']['BE']['fileCreateMask'])) {
            $this->fileCreateMask = octdec($GLOBALS['TYPO3_CONF_VARS']['BE']['fileCreateMask']);
        } else {
            if (isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fileCreateMask'])) {
                $this->fileCreateMask = octdec($GLOBALS['TYPO3_CONF_VARS']['SYS']['fileCreateMask']);
            } else {
                $this->fileCreateMask = octdec(0664);
            }
        }
    }
}
