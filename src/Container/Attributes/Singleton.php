<?php

/**
 * Singleton attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Container\Attributes;

use Attribute;

/**
 * Marks a class to be cached as a single shared instance when the container
 * autowires it, without requiring an explicit singleton binding. It only
 * applies to classes resolved without an explicit binding; a binding's declared
 * lifetime always takes precedence.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Singleton
{
}
