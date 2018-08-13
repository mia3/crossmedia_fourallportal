<?php
namespace Crossmedia\Fourallportal\Domain\Repository;

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
    public function findByStatus($status)
    {
        $query = $this->createQuery();
        $query->matching($query->equals('status', $status));
        $query->setOrderings(
            [
                'module.sorting' => 'ASC',
                'event_id' => 'ASC',
            ]
        );
        return $query->execute();
    }
}
