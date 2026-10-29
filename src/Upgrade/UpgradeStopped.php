<?php
declare(strict_types=1);
namespace Resurrection\Upgrade;

/**
 * The upgrade stopped at its first failure, partway. Everything before the failed change is applied and recorded;
 * the failed change is not recorded, and nothing after it ran. The message is fixed text the command prints as it is.
 */
final class UpgradeStopped extends \RuntimeException
{
}
