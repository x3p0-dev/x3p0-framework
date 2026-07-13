<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

use X3P0\Framework\Container\Attributes\Tagged;

final class CacheCollector
{
	/** @var array<Cache> */
	public readonly array $caches;

	public function __construct(
		#[Tagged('caches')] Cache ...$caches
	) {
		$this->caches = $caches;
	}
}
