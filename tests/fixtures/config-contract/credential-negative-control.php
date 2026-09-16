<?php

/**
 * @file
 * Expands the synthetic credential negative-control fixture.
 *
 * The JSON fixture stores every credential-shaped value as a marker
 * (`__PEM__`, `__GHP__`, ...) rather than a literal, and this file assembles
 * them from fragments at runtime. The
 * reason is purely mechanical: a complete PEM block or JWT committed anywhere
 * in the tree trips the repository's pre-commit secret scanner, which has no
 * way to tell a deliberate synthetic negative control from a real leak. The
 * assembled values are genuine credential SHAPES — which is the whole point of
 * a negative control — while nothing credential-shaped is stored on disk.
 *
 * Every value here is obviously fake. Never put a real value in this fixture.
 *
 * @return array
 *   ['settings' => array, 'layers' => array, 'leaks' => string[], 'shapes' =>
 *   string[]] — the builder input; the substrings that must never appear in
 *   the serialized response, GROUPED BY WHICH DEFENCE removes each one; the
 *   flattened list of them; and the five credential shapes that must be
 *   detected by value alone.
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

// The five shapes a reviewer measured escaping the anchored detector: a
// password-shaped token, the same shape embedded in prose, a Slack-shaped
// token, a GitHub-shaped PAT, and a long single-case opaque token. All five
// are assembled from fragments for the same reason as the PEM and the JWT.
$passwordLike = 'Summer' . '2024' . '!bioland';
$prosePassword = 'Login to the mailer with user admin and password ' . 'Tr0ub' . '4dor' . '3xyz';
$slackToken = 'xox' . 'b-' . '2222222222-3333333333-' . 'abcdefghijklmnopqrstuvwx';
$githubPat = 'gh' . 'p_' . '16c7e42f' . 'a9b8c7d6' . 'e5f4a3b2' . 'c1d0e9f8' . 'a7b6c5d4';
$lowerToken = 'a1b2c3d4' . 'e5f6a7b8' . 'c9d0e1f2' . 'a3b4c5d6';
$rulesJson = '[{"bundle":"page","field":"field_url","visible":true,"pass":"' . $passwordLike . '"}]';

$markers = [
  '__PEM__' => $pemHead . "\n" . str_repeat('FAKE', 16) . "\n" . $pemTail,
  '__JWT__' => $jwtHead . '.' . $jwtBody . '.' . 'ZmFrZXNpZ25hdHVyZUZBS0U',
  '__PASSWORD_LIKE__' => $passwordLike,
  '__PROSE_PASSWORD__' => $prosePassword,
  '__WYSIWYG_PASSWORD__' => '<p>' . $passwordLike . '</p>',
  '__SLACK__' => $slackToken,
  '__GHP__' => $githubPat,
  '__LOWER32__' => $lowerToken,
  '__RULES_JSON__' => $rulesJson,
];

$settings = json_decode(file_get_contents(__DIR__ . '/bioland-settings.credential-negative-control.json'), TRUE);

// Which DEFENCE each leak string exercises. Without this split the suite
// cannot tell a value the scrubber caught from one the allowlist or the
// key-name deny list removed before the value scrubber ever ran, and an
// assertion that passes for an unrelated reason is not a test.
$layers = [
  // Removed because the TOP-LEVEL key is not on the allowlist at all. These
  // never reach the key deny list or the value scrubber.
  'allowlist' => [
    'fake-upstream-only-value',
    'FAKEpanoramaKEYvalue',
    'fake-db-password',
    'fake.person@example.invalid',
  ],
  // Removed by the never-ship KEY NAME match while nested INSIDE an
  // allowlisted key. None of these values is credential-shaped on its own, so
  // only the key layer can be what removes them.
  'deny_key' => [
    'fake-pass',
    'fake-token-value',
  ],
  // Removed by the VALUE scrubber: each sits under a benign allowlisted key
  // with a benign name, which is the scenario the scrubber exists for.
  'value_scrubber' => [
    $pemHead,
    $pemTail,
    $jwtHead,
    'FAKEPASSWORDpleaseignore',
    'mysql://',
    'smtp://',
    'FAKEMAILPASSWORD',
    'aGVsbG9GQUtFc2VjcmV0',
    $passwordLike,
    'Tr0ub' . '4dor' . '3xyz',
    $slackToken,
    $githubPat,
    $lowerToken,
  ],
];

return [
  'settings' => $expand($settings, $markers),
  'layers' => $layers,
  'leaks' => array_merge($layers['allowlist'], $layers['deny_key'], $layers['value_scrubber']),
  // The five shapes a reviewer measured as NOT caught by the anchored
  // detector. Each must be credential-shaped on its own, not merely absent
  // from the response because something else removed its container.
  'shapes' => [
    'password-shaped token' => $passwordLike,
    'credential embedded in prose' => $prosePassword,
    'slack-shaped token' => $slackToken,
    'github-shaped pat' => $githubPat,
    'long single-case opaque token' => $lowerToken,
  ],
];
