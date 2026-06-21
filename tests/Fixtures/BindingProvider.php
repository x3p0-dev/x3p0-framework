<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

use X3P0\Framework\Core\ServiceProvider;

final class BindingProvider extends ServiceProvider
{
	protected const SINGLETONS = [
		SharedService::class,
		Cache::class => FileCache::class
	];

	protected const TRANSIENTS = [
		TransientService::class
	];

	protected const ALIASES = [
		'cache.alias' => Cache::class
	];

	protected const TAGS = [
		'group' => [SharedService::class, TransientService::class]
	];
}
