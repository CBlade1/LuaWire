# LuaWire

A local PHP console for sending Lua code to a running application that embeds Lua.

LuaWire is a small job queue with two sides: a PHP operator interface and a Lua runtime adapter. The PHP application stores a submitted snippet; the embedded Lua runtime claims it, executes it in its own context, and sends the result back.

It does **not** inject code into arbitrary processes, start another interpreter, or assume that a process exposes Lua externally. The host application must load the adapter and call its polling function.

## Requirements

- PHP 8.1+ with sessions enabled
- Lua 5.1+ embedded in the target application
- LuaSocket and lua-cjson available to that runtime for the supplied adapter
- Both components on the same machine
- A host hook that invokes Lua functions regularly

No framework, JavaScript build step, database server, or additional machine is required. The PHP reference server is for local development and controlled operation, not for direct internet exposure.

## Setup

```sh
php bin/setup.php 'your-strong-local-password'
./bin/serve.sh
```

Visit `http://127.0.0.1:8787` and sign in with that password. The setup command creates `.env.local` containing a password hash and agent token; it is ignored by Git.

### Connect a Lua runtime

The host application must load `examples/luawire-agent.lua` **inside its own Lua state** and call `agent.poll()` from a scheduler or update hook. The example uses LuaSocket and lua-cjson. It accesses the locally bound PHP API using the agent token.

```lua
local agent = require('luawire-agent')

-- Use your application's existing periodic callback.
-- The exact registration API varies by host.
function onMaintenanceTick()
    local handled, err = agent.poll()
    if err then print('LuaWire: ' .. err) end
end
```

Run the target process with `LUAWIRE_URL` and `LUAWIRE_AGENT_TOKEN` set from `.env.local`. The PHP server should run under a separate, restricted OS account. Keep the token out of logs and public files.

An example snippet to run from the editor:

```lua
return 'Lua ' .. _VERSION
```

The return value appears in the output panel. Error messages from `pcall` are also reported. The adapter runs one job per poll; no automatic polling is started by the module. Call it at a cadence appropriate for the host, taking into account the HTTP timeout.

## Remote access

The console intentionally rejects requests that do not originate from `127.0.0.1` or `::1`. Use an SSH tunnel if you need to access it from a different computer:

```sh
ssh -L 8787:127.0.0.1:8787 user@your-host
```

Then open `http://127.0.0.1:8787` locally. Do not expose this tool through a public reverse proxy. HTTP forwarding headers are not trusted for authentication.

## Execution model

- Submissions are validated, capped at 16 KiB, and stored as pending jobs.
- A runtime adapter claims one pending job at a time.
- Claimed jobs are not reissued automatically. If the worker disappears, the status becomes `unknown`, not `failed`, because execution may already have happened.
- Pending jobs expire after two minutes. Claimed jobs without a result become `unknown` after one minute.
- The most recent 500 jobs are kept. The queue is serialized to a local file under an exclusive lock.
- All snippets are retained in the local queue until removed by the operator. Treat the queue as sensitive data.

LuaWire is **not a Lua sandbox**. Submitted code inherits the embedded runtime's privileges. A snippet can hang, alter state, or crash its host, and a Lua `pcall` cannot prevent every type of failure. Do not execute untrusted code. The example captures one returned value rather than intercepting arbitrary `print` output.

For long-running or expensive commands, use the host application's existing job scheduler rather than executing inside a latency-sensitive main loop. PHP cannot enforce Lua CPU or memory limits inside a different process.

## Security notes

- Localhost-only HTTP endpoints; no public exposure by default
- Password-based operator session with HTTP-only, SameSite cookies and CSRF checks
- Separate shared secret for the runtime adapter
- Fixed local storage path; no shell execution or arbitrary file upload
- Size-limited requests and a bounded queue
- No use of `X-Forwarded-For` or similar spoofable headers for trust decisions

The supplied PHP development server does not provide a production-grade HTTP runtime, and the file queue is intended for low-volume local operation. Protect the host, use a dedicated account, and review your application's Lua environment before enabling execution.

## Layout

```text
public/                 Web entrypoint and static interface
src/bootstrap.php       Authentication and queue primitives
examples/luawire-agent.lua    Adapter loaded by the host Lua runtime
bin/setup.php           Credential setup
bin/serve.sh            Local web server launcher
storage/                Local execution queue (not tracked)
```

## License

MIT. See `LICENSE`.
