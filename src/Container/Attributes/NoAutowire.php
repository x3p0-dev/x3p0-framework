<?php

/**
 * NoAutowire attribute.
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
 * Suppresses type-based autowiring for the attributed parameter. Instead of
 * resolving the parameter from its type, the container resolves it as if it
 * were not involved: the parameter's declared default value is used, or `null`
 * when the parameter is nullable but declares no default. A required parameter
 * with no fallback of its own still fails, since there is nothing to inject.
 *
 * This is useful when a parameter's type is autowirable but an instance is not
 * wanted by default — a value object the caller supplies later, for example:
 *
 *     public function __construct(
 *         #[NoAutowire] ?WP_User $user = null
 *     ) {}
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class NoAutowire
{
}
