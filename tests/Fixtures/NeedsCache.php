<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

/**
 * A consumer of the `Cache` interface, used to verify a type-based contextual
 * binding swaps the implementation for this consumer only.
 */
final class NeedsCache
{
	public function __construct(public readonly Cache $cache)
	{}
}
