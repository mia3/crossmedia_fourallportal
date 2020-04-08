<?php
namespace Crossmedia\Fourallportal\Service;

use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\Error\ApiException;

class ApiClient
{

    /**
     * @var Server
     */
    protected $server;

    /**
     * @var string
     */
    protected $sessionId;

    /**
     * @var array
     */
    protected static $sessionPool = array();

    /**
     * @var int
     */
    protected $folderCreateMask;

    /**
     * @var int
     */
    protected $fileCreateMask;

    /**
     * @var array
     */
    protected static $lastResponse = [];

    /**
     * @var array
     */
    protected $extensionConfiguration = [];

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
        $this->extensionConfiguration = (array) @unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['fourallportal']);
    }

    public function login()
    {
        $response = $this->doPostRequest(
            $uri = $this->server->getLoginUrl(),
            [
                'username' => $this->server->getUsername(),
                'password' => base64_encode($this->server->getPassword()),
                'language' => 'en_US',
            ],
            false
        );
        if (isset($response['session'])) {
            $this->sessionId = $response['session'];

            return $this->sessionId;
        }

        return false;
    }

    public function logout()
    {
        if ($this->sessionId !== null) {
            $this->doPostRequest(
                $uri = $this->server->getRestUrl() . 'LoginRemoteService/logout',
                [
                    $this->sessionId,
                ],
                false
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
            ],
            false
        );
        $this->validateResponseCode($response);
        return $response['result'];
    }

    /**
     * Get module configuration from MAM
     *
     * @apiparam session_id - Usersession
     * @apiparam connector_name - Name des Connectors
     *
     * @param string $moduleName
     * @return array $configuration
     * @throws ApiException
     */
    public function getModuleConfig($moduleName = null)
    {
        $response = $this->doPostRequest(
            $uri = $this->server->getRestUrl() . 'PAPRemoteService/getModuleConfig',
            [
                $this->sessionId,
                $moduleName,
            ],
            false
        );
        $this->validateResponseCode($response);
        return $response['result'];
    }

    /**
     * Fetches a specific derivate from MAM
     *
     * @param string $objectId id of the object to get a derivate for
     * @return array
     */
    public function getDerivate($objectId, $usage = null)
    {
        if ($usage === null) {
            $usage = $this->defaultDerivate;
        }
        $query = array(
            'session' => $this->sessionId,
            'apptype' => 'MAM',
            'clientType' => 'Web',
            'usage' => $usage,
            'id' => $objectId,
        );
        $uri = $this->server->getDataUrl() . '?' . http_build_query($query);

        return $this->doGetRequest($uri);
    }

    /**
     * @param string $filename
     * @param string $objectId
     * @param string|null $usage
     * @return bool|string
     * @throws ApiException
     */
    public function saveDerivate($filename, $objectId, $usage = null)
    {
        $query = array(
            'session' => $this->sessionId,
            'apptype' => 'MAM',
            'clientType' => 'Web',
            'usage' => $usage ?: 'Original',
            'id' => $objectId,
        );
        $uri = $this->server->getDataUrl() . '?' . http_build_query($query);

        //echo '  fetching: ' . $uri . PHP_EOL;

        $temporaryFilename = tempnam(sys_get_temp_dir(), 'fal_mam-' . $objectId);

        if (!file_exists(dirname($temporaryFilename))) {
            $oldUmask = umask(0);
            mkdir(dirname($temporaryFilename), $this - $this->folderCreateMask, true);
            umask($oldUmask);
        }

        $fp = fopen($temporaryFilename, 'w+');
        $ch = curl_init($uri);
        $temporaryHeaderbufferName = tempnam(sys_get_temp_dir(), 'header-buff' . $objectId);
        $headerBuff = fopen($temporaryHeaderbufferName, 'w+');

        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$this->extensionConfiguration['clientConnectTimeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$this->extensionConfiguration['clientTransferTimeout']);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_WRITEHEADER, $headerBuff);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool)$this->extensionConfiguration['verifyPeer']);

        //echo '  sending request...' . PHP_EOL;

        curl_exec($ch);

        rewind($headerBuff);
        $headers = stream_get_contents($headerBuff);
        fclose($headerBuff);
        unlink($temporaryHeaderbufferName);

        $info = curl_getinfo($ch);
        if (preg_match('/filename="([^"]+)/', $headers, $matches)) {
            $filename = substr($filename, 0, strrpos($filename, '/') + 1) . $matches[1];
        }

        //echo '  data received (' . filesize($temporaryFilename) . ' bytes, code: ' . $info['http_code']  . ')' . PHP_EOL;

        if (preg_match('/Content-Length:[^0-9]*([0-9]+)/', $headers, $matches)) {
            $expectedFileSize = $matches[1];
        }

        if (!empty($curlError = curl_error($ch))) {
            throw new ApiException('CURL Failed with the Error: ' . $curlError);
        }

        if ($info['http_code'] !== 200) {
            $errorMessage = sprintf('CURL response code was %d when fetching "%s": ', $info['http_code'], $uri);
            //echo '  ' . $errorMessage . PHP_EOL;
            throw new \RuntimeException($errorMessage);
        }

        curl_close($ch);
        fclose($fp);

        if ($expectedFileSize > 0 && $expectedFileSize != filesize($temporaryFilename)) {
            unlink($temporaryFilename);
            throw new ApiException('The downloaded file does not match the expected filesize');
        }

        if (!file_exists(dirname($filename))) {
            $oldUmask = umask(0);
            mkdir(dirname($filename), $this->folderCreateMask, true);
            umask($oldUmask);
        }
        rename($temporaryFilename, $filename);
        chmod($filename, $this->fileCreateMask);

        return $filename;
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
     * @param string $connectorName
     * @param  integer $eventId
     * @return array $events
     *
     * id - event id
     * create_time - time of creation
     * object_id - id of the relevant object
     * object_type - type of the relevant object (0 = bean, 1 = derivate, 2 = both)
     * field_name - derivate type
     * event_type - type of event (0 = delete, 1 = update, 2 = create)
     * @throws ApiException
     */
    public function getEvents($connectorName, $eventId)
    {
        $connectorConfig = $this->getConnectorConfig($connectorName);

        $response = $this->doPostRequest(
            $uri = $this->server->getRestUrl() . 'PAPRemoteService/getEvents',
            [
                $this->sessionId,
                $connectorName,
                $eventId ? $eventId + 1 : 0,
                $connectorConfig['config_hash']
            ],
            false
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
     * @return array $events
     * @throws ApiException
     */
    public function synchronize($connectorName = null)
    {
        $response = $this->doPostRequest(
            $uri = $this->server->getRestUrl() . 'PAPRemoteService/synchronize',
            [
                $this->sessionId,
                $connectorName
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
     * @param integer|array $objectIds
     * @param string $connectorName
     * @return array $beans
     * @throws ApiException
     */
    public function getBeans($objectIds, string $connectorName): iterable
    {
        if (!is_array($objectIds)) {
            $objectIds = array($objectIds);
        }

        $beans = $this->doPostRequest(
            $this->server->getRestUrl() . 'PAPRemoteService/getBeans',
            array(
                $this->sessionId,
                $connectorName,
                $objectIds,
            )
        );

        if (!isset($beans['result'][0])) {
            throw new ApiException('Bean data request returned no results. Response: ' . json_encode($beans), 1525694885);
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

        $this->validateResponseCode($result);

        return $result['result'];
    }

    /**
     * @param string $uri
     * @param array $data
     * @param bool $persist
     * @return array
     */
    public function doPostRequest($uri, $data, $persist = true)
    {
        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$this->extensionConfiguration['clientConnectTimeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$this->extensionConfiguration['clientTransferTimeout']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool)$this->extensionConfiguration['verifyPeer']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        $response = curl_exec($ch);
        if ($persist) {
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, array(&$this, 'catchResponseHeaderCallback'));
            static::$lastResponse['headers'] = [];
            static::$lastResponse['response'] = $response;
            static::$lastResponse['url'] = $uri;
            static::$lastResponse['payload'] = json_encode($data, JSON_PRETTY_PRINT);
        }
        $result = json_decode($response, true);

        $this->validateResponseCode($result);

        return $result;
    }

    /**
     * @param object $curl
     * @param string $line
     * @return integer
     */
    public function catchResponseHeaderCallback($curl, $line)
    {
        static::$lastResponse['headers'][] = $line;
        return mb_strlen($line);
    }

    /**
     * @param mixed $result
     * @throws ApiException
     */
    protected function validateResponseCode($result)
    {
        if (!isset($result['code']) || $result['code'] != 0) {
            $message = isset($result['message']) ? $result['message'] : 'MamClient: could not communicate with mam api. please try again later';
            throw new ApiException($message . ' - Response code ' . $result['code'] . ': ' . $this->translateResponseCode($result['code']));
        }
        if (!is_array($result)) {
            throw new ApiException('The MAM API returned garbage data (not JSON array)');
        }
    }

    /**
     * @param integer $code
     * @return string
     */
    protected function translateResponseCode($code)
    {
        switch ($code) {
            case -1: return 'UNDEFINED_ERROR';
            case 1: return 'PARAMETER_NOT_SET';
            case 2: return 'FUNCTION_NOT_IMPLEMENTED';
            default: return 'Error code given but not known by the client, see REST API documentation';
        }
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
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$this->extensionConfiguration['clientConnectTimeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$this->extensionConfiguration['clientTransferTimeout']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool)$this->extensionConfiguration['verifyPeer']);
        static::$lastResponse['headers'] = [];
        static::$lastResponse['response'] = $result = curl_exec($ch);
        static::$lastResponse['uri'] = $uri;
        static::$lastResponse['payload'] = '';
        return $result;
    }

    protected function initializeCreateMasks(): void
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

    /**
     * @return array
     */
    public function getLastResponse()
    {
        $response = static::$lastResponse;
        $response['headers'] = trim(implode('', $response['headers'] ?? []));
        $decoded = json_decode($response['response'], true);
        if ($decoded) {
            $response['response'] = json_encode($decoded, JSON_PRETTY_PRINT);
        }
        return $response;
    }
}
