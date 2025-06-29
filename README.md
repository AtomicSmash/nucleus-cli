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

This command will:
1. Ask for confirmation to proceed with the overall setup
2. For each setup command, show a description and ask if you want to run it:
   - **Project Core Setup**: Copy and configure core project files (composer.json, wp-config.php, etc.)
   - **WordPress Setup**: Move WordPress to the correct location and organise wp-content directory
   - **Plugin Migration**: Migrate WordPress plugins to Composer via wpackagist
   - **Theme Cleanup**: Remove unused themes, keeping only the active theme and its parent
3. Allow you to skip any individual section if you don't need it
4. Provide a summary of completed tasks and next steps

You can also run each command independently if you prefer to set up your project step by step.

### WordPress Setup

Set up your WordPress installation with proper directory structure:

```bash
nucleus wordpress:setup
```

The command will:
1. Prompt you for the web root path (default: `public/`)
2. Find your WordPress installation in common locations
3. Prompt you for the WordPress installation path relative to the web root (default: `wp`)
4. Move WordPress to the specified location within the web root
5. Move the `wp-content` directory to the web root
6. Remove WordPress core files from the target location
7. Provide next steps guidance

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

### Theme Cleanup

Clean up themes by keeping only the active theme and its parent (if child theme):

```bash
nucleus theme:cleanup
```

The command will:
1. Check for active theme in `package.json` config (if exists)
2. If not found, prompt you to select the active theme from available themes
3. Detect if the active theme is a parent or child theme by reading `style.css`
4. If it's a child theme, identify and preserve the parent theme
5. Show a summary of themes to be deleted
6. Confirm deletion with the user
7. Delete all other themes while preserving the active theme and its parent (if applicable)

## Template Files

The package includes template files that are copied during the project core setup. These files contain placeholders that are replaced with user input:

### Basic Project Settings
- `{{VENDOR_NAME}}` - Your vendor/organisation name
- `{{PROJECT_NAME}}` - Your project name (e.g. "The ABC Company")
- `{{PROJECT_DESCRIPTION}}` - Project description
- `{{PROJECT_LICENSE}}` - Project license (e.g., proprietary)
- `{{PROJECT_SLUG}}` - Project slug (auto-generated from project name, e.g. "the-abc-company")
- `{{THEME_NAME}}` - Theme name (selected from available themes in `wp-content/themes` or custom entry)

### Environment Configuration
- `{{PHP_VERSION}}` - PHP version (e.g. `8.2`)
- `{{WORDPRESS_VERSION}}` - WordPress version (e.g. `6.7.2`)
- `{{WEB_ROOT}}` - Web root path (e.g. `public/`)
- `{{WORDPRESS_INSTALL_PATH}}` - WordPress installation directory (e.g. `wp`)
- `{{WORDPRESS_TABLE_PREFIX}}` - WordPress database table prefix (e.g. `wp_`)
- `{{KINSTA_FOLDER}}` - Kinsta folder name (e.g. `theabccompany_123`)

### Git Configuration
- `{{GIT_REMOTE_SSH}}` - Git remote SSH URL
- `{{GIT_DEFAULT_BRANCH}}` - Git default branch (e.g. `main`)

### Development Environment
- `{{DB_NAME_DEVELOPMENT}}` - Development database name
- `{{DB_USER_DEVELOPMENT}}` - Development database user
- `{{DB_PASSWORD_DEVELOPMENT}}` - Development database password
- `{{DB_HOST_DEVELOPMENT}}` - Development database host

### Staging Environment
- `{{DB_NAME_STAGING}}` - Staging database name
- `{{DB_USER_STAGING}}` - Staging database user
- `{{DB_PASSWORD_STAGING}}` - Staging database password
- `{{DB_HOST_STAGING}}` - Staging database host
- `{{STAGING_SSH_HOST}}` - Staging SSH host
- `{{STAGING_SSH_USER}}` - Staging SSH user
- `{{STAGING_SSH_PORT}}` - Staging SSH port
- `{{STAGING_URL}}` - Staging URL

### Production Environment
- `{{DB_NAME_PRODUCTION}}` - Production database name
- `{{DB_USER_PRODUCTION}}` - Production database user
- `{{DB_PASSWORD_PRODUCTION}}` - Production database password
- `{{DB_HOST_PRODUCTION}}` - Production database host
- `{{PRODUCTION_SSH_HOST}}` - Production SSH host
- `{{PRODUCTION_SSH_USER}}` - Production SSH user
- `{{PRODUCTION_SSH_PORT}}` - Production SSH port
- `{{PRODUCTION_URL}}` - Production URL

### WordPress Security Keys

The package automatically generates secure WordPress security keys using the [rbdwllr/wordpress-salts-generator](https://packagist.org/packages/rbdwllr/wordpress-salts-generator) package. These include:

- `{{AUTH_KEY}}` - WordPress authentication key
- `{{SECURE_AUTH_KEY}}` - WordPress secure authentication key
- `{{LOGGED_IN_KEY}}` - WordPress logged in key
- `{{NONCE_KEY}}` - WordPress nonce key
- `{{AUTH_SALT}}` - WordPress authentication salt
- `{{SECURE_AUTH_SALT}}` - WordPress secure authentication salt
- `{{LOGGED_IN_SALT}}` - WordPress logged in salt
- `{{NONCE_SALT}}` - WordPress nonce salt

### External Services
- `{{MAILTRAP_USERNAME}}` - MailTrap username
- `{{MAILTRAP_PASSWORD}}` - MailTrap password
- `{{RELEASE_BELT_USERNAME}}` - Release Belt username
- `{{RELEASE_BELT_PASSWORD}}` - Release Belt password
- `{{ACF_USERNAME}}` - ACF Pro username
- `{{ACF_PASSWORD}}` - ACF Pro password

During the project core setup, you'll be prompted for all these values with sensible defaults provided. For WordPress security keys, you can choose to generate them automatically or enter them manually.

## Requirements

- PHP 7.4 or higher
- WordPress installation
- Composer

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
