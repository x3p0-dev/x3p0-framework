<?php

/**
 * Not found exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Container;

/**
 * Thrown when the container has no entry for the requested identifier and is
 * unable to resolve it. Extends `ContainerException`, so catching the latter
 * also catches not-found failures.
 */
class NotFoundException extends ContainerException
{
}
