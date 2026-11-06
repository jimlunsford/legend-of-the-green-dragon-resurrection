<?php
declare(strict_types=1);
namespace Resurrection\Upgrade;
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }

/**
 * The upgrade stopped at its first failure, partway. Everything before the failed change is applied and recorded;
 * the failed change is not recorded, and nothing after it ran. The message is fixed text the command prints as it is.
 */
final class UpgradeStopped extends \RuntimeException
{
}
