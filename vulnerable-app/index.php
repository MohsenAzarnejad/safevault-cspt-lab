<?php
/* ============================================================================
 *  SafeVault - VULNERABLE demo (Client Side Path Traversal / CSPT2XSS)
 *  PHP / Apache version - just drop this folder's files into /var/www/html.
 *  FOR SECURITY TRAINING ON AN ISOLATED MACHINE ONLY.
 *
 *  The bug lives in the client JS below:
 *      fetch('api.php/documents/' + docId)      // docId from ?doc= , unvalidated
 *  With ?doc=../preview/<base64url> the browser normalises the path to
 *      api.php/preview/<base64url>
 *  which reflects attacker HTML that innerHTML then executes -> CSPT -> XSS.
 * ==========================================================================*/

// The session cookie is a PHP session id; the logged-in username lives
// server-side in $_SESSION and is looked up by that id (no separate user
// cookie). Cookie is intentionally NOT HttpOnly so the demo can show cookie
// theft. Real apps MUST use HttpOnly + Secure + SameSite (see ../secure-app).
session_name('session');
session_set_cookie_params(['path' => '/', 'httponly' => false, 'samesite' => 'Lax']);
session_start();

// Remove any legacy 'user' cookie from earlier lab versions - identity now
// comes from the session id alone, so a stolen session cookie IS the account.
if (isset($_COOKIE['user'])) {
    setcookie('user', '', ['path' => '/', 'expires' => 1]);
}

$action = $_GET['action'] ?? '';
$loginError = '';

// Demo credentials (training lab only) - see README for the list.
$USERS = ['victim' => 'victim', 'hacker' => 'hacker'];

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';
    if (isset($USERS[$u]) && hash_equals($USERS[$u], $p)) {
        session_regenerate_id(true);   // fresh session id on login
        $_SESSION['user'] = $u;        // the session id now identifies the user
        header('Location: ./');
        exit;
    }
    $loginError = 'Invalid username or password.';
}
if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    setcookie('session', '', ['path' => '/', 'expires' => 1]);
    header('Location: ./');
    exit;
}

$user     = $_SESSION['user'] ?? '';   // resolved from the session id, not a cookie
$loggedIn = ($user !== '');

