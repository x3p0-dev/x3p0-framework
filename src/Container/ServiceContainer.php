<?php

/**
 * Container implementation.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Container;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Implementation of the dependency injection container.
 */
final class ServiceContainer implements Container
{
	/**
	 * Stores registered services.
	 */
	protected array $bindings = [];

	/**
	 * Stores registered instances and resolved singletons.
	 */
	protected array $instances = [];

	/**
	 * Maps tag names to the list of abstracts assigned to them.
	 *
	 * @var array<string, array<string>>
	 */
	protected array $tags = [];

	/**
	 * Tracks the abstracts currently being resolved so that circular
	 * dependencies are detected instead of recursing into a stack overflow.
	 *
	 * @var array<string>
	 */
	private array $buildStack = [];

	/**
	 * @inheritDoc
	 */
	public function transient(string $abstract, mixed $concrete = null): void
	{
		unset($this->instances[$abstract]);

		$this->bindings[$abstract] = [
			'concrete' => $concrete === null ? $abstract : $concrete,
			'shared'   => false
		];
	}

	/**
	 * @inheritDoc
	 */
	public function singleton(string $abstract, mixed $concrete = null): void
	{
		unset($this->instances[$abstract]);

		$this->bindings[$abstract] = [
			'concrete' => $concrete === null ? $abstract : $concrete,
			'shared'   => true
		];
	}

	/**
	 * @inheritDoc
	 */
	public function instance(string $abstract, mixed $instance): void
	{
		$this->instances[$abstract] = $instance;
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException|ReflectionException
	 */
	public function get(string $abstract): mixed
	{
		return $this->resolve($abstract);
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException|ReflectionException
	 */
	public function make(string $abstract, array $parameters = []): object
	{
		return $this->resolve($abstract, $parameters);
	}

	/**
	 * @inheritDoc
	 */
	public function has(string $abstract): bool
	{
		return isset($this->bindings[$abstract]) || array_key_exists($abstract, $this->instances);
	}

	/**
	 * @inheritDoc
	 */
	public function tag(string|array $abstracts, string $tag): void
	{
		foreach ((array) $abstracts as $abstract) {
			$this->tags[$tag][] = $abstract;
		}
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException|ReflectionException
	 */
	public function tagged(string $tag): array
	{
		return array_map(
			fn (string $abstract): mixed => $this->resolve($abstract),
			$this->tags[$tag] ?? []
		);
	}

	/**
	 * Resolve a service from the container with additional parameters.
	 * @throws ContainerException|ReflectionException
	 */
	private function resolve(string $abstract, array $parameters = []): mixed
	{
		// Return cached instance if it exists and no parameters are
		// provided. Note that singletons are cached as instances once
		// they are resolved.
		if (array_key_exists($abstract, $this->instances) && $parameters === []) {
			return $this->instances[$abstract];
		}

		// Detect circular dependencies before recursing any further.
		if (in_array($abstract, $this->buildStack, true)) {
			throw new ContainerException(sprintf(
				'Circular dependency detected while resolving "%s": %s.',
				$abstract,
				implode(' -> ', [...$this->buildStack, $abstract])
			));
		}

		// Track this abstract for the duration of the build so nested
		// resolutions can detect a cycle back to it.
		$this->buildStack[] = $abstract;

		try {
			// Resolve the service.
			$concrete = $this->getConcrete($abstract);

			// If we can't build an object, throw an exception.
			// Distinguish between an unknown identifier and a
			// registered-but-unbuildable binding so consumers can
			// handle each case differently.
			if (! $this->isBuildable($concrete)) {
				if ($this->has($abstract)) {
					throw new ContainerException(sprintf(
						'Service "%s" is registered but its bound concrete cannot be built.',
						$abstract
					));
				}

				throw new NotFoundException(sprintf(
					'Service "%s" is not registered and could not be resolved.',
					$abstract
				));
			}

			// Build the object.
			$service = $this->build($concrete, $parameters);

			// If this is a shared/singleton service, cache as an
			// instance if no parameters have been passed in.
			if ($this->isShared($abstract) && $parameters === []) {
				$this->instances[$abstract] = $service;
			}

			return $service;
		} finally {
			// Always pop, even on failure, so a thrown-and-caught
			// resolution does not corrupt the stack for later calls.
			array_pop($this->buildStack);
		}
	}

	/**
	 * Check if an abstract is bound as a singleton.
	 */
	private function isShared(string $abstract): bool
	{
		return array_key_exists($abstract, $this->instances)
			|| ($this->bindings[$abstract]['shared'] ?? false);
	}

	/**
	 * Determine if the given concrete is buildable.
	 */
	private function isBuildable(mixed $concrete): bool
	{
		return $concrete instanceof Closure
			|| (is_string($concrete) && class_exists($concrete));
	}

	/**
	 * Get the concrete implementation for an abstract. If no service
	 * exists, return the abstract itself for auto-resolution.
	 */
	private function getConcrete(string $abstract): mixed
	{
		return ! isset($this->bindings[$abstract])
			? $abstract
			: $this->bindings[$abstract]['concrete'];
	}

	/**
	 * Build an instance of the given concrete.
	 * @throws ContainerException|ReflectionException
	 */
	private function build(Closure|string $concrete, array $parameters = []): object
	{
		// If concrete is a closure, invoke it.
		if ($concrete instanceof Closure) {
			return $concrete($this, $parameters);
		}

		// Otherwise, resolve as a class.
		$reflector = new ReflectionClass($concrete);

		// Get the class constructor method.
		$constructor = $reflector->getConstructor();

		// If there's no constructor, just instantiate.
		if ($constructor === null) {
			return new $concrete();
		}

		// Resolve constructor dependencies and create new instance.
		return $reflector->newInstanceArgs($this->resolveDependencies(
			$constructor->getParameters(),
			$parameters
		));
	}

	/**
	 * Resolve constructor dependencies.
	 * @throws ContainerException|ReflectionException
	 */
	private function resolveDependencies(array $params, array $providedParams): array
	{
		$dependencies = [];

		foreach ($params as $param) {
			$name = $param->getName();

			// Use provided parameter if available
			if (array_key_exists($name, $providedParams)) {
				$dependencies[] = $providedParams[$name];
				continue;
			}

			$type = $param->getType();

			// Only a single, non-built-in named type (a class or
			// interface) can be autowired. Try to resolve it from
			// the container or build it directly. Anything
			// unresolvable falls through to the fallback.
			if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
				$className = $type->getName();

				if ($this->has($className)) {
					$dependencies[] = $this->resolve($className);
					continue;
				}

				if (class_exists($className)) {
					$dependencies[] = $this->make($className);
					continue;
				}
			}

			// Untyped, built-in, union, or intersection types, as
			// well as unresolvable class types, cannot be autowired.
			// Fall back to a default value, `null` when the
			// parameter is nullable, or fail.
			$dependencies[] = $this->resolveFallback($param);
		}

		return $dependencies;
	}

	/**
	 * Resolve a parameter that cannot be autowired by falling back to its
	 * default value or `null` when the signature permits it.
	 * @throws ContainerException
	 */
	private function resolveFallback(ReflectionParameter $param): mixed
	{
		if ($param->isDefaultValueAvailable()) {
			return $param->getDefaultValue();
		}

		$type = $param->getType();

		if ($type?->allowsNull()) {
			return null;
		}

		throw new ContainerException(sprintf(
			'Unresolvable dependency: parameter "$%s"%s in %s could not be resolved.',
			$param->getName(),
			$type ? sprintf(' of type %s', $type) : '',
			$param->getDeclaringClass()?->getName() ?? 'an unknown class'
		));
	}
}
