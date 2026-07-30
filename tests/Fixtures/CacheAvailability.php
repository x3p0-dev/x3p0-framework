<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

final class CacheAvailability
{
	public function __construct(private Cache $cache)
	{
	}

	public function cacheIsAvailable(): bool
	{
		return $this->cache instanceof Cache;
	}
}
