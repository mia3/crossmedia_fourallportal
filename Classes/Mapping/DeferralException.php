<?php
namespace Crossmedia\Fourallportal\Mapping;

/**
 * Exception thrown when a mapping failed but the event
 * should be retried (up to a certain limit) because the
 * mapping problem is expected to be resolved.
 */
class DeferralException extends \RuntimeException
{
}
