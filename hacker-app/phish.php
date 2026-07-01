<?php
/* ============================================================================
 *  EvilCorp C2 - the phishing lure. Open it via the dashboard's
 *  "Open the phishing page" button (it passes the crafted link as ?to=).
 *  This is what you would email a victim. FOR SECURITY TRAINING ONLY.
 * ==========================================================================*/

$to = $_GET['to'] ?? '#';
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>A document was shared with you</title>
<style>
  body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:#f1f5f9;margin:0;font-size:18px;
       display:flex;min-height:100vh;align-items:center;justify-content:center}
  .card{background:#fff;border-radius:14px;padding:48px;max-width:500px;text-align:center;
        box-shadow:0 12px 40px #0002}
  .logo{font-size:52px}
  h1{font-size:26px;margin:14px 0 6px;color:#0f172a}
  p{color:#475569;font-size:17px;line-height:1.7}
  a.btn{display:inline-block;margin-top:22px;background:#2563eb;color:#fff;text-decoration:none;
        padding:15px 30px;border-radius:9px;font-weight:600;font-size:18px}
  small{display:block;margin-top:24px;color:#94a3b8;font-size:14px}
</style></head>
<body><div class="card">
  <div class="logo">&#128273;</div>
  <h1>SafeVault</h1>
  <p><b>Alice</b> shared a confidential document with you:
     <br><i>&ldquo;Q3 Financial Summary&rdquo;</i></p>
  <p>For your security, this link expires in 24 hours.</p>
  <a class="btn" href="<?php echo htmlspecialchars($to, ENT_QUOTES); ?>">Open document &rarr;</a>
  <small>You are receiving this because a document was shared with your account.</small>
</div></body></html>