// Cosmetic only: figure out which sidebar entry to highlight as active.
$curDoc  = $_GET['doc']  ?? null;
$curNote = $_GET['note'] ?? null;
if ($curDoc === null && $curNote === null) { $curDoc = '1'; }
function activeIf($cond) { return $cond ? ' class="active"' : ''; }
?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafeVault</title>
<style>
  :root{
    --bg:#0b1120; --panel:#151d2e; --panel2:#0f1626; --line:#26314a;
    --ink:#e6ecf7; --muted:#8b97ad; --brand1:#6366f1; --brand2:#22d3ee; --accent:#818cf8;
  }
  *{box-sizing:border-box}
  html,body{margin:0}
  body{font-family:system-ui,"Segoe UI",Roboto,Arial,sans-serif;color:var(--ink);font-size:18px;
       background:radial-gradient(1200px 700px at 15% -10%,#1b2543 0%,transparent 55%),
                  radial-gradient(1000px 600px at 110% 10%,#0e2b3a 0%,transparent 50%),var(--bg);
       min-height:100vh;-webkit-font-smoothing:antialiased}
  .logo{width:60px;height:60px;border-radius:16px;display:flex;align-items:center;justify-content:center;
        font-size:30px;background:linear-gradient(135deg,var(--brand1),var(--brand2));
        box-shadow:0 8px 24px #6366f155;margin:0 auto}
  .brandtext{background:linear-gradient(90deg,#c7d2fe,#a5f3fc);-webkit-background-clip:text;
             background-clip:text;color:transparent;font-weight:800;letter-spacing:.3px}
  code{background:var(--panel2);padding:3px 7px;border-radius:6px;color:#a5b4fc;font-size:14px;
       border:1px solid var(--line)}
  @keyframes rise{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
<?php if (!$loggedIn): ?>
  body{display:flex;align-items:center;justify-content:center;padding:24px}
  .card{background:linear-gradient(180deg,var(--panel),var(--panel2));padding:40px 40px 34px;
        border-radius:20px;width:400px;border:1px solid var(--line);
        box-shadow:0 24px 70px #00000066;text-align:center;animation:rise .5s ease both}
  h1{margin:18px 0 2px;font-size:30px}
  .sub{color:var(--muted);font-size:15px;margin-bottom:22px}
  form{text-align:left}
  label{display:block;font-size:13px;text-transform:uppercase;letter-spacing:.6px;
        color:var(--muted);margin:16px 0 6px}
  input{width:100%;padding:14px;border-radius:11px;border:1px solid var(--line);background:#0b1220;
        color:var(--ink);font-size:16px;transition:border-color .15s,box-shadow .15s}
  input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px #6366f133}
  button{width:100%;margin-top:26px;padding:15px;border:0;border-radius:11px;color:#fff;font-weight:700;
         font-size:17px;cursor:pointer;background:linear-gradient(135deg,var(--brand1),#4f46e5);
         box-shadow:0 10px 24px #4f46e555;transition:transform .1s,box-shadow .15s}
  button:hover{transform:translateY(-1px);box-shadow:0 14px 30px #4f46e577}
  button:active{transform:translateY(0)}
  .creds{margin-top:22px;padding:13px 15px;background:#0b1220;border:1px dashed var(--line);
         border-radius:11px;font-size:14px;color:#a5b4fc;line-height:1.7}
  .err{margin-top:18px;padding:12px 15px;background:#3b0d12;border:1px solid #7f1d1d;
       border-radius:11px;font-size:15px;color:#fecaca}
  .foot{margin-top:22px;color:#5b657a;font-size:12.5px}
<?php else: ?>
  header{position:sticky;top:0;z-index:5;background:#0f1626cc;backdrop-filter:blur(10px);
         padding:14px 26px;display:flex;align-items:center;gap:14px;border-bottom:1px solid var(--line)}
  header .logo{width:40px;height:40px;border-radius:11px;font-size:20px;margin:0}
  header .brand{font-size:22px}
  header .who{margin-left:auto;color:var(--muted);font-size:14px;padding:6px 12px;
              border:1px solid var(--line);border-radius:20px;background:#0b1220}
  header .signout{color:#c7d2fe;text-decoration:none;font-size:15px;
                  border:1px solid var(--line);padding:8px 16px;border-radius:10px;transition:background .15s}
  header .signout:hover{background:#1b2340}
  .wrap{display:flex;min-height:calc(100vh - 70px)}
  nav{width:262px;padding:22px 16px;border-right:1px solid var(--line);background:#0d1424aa}
  nav .grouplabel{margin:18px 6px 8px;padding-top:14px;border-top:1px solid var(--line);
                  color:#5b657a;font-size:11.5px;font-weight:700;letter-spacing:1.2px}
  nav .grouplabel.first{margin-top:0;padding-top:0;border-top:0}
  nav a{display:flex;align-items:center;gap:10px;color:#c3cce0;text-decoration:none;padding:12px 14px;
        border-radius:11px;margin-bottom:4px;font-size:16px;transition:background .15s,color .15s}
  nav a:hover{background:#1a2340;color:#fff}
  nav a.active{background:linear-gradient(135deg,#6366f133,#22d3ee22);color:#fff;
               box-shadow:inset 3px 0 0 var(--accent)}
  main{flex:1;padding:40px;max-width:1000px}
  .doc{background:linear-gradient(180deg,var(--panel),var(--panel2));padding:34px 36px;border-radius:18px;
       max-width:840px;border:1px solid var(--line);box-shadow:0 18px 50px #00000055;animation:rise .35s ease both}
  .doc h2{margin:0 0 18px;font-size:27px;padding-bottom:16px;border-bottom:1px solid var(--line)}
  .doc #body{font-size:17px;line-height:1.75;color:#d5deee}
  .doc #body pre{background:#0a0f1c;border:1px solid var(--line);border-radius:10px;padding:16px;
                 overflow:auto;font-size:13.5px;color:#a5f3fc}
  .hint{margin-top:26px;color:var(--muted);font-size:14.5px;max-width:840px;line-height:1.8;
        background:#0e1626;border:1px solid var(--line);border-left:3px solid var(--accent);
        border-radius:12px;padding:16px 18px}
<?php endif; ?>
</style></head>
<body>
<?php if (!$loggedIn): ?>
  <div class="card">
    <div class="logo">&#128274;</div>
    <h1><span class="brandtext">SafeVault</span></h1>
    <div class="sub">Secure Document Manager</div>
    <form method="POST" action="?action=login" autocomplete="off">
      <label>Username</label>
      <input name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES); ?>" autocomplete="off" autofocus placeholder="username">
      <label>Password</label>
      <input name="password" type="password" autocomplete="new-password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
      <button type="submit">&#128274;&nbsp; Sign in</button>
    </form>
<?php if ($loginError): ?>    <div class="err"><?php echo htmlspecialchars($loginError); ?></div>
<?php endif; ?>    <div class="foot">&#128274; Encrypted &middot; SOC 2 &middot; Zero-knowledge</div>
  </div>
<?php else: ?>
  <header>
    <span class="logo">&#128273;</span>
    <span class="brand brandtext">SafeVault</span>
    <span class="who">&#128100; Signed in as <b><?php echo htmlspecialchars($user, ENT_QUOTES); ?></b></span>
    <a class="signout" href="?action=logout">Sign out</a>
  </header>
  <div class="wrap">
    <nav>
      <div class="grouplabel first">Documents</div>
      <a href="?doc=1"<?php echo activeIf($curDoc==='1'); ?>>&#128075; Welcome</a>
      <a href="?doc=2"<?php echo activeIf($curDoc==='2'); ?>>&#128203; Q3 Meeting Notes</a>
      <a href="?doc=secret"<?php echo activeIf($curDoc==='secret'); ?>>&#128274; Confidential</a>
      <div class="grouplabel">Secure Notes</div>
      <a href="?note=welcome"<?php echo activeIf($curNote==='welcome'); ?>>&#128221; Getting started</a>
      <a href="?note=ssh"<?php echo activeIf($curNote==='ssh'); ?>>&#128273; Deploy SSH key</a>
    </nav>
    <main>
      <div class="doc">
        <h2 id="title">Loading&hellip;</h2>
        <div id="body"></div>
      </div>
      <div class="hint" id="hint">
        This viewer loads a document by id:
        <code>fetch('api.php/documents/' + docId)</code>.
        The <code>docId</code> comes straight from the <code>?doc=</code> URL
        parameter with no validation &mdash; that is the CSPT bug.
      </div>
    </main>
  </div>
<script>
  var params = new URLSearchParams(location.search);
  var noteId = params.get('note');

  function render(doc){
      document.getElementById('title').textContent = doc.title || 'Untitled';
      // DOM XSS sink: reflected/preview content rendered as HTML.
      document.getElementById('body').innerHTML = doc.body || '';
  }
  function fail(e){
      document.getElementById('title').textContent = 'Error';
      document.getElementById('body').textContent = String(e);
  }

  if (noteId !== null) {
    // Scenario 2 (Secure Notes) - VULNERABLE, and a little harder: the viewer
    // ALWAYS appends ".json". ?note=../render/<b64> escapes api.php/notes/ into
    // api.php/render/ but drags a trailing ".json" along; the attacker sheds it
    // with a "?" so it falls into the query string.
    document.getElementById('hint').innerHTML =
        'This viewer loads a note by id: '
      + '<code>fetch(\'api.php/notes/\' + noteId + \'.json\')</code>. '
      + 'The <code>noteId</code> comes straight from <code>?note=</code> with no '
      + 'validation &mdash; same CSPT bug, but the appended <code>.json</code> '
      + 'makes the payload trickier.';
    fetch('api.php/notes/' + noteId + '.json', { credentials: 'same-origin' })
      .then(function(r){ return r.json(); }).then(render).catch(fail);
  } else {
    var docId = params.get('doc') || '1';
    // Scenario 1 (Documents) - VULNERABLE: attacker-controlled docId concatenated
    // into the fetch path. ?doc=../preview/<b64> escapes api.php/documents/ and
    // hits api.php/preview/.
    fetch('api.php/documents/' + docId, { credentials: 'same-origin' })
      .then(function(r){ return r.json(); }).then(render).catch(fail);
  }
</script>
<?php endif; ?>
</body></html>
