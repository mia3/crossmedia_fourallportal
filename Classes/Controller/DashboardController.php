<?php
namespace Crossmedia\Fourallportal\Controller;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * The Dashboard Controller offers a basic overview of the synchronisation and
 * mapping of mam fields to fal fields.
 */
class DashboardController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * @var DatabaseConnection
     */
    protected $databaseConnection;

    /**
     * DashboardController constructor.
     * @param DatabaseConnection $databaseConnection
     */

    public function __construct(DatabaseConnection $databaseConnection = null)
    {
        if ($databaseConnection === null) {
            $databaseConnection = $GLOBALS['TYPO3_DB'];
        }
        $this->databaseConnection = $databaseConnection;
    }

    /**
     * @return void
     */
    public function indexAction()
    {

    }

    /**
     * @return void
     */
    public function configurationAction()
    {
        $servers = $this->databaseConnection->exec_SELECTgetRows('*', 'tx_fourallportal_server', 'deleted = 0');
        $this->view->assign('servers', $servers);
    }

}
