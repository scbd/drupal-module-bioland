<?php

namespace Drupal\bioland\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

/**
 * Authorizes the Bioland config API route.
 *
 * Two ways in, in this order:
 * 1. A shared service-account key presented in the `X-Bioland-Api-Key`
 *    request header and compared with `hash_equals()` against
 *    `$settings['bioland_config_api_key']` from settings.php. The key lives in
 *    settings.php rather than config so it is never exported, never part of a
 *    config diff, and can never be reflected back by the config API itself.
 * 2. Otherwise the account's own `access bioland config api` permission.
 *    Anonymous does not hold it by default, so an unauthenticated caller with
 *    no valid header is refused. This is never `_access: 'TRUE'`.
 *
 * A key supplied in the QUERY STRING is refused outright, even if it is the
 * correct key. Query parameters are written verbatim into web-server and CDN
 * access logs and travel in referrer chains; accepting the query form
 * alongside the header form would make the leaky form permanent.
 *
 * Every result is uncacheable (max-age 0) so no authorization decision is
 * stored and replayed to a different caller.
 */
class BiolandConfigApiAccessCheck implements AccessInterface {

  /**
   * The only accepted transport for the api key.
   */
  public const HEADER = 'X-Bioland-Api-Key';

  /**
   * The settings.php key holding the shared service-account key.
   */
  public const SETTING = 'bioland_config_api_key';

  /**
   * Query parameter names that are refused rather than honoured.
   */
  public const REJECTED_QUERY_PARAMS = ['api-key', 'api_key', 'apikey'];

  /**
   * The permission a non-key caller must hold.
   */
  public const PERMISSION = 'access bioland config api';

  /**
   * The site settings.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * Constructs the access check.
   */
  public function __construct(Settings $settings) {
    $this->settings = $settings;
  }

  /**
   * Checks access to the config API route.
   *
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The current request, or NULL. Core passes NULL when access is checked
   *   outside an incoming request — a route access check run from the CLI, or
   *   a link-access check during rendering. With no request there is no header
   *   to authenticate and no query string to refuse, so the only safe answer
   *   is forbidden. Typing this non-nullable instead produces a TypeError,
   *   which is a WSOD rather than a bypass, but a WSOD all the same.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account making the request.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(?Request $request, AccountInterface $account): AccessResultInterface {
    if ($request === NULL) {
      return $this->uncacheable(AccessResult::forbidden('The Bioland config api is only reachable through an HTTP request carrying the ' . self::HEADER . ' header.'));
    }

    // Case-insensitive: array_change_key_case() so ?apiKey=, ?API_KEY= or
    // ?Api-Key= are refused exactly like their canonical spelling. Without
    // this a differently-cased query key would still land in web-server/CDN
    // access logs and referrer chains, defeating the whole point of refusing
    // it outright.
    $lowercasedQueryKeys = array_change_key_case($request->query->all(), CASE_LOWER);
    foreach (self::REJECTED_QUERY_PARAMS as $param) {
      if (array_key_exists($param, $lowercasedQueryKeys)) {
        return $this->uncacheable(AccessResult::forbidden('The Bioland config api key must be sent in the ' . self::HEADER . ' request header, never in the query string.'));
      }
    }

    $expected = $this->settings->get(self::SETTING);
    $provided = $request->headers->get(self::HEADER);
    if (is_string($expected) && $expected !== '' && is_string($provided) && $provided !== '' && hash_equals($expected, $provided)) {
      return $this->uncacheable(AccessResult::allowed());
    }

    return $this->uncacheable(AccessResult::allowedIfHasPermission($account, self::PERMISSION));
  }

  /**
   * Marks a result uncacheable and declares what it varies on.
   */
  protected function uncacheable(AccessResultInterface $result): AccessResultInterface {
    return $result
      ->addCacheContexts(['headers:' . self::HEADER, 'url.query_args', 'user.permissions'])
      ->setCacheMaxAge(0);
  }

}
