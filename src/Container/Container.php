<?php

/**
 * Container interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Container;

use Closure;

/**
 * Defines the dependency injection container interface. It binds concrete
 * implementations to abstracts — as transients, singletons, or existing
 * instances — and autowires dependencies when resolving. It also supports
 * tagging, deferred resolution, and lifecycle hooks (`resolving()`,
 * `decorate()`) for observing and decorating resolved services.
 */
interface Container
{
	/**
	 * Register a transient service (new instance each time).
	 */
	public function transient(string $abstract, mixed $concrete = null): void;

	/**
	 * Register a transient service only if the abstract is not already
	 * bound, so an existing binding (such as one registered by an extension)
	 * is left in place.
	 */
	public function transientIf(string $abstract, mixed $concrete = null): void;

	/**
	 * Register a singleton service (cached instance).
	 */
	public function singleton(string $abstract, mixed $concrete = null): void;

	/**
	 * Register a singleton service only if the abstract is not already
	 * bound, so an existing binding (such as one registered by an extension)
	 * is left in place.
	 */
	public function singletonIf(string $abstract, mixed $concrete = null): void;

	/**
	 * Register an existing value as a singleton. The value is stored and
	 * returned as-is; it is never built or autowired by the container.
	 */
	public function instance(string $abstract, mixed $instance): void;

	/**
	 * Register an alias so that resolving `$alias` resolves `$abstract`
	 * instead, returning the same instance and lifetime. Aliases are
	 * followed transitively, so an alias may point at another alias. An
	 * identifier is either an alias or a binding, never both: aliasing a
	 * name drops any binding registered under it, and binding a name drops
	 * any alias under it.
	 */
	public function alias(string $alias, string $abstract): void;

	/**
	 * Resolve a service from the container by its identifier, returning the
	 * bound value or an autowired instance. Use `make()` to pass constructor
	 * overrides or for a guaranteed object return.
	 */
	public function get(string $abstract): mixed;

	/**
	 * Resolve a class from the container, optionally overriding constructor
	 * parameters. Values in `$parameters` are matched by name and take
	 * precedence over type-based autowiring; remaining parameters are resolved
	 * from the container. A parameterized resolution is never cached.
	 *
	 * @template T of object
	 * @param    class-string<T>      $abstract
	 * @param    array<string, mixed> $parameters
	 * @return   T
	 */
	public function make(string $abstract, array $parameters = []): object;

	/**
	 * Invoke a callable, resolving its parameters from the container.
	 * Values in `$parameters` are matched by name and take precedence over
	 * type-based resolution. The array form of `$callback` accepts a
	 * `[class-string, 'method']` pair, whose object is resolved from the
	 * container before the method is invoked.
	 *
	 * @param callable|array{0: object|string, 1: string} $callback
	 * @param array<string, mixed>                        $parameters
	 */
	public function call(callable|array $callback, array $parameters = []): mixed;

	/**
	 * Return a closure that resolves the given abstract on each call, so a
	 * consumer can create instances on demand without depending on the
	 * container itself. Values passed to the returned closure are matched by
	 * name and take precedence over type-based resolution, mirroring `make()`.
	 *
	 * @template T of object
	 * @param    class-string<T> $abstract
	 * @return   Closure(array<string, mixed>): T
	 */
	public function defer(string $abstract): Closure;

	/**
	 * Register a callback run after the given abstract is built, before it is
	 * returned. The callback receives the resolved instance and the container,
	 * and is expected to mutate the instance in place. Callbacks run once per
	 * build, so a resolved singleton is only observed the first time it is
	 * created.
	 *
	 * @param Closure(object, Container): void $callback
	 */
	public function resolving(string $abstract, Closure $callback): void;

	/**
	 * Register a callback that decorates the given abstract after it is built.
	 * The callback receives the resolved instance and the container, and must
	 * return the instance to use in its place — typically a wrapper that adds
	 * behavior while satisfying the same contract. Though any replacement
	 * honoring the contract is allowed (a reconfigured copy or an alternative
	 * implementation). Multiple decorators stack in registration order,
	 * each wrapping the result of the previous one. If the abstract is already
	 * resolved or registered, the decorator is applied to the stored instance
	 * immediately.
	 *
	 * @param Closure(object, Container): object $closure
	 */
	public function decorate(string $abstract, Closure $closure): void;

	/**
	 * Check whether the container can resolve the given abstract — that is,
	 * whether `get()` would return without throwing a not-found error. This
	 * is true for a registered abstract or any buildable (existing) class,
	 * so an auto-wirable class reports `true` even without an explicit binding.
	 */
	public function has(string $abstract): bool;

	/**
	 * Check whether the abstract has been explicitly registered as a binding
	 * or an instance. Unlike `has()`, this is `false` for a class that is
	 * merely auto-wirable.
	 */
	public function registered(string $abstract): bool;

	/**
	 * Check whether an instance for the abstract is already cached — either
	 * a resolved singleton or a value registered via `instance()`.
	 */
	public function resolved(string $abstract): bool;

	/**
	 * Forget a resolved singleton or registered instance so the next
	 * resolution rebuilds it. The binding and any `resolving()`/`decorate()`
	 * hooks are left in place and re-applied on the next build.
	 */
	public function forgetInstance(string $abstract): void;

	/**
	 * Assign one or more abstracts to a tag so they can be resolved together.
	 * Abstracts already assigned to the tag are ignored, so a tag never holds
	 * duplicates.
	 *
	 * @param string|array<string> $abstracts
	 */
	public function tag(string|array $abstracts, string $tag): void;

	/**
	 * Remove one or more abstracts from a tag, leaving the rest in place.
	 *
	 * @param string|array<string> $abstracts
	 */
	public function untag(string|array $abstracts, string $tag): void;

	/**
	 * Resolve every abstract assigned to the given tag.
	 *
	 * @return array<object>
	 */
	public function tagged(string $tag): array;

	/**
	 * Return the abstracts assigned to the given tag without resolving them,
	 * for inspection or lazy resolution. The order matches assignment order,
	 * and an unknown tag yields an empty array.
	 *
	 * @return array<string>
	 */
	public function taggedIds(string $tag): array;
}
