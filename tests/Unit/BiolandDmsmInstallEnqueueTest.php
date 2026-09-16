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
     * No source file still carries the retired hardcoded dev host.
     */
    public function testHardcodedDevHostIsGone()
    {
        $root = dirname(__DIR__, 2);
        $paths = array_merge(
            glob($root . '/src/**/*.php') ?: [],
            glob($root . '/src/*/*/*.php') ?: [],
            glob($root . '/includes/*.inc') ?: []
        );

        foreach ($paths as $path) {
            $this->assertStringNotContainsString(
                'dmsm.cbddev.xyz',
                file_get_contents($path),
                sprintf('%s still hardcodes the dev config host.', $path)
            );
        }
    }
}
