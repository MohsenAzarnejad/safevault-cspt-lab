# Client Side Path Traversal (CSPT → XSS) — Training Demo

A self-contained **PHP** lab for teaching developers **how Client Side Path
Traversal happens, what its impact is, and how to fix it**. Drop the files into
`/var/www/html` and Apache serves them on port 80 — no port conflicts, and it
already starts on boot.

> ⚠️ For authorized security training on an isolated machine only. The
> "attacker" app never touches any real system; it only talks to the
> deliberately vulnerable demo app.

```
Client Side Path Traversal/
├── vulnerable-app/     index.php  api.php
├── hacker-app/         index.php  phish.php  collect.php  loot.php  clear.php
├── secure-app/         index.php  api.php
└── README.md
```

The two apps are meant to run on **two different machines** (a "victim/app"
server and an "attacker" server).

---

## 1. Deploy on Kali (PHP + Apache)

### One-time setup (on each machine)

Kali has Apache; make sure PHP is installed and Apache is running:

```bash
sudo apt update && sudo apt install -y apache2 php libapache2-mod-php
sudo systemctl enable --now apache2
```

### Copy the files

`/var/www/html` usually contains a default `index.html` — remove it first so
the app's `index.php` is served.

```bash
# ---- on the VULNERABLE / app server ----
sudo rm -f /var/www/html/index.html
sudo cp vulnerable-app/index.php vulnerable-app/api.php /var/www/html/

# ---- on the ATTACKER server ----
sudo rm -f /var/www/html/index.html
sudo cp hacker-app/index.php hacker-app/phish.php hacker-app/collect.php \
        hacker-app/loot.php hacker-app/clear.php /var/www/html/

# ---- OPTIONAL: the SECURED build, alongside the vulnerable one ----
# served at  http://<vuln-ip>/secure/
sudo mkdir -p /var/www/html/secure
sudo cp secure-app/index.php secure-app/api.php /var/www/html/secure/
```

That's it — the apps are live and keep running across reboots (Apache is
already a boot service). Open them at:

| App | URL |
|-----|-----|
| SafeVault (vulnerable) | `http://<vuln-ip>/` |
| EvilCorp C2 (attacker) | `http://<attacker-ip>/` |
| SafeVault (secured, optional) | `http://<vuln-ip>/secure/` |

> **If the document area shows an error / the API 404s:** the API routes via
> `PATH_INFO` (e.g. `api.php/documents/secret`), which Kali's default `mod_php`
> supports out of the box. If your Apache was changed to reject it, re-enable
> with `AcceptPathInfo On` (in the site/directory config) and
> `sudo systemctl reload apache2`. Also make sure PHP is actually enabled
> (`php -v` works and `sudo a2query -m php*` shows the module) — otherwise
> Apache serves the `.php` source as text.

---

## 2. Run the demo (≈2 minutes)

