<?php

namespace Drupal\media_library;

use Drupal\Component\Utility\Crypt;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Stub of core's MediaLibraryState (a ParameterBag of the library query).
 *
 * The hash is computed exactly as core's getHash() does (sorted allowed types,
 * ksorted opener parameters, serialize(), Crypt::hmacBase64()), only with a
 * fixed key standing in for private_key + hash salt. That keeps the one thing
 * that matters honest: a state that does not survive the URL round trip
 * (create() -> query string -> fromRequest()) fails here as it would on a
 * site. fromRequest() throws HttpFoundation's BadRequestException in place of
 * core's HttpKernel BadRequestHttpException, which the suite does not stub.
 */
class MediaLibraryState {

  /**
   * Stand-in for private_key + Settings::getHashSalt().
   */
  public const HASH_KEY = 'bioland-test-private-key';

  /**
   * The state parameters.
   *
   * @var array
   */
  protected $parameters;

  /**
   * Constructs the state and stamps its hash, as core does.
   */
  public function __construct(array $parameters = []) {
    $this->parameters = $parameters + ['media_library_opener_parameters' => []];
    $this->parameters['hash'] = $this->getHash();
  }

  /**
   * Mirrors MediaLibraryState::create().
   */
  public static function create($opener_id, array $allowed_media_type_ids, $selected_type_id, $remaining_slots, array $opener_parameters = []) {
    return new static([
      'media_library_opener_id' => $opener_id,
      'media_library_allowed_types' => $allowed_media_type_ids,
      'media_library_selected_type' => $selected_type_id,
      'media_library_remaining' => $remaining_slots,
      'media_library_opener_parameters' => $opener_parameters,
    ]);
  }

  /**
   * Mirrors MediaLibraryState::fromRequest(): rebuild, then verify the hash.
   */
  public static function fromRequest(Request $request) {
    $query = $request->query;
    $state = static::create(
      $query->get('media_library_opener_id'),
      $query->all('media_library_allowed_types'),
      $query->get('media_library_selected_type'),
      $query->get('media_library_remaining'),
      $query->all('media_library_opener_parameters')
    );
    if (!$state->isValidHash($query->get('hash'))) {
      throw new BadRequestException('Invalid media library parameters specified.');
    }
    $state->parameters = $query->all();

    return $state;
  }

  /**
   * Mirrors MediaLibraryState::getHash().
   */
  public function getHash() {
    $allowed_media_type_ids = array_values($this->parameters['media_library_allowed_types']);
    sort($allowed_media_type_ids);
    $opener_parameters = $this->getOpenerParameters();
    ksort($opener_parameters);
    $hash = implode(':', [
      $this->parameters['media_library_opener_id'],
      implode(':', $allowed_media_type_ids),
      $this->parameters['media_library_selected_type'],
      (int) $this->parameters['media_library_remaining'],
      serialize($opener_parameters),
    ]);

    return Crypt::hmacBase64($hash, self::HASH_KEY);
  }

  /**
   * Mirrors MediaLibraryState::isValidHash().
   */
  public function isValidHash($hash) {
    return is_string($hash) && hash_equals($this->getHash(), $hash);
  }

  /**
   * Returns every parameter.
   */
  public function all() {
    return $this->parameters;
  }

  /**
   * Returns the opener parameters.
   */
  public function getOpenerParameters() {
    return $this->parameters['media_library_opener_parameters'] ?? [];
  }

  /**
   * Mirrors MediaLibraryState::getCacheContexts().
   */
  public function getCacheContexts() {
    return ['url.query_args'];
  }

  /**
   * Mirrors MediaLibraryState::getCacheTags().
   */
  public function getCacheTags() {
    return [];
  }

  /**
   * Mirrors MediaLibraryState::getCacheMaxAge() (Cache::PERMANENT).
   */
  public function getCacheMaxAge() {
    return -1;
  }

}
