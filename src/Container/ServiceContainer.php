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
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use X3P0\Framework\Container\Attributes\ContextualAttribute;
use X3P0\Framework\Container\Attributes\NoAutowire;
use X3P0\Framework\Container\Attributes\Singleton;

/**
 * Implementation of the dependency injection container.
 */
final class ServiceContainer implements Container
{
	/**
	 * Stores registered services.
	 *
	 * @var array<string, array{concrete: mixed, shared: bool}>
	 */
	protected array $bindings = [];

	/**
	 * Stores registered instances and resolved singletons.
	 *
	 * @var array<string, mixed>
	 */
	protected array $instances = [];

	/**
	 * Maps tag names to the list of abstracts assigned to them.
	 *
	 * @var array<string, array<string>>
	 */
	protected array $tags = [];

	/**
	 * Maps a tag name and abstract to the attributes it was tagged with.
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	protected array $tagAttributes = [];

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
	private array $decorators = [];

	/**
	 * Contextual bindings, keyed by the concrete class being built. Each
	 * consumer holds two buckets: `params` maps a constructor parameter
	 * name to a literal value (or a closure computing one), and `types`
	 * maps a parameter's type to a class-string the container resolves (or
	 * a closure). Keeping the two kinds separate is what lets resolution
	 * interpret each without parsing or guessing whether a value is a
	 * literal or a class.
	 *
	 * @var array<string, array{params?: array<string, mixed>, types?: array<string, Closure|string>}>
	 */
	private array $contextual = [];

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
			throw new ContainerException(esc_html(sprintf(
				'Cannot alias "%s" to itself.',
				$alias
			)));
		}

		// An identifier is either an alias or a binding, never both, so a
		// new alias drops any binding or cached instance under that name.
		unset($this->bindings[$alias], $this->instances[$alias]);

		$this->aliases[$alias] = $abstract;
	}

	/**
	 * @inheritDoc
	 */
	public function whenNeedsParam(string $consumer, string $param, mixed $value): void
	{
		$this->contextual[$consumer]['params'][$param] = $value;
	}

	/**
	 * @inheritDoc
	 */
	public function whenNeedsType(string $consumer, string $type, Closure|string $concrete): void
	{
		$this->contextual[$consumer]['types'][$type] = $concrete;
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
	 * @throws ContainerException
	 */
	public function makeFresh(string $abstract, array $parameters = []): object
	{
		return $this->resolve($abstract, $parameters, fresh: true);
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
	public function decorate(string $abstract, Closure $closure): void
	{
		$abstract = $this->getAlias($abstract);

		$this->decorators[$abstract][] = $closure;

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
	public function tag(string|array $abstracts, string $tag, array $attributes = []): void
	{
		foreach ((array) $abstracts as $abstract) {
			if (! in_array($abstract, $this->tags[$tag] ?? [], true)) {
				$this->tags[$tag][] = $abstract;
			}

			if ($attributes !== []) {
				$this->tagAttributes[$tag][$abstract] = $attributes;
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

		foreach ((array) $abstracts as $abstract) {
			unset($this->tagAttributes[$tag][$abstract]);
		}
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
	 * @inheritDoc
	 * @throws ContainerException
	 */
	public function taggedWith(string $tag, string $attribute): array
	{
		$map = [];

		foreach ($this->tags[$tag] ?? [] as $abstract) {
			$value = $this->tagAttributes[$tag][$abstract][$attribute] ?? null;

			if ($value !== null) {
				$map[$value] = $this->resolve($abstract);
			}
		}

		return $map;
	}

	/**
	 * @inheritDoc
	 */
	public function taggedAbstracts(string $tag): array
	{
		return $this->tags[$tag] ?? [];
	}

	/**
	 * @inheritDoc
	 */
	public function taggedAbstractsWith(string $tag, string $attribute): array
	{
		$map = [];

		foreach ($this->tags[$tag] ?? [] as $abstract) {
			$value = $this->tagAttributes[$tag][$abstract][$attribute] ?? null;

			if ($value !== null) {
				$map[$value] = $abstract;
			}
		}

		return $map;
	}

	/**
	 * @inheritDoc
	 */
	public function hasTag(string $tag): bool
	{
		return ! empty($this->tags[$tag]);
	}

	/**
	 * Resolve a service from the container, optionally with named constructor
	 * overrides. A parameterized resolution is never cached. When `$fresh`
	 * is `true`, any cached singleton is ignored and left untouched: a new
	 * instance is built and not stored. Freshness applies only to this
	 * abstract — it follows delegation to a bound concrete but is not passed
	 * on to the abstract's own dependencies, which resolve normally.
	 *
	 * @param  array<string, mixed> $parameters
	 * @throws ContainerException
	 */
	private function resolve(string $abstract, array $parameters = [], bool $fresh = false): mixed
	{
		// Follow any alias to its target up front so caching, circular
		// dependency tracking, and binding lookups all key off the
		// canonical identifier.
		$abstract = $this->getAlias($abstract);

		// Return cached instance if it exists and no parameters are
		// provided, unless a fresh instance was requested. Note that
		// singletons are cached as instances once they are resolved.
		if (! $fresh && array_key_exists($abstract, $this->instances) && $parameters === []) {
			return $this->instances[$abstract];
		}

		// Detect circular dependencies before recursing any further.
		if (in_array($abstract, $this->buildStack, true)) {
			throw new ContainerException(esc_html(sprintf(
				'Circular dependency detected while resolving "%s": %s.',
				$abstract,
				implode(' -> ', [...$this->buildStack, $abstract])
			)));
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
				// Delegation targets the same logical entity,
				// so a fresh request carries through; the concrete
				// is what actually gets built anew.
				$service = $this->resolve($concrete, $parameters, $fresh);
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
			$service = $this->applyDecorators($abstract, $service);

			// Decide whether to cache the instance. Explicit bindings
			// take precedence; for autowired classes (those with no
			// binding), the `#[Singleton]` attribute opts the class into
			// singleton lifetime. Parameterized builds are never cached.
			$shared = $this->isShared($abstract);

			if (! isset($this->bindings[$abstract])) {
				$shared = $shared || $this->declaresSingleton($concrete);
			}

			if ($shared && $parameters === [] && ! $fresh) {
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
	private function applyDecorators(string $abstract, object $instance): object
	{
		foreach ($this->decorators[$abstract] ?? [] as $decorator) {
			$instance = $decorator($instance, $this);
		}

		return $instance;
	}

	/**
	 * Whether the abstract should be treated as shared: either it already has
	 * a cached instance (a resolved singleton or an `instance()` value), or its
	 * binding opts into singleton lifetime.
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
				throw new ContainerException(esc_html(sprintf(
					'Circular alias detected while resolving "%s".',
					$abstract
				)));
			}

			$seen[$abstract] = true;
			$abstract = $this->aliases[$abstract];
		}

		return $abstract;
	}

	/**
	 * Get the concrete bound to an abstract, or the abstract itself (for
	 * auto-resolution) when it has no binding.
	 */
	private function getConcrete(string $abstract): mixed
	{
		return ! isset($this->bindings[$abstract])
			? $abstract
			: $this->bindings[$abstract]['concrete'];
	}

	/**
	 * Build an instance of the given concrete. A closure concrete is treated
	 * as a factory and invoked as `fn(InstanceResolver $resolver, array
	 * $parameters): object`; a class-name concrete is reflected and
	 * instantiated with its autowired dependencies.
	 *
	 * @param  array<string, mixed> $parameters Named constructor overrides.
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

			// Resolve constructor dependencies and create new
			// instance. The concrete being built is the "consumer"
			// for any contextual bindings its parameters may match.
			return $reflector->newInstanceArgs($this->resolveDependencies(
				$constructor->getParameters(),
				$parameters,
				$concrete
			));
		} catch (ReflectionException | Error $e) {
			throw new ContainerException(
				esc_html(sprintf(
					'Failed to build "%s": %s',
					$concrete,
					$e->getMessage()
				)),
				// The previous exception is a Throwable for chaining, not output.
				previous: $e // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
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
	 * Resolve constructor dependencies. `$consumer` is the concrete class
	 * being built, used to match contextual bindings; it is `null` for a
	 * free callable resolved through `call()`, which has no consumer.
	 *
	 * @param  ReflectionParameter[] $params
	 * @param  array<string, mixed>  $providedParams
	 * @return list<mixed>
	 * @throws ContainerException
	 */
	private function resolveDependencies(array $params, array $providedParams, ?string $consumer = null): array
	{
		$dependencies = [];

		foreach ($params as $param) {
			$value = $this->resolveParameter($param, $providedParams, $consumer);

			// A variadic slot is filled by spreading a resolved
			// collection across it, so each element arrives as its
			// own positional argument and PHP enforces the
			// parameter's type hint on every item. A non-array
			// value falls through and is passed as-is.
			if ($param->isVariadic() && is_array($value)) {
				array_push($dependencies, ...array_values($value));
			} else {
				$dependencies[] = $value;
			}
		}

		return $dependencies;
	}

	/**
	 * Resolve a single parameter, in order of precedence: an explicitly
	 * provided argument, a parameter attribute (`#[NoAutowire]` or a
	 * contextual attribute), a contextual binding registered for the
	 * consumer, autowiring from the parameter's type, and finally a
	 * fallback to a default value, `null`, or failure.
	 *
	 * @param  array<string, mixed> $providedParams
	 * @throws ContainerException
	 */
	private function resolveParameter(ReflectionParameter $param, array $providedParams, ?string $consumer = null): mixed
	{
		// An explicitly provided argument always wins.
		$name = $param->getName();

		if (array_key_exists($name, $providedParams)) {
			return $providedParams[$name];
		}

		// A `#[NoAutowire]` marker suppresses type-based autowiring,
		// deferring to whatever fallback the signature allows: the
		// parameter's declared default, `null` when it is nullable, or
		// failure when it has neither.
		if ($param->getAttributes(NoAutowire::class) !== []) {
			return $this->resolveFallback($param);
		}

		// A contextual attribute resolves its own value and takes
		// precedence over type-based autowiring.
		$contextual = $param->getAttributes(
			ContextualAttribute::class,
			ReflectionAttribute::IS_INSTANCEOF
		);

		if ($contextual !== []) {
			return $contextual[0]->newInstance()->resolve($this);
		}

		// A contextual binding lets a specific consumer override how a
		// parameter is supplied: by name (a value the container cannot
		// autowire, such as a scalar) or by type (a per-consumer
		// implementation swap). It sits below an explicit argument and
		// any parameter attribute, but above type-based autowiring.
		if ($consumer !== null && isset($this->contextual[$consumer])) {
			$bindings = $this->contextual[$consumer];

			// By name first: the most specific, and the path for a
			// scalar the container has no other way to supply. The
			// bound value is passed as-is (or computed, for a
			// closure) — never resolved as a class, since a name
			// binding is always a value.
			if (array_key_exists($name, $bindings['params'] ?? [])) {
				$give = $bindings['params'][$name];

				return $give instanceof Closure ? $give($this) : $give;
			}

			// Then by type, limited to a single named, non-builtin
			// type. A closure is computed; a class-string is
			// resolved through the container so its own binding,
			// lifetime, and hooks apply.
			$type = $this->parameterTypeName($param);

			if ($type !== null && array_key_exists($type, $bindings['types'] ?? [])) {
				$give = $bindings['types'][$type];

				return $give instanceof Closure
					? $give($this)
					: $this->resolve($give);
			}
		}

		// A variadic parameter is inherently optional: PHP permits
		// calling with zero arguments for it. With no provided value or
		// contextual attribute (such as `#[Tagged]`) to fill it, the
		// container contributes an empty set rather than autowiring a
		// lone instance or failing on an unsatisfiable type. The `[]`
		// is spread by resolveDependencies() into zero arguments, the
		// same path a populated collection takes, so it must stay an array.
		if ($param->isVariadic()) {
			return [];
		}

		// Autowire from the type, falling back to a default value, `null`
		// when the parameter is nullable, or failing.
		return $this->autowireParameter($param)
			?? $this->resolveFallback($param);
	}

	/**
	 * Autowire a dependency from a parameter type, returning the resolved
	 * value or `null` when the type cannot be satisfied.
	 *
	 * typeAlternatives() expresses the type in disjunctive normal form: a
	 * list of alternatives, each a set of class names a single object must
	 * satisfy together (one for a plain type, several for an intersection).
	 * A registered binding is preferred over a class that merely exists, so
	 * the bindings pass runs across every alternative before the build
	 * pass; the first alternative satisfied wins.
	 *
	 * A binding that resolves to `null` is indistinguishable from a type
	 * that could not be autowired, so it too returns `null` and is deferred
	 * to the caller's fallback. This is deliberate: for a nullable parameter
	 * the fallback yields the same `null`, and for a non-nullable one it
	 * raises a clear ContainerException rather than letting the `null` reach
	 * the constructor as a TypeError. (Provided arguments and contextual
	 * attributes are matched by presence, so their `null` values are not
	 * subject to this and are injected as-is.)
	 *
	 * A class can exist yet not be auto-wirable — a value object such as
	 * `WP_Post`, whose own constructor requires a `WP_Post`. Building one
	 * throws, which means only "this alternative is unsatisfied": the build
	 * loop records the failure and moves on, so a union still gets to try
	 * its remaining members. If nothing is satisfiable and the parameter
	 * carries its own fallback (a default value or a nullable type), the
	 * failure is swallowed and `null` is returned so resolveFallback()
	 * supplies the default; otherwise the original build failure is
	 * re-thrown, preserving its diagnostic chain for a required dependency.
	 *
	 * @throws ContainerException
	 */
	private function autowireParameter(ReflectionParameter $param): mixed
	{
		$alternatives = $this->typeAlternatives($param->getType());

		// A registered binding anywhere wins over a class that merely
		// exists, so every alternative is tried against bindings before
		// any is built.
		$bound = $this->firstSatisfied($alternatives, $this->fromBinding(...));

		if ($bound !== null) {
			return $bound;
		}

		// Build pass. A candidate that throws leaves the alternative
		// unsatisfied (so the next one is still tried) while the first
		// such failure is kept to surface later if needed.
		$failure = null;

		$built = $this->firstSatisfied(
			$alternatives,
			function (string $className) use (&$failure): mixed {
				if (! class_exists($className)) {
					return null;
				}

				try {
					return $this->make($className);
				} catch (ContainerException $e) {
					$failure ??= $e;

					return null;
				}
			}
		);

		if ($built !== null) {
			return $built;
		}

		// A genuine build failure is surfaced only for a parameter with no
		// fallback of its own; an optional one defers to resolveFallback().
		if ($failure !== null && ! $this->parameterHasFallback($param)) {
			throw $failure;
		}

		return null;
	}

	/**
	 * Returns the first alternative that $acquire can satisfy, or `null`.
	 * Each alternative is a set of class names a single object must satisfy
	 * together; $acquire turns a class name into a candidate, or `null`
	 * when it has nothing to offer for that name.
	 *
	 * @param  list<list<class-string>>      $alternatives
	 * @param  callable(class-string): mixed $acquire
	 * @throws ContainerException
	 */
	private function firstSatisfied(array $alternatives, callable $acquire): mixed
	{
		foreach ($alternatives as $members) {
			foreach ($members as $className) {
				$candidate = $acquire($className);

				if ($this->candidateSatisfies($candidate, $members)) {
					return $candidate;
				}
			}
		}

		return null;
	}

	/**
	 * Resolve a candidate from a registered binding, or `null` when the
	 * name is not registered.
	 *
	 * @param  class-string $className
	 * @throws ContainerException
	 */
	private function fromBinding(string $className): mixed
	{
		return $this->registered($className) ? $this->resolve($className) : null;
	}

	/**
	 * Decomposes a parameter type into the alternatives that can satisfy
	 * it, in declaration order. Each alternative is a list of class names a
	 * single object must satisfy together: one name for a plain class type,
	 * several for an intersection. A union yields one alternative per
	 * member. Built-in types and built-in union members yield nothing.
	 *
	 * This mirrors the disjunctive normal form (DNF) that PHP 8.2+ uses for
	 * composite types — a union whose members may be intersections, e.g.
	 * `(A&B)|C`. On PHP 8.1 a union never contains an intersection, so the
	 * nested case simply never arises.
	 *
	 * @return list<list<class-string>>
	 */
	private function typeAlternatives(?ReflectionType $type): array
	{
		if ($type instanceof ReflectionNamedType) {
			return $type->isBuiltin() ? [] : [[$type->getName()]];
		}

		if ($type instanceof ReflectionIntersectionType) {
			return [$this->intersectionMembers($type)];
		}

		if ($type instanceof ReflectionUnionType) {
			$alternatives = [];

			foreach ($type->getTypes() as $member) {
				if ($member instanceof ReflectionIntersectionType) {
					$alternatives[] = $this->intersectionMembers($member);
				} elseif ($member instanceof ReflectionNamedType && ! $member->isBuiltin()) {
					$alternatives[] = [$member->getName()];
				}
			}

			return $alternatives;
		}

		return [];
	}

	/**
	 * Returns the class names that make up an intersection type, narrowed
	 * to named types (the only members PHP permits in an intersection).
	 *
	 * @return list<class-string>
	 */
	private function intersectionMembers(ReflectionIntersectionType $type): array
	{
		$members = [];

		foreach ($type->getTypes() as $member) {
			if ($member instanceof ReflectionNamedType) {
				$members[] = $member->getName();
			}
		}

		return $members;
	}

	/**
	 * Whether a candidate satisfies an alternative. A single-member
	 * alternative is trusted as-is (any non-null value); a multi-member
	 * (intersection) alternative requires an object that is an instance of
	 * every member.
	 *
	 * @param list<class-string> $members
	 */
	private function candidateSatisfies(mixed $candidate, array $members): bool
	{
		if ($candidate === null) {
			return false;
		}

		return count($members) === 1
			|| (is_object($candidate) && $this->satisfiesAll($candidate, $members));
	}

	/**
	 * Whether an object is an instance of every one of the given types,
	 * i.e. whether it satisfies an intersection of them.
	 *
	 * @param list<class-string> $members
	 */
	private function satisfiesAll(object $candidate, array $members): bool
	{
		foreach ($members as $member) {
			if (! $candidate instanceof $member) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Return the name of a parameter's type when it is a single named,
	 * non-builtin type — the only shape a type-based contextual binding
	 * matches. A built-in, union, intersection, or untyped parameter
	 * yields `null`.
	 */
	private function parameterTypeName(ReflectionParameter $param): ?string
	{
		$type = $param->getType();

		return $type instanceof ReflectionNamedType && ! $type->isBuiltin()
			? $type->getName()
			: null;
	}

	/**
	 * Whether a parameter carries a fallback of its own: a default value or
	 * a nullable type. This is exactly the set of cases resolveFallback()
	 * satisfies without throwing, so autowiring defers to it rather than
	 * surfacing a build failure when this is `true`.
	 */
	private function parameterHasFallback(ReflectionParameter $param): bool
	{
		return $param->isDefaultValueAvailable()
			|| (bool) $param->getType()?->allowsNull();
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

		throw new ContainerException(esc_html(sprintf(
			'Unresolvable dependency: parameter "$%s"%s in %s could not be resolved.',
			$param->getName(),
			$type ? sprintf(' of type %s', $type) : '',
			$param->getDeclaringClass()?->getName() ?? 'an unknown class'
		)));
	}
}
