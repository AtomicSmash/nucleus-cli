# Nucleus

A WordPress development toolkit for working with projects that use composer and node to manage assets.

## Installation

You can install the package globally via composer:

```bash
composer global require atomicsmash/nucleus
```

## Usage

### Plugin Migration

The package provides a command to migrate your WordPress plugins to Composer via wpackagist. This is useful for managing WordPress plugins through Composer in non-Launchpad projects.

Run the following command from your project directory.

```bash
nucleus plugins:migrate
```

The command will:
1. Look for the `wp-content` directory in either:
   - The project root
   - A `public` directory
2. If not found, prompt you to enter the path or quit
3. Scan all plugins in the plugins directory
4. For each plugin:
   - Extract its version from the main plugin file
   - Check if it exists in the WordPress plugin directory
   - If it exists, add it to composer via wpackagist
   - If not, add it to a list of not found plugins
5. Display any plugins that weren't found in the WordPress directory

## Requirements

- PHP 7.4 or higher
- WordPress installation with plugins directory
- Composer

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
