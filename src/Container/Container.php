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

/**
 * Defines the dependency injection container interface, which allows for
 * binding concrete implementations to abstracts. The container supports the
 * registration of transients, singletons, and instances.
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
	 * Register an existing value as a singleton.
	 */
	public function instance(string $abstract, mixed $instance): void;

	/**
	 * Resolve a service from the container.
	 */
	public function get(string $abstract): mixed;

	/**
	 * Resolves a service from the container with parameters.
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
