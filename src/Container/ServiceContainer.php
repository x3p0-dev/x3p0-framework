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
use Error;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use X3P0\Framework\Container\Attributes\ContextualAttribute;
use X3P0\Framework\Container\Attributes\Singleton;

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
	 * Maps an alias to the abstract it points at. Aliases are followed
	 * transitively when an identifier is resolved.
	 *
	 * @var array<string, string>
	 */
	private array $aliases = [];

	/**
	 * Tracks the abstracts currently being resolved so that circular
	 * dependencies are detected instead of recursing into a stack overflow.
	 *
	 * @var array<string>
	 */
	private array $buildStack = [];

	/**
	 * Memoizes whether a concrete class declares the `#[Singleton]` attribute
	 * so the class is only reflected for it once.
	 *
	 * @var array<string, bool>
	 */
	private array $singletonAttributeCache = [];

	/**
	 * Maps an abstract to the list of callbacks to run after it is built.
	 *
	 * @var array<string, array<Closure>>
	 */
	private array $resolvingCallbacks = [];

	/**
	 * Maps an abstract to the list of decorators applied after it is built.
	 *
	 * @var array<string, array<Closure>>
	 */
	private array $extenders = [];

	/**
	 * @inheritDoc
	 */
	public function transient(string $abstract, mixed $concrete = null): void
	{
		unset($this->instances[$abstract], $this->aliases[$abstract]);

		$this->bindings[$abstract] = [
			'concrete' => $concrete === null ? $abstract : $concrete,
			'shared'   => false
		];
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	public function transientIf(string $abstract, mixed $concrete = null): void
	{
		if (! $this->registered($abstract)) {
			$this->transient($abstract, $concrete);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function singleton(string $abstract, mixed $concrete = null): void
	{
		unset($this->instances[$abstract], $this->aliases[$abstract]);

		$this->bindings[$abstract] = [
			'concrete' => $concrete === null ? $abstract : $concrete,
			'shared'   => true
		];
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	public function singletonIf(string $abstract, mixed $concrete = null): void
	{
		if (! $this->registered($abstract)) {
			$this->singleton($abstract, $concrete);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function instance(string $abstract, mixed $instance): void
	{
		unset($this->aliases[$abstract]);

		$this->instances[$abstract] = $instance;
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	public function alias(string $alias, string $abstract): void
	{
		if ($alias === $abstract) {
			throw new ContainerException(sprintf(
				'Cannot alias "%s" to itself.',
				$alias
			));
		}

		// An identifier is either an alias or a binding, never both, so a
		// new alias drops any binding or cached instance under that name.
		unset($this->bindings[$alias], $this->instances[$alias]);

		$this->aliases[$alias] = $abstract;
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	public function get(string $abstract): mixed
	{
		return $this->resolve($abstract);
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	public function make(string $abstract, array $parameters = []): object
	{
		return $this->resolve($abstract, $parameters);
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException|ReflectionException
	 */
	public function call(callable|array $callback, array $parameters = []): mixed
	{
		[$reflector, $invokable] = $this->reflectCallback($callback);

		$args = $this->resolveDependencies(
			$reflector->getParameters(),
			$parameters
		);

		return $invokable(...$args);
	}

	/**
	 * @inheritDoc
	 */
	public function defer(string $abstract): Closure
	{
		return fn (array $parameters = []): object =>
			$this->make($abstract, $parameters);
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	public function resolving(string $abstract, Closure $callback): void
	{
		$this->resolvingCallbacks[$this->getAlias($abstract)][] = $callback;
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	public function extend(string $abstract, Closure $closure): void
	{
		$abstract = $this->getAlias($abstract);

		$this->extenders[$abstract][] = $closure;

		// If the instance is already cached (a resolved singleton, or a
		// value bound via instance()), decorate it now and replace the
		// stored copy so later resolutions return the wrapped object.
		if (array_key_exists($abstract, $this->instances)) {
			$this->instances[$abstract] = $closure($this->instances[$abstract], $this);
		}
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	public function has(string $abstract): bool
	{
		$abstract = $this->getAlias($abstract);

		// Mirror resolve(): a not-found error is avoided when the
		// abstract is registered or its concrete is buildable (an
		// existing class).
		return $this->registered($abstract) || $this->isBuildable($abstract);
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	public function registered(string $abstract): bool
	{
		$abstract = $this->getAlias($abstract);

		return isset($this->bindings[$abstract]) || array_key_exists($abstract, $this->instances);
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	public function resolved(string $abstract): bool
	{
		return array_key_exists($this->getAlias($abstract), $this->instances);
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	public function forgetInstance(string $abstract): void
	{
		unset($this->instances[$this->getAlias($abstract)]);
	}

	/**
	 * @inheritDoc
	 */
	public function tag(string|array $abstracts, string $tag): void
	{
		foreach ((array) $abstracts as $abstract) {
			if (! in_array($abstract, $this->tags[$tag] ?? [], true)) {
				$this->tags[$tag][] = $abstract;
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function untag(string|array $abstracts, string $tag): void
	{
		if (! isset($this->tags[$tag])) {
			return;
		}

		$this->tags[$tag] = array_values(array_diff(
			$this->tags[$tag],
			(array) $abstracts
		));
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
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
	 * @throws ContainerException
	 */
	private function resolve(string $abstract, array $parameters = []): mixed
	{
		// Follow any alias to its target up front so caching, circular
		// dependency tracking, and binding lookups all key off the
		// canonical identifier.
		$abstract = $this->getAlias($abstract);

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

			// Delegation: when a binding maps the abstract to a
			// *different* identifier (an interface bound to a concrete
			// class, say), resolve that identifier in its own right
			// rather than building it blindly here. This routes through
			// the concrete's own binding, lifetime, `#[Singleton]`, and
			// hooks, so a shared concrete reached via an interface is
			// the same instance you would get resolving it directly.
			//
			// The concrete's decorators and resolving callbacks run
			// inside this nested call; the abstract's own hooks are
			// then layered on top below. Reaching this branch implies
			// `$this->bindings[$abstract]` is set (only a binding can
			// map the abstract to a differing concrete), so the
			// `declaresSingleton()` check further down is skipped and
			// the concrete's singleton lifetime is honored by the
			// nested resolution instead.
			if (
				is_string($concrete)
				&& $concrete !== $abstract
				&& ($this->registered($concrete) || class_exists($concrete))
			) {
				$service = $this->resolve($concrete, $parameters);
			} else {
				// If we can't build an object, throw an exception.
				// Distinguish between an unknown identifier and a
				// registered-but-unbuildable binding so consumers can
				// handle each case differently.
				if (! $this->isBuildable($concrete)) {
					if ($this->registered($abstract)) {
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
			}

			// Apply any decorators registered for this abstract. Each
			// may wrap or replace the instance, so this runs before
			// caching to ensure the stored copy is the decorated one.
			// When the abstract is a transient view over a shared
			// concrete, this re-wraps the same shared instance on each
			// resolution, yielding a fresh decorator per call by design.
			$service = $this->applyExtenders($abstract, $service);

			// Decide whether to cache the instance. Explicit bindings
			// take precedence; for autowired classes (those with no
			// binding), the `#[Singleton]` attribute opts the class into
			// singleton lifetime. Parameterized builds are never cached.
			$shared = $this->isShared($abstract);

			if (! isset($this->bindings[$abstract])) {
				$shared = $shared || $this->declaresSingleton($concrete);
			}

			if ($shared && $parameters === []) {
				$this->instances[$abstract] = $service;
			}

			// Let registered callbacks observe the built instance.
			// This runs after caching so a singleton's callbacks see
			// the same shared object that later resolutions return.
			$this->notifyResolving($abstract, $service);

			return $service;
		} finally {
			// Always pop, even on failure, so a thrown-and-caught
			// resolution does not corrupt the stack for later calls.
			array_pop($this->buildStack);
		}
	}

	/**
	 * Run any callbacks registered for the given abstract against the freshly
	 * built instance.
	 */
	private function notifyResolving(string $abstract, object $instance): void
	{
		foreach ($this->resolvingCallbacks[$abstract] ?? [] as $callback) {
			$callback($instance, $this);
		}
	}

	/**
	 * Apply any decorators registered for the given abstract, returning the
	 * final (possibly wrapped) instance.
	 */
	private function applyExtenders(string $abstract, object $instance): object
	{
		foreach ($this->extenders[$abstract] ?? [] as $extender) {
			$instance = $extender($instance, $this);
		}

		return $instance;
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
	 * Determine whether a concrete class opts into singleton lifetime via the
	 * `#[Singleton]` attribute. Results are memoized per class name to avoid
	 * reflecting the same class more than once.
	 */
	private function declaresSingleton(mixed $concrete): bool
	{
		if (! is_string($concrete) || ! class_exists($concrete)) {
			return false;
		}

		return $this->singletonAttributeCache[$concrete] ??=
			(new ReflectionClass($concrete))->getAttributes(Singleton::class) !== [];
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
	 * Follow an alias chain to the canonical identifier it points at,
	 * returning the abstract unchanged when it is not aliased. A cycle in the
	 * chain is reported rather than looping indefinitely.
	 * @throws ContainerException
	 */
	private function getAlias(string $abstract): string
	{
		$seen = [];

		while (isset($this->aliases[$abstract])) {
			if (isset($seen[$abstract])) {
				throw new ContainerException(sprintf(
					'Circular alias detected while resolving "%s".',
					$abstract
				));
			}

			$seen[$abstract] = true;
			$abstract = $this->aliases[$abstract];
		}

		return $abstract;
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
	 * @throws ContainerException
	 */
	private function build(Closure|string $concrete, array $parameters = []): object
	{
		// If concrete is a closure, invoke it. Exceptions thrown by a
		// user-supplied factory propagate as-is.
		if ($concrete instanceof Closure) {
			return $concrete($this, $parameters);
		}

		// Otherwise, resolve as a class. Low-level reflection and
		// instantiation failures (a missing or non-instantiable class,
		// for example) are wrapped so the container only ever throws a
		// ContainerException, while nested ContainerExceptions from
		// dependency resolution propagate unchanged.
		try {
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
		} catch (ReflectionException | Error $e) {
			throw new ContainerException(
				sprintf('Failed to build "%s": %s', $concrete, $e->getMessage()),
				previous: $e
			);
		}
	}

	/**
	 * Reflect a callable into its parameter source and an invokable form.
	 * A `[class-string, 'method']` pair (or a non-static method written as
	 * `'Class::method'`) has its object resolved from the container first so
	 * the instance is autowired before the method is invoked. Methods are
	 * bound via closures so non-public members can be called.
	 *
	 * @param  callable|array{0: object|string, 1: string} $callback
	 * @return array{0: ReflectionFunctionAbstract, 1: callable}
	 * @throws ContainerException|ReflectionException
	 */
	private function reflectCallback(callable|array $callback): array
	{
		// `[$objectOrClass, 'method']`
		if (is_array($callback)) {
			[$target, $method] = $callback;
			$reflector = new ReflectionMethod($target, $method);

			// Static methods need no instance.
			if ($reflector->isStatic()) {
				return [$reflector, $reflector->getClosure()];
			}

			$object = is_object($target) ? $target : $this->resolve($target);

			return [$reflector, $reflector->getClosure($object)];
		}

		// `'Class::method'`
		if (is_string($callback) && str_contains($callback, '::')) {
			$reflector = new ReflectionMethod($callback);

			if ($reflector->isStatic()) {
				return [$reflector, $callback];
			}

			// A non-static method named in static form still needs an
			// instance, so resolve its declaring class.
			$object = $this->resolve($reflector->getDeclaringClass()->getName());

			return [$reflector, $reflector->getClosure($object)];
		}

		// Invokable object (anything but a closure).
		if (is_object($callback) && ! $callback instanceof Closure) {
			return [new ReflectionMethod($callback, '__invoke'), $callback];
		}

		// Closure or function name.
		$closure = $callback(...);

		return [new ReflectionFunction($closure), $closure];
	}

	/**
	 * Resolve constructor dependencies.
	 * @throws ContainerException
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

			// A contextual attribute on the parameter resolves its
			// own value and takes precedence over type-based autowiring.
			$contextual = $param->getAttributes(
				ContextualAttribute::class,
				ReflectionAttribute::IS_INSTANCEOF
			);

			if ($contextual !== []) {
				$dependencies[] = $contextual[0]->newInstance()->resolve($this);
				continue;
			}

			$type = $param->getType();

			// Only a single, non-built-in named type (a class or
			// interface) can be autowired. Try to resolve it from
			// the container or build it directly. Anything
			// unresolvable falls through to the fallback.
			if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
				$className = $type->getName();

				if ($this->registered($className)) {
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
