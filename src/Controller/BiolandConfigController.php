<?php

namespace Drupal\bioland\Controller;

use Drupal\bioland\Service\BiolandConfigDocumentBuilder;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Language\LanguageManagerInterface;
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
 * `config:system.site`, `config:system.date` and
 * `config:configurable_language_list` cache tags, so saving any of those
 * invalidates Drupal's own caches automatically.
 *
 * CDN DECISION. Drupal cache tags invalidate Drupal's caches; they do not
 * purge a CDN, and this deployment has no purge integration. A tagged response
 * behind an un-purged edge would serve stale config for the edge TTL — the
 * exact failure this work removes. The response is therefore marked
 * `Cache-Control: private, no-store` and the route is `no_cache: TRUE`, so
 * neither a CDN nor Drupal's internal page cache stores it. The internal page
 * cache is additionally unsafe here because it does not vary on request
 * headers: a cached key-authenticated response could otherwise be replayed to
 * an unauthenticated caller.
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
   * Constructs the controller.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager, TimeInterface $time, BiolandConfigDocumentBuilder $builder, string $site_path) {
    $this->configFactory = $config_factory;
    $this->languageManager = $language_manager;
    $this->time = $time;
    $this->builder = $builder;
    $this->sitePath = $site_path;
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
      (string) $container->getParameter('site.path')
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
    $document = $this->builder->build(
      is_array($settings) ? $settings : [],
      $this->buildSystemSite(),
      ['timezone' => ['default' => (string) ($this->configFactory->get('system.date')->get('timezone.default') ?? 'UTC')]],
      $this->siteCode(),
      $this->generatedTimestamp()
    );

    $response = new CacheableJsonResponse($document);
    $metadata = new CacheableMetadata();
    $metadata->setCacheTags([
      'config:bioland.settings',
      'config:system.site',
      'config:system.date',
      'config:configurable_language_list',
    ]);
    // The document enumerates every language explicitly, so it does not vary
    // by the request's interface language; it varies only by the caller's
    // authorization, which the access check already declares.
    $metadata->setCacheContexts([]);
    $response->addCacheableDependency($metadata);
    $response->headers->set('Cache-Control', 'private, no-store');

    return $response;
  }

  /**
   * Builds the `system.site` section, including per-language name overrides.
   *
   * Per-language names are read through the language config override system
   * rather than a raw config read, which is the only way to see them.
   *
   * @return array
   *   The system.site section.
   */
  protected function buildSystemSite(): array {
    $section = [
      'name' => (string) ($this->configFactory->get('system.site')->get('name') ?? ''),
      'translations' => [],
    ];
    foreach (array_keys($this->languageManager->getLanguages()) as $langcode) {
      $override = $this->languageManager->getLanguageConfigOverride($langcode, 'system.site');
      $name = $override ? $override->get('name') : NULL;
      if (is_string($name) && $name !== '') {
        $section['translations'][$langcode] = ['name' => $name];
      }
    }
    return $section;
  }

  /**
   * Derives the site code from the site directory, e.g. "sites/abc" -> "abc".
   */
  protected function siteCode(): string {
    $code = basename($this->sitePath);
    return $code !== '' ? $code : 'default';
  }

  /**
   * An ISO-8601 UTC timestamp with milliseconds, matching the contract.
   */
  protected function generatedTimestamp(): string {
    return gmdate('Y-m-d\TH:i:s', $this->time->getRequestTime()) . '.000Z';
  }

}
