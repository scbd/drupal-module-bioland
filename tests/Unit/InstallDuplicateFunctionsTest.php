<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Test for duplicate function declarations in bioland.install.
 *
 * @group bioland
 * @coversNothing
 */
class InstallDuplicateFunctionsTest extends TestCase {

  /**
   * Test that bioland.install has no duplicate function names.
   *
   * This test prevents the fatal error:
   * "Cannot redeclare function bioland_update_XXXX()"
   *
   * It scans the bioland.install file for all function declarations
   * and ensures each function name appears only once.
   */
  public function testNoDuplicateFunctions() {
    $install_file = dirname(__DIR__, 3) . '/bioland.install';
    
    // Handle both module root and tests directory contexts
    if (!file_exists($install_file)) {
      $install_file = __DIR__ . '/../../bioland.install';
    }
    
    $this->assertFileExists($install_file, 'bioland.install file should exist');
    
    $content = file_get_contents($install_file);
    $this->assertNotEmpty($content, 'bioland.install should not be empty');
    
    // Match all function declarations
    preg_match_all('/^function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m', $content, $matches);
    
    $this->assertNotEmpty($matches[1], 'Should find at least one function declaration');
    
    $function_names = $matches[1];
    $duplicates = [];
    $seen = [];
    
    foreach ($function_names as $line_number => $function_name) {
      if (isset($seen[$function_name])) {
        $duplicates[] = $function_name;
      }
      $seen[$function_name] = $line_number;
    }
    
    $this->assertEmpty(
      $duplicates,
      sprintf(
        'Found duplicate function declarations: %s. Each function must have a unique name.',
        implode(', ', array_unique($duplicates))
      )
    );
  }

  /**
   * Test that update hook numbers are sequential (within reason).
   *
   * This test ensures update hooks follow a logical sequence.
   * While gaps are allowed (e.g., 9011, 9013), duplicates are not.
   */
  public function testUpdateHooksAreUnique() {
    $install_file = dirname(__DIR__, 3) . '/bioland.install';
    
    // Handle both module root and tests directory contexts
    if (!file_exists($install_file)) {
      $install_file = __DIR__ . '/../../bioland.install';
    }
    
    $this->assertFileExists($install_file);
    
    $content = file_get_contents($install_file);
    
    // Match all bioland_update_XXXX functions
    preg_match_all('/^function\s+(bioland_update_(\d+))\s*\(/m', $content, $matches);
    
    $this->assertNotEmpty($matches[1], 'Should find at least one update hook');
    
    $update_hooks = $matches[1];  // Full function names
    $update_numbers = $matches[2]; // Just the numbers
    
    // Check for duplicate update numbers
    $counts = array_count_values($update_numbers);
    $duplicates = array_filter($counts, function($count) {
      return $count > 1;
    });
    
    $this->assertEmpty(
      $duplicates,
      sprintf(
        'Found duplicate update hook numbers: %s. Each update hook must have a unique number.',
        implode(', ', array_keys($duplicates))
      )
    );
  }

  /**
   * Test that helper functions are not duplicated.
   *
   * Ensures private helper functions (prefixed with _bioland_) are unique.
   */
  public function testHelperFunctionsAreUnique() {
    $install_file = dirname(__DIR__, 3) . '/bioland.install';
    
    // Handle both module root and tests directory contexts
    if (!file_exists($install_file)) {
      $install_file = __DIR__ . '/../../bioland.install';
    }
    
    $this->assertFileExists($install_file);
    
    $content = file_get_contents($install_file);
    
    // Match all _bioland_ helper functions
    preg_match_all('/^function\s+(_bioland_[a-zA-Z0-9_]*)\s*\(/m', $content, $matches);
    
    if (empty($matches[1])) {
      // No helper functions found, that's okay
      $this->addToAssertionCount(1);
      return;
    }
    
    $helper_functions = $matches[1];
    
    // Check for duplicates
    $counts = array_count_values($helper_functions);
    $duplicates = array_filter($counts, function($count) {
      return $count > 1;
    });
    
    $this->assertEmpty(
      $duplicates,
      sprintf(
        'Found duplicate helper functions: %s. Each helper function must have a unique name.',
        implode(', ', array_keys($duplicates))
      )
    );
  }

  /**
   * Test that the file can be parsed without syntax errors.
   *
   * This is a basic sanity check that the PHP syntax is valid.
   */
  public function testFileHasValidSyntax() {
    $install_file = dirname(__DIR__, 3) . '/bioland.install';
    
    // Handle both module root and tests directory contexts
    if (!file_exists($install_file)) {
      $install_file = __DIR__ . '/../../bioland.install';
    }
    
    $this->assertFileExists($install_file);
    
    // Use php -l to check syntax
    $output = [];
    $return_var = 0;
    exec('php -l ' . escapeshellarg($install_file) . ' 2>&1', $output, $return_var);
    
    $this->assertEquals(
      0,
      $return_var,
      sprintf(
        'bioland.install has syntax errors: %s',
        implode("\n", $output)
      )
    );
  }

}
