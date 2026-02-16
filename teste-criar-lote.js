// ============================================
// SCRIPT DE TESTE - CRIAR LOTE "ÁGUA COM GÁS"
// ============================================
// Execute este script no console do navegador (F12)
// Cole todo o código abaixo no console e pressione Enter

(async function criarLoteAguaGas() {
  console.log("🚀 Iniciando teste de criação de lote 'Água com Gás'...");
  
  try {
    // Usar API_URL e fetchJSON se disponíveis, senão usar fetch direto
    const apiUrl = window.API_URL || 'http://localhost:5000/api';
    const userId = window.currentUser?.id || '1';
    
    const fetchAPI = async (path, options = {}) => {
      if (window.fetchJSON) {
        return await window.fetchJSON(path, options);
      }
      const url = `${apiUrl}${path}`;
      const response = await fetch(url, {
        ...options,
        headers: {
          'Content-Type': 'application/json',
          'X-Usuario-Id': userId,
          ...(options.headers || {})
        }
      });
      const data = await response.json();
      if (!response.ok) {
        const error = new Error(data.error || 'Erro na requisição');
        error.responseData = data;
        error.status = response.status;
        throw error;
      }
      return data;
    };
    
    // 1. Buscar produtos
    console.log("📦 Buscando produtos...");
    const produtos = await fetchAPI('/produtos?todas=1');
    console.log("✅ Produtos encontrados:", produtos.length);
    
    // 2. Procurar ou criar produto "água com gás"
    let produtoAgua = produtos.find(p => 
      p.nome && (
        p.nome.toLowerCase().includes('água') || 
        p.nome.toLowerCase().includes('agua')
      ) && (
        p.nome.toLowerCase().includes('gás') || 
        p.nome.toLowerCase().includes('gas') ||
        p.nome.toLowerCase().includes('com gas')
      )
    );
    
    if (!produtoAgua) {
      console.log("📝 Produto 'água com gás' não encontrado. Criando...");
      
      // Buscar unidades
      const unidades = await fetchAPI('/unidades?todas=1');
      if (!unidades || unidades.length === 0) {
        throw new Error("❌ Nenhuma unidade encontrada. Crie uma unidade primeiro.");
      }
      
      const primeiraUnidade = unidades[0];
      console.log("🏢 Usando unidade:", primeiraUnidade.nome);
      
      // Criar produto
      const novoProduto = {
        nome: "Água com Gás",
        categoria: "BEBIDAS",
        unidade_base: "UND",
        unidade_id: primeiraUnidade.id || null,
        ativo: 1
      };
      
      produtoAgua = await fetchAPI('/produtos', {
        method: 'POST',
        body: JSON.stringify(novoProduto)
      });
      
      console.log("✅ Produto criado:", produtoAgua);
    } else {
      console.log("✅ Produto encontrado:", produtoAgua.nome, "(ID:", produtoAgua.id + ")");
    }
    
    // 3. Buscar unidades
    const unidades = await fetchAPI('/unidades?todas=1');
    if (!unidades || unidades.length === 0) {
      throw new Error("❌ Nenhuma unidade encontrada.");
    }
    
    const unidade = unidades[0];
    console.log("🏢 Usando unidade:", unidade.nome, "(ID:", unidade.id + ")");
    
    // 4. Preparar dados do lote
    const dataValidade = new Date();
    dataValidade.setDate(dataValidade.getDate() + 365); // Válido por 1 ano
    const dataValidadeStr = dataValidade.toISOString().split('T')[0];
    
    const loteData = {
      produto_id: produtoAgua.id,
      unidade_id: unidade.id,
      codigo_lote: `AGUA-GAS-${Date.now()}`,
      quantidade: 24,
      custo_unitario: 2.50,
      data_validade: dataValidadeStr
    };
    
    console.log("📤 Criando lote com dados:", loteData);
    
    // 5. Criar lote
    const resultado = await fetchAPI('/lotes', {
      method: 'POST',
      body: JSON.stringify(loteData)
    });
    
    console.log("✅ Lote criado com sucesso!");
    console.log("📋 Detalhes do lote:");
    console.log("   - ID:", resultado.id);
    console.log("   - Produto:", resultado.produto_nome || produtoAgua.nome);
    console.log("   - Código:", resultado.codigo_lote);
    console.log("   - Quantidade:", resultado.quantidade);
    console.log("   - Custo unitário: R$", resultado.custo_unitario);
    console.log("   - Validade:", resultado.data_validade);
    console.log("   - Status:", resultado.status || "ATIVO");
    
    // 6. Recarregar lotes na interface se a função existir
    if (typeof loadLotes === 'function') {
      console.log("🔄 Recarregando lotes na interface...");
      await loadLotes();
      console.log("✅ Interface atualizada!");
    } else if (window.loadLotes) {
      await window.loadLotes();
    }
    
    alert("✅ Lote criado com sucesso!\n\n" +
          "Produto: " + (resultado.produto_nome || produtoAgua.nome) + "\n" +
          "Código: " + resultado.codigo_lote + "\n" +
          "Quantidade: " + resultado.quantidade + "\n" +
          "Validade: " + resultado.data_validade);
    
    return resultado;
    
  } catch (error) {
    console.error("❌ Erro no teste:", error);
    console.error("Mensagem:", error.message);
    if (error.responseData) {
      console.error("Resposta do servidor:", error.responseData);
    }
    alert("❌ Erro ao criar lote:\n\n" + error.message);
    throw error;
  }
})();
