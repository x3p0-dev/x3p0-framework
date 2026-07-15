<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

/**
 * A second, distinct consumer of the `Cache` interface, used to verify a
 * contextual binding registered for another consumer does not leak to it.
 */
final class AlsoNeedsCache
{
	public function __construct(public readonly Cache $cache)
	{}
}
