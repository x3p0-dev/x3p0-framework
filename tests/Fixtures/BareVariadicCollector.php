<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

/**
 * A variadic with no `#[Tagged]` attribute. PHP permits calling this with zero
 * arguments, so the container must contribute an empty set rather than
 * autowiring a lone instance or failing on the un-buildable `Cache` interface.
 */
final class BareVariadicCollector
{
	/** @var array<Cache> */
	public readonly array $caches;

	public function __construct(Cache ...$caches)
	{
		$this->caches = $caches;
	}
}
