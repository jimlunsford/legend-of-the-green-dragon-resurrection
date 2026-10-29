<?php

declare(strict_types=1);

namespace Resurrection\Installation;

/** An install the command refuses before changing anything. Its message is fixed text, safe to show the operator. */
final class InstallRefused extends \RuntimeException
{
}
