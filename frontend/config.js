// ⚠️ CONFIGURAÇÃO CRÍTICA - MODIFICAR COM CUIDADO ⚠️
// API_URL: URL do backend Laravel. Se o cadastro falhar, verifique:
// - API no mesmo servidor: use "/sas-estoque/backend/public/api" (path relativo) ou "https://SEU-DOMINIO/sas-estoque/backend/public/api"
// - API em subdomínio: "https://api.gruposaborparaense.com.br/api"
window.APP_CONFIG = {
  API_URL: "http://127.0.0.1:8000/api",
  // Opcional — deve ser IGUAL ao Laravel (.env): ADMIN_BACKUP_KEY (se definida).
  // Se vazio, o frontend usa a chave legada (compatível com instalações antigas).
  // ADMIN_BACKUP_KEY: "",
};
