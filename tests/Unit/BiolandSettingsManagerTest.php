<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;
use Drupal\bioland\Service\BiolandSettingsManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Unit tests for BiolandSettingsManager service.
 *
 * @covers \Drupal\bioland\Service\BiolandSettingsManager
 */
class BiolandSettingsManagerTest extends TestCase {

  /**
   * Tests that getConfig returns the config object.
   */
  public function testGetConfigReturnsConfigObject(): void {
    $configData = [
      'countries' => ['us', 'ca'],
      'region' => 'north_america',
      'is_biosafety_land' => FALSE,
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->expects($this->once())
      ->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $manager = new BiolandSettingsManager($configFactory);
    $result = $manager->getConfig();
    
    $this->assertInstanceOf(ImmutableConfig::class, $result);
    $this->assertSame('bioland.settings', $result->getName());
  }

  /**
   * Tests that get returns a specific config value.
   */
  public function testGetReturnsSpecificValue(): void {
    $configData = [
      'countries' => ['us', 'ca'],
      'region' => 'north_america',
      'is_biosafety_land' => FALSE,
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $manager = new BiolandSettingsManager($configFactory);
    
    $this->assertSame(['us', 'ca'], $manager->get('countries'));
    $this->assertSame('north_america', $manager->get('region'));
    $this->assertFalse($manager->get('is_biosafety_land'));
  }

  /**
   * Tests that get returns default when key is not found.
   */
  public function testGetReturnsDefaultForMissingKey(): void {
    $configData = [
      'region' => 'europe',
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $manager = new BiolandSettingsManager($configFactory);
    
    $this->assertNull($manager->get('non_existent_key'));
    $this->assertSame('default_value', $manager->get('non_existent_key', 'default_value'));
  }

  /**
   * Tests that get returns null when value is null and no default.
   */
  public function testGetReturnsNullWhenValueIsNullAndNoDefault(): void {
    $configData = [
      'nullable_key' => NULL,
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $manager = new BiolandSettingsManager($configFactory);
    
    // When key exists but value is null, should return the default.
    $this->assertSame('fallback', $manager->get('nullable_key', 'fallback'));
  }

  /**
   * Tests nested config values using dot notation.
   */
  public function testGetNestedConfigValue(): void {
    $configData = [
      'translation' => [
        'auto_create' => TRUE,
        'target_languages' => ['en', 'fr', 'es'],
      ],
      'field_visibility' => [
        'url_content_types' => [2, 3, 5],
      ],
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $manager = new BiolandSettingsManager($configFactory);
    
    $this->assertTrue($manager->get('translation.auto_create'));
    $this->assertSame(['en', 'fr', 'es'], $manager->get('translation.target_languages'));
    $this->assertSame([2, 3, 5], $manager->get('field_visibility.url_content_types'));
  }

  /**
   * Tests that get handles boolean false correctly.
   */
  public function testGetHandlesBooleanFalse(): void {
    $configData = [
      'enable_feature' => FALSE,
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $manager = new BiolandSettingsManager($configFactory);
    
    // Should return FALSE, not the default value.
    $this->assertFalse($manager->get('enable_feature', TRUE));
  }

  /**
   * Tests that get handles empty arrays correctly.
   */
  public function testGetHandlesEmptyArrays(): void {
    $configData = [
      'empty_array' => [],
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $manager = new BiolandSettingsManager($configFactory);
    
    // Empty array is not null, so should return the empty array.
    $this->assertSame([], $manager->get('empty_array', ['default']));
  }

}
