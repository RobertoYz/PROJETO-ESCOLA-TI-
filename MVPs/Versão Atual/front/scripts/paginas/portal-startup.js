import { requisicaoApi } from '../servicos/api.js';
import { removerToken } from '../servicos/servicoAutenticacao.js';
import { calcularDiasRestantes } from '../servicos/servicoEditais.js';

const mensagem = document.getElementById('mensagem');

function elemento(tag, texto, classe) {
    const el = document.createElement(tag);
    el.textContent = texto;
    if (classe) el.className = classe;
    return el;
}

function dataLocal(valor) {
    return valor.slice(0, 10).split('-').reverse().join('/');
}

async function carregar() {
    try {
        const { startup, agencia, resumo, processos } = await requisicaoApi('/portal/acompanhamento');
        document.getElementById('nome').textContent = startup.nome;
        document.getElementById('agencia').textContent = 'Acompanhamento por ' + agencia.nome;
        const faixa = document.getElementById('resumo');
        faixa.append(elemento('strong', 'Total de processos: ' + resumo.total_processos));
        for (const [etapa, total] of Object.entries(resumo.por_etapa)) {
            faixa.append(elemento('span', etapa + ': ' + total));
        }
        mensagem.textContent = processos.length ? '' : 'Quando a agência vincular um processo à sua startup, ele aparecerá aqui.';
        for (const processo of processos) {
            const item = document.createElement('article');
            item.append(elemento('span', processo.etapa, 'etapa'), elemento('h3', processo.titulo));
            if (processo.edital && processo.edital !== processo.titulo) item.append(elemento('p', processo.edital));
            if (processo.descricao) item.append(elemento('p', processo.descricao));
            if (processo.prazo) {
                const prazo = dataLocal(processo.prazo);
                const dias = calcularDiasRestantes(prazo);
                item.append(elemento('p', 'Prazo: ' + prazo, dias >= 0 && dias <= 7 ? 'urgente' : ''));
            }
            item.append(elemento('small', 'Atualizado em ' + dataLocal(processo.atualizado_em)));
            document.getElementById('processos').append(item);
        }
    } catch (erro) {
        mensagem.textContent = erro.message;
    }
}

document.getElementById('sair').addEventListener('click', async () => {
    try {
        await requisicaoApi('/auth/logout', { method: 'POST' });
    } catch (erro) {
        if (erro.status !== 401) {
            mensagem.textContent = 'Falha ao encerrar a sessão. Tente novamente.';
            return;
        }
    }
    removerToken();
    location.replace('login.html');
});

carregar();
