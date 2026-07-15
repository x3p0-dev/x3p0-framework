<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Container;

use PHPUnit\Framework\TestCase;
use X3P0\Framework\Container\Attributes\Tagged;
use X3P0\Framework\Container\ContainerException;
use X3P0\Framework\Container\NotFoundException;
use X3P0\Framework\Container\ServiceContainer;
use X3P0\Framework\Tests\Fixtures\BareVariadicCollector;
use X3P0\Framework\Tests\Fixtures\Cache;
use X3P0\Framework\Tests\Fixtures\CacheCollector;
use X3P0\Framework\Tests\Fixtures\FileCache;
use X3P0\Framework\Tests\Fixtures\NullCache;
use X3P0\Framework\Tests\Fixtures\LoggingCache;
use X3P0\Framework\Tests\Fixtures\NeedsStatus;
use X3P0\Framework\Tests\Fixtures\NoAutowireCache;
use X3P0\Framework\Tests\Fixtures\OptionalValueObject;
use X3P0\Framework\Tests\Fixtures\ReportBuilder;
use X3P0\Framework\Tests\Fixtures\RequiresValueObject;
use X3P0\Framework\Tests\Fixtures\UnionValueObject;
use X3P0\Framework\Tests\Fixtures\ValueObject;

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

	public function testTaggedServicesSpreadIntoAVariadicParameter(): void
	{
		$this->container->singleton(FileCache::class);
		$this->container->singleton(NullCache::class);
		$this->container->tag([FileCache::class, NullCache::class], 'caches');

		$collector = $this->container->make(CacheCollector::class);

		$this->assertCount(2, $collector->caches);
		$this->assertInstanceOf(FileCache::class, $collector->caches[0]);
		$this->assertInstanceOf(NullCache::class, $collector->caches[1]);
	}

	public function testBareVariadicResolvesToEmpty(): void
	{
		// A variadic with no `#[Tagged]` attribute is inherently optional:
		// with nothing to fill it, the container passes zero arguments
		// rather than autowiring a lone instance or failing on the
		// un-buildable `Cache` interface.
		$collector = $this->container->make(BareVariadicCollector::class);

		$this->assertSame([], $collector->caches);
	}

	public function testCallSpreadsTaggedServicesIntoAVariadicParameter(): void
	{
		$this->container->singleton(FileCache::class);
		$this->container->singleton(NullCache::class);
		$this->container->tag([FileCache::class, NullCache::class], 'caches');

		$collected = $this->container->call(
			fn (FileCache $first, #[Tagged('caches')] Cache ...$caches): array => $caches
		);

		$this->assertCount(2, $collected);
		$this->assertInstanceOf(FileCache::class, $collected[0]);
		$this->assertInstanceOf(NullCache::class, $collected[1]);
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

	public function testOptionalUnbuildableDependencyFallsBackToDefault(): void
	{
		// `ValueObject` exists but cannot be autowired. Because the
		// parameter is optional, the build failure must not escape: the
		// default value is used instead.
		$object = $this->container->make(OptionalValueObject::class);

		$this->assertNull($object->value);
	}

	public function testOptionalUnbuildableDependencyStillUsesABinding(): void
	{
		// A registered binding is preferred over building, so an optional
		// parameter is satisfied when one exists rather than falling back.
		$value = new ValueObject('source');
		$this->container->instance(ValueObject::class, $value);

		$object = $this->container->make(OptionalValueObject::class);

		$this->assertSame($value, $object->value);
	}

	public function testRequiredUnbuildableDependencyStillThrows(): void
	{
		// With no fallback of its own, a required un-autowirable dependency
		// must surface an error rather than being silently skipped.
		$this->expectException(ContainerException::class);

		$this->container->make(RequiresValueObject::class);
	}

	public function testUnionSkipsUnbuildableMemberForABuildableOne(): void
	{
		// Building the `ValueObject` member throws; the union must fall
		// through to the autowirable `FileCache` member instead of failing.
		$object = $this->container->make(UnionValueObject::class);

		$this->assertInstanceOf(FileCache::class, $object->dep);
	}

	public function testNoAutowireKeepsTheDefaultInsteadOfBuilding(): void
	{
		// The `Cache` dependency is autowirable, but `#[NoAutowire]`
		// suppresses that so the parameter keeps its `null` default.
		$object = $this->container->make(NoAutowireCache::class);

		$this->assertNull($object->cache);
	}

	public function testNoAutowireStillYieldsToAnExplicitArgument(): void
	{
		// Suppressing autowiring does not block an explicitly provided
		// argument, which always takes precedence.
		$cache = new FileCache();

		$object = $this->container->make(NoAutowireCache::class, [
			'cache' => $cache
		]);

		$this->assertSame($cache, $object->cache);
	}
}
