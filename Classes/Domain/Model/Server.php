<?php
namespace Crossmedia\Fourallportal\Domain\Model;

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

use Crossmedia\Fourallportal\Service\ApiClient;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Server
 */
class Server extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    /**
     * domain
     *
     * @var string
     */
    protected $domain = '';

    /**
     * customerName
     *
     * @var string
     */
    protected $customerName = '';

    /**
     * username
     *
     * @var string
     */
    protected $username = '';

    /**
     * password
     *
     * @var string
     */
    protected $password = '';

    /**
     * active
     *
     * @var bool
     */
    protected $active = false;

    /**
     * modules
     *
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\Crossmedia\Fourallportal\Domain\Model\Module>
     * @cascade remove
     */
    protected $modules = null;

    /**
     * modules
     *
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\Crossmedia\Fourallportal\Domain\Model\DimensionMapping>
     * @cascade remove
     */
    protected $dimensionMappings = null;

    /**
     * __construct
     */
    public function __construct()
    {
        //Do not remove the next line: It would break the functionality
        $this->initStorageObjects();
    }

    /**
     * Initializes all ObjectStorage properties
     * Do not modify this method!
     * It will be rewritten on each save in the extension builder
     * You may modify the constructor of this class instead
     *
     * @return void
     */
    protected function initStorageObjects()
    {
        $this->modules = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
        $this->dimensionMappings = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
    }

    /**
     * Returns the domain
     *
     * @return string $domain
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Sets the domain
     *
     * @param string $domain
     * @return void
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    /**
     * Returns the customerName
     *
     * @return string $customerName
     */
    public function getCustomerName()
    {
        return $this->customerName;
    }

    /**
     * Sets the customerName
     *
     * @param string $customerName
     * @return void
     */
    public function setCustomerName($customerName)
    {
        $this->customerName = $customerName;
    }

    /**
     * Returns the username
     *
     * @return string $username
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Sets the username
     *
     * @param string $username
     * @return void
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * Returns the password
     *
     * @return string $password
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Sets the password
     *
     * @param string $password
     * @return void
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * Adds a Module
     *
     * @param \Crossmedia\Fourallportal\Domain\Model\Module $module
     * @return void
     */
    public function addModule($module)
    {
        $this->modules->attach($module);
    }

    /**
     * Removes a Module
     *
     * @param \Crossmedia\Fourallportal\Domain\Model\Module $moduleToRemove The Module to be removed
     * @return void
     */
    public function removeModule($moduleToRemove)
    {
        $this->modules->detach($moduleToRemove);
    }

    /**
     * Returns the modules
     *
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\Crossmedia\Fourallportal\Domain\Model\Module> modules
     */
    public function getModules()
    {
        return $this->modules;
    }

    /**
     * Sets the modules
     *
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\Crossmedia\Fourallportal\Domain\Model\Module> $modules
     * @return void
     */
    public function setModules(\TYPO3\CMS\Extbase\Persistence\ObjectStorage $modules)
    {
        $this->modules = $modules;
    }

    public function getModule(string $moduleName): ?Module
    {
        foreach ($this->getModules() as $module) {
            if ($module->getModuleName() === $moduleName) {
                return $module;
            }
        }
        return null;
    }

    /**
     * @return string
     */
    public function getDataUrl()
    {
        // http://[DOMAIN]/[CUSTOMER_NAME]/dataservice
        return sprintf(
            '%s/service/object_image/get',
            rtrim($this->getDomain(), '/')#,
            #ltrim($this->getCustomerName(), '/')
        );
    }

    /**
     * @return string
     */
    public function getLoginUrl()
    {
        // http://[DOMAIN]/service/usermanagement/login
        return sprintf(
            '%s/service/usermanagement/login',
            rtrim($this->getDomain(), '/')
        );
    }

    /**
     * @return string
     */
    public function getRestUrl()
    {
        // http://[DOMAIN]/rest/PAPRemoteService
        return sprintf(
            '%s/rest/',
            rtrim($this->getDomain(), '/')
        );
    }

    /**
     * Returns the active
     *
     * @return bool $active
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Sets the active
     *
     * @param bool $active
     * @return void
     */
    public function setActive($active)
    {
        $this->active = $active;
    }

    /**
     * Returns the boolean state of active
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * @return ApiClient
     */
    public function getClient()
    {
        static $client = null;
        if ($client) {
            return $client;
        }
        $client = GeneralUtility::makeInstance(ObjectManager::class)->get(ApiClient::class, $this);
        $client->login();
        return $client;
    }

    /**
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage
     */
    public function getDimensionMappings()
    {
        return $this->dimensionMappings;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage $dimensionMappings
     */
    public function setDimensionMappings($dimensionMappings)
    {
        $this->dimensionMappings = $dimensionMappings;
    }

}
