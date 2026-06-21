<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Core;

use PHPUnit\Framework\TestCase;
use X3P0\Framework\Container\ServiceContainer;
use X3P0\Framework\Core\UnbootableServiceException;
use X3P0\Framework\Tests\Fixtures\BindingProvider;
use X3P0\Framework\Tests\Fixtures\BootableProvider;
use X3P0\Framework\Tests\Fixtures\Cache;
use X3P0\Framework\Tests\Fixtures\FileCache;
use X3P0\Framework\Tests\Fixtures\OverridableProvider;
use X3P0\Framework\Tests\Fixtures\Recorder;
use X3P0\Framework\Tests\Fixtures\SharedService;
use X3P0\Framework\Tests\Fixtures\TransientService;
use X3P0\Framework\Tests\Fixtures\UnbootableProvider;

final class ServiceProviderTest extends TestCase
{
	private ServiceContainer $container;

	protected function setUp(): void
	{
		$this->container = new ServiceContainer();
	}

	public function testSingletonsConstantRegistersSharedBindings(): void
	{
		(new BindingProvider($this->container))->register();

		$this->assertInstanceOf(FileCache::class, $this->container->get(Cache::class));
		$this->assertSame(
			$this->container->get(Cache::class),
			$this->container->get(Cache::class)
		);
	}

	public function testSingletonsConstantSelfBindsNumericEntries(): void
	{
		(new BindingProvider($this->container))->register();

		$this->assertSame(
			$this->container->get(SharedService::class),
			$this->container->get(SharedService::class)
		);
	}

	public function testTransientsConstantRegistersFreshBindings(): void
	{
		(new BindingProvider($this->container))->register();

		$this->assertNotSame(
			$this->container->get(TransientService::class),
			$this->container->get(TransientService::class)
		);
	}

	public function testAliasesConstantRegistersAliases(): void
	{
		(new BindingProvider($this->container))->register();

		$this->assertSame(
			$this->container->get(Cache::class),
			$this->container->get('cache.alias')
		);
	}

	public function testTagsConstantAssignsTags(): void
	{
		(new BindingProvider($this->container))->register();

		$this->assertCount(2, $this->container->tagged('group'));
	}

	public function testSingletonIfLeavesAnExistingBinding(): void
	{
		$this->container->singleton(Cache::class, FileCache::class);

		(new OverridableProvider($this->container))->register();

		$this->assertInstanceOf(FileCache::class, $this->container->get(Cache::class));
	}

	public function testTransientIfLeavesAnExistingBinding(): void
	{
		$this->container->singleton(TransientService::class);

		(new OverridableProvider($this->container))->register();

		$this->assertSame(
			$this->container->get(TransientService::class),
			$this->container->get(TransientService::class)
		);
	}

	public function testBootableConstantBootsInDeclarationOrder(): void
	{
		$recorder = new Recorder();
		$this->container->instance(Recorder::class, $recorder);

		(new BootableProvider($this->container))->boot();

		$this->assertSame(['first', 'second'], $recorder->events);
	}

	public function testBootableThrowsWhenServiceIsNotBootable(): void
	{
		$this->expectException(UnbootableServiceException::class);

		(new UnbootableProvider($this->container))->boot();
	}
}
