// Sistema de roteamento para carregar telas dinamicamente
class Router {
  constructor() {
    this.currentSection = null;
    this.contentContainer = null;
    this.initialNavigationDone = false; // Flag para evitar navegação inicial duplicada
    this.routes = {
      dashboard: 'src/pages/dashboard/index.html',
      unidades: 'src/pages/unidades/index.html',
      usuarios: 'src/pages/usuarios/index.html',
      produtos: 'src/pages/produtos/index.html',
      estoque: 'src/pages/estoque/index.html',
      lotes: 'src/pages/lotes/index.html',
      locais: 'src/pages/locais/index.html',
      movimentacoes: 'src/pages/movimentacoes/index.html',
      compras: 'src/pages/compras/index.html',
      relatorios: 'src/pages/relatorios/index.html'
    };
  }

  init(containerId) {
    this.contentContainer = document.querySelector(containerId);
    if (!this.contentContainer) {
      console.error('Container não encontrado:', containerId);
      return;
    }
    
    // Verifica se há usuário logado E seção salva
    let userLoggedIn = false;
    let hasSavedSection = false;
    
    try {
      const userData = localStorage.getItem('sas-estoque-user');
      if (userData) {
        const user = JSON.parse(userData);
        userLoggedIn = !!(user && user.token);
      }
      
      const savedSection = localStorage.getItem('sas-estoque-current-section');
      if (savedSection) {
        const validSections = ['dashboard', 'produtos', 'estoque', 'unidades', 'usuarios', 'lotes', 'locais', 'movimentacoes', 'relatorios', 'compras', 'fornecedores', 'fornecedoresBackup', 'boletao', 'kanbanAdministrativo', 'fechamento', 'fechamentoDash'];
        hasSavedSection = validSections.includes(savedSection);
      }
    } catch (err) {
      // Ignora erros de parsing
    }
    
    // Se o usuário está logado OU há uma seção salva, NÃO navega automaticamente
    // Deixa o startAppSession fazer a navegação inicial para restaurar a seção salva
    // Isso evita o "flash" para o dashboard
    if (userLoggedIn || hasSavedSection) {
      this.initialNavigationDone = false; // Marca que ainda não navegou
      return;
    }
    
    // Só navega automaticamente se não houver usuário logado E não houver seção salva
    // Aguarda um pouco para garantir que o DOM está pronto
    setTimeout(() => {
      // Só navega se ainda não houver uma seção atual definida
      if (!this.currentSection && !this.initialNavigationDone) {
        this.navigate('dashboard');
        this.initialNavigationDone = true;
      }
    }, 100);
  }

