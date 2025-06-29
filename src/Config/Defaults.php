<?php

namespace Nucleus\Config;

use WPSalts\Salts;

class Defaults
{
    // WordPress configuration
    public const WORDPRESS_VERSION = '6.7.2';
    public const WORDPRESS_INSTALL_PATH = 'wp';
    public const WORDPRESS_TABLE_PREFIX = 'wp';
    
    // WordPress security keys (should be generated)
    public const AUTH_KEY = 'your-unique-phrase';
    public const SECURE_AUTH_KEY = 'your-unique-phrase';
    public const LOGGED_IN_KEY = 'your-unique-phrase';
    public const NONCE_KEY = 'your-unique-phrase';
    public const AUTH_SALT = 'your-unique-phrase';
    public const SECURE_AUTH_SALT = 'your-unique-phrase';
    public const LOGGED_IN_SALT = 'your-unique-phrase';
    public const NONCE_SALT = 'your-unique-phrase';
    
    // Project configuration
    public const VENDOR_NAME = 'atomicsmash';
    public const PROJECT_NAME = null;
    public const PROJECT_SLUG = null;
    public const PROJECT_DESCRIPTION = 'WordPress project';
    public const PROJECT_LICENSE = 'proprietary';
    public const THEME_NAME = null; // Will be set to basename of project root
    
    // Environment configuration
    public const PHP_VERSION = '8.2';
    public const WEB_ROOT = 'public/';
    
    // File paths
    public const WP_CONTENT_TARGET = 'public/wp-content';
    
    // Git configuration
    public const GIT_REMOTE_SSH = 'git@github.com:mycompany/project.git';
    public const GIT_DEFAULT_BRANCH = 'main';
    
    // Development environment
    public const DB_NAME_DEVELOPMENT = 'wordpress_dev';
    public const DB_USER_DEVELOPMENT = 'root';
    public const DB_PASSWORD_DEVELOPMENT = '';
    public const DB_HOST_DEVELOPMENT = 'localhost';
    
    // Staging environment
    public const STAGING_SSH_HOST = 'staging.example.com';
    public const STAGING_SSH_USER = 'deploy';
    public const STAGING_SSH_PORT = '22';
    public const STAGING_URL = 'staging.example.com';
    public const DB_NAME_STAGING = 'wordpress_staging';
    public const DB_USER_STAGING = 'staging_user';
    public const DB_PASSWORD_STAGING = 'staging_password';
    public const DB_HOST_STAGING = 'localhost';
    
    // Production environment
    public const PRODUCTION_SSH_HOST = 'production.example.com';
    public const PRODUCTION_SSH_USER = 'deploy';
    public const PRODUCTION_SSH_PORT = '22';
    public const PRODUCTION_URL = 'example.com';
    public const DB_NAME_PRODUCTION = 'wordpress_production';
    public const DB_USER_PRODUCTION = 'production_user';
    public const DB_PASSWORD_PRODUCTION = 'production_password';
    public const DB_HOST_PRODUCTION = 'localhost';
    public const KINSTA_FOLDER = 'example-com';
    
    // External services
    public const MAILTRAP_USERNAME = 'your-mailtrap-username';
    public const MAILTRAP_PASSWORD = 'your-mailtrap-password';
    public const RELEASE_BELT_USERNAME = 'your-release-belt-username';
    public const RELEASE_BELT_PASSWORD = 'your-release-belt-password';
    public const ACF_USERNAME = 'your-acf-username';
    public const ACF_PASSWORD = 'your-acf-password';
    
    // Possible WordPress installation paths to search
    public const WORDPRESS_SEARCH_PATHS = [
        'wordpress',
        'wp',
        'public/wordpress',
        'public/wp',
        'public',
        '.'
    ];
    
    /**
     * Get the theme name default (basename of current directory)
     */
    public static function getThemeName(): string
    {
        return basename(getcwd());
    }
    
    /**
     * Get the project name default (formatted basename of current directory)
     */
    public static function getProjectName(): string
    {
        $folderName = basename(getcwd());
        
        // Replace dashes and underscores with spaces
        $formatted = str_replace(['-', '_'], ' ', $folderName);
        
        // Capitalize the first letter of each word
        $formatted = ucwords($formatted);
        
        return $formatted;
    }
    
    /**
     * Get the project slug default (basename of current directory)
     */
    public static function getProjectSlug(): string
    {
        return basename(getcwd());
    }
    
    /**
     * Generate a slug from a project name
     */
    public static function generateProjectSlug(string $projectName): string
    {
        // Convert to lowercase
        $slug = strtolower($projectName);
        
        // Replace spaces and non-alphanumeric characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        
        // Remove leading and trailing hyphens
        $slug = trim($slug, '-');
        
        return $slug;
    }
    
    /**
     * Generate WordPress security keys using the wordpress-salts-generator package
     */
    public static function generateWordPressKeys(): array
    {
        $salts = new Salts();
        return $salts->wordPressSalts();
    }
} 
