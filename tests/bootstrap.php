<?php

/**
 * PHPUnit bootstrap.
 *
 * The framework targets WordPress, so it calls WordPress functions directly.
 * The unit suite runs without a WordPress runtime, so the few WP functions the
 * source relies on are stubbed here with faithful-enough behavior for tests to
 * exercise code paths (such as container exception messages) that use them.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

// A bootstrap both loads the autoloader (a side effect) and declares the WP
// stubs below, which is exactly the pairing this sniff forbids elsewhere.
// phpcs:disable PSR1.Files.SideEffects

require_once __DIR__ . '/../vendor/autoload.php';

if (! function_exists('esc_html')) {
	function esc_html(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}
}
