<?php
session_start();
if (!isset($_SESSION[\'usuario\'])) {
    header(\'Location: login.php\');
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
    .status-pill.loading{ background:#e3f2fd; color:#1565c0; border-color:#bbdefb; }
    .status-pill.loading::before{ background:#1976d2; animation: pulse 1.5s infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }

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
    }
  </style>
</head>
<body>
  <div class="topbar">
    <div class="topbar-left"><h1>Consolidado das Farmácias</h1></div>
    <div class="topbar-right">
      <img src="images/logocombempopular.png" alt="Logo" class="logo">
      <div class="user-box">
        <span>Olá, <?php echo htmlspecialchars($_SESSION[\'usuario\']); ?></span>
        <a class="logout-btn" href="logout.php">Sair</a>
      </div>
    </div>
  </div>

  <div id="status-lojas" class="status-bar"></div>

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

  <h2>Vendas do dia</h2>
  <div class="table-scroll">
    <table id="tabela-dia">
      <thead>
        <tr>
          <th>Loja</th> <th>Venda Bruta</th> <th>Venda Líquida</th> <th>% Lucro</th> <th>Lucro</th>
          <th>CMV (R$)</th> <th>% CMV</th> <th>Atend.</th> <th>Ticket</th> <th>% Desconto</th>
          <th>Descontos</th> <th>Estornos</th> <th>Devoluções</th>
        </tr>
      </thead>
      <tbody></tbody> <tfoot></tfoot>
    </table>
  </div>

  <h2 style="margin-top:25px;">Acumulado do mês</h2>
  <div class="table-scroll">
    <table id="tabela-mes">
      <thead>
        <tr>
          <th>Loja</th> <th>VB mês</th> <th>VL mês</th> <th>Prev. venda</th> <th>Prev. lucro</th>
          <th>Prev. lucro (%)</th> <th>Lucro (%)</th> <th>Lucro (R$)</th> <th>CMV (R$)</th> <th>CMV (%)</th>
          <th>Atend.</th> <th>Ticket</th> <th>Desc. (R$)</th> <th>Desc. (%)</th> <th>Devoluções</th> <th>Estornos</th>
        </tr>
      </thead>
      <tbody></tbody> <tfoot></tfoot>
    </table>
  </div>

  <div class="charts-row">
    <div class="chart-box"><h4>Venda Líquida</h4><canvas id="graf-venda"></canvas></div>
    <div class="chart-box"><h4>Lucro %</h4><canvas id="graf-lucro-pct"></canvas></div>
    <div class="chart-box"><h4>Ticket médio</h4><canvas id="graf-ticket"></canvas></div>
    <div class="chart-box"><h4>Atendimentos</h4><canvas id="graf-atend"></canvas></div>
  </div>
  
  <h2 style="margin-top:20px;">Detalhes da Loja (em desenvolvimento)</h2>
  <div id="areaDetalhes"></div>

<script>
    // ---------- CONFIGURAÇÕES GLOBAIS ----------
    const LOJAS = [
      { id: \'sapezal\',  label: \'Sapezal\' },
      { id: \'pbcentro\', label: \'Pimenta\' },
      { id: \'alvorada\', label: \'Alvorada\' }
    ];
    const PCT_DECIMALS = 2;
    const MONEY_DECIMALS = 2;

    // ---------- ESTADO DA APLICAÇÃO ----------
    let dadosDia = [];
    let dadosMes = [];
    let orcamentoData = null;
    let controllersAtivos = [];
    let renderTimer = null;

    // ---------- ELEMENTOS DA DOM ----------
    const UIElements = {
        lojasGroup: document.getElementById(\'lojasGroup\'),
        statusBar: document.getElementById(\'status-lojas\'),
        inputData: document.getElementById(\'dataBusca\'),
        selectPeriodo: document.getElementById(\'filtroPeriodo\'),
        form: document.getElementById(\'formData\'),
        cards: document.getElementById(\'cards\'),
        orcamentoSection: document.getElementById(\'orcamento-section\'),
        tabelaDia: {
            tbody: document.querySelector(\'#tabela-dia tbody\'),
            tfoot: document.querySelector(\'#tabela-dia tfoot\')
        },
        tabelaMes: {
            tbody: document.querySelector(\'#tabela-mes tbody\'),
            tfoot: document.querySelector(\'#tabela-mes tfoot\')
        }
    };

    // ---------- FORMATAÇÃO E HELPERS ----------
    const fmt = {
        pct: (v) => `${Number(v || 0).toFixed(PCT_DECIMALS)}%`,
        money: (v) => Number(v || 0).toLocaleString(\'pt-BR\', { minimumFractionDigits: MONEY_DECIMALS, maximumFractionDigits: MONEY_DECIMALS })
    };
    const getLojaLabel = (id) => (LOJAS.find(l => l.id === id) || { label: id }).label;
    const hojeLocalISO = () => new Date(new Date().getTime() - (new Date().getTimezoneOffset() * 60000)).toISOString().split(\'T\')[0];
    const getSelectedLojas = () => Array.from(document.querySelectorAll(\'.lojaSel:checked\')).map(c => c.dataset.id);
    
    // ---------- LÓGICA DE DADOS (API) ----------
    function abortarBuscasAtuais() {
        controllersAtivos.forEach(c => c.abort());
        controllersAtivos = [];
    }

    async function fetchJson(url, signal) {
        try {
            const response = await fetch(url, { signal });
            if (!response.ok) {
                const errorText = await response.text();
                console.error(`Erro na API (${response.status}):`, errorText.slice(0, 500));
                return null;
            }
            // Robusto: tenta parsear mesmo com lixo antes/depois do JSON
            const text = await response.text();
            const jsonMatch = text.match(/(\\[|{).*(\\
\]|})/);
            if(jsonMatch) return JSON.parse(jsonMatch[0]);

            return JSON.parse(text); // Fallback para JSON limpo
        } catch (error) {
            if (error.name !== \'AbortError\') {
                console.error(\'Falha na requisição:\', error);
            }
            return null;
        }
    }

    // ---------- LÓGICA DE UI E RENDERIZAÇÃO ----------
    function inicializarCheckboxesLojas() {
        UIElements.lojasGroup.innerHTML = `
            <div class="loja-check">
                <input type="checkbox" id="todasLojas" checked />
                <label for="todasLojas"><strong>Selecionar todas</strong></label>
            </div>
            ${LOJAS.map(l => `
                <label class="loja-check">
                    <input type="checkbox" class="lojaSel" data-id="${l.id}" checked />
                    <span>${l.label}</span>
                </label>`).join(\'\')}
        `;

        document.getElementById(\'todasLojas\').addEventListener(\'change\', (e) => {
            document.querySelectorAll(\'.lojaSel\').forEach(c => c.checked = e.target.checked);
            iniciarCarregamento();
        });

        document.querySelectorAll(\'.lojaSel\').forEach(c => {
            c.addEventListener(\'change\', () => {
                document.getElementById(\'todasLojas\').checked = document.querySelectorAll(\'.lojaSel:checked\').length === LOJAS.length;
                iniciarCarregamento();
            });
        });
    }

    function setStatusLoja(id, estado, msg = \'\') {
        const rotulo = getLojaLabel(id);
        let pill = UIElements.statusBar.querySelector(`[data-loja="${id}"]`);
        if (!pill) {
            pill = document.createElement(\'div\');
            pill.dataset.loja = id;
            UIElements.statusBar.appendChild(pill);
        }
        pill.className = `status-pill ${estado}`;
        pill.innerHTML = `<span>${rotulo}</span><span>${msg || estado.charAt(0).toUpperCase() + estado.slice(1)}</span>`;
    }

    function renderizarTabela(tbody, tfoot, dados, tipo) {
        tbody.innerHTML = \'\';
        tfoot.innerHTML = \'\';
        if (!dados || dados.length === 0) return;

        let totalBruta = 0, totalLiquida = 0, totalLucro = 0, totalCmv = 0, totalAtend = 0, totalDesc = 0, totalEst = 0, totalDev = 0;

        dados.forEach(d => {
            const { venda_bruta, venda_liquida, descontos, cmv, lucro, atendimentos, estornos, devolucoes } = d;
            totalBruta += venda_bruta; totalLiquida += venda_liquida; totalLucro += lucro; totalCmv += cmv;
            totalAtend += atendimentos; totalDesc += descontos; totalEst += estornos; totalDev += devolucoes;
            
            const percLucro = venda_liquida > 0 ? (lucro / venda_liquida) * 100 : 0;
            const percCmv = venda_liquida > 0 ? (cmv / venda_liquida) * 100 : 0;
            const percDesc = venda_bruta > 0 ? (descontos / venda_bruta) * 100 : 0;
            const ticket = atendimentos > 0 ? (venda_liquida / atendimentos) : 0;

            const tr = tbody.insertRow();
            tr.innerHTML = `
                <td>${getLojaLabel(d.loja)}</td>
                <td>${fmt.money(venda_bruta)}</td>
                <td class="col-vl">${fmt.money(venda_liquida)}</td>
                <td class="col-lucro-pct">${fmt.pct(percLucro)}</td>
                <td class="col-lucro">${fmt.money(lucro)}</td>
                <td class="col-cmv">${fmt.money(cmv)}</td>
                <td class="col-cmv-pct">${fmt.pct(percCmv)}</td>
                <td>${atendimentos}</td>
                <td>${fmt.money(ticket)}</td>
                <td class="col-desconto-pct">${fmt.pct(percDesc)}</td>
                <td class="col-desconto">${fmt.money(descontos)}</td>
                <td>${fmt.money(estornos)}</td>
                <td>${fmt.money(devolucoes)}</td>
            `;
        });
        
        // Rodapé com totais
        const totPercLucro = totalLiquida > 0 ? (totalLucro / totalLiquida) * 100 : 0;
        const totPercCmv = totalLiquida > 0 ? (totalCmv / totalLiquida) * 100 : 0;
        const totTicket = totalAtend > 0 ? (totalLiquida / totalAtend) : 0;
        const totPercDesc = totalBruta > 0 ? (totalDesc / totalBruta) * 100 : 0;

        tfoot.innerHTML = `
            <tr>
                <th>Total</th>
                <th>${fmt.money(totalBruta)}</th>
                <th class="col-vl">${fmt.money(totalLiquida)}</th>
                <th class="col-lucro-pct">${fmt.pct(totPercLucro)}</th>
                <th class="col-lucro">${fmt.money(totalLucro)}</th>
                <th class="col-cmv">${fmt.money(totalCmv)}</th>
                <th class="col-cmv-pct">${fmt.pct(totPercCmv)}</th>
                <th>${totalAtend}</th>
                <th>${fmt.money(totTicket)}</th>
                <th class="col-desconto-pct">${fmt.pct(totPercDesc)}</th>
                <th class="col-desconto">${fmt.money(totalDesc)}</th>
                <th>${fmt.money(totalEst)}</th>
                <th>${fmt.money(totalDev)}</th>
            </tr>
        `;
    }
    
    function renderizarPainel() {
        const periodo = UIElements.selectPeriodo.value;
        const fontePrincipal = (periodo === \'dia\') ? dadosDia : dadosMes;

        renderizarTabela(UIElements.tabelaDia.tbody, UIElements.tabelaDia.tfoot, dadosDia, \'dia\');
        renderizarTabela(UIElements.tabelaMes.tbody, UIElements.tabelaMes.tfoot, dadosMes, \'mes\');
    }

    function scheduleRender() {
        if (renderTimer) cancelAnimationFrame(renderTimer);
        renderTimer = requestAnimationFrame(renderizarPainel);
    }
    
    // ---------- FUNÇÃO PRINCIPAL DE CARREGAMENTO ----------
    async function iniciarCarregamento() {
        abortarBuscasAtuais();
        
        const lojasSelecionadas = getSelectedLojas();
        const data = UIElements.inputData.value;
        const mes = data.slice(0, 7);
        
        // Reset UI
        UIElements.statusBar.innerHTML = \'\';
        if (lojasSelecionadas.length === 0) {
            renderizarPainel(); // Limpa as tabelas
            return;
        }
        lojasSelecionadas.forEach(id => setStatusLoja(id, \'loading\', \'Carregando...\'));

        const ctrl = new AbortController();
        controllersAtivos.push(ctrl);

        const lojasParam = lojasSelecionadas.join(\',\');
        
        const promessas = [
            fetchJson(`api.php?endpoint=totais&periodo=dia&data=${data}&lojas=${lojasParam}`, ctrl.signal),
            fetchJson(`api.php?endpoint=totais&periodo=mes&data=${data}&lojas=${lojasParam}`, ctrl.signal),
            fetchJson(`api.php?endpoint=orcamento&mes=${mes}&lojas=${lojasParam}`, ctrl.signal)
        ];
        
        const [resDia, resMes, resOrc] = await Promise.all(promessas);

        // Processa resultados "Totais" (Dia e Mês)
        dadosDia = resDia || [];
        dadosMes = resMes || [];
        
        // Atualiza status com base na resposta (apenas do dia, que é mais crítico)
        const lojasProcessadas = new Set();
        (resDia || []).forEach(d => {
            setStatusLoja(d.loja, d.status === \'ok\' ? \'ok\' : \'erro\', d.status === \'ok\' ? \'OK\' : d.mensagem);
            lojasProcessadas.add(d.loja);
        });
        // Marca como erro lojas que não retornaram na API
        lojasSelecionadas.forEach(id => {
            if (!lojasProcessadas.has(id)) {
                setStatusLoja(id, \'erro\', \'Sem resposta\');
            }
        });
        
        // Processa resultado "Orçamento"
        orcamentoData = resOrc || null;
        
        // Renderiza tudo
        scheduleRender();
    }

    // ---------- INICIALIZAÇÃO ----------
    document.addEventListener(\'DOMContentLoaded\', () => {
        UIElements.inputData.value = hojeLocalISO();
        inicializarCheckboxesLojas();
        
        UIElements.form.addEventListener(\'submit\', (e) => {
            e.preventDefault();
            iniciarCarregamento();
        });
        UIElements.selectPeriodo.addEventListener(\'change\', scheduleRender);

        iniciarCarregamento();
    });

</script>
</body>
</html>
