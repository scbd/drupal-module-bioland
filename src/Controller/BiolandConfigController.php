<?php

namespace Drupal\bioland\Controller;

use Drupal\bioland\Access\BiolandConfigApiAccessCheck;
use Drupal\bioland\Service\BiolandConfigDocumentBuilder;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Serves the Bioland site configuration document as JSON.
 *
 * This is the module's first HTTP/JSON route. The conventions it establishes,
 * and the reasons for them:
 *
 * AUTHORIZATION. The route requires `_bioland_config_api`, a dedicated access
 * check (\Drupal\bioland\Access\BiolandConfigApiAccessCheck) backed by the
 * dedicated `access bioland config api` permission. The route is never open
 * to everyone: this document is not public, and anonymous holds no such
 * permission by default. A service account authenticates with a shared key.
 *
 * TRANSPORT OF THE KEY. The key travels ONLY in the `X-Bioland-Api-Key`
 * request header. A request that puts a key in the query string is refused
 * outright rather than silently accepted, because a query parameter is
 * written verbatim into web-server access logs, CDN logs and referrer
 * chains. The existing bioland-head query-string convention is the thing
 * this route exists not to copy; accepting both forms would make the leaky
 * one permanent.
 *
 * WHAT IS SERVED. An additively built, allowlisted projection — see
 * \Drupal\bioland\Service\BiolandConfigDocumentBuilder for the include-list,
 * the never-ship key names and the credential-shaped value scrubber. Nothing
 * outside the allowlist can reach the wire, and no whole config object is ever
 * copied.
 *
 * CACHING. The response carries `config:bioland.settings`,
 * `config:system.site`, `config:system.date`,
 * `config:configurable_language_list` and one
 * `config:language.config.<langcode>.system.site` tag per configured language,
 * so saving any of those — including renaming the site in one language —
 * invalidates Drupal's own caches automatically.
 *
 * CDN DECISION. Drupal cache tags invalidate Drupal's caches; they do not
 * purge a CDN, and this deployment has no purge integration. A tagged response
 * behind an un-purged edge would serve stale config for the edge TTL — the
 * exact failure this work removes. The response is therefore uncacheable four
 * times over, deliberately, because no single one of them should be the whole
 * defence for a key-authenticated body:
 * - the route is `no_cache: TRUE`, so Drupal's internal page cache — which
 *   does not vary on request headers, and could therefore replay a
 *   key-authenticated response to an unauthenticated caller — never stores it;
 * - the response's own cacheability declares max-age 0 and the contexts it
 *   really varies on (`user.permissions`, `headers:X-Bioland-Api-Key`), so a
 *   dynamic page cache or a render-cache consumer cannot store it either;
 * - it declares `Vary: X-Bioland-Api-Key`, so a shared proxy that ignores
 *   `Cache-Control` still has something telling it the body is key-dependent;
 * - this controller sets `Cache-Control: private, no-store`.
 *
 * Note on that last header: because the response policy denies caching, core's
 * FinishResponseSubscriber::onRespond() takes its not-cacheable branch and
 * REPLACES the header, so what production actually emits is
 * `Cache-Control: must-revalidate, no-cache, private`. That is equally
 * non-storable for a shared cache. The header set here is the intent stated at
 * the source, not the byte sequence on the wire; do not assert the literal
 * `private, no-store` against a real HTTP response.
 *
 * CORS IS A SITE-LEVEL PRECONDITION, NOT SOMETHING THIS ROUTE CAN ENFORCE. A
 * site running a permissive `cors.config` (wildcard origin with
 * `supportsCredentials: true`) turns a logged-in administrator's browser into
 * a key-free read path for this document, because the permission branch of the
 * access check authorizes on the session cookie. Any site exposing this route
 * must keep `cors.config` restrictive, or disable credentialed CORS entirely.
 */
