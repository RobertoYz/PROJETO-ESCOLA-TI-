import { usuarioEstaAutenticado } from '../servicos/servicoAutenticacao.js';
import { buscarPlanos, confirmarSelecaoPlano } from '../servicos/servicoPlanos.js';

const containerListaPlanos = document.getElementById('lista-planos');
const divMensagemErro = document.getElementById('mensagem-erro');

if (!usuarioEstaAutenticado()) {
    window.location.href = 'cadastro.html';
}

function exibirErro(mensagem) {
    divMensagemErro.textContent = mensagem;
    divMensagemErro.hidden = false;
}

async function carregarPlanosNaTela() {
    try {
        const planos = await buscarPlanos();
        containerListaPlanos.innerHTML = '';

        const planosExibicao = planos.length > 0 ? planos : [
            { id: 1, nome: 'Standard' },
            { id: 2, nome: 'Pro' },
            { id: 3, nome: 'Enterprise' }
        ];

        planosExibicao.forEach((plano) => {
            const bloco = document.createElement('div');
            bloco.className = 'bloco-plano';

            const titulo = document.createElement('h2');
            titulo.textContent = plano.nome;

            const botao = document.createElement('button');
            botao.type = 'button';
            botao.textContent = 'Selecionar Plano';

            botao.addEventListener('click', async () => {
                divMensagemErro.hidden = true;
                botao.disabled = true;
                try {
                    const resposta = await confirmarSelecaoPlano(plano.id);
                    const urlPagamento = resposta.checkout_url || (resposta.data && resposta.data.checkout_url);
                    if (urlPagamento) {
                        window.location.href = urlPagamento;
                    } else {
                        window.location.href = 'login.html';
                    }
                } catch (erro) {
                    exibirErro(erro.message);
                    botao.disabled = false;
                }
            });

            bloco.appendChild(titulo);
            bloco.appendChild(botao);
            containerListaPlanos.appendChild(bloco);
        });
    } catch (erro) {
        exibirErro(erro.message);
    }
}

carregarPlanosNaTela();
