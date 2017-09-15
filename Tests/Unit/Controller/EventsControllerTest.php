<?php
namespace Crossmedia\Fourallportal\Tests\Unit\Controller;

/**
 * Test case.
 *
 * @author Marc Neuhaus <marc@mia3.com>
 */
class EventsControllerTest extends \TYPO3\CMS\Core\Tests\UnitTestCase
{
    /**
     * @var \Crossmedia\Fourallportal\Controller\EventsController
     */
    protected $subject = null;

    protected function setUp()
    {
        parent::setUp();
        $this->subject = $this->getMockBuilder(\Crossmedia\Fourallportal\Controller\EventsController::class)
            ->setMethods(['redirect', 'forward', 'addFlashMessage'])
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function tearDown()
    {
        parent::tearDown();
    }

}
