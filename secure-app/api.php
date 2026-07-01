<?php
/* ============================================================================
 *  SafeVault API (SECURED). Every id is allowlisted server-side, and there is
 *  deliberately NO /preview or /render reflecting endpoint.
 *      GET api.php/documents/<id>    (id must match ^[A-Za-z0-9_-]+$)
 *      GET api.php/notes/<id>.json   (id must match ^[A-Za-z0-9_-]+$)
 *  FOR SECURITY TRAINING ONLY.
 * ==========================================================================*/

header('Content-Type: application/json; charset=utf-8');

// Per-user data resolved from the session id (server-side), not a cookie.
session_name('secure_session');
session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Strict']);
session_start();
$user = $_SESSION['user'] ?? '';

$pi = $_SERVER['PATH_INFO'] ?? '';

$DATA = [
    'victim' => [
        'documents' => [
            '1' => ['title' => 'Welcome to SafeVault',
                    'body'  => 'Hi victim! SafeVault keeps your company documents safe. '
                             . 'Use the sidebar to open a document.'],
            '2' => ['title' => 'Q3 Kickoff Meeting Notes',
                    'body'  => 'Attendees: Alice, Bob, Carol. '
                             . 'Action item: migrate billing service before the freeze.'],
            'secret' => ['title' => 'CONFIDENTIAL - Do NOT share',
                         'body'  => 'Production API key: sk-live-9c1f4a7b2e8d0c63 | '
                                  . 'Admin password: Sup3rS3cret!2026'],
        ],
        'notes' => [
            'welcome' => ['title' => 'Getting started with Secure Notes',
                          'body'  => 'Secure Notes keep API tokens, passphrases and keys behind your login.'],
            'ssh'     => ['title' => 'Production deploy key (ssh)',
                          'body'  => 'Host: deploy@prod-gw.internal (private key stored securely).'],
        ],
    ],
    'hacker' => [
        'documents' => [
            '1' => ['title' => 'Welcome to SafeVault',
                    'body'  => 'Hi hacker! SafeVault keeps your documents safe. '
                             . 'Use the sidebar to open a document.'],
            '2' => ['title' => 'Operation notes',
                    'body'  => 'Recon on target complete. Phishing lure drafted. '
                             . 'Action item: rotate the C2 domain before the next campaign.'],
            'secret' => ['title' => 'CONFIDENTIAL - Do NOT share',
                         'body'  => 'EvilCorp C2 login: root / pwn3d-EvilCorp!2026 | '
                                  . 'Monero payout wallet: 4Af3xEvilWa11etDoNotUseFake00000'],
        ],
        'notes' => [
            'welcome' => ['title' => 'Getting started with Secure Notes',
                          'body'  => 'Secure Notes keep your operational secrets behind your login.'],
            'ssh'     => ['title' => 'Attacker box key (ssh)',
                          'body'  => 'Host: root@c2.evilcorp.internal (private key stored securely).'],
        ],
    ],
];

$profile   = $DATA[$user] ?? $DATA['victim'];
$documents = $profile['documents'];
$notes     = $profile['notes'];

if (preg_match('#^/documents/(.+)$#', $pi, $m)) {
    if ($user === '') {
        http_response_code(401);
        echo json_encode(['error' => 'not authenticated']);
        exit;
    }
    $id = $m[1];
    // FIX #2 (server side): reject anything not on the allowlist.
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
        http_response_code(400);
        echo json_encode(['title' => 'Blocked', 'body' => 'invalid id: ' . $id]);
        exit;
    }
    if (isset($documents[$id])) {
        echo json_encode($documents[$id]);
    } else {
        http_response_code(404);
        echo json_encode(['title' => 'Not found', 'body' => 'no such document']);
    }
    exit;
}

// Secured notes route (scenario 2). The ".json" suffix belongs to the route,
// and the id is allowlisted BEFORE any lookup - a "../render/<b64>" id contains
// '/' and '.', fails the allowlist, and is rejected.
if (preg_match('#^/notes/(.+)\.json$#', $pi, $m)) {
    if ($user === '') {
        http_response_code(401);
        echo json_encode(['error' => 'not authenticated']);
        exit;
    }
    $id = $m[1];
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
        http_response_code(400);
        echo json_encode(['title' => 'Blocked', 'body' => 'invalid note id: ' . $id]);
        exit;
    }
    if (isset($notes[$id])) {
        echo json_encode($notes[$id]);
    } else {
        http_response_code(404);
        echo json_encode(['title' => 'Not found', 'body' => 'no such note']);
    }
    exit;
}

// FIX #4: there is NO /preview or /render reflecting endpoint here.
http_response_code(404);
echo json_encode(['error' => 'not found']);
