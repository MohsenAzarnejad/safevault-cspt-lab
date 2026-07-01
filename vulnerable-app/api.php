<?php
/* ============================================================================
 *  SafeVault API (VULNERABLE) - front controller using PATH_INFO.
 *  Routes (no Apache rewrite needed, works on default config):
 *      GET api.php/documents/<id>    -> a document (requires session cookie)
 *      GET api.php/preview/<b64url>  -> reflects decoded content (CSPT sink #1)
 *      GET api.php/notes/<id>.json   -> a secure note (requires session cookie)
 *      GET api.php/render/<b64url>   -> reflects decoded content, STRICT decode
 *                                       (CSPT sink #2, scenario 2 / harder)
 *  FOR SECURITY TRAINING ONLY.
 * ==========================================================================*/

header('Content-Type: application/json; charset=utf-8');

// Identify the account from the session id (server-side), not a separate
// cookie. Each user sees their OWN documents and notes, so victim and hacker
// hold different sensitive data.
session_name('session');
session_set_cookie_params(['path' => '/', 'httponly' => false, 'samesite' => 'Lax']);
session_start();
$user = $_SESSION['user'] ?? '';

$pi = $_SERVER['PATH_INFO'] ?? '';   // e.g. /documents/secret  or  /preview/<b64>

$DATA = [
    'victim' => [
        'documents' => [
            '1' => [
                'title' => 'Welcome to SafeVault',
                'body'  => '<p>Hi <b>victim</b>! SafeVault keeps your company documents safe. '
                         . 'Use the sidebar to open a document.</p>',
            ],
            '2' => [
                'title' => 'Q3 Kickoff Meeting Notes',
                'body'  => '<p>Attendees: Alice, Bob, Carol.</p>'
                         . '<p>Action item: migrate billing service before the freeze.</p>',
            ],
            // The juicy target the attacker steals via the victim's session.
            'secret' => [
                'title' => 'CONFIDENTIAL - Do NOT share',
                'body'  => '<p><b style="color:#818cf8">Production API key:</b> '
                         . '<span style="color:#34d399;font-family:ui-monospace,Consolas,monospace">sk-live-9c1f4a7b2e8d0c63</span></p>'
                         . '<p><b style="color:#818cf8">Admin password:</b> '
                         . '<span style="color:#f472b6;font-family:ui-monospace,Consolas,monospace">Sup3rS3cret!2026</span></p>'
                         . '<p><b style="color:#818cf8">Bank routing:</b> '
                         . '<span style="color:#fbbf24;font-family:ui-monospace,Consolas,monospace">021000021 / acct 1839472210</span></p>',
            ],
        ],
        'notes' => [
            'welcome' => [
                'title' => 'Getting started with Secure Notes',
                'body'  => '<p>Secure Notes keep the credentials your services need &mdash; '
                         . 'API tokens, passphrases and private keys &mdash; behind your login.</p>',
            ],
            // The juicy scenario-2 target: a private SSH key stolen via the victim session.
            'ssh' => [
                'title' => 'Production deploy key (ssh)',
                'body'  => '<p><b>Host:</b> deploy@prod-gw.internal</p>'
                         . '<pre>-----BEGIN OPENSSH PRIVATE KEY-----' . "\n"
                         . 'b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtzc2gt' . "\n"
                         . 'ZWQyNTUxOQAAACBGQUtFa2V5RE9OT1RVU0VmYWtla2V5RE9OT1RVU0VmYWtlMDAwMDAw' . "\n"
                         . 'AAAAkGZha2VmYWtlRkFLRWtleURPTk9UVVNFZmFrZWtleURPTk9UVVNFZmFrZWtleTAw' . "\n"
                         . 'VklDVElNLURFUExPWS1LRVktRE8tTk9ULVVTRS1USElTLUlTLUZBS0UtMDAwMDAwMDA=' . "\n"
                         . '-----END OPENSSH PRIVATE KEY-----</pre>',
            ],
        ],
    ],
    'hacker' => [
        'documents' => [
            '1' => [
                'title' => 'Welcome to SafeVault',
                'body'  => '<p>Hi <b>hacker</b>! SafeVault keeps your documents safe. '
                         . 'Use the sidebar to open a document.</p>',
            ],
            '2' => [
                'title' => 'Operation notes',
                'body'  => '<p>Recon on target complete. Phishing lure drafted.</p>'
                         . '<p>Action item: rotate the C2 domain before the next campaign.</p>',
            ],
            // Hacker's OWN secrets - deliberately different from the victim's.
            'secret' => [
                'title' => 'CONFIDENTIAL - Do NOT share',
                'body'  => '<p><b style="color:#818cf8">EvilCorp C2 login:</b> '
                         . '<span style="color:#34d399;font-family:ui-monospace,Consolas,monospace">root / pwn3d-EvilCorp!2026</span></p>'
                         . '<p><b style="color:#818cf8">Monero payout wallet:</b> '
                         . '<span style="color:#f472b6;font-family:ui-monospace,Consolas,monospace">4Af3xEvilWa11etDoNotUseFake00000</span></p>'
                         . '<p><b style="color:#818cf8">Exfil bucket key:</b> '
                         . '<span style="color:#fbbf24;font-family:ui-monospace,Consolas,monospace">AKIA-FAKE-HACKER-9Z1Q</span></p>',
            ],
        ],
        'notes' => [
            'welcome' => [
                'title' => 'Getting started with Secure Notes',
                'body'  => '<p>Secure Notes keep your operational secrets behind your login.</p>',
            ],
            'ssh' => [
                'title' => 'Attacker box key (ssh)',
                'body'  => '<p><b>Host:</b> root@c2.evilcorp.internal</p>'
                         . '<pre>-----BEGIN OPENSSH PRIVATE KEY-----' . "\n"
                         . 'b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAk3NzaC1yc2Et' . "\n"
                         . 'Q2MkV4ZmlsQmVhY29uS2l0RXZpbENvcnBEcm9wcGVyWmVyb0RheUZha2VLZXkwMDAwMA' . "\n"
                         . 'UGF5bG9hZFJvb3RBY2Nlc3NBdHRhY2tlckJveEhhY2tlckMyRmFrZURvTm90VXNlMDAw' . "\n"
                         . 'SEFDS0VSLUMyLVJPT1QtS0VZLURPLU5PVC1VU0UtVEhJUy1JUy1GQUtFLTAwMDAwMDA9' . "\n"
                         . '-----END OPENSSH PRIVATE KEY-----</pre>',
            ],
        ],
    ],
];

