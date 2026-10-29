<?php
declare(strict_types=1);
namespace Resurrection\Upgrade;

/** The upgrade refused before it changed anything. The message is fixed text the command prints as it is. */
final class UpgradeRefused extends \RuntimeException
{
}
