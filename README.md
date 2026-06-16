# X3P0: Framework

![Nova, a blue alien, as a construction worker wearing a toolbelt and holding a wrench in a city construction zone.](https://repository-images.githubusercontent.com/1098370533/fc172954-cd4e-4669-be63-ef92774fcbbf)

A lightweight, modern dependency injection framework for WordPress plugins and themes. Built with PHP 8.1+, it provides a robust DI container and abstract application layer to help you write cleaner, more maintainable WordPress code.

[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net)

## Features

- **Modern Dependency Injection Container**: Manage your application's dependencies with ease
- **Service Providers**: Organize your code with a clean, modular architecture
- **Singleton and Transient Services**: Full control over service lifetimes
- **WordPress-Friendly**: Designed to fit WordPress's load and boot lifecycle, while staying free of WordPress dependencies in its core
- **Bootable Services**: Optional boot process for service initialization
- **Attribute-Based Hooks**: Register WordPress actions and filters declaratively with `#[Action]` and `#[Filter]`
- **Lightweight**: Minimal overhead with maximum flexibility
- **Type-Safe**: Full PHP 8.1+ type declarations for better IDE support

## Requirements

- PHP 8.1 or higher
- WordPress (recommended latest version)
- Composer

## Installation

Install via Composer:

```bash
composer require x3p0-dev/x3p0-framework
```

**Important:** If you're releasing this as part of a theme or plugin bundle, please vendor prefix your installation to avoid conflicts with other plugins/themes.

## Quick Start

### 1. Create Your Services

First, define the services your application needs:

```php
<?php
namespace Your\Project;

class ServiceA implements ServiceAInterface
{
	public function doSomething(): void
	{
		// Your implementation
	}
}

class ServiceB
{
	public function __construct(
		private ServiceAInterface $serviceA
	) {}

	public function boot(): void
	{
		// Bootstrap code
	}
}
```

### 2. Registers Services via a Service Provider

Extend the `ServiceProvider` base class to register your services:

```php
<?php
namespace Your\Project;

use X3P0\Framework\Contracts\Bootable;
use X3P0\Framework\Core\ServiceProvider;

final class YourServiceProvider extends ServiceProvider implements Bootable
{
	public function register(): void
	{
		// Register an abstract/interface with a concrete implementation.
		// `transient()` creates a new instance each time.
		$this->container->transient(
			ServiceAInterface::class,
			ServiceA::class
		);

		// Register a concrete implementation.
		// `singleton()` creates a single instance and reuses it.
		$this->container->singleton(ServiceB::class);
	}

	// Implementing `Bootable` is optional but useful for bootstrapping.
	public function boot(): void
	{
		$this->container->get(ServiceB::class)->boot();
	}
}
```

### 3. Create Your Application

Extend the `Application` base class to define your plugin/theme configuration:

```php
<?php
namespace Your\Project;

use X3P0\Framework\Core\Application;

final class Plugin extends Application
{
	/**
	 * Defines the plugin's service providers.
	 */
	protected const PROVIDERS = [
		YourServiceProvider::class
	];
}
```

### 4. Bootstrap Your Application

Create a helper function to access your application instance:

```php
<?php
namespace Your\Project;

use X3P0\Framework\Container\ServiceContainer;
use X3P0\Framework\Core\Application;

function plugin(): Plugin
{
	static $plugin;

	if (! $plugin instanceof Plugin) {
		$plugin = new Plugin(new ServiceContainer());
	}

	return $plugin;
}
```

### 5. Initialize in Your Main Plugin File

The framework doesn't fire any WordPress hooks of its own, so you decide when
registration and booting happen. A typical setup instantiates the application
on `plugins_loaded`, fires your own registration hook so third parties can add
providers, then boots:

```php
<?php
/**
 * Plugin Name: Your Plugin
 * Plugin URI:  https://example.com
 * Description: Your plugin description
 * Version:	1.0.0
 * Author:	Your Name
 */

namespace Your\Project;

// Autoload dependencies.
require_once __DIR__ . '/vendor/autoload.php';

add_action('plugins_loaded', function (): void {
	// Let third-party code register additional providers.
	do_action('your/project/register', plugin());

	// Boot all registered providers.
	plugin()->boot();
}, -999);
```

`boot()` is safe to call more than once: each provider is only booted a single
time, so you can boot across multiple load phases. For example, a project might
register some providers on `plugins_loaded` and others on `after_setup_theme`,
booting each phase as it goes:

```php
add_action('plugins_loaded', function (): void {
	do_action('your/project/register/plugin', plugin());
	plugin()->boot();
}, -999);

add_action('after_setup_theme', function (): void {
	do_action('your/project/register/theme', plugin());
	plugin()->boot();
}, -999);
```

Providers registered during the theme phase boot on the second `boot()` call;
those already booted during the plugin phase are skipped.

## Core Concepts

### Service Container

The service container manages the creation and lifecycle of your application's objects. It supports three binding types:

#### Singleton

Creates a single instance that's reused throughout the application:

```php
$this->container->singleton(MyServiceInterface::class, MyService::class);
```

#### Transient

Creates a new instance each time it's requested:

```php
$this->container->transient(MyServiceInterface::class, MyService::class);
```

#### Instance

Registers an existing instance with the container, which is reused throughout the application:

```php
$this->container->instance('my-custom-instance', new MyCustomInstance());
```

### Service Providers

Service providers are the central place to configure your container bindings. They have two main methods:

- `register()`: Register bindings in the container
- `boot()`: Execute bootstrapping code (optional, requires implementing `Bootable`)

### Tagging

Tagging groups related bindings under a shared label so they can be resolved
together. This is useful for any collection of like services — blocks, widgets,
Customizer panels, REST controllers, CLI commands — where you'd otherwise have
to maintain a master list of every class.

Assign abstracts to a tag with `tag()` (a single abstract or an array), then
resolve them all with `tagged()`:

```php
$this->container->tag([AlertBlock::class, CalloutBlock::class], 'theme.blocks');

foreach ($this->container->tagged('theme.blocks') as $block) {
	$block->register();
}
```

Tagged abstracts are resolved through the container like any other service, so
singletons stay shared and unbound classes are auto-wired. An unknown tag
resolves to an empty array.

Service providers can declare tags up front with the `TAGS` constant, which maps
each tag name to its list of abstracts:

```php
final class BlockServiceProvider extends ServiceProvider
{
	protected const SINGLETONS = [
		AlertBlock::class,
		CalloutBlock::class
	];

	protected const TAGS = [
		'theme.blocks' => [
			AlertBlock::class,
			CalloutBlock::class
		]
	];

	public function boot(): void
	{
		add_action('init', function (): void {
			foreach ($this->container->tagged('theme.blocks') as $block) {
				$block->register();
			}
		});
	}
}
```

Because tags accumulate, different providers — or third-party code hooking your
registration action — can contribute to the same tag without editing the
provider that consumes it:

```php
add_action('your/project/register', function ($app): void {
	$app->container()->singleton(TestimonialBlock::class);
	$app->container()->tag(TestimonialBlock::class, 'theme.blocks');
});
```

The consuming `tagged('theme.blocks')` loop picks up the added block
automatically.

### The Application Class

The application class serves as the central hub of your plugin/theme:

- Manages service providers
- Orchestrates the boot process (safe to call across multiple load phases)

### Attribute-Based Hooks

The `X3P0\Framework\Support\Hooks` namespace provides a declarative way to
register WordPress actions and filters with PHP attributes, replacing manual
`add_action()`/`add_filter()` plumbing. Unlike the container core, this is an
opt-in WordPress integration layer — it calls WordPress hook functions, so it
is only useful inside a WordPress environment.

Add the `Hookable` trait to a class, mark members with `#[Action]` or
`#[Filter]`, and implement `Bootable`. The trait's `boot()` reflects the class
and wires every attributed member:

```php
use X3P0\Framework\Contracts\Bootable;
use X3P0\Framework\Support\Hooks\Action;
use X3P0\Framework\Support\Hooks\Filter;
use X3P0\Framework\Support\Hooks\Hookable;

final class Assets implements Bootable
{
	use Hookable;

	#[Action('wp_enqueue_scripts')]
	public function enqueue(): void
	{
		wp_enqueue_style('theme', get_stylesheet_uri());
	}

	#[Filter('body_class')]
	public function bodyClass(array $classes): array
	{
		$classes[] = 'x3p0-theme';
		return $classes;
	}
}
```

The number of arguments passed to the callback is taken from the method's
parameter count, so `bodyClass()` above receives the `$classes` argument
automatically.

Attributes are repeatable, and the priority accepts an integer or the shorthand
`'first'` (`PHP_INT_MIN`) / `'last'` (`PHP_INT_MAX`):

```php
#[Action('init')]
#[Action('wp_loaded', priority: 'first')]
public function bootstrap(): void
{
	// Runs on both `init` (priority 10) and `wp_loaded` (priority PHP_INT_MIN).
}

#[Filter('the_content', priority: 20)]
public function content(string $content): string
{
	return $content;
}
```

Properties and class constants can be hooked too, in which case their value is
returned to the filter:

```php
#[Filter('the_title', priority: 'last')]
const TITLE_SUFFIX = ' ✦';
```

Because the attributes are matched by the `Hook` interface using
`ReflectionAttribute::IS_INSTANCEOF`, you can define your own hook attributes by
implementing `Hook` (or extending `Filter`), and the trait will pick them up.

A hookable class is wired like any other bootable service — register it in a
provider's `SINGLETONS` and list it in `BOOTABLE`:

```php
final class FrontendServiceProvider extends ServiceProvider
{
	protected const SINGLETONS = [
		Assets::class,
	];

	protected const BOOTABLE = [
		Assets::class,
	];
}
```

When the provider boots, `Assets::boot()` runs and registers its hooks.

## Advanced Usage

### Accessing Services

Retrieve services from the container:

```php
$service = plugin()->container()->get(ServiceA::class);
```

Or from within a service provider:

```php
$service = $this->container->get(ServiceA::class);
```

### Multiple Service Providers

Register multiple service providers in your application:

```php
final class App extends Application
{
	protected const PROVIDERS = [
		CoreServiceProvider::class,
		AdminServiceProvider::class,
		FrontendServiceProvider::class,
	];
}
```

### Constructor Injection

The container automatically resolves dependencies:

```php
class MyService
{
	public function __construct(
		private DependencyA $dependencyA,
		private DependencyB $dependencyB
	) {}
}

// The container will automatically inject DependencyA and DependencyB.
$this->container->singleton(MyService::class);
```

### Service Provider Dependencies

Providers registered by class name are resolved through the container, so they
can declare their own dependencies in the constructor and have them auto-wired.
Accept the `Container` and pass it to the parent, then add whatever else you
need:

```php
use X3P0\Framework\Container\Container;
use X3P0\Framework\Core\ServiceProvider;

final class YourServiceProvider extends ServiceProvider
{
	public function __construct(Container $container, private Logger $logger)
	{
		parent::__construct($container);
	}

	public function register(): void
	{
		// $this->logger is available here.
	}
}
```

Dependencies must be resolvable by the time the provider is registered.

## Best Practices

### 1. Keep Service Providers Focused

Each service provider should handle a specific domain or feature:

```php
// Good
class AdminServiceProvider extends ServiceProvider { /* ... */ }
class ApiServiceProvider extends ServiceProvider { /* ... */ }

// Avoid
class EverythingProvider extends ServiceProvider { /* ... */ }
```

### 2. Use Interfaces for Flexibility

Bind interfaces to implementations for easier testing and flexibility:

```php
$this->container->singleton(
	CacheInterface::class,
	TransientCache::class
);
```

### 3. Leverage the Boot Method

Use the service provider's `boot()` method for operations that require all services to be registered:

```php
public function boot(): void
{
	// Runs a service's `init()` method.
	$this->container->get(MyService::class)->init();
}
```

### 4. Vendor Prefix When Necessary

If you're distributing your plugin/theme, consider using a tool like [PHP-Scoper](https://github.com/humbug/php-scoper) to avoid conflicts.

## License

X3P0 Framework is licensed under the [GPL-2.0-or-later](LICENSE.md) license.

## Credits

Created and maintained by [Justin Tadlock](https://github.com/justintadlock) under the [X3P0](https://github.com/x3p0-dev) umbrella.

## Support

- [GitHub Issues](https://github.com/x3p0-dev/x3p0-framework/issues)
- [Packagist](https://packagist.org/packages/x3p0-dev/x3p0-framework)

---

**Note:** This framework is designed for modern PHP development. If you need to support older PHP versions, please consider using a different solution or forking this project.
