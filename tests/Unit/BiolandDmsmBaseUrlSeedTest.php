<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\Service\BiolandDmsmConfigService;
use Drupal\Core\Config\Config;
use Drupal\Core\Site\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Tests the update hook that seeds the base URL on already-installed sites.
 *
 * config/install/ is imported at module install only, so the sites that were
 * already installed never receive a new key from it. Without bioland_update_9082
 * every one of them would have bioland.settings.dmsm_config_base_url unset, the
 * fetch would refuse, and geography sync would stop fleet-wide on deploy -
 * visible only as a watchdog error from a cron worker.
 *
 * @group bioland
 */
class BiolandDmsmBaseUrlSeedTest extends TestCase
{
    /**
     * The config the hook writes to.
     *
     * @var \Drupal\Core\Config\Config
     */
    protected $config;

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

        Settings::setAll([]);
        putenv('BIOLAND_DMSM_CONFIG_BASE_URL');
        putenv('BIOLAND_DMSM_PROD_HOST_ALLOWLIST');

        $this->config = new Config('bioland.settings', []);
        $factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
        $factory->method('getEditable')->willReturn($this->config);
        $factory->method('get')->willReturn($this->config);

        \Drupal::setService('config.factory', $factory);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        Settings::setAll([]);
        putenv('BIOLAND_DMSM_CONFIG_BASE_URL');
        putenv('BIOLAND_DMSM_PROD_HOST_ALLOWLIST');
        \Drupal::resetContainer();
        parent::tearDown();
    }

    /**
     * The key name the service reads is the key the hook writes.
     */
    public function testSeedsTheKeyOnASiteThatLacksIt()
    {
        Settings::setAll(['bioland_dmsm_config_base_url' => 'https://config.example.test/']);

        $this->assertNull(
            $this->config->get(BiolandDmsmConfigService::CONFIG_BASE_URL_KEY),
            'Precondition: the site does not have the key.'
        );

        $message = (string) bioland_update_9082();

        $this->assertSame(
            'https://config.example.test',
            $this->config->get(BiolandDmsmConfigService::CONFIG_BASE_URL_KEY)
        );
        $this->assertTrue($this->config->saved);
        $this->assertStringContainsString('Seeded', $message);
    }

    /**
     * The environment variable is used when settings.php names nothing.
     */
    public function testSeedsFromTheEnvironmentVariable()
    {
        putenv('BIOLAND_DMSM_CONFIG_BASE_URL=https://config.env.test');

        bioland_update_9082();

        $this->assertSame(
            'https://config.env.test',
            $this->config->get(BiolandDmsmConfigService::CONFIG_BASE_URL_KEY)
        );
    }

    /**
     * settings.php wins over the environment variable.
     */
    public function testSettingsPhpTakesPrecedence()
    {
        Settings::setAll(['bioland_dmsm_config_base_url' => 'https://config.settings.test']);
        putenv('BIOLAND_DMSM_CONFIG_BASE_URL=https://config.env.test');

        bioland_update_9082();

        $this->assertSame(
            'https://config.settings.test',
            $this->config->get(BiolandDmsmConfigService::CONFIG_BASE_URL_KEY)
        );
    }

    /**
     * An already-configured site is left exactly as it is.
     */
    public function testExistingValueIsNotOverwritten()
    {
        $this->config->set(BiolandDmsmConfigService::CONFIG_BASE_URL_KEY, 'https://already.example.test');
        $this->config->saved = false;
        Settings::setAll(['bioland_dmsm_config_base_url' => 'https://config.example.test']);

        $message = (string) bioland_update_9082();

        $this->assertSame(
            'https://already.example.test',
            $this->config->get(BiolandDmsmConfigService::CONFIG_BASE_URL_KEY)
        );
        $this->assertStringContainsString('already set', $message);
    }

    /**
     * The update FAILS when the deployment offers nothing to seed.
     *
     * Failing here surfaces the problem during deployment, rather than silently
     * at the next cron run on 200-odd sites at once.
     */
    public function testUpdateFailsWhenNoValueIsAvailable()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot seed bioland.settings.dmsm_config_base_url');

        bioland_update_9082();
    }

    /**
     * A value the fetch could never accept is treated as absent, not seeded.
     *
     * @dataProvider unusableValueProvider
     */
    public function testUnusableValuesAreTreatedAsAbsent($value)
    {
        Settings::setAll(['bioland_dmsm_config_base_url' => $value]);

        $this->expectException(\RuntimeException::class);

        bioland_update_9082();
    }

    /**
     * Data provider of deployment values that must not be seeded.
     *
     * @return array
     *   The values.
     */
    public function unusableValueProvider()
    {
        return [
            'empty' => [''],
            'whitespace' => ['   '],
            'no scheme' => ['config.example.test'],
            'not a URL' => ['not a url'],
            'embedded credentials' => ['https://svc:hunter2@config.example.test'],
            'a non-http scheme' => ['gopher://config.example.test'],
            'a non-string' => [42],
        ];
    }

    /**
     * The production allowlist is seeded from the deployment too.
     */
    public function testSeedsTheProductionAllowlist()
    {
        Settings::setAll([
            'bioland_dmsm_config_base_url' => 'https://config.example.test',
            'bioland_dmsm_prod_host_allowlist' => ['Config.Prod.Test.', 'other.prod.test'],
        ]);

        bioland_update_9082();

        $this->assertSame(
            ['config.prod.test', 'other.prod.test'],
            $this->config->get(BiolandDmsmConfigService::CONFIG_PROD_HOST_ALLOWLIST_KEY)
        );
    }

    /**
     * The allowlist also accepts a comma-separated environment variable.
     */
    public function testSeedsTheProductionAllowlistFromTheEnvironment()
    {
        Settings::setAll(['bioland_dmsm_config_base_url' => 'https://config.example.test']);
        putenv('BIOLAND_DMSM_PROD_HOST_ALLOWLIST=a.prod.test, b.prod.test');

        bioland_update_9082();

        $this->assertSame(
            ['a.prod.test', 'b.prod.test'],
            $this->config->get(BiolandDmsmConfigService::CONFIG_PROD_HOST_ALLOWLIST_KEY)
        );
    }

    /**
     * Nothing is written for the allowlist when the deployment names none.
     */
    public function testAllowlistIsLeftAbsentWhenTheDeploymentNamesNone()
    {
        Settings::setAll(['bioland_dmsm_config_base_url' => 'https://config.example.test']);

        bioland_update_9082();

        $this->assertNull(
            $this->config->get(BiolandDmsmConfigService::CONFIG_PROD_HOST_ALLOWLIST_KEY)
        );
    }

    /**
     * hook_requirements surfaces an unset base URL as an error, not silence.
     */
    public function testRequirementsReportsAnUnsetBaseUrl()
    {
        $service = $this->createMock(BiolandDmsmConfigService::class);
        $service->method('checkConfiguredBaseUrl')->willReturn([
            'status' => 'unset',
            'message' => 'bioland.settings.dmsm_config_base_url is not set.',
            'value' => '',
        ]);

        \Drupal::setService('bioland.dmsm_config', $service);

        $requirements = _bioland_dmsm_requirements('runtime');

        $this->assertArrayHasKey('bioland_dmsm_base_url', $requirements);
        $this->assertSame(REQUIREMENT_ERROR, $requirements['bioland_dmsm_base_url']['severity']);
        $this->assertStringContainsString('frozen', (string) $requirements['bioland_dmsm_base_url']['description']);
    }

    /**
     * hook_requirements surfaces a guard refusal as an error.
     */
    public function testRequirementsReportsARefusal()
    {
        $service = $this->createMock(BiolandDmsmConfigService::class);
        $service->method('checkConfiguredBaseUrl')->willReturn([
            'status' => 'refused',
            'message' => 'not in the production host allowlist',
            'value' => 'https://evil.example.com',
        ]);

        \Drupal::setService('bioland.dmsm_config', $service);

        $requirements = _bioland_dmsm_requirements('runtime');

        $this->assertSame(REQUIREMENT_ERROR, $requirements['bioland_dmsm_base_url']['severity']);
    }

    /**
     * A healthy configuration reports ok and raises no queue warning.
     */
    public function testRequirementsReportsOk()
    {
        $service = $this->createMock(BiolandDmsmConfigService::class);
        $service->method('checkConfiguredBaseUrl')->willReturn([
            'status' => 'ok',
            'message' => '',
            'value' => 'https://config.example.test',
        ]);

        \Drupal::setService('bioland.dmsm_config', $service);

        $requirements = _bioland_dmsm_requirements('runtime');

        $this->assertSame(REQUIREMENT_OK, $requirements['bioland_dmsm_base_url']['severity']);
        $this->assertArrayNotHasKey('bioland_dmsm_queue', $requirements);
    }

    /**
     * Requirements are a runtime-only concern.
     */
    public function testRequirementsAreRuntimeOnly()
    {
        $this->assertSame([], _bioland_dmsm_requirements('install'));
    }

    /**
     * The shared seed helper reports a missing value instead of throwing.
     *
     * bioland_install() relies on this so a fresh install never fails.
     */
    public function testSeedHelperReturnsNullWithoutThrowing()
    {
        $this->assertNull(_bioland_seed_dmsm_config_from_environment());
        $this->assertNull($this->config->get(BiolandDmsmConfigService::CONFIG_BASE_URL_KEY));
    }

    /**
     * The shared seed helper seeds once, then reports the key as already set.
     */
    public function testSeedHelperSeedsOnceFromTheEnvironment()
    {
        putenv('BIOLAND_DMSM_CONFIG_BASE_URL=https://config.env.test/');
        putenv('BIOLAND_DMSM_PROD_HOST_ALLOWLIST=config.env.test');

        $this->assertTrue(_bioland_seed_dmsm_config_from_environment());
        $this->assertSame(
            'https://config.env.test',
            $this->config->get(BiolandDmsmConfigService::CONFIG_BASE_URL_KEY)
        );
        $this->assertSame(
            ['config.env.test'],
            $this->config->get(BiolandDmsmConfigService::CONFIG_PROD_HOST_ALLOWLIST_KEY)
        );

        $this->assertFalse(_bioland_seed_dmsm_config_from_environment());
    }

    /**
     * Fresh installs and freshly-installed sites both get the seed.
     *
     * A fresh install marks bioland_update_9082() as run without executing
     * it, so bioland_install() must seed before it queues a geography fetch,
     * and bioland_update_9085() must repair sites installed before that.
     */
    public function testInstallAndLatestUpdateSeedTheConfig()
    {
        $root = __DIR__ . '/../..';

        $this->assertMatchesRegularExpression(
            '/function\s+bioland_install\s*\(\)\s*\{.*_bioland_seed_dmsm_config_from_environment\s*\(.*_bioland_update_countries_from_dmsm\s*\(/s',
            file_get_contents($root . '/bioland.install'),
            'bioland_install() must seed the DMSM config before queueing the countries update.'
        );
        $this->assertMatchesRegularExpression(
            '/function\s+bioland_update_9085\s*\([^)]*\)\s*\{[^}]*_bioland_seed_dmsm_config_or_fail\s*\(/s',
            file_get_contents($root . '/includes/bioland.install.focal_point.inc'),
            'bioland_update_9085() must seed the DMSM config on sites installed before bioland_install() did.'
        );
    }
}
