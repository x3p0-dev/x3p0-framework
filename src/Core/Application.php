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

use X3P0\Framework\Container\Container;
use X3P0\Framework\Contracts\Bootable;

/**
 * Base application class that wires a project together around a dependency
 * injection container and a set of service providers. A subclass lists its
 * providers in the `PROVIDERS` constant (and may register more at runtime);
 * the application registers each provider's bindings, then boots them.
 *
 * Registration and booting are separate phases so that every provider's
 * services are registered before any provider boots. As a `Bootable` itself,
 * the application boots all registered providers when its own `boot()` runs,
 * and `boot()` is safe to call across multiple WordPress load phases (such as
 * `plugins_loaded` and `after_setup_theme`) — each provider boots only once.
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
	private array $registeredProviders = [];

	/**
	 * Tracks which providers have already been booted, keyed by class name,
	 * so that `boot()` is safe to call across multiple load phases (e.g.,
	 * `plugins_loaded` and `after_setup_theme`) without re-running a
	 * provider's boot logic.
	 *
	 * @var array<string, true>
	 */
	private array $bootedProviders = [];

	/**
	 * Stores the container and registers the default bindings and service
	 * providers, leaving the application ready to boot.
	 */
	public function __construct(protected readonly Container $container)
	{
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
	 *
	 * @param  ServiceProvider|class-string<ServiceProvider> $provider
	 * @throws InvalidProviderException If a class-name provider is not a
	 *         `ServiceProvider` subclass.
	 */
	public function register(ServiceProvider|string $provider): void
	{
		if (is_string($provider) && ! is_subclass_of($provider, ServiceProvider::class)) {
			throw new InvalidProviderException(sprintf(
				'Provider must be a %s class',
				ServiceProvider::class
			));
		}

		// Determine the provider class up front so a duplicate can be
		// skipped without resolving it from the container.
		$class = is_string($provider) ? $provider : $provider::class;

		// Skip if a provider of this class is already registered, so the
		// same provider added via multiple paths only registers once.
		if (isset($this->registeredProviders[$class])) {
			return;
		}

		// Resolve a class-name provider into an instance.
		if (is_string($provider)) {
			$provider = $this->resolveProvider($provider);
		}

		$provider->register();
		$this->registeredProviders[$class] = $provider;
	}

	/**
	 * Resolve a service provider instance from its class name. Resolution
	 * goes through the container, so a provider can type-hint its own
	 * dependencies in the constructor and have them auto-wired. Override to
	 * customize how providers are constructed.
	 *
	 * @param class-string<ServiceProvider> $provider
	 */
	protected function resolveProvider(string $provider): ServiceProvider
	{
		return $this->container->make($provider);
	}

	/**
	 * Boots all registered service providers that have not yet been booted.
	 * Already-booted providers are skipped, making it safe to call this
	 * method multiple times across different WordPress load phases.
	 */
	public function boot(): void
	{
		foreach ($this->registeredProviders as $class => $provider) {
			$this->bootProvider($class, $provider);
		}
	}

	/**
	 * Boots a single provider unless it has already been booted, recording it
	 * so that later boot passes skip it.
	 */
	private function bootProvider(string $class, ServiceProvider $provider): void
	{
		if (isset($this->bootedProviders[$class])) {
			return;
		}

		$provider->boot();
		$this->bootedProviders[$class] = true;
	}
}
