<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;
use Drupal\bioland\Plugin\Derivative\BiolandMenuLink;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Unit tests for BiolandMenuLink derivative plugin.
 *
 * @covers \Drupal\bioland\Plugin\Derivative\BiolandMenuLink
 */
class BiolandMenuLinkTest extends TestCase {

  /**
   * Tests getDerivativeDefinitions returns Bioland branding when not biosafety land.
   */
  public function testGetDerivativeDefinitionsReturnsBiolandBranding(): void {
    $configData = [
      'is_biosafety_land' => FALSE,
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $menuLink = new BiolandMenuLink($configFactory);
    
    $baseDefinition = [
      'id' => 'bioland.settings',
      'route_name' => 'bioland.settings',
      'parent' => 'system.admin_config',
    ];
    
    $derivatives = $menuLink->getDerivativeDefinitions($baseDefinition);
    
    $this->assertIsArray($derivatives);
    $this->assertArrayHasKey('bioland.settings', $derivatives);
    $this->assertSame('Bioland', $derivatives['bioland.settings']['title']);
  }

  /**
   * Tests getDerivativeDefinitions returns Biosafety Land branding when configured.
   */
  public function testGetDerivativeDefinitionsReturnsBiosafetyLandBranding(): void {
    $configData = [
      'is_biosafety_land' => TRUE,
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $menuLink = new BiolandMenuLink($configFactory);
    
    $baseDefinition = [
      'id' => 'bioland.settings',
      'route_name' => 'bioland.settings',
      'parent' => 'system.admin_config',
    ];
    
    $derivatives = $menuLink->getDerivativeDefinitions($baseDefinition);
    
    $this->assertIsArray($derivatives);
    $this->assertArrayHasKey('bioland.settings', $derivatives);
    $this->assertSame('Biosafety Land', $derivatives['bioland.settings']['title']);
  }

  /**
   * Tests getDerivativeDefinitions includes description with branding.
   */
  public function testGetDerivativeDefinitionsIncludesDescription(): void {
    $configData = [
      'is_biosafety_land' => FALSE,
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $menuLink = new BiolandMenuLink($configFactory);
    
    $baseDefinition = [];
    
    $derivatives = $menuLink->getDerivativeDefinitions($baseDefinition);
    
    $this->assertArrayHasKey('description', $derivatives['bioland.settings']);
    $this->assertStringContainsString('Bioland', $derivatives['bioland.settings']['description']);
    $this->assertStringContainsString('settings', $derivatives['bioland.settings']['description']);
  }

  /**
   * Tests getDerivativeDefinitions preserves base plugin definition.
   */
  public function testGetDerivativeDefinitionsPreservesBaseDefinition(): void {
    $configData = [
      'is_biosafety_land' => FALSE,
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $menuLink = new BiolandMenuLink($configFactory);
    
    $baseDefinition = [
      'route_name' => 'bioland.settings',
      'parent' => 'system.admin_config',
      'weight' => 10,
    ];
    
    $derivatives = $menuLink->getDerivativeDefinitions($baseDefinition);
    
    $this->assertSame('bioland.settings', $derivatives['bioland.settings']['route_name']);
    $this->assertSame('system.admin_config', $derivatives['bioland.settings']['parent']);
    $this->assertSame(10, $derivatives['bioland.settings']['weight']);
  }

  /**
   * Tests getDerivativeDefinitions handles null is_biosafety_land config.
   */
  public function testGetDerivativeDefinitionsHandlesNullConfig(): void {
    $configData = [
      'is_biosafety_land' => NULL,
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $menuLink = new BiolandMenuLink($configFactory);
    
    $baseDefinition = [];
    
    $derivatives = $menuLink->getDerivativeDefinitions($baseDefinition);
    
    // When NULL, should default to Bioland (falsy value).
    $this->assertSame('Bioland', $derivatives['bioland.settings']['title']);
  }

  /**
   * Tests getDerivativeDefinition returns specific derivative.
   */
  public function testGetDerivativeDefinitionReturnsSpecificDerivative(): void {
    $configData = [
      'is_biosafety_land' => FALSE,
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $menuLink = new BiolandMenuLink($configFactory);
    
    $baseDefinition = [
      'route_name' => 'bioland.settings',
    ];
    
    $derivative = $menuLink->getDerivativeDefinition('bioland.settings', $baseDefinition);
    
    $this->assertIsArray($derivative);
    $this->assertSame('Bioland', $derivative['title']);
  }

  /**
   * Tests getDerivativeDefinition returns null for non-existent derivative.
   */
  public function testGetDerivativeDefinitionReturnsNullForNonExistent(): void {
    $configData = [
      'is_biosafety_land' => FALSE,
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $menuLink = new BiolandMenuLink($configFactory);
    
    $baseDefinition = [];
    
    $derivative = $menuLink->getDerivativeDefinition('non.existent', $baseDefinition);
    
    $this->assertNull($derivative);
  }

}
