<?php
namespace Crossmedia\Fourallportal\Tests\Unit\Domain\Model;

/**
 * Test case.
 *
 * @author Marc Neuhaus <marc@mia3.com>
 */
class ServerTest extends \TYPO3\CMS\Core\Tests\UnitTestCase
{
    /**
     * @var \Crossmedia\Fourallportal\Domain\Model\Server
     */
    protected $subject = null;

    protected function setUp()
    {
        parent::setUp();
        $this->subject = new \Crossmedia\Fourallportal\Domain\Model\Server();
    }

    protected function tearDown()
    {
        parent::tearDown();
    }

    /**
     * @test
     */
    public function getDomainReturnsInitialValueForString()
    {
        self::assertSame(
            '',
            $this->subject->getDomain()
        );
    }

    /**
     * @test
     */
    public function setDomainForStringSetsDomain()
    {
        $this->subject->setDomain('Conceived at T3CON10');

        self::assertAttributeEquals(
            'Conceived at T3CON10',
            'domain',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getCustomerNameReturnsInitialValueForString()
    {
        self::assertSame(
            '',
            $this->subject->getCustomerName()
        );
    }

    /**
     * @test
     */
    public function setCustomerNameForStringSetsCustomerName()
    {
        $this->subject->setCustomerName('Conceived at T3CON10');

        self::assertAttributeEquals(
            'Conceived at T3CON10',
            'customerName',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getUsernameReturnsInitialValueForString()
    {
        self::assertSame(
            '',
            $this->subject->getUsername()
        );
    }

    /**
     * @test
     */
    public function setUsernameForStringSetsUsername()
    {
        $this->subject->setUsername('Conceived at T3CON10');

        self::assertAttributeEquals(
            'Conceived at T3CON10',
            'username',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getPasswordReturnsInitialValueForString()
    {
        self::assertSame(
            '',
            $this->subject->getPassword()
        );
    }

    /**
     * @test
     */
    public function setPasswordForStringSetsPassword()
    {
        $this->subject->setPassword('Conceived at T3CON10');

        self::assertAttributeEquals(
            'Conceived at T3CON10',
            'password',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getActiveReturnsInitialValueForBool()
    {
        self::assertSame(
            false,
            $this->subject->getActive()
        );
    }

    /**
     * @test
     */
    public function setActiveForBoolSetsActive()
    {
        $this->subject->setActive(true);

        self::assertAttributeEquals(
            true,
            'active',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getModulesReturnsInitialValueForModule()
    {
        $newObjectStorage = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
        self::assertEquals(
            $newObjectStorage,
            $this->subject->getModules()
        );
    }

    /**
     * @test
     */
    public function setModulesForObjectStorageContainingModuleSetsModules()
    {
        $module = new \Crossmedia\Fourallportal\Domain\Model\Module();
        $objectStorageHoldingExactlyOneModules = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
        $objectStorageHoldingExactlyOneModules->attach($module);
        $this->subject->setModules($objectStorageHoldingExactlyOneModules);

        self::assertAttributeEquals(
            $objectStorageHoldingExactlyOneModules,
            'modules',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function addModuleToObjectStorageHoldingModules()
    {
        $module = new \Crossmedia\Fourallportal\Domain\Model\Module();
        $modulesObjectStorageMock = $this->getMockBuilder(\TYPO3\CMS\Extbase\Persistence\ObjectStorage::class)
            ->setMethods(['attach'])
            ->disableOriginalConstructor()
            ->getMock();

        $modulesObjectStorageMock->expects(self::once())->method('attach')->with(self::equalTo($module));
        $this->inject($this->subject, 'modules', $modulesObjectStorageMock);

        $this->subject->addModule($module);
    }

    /**
     * @test
     */
    public function removeModuleFromObjectStorageHoldingModules()
    {
        $module = new \Crossmedia\Fourallportal\Domain\Model\Module();
        $modulesObjectStorageMock = $this->getMockBuilder(\TYPO3\CMS\Extbase\Persistence\ObjectStorage::class)
            ->setMethods(['detach'])
            ->disableOriginalConstructor()
            ->getMock();

        $modulesObjectStorageMock->expects(self::once())->method('detach')->with(self::equalTo($module));
        $this->inject($this->subject, 'modules', $modulesObjectStorageMock);

        $this->subject->removeModule($module);
    }
}
