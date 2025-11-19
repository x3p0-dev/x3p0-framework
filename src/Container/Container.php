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
 * binding concrete implementations to abstracts. The container supports
 * transient, singleton, and single-instance bindings.
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
	 * Register an existing instance as a singleton.
	 */
	public function instance(string $abstract, object $instance): void;

	/**
	 * Resolve a service from the container.
	 */
	public function get(string $abstract): mixed;

	/**
	 * Resolves a service from the container with parameters.
	 */
	public function make(string $abstract, array $parameters = []): object;

	/**
	 * Check if a service is registered with the container.
	 */
	public function has(string $abstract): bool;
}
