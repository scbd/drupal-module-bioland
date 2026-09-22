<?php

namespace Drupal\media_library;

/**
 * Stub of core's MediaLibraryState (a ParameterBag of the library query).
 *
 * Keeps the query keys core uses; the hash is a placeholder, not core's HMAC.
 */
class MediaLibraryState {

  /**
   * The state parameters.
   *
   * @var array
   */
  protected $parameters;

  /**
   * Constructs the state.
   */
  public function __construct(array $parameters) {
    $this->parameters = $parameters;
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
      'hash' => 'stub-hash',
    ]);
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

}
