/* eslint-disable no-alert */

// ⚠️ CONFIGURAÇÃO CRÍTICA - MODIFICAR COM CUIDADO ⚠️
// Resolve a URL base para chamadas ao backend permitindo sobrescrever via configuracao externa.
// Fallback deve ser https://api.gruposaborparaense.com.br/api (porta do backend Laravel)
const API_URL = (() => {
  const fallback = "https://api.gruposaborparaense.com.br/api";
  if (window.APP_CONFIG && window.APP_CONFIG.API_URL) {
    return window.APP_CONFIG.API_URL;
  }
  return fallback;
})();

// URL base para arquivos estáticos (fotos, uploads) - sem /api
const BASE_URL = API_URL.replace(/\/api\/?$/, "") || "https://api.gruposaborparaense.com.br";

/** Monta URL completa da foto do usuário (backend salva em public/uploads/usuarios/) */
function getUsuarioFotoUrl(path) {
  if (!path || typeof path !== "string") return null;
  const p = path.replace(/^\//, "");
  return p ? `${BASE_URL}/${p}` : null;
}

const storageKey = "sas-estoque-user";
const currentSectionKey = "sas-estoque-current-section";

// Cache para marca/modelo do dispositivo (User-Agent Client Hints - Chrome/Edge)
let cachedDeviceInfo = null;
let deviceInfoPromise = null;
function ensureDeviceInfo() {
  if (cachedDeviceInfo !== null) return;
  if (deviceInfoPromise) return;
  if (typeof navigator !== "undefined" && navigator.userAgentData?.getHighEntropyValues) {
    deviceInfoPromise = navigator.userAgentData.getHighEntropyValues(["model", "platform"])
      .then(v => { cachedDeviceInfo = { model: (v.model || "").trim(), platform: (v.platform || "").trim() }; })
      .catch(() => { cachedDeviceInfo = {}; });
  } else {
    cachedDeviceInfo = {};
  }
}
ensureDeviceInfo();

function getDeviceHeaders() {
  if (!cachedDeviceInfo) return {};
  const h = {};
  if (cachedDeviceInfo.model) h["X-Device-Model"] = cachedDeviceInfo.model;
  if (cachedDeviceInfo.platform) h["X-Device-Platform"] = cachedDeviceInfo.platform;
  return h;
}

// Colecao das principais referencias de interface usadas pelos modulos.
const dom = {
  toast: document.getElementById("toast"),
  loginOverlay: document.getElementById("loginOverlay"),
  appShell: document.getElementById("appShell"),
  userName: document.getElementById("userName"),
  userRole: document.getElementById("userRole"),
  userEmail: document.getElementById("userEmail"),
  loginForm: document.getElementById("loginForm"),
  logoutBtn: document.getElementById("logoutBtn"),
  matrixCanvas: document.getElementById("matrixCanvas"),
  sidebar: document.getElementById("sidebar"),
  sidebarBackdrop: document.getElementById("sidebarBackdrop"),
  sidebarCollapseBtn: document.getElementById("sidebarCollapseBtn"),
  menuToggle: document.getElementById("menuToggle"),
  navLinks: Array.from(document.querySelectorAll(".nav-link[data-section]")),
  sections: Array.from(document.querySelectorAll(".view-section")),
  movTable: document.getElementById("movTable"),
  lotesTable: document.getElementById("lotesTable"),
  lotesManageTable: document.getElementById("lotesManageTable"),
  produtosTable: document.getElementById("produtosTable"),
  estoqueSection: document.getElementById("estoqueSection"),
  estoqueProdutoSelect: document.getElementById("estoqueProdutoSelect"),
  estoqueInfo: document.getElementById("estoqueInfo"),
  estoqueProdutoNome: document.getElementById("estoqueProdutoNome"),
  estoqueTotalQtd: document.getElementById("estoqueTotalQtd"),
  estoqueTotalUnitario: document.getElementById("estoqueTotalUnitario"),
  estoqueTotalValor: document.getElementById("estoqueTotalValor"),
  estoqueUnidadeBase: document.getElementById("estoqueUnidadeBase"),
  estoqueTable: document.getElementById("estoqueTable"),
  unidadesTable: document.getElementById("unidadesTable"),
  usuariosTable: document.getElementById("usuariosTable"),
  listasComprasTable: document.getElementById("listasComprasTable"),
  listaComprasItensTable: document.getElementById("listaComprasItensTable"),
  listaComprasEstabelecimentosTable: document.getElementById("listaComprasEstabelecimentosTable"),
  listaComprasAnexos: document.getElementById("listaComprasAnexos"),
  produtosDashboardTable: document.getElementById("produtosDashboardTable"),
  kpiProdutos: document.getElementById("kpiProdutos"),
  kpiVencer: document.getElementById("kpiVencer"),
  kpiLotesAVencer: document.getElementById("kpiLotesAVencer"),
  kpiLotesVencidos: document.getElementById("kpiLotesVencidos"),
  kpiMinimo: document.getElementById("kpiMinimo"),
  cardMinimo: document.getElementById("cardMinimo"),
  cardMinimoHint: document.getElementById("cardMinimoHint"),
  kpiPerdas: document.getElementById("kpiPerdas"),
  cardPerdas: document.getElementById("cardPerdas"),
  cardPerdasHint: document.getElementById("cardPerdasHint"),
  cardLotesAVencer: document.getElementById("cardLotesAVencer"),
  cardLotesVencidos: document.getElementById("cardLotesVencidos"),
  cardComprasAndamento: document.getElementById("cardComprasAndamento"),
  cardProdutosAtivos: document.getElementById("cardProdutosAtivos"),
  cardLotes7Dias: document.getElementById("cardLotes7Dias"),
  kpiComprasAtivas: document.getElementById("kpiComprasAtivas"),
  loteStatusChart: document.getElementById("loteStatusChart"),
  lotesFilterForm: document.getElementById("lotesFilterForm"),
  lotesFiltroPesquisa: document.getElementById("lotesFiltroPesquisa"),
  lotesFiltroProduto: document.getElementById("lotesFiltroProduto"),
  lotesFiltroProdutoBusca: document.getElementById("lotesFiltroProdutoBusca"),
  lotesFiltroUnidade: document.getElementById("lotesFiltroUnidade"),
  lotesFiltroStatus: document.getElementById("lotesFiltroStatus"),
  lotesFiltroValidadeDe: document.getElementById("lotesFiltroValidadeDe"),
  lotesFiltroValidadeAte: document.getElementById("lotesFiltroValidadeAte"),
  aplicarFiltrosLotes: document.getElementById("aplicarFiltrosLotes"),
  limparFiltrosLotes: document.getElementById("limparFiltrosLotes"),
  openNovoLoteBtn: document.getElementById("openNovoLote"),
  produtosModal: document.getElementById("produtoModal"),
  produtosForm: document.getElementById("produtoForm"),
  produtoModalTitle: document.getElementById("produtoModalTitle"),
  produtoFormFeedback: document.getElementById("produtoFormFeedback"),
  openProdutoBtn: document.getElementById("openProduto"),
  closeProdutoBtn: document.getElementById("closeProduto"),
  cancelProdutoBtn: document.getElementById("cancelProduto"),
  usuarioModal: document.getElementById("usuarioModal"),
  usuarioForm: document.getElementById("usuarioForm"),
  usuarioModalTitle: document.getElementById("usuarioModalTitle"),
  usuarioFormFeedback: document.getElementById("usuarioFormFeedback"),
  usuarioFotoInput: document.getElementById("usuarioFotoInput"),
  usuarioFotoTrocar: document.getElementById("usuarioFotoTrocar"),
  usuarioFotoRemover: document.getElementById("usuarioFotoRemover"),
  usuarioAvatarPreview: document.getElementById("usuarioAvatarPreview"),
  openUsuarioBtn: document.getElementById("openUsuario"),
  closeUsuarioBtn: document.getElementById("closeUsuario"),
  cancelUsuarioBtn: document.getElementById("cancelUsuario"),
  openListaCompraBtn: document.getElementById("openListaCompra"),
  listaCompraModal: document.getElementById("listaCompraModal"),
  listaCompraForm: document.getElementById("listaCompraForm"),
  listaCompraModalTitle: document.getElementById("listaCompraModalTitle"),
  closeListaCompraBtn: document.getElementById("closeListaCompra"),
  cancelListaCompraBtn: document.getElementById("cancelListaCompra"),
  openSugestoesComprasBtn: document.getElementById("openSugestoesCompras"),
  sugestoesComprasModal: document.getElementById("sugestoesComprasModal"),
  closeSugestoesComprasBtn: document.getElementById("closeSugestoesCompras"),
  sugestoesFiltroUnidade: document.getElementById("sugestoesFiltroUnidade"),
  sugestoesDiasAnalise: document.getElementById("sugestoesDiasAnalise"),
  sugestoesDiasProjecao: document.getElementById("sugestoesDiasProjecao"),
  sugestoesBuscarBtn: document.getElementById("sugestoesBuscar"),
  sugestoesComprasContent: document.getElementById("sugestoesComprasContent"),
  sugestoesComprasLoading: document.getElementById("sugestoesComprasLoading"),
  listaCompraTitulo: document.getElementById("listaCompraTitulo"),
  listaCompraSubtitulo: document.getElementById("listaCompraSubtitulo"),
  listaCompraStatus: document.getElementById("listaCompraStatus"),
  listaCompraObservacoes: document.getElementById("listaCompraObservacoes"),
  listaCompraTotalPlanejado: document.getElementById("listaCompraTotalPlanejado"),
  listaCompraTotalRealizado: document.getElementById("listaCompraTotalRealizado"),
  loteModal: document.getElementById("loteModal"),
  loteModalTitle: document.getElementById("loteModalTitle"),
  loteForm: document.getElementById("loteForm"),
  closeLoteBtn: document.getElementById("closeLote"),
  cancelLoteBtn: document.getElementById("cancelLote"),
  boletoModal: document.getElementById("boletoModal"),
  boletoForm: document.getElementById("boletoForm"),
  boletoModalTitle: document.getElementById("boletoModalTitle"),
  closeBoletoBtn: document.getElementById("closeBoleto"),
  cancelBoletoBtn: document.getElementById("cancelBoleto"),
  openNovoBoletoBtn: document.getElementById("openNovoBoleto"),
  boletosMesAnoFiltro: document.getElementById("boletosMesAnoFiltro"),
  boletosUnidadeFiltro: document.getElementById("boletosUnidadeFiltro"),
  boletosStatusFiltro: document.getElementById("boletosStatusFiltro"),
  limparFiltrosBoletos: document.getElementById("limparFiltrosBoletos"),
  boletosTable: document.getElementById("boletosTable"),
  boletosTotalMes: document.getElementById("boletosTotalMes"),
  boletosPagoEmDia: document.getElementById("boletosPagoEmDia"),
  boletosJurosPagos: document.getElementById("boletosJurosPagos"),
  boletosAtrasados: document.getElementById("boletosAtrasados"),
  passwordToggles: Array.from(document.querySelectorAll(".password-toggle")),
  listaCompraItensResumo: document.getElementById("listaCompraItensResumo"),
  listaCompraStatusRascunho: document.getElementById("listaCompraStatusRascunho"),
  listaCompraStatusEmCompras: document.getElementById("listaCompraStatusEmCompras"),
  listaCompraStatusPausada: document.getElementById("listaCompraStatusPausada"),
  listaCompraAdicionarItem: document.getElementById("listaCompraAdicionarItem"),
  listaCompraAdicionarEstabelecimento: document.getElementById("listaCompraAdicionarEstabelecimento"),
  listaCompraFinalizar: document.getElementById("listaCompraFinalizar"),
  listaCompraGerarPdf: document.getElementById("listaCompraGerarPdf"),
  listaCompraPdf: document.getElementById("listaCompraPdf"),
  listaCompraLancarEstoque: document.getElementById("listaCompraLancarEstoque"),
  listaCompraFiltroStatus: document.getElementById("listaCompraFiltroStatus"),
  locaisTable: document.getElementById("locaisTable"),
  openLocalModalBtn: document.getElementById("openLocalModal"),
  localModal: document.getElementById("localModal"),
  localModalTitle: document.querySelector("#localModal h2"),
  localForm: document.getElementById("localForm"),
  closeLocalModalBtn: document.getElementById("closeLocalModal"),
  cancelLocalBtn: document.getElementById("cancelLocal"),
  localUnidadeSelect: document.getElementById("localUnidadeSelect"),
  localTipoSelect: document.getElementById("localTipoSelect"),
  localNivelAcessoSelect: document.getElementById("localNivelAcesso"),
  itemCompraModal: document.getElementById("itemCompraModal"),
  itemCompraForm: document.getElementById("itemCompraForm"),
  itemCompraModalTitle: document.getElementById("itemCompraModalTitle"),
  closeItemCompraBtn: document.getElementById("closeItemCompra"),
  cancelItemCompraBtn: document.getElementById("cancelItemCompra"),
  estabelecimentoCompraModal: document.getElementById("estabelecimentoCompraModal"),
  estabelecimentoCompraForm: document.getElementById("estabelecimentoCompraForm"),
  estabelecimentoCompraModalTitle: document.getElementById("estabelecimentoCompraModalTitle"),
  closeEstabelecimentoCompraBtn: document.getElementById("closeEstabelecimentoCompra"),
  cancelEstabelecimentoCompraBtn: document.getElementById("cancelEstabelecimentoCompra"),
  finalizarListaModal: document.getElementById("finalizarListaModal"),
  finalizarListaForm: document.getElementById("finalizarListaForm"),
  closeFinalizarListaBtn: document.getElementById("closeFinalizarLista"),
  cancelFinalizarListaBtn: document.getElementById("cancelFinalizarLista"),
  erroMovimentacaoModal: document.getElementById("erroMovimentacaoModal"),
  erroMovimentacaoTitulo: document.getElementById("erroMovimentacaoTitulo"),
  erroMovimentacaoMensagem: document.getElementById("erroMovimentacaoMensagem"),
  erroMovimentacaoDetalhes: document.getElementById("erroMovimentacaoDetalhes"),
  closeErroMovimentacaoBtn: document.getElementById("closeErroMovimentacao"),
  fecharErroMovimentacaoBtn: document.getElementById("fecharErroMovimentacao"),
  unidadeModal: document.getElementById("unidadeModal"),
  unidadeForm: document.getElementById("unidadeForm"),
  unidadeInlineForm: document.getElementById("unidadeInlineForm"),
  unidadeInlineFormCard: document.getElementById("unidadeInlineCard"),
  cancelInlineUnidadeBtn: document.getElementById("cancelInlineUnidade"),
  unidadeModalTitle: document.getElementById("unidadeModalTitle"),
  openUnidadeBtn: document.getElementById("openUnidade"),
  closeUnidadeBtn: document.getElementById("closeUnidade"),
  cancelUnidadeBtn: document.getElementById("cancelUnidade"),
  entradaModal: document.getElementById("entradaModal"),
  entradaForm: document.getElementById("entradaForm"),
  openEntradaBtn: document.getElementById("openEntrada"),
  closeEntradaBtn: document.getElementById("closeEntrada"),
  entradaUnidadeSelect: document.getElementById("entradaUnidadeSelect"),
  entradaLocalSelect: document.getElementById("entradaLocalSelect"),
  saidaModal: document.getElementById("saidaModal"),
  saidaForm: document.getElementById("saidaForm"),
  saidaProdutoSelect: document.getElementById("saidaProdutoSelect"),
  saidaOrigemSelect: document.getElementById("saidaOrigemUnidade"),
  saidaMotivo: document.getElementById("saidaMotivo"),
  saidaDestinoWrapper: document.getElementById("saidaDestinoWrapper"),
  saidaLoteWrapper: document.getElementById("saidaLoteWrapper"),
  saidaLoteSelect: document.getElementById("saidaLoteSelect"),
  saidaLoteManualWrapper: document.getElementById("saidaLoteManualWrapper"),
  saidaLoteManualInput: document.getElementById("saidaLoteManualInput"),
  saidaDestinoSelect: document.getElementById("saidaDestinoUnidade"),
  openSaidaBtn: document.getElementById("openSaida"),
  closeSaidaBtn: document.getElementById("closeSaida"),
  cancelSaidaBtn: document.getElementById("cancelSaida"),
  entradaSubmitBtn: document.querySelector("#entradaForm button[type='submit']"),
  cancelEntradaBtn: document.getElementById("cancelEntrada"),
  movFilterForm: document.getElementById("movFilterForm"),
  movFiltroTipo: document.getElementById("movFiltroTipo"),
  movFiltroProduto: document.getElementById("movFiltroProduto"),
  movFiltroUnidade: document.getElementById("movFiltroUnidade"),
  movFiltroDataDe: document.getElementById("movFiltroDataDe"),
  movFiltroDataAte: document.getElementById("movFiltroDataAte"),
  movFiltrosLimpar: document.getElementById("movFiltrosLimpar"),
  movimentacoesTable: document.getElementById("movimentacoesTable"),
  relatorioFilterForm: document.getElementById("relatorioFilterForm"),
  relatorioAgrupar: document.getElementById("relatorioAgrupar"),
  relatorioTipo: document.getElementById("relatorioTipo"),
  relatorioProduto: document.getElementById("relatorioProduto"),
  relatorioUnidade: document.getElementById("relatorioUnidade"),
  relatorioDataDe: document.getElementById("relatorioDataDe"),
  relatorioDataAte: document.getElementById("relatorioDataAte"),
  relatorioLimpar: document.getElementById("relatorioLimpar"),
  relatorioResumoTable: document.getElementById("relatorioResumoTable"),
  relatorioDetalhesTable: document.getElementById("relatorioDetalhesTable"),
  relResumoColuna: document.getElementById("relResumoColuna"),
  relatorioExportCsv: document.getElementById("relatorioExportCsv"),
  relatorioExportPdf: document.getElementById("relatorioExportPdf"),
  funcionariosTable: document.getElementById("funcionariosTable"),
  funcionarioModal: document.getElementById("funcionarioModal"),
  funcionarioForm: document.getElementById("funcionarioForm"),
  funcionarioModalTitle: document.getElementById("funcionarioModalTitle"),
  funcionarioFormFeedback: document.getElementById("funcionarioFormFeedback"),
  openFuncionarioBtn: document.getElementById("openFuncionario"),
  closeFuncionarioBtn: document.getElementById("closeFuncionario"),
  cancelFuncionarioBtn: document.getElementById("cancelFuncionario"),
  funcionarioPossuiAcesso: document.getElementById("funcionarioPossuiAcesso"),
  funcionarioAcessoArea: document.getElementById("funcionarioAcessoArea"),
  funcionarioUsuarioModal: document.getElementById("funcionarioUsuarioModal"),
  funcionarioConfigurarUsuario: document.getElementById("funcionarioConfigurarUsuario"),
  funcionarioUsuarioResumo: document.getElementById("funcionarioUsuarioResumo"),
  funcionarioFotoInput: document.getElementById("funcionarioFotoInput"),
  funcionarioAvatarPreview: document.getElementById("funcionarioAvatarPreview"),
  funcionarioFotoTrocar: document.getElementById("funcionarioFotoTrocar"),
  funcionarioFotoRemover: document.getElementById("funcionarioFotoRemover"),
  funcionariosFilterForm: document.getElementById("funcionariosFilterForm"),
  funcionariosLimparFiltros: document.getElementById("funcionariosLimparFiltros"),
  funcionariosFiltroUnidade: document.getElementById("funcionariosFiltroUnidade"),
  funcionarioViewModal: document.getElementById("funcionarioViewModal"),
  funcionarioViewContent: document.getElementById("funcionarioViewContent"),
  closeFuncionarioViewBtn: document.getElementById("closeFuncionarioView"),
  funcionarioViewEditar: document.getElementById("funcionarioViewEditar"),
  funcionarioViewInativar: document.getElementById("funcionarioViewInativar"),
  closeFuncionarioView: document.getElementById("closeFuncionarioView"),
};

let stopMatrixAnimation = null;

// Traducao simples de perfis para nomes amigaveis.
const PERFIL_LABELS = {
  ADMIN: "Administrador",
  ESTOQUISTA: "Estoquista",
  COZINHA: "Cozinha",
  BAR: "Bar",
  FINANCEIRO: "Financeiro",
  ASSISTENTE_ADMINISTRATIVO: "Auxiliar Administrativo",
  VISUALIZADOR: "Visualizador",
  GERENTE: "Gerente",
  ATENDENTE: "Atendente",
  ATENDENTE_CAIXA: "Atendente Caixa",
  FUNCIONARIO: "Funcionário",
};

// Regras de permissao utilizadas para montar menus, botoes e acoes por perfil.
const PERMISSOES = {
  ADMIN: {
    sections: ["boasVindas", "minhaConta", "dashboard", "unidades", "usuarios", "produtos", "fechaTecnica", "estoque", "lotes", "locais", "movimentacoes", "compras", "relatorios", "fornecedores", "fornecedoresBackup", "boletao", "alvara", "proventos", "reciboAjuda", "fechamento", "reservaMesa", "historicoReservas", "funcionarios", "logs"],
    canManageUsuarios: true,
    canManageProdutos: true,
    canManageUnidades: true,
    canManageCompras: true,
    canRegistrarMovimentacoes: true,
  },
  GERENTE: {
    sections: ["boasVindas", "minhaConta", "dashboard", "unidades", "usuarios", "locais", "compras", "produtos", "fechaTecnica", "estoque", "lotes", "movimentacoes", "relatorios", "fornecedores", "boletao", "alvara", "proventos", "reciboAjuda", "fechamento", "reservaMesa", "historicoReservas", "funcionarios", "logs"],
    canManageUsuarios: false,
    canManageProdutos: true,
    canManageUnidades: false,
    canManageCompras: true,
    canRegistrarMovimentacoes: true,
  },
  ESTOQUISTA: {
    sections: ["boasVindas", "minhaConta", "dashboard", "unidades", "locais", "compras", "produtos", "fechaTecnica", "estoque", "lotes", "movimentacoes", "relatorios", "fornecedores"],
    canManageUsuarios: false,
    canManageProdutos: true,
    canManageUnidades: false,
    canManageCompras: true,
    canRegistrarMovimentacoes: true,
  },
  COZINHA: {
    sections: ["boasVindas", "minhaConta", "dashboard", "compras", "produtos", "fechaTecnica", "estoque", "movimentacoes", "relatorios"],
    canManageUsuarios: false,
    canManageProdutos: false,
    canManageUnidades: false,
    canManageCompras: true,
    canRegistrarMovimentacoes: true,
  },
  BAR: {
    sections: ["boasVindas", "minhaConta", "dashboard", "compras", "produtos", "fechaTecnica", "estoque", "movimentacoes", "relatorios", "reservaMesa", "historicoReservas"],
    canManageUsuarios: false,
    canManageProdutos: false,
    canManageUnidades: false,
    canManageCompras: true,
    canRegistrarMovimentacoes: true,
  },
  FINANCEIRO: {
    sections: ["boasVindas", "minhaConta", "dashboard", "relatorios", "fornecedores", "fechaTecnica", "boletao", "alvara", "proventos", "reciboAjuda", "fechamento", "reservaMesa", "historicoReservas"],
    canManageUsuarios: false,
    canManageProdutos: false,
    canManageUnidades: false,
    canManageCompras: false,
    canRegistrarMovimentacoes: false,
  },
  ASSISTENTE_ADMINISTRATIVO: {
    sections: ["boasVindas", "minhaConta", "dashboard", "unidades", "locais", "produtos", "fechaTecnica", "estoque", "lotes", "movimentacoes", "compras", "relatorios", "fornecedores", "boletao", "alvara", "proventos", "reciboAjuda", "fechamento", "reservaMesa", "historicoReservas", "funcionarios"],
    canManageUsuarios: false,
    canManageProdutos: true,
    canManageUnidades: false,
    canManageCompras: true,
    canRegistrarMovimentacoes: true,
  },
  VISUALIZADOR: {
    sections: ["boasVindas", "minhaConta", "dashboard", "relatorios", "fechaTecnica"],
    canManageUsuarios: false,
    canManageProdutos: false,
    canManageUnidades: false,
    canManageCompras: false,
    canRegistrarMovimentacoes: false,
  },
  ATENDENTE: {
    sections: ["boasVindas", "minhaConta", "estoque", "fechaTecnica", "reservaMesa", "historicoReservas"],
    canManageUsuarios: false,
    canManageProdutos: false,
    canManageUnidades: false,
    canManageCompras: false,
    canRegistrarMovimentacoes: false,
  },
  ATENDENTE_CAIXA: {
    sections: ["boasVindas", "minhaConta", "dashboard", "proventos", "reciboAjuda", "fechamento", "fechaTecnica", "reservaMesa", "historicoReservas"],
    canManageUsuarios: false,
    canManageProdutos: false,
    canManageUnidades: false,
    canManageCompras: false,
    canRegistrarMovimentacoes: false,
  },
  FUNCIONARIO: {
    sections: ["boasVindas", "minhaConta", "dashboard", "proventos", "reciboAjuda", "fechamento", "fechaTecnica"],
    canManageUsuarios: false,
    canManageProdutos: false,
    canManageUnidades: false,
    canManageCompras: false,
    canRegistrarMovimentacoes: false,
  },
};

const LOCAL_TIPOS_LABELS = {
  CAMARA_FRIA: "Câmara Fria",
  FREEZER: "Freezer",
  GELADEIRA: "Geladeira",
  DEPOSITO: "Depósito",
  PRATELEIRA: "Prateleira",
  ESTOQUE_SECO: "Estoque Seco",
  COZINHA: "Cozinha",
  BAR: "Bar",
  OUTROS: "Outros",
};

const LOCAL_NIVEL_ACESSO_LABELS = {
  TODOS: "Todos",
  COZINHA: "Cozinha",
  GERENTE: "Gerente",
  ADMINISTRATIVO: "Administrativo",
  ESTOQUISTA: "Estoquista",
  ATENDENTE: "Atendente",
  BAR: "Bar",
  CAIXA: "Caixa",
  SERVICOS_GERAIS: "Serviços Gerais",
  RESTRITO: "Restrito",
  OUTROS: "Outros",
};

// Estrutura central de estado com colecoes e flags compartilhadas.
const state = {
  produtos: [],
  produtosAbaixoMinimo: [],
  perdasResumo: { total_registros: 0, quantidade_total: 0, movimentacoes: [] },
  unidades: [],
  locais: [],
  usuarios: [],
  funcionarios: [],
  proventos: [],
  listasCompras: [],
  listaCompraAtual: null,
  listaComprasFiltroStatus: "ativas",
  listasComprasAtivasSnapshot: [],
  estabelecimentosGlobais: [],
  unidadeInlineVisivel: false,
  lotes: [],
  movimentacoes: [],
  movimentacoesRecentes: [],
  relatorioResumo: [],
  relatorioDetalhes: [],
  /** Ingredientes da ficha técnica (em edição). */
  fichaTecnicaIngredientes: [],
  /** Fichas técnicas (servidor; localStorage só como cache / fallback). */
  fichaTecnicaPratos: [],
};

// Variáveis auxiliares para session e rastreamento de efeitos.
let currentUser = null;
let usuarioFotoFile = null;
let usuarioFotoRemovida = false;
let funcionarioFotoFile = null;
let funcionarioFotoRemovida = false;
let logoDataUrl = null;
/** Ao abrir a seção Ficha técnica no menu: recarrega lista do armazenamento local. */
let onNavigateFichaTecnicaCallback = () => {};
const FICHA_TECNICA_STORAGE_KEY = 'sas-estoque-fichas-tecnicas-v1';
const FICHA_TECNICA_STORAGE_BAK_KEY = 'sas-estoque-fichas-tecnicas-v1.bak';
const FICHA_TECNICA_FOTO_MAX_BYTES = Math.floor(1.8 * 1024 * 1024);
const MOBILE_BREAKPOINT = 1024;
const LISTA_STATUS_LABEL = {
  RASCUNHO: "Rascunho",
  EM_COMPRAS: "Em compras",
  PAUSADA: "Pausada",
  FINALIZADA: "Finalizada",
};
const LISTA_STATUS_CLASS = {
  RASCUNHO: "status-pill--info",
  EM_COMPRAS: "status-pill--success",
  PAUSADA: "status-pill--warning",
  FINALIZADA: "status-pill--success",
};
let listaCompraItemEdicaoId = null;
let listaCompraEstabelecimentoEdicaoId = null;
let finalizarListaArquivos = [];
const pendingItemUpdates = new Map();
let listaStatusAtualizando = false;
let usuariosCarregando = null;
let saidaProdutosRequestId = 0;
let loteProdutosRequestId = 0;
let movimentacoesRequestId = 0;
let entradaLocaisRequestId = 0;
let inactivityTimer = null;
let inactivityResetHandler = null;
const INACTIVITY_TIMEOUT = 6 * 60 * 1000; // 6 minutos em milissegundos

// --- Utilidades de interface e formatação ---
function showToast(message, type = "info") {
  if (!dom.toast) return;
  dom.toast.textContent = message;
  dom.toast.className = `toast toast--${type}`;
  setTimeout(() => {
    dom.toast.className = "toast";
  }, 3200);
}

/**
 * Exibe um modal de erro detalhado para movimentações
 * @param {Object} errorData - Dados do erro retornados pelo backend
 * @param {string} errorData.error - Tipo do erro (ex: "Sem estoque disponível")
 * @param {string} errorData.message - Mensagem descritiva do erro
 * @param {number} [errorData.disponivel] - Quantidade disponível (se aplicável)
 * @param {number} [errorData.solicitado] - Quantidade solicitada (se aplicável)
 * @param {string} [errorData.produto] - Nome do produto (se aplicável)
 */
function showErrorModal(errorData) {
  if (!dom.erroMovimentacaoModal) {
    console.error("Modal de erro não encontrado");
    // Fallback para toast se o modal não existir
    const message = errorData.message || errorData.error || "Erro ao realizar movimentação";
    showToast(message, "error");
    return;
  }

  // Mapeia tipos de erro para mensagens mais compreensíveis
  const errorMessages = {
    "Produto não encontrado": {
      titulo: "Produto não encontrado",
      mensagem: "O produto selecionado não existe no sistema ou foi removido.",
      explicacao: "Isso pode acontecer se o produto foi excluído ou se há um problema com a seleção.",
      solucao: "Verifique se o produto está cadastrado corretamente e tente novamente.",
      tipo: "operacional"
    },
    "Produto inativo": {
      titulo: "Produto inativo",
      mensagem: "O produto selecionado está inativo no sistema.",
      explicacao: "Produtos inativos não podem receber movimentações de estoque por questões de controle e organização.",
      solucao: "Ative o produto na tela de cadastro antes de realizar a movimentação.",
      tipo: "operacional"
    },
    "Sem estoque disponível": {
      titulo: "Produto sem estoque",
      mensagem: "Este produto não possui estoque disponível na unidade selecionada.",
      explicacao: "O produto não tem lotes cadastrados ou todo o estoque já foi utilizado. Para realizar uma saída, é necessário ter pelo menos um lote com quantidade disponível.",
      solucao: "Crie um lote para este produto através de uma entrada de estoque. Acesse 'Entrada de Estoque' e registre um novo lote com a quantidade desejada.",
      tipo: "estoque",
      sugereCriarLote: true
    },
    "Estoque insuficiente": {
      titulo: "Estoque insuficiente",
      mensagem: "A quantidade solicitada é maior que a quantidade disponível em estoque.",
      explicacao: "Você está tentando retirar mais produtos do que existem no estoque atual. O estoque disponível não é suficiente para atender a quantidade solicitada.",
      solucao: "Ajuste a quantidade solicitada para o valor disponível, ou registre uma nova entrada de estoque para aumentar o estoque deste produto.",
      tipo: "estoque"
    },
    "Nenhum lote disponível": {
      titulo: "Produto sem lote cadastrado",
      mensagem: "Este produto não possui nenhum lote cadastrado na unidade selecionada.",
      explicacao: "Para realizar uma saída de estoque, é obrigatório ter pelo menos um lote cadastrado com quantidade disponível. Sem lotes, não é possível controlar a origem e validade dos produtos.",
      solucao: "Crie um lote para este produto. Acesse 'Entrada de Estoque', selecione o produto e a unidade, informe o número do lote e a quantidade. Isso criará automaticamente o lote necessário.",
      tipo: "estoque",
      sugereCriarLote: true
    },
    "Lotes vencidos bloqueiam a saída": {
      titulo: "Lotes vencidos impedem a saída",
      mensagem: "Os lotes disponíveis deste produto estão vencidos e bloqueiam a saída automática.",
      explicacao: "O sistema identificou que os lotes em estoque estão com a data de validade vencida. Por padrão, o sistema não permite usar lotes vencidos automaticamente para garantir a qualidade e segurança dos produtos.",
      solucao: "Opção 1: Se você realmente precisa usar lotes vencidos, marque a opção 'Forçar' no formulário de saída. Opção 2: Registre uma nova entrada de estoque com lotes válidos (não vencidos) para este produto.",
      tipo: "vencimento"
    },
    "Dados inválidos": {
      titulo: "Dados inválidos",
      mensagem: "Alguns dados informados estão incorretos ou incompletos.",
      explicacao: "Um ou mais campos obrigatórios não foram preenchidos corretamente, ou contêm valores inválidos que impedem o processamento da movimentação.",
      solucao: "Revise o formulário e verifique se todos os campos obrigatórios foram preenchidos corretamente. Verifique especialmente: produto selecionado, unidade, quantidade e motivo da movimentação.",
      tipo: "operacional"
    }
  };

  // Obtém o tipo de erro
  const errorType = errorData.error || "";
  const errorInfo = errorMessages[errorType] || {
    titulo: errorData.error || "Erro na movimentação",
    mensagem: errorData.message || "Não foi possível realizar a movimentação.",
    explicacao: "Ocorreu um erro ao processar sua solicitação.",
    solucao: "Tente novamente ou entre em contato com o suporte se o problema persistir."
  };

  // Define o título do erro
  dom.erroMovimentacaoTitulo.textContent = errorInfo.titulo;

  // Monta a mensagem completa e compreensível
  let mensagemCompleta = `<div style="margin-bottom: 1rem;"><strong>O que aconteceu:</strong><br>${escapeHtml(errorInfo.mensagem)}</div>`;
  
  mensagemCompleta += `<div style="margin-bottom: 1rem;"><strong>Por que isso aconteceu:</strong><br>${escapeHtml(errorInfo.explicacao)}</div>`;
  
  // Adiciona informação sobre tipo de problema
  if (errorInfo.tipo) {
    let tipoLabel = "";
    let tipoIcon = "";
    if (errorInfo.tipo === "estoque") {
      tipoLabel = "Problema de Estoque";
      tipoIcon = "📦";
    } else if (errorInfo.tipo === "vencimento") {
      tipoLabel = "Problema de Validade";
      tipoIcon = "📅";
    } else if (errorInfo.tipo === "operacional") {
      tipoLabel = "Problema Operacional";
      tipoIcon = "⚙️";
    }
    
    if (tipoLabel) {
      mensagemCompleta += `<div style="margin-bottom: 1rem; padding: 0.75rem; background: #fff3cd; border-left: 3px solid #ffc107; border-radius: 4px; font-size: 0.9rem;">
        <strong>${tipoIcon} Tipo de problema:</strong> ${tipoLabel}
      </div>`;
    }
  }
  
  mensagemCompleta += `<div style="margin-bottom: 0.5rem;"><strong>Como resolver:</strong><br>${escapeHtml(errorInfo.solucao)}</div>`;
  
  // Se sugere criar lote, adiciona destaque especial
  if (errorInfo.sugereCriarLote) {
    mensagemCompleta += `<div style="margin-top: 1rem; padding: 1rem; background: #e3f2fd; border-left: 3px solid #2196f3; border-radius: 4px; font-size: 0.9rem;">
      <strong>💡 Dica importante:</strong><br>
      Para criar um lote, acesse o menu <strong>"Entrada de Estoque"</strong>, selecione este produto, informe o número do lote e a quantidade. O sistema criará automaticamente o lote necessário.
    </div>`;
  }

  dom.erroMovimentacaoMensagem.innerHTML = mensagemCompleta;

  // Prepara detalhes adicionais se disponíveis
  const detalhes = [];
  
  if (errorData.produto) {
    detalhes.push(`<strong>Produto:</strong> ${escapeHtml(errorData.produto)}`);
  }
  
  if (errorData.disponivel !== undefined && errorData.solicitado !== undefined) {
    detalhes.push(`<strong>Quantidade disponível:</strong> ${errorData.disponivel}`);
    detalhes.push(`<strong>Quantidade solicitada:</strong> ${errorData.solicitado}`);
    const diferenca = errorData.solicitado - errorData.disponivel;
    if (diferenca > 0) {
      detalhes.push(`<strong>Faltam:</strong> ${diferenca} unidades`);
    }
  } else if (errorData.disponivel !== undefined) {
    detalhes.push(`<strong>Quantidade disponível:</strong> ${errorData.disponivel}`);
  }

  // Mostra ou esconde a seção de detalhes
  if (detalhes.length > 0) {
    dom.erroMovimentacaoDetalhes.innerHTML = `<div style="font-size: 0.9rem;"><strong>Informações detalhadas:</strong><br>${detalhes.join("<br>")}</div>`;
    dom.erroMovimentacaoDetalhes.style.display = "block";
  } else {
    dom.erroMovimentacaoDetalhes.style.display = "none";
  }

  // Exibe o modal
  dom.erroMovimentacaoModal.style.display = "flex";
}

/**
 * Fecha o modal de erro de movimentação
 */
function closeErrorModal() {
  if (dom.erroMovimentacaoModal) {
    dom.erroMovimentacaoModal.style.display = "none";
  }
}

function buildStatusPill(status) {
  const label = escapeHtml(status || "--");
  const normalized = (status || "").toString().trim().toLowerCase();
  let modifier = "info";
  if (["ativo", "disponivel", "liberado", "entrada"].includes(normalized)) modifier = "success";
  else if (["vencido", "bloqueado", "critico", "saida", "perda"].includes(normalized)) modifier = "danger";
  else if (["a vencer", "avencer", "pendente", "ajuste"].includes(normalized)) modifier = "warning";
  else if (["esgotado", "inativo"].includes(normalized)) modifier = "muted";
  else if (["transferencia"].includes(normalized)) modifier = "info";
  else if (["reversao"].includes(normalized)) modifier = "warning";
  return `<span class="status-pill status-pill--${modifier}">${label}</span>`;
}

function isMobileViewport() {
  return window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT}px)`).matches;
}

const SIDEBAR_COLLAPSED_KEY = "sas-sidebar-collapsed";

function setSidebarOpen(open) {
  if (!dom.sidebar) return;
  dom.sidebar.classList.toggle("is-open", open);
  dom.sidebarBackdrop?.classList.toggle("is-active", open);
  document.body.classList.toggle("sidebar-open", open);
}

function setSidebarCollapsed(collapsed) {
  if (!dom.sidebar) return;
  const apply = collapsed && !isMobileViewport();
  dom.sidebar.classList.toggle("is-collapsed", apply);
  try {
    localStorage.setItem(SIDEBAR_COLLAPSED_KEY, apply ? "1" : "0");
  } catch (_) {}
}

function isSidebarCollapsed() {
  try {
    return localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === "1";
  } catch (_) {
    return false;
  }
}

function setupResponsiveSidebar() {
  if (!dom.menuToggle || !dom.sidebar) return;

  dom.menuToggle.addEventListener("click", () => {
    const willOpen = !dom.sidebar.classList.contains("is-open");
    setSidebarOpen(willOpen);
  });

  dom.sidebarBackdrop?.addEventListener("click", () => setSidebarOpen(false));

  const updateCollapseBtnLabel = () => {
    if (!dom.sidebarCollapseBtn) return;
    const collapsed = dom.sidebar?.classList.contains("is-collapsed");
    const label = collapsed ? "Expandir menu" : "Recolher menu";
    dom.sidebarCollapseBtn.setAttribute("aria-label", label);
    dom.sidebarCollapseBtn.setAttribute("title", label);
  };

  if (dom.sidebarCollapseBtn) {
    dom.sidebarCollapseBtn.addEventListener("click", () => {
      if (isMobileViewport()) return;
      const collapsed = !dom.sidebar.classList.contains("is-collapsed");
      setSidebarCollapsed(collapsed);
      updateCollapseBtnLabel();
    });
  }

  window.addEventListener("resize", () => {
    if (!isMobileViewport()) {
      setSidebarOpen(false);
      if (isSidebarCollapsed()) {
        dom.sidebar?.classList.add("is-collapsed");
      } else {
        dom.sidebar?.classList.remove("is-collapsed");
      }
    } else {
      dom.sidebar?.classList.remove("is-collapsed");
    }
    updateCollapseBtnLabel();
  });

  if (!isMobileViewport() && isSidebarCollapsed()) {
    dom.sidebar?.classList.add("is-collapsed");
  }
  updateCollapseBtnLabel();

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && dom.sidebar?.classList.contains("is-open")) {
      setSidebarOpen(false);
    }
  });
}

function initMatrixBackground() {
  const canvas = dom.matrixCanvas;
  if (!canvas) return null;
  const ctx = canvas.getContext("2d");
  if (!ctx) return null;

  const characters = "01ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890#$%&";
  let width = canvas.offsetWidth;
  let height = canvas.offsetHeight;
  let fontSize = 16;
  let columns = 0;
  let drops = [];
  let animationId = null;

  const face = {
    active: false,
    start: 0,
    duration: 2200,
    next: performance.now() + 8000 + Math.random() * 12000,
  };

  const easeInOut = (t) => (t < 0.5 ? 4 * t * t * t : 1 - ((-2 * t + 2) ** 3) / 2);
  const scheduleFace = () => {
    face.next = performance.now() + 8000 + Math.random() * 12000;
  };

  const resizeCanvas = () => {
    const { offsetWidth, offsetHeight } = canvas;
    const dpr = window.devicePixelRatio || 1;
    canvas.width = Math.floor(offsetWidth * dpr);
    canvas.height = Math.floor(offsetHeight * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    width = offsetWidth;
    height = offsetHeight;
    fontSize = Math.max(Math.round(width / 80), 14);
    columns = Math.max(Math.floor(width / fontSize), 1);
    drops = new Array(columns).fill(0);
  };

  const drawNeonFace = (alpha = 1) => {
    const size = Math.min(width, height) * 0.35;
    const cx = width * 0.5;
    const cy = height * 0.45;

    ctx.save();
    ctx.translate(cx, cy);
    ctx.scale(1, 1.1);
    ctx.globalAlpha = alpha;

    ctx.lineWidth = 2;
    ctx.strokeStyle = "#00ff88";
    ctx.shadowColor = "#00ff88";
    ctx.shadowBlur = 12;

    ctx.beginPath();
    ctx.ellipse(0, 0, size * 0.38, size * 0.5, 0, 0, Math.PI * 2);
    ctx.stroke();

    for (let i = -3; i <= 3; i += 1) {
      ctx.beginPath();
      const yy = (i / 3) * size * 0.42;
      ctx.moveTo(-size * 0.3, yy);
      ctx.bezierCurveTo(-size * 0.1, yy * 1.05, size * 0.1, yy * 1.05, size * 0.3, yy);
      ctx.stroke();
    }

    const eyeY = -size * 0.08;
    const eyeX = size * 0.16;
    const eyeW = size * 0.1;
    const eyeH = size * 0.04;
    ctx.beginPath();
    ctx.ellipse(-eyeX, eyeY, eyeW, eyeH, 0, 0, Math.PI * 2);
    ctx.stroke();
    ctx.beginPath();
    ctx.ellipse(eyeX, eyeY, eyeW, eyeH, 0, 0, Math.PI * 2);
    ctx.stroke();

    ctx.beginPath();
    ctx.moveTo(0, -size * 0.02);
    ctx.lineTo(-size * 0.02, size * 0.07);
    ctx.lineTo(size * 0.02, size * 0.07);
    ctx.stroke();

    ctx.beginPath();
    ctx.moveTo(-size * 0.12, size * 0.14);
    ctx.quadraticCurveTo(0, size * 0.18, size * 0.12, size * 0.14);
    ctx.stroke();

    for (let i = 0; i < 5; i += 1) {
      const px = -size * 0.35;
      const py = -size * 0.3 + i * (size * 0.12);
      ctx.beginPath();
      ctx.moveTo(px, py);
      ctx.lineTo(px + size * 0.08, py + size * 0.02);
      ctx.lineTo(px + size * 0.16, py);
      ctx.stroke();

      ctx.beginPath();
      ctx.moveTo(-px, py);
      ctx.lineTo(-px - size * 0.08, py + size * 0.02);
      ctx.lineTo(-px - size * 0.16, py);
      ctx.stroke();
    }

    ctx.restore();
  };

  const drawMatrix = () => {
    ctx.fillStyle = "rgba(0, 0, 0, 0.15)";
    ctx.fillRect(0, 0, width, height);
    ctx.fillStyle = "#31ff7b";
    ctx.font = `${fontSize}px "Fira Code", "Roboto Mono", monospace`;
    ctx.textBaseline = "top";

    for (let i = 0; i < columns; i += 1) {
      const char = characters[Math.floor(Math.random() * characters.length)];
      const x = i * fontSize;
      const y = drops[i] * fontSize;
      ctx.fillText(char, x, y);
      if (y > height && Math.random() > 0.965) drops[i] = 0;
      else drops[i] += 1;
    }
  };

  const renderNeonFace = (now) => {
    if (!face.active && now >= face.next) {
      face.active = true;
      face.start = now;
    }

    if (!face.active) return;

    const progress = (now - face.start) / face.duration;
    if (progress >= 1) {
      face.active = false;
      scheduleFace();
      return;
    }

    const fadeIn = easeInOut(Math.min(1, Math.max(0, progress * 1.2)));
    const decayFactor = 1 - (progress - 0.4) / 0.6;
    const fadeOut = easeInOut(Math.min(1, Math.max(0, decayFactor)));
    const alpha = Math.min(fadeIn, fadeOut) * 0.9;
    if (alpha <= 0) return;

    drawNeonFace(alpha);

    if (Math.random() < 0.08) {
      const glitchY = Math.random() * height;
      ctx.save();
      ctx.globalAlpha = alpha * 0.35;
      ctx.fillStyle = "#00ff88";
      ctx.fillRect(0, glitchY, width, 1);
      ctx.restore();
    }
  };

  const loop = () => {
    const now = performance.now();
    drawMatrix();
    renderNeonFace(now);
    animationId = requestAnimationFrame(loop);
  };

  resizeCanvas();
  loop();

  const handleResize = debounce(() => {
    resizeCanvas();
  }, 200);

  window.addEventListener("resize", handleResize);

  return () => {
    if (animationId) cancelAnimationFrame(animationId);
    window.removeEventListener("resize", handleResize);
    ctx.clearRect(0, 0, canvas.width, canvas.height);
  };
}

function escapeHtml(value) {
  return String(value ?? "").replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function formatCurrency(value) {
  return `R$ ${formatNumber(value, 2)}`;
}

function debounce(fn, delay = 600) {
  let timer = null;
  return (...args) => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}

function formatNumber(value, digits = 2) {
  const num = Number(value ?? 0);
  if (!Number.isFinite(num)) return "0";
  if (digits === 0 || num === 0) return String(Math.round(num));
  const texto = num.toFixed(digits);
  return texto.replace(/(\.\d*?)0+$/, "$1").replace(/\.$/, "");
}

// Formata valor unitário: mostra sem decimais se for inteiro, caso contrário mostra decimais necessários
function formatUnitValue(value) {
  const num = Number(value ?? 0);
  if (!Number.isFinite(num)) return "0";
  // Se for inteiro, retorna sem decimais
  if (Number.isInteger(num)) return String(num);
  // Caso contrário, formata com até 2 casas decimais, removendo zeros à direita
  const texto = num.toFixed(2);
  return texto.replace(/(\.\d*?)0+$/, "$1").replace(/\.$/, "");
}

function roundToCurrency(value) {
  const num = Number(value ?? 0);
  if (!Number.isFinite(num)) return 0;
  return Math.round(num * 100) / 100;
}

function formatCurrencyBRL(value) {
  const num = Number(value ?? 0);
  if (!Number.isFinite(num)) return "R$ 0,00";
  return num.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}

function roundToQuantity(value) {
  const num = Number(value ?? 0);
  if (!Number.isFinite(num)) return 0;
  return Math.round(num * 100) / 100;
}

function formatQuantityDisplay(value) {
  const num = Number(value ?? 0);
  if (!Number.isFinite(num)) return "0";
  // Se for inteiro, retorna sem decimais
  if (Number.isInteger(num)) return String(num);
  // Caso contrário, formata com até 2 casas decimais, removendo zeros à direita
  const texto = num.toFixed(2);
  return texto.replace(/(\.\d*?)0+$/, "$1").replace(/\.$/, "");
}

// Formata data ISO ou string mantendo o dia exato (evita erro de fuso horário voltando 1 dia)
function fmtData(dataStr) {
  if (!dataStr) return '--';
  try {
    const s = String(dataStr).trim();
    const match = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (match) {
      return `${match[3]}/${match[2]}/${match[1]}`;
    }
    const d = new Date(s);
    if (isNaN(d.getTime())) return '--';
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    return `${day}/${month}/${d.getFullYear()}`;
  } catch {
    return '--';
  }
}

function formatDate(value) {
  if (!value) return "--";
  // Qualquer string com YYYY-MM-DD: extrai só a data e formata, sem usar new Date().
  // Evita bug de fuso horário (ex: 2026-03-15T00:00:00Z em UTC vira 14/03 no Brasil).
  if (typeof value === "string") {
    const match = value.trim().match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (match) {
      return `${match[3]}/${match[2]}/${match[1]}`;
    }
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "--";
  const dia = String(date.getDate()).padStart(2, "0");
  const mes = String(date.getMonth() + 1).padStart(2, "0");
  const ano = String(date.getFullYear());
  const hora = String(date.getHours()).padStart(2, "0");
  const minuto = String(date.getMinutes()).padStart(2, "0");
  return `${dia}/${mes}/${ano} ${hora}:${minuto}`;
}

function formatFileSize(bytes) {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Normaliza unidade base: substitui "un" por "UND"
function fmtNum(n) {
  if (n == null || isNaN(n)) return "0";
  return parseFloat(Number(n).toFixed(3)).toString();
}

function normalizarUnidadeBase(value) {
  if (!value) return "UND";
  const unidade = String(value).trim().toLowerCase();
  if (unidade === "un") return "UND";
  return unidade.toUpperCase();
}

function toInputDate(value) {
  if (!value) return "";
  if (typeof value === "string" && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return value;
  }
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  const ano = date.getFullYear();
  const mes = String(date.getMonth() + 1).padStart(2, "0");
  const dia = String(date.getDate()).padStart(2, "0");
  return `${ano}-${mes}-${dia}`;
}

function todayInputValue() {
  const agora = new Date();
  return toInputDate(agora);
}

function generateLoteCodigo() {
  const agora = new Date();
  const dia = String(agora.getDate()).padStart(2, "0");
  const mes = String(agora.getMonth() + 1).padStart(2, "0");
  const ano = String(agora.getFullYear());
  const sufixo = Math.random().toString(36).slice(2, 6).toUpperCase();
  return `L${dia}${mes}${ano}-${sufixo}`;
}

async function ensureLocaisCarregados(force = false) {
  if (force || !Array.isArray(state.locais) || !state.locais.length) {
    try {
      await loadLocais(true);
    } catch (err) {
      showToast(err?.message || "Falha ao carregar locais.", "error");
    }
  }
  return Array.isArray(state.locais) ? state.locais : [];
}

function populateEntradaLoteOptions() {
  const datalist = document.getElementById("entradaLoteOptions");
  if (!datalist) return;
  const codigos = new Map();
  (state.lotes || []).forEach((lote) => {
    const codigo = (lote.codigo_lote || lote.numero_lote || "").toString().trim();
    if (!codigo) return;
    if (codigos.has(codigo)) return;
    const produto = lote.produto_nome ? ` — ${escapeHtml(lote.produto_nome)}` : "";
    const unidade = lote.unidade_nome ? ` (${escapeHtml(lote.unidade_nome)})` : "";
    codigos.set(
      codigo,
      `<option value="${escapeHtml(codigo)}">${escapeHtml(codigo)}${produto}${unidade}</option>`,
    );
  });
  datalist.innerHTML = Array.from(codigos.values()).join("");
}

function populateEntradaLocaisSelect(listaLocais = [], unidadeId = null) {
  const select = dom.entradaLocalSelect || dom.entradaForm?.querySelector('select[name="local_id"]');
  if (!select) return;
  const unidadeNumero = Number(unidadeId);
  if (!Number.isFinite(unidadeNumero) || unidadeNumero <= 0) {
    resetEntradaLocalSelect();
    return;
  }
  const lista = Array.isArray(listaLocais)
    ? listaLocais.filter((local) => Number(local.unidade_id) === unidadeNumero)
    : [];
  if (!lista.length) {
    resetEntradaLocalSelect("Nenhum local cadastrado nesta unidade");
    return;
  }
  const ordenados = [...lista].sort((a, b) => (a.nome || "").localeCompare(b.nome || "", "pt-BR"));
  const options = ordenados
    .map((local) =>
      `<option value="${escapeHtml(String(local.id))}" data-unidade-id="${escapeHtml(String(local.unidade_id ?? ""))}">${escapeHtml(local.nome || `Local ${local.id}`)}</option>`,
    )
    .join("");
  populateSelect(select, options, "Selecione o local");
  select.disabled = false;
}

function formatCnpjMask(rawValue) {
  const digits = (rawValue || "").replace(/\D/g, "").slice(0, 14);
  const parts = [];
  if (digits.length <= 2) return digits;
  parts.push(digits.slice(0, 2));
  parts.push(digits.slice(2, 5));
  parts.push(digits.slice(5, 8));
  const branch = digits.slice(8, 12);
  const suffix = digits.slice(12, 14);
  let formatted = `${parts[0]}.${parts[1]}`;
  if (parts[2]) formatted += `.${parts[2]}`;
  if (branch) formatted += `/${branch}`;
  if (suffix) formatted += `-${suffix}`;
  return formatted;
}

function attachCnpjMask(input) {
  if (!input) return;
  input.addEventListener("input", () => {
    const formatted = formatCnpjMask(input.value);
    input.value = formatted;
  });
  if (input.value) input.value = formatCnpjMask(input.value);
}

function formatCpfMask(rawValue) {
  const digits = (rawValue || "").replace(/\D/g, "").slice(0, 11);
  if (digits.length <= 3) return digits;
  if (digits.length <= 6) return `${digits.slice(0, 3)}.${digits.slice(3)}`;
  return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
}

/** Formata CNPJ ou CPF para exibição (lista, detalhes) */
function formatCnpjCpfDisplay(val) {
  if (!val || typeof val !== 'string') return '-';
  const digits = val.replace(/\D/g, '');
  if (digits.length === 14) return formatCnpjMask(val);
  if (digits.length === 11) return formatCpfMask(val);
  return val;
}

function attachCpfMask(input) {
  if (!input) return;
  input.addEventListener("input", () => {
    input.value = formatCpfMask(input.value);
  });
  if (input.value) input.value = formatCpfMask(input.value);
}

function updateSaidaDestinoVisibility() {
  const motivo = (dom.saidaMotivo?.value || "").toUpperCase();
  const isTransferencia = motivo === "TRANSFERENCIA";
  if (dom.saidaDestinoWrapper) dom.saidaDestinoWrapper.classList.toggle("hidden", !isTransferencia);
  if (!isTransferencia && dom.saidaDestinoSelect) {
    dom.saidaDestinoSelect.value = "";
  }
}

/** Abre o modal de Registro de Saída pré-preenchido com dados do lote (QR code da etiqueta).
 *  Unidade origem, produto e lote ficam bloqueados; usuário só informa motivo e quantidade. */
async function abrirModalSaidaComLote(loteId) {
  const perfilAtual = (currentUser?.perfil || "").toString().trim().toUpperCase();
  const isCozinhaOuBar = perfilAtual === "COZINHA" || perfilAtual === "BAR" || perfilAtual === "ATENDENTE";
  const regras = PERMISSOES[perfilAtual] || PERMISSOES.VISUALIZADOR;
  const podeUsar = regras.canRegistrarMovimentacoes || isCozinhaOuBar;
  if (!podeUsar) {
    showToast("Você não tem permissão para registrar saídas.", "warning");
    return;
  }
  if (!loteId) {
    showToast("Lote não informado.", "error");
    return;
  }
  try {
    const lote = await fetchJSON(`/lotes/${loteId}`);
    if (!lote || !lote.id) {
      showToast("Lote não encontrado.", "error");
      return;
    }
    await loadUnidades(false).catch(() => {});
    dom.saidaForm = document.getElementById("saidaForm");
    dom.saidaProdutoSelect = document.getElementById("saidaProdutoSelect");
    dom.saidaOrigemSelect = document.getElementById("saidaOrigemUnidade");
    dom.saidaMotivo = document.getElementById("saidaMotivo");
    dom.saidaDestinoSelect = document.getElementById("saidaDestinoUnidade");
    dom.saidaLoteWrapper = document.getElementById("saidaLoteWrapper");
    dom.saidaLoteSelect = document.getElementById("saidaLoteSelect");
    dom.saidaLoteManualWrapper = document.getElementById("saidaLoteManualWrapper");
    dom.saidaLoteManualInput = document.getElementById("saidaLoteManualInput");
    if (!dom.saidaForm || !dom.saidaOrigemSelect || !dom.saidaProdutoSelect) {
      showToast("Erro ao abrir formulário de saída.", "error");
      return;
    }
    dom.saidaForm.reset();
    updateSaidaDestinoVisibility();
    resetSaidaProdutoSelect();
    dom.saidaOrigemSelect.value = String(lote.unidade_id || "");
    dom.saidaOrigemSelect.disabled = true;
    await handleSaidaOrigemChange();
    dom.saidaProdutoSelect.value = String(lote.produto_id || "");
    dom.saidaProdutoSelect.disabled = true;
    const buscaInput = document.getElementById("saidaProdutoBusca");
    if (buscaInput) {
      buscaInput.value = lote.produto_nome || "";
      buscaInput.disabled = true;
    }
    await handleSaidaProdutoChange();
    const codigoLote = lote.numero_lote || lote.codigo_lote || `Lote #${lote.id}`;
    if (dom.saidaLoteWrapper) dom.saidaLoteWrapper.classList.remove("hidden");
    if (dom.saidaLoteSelect) {
      const opt = Array.from(dom.saidaLoteSelect.options).find((o) => o.value === codigoLote);
      if (opt) {
        dom.saidaLoteSelect.value = codigoLote;
      } else {
        dom.saidaLoteSelect.innerHTML =
          `<option value="${escapeHtml(codigoLote)}" selected>${escapeHtml(codigoLote)}</option>` +
          dom.saidaLoteSelect.innerHTML;
        dom.saidaLoteSelect.value = codigoLote;
      }
      dom.saidaLoteSelect.disabled = true;
    }
    dom.saidaForm.dataset.fromQr = "1";
    const submitBtn = dom.saidaForm.querySelector('button[type="submit"]');
    if (submitBtn) {
      submitBtn.onclick = function (e) {
        e.preventDefault();
        e.stopPropagation();
        submitSaida(e).catch((err) => {
          console.error("Erro ao processar:", err);
          showToast(err.message || "Erro ao registrar saída", "error");
        });
      };
    }
    toggleModal(dom.saidaModal, true);
    showToast("Escaneio detectado. Informe motivo e quantidade.", "success");
  } catch (err) {
    console.error("Erro ao abrir saída pelo QR:", err);
    showToast(err?.message || "Erro ao carregar dados do lote.", "error");
  }
}

/** Remove o modo QR do formulário de saída (reabilita campos bloqueados). */
function resetSaidaFromQR() {
  if (!dom.saidaForm || dom.saidaForm.dataset.fromQr !== "1") return;
  delete dom.saidaForm.dataset.fromQr;
  dom.saidaOrigemSelect.disabled = false;
  dom.saidaProdutoSelect.disabled = false;
  const buscaInput = document.getElementById("saidaProdutoBusca");
  if (buscaInput) buscaInput.disabled = false;
  if (dom.saidaLoteSelect) dom.saidaLoteSelect.disabled = false;
}

function collectLotesFiltros() {
  return {
    pesquisa: dom.lotesFiltroPesquisa?.value || "",
    produto_id: dom.lotesFiltroProduto?.value || "",
    unidade_id: dom.lotesFiltroUnidade?.value || "",
    status: dom.lotesFiltroStatus?.value || "",
    validade_de: dom.lotesFiltroValidadeDe?.value || "",
    validade_ate: dom.lotesFiltroValidadeAte?.value || "",
  };
}

function collectMovimentacoesFiltros() {
  return {
    tipo: dom.movFiltroTipo?.value || "",
    produto_id: dom.movFiltroProduto?.value || "",
    unidade_id: dom.movFiltroUnidade?.value || "",
    data_ini: dom.movFiltroDataDe?.value || "",
    data_fim: dom.movFiltroDataAte?.value || "",
  };
}

function collectRelatorioFiltros() {
  const agrupar = dom.relatorioAgrupar?.value || "produto";
  return {
    agrupar,
    tipo: dom.relatorioTipo?.value || "",
    produto_id: dom.relatorioProduto?.value || "",
    unidade_id: dom.relatorioUnidade?.value || "",
    data_ini: dom.relatorioDataDe?.value || "",
    data_fim: dom.relatorioDataAte?.value || "",
  };
}

function sortMovimentacoes(lista) {
  const itens = Array.isArray(lista) ? [...lista] : [];
  // Ordena mais recente no topo: id maior = registrado por último
  return itens.sort((a, b) => {
    const idA = Number(a.id) || 0;
    const idB = Number(b.id) || 0;
    if (idB !== idA) return idB - idA;
    const parseData = (mov) => {
      const campos = [mov.data_mov, mov.data, mov.created_at, mov.criado_em];
      for (const valor of campos) {
        if (!valor) continue;
        const time = new Date(valor).getTime();
        if (!Number.isNaN(time)) return time;
      }
      return 0;
    };
    return parseData(b) - parseData(a);
  });
}

async function fetchJSON(path, options = {}) {
  const tokenHeaders = currentUser && currentUser.token ? { Authorization: `Bearer ${currentUser.token}` } : {};
  const userHeaders =
    currentUser && typeof currentUser.id !== "undefined" && currentUser.id !== null
      ? { "X-Usuario-Id": String(currentUser.id) }
      : {};
  const { headers, ...rest } = options;
  const mergedHeaders = {
    "Content-Type": "application/json",
    ...tokenHeaders,
    ...userHeaders,
    ...getDeviceHeaders(),
    ...(headers || {}),
  };
  
  try {
    const res = await fetch(`${API_URL}${path}`, {
      cache: "no-store",
      headers: mergedHeaders,
      ...rest,
    });
    
    const contentType = res.headers.get("Content-Type") || "";
    let payload;
    
    try {
      // Tenta parsear como JSON primeiro (nosso backend sempre retorna JSON)
      const text = await res.text();
      if (text && text.trim()) {
        try {
          payload = JSON.parse(text);
        } catch {
          // Se não for JSON válido, usa o texto
          payload = text;
        }
      } else {
        // Resposta vazia - para DELETE com sucesso, pode ser normal
        // Mas nosso backend sempre retorna JSON, então isso não deveria acontecer
        payload = { success: res.ok, message: res.statusText || 'Operação realizada' };
      }
    } catch (parseError) {
      throw new Error(`Erro ao processar resposta do servidor (Status: ${res.status}): ${parseError.message}`);
    }
    
    // ✅ 3. Conferir a resposta do backend
    console.log("Resposta do servidor:", {
      url: `${API_URL}${path}`,
      status: res.status,
      statusText: res.statusText,
      payload: payload
    });
    
    if (!res.ok) {
      let message = `Erro ${res.status}: ${res.statusText}`;
      
      if (payload) {
        // ✅ Prioriza message se existir (mensagem mais descritiva)
        if (payload.message) {
          message = payload.message;
        } else if (payload.error) {
          message = payload.error;
        } else if (payload.messages && typeof payload.messages === 'object') {
          // Erros de validação do Laravel
          const errors = Object.values(payload.messages).flat();
          message = errors.length > 0 ? errors.join(', ') : message;
        } else if (typeof payload === 'string' && !payload.trim().startsWith('<')) {
          message = payload;
        }
      }
      // Nunca usar HTML como mensagem (ex: página 500)
      if (typeof message === 'string' && (message.length > 500 || message.trim().startsWith('<'))) {
        message = res.status >= 500 ? 'Erro no servidor. Tente novamente.' : `Erro ${res.status}`;
      }
      
      console.error("Erro na resposta do servidor:", {
        status: res.status,
        message: message,
        payload: payload
      });
      
      // Cria um erro com os dados completos do payload
      const error = new Error(message);
      error.responseData = payload; // Adiciona os dados completos do backend
      error.status = res.status;
      throw error;
    }
    
    return payload;
  } catch (error) {
    // Trata erros de rede
    if (error.name === "TypeError" && (error.message.includes("fetch") || error.message.includes("NetworkError"))) {
      throw new Error(`Não foi possível conectar ao servidor. Verifique se o servidor está rodando em ${API_URL}`);
    }
    throw error;
  }
}

/**
 * Registra entrada de estoque via API centralizada (gera lote automaticamente se numero_lote não informado).
 * Usado pelo dashboard (entrada manual) e pela lista de compras.
 * @param {Object} dadosEntrada - { produto_id, unidade_id, quantidade, custo_unitario, usuario_id, numero_lote?, data_fabricacao?, data_validade?, local_id?, motivo?, observacao?, origem?: 'DASHBOARD'|'LISTA_COMPRAS' }
 * @returns {Promise<Object>} Resposta da API
 */
async function registrarEntradaEstoque(dadosEntrada) {
  const url = `${API_URL}/estoque/entradas`;
  const body = {
    produto_id: dadosEntrada.produto_id,
    unidade_id: dadosEntrada.unidade_id,
    quantidade: dadosEntrada.quantidade ?? dadosEntrada.qtd,
    custo_unitario: dadosEntrada.custo_unitario,
    usuario_id: dadosEntrada.usuario_id,
    numero_lote: dadosEntrada.numero_lote && String(dadosEntrada.numero_lote).trim() ? dadosEntrada.numero_lote : undefined,
    data_fabricacao: dadosEntrada.data_fabricacao || undefined,
    data_validade: dadosEntrada.data_validade || undefined,
    local_id: dadosEntrada.local_id || undefined,
    motivo: dadosEntrada.motivo || undefined,
    observacao: dadosEntrada.observacao || undefined,
    origem: dadosEntrada.origem || "DASHBOARD",
  };
  const res = await fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      ...(currentUser?.token ? { Authorization: `Bearer ${currentUser.token}` } : {}),
      ...(currentUser?.id != null ? { "X-Usuario-Id": String(currentUser.id) } : {}),
    },
    body: JSON.stringify(body),
  });
  const payload = await res.json().catch(() => ({}));
  if (!res.ok) {
    const err = new Error(payload.message || payload.error || `Erro ${res.status}`);
    err.responseData = payload;
    err.status = res.status;
    throw err;
  }
  return payload;
}

async function fetchForm(path, method, body) {
  const tokenHeaders = currentUser && currentUser.token ? { Authorization: `Bearer ${currentUser.token}` } : {};
  const userHeaders =
    currentUser && typeof currentUser.id !== "undefined" && currentUser.id !== null
      ? { "X-Usuario-Id": String(currentUser.id) }
      : {};
  
  // Quando o body é FormData, NÃO definir Content-Type - o navegador faz isso automaticamente
  // com o boundary correto para multipart/form-data
  const headers = { ...tokenHeaders, ...userHeaders };
  
  // Se não for FormData, pode definir Content-Type se necessário
  // Mas para FormData, deixamos o navegador fazer isso
  
  console.log("📤 fetchForm:", { path, method, bodyType: body instanceof FormData ? "FormData" : typeof body });
  
  const res = await fetch(`${API_URL}${path}`, { method, body, headers });
  
  const text = await res.text();
  let payload = {};
  try {
    payload = text ? JSON.parse(text) : {};
  } catch (e) {
    console.error("Erro ao parsear resposta:", text);
    payload = { error: text || "Resposta inválida do servidor" };
  }
  
  console.log("📥 fetchForm resposta:", { status: res.status, payload });
  
  if (!res.ok) {
    let errorMsg = payload.error || payload.message || `Erro ${res.status}: ${res.statusText}`;
    if (payload.details && typeof payload.details === 'object') {
      const parts = Object.values(payload.details).flat().filter(Boolean);
      if (parts.length) errorMsg = Array.isArray(parts[0]) ? parts.flat().join(' ') : parts.join(' ');
    }
    if (typeof errorMsg === 'string' && (errorMsg.length > 500 || errorMsg.trim().startsWith('<'))) {
      errorMsg = res.status >= 500 ? 'Erro no servidor. Tente novamente.' : `Erro ${res.status}`;
    }
    const err = new Error(errorMsg);
    err.responseData = payload;
    err.status = res.status;
    throw err;
  }
  return payload;
}

function setUser(user) {
  localStorage.setItem(storageKey, JSON.stringify(user));
  currentUser = user;
}

function getUser() {
  const raw = localStorage.getItem(storageKey);
  if (!raw) return null;
  try {
    return JSON.parse(raw);
  } catch (err) {
    return null;
  }
}

function clearUser() {
  localStorage.removeItem(storageKey);
  currentUser = null;
  stopInactivityTimer();
}

function updateUserHeader() {
  if (!currentUser) {
    if (dom.userName) dom.userName.textContent = "Visitante";
    if (dom.userRole) dom.userRole.textContent = "Responsavel";
    if (dom.userEmail) dom.userEmail.textContent = "";
    return;
  }
  if (dom.userName) dom.userName.textContent = currentUser.nome || currentUser.email;
  const perfilKey = (currentUser.perfil || "").toString().trim().toUpperCase();
  if (dom.userRole) dom.userRole.textContent = PERFIL_LABELS[perfilKey] || currentUser.perfil || "";
  if (dom.userEmail) dom.userEmail.textContent = currentUser.email || "";
}

function canManageCompras() {
  const perfil = currentUser && currentUser.perfil ? currentUser.perfil.toUpperCase() : "VISUALIZADOR";
  const regras = PERMISSOES[perfil] || PERMISSOES.VISUALIZADOR;
  return Boolean(regras.canManageCompras);
}

// Verifica se o perfil só pode criar lista e adicionar itens (estoquista, cozinha e bar)
function canOnlyCreateAndAddItems() {
  if (!currentUser) return false;
  const perfil = (currentUser.perfil || "").toString().trim().toUpperCase();
  // ADMIN e GERENTE podem fazer tudo, não têm restrições
  if (perfil === "ADMIN" || perfil === "GERENTE") return false;
  return perfil === "ESTOQUISTA" || perfil === "COZINHA" || perfil === "BAR" || perfil === "ATENDENTE";
}

// Verifica se é ADMIN ou GERENTE (permissões totais)
function isAdminOrGerente() {
  if (!currentUser) return false;
  const perfil = (currentUser.perfil || "").toString().trim().toUpperCase();
  return perfil === "ADMIN" || perfil === "GERENTE";
}

// Verifica se é ADMIN (apenas administrador)
function isAdmin() {
  if (!currentUser) return false;
  const perfil = (currentUser.perfil || "").toString().trim().toUpperCase();
  return perfil === "ADMIN";
}

// ============================================
// BACKUP E RESTAURAÇÃO — apenas ADMIN
// ============================================
function abrirBackupModal() {
  const modal = document.getElementById('backupModal');
  if (!modal) return;
  modal.style.display = 'flex';
  carregarListaBackups();

  const btnGerar = document.getElementById('btnGerarBackup');
  if (btnGerar && !btnGerar._listenerAdded) {
    btnGerar._listenerAdded = true;
    btnGerar.addEventListener('click', gerarBackup);
  }
  const btnFechar = document.getElementById('closeBackupModal');
  if (btnFechar && !btnFechar._listenerAdded) {
    btnFechar._listenerAdded = true;
    btnFechar.addEventListener('click', () => { modal.style.display = 'none'; });
  }
  modal.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });
}

async function gerarBackupDireto() {
  const btn = document.getElementById('btnAbrirBackup');
  if (btn) { btn.disabled = true; btn.textContent = 'Gerando...'; }
  try {
    const res = await fetch(API_URL + '/admin/backup', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...(currentUser?.token ? { Authorization: 'Bearer ' + currentUser.token } : {}),
        ...(currentUser?.id != null ? { 'X-Usuario-Id': String(currentUser.id) } : {}),
      },
      body: JSON.stringify({ chave: 'BACKUP-SABORPARAENSE-2026' }),
    });
    const data = await res.json().catch(() => null);
    if (res.ok && data?.sucesso) {
      // Baixa automaticamente o arquivo gerado
      const link = document.createElement('a');
      link.href = `${API_URL}/admin/backup/${encodeURIComponent(data.arquivo)}?chave=BACKUP-SABORPARAENSE-2026`;
      link.download = data.arquivo;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      alert('✅ Backup gerado e download iniciado!\nArquivo: ' + data.arquivo + '\nTamanho: ' + data.tamanho_kb + ' KB');
    } else {
      alert('❌ Erro ao gerar backup (HTTP ' + res.status + '):\n' + (data?.error || data?.message || JSON.stringify(data)));
    }
  } catch (e) {
    alert('❌ Falha na conexão: ' + e.message);
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = '📦 Fazer Backup'; }
  }
}

async function gerarBackup() {
  const btn = document.getElementById('btnGerarBackup');
  if (btn) { btn.disabled = true; btn.textContent = 'Gerando...'; }
  try {
    const res = await fetch(API_URL + '/admin/backup', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...(currentUser?.token ? { Authorization: 'Bearer ' + currentUser.token } : {}),
        ...(currentUser?.id != null ? { 'X-Usuario-Id': String(currentUser.id) } : {}),
      },
      body: JSON.stringify({ chave: 'BACKUP-SABORPARAENSE-2026' }),
    });
    const data = await res.json().catch(() => null);
    if (res.ok && data?.sucesso) {
      alert('✅ Backup gerado com sucesso!\nArquivo: ' + data.arquivo + '\nTamanho: ' + data.tamanho_kb + ' KB');
      carregarListaBackups();
    } else {
      alert('❌ Erro ao gerar backup (HTTP ' + res.status + '):\n' + (data?.error || data?.message || JSON.stringify(data)));
    }
  } catch (e) {
    alert('❌ Falha na conexão: ' + e.message);
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = '📦 Gerar Backup Agora'; }
  }
}

async function carregarListaBackups() {
  const content = document.getElementById('backupListaContent');
  if (!content) return;
  content.innerHTML = '<p style="text-align:center;color:#607d8b;padding:1rem;">Carregando...</p>';
  try {
    const res = await fetch(`${API_URL}/admin/backups?chave=BACKUP-SABORPARAENSE-2026`, {
      headers: {
        ...(currentUser?.token ? { Authorization: 'Bearer ' + currentUser.token } : {}),
        ...(currentUser?.id != null ? { 'X-Usuario-Id': String(currentUser.id) } : {}),
      },
    });
    const lista = await res.json().catch(() => []);
    if (!Array.isArray(lista) || lista.length === 0) {
      content.innerHTML = '<p style="text-align:center;color:#607d8b;padding:1rem;">Nenhum backup encontrado. Clique em "Gerar Backup Agora".</p>';
      return;
    }
    let html = '<div style="display:flex;flex-direction:column;gap:0.6rem;">';
    lista.forEach(b => {
      const data = b.gerado_em ? new Date(b.gerado_em).toLocaleString('pt-BR') : b.arquivo;
      const totais = b.totais ? Object.entries(b.totais).map(([t, n]) => `${t}: ${n}`).join(' · ') : '';
      html += `
        <div style="border:1px solid #e0e0e0;border-radius:8px;padding:0.75rem 1rem;display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
          <div style="flex:1;min-width:0;">
            <div style="font-weight:600;font-size:0.9rem;">📅 ${data}</div>
            <div style="font-size:0.78rem;color:#888;margin-top:0.2rem;">${b.tamanho_kb} KB · ${totais}</div>
          </div>
          <a href="${API_URL}/admin/backup/${encodeURIComponent(b.arquivo)}?chave=BACKUP-SABORPARAENSE-2026" download="${b.arquivo}"
             style="padding:0.4rem 0.8rem;background:#1565c0;color:#fff;border-radius:5px;font-size:0.82rem;text-decoration:none;white-space:nowrap;">
            ⬇ Baixar
          </a>
          <button onclick="restaurarBackup('${escapeHtml(b.arquivo)}')"
            style="padding:0.4rem 0.8rem;background:#e65100;color:#fff;border:none;border-radius:5px;font-size:0.82rem;cursor:pointer;white-space:nowrap;">
            🔄 Restaurar
          </button>
        </div>
      `;
    });
    html += '</div>';
    content.innerHTML = html;
  } catch (e) {
    content.innerHTML = `<p style="text-align:center;color:red;padding:1rem;">Erro ao carregar backups: ${e.message}</p>`;
  }
}

async function restaurarBackup(arquivo) {
  const confirmado = window.confirm(
    `⚠ ATENÇÃO!\n\nVocê vai restaurar o backup:\n${arquivo}\n\n` +
    'TODOS os dados atuais serão substituídos pelos dados deste backup.\n\n' +
    'Esta ação NÃO pode ser desfeita. Tem certeza?'
  );
  if (!confirmado) return;

  try {
    const res = await fetch(API_URL + '/admin/restaurar', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...(currentUser?.token ? { Authorization: 'Bearer ' + currentUser.token } : {}),
        ...(currentUser?.id != null ? { 'X-Usuario-Id': String(currentUser.id) } : {}),
      },
      body: JSON.stringify({ chave: 'BACKUP-SABORPARAENSE-2026', arquivo }),
    });
    const data = await res.json().catch(() => null);
    if (res.ok && data?.sucesso) {
      alert('✅ Backup restaurado com sucesso!\nA página será recarregada.');
      window.location.reload();
    } else {
      alert('❌ Erro ao restaurar: ' + (data?.error || res.status));
    }
  } catch (e) {
    alert('❌ Falha na conexão: ' + e.message);
  }
}

// Botão Zerar Históricos — visível e funcional apenas para ADMIN
async function zerarHistoricos() {
  const btn = document.getElementById('btnZerarHistoricos');

  const perfil = (currentUser?.perfil || '').toString().trim().toUpperCase();
  if (perfil !== 'ADMIN') {
    alert('Apenas administradores podem executar esta ação.');
    return;
  }

  const confirmado = window.confirm(
    '⚠ ATENÇÃO!\n\nEsta ação vai apagar PERMANENTEMENTE:\n' +
    '• Todas as movimentações\n' +
    '• Todo o estoque (stock_lotes)\n' +
    '• Todos os lotes\n' +
    '• Todas as listas de compras\n' +
    '• Logs de etiquetas e usuários\n\n' +
    'Cadastros (produtos, unidades, locais, usuários) serão preservados.\n\n' +
    'Tem certeza absoluta?'
  );
  if (!confirmado) return;

  if (btn) { btn.disabled = true; btn.textContent = 'Zerando...'; }

  try {
    const res = await fetch(API_URL + '/admin/zerar-historicos', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...(currentUser?.token ? { Authorization: 'Bearer ' + currentUser.token } : {}),
        ...(currentUser?.id != null ? { 'X-Usuario-Id': String(currentUser.id) } : {}),
      },
      body: JSON.stringify({ chave: 'ZERAR-SABORPARAENSE-2026' }),
    });

    const data = await res.json().catch(() => null);

    if (res.ok && data && data.sucesso) {
      alert('✅ Históricos zerados com sucesso!\nSistema pronto para os testes.');
    } else {
      const msg = data?.error || data?.message || ('Erro HTTP ' + res.status);
      alert('❌ Erro ao zerar: ' + msg);
    }
  } catch (e) {
    alert('❌ Falha na conexão: ' + (e.message || 'Erro desconhecido'));
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = '⚠ Zerar Históricos'; }
  }
}

// Verifica se pode imprimir etiquetas (ADMIN, GERENTE ou ESTOQUISTA)
function podeImprimirEtiqueta() {
  if (!currentUser) return false;
  const perfil = (currentUser.perfil || "").toString().trim().toUpperCase();
  return perfil === "ADMIN" || perfil === "GERENTE" || perfil === "ESTOQUISTA";
}

// Mostra modal com opções para imprimir ou baixar etiqueta
function mostrarOpcoesEtiqueta(loteId) {
  const modal = document.createElement('div');
  modal.className = 'modal-backdrop';
  modal.style.display = 'flex';
  modal.innerHTML = `
    <div class="modal" style="max-width: 400px;">
      <header>
        <h2>Etiqueta de Lote</h2>
        <button type="button" class="close-btn" data-action="close" aria-label="Fechar">×</button>
      </header>
      <div style="padding: 1.5rem;">
        <p style="margin-bottom: 1.5rem;">Lote salvo com sucesso! Deseja imprimir a etiqueta?</p>
        <div style="margin-bottom: 1rem;">
          <label style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
            <span style="color: #555;">Quantidade de etiquetas</span>
            <input
              type="number"
              min="1"
              max="200"
              value="1"
              data-action="copies-input"
              style="width: 110px; border-radius: 8px; padding: 0.5rem; border: 1px solid #d0d0d0;"
            />
          </label>
        </div>
        <div style="display: flex; gap: 1rem; justify-content: flex-end;">
          <button type="button" class="btn secondary" data-action="cancel">Cancelar</button>
          <button type="button" class="btn secondary" data-action="download" data-lote-id="${loteId}">Baixar PDF</button>
          <button type="button" class="btn primary" data-action="print" data-lote-id="${loteId}">Imprimir</button>
        </div>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
  
  // Event listeners para os botões usando event delegation
  const closeModal = () => {
    console.log("🔒 Fechando modal de etiqueta");
    modal.remove();
  };
  
  // Event listeners diretos nos botões (mais confiável)
  const modalContent = modal.querySelector('.modal');
  
  // Busca os botões após inserir no DOM
  setTimeout(() => {
    const closeBtn = modal.querySelector('[data-action="close"]');
    const cancelBtn = modal.querySelector('[data-action="cancel"]');
    const downloadBtn = modal.querySelector('[data-action="download"]');
    const printBtn = modal.querySelector('[data-action="print"]');
    
    console.log("🔍 Botões encontrados:", {
      close: !!closeBtn,
      cancel: !!cancelBtn,
      download: !!downloadBtn,
      print: !!printBtn
    });
    
    if (closeBtn) {
      closeBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        console.log("🔘 Botão fechar clicado");
        closeModal();
      });
    }
    
    if (cancelBtn) {
      cancelBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        console.log("🔘 Botão cancelar clicado");
        closeModal();
      });
    }
    
    if (downloadBtn) {
      downloadBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const id = downloadBtn.dataset.loteId ? Number(downloadBtn.dataset.loteId) : loteId;
        const copiesInput = modal.querySelector('[data-action="copies-input"]');
        let copies = Number(copiesInput?.value ?? 1);
        if (!Number.isFinite(copies) || copies < 1) copies = 1;
        if (copies > 200) copies = 200;
        console.log("📥 Botão download clicado, loteId:", id);
        closeModal();
        setTimeout(() => {
          baixarEtiquetaLote(id, copies);
        }, 150);
      });
    }
    
    if (printBtn) {
      printBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const id = printBtn.dataset.loteId ? Number(printBtn.dataset.loteId) : loteId;
        const copiesInput = modal.querySelector('[data-action="copies-input"]');
        let copies = Number(copiesInput?.value ?? 1);
        if (!Number.isFinite(copies) || copies < 1) copies = 1;
        if (copies > 200) copies = 200;
        console.log("🖨️ Botão imprimir clicado, loteId:", id);
        closeModal();
        setTimeout(() => {
          imprimirEtiquetaLote(id, copies);
        }, 150);
      });
    }
  }, 10);
  
  // Previne fechamento ao clicar dentro do modal
  if (modalContent) {
    modalContent.addEventListener('click', (e) => {
      e.stopPropagation();
    });
  }
  
  // Fecha ao clicar no backdrop (fora do conteúdo do modal)
  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      console.log("🔒 Clicou no backdrop, fechando modal");
      closeModal();
    }
  });
  
  console.log("✅ Modal de etiqueta criado");
}

// Imprime etiqueta do lote (usando a mesma lógica da lista de compras)
async function imprimirEtiquetaLote(loteId, copies = 1) {
  console.log("🖨️ imprimirEtiquetaLote chamada com loteId:", loteId);
  
  if (!podeImprimirEtiqueta()) {
    showToast("Sem permissão para imprimir etiquetas.", "error");
    return;
  }
  
  if (!loteId) {
    showToast("ID do lote não informado.", "error");
    return;
  }

  let safeCopies = Number(copies);
  if (!Number.isFinite(safeCopies) || safeCopies < 1) safeCopies = 1;
  if (safeCopies > 200) safeCopies = 200;
  
  try {
    // Busca os dados do lote via API
    const lote = await fetchJSON(`/lotes/${loteId}`);
    if (!lote || !lote.id) {
      showToast("Lote não encontrado.", "error");
      return;
    }
    
    console.log("✅ Dados do lote carregados:", lote);
    
    // Prepara dados da etiqueta
    const numeroLote = lote.numero_lote || lote.codigo_lote || 'N/A';
    const produtoNome = lote.produto_nome || 'Produto';
    const dataValidade = lote.data_validade ? formatDate(lote.data_validade).split(' ')[0] : null; // Remove hora, mantém apenas data
    const dataGeracao = (lote.criado_em || lote.created_at) ? formatDate(lote.criado_em || lote.created_at).split(' ')[0] : null;
    
    // Gera URL do QR Code: ao escanear, leva ao dashboard > Registro de Saída com lote pré-preenchido
    const qrUrl = window.location.origin + window.location.pathname + '#dashboard?saida=1&lote=' + loteId;
    
    // Gera QR Code usando API externa (mais simples que gerar no backend)
    // Usando API pública do QR Code: https://api.qrserver.com
    const qrCodeUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(qrUrl)}`;
    
    // Estilo da impressão em A4 com grid (tamanho: 50mm x 30mm)
    const estilo = `
      <style>
        @page {
          size: A4 portrait;
          margin: 0mm;
        }
        body {
          font-family: Arial, sans-serif;
          font-size: 8pt;
          margin: 0;
          padding: 0;
        }
        .page {
          width: 210mm;
          height: 297mm;
          page-break-after: always;
        }
        .page.last-page {
          page-break-after: avoid;
        }
        .pageTable {
          border-collapse: collapse;
          border-spacing: 0;
          table-layout: fixed;
          width: 210mm;
        }
        .pageTable td {
          width: 50mm;
          height: 30mm;
          padding: 0;
          margin: 0;
          vertical-align: top;
          overflow: hidden;
        }
        .etiqueta {
          box-sizing: border-box;
          width: 100%;
          height: 100%;
          padding: 2mm;
          display: flex;
          flex-direction: row;
          align-items: center;
          justify-content: space-between;
        }
        .etiqueta-info {
          flex: 1;
          display: flex;
          flex-direction: column;
          gap: 2px;
        }
        .produto-nome {
          font-size: 7pt;
          color: #666;
          margin-bottom: 2px;
          line-height: 1.2;
        }
        .numero-lote {
          font-size: 12pt;
          font-weight: bold;
          color: #000;
          line-height: 1.3;
        }
        .validade {
          font-size: 8pt;
          color: #333;
          margin-top: 2px;
        }
        .data-geracao {
          font-size: 8pt;
          color: #1976d2;
          margin-top: 1px;
        }
        .qr-code {
          width: 22mm;
          height: 22mm;
          margin-left: 3mm;
          flex-shrink: 0;
          image-rendering: -webkit-optimize-contrast;
        }
      </style>
    `;
    
    const cols = 3;
    const rows = 9;
    const perPage = cols * rows; // 36 etiquetas por A4

    const labelHtml = `
      <div class="etiqueta">
        <div class="etiqueta-info">
          <div class="produto-nome">${escapeHtml(produtoNome)}</div>
          <div class="numero-lote">LOTE: ${escapeHtml(numeroLote)}</div>
          ${dataGeracao ? `<div class="data-geracao">GER: ${escapeHtml(dataGeracao)}</div>` : ''}
          ${dataValidade ? `<div class="validade">VAL: ${escapeHtml(dataValidade)}</div>` : ''}
        </div>
        <img src="${qrCodeUrl}" class="qr-code" alt="QR Code" />
      </div>
    `;

    const pagesHtml = [];
    for (let start = 0; start < safeCopies; start += perPage) {
      const end = Math.min(safeCopies, start + perPage);
      const isLast = end >= safeCopies;
      const maxIndexInPage = end - start;
      const trs = [];
      for (let r = 0; r < rows; r++) {
        const tds = [];
        for (let c = 0; c < cols; c++) {
          const idxInPage = r * cols + c;
          if (idxInPage < maxIndexInPage) {
            tds.push(`<td>${labelHtml}</td>`);
          } else {
            tds.push(`<td></td>`);
          }
        }
        trs.push(`<tr>${tds.join("")}</tr>`);
      }
      const tableHtml = `<table class="pageTable"><tbody>${trs.join("")}</tbody></table>`;
      pagesHtml.push(`<div class="page${isLast ? " last-page" : ""}">${tableHtml}</div>`);
    }

    // HTML das páginas (várias etiquetas em A4)
    const conteudo = `
      <!DOCTYPE html>
      <html lang="pt-BR">
        <head>
          <meta charset="utf-8" />
          <title>Etiqueta Lote ${numeroLote}</title>
          ${estilo}
        </head>
        <body>
          ${pagesHtml.join("")}
        </body>
      </html>
    `;
    
    // Usa a mesma lógica da lista de compras: iframe + doc.write
    const iframe = document.createElement("iframe");
    iframe.style.position = "fixed";
    iframe.style.right = "0";
    iframe.style.bottom = "0";
    iframe.style.width = "0";
    iframe.style.height = "0";
    iframe.style.border = "0";
    iframe.style.visibility = "hidden";
    document.body.appendChild(iframe);
    
    let timeoutId = null;
    
    const cleanup = () => {
      if (timeoutId) {
        clearTimeout(timeoutId);
        timeoutId = null;
      }
      try {
        if (iframe.contentWindow) {
          iframe.contentWindow.onafterprint = null;
        }
      } catch (err) {
        /* noop */
      }
      if (iframe.parentNode) {
        iframe.parentNode.removeChild(iframe);
      }
    };
    
    iframe.onload = () => {
      const win = iframe.contentWindow;
      if (!win) {
        cleanup();
        showToast("Não foi possível preparar a etiqueta.", "error");
        return;
      }
      win.onafterprint = cleanup;
      win.focus();
      try {
        if (typeof win.print === "function") {
          win.print();
          showToast("Etiqueta enviada para impressão!", "success");
        } else {
          cleanup();
          showToast("Seu navegador não suportou a impressão.", "error");
        }
      } catch (err) {
        cleanup();
        showToast("Falha ao acionar a impressão.", "error");
      }
    };
    
    iframe.onerror = () => {
      cleanup();
      showToast("Não foi possível gerar a etiqueta.", "error");
    };
    
    const doc = iframe.contentDocument || iframe.contentWindow?.document;
    if (!doc) {
      cleanup();
      showToast("Não foi possível gerar a etiqueta.", "error");
      return;
    }
    doc.open();
    doc.write(conteudo);
    doc.close();
    
    timeoutId = setTimeout(() => {
      if (!document.body.contains(iframe)) return;
      cleanup();
      showToast("Falha ao imprimir. Verifique as configurações do navegador.", "error");
    }, 60000);
    
  } catch (err) {
    console.error("❌ Erro ao imprimir etiqueta:", err);
    console.error("❌ Stack trace:", err.stack);
    showToast(err.message || "Falha ao imprimir etiqueta.", "error");
  }
}

// Baixa PDF da etiqueta do lote
async function baixarEtiquetaLote(loteId, copies = 1) {
  console.log("📥 baixarEtiquetaLote chamada com loteId:", loteId);
  console.log("📥 currentUser:", currentUser);
  console.log("📥 podeImprimirEtiqueta():", podeImprimirEtiqueta());
  console.log("📥 API_URL:", API_URL);
  
  if (!podeImprimirEtiqueta()) {
    showToast("Sem permissão para baixar etiquetas.", "error");
    return;
  }
  
  if (!loteId) {
    showToast("ID do lote não informado.", "error");
    return;
  }

  let safeCopies = Number(copies);
  if (!Number.isFinite(safeCopies) || safeCopies < 1) safeCopies = 1;
  if (safeCopies > 200) safeCopies = 200;
  
  try {
    // Busca os dados do lote via API
    const lote = await fetchJSON(`/lotes/${loteId}`);
    if (!lote || !lote.id) {
      showToast("Lote não encontrado.", "error");
      return;
    }
    
    console.log("✅ Dados do lote carregados:", lote);
    
    // Prepara dados da etiqueta
    const numeroLote = lote.numero_lote || lote.codigo_lote || 'N/A';
    const produtoNome = lote.produto_nome || 'Produto';
    const dataValidade = lote.data_validade ? formatDate(lote.data_validade).split(' ')[0] : null; // Remove hora, mantém apenas data
    const dataGeracao = (lote.criado_em || lote.created_at) ? formatDate(lote.criado_em || lote.created_at).split(' ')[0] : null;
    
    // Gera URL do QR Code: ao escanear, leva ao dashboard > Registro de Saída com lote pré-preenchido
    const qrUrl = window.location.origin + window.location.pathname + '#dashboard?saida=1&lote=' + loteId;
    
    // Gera QR Code usando API externa (mais simples que gerar no backend)
    // Usando API pública do QR Code: https://api.qrserver.com
    const qrCodeUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(qrUrl)}`;
    
    // Estilo da impressão em A4 com grid (tamanho: 50mm x 30mm)
    const estilo = `
      <style>
        @page {
          size: A4 portrait;
          margin: 0mm;
        }
        body {
          font-family: Arial, sans-serif;
          font-size: 8pt;
          margin: 0;
          padding: 0;
        }
        .page {
          width: 210mm;
          height: 297mm;
          page-break-after: always;
        }
        .page.last-page {
          page-break-after: avoid;
        }
        .pageTable {
          border-collapse: collapse;
          border-spacing: 0;
          table-layout: fixed;
          width: 210mm;
        }
        .pageTable td {
          width: 50mm;
          height: 30mm;
          padding: 0;
          margin: 0;
          vertical-align: top;
          overflow: hidden;
        }
        .etiqueta {
          box-sizing: border-box;
          width: 100%;
          height: 100%;
          padding: 2mm;
          display: flex;
          flex-direction: row;
          align-items: center;
          justify-content: space-between;
        }
        .etiqueta-info {
          flex: 1;
          display: flex;
          flex-direction: column;
          gap: 2px;
        }
        .produto-nome {
          font-size: 7pt;
          color: #666;
          margin-bottom: 2px;
          line-height: 1.2;
        }
        .numero-lote {
          font-size: 12pt;
          font-weight: bold;
          color: #000;
          line-height: 1.3;
        }
        .validade {
          font-size: 8pt;
          color: #333;
          margin-top: 2px;
        }
        .data-geracao {
          font-size: 8pt;
          color: #1976d2;
          margin-top: 1px;
        }
        .qr-code {
          width: 22mm;
          height: 22mm;
          margin-left: 3mm;
          flex-shrink: 0;
          image-rendering: -webkit-optimize-contrast;
        }
      </style>
    `;
    
    const cols = 3;
    const rows = 9;
    const perPage = cols * rows; // 36 etiquetas por A4

    const labelHtml = `
      <div class="etiqueta">
        <div class="etiqueta-info">
          <div class="produto-nome">${escapeHtml(produtoNome)}</div>
          <div class="numero-lote">LOTE: ${escapeHtml(numeroLote)}</div>
          ${dataGeracao ? `<div class="data-geracao">GER: ${escapeHtml(dataGeracao)}</div>` : ''}
          ${dataValidade ? `<div class="validade">VAL: ${escapeHtml(dataValidade)}</div>` : ''}
        </div>
        <img src="${qrCodeUrl}" class="qr-code" alt="QR Code" />
      </div>
    `;

    const pagesHtml = [];
    for (let start = 0; start < safeCopies; start += perPage) {
      const end = Math.min(safeCopies, start + perPage);
      const isLast = end >= safeCopies;
      const maxIndexInPage = end - start;
      const trs = [];
      for (let r = 0; r < rows; r++) {
        const tds = [];
        for (let c = 0; c < cols; c++) {
          const idxInPage = r * cols + c;
          if (idxInPage < maxIndexInPage) {
            tds.push(`<td>${labelHtml}</td>`);
          } else {
            tds.push(`<td></td>`);
          }
        }
        trs.push(`<tr>${tds.join("")}</tr>`);
      }
      const tableHtml = `<table class="pageTable"><tbody>${trs.join("")}</tbody></table>`;
      pagesHtml.push(`<div class="page${isLast ? " last-page" : ""}">${tableHtml}</div>`);
    }

    // HTML das páginas (várias etiquetas em A4)
    const conteudo = `
      <!DOCTYPE html>
      <html lang="pt-BR">
        <head>
          <meta charset="utf-8" />
          <title>Etiqueta Lote ${numeroLote}</title>
          ${estilo}
        </head>
        <body>
          ${pagesHtml.join("")}
        </body>
      </html>
    `;
    
    // Usa a mesma lógica da lista de compras: iframe + doc.write
    // Para download, abre diálogo de impressão onde pode salvar como PDF
    const iframe = document.createElement("iframe");
    iframe.style.position = "fixed";
    iframe.style.right = "0";
    iframe.style.bottom = "0";
    iframe.style.width = "0";
    iframe.style.height = "0";
    iframe.style.border = "0";
    iframe.style.visibility = "hidden";
    document.body.appendChild(iframe);
    
    let timeoutId = null;
    
    const cleanup = () => {
      if (timeoutId) {
        clearTimeout(timeoutId);
        timeoutId = null;
      }
      try {
        if (iframe.contentWindow) {
          iframe.contentWindow.onafterprint = null;
        }
      } catch (err) {
        /* noop */
      }
      if (iframe.parentNode) {
        iframe.parentNode.removeChild(iframe);
      }
    };
    
    iframe.onload = () => {
      const win = iframe.contentWindow;
      if (!win) {
        cleanup();
        showToast("Não foi possível preparar a etiqueta.", "error");
        return;
      }
      win.onafterprint = cleanup;
      win.focus();
      try {
        if (typeof win.print === "function") {
          // Para download, abre diálogo de impressão onde pode salvar como PDF
          win.print();
          showToast("Use 'Salvar como PDF' na impressora para baixar.", "info");
        } else {
          cleanup();
          showToast("Seu navegador não suportou a impressão.", "error");
        }
      } catch (err) {
        cleanup();
        showToast("Falha ao acionar a impressão.", "error");
      }
    };
    
    iframe.onerror = () => {
      cleanup();
      showToast("Não foi possível gerar a etiqueta.", "error");
    };
    
    const doc = iframe.contentDocument || iframe.contentWindow?.document;
    if (!doc) {
      cleanup();
      showToast("Não foi possível gerar a etiqueta.", "error");
      return;
    }
    doc.open();
    doc.write(conteudo);
    doc.close();
    
    timeoutId = setTimeout(() => {
      if (!document.body.contains(iframe)) return;
      cleanup();
      showToast("Falha ao gerar. Verifique as configurações do navegador.", "error");
    }, 60000);
    
  } catch (err) {
    console.error("❌ Erro ao baixar etiqueta:", err);
    console.error("❌ Stack trace:", err.stack);
    showToast(err.message || "Falha ao baixar etiqueta.", "error");
  }
}

// Verifica se pode lançar lista no estoque
function listaPermiteLancarEstoque(lista = state.listaCompraAtual) {
  if (!canManageCompras()) return false;
  if (!lista) return false;
  
  // ADMIN e GERENTE podem lançar qualquer lista
  if (isAdminOrGerente()) {
    return true;
  }
  
  // ESTOQUISTA pode lançar
  const perfil = (currentUser?.perfil || "").toString().trim().toUpperCase();
  if (perfil === "ESTOQUISTA") {
    return true;
  }
  
  // COZINHA e BAR NÃO podem lançar
  if (perfil === "COZINHA" || perfil === "BAR" || perfil === "ATENDENTE") {
    return false;
  }
  
  // Outros perfis: permite se for o dono
  return isListaOwner(lista);
}

function canManageProdutos() {
  const perfil = currentUser && currentUser.perfil ? currentUser.perfil.toUpperCase() : "VISUALIZADOR";
  const regras = PERMISSOES[perfil] || PERMISSOES.VISUALIZADOR;
  return Boolean(regras.canManageProdutos);
}

function canManageUnidades() {
  const perfil = currentUser && currentUser.perfil ? currentUser.perfil.toUpperCase() : "VISUALIZADOR";
  const regras = PERMISSOES[perfil] || PERMISSOES.VISUALIZADOR;
  return Boolean(regras.canManageUnidades);
}

// Verifica se pode gerenciar usuários BAR (BAR pode gerenciar apenas usuários BAR)
function canManageUsuariosBar() {
  if (!currentUser) return false;
  const perfil = (currentUser.perfil || "").toString().trim().toUpperCase();
  return perfil === "BAR";
}

// Verifica se pode gerenciar um usuário específico
function canManageUsuario(usuario) {
  if (!usuario) return false;
  
  // ADMIN pode gerenciar todos
  const perfilAtual = (currentUser?.perfil || "").toString().trim().toUpperCase();
  if (perfilAtual === "ADMIN") return true;
  
  // BAR pode gerenciar apenas usuários BAR
  if (canManageUsuariosBar()) {
    const perfilUsuario = (usuario.perfil || "").toString().trim().toUpperCase();
    return perfilUsuario === "BAR";
  }
  
  // Outros perfis seguem regra padrão
  const regras = PERMISSOES[perfilAtual] || PERMISSOES.VISUALIZADOR;
  return Boolean(regras.canManageUsuarios);
}

function isListaOwner(lista = state.listaCompraAtual) {
  if (!lista || !currentUser) return false;
  if (typeof lista.responsavel_id === "undefined" || lista.responsavel_id === null) return false;
  return Number(lista.responsavel_id) === Number(currentUser.id);
}

function listaPermiteEdicao(lista = state.listaCompraAtual) {
  if (!canManageCompras()) return false;
  if (!lista) return false;
  const status = (lista.status || "").toUpperCase();
  if (status === "FINALIZADA") return false;
  
  // ADMIN e GERENTE podem fazer tudo
  if (isAdminOrGerente()) {
    return true;
  }
  
  // Estoquista e Cozinha só podem adicionar itens, não editar lista
  if (canOnlyCreateAndAddItems()) {
    return false; // Não permite editar lista, apenas adicionar itens
  }
  
  // Outros perfis: permite edição se for o dono
  return isListaOwner(lista);
}

function listaPermiteFinalizar(lista = state.listaCompraAtual) {
  if (!canManageCompras()) return false;
  if (!lista) return false;
  const status = (lista.status || "").toUpperCase();
  if (status === "FINALIZADA") return false;
  
  // ADMIN e GERENTE podem fazer tudo
  if (isAdminOrGerente()) {
    return true;
  }
  
  // Estoquista e Cozinha não podem finalizar lista
  if (canOnlyCreateAndAddItems()) {
    return false;
  }
  
  // Outros perfis: permite finalizar se for o dono
  return isListaOwner(lista);
}

// Verifica se pode adicionar itens à lista (estoquista e cozinha podem)
function listaPermiteAdicionarItens(lista = state.listaCompraAtual) {
  if (!canManageCompras()) return false;
  if (!lista) return false;
  const status = (lista.status || "").toUpperCase();
  if (status === "FINALIZADA") return false;
  
  // Estoquista e Cozinha podem adicionar itens mesmo sem ser dono
  if (canOnlyCreateAndAddItems()) {
    return true;
  }
  
  // ADMIN e GERENTE podem fazer tudo
  if (isAdminOrGerente()) {
    return true;
  }
  
  // Outros perfis: permite adicionar se for o dono
  return isListaOwner(lista);
}

function canCreateLista() {
  if (!canManageCompras() || !currentUser) return false;
  const perfil = (currentUser.perfil || "").toString().trim().toUpperCase();
  
  // ADMIN e GERENTE podem criar listas
  if (isAdminOrGerente()) {
    return true;
  }
  
  // ESTOQUISTA, COZINHA, BAR e ASSISTENTE_ADMINISTRATIVO podem criar listas
  if (perfil === "ESTOQUISTA" || perfil === "COZINHA" || perfil === "BAR" || perfil === "ASSISTENTE_ADMINISTRATIVO") {
    return true;
  }
  
  // Outros perfis: apenas se for dono da lista atual
  if (isListaOwner(state.listaCompraAtual)) {
    return true;
  }
  
  const listasReferencia =
    state.listasComprasAtivasSnapshot && state.listasComprasAtivasSnapshot.length
      ? state.listasComprasAtivasSnapshot
      : state.listasCompras;
  if (!listasReferencia?.length) return true;
  return listasReferencia.some((lista) => Number(lista.responsavel_id) === Number(currentUser.id));
}

function updateNovaListaButton() {
  if (!dom.openListaCompraBtn) return;
  const allowed = canCreateLista();
  dom.openListaCompraBtn.classList.toggle("hidden", !allowed);
  dom.openListaCompraBtn.disabled = !allowed;
}

function updateComprasDashboardCard() {
  if (dom.kpiComprasAtivas) {
    dom.kpiComprasAtivas.textContent = state.listasComprasAtivasSnapshot.length || 0;
  }
}

function updateMinimoDashboardCard() {
  const quantidade = Array.isArray(state.produtosAbaixoMinimo) ? state.produtosAbaixoMinimo.length : 0;
  const lista = Array.isArray(state.produtosAbaixoMinimo) ? state.produtosAbaixoMinimo : [];
  if (dom.kpiMinimo) dom.kpiMinimo.textContent = quantidade;
  const hintMinimo = document.getElementById("cardMinimoHint");
  const selectMinimo = document.getElementById("cardMinimoSelect");
  if (hintMinimo) {
    hintMinimo.style.display = quantidade > 0 ? "none" : "";
    hintMinimo.textContent = quantidade > 0 ? "" : "Tudo em dia";
  }
  if (selectMinimo) {
    selectMinimo.style.display = quantidade > 0 ? "block" : "none";
    selectMinimo.innerHTML = '<option value="">Selecione um produto</option>' +
      lista.map((p) => `<option value="${p.id}">${escapeHtml(p.nome || `Produto ${p.id}`)}</option>`).join("");
  }
  if (dom.cardMinimo) dom.cardMinimo.classList.toggle("card--alert", quantidade > 0);
  if (Array.isArray(state.produtos) && state.produtos.length) {
    renderProdutos(state.produtos);
  }
}

function updatePerdasDashboardCard() {
  const resumo = state.perdasResumo || {};
  const totalQtd = Number(resumo.quantidade_total || 0);
  const totalRegistros = Number(resumo.total_registros || 0);
  // Mostra apenas número inteiro (sem casas decimais)
  if (dom.kpiPerdas) dom.kpiPerdas.textContent = Math.round(totalQtd);
  if (dom.cardPerdasHint) {
    dom.cardPerdasHint.textContent = totalRegistros ? `${totalRegistros} movimentacoes recentes` : "Sem perdas registradas";
  }
  if (dom.cardPerdas) dom.cardPerdas.classList.toggle("card--alert", totalQtd > 0);
}

function updateUnidadeInlineUI(canManage) {
  const card = dom.unidadeInlineFormCard;
  const form = dom.unidadeInlineForm;
  const toggleBtn = dom.openUnidadeBtn;
  if (!canManage && state.unidadeInlineVisivel) state.unidadeInlineVisivel = false;
  const shouldShow = Boolean(canManage && state.unidadeInlineVisivel);

  if (card) card.classList.toggle("hidden", !shouldShow);
  if (toggleBtn) {
    toggleBtn.classList.toggle("hidden", !canManage);
    toggleBtn.disabled = !canManage;
    toggleBtn.textContent = shouldShow ? "Cancelar cadastro" : "+ Nova Unidade";
  }
  if (form) {
    Array.from(form.elements).forEach((element) => {
      if (element.type !== "hidden") element.disabled = !canManage;
    });
  }
}

// Controla quais secoes e botoes ficam habilitados de acordo com o perfil logado.
// Se o usuário tem permissoes_menu personalizadas (array não vazio), usa-as. Caso contrário, usa o padrão do perfil.
function applyPermissions() {
  const perfil = currentUser && currentUser.perfil ? currentUser.perfil.toUpperCase() : "VISUALIZADOR";
  const regrasBase = PERMISSOES[perfil] || PERMISSOES.VISUALIZADOR;
  const permPersonalizadas = currentUser && Array.isArray(currentUser.permissoes_menu) && currentUser.permissoes_menu.length > 0;
  let sections = permPersonalizadas ? [...currentUser.permissoes_menu] : (regrasBase.sections || []);
  // ADMIN e GERENTE sempre têm acesso a Logs (mesmo com permissões personalizadas)
  if (["ADMIN", "GERENTE"].includes(perfil) && !sections.includes("logs")) {
    sections = [...sections, "logs"];
  }
  /**
   * Ficha técnica: permissoes_menu salvo no servidor é uma lista fixa e não ganha
   * seções novas automaticamente — sem isto o item some para quase todos os usuários reais.
   */
  if (currentUser && !sections.includes("fechaTecnica")) {
    sections = [...sections, "fechaTecnica"];
  }
  // Boas-vindas e Minha conta: sempre acessíveis (permissoes_menu antigas podem omitir)
  if (currentUser) {
    const sempre = ["boasVindas", "minhaConta"];
    const faltando = sempre.filter((s) => !sections.includes(s));
    if (faltando.length) sections = [...faltando, ...sections];
  }
  // Reserva: itens Mesa e Histórico no menu; permissão antiga só com reservaMesa inclui ambos.
  if (sections.includes("reservaMesa") && !sections.includes("historicoReservas")) {
    sections = [...sections, "historicoReservas"];
  }
  if (sections.includes("historicoReservas") && !sections.includes("reservaMesa")) {
    sections = [...sections, "reservaMesa"];
  }
  // Auditoria fechamento caixa (id fechamento): permissoes_menu antigas sem o módulo novo mantêm acesso junto a Boleto/Alvará/Proventos
  if (
    (sections.includes("boletao") || sections.includes("alvara") || sections.includes("proventos")) &&
    !sections.includes("fechamento")
  ) {
    sections = [...sections, "fechamento"];
  }
  const regras = { ...regrasBase, sections };
  updateUserHeader();

  dom.navLinks.forEach((link) => {
    const allowed = regras.sections.includes(link.dataset.section);
    link.classList.toggle("hidden", !allowed);
  });

  // Oculta o menu pai "RH" quando nenhum filho está permitido
  const rhNavSubmenu = document.getElementById("rhMenu")?.closest(".nav-submenu");
  if (rhNavSubmenu) {
    const temAcessoRH = regras.sections.includes("funcionarios");
    rhNavSubmenu.classList.toggle("hidden", !temAcessoRH);
  }
  // Oculta o menu pai "Financeiro" quando nenhum filho está permitido
  const financeiroNavSubmenu = document.getElementById("financeiroMenu")?.closest(".nav-submenu");
  if (financeiroNavSubmenu) {
    const temAcessoFinanceiro =
      regras.sections.includes("boletao") ||
      regras.sections.includes("alvara") ||
      regras.sections.includes("proventos") ||
      regras.sections.includes("reciboAjuda") ||
      regras.sections.includes("fechamento");
    financeiroNavSubmenu.classList.toggle("hidden", !temAcessoFinanceiro);
  }
  // Oculta o menu pai "Configuracoes" quando nenhum filho está permitido (ex.: Backup de Fornecedores no perfil padrão = só ADMIN)
  const configuracoesNavSubmenu = document.getElementById("configuracoesMenu")?.closest(".nav-submenu");
  if (configuracoesNavSubmenu) {
    const temAcessoConfig = regras.sections.includes("fornecedoresBackup");
    configuracoesNavSubmenu.classList.toggle("hidden", !temAcessoConfig);
  }
  const reservaNavSubmenu = document.getElementById("reservaMenu")?.closest(".nav-submenu");
  if (reservaNavSubmenu) {
    const temAcessoReserva =
      regras.sections.includes("reservaMesa") || regras.sections.includes("historicoReservas");
    reservaNavSubmenu.classList.toggle("hidden", !temAcessoReserva);
  }

  dom.sections.forEach((section) => {
    const key = section.id.replace("Section", "");
    const allowed = regras.sections.includes(key);
    if (!allowed) section.classList.add("hidden");
  });

  if (dom.openProdutoBtn) dom.openProdutoBtn.classList.toggle("hidden", !regras.canManageProdutos);
  // BAR pode criar usuários BAR, mesmo sem canManageUsuarios
  const podeGerenciarUsuarios = regras.canManageUsuarios || canManageUsuariosBar();
  if (dom.openUsuarioBtn) dom.openUsuarioBtn.classList.toggle("hidden", !podeGerenciarUsuarios);
  updateUnidadeInlineUI(regras.canManageUnidades);

  // Oculta coluna Acoes e botão Novo nas tabelas de Unidades e Locais para quem não é ADMIN
  const unidadesAcoesHeader = document.getElementById("unidadesAcoesHeader");
  const locaisAcoesHeader = document.getElementById("locaisAcoesHeader");
  const mostrarAcoes = perfil === "ADMIN";
  if (unidadesAcoesHeader) unidadesAcoesHeader.style.display = mostrarAcoes ? "" : "none";
  if (locaisAcoesHeader) locaisAcoesHeader.style.display = mostrarAcoes ? "" : "none";
  if (dom.openLocalModalBtn) dom.openLocalModalBtn.classList.toggle("hidden", !mostrarAcoes);

  // Oculta coluna Acoes da tabela de Usuários para GERENTE (só ADMIN pode editar/excluir)
  const usuariosAcoesHeader = document.getElementById("usuariosAcoesHeader");
  const mostrarAcoesUsuarios = perfil === "ADMIN";
  if (usuariosAcoesHeader) usuariosAcoesHeader.style.display = mostrarAcoesUsuarios ? "" : "none";

  // Oculta coluna Acoes das tabelas de Movimentações para quem não é ADMIN (excluir entrada/saída)
  const movTableAcoesHeader = document.getElementById("movTableAcoesHeader");
  const movimentacoesAcoesHeader = document.getElementById("movimentacoesAcoesHeader");
  const relatorioDetalhesAcoesHeader = document.getElementById("relatorioDetalhesAcoesHeader");
  if (movTableAcoesHeader) movTableAcoesHeader.style.display = mostrarAcoes ? "" : "none";
  if (movimentacoesAcoesHeader) movimentacoesAcoesHeader.style.display = mostrarAcoes ? "" : "none";
  if (relatorioDetalhesAcoesHeader) relatorioDetalhesAcoesHeader.style.display = mostrarAcoes ? "" : "none";

  // Botão "Novo lote" oculto – lotes são criados automaticamente em entradas de estoque
  if (dom.openNovoLoteBtn) dom.openNovoLoteBtn.classList.add("hidden");
  // COZINHA e BAR não podem registrar entrada - oculta o botão
  if (dom.openEntradaBtn) {
    const perfilAtual = (currentUser?.perfil || "").toString().trim().toUpperCase();
    const isCozinhaOuBar = perfilAtual === "COZINHA" || perfilAtual === "BAR" || perfilAtual === "ATENDENTE";
    const podeRegistrarEntrada = regras.canRegistrarMovimentacoes && !isCozinhaOuBar;
    dom.openEntradaBtn.classList.toggle("hidden", !podeRegistrarEntrada);
  }
  const openProventoBtn = document.getElementById("openProvento");
  if (openProventoBtn) {
    const podeCriarProvento = ["ADMIN","GERENTE","FINANCEIRO","ASSISTENTE_ADMINISTRATIVO"].includes(perfil);
    openProventoBtn.classList.toggle("hidden", !podeCriarProvento);
  }
  // COZINHA e BAR podem registrar saída - habilita o botão
  if (dom.openSaidaBtn) {
    const perfilAtual = (currentUser?.perfil || "").toString().trim().toUpperCase();
    const isCozinhaOuBar = perfilAtual === "COZINHA" || perfilAtual === "BAR" || perfilAtual === "ATENDENTE";
    // Garante que BAR e COZINHA sempre possam usar o botão (têm canRegistrarMovimentacoes: true)
    const podeRegistrarSaida = regras.canRegistrarMovimentacoes || isCozinhaOuBar;
    dom.openSaidaBtn.disabled = !podeRegistrarSaida;
  }
  updateNovaListaButton();
  // Sugestões de Compras: apenas ADMIN e GERENTE
  if (dom.openSugestoesComprasBtn) {
    const podeVerSugestoes = perfil === "ADMIN" || perfil === "GERENTE";
    dom.openSugestoesComprasBtn.classList.toggle("hidden", !podeVerSugestoes);
  }
  if (dom.listaCompraAdicionarItem) dom.listaCompraAdicionarItem.classList.toggle("hidden", !regras.canManageCompras);
  // Estoquista e Cozinha não podem gerenciar estabelecimentos
  if (dom.listaCompraAdicionarEstabelecimento) {
    const podeGerenciarEstabelecimentos = regras.canManageCompras && !canOnlyCreateAndAddItems();
    dom.listaCompraAdicionarEstabelecimento.classList.toggle("hidden", !podeGerenciarEstabelecimentos);
  }
  // Estoquista e Cozinha não podem finalizar lista
  if (dom.listaCompraFinalizar) {
    const podeFinalizar = regras.canManageCompras && !canOnlyCreateAndAddItems();
    dom.listaCompraFinalizar.classList.toggle("hidden", !podeFinalizar);
  }
  // Estoquista e Cozinha não podem alterar status
  const podeAlterarStatus = regras.canManageCompras && !canOnlyCreateAndAddItems();
  if (dom.listaCompraStatusRascunho) dom.listaCompraStatusRascunho.classList.toggle("hidden", !podeAlterarStatus);
  if (dom.listaCompraStatusEmCompras) dom.listaCompraStatusEmCompras.classList.toggle("hidden", !podeAlterarStatus);
  if (dom.listaCompraStatusPausada) dom.listaCompraStatusPausada.classList.toggle("hidden", !podeAlterarStatus);
  if (dom.listaCompraObservacoes) {
    const podeEditarObs = regras.canManageCompras && !canOnlyCreateAndAddItems();
    dom.listaCompraObservacoes.disabled = !podeEditarObs;
  }
  
  // COZINHA e BAR não podem lançar no estoque - oculta o botão
  if (dom.listaCompraLancarEstoque) {
    const perfilAtual = (currentUser?.perfil || "").toString().trim().toUpperCase();
    const isCozinhaOuBar = perfilAtual === "COZINHA" || perfilAtual === "BAR" || perfilAtual === "ATENDENTE";
    const podeLancar = regras.canManageCompras && !isCozinhaOuBar;
    dom.listaCompraLancarEstoque.classList.toggle("hidden", !podeLancar);
  }
  
  // COZINHA e BAR não podem ver valores em dinheiro na tela de estoque
  const perfilAtual = (currentUser?.perfil || "").toString().trim().toUpperCase();
  const isCozinhaOuBar = perfilAtual === "COZINHA" || perfilAtual === "BAR" || perfilAtual === "ATENDENTE";
  
  // Ocultar cards de valores
  const estoqueCardUnitario = document.getElementById("estoqueCardUnitario");
  const estoqueCardTotal = document.getElementById("estoqueCardTotal");
  const estoqueResumoCard = document.querySelector(".estoque-resumo-card");
  if (estoqueCardUnitario) estoqueCardUnitario.classList.toggle("hidden", isCozinhaOuBar);
  if (estoqueCardTotal) estoqueCardTotal.classList.toggle("hidden", isCozinhaOuBar);
  if (estoqueResumoCard) estoqueResumoCard.classList.toggle("hidden", isCozinhaOuBar);
  
  // Ocultar colunas da tabela
  const estoqueColValorUnitario = document.querySelectorAll(".estoque-col-valor-unitario");
  const estoqueColValorTotal = document.querySelectorAll(".estoque-col-valor-total");
  estoqueColValorUnitario.forEach(col => col.classList.toggle("hidden", isCozinhaOuBar));
  estoqueColValorTotal.forEach(col => col.classList.toggle("hidden", isCozinhaOuBar));

  return regras;
}

function navigateTo(section) {
  // Salva a seção atual no localStorage para restaurar após refresh
  if (section) {
    try {
      localStorage.setItem(currentSectionKey, section);
    } catch (err) {
      console.warn('Erro ao salvar seção atual:', err);
    }
  }
  
  // Usa alternância de seções (conteúdo no index.html) - evita fetch que pode falhar em produção
  dom.navLinks.forEach((link) => link.classList.toggle("active", link.dataset.section === section));
  dom.sections.forEach((sec) => sec.classList.toggle("hidden", sec.id !== `${section}Section`));
  const mainScroll = document.querySelector(".main-content");
  if (mainScroll) mainScroll.scrollTop = 0;
  const reservaNavSubmenu = document.getElementById("reservaMenu")?.closest(".nav-submenu");
  if (reservaNavSubmenu) {
    if (section === "reservaMesa" || section === "historicoReservas") {
      reservaNavSubmenu.classList.add("open");
    } else {
      reservaNavSubmenu.classList.remove("open");
    }
  }
  const financeiroNavSubmenuNav = document.getElementById("financeiroMenu")?.closest(".nav-submenu");
  if (financeiroNavSubmenuNav) {
    if (section === "boletao" || section === "alvara" || section === "proventos" || section === "reciboAjuda" || section === "fechamento") {
      financeiroNavSubmenuNav.classList.add("open");
    } else {
      financeiroNavSubmenuNav.classList.remove("open");
    }
  }
  if (section === 'boasVindas') {
    const el = document.getElementById('boasVindasNome');
    if (el && currentUser && currentUser.nome) el.textContent = currentUser.nome;
  }
  if (section === 'fornecedores') loadFornecedores();
  else if (section === 'fornecedoresBackup') loadFornecedoresBackup();
  else if (section === 'logs') loadLogs();
}

// Renderizadores auxiliares usados por várias tabelas e painéis.
function renderTable(target, rowsHtml, emptyMessage, cols) {
  if (!target) return;
  if (!rowsHtml) {
    target.innerHTML = `<tr><td colspan="${cols}" style="text-align:center; color:#607d8b">${emptyMessage}</td></tr>`;
  } else {
    target.innerHTML = rowsHtml;
  }
}
function renderMovimentacoes(lista, target, emptyMessage) {
  console.log("renderMovimentacoes chamado:", {
    listaLength: Array.isArray(lista) ? lista.length : 0,
    target: target ? "encontrado" : "não encontrado",
    emptyMessage
  });
  
  if (!target) {
    console.error("Target não fornecido para renderMovimentacoes");
    return;
  }
  
  if (!Array.isArray(lista) || lista.length === 0) {
    console.log("Lista vazia, renderizando mensagem vazia");
    renderTable(target, "", emptyMessage, 8);
    return;
  }

  const user = getUser();
  const isAdmin = user && (user.perfil || "").toString().toUpperCase() === "ADMIN";
  
  const dadosOrdenados = sortMovimentacoes(lista);
  console.log("Dados ordenados para renderização:", dadosOrdenados.length);
  
  const rows = dadosOrdenados.map((item) => {
    const quantidadeValor = Number(item.qtd ?? item.quantidade ?? 0);
    const unidadeLabel = (() => {
      const unidadeBruta = (item.unidade || "").trim().toUpperCase();
      if (!unidadeBruta) return "UND";
      if (["UN", "UND", "UNID", "UNIDADE", "UNIDADES"].includes(unidadeBruta)) return "UND";
      return unidadeBruta;
    })();
    const quantidade = `${formatNumber(quantidadeValor, 3)} ${escapeHtml(unidadeLabel)}`.trim();
    // ✅ Formata motivo: se for "COMPRA" e tiver "Lista de compras" na observação, mostra "Lista de compras"
    let motivo = item.motivo || item.observacao || "--";
    if ((motivo === "COMPRA" || motivo.toUpperCase() === "COMPRA") && 
        (item.observacao && item.observacao.includes("Lista de compras"))) {
      motivo = "Lista de compras";
    }
    motivo = escapeHtml(motivo);
    const tipo = (item.tipo || "").trim().toUpperCase();
    
    // Formata unidade para transferências - forma simples
    let unidadeDisplay = item.unidade_nome || "N/A";
    
    // Se for transferência, mostra origem → destino
    if (tipo === "TRANSFERENCIA" && item.para_unidade_id) {
      const origemNome = item.unidade_origem_nome || item.unidade_nome || "N/A";
      const destinoNome = item.unidade_destino_nome || "N/A";
      if (destinoNome !== "N/A") {
        unidadeDisplay = `${origemNome} → ${destinoNome}`;
      }
    }
    // Se for reversão com destino, mostra origem → destino (ex: reversão de transferência)
    else if (tipo === "REVERSAO" && item.para_unidade_id) {
      const origemNome = item.unidade_origem_nome || item.unidade_nome || "N/A";
      const destinoNome = item.unidade_destino_nome || "N/A";
      if (destinoNome !== "N/A") {
        unidadeDisplay = `↩ ${origemNome} → ${destinoNome}`;
      }
    }
    // Se for entrada de transferência, mostra unidade que recebeu
    else if (tipo === "ENTRADA" && item.motivo === "TRANSFERENCIA" && item.para_unidade_id) {
      const destinoNome = item.unidade_destino_nome || item.unidade_nome || "N/A";
      unidadeDisplay = destinoNome;
    }
    
    const isTransferencia = tipo === "TRANSFERENCIA";
    const isReversao = tipo === "REVERSAO";
    const btnAcao = isTransferencia
      ? { title: "Reverter transferência (retorna produto ao local de origem)", icon: "↩️" }
      : { title: "Excluir (reverte estoque)", icon: "🗑️" };
    const acoesCell = isAdmin && !isReversao
      ? `<td data-label="Acoes"><button type="button" class="btn-icon btn-icon--danger btn-excluir-movimentacao" title="${btnAcao.title}" data-id="${item.id}" data-tipo="${tipo}">${btnAcao.icon}</button></td>`
      : `<td data-label="Acoes"></td>`;
    return `<tr data-id="${item.id ?? ""}">
      <td data-label="Data">${formatDate(item.data_mov)}</td>
      <td data-label="Tipo">${buildStatusPill(tipo || "--")}</td>
      <td data-label="Produto">${escapeHtml(item.produto_nome || "--")}</td>
      <td data-label="Unidade">${escapeHtml(unidadeDisplay)}</td>
      <td data-label="Qtd">${quantidade}</td>
      <td data-label="Motivo">${motivo}</td>
      <td data-label="Responsavel">${escapeHtml(item.responsavel_nome || "--")}</td>
      ${acoesCell}
    </tr>`;
  }).join("");

  console.log("Renderizando", rows.split("</tr>").length - 1, "linhas na tabela");
  renderTable(target, rows, emptyMessage, 8);
  console.log("Tabela renderizada com sucesso");
}

function renderMovimentacoesDashboard(lista) {
  console.log("renderMovimentacoesDashboard chamado com:", lista?.length || 0, "itens");
  
  // Tenta encontrar o elemento novamente se não estiver disponível
  if (!dom.movTable) {
    dom.movTable = document.getElementById("movTable");
    console.log("movTable buscado novamente:", dom.movTable ? "encontrado" : "não encontrado");
  }
  
  if (!dom.movTable) {
    console.warn("dom.movTable não encontrado - tentando novamente em 200ms...");
    // Aguarda um pouco mais e tenta novamente
    setTimeout(() => {
      dom.movTable = document.getElementById("movTable");
      if (dom.movTable) {
        console.log("movTable encontrado no retry, renderizando...");
        renderMovimentacoesDashboard(lista);
      } else {
        console.error("movTable ainda não encontrado após retry");
      }
    }, 200);
    return;
  }
  
  // Usar a lista passada ou fallback para movimentacoesRecentes
  const listaParaUsar = Array.isArray(lista) && lista.length > 0 ? lista : (Array.isArray(state.movimentacoesRecentes) ? state.movimentacoesRecentes : []);
  console.log("Lista para usar:", listaParaUsar.length, "itens");
  
  if (listaParaUsar.length === 0) {
    console.warn("Nenhuma movimentação disponível para renderizar");
    renderTable(dom.movTable, "", "Sem movimentacoes recentes.", 7);
    return;
  }
  
  // ✅ Remove duplicatas baseado no ID da movimentação
  const idsVistos = new Set();
  const listaSemDuplicatas = listaParaUsar.filter((mov) => {
    const id = mov.id || mov.movimentacao_id;
    if (!id) return true; // Mantém se não tiver ID
    if (idsVistos.has(id)) {
      console.warn("Movimentação duplicada removida:", id);
      return false;
    }
    idsVistos.add(id);
    return true;
  });
  
  console.log("Lista sem duplicatas:", listaSemDuplicatas.length, "itens (removidos", listaParaUsar.length - listaSemDuplicatas.length, "duplicatas)");
  
  const dados = sortMovimentacoes(listaSemDuplicatas);
  console.log("Dados ordenados:", dados.length, "itens");
  
  // Debug: verificar quantas movimentações temos
  if (dados.length > 0) {
    const tipos = dados.reduce((acc, m) => {
      const tipo = (m.tipo || "DESCONHECIDO").toUpperCase();
      acc[tipo] = (acc[tipo] || 0) + 1;
      return acc;
    }, {});
    console.log("Renderizando movimentacoes no dashboard:", dados.length, "total. Tipos:", tipos);
  }
  
  // Renderiza as movimentações
  const dadosParaRenderizar = dados.slice(0, 10);
  console.log("Renderizando", dadosParaRenderizar.length, "movimentações na tabela");
  
  // Verifica novamente se o elemento existe antes de renderizar
  if (!dom.movTable) {
    console.error("movTable não encontrado antes de renderizar!");
    return;
  }
  
  try {
    renderMovimentacoes(dadosParaRenderizar, dom.movTable, "Sem movimentacoes recentes.");
    console.log("Movimentações renderizadas com sucesso!");
  } catch (error) {
    console.error("Erro ao renderizar movimentações:", error);
  }
}

function renderLotesDashboard(lista) {
  // Tenta encontrar o elemento novamente se não estiver disponível
  if (!dom.lotesTable) {
    dom.lotesTable = document.getElementById("lotesTable");
  }
  
  if (!dom.lotesTable) {
    console.warn("dom.lotesTable não encontrado");
    return;
  }
  
  const rows = (lista || []).slice(0, 10).map((lote) => {
    const quantidade = `${formatNumber(lote.qtd_atual ?? lote.quantidade ?? 0, 3)} ${escapeHtml(normalizarUnidadeBase(lote.unidade))}`;
    return `<tr>
      <td data-label="Produto">${escapeHtml(lote.produto_nome || "--")}</td>
      <td data-label="Lote">${escapeHtml(lote.numero_lote || lote.codigo_lote || "--")}</td>
      <td data-label="Validade">${formatDate(lote.data_validade)}</td>
      <td data-label="Qtd">${quantidade}</td>
      <td data-label="Unidade">${escapeHtml(lote.unidade_nome || "--")}</td>
      <td data-label="Status">${buildStatusPill(lote.status || "--")}</td>
      <td data-label="Responsavel">${escapeHtml(lote.responsavel_nome || "--")}</td>
    </tr>`;
  }).join("");
  renderTable(dom.lotesTable, rows, "Nenhum lote a vencer", 7);
}

// Renderiza gráfico de lotes por status
function renderLotesStatusChart(stats) {
  if (!dom.loteStatusChart) {
    console.warn('loteStatusChart não encontrado');
    return;
  }

  const statsData = stats || {};
  const statusLabels = {
    'ATIVO': 'Ativo',
    'BLOQUEADO': 'Bloqueado',
    'VENCIDO': 'Vencido',
    'ESGOTADO': 'Esgotado',
    'A_VENCER': 'A Vencer'
  };

  const statusColors = {
    'ATIVO': '#4caf50',
    'BLOQUEADO': '#ff9800',
    'VENCIDO': '#f44336',
    'ESGOTADO': '#9e9e9e',
    'A_VENCER': '#ffc107'
  };

  // Coleta dados de status
  const statusData = [];
  let total = 0;
  
  Object.keys(statusLabels).forEach(status => {
    const count = Number(statsData[status.toLowerCase()] || statsData[status] || 0);
    if (count > 0) {
      statusData.push({
        label: statusLabels[status],
        count: count,
        color: statusColors[status] || '#607d8b'
      });
      total += count;
    }
  });

  if (statusData.length === 0) {
    dom.loteStatusChart.innerHTML = '<p style="text-align: center; color: #999; padding: 2rem;">Nenhum dado disponível</p>';
    return;
  }

  // Cria HTML do gráfico (barra horizontal simples)
  const maxCount = Math.max(...statusData.map(s => s.count));
  const chartHTML = statusData.map(item => {
    const percentage = total > 0 ? (item.count / total * 100).toFixed(1) : 0;
    const barWidth = maxCount > 0 ? (item.count / maxCount * 100) : 0;
    
    return `
      <div style="margin-bottom: 1rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
          <span style="font-weight: 500; color: #333;">${escapeHtml(item.label)}</span>
          <span style="color: #666; font-size: 0.9rem;">
            <strong>${item.count}</strong> <span style="color: #999;">(${percentage}%)</span>
          </span>
        </div>
        <div style="background: #f5f5f5; height: 24px; border-radius: 4px; overflow: hidden;">
          <div style="background: ${item.color}; height: 100%; width: ${barWidth}%; transition: width 0.3s ease; display: flex; align-items: center; padding: 0 8px;">
            <span style="color: white; font-size: 0.75rem; font-weight: 500; white-space: nowrap;">${item.count}</span>
          </div>
        </div>
      </div>
    `;
  }).join('');

  dom.loteStatusChart.innerHTML = `
    <div style="padding: 1rem;">
      ${chartHTML}
      <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #eee; text-align: center; color: #666; font-size: 0.9rem;">
        <strong>Total:</strong> ${total} lote(s)
      </div>
    </div>
  `;
}

function renderLotesGerenciamento(lista) {
  console.log("🔍 renderLotesGerenciamento chamado com", lista?.length || 0, "lotes");
  
  let primeiroLoteLogado = false;
  const rows = (lista || []).map((lote) => {
    const ativo = Number(lote.ativo) === 1;
    const quantidade = `${formatNumber(lote.quantidade ?? lote.qtd_atual ?? 0, 3)} ${escapeHtml(normalizarUnidadeBase(lote.unidade))}`.trim();
    
    // Formata data de validade - trata diferentes formatos
    let validadeFormatada = "--";
    if (lote.data_validade) {
      const dataStr = String(lote.data_validade).trim();
      if (dataStr && dataStr !== "null" && dataStr !== "undefined" && dataStr !== "") {
        // Tenta formatar usando formatDate primeiro
        const dataFormatada = formatDate(lote.data_validade);
        if (dataFormatada && dataFormatada !== "--") {
          validadeFormatada = dataFormatada;
        } else {
          // Se formatDate não funcionou, tenta formatar manualmente
          // Tenta parsear como YYYY-MM-DD
          if (/^\d{4}-\d{2}-\d{2}/.test(dataStr)) {
            const partes = dataStr.split(' ')[0].split('-');
            const [year, month, day] = partes;
            validadeFormatada = `${day}/${month}/${year}`;
          } else {
            // Tenta parsear como Date
            try {
              const dateObj = new Date(lote.data_validade);
              if (!isNaN(dateObj.getTime())) {
                const dia = String(dateObj.getDate()).padStart(2, "0");
                const mes = String(dateObj.getMonth() + 1).padStart(2, "0");
                const ano = String(dateObj.getFullYear());
                validadeFormatada = `${dia}/${mes}/${ano}`;
              } else {
                validadeFormatada = dataStr;
              }
            } catch (e) {
              validadeFormatada = dataStr;
            }
          }
        }
      }
    }
    
    // Calcula dias para vencer - usa o valor do backend se disponível, senão calcula
    let dias = "--";
    let diffDays = null;
    
    // Tenta usar o valor calculado pelo backend primeiro
    if (lote.dias_para_vencer !== null && lote.dias_para_vencer !== undefined && lote.dias_para_vencer !== "") {
      const diasBackend = Number(lote.dias_para_vencer);
      if (!isNaN(diasBackend) && isFinite(diasBackend)) {
        diffDays = diasBackend;
      }
    }
    
    // Se não veio do backend ou não é válido, calcula no frontend
    if (diffDays === null && lote.data_validade) {
      try {
        // Tenta parsear a data
        let dataValidade = null;
        const dataStr = String(lote.data_validade).trim();
        
        // Se já está no formato YYYY-MM-DD, usa diretamente
        if (/^\d{4}-\d{2}-\d{2}/.test(dataStr)) {
          const partes = dataStr.split(' ')[0].split('-');
          dataValidade = new Date(parseInt(partes[0]), parseInt(partes[1]) - 1, parseInt(partes[2]));
        } else {
          dataValidade = new Date(lote.data_validade);
        }
        
        if (dataValidade && !isNaN(dataValidade.getTime())) {
          const hoje = new Date();
          hoje.setHours(0, 0, 0, 0);
          dataValidade.setHours(0, 0, 0, 0);
          const diffTime = dataValidade - hoje;
          diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        }
      } catch (e) {
        console.warn("Erro ao calcular dias para vencer:", e, lote);
      }
    }
    
    // Formata a exibição dos dias - SEMPRE exibe algo se tiver valor
    if (diffDays !== null && Number.isFinite(diffDays) && !isNaN(diffDays)) {
      if (diffDays < 0) {
        dias = `<span style="color: #f44336; font-weight: bold;">Vencido há ${Math.abs(diffDays)} dia(s)</span>`;
      } else if (diffDays === 0) {
        dias = `<span style="color: #ff9800; font-weight: bold;">Vence hoje</span>`;
      } else if (diffDays <= 7) {
        dias = `<span style="color: #ff9800; font-weight: bold;">${diffDays} dia(s)</span>`;
      } else {
        dias = `${diffDays} dia(s)`;
      }
    } else if (lote.data_validade) {
      // Se tem data mas não conseguiu calcular, mostra que tem data
      dias = "Calculando...";
    }
    
    const podeImprimir = podeImprimirEtiqueta();
    const acoes = [
      '<div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">',
      '<button class="table-action" data-action="edit">Editar</button>',
      podeImprimir ? '<button class="table-action" data-action="etiqueta" title="Imprimir etiqueta">Etiqueta</button>' : '',
      ativo
        ? '<button class="table-action danger" data-action="disable">Desativar</button>'
        : '<button class="table-action" data-action="enable">Ativar</button>',
      '<button class="table-action danger" data-action="delete">Excluir</button>',
      '</div>',
    ].join("");
    
    // Status baseado em ativo e outras condições
    let statusLabel = ativo ? "Ativo" : "Inativo";
    if (ativo) {
      if ((lote.quantidade ?? lote.qtd_atual ?? 0) <= 0) {
        statusLabel = "Esgotado";
      } else if (lote.data_validade && lote.data_validade < new Date().toISOString().split('T')[0]) {
        statusLabel = "Vencido";
      }
    }
    
    // Debug: log do primeiro lote para verificar dados
    if (!primeiroLoteLogado) {
      console.log("📋 Primeiro lote renderizado:", {
        id: lote.id,
        data_validade: lote.data_validade,
        validadeFormatada: validadeFormatada,
        dias_para_vencer: lote.dias_para_vencer,
        diffDays: diffDays,
        dias: dias
      });
      primeiroLoteLogado = true;
    }
    
    return `<tr data-id="${lote.id}">
      <td data-label="Produto">${escapeHtml(lote.produto_nome || "--")}</td>
      <td data-label="Unidade">${escapeHtml(lote.unidade_nome || "--")}</td>
      <td data-label="Codigo">${escapeHtml(lote.codigo_lote || lote.numero_lote || "--")}</td>
      <td data-label="Qtd">${quantidade}</td>
      <td data-label="Validade">${validadeFormatada}</td>
      <td data-label="Dias p/ vencer">${dias}</td>
      <td data-label="Status">${buildStatusPill(statusLabel)}</td>
      <td data-label="Valor total">R$ ${formatNumber((lote.quantidade ?? lote.qtd_atual ?? 0) * (lote.custo_unitario ?? 0), 2)}</td>
      <td data-label="Acoes" class="table-actions">${acoes}</td>
    </tr>`;
  }).join("");
  
  console.log("✅ Renderizando", rows.split("</tr>").length - 1, "linhas na tabela de lotes");
  if (!dom.lotesManageTable) {
    console.error("❌ dom.lotesManageTable não encontrado!");
    return;
  }
  renderTable(dom.lotesManageTable, rows, "Nenhum lote para os filtros.", 9);
}

function renderProdutos(lista) {
  const abaixoMinimoMap = new Map((state.produtosAbaixoMinimo || []).map((item) => [String(item.produto_id), item]));
  
  // Verifica se é COZINHA ou BAR (não podem gerenciar produtos)
  const perfil = (currentUser?.perfil || "").toString().trim().toUpperCase();
  const isCozinhaOuBar = perfil === "COZINHA" || perfil === "BAR" || perfil === "ATENDENTE";
  const podeGerenciar = canManageProdutos();
  
  // Ocultar coluna de Ações no cabeçalho se for BAR ou COZINHA
  const produtosTable = dom.produtosTable;
  if (produtosTable) {
    const thead = produtosTable.closest('table')?.querySelector('thead');
    if (thead) {
      const thAcoes = thead.querySelector('th:last-child');
      if (thAcoes && thAcoes.textContent.trim() === 'Acoes') {
        thAcoes.style.display = isCozinhaOuBar ? 'none' : '';
      }
    }
  }
  
  const rows = (lista || []).map((produto) => {
    const ativo = Number(produto.ativo) === 1;
    const infoMinimo = abaixoMinimoMap.get(String(produto.id));
    const rowClass = infoMinimo ? ' class="produto-abaixo-minimo"' : "";
    const badge = infoMinimo
      ? `<span class="table-flag danger" title="Estoque atual: ${formatNumber(infoMinimo.estoque_atual, 3)}">Estoque atual: ${formatNumber(
          infoMinimo.estoque_atual,
          3,
        )}</span>`
      : "";
    
    // COZINHA e BAR não vêem ações
    const acoes = (!isCozinhaOuBar && podeGerenciar) ? [
      '<button class="table-action" data-action="edit">Editar</button>',
      ativo
        ? '<button class="table-action danger" data-action="disable">Desativar</button>'
        : '<button class="table-action" data-action="enable">Ativar</button>',
      '<button class="table-action danger" data-action="delete">Excluir</button>',
    ].join("") : "--";
    
    // Se for BAR ou COZINHA, não renderiza a coluna de Ações
    const colunaAcoes = isCozinhaOuBar ? '' : `<td data-label="Acoes" class="table-actions">${acoes}</td>`;
    
    return `<tr data-id="${produto.id}"${rowClass}>
        <td data-label="Nome">${escapeHtml(produto.nome)}</td>
        <td data-label="Categoria">${escapeHtml(produto.categoria)}</td>
        <td data-label="Unidade base">${escapeHtml(normalizarUnidadeBase(produto.unidade_base))}</td>
        <td data-label="Codigo de barras">${escapeHtml(produto.codigo_barras || "--")}</td>
        <td data-label="Custo medio">R$ ${formatNumber(produto.custo_medio, 2)}</td>
        <td data-label="Estoque minimo">${formatNumber(produto.estoque_minimo, 3)}${badge}</td>
        <td data-label="Unidade">${escapeHtml(produto.unidade_nome || "--")}</td>
        <td data-label="Status"><span class="status-pill ${ativo ? "status-pill--active" : "status-pill--inactive"}">${ativo ? "Ativo" : "Inativo"}</span></td>
        ${colunaAcoes}
      </tr>`;
  }).join("");
  
  // Ajusta o número de colunas baseado se BAR/COZINHA veem ações ou não
  const numColunas = isCozinhaOuBar ? 8 : 9;
  renderTable(dom.produtosTable, rows, "Nenhum produto cadastrado.", numColunas);
}

function renderProdutosDashboard(lista) {
  // Tenta encontrar o elemento novamente se não estiver disponível
  if (!dom.produtosDashboardTable) {
    dom.produtosDashboardTable = document.getElementById("produtosDashboardTable");
  }
  
  if (!dom.produtosDashboardTable) {
    console.warn("dom.produtosDashboardTable não encontrado");
    return;
  }
  
  const rows = (lista || [])
    .filter((produto) => Number(produto.ativo ?? 1) === 1)
    .slice(0, 10)
    .map((produto) => (
      `<tr><td>${escapeHtml(produto.nome)}</td><td>${escapeHtml(produto.categoria)}</td><td>${escapeHtml(normalizarUnidadeBase(produto.unidade_base))}</td><td>${escapeHtml(produto.unidade_nome || "--")}</td></tr>`
    ))
    .join("");
  renderTable(dom.produtosDashboardTable, rows, "Nenhum produto.", 4);
}

function renderLocais(lista) {
  const podeEditar = isAdmin();
  const header = document.getElementById("locaisAcoesHeader");
  if (header) header.style.display = podeEditar ? "" : "none";
  const rows = (lista || []).map((local) => {
    const ativo = Number(local.ativo ?? 1) === 1;
    const temperatura = local.temperatura_media !== null && local.temperatura_media !== undefined
      ? `${formatNumber(Number(local.temperatura_media), 1)} C`
      : "--";
    const acesso = local.nivel_acesso ? escapeHtml(LOCAL_NIVEL_ACESSO_LABELS[local.nivel_acesso] || local.nivel_acesso) : "--";
    const cadastro = local.data_cadastro ? formatDate(local.data_cadastro) : "--";
    const tipoLabel = LOCAL_TIPOS_LABELS[local.tipo] || local.tipo || "--";
    const observacoes = local.observacoes || local.descricao || "--";
    const statusPill = buildStatusPill(ativo ? "Ativo" : "Inativo");
    const acoesCell = podeEditar ? `<td data-label="Acoes" class="table-actions">${[
      '<button class="table-action" data-action="edit">Editar</button>',
      ativo
        ? '<button class="table-action danger" data-action="disable">Desativar</button>'
        : '<button class="table-action" data-action="enable">Ativar</button>',
      '<button class="table-action danger" data-action="delete">Excluir</button>',
    ].join("")}</td>` : "";
    return `<tr data-id="${escapeHtml(String(local.id))}">
      <td data-label="ID">${escapeHtml(String(local.id))}</td>
      <td data-label="Nome">${escapeHtml(local.nome || "--")}</td>
      <td data-label="Unidade">${escapeHtml(local.unidade_nome || "--")}</td>
      <td data-label="Tipo">${escapeHtml(tipoLabel)}</td>
      <td data-label="Temperatura">${temperatura}</td>
      <td data-label="Acesso">${acesso}</td>
      <td data-label="Observacoes">${escapeHtml(observacoes)}</td>
      <td data-label="Cadastro">${cadastro}</td>
      <td data-label="Status">${statusPill}</td>
      ${acoesCell}
    </tr>`;
  }).join("");
  renderTable(dom.locaisTable, rows, "Nenhum local cadastrado.", podeEditar ? 10 : 9);
}

function renderUnidades(lista) {
  const podeEditar = isAdmin();
  const header = document.getElementById("unidadesAcoesHeader");
  if (header) header.style.display = podeEditar ? "" : "none";
  const rows = (lista || []).map((unidade) => {
    const ativo = Number(unidade.ativo) === 1;
    const statusLabel = ativo ? "Ativa" : "Desativada";
    const acoesCell = podeEditar ? `<td data-label="Acoes" class="table-actions">${[
      '<button class="table-action" data-action="edit">Editar</button>',
      ...(ativo ? ['<button class="table-action danger" data-action="disable">Desativar</button>'] : []),
      '<button class="table-action danger" data-action="delete">Excluir</button>',
    ].join("")}</td>` : "";
    return `<tr data-id="${unidade.id}">
      <td data-label="Nome">${escapeHtml(unidade.nome)}</td>
      <td data-label="Endereco">${escapeHtml(unidade.endereco || "--")}</td>
      <td data-label="CNPJ">${escapeHtml(unidade.cnpj || "--")}</td>
      <td data-label="Gerente">${escapeHtml(unidade.gerente_nome || "--")}</td>
      <td data-label="Telefone">${escapeHtml(unidade.telefone || "--")}</td>
      <td data-label="Email">${escapeHtml(unidade.email || "--")}</td>
      <td data-label="Observacoes">${escapeHtml(unidade.observacoes || "--")}</td>
      <td data-label="Status"><span class="status-pill ${ativo ? "status-pill--active" : "status-pill--inactive"}">${statusLabel}</span></td>
      ${acoesCell}
    </tr>`;
  }).join("");
  renderTable(dom.unidadesTable, rows, "Nenhuma unidade cadastrada.", podeEditar ? 9 : 8);
}

function renderUsuarios(lista) {
  const isAdminUser = isAdmin();
  const podeGerenciarBar = canManageUsuariosBar();

  // Ocultar coluna de Ações no cabeçalho para não-ADMIN
  const usuariosAcoesHeaderEl = document.getElementById("usuariosAcoesHeader");
  if (usuariosAcoesHeaderEl) usuariosAcoesHeaderEl.style.display = isAdminUser ? "" : "none";

  const rows = (lista || []).map((usuario) => {
    const ativo = Number(usuario.ativo) === 1;
    const fotoPath = usuario.foto || usuario.foto_path;
    const fotoUrl = getUsuarioFotoUrl(fotoPath);
    const foto = fotoUrl
      ? `<img src="${fotoUrl}" alt="${escapeHtml(usuario.nome)}" class="usuarios-foto" loading="lazy" />`
      : '<div class="usuarios-foto usuarios-foto--placeholder" aria-label="Sem foto"></div>';

    const podeGerenciar = isAdminUser
      ? true
      : canManageUsuario(usuario) || (podeGerenciarBar && (usuario.perfil || "").toString().trim().toUpperCase() === "BAR");

    const acoes = podeGerenciar ? [
      '<button class="table-action" data-action="edit">Editar</button>',
      ativo
        ? '<button class="table-action danger" data-action="disable">Desativar</button>'
        : '<button class="table-action" data-action="enable">Ativar</button>',
      isAdminUser ? '<button class="table-action danger" data-action="delete">Excluir</button>' : '',
    ].join("") : "--";

    const colunaAcoes = isAdminUser ? `<td data-label="Acoes" class="table-actions">${acoes}</td>` : "";

    return `<tr data-id="${usuario.id}"${!ativo ? ' class="usuario-inativo"' : ''}>
      <td data-label="Foto">${foto}</td>
      <td data-label="Nome">${escapeHtml(usuario.nome)}</td>
      <td data-label="Email">${escapeHtml(usuario.email)}</td>
      <td data-label="Perfil">${escapeHtml(PERFIL_LABELS[(usuario.perfil || "").toString().trim().toUpperCase()] || (usuario.perfil || "--"))}</td>
      <td data-label="Unidade">${escapeHtml(usuario.unidade_nome || "--")}</td>
      <td data-label="Status"><span class="status-pill ${ativo ? "status-pill--active" : "status-pill--inactive"}">${ativo ? "Ativo" : "Inativo"}</span></td>
      ${colunaAcoes}
    </tr>`;
  }).join("");

  renderTable(dom.usuariosTable, rows, "Nenhum usuario cadastrado.", isAdminUser ? 7 : 6);
}

function renderRelatorioResumo(lista, label) {
  if (dom.relResumoColuna) dom.relResumoColuna.textContent = label;
  const colLabel = label || "Agrupador";
  const rows = (lista || []).map((item) => (
    `<tr>
      <td data-label="${colLabel}">${escapeHtml(item.nome)}</td>
      <td data-label="Entradas (Qtd)">${formatNumber(item.entradasQtd, 3)}</td>
      <td data-label="Entradas (Valor)">R$ ${formatNumber(item.entradasVal, 2)}</td>
      <td data-label="Saídas (Qtd)">${formatNumber(item.saidasQtd, 3)}</td>
      <td data-label="Saídas (Valor)">R$ ${formatNumber(item.saidasVal, 2)}</td>
      <td data-label="Saldo (Qtd)">${formatNumber(item.saldoQtd, 3)}</td>
    </tr>`
  )).join("");
  renderTable(dom.relatorioResumoTable, rows, "Sem dados no periodo.", 6);
}

const renderRelatorioDetalhes = (lista) => renderMovimentacoes(lista, dom.relatorioDetalhesTable, "Sem movimentacoes.");

function arrayBufferToBase64(buffer) {
  let binary = "";
  const bytes = new Uint8Array(buffer);
  const len = bytes.byteLength;
  for (let i = 0; i < len; i += 1) {
    binary += String.fromCharCode(bytes[i]);
  }
  return window.btoa(binary);
}

let cachedLogoMarkup = null;
const LOGO_FALLBACK_MARKUP = '<img src="imagens/logo.png" alt="Logo" onerror="this.onerror=null;this.src=\'imagens/logosemfundo.png\';" />';
async function getReportLogoMarkup() {
  if (cachedLogoMarkup !== null) return cachedLogoMarkup;
  const logos = ["imagens/logo.png", "imagens/logosemfundo.png", "imagens/logo.pdf.png", "imagens/logo.pdf"];
  for (const caminho of logos) {
    try {
      const resposta = await fetch(caminho);
      if (!resposta.ok) continue;
      const contentType = (resposta.headers.get("Content-Type") || "").toLowerCase();
      if (contentType.includes("image/")) {
        const blob = await resposta.blob();
        const dataUrl = await new Promise((resolve, reject) => {
          const reader = new FileReader();
          reader.onloadend = () => resolve(reader.result);
          reader.onerror = () => reject(new Error("Falha ao carregar logo"));
          reader.readAsDataURL(blob);
        });
        cachedLogoMarkup = `<img src="${dataUrl}" alt="Logo" />`;
        return cachedLogoMarkup;
      }
      if (contentType.includes("pdf")) {
        const buffer = await resposta.arrayBuffer();
        const base64 = arrayBufferToBase64(buffer);
        const dataUrl = `data:application/pdf;base64,${base64}`;
        cachedLogoMarkup = `<object data="${dataUrl}" type="application/pdf" width="120" height="120"></object>`;
        return cachedLogoMarkup;
      }
    } catch (err) {
      /* tenta proximo */
    }
  }
  cachedLogoMarkup = LOGO_FALLBACK_MARKUP;
  return cachedLogoMarkup;
}

function getRelatorioSnapshot() {
  const filtros = collectRelatorioFiltros();
  return {
    filtros,
    resumo: Array.isArray(state.relatorioResumo) ? [...state.relatorioResumo] : [],
    detalhes: Array.isArray(state.movimentacoes) ? [...state.movimentacoes] : [],
  };
}

function exportRelatorioCsv() {
  const { filtros, resumo, detalhes } = getRelatorioSnapshot();
  if (!resumo.length && !detalhes.length) {
    showToast("Gere o relatorio antes de exportar.", "warning");
    return;
  }
  const linhas = [];
  const agora = new Date();
  linhas.push(`Relatorio de Movimentacoes;Gerado em;${agora.toLocaleString("pt-BR")}`);
  linhas.push("");
  linhas.push("Filtros aplicados;");
  linhas.push(`Agrupar por;${filtros.agrupar || "produto"}`);
  linhas.push(`Tipo;${filtros.tipo || "Todos"}`);
  linhas.push(`Produto ID;${filtros.produto_id || "Todos"}`);
  linhas.push(`Unidade ID;${filtros.unidade_id || "Todas"}`);
  linhas.push(`Data inicial;${filtros.data_ini || "--"}`);
  linhas.push(`Data final;${filtros.data_fim || "--"}`);
  linhas.push("");
  linhas.push("Resumo;");
  linhas.push("Agrupador;Entradas (Qtd);Entradas (Valor);Saidas (Qtd);Saidas (Valor);Saldo (Qtd)");
  resumo.forEach((item) => {
    linhas.push(
      [
        item.nome,
        formatNumber(item.entradasQtd, 3),
        formatNumber(item.entradasVal, 2),
        formatNumber(item.saidasQtd, 3),
        formatNumber(item.saidasVal, 2),
        formatNumber(item.saldoQtd, 3),
      ].join(";"),
    );
  });
  linhas.push("");
  linhas.push("Detalhes;");
  linhas.push("Data;Tipo;Produto;Unidade;Quantidade;Motivo;Responsavel");
  detalhes.forEach((mov) => {
    const quantidadeValor = Number(mov.qtd ?? mov.quantidade ?? 0);
    const quantidade = `${formatNumber(quantidadeValor, 3)} ${mov.unidade || ""}`.trim();
    linhas.push(
      [
        formatDate(mov.data_mov),
        (mov.tipo || "--").toUpperCase(),
        mov.produto_nome || "--",
        mov.unidade_nome || "--",
        quantidade,
        mov.motivo || mov.observacao || "--",
        mov.responsavel_nome || "--",
      ].map((valor) => String(valor).replace(/;/g, ",")).join(";"),
    );
  });
  linhas.push("");
  linhas.push("Grupo Sabor Paraense");

  const blob = new Blob([linhas.join("\r\n")], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = `relatorio_movimentacoes_${agora.toISOString().replace(/[:.]/g, "-")}.csv`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
  showToast("Relatorio exportado em CSV.", "success");
}

async function exportRelatorioPdf() {
  const { filtros, resumo, detalhes } = getRelatorioSnapshot();
  if (!resumo.length && !detalhes.length) {
    showToast("Gere o relatorio antes de exportar.", "warning");
    return;
  }
  const agora = new Date();
  const titulo = "Relatorio de Movimentacoes";
  const logoMarkup = await getReportLogoMarkup();
  const estilo = `
    <style>
      body { font-family: Arial, sans-serif; color: #111; padding: 24px; }
      .report-header { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
      .report-header img, .report-header object { height: 64px; width: auto; max-width: 120px; }
      .report-header object { border: none; }
      .report-header h1 { margin: 0; font-size: 24px; }
      .meta { margin-bottom: 24px; }
      table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
      th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 12px; text-align: left; }
      th { background: #f0f4f8; }
      .section-title { font-size: 16px; margin: 24px 0 8px; }
      .report-footer {
        margin-top: 40px;
        padding-top: 16px;
        border-top: 1px solid #ddd;
        text-align: center;
        font-size: 13px;
        color: #333;
        font-weight: 600;
      }
    </style>
  `;
  const filtrosHtml = `
    <div class="meta">
      <strong>Gerado em:</strong> ${agora.toLocaleString("pt-BR")}<br />
      <strong>Agrupar por:</strong> ${filtros.agrupar || "produto"}<br />
      <strong>Tipo:</strong> ${filtros.tipo || "Todos"}<br />
      <strong>Produto ID:</strong> ${filtros.produto_id || "Todos"}<br />
      <strong>Unidade ID:</strong> ${filtros.unidade_id || "Todas"}<br />
      <strong>Data inicial:</strong> ${filtros.data_ini || "--"}<br />
      <strong>Data final:</strong> ${filtros.data_fim || "--"}
    </div>
  `;
  const resumoHtml = resumo.length
    ? `
      <h2 class="section-title">Resumo por agrupador</h2>
      <table>
        <thead>
          <tr>
            <th>Agrupador</th>
            <th>Entradas (Qtd)</th>
            <th>Entradas (Valor)</th>
            <th>Saidas (Qtd)</th>
            <th>Saidas (Valor)</th>
            <th>Saldo (Qtd)</th>
          </tr>
        </thead>
        <tbody>
          ${resumo
            .map(
              (item) => `
                <tr>
                  <td>${escapeHtml(item.nome)}</td>
                  <td>${formatNumber(item.entradasQtd, 3)}</td>
                  <td>R$ ${formatNumber(item.entradasVal, 2)}</td>
                  <td>${formatNumber(item.saidasQtd, 3)}</td>
                  <td>R$ ${formatNumber(item.saidasVal, 2)}</td>
                  <td>${formatNumber(item.saldoQtd, 3)}</td>
                </tr>`,
            )
            .join("")}
        </tbody>
      </table>
    `
    : "<p>Sem dados de resumo para os filtros selecionados.</p>";

  const detalhesHtml = detalhes.length
    ? `
      <h2 class="section-title">Movimentacoes detalhadas</h2>
      <table>
        <thead>
          <tr>
            <th>Data</th>
            <th>Tipo</th>
            <th>Produto</th>
            <th>Unidade</th>
            <th>Quantidade</th>
            <th>Motivo</th>
            <th>Responsavel</th>
          </tr>
        </thead>
        <tbody>
          ${detalhes
            .map((mov) => {
              const quantidadeValor = Number(mov.qtd ?? mov.quantidade ?? 0);
              const quantidade = `${formatNumber(quantidadeValor, 3)} ${escapeHtml(mov.unidade || "")}`.trim();
              return `
                <tr>
                  <td>${formatDate(mov.data_mov)}</td>
                  <td>${escapeHtml((mov.tipo || "--").toUpperCase())}</td>
                  <td>${escapeHtml(mov.produto_nome || "--")}</td>
                  <td>${escapeHtml(mov.unidade_nome || "--")}</td>
                  <td>${quantidade}</td>
                  <td>${escapeHtml(mov.motivo || mov.observacao || "--")}</td>
                  <td>${escapeHtml(mov.responsavel_nome || "--")}</td>
                </tr>`;
            })
            .join("")}
        </tbody>
      </table>
    `
    : "<p>Sem movimentacoes para os filtros selecionados.</p>";

  const conteudo = `
    <!DOCTYPE html>
    <html lang="pt-BR">
      <head>
        <meta charset="utf-8" />
        <title>${titulo}</title>
        ${estilo}
      </head>
      <body>
        <div class="report-header">
          ${logoMarkup}
          <h1>${titulo}</h1>
        </div>
        ${filtrosHtml}
        ${resumoHtml}
        ${detalhesHtml}
        <footer class="report-footer">Grupo Sabor Paraense</footer>
      </body>
    </html>
  `;

  const iframe = document.createElement("iframe");
  iframe.style.position = "fixed";
  iframe.style.right = "0";
  iframe.style.bottom = "0";
  iframe.style.width = "0";
  iframe.style.height = "0";
  iframe.style.border = "0";
  iframe.style.visibility = "hidden";
  document.body.appendChild(iframe);

  let timeoutId = null;

  const cleanup = () => {
    if (timeoutId) {
      clearTimeout(timeoutId);
      timeoutId = null;
    }
    try {
      if (iframe.contentWindow) {
        iframe.contentWindow.onafterprint = null;
      }
    } catch (err) {
      /* noop */
    }
    if (iframe.parentNode) {
      iframe.parentNode.removeChild(iframe);
    }
  };

  iframe.onload = () => {
    const win = iframe.contentWindow;
    if (!win) {
      cleanup();
      showToast("Nao foi possivel preparar o PDF.", "error");
      return;
    }
    win.onafterprint = cleanup;
    win.focus();
    try {
      if (typeof win.print === "function") {
        win.print();
      } else {
        cleanup();
        showToast("Seu navegador nao suportou a impressao.", "error");
      }
    } catch (err) {
      cleanup();
      showToast("Falha ao acionar a impressao.", "error");
    }
  };

  iframe.onerror = () => {
    cleanup();
    showToast("Nao foi possivel gerar o PDF.", "error");
  };

  const doc = iframe.contentDocument || iframe.contentWindow?.document;
  if (!doc) {
    cleanup();
    showToast("Nao foi possivel gerar o PDF.", "error");
    return;
  }
  doc.open();
  doc.write(conteudo);
  doc.close();

  timeoutId = setTimeout(() => {
    if (!document.body.contains(iframe)) return;
    cleanup();
    showToast("Falha ao imprimir. Verifique as configuracoes do navegador.", "error");
  }, 60000);
  showToast("Relatorio enviado para impressao/arquivo PDF.", "success");
}

function buildListaStatusPill(status) {
  const label = LISTA_STATUS_LABEL[status] || status || "--";
  const css = LISTA_STATUS_CLASS[status] || "status-pill--muted";
  return `<span class="status-pill ${css}">${label}</span>`;
}

function updateListaStatusButtons(lista) {
  const statusAtual = (lista?.status || "").toUpperCase();
  const bloqueado = !lista || statusAtual === "FINALIZADA";
  // Permite alterar status se for o dono OU se for admin
  const perfil = (currentUser?.perfil || "").toString().trim().toUpperCase();
  const isAdmin = perfil === "ADMIN";
  const permitido = canManageCompras() && (isListaOwner(lista) || isAdmin);
  const controles = [
    { el: dom.listaCompraStatusRascunho, codigo: "RASCUNHO" },
    { el: dom.listaCompraStatusEmCompras, codigo: "EM_COMPRAS" },
    { el: dom.listaCompraStatusPausada, codigo: "PAUSADA" },
  ];
  controles.forEach(({ el, codigo }) => {
    if (!el) return;
    const ativo = statusAtual === codigo;
    const deveDesabilitar = bloqueado || !permitido || listaStatusAtualizando || ativo;
    el.disabled = deveDesabilitar;
    el.classList.toggle("is-active", ativo);
    el.setAttribute("aria-pressed", ativo ? "true" : "false");
  });
}

function resolveListaPdfHref(path) {
  if (!path) return "";
  if (path.startsWith("http://") || path.startsWith("https://")) {
    return path;
  }
  return `${API_URL}${path.startsWith("/") ? path : `/${path}`}`;
}

function updateListaPdfButton(lista) {
  const abrirBtn = dom.listaCompraPdf;
  const gerarBtn = dom.listaCompraGerarPdf;
  if (gerarBtn && !gerarBtn.dataset.loading) gerarBtn.dataset.loading = "0";
  if (!lista) {
    if (abrirBtn) {
      abrirBtn.textContent = "Abrir PDF";
      abrirBtn.disabled = true;
    }
    if (gerarBtn) {
      gerarBtn.textContent = "Gerar PDF";
      gerarBtn.disabled = true;
      gerarBtn.dataset.loading = "0";
    }
    return;
  }
  const status = (lista.status || "").toUpperCase();
  
  // Verifica se é perfil COZINHA ou BAR - podem gerar PDF mesmo sem lista finalizada
  const perfilAtual = currentUser && currentUser.perfil ? currentUser.perfil.toString().trim().toUpperCase() : "";
  const isCozinhaOuBar = perfilAtual === "COZINHA" || perfilAtual === "BAR" || perfilAtual === "ATENDENTE";
  
  // COZINHA e BAR podem gerar PDF se a lista existir, outros perfis precisam que esteja finalizada e tenham permissão
  const podeGerar = isCozinhaOuBar ? true : (status === "FINALIZADA" && canManageCompras());
  
  if (abrirBtn) {
    abrirBtn.textContent = "Abrir PDF";
    abrirBtn.disabled = !podeGerar;
  }
  
  if (gerarBtn) {
    const emProcesso = gerarBtn.dataset.loading === "1";
    gerarBtn.disabled = emProcesso || !podeGerar;
    if (!emProcesso) gerarBtn.textContent = "Gerar PDF";
  }
}

function buildEstabelecimentoOptions(selectedId) {
  // Usa estabelecimentos da lista atual, se disponível, senão usa globais
  const estabelecimentos = (state.listaCompraAtual?.estabelecimentos || state.estabelecimentosGlobais || []);
  const opts = estabelecimentos.map(
    (est) => `<option value="${est.id}" ${Number(est.id) === Number(selectedId) ? "selected" : ""}>${escapeHtml(est.nome)}</option>`,
  );
  return ['<option value="">Sem vinculo</option>', ...opts].join("");
}

function renderListasCompras(listas) {
  const rows = (listas || []).map((lista) => {
    const selecionada = state.listaCompraAtual && Number(state.listaCompraAtual.id) === Number(lista.id);
    const classe = selecionada ? "selected" : "";
    return `<tr data-id="${lista.id}" class="${classe}">
      <td data-label="Lista">${escapeHtml(lista.nome)}</td>
      <td data-label="Unidade">${escapeHtml(lista.unidade_nome || "--")}</td>
      <td data-label="Status">${buildListaStatusPill(lista.status)}</td>
      <td data-label="Planejado">${formatCurrency(lista.total_planejado || 0)}</td>
      <td data-label="Realizado">${formatCurrency(lista.total_realizado || 0)}</td>
      <td data-label="Itens">${Number(lista.itens_comprados || 0)} / ${Number(lista.itens_total || 0)}</td>
    </tr>`;
  }).join("");
  renderTable(dom.listasComprasTable, rows, "Nenhuma lista de compras cadastrada.", 6);
}

function renderListaCompraDetalhes(lista) {
  if (!lista) {
    dom.listaCompraTitulo.textContent = "Selecione uma lista";
    dom.listaCompraSubtitulo.textContent = "";
    dom.listaCompraStatus.innerHTML = "--";
    dom.listaCompraObservacoes.value = "";
    dom.listaCompraTotalPlanejado.textContent = "R$ 0.00";
    dom.listaCompraTotalRealizado.textContent = "R$ 0.00";
    dom.listaCompraItensResumo.textContent = "0/0";
    updateListaPdfButton(null);
    if (dom.listaCompraLancarEstoque) {
      // COZINHA e BAR não podem lançar - oculta o botão
      const perfilAtual = (currentUser?.perfil || "").toString().trim().toUpperCase();
      const isCozinhaOuBar = perfilAtual === "COZINHA" || perfilAtual === "BAR" || perfilAtual === "ATENDENTE";
      if (isCozinhaOuBar) {
        dom.listaCompraLancarEstoque.classList.add("hidden");
      } else {
        dom.listaCompraLancarEstoque.classList.remove("hidden");
        dom.listaCompraLancarEstoque.disabled = true;
        dom.listaCompraLancarEstoque.textContent = "Lancar no Estoque";
        dom.listaCompraLancarEstoque.dataset.loading = "0";
      }
    }
    if (dom.listaCompraAdicionarItem) dom.listaCompraAdicionarItem.disabled = true;
    if (dom.listaCompraAdicionarEstabelecimento) dom.listaCompraAdicionarEstabelecimento.disabled = true;
    if (dom.listaCompraFinalizar) dom.listaCompraFinalizar.disabled = true;
    if (dom.listaCompraObservacoes) dom.listaCompraObservacoes.disabled = true;
    renderListaCompraItens([]);
    renderListaCompraEstabelecimentos([]);
    renderListaCompraAnexos([]);
    updateListaStatusButtons(null);
    updateNovaListaButton();
    return;
  }
  dom.listaCompraTitulo.textContent = lista.nome;
  dom.listaCompraSubtitulo.textContent = `Responsavel: ${escapeHtml(lista.responsavel_nome || "--")} • Unidade: ${escapeHtml(lista.unidade_nome || "--")}`;
  dom.listaCompraStatus.innerHTML = buildListaStatusPill(lista.status);
  dom.listaCompraObservacoes.value = lista.observacoes || "";
  dom.listaCompraTotalPlanejado.textContent = formatCurrency(lista.total_planejado || 0);
  dom.listaCompraTotalRealizado.textContent = formatCurrency(lista.total_realizado || 0);
  dom.listaCompraItensResumo.textContent = `${Number((lista.itens || []).filter((item) => (item.status || "").toUpperCase() === "COMPRADO").length)} / ${Number(lista.itens?.length || 0)}`;
  updateListaPdfButton(lista);
  const podeEditar = listaPermiteEdicao(lista);

  if (dom.listaCompraLancarEstoque) {
    // COZINHA e BAR não podem lançar - oculta o botão
    const perfilAtual = (currentUser?.perfil || "").toString().trim().toUpperCase();
    const isCozinhaOuBar = perfilAtual === "COZINHA" || perfilAtual === "BAR" || perfilAtual === "ATENDENTE";
    
    if (isCozinhaOuBar) {
      dom.listaCompraLancarEstoque.classList.add("hidden");
    } else {
      dom.listaCompraLancarEstoque.classList.remove("hidden");
      
      if (!dom.listaCompraLancarEstoque.dataset.loading) dom.listaCompraLancarEstoque.dataset.loading = "0";
      const jaLancado = Boolean(lista.estoque_lancado_em);
      const emProcesso = dom.listaCompraLancarEstoque.dataset.loading === "1";
      // ✅ 4. Bloquear botão quando não houver itens comprados
      const itensComprados = (lista.itens || []).filter((item) => (item.status || "").toUpperCase() === "COMPRADO");
      const temItensComprados = itensComprados.length > 0;
      
      dom.listaCompraLancarEstoque.textContent = jaLancado ? "Lancado" : "Lancar no Estoque";
      const podeLancar = listaPermiteLancarEstoque(lista);
      dom.listaCompraLancarEstoque.disabled =
        jaLancado ||
        lista.status !== "FINALIZADA" ||
        emProcesso ||
        !podeLancar ||
        !temItensComprados; // Bloqueia se não houver itens comprados
    }
  }
  if (dom.listaCompraFinalizar) {
    const podeFinalizar = listaPermiteFinalizar(lista);
    const status = (lista.status || "").toUpperCase();
    dom.listaCompraFinalizar.disabled = status === "FINALIZADA" || !podeFinalizar;
    dom.listaCompraFinalizar.textContent = status === "FINALIZADA" ? "Lista finalizada" : "Finalizar lista";
  }
  // Estoquista e Cozinha podem adicionar itens, mas não editar lista
  const podeAdicionarItens = listaPermiteAdicionarItens(lista);
  if (dom.listaCompraAdicionarItem) dom.listaCompraAdicionarItem.disabled = !podeAdicionarItens;
  
  // Estoquista e Cozinha não podem gerenciar estabelecimentos
  if (dom.listaCompraAdicionarEstabelecimento) {
    const podeGerenciarEstabelecimentos = canManageCompras() && !canOnlyCreateAndAddItems();
    dom.listaCompraAdicionarEstabelecimento.disabled = !podeGerenciarEstabelecimentos;
  }
  
  // Estoquista e Cozinha não podem editar observações da lista
  if (dom.listaCompraObservacoes) dom.listaCompraObservacoes.disabled = !podeEditar;
  renderListaCompraItens(lista.itens || []);
  // Renderiza os estabelecimentos visitados desta lista
  renderListaCompraEstabelecimentos(lista.estabelecimentos || []);
  renderListaCompraAnexos(lista.anexos || []);
  updateListaStatusButtons(lista);
  updateNovaListaButton();
}

function renderListaCompraItens(itens) {
  const podeEditar = listaPermiteEdicao();
  // ADMIN e GERENTE podem editar tudo. Estoquista e Cozinha não podem editar/deletar itens existentes
  const podeEditarItens = podeEditar && (isAdminOrGerente() || !canOnlyCreateAndAddItems());
  const inputAttrs = podeEditarItens ? "" : "disabled";
  const rows = (itens || []).map((item) => {
    const id = item.id;
    return `<tr data-id="${id}">
      <td data-label="Produto">${escapeHtml(item.produto_nome || "--")}</td>
      <td data-label="Planejado"><input class="lista-compras-item-input" data-field="quantidade_planejada" type="number" step="0.01" min="0" value="${formatQuantityDisplay(item.quantidade_planejada)}" ${inputAttrs} /></td>
      <td data-label="Comprado"><input class="lista-compras-item-input" data-field="quantidade_comprada" type="number" step="0.01" min="0" value="${formatQuantityDisplay(item.quantidade_comprada)}" ${inputAttrs} /></td>
      <td data-label="Preco unitario"><input class="lista-compras-item-input" data-field="valor_unitario" type="number" step="0.01" min="0" value="${formatUnitValue(item.valor_unitario || 0)}" ${inputAttrs} /></td>
      <td data-label="Total" class="lista-compras-total-cell" data-total="${item.valor_total || 0}">${formatCurrency(item.valor_total || 0)}</td>
      <td data-label="Status"><select class="lista-compras-item-select" data-field="status" ${inputAttrs}>
        <option value="PENDENTE" ${(item.status || "").toUpperCase() === "PENDENTE" ? "selected" : ""}>Pendente</option>
        <option value="COMPRADO" ${(item.status || "").toUpperCase() === "COMPRADO" ? "selected" : ""}>Comprado</option>
        <option value="CANCELADO" ${(item.status || "").toUpperCase() === "CANCELADO" ? "selected" : ""}>Cancelado</option>
      </select></td>
      <td data-label="Estabelecimento"><select class="lista-compras-item-select" data-field="estabelecimento_id" ${inputAttrs}>${buildEstabelecimentoOptions(item.estabelecimento_id)}</select></td>
      <td data-label="Observacoes"><textarea class="lista-compras-item-textarea" data-field="observacoes" ${inputAttrs}>${escapeHtml(item.observacoes || "")}</textarea></td>
      <td data-label="Acoes" class="compras-table-actions">
        <button class="table-action" data-action="edit" ${podeEditarItens ? "" : "disabled"}>Editar</button>
        <button class="table-action danger" data-action="delete" ${podeEditarItens ? "" : "disabled"}>Excluir</button>
      </td>
    </tr>`;
  }).join("");
  renderTable(dom.listaComprasItensTable, rows, "Nenhum item cadastrado nesta lista.", 9);
}

function renderListaCompraEstabelecimentos(estabelecimentos) {
  // Estoquista e Cozinha não podem gerenciar estabelecimentos
  const podeGerenciar = canManageCompras() && !canOnlyCreateAndAddItems();
  
  // Busca os itens da lista atual para verificar vínculos
  const itensLista = state.listaCompraAtual?.itens || [];
  
  const rows = (estabelecimentos || []).map((est) => {
    // Conta quantos itens estão vinculados a este estabelecimento
    const itensVinculados = itensLista.filter(item => 
      item.estabelecimento_id && Number(item.estabelecimento_id) === Number(est.id)
    );
    
    const quantidadeVinculados = itensVinculados.length;
    
    // Monta lista de produtos vinculados
    let vinculoTexto = "--";
    if (quantidadeVinculados > 0) {
      const produtosNomes = itensVinculados
        .map(item => item.produto_nome || "Produto sem nome")
        .slice(0, 3) // Mostra até 3 produtos
        .join(", ");
      
      const maisItens = quantidadeVinculados > 3 ? ` e mais ${quantidadeVinculados - 3}` : "";
      vinculoTexto = `${quantidadeVinculados} item(ns): ${produtosNomes}${maisItens}`;
    }
    
    // Classe para destacar estabelecimentos com vínculo
    const temVinculo = quantidadeVinculados > 0;
    const classeLinha = temVinculo ? "tem-vinculo" : "";
    
    return `<tr data-id="${est.id}" class="${classeLinha}">
      <td data-label="Nome">${escapeHtml(est.nome)}</td>
      <td data-label="Localizacao">${escapeHtml(est.localizacao || "--")}</td>
      <td data-label="Pagamento">${escapeHtml(est.forma_pagamento || "--")}</td>
      <td data-label="Vinculo" class="vinculo-cell" title="${escapeHtml(vinculoTexto)}">
        ${temVinculo 
          ? `<span class="vinculo-badge" title="${escapeHtml(vinculoTexto)}">${quantidadeVinculados} item(ns)</span>` 
          : '<span class="sem-vinculo">Sem vínculo</span>'}
      </td>
      <td data-label="Observacoes">${escapeHtml(est.observacoes || "--")}</td>
      <td data-label="Acoes" class="compras-table-actions">
        <button class="table-action" data-action="editar" ${podeGerenciar ? "" : "disabled"}>Editar</button>
        <button class="table-action danger" data-action="deletar" ${podeGerenciar ? "" : "disabled"}>Deletar</button>
      </td>
    </tr>`;
  }).join("");
  renderTable(dom.listaComprasEstabelecimentosTable, rows, "Nenhum estabelecimento registrado.", 6);
}

function renderListaCompraAnexos(anexos) {
  if (!dom.listaComprasAnexos) return;
  if (!anexos || anexos.length === 0) {
    dom.listaComprasAnexos.innerHTML = "<li>Nenhum anexo enviado.</li>";
    return;
  }
  dom.listaComprasAnexos.innerHTML = anexos.map((anexo) => {
    const caminho = anexo.arquivo_path ? `${API_URL}${anexo.arquivo_path.startsWith("/") ? anexo.arquivo_path : `/${anexo.arquivo_path}`}` : "#";
    const nome = anexo.nome_original || `Arquivo ${anexo.id}`;
    const criado = anexo.criado_em ? formatDate(anexo.criado_em) : "--";
    return `<li><a href="${caminho}" target="_blank" rel="noopener">${escapeHtml(nome)}</a><span>Enviado em ${criado}</span></li>`;
  }).join("");
}

function resumoListaDetalhe(lista) {
  if (!lista) return null;
  const itens = lista.itens || [];
  const itensComprados = itens.filter((item) => (item.status || "").toUpperCase() === "COMPRADO").length;
  return {
    id: lista.id,
    nome: lista.nome,
    responsavel_id: lista.responsavel_id,
    responsavel_nome: lista.responsavel_nome,
    unidade_id: lista.unidade_id,
    unidade_nome: lista.unidade_nome,
    status: lista.status,
    total_planejado: lista.total_planejado,
    total_realizado: lista.total_realizado,
    itens_total: itens.length,
    itens_comprados: itensComprados,
  };
}

const flushItemUpdates = debounce(async () => {
  if (!pendingItemUpdates.size) return;
  const entries = Array.from(pendingItemUpdates.entries());
  pendingItemUpdates.clear();
  try {
    // Recalcula valores totais para garantir que estão corretos antes de enviar
    const finalEntries = entries.map(([itemId, payload]) => {
      // Se campos numéricos foram alterados, recalcula o total
      if (payload.quantidade_planejada !== undefined || 
          payload.quantidade_comprada !== undefined || 
          payload.valor_unitario !== undefined) {
        const row = dom.listaComprasItensTable?.querySelector(`tr[data-id="${itemId}"]`);
        if (row) {
          const novoTotal = calcularTotalItemLinha(row);
          payload.valor_total = novoTotal;
        }
      }
      return [itemId, payload];
    });
    
    await Promise.all(finalEntries.map(([itemId, payload]) => fetchJSON(`/itens/${itemId}`, {
      method: "PUT",
      body: JSON.stringify(payload),
    })));
    if (state.listaCompraAtual) await selecionarListaCompra(state.listaCompraAtual.id, true);
    showToast("Itens atualizados.", "success");
  } catch (error) {
    showToast(error.message, "error");
  }
}, 700);

function calcularTotalItemLinha(row) {
  if (!row) return 0;
  const quantidadePlanejadaInput = row.querySelector('input[data-field="quantidade_planejada"]');
  const quantidadeCompradaInput = row.querySelector('input[data-field="quantidade_comprada"]');
  const valorUnitarioInput = row.querySelector('input[data-field="valor_unitario"]');
  
  let quantidadePlanejada = 0;
  let quantidadeComprada = 0;
  let valorUnitario = 0;
  
  if (quantidadePlanejadaInput) {
    const valor = Number(quantidadePlanejadaInput.value) || 0;
    quantidadePlanejada = Math.max(0, roundToQuantity(valor));
  }
  
  if (quantidadeCompradaInput) {
    const valor = Number(quantidadeCompradaInput.value) || 0;
    quantidadeComprada = Math.max(0, roundToQuantity(valor));
  }
  
  if (valorUnitarioInput) {
    const valor = Number(valorUnitarioInput.value) || 0;
    valorUnitario = Math.max(0, roundToCurrency(valor));
  }
  
  // Usa quantidade comprada se houver, senão usa quantidade planejada
  const quantidadeBase = quantidadeComprada > 0 ? quantidadeComprada : quantidadePlanejada;
  const valorTotal = roundToCurrency(valorUnitario * quantidadeBase);
  return Math.max(0, valorTotal);
}

function atualizarTotalItemLinha(row) {
  if (!row) return;
  const totalCell = row.querySelector('.lista-compras-total-cell');
  if (!totalCell) return;
  const novoTotal = calcularTotalItemLinha(row);
  totalCell.textContent = formatCurrency(novoTotal);
  totalCell.setAttribute('data-total', novoTotal);
}

function queueItemUpdate(itemId, field, value) {
  if (!itemId) return;
  if (!pendingItemUpdates.has(itemId)) pendingItemUpdates.set(itemId, {});
  const payload = pendingItemUpdates.get(itemId);
  payload[field] = value;
  
  // Atualiza visualmente o total se os campos relevantes foram alterados
  if (["quantidade_planejada", "quantidade_comprada", "valor_unitario"].includes(field)) {
    const row = dom.listaComprasItensTable?.querySelector(`tr[data-id="${itemId}"]`);
    if (row) {
      atualizarTotalItemLinha(row);
    }
  }
  
  flushItemUpdates();
}


const atualizarObservacoesListaDebounced = debounce(async (listaId, observacoes) => {
  if (!listaId) return;
  try {
    await fetchJSON(`/listas/${listaId}`, {
      method: "PUT",
      body: JSON.stringify({ observacoes }),
    });
    if (state.listaCompraAtual && Number(state.listaCompraAtual.id) === Number(listaId)) {
      state.listaCompraAtual.observacoes = observacoes;
    }
  } catch (error) {
    showToast(error.message, "error");
  }
}, 600);

async function loadListasCompras() {
  const statusFiltro = state.listaComprasFiltroStatus || "ativas";
  if (dom.listaCompraFiltroStatus) dom.listaCompraFiltroStatus.value = statusFiltro;
  try {
    const todas = await fetchJSON("/listas");
    const listas = Array.isArray(todas) ? todas : [];
    const ativas = listas.filter((item) => (item.status || "").toUpperCase() !== "FINALIZADA");
    const finalizadas = listas.filter((item) => (item.status || "").toUpperCase() === "FINALIZADA");
    state.listasComprasAtivasSnapshot = ativas;

    let exibicao;
    if (statusFiltro === "finalizadas") exibicao = finalizadas;
    else if (statusFiltro === "todas") exibicao = listas;
    else exibicao = ativas;

    state.listasCompras = exibicao;
    renderListasCompras(state.listasCompras);

    let alvoId = state.listaCompraAtual?.id ? Number(state.listaCompraAtual.id) : null;
    if (!alvoId || !state.listasCompras.some((item) => Number(item.id) === alvoId)) {
      alvoId = state.listasCompras.length ? Number(state.listasCompras[0].id) : null;
    }

    if (alvoId) {
      await selecionarListaCompra(alvoId, true);
    } else {
      state.listaCompraAtual = null;
      renderListaCompraDetalhes(null);
    }
    updateNovaListaButton();
    updateComprasDashboardCard();
  } catch (error) {
    showToast(error.message, "error");
  }
}

async function selecionarListaCompra(listaId, silent = false) {
  if (!listaId) return;
  try {
    const detalhes = await fetchJSON(`/listas/${listaId}`);
    state.listaCompraAtual = detalhes;
    await loadEstabelecimentosGlobais();
    if (!silent) {
      showToast("Lista carregada.", "info");
    }
    if (detalhes) {
      const resumo = resumoListaDetalhe(detalhes);
      state.listasCompras = state.listasCompras.map((lista) => (Number(lista.id) === Number(listaId) ? { ...lista, ...resumo } : lista));
    }
    renderListasCompras(state.listasCompras);
    renderListaCompraDetalhes(detalhes);
  } catch (error) {
    showToast(error.message, "error");
  }
}

async function atualizarStatusListaCompra(status) {
  if (!state.listaCompraAtual) return;
  if (listaStatusAtualizando) return;
  const statusAtual = (state.listaCompraAtual.status || "").toUpperCase();
  const destino = (status || "").toUpperCase();
  if (!destino || statusAtual === destino) return;
  if (statusAtual === "FINALIZADA") {
    showToast("Lista finalizada nao permite alterar status.", "warning");
    updateListaStatusButtons(state.listaCompraAtual);
    return;
  }
  if (!canManageCompras()) {
    showToast("Sem permissão para alterar status.", "warning");
    return;
  }
  
  // Estoquista e Cozinha não podem alterar status
  if (canOnlyCreateAndAddItems()) {
    showToast("Você não tem permissão para alterar o status da lista.", "warning");
    updateListaStatusButtons(state.listaCompraAtual);
    return;
  }
  
  // ADMIN e GERENTE podem fazer tudo
  if (isAdminOrGerente()) {
    // Permite alterar status
  } else {
    // Outros perfis: permite alterar status se for o dono
    if (!isListaOwner(state.listaCompraAtual)) {
      showToast("Somente o criador da lista pode alterar o status.", "warning");
      updateListaStatusButtons(state.listaCompraAtual);
      return;
    }
  }
  listaStatusAtualizando = true;
  updateListaStatusButtons(state.listaCompraAtual);
  try {
    const atualizada = await fetchJSON(`/listas/${state.listaCompraAtual.id}`, {
      method: "PUT",
      body: JSON.stringify({ status: destino }),
    });
    state.listaCompraAtual = atualizada;
    const resumo = resumoListaDetalhe(atualizada);
    state.listasCompras = state.listasCompras.map((lista) => (Number(lista.id) === Number(atualizada.id) ? { ...lista, ...resumo } : lista));
    renderListasCompras(state.listasCompras);
    renderListaCompraDetalhes(atualizada);
    showToast("Status da lista atualizado.", "success");
  } catch (error) {
    showToast(error.message, "error");
  } finally {
    listaStatusAtualizando = false;
    updateListaStatusButtons(state.listaCompraAtual);
  }
}

async function abrirListaCompraModal(lista = null) {
  if (!dom.listaCompraForm) return;
  
  // Estoquista e Cozinha só podem criar novas listas, não editar existentes
  if (lista && canOnlyCreateAndAddItems()) {
    showToast("Você só pode criar novas listas de compras, não editar listas existentes.", "warning");
    return;
  }
  
  // Carrega unidades se necessário
  try {
    await loadUnidades(false);
  } catch (error) {
    console.error("Erro ao carregar unidades:", error);
    showToast("Erro ao carregar unidades. Tente novamente.", "error");
    return;
  }
  
  dom.listaCompraForm.reset();
  dom.listaCompraModalTitle.textContent = lista ? "Editar lista" : "Nova lista";
  
  const unidadeSelect = dom.listaCompraForm.elements.unidade_id;
  const perfil = (currentUser?.perfil || "").toString().trim().toUpperCase();
  const isCozinhaOuBar = perfil === "COZINHA" || perfil === "BAR" || perfil === "ATENDENTE";
  
  if (lista) {
    dom.listaCompraForm.elements.id.value = lista.id;
    dom.listaCompraForm.elements.nome.value = lista.nome || "";
    dom.listaCompraForm.elements.unidade_id.value = lista.unidade_id || "";
    dom.listaCompraForm.elements.observacoes.value = lista.observacoes || "";
    
    // COZINHA e BAR não podem editar unidade mesmo ao editar (se tivesse permissão)
    if (isCozinhaOuBar && unidadeSelect) {
      unidadeSelect.disabled = true;
    }
  } else {
    // Nova lista
    if (unidadeSelect) {
      // COZINHA e BAR: mostram apenas sua própria unidade
      if (isCozinhaOuBar && currentUser?.unidade_id) {
        // Aguarda um pouco para garantir que as unidades foram carregadas
        if (!state.unidades || state.unidades.length === 0) {
          await loadUnidades(false);
        }
        
        // Busca a unidade do usuário (COZINHA ou BAR)
        const unidadeUsuario = state.unidades.find(u => Number(u.id) === Number(currentUser.unidade_id));
        if (unidadeUsuario) {
          // Limpa e mostra apenas a unidade do usuário
          unidadeSelect.innerHTML = "";
          const option = document.createElement("option");
          option.value = String(unidadeUsuario.id);
          option.textContent = unidadeUsuario.nome;
          unidadeSelect.appendChild(option);
          unidadeSelect.value = String(currentUser.unidade_id);
          unidadeSelect.disabled = true;
          
          console.log(`✅ ${perfil} - Select configurado com apenas sua unidade:`, {
            unidadeId: unidadeUsuario.id,
            unidadeNome: unidadeUsuario.nome,
            selectValue: unidadeSelect.value,
            optionsCount: unidadeSelect.options.length
          });
        } else {
          showToast("Erro: unidade não encontrada. Entre em contato com o administrador.", "error");
          toggleModal(dom.listaCompraModal, false);
          return;
        }
      } else {
        // Outros perfis: mostra todas as unidades disponíveis
        if (state.unidades && state.unidades.length > 0) {
          unidadeSelect.innerHTML = "";
          const defaultOption = document.createElement("option");
          defaultOption.value = "";
          defaultOption.textContent = "Selecione";
          unidadeSelect.appendChild(defaultOption);
          
          state.unidades.forEach((unidade) => {
            const option = document.createElement("option");
            option.value = String(unidade.id);
            option.textContent = unidade.nome;
            unidadeSelect.appendChild(option);
          });
        }
        // Preenche com a unidade do usuário se existir
        if (currentUser?.unidade_id) {
          unidadeSelect.value = String(currentUser.unidade_id);
        }
        unidadeSelect.disabled = false;
      }
    }
  }
  
  toggleModal(dom.listaCompraModal, true);
}

async function submitListaCompra(event) {
  event.preventDefault();
  event.stopPropagation();
  
  if (!dom.listaCompraForm) {
    console.error("Formulário não encontrado");
    showToast("Erro: formulário não encontrado.", "error");
    return;
  }
  
  // Validação do formulário
  if (!dom.listaCompraForm.checkValidity()) {
    dom.listaCompraForm.reportValidity();
    showToast("Por favor, preencha todos os campos obrigatórios.", "warning");
    return;
  }
  
  const formData = new FormData(dom.listaCompraForm);
  const payload = Object.fromEntries(formData.entries());
  const listaId = payload.id && String(payload.id).trim() !== "" ? String(payload.id).trim() : null;
  
  // Obtém referência ao botão de submit
  const submitButton = dom.listaCompraForm.querySelector('button[type="submit"]');
  const originalButtonText = submitButton?.textContent || "Salvar";
  
  // Validação de campos obrigatórios
  if (!payload.nome || !String(payload.nome).trim()) {
    showToast("O nome da lista é obrigatório.", "error");
    return;
  }
  
  if (!currentUser || !currentUser.id) {
    showToast("Usuário não identificado. Faça login novamente.", "error");
    return;
  }
  
  // Validação de unidade (mesma lógica para todos)
  if (!payload.unidade_id || !String(payload.unidade_id).trim()) {
    showToast("Selecione uma unidade destino.", "error");
    return;
  }
  
  // Prepara o payload - remove campos vazios (mesma lógica para todos)
  const dataToSend = {
    nome: String(payload.nome).trim(),
    unidade_id: Number(payload.unidade_id),
    responsavel_id: Number(currentUser.id),
  };
  
  console.log("📋 Dados finais a serem enviados:", dataToSend);
  
  // Adiciona observações apenas se não estiver vazia
  if (payload.observacoes && String(payload.observacoes).trim()) {
    dataToSend.observacoes = String(payload.observacoes).trim();
  }
  
  // Desabilita o botão de submit para evitar duplo envio
  if (submitButton) {
    submitButton.disabled = true;
    submitButton.textContent = "Salvando...";
  }
  
  console.log("📤 Dados a serem enviados:", dataToSend);
  console.log("🔍 URL da API:", API_URL);
  console.log("👤 Usuário atual:", currentUser);
  
  try {
    const url = listaId ? `/listas/${listaId}` : "/listas";
    const method = listaId ? "PUT" : "POST";
    
    console.log(`📡 Enviando ${method} para: ${API_URL}${url}`);
    
    const response = await fetchJSON(url, {
      method: method,
      body: JSON.stringify(dataToSend),
    });
    
    console.log("✅ Resposta recebida:", response);
    
    if (!response || !response.id) {
      throw new Error("Resposta inválida do servidor. A lista não foi criada/atualizada.");
    }
    
    if (listaId) {
      // Edição de lista existente
      state.listaCompraAtual = response;
      const resumo = resumoListaDetalhe(response);
      state.listasCompras = state.listasCompras.map((lista) => 
        Number(lista.id) === Number(listaId) ? { ...lista, ...resumo } : lista
      );
      showToast("Lista atualizada com sucesso!", "success");
      toggleModal(dom.listaCompraModal, false);
      renderListasCompras(state.listasCompras);
      renderListaCompraDetalhes(response);
    } else {
      // Criação de nova lista
      showToast("Lista criada com sucesso!", "success");
      toggleModal(dom.listaCompraModal, false);
      // Recarrega as listas para garantir sincronização completa
      await loadListasCompras();
    }
  } catch (error) {
    console.error("❌ Erro ao salvar lista:", error);
    console.error("❌ Stack trace:", error.stack);
    console.error("❌ Dados enviados:", dataToSend);
    console.error("❌ Payload original:", payload);
    console.error("❌ currentUser:", currentUser);
    const errorMessage = error.message || "Falha ao salvar lista. Verifique os dados e tente novamente.";
    showToast(errorMessage, "error");
  } finally {
    // Reabilita o botão
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.textContent = originalButtonText;
    }
  }
}

// ============================================
// SUGESTÕES DE COMPRAS
// ============================================

// Controla quais itens já foram adicionados na sessão atual do modal (chave: "produtoId_unidadeId")
let sugestoesAdicionadas = new Set();

// Carrega sugestões de compras baseadas em movimentações
async function loadSugestoesCompras(unidadeId = null, diasAnalise = 30, diasProjecao = 15) {
  try {
    const params = new URLSearchParams();
    if (unidadeId) params.append('unidade_id', unidadeId);
    params.append('dias', diasAnalise);
    if (diasProjecao > 0) params.append('dias_projecao', diasProjecao);
    const dados = await fetchJSON(`/sugestoes-compras?${params.toString()}`);
    return dados;
  } catch (error) {
    console.error('Erro ao carregar sugestões:', error);
    showToast('Erro ao carregar sugestões de compras.', 'error');
    return { sugestoes: [], total_sugestoes: 0 };
  }
}

// Abre modal de sugestões
async function abrirSugestoesComprasModal() {
  if (!dom.sugestoesComprasModal) return;

  try {
    await loadUnidades(false);
  } catch (error) {
    console.error("Erro ao carregar unidades:", error);
  }

  if (dom.sugestoesFiltroUnidade && state.unidades && state.unidades.length > 0) {
    const options = state.unidades.map((u) =>
      `<option value="${u.id}">${escapeHtml(u.nome)}</option>`
    ).join("");
    dom.sugestoesFiltroUnidade.innerHTML = `<option value="">Todas</option>${options}`;
    if (currentUser?.unidade_id) {
      dom.sugestoesFiltroUnidade.value = currentUser.unidade_id;
    }
  }

  // Reseta controle de adicionados ao abrir o modal
  sugestoesAdicionadas = new Set();

  dom.sugestoesComprasContent.innerHTML = '<p style="text-align: center; color: #607d8b; padding: 2rem;">Clique em "Buscar Sugestões" para ver recomendações baseadas nas movimentações.</p>';

  toggleModal(dom.sugestoesComprasModal, true);
}

// Renderiza a tabela de sugestões filtrando os já adicionados
function renderSugestoesTabela(dados) {
  const pendentes = (dados.sugestoes || []).filter(
    (s) => !sugestoesAdicionadas.has(`${s.produto_id}_${s.unidade_id}`)
  );

  if (pendentes.length === 0) {
    toggleModal(dom.sugestoesComprasModal, false);
    showToast('Todos os itens foram adicionados à lista!', 'success');
    selecionarListaCompra(state.listaCompraAtual?.id, true);
    return;
  }

  // Monta o container
  const container = document.createElement('div');

  const diasProj = dados.dias_projecao || 0;
  const temProjecao = diasProj > 0;
  const resumo = document.createElement('div');
  resumo.style.cssText = 'margin-bottom:1rem;padding:1rem;background:#f5f5f5;border-radius:4px;';
  resumo.innerHTML = `<strong>Pendentes:</strong> ${pendentes.length} de ${dados.total_sugestoes} | <strong>Análise:</strong> últimos ${dados.dias_analise} dias` + (temProjecao ? ` | <strong>Projeção:</strong> próximos ${diasProj} dias` : '');
  container.appendChild(resumo);

  const tableWrapper = document.createElement('div');
  tableWrapper.className = 'table-wrapper sugestoes-compras-table';

  const theadCols = ['Prioridade', 'Produto', 'Unidade', 'Estoque Atual', 'Estoque Mín.', 'Qtd. p/ Completar', 'Consumo Médio/Dia'];
  if (temProjecao) theadCols.push(`Projeção (${diasProj} dias)`);
  theadCols.push('Qtd. Sugerida', 'Ações');

  const table = document.createElement('table');
  table.id = 'sugestoesTabela';
  table.innerHTML = '<thead><tr>' + theadCols.map(c => `<th>${c}</th>`).join('') + '</tr></thead>';

  const tbody = document.createElement('tbody');

  pendentes.forEach((sugestao) => {
    const unidadeBase = normalizarUnidadeBase(sugestao.unidade_base);
    const prioridadeClass = sugestao.prioridade === 'ALTA' ? 'status-pill--danger' :
                            sugestao.prioridade === 'MEDIA' ? 'status-pill--warning' :
                            'status-pill--muted';

    const qtdParaCompletar = sugestao.quantidade_para_completar ?? 0;
    const consumoProjetado = sugestao.consumo_projetado ?? 0;

    let tds = [
      { label: 'Prioridade', html: `<span class="status-pill ${prioridadeClass}">${sugestao.prioridade}</span>` },
      { label: 'Produto', html: escapeHtml(sugestao.produto_nome) },
      { label: 'Unidade', html: escapeHtml(sugestao.unidade_nome) },
      { label: 'Estoque Atual', html: `${fmtNum(sugestao.estoque_atual)} ${unidadeBase}` },
      { label: 'Estoque Mín.', html: `${fmtNum(sugestao.estoque_minimo)} ${unidadeBase}` },
      { label: 'Qtd. p/ Completar', html: `<strong>${fmtNum(qtdParaCompletar)}</strong> ${unidadeBase}` },
      { label: 'Consumo Médio/Dia', html: `${fmtNum(sugestao.consumo_medio_diario)} ${unidadeBase}` }
    ];
    if (temProjecao) tds.push({ label: `Projeção (${diasProj} dias)`, html: `${fmtNum(consumoProjetado)} ${unidadeBase}` });
    tds.push(
      { label: 'Qtd. Sugerida', html: `<strong>${fmtNum(sugestao.quantidade_sugerida)}</strong> ${unidadeBase}` },
      { label: 'Ações', html: '<button type="button" class="btn secondary btn-sm">Adicionar à Lista</button>', cls: 'sugestoes-acoes' }
    );

    const tr = document.createElement('tr');
    tr.innerHTML = tds.map(t => `<td data-label="${escapeHtml(t.label)}"${t.cls ? ' class="' + t.cls + '"' : ''}>${t.html}</td>`).join('');

    // Listener direto no botão desta linha — sem ambiguidade
    const btn = tr.querySelector('button');
    const produtoId = Number(sugestao.produto_id);
    const quantidade = parseFloat(sugestao.quantidade_sugerida);
    const unidadeId = Number(sugestao.unidade_id);
    const unidadeNome = sugestao.unidade_nome || '';
    btn.addEventListener('click', () => {
      adicionarSugestaoALista(produtoId, quantidade, unidadeBase, unidadeId, btn, unidadeNome);
    });

    tbody.appendChild(tr);
  });

  table.appendChild(tbody);
  tableWrapper.appendChild(table);
  container.appendChild(tableWrapper);

  const dica = document.createElement('div');
  dica.style.cssText = 'margin-top:1rem;padding:1rem;background:#e3f2fd;border-radius:4px;';
  dica.innerHTML = '<strong>💡 Dica:</strong> <strong>Qtd. p/ Completar</strong> = quanto comprar para atingir o estoque mínimo. ' + (temProjecao ? '<strong>Qtd. Sugerida</strong> inclui consumo projetado nos próximos ' + diasProj + ' dias. ' : '<strong>Qtd. Sugerida</strong> = Qtd. p/ Completar (sem projeção). ') + 'Produtos abaixo do mínimo aparecem mesmo sem consumo recente.';
  container.appendChild(dica);

  dom.sugestoesComprasContent.innerHTML = '';
  dom.sugestoesComprasContent.appendChild(container);
}

// Busca e exibe sugestões — nova busca reseta o controle de adicionados
async function buscarSugestoesCompras() {
  if (!dom.sugestoesComprasContent) return;

  const unidadeId = dom.sugestoesFiltroUnidade?.value || null;
  const diasAnalise = parseInt(dom.sugestoesDiasAnalise?.value || 30);
  const diasProjecaoRaw = dom.sugestoesDiasProjecao?.value?.trim();
  const diasProjecao = diasProjecaoRaw ? parseInt(diasProjecaoRaw) : 0;

  // Nova busca reseta os adicionados para mostrar tudo de novo
  sugestoesAdicionadas = new Set();

  dom.sugestoesComprasLoading.style.display = 'block';
  dom.sugestoesComprasContent.innerHTML = '';

  try {
    const dados = await loadSugestoesCompras(unidadeId, diasAnalise, diasProjecao);
    dom.sugestoesComprasLoading.style.display = 'none';

    if (!dados.sugestoes || dados.sugestoes.length === 0) {
      dom.sugestoesComprasContent.innerHTML = `
        <div style="text-align: center; padding: 2rem; color: #607d8b;">
          <p>Nenhuma sugestão encontrada para os parâmetros selecionados.</p>
          <p style="font-size: 0.9em; margin-top: 0.5rem;">Tente ajustar os filtros ou verifique se há movimentações no período.</p>
        </div>
      `;
      return;
    }

    // Guarda os dados da última busca para re-renderizar ao adicionar itens
    window._sugestoesComprasDados = dados;
    renderSugestoesTabela(dados);

  } catch (error) {
    dom.sugestoesComprasLoading.style.display = 'none';
    dom.sugestoesComprasContent.innerHTML = `
      <div style="text-align: center; padding: 2rem; color: #f44336;">
        <p>Erro ao carregar sugestões.</p>
        <p style="font-size: 0.9em; margin-top: 0.5rem;">${error.message || 'Tente novamente mais tarde.'}</p>
      </div>
    `;
  }
}

// Adiciona sugestão à lista atual (cria lista "Sugestão de Compras" automaticamente se não houver nenhuma selecionada)
async function adicionarSugestaoALista(produtoId, quantidade, unidade, unidadeId, btn = null, unidadeNome = '') {
  // Desabilita o botão da linha para evitar duplo clique
  if (btn) { btn.disabled = true; btn.textContent = 'Adicionando...'; }

  try {
    // Se não há lista selecionada, cria automaticamente uma lista "Sugestão de Compras"
    if (!state.listaCompraAtual || !state.listaCompraAtual.id) {
      if (!currentUser || !currentUser.id) {
        showToast('Usuário não identificado. Faça login novamente.', 'error');
        if (btn) { btn.disabled = false; btn.textContent = 'Adicionar à Lista'; }
        return;
      }
      const novaLista = await fetchJSON('/listas', {
        method: 'POST',
        body: JSON.stringify({
          nome: 'Sugestão de Compras',
          unidade_id: Number(unidadeId),
          responsavel_id: Number(currentUser.id),
        })
      });
      if (!novaLista || !novaLista.id) {
        showToast('Erro ao criar lista de compras automática.', 'error');
        if (btn) { btn.disabled = false; btn.textContent = 'Adicionar à Lista'; }
        return;
      }
      state.listaCompraAtual = novaLista;
      // Atualiza a lista lateral sem trocar a lista selecionada
      const todasListas = await fetchJSON('/listas');
      const listas = Array.isArray(todasListas) ? todasListas : [];
      state.listasCompras = listas.filter((l) => (l.status || '').toUpperCase() !== 'FINALIZADA');
      renderListasCompras(state.listasCompras);
      showToast('Lista "Sugestão de Compras" criada automaticamente.', 'info');
    }

    await fetchJSON('/itens', {
      method: 'POST',
      body: JSON.stringify({
        lista_id: state.listaCompraAtual.id,
        produto_id: produtoId,
        quantidade_planejada: quantidade,
        unidade: unidade,
        observacoes: `Sugestão automática baseada em movimentações${unidadeNome ? ' - Unidade: ' + unidadeNome : ''}`
      })
    });

    showToast('Item adicionado à lista!', 'success');

    // Marca como adicionado (chave composta produto+unidade) e atualiza a tabela
    sugestoesAdicionadas.add(`${produtoId}_${unidadeId}`);
    if (window._sugestoesComprasDados) {
      renderSugestoesTabela(window._sugestoesComprasDados);
    }

    // Atualiza os detalhes da lista em background sem bloquear o modal
    selecionarListaCompra(state.listaCompraAtual.id, true);

  } catch (error) {
    console.error('Erro ao adicionar sugestão:', error);
    showToast(error.message || 'Erro ao adicionar item à lista.', 'error');
    if (btn) { btn.disabled = false; btn.textContent = 'Adicionar à Lista'; }
  }
}

// Adiciona função global para uso no onclick
window.adicionarSugestaoALista = adicionarSugestaoALista;

async function abrirItemCompraModal(item = null) {
  if (!state.listaCompraAtual) {
    showToast("Selecione uma lista primeiro.", "warning");
    return;
  }
  
  // Se for editar item existente, verifica se pode editar
  if (item) {
    if (!listaPermiteEdicao()) {
      showToast("Esta lista não permite alteracoes no momento.", "warning");
      return;
    }
    // Estoquista e Cozinha não podem editar itens existentes
    if (canOnlyCreateAndAddItems()) {
      showToast("Você só pode adicionar novos itens, não editar itens existentes.", "warning");
      return;
    }
  } else {
    // Se for adicionar novo item, verifica se pode adicionar
    if (!listaPermiteAdicionarItens()) {
      showToast("Você não tem permissão para adicionar itens nesta lista.", "warning");
      return;
    }
  }
  
  // Garante que os produtos estejam carregados
  if (!state.produtos || state.produtos.length === 0) {
    try {
      await loadProdutos();
    } catch (err) {
      console.error("Erro ao carregar produtos:", err);
      showToast("Erro ao carregar produtos. Tente novamente.", "error");
      return;
    }
  }
  
  // Atualiza o select de produtos
  refreshProdutoSelects();
  
  listaCompraItemEdicaoId = item ? item.id : null;
  dom.itemCompraForm?.reset();
  if (dom.itemCompraForm?.elements.valor_total) {
    dom.itemCompraForm.elements.valor_total.readOnly = true;
  }
  
  // COZINHA e BAR não podem ver campos de valores ao adicionar itens
  const perfilAtual = currentUser && currentUser.perfil ? currentUser.perfil.toString().trim().toUpperCase() : "";
  const isCozinhaOuBar = perfilAtual === "COZINHA" || perfilAtual === "BAR" || perfilAtual === "ATENDENTE";
  
  const quantidadeCompradaLabel = document.getElementById("itemCompraLabelQuantidadeComprada");
  const valorUnitarioLabel = document.getElementById("itemCompraLabelValorUnitario");
  const valorTotalLabel = document.getElementById("itemCompraLabelValorTotal");
  
  if (quantidadeCompradaLabel) quantidadeCompradaLabel.classList.toggle("hidden", isCozinhaOuBar);
  if (valorUnitarioLabel) valorUnitarioLabel.classList.toggle("hidden", isCozinhaOuBar);
  if (valorTotalLabel) valorTotalLabel.classList.toggle("hidden", isCozinhaOuBar);
  
  dom.itemCompraModalTitle.textContent = item ? "Editar item" : "Adicionar item";
  if (dom.itemCompraForm?.elements.lista_id) dom.itemCompraForm.elements.lista_id.value = state.listaCompraAtual.id;
  if (item && dom.itemCompraForm) {
    dom.itemCompraForm.elements.id.value = item.id;
    dom.itemCompraForm.elements.produto_id.value = item.produto_id || "";
    dom.itemCompraForm.elements.unidade.value = item.unidade || "";
    dom.itemCompraForm.elements.quantidade_planejada.value = item.quantidade_planejada ?? "";
    // Só preenche campos de valores se não for COZINHA ou BAR
    if (!isCozinhaOuBar) {
      dom.itemCompraForm.elements.quantidade_comprada.value = item.quantidade_comprada ?? "";
      dom.itemCompraForm.elements.valor_unitario.value = item.valor_unitario ?? "";
      dom.itemCompraForm.elements.valor_total.value = item.valor_total ?? "";
    }
    dom.itemCompraForm.elements.observacoes.value = item.observacoes || "";
    dom.itemCompraForm.elements.estabelecimento_id.value = item.estabelecimento_id || "";
  }
  if (dom.itemCompraForm?.elements.estabelecimento_id) {
    dom.itemCompraForm.elements.estabelecimento_id.innerHTML = buildEstabelecimentoOptions(item?.estabelecimento_id);
  }
  // Só atualiza totais se não for COZINHA ou BAR
  if (!isCozinhaOuBar) {
    updateItemModalTotals();
  }
  toggleModal(dom.itemCompraModal, true);
}

function getItemModalNumericValue(name) {
  if (!dom.itemCompraForm) return 0;
  const campo = dom.itemCompraForm.elements[name];
  if (!campo) return 0;
  const numero = Number(campo.value);
  return Number.isFinite(numero) ? numero : 0;
}

function computeItemModalTotals() {
  const quantidadePlanejada = roundToQuantity(getItemModalNumericValue("quantidade_planejada"));
  const quantidadeComprada = roundToQuantity(getItemModalNumericValue("quantidade_comprada"));
  const valorUnitario = roundToCurrency(getItemModalNumericValue("valor_unitario"));
  const quantidadeBase = quantidadeComprada > 0 ? quantidadeComprada : quantidadePlanejada;
  const valorPlanejado = roundToCurrency(valorUnitario * quantidadePlanejada);
  const valorTotal = roundToCurrency(valorUnitario * quantidadeBase);
  return {
    quantidadePlanejada,
    quantidadeComprada,
    valorUnitario,
    valorPlanejado,
    valorTotal,
  };
}

function updateItemModalTotals() {
  if (!dom.itemCompraForm) return;
  
  // Verifica se é perfil COZINHA ou BAR - não atualiza totais para esses perfis
  const perfilAtual = currentUser && currentUser.perfil ? currentUser.perfil.toString().trim().toUpperCase() : "";
  const isCozinhaOuBar = perfilAtual === "COZINHA" || perfilAtual === "BAR" || perfilAtual === "ATENDENTE";
  
  if (isCozinhaOuBar) {
    const campoTotal = dom.itemCompraForm.elements.valor_total;
    if (campoTotal) {
      campoTotal.value = "0.00";
    }
    return { quantidadePlanejada: 0, quantidadeComprada: 0, valorUnitario: 0, valorPlanejado: 0, valorTotal: 0 };
  }
  
  const totais = computeItemModalTotals();
  const campoTotal = dom.itemCompraForm.elements.valor_total;
  if (campoTotal) {
    campoTotal.value = formatNumber(totais.valorTotal, 2);
  }
  return totais;
}

async function submitItemCompra(event) {
  event.preventDefault();
  if (!dom.itemCompraForm) return;
  
  if (!state.listaCompraAtual) {
    showToast("Erro: nenhuma lista selecionada.", "error");
    return;
  }
  
  // Verifica se pode adicionar ou editar
  const isEditando = listaCompraItemEdicaoId;
  
  if (isEditando) {
    // Se for editar, verifica permissão de edição
    if (!listaPermiteEdicao()) {
      showToast("Lista finalizada ou sem permissao para alterar.", "warning");
      return;
    }
    // Estoquista e Cozinha não podem editar itens existentes
    if (canOnlyCreateAndAddItems()) {
      showToast("Você só pode adicionar novos itens, não editar itens existentes.", "warning");
      return;
    }
  } else {
    // Se for adicionar, verifica permissão de adicionar
    if (!listaPermiteAdicionarItens()) {
      showToast("Você não tem permissão para adicionar itens nesta lista.", "warning");
      return;
    }
  }
  
  const formData = new FormData(dom.itemCompraForm);
  const payload = Object.fromEntries(formData.entries());
  
  // Adiciona lista_id
  payload.lista_id = state.listaCompraAtual.id;
  
  // Verifica se é perfil COZINHA ou BAR
  const perfilAtual = currentUser && currentUser.perfil ? currentUser.perfil.toString().trim().toUpperCase() : "";
  const isCozinhaOuBar = perfilAtual === "COZINHA" || perfilAtual === "BAR" || perfilAtual === "ATENDENTE";
  
  // Calcula os totais
  const totais = computeItemModalTotals();
  payload.quantidade_planejada = Number.isFinite(totais.quantidadePlanejada) ? totais.quantidadePlanejada : 0;
  
  // Para COZINHA e BAR, os campos de valores são zerados
  if (isCozinhaOuBar) {
    payload.quantidade_comprada = 0;
    payload.valor_unitario = 0;
    payload.valor_planejado = 0;
    payload.valor_total = 0;
  } else {
    payload.quantidade_comprada = Number.isFinite(totais.quantidadeComprada) ? totais.quantidadeComprada : 0;
    payload.valor_unitario = roundToCurrency(totais.valorUnitario);
    payload.valor_planejado = totais.valorPlanejado;
    payload.valor_total = totais.valorTotal;
  }
  
  // Converte tipos
  payload.produto_id = payload.produto_id ? Number(payload.produto_id) : null;
  payload.estabelecimento_id = payload.estabelecimento_id ? Number(payload.estabelecimento_id) : null;
  
  try {
    if (listaCompraItemEdicaoId) {
      await fetchJSON(`/itens/${listaCompraItemEdicaoId}`, {
        method: "PUT",
        body: JSON.stringify(payload),
      });
      showToast("Item atualizado.", "success");
    } else {
      await fetchJSON("/itens", {
        method: "POST",
        body: JSON.stringify(payload),
      });
      showToast("Item adicionado.", "success");
    }
    toggleModal(dom.itemCompraModal, false);
    await selecionarListaCompra(state.listaCompraAtual.id, true);
  } catch (error) {
    showToast(error.message, "error");
  }
}

async function removerItemLista(itemId) {
  if (!state.listaCompraAtual || !itemId) return;
  if (!listaPermiteEdicao()) {
    showToast("Lista finalizada ou sem permissao para alterar.", "warning");
    return;
  }
  if (!window.confirm("Remover este item da lista?")) return;
  try {
    await fetchJSON(`/itens/${itemId}`, { method: "DELETE" });
    showToast("Item removido.", "success");
    await selecionarListaCompra(state.listaCompraAtual.id, true);
  } catch (error) {
    showToast(error.message, "error");
  }
}

function abrirEstabelecimentoModal(est = null) {
  if (!canManageCompras()) {
    showToast("Sem permissão para gerenciar estabelecimentos.", "warning");
    return;
  }
  listaCompraEstabelecimentoEdicaoId = est ? est.id : null;
  dom.estabelecimentoCompraForm?.reset();
  dom.estabelecimentoCompraModalTitle.textContent = est ? "Editar estabelecimento" : "Adicionar estabelecimento";
  if (est && dom.estabelecimentoCompraForm) {
    dom.estabelecimentoCompraForm.elements.id.value = est.id || "";
    dom.estabelecimentoCompraForm.elements.nome.value = est.nome || "";
    dom.estabelecimentoCompraForm.elements.localizacao.value = est.localizacao || "";
    dom.estabelecimentoCompraForm.elements.forma_pagamento.value = est.forma_pagamento || "";
    dom.estabelecimentoCompraForm.elements.observacoes.value = est.observacoes || "";
    dom.estabelecimentoCompraForm.elements.latitude.value = est.latitude ?? "";
    dom.estabelecimentoCompraForm.elements.longitude.value = est.longitude ?? "";
  }
  toggleModal(dom.estabelecimentoCompraModal, true);
}

async function submitEstabelecimentoCompra(event) {
  event.preventDefault();
  if (!dom.estabelecimentoCompraForm) return;
  
  if (!state.listaCompraAtual || !state.listaCompraAtual.id) {
    showToast("Erro: nenhuma lista selecionada.", "error");
    return;
  }
  
  const formData = new FormData(dom.estabelecimentoCompraForm);
  const payload = Object.fromEntries(formData.entries());
  payload.latitude = payload.latitude ? Number(payload.latitude) : null;
  payload.longitude = payload.longitude ? Number(payload.longitude) : null;
  
  const listaId = state.listaCompraAtual.id;
  
  try {
    if (listaCompraEstabelecimentoEdicaoId) {
      await fetchJSON(`/listas/${listaId}/estabelecimentos/${listaCompraEstabelecimentoEdicaoId}`, {
        method: "PUT",
        body: JSON.stringify(payload),
      });
      showToast("Estabelecimento atualizado.", "success");
    } else {
      await fetchJSON(`/listas/${listaId}/estabelecimentos`, {
        method: "POST",
        body: JSON.stringify(payload),
      });
      showToast("Estabelecimento adicionado.", "success");
    }
    toggleModal(dom.estabelecimentoCompraModal, false);
    // Recarrega a lista para atualizar os estabelecimentos
    await selecionarListaCompra(listaId, true);
  } catch (error) {
    showToast(error.message, "error");
  }
}

async function loadEstabelecimentosGlobais() {
  try {
    state.estabelecimentosGlobais = await fetchJSON("/estabelecimentos-globais");
    renderListaCompraEstabelecimentos(state.estabelecimentosGlobais);
    // Atualizar selects de estabelecimentos
    if (dom.itemCompraForm?.elements.estabelecimento_id) {
      const currentValue = dom.itemCompraForm.elements.estabelecimento_id.value;
      dom.itemCompraForm.elements.estabelecimento_id.innerHTML = buildEstabelecimentoOptions(currentValue);
    }
  } catch (error) {
    showToast(error.message || "Falha ao carregar estabelecimentos.", "error");
  }
}

async function deletarEstabelecimentoLista(estId) {
  if (!window.confirm("Deseja realmente deletar este estabelecimento?")) return;
  
  if (!state.listaCompraAtual || !state.listaCompraAtual.id) {
    showToast("Erro: nenhuma lista selecionada.", "error");
    return;
  }
  
  const listaId = state.listaCompraAtual.id;
  
  try {
    await fetchJSON(`/listas/${listaId}/estabelecimentos/${estId}`, { method: "DELETE" });
    showToast("Estabelecimento deletado.", "success");
    // Recarrega a lista para atualizar os estabelecimentos
    await selecionarListaCompra(listaId, true);
  } catch (error) {
    showToast(error.message, "error");
  }
}


function abrirFinalizarListaModal() {
  if (!state.listaCompraAtual) {
    showToast("Selecione uma lista primeiro.", "warning");
    return;
  }
  if (!listaPermiteFinalizar()) {
    showToast("Sem permissão para finalizar esta lista.", "warning");
    return;
  }
  const status = (state.listaCompraAtual.status || "").toUpperCase();
  if (status === "FINALIZADA") {
    showToast("Esta lista já está finalizada.", "info");
    return;
  }
  finalizarListaArquivos = [];
  dom.finalizarListaForm?.reset();
  if (dom.finalizarListaForm?.elements.observacoes) {
    dom.finalizarListaForm.elements.observacoes.value = state.listaCompraAtual.observacoes || "";
  }
  toggleModal(dom.finalizarListaModal, true);
}

async function submitFinalizarLista(event) {
  event.preventDefault();
  event.stopPropagation();
  
  console.log("submitFinalizarLista chamado");
  
  if (!state.listaCompraAtual) {
    console.error("Nenhuma lista selecionada");
    showToast("Nenhuma lista selecionada.", "error");
    return;
  }
  
  if (!dom.finalizarListaForm) {
    console.error("Formulário não encontrado");
    showToast("Formulário não encontrado.", "error");
    return;
  }
  
  const listaId = state.listaCompraAtual.id;
  console.log("Finalizando lista ID:", listaId);
  
  // Validações básicas
  const status = (state.listaCompraAtual.status || "").toUpperCase();
  if (status === "FINALIZADA") {
    showToast("Esta lista já está finalizada.", "info");
    toggleModal(dom.finalizarListaModal, false);
    return;
  }
  
  // Verifica permissão de forma mais simples
  if (!canManageCompras()) {
    showToast("Sem permissão para finalizar listas.", "warning");
    toggleModal(dom.finalizarListaModal, false);
    return;
  }
  
  const formData = new FormData(dom.finalizarListaForm);
  const observacoes = formData.get("observacoes") || "";
  
  const submitButton = dom.finalizarListaForm?.querySelector('button[type="submit"]');
  const originalText = submitButton?.textContent || "Finalizar";
  
  if (submitButton) {
    submitButton.disabled = true;
    submitButton.textContent = "Finalizando...";
  }
  
  try {
    const payloadData = {
      observacoes: observacoes || null
    };
    
    console.log("Enviando requisição para finalizar lista:", {
      url: `/listas/${listaId}/finalizar`,
      method: "PUT",
      payload: payloadData
    });
    
    const data = await fetchJSON(`/listas/${listaId}/finalizar`, {
      method: "PUT",
      body: JSON.stringify(payloadData)
    });
    
    console.log("Resposta recebida:", data);
    
    if (!data || !data.id) {
      throw new Error("Resposta inválida do servidor");
    }
    
    // Atualiza o estado
    state.listaCompraAtual = data;
    const resumo = resumoListaDetalhe(data);
    state.listasCompras = state.listasCompras.map((lista) => 
      (Number(lista.id) === Number(listaId) ? { ...lista, ...resumo } : lista)
    );
    
    // Re-renderiza tudo
    renderListasCompras(state.listasCompras);
    renderListaCompraDetalhes(data);
    
    showToast("✅ Lista finalizada com sucesso!", "success");
    
    // Recarrega a lista para garantir que está atualizada
    await selecionarListaCompra(listaId, true);
    
  } catch (error) {
    console.error("Erro ao finalizar lista:", error);
    const errorMessage = error.message || "Falha ao finalizar lista. Verifique o console para mais detalhes.";
    showToast(errorMessage, "error");
  } finally {
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.textContent = originalText;
    }
    toggleModal(dom.finalizarListaModal, false);
  }
}

async function lancarListaNoEstoque() {
  console.log("lancarListaNoEstoque chamado");
  
  if (!state.listaCompraAtual) {
    console.error("Nenhuma lista selecionada");
    showToast("Nenhuma lista selecionada.", "error");
    return;
  }
  
  // Verifica permissão para lançar no estoque
  if (!listaPermiteLancarEstoque()) {
    const perfil = (currentUser?.perfil || "").toString().trim().toUpperCase();
    if (perfil === "COZINHA" || perfil === "BAR" || perfil === "ATENDENTE") {
      showToast("Você não tem permissão para lançar no estoque.", "warning");
    } else {
      showToast("Sem permissão para lançar no estoque.", "warning");
    }
    return;
  }
  
  const status = (state.listaCompraAtual.status || "").toUpperCase();
  if (status !== "FINALIZADA") {
    showToast("Finalize a lista antes de lançar no estoque.", "info");
    return;
  }
  
  if (state.listaCompraAtual.estoque_lancado_em) {
    showToast("Lista já lançada no estoque.", "info");
    return;
  }
  
  if (!currentUser?.id) {
    showToast("Usuário não identificado. Faça login novamente.", "error");
    return;
  }
  
  // ✅ 1. Verificar se os itens realmente estão sendo coletados antes do envio
  // Processa TODOS os itens com quantidade comprada > 0 (não apenas os com status COMPRADO)
  const itens = state.listaCompraAtual.itens || [];
  const itensParaLancar = itens.filter((item) => {
    const qtdComprada = parseFloat(item.quantidade_comprada || 0);
    return qtdComprada > 0;
  });
  
  console.log("Itens a lançar:", itensParaLancar);
  console.log("Total de itens na lista:", itens.length);
  console.log("Total de itens com quantidade comprada:", itensParaLancar.length);
  
  if (itensParaLancar.length === 0) {
    console.error("Nenhum item com quantidade comprada encontrado na lista");
    showToast("Não há itens com quantidade comprada para lançar no estoque.", "warning");
    return;
  }
  
  // ✅ 2. Garantir que os itens tenham todos os campos necessários
  const itensInvalidos = [];
  itensParaLancar.forEach((item, index) => {
    const camposFaltando = [];
    if (!item.id) camposFaltando.push("id");
    if (item.quantidade_comprada === undefined || item.quantidade_comprada === null || item.quantidade_comprada <= 0) {
      camposFaltando.push("quantidade_comprada");
    }
    if (!item.unidade) camposFaltando.push("unidade");
    if (item.valor_unitario === undefined || item.valor_unitario === null) {
      camposFaltando.push("valor_unitario");
    }
    
    if (camposFaltando.length > 0) {
      itensInvalidos.push({
        index,
        itemId: item.id,
        camposFaltando
      });
    }
  });
  
  if (itensInvalidos.length > 0) {
    console.error("Itens com campos faltando:", itensInvalidos);
    showToast(`Alguns itens estão incompletos. Verifique o console para detalhes.`, "error");
    return;
  }
  
  console.log("✅ Todos os itens têm os campos necessários:", itensParaLancar.map(item => ({
    id: item.id,
    quantidade: item.quantidade_comprada,
    unidade: item.unidade,
    preco: item.valor_unitario,
    status: item.status
  })));
  
  const btn = dom.listaCompraLancarEstoque;
  if (btn) {
    btn.dataset.loading = "1";
    btn.disabled = true;
    btn.textContent = "Lançando...";
  }
  
  try {
    console.log("Enviando requisição para lançar no estoque:", {
      listaId: state.listaCompraAtual.id,
      usuarioId: currentUser.id,
      quantidadeItens: itensParaLancar.length
    });
    
    const resposta = await fetchJSON(`/listas/${state.listaCompraAtual.id}/estoque`, {
      method: "POST",
      body: JSON.stringify({ usuario_id: currentUser.id }),
    });
    
    console.log("Resposta recebida:", resposta);
    
    if (!resposta || !resposta.id) {
      throw new Error("Resposta inválida do servidor");
    }
    
    state.listaCompraAtual = resposta;
    const resumo = resumoListaDetalhe(resposta);
    state.listasCompras = state.listasCompras.map((lista) => 
      (Number(lista.id) === Number(resposta.id) ? { ...lista, ...resumo } : lista)
    );
    
    renderListasCompras(state.listasCompras);
    renderListaCompraDetalhes(resposta);
    showToast("✅ Itens lançados no estoque com sucesso!", "success");
    
    // ✅ Aguarda um pouco para garantir que o commit foi finalizado no banco
    await new Promise(resolve => setTimeout(resolve, 500));
    
    console.log("🔄 Atualizando todas as telas relacionadas após lançamento...");
    
    // ✅ Atualiza TODAS as telas relacionadas para garantir que os produtos apareçam
    const atualizacoes = [
      // Dashboard - atualiza cards e movimentações
      loadDashboard().catch((err) => {
        console.error("Erro ao atualizar dashboard:", err);
        showToast(err?.message || "Falha ao atualizar dashboard.", "error");
      }),
      // Lotes - mostra os novos lotes criados
      loadLotes().catch((err) => {
        console.error("Erro ao atualizar lotes:", err);
        showToast(err?.message || "Falha ao atualizar lotes.", "error");
      }),
      // Produtos - atualiza lista de produtos
      loadProdutos().catch((err) => {
        console.error("Erro ao atualizar produtos:", err);
      }),
      // Estoque - atualiza visualização de estoque
      loadEstoqueProdutos().catch((err) => {
        console.error("Erro ao atualizar estoque:", err);
      }),
    ];
    await Promise.all(atualizacoes);
    
    // ✅ Recarrega movimentações com cache busting forçado
    console.log("Recarregando movimentações após lançamento...");
    await loadMovimentacoesDetalhadas({}, { refreshDashboard: true }).catch((err) => {
      console.error("Erro ao atualizar movimentações:", err);
    });
    
    // ✅ Aguarda mais um pouco e força renderização novamente
    await new Promise(resolve => setTimeout(resolve, 300));
    
    // Força renderização imediata das movimentações do dashboard
    if (state.movimentacoesRecentes && state.movimentacoesRecentes.length > 0) {
      console.log("Renderizando", state.movimentacoesRecentes.length, "movimentações no dashboard");
      renderMovimentacoesDashboard(state.movimentacoesRecentes);
    } else {
      // Se ainda não tiver, tenta recarregar novamente
      console.log("Movimentações ainda não carregadas, tentando novamente...");
      const movs = await fetchJSON(`/movimentacoes?limit=50&_=${Date.now()}`).catch(() => []);
      if (Array.isArray(movs) && movs.length > 0) {
        state.movimentacoesRecentes = sortMovimentacoes(movs);
        renderMovimentacoesDashboard(state.movimentacoesRecentes);
      }
    }
    
    // ✅ Força atualização adicional após 1 segundo para garantir que tudo apareça
    setTimeout(async () => {
      console.log("🔄 Atualização final das telas...");
      await Promise.all([
        loadDashboard().catch(() => {}),
        loadLotes().catch(() => {}),
        loadProdutos().catch(() => {}),
        loadEstoqueProdutos().catch(() => {}),
      ]);
    }, 1000);
    if (possuiFiltros) {
      await loadMovimentacoesDetalhadas(movFiltrosAtuais).catch((err) => {
        console.error("Erro ao atualizar movimentações com filtros:", err);
      });
    }
    if (relFiltrosAtuais) {
      await loadRelatorio(relFiltrosAtuais).catch((err) => {
        console.error("Erro ao atualizar relatórios:", err);
      });
    }
    
    // Recarrega a lista para garantir que está atualizada
    await selecionarListaCompra(state.listaCompraAtual.id, true);
    
  } catch (error) {
    console.error("Erro ao lançar no estoque:", error);
    showToast(error.message || "Falha ao lançar no estoque. Verifique o console para mais detalhes.", "error");
  } finally {
    if (btn) {
      btn.dataset.loading = "0";
      btn.disabled = false;
      btn.textContent = "Lançar no Estoque";
    }
    if (state.listaCompraAtual) {
      renderListaCompraDetalhes(state.listaCompraAtual);
    }
  }
}

async function gerarPdfLista() {
  if (!state.listaCompraAtual) {
    showToast("Nenhuma lista selecionada.", "error");
    return;
  }
  
  const lista = state.listaCompraAtual;
  const status = (lista.status || "").toUpperCase();
  
  // Verifica se é perfil COZINHA ou BAR - podem gerar PDF mesmo sem lista finalizada
  const perfilAtual = currentUser && currentUser.perfil ? currentUser.perfil.toString().trim().toUpperCase() : "";
  const isCozinhaOuBar = perfilAtual === "COZINHA" || perfilAtual === "BAR" || perfilAtual === "ATENDENTE";
  
  // COZINHA e BAR podem gerar PDF em qualquer status, outros perfis precisam que esteja finalizada
  if (status !== "FINALIZADA" && !isCozinhaOuBar) {
    showToast("Finalize a lista antes de gerar o PDF.", "info");
    return;
  }
  
  const agora = new Date();
  const titulo = `Lista de Compras - ${escapeHtml(lista.nome || "Sem nome")}`;
  const logoMarkup = await getReportLogoMarkup();
  
  const estilo = `
    <style>
      body { font-family: Arial, sans-serif; color: #111; padding: 24px; }
      .report-header { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
      .report-header img, .report-header object { height: 64px; width: auto; max-width: 120px; }
      .report-header object { border: none; }
      .report-header h1 { margin: 0; font-size: 24px; }
      .meta { margin-bottom: 24px; line-height: 1.6; }
      .meta strong { display: inline-block; min-width: 140px; }
      table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
      th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 12px; text-align: left; }
      th { background: #f0f4f8; font-weight: bold; }
      .section-title { font-size: 16px; margin: 24px 0 8px; font-weight: bold; }
      .resumo { display: flex; gap: 24px; margin-bottom: 24px; }
      .resumo-item { flex: 1; padding: 12px; background: #f0f4f8; border-radius: 4px; }
      .resumo-item strong { display: block; margin-bottom: 4px; }
      .resumo-item span { font-size: 18px; font-weight: bold; }
    </style>
  `;
  
  const metaHtml = `
    <div class="meta">
      <strong>Gerado em:</strong> ${agora.toLocaleString("pt-BR")}<br />
      <strong>Lista:</strong> ${escapeHtml(lista.nome || "--")}<br />
      <strong>Unidade:</strong> ${escapeHtml(lista.unidade_nome || "--")}<br />
      <strong>Responsável:</strong> ${escapeHtml(lista.responsavel_nome || "--")}<br />
      <strong>Status:</strong> ${escapeHtml(lista.status || "--")}<br />
      ${lista.observacoes ? `<strong>Observações:</strong> ${escapeHtml(lista.observacoes)}<br />` : ""}
    </div>
  `;
  
  const resumoHtml = `
    <div class="resumo">
      <div class="resumo-item">
        <strong>Total Planejado</strong>
        <span>${formatCurrency(lista.total_planejado || 0)}</span>
      </div>
      <div class="resumo-item">
        <strong>Total Realizado</strong>
        <span>${formatCurrency(lista.total_realizado || 0)}</span>
      </div>
      <div class="resumo-item">
        <strong>Itens Comprados</strong>
        <span>${Number((lista.itens || []).filter((item) => (item.status || "").toUpperCase() === "COMPRADO").length)} / ${Number(lista.itens?.length || 0)}</span>
      </div>
    </div>
  `;
  
  const itens = lista.itens || [];
  const itensHtml = itens.length
    ? `
      <h2 class="section-title">Itens da Lista</h2>
      <table>
        <thead>
          <tr>
            <th>Produto</th>
            <th>Planejado</th>
            <th>Comprado</th>
            <th>Preço Unitário</th>
            <th>Total</th>
            <th>Status</th>
            <th>Estabelecimento</th>
            <th>Observações</th>
          </tr>
        </thead>
        <tbody>
          ${itens
            .map((item) => {
              const quantidadePlanejada = `${formatNumber(item.quantidade_planejada || 0, 3)} ${escapeHtml(item.unidade || "")}`.trim();
              const quantidadeComprada = `${formatNumber(item.quantidade_comprada || 0, 3)} ${escapeHtml(item.unidade || "")}`.trim();
              const statusLabel = (item.status || "").toUpperCase() === "COMPRADO" ? "Comprado" : 
                                  (item.status || "").toUpperCase() === "CANCELADO" ? "Cancelado" : "Pendente";
              return `
                <tr>
                  <td>${escapeHtml(item.produto_nome || "--")}</td>
                  <td>${quantidadePlanejada}</td>
                  <td>${quantidadeComprada}</td>
                  <td>R$ ${formatUnitValue(item.valor_unitario || 0)}</td>
                  <td>${formatCurrency(item.valor_total || 0)}</td>
                  <td>${escapeHtml(statusLabel)}</td>
                  <td>${escapeHtml(item.estabelecimento_nome || "--")}</td>
                  <td>${escapeHtml(item.observacoes || "--")}</td>
                </tr>`;
            })
            .join("")}
        </tbody>
      </table>
    `
    : "<p>Nenhum item cadastrado nesta lista.</p>";
  
  const conteudo = `
    <!DOCTYPE html>
    <html lang="pt-BR">
      <head>
        <meta charset="utf-8" />
        <title>${titulo}</title>
        ${estilo}
      </head>
      <body>
        <div class="report-header">
          ${logoMarkup}
          <h1>${titulo}</h1>
        </div>
        ${metaHtml}
        ${resumoHtml}
        ${itensHtml}
      </body>
    </html>
  `;
  
  const iframe = document.createElement("iframe");
  iframe.style.position = "fixed";
  iframe.style.right = "0";
  iframe.style.bottom = "0";
  iframe.style.width = "0";
  iframe.style.height = "0";
  iframe.style.border = "0";
  iframe.style.visibility = "hidden";
  document.body.appendChild(iframe);
  
  let timeoutId = null;
  
  const cleanup = () => {
    if (timeoutId) {
      clearTimeout(timeoutId);
      timeoutId = null;
    }
    try {
      if (iframe.contentWindow) {
        iframe.contentWindow.onafterprint = null;
      }
    } catch (err) {
      /* noop */
    }
    if (iframe.parentNode) {
      iframe.parentNode.removeChild(iframe);
    }
  };
  
  iframe.onload = () => {
    const win = iframe.contentWindow;
    if (!win) {
      cleanup();
      showToast("Não foi possível preparar o PDF.", "error");
      return;
    }
    win.onafterprint = cleanup;
    win.focus();
    try {
      if (typeof win.print === "function") {
        win.print();
      } else {
        cleanup();
        showToast("Seu navegador não suportou a impressão.", "error");
      }
    } catch (err) {
      cleanup();
      showToast("Falha ao acionar a impressão.", "error");
    }
  };
  
  iframe.onerror = () => {
    cleanup();
    showToast("Não foi possível gerar o PDF.", "error");
  };
  
  const doc = iframe.contentDocument || iframe.contentWindow?.document;
  if (!doc) {
    cleanup();
    showToast("Não foi possível gerar o PDF.", "error");
    return;
  }
  doc.open();
  doc.write(conteudo);
  doc.close();
  
  timeoutId = setTimeout(() => {
    if (!document.body.contains(iframe)) return;
    cleanup();
    showToast("Falha ao imprimir. Verifique as configurações do navegador.", "error");
  }, 60000);
  showToast("Lista de compras enviada para impressão/arquivo PDF.", "success");
}

function populateSelect(select, options, emptyLabel) {
  if (!select) {
    console.warn("populateSelect: select não fornecido");
    return;
  }
  
  if (!options && emptyLabel) {
    select.innerHTML = `<option value="">${escapeHtml(emptyLabel)}</option>`;
    return;
  }
  
  const previousValue = select.value;
  const html = emptyLabel ? `<option value="">${escapeHtml(emptyLabel)}</option>${options}` : options;
  select.innerHTML = html;
  
  if (previousValue && Array.from(select.options).some((opt) => opt.value === previousValue)) {
    select.value = previousValue;
  }
  
  console.log(`populateSelect: ${select.name || 'select'} atualizado com ${select.options.length} opções`);
}

// Vincula um input de busca a um select: digitar filtra as opções visíveis
function bindBuscaSelect(inputId, selectId) {
  const input = document.getElementById(inputId);
  const select = document.getElementById(selectId);
  if (!input || !select || input._buscaBound) return;
  input._buscaBound = true;

  // Guarda todas as opções originais (exceto a vazia) para poder restaurar
  function capturarOpcoes() {
    input._todasOpcoes = Array.from(select.options)
      .filter((o) => o.value)
      .map((o) => ({ value: o.value, text: o.text }));
  }

  // Captura assim que o select for populado (pode ser chamado depois)
  const observer = new MutationObserver(() => {
    if (select.options.length > 1 && !input._todasOpcoes?.length) {
      capturarOpcoes();
      observer.disconnect();
    }
  });
  observer.observe(select, { childList: true });

  // Captura imediata se já estiver populado
  if (select.options.length > 1) {
    capturarOpcoes();
    observer.disconnect();
  }

  input.addEventListener("input", () => {
    const termo = input.value.trim().toLowerCase();
    const valorAtual = select.value;
    const opcaoVazia = select.options[0]; // "Todos" ou "Selecione..."

    // Reconstrói as opções do select filtrando pelo termo
    const todas = input._todasOpcoes || [];
    const filtradas = termo ? todas.filter((o) => o.text.toLowerCase().includes(termo)) : todas;

    select.innerHTML = "";
    select.appendChild(opcaoVazia.cloneNode ? opcaoVazia.cloneNode(true) : new Option(opcaoVazia.text, ""));
    filtradas.forEach((o) => {
      select.appendChild(new Option(o.text, o.value));
    });

    // Mantém o valor selecionado se ainda estiver na lista filtrada
    if (filtradas.some((o) => o.value === valorAtual)) {
      select.value = valorAtual;
    } else {
      select.value = "";
    }

    // Abre o select automaticamente se houver resultados
    if (filtradas.length > 0 && termo) {
      select.size = Math.min(filtradas.length + 1, 8);
    } else {
      select.size = 1;
    }
  });

  // Ao selecionar, fecha o select (volta para size 1) e limpa o input de busca
  select.addEventListener("change", () => {
    select.size = 1;
    input.value = "";
  });

  // Se clicar fora, fecha o select expandido
  document.addEventListener("click", (e) => {
    if (e.target !== input && e.target !== select) {
      select.size = 1;
    }
  });
}

function refreshProdutoSelects() {
  if (!state.produtos || !Array.isArray(state.produtos) || state.produtos.length === 0) {
    console.warn("refreshProdutoSelects: Nenhum produto disponível no state");
    return;
  }
  
  const ativos = state.produtos.filter((produto) => Number(produto.ativo) === 1);
  console.log("refreshProdutoSelects: Atualizando selects com", ativos.length, "produtos ativos");
  
  const options = ativos.map((produto) => `<option value="${produto.id}">${escapeHtml(produto.nome)}</option>`).join("");

  // Filtro por digitação nos selects de produto
  populateSelect(dom.lotesFiltroProduto, options, "Todos");
  bindBuscaSelect("lotesFiltroProdutoBusca", "lotesFiltroProduto");

  populateSelect(dom.movFiltroProduto, options, "Todos");
  populateSelect(dom.relatorioProduto, options, "Todos");
  
  // Atualiza select de produtos no formulário de entrada
  const entradaProdutoSelect = dom.entradaForm?.querySelector('select[name="produto_id"]');
  if (entradaProdutoSelect) {
    populateSelect(entradaProdutoSelect, options, "Selecione");
    bindBuscaSelect("entradaProdutoBusca", "entradaProdutoSelect");
    console.log("Select de produtos do formulário de entrada atualizado");
  } else {
    console.warn("Select de produtos do formulário de entrada não encontrado");
  }
  if (dom.itemCompraForm?.elements.produto_id) {
    populateSelect(dom.itemCompraForm.elements.produto_id, options, "Selecione");
    bindBuscaSelect("itemCompraProdutoBusca", "itemCompraProdutoSelect");
  }
  // estoqueProdutoSelect agora é um hidden input — autocomplete gerenciado por initEstoqueProdutoAutocomplete
  if (dom.loteForm?.elements.produto_id) {
    resetLoteProdutoSelect();
    const unidadeAtual = Number(dom.loteForm.elements.unidade_id?.value);
    if (Number.isFinite(unidadeAtual) && unidadeAtual > 0) {
      handleLoteUnidadeChange();
    }
  }
  if (Number(dom.saidaOrigemSelect?.value)) {
    handleSaidaOrigemChange();
  }
}

function resetLoteProdutoSelect(message = "Selecione a unidade primeiro") {
  loteProdutosRequestId += 1;
  const select = dom.loteForm?.elements?.produto_id;
  if (!select) return;
  select.innerHTML = `<option value="">${escapeHtml(message)}</option>`;
  select.value = "";
  select.disabled = true;
}

async function handleLoteUnidadeChange() {
  const form = dom.loteForm;
  if (!form) return;
  const produtoSelect = form.elements?.produto_id;
  const unidadeSelect = form.elements?.unidade_id;
  if (!produtoSelect || !unidadeSelect) return;
  const unidadeId = Number(unidadeSelect.value);
  if (!Number.isFinite(unidadeId) || unidadeId <= 0) {
    resetLoteProdutoSelect();
    return;
  }
  loteProdutosRequestId += 1;
  const requestId = loteProdutosRequestId;
  const previousValue = produtoSelect.value;
  produtoSelect.disabled = true;
  produtoSelect.innerHTML = '<option value="">Carregando produtos...</option>';
  try {
    const produtos = await fetchJSON(`/produtos?unidade_id=${encodeURIComponent(unidadeId)}`);
    if (loteProdutosRequestId !== requestId) return;
    const ativos = Array.isArray(produtos) ? produtos.filter((produto) => Number(produto.ativo ?? 1) === 1) : [];
    if (!ativos.length) {
      resetLoteProdutoSelect("Nenhum produto disponivel");
      return;
    }
    const options = ativos
      .map(
        (produto) =>
          `<option value="${escapeHtml(String(produto.id))}">${escapeHtml(produto.nome || `Produto ${produto.id}`)}</option>`,
      )
      .join("");
    produtoSelect.innerHTML = `<option value="">Selecione o produto</option>${options}`;
    produtoSelect.disabled = false;
    if (previousValue && ativos.some((produto) => String(produto.id) === String(previousValue))) {
      produtoSelect.value = previousValue;
    }
  } catch (err) {
    if (loteProdutosRequestId !== requestId) return;
    resetLoteProdutoSelect("Falha ao carregar produtos");
    showToast(err?.message || "Falha ao carregar produtos da unidade.", "error");
  }
}

function resetSaidaProdutoSelect(message = "Selecione a unidade de origem") {
  const select = dom.saidaProdutoSelect || dom.saidaForm?.querySelector('select[name="produto_id"]');
  if (!select) return;
  select.innerHTML = `<option value="">${escapeHtml(message)}</option>`;
  select.value = "";
  select.disabled = true;
  const buscaInput = document.getElementById("saidaProdutoBusca");
  if (buscaInput) {
    buscaInput.disabled = true;
    buscaInput.value = "";
    buscaInput._buscaBound = false;
    buscaInput._todasOpcoes = [];
  }
}

function resetEntradaLocalSelect(message = "Selecione a unidade") {
  const select = dom.entradaLocalSelect || dom.entradaForm?.querySelector('select[name="local_id"]');
  if (!select) return;
  select.innerHTML = `<option value="">${escapeHtml(message)}</option>`;
  select.value = "";
  select.disabled = true;
}

async function handleSaidaOrigemChange() {
  const select = dom.saidaProdutoSelect || dom.saidaForm?.querySelector('select[name="produto_id"]');
  if (!select) return;
  const unidadeIdRaw = dom.saidaOrigemSelect?.value;
  const unidadeId = Number(unidadeIdRaw);
  if (!Number.isFinite(unidadeId) || unidadeId <= 0) {
    resetSaidaProdutoSelect();
    return;
  }
  saidaProdutosRequestId += 1;
  const requestId = saidaProdutosRequestId;
  select.disabled = true;
  select.innerHTML = '<option value="">Carregando produtos...</option>';
  try {
    const produtos = await fetchJSON(`/produtos?unidade_id=${encodeURIComponent(unidadeId)}&com_estoque=1`);
    if (saidaProdutosRequestId !== requestId) return;
    if (!Array.isArray(produtos) || !produtos.length) {
      resetSaidaProdutoSelect("Nenhum produto com estoque disponivel");
      return;
    }
    const options = produtos
      .filter((produto) => Number(produto.ativo ?? 1) === 1)
      .map((produto) => `<option value="${produto.id}">${escapeHtml(produto.nome)}</option>`)
      .join("");
    populateSelect(select, options, "Selecione");
    select.disabled = false;
    const buscaInput = document.getElementById("saidaProdutoBusca");
    if (buscaInput) {
      buscaInput.disabled = false;
      buscaInput.value = "";
    }
    bindBuscaSelect("saidaProdutoBusca", "saidaProdutoSelect");
  } catch (err) {
    if (saidaProdutosRequestId !== requestId) return;
    resetSaidaProdutoSelect("Falha ao carregar produtos");
    showToast(err?.message || "Falha ao carregar produtos da unidade.", "error");
  }
}

async function handleSaidaProdutoChange() {
  const loteWrapper = dom.saidaLoteWrapper;
  const loteSelect = dom.saidaLoteSelect;
  if (!loteWrapper || !loteSelect) return;

  const produtoId = Number(dom.saidaProdutoSelect?.value);
  const unidadeId = Number(dom.saidaOrigemSelect?.value);

  // Esconde wrapper se produto não selecionado
  if (!produtoId || !unidadeId) {
    loteWrapper.classList.add("hidden");
    loteSelect.innerHTML = '<option value="">Selecionar lote disponível…</option><option value="__manual__">✏️ Digitar código manualmente</option>';
    if (dom.saidaLoteManualWrapper) dom.saidaLoteManualWrapper.classList.add("hidden");
    if (dom.saidaLoteManualInput) dom.saidaLoteManualInput.value = "";
    return;
  }

  loteWrapper.classList.remove("hidden");
  loteSelect.disabled = true;
  loteSelect.innerHTML = '<option value="">Carregando lotes…</option>';

  try {
    const lotes = await fetchJSON(`/lotes?produto_id=${encodeURIComponent(produtoId)}&unidade_id=${encodeURIComponent(unidadeId)}&status=ATIVO`);
    const lista = Array.isArray(lotes) ? lotes : (lotes?.data ?? []);

    const options = lista
      .filter((l) => Number(l.qtd_atual ?? l.quantidade_atual ?? 0) > 0)
      .map((l) => {
        const codigo = l.numero_lote || l.codigo_lote || `Lote #${l.id}`;
        const qtd = Number(l.qtd_atual ?? l.quantidade_atual ?? 0);
        const val = l.data_validade ? ` | val: ${l.data_validade}` : "";
        return `<option value="${escapeHtml(codigo)}">${escapeHtml(codigo)} (qtd: ${qtd}${val})</option>`;
      })
      .join("");

    loteSelect.innerHTML =
      `<option value="">— Automático (FIFO) —</option>` +
      options +
      `<option value="__manual__">✏️ Digitar código manualmente</option>`;
    loteSelect.disabled = false;
  } catch (err) {
    loteSelect.innerHTML =
      `<option value="">— Automático (FIFO) —</option>` +
      `<option value="__manual__">✏️ Digitar código manualmente</option>`;
    loteSelect.disabled = false;
  }
}

async function handleEntradaUnidadeChange() {
  const select = dom.entradaLocalSelect || dom.entradaForm?.querySelector('select[name="local_id"]');
  if (!select) return;
  const unidadeId = Number(dom.entradaUnidadeSelect?.value);
  if (!Number.isFinite(unidadeId) || unidadeId <= 0) {
    resetEntradaLocalSelect();
    return;
  }
  entradaLocaisRequestId += 1;
  const requestId = entradaLocaisRequestId;
  select.disabled = true;
  select.innerHTML = '<option value="">Carregando locais...</option>';
  try {
    const locaisDisponiveis = await ensureLocaisCarregados(false);
    if (entradaLocaisRequestId !== requestId) return;
    populateEntradaLocaisSelect(locaisDisponiveis, unidadeId);
  } catch (err) {
    if (entradaLocaisRequestId !== requestId) return;
    resetEntradaLocalSelect("Falha ao carregar locais");
    showToast(err?.message || "Falha ao carregar locais da unidade.", "error");
  }
}

function refreshUnidadeSelects() {
  const options = state.unidades.map((unidade) => `<option value="${unidade.id}">${escapeHtml(unidade.nome)}</option>`).join("");
  populateSelect(dom.lotesFiltroUnidade, options, "Todas");
  populateSelect(dom.movFiltroUnidade, options, "Todas");
  populateSelect(dom.relatorioUnidade, options, "Todas");
  populateSelect(dom.saidaOrigemSelect, options, "Selecione a unidade");
  populateSelect(dom.saidaDestinoSelect, options, "Selecione a unidade");
  if (dom.produtosForm?.elements.unidade_id) populateSelect(dom.produtosForm.elements.unidade_id, options, "Sem unidade");
  if (dom.loteForm?.elements.unidade_id) populateSelect(dom.loteForm.elements.unidade_id, options, "Selecione a unidade");
  if (dom.usuarioForm) populateSelect(dom.usuarioForm.querySelector('select[name="unidade_id"]'), options, "Sem unidade");
  if (dom.entradaForm) populateSelect(dom.entradaForm.querySelector('select[name="unidade_id"]'), options, "Selecione");
  if (dom.localUnidadeSelect) populateSelect(dom.localUnidadeSelect, options, "Selecione a unidade");
  const proventoFormUnidade = document.getElementById("proventoForm")?.elements?.unidade_id;
  if (proventoFormUnidade) populateSelect(proventoFormUnidade, options, "Selecione");
  populateSelect(document.getElementById("fechamentoUnidade"), options, "Selecione a unidade");
  
  // Não sobrescreve o select da lista de compras se for COZINHA ou BAR criando nova lista
  const perfil = (currentUser?.perfil || "").toString().trim().toUpperCase();
  const isCozinhaOuBar = perfil === "COZINHA" || perfil === "BAR" || perfil === "ATENDENTE";
  const listaCompraSelect = dom.listaCompraForm?.elements.unidade_id;
  const isModalOpen = dom.listaCompraModal && !dom.listaCompraModal.classList.contains("hidden");
  const isNovaLista = !dom.listaCompraForm?.elements.id?.value;
  
  if (listaCompraSelect && !(isCozinhaOuBar && isModalOpen && isNovaLista && listaCompraSelect.disabled)) {
    populateSelect(listaCompraSelect, options, "Selecione");
  }
  
  handleSaidaOrigemChange();
  handleLoteUnidadeChange();
  handleEntradaUnidadeChange();
}

function refreshGerenteSelect(selectedId) {
  const selects = [];
  if (dom.unidadeForm?.elements.gerente_usuario_id) selects.push(dom.unidadeForm.elements.gerente_usuario_id);
  if (dom.unidadeInlineForm?.elements.gerente_usuario_id) selects.push(dom.unidadeInlineForm.elements.gerente_usuario_id);
  if (!selects.length) return;

  const usuariosAtivos = (state.usuarios || []).filter((usuario) => Number(usuario?.ativo ?? 0) === 1);
  const usuariosOrdenados = usuariosAtivos
    .map((usuario) => {
      const nome = (usuario?.nome || usuario?.email || `Usuario ${usuario?.id ?? ""}`).toString();
      return { ...usuario, _nomeOrdenacao: nome };
    })
    .sort((a, b) => a._nomeOrdenacao.localeCompare(b._nomeOrdenacao, "pt-BR"));
  const options = usuariosOrdenados
    .map((usuario) => {
      const rotulo = usuario.nome || usuario._nomeOrdenacao;
      return `<option value="${escapeHtml(String(usuario.id ?? ""))}">${escapeHtml(rotulo)}</option>`;
    })
    .join("");

  selects.forEach((select) => {
    const valorAnterior = selectedId !== undefined ? selectedId : select.value;
    populateSelect(select, options, "Selecione um usuario");
    const alvo = valorAnterior !== undefined && valorAnterior !== null ? String(valorAnterior) : "";
    if (alvo && Array.from(select.options).some((opt) => opt.value === alvo)) {
      select.value = alvo;
    }
  });
}

// --- Rotinas de carregamento principal de dados vindos da API ---
// Função global para atualizar cards - pode ser chamada de qualquer lugar
function atualizarCardsDashboard(dados) {
  const { produtos = [], lotes = [], minimos = {}, perdas = {}, lotesStats = {}, comprasAtivas = 0 } = dados;
  
  // Calcula valores
  const produtosAtivos = Array.isArray(produtos) ? produtos.filter(p => Number(p.ativo) === 1).length : 0;
  const lotes7Dias = Array.isArray(lotes) ? lotes.length : 0;
  const lotes15Dias = Number(lotesStats?.a_vencer ?? 0);
  const lotesVencidos = Number(lotesStats?.vencidos ?? 0);
  const produtosAbaixoMinimo = Array.isArray(minimos?.produtos) ? minimos.produtos.length : Number(minimos?.total ?? 0);
  const perdasQtd = Number(perdas?.quantidade_total ?? 0);
  const perdasRegistros = Number(perdas?.total_registros ?? 0);
  
  // Função auxiliar para atualizar
  function setCardValue(id, value) {
    const el = document.getElementById(id);
    if (el) {
      el.textContent = String(value ?? "0");
      return true;
    }
    return false;
  }
  
  // Atualiza todos os cards
  setCardValue("kpiProdutos", produtosAtivos);
  setCardValue("kpiVencer", lotes7Dias);
  setCardValue("kpiLotesAVencer", lotes15Dias);
  setCardValue("kpiLotesVencidos", lotesVencidos);
  setCardValue("kpiMinimo", produtosAbaixoMinimo);
  // Perdas: mostra apenas número inteiro (sem casas decimais)
  setCardValue("kpiPerdas", Math.round(perdasQtd));
  setCardValue("kpiComprasAtivas", comprasAtivas);
  
  // Atualiza card minimo: select ou hint
  const hintMinimo = document.getElementById("cardMinimoHint");
  const selectMinimo = document.getElementById("cardMinimoSelect");
  const listaProdutosMinimo = Array.isArray(minimos?.produtos) ? minimos.produtos : [];
  if (hintMinimo) {
    hintMinimo.style.display = produtosAbaixoMinimo > 0 ? "none" : "";
    hintMinimo.textContent = produtosAbaixoMinimo > 0 ? "" : "Tudo em dia";
  }
  if (selectMinimo) {
    selectMinimo.style.display = produtosAbaixoMinimo > 0 ? "block" : "none";
    selectMinimo.innerHTML = '<option value="">Selecione um produto</option>' +
      listaProdutosMinimo.map((p) => `<option value="${p.id}">${escapeHtml(p.nome || `Produto ${p.id}`)}</option>`).join("");
  }
  
  const hintPerdas = document.getElementById("cardPerdasHint");
  if (hintPerdas) hintPerdas.textContent = perdasRegistros > 0 ? `${perdasRegistros} movimentacoes recentes` : "Sem perdas registradas";
  
  // Atualiza classes de alerta
  const cardMinimo = document.getElementById("cardMinimo");
  if (cardMinimo) cardMinimo.classList.toggle("card--alert", produtosAbaixoMinimo > 0);
  
  const cardPerdas = document.getElementById("cardPerdas");
  if (cardPerdas) cardPerdas.classList.toggle("card--alert", perdasQtd > 0);
  
  const cardLotesVencidos = document.getElementById("cardLotesVencidos");
  if (cardLotesVencidos) cardLotesVencidos.classList.toggle("card--alert", lotesVencidos > 0);
}

async function loadDashboard() {
  try {
    // Carrega listas de compras para o card de compras
    try {
      await loadListasCompras().catch(() => {});
    } catch (err) {
      console.error("Erro ao carregar listas:", err);
    }
    
    const estoqueMinimoPromise = fetchJSON("/estoque-abaixo-minimo").catch(() => ({ total: 0, produtos: [] }));
    const perdasPromise = fetchJSON("/perdas-recentes").catch(() => ({ total_registros: 0, quantidade_total: 0, movimentacoes: [] }));
    const lotesStatsPromise = fetchJSON("/lotes/stats").catch(() => null);
    
    const [produtos, lotes, movs, minimos, perdas, lotesStatsRaw] = await Promise.all([
      fetchJSON("/produtos?todas=1").catch(() => []),
      fetchJSON("/lotes-a-vencer").catch(() => []),
      fetchJSON(`/movimentacoes?limit=50&_=${Date.now()}`).catch(() => []),
      estoqueMinimoPromise,
      perdasPromise,
      lotesStatsPromise,
    ]);
    
    const lotesStats = lotesStatsRaw && typeof lotesStatsRaw === "object" ? lotesStatsRaw : {};
    state.produtos = produtos;
    state.produtosAbaixoMinimo = Array.isArray(minimos?.produtos) ? minimos.produtos : [];
    state.perdasResumo = {
      total_registros: Number(perdas?.total_registros || 0),
      quantidade_total: Number(perdas?.quantidade_total || 0),
      movimentacoes: Array.isArray(perdas?.movimentacoes) ? perdas.movimentacoes : [],
    };
    
    const movsArray = Array.isArray(movs) ? movs : [];
    const movsOrdenados = sortMovimentacoes(movsArray);
    state.movimentacoes = movsOrdenados;
    state.movimentacoesRecentes = movsOrdenados;
    
    // Atualiza cards imediatamente
    atualizarCardsDashboard({
      produtos,
      lotes,
      minimos,
      perdas,
      lotesStats,
      comprasAtivas: state.listasComprasAtivasSnapshot?.length ?? 0
    });
    
    // Aguarda e atualiza novamente para garantir
    setTimeout(() => {
      atualizarCardsDashboard({
        produtos,
        lotes,
        minimos,
        perdas,
        lotesStats,
        comprasAtivas: state.listasComprasAtivasSnapshot?.length ?? 0
      });
      
      // Renderiza tabelas e gráficos
      renderMovimentacoesDashboard(movsOrdenados);
      renderLotesDashboard(lotes);
      renderProdutosDashboard(produtos);
      refreshProdutoSelects();
    }, 300);
    
    // Tenta mais uma vez após 1 segundo para garantir
    setTimeout(() => {
      atualizarCardsDashboard({
        produtos,
        lotes,
        minimos,
        perdas,
        lotesStats,
        comprasAtivas: state.listasComprasAtivasSnapshot?.length ?? 0
      });
    }, 1000);
    
  } catch (err) {
    console.error("Erro ao carregar dashboard:", err);
    showToast("Erro ao carregar dashboard.", "error");
  }
}

async function loadProdutos(search) {
  const searchEl = document.getElementById('produtoSearch');
  const termo = search !== undefined ? String(search || '').trim() : (searchEl ? (searchEl.value || '').trim() : '');
  console.log("loadProdutos: Carregando produtos da API...", termo ? `(search: ${termo})` : '');
  try {
    const params = new URLSearchParams();
    params.set('todas', '1');
    if (termo) params.set('search', termo);
    const produtos = await fetchJSON(`/produtos?${params}`);
    console.log("loadProdutos: Produtos recebidos da API:", produtos?.length || 0);
    
    if (!Array.isArray(produtos)) {
      console.error("loadProdutos: Resposta da API não é um array:", produtos);
      state.produtos = [];
      return;
    }
    
    state.produtos = produtos;
    const produtosAtivos = produtos.filter(p => Number(p.ativo) === 1);
    console.log("loadProdutos: Produtos ativos:", produtosAtivos.length);
    console.log("loadProdutos: Total de produtos:", produtos.length);
    
    renderProdutos(state.produtos);
    refreshProdutoSelects();
  } catch (error) {
    console.error("loadProdutos: Erro ao carregar produtos:", error);
    state.produtos = [];
    throw error;
  }
}

async function loadEstoqueResumo() {
  const valorEl = document.getElementById("estoqueResumoValor");
  const unidadeSelect = document.getElementById("estoqueResumoUnidade");
  if (!valorEl || !unidadeSelect) return;
  const perfilAtual = (currentUser?.perfil || "").toString().trim().toUpperCase();
  const isCozinhaOuBar = perfilAtual === "COZINHA" || perfilAtual === "BAR" || perfilAtual === "ATENDENTE";
  try {
    const unidadeId = unidadeSelect.value || "";
    const params = unidadeId ? `?unidade_id=${encodeURIComponent(unidadeId)}` : "";
    const resumo = await fetchJSON(`/estoque/resumo${params}`);
    const valorTotal = Number(resumo.valor_total || 0);
    if (isCozinhaOuBar) {
      valorEl.textContent = "---";
    } else {
      valorEl.textContent = formatCurrencyBRL(valorTotal);
    }
  } catch (err) {
    valorEl.textContent = "R$ 0,00";
  }
}

async function loadEstoqueProdutos() {
  try {
    if (!state.produtos || state.produtos.length === 0) {
      state.produtos = await fetchJSON("/produtos?todas=1");
    }
    
    const ativos = (state.produtos || []).filter((p) => Number(p.ativo ?? 1) === 1);
    const options = ativos.map((p) => `<option value="${p.id}">${escapeHtml(p.nome || `Produto ${p.id}`)}</option>`).join("");
    if (dom.estoqueProdutoSelect) {
      populateSelect(dom.estoqueProdutoSelect, options, "Selecione um produto");
    }
    bindBuscaSelect("estoqueProdutoBusca", "estoqueProdutoSelect");

    // Popula select de unidades no resumo e carrega valor total
    const unidades = state.unidades && state.unidades.length > 0 ? state.unidades : await fetchJSON("/unidades?todas=1");
    state.unidades = unidades;
    const selectResumoUnidade = document.getElementById("estoqueResumoUnidade");
    if (selectResumoUnidade) {
      const optHtml = (unidades || []).map((u) => `<option value="${u.id}">${escapeHtml(u.nome || `Unidade ${u.id}`)}</option>`).join("");
      selectResumoUnidade.innerHTML = '<option value="">Todas as unidades</option>' + optHtml;
    }
    await loadEstoqueResumo();
  } catch (err) {
    console.error("Erro ao carregar produtos para estoque:", err);
    showToast("Falha ao carregar lista de produtos.", "error");
  }
}

async function loadEstoqueProduto(produtoId) {
  if (!produtoId) {
    const estoqueInfo = document.getElementById("estoqueInfo");
    if (estoqueInfo) estoqueInfo.style.display = "none";
    return;
  }
  
  try {
    // Busca elementos diretamente pelo ID para garantir que estão disponíveis
    const estoqueInfo = document.getElementById("estoqueInfo");
    const estoqueProdutoNome = document.getElementById("estoqueProdutoNome");
    const estoqueTotalQtd = document.getElementById("estoqueTotalQtd");
    const estoqueTotalUnitario = document.getElementById("estoqueTotalUnitario");
    const estoqueTotalValor = document.getElementById("estoqueTotalValor");
    const estoqueUnidadeBase = document.getElementById("estoqueUnidadeBase");
    const estoqueTable = document.getElementById("estoqueTable");
    
    if (!estoqueInfo || !estoqueProdutoNome || !estoqueTotalQtd || !estoqueTotalUnitario || !estoqueTotalValor || !estoqueUnidadeBase || !estoqueTable) {
      console.error("Elementos do estoque não encontrados no DOM");
      return;
    }
    
    const dados = await fetchJSON(`/produtos/${produtoId}/estoque`);
    
    if (!dados || !dados.produto || !dados.estoque_total) {
      showToast("Dados de estoque inválidos.", "error");
      return;
    }
    
    // Verifica se é perfil COZINHA ou BAR para ocultar valores
    const perfilAtual = currentUser && currentUser.perfil ? currentUser.perfil.toString().trim().toUpperCase() : "";
    const isCozinhaOuBar = perfilAtual === "COZINHA" || perfilAtual === "BAR" || perfilAtual === "ATENDENTE";
    
    // Atualiza informações do produto
    estoqueProdutoNome.textContent = dados.produto.nome || "Produto";

    // Alerta de abaixo do mínimo
    const abaixoDoMinimo = dados.estoque_total?.abaixo_do_minimo;
    const estoqueMinimo = dados.produto?.estoque_minimo || 0;
    let alertaMinimo = document.getElementById('estoqueAlertaMinimo');
    if (!alertaMinimo) {
      alertaMinimo = document.createElement('div');
      alertaMinimo.id = 'estoqueAlertaMinimo';
      estoqueProdutoNome.parentNode.insertBefore(alertaMinimo, estoqueProdutoNome.nextSibling);
    }
    if (abaixoDoMinimo) {
      alertaMinimo.innerHTML = `<span style="display:inline-flex;align-items:center;gap:0.4rem;background:#fff3e0;color:#e65100;border:1px solid #ffb74d;border-radius:6px;padding:0.3rem 0.75rem;font-size:0.85rem;font-weight:600;margin-top:0.4rem;">⚠ Estoque abaixo do mínimo — Mínimo: ${estoqueMinimo.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 3 })} ${normalizarUnidadeBase(dados.produto.unidade_base)}</span>`;
    } else {
      alertaMinimo.innerHTML = estoqueMinimo > 0
        ? `<span style="display:inline-flex;align-items:center;gap:0.4rem;background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:6px;padding:0.3rem 0.75rem;font-size:0.85rem;font-weight:600;margin-top:0.4rem;">✔ Estoque OK — Mínimo: ${estoqueMinimo.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 3 })} ${normalizarUnidadeBase(dados.produto.unidade_base)}</span>`
        : '';
    }

    estoqueTotalQtd.textContent = (dados.estoque_total.qtd_total || 0).toLocaleString("pt-BR", {
      minimumFractionDigits: 0,
      maximumFractionDigits: 3,
    });
    estoqueUnidadeBase.textContent = `Unidade: ${normalizarUnidadeBase(dados.produto.unidade_base)}`;
    
    // Atualiza valores dos cards apenas se não for COZINHA ou BAR
    if (!isCozinhaOuBar) {
      estoqueTotalUnitario.textContent = `R$ ${(dados.estoque_total.valor_unitario_medio || 0).toLocaleString("pt-BR", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })}`;
      estoqueTotalValor.textContent = `R$ ${(dados.estoque_total.valor_total || 0).toLocaleString("pt-BR", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })}`;
    }
    
    // Guarda dados para uso no modal de detalhes
    window._estoqueDadosAtual = dados;

    // Renderiza tabela de estoque por unidade
    if (!dados.estoque_por_unidade || dados.estoque_por_unidade.length === 0) {
      const colspan = isCozinhaOuBar ? "5" : "7";
      estoqueTable.innerHTML = `
        <tr>
          <td colspan="${colspan}" style="text-align: center; color: #607d8b;">
            Nenhum estoque encontrado para este produto
          </td>
        </tr>
      `;
    } else {
      const tbody = document.getElementById('estoqueTable');
      tbody.innerHTML = '';

      dados.estoque_por_unidade.forEach((item) => {
        const tr = document.createElement('tr');

        const valorTotalCell = isCozinhaOuBar ? '' : `
          <td data-label="Valor Total" class="estoque-col-valor-total">R$ ${(item.valor_total || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        `;

        tr.innerHTML = `
          <td data-label="Unidade">${escapeHtml(item.unidade_nome || "N/A")}</td>
          <td data-label="Local">${item.locais ? escapeHtml(item.locais) : "—"}</td>
          <td data-label="Quantidade">${(item.qtd_total || 0).toLocaleString("pt-BR", { minimumFractionDigits: 0, maximumFractionDigits: 3 })}</td>
          ${valorTotalCell}
          <td data-label="Lotes (código)">${item.codigos_lote ? escapeHtml(item.codigos_lote) : (item.num_lotes ? `${item.num_lotes} lote(s)` : "—")}</td>
          <td data-label="Detalhes" class="table-actions"><button type="button" class="btn secondary btn-sm btn-estoque-detalhe">Detalhes</button></td>
        `;

        // Listener direto no botão
        tr.querySelector('.btn-estoque-detalhe').addEventListener('click', () => abrirEstoqueLotesModal(item, dados.produto));

        tbody.appendChild(tr);
      });
    }
    
    estoqueInfo.style.display = "block";
  } catch (err) {
    console.error("Erro ao carregar estoque:", err);
    showToast(err?.message || "Falha ao carregar estoque do produto.", "error");
    const estoqueInfo = document.getElementById("estoqueInfo");
    if (estoqueInfo) estoqueInfo.style.display = "none";
  }
}

async function abrirEstoqueLotesModal(item, produto) {
  const modal = document.getElementById('estoqueLotesModal');
  const titulo = document.getElementById('estoqueLotesModalTitulo');
  const content = document.getElementById('estoqueLotesModalContent');
  if (!modal || !titulo || !content) return;

  titulo.textContent = `Detalhes — ${escapeHtml(produto?.nome || 'Produto')} · ${escapeHtml(item.unidade_nome || '')}`;
  content.innerHTML = '<p style="text-align:center;color:#607d8b;padding:1.5rem;">Carregando...</p>';
  toggleModal(modal, true);

  const perfilAtual = currentUser?.perfil?.toString().trim().toUpperCase() || '';
  const isCozinhaOuBar = perfilAtual === 'COZINHA' || perfilAtual === 'BAR' || perfilAtual === 'ATENDENTE';

  // Usa lotes_detalhados se o servidor já retornar, senão busca via /lotes
  let lotes = item.lotes_detalhados || [];

  if (lotes.length === 0) {
    try {
      const produtoId = produto?.id || item.produto_id;
      const unidadeId = item.unidade_id;
      const params = new URLSearchParams();
      if (produtoId) params.append('produto_id', produtoId);
      if (unidadeId) params.append('unidade_id', unidadeId);
      const resposta = await fetchJSON(`/lotes?${params.toString()}`);
      const lotesRaw = Array.isArray(resposta) ? resposta : [];
      // Filtra apenas lotes com quantidade > 0 e monta no mesmo formato de lotes_detalhados
      lotes = lotesRaw
        .filter(l => parseFloat(l.qtd_atual ?? l.quantidade ?? 0) > 0)
        .map(l => ({
          codigo_lote:    l.numero_lote || l.codigo_lote || '—',
          quantidade:     parseFloat(l.qtd_atual ?? l.quantidade ?? 0),
          custo_unitario: parseFloat(l.custo_unitario ?? 0),
          valor_total:    parseFloat(l.qtd_atual ?? l.quantidade ?? 0) * parseFloat(l.custo_unitario ?? 0),
          data_validade:  l.data_validade || null,
        }));
    } catch (e) {
      console.error('Erro ao buscar lotes para modal:', e);
    }
  }

  if (lotes.length === 0) {
    content.innerHTML = '<p style="text-align:center;color:#607d8b;padding:1.5rem;">Nenhum lote encontrado.</p>';
    return;
  }

  // Alerta de estoque mínimo no modal
  const estoqueMinimo = parseFloat(produto?.estoque_minimo || 0);
  const qtdTotalUnidade = lotes.reduce((s, l) => s + Number(l.quantidade || 0), 0);
  const unidadeBase = normalizarUnidadeBase(produto?.unidade_base || '');
  let alertaHtml = '';
  if (estoqueMinimo > 0) {
    if (qtdTotalUnidade < estoqueMinimo) {
      alertaHtml = `<div style="display:flex;align-items:center;gap:0.5rem;background:#fff3e0;color:#e65100;border:1px solid #ffb74d;border-radius:6px;padding:0.5rem 0.75rem;margin-bottom:0.75rem;font-size:0.85rem;font-weight:600;">⚠ Estoque abaixo do mínimo — Atual: ${qtdTotalUnidade.toLocaleString('pt-BR', { maximumFractionDigits: 3 })} / Mínimo: ${estoqueMinimo.toLocaleString('pt-BR', { maximumFractionDigits: 3 })} ${unidadeBase}</div>`;
    } else {
      alertaHtml = `<div style="display:flex;align-items:center;gap:0.5rem;background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:6px;padding:0.5rem 0.75rem;margin-bottom:0.75rem;font-size:0.85rem;font-weight:600;">✔ Estoque OK — Atual: ${qtdTotalUnidade.toLocaleString('pt-BR', { maximumFractionDigits: 3 })} / Mínimo: ${estoqueMinimo.toLocaleString('pt-BR', { maximumFractionDigits: 3 })} ${unidadeBase}</div>`;
    }
  }

  let html = alertaHtml + `
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Lote (código)</th>
            <th>Quantidade</th>
            ${!isCozinhaOuBar ? '<th>Valor Unitário</th><th>Valor Total</th>' : ''}
            <th>Validade</th>
          </tr>
        </thead>
        <tbody>
  `;

  lotes.forEach((lote) => {
    const validade = lote.data_validade
      ? new Date(lote.data_validade + 'T00:00:00').toLocaleDateString('pt-BR')
      : '—';
    html += `
      <tr>
        <td data-label="Lote (código)">${escapeHtml(String(lote.codigo_lote || '—'))}</td>
        <td data-label="Quantidade">${Number(lote.quantidade).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 3 })}</td>
        ${!isCozinhaOuBar ? `
          <td data-label="Valor Unitário">R$ ${Number(lote.custo_unitario).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
          <td data-label="Valor Total">R$ ${Number(lote.valor_total).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        ` : ''}
        <td data-label="Validade">${validade}</td>
      </tr>
    `;
  });

  html += '</tbody></table></div>';
  content.innerHTML = html;
}

async function loadUnidades(refresh = true) {
  if (!refresh && state.unidades.length) {
    renderUnidades(state.unidades);
    refreshUnidadeSelects();
    return;
  }
  state.unidades = await fetchJSON("/unidades?todas=1");
  renderUnidades(state.unidades);
  refreshUnidadeSelects();
}

async function loadLocais(force = false) {
  if (!force && Array.isArray(state.locais) && state.locais.length) {
    renderLocais(state.locais);
    handleEntradaUnidadeChange().catch(() => {});
    return state.locais;
  }
  state.locais = await fetchJSON("/locais");
  renderLocais(state.locais);
  handleEntradaUnidadeChange().catch(() => {});
  return state.locais;
}

async function loadUsuarios(force = false) {
  if (usuariosCarregando) {
    await usuariosCarregando;
    if (!force) return state.usuarios;
  }
  if (!force && state.usuarios.length) {
    renderUsuarios(state.usuarios);
    refreshGerenteSelect();
    return state.usuarios;
  }
  const tarefa = (async () => {
    const dados = await fetchJSON("/usuarios?todas=1");
    state.usuarios = dados;
    renderUsuarios(state.usuarios);
    refreshGerenteSelect();
    return state.usuarios;
  })();
  usuariosCarregando = tarefa;
  try {
    return await tarefa;
  } finally {
    usuariosCarregando = null;
  }
}

async function loadFuncionarios(filtros = {}) {
  const params = new URLSearchParams();
  ["nome", "cpf", "cargo", "unidade_id", "status"].forEach(k => {
    if (filtros[k]) params.append(k, filtros[k]);
  });
  const url = params.toString() ? `/funcionarios?${params}` : "/funcionarios";
  const dados = await fetchJSON(url);
  state.funcionarios = Array.isArray(dados) ? dados : [];
  populateFuncionariosFiltroNome(state.funcionarios);
  populateProventosFiltroFuncionario(state.funcionarios);
  renderFuncionarios(state.funcionarios);
  return state.funcionarios;
}

function populateFuncionariosFiltroNome(lista) {
  const select = document.getElementById("funcionariosFiltroNome");
  const buscaInput = document.getElementById("funcionariosFiltroNomeBusca");
  if (!select) return;

  const selecionado = select.value;
  const nomes = [...new Set((Array.isArray(lista) ? lista : [])
    .map((f) => (f?.nome_completo || "").toString().trim())
    .filter(Boolean))]
    .sort((a, b) => a.localeCompare(b, "pt-BR"));

  select.innerHTML = '<option value="">Todos</option>' +
    nomes.map((nome) => `<option value="${escapeHtml(nome)}">${escapeHtml(nome)}</option>`).join("");

  if (selecionado && nomes.includes(selecionado)) {
    select.value = selecionado;
  } else {
    select.value = "";
  }

  if (buscaInput) {
    buscaInput._todasOpcoes = nomes.map((nome) => ({ value: nome, text: nome }));
  }

  if (select.dataset.buscaBound !== "true") {
    bindBuscaSelect("funcionariosFiltroNomeBusca", "funcionariosFiltroNome");
    select.dataset.buscaBound = "true";
  }
}

/** Mesmo padrão do filtro "Nome" em RH → Funcionários: busca + select com bindBuscaSelect; value = id para API proventos. */
function populateProventosFiltroFuncionario(lista) {
  const select = document.getElementById("proventosFiltroFuncionario");
  const buscaInput = document.getElementById("proventosFiltroFuncionarioBusca");
  if (!select) return;

  const selecionado = select.value;
  const ativos = (Array.isArray(lista) ? lista : []).filter((f) => (f.status || "ativo") === "ativo");
  ativos.sort((a, b) => (a.nome_completo || "").toString().localeCompare((b.nome_completo || "").toString(), "pt-BR"));

  select.innerHTML =
    '<option value="">Todos</option>' +
    ativos
      .map((f) => {
        const nome = (f.nome_completo || "").toString().trim();
        const cpf = f.cpf ? ` — ${f.cpf}` : "";
        const text = nome + cpf;
        return `<option value="${escapeHtml(String(f.id))}">${escapeHtml(text)}</option>`;
      })
      .join("");

  if (selecionado && [...select.options].some((o) => o.value === selecionado)) select.value = selecionado;
  else select.value = "";

  if (buscaInput) {
    buscaInput._todasOpcoes = ativos.map((f) => {
      const nome = (f.nome_completo || "").toString().trim();
      const cpf = f.cpf ? ` — ${f.cpf}` : "";
      return { value: String(f.id), text: nome + cpf };
    });
  }

  if (select.dataset.buscaBound !== "true") {
    bindBuscaSelect("proventosFiltroFuncionarioBusca", "proventosFiltroFuncionario");
    select.dataset.buscaBound = "true";
  }
}

function renderFuncionarios(lista) {
  const target = dom.funcionariosTable;
  if (!target) return;
  if (!Array.isArray(lista) || lista.length === 0) {
    target.innerHTML = '<tr><td colspan="11" style="text-align:center;color:#607d8b">Nenhum funcionário encontrado.</td></tr>';
    return;
  }
  const escape = (s) => (s == null || s === undefined ? "" : String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"));
  const rows = lista.map(f => {
    const statusCls = (f.status || "ativo") === "ativo" ? "status-pill status-pill--success" : "status-pill status-pill--muted";
    const statusLabel = (f.status || "ativo") === "ativo" ? "Ativo" : "Inativo";
    const acessoLabel = f.possui_acesso ? "Sim" : "Não";
    const isAtivo = (f.status || "ativo") === "ativo";
    const btnInativar = isAtivo ? `<button type="button" class="table-action btn-inativar-funcionario" data-id="${f.id}" title="Inativar">Inativar</button>` : "";
    const fotoUrl = f.foto ? getUsuarioFotoUrl(f.foto) : null;
    const fotoCell = fotoUrl
      ? `<img src="${fotoUrl}" alt="${escape(f.nome_completo)}" class="usuarios-foto" loading="lazy" />`
      : '<div class="usuarios-foto usuarios-foto--placeholder" aria-label="Sem foto"></div>';
    return `<tr data-id="${f.id}">
      <td data-label="Foto">${fotoCell}</td>
      <td data-label="ID">${escape(f.id)}</td>
      <td data-label="Nome">${escape(f.nome_completo)}</td>
      <td data-label="CPF">${escape(f.cpf)}</td>
      <td data-label="Cargo">${escape(f.cargo)}</td>
      <td data-label="Unidade">${escape(f.unidade_nome || "-")}</td>
      <td data-label="WhatsApp">${escape(f.whatsapp || "-")}</td>
      <td data-label="E-mail">${escape(f.email || "-")}</td>
      <td data-label="Status"><span class="${statusCls}">${statusLabel}</span></td>
      <td data-label="Acesso">${acessoLabel}</td>
      <td data-label="Ações" class="table-actions">
        <button type="button" class="table-action btn-view-funcionario" data-id="${f.id}" title="Visualizar">Visualizar</button>
        <button type="button" class="table-action btn-edit-funcionario" data-id="${f.id}" title="Editar">Editar</button>
        ${btnInativar}
      </td>
    </tr>`;
  });
  target.innerHTML = rows.join("");
}

const PROVENTO_STATUS_LABELS = {
  rascunho: "Rascunho",
  aguardando_autorizacao: "Pendente aceite (funcionário)",
  autorizado: "Autorizado (gestão)",
  aguardando_assinatura: "Aguard. assinatura",
  assinado: "Aceito / assinado",
  finalizado: "Finalizado",
  cancelado: "Cancelado",
  rejeitado: "Rejeitado",
};
const PROVENTO_TIPO_LABELS = { vale: "Vale", adiantamento: "Adiantamento", consumo_interno: "Consumo interno", ajuda_custo: "Ajuda de custo", outro: "Outro" };

async function loadProventos(filtros = {}) {
  const perfil = (currentUser?.perfil || "").toString().trim().toUpperCase();
  const podeCriarProvento = ["ADMIN","GERENTE","FINANCEIRO","ASSISTENTE_ADMINISTRATIVO"].includes(perfil);
  const usaMeusProventos = !podeCriarProvento; // Sem permissão para lançar: vê apenas os próprios proventos
  const url = usaMeusProventos ? "/proventos/meus" : "/proventos";
  try {
    const params = new URLSearchParams();
    if (!usaMeusProventos) {
      ["funcionario_id", "tipo", "unidade_id", "status", "data_inicio", "data_fim"].forEach((k) => {
        if (filtros[k]) params.append(k, filtros[k]);
      });
    }
    const fullUrl = params.toString() ? `${url}?${params}` : url;
    const dados = await fetchJSON(fullUrl);
    state.proventos = Array.isArray(dados) ? dados : [];
    renderProventos(state.proventos);
    return state.proventos;
  } catch (e) {
    state.proventos = [];
    const tbl = document.getElementById("proventosTable");
    if (tbl) {
      const raw = (e?.message || e?.responseData?.error || "Erro ao carregar a lista de proventos.").toString().trim();
      const safe = raw.replace(/</g, "&lt;").replace(/>/g, "&gt;").slice(0, 400);
      const hint =
        e?.status === 401
          ? " Faça login novamente."
          : /migrations|tabela|column|SQLSTATE/i.test(raw)
            ? " Se o problema continuar, rode as migrations do backend (incluindo a coluna PIX em funcionários) ou peça suporte."
            : "";
      tbl.innerHTML = `<tr><td colspan="11" style="text-align:center;color:#c62828">${safe}${hint}</td></tr>`;
    }
    throw e;
  }
}

/** Abre o PDF do recibo (só existe no servidor para status finalizado). */
async function abrirReciboProventoPdf(proventoId) {
  if (!proventoId || !currentUser) {
    showToast("Sessão inválida. Faça login novamente.", "error");
    return;
  }
  try {
    const headers = {
      ...getDeviceHeaders(),
      ...(currentUser.token ? { Authorization: `Bearer ${currentUser.token}` } : {}),
      ...(currentUser.id != null ? { "X-Usuario-Id": String(currentUser.id) } : {}),
    };
    const res = await fetch(`${API_URL}/proventos/${proventoId}/recibo.pdf`, { method: "GET", headers, cache: "no-store" });
    if (!res.ok) {
      let msg = `Erro ${res.status}`;
      try {
        const ct = res.headers.get("Content-Type") || "";
        if (ct.includes("json")) {
          const j = await res.json();
          if (j.error) msg = j.error;
        }
      } catch (_) {}
      throw new Error(msg);
    }
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    window.open(url, "_blank", "noopener,noreferrer");
    setTimeout(() => URL.revokeObjectURL(url), 120000);
    showToast("Recibo aberto — use o navegador para salvar ou imprimir como PDF.", "success");
  } catch (e) {
    showToast(e?.message || "Não foi possível gerar o recibo.", "error");
  }
}

function renderProventos(lista) {
  const target = document.getElementById("proventosTable");
  if (!target) return;
  const perfilNav = (currentUser?.perfil || "").toString().trim().toUpperCase();
  const podeCriarNav = ["ADMIN", "GERENTE", "FINANCEIRO", "ASSISTENTE_ADMINISTRATIVO"].includes(perfilNav);
  if (!Array.isArray(lista) || lista.length === 0) {
    const msg = podeCriarNav
      ? "Nenhum provento encontrado com os filtros atuais."
      : "Nenhum provento para você no momento. Quando o financeiro lançar um provento vinculado ao seu cadastro de funcionário, ele aparecerá aqui para aceite.";
    target.innerHTML = `<tr><td colspan="11" style="text-align:center;color:#607d8b">${msg}</td></tr>`;
    return;
  }
  const esc = s => (s == null ? "-" : String(s).replace(/</g, "&lt;"));
  const perfil = (currentUser?.perfil || "").toString().trim().toUpperCase();
  const podeCriar = ["ADMIN","GERENTE","FINANCEIRO","ASSISTENTE_ADMINISTRATIVO"].includes(perfil);
  const podeAssinarProvento = !podeCriar; // Quem vê só os próprios proventos pode assinar (como Ing e Kel)
  const rows = lista.map(p => {
    const st = PROVENTO_STATUS_LABELS[p.status] || p.status;
    const stCls = p.status === "finalizado" ? "status-pill--success" : p.status === "cancelado" || p.status === "rejeitado" ? "status-pill--muted" : "status-pill";
    const tipoL = PROVENTO_TIPO_LABELS[p.tipo] || p.tipo;
    const valorF = "R$ " + (Number(p.valor) || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2 });
    const dataF = p.data_provento ? new Date(p.data_provento + "T12:00:00").toLocaleDateString("pt-BR") : "-";
    let acoes = `<button type="button" class="table-action btn-view-provento" data-id="${p.id}">Visualizar</button>`;
    if (p.status === "finalizado") {
      acoes += `<button type="button" class="table-action btn-recibo-provento" data-id="${p.id}">Recibo PDF</button>`;
    }

    // Funcionário/atendente assina quando está aguardando assinatura
    if (podeAssinarProvento && p.status === "aguardando_autorizacao") {
      acoes += `<button type="button" class="table-action btn-primary btn-assinar-provento" data-id="${p.id}">Aceitar / Assinar</button>`;
    }

    if (podeCriar) {
      if (["rascunho","aguardando_autorizacao"].includes(p.status)) {
        acoes += `<button type="button" class="table-action btn-edit-provento" data-id="${p.id}">Editar</button>`;
      }
      // Gestão autoriza DEPOIS que funcionário assinou
      if (p.status === "assinado") {
        acoes += `<button type="button" class="table-action btn-autorizar-provento" data-id="${p.id}">Autorizar</button>`;
      }
      // Finalizar depois de autorizado
      if (p.status === "autorizado") {
        acoes += `<button type="button" class="table-action btn-finalizar-provento" data-id="${p.id}">Finalizar</button>`;
      }
      if (!["finalizado","cancelado","rejeitado"].includes(p.status)) {
        acoes += `<button type="button" class="table-action btn-danger btn-cancelar-provento" data-id="${p.id}">Cancelar</button>`;
      }
    }
    return `<tr>
      <td data-label="ID">${esc(p.id)}</td>
      <td data-label="Funcionário">${esc(p.funcionario_nome)}</td>
      <td data-label="CPF">${esc(p.funcionario_cpf)}</td>
      <td data-label="Tipo">${esc(tipoL)}</td>
      <td data-label="CNPJ (unidade)">${esc(formatCnpjCpfDisplay(p.unidade_cnpj || ""))}</td>
      <td data-label="Valor">${valorF}</td>
      <td data-label="Unidade">${esc(p.unidade_nome)}</td>
      <td data-label="Criado por">${esc(p.criado_por_nome)}</td>
      <td data-label="Data">${dataF}</td>
      <td data-label="Status"><span class="status-pill ${stCls}">${st}</span></td>
      <td data-label="Ações" class="table-actions">${acoes}</td>
    </tr>`;
  });
  target.innerHTML = rows.join("");
}

async function loadLotes(filtros = {}) {
  const params = new URLSearchParams();
  Object.entries(filtros).forEach(([key, value]) => { if (value) params.append(key, value); });
  const dados = await fetchJSON(params.toString() ? `/lotes?${params}` : "/lotes");
  console.log("📦 Dados recebidos do backend (/lotes):", dados?.length || 0, "lotes");
  if (dados && dados.length > 0) {
    console.log("📦 Primeiro lote recebido:", {
      id: dados[0].id,
      data_validade: dados[0].data_validade,
      dias_para_vencer: dados[0].dias_para_vencer,
      produto_nome: dados[0].produto_nome
    });
  }
  state.lotes = dados;
  renderLotesGerenciamento(dados);
  handleSaidaOrigemChange();
  populateEntradaLoteOptions();
}

// Recupera movimentacoes com filtros e sincroniza as visoes de detalhes.
async function loadMovimentacoesDetalhadas(filtros = {}, options = {}) {
  const resolvedOptions =
    typeof options === "boolean" ? { refreshDashboard: options } : options || {};
  const { refreshDashboard = false } = resolvedOptions;
  movimentacoesRequestId += 1;
  const requestId = movimentacoesRequestId;
  
  // ✅ Prepara parâmetros de query, removendo valores vazios
  const params = new URLSearchParams();
  Object.entries(filtros).forEach(([key, value]) => { 
    if (value && value.toString().trim() !== "") {
      params.append(key, value.toString().trim());
    }
  });
  
  const queryString = params.toString();
  const bustParam = `_=${Date.now()}`;
  const url = queryString ? `/movimentacoes?${queryString}&${bustParam}` : `/movimentacoes?limit=100&${bustParam}`;
  
  console.log("Carregando movimentações:", { filtros, url });
  
  try {
    const dados = await fetchJSON(url);
    if (movimentacoesRequestId !== requestId) return;
    
    // Garantir que dados seja um array
    const dadosArray = Array.isArray(dados) ? dados : [];
    const dadosOrdenados = sortMovimentacoes(dadosArray);
    state.movimentacoes = dadosOrdenados;
    
    console.log(`Movimentações carregadas: ${dadosOrdenados.length} registros`);
    
    // ✅ Renderiza na tabela de movimentações
    renderMovimentacoes(dadosOrdenados, dom.movimentacoesTable, "Sem movimentacoes para os filtros selecionados.");
    
    // Atualiza state.movimentacoesRecentes quando não há filtros
    if (!queryString) {
      state.movimentacoesRecentes = dadosOrdenados;
      // NÃO renderiza o dashboard aqui se refreshDashboard for true, pois loadDashboard() já vai renderizar
      if (!refreshDashboard) {
        renderMovimentacoesDashboard(dadosOrdenados);
      }
    }
    
    if (refreshDashboard) {
      try {
        await loadDashboard();
      } catch (err) {
        showToast(err?.message || "Falha ao atualizar dashboard.", "error");
      }
    }
    
    return dadosOrdenados;
  } catch (err) {
    console.error("Erro ao carregar movimentações:", err);
    renderMovimentacoes([], dom.movimentacoesTable, "Erro ao carregar movimentações.");
    throw err;
  }
}

function agruparMovimentacoes(lista, tipo) {
  const mapa = new Map();
  (lista || []).forEach((mov) => {
    const quantidade = Number(mov.qtd ?? 0) || 0;
    const valor = quantidade * (Number(mov.custo_unitario ?? 0) || 0);
    let chave;
    let nome;
    if (tipo === "unidade") {
      chave = mov.unidade_id || mov.de_unidade_id || mov.para_unidade_id || "--";
      nome = mov.unidade_nome || "--";
    } else if (tipo === "responsavel") {
      chave = mov.usuario_id || "--";
      nome = mov.responsavel_nome || "--";
    } else {
      chave = mov.produto_id || "--";
      nome = mov.produto_nome || "--";
    }
    if (!mapa.has(chave)) {
      mapa.set(chave, { nome, entradasQtd: 0, entradasVal: 0, saidasQtd: 0, saidasVal: 0, saldoQtd: 0 });
    }
    const item = mapa.get(chave);
    if ((mov.tipo || "").toUpperCase() === "ENTRADA") {
      item.entradasQtd += quantidade;
      item.entradasVal += valor;
      item.saldoQtd += quantidade;
    } else {
      item.saidasQtd += quantidade;
      item.saidasVal += valor;
      item.saldoQtd -= quantidade;
    }
  });
  return Array.from(mapa.values());
}

// Atualiza os dados de relatorios respeitando os filtros ativos na interface.
async function loadRelatorio(filtros = {}) {
  await loadMovimentacoesDetalhadas(filtros);
  const agruparPor = (filtros.agrupar || dom.relatorioAgrupar?.value || "produto").toLowerCase();
  const label = agruparPor === "unidade" ? "Unidade" : agruparPor === "responsavel" ? "Responsavel" : "Produto";
  state.relatorioResumo = agruparMovimentacoes(state.movimentacoes, agruparPor);
  renderRelatorioResumo(state.relatorioResumo, label);
  renderRelatorioDetalhes(state.movimentacoes);
}
async function startAppSession(user) {
  if (user) setUser(user);
  currentUser = getUser();
  if (!currentUser) {
    showToast("Erro ao iniciar sessão. Tente fazer login novamente.", "error");
    return;
  }
  
  applyPermissions();

  if (typeof stopMatrixAnimation === "function") {
    stopMatrixAnimation();
    stopMatrixAnimation = null;
  }
  
  console.log('Iniciando sessão do usuário:', currentUser);
  
  // Esconde tela de login e mostra aplicação
  if (dom.loginOverlay) {
    dom.loginOverlay.classList.add("hidden");
    console.log('Tela de login escondida');
  } else {
    console.error('loginOverlay não encontrado!');
  }
  
  if (dom.appShell) {
    dom.appShell.classList.remove("hidden");
    console.log('App shell mostrado');
  } else {
    console.error('appShell não encontrado!');
  }

  // Mostra botões de ADMIN (Backup e Zerar Históricos) apenas para ADMIN
  const perfil = (currentUser?.perfil || '').toString().trim().toUpperCase();
  const isAdminUser = perfil === 'ADMIN';
  const btnZerar = document.getElementById('btnZerarHistoricos');
  if (btnZerar) btnZerar.style.display = isAdminUser ? 'block' : 'none';
  const btnBackup = document.getElementById('btnAbrirBackup');
  if (btnBackup) btnBackup.style.display = isAdminUser ? 'block' : 'none';

  setSidebarOpen(false);
  
  // Carrega dados iniciais em background (não bloqueia o login)
  Promise.allSettled([
    loadDashboard().catch(err => console.error("Erro ao carregar dashboard:", err)),
    loadProdutos().catch(err => console.error("Erro ao carregar produtos:", err)),
    loadUnidades().catch(err => console.error("Erro ao carregar unidades:", err)),
    loadLocais().catch(err => console.error("Erro ao carregar locais:", err)),
    loadUsuarios().catch(err => console.error("Erro ao carregar usuários:", err)),
    loadLotes().catch(err => console.error("Erro ao carregar lotes:", err)),
    loadMovimentacoesDetalhadas().catch(err => console.error("Erro ao carregar movimentações:", err)),
    loadListasCompras().catch(err => console.error("Erro ao carregar listas:", err)),
    loadRelatorio({}).catch(err => console.error("Erro ao carregar relatório:", err)),
  ]).then(() => {
    console.log('Dados iniciais carregados');
  });
  
  // Navegação inicial:
  // - Login novo (user informado): sempre vai para Boas-vindas.
  // - Reabertura/refresh com sessão (user não informado): restaura a última seção salva.
  // Exceção: hash QR de saída continua indo ao dashboard e abre o modal de lote.
  (() => {
    const isFreshLogin = !!user;
    const allSections = new Set([
      "boasVindas", "minhaConta", "dashboard", "unidades", "usuarios", "produtos", "fechaTecnica",
      "estoque", "lotes", "locais", "movimentacoes", "compras", "relatorios", "fornecedores",
      "fornecedoresBackup", "boletao", "alvara", "proventos", "fechamento", "reservaMesa", "historicoReservas",
      "funcionarios", "logs"
    ]);

    let sectionToNavigate = "boasVindas";

    if (!isFreshLogin) {
      try {
        const saved = localStorage.getItem(currentSectionKey);
        if (saved && allSections.has(saved)) sectionToNavigate = saved;
      } catch (err) {
        console.warn("Erro ao ler seção salva:", err);
      }
    }

    const hash = window.location.hash || "";
    const m = hash.match(/[?&]lote=(\d+)/);
    const hashSaidaMatch = m && (hash.includes("saida=1") || hash.includes("saida=true")) ? m : null;
    if (hashSaidaMatch) {
      sectionToNavigate = "dashboard";
    }

    navigateTo(sectionToNavigate);

    requestAnimationFrame(async () => {
      try {
        if (sectionToNavigate === 'dashboard') await loadDashboard();
        else if (sectionToNavigate === 'produtos') await loadProdutos();
        else if (sectionToNavigate === 'estoque') await loadEstoqueProdutos();
        else if (sectionToNavigate === 'unidades') await Promise.all([loadUnidades(), loadUsuarios()]);
        else if (sectionToNavigate === 'usuarios') await loadUsuarios();
        else if (sectionToNavigate === 'lotes') await loadLotes();
        else if (sectionToNavigate === 'locais') await Promise.all([loadLocais(true), loadUnidades(false)]);
        else if (sectionToNavigate === 'movimentacoes') {
          await Promise.all([loadProdutos().catch(() => {}), loadUnidades().catch(() => {})]);
          refreshProdutoSelects();
          refreshUnidadeSelects();
          await loadMovimentacoesDetalhadas({}, { refreshDashboard: true });
        } else if (sectionToNavigate === 'relatorios') await loadRelatorio();
        else if (sectionToNavigate === 'compras') await loadListasCompras();
        else if (sectionToNavigate === 'fornecedores') await loadFornecedores();
        else if (sectionToNavigate === 'fornecedoresBackup') await loadFornecedoresBackup();
        else if (sectionToNavigate === 'logs') await loadLogs();
        else if (sectionToNavigate === 'reservaMesa') {
          var uSelect = document.getElementById('reservasUnidadeFiltro');
          if (uSelect && uSelect.options.length <= 1) {
            var unidades = state.unidades && state.unidades.length ? state.unidades : await fetchJSON('/unidades').catch(function() { return []; });
            uSelect.innerHTML = '<option value="">Selecione a unidade</option>';
            (unidades || []).forEach(function(u) {
              var opt = document.createElement('option');
              opt.value = u.id;
              opt.textContent = u.nome || 'Unidade ' + u.id;
              uSelect.appendChild(opt);
            });
            if (currentUser && currentUser.unidade_id && (currentUser.perfil || '').toUpperCase() !== 'ADMIN') {
              uSelect.value = currentUser.unidade_id;
              uSelect.disabled = true;
            }
          }
          await loadReservasMesas();
        }
        else if (sectionToNavigate === 'historicoReservas') {
          var uSelect = document.getElementById('historicoUnidadeFiltro');
          if (uSelect && uSelect.options.length <= 1) {
            var unidades = state.unidades && state.unidades.length ? state.unidades : await fetchJSON('/unidades').catch(function() { return []; });
            state.unidades = unidades;
            uSelect.innerHTML = '<option value="">Selecione a unidade</option>';
            (unidades || []).forEach(function(u) {
              var opt = document.createElement('option');
              opt.value = u.id;
              opt.textContent = u.nome || 'Unidade ' + u.id;
              uSelect.appendChild(opt);
            });
            if (currentUser && currentUser.unidade_id && (currentUser.perfil || '').toUpperCase() !== 'ADMIN') {
              uSelect.value = currentUser.unidade_id;
              uSelect.disabled = true;
            }
          }
          var dInicio = document.getElementById('historicoDataInicio');
          var dFim = document.getElementById('historicoDataFim');
          if (dInicio && !dInicio.value) dInicio.value = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
          if (dFim && !dFim.value) dFim.value = new Date().toISOString().slice(0, 10);
          await loadHistoricoReservas();
        }
        else if (sectionToNavigate === 'boletao') {
          const tbody = document.getElementById('boletosTable');
          if (tbody) {
            await loadBoletos({}).catch(() => {});
            await loadBoletosResumo().catch(() => {});
          }
        }
        else if (sectionToNavigate === 'proventos') {
          const perfil = (currentUser?.perfil || "").toString().trim().toUpperCase();
          const podeCriarProvento = ["ADMIN","GERENTE","FINANCEIRO","ASSISTENTE_ADMINISTRATIVO"].includes(perfil);
          const isFuncionario = !podeCriarProvento;
          const titleEl = document.getElementById("proventosSectionTitle");
          const subtitleEl = document.getElementById("proventosSectionSubtitle");
          const filterForm = document.getElementById("proventosFilterForm");
          const openProventoBtn = document.getElementById("openProvento");
          if (titleEl) titleEl.textContent = isFuncionario ? "Meus Proventos" : "Proventos";
          if (subtitleEl) subtitleEl.textContent = isFuncionario ? "Proventos que precisam da sua aceite ou já foram processados" : "Controle de proventos e lançamentos relacionados aos funcionários";
          if (filterForm) filterForm.style.display = isFuncionario ? "none" : "";
          if (openProventoBtn) openProventoBtn.classList.toggle("hidden", !podeCriarProvento);
          if (!isFuncionario) {
            await loadUnidades(false).catch(() => {});
            await loadFuncionarios().catch(() => {});
            const selUn = document.getElementById("proventosFiltroUnidade");
            if (selUn && state.unidades?.length) {
              selUn.innerHTML = '<option value="">Todas as unidades</option>' + state.unidades.map(u => `<option value="${u.id}">${(u.nome||"").replace(/</g,"&lt;")}</option>`).join("");
            }
            await loadProventos({}).catch(() => {});
          } else {
            await loadProventos().catch(() => {});
          }
        }
        else if (sectionToNavigate === 'funcionarios') await loadFuncionarios();
        else if (sectionToNavigate === 'fechaTecnica') {
          onNavigateFichaTecnicaCallback();
        } else if (sectionToNavigate === 'alvara') {
          await populateAlvarasUnidades().catch(() => {});
          await loadAlvaras(collectAlvarasListFiltersFromDOM()).catch(() => {});
        } else if (sectionToNavigate === 'fechamento') {
          await loadFechamentoCaixaSection();
        }
      } catch (err) {
        console.error('Erro ao carregar seção inicial:', err);
      }

      // Se veio do QR code da etiqueta: abre modal Registrar Saída com lote pré-preenchido
      if (hashSaidaMatch) {
        const loteIdQr = Number(hashSaidaMatch[1]);
        try {
          await abrirModalSaidaComLote(loteIdQr);
        } catch (err) {
          console.error('Erro ao abrir saída pelo QR code:', err);
          showToast('Erro ao carregar dados do lote.', 'error');
        }
        history.replaceState(null, '', window.location.pathname + '#dashboard');
      }
    });
  })();
  
  // Inicia o monitoramento de inatividade após login
  startInactivityTimer();
}

// Funções para gerenciar timeout de inatividade
function startInactivityTimer() {
  // Limpa timer anterior se existir
  stopInactivityTimer();
  
  // Reseta o timer quando há atividade do usuário
  inactivityResetHandler = () => {
    if (inactivityTimer) {
      clearTimeout(inactivityTimer);
    }
    
    // Define novo timer de 6 minutos
    inactivityTimer = setTimeout(() => {
      // Verifica se ainda há usuário logado
      const user = getUser();
      if (user && user.token) {
        showToast("Sessão expirada por inatividade. Faça login novamente.", "warning");
        handleLogout();
      }
    }, INACTIVITY_TIMEOUT);
  };
  
  // Eventos que indicam atividade do usuário
  const activityEvents = [
    'mousedown',
    'mousemove',
    'keypress',
    'scroll',
    'touchstart',
    'click',
    'keydown'
  ];
  
  // Adiciona listeners para eventos de atividade
  activityEvents.forEach(event => {
    document.addEventListener(event, inactivityResetHandler, true);
  });
  
  // Inicia o timer
  inactivityResetHandler();
  
  console.log('Monitoramento de inatividade iniciado (6 minutos)');
}

function stopInactivityTimer() {
  if (inactivityTimer) {
    clearTimeout(inactivityTimer);
    inactivityTimer = null;
  }
  
  // Remove todos os event listeners de atividade
  if (inactivityResetHandler) {
    const activityEvents = [
      'mousedown',
      'mousemove',
      'keypress',
      'scroll',
      'touchstart',
      'click',
      'keydown'
    ];
    
    activityEvents.forEach(event => {
      document.removeEventListener(event, inactivityResetHandler, true);
    });
    
    inactivityResetHandler = null;
  }
  
  console.log('Monitoramento de inatividade parado');
}

function resetForms() {
  [dom.produtosForm, dom.unidadeForm, dom.unidadeInlineForm, dom.usuarioForm, dom.entradaForm, dom.loteForm, dom.saidaForm, dom.listaCompraForm, dom.itemCompraForm, dom.estabelecimentoCompraForm, dom.finalizarListaForm].forEach((form) => form && form.reset());
  usuarioFotoFile = null;
  usuarioFotoRemovida = false;
  if (dom.unidadeForm?.elements.gerente_usuario_id) dom.unidadeForm.elements.gerente_usuario_id.value = "";
  if (dom.unidadeInlineForm?.elements.gerente_usuario_id) dom.unidadeInlineForm.elements.gerente_usuario_id.value = "";
  state.produtos = [];
  state.produtosAbaixoMinimo = [];
  state.perdasResumo = { total_registros: 0, quantidade_total: 0, movimentacoes: [] };
  state.unidades = [];
  state.locais = [];
  state.usuarios = [];
  state.lotes = [];
  state.movimentacoes = [];
  state.relatorioResumo = [];
  state.relatorioDetalhes = [];
  state.listaComprasFiltroStatus = "ativas";
  state.listasComprasAtivasSnapshot = [];
  state.unidadeInlineVisivel = false;
  usuariosCarregando = null;
  if (dom.listaCompraFiltroStatus) dom.listaCompraFiltroStatus.value = "ativas";
  refreshGerenteSelect();
  updateSaidaDestinoVisibility();
  resetSaidaProdutoSelect();
  resetEntradaLocalSelect();
  if (dom.usuarioAvatarPreview) dom.usuarioAvatarPreview.innerHTML = '<span class="avatar-placeholder">?</span>';
  state.listaCompraAtual = null;
  state.listasCompras = [];
  renderListasCompras([]);
  renderListaCompraDetalhes(null);
  updateComprasDashboardCard();
  updateMinimoDashboardCard();
  updatePerdasDashboardCard();
  applyPermissions();
}

function toggleModal(modal, visible) {
  if (!modal) return;
  modal.classList.toggle("active", visible);
}

async function handleLogin(event) {
  console.log('=== handleLogin CHAMADO ===');
  event.preventDefault();
  console.log('=== INÍCIO DO LOGIN ===');
  
  const email = document.getElementById("loginEmail")?.value.trim();
  const senha = document.getElementById("loginPassword")?.value.trim();
  
  console.log('Email:', email ? 'preenchido' : 'vazio');
  console.log('Senha:', senha ? 'preenchida' : 'vazia');
  
  if (!email || !senha) {
    console.log('Erro: Email ou senha vazios');
    showToast("Informe email e senha.", "error");
    return;
  }
  
  // Desabilita botão durante o login
  const submitBtn = dom.loginForm?.querySelector('button[type="submit"]');
  const originalText = submitBtn?.textContent;
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = "Entrando...";
  }
  
  try {
    console.log('=== DETALHES DA REQUISIÇÃO ===');
    console.log('API_URL:', API_URL);
    console.log('URL completa:', `${API_URL}/login`);
    console.log('Payload:', { email, senha: '***' });
    
    const payload = await fetchJSON("/login", { method: "POST", body: JSON.stringify({ email, senha }) });
    
    console.log('Resposta recebida:', payload);
    
    if (!payload || !payload.id) {
      console.error('Resposta inválida:', payload);
      throw new Error("Resposta inválida do servidor");
    }
    
    console.log('Login bem-sucedido! Payload:', payload);
    showToast("Login realizado com sucesso!", "success");
    
    // Aguarda um pouco para garantir que o toast seja exibido
    await new Promise(resolve => setTimeout(resolve, 300));
    
    startAppSession({ 
      id: payload.id, 
      nome: payload.nome, 
      email: payload.email, 
      perfil: payload.perfil, 
      permissoes_menu: payload.permissoes_menu || null,
      token: payload.token 
    });
    
    dom.loginForm.reset();
    console.log('Sessão iniciada, formulário resetado');
  } catch (err) {
    console.error('=== ERRO NO LOGIN ===');
    console.error('Erro completo:', err);
    console.error('Mensagem:', err.message);
    console.error('Stack:', err.stack);
    
    let errorMessage = err.message || "Falha ao autenticar.";
    
    // Mensagens de erro mais amigáveis
    if (errorMessage.includes("não foi possível conectar") || errorMessage.includes("fetch") || errorMessage.includes("Failed to fetch")) {
      errorMessage = "Servidor não está acessível. Verifique se o servidor Laravel está rodando em https://api.gruposaborparaense.com.br";
    } else if (errorMessage.includes("401") || errorMessage.includes("Credenciais") || errorMessage.includes("incorretos")) {
      errorMessage = "Email ou senha incorretos. Verifique suas credenciais.";
    } else if (errorMessage.includes("500")) {
      errorMessage = "Erro no servidor. Verifique os logs do Laravel.";
    } else if (errorMessage.includes("CORS")) {
      errorMessage = "Erro de CORS. Verifique a configuração do servidor.";
    }
    
    showToast(errorMessage, "error");
  } finally {
    // Reabilita botão
    if (submitBtn) {
      submitBtn.disabled = false;
      if (originalText) submitBtn.textContent = originalText;
    }
    console.log('=== FIM DO LOGIN ===');
  }
}

function handleLogout() {
  stopInactivityTimer();
  // Registra logout no audit (antes de limpar usuário)
  if (currentUser && currentUser.id) {
    fetch(`${API_URL}/audit-logs/registrar`, {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-Usuario-Id": String(currentUser.id), ...getDeviceHeaders() },
      body: JSON.stringify({ acao: "logout", recurso: "auth", descricao: "Logout realizado" }),
    }).catch(() => {});
  }
  clearUser();
  resetForms();
  dom.appShell.classList.add("hidden");
  dom.loginOverlay.classList.remove("hidden");
  setSidebarOpen(false);
  if (!stopMatrixAnimation) {
    stopMatrixAnimation = initMatrixBackground();
  }
}

let submittingProduto = false;

async function submitProduto(event) {
  event.preventDefault();
  if (submittingProduto) return;
  submittingProduto = true;
  console.log('🚀 === SUBMIT PRODUTO INICIADO ===');
  
  const form = dom.produtosForm || document.getElementById('produtoForm');
  
  if (!form) {
    console.error('❌ Formulário não encontrado!');
    showToast("Erro: Formulário não encontrado.", "error");
    return;
  }
  
  console.log('📝 Coletando dados do formulário...');
  
  // Coleta codigo_barras e verifica se não é o texto placeholder
  const codigoBarrasValue = form.elements.codigo_barras.value.trim();
  const codigoBarras = (codigoBarrasValue && codigoBarrasValue !== 'Gerado automaticamente') 
    ? codigoBarrasValue 
    : null;
  
  const custoVal = Number(form.elements.custo_medio?.value || 0);
  const estoqueVal = Number(form.elements.estoque_minimo?.value || 0);
  const payload = {
    nome: form.elements.nome.value.trim(),
    categoria: form.elements.categoria.value,
    unidade_base: form.elements.unidade_base.value,
    codigo_barras: codigoBarras,
    descricao: form.elements.descricao.value.trim() || null,
    custo_medio: isNaN(custoVal) ? 0 : custoVal,
    estoque_minimo: isNaN(estoqueVal) ? 0 : estoqueVal,
    unidade_id: form.elements.unidade_id?.value || null,
    ativo: Number(form.elements.ativo?.value || 1) || 1,
  };
  
  console.log('📊 Payload preparado:', payload);
  
  if (!payload.nome || !payload.categoria || !payload.unidade_base) {
    console.warn('⚠️ Validação falhou - campos obrigatórios vazios');
    showToast("Preencha os campos obrigatorios.", "error");
    return;
  }
  
  const id = form.elements.id.value;
  console.log('🔍 ID do produto:', id || 'NOVO PRODUTO');
  
  const submitBtn = form.querySelector('button[type="submit"]');
  const originalText = submitBtn?.textContent || 'Salvar';
  
  try {
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Salvando...';
    }
    
    console.log('📤 Enviando para API...');
    const url = id ? `/produtos/${id}` : "/produtos";
    const method = id ? "PUT" : "POST";
    console.log(`📍 ${method} ${url}`);
    
    const result = await fetchJSON(url, { 
      method: method, 
      body: JSON.stringify(payload) 
    });
    
    console.log('✅ Resposta da API:', result);
    showToast("Produto salvo com sucesso!", "success");
    toggleModal(dom.produtosModal, false);
    form.reset();
    
    console.log('🔄 Recarregando lista de produtos...');
    await loadProdutos();
    console.log('✅ Produto salvo e lista atualizada!');
    
  } catch (err) {
    console.error('❌ Erro ao salvar produto:', err);
    console.error('❌ Stack:', err.stack);
    showToast(err.message || "Falha ao salvar produto.", "error");
  } finally {
    submittingProduto = false;
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;
    }
    console.log('🔄 === FIM SUBMIT PRODUTO ===');
  }
}

async function submitUnidade(event, formOverride = null) {
  event.preventDefault();
  const form = formOverride || dom.unidadeForm;
  if (!form) return;
  const isModal = form === dom.unidadeForm;

  const formData = new FormData(form);
  const id = formData.get("id") || "";
  formData.delete("id");
  const data = Object.fromEntries(formData.entries());

  data.nome = (data.nome || "").trim();
  if (!data.nome) {
    showToast("Informe o nome da unidade.", "error");
    return;
  }
  data.endereco = (data.endereco || "").trim();
  data.cnpj = (data.cnpj || "").trim();
  data.telefone = (data.telefone || "").trim();
  data.email = (data.email || "").trim();
  data.observacoes = (data.observacoes || "").trim();

  const gerenteValor = (data.gerente_usuario_id || "").trim();
  if (!gerenteValor) {
    data.gerente_usuario_id = null;
  } else {
    const numero = Number(gerenteValor);
    if (Number.isNaN(numero)) {
      showToast("Selecione um gerente valido.", "error");
      return;
    }
    data.gerente_usuario_id = numero;
  }

  try {
    await fetchJSON(id ? `/unidades/${id}` : "/unidades", { method: id ? "PUT" : "POST", body: JSON.stringify(data) });
    showToast("Unidade salva com sucesso!", "success");
    if (isModal) {
      toggleModal(dom.unidadeModal, false);
    } else {
      form.reset();
      refreshGerenteSelect();
      state.unidadeInlineVisivel = false;
      dom.unidadeInlineFormCard?.classList.add("hidden");
      applyPermissions();
    }
    await loadUnidades();
    applyPermissions();
  } catch (err) {
    showToast(err.message || "Falha ao salvar unidade.", "error");
  }
}

let submittingUsuario = false;

function updateUsuarioAtendeCaixaVisibility() {
  const wrap = document.getElementById("usuarioAtendeCaixaWrap");
  const cb = document.getElementById("usuarioAtendeCaixa");
  const perfil = (dom.usuarioForm?.elements?.perfil?.value || "").toUpperCase();
  if (!wrap) return;
  const show = perfil === "ATENDENTE";
  wrap.classList.toggle("hidden", !show);
  if (!show && cb) cb.checked = false;
}

async function submitUsuario(event) {
  event.preventDefault();
  if (submittingUsuario) return;
  submittingUsuario = true;

  const form = dom.usuarioForm;
  if (!form) {
    submittingUsuario = false;
    showToast("Formulario nao encontrado.", "error");
    return;
  }

  const id = form.elements.id.value;
  const nome = form.elements.nome.value.trim();
  const email = form.elements.email.value.trim();
  const perfil = form.elements.perfil.value.trim().toUpperCase();
  const senha = form.elements.senha.value.trim();
  const confirmar = form.elements.confirmar_senha.value.trim();
  const unidade_id = form.elements.unidade_id.value || null;
  const ativo = Number(form.elements.ativo.value);

  const submitBtn = form.querySelector('button[type="submit"]');
  const originalText = submitBtn?.textContent || "Salvar";

  try {
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = "Salvando...";
    }

    if (!nome || !email || !perfil) {
      showToast("Preencha os campos obrigatorios.", "error");
      return;
    }
    if (!id && !senha) {
      showToast("Informe uma senha para o novo usuario.", "error");
      return;
    }
    if (senha) {
      if (senha !== confirmar) {
        showToast("As senhas nao conferem.", "error");
        return;
      }
      if (senha.length < 6) {
        showToast("A senha deve ter no minimo 6 caracteres.", "error");
        return;
      }
    }

    const permModules = Array.from(document.querySelectorAll('input[name="perm_module"]:checked')).map(cb => cb.value);
    const payload = { nome, email, perfil, ativo };
    if (senha) payload.senha = senha;
    if (unidade_id && unidade_id !== "null") payload.unidade_id = Number(unidade_id);
    payload.permissoes_menu = permModules;
    if (perfil === "ATENDENTE") {
      payload.atende_caixa = form.elements.atende_caixa?.checked ? 1 : 0;
    }

    const temFoto = !!usuarioFotoFile;
    const temRemoverFoto = !!usuarioFotoRemovida;
    let resultado;

    if (temFoto || temRemoverFoto) {
      const formData = new FormData();
      formData.append("nome", nome);
      formData.append("email", email);
      formData.append("perfil", perfil);
      formData.append("ativo", ativo.toString());
      if (senha) formData.append("senha", senha);
      if (unidade_id && unidade_id !== "null") formData.append("unidade_id", unidade_id);
      if (usuarioFotoRemovida) formData.append("remove_foto", "1");
      if (usuarioFotoFile) formData.append("foto", usuarioFotoFile);
      formData.append("permissoes_menu", JSON.stringify(permModules));
      if (perfil === "ATENDENTE") {
        formData.append("atende_caixa", form.elements.atende_caixa?.checked ? "1" : "0");
      }
      resultado = await fetchForm(id ? `/usuarios/${id}` : "/usuarios", id ? "PUT" : "POST", formData);
    } else {
      resultado = await fetchJSON(id ? `/usuarios/${id}` : "/usuarios", {
        method: id ? "PUT" : "POST",
        body: JSON.stringify(payload),
      });
    }

    showToast("Usuario salvo com sucesso!", "success");
    toggleModal(dom.usuarioModal, false);
    form.reset();
    usuarioFotoFile = null;
    usuarioFotoRemovida = false;
    await loadUsuarios(true);

  } catch (err) {
    showToast(err.message || "Falha ao salvar usuario.", "error");
  } finally {
    submittingUsuario = false;
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;
    }
  }
}

async function submitEntrada(event) {
  event.preventDefault();
  const submitButton = dom.entradaForm?.querySelector('button[type="submit"]');
  const submitLabel = submitButton?.textContent || "";
  const data = Object.fromEntries(new FormData(dom.entradaForm).entries());
  data.qtd = Number(data.qtd || 0);
  data.custo_unitario = parseCurrencyInput(dom.entradaForm?.elements.custo_unitario);
  data.usuario_id = currentUser?.id;
  if (!data.usuario_id) {
    showToast("Efetue login primeiro.", "error");
    return;
  }
  if (!Number.isFinite(data.qtd) || data.qtd <= 0) {
    showToast("Informe uma quantidade valida.", "error");
    return;
  }
  if (!Number.isFinite(data.custo_unitario) || data.custo_unitario < 0) {
    showToast("Informe um custo unitario valido.", "error");
    return;
  }
  const movFiltrosAtuais = collectMovimentacoesFiltros();
  const relFiltrosAtuais = dom.relatorioFilterForm ? collectRelatorioFiltros() : null;
  if (submitButton) {
    submitButton.disabled = true;
    submitButton.textContent = "Salvando...";
  }
  try {
    // Validação adicional antes de enviar
    if (!data.produto_id || !Number(data.produto_id)) {
      showToast("❌ Selecione um produto válido.", "error");
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = submitLabel || "Registrar entrada";
      }
      return;
    }
    
    if (!data.unidade_id || !Number(data.unidade_id)) {
      showToast("❌ Selecione uma unidade válida.", "error");
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = submitLabel || "Registrar entrada";
      }
      return;
    }
    
    // numero_lote é opcional: se vazio, o backend gera automaticamente
    const dadosEntrada = {
      produto_id: Number(data.produto_id),
      unidade_id: Number(data.unidade_id),
      quantidade: data.qtd,
      qtd: data.qtd,
      custo_unitario: data.custo_unitario,
      usuario_id: data.usuario_id,
      numero_lote: data.numero_lote?.trim() || undefined,
      data_validade: data.data_validade || undefined,
      local_id: data.local_id ? Number(data.local_id) : undefined,
      motivo: data.motivo || undefined,
      origem: "DASHBOARD",
    };
    console.log("Enviando dados de entrada:", { ...dadosEntrada });
    const resposta = await registrarEntradaEstoque(dadosEntrada);
    console.log("Resposta da API de entrada:", resposta);
    
    // Mensagem de sucesso detalhada
    if (resposta.details) {
      const detalhes = resposta.details;
      showToast(
        `✅ Entrada registrada com sucesso! ${detalhes.produto || 'Produto'} - ${detalhes.quantidade || data.qtd} ${detalhes.unidade || ''}`,
        "success"
      );
    } else {
      showToast(resposta.message || "✅ Entrada registrada com sucesso!", "success");
    }
    
    dom.entradaForm?.reset();
    if (dom.entradaUnidadeSelect) dom.entradaUnidadeSelect.value = "";
    resetEntradaLocalSelect();
    handleEntradaUnidadeChange();
    toggleModal(dom.entradaModal, false);
    const possuiFiltros = Object.values(movFiltrosAtuais || {}).some((valor) => Boolean(valor));
    const atualizacoes = [
      loadDashboard().catch((err) => {
        showToast(err?.message || "Falha ao atualizar dashboard.", "error");
      }),
      loadLotes().catch((err) => {
        showToast(err?.message || "Falha ao atualizar lotes.", "error");
      }),
    ];
    await Promise.all(atualizacoes);
    await loadMovimentacoesDetalhadas().catch((err) => {
      showToast(err?.message || "Falha ao atualizar movimentacoes.", "error");
    });
    if (relFiltrosAtuais) {
      await loadRelatorio(relFiltrosAtuais).catch((err) => {
        showToast(err?.message || "Falha ao atualizar relatorios.", "error");
      });
    }
    if (possuiFiltros) {
      await loadMovimentacoesDetalhadas(movFiltrosAtuais).catch((err) => {
        showToast(err?.message || "Falha ao atualizar movimentacoes.", "error");
      });
    }
  } catch (err) {
    console.error("Erro ao registrar entrada:", err);
    
    // Tenta extrair dados estruturados do erro
    let errorData = {
      error: "Erro ao registrar entrada",
      message: "Não foi possível registrar a entrada. Verifique os dados e tente novamente."
    };
    
    if (err.responseData) {
      // Se o fetchJSON retornou dados estruturados do backend
      errorData = {
        error: err.responseData.error || "Erro ao registrar entrada",
        message: err.responseData.message || err.message || "Não foi possível registrar a entrada.",
        produto: err.responseData.produto,
        disponivel: err.responseData.disponivel,
        solicitado: err.responseData.solicitado
      };
    } else if (err.message) {
      // Fallback para mensagens de texto simples
      errorData.message = err.message;
      
      // Tenta identificar o tipo de erro pela mensagem
      if (err.message.includes("Produto não encontrado") || err.message.includes("não existe")) {
        errorData.error = "Produto não encontrado";
        errorData.message = "O produto selecionado não existe no sistema. Verifique o produto e tente novamente.";
      } else if (err.message.includes("Produto inativo") || err.message.includes("inativo")) {
        errorData.error = "Produto inativo";
        errorData.message = "O produto selecionado está inativo. Ative o produto antes de registrar entrada.";
      } else if (err.message.includes("Dados inválidos") || err.message.includes("inválid")) {
        errorData.error = "Dados inválidos";
        errorData.message = "Verifique os campos preenchidos e tente novamente.";
      }
    }
    
    // Mostra o modal de erro detalhado
    showErrorModal(errorData);
  } finally {
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.textContent = submitLabel || "Registrar entrada";
    }
  }
}

async function submitLote(event) {
  event.preventDefault();
  console.log("🔵 submitLote chamado");
  
  if (!dom.loteForm) {
    console.error("❌ dom.loteForm não encontrado!");
    showToast("Formulário não encontrado.", "error");
    return;
  }
  
  if (!canManageProdutos()) {
    showToast("Sem permissao para registrar lotes.", "warning");
    return;
  }
  
  const submitBtn = dom.loteForm.querySelector('button[type="submit"]');
  const submitLabel = submitBtn?.textContent || "";
  const formData = new FormData(dom.loteForm);
  const data = Object.fromEntries(formData.entries());
  
  console.log("📋 Dados do formulário:", data);
  
  // Remove campo id se estiver vazio (para criação)
  if (data.id && (!data.id.trim() || data.id === "0" || data.id === "undefined")) {
    delete data.id;
  }
  const produtoId = Number(data.produto_id);
  const unidadeId = Number(data.unidade_id);
  if (!Number.isFinite(produtoId) || produtoId <= 0) {
    showToast("Selecione o produto.", "error");
    return;
  }
  if (!Number.isFinite(unidadeId) || unidadeId <= 0) {
    showToast("Selecione a unidade.", "error");
    return;
  }
  const quantidade = Number(data.quantidade || 0);
  const custoUnitarioInput = dom.loteForm.elements.custo_unitario;
  
  if (!custoUnitarioInput) {
    console.error("❌ Campo custo_unitario não encontrado no formulário!");
    showToast("Campo de custo unitário não encontrado.", "error");
    return;
  }
  
  // Tenta obter o valor do dataset primeiro, depois do input
  let custoUnitario = 0;
  if (custoUnitarioInput.dataset.value && custoUnitarioInput.dataset.value !== "") {
    custoUnitario = Number(custoUnitarioInput.dataset.value);
  } else {
    custoUnitario = parseCurrencyInput(custoUnitarioInput);
  }
  
  console.log("💰 Custo unitário:", {
    datasetValue: custoUnitarioInput.dataset.value,
    inputValue: custoUnitarioInput.value,
    parseado: custoUnitario
  });
  
  if (!Number.isFinite(quantidade) || quantidade <= 0) {
    showToast("Informe a quantidade do lote.", "error");
    return;
  }
  
  if (!Number.isFinite(custoUnitario) || custoUnitario <= 0) {
    console.error("❌ Custo unitário inválido:", {
      custoUnitario,
      inputValue: custoUnitarioInput.value,
      datasetValue: custoUnitarioInput.dataset.value,
      isFinite: Number.isFinite(custoUnitario),
      isPositive: custoUnitario > 0
    });
    showToast("Informe o custo unitário válido (maior que zero).", "error");
    return;
  }
  const status = (data.status || "ATIVO").toUpperCase();
  const STATUS_VALIDOS = new Set(["ATIVO", "BLOQUEADO", "VENCIDO", "ESGOTADO"]);
  if (!STATUS_VALIDOS.has(status)) {
    showToast("Status invalido.", "error");
    return;
  }
  // Valida código do lote antes de continuar
  const codigoLote = (data.codigo_lote || "").trim();
  if (!codigoLote) {
    showToast("Informe o codigo do lote.", "error");
    return;
  }
  
  const payload = {
    produto_id: produtoId,
    unidade_id: unidadeId,
    codigo_lote: codigoLote,
    quantidade,
    custo_unitario: custoUnitario,
    data_fabricacao: (data.data_fabricacao && data.data_fabricacao.trim()) ? data.data_fabricacao.trim() : null,
    data_validade: (data.data_validade && data.data_validade.trim()) ? data.data_validade.trim() : null,
    fornecedor: (data.fornecedor || "").trim() || null,
    nota_fiscal: (data.nota_fiscal || "").trim() || null,
    localizacao: (data.localizacao || "").trim() || null,
    status,
    observacoes: (data.observacoes || "").trim() || null,
  };
  // Verifica se é edição ou criação
  const id = data.id ? String(data.id).trim() : null;
  const isEdit = id && id !== "" && !isNaN(Number(id));
  
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = "Salvando...";
  }
  try {
    console.log("📤 Enviando lote:", { isEdit, id, payload });
    
    // Prepara payload apenas com campos que o backend espera
    const payloadBackend = {
      produto_id: payload.produto_id,
      unidade_id: payload.unidade_id,
      codigo_lote: payload.codigo_lote,
      quantidade: payload.quantidade,
      custo_unitario: payload.custo_unitario,
      data_fabricacao: payload.data_fabricacao || null,
      data_validade: payload.data_validade || null,
    };
    
    // Adiciona local_id apenas se existir no formulário
    if (data.local_id && data.local_id.trim()) {
      const localId = Number(data.local_id);
      if (Number.isFinite(localId) && localId > 0) {
        payloadBackend.local_id = localId;
      }
    }
    
    // Validação final antes de enviar
    if (!payloadBackend.produto_id || payloadBackend.produto_id <= 0) {
      throw new Error("Produto inválido");
    }
    if (!payloadBackend.unidade_id || payloadBackend.unidade_id <= 0) {
      throw new Error("Unidade inválida");
    }
    if (!payloadBackend.codigo_lote || payloadBackend.codigo_lote.trim() === "") {
      throw new Error("Código do lote é obrigatório");
    }
    if (!payloadBackend.quantidade || payloadBackend.quantidade <= 0) {
      throw new Error("Quantidade deve ser maior que zero");
    }
    if (!payloadBackend.custo_unitario || payloadBackend.custo_unitario <= 0) {
      throw new Error("Custo unitário deve ser maior que zero");
    }
    
    console.log("📤 Payload para backend:", payloadBackend);
    console.log("📤 URL:", isEdit ? `/lotes/${id}` : "/lotes");
    console.log("📤 Method:", isEdit ? "PUT" : "POST");
    
    const resultado = await fetchJSON(isEdit ? `/lotes/${id}` : "/lotes", { 
      method: isEdit ? "PUT" : "POST", 
      body: JSON.stringify(payloadBackend) 
    });
    
    console.log("✅ Lote salvo com sucesso:", resultado);
    showToast(isEdit ? "Lote atualizado com sucesso!" : "Lote cadastrado com sucesso!", "success");
    dom.loteForm.reset();
    if (dom.loteForm.elements.custo_unitario) {
      dom.loteForm.elements.custo_unitario.dataset.value = "";
      dom.loteForm.elements.custo_unitario.value = "";
    }
    if (dom.loteForm.elements.status) dom.loteForm.elements.status.value = "ATIVO";
    toggleModal(dom.loteModal, false);
    
    // Se foi criação (não edição) e usuário tem permissão, oferece imprimir etiqueta
    if (!isEdit && resultado?.id && podeImprimirEtiqueta()) {
      mostrarOpcoesEtiqueta(resultado.id);
    }
    
    await loadLotes();
    await loadDashboard();
  } catch (err) {
    console.error("❌ Erro ao salvar lote:", err);
    
    // Tenta extrair mensagem de erro mais detalhada
    let errorMessage = "Falha ao salvar lote.";
    
    // Verifica se há responseData (dados do backend)
    const responseData = err.responseData || {};
    
    if (err.message) {
      errorMessage = err.message;
    } else if (responseData.error) {
      errorMessage = responseData.error;
    } else if (responseData.message) {
      errorMessage = responseData.message;
    } else if (typeof err === 'string') {
      errorMessage = err;
    }
    
    // Se houver mensagens de validação do Laravel, mostra a primeira
    if (responseData.messages && typeof responseData.messages === 'object') {
      const firstError = Object.values(responseData.messages)[0];
      if (Array.isArray(firstError) && firstError.length > 0) {
        errorMessage = firstError[0];
      }
    }
    
    console.error("❌ Detalhes do erro:", {
      message: err.message,
      status: err.status,
      responseData: responseData
    });
    
    showToast(errorMessage, "error");
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = submitLabel || "Salvar lote";
    }
  }
}

async function submitLocal(event) {
  event.preventDefault();
  event.stopPropagation();
  if (!isAdmin()) {
    showToast("Apenas administradores podem criar ou editar locais.", "warning");
    return;
  }
  if (!dom.localForm) return;
  const form = dom.localForm;
  const submitBtn = form.querySelector('button[type="submit"]');
  const submitLabel = submitBtn?.textContent || "Salvar local";
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = "Salvando...";
  }
  const formData = new FormData(form);
  const payload = Object.fromEntries(formData.entries());
  const localId = Number(payload.id || 0) || null;
  payload.nome = (payload.nome || "").toString().trim();
  if (!payload.nome) {
    showToast("Informe o nome do local.", "error");
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = submitLabel; }
    return;
  }
  payload.unidade_id = Number(payload.unidade_id);
  if (!Number.isFinite(payload.unidade_id) || payload.unidade_id <= 0) {
    showToast("Selecione a unidade do local.", "error");
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = submitLabel; }
    return;
  }
  payload.tipo = (payload.tipo || "").toString().trim().toUpperCase();
  if (!payload.tipo) {
    showToast("Selecione o tipo do local.", "error");
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = submitLabel; }
    return;
  }
  payload.temperatura_media = payload.temperatura_media ? Number(payload.temperatura_media) : null;
  payload.descricao = (payload.descricao || "").toString().trim() || null;
  payload.responsavel = (payload.responsavel || "").toString().trim() || null;
  payload.nivel_acesso = (payload.nivel_acesso || "").toString().trim() || null;
  payload.observacoes = (payload.observacoes || "").toString().trim() || null;
  payload.data_cadastro = (payload.data_cadastro || "").toString().trim() || null;
  delete payload.id;
  const endpoint = localId ? `/locais/${localId}` : "/locais";
  const method = localId ? "PUT" : "POST";
  try {
    const criado = await fetchJSON(endpoint, { method, body: JSON.stringify(payload) });
    showToast(localId ? "Local atualizado com sucesso!" : "Local cadastrado com sucesso!", "success");
    form.reset();
    if (dom.localTipoSelect) dom.localTipoSelect.value = "";
    if (dom.localNivelAcessoSelect) dom.localNivelAcessoSelect.value = "";
    if (form.elements.id) form.elements.id.value = "";
    if (dom.localModalTitle) dom.localModalTitle.textContent = "Novo local";
    toggleModal(dom.localModal, false);
    await loadLocais(true);
    refreshUnidadeSelects();
    if (String(dom.entradaUnidadeSelect?.value || "") === String(payload.unidade_id)) {
      await handleEntradaUnidadeChange();
    }
    return criado;
  } catch (err) {
    showToast(err?.message || "Falha ao salvar local.", "error");
    return null;
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = submitLabel;
    }
  }
}

// Flag global para prevenir chamadas duplicadas
let submittingSaida = false;

async function submitSaida(event) {
  console.log("🚀 submitSaida chamada");
  
  // ✅ Previne processamento duplicado
  if (submittingSaida) {
    console.warn("⚠️ submitSaida já está sendo processada, ignorando chamada duplicada");
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }
    return;
  }
  
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }
  
  if (!dom.saidaForm) {
    console.error("❌ Formulário de saída não encontrado");
    showToast("Formulário não encontrado.", "error");
    return;
  }
  
  console.log("✅ Formulário encontrado:", dom.saidaForm);
  
  const submitButton = dom.saidaForm.querySelector('button[type="submit"]');
  const submitLabel = submitButton?.textContent || "";
  
  if (!submitButton) {
    console.error("❌ Botão de submit não encontrado");
    showToast("Botão de submit não encontrado.", "error");
    return;
  }
  
  console.log("✅ Botão de submit encontrado:", submitButton);
  
  // Marca como processando
  submittingSaida = true;
  
  // Coleta dados do formulário - campos disabled não são incluídos no FormData, então pegamos manualmente
  const formData = new FormData(dom.saidaForm);
  const data = Object.fromEntries(formData.entries());
  
  // Garante que produto_id seja capturado mesmo se o select estiver disabled
  const produtoSelect = dom.saidaProdutoSelect || dom.saidaForm.querySelector('select[name="produto_id"]');
  if (produtoSelect) {
    data.produto_id = produtoSelect.value || data.produto_id || "";
  }
  
  // Garante que de_unidade_id seja capturado
  const origemSelect = dom.saidaOrigemSelect || dom.saidaForm.querySelector('select[name="de_unidade_id"]');
  if (origemSelect) {
    data.de_unidade_id = origemSelect.value || data.de_unidade_id || "";
  }
  
  // Garante que motivo seja capturado
  const motivoSelect = dom.saidaMotivo || dom.saidaForm.querySelector('select[name="motivo"]');
  if (motivoSelect) {
    data.motivo = motivoSelect.value || data.motivo || "";
  }
  
  // Garante que para_unidade_id seja capturado se for transferência
  const destinoSelect = dom.saidaDestinoSelect || dom.saidaForm.querySelector('select[name="para_unidade_id"]');
  if (destinoSelect && data.motivo === "TRANSFERENCIA") {
    data.para_unidade_id = destinoSelect.value || data.para_unidade_id || "";
  }
  
  // Coleta o lote selecionado (opcional)
  const loteSelectVal = dom.saidaLoteSelect?.value ?? "";
  if (loteSelectVal === "__manual__") {
    const manual = (dom.saidaLoteManualInput?.value ?? "").trim();
    if (manual) data.codigo_lote = manual;
  } else if (loteSelectVal) {
    data.codigo_lote = loteSelectVal;
  }

  console.log("📤 Dados coletados do formulário de saída:", data);
  
  data.motivo = (data.motivo || "").toString().trim().toUpperCase();
  data.forcar = Boolean(dom.saidaForm.querySelector('input[name="forcar"]')?.checked);
  data.qtd = Number(data.qtd ?? 0);
  if (!Number.isFinite(data.qtd) || data.qtd <= 0) {
    submittingSaida = false;
    showToast("Erro: Informe uma quantidade válida.", "error");
    return;
  }
  data.produto_id = Number(data.produto_id);
  if (!Number.isFinite(data.produto_id) || data.produto_id <= 0) {
    submittingSaida = false;
    showToast("Erro: Selecione o produto para a saída.", "error");
    return;
  }
  data.usuario_id = Number(currentUser?.id);
  if (!Number.isFinite(data.usuario_id) || data.usuario_id <= 0) {
    submittingSaida = false;
    showToast("Erro: Efetue login primeiro.", "error");
    return;
  }
  const motivosValidos = new Set(["PRODUCAO", "CONSUMO", "PERDA", "TRANSFERENCIA"]);
  if (!motivosValidos.has(data.motivo)) {
    submittingSaida = false;
    showToast("Erro: Informe o motivo da saída.", "error");
    return;
  }
  if (!data.de_unidade_id) {
    submittingSaida = false;
    showToast("Erro: Selecione a unidade de origem.", "error");
    return;
  }
  data.de_unidade_id = Number(data.de_unidade_id);
  if (!Number.isFinite(data.de_unidade_id) || data.de_unidade_id <= 0) {
    submittingSaida = false;
    showToast("Erro: Unidade de origem inválida.", "error");
    return;
  }
  const isTransferencia = data.motivo === "TRANSFERENCIA";
  if (isTransferencia) {
    if (!data.para_unidade_id) {
      submittingSaida = false;
      showToast("Erro: Selecione a unidade destino da transferência.", "error");
      return;
    }
    data.para_unidade_id = Number(data.para_unidade_id);
    if (!Number.isFinite(data.para_unidade_id) || data.para_unidade_id <= 0) {
      submittingSaida = false;
      showToast("Erro: Unidade destino inválida.", "error");
      return;
    }
    if (data.para_unidade_id === data.de_unidade_id) {
      submittingSaida = false;
      showToast("Erro: Origem e destino devem ser diferentes.", "error");
      return;
    }
  } else {
    delete data.para_unidade_id;
  }
  if (!data.usuario_id) {
    submittingSaida = false;
    showToast("Erro: Efetue login primeiro.", "error");
    return;
  }
  const movFiltrosAtuais = collectMovimentacoesFiltros();
  const relFiltrosAtuais = dom.relatorioFilterForm ? collectRelatorioFiltros() : null;
  if (submitButton) {
    submitButton.disabled = true;
    submitButton.textContent = "Salvando...";
  }
  try {
    console.log("📤 Enviando dados para /saida:", data);
    const resultado = await fetchJSON("/saida", { method: "POST", body: JSON.stringify(data) });
    console.log("✅ Resposta do servidor:", resultado);
    
    // Mostra mensagem de sucesso
    showToast("Feito com sucesso!", "success");
    
    const possuiFiltros = Object.values(movFiltrosAtuais || {}).some((valor) => Boolean(valor));
    resetSaidaFromQR();
    dom.saidaForm?.reset();
    resetSaidaProdutoSelect();
    toggleModal(dom.saidaModal, false);
    const atualizacoes = [
      loadDashboard().catch((err) => {
        console.error("Erro ao atualizar dashboard:", err);
      }),
      loadLotes().catch((err) => {
        console.error("Erro ao atualizar lotes:", err);
      }),
    ];
    await Promise.all(atualizacoes);
    // Passa refreshDashboard: true para evitar renderização duplicada, pois loadDashboard() já renderiza
    await loadMovimentacoesDetalhadas({}, { refreshDashboard: true }).catch((err) => {
      console.error("Erro ao atualizar movimentações:", err);
    });
    if (possuiFiltros) {
      await loadMovimentacoesDetalhadas(movFiltrosAtuais).catch((err) => {
        console.error("Erro ao atualizar movimentações filtradas:", err);
      });
    }
    if (relFiltrosAtuais) {
      await loadRelatorio(relFiltrosAtuais).catch((err) => {
        console.error("Erro ao atualizar relatórios:", err);
      });
    }
  } catch (err) {
    console.error("❌ Erro ao registrar saída:", err);
    console.error("❌ Mensagem:", err.message);
    console.error("❌ Stack:", err.stack);
    
    // Extrai dados estruturados do erro do backend
    let errorData = {
      error: "Erro ao registrar saída",
      message: "Não foi possível registrar a saída. Verifique os dados e tente novamente."
    };
    
    if (err.responseData) {
      // Se o fetchJSON retornou dados estruturados do backend
      const responseData = err.responseData;
      errorData = {
        error: responseData.error || "Erro ao registrar saída",
        message: responseData.message || err.message || "Não foi possível registrar a saída.",
        produto: responseData.produto,
        disponivel: responseData.disponivel,
        solicitado: responseData.solicitado
      };
    } else if (err.message) {
      errorData.message = err.message;
      
      // Tenta identificar o tipo de erro pela mensagem
      if (err.message.includes("Sem estoque") || err.message.includes("estoque disponível")) {
        errorData.error = "Sem estoque disponível";
      } else if (err.message.includes("Estoque insuficiente")) {
        errorData.error = "Estoque insuficiente";
      } else if (err.message.includes("Nenhum lote") || err.message.includes("lote disponível")) {
        errorData.error = "Nenhum lote disponível";
      } else if (err.message.includes("Lotes vencidos")) {
        errorData.error = "Lotes vencidos bloqueiam a saída";
      } else if (err.message.includes("Produto não encontrado")) {
        errorData.error = "Produto não encontrado";
      } else if (err.message.includes("Produto inativo")) {
        errorData.error = "Produto inativo";
      }
    } else if (err.error) {
      errorData.error = err.error;
      errorData.message = err.error;
    } else if (typeof err === 'string') {
      errorData.message = err;
    }
    
    // Mostra o modal de erro detalhado
    showErrorModal(errorData);
  } finally {
    // ✅ Libera o flag de processamento
    submittingSaida = false;
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.textContent = submitLabel || "Registrar saida";
    }
  }
}

async function handleUsuarioTableClick(event) {
  try {
    const button = event.target.closest("button[data-action]");
    if (!button) return;
    const row = button.closest("tr");
    const id = row?.dataset.id;
    if (!id) return;
    const usuario = state.usuarios.find((item) => String(item.id) === String(id));
    if (!usuario) return;
    const action = button.dataset.action;
    if (action === "edit") {
      const form = dom.usuarioForm;
      const modal = dom.usuarioModal;
      const title = dom.usuarioModalTitle;
      if (!form || !modal) { showToast("Modal não encontrado.", "error"); return; }
      if (title) title.textContent = "Editar usuario";
      form.elements.id.value = usuario.id;
      form.elements.nome.value = usuario.nome || "";
      form.elements.email.value = usuario.email || "";
      const sel = form.elements.perfil;
      if (sel) { sel.disabled = false; sel.value = (usuario.perfil || "").toUpperCase(); }
      if (form.elements.unidade_id) form.elements.unidade_id.value = usuario.unidade_id || "";
      // Permissões de menu
      const pm = Array.isArray(usuario.permissoes_menu) ? usuario.permissoes_menu : (typeof usuario.permissoes_menu === 'string' ? (() => { try { const a = JSON.parse(usuario.permissoes_menu); return Array.isArray(a) ? a : []; } catch (e) { return []; } })() : []);
      document.querySelectorAll('input[name="perm_module"]').forEach(cb => {
        cb.checked = pm.includes(cb.value);
      });
      if (form.elements.ativo) form.elements.ativo.value = Number(usuario.ativo) === 1 ? "1" : "0";
      const acCb = document.getElementById("usuarioAtendeCaixa");
      if (acCb) {
        acCb.checked =
          Number(usuario.atende_caixa) === 1 ||
          usuario.atende_caixa === true ||
          String(usuario.atende_caixa) === "1";
      }
      updateUsuarioAtendeCaixaVisibility();
      if (form.elements.senha) form.elements.senha.value = "";
      if (form.elements.confirmar_senha) form.elements.confirmar_senha.value = "";
      usuarioFotoFile = null;
      usuarioFotoRemovida = false;
      if (dom.usuarioAvatarPreview) {
        const fotoUrl = getUsuarioFotoUrl(usuario.foto || usuario.foto_path);
        dom.usuarioAvatarPreview.innerHTML = fotoUrl
          ? `<img src="${fotoUrl}" alt="${escapeHtml(usuario.nome)}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" />`
          : '<span class="avatar-placeholder">?</span>';
      }
      toggleModal(modal, true);
    } else if (action === "disable" || action === "enable") {
      try {
        await fetchJSON(`/usuarios/${usuario.id}`, { method: "PUT", body: JSON.stringify({ ativo: action === "enable" ? 1 : 0 }) });
        showToast("Status do usuario atualizado.", "success");
        await loadUsuarios(true);
      } catch (err) {
        showToast(err.message, "error");
      }
    } else if (action === "delete") {
      if (!confirm("Remover permanentemente este usuario?")) return;
      try {
        await fetchJSON(`/usuarios/${usuario.id}`, { method: "DELETE" });
        showToast("Usuario removido.", "success");
        await loadUsuarios(true);
      } catch (err) {
        showToast(err.message || "Falha ao remover usuario.", "error");
      }
    }
  } catch (err) {
    showToast(err.message || "Erro ao processar acao.", "error");
  }
}

// Liga as interacoes nas tabelas para permitir edicao e acoes inline.
function setupTables() {
  dom.produtosTable?.addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action]");
    if (!button) return;
    const row = button.closest("tr");
    const id = row?.dataset.id;
    const produto = state.produtos.find((item) => String(item.id) === String(id));
    if (!produto) return;
    const action = button.dataset.action;
    if (action === "edit") {
      await loadUnidades(false);
      dom.produtoModalTitle.textContent = "Editar produto";
      dom.produtosForm.elements.id.value = produto.id;
      dom.produtosForm.elements.nome.value = produto.nome || "";
      dom.produtosForm.elements.categoria.value = produto.categoria || "";
      dom.produtosForm.elements.unidade_base.value = normalizarUnidadeBase(produto.unidade_base) || "";
      dom.produtosForm.elements.codigo_barras.value = produto.codigo_barras || "";
      dom.produtosForm.elements.descricao.value = produto.descricao || "";
      dom.produtosForm.elements.custo_medio.value = produto.custo_medio || "";
      dom.produtosForm.elements.estoque_minimo.value = produto.estoque_minimo || "";
      dom.produtosForm.elements.unidade_id.value = produto.unidade_id || "";
      dom.produtosForm.elements.ativo.value = Number(produto.ativo) === 1 ? "1" : "0";
      toggleModal(dom.produtosModal, true);
    } else if (action === "disable" || action === "enable") {
      try {
        await fetchJSON(`/produtos/${produto.id}`, { method: "PUT", body: JSON.stringify({ ativo: action === "enable" ? 1 : 0 }) });
        showToast("Status do produto atualizado.", "success");
        await loadProdutos();
      } catch (err) {
        showToast(err.message, "error");
      }
    } else if (action === "delete") {
      if (!confirm("Remover permanentemente este produto?")) return;
      try {
        await fetchJSON(`/produtos/${produto.id}/remover`, { method: "DELETE" });
        showToast("Produto removido.", "success");
        await loadProdutos();
      } catch (err) {
        showToast(err.message, "error");
      }
    }
  });

  dom.lotesManageTable?.addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action]");
    if (!button) return;
    const row = button.closest("tr");
    const id = row?.dataset.id;
    const lote = state.lotes?.find((item) => String(item.id) === String(id));
    if (!lote) return;
    const action = button.dataset.action;
    if (action === "edit") {
      if (!canManageProdutos()) {
        showToast("Sem permissao para editar lotes.", "warning");
        return;
      }
      await loadProdutos();
      await loadUnidades(false);
      if (dom.loteModalTitle) dom.loteModalTitle.textContent = "Editar lote";
      dom.loteForm.elements.id.value = lote.id;
      dom.loteForm.elements.produto_id.value = lote.produto_id || "";
      dom.loteForm.elements.unidade_id.value = lote.unidade_id || "";
      dom.loteForm.elements.codigo_lote.value = lote.codigo_lote || "";
      dom.loteForm.elements.quantidade.value = lote.quantidade || "";
      const custoUnitarioInput = dom.loteForm.elements.custo_unitario;
      if (custoUnitarioInput && lote.custo_unitario) {
        custoUnitarioInput.value = formatCurrencyBRL(lote.custo_unitario);
        custoUnitarioInput.dataset.value = lote.custo_unitario;
      }
      // Formata datas para o formato YYYY-MM-DD que o input type="date" espera
      const formatDateForInput = (dateValue) => {
        if (!dateValue) return "";
        try {
          // Se já está no formato YYYY-MM-DD, retorna direto
          if (/^\d{4}-\d{2}-\d{2}/.test(String(dateValue))) {
            return String(dateValue).split(' ')[0]; // Remove hora se houver
          }
          // Tenta parsear como Date
          const date = new Date(dateValue);
          if (!isNaN(date.getTime())) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, "0");
            const day = String(date.getDate()).padStart(2, "0");
            return `${year}-${month}-${day}`;
          }
        } catch (e) {
          console.warn("Erro ao formatar data para input:", e, dateValue);
        }
        return "";
      };
      
      dom.loteForm.elements.data_fabricacao.value = formatDateForInput(lote.data_fabricacao);
      dom.loteForm.elements.data_validade.value = formatDateForInput(lote.data_validade);
      dom.loteForm.elements.fornecedor.value = lote.fornecedor || "";
      dom.loteForm.elements.nota_fiscal.value = lote.nota_fiscal || "";
      dom.loteForm.elements.localizacao.value = lote.localizacao || "";
      dom.loteForm.elements.status.value = lote.status || "ATIVO";
      dom.loteForm.elements.observacoes.value = lote.observacoes || "";
      toggleModal(dom.loteModal, true);
    } else if (action === "etiqueta") {
      if (!podeImprimirEtiqueta()) {
        showToast("Sem permissão para imprimir etiquetas.", "error");
        return;
      }
      mostrarOpcoesEtiqueta(lote.id);
    } else if (action === "disable" || action === "enable") {
      if (!canManageProdutos()) {
        showToast("Sem permissao para alterar status de lotes.", "warning");
        return;
      }
      try {
        const novoStatus = action === "enable" ? 1 : 0;
        console.log("Atualizando lote:", lote.id, "ativo:", novoStatus);
        const resultado = await fetchJSON(`/lotes/${lote.id}`, { 
          method: "PUT", 
          body: JSON.stringify({ ativo: novoStatus }) 
        });
        console.log("Lote atualizado:", resultado);
        showToast("Status do lote atualizado.", "success");
        await loadLotes(collectLotesFiltros());
        await loadDashboard();
      } catch (err) {
        console.error("Erro ao atualizar lote:", err);
        showToast(err.message || "Erro ao atualizar status do lote.", "error");
      }
    } else if (action === "delete") {
      if (!canManageProdutos()) {
        showToast("Sem permissao para excluir lotes.", "warning");
        return;
      }
      if (!confirm("Remover permanentemente este lote?")) return;
      try {
        await fetchJSON(`/lotes/${lote.id}`, { method: "DELETE" });
        showToast("Lote removido.", "success");
        await loadLotes(collectLotesFiltros());
        await loadDashboard();
      } catch (err) {
        showToast(err.message || "Falha ao remover lote.", "error");
      }
    }
  });

  dom.unidadesTable?.addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action]");
    if (!button) return;
    const row = button.closest("tr");
    if (!row) return;
    const { id } = row.dataset;
    if (!id) return;
    const unidade = state.unidades.find((item) => String(item.id) === String(id));
    if (!unidade) return;
    const action = button.dataset.action;
    if (action === "edit") {
      if (!canManageUnidades()) {
        showToast("Sem permissao para gerenciar unidades.", "warning");
        return;
      }
      state.unidadeInlineVisivel = true;
      applyPermissions();
      if (dom.unidadeInlineForm) {
        dom.unidadeInlineForm.reset();
        if (dom.unidadeInlineForm.elements.id) dom.unidadeInlineForm.elements.id.value = unidade.id;
        dom.unidadeInlineForm.elements.nome.value = unidade.nome || "";
        dom.unidadeInlineForm.elements.endereco.value = unidade.endereco || "";
        dom.unidadeInlineForm.elements.cnpj.value = unidade.cnpj || "";
        dom.unidadeInlineForm.elements.telefone.value = unidade.telefone || "";
        dom.unidadeInlineForm.elements.email.value = unidade.email || "";
        dom.unidadeInlineForm.elements.observacoes.value = unidade.observacoes || "";
      }
      try {
        await loadUsuarios(true);
      } catch (err) {
        showToast(err.message || "Falha ao carregar usuarios.", "error");
      }
      refreshGerenteSelect(unidade.gerente_usuario_id);
      if (!state.usuarios.length) {
        showToast("Cadastre usuarios para atribuir um gerente.", "warning");
      }
      if (dom.unidadeInlineFormCard) {
        dom.unidadeInlineFormCard.classList.remove("hidden");
        dom.unidadeInlineFormCard.scrollIntoView({ behavior: "smooth", block: "start" });
      }
      dom.unidadeInlineForm?.elements.nome?.focus();
      return;
    } else if (action === "disable" || action === "enable") {
      const ativo = action === "enable" ? 1 : 0;
      const mensagem = ativo ? "Ativar esta unidade?" : "Desativar esta unidade?";
      if (!confirm(mensagem)) return;
      try {
        await fetchJSON(`/unidades/${unidade.id}`, { method: "PUT", body: JSON.stringify({ ativo }) });
        showToast(ativo ? "Unidade ativada." : "Unidade desativada.", "success");
        await loadUnidades();
        if (dom.unidadeInlineForm?.elements.id && dom.unidadeInlineForm.elements.id.value === String(unidade.id)) {
          dom.unidadeInlineForm.reset();
          dom.unidadeInlineForm.elements.id.value = "";
          state.unidadeInlineVisivel = false;
          applyPermissions();
        }
      } catch (err) {
        showToast(err.message, "error");
      }
    } else if (action === "delete") {
      if (!confirm("Remover permanentemente esta unidade?")) return;
      try {
        await fetchJSON(`/unidades/${unidade.id}/remover`, { method: "DELETE" });
        showToast("Unidade removida.", "success");
        await loadUnidades();
        if (dom.unidadeInlineForm?.elements.id && dom.unidadeInlineForm.elements.id.value === String(unidade.id)) {
          dom.unidadeInlineForm.reset();
          dom.unidadeInlineForm.elements.id.value = "";
          state.unidadeInlineVisivel = false;
          applyPermissions();
        }
      } catch (err) {
        showToast(err.message, "error");
      }
    }
  });

  dom.locaisTable?.addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action]");
    if (!button) return;
    if (!isAdmin()) {
      showToast("Apenas administradores podem gerenciar locais.", "warning");
      return;
    }
    const row = button.closest("tr");
    const id = row?.dataset.id;
    if (!id) return;
    let local = (state.locais || []).find((item) => String(item.id) === String(id));
    if (!local) {
      try {
        local = await fetchJSON(`/locais/${id}`);
      } catch (err) {
        showToast("Local não encontrado.", "error");
        return;
      }
    }
    const action = button.dataset.action;
    if (action === "edit") {
      try {
        await loadUnidades(false);
      } catch (err) {
        showToast(err?.message || "Falha ao carregar unidades.", "error");
        return;
      }
      if (dom.localForm) {
        dom.localForm.reset();
        if (dom.localForm.elements.id) dom.localForm.elements.id.value = local.id ?? "";
        if (dom.localForm.elements.nome) dom.localForm.elements.nome.value = local.nome || "";
        if (dom.localForm.elements.unidade_id) dom.localForm.elements.unidade_id.value = local.unidade_id || "";
        if (dom.localTipoSelect) dom.localTipoSelect.value = local.tipo || "";
        if (dom.localForm.elements.temperatura_media) {
          dom.localForm.elements.temperatura_media.value =
            local.temperatura_media !== null && local.temperatura_media !== undefined
              ? Number(local.temperatura_media)
              : "";
        }
        if (dom.localForm.elements.descricao) dom.localForm.elements.descricao.value = local.descricao || "";
        if (dom.localForm.elements.responsavel) dom.localForm.elements.responsavel.value = local.responsavel || "";
        if (dom.localNivelAcessoSelect) dom.localNivelAcessoSelect.value = local.nivel_acesso || "";
        if (dom.localForm.elements.data_cadastro) {
          dom.localForm.elements.data_cadastro.value = toInputDate(local.data_cadastro);
        }
        if (dom.localForm.elements.observacoes) dom.localForm.elements.observacoes.value = local.observacoes || "";
      }
      if (dom.localModalTitle) dom.localModalTitle.textContent = "Editar local";
      toggleModal(dom.localModal, true);
    } else if (action === "disable" || action === "enable") {
      const ativo = action === "enable" ? 1 : 0;
      try {
        await fetchJSON(`/locais/${local.id}/status`, { method: "PATCH", body: JSON.stringify({ ativo }) });
        showToast(ativo ? "Local ativado." : "Local desativado.", "success");
        await loadLocais(true);
        refreshUnidadeSelects();
      } catch (err) {
        showToast(err?.message || "Falha ao atualizar local.", "error");
      }
    } else if (action === "delete") {
      if (!confirm("Remover permanentemente este local?")) return;
      try {
        await fetchJSON(`/locais/${local.id}`, { method: "DELETE" });
        showToast("Local removido.", "success");
        await loadLocais(true);
        refreshUnidadeSelects();
      } catch (err) {
        showToast(err?.message || "Falha ao remover local.", "error");
      }
    }
  });

  dom.usuariosTable?.addEventListener("click", handleUsuarioTableClick);

  dom.listasComprasTable?.addEventListener("click", async (event) => {
    const row = event.target.closest("tr[data-id]");
    if (!row) return;
    if (event.target.closest("button")) return;
    await selecionarListaCompra(row.dataset.id);
  });

  dom.listaComprasItensTable?.addEventListener("click", (event) => {
    const button = event.target.closest("button[data-action]");
    if (!button) return;
    
    // Estoquista e Cozinha não podem editar/deletar itens existentes (ADMIN e GERENTE podem)
    if (canOnlyCreateAndAddItems()) {
      showToast("Você só pode adicionar novos itens, não editar ou excluir itens existentes.", "warning");
      return;
    }
    
    if (!listaPermiteEdicao()) return;
    const row = button.closest("tr[data-id]");
    if (!row) return;
    const item = state.listaCompraAtual?.itens?.find((it) => Number(it.id) === Number(row.dataset.id));
    if (!item) return;
    if (button.dataset.action === "edit") abrirItemCompraModal(item);
    else if (button.dataset.action === "delete") removerItemLista(item.id);
  });

  dom.listaComprasItensTable?.addEventListener("input", (event) => {
    // Estoquista e Cozinha não podem editar itens inline
    if (canOnlyCreateAndAddItems()) {
      event.target.disabled = true;
      showToast("Você só pode adicionar novos itens, não editar itens existentes.", "warning");
      return;
    }
    if (!listaPermiteEdicao()) return;
    const field = event.target?.dataset?.field;
    if (!field) return;
    const row = event.target.closest("tr[data-id]");
    if (!row) return;
    const itemId = Number(row.dataset.id);
    if (!itemId) return;
    
    let value = event.target.value;
    
    // Processa valores numéricos
    if (["quantidade_planejada", "quantidade_comprada", "valor_unitario"].includes(field)) {
      if (value === "" || value === null || value === undefined) {
        value = null;
      } else {
        const numero = Number(value);
        if (Number.isFinite(numero) && numero >= 0) {
          value = ["quantidade_planejada", "quantidade_comprada"].includes(field) 
            ? roundToQuantity(numero) 
            : roundToCurrency(numero);
        } else if (Number.isFinite(numero) && numero < 0) {
          // Garante que valores negativos sejam convertidos para 0
          value = 0;
          event.target.value = field === "valor_unitario" ? "0" : "0.00";
        } else {
          value = null;
        }
      }
      
      // Atualiza o total visualmente em tempo real
      atualizarTotalItemLinha(row);
    }
    
    queueItemUpdate(itemId, field, value);
  });

  dom.listaComprasItensTable?.addEventListener("change", async (event) => {
    if (!listaPermiteEdicao()) return;
    const field = event.target?.dataset?.field;
    if (!field) return;
    const row = event.target.closest("tr[data-id]");
    if (!row) return;
    const itemId = Number(row.dataset.id);
    if (!itemId) return;
    
    let value = event.target.value;
    
    // Processa diferentes tipos de campos
    if (field === "estabelecimento_id") {
      value = value ? Number(value) : null;
      
      // Salva imediatamente o estabelecimento_id (sem debounce)
      try {
        await fetchJSON(`/itens/${itemId}`, {
          method: "PUT",
          body: JSON.stringify({ estabelecimento_id: value }),
        });
        
        // Atualiza o item no state.listaCompraAtual
        if (state.listaCompraAtual?.itens) {
          const item = state.listaCompraAtual.itens.find(it => Number(it.id) === itemId);
          if (item) {
            item.estabelecimento_id = value;
            // Atualiza também o nome do estabelecimento se disponível
            const estabelecimentos = state.listaCompraAtual?.estabelecimentos || state.estabelecimentosGlobais || [];
            if (value && estabelecimentos.length > 0) {
              const estabelecimento = estabelecimentos.find(est => Number(est.id) === value);
              if (estabelecimento) {
                item.estabelecimento_nome = estabelecimento.nome;
              }
            } else {
              item.estabelecimento_nome = null;
            }
          }
        }
        
        // Atualiza a tabela de estabelecimentos para mostrar o novo vínculo
        if (state.listaCompraAtual?.estabelecimentos) {
          renderListaCompraEstabelecimentos(state.listaCompraAtual.estabelecimentos);
        }
        
        // Recarrega a lista para garantir que tudo está sincronizado
        if (state.listaCompraAtual?.id) {
          await selecionarListaCompra(state.listaCompraAtual.id, true);
        }
        
        showToast("Vínculo com estabelecimento atualizado.", "success");
      } catch (error) {
        showToast(error.message || "Erro ao atualizar vínculo.", "error");
        // Reverte o valor no select em caso de erro
        if (state.listaCompraAtual?.itens) {
          const item = state.listaCompraAtual.itens.find(it => Number(it.id) === itemId);
          if (item) {
            event.target.value = item.estabelecimento_id || "";
          }
        }
      }
      return; // Não processa mais, já salvou
    } else if (field === "status") {
      value = (value || "").toUpperCase();
    } else if (["quantidade_planejada", "quantidade_comprada"].includes(field)) {
      const numero = Number(value);
      if (Number.isFinite(numero) && numero >= 0) {
        value = roundToQuantity(numero);
        event.target.value = formatQuantityDisplay(value);
      } else {
        value = 0;
        event.target.value = "0.00";
      }
      // Atualiza o total quando quantidade muda
      atualizarTotalItemLinha(row);
    } else if (field === "valor_unitario") {
      const numero = Number(value);
      if (Number.isFinite(numero) && numero >= 0) {
        value = roundToCurrency(numero);
        event.target.value = formatNumber(value, 2);
      } else {
        value = 0;
        event.target.value = "0";
      }
      // Atualiza o total quando valor unitário muda
      atualizarTotalItemLinha(row);
    }
    
    queueItemUpdate(itemId, field, value);
  });

  dom.listaComprasEstabelecimentosTable?.addEventListener("click", (event) => {
    if (!canManageCompras()) return;
    const button = event.target.closest("button[data-action]");
    if (!button) return;
    const row = button.closest("tr[data-id]");
    if (!row) return;
    const estId = Number(row.dataset.id);
    if (!estId) return;
    // Busca o estabelecimento na lista atual
    const est = state.listaCompraAtual?.estabelecimentos?.find((item) => Number(item.id) === estId);
    if (!est) return;
    if (button.dataset.action === "editar") {
      abrirEstabelecimentoModal(est);
    } else if (button.dataset.action === "deletar") {
      deletarEstabelecimentoLista(estId);
    }
  });
}

// Configura filtros e botoes de busca usados nas paginas de dados.
function setupFilters() {
  const aplicarFiltrosLotes = async () => {
    await loadLotes(collectLotesFiltros()).catch((err) => showToast(err.message, "error"));
  };

  dom.lotesFilterForm?.addEventListener("submit", async (event) => {
    event.preventDefault();
    await aplicarFiltrosLotes();
  });

  dom.aplicarFiltrosLotes?.addEventListener("click", async (event) => {
    event.preventDefault();
    if (dom.lotesFilterForm && typeof dom.lotesFilterForm.requestSubmit === "function") {
      dom.lotesFilterForm.requestSubmit();
    } else if (dom.lotesFilterForm) {
      dom.lotesFilterForm.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }));
    } else {
      await aplicarFiltrosLotes();
    }
  });

  dom.limparFiltrosLotes?.addEventListener("click", () => {
    dom.lotesFilterForm?.reset();
    if (dom.lotesFiltroProdutoBusca) dom.lotesFiltroProdutoBusca.value = "";
    if (dom.lotesFiltroProduto) dom.lotesFiltroProduto.value = "";
    loadLotes().catch(() => {});
  });

  document.body.addEventListener("submit", async (e) => {
    if (e.target.id === "lotesFilterForm") {
      e.preventDefault();
      await loadLotes(collectLotesFiltros()).catch((err) => showToast(err?.message || "Erro ao aplicar filtros", "error"));
    }
  });
  document.body.addEventListener("click", (e) => {
    if (e.target.id === "limparFiltrosLotes" || e.target.closest("#limparFiltrosLotes")) {
      e.preventDefault();
      const form = document.getElementById("lotesFilterForm");
      if (form) form.reset();
      loadLotes().catch(() => {});
    }
  });

  // Listener para pesquisa por código - abre modal quando encontrar
  let pesquisaTimeout = null;
  dom.lotesFiltroPesquisa?.addEventListener("keydown", async (event) => {
    // Se pressionar Enter, busca imediatamente
    if (event.key === "Enter") {
      event.preventDefault();
      const codigo = dom.lotesFiltroPesquisa.value.trim();
      
      if (!codigo || codigo.length < 2) {
        showToast("Digite pelo menos 2 caracteres para pesquisar.", "warning");
        return;
      }
      
      try {
        // Busca na API
        const lotes = await fetchJSON(`/lotes?pesquisa=${encodeURIComponent(codigo)}`);
        
        if (lotes && lotes.length > 0) {
          // Pega o primeiro lote que corresponde exatamente ao código
          const loteEncontrado = lotes.find((lote) => {
            const codigoLote = (lote.codigo_lote || lote.numero_lote || "").toString().trim().toLowerCase();
            return codigoLote === codigo.toLowerCase();
          }) || lotes[0]; // Se não encontrar exato, pega o primeiro
          
          if (loteEncontrado && canManageProdutos()) {
            // Abre o modal de edição
            await loadProdutos();
            await loadUnidades(false);
            if (dom.loteModalTitle) dom.loteModalTitle.textContent = "Editar lote";
            dom.loteForm.elements.id.value = loteEncontrado.id;
            dom.loteForm.elements.produto_id.value = loteEncontrado.produto_id || "";
            dom.loteForm.elements.unidade_id.value = loteEncontrado.unidade_id || "";
            dom.loteForm.elements.codigo_lote.value = loteEncontrado.codigo_lote || loteEncontrado.numero_lote || "";
            dom.loteForm.elements.quantidade.value = loteEncontrado.quantidade || loteEncontrado.qtd_atual || "";
            const custoUnitarioInput = dom.loteForm.elements.custo_unitario;
            if (custoUnitarioInput && loteEncontrado.custo_unitario) {
              custoUnitarioInput.value = formatCurrencyBRL(loteEncontrado.custo_unitario);
              custoUnitarioInput.dataset.value = loteEncontrado.custo_unitario;
            }
            dom.loteForm.elements.data_fabricacao.value = loteEncontrado.data_fabricacao || "";
            dom.loteForm.elements.data_validade.value = loteEncontrado.data_validade || "";
            dom.loteForm.elements.fornecedor.value = loteEncontrado.fornecedor || "";
            dom.loteForm.elements.nota_fiscal.value = loteEncontrado.nota_fiscal || "";
            dom.loteForm.elements.localizacao.value = loteEncontrado.localizacao || "";
            dom.loteForm.elements.status.value = loteEncontrado.status || "ATIVO";
            dom.loteForm.elements.observacoes.value = loteEncontrado.observacoes || "";
            toggleModal(dom.loteModal, true);
            showToast("Lote encontrado e selecionado!", "success");
          }
        } else {
          showToast("Nenhum lote encontrado com esse código.", "warning");
        }
      } catch (err) {
        console.error("Erro ao buscar lote:", err);
        showToast("Erro ao buscar lote. Tente novamente.", "error");
      }
    }
  });

  dom.movFilterForm?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const filtros = collectMovimentacoesFiltros();
    console.log("Aplicando filtros de movimentações:", filtros);
    try {
      await loadMovimentacoesDetalhadas(filtros, { refreshDashboard: false });
      showToast("Filtros aplicados com sucesso!", "success");
    } catch (err) {
      console.error("Erro ao aplicar filtros:", err);
      showToast(err.message || "Erro ao aplicar filtros.", "error");
    }
  });

  dom.movFiltrosLimpar?.addEventListener("click", () => {
    dom.movFilterForm?.reset();
    // ✅ Carrega movimentações sem filtros ao limpar
    loadMovimentacoesDetalhadas({}, { refreshDashboard: true }).catch((err) => {
      showToast(err?.message || "Erro ao carregar movimentações.", "error");
    });
  });

  document.addEventListener("click", async (e) => {
    const btn = e.target.closest(".btn-excluir-movimentacao");
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    const id = btn.dataset.id;
    if (!id) return;
    const user = getUser();
    if (!user || (user.perfil || "").toString().toUpperCase() !== "ADMIN") {
      showToast("Apenas administradores podem excluir movimentações.", "warning");
      return;
    }
    if (!confirm("Excluir esta movimentação? O estoque será revertido automaticamente.")) return;
    btn.disabled = true;
    try {
      await fetchJSON(`/movimentacoes/${id}`, { method: "DELETE" });
      showToast("Movimentação excluída e estoque revertido.", "success");
      const filtros = typeof collectMovimentacoesFiltros === "function" ? collectMovimentacoesFiltros() : {};
      await loadMovimentacoesDetalhadas(filtros, { refreshDashboard: true });
    } catch (err) {
      showToast(err?.message || "Erro ao excluir movimentação.", "error");
      btn.disabled = false;
    }
  });

  dom.relatorioFilterForm?.addEventListener("submit", async (event) => {
    event.preventDefault();
    await loadRelatorio(collectRelatorioFiltros()).catch((err) => showToast(err.message, "error"));
  });

  dom.relatorioLimpar?.addEventListener("click", () => {
    dom.relatorioFilterForm?.reset();
    loadRelatorio().catch(() => {});
  });

  dom.relatorioExportCsv?.addEventListener("click", exportRelatorioCsv);
  dom.relatorioExportPdf?.addEventListener("click", exportRelatorioPdf);
}

// Organiza abertura, fechamento e estados default das modais do sistema.
function setupModals() {
  const produtoSearchEl = document.getElementById("produtoSearch");
  if (produtoSearchEl) {
    let produtoSearchTimeout;
    produtoSearchEl.addEventListener("input", () => {
      clearTimeout(produtoSearchTimeout);
      produtoSearchTimeout = setTimeout(() => loadProdutos(produtoSearchEl.value.trim()).catch(() => {}), 300);
    });
  }

  dom.openProdutoBtn?.addEventListener("click", async () => {
    dom.produtoModalTitle.textContent = "Cadastrar produto";
    dom.produtosForm?.reset();
    await loadUnidades(false);
    toggleModal(dom.produtosModal, true);
  });
  dom.closeProdutoBtn?.addEventListener("click", () => toggleModal(dom.produtosModal, false));
  dom.cancelProdutoBtn?.addEventListener("click", () => toggleModal(dom.produtosModal, false));

  dom.openUsuarioBtn?.addEventListener("click", () => {
    dom.usuarioModalTitle.textContent = "Cadastrar usuario";
    dom.usuarioForm?.reset();
    if (dom.usuarioForm?.elements.id) dom.usuarioForm.elements.id.value = "";
    if (dom.usuarioForm?.elements.unidade_id) dom.usuarioForm.elements.unidade_id.value = "";
    if (dom.usuarioForm?.elements.ativo) dom.usuarioForm.elements.ativo.value = "1";
    
    // Configura select de perfil baseado nas permissões
    const perfilSelect = dom.usuarioForm?.elements.perfil;
    if (perfilSelect) {
      // BAR só pode criar usuários BAR
      if (canManageUsuariosBar()) {
        // Remove todas as opções exceto BAR
        perfilSelect.innerHTML = '<option value="">Selecione</option><option value="BAR">Bar</option>';
        perfilSelect.value = "BAR";
        perfilSelect.disabled = true; // BAR não pode alterar o perfil
      } else {
        // Outros perfis veem todas as opções
        perfilSelect.innerHTML = `
          <option value="">Selecione</option>
          <option value="ADMIN">Administrador</option>
          <option value="ESTOQUISTA">Estoquista</option>
          <option value="COZINHA">Cozinha</option>
          <option value="BAR">Bar</option>
          <option value="FINANCEIRO">Financeiro</option>
          <option value="ASSISTENTE_ADMINISTRATIVO">Auxiliar Administrativo</option>
          <option value="GERENTE">Gerente</option>
          <option value="VISUALIZADOR">Visualizador</option>
          <option value="ATENDENTE">Atendente</option>
          <option value="ATENDENTE_CAIXA">Atendente Caixa</option>
        `;
        perfilSelect.value = "";
        perfilSelect.disabled = false;
      }
    }
    
    usuarioFotoFile = null;
    usuarioFotoRemovida = false;
    const acNovo = document.getElementById("usuarioAtendeCaixa");
    if (acNovo) acNovo.checked = false;
    updateUsuarioAtendeCaixaVisibility();
    toggleModal(dom.usuarioModal, true);
  });
  document.getElementById("usuarioPerfilSelect")?.addEventListener("change", updateUsuarioAtendeCaixaVisibility);
  dom.closeUsuarioBtn?.addEventListener("click", () => toggleModal(dom.usuarioModal, false));
  dom.cancelUsuarioBtn?.addEventListener("click", () => toggleModal(dom.usuarioModal, false));
  document.getElementById("usuarioPermissoesPadrao")?.addEventListener("click", () => {
    const perfil = dom.usuarioForm?.elements?.perfil?.value?.toUpperCase();
    const sections = (perfil && PERMISSOES[perfil]) ? PERMISSOES[perfil].sections : [];
    document.querySelectorAll('input[name="perm_module"]').forEach(cb => {
      cb.checked = sections.includes(cb.value);
    });
    showToast("Permissões preenchidas com o padrão do perfil.", "info");
  });

  // === Funcionários (RH) ===
  function populateFuncionarioUnidades(selectForm, selectFilter) {
    const opts = (state.unidades || []).map(u => `<option value="${u.id}">${(u.nome || "").replace(/</g, "&lt;")}</option>`).join("");
    if (selectForm) {
      const el = dom.funcionarioForm?.querySelector("[name=unidade_id]");
      if (el) el.innerHTML = '<option value="">Sem unidade</option>' + opts;
    }
    if (selectFilter && dom.funcionariosFiltroUnidade) {
      dom.funcionariosFiltroUnidade.innerHTML = '<option value="">Todas</option>' + opts;
    }
  }
  const FUNCIONARIO_ESCOLARIDADE_LABELS = {
    fundamental_incompleto: "Fundamental incompleto",
    fundamental_completo: "Fundamental completo",
    medio_incompleto: "Ensino médio incompleto",
    medio_completo: "Ensino médio completo",
    superior_incompleto: "Superior incompleto",
    superior_completo: "Superior completo",
    pos_concluida: "Pós-graduação concluída",
  };
  const FUNCIONARIO_FORMACAO_BLOCOS = [
    { key: "curso_complementar", titulo: "Curso / qualificação" },
    { key: "tecnico", titulo: "Curso técnico" },
    { key: "graduacao", titulo: "Graduação" },
    { key: "pos_graduacao", titulo: "Pós-graduação" },
  ];
  function getFuncionarioFormRecordId(form) {
    const el = document.getElementById("funcionarioRecordId") || form?.querySelector('input[name="id"]');
    return (el?.value || "").trim();
  }
  function setFuncionarioFormRecordId(form, value) {
    const el = document.getElementById("funcionarioRecordId") || form?.querySelector('input[name="id"]');
    if (el) el.value = value != null ? String(value) : "";
  }
  function formacaoItemsFromData(data, key) {
    if (!data || typeof data !== "object") return [];
    const raw = data[key];
    if (raw == null) return [];
    if (Array.isArray(raw)) return raw.filter((x) => x && typeof x === "object");
    if (typeof raw === "object") return [raw];
    return [];
  }
  function clearFuncionarioFormacaoLinhas(form) {
    if (!form) return;
    FUNCIONARIO_FORMACAO_BLOCOS.forEach(({ key }) => {
      const wrap = form.querySelector(`.formacao-linhas[data-formacao-key="${key}"]`);
      if (wrap) wrap.innerHTML = "";
    });
  }
  function addFuncionarioFormacaoLinha(form, key) {
    const tpl = document.getElementById("funcionarioFormacaoLinhaTpl");
    const wrap = form.querySelector(`.formacao-linhas[data-formacao-key="${key}"]`);
    if (!tpl || !wrap) return null;
    const node = tpl.content.firstElementChild.cloneNode(true);
    wrap.appendChild(node);
    return node;
  }
  function fillFuncionarioFormacaoLinha(row, b) {
    if (!row || !b || typeof b !== "object") return;
    const set = (f, v) => {
      const el = row.querySelector(`[data-f="${f}"]`);
      if (!el) return;
      if (el.type === "checkbox") el.checked = !!v;
      else if (v != null && v !== "") el.value = String(v);
    };
    set("curso", b.curso);
    set("instituicao", b.instituicao);
    set("local", b.local);
    set("data_inicio", b.data_inicio);
    set("data_conclusao", b.data_conclusao);
    set("em_andamento", b.em_andamento);
  }
  function readFuncionarioFormacaoLinha(row) {
    if (!row) return null;
    const g = (f) => row.querySelector(`[data-f="${f}"]`);
    const curso = (g("curso")?.value || "").trim();
    const instituicao = (g("instituicao")?.value || "").trim();
    const local = (g("local")?.value || "").trim();
    const data_inicio = g("data_inicio")?.value || "";
    const data_conclusao = g("data_conclusao")?.value || "";
    const em_andamento = !!(g("em_andamento")?.checked);
    if (!curso && !instituicao && !local && !data_inicio && !data_conclusao && !em_andamento) return null;
    return {
      curso: curso || "",
      instituicao: instituicao || "",
      local: local || "",
      data_inicio: data_inicio || null,
      data_conclusao: em_andamento ? null : (data_conclusao || null),
      em_andamento,
    };
  }
  function rowFormacaoTemAlgumCampo(row) {
    if (!row) return false;
    const g = (f) => row.querySelector(`[data-f="${f}"]`);
    if ((g("curso")?.value || "").trim()) return true;
    if ((g("instituicao")?.value || "").trim()) return true;
    if ((g("local")?.value || "").trim()) return true;
    if (g("data_inicio")?.value) return true;
    if (g("data_conclusao")?.value) return true;
    if (g("em_andamento")?.checked) return true;
    return false;
  }
  function setFormacaoLinhaConfirmada(row, confirmada) {
    if (!row) return;
    const g = (f) => row.querySelector(`[data-f="${f}"]`);
    const btnSalvar = row.querySelector(".btn-formacao-salvar-linha");
    const btnEditar = row.querySelector(".btn-formacao-editar-linha");
    const st = row.querySelector(".formacao-linha__status");
    if (confirmada) {
      row.dataset.formacaoConfirmada = "1";
      row.classList.add("formacao-linha--confirmada");
      ["curso", "instituicao", "local", "data_inicio", "data_conclusao"].forEach((f) => {
        const el = g(f);
        if (el) el.readOnly = true;
      });
      const ch = g("em_andamento");
      if (ch) ch.disabled = true;
      if (btnSalvar) btnSalvar.hidden = true;
      if (btnEditar) btnEditar.hidden = false;
      if (st) st.hidden = false;
    } else {
      delete row.dataset.formacaoConfirmada;
      row.classList.remove("formacao-linha--confirmada");
      ["curso", "instituicao", "local", "data_inicio", "data_conclusao"].forEach((f) => {
        const el = g(f);
        if (el) el.readOnly = false;
      });
      const ch = g("em_andamento");
      if (ch) ch.disabled = false;
      if (btnSalvar) btnSalvar.hidden = false;
      if (btnEditar) btnEditar.hidden = true;
      if (st) st.hidden = true;
    }
  }
  function validarFormacaoLinhaParaSalvar(row) {
    const g = (f) => row.querySelector(`[data-f="${f}"]`);
    const curso = (g("curso")?.value || "").trim();
    const instituicao = (g("instituicao")?.value || "").trim();
    if (!curso && !instituicao) {
      return "Informe pelo menos o nome do curso ou a instituição para salvar esta formação.";
    }
    return null;
  }
  function fillFuncionarioFormacaoFields(form, escolaridade, formacaoJson) {
    if (!form) return;
    clearFuncionarioFormacaoLinhas(form);
    if (form.elements.escolaridade) {
      form.elements.escolaridade.value = escolaridade != null && escolaridade !== "" ? String(escolaridade) : "";
    }
    let data = formacaoJson;
    if (typeof data === "string" && data.trim()) {
      try {
        data = JSON.parse(data);
      } catch (e) {
        data = null;
      }
    }
    FUNCIONARIO_FORMACAO_BLOCOS.forEach(({ key }) => {
      const items = formacaoItemsFromData(data, key);
      if (items.length === 0) {
        addFuncionarioFormacaoLinha(form, key);
        return;
      }
      items.forEach((b) => {
        const row = addFuncionarioFormacaoLinha(form, key);
        fillFuncionarioFormacaoLinha(row, b);
        setFormacaoLinhaConfirmada(row, true);
      });
    });
  }
  function collectFuncionarioFormacaoJson(form) {
    if (!form) return null;
    const out = {};
    FUNCIONARIO_FORMACAO_BLOCOS.forEach(({ key }) => {
      const wrap = form.querySelector(`.formacao-linhas[data-formacao-key="${key}"]`);
      if (!wrap) return;
      const arr = [];
      wrap.querySelectorAll(":scope > .formacao-linha").forEach((row) => {
        if (row.dataset.formacaoConfirmada !== "1") return;
        const one = readFuncionarioFormacaoLinha(row);
        if (one) arr.push(one);
      });
      if (arr.length) out[key] = arr;
    });
    return Object.keys(out).length ? out : null;
  }
  function renderFuncionarioFormacaoViewHtml(f, esc, field) {
    const escolaridadeTxt = FUNCIONARIO_ESCOLARIDADE_LABELS[f.escolaridade] || f.escolaridade || "";
    let formacao = f.formacao_json;
    if (typeof formacao === "string" && formacao.trim()) {
      try {
        formacao = JSON.parse(formacao);
      } catch (e) {
        formacao = null;
      }
    }
    const linhasBlocoInner = (b) => {
      if (!b || typeof b !== "object") return "";
      const p = [];
      if (b.curso) p.push(`<strong>Curso:</strong> ${esc(b.curso)}`);
      if (b.instituicao) p.push(`<strong>Instituição:</strong> ${esc(b.instituicao)}`);
      if (b.local) p.push(`<strong>Local:</strong> ${esc(b.local)}`);
      if (b.data_inicio) p.push(`<strong>Início:</strong> ${esc(b.data_inicio)}`);
      if (b.em_andamento) p.push("<strong>Situação:</strong> Em andamento");
      else if (b.data_conclusao) p.push(`<strong>Conclusão:</strong> ${esc(b.data_conclusao)}`);
      return p.length ? p.join("<br/>") : "";
    };
    let blocosHtml = "";
    FUNCIONARIO_FORMACAO_BLOCOS.forEach(({ key, titulo }) => {
      const items = formacaoItemsFromData(formacao, key);
      if (!items.length) return;
      const partes = items.map((b, idx) => {
        const inner = linhasBlocoInner(b);
        if (!inner) return "";
        const prefix = items.length > 1 ? `<span style="font-size:0.85rem;color:#607d8b;">Formação ${idx + 1}</span><br/>` : "";
        return `<div class="formacao-view-item" style="margin-top:8px;padding:8px 0 8px 10px;border-left:3px solid #90caf9;line-height:1.5;">${prefix}${inner}</div>`;
      }).filter(Boolean);
      if (!partes.length) return;
      blocosHtml += `<div class="view-field" style="grid-column:1/-1;"><div class="view-field-label">${esc(titulo)}</div><div class="view-field-value">${partes.join("")}</div></div>`;
    });
    const temFormacao = escolaridadeTxt || blocosHtml;
    if (!temFormacao) return "";
    return `
        <div class="form-section">
          <h3>Formação e educação</h3>
          <div class="view-fields-grid">
            ${escolaridadeTxt ? field("Escolaridade", escolaridadeTxt) : ""}
            ${blocosHtml}
          </div>
        </div>`;
  }
  async function openFuncionarioModal(editId = null) {
    try {
      if (!state.unidades?.length) {
        try { await loadUnidades(false); } catch (e) {
          showToast("Não foi possível carregar unidades. Tente novamente.", "warning");
        }
      }
    populateFuncionarioUnidades("unidade_id", true);
    dom.funcionarioForm?.reset();
    setFuncionarioFormRecordId(dom.funcionarioForm, editId || "");
    dom.funcionarioModalTitle.textContent = editId ? "Editar funcionário" : "Novo funcionário";
    const submitBtn = document.getElementById("funcionarioFormSubmit");
    if (submitBtn) submitBtn.textContent = editId ? "Salvar alterações" : "Salvar";
    if (dom.funcionarioAcessoArea) dom.funcionarioAcessoArea.classList.add("hidden");
    if (dom.funcionarioPossuiAcesso) dom.funcionarioPossuiAcesso.checked = false;

    // RH: garantir que não fique valor "sobrando" no vínculo do usuário
    const uidEl = document.getElementById("funcionarioUsuarioId");
    const loginEl = document.getElementById("funcionarioLoginUsuario");
    const senhaEl = document.getElementById("funcionarioSenhaUsuario");
    const perfilEl = document.getElementById("funcionarioPerfilUsuario");
    if (uidEl) uidEl.value = "";
    if (loginEl) loginEl.value = "";
    if (senhaEl) senhaEl.value = "";
    if (perfilEl) perfilEl.value = "FUNCIONARIO";
    if (dom.funcionarioUsuarioResumo) { dom.funcionarioUsuarioResumo.textContent = ""; dom.funcionarioUsuarioResumo.style.display = "none"; }

    funcionarioFotoFile = null;
    funcionarioFotoRemovida = false;
    if (editId) {
      const f = await fetchJSON(`/funcionarios/${editId}`);
      ["nome_completo","cpf","data_nascimento","sexo","estado_civil","unidade_id","whatsapp","email","data_admissao","status","observacoes","banco","agencia","conta","conta_digito","pix"].forEach(k => {
        const el = dom.funcionarioForm?.elements[k];
        if (el && f[k] != null) el.value = f[k] || "";
      });
      const cargoSel = dom.funcionarioForm?.querySelector('[name="cargo"]');
      if (cargoSel && f.cargo != null && String(f.cargo).trim() !== "") {
        const c = String(f.cargo).trim();
        if (![...cargoSel.options].some((o) => o.value === c)) {
          const o = document.createElement("option");
          o.value = c;
          o.textContent = c;
          cargoSel.appendChild(o);
        }
        cargoSel.value = c;
      }
      fillFuncionarioFormacaoFields(dom.funcionarioForm, f.escolaridade, f.formacao_json);
      if (f.possui_acesso) {
        dom.funcionarioPossuiAcesso.checked = true;
        if (dom.funcionarioAcessoArea) dom.funcionarioAcessoArea.classList.remove("hidden");
        if (loginEl) loginEl.value = f.usuario_email || "";
        if (perfilEl) perfilEl.value = f.perfil_usuario || "FUNCIONARIO";
        if (uidEl) uidEl.value = f.usuario_id != null && String(f.usuario_id).trim() !== "" ? String(f.usuario_id) : "";
        atualizarFuncionarioUsuarioResumo();
      } else {
        // Se não possui acesso, deixa os campos de usuário zerados
        if (dom.funcionarioAcessoArea) dom.funcionarioAcessoArea.classList.add("hidden");
        if (dom.funcionarioUsuarioResumo) { dom.funcionarioUsuarioResumo.textContent = ""; dom.funcionarioUsuarioResumo.style.display = "none"; }
      }
      if (dom.funcionarioForm?.elements.cpf) dom.funcionarioForm.elements.cpf.readOnly = true;
      if (dom.funcionarioAvatarPreview) {
        const url = f.foto ? getUsuarioFotoUrl(f.foto) : null;
        dom.funcionarioAvatarPreview.innerHTML = url
          ? `<img src="${url}" alt="Foto" class="usuarios-foto" style="max-width:96px;border-radius:8px;" />`
          : '<span class="avatar-placeholder">?</span>';
      }
    } else {
      if (dom.funcionarioForm?.elements.cpf) dom.funcionarioForm.elements.cpf.readOnly = false;
      if (dom.funcionarioAvatarPreview) dom.funcionarioAvatarPreview.innerHTML = '<span class="avatar-placeholder">?</span>';
      fillFuncionarioFormacaoFields(dom.funcionarioForm, "", null);
    }
    toggleModal(dom.funcionarioModal, true);
    } catch (err) {
      const msg = String(err?.message || "Erro ao abrir formulário.");
      const safeMsg = msg.length > 200 || msg.trim().startsWith("<") ? "Erro no servidor. Verifique a conexão." : msg;
      showToast(safeMsg, "error");
    }
  }
  function viewFuncionario(id) {
    fetchJSON(`/funcionarios/${id}`).then(f => {
      const esc = s => (s == null || s === "" ? "-" : String(s).replace(/</g, "&lt;"));
      const statusLabel = (f.status || "ativo") === "ativo" ? "Ativo" : "Inativo";
      const acessoLabel = f.possui_acesso ? "Sim" : "Não";
      const viewFotoUrl = f.foto ? getUsuarioFotoUrl(f.foto) : null;
      const viewFotoHtml = viewFotoUrl
        ? `<img src="${viewFotoUrl}" alt="${esc(f.nome_completo)}" class="usuarios-foto view-foto" />`
        : '<div class="usuarios-foto usuarios-foto--placeholder view-foto-placeholder"></div>';
      const field = (label, val) => `<div class="view-field"><div class="view-field-label">${label}</div><div class="view-field-value">${esc(val)}</div></div>`;
      dom.funcionarioViewContent.innerHTML = `
        <div class="view-funcionario-header">
          <div class="view-foto-wrap">${viewFotoHtml}</div>
          <div class="view-fields-grid">
            ${field("Nome completo", f.nome_completo)}
            ${field("CPF", f.cpf)}
            ${field("Data de nascimento", f.data_nascimento)}
            ${field("Sexo", f.sexo)}
            ${field("Estado civil", f.estado_civil)}
          </div>
        </div>
        <div class="form-section">
          <h3>Dados profissionais</h3>
          <div class="view-fields-grid">
            ${field("Cargo", f.cargo)}
            ${field("Unidade", f.unidade_nome)}
            ${field("Data de admissão", f.data_admissao)}
            ${field("Status", statusLabel)}
          </div>
        </div>
        ${renderFuncionarioFormacaoViewHtml(f, esc, field)}
        <div class="form-section">
          <h3>Contato</h3>
          <div class="view-fields-grid">
            ${field("WhatsApp", f.whatsapp)}
            ${field("E-mail", f.email)}
          </div>
        </div>
        <div class="form-section">
          <h3>Dados bancários</h3>
          <div class="view-fields-grid">
            ${field("Banco", f.banco)}
            ${field("Agência", f.agencia)}
            ${field("Conta", f.conta)}
            ${field("Dígito", f.conta_digito)}
            ${field("PIX", f.pix)}
          </div>
        </div>
        <div class="form-section">
          <div class="view-fields-grid">
            ${field("Possui acesso ao sistema", acessoLabel)}
            ${field("Login (e-mail)", f.usuario_email || "-")}
            ${f.perfil_usuario ? field("Perfil no sistema", PERFIL_LABELS[f.perfil_usuario] || f.perfil_usuario) : ""}
            ${field("Cadastrado em", f.created_at)}
            ${field("Observações", f.observacoes)}
          </div>
        </div>
      `;
      dom.funcionarioViewEditar.dataset.id = id;
      dom.funcionarioViewInativar.dataset.id = id;
      dom.funcionarioViewInativar.style.display = (f.status || "ativo") === "ativo" ? "" : "none";
      toggleModal(dom.funcionarioViewModal, true);
    }).catch(() => showToast("Erro ao carregar funcionário.", "error"));
  }
  async function editFuncionario(id) {
    toggleModal(dom.funcionarioViewModal, false);
    await openFuncionarioModal(id);
  }
  async function inativarFuncionario(id) {
    if (!confirm("Deseja inativar este funcionário?")) return;
    try {
      await fetchJSON(`/funcionarios/${id}/inativar`, { method: "PUT" });
      showToast("Funcionário inativado.", "success");
      await loadFuncionarios(getFuncionariosFiltros());
    } catch (e) {
      showToast(e?.message || "Erro ao inativar.", "error");
    }
  }
  function getFuncionariosFiltros() {
    const nome = document.getElementById("funcionariosFiltroNome")?.value;
    const cpf = document.getElementById("funcionariosFiltroCpf")?.value?.trim();
    const cargo = document.getElementById("funcionariosFiltroCargo")?.value;
    const unidadeId = document.getElementById("funcionariosFiltroUnidade")?.value;
    const status = document.getElementById("funcionariosFiltroStatus")?.value;
    return { nome: nome || undefined, cpf: cpf || undefined, cargo: cargo || undefined, unidade_id: unidadeId || undefined, status: status || undefined };
  }
  dom.openFuncionarioBtn?.addEventListener("click", () => openFuncionarioModal());
  dom.closeFuncionarioBtn?.addEventListener("click", () => toggleModal(dom.funcionarioModal, false));
  dom.cancelFuncionarioBtn?.addEventListener("click", () => {
    dom.funcionarioForm?.reset();
    funcionarioFotoFile = null;
    funcionarioFotoRemovida = false;
    setFuncionarioFormRecordId(dom.funcionarioForm, "");
    if (dom.funcionarioFormFeedback) { dom.funcionarioFormFeedback.classList.add("hidden"); dom.funcionarioFormFeedback.textContent = ""; }
    if (dom.funcionarioPossuiAcesso) dom.funcionarioPossuiAcesso.checked = false;
    if (dom.funcionarioAcessoArea) dom.funcionarioAcessoArea.classList.add("hidden");
    ["funcionarioLoginUsuario","funcionarioSenhaUsuario","funcionarioPerfilUsuario","funcionarioUsuarioId"].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = id === "funcionarioPerfilUsuario" ? "FUNCIONARIO" : "";
    });
    if (dom.funcionarioUsuarioResumo) { dom.funcionarioUsuarioResumo.textContent = ""; dom.funcionarioUsuarioResumo.style.display = "none"; }
    if (dom.funcionarioAvatarPreview) dom.funcionarioAvatarPreview.innerHTML = '<span class="avatar-placeholder">?</span>';
    dom.funcionarioModalTitle.textContent = "Novo Funcionário";
    const submitBtn = document.getElementById("funcionarioFormSubmit");
    if (submitBtn) submitBtn.textContent = "Salvar";
    fillFuncionarioFormacaoFields(dom.funcionarioForm, "", null);
  });
  dom.funcionarioForm?.addEventListener("click", (e) => {
    const addBtn = e.target.closest(".btn-formacao-add");
    if (addBtn && dom.funcionarioForm?.contains(addBtn)) {
      e.preventDefault();
      e.stopPropagation();
      const key = addBtn.getAttribute("data-formacao-key");
      if (key) addFuncionarioFormacaoLinha(dom.funcionarioForm, key);
      return;
    }
    const salvarLinhaBtn = e.target.closest(".btn-formacao-salvar-linha");
    if (salvarLinhaBtn && dom.funcionarioForm?.contains(salvarLinhaBtn)) {
      e.preventDefault();
      e.stopPropagation();
      const row = salvarLinhaBtn.closest(".formacao-linha");
      if (!row) return;
      const err = validarFormacaoLinhaParaSalvar(row);
      if (err) {
        showToast(err, "warning");
        return;
      }
      setFormacaoLinhaConfirmada(row, true);
      showToast("Formação incluída neste cadastro. Use Salvar funcionário abaixo para gravar no servidor.", "success");
      return;
    }
    const editarLinhaBtn = e.target.closest(".btn-formacao-editar-linha");
    if (editarLinhaBtn && dom.funcionarioForm?.contains(editarLinhaBtn)) {
      e.preventDefault();
      e.stopPropagation();
      const row = editarLinhaBtn.closest(".formacao-linha");
      if (row) setFormacaoLinhaConfirmada(row, false);
      return;
    }
    const remBtn = e.target.closest(".btn-formacao-remover");
    if (remBtn && dom.funcionarioForm?.contains(remBtn)) {
      e.preventDefault();
      e.stopPropagation();
      const row = remBtn.closest(".formacao-linha");
      const wrap = row?.parentElement;
      if (!row || !wrap || !wrap.classList.contains("formacao-linhas")) return;
      const rows = wrap.querySelectorAll(":scope > .formacao-linha");
      if (rows.length <= 1) {
        row.querySelectorAll("[data-f]").forEach((el) => {
          if (el.type === "checkbox") el.checked = false;
          else el.value = "";
        });
        setFormacaoLinhaConfirmada(row, false);
      } else {
        row.remove();
      }
    }
  });
  document.getElementById("funcionariosSection")?.addEventListener("click", (e) => {
    const btn = e.target.closest(".btn-view-funcionario, .btn-edit-funcionario, .btn-inativar-funcionario");
    if (!btn) return;
    const id = btn.dataset.id;
    if (!id) return;
    e.preventDefault();
    if (btn.classList.contains("btn-view-funcionario")) viewFuncionario(id);
    else if (btn.classList.contains("btn-edit-funcionario")) editFuncionario(id);
    else if (btn.classList.contains("btn-inativar-funcionario")) inativarFuncionario(id);
  });
  dom.funcionarioFotoInput?.addEventListener("change", (ev) => {
    const file = ev.target.files?.[0];
    if (file && (file.type === "image/jpeg" || file.type === "image/png") && file.size <= 2 * 1024 * 1024) {
      funcionarioFotoFile = file;
      funcionarioFotoRemovida = false;
      const url = URL.createObjectURL(file);
      if (dom.funcionarioAvatarPreview) {
        dom.funcionarioAvatarPreview.innerHTML = `<img src="${url}" alt="Foto" class="usuarios-foto" style="max-width:96px;border-radius:8px;" />`;
      }
    } else if (file) {
      showToast("Use JPG ou PNG até 2 MB.", "warning");
    }
  });
  dom.funcionarioFotoTrocar?.addEventListener("click", () => dom.funcionarioFotoInput?.click());
  dom.funcionarioFotoRemover?.addEventListener("click", () => {
    funcionarioFotoFile = null;
    funcionarioFotoRemovida = true;
    if (dom.funcionarioAvatarPreview) dom.funcionarioAvatarPreview.innerHTML = '<span class="avatar-placeholder">?</span>';
    if (dom.funcionarioFotoInput) dom.funcionarioFotoInput.value = "";
  });
  dom.funcionarioPossuiAcesso?.addEventListener("change", function() {
    if (dom.funcionarioAcessoArea) dom.funcionarioAcessoArea.classList.toggle("hidden", !this.checked);
    if (!this.checked) {
      const loginEl = document.getElementById("funcionarioLoginUsuario");
      const senhaEl = document.getElementById("funcionarioSenhaUsuario");
      const perfilEl = document.getElementById("funcionarioPerfilUsuario");
      const uidEl = document.getElementById("funcionarioUsuarioId");
      if (loginEl) loginEl.value = "";
      if (senhaEl) senhaEl.value = "";
      if (perfilEl) perfilEl.value = "FUNCIONARIO";
      if (uidEl) uidEl.value = "";
      if (dom.funcionarioUsuarioResumo) { dom.funcionarioUsuarioResumo.textContent = ""; dom.funcionarioUsuarioResumo.style.display = "none"; }
    }
  });

  function atualizarFuncionarioUsuarioResumo() {
    const login = document.getElementById("funcionarioLoginUsuario")?.value?.trim();
    const usuarioId = document.getElementById("funcionarioUsuarioId")?.value;
    const resumo = dom.funcionarioUsuarioResumo;
    if (!resumo) return;
    if (usuarioId) {
      const sel = document.getElementById("funcUsuarioExistente");
      const opt = sel?.options?.[sel?.selectedIndex];
      if (opt?.textContent) resumo.textContent = `Vinculado: ${opt.textContent}`;
      else if (login) resumo.textContent = `Vinculado: ${login}`;
      else resumo.textContent = "Usuário vinculado";
      resumo.style.display = "";
    } else if (login) {
      const perfil = document.getElementById("funcionarioPerfilUsuario")?.value || "FUNCIONARIO";
      resumo.textContent = `Usuário configurado: ${login} (${PERFIL_LABELS[perfil] || perfil})`;
      resumo.style.display = "";
    } else {
      resumo.textContent = "";
      resumo.style.display = "none";
    }
  }

  dom.funcionarioConfigurarUsuario?.addEventListener("click", async () => {
    const loginEl = document.getElementById("funcUsuarioEmail");
    const perfilEl = document.getElementById("funcUsuarioPerfil");
    const modoNovo = document.querySelector('input[name="funcionarioUsuarioModo"][value="novo"]');
    const modoExistente = document.querySelector('input[name="funcionarioUsuarioModo"][value="existente"]');
    const divNovo = document.getElementById("funcionarioUsuarioModoNovo");
    const divExistente = document.getElementById("funcionarioUsuarioModoExistente");
    if (loginEl) loginEl.value = document.getElementById("funcionarioLoginUsuario")?.value || "";
    if (perfilEl) perfilEl.value = document.getElementById("funcionarioPerfilUsuario")?.value || "FUNCIONARIO";
    const senhaEl = document.getElementById("funcUsuarioSenha");
    if (senhaEl) senhaEl.value = "";
    const uid = document.getElementById("funcionarioUsuarioId")?.value;
    if (uid) {
      if (modoExistente) modoExistente.checked = true;
      if (modoNovo) modoNovo.checked = false;
      if (divNovo) divNovo.classList.add("hidden");
      if (divExistente) divExistente.classList.remove("hidden");
      try {
        const usuarios = await fetchJSON("/usuarios");
        const sel = document.getElementById("funcUsuarioExistente");
        if (sel && Array.isArray(usuarios)) {
          sel.innerHTML = '<option value="">Selecione</option>' + usuarios.filter(u => u.ativo !== 0).map(u => `<option value="${u.id}" ${String(u.id) === String(uid) ? "selected" : ""}>${(u.nome || u.email || "").replace(/</g,"&lt;")} (${u.email || "-"})</option>`).join("");
        }
      } catch (e) { showToast("Erro ao carregar usuários.", "error"); }
    } else {
      if (modoNovo) modoNovo.checked = true;
      if (modoExistente) modoExistente.checked = false;
      if (divNovo) divNovo.classList.remove("hidden");
      if (divExistente) divExistente.classList.add("hidden");
    }
    toggleModal(dom.funcionarioUsuarioModal, true);
  });

  document.querySelectorAll('input[name="funcionarioUsuarioModo"]').forEach(r => {
    r.addEventListener("change", function() {
      const divNovo = document.getElementById("funcionarioUsuarioModoNovo");
      const divExistente = document.getElementById("funcionarioUsuarioModoExistente");
      if (this.value === "novo") {
        if (divNovo) divNovo.classList.remove("hidden");
        if (divExistente) divExistente.classList.add("hidden");
      } else {
        if (divNovo) divNovo.classList.add("hidden");
        if (divExistente) divExistente.classList.remove("hidden");
        (async () => {
          try {
            const usuarios = await fetchJSON("/usuarios");
            const sel = document.getElementById("funcUsuarioExistente");
            if (sel && Array.isArray(usuarios)) {
              const jaVinculados = new Set((state.funcionarios || []).filter(f => f.usuario_id).map(f => String(f.usuario_id)));
              sel.innerHTML = '<option value="">Selecione</option>' + usuarios.filter(u => u.ativo !== 0 && !jaVinculados.has(String(u.id))).map(u => `<option value="${u.id}">${(u.nome || u.email || "").replace(/</g,"&lt;")} (${u.email || "-"})</option>`).join("");
            }
          } catch (e) { showToast("Erro ao carregar usuários.", "error"); }
        })();
      }
    });
  });

  document.getElementById("closeFuncionarioUsuarioModal")?.addEventListener("click", () => toggleModal(dom.funcionarioUsuarioModal, false));
  document.getElementById("funcionarioUsuarioModalCancelar")?.addEventListener("click", () => toggleModal(dom.funcionarioUsuarioModal, false));
  document.getElementById("funcionarioUsuarioModalAplicar")?.addEventListener("click", () => {
    const modo = document.querySelector('input[name="funcionarioUsuarioModo"]:checked')?.value;
    const loginEl = document.getElementById("funcUsuarioEmail");
    const senhaEl = document.getElementById("funcUsuarioSenha");
    const perfilEl = document.getElementById("funcUsuarioPerfil");
    const selExistente = document.getElementById("funcUsuarioExistente");
    if (modo === "existente") {
      const uid = selExistente?.value;
      if (!uid) { showToast("Selecione um usuário.", "warning"); return; }
      document.getElementById("funcionarioUsuarioId").value = uid;
      document.getElementById("funcionarioLoginUsuario").value = "";
      document.getElementById("funcionarioSenhaUsuario").value = "";
      document.getElementById("funcionarioPerfilUsuario").value = "";
      atualizarFuncionarioUsuarioResumo();
      toggleModal(dom.funcionarioUsuarioModal, false);
      return;
    }
    const login = (loginEl?.value || "").trim();
    const senha = senhaEl?.value || "";
    const perfil = perfilEl?.value || "FUNCIONARIO";
    if (!login) { showToast("Informe o e-mail ou login do usuário.", "warning"); return; }
    if (senha.length < 6) { showToast("A senha deve ter no mínimo 6 caracteres.", "warning"); return; }
    document.getElementById("funcionarioUsuarioId").value = "";
    document.getElementById("funcionarioLoginUsuario").value = login;
    document.getElementById("funcionarioSenhaUsuario").value = senha;
    document.getElementById("funcionarioPerfilUsuario").value = perfil;
    atualizarFuncionarioUsuarioResumo();
    toggleModal(dom.funcionarioUsuarioModal, false);
  });

  dom.funcionarioForm?.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (!currentUser?.id) {
      showToast("Faça login novamente. Sessão expirada.", "error");
      return;
    }
    const form = dom.funcionarioForm;
    const id = getFuncionarioFormRecordId(form);
    const nome = (form.elements.nome_completo?.value || "").trim();
    const cpf = (form.elements.cpf?.value || "").replace(/\D/g, "");
    const cargo = (form.elements.cargo?.value || "").trim();
    const feedback = dom.funcionarioFormFeedback;
    if (!nome) { if (feedback) { feedback.textContent = "Nome completo é obrigatório."; feedback.className = "form-feedback error"; feedback.classList.remove("hidden"); } else showToast("Nome completo é obrigatório.", "error"); return; }
    if (cpf.length !== 11) { if (feedback) { feedback.textContent = "CPF inválido. Informe 11 dígitos."; feedback.className = "form-feedback error"; feedback.classList.remove("hidden"); } else showToast("CPF inválido.", "error"); return; }
    if (!cargo) { if (feedback) { feedback.textContent = "Cargo é obrigatório."; feedback.className = "form-feedback error"; feedback.classList.remove("hidden"); } else showToast("Cargo é obrigatório.", "error"); return; }
    const possuiAcesso = dom.funcionarioPossuiAcesso?.checked || false;
    if (possuiAcesso) {
      const usuarioId = (document.getElementById("funcionarioUsuarioId")?.value || "").trim();
      const login = (form.elements.login_usuario?.value || "").trim();
      const senha = form.elements.senha_usuario?.value || "";
      const isCadastroNovo = !id;
      // Novo funcionário: sem usuário vinculado, é obrigatório configurar e-mail + senha (ou usuário existente).
      // Edição: quem já tem acesso no cadastro pode salvar sem redigitar senha (o servidor mantém o vínculo).
      if (isCadastroNovo && !usuarioId) {
        if (!login || senha.length < 6) {
          if (feedback) {
            feedback.textContent = "Clique em 'Configurar usuário' e preencha e-mail e senha (mín. 6 caracteres), ou vincule um usuário existente.";
            feedback.className = "form-feedback error";
            feedback.classList.remove("hidden");
          } else {
            showToast("Configure o usuário antes de salvar.", "error");
          }
          return;
        }
      }
    }
    const linhasFormacao = form.querySelectorAll("#funcionarioFormacaoBlocos .formacao-linha");
    for (const fRow of linhasFormacao) {
      if (rowFormacaoTemAlgumCampo(fRow) && fRow.dataset.formacaoConfirmada !== "1") {
        const msg = "Há formação com dados preenchidos sem confirmar. Clique em \"Salvar esta formação\" em cada linha ou limpe os campos.";
        if (feedback) {
          feedback.textContent = msg;
          feedback.className = "form-feedback error";
          feedback.classList.remove("hidden");
        } else {
          showToast(msg, "warning");
        }
        return;
      }
    }
    if (feedback) { feedback.classList.add("hidden"); feedback.textContent = ""; feedback.className = "form-feedback hidden"; }
    const payload = {
      nome_completo: form.elements.nome_completo?.value,
      cpf: (form.elements.cpf?.value || "").replace(/\D/g, ""),
      data_nascimento: form.elements.data_nascimento?.value || null,
      sexo: form.elements.sexo?.value || null,
      estado_civil: form.elements.estado_civil?.value || null,
      cargo: form.elements.cargo?.value,
      unidade_id: form.elements.unidade_id?.value || null,
      whatsapp: form.elements.whatsapp?.value || null,
      email: form.elements.email?.value || null,
      data_admissao: form.elements.data_admissao?.value || null,
      status: form.elements.status?.value || "ativo",
      observacoes: form.elements.observacoes?.value || null,
      banco: form.elements.banco?.value || null,
      agencia: form.elements.agencia?.value || null,
      conta: form.elements.conta?.value || null,
      conta_digito: form.elements.conta_digito?.value || null,
      pix: form.elements.pix?.value || null,
      escolaridade: form.elements.escolaridade?.value || null,
      possui_acesso: dom.funcionarioPossuiAcesso?.checked || false,
    };
    const formacaoJsonObj = collectFuncionarioFormacaoJson(form);
    const formacaoJsonStr = formacaoJsonObj ? JSON.stringify(formacaoJsonObj) : "";
    if (payload.possui_acesso) {
      payload.usuario_id = document.getElementById("funcionarioUsuarioId")?.value || null;
      payload.login_usuario = form.elements.login_usuario?.value;
      payload.senha_usuario = form.elements.senha_usuario?.value;
      payload.perfil_usuario = form.elements.perfil_usuario?.value || "FUNCIONARIO";
    }
    const temFoto = !!funcionarioFotoFile;
    const temRemoverFoto = !!funcionarioFotoRemovida;
    try {
      const fd = new FormData();
      if (id) {
        const putPayload = {
          nome_completo: payload.nome_completo,
          data_nascimento: payload.data_nascimento,
          sexo: payload.sexo,
          estado_civil: payload.estado_civil,
          cargo: payload.cargo,
          unidade_id: payload.unidade_id ?? "",
          whatsapp: payload.whatsapp,
          email: payload.email,
          data_admissao: payload.data_admissao,
          status: payload.status,
          observacoes: payload.observacoes,
          banco: payload.banco,
          agencia: payload.agencia,
          conta: payload.conta,
          conta_digito: payload.conta_digito,
          pix: payload.pix,
          escolaridade: payload.escolaridade ?? "",
          formacao_json: formacaoJsonStr,
          // RH (Acesso ao sistema)
          possui_acesso: payload.possui_acesso ? "1" : "0",
          usuario_id: payload.usuario_id ?? null,
          login_usuario: payload.login_usuario ?? null,
          senha_usuario: payload.senha_usuario ?? null,
          perfil_usuario: payload.perfil_usuario ?? null,
        };
        Object.entries(putPayload).forEach(([k, v]) => { fd.append(k, v != null ? String(v) : ""); });
        if (temRemoverFoto) fd.append("remove_foto", "1");
        if (temFoto) fd.append("foto", funcionarioFotoFile);
        await fetchForm(`/funcionarios/${id}/atualizar`, "POST", fd);
      } else {
        fd.append("nome_completo", payload.nome_completo);
        fd.append("cpf", (payload.cpf || "").replace(/\D/g, ""));
        fd.append("cargo", payload.cargo);
        fd.append("status", payload.status || "ativo");
        fd.append("possui_acesso", payload.possui_acesso ? "1" : "0");
        if (payload.data_nascimento) fd.append("data_nascimento", payload.data_nascimento);
        if (payload.sexo) fd.append("sexo", payload.sexo);
        if (payload.estado_civil) fd.append("estado_civil", payload.estado_civil);
        if (payload.unidade_id) fd.append("unidade_id", payload.unidade_id);
        if (payload.whatsapp) fd.append("whatsapp", payload.whatsapp);
        if (payload.email) fd.append("email", payload.email);
        if (payload.data_admissao) fd.append("data_admissao", payload.data_admissao);
        if (payload.observacoes) fd.append("observacoes", payload.observacoes);
        if (payload.banco) fd.append("banco", payload.banco);
        if (payload.agencia) fd.append("agencia", payload.agencia);
        if (payload.conta) fd.append("conta", payload.conta);
        if (payload.conta_digito) fd.append("conta_digito", payload.conta_digito);
        if (payload.pix) fd.append("pix", payload.pix);
        if (payload.escolaridade) fd.append("escolaridade", payload.escolaridade);
        fd.append("formacao_json", formacaoJsonStr);
        if (payload.possui_acesso) {
          if (payload.usuario_id) fd.append("usuario_id", payload.usuario_id);
          else {
            fd.append("login_usuario", payload.login_usuario || "");
            fd.append("senha_usuario", payload.senha_usuario || "");
            fd.append("perfil_usuario", payload.perfil_usuario || "FUNCIONARIO");
          }
        }
        if (temRemoverFoto) fd.append("remove_foto", "1");
        if (temFoto) fd.append("foto", funcionarioFotoFile);
        await fetchForm("/funcionarios", "POST", fd);
      }
      showToast(id ? "Funcionário atualizado." : "Funcionário cadastrado.", "success");
      toggleModal(dom.funcionarioModal, false);
      funcionarioFotoFile = null;
      funcionarioFotoRemovida = false;
      await loadFuncionarios(getFuncionariosFiltros());
    } catch (err) {
      let msg = String(err?.message || err?.error || "Erro ao salvar.");
      const details = err?.responseData?.details;
      if (details && typeof details === 'object') {
        const parts = Object.entries(details).map(([k, v]) => Array.isArray(v) ? v.join(', ') : v).filter(Boolean);
        if (parts.length) msg = parts.join(' ');
      }
      const safeMsg = msg.length > 500 || msg.trim().startsWith("<") ? "Erro no servidor. Tente novamente ou contate o suporte." : msg;
      if (feedback) { feedback.textContent = safeMsg; feedback.className = "form-feedback error"; feedback.classList.remove("hidden"); }
      else showToast(safeMsg, "error");
    }
  });
  dom.funcionariosFilterForm?.addEventListener("submit", async (e) => { e.preventDefault(); await loadFuncionarios(getFuncionariosFiltros()); });
  dom.funcionariosLimparFiltros?.addEventListener("click", () => {
    document.getElementById("funcionariosFiltroNome").value = "";
    document.getElementById("funcionariosFiltroNomeBusca").value = "";
    document.getElementById("funcionariosFiltroCpf").value = "";
    document.getElementById("funcionariosFiltroCargo").value = "";
    if (dom.funcionariosFiltroUnidade) dom.funcionariosFiltroUnidade.value = "";
    document.getElementById("funcionariosFiltroStatus").value = "";
    loadFuncionarios();
  });
  dom.closeFuncionarioView?.addEventListener("click", () => toggleModal(dom.funcionarioViewModal, false));
  dom.closeFuncionarioViewBtn?.addEventListener("click", () => toggleModal(dom.funcionarioViewModal, false));
  dom.funcionarioViewEditar?.addEventListener("click", () => { const id = dom.funcionarioViewEditar.dataset.id; if (id) editFuncionario(id); });
  dom.funcionarioViewInativar?.addEventListener("click", () => { const id = dom.funcionarioViewInativar.dataset.id; if (id) { toggleModal(dom.funcionarioViewModal, false); inativarFuncionario(id); } });

  function getProventosFiltros() {
    return {
      funcionario_id: document.getElementById("proventosFiltroFuncionario")?.value,
      tipo: document.getElementById("proventosFiltroTipo")?.value,
      unidade_id: document.getElementById("proventosFiltroUnidade")?.value,
      status: document.getElementById("proventosFiltroStatus")?.value,
      data_inicio: document.getElementById("proventosFiltroDataInicio")?.value,
      data_fim: document.getElementById("proventosFiltroDataFim")?.value
    };
  }
  function syncProventoUnidadeCnpj(form) {
    if (!form) return;
    const el = document.getElementById("proventoUnidadeCnpj");
    if (!el) return;
    const uid = form.elements.unidade_id?.value;
    const u = (state.unidades || []).find((x) => String(x.id) === String(uid));
    const raw = u?.cnpj;
    el.value = raw != null && String(raw).trim() !== "" ? formatCnpjCpfDisplay(String(raw)) : "";
  }
  async function openProventoModal(editId = null) {
    const perfil = (currentUser?.perfil || "").toString().trim().toUpperCase();
    if (!["ADMIN","GERENTE","FINANCEIRO","ASSISTENTE_ADMINISTRATIVO"].includes(perfil)) return showToast("Sem permissão.", "warning");
    try {
      await loadUnidades(false).catch(() => {});
      await loadFuncionarios().catch(() => {});
      const form = document.getElementById("proventoForm");
      if (!form) return;
      form.reset();
      form.elements.id.value = editId || "";
      document.getElementById("proventoModalTitle").textContent = editId ? "Editar Provento" : "Novo Provento";
      const selFunc = form.elements.funcionario_id;
      selFunc.innerHTML = '<option value="">Selecione</option>' + (state.funcionarios || []).filter(f => (f.status||"ativo")==="ativo").map(f => `<option value="${f.id}">${(f.nome_completo||"").replace(/</g,"&lt;")}</option>`).join("");
      const selUn = form.elements.unidade_id;
      selUn.innerHTML = '<option value="">Selecione</option>' + (state.unidades || []).map(u => `<option value="${u.id}">${(u.nome||"").replace(/</g,"&lt;")}</option>`).join("");
      let proventoCarregado = null;
      if (editId) {
        const p = await fetchJSON(`/proventos/${editId}`);
        proventoCarregado = p;
        form.elements.funcionario_id.value = p.funcionario_id;
        form.elements.cpf.value = p.funcionario_cpf || "";
        form.elements.unidade_id.value = p.unidade_id || "";
        form.elements.tipo.value = p.tipo || "";
        form.elements.valor.value = p.valor || "";
        form.elements.data_provento.value = (p.data_provento || "").slice(0,10);
        form.elements.competencia.value = p.competencia || "";
        form.elements.observacao_interna.value = p.observacao_interna || "";
      }
      syncProventoUnidadeCnpj(form);
      const cnpjEl = document.getElementById("proventoUnidadeCnpj");
      if (cnpjEl && !cnpjEl.value && proventoCarregado?.unidade_cnpj) {
        cnpjEl.value = formatCnpjCpfDisplay(String(proventoCarregado.unidade_cnpj));
      }
      toggleModal(document.getElementById("proventoModal"), true);
    } catch (e) {
      showToast(e?.message || "Erro ao abrir formulário", "error");
    }
  }
  const escProventoView = (s) => (s == null ? "-" : String(s).replace(/</g, "&lt;"));
  function formatProventoDataHora(v) {
    if (v == null || v === "") return "-";
    const d = new Date(String(v).includes("T") ? v : String(v).replace(" ", "T"));
    if (Number.isNaN(d.getTime())) return escProventoView(String(v));
    return d.toLocaleString("pt-BR", { dateStyle: "short", timeStyle: "short" });
  }
  function viewProvento(id) {
    fetchJSON(`/proventos/${id}`).then(p => {
      const esc = escProventoView;
      const st = PROVENTO_STATUS_LABELS[p.status] || p.status;
      const tipoL = PROVENTO_TIPO_LABELS[p.tipo] || p.tipo;
      const valorF = "R$ " + (Number(p.valor)||0).toLocaleString("pt-BR",{minimumFractionDigits:2});
      const dataProv = p.data_provento ? new Date(p.data_provento + "T12:00:00").toLocaleDateString("pt-BR") : "-";
      const perfilV = (currentUser?.perfil || "").toString().trim().toUpperCase();
      const podeVerObsInterna = ["ADMIN", "GERENTE", "FINANCEIRO", "ASSISTENTE_ADMINISTRATIVO"].includes(perfilV);
      document.getElementById("proventoViewContent").innerHTML = `
        <div class="view-fields-grid" style="display:grid;gap:0.5rem;margin-bottom:1rem;">
          <div class="view-field"><div class="view-field-label">Funcionário</div><div class="view-field-value">${esc(p.funcionario_nome)}</div></div>
          <div class="view-field"><div class="view-field-label">CPF</div><div class="view-field-value">${esc(p.funcionario_cpf)}</div></div>
          ${p.funcionario_pix ? `<div class="view-field"><div class="view-field-label">PIX</div><div class="view-field-value">${esc(p.funcionario_pix)}</div></div>` : ""}
          <div class="view-field"><div class="view-field-label">Tipo</div><div class="view-field-value">${esc(tipoL)}</div></div>
          <div class="view-field"><div class="view-field-label">Valor</div><div class="view-field-value">${valorF}</div></div>
          <div class="view-field"><div class="view-field-label">Unidade</div><div class="view-field-value">${esc(p.unidade_nome)}</div></div>
          <div class="view-field"><div class="view-field-label">CNPJ (unidade)</div><div class="view-field-value">${esc(formatCnpjCpfDisplay(p.unidade_cnpj || ""))}</div></div>
          <div class="view-field"><div class="view-field-label">Data do provento</div><div class="view-field-value">${esc(dataProv)}</div></div>
          <div class="view-field"><div class="view-field-label">Competência</div><div class="view-field-value">${esc(p.competencia)}</div></div>
          ${podeVerObsInterna ? `<div class="view-field"><div class="view-field-label">Obs. interna</div><div class="view-field-value">${esc(p.observacao_interna)}</div></div>` : ""}
          <div class="view-field"><div class="view-field-label">Criado por</div><div class="view-field-value">${esc(p.criado_por_nome)}</div></div>
          <div class="view-field"><div class="view-field-label">Autorizado por</div><div class="view-field-value">${esc(p.autorizado_por_nome)}</div></div>
          <div class="view-field"><div class="view-field-label">Finalizado por</div><div class="view-field-value">${esc(p.finalizado_por_nome)}</div></div>
          <div class="view-field"><div class="view-field-label">Data aceite</div><div class="view-field-value">${formatProventoDataHora(p.data_assinatura)}</div></div>
          <div class="view-field"><div class="view-field-label">Data autorização</div><div class="view-field-value">${formatProventoDataHora(p.data_autorizacao)}</div></div>
          <div class="view-field"><div class="view-field-label">Data finalização</div><div class="view-field-value">${formatProventoDataHora(p.data_finalizacao)}</div></div>
          <div class="view-field"><div class="view-field-label">Status</div><div class="view-field-value">${esc(st)}</div></div>
          ${p.justificativa_cancelamento ? `<div class="view-field" style="grid-column:1/-1;"><div class="view-field-label">Justificativa cancelamento</div><div class="view-field-value">${esc(p.justificativa_cancelamento)}</div></div>` : ""}
        </div>
      `;
      document.getElementById("proventoViewEditar").dataset.id = id;
      const podeCriar = ["ADMIN","GERENTE","FINANCEIRO","ASSISTENTE_ADMINISTRATIVO"].includes(perfilV);
      const podeEditar = podeCriar && ["rascunho","aguardando_autorizacao"].includes(p.status);
      const podeAssinar = !podeCriar && p.status === "aguardando_autorizacao"; // Quem vê só os próprios (Ing, Kel) pode assinar
      document.getElementById("proventoViewEditar").style.display = podeEditar ? "" : "none";
      const btnAssinar = document.getElementById("proventoViewAssinar");
      if (btnAssinar) {
        btnAssinar.dataset.id = id;
        btnAssinar.style.display = podeAssinar ? "" : "none";
      }
      const btnRecibo = document.getElementById("proventoViewRecibo");
      if (btnRecibo) {
        btnRecibo.dataset.id = id;
        btnRecibo.style.display = p.status === "finalizado" ? "" : "none";
      }
      toggleModal(document.getElementById("proventoViewModal"), true);
    }).catch(() => showToast("Erro ao carregar provento.", "error"));
  }
  async function editProvento(id) {
    toggleModal(document.getElementById("proventoViewModal"), false);
    await openProventoModal(id);
  }
  async function autorizarProvento(id) {
    try {
      await fetchJSON(`/proventos/${id}/autorizar`, { method: "POST" });
      showToast("Provento autorizado.", "success");
      await loadProventos(getProventosFiltros());
    } catch (e) { showToast(e?.message || "Erro ao autorizar.", "error"); }
  }
  async function finalizarProvento(id) {
    try {
      await fetchJSON(`/proventos/${id}/finalizar`, { method: "POST" });
      showToast("Provento finalizado.", "success");
      await loadProventos(getProventosFiltros());
    } catch (e) { showToast(e?.message || "Erro ao finalizar.", "error"); }
  }
  async function cancelarProvento(id) {
    const just = prompt("Justificativa do cancelamento (obrigatório):");
    if (!just || !just.trim()) return;
    try {
      await fetchJSON(`/proventos/${id}/cancelar`, { method: "POST", body: JSON.stringify({ justificativa: just.trim() }) });
      showToast("Provento cancelado.", "success");
      await loadProventos(getProventosFiltros());
    } catch (e) { showToast(e?.message || "Erro ao cancelar.", "error"); }
  }
  function assinarProvento(id) {
    const modal = document.getElementById("proventoAssinaturaModal");
    const etapa1 = document.getElementById("proventoAssinaturaEtapa1");
    const etapa2 = document.getElementById("proventoAssinaturaEtapa2");
    const codigoInput = document.getElementById("proventoAssinaturaCodigo");
    const feedback = document.getElementById("proventoAssinaturaFeedback");
    const whatsappWrap = document.getElementById("proventoAssinaturaWhatsappBtnWrap");
    modal.dataset.proventoId = id;
    if (etapa1) etapa1.classList.remove("hidden");
    if (etapa2) etapa2.classList.add("hidden");
    if (codigoInput) codigoInput.value = "";
    if (feedback) { feedback.classList.add("hidden"); feedback.textContent = ""; }
    if (whatsappWrap) whatsappWrap.classList.add("hidden");
    toggleModal(modal, true);
  }
  document.getElementById("closeProventoAssinaturaModal")?.addEventListener("click", () => toggleModal(document.getElementById("proventoAssinaturaModal"), false));
  document.getElementById("proventoAssinaturaSolicitar")?.addEventListener("click", async () => {
    const id = document.getElementById("proventoAssinaturaModal")?.dataset?.proventoId;
    const canal = document.querySelector('input[name="assinaturaCanal"]:checked')?.value || "email";
    if (!id) return;
    try {
      const resp = await fetchJSON(`/proventos/${id}/enviar-codigo`, { method: "POST", body: JSON.stringify({ canal }) });
      document.getElementById("proventoAssinaturaEtapa1").classList.add("hidden");
      document.getElementById("proventoAssinaturaEtapa2").classList.remove("hidden");
      const codigoInput = document.getElementById("proventoAssinaturaCodigo");
      const feedback = document.getElementById("proventoAssinaturaFeedback");
      const whatsappWrap = document.getElementById("proventoAssinaturaWhatsappBtnWrap");
      const whatsappLink = document.getElementById("proventoAssinaturaWhatsappLink");
      if (whatsappWrap) whatsappWrap.classList.add("hidden");
      if (resp && resp.codigo) {
        codigoInput.value = resp.codigo;
        if (resp.whatsapp_link && whatsappLink) {
          whatsappLink.href = resp.whatsapp_link;
          if (whatsappWrap) whatsappWrap.classList.remove("hidden");
          // Mesma lógica da Reserva de Mesa: abrir link programaticamente para garantir envio
          var a = document.createElement('a');
          a.href = resp.whatsapp_link;
          a.target = '_blank';
          a.rel = 'noopener noreferrer';
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
        }
        if (feedback) {
          feedback.textContent = resp._aviso || "Código preenchido. Clique em 'Confirmar aceite' para concluir.";
          feedback.className = "form-feedback";
          feedback.classList.remove("hidden");
        }
        showToast("Código gerado! Clique em 'Confirmar aceite'.", "success");
      } else {
        codigoInput.value = "";
        if (feedback) { feedback.classList.add("hidden"); feedback.textContent = ""; }
        showToast("Código enviado! Verifique seu " + (canal === "email" ? "e-mail" : "WhatsApp") + ".", "success");
      }
      codigoInput.focus();
    } catch (e) {
        const msg = (e?.responseData?.error || e?.message || "Erro ao enviar código.").toString();
        showToast(msg.length > 80 ? msg.substring(0, 77) + "..." : msg, "error");
      }
  });
  document.getElementById("proventoAssinaturaNovoCodigo")?.addEventListener("click", () => {
    document.getElementById("proventoAssinaturaEtapa2").classList.add("hidden");
    document.getElementById("proventoAssinaturaEtapa1").classList.remove("hidden");
    document.getElementById("proventoAssinaturaCodigo").value = "";
    const w = document.getElementById("proventoAssinaturaWhatsappBtnWrap");
    if (w) w.classList.add("hidden");
    const f = document.getElementById("proventoAssinaturaFeedback");
    if (f) { f.classList.add("hidden"); f.textContent = ""; }
  });
  document.getElementById("proventoAssinaturaConfirmar")?.addEventListener("click", async () => {
    const id = document.getElementById("proventoAssinaturaModal")?.dataset?.proventoId;
    const codigo = (document.getElementById("proventoAssinaturaCodigo")?.value || "").replace(/\D/g, "");
    const feedback = document.getElementById("proventoAssinaturaFeedback");
    if (!id) return;
    if (codigo.length !== 6) {
      if (feedback) { feedback.textContent = "Informe o código de 6 dígitos."; feedback.className = "form-feedback error"; feedback.classList.remove("hidden"); }
      return;
    }
    try {
      await fetchJSON(`/proventos/${id}/confirmar-assinatura`, { method: "POST", body: JSON.stringify({ codigo }) });
      showToast("Provento aceito com sucesso!", "success");
      toggleModal(document.getElementById("proventoAssinaturaModal"), false);
      await loadProventos(getProventosFiltros());
    } catch (e) {
      const msg = e?.message || "Erro ao confirmar.";
      if (feedback) { feedback.textContent = msg; feedback.className = "form-feedback error"; feedback.classList.remove("hidden"); }
      else showToast(msg, "error");
    }
  });
  document.getElementById("proventoForm")?.addEventListener("change", (e) => {
    const form = document.getElementById("proventoForm");
    if (!form) return;
    if (e.target?.name === "funcionario_id" && form.elements?.cpf) {
      const f = (state.funcionarios || []).find((x) => String(x.id) === String(e.target.value));
      form.elements.cpf.value = f?.cpf || "";
    }
    if (e.target?.name === "unidade_id") syncProventoUnidadeCnpj(form);
  });
  document.getElementById("openProvento")?.addEventListener("click", () => openProventoModal());
  document.getElementById("closeProvento")?.addEventListener("click", () => toggleModal(document.getElementById("proventoModal"), false));
  document.getElementById("cancelProvento")?.addEventListener("click", () => { document.getElementById("proventoForm")?.reset(); toggleModal(document.getElementById("proventoModal"), false); });
  document.getElementById("closeProventoView")?.addEventListener("click", () => toggleModal(document.getElementById("proventoViewModal"), false));
  document.getElementById("closeProventoViewBtn")?.addEventListener("click", () => toggleModal(document.getElementById("proventoViewModal"), false));
  document.getElementById("proventoViewEditar")?.addEventListener("click", () => { const id = document.getElementById("proventoViewEditar")?.dataset?.id; if (id) editProvento(id); });
  document.getElementById("proventoViewAssinar")?.addEventListener("click", () => { const id = document.getElementById("proventoViewAssinar")?.dataset?.id; if (id) { toggleModal(document.getElementById("proventoViewModal"), false); assinarProvento(id); } });
  document.getElementById("proventosSection")?.addEventListener("click", (e) => {
    const btn = e.target.closest(".btn-view-provento, .btn-edit-provento, .btn-autorizar-provento, .btn-finalizar-provento, .btn-cancelar-provento, .btn-assinar-provento, .btn-recibo-provento");
    if (!btn) return;
    const id = btn.dataset.id;
    if (!id) return;
    e.preventDefault();
    if (btn.classList.contains("btn-view-provento")) viewProvento(id);
    else if (btn.classList.contains("btn-edit-provento")) editProvento(id);
    else if (btn.classList.contains("btn-autorizar-provento")) autorizarProvento(id);
    else if (btn.classList.contains("btn-finalizar-provento")) finalizarProvento(id);
    else if (btn.classList.contains("btn-cancelar-provento")) cancelarProvento(id);
    else if (btn.classList.contains("btn-assinar-provento")) assinarProvento(id);
    else if (btn.classList.contains("btn-recibo-provento")) abrirReciboProventoPdf(id);
  });
  document.getElementById("proventoViewRecibo")?.addEventListener("click", () => {
    const id = document.getElementById("proventoViewRecibo")?.dataset?.id;
    if (id) abrirReciboProventoPdf(id);
  });
  document.getElementById("proventosFilterForm")?.addEventListener("submit", async (e) => { e.preventDefault(); await loadProventos(getProventosFiltros()); });
  document.getElementById("proventosLimparFiltros")?.addEventListener("click", () => {
    const sel = document.getElementById("proventosFiltroFuncionario");
    const busca = document.getElementById("proventosFiltroFuncionarioBusca");
    if (sel) sel.value = "";
    if (busca) busca.value = "";
    populateProventosFiltroFuncionario(state.funcionarios);
    ["proventosFiltroTipo", "proventosFiltroUnidade", "proventosFiltroStatus", "proventosFiltroDataInicio", "proventosFiltroDataFim"].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.value = "";
    });
    loadProventos();
  });
  document.getElementById("proventoForm")?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const form = document.getElementById("proventoForm");
    const id = form.elements.id?.value;
    const payload = { funcionario_id: form.elements.funcionario_id?.value, unidade_id: form.elements.unidade_id?.value || null, tipo: form.elements.tipo?.value, valor: form.elements.valor?.value, data_provento: form.elements.data_provento?.value, competencia: form.elements.competencia?.value || null, observacao_interna: form.elements.observacao_interna?.value || null };
    if (id) delete payload.funcionario_id;
    const obrigatoriosFaltando =
      !payload.unidade_id ||
      !payload.tipo ||
      !payload.valor ||
      !payload.data_provento ||
      (!id && !form.elements.funcionario_id?.value);
    if (obrigatoriosFaltando) {
      const fb = document.getElementById("proventoFormFeedback");
      if (fb) { fb.textContent = "Preencha todos os campos obrigatórios (funcionário, unidade, tipo, valor e data do provento)."; fb.className = "form-feedback error"; fb.classList.remove("hidden"); }
      return;
    }
    if (Number(payload.valor) <= 0) { showToast("Valor deve ser maior que zero.", "error"); return; }
    const feedback = document.getElementById("proventoFormFeedback");
    if (feedback) { feedback.classList.add("hidden"); }
    try {
      if (id) {
        await fetchJSON(`/proventos/${id}`, { method: "PUT", body: JSON.stringify(payload) });
        showToast("Provento atualizado.", "success");
      } else {
        await fetchJSON("/proventos", { method: "POST", body: JSON.stringify(payload) });
        showToast("Provento cadastrado.", "success");
      }
      toggleModal(document.getElementById("proventoModal"), false);
      await loadProventos(getProventosFiltros());
    } catch (err) {
      const msg = String(err?.message || err?.error || "Erro ao salvar.");
      if (feedback) { feedback.textContent = msg; feedback.className = "form-feedback error"; feedback.classList.remove("hidden"); }
      else showToast(msg, "error");
    }
  });
  dom.openUnidadeBtn?.addEventListener("click", async () => {
    if (!canManageUnidades()) {
      showToast("Sem permissao para gerenciar unidades.", "warning");
      return;
    }
    if (state.unidadeInlineVisivel) {
      state.unidadeInlineVisivel = false;
      applyPermissions();
      dom.unidadeInlineForm?.reset();
      if (dom.unidadeInlineForm?.elements.id) dom.unidadeInlineForm.elements.id.value = "";
      dom.unidadeInlineForm?.elements.nome?.blur();
      return;
    }
    state.unidadeInlineVisivel = true;
    applyPermissions();
    if (dom.unidadeInlineForm) {
      dom.unidadeInlineForm.reset();
      if (dom.unidadeInlineForm.elements.id) dom.unidadeInlineForm.elements.id.value = "";
      if (dom.unidadeInlineForm.elements.ativo) dom.unidadeInlineForm.elements.ativo.value = "1";
    }
    try {
      await loadUsuarios();
    } catch (err) {
      showToast(err.message || "Falha ao carregar usuarios.", "error");
    }
    refreshGerenteSelect();
    if (!state.usuarios.length) {
      showToast("Cadastre usuarios para atribuir um gerente.", "warning");
    }
    if (dom.unidadeInlineFormCard) {
      dom.unidadeInlineFormCard.classList.remove("hidden");
      dom.unidadeInlineFormCard.scrollIntoView({ behavior: "smooth", block: "start" });
    }
    const nomeInput = dom.unidadeInlineForm?.elements.nome;
    if (nomeInput) nomeInput.focus();
  });
  dom.closeUnidadeBtn?.addEventListener("click", () => toggleModal(dom.unidadeModal, false));
  dom.cancelUnidadeBtn?.addEventListener("click", () => toggleModal(dom.unidadeModal, false));
  dom.cancelInlineUnidadeBtn?.addEventListener("click", () => {
    state.unidadeInlineVisivel = false;
    dom.unidadeInlineForm?.reset();
    if (dom.unidadeInlineForm?.elements.id) dom.unidadeInlineForm.elements.id.value = "";
    dom.unidadeInlineForm?.elements.nome?.blur();
    applyPermissions();
  });

  dom.openLocalModalBtn?.addEventListener("click", async () => {
    if (!isAdmin()) {
      showToast("Apenas administradores podem gerenciar locais.", "warning");
      return;
    }
    try {
      await loadUnidades(false);
    } catch (err) {
      showToast(err?.message || "Falha ao carregar unidades.", "error");
      return;
    }
    dom.localForm?.reset();
    if (dom.localTipoSelect) dom.localTipoSelect.value = "";
    if (dom.localNivelAcessoSelect) dom.localNivelAcessoSelect.value = "";
    if (dom.localForm?.elements.data_cadastro) {
      dom.localForm.elements.data_cadastro.value = todayInputValue();
    }
    if (dom.localForm?.elements.id) dom.localForm.elements.id.value = "";
    if (dom.localModalTitle) dom.localModalTitle.textContent = "Novo local";
    toggleModal(dom.localModal, true);
  });
  dom.closeLocalModalBtn?.addEventListener("click", () => {
    dom.localForm?.reset();
    if (dom.localForm?.elements.id) dom.localForm.elements.id.value = "";
    if (dom.localModalTitle) dom.localModalTitle.textContent = "Novo local";
    toggleModal(dom.localModal, false);
  });
  dom.cancelLocalBtn?.addEventListener("click", () => {
    dom.localForm?.reset();
    if (dom.localForm?.elements.id) dom.localForm.elements.id.value = "";
    if (dom.localModalTitle) dom.localModalTitle.textContent = "Novo local";
    toggleModal(dom.localModal, false);
  });

  dom.openEntradaBtn?.addEventListener("click", async () => {
    console.log("Botão Registrar Entrada clicado");
    
    // Abre o modal primeiro para resposta imediata (como o botão de saída)
    dom.entradaForm?.reset();
    if (dom.entradaUnidadeSelect) dom.entradaUnidadeSelect.value = "";
    const entradaCustoInput = dom.entradaForm?.elements.custo_unitario;
    if (entradaCustoInput) {
      entradaCustoInput.dataset.value = "";
      entradaCustoInput.value = "";
    }
    resetEntradaLocalSelect();
    toggleModal(dom.entradaModal, true);
    
    // Carrega dados em background após abrir o modal (não bloqueia a abertura)
    Promise.all([
      // Carrega produtos se ainda não foram carregados
      (!state.produtos || state.produtos.length === 0) 
        ? loadProdutos().catch(err => {
            console.error("Erro ao carregar produtos:", err);
            showToast("Erro ao carregar produtos.", "error");
          })
        : Promise.resolve(),
      // Carrega unidades, locais e lotes em paralelo
      loadUnidades(false).catch(() => {}),
      loadLocais(true).catch(() => {}),
      loadLotes().catch(() => {}),
    ]).then(() => {
      // Atualiza o select de produtos após carregar
      refreshProdutoSelects();
      
      // Verifica se o select foi populado
      const entradaProdutoSelect = dom.entradaForm?.querySelector('select[name="produto_id"]');
      if (entradaProdutoSelect) {
        const optionsCount = entradaProdutoSelect.options.length;
        console.log("Select de produtos atualizado. Opções disponíveis:", optionsCount);
        if (optionsCount <= 1) {
          console.warn("Apenas a opção padrão encontrada. Produtos podem não ter sido carregados.");
        }
      }
      
      // Atualiza locais e lotes após carregar
      ensureLocaisCarregados()
        .then(() => handleEntradaUnidadeChange())
        .catch(() => {
          resetEntradaLocalSelect();
        });
      populateEntradaLoteOptions();
      handleEntradaUnidadeChange();
    });
  });
  dom.closeEntradaBtn?.addEventListener("click", () => toggleModal(dom.entradaModal, false));
  dom.cancelEntradaBtn?.addEventListener("click", () => {
    dom.entradaForm?.reset();
    const entradaCustoInput = dom.entradaForm?.elements.custo_unitario;
    if (entradaCustoInput) {
      entradaCustoInput.dataset.value = "";
      entradaCustoInput.value = "";
    }
    resetEntradaLocalSelect();
    toggleModal(dom.entradaModal, false);
  });

  function openNovoLoteModal() {
    if (!canManageProdutos()) {
      showToast("Sem permissao para cadastrar lotes.", "warning");
      return;
    }
    if (dom.loteModalTitle) dom.loteModalTitle.textContent = "Novo lote";
    dom.loteForm?.reset();
    if (dom.loteForm?.elements.id) dom.loteForm.elements.id.value = "";
    if (dom.loteForm?.elements.status) dom.loteForm.elements.status.value = "ATIVO";
    if (dom.loteForm?.elements.data_fabricacao) dom.loteForm.elements.data_fabricacao.value = "";
    if (dom.loteForm?.elements.data_validade) dom.loteForm.elements.data_validade.value = "";
    const loteCustoInput = dom.loteForm?.elements.custo_unitario;
    if (loteCustoInput) {
      loteCustoInput.dataset.value = "";
      loteCustoInput.value = "";
    }
    const codigoInput = dom.loteForm?.elements?.codigo_lote;
    if (codigoInput) codigoInput.value = generateLoteCodigo();
    toggleModal(dom.loteModal, true);
  }
  document.body.addEventListener("click", (e) => {
    if (e.target.id === "openNovoLote" || e.target.closest("#openNovoLote")) {
      e.preventDefault();
      openNovoLoteModal();
    }
  });
  dom.closeLoteBtn?.addEventListener("click", () => toggleModal(dom.loteModal, false));
  dom.cancelLoteBtn?.addEventListener("click", () => {
    dom.loteForm?.reset();
    if (dom.loteForm?.elements.status) dom.loteForm.elements.status.value = "ATIVO";
    const loteCustoInput = dom.loteForm?.elements.custo_unitario;
    if (loteCustoInput) {
      loteCustoInput.dataset.value = "";
      loteCustoInput.value = "";
    }
    toggleModal(dom.loteModal, false);
  });

  dom.openSaidaBtn?.addEventListener("click", async () => {
    console.log("🔓 Abrindo modal de saída");
    
    // Verifica se BAR ou COZINHA podem usar (garantia extra)
    const perfilAtual = (currentUser?.perfil || "").toString().trim().toUpperCase();
    const isCozinhaOuBar = perfilAtual === "COZINHA" || perfilAtual === "BAR" || perfilAtual === "ATENDENTE";
    const regras = PERMISSOES[perfilAtual] || PERMISSOES.VISUALIZADOR;
    const podeUsar = regras.canRegistrarMovimentacoes || isCozinhaOuBar;
    
    if (!podeUsar) {
      showToast("Você não tem permissão para registrar saídas.", "warning");
      return;
    }
    
    await loadUnidades(false).catch(() => {});
    
    // Garante que o formulário está disponível
    if (!dom.saidaForm) {
      dom.saidaForm = document.getElementById("saidaForm");
      console.log("📋 Formulário recuperado:", dom.saidaForm ? "encontrado" : "não encontrado");
    }
    
    // Atualiza referências dos elementos
    dom.saidaProdutoSelect = document.getElementById("saidaProdutoSelect");
    dom.saidaOrigemSelect = document.getElementById("saidaOrigemUnidade");
    dom.saidaMotivo = document.getElementById("saidaMotivo");
    dom.saidaDestinoSelect = document.getElementById("saidaDestinoUnidade");
    dom.saidaLoteWrapper = document.getElementById("saidaLoteWrapper");
    dom.saidaLoteSelect = document.getElementById("saidaLoteSelect");
    dom.saidaLoteManualWrapper = document.getElementById("saidaLoteManualWrapper");
    dom.saidaLoteManualInput = document.getElementById("saidaLoteManualInput");
    
    if (dom.saidaForm) {
      dom.saidaForm.reset();
      updateSaidaDestinoVisibility();
      resetSaidaProdutoSelect();
      
      // Garante que o botão de submit tenha o listener quando o modal abre
      const submitBtn = dom.saidaForm.querySelector('button[type="submit"]');
      if (submitBtn) {
        // Remove qualquer handler anterior e adiciona novo usando onclick direto
        submitBtn.onclick = null; // Limpa handler anterior
        submitBtn.onclick = function(e) {
          console.log("🔘 BOTÃO DE SUBMIT CLICADO - Handler direto no botão");
          e.preventDefault();
          e.stopPropagation();
          
          // Chama submitSaida diretamente
          submitSaida(e).catch(err => {
            console.error("❌ Erro ao processar:", err);
            showToast(err.message || "Erro ao registrar saída", "error");
          });
        };
        console.log("✅ Handler onclick direto anexado ao botão ao abrir modal");
      } else {
        console.error("❌ Botão de submit não encontrado ao abrir modal");
      }
    }
    
    toggleModal(dom.saidaModal, true);
    console.log("✅ Modal de saída aberto");
  });
  dom.closeSaidaBtn?.addEventListener("click", () => {
    resetSaidaFromQR();
    dom.saidaForm?.reset();
    resetSaidaProdutoSelect();
    updateSaidaDestinoVisibility();
    toggleModal(dom.saidaModal, false);
  });
  dom.cancelSaidaBtn?.addEventListener("click", (e) => {
    e.preventDefault();
    e.stopPropagation();
    resetSaidaFromQR();
    if (dom.saidaForm) {
      dom.saidaForm.reset();
      resetSaidaProdutoSelect();
      updateSaidaDestinoVisibility();
      // Garante que o botão de submit está habilitado
      const submitBtn = dom.saidaForm.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = "Registrar saida";
      }
    }
  });

  dom.openListaCompraBtn?.addEventListener("click", async () => {
    if (!canCreateLista()) {
      showToast("Você não tem permissão para criar novas listas.", "warning");
      return;
    }
    await abrirListaCompraModal();
  });
  dom.closeListaCompraBtn?.addEventListener("click", () => toggleModal(dom.listaCompraModal, false));
  dom.cancelListaCompraBtn?.addEventListener("click", () => toggleModal(dom.listaCompraModal, false));

  dom.listaCompraAdicionarItem?.addEventListener("click", () => {
    if (!listaPermiteAdicionarItens()) {
      showToast("Você não tem permissão para adicionar itens nesta lista.", "warning");
      return;
    }
    abrirItemCompraModal();
  });
  dom.closeItemCompraBtn?.addEventListener("click", () => {
    dom.itemCompraForm?.reset();
    listaCompraItemEdicaoId = null;
    // Restaura visibilidade dos campos ao fechar o modal (caso algum perfil diferente abra depois)
    const quantidadeCompradaLabel = document.getElementById("itemCompraLabelQuantidadeComprada");
    const valorUnitarioLabel = document.getElementById("itemCompraLabelValorUnitario");
    const valorTotalLabel = document.getElementById("itemCompraLabelValorTotal");
    if (quantidadeCompradaLabel) quantidadeCompradaLabel.classList.remove("hidden");
    if (valorUnitarioLabel) valorUnitarioLabel.classList.remove("hidden");
    if (valorTotalLabel) valorTotalLabel.classList.remove("hidden");
    toggleModal(dom.itemCompraModal, false);
  });
  dom.cancelItemCompraBtn?.addEventListener("click", () => {
    dom.itemCompraForm?.reset();
    listaCompraItemEdicaoId = null;
    // Restaura visibilidade dos campos ao fechar o modal (caso algum perfil diferente abra depois)
    const quantidadeCompradaLabel = document.getElementById("itemCompraLabelQuantidadeComprada");
    const valorUnitarioLabel = document.getElementById("itemCompraLabelValorUnitario");
    const valorTotalLabel = document.getElementById("itemCompraLabelValorTotal");
    if (quantidadeCompradaLabel) quantidadeCompradaLabel.classList.remove("hidden");
    if (valorUnitarioLabel) valorUnitarioLabel.classList.remove("hidden");
    if (valorTotalLabel) valorTotalLabel.classList.remove("hidden");
    updateItemModalTotals();
    toggleModal(dom.itemCompraModal, false);
  });
  if (dom.itemCompraForm?.elements.valor_total) {
    dom.itemCompraForm.elements.valor_total.readOnly = true;
  }
  ["quantidade_planejada", "quantidade_comprada", "valor_unitario"].forEach((field) => {
    const input = dom.itemCompraForm?.elements[field];
    if (input) {
      input.addEventListener("input", () => updateItemModalTotals());
      input.addEventListener("change", () => updateItemModalTotals());
    }
  });

  dom.listaCompraAdicionarEstabelecimento?.addEventListener("click", async () => {
    if (!state.listaCompraAtual || !state.listaCompraAtual.id) {
      showToast("Erro: nenhuma lista selecionada.", "error");
      return;
    }
    abrirEstabelecimentoModal();
  });
  dom.closeEstabelecimentoCompraBtn?.addEventListener("click", () => toggleModal(dom.estabelecimentoCompraModal, false));
  dom.cancelEstabelecimentoCompraBtn?.addEventListener("click", () => toggleModal(dom.estabelecimentoCompraModal, false));

  dom.listaCompraFinalizar?.addEventListener("click", () => {
    if (dom.listaCompraFinalizar.disabled) return;
    abrirFinalizarListaModal();
  });
  dom.closeFinalizarListaBtn?.addEventListener("click", () => toggleModal(dom.finalizarListaModal, false));
  dom.cancelFinalizarListaBtn?.addEventListener("click", () => toggleModal(dom.finalizarListaModal, false));

  // Event listeners para modal de erro de movimentação
  dom.closeErroMovimentacaoBtn?.addEventListener("click", closeErrorModal);
  dom.fecharErroMovimentacaoBtn?.addEventListener("click", closeErrorModal);
  dom.erroMovimentacaoModal?.addEventListener("click", (e) => {
    if (e.target === dom.erroMovimentacaoModal) {
      closeErrorModal();
    }
  });

  dom.listaCompraStatusRascunho?.addEventListener("click", () => atualizarStatusListaCompra("RASCUNHO"));
  dom.listaCompraStatusEmCompras?.addEventListener("click", () => atualizarStatusListaCompra("EM_COMPRAS"));
  dom.listaCompraStatusPausada?.addEventListener("click", () => atualizarStatusListaCompra("PAUSADA"));

  dom.listaCompraPdf?.addEventListener("click", () => {
    // O botão "Abrir PDF" agora apenas chama a mesma função de gerar PDF
    // que mostra o PDF diretamente no navegador
    gerarPdfLista();
  });

  dom.listaCompraGerarPdf?.addEventListener("click", () => {
    if (dom.listaCompraGerarPdf.disabled) return;
    gerarPdfLista();
  });
  dom.listaCompraFiltroStatus?.addEventListener("change", (event) => {
    const value = (event.target.value || "ativas").toString().trim().toLowerCase();
    const allowed = ["ativas", "todas", "finalizadas"];
    const selecionado = allowed.includes(value) ? value : "ativas";
    if (state.listaComprasFiltroStatus === selecionado) return;
    state.listaComprasFiltroStatus = selecionado;
    state.listaCompraAtual = null;
    renderListaCompraDetalhes(null);
    loadListasCompras();
  });

  dom.listaCompraLancarEstoque?.addEventListener("click", () => {
    if (dom.listaCompraLancarEstoque.disabled) return;
    if (dom.listaCompraLancarEstoque.dataset.loading === "1") return;
    lancarListaNoEstoque();
  });

  dom.listaCompraObservacoes?.addEventListener("input", (event) => {
    if (!state.listaCompraAtual) return;
    if (!listaPermiteEdicao()) {
      event.target.value = state.listaCompraAtual.observacoes || "";
      return;
    }
    atualizarObservacoesListaDebounced(state.listaCompraAtual.id, event.target.value);
  });

  // Sugestões de compras
  dom.openSugestoesComprasBtn?.addEventListener("click", async () => {
    await abrirSugestoesComprasModal();
  });
  
  dom.closeSugestoesComprasBtn?.addEventListener("click", () => {
    toggleModal(dom.sugestoesComprasModal, false);
  });
  
  dom.sugestoesBuscarBtn?.addEventListener("click", async () => {
    await buscarSugestoesCompras();
  });
}

// Habilita interacoes principais nos cards do dashboard para navegacao rapida.
function setupCards() {
  document.getElementById("cardMinimoSelect")?.addEventListener("change", async (event) => {
    const produtoId = Number(event.target.value);
    if (!Number.isFinite(produtoId) || produtoId <= 0) return;
    navigateTo("estoque");
    try {
      await loadEstoqueProdutos();
      const selectEstoque = document.getElementById("estoqueProdutoSelect");
      if (selectEstoque) {
        selectEstoque.value = String(produtoId);
        selectEstoque.dispatchEvent(new Event("change"));
      } else {
        await loadEstoqueProduto(produtoId);
      }
    } catch (err) {
      showToast(err?.message || "Falha ao carregar estoque.", "error");
    }
    event.target.value = "";
  });

  dom.cardLotesAVencer?.addEventListener("click", async () => {
    navigateTo("lotes");
    const hoje = new Date();
    const ate = new Date();
    ate.setDate(hoje.getDate() + 15);
    await loadLotes({ validade_de: hoje.toISOString().slice(0, 10), validade_ate: ate.toISOString().slice(0, 10) }).catch(() => {});
  });

  dom.cardComprasAndamento?.addEventListener("click", async () => {
    state.listaComprasFiltroStatus = "ativas";
    if (dom.listaCompraFiltroStatus) dom.listaCompraFiltroStatus.value = "ativas";
    navigateTo("compras");
    await loadListasCompras().catch((err) => showToast(err.message || "Falha ao carregar listas.", "error"));
  });

  dom.cardLotesVencidos?.addEventListener("click", async () => {
    navigateTo("lotes");
    await loadLotes({ status: "VENCIDO" }).catch(() => {});
  });
}

// Mapeia cliques de navegacao lateral e recarrega secoes conforme necessario.
function setupNavigation() {
  dom.navLinks.forEach((link) => {
    link.addEventListener("click", async (event) => {
      event.preventDefault();
      const regras = applyPermissions();
      const target = link.dataset.section;
      if (!regras.sections.includes(target)) {
        showToast("Perfil sem permissao para acessar esta area.", "error");
        return;
      }
      navigateTo(target);
      if (isMobileViewport()) setSidebarOpen(false);
      try {
      if (target === "dashboard") await loadDashboard();
      else if (target === "produtos") await loadProdutos();
      else if (target === "estoque") await loadEstoqueProdutos();
      else if (target === "unidades") await Promise.all([loadUnidades(), loadUsuarios()]);
      else if (target === "usuarios") await loadUsuarios();
      else if (target === "funcionarios") await loadFuncionarios();
      else if (target === "reciboAjuda") {
        if (typeof window.loadReciboAjudaSection === "function") {
          await window.loadReciboAjudaSection();
        } else {
          await Promise.all([loadUnidades(false).catch(() => {}), loadFuncionarios(false).catch(() => {})]);
        }
      }
      else if (target === "proventos") {
        try {
          const perfil = (currentUser?.perfil || "").toString().trim().toUpperCase();
          const podeCriarProvento = ["ADMIN","GERENTE","FINANCEIRO","ASSISTENTE_ADMINISTRATIVO"].includes(perfil);
          const isFuncionario = !podeCriarProvento; // Sem permissão para lançar: padrão Ing (Meus Proventos, sem filtros, sem botão Novo)
          const titleEl = document.getElementById("proventosSectionTitle");
          const subtitleEl = document.getElementById("proventosSectionSubtitle");
          const filterForm = document.getElementById("proventosFilterForm");
          const openProventoBtn = document.getElementById("openProvento");
          if (titleEl) titleEl.textContent = isFuncionario ? "Meus Proventos" : "Proventos";
          if (subtitleEl) subtitleEl.textContent = isFuncionario ? "Proventos que precisam da sua aceite ou já foram processados" : "Controle de proventos e lançamentos relacionados aos funcionários";
          if (filterForm) filterForm.style.display = isFuncionario ? "none" : "";
          if (openProventoBtn) openProventoBtn.classList.toggle("hidden", !podeCriarProvento);
          if (!isFuncionario) {
            await loadUnidades(false).catch(() => {});
            await loadFuncionarios().catch(() => {});
            const selUn = document.getElementById("proventosFiltroUnidade");
            if (selUn && state.unidades?.length) {
              selUn.innerHTML = '<option value="">Todas as unidades</option>' + state.unidades.map(u => `<option value="${u.id}">${(u.nome||"").replace(/</g,"&lt;")}</option>`).join("");
            }
            await loadProventos(getProventosFiltros());
          } else {
            await loadProventos();
          }
        } catch (e) {
          showToast(e?.message || "Erro ao carregar proventos", "error");
        }
      }
      else if (target === "lotes") await loadLotes();
      else if (target === "locais") await Promise.all([loadLocais(true), loadUnidades(false)]);
      else if (target === "movimentacoes") {
        // ✅ Garante que produtos e unidades estejam carregados para popular os selects
        await Promise.all([
          loadProdutos().catch(() => {}),
          loadUnidades().catch(() => {})
        ]);
        // ✅ Popula os selects
        refreshProdutoSelects();
        refreshUnidadeSelects();
        // ✅ Carrega movimentações
        await loadMovimentacoesDetalhadas({}, { refreshDashboard: true });
      }
      else if (target === "relatorios") await loadRelatorio();
      else if (target === "compras") await loadListasCompras();
      else if (target === "fornecedores") await loadFornecedores();
      else if (target === "fornecedoresBackup") await loadFornecedoresBackup();
      else if (target === "logs") {
        fetch(`${API_URL}/audit-logs/registrar`, {
          method: "POST",
          headers: { "Content-Type": "application/json", "X-Usuario-Id": String(currentUser?.id || ""), ...getDeviceHeaders() },
          body: JSON.stringify({ acao: "acessar_secao", recurso: "logs", descricao: "Acesso à página de Logs e Auditoria" }),
        }).catch(() => {});
        await loadLogs();
      }
      else if (target === "reservaMesa") {
        var uSelect = document.getElementById('reservasUnidadeFiltro');
        if (uSelect && uSelect.options.length <= 1) {
          var unidades = state.unidades && state.unidades.length ? state.unidades : await fetchJSON('/unidades').catch(function() { return []; });
          uSelect.innerHTML = '<option value="">Selecione a unidade</option>';
          (unidades || []).forEach(function(u) {
            var opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = u.nome || 'Unidade ' + u.id;
            uSelect.appendChild(opt);
          });
          if (currentUser && currentUser.unidade_id && (currentUser.perfil || '').toUpperCase() !== 'ADMIN') {
            uSelect.value = currentUser.unidade_id;
            uSelect.disabled = true;
          }
        }
        await loadReservasMesas();
      }
      else if (target === "alvara") {
        await populateAlvarasUnidades().catch(() => {});
        await loadAlvaras(collectAlvarasListFiltersFromDOM()).catch(() => {});
      } else if (target === "fechaTecnica") {
        onNavigateFichaTecnicaCallback();
      }
      else if (target === "historicoReservas") {
        var uSelect = document.getElementById('historicoUnidadeFiltro');
        if (uSelect && uSelect.options.length <= 1) {
          var unidades = state.unidades && state.unidades.length ? state.unidades : await fetchJSON('/unidades').catch(function() { return []; });
          state.unidades = unidades;
          uSelect.innerHTML = '<option value="">Selecione a unidade</option>';
          (unidades || []).forEach(function(u) {
            var opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = u.nome || 'Unidade ' + u.id;
            uSelect.appendChild(opt);
          });
          if (currentUser && currentUser.unidade_id && (currentUser.perfil || '').toUpperCase() !== 'ADMIN') {
            uSelect.value = currentUser.unidade_id;
            uSelect.disabled = true;
          }
        }
        var dInicio = document.getElementById('historicoDataInicio');
        var dFim = document.getElementById('historicoDataFim');
        if (dInicio && !dInicio.value) dInicio.value = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
        if (dFim && !dFim.value) dFim.value = new Date().toISOString().slice(0, 10);
        await loadHistoricoReservas();
      }
      else if (target === "boletao") {
        const tbody = document.getElementById("boletosTable");
        if (!tbody) {
          showToast("Erro: Tabela não encontrada. Recarregue a página.", "error");
        } else {
          await loadBoletos({}).catch(() => {});
          await loadBoletosResumo().catch(() => {});
        }
      }
      else if (target === "fechamento") {
        await loadFechamentoCaixaSection();
      }
    } catch (err) {
      showToast(err.message, "error");
      }
    });
  });

  // Setup submenu toggle for Financeiro
  const financeiroMenu = document.getElementById('financeiroMenu');
  if (financeiroMenu) {
    financeiroMenu.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      const parent = financeiroMenu.closest('.nav-submenu');
      if (parent) {
        parent.classList.toggle('open');
      }
    });
  }
  // Setup submenu toggle for RH
  const rhMenu = document.getElementById('rhMenu');
  if (rhMenu) {
    rhMenu.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      const parent = rhMenu.closest('.nav-submenu');
      if (parent) parent.classList.toggle('open');
    });
  }
  // Setup submenu toggle for Configuracoes
  const configuracoesMenu = document.getElementById('configuracoesMenu');
  if (configuracoesMenu) {
    configuracoesMenu.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      const parent = configuracoesMenu.closest('.nav-submenu');
      if (parent) {
        parent.classList.toggle('open');
      }
    });
  }
  const reservaMenu = document.getElementById('reservaMenu');
  if (reservaMenu) {
    reservaMenu.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      const parent = reservaMenu.closest('.nav-submenu');
      if (parent) parent.classList.toggle('open');
    });
  }
}

function togglePasswordVisibility(button) {
  if (!button) return;
  const targetId = button.dataset.target;
  if (!targetId) return;
  const input = document.getElementById(targetId);
  if (!input) return;
  const isVisible = input.type === "text";
  input.type = isVisible ? "password" : "text";
  button.setAttribute("aria-pressed", String(!isVisible));
  button.setAttribute("aria-label", isVisible ? "Mostrar senha" : "Ocultar senha");
  button.classList.toggle("is-visible", !isVisible);
}

function setupPasswordToggles() {
  dom.passwordToggles.forEach((button) => {
    button.addEventListener("click", () => togglePasswordVisibility(button));
  });
}

// ===== Funcoes do Modulo de Boletos =====

// Popula o select de unidades no modal de boleto
// Popula o filtro Mês/Ano: ano atual completo + ano seguinte inteiro (gerado dinamicamente)
function populateBoletosMesAnoFiltro() {
  const select = document.getElementById('boletosMesAnoFiltro');
  if (!select) return;
  const hoje = new Date();
  const anoAtual = hoje.getFullYear();
  const nomesMeses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
  const valorAtual = select.value;
  select.innerHTML = '<option value="">📋 Todos os boletos</option>';
  const anoInicio = Math.max(2026, anoAtual - 1);
  const anoFim = Math.max(anoAtual + 1, 2027);
  for (let ano = anoInicio; ano <= anoFim; ano++) {
    for (let m = 0; m <= 11; m++) {
      const valor = `${ano}-${String(m + 1).padStart(2, '0')}`;
      const opt = document.createElement('option');
      opt.value = valor;
      opt.textContent = `${nomesMeses[m]} ${ano}`;
      select.appendChild(opt);
    }
  }
  if (valorAtual) select.value = valorAtual;
}

function populateAlvarasMesAnoFiltro() {
  const select = document.getElementById('alvarasMesAnoFiltro');
  if (!select) return;
  const hoje = new Date();
  const anoAtual = hoje.getFullYear();
  const nomesMeses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
  const valorAtual = select.value;
  select.innerHTML = '<option value="">📋 Todos os alvarás</option>';
  const anoInicio = Math.max(2026, anoAtual - 1);
  const anoFim = Math.max(anoAtual + 1, 2027);
  for (let ano = anoInicio; ano <= anoFim; ano++) {
    for (let m = 0; m <= 11; m++) {
      const valor = `${ano}-${String(m + 1).padStart(2, '0')}`;
      const opt = document.createElement('option');
      opt.value = valor;
      opt.textContent = `${nomesMeses[m]} ${ano}`;
      select.appendChild(opt);
    }
  }
  if (valorAtual) select.value = valorAtual;
}

async function populateBoletosUnidades() {
  const select = document.querySelector('#boletoForm select[name="unidade_id"]');
  if (!select) return;

  try {
    const response = await fetch(`${API_URL}/unidades`, {
      headers: {
        'Content-Type': 'application/json',
        'X-Usuario-Id': currentUser?.id || ''
      }
    });

    if (!response.ok) throw new Error('Erro ao buscar unidades');

    const unidades = await response.json();
    
    select.innerHTML = '<option value="">Selecione a unidade</option>';
    unidades.forEach(unidade => {
      const option = document.createElement('option');
      option.value = unidade.id;
      option.textContent = unidade.nome;
      select.appendChild(option);
    });
  } catch (error) {
    console.error('Erro ao carregar unidades:', error);
    showToast('Erro ao carregar unidades', 'error');
  }
}

// Carrega boletos do backend
async function loadBoletos(filtros = {}) {
  console.log('📊 === LOAD BOLETOS INICIADO ===');
  console.log('Filtros:', filtros);
  
  const tbody = document.getElementById('boletosTable');
  
  if (!tbody) {
    console.error('❌ CRÍTICO: Elemento boletosTable não existe!');
    showToast('Erro: Tabela não encontrada', 'error');
    return;
  }
  
  // Mostra carregando
  tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 30px; color: #2196F3; font-size: 1.1rem;">⏳ Carregando boletos...</td></tr>';
  
  try {
    const params = new URLSearchParams();
    if (filtros.mes_ano) params.append('mes_ano', filtros.mes_ano);
    if (filtros.unidade_id) params.append('unidade_id', filtros.unidade_id);
    if (filtros.status) params.append('status', filtros.status);

    const url = `${API_URL}/boletos?${params.toString()}`;
    console.log('📤 URL:', url);

    const response = await fetch(url, {
      headers: {
        'Content-Type': 'application/json',
        'X-Usuario-Id': currentUser?.id || '1'
      }
    });

    console.log('📥 Status:', response.status);

    if (!response.ok) {
      const errorText = await response.text();
      console.error('❌ Erro:', errorText);
      tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 30px; color: #f44336; font-size: 1.1rem;">❌ Erro ao carregar boletos</td></tr>';
      throw new Error(`HTTP ${response.status}`);
    }

    const boletos = await response.json();
    console.log(`✅ ${boletos.length} boletos recebidos`);
    
    if (boletos.length === 0) {
      tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 30px; color: #999; font-size: 1.1rem;">📋 Nenhum boleto encontrado</td></tr>';
      console.log('⚠️ Array de boletos está vazio');
      return;
    }
    
    renderBoletos(boletos);
    console.log('✅ === LOAD BOLETOS CONCLUÍDO ===');
    
  } catch (error) {
    console.error('❌ ERRO:', error);
    showToast('Erro ao carregar: ' + error.message, 'error');
    tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 30px; color: #f44336;">❌ Erro. Tente novamente.</td></tr>';
  }
}

// Renderiza boletos na tabela
function renderBoletos(boletos) {
  console.log('🎨 renderBoletos() chamado com:', boletos ? boletos.length : 0, 'boletos');
  
  const tbody = document.getElementById('boletosTable');
  if (!tbody) {
    console.error('❌ Elemento boletosTable não encontrado!');
    return;
  }

  if (!boletos || boletos.length === 0) {
    console.warn('⚠️ Nenhum boleto para renderizar');
    tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; color: #607d8b;">Nenhum boleto encontrado</td></tr>';
    return;
  }

  console.log('✅ Renderizando', boletos.length, 'boletos');

  try {
    const hoje = new Date();
    const hojeY = hoje.getFullYear(), hojeM = hoje.getMonth(), hojeD = hoje.getDate();
    // Extrai ano/mes/dia da data de vencimento (sem usar new Date para evitar fuso)
    const parseDataLocal = (str) => {
      if (!str) return null;
      const m = String(str).trim().match(/^(\d{4})-(\d{2})-(\d{2})/);
      if (m) return new Date(parseInt(m[1], 10), parseInt(m[2], 10) - 1, parseInt(m[3], 10));
      return null;
    };
    const diasEntre = (venc) => {
      if (!venc) return 0;
      const v = parseDataLocal(venc);
      if (!v) return 0;
      const d1 = new Date(hojeY, hojeM, hojeD);
      const d2 = new Date(v.getFullYear(), v.getMonth(), v.getDate());
      return Math.round((d2 - d1) / (1000 * 60 * 60 * 24));
    };
    const getSortKey = (b) => {
      if (b.status === 'PAGO' || b.status === 'CANCELADO') return 99999;
      const diff = diasEntre(b.data_vencimento);
      return diff;
    };
    const ordenados = [...boletos].sort((a, b) => getSortKey(a) - getSortKey(b));

    const html = ordenados.map(boleto => {
      const valorJuros = parseFloat(boleto.juros_multa || 0);
      let statusClass, statusLabel;
      if (boleto.status === 'PAGO') {
        statusClass = valorJuros > 0 ? 'status-pill--warning' : 'status-pill--success';
        statusLabel = valorJuros > 0 ? 'Pago com atraso' : 'Pago';
      } else if (boleto.status === 'CANCELADO') {
        statusClass = 'status-pill--muted';
        statusLabel = 'Cancelado';
      } else {
        // Calcula estado real pela data de vencimento (A vencer ou Atrasado)
        const diff = diasEntre(boleto.data_vencimento);
        if (diff < 0) {
          statusClass = 'status-pill--danger';
          statusLabel = 'Atrasado';
        } else {
          statusClass = 'status-pill--info';
          statusLabel = 'A vencer';
        }
      }
      
      // Determina o ícone do anexo baseado no tipo
      let anexoIcon = '';
      if (boleto.anexo_path) {
        const tipo = (boleto.anexo_tipo || '').toLowerCase();
        if (tipo === 'pdf') {
          anexoIcon = `<a href="${API_URL}/boletos/${boleto.id}/anexo" target="_blank" title="Baixar ${boleto.anexo_nome}" style="font-size: 1.5rem; text-decoration: none;">📄</a>`;
        } else {
          anexoIcon = `<a href="${API_URL}/boletos/${boleto.id}/anexo" target="_blank" title="Baixar ${boleto.anexo_nome}" style="font-size: 1.5rem; text-decoration: none;">🖼️</a>`;
        }
      } else {
        anexoIcon = '<span style="color: #ccc;">-</span>';
      }
      
      // Calcula dias restante (para A_VENCER ou Atrasado)
      let diasRestante = '-';
      if (boleto.status !== 'PAGO' && boleto.status !== 'CANCELADO') {
        const diffDays = diasEntre(boleto.data_vencimento);
        if (diffDays > 0) {
          diasRestante = `${diffDays} dia${diffDays !== 1 ? 's' : ''}`;
        } else if (diffDays < 0) {
          diasRestante = `${Math.abs(diffDays)} dia${Math.abs(diffDays) !== 1 ? 's' : ''} atrasado`;
        } else {
          diasRestante = 'Hoje';
        }
      }
      
      const fmtData = (v) => (formatDate(v) || '').split(' ')[0] || '-';
      return `
        <tr>
          <td data-label="Status"><span class="status-pill ${statusClass}">${statusLabel}</span></td>
          <td data-label="Fornecedor">${boleto.fornecedor || '-'}</td>
          <td data-label="Vencimento">${fmtData(boleto.data_vencimento)}</td>
          <td data-label="Dias Restante">${diasRestante}</td>
          <td data-label="Valor">${formatCurrencyBRL(boleto.valor || 0)}</td>
          <td data-label="Data Pagamento">${boleto.data_pagamento ? fmtData(boleto.data_pagamento) : '-'}</td>
          <td data-label="Valor Pago">${boleto.valor_pago ? formatCurrencyBRL(boleto.valor_pago) : '-'}</td>
          <td data-label="Juros/Multa">${formatCurrencyBRL(valorJuros)}</td>
          <td data-label="Anexo" style="text-align: center;">${anexoIcon}</td>
          <td data-label="Ações">
            ${boleto.status !== 'PAGO' ? `<button class="btn-icon" title="Editar" data-id="${boleto.id}">✏️</button>` : ''}
            <button class="btn-icon" title="Detalhes" data-id="${boleto.id}">👁️</button>
            ${boleto.status !== 'PAGO' ? `<button class="btn-icon btn-icon--primary" title="Pagar" data-id="${boleto.id}">💳</button>` : ''}
            <button class="btn-icon btn-icon--danger btn-deletar-boleto" title="Excluir" data-id="${boleto.id}" style="color:#c62828;">🗑️</button>
          </td>
        </tr>
      `;
    }).join('');
    
    tbody.innerHTML = html;

    // Listener direto para Editar (evita falha no clique)
    tbody.querySelectorAll('.btn-icon[title="Editar"]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const id = btn.getAttribute('data-id');
        if (id) editarBoleto(id);
      });
    });

    // Listener para botões de deletar
    tbody.querySelectorAll('.btn-deletar-boleto').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.getAttribute('data-id');
        if (!confirm('Tem certeza que deseja excluir este boleto? Esta ação não pode ser desfeita.')) return;
        btn.disabled = true;
        btn.textContent = '...';
        try {
          await fetchJSON(`/boletos/${id}`, { method: 'DELETE' });
          showToast('Boleto excluído com sucesso.', 'success');
          const mesAno = dom.boletosMesAnoFiltro?.value || '';
          const unidadeId = dom.boletosUnidadeFiltro?.value || '';
          const status = dom.boletosStatusFiltro?.value || '';
          await loadBoletos({ mes_ano: mesAno, unidade_id: unidadeId, status });
          await loadBoletosResumo(mesAno);
        } catch (e) {
          showToast('Erro ao excluir: ' + (e.message || 'Falha na operação.'), 'error');
          btn.disabled = false;
          btn.textContent = '🗑️';
        }
      });
    });

    console.log('✅ Boletos renderizados com sucesso!');
  } catch (error) {
    console.error('❌ Erro ao renderizar boletos:', error);
    tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; color: red;">Erro ao renderizar boletos</td></tr>';
  }
}

// Carrega resumo financeiro
async function loadBoletosResumo(mesAno) {
  console.log('💰 Carregando resumo financeiro para:', mesAno || 'todos os boletos');
  
  // Primeiro, verifica se os elementos existem no DOM
  const totalMesEl = document.getElementById('boletosTotalMes');
  const pagoEmDiaEl = document.getElementById('boletosPagoEmDia');
  const jurosPagosEl = document.getElementById('boletosJurosPagos');
  const atrasadosEl = document.getElementById('boletosAtrasados');
  
  console.log('🔍 Verificando elementos no DOM:');
  console.log('  boletosTotalMes:', totalMesEl ? '✅ Encontrado' : '❌ NÃO encontrado');
  console.log('  boletosPagoEmDia:', pagoEmDiaEl ? '✅ Encontrado' : '❌ NÃO encontrado');
  console.log('  boletosJurosPagos:', jurosPagosEl ? '✅ Encontrado' : '❌ NÃO encontrado');
  console.log('  boletosAtrasados:', atrasadosEl ? '✅ Encontrado' : '❌ NÃO encontrado');
  
  try {
    const params = new URLSearchParams();
    if (mesAno) params.append('mes_ano', mesAno);

    const url = `${API_URL}/boletos/resumo?${params.toString()}`;
    console.log('📤 Buscando resumo em:', url);

    const response = await fetch(url, {
      headers: {
        'Content-Type': 'application/json',
        'X-Usuario-Id': currentUser?.id || ''
      }
    });

    if (!response.ok) {
      const errorText = await response.text();
      console.error('❌ Erro ao buscar resumo:', errorText);
      throw new Error('Erro ao buscar resumo');
    }

    const resumo = await response.json();
    console.log('✅ Resumo carregado:', resumo);
    console.log('📊 Dados:');
    console.log('  Total mês:', resumo.total_mes);
    console.log('  Pago em dia:', resumo.pago_em_dia);
    console.log('  Juros pagos:', resumo.juros_pagos);
    console.log('  Boletos pagos com atraso:', resumo.boletos_pagos_com_atraso);
    console.log('  Total boletos:', resumo.total_boletos);
    
    // Atualiza cards
    if (totalMesEl) {
      totalMesEl.textContent = formatCurrencyBRL(resumo.total_mes || 0);
      console.log('💳 Total do mês atualizado:', totalMesEl.textContent);
    } else {
      console.warn('⚠️ Elemento boletosTotalMes não encontrado, não foi possível atualizar');
    }
    
    if (pagoEmDiaEl) {
      pagoEmDiaEl.textContent = formatCurrencyBRL(resumo.pago_em_dia || 0);
      console.log('✅ Pago em dia atualizado:', pagoEmDiaEl.textContent);
    } else {
      console.warn('⚠️ Elemento boletosPagoEmDia não encontrado, não foi possível atualizar');
    }
    
    if (jurosPagosEl) {
      jurosPagosEl.textContent = formatCurrencyBRL(resumo.juros_pagos || 0);
      console.log('⚠️ Juros pagos atualizado:', jurosPagosEl.textContent);
    } else {
      console.warn('⚠️ Elemento boletosJurosPagos não encontrado, não foi possível atualizar');
    }
    
    if (atrasadosEl) {
      const quantidade = parseInt(resumo.boletos_pagos_com_atraso || 0);
      atrasadosEl.textContent = quantidade;
      console.log('⚠️ Boletos pagos com atraso atualizado:', quantidade);
    } else {
      console.warn('⚠️ Elemento boletosAtrasados não encontrado, não foi possível atualizar');
    }
    
    console.log('🎉 Resumo financeiro atualizado com sucesso!');
    
  } catch (error) {
    console.error('❌ Erro ao carregar resumo:', error);
    console.error('Stack trace:', error.stack);
  }
}

// ===== Modulo Fornecedores =====
let fornecedorParaExcluir = null;

async function loadFornecedores() {
  const tbody = document.getElementById('fornecedoresTable');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Carregando...</td></tr>';
  const search = document.getElementById('fornecedorSearch')?.value?.trim() || '';
  try {
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    const data = await fetchJSON(`/fornecedores${params.toString() ? '?' + params : ''}`);
    const isAdmin = (getUser()?.perfil || '').toString().toUpperCase() === 'ADMIN';
    const rows = (data || []).map(f => {
      const cnpjCpf = formatCnpjCpfDisplay(f.cnpj || f.cpf || '');
      const acoes = [];
      acoes.push(`<button type="button" class="btn small primary" data-action="editar" data-id="${f.id}">Editar</button>`);
      if (f.ativo) {
        acoes.push(`<button type="button" class="btn small secondary" data-action="desativar" data-id="${f.id}">Inativar</button>`);
      } else {
        acoes.push(`<button type="button" class="btn small primary" data-action="ativar" data-id="${f.id}">Ativar</button>`);
      }
      if (isAdmin) {
        acoes.push(`<button type="button" class="btn small danger" data-action="excluir" data-id="${f.id}">Excluir</button>`);
      }
      return `<tr><td data-label="Nome">${escapeHtml(f.nome || '-')}</td><td data-label="CNPJ / CPF">${escapeHtml(cnpjCpf)}</td><td data-label="Email">${escapeHtml(f.email || '-')}</td><td data-label="Telefone">${escapeHtml(f.telefone || '-')}</td><td data-label="Status">${f.ativo ? 'Ativo' : 'Inativo'}</td><td data-label="Acoes" class="table-actions">${acoes.join(' ')}</td></tr>`;
    });
    tbody.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="6" style="text-align:center;color:#607d8b;">Nenhum fornecedor cadastrado.</td></tr>';
    tbody.querySelectorAll('[data-action]').forEach(btn => {
      btn.addEventListener('click', () => {
        const action = btn.dataset.action;
        const id = parseInt(btn.dataset.id, 10);
        if (action === 'editar') openFornecedorModal(id);
        else if (action === 'desativar') desativarFornecedor(id);
        else if (action === 'ativar') ativarFornecedor(id);
        else if (action === 'excluir') solicitarExclusaoFornecedor(id);
      });
    });
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#d32f2f;">Erro ao carregar.</td></tr>';
    showToast('Erro ao carregar fornecedores', 'error');
  }
}

function openFornecedorModal(id) {
  const modal = document.getElementById('fornecedorModal');
  const form = document.getElementById('fornecedorForm');
  const title = document.getElementById('fornecedorModalTitle');
  if (!modal || !form) return;
  form.reset();
  form.querySelector('[name="id"]').value = id || '';
  title.textContent = id ? 'Editar Fornecedor' : 'Novo Fornecedor';
  if (id) {
    fetchJSON(`/fornecedores/${id}`).then(res => {
      const f = res.fornecedor || res;
      form.querySelector('[name="nome"]').value = f.nome || '';
      const cnpjEl = form.querySelector('[name="cnpj"]');
      const cpfEl = form.querySelector('[name="cpf"]');
      if (cnpjEl) cnpjEl.value = formatCnpjMask(f.cnpj || '');
      if (cpfEl) cpfEl.value = formatCpfMask(f.cpf || '');
      form.querySelector('[name="email"]').value = f.email || '';
      form.querySelector('[name="telefone"]').value = f.telefone || '';
      form.querySelector('[name="endereco"]').value = f.endereco || '';
      form.querySelector('[name="observacoes"]').value = f.observacoes || '';
      form.querySelector('[name="ativo"]').value = f.ativo ? '1' : '0';
    }).catch(() => showToast('Erro ao carregar fornecedor', 'error'));
  }
  modal.style.display = 'flex';
}

function closeFornecedorModal() {
  const modal = document.getElementById('fornecedorModal');
  if (modal) modal.style.display = 'none';
}

async function saveFornecedor(e) {
  e.preventDefault();
  const form = e.target;
  const id = form.querySelector('[name="id"]').value;
  const payload = {
    nome: form.querySelector('[name="nome"]').value.trim(),
    cnpj: form.querySelector('[name="cnpj"]').value.trim() || null,
    cpf: form.querySelector('[name="cpf"]').value.trim() || null,
    email: form.querySelector('[name="email"]').value.trim() || null,
    telefone: form.querySelector('[name="telefone"]').value.trim() || null,
    endereco: form.querySelector('[name="endereco"]').value.trim() || null,
    observacoes: form.querySelector('[name="observacoes"]').value.trim() || null,
    ativo: form.querySelector('[name="ativo"]').value === '1',
  };
  if (!payload.nome) {
    showToast('Preencha o nome.', 'error');
    return;
  }
  try {
    const url = id ? `/fornecedores/${id}` : '/fornecedores';
    await fetchJSON(url, {
      method: id ? 'PUT' : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    closeFornecedorModal();
    showToast('Fornecedor salvo com sucesso.');
    loadFornecedores();
  } catch (err) {
    showToast(err?.message || 'Erro ao salvar.', 'error');
  }
}

async function desativarFornecedor(id) {
  try {
    await fetchJSON(`/fornecedores/${id}/desativar`, { method: 'PUT' });
    showToast('Fornecedor inativado.');
    loadFornecedores();
  } catch (e) {
    showToast('Erro ao inativar.', 'error');
  }
}

async function ativarFornecedor(id) {
  try {
    await fetchJSON(`/fornecedores/${id}/ativar`, { method: 'PUT' });
    showToast('Fornecedor ativado.');
    loadFornecedores();
  } catch (e) {
    showToast('Erro ao ativar.', 'error');
  }
}

async function solicitarExclusaoFornecedor(id) {
  fornecedorParaExcluir = id;
  try {
    const res = await fetchJSON(`/fornecedores/${id}/check-historico`);
    const comHistorico = res.possui_historico === true;
    const modalSimples = document.getElementById('fornecedorExcluirModal');
    const modalBackup = document.getElementById('fornecedorExcluirBackupModal');
    if (comHistorico) {
      modalSimples.style.display = 'none';
      if (modalBackup) modalBackup.style.display = 'flex';
    } else {
      if (modalBackup) modalBackup.style.display = 'none';
      const msg = document.getElementById('fornecedorExcluirMsg');
      if (msg) msg.textContent = 'Deseja realmente excluir este fornecedor?';
      if (modalSimples) modalSimples.style.display = 'flex';
    }
  } catch (e) {
    showToast('Erro ao verificar historico.', 'error');
    fornecedorParaExcluir = null;
  }
}

async function confirmarExclusaoFornecedor(comBackup) {
  const id = fornecedorParaExcluir;
  if (!id) return;
  try {
    const params = comBackup ? '?com_backup=1' : '';
    await fetchJSON(`/fornecedores/${id}${params}`, { method: 'DELETE' });
    document.getElementById('fornecedorExcluirModal').style.display = 'none';
    document.getElementById('fornecedorExcluirBackupModal').style.display = 'none';
    fornecedorParaExcluir = null;
    showToast('Fornecedor excluido.');
    loadFornecedores();
  } catch (err) {
    const msg = err?.message || (err?.error || 'Erro ao excluir.');
    showToast(typeof msg === 'string' ? msg : 'Erro ao excluir.', 'error');
  }
}

async function loadFornecedoresBackup() {
  const tbody = document.getElementById('fornecedoresBackupTable');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Carregando...</td></tr>';
  try {
    const data = await fetchJSON('/fornecedores-backup');
    const rows = (data || []).map(b => `
      <tr>
        <td data-label="ID">${b.id}</td>
        <td data-label="Nome">${escapeHtml(b.nome_fornecedor || '-')}</td>
        <td data-label="CNPJ / CPF">${escapeHtml(formatCnpjCpfDisplay(b.cnpj_cpf || ''))}</td>
        <td data-label="Data exclusao">${escapeHtml(b.data_exclusao || '-')}</td>
        <td data-label="Usuario">${escapeHtml(b.usuario_exclusao_nome || '-')}</td>
        <td data-label="Acoes" class="table-actions">
          <button type="button" class="btn small" data-action="detalhes" data-id="${b.id}">Ver detalhes</button>
          <button type="button" class="btn small" data-action="restaurar" data-id="${b.id}">Restaurar</button>
          <button type="button" class="btn small danger" data-action="excluir" data-id="${b.id}">Excluir backup</button>
        </td>
      </tr>
    `);
    tbody.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="6" style="text-align:center;color:#607d8b;">Nenhum backup de fornecedor.</td></tr>';
    tbody.querySelectorAll('[data-action]').forEach(btn => {
      btn.addEventListener('click', () => {
        const action = btn.dataset.action;
        const id = parseInt(btn.dataset.id, 10);
        if (action === 'detalhes') verDetalhesBackup(id);
        else if (action === 'restaurar') restaurarFornecedor(id);
        else if (action === 'excluir') excluirBackupFornecedor(id);
      });
    });
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#d32f2f;">Erro ao carregar (apenas ADMIN).</td></tr>';
  }
}

async function verDetalhesBackup(id) {
  const content = document.getElementById('fornecedorBackupDetalhesContent');
  const modal = document.getElementById('fornecedorBackupDetalhesModal');
  if (!content || !modal) return;
  content.innerHTML = 'Carregando...';
  modal.style.display = 'flex';
  try {
    const b = await fetchJSON(`/fornecedores-backup/${id}`);
    const d = b.dados_fornecedor || {};
    content.innerHTML = `
      <p><strong>Nome:</strong> ${escapeHtml(d.nome || '-')}</p>
      <p><strong>CNPJ:</strong> ${escapeHtml(formatCnpjCpfDisplay(d.cnpj || ''))}</p>
      <p><strong>CPF:</strong> ${escapeHtml(formatCnpjCpfDisplay(d.cpf || ''))}</p>
      <p><strong>Email:</strong> ${escapeHtml(d.email || '-')}</p>
      <p><strong>Telefone:</strong> ${escapeHtml(d.telefone || '-')}</p>
      <p><strong>Data exclusao:</strong> ${escapeHtml(b.data_backup || '-')}</p>
      <p><strong>Usuario:</strong> ${escapeHtml(b.usuario_exclusao_nome || '-')}</p>
    `;
  } catch (e) {
    content.innerHTML = '<p style="color:#d32f2f;">Erro ao carregar detalhes.</p>';
  }
}

async function restaurarFornecedor(id) {
  if (!confirm('Restaurar este fornecedor?')) return;
  try {
    await fetchJSON(`/fornecedores-backup/${id}/restaurar`, { method: 'POST' });
    document.getElementById('fornecedorBackupDetalhesModal').style.display = 'none';
    showToast('Fornecedor restaurado com sucesso.');
    loadFornecedoresBackup();
  } catch (e) {
    showToast(e?.message || 'Erro ao restaurar.', 'error');
  }
}

async function excluirBackupFornecedor(id) {
  if (!confirm('Excluir este backup definitivamente?')) return;
  try {
    await fetchJSON(`/fornecedores-backup/${id}`, { method: 'DELETE' });
    showToast('Backup excluido.');
    loadFornecedoresBackup();
  } catch (e) {
    showToast('Erro ao excluir backup.', 'error');
  }
}

// ========== LOGS E AUDITORIA ==========
let stateLogsProventoId = null;
let stateLogsProventosLista = [];
let stateLogsGeralLista = [];

/** Infere marca legível a partir do modelo (ex: "Pixel 6" -> "Google Pixel 6", "SM-S911B" -> "Samsung Galaxy S23") */
function inferirMarcaDoModelo(model) {
  if (!model || typeof model !== "string") return "";
  const m = model.trim();
  if (!m) return "";
  if (/^SM-|Samsung|Galaxy/i.test(m)) {
    const sm = m.match(/SM-([A-Z])(\d+)[A-Z]?/i);
    if (sm) {
      const ser = (sm[1] || "").toUpperCase();
      const num = parseInt((sm[2] || "").replace(/\D/g, "").slice(0, 3), 10) || 0;
      if (ser === "S" && num >= 920) return "Samsung Galaxy S24";
      if (ser === "S" && num >= 910) return "Samsung Galaxy S23";
      if (ser === "S" && num >= 900) return "Samsung Galaxy S22";
      if (ser === "S" && num >= 890) return "Samsung Galaxy S21";
      if (ser === "A" && num) return `Samsung Galaxy A${String(num).slice(0, 2)}`;
      if (ser === "M" && num) return `Samsung Galaxy M${String(num).slice(0, 2)}`;
    }
    return "Samsung " + m;
  }
  if (/Pixel/i.test(m)) return "Google " + m;
  if (/moto|motorola/i.test(m)) return "Motorola " + m;
  if (/Redmi|Mi\s|Xiaomi|POCO|Note\s*\d/i.test(m)) return "Xiaomi " + m;
  if (/OP\d|OnePlus/i.test(m)) return "OnePlus " + m;
  if (/iPhone|iPad|iPod/i.test(m)) return "Apple " + m;
  if (/ASUS|Zenfone|Z00|ROG/i.test(m)) return "Asus " + m;
  if (/LG-|V\d{2}|G\d|K\d/i.test(m)) return "LG " + m;
  if (/Nokia|X-/i.test(m)) return "Nokia " + m;
  if (/Realme|RMX/i.test(m)) return "Realme " + m;
  if (/Oppo|CPH|Reno|Find/i.test(m)) return "Oppo " + m;
  if (/Vivo|V\d{4}|Y\d{2}/i.test(m)) return "Vivo " + m;
  return m;
}

/** Extrai modelo do dispositivo do user_agent (ex: Android "SM-S911B" ou "moto g stylus") */
function extrairModeloDispositivoUA(ua) {
  if (!ua || typeof ua !== "string") return "";
  const s = ua;
  const androidModel = s.match(/Android\s+[\d.]+;\s*([^);]+)\)/);
  if (androidModel) {
    const raw = androidModel[1].trim();
    if (raw === "K" || raw.length <= 2) return ""; // Chrome reduced UA
    return raw;
  }
  const iphoneModel = s.match(/iPhone[,\s]+([^;)]+)/);
  if (iphoneModel) return "iPhone " + iphoneModel[1].trim();
  return "";
}

/** Converte user_agent em texto legível: "Celular Samsung S23", "Computador Windows", etc. */
function parseUserAgentLegivel(ua, comEmoji = false, dadosExtras = null) {
  const extras = typeof dadosExtras === "string" ? (() => { try { return JSON.parse(dadosExtras); } catch { return null; } })() : dadosExtras;
  const deviceModel = extras?.device_model;
  if (deviceModel && typeof deviceModel === "string" && deviceModel.trim()) {
    const marcaModelo = inferirMarcaDoModelo(deviceModel);
    if (marcaModelo) {
      const txt = "Celular " + marcaModelo;
      return comEmoji ? "📱 " + txt : txt;
    }
  }
  if (!ua || typeof ua !== "string") return "-";
  const s = ua;
  const isMobile = /\bMobile\b/i.test(s) && !/iPad/i.test(s);
  const isTablet = /iPad|Tablet|PlayBook|Silk/i.test(s);
  if (/iPhone/i.test(s)) return comEmoji ? "📱 Celular iPhone" : "Celular iPhone";
  if (/iPad/i.test(s)) return comEmoji ? "📱 Tablet iPad" : "Tablet iPad";
  if (/iPod/i.test(s)) return comEmoji ? "📱 Celular iPod" : "Celular iPod";
  if (/SamsungBrowser|SM-[A-Z0-9-]+/i.test(s) || /Android.*Samsung/i.test(s)) {
    const m = s.match(/SM-([A-Z])(\d+)[A-Z]?/i);
    let modelo = "Samsung";
    if (m) {
      const ser = (m[1] || "").toUpperCase();
      const num = parseInt((m[2] || "").replace(/\D/g, "").slice(0, 3), 10) || 0;
      if (ser === "S" && num >= 920) modelo = "Samsung Galaxy S24";
      else if (ser === "S" && num >= 910) modelo = "Samsung Galaxy S23";
      else if (ser === "S" && num >= 900) modelo = "Samsung Galaxy S22";
      else if (ser === "S" && num >= 890) modelo = "Samsung Galaxy S21";
      else if (ser === "A" && num) modelo = `Samsung Galaxy A${String(num).slice(0, 2)}`;
      else if (ser === "M" && num) modelo = `Samsung Galaxy M${String(num).slice(0, 2)}`;
      else if (ser === "G" && num >= 990) modelo = "Samsung Galaxy S21";
    }
    const txt = isMobile ? `Celular ${modelo}` : `Tablet ${modelo}`;
    return comEmoji ? `📱 ${txt}` : txt;
  }
  if (/Android/i.test(s)) {
    const modeloExtraido = extrairModeloDispositivoUA(ua);
    let modelo = "Celular Android";
    if (modeloExtraido) {
      if (/moto|motorola/i.test(modeloExtraido)) modelo = `Celular Motorola ${modeloExtraido.replace(/moto\s*/i, "").trim()}`;
      else if (/Pixel/i.test(modeloExtraido)) modelo = `Celular ${modeloExtraido}`;
      else if (/Redmi|Mi\s|Xiaomi|POCO/i.test(modeloExtraido)) modelo = `Celular Xiaomi ${modeloExtraido}`;
      else if (/OP\d|OnePlus/i.test(modeloExtraido)) modelo = `Celular OnePlus ${modeloExtraido}`;
      else if (modeloExtraido.length <= 25) modelo = `Celular ${modeloExtraido}`;
    }
    return comEmoji ? `📱 ${modelo}` : modelo;
  }
  if (/Windows Phone|IEMobile/i.test(s)) return comEmoji ? "📱 Celular Windows" : "Celular Windows";
  if (/BlackBerry|BB10/i.test(s)) return comEmoji ? "📱 Celular BlackBerry" : "Celular BlackBerry";
  if (isTablet) return comEmoji ? "📱 Tablet" : "Tablet";
  if (isMobile) return comEmoji ? "📱 Celular" : "Celular";
  if (/Windows NT 10/i.test(s)) return comEmoji ? "💻 Computador Windows" : "Computador Windows";
  if (/Windows NT 11|Windows 11/i.test(s)) return comEmoji ? "💻 Computador Windows 11" : "Computador Windows 11";
  if (/Windows/i.test(s)) return comEmoji ? "💻 Computador Windows" : "Computador Windows";
  if (/Macintosh|Mac OS X/i.test(s)) return comEmoji ? "💻 Computador Mac" : "Computador Mac";
  if (/Linux/i.test(s) && !/Android/i.test(s)) return comEmoji ? "💻 Computador Linux" : "Computador Linux";
  if (/CrOS/i.test(s)) return comEmoji ? "💻 Computador Chrome OS" : "Computador Chrome OS";
  const browser = /Edg\//i.test(s) ? "Edge" : /Chrome\//i.test(s) ? "Chrome" : /Firefox\//i.test(s) ? "Firefox" : /Safari\//i.test(s) ? "Safari" : "";
  const txt = browser ? `Computador (${browser})` : "Computador";
  return comEmoji ? `💻 ${txt}` : txt;
}

/** Extrai dados detalhados do user_agent para auditoria/perícia */
function parseUserAgentDetalhado(ua, dadosExtras = null) {
  const extras = typeof dadosExtras === "string" ? (() => { try { return JSON.parse(dadosExtras); } catch { return null; } })() : dadosExtras;
  const deviceModel = extras?.device_model;
  let dispositivo = "-";
  let dispositivoModelo = "";
  if (deviceModel && typeof deviceModel === "string" && deviceModel.trim()) {
    const marcaModelo = inferirMarcaDoModelo(deviceModel);
    if (marcaModelo) {
      dispositivo = "Celular " + marcaModelo;
      dispositivoModelo = deviceModel;
    }
  }
  if (dispositivo === "-" && (!ua || typeof ua !== "string")) return { dispositivo: "-", navegador: "-", sistemaOperacional: "-", dispositivoModelo: "-", userAgentCompleto: "-" };
  if (dispositivo === "-") dispositivo = parseUserAgentLegivel(ua, false);
  const s = ua || "";
  let navegador = "-";
  let versaoNavegador = "";
  let sistemaOperacional = "-";
  let versaoOS = "";

  // Navegador e versão
  const edgeM = s.match(/Edg\/([\d.]+)/i);
  const chromeM = s.match(/Chrome\/([\d.]+)/i);
  const firefoxM = s.match(/Firefox\/([\d.]+)/i);
  const safariM = s.match(/Version\/([\d.]+).*Safari/i) || s.match(/Safari\/([\d.]+)/i);
  const operaM = s.match(/OPR\/([\d.]+)/i);
  const samsungBrowserM = s.match(/SamsungBrowser\/([\d.]+)/i);
  if (edgeM) { navegador = "Microsoft Edge"; versaoNavegador = edgeM[1]; }
  else if (operaM) { navegador = "Opera"; versaoNavegador = operaM[1]; }
  else if (samsungBrowserM) { navegador = "Samsung Internet"; versaoNavegador = samsungBrowserM[1]; }
  else if (chromeM) { navegador = "Chrome"; versaoNavegador = chromeM[1]; }
  else if (firefoxM) { navegador = "Firefox"; versaoNavegador = firefoxM[1]; }
  else if (safariM) { navegador = "Safari"; versaoNavegador = safariM[1] || ""; }

  // Sistema operacional
  const win11 = s.match(/Windows NT 10\.0; Win64; x64/) && /Chrome|Edge/.test(s);
  const winM = s.match(/Windows NT ([\d.]+)/);
  const macM = s.match(/Mac OS X ([\d_]+)/);
  const androidM = s.match(/Android ([\d.]+)/);
  const iphoneOSM = s.match(/CPU iPhone OS ([\d_]+) like/);
  const linuxM = /Linux/.test(s) && !/Android/.test(s);
  if (win11 || /Windows 11/i.test(s)) { sistemaOperacional = "Windows 11"; }
  else if (winM) { sistemaOperacional = "Windows"; versaoOS = (winM[1] || "").replace(/_/g, "."); }
  else if (macM) { sistemaOperacional = "macOS"; versaoOS = (macM[1] || "").replace(/_/g, "."); }
  else if (androidM) { sistemaOperacional = "Android"; versaoOS = androidM[1] || ""; }
  else if (iphoneOSM) { sistemaOperacional = "iOS"; versaoOS = (iphoneOSM[1] || "").replace(/_/g, "."); }
  else if (/iPad/.test(s)) { sistemaOperacional = "iPadOS"; }
  else if (/CrOS/.test(s)) { sistemaOperacional = "Chrome OS"; }
  else if (linuxM) { sistemaOperacional = "Linux"; }

  // Modelo do dispositivo: só extrair do UA se não veio de dados_extras (Client Hints)
  if (!dispositivoModelo && s) {
    const modeloM = s.match(/SM-[A-Z0-9-]+/i) || s.match(/iPhone\d+,\d+/i);
    if (modeloM) dispositivoModelo = modeloM[0];
    else {
      const extraido = extrairModeloDispositivoUA(ua);
      if (extraido) dispositivoModelo = extraido;
    }
  }

  return {
    dispositivo,
    navegador: versaoNavegador ? `${navegador} ${versaoNavegador}` : navegador,
    sistemaOperacional: versaoOS ? `${sistemaOperacional} ${versaoOS}` : sistemaOperacional,
    dispositivoModelo: dispositivoModelo || "-",
    userAgentCompleto: s
  };
}

async function loadLogs() {
  const tabGeral = document.getElementById("logsTabGeral");
  const tabProventos = document.getElementById("logsTabProventos");
  const painelGeral = document.getElementById("logsPainelGeral");
  const painelProventos = document.getElementById("logsPainelProventos");
  if (tabGeral?.classList.contains("primary")) {
    await loadLogsGeral();
  } else {
    await loadLogsProventosSelect();
  }
}

async function loadLogsGeral() {
  const tbody = document.getElementById("logsGeralTable");
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Carregando...</td></tr>';
  try {
    const params = new URLSearchParams();
    const di = document.getElementById("logsFiltroDataInicio")?.value;
    const df = document.getElementById("logsFiltroDataFim")?.value;
    const acao = document.getElementById("logsFiltroAcao")?.value;
    if (di) params.append("data_inicio", di);
    if (df) params.append("data_fim", df);
    if (acao) params.append("acao", acao);
    const url = params.toString() ? `/audit-logs?${params}` : "/audit-logs";
    const lista = await fetchJSON(url);
    const esc = s => (s == null || s === undefined ? "-" : String(s).replace(/</g, "&lt;"));
    if (!Array.isArray(lista) || lista.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#607d8b;">Nenhum registro.</td></tr>';
      return;
    }
    stateLogsGeralLista = lista;
    const rows = lista.map((l, i) => {
      const dt = l.created_at ? new Date(l.created_at).toLocaleString("pt-BR") : "-";
      return `<tr>
        <td data-label="Data/Hora">${esc(dt)}</td>
        <td data-label="Usuário">${esc(l.usuario_nome || l.usuario_email || "-")}</td>
        <td data-label="Ação">${esc(l.acao)}</td>
        <td data-label="Recurso">${esc(l.recurso)}</td>
        <td data-label="Descrição">${esc(l.descricao)}</td>
        <td data-label="IP">${esc(l.ip)}</td>
        <td data-label="Dispositivo" class="log-dispositivo-clicavel" data-log-index="${i}" data-log-origin="geral" title="Clique para ver detalhes da auditoria">${esc(parseUserAgentLegivel(l.user_agent, true, l.dados_extras))}</td>
      </tr>`;
    });
    tbody.innerHTML = rows.join("");
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#d32f2f;">Erro ao carregar (apenas ADMIN/GERENTE).</td></tr>';
  }
}

async function loadLogsProventosSelect() {
  const sel = document.getElementById("logsProventoSelect");
  if (!sel) return;
  try {
    const perfil = (currentUser?.perfil || "").toString().trim().toUpperCase();
    const podeCriar = ["ADMIN","GERENTE","FINANCEIRO","ASSISTENTE_ADMINISTRATIVO"].includes(perfil);
    const url = podeCriar ? "/proventos" : "/proventos/meus";
    const proventos = await fetchJSON(url);
    sel.innerHTML = '<option value="">Selecione um provento</option>' +
      (Array.isArray(proventos) ? proventos : []).map(p =>
        `<option value="${p.id}">#${p.id} - ${(p.funcionario_nome || "").replace(/</g,"&lt;")} - R$ ${(Number(p.valor)||0).toLocaleString("pt-BR",{minimumFractionDigits:2})}</option>`
      ).join("");
    stateLogsProventoId = null;
  } catch (e) {
    sel.innerHTML = '<option value="">Erro ao carregar proventos</option>';
  }
}

async function loadLogsProvento(id) {
  const tbody = document.getElementById("logsProventosTable");
  if (!tbody) return;
  if (!id) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#607d8b;">Selecione um provento.</td></tr>';
    return;
  }
  tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Carregando...</td></tr>';
  try {
    const lista = await fetchJSON(`/proventos/${id}/logs`);
    const esc = s => (s == null || s === undefined ? "-" : String(s).replace(/</g, "&lt;"));
    if (!Array.isArray(lista) || lista.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#607d8b;">Nenhum log para este provento.</td></tr>';
      return;
    }
    stateLogsProventosLista = lista;
    const rows = lista.map((l, i) => {
      const dt = l.created_at ? new Date(l.created_at).toLocaleString("pt-BR") : "-";
      const status = l.status_anterior && l.status_novo ? `${esc(l.status_anterior)} → ${esc(l.status_novo)}` : "-";
      return `<tr>
        <td data-label="Data/Hora">${esc(dt)}</td>
        <td data-label="Usuário">${esc(l.usuario_nome || "-")}</td>
        <td data-label="Ação">${esc(l.acao)}</td>
        <td data-label="Status">${status}</td>
        <td data-label="Descrição">${esc(l.descricao)}</td>
        <td data-label="IP">${esc(l.ip)}</td>
        <td data-label="Dispositivo" class="log-dispositivo-clicavel" data-log-index="${i}" data-log-origin="proventos" title="Clique para ver detalhes da auditoria">${esc(parseUserAgentLegivel(l.user_agent, true, l.dados_extras))}</td>
      </tr>`;
    });
    tbody.innerHTML = rows.join("");
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#d32f2f;">Erro ao carregar logs.</td></tr>';
  }
}

async function exportarLogsPericia(id) {
  if (!id) {
    showToast("Selecione um provento primeiro.", "warning");
    return;
  }
  try {
    const d = await fetchJSON(`/proventos/${id}/export-pericia`);
    if (d && d.error) throw new Error(d.error);
    const blob = new Blob([JSON.stringify(d || {}, null, 2)], { type: "application/json" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = `provento-${id}-pericia-${new Date().toISOString().slice(0,19).replace(/[-:T]/g,"")}.json`;
    a.click();
    URL.revokeObjectURL(a.href);
    showToast("Backup exportado para perícia.");
  } catch (e) {
    showToast(e?.message || "Erro ao exportar.", "error");
  }
}

function setupLogsModule() {
  const tabGeral = document.getElementById("logsTabGeral");
  const tabProventos = document.getElementById("logsTabProventos");
  const painelGeral = document.getElementById("logsPainelGeral");
  const painelProventos = document.getElementById("logsPainelProventos");
  tabGeral?.addEventListener("click", () => {
    tabGeral.classList.add("primary");
    tabProventos?.classList.remove("primary");
    painelGeral?.style.removeProperty("display");
    painelProventos?.style.setProperty("display", "none");
    loadLogsGeral();
  });
  tabProventos?.addEventListener("click", () => {
    tabProventos.classList.add("primary");
    tabGeral?.classList.remove("primary");
    painelProventos?.style.removeProperty("display");
    painelGeral?.style.setProperty("display", "none");
    loadLogsProventosSelect();
  });
  document.getElementById("logsFiltrarGeral")?.addEventListener("click", () => loadLogsGeral());
  document.getElementById("logsCarregarProvento")?.addEventListener("click", async () => {
    const id = document.getElementById("logsProventoSelect")?.value;
    stateLogsProventoId = id ? parseInt(id, 10) : null;
    await loadLogsProvento(stateLogsProventoId);
  });
  document.getElementById("logsExportarPericia")?.addEventListener("click", () => {
    const id = stateLogsProventoId || document.getElementById("logsProventoSelect")?.value;
    exportarLogsPericia(id ? parseInt(id, 10) : null);
  });

  document.getElementById("closeDispositivoDetalhes")?.addEventListener("click", () => toggleModal(document.getElementById("dispositivoDetalhesModal"), false));
  document.getElementById("closeDispositivoDetalhesBtn")?.addEventListener("click", () => toggleModal(document.getElementById("dispositivoDetalhesModal"), false));

  document.getElementById("logsSection")?.addEventListener("click", (e) => {
    const cell = e.target.closest(".log-dispositivo-clicavel");
    if (!cell) return;
    e.preventDefault();
    const idx = parseInt(cell.dataset.logIndex, 10);
    const origin = cell.dataset.logOrigin;
    const lista = origin === "proventos" ? stateLogsProventosLista : stateLogsGeralLista;
    const log = Array.isArray(lista) && lista[idx] ? lista[idx] : null;
    if (!log) return;
    const det = parseUserAgentDetalhado(log.user_agent, log.dados_extras);
    const esc = s => (s == null || s === undefined ? "-" : String(s).replace(/</g, "&lt;"));
    const dt = log.created_at ? new Date(log.created_at).toLocaleString("pt-BR") : "-";
    const usuarioOuFunc = log.usuario_nome || log.usuario_email || log.funcionario_nome || "-";
    const telefone = log.funcionario_whatsapp || "";
    const operadora = "Não disponível (não enviada pelo navegador)";
    const content = document.getElementById("dispositivoDetalhesContent");
    if (content) {
      content.innerHTML = `
        <div style="display:grid;gap:0.75rem;">
          <div><strong>Data/Hora:</strong> ${esc(dt)}</div>
          <div><strong>Usuário:</strong> ${esc(usuarioOuFunc)}</div>
          <div><strong>IP:</strong> ${esc(log.ip)}</div>
          <div><strong>Ação:</strong> ${esc(log.acao)}</div>
          <hr style="border:none;border-top:1px solid #ddd;margin:0.5rem 0;" />
          <div><strong>Dispositivo / Modelo:</strong> ${esc(det.dispositivo)}${det.dispositivoModelo && det.dispositivoModelo !== "-" ? " (" + esc(det.dispositivoModelo) + ")" : ""}</div>
          <div><strong>Navegador:</strong> ${esc(det.navegador)}</div>
          <div><strong>Sistema operacional:</strong> ${esc(det.sistemaOperacional)}</div>
          <div><strong>Telefone (WhatsApp):</strong> ${telefone ? esc(telefone) : "-"}</div>
          <div><strong>Operadora:</strong> ${operadora}</div>
          <hr style="border:none;border-top:1px solid #ddd;margin:0.5rem 0;" />
          <div><strong>User-Agent completo:</strong></div>
          <pre style="background:#f5f5f5;padding:0.75rem;font-size:0.8rem;overflow-x:auto;max-height:180px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;">${esc(det.userAgentCompleto)}</pre>
        </div>`;
      toggleModal(document.getElementById("dispositivoDetalhesModal"), true);
    }
  });
}

function setupFornecedoresModule() {
  const cnpjInput = document.querySelector('#fornecedorForm [name="cnpj"]');
  const cpfInput = document.querySelector('#fornecedorForm [name="cpf"]');
  if (cnpjInput) attachCnpjMask(cnpjInput);
  if (cpfInput) attachCpfMask(cpfInput);

  document.getElementById('openFornecedor')?.addEventListener('click', () => openFornecedorModal());
  document.getElementById('closeFornecedor')?.addEventListener('click', closeFornecedorModal);
  document.getElementById('cancelFornecedor')?.addEventListener('click', closeFornecedorModal);
  document.getElementById('fornecedorForm')?.addEventListener('submit', saveFornecedor);
  document.getElementById('fornecedorSearch')?.addEventListener('input', () => loadFornecedores());
  document.getElementById('fornecedorSearch')?.addEventListener('keyup', (e) => { if (e.key === 'Enter') loadFornecedores(); });

  document.getElementById('cancelFornecedorExcluir')?.addEventListener('click', () => { document.getElementById('fornecedorExcluirModal').style.display = 'none'; fornecedorParaExcluir = null; });
  document.getElementById('closeFornecedorExcluir')?.addEventListener('click', () => { document.getElementById('fornecedorExcluirModal').style.display = 'none'; fornecedorParaExcluir = null; });
  document.getElementById('confirmFornecedorExcluir')?.addEventListener('click', () => confirmarExclusaoFornecedor(false));

  document.getElementById('cancelFornecedorExcluirBackup')?.addEventListener('click', () => { document.getElementById('fornecedorExcluirBackupModal').style.display = 'none'; fornecedorParaExcluir = null; });
  document.getElementById('closeFornecedorExcluirBackup')?.addEventListener('click', () => { document.getElementById('fornecedorExcluirBackupModal').style.display = 'none'; fornecedorParaExcluir = null; });
  document.getElementById('confirmFornecedorExcluirBackup')?.addEventListener('click', () => confirmarExclusaoFornecedor(true));

  document.getElementById('closeFornecedorBackupDetalhes')?.addEventListener('click', () => { document.getElementById('fornecedorBackupDetalhesModal').style.display = 'none'; });
  document.getElementById('fecharFornecedorBackupDetalhes')?.addEventListener('click', () => { document.getElementById('fornecedorBackupDetalhesModal').style.display = 'none'; });
}

// ========== RESERVAS DE MESAS ==========
function formatTelefoneParaWhatsApp(telefone) {
  if (!telefone || typeof telefone !== 'string') return null;
  var dig = telefone.replace(/\D/g, '');
  if (dig.length < 10) return null;
  if (dig.substring(0, 2) === '55' && dig.length >= 12) return dig;
  if (dig.length === 10 || dig.length === 11) return '55' + dig;
  return '55' + dig;
}

function formatDataBrasil(val) {
  if (!val) return '';
  var s = (val.date ? val.date : val).toString().slice(0, 10);
  var m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
  return m ? m[3] + '/' + m[2] + '/' + m[1] : s;
}

function getMensagemReservaWhatsApp(r) {
  var mesaNome = (r.mesa && (r.mesa.nome_mesa || r.mesa.numero_mesa)) || 'Mesa ' + (r.mesa_id || '');
  var dataStr = formatDataBrasil(r.data_reserva);
  var horaStr = formatHora(r.hora_reserva);
  var criadoPor = (r.usuario && r.usuario.nome) ? r.usuario.nome : '';
  var unidadeNome = (r.unidade && r.unidade.nome) ? r.unidade.nome : '';
  var unidadeEndereco = (r.unidade && r.unidade.endereco) ? r.unidade.endereco.trim() : '';
  var pad = function(lbl) { return (lbl + ':').padEnd(13, ' '); };
  var linhas = [];
  linhas.push('Olá ' + (r.nome_cliente || '') + '! Sua reserva foi confirmada:');
  linhas.push('');
  if (unidadeNome) {
    linhas.push('📍 ' + pad('Local') + unidadeNome);
    if (unidadeEndereco) linhas.push('   ' + unidadeEndereco);
    linhas.push('');
  }
  linhas.push('📅 ' + pad('Data') + dataStr);
  linhas.push('🕐 ' + pad('Horário') + horaStr);
  linhas.push('🪑 ' + pad('Mesa') + mesaNome);
  linhas.push('👥 ' + pad('Pessoas') + String(r.qtd_pessoas || '-'));
  if (criadoPor) linhas.push('👤 ' + pad('Atendimento') + criadoPor);
  linhas.push('');
  linhas.push('Aguardamos você!');
  return linhas.join('\n');
}

function abrirWhatsAppReserva(r) {
  if (!r) { showToast('Dados da reserva não encontrados.', 'warning'); return; }
  var tel = formatTelefoneParaWhatsApp(r.telefone_cliente);
  if (!tel) {
    showToast('Telefone inválido ou não informado. Cadastre o telefone do cliente.', 'warning');
    return;
  }
  function abrirWhatsAppUrl(tel, msgEnc) {
    var url = 'https://wa.me/' + tel + '?text=' + msgEnc;
    var a = document.createElement('a');
    a.href = url;
    a.target = '_blank';
    a.rel = 'noopener noreferrer';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }
  if (!r.unidade && r.unidade_id) {
    fetchJSON('/unidades/' + r.unidade_id).then(function(u) {
      r.unidade = u;
      abrirWhatsAppUrl(tel, encodeURIComponent(getMensagemReservaWhatsApp(r)));
    }).catch(function() {
      abrirWhatsAppUrl(tel, encodeURIComponent(getMensagemReservaWhatsApp(r)));
    });
    return;
  }
  abrirWhatsAppUrl(tel, encodeURIComponent(getMensagemReservaWhatsApp(r)));
}

function formatHora(str) {
  if (!str) return '-';
  const s = String(str);
  const m = s.match(/^(\d{1,2}):(\d{2})/);
  return m ? m[1].padStart(2,'0') + ':' + m[2] : s.substring(0, 5);
}

function formatDataReserva(val) {
  if (!val) return '';
  if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}/.test(val)) return val.slice(0, 10);
  if (val && val.date) return String(val.date).slice(0, 10);
  return String(val).slice(0, 10);
}

var _reservasMesasCache = { mesas: [], reservas: [], unidadeId: '' };

async function loadHistoricoReservas() {
  var selUnidade = document.getElementById('historicoUnidadeFiltro');
  var unidadeId = (selUnidade && selUnidade.value) || '';
  var dataInicio = document.getElementById('historicoDataInicio') && document.getElementById('historicoDataInicio').value;
  var dataFim = document.getElementById('historicoDataFim') && document.getElementById('historicoDataFim').value;
  var status = document.getElementById('historicoStatusFiltro') && document.getElementById('historicoStatusFiltro').value;
  var tbody = document.getElementById('historicoReservasTableBody');
  if (!tbody) return;
  if (!unidadeId) {
    tbody.innerHTML = '<tr><td colspan="8" class="reservas-empty">Selecione uma unidade e clique em Atualizar.</td></tr>';
    return;
  }
  var params = 'unidade_id=' + unidadeId;
  if (dataInicio) params += '&data_inicio=' + dataInicio;
  if (dataFim) params += '&data_fim=' + dataFim;
  if (status) params += '&status=' + encodeURIComponent(status);
  try {
    var lista = await fetchJSON('/reservas-mesas/historico?' + params);
    if (!lista || lista.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" class="reservas-empty">Nenhuma reserva encontrada no período.</td></tr>';
      return;
    }
    tbody.innerHTML = lista.map(function(r) {
      var dataStr = formatDataBrasil(r.data_reserva);
      var horaStr = formatHora(r.hora_reserva);
      var mesaNome = (r.mesa && (r.mesa.nome_mesa || r.mesa.numero_mesa)) || '-';
      var statusClass = (r.status || '').replace(/_/g, '-');
      var btnWhatsApp = r.telefone_cliente ? '<button class="btn-icon" title="WhatsApp" data-id="' + r.id + '" data-action="whatsapp-historico">📱</button> ' : '';
      return '<tr>' +
        '<td data-label="Data">' + escapeHtml(dataStr) + '</td>' +
        '<td data-label="Horário">' + escapeHtml(horaStr) + '</td>' +
        '<td data-label="Cliente">' + escapeHtml(r.nome_cliente || '-') + '</td>' +
        '<td data-label="WhatsApp">' + escapeHtml(r.telefone_cliente || '-') + (btnWhatsApp ? ' ' + btnWhatsApp : '') + '</td>' +
        '<td data-label="Pessoas">' + (r.qtd_pessoas || '-') + '</td>' +
        '<td data-label="Mesa">' + escapeHtml(mesaNome) + '</td>' +
        '<td data-label="Status"><span class="status-reserva status-reserva--' + statusClass + '">' + (r.status || '').replace(/_/g, ' ') + '</span></td>' +
        '<td data-label="Total">' + (r.total_reservas_cliente || 1) + '</td></tr>';
    }).join('');
    tbody.querySelectorAll('[data-action="whatsapp-historico"]').forEach(function(btn) {
      btn.addEventListener('click', async function() {
        var id = btn.getAttribute('data-id');
        var r = await fetchJSON('/reservas-mesas/' + id);
        abrirWhatsAppReserva(r);
      });
    });
  } catch (err) {
    showToast(err.message || 'Erro ao carregar histórico.', 'error');
    tbody.innerHTML = '<tr><td colspan="8" class="reservas-empty">Erro ao carregar.</td></tr>';
  }
}

function setupHistoricoReservas() {
  document.getElementById('btnVoltarReservas') && document.getElementById('btnVoltarReservas').addEventListener('click', function() {
    navigateTo('reservaMesa');
  });
  document.getElementById('historicoAtualizar') && document.getElementById('historicoAtualizar').addEventListener('click', function() {
    loadHistoricoReservas();
  });
  document.getElementById('historicoUnidadeFiltro') && document.getElementById('historicoUnidadeFiltro').addEventListener('change', function() {
    loadHistoricoReservas();
  });
}

async function loadReservasMesas() {
  var unitSelect = document.getElementById('reservasUnidadeFiltro');
  var unidadeId = (unitSelect && unitSelect.value) || '';
  const dataFiltro = document.getElementById('reservasDataFiltro')?.value || new Date().toISOString().slice(0, 10);
  const turno = document.getElementById('reservasTurnoFiltro')?.value || '';
  const statusFiltro = document.getElementById('reservasStatusFiltro')?.value || '';

  if (!unidadeId) {
    var cardsEl = document.getElementById('reservasMesasCards');
    if (cardsEl) cardsEl.innerHTML = '<p class="reservas-empty">Selecione uma unidade acima para ver as mesas e reservas.</p>';
    var tbody = document.getElementById('reservasTableBody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="reservas-empty">Selecione uma unidade.</td></tr>';
    _reservasMesasCache = { mesas: [], reservas: [], unidadeId: '' };
    return;
  }

  document.getElementById('reservasDataFiltro').value = dataFiltro;

  try {
    const [mesas, reservas, resumo] = await Promise.all([
      fetchJSON('/mesas?unidade_id=' + unidadeId),
      fetchJSON('/reservas-mesas?unidade_id=' + unidadeId + '&data_reserva=' + dataFiltro + (turno ? '&turno=' + turno : '') + (statusFiltro ? '&status=' + statusFiltro : '')),
      fetchJSON('/reservas-mesas/resumo?unidade_id=' + unidadeId + '&data_reserva=' + dataFiltro)
    ]);

    const reservasPorMesa = {};
    (reservas || []).forEach(function(r) {
      if (['cancelada', 'no_show', 'finalizada'].indexOf(r.status) === -1) {
        const mid = r.mesa_id || (r.mesa && r.mesa.id);
        if (mid) reservasPorMesa[mid] = r;
      }
    });

    const cardsEl = document.getElementById('reservasMesasCards');
    cardsEl.innerHTML = (mesas || []).map(function(m) {
      const res = reservasPorMesa[m.id];
      var statusClass = 'livre';
      if (m.status === 'bloqueada' || !m.ativo) statusClass = 'bloqueada';
      else if (res) {
        statusClass = res.status === 'cliente_chegou' ? 'ocupada' : 'reservada';
      }
      var cliente = res ? (res.nome_cliente || '-') : '';
      var horario = res ? formatHora(res.hora_reserva) : '';
      var qtd = res ? (res.qtd_pessoas || '-') : '';
      var btnDeletar = (statusClass === 'livre') ? '<button type="button" class="mesa-card__del" title="Excluir mesa" data-mesa-del="' + m.id + '" aria-label="Excluir mesa">🗑️</button>' : '';
      return '<div class="mesa-card mesa-card--' + statusClass + '" data-mesa-id="' + m.id + '" data-reserva-id="' + (res ? res.id : '') + '">' +
        btnDeletar +
        '<div class="mesa-card__numero">' + (m.nome_mesa || m.numero_mesa || 'Mesa ' + m.id) + '</div>' +
        '<div class="mesa-card__info">Capacidade: ' + m.capacidade + (m.localizacao ? ' • ' + m.localizacao : '') + '</div>' +
        (cliente ? '<div class="mesa-card__cliente">' + cliente + (horario ? ' • ' + horario : '') + (qtd ? ' (' + qtd + ' p.)' : '') + '</div>' : '') +
        '</div>';
    }).join('') || '<p class="reservas-empty">Nenhuma mesa cadastrada.</p>';

    const tbody = document.getElementById('reservasTableBody');
    const reservasOrdenadas = (reservas || []).slice().sort(function(a, b) {
      var ha = String(a.hora_reserva || '').replace(/^(\d{1,2}):(\d{2}).*/, function(_, h, m) { return parseInt(h, 10) * 60 + parseInt(m, 10); });
      var hb = String(b.hora_reserva || '').replace(/^(\d{1,2}):(\d{2}).*/, function(_, h, m) { return parseInt(h, 10) * 60 + parseInt(m, 10); });
      return (ha || 0) - (hb || 0);
    });
    tbody.innerHTML = reservasOrdenadas.map(function(r) {
      var mesaNome = (r.mesa && (r.mesa.nome_mesa || r.mesa.numero_mesa)) || 'Mesa ' + r.mesa_id;
      var statusClass = (r.status || 'pendente').replace(/_/g, '-');
      var criadoPor = (r.usuario && r.usuario.nome) || '-';
      var podeEditar = ['cancelada', 'no_show', 'finalizada'].indexOf(r.status) === -1;
      var btnWhatsApp = (r.telefone_cliente ? '<button class="btn-icon" title="Enviar WhatsApp" data-action="whatsapp" data-id="' + r.id + '">📱</button> ' : '');
      return '<tr class="reserva-row">' +
        '<td data-label="Horário">' + formatHora(r.hora_reserva) + '</td>' +
        '<td data-label="Mesa">' + escapeHtml(mesaNome) + '</td>' +
        '<td data-label="Cliente">' + escapeHtml(r.nome_cliente) + '</td>' +
        '<td data-label="Telefone">' + escapeHtml(r.telefone_cliente || '-') + '</td>' +
        '<td data-label="Pessoas">' + (r.qtd_pessoas || '-') + '</td>' +
        '<td data-label="Status"><span class="status-reserva status-reserva--' + statusClass + '">' + (r.status || 'pendente').replace(/_/g, ' ') + '</span></td>' +
        '<td data-label="Criado por">' + escapeHtml(criadoPor) + '</td>' +
        '<td data-label="Ações" class="reserva-row-acoes">' +
        btnWhatsApp +
        '<button class="btn-icon" title="Detalhes" data-id="' + r.id + '">👁️</button> ' +
        (podeEditar ? '<button class="btn-icon" title="Editar" data-id="' + r.id + '">✏️</button> <button class="btn-icon" title="Confirmar chegada" data-action="cliente_chegou" data-id="' + r.id + '">✅</button> <button class="btn-icon" title="Cancelar" data-action="cancelar" data-id="' + r.id + '">❌</button>' : '') +
        '</td></tr>';
    }).join('') || '<tr class="reserva-row reserva-row-empty"><td colspan="8" class="reservas-empty" data-label="">Nenhuma reserva para esta data.</td></tr>';

    document.getElementById('reservasMesasLivres').textContent = resumo.mesas_livres ?? 0;
    document.getElementById('reservasMesasReservadas').textContent = resumo.mesas_reservadas ?? 0;
    document.getElementById('reservasMesasOcupadas').textContent = (resumo.mesas_ocupadas ?? 0) + (resumo.mesas_aguardando_cliente ?? 0);
    document.getElementById('reservasTotalDia').textContent = resumo.total_reservas_dia ?? 0;

    _reservasMesasCache = { mesas: mesas || [], reservas: reservas || [], unidadeId: unidadeId };
    cardsEl.querySelectorAll('.mesa-card').forEach(function(c) { c.style.cursor = 'pointer'; });
    document.querySelectorAll('#reservasTableBody .btn-icon').forEach(function(btn) {
      btn.addEventListener('click', async function(e) {
        e.stopPropagation();
        var id = btn.getAttribute('data-id');
        var action = btn.getAttribute('data-action');
        if (action === 'cancelar') {
          if (!confirm('Cancelar esta reserva?')) return;
          await fetchJSON('/reservas-mesas/' + id + '/cancelar', { method: 'POST' });
          showToast('Reserva cancelada.', 'success');
          await loadReservasMesas();
        } else if (action === 'cliente_chegou') {
          await fetchJSON('/reservas-mesas/' + id + '/status', { method: 'PATCH', body: JSON.stringify({ status: 'cliente_chegou' }) });
          showToast('Cliente marcado como chegou.', 'success');
          await loadReservasMesas();
        } else if (action === 'whatsapp') {
          var r = await fetchJSON('/reservas-mesas/' + id);
          abrirWhatsAppReserva(r);
        } else if (btn.getAttribute('title') === 'Editar') {
          await abrirEditarReserva(id);
        } else if (btn.getAttribute('title') === 'Detalhes') {
          await abrirDetalhesReserva(id);
        }
      });
    });
  } catch (err) {
    showToast(err.message || 'Erro ao carregar reservas', 'error');
    document.getElementById('reservasMesasCards').innerHTML = '<p class="reservas-empty">Erro ao carregar.</p>';
    document.getElementById('reservasTableBody').innerHTML = '<tr><td colspan="8" class="reservas-empty">Erro ao carregar.</td></tr>';
  }
}

async function popularMesasReserva(unidadeId) {
  var u = unidadeId || (document.getElementById('reservaFormUnidadeId') && document.getElementById('reservaFormUnidadeId').value) || (document.getElementById('reservaUnidadeSelect') && document.getElementById('reservaUnidadeSelect').value) || (document.getElementById('reservasUnidadeFiltro') && document.getElementById('reservasUnidadeFiltro').value);
  if (!u) return;
  var mesas = await fetchJSON('/mesas?unidade_id=' + u);
  var select = document.getElementById('reservaMesaSelect');
  if (!select) return;
  select.innerHTML = '<option value="">Selecione a mesa</option>';
  (mesas || []).filter(function(m) { return m.ativo !== false; }).forEach(function(m) {
    var opt = document.createElement('option');
    opt.value = m.id;
    opt.textContent = (m.nome_mesa || m.numero_mesa || 'Mesa ' + m.id) + ' (cap. ' + m.capacidade + ')';
    if (m.unidade_id) opt.setAttribute('data-unidade-id', String(m.unidade_id));
    select.appendChild(opt);
  });
}

async function abrirMesaLivre(mesaId, mesas, unidadeId) {
  var m = (mesas || []).find(function(x) { return x.id == mesaId; });
  if (!m || !unidadeId) { showToast('Selecione uma unidade e clique em uma mesa.', 'warning'); return; }
  document.getElementById('reservaMesaModalTitle').textContent = 'Nova Reserva';
  var form = document.getElementById('reservaMesaForm');
  form.reset();
  form.querySelector('[name="id"]').value = '';
  var hid = document.getElementById('reservaFormUnidadeId');
  if (hid) hid.value = String(unidadeId);
  form.querySelector('[name="mesa_id"]').value = mesaId;
  form.querySelector('[name="data_reserva"]').value = document.getElementById('reservasDataFiltro')?.value || new Date().toISOString().slice(0, 10);
  form.querySelector('[name="qtd_pessoas"]').value = m.capacidade || 4;
  if (document.getElementById('reservaUnidadeSelect')) document.getElementById('reservaUnidadeSelect').value = unidadeId;
  await popularMesasReserva(unidadeId);
  var sel = document.getElementById('reservaMesaSelect');
  if (sel && mesaId) sel.value = String(mesaId);
  document.getElementById('reservaMesaModal').classList.add('active');
}

async function abrirDetalhesReserva(id) {
  var modal = document.getElementById('reservaDetalhesModal');
  var content = document.getElementById('reservaDetalhesContent');
  content.innerHTML = '<p>Carregando...</p>';
  modal.classList.add('active');
  try {
    var r = await fetchJSON('/reservas-mesas/' + id);
    var mesaNome = (r.mesa && (r.mesa.nome_mesa || r.mesa.numero_mesa)) || 'Mesa ' + r.mesa_id;
    var cap = (r.mesa && r.mesa.capacidade) || 99;
    var qtd = r.qtd_pessoas || 1;
    var podeEditar = ['cancelada', 'no_show', 'finalizada'].indexOf(r.status || '') === -1;
    var acoes = '';
    if (podeEditar) {
      acoes = '<div class="reserva-detalhes-acoes" style="margin-top:1rem;padding-top:1rem;border-top:1px solid #eee;display:flex;flex-wrap:wrap;gap:0.5rem;">' +
        '<button type="button" class="btn primary" data-action="mais-pessoa" data-id="' + r.id + '">➕ Mais pessoa</button>' +
        '<button type="button" class="btn primary" data-action="menos-pessoa" data-id="' + r.id + '">➖ Menos pessoa</button>' +
        '<button type="button" class="btn neutral" data-action="juntar-mesa" data-id="' + r.id + '">🔗 Juntar mesa</button>' +
        '<button type="button" class="btn neutral" data-action="separar-mesa" data-id="' + r.id + '">✂️ Separar mesa</button>' +
        '<button type="button" class="btn danger" data-action="liberar-mesa" data-id="' + r.id + '">✅ Liberar mesa</button>' +
        '</div>';
    }
    var btnWhatsApp = r.telefone_cliente ? '<p><button type="button" class="btn primary" id="btnWhatsAppDetalhes" style="margin-top:0.5rem;">📱 Enviar confirmação no WhatsApp</button></p>' : '';
    content.innerHTML = '<div style="display:grid; gap:0.75rem;">' +
      '<p><strong>Mesa:</strong> ' + escapeHtml(mesaNome) + '</p>' +
      '<p><strong>Cliente:</strong> ' + escapeHtml(r.nome_cliente) + '</p>' +
      '<p><strong>Telefone:</strong> ' + escapeHtml(r.telefone_cliente || '-') + '</p>' +
      '<p><strong>Data:</strong> ' + formatDataBrasil(r.data_reserva) + ' | <strong>Horário:</strong> ' + formatHora(r.hora_reserva) + '</p>' +
      '<p><strong>Pessoas:</strong> <span id="detQtdPessoas">' + qtd + '</span> / ' + cap + '</p>' +
      '<p><strong>Status:</strong> ' + (r.status || '').replace(/_/g, ' ') + '</p>' +
      '<p><strong>Criado por:</strong> ' + escapeHtml((r.usuario && r.usuario.nome) || '-') + '</p>' +
      (r.observacao ? '<p><strong>Observação:</strong> ' + escapeHtml(r.observacao) + '</p>' : '') +
      btnWhatsApp +
      acoes +
      '</div>';
    document.getElementById('btnWhatsAppDetalhes') && document.getElementById('btnWhatsAppDetalhes').addEventListener('click', function() { abrirWhatsAppReserva(r); });
    content.querySelectorAll('[data-action]').forEach(function(btn) {
      btn.addEventListener('click', async function() {
        var action = btn.getAttribute('data-action');
        var resid = btn.getAttribute('data-id');
        var data = { id: r.id, qtd_pessoas: r.qtd_pessoas || 1, capacidade: (r.mesa && r.mesa.capacidade) || 99, mesa_id: r.mesa_id, unidade_id: r.unidade_id, nome_cliente: r.nome_cliente, telefone_cliente: r.telefone_cliente, data_reserva: r.data_reserva, hora_reserva: r.hora_reserva, status: r.status, observacao: r.observacao };
        if (action === 'mais-pessoa') acaoMaisPessoa(resid, data);
        else if (action === 'menos-pessoa') acaoMenosPessoa(resid, data);
        else if (action === 'juntar-mesa') acaoJuntarMesa(resid, data);
        else if (action === 'separar-mesa') acaoSepararMesa(resid, data);
        else if (action === 'liberar-mesa') acaoLiberarMesa(resid, data);
      });
    });
  } catch (e) {
    content.innerHTML = '<p>Erro ao carregar detalhes.</p>';
  }
}

async function acaoMaisPessoa(reservId, data) {
  var cap = data.capacidade || 99;
  var novaQtd = Math.min((data.qtd_pessoas || 1) + 1, cap);
  if (novaQtd === (data.qtd_pessoas || 1)) { showToast('Mesa já está no limite de capacidade.', 'warning'); return; }
  try {
    var r = await fetchJSON('/reservas-mesas/' + reservId);
    var payload = { mesa_id: r.mesa_id, nome_cliente: r.nome_cliente, telefone_cliente: r.telefone_cliente, data_reserva: formatDataReserva(r.data_reserva), hora_reserva: formatHora(r.hora_reserva), qtd_pessoas: novaQtd, status: r.status, observacao: r.observacao };
    await fetchJSON('/reservas-mesas/' + reservId, { method: 'PUT', body: JSON.stringify(payload) });
    showToast('Quantidade atualizada: ' + novaQtd + ' pessoas.', 'success');
    await loadReservasMesas();
    abrirDetalhesReserva(reservId);
  } catch (e) {
    showToast(e.message || 'Erro ao atualizar.', 'error');
  }
}

async function acaoMenosPessoa(reservId, data) {
  var novaQtd = Math.max((data.qtd_pessoas || 1) - 1, 1);
  if (novaQtd === (data.qtd_pessoas || 1)) return;
  try {
    var r = await fetchJSON('/reservas-mesas/' + reservId);
    var payload = { mesa_id: r.mesa_id, nome_cliente: r.nome_cliente, telefone_cliente: r.telefone_cliente, data_reserva: formatDataReserva(r.data_reserva), hora_reserva: formatHora(r.hora_reserva), qtd_pessoas: novaQtd, status: r.status, observacao: r.observacao };
    await fetchJSON('/reservas-mesas/' + reservId, { method: 'PUT', body: JSON.stringify(payload) });
    showToast('Quantidade atualizada: ' + novaQtd + ' pessoas.', 'success');
    await loadReservasMesas();
    abrirDetalhesReserva(reservId);
  } catch (e) {
    showToast(e.message || 'Erro ao atualizar.', 'error');
  }
}

async function acaoJuntarMesa(reservId, data) {
  var unidadeId = data.unidade_id || document.getElementById('reservasUnidadeFiltro')?.value;
  if (!unidadeId) { showToast('Selecione a unidade.', 'warning'); return; }
  var mesas = await fetchJSON('/mesas?unidade_id=' + unidadeId);
  var reservas = await fetchJSON('/reservas-mesas?unidade_id=' + unidadeId + '&data_reserva=' + formatDataReserva(data.data_reserva));
  var horaReserva = (data.hora_reserva || '').toString().slice(0, 5);
  var mesasOcupadasNesseHorario = (reservas || []).filter(function(r) {
    if (['cancelada', 'no_show', 'finalizada'].indexOf(r.status || '') !== -1) return false;
    var hr = (r.hora_reserva || '').toString().slice(0, 5);
    return hr === horaReserva;
  }).map(function(r) { return r.mesa_id || (r.mesa && r.mesa.id); });
  var qtd = data.qtd_pessoas || 1;
  var opcoes = (mesas || []).filter(function(m) {
    return m.ativo !== false && m.id != data.mesa_id && m.capacidade >= qtd && mesasOcupadasNesseHorario.indexOf(m.id) === -1;
  });
  if (!opcoes.length) { showToast('Nenhuma outra mesa disponível com capacidade suficiente no mesmo horário.', 'warning'); return; }
  var msg = 'Selecione a mesa para juntar (mover reserva):\n\n' + opcoes.map(function(m, i) { return (i + 1) + '. ' + (m.nome_mesa || m.numero_mesa) + ' (cap. ' + m.capacidade + ')'; }).join('\n');
  var escolha = prompt(msg, '1');
  if (escolha === null || escolha === '') return;
  var idx = parseInt(escolha, 10) - 1;
  if (isNaN(idx) || idx < 0 || idx >= opcoes.length) { showToast('Opção inválida.', 'warning'); return; }
  var mesaNova = opcoes[idx];
  try {
    var r = await fetchJSON('/reservas-mesas/' + reservId);
    var payload = { mesa_id: mesaNova.id, nome_cliente: r.nome_cliente, telefone_cliente: r.telefone_cliente, data_reserva: formatDataReserva(r.data_reserva), hora_reserva: formatHora(r.hora_reserva), qtd_pessoas: r.qtd_pessoas, status: r.status, observacao: r.observacao };
    await fetchJSON('/reservas-mesas/' + reservId, { method: 'PUT', body: JSON.stringify(payload) });
    showToast('Reserva movida para ' + (mesaNova.nome_mesa || mesaNova.numero_mesa) + '.', 'success');
    document.getElementById('closeReservaDetalhes').click();
    await loadReservasMesas();
  } catch (e) {
    showToast(e.message || 'Erro ao trocar mesa.', 'error');
  }
}

async function acaoSepararMesa(reservId, data) {
  var qtd = data.qtd_pessoas || 1;
  if (qtd < 2) { showToast('Precisa de pelo menos 2 pessoas para separar.', 'warning'); return; }
  var str = prompt('Quantas pessoas vão para a outra mesa?', '1');
  if (str === null || str === '') return;
  var qtdNova = parseInt(str, 10);
  if (isNaN(qtdNova) || qtdNova < 1 || qtdNova >= qtd) { showToast('Informe um valor entre 1 e ' + (qtd - 1) + '.', 'warning'); return; }
  var unidadeId = data.unidade_id || document.getElementById('reservasUnidadeFiltro')?.value;
  if (!unidadeId) { showToast('Selecione a unidade.', 'warning'); return; }
  var mesas = await fetchJSON('/mesas?unidade_id=' + unidadeId);
  var reservas = await fetchJSON('/reservas-mesas?unidade_id=' + unidadeId + '&data_reserva=' + formatDataReserva(data.data_reserva));
  var horaReserva = formatHora(data.hora_reserva) || (data.hora_reserva || '').toString().slice(0, 5);
  var mesasOcupadasHorario = (reservas || []).filter(function(rr) {
    if (['cancelada', 'no_show', 'finalizada'].indexOf(rr.status || '') !== -1) return false;
    var hr = formatHora(rr.hora_reserva) || (rr.hora_reserva || '').toString().slice(0, 5);
    return hr === horaReserva;
  }).map(function(rr) { return rr.mesa_id || (rr.mesa && rr.mesa.id); });
  var opcoes = (mesas || []).filter(function(m) { return m.ativo !== false && m.id != data.mesa_id && m.capacidade >= qtdNova && mesasOcupadasHorario.indexOf(m.id) === -1; });
  if (!opcoes.length) { opcoes = (mesas || []).filter(function(m) { return m.ativo !== false && m.id != data.mesa_id && m.capacidade >= qtdNova; }); }
  if (!opcoes.length) { showToast('Nenhuma mesa disponível.', 'warning'); return; }
  var msg = 'Selecione a mesa para as ' + qtdNova + ' pessoas:\n\n' + opcoes.map(function(m, i) { return (i + 1) + '. ' + (m.nome_mesa || m.numero_mesa) + ' (cap. ' + m.capacidade + ')'; }).join('\n');
  var escolha = prompt(msg, '1');
  if (escolha === null || escolha === '') return;
  var idx = parseInt(escolha, 10) - 1;
  if (isNaN(idx) || idx < 0 || idx >= opcoes.length) { showToast('Opção inválida.', 'warning'); return; }
  var mesaNova = opcoes[idx];
  try {
    var r = await fetchJSON('/reservas-mesas/' + reservId);
    var dataReserva = formatDataReserva(r.data_reserva);
    var horaReserva = formatHora(r.hora_reserva);
    var novoPayload = { unidade_id: unidadeId, mesa_id: mesaNova.id, nome_cliente: r.nome_cliente, telefone_cliente: r.telefone_cliente, data_reserva: dataReserva, hora_reserva: horaReserva, qtd_pessoas: qtdNova, status: r.status, observacao: (r.observacao || '') + ' [Separado]' };
    await fetchJSON('/reservas-mesas', { method: 'POST', body: JSON.stringify(novoPayload) });
    var qtdRestante = qtd - qtdNova;
    var updPayload = { mesa_id: r.mesa_id, nome_cliente: r.nome_cliente, telefone_cliente: r.telefone_cliente, data_reserva: dataReserva, hora_reserva: horaReserva, qtd_pessoas: qtdRestante, status: r.status, observacao: r.observacao };
    await fetchJSON('/reservas-mesas/' + reservId, { method: 'PUT', body: JSON.stringify(updPayload) });
    showToast('Grupo separado: ' + qtdNova + ' em nova mesa, ' + qtdRestante + ' permanecem.', 'success');
    document.getElementById('closeReservaDetalhes').click();
    await loadReservasMesas();
  } catch (e) {
    showToast(e.message || 'Erro ao separar.', 'error');
  }
}

async function acaoLiberarMesa(reservId, data) {
  if (!confirm('Liberar esta mesa e finalizar a reserva?')) return;
  try {
    await fetchJSON('/reservas-mesas/' + reservId + '/status', {
      method: 'PATCH',
      body: JSON.stringify({ status: 'finalizada' })
    });
    showToast('Mesa liberada e reserva finalizada.', 'success');
    document.getElementById('reservaDetalhesModal')?.classList.remove('active');
    await loadReservasMesas();
  } catch (e) {
    showToast(e.message || 'Erro ao liberar mesa.', 'error');
  }
}

async function abrirEditarReserva(id) {
  var r = await fetchJSON('/reservas-mesas/' + id);
  var form = document.getElementById('reservaMesaForm');
  form.querySelector('[name="id"]').value = r.id;
  var hid = document.getElementById('reservaFormUnidadeId');
  if (hid) hid.value = r.unidade_id || '';
  form.querySelector('[name="mesa_id"]').value = r.mesa_id || '';
  form.querySelector('[name="nome_cliente"]').value = r.nome_cliente || '';
  form.querySelector('[name="telefone_cliente"]').value = r.telefone_cliente || '';
  form.querySelector('[name="data_reserva"]').value = (r.data_reserva || '').toString().slice(0, 10);
  form.querySelector('[name="hora_reserva"]').value = formatHora(r.hora_reserva);
  form.querySelector('[name="qtd_pessoas"]').value = r.qtd_pessoas || 2;
  form.querySelector('[name="status"]').value = r.status || 'pendente';
  form.querySelector('[name="observacao"]').value = r.observacao || '';
  var localEl = form.querySelector('[name="local"]');
  if (localEl) localEl.value = r.local || '';
  var ocasiaoEl = form.querySelector('[name="ocasiao"]');
  if (ocasiaoEl) ocasiaoEl.value = r.ocasiao || '';
  document.getElementById('reservaMesaModalTitle').textContent = '✏️ Editar Reserva';
  await popularMesasReserva(r.unidade_id);
  document.getElementById('reservaMesaModal').classList.add('active');
}

function setupReservasMesasModule() {
  var isAdmin = function() { return currentUser && (currentUser.perfil || '').toUpperCase() === 'ADMIN'; };
  var podeGerenciarMesas = function() {
    var p = (currentUser && currentUser.perfil || '').toUpperCase();
    return p === 'ADMIN' || p === 'GERENTE' || p === 'ASSISTENTE_ADMINISTRATIVO';
  };
  var openGerenciar = document.getElementById('openGerenciarMesas');
  if (openGerenciar) openGerenciar.style.display = podeGerenciarMesas() ? '' : 'none';

  var unidadeSelect = document.getElementById('reservasUnidadeFiltro');
  var dataInput = document.getElementById('reservasDataFiltro');
  if (dataInput) dataInput.value = new Date().toISOString().slice(0, 10);

  async function popularUnidades() {
    var unidades = state.unidades && state.unidades.length ? state.unidades : await fetchJSON('/unidades');
    if (!unidadeSelect) return;
    unidadeSelect.innerHTML = '<option value="">Selecione a unidade</option>';
    (unidades || []).forEach(function(u) {
      var opt = document.createElement('option');
      opt.value = u.id;
      opt.textContent = u.nome || 'Unidade ' + u.id;
      unidadeSelect.appendChild(opt);
    });
    if (currentUser && currentUser.unidade_id && !isAdmin()) {
      unidadeSelect.value = currentUser.unidade_id;
      unidadeSelect.disabled = true;
    }
  }

  var reservaUnidadeLabel = document.getElementById('reservaUnidadeLabel');
  if (reservaUnidadeLabel) reservaUnidadeLabel.style.display = isAdmin() ? '' : 'none';

  document.getElementById('reservaUnidadeSelect') && document.getElementById('reservaUnidadeSelect').addEventListener('change', function() {
    var hid = document.getElementById('reservaFormUnidadeId');
    if (hid) hid.value = this.value;
    popularMesasReserva(this.value);
  });

  document.getElementById('btnNovaMesaReserva') && document.getElementById('btnNovaMesaReserva').addEventListener('click', async function() {
    var filtro = document.getElementById('reservasUnidadeFiltro');
    var unidadeId = (filtro && filtro.value) || (document.getElementById('reservaFormUnidadeId') && document.getElementById('reservaFormUnidadeId').value) || (document.getElementById('reservaUnidadeSelect') && document.getElementById('reservaUnidadeSelect').value);
    if (!unidadeId) { showToast('Selecione uma unidade no filtro acima primeiro.', 'warning'); return; }
    unidadeId = String(unidadeId).trim();
    var numero = prompt('Número da mesa:', '1');
    if (!numero || !numero.trim()) return;
    numero = numero.trim();
    var capStr = prompt('Quantidade de pessoas (capacidade):', '4');
    var capacidade = 4;
    if (capStr && !isNaN(parseInt(capStr, 10))) capacidade = Math.max(1, parseInt(capStr, 10));
    try {
      var resp = await fetchJSON('/mesas', {
        method: 'POST',
        body: JSON.stringify({
          unidade_id: unidadeId,
          numero_mesa: numero,
          capacidade: capacidade
        })
      });
      var mesaNova = resp.mesa || resp;
      showToast('Mesa criada.', 'success');
      await loadReservasMesas();
      await popularMesasReserva(unidadeId);
      var select = document.getElementById('reservaMesaSelect');
      if (select && mesaNova && mesaNova.id) select.value = String(mesaNova.id);
    } catch (err) {
      showToast(err.message || 'Erro ao criar mesa', 'error');
    }
  });

  popularUnidades();

  document.getElementById('openNovaReservaMesa') && document.getElementById('openNovaReservaMesa').addEventListener('click', async function() {
    var unidadeId = document.getElementById('reservasUnidadeFiltro') && document.getElementById('reservasUnidadeFiltro').value;
    if (!unidadeId) { showToast('Selecione uma unidade primeiro.', 'warning'); return; }
    var form = document.getElementById('reservaMesaForm');
    form.reset();
    form.querySelector('[name="id"]').value = '';
    var unidadeInput = document.getElementById('reservaFormUnidadeId') || form.querySelector('[name="unidade_id"]');
    if (unidadeInput) unidadeInput.value = unidadeId;
    var hid = document.getElementById('reservaFormUnidadeId');
    if (hid) hid.value = unidadeId;
    if (isAdmin()) {
      var reservaUnidadeSelect = document.getElementById('reservaUnidadeSelect');
      if (reservaUnidadeSelect) {
        reservaUnidadeSelect.innerHTML = '<option value="">Selecione</option>';
        (state.unidades || []).forEach(function(u) {
          var opt = document.createElement('option');
          opt.value = u.id;
          opt.textContent = u.nome || 'Unidade ' + u.id;
          reservaUnidadeSelect.appendChild(opt);
        });
        reservaUnidadeSelect.value = unidadeId;
      }
    }
    form.querySelector('[name="data_reserva"]').value = (document.getElementById('reservasDataFiltro') && document.getElementById('reservasDataFiltro').value) || new Date().toISOString().slice(0, 10);
    form.querySelector('[name="hora_reserva"]').value = '19:00';
    form.querySelector('[name="qtd_pessoas"]').value = 2;
    form.querySelector('[name="status"]').value = 'pendente';
    document.getElementById('reservaMesaModalTitle').textContent = '🍽 Nova Reserva';
    await popularMesasReserva(unidadeId);
    document.getElementById('reservaMesaModal').classList.add('active');
  });

  document.getElementById('closeReservaMesa') && document.getElementById('closeReservaMesa').addEventListener('click', function() { document.getElementById('reservaMesaModal').classList.remove('active'); });
  document.getElementById('cancelReservaMesa') && document.getElementById('cancelReservaMesa').addEventListener('click', function() { document.getElementById('reservaMesaModal').classList.remove('active'); });

  document.getElementById('reservaMesaForm') && document.getElementById('reservaMesaForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    var form = e.target;
    var id = form.querySelector('[name="id"]').value;
    var hid = document.getElementById('reservaFormUnidadeId');
    var filtroUnidade = document.getElementById('reservasUnidadeFiltro') && document.getElementById('reservasUnidadeFiltro').value;
    var unidadeVal = String((hid && hid.value) || filtroUnidade || '').trim();
    var mesaOpt = form.querySelector('[name="mesa_id"]');
    if (!unidadeVal && mesaOpt && mesaOpt.selectedOptions && mesaOpt.selectedOptions[0]) {
      var optUnidade = mesaOpt.selectedOptions[0].getAttribute('data-unidade-id');
      if (optUnidade) unidadeVal = String(optUnidade).trim();
    }
    if (!unidadeVal) { showToast('Selecione a unidade no filtro acima primeiro.', 'error'); return; }
    var data = {
      unidade_id: parseInt(unidadeVal, 10) || unidadeVal,
      mesa_id: form.querySelector('[name="mesa_id"]').value,
      nome_cliente: form.querySelector('[name="nome_cliente"]').value,
      telefone_cliente: form.querySelector('[name="telefone_cliente"]').value,
      data_reserva: form.querySelector('[name="data_reserva"]').value,
      hora_reserva: form.querySelector('[name="hora_reserva"]').value,
      qtd_pessoas: parseInt(form.querySelector('[name="qtd_pessoas"]').value, 10),
      status: form.querySelector('[name="status"]').value,
      observacao: form.querySelector('[name="observacao"]').value,
      local: form.querySelector('[name="local"]') && form.querySelector('[name="local"]').value,
      ocasiao: form.querySelector('[name="ocasiao"]') && form.querySelector('[name="ocasiao"]').value
    };
    if (!data.unidade_id || !data.mesa_id) { showToast('Selecione unidade e mesa.', 'error'); return; }
    try {
      if (id) {
        await fetchJSON('/reservas-mesas/' + id, { method: 'PUT', body: JSON.stringify(data) });
        showToast('Reserva atualizada.', 'success');
      } else {
        var resp = await fetchJSON('/reservas-mesas', { method: 'POST', body: JSON.stringify(data) });
        showToast('Reserva criada.', 'success');
        var reservaCriada = resp.reserva || resp;
        if (reservaCriada && reservaCriada.telefone_cliente && confirm('Reserva criada! Deseja enviar confirmação por WhatsApp para o cliente?')) {
          abrirWhatsAppReserva(reservaCriada);
        }
      }
      document.getElementById('reservaMesaModal').classList.remove('active');
      await loadReservasMesas();
    } catch (err) { showToast(err.message || 'Erro ao salvar', 'error'); }
  });

  document.getElementById('closeReservaDetalhes') && document.getElementById('closeReservaDetalhes').addEventListener('click', function() { document.getElementById('reservaDetalhesModal').classList.remove('active'); });
  document.getElementById('reservaDetalhesModal') && document.getElementById('reservaDetalhesModal').addEventListener('click', function(e) {
    if (e.target.id === 'reservaDetalhesModal') e.target.classList.remove('active');
  });

  document.getElementById('reservasUnidadeFiltro') && document.getElementById('reservasUnidadeFiltro').addEventListener('change', function() { loadReservasMesas(); });
  document.getElementById('reservasDataFiltro') && document.getElementById('reservasDataFiltro').addEventListener('change', function() { loadReservasMesas(); });
  document.getElementById('reservasTurnoFiltro') && document.getElementById('reservasTurnoFiltro').addEventListener('change', function() { loadReservasMesas(); });
  document.getElementById('reservasStatusFiltro') && document.getElementById('reservasStatusFiltro').addEventListener('change', function() { loadReservasMesas(); });
  document.getElementById('reservasAtualizar') && document.getElementById('reservasAtualizar').addEventListener('click', function() { loadReservasMesas(); });

  document.getElementById('btnHistoricoReservas') && document.getElementById('btnHistoricoReservas').addEventListener('click', async function() {
    var unidadeAtual = document.getElementById('reservasUnidadeFiltro') && document.getElementById('reservasUnidadeFiltro').value;
    navigateTo('historicoReservas');
    var uSelect = document.getElementById('historicoUnidadeFiltro');
    if (uSelect && uSelect.options.length <= 1) {
      var unidades = state.unidades && state.unidades.length ? state.unidades : await fetchJSON('/unidades').catch(function() { return []; });
      state.unidades = unidades;
      uSelect.innerHTML = '<option value="">Selecione a unidade</option>';
      (unidades || []).forEach(function(u) {
        var opt = document.createElement('option');
        opt.value = u.id;
        opt.textContent = u.nome || 'Unidade ' + u.id;
        uSelect.appendChild(opt);
      });
      if (currentUser && currentUser.unidade_id && (currentUser.perfil || '').toUpperCase() !== 'ADMIN') {
        uSelect.value = currentUser.unidade_id;
        uSelect.disabled = true;
      }
    }
    if (unidadeAtual && uSelect) uSelect.value = unidadeAtual;
    var dInicio = document.getElementById('historicoDataInicio');
    var dFim = document.getElementById('historicoDataFim');
    if (dInicio && !dInicio.value) dInicio.value = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
    if (dFim && !dFim.value) dFim.value = new Date().toISOString().slice(0, 10);
    await loadHistoricoReservas();
  });

  var cardsContainer = document.getElementById('reservasMesasCards');
  if (cardsContainer) {
    cardsContainer.addEventListener('click', function(e) {
      var btnDel = e.target.closest('.mesa-card__del');
      if (btnDel) {
        e.stopPropagation();
        var mid = btnDel.getAttribute('data-mesa-del');
        if (!mid) return;
        if (!confirm('Excluir esta mesa?')) return;
        fetchJSON('/mesas/' + mid, { method: 'DELETE' }).then(function() {
          showToast('Mesa excluída.', 'success');
          loadReservasMesas();
        }).catch(function(err) {
          showToast(err.message || 'Erro ao excluir mesa.', 'error');
        });
        return;
      }
      var card = e.target.closest('.mesa-card');
      if (!card) return;
      var rid = card.getAttribute('data-reserva-id');
      var mid = card.getAttribute('data-mesa-id');
      var cache = _reservasMesasCache || {};
      var mesas = cache.mesas || [];
      var unidadeId = cache.unidadeId || (document.getElementById('reservasUnidadeFiltro') && document.getElementById('reservasUnidadeFiltro').value) || '';
      if (rid) abrirDetalhesReserva(rid);
      else if (mid && unidadeId) abrirMesaLivre(mid, mesas, unidadeId);
      else showToast('Selecione uma unidade primeiro.', 'warning');
    });
  }

  document.getElementById('openGerenciarMesas') && document.getElementById('openGerenciarMesas').addEventListener('click', async function() {
    await loadUnidades(false);
    var select = document.getElementById('mesasModalUnidade');
    select.innerHTML = '';
    var unidadePadrao = (document.getElementById('reservasUnidadeFiltro') && document.getElementById('reservasUnidadeFiltro').value) || (currentUser && currentUser.unidade_id) || (state.unidades && state.unidades[0] && state.unidades[0].id) || '';
    var unidadesParaMostrar = state.unidades || [];
    if (!isAdmin() && currentUser && currentUser.unidade_id) {
      unidadesParaMostrar = (state.unidades || []).filter(function(u) { return u.id == currentUser.unidade_id; });
      unidadePadrao = currentUser.unidade_id;
      select.disabled = true;
    } else {
      select.disabled = false;
    }
    unidadesParaMostrar.forEach(function(u) {
      var opt = document.createElement('option');
      opt.value = u.id;
      opt.textContent = u.nome;
      select.appendChild(opt);
    });
    select.value = unidadePadrao || (unidadesParaMostrar[0] && unidadesParaMostrar[0].id) || '';
    document.getElementById('mesaFormCard').style.display = 'none';
    await carregarMesasModal();
    document.getElementById('mesasModal').classList.add('active');
  });

  document.getElementById('closeMesasModal') && document.getElementById('closeMesasModal').addEventListener('click', function() { document.getElementById('mesasModal').classList.remove('active'); });
  document.getElementById('mesasModalUnidade') && document.getElementById('mesasModalUnidade').addEventListener('change', function() { carregarMesasModal(); });

  async function carregarMesasModal() {
    var unidadeId = document.getElementById('mesasModalUnidade') && document.getElementById('mesasModalUnidade').value;
    if (!unidadeId) return;
    var mesas = await fetchJSON('/mesas?unidade_id=' + unidadeId);
    var tbody = document.getElementById('mesasModalTableBody');
    if (!tbody) return;
    tbody.innerHTML = (mesas || []).map(function(m) {
      var estaOcupada = (m.status || 'livre') === 'ocupada';
      var btnDelTitle = estaOcupada ? 'Inativar mesa (ocupada)' : 'Excluir mesa';
      return '<tr><td>' + m.numero_mesa + '</td><td>' + escapeHtml(m.nome_mesa || '-') + '</td><td>' + m.capacidade + '</td><td>' + escapeHtml(m.localizacao || '-') + '</td><td>' + (m.status || 'livre').replace(/_/g, ' ') + '</td><td>' +
        '<button class="btn-icon" title="Editar mesa" data-mesa-id="' + m.id + '">✏️</button> ' +
        '<button class="btn-icon" title="' + btnDelTitle + '" data-mesa-id="' + m.id + '" data-action="excluir">🗑️</button></td></tr>';
    }).join('') || '<tr><td colspan="6">Nenhuma mesa.</td></tr>';

    tbody.querySelectorAll('[data-mesa-id]').forEach(function(btn) {
      btn.addEventListener('click', async function() {
        var mid = btn.getAttribute('data-mesa-id');
        if (btn.getAttribute('data-action') === 'excluir') {
          var m = mesas.find(function(x) { return x.id == mid; });
          var msg = (m && (m.status || 'livre') === 'ocupada') ? 'Mesa ocupada. Será apenas inativada. Continuar?' : 'Excluir esta mesa permanentemente?';
          if (!confirm(msg)) return;
          var resp = await fetchJSON('/mesas/' + mid, { method: 'DELETE' });
          showToast((resp && resp.message) || 'Mesa removida.', 'success');
          await carregarMesasModal();
          await loadReservasMesas();
        } else {
          var m = mesas.find(function(x) { return x.id == mid; });
          if (m) {
            document.getElementById('mesaFormTitle').textContent = 'Editar Mesa';
            document.getElementById('mesaForm').querySelector('[name="id"]').value = m.id;
            document.getElementById('mesaFormUnidadeId').value = m.unidade_id;
            document.getElementById('mesaForm').querySelector('[name="numero_mesa"]').value = m.numero_mesa;
            document.getElementById('mesaForm').querySelector('[name="nome_mesa"]').value = m.nome_mesa || '';
            document.getElementById('mesaForm').querySelector('[name="capacidade"]').value = m.capacidade || 4;
            document.getElementById('mesaForm').querySelector('[name="localizacao"]').value = m.localizacao || '';
            var pj = document.getElementById('mesaForm').querySelector('[name="pode_juntar"]');
            var ps = document.getElementById('mesaForm').querySelector('[name="pode_separar"]');
            if (pj) pj.checked = !!m.pode_juntar;
            if (ps) ps.checked = !!m.pode_separar;
            document.getElementById('mesaFormCard').style.display = 'block';
          }
        }
      });
    });
  }

  document.getElementById('addMesaBtn') && document.getElementById('addMesaBtn').addEventListener('click', function() {
    document.getElementById('mesaFormTitle').textContent = 'Nova Mesa';
    document.getElementById('mesaForm').reset();
    document.getElementById('mesaForm').querySelector('[name="id"]').value = '';
    document.getElementById('mesaFormUnidadeId').value = (document.getElementById('mesasModalUnidade') && document.getElementById('mesasModalUnidade').value) || '';
    document.getElementById('mesaForm').querySelector('[name="capacidade"]').value = 4;
    document.getElementById('mesaFormCard').style.display = 'block';
  });

  document.getElementById('cancelMesaForm') && document.getElementById('cancelMesaForm').addEventListener('click', function() {
    document.getElementById('mesaFormCard').style.display = 'none';
  });

  document.getElementById('mesaForm') && document.getElementById('mesaForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    var form = e.target;
    var id = form.querySelector('[name="id"]').value;
    var payload = {
      unidade_id: form.querySelector('[name="unidade_id"]').value,
      numero_mesa: form.querySelector('[name="numero_mesa"]').value,
      nome_mesa: form.querySelector('[name="nome_mesa"]').value || null,
      capacidade: parseInt(form.querySelector('[name="capacidade"]').value, 10),
      localizacao: form.querySelector('[name="localizacao"]').value || null,
      pode_juntar: !!form.querySelector('[name="pode_juntar"]') && form.querySelector('[name="pode_juntar"]').checked,
      pode_separar: !!form.querySelector('[name="pode_separar"]') && form.querySelector('[name="pode_separar"]').checked
    };
    try {
      if (id) {
        await fetchJSON('/mesas/' + id, { method: 'PUT', body: JSON.stringify(payload) });
        showToast('Mesa atualizada.', 'success');
      } else {
        await fetchJSON('/mesas', { method: 'POST', body: JSON.stringify(payload) });
        showToast('Mesa criada.', 'success');
      }
      document.getElementById('mesaFormCard').style.display = 'none';
      await carregarMesasModal();
      await loadReservasMesas();
    } catch (err) { showToast(err.message || 'Erro ao salvar', 'error'); }
  });
}

function setupBoletosModule() {
  const openNovoBoletoBtn = document.getElementById('openNovoBoleto');
  const recarregarTabelaBoletos = document.getElementById('recarregarTabelaBoletos');
  const boletoModal = document.getElementById('boletoModal');
  const closeBoletoBtn = document.getElementById('closeBoleto');
  const cancelBoletoBtn = document.getElementById('cancelBoleto');
  const boletoForm = document.getElementById('boletoForm');
  const boletosMesAnoFiltro = document.getElementById('boletosMesAnoFiltro');
  const boletosUnidadeFiltro = document.getElementById('boletosUnidadeFiltro');
  const boletosStatusFiltro = document.getElementById('boletosStatusFiltro');
  const limparFiltrosBoletos = document.getElementById('limparFiltrosBoletos');
  const boletoAnexoInput = document.getElementById('boletoAnexoInput');
  const boletoAnexoPreview = document.getElementById('boletoAnexoPreview');
  const boletoRemoverAnexo = document.getElementById('boletoRemoverAnexo');
  const boletoRecorrente = document.getElementById('boletoRecorrente');
  const recorrenteFields = document.getElementById('recorrenteFields');
  
  // Popula filtro Mês/Ano (ano atual + ano seguinte completo)
  populateBoletosMesAnoFiltro();
  
  // Popula select de unidades no filtro
  async function carregarUnidadesFiltro() {
    if (!boletosUnidadeFiltro) return;
    
    try {
      const unidades = await fetchJSON('/unidades');
      boletosUnidadeFiltro.innerHTML = '<option value="">Todas as unidades</option>';
      
      unidades.forEach(unidade => {
        const option = document.createElement('option');
        option.value = unidade.id;
        option.textContent = unidade.nome;
        boletosUnidadeFiltro.appendChild(option);
      });
      
      console.log('✅ Unidades carregadas no filtro de boletos:', unidades.length);
    } catch (error) {
      console.error('❌ Erro ao carregar unidades no filtro:', error);
    }
  }
  
  // Carrega unidades ao iniciar
  carregarUnidadesFiltro();
  
  // Botão de atualizar na tabela
  if (recarregarTabelaBoletos) {
    recarregarTabelaBoletos.addEventListener('click', async () => {
      console.log('🔄 === ATUALIZAR TABELA CLICADO ===');
      
      try {
        recarregarTabelaBoletos.disabled = true;
        recarregarTabelaBoletos.textContent = '⏳ Atualizando...';
        
        const mesAno = boletosMesAnoFiltro?.value;
        
        if (mesAno && mesAno !== '') {
          console.log('📅 Recarregando boletos do mês:', mesAno);
          await loadBoletos({ mes_ano: mesAno });
          await loadBoletosResumo(mesAno);
        } else {
          console.log('📋 Recarregando TODOS os boletos');
          await loadBoletos({});
          await loadBoletosResumo();
        }
        
        showToast('✅ Boletos atualizados!', 'success');
        console.log('✅ Atualização concluída!');
        
      } catch (error) {
        console.error('❌ Erro ao atualizar:', error);
        showToast('Erro ao atualizar boletos', 'error');
      } finally {
        recarregarTabelaBoletos.disabled = false;
        recarregarTabelaBoletos.textContent = '🔄 Atualizar';
        console.log('🔄 === FIM ATUALIZAR ===');
      }
    });
  }

  // Controla exibição dos campos de recorrência
  if (boletoRecorrente && recorrenteFields) {
    boletoRecorrente.addEventListener('change', (e) => {
      if (e.target.checked) {
        recorrenteFields.style.display = 'block';
        document.getElementById('mesesRecorrencia').required = true;
      } else {
        recorrenteFields.style.display = 'none';
        document.getElementById('mesesRecorrencia').required = false;
        document.getElementById('mesesRecorrencia').value = '';
      }
    });
  }

  // Preview do anexo
  if (boletoAnexoInput) {
    boletoAnexoInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (file) {
        // Verifica tamanho (5MB)
        if (file.size > 5 * 1024 * 1024) {
          showToast('Arquivo muito grande! Máximo 5MB', 'error');
          boletoAnexoInput.value = '';
          return;
        }

        // Mostra preview
        const nomeEl = document.getElementById('boletoAnexoNome');
        const tamanhoEl = document.getElementById('boletoAnexoTamanho');
        if (nomeEl) nomeEl.textContent = file.name;
        if (tamanhoEl) tamanhoEl.textContent = formatFileSize(file.size);
        if (boletoAnexoPreview) boletoAnexoPreview.style.display = 'block';
      }
    });
  }

  // Remove anexo antes de enviar
  if (boletoRemoverAnexo) {
    boletoRemoverAnexo.addEventListener('click', () => {
      if (boletoAnexoInput) boletoAnexoInput.value = '';
      if (boletoAnexoPreview) boletoAnexoPreview.style.display = 'none';
    });
  }

  // Segurança: garante que "Novo Boleto" nunca herda ID/modo de edição
  const resetBoletoFormToCreateMode = () => {
    if (!boletoForm) return;
    boletoForm.dataset.mode = 'create';
    const idEl = boletoForm.querySelector('input[name="id"]');
    if (idEl) idEl.value = '';
    const pagamentoFields = document.getElementById('pagamentoFields');
    if (pagamentoFields) pagamentoFields.style.display = 'none';
    if (boletoAnexoPreview) boletoAnexoPreview.style.display = 'none';
    const recorrenteFields = document.getElementById('recorrenteFields');
    if (recorrenteFields) recorrenteFields.style.display = 'none';
    const boletoRecorrente = document.getElementById('boletoRecorrente');
    if (boletoRecorrente) boletoRecorrente.checked = false;
    const mesesRec = document.getElementById('mesesRecorrencia');
    if (mesesRec) mesesRec.value = '';
  };

  // Abre modal de novo boleto
  if (openNovoBoletoBtn) {
    openNovoBoletoBtn.addEventListener('click', async () => {
      if (boletoModal) {
        await populateBoletosUnidades();
        boletoModal.classList.add('active');
        document.getElementById('boletoModalTitle').textContent = '💰 Novo Boleto';
        if (boletoForm) boletoForm.reset();
        resetBoletoFormToCreateMode();
      }
    });
  }

  // Fecha modal
  if (closeBoletoBtn) {
    closeBoletoBtn.addEventListener('click', () => {
      if (boletoModal) boletoModal.classList.remove('active');
      resetBoletoFormToCreateMode();
    });
  }

  // Cancela modal
  if (cancelBoletoBtn) {
    cancelBoletoBtn.addEventListener('click', () => {
      if (boletoForm) boletoForm.reset();
      resetBoletoFormToCreateMode();
    });
  }

  // Fecha modal ao clicar fora
  if (boletoModal) {
    boletoModal.addEventListener('click', (e) => {
      if (e.target === boletoModal) {
        boletoModal.classList.remove('active');
        resetBoletoFormToCreateMode();
      }
    });
  }

  // Controla exibicao dos campos de pagamento
  const statusSelect = boletoForm?.querySelector('[name="status"]');
  const pagamentoFields = document.getElementById('pagamentoFields');
  
  if (statusSelect && pagamentoFields) {
    statusSelect.addEventListener('change', (e) => {
      if (e.target.value === 'PAGO') {
        pagamentoFields.style.display = 'block';
      } else {
        pagamentoFields.style.display = 'none';
      }
    });
  }

  // Função para coletar filtros ativos
  function getBoletosFilters() {
    const filtros = {};
    
    if (boletosMesAnoFiltro?.value) {
      filtros.mes_ano = boletosMesAnoFiltro.value;
    }
    
    if (boletosUnidadeFiltro?.value) {
      filtros.unidade_id = boletosUnidadeFiltro.value;
    }
    
    if (boletosStatusFiltro?.value) {
      filtros.status = boletosStatusFiltro.value;
    }
    
    return filtros;
  }
  
  // Função para aplicar filtros
  async function aplicarFiltrosBoletos() {
    try {
      const filtros = getBoletosFilters();
      console.log('🔍 Aplicando filtros:', filtros);
      
      await loadBoletos(filtros);
      await loadBoletosResumo(filtros.mes_ano);
      
      showToast('✅ Filtros aplicados', 'success');
    } catch (error) {
      console.error('❌ Erro ao filtrar boletos:', error);
      showToast('Erro ao filtrar boletos', 'error');
    }
  }
  
  // Event listeners para os filtros
  if (boletosMesAnoFiltro) {
    boletosMesAnoFiltro.addEventListener('change', aplicarFiltrosBoletos);
  }
  
  if (boletosUnidadeFiltro) {
    boletosUnidadeFiltro.addEventListener('change', aplicarFiltrosBoletos);
  }
  
  if (boletosStatusFiltro) {
    boletosStatusFiltro.addEventListener('change', aplicarFiltrosBoletos);
  }
  
  // Botão limpar filtros
  if (limparFiltrosBoletos) {
    limparFiltrosBoletos.addEventListener('click', async () => {
      console.log('🔄 Limpando filtros');
      
      try {
        // Limpa os selects
        if (boletosMesAnoFiltro) boletosMesAnoFiltro.value = '';
        if (boletosUnidadeFiltro) boletosUnidadeFiltro.value = '';
        if (boletosStatusFiltro) boletosStatusFiltro.value = '';
        
        // Carrega todos os boletos
        await loadBoletos({});
        await loadBoletosResumo();
        
        showToast('✅ Filtros limpos', 'success');
      } catch (error) {
        console.error('❌ Erro ao limpar filtros:', error);
        showToast('Erro ao limpar filtros', 'error');
      }
    });
  }

  // Submit do formulario de boleto
  if (boletoForm) {
    // Máscara para WhatsApp
    const whatsappInput = document.getElementById('whatsapp_pagador');
    if (whatsappInput) {
      whatsappInput.addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, ''); // Remove tudo que não é número
        
        // Aplica a máscara: (99) 99999-9999
        if (value.length > 11) value = value.slice(0, 11);
        
        if (value.length > 2) {
          value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
        }
        if (value.length > 10) {
          value = value.substring(0, 10) + '-' + value.substring(10);
        }
        
        e.target.value = value;
      });
    }

    boletoForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      console.log('🚀 Iniciando salvamento do boleto...');
      
      const fornecedor = boletoForm.querySelector('[name="fornecedor"]')?.value?.trim();
      const descricao = boletoForm.querySelector('[name="descricao"]')?.value?.trim();
      const dataVenc = boletoForm.querySelector('[name="data_vencimento"]')?.value;
      let valorStr = boletoForm.querySelector('[name="valor"]')?.value;
      
      if (!fornecedor) {
        showToast('Preencha o fornecedor.', 'error');
        return;
      }
      if (!descricao) {
        showToast('Preencha a descrição.', 'error');
        return;
      }
      if (!dataVenc) {
        showToast('Preencha a data de vencimento.', 'error');
        return;
      }
      const valorInput = boletoForm.querySelector('[name="valor"]');
      const valor = parseCurrencyInput(valorInput);
      if (valor <= 0) {
        showToast('Informe um valor válido.', 'error');
        return;
      }

      const valorPagoInput = boletoForm.querySelector('[name="valor_pago"]');
      const valorPago = parseCurrencyInput(valorPagoInput);
      
      if (!currentUser?.id) {
        showToast('Sessão expirada. Faça login novamente.', 'error');
        return;
      }
      
      try {
        // Desabilita o botão para evitar cliques duplos
        const submitBtn = boletoForm.querySelector('button[type="submit"]');
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = 'Salvando...';
        }

        const formData = new FormData(boletoForm);
        formData.set('valor', valor.toFixed(2));
        formData.set('valor_pago', valorPago.toFixed(2));

        // Debug: mostra todos os campos
        console.log('📝 Campos do formulário:');
        for (let [key, value] of formData.entries()) {
          console.log(`  ${key}:`, value instanceof File ? `[Arquivo: ${value.name}]` : value);
        }
        
        // Se não houver arquivo, remove do FormData
        if (!boletoAnexoInput?.files[0]) {
          formData.delete('anexo');
          console.log('ℹ️ Nenhum anexo selecionado');
        }

        // Verifica se é recorrente
        const isRecorrente = formData.get('is_recorrente') === '1';
        const mesesRecorrencia = parseInt(formData.get('meses_recorrencia')) || 1;

        console.log('🔄 Recorrente:', isRecorrente, 'Meses:', mesesRecorrencia);

        if (isRecorrente && mesesRecorrencia > 1) {
          // Criar múltiplos boletos
          showToast(`Criando ${mesesRecorrencia} boletos recorrentes...`, 'info');
          
          let sucessos = 0;
          let erros = 0;
          
          for (let i = 0; i < mesesRecorrencia; i++) {
            try {
              const formDataCopy = new FormData();
              
              // Copia todos os campos exceto recorrência
              for (let [key, value] of formData.entries()) {
                if (key !== 'is_recorrente' && key !== 'meses_recorrencia' && key !== 'anexo') {
                  formDataCopy.append(key, value);
                }
              }
              
              // Ajusta a data de vencimento para cada mês (parse local para evitar bug de timezone)
              const dataStr = formData.get('data_vencimento');
              const [ano, mes, dia] = dataStr.split('-').map(Number);
              const dataVencimento = new Date(ano, mes - 1, dia);
              dataVencimento.setMonth(dataVencimento.getMonth() + i);
              const novaDataStr = dataVencimento.getFullYear() + '-' +
                String(dataVencimento.getMonth() + 1).padStart(2, '0') + '-' +
                String(dataVencimento.getDate()).padStart(2, '0');
              formDataCopy.set('data_vencimento', novaDataStr);
              
              // Adiciona anexo apenas no primeiro boleto
              if (i === 0 && boletoAnexoInput?.files[0]) {
                formDataCopy.append('anexo', boletoAnexoInput.files[0]);
              }

              console.log(`📤 Enviando boleto ${i + 1}/${mesesRecorrencia}...`);

              const response = await fetch(`${API_URL}/boletos`, {
                method: 'POST',
                headers: {
                  'X-Usuario-Id': currentUser?.id || ''
                },
                body: formDataCopy
              });

              if (response.ok) {
                sucessos++;
                console.log(`✅ Boleto ${i + 1} criado com sucesso`);
              } else {
                erros++;
                const errorText = await response.text();
                console.error(`❌ Erro no boleto ${i + 1}:`, errorText);
              }
            } catch (err) {
              erros++;
              console.error(`❌ Erro ao criar boleto ${i + 1}:`, err);
            }
          }
          
          if (sucessos > 0) {
            showToast(`✅ ${sucessos} boletos criados com sucesso!`, 'success');
          }
          if (erros > 0) {
            showToast(`⚠️ ${erros} boletos falharam`, 'warning');
          }
        } else {
          // Verifica se é edição ou criação com "modo" explícito (protege contra sobrescrita acidental).
          const idInput = boletoForm.querySelector('input[name="id"]');
          const boletoId = (idInput && idInput.value) ? String(idInput.value).trim() : (formData.get('id') || '').toString().trim();
          const mode = (boletoForm?.dataset?.mode || 'create').toLowerCase();
          const isEdicao = mode === 'edit' && boletoId !== '';

          // Segurança extra: se estiver em modo criação, nunca envia ID.
          if (!isEdicao) {
            if (idInput) idInput.value = '';
            formData.delete('id');
          }
          
          if (isEdicao) {
            // EDIÇÃO - usa PUT
            console.log('✏️ Editando boleto ID:', boletoId);
            
            const unidadeVal = boletoForm.querySelector('[name="unidade_id"]')?.value;
            const jurosVal = parseFloat(boletoForm.querySelector('[name="juros_multa"]')?.value) || 0;
            const data = {
              fornecedor,
              descricao,
              data_vencimento: dataVenc,
              valor: valor.toFixed(2),
              unidade_id: unidadeVal && unidadeVal !== '' ? unidadeVal : null,
              categoria: boletoForm.querySelector('[name="categoria"]')?.value || null,
              numero_boleto: boletoForm.querySelector('[name="numero_boleto"]')?.value || null,
              nome_pagador: boletoForm.querySelector('[name="nome_pagador"]')?.value || null,
              whatsapp_pagador: boletoForm.querySelector('[name="whatsapp_pagador"]')?.value || null,
              status: boletoForm.querySelector('[name="status"]')?.value || 'A_VENCER',
              data_pagamento: boletoForm.querySelector('[name="data_pagamento"]')?.value || null,
              valor_pago: valorPago > 0 ? valorPago.toFixed(2) : null,
              juros_multa: jurosVal,
              observacoes: boletoForm.querySelector('[name="observacoes"]')?.value?.trim() || null
            };
            
            const response = await fetch(`${API_URL}/boletos/${boletoId}`, {
              method: 'PUT',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Usuario-Id': currentUser?.id || ''
              },
              body: JSON.stringify(data)
            });
            
            if (!response.ok) {
              const errorText = await response.text();
              if (response.status === 404) {
                boletoModal.classList.remove('active');
                boletoForm.reset();
                showToast('Boleto não encontrado (pode ter sido excluído). Lista atualizada.', 'warning');
                await loadBoletos({});
                await loadBoletosResumo();
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Salvar Boleto'; }
                return;
              }
              let errMsg = errorText;
              try {
                const errJson = JSON.parse(errorText);
                errMsg = errJson.message || errJson.error || (errJson.errors ? Object.values(errJson.errors).flat().join(', ') : errorText);
              } catch (_) {}
              throw new Error(errMsg || `Erro ao atualizar boleto (${response.status})`);
            }
            
            const result = await response.json();
            console.log('✅ Boleto atualizado:', result);
            showToast('✅ Boleto atualizado com sucesso!', 'success');
            
          } else {
            // CRIAÇÃO - usa POST
            console.log('📤 Criando novo boleto');
            
            const response = await fetch(`${API_URL}/boletos`, {
              method: 'POST',
              headers: {
                'X-Usuario-Id': currentUser?.id || ''
              },
              body: formData
            });

            console.log('📥 Resposta da API:', response.status, response.statusText);

            if (!response.ok) {
              const errorText = await response.text();
              console.error('❌ Erro da API (texto):', errorText);
              
              let errorMessage = 'Erro ao salvar boleto';
              try {
                const error = JSON.parse(errorText);
                console.error('❌ Erro da API (JSON):', error);
                errorMessage = error.message || errorMessage;
                if (error.errors) {
                  console.error('❌ Erros de validação:', error.errors);
                  errorMessage += ': ' + Object.values(error.errors).flat().join(', ');
                }
              } catch {
                errorMessage += ': ' + errorText;
              }
              throw new Error(errorMessage);
            }

            const result = await response.json();
            console.log('✅ Boleto criado com sucesso:', result);
            showToast('✅ Boleto salvo com sucesso!', 'success');
          }
        }

        // Fecha o modal e reseta o formulário
        console.log('🔄 Fechando modal e atualizando lista...');
        boletoModal.classList.remove('active');
        boletoForm.reset();
        boletoForm.dataset.mode = 'create';
        const idReset = boletoForm.querySelector('input[name="id"]');
        if (idReset) idReset.value = '';
        if (boletoAnexoPreview) boletoAnexoPreview.style.display = 'none';
        if (recorrenteFields) recorrenteFields.style.display = 'none';
        
        // Reabilita o botão
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Salvar Boleto';
        }
        
        // Recarregar lista de boletos E resumo
        console.log('🔄 Recarregando lista e cards...');
        await loadBoletos({});  // Carrega TODOS os boletos
        await loadBoletosResumo();  // Atualiza os cards
        
        console.log('✅ Processo concluído!');
      } catch (error) {
        console.error('❌ ERRO ao salvar boleto:', error);
        const msg = error.message || 'Erro ao salvar. Verifique a conexão e tente novamente.';
        const dica = msg.includes('fetch') || msg.includes('Failed') || msg.includes('Network')
          ? ' (Verifique se a API está online e CORS está configurado)'
          : '';
        showToast('❌ ' + msg + dica, 'error');
        
        const submitBtn = boletoForm.querySelector('button[type="submit"]');
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Salvar Boleto';
        }
      }
    });
  }

  // Adiciona eventos aos botoes de acao na tabela
  document.addEventListener('click', async (e) => {
    if (e.target.closest('.btn-icon')) {
      const btn = e.target.closest('.btn-icon');
      const title = btn.getAttribute('title');
      const id = btn.getAttribute('data-id');
      
      if (title === 'Editar') {
        await editarBoleto(id);
      } else if (title === 'Detalhes') {
        await mostrarDetalhesBoleto(id);
      } else if (title === 'Pagar') {
        await abrirModalPagamento(id);
      }
    }
  });
}

// ===== Módulo de Alvarás =====
async function populateAlvarasUnidades() {
  const selectModal = document.querySelector('#alvaraForm select[name="unidade_id"]');
  const selectFiltro = document.getElementById('alvarasUnidadeFiltro');
  try {
    const unidades = await fetchJSON('/unidades');
    if (Array.isArray(unidades)) {
      state.unidades = unidades;
    }
    if (selectModal) {
      selectModal.innerHTML = '<option value="">Selecione a unidade (opcional)</option>' +
        (unidades || []).map(u => `<option value="${u.id}">${escapeHtml(u.nome || ('Unidade ' + u.id))}</option>`).join('');
    }
    if (selectFiltro) {
      selectFiltro.innerHTML = '<option value="">Todas as unidades</option>' +
        (unidades || []).map(u => `<option value="${u.id}">${escapeHtml(u.nome || ('Unidade ' + u.id))}</option>`).join('');
    }
  } catch (_) {
    // silencioso
  }
}

function collectAlvarasListFiltersFromDOM() {
  const filtros = {};
  const mesAno = (document.getElementById('alvarasMesAnoFiltro')?.value || '').trim();
  const unidadeId = (document.getElementById('alvarasUnidadeFiltro')?.value || '').trim();
  if (mesAno) filtros.mes_ano = mesAno;
  if (unidadeId) filtros.unidade_id = unidadeId;
  return filtros;
}

async function loadAlvaras(filtros = {}) {
  const tbody = document.getElementById('alvarasTable');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#2196F3;padding:30px;">⏳ Carregando alvarás...</td></tr>';
  try {
    const params = new URLSearchParams();
    if (filtros.mes_ano) params.append('mes_ano', filtros.mes_ano);
    if (filtros.unidade_id) params.append('unidade_id', filtros.unidade_id);
    const url = `${API_URL}/alvaras?${params.toString()}`;
    const res = await fetch(url, { headers: { 'Content-Type': 'application/json', 'X-Usuario-Id': currentUser?.id || '' } });
    if (!res.ok) throw new Error('Erro ao carregar alvarás');
    const alvaras = await res.json();
    renderAlvaras(Array.isArray(alvaras) ? alvaras : []);
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#d32f2f;padding:30px;">❌ Erro ao carregar alvarás</td></tr>';
  }
}

function renderAlvaras(alvaras) {
  const tbody = document.getElementById('alvarasTable');
  if (!tbody) return;
  if (!alvaras.length) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#607d8b;padding:20px;">Nenhum alvará encontrado.</td></tr>';
    return;
  }
  const unidadesPorId = new Map((state.unidades || []).map(u => [String(u.id), u]));
  const fmtData = (v) => (formatDate(v) || '').split(' ')[0] || '-';
  const rows = alvaras.map(a => {
    const anexo = a.anexo_path
      ? `<a href="${API_URL}/alvaras/${a.id}/anexo" target="_blank" title="Baixar ${escapeHtml(a.anexo_nome || 'anexo')}" style="text-decoration:none;font-size:1.2rem;">📎</a>`
      : '<span style="color:#ccc;">-</span>';
    const unidadeObj = unidadesPorId.get(String(a.unidade_id || '')) || null;
    const unidadeLabel = unidadeObj ? (unidadeObj.nome || `Unidade ${unidadeObj.id}`) : (a.unidade_id ? `Unidade ${a.unidade_id}` : '-');
    return `
      <tr>
        <td data-label="Tipo">${escapeHtml(a.tipo || '-')}</td>
        <td data-label="Unidade">${escapeHtml(unidadeLabel)}</td>
        <td data-label="Início">${fmtData(a.data_inicio)}</td>
        <td data-label="Vencimento">${fmtData(a.data_vencimento)}</td>
        <td data-label="Valor pago">${formatCurrencyBRL(a.valor_pago || 0)}</td>
        <td data-label="Anexo" style="text-align:center;">${anexo}</td>
        <td data-label="Ações">
          <button class="btn-icon" title="Editar" data-id="${a.id}">✏️</button>
          <button class="btn-icon" title="Visualizar" data-id="${a.id}">👁️</button>
          <button class="btn-icon btn-icon--danger btn-deletar-alvara" title="Excluir" data-id="${a.id}" style="color:#c62828;">🗑️</button>
        </td>
      </tr>
    `;
  }).join('');
  tbody.innerHTML = rows;

  tbody.querySelectorAll('.btn-deletar-alvara').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.getAttribute('data-id');
      if (!confirm('Tem certeza que deseja excluir este alvará?')) return;
      btn.disabled = true;
      try {
        await fetchJSON(`/alvaras/${id}`, { method: 'DELETE' });
        showToast('Alvará excluído com sucesso.', 'success');
        const f = collectAlvarasListFiltersFromDOM();
        await loadAlvaras(f);
      } catch (e) {
        showToast('Erro ao excluir: ' + (e.message || 'Falha na operação.'), 'error');
        btn.disabled = false;
      }
    });
  });
}

async function mostrarDetalhesAlvara(id) {
  const modal = document.getElementById('alvaraDetalhesModal');
  const content = document.getElementById('alvaraDetalhesContent');
  const verAnexoBtn = document.getElementById('verAlvaraAnexo');
  if (!modal || !content) return;
  content.innerHTML = '<p style="text-align:center;color:#999;">⏳ Carregando...</p>';
  modal.classList.add('active');
  try {
    const a = await fetchJSON(`/alvaras/${id}`);
    const unidadeObj = (state.unidades || []).find(u => String(u.id) === String(a.unidade_id));
    const unidadeLabel = unidadeObj ? (unidadeObj.nome || `Unidade ${unidadeObj.id}`) : (a.unidade_id ? `Unidade ${a.unidade_id}` : '-');
    const temAnexo = !!a.anexo_path;
    const anexoHtml = temAnexo
      ? `<a href="${API_URL}/alvaras/${a.id}/anexo" target="_blank" rel="noopener noreferrer">${escapeHtml(a.anexo_nome || 'Anexo')}</a>`
      : '<span style="color:#607d8b;">Sem anexo</span>';

    // Botão "Ver anexo" (abre um modal na frente, sem sair da página)
    if (verAnexoBtn) {
      if (a.anexo_path) {
        verAnexoBtn.style.display = '';
        verAnexoBtn.onclick = () => abrirModalAnexoAlvara(a);
      } else {
        verAnexoBtn.style.display = 'none';
        verAnexoBtn.onclick = null;
      }
    }

    const v = (val) => (val === null || val === undefined || val === '' ? '-' : val);
    content.innerHTML = `
      <div class="alvara-kv">
        <div style="background:#f5f5f5;padding:1rem;border-radius:8px;">
          <div class="alvara-kv">
            <div class="alvara-kv__row">
              <div class="alvara-kv__label">ID</div>
              <div class="alvara-kv__value">#${a.id}</div>
            </div>
            <div class="alvara-kv__row">
              <div class="alvara-kv__label">Tipo</div>
              <div class="alvara-kv__value">${escapeHtml(v(a.tipo))}</div>
            </div>
            <div class="alvara-kv__row">
              <div class="alvara-kv__label">Unidade</div>
              <div class="alvara-kv__value">${escapeHtml(v(unidadeLabel))}</div>
            </div>
            <div class="alvara-kv__row">
              <div class="alvara-kv__label">Início</div>
              <div class="alvara-kv__value">${escapeHtml(v(formatDate(a.data_inicio)))}</div>
            </div>
            <div class="alvara-kv__row">
              <div class="alvara-kv__label">Vencimento</div>
              <div class="alvara-kv__value">${escapeHtml(v(formatDate(a.data_vencimento)))}</div>
            </div>
            <div class="alvara-kv__row">
              <div class="alvara-kv__label">Valor pago</div>
              <div class="alvara-kv__value">${escapeHtml(formatCurrencyBRL(a.valor_pago || 0))}</div>
            </div>
            <div class="alvara-kv__row">
              <div class="alvara-kv__label">Anexo</div>
              <div class="alvara-kv__value">${anexoHtml}</div>
            </div>
          </div>
        </div>
      </div>
    `;
  } catch (e) {
    if (verAnexoBtn) {
      verAnexoBtn.style.display = 'none';
      verAnexoBtn.onclick = null;
    }
    content.innerHTML = '<p style="text-align:center;color:#d32f2f;">❌ Erro ao carregar detalhes</p>';
  }
}

/**
 * Visualização de anexo do Alvará (PDF + imagem) no mesmo modal em PC e mobile.
 *
 * CONTRATO (não alterar sem revisar tudo abaixo — evita regressão):
 * 1) Todo GET /alvaras/{id}/anexo DEVE usar os mesmos cabeçalhos de sessão que o resto do app
 *    (Authorization + X-Usuario-Id). Sem isso a API responde erro e o modal fica vazio.
 * 2) Não usar URL do anexo dentro do Google Docs Viewer: o Google não envia esses cabeçalhos.
 * 3) PDF: PDF.js em canvas (mobile); iframe+blob só como fallback se PDF.js falhar.
 * 4) Backend: arquivo binário vem como Symfony BinaryFileResponse — na rota Laravel não use
 *    ->header() em cadeia sobre esse retorno (causa HTTP 500). CORS fica no controller.
 */
const ALVARA_PDFJS_VER = '3.11.174';
const ALVARA_PDFJS_BASE = `https://cdnjs.cloudflare.com/ajax/libs/pdf.js/${ALVARA_PDFJS_VER}`;

let alvaraAnexoObjectUrl = null;
/** Instância PDF.js após carregar o documento (precisa de destroy() ao fechar o modal). */
let alvaraPdfDocumentProxy = null;
/** Task de carregamento em andamento (cancelável). */
let alvaraPdfLoadingTask = null;
/** Promise única para não carregar pdf.min.js várias vezes. */
let alvaraPdfJsLoadPromise = null;

/** Cabeçalhos para GET binário (sem forçar Content-Type: application/json). */
function headersParaAnexoAlvara() {
  const h = {
    ...(currentUser?.token ? { Authorization: `Bearer ${currentUser.token}` } : {}),
    ...(currentUser?.id != null ? { 'X-Usuario-Id': String(currentUser.id) } : {}),
    ...getDeviceHeaders(),
  };
  return h;
}

/** Libera canvas PDF.js, tasks e esconde o host (chamar ao fechar modal ou antes de novo preview). */
function limparVisualizacaoPdfAlvara() {
  if (alvaraPdfDocumentProxy) {
    try {
      alvaraPdfDocumentProxy.destroy();
    } catch (_) {}
    alvaraPdfDocumentProxy = null;
  }
  if (alvaraPdfLoadingTask) {
    try {
      alvaraPdfLoadingTask.destroy();
    } catch (_) {}
    alvaraPdfLoadingTask = null;
  }
  const host = document.getElementById('alvaraAnexoPdfHost');
  if (host) {
    host.innerHTML = '';
    host.style.display = 'none';
  }
}

/**
 * Garante pdfjsLib no window (carrega script uma vez). Worker obrigatório no PDF.js 3.x.
 */
function ensurePdfJsParaAlvara() {
  if (typeof window.pdfjsLib !== 'undefined' && window.pdfjsLib.getDocument) {
    window.pdfjsLib.GlobalWorkerOptions.workerSrc = `${ALVARA_PDFJS_BASE}/pdf.worker.min.js`;
    return Promise.resolve(window.pdfjsLib);
  }
  if (!alvaraPdfJsLoadPromise) {
    alvaraPdfJsLoadPromise = new Promise((resolve, reject) => {
      const id = 'alvaraPdfJsScript';
      if (document.getElementById(id)) {
        let tentativas = 0;
        const wait = () => {
          if (window.pdfjsLib && window.pdfjsLib.getDocument) {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = `${ALVARA_PDFJS_BASE}/pdf.worker.min.js`;
            resolve(window.pdfjsLib);
          } else if (tentativas++ > 200) {
            reject(new Error('Tempo esgotado ao carregar PDF.js'));
          } else {
            setTimeout(wait, 30);
          }
        };
        wait();
        return;
      }
      const s = document.createElement('script');
      s.id = id;
      s.async = true;
      s.src = `${ALVARA_PDFJS_BASE}/pdf.min.js`;
      s.onload = () => {
        if (!window.pdfjsLib || !window.pdfjsLib.getDocument) {
          reject(new Error('PDF.js carregou mas pdfjsLib não está disponível'));
          return;
        }
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = `${ALVARA_PDFJS_BASE}/pdf.worker.min.js`;
        resolve(window.pdfjsLib);
      };
      s.onerror = () => reject(new Error('Não foi possível carregar PDF.js (verifique a internet / CDN)'));
      document.head.appendChild(s);
    }).catch((err) => {
      alvaraPdfJsLoadPromise = null;
      throw err;
    });
  }
  return alvaraPdfJsLoadPromise;
}

/**
 * Desenha todas as páginas do PDF em #alvaraAnexoPdfHost (rolagem vertical, boa no mobile).
 */
async function renderizarPdfAlvaraComPdfJs(arrayBuffer) {
  const host = document.getElementById('alvaraAnexoPdfHost');
  const frame = document.getElementById('alvaraAnexoFrame');
  if (!host) throw new Error('Container de PDF ausente');

  limparVisualizacaoPdfAlvara();
  host.style.display = 'block';
  host.innerHTML =
    '<p style="text-align:center;color:#e0e0e0;padding:1.25rem;margin:0;">Carregando documento…</p>';

  const pdfjsLib = await ensurePdfJsParaAlvara();
  const data = arrayBuffer.slice(0);
  alvaraPdfLoadingTask = pdfjsLib.getDocument({ data });
  let pdf;
  try {
    pdf = await alvaraPdfLoadingTask.promise;
  } finally {
    alvaraPdfLoadingTask = null;
  }
  alvaraPdfDocumentProxy = pdf;

  host.innerHTML = '';
  await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
  const hostW = Math.max(280, host.getBoundingClientRect().width || window.innerWidth - 48);
  const dpr = Math.min(window.devicePixelRatio || 1, 2);

  for (let p = 1; p <= pdf.numPages; p++) {
    const page = await pdf.getPage(p);
    const baseVp = page.getViewport({ scale: 1 });
    const scale = Math.min(2.5, hostW / baseVp.width);
    const viewport = page.getViewport({ scale: scale * dpr });
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d', { alpha: false });
    canvas.width = viewport.width;
    canvas.height = viewport.height;
    canvas.style.width = `${viewport.width / dpr}px`;
    canvas.style.maxWidth = '100%';
    canvas.style.height = 'auto';
    host.appendChild(canvas);
    await page.render({ canvasContext: ctx, viewport }).promise;
  }

  if (frame) {
    frame.style.display = 'none';
    frame.src = 'about:blank';
  }
}

async function abrirModalAnexoAlvara(alvara) {
  const modal = document.getElementById('alvaraAnexoModal');
  const frame = document.getElementById('alvaraAnexoFrame');
  const img = document.getElementById('alvaraAnexoImg');
  const pdfHost = document.getElementById('alvaraAnexoPdfHost');
  const title = document.getElementById('alvaraAnexoTitle');
  const baixarLink = document.getElementById('baixarAlvaraAnexo');
  if (!modal || !frame || !img) return;

  const nome = (alvara?.anexo_nome || 'Anexo').toString();
  if (title) title.textContent = `📎 ${nome}`;

  const viewUrl = `${API_URL}/alvaras/${alvara.id}/anexo`;
  const downloadUrl = `${API_URL}/alvaras/${alvara.id}/anexo?download=1`;

  if (alvaraAnexoObjectUrl) {
    try {
      URL.revokeObjectURL(alvaraAnexoObjectUrl);
    } catch (_) {}
    alvaraAnexoObjectUrl = null;
  }
  limparVisualizacaoPdfAlvara();

  frame.style.display = 'none';
  img.style.display = 'none';
  frame.src = 'about:blank';
  img.src = '';
  if (pdfHost) pdfHost.style.display = 'none';
  modal.classList.add('active');

  const nomeLower = String(nome).toLowerCase();
  const tipoExt = String(alvara?.anexo_tipo || '').toLowerCase();
  const possivelPdf = nomeLower.endsWith('.pdf') || tipoExt === 'pdf';
  const possivelImagem =
    /\.(jpe?g|png|gif|webp)$/i.test(nomeLower) || /^(jpe?g|jpeg|png|gif|webp)$/i.test(tipoExt);

  const mimeFromResponse = (res) => {
    const raw = (res.headers.get('content-type') || '').split(';')[0].trim().toLowerCase();
    if (raw && raw !== 'application/octet-stream') return raw;
    if (possivelPdf) return 'application/pdf';
    if (possivelImagem) {
      if (tipoExt === 'png' || nomeLower.endsWith('.png')) return 'image/png';
      if (tipoExt === 'gif' || nomeLower.endsWith('.gif')) return 'image/gif';
      if (tipoExt === 'webp' || nomeLower.endsWith('.webp')) return 'image/webp';
      return 'image/jpeg';
    }
    return 'application/octet-stream';
  };

  try {
    const res = await fetch(viewUrl, { method: 'GET', cache: 'no-store', headers: headersParaAnexoAlvara() });
    if (!res.ok) {
      let msg = 'Falha ao carregar anexo';
      try {
        const t = await res.text();
        const j = JSON.parse(t);
        if (j.message) msg = j.message;
      } catch (_) {}
      throw new Error(msg);
    }
    const mime = mimeFromResponse(res);
    const buffer = await res.arrayBuffer();
    const isPdf = mime === 'application/pdf' || possivelPdf;

    if (isPdf) {
      img.style.display = 'none';
      img.src = '';
      try {
        await renderizarPdfAlvaraComPdfJs(buffer);
      } catch (pdfErr) {
        limparVisualizacaoPdfAlvara();
        const blob = new Blob([buffer], { type: 'application/pdf' });
        alvaraAnexoObjectUrl = URL.createObjectURL(blob);
        frame.style.display = 'block';
        frame.src = alvaraAnexoObjectUrl;
        showToast('Visualização alternativa (PDF). Se estiver em branco no celular, use Baixar.', 'info');
      }
    } else {
      const blob = new Blob([buffer], { type: mime });
      alvaraAnexoObjectUrl = URL.createObjectURL(blob);
      frame.style.display = 'none';
      frame.src = 'about:blank';
      img.style.display = 'block';
      img.src = alvaraAnexoObjectUrl;
    }
  } catch (e) {
    showToast('Erro ao abrir anexo: ' + (e.message || 'Falha'), 'error');
  }

  if (baixarLink) {
    baixarLink.style.display = '';
    baixarLink.href = '#';
    baixarLink.onclick = async (ev) => {
      ev.preventDefault();
      try {
        const res = await fetch(downloadUrl, { method: 'GET', cache: 'no-store', headers: headersParaAnexoAlvara() });
        if (!res.ok) throw new Error('Falha ao baixar');
        const b = await res.blob();
        const url = URL.createObjectURL(b);
        const a = document.createElement('a');
        a.href = url;
        a.download = nome;
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => {
          try {
            URL.revokeObjectURL(url);
          } catch (_) {}
        }, 60_000);
      } catch (err) {
        showToast('Erro ao baixar: ' + (err.message || 'Falha'), 'error');
      }
    };
  }
}

async function editarAlvara(id) {
  const modal = document.getElementById('alvaraModal');
  const form = document.getElementById('alvaraForm');
  if (!modal || !form) return;
  try {
    await populateAlvarasUnidades();
    const a = await fetchJSON(`/alvaras/${id}`);
    form.dataset.mode = 'edit';
    form.querySelector('[name="id"]').value = a.id;
    form.querySelector('[name="unidade_id"]').value = a.unidade_id || '';
    form.querySelector('[name="tipo"]').value = a.tipo || '';
    form.querySelector('[name="data_inicio"]').value = formatDateForInput(a.data_inicio);
    form.querySelector('[name="data_vencimento"]').value = formatDateForInput(a.data_vencimento);
    const vInput = form.querySelector('[name="valor_pago"]');
    if (vInput) {
      vInput.dataset.value = String(a.valor_pago || 0);
      vInput.value = a.valor_pago ? formatCurrencyBRL(parseFloat(a.valor_pago)) : '';
    }
    document.getElementById('alvaraModalTitle').textContent = '✏️ Editar Alvará';
    modal.classList.add('active');
  } catch (e) {
    showToast('Erro ao carregar alvará para edição', 'error');
  }
}

function setupAlvarasModule() {
  const openBtn = document.getElementById('openNovoAlvara');
  const modal = document.getElementById('alvaraModal');
  const closeBtn = document.getElementById('closeAlvara');
  const cancelBtn = document.getElementById('cancelAlvara');
  const form = document.getElementById('alvaraForm');
  const anexoInput = document.getElementById('alvaraAnexoInput');
  const anexoPreview = document.getElementById('alvaraAnexoPreview');
  const removerAnexoBtn = document.getElementById('alvaraRemoverAnexo');
  const limparFiltrosBtn = document.getElementById('limparFiltrosAlvaras');
  const recarregarBtn = document.getElementById('recarregarTabelaAlvaras');

  populateAlvarasMesAnoFiltro();
  populateAlvarasUnidades();

  const resetToCreate = () => {
    if (!form) return;
    form.dataset.mode = 'create';
    const idEl = form.querySelector('input[name="id"]');
    if (idEl) idEl.value = '';
    if (anexoPreview) anexoPreview.style.display = 'none';
    if (anexoInput) anexoInput.value = '';
    const title = document.getElementById('alvaraModalTitle');
    if (title) title.textContent = '🧾 Novo Alvará';
  };

  if (anexoInput) {
    anexoInput.addEventListener('change', (e) => {
      const file = e.target.files?.[0];
      if (!file) return;
      if (file.size > 5 * 1024 * 1024) {
        showToast('Arquivo muito grande! Máximo 5MB', 'error');
        anexoInput.value = '';
        return;
      }
      const nomeEl = document.getElementById('alvaraAnexoNome');
      const tamanhoEl = document.getElementById('alvaraAnexoTamanho');
      if (nomeEl) nomeEl.textContent = file.name;
      if (tamanhoEl) tamanhoEl.textContent = formatFileSize(file.size);
      if (anexoPreview) anexoPreview.style.display = 'block';
    });
  }
  if (removerAnexoBtn) {
    removerAnexoBtn.addEventListener('click', () => {
      if (anexoInput) anexoInput.value = '';
      if (anexoPreview) anexoPreview.style.display = 'none';
    });
  }

  if (openBtn) {
    openBtn.addEventListener('click', async () => {
      await populateAlvarasUnidades();
      if (modal) modal.classList.add('active');
      if (form) form.reset();
      resetToCreate();
    });
  }
  if (closeBtn) closeBtn.addEventListener('click', () => { if (modal) modal.classList.remove('active'); resetToCreate(); });
  if (cancelBtn) cancelBtn.addEventListener('click', () => { if (form) form.reset(); resetToCreate(); });
  if (modal) modal.addEventListener('click', (e) => { if (e.target === modal) { modal.classList.remove('active'); resetToCreate(); } });

  if (limparFiltrosBtn) {
    limparFiltrosBtn.addEventListener('click', async () => {
      const s1 = document.getElementById('alvarasMesAnoFiltro');
      const s2 = document.getElementById('alvarasUnidadeFiltro');
      if (s1) s1.value = '';
      if (s2) s2.value = '';
      await loadAlvaras({});
      showToast('✅ Filtros limpos', 'success');
    });
  }
  if (recarregarBtn) recarregarBtn.addEventListener('click', async () => loadAlvaras(collectAlvarasListFiltersFromDOM()));
  document.getElementById('alvarasMesAnoFiltro')?.addEventListener('change', async () => loadAlvaras(collectAlvarasListFiltersFromDOM()));
  document.getElementById('alvarasUnidadeFiltro')?.addEventListener('change', async () => loadAlvaras(collectAlvarasListFiltersFromDOM()));

  if (form) {
    attachCurrencyMask(form.querySelector('[name="valor_pago"]'));
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      /**
       * Proteção contra dados inválidos:
       * - Evita enviar unidade_id que não existe (isso causa confusão e "IDs fantasmas").
       * - Evita regressões futuras se o select ficar desatualizado ou DOM for alterado.
       */
      const tipo = form.querySelector('[name="tipo"]')?.value?.trim();
      const dataInicio = form.querySelector('[name="data_inicio"]')?.value;
      const dataVenc = form.querySelector('[name="data_vencimento"]')?.value;
      if (!tipo) return showToast('Preencha o tipo de alvará.', 'error');
      if (!dataInicio) return showToast('Preencha a data de início.', 'error');
      if (!dataVenc) return showToast('Preencha a data de vencimento.', 'error');

      const valorPago = parseCurrencyInput(form.querySelector('[name="valor_pago"]'));
      const fd = new FormData(form);
      fd.set('valor_pago', valorPago > 0 ? valorPago.toFixed(2) : '');
      // Normaliza unidade_id: se vazio, não envia (backend grava null).
      const unidadeVal = (form.querySelector('[name="unidade_id"]')?.value || '').trim();
      if (!unidadeVal) {
        fd.delete('unidade_id');
      } else {
        const unidadeExiste = Array.isArray(state.unidades) && state.unidades.some(u => String(u.id) === String(unidadeVal));
        if (!unidadeExiste) {
          showToast('Unidade inválida. Recarregue a lista de unidades e tente novamente.', 'error');
          return;
        }
        fd.set('unidade_id', unidadeVal);
      }

      const mode = (form.dataset.mode || 'create').toLowerCase();
      const id = (form.querySelector('[name="id"]')?.value || '').trim();
      if (mode !== 'edit') {
        fd.delete('id');
      }

      try {
        let res;
        if (mode === 'edit' && id) {
          res = await fetch(`${API_URL}/alvaras/${id}`, { method: 'POST', headers: { 'X-HTTP-Method-Override': 'PUT', 'X-Usuario-Id': currentUser?.id || '' }, body: fd });
          // fallback: alguns ambientes não aceitam PUT multipart; usa method override
        } else {
          res = await fetch(`${API_URL}/alvaras`, { method: 'POST', headers: { 'X-Usuario-Id': currentUser?.id || '' }, body: fd });
        }
        if (!res.ok) {
          const t = await res.text();
          throw new Error(t || 'Erro ao salvar alvará');
        }
        showToast('✅ Alvará salvo com sucesso!', 'success');
        if (modal) modal.classList.remove('active');
        form.reset();
        resetToCreate();
        await loadAlvaras(collectAlvarasListFiltersFromDOM());
      } catch (err) {
        showToast('❌ ' + (err.message || 'Erro ao salvar'), 'error');
      }
    });
  }

  // Delegação de cliques nas ações
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-icon');
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    const title = btn.getAttribute('title');
    if (!id) return;
    if (title === 'Editar') await editarAlvara(id);
    if (title === 'Visualizar') await mostrarDetalhesAlvara(id);
  });

  document.getElementById('closeAlvaraDetalhes')?.addEventListener('click', () => document.getElementById('alvaraDetalhesModal')?.classList.remove('active'));
  document.getElementById('fecharAlvaraDetalhes')?.addEventListener('click', () => document.getElementById('alvaraDetalhesModal')?.classList.remove('active'));
  document.getElementById('alvaraDetalhesModal')?.addEventListener('click', (e) => { if (e.target.id === 'alvaraDetalhesModal') e.target.classList.remove('active'); });

  // Modal de anexo do alvará
  const closeAnexo = () => {
    const m = document.getElementById('alvaraAnexoModal');
    const f = document.getElementById('alvaraAnexoFrame');
    const i = document.getElementById('alvaraAnexoImg');
    if (m) m.classList.remove('active');
    limparVisualizacaoPdfAlvara();
    if (f) f.src = 'about:blank';
    if (f) f.style.display = 'none';
    if (i) {
      i.src = '';
      i.style.display = 'none';
    }
    if (alvaraAnexoObjectUrl) {
      try { URL.revokeObjectURL(alvaraAnexoObjectUrl); } catch (_) {}
      alvaraAnexoObjectUrl = null;
    }
  };
  document.getElementById('closeAlvaraAnexo')?.addEventListener('click', closeAnexo);
  document.getElementById('fecharAlvaraAnexo')?.addEventListener('click', closeAnexo);
  document.getElementById('alvaraAnexoModal')?.addEventListener('click', (e) => {
    if (e.target && e.target.id === 'alvaraAnexoModal') closeAnexo();
  });
}

const FECHAMENTO_CAIXA_FORMAS = [
  { key: "dinheiro", label: "Dinheiro" },
  { key: "debito", label: "Débito" },
  { key: "credito", label: "Crédito" },
  { key: "pix", label: "PIX" },
  { key: "pix_thiago", label: "PIX Thiago" },
];

const FECHAMENTO_MAQUINHA_LABELS = {
  stone: "Stone",
  cielo: "Cielo",
  rede: "Rede",
  pagbank: "PagBank / PagSeguro",
  mercado_pago: "Mercado Pago",
  sumup: "SumUp",
  nao_utilizada: "Não utilizada neste fechamento",
  outra: "Outra",
};

function fechamentoLegivelMaquinha(val) {
  const k = String(val || "")
    .trim()
    .toLowerCase();
  if (!k) return "—";
  return FECHAMENTO_MAQUINHA_LABELS[k] || String(val);
}

function clearFechamentoCaixaModoEdicao() {
  const hid = document.getElementById("fechamentoEdicaoId");
  if (hid) hid.value = "";
  const btn = document.getElementById("fechamentoSalvarBtn");
  if (btn) btn.textContent = "Salvar registro";
}

function applyFechamentoValorInput(inp, num) {
  if (!inp) return;
  const n = roundToCurrency(Number(num) || 0);
  inp.dataset.value = String(n);
  inp.value = n > 0 ? formatCurrencyBRL(n) : "";
}

async function fetchFechamentoCaixaById(id) {
  return fetchJSON(`/fechamentos-caixa/${encodeURIComponent(String(id))}`);
}

function renderFechamentoCaixaVerHtml(r) {
  let linhas = [];
  try {
    const raw = r?.linhas_json;
    const L = typeof raw === "string" ? JSON.parse(raw) : raw;
    if (Array.isArray(L)) linhas = L;
  } catch (_) {
    /* ignore */
  }
  const saldo = Number(r.saldo_liquido ?? 0);
  const tol = 0.009;
  const sem = Math.abs(saldo) < tol;
  let sit = "Sem quebra (compensado)";
  if (!sem && saldo > 0) sit = "Sobras no fechamento";
  if (!sem && saldo < 0) sit = "Quebra de caixa";

  const rowsT = linhas
    .map((ln) => {
      const lab = escapeHtml((ln.label || ln.key || "—").toString());
      const sis = Number(ln.sis ?? 0);
      const maq = Number(ln.maq ?? 0);
      const d = Number(ln.diff ?? roundToCurrency(maq - sis));
      return `<tr><td>${lab}</td><td style="text-align:right">${escapeHtml(formatCurrencyBRL(sis))}</td><td style="text-align:right">${escapeHtml(formatCurrencyBRL(maq))}</td><td style="text-align:right">${escapeHtml(formatFechamentoSignedDiff(d))}</td></tr>`;
    })
    .join("");

  const dataStr = escapeHtml(fmtData(r.data_fechamento));
  const hora = r.hora_fechamento ? escapeHtml(String(r.hora_fechamento)) : "—";
  const un = escapeHtml((r.unidade_nome || "—").toString());
  const op = escapeHtml((r.operador_nome || "—").toString());
  const reg = escapeHtml((r.registrado_por_nome || "—").toString());
  const pdvNome = escapeHtml((r.sistema_pdv || "—").toString());
  const maqNome = escapeHtml(fechamentoLegivelMaquinha(r.maquinha));
  const obs = (r.observacoes || "").toString().trim();
  const obsHtml = obs ? `<p style="margin-top:0.75rem"><strong>Observações:</strong> ${escapeHtml(obs).replace(/\n/g, "<br/>")}</p>` : "";

  return `
    <p class="subtle-text" style="margin:0 0 0.5rem">Registro nº <strong>${escapeHtml(String(r.id ?? ""))}</strong></p>
    <dl class="fechamento-ver-modal__meta">
      <dt>Data</dt><dd>${dataStr}</dd>
      <dt>Hora</dt><dd>${hora}</dd>
      <dt>Unidade</dt><dd>${un}</dd>
      <dt>Operador do caixa</dt><dd>${op}</dd>
      <dt>Sistema (PDV)</dt><dd>${pdvNome}</dd>
      <dt>Maquinha</dt><dd>${maqNome}</dd>
      <dt>Registrado por</dt><dd>${reg}</dd>
      <dt>Fechamento</dt><dd>${escapeHtml(sit)}</dd>
      <dt>Valor (quebra/sobra)</dt><dd>${escapeHtml(sem ? formatCurrencyBRL(0) : formatCurrencyBRL(Math.abs(saldo)))}</dd>
    </dl>
    ${obsHtml}
    <table>
      <thead><tr><th>Forma</th><th style="text-align:right">PDV</th><th style="text-align:right">Maquinha</th><th style="text-align:right">Diferença</th></tr></thead>
      <tbody>${rowsT || `<tr><td colspan="4" style="text-align:center;color:#78909c">Sem linhas</td></tr>`}</tbody>
    </table>
  `;
}

function openFechamentoVerModal(html) {
  const body = document.getElementById("fechamentoVerModalBody");
  const backdrop = document.getElementById("fechamentoVerModal");
  if (body) body.innerHTML = html;
  if (backdrop) backdrop.classList.add("active");
}

function closeFechamentoVerModal() {
  document.getElementById("fechamentoVerModal")?.classList.remove("active");
}

function popularFechamentoCaixaFormulario(r) {
  const dEl = document.getElementById("fechamentoData");
  if (dEl) dEl.value = formatDateForInput(r.data_fechamento);
  const hEl = document.getElementById("fechamentoHora");
  if (hEl) hEl.value = (r.hora_fechamento && String(r.hora_fechamento).slice(0, 5)) || "";
  const uSel = document.getElementById("fechamentoUnidade");
  if (uSel && r.unidade_id != null) uSel.value = String(r.unidade_id);
  const opInp = document.getElementById("fechamentoCaixaOperador");
  if (opInp) opInp.value = (r.operador_nome || "").toString();
  const opHid = document.getElementById("fechamentoCaixaUsuarioId");
  if (opHid) opHid.value = r.operador_usuario_id != null ? String(r.operador_usuario_id) : "";
  const sisNome = document.getElementById("fechamentoSistemaPdv");
  if (sisNome) sisNome.value = (r.sistema_pdv || "").toString();
  const maqSel = document.getElementById("fechamentoMaquinha");
  if (maqSel) maqSel.value = (r.maquinha || "").toString();
  const obs = document.getElementById("fechamentoObservacoes");
  if (obs) obs.value = (r.observacoes || "").toString();

  let linhas = [];
  try {
    const raw = r?.linhas_json;
    const L = typeof raw === "string" ? JSON.parse(raw) : raw;
    if (Array.isArray(L)) linhas = L;
  } catch (_) {
    /* ignore */
  }
  const byKey = Object.fromEntries(
    linhas.filter((x) => x && x.key).map((x) => [String(x.key), x])
  );
  FECHAMENTO_CAIXA_FORMAS.forEach(({ key }) => {
    const ln = byKey[key] || {};
    applyFechamentoValorInput(document.getElementById(`fechamento_sis_${key}`), ln.sis ?? 0);
    applyFechamentoValorInput(document.getElementById(`fechamento_maq_${key}`), ln.maq ?? 0);
  });
  scheduleRecalcFechamentoCaixa();
}

/** Atendente Caixa sempre; Atendente comum só se marcado “atende caixa” no cadastro. */
function usuarioApareceComoOperadorCaixa(u) {
  const p = (u.perfil || "").toString().trim().toUpperCase();
  if (p === "ATENDENTE_CAIXA") return true;
  if (p === "ATENDENTE") {
    const ac = u.atende_caixa;
    return ac === 1 || ac === true || ac === "1";
  }
  return false;
}

function rotuloFechamentoCaixaOperador(u) {
  const perfil = (u.perfil || "").toString().trim().toUpperCase();
  const nome = (u.nome || u.email || `Usuário ${u.id}`).toString().trim();
  const pLabel = PERFIL_LABELS[perfil] || u.perfil || perfil;
  return `${nome} — ${pLabel}`;
}

function populateFechamentoCaixaOperadorDatalist() {
  const dl = document.getElementById("fechamentoCaixaOperadorDatalist");
  if (!dl) return;
  const lista = (state.usuarios || [])
    .filter((u) => {
      if (Number(u?.ativo ?? 1) !== 1) return false;
      return usuarioApareceComoOperadorCaixa(u);
    })
    .sort((a, b) =>
      (a.nome || a.email || "").localeCompare(b.nome || b.email || "", "pt-BR")
    );
  dl.innerHTML = lista
    .map((u) => `<option value="${escapeHtml(rotuloFechamentoCaixaOperador(u))}"></option>`)
    .join("");
}

function syncFechamentoCaixaOperadorUsuarioId() {
  const inp = document.getElementById("fechamentoCaixaOperador");
  const hid = document.getElementById("fechamentoCaixaUsuarioId");
  if (!inp || !hid) return;
  const val = (inp.value || "").trim();
  hid.value = "";
  if (!val) return;
  const found = (state.usuarios || []).find((u) => rotuloFechamentoCaixaOperador(u) === val);
  if (found && found.id != null) hid.value = String(found.id);
}

function buildFechamentoCaixaPayload() {
  const linhas = [];
  let totalInf = 0;
  let totalSis = 0;
  let totalMaq = 0;
  let somaDiff = 0;
  FECHAMENTO_CAIXA_FORMAS.forEach(({ key, label }) => {
    const sis = fechamentoMoneyFromInput(document.getElementById(`fechamento_sis_${key}`));
    const maq = fechamentoMoneyFromInput(document.getElementById(`fechamento_maq_${key}`));
    const informado = roundToCurrency(sis + maq);
    totalInf = roundToCurrency(totalInf + informado);
    totalSis = roundToCurrency(totalSis + sis);
    totalMaq = roundToCurrency(totalMaq + maq);
    const diffLinha = roundToCurrency(maq - sis);
    somaDiff = roundToCurrency(somaDiff + diffLinha);
    linhas.push({ key, label, esp: 0, sis, maq, informado, diff: diffLinha });
  });
  const tol = 0.009;
  const saldoLiquido = somaDiff;
  const semQuebra = Math.abs(saldoLiquido) < tol;
  const unidadeEl = document.getElementById("fechamentoUnidade");
  const unidadeVal = unidadeEl?.value?.trim();
  const unidadeId = unidadeVal && Number.isFinite(Number(unidadeVal)) ? Number(unidadeVal) : null;
  const opIdRaw = document.getElementById("fechamentoCaixaUsuarioId")?.value?.trim();
  const operadorUsuarioId = opIdRaw && Number.isFinite(Number(opIdRaw)) ? Number(opIdRaw) : null;
  const horaVal = document.getElementById("fechamentoHora")?.value?.trim();
  return {
    data_fechamento: document.getElementById("fechamentoData")?.value?.trim() || "",
    hora_fechamento: horaVal || null,
    unidade_id: unidadeId,
    operador_nome: (document.getElementById("fechamentoCaixaOperador")?.value || "").trim() || null,
    operador_usuario_id: operadorUsuarioId,
    sistema_pdv: (document.getElementById("fechamentoSistemaPdv")?.value || "").trim() || null,
    maquinha: (document.getElementById("fechamentoMaquinha")?.value || "").trim() || null,
    observacoes: (document.getElementById("fechamentoObservacoes")?.value || "").trim() || null,
    linhas,
    total_referencia: 0,
    total_informado: totalInf,
    saldo_liquido: saldoLiquido,
    sem_quebra: semQuebra,
  };
}

async function downloadFechamentoCaixaPdf(id) {
  const headers = {
    ...(currentUser?.token ? { Authorization: `Bearer ${currentUser.token}` } : {}),
    ...(currentUser?.id != null ? { "X-Usuario-Id": String(currentUser.id) } : {}),
    ...getDeviceHeaders(),
  };
  const res = await fetch(`${API_URL}/fechamentos-caixa/${encodeURIComponent(String(id))}/pdf`, {
    method: "GET",
    headers,
    cache: "no-store",
  });
  if (!res.ok) {
    let msg = "Erro ao gerar PDF";
    try {
      const t = await res.text();
      const j = JSON.parse(t);
      if (j.error) msg = j.error;
    } catch (_) {
      /* ignore */
    }
    throw new Error(msg);
  }
  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `fechamento-caixa-${id}.pdf`;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

function fechamentoTotalMaquinasFromRow(row) {
  try {
    const raw = row?.linhas_json;
    const L = typeof raw === "string" ? JSON.parse(raw) : raw;
    if (!Array.isArray(L)) return 0;
    let s = 0;
    L.forEach((line) => {
      const m = Number(line?.maq ?? 0);
      if (Number.isFinite(m)) s = roundToCurrency(s + m);
    });
    return s;
  } catch (_) {
    return 0;
  }
}

/** ym = "YYYY-MM" → último dia do mês em "YYYY-MM-DD" */
function fechamentoUltimoDiaMes(ym) {
  const parts = String(ym || "").split("-");
  const y = parseInt(parts[0], 10);
  const m = parseInt(parts[1], 10);
  if (!y || !m || m < 1 || m > 12) return `${ym}-31`;
  const last = new Date(y, m, 0);
  const d = last.getDate();
  return `${y}-${String(m).padStart(2, "0")}-${String(d).padStart(2, "0")}`;
}

const FECHAMENTO_NEG_TOL = 0.005;

/** Valor maquinha na UI: negativo em vermelho com dica (passe contexto para o title). */
function fechamentoMaquinhaValorHtml(maqTotal, titleNegativo) {
  const m = roundToCurrency(maqTotal);
  const fmt = escapeHtml(formatCurrencyBRL(m));
  if (m < -FECHAMENTO_NEG_TOL) {
    const t = escapeHtml(titleNegativo || "Valor negativo (total maquinha)");
    return `<span class="fechamento-valor-negativo" title="${t}">${fmt}</span>`;
  }
  return fmt;
}

function renderFechamentosCaixaHistorico(rows) {
  const tbody = document.getElementById("fechamentosCaixaHistoricoBody");
  if (!tbody) return;
  if (!rows.length) {
    tbody.innerHTML =
      '<tr><td colspan="8" style="text-align:center;color:#607d8b">Nenhum registro salvo ainda. Preencha e clique em Salvar registro.</td></tr>';
    return;
  }
  tbody.innerHTML = rows
    .map((r) => {
      const op = escapeHtml((r.operador_nome || "—").toString());
      const un = escapeHtml((r.unidade_nome || "—").toString());
      const tot = fechamentoTotalMaquinasFromRow(r);
      const saldo = Number(r.saldo_liquido ?? 0);
      const tol = 0.009;
      const sem = Number(r.sem_quebra) === 1 || Math.abs(saldo) < tol;
      let rotuloFech;
      let valorFech;
      if (sem) {
        rotuloFech = "Sem quebra (compensado)";
        valorFech = formatCurrencyBRL(0);
      } else if (saldo > 0) {
        rotuloFech = "Sobras";
        valorFech = formatCurrencyBRL(saldo);
      } else {
        rotuloFech = "Quebra de caixa";
        valorFech = formatCurrencyBRL(Math.abs(saldo));
      }
      return `<tr>
        <td data-label="Nº">${escapeHtml(String(r.id ?? ""))}</td>
        <td data-label="Data">${escapeHtml(fmtData(r.data_fechamento))} ${r.hora_fechamento ? `<small>${escapeHtml(String(r.hora_fechamento))}</small>` : ""}</td>
        <td data-label="Unidade">${un}</td>
        <td data-label="Operador">${op}</td>
        <td data-label="Total maquinha">${fechamentoMaquinhaValorHtml(tot, `Negativo no histórico: fechamento nº ${r.id ?? "—"}, data ${fmtData(r.data_fechamento)}`)}</td>
        <td data-label="Fechamento">${escapeHtml(rotuloFech)}</td>
        <td data-label="Valor">${escapeHtml(valorFech)}</td>
        <td data-label="Ações" class="fechamento-audit__acoes">
          <button type="button" class="btn secondary fechamento-caixa-ver-btn" data-fechamento-ver="${escapeHtml(String(r.id))}">Ver</button>
          <button type="button" class="btn secondary fechamento-caixa-edit-btn" data-fechamento-edit="${escapeHtml(String(r.id))}">Editar</button>
          <button type="button" class="btn danger fechamento-caixa-del-btn" data-fechamento-del="${escapeHtml(String(r.id))}">Excluir</button>
          <button type="button" class="btn secondary fechamento-caixa-pdf-btn" data-fechamento-pdf="${escapeHtml(String(r.id))}">PDF</button>
        </td>
      </tr>`;
    })
    .join("");
}

async function loadFechamentosCaixaHistorico() {
  const tbody = document.getElementById("fechamentosCaixaHistoricoBody");
  if (!tbody) return;
  try {
    const list = await fetchJSON("/fechamentos-caixa?limit=200");
    renderFechamentosCaixaHistorico(Array.isArray(list) ? list : []);
  } catch (err) {
    console.error(err);
    tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:#c62828">${escapeHtml(err?.message || "Erro ao carregar histórico.")}</td></tr>`;
  }
}

function renderFechamentoResumoMensal(rows, ym, unidadeLabel) {
  const out = document.getElementById("fechamentoResumoMesResultado");
  if (!out) return;
  if (!rows.length) {
    out.innerHTML = `<p class="fechamento-resumo-mes-head"><strong>${escapeHtml(unidadeLabel)}</strong> — ${escapeHtml(ym)}: nenhum fechamento neste período.</p>`;
    return;
  }
  let sumMaq = 0;
  const body = rows
    .map((r) => {
      const maq = fechamentoTotalMaquinasFromRow(r);
      sumMaq = roundToCurrency(sumMaq + maq);
      const op = escapeHtml((r.operador_nome || "—").toString());
      const hora = r.hora_fechamento ? ` <small>${escapeHtml(String(r.hora_fechamento))}</small>` : "";
      const dataFmt = escapeHtml(fmtData(r.data_fechamento));
      const negHint = `Negativo na linha: fechamento nº ${r.id ?? "—"}, data ${fmtData(r.data_fechamento)}`;
      return `<tr>
        <td>${escapeHtml(String(r.id ?? ""))}</td>
        <td>${dataFmt}${hora}</td>
        <td>${op}</td>
        <td style="text-align:right">${fechamentoMaquinhaValorHtml(maq, negHint)}</td>
      </tr>`;
    })
    .join("");
  const totNeg = sumMaq < -FECHAMENTO_NEG_TOL;
  const totBoxClass = totNeg ? "fechamento-resumo-mes-totais fechamento-resumo-mes-totais--negativo" : "fechamento-resumo-mes-totais";
  const totStrong = fechamentoMaquinhaValorHtml(sumMaq, "Total maquinha do mês negativo (soma dos registros)");
  out.innerHTML = `
    <p class="fechamento-resumo-mes-head"><strong>${escapeHtml(unidadeLabel)}</strong> — ${escapeHtml(ym)} · ${rows.length} registro(s)</p>
    <div class="table-wrapper">
      <table>
        <thead><tr>
          <th>Nº</th><th>Data</th><th>Operador</th>
          <th style="text-align:right">Total maquinha</th>
        </tr></thead>
        <tbody>${body}</tbody>
      </table>
    </div>
    <div class="${totBoxClass}">
      Total maquinha no mês: <strong>${totStrong}</strong>
    </div>
  `;
}

const FECHAMENTO_RESUMO_MES_PLACEHOLDER =
  'Escolha o mês e a unidade e clique em <strong>Ver resumo</strong>.';

function limparFechamentoResumoMes() {
  const out = document.getElementById("fechamentoResumoMesResultado");
  if (out) out.innerHTML = FECHAMENTO_RESUMO_MES_PLACEHOLDER;
  const uSel = document.getElementById("fechamentoResumoUnidade");
  const perfil = (currentUser?.perfil || "").toString().trim().toUpperCase();
  if (uSel && perfil === "ADMIN") uSel.value = "";
  showToast("Resumo limpo.", "info");
}

async function carregarFechamentoResumoMensal() {
  const out = document.getElementById("fechamentoResumoMesResultado");
  const uSel = document.getElementById("fechamentoResumoUnidade");
  const mesInp = document.getElementById("fechamentoResumoMes");
  if (!out || !uSel || !mesInp) return;
  const unidadeId = uSel.value?.trim();
  const ym = mesInp.value?.trim();
  if (!unidadeId) {
    showToast("Selecione a unidade.", "error");
    return;
  }
  if (!ym || ym.length < 7) {
    showToast("Selecione o mês.", "error");
    return;
  }
  const parts = ym.split("-");
  const y = parseInt(parts[0], 10);
  const mo = parseInt(parts[1], 10);
  const de = `${y}-${String(mo).padStart(2, "0")}-01`;
  const ate = fechamentoUltimoDiaMes(ym);
  const unidadeLabel = (uSel.options[uSel.selectedIndex]?.text || "").trim() || "Unidade";
  out.innerHTML = '<p class="subtle-text">Carregando…</p>';
  try {
    const qs = new URLSearchParams({
      unidade_id: unidadeId,
      de,
      ate,
      limit: "500",
    });
    const list = await fetchJSON(`/fechamentos-caixa?${qs.toString()}`);
    const rows = Array.isArray(list) ? [...list] : [];
    rows.sort((a, b) => {
      const da = String(a.data_fechamento || "");
      const db = String(b.data_fechamento || "");
      if (da !== db) return da.localeCompare(db);
      return Number(a.id || 0) - Number(b.id || 0);
    });
    renderFechamentoResumoMensal(rows, ym, unidadeLabel);
  } catch (err) {
    console.error(err);
    out.innerHTML = `<p style="color:#c62828">${escapeHtml(err?.message || "Erro ao carregar resumo.")}</p>`;
  }
}

async function salvarFechamentoCaixaRegistro() {
  if (!currentUser?.id) {
    showToast("Faça login para salvar.", "error");
    return;
  }
  const payload = buildFechamentoCaixaPayload();
  if (!payload.data_fechamento) {
    showToast("Informe a data do fechamento.", "error");
    return;
  }
  const editId = document.getElementById("fechamentoEdicaoId")?.value?.trim();
  const btn = document.getElementById("fechamentoSalvarBtn");
  const prev = btn?.textContent;
  try {
    if (btn) {
      btn.disabled = true;
      btn.textContent = "Salvando…";
    }
    if (editId) {
      await fetchJSON(`/fechamentos-caixa/${encodeURIComponent(editId)}`, {
        method: "PUT",
        body: JSON.stringify(payload),
      });
      showToast("Registro atualizado.", "success");
      clearFechamentoCaixaModoEdicao();
    } else {
      await fetchJSON("/fechamentos-caixa", { method: "POST", body: JSON.stringify(payload) });
      showToast("Fechamento salvo no servidor.", "success");
    }
    await loadFechamentosCaixaHistorico();
  } catch (err) {
    showToast(err?.message || "Erro ao salvar fechamento.", "error");
  } finally {
    if (btn) {
      btn.disabled = false;
      const stillEditing = document.getElementById("fechamentoEdicaoId")?.value?.trim();
      btn.textContent = stillEditing ? "Atualizar registro" : "Salvar registro";
    }
  }
}

function fechamentoMoneyFromInput(el) {
  if (!el) return 0;
  if (el.dataset.fechamentoMaskBound) {
    const n = Number(el.dataset.value || 0);
    if (Number.isFinite(n)) return roundToCurrency(n);
  }
  return roundToCurrency(parseCurrencyFromString(el.value || ""));
}

function formatFechamentoSignedDiff(value) {
  const v = roundToCurrency(value);
  const tol = 0.005;
  if (Math.abs(v) < tol) return formatCurrencyBRL(0);
  const absFmt = formatCurrencyBRL(Math.abs(v));
  return v > 0 ? `+ ${absFmt}` : `− ${absFmt}`;
}

let fechamentoRecalcRaf = null;
function scheduleRecalcFechamentoCaixa() {
  if (fechamentoRecalcRaf) cancelAnimationFrame(fechamentoRecalcRaf);
  fechamentoRecalcRaf = requestAnimationFrame(() => {
    fechamentoRecalcRaf = null;
    recalcFechamentoCaixa();
  });
}

function recalcFechamentoCaixa() {
  const tol = 0.009;
  let totalSis = 0;
  let totalMaq = 0;
  let somaDiff = 0;

  FECHAMENTO_CAIXA_FORMAS.forEach(({ key }) => {
    const sis = fechamentoMoneyFromInput(document.getElementById(`fechamento_sis_${key}`));
    const maq = fechamentoMoneyFromInput(document.getElementById(`fechamento_maq_${key}`));
    const diffLinha = roundToCurrency(maq - sis);
    totalSis = roundToCurrency(totalSis + sis);
    totalMaq = roundToCurrency(totalMaq + maq);
    somaDiff = roundToCurrency(somaDiff + diffLinha);

    const diffEl = document.getElementById(`fechamento_diff_linha_${key}`);
    if (diffEl) {
      diffEl.textContent = formatFechamentoSignedDiff(diffLinha);
      diffEl.classList.remove("fechamento-audit__diff--pos", "fechamento-audit__diff--neg", "fechamento-audit__diff--zero");
      if (Math.abs(diffLinha) < tol) diffEl.classList.add("fechamento-audit__diff--zero");
      else if (diffLinha > 0) diffEl.classList.add("fechamento-audit__diff--pos");
      else diffEl.classList.add("fechamento-audit__diff--neg");
    }
  });

  const totPdvEl = document.getElementById("fechamentoTotalSistemaPdv");
  if (totPdvEl) totPdvEl.textContent = formatCurrencyBRL(totalSis);
  const totMaqEl = document.getElementById("fechamentoTotalMaquinas");
  if (totMaqEl) totMaqEl.textContent = formatCurrencyBRL(totalMaq);

  const footPdv = document.getElementById("fechamentoFootPdv");
  if (footPdv) footPdv.textContent = formatCurrencyBRL(totalSis);
  const footMaq = document.getElementById("fechamentoFootMaq");
  if (footMaq) footMaq.textContent = formatCurrencyBRL(totalMaq);

  const footDiff = document.getElementById("fechamentoFootDiff");
  if (footDiff) {
    footDiff.textContent = formatFechamentoSignedDiff(somaDiff);
    footDiff.classList.remove("fechamento-audit__diff--pos", "fechamento-audit__diff--neg", "fechamento-audit__diff--zero");
    if (Math.abs(somaDiff) < tol) footDiff.classList.add("fechamento-audit__diff--zero");
    else if (somaDiff > 0) footDiff.classList.add("fechamento-audit__diff--pos");
    else footDiff.classList.add("fechamento-audit__diff--neg");
  }

  const delta = roundToCurrency(somaDiff);
  const temValores = totalSis > tol || totalMaq > tol;
  const badge = document.getElementById("fechamentoStatusBadge");
  const valorEl = document.getElementById("fechamentoStatusValor");

  if (valorEl) {
    valorEl.classList.remove("fechamento-audit__sit-valor--zero", "fechamento-audit__sit-valor--pos", "fechamento-audit__sit-valor--neg");
    if (!temValores) {
      valorEl.textContent = "Valor: —";
    } else if (Math.abs(delta) < tol) {
      valorEl.textContent = `Valor: ${formatCurrencyBRL(0)}`;
      valorEl.classList.add("fechamento-audit__sit-valor--zero");
    } else if (delta > 0) {
      valorEl.textContent = `Valor: ${formatCurrencyBRL(delta)}`;
      valorEl.classList.add("fechamento-audit__sit-valor--pos");
    } else {
      valorEl.textContent = `Valor: ${formatCurrencyBRL(Math.abs(delta))}`;
      valorEl.classList.add("fechamento-audit__sit-valor--neg");
    }
  }

  if (badge) {
    if (!temValores) {
      badge.textContent = "Preencha PDV e maquinha";
      badge.className = "fechamento-audit__status fechamento-audit__status--neutral";
    } else if (Math.abs(delta) < tol) {
      badge.textContent = "Sem quebra (compensado entre formas)";
      badge.className = "fechamento-audit__status fechamento-audit__status--ok";
    } else if (delta > 0) {
      badge.textContent = "Sobras no fechamento";
      badge.className = "fechamento-audit__status fechamento-audit__status--ok";
    } else {
      badge.textContent = "Quebra de caixa";
      badge.className = "fechamento-audit__status fechamento-audit__status--alert";
    }
  }
}

function ensureFechamentoCaixaCurrencyMasks() {
  const root = document.getElementById("fechamentoSection");
  if (!root) return;
  root.querySelectorAll('input[data-currency="1"]').forEach((inp) => {
    if (inp.dataset.fechamentoMaskBound) return;
    inp.dataset.fechamentoMaskBound = "1";
    attachCurrencyMask(inp);
  });
}

function resetFechamentoCaixaForm() {
  clearFechamentoCaixaModoEdicao();
  const root = document.getElementById("fechamentoSection");
  if (!root) return;
  root.querySelectorAll('input[data-currency="1"]').forEach((inp) => {
    inp.value = "";
    inp.dataset.value = "0";
  });
  const d = document.getElementById("fechamentoData");
  const h = document.getElementById("fechamentoHora");
  if (d) d.value = new Date().toISOString().slice(0, 10);
  if (h) h.value = new Date().toTimeString().slice(0, 5);
  const op = document.getElementById("fechamentoCaixaOperador");
  const opId = document.getElementById("fechamentoCaixaUsuarioId");
  if (op) op.value = "";
  if (opId) opId.value = "";
  const maq = document.getElementById("fechamentoMaquinha");
  if (maq) maq.value = "";
  const sis = document.getElementById("fechamentoSistemaPdv");
  if (sis) sis.value = "";
  const obs = document.getElementById("fechamentoObservacoes");
  if (obs) obs.value = "";
  const perfil = (currentUser?.perfil || "").toString().trim().toUpperCase();
  const uSel = document.getElementById("fechamentoUnidade");
  if (uSel && perfil !== "ADMIN") {
    if (currentUser?.unidade_id) uSel.value = String(currentUser.unidade_id);
  } else if (uSel) uSel.value = "";
  scheduleRecalcFechamentoCaixa();
}

function populateFechamentoUnidadeSelectsFromState() {
  const uSel = document.getElementById("fechamentoUnidade");
  const rSel = document.getElementById("fechamentoResumoUnidade");
  const perfil = (currentUser?.perfil || "").toString().trim().toUpperCase();
  const fixedUnidade = currentUser?.unidade_id && perfil !== "ADMIN";
  const apply = (sel) => {
    if (!sel) return;
    if (!Array.isArray(state.unidades) || !state.unidades.length) {
      populateSelect(sel, "", "Selecione a unidade");
      sel.disabled = false;
      return;
    }
    const options = state.unidades
      .map((u) => `<option value="${u.id}">${escapeHtml(u.nome || `Unidade ${u.id}`)}</option>`)
      .join("");
    populateSelect(sel, options, "Selecione a unidade");
    if (fixedUnidade) {
      sel.value = String(currentUser.unidade_id);
      sel.disabled = true;
    } else {
      sel.disabled = false;
    }
  };
  apply(uSel);
  apply(rSel);
}

async function loadFechamentoCaixaSection() {
  await loadUnidades(false).catch(() => {});
  await loadUsuarios(false).catch(() => {});
  populateFechamentoCaixaOperadorDatalist();
  syncFechamentoCaixaOperadorUsuarioId();
  populateFechamentoUnidadeSelectsFromState();
  const mesResumo = document.getElementById("fechamentoResumoMes");
  if (mesResumo && !mesResumo.value) mesResumo.value = new Date().toISOString().slice(0, 7);
  const d = document.getElementById("fechamentoData");
  const t = document.getElementById("fechamentoHora");
  if (d && !d.value) d.value = new Date().toISOString().slice(0, 10);
  if (t && !t.value) t.value = new Date().toTimeString().slice(0, 5);
  ensureFechamentoCaixaCurrencyMasks();
  scheduleRecalcFechamentoCaixa();
  loadFechamentosCaixaHistorico();
}

function setupFechamentoCaixaAuditoria() {
  const section = document.getElementById("fechamentoSection");
  if (!section) return;

  section.addEventListener("input", (e) => {
    if (e.target && e.target.closest && e.target.closest("#fechamentoSection") && e.target.matches("input, textarea")) {
      scheduleRecalcFechamentoCaixa();
    }
  });
  section.addEventListener("change", (e) => {
    if (e.target && e.target.closest && e.target.closest("#fechamentoSection")) {
      scheduleRecalcFechamentoCaixa();
    }
  });

  document.getElementById("fechamentoLimparBtn")?.addEventListener("click", () => {
    resetFechamentoCaixaForm();
    showToast("Conferência limpa.", "success");
  });

  document.getElementById("fechamentoCaixaOperador")?.addEventListener("change", () => {
    syncFechamentoCaixaOperadorUsuarioId();
  });
  document.getElementById("fechamentoCaixaOperador")?.addEventListener("blur", () => {
    syncFechamentoCaixaOperadorUsuarioId();
  });

  document.getElementById("fechamentoSalvarBtn")?.addEventListener("click", () => {
    salvarFechamentoCaixaRegistro();
  });
  document.getElementById("fechamentoTogglePrincipalBtn")?.addEventListener("click", () => {
    const painel = document.getElementById("fechamentoPrincipalPainel");
    const btn = document.getElementById("fechamentoTogglePrincipalBtn");
    if (!painel || !btn) return;
    const collapsed = painel.classList.toggle("fechamento-audit__principal-painel--collapsed");
    btn.setAttribute("aria-expanded", collapsed ? "false" : "true");
    btn.title = collapsed ? "Expandir painel" : "Recolher painel";
  });
  document.getElementById("fechamentoAtualizarHistoricoBtn")?.addEventListener("click", () => {
    loadFechamentosCaixaHistorico().then(() => showToast("Lista atualizada.", "info"));
  });

  document.getElementById("fechamentoResumoMesBtn")?.addEventListener("click", () => {
    carregarFechamentoResumoMensal();
  });
  document.getElementById("fechamentoResumoMesLimparBtn")?.addEventListener("click", () => {
    limparFechamentoResumoMes();
  });

  document.getElementById("fechamentoHistoricoTable")?.addEventListener("click", async (e) => {
    const pdfBtn = e.target.closest("[data-fechamento-pdf]");
    if (pdfBtn) {
      const id = pdfBtn.getAttribute("data-fechamento-pdf");
      if (!id) return;
      try {
        await downloadFechamentoCaixaPdf(id);
        showToast("PDF baixado.", "success");
      } catch (err) {
        showToast(err?.message || "Erro ao baixar PDF.", "error");
      }
      return;
    }

    const verBtn = e.target.closest("[data-fechamento-ver]");
    if (verBtn) {
      const id = verBtn.getAttribute("data-fechamento-ver");
      if (!id) return;
      try {
        const row = await fetchFechamentoCaixaById(id);
        openFechamentoVerModal(renderFechamentoCaixaVerHtml(row));
      } catch (err) {
        showToast(err?.message || "Erro ao abrir registro.", "error");
      }
      return;
    }

    const editBtn = e.target.closest("[data-fechamento-edit]");
    if (editBtn) {
      const id = editBtn.getAttribute("data-fechamento-edit");
      if (!id || !currentUser?.id) return;
      try {
        const row = await fetchFechamentoCaixaById(id);
        popularFechamentoCaixaFormulario(row);
        const hid = document.getElementById("fechamentoEdicaoId");
        if (hid) hid.value = String(id);
        const sBtn = document.getElementById("fechamentoSalvarBtn");
        if (sBtn) sBtn.textContent = "Atualizar registro";
        document.getElementById("fechamentoSection")?.scrollIntoView({ behavior: "smooth", block: "start" });
      } catch (err) {
        showToast(err?.message || "Erro ao carregar para edição.", "error");
      }
      return;
    }

    const delBtn = e.target.closest("[data-fechamento-del]");
    if (delBtn) {
      const id = delBtn.getAttribute("data-fechamento-del");
      if (!id || !currentUser?.id) return;
      if (!window.confirm(`Excluir o fechamento nº ${id}? Esta ação não pode ser desfeita.`)) return;
      try {
        await fetchJSON(`/fechamentos-caixa/${encodeURIComponent(id)}`, { method: "DELETE" });
        showToast("Registro excluído.", "success");
        const editing = document.getElementById("fechamentoEdicaoId")?.value?.trim();
        if (editing === String(id)) {
          resetFechamentoCaixaForm();
        }
        await loadFechamentosCaixaHistorico();
      } catch (err) {
        showToast(err?.message || "Erro ao excluir.", "error");
      }
    }
  });

  document.getElementById("fechamentoVerModalFechar")?.addEventListener("click", () => closeFechamentoVerModal());
  document.getElementById("fechamentoVerModal")?.addEventListener("click", (ev) => {
    if (ev.target && ev.target.id === "fechamentoVerModal") closeFechamentoVerModal();
  });
}

function setupReciboAjudaCusto() {
  const section = document.getElementById("reciboAjudaSection");
  if (!section) return;

  const apiFeedback = document.getElementById("reciboAjudaApiFeedback");
  const edicaoId = document.getElementById("reciboAjudaEdicaoId");
  const funcionarioBusca = document.getElementById("reciboAjudaFuncionarioBusca");
  const funcionarioSelect = document.getElementById("reciboAjudaFuncionarioSelect");
  const funcionarioCpf = document.getElementById("reciboAjudaFuncionarioCpf");
  const unidadeSelect = document.getElementById("reciboAjudaUnidadeSelect");
  const unidadeCnpj = document.getElementById("reciboAjudaUnidadeCnpj");
  const competencia = document.getElementById("reciboAjudaCompetencia");
  const dataPagamento = document.getElementById("reciboAjudaDataPagamento");
  const finalidadeSelect = document.getElementById("reciboAjudaFinalidadeSelect");
  const valor = document.getElementById("reciboAjudaValor");
  const assinaturaTipo = document.getElementById("reciboAjudaAssinaturaTipo");
  const btnSalvar = document.getElementById("reciboAjudaSalvarBtn");
  const btnLimpar = document.getElementById("reciboAjudaLimparBtn");
  const canvas = document.getElementById("reciboAjudaAssinaturaCanvas");
  const btnLimparAss = document.getElementById("reciboAjudaAssinaturaLimparBtn");
  const tableBody = document.getElementById("reciboAjudaTableBody");
  const modoCodigoInfo = document.getElementById("reciboAjudaModoCodigoInfo");

  const togglePainelBtn = document.getElementById("reciboAjudaTogglePainelBtn");
  const painel = document.getElementById("reciboAjudaPainel");

  const confirmarSolicitarBtn = document.getElementById("reciboAjudaConfirmarSolicitarBtn");
  const confirmarWhatsappLink = document.getElementById("reciboAjudaConfirmarWhatsappLink");
  const confirmarCodigoInput = document.getElementById("reciboAjudaConfirmarCodigoInput");
  const confirmarCodigoBtn = document.getElementById("reciboAjudaConfirmarCodigoBtn");
  const confirmarFeedback = document.getElementById("reciboAjudaConfirmarFeedback");

  const capturarIpBtn = document.getElementById("reciboAjudaCapturarIpBtn");
  const capturarLocalBtn = document.getElementById("reciboAjudaCapturarLocalBtn");
  const evidResumo = document.getElementById("reciboAjudaEvidenciasResumo");

  const FINALIDADE_LABELS = {
    auxilio_combustivel: "Auxílio Combustível",
    ajuda_custo: "Ajuda de custo",
    transporte: "Transporte",
    alimentacao: "Alimentação",
    outro: "Outro",
  };

  const CONFIRM_CODE_STORAGE_KEY = "sas-estoque-recibo-ajuda-confirm-code";
  const CONFIRM_CODE_TTL_MS = 10 * 60 * 1000;

  let confirmadoEmIso = "";
  let evidIpPublico = "";
  let evidGeo = null; // { lat, lng, acc }

  function setFeedback(el, msg, kind = "info") {
    if (!el) return;
    el.textContent = msg || "";
    el.className = `form-feedback ${kind}`;
    el.classList.toggle("hidden", !msg);
  }

  function setApiFeedback(msg, kind = "info") {
    setFeedback(apiFeedback, msg, kind);
  }

  function setCanvasLocked(locked) {
    if (!canvas) return;
    canvas.classList.toggle("is-locked", !!locked);
  }

  function getAssinaturaTipo() {
    const v = (assinaturaTipo?.value || "desenho").toString().trim().toLowerCase();
    return v === "codigo" ? "codigo" : "desenho";
  }
  function applyAssinaturaTipoUI() {
    const tipo = getAssinaturaTipo();
    const isCodigo = tipo === "codigo";
    if (modoCodigoInfo) modoCodigoInfo.classList.toggle("hidden", !isCodigo);
    if (btnLimparAss) btnLimparAss.disabled = isCodigo;
    if (canvas) canvas.style.display = isCodigo ? "none" : "";
    // No modo código: não exige WhatsApp/assinatura
    if (isCodigo) {
      setCanvasLocked(true);
      setFeedback(confirmarFeedback, "Modo código: não precisa confirmação/assinatura.", "info");
    } else {
      setCanvasLocked(!confirmadoEmIso);
    }
  }
  assinaturaTipo?.addEventListener("change", applyAssinaturaTipoUI);

  togglePainelBtn?.addEventListener("click", () => {
    if (!painel || !togglePainelBtn) return;
    const collapsed = painel.classList.toggle("recibo-ajuda__painel--collapsed");
    togglePainelBtn.setAttribute("aria-expanded", collapsed ? "false" : "true");
    togglePainelBtn.title = collapsed ? "Expandir painel" : "Recolher painel";
  });

  // === Modal PDF (Recibo ajuda) ===
  let reciboAjudaPdfObjectUrl = null;
  let reciboAjudaPdfDocumentProxy = null;
  let reciboAjudaPdfLoadingTask = null;

  function limparVisualizacaoPdfReciboAjuda() {
    if (reciboAjudaPdfDocumentProxy) {
      try { reciboAjudaPdfDocumentProxy.destroy(); } catch (_) {}
      reciboAjudaPdfDocumentProxy = null;
    }
    if (reciboAjudaPdfLoadingTask) {
      try { reciboAjudaPdfLoadingTask.destroy(); } catch (_) {}
      reciboAjudaPdfLoadingTask = null;
    }
    const host = document.getElementById('reciboAjudaPdfHost');
    if (host) {
      host.innerHTML = '';
      host.style.display = 'none';
    }
    const frame = document.getElementById('reciboAjudaPdfFrame');
    if (frame) {
      frame.src = 'about:blank';
      frame.style.display = 'none';
    }
    const dl = document.getElementById('reciboAjudaPdfBaixar');
    if (dl) {
      dl.href = '#';
      dl.style.display = 'none';
    }
    if (reciboAjudaPdfObjectUrl) {
      try { URL.revokeObjectURL(reciboAjudaPdfObjectUrl); } catch (_) {}
      reciboAjudaPdfObjectUrl = null;
    }
  }

  async function renderizarPdfReciboAjudaComPdfJs(arrayBuffer) {
    const host = document.getElementById('reciboAjudaPdfHost');
    const frame = document.getElementById('reciboAjudaPdfFrame');
    if (!host) throw new Error('Container de PDF ausente');
    limparVisualizacaoPdfReciboAjuda();

    host.style.display = 'block';
    host.innerHTML = '<p style="text-align:center;color:#e0e0e0;padding:1.25rem;margin:0;">Carregando documento…</p>';

    // Reaproveita o loader do Alvará (PDF.js)
    const pdfjsLib = await ensurePdfJsParaAlvara();
    const data = arrayBuffer.slice(0);
    reciboAjudaPdfLoadingTask = pdfjsLib.getDocument({ data });
    let pdf;
    try {
      pdf = await reciboAjudaPdfLoadingTask.promise;
    } finally {
      reciboAjudaPdfLoadingTask = null;
    }
    reciboAjudaPdfDocumentProxy = pdf;

    host.innerHTML = '';
    await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
    const hostW = Math.max(280, host.getBoundingClientRect().width || window.innerWidth - 48);
    const dpr = Math.min(window.devicePixelRatio || 1, 2);

    for (let p = 1; p <= pdf.numPages; p++) {
      const page = await pdf.getPage(p);
      const baseVp = page.getViewport({ scale: 1 });
      const scale = Math.min(2.5, hostW / baseVp.width);
      const viewport = page.getViewport({ scale: scale * dpr });
      const c = document.createElement('canvas');
      const ctx = c.getContext('2d', { alpha: false });
      c.width = viewport.width;
      c.height = viewport.height;
      c.style.width = `${viewport.width / dpr}px`;
      c.style.maxWidth = '100%';
      c.style.height = 'auto';
      host.appendChild(c);
      await page.render({ canvasContext: ctx, viewport }).promise;
    }

    if (frame) {
      frame.style.display = 'none';
      frame.src = 'about:blank';
    }
  }

  async function abrirReciboAjudaPdfModal(id) {
    const modal = document.getElementById('reciboAjudaPdfModal');
    const host = document.getElementById('reciboAjudaPdfHost');
    const frame = document.getElementById('reciboAjudaPdfFrame');
    const title = document.getElementById('reciboAjudaPdfTitle');
    const baixar = document.getElementById('reciboAjudaPdfBaixar');
    if (!modal || !host || !frame) return;

    if (title) title.textContent = `🧾 Recibo (PDF) #${id}`;
    modal.dataset.reciboAjudaId = String(id);
    limparVisualizacaoPdfReciboAjuda();
    modal.classList.add('active');

    try {
      const headers = {
        ...(currentUser?.token ? { Authorization: `Bearer ${currentUser.token}` } : {}),
        ...(currentUser?.id != null ? { 'X-Usuario-Id': String(currentUser.id) } : {}),
        ...getDeviceHeaders(),
      };
      // NÃO enviar Content-Type JSON em binário
      const resPdf = await fetch(`${API_URL}/recibos-ajuda/${encodeURIComponent(String(id))}/pdf`, { method: "GET", headers, cache: "no-store" });
      if (!resPdf.ok) throw new Error("Não foi possível carregar o PDF.");
      const blob = await resPdf.blob();
      reciboAjudaPdfObjectUrl = URL.createObjectURL(blob);
      if (baixar) {
        baixar.href = reciboAjudaPdfObjectUrl;
        baixar.style.display = '';
      }
      const buffer = await blob.arrayBuffer();
      try {
        await renderizarPdfReciboAjudaComPdfJs(buffer);
      } catch (pdfErr) {
        // fallback: iframe
        if (host) host.style.display = 'none';
        frame.src = reciboAjudaPdfObjectUrl;
        frame.style.display = 'block';
      }
    } catch (e) {
      if (host) {
        host.style.display = 'block';
        host.innerHTML = '<p style="text-align:center;color:#ff8a80;padding:1.25rem;margin:0;">❌ Erro ao carregar PDF</p>';
      }
    }
  }

  const closeReciboPdf = () => {
    const m = document.getElementById('reciboAjudaPdfModal');
    if (m) m.classList.remove('active');
    limparVisualizacaoPdfReciboAjuda();
  };
  document.getElementById('closeReciboAjudaPdf')?.addEventListener('click', closeReciboPdf);
  document.getElementById('fecharReciboAjudaPdf')?.addEventListener('click', closeReciboPdf);
  document.getElementById('reciboAjudaPdfModal')?.addEventListener('click', (e) => {
    if (e.target && e.target.id === 'reciboAjudaPdfModal') closeReciboPdf();
  });

  document.getElementById('reciboAjudaPdfSalvarBtn')?.addEventListener('click', async () => {
    const modal = document.getElementById('reciboAjudaPdfModal');
    const id = modal?.dataset?.reciboAjudaId;
    if (!id) return showToast("Abra um recibo antes de salvar.", "warning");

    try {
      // se já temos objectUrl carregado, baixa direto
      let url = reciboAjudaPdfObjectUrl;
      if (!url) {
        // fallback: carrega PDF e cria objectUrl
        const headers = {
          ...(currentUser?.token ? { Authorization: `Bearer ${currentUser.token}` } : {}),
          ...(currentUser?.id != null ? { 'X-Usuario-Id': String(currentUser.id) } : {}),
          ...getDeviceHeaders(),
        };
        const resPdf = await fetch(`${API_URL}/recibos-ajuda/${encodeURIComponent(String(id))}/pdf`, { method: "GET", headers, cache: "no-store" });
        if (!resPdf.ok) throw new Error("Não foi possível carregar o PDF.");
        const blob = await resPdf.blob();
        url = URL.createObjectURL(blob);
        reciboAjudaPdfObjectUrl = url;
      }
      const a = document.createElement('a');
      a.href = url;
      a.download = `recibo-ajuda-${id}.pdf`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      showToast("Download iniciado.", "success");
    } catch (e) {
      showToast(e?.message || "Não foi possível salvar o PDF.", "error");
    }
  });

  function updateEvidResumo() {
    if (!evidResumo) return;
    const parts = [];
    if (confirmadoEmIso) parts.push(`Confirmado: ${new Date(confirmadoEmIso).toLocaleString("pt-BR")}`);
    if (evidIpPublico) parts.push(`IP: ${evidIpPublico}`);
    if (evidGeo?.lat && evidGeo?.lng) parts.push(`Geo: ${evidGeo.lat.toFixed(5)}, ${evidGeo.lng.toFixed(5)} (±${Math.round(evidGeo.acc || 0)}m)`);
    evidResumo.textContent = parts.length ? parts.join(" • ") : "";
  }

  async function fetchRecibosAjudaLista() {
    const headers = { "Content-Type": "application/json", "X-Usuario-Id": String(currentUser?.id || ""), ...getDeviceHeaders() };
    if ((currentUser?.perfil || "").toString().trim().toUpperCase() === "ADMIN") headers["X-Debug"] = "1";
    const res = await fetch(`${API_URL}/recibos-ajuda`, { method: "GET", headers, cache: "no-store" });
    if (!res.ok) {
      const txt = await res.text().catch(() => "");
      let msg = txt;
      try { const j = JSON.parse(txt); if (j && typeof j === "object" && j.error) msg = j.error; } catch (e) {}
      throw new Error(msg || `Falha ao carregar recibos (HTTP ${res.status})`);
    }
    const data = await res.json().catch(() => []);
    return Array.isArray(data) ? data : [];
  }

  async function renderRecibosTabela() {
    if (!tableBody) return;
    try {
      setApiFeedback("", "info");
      const lista = (await fetchRecibosAjudaLista()).sort((a, b) => Number(b.id) - Number(a.id));
      if (!lista.length) {
        tableBody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#607d8b">Nenhum recibo salvo.</td></tr>`;
        return;
      }
      tableBody.innerHTML = lista
        .map((r) => {
          const fid = escapeHtml(r.funcionario_nome || "-");
          const un = escapeHtml(r.unidade_nome || "-");
          const comp = escapeHtml(r.competencia || "-");
          const fin = escapeHtml(FINALIDADE_LABELS[r.finalidade] || r.finalidade || "-");
          const v = escapeHtml(formatCurrencyBRL(Number(r.valor) || 0));
          const id = escapeHtml(String(r.id));
          return `<tr>
            <td data-label="Nº">#${id}</td>
            <td data-label="Funcionário">${fid}</td>
            <td data-label="Unidade">${un}</td>
            <td data-label="Competência">${comp}</td>
            <td data-label="Finalidade">${fin}</td>
            <td data-label="Valor">${v}</td>
            <td data-label="Ações" class="table-actions">
              <button type="button" class="table-action" data-reciboajuda-ver="${id}">Ver</button>
              <button type="button" class="table-action" data-reciboajuda-del="${id}">Deletar</button>
            </td>
          </tr>`;
        })
        .join("");
    } catch (e) {
      const msg = escapeHtml(e?.message || "Não foi possível carregar a lista.");
      tableBody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#c62828">Falha ao listar: ${msg}</td></tr>`;
      showToast(e?.message || "Falha ao listar recibos.", "error");
      setApiFeedback(`Falha ao listar recibos na API (${API_URL}): ${e?.message || "erro"}`, "error");
      console.error("ReciboAjuda: listar falhou", e);
    }
  }

  function setUnidadeCnpjFromSelect() {
    if (!unidadeCnpj) return;
    const uid = (unidadeSelect?.value || "").trim();
    const u = (state.unidades || []).find((x) => String(x.id) === String(uid));
    const raw = u?.cnpj;
    unidadeCnpj.value = raw != null && String(raw).trim() !== "" ? formatCnpjCpfDisplay(String(raw)) : "";
  }

  function setFuncionarioCpfFromSelect() {
    if (!funcionarioCpf) return;
    const fid = (funcionarioSelect?.value || "").trim();
    const f = (state.funcionarios || []).find((x) => String(x.id) === String(fid));
    const raw = f?.cpf;
    funcionarioCpf.value = raw != null && String(raw).trim() !== "" ? formatCnpjCpfDisplay(String(raw)) : "";
  }

  function populateReciboAjudaSelects() {
    if (funcionarioSelect) {
      const ativos = (state.funcionarios || []).filter((f) => (f.status || "ativo") === "ativo");
      funcionarioSelect.innerHTML =
        '<option value="">Selecione</option>' +
        ativos
          .map((f) => `<option value="${f.id}">${escapeHtml(f.nome_completo || f.nome || "")}</option>`)
          .join("");
    }
    if (unidadeSelect) {
      unidadeSelect.innerHTML =
        '<option value="">Selecione a unidade</option>' +
        (state.unidades || []).map((u) => `<option value="${u.id}">${escapeHtml(u.nome || `Unidade ${u.id}`)}</option>`).join("");
    }
    setUnidadeCnpjFromSelect();
    setFuncionarioCpfFromSelect();
  }

  async function loadReciboAjudaSection() {
    await loadUnidades(false).catch(() => {});
    await loadFuncionarios(false).catch(() => {});
    populateReciboAjudaSelects();
    if (competencia && !competencia.value) competencia.value = new Date().toISOString().slice(0, 7);
    if (valor && !valor.dataset.reciboMaskBound) {
      valor.dataset.reciboMaskBound = "1";
      attachCurrencyMask(valor);
    }
    // por padrão, exige confirmação antes de assinar (exceto modo código)
    confirmadoEmIso = "";
    evidIpPublico = "";
    evidGeo = null;
    setCanvasLocked(true);
    setFeedback(confirmarFeedback, "Confirme via WhatsApp para liberar a assinatura.", "info");
    if (confirmarCodigoInput) { confirmarCodigoInput.value = ""; confirmarCodigoInput.classList.add("hidden"); }
    confirmarCodigoBtn?.classList.add("hidden");
    confirmarWhatsappLink?.classList.add("hidden");
    updateEvidResumo();
    if (assinaturaTipo && !assinaturaTipo.value) assinaturaTipo.value = "desenho";
    applyAssinaturaTipoUI();
    await renderRecibosTabela();
  }

  // expõe para o handler de navegação
  window.loadReciboAjudaSection = loadReciboAjudaSection;

  function filterFuncionarioSelect() {
    if (!funcionarioSelect || !funcionarioBusca) return;
    const q = funcionarioBusca.value.trim().toLowerCase();
    Array.from(funcionarioSelect.options).forEach((opt, idx) => {
      if (idx === 0) return; // placeholder
      const show = !q || (opt.textContent || "").toLowerCase().includes(q);
      opt.hidden = !show;
    });
  }

  funcionarioBusca?.addEventListener("input", filterFuncionarioSelect);
  unidadeSelect?.addEventListener("change", setUnidadeCnpjFromSelect);
  funcionarioSelect?.addEventListener("change", () => {
    setFuncionarioCpfFromSelect();
  });

  // Assinatura (canvas) - desenho simples
  let drawing = false;
  let last = null;
  const ctx = canvas?.getContext ? canvas.getContext("2d") : null;
  function canvasPointFromEvent(ev) {
    const rect = canvas.getBoundingClientRect();
    const clientX = ev.touches?.[0]?.clientX ?? ev.clientX;
    const clientY = ev.touches?.[0]?.clientY ?? ev.clientY;
    const x = ((clientX - rect.left) / rect.width) * canvas.width;
    const y = ((clientY - rect.top) / rect.height) * canvas.height;
    return { x, y };
  }
  function startDraw(ev) {
    if (!canvas || !ctx) return;
    drawing = true;
    last = canvasPointFromEvent(ev);
    ev.preventDefault?.();
  }
  function moveDraw(ev) {
    if (!drawing || !canvas || !ctx || !last) return;
    const p = canvasPointFromEvent(ev);
    ctx.lineWidth = 2.5;
    ctx.lineCap = "round";
    ctx.strokeStyle = "#263238";
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    last = p;
    ev.preventDefault?.();
  }
  function endDraw() {
    drawing = false;
    last = null;
  }
  function clearSignature() {
    if (!canvas || !ctx) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
  }
  if (canvas) {
    canvas.addEventListener("mousedown", startDraw);
    canvas.addEventListener("mousemove", moveDraw);
    window.addEventListener("mouseup", endDraw);
    canvas.addEventListener("touchstart", startDraw, { passive: false });
    canvas.addEventListener("touchmove", moveDraw, { passive: false });
    canvas.addEventListener("touchend", endDraw);
    canvas.addEventListener("touchcancel", endDraw);
  }
  btnLimparAss?.addEventListener("click", clearSignature);

  function isBlankCanvas() {
    if (!canvas || !ctx) return true;
    const img = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
    for (let i = 3; i < img.length; i += 4) {
      if (img[i] !== 0) return false;
    }
    return true;
  }

  function gerarReciboHtml(payload) {
    const agora = new Date();
    const esc = escapeHtml;
    const assinaturaImg = payload.assinaturaDataUrl
      ? `<img src="${payload.assinaturaDataUrl}" alt="Assinatura" style="max-width: 520px; width: 100%; height: auto; display:block; margin-top:8px;" />`
      : "";
    const fotoImg = payload.fotoDataUrl
      ? `<div style="margin-top:10px;"><div style="font-size:12px;color:#555;margin-bottom:4px;">Foto (evidência)</div><img src="${payload.fotoDataUrl}" alt="Foto" style="max-width: 520px; width: 100%; height: auto; display:block; border:1px solid #ddd; border-radius:10px;" /></div>`
      : "";
    const evidencias = [
      payload.confirmadoEm ? `Confirmado em: ${esc(new Date(payload.confirmadoEm).toLocaleString("pt-BR"))}` : "",
      payload.ipPublico ? `IP: ${esc(payload.ipPublico)}` : "",
      payload.geo ? `Localização: ${esc(payload.geo)}` : "",
    ].filter(Boolean).join(" • ");

    return `<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Recibo - Ajuda de custo</title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; color:#111; margin: 24px; }
    .top { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; border-bottom:1px solid #ddd; padding-bottom:12px; margin-bottom:18px; }
    .brand { font-weight:700; font-size:18px; }
    .meta { font-size:12px; color:#444; text-align:right; }
    h1 { font-size:18px; margin: 0 0 10px; }
    .grid { display:grid; grid-template-columns: 1fr 1fr; gap: 10px 18px; margin-top: 8px; }
    .field { font-size: 13px; }
    .lbl { color:#555; font-size: 12px; }
    .val { font-weight: 600; }
    .box { border:1px solid #ddd; border-radius:10px; padding:12px; }
    .text { margin-top: 14px; font-size: 13px; line-height: 1.45; }
    .sign { margin-top: 22px; display:grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items:end; }
    .line { border-top: 1px solid #111; padding-top: 6px; font-size: 12px; color:#333; }
    @media print { body { margin: 10mm; } }
  </style>
</head>
<body>
  <div class="top">
    <div>
      <div class="brand">Grupo Sabor Paraense</div>
      <div style="font-size:12px;color:#444;margin-top:4px;">CNPJ: ${esc(payload.unidadeCnpj || "-")}</div>
    </div>
    <div class="meta">
      <div><strong>Gerado em:</strong> ${esc(agora.toLocaleString("pt-BR"))}</div>
      <div><strong>Unidade:</strong> ${esc(payload.unidadeNome || "-")}</div>
      <div><strong>Competência:</strong> ${esc(payload.competencia || "-")}</div>
    </div>
  </div>

  <h1>Recibo de ajuda de custo</h1>
  <div class="box">
    <div class="grid">
      <div class="field"><div class="lbl">Funcionário</div><div class="val">${esc(payload.funcionarioNome || "-")}</div></div>
      <div class="field"><div class="lbl">CPF</div><div class="val">${esc(payload.funcionarioCpf || "-")}</div></div>
      <div class="field"><div class="lbl">Valor</div><div class="val">${esc(payload.valorFmt || "R$ 0,00")}</div></div>
      <div class="field" style="grid-column:1 / -1;"><div class="lbl">Finalidade</div><div class="val">${esc(payload.finalidade || "-")}</div></div>
    </div>
    <div class="text">
      <strong>Declaro que recebi o valor acima e confirmo as informações.</strong>
      <br />
      Declaro, para os devidos fins, que recebi da empresa acima identificada o valor informado a título de <strong>ajuda de custo</strong>, referente à competência indicada.
    </div>
    ${evidencias ? `<div style="margin-top:12px;font-size:12px;color:#444;"><strong>Evidências:</strong> ${evidencias}</div>` : ""}
  </div>

  <div class="sign">
    <div>
      <div class="line">Assinatura do funcionário</div>
      ${assinaturaImg}
      ${fotoImg}
    </div>
    <div>
      <div class="line">Responsável</div>
    </div>
  </div>
</body>
</html>`;
  }

  function genCode6() {
    return String(Math.floor(100000 + Math.random() * 900000));
  }
  function persistConfirmCode(code, funcionarioNome) {
    try {
      localStorage.setItem(CONFIRM_CODE_STORAGE_KEY, JSON.stringify({
        code,
        funcionarioNome: funcionarioNome || "",
        createdAt: Date.now(),
      }));
    } catch (e) {}
  }
  function getPersistedConfirmCode() {
    try {
      const raw = localStorage.getItem(CONFIRM_CODE_STORAGE_KEY);
      const parsed = raw ? JSON.parse(raw) : null;
      if (!parsed || !parsed.code || !parsed.createdAt) return null;
      if (Date.now() - Number(parsed.createdAt) > CONFIRM_CODE_TTL_MS) return null;
      return parsed;
    } catch (e) {
      return null;
    }
  }

  function buildWhatsappLink(text, phone = "") {
    const t = encodeURIComponent(text || "");
    // se phone estiver vazio, abre só com texto (web/desktop)
    if (phone) return `https://wa.me/${encodeURIComponent(phone)}?text=${t}`;
    return `https://wa.me/?text=${t}`;
  }

  async function capturePublicIp() {
    try {
      const ctrl = new AbortController();
      const t = setTimeout(() => ctrl.abort(), 5500);
      const res = await fetch("https://api.ipify.org?format=json", { signal: ctrl.signal, cache: "no-store" });
      clearTimeout(t);
      const data = await res.json().catch(() => null);
      const ip = data?.ip ? String(data.ip) : "";
      if (!ip) throw new Error("IP indisponível");
      evidIpPublico = ip;
      updateEvidResumo();
      showToast("IP capturado.", "success");
    } catch (e) {
      showToast("Não foi possível capturar IP.", "warning");
    }
  }

  async function captureGeo() {
    if (!navigator.geolocation) return showToast("Geolocalização não suportada neste dispositivo.", "warning");
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        evidGeo = { lat: pos.coords.latitude, lng: pos.coords.longitude, acc: pos.coords.accuracy };
        updateEvidResumo();
        showToast("Localização capturada.", "success");
      },
      () => showToast("Permissão negada ou localização indisponível.", "warning"),
      { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
    );
  }

  function fileToDataUrl(file) {
    return new Promise((resolve, reject) => {
      const r = new FileReader();
      r.onload = () => resolve(String(r.result || ""));
      r.onerror = () => reject(new Error("Falha ao ler arquivo"));
      r.readAsDataURL(file);
    });
  }

  function confirmRequiredOk() {
    return !!confirmadoEmIso;
  }

  async function salvarReciboSomente() {
    try {
      setApiFeedback("", "info");
      const fid = funcionarioSelect?.value || "";
      const func = (state.funcionarios || []).find((f) => String(f.id) === String(fid));
      const uid = unidadeSelect?.value || "";
      const un = (state.unidades || []).find((u) => String(u.id) === String(uid));
      const comp = (competencia?.value || "").trim();
      const dtPag = (dataPagamento?.value || "").trim();
      const fin = (finalidadeSelect?.value || "").trim();
      const valorNum = Number(valor?.dataset?.value || 0);
      const tipoAss = getAssinaturaTipo();

      if (!fid) return showToast("Selecione o funcionário.", "warning");
      if (!uid) return showToast("Selecione a unidade.", "warning");
      if (!comp) return showToast("Informe a competência.", "warning");
      if (!fin) return showToast("Selecione a finalidade.", "warning");
      if (!Number.isFinite(valorNum) || valorNum <= 0) return showToast("Informe um valor válido.", "warning");
      if (tipoAss === "desenho") {
        if (!confirmRequiredOk()) return showToast("Confirme via WhatsApp para liberar a assinatura.", "warning");
        if (isBlankCanvas()) return showToast("Faça a assinatura antes de salvar.", "warning");
      }

      const assinaturaDataUrl = tipoAss === "desenho" && canvas?.toDataURL ? canvas.toDataURL("image/png") : null;
      const geoTxt = evidGeo?.lat && evidGeo?.lng ? `${evidGeo.lat.toFixed(5)}, ${evidGeo.lng.toFixed(5)} (±${Math.round(evidGeo.acc || 0)}m)` : "";

      const headers = { "Content-Type": "application/json", "X-Usuario-Id": String(currentUser?.id || ""), ...getDeviceHeaders() };
      if ((currentUser?.perfil || "").toString().trim().toUpperCase() === "ADMIN") headers["X-Debug"] = "1";
      const editing = (edicaoId?.value || "").trim();
      const body = {
        funcionario_id: fid,
        unidade_id: uid || null,
        competencia: comp,
        data_pagamento: dtPag || null,
        assinatura_tipo: tipoAss,
        finalidade: fin,
        valor: roundToCurrency(valorNum),
        confirmado_em: tipoAss === "desenho" ? confirmadoEmIso : null,
        ip_publico: evidIpPublico,
        geo: geoTxt,
        assinatura_data_url: assinaturaDataUrl,
        foto_data_url: null,
      };
      const url = editing ? `${API_URL}/recibos-ajuda/${encodeURIComponent(editing)}` : `${API_URL}/recibos-ajuda`;
      const method = editing ? "PUT" : "POST";
      const resSave = await fetch(url, { method, headers, body: JSON.stringify(body) });
      if (!resSave.ok) {
        const txt = await resSave.text().catch(() => "");
        let msg = txt;
        try { const j = JSON.parse(txt); if (j && typeof j === "object") msg = j.details || j.error || msg; } catch (e) {}
        throw new Error(msg || `Falha ao salvar recibo (HTTP ${resSave.status})`);
      }
      const saved = await resSave.json().catch(() => null);
      if (edicaoId && saved?.id) edicaoId.value = String(saved.id);
      await renderRecibosTabela();
      showToast("Recibo salvo.", "success");
      setApiFeedback("Salvo com sucesso no servidor.", "success");
    } catch (e) {
      showToast(e?.message || "Erro ao salvar recibo.", "error");
      setApiFeedback(`Falha ao salvar na API (${API_URL}): ${e?.message || "erro"}`, "error");
      console.error("ReciboAjuda: salvar falhou", e);
    }
  }

  confirmarSolicitarBtn?.addEventListener("click", () => {
    const fid = funcionarioSelect?.value || "";
    const func = (state.funcionarios || []).find((f) => String(f.id) === String(fid));
    const funcionarioNome = func?.nome_completo || func?.nome || "";
    const comp = (competencia?.value || "").trim();
    const fin = (finalidadeSelect?.value || "").trim();
    const valorNum = Number(valor?.dataset?.value || 0);
    if (!fid) return setFeedback(confirmarFeedback, "Selecione o funcionário antes de solicitar a confirmação.", "error");
    if (!comp) return setFeedback(confirmarFeedback, "Informe a competência antes de solicitar a confirmação.", "error");
    if (!fin) return setFeedback(confirmarFeedback, "Selecione a finalidade antes de solicitar a confirmação.", "error");
    if (!Number.isFinite(valorNum) || valorNum <= 0) return setFeedback(confirmarFeedback, "Informe um valor válido antes de solicitar a confirmação.", "error");

    const code = genCode6();
    persistConfirmCode(code, funcionarioNome);
    const msg = `CONFIRMAÇÃO DE RECIBO\n\nFuncionário: ${funcionarioNome}\nCompetência: ${comp}\nFinalidade: ${FINALIDADE_LABELS[fin] || fin}\nValor: ${formatCurrencyBRL(valorNum)}\n\nCódigo: ${code}\n\nResponda apenas com o código.`;
    if (confirmarWhatsappLink) {
      confirmarWhatsappLink.href = buildWhatsappLink(msg);
      confirmarWhatsappLink.classList.remove("hidden");
    }
    confirmarCodigoInput?.classList.remove("hidden");
    confirmarCodigoBtn?.classList.remove("hidden");
    setFeedback(confirmarFeedback, "Código gerado. Envie pelo WhatsApp e depois digite o código recebido para liberar a assinatura.", "info");
    setCanvasLocked(true);
  });

  confirmarCodigoBtn?.addEventListener("click", () => {
    const typed = (confirmarCodigoInput?.value || "").replace(/\D/g, "");
    if (typed.length !== 6) return setFeedback(confirmarFeedback, "Digite o código de 6 dígitos.", "error");
    const persisted = getPersistedConfirmCode();
    if (!persisted?.code) return setFeedback(confirmarFeedback, "Código expirado. Solicite novamente.", "error");
    if (typed !== String(persisted.code)) return setFeedback(confirmarFeedback, "Código inválido.", "error");

    confirmadoEmIso = new Date().toISOString();
    setCanvasLocked(false);
    setFeedback(confirmarFeedback, `Confirmação OK em ${new Date(confirmadoEmIso).toLocaleString("pt-BR")}. Assinatura liberada.`, "success");
    updateEvidResumo();
  });

  capturarIpBtn?.addEventListener("click", capturePublicIp);
  capturarLocalBtn?.addEventListener("click", captureGeo);
  // Foto removida (evidência opcional) por solicitação.

  // Geração/visualização do PDF agora acontece no botão "Ver" (modal).

  btnSalvar?.addEventListener("click", salvarReciboSomente);
  btnLimpar?.addEventListener("click", () => {
    if (edicaoId) edicaoId.value = "";
    if (funcionarioBusca) funcionarioBusca.value = "";
    if (funcionarioSelect) funcionarioSelect.value = "";
    if (funcionarioCpf) funcionarioCpf.value = "";
    if (unidadeSelect) unidadeSelect.value = "";
    if (unidadeCnpj) unidadeCnpj.value = "";
    if (competencia) competencia.value = new Date().toISOString().slice(0, 7);
    if (dataPagamento) dataPagamento.value = "";
    if (finalidadeSelect) finalidadeSelect.value = "";
    if (assinaturaTipo) assinaturaTipo.value = "desenho";
    if (valor) { valor.value = ""; valor.dataset.value = "0"; }
    clearSignature();
    confirmadoEmIso = "";
    evidIpPublico = "";
    evidGeo = null;
    setCanvasLocked(true);
    setFeedback(confirmarFeedback, "Confirme via WhatsApp para liberar a assinatura.", "info");
    if (confirmarCodigoInput) { confirmarCodigoInput.value = ""; confirmarCodigoInput.classList.add("hidden"); }
    confirmarCodigoBtn?.classList.add("hidden");
    confirmarWhatsappLink?.classList.add("hidden");
    updateEvidResumo();
    filterFuncionarioSelect();
    applyAssinaturaTipoUI();
  });

  // ações da tabela
  section.querySelector(".recibo-ajuda__historico")?.addEventListener("click", (e) => {
    const ver = e.target.closest("[data-reciboajuda-ver]");
    const del = e.target.closest("[data-reciboajuda-del]");
    const id = ver?.getAttribute("data-reciboajuda-ver") || del?.getAttribute("data-reciboajuda-del");
    if (!id) return;
    if (ver) {
      (async () => {
        try {
          await abrirReciboAjudaPdfModal(id);
        } catch (e) {
          showToast("Não foi possível abrir o PDF.", "error");
        }
      })();
      return;
    }

    if (del) {
      (async () => {
        if (!window.confirm(`Deletar o recibo #${id}?`)) return;
        try {
          const headers = { "Content-Type": "application/json", "X-Usuario-Id": String(currentUser?.id || ""), ...getDeviceHeaders() };
          const res = await fetch(`${API_URL}/recibos-ajuda/${encodeURIComponent(String(id))}`, { method: "DELETE", headers });
          if (!res.ok) throw new Error("Falha ao deletar");
          if ((edicaoId?.value || "").trim() === String(id)) edicaoId.value = "";
          await renderRecibosTabela();
          showToast("Recibo deletado.", "success");
        } catch (e) {
          showToast("Não foi possível deletar.", "error");
        }
      })();
    }
  });
}

// Formata data para input type="date" (YYYY-MM-DD)
function formatDateForInput(dateStr) {
  if (!dateStr) return '';
  const s = String(dateStr).trim();
  const match = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (match) return match[1] + '-' + match[2] + '-' + match[3];
  try {
    const d = new Date(s);
    if (isNaN(d.getTime())) return '';
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  } catch {
    return '';
  }
}

// Função para editar boleto
async function editarBoleto(id) {
  console.log('✏️ Editando boleto:', id);
  
  try {
    showToast('Carregando dados do boleto...', 'info');
    
    await populateBoletosUnidades();
    
    const response = await fetch(`${API_URL}/boletos/${id}`, {
      headers: {
        'Content-Type': 'application/json',
        'X-Usuario-Id': currentUser?.id || '1'
      }
    });
    
    if (!response.ok) throw new Error('Erro ao carregar boleto');
    
    const boleto = await response.json();
    console.log('Boleto carregado:', boleto);
    
    // Preenche o formulário
    const form = document.getElementById('boletoForm');
    if (form) form.dataset.mode = 'edit';
    form.querySelector('[name="id"]').value = boleto.id;
    form.querySelector('[name="fornecedor"]').value = boleto.fornecedor || '';
    form.querySelector('[name="unidade_id"]').value = boleto.unidade_id || '';
    form.querySelector('[name="descricao"]').value = boleto.descricao || '';
    form.querySelector('[name="data_vencimento"]').value = formatDateForInput(boleto.data_vencimento);
    const valorInput = form.querySelector('[name="valor"]');
    valorInput.dataset.value = String(boleto.valor || 0);
    valorInput.value = boleto.valor ? formatCurrencyBRL(parseFloat(boleto.valor)) : '';
    form.querySelector('[name="categoria"]').value = boleto.categoria || '';
    form.querySelector('[name="numero_boleto"]').value = boleto.numero_boleto || '';
    form.querySelector('[name="nome_pagador"]').value = boleto.nome_pagador || '';
    form.querySelector('[name="whatsapp_pagador"]').value = boleto.whatsapp_pagador || '';
    form.querySelector('[name="status"]').value = boleto.status;
    form.querySelector('[name="observacoes"]').value = boleto.observacoes || '';
    
    // Se tiver dados de pagamento
    const valorPagoInput = form.querySelector('[name="valor_pago"]');
    if (boleto.data_pagamento) {
      form.querySelector('[name="data_pagamento"]').value = formatDateForInput(boleto.data_pagamento);
      valorPagoInput.dataset.value = String(boleto.valor_pago || 0);
      valorPagoInput.value = boleto.valor_pago ? formatCurrencyBRL(parseFloat(boleto.valor_pago)) : '';
      form.querySelector('[name="juros_multa"]').value = boleto.juros_multa || '';
      document.getElementById('pagamentoFields').style.display = 'block';
    } else {
      form.querySelector('[name="data_pagamento"]').value = '';
      valorPagoInput.dataset.value = '0';
      valorPagoInput.value = '';
      form.querySelector('[name="juros_multa"]').value = '';
      document.getElementById('pagamentoFields').style.display = 'none';
    }
    
    const recorrenteFields = document.getElementById('recorrenteFields');
    const boletoRecorrente = document.getElementById('boletoRecorrente');
    if (recorrenteFields) recorrenteFields.style.display = 'none';
    if (boletoRecorrente) boletoRecorrente.checked = false;
    
    // Atualiza título e abre modal
    document.getElementById('boletoModalTitle').textContent = '✏️ Editar Boleto';
    document.getElementById('boletoModal').classList.add('active');
    
    showToast('Boleto carregado para edição', 'success');
    
  } catch (error) {
    console.error('Erro ao editar boleto:', error);
    showToast('Erro ao carregar boleto', 'error');
  }
}

// Função para mostrar detalhes do boleto
async function mostrarDetalhesBoleto(id) {
  console.log('👁️ Mostrando detalhes do boleto:', id);
  
  try {
    const modal = document.getElementById('boletoDetalhesModal');
    const content = document.getElementById('boletoDetalhesContent');
    
    content.innerHTML = '<p style="text-align: center; color: #999;">⏳ Carregando...</p>';
    modal.classList.add('active');
    
    const response = await fetch(`${API_URL}/boletos/${id}`, {
      headers: {
        'Content-Type': 'application/json',
        'X-Usuario-Id': currentUser?.id || '1'
      }
    });
    
    if (!response.ok) throw new Error('Erro ao carregar boleto');
    
    const boleto = await response.json();
    console.log('Detalhes do boleto:', boleto);
    
    const valorJuros = parseFloat(boleto.juros_multa || 0);
    let statusLabel, statusColor;
    if (boleto.status === 'PAGO') {
      statusLabel = valorJuros > 0 ? 'Pago com atraso' : 'Pago';
      statusColor = valorJuros > 0 ? '#ff9800' : '#4CAF50';
    } else if (boleto.status === 'CANCELADO') {
      statusLabel = 'Cancelado';
      statusColor = '#9E9E9E';
    } else {
      const hoje = new Date();
      hoje.setHours(0, 0, 0, 0);
      const venc = new Date(boleto.data_vencimento);
      venc.setHours(0, 0, 0, 0);
      const diff = Math.ceil((venc - hoje) / (1000 * 60 * 60 * 24));
      if (diff < 0) {
        statusLabel = 'Atrasado';
        statusColor = '#f44336';
      } else {
        statusLabel = 'A vencer';
        statusColor = '#2196F3';
      }
    }
    
    let html = `
      <div style="display: grid; gap: 1rem;">
        <div style="background: #f5f5f5; padding: 1rem; border-radius: 8px;">
          <h3 style="margin: 0 0 1rem 0; color: #333; border-bottom: 2px solid #ddd; padding-bottom: 0.5rem;">
            📋 Informações Gerais
          </h3>
          <p style="margin: 0.5rem 0;"><strong>ID:</strong> #${boleto.id}</p>
          <p style="margin: 0.5rem 0;"><strong>Fornecedor:</strong> ${boleto.fornecedor}</p>
          <p style="margin: 0.5rem 0;"><strong>Descrição:</strong> ${boleto.descricao || '-'}</p>
          <p style="margin: 0.5rem 0;"><strong>Categoria:</strong> ${boleto.categoria || '-'}</p>
          <p style="margin: 0.5rem 0;"><strong>Número do Boleto:</strong> ${boleto.numero_boleto || '-'}</p>
          <p style="margin: 0.5rem 0;"><strong>Nome do Pagador:</strong> ${boleto.nome_pagador || '-'}</p>
          <p style="margin: 0.5rem 0;"><strong>WhatsApp:</strong> ${boleto.whatsapp_pagador || '-'}</p>
          <p style="margin: 0.5rem 0;">
            <strong>Status:</strong> 
            <span style="background: ${statusColor}; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.9rem;">
              ${statusLabel}
            </span>
          </p>
        </div>
        
        <div style="background: #e3f2fd; padding: 1rem; border-radius: 8px;">
          <h3 style="margin: 0 0 1rem 0; color: #1976d2; border-bottom: 2px solid #90caf9; padding-bottom: 0.5rem;">
            💰 Valores
          </h3>
          <p style="margin: 0.5rem 0;"><strong>Valor:</strong> <span style="font-size: 1.2rem; color: #1976d2;">R$ ${parseFloat(boleto.valor).toFixed(2)}</span></p>
          <p style="margin: 0.5rem 0;"><strong>Data de Vencimento:</strong> ${formatDate(boleto.data_vencimento)}</p>
    `;
    
    if (boleto.status === 'PAGO') {
      html += `
          <p style="margin: 0.5rem 0;"><strong>Data de Pagamento:</strong> ${formatDate(boleto.data_pagamento)}</p>
          <p style="margin: 0.5rem 0;"><strong>Valor Pago:</strong> R$ ${parseFloat(boleto.valor_pago || 0).toFixed(2)}</p>
          <p style="margin: 0.5rem 0;"><strong>Juros/Multa:</strong> R$ ${valorJuros.toFixed(2)}</p>
      `;
    }
    
    html += `</div>`;
    
    if (boleto.observacoes) {
      html += `
        <div style="background: #fff3e0; padding: 1rem; border-radius: 8px;">
          <h3 style="margin: 0 0 0.5rem 0; color: #f57c00;">📝 Observações</h3>
          <p style="margin: 0; white-space: pre-wrap;">${boleto.observacoes}</p>
        </div>
      `;
    }
    
    if (boleto.anexo_path) {
      html += `
        <div style="background: #e8f5e9; padding: 1rem; border-radius: 8px;">
          <h3 style="margin: 0 0 0.5rem 0; color: #388e3c;">📎 Anexo</h3>
          <p style="margin: 0;">
            <a href="${API_URL}/boletos/${boleto.id}/anexo" target="_blank" style="color: #388e3c; text-decoration: underline;">
              ${boleto.anexo_nome || 'Download'}
            </a>
          </p>
        </div>
      `;
    }
    
    if (boleto.is_recorrente) {
      html += `
        <div style="background: #f3e5f5; padding: 1rem; border-radius: 8px;">
          <h3 style="margin: 0 0 0.5rem 0; color: #7b1fa2;">🔄 Recorrência</h3>
          <p style="margin: 0;">Este boleto é recorrente por ${boleto.meses_recorrencia} meses</p>
        </div>
      `;
    }
    
    let textZap = `Olá`;
    if (boleto.nome_pagador) textZap += ` ${boleto.nome_pagador}`;
    textZap += `! Segue as informações do seu boleto.\n\n`;
    if (boleto.fornecedor) textZap += `🏢 *Fornecedor:* ${boleto.fornecedor}\n`;
    if (boleto.categoria) textZap += `📂 *Categoria:* ${boleto.categoria}\n`;
    if (boleto.fornecedor || boleto.categoria) textZap += `\n`;
    if (boleto.numero_boleto) textZap += `🔢 *Número do Boleto:*\n${boleto.numero_boleto}\n\n`;
    textZap += `📅 *Vencimento:* ${formatDate(boleto.data_vencimento)}\n`;
    textZap += `💰 *Valor:* R$ ${parseFloat(boleto.valor).toFixed(2)}\n\n`;
    if (boleto.anexo_path) {
      // Usa exatamente a mesma lógica que o botão de anexo que funciona na tela usa (API_URL)
      const baseUrl = API_URL.startsWith('http') ? API_URL : window.location.origin + API_URL;
      textZap += `⬇️ *Baixar PDF do Boleto:*\n${baseUrl}/boletos/${boleto.id}/anexo\n\n`;
    }
    
    textZap += `Após o pagamento, por favor, nos envie o comprovante por aqui para que possamos dar baixa no sistema. Obrigado! 🤝`;
    
    let zapLink = `https://wa.me/`;
    if (boleto.whatsapp_pagador) {
        let numeroLimpo = boleto.whatsapp_pagador.replace(/\D/g, '');
        if (numeroLimpo.length === 10 || numeroLimpo.length === 11) {
            numeroLimpo = '55' + numeroLimpo;
        }
        zapLink += `${numeroLimpo}`;
    }
    zapLink += `?text=${encodeURIComponent(textZap)}`;

    html += `
        <div style="background: #e8f5e9; padding: 1rem; border-radius: 8px; text-align: center;">
          <a href="${zapLink}" target="_blank" style="display: inline-block; background: #25D366; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: bold; width: 100%; box-sizing: border-box;">
            📱 Enviar para o WhatsApp
          </a>
        </div>
    `;
    
    html += `
        <div style="background: #f5f5f5; padding: 1rem; border-radius: 8px; font-size: 0.85rem; color: #666;">
          <p style="margin: 0;"><strong>Cadastrado em:</strong> ${formatDate(boleto.created_at)}</p>
          <p style="margin: 0.5rem 0 0 0;"><strong>Última atualização:</strong> ${formatDate(boleto.updated_at)}</p>
        </div>
      </div>
    `;
    
    content.innerHTML = html;
    
  } catch (error) {
    console.error('Erro ao mostrar detalhes:', error);
    content.innerHTML = '<p style="text-align: center; color: #f44336;">❌ Erro ao carregar detalhes</p>';
  }
}

// Função para abrir modal de pagamento
async function abrirModalPagamento(id) {
  console.log('💳 Abrindo modal de pagamento para boleto:', id);
  
  try {
    showToast('Carregando dados do boleto...', 'info');
    
    const response = await fetch(`${API_URL}/boletos/${id}`, {
      headers: {
        'Content-Type': 'application/json',
        'X-Usuario-Id': currentUser?.id || '1'
      }
    });
    
    if (!response.ok) throw new Error('Erro ao carregar boleto');
    
    const boleto = await response.json();
    console.log('Boleto para pagamento:', boleto);
    
    // Preenche os dados no modal
    document.getElementById('pagamentoBoletoId').value = boleto.id;
    document.getElementById('pagamentoFornecedor').textContent = boleto.fornecedor;
    document.getElementById('pagamentoDescricao').textContent = boleto.descricao;
    document.getElementById('pagamentoValorOriginal').textContent = parseFloat(boleto.valor).toFixed(2);
    
    // Preenche data de hoje e valor original
    document.getElementById('pagamentoData').value = new Date().toISOString().split('T')[0];
    document.getElementById('pagamentoValor').value = boleto.valor;
    document.getElementById('pagamentoJuros').value = '0.00';
    document.getElementById('pagamentoObs').value = '';
    
    // Guarda o valor original para cálculo automático de juros
    const pagamentoValorInput = document.getElementById('pagamentoValor');
    const pagamentoJurosInput = document.getElementById('pagamentoJuros');
    
    // Remove listener antigo se existir
    const newPagamentoValorInput = pagamentoValorInput.cloneNode(true);
    pagamentoValorInput.parentNode.replaceChild(newPagamentoValorInput, pagamentoValorInput);
    
    // Adiciona listener para calcular juros automaticamente
    newPagamentoValorInput.addEventListener('input', function() {
      const valorPago = parseFloat(this.value) || 0;
      const valorOriginal = parseFloat(boleto.valor) || 0;
      const juros = Math.max(0, valorPago - valorOriginal); // Não permite juros negativos
      
      const jurosInput = document.getElementById('pagamentoJuros');
      if (jurosInput) {
        jurosInput.value = juros.toFixed(2);
        
        // Feedback visual
        if (juros > 0) {
          console.log(`💸 Juros calculados: R$ ${juros.toFixed(2)} (Valor Pago: R$ ${valorPago.toFixed(2)} - Valor Original: R$ ${valorOriginal.toFixed(2)})`);
        } else {
          console.log('✅ Pago sem juros');
        }
      }
    });
    
    // Abre modal
    document.getElementById('boletoPagamentoModal').classList.add('active');
    
  } catch (error) {
    console.error('Erro ao abrir modal de pagamento:', error);
    showToast('Erro ao carregar boleto', 'error');
  }
}

// Event listeners para fechar modais
document.getElementById('closeBoletoDetalhes')?.addEventListener('click', () => {
  document.getElementById('boletoDetalhesModal').classList.remove('active');
});

document.getElementById('fecharDetalhes')?.addEventListener('click', () => {
  document.getElementById('boletoDetalhesModal').classList.remove('active');
});

document.getElementById('closeBoletoPagamento')?.addEventListener('click', () => {
  document.getElementById('boletoPagamentoModal').classList.remove('active');
});

document.getElementById('cancelPagamento')?.addEventListener('click', () => {
  document.getElementById('boletoPagamentoModal').classList.remove('active');
});

// Fechar modal ao clicar fora
document.getElementById('boletoDetalhesModal')?.addEventListener('click', (e) => {
  if (e.target.id === 'boletoDetalhesModal') {
    e.target.classList.remove('active');
  }
});

document.getElementById('boletoPagamentoModal')?.addEventListener('click', (e) => {
  if (e.target.id === 'boletoPagamentoModal') {
    e.target.classList.remove('active');
  }
});

// Form de pagamento
document.getElementById('boletoPagamentoForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  console.log('💳 Processando pagamento...');
  
  const submitBtn = e.target.querySelector('button[type="submit"]');
  const originalText = submitBtn.textContent;
  
  try {
    submitBtn.disabled = true;
    submitBtn.textContent = 'Processando...';
    
    const formData = new FormData(e.target);
    const id = formData.get('id');
    
    // Prepara dados
    const data = {
      status: 'PAGO',
      data_pagamento: formData.get('data_pagamento'),
      valor_pago: formData.get('valor_pago'),
      juros_multa: formData.get('juros_multa') || '0',
      observacoes: formData.get('observacoes') || ''
    };
    
    console.log('Dados do pagamento:', data);
    
    const response = await fetch(`${API_URL}/boletos/${id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'X-Usuario-Id': currentUser?.id || '1'
      },
      body: JSON.stringify(data)
    });
    
    if (!response.ok) {
      const errorText = await response.text();
      throw new Error(`Erro ao registrar pagamento: ${errorText}`);
    }
    
    const result = await response.json();
    console.log('✅ Pagamento registrado:', result);
    
    showToast('✅ Pagamento registrado com sucesso!', 'success');
    
    // Fecha modal
    document.getElementById('boletoPagamentoModal').classList.remove('active');
    e.target.reset();
    
    // Recarrega boletos
    await loadBoletos({});
    await loadBoletosResumo();
    
  } catch (error) {
    console.error('❌ Erro ao registrar pagamento:', error);
    showToast('Erro ao registrar pagamento: ' + error.message, 'error');
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = originalText;
  }
});

// Atualizar boleto (para edição)
async function atualizarBoleto(id, data) {
  try {
    const response = await fetch(`${API_URL}/boletos/${id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'X-Usuario-Id': currentUser?.id || '1'
      },
      body: JSON.stringify(data)
    });
    
    if (!response.ok) throw new Error('Erro ao atualizar boleto');
    
    return await response.json();
  } catch (error) {
    console.error('Erro ao atualizar boleto:', error);
    throw error;
  }
}

// Registra ouvintes de formulario e ganchos para sincronizar UI e backend.
function setupForms() {
  if (dom.entradaSubmitBtn) {
    dom.entradaSubmitBtn.classList.remove("primary");
    dom.entradaSubmitBtn.classList.add("danger");
    dom.entradaSubmitBtn.textContent = "Registrar entrada";
  }
  if (dom.cancelEntradaBtn) {
    dom.cancelEntradaBtn.classList.remove("primary", "danger");
    dom.cancelEntradaBtn.classList.add("neutral");
  }

  dom.entradaUnidadeSelect?.addEventListener("change", handleEntradaUnidadeChange);
  if (Number(dom.entradaUnidadeSelect?.value)) {
    handleEntradaUnidadeChange();
  } else {
    resetEntradaLocalSelect();
    ensureLocaisCarregados()
      .then(() => handleEntradaUnidadeChange())
      .catch(() => {
        resetEntradaLocalSelect();
      });
  }
  dom.entradaLocalSelect?.addEventListener("change", (event) => {
    const option = event.target.selectedOptions ? event.target.selectedOptions[0] : null;
    const unidadeId = option?.dataset?.unidadeId;
    if (unidadeId && dom.entradaUnidadeSelect) {
      dom.entradaUnidadeSelect.value = unidadeId;
    }
  });

  attachCurrencyMask(dom.entradaForm?.elements.custo_unitario);
  attachCurrencyMask(dom.loteForm?.elements.custo_unitario);
  const boletoFormEl = document.getElementById('boletoForm');
  if (boletoFormEl) {
    attachCurrencyMask(boletoFormEl.querySelector('[name="valor"]'));
    attachCurrencyMask(boletoFormEl.querySelector('[name="valor_pago"]'));
  }

  // Setup de formulários com logs
  console.log('🔧 Configurando event listeners de formulários...');
  
  if (dom.produtosForm) {
    dom.produtosForm.addEventListener("submit", (e) => {
      e.preventDefault();
      e.stopPropagation();
      submitProduto(e).catch(err => {
        console.error("Erro ao salvar produto:", err);
        showToast(err.message || "Falha ao salvar produto.", "error");
      });
      return false;
    });
    const produtoSalvarBtn = dom.produtosForm.querySelector('button[type="submit"]');
    if (produtoSalvarBtn) {
      produtoSalvarBtn.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (dom.produtosForm.checkValidity()) {
          submitProduto(e).catch(err => {
            console.error("Erro ao salvar produto:", err);
            showToast(err.message || "Falha ao salvar produto.", "error");
          });
        } else {
          dom.produtosForm.reportValidity();
        }
        return false;
      });
    }
  }
  
  dom.unidadeForm?.addEventListener("submit", (event) => submitUnidade(event, dom.unidadeForm));
  dom.unidadeInlineForm?.addEventListener("submit", (event) => submitUnidade(event, dom.unidadeInlineForm));

  document.getElementById("minhaContaForm")?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const form = e.target;
    const senhaAtual = form.elements.senha_atual?.value?.trim();
    const novaSenha = form.elements.nova_senha?.value?.trim();
    const confirmarSenha = form.elements.confirmar_senha?.value?.trim();
    if (!senhaAtual) { showToast("Digite sua senha atual.", "error"); return; }
    if (!novaSenha || novaSenha.length < 6) { showToast("A nova senha deve ter no mínimo 6 caracteres.", "error"); return; }
    if (novaSenha !== confirmarSenha) { showToast("A confirmação da senha não confere.", "error"); return; }
    try {
      await fetchJSON("/usuarios/me/senha", {
        method: "PUT",
        body: JSON.stringify({ senha_atual: senhaAtual, nova_senha: novaSenha, confirmar_senha: confirmarSenha }),
      });
      showToast("Senha alterada com sucesso!", "success");
      form.reset();
    } catch (err) {
      showToast(err.message || "Falha ao alterar senha.", "error");
    }
    return false;
  });

  if (dom.usuarioForm) {
    dom.usuarioForm.addEventListener("submit", (e) => {
      e.preventDefault();
      e.stopPropagation();
      submitUsuario(e).catch(err => {
        console.error("Erro ao salvar usuário:", err);
        showToast(err.message || "Falha ao salvar usuário.", "error");
      });
      return false;
    });
    const usuarioSalvarBtn = dom.usuarioForm.querySelector('button[type="submit"]');
    if (usuarioSalvarBtn) {
      usuarioSalvarBtn.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (dom.usuarioForm.checkValidity()) {
          submitUsuario(e).catch(err => {
            console.error("Erro ao salvar usuário:", err);
            showToast(err.message || "Falha ao salvar usuário.", "error");
          });
        } else {
          dom.usuarioForm.reportValidity();
        }
        return false;
      });
    }
  }
  dom.entradaForm?.addEventListener("submit", submitEntrada);
  if (dom.localForm) {
    dom.localForm.onsubmit = (e) => {
      e.preventDefault();
      e.stopPropagation();
      submitLocal(e).catch(err => {
        showToast(err?.message || "Falha ao salvar local.", "error");
      });
      return false;
    };
    dom.localForm.addEventListener("submit", (e) => {
      e.preventDefault();
      e.stopPropagation();
      return false;
    });
  }
  dom.loteForm?.addEventListener("submit", submitLote);
  // Event listener para submit do formulário de saída
  if (dom.saidaForm) {
    // Anexa event listener de submit no formulário
    dom.saidaForm.onsubmit = (e) => {
      console.log("📝 Evento submit capturado (onsubmit)");
      e.preventDefault();
      e.stopPropagation();
      submitSaida(e).catch(err => {
        console.error("Erro ao processar submit:", err);
        showToast(err.message || "Erro ao registrar saída", "error");
      });
      return false;
    };
    console.log("✅ Handler onsubmit anexado ao formulário de saída");
    
    // Anexa também addEventListener como fallback
    dom.saidaForm.addEventListener("submit", (e) => {
      console.log("📝 Evento submit capturado (addEventListener)");
      e.preventDefault();
      e.stopPropagation();
      submitSaida(e).catch(err => {
        console.error("Erro ao processar submit:", err);
        showToast(err.message || "Erro ao registrar saída", "error");
      });
    });
    
    // Fallback: também anexa ao botão de submit diretamente
    const submitBtn = dom.saidaForm.querySelector('button[type="submit"]');
    if (submitBtn) {
      // Usa onclick direto
      submitBtn.onclick = (e) => {
        console.log("🔘 Botão clicado (onclick direto)");
        e.preventDefault();
        e.stopPropagation();
        submitSaida(e).catch(err => {
          console.error("Erro ao processar:", err);
          showToast(err.message || "Erro ao registrar saída", "error");
        });
      };
      
      // Também adiciona addEventListener como backup
      submitBtn.addEventListener("click", (e) => {
        console.log("🔘 Botão clicado (addEventListener)");
        e.preventDefault();
        e.stopPropagation();
        submitSaida(e).catch(err => {
          console.error("Erro ao processar:", err);
          showToast(err.message || "Erro ao registrar saída", "error");
        });
      });
      console.log("✅ Handlers anexados ao botão de submit");
    } else {
      console.warn("⚠️ Botão de submit não encontrado no formulário de saída");
    }
    
    // Expõe função globalmente como fallback
    window.submitSaida = submitSaida;
    console.log("✅ Função submitSaida exposta globalmente");
  } else {
    console.error("❌ Formulário de saída não encontrado na inicialização!");
  }
  dom.listaCompraForm?.addEventListener("submit", submitListaCompra);
  dom.itemCompraForm?.addEventListener("submit", submitItemCompra);
  dom.estabelecimentoCompraForm?.addEventListener("submit", submitEstabelecimentoCompra);
  dom.finalizarListaForm?.addEventListener("submit", submitFinalizarLista);

  if (dom.loteForm) {
    resetLoteProdutoSelect();
    dom.loteForm.addEventListener("reset", () => resetLoteProdutoSelect());
    const loteUnidadeSelect = dom.loteForm.elements?.unidade_id;
    loteUnidadeSelect?.addEventListener("change", () => {
      handleLoteUnidadeChange();
    });
    if (Number(loteUnidadeSelect?.value)) {
      handleLoteUnidadeChange();
    }
  }

  attachCnpjMask(dom.unidadeForm?.elements.cnpj);
  attachCnpjMask(dom.unidadeInlineForm?.elements.cnpj);
  updateSaidaDestinoVisibility();
  resetSaidaProdutoSelect();
  dom.saidaMotivo?.addEventListener("change", updateSaidaDestinoVisibility);
  dom.saidaForm?.addEventListener("reset", () => {
    updateSaidaDestinoVisibility();
    resetSaidaProdutoSelect();
    if (dom.saidaLoteWrapper) dom.saidaLoteWrapper.classList.add("hidden");
    if (dom.saidaLoteManualWrapper) dom.saidaLoteManualWrapper.classList.add("hidden");
    if (dom.saidaLoteManualInput) dom.saidaLoteManualInput.value = "";
  });
  dom.saidaOrigemSelect?.addEventListener("change", () => {
    handleSaidaOrigemChange();
    // Resetar lote ao trocar unidade
    if (dom.saidaLoteWrapper) dom.saidaLoteWrapper.classList.add("hidden");
    if (dom.saidaLoteManualWrapper) dom.saidaLoteManualWrapper.classList.add("hidden");
    if (dom.saidaLoteManualInput) dom.saidaLoteManualInput.value = "";
  });
  if (Number(dom.saidaOrigemSelect?.value)) {
    handleSaidaOrigemChange();
  }

  // Ao trocar produto, carrega lotes disponíveis
  dom.saidaProdutoSelect?.addEventListener("change", () => {
    handleSaidaProdutoChange();
  });

  // Ao trocar opção no select de lote, mostra/esconde input manual
  dom.saidaLoteSelect?.addEventListener("change", () => {
    const isManual = dom.saidaLoteSelect.value === "__manual__";
    if (dom.saidaLoteManualWrapper) {
      dom.saidaLoteManualWrapper.classList.toggle("hidden", !isManual);
    }
    if (!isManual && dom.saidaLoteManualInput) {
      dom.saidaLoteManualInput.value = "";
    }
  });

  dom.usuarioFotoInput?.addEventListener("change", (event) => {
    const [file] = event.target.files || [];
    if (!file) return;
    if (!["image/png", "image/jpeg"].includes(file.type)) {
      showToast("Formato invalido. Use JPG ou PNG.", "error");
      event.target.value = "";
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      showToast("Arquivo ultrapassa 2MB.", "error");
      event.target.value = "";
      return;
    }
    usuarioFotoFile = file;
    usuarioFotoRemovida = false;
    const reader = new FileReader();
    reader.onload = () => {
      if (dom.usuarioAvatarPreview) dom.usuarioAvatarPreview.innerHTML = `<img src="${reader.result}" alt="preview" />`;
    };
    reader.readAsDataURL(file);
  });

  dom.usuarioFotoTrocar?.addEventListener("click", () => dom.usuarioFotoInput?.click());
  dom.usuarioFotoRemover?.addEventListener("click", () => {
    usuarioFotoFile = null;
    usuarioFotoRemovida = true;
    if (dom.usuarioAvatarPreview) dom.usuarioAvatarPreview.innerHTML = '<span class="avatar-placeholder">?</span>';
  });

  // Configura event listener do select de estoque
  const estoqueProdutoSelect = document.getElementById("estoqueProdutoSelect");
  if (estoqueProdutoSelect) {
    estoqueProdutoSelect.addEventListener("change", (event) => {
      const produtoId = Number(event.target.value);
      if (Number.isFinite(produtoId) && produtoId > 0) {
        loadEstoqueProduto(produtoId);
      } else {
        const estoqueInfo = document.getElementById("estoqueInfo");
        if (estoqueInfo) estoqueInfo.style.display = "none";
      }
    });
  }

  // Select de unidade no resumo de estoque
  const estoqueResumoUnidadeEl = document.getElementById("estoqueResumoUnidade");
  if (estoqueResumoUnidadeEl) {
    estoqueResumoUnidadeEl.addEventListener("change", () => loadEstoqueResumo());
  }

  // Fechar modal de detalhes de lotes
  document.getElementById('closeEstoqueLotesModal')?.addEventListener('click', () => {
    toggleModal(document.getElementById('estoqueLotesModal'), false);
  });

}

/** Normaliza minutos ≥ 60 para horas (ficha técnica — tempo de preparo). */
function normalizarFichaTecnicaHorasMinutos(horas, minutos) {
  let h = Math.max(0, Math.floor(Number(horas) || 0));
  let m = Math.max(0, Math.floor(Number(minutos) || 0));
  h += Math.floor(m / 60);
  m = m % 60;
  return { h, m };
}

/** Texto legível em horas/minutos para armazenar e exibir nas listas. */
function formatarFichaTecnicaTempoPreparo(horas, minutos) {
  const { h, m } = normalizarFichaTecnicaHorasMinutos(horas, minutos);
  if (h === 0 && m === 0) return '';
  if (h === 0) return m === 1 ? '1 minuto' : `${m} minutos`;
  if (m === 0) return h === 1 ? '1 hora' : `${h} horas`;
  return `${h} h ${m} min`;
}

/** Interpreta texto antigo ou novo (ex.: "1h 30min", "1 hora", "45 minutos", "2:15"). */
function parseFichaTecnicaTempoPreparo(str) {
  if (str == null || !String(str).trim()) return { h: 0, m: 0 };
  const s = String(str).trim();
  const hMatch = s.match(/(\d+)\s*(?:h\b|hora(?:s)?\b)/i);
  const mMatch = s.match(/(\d+)\s*m(?:in(?:uto)?s?)?\b/i);
  let h = 0;
  let m = 0;
  if (hMatch) h = parseInt(hMatch[1], 10) || 0;
  if (mMatch) m = parseInt(mMatch[1], 10) || 0;
  if (!hMatch && !mMatch) {
    const colon = s.match(/^(\d{1,3})\s*:\s*(\d{1,2})$/);
    if (colon) {
      h = parseInt(colon[1], 10) || 0;
      m = parseInt(colon[2], 10) || 0;
      return normalizarFichaTecnicaHorasMinutos(h, m);
    }
    const only = s.match(/^(\d+)$/);
    if (only) {
      const n = parseInt(only[1], 10);
      if (n >= 60) {
        h = Math.floor(n / 60);
        m = n % 60;
      } else {
        m = n;
      }
    }
  }
  return normalizarFichaTecnicaHorasMinutos(h, m);
}

/** Sanitiza HTML do modo de preparo (ficha técnica). */
function sanitizeFichaTecnicaModoPreparoHtml(html) {
  const s = String(html ?? '').trim();
  if (!s) return '';
  if (typeof window.DOMPurify !== 'undefined') {
    return window.DOMPurify.sanitize(s, {
      ALLOWED_TAGS: [
        'p',
        'div',
        'br',
        'strong',
        'b',
        'em',
        'i',
        'u',
        'span',
        'ul',
        'ol',
        'li',
        'font',
        'h3',
        'h4',
        'blockquote',
        'mark',
      ],
      ALLOWED_ATTR: ['style', 'color', 'size', 'class', 'face'],
      ALLOW_DATA_ATTR: false,
    });
  }
  const el = document.createElement('div');
  el.textContent = s.replace(/<[^>]*>/g, '');
  return el.innerHTML;
}

/** Texto visível no editor (ignora só <br> / blocos vazios do navegador). */
function fichaTecnicaModoPreparoTextoVisivel(htmlOrEl) {
  if (htmlOrEl && typeof htmlOrEl === 'object' && htmlOrEl.nodeType === Node.ELEMENT_NODE) {
    const t = (htmlOrEl.innerText || htmlOrEl.textContent || '').replace(/\u200b/g, '');
    return t.trim().length > 0;
  }
  const div = document.createElement('div');
  div.innerHTML = String(htmlOrEl ?? '');
  const t = (div.innerText || div.textContent || '').replace(/\u200b/g, '');
  return t.trim().length > 0;
}

/** Texto antigo (sem tags) ou HTML já salvo → conteúdo seguro para o editor. */
function modoPreparoHtmlParaEditor(stored) {
  if (stored == null) return '';
  const s = String(stored).trim();
  if (!s) return '';
  const looksHtml = /^[\s]*</.test(s) && /<[a-z][\s\S]*>/i.test(s);
  if (looksHtml) {
    const clean = sanitizeFichaTecnicaModoPreparoHtml(s);
    if (clean && fichaTecnicaModoPreparoTextoVisivel(clean)) return clean;
    const div = document.createElement('div');
    div.innerHTML = s;
    const fallbackText = (div.textContent || div.innerText || '').trim();
    if (fallbackText) return `<p>${escapeHtml(fallbackText).replace(/\n/g, '<br>')}</p>`;
    return clean || '';
  }
  return `<p>${escapeHtml(s).replace(/\n/g, '<br>')}</p>`;
}

/** Formulário e lista da ficha técnica (persistência na API + cache local). */
function setupFichaTecnicaForm() {
  const form = document.getElementById('fichaTecnicaForm');
  const fotoInput = document.getElementById('fichaTecnicaFoto');
  const preview = document.getElementById('fichaTecnicaFotoPreview');
  const previewWrap = document.getElementById('fichaTecnicaFotoPreviewWrap');
  const listaView = document.getElementById('fichaTecnicaListaView');
  const formView = document.getElementById('fichaTecnicaFormView');
  const listaTbody = document.getElementById('fichaTecnicaListaTbody');
  const listaEmpty = document.getElementById('fichaTecnicaListaEmpty');
  const btnNova = document.getElementById('fichaTecnicaBtnNova');
  const btnVoltar = document.getElementById('fichaTecnicaVoltarLista');
  const editIdEl = document.getElementById('fichaTecnicaEditId');
  const formTitulo = document.getElementById('fichaTecnicaFormTitulo');
  const verModal = document.getElementById('fichaTecnicaVerModal');
  const verModalBody = document.getElementById('fichaTecnicaVerModalBody');
  const verModalTitulo = document.getElementById('fichaTecnicaVerModalTitulo');
  const verModalFechar = document.getElementById('fichaTecnicaVerModalFechar');
  const verModalPdf = document.getElementById('fichaTecnicaVerModalPdf');

  if (!form) return;

  let pratoModalAtual = null;
  let salvandoFichaTecnica = false;

  const readFileAsDataUrl = (file) =>
    new Promise((resolve, reject) => {
      const r = new FileReader();
      r.onload = () => resolve(String(r.result || ''));
      r.onerror = () => reject(new Error('read'));
      r.readAsDataURL(file);
    });

  const parseFichaTecnicaListaJson = (str) => {
    try {
      const p = JSON.parse(str || '[]');
      return Array.isArray(p) ? p : null;
    } catch (_) {
      return null;
    }
  };

  const carregarFichasSomenteLocal = () => {
    const principal = localStorage.getItem(FICHA_TECNICA_STORAGE_KEY);
    let list = parseFichaTecnicaListaJson(principal);
    if (!list) {
      const bak = localStorage.getItem(FICHA_TECNICA_STORAGE_BAK_KEY);
      list = parseFichaTecnicaListaJson(bak);
      if (list && list.length) {
        console.warn('Ficha técnica: lista principal corrompida; restaurada do backup automático.');
        showToast('Lista de fichas recuperada do backup automático.', 'warning');
        try {
          localStorage.setItem(FICHA_TECNICA_STORAGE_KEY, JSON.stringify(list));
        } catch (_) {}
      } else {
        list = [];
      }
    }
    state.fichaTecnicaPratos = list;
  };

  const carregarFichasDoArmazenamento = async () => {
    try {
      const remote = await fetchJSON('/fichas-tecnicas');
      const list = Array.isArray(remote) ? remote : [];
      state.fichaTecnicaPratos = list;
      try {
        const json = JSON.stringify(list);
        const anterior = localStorage.getItem(FICHA_TECNICA_STORAGE_KEY);
        if (anterior) {
          try {
            localStorage.setItem(FICHA_TECNICA_STORAGE_BAK_KEY, anterior);
          } catch (_) {}
        }
        localStorage.setItem(FICHA_TECNICA_STORAGE_KEY, json);
      } catch (_) {}
      return true;
    } catch (err) {
      console.warn('Ficha técnica: não foi possível carregar do servidor; usando cache local.', err);
      carregarFichasSomenteLocal();
      return false;
    }
  };

  const persistirFichas = () => {
    try {
      const json = JSON.stringify(state.fichaTecnicaPratos);
      const anterior = localStorage.getItem(FICHA_TECNICA_STORAGE_KEY);
      if (anterior) {
        try {
          localStorage.setItem(FICHA_TECNICA_STORAGE_BAK_KEY, anterior);
        } catch (_) {}
      }
      localStorage.setItem(FICHA_TECNICA_STORAGE_KEY, json);
      return true;
    } catch (err) {
      console.error(err);
      showToast('Não foi possível salvar no armazenamento local (espaço cheio?).', 'error');
      return false;
    }
  };

  const syncFichaTecnicaVisaoPrecos = () => {
    const nomeEl = document.getElementById('fichaTecnicaNomePrato');
    const precoEl = document.getElementById('fichaTecnicaPrecoPrato');
    const sugEl = document.getElementById('fichaTecnicaSugestaoVenda');
    const vn = document.getElementById('fichaTecnicaVisaoNome');
    const vp = document.getElementById('fichaTecnicaVisaoPreco');
    const vs = document.getElementById('fichaTecnicaVisaoSugestao');
    if (vn) vn.textContent = nomeEl && nomeEl.value.trim() ? nomeEl.value.trim() : '—';
    const parseMoedaInput = (el) => {
      const s = String(el && el.value != null ? el.value : '').trim();
      if (s === '') return null;
      const n = parseFloat(s.replace(',', '.'));
      return Number.isFinite(n) ? n : null;
    };
    const p = parseMoedaInput(precoEl);
    const sv = parseMoedaInput(sugEl);
    if (vp) vp.textContent = p != null ? formatCurrencyBRL(p) : '—';
    if (vs) vs.textContent = sv != null ? formatCurrencyBRL(sv) : '—';
  };

  document.getElementById('fichaTecnicaNomePrato')?.addEventListener('input', syncFichaTecnicaVisaoPrecos);
  document.getElementById('fichaTecnicaPrecoPrato')?.addEventListener('input', syncFichaTecnicaVisaoPrecos);
  document.getElementById('fichaTecnicaSugestaoVenda')?.addEventListener('input', syncFichaTecnicaVisaoPrecos);
  syncFichaTecnicaVisaoPrecos();

  const tempoHoras = document.getElementById('fichaTecnicaTempoHoras');
  const tempoMinutos = document.getElementById('fichaTecnicaTempoMinutos');
  const tempoHidden = document.getElementById('fichaTecnicaTempoPreparo');

  const sincronizarTempoPreparoOculto = () => {
    const h =
      tempoHoras?.value === '' || tempoHoras?.value == null ? 0 : parseInt(tempoHoras.value, 10) || 0;
    const m =
      tempoMinutos?.value === '' || tempoMinutos?.value == null ? 0 : parseInt(tempoMinutos.value, 10) || 0;
    const { h: H, m: M } = normalizarFichaTecnicaHorasMinutos(h, m);
    if (tempoHidden) tempoHidden.value = formatarFichaTecnicaTempoPreparo(H, M);
  };

  const normalizarCamposTempoAoSair = () => {
    const h =
      tempoHoras?.value === '' || tempoHoras?.value == null ? 0 : parseInt(tempoHoras.value, 10) || 0;
    const m =
      tempoMinutos?.value === '' || tempoMinutos?.value == null ? 0 : parseInt(tempoMinutos.value, 10) || 0;
    const { h: H, m: M } = normalizarFichaTecnicaHorasMinutos(h, m);
    if (tempoHoras) tempoHoras.value = H > 0 ? String(H) : '';
    if (tempoMinutos) tempoMinutos.value = M > 0 ? String(M) : '';
    sincronizarTempoPreparoOculto();
  };

  tempoHoras?.addEventListener('input', sincronizarTempoPreparoOculto);
  tempoMinutos?.addEventListener('input', sincronizarTempoPreparoOculto);
  tempoHoras?.addEventListener('blur', normalizarCamposTempoAoSair);
  tempoMinutos?.addEventListener('blur', normalizarCamposTempoAoSair);
  sincronizarTempoPreparoOculto();

  const modoEditor = document.getElementById('fichaTecnicaModoPreparoEditor');
  const modoHidden = document.getElementById('fichaTecnicaModoPreparo');
  const modoToolbar = document.getElementById('fichaTecnicaModoToolbar');
  const modoFontSize = document.getElementById('fichaTecnicaModoFontSize');
  const modoCor = document.getElementById('fichaTecnicaModoCor');
  const modoEmojiBtn = document.getElementById('fichaTecnicaModoEmojiBtn');
  const modoEmojiPanel = document.getElementById('fichaTecnicaModoEmojiPanel');

  const atualizarClassePlaceholderModoPreparo = () => {
    if (!modoEditor) return;
    modoEditor.classList.toggle('ficha-tecnica-rich-editor--empty', !fichaTecnicaModoPreparoTextoVisivel(modoEditor));
  };

  const sincronizarModoPreparoOculto = () => {
    if (!modoEditor || !modoHidden) return;
    if (!fichaTecnicaModoPreparoTextoVisivel(modoEditor)) {
      modoHidden.value = '';
      atualizarClassePlaceholderModoPreparo();
      return;
    }
    modoHidden.value = sanitizeFichaTecnicaModoPreparoHtml(modoEditor.innerHTML);
    atualizarClassePlaceholderModoPreparo();
  };

  const fecharPainelEmojiModoPreparoSomente = () => {
    if (!modoEmojiPanel || modoEmojiPanel.hidden) return;
    modoEmojiPanel.hidden = true;
    modoEmojiBtn?.setAttribute('aria-expanded', 'false');
  };

  if (modoEmojiPanel && modoEmojiPanel.dataset.emojiUi !== '3') {
    modoEmojiPanel.dataset.emojiUi = '3';
    const emojis = [
      '🍳', '🔥', '⏱️', '🌡️', '🥘', '🍖', '🧂', '💧', '❄️', '✅', '⭐', '📋', '👨‍🍳', '🫕', '🔪', '🥄',
      '🍋', '🌿', '♨️', '⚠️', '🧄', '🧅', '🥕', '🍅', '🐟', '🍗', '🥩', '🍚', '🧈', '🥛', '☕', '🍽️',
    ];
    modoEmojiPanel.innerHTML = emojis
      .map(
        (ch) =>
          `<button type="button" class="ficha-tecnica-emoji-item" data-emoji="${ch}" aria-label="Inserir ${ch}">${ch}</button>`
      )
      .join('');
  }

  modoToolbar?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-rich-cmd]');
    if (!btn) return;
    e.preventDefault();
    modoEditor?.focus();
    try {
      document.execCommand(btn.getAttribute('data-rich-cmd'), false, null);
    } catch (_) {}
    sincronizarModoPreparoOculto();
  });

  modoFontSize?.addEventListener('change', () => {
    const v = modoFontSize.value;
    if (!v || !modoEditor) return;
    modoEditor.focus();
    try {
      document.execCommand('fontSize', false, v);
    } catch (_) {}
    modoFontSize.value = '';
    sincronizarModoPreparoOculto();
  });

  modoCor?.addEventListener('input', () => {
    if (!modoEditor || !modoCor) return;
    modoEditor.focus();
    try {
      document.execCommand('foreColor', false, modoCor.value);
    } catch (_) {}
    sincronizarModoPreparoOculto();
  });

  modoEditor?.addEventListener('input', sincronizarModoPreparoOculto);
  modoEditor?.addEventListener('blur', sincronizarModoPreparoOculto);
  atualizarClassePlaceholderModoPreparo();

  modoEmojiBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    if (!modoEmojiPanel) return;
    const open = modoEmojiPanel.hidden;
    modoEmojiPanel.hidden = !open;
    modoEmojiBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  modoEmojiBtn?.addEventListener('mousedown', (e) => {
    e.stopPropagation();
  });

  modoEmojiPanel?.addEventListener('click', (e) => {
    const item = e.target.closest('[data-emoji]');
    if (!item) return;
    e.preventDefault();
    const ch = item.getAttribute('data-emoji') || '';
    modoEditor?.focus();
    try {
      document.execCommand('insertText', false, ch);
    } catch (_) {}
    fecharPainelEmojiModoPreparoSomente();
    sincronizarModoPreparoOculto();
  });

  modoEditor?.addEventListener('mousedown', () => {
    fecharPainelEmojiModoPreparoSomente();
  });

  const fecharPainelEmojiModoPreparo = (ev) => {
    if (!modoEmojiPanel || modoEmojiPanel.hidden) return;
    if (modoEmojiBtn?.contains(ev.target) || modoEmojiPanel.contains(ev.target)) return;
    fecharPainelEmojiModoPreparoSomente();
  };
  document.addEventListener('click', fecharPainelEmojiModoPreparo, true);

  document.addEventListener(
    'keydown',
    (e) => {
      if (e.key !== 'Escape') return;
      if (!modoEmojiPanel || modoEmojiPanel.hidden) return;
      if (formView?.classList.contains('hidden')) return;
      fecharPainelEmojiModoPreparoSomente();
    },
    true
  );

  /** Enter nos campos do painel de ingrediente não envia o formulário principal. */
  form.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    const t = e.target;
    if (t.isContentEditable) return;
    if (t.tagName === 'TEXTAREA') return;
    if (t.tagName !== 'INPUT' && t.tagName !== 'SELECT') return;
    const ingRoot = document.getElementById('fichaTecnicaIngredienteForm');
    if (ingRoot && ingRoot.contains(t)) e.preventDefault();
  });

  const ingForm = document.getElementById('fichaTecnicaIngredienteForm');
  const ingWrap = document.getElementById('fichaTecnicaIngredienteFormWrap');
  const ingAbrirBtn = document.getElementById('fichaTecnicaAbrirIngrediente');
  const ingCancelarBtn = document.getElementById('fichaTecnicaCancelarIngrediente');
  const ingAdicionarBtn = document.getElementById('fichaTecnicaIngredienteAdicionarBtn');
  const ingNome = document.getElementById('fichaTecnicaIngredienteNome');
  const ingQ = document.getElementById('fichaTecnicaIngredienteQuantidade');
  const ingUn = document.getElementById('fichaTecnicaIngredienteUnidade');
  const ingCu = document.getElementById('fichaTecnicaIngredienteCustoUnitario');
  const ingTot = document.getElementById('fichaTecnicaIngredienteCustoTotal');
  const ingEmpty = document.getElementById('fichaTecnicaIngredientesEmpty');
  const ingTableWrap = document.getElementById('fichaTecnicaIngredientesTableWrap');
  const ingTbody = document.getElementById('fichaTecnicaIngredientesTbody');

  /** null = novo ingrediente; caso contrário, id do item em edição. */
  let fichaTecnicaIngredienteEditId = null;

  const atualizarRotuloBotaoIngrediente = () => {
    if (!ingAdicionarBtn) return;
    ingAdicionarBtn.textContent =
      fichaTecnicaIngredienteEditId != null ? 'Salvar alterações' : 'Adicionar à lista';
  };

  const resetModoEdicaoIngrediente = () => {
    fichaTecnicaIngredienteEditId = null;
    atualizarRotuloBotaoIngrediente();
  };

  const parseNumIng = (v) => {
    if (v == null || v === '') return 0;
    const n = parseFloat(String(v).replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
  };
  const parseOptionalIngNum = (raw) => {
    const s = String(raw ?? '').trim();
    if (s === '') return null;
    const n = parseFloat(s.replace(',', '.'));
    return Number.isFinite(n) ? n : null;
  };
  const fmtIngQtdCell = (q) => (q != null && q !== '' ? formatQuantityDisplay(q) : '—');
  const fmtIngBRLCell = (v) =>
    v != null && v !== '' && Number.isFinite(Number(v)) ? formatCurrencyBRL(v) : '—';
  const recalcIngredienteCustoTotal = () => {
    if (!ingTot) return;
    const qs = String(ingQ?.value ?? '').trim();
    const cus = String(ingCu?.value ?? '').trim();
    if (qs === '' || cus === '') {
      ingTot.value = '';
      return;
    }
    const q = parseFloat(qs.replace(',', '.'));
    const cu = parseFloat(cus.replace(',', '.'));
    if (!Number.isFinite(q) || !Number.isFinite(cu)) {
      ingTot.value = '';
      return;
    }
    const t = Math.round(q * cu * 100) / 100;
    ingTot.value = t.toFixed(2).replace('.', ',');
  };
  ingQ?.addEventListener('input', recalcIngredienteCustoTotal);
  ingCu?.addEventListener('input', recalcIngredienteCustoTotal);
  recalcIngredienteCustoTotal();

  const fecharFormularioIngrediente = () => {
    if (ingWrap) ingWrap.hidden = true;
    resetModoEdicaoIngrediente();
    limparCamposIngrediente();
  };

  const renderListaIngredientesFichaTecnica = () => {
    const list = state.fichaTecnicaIngredientes;
    if (!ingEmpty || !ingTableWrap || !ingTbody) return;
    if (!list.length) {
      ingEmpty.hidden = false;
      ingTableWrap.hidden = true;
      ingTbody.innerHTML = '';
      return;
    }
    ingEmpty.hidden = true;
    ingTableWrap.hidden = false;
    ingTbody.innerHTML = list
      .map(
        (it) => `
      <tr>
        <td data-label="Ingrediente">${escapeHtml(it.nome)}</td>
        <td data-label="Quantidade">${escapeHtml(fmtIngQtdCell(it.quantidade))}</td>
        <td data-label="Un.">${escapeHtml(it.unidade_medida)}</td>
        <td data-label="Custo unitário">${escapeHtml(fmtIngBRLCell(it.custo_unitario))}</td>
        <td data-label="Custo total">${escapeHtml(fmtIngBRLCell(it.custo_total))}</td>
        <td class="ficha-tecnica-ingredientes-td-acoes" data-label="Ações">
          <span class="ficha-tecnica-ingredientes-acoes-btns">
            <button type="button" class="btn-icon ficha-tecnica-ingrediente-editar" title="Editar" data-editar-ingrediente-id="${escapeHtml(String(it.id))}" aria-label="Editar ingrediente">✎</button>
            <button type="button" class="btn-icon danger" title="Remover" data-remover-ingrediente-id="${escapeHtml(String(it.id))}" aria-label="Remover ingrediente">✕</button>
          </span>
        </td>
      </tr>`
      )
      .join('');
  };

  const limparCamposIngrediente = () => {
    if (ingNome) ingNome.value = '';
    if (ingQ) ingQ.value = '';
    if (ingUn) ingUn.value = '';
    if (ingCu) ingCu.value = '';
    if (ingTot) ingTot.value = '';
    recalcIngredienteCustoTotal();
  };

  const revogarPreviewUrl = () => {
    if (preview && preview.dataset.objectUrl) {
      try {
        URL.revokeObjectURL(preview.dataset.objectUrl);
      } catch (_) {}
      delete preview.dataset.objectUrl;
    }
  };

  const limparFormulario = () => {
    revogarPreviewUrl();
    if (preview) {
      preview.removeAttribute('src');
      delete preview.dataset.persistedBase64;
    }
    if (previewWrap) previewWrap.hidden = true;
    if (fotoInput) fotoInput.value = '';
    if (editIdEl) editIdEl.value = '';
    if (formTitulo) formTitulo.textContent = 'Nova ficha técnica';
    form.reset();
    sincronizarTempoPreparoOculto();
    if (modoEditor) modoEditor.innerHTML = '';
    if (modoHidden) modoHidden.value = '';
    atualizarClassePlaceholderModoPreparo();
    if (modoEmojiPanel) modoEmojiPanel.hidden = true;
    modoEmojiBtn?.setAttribute('aria-expanded', 'false');
    state.fichaTecnicaIngredientes = [];
    renderListaIngredientesFichaTecnica();
    syncFichaTecnicaVisaoPrecos();
    fecharFormularioIngrediente();
  };

  /** HTML completo da ficha para impressão / PDF (mesmo conteúdo em ambos os fluxos). */
  const montarHtmlDocumentoFichaTecnica = (p) => {
    const titulo = escapeHtml(p.nome_prato || 'Ficha técnica');
    const ingBody =
      (p.ingredientes || []).length > 0
        ? `<table><thead><tr><th>Ingrediente</th><th>Qtd</th><th>Un.</th><th>Custo un.</th><th>Custo tot.</th></tr></thead><tbody>${(p.ingredientes || [])
            .map(
              (it) =>
                `<tr><td>${escapeHtml(it.nome)}</td><td>${escapeHtml(fmtIngQtdCell(it.quantidade))}</td><td>${escapeHtml(it.unidade_medida)}</td><td>${fmtIngBRLCell(it.custo_unitario)}</td><td>${fmtIngBRLCell(it.custo_total)}</td></tr>`
            )
            .join('')}</tbody></table>`
        : '<p>—</p>';
    const modoHtml = sanitizeFichaTecnicaModoPreparoHtml(p.modo_preparo || '') || '—';
    return `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"/><title>${titulo}</title>
      <style>
        body{font-family:system-ui,Arial,sans-serif;padding:1.2rem;color:#111;max-width:720px;margin:0 auto;}
        h1{font-size:1.25rem;margin:0 0 1rem;}
        img{max-width:100%;max-height:220px;object-fit:contain;}
        table{border-collapse:collapse;width:100%;margin:0.5rem 0;font-size:0.9rem;}
        th,td{border:1px solid #cfd8dc;padding:6px 8px;text-align:left;}
        th{background:#eceff1;font-weight:700;}
        h2{font-size:1rem;margin-top:1rem;margin-bottom:0.35rem;}
        .modo-html{white-space:normal;line-height:1.55;}
      </style></head><body>
      <h1>${titulo}</h1>
      ${p.foto_base64 ? `<p><img src="${p.foto_base64}" alt=""/></p>` : ''}
      <p><strong>Tempo:</strong> ${escapeHtml(p.tempo_preparo || '—')}</p>
      <p><strong>Responsável:</strong> ${escapeHtml(p.responsavel_tecnico || '—')}</p>
      <p><strong>Preço por prato:</strong> ${p.preco_prato != null ? formatCurrencyBRL(p.preco_prato) : '—'}</p>
      <p><strong>Sugestão de venda:</strong> ${p.sugestao_venda != null ? formatCurrencyBRL(p.sugestao_venda) : '—'}</p>
      <h2>Ingredientes</h2>
      ${ingBody}
      <h2>Modo de preparo</h2>
      <div class="modo-html">${modoHtml}</div>
      </body></html>`;
  };

  const montarHtmlDetalhe = (p) => {
    const ings = Array.isArray(p.ingredientes) ? p.ingredientes : [];
    const ingRows = ings
      .map(
        (it) =>
          `<tr><td data-label="Ingrediente">${escapeHtml(it.nome)}</td><td data-label="Quantidade">${escapeHtml(fmtIngQtdCell(it.quantidade))}</td><td data-label="Un.">${escapeHtml(it.unidade_medida)}</td><td data-label="Custo unitário">${fmtIngBRLCell(it.custo_unitario)}</td><td data-label="Custo total">${fmtIngBRLCell(it.custo_total)}</td></tr>`
      )
      .join('');
    const fotoBlock =
      p.foto_base64 && String(p.foto_base64).trim()
        ? `<div class="ficha-tecnica-ver-foto"><img src="${p.foto_base64}" alt="" /></div>`
        : '';
    return `
      ${fotoBlock}
      <dl class="ficha-tecnica-ver-dl">
        <dt>Tempo de preparo</dt><dd>${escapeHtml(p.tempo_preparo || '—')}</dd>
        <dt>Responsável técnico</dt><dd>${escapeHtml(p.responsavel_tecnico || '—')}</dd>
        <dt>Preço por prato</dt><dd>${p.preco_prato != null ? formatCurrencyBRL(p.preco_prato) : '—'}</dd>
        <dt>Sugestão de venda</dt><dd>${p.sugestao_venda != null ? formatCurrencyBRL(p.sugestao_venda) : '—'}</dd>
      </dl>
      <h4 class="ficha-tecnica-ver-subtitulo">Ingredientes</h4>
      ${
        ings.length
          ? `<table class="ficha-tecnica-ver-ing-table"><thead><tr><th>Ingrediente</th><th>Qtd</th><th>Un.</th><th>Custo un.</th><th>Custo tot.</th></tr></thead><tbody>${ingRows}</tbody></table>`
          : '<p class="ficha-tecnica-ver-vazio">Nenhum ingrediente cadastrado.</p>'
      }
      <h4 class="ficha-tecnica-ver-subtitulo">Modo de preparo</h4>
      <div class="ficha-tecnica-ver-modo ficha-tecnica-ver-modo--html">${sanitizeFichaTecnicaModoPreparoHtml(p.modo_preparo || '') || '<span class="ficha-tecnica-ver-vazio">—</span>'}</div>
    `;
  };

  /** PDF via diálogo de impressão do navegador (Destino: Salvar como PDF ou impressora), igual à lista de compras. */
  const gerarPdfFichaTecnica = (p) => {
    if (!p) return;
    const conteudo = montarHtmlDocumentoFichaTecnica(p);
    const iframe = document.createElement('iframe');
    iframe.style.cssText =
      'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
    document.body.appendChild(iframe);
    let timeoutId = null;
    const cleanup = () => {
      if (timeoutId) {
        clearTimeout(timeoutId);
        timeoutId = null;
      }
      try {
        if (iframe.contentWindow) iframe.contentWindow.onafterprint = null;
      } catch (_) {}
      if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
    };
    iframe.onload = () => {
      const win = iframe.contentWindow;
      if (!win) {
        cleanup();
        showToast('Não foi possível preparar o PDF.', 'error');
        return;
      }
      win.onafterprint = cleanup;
      win.focus();
      try {
        if (typeof win.print === 'function') win.print();
        else {
          cleanup();
          showToast('Seu navegador não suportou a impressão.', 'error');
        }
      } catch (_) {
        cleanup();
        showToast('Falha ao acionar a impressão.', 'error');
      }
    };
    iframe.onerror = () => {
      cleanup();
      showToast('Não foi possível gerar o PDF.', 'error');
    };
    const doc = iframe.contentDocument || iframe.contentWindow?.document;
    if (!doc) {
      cleanup();
      showToast('Não foi possível gerar o PDF.', 'error');
      return;
    }
    doc.open();
    doc.write(conteudo);
    doc.close();
    timeoutId = setTimeout(() => {
      if (!document.body.contains(iframe)) return;
      cleanup();
      showToast('Falha ao gerar PDF. Verifique o navegador.', 'error');
    }, 60000);
    showToast('Abra “Salvar como PDF” na janela de impressão.', 'success');
  };

  const abrirModalVer = (p) => {
    pratoModalAtual = p;
    if (verModalTitulo) verModalTitulo.textContent = p.nome_prato || 'Ficha técnica';
    if (verModalBody) verModalBody.innerHTML = montarHtmlDetalhe(p);
    toggleModal(verModal, true);
  };

  const renderListaTabela = () => {
    const list = [...state.fichaTecnicaPratos].sort((a, b) => {
      const ta = new Date(a.updatedAt || 0).getTime();
      const tb = new Date(b.updatedAt || 0).getTime();
      return tb - ta;
    });
    const wrap = listaTbody?.closest('.table-wrapper');
    if (!listaTbody) return;
    if (!list.length) {
      listaTbody.innerHTML = '';
      if (listaEmpty) listaEmpty.hidden = false;
      if (wrap) wrap.style.display = 'none';
      return;
    }
    if (listaEmpty) listaEmpty.hidden = true;
    if (wrap) wrap.style.display = '';
    listaTbody.innerHTML = list
      .map((p) => {
        const idAttr = escapeHtml(String(p.id));
        const prec = p.preco_prato != null ? formatCurrencyBRL(p.preco_prato) : '—';
        const sug = p.sugestao_venda != null ? formatCurrencyBRL(p.sugestao_venda) : '—';
        return `<tr>
        <td data-label="Prato">${escapeHtml(p.nome_prato || '')}</td>
        <td data-label="Tempo de preparo">${escapeHtml(p.tempo_preparo || '')}</td>
        <td data-label="Responsável">${escapeHtml(p.responsavel_tecnico || '')}</td>
        <td data-label="Preço">${prec}</td>
        <td data-label="Sugestão de venda">${sug}</td>
        <td class="ficha-tecnica-acoes" data-label="Ações">
          <button type="button" class="btn neutral ficha-tecnica-acao-btn" data-ficha-acao="ver" data-ficha-id="${idAttr}">Ver</button>
          <button type="button" class="btn neutral ficha-tecnica-acao-btn" data-ficha-acao="editar" data-ficha-id="${idAttr}">Editar</button>
          <button type="button" class="btn neutral ficha-tecnica-acao-btn" data-ficha-acao="excluir" data-ficha-id="${idAttr}">Excluir</button>
        </td>
      </tr>`;
      })
      .join('');
  };

  const mostrarVistaLista = async () => {
    await carregarFichasDoArmazenamento();
    if (listaView) listaView.classList.remove('hidden');
    if (formView) formView.classList.add('hidden');
    renderListaTabela();
  };

  const mostrarVistaForm = () => {
    if (listaView) listaView.classList.add('hidden');
    if (formView) formView.classList.remove('hidden');
  };

  const obterPratoPorId = (id) => state.fichaTecnicaPratos.find((x) => String(x.id) === String(id));

  const preencherFormulario = (p) => {
    limparFormulario();
    if (editIdEl) editIdEl.value = String(p.id);
    const nome = document.getElementById('fichaTecnicaNomePrato');
    const th = document.getElementById('fichaTecnicaTempoHoras');
    const tm = document.getElementById('fichaTecnicaTempoMinutos');
    const resp = document.getElementById('fichaTecnicaResponsavel');
    const preco = document.getElementById('fichaTecnicaPrecoPrato');
    const sug = document.getElementById('fichaTecnicaSugestaoVenda');
    if (nome) nome.value = p.nome_prato || '';
    const tp = parseFichaTecnicaTempoPreparo(p.tempo_preparo);
    if (th) th.value = tp.h > 0 ? String(tp.h) : '';
    if (tm) tm.value = tp.m > 0 ? String(tp.m) : '';
    sincronizarTempoPreparoOculto();
    if (resp) resp.value = p.responsavel_tecnico || '';
    if (preco) preco.value = p.preco_prato != null && p.preco_prato !== '' ? String(p.preco_prato) : '';
    if (sug) sug.value = p.sugestao_venda != null && p.sugestao_venda !== '' ? String(p.sugestao_venda) : '';
    if (modoEditor) modoEditor.innerHTML = modoPreparoHtmlParaEditor(p.modo_preparo);
    sincronizarModoPreparoOculto();
    state.fichaTecnicaIngredientes = Array.isArray(p.ingredientes)
      ? p.ingredientes.map((it) => ({
          id: it.id != null ? it.id : Date.now() + Math.random(),
          nome: it.nome,
          quantidade: it.quantidade,
          unidade_medida: it.unidade_medida,
          custo_unitario: it.custo_unitario,
          custo_total: it.custo_total,
        }))
      : [];
    renderListaIngredientesFichaTecnica();
    if (p.foto_base64 && String(p.foto_base64).trim() && preview && previewWrap) {
      preview.dataset.persistedBase64 = p.foto_base64;
      preview.src = p.foto_base64;
      previewWrap.hidden = false;
    }
    if (formTitulo) formTitulo.textContent = 'Editar ficha técnica';
    syncFichaTecnicaVisaoPrecos();
  };

  onNavigateFichaTecnicaCallback = () => {
    void mostrarVistaLista();
  };

  btnNova?.addEventListener('click', () => {
    limparFormulario();
    mostrarVistaForm();
    document.getElementById('fichaTecnicaNomePrato')?.focus();
  });

  btnVoltar?.addEventListener('click', () => {
    void mostrarVistaLista();
  });

  listaTbody?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-ficha-acao][data-ficha-id]');
    if (!btn) return;
    const acao = btn.getAttribute('data-ficha-acao');
    const id = btn.getAttribute('data-ficha-id');
    const p = obterPratoPorId(id);
    if (!p) {
      showToast('Registro não encontrado.', 'error');
      void (async () => {
        await carregarFichasDoArmazenamento();
        renderListaTabela();
      })();
      return;
    }
    if (acao === 'ver') abrirModalVer(p);
    else if (acao === 'editar') {
      void (async () => {
        await carregarFichasDoArmazenamento();
        const fresh = obterPratoPorId(id);
        if (!fresh) {
          showToast('Registro não encontrado.', 'error');
          renderListaTabela();
          return;
        }
        preencherFormulario(fresh);
        mostrarVistaForm();
      })();
    } else if (acao === 'excluir') {
      if (!confirm('Excluir esta ficha técnica?')) return;
      void (async () => {
        try {
          await fetchJSON(`/fichas-tecnicas/${encodeURIComponent(id)}`, { method: 'DELETE' });
          state.fichaTecnicaPratos = state.fichaTecnicaPratos.filter((x) => String(x.id) !== String(id));
          persistirFichas();
          renderListaTabela();
          showToast('Ficha excluída.', 'success');
        } catch (err) {
          console.error('Ficha técnica — excluir:', err);
          showToast(err.message || 'Não foi possível excluir no servidor.', 'error');
        }
      })();
    }
  });

  verModalFechar?.addEventListener('click', () => toggleModal(verModal, false));
  verModal?.addEventListener('click', (e) => {
    if (e.target === verModal) toggleModal(verModal, false);
  });
  verModalPdf?.addEventListener('click', () => {
    if (pratoModalAtual) gerarPdfFichaTecnica(pratoModalAtual);
  });

  const salvarFichaTecnica = async () => {
    if (salvandoFichaTecnica) return;
    normalizarCamposTempoAoSair();
    sincronizarModoPreparoOculto();
    if (!form.checkValidity()) {
      form.reportValidity();
      showToast('Preencha os campos obrigatórios da ficha técnica.', 'warning');
      return;
    }
    salvandoFichaTecnica = true;
    try {
      const file = fotoInput?.files?.[0];
      let fotoBase64 = null;
      if (file && file.type.startsWith('image/')) {
        if (file.size > FICHA_TECNICA_FOTO_MAX_BYTES) {
          showToast('A imagem é muito grande. Use outra com menos de ~1,8 MB.', 'error');
          return;
        }
        try {
          fotoBase64 = await readFileAsDataUrl(file);
        } catch {
          showToast('Não foi possível ler a foto.', 'error');
          return;
        }
      } else if (preview?.dataset.persistedBase64) {
        fotoBase64 = preview.dataset.persistedBase64;
      }

      const parseMoedaInput = (el) => {
        const s = String(el && el.value != null ? el.value : '').trim();
        if (s === '') return null;
        const n = parseFloat(s.replace(',', '.'));
        return Number.isFinite(n) ? n : null;
      };

      let ingredientes;
      try {
        ingredientes = JSON.parse(JSON.stringify(state.fichaTecnicaIngredientes));
      } catch (err) {
        console.error(err);
        showToast('Erro ao montar a lista de ingredientes. Recarregue a página e tente de novo.', 'error');
        return;
      }

      const nome_prato = document.getElementById('fichaTecnicaNomePrato')?.value.trim() ?? '';
      const tempo_preparo = document.getElementById('fichaTecnicaTempoPreparo')?.value.trim() ?? '';
      const responsavel_tecnico = document.getElementById('fichaTecnicaResponsavel')?.value.trim() ?? '';
      const modo_preparo =
        modoEditor && fichaTecnicaModoPreparoTextoVisivel(modoEditor)
          ? sanitizeFichaTecnicaModoPreparoHtml(modoEditor.innerHTML)
          : '';
      const preco_prato = parseMoedaInput(document.getElementById('fichaTecnicaPrecoPrato'));
      const sugestao_venda = parseMoedaInput(document.getElementById('fichaTecnicaSugestaoVenda'));
      const editId = (editIdEl?.value || '').trim();

      const apiPayload = {
        nome_prato,
        tempo_preparo,
        responsavel_tecnico,
        foto_base64: fotoBase64,
        preco_prato,
        sugestao_venda,
        modo_preparo,
        ingredientes,
      };

      let saved;
      if (editId) {
        saved = await fetchJSON(`/fichas-tecnicas/${encodeURIComponent(editId)}`, {
          method: 'PUT',
          body: JSON.stringify(apiPayload),
        });
        const ix = state.fichaTecnicaPratos.findIndex((x) => String(x.id) === String(editId));
        if (ix >= 0) state.fichaTecnicaPratos[ix] = saved;
        else state.fichaTecnicaPratos.push(saved);
      } else {
        saved = await fetchJSON('/fichas-tecnicas', {
          method: 'POST',
          body: JSON.stringify(apiPayload),
        });
        state.fichaTecnicaPratos.push(saved);
      }

      if (!persistirFichas()) return;

      showToast(editId ? 'Ficha técnica atualizada.' : 'Ficha técnica salva.', 'success');
      limparFormulario();
      await mostrarVistaLista();
    } finally {
      salvandoFichaTecnica = false;
    }
  };

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    salvarFichaTecnica().catch((err) => {
      console.error('Ficha técnica — salvar:', err);
      showToast('Não foi possível salvar a ficha técnica.', 'error');
    });
  });

  document.getElementById('fichaTecnicaSalvarBtn')?.addEventListener('click', (e) => {
    e.preventDefault();
    salvarFichaTecnica().catch((err) => {
      console.error('Ficha técnica — salvar:', err);
      showToast('Não foi possível salvar a ficha técnica.', 'error');
    });
  });

  if (fotoInput && preview && previewWrap) {
    fotoInput.addEventListener('change', () => {
      const file = fotoInput.files && fotoInput.files[0];
      delete preview.dataset.persistedBase64;
      revogarPreviewUrl();
      if (!file || !file.type.startsWith('image/')) {
        preview.removeAttribute('src');
        previewWrap.hidden = true;
        return;
      }
      const url = URL.createObjectURL(file);
      preview.dataset.objectUrl = url;
      preview.src = url;
      previewWrap.hidden = false;
    });
  }

  ingAbrirBtn?.addEventListener('click', () => {
    if (!ingWrap) return;
    const abrir = ingWrap.hidden;
    ingWrap.hidden = !abrir;
    resetModoEdicaoIngrediente();
    limparCamposIngrediente();
    if (abrir) ingNome?.focus();
  });
  ingCancelarBtn?.addEventListener('click', () => {
    fecharFormularioIngrediente();
  });

  ingTbody?.addEventListener('click', (e) => {
    const editBtn = e.target.closest('[data-editar-ingrediente-id]');
    if (editBtn) {
      const idRaw = editBtn.getAttribute('data-editar-ingrediente-id');
      const it = state.fichaTecnicaIngredientes.find((x) => String(x.id) === String(idRaw));
      if (!it) return;
      fichaTecnicaIngredienteEditId = it.id;
      if (ingWrap) ingWrap.hidden = false;
      if (ingNome) ingNome.value = it.nome || '';
      if (ingQ) ingQ.value = it.quantidade != null && it.quantidade !== '' ? String(it.quantidade) : '';
      if (ingUn) ingUn.value = it.unidade_medida || '';
      if (ingCu) ingCu.value = it.custo_unitario != null && it.custo_unitario !== '' ? String(it.custo_unitario) : '';
      recalcIngredienteCustoTotal();
      atualizarRotuloBotaoIngrediente();
      ingNome?.focus();
      return;
    }
    const btn = e.target.closest('[data-remover-ingrediente-id]');
    if (!btn) return;
    const idRaw = btn.getAttribute('data-remover-ingrediente-id');
    state.fichaTecnicaIngredientes = state.fichaTecnicaIngredientes.filter(
      (x) => String(x.id) !== String(idRaw)
    );
    if (String(fichaTecnicaIngredienteEditId) === String(idRaw)) {
      resetModoEdicaoIngrediente();
      limparCamposIngrediente();
    }
    renderListaIngredientesFichaTecnica();
  });

  renderListaIngredientesFichaTecnica();

  ingAdicionarBtn?.addEventListener('click', () => {
    if (!ingForm) return;
    if (!(ingNome?.value || '').trim()) {
      showToast('Informe o nome do ingrediente.', 'error');
      ingNome?.focus();
      return;
    }
    if (!(ingUn?.value || '').trim()) {
      showToast('Selecione a unidade de medida.', 'error');
      ingUn?.focus();
      return;
    }
    recalcIngredienteCustoTotal();
    const nome = (ingNome?.value || '').trim();
    const quantidade = parseOptionalIngNum(ingQ?.value);
    const unidade_medida = (ingUn?.value || '').trim();
    const custo_unitario = parseOptionalIngNum(ingCu?.value);
    const custo_total =
      quantidade != null && custo_unitario != null
        ? Math.round(quantidade * custo_unitario * 100) / 100
        : null;
    if (fichaTecnicaIngredienteEditId != null) {
      const ix = state.fichaTecnicaIngredientes.findIndex(
        (x) => String(x.id) === String(fichaTecnicaIngredienteEditId)
      );
      if (ix >= 0) {
        const prevId = state.fichaTecnicaIngredientes[ix].id;
        state.fichaTecnicaIngredientes[ix] = {
          id: prevId,
          nome,
          quantidade,
          unidade_medida,
          custo_unitario,
          custo_total,
        };
      }
      renderListaIngredientesFichaTecnica();
      fecharFormularioIngrediente();
      showToast('Ingrediente atualizado.', 'success');
    } else {
      state.fichaTecnicaIngredientes.push({
        id: Date.now(),
        nome,
        quantidade,
        unidade_medida,
        custo_unitario,
        custo_total,
      });
      renderListaIngredientesFichaTecnica();
      fecharFormularioIngrediente();
      showToast('Ingrediente adicionado à lista.', 'success');
    }
  });
}

// Sequencia de inicializacao que amarra todos os componentes e listeners.
async function init() {
  // Verifica se está usando file:// e mostra aviso
  if (window.location.protocol === 'file:') {
    console.warn('⚠️ Arquivo aberto via file://. Para funcionar corretamente, use um servidor HTTP.');
    console.warn('Execute: cd frontend && php -S localhost:8000');
    console.warn('Depois acesse: http://localhost:8000');
    
    // Mostra um toast informativo
    setTimeout(() => {
      showToast('⚠️ Para funcionar corretamente, use um servidor HTTP. Execute: cd frontend && php -S localhost:8000', 'error', 10000);
    }, 1000);
  }
  
  applyPermissions();
  setupModals();
  setupNavigation();
  setupResponsiveSidebar();
  setupTables();
  setupFilters();
  setupForms();
  setupCards();
  setupPasswordToggles();
  setupFornecedoresModule();
  setupLogsModule();
  setupReservasMesasModule();
  setupHistoricoReservas();
  setupBoletosModule();
  setupAlvarasModule();
  setupFechamentoCaixaAuditoria();
  setupReciboAjudaCusto();
  setupFichaTecnicaForm();
  if (!stopMatrixAnimation) {
    stopMatrixAnimation = initMatrixBackground();
  }
  // Verifica se o formulário existe antes de adicionar listener
  console.log('🔍 Verificando formulário de login...');
  console.log('dom.loginForm:', dom.loginForm);
  if (dom.loginForm) {
    console.log('✅ Formulário de login encontrado, adicionando listener');
    dom.loginForm.addEventListener("submit", handleLogin);
    console.log('✅ Listener adicionado com sucesso');
  } else {
    console.error('❌ ERRO: Formulário de login não encontrado! ID: loginForm');
    // Tenta encontrar novamente após um delay
    setTimeout(() => {
      const form = document.getElementById("loginForm");
      if (form) {
        console.log('✅ Formulário encontrado no segundo try, adicionando listener');
        form.addEventListener("submit", handleLogin);
      } else {
        console.error('❌ Formulário ainda não encontrado após delay');
      }
    }, 500);
  }
  dom.logoutBtn?.addEventListener("click", handleLogout);
  
  // Função para atualizar referências do DOM quando uma seção é carregada dinamicamente
  function refreshDOMReferences() {
    // Atualiza referências dos elementos do dashboard
    dom.kpiProdutos = document.getElementById("kpiProdutos");
    dom.kpiVencer = document.getElementById("kpiVencer");
    dom.kpiLotesAVencer = document.getElementById("kpiLotesAVencer");
    dom.kpiLotesVencidos = document.getElementById("kpiLotesVencidos");
    dom.kpiMinimo = document.getElementById("kpiMinimo");
    dom.kpiPerdas = document.getElementById("kpiPerdas");
    dom.kpiComprasAtivas = document.getElementById("kpiComprasAtivas");
    dom.cardMinimo = document.getElementById("cardMinimo");
    dom.cardMinimoHint = document.getElementById("cardMinimoHint");
    dom.cardPerdas = document.getElementById("cardPerdas");
    dom.cardPerdasHint = document.getElementById("cardPerdasHint");
    dom.cardLotesAVencer = document.getElementById("cardLotesAVencer");
    dom.cardLotesVencidos = document.getElementById("cardLotesVencidos");
    dom.cardComprasAndamento = document.getElementById("cardComprasAndamento");
    dom.cardProdutosAtivos = document.getElementById("cardProdutosAtivos");
    dom.cardLotes7Dias = document.getElementById("cardLotes7Dias");
    dom.movTable = document.getElementById("movTable");
    dom.lotesTable = document.getElementById("lotesTable");
    dom.produtosDashboardTable = document.getElementById("produtosDashboardTable");
    dom.loteStatusChart = document.getElementById("loteStatusChart");
    dom.openEntradaBtn = document.getElementById("openEntrada");
    dom.openSaidaBtn = document.getElementById("openSaida");
    
    // Atualiza outras referências conforme necessário
    dom.produtosTable = document.getElementById("produtosTable");
    dom.unidadesTable = document.getElementById("unidadesTable");
    dom.usuariosTable = document.getElementById("usuariosTable");
    dom.locaisTable = document.getElementById("locaisTable");
    dom.lotesManageTable = document.getElementById("lotesManageTable");
    dom.lotesFilterForm = document.getElementById("lotesFilterForm");
    dom.lotesFiltroPesquisa = document.getElementById("lotesFiltroPesquisa");
    dom.lotesFiltroProduto = document.getElementById("lotesFiltroProduto");
    dom.lotesFiltroProdutoBusca = document.getElementById("lotesFiltroProdutoBusca");
    dom.lotesFiltroUnidade = document.getElementById("lotesFiltroUnidade");
    dom.lotesFiltroStatus = document.getElementById("lotesFiltroStatus");
    dom.lotesFiltroValidadeDe = document.getElementById("lotesFiltroValidadeDe");
    dom.lotesFiltroValidadeAte = document.getElementById("lotesFiltroValidadeAte");
    dom.aplicarFiltrosLotes = document.getElementById("aplicarFiltrosLotes");
    dom.limparFiltrosLotes = document.getElementById("limparFiltrosLotes");
    dom.estoqueSection = document.getElementById("estoqueSection");
    dom.estoqueProdutoSelect = document.getElementById("estoqueProdutoSelect");
    dom.estoqueInfo = document.getElementById("estoqueInfo");
    dom.estoqueProdutoNome = document.getElementById("estoqueProdutoNome");
    dom.estoqueTotalQtd = document.getElementById("estoqueTotalQtd");
    dom.estoqueTotalUnitario = document.getElementById("estoqueTotalUnitario");
    dom.estoqueTotalValor = document.getElementById("estoqueTotalValor");
    dom.estoqueUnidadeBase = document.getElementById("estoqueUnidadeBase");
    dom.estoqueTable = document.getElementById("estoqueTable");
  }

  // Função para configurar event listeners do dashboard
  function setupDashboardListeners() {
    // Remove listeners antigos se existirem
    const oldEntradaBtn = dom.openEntradaBtn;
    const oldSaidaBtn = dom.openSaidaBtn;
    const oldCardMinimo = dom.cardMinimo;
    const oldCardLotesAVencer = dom.cardLotesAVencer;
    const oldCardComprasAndamento = dom.cardComprasAndamento;
    const oldCardLotesVencidos = dom.cardLotesVencidos;
    const oldCardPerdas = dom.cardPerdas;
    const oldCardProdutosAtivos = dom.cardProdutosAtivos;
    const oldCardLotes7Dias = dom.cardLotes7Dias;
    
    // Atualiza referências
    refreshDOMReferences();
    
    // Configura novos listeners apenas se os botões existirem e forem diferentes
    if (dom.openEntradaBtn && dom.openEntradaBtn !== oldEntradaBtn) {
      // Remove listener antigo se existir
      if (oldEntradaBtn) {
        const newBtn = dom.openEntradaBtn.cloneNode(true);
        oldEntradaBtn.parentNode?.replaceChild(newBtn, oldEntradaBtn);
        dom.openEntradaBtn = document.getElementById("openEntrada");
      }
      
      dom.openEntradaBtn.addEventListener("click", async () => {
        console.log("Botão Registrar Entrada clicado (dashboard)");
        
        // Abre o modal primeiro para resposta imediata (como o botão de saída)
        dom.entradaForm?.reset();
        if (dom.entradaUnidadeSelect) dom.entradaUnidadeSelect.value = "";
        const entradaCustoInput = dom.entradaForm?.elements.custo_unitario;
        if (entradaCustoInput) {
          entradaCustoInput.dataset.value = "";
          entradaCustoInput.value = "";
        }
        resetEntradaLocalSelect();
        toggleModal(dom.entradaModal, true);
        
        // Carrega dados em background após abrir o modal (não bloqueia a abertura)
        Promise.all([
          // Carrega produtos se ainda não foram carregados
          (!state.produtos || state.produtos.length === 0) 
            ? loadProdutos().catch(err => {
                console.error("Erro ao carregar produtos:", err);
                showToast("Erro ao carregar produtos.", "error");
              })
            : Promise.resolve(),
          // Carrega unidades, locais e lotes em paralelo
          loadUnidades(false).catch(() => {}),
          loadLocais(true).catch(() => {}),
          loadLotes().catch(() => {}),
        ]).then(() => {
          // Atualiza o select de produtos após carregar
          refreshProdutoSelects();
          
          // Verifica se o select foi populado
          const entradaProdutoSelect = dom.entradaForm?.querySelector('select[name="produto_id"]');
          if (entradaProdutoSelect) {
            const optionsCount = entradaProdutoSelect.options.length;
            console.log("Select de produtos atualizado. Opções disponíveis:", optionsCount);
            if (optionsCount <= 1) {
              console.warn("Apenas a opção padrão encontrada. Verificando produtos...");
              
              // Se não há produtos, tenta carregar novamente
              if (state.produtos?.length === 0) {
                console.log("Tentando carregar produtos novamente...");
                loadProdutos().then(() => {
                  refreshProdutoSelects();
                });
              }
            }
          }
          
          // Atualiza locais e lotes após carregar
          ensureLocaisCarregados()
            .then(() => handleEntradaUnidadeChange())
            .catch(() => {
              resetEntradaLocalSelect();
            });
          populateEntradaLoteOptions();
          handleEntradaUnidadeChange();
        });
      });
    }
    
    if (dom.openSaidaBtn && dom.openSaidaBtn !== oldSaidaBtn) {
      // Remove listener antigo se existir
      if (oldSaidaBtn) {
        const newBtn = dom.openSaidaBtn.cloneNode(true);
        oldSaidaBtn.parentNode?.replaceChild(newBtn, oldSaidaBtn);
        dom.openSaidaBtn = document.getElementById("openSaida");
      }
      
      dom.openSaidaBtn.addEventListener("click", async () => {
        // Verifica se BAR ou COZINHA podem usar (garantia extra)
        const perfilAtual = (currentUser?.perfil || "").toString().trim().toUpperCase();
        const isCozinhaOuBar = perfilAtual === "COZINHA" || perfilAtual === "BAR" || perfilAtual === "ATENDENTE";
        const regras = PERMISSOES[perfilAtual] || PERMISSOES.VISUALIZADOR;
        const podeUsar = regras.canRegistrarMovimentacoes || isCozinhaOuBar;
        
        if (!podeUsar) {
          showToast("Você não tem permissão para registrar saídas.", "warning");
          return;
        }
        
        await loadUnidades(false).catch(() => {});
        dom.saidaForm?.reset();
        updateSaidaDestinoVisibility();
        resetSaidaProdutoSelect();
        toggleModal(dom.saidaModal, true);
      });
    }

    // Configura listeners dos cards do dashboard
    if (dom.cardMinimo && dom.cardMinimo !== oldCardMinimo) {
      if (oldCardMinimo) {
        const newCard = dom.cardMinimo.cloneNode(true);
        oldCardMinimo.parentNode?.replaceChild(newCard, oldCardMinimo);
        dom.cardMinimo = document.getElementById("cardMinimo");
        dom.cardMinimoHint = document.getElementById("cardMinimoHint");
      }

      document.getElementById("cardMinimoSelect")?.addEventListener("change", async (event) => {
        const produtoId = Number(event.target.value);
        if (!Number.isFinite(produtoId) || produtoId <= 0) return;
        navigateTo("estoque");
        try {
          await loadEstoqueProdutos();
          const selectEstoque = document.getElementById("estoqueProdutoSelect");
          if (selectEstoque) {
            selectEstoque.value = String(produtoId);
            selectEstoque.dispatchEvent(new Event("change"));
          } else {
            await loadEstoqueProduto(produtoId);
          }
        } catch (err) {
          showToast(err?.message || "Falha ao carregar estoque.", "error");
        }
        event.target.value = "";
      });
    }

    if (dom.cardLotesAVencer && dom.cardLotesAVencer !== oldCardLotesAVencer) {
      if (oldCardLotesAVencer) {
        const newCard = dom.cardLotesAVencer.cloneNode(true);
        oldCardLotesAVencer.parentNode?.replaceChild(newCard, oldCardLotesAVencer);
        dom.cardLotesAVencer = document.getElementById("cardLotesAVencer");
      }
      
      dom.cardLotesAVencer.addEventListener("click", async () => {
        navigateTo("lotes");
        const hoje = new Date();
        const ate = new Date();
        ate.setDate(hoje.getDate() + 15);
        await loadLotes({ validade_de: hoje.toISOString().slice(0, 10), validade_ate: ate.toISOString().slice(0, 10) }).catch(() => {});
      });
    }

    if (dom.cardComprasAndamento && dom.cardComprasAndamento !== oldCardComprasAndamento) {
      if (oldCardComprasAndamento) {
        const newCard = dom.cardComprasAndamento.cloneNode(true);
        oldCardComprasAndamento.parentNode?.replaceChild(newCard, oldCardComprasAndamento);
        dom.cardComprasAndamento = document.getElementById("cardComprasAndamento");
      }
      
      dom.cardComprasAndamento.addEventListener("click", async () => {
        state.listaComprasFiltroStatus = "ativas";
        if (dom.listaCompraFiltroStatus) dom.listaCompraFiltroStatus.value = "ativas";
        navigateTo("compras");
        await loadListasCompras().catch((err) => showToast(err.message || "Falha ao carregar listas.", "error"));
      });
    }

    if (dom.cardLotesVencidos && dom.cardLotesVencidos !== oldCardLotesVencidos) {
      if (oldCardLotesVencidos) {
        const newCard = dom.cardLotesVencidos.cloneNode(true);
        oldCardLotesVencidos.parentNode?.replaceChild(newCard, oldCardLotesVencidos);
        dom.cardLotesVencidos = document.getElementById("cardLotesVencidos");
      }
      
      dom.cardLotesVencidos.addEventListener("click", async () => {
        navigateTo("lotes");
        await loadLotes({ status: "VENCIDO" }).catch(() => {});
      });
    }

    if (dom.cardPerdas && dom.cardPerdas !== oldCardPerdas) {
      if (oldCardPerdas) {
        const newCard = dom.cardPerdas.cloneNode(true);
        oldCardPerdas.parentNode?.replaceChild(newCard, oldCardPerdas);
        dom.cardPerdas = document.getElementById("cardPerdas");
        dom.cardPerdasHint = document.getElementById("cardPerdasHint");
      }
      
      dom.cardPerdas.addEventListener("click", async () => {
        const resumo = state.perdasResumo || {};
        const totalRegistros = Number(resumo.total_registros || 0);
        if (totalRegistros === 0) {
          showToast("Nenhuma perda registrada.", "info");
          return;
        }
        navigateTo("movimentacoes");
        await loadMovimentacoesDetalhadas({ motivo: "PERDA" }, { refreshDashboard: false }).catch((err) => {
          showToast(err?.message || "Falha ao carregar perdas.", "error");
        });
      });
    }

    // Card de Produtos Ativos
    if (dom.cardProdutosAtivos && dom.cardProdutosAtivos !== oldCardProdutosAtivos) {
      if (oldCardProdutosAtivos) {
        const newCard = dom.cardProdutosAtivos.cloneNode(true);
        oldCardProdutosAtivos.parentNode?.replaceChild(newCard, oldCardProdutosAtivos);
        dom.cardProdutosAtivos = document.getElementById("cardProdutosAtivos");
      }
      
      dom.cardProdutosAtivos.addEventListener("click", async () => {
        navigateTo("produtos");
        await loadProdutos().catch((err) => {
          showToast(err?.message || "Falha ao carregar produtos.", "error");
        });
      });
    }

    // Card de Lotes a vencer (7 dias)
    if (dom.cardLotes7Dias && dom.cardLotes7Dias !== oldCardLotes7Dias) {
      if (oldCardLotes7Dias) {
        const newCard = dom.cardLotes7Dias.cloneNode(true);
        oldCardLotes7Dias.parentNode?.replaceChild(newCard, oldCardLotes7Dias);
        dom.cardLotes7Dias = document.getElementById("cardLotes7Dias");
      }
      
      dom.cardLotes7Dias.addEventListener("click", async () => {
        navigateTo("lotes");
        const hoje = new Date();
        const ate = new Date();
        ate.setDate(hoje.getDate() + 7);
        await loadLotes({ validade_de: hoje.toISOString().slice(0, 10), validade_ate: ate.toISOString().slice(0, 10) }).catch(() => {});
      });
    }
  }

  // Listener para quando o router carrega uma tela
  window.addEventListener('sectionLoaded', async (event) => {
    const section = event.detail?.section;
    if (!section) {
      console.warn('sectionLoaded sem section');
      return;
    }
    
    console.log('sectionLoaded disparado para:', section);
    
    // Atualiza referências do DOM antes de carregar os dados
    refreshDOMReferences();
    
    try {
      // Carrega os dados específicos de cada tela
      if (section === "dashboard") {
        console.log('Carregando dashboard...');
        // Configura listeners do dashboard antes de carregar
        setupDashboardListeners();
        await loadDashboard();
      } else if (section === "produtos") await loadProdutos();
      else if (section === "estoque") {
        // Aplica permissões de estoque quando a seção é carregada
        const perfilAtual = (currentUser?.perfil || "").toString().trim().toUpperCase();
        const isCozinhaOuBar = perfilAtual === "COZINHA" || perfilAtual === "BAR" || perfilAtual === "ATENDENTE";
        
        // Ocultar cards de valores
        const estoqueCardUnitario = document.getElementById("estoqueCardUnitario");
        const estoqueCardTotal = document.getElementById("estoqueCardTotal");
        const estoqueResumoCard = document.querySelector(".estoque-resumo-card");
        if (estoqueCardUnitario) estoqueCardUnitario.classList.toggle("hidden", isCozinhaOuBar);
        if (estoqueCardTotal) estoqueCardTotal.classList.toggle("hidden", isCozinhaOuBar);
        if (estoqueResumoCard) estoqueResumoCard.classList.toggle("hidden", isCozinhaOuBar);
        
        // Ocultar colunas da tabela
        const estoqueColValorUnitario = document.querySelectorAll(".estoque-col-valor-unitario");
        const estoqueColValorTotal = document.querySelectorAll(".estoque-col-valor-total");
        estoqueColValorUnitario.forEach(col => col.classList.toggle("hidden", isCozinhaOuBar));
        estoqueColValorTotal.forEach(col => col.classList.toggle("hidden", isCozinhaOuBar));
        
        await loadEstoqueProdutos();
      }
      else if (section === "unidades") await Promise.all([loadUnidades(), loadUsuarios()]);
      else if (section === "usuarios") await loadUsuarios();
      else if (section === "lotes") {
        document.querySelector(".content .view-section")?.classList.remove("hidden");
        await loadLotes();
      }
      else if (section === "locais") await Promise.all([loadLocais(true), loadUnidades(false)]);
      else if (section === "movimentacoes") {
        // ✅ Garante que produtos e unidades estejam carregados para popular os selects
        await Promise.all([
          loadProdutos().catch(() => {}),
          loadUnidades().catch(() => {})
        ]);
        // ✅ Popula os selects
        refreshProdutoSelects();
        refreshUnidadeSelects();
        // ✅ Carrega movimentações
        await loadMovimentacoesDetalhadas({}, { refreshDashboard: true });
      }
      else if (section === "relatorios") await loadRelatorio();
      else if (section === "compras") await loadListasCompras();
    } catch (err) {
      console.error('Erro ao carregar dados da seção:', err);
      showToast(err?.message || 'Erro ao carregar dados', 'error');
    }
  });
  const stored = getUser();
  if (stored && stored.token) {
    startAppSession();
    // Timer de inatividade será iniciado dentro de startAppSession
  } else {
    clearUser();
    dom.loginOverlay.classList.remove("hidden");
    dom.appShell.classList.add("hidden");
  }
}

init();




// Converte string em valor numérico aceitando formato BR (1.000,50) e US (1,000.50)
function parseCurrencyFromString(str) {
  const s = (str || "").toString().replace(/[^\d,.-]/g, "").trim();
  if (!s) return 0;
  const lastComma = s.lastIndexOf(",");
  const lastDot = s.lastIndexOf(".");
  let normalized;
  if (lastComma > lastDot) {
    normalized = s.replace(/\./g, "").replace(",", ".");
  } else if (lastDot > lastComma) {
    normalized = s.replace(/,/g, "");
  } else {
    normalized = s.replace(",", ".");
  }
  const num = Number(normalized);
  return Number.isFinite(num) ? num : 0;
}

function attachCurrencyMask(input) {
  if (!input) return;
  input.addEventListener("input", (event) => {
    const raw = event.target.value;
    const numero = parseCurrencyFromString(raw);
    event.target.dataset.value = String(numero);
  });
  input.addEventListener("focus", (event) => {
    const valor = parseCurrencyFromString(event.target.dataset.value || event.target.value);
    if (valor !== 0) {
      event.target.value = valor.toFixed(2).replace(".", ",");
    } else {
      event.target.value = "";
    }
  });
  input.addEventListener("blur", (event) => {
    const numero = parseCurrencyFromString(event.target.value);
    event.target.dataset.value = String(numero);
    event.target.value = numero > 0 ? formatCurrencyBRL(numero) : "";
  });
  const initial = parseCurrencyFromString(input.value || input.dataset.value);
  if (initial > 0) {
    input.dataset.value = String(initial);
    input.value = formatCurrencyBRL(initial);
  } else {
    input.dataset.value = "0";
    input.value = "";
  }
}

function parseCurrencyInput(input) {
  if (!input) return 0;
  const datasetValue = input.dataset.value;
  if (datasetValue !== undefined && datasetValue !== "") {
    const numero = Number(datasetValue);
    if (Number.isFinite(numero)) return numero;
  }
  return parseCurrencyFromString(input.value);
}

// Expor funções de etiqueta no escopo global para uso em onclick (caso necessário)
if (typeof window !== 'undefined') {
  window.imprimirEtiquetaLote = imprimirEtiquetaLote;
  window.baixarEtiquetaLote = baixarEtiquetaLote;
  
  // Função de teste para criar lote "água com gás"
  window.testarCriarLoteAguaGas = async function() {
    console.log("🚀 Teste: Criando lote 'Água com Gás'...");
    try {
      // Buscar produtos
      const produtos = await fetchJSON('/produtos?todas=1');
      let produto = produtos.find(p => 
        p.nome && p.nome.toLowerCase().includes('água') && 
        (p.nome.toLowerCase().includes('gás') || p.nome.toLowerCase().includes('gas'))
      );
      
      // Se não encontrar, criar produto
      if (!produto) {
        const unidades = await fetchJSON('/unidades?todas=1');
        if (!unidades || unidades.length === 0) {
          throw new Error("Crie uma unidade primeiro!");
        }
        
        produto = await fetchJSON('/produtos', {
          method: 'POST',
          body: JSON.stringify({
            nome: "Água com Gás",
            categoria: "BEBIDAS",
            unidade_base: "UND",
            unidade_id: unidades[0].id,
            ativo: 1
          })
        });
        console.log("✅ Produto criado:", produto.nome);
      }
      
      // Buscar unidade
      const unidades = await fetchJSON('/unidades?todas=1');
      const unidade = unidades[0];
      
      // Calcular data de validade (1 ano a partir de hoje)
      const dataValidade = new Date();
      dataValidade.setDate(dataValidade.getDate() + 365);
      const dataValidadeStr = dataValidade.toISOString().split('T')[0];
      
      // Criar lote
      const lote = await fetchJSON('/lotes', {
        method: 'POST',
        body: JSON.stringify({
          produto_id: produto.id,
          unidade_id: unidade.id,
          codigo_lote: `AGUA-GAS-${Date.now()}`,
          quantidade: 24,
          custo_unitario: 2.50,
          data_validade: dataValidadeStr
        })
      });
      
      console.log("✅ Lote criado com sucesso!", lote);
      showToast("✅ Lote 'Água com Gás' criado com sucesso!", "success");
      
      // Recarregar lotes
      if (typeof loadLotes === 'function') {
        await loadLotes();
      }
      
      return lote;
    } catch (err) {
      console.error("❌ Erro ao criar lote:", err);
      showToast("❌ Erro: " + (err.message || "Falha ao criar lote"), "error");
      throw err;
    }
  };
}
