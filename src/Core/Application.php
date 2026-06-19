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
	 * Stores the registered service providers, keyed by class name so that
	 * the same provider is never registered more than once.
	 *
	 * @var array<string, ServiceProvider>
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
	 * Register a service provider with the application. A provider may be
	 * passed as an instance or as a class name. Class names are resolved
	 * through the container, so providers can type-hint their own
	 * dependencies in the constructor and have them auto-wired.
	 */
	public function register(ServiceProvider|string $provider): void
	{
		if (is_string($provider) && ! is_subclass_of($provider, ServiceProvider::class)) {
			throw new InvalidArgumentException(sprintf(
				'Provider must be a %s class',
				ServiceProvider::class
			));
		}

		// Determine the provider class up front so a duplicate can be
		// skipped without resolving it from the container.
		$class = is_string($provider) ? $provider : $provider::class;

		// Skip if a provider of this class is already registered, so the
		// same provider added via multiple paths only registers once.
		if (isset($this->serviceProviders[$class])) {
			return;
		}

		// Resolve a class-name provider through the container, so providers
		// can type-hint their own dependencies and have them auto-wired.
		if (is_string($provider)) {
			$provider = $this->container->make($provider);
		}

		$provider->register();
		$this->serviceProviders[$class] = $provider;
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
