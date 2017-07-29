<?php
namespace Crossmedia\Fourallportal\Service;

use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Logger
{

    /**
     * @var Raven_Client
     */
    protected $client;

    /**
     * @var integer
     */
    protected $logLevel = 1;

    /**
     * @var \TYPO3\CMS\Core\Log\Logger
     */
    protected $typo3Logger;

    public function __construct($autologin = true)
    {
        $this->typo3Logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        if (!isset($GLOBALS['TYPO3_CONF_VARS']["EXT"]["extConf"]['fal_mam'])) {
            return;
        }
        $configuration = unserialize($GLOBALS['TYPO3_CONF_VARS']["EXT"]["extConf"]['fal_mam']);
        if (!isset($configuration['fal_mam.']['logging.'])) {
            return;
        }
        $logConfiguration = $configuration['fal_mam.']['logging.'];
        $this->logLevel = intval($logConfiguration['log_level']);

        if (empty($logConfiguration['baseUrl'])) {
            return;
        }
        $this->client = new \Raven_Client($logConfiguration['baseUrl']);
        $this->client->user_context($configuration);
    }

    public function debug($message, $context = array())
    {
        if ($this->logLevel >= 3) {
            $this->typo3Logger->debug($message, is_array($context) ? $context : []);
        }
        if ($this->client === null) {
            return;
        }
        if ($this->logLevel < 3) {
            $this->client->extra_context($context);
            $this->client->breadcrumbs->record(array(
                'message' => $message,
                'data' => $context,
                'level' => 'debug',
            ));

            return;
        }

        $this->client->captureMessage($message, array(),
            array(
                'level' => 'debug',
                'extra' => $context,
            )
        );
    }

    public function info($message, $context = array())
    {
        if ($this->logLevel >= 2) {
            $this->typo3Logger->info($message, is_array($context) ? $context : []);
        }
        if ($this->client === null) {
            return;
        }
        if ($this->logLevel < 2) {
            $this->client->extra_context($context);
            $this->client->breadcrumbs->record(array(
                'message' => $message,
                'data' => $context,
                'level' => 'info',
            ));

            return;
        }
        $this->client->captureMessage($message, array(),
            array(
                'level' => 'info',
                'extra' => $context,
            )
        );
    }

    public function warning($message, $context = array())
    {
        if ($this->logLevel >= 1) {
            $this->typo3Logger->warning($message, is_array($context) ? $context : []);
        }
        if ($this->client === null) {
            return;
        }
        if ($this->logLevel < 1) {
            $this->client->extra_context($context);
            $this->client->breadcrumbs->record(array(
                'message' => $message,
                'data' => $context,
                'level' => 'warning',
            ));

            return;
        }
        $this->client->captureMessage($message, array(),
            array(
                'level' => 'warning',
                'extra' => $context,
            )
        );
    }

    public function error($message, $context = array())
    {
        if ($this->logLevel >= 1) {
            $this->typo3Logger->error($message, is_array($context) ? $context : []);
        }
        if ($this->client === null) {
            return;
        }
        if ($this->logLevel < 0) {
            $this->client->extra_context($context);
            $this->client->breadcrumbs->record(array(
                'message' => $message,
                'data' => $context,
                'level' => 'error',
            ));

            return;
        }
        $this->client->captureMessage($message, array(),
            array(
                'level' => 'error',
                'extra' => $context,
            )
        );
    }

    public function fatal($message, $context = array())
    {
        if ($this->logLevel >= 3) {
            $this->typo3Logger->critical($message, is_array($context) ? $context : []);
        }
        if ($this->client === null) {
            return;
        }
        if ($this->logLevel < 0) {
            return;
        }
        $this->client->captureMessage($message, array(),
            array(
                'level' => 'fatal',
                'extra' => $context,
            )
        );
    }

    public function captureException($exception)
    {
        if ($this->client === null) {
            return;
        }
        $this->client->captureException($exception);
    }
}
