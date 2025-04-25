<?php
/**
 * Test file to find syntax errors
 */

// Start with basics
echo "Testing basic PHP syntax\n";

// Define a constant
define('TEST_CONSTANT', 'test-value');

// Simple function
function test_function() {
    return "Function works";
}

// Test array syntax
$test_array = array(
    'key1' => 'value1',
    'key2' => 'value2'
);

// Test object syntax
class Test_Class {
    public function test_method() {
        return "Method works";
    }
}

// Test WordPress-like functions
function test_add_action($hook, $callback) {
    echo "Adding action: $hook\n";
}

function test_add_filter($hook, $callback) {
    echo "Adding filter: $hook\n";
}

// Test rewrite rule syntax
function test_add_rewrite_rule($pattern, $query, $position) {
    echo "Adding rewrite rule: $pattern -> $query ($position)\n";
}

// Mock WordPress functions
function get_option($option, $default = '') {
    return $default;
}

function add_option($option, $value) {
    echo "Added option: $option = $value\n";
}

function update_option($option, $value) {
    echo "Updated option: $option = $value\n";
}

// Test all potential syntax errors often seen
test_add_action('init', 'test_function'); // Correct

// Potential error: missing comma in array
$test_array2 = array(
    'key1' => 'value1',
    'key2' => 'value2'
);

// Potential error: unclosed parenthesis
test_add_filter('the_content', 'test_function');

// Potential error: missing semicolon
$var = "test"

// Potential error: incorrect function parameter format
test_add_rewrite_rule(
    'test',
    'index.php?test=1',
    'top'
);

echo "Test completed\n";