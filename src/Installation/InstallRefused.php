<?php

declare(strict_types=1);

namespace Resurrection\Installation;
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }

/** An install the command refuses before changing anything. Its message is fixed text, safe to show the operator. */
final class InstallRefused extends \RuntimeException
{
}
