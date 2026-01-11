<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Consolidado das Farmácias</title>

  <!-- Favicons -->
  <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="96x96" href="favicon-96x96.png">
  <link rel="icon" type="image/svg+xml" href="favicon.svg">
  <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
  <link rel="shortcut icon" href="favicon.ico">
  <link rel="manifest" href="site.webmanifest">
  <meta name="theme-color" content="#1976d2">

  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    :root{
      --card-bg:#fff; --bg:#eee; --shadow:0 1px 3px rgba(0,0,0,.08); --radius:10px;
      --verde:#2e7d32; --vermelho:#c62828; --laranja:#ef6c00;
      --cinza:#e5e7eb; --cinza-escuro:#9aa1a9;
    }
    body{ font-family:Arial, sans-serif; background:var(--bg); margin:0; padding:20px; }

    .topbar{ display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:10px; }
    .topbar-left{ display:flex; gap:10px; align-items:center; }
    .topbar h1{ margin:0; font-size:1.4rem; }
    .topbar-right{ display:flex; align-items:center; gap:12px; }
    .logo{ height:46px; object-fit:contain; }
    .user-box{ display:flex; gap:6px; align-items:center; font-size:.85rem; }
    .logout-btn{ text-decoration:none; background:#c62828; color:#fff; padding:6px 10px; border-radius:6px; font-size:.78rem; }

    /* STATUS */
    .status-bar{ display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px; }
    .status-pill{
      display:inline-flex; align-items:center; justify-content:center; gap:5px;
      padding:4px 12px; border-radius:9999px; font-size:.78rem; font-weight:500;
      background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; box-shadow:0 1px 2px rgba(0,0,0,.05);
    }
    .status-pill::before{ content:""; width:8px; height:8px; border-radius:50%; background:#43a047; }
    .status-pill.ok{ background:#e8f5e9; color:#2e7d32; border-color:#c8e6c9; }
    .status-pill.erro{ background:#ffebee; color:#c62828; border-color:#ffcdd2; }
    .status-pill.erro::before{ background:#e53935; }

    /* FILTROS */
    .filters{
      margin-bottom:15px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;
      background:#fff; padding:10px 12px; border-radius:10px; box-shadow:var(--shadow);
    }
    .filters label{ font-size:.85rem; display:flex; gap:5px; align-items:center; }
    .filters input[type="date"], .filters select{ padding:4px 6px; border-radius:6px; border:1px solid #ccc; }
    .filters button{ padding:5px 10px; border:none; background:#1565c0; color:#fff; border-radius:6px; cursor:pointer; }

    /* Multi-loja */
    .lojas-group{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; padding:6px 8px; background:#f8f9fb; border-radius:8px; border:1px solid #e6e8eb; }
    .loja-check{ display:flex; align-items:center; gap:6px; background:#fff; padding:4px 8px; border-radius:8px; border:1px solid #e6e8eb; box-shadow:var(--shadow); font-size:.85rem; }
    .loja-check input{ transform:translateY(1px); }

    /* CARDS */
    .cards{ display:flex; gap:15px; flex-wrap:wrap; margin-bottom:20px; }
    .card{ background:#fff; padding:12px 16px; border-radius:10px; box-shadow:var(--shadow); min-width:210px; flex:1 1 160px; }
    .card small{ display:block; color:#777; margin-bottom:4px; }
    .card strong{ font-size:1.1rem; }

    /* ORÇAMENTO (dotação / consumo) */
    .orcamento-section{ margin-bottom:20px; }
    .orcamento-wrap{ background:#fff; padding:12px 16px; border-radius:10px; box-shadow:var(--shadow); }
    .orcamento-grid{ display:grid; grid-template-columns: 260px 1fr; gap:14px; align-items:stretch; }
    @media (max-width: 900px){
      .orcamento-grid{ grid-template-columns:1fr; }
    }
    .gauge-box{ display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; border:1px solid #eee; border-radius:10px; padding:10px; }
    .gauge-percent{ font-size:1.6rem; font-weight:700; }
    .gauge-sub{ font-size:.85rem; color:#666; }
    .orc-table{ width:100%; border-collapse:collapse; font-size:.85rem; }
    .orc-table th, .orc-table td{ padding:8px 10px; border-bottom:1px solid #eee; white-space:nowrap; }
    .orc-table th{ text-align:left; background:#f7f7f7; }
    .orc-table td{ text-align:right; }
    .orc-table td:first-child{ text-align:left; }
    .util-pill{ display:inline-block; padding:3px 8px; border-radius:9999px; font-weight:700; }
    .util-ok{ background:#e8f5e9; color:#2e7d32; }
    .util-mid{ background:#fff8e1; color:#ef6c00; }
    .util-high{ background:#ffebee; color:#c62828; }

    /* TABELAS */
    .table-scroll{ width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; }
    table{ width:100%; border-collapse:collapse; background:#fff; border-radius:10px; box-shadow:var(--shadow); margin-bottom:20px; font-size:.85rem; }
    th,td{ padding:8px 10px; border-bottom:1px solid #eee; text-align:right; white-space:nowrap; }
    th:first-child, td:first-child{ text-align:left; }
    th{ background:#f7f7f7; } tfoot th{ background:#eaeaea; }

    .col-lucro,.col-lucro-pct{ color:var(--verde); font-weight:600; }
    .col-desconto,.col-desconto-pct{ color:var(--vermelho); font-weight:600; }
    .col-cmv,.col-cmv-pct{ color:var(--laranja); font-weight:600; }
    .col-vl{ color:#1565c0; font-weight:600; }

    /* Barrinha % */
    .progress-cell{ text-align:right; }
    .progress-wrap{
      display:inline-block; width:120px; height:8px; margin-left:8px; vertical-align:middle;
      background:var(--cinza); border-radius:999px; overflow:hidden; box-shadow:inset 0 0 0 1px rgba(0,0,0,.04);
    }
    .progress-bar{
      display:block; height:100%; width:0%; background:#2e7d32; border-radius:999px;
    }

    /* GRÁFICOS */
    .charts-row, .charts-row-wide{ display:flex; gap:15px; flex-wrap:wrap; margin-top:15px; }
    .chart-box, .chart-wide{ background:#fff; border-radius:10px; box-shadow:var(--shadow); padding:8px; flex:1 1 210px; min-width:240px; }
    .chart-wide{ min-width:320px; }
    .chart-box h4, .chart-wide h4{ margin:4px 0 6px; font-size:.85rem; }
    .chart-box canvas{ height:140px !important; }
    .chart-wide canvas{ height:230px !important; }

    .block-table{ margin-top:15px; }
    .block-table h3{ margin:5px 0; }

    @media (max-width:768px){
      body{ padding:12px; }
      .topbar{ flex-direction:column; align-items:flex-start; }
      table{ font-size:.78rem; }
      .charts-row, .charts-row-wide{ flex-direction:column; }
      .progress-wrap{ width:96px; }
    }
  </style>
</head>
<body>
  <div class="topbar">
    <div class="topbar-left"><h1>Consolidado das Farmácias</h1></div>
    <div class="topbar-right">
      <img src="images/logocombempopular.png" alt="Logo" class="logo">
      <div class="user-box">
        <span>Olá, <?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
        <a class="logout-btn" href="logout.php">Sair</a>
      </div>
    </div>
  </div>

  <!-- status -->
  <div id="status-lojas" class="status-bar"></div>

  <!-- filtros -->
  <div class="filters">
    <form id="formData" style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
      <label>Data: <input type="date" id="dataBusca" name="dataBusca" /></label>
      <label>Período:
        <select id="filtroPeriodo">
          <option value="dia">Dia</option>
          <option value="mes">Mês</option>
        </select>
      </label>
      <button type="submit">Buscar</button>
    </form>
    <div class="lojas-group" id="lojasGroup"></div>
  </div>

  <div class="cards" id="cards"></div>

  <div id="orcamento-section" class="orcamento-section"></div>

  <!-- tabela dia -->
  <h2>Vendas do dia</h2>
  <div class="table-scroll">
    <table id="tabela-dia">
      <thead>
        <tr>
          <th>Loja</th>
          <th>Venda Bruta</th>
          <th>Venda Líquida</th>
          <th>% Lucro</th>
          <th>Lucro</th>
          <th>CMV (R$)</th>
          <th>% CMV</th>
          <th>Atend.</th>
          <th>Ticket</th>
          <th>% Desconto</th>
          <th>Descontos</th>
          <th>Estornos</th>
          <th>Devoluções</th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot></tfoot>
    </table>
  </div>

  <!-- tabela mês -->
  <h2 style="margin-top:25px;">Acumulado do mês</h2>
  <div class="table-scroll">
    <table id="tabela-mes">
      <thead>
        <tr>
          <th>Loja</th>
          <th>VB mês</th>
          <th>VL mês</th>
          <th>Prev. venda</th>
          <th>Prev. lucro</th>
          <th>Prev. lucro (%)</th>
          <th>Lucro (%)</th>
          <th>Lucro (R$)</th>
          <th>CMV (R$)</th>
          <th>CMV (%)</th>
          <th>Atend.</th>
          <th>Ticket</th>
          <th>Desc. (R$)</th>
          <th>Desc. (%)</th>
          <th>Devoluções</th>
          <th>Estornos</th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot></tfoot>
    </table>
  </div>

  <!-- gráficos -->
  <div class="charts-row">
    <div class="chart-box"><h4>Venda Líquida</h4><canvas id="graf-venda"></canvas></div>
    <div class="chart-box"><h4>Lucro %</h4><canvas id="graf-lucro-pct"></canvas></div>
    <div class="chart-box"><h4>Ticket médio</h4><canvas id="graf-ticket"></canvas></div>
    <div class="chart-box"><h4>Atendimentos</h4><canvas id="graf-atend"></canvas></div>
  </div>

  <div class="charts-row-wide">
    <div class="chart-wide"><h4>Vendas por classe (todas as lojas selecionadas)</h4><canvas id="graf-classes"></canvas></div>
    <div class="chart-wide"><h4>Top 3 vendedores (todas as lojas selecionadas)</h4><canvas id="graf-topvend"></canvas></div>
  </div>

  <!-- detalhamento -->
  <h2 style="margin-top:20px;">Vendas por classe (por loja)</h2>
  <div id="areaClasses"></div>

  <h2 style="margin-top:20px;">Top 5 vendedores (por loja)</h2>
  <div id="areaVendedores"></div>

  <script>
    // ---------- LOJAS ----------
    const LOJAS = [
      { id:'sapezal',  label:'Sapezal',  path:'sapezal'  },
      { id:'pbcentro', label:'Pimenta',  path:'pbcentro' },
      { id:'alvorada', label:'Alvorada', path:'alvorada' }
    ];

    // aliases: como as APIs podem retornar o nome
    const LOJA_ALIASES = {
      'pimenta': 'pbcentro',
      'pbcentro': 'pbcentro',
      'sapezal': 'sapezal',
      'alvorada': 'alvorada'
    };

    function normalizeLojaId(v) {
      const key = String(v || '').toLowerCase().trim();
      return LOJA_ALIASES[key] || key;
    }

    function titleCase(s) {
      return String(s || '')
        .toLowerCase()
        .replace(/\b\w/g, c => c.toUpperCase());
    }

    function getLojaById(id){ return LOJAS.find(l=>l.id===id); }

    function resolveLojaLabel(dados) {
      const raw = dados?.loja || dados?.loja_id || dados?.loja_nome || '';
      const norm = normalizeLojaId(raw);
      const found = getLojaById(norm);
      if (found) return found.label;
      if (dados?.loja_nome) return titleCase(dados.loja_nome);
      return titleCase(raw || norm);
    }

    // formatação global
    const PCT_DECIMALS   = 2;
    const MONEY_DECIMALS = 2;

    function fmtPct(x) {
      return Number(x || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: PCT_DECIMALS,
        maximumFractionDigits: PCT_DECIMALS,
      }) + '%';
    }

    function fmtMoney(v) {
      return Number(v || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: MONEY_DECIMALS,
        maximumFractionDigits: MONEY_DECIMALS,
      });
    }

    // checkboxes
    const lojasGroup = document.getElementById('lojasGroup');
    lojasGroup.innerHTML = `
      <div class="loja-check">
        <input type="checkbox" id="todasLojas" checked />
        <label for="todasLojas"><strong>Selecionar todas</strong></label>
      </div>
      ${LOJAS.map(l=>`
        <label class="loja-check">
          <input type="checkbox" class="lojaSel" data-id="${l.id}" checked />
          <span>${l.label}</span>
        </label>`).join('')}
    `;

    const statusBar = document.getElementById('status-lojas');

    // helpers
    function hojeLocalISO(){
      const d = new Date();
      return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    }
    function getSelectedLojas(){
      const all = document.getElementById('todasLojas');
      const checks = Array.from(document.querySelectorAll('.lojaSel'));
      if(all.checked) return LOJAS.map(l=>l.id);
      return checks.filter(c=>c.checked).map(c=>c.dataset.id);
    }

    // selecionar todas / individuais
    document.getElementById('todasLojas').addEventListener('change', e=>{
      document.querySelectorAll('.lojaSel').forEach(c=>c.checked=e.target.checked);
      iniciarCarregamento();
    });
    document.querySelectorAll('.lojaSel').forEach(c=>{
      c.addEventListener('change', ()=>{
        const checks = Array.from(document.querySelectorAll('.lojaSel'));
        document.getElementById('todasLojas').checked = checks.every(x=>x.checked);
        iniciarCarregamento();
      });
    });

    // cache + abort
    const cache = new Map();
    let controllersAtivos = [];
    function abortarBuscasAtuais(){ controllersAtivos.forEach(c=>c.abort()); controllersAtivos=[]; }
    async function fetchJsonSeguroAbort(url, signal){
      // Cache simples para evitar refetch em sequência.
      // IMPORTANTE: não cachear resposta inválida (null) para não travar a UI.
      if(cache.has(url)) return cache.get(url);

      const res = await fetch(url, {signal});
      const txt = await res.text();

      // tenta parsear JSON de forma robusta (removendo BOM / warnings antes do JSON)
      const tryParse = (raw)=>{
        if(!raw) return null;
        // remove BOM
        raw = raw.replace(/^\uFEFF/, '');
        // tenta parse direto
        try{ return JSON.parse(raw); }catch(e){}
        // tenta extrair o primeiro objeto/array JSON no meio do texto
        const firstObj = raw.indexOf('{');
        const lastObj  = raw.lastIndexOf('}');
        if(firstObj !== -1 && lastObj !== -1 && lastObj > firstObj){
          const sub = raw.slice(firstObj, lastObj+1);
          try{ return JSON.parse(sub); }catch(e){}
        }
        const firstArr = raw.indexOf('[');
        const lastArr  = raw.lastIndexOf(']');
        if(firstArr !== -1 && lastArr !== -1 && lastArr > firstArr){
          const sub = raw.slice(firstArr, lastArr+1);
          try{ return JSON.parse(sub); }catch(e){}
        }
        return null;
      };

      const json = tryParse(txt);

      // Se a resposta não é OK, ainda assim devolve o JSON (se houver) para debug.
      // Não cacheia resposta inválida.
      if(json !== null){
        cache.set(url, json);
      } else {
        console.warn('Resposta não-JSON em', url, txt.slice(0, 200));
      }
      return json;
    }
    function montarUrlDia(id, dataISO){ return `${getLojaById(id).path}/api_totais.php?data=${dataISO}`; }
    function montarUrlMes(id, dataISO){ return `${getLojaById(id).path}/api_totais.php?mes=${dataISO.slice(0,7)}`; }
    function montarUrlOrcamento(lojasIds, dataISO){
      const mes = dataISO.slice(0,7);
      const lojasParam = encodeURIComponent(lojasIds.join(","));
      return `api/orcamento.php?mes=${mes}&lojas=${lojasParam}`;
    }


    // estado
    let dadosDiaMap = {};
    let dadosMesMap = {};
    let orcamentoData = null;


    // status UI
    function setStatusLoja(id, estado, msg=''){
      let pill = statusBar.querySelector(`[data-loja="${id}"]`);
      const rotulo = (getLojaById(id)||{}).label || titleCase(id);
      if(!pill){
        pill = document.createElement('div');
        pill.className='status-pill';
        pill.dataset.loja=id;
        statusBar.appendChild(pill);
      }
      if(estado==='loading'){ pill.className='status-pill'; pill.innerHTML=`<span>${rotulo}</span><span>Carregando…</span>`; }
      else if(estado==='ok'){ pill.className='status-pill ok'; pill.innerHTML=`<span>${rotulo}</span><span>OK</span>`; }
      else { pill.className='status-pill erro'; pill.innerHTML=`<span>${rotulo}</span><span>${msg||'Erro'}</span>`; }
    }
    function resetStatusBar(selected){ statusBar.innerHTML=''; selected.forEach(id=>setStatusLoja(id,'loading')); }

    // inputs
    const inputData = document.getElementById('dataBusca');
    const selectPeriodo = document.getElementById('filtroPeriodo');
    inputData.value = hojeLocalISO();

    document.getElementById('formData').addEventListener('submit', e=>{ e.preventDefault(); iniciarCarregamento(); });
    selectPeriodo.addEventListener('change', ()=>{ scheduleRender(); });

    // charts
    let grafVenda, grafLucroPct, grafTicket, grafAtend, grafClasses, grafTopVend;
    function destroyCharts(){ [grafVenda,grafLucroPct,grafTicket,grafAtend,grafClasses,grafTopVend].forEach(g=>g&&g.destroy&&g.destroy()); grafVenda=grafLucroPct=grafTicket=grafAtend=grafClasses=grafTopVend=null; }
    function dynamicColors(n){ const a=[]; for(let i=0;i<n;i++){ const h=Math.round((360/Math.max(n,1))*i); a.push(`hsl(${h} 70% 60% / 0.6)`);} return a; }
    function dynamicBorderColors(n){ const a=[]; for(let i=0;i<n;i++){ const h=Math.round((360/Math.max(n,1))*i); a.push(`hsl(${h} 70% 45% / 1)`);} return a; }

    // cores fixas por classe
    function hashCode(str){ let h=0; for(let i=0;i<str.length;i++){ h=((h<<5)-h)+str.charCodeAt(i); h|=0; } return Math.abs(h); }
    function colorForClass(nome){ const b=hashCode((nome||'').toLowerCase().trim()); const hue=b%360; return `hsl(${hue} 70% 55% / 0.85)`; }
    function colorForClassSolid(nome){ const b=hashCode((nome||'').toLowerCase().trim()); const hue=b%360; return `hsl(${hue} 70% 45%)`; }

    // THROTTLE de render (150ms)
    let renderTimer = null;
    function scheduleRender(){
      if (renderTimer) return;
      renderTimer = setTimeout(()=>{
        renderTimer = null;
        renderPainel(getSelectedLojas(), document.getElementById('filtroPeriodo').value);
      }, 150);
    }

    // render
    function renderPainel(lojasSelecionadas, periodo='dia'){
      try{
        const tbodyDia   = document.querySelector('#tabela-dia tbody');
        const tfootDia   = document.querySelector('#tabela-dia tfoot');
        const tbodyMes   = document.querySelector('#tabela-mes tbody');
        const tfootMes   = document.querySelector('#tabela-mes tfoot');
        const cards      = document.getElementById('cards');
        const areaClasses = document.getElementById('areaClasses');
        const areaVendedores = document.getElementById('areaVendedores');

        tbodyDia.innerHTML=''; tfootDia.innerHTML='';
        tbodyMes.innerHTML=''; tfootMes.innerHTML='';
        areaClasses.innerHTML=''; areaVendedores.innerHTML='';

        const fonteDia = lojasSelecionadas.map(id=>dadosDiaMap[id]).filter(Boolean);
        const fonteMes = lojasSelecionadas.map(id=>dadosMesMap[id]).filter(Boolean);
        const fontePrincipal = (periodo==='dia') ? fonteDia : fonteMes;

        let totVendaBruta=0, totVendaLiq=0, totLucro=0, totCmv=0;
        let totDesc=0, totEst=0, totDev=0, totAtend=0;

        const lojas=[], vendasLiq=[], lucrosPct=[], tickets=[], atendArray=[];
        const mapaClasses={}; // agregado p/ gráfico geral
        const todosVendedores=[];

        fontePrincipal.forEach(dados=>{
          const lojaId = normalizeLojaId(dados?.loja_id || dados?.loja || '');
          const lojaLabel = resolveLojaLabel(dados);

          const vendaBruta=Number(dados?.venda_bruta||0);
          const vendaLiq  =Number(dados?.venda_liquida||0);
          const descontos =Number(dados?.descontos||(vendaBruta - vendaLiq)||0);
          const cmv       =Number(dados?.cmv||0);
          const lucro     =Number(dados?.lucro||(vendaLiq - cmv)||0);
          const est       =Number(dados?.estornos||0);
          const dev       =Number(dados?.devolucoes||0);
          const atend     =Number(dados?.atendimentos||0);

          const percLucro = vendaLiq>0 ? (lucro/vendaLiq)*100 : 0;
          const percDesc  = vendaBruta>0 ? (descontos/vendaBruta)*100 : 0;
          const percCmv   = vendaLiq>0 ? (cmv/vendaLiq)*100 : 0;
          const ticket    = atend>0 ? (vendaLiq/atend) : 0;

          const tr=document.createElement('tr');
          tr.innerHTML=`
            <td>${lojaLabel}</td>
            <td>${fmtMoney(vendaBruta)}</td>
            <td class="col-vl">${fmtMoney(vendaLiq)}</td>
            <td class="col-lucro-pct">${fmtPct(percLucro)}</td>
            <td class="col-lucro">${fmtMoney(lucro)}</td>
            <td class="col-cmv">${fmtMoney(cmv)}</td>
            <td class="col-cmv-pct">${fmtPct(percCmv)}</td>
            <td>${atend}</td>
            <td>${fmtMoney(ticket)}</td>
            <td class="col-desconto-pct">${fmtPct(percDesc)}</td>
            <td class="col-desconto">${fmtMoney(descontos)}</td>
            <td>${fmtMoney(est)}</td>
            <td>${fmtMoney(dev)}</td>
          `;
          tbodyDia.appendChild(tr);

          totVendaBruta+=vendaBruta; totVendaLiq+=vendaLiq; totLucro+=lucro; totCmv+=cmv;
          totDesc+=descontos; totEst+=est; totDev+=dev; totAtend+=atend;

          lojas.push(lojaLabel); vendasLiq.push(vendaLiq); lucrosPct.push(percLucro); tickets.push(ticket); atendArray.push(atend);

          (dados?.classes||[]).forEach(cls=>{
            const nome = cls.nome_classe || 'Sem classe';
            const val  = Number(cls.venda_liq || 0);
            mapaClasses[nome] = (mapaClasses[nome]||0) + val;
          });

          (dados?.vendedores||[]).forEach(v=>{
            todosVendedores.push({ loja:lojaLabel, apelido:v.apelido||'Sem nome', venda:Number(v.venda_liq||0) });
          });
        });

        const totPercLucro = totVendaLiq>0 ? (totLucro/totVendaLiq)*100 : 0;
        const totPercDesc  = totVendaBruta>0 ? (totDesc/totVendaBruta)*100 : 0;
        const totPercCmv   = totVendaLiq>0 ? (totCmv/totVendaLiq)*100 : 0;
        const totTicket    = totAtend>0 ? (totVendaLiq/totAtend) : 0;

        tfootDia.innerHTML=`
          <tr>
            <th>Total</th>
            <th>${fmtMoney(totVendaBruta)}</th>
            <th class="col-vl">${fmtMoney(totVendaLiq)}</th>
            <th class="col-lucro-pct">${fmtPct(totPercLucro)}</th>
            <th class="col-lucro">${fmtMoney(totLucro)}</th>
            <th class="col-cmv">${fmtMoney(totCmv)}</th>
            <th class="col-cmv-pct">${fmtPct(totPercCmv)}</th>
            <th>${totAtend}</th>
            <th>${fmtMoney(totTicket)}</th>
            <th class="col-desconto-pct">${fmtPct(totPercDesc)}</th>
            <th class="col-desconto">${fmtMoney(totDesc)}</th>
            <th>${fmtMoney(totEst)}</th>
            <th>${fmtMoney(totDev)}</th>
          </tr>
        `;

        // --------- MÊS ---------
        const dataFiltro = document.getElementById('dataBusca').value || hojeLocalISO();
        const dataRef = new Date(dataFiltro+'T00:00:00');
        const diaAtual = dataRef.getDate();
        const diasNoMes = new Date(dataRef.getFullYear(), dataRef.getMonth()+1, 0).getDate();

        let totVBMes=0, totVLMes=0, totDescMes=0, totCMVMes=0, totLucroMes=0, totDevMes=0, totEstMes=0, totAtendMes=0;
        let totPrevVenda=0, totPrevLucro=0;

        const fonteMesEfetiva = (periodo==='dia') ? fonteMes : fontePrincipal;

        fonteMesEfetiva.forEach(d=>{
          const lojaId = normalizeLojaId(d.loja_id || d.loja || '');
          const lojaLabel = resolveLojaLabel(d);

          const vbMes=Number(d.venda_bruta||0);
          const vlMes=Number(d.venda_liquida||d.venda_mes||0);
          const cmvMes=Number(d.cmv||0);
          const lucroMes=Number(d.lucro||0);
          const devMes=Number(d.devolucoes||0);
          const estMes=Number(d.estornos||0);
          const atMes=Number(d.atendimentos||0);

          const descMes = vbMes - vlMes;
          const descPctMes = vbMes>0 ? (descMes/vbMes)*100 : 0;
          const cmvPctMes  = vlMes>0 ? (cmvMes/vlMes)*100 : 0;
          const lucroPctMes= vlMes>0 ? (lucroMes/vlMes)*100 : 0;
          const ticketMes  = atMes>0 ? (vlMes/atMes) : 0;

          const prevVenda = d.previsao_mes ? Number(d.previsao_mes) : (diaAtual>0 ? (vlMes/diaAtual)*diasNoMes : vlMes);
          const prevLucro = diaAtual>0 ? (lucroMes/diaAtual)*diasNoMes : lucroMes;
          const prevLucroPct = prevVenda>0 ? (prevLucro/prevVenda)*100 : 0;

          const trm=document.createElement('tr');
          trm.innerHTML=`
            <td>${lojaLabel}</td>
            <td>${fmtMoney(vbMes)}</td>
            <td>${fmtMoney(vlMes)}</td>
            <td>${fmtMoney(prevVenda)}</td>
            <td>${fmtMoney(prevLucro)}</td>
            <td>${fmtPct(prevLucroPct)}</td>
            <td class="col-lucro-pct">${fmtPct(lucroPctMes)}</td>
            <td class="col-lucro">${fmtMoney(lucroMes)}</td>
            <td class="col-cmv">${fmtMoney(cmvMes)}</td>
            <td class="col-cmv-pct">${fmtPct(cmvPctMes)}</td>
            <td>${atMes}</td>
            <td>${fmtMoney(ticketMes)}</td>
            <td class="col-desconto">${fmtMoney(descMes)}</td>
            <td class="col-desconto-pct">${fmtPct(descPctMes)}</td>
            <td>${fmtMoney(devMes)}</td>
            <td>${fmtMoney(estMes)}</td>
          `;
          tbodyMes.appendChild(trm);

          totVBMes+=vbMes; totVLMes+=vlMes; totDescMes+=descMes; totCMVMes+=cmvMes;
          totLucroMes+=lucroMes; totDevMes+=devMes; totEstMes+=estMes; totAtendMes+=atMes;
          totPrevVenda+=prevVenda; totPrevLucro+=prevLucro;
        });

        const totDescPctMes = totVBMes>0 ? (totDescMes/totVBMes)*100 : 0;
        const totCMVPctMes  = totVLMes>0 ? (totCMVMes/totVLMes)*100 : 0;
        const totLucroPctMes= totVLMes>0 ? (totLucroMes/totVLMes)*100 : 0;
        const totTicketMes  = totAtendMes>0 ? (totVLMes/totAtendMes) : 0;
        const totPrevLucroPct = totPrevVenda>0 ? (totPrevLucro/totPrevVenda)*100 : 0;

        tfootMes.innerHTML=`
          <tr>
            <th>Total</th>
            <th>${fmtMoney(totVBMes)}</th>
            <th>${fmtMoney(totVLMes)}</th>
            <th>${fmtMoney(totPrevVenda)}</th>
            <th>${fmtMoney(totPrevLucro)}</th>
            <th>${fmtPct(totPrevLucroPct)}</th>
            <th class="col-lucro-pct">${fmtPct(totLucroPctMes)}</th>
            <th class="col-lucro">${fmtMoney(totLucroMes)}</th>
            <th class="col-cmv">${fmtMoney(totCMVMes)}</th>
            <th class="col-cmv-pct">${fmtPct(totCMVPctMes)}</th>
            <th>${totAtendMes}</th>
            <th>${fmtMoney(totTicketMes)}</th>
            <th class="col-desconto">${fmtMoney(totDescMes)}</th>
            <th class="col-desconto-pct">${fmtPct(totDescPctMes)}</th>
            <th>${fmtMoney(totDevMes)}</th>
            <th>${fmtMoney(totEstMes)}</th>
          </tr>
        `;

        // Cards (mostrar hífen quando mês ainda não chegou e período=dia)
        const labelPeriodo = periodo==='dia' ? 'dia' : 'mês';
        const mesChegou = (periodo==='mes') || Object.keys(dadosMesMap).length > 0;
        const vlMesCard  = mesChegou ? `R$ ${fmtMoney(totVLMes)}` : '—';
        const prevMesCard= mesChegou ? `R$ ${fmtMoney(totPrevVenda)}` : '—';

        // Dotação Orçamentária (mês) - baseado nas lojas selecionadas (checkbox)
        const dotacaoCard = (orcamentoData && orcamentoData.consolidado) ? `R$ ${fmtMoney(orcamentoData.consolidado.dotacao||0)}` : "—";
        const fatorVendasCard = (orcamentoData && orcamentoData.consolidado) ? fmtPct((orcamentoData.consolidado.percent_fator||100)) : "—";
        const fatorCalendarioCard = (orcamentoData && orcamentoData.consolidado) ? fmtPct(((orcamentoData.consolidado.f2_score||1)*100)) : "—";
        const ajusteManualCard = (orcamentoData && orcamentoData.consolidado) ? `R$ ${fmtMoney(orcamentoData.consolidado.f3_ajuste||0)}` : "—";
        const comprasMesCard = (orcamentoData && orcamentoData.consolidado) ? `R$ ${fmtMoney(orcamentoData.consolidado.compras_mes||0)}` : "—";
        const consumidoPctCard = (orcamentoData && orcamentoData.consolidado) ? fmtPct((orcamentoData.consolidado.percent_consumido||0)) : "—";


        cards.innerHTML = `
          <div class="card"><small>Dotação Orçamentária (mês)</small><strong>${dotacaoCard}</strong></div>
          <div class="card"><small>Compras (mês)</small><strong>${comprasMesCard}</strong></div>
          <div class="card"><small>Total consumido (%)</small><strong>${consumidoPctCard}</strong></div>
          <div class="card"><small>Fator vendas (F1)</small><strong>${fatorVendasCard}</strong></div>
          <div class="card"><small>Fator calendário (F2)</small><strong>${fatorCalendarioCard}</strong></div>
          <div class="card"><small>Ajuste manual (F3)</small><strong>${ajusteManualCard}</strong></div>

          <div class="card"><small>Venda Líquida (${labelPeriodo})</small><strong>R$ ${fmtMoney(totVendaLiq)}</strong></div>
          <div class="card"><small>Lucro (${labelPeriodo})</small><strong>R$ ${fmtMoney(totLucro)}</strong></div>
          <div class="card"><small>CMV (%) ${labelPeriodo}</small><strong>${fmtPct(totPercCmv)}</strong></div>
          <div class="card"><small>Ticket médio (${labelPeriodo})</small><strong>R$ ${fmtMoney(totTicket)}</strong></div>
          <div class="card"><small>Venda Líq. (mês)</small><strong>${vlMesCard}</strong></div>
          <div class="card"><small>Prev. faturamento (mês)</small><strong>${prevMesCard}</strong></div>
        `;

        // Seção de Orçamento (gauge + tabela por categoria)
        const orcSec = document.getElementById('orcamento-section');
        if (orcSec) {
          if (orcamentoData && orcamentoData.consolidado) {
            const cons = Number(orcamentoData.consolidado.percent_consumido || 0);
            const dot = Number(orcamentoData.consolidado.dotacao || 0);
            const comp = Number(orcamentoData.consolidado.compras_mes || 0);

            const cats = (orcamentoData.consolidado.categorias || []).slice(0, 12); // top 12
            const rows = cats.map(r=>{
              const util = Number(r.percent_util || 0);
              const pillClass = util <= 90 ? 'util-ok' : (util <= 100 ? 'util-mid' : 'util-high');
              return `
                <tr>
                  <td>${r.nome || 'Sem classe'}</td>
                  <td>${fmtMoney(Number(r.consumido||0))}</td>
                  <td>${fmtMoney(Number(r.dotacao||0))}</td>
                  <td>${fmtPct(Number(r.percent_fator||0))}</td>
                  <td><span class="util-pill ${pillClass}">${fmtPct(util)}</span></td>
                </tr>
              `;
            }).join('');

            orcSec.innerHTML = `
              <div class="orcamento-wrap">
                <h2 style="margin:0 0 10px 0;">Orçamento de compras</h2>
                <div class="orcamento-grid">
                  <div class="gauge-box">
                    <div class="gauge-sub">Total consumido</div>
                    <div class="gauge-percent">${fmtPct(cons)}</div>
                    <div class="gauge-sub">${dot > 0 ? `R$ ${fmtMoney(comp)} / R$ ${fmtMoney(dot)}` : '—'}</div>
                    <div style="width:100%; margin-top:6px;">
                      <div style="height:10px; background:#eee; border-radius:9999px; overflow:hidden;">
                        <div style="height:10px; width:${Math.min(100, Math.max(0, cons))}%; background:#26a69a;"></div>
                      </div>
                      <div style="display:flex; justify-content:space-between; font-size:.75rem; color:#777; margin-top:4px;">
                        <span>0%</span><span>100%</span>
                      </div>
                    </div>
                  </div>

                  <div>
                    <h3 style="margin:0 0 8px 0;">Por categoria (classe)</h3>
                    <div class="table-scroll">
                      <table class="orc-table">
                        <thead>
                          <tr>
                            <th>Grupos</th>
                            <th>R$ Consumido</th>
                            <th>R$ Dotação</th>
                            <th>% Fator</th>
                            <th>% Util.</th>
                          </tr>
                        </thead>
                        <tbody>
                          ${rows || `<tr><td colspan="5" style="text-align:center; color:#777;">Sem dados</td></tr>`}
                        </tbody>
                      </table>
                    </div>
                    <div style="font-size:.78rem; color:#666; margin-top:6px;">
                      * Consumo por categoria vem dos itens da compra (VALORITEMCOMPRA). O consumo total do orçamento usa o valor da nota (VALORNOTA).
                    </div>
                  </div>
                </div>
              </div>
            `;
          } else {
            orcSec.innerHTML = '';
          }
        }


        // ---- Vendas por classe (por loja) com % da LOJA ----
        const nomesClasses = Object.keys(mapaClasses);
        const valoresClasses = Object.values(mapaClasses);

        fontePrincipal.forEach(dados=>{
          if(!dados || !dados.classes) return;
          const lojaLabel = resolveLojaLabel(dados);

          const totalLojaClasses = (dados.classes||[]).reduce((acc, cls)=> acc + Number(cls.venda_liq||0), 0);

          const div=document.createElement('div');
          div.className='block-table';
       div.innerHTML = `
  <h3>${lojaLabel}</h3>
  <div class="table-scroll">
    <table class="inner-table">
      <tr>
        <th>Classe</th>
        <th style="text-align:right;">Venda Líq.</th>
        <th style="text-align:right;">% da Loja</th>
        <th style="text-align:right;">Desc. (R$)</th>
        <th style="text-align:right;">Desc. (%)</th>
        <th style="text-align:right;">Custo</th>
        <th style="text-align:right;">% Custo</th>
        <th style="text-align:right;">Lucro</th>
        <th style="text-align:right;">% Lucro</th>
        <th style="text-align:right;">Qtd.</th>
      </tr>
      ${dados.classes.map(cls => {
        const nomeClasse = cls.nome_classe || 'Sem classe';

        const vendaLiqC  = Number(cls.venda_liq || 0);
        const custoC     = Number(cls.custo || 0);
        const lucroC     = Number(cls.lucro || (vendaLiqC - custoC));
        const percCusto  = vendaLiqC > 0 ? (custoC / vendaLiqC) * 100 : 0;
        const percLucro  = vendaLiqC > 0 ? (lucroC / vendaLiqC) * 100 : 0;
        const qtd        = Number(cls.quant || cls.qtd || 0);

        // tenta pegar descontos ou calcular pela venda_bruta, se existir
        const descInfo   = Number(cls.descontos || cls.desconto || 0);
        const vendaBrutaC = cls.venda_bruta
          ? Number(cls.venda_bruta)
          : (descInfo ? vendaLiqC + descInfo : vendaLiqC);

        const descC     = vendaBrutaC - vendaLiqC;
        const descPctC  = vendaBrutaC > 0 ? (descC / vendaBrutaC) * 100 : 0;

        const pctLoja   = totalLojaClasses > 0 ? (vendaLiqC / totalLojaClasses) * 100 : 0;
        const widthPct  = pctLoja > 0 ? Math.max(0.8, Math.min(100, Number(pctLoja.toFixed(PCT_DECIMALS)))) : 0;
        const barColor  = colorForClassSolid(nomeClasse);

        return `
          <tr>
            <td>${nomeClasse}</td>
            <td style="text-align:right;">${fmtMoney(vendaLiqC)}</td>
            <td class="progress-cell">
              ${fmtPct(pctLoja)} 
              <span class="progress-wrap" aria-hidden="true" title="${nomeClasse}">
                <span class="progress-bar" style="width:${widthPct}%; background:${barColor};"></span>
              </span>
            </td>
            <td style="text-align:right;">${fmtMoney(descC)}</td>
            <td style="text-align:right;">${fmtPct(descPctC)}</td>
            <td style="text-align:right;">${fmtMoney(custoC)}</td>
            <td style="text-align:right;">${fmtPct(percCusto)}</td>
            <td style="text-align:right;">${fmtMoney(lucroC)}</td>
            <td style="text-align:right;">${fmtPct(percLucro)}</td>
            <td style="text-align:right;">${qtd}</td>
          </tr>
        `;
      }).join('')}
    </table>
  </div>
          `;
          areaClasses.appendChild(div);
        });

        // ---- Vendedores por loja ----
        fontePrincipal.forEach(dados=>{
          if(!dados || !dados.vendedores) return;
          const lojaLabel = resolveLojaLabel(dados);

          const div=document.createElement('div');
          div.className='block-table';
          div.innerHTML = `
            <h3>${lojaLabel}</h3>
            <div class="table-scroll">
              <table class="inner-table">
                <tr>
                  <th>Vendedor</th>
                  <th style="text-align:right;">Venda Líq.</th>
                  <th style="text-align:right;">Lucro</th>
                  <th style="text-align:right;">% Lucro</th>
                  <th style="text-align:right;">Atend.</th>
                  <th style="text-align:right;">Ticket</th>
                </tr>
                ${(dados.vendedores||[]).map(v=>{
                  const vendaV=Number(v.venda_liq||0);
                  const lucroV=Number(v.lucro||0);
                  const percV = v.perc_lucro ? Number(v.perc_lucro) : (vendaV>0 ? (lucroV/vendaV)*100 : 0);
                  const atendV=Number(v.atend||v.atendimentos||0);
                  const ticketV=Number(v.ticket||(atendV>0? vendaV/atendV:0));
                  return `
                    <tr>
                      <td>${v.apelido||'Sem nome'}</td>
                      <td style="text-align:right;">${fmtMoney(vendaV)}</td>
                      <td style="text-align:right;">${fmtMoney(lucroV)}</td>
                      <td style="text-align:right;">${fmtPct(percV)}</td>
                      <td style="text-align:right;">${atendV}</td>
                      <td style="text-align:right;">${fmtMoney(ticketV)}</td>
                    </tr>
                  `;
                }).join('')}
              </table>
            </div>
          `;
          areaVendedores.appendChild(div);
        });

        // ---- Gráficos ----
        destroyCharts();
        if(lojas.length){
          const bgColors=dynamicColors(lojas.length);
          const bdColors=dynamicBorderColors(lojas.length);
          grafVenda = new Chart(document.getElementById('graf-venda'), { type:'bar',
            data:{ labels:lojas, datasets:[{ data:vendasLiq, backgroundColor:bgColors, borderColor:bdColors, borderWidth:1 }] },
            options:{ plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true } } }
          });
          grafLucroPct = new Chart(document.getElementById('graf-lucro-pct'), { type:'bar',
            data:{ labels:lojas, datasets:[{ data:lucrosPct, backgroundColor:bgColors, borderColor:bdColors, borderWidth:1 }] },
            options:{ plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, ticks:{ callback:v=>fmtPct(v) } } } }
          });
          grafTicket = new Chart(document.getElementById('graf-ticket'), { type:'bar',
            data:{ labels:lojas, datasets:[{ data:tickets, backgroundColor:bgColors, borderColor:bdColors, borderWidth:1 }] },
            options:{ plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true } } }
          });
          grafAtend = new Chart(document.getElementById('graf-atend'), { type:'bar',
            data:{ labels:lojas, datasets:[{ data:atendArray, backgroundColor:bgColors, borderColor:bdColors, borderWidth:1 }] },
            options:{ plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true } } }
          });
        }

        if(nomesClasses.length){
          const totalClasses = valoresClasses.reduce((a,v)=>a+v,0);
          const classColors = nomesClasses.map(n=>colorForClass(n));
          grafClasses = new Chart(document.getElementById('graf-classes'), {
            type:'pie',
            data:{ labels:nomesClasses, datasets:[{ data:valoresClasses, backgroundColor:classColors }] },
            options:{ plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label:(ctx)=>{
              const valor=ctx.parsed; const pct= totalClasses>0 ? (valor/totalClasses)*100 : 0;
              return `${ctx.label}: R$ ${fmtMoney(valor)} (${fmtPct(pct)})`;
            }}}}}
          });
        }

        todosVendedores.sort((a,b)=>b.venda-a.venda);
        const top3=todosVendedores.slice(0,3);
        if(top3.length){
          grafTopVend = new Chart(document.getElementById('graf-topvend'), {
            type:'bar',
            data:{ labels:top3.map(t=>`${t.apelido} (${t.loja})`),
              datasets:[{ data:top3.map(t=>t.venda), backgroundColor:dynamicColors(top3.length), borderColor:dynamicBorderColors(top3.length), borderWidth:1 }] },
            options:{ plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true } } }
          });
        }
      }catch(err){ console.error('Erro no renderPainel:', err); }
    }

    // carregar (Dia primeiro, Mês depois)
    async function iniciarCarregamento(){
      const data = document.getElementById('dataBusca').value || hojeLocalISO();
      const lojasSelecionadas = getSelectedLojas();

      abortarBuscasAtuais();
      // Limpa cache entre buscas (evita manter resposta antiga de erro/estrutura diferente)
      cache.clear();
      dadosDiaMap={}; dadosMesMap={};
      orcamentoData=null;

      resetStatusBar(lojasSelecionadas);

      // limpa UI
      document.querySelector('#tabela-dia tbody').innerHTML='';
      document.querySelector('#tabela-dia tfoot').innerHTML='';
      document.querySelector('#tabela-mes tbody').innerHTML='';
      document.querySelector('#tabela-mes tfoot').innerHTML='';
      document.getElementById('areaClasses').innerHTML='';
      document.getElementById('areaVendedores').innerHTML='';
      destroyCharts();

      // 1) Primeiro: DIA (progressivo)
      const promDia = lojasSelecionadas.map(id=>{
        const ctrl = new AbortController(); controllersAtivos.push(ctrl);
        const url = montarUrlDia(id, data);
        return fetchJsonSeguroAbort(url, ctrl.signal)
          .then(json=>{
            if(json){
              json.loja = normalizeLojaId(json.loja || id);
              dadosDiaMap[id]=json;
              setStatusLoja(id,'ok');
            } else {
              setStatusLoja(id,'erro','Sem dados');
            }
            scheduleRender(); // atualização “suave”
          })
          .catch(e=>{ if(e.name!=='AbortError') setStatusLoja(id,'erro', e.message); });
      });

      await Promise.allSettled(promDia);

      // 2) Depois: MÊS (em segundo plano)
      const promMes = lojasSelecionadas.map(id=>{
        const ctrl = new AbortController(); controllersAtivos.push(ctrl);
        const url = montarUrlMes(id, data);
        return fetchJsonSeguroAbort(url, ctrl.signal)
          .then(json=>{
            if(json){
              json.loja = normalizeLojaId(json.loja || id);
              dadosMesMap[id]=json;
            }
          })
          .catch(()=>{});
      });

      await Promise.allSettled(promMes);
      // 3) Dotação Orçamentária (mês) - 1 chamada para as lojas selecionadas
      try{
        const ctrlO = new AbortController(); controllersAtivos.push(ctrlO);
        const urlO = montarUrlOrcamento(lojasSelecionadas, data);
        const jsonO = await fetchJsonSeguroAbort(urlO, ctrlO.signal);
        if(jsonO && (jsonO.consolidado || jsonO.por_loja)){
          orcamentoData = jsonO;
        }
      }catch(e){ /* silencioso */ }


      scheduleRender(); // render final consolidado
    }

    // start
    iniciarCarregamento();
  </script>
</body>
</html>
