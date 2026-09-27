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
 * Two rules, both matching on exact (trimmed) title + link uri:
 *
 * 1. Exact duplicates under one parent: links sharing title, uri AND parent
 *    are the same item repeated (e.g. "Content" -> /node/add/content created
 *    once per update hook 9048, 9051 and 9057). The lowest-id ENABLED link is
 *    kept (the lowest id when none is enabled), the rest are deleted.
 * 2. Copies under "Add": a link under the "Add" parent whose title is in
 *    ADD_COPY_TITLES and whose title + uri also exists, ENABLED, under a
 *    different parent is a stray copy of that other item, so the copy under
 *    "Add" is deleted and the other one is kept. Rule 2 is limited to that
 *    allowlist on purpose so it never touches unrelated links under "Add".
 *
 * The "Content" -> /node/add/content link under "Add" is retired outright
 * (see retiredAddLinkIds(), used by bioland_update_9092()), leaving
 * Publishing with just "Content (pages)" and "Media (attachments)".
 *
 * Descriptors without an 'enabled' key are treated as enabled.
 */
final class BiolandAdminMenuDedupe {

  /**
   * Plugin id of the Publishing > "Add" admin menu parent.
   */
  public const ADD_PARENT = 'menu_link_content:059aed54-bd84-4699-a7c4-5358d5e7c36c';

  /**
   * Titles whose stray copies under "Add" rule 2 may delete.
   */
  public const ADD_COPY_TITLES = ['Content (pages)', 'Media (attachments)'];

  /**
   * Title + uri of the retired Publishing > Add > Content link.
   */
  public const RETIRED_ADD_LINK = ['title' => 'Content', 'uri' => 'internal:/node/add/content'];

  /**
   * Returns the ids of retired "Content" links under "Add", every copy.
   *
   * @param array $links
   *   Descriptors, as for idsToDelete().
   * @param string $addParent
   *   Plugin id of the "Add" parent.
   *
   * @return int[]
   *   Ids to delete, ascending.
   */
  public static function retiredAddLinkIds(array $links, string $addParent = self::ADD_PARENT): array {
    $retired = self::key(self::RETIRED_ADD_LINK);
    $ids = [];
    foreach ($links as $link) {
      if ((string) ($link['parent'] ?? '') === $addParent && self::key($link) === $retired) {
        $ids[] = (int) $link['id'];
      }
    }
    sort($ids);
    return $ids;
  }

  /**
   * Returns the ids of duplicate links to delete.
   *
   * @param array $links
   *   List of descriptors, each ['id' => int|string, 'title' => string,
   *   'uri' => string, 'parent' => string, 'enabled' => bool].
   * @param string $addParent
   *   Plugin id of the "Add" parent whose stray copies are removed.
   *
   * @return int[]
   *   Ids to delete, ascending, without duplicates.
   */
  public static function idsToDelete(array $links, string $addParent = self::ADD_PARENT): array {
    return array_keys(self::plan($links, $addParent));
  }

  /**
   * Returns each id to delete mapped to the id of the copy that survives.
   *
   * The survivor is where children of a deleted link should be re-parented.
   * It is NULL when no surviving copy is known.
   *
   * @param array $links
   *   Descriptors, as for idsToDelete().
   * @param string $addParent
   *   Plugin id of the "Add" parent whose stray copies are removed.
   *
   * @return array<int, int|null>
   *   Delete id => survivor id, sorted by delete id.
   */
  public static function plan(array $links, string $addParent = self::ADD_PARENT): array {
    $delete = [];

    // Rule 1: exact duplicates under one parent keep the lowest enabled id.
    $groups = [];
    foreach ($links as $link) {
      $key = self::key($link) . "\0" . (string) ($link['parent'] ?? '');
      $groups[$key][] = $link;
    }
    foreach ($groups as $group) {
      if (count($group) < 2) {
        continue;
      }
      usort($group, [self::class, 'compareKeepFirst']);
      $keep = (int) array_shift($group)['id'];
      foreach ($group as $link) {
        $delete[(int) $link['id']] = $keep;
      }
    }

    // Rule 2: an allowlisted copy under "Add" of an item that lives, enabled,
    // under another parent. The survivor is the lowest surviving enabled copy.
    $elsewhere = [];
    foreach ($links as $link) {
      $id = (int) $link['id'];
      if ((string) ($link['parent'] ?? '') !== $addParent && self::enabled($link) && !isset($delete[$id])) {
        $key = self::key($link);
        $elsewhere[$key] = isset($elsewhere[$key]) ? min($elsewhere[$key], $id) : $id;
      }
    }
    foreach ($links as $link) {
      $key = self::key($link);
      if ((string) ($link['parent'] ?? '') === $addParent
        && in_array(trim((string) ($link['title'] ?? '')), self::ADD_COPY_TITLES, TRUE)
        && isset($elsewhere[$key])) {
        $delete[(int) $link['id']] = $elsewhere[$key];
      }
    }

    ksort($delete);
    return $delete;
  }

  /**
   * Orders links so the one to keep comes first: enabled, then lowest id.
   */
  private static function compareKeepFirst(array $a, array $b): int {
    return [self::enabled($a) ? 0 : 1, (int) $a['id']] <=> [self::enabled($b) ? 0 : 1, (int) $b['id']];
  }

  /**
   * Whether a descriptor is enabled (missing key counts as enabled).
   */
  private static function enabled(array $link): bool {
    return !array_key_exists('enabled', $link) || (bool) $link['enabled'];
  }

  /**
   * Builds the title + uri identity of a link.
   */
  private static function key(array $link): string {
    return trim((string) ($link['title'] ?? '')) . "\0" . trim((string) ($link['uri'] ?? ''));
  }

}
