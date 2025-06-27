# Nucleus

A WordPress development toolkit for working with projects that use composer and node to manage assets.

## Installation

You can install the package globally via composer:

```bash
composer global require atomicsmash/nucleus
```

## Usage

Nucleus provides several commands to help set up and manage WordPress projects:

### Full Project Setup

Run a complete project setup that executes all setup commands in sequence:

```bash
nucleus project:setup
```

This command will run:
1. WordPress Setup
2. Project Core Setup  
3. Plugin Migration

### WordPress Setup

Set up your WordPress installation with proper directory structure:

```bash
nucleus wordpress:setup
```

The command will:
1. Find your WordPress installation in common locations
2. Prompt you for the target location (default: `public/wp`)
3. Move WordPress to the specified location
4. Move the `wp-content` directory to `public/`
5. Remove WordPress core files from the target location
6. Provide next steps guidance

### Project Core Setup

Copy and configure core project files from templates:

```bash
nucleus project:core
```

The command will:
1. Collect project configuration (vendor name, project name, PHP version, etc.)
2. Copy template files from the nucleus package:
   - `.config/wp-configs/*` → `.config/wp-configs/`
   - `public/wp-config.php` → `public/wp-config.php`
   - `.editorconfig` → `.editorconfig`
   - `.gitignore` → `.gitignore`
   - `.valetrc` → `.valetrc`
   - `composer.json` → `composer.json`
   - `herd.yml` → `herd.yml`
3. Replace placeholders (e.g., `{{PROJECT_NAME}}`) with user input
4. Handle file conflicts with options to overwrite, backup and replace, or skip

### Plugin Migration

Migrate your WordPress plugins to Composer via wpackagist:

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

## Template Files

The package includes template files that are copied during the project core setup. These files contain placeholders that are replaced with user input:

- `{{VENDOR_NAME}}` - Your vendor/organization name
- `{{PROJECT_NAME}}` - Your project name
- `{{PROJECT_DESCRIPTION}}` - Project description
- `{{PHP_VERSION}}` - PHP version (e.g., 8.1)
- `{{WORDPRESS_VERSION}}` - WordPress version (e.g., 6.4)
- `{{WEB_ROOT}}` - Web root path (e.g., public/)
- `{{THEME_NAME}}` - Theme name

## Requirements

- PHP 7.4 or higher
- WordPress installation
- Composer

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