  loadPageWithXHR(url) {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('GET', url, true);
      
      let resolved = false;
      
      xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && !resolved) {
          resolved = true;
          // Status 0 geralmente indica arquivo local (file://)
          if (xhr.status === 200 || xhr.status === 0) {
            if (xhr.responseText && xhr.responseText.trim().length > 0) {
              resolve(xhr.responseText);
            } else {
              reject(new Error(`Arquivo vazio ou não encontrado: ${url}`));
            }
          } else {
            reject(new Error(`Erro ao carregar ${url}: ${xhr.statusText} (status: ${xhr.status})`));
          }
        }
      };
      
      xhr.onerror = function() {
        if (!resolved) {
          resolved = true;
          reject(new Error(`Erro de rede ao carregar ${url}`));
        }
      };
      
      xhr.onloadend = function() {
        if (!resolved && xhr.readyState === 4) {
          resolved = true;
          if (xhr.status === 200 || xhr.status === 0) {
            if (xhr.responseText && xhr.responseText.trim().length > 0) {
              resolve(xhr.responseText);
            } else {
              reject(new Error(`Arquivo vazio: ${url}`));
            }
          } else {
            reject(new Error(`Erro HTTP ${xhr.status}: ${url}`));
          }
        }
      };
      
      try {
        xhr.send();
      } catch (error) {
        if (!resolved) {
          resolved = true;
          reject(new Error(`Erro ao enviar requisição: ${error.message}`));
        }
      }
    });
  }

  // Método alternativo usando iframe (para file:// quando XHR falha)
  loadPageWithIframe(url) {
    return new Promise((resolve, reject) => {
      const iframe = document.createElement('iframe');
      iframe.style.display = 'none';
      iframe.style.width = '0';
      iframe.style.height = '0';
      iframe.style.border = 'none';
      
      const timeout = setTimeout(() => {
        document.body.removeChild(iframe);
        reject(new Error(`Timeout ao carregar ${url}`));
      }, 5000);
      
      iframe.onload = function() {
        try {
          const content = iframe.contentDocument || iframe.contentWindow.document;
          const html = content.body ? content.body.innerHTML : content.documentElement.innerHTML;
          clearTimeout(timeout);
          document.body.removeChild(iframe);
          if (html && html.trim().length > 0) {
            resolve(html);
          } else {
            reject(new Error(`Conteúdo vazio de ${url}`));
          }
        } catch (error) {
          clearTimeout(timeout);
          document.body.removeChild(iframe);
          reject(new Error(`Erro ao acessar conteúdo do iframe: ${error.message}`));
        }
      };
      
      iframe.onerror = function() {
        clearTimeout(timeout);
        document.body.removeChild(iframe);
        reject(new Error(`Erro ao carregar iframe: ${url}`));
      };
      
      document.body.appendChild(iframe);
      iframe.src = url;
    });
  }

  async navigate(section) {
    if (!this.routes[section]) {
      console.error('Rota não encontrada:', section);
      return;
    }

    // Se já estamos na mesma seção, não navega novamente
    if (this.currentSection === section) {
      console.log('Já estamos na seção:', section, '- pulando navegação');
      return;
    }

    console.log('Navegando para:', section, 'URL:', this.routes[section]);

    // Atualiza os links de forma atômica para evitar flash
    // Usa toggle em uma única passada para evitar o momento onde nenhum link está ativo
    const allLinks = document.querySelectorAll('.nav-link');
    const newActiveLink = document.querySelector(`.nav-link[data-section="${section}"]`);
    
    // Atualiza todos os links de uma vez - se o link é o novo ativo, adiciona 'active', senão remove
    allLinks.forEach(link => {
      link.classList.toggle('active', link === newActiveLink);
    });

    try {
      let html;
      const url = this.routes[section];
      const isFileProtocol = window.location.protocol === 'file:';
      
      // Se estiver em file://, tenta várias abordagens
      if (isFileProtocol) {
        console.log('Protocolo file:// detectado, tentando carregar página...');
        let lastError = null;
        
        // Tenta 1: XHR
        try {
          html = await this.loadPageWithXHR(url);
          console.log('✅ Página carregada via XHR:', section);
        } catch (xhrError) {
          console.warn('❌ XHR falhou:', xhrError.message);
          lastError = xhrError;
          
          // Tenta 2: Fetch
          try {
            const response = await fetch(url);
            if (response.ok) {
              html = await response.text();
              console.log('✅ Página carregada via fetch:', section);
            } else {
              throw new Error(`Fetch retornou status ${response.status}`);
            }
          } catch (fetchError) {
            console.warn('❌ Fetch falhou:', fetchError.message);
            lastError = fetchError;
            
            // Tenta 3: Iframe (última tentativa)
            try {
              html = await this.loadPageWithIframe(url);
              console.log('✅ Página carregada via iframe:', section);
            } catch (iframeError) {
              console.error('❌ Iframe falhou:', iframeError.message);
              throw new Error(`Não foi possível carregar a página com nenhum método. Último erro: ${iframeError.message}`);
            }
          }
        }
      } else {
        // Se estiver em servidor HTTP, tenta fetch primeiro
        try {
          const response = await fetch(url);
          if (response.ok) {
            html = await response.text();
            console.log('Página carregada via fetch:', section);
          } else {
            throw new Error(`Fetch retornou status ${response.status}`);
          }
        } catch (fetchError) {
          console.log('Fetch falhou, tentando XHR:', fetchError.message);
          // Se fetch falhar, tenta carregar via XMLHttpRequest
          html = await this.loadPageWithXHR(url);
          console.log('Página carregada via XHR:', section);
        }
      }
      
      if (!html || html.trim().length === 0) {
        throw new Error('Página vazia ou não encontrada');
      }
      
      // Substitui o conteúdo do container
      this.contentContainer.innerHTML = html;
      console.log('HTML inserido no container, tamanho:', html.length);
      
      this.currentSection = section;
      this.initialNavigationDone = true; // Marca que a navegação inicial foi feita
      
      // Salva a seção atual no localStorage para restaurar após refresh
      try {
        localStorage.setItem('sas-estoque-current-section', section);
      } catch (err) {
        console.warn('Erro ao salvar seção atual no localStorage:', err);
      }
      
      // Aguarda um pouco para garantir que o HTML foi inserido no DOM
      setTimeout(() => {
        console.log('Disparando evento sectionLoaded para:', section);
        // Dispara evento customizado para notificar que a tela foi carregada
        window.dispatchEvent(new CustomEvent('sectionLoaded', { 
          detail: { section } 
        }));
      }, 100);
      
    } catch (error) {
      console.error('Erro ao carregar tela:', error);
      const isFileProtocol = window.location.protocol === 'file:';
      
      if (isFileProtocol) {
        // Mostra modal de aviso para file://
        this.showFileProtocolWarning(section);
      } else {
        const errorMessage = `Erro ao carregar a página. Verifique se o arquivo existe e se o servidor está configurado corretamente.`;
        this.contentContainer.innerHTML = `
          <div style="padding: 2rem; text-align: center; color: #d32f2f;">
            <h2>Erro ao carregar a tela: ${section}</h2>
            <p style="margin: 1rem 0;">${error.message}</p>
            <div style="font-size: 0.875rem; margin-top: 1rem; color: #666; background: #f5f5f5; padding: 1rem; border-radius: 4px; text-align: left; max-width: 600px; margin-left: auto; margin-right: auto;">
              💡 <strong>Dica:</strong><br>
              ${errorMessage}
            </div>
            <p style="font-size: 0.75rem; margin-top: 1rem; color: #999;">
              URL tentada: <code>${this.routes[section]}</code><br>
              Protocolo: <code>${window.location.protocol}</code>
            </p>
          </div>
        `;
      }
    }
  }

  showFileProtocolWarning(section) {
    // Remove modal anterior se existir
    const existingModal = document.getElementById('fileProtocolWarning');
    if (existingModal) {
      existingModal.remove();
    }

    const modal = document.createElement('div');
    modal.id = 'fileProtocolWarning';
    modal.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.7);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10000;
      padding: 20px;
    `;

    modal.innerHTML = `
      <div style="background: white; border-radius: 8px; padding: 2rem; max-width: 600px; width: 100%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <h2 style="margin: 0 0 1rem 0; color: #d32f2f;">⚠️ Servidor HTTP Necessário</h2>
        <p style="margin: 0 0 1.5rem 0; color: #666;">
          O arquivo foi aberto diretamente no navegador (file://), o que impede o carregamento das páginas devido a restrições de segurança.
        </p>
        <div style="background: #f5f5f5; padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem; font-family: monospace; font-size: 0.9rem;">
          <strong>Para resolver:</strong><br><br>
          <strong>1.</strong> Abra o PowerShell ou Terminal<br><br>
          <strong>2.</strong> Execute um dos comandos abaixo:<br><br>
          <div style="background: #fff; padding: 0.75rem; border-radius: 3px; margin: 0.5rem 0;">
            <strong>Opção A (Frontend apenas):</strong><br>
            <code style="color: #1976d2;">cd frontend</code><br>
            <code style="color: #1976d2;">php -S localhost:8001</code>
          </div>
          <div style="background: #fff; padding: 0.75rem; border-radius: 3px; margin: 0.5rem 0;">
            <strong>Opção B (Frontend + Backend):</strong><br>
            <code style="color: #1976d2;">.\\iniciar-servidores.ps1</code>
          </div>
          <br>
          <strong>3.</strong> Acesse no navegador:<br>
          <code style="color: #1976d2; font-size: 1.1em;">http://localhost:8001</code>
        </div>
        <div style="display: flex; gap: 1rem; justify-content: flex-end;">
          <button id="closeFileWarning" style="padding: 0.75rem 1.5rem; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; font-size: 1rem;">
            Entendi
          </button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);

    // Fecha ao clicar no botão
    modal.querySelector('#closeFileWarning').addEventListener('click', () => {
      modal.remove();
    });

    // Fecha ao clicar fora
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.remove();
      }
    });

    // Mostra erro na tela também
    this.contentContainer.innerHTML = `
      <div style="padding: 2rem; text-align: center; color: #d32f2f;">
        <h2>Erro ao carregar a tela: ${section}</h2>
        <p style="margin: 1rem 0;">Arquivo aberto via file:// - Servidor HTTP necessário</p>
        <p style="font-size: 0.875rem; margin-top: 1rem; color: #666;">
          Veja as instruções no modal acima ou execute: <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">cd frontend && php -S localhost:8001</code>
        </p>
      </div>
    `;
  }
}

// Instância global do router
const router = new Router();

// Inicializa o router quando o DOM estiver pronto
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    router.init('.content');
  });
} else {
  router.init('.content');
}

