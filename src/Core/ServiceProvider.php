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
	 * entry must resolve to a `Bootable` instance. Services boot in the order
	 * listed, so a service that depends on another having booted should be
	 * declared after it.
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
	 * A map of singletons to register as overridable defaults, following
	 * the same key conventions as `SINGLETONS`. Each is registered only if
	 * the abstract is not already bound, so an extension may replace it by
	 * binding its own concrete (via `singleton()`) regardless of load order.
	 *
	 * @var  array<int|string, string> Default singleton abstracts/concretes.
	 * @todo Type hint with PHP 8.3+ requirement.
	 */
	protected const SINGLETONS_IF = [];

	/**
	 * A map of transients to register (a new instance on each resolution),
	 * following the same key conventions as `SINGLETONS`. Bindings that
	 * require a closure factory should be registered in an overridden
	 * `register()` method.
	 *
	 * @var  array<int|string, string> Transient abstracts/concretes.
	 * @todo Type hint with PHP 8.3+ requirement.
	 */
	protected const TRANSIENTS = [];

	/**
	 * A map of transients to register as overridable defaults, following
	 * the same key conventions as `SINGLETONS`. Each is registered only if
	 * the abstract is not already bound, so an extension may replace it by
	 * binding its own concrete (via `transient()`) regardless of load order.
	 *
	 * @var  array<int|string, string> Default transient abstracts/concretes.
	 * @todo Type hint with PHP 8.3+ requirement.
	 */
	protected const TRANSIENTS_IF = [];

	/**
	 * A map of alias names to the abstracts they resolve to. Resolving an
	 * alias resolves its abstract instead, returning the same instance and
	 * lifetime, mirroring the container's `alias()` method.
	 *
	 * @var  array<string, string> Alias names mapped to abstracts.
	 * @todo Type hint with PHP 8.3+ requirement.
	 */
	protected const ALIASES = [];

	/**
	 * A map of tag names to the list of abstracts assigned to each tag. The
	 * tagged abstracts are resolvable together via the container's `tagged()`
	 * method.
	 *
	 * @var  array<string, array<string>> Tag names mapped to abstracts.
	 * @todo Type hint with PHP 8.3+ requirement.
	 */
	protected const TAGS = [];

	/**
	 * Accepts a container implementation for registering services.
	 */
	public function __construct(protected readonly Container $container)
	{}

	/**
	 * Registers the bindings listed in the `SINGLETONS`, `SINGLETONS_IF`,
	 * `TRANSIENTS`, and `TRANSIENTS_IF` constants, assigns each tag listed
	 * in the `TAGS` constant, and registers each alias in the `ALIASES`
	 * constant. Override and call `parent::register()` to add custom bindings.
	 */
	public function register(): void
	{
		$this->registerBindings(static::SINGLETONS,    shared: true,  overridable: false);
		$this->registerBindings(static::SINGLETONS_IF, shared: true,  overridable: true);
		$this->registerBindings(static::TRANSIENTS,    shared: false, overridable: false);
		$this->registerBindings(static::TRANSIENTS_IF, shared: false, overridable: true);

		foreach (static::ALIASES as $alias => $abstract) {
			$this->container->alias($alias, $abstract);
		}

		foreach (static::TAGS as $tag => $abstracts) {
			$this->container->tag($abstracts, $tag);
		}
	}

	/**
	 * Registers a map of bindings of the given lifetime. Numeric-keyed
	 * entries are self-bound (the abstract is its own concrete); string-keyed
	 * entries bind an abstract to a concrete class name. When `$shared`,
	 * the bindings are singletons; otherwise they are transients. When
	 * `$overridable`, each is registered only if the abstract is not
	 * already bound.
	 *
	 * @param array<int|string, string> $bindings
	 */
	private function registerBindings(array $bindings, bool $shared, bool $overridable): void
	{
		foreach ($bindings as $abstract => $concrete) {
			$arguments = is_int($abstract) ? [$concrete] : [$abstract, $concrete];

			if ($shared) {
				$overridable
					? $this->container->singletonIf(...$arguments)
					: $this->container->singleton(...$arguments);
			} else {
				$overridable
					? $this->container->transientIf(...$arguments)
					: $this->container->transient(...$arguments);
			}
		}
	}

	/**
	 * Resolves and boots each abstract listed in the `BOOTABLE` constant.
	 * Override and call `parent::boot()` to add custom boot logic.
	 *
	 * @throws UnbootableServiceException If a `BOOTABLE` entry does not
	 *         implement `Bootable`.
	 */
	public function boot(): void
	{
		foreach (static::BOOTABLE as $abstract) {
			$service = $this->container->get($abstract);

			if (! $service instanceof Bootable) {
				throw new UnbootableServiceException(sprintf(
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
