<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\user\Entity\Role;
use PHPUnit\Framework\TestCase;

/**
 * Tests the BL-1274 image-to-embed media permission mirror.
 *
 * Covers _bioland_mirror_image_media_permissions_to_embed(): a role gets the
 * embed permission matching each image permission it holds, nothing else,
 * nothing is revoked, reruns are no-ops, and undefined permission names or a
 * missing media type skip cleanly.
 *
 * @group bioland
 */
class BiolandEmbedMediaPermissionsTest extends TestCase {

  private const DEFINED = [
    'create embed media',
    'edit own embed media',
    'edit any embed media',
    'delete own embed media',
    'delete any embed media',
  ];

  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once __DIR__ . '/../../includes/bioland.install.helpers.inc';
    require_once __DIR__ . '/../../includes/bioland.install.roles.inc';
  }

  protected function tearDown(): void {
    Role::resetStore();
    \Drupal::resetContainer();
    parent::tearDown();
  }

  /**
   * Registers the module handler, storages and permission handler doubles.
   *
   * @param array $roles
   *   Role id => permissions the role holds; all are saved.
   * @param array $defined
   *   Permission names the permission handler defines.
   */
  private function setUpSite(array $roles, array $defined = self::DEFINED, bool $media = TRUE, bool $embed_type = TRUE): void {
    $saved = [];
    foreach ($roles as $rid => $permissions) {
      $role = Role::create(['id' => $rid, 'permissions' => $permissions]);
      $role->save();
      $saved[$rid] = $role;
    }

    \Drupal::setService('module_handler', new class($media) {
      public function __construct(private bool $media) {}

      public function moduleExists($module) {
        return $module === 'media' && $this->media;
      }
    });
    \Drupal::setService('entity_type.manager', new class($saved, $embed_type) {
      public function __construct(private array $roles, private bool $embed) {}

      public function getStorage($entity_type_id) {
        return new class($entity_type_id, $this->roles, $this->embed) {
          public function __construct(private string $type, private array $roles, private bool $embed) {}

          public function load($id) {
            return $this->embed ? (object) ['id' => $id] : NULL;
          }

          public function loadMultiple() {
            return $this->roles;
          }
        };
      }
    });
    \Drupal::setService('user.permissions', new class($defined) {
      public function __construct(private array $defined) {}

      public function getPermissions() {
        return array_fill_keys($this->defined, ['title' => 'x']);
      }
    });
  }

  private function permissions(string $rid): array {
    $permissions = Role::load($rid)->getPermissions();
    sort($permissions);
    return $permissions;
  }

  public function testMirrorsHeldImagePermissionsOnly(): void {
    $this->setUpSite(['content_manager' => ['create image media', 'edit own image media']]);

    _bioland_mirror_image_media_permissions_to_embed();

    $this->assertSame(
      ['create embed media', 'create image media', 'edit own embed media', 'edit own image media'],
      $this->permissions('content_manager')
    );
  }

  public function testRoleWithoutImagePermissionsGetsNothing(): void {
    $this->setUpSite(['authenticated' => [], 'anonymous' => ['access content']]);

    _bioland_mirror_image_media_permissions_to_embed();

    $this->assertSame([], Role::load('authenticated')->getPermissions());
    $this->assertSame(1, Role::load('authenticated')->saveCount);
    $this->assertSame(['access content'], Role::load('anonymous')->getPermissions());
    $this->assertSame(1, Role::load('anonymous')->saveCount);
  }

  public function testRoleAlreadyHoldingEmbedSetIsUnchanged(): void {
    $this->setUpSite(['scbd_staff' => ['create image media', 'create embed media']]);

    $result = (string) _bioland_mirror_image_media_permissions_to_embed();

    $this->assertSame(1, Role::load('scbd_staff')->saveCount);
    $this->assertSame('Embed media permissions already mirror the image media permissions.', $result);
  }

  public function testRerunIsNoOp(): void {
    $this->setUpSite(['site_manager' => ['create image media', 'delete any image media']]);
    _bioland_mirror_image_media_permissions_to_embed();
    $saves = Role::load('site_manager')->saveCount;

    _bioland_mirror_image_media_permissions_to_embed();

    $this->assertSame($saves, Role::load('site_manager')->saveCount);
  }

  public function testUndefinedPermissionsAreNeverGranted(): void {
    $this->setUpSite(['content_manager' => ['create image media', 'view any image media revisions']]);

    _bioland_mirror_image_media_permissions_to_embed();

    $this->assertFalse(Role::load('content_manager')->hasPermission('view any embed media revisions'));
    $this->assertTrue(Role::load('content_manager')->hasPermission('create embed media'));
  }

  public function testDefinedRevisionPermissionsAreMirrored(): void {
    $this->setUpSite(
      ['content_manager' => ['view any image media revisions']],
      array_merge(self::DEFINED, ['view any embed media revisions'])
    );

    _bioland_mirror_image_media_permissions_to_embed();

    $this->assertTrue(Role::load('content_manager')->hasPermission('view any embed media revisions'));
  }

  public function testNothingIsRevoked(): void {
    $this->setUpSite(['contributor' => ['edit any embed media', 'access content']]);

    _bioland_mirror_image_media_permissions_to_embed();

    $this->assertSame(['access content', 'edit any embed media'], $this->permissions('contributor'));
  }

  public function testSkipsWithoutMediaModule(): void {
    $this->setUpSite(['content_manager' => ['create image media']], self::DEFINED, FALSE);

    $result = (string) _bioland_mirror_image_media_permissions_to_embed();

    $this->assertFalse(Role::load('content_manager')->hasPermission('create embed media'));
    $this->assertStringContainsString('media module is not enabled', $result);
  }

  public function testSkipsWithoutEmbedMediaType(): void {
    $this->setUpSite(['content_manager' => ['create image media']], self::DEFINED, TRUE, FALSE);

    $result = (string) _bioland_mirror_image_media_permissions_to_embed();

    $this->assertFalse(Role::load('content_manager')->hasPermission('create embed media'));
    $this->assertStringContainsString('Media type embed does not exist', $result);
  }

}
