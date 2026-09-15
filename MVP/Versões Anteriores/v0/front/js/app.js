document.addEventListener('DOMContentLoaded', () => {
    console.log('App inicializado.');

    const btnTestar = document.getElementById('btn-testar-api');
    const responseDiv = document.getElementById('api-response');

    btnTestar.addEventListener('click', async () => {
        try {
            // URL da sua API Laravel rodando localmente
            const apiUrl = 'http://localhost:8000/api/ping'; 
            
            // Mock visual para teste inicial (pode ser substituído pelo fetch real)
            const data = { mensagem: 'Conexão simulada com o backend concluída com sucesso!' };

            responseDiv.classList.remove('hidden');
            responseDiv.innerHTML = `<p class="text-green-600 font-medium">Resposta: ${data.mensagem}</p>`;
            
        } catch (error) {
            console.error('Erro de conexão:', error);
            responseDiv.classList.remove('hidden');
            responseDiv.innerHTML = `<p class="text-red-600 font-medium">Erro ao conectar na API. Verifique se o servidor Laravel está rodando na porta 8000.</p>`;
        }
    });
});
