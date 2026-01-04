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
   * Test that update hook numbers are unique across all install files.
   *
   * This test ensures update hooks follow a logical sequence.
   * While gaps are allowed (e.g., 9011, 9013), duplicates are not.
   * Checks both bioland.install and all includes/*.inc files.
   */
  public function testUpdateHooksAreUnique() {
    $module_root = dirname(__DIR__, 3);
    
    // Handle both module root and tests directory contexts
    if (!file_exists($module_root . '/bioland.install')) {
      $module_root = __DIR__ . '/../..';
    }
    
    // Collect all install-related files
    $files_to_check = [];
    
    // Main install file
    $install_file = $module_root . '/bioland.install';
    if (file_exists($install_file)) {
      $files_to_check[] = $install_file;
    }
    
    // Include files
    $includes_dir = $module_root . '/includes';
    if (is_dir($includes_dir)) {
      $include_files = glob($includes_dir . '/bioland.install.*.inc');
      if ($include_files) {
        $files_to_check = array_merge($files_to_check, $include_files);
      }
    }
    
    $this->assertNotEmpty($files_to_check, 'Should find at least one install file in ' . $module_root);
    
    $all_update_numbers = [];
    $update_hook_locations = [];
    
    // Scan all files for update hooks
    foreach ($files_to_check as $file) {
      $content = file_get_contents($file);
      
      // Match all bioland_update_XXXX functions
      preg_match_all('/^function\s+(bioland_update_(\d+))\s*\(/m', $content, $matches);
      
      if (!empty($matches[2])) {
        foreach ($matches[2] as $number) {
          $all_update_numbers[] = $number;
          $update_hook_locations[$number][] = basename($file);
        }
      }
    }
    
    $this->assertNotEmpty($all_update_numbers, 'Should find at least one update hook across all install files');
    
    // Check for duplicate update numbers
    $counts = array_count_values($all_update_numbers);
    $duplicates = array_filter($counts, function($count) {
      return $count > 1;
    });
    
    if (!empty($duplicates)) {
      $error_details = [];
      foreach (array_keys($duplicates) as $dup_number) {
        $locations = implode(', ', $update_hook_locations[$dup_number]);
        $error_details[] = "bioland_update_$dup_number (found in: $locations)";
      }
      
      $this->fail(
        sprintf(
          "Found duplicate update hook numbers:\n%s\n\nEach update hook must have a unique number.",
          implode("\n", $error_details)
        )
      );
    }
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
