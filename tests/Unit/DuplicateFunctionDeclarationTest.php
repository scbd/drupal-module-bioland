<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for duplicate function declarations across include files.
 *
 * @group bioland
 * @group bioland_code_quality
 */
class DuplicateFunctionDeclarationTest extends TestCase
{
    /**
     * Test that there are no duplicate function declarations in include files.
     *
     * This test scans all .inc files in the includes/ directory to ensure
     * that no function is declared more than once, which would cause
     * fatal errors when the module is enabled.
     */
    public function testNoDuplicateFunctionDeclarations()
    {
        $module_path = dirname(__DIR__, 2);
        $includes_path = $module_path . '/includes';

        if (!is_dir($includes_path)) {
            $this->markTestSkipped('Includes directory not found.');
        }

        $functions = [];
        $duplicates = [];

        // Scan all .inc files
        $files = glob($includes_path . '/*.inc');
        $this->assertNotEmpty($files, 'No .inc files found in includes/ directory');

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relative_path = str_replace($module_path . '/', '', $file);

            // Match function declarations using regex
            // Matches: function function_name(...) {
            preg_match_all(
                '/^\s*function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m',
                $content,
                $matches,
                PREG_OFFSET_CAPTURE
            );

            foreach ($matches[1] as $match) {
                $function_name = $match[0];
                $position = $match[1];

                // Calculate line number for better error reporting
                $line_number = substr_count(substr($content, 0, $position), "\n") + 1;

                if (isset($functions[$function_name])) {
                    // Found a duplicate
                    $duplicates[$function_name][] = [
                        'file' => $relative_path,
                        'line' => $line_number,
                    ];
                } else {
                    // First occurrence
                    $functions[$function_name] = [
                        'file' => $relative_path,
                        'line' => $line_number,
                    ];
                }
            }
        }

        // If duplicates found, add the original declarations to the duplicates array
        if (!empty($duplicates)) {
            foreach ($duplicates as $function_name => $occurrences) {
                array_unshift($duplicates[$function_name], $functions[$function_name]);
            }

            // Format error message
            $error_message = "Duplicate function declarations found:\n\n";
            foreach ($duplicates as $function_name => $occurrences) {
                $error_message .= "Function: $function_name\n";
                foreach ($occurrences as $occurrence) {
                    $error_message .= "  - {$occurrence['file']} (line {$occurrence['line']})\n";
                }
                $error_message .= "\n";
            }

            $this->fail($error_message);
        }

        // Assert that we scanned at least some functions
        $this->assertGreaterThan(
            0,
            count($functions),
            'No functions found in include files - test may not be working correctly'
        );
    }

    /**
     * Test that key helper functions exist and are unique.
     *
     * This test verifies that critical helper functions exist exactly once.
     */
    public function testCriticalFunctionsExistOnce()
    {
        $module_path = dirname(__DIR__, 2);
        $includes_path = $module_path . '/includes';

        // List of critical functions that should exist
        $critical_functions = [
            '_bioland_configure_content_type_available_menus',
            '_bioland_configure_content_types',
            '_bioland_enable_main_menu_lock',
            '_bioland_provision_users',
            '_bioland_import_translations',
        ];

        foreach ($critical_functions as $function_name) {
            $count = 0;
            $found_in = null;

            // Search all .inc files
            $files = glob($includes_path . '/*.inc');
            foreach ($files as $file) {
                $content = file_get_contents($file);
                if (preg_match('/^\s*function\s+' . preg_quote($function_name, '/') . '\s*\(/m', $content)) {
                    $count++;
                    $found_in = basename($file);
                }
            }

            $this->assertEquals(
                1,
                $count,
                "Function $function_name should exist exactly once (found $count times)" .
                ($found_in ? " in $found_in" : "")
            );
        }
    }
}
