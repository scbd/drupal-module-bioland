<?php

namespace Drupal\Core\Form;

/**
 * Stub interface for FormStateInterface.
 */
interface FormStateInterface {

  /**
   * Returns the submitted and sanitized form values.
   *
   * @return array
   *   The form values.
   */
  public function getValues();

  /**
   * Returns a form value.
   *
   * @param string|array $key
   *   The key.
   * @param mixed $default
   *   The default value.
   *
   * @return mixed
   *   The form value.
   */
  public function getValue($key, $default = NULL);

  /**
   * Sets a form value.
   *
   * @param string|array $key
   *   The key.
   * @param mixed $value
   *   The value.
   *
   * @return $this
   */
  public function setValue($key, $value);

  /**
   * Gets arbitrary data from form state.
   *
   * @param string|array $key
   *   The key.
   *
   * @return mixed
   *   The data.
   */
  public function get($key);

  /**
   * Sets arbitrary data in form state.
   *
   * @param string|array $key
   *   The key.
   * @param mixed $value
   *   The value.
   *
   * @return $this
   */
  public function set($key, $value);

  /**
   * Sets an error for the form.
   *
   * @param string $name
   *   The element name.
   * @param string $message
   *   The error message.
   *
   * @return $this
   */
  public function setErrorByName($name, $message = '');

  /**
   * Gets any form errors.
   *
   * @return array
   *   The errors.
   */
  public function getErrors();

  /**
   * Sets the redirect for the form.
   *
   * @param string $route_name
   *   The route name.
   * @param array $route_parameters
   *   The route parameters.
   * @param array $options
   *   The options.
   *
   * @return $this
   */
  public function setRedirect($route_name, array $route_parameters = [], array $options = []);

}
