<?php
/* ============================================================================
 *  SafeVault - SECURED version (PHP / Apache) - the same app, CSPT fixed.
 *  FOR SECURITY TRAINING ONLY.  Show side-by-side with ../vulnerable-app:
 *  the same attacker link that pops the vulnerable app does nothing here.
 *
 *  Layered fixes:
 *    1. encodeURIComponent() on the path segment  (slashes can't traverse)
 *    2. allowlist the id  ^[A-Za-z0-9_-]+$  (client AND server)
 *    3. textContent instead of innerHTML  (no script execution)
 *    4. HttpOnly + SameSite=Strict cookie; NO /preview reflecting endpoint
 * ==========================================================================*/

// FIX #4: the session id lives in an HttpOnly + SameSite=Strict cookie, and the
// username is stored server-side in $_SESSION (looked up by that id) - never in
// a client-readable cookie.
session_name('secure_session');
session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Strict']);
session_start();

// Remove any legacy 'secure_user' cookie from earlier lab versions.
if (isset($_COOKIE['secure_user'])) {
    setcookie('secure_user', '', ['path' => '/', 'expires' => 1]);
}

$action = $_GET['action'] ?? '';
$loginError = '';

// Demo credentials (training lab only) - see README for the list.
$USERS = ['victim' => 'victim', 'hacker' => 'hacker'];

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';
    if (isset($USERS[$u]) && hash_equals($USERS[$u], $p)) {
        session_regenerate_id(true);
        $_SESSION['user'] = $u;
        header('Location: ./');
        exit;
    }
    $loginError = 'Invalid username or password.';
}
if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    setcookie('secure_session', '', ['path' => '/', 'expires' => 1]);
    header('Location: ./');
    exit;
}

