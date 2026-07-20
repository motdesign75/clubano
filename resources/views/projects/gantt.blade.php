<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Gantt – {{ $project->name }}</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  :root{
    --bg:#f3f4f6;--surface:#fff;--text:#0f172a;--muted:#6b7280;
    --line:#e5e7eb;--line2:#eef2f7;--shadow:0 4px 18px rgba(2,6,23,.06);
    --task:#1d4ed8;--done:#16a34a;--blocked:#dc2626;--ms:#f59e0b;--today:#7c3aed;
    --radius:14px;--rowH:40px;--barH:18px;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial}
  .wrap{max-width:1400px;margin:24px auto;padding:0 16px}
  .head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:10px}
  .title{margin:0 0 2px;font-size:30px;font-weight:800}
  .muted{color:var(--muted);margin:0}
  .btn{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--line);
       padding:8px 12px;border-radius:10px;background:#fff;color:var(--text);text-decoration:none;box-shadow:var(--shadow)}
  .legend{display:flex;gap:16px;align-items:center;flex-wrap:wrap}
  .badge{display:inline-flex;align-items:center;gap:6px;color:var(--muted);font-size:13px}
  .dot{width:12px;height:12px;border-radius:3px;background:var(--task)}
  .dot.done{background:var(--done)} .dot.blocked{background:var(--blocked)} .dot.ms{background:var(--ms)}
  .card{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow)}
  .chart{overflow:auto;position:relative}
  .row{display:grid;grid-template-columns:320px 100px 110px 110px 1fr;border-bottom:1px solid var(--line)}
  .row:nth-child(odd) .cell.name{background:#fafafa}
  .cell{padding:10px 12px;border-right:1px solid var(--line);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;background:#fff}
  .cell:last-child{border-right:0}
  /* Sticky Header + Sticky Name */
  .row.axis{position:sticky;top:0;z-index:5}
  .row.axis .cell{font-weight:700;background:#fff}
  .cell.name{position:sticky;left:0;z-index:4}
  .row.axis .cell.name{z-index:6}

  .grid{position:relative;height:var(--rowH);min-width:900px;background:#fff}
  .bar{position:absolute;height:var(--barH);top:calc((var(--rowH)-var(--barH))/2);border-radius:8px;background:var(--task);
       color:#fff;font-size:11px;display:flex;align-items:center;justify-content:center;padding:0 6px;box-shadow:0 1px 2px rgba(0,0,0,.08)}
  .bar.done{background:var(--done)} .bar.blocked{background:var(--blocked)}
  .fill{position:absolute;top:0;bottom:0;left:0;width:0;background:rgba(255,255,255,.35);border-radius:8px}
  .ms{position:absolute;width:12px;height:12px;top:50%;transform:translate(-50%,-50%) rotate(45deg);background:var(--ms);border-radius:2px;box-shadow:0 1px 2px rgba(0,0,0,.08)}
  .tick{position:absolute;top:0;bottom:0;border-left:1px solid var(--line2)}
  .wk{position:absolute;top:0;bottom:0;border-left:1px solid #dbe3ef}
  .wknd{position:absolute;top:0;bottom:0;background:rgba(15,23,42,.05)}
  .label{position:absolute;top:2px;left:4px;font-size:12px;color:#334155;background:#fff;padding:2px 6px;border-radius:6px}
  .today{position:absolute;top:0;bottom:0;width:2px;background:var(--today);opacity:.85}

  /* Dependency overlay (SVG lines) */
  .links{position:absolute;left:0;top:calc(var(--rowH) + 1px); /* unter Axis-Zeile */
         pointer-events:none}

  /* Tooltip */
  .tip{position:fixed;pointer-events:none;z-index:9999;background:#111827;color:#f9fafb;padding:8px 10px;border-radius:8px;font-size:12px;box-shadow:var(--shadow);opacity:0;transform:translateY(-4px);transition:opacity .08s ease,transform .08s ease}
  .tip.show{opacity:1;transform:translateY(0)}

  /* Print */
  @media print{
    .head .btn, .legend button{display:none}
    .chart{overflow:visible}
  }
</style>
</head>
<body>
<div class="wrap">
  @php($__g = app(\App\Http\Controllers\ProjectGanttController::class)->json(request(), $project)->getData(true))
  <script id="gantt-data" type="application/json">{!! json_encode($__g, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>

  <div class="head">
    <div>
      <a class="btn" href="{{ route('projects.show', $project) }}">← Zurück</a>
      <h1 class="title">{{ $project->name }} – Gantt</h1>
      <p class="muted">Aufgaben, Meilensteine, Wochenraster, Wochenenden & Heute-Linie. Drag zum Pannen, Scroll zum Zoomen.</p>
    </div>
    <div class="legend">
      <span class="badge"><span class="dot"></span>Aufgabe</span>
      <span class="badge"><span class="dot done"></span>Erledigt</span>
      <span class="badge"><span class="dot blocked"></span>Blockiert</span>
      <span class="badge"><span class="dot ms"></span>Meilenstein</span>
    </div>
  </div>

  <div class="head" style="margin:8px 0 14px">
    <div class="legend">
      <button id="scale-day"   class="btn" type="button" aria-pressed="true">Day</button>
      <button id="scale-week"  class="btn" type="button">Week</button>
      <button id="scale-month" class="btn" type="button">Month</button>
      <button id="zoom-out" class="btn" type="button" aria-label="Zoom verkleinern">−</button>
      <button id="zoom-in"  class="btn" type="button" aria-label="Zoom vergrößern">+</button>
      <button id="fit"      class="btn" type="button">Fit</button>
      <button id="today"    class="btn" type="button">Heute</button>
      <button id="print"    class="btn" type="button">Drucken</button>
    </div>
  </div>

  <div id="chart" class="chart card" aria-live="polite" role="region" aria-label="Gantt Diagramm">
    <div class="row axis" role="row">
      <div class="cell name" role="columnheader">Aufgabe</div>
      <div class="cell" role="columnheader">Dauer</div>
      <div class="cell" role="columnheader">Start</div>
      <div class="cell" role="columnheader">Ende</div>
      <div class="cell" style="padding:0" role="columnheader">
        <div class="grid" id="axisGrid" role="img" aria-label="Zeitachse"></div>
      </div>
    </div>
    <svg id="links" class="links" width="0" height="0" aria-hidden="true"></svg>
    <div id="rows"></div>
  </div>
  <div id="tip" class="tip" role="tooltip" aria-hidden="true"></div>

<script>
/* ---------- utils & state ---------- */
(function(){
  const S = { pxPerDay: 28, min:null, max:null, scale:'day' };
  const byId = (id)=>document.getElementById(id);
  const msPerDay = 86400000;

  const sod = d => new Date(d.getFullYear(), d.getMonth(), d.getDate());
  const addDays = (d,n)=>{const x=new Date(d); x.setDate(x.getDate()+n); return x;};
  const daysBetween = (a,b)=> Math.round((b - a)/msPerDay);
  const fmtDE = new Intl.DateTimeFormat('de-DE');
  const fmtDEhm = new Intl.DateTimeFormat('de-DE',{day:'2-digit',month:'2-digit',year:'numeric'});

  function robustParse(s){
    if(!s) return null;
    let d = new Date(s); if(!isNaN(+d)) return sod(d);
    if(typeof s==='string' && s.includes(' ')){ d = new Date(s.replace(' ','T')); if(!isNaN(+d)) return sod(d); }
    if(/^\d{4}-\d{2}-\d{2}$/.test(s)){ d=new Date(s+'T00:00:00'); if(!isNaN(+d)) return sod(d); }
    return null;
  }

  function clamp(v, min, max){ return Math.max(min, Math.min(max, v)); }

  window.__G__ = { S, byId, sod, addDays, daysBetween, robustParse, fmtDE, fmtDEhm, clamp, msPerDay };
})();
</script>

<script>
/* ---------- axis/grid drawing ---------- */
(function(){
  const { S, byId, sod, addDays, daysBetween } = window.__G__;
  const axis = byId('axisGrid');

  function clear(el){ while(el.firstChild) el.removeChild(el.firstChild); }

  function addWeekendBands(container){
    let c = sod(S.min);
    while(c <= S.max){
      const day = c.getDay(); // 0 So, 6 Sa
      if(day===6 || day===0){
        const left = daysBetween(S.min, c)*S.pxPerDay;
        const w = (day===6?2:1)*S.pxPerDay;
        const band = document.createElement('div'); band.className='wknd';
        band.style.left = left+'px'; band.style.width = w+'px';
        container.appendChild(band);
      }
      c = addDays(c,1);
    }
  }
  function addWeekLines(container){
    let c = sod(S.min);
    while(c <= S.max){
      if(c.getDay()===1){ // Montag
        const left = daysBetween(S.min,c)*S.pxPerDay;
        const w = document.createElement('div'); w.className='wk'; w.style.left = left+'px';
        container.appendChild(w);
      }
      c = addDays(c,1);
    }
  }

  function drawAxis(){
    const totalDays = Math.max(1, daysBetween(S.min,S.max));
    const width = totalDays * S.pxPerDay;
    axis.style.width = width + 'px';
    clear(axis);

    // Wochenenden + Wochenlinien (bei Month/Week sichtbar sinnvoll)
    addWeekendBands(axis);
    addWeekLines(axis);

    // Monats-Ticks mit Label
    let c = new Date(S.min.getFullYear(), S.min.getMonth(), 1);
    while (c <= S.max){
      const next = new Date(c.getFullYear(), c.getMonth()+1, 1);
      const left = daysBetween(S.min, c) * S.pxPerDay;
      const tick = document.createElement('div'); tick.className='tick'; tick.style.left = left+'px';
      const label = document.createElement('div'); label.className='label';
      label.textContent = c.toLocaleDateString('de-DE',{month:'short',year:'numeric'});
      tick.appendChild(label); axis.appendChild(tick); c = next;
    }

    // Heute-Linie
    const t = sod(new Date());
    if(t >= S.min && t <= S.max){
      const line = document.createElement('div');
      line.className = 'today';
      line.style.left = (daysBetween(S.min,t)*S.pxPerDay) + 'px';
      axis.appendChild(line);
    }
  }

  window.__AXIS__ = { drawAxis };
})();
</script>

<script>
/* ---------- data normalization ---------- */
(function(){
  const { robustParse } = window.__G__;
  function normalize(t){
    const id = t.id || t.ulid || t.uuid || t.name; // fallback
    const type=(t.type||'').toLowerCase();
    const s=robustParse(t.start ?? t.starts_at ?? t.plan_start ?? t.date);
    const e=robustParse(t.end   ?? t.ends_at   ?? t.plan_end   ?? t.date);
    const status=(t.status||'open').toLowerCase();
    const progress = Number(t.progress ?? t.percent_done ?? 0);
    const name = t.name || t.title || 'Aufgabe';

    if(type==='milestone' || t.milestone){
      const d=s||e; if(!d) return null;
      return {kind:'ms',id,name,status,start:d,end:d,progress:100};
    }
    const ss=s||e, ee=e||s; if(!ss && !ee) return null;
    return {kind:'task',id,name,status,start:ss,end:ee,progress};
  }
  window.__NORM__ = { normalize };
})();
</script>

<script>
/* ---------- rendering ---------- */
(function(){
  const { S, byId, addDays, daysBetween, fmtDEhm, clamp } = window.__G__;
  const { drawAxis } = window.__AXIS__;
  const { normalize } = window.__NORM__;

  const chart = byId('chart'), rows = byId('rows');
  const linksSvg = byId('links');
  const tag = document.getElementById('gantt-data');
  let data = tag ? JSON.parse(tag.textContent) : null;
  if(!data){ chart.innerHTML = '<div style="padding:1rem">Keine Daten vorhanden.</div>'; return; }

  const itemsA = (data.tasks||[]).map(normalize).filter(Boolean);
  const itemsB = (data.milestones||[]).map(m=>normalize({...m,type:'milestone'})).filter(Boolean);
  const items = [...itemsA, ...itemsB];

  const pStart = window.__G__.robustParse(data.project?.starts_at);
  const pEnd   = window.__G__.robustParse(data.project?.ends_at);

  let min = items.length ? new Date(Math.min(...items.map(x=>x.start??x.end))) : pStart;
  let max = items.length ? new Date(Math.max(...items.map(x=>x.end??x.start)))   : pEnd;
  if(!min || !max){ chart.innerHTML = '<div style="padding:1rem">Keine datierten Daten vorhanden.</div>'; return; }

  // Ränder
  S.min = addDays(min,-6);
  S.max = addDays(max, +6);

  // DOM helpers
  function rowStripes(gridEl){
    const totalDays = Math.max(1, daysBetween(S.min,S.max));
    gridEl.style.width = (totalDays * S.pxPerDay) + 'px';
  }

  // Map für Bars (für Dependency-Linien)
  const barMap = new Map(); // id -> DOMRect infos

  function render(){
    rows.innerHTML = '';
    barMap.clear();
    drawAxis();

    const totalDays = Math.max(1, daysBetween(S.min,S.max));
    const width = totalDays * S.pxPerDay;

    const list = items.length ? items : [{kind:'task',id:'project-span',name:'Projektzeitraum',status:'open',start:min,end:max,progress:0}];

    const frag = document.createDocumentFragment();

    list.forEach((it, idx) => {
      const row = document.createElement('div'); row.className='row'; row.setAttribute('role','row');

      const durationDays = Math.max(1, daysBetween(it.start,it.end)+1);
      const cellName  = document.createElement('div'); cellName.className='cell name'; cellName.textContent = it.name || (it.kind==='ms'?'Meilenstein':'Aufgabe'); cellName.setAttribute('role','cell');
      const cellDur   = document.createElement('div'); cellDur.className='cell';      cellDur.textContent  = `${durationDays} Tag${durationDays!==1?'e':''}`; cellDur.setAttribute('role','cell');
      const cellStart = document.createElement('div'); cellStart.className='cell';    cellStart.textContent= fmtDEhm.format(it.start); cellStart.setAttribute('role','cell');
      const cellEnd   = document.createElement('div'); cellEnd.className='cell';      cellEnd.textContent  = fmtDEhm.format(it.end);   cellEnd.setAttribute('role','cell');

      const cellGrid  = document.createElement('div'); cellGrid.className='cell'; cellGrid.style.padding='0'; cellGrid.setAttribute('role','cell');
      const grid      = document.createElement('div'); grid.className='grid'; grid.style.width = width+'px';
      rowStripes(grid);

      if(it.kind==='ms'){
        const x = daysBetween(S.min,it.start)*S.pxPerDay;
        const ms = document.createElement('div'); ms.className='ms';
        ms.style.left = x+'px'; ms.title = `${it.name}\n${fmtDEhm.format(it.start)} (Meilenstein)`;
        grid.appendChild(ms);
        // speichern für Dependencies
        barMap.set(it.id || `ms-${idx}`, {kind:'ms',el:ms});
      } else {
        const left = daysBetween(S.min,it.start)*S.pxPerDay;
        const spanDays = Math.max(1, daysBetween(it.start,it.end)+1);
        const bar = document.createElement('div'); bar.className='bar';
        if(it.status==='done') bar.classList.add('done');
        if(it.status==='blocked') bar.classList.add('blocked');
        bar.style.left = left+'px'; bar.style.width = (spanDays*S.pxPerDay)+'px';
        bar.dataset.id = it.id;
        bar.setAttribute('tabindex','0');
        bar.setAttribute('aria-label', `${it.name}, ${fmtDEhm.format(it.start)} bis ${fmtDEhm.format(it.end)} Status ${it.status}`);
        bar.title = `${it.name}\n${fmtDEhm.format(it.start)} – ${fmtDEhm.format(it.end)}\nStatus: ${it.status}`;

        const p = Number(it.progress||0);
        if(!isNaN(p) && p>0){ const fill=document.createElement('div'); fill.className='fill'; fill.style.width=clamp(Math.round(p),0,100)+'%'; bar.appendChild(fill); }
        grid.appendChild(bar);

        barMap.set(it.id || `task-${idx}`, {kind:'task',el:bar});
      }

      cellGrid.appendChild(grid);
      row.appendChild(cellName);
      row.appendChild(cellDur);
      row.appendChild(cellStart);
      row.appendChild(cellEnd);
      row.appendChild(cellGrid);
      frag.appendChild(row);
    });

    rows.appendChild(frag);

    // Dependency-Linien
    drawLinks();
  }

  function drawLinks(){
    const deps = Array.isArray(data.links) ? data.links : []; // [{from,to}]
    if(!deps.length){ linksSvg.setAttribute('width','0'); linksSvg.setAttribute('height','0'); linksSvg.innerHTML=''; return; }

    // Größe auf gesamte Timeline setzen
    const totalDays = Math.max(1, daysBetween(S.min,S.max));
    const timelineWidth = totalDays * S.pxPerDay;
    const totalRows = rows.children.length;
    const timelineHeight = totalRows * parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--rowH'));

    linksSvg.setAttribute('width', timelineWidth);
    linksSvg.setAttribute('height', timelineHeight);
    linksSvg.innerHTML = '';

    const chartRect = chart.getBoundingClientRect();

    function centerRight(rect){ return { x: rect.left - chartRect.left + rect.width, y: rect.top - chartRect.top + rect.height/2 }; }
    function centerLeft(rect){ return { x: rect.left - chartRect.left, y: rect.top - chartRect.top + rect.height/2 }; }

    deps.forEach(link=>{
      const a = barMap.get(link.from), b = barMap.get(link.to);
      if(!a || !b) return;

      const ra = a.el.getBoundingClientRect();
      const rb = b.el.getBoundingClientRect();
      const A = centerRight(ra);
      const B = centerLeft(rb);

      const dx = Math.max(20, (B.x - A.x)/2);
      const path = document.createElementNS('http://www.w3.org/2000/svg','path');
      path.setAttribute('d', `M ${A.x},${A.y} C ${A.x+dx},${A.y} ${B.x-dx},${B.y} ${B.x},${B.y}`);
      path.setAttribute('fill','none');
      path.setAttribute('stroke','#64748b');
      path.setAttribute('stroke-width','1.5');

      const arrow = document.createElementNS('http://www.w3.org/2000/svg','path');
      arrow.setAttribute('d', `M ${B.x-6},${B.y-4} L ${B.x},${B.y} L ${B.x-6},${B.y+4} Z`);
      arrow.setAttribute('fill','#64748b');

      linksSvg.appendChild(path);
      linksSvg.appendChild(arrow);
    });
  }

  window.__RENDER__ = { render, drawLinks };
})();
</script>

<script>
/* ---------- interactions (zoom/pan/controls/keyboard) ---------- */
(function(){
  const { S, byId, sod, daysBetween, clamp } = window.__G__;
  const { render, drawLinks } = window.__RENDER__;
  const { drawAxis } = window.__AXIS__;
  const chart = byId('chart');

  // Controls
  const btnIn=byId('zoom-in'), btnOut=byId('zoom-out'), btnFit=byId('fit'), btnToday=byId('today'), btnPrint=byId('print');
  const bDay=byId('scale-day'), bWeek=byId('scale-week'), bMonth=byId('scale-month');

  function setScale(scale){
    S.scale = scale;
    bDay.setAttribute('aria-pressed', scale==='day'); bWeek.setAttribute('aria-pressed', scale==='week'); bMonth.setAttribute('aria-pressed', scale==='month');
    if(scale==='day')   S.pxPerDay = clamp(S.pxPerDay, 18, 60);
    if(scale==='week')  S.pxPerDay = 12*7; // 12px pro Tag ~ 84px/Woche
    if(scale==='month') S.pxPerDay = 6*30; // 6px pro Tag ~ 180px/Monat
    render();
  }

  bDay.onclick  = ()=> setScale('day');
  bWeek.onclick = ()=> setScale('week');
  bMonth.onclick= ()=> setScale('month');

  btnIn.onclick  = ()=>{ S.pxPerDay = clamp(S.pxPerDay+4, 8, 80); render(); };
  btnOut.onclick = ()=>{ S.pxPerDay = clamp(S.pxPerDay-4, 8, 80); render(); };
  btnFit.onclick = ()=>{ chart.scrollTo({left:0,behavior:'smooth'}); };
  btnToday.onclick = ()=>{
    const rows = document.getElementById('rows');
    const t = sod(new Date());
    if(S.min && S.max && t>=S.min && t<=S.max){
      const left = daysBetween(S.min,t)*S.pxPerDay - chart.clientWidth/2;
      chart.scrollTo({left:Math.max(0,left),behavior:'smooth'});
    }
    // leichte Hervorhebung via re-render zeichnet Today-Linie neu
    drawAxis(); drawLinks();
  };
  btnPrint.onclick = ()=> window.print();

  // Mouse drag to pan
  let isDown=false, startX=0, startScroll=0;
  chart.addEventListener('mousedown', (e)=>{ isDown=true; startX=e.clientX; startScroll=chart.scrollLeft; chart.style.cursor='grabbing'; });
  chart.addEventListener('mouseleave', ()=>{ isDown=false; chart.style.cursor='default'; });
  chart.addEventListener('mouseup', ()=>{ isDown=false; chart.style.cursor='default'; drawLinks(); });
  chart.addEventListener('mousemove', (e)=>{ if(!isDown) return; const dx=e.clientX - startX; chart.scrollLeft = startScroll - dx; });

  // Wheel zoom around cursor (Ctrl/Meta or trackpad pinch events seen as ctrlKey)
  chart.addEventListener('wheel', (e)=>{
    if(!e.ctrlKey) return;
    e.preventDefault();
    const old = S.pxPerDay;
    const rect = chart.getBoundingClientRect();
    const x = e.clientX - rect.left + chart.scrollLeft;
    const dayAtCursor = x / old;

    S.pxPerDay = clamp(S.pxPerDay * (e.deltaY < 0 ? 1.1 : 0.9), 8, 100);
    render();

    const newScrollLeft = dayAtCursor * S.pxPerDay - (e.clientX - rect.left);
    chart.scrollLeft = Math.max(0, newScrollLeft);
  }, {passive:false});

  // Keep dependency lines in sync on scroll/resize
  chart.addEventListener('scroll', ()=> drawLinks());
  window.addEventListener('resize', ()=> drawLinks());

  // Keyboard shortcuts
  document.addEventListener('keydown',(e)=>{
    if(e.key==='+' || e.key==='='){ btnIn.click(); }
    if(e.key==='-' ){ btnOut.click(); }
    if(e.key==='t' || e.key==='T'){ btnToday.click(); }
    if(e.key==='f' || e.key==='F'){ btnFit.click(); }
    if(e.key==='ArrowLeft'){ chart.scrollLeft -= 120; }
    if(e.key==='ArrowRight'){ chart.scrollLeft += 120; }
  });

  // Initial render
  render();
})();
</script>
</div>

</body>
</html>
