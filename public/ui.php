<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>LuaWire / Console</title>
<link rel="stylesheet" href="/assets/app.css">
</head>
<body data-logged="<?= $logged ?>" data-csrf="<?= $csrf ?>">
<div class="shell">
  <header class="top"><div class="brand"><span class="mark">&gt;_</span><b>LuaWire</b><span class="subbrand">/ console</span></div><div class="right"><span class="dot"></span><span id="connection">LOCAL ONLY</span><button id="theme" class="plain" aria-label="Switch theme">◐</button><button id="logout" class="plain" hidden>Log out</button></div></header>
  <main>
    <section id="login-panel" class="login" hidden><p class="eyebrow">AUTHENTICATION</p><h1>Operator access.</h1><p class="muted">Execution is restricted to the local machine. For remote access, use an SSH tunnel.</p><form id="login-form"><label for="password">Password</label><input id="password" type="password" autocomplete="current-password" required autofocus><button class="primary">Unlock console <span>↗</span></button></form><p class="error" id="login-error"></p></section>
    <section id="workspace" hidden>
      <div class="intro"><div><p class="eyebrow">RUNTIME INTERFACE <span class="divider">/</span> LUA</p><h1>Execution console<span class="period">.</span></h1><p class="muted">Submit Lua to a connected runtime. No standalone Lua process is launched.</p></div><div class="status"><span class="caption">EXECUTION MODE</span><strong>Host runtime</strong><small>Local queue · explicit polling</small></div></div>
      <div class="layout">
        <div class="editor card"><div class="panelhead"><span><span class="small-dot"></span> buffer.lua</span><span class="meta">Lua · 16 KB max</span></div><div class="codearea"><div class="linenums" id="lines">1</div><textarea spellcheck="false" id="code" aria-label="Lua code" placeholder="-- Lua executed within your application's runtime&#10;return 'ready'"></textarea></div><div class="editorfoot"><span class="hint"><kbd>Ctrl</kbd> + <kbd>Enter</kbd> to execute</span><button id="execute" class="primary">Run code <span>↗</span></button></div></div>
        <div class="results card"><div class="panelhead"><span>Output</span><span id="run-status" class="meta">IDLE</span></div><div class="output" id="output">No execution yet.<span class="cursor">_</span></div><div class="resultfoot"><span>Latest run</span><span id="run-id">—</span></div></div>
      </div>
      <section class="history"><div class="historytop"><div><p class="eyebrow">LOG / EXECUTIONS</p><h2>Recent activity</h2></div><button id="refresh" class="outline">Refresh ↻</button></div><div class="tablewrap"><table><thead><tr><th>TIME</th><th>JOB</th><th>STATE</th><th>OUTPUT</th></tr></thead><tbody id="history"><tr><td colspan="4" class="empty">No executions recorded.</td></tr></tbody></table></div></section>
      <footer><span>LUAWIRE <span class="muted">/ 01</span></span><span>Runs with host process privileges. Restrict access.</span></footer>
    </section>
  </main>
</div>
<script src="/assets/app.js" defer></script>
</body></html>
