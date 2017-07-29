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

    public function getRestUrl()
    {
        // http://[DOMAIN]/[CUSTOMER_NAME]/rest/PAPRemoteService
        return sprintf(
            '%s/%s/rest/',
            rtrim($this->getDomain(), '/'),
            ltrim($this->getCustomerName(), '/')
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
}