class BiolandConfigController implements ContainerInjectionInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The document builder.
   *
   * @var \Drupal\bioland\Service\BiolandConfigDocumentBuilder
   */
  protected $builder;

  /**
   * The site directory path, e.g. "sites/example".
   *
   * @var string
   */
  protected $sitePath;

  /**
   * The logger channel, or NULL.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface|null
   */
  protected $logger;

  /**
   * Cache tags for the per-language system.site overrides that were read.
   *
   * @var string[]
   */
  protected $languageOverrideTags = [];

  /**
   * Constructs the controller.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager, TimeInterface $time, BiolandConfigDocumentBuilder $builder, string $site_path, ?LoggerChannelInterface $logger = NULL) {
    $this->configFactory = $config_factory;
    $this->languageManager = $language_manager;
    $this->time = $time;
    $this->builder = $builder;
    $this->sitePath = $site_path;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('datetime.time'),
      $container->get('bioland.config_document_builder'),
      (string) $container->getParameter('site.path'),
      $container->get('logger.factory')->get('bioland')
    );
  }

  /**
   * Returns the site configuration document.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The cache-tagged JSON response.
   */
  public function document() {
    $settings = $this->configFactory->get('bioland.settings')->get();
    $systemSite = $this->buildSystemSite();
    $document = $this->builder->build(
      is_array($settings) ? $settings : [],
      $systemSite,
      ['timezone' => ['default' => (string) ($this->configFactory->get('system.date')->get('timezone.default') ?? 'UTC')]],
      $this->siteCode(),
      $this->generatedTimestamp()
    );

    $response = new CacheableJsonResponse($document);
    $metadata = new CacheableMetadata();
    $metadata->setCacheTags(array_values(array_unique(array_merge([
      'config:bioland.settings',
      'config:system.site',
      'config:system.date',
      'config:configurable_language_list',
    ], $this->languageOverrideTags))));
    // The document enumerates every language explicitly, so it does not vary
    // by the request's interface language. It DOES vary by the caller's
    // authorization, and the response must say so itself rather than leaning
    // on the route's `no_cache` option as the single point of failure: an
    // empty context list plus the default permanent max-age declares
    // "cacheable forever, varies on nothing" about a key-authenticated body.
    $metadata->setCacheContexts(['user.permissions', 'headers:' . BiolandConfigApiAccessCheck::HEADER]);
    $metadata->setCacheMaxAge(0);
    $response->addCacheableDependency($metadata);
    $response->headers->set('Cache-Control', 'private, no-store');
    // Core replaces the Cache-Control header above on a not-cacheable
    // response, and never emits a Vary of its own here. A shared proxy
    // configured to ignore Cache-Control would then have nothing telling it
    // this body is key-dependent, so declare it explicitly.
    $response->setVary(BiolandConfigApiAccessCheck::HEADER, FALSE);

    return $response;
  }

  /**
   * Builds the `system.site` section, including per-language name overrides.
   *
   * Per-language names are read through the language config override system
   * rather than a raw config read, which is the only way to see them.
   *
   * Each override read is a read of the config object
   * `language.config.<langcode>.system.site`, which is a DIFFERENT object from
   * `system.site` and carries its own cache tag. Renaming the site in French
   * saves that object and nothing else, so without its tag the response would
   * never be invalidated. The tags are collected here, at the point of the
   * read, so a future read cannot be added without one.
   *
   * @return array
   *   The system.site section.
   */
  protected function buildSystemSite(): array {
    $this->languageOverrideTags = [];
    $section = [
      'name' => (string) ($this->configFactory->get('system.site')->get('name') ?? ''),
      'translations' => [],
    ];
    foreach (array_keys($this->languageManager->getLanguages()) as $langcode) {
      $this->languageOverrideTags[] = 'config:' . self::languageOverrideName((string) $langcode, 'system.site');
      $override = $this->languageManager->getLanguageConfigOverride($langcode, 'system.site');
      $name = $override ? $override->get('name') : NULL;
      if (is_string($name) && $name !== '') {
        $section['translations'][$langcode] = ['name' => $name];
      }
    }
    return $section;
  }

  /**
   * The config object name of one language's override of $name.
   *
   * @param string $langcode
   *   The language code.
   * @param string $name
   *   The overridden config object name.
   *
   * @return string
   *   The override config object name, which carries its own cache tag.
   */
  public static function languageOverrideName(string $langcode, string $name = 'system.site'): string {
    return 'language.config.' . $langcode . '.' . $name;
  }

  /**
   * Derives the site code from the site directory, e.g. "sites/abc" -> "abc".
   *
   * A multi-site install serving from `sites/default` yields "default", which
   * is not a Bioland site code and which p02-05 cannot resolve to a site. In
   * that case fall back to the `system.site` uuid, which is stable, unique per
   * install, and already cache-tagged here. Emitting an unresolvable code
   * silently is the one outcome this must not produce.
   *
   * @throws \RuntimeException
   *   When neither a real site directory nor a uuid is available, which means
   *   the install is broken rather than merely unconventional.
   */
  protected function siteCode(): string {
    $code = basename($this->sitePath);
    if ($code !== '' && $code !== 'default' && $code !== '.') {
      return $code;
    }
    $uuid = $this->configFactory->get('system.site')->get('uuid');
    if (is_string($uuid) && $uuid !== '') {
      if ($this->logger !== NULL) {
        $this->logger->warning('Bioland config document: site path @path yields no usable site code; falling back to the system.site uuid.', ['@path' => $this->sitePath]);
      }
      return $uuid;
    }
    throw new \RuntimeException(sprintf('Cannot derive a Bioland site code: site path "%s" is not a site directory and system.site has no uuid.', $this->sitePath));
  }

  /**
   * An ISO-8601 UTC timestamp with milliseconds, matching the contract.
   *
   * The milliseconds are always `.000`: the contract asks for the field, and
   * the request time this is derived from has one-second resolution. Consumers
   * must not read precision into the trailing zeros.
   */
  protected function generatedTimestamp(): string {
    return gmdate('Y-m-d\TH:i:s', $this->time->getRequestTime()) . '.000Z';
  }

}
