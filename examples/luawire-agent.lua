-- Load this module from the application's existing Lua runtime.
-- Call agent.poll() from its normal update loop; do not run it in a separate Lua process.
local http = require('socket.http')
local ltn12 = require('ltn12')
local json = require('cjson')

local agent = {}
local endpoint = assert(os.getenv('LUAWIRE_URL'), 'LUAWIRE_URL is missing')
local token = assert(os.getenv('LUAWIRE_AGENT_TOKEN'), 'LUAWIRE_AGENT_TOKEN is missing')
http.TIMEOUT = 0.5

local function post(path, body)
  local parts = {}
  local ok, status = http.request({
    url = endpoint .. path,
    method = 'POST',
    headers = {
      ['Content-Type'] = 'application/json',
      ['Content-Length'] = tostring(#body),
      ['X-Lua-Agent-Token'] = token
    },
    source = ltn12.source.string(body),
    sink = ltn12.sink.table(parts)
  })
  if not ok or tonumber(status) ~= 200 then
    return nil, 'HTTP ' .. tostring(status)
  end
  return json.decode(table.concat(parts))
end

function agent.poll()
  local response, err = post('/api/agent/claim', '{}')
  if not response then return nil, err end
  local job = response.job
  if not job then return false end

  local compile = loadstring or load
  local chunk, syntax_error = compile(job.code, 'luawire:' .. job.id)
  local success, result
  if not chunk then
    success, result = false, tostring(syntax_error)
  else
    -- Executes within the current Lua state with its existing privileges.
    success, result = pcall(chunk)
  end
  local body = json.encode({id=job.id, ok=success, result=tostring(result == nil and '' or result)})
  local recorded, report_error = post('/api/agent/result', body)
  if not recorded then return nil, report_error end
  return true
end

return agent
