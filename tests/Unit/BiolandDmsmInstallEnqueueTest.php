<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\Service\BiolandDmsmConfigService;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the update-hook path enqueues and never fetches in-request.
 *
 * Update hooks run under /update.php as a web request in the site's own
 * PHP-FPM pool. With the config API base URL now configurable and expected to
 * point at this site's own route, an in-request fetch would block one worker of
 * that pool waiting on another worker of the same pool.
 *
 * @group bioland
 */
class BiolandDmsmInstallEnqueueTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__ . '/../../includes/bioland.install.dmsm.inc';
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        // The enqueue helper deduplicates per process, so hook_install and both
        // update hooks queue ONE item for a host rather than three. That record
        // outlives a single test, so clear it between cases.
        $queued = &_bioland_dmsm_enqueue_dedupe_state();
        $queued = [];
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        \Drupal::resetContainer();
        parent::tearDown();
    }

    /**
     * The helper enqueues via the service and performs no HTTP request.
     */
    public function testHelperEnqueuesAndDoesNotFetch()
    {
        $service = $this->createMock(BiolandDmsmConfigService::class);
        $service->expects($this->once())
            ->method('enqueueCountriesUpdate')
            ->with('example.bl2.cbddev.xyz')
            ->willReturn(['success' => true, 'message' => 'queued', 'queued' => true]);
        $service->expects($this->never())->method('updateCountriesFromDmsm');

        \Drupal::setService('bioland.dmsm_config', $service);

        $this->assertNull(_bioland_update_countries_from_dmsm('example.bl2.cbddev.xyz'));
    }

    /**
     * A failed enqueue is surfaced as a message, not swallowed.
     */
    public function testHelperReportsEnqueueFailure()
    {
        $service = $this->createMock(BiolandDmsmConfigService::class);
        $service->method('enqueueCountriesUpdate')
            ->willReturn(['success' => false, 'message' => 'bad host', 'queued' => false]);
        $service->expects($this->never())->method('updateCountriesFromDmsm');

        \Drupal::setService('bioland.dmsm_config', $service);

        $this->assertStringContainsString(
            'bad host',
            (string) _bioland_update_countries_from_dmsm('invalid.example.com')
        );
    }

    /**
     * The update hook enqueues and returns without fetching.
     */
    public function testUpdateHookEnqueuesWithoutFetching()
    {
        $service = $this->createMock(BiolandDmsmConfigService::class);
        $service->expects($this->once())
            ->method('enqueueCountriesUpdate')
            ->willReturn(['success' => true, 'message' => 'queued', 'queued' => true]);
        $service->expects($this->never())->method('updateCountriesFromDmsm');

        \Drupal::setService('bioland.dmsm_config', $service);

        $this->assertStringContainsString('Queued', (string) bioland_update_9023());
    }

    /**
     * The region/continent update hook enqueues rather than fetching.
     */
    public function testRegionUpdateHookEnqueuesWithoutFetching()
    {
        $service = $this->createMock(BiolandDmsmConfigService::class);
        $service->expects($this->once())
            ->method('enqueueCountriesUpdate')
            ->willReturn(['success' => true, 'message' => 'queued', 'queued' => true]);
        $service->expects($this->never())->method('updateCountriesFromDmsm');

        \Drupal::setService('bioland.dmsm_config', $service);

        $this->assertStringContainsString('Queued', (string) bioland_update_9046());
    }

    /**
     * The same host is enqueued once per process, not once per caller.
     *
     * hook_install and both update hooks request the same fetch for the same
     * host; a single `drush updatedb` run would otherwise queue three identical
     * items that each repeat the same work.
     */
    public function testRepeatedRequestsForOneHostQueueOneItem()
    {
        $service = $this->createMock(BiolandDmsmConfigService::class);
        $service->expects($this->once())
            ->method('enqueueCountriesUpdate')
            ->willReturn(['success' => true, 'message' => 'queued', 'queued' => true]);

        \Drupal::setService('bioland.dmsm_config', $service);

        $this->assertNull(_bioland_update_countries_from_dmsm('example.bl2.cbddev.xyz'));
        $this->assertNull(_bioland_update_countries_from_dmsm('example.bl2.cbddev.xyz'));
        $this->assertNull(_bioland_update_countries_from_dmsm('example.bl2.cbddev.xyz'));
    }

    /**
     * No source file still carries the retired hardcoded dev host.
     *
     * Scans every .php under src/ and every .inc under includes/ with a
     * RecursiveDirectoryIterator. The previous version used glob() with "**",
     * which PHP does not support, so a top-level src/*.php was never scanned at
     * all and this anti-regression test had a hole in exactly the directory it
     * most needed to cover.
     *
     * Comments are stripped before matching: the docblocks explaining WHY the
     * dev host was retired legitimately name it, and the regression this guards
     * against is a host in code, not a host in prose.
     */
    public function testHardcodedDevHostIsGone()
    {
        $paths = $this->sourceFiles();

        $this->assertNotEmpty($paths, 'The source scan found no files to check.');
        $this->assertContains(
            dirname(__DIR__, 2) . '/src/Service/BiolandDmsmConfigService.php',
            $paths,
            'The scan must reach the service that used to hardcode the host.'
        );

        foreach ($paths as $path) {
            $this->assertStringNotContainsString(
                'dmsm.cbddev.xyz',
                $this->stripComments(file_get_contents($path)),
                sprintf('%s still hardcodes the dev config host.', $path)
            );
        }
    }

    /**
     * Every PHP source file of the module, at any depth.
     *
     * @return string[]
     *   Absolute paths.
     */
    protected function sourceFiles()
    {
        $root = dirname(__DIR__, 2);
        $paths = [];

        foreach (['/src', '/includes'] as $dir) {
            if (!is_dir($root . $dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root . $dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (in_array($file->getExtension(), ['php', 'inc'], true)) {
                    $paths[] = $file->getPathname();
                }
            }
        }

        sort($paths);

        return $paths;
    }

    /**
     * Drops comments and docblocks, leaving the executable source.
     *
     * @param string $source
     *   The PHP source.
     *
     * @return string
     *   The source without comments.
     */
    protected function stripComments($source)
    {
        $out = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }

                $out .= $token[1];
                continue;
            }

            $out .= $token;
        }

        return $out;
    }
}
