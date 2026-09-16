<?php

namespace Drupal\Tests\bioland\Unit\Access;

use Drupal\bioland\Access\BiolandConfigApiAccessCheck;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Covers authorization of the Bioland config API route.
 *
 * @coversDefaultClass \Drupal\bioland\Access\BiolandConfigApiAccessCheck
 */
class BiolandConfigApiAccessCheckTest extends TestCase {

  /**
   * The fake service-account key used throughout. Obviously not a real key.
   */
  private const FAKE_KEY = 'fake-service-account-key-for-tests';

  /**
   * Builds the access check with a configured key.
   *
   * @param string|null $key
   *   The configured key, or NULL for an unconfigured site.
   *
   * @return \Drupal\bioland\Access\BiolandConfigApiAccessCheck
   *   The access check.
   */
  protected function check($key = self::FAKE_KEY) {
    $settings = $key === NULL ? new Settings([]) : new Settings([BiolandConfigApiAccessCheck::SETTING => $key]);
    return new BiolandConfigApiAccessCheck($settings);
  }

  /**
   * Builds an account mock.
   *
   * @param bool $has_permission
   *   Whether the account holds the API permission.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The account.
   */
  protected function account(bool $has_permission) {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn($has_permission);
    $account->method('id')->willReturn($has_permission ? 1 : 0);
    return $account;
  }

  /**
   * A correct key in the header authorizes an otherwise anonymous caller.
   */
  public function testValidHeaderKeyIsAllowed() {
    $request = new Request([], [BiolandConfigApiAccessCheck::HEADER => self::FAKE_KEY]);
    $result = $this->check()->access($request, $this->account(FALSE));

    $this->assertTrue($result->isAllowed());
  }

  /**
   * The header name is matched case-insensitively, as HTTP requires.
   */
  public function testHeaderLookupIsCaseInsensitive() {
    $request = new Request([], ['x-bioland-api-key' => self::FAKE_KEY]);
    $this->assertTrue($this->check()->access($request, $this->account(FALSE))->isAllowed());
  }

  /**
   * An unauthorized caller — no key, no permission — is refused.
   */
  public function testUnauthorizedCallerIsRefused() {
    $request = new Request();
    $result = $this->check()->access($request, $this->account(FALSE));

    $this->assertFalse($result->isAllowed());
  }

  /**
   * A wrong key does not fall through to access.
   */
  public function testWrongHeaderKeyIsRefused() {
    $request = new Request([], [BiolandConfigApiAccessCheck::HEADER => 'not-the-key']);
    $this->assertFalse($this->check()->access($request, $this->account(FALSE))->isAllowed());
  }

  /**
   * An empty configured key never authorizes, even against an empty header.
   */
  public function testUnconfiguredKeyNeverAuthorizes() {
    foreach ([NULL, ''] as $configured) {
      $request = new Request([], [BiolandConfigApiAccessCheck::HEADER => (string) $configured]);
      $this->assertFalse($this->check($configured)->access($request, $this->account(FALSE))->isAllowed());
    }
  }

  /**
   * A key in the query string is forbidden, even when it is the correct key.
   */
  public function testQueryStringKeyIsRejected() {
    foreach (BiolandConfigApiAccessCheck::REJECTED_QUERY_PARAMS as $param) {
      $request = new Request([$param => self::FAKE_KEY], [BiolandConfigApiAccessCheck::HEADER => self::FAKE_KEY]);
      $result = $this->check()->access($request, $this->account(TRUE));

      $this->assertTrue($result->isForbidden(), "A key in ?$param= must be forbidden outright.");
      $this->assertFalse($result->isAllowed());
      $this->assertStringContainsString(BiolandConfigApiAccessCheck::HEADER, (string) $result->getReason());
    }
  }

  /**
   * A caller holding the dedicated permission is allowed without a key.
   */
  public function testPermissionFallbackAllows() {
    $request = new Request();
    $this->assertTrue($this->check()->access($request, $this->account(TRUE))->isAllowed());
  }

  /**
   * Access results are uncacheable and declare what they vary on.
   */
  public function testResultsAreUncacheableAndVaryCorrectly() {
    $result = $this->check()->access(new Request(), $this->account(FALSE));

    $this->assertSame(0, $result->getCacheMaxAge());
    $this->assertContains('headers:' . BiolandConfigApiAccessCheck::HEADER, $result->getCacheContexts());
    $this->assertContains('user.permissions', $result->getCacheContexts());
  }

}
