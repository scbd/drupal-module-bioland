<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests to prevent duplicate function declarations in module files.
 *
 * This test exists because duplicate update hook functions have been
 * accidentally introduced multiple times, causing fatal PHP errors.
 */
final class DuplicateFunctionTest extends TestCase
{
    /**
     * Test that bioland.install has no duplicate function declarations.
     */
    public function testNoDuplicateFunctionsInInstallFile(): void
    {
        $installFile = dirname(__DIR__, 2) . '/bioland.install';
        $this->assertFileExists($installFile, 'bioland.install file should exist');

        $content = file_get_contents($installFile);
        $this->assertNotEmpty($content, 'bioland.install should not be empty');

        // Find all function declarations
        preg_match_all('/^\s*function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m', $content, $matches);

        $functionNames = $matches[1];
        $this->assertNotEmpty($functionNames, 'Should find function declarations in bioland.install');

        // Check for duplicates
        $counts = array_count_values($functionNames);
        $duplicates = array_filter($counts, fn($count) => $count > 1);

        $this->assertEmpty(
            $duplicates,
            sprintf(
                "Duplicate function declarations found in bioland.install: %s",
                implode(', ', array_map(
                    fn($name, $count) => "$name (declared $count times)",
                    array_keys($duplicates),
                    array_values($duplicates)
                ))
            )
        );
    }

    /**
     * Test that bioland.module has no duplicate function declarations.
     */
    public function testNoDuplicateFunctionsInModuleFile(): void
    {
        $moduleFile = dirname(__DIR__, 2) . '/bioland.module';
        $this->assertFileExists($moduleFile, 'bioland.module file should exist');

        $content = file_get_contents($moduleFile);
        $this->assertNotEmpty($content, 'bioland.module should not be empty');

        // Find all function declarations
        preg_match_all('/^\s*function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m', $content, $matches);

        $functionNames = $matches[1];
        
        if (empty($functionNames)) {
            // Module file might have no functions, which is fine
            $this->assertTrue(true);
            return;
        }

        // Check for duplicates
        $counts = array_count_values($functionNames);
        $duplicates = array_filter($counts, fn($count) => $count > 1);

        $this->assertEmpty(
            $duplicates,
            sprintf(
                "Duplicate function declarations found in bioland.module: %s",
                implode(', ', array_map(
                    fn($name, $count) => "$name (declared $count times)",
                    array_keys($duplicates),
                    array_values($duplicates)
                ))
            )
        );
    }

    /**
     * Test that update hooks are sequentially numbered without gaps or duplicates.
     */
    public function testUpdateHooksAreSequential(): void
    {
        $installFile = dirname(__DIR__, 2) . '/bioland.install';
        $content = file_get_contents($installFile);

        // Find all bioland_update_XXXX functions
        preg_match_all('/function\s+bioland_update_(\d+)\s*\(/m', $content, $matches);

        $updateNumbers = array_map('intval', $matches[1]);
        
        if (empty($updateNumbers)) {
            $this->assertTrue(true, 'No update hooks found');
            return;
        }

        sort($updateNumbers);

        // Check for duplicates
        $uniqueNumbers = array_unique($updateNumbers);
        $this->assertCount(
            count($updateNumbers),
            $uniqueNumbers,
            sprintf(
                "Duplicate update hook numbers found: %s",
                implode(', ', array_diff_assoc($updateNumbers, $uniqueNumbers))
            )
        );

        // Check for sequential numbering (no gaps)
        $first = reset($updateNumbers);
        $last = end($updateNumbers);
        $expected = range($first, $last);

        $missing = array_diff($expected, $updateNumbers);
        $this->assertEmpty(
            $missing,
            sprintf(
                "Missing update hook numbers (gaps in sequence): %s",
                implode(', ', $missing)
            )
        );
    }
}
