<?php
namespace Crossmedia\Fourallportal\Tests\Unit\Domain\Model;

/**
 * Test case.
 *
 * @author Marc Neuhaus <marc@mia3.com>
 */
class EventTest extends \TYPO3\CMS\Core\Tests\UnitTestCase
{
    /**
     * @var \Crossmedia\Fourallportal\Domain\Model\Event
     */
    protected $subject = null;

    protected function setUp()
    {
        parent::setUp();
        $this->subject = new \Crossmedia\Fourallportal\Domain\Model\Event();
    }

    protected function tearDown()
    {
        parent::tearDown();
    }

    /**
     * @test
     */
    public function getEventIdReturnsInitialValueForInt()
    {
        self::assertSame(
            0,
            $this->subject->getEventId()
        );
    }

    /**
     * @test
     */
    public function setEventIdForIntSetsEventId()
    {
        $this->subject->setEventId(12);

        self::assertAttributeEquals(
            12,
            'eventId',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getEventTypeReturnsInitialValueForString()
    {
        self::assertSame(
            '',
            $this->subject->getEventType()
        );
    }

    /**
     * @test
     */
    public function setEventTypeForStringSetsEventType()
    {
        $this->subject->setEventType('Conceived at T3CON10');

        self::assertAttributeEquals(
            'Conceived at T3CON10',
            'eventType',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getStatusReturnsInitialValueForString()
    {
        self::assertSame(
            '',
            $this->subject->getStatus()
        );
    }

    /**
     * @test
     */
    public function setStatusForStringSetsStatus()
    {
        $this->subject->setStatus('Conceived at T3CON10');

        self::assertAttributeEquals(
            'Conceived at T3CON10',
            'status',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getSkipUntilReturnsInitialValueForInt()
    {
        self::assertSame(
            0,
            $this->subject->getSkipUntil()
        );
    }

    /**
     * @test
     */
    public function setSkipUntilForIntSetsSkipUntil()
    {
        $this->subject->setSkipUntil(12);

        self::assertAttributeEquals(
            12,
            'skipUntil',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getObjectIdReturnsInitialValueForString()
    {
        self::assertSame(
            '',
            $this->subject->getObjectId()
        );
    }

    /**
     * @test
     */
    public function setObjectIdForStringSetsObjectId()
    {
        $this->subject->setObjectId('Conceived at T3CON10');

        self::assertAttributeEquals(
            'Conceived at T3CON10',
            'objectId',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getModuleReturnsInitialValueForModule()
    {
        self::assertEquals(
            null,
            $this->subject->getModule()
        );
    }

    /**
     * @test
     */
    public function setModuleForModuleSetsModule()
    {
        $moduleFixture = new \Crossmedia\Fourallportal\Domain\Model\Module();
        $this->subject->setModule($moduleFixture);

        self::assertAttributeEquals(
            $moduleFixture,
            'module',
            $this->subject
        );
    }
}