// Fall back to the victim profile for unknown / legacy sessions.
$profile   = $DATA[$user] ?? $DATA['victim'];
$documents = $profile['documents'];
$notes     = $profile['notes'];

// ---- GET api.php/documents/<id>  (requires the victim's session) -----------
if (preg_match('#^/documents/(.+)$#', $pi, $m)) {
    if ($user === '') {
        http_response_code(401);
        echo json_encode(['error' => 'not authenticated']);
        exit;
    }
    $id = $m[1];
    if (isset($documents[$id])) {
        echo json_encode($documents[$id]);
    } else {
        http_response_code(404);
        echo json_encode(['title' => 'Not found', 'body' => 'No such document: ' . $id]);
    }
    exit;
}

// ---- GET api.php/preview/<b64url>  (the reflecting sink) --------------------
// In the real world this is any same-origin endpoint that echoes
// attacker-influenced content (search, stored messages, previews, ...).
if (preg_match('#^/preview/(.+)$#', $pi, $m)) {
    $b64 = strtr($m[1], '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad) { $b64 .= str_repeat('=', 4 - $pad); }
    $html = base64_decode($b64);
    if ($html === false) { $html = 'preview error'; }
    echo json_encode(['title' => 'Preview', 'body' => $html]);
    exit;
}

// ---- GET api.php/notes/<id>.json  (scenario 2, requires the session) -------
// The notes viewer fetches  api.php/notes/<id>.json  -> it always appends
// ".json". So the *route* expects that suffix; a legit note id is captured
// from between "notes/" and ".json".
if (preg_match('#^/notes/(.+)\.json$#', $pi, $m)) {
    if ($user === '') {
        http_response_code(401);
        echo json_encode(['error' => 'not authenticated']);
        exit;
    }
    $id = $m[1];
    if (isset($notes[$id])) {
        echo json_encode($notes[$id]);
    } else {
        http_response_code(404);
        echo json_encode(['title' => 'Not found', 'body' => 'No such note: ' . $id]);
    }
    exit;
}

// ---- GET api.php/render/<b64url>  (scenario 2 reflecting sink) --------------
// Like /preview, but with STRICT base64 decode. This is what makes scenario 2
// harder: the notes viewer appends ".json", so a naive
//     ?note=../render/<b64>   ->   GET api.php/render/<b64>.json
// hands this route "<b64>.json", and the invalid '.' makes strict decode FAIL
// (no XSS). The attacker must shed the suffix by ending the payload with "?"
// (or "#") so ".json" lands in the query string instead of the path:
//     ?note=../render/<b64>?  ->  GET api.php/render/<b64>  +  QUERY_STRING=.json
if (preg_match('#^/render/(.+)$#', $pi, $m)) {
    $b64 = strtr($m[1], '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad) { $b64 .= str_repeat('=', 4 - $pad); }
    $html = base64_decode($b64, true);   // STRICT: any invalid char (e.g. '.') -> false
    if ($html === false) { $html = 'render error: invalid payload'; }
    echo json_encode(['title' => 'Note preview', 'body' => $html]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'not found', 'path' => $pi]);
