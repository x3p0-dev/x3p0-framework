<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

use X3P0\Framework\Core\ServiceProvider;

final class OverridableProvider extends ServiceProvider
{
	protected const SINGLETONS_IF = [
		Cache::class => NullCache::class
	];

	protected const TRANSIENTS_IF = [
		TransientService::class
	];
}