> **Demo credentials** (the login page no longer prints these):
>
> | Username | Password |
> |----------|----------|
> | `victim` | `victim` |
> | `hacker` | `hacker` |
>
> Both work on the vulnerable app and the secured build. Use `victim` for the
> victim role; `hacker` is a spare account for the attacker to log in and poke
> at the target. After login the header shows **who** is signed in, and each
> account holds **different** confidential data (the victim's corporate secrets
> vs. the hacker's own C2 credentials) — so the loot the attack exfiltrates is
> unmistakably the *victim's* data, taken through the victim's session.

1. **Be the victim.** Open the vulnerable server (`http://<vuln-ip>/`) and
   **Sign in** as `victim` / `victim`. You now have a session cookie. Click
   around — the **Confidential** document holds an API key, admin password,
   etc. (The **Sign out** button in the header ends the session for another
   run-through.)

2. **Be the attacker.** Open the C2 dashboard (`http://<attacker-ip>/`), set
   **Target** to the vulnerable server's address, choose an **Attack scenario**
   (Scenario 1 = Documents, the default; Scenario 2 = the harder Secure-Notes →
   SSH-key variant, see §3), pick an **After exploitation** behaviour, and click
   **Generate payload & phishing link**. It shows the injected JavaScript and the
   malicious link (a normal-looking SafeVault URL).

   The **After exploitation** dropdown controls what the victim sees once the
   payload runs — the exfiltration happens the same way either way:
   - **Stealth** (default) — steals the data silently and then redirects the
     victim to a normal document. The victim sees a working SafeVault page and
     suspects nothing; this is how a real attack behaves.
   - **Deface** — drops a full-screen red "Account compromised" overlay. Use it
     in a classroom to *prove* the attacker's JS executed in the victim session.

3. **Deliver the lure.** Click **Open the phishing page →** — the page you'd
   email a victim ("Alice shared a document with you"). Click **Open
   document →**.

4. **Watch it fire.** The victim's browser lands on SafeVault and the CSPT
   payload runs:
   - in **Stealth** mode the page briefly loads and then quietly shows a normal
     document — no visible sign of compromise;
   - in **Deface** mode the page is **visibly defaced** with an "Account
     compromised" overlay showing the stolen cookie and confidential document;
   - either way, the C2 dashboard's **Stolen data (live)** table fills in with
     the cookie and data, exfiltrated to the attacker's server.

> Do steps 1–4 in the **same browser** so the victim session cookie is present
> when the payload runs. That is the whole point: the attack rides the victim's
> existing login.

### Troubleshooting: nothing lands in the loot table

The stolen data is beaconed to the **Collector URL**, which the C2 dashboard
auto-fills from *its own address*. If you opened the C2 at `http://localhost`
but the victim reaches the attacker box at its LAN IP, the beacon points at the
wrong host and never arrives.

**Fix:** open the C2 dashboard using the attacker machine's real address
(e.g. `http://192.168.1.185/`) *before* clicking **Generate**, or set the
**Collector URL** field to the attacker machine's reachable address and
regenerate. The on-victim defacement overlay works regardless, so if you see
the overlay but no loot row, it's always a collector-address mismatch.

### Troubleshooting: the payload injects but nothing happens (no overlay, no loot)

If the document card shows a **broken-image icon but no red overlay**, the
`<img onerror>` fired but the exfil `fetch()` to the **steal endpoint** was
rejected — most often because that endpoint resolved to a *different origin*
than the page the victim is on (IP vs hostname, a port difference, `http` vs
`https`), so the browser's same-origin policy blocked it.

**Fix:** leave the **Endpoint to steal** field as the **relative** default
(`/api.php/documents/secret` for Scenario 1, `/api.php/notes/ssh.json` for
Scenario 2). A relative path is always same-origin with the victim page, so it
never hits CORS. Also make sure the C2 **Target** is the *same address the
victim actually browses* (all `192.168.1.185`, not a mix of IP and hostname).
The payload also has a `catch` so a failed document fetch still defaces the page
and beacons the cookie (the data column shows
`[document fetch failed - cookie still stolen]`) — so a truly silent "nothing
happened" now means the injection itself didn't land, not the exfil.

---

## 3. Why it works (the teaching moment)

The vulnerable page builds a `fetch` path by **concatenating unvalidated user
input**:

```js
const docId = new URLSearchParams(location.search).get('doc');  // attacker-controlled
fetch('api.php/documents/' + docId)       // ❌ no encoding, no validation
  .then(r => r.json())
  .then(doc => body.innerHTML = doc.body); // ❌ dangerous sink
```

The attacker sets `?doc=../preview/<base64-payload>`. The browser **normalises
the `../` before sending**, so the request silently changes target:

```
api.php/documents/../preview/<b64>   →   GET api.php/preview/<b64>
```

`api.php/preview/` reflects attacker-controlled HTML, which `innerHTML` then
**executes** — inside SafeVault's origin, with the victim's cookies. That's
**CSPT → XSS**. From there the injected script can read the victim's data, act
as them, and exfiltrate everything (as the live loot table shows).

Key point for developers: **the payload never contained `http://evil.com`.**
It's a same-origin request that got *redirected within the app* by path
traversal — which is exactly why URL/host allowlists and CSP `connect-src`
alone don't save you.

Because the app identifies the user by the **session id alone**, the stolen
`session` cookie is a full account takeover: paste it into another browser on
the target origin (DevTools → Application → Cookies) and you are logged in as
the victim — no password required. That is why the vulnerable app deliberately
leaves the session cookie readable by script, and why the fix makes it
`HttpOnly`.

### Scenario 2 (harder) — the appended suffix, `Secure Notes` → SSH key

Pick **Scenario 2** in the C2 dashboard's *Attack scenario* dropdown. It targets
the vault's **Secure Notes** section, whose viewer loads a note like this:

```js
const noteId = new URLSearchParams(location.search).get('note'); // attacker-controlled
fetch('api.php/notes/' + noteId + '.json')   // ❌ concatenated AND a ".json" is appended
  .then(r => r.json()).then(doc => body.innerHTML = doc.body);
```

Same bug — but the app **always appends `.json`**, and the reflecting endpoint
(`/render/`) uses **strict** base64 decoding. So the obvious payload breaks:

```
?note=../render/<b64>     →   GET api.php/render/<b64>.json   ✗ strict decode fails ("." is invalid) → no XSS
```

The fix from the attacker's side is to **shed the suffix**: end the payload with
a `?` (or `#`) so the appended `.json` falls into the *query string* instead of
the path:

```
?note=../render/<b64>?    →   GET api.php/render/<b64>  +  ?.json   ✓ clean path → XSS fires
```

The dashboard builds this automatically for Scenario 2. The stolen asset here is
a **production SSH private key** read from `api.php/notes/ssh.json` using the
victim's session. Lesson: real CSPT targets often glue a fixed prefix/suffix
onto your input — recognising that a `?`/`#` can neutralise a trailing segment
is a core exploitation skill.

---

## 4. How to fix it — `secure-app`

Point the C2's **Target** at the secured build (`http://<vuln-ip>/secure/`),
regenerate, and try the link — nothing happens. The fix is defense in depth
(any one of these breaks the attack; use them all):

| # | Defense | Code |
|---|---------|------|
| 1 | **Encode** the path segment so `/` and `.` can't traverse | `fetch('api.php/documents/' + encodeURIComponent(docId))` |
| 2 | **Allowlist / validate** the input (client *and* server) | `/^[A-Za-z0-9_-]+$/.test(docId)` |
| 3 | **Avoid the sink** — render as text, not markup | `el.textContent = doc.body` (not `innerHTML`) |
| 4 | **Harden the session** — `HttpOnly` + `SameSite`; no reflecting endpoint | `setcookie(..., ['httponly'=>true,'samesite'=>'Strict'])` |

The **same four defenses fix Scenario 2 as well** — the `?`/`#` suffix trick
buys the attacker nothing once the note id is allowlisted and encoded, and the
secured build has no `/render` (or `/preview`) reflecting endpoint at all.

General rule: **never build a request path by string-concatenating untrusted
input.** Treat every dynamic path segment as data that must be encoded and
validated against an allowlist, and keep untrusted content away from HTML
sinks (`innerHTML`, `outerHTML`, `document.write`, `insertAdjacentHTML`).

---

## 5. Reset

- **Sign out** in the app, or clear cookies, to reset a victim session.
- Clear captured data with the **Clear loot** button on the C2 dashboard (the
  loot table auto-refreshes every 5s). It persists in a file on the attacker
  box, so you can also delete it directly: `sudo rm -f /tmp/evilcorp_loot.json`.

---

## 6. Security notes for this lab

- Login uses **PHP sessions**: the `session` cookie is a session id and the
  username is stored server-side (`$_SESSION`), so the id alone identifies the
  user. The vulnerable app's session cookie is intentionally **not** HttpOnly so
  the attack can steal a *real, usable* session id via `document.cookie`; the
  secured build makes it HttpOnly + `SameSite=Strict`. All confidential data is
  fake — keep this lab off any production/shared host.
- Serve it only on an isolated training network.

> **If login doesn't "stick"** (you sign in but land back on the login page),
> PHP can't write its session files — make the session save path writable
> (`/var/lib/php/sessions` is correct out of the box on Kali/Apache).

Reference: PayloadsAllTheThings —
<https://github.com/swisskyrepo/PayloadsAllTheThings/tree/master/Client%20Side%20Path%20Traversal>

---

## 7. License & responsible use

Released under the [MIT License](LICENSE).

This project is an intentionally **vulnerable** application built solely for
education and authorized security testing. Run it only on machines and networks
you own or are explicitly permitted to test. Do **not** deploy it on the public
internet or any shared/production host, and never use these techniques against
systems without written authorization. You are responsible for how you use it.
