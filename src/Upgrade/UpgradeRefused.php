<?php
declare(strict_types=1);
namespace Resurrection\Upgrade;
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }

/** The upgrade refused before it changed anything. The message is fixed text the command prints as it is. */
final class UpgradeRefused extends \RuntimeException
{
}
