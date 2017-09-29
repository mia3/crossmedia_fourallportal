<?php
namespace Crossmedia\Fourallportal\Service;

use Crossmedia\Fourallportal\Error\MamApiException;


class MamClient
{

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var string
     */
    protected $restUrl;

    /**
     * @var string
     */
    protected $dataUrl;

    /**
     * @var string
     */
    protected $connectorName;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $customer;

    /**
     * @var string
     */
    protected $sessionId;

    /**
     * @var string
     */
    protected $configHash;

    /**
     * @var string
     */
    protected $defaultDerivate;

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

    public function __construct($autologin = true)
    {
        if ($autologin === true) {
            $this->initialize();
        }
        if (empty(self::$sessionPool)) {
            register_shutdown_function(function () {
                foreach (self::$sessionPool as $session) {
                    $session->logout();
                }
            });
        }
        self::$sessionPool[] = $this;

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

    public function initialize()
    {
        if (isset($GLOBALS['TYPO3_CONF_VARS']["EXT"]["extConf"]['fal_mam'])) {
            $configuration = unserialize($GLOBALS['TYPO3_CONF_VARS']["EXT"]["extConf"]['fal_mam']);
            $this->configuration = $configuration['fal_mam.'];

            $this->setBaseUrl($this->configuration['base_url']);
            $this->setConnectorName($this->configuration['connector_name']);
            $this->username = $this->configuration['username'];
            $this->password = $this->configuration['password'];
            $this->customer = $this->configuration['customer'];

            $this->login();

            $connectorConfig = $this->getConnectorConfig();
            $this->configHash = $connectorConfig['config_hash'];
            $this->defaultDerivate = current($connectorConfig['derivates']);
        }
    }

    /**
     * @param string $baseUrl
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
        $this->restUrl = $baseUrl . 'rest?service=PAPRemoteService';
        $this->dataUrl = $baseUrl . 'dataservice';
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * @param string $configHash
     */
    public function setConfigHash($configHash)
    {
        $this->configHash = $configHash;
    }

    /**
     * @return string
     */
    public function getConfigHash()
    {
        return $this->configHash;
    }

    /**
     * @param string $connectorName
     */
    public function setConnectorName($connectorName)
    {
        $this->connectorName = $connectorName;
    }

    /**
     * @return string
     */
    public function getConnectorName()
    {
        return $this->connectorName;
    }

    /**
     * @param string $sessionId
     */
    public function setSessionId($sessionId)
    {
        $this->sessionId = $sessionId;
    }

    /**
     * @return string
     */
    public function getSessionId()
    {
        return $this->sessionId;
    }

    /**
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $customer
     */
    public function setCustomer($customer)
    {
        $this->customer = $customer;
    }

    /**
     * @return string
     */
    public function getCustomer()
    {
        return $this->customer;
    }

    public function login()
    {
        $response = $this->getRequest('login', array(
            $this->username,
            urlencode($this->password),
            $this->customer,
        ));
        if (isset($response['sessionID'])) {
            $this->sessionId = $response['sessionID'];

            return true;
        }

        return false;
    }

    public function logout()
    {
        if ($this->sessionId !== null) {
            $this->getRequest('logout', array(
                $this->sessionId,
            ));
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
     */
    public function getConnectorConfig($connectorName = null)
    {
        $configuration = $this->getRequest('getConnectorConfig', array(
            $this->sessionId,
            ($connectorName ? $connectorName : $this->connectorName),
        ));

        return $configuration;
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
     * @param integer $eventId
     * @param integer $offset
     * @param string $connectorName
     * @return array $events
     */
    public function synchronize($eventId, $offset = 0, $connectorName = null)
    {
        $result = $this->getRequest('synchronize', array(
            $this->sessionId,
            $connectorName ? $connectorName : $this->connectorName,
            $eventId,
            $offset,
        ));

        return $result;
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

        $beans = $this->getRequest('getBeans', array(
            $this->sessionId,
            $connectorName ? $connectorName : $this->connectorName,
            $objectIds,
        ));
        $beans = $this->normalizeArray($beans);
        foreach ($beans as $key => $bean) {
            $beans[$key]['properties']['data_shellpath'] = $this->normalizePath($beans[$key]['properties']['data_shellpath']);
        }

        return $beans;
    }


    /**
     * Fetches a specific derivate from MAM
     *
     * @apiparam connector_name - Name des Connectors
     * @apiparam id - Id des Datensatzes für das jeweilige Derivat
     * @apiparam derivate - web, print etc.
     *
     * @param string $objectId id of the object to get a derivate for
     * @return ???
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
        $uri = $this->dataUrl . '?' . http_build_query($query);

        return $this->doGetRequest($uri);
    }

    public function saveDerivate($filename, $objectId, $usage = null)
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
        $uri = $this->dataUrl . '?' . http_build_query($query);

        $temporaryFilename = tempnam(sys_get_temp_dir(), 'fal_mam-' . $objectId);

        ob_start();
        if (!file_exists(dirname($temporaryFilename))) {
            $oldUmask = umask(0);
            mkdir(dirname($temporaryFilename), $this - $this->folderCreateMask, true);
            umask($oldUmask);
        }

        $fp = fopen($temporaryFilename, 'w+');
        $ch = curl_init($uri);
        $temporaryHeaderbufferName = tempnam(sys_get_temp_dir(), 'header-buff' . $objectId);
        $headerBuff = fopen($temporaryHeaderbufferName, 'w+');

        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_WRITEHEADER, $headerBuff);
        curl_exec($ch);

        rewind($headerBuff);
        $headers = stream_get_contents($headerBuff);
        $derivateSuffix = '';
        fclose($headerBuff);
        unlink($temporaryHeaderbufferName);
        if (preg_match('/Content-Disposition: .*filename="([^\n"]+)"/', $headers, $matches)) {
            $derivateFilename = trim($matches[1]);
            $derivateSuffix = strtolower(pathinfo($derivateFilename, PATHINFO_EXTENSION));
            if (empty($derivateSuffix)) {
                throw new MamApiException('failed to determine derivate suffix for derivate ' . $derivateFilename);
            }
        } else {
            throw new MamApiException("missing Content-Disposition Header!");
        }

        if (preg_match('/Content-Length:[^0-9]*([0-9]+)/', $headers, $matches)) {
            $expectedFileSize = $matches[1];
        }

        if (!empty($curlError = curl_error($ch))) {
            throw new MamApiException('CURL Failed with the Error: ' . $curlError);
        }

        curl_close($ch);
        fclose($fp);
        $output = ob_get_clean();

        if ($expectedFileSize > 0 && $expectedFileSize != filesize($temporaryFilename)) {
            unlink($temporaryFilename);
            throw new MamApiException('The downloaded file does not match the expected filesize');
        }

        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) != strtolower($derivateSuffix)) {
            $filename = $filename . '.' . $derivateSuffix;
        }
        if (!file_exists(dirname($filename))) {
            $oldUmask = umask(0);
            mkdir(dirname($filename), $this->folderCreateMask, true);
            umask($oldUmask);
        }
        rename($temporaryFilename, $filename);
        chmod($filename, $this->fileCreateMask);

        return $derivateSuffix;
    }

    /**
     * build a remote request towards the MAM API
     *
     * @param string $method
     * @param array $parameters
     * @return array
     */
    public function getRequest($method, $parameter)
    {
        $uri = $this->restUrl . '&method=' . $method . '&parameter=' . json_encode($parameter);
        $response = $this->doGetRequest($uri);
        $result = json_decode($response, true);
        if (!isset($result['code']) || $result['code'] !== 0) {
            // var_dump($result, $uri, $this->sessionId);
            $message = isset($result['message']) ? $result['message'] : 'MamClient: could not communicate with mam api. please try again later';
            throw new MamApiException($message);
        }

        return $result['result'];
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
}
