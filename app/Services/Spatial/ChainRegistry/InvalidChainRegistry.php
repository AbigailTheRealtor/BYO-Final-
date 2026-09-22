<?php

namespace App\Services\Spatial\ChainRegistry;

/**
 * The chain registry configuration broke a load-time rule. Raised by {@see ChainRegistry} before
 * any matching can happen, so a malformed registry can never answer a question.
 */
final class InvalidChainRegistry extends \InvalidArgumentException
{
}