$user     = $_SESSION['user'] ?? '';
$loggedIn = ($user !== '');
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>SafeVault (secured)</title>
<style>
  *{box-sizing:border-box}
  body{font-family:system-ui,Segoe UI,Arial,sans-serif;margin:0;background:#052e16;color:#dcfce7;font-size:18px}
<?php if (!$loggedIn): ?>
  body{display:flex;min-height:100vh;align-items:center;justify-content:center}
  .card{background:#064e3b;padding:44px;border-radius:14px;width:380px}
  h1{font-size:30px;margin:8px 0 4px}
  input{width:100%;padding:13px;margin:7px 0;border-radius:8px;border:1px solid #16a34a;
        background:#022c22;color:#dcfce7;box-sizing:border-box;font-size:17px}
  button{width:100%;margin-top:18px;padding:14px;border:0;border-radius:8px;background:#16a34a;
         color:#022c22;font-weight:700;cursor:pointer;font-size:18px}
  .creds{margin-top:20px;padding:12px 14px;background:#022c22;border:1px solid #16a34a;
         border-radius:8px;font-size:14px;color:#86efac;line-height:1.6}
  .err{margin-top:16px;padding:11px 14px;background:#450a0a;border:1px solid #b91c1c;
       border-radius:8px;font-size:15px;color:#fecaca}
<?php else: ?>
  header{background:#064e3b;padding:16px 24px;display:flex;gap:10px;align-items:center;border-bottom:1px solid #16a34a}
  .brand{font-weight:700;font-size:24px}.who{margin-left:auto;color:#86efac;font-size:16px}
  .signout{color:#86efac;text-decoration:none;font-size:16px;margin-left:18px;
           border:1px solid #16a34a;padding:7px 14px;border-radius:8px}
  .signout:hover{background:#022c22}
  .wrap{display:flex;min-height:calc(100vh - 58px)}
  nav{width:250px;background:#022c22;padding:18px;border-right:1px solid #16a34a}
  nav a{display:block;color:#bbf7d0;text-decoration:none;padding:11px 14px;border-radius:8px;margin-bottom:6px;font-size:17px}
  nav a:hover{background:#064e3b}
  main{flex:1;padding:38px}
  .doc{background:#064e3b;padding:30px;border-radius:12px;max-width:820px}
  .doc h2{font-size:28px}.doc #body{font-size:18px;line-height:1.6}
  .badge{display:inline-block;background:#16a34a;color:#022c22;font-weight:700;font-size:13px;padding:4px 11px;border-radius:20px}
  .hint{margin-top:26px;color:#86efac;font-size:15px;max-width:820px;line-height:1.7}
  code{background:#022c22;padding:3px 7px;border-radius:5px;color:#86efac;font-size:14px}
<?php endif; ?>
</style></head>
<body>
<?php if (!$loggedIn): ?>
  <form class="card" method="POST" action="?action=login" autocomplete="off">
    <div style="font-size:44px">&#9989;</div><h1>SafeVault</h1>
    <p style="color:#86efac;font-size:16px">Secure Document Manager (fixed build)</p>
    <input name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES); ?>" autocomplete="off" placeholder="Username">
    <input name="password" type="password" autocomplete="new-password" placeholder="Password">
    <button>Sign in</button>
<?php if ($loginError): ?>    <div class="err"><?php echo htmlspecialchars($loginError); ?></div>
<?php endif; ?>  </form>
<?php else: ?>
  <header><span class="brand">&#9989; SafeVault</span>
    <span class="who">&#128100; Signed in as <b><?php echo htmlspecialchars($user, ENT_QUOTES); ?></b> &middot; SECURED build</span>
    <a class="signout" href="?action=logout">Sign out</a></header>
  <div class="wrap">
    <nav>
      <a href="?doc=1">&#128075; Welcome</a>
      <a href="?doc=2">&#128203; Q3 Meeting Notes</a>
      <a href="?doc=secret">&#128274; Confidential</a>
      <div style="margin:16px 0 8px;padding-top:12px;border-top:1px solid #16a34a;color:#4ade80;font-size:13px;letter-spacing:.5px">SECURE NOTES</div>
      <a href="?note=welcome">&#128221; Getting started</a>
      <a href="?note=ssh">&#128273; Deploy SSH key</a>
    </nav>
    <main>
      <div class="doc">
        <span class="badge">CSPT FIXED</span>
        <h2 id="title">Loading&hellip;</h2>
        <div id="body"></div>
      </div>
      <div class="hint" id="msg"></div>
    </main>
  </div>
<script>
  var params = new URLSearchParams(location.search);
  var noteId = params.get('note');
  var msg    = document.getElementById('msg');

  function blocked(id){
    document.getElementById('title').textContent = 'Blocked';
    document.getElementById('body').textContent = 'Rejected suspicious id: ' + id;
    msg.innerHTML = 'The value <code>'+id.replace(/</g,'&lt;')+
        '</code> failed the <code>^[A-Za-z0-9_-]+$</code> allowlist &mdash; '+
        'the CSPT payload never leaves the page.';
  }
  function loaded(doc, note){
    document.getElementById('title').textContent = doc.title || 'Untitled';
    // FIX #3: textContent, not innerHTML -> no script execution possible.
    document.getElementById('body').textContent = doc.body || '';
    msg.innerHTML = 'Loaded with <code>encodeURIComponent</code> + allowlist + '+
        '<code>textContent</code>'+(note?' (notes endpoint)':'')+
        '. Try the attacker link now.';
  }
  function fail(e){
    document.getElementById('title').textContent = 'Error';
    document.getElementById('body').textContent = String(e);
  }

  if (noteId !== null) {
    // Scenario 2 (Secure Notes), secured. Same defenses as documents: the
    // appended ".json" gives an attacker nothing, because the id itself is
    // allowlisted and encoded first.
    if(!/^[A-Za-z0-9_-]+$/.test(noteId)){ blocked(noteId); }
    else {
      fetch('api.php/notes/' + encodeURIComponent(noteId) + '.json', {credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(doc){ loaded(doc, true); })
        .catch(fail);
    }
  } else {
    var docId = params.get('doc') || '1';
    // Scenario 1 (Documents), secured.
    // FIX #2: allowlist the id before it ever touches a request.
    if(!/^[A-Za-z0-9_-]+$/.test(docId)){ blocked(docId); }
    else {
      // FIX #1: encode the segment so '/' and '.' cannot traverse the path.
      fetch('api.php/documents/' + encodeURIComponent(docId), {credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(doc){ loaded(doc, false); })
        .catch(fail);
    }
  }
</script>
<?php endif; ?>
</body></html>
