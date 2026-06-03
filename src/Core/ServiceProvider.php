<?php

/**
 * Abstract service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Core;

use LogicException;
use X3P0\Framework\Container\Container;
use X3P0\Framework\Contracts\Bootable;

/**
 * Service providers allow you to connect services to the application container.
 * This base class can be extended with a `register()` method for registering
 * services and a `BOOTABLE` constant (or a custom `boot()` method) for booting
 * them.
 */
abstract class ServiceProvider implements Bootable
{
	/**
	 * An array of abstracts to resolve from the container and boot. Each
	 * entry must resolve to a `Bootable` instance.
	 *
	 * @var  array<string> Bootable service abstracts.
	 * @todo Type hint with PHP 8.3+ requirement.
	 */
	protected const BOOTABLE = [];

	/**
	 * A map of singletons to register. Numeric-keyed entries are self-bound
	 * (the abstract is its own concrete); string-keyed entries bind an
	 * abstract to a concrete class name. Bindings that require a closure
	 * factory should be registered in an overridden `register()` method.
	 *
	 * @var  array<int|string, string> Singleton abstracts/concretes.
	 * @todo Type hint with PHP 8.3+ requirement.
	 */
	protected const SINGLETONS = [];

	/**
	 * Accepts a container implementation for registering services.
	 */
	public function __construct(protected readonly Container $container)
	{}

	/**
	 * Registers each singleton listed in the `SINGLETONS` constant. Override
	 * and call `parent::register()` to add custom bindings.
	 */
	public function register(): void
	{
		foreach (static::SINGLETONS as $abstract => $concrete) {
			is_int($abstract)
				? $this->container->singleton($concrete)
				: $this->container->singleton($abstract, $concrete);
		}
	}

	/**
	 * Resolves and boots each abstract listed in the `BOOTABLE` constant.
	 * Override and call `parent::boot()` to add custom boot logic.
	 */
	public function boot(): void
	{
		foreach (static::BOOTABLE as $abstract) {
			$service = $this->container->get($abstract);

			if (! $service instanceof Bootable) {
				throw new LogicException(sprintf(
					'%s is listed in %s::BOOTABLE but does not implement %s.',
					$abstract,
					static::class,
					Bootable::class
				));
			}

			$service->boot();
		}
	}
}
