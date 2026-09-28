<?php

namespace Drupal\Tests\bioland\Unit\Plugin\Validation\Constraint;

use Drupal\bioland\Plugin\Validation\Constraint\BiolandEmbedAllowedOriginConstraint;
use Drupal\bioland\Plugin\Validation\Constraint\BiolandEmbedAllowedOriginConstraintValidator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * Tests the BL-1218 embed URL allowlist constraint.
 *
 * @coversDefaultClass \Drupal\bioland\Plugin\Validation\Constraint\BiolandEmbedAllowedOriginConstraintValidator
 * @group bioland
 */
class BiolandEmbedAllowedOriginConstraintValidatorTest extends TestCase {

  private const ORIGINS = [
    ['url' => 'https://app.powerbi.com/view', 'label' => 'Power BI', 'sandbox' => ''],
    ['url' => 'https://www.youtube.com', 'label' => 'YouTube', 'sandbox' => ''],
    ['url' => 'https://www.youtube.com/embed', 'label' => 'YouTube again', 'sandbox' => ''],
  ];

  /**
   * Runs the validator over the URLs and returns the violations it raised.
   *
   * @return array[]
   *   One ['message' => ..., 'path' => ..., 'params' => [...]] per violation.
   */
  private function violations(array $urls, mixed $origins): array {
    $settings = $origins === NULL ? [] : ['embed' => ['allowed_origins' => $origins]];
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('bioland.settings')->willReturn(new ImmutableConfig('bioland.settings', $settings));

    $violations = [];
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->method('buildViolation')->willReturnCallback(function ($message) use (&$violations) {
      $violation = ['message' => $message, 'path' => NULL, 'params' => []];
      $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
      $builder->method('setParameter')->willReturnCallback(function ($key, $value) use (&$violation, &$builder) {
        $violation['params'][$key] = $value;
        return $builder;
      });
      $builder->method('atPath')->willReturnCallback(function ($path) use (&$violation, &$builder) {
        $violation['path'] = $path;
        return $builder;
      });
      $builder->method('addViolation')->willReturnCallback(function () use (&$violation, &$violations) {
        $violations[] = $violation;
      });
      return $builder;
    });

    $validator = new BiolandEmbedAllowedOriginConstraintValidator($factory);
    $validator->initialize($context);
    $items = array_map(fn ($url) => (object) ['url' => $url], $urls);
    $validator->validate($items, new BiolandEmbedAllowedOriginConstraint());
    return $violations;
  }

  /**
   * An allowed PowerBI publish-to-web URL raises nothing.
   */
  public function testAllowedPowerBiUrlPasses(): void {
    $this->assertSame([], $this->violations(['https://app.powerbi.com/view?r=eyJrIjoiYWJjIn0%3D'], self::ORIGINS));
  }

  /**
   * An empty URL is left to the field's own required check.
   */
  public function testEmptyUrlIsSkipped(): void {
    $this->assertSame([], $this->violations(['', '  '], self::ORIGINS));
  }

  /**
   * Near misses fail on their own delta, with the message for their cause.
   *
   * @dataProvider rejectedProvider
   */
  public function testRejected(string $url, string $message_property): void {
    $violations = $this->violations(['https://www.youtube.com/embed/x', $url], self::ORIGINS);
    $this->assertCount(1, $violations);
    $this->assertSame((new BiolandEmbedAllowedOriginConstraint())->{$message_property}, $violations[0]['message']);
    $this->assertSame('1.url', $violations[0]['path']);
    $this->assertSame($url, $violations[0]['params']['%url']);
    $this->assertSame('https://app.powerbi.com/view, https://www.youtube.com, https://www.youtube.com/embed', $violations[0]['params']['%allowed']);
    $this->assertSame(BiolandEmbedAllowedOriginConstraintValidator::SETTINGS_PATH, $violations[0]['params']['@settings']);
  }

  /**
   * URLs that must not pass, and the message property each one gets.
   */
  public static function rejectedProvider(): array {
    return [
      'path not on segment boundary' => ['https://app.powerbi.com/view-evil', 'pathMessage'],
      'dot segment on allowed host' => ['https://app.powerbi.com/view/../x', 'pathMessage'],
      'lookalike host' => ['https://app.powerbi.com.evil.example/view', 'message'],
      'lookalike youtube' => ['https://evil-youtube.com/embed/x', 'message'],
      'userinfo' => ['https://attacker@app.powerbi.com/view', 'message'],
      'relative' => ['/view', 'message'],
    ];
  }

  /**
   * A missing or empty list rejects every URL, and says none are allowed.
   *
   * @dataProvider emptyListProvider
   */
  public function testMissingOrEmptyListRejectsEverything(mixed $origins): void {
    $violations = $this->violations(['https://app.powerbi.com/view', 'https://www.youtube.com/embed/x'], $origins);
    $this->assertCount(2, $violations);
    $this->assertSame('none', $violations[0]['params']['%allowed']);
    $this->assertSame((new BiolandEmbedAllowedOriginConstraint())->message, $violations[0]['message']);
  }

  /**
   * Missing, empty and malformed lists.
   */
  public static function emptyListProvider(): array {
    return [
      'missing' => [NULL],
      'empty' => [[]],
      'not a list' => ['https://app.powerbi.com/view'],
      'invalid entries only' => [[['url' => 'nope'], 'nope']],
    ];
  }

  /**
   * create() pulls the config factory from the container.
   */
  public function testCreateUsesConfigFactory(): void {
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->expects($this->once())->method('get')->with('bioland.settings')
      ->willReturn(new ImmutableConfig('bioland.settings', []));
    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->once())->method('get')->with('config.factory')->willReturn($factory);

    $validator = BiolandEmbedAllowedOriginConstraintValidator::create($container);
    $this->assertInstanceOf(BiolandEmbedAllowedOriginConstraintValidator::class, $validator);
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->never())->method('buildViolation');
    $validator->initialize($context);
    $validator->validate([], new BiolandEmbedAllowedOriginConstraint());
  }

  /**
   * The plugin attribute id is the one the bundle field alter attaches.
   */
  public function testPluginIdMatchesAttribute(): void {
    $attributes = (new \ReflectionClass(BiolandEmbedAllowedOriginConstraint::class))->getAttributes();
    $this->assertCount(1, $attributes);
    $this->assertSame(BiolandEmbedAllowedOriginConstraint::PLUGIN_ID, $attributes[0]->getArguments()['id']);
  }

  /**
   * Both messages list the allowed entries; the host one also names the
   * settings form and the framing requirement.
   */
  public function testMessages(): void {
    $constraint = new BiolandEmbedAllowedOriginConstraint();
    foreach ([$constraint->message, $constraint->pathMessage] as $message) {
      $this->assertStringContainsString('%url', $message);
      $this->assertStringContainsString('It must start with one of: %allowed.', $message);
    }
    $this->assertStringContainsString('not on an allowed embed host', $constraint->message);
    $this->assertStringContainsString('Front End General settings (@settings)', $constraint->message);
    $this->assertStringContainsString('allow being framed', $constraint->message);
    $this->assertStringContainsString('on an allowed embed host but not under an allowed path', $constraint->pathMessage);
  }

}
