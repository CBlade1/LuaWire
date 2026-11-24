const root = document.body;
const csrf = root.dataset.csrf;
const $ = (id) => document.getElementById(id);
let selectedJob = null;
let loggedIn = root.dataset.logged === 'true';
const request = async (path, method = 'GET', body) => {
  const res = await fetch(path, { method, credentials: 'same-origin', headers: {'Content-Type': 'application/json','X-CSRF-Token': csrf}, body: body ? JSON.stringify(body) : undefined, cache: 'no-store'});
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Request failed');
  return data;
};
const theme = localStorage.getItem('luawire-theme') || 'light';
root.classList.toggle('dark', theme === 'dark');
$('theme').onclick = () => {root.classList.toggle('dark');localStorage.setItem('luawire-theme', root.classList.contains('dark') ? 'dark' : 'light');};
function showPanels() { $('login-panel').hidden = loggedIn; $('workspace').hidden = !loggedIn; $('logout').hidden = !loggedIn; if (loggedIn) refresh(); }
$('login-form').onsubmit = async (event) => { event.preventDefault(); try { await request('/api/login', 'POST', {password:$('password').value}); loggedIn=true; $('password').value=''; $('login-error').textContent=''; showPanels();} catch(e){ $('login-error').textContent=e.message; }};
$('logout').onclick = async () => {await request('/api/logout','POST');location.reload();};
function updateLines(){const field=$('code');$('lines').textContent=Array.from({length:Math.max(1,field.value.split('\n').length)},(_,i)=>i+1).join('\n');}
$('code').addEventListener('input',updateLines);
$('code').addEventListener('scroll',()=>{$('lines').scrollTop=$('code').scrollTop;});
$('code').addEventListener('keydown',(e)=>{if(e.key==='Tab'){e.preventDefault();const el=e.target,start=el.selectionStart;el.setRangeText('  ',start,el.selectionEnd,'end');updateLines();}if(e.key==='Enter'&&(e.ctrlKey||e.metaKey)){e.preventDefault();run();}});
function shortTime(unix){return new Date(unix*1000).toLocaleString();}
function escapeText(value){return String(value||'').slice(0,180);}
async function refresh(){if(!loggedIn)return;try {const data=await request('/api/status');const tbody=$('history');tbody.replaceChildren();if (!data.jobs.length){const row=document.createElement('tr');const cell=document.createElement('td');cell.colSpan=4;cell.className='empty';cell.textContent='No executions recorded.';row.append(cell);tbody.append(row);}for(const job of data.jobs){const row=document.createElement('tr');for(const value of [shortTime(job.created),job.id.slice(0,12),job.status,escapeText(job.result)]){const td=document.createElement('td');td.textContent=value;row.append(td);}tbody.append(row);}if(selectedJob){const item=data.jobs.find(j=>j.id===selectedJob);if(item){$('run-status').textContent=item.status.toUpperCase();$('run-id').textContent=item.id.slice(0,12);$('output').textContent=item.result || (item.status==='pending'?'Waiting for runtime…':item.status==='running'?'Executing in host runtime…':'No output');if(!['pending','running'].includes(item.status)){$('execute').disabled=false;selectedJob=null;}}}} catch(e){$('connection').textContent='CONNECTION ERROR';}}
async function run(){if(selectedJob)return;const code=$('code').value;if(!code.trim())return;$('execute').disabled=true;try {const data=await request('/api/submit','POST',{code});selectedJob=data.id;$('output').textContent='Job queued. Waiting for host runtime…';$('run-id').textContent=data.id.slice(0,12);await refresh();}catch(e){$('output').textContent=e.message;$('execute').disabled=false;}}
$('execute').onclick=run;
$('refresh').onclick=refresh;
showPanels();setInterval(()=>{if(loggedIn)refresh();},1200);
