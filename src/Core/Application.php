<?php

/**
 * Abstract application class.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Core;

use InvalidArgumentException;
use X3P0\Framework\Container\Container;
use X3P0\Framework\Contracts\Bootable;

/**
 * Base class that does the heavy lifting of bootstrapping an application while
 * letting subclasses handle the registration aspects specific to them.
 */
abstract class Application implements Bootable
{
	/**
	 * An array of service provider classnames to automatically register if
	 * defined in a subclass.
	 *
	 * @var  array<string> Service provider classnames.
	 * @todo Type hint with PHP 8.3+ requirement.
	 */
	protected const PROVIDERS = [];

	/**
	 * Stores an array of the registered service providers.
	 */
	private array $serviceProviders = [];

	/**
	 * Tracks which providers have already been booted so that `boot()` is
	 * safe to call across multiple load phases (e.g., `plugins_loaded` and
	 * `after_setup_theme`) without re-running a provider's boot logic.
	 */
	private array $bootedProviders = [];

	/**
	 * Sets up the initial object state.
	 */
	public function __construct(protected readonly Container $container)
	{
		// Register default bindings and service providers.
		$this->registerDefaultBindings();
		$this->registerDefaultProviders();
	}

	/**
	 * Registers default container bindings.
	 */
	protected function registerDefaultBindings(): void
	{
		$this->container->instance(Container::class, $this->container);
	}

	/**
	 * Registers the default service providers.
	 */
	protected function registerDefaultProviders(): void
	{
		foreach (static::PROVIDERS as $provider) {
			$this->register($provider);
		}
	}

	/**
	 * Get the container instance.
	 */
	public function container(): Container
	{
		return $this->container;
	}

	/**
	 * Register a service provider with the application.
	 */
	public function register(ServiceProvider|string $provider): void
	{
		if (is_string($provider)) {
			if (! is_subclass_of($provider, ServiceProvider::class)) {
				throw new InvalidArgumentException(sprintf(
					'Provider must be a %s class',
					ServiceProvider::class
				));
			}

			$provider = new $provider($this->container);
		}

		$provider->register();
		$this->serviceProviders[] = $provider;
	}

	/**
	 * Boots all registered service providers that have not yet been booted.
	 * Already-booted providers are skipped, making it safe to call this
	 * method multiple times across different WordPress load phases.
	 */
	public function boot(): void
	{
		foreach ($this->serviceProviders as $provider) {
			if (in_array($provider, $this->bootedProviders, true)) {
				continue;
			}

			$provider->boot();
			$this->bootedProviders[] = $provider;
		}
	}
}
