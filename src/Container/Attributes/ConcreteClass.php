<?php

/**
 * Concrete class attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Container\Attributes;

use Attribute;
use X3P0\Framework\Container\Container;
use X3P0\Framework\Container\NotFoundException;

/**
 * Injects the concrete class the container would build for an abstract, without
 * building it, mirroring `Container::concreteClass()`. Useful for handing a
 * class-string to code that constructs the instance later:
 *
 *     public function __construct(
 *         #[ConcreteClass(Handler::class)] private string $handler
 *     ) {}
 *
 * When the abstract has no static class (a factory-closure binding or an
 * unregistered non-class id), this throws `NotFoundException` so a parameter
 * with a default or nullable type falls back to it, mirroring `#[Param]`.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class ConcreteClass implements ContextualAttribute
{
	/**
	 * Stores the abstract whose concrete class is resolved.
	 */
	public function __construct(private readonly string $abstract)
	{}

	/**
	 * Resolves the concrete class from the container.
	 *
	 * @return class-string
	 * @throws NotFoundException When the abstract has no static class.
	 */
	public function resolve(Container $container): string
	{
		$class = $container->concreteClass($this->abstract);

		if ($class === null) {
			throw new NotFoundException(esc_html(sprintf(
				'Cannot resolve a concrete class for "%s".',
				$this->abstract
			)));
		}

		return $class;
	}
}
