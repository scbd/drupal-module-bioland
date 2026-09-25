<?php

namespace Drupal\bioland;

/**
 * Decides which admin-menu links are duplicates and should be deleted.
 *
 * Pure logic with no Drupal dependencies so it can be unit tested standalone.
 * bioland_update_9087() feeds it plain descriptors built from the site's
 * menu_link_content entities in the 'admin' menu and deletes the ids it
 * returns (BL-836: duplicate "Content", "Content (pages)" and
 * "Media (attachments)" entries under Publishing > Add).
 *
 * Two rules, both matching on exact title + link uri:
 *
 * 1. Exact duplicates under one parent: links sharing title, uri AND parent
 *    are the same item repeated (e.g. "Content" -> /node/add/content created
 *    once per update hook 9048, 9051 and 9057). The lowest id is kept, the
 *    rest are deleted.
 * 2. Copies under "Add": a link under the "Add" parent whose title + uri also
 *    exists under a different parent is a stray copy of that other item, so
 *    the copy under "Add" is deleted and the other one is kept.
 */
final class BiolandAdminMenuDedupe {

  /**
   * Plugin id of the Publishing > "Add" admin menu parent.
   */
  public const ADD_PARENT = 'menu_link_content:059aed54-bd84-4699-a7c4-5358d5e7c36c';

  /**
   * Returns the ids of duplicate links to delete.
   *
   * @param array $links
   *   List of descriptors, each ['id' => int|string, 'title' => string,
   *   'uri' => string, 'parent' => string].
   * @param string $addParent
   *   Plugin id of the "Add" parent whose stray copies are removed.
   *
   * @return int[]
   *   Ids to delete, ascending, without duplicates.
   */
  public static function idsToDelete(array $links, string $addParent = self::ADD_PARENT): array {
    $delete = [];

    // Rule 1: exact duplicates under one parent keep the lowest id.
    $groups = [];
    foreach ($links as $link) {
      $key = self::key($link) . "\0" . (string) ($link['parent'] ?? '');
      $groups[$key][] = (int) $link['id'];
    }
    foreach ($groups as $ids) {
      if (count($ids) > 1) {
        sort($ids);
        array_shift($ids);
        foreach ($ids as $id) {
          $delete[$id] = TRUE;
        }
      }
    }

    // Rule 2: a copy under "Add" of an item that lives under another parent.
    $elsewhere = [];
    foreach ($links as $link) {
      if ((string) ($link['parent'] ?? '') !== $addParent) {
        $elsewhere[self::key($link)] = TRUE;
      }
    }
    foreach ($links as $link) {
      if ((string) ($link['parent'] ?? '') === $addParent && isset($elsewhere[self::key($link)])) {
        $delete[(int) $link['id']] = TRUE;
      }
    }

    $ids = array_keys($delete);
    sort($ids);
    return $ids;
  }

  /**
   * Builds the title + uri identity of a link.
   */
  private static function key(array $link): string {
    return trim((string) ($link['title'] ?? '')) . "\0" . trim((string) ($link['uri'] ?? ''));
  }

}
