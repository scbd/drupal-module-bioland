<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\user\Entity\Role;
use PHPUnit\Framework\TestCase;

/**
 * Tests the BL-784 'delete any media' grant.
 *
 * Covers _bioland_grant_media_delete_permissions(), called from
 * bioland_update_9097() and bioland_install(): content manager and above get
 * the permission, reruns are no-ops, missing roles are skipped with a logged
 * notice, the grant is skipped without media, and lower roles are never
 * granted it.
 *
 * @group bioland
 */
class BiolandMediaDeletePermissionsTest extends TestCase {

  private const PERMISSION = 'delete any media';

  private const GRANTED_ROLES = ['content_manager', 'site_manager', 'scbd_staff', 'administrator'];

  private const NEVER_GRANTED_ROLES = ['contributor', 'system', 'authenticated', 'anonymous'];

  /**
   * Notices logged to the bioland channel.
   *
   * @var array
   */
  private array $notices = [];

  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once __DIR__ . '/../../includes/bioland.install.roles.inc';
  }

  protected function setUp(): void {
    parent::setUp();

    $this->setMediaEnabled(TRUE);

    $this->notices = [];
    $logger = new class($this->notices) {
      private array $notices;

      public function __construct(array &$notices) {
        $this->notices = &$notices;
      }

      public function notice($message, array $context = []) {
        $this->notices[] = strtr($message, $context);
      }
    };
    $factory = new class($logger) {
      public function __construct(private object $logger) {}

      public function get($channel) {
        return $this->logger;
      }
    };
    \Drupal::setService('logger.factory', $factory);
  }

  protected function tearDown(): void {
    Role::resetStore();
    \Drupal::resetContainer();
    parent::tearDown();
  }

  /**
   * Registers a module handler reporting whether media is enabled.
   */
  private function setMediaEnabled(bool $enabled): void {
    \Drupal::setService('module_handler', new class($enabled) {
      public function __construct(private bool $enabled) {}

      public function moduleExists($module) {
        return $module === 'media' && $this->enabled;
      }
    });
  }

  /**
   * Seeds saved roles with no permissions.
   */
  private function seedRoles(array $rids): void {
    foreach ($rids as $rid) {
      Role::create(['id' => $rid])->save();
    }
  }

  public function testGrantsContentManagerAndAbove(): void {
    $this->seedRoles(array_merge(self::GRANTED_ROLES, self::NEVER_GRANTED_ROLES));

    $result = (string) _bioland_grant_media_delete_permissions();

    foreach (self::GRANTED_ROLES as $rid) {
      $this->assertTrue(Role::load($rid)->hasPermission(self::PERMISSION), "$rid must be granted '" . self::PERMISSION . "'.");
      $this->assertStringContainsString($rid, $result);
    }
    $this->assertSame([], $this->notices);
  }

  public function testRerunIsNoOp(): void {
    $this->seedRoles(self::GRANTED_ROLES);
    _bioland_grant_media_delete_permissions();

    $saves = [];
    foreach (self::GRANTED_ROLES as $rid) {
      $saves[$rid] = Role::load($rid)->saveCount;
    }

    $result = (string) _bioland_grant_media_delete_permissions();

    foreach (self::GRANTED_ROLES as $rid) {
      $this->assertSame($saves[$rid], Role::load($rid)->saveCount, "$rid must not be re-saved on rerun.");
      $this->assertSame([self::PERMISSION], Role::load($rid)->getPermissions());
    }
    $this->assertSame('delete any media already granted to content manager and above.', $result);
  }

  public function testMissingRoleIsSkippedAndLogged(): void {
    $this->seedRoles(['content_manager', 'administrator']);

    $result = (string) _bioland_grant_media_delete_permissions();

    $this->assertTrue(Role::load('content_manager')->hasPermission(self::PERMISSION));
    $this->assertTrue(Role::load('administrator')->hasPermission(self::PERMISSION));
    $this->assertNull(Role::load('site_manager'));
    $this->assertNull(Role::load('scbd_staff'));
    $this->assertSame(['Skipped granting delete any media to missing roles: site_manager, scbd_staff.'], $this->notices);
    $this->assertStringContainsString('Skipped missing roles: site_manager, scbd_staff.', $result);
  }

  public function testLowerRolesAreNeverGranted(): void {
    $this->seedRoles(array_merge(self::GRANTED_ROLES, self::NEVER_GRANTED_ROLES));

    _bioland_grant_media_delete_permissions();
    _bioland_grant_media_delete_permissions();

    foreach (self::NEVER_GRANTED_ROLES as $rid) {
      $this->assertFalse(Role::load($rid)->hasPermission(self::PERMISSION), "$rid must never be granted '" . self::PERMISSION . "'.");
      $this->assertSame(1, Role::load($rid)->saveCount, "$rid must never be re-saved.");
    }
  }

  public function testSkipsWhenMediaModuleIsDisabled(): void {
    $this->seedRoles(self::GRANTED_ROLES);
    $this->setMediaEnabled(FALSE);

    $result = (string) _bioland_grant_media_delete_permissions();

    foreach (self::GRANTED_ROLES as $rid) {
      $this->assertFalse(Role::load($rid)->hasPermission(self::PERMISSION));
      $this->assertSame(1, Role::load($rid)->saveCount);
    }
    $this->assertSame('Media module not enabled; delete any media not granted.', $result);
  }

  public function testStandardMatrixIsUnchanged(): void {
    foreach (_bioland_get_standard_permission_matrix() as $rid => $permissions) {
      $this->assertNotContains(self::PERMISSION, $permissions, "The standard matrix for $rid must stay as it was; the grant lives in its own helper.");
    }
  }

}
