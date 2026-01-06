<?php

declare(strict_types=1);

$_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

if (!file_exists("{$_tests_dir}/includes/functions.php")) {
    echo "Could not find {$_tests_dir}/includes/functions.php\n";
    echo "Run: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]\n";
    exit(1);
}

require_once "{$_tests_dir}/includes/functions.php";

tests_add_filter('muplugins_loaded', function (): void {
    require dirname(__DIR__) . '/call-scheduler.php';
});

require "{$_tests_dir}/includes/bootstrap.php";
