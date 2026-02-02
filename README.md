# Nucleus CLI

A WordPress development toolkit for working with projects that use composer and node to manage assets with a DevOps focus.

## Installation

You can install the package globally via composer:

```bash
composer global require atomicsmash/nucleus-cli
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
   - **Project Configuration**: Collect and store project settings (vendor, project name, web root, etc.)
   - **Project Core Setup**: Copy and configure core project files (composer.json, wp-config.php, etc.)
   - **WordPress Setup**: Move WordPress to the correct location and organise wp-content directory
   - **Plugin Migration**: Migrate WordPress plugins to Composer via wpackagist
   - **Theme Cleanup**: Remove unused themes, keeping only the active theme and its parent
3. Allow you to skip any individual section if you don't need it
4. Provide a summary of completed tasks and next steps

You can also run each command independently if you prefer to set up your project step by step.

### Project Configuration

Configure project settings that are shared across all commands:

```bash
nucleus project:config
```

This command collects and stores the following project information:
- **Basic Project Settings**: Vendor name, project name, description, license, slug
- **WordPress Configuration**: Web root path, WordPress install path, PHP version
- **Theme Selection**: Choose from available themes in current WordPress installation
- **Git Configuration**: Remote SSH URL, default branch

The configuration is stored in memory and used by subsequent commands, eliminating duplicate prompts.

### WordPress Setup

Set up your WordPress installation with proper directory structure:

```bash
nucleus wordpress:setup
```

**Note**: Requires project configuration to be set up first using `nucleus project:config`.

The command will:
1. Use stored project configuration (web root, WordPress install path)
2. Find your WordPress installation in common locations
3. Move WordPress to the specified location within the web root
4. Move the `wp-content` directory to the web root
5. Remove WordPress core files from the target location
6. Provide next steps guidance

### Project Core Setup

Copy and configure core project files from templates:

```bash
nucleus project:core
```

**Note**: Requires project configuration to be set up first using `nucleus project:config`.

The command will:
1. Use stored project configuration (vendor, project name, web root, etc.)
2. Collect environment-specific configuration (database settings, security keys, external services)
3. Copy template files from the nucleus package:
   - `.config/wp-configs/*` → `.config/wp-configs/`
   - `public/wp-config.php` → `[web-root]/wp-config.php`
   - `.editorconfig` → `.editorconfig`
   - `.gitignore` → `.gitignore`
   - `.valetrc` → `.valetrc`
   - `composer.json` → `composer.json`
   - `herd.yml` → `herd.yml`
4. Replace placeholders with collected configuration
5. Handle file conflicts with options to overwrite, backup and replace, or skip

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

### Plugins Update

Run the monthly plugin update workflow with optional maintenance emails and deploy steps:

```bash
nucleus plugins:update
```

**Requirements:** Project must have `composer.json` with `johnpbloch/wordpress` and at least one of `https://wpackagist.org` or `https://release-belt.atomicsmash.co.uk` in repositories. A `.nucleus/config.yml` file is required.

The command will:
1. Ask if you want to send the monthly maintenance email to your client
2. Check project and (if sending email) global config; prompt for MailerSend API key and sender details if missing
3. Optionally create a maintenance branch from `main` (e.g. `feature/🛠️-Maintenance-YYYY-MM-DD`)
4. Optionally send the "starting maintenance" email (Template 1) via MailerSend
5. Check wpackagist and release-belt plugins for updates; show changelog; apply updates (including release-belt flow: plugins.atomicsmash.co.uk + SSH update script)
6. Optionally commit changes to the maintenance branch with a generated commit message
7. Optionally deploy to staging (create release branch, merge maintenance, run `npm run deploy`)
8. Prompt you to check staging and create a PR (e.g. via Sourcetree or GitLab)
9. Pull `main`, then optionally deploy to production (`npm run deploy:prod`)
10. Optionally send the "maintenance complete" email (Template 2) via MailerSend
11. Finish and clear workflow state

Progress is stored in `.nucleus/plugin-workflow.yml` so you can resume if interrupted. Use `--no-resume` to start fresh.

**Config:**
- **Project** `.nucleus/config.yml`: `plugins_update.exclude` (plugin slugs to skip), `maintenance_email.client_name`, `maintenance_email.client_email`, `maintenance_email.cc`
- **Global** `~/.config/nucleus/config.yml` (or `~/.nucleus/config.yml`): `mailersend.api_key`, `mailersend.template_id`, `sender_email`, `developer_name`

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
- `{{PROJECT_SLUG}}` - Project slug (auto-generated from project name if not specified, e.g. "the-abc-company")
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
