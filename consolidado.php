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

    /* ORÇAMENTO (dotação / consumo) */
    .orcamento-section{ margin-bottom:20px; }
    .orcamento-wrap{ background:#fff; padding:12px 16px; border-radius:10px; box-shadow:var(--shadow); }
    .orcamento-wrap h3 { margin: 4px 0 12px 0; font-size: 1.1rem; }
    .orcamento-grid{ display:grid; grid-template-columns: 260px 1fr; gap:14px; align-items:stretch; }
    @media (max-width: 900px){ .orcamento-grid{ grid-template-columns:1fr; } }
    .gauge-box{ display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; border:1px solid #eee; border-radius:10px; padding:10px; }
    .gauge-percent{ font-size:1.6rem; font-weight:700; }
    .gauge-sub{ font-size:.85rem; color:#666; }
    .orc-table{ width:100%; border-collapse:collapse; font-size:.85rem; }
    .orc-table th, .orc-table td{ padding:8px 10px; border-bottom:1px solid #eee; white-space:nowrap; }
    .orc-table th{ text-align:left; font-weight: 600; background:#f7f7f7; }
    .orc-table td{ text-align:right; }
    .util-pill{ display:inline-block; padding:3px 8px; border-radius:9999px; font-weight:700; font-size: 0.8rem; }
    .util-ok{ background:#e8f5e9; color:#2e7d32; }
    .util-mid{ background:#fff8e1; color:#ef6c00; }
    .util-high{ background:#ffebee; color:#c62828; }

    /* TABELAS */
    .table-scroll{ width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; }
    table{ width:100%; border-collapse:collapse; background:#fff; border-radius:10px; box-shadow:var(--shadow); margin-bottom:20px; font-size:.85rem; }
    th,td{ padding:8px 10px; border-bottom:1px solid #eee; text-align:right; white-space:nowrap; }
    th:first-child, td:first-child{ text-align:left; }
    th{ background:#f7f7f7; } tfoot th{ background:#eaeaea; font-weight: 700; }

    .col-lucro,.col-lucro-pct{ color:var(--verde); font-weight:600; }
    .col-desconto,.col-desconto-pct{ color:var(--vermelho); font-weight:600; }
    .col-cmv,.col-cmv-pct{ color:var(--laranja); font-weight:600; }
    .col-vl{ color:#1565c0; font-weight:600; }

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
        <span>Olá, <?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
        <a class="logout-btn" href="logout.php">Sair</a>
      </div>
    </div>
  </div>

  <div id="status-lojas" class="status-bar"></div>

  <div class="filters">
    <form id="formData" style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
      <label>Data: <input type="date" id="dataBusca" name="dataBusca" /></label>
      <button type="submit">Buscar</button>
    </form>
    <div class="lojas-group" id="lojasGroup"></div>
  </div>

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
          <th>Loja</th> <th>Venda Bruta</th> <th>Venda Líquida</th> <th>% Lucro</th> <th>Lucro</th>
          <th>CMV (R$)</th> <th>% CMV</th> <th>Atend.</th> <th>Ticket</th> <th>% Desconto</th>
          <th>Descontos</th> <th>Estornos</th> <th>Devoluções</th>
        </tr>
      </thead>
      <tbody></tbody> <tfoot></tfoot>
    </table>
  </div>

<script>
    // ---------- CONFIGURAÇÕES GLOBAIS ----------
    const LOJAS = [
      { id: 'sapezal',  label: 'Sapezal' },
      { id: 'pbcentro', label: 'Pimenta' },
      { id: 'alvorada', label: 'Alvorada' }
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
        lojasGroup: document.getElementById('lojasGroup'),
        statusBar: document.getElementById('status-lojas'),
        inputData: document.getElementById('dataBusca'),
        form: document.getElementById('formData'),
        orcamentoSection: document.getElementById('orcamento-section'),
        tabelaDia: {
            tbody: document.querySelector('#tabela-dia tbody'),
            tfoot: document.querySelector('#tabela-dia tfoot')
        },
        tabelaMes: {
            tbody: document.querySelector('#tabela-mes tbody'),
            tfoot: document.querySelector('#tabela-mes tfoot')
        }
    };

    // ---------- FORMATAÇÃO E HELPERS ----------
    const fmt = {
        pct: (v) => `${Number(v || 0).toFixed(PCT_DECIMALS)}%`,
        money: (v) => Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: MONEY_DECIMALS, maximumFractionDigits: MONEY_DECIMALS })
    };
    const getLojaLabel = (id) => (LOJAS.find(l => l.id === id) || { label: id }).label;
    const hojeLocalISO = () => new Date(new Date().getTime() - (new Date().getTimezoneOffset() * 60000)).toISOString().split('T')[0];
    const getSelectedLojas = () => Array.from(document.querySelectorAll('.lojaSel:checked')).map(c => c.dataset.id);
    
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
            const text = await response.text();
            try {
                // Tenta parsear o JSON de forma robusta, ignorando potenciais avisos do PHP
                const jsonMatch = text.match(/(\[|{).*(\]|})/s);
                if (jsonMatch && jsonMatch[0]) {
                    return JSON.parse(jsonMatch[0]);
                }
                return JSON.parse(text); // Fallback
            } catch (e) {
                console.error("Erro ao parsear JSON:", e);
                console.log("Texto recebido:", text);
                return null;
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Falha na requisição:', error);
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
                </label>`).join('')}
        `;

        const chkTodas = document.getElementById('todasLojas');
        const chkLojas = document.querySelectorAll('.lojaSel');

        chkTodas.addEventListener('change', (e) => {
            chkLojas.forEach(c => c.checked = e.target.checked);
            iniciarCarregamento();
        });

        chkLojas.forEach(c => {
            c.addEventListener('change', () => {
                chkTodas.checked = document.querySelectorAll('.lojaSel:checked').length === LOJAS.length;
                iniciarCarregamento();
            });
        });
    }

    function setStatusLoja(id, estado, msg = '') {
        const rotulo = getLojaLabel(id);
        let pill = UIElements.statusBar.querySelector(`[data-loja="${id}"]`);
        if (!pill) {
            pill = document.createElement('div');
            pill.dataset.loja = id;
            UIElements.statusBar.appendChild(pill);
        }
        pill.className = `status-pill ${estado}`;
        pill.innerHTML = `<span>${rotulo}</span><span>${msg || estado.charAt(0).toUpperCase() + estado.slice(1)}</span>`;
    }

    function renderizarTabela(tbody, tfoot, dados) {
        tbody.innerHTML = '';
        tfoot.innerHTML = '';
        if (!dados || dados.length === 0) return;

        const totais = dados.reduce((acc, d) => {
            acc.venda_bruta += d.venda_bruta;
            acc.venda_liquida += d.venda_liquida;
            acc.lucro += d.lucro;
            acc.cmv += d.cmv;
            acc.atendimentos += d.atendimentos;
            acc.descontos += d.descontos;
            acc.estornos += d.estornos;
            acc.devolucoes += d.devolucoes;
            return acc;
        }, { venda_bruta: 0, venda_liquida: 0, lucro: 0, cmv: 0, atendimentos: 0, descontos: 0, estornos: 0, devolucoes: 0 });

        dados.forEach(d => {
            const percLucro = d.venda_liquida > 0 ? (d.lucro / d.venda_liquida) * 100 : 0;
            const percCmv = d.venda_liquida > 0 ? (d.cmv / d.venda_liquida) * 100 : 0;
            const percDesc = d.venda_bruta > 0 ? (d.descontos / d.venda_bruta) * 100 : 0;
            const ticket = d.atendimentos > 0 ? (d.venda_liquida / d.atendimentos) : 0;

            const tr = tbody.insertRow();
            tr.innerHTML = `
                <td>${getLojaLabel(d.loja)}</td>
                <td>${fmt.money(d.venda_bruta)}</td>
                <td class="col-vl">${fmt.money(d.venda_liquida)}</td>
                <td class="col-lucro-pct">${fmt.pct(percLucro)}</td>
                <td class="col-lucro">${fmt.money(d.lucro)}</td>
                <td class="col-cmv">${fmt.money(d.cmv)}</td>
                <td class="col-cmv-pct">${fmt.pct(percCmv)}</td>
                <td>${d.atendimentos}</td>
                <td>${fmt.money(ticket)}</td>
                <td class="col-desconto-pct">${fmt.pct(percDesc)}</td>
                <td class="col-desconto">${fmt.money(d.descontos)}</td>
                <td>${fmt.money(d.estornos)}</td>
                <td>${fmt.money(d.devolucoes)}</td>
            `;
        });
        
        const totPercLucro = totais.venda_liquida > 0 ? (totais.lucro / totais.venda_liquida) * 100 : 0;
        const totPercCmv = totais.venda_liquida > 0 ? (totais.cmv / totais.venda_liquida) * 100 : 0;
        const totTicket = totais.atendimentos > 0 ? (totais.venda_liquida / totais.atendimentos) : 0;
        const totPercDesc = totais.venda_bruta > 0 ? (totais.descontos / totais.venda_bruta) * 100 : 0;

        tfoot.innerHTML = `
            <tr>
                <th>Total</th>
                <th>${fmt.money(totais.venda_bruta)}</th>
                <th class="col-vl">${fmt.money(totais.venda_liquida)}</th>
                <th class="col-lucro-pct">${fmt.pct(totPercLucro)}</th>
                <th class="col-lucro">${fmt.money(totais.lucro)}</th>
                <th class="col-cmv">${fmt.money(totais.cmv)}</th>
                <th class="col-cmv-pct">${fmt.pct(totPercCmv)}</th>
                <th>${totais.atendimentos}</th>
                <th>${fmt.money(totTicket)}</th>
                <th class="col-desconto-pct">${fmt.pct(totPercDesc)}</th>
                <th class="col-desconto">${fmt.money(totais.descontos)}</th>
                <th>${fmt.money(totais.estornos)}</th>
                <th>${fmt.money(totais.devolucoes)}</th>
            </tr>
        `;
    }
    
    function renderizarOrcamento() {
        const container = UIElements.orcamentoSection;
        container.innerHTML = ''; 

        if (!orcamentoData || !orcamentoData.consolidado) return;

        const {
            dotacao_total,
            consumido_total,
            saldo_total,
            percentual_consumido,
            dias_uteis_corridos_percentual
        } = orcamentoData.consolidado;

        let gaugeColorClass = 'util-ok';
        // Tolerância de 5% acima do esperado para os dias úteis
        if (percentual_consumido > dias_uteis_corridos_percentual + 5) {
            gaugeColorClass = 'util-high';
        } else if (percentual_consumido > dias_uteis_corridos_percentual) {
            gaugeColorClass = 'util-mid';
        }

        const html = `
            <div class="orcamento-wrap">
                <h3>Orçamento Consolidado (GMD)</h3>
                <div class="orcamento-grid">
                    <div class="gauge-box">
                        <div class="gauge-percent ${gaugeColorClass}">${fmt.pct(percentual_consumido)}</div>
                        <div class="gauge-sub">Consumido</div>
                        <small>Ideal dias úteis: ${fmt.pct(dias_uteis_corridos_percentual)}</small>
                    </div>
                    <div class="table-scroll">
                        <table class="orc-table">
                            <tbody>
                                <tr>
                                    <th>Dotação Total</th>
                                    <td>${fmt.money(dotacao_total)}</td>
                                </tr>
                                <tr>
                                    <th>Consumido Total</th>
                                    <td>${fmt.money(consumido_total)}</td>
                                </tr>
                                <tr>
                                    <th>Saldo</th>
                                    <td class="${(saldo_total < 0) ? 'util-high' : ''}">${fmt.money(saldo_total)}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        container.innerHTML = html;
    }

    function renderizarTudo() {
        renderizarTabela(UIElements.tabelaDia.tbody, UIElements.tabelaDia.tfoot, dadosDia);
        renderizarTabela(UIElements.tabelaMes.tbody, UIElements.tabelaMes.tfoot, dadosMes);
        renderizarOrcamento();
    }

    function scheduleRender() {
        if (renderTimer) cancelAnimationFrame(renderTimer);
        renderTimer = requestAnimationFrame(renderizarTudo);
    }
    
    // ---------- FUNÇÃO PRINCIPAL DE CARREGAMENTO ----------
    async function iniciarCarregamento() {
        abortarBuscasAtuais();
        
        const lojasSelecionadas = getSelectedLojas();
        const data = UIElements.inputData.value;
        const mes = data.slice(0, 7);
        
        UIElements.statusBar.innerHTML = '';
        if (lojasSelecionadas.length === 0) {
            dadosDia = []; dadosMes = []; orcamentoData = null;
            scheduleRender();
            return;
        }
        
        lojasSelecionadas.forEach(id => setStatusLoja(id, 'loading', 'Carregando...'));

        const ctrl = new AbortController();
        controllersAtivos.push(ctrl);

        const lojasParam = lojasSelecionadas.join(',');
        
        const promessas = [
            fetchJson(`api.php?endpoint=totais&periodo=dia&data=${data}&lojas=${lojasParam}`, ctrl.signal),
            fetchJson(`api.php?endpoint=totais&periodo=mes&data=${data}&lojas=${lojasParam}`, ctrl.signal),
            fetchJson(`api.php?endpoint=orcamento&mes=${mes}&lojas=${lojasParam}`, ctrl.signal)
        ];
        
        const [resDia, resMes, resOrc] = await Promise.all(promessas);

        dadosDia = resDia || [];
        dadosMes = resMes || [];
        orcamentoData = resOrc || null;

        const lojasRespondidas = new Set();
        (resDia || []).forEach(d => {
            setStatusLoja(d.loja, d.status === 'ok' ? 'ok' : 'erro', d.status === 'ok' ? 'OK' : (d.mensagem || 'Erro'));
            lojasRespondidas.add(d.loja);
        });

        lojasSelecionadas.forEach(id => {
            if (!lojasRespondidas.has(id)) {
                setStatusLoja(id, 'erro', 'Sem resposta');
            }
        });
        
        scheduleRender();
    }

    // ---------- INICIALIZAÇÃO ----------
    document.addEventListener('DOMContentLoaded', () => {
        UIElements.inputData.value = hojeLocalISO();
        inicializarCheckboxesLojas();
        
        UIElements.form.addEventListener('submit', (e) => {
            e.preventDefault();
            iniciarCarregamento();
        });

        iniciarCarregamento();
    });

</script>
</body>
</html>
