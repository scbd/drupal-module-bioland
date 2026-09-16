<?php

/**
 * @file
 * Expands the synthetic credential negative-control fixture.
 *
 * The JSON fixture stores two values as markers (`__PEM__`, `__JWT__`) rather
 * than literals, and this file assembles them from fragments at runtime. The
 * reason is purely mechanical: a complete PEM block or JWT committed anywhere
 * in the tree trips the repository's pre-commit secret scanner, which has no
 * way to tell a deliberate synthetic negative control from a real leak. The
 * assembled values are genuine credential SHAPES — which is the whole point of
 * a negative control — while nothing credential-shaped is stored on disk.
 *
 * Every value here is obviously fake. Never put a real value in this fixture.
 *
 * @return array
 *   ['settings' => array, 'leaks' => string[]] — the builder input, and the
 *   substrings that must never appear in the serialized response.
 */

$expand = static function (array $data, array $markers) use (&$expand): array {
  foreach ($data as $key => $value) {
    if (is_array($value)) {
      $data[$key] = $expand($value, $markers);
    }
    elseif (is_string($value) && isset($markers[$value])) {
      $data[$key] = $markers[$value];
    }
  }
  return $data;
};

$pemHead = '-----BEGIN RSA ' . 'PRIVATE KEY-----';
$pemTail = '-----END RSA ' . 'PRIVATE KEY-----';
$jwtHead = 'ey' . 'JhbGciOiJIUzI1NiJ9';
$jwtBody = 'ey' . 'JzdWIiOiJmYWtlIn0';

$markers = [
  '__PEM__' => $pemHead . "\n" . str_repeat('FAKE', 16) . "\n" . $pemTail,
  '__JWT__' => $jwtHead . '.' . $jwtBody . '.' . 'ZmFrZXNpZ25hdHVyZUZBS0U',
];

$settings = json_decode(file_get_contents(__DIR__ . '/bioland-settings.credential-negative-control.json'), TRUE);

return [
  'settings' => $expand($settings, $markers),
  'leaks' => [
    $pemHead,
    $pemTail,
    $jwtHead,
    'FAKEPASSWORDpleaseignore',
    'mysql://',
    'smtp://',
    'FAKEMAILPASSWORD',
    'aGVsbG9GQUtFc2VjcmV0',
    'fake-token-value',
    'FAKEpanoramaKEYvalue',
    'fake-db-password',
    'fake.person@example.invalid',
    'fake-pass',
  ],
];
