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
use X3P0\Framework\Container\InstanceResolver;
use X3P0\Framework\Contracts\Bootable;

/**
 * Base application class that wires a project together around a dependency
 * injection container and a set of service providers. A subclass lists its
 * providers in the `PROVIDERS` constant (and may register more at runtime);
 * the application registers each provider's bindings, then boots them.
 *
 * A single pass just registers providers and calls `boot()`. To register
 * across multiple WordPress load phases (such as `plugins_loaded` and
 * `after_setup_theme`), call `begin()` to open each later pass — it clears the
 * booted state and returns the application. Then register that pass's
 * providers and call `boot()` to boot them as a batch. Booting marks the
 * application booted, so any provider registered afterward — outside a
 * begin/boot pass — boots immediately. Each provider boots only once.
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
	 * Whether a boot pass has begun. Once it has, a provider registered
	 * afterward is booted immediately on registration rather than waiting
	 * for a boot pass that may never come. `begin()` clears it to open a
	 * new registration pass for a later load phase.
	 */
	private bool $booted = false;

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
		$this->container->alias(InstanceResolver::class, Container::class);
	}

	/**
	 * Registers the default service providers.
	 */
	protected function registerDefaultProviders(): void
	{
		$this->register(...static::PROVIDERS);
	}

	/**
	 * Get the container instance.
	 */
	public function container(): Container
	{
		return $this->container;
	}

	/**
	 * Register one or more service providers with the application. A
	 * provider may be passed as an instance or as a class name; class names
	 * are resolved through the container, so providers can type-hint their
	 * own dependencies in the constructor and have them auto-wired.
	 *
	 * If the application has already booted, the providers in the call are
	 * all registered first and then booted together. So a late batch keeps
	 * the guarantee that every provider is registered before any of them
	 * boots — and none is left dormant.
	 *
	 * @param ServiceProvider|class-string ...$providers
	 */
	public function register(ServiceProvider|string ...$providers): void
	{
		$registered = [];

		foreach ($providers as $provider) {
			if ($instance = $this->registerProvider($provider)) {
				$registered[] = $instance;
			}
		}

		// Once booting has begun, providers registered afterward missed
		// the boot pass. Boot them only after the whole batch is
		// registered, so a provider can rely on the others registered
		// in the same call.
		if ($this->booted && $registered !== []) {
			foreach ($registered as $provider) {
				$this->bootProvider($provider);
			}
		}
	}

	/**
	 * Registers a single provider — validating it, skipping duplicates,
	 * resolving a class name into an instance, and running its `register()`.
	 * Returns the registered provider, or `null` when a provider of that class
	 * is already registered. Booting is left to the caller so a batch can
	 * finish registering before any provider boots.
	 *
	 * @param  ServiceProvider|class-string<ServiceProvider> $provider
	 * @throws InvalidProviderException If a class-name provider is not a
	 *         `ServiceProvider` subclass.
	 */
	private function registerProvider(ServiceProvider|string $provider): ?ServiceProvider
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
			return null;
		}

		// Resolve a class-name provider into an instance.
		if (is_string($provider)) {
			$provider = $this->resolveProvider($provider);
		}

		$provider->register();
		$this->registeredProviders[$class] = $provider;

		return $provider;
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
	 * Opens a fresh registration pass by clearing the booted flag, so
	 * providers registered next are deferred until the following `boot()`
	 * call instead of booting immediately. Returns the application so it
	 * can be handed straight to a registration hook.
	 *
	 * A single register-then-boot pass does not need this. It is required
	 * only when registering across multiple passes (such as one on
	 * `plugins_loaded` and another on `after_setup_theme`), so each later
	 * pass's providers boot together as a batch rather than auto-booting
	 * one at a time.
	 */
	public function begin(): static
	{
		$this->booted = false;
		return $this;
	}

	/**
	 * Boots all registered service providers that have not yet been booted
	 * and marks the application as booted, so any provider registered afterward
	 * boots immediately on registration. Already-booted providers are skipped,
	 * making it safe to call across multiple WordPress load phases.
	 */
	public function boot(): void
	{
		// Mark booting as begun before the loop so a provider registered
		// during this pass (for example, from another provider's boot())
		// is booted immediately by register() rather than missed by the
		// array snapshot.
		$this->booted = true;

		foreach ($this->registeredProviders as $provider) {
			$this->bootProvider($provider);
		}
	}

	/**
	 * Boots a single provider unless it has already been booted, recording
	 * it so that later boot passes skip it.
	 */
	private function bootProvider(ServiceProvider $provider): void
	{
		$class = $provider::class;

		if (isset($this->bootedProviders[$class])) {
			return;
		}

		$provider->boot();
		$this->bootedProviders[$class] = true;
	}
}
