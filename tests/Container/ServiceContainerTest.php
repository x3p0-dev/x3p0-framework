<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Container;

use PHPUnit\Framework\TestCase;
use X3P0\Framework\Container\ContainerException;
use X3P0\Framework\Container\NotFoundException;
use X3P0\Framework\Container\ServiceContainer;
use X3P0\Framework\Tests\Fixtures\Cache;
use X3P0\Framework\Tests\Fixtures\FileCache;
use X3P0\Framework\Tests\Fixtures\LoggingCache;
use X3P0\Framework\Tests\Fixtures\NeedsStatus;
use X3P0\Framework\Tests\Fixtures\ReportBuilder;

final class ServiceContainerTest extends TestCase
{
	private ServiceContainer $container;

	protected function setUp(): void
	{
		$this->container = new ServiceContainer();
	}

	public function testSingletonReturnsTheSameInstance(): void
	{
		$this->container->singleton(Cache::class, FileCache::class);

		$this->assertSame(
			$this->container->get(Cache::class),
			$this->container->get(Cache::class)
		);
	}

	public function testTransientReturnsAFreshInstance(): void
	{
		$this->container->transient(Cache::class, FileCache::class);

		$this->assertNotSame(
			$this->container->get(Cache::class),
			$this->container->get(Cache::class)
		);
	}

	public function testInstanceIsReturnedAsIs(): void
	{
		$cache = new FileCache();
		$this->container->instance(Cache::class, $cache);

		$this->assertSame($cache, $this->container->get(Cache::class));
	}

	public function testRebindingClearsTheCachedInstance(): void
	{
		$this->container->singleton(Cache::class, FileCache::class);
		$first = $this->container->get(Cache::class);

		$this->container->singleton(Cache::class, FileCache::class);

		$this->assertNotSame($first, $this->container->get(Cache::class));
	}

	public function testAutowiresConstructorDependencies(): void
	{
		$this->container->singleton(Cache::class, FileCache::class);

		$report = $this->container->make(ReportBuilder::class);

		$this->assertInstanceOf(FileCache::class, $report->cache);
	}

	public function testTaggedServicesResolveTogether(): void
	{
		$this->container->singleton(FileCache::class);
		$this->container->tag(FileCache::class, 'caches');

		$tagged = $this->container->tagged('caches');

		$this->assertCount(1, $tagged);
		$this->assertInstanceOf(FileCache::class, $tagged[0]);
	}

	public function testDecorateWrapsTheResolvedInstance(): void
	{
		$this->container->singleton(Cache::class, FileCache::class);
		$this->container->decorate(
			Cache::class,
			fn (Cache $cache): Cache => new LoggingCache($cache)
		);

		$this->assertInstanceOf(LoggingCache::class, $this->container->get(Cache::class));
	}

	public function testUnknownServiceThrowsNotFound(): void
	{
		$this->expectException(NotFoundException::class);

		$this->container->get('does-not-exist');
	}

	public function testEnumCannotBeAutowired(): void
	{
		$this->expectException(ContainerException::class);

		$this->container->make(NeedsStatus::class);
	}
}
