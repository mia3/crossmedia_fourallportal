<?php
namespace Crossmedia\Fourallportal\Service;


/**
 * This class represents a central access to the extension and mapping configuration
 */
class Configuration implements \TYPO3\CMS\Core\SingletonInterface
{

    /**
     * Base URL of the remote MAM API endpoint
     *
     * @var string
     */
    public $base_url;

    /**
     * Name of the connector for communication with the MAM API
     *
     * @var string
     */
    public $connector_name;

    /**
     * Username for the MAM API
     *
     * @var string
     */
    public $username;

    /**
     * Password for the MAM API
     *
     * @var string
     */
    public $password;

    /**
     * Customer name for the MAM API
     *
     * @var string
     */
    public $customer;

    /**
     * Absolute path to the local file storage directory
     *
     * @var string
     */
    public $base_path;

    /**
     * UID of the configured MAM FAL Storage
     *
     * @var string
     */
    public $storage_pid;

    /**
     * Remote shell path that needs to be removed from the received paths
     *
     * @var string
     */
    public $mam_shell_path;

    /**
     * Mapping of MAM Fields to FAL Metadata fields
     *
     * @var array
     */
    public $mapping = array();

    /**
     * Mail to notify about configuration changes
     *
     * @var string
     */
    public $admin_mail = array();

    /**
     * load basic configuration and mapping
     */
    public function __construct()
    {
        if (isset($GLOBALS['TYPO3_CONF_VARS']["EXT"]["extConf"]['fal_mam'])) {
            $configuration = unserialize($GLOBALS['TYPO3_CONF_VARS']["EXT"]["extConf"]['fal_mam']);
            $this->configuration = $configuration['fal_mam.'];
            foreach ($this->configuration as $key => $value) {
                $this->$key = $value;
            }
        }

        $mapping = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            '*',
            'tx_falmam_mapping',
            'connector_name = "' . $this->connector_name . '"',
            '',
            '',
            '',
            'mam_field'
        );
        foreach ($mapping as $key => $value) {
            $valueMap = array();
            foreach (explode("\n", $value['value_map']) as $row) {
                $parts = explode(':', $row);
                $valueMap[trim($parts[0])] = trim($parts[1]);
            }
            $value['value_map'] = $valueMap;
            $this->mapping[$key] = $value;
        }
    }
}
