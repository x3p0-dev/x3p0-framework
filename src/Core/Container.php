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

namespace X3P0\Framework\Core;

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
	 * Resolves a service from the container.
	 */
	public function make(string $abstract, array $parameters = []): object;

	/**
	 * Determines if the registered service has been bound.
	 */
	public function bound(string $abstract): bool;

	/**
	 * Resolve a binding from the container.
	 */
	public function get(string $id): mixed;

	/**
	 * Check if an abstract is bound.
	 */
	public function has(string $id): bool;
}
