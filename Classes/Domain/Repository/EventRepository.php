<?php
namespace Crossmedia\Fourallportal\Domain\Repository;

use Crossmedia\Fourallportal\Domain\Model\Module;

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
 * The repository for Events
 */
class EventRepository extends \TYPO3\CMS\Extbase\Persistence\Repository
{
    public function findByStatus(string $status, int $limit = 0, bool $includeProcessing = true)
    {
        $query = $this->createQuery();
        $constraints = [
            $query->equals('status', $status),
            $query->equals('module.server.active', true),
        ];
        if (!$includeProcessing) {
            $constraints[] = $query->logicalNot($query->equals('processing', true));
        }
        $constraint = $query->logicalAnd(...$constraints);

        $query->matching($constraint);
        $query->setOrderings(
            [
                'crdate' => 'ASC',
            ]
        );
        if ($limit) {
            $query->setLimit($limit);
        }
        return $query->execute();
    }

    public function findDeferred(int $limit = 0)
    {
        $query = $this->createQuery();
        $constraint = $query->logicalAnd(
            $query->equals('status', 'deferred'),
            $query->lessThan('nextRetry', time()),
            $query->logicalNot($query->equals('processing', true))
        );
        $query->matching($constraint);
        $query->setOrderings(
            [
                'crdate' => 'ASC',
            ]
        );
        if ($limit) {
            $query->setLimit($limit);
        }
        return $query->execute();
    }

    public function findOneByModuleAndEventId(Module $module, int $eventId)
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('module', $module->getUid()),
                $query->equals('eventId', $eventId)
            )
        );
        return $query->execute()->getFirst();
    }
}
