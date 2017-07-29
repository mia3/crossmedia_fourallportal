<?php
namespace Crossmedia\Fourallportal\Tests\Unit\Controller;

/**
 * Test case.
 *
 * @author Marc Neuhaus <marc@mia3.com>
 */
class ServerControllerTest extends \TYPO3\CMS\Core\Tests\UnitTestCase
{
    /**
     * @var \Crossmedia\Fourallportal\Controller\ServerController
     */
    protected $subject = null;

    protected function setUp()
    {
        parent::setUp();
        $this->subject = $this->getMockBuilder(\Crossmedia\Fourallportal\Controller\ServerController::class)
            ->setMethods(['redirect', 'forward', 'addFlashMessage'])
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function tearDown()
    {
        parent::tearDown();
    }

    /**
     * @test
     */
    public function deleteActionRemovesTheGivenServerFromServerRepository()
    {
        $server = new \Crossmedia\Fourallportal\Domain\Model\Server();

        $serverRepository = $this->getMockBuilder(\Crossmedia\Fourallportal\Domain\Repository\ServerRepository::class)
            ->setMethods(['remove'])
            ->disableOriginalConstructor()
            ->getMock();

        $serverRepository->expects(self::once())->method('remove')->with($server);
        $this->inject($this->subject, 'serverRepository', $serverRepository);

        $this->subject->deleteAction($server);
    }
}
