<?php
namespace Crossmedia\Fourallportal\Tests\Unit\Domain\Model;

/**
 * Test case.
 *
 * @author Marc Neuhaus <marc@mia3.com>
 */
class ModuleTest extends \TYPO3\CMS\Core\Tests\UnitTestCase
{
    /**
     * @var \Crossmedia\Fourallportal\Domain\Model\Module
     */
    protected $subject = null;

    protected function setUp()
    {
        parent::setUp();
        $this->subject = new \Crossmedia\Fourallportal\Domain\Model\Module();
    }

    protected function tearDown()
    {
        parent::tearDown();
    }

    /**
     * @test
     */
    public function getConnectorNameReturnsInitialValueForString()
    {
        self::assertSame(
            '',
            $this->subject->getConnectorName()
        );
    }

    /**
     * @test
     */
    public function setConnectorNameForStringSetsConnectorName()
    {
        $this->subject->setConnectorName('Conceived at T3CON10');

        self::assertAttributeEquals(
            'Conceived at T3CON10',
            'connectorName',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getMappingClassReturnsInitialValueForString()
    {
        self::assertSame(
            '',
            $this->subject->getMappingClass()
        );
    }

    /**
     * @test
     */
    public function setMappingClassForStringSetsMappingClass()
    {
        $this->subject->setMappingClass('Conceived at T3CON10');

        self::assertAttributeEquals(
            'Conceived at T3CON10',
            'mappingClass',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getConfigHashReturnsInitialValueForString()
    {
        self::assertSame(
            '',
            $this->subject->getConfigHash()
        );
    }

    /**
     * @test
     */
    public function setConfigHashForStringSetsConfigHash()
    {
        $this->subject->setConfigHash('Conceived at T3CON10');

        self::assertAttributeEquals(
            'Conceived at T3CON10',
            'configHash',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getLastEventIdReturnsInitialValueForInt()
    {
        self::assertSame(
            0,
            $this->subject->getLastEventId()
        );
    }

    /**
     * @test
     */
    public function setLastEventIdForIntSetsLastEventId()
    {
        $this->subject->setLastEventId(12);

        self::assertAttributeEquals(
            12,
            'lastEventId',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getShellPathReturnsInitialValueForString()
    {
        self::assertSame(
            '',
            $this->subject->getShellPath()
        );
    }

    /**
     * @test
     */
    public function setShellPathForStringSetsShellPath()
    {
        $this->subject->setShellPath('Conceived at T3CON10');

        self::assertAttributeEquals(
            'Conceived at T3CON10',
            'shellPath',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getStoragePidReturnsInitialValueForInt()
    {
        self::assertSame(
            0,
            $this->subject->getStoragePid()
        );
    }

    /**
     * @test
     */
    public function setStoragePidForIntSetsStoragePid()
    {
        $this->subject->setStoragePid(12);

        self::assertAttributeEquals(
            12,
            'storagePid',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getServerReturnsInitialValueForServer()
    {
        self::assertEquals(
            null,
            $this->subject->getServer()
        );
    }

    /**
     * @test
     */
    public function setServerForServerSetsServer()
    {
        $serverFixture = new \Crossmedia\Fourallportal\Domain\Model\Server();
        $this->subject->setServer($serverFixture);

        self::assertAttributeEquals(
            $serverFixture,
            'server',
            $this->subject
        );
    }
}
