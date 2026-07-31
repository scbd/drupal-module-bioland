<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;
use Drupal\bioland\Service\BiolandFieldFunctionalityManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Language\LanguageInterface;

/**
 * Unit tests for BiolandFieldFunctionalityManager service.
 *
 * @covers \Drupal\bioland\Service\BiolandFieldFunctionalityManager
 */
class BiolandFieldFunctionalityManagerTest extends TestCase {

  /**
   * Creates a mock language manager for testing.
   *
   * @return \Drupal\Core\Language\LanguageManagerInterface
   *   A mock language manager.
   */
  protected function createMockLanguageManager(): LanguageManagerInterface {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('en');

    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $languageManager->method('getCurrentLanguage')->willReturn($language);
    $languageManager->method('getDefaultLanguage')->willReturn($language);

    return $languageManager;
  }

  /**
   * Tests isAnyFunctionalityEnabled returns true when field visibility is enabled.
   */
  public function testIsAnyFunctionalityEnabledWithFieldVisibility(): void {
    $configData = [
      'enable_field_visibility' => TRUE,
      'enable_additional_fields' => FALSE,
      'enable_auto_summary' => FALSE,
      'enable_help_comments' => FALSE,
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $manager = new BiolandFieldFunctionalityManager($configFactory);
    
    $this->assertTrue($manager->isAnyFunctionalityEnabled());
  }

  /**
   * Tests isAnyFunctionalityEnabled returns true when additional fields is enabled.
   */
  public function testIsAnyFunctionalityEnabledWithAdditionalFields(): void {
    $configData = [
      'enable_field_visibility' => FALSE,
      'enable_additional_fields' => TRUE,
      'enable_auto_summary' => FALSE,
      'enable_help_comments' => FALSE,
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $manager = new BiolandFieldFunctionalityManager($configFactory);
    
    $this->assertTrue($manager->isAnyFunctionalityEnabled());
  }

  /**
   * Tests isAnyFunctionalityEnabled returns true when auto summary is enabled.
   */
  public function testIsAnyFunctionalityEnabledWithAutoSummary(): void {
    $configData = [
      'enable_field_visibility' => FALSE,
      'enable_additional_fields' => FALSE,
      'enable_auto_summary' => TRUE,
      'enable_help_comments' => FALSE,
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $manager = new BiolandFieldFunctionalityManager($configFactory);
    
    $this->assertTrue($manager->isAnyFunctionalityEnabled());
  }

  /**
   * Tests isAnyFunctionalityEnabled returns true when help comments is enabled.
   */
  public function testIsAnyFunctionalityEnabledWithHelpComments(): void {
    $configData = [
      'enable_field_visibility' => FALSE,
      'enable_additional_fields' => FALSE,
      'enable_auto_summary' => FALSE,
      'enable_help_comments' => TRUE,
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $manager = new BiolandFieldFunctionalityManager($configFactory);
    
    $this->assertTrue($manager->isAnyFunctionalityEnabled());
  }

  /**
   * Tests isAnyFunctionalityEnabled returns false when all features are disabled.
   */
  public function testIsAnyFunctionalityEnabledReturnsFalseWhenAllDisabled(): void {
    $configData = [
      'enable_field_visibility' => FALSE,
      'enable_additional_fields' => FALSE,
      'enable_auto_summary' => FALSE,
      'enable_help_comments' => FALSE,
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $manager = new BiolandFieldFunctionalityManager($configFactory);
    
    $this->assertFalse($manager->isAnyFunctionalityEnabled());
  }

  /**
   * Tests getJavaScriptSettings returns expected structure.
   */
  public function testGetJavaScriptSettingsReturnsExpectedStructure(): void {
    $configData = [
      'enable_field_visibility' => TRUE,
      'enable_additional_fields' => TRUE,
      'enable_auto_summary' => TRUE,
      'enable_help_comments' => TRUE,
      'field_visibility_rules' => '{"rule": "value"}',
      'field_visibility' => [
        'url_content_types' => ['2' => '2', '3' => '3', '0' => 0],
        'published_content_types' => ['3' => '3', '5' => '5'],
        'date_range_content_types' => ['2' => '2'],
      ],
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $languageManager = $this->createMockLanguageManager();
    $manager = new BiolandFieldFunctionalityManager($configFactory, $languageManager);
    $settings = $manager->getJavaScriptSettings();
    
    $this->assertIsArray($settings);
    $this->assertTrue($settings['enableFieldVisibility']);
    $this->assertTrue($settings['enableAdditionalFields']);
    $this->assertTrue($settings['enableAutoSummary']);
    $this->assertTrue($settings['enableHelpComments']);
    $this->assertSame('{"rule": "value"}', $settings['fieldVisibilityRules']);
  }

  /**
   * Tests getJavaScriptSettings filters and converts content types correctly.
   */
  public function testGetJavaScriptSettingsFiltersContentTypes(): void {
    $configData = [
      'enable_field_visibility' => TRUE,
      'enable_additional_fields' => FALSE,
      'enable_auto_summary' => FALSE,
      'enable_help_comments' => FALSE,
      'field_visibility_rules' => '',
      'field_visibility' => [
        // Simulating checkbox values with 0 for unchecked.
        'url_content_types' => ['2' => '2', '3' => '3', '5' => 0, '12' => '12'],
        'published_content_types' => ['3' => '3', '5' => 0],
        'date_range_content_types' => ['2' => 0, '3' => '3'],
      ],
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $languageManager = $this->createMockLanguageManager();
    $manager = new BiolandFieldFunctionalityManager($configFactory, $languageManager);
    $settings = $manager->getJavaScriptSettings();
    
    // Should filter out 0 values and convert to integers.
    $this->assertSame([2, 3, 12], $settings['urlContentTypes']);
    $this->assertSame([3], $settings['publishedContentTypes']);
    $this->assertSame([3], $settings['dateRangeContentTypes']);
  }

  /**
   * Tests getJavaScriptSettings handles empty config values.
   */
  public function testGetJavaScriptSettingsHandlesEmptyValues(): void {
    $configData = [
      'enable_field_visibility' => NULL,
      'enable_additional_fields' => NULL,
      'enable_auto_summary' => NULL,
      'enable_help_comments' => NULL,
      'field_visibility_rules' => NULL,
      'field_visibility' => [
        'url_content_types' => NULL,
        'published_content_types' => NULL,
        'date_range_content_types' => NULL,
      ],
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $languageManager = $this->createMockLanguageManager();
    $manager = new BiolandFieldFunctionalityManager($configFactory, $languageManager);
    $settings = $manager->getJavaScriptSettings();
    
    // Default behavior: NULL !== FALSE should be TRUE.
    $this->assertTrue($settings['enableFieldVisibility']);
    $this->assertTrue($settings['enableAdditionalFields']);
    $this->assertTrue($settings['enableAutoSummary']);
    $this->assertTrue($settings['enableHelpComments']);
    $this->assertSame('', $settings['fieldVisibilityRules']);
    $this->assertSame([], $settings['urlContentTypes']);
    $this->assertSame([], $settings['publishedContentTypes']);
    $this->assertSame([], $settings['dateRangeContentTypes']);
  }

  /**
   * Tests getJavaScriptSettings with all features disabled.
   */
  public function testGetJavaScriptSettingsWithAllDisabled(): void {
    $configData = [
      'enable_field_visibility' => FALSE,
      'enable_additional_fields' => FALSE,
      'enable_auto_summary' => FALSE,
      'enable_help_comments' => FALSE,
      'field_visibility_rules' => '',
      'field_visibility' => [
        'url_content_types' => [],
        'published_content_types' => [],
        'date_range_content_types' => [],
      ],
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $languageManager = $this->createMockLanguageManager();
    $manager = new BiolandFieldFunctionalityManager($configFactory, $languageManager);
    $settings = $manager->getJavaScriptSettings();
    
    $this->assertFalse($settings['enableFieldVisibility']);
    $this->assertFalse($settings['enableAdditionalFields']);
    $this->assertFalse($settings['enableAutoSummary']);
    $this->assertFalse($settings['enableHelpComments']);
  }

  /**
   * Tests getJavaScriptSettings falls back to the fixed additional tag values.
   */
  public function testGetJavaScriptSettingsFallsBackToFixedAdditionalTags(): void {
    // No additional_tags key at all - the defaults must still reach the browser.
    $configObject = new ImmutableConfig('bioland.settings', []);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);

    $languageManager = $this->createMockLanguageManager();
    $manager = new BiolandFieldFunctionalityManager($configFactory, $languageManager);
    $settings = $manager->getJavaScriptSettings();

    $this->assertSame([
      'eventStatusContentTypes' => [3],
      'projectStatusContentTypes' => [5],
      'organizationTypesContentTypes' => [8],
      'ecosystemTypesContentTypes' => [9],
      'documentTypesContentTypes' => [12],
    ], $settings['additionalTags']);
  }

  /**
   * Tests getJavaScriptSettings reads the additional tag values from config.
   */
  public function testGetJavaScriptSettingsReadsAdditionalTagsFromConfig(): void {
    $configData = [
      'additional_tags' => [
        // Simulating stored checkbox values, including an unchecked 0.
        'event_status_content_types' => ['3' => '3'],
        'project_status_content_types' => ['5' => '5', '7' => 0],
        'organization_types_content_types' => ['8' => '8'],
        'ecosystem_types_content_types' => ['9' => '9'],
        'document_types_content_types' => ['12' => '12'],
      ],
    ];

    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);

    $languageManager = $this->createMockLanguageManager();
    $manager = new BiolandFieldFunctionalityManager($configFactory, $languageManager);
    $settings = $manager->getJavaScriptSettings();

    // Values are filtered, cast to integers, and reindexed.
    $this->assertSame([
      'eventStatusContentTypes' => [3],
      'projectStatusContentTypes' => [5],
      'organizationTypesContentTypes' => [8],
      'ecosystemTypesContentTypes' => [9],
      'documentTypesContentTypes' => [12],
    ], $settings['additionalTags']);
  }

}
