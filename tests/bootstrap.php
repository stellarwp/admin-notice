<?php

/**
 * PHPUnit bootstrap file.
 */

define('PROJECT_ROOT', dirname(__DIR__));

$_tests_dir = getenv('WP_TESTS_DIR') ?: rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
$autoloader = PROJECT_ROOT . '/vendor/autoload.php';

// First, verify that Composer dependencies have been installed.
if (! file_exists($autoloader)) {
    echo 'Composer dependencies must be installed for testing. Please run `composer install`, then try again.'
        . PHP_EOL;
    exit(1);
}

// Next, make sure we can find the WordPress instance.
if (! file_exists($_tests_dir . '/includes/functions.php')) {
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo "Could not find {$_tests_dir}/includes/functions.php, have you run `vendor/bin/install-wp-tests.sh` ?"
        . PHP_EOL;
    exit(1);
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

// Start up the WP testing environment.
require $autoloader;
require $_tests_dir . '/includes/bootstrap.php';

// Print the WordPress version during PHPUnit startup.
printf('WordPress %s' . PHP_EOL, esc_html($GLOBALS['wp_version']));
