import { requisicaoApi } from '../servicos/api.js';
import { baixarAnexo, excluirAnexo } from '../servicos/arquivosKanban.js';

const dialogo = document.getElementById('dialogoArquivos');
const aviso = document.getElementById('avisoArquivos');
const resumo = document.getElementById('resumoArquivos');
const lista = document.getElementById('listaArquivos');
let versao = 0;
dialogo.addEventListener('close', () => ++versao);

function botao(texto, acao, atual) {
    const elemento = document.createElement('button');
    elemento.type = 'button';
    elemento.textContent = texto;
    elemento.onclick = async () => {
        elemento.disabled = true;
        try {
            await acao();
        } catch (erro) {
            if (atual === versao && erro.name !== 'AbortError') aviso.textContent = erro.message;
        } finally {
            elemento.disabled = false;
        }
    };
    return elemento;
}

export async function abrirArquivos(startup) {
    const atual = ++versao;
    document.getElementById('tituloArquivos').textContent = 'Arquivos de ' + (startup.nome_fantasia || startup.razao_social);
    lista.replaceChildren();
    resumo.textContent = '';
    aviso.textContent = 'Carregando arquivos…';
    if (!dialogo.open) dialogo.showModal();
    try {
        const dados = await requisicaoApi('/startups/' + startup.id + '/arquivos');
        if (atual !== versao) return;
        resumo.textContent = dados.usado_mb.toLocaleString('pt-BR') + ' MB em ' + dados.total_arquivos + ' arquivo(s)';
        aviso.textContent = dados.total_arquivos ? '' : 'Esta startup ainda não tem arquivos anexados.';
        for (const arquivo of dados.arquivos) {
            const item = document.createElement('li');
            const nome = document.createElement('strong');
            nome.textContent = arquivo.nome_arquivo;
            const detalhes = document.createElement('span');
            detalhes.textContent = [arquivo.card_titulo, arquivo.etapa_nome, arquivo.tamanho_mb.toLocaleString('pt-BR') + ' MB'].join(' · ');
            const acoes = document.createElement('div');
            acoes.append(botao('Baixar', () => baixarAnexo(arquivo.id, arquivo.nome_arquivo), atual));
            if (dados.pode_excluir) {
                acoes.append(botao('Excluir', async () => {
                    if (!window.confirm('Excluir permanentemente ' + arquivo.nome_arquivo + '?')) return;
                    await excluirAnexo(arquivo.id);
                    if (atual === versao) await abrirArquivos(startup);
                }, atual));
            }
            item.append(nome, detalhes, acoes);
            lista.append(item);
        }
    } catch (erro) {
        if (atual === versao) aviso.textContent = erro.message;
    }
}
