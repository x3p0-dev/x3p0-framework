<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Core;

use PHPUnit\Framework\TestCase;
use X3P0\Framework\Container\ServiceContainer;
use X3P0\Framework\Core\InvalidProviderException;
use X3P0\Framework\Tests\Fixtures\NotAProvider;
use X3P0\Framework\Tests\Fixtures\ProviderA;
use X3P0\Framework\Tests\Fixtures\ProviderB;
use X3P0\Framework\Tests\Fixtures\ProviderC;
use X3P0\Framework\Tests\Fixtures\Recorder;
use X3P0\Framework\Tests\Fixtures\TestApplication;

final class ApplicationTest extends TestCase
{
	private ServiceContainer $container;
	private Recorder $recorder;
	private TestApplication $app;

	protected function setUp(): void
	{
		$this->container = new ServiceContainer();
		$this->recorder = new Recorder();

		$this->container->instance(Recorder::class, $this->recorder);

		$this->app = new TestApplication($this->container);
	}

	public function testRegistersAllThenBootsInOrder(): void
	{
		$this->app->register(ProviderA::class, ProviderB::class);
		$this->app->boot();

		$this->assertSame(
			['ProviderA:register', 'ProviderB:register', 'ProviderA:boot', 'ProviderB:boot'],
			$this->recorder->events
		);
	}

	public function testBeginOpensABatchedPassThatPreservesOrdering(): void
	{
		$this->app->register(ProviderA::class);
		$this->app->boot();

		$this->app->begin();
		$this->app->register(ProviderB::class);
		$this->app->register(ProviderC::class);
		$this->app->boot();

		$this->assertSame(
			[
				'ProviderA:register',
				'ProviderA:boot',
				'ProviderB:register',
				'ProviderC:register',
				'ProviderB:boot',
				'ProviderC:boot'
			],
			$this->recorder->events
		);
	}

	public function testProviderRegisteredAfterBootBootsImmediately(): void
	{
		$this->app->boot();
		$this->app->register(ProviderA::class);

		$this->assertSame(
			['ProviderA:register', 'ProviderA:boot'],
			$this->recorder->events
		);
	}

	public function testDuplicateProviderRegistersOnce(): void
	{
		$this->app->register(ProviderA::class);
		$this->app->register(ProviderA::class);
		$this->app->boot();

		$this->assertSame(
			['ProviderA:register', 'ProviderA:boot'],
			$this->recorder->events
		);
	}

	public function testRegisteringANonProviderThrows(): void
	{
		$this->expectException(InvalidProviderException::class);

		$this->app->register(NotAProvider::class);
	}
}
