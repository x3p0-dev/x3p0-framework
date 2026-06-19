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
 * `extend()`) for observing and decorating resolved services.
 */
interface Container
{
	/**
	 * Register a transient service (new instance each time).
	 */
	public function transient(string $abstract, mixed $concrete = null): void;

	/**
	 * Register a singleton service (cached instance).
	 */
	public function singleton(string $abstract, mixed $concrete = null): void;

	/**
	 * Register an existing value as a singleton. The value is stored and
	 * returned as-is; it is never built or autowired by the container.
	 */
	public function instance(string $abstract, mixed $instance): void;

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
	 * Register a callback that may decorate or replace the given abstract
	 * after it is built. The callback receives the resolved instance and the
	 * container, and must return the instance to use in its place — typically
	 * a wrapper satisfying the same contract. If the abstract is already
	 * resolved or registered, the decorator is applied to the stored instance
	 * immediately.
	 *
	 * @param Closure(object, Container): object $closure
	 */
	public function extend(string $abstract, Closure $closure): void;

	/**
	 * Check if a service is registered with the container.
	 */
	public function has(string $abstract): bool;

	/**
	 * Assign one or more abstracts to a tag so they can be resolved together.
	 *
	 * @param string|array<string> $abstracts
	 */
	public function tag(string|array $abstracts, string $tag): void;

	/**
	 * Resolve every abstract assigned to the given tag.
	 *
	 * @return array<object>
	 */
	public function tagged(string $tag): array;
}
