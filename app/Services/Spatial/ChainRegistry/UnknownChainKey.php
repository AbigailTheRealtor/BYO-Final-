<?php

namespace App\Services\Spatial\ChainRegistry;

/**
 * A caller asked for a chain the registry does not define. Raised rather than answered with an
 * empty result: "no such chain" must never be readable as "no such store nearby".
 */
final class UnknownChainKey extends \OutOfBoundsException
{
}
