# Dagger PHP Module — Project Context

## Overview

This is a Dagger module monorepo providing a "ready to use" PHP/Symfony CI/CD toolchain. It exposes an intuitive fluent API for building PHP containers with common tools pre-integrated.

## Module Structure

```
dagger-php-module/
├── php/        # Main module — PHP container builder (all tools integrated)
├── phpstan/    # Separate module — Static analysis via PHPStan
└── app/        # Demo/test module — usage examples, not for production use
```

### Module dependencies
```
app → php
app → phpstan
phpstan → (none, uses official image ghcr.io/phpstan/phpstan)
```

## Key Architecture Decisions

- **Composer and PIE are integrated into the `php/` module** (not standalone). This keeps the fluent API ergonomic: `dag()->php()->withComposer()->install()->endComposer()->withPie()->install()->endPie()`.
- **PECL is included for legacy extensions** not yet available via PIE (e.g. redis, imagick).
- **PHPStan uses its official Docker image** (`ghcr.io/phpstan/phpstan`) to avoid managing a PHP container with PHPStan installed separately.
- **Symfony tooling is a sub-object** (`withSymfony()`) to keep Symfony-specific concerns isolated.

## Development Commands

```bash
# Set up development environment for php module (generates SDK)
dagger develop -m ./php

# List exposed functions
dagger functions -m ./php
dagger functions -m ./phpstan

# Run the demo app (interactive terminal)
dagger call -m ./app debug terminal

# Run PHPUnit tests via app
dagger call -m ./app test --sources=.

# Run static analysis via app
dagger call -m ./app analyse --sources=.

# PHPStan standalone
dagger call -m ./phpstan analyse --sources=./src --level=5
```

## php/ Module — Fluent API Overview

```php
dag()
    ->php('8.3-cli')                    // PHP version/tag
    ->withSources($dir)                 // mount source code
    ->withComposer()                    // Composer sub-object
        ->install()
    ->endComposer()
    ->withPie()                         // PIE extension installer
        ->install(['xdebug/xdebug'])
    ->endPie()
    ->withPecl()                        // PECL legacy installer
        ->install(['redis'])
    ->endPecl()
    ->withSymfony()                     // Symfony tooling
        ->clearCache()
        ->migrate()
    ->endSymfony()
    ->phpunit()                         // run PHPUnit (returns Container)
    ->container()                       // export Container
```

## phpstan/ Module

```php
dag()
    ->phpstan()
    ->analyse($sources, level: '8', config: 'phpstan.neon')
```

## Daggerverse Standards Applied

- `#[Doc]` on all classes, methods, and parameters
- `#[ListOfType('string')]` on all `array` parameters
- `#[DefaultPath('.')]` + `#[Ignore(...)]` on `Directory` inputs
- Fluent builder pattern with `clone $this` for immutability
- Cache volumes keyed by PHP version for apt and composer caches
- `endXxx()` pattern to return to the parent in the chain

## Testing Changes

After modifying a module, run:
```bash
dagger develop -m ./php       # re-generate SDK
dagger call -m ./app debug terminal  # smoke test
```
