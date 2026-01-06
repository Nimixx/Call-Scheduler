<?php
/**
 * Cache demonstration and test
 * Run with: php tests/cache-demo.php
 *
 * This demonstrates the Cache class functionality without WordPress
 */

declare(strict_types=1);

// Mock WordPress functions for standalone testing
$GLOBALS['_test_transients'] = [];
$GLOBALS['_test_expiry'] = [];

function get_transient(string $key) {
    if (!isset($GLOBALS['_test_transients'][$key])) {
        return false;
    }

    if (isset($GLOBALS['_test_expiry'][$key]) && time() > $GLOBALS['_test_expiry'][$key]) {
        unset($GLOBALS['_test_transients'][$key], $GLOBALS['_test_expiry'][$key]);
        return false;
    }

    return $GLOBALS['_test_transients'][$key];
}

function set_transient(string $key, $value, int $ttl): bool {
    $GLOBALS['_test_transients'][$key] = $value;
    $GLOBALS['_test_expiry'][$key] = time() + $ttl;

    return true;
}

function delete_transient(string $key): bool {
    if (!isset($GLOBALS['_test_transients'][$key])) {
        return false;
    }

    unset($GLOBALS['_test_transients'][$key], $GLOBALS['_test_expiry'][$key]);
    return true;
}

define('ABSPATH', true);
define('HOUR_IN_SECONDS', 3600);

// Load the Cache class
require_once __DIR__ . '/../src/Cache.php';

use CallScheduler\Cache;

echo "Cache Class Demonstration\n";
echo "==========================\n\n";

$cache = new Cache();

// Test 1: Basic get/set
echo "Test 1: Basic get/set\n";
$cache->set('test_key', 'test_value');
$value = $cache->get('test_key');
echo "Set 'test_key' = 'test_value'\n";
echo "Get 'test_key' = " . var_export($value, true) . "\n";
echo $value === 'test_value' ? "✓ PASS\n\n" : "✗ FAIL\n\n";

// Test 2: Cache miss
echo "Test 2: Cache miss\n";
$value = $cache->get('nonexistent_key');
echo "Get 'nonexistent_key' = " . var_export($value, true) . "\n";
echo $value === null ? "✓ PASS\n\n" : "✗ FAIL\n\n";

// Test 3: has() method
echo "Test 3: has() method\n";
$cache->set('exists', 'value');
$exists = $cache->has('exists');
$missing = $cache->has('doesnt_exist');
echo "has('exists') = " . var_export($exists, true) . "\n";
echo "has('doesnt_exist') = " . var_export($missing, true) . "\n";
echo ($exists === true && $missing === false) ? "✓ PASS\n\n" : "✗ FAIL\n\n";

// Test 4: delete() method
echo "Test 4: delete() method\n";
$cache->set('to_delete', 'value');
echo "Set 'to_delete'\n";
$deleted = $cache->delete('to_delete');
echo "Delete returned: " . var_export($deleted, true) . "\n";
$value = $cache->get('to_delete');
echo "Get after delete: " . var_export($value, true) . "\n";
echo $value === null ? "✓ PASS\n\n" : "✗ FAIL\n\n";

// Test 5: remember() method (read-through cache)
echo "Test 5: remember() method\n";
$call_count = 0;
$generator = function() use (&$call_count) {
    $call_count++;
    return "generated_value_{$call_count}";
};

// First call - should generate
$value1 = $cache->remember('remember_key', $generator);
echo "First call: value = $value1, generator called $call_count times\n";

// Second call - should use cache
$value2 = $cache->remember('remember_key', $generator);
echo "Second call: value = $value2, generator called $call_count times\n";

echo ($value1 === $value2 && $call_count === 1) ? "✓ PASS (generator only called once)\n\n" : "✗ FAIL\n\n";

// Test 6: Complex data types
echo "Test 6: Complex data types\n";
$complex_data = [
    'users' => [
        ['id' => 1, 'name' => 'John'],
        ['id' => 2, 'name' => 'Jane'],
    ],
    'meta' => [
        'total' => 2,
        'cached_at' => time(),
    ],
];

$cache->set('complex', $complex_data);
$retrieved = $cache->get('complex');

echo "Stored array with " . count($complex_data['users']) . " users\n";
echo "Retrieved: " . var_export($retrieved, true) . "\n";
echo ($retrieved === $complex_data) ? "✓ PASS\n\n" : "✗ FAIL\n\n";

// Test 7: Cache pattern simulation (like team members)
echo "Test 7: Simulating team members cache\n";

$db_query_count = 0;

$getTeamMembers = function() use ($cache, &$db_query_count) {
    return $cache->remember(
        'team_members',
        function() use (&$db_query_count) {
            $db_query_count++;
            echo "  → Database query executed (query #$db_query_count)\n";
            return [
                ['id' => 1, 'name' => 'User 1'],
                ['id' => 2, 'name' => 'User 2'],
            ];
        },
        HOUR_IN_SECONDS
    );
};

echo "Call 1:\n";
$members1 = $getTeamMembers();

echo "Call 2 (should use cache):\n";
$members2 = $getTeamMembers();

echo "Call 3 (should use cache):\n";
$members3 = $getTeamMembers();

echo "\nDatabase queries executed: $db_query_count\n";
echo ($db_query_count === 1) ? "✓ PASS (only 1 database query)\n\n" : "✗ FAIL\n\n";

// Test 8: Cache invalidation pattern
echo "Test 8: Cache invalidation pattern\n";

echo "Initial load (hits database):\n";
$members = $getTeamMembers();
echo "Cached members: " . count($members) . "\n";

echo "\nInvalidating cache (team member updated):\n";
$cache->delete('team_members');

echo "Load after invalidation (should hit database again):\n";
$db_query_count_before = $db_query_count;
$members = $getTeamMembers();
$queries_after_invalidation = $db_query_count - $db_query_count_before;

echo "New database queries: $queries_after_invalidation\n";
echo ($queries_after_invalidation === 1) ? "✓ PASS (cache was invalidated)\n\n" : "✗ FAIL\n\n";

// Summary
echo "==========================\n";
echo "All tests completed!\n";
echo "\nCache Benefits:\n";
echo "- Reduces database queries from N to 1\n";
echo "- Automatic expiration (TTL)\n";
echo "- Scales with Redis/Memcached if available\n";
echo "- Simple invalidation on data changes\n";
