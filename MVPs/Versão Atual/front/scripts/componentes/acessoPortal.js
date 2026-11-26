import { abrirArquivos } from './arquivosStartup.js';
import { requisicaoApi } from '../servicos/api.js';

const dialogo = document.getElementById('dialogoPortal');
const form = dialogo.querySelector('form');
const campos = form.elements;
const aviso = dialogo.querySelector('[role=status]');
let selecionada;

function abrir(startup) {
    selecionada = startup;
    const perfil = startup.portal_acesso;
    campos.login.value = perfil.login || '';
    campos.ativo.checked = perfil.ativo;
    atualizarRotuloEstado();
    campos.ativo.parentElement.hidden = !perfil.existe;
    campos.password.required = !perfil.existe;
    document.getElementById('rotuloSenhaPortal').textContent = perfil.existe ? 'Nova senha (opcional)' : 'Senha';
    campos.salvar.textContent = perfil.existe ? 'Salvar alterações' : 'Criar acesso';
    document.getElementById('tituloPortal').textContent = 'Acesso de ' + (startup.nome_fantasia || startup.razao_social);
    aviso.textContent = '';
    dialogo.showModal();
    campos.login.focus();
}

function atualizarRotuloEstado() {
    document.getElementById('estadoPortal').textContent = campos.ativo.checked ? 'Acesso ativo' : 'Acesso pausado';
}

campos.ativo.onchange = atualizarRotuloEstado;
dialogo.querySelector('.fechar').onclick = () => dialogo.close();
dialogo.addEventListener('close', () => form.reset());
campos.gerar.onclick = () => {
    campos.password.value = Array.from(crypto.getRandomValues(new Uint8Array(16)), n => n.toString(16).padStart(2, '0')).join('');
    aviso.textContent = 'Senha gerada. As credenciais estão disponíveis para cópia.';
};

campos.copiar.onclick = async () => {
    if (!campos.login.reportValidity()) return;
    const endereco = window.Capacitor || location.protocol === 'capacitor:' || navigator.userAgent.includes('wv')
        ? 'https://anjonexus.com.br/paginas/login.html' : new URL('login.html', location.href).href;
    const linhas = [endereco, 'Usuário: ' + campos.login.value];
    if (campos.password.value) linhas.push('Senha: ' + campos.password.value);
    try {
        await navigator.clipboard.writeText(linhas.join('\n'));
        aviso.textContent = 'Credenciais copiadas.';
    } catch {
        aviso.textContent = 'O navegador bloqueou a cópia. Selecione os campos para copiar.';
    }
};

form.onsubmit = async evento => {
    evento.preventDefault();
    const startup = selecionada;
    const existe = startup.portal_acesso.existe;
    const dados = { login: campos.login.value };
    if (existe) dados.ativo = campos.ativo.checked;
    if (campos.password.value) dados.password = campos.password.value;
    campos.salvar.disabled = true;
    try {
        startup.portal_acesso = await requisicaoApi('/startups/' + startup.id + '/portal-acesso', {
            method: existe ? 'PATCH' : 'POST', body: JSON.stringify(dados)
        });
        document.dispatchEvent(new Event('portal-atualizado'));
        dialogo.close();
    } catch (erro) {
        aviso.textContent = erro.message;
    } finally {
        campos.salvar.disabled = false;
    }
};

export function rodapePortal(startup, podeEditar, editarDados) {
    const rodape = document.createElement('div');
    rodape.className = 'rodape-portal';
    rodape.onclick = evento => evento.stopPropagation();
    const perfil = startup.portal_acesso;
    const estado = document.createElement('span');
    estado.className = perfil?.ativo ? 'estado ativo' : 'estado';
    estado.textContent = perfil?.existe ? (perfil.ativo ? '● Portal ativo' : '○ Portal pausado') : '○ Sem portal';

    const engrenagem = document.createElement('button');
    engrenagem.type = 'button';
    engrenagem.textContent = '⚙';
    engrenagem.setAttribute('aria-label', 'Opções de ' + (startup.nome_fantasia || startup.razao_social));
    const menu = document.createElement('div');
    menu.className = 'menu-startup';
    menu.id = 'menu-startup-' + startup.id;
    menu.popover = 'auto';
    const nativo = typeof menu.showPopover === 'function';
    engrenagem.setAttribute('aria-controls', menu.id);
    engrenagem.setAttribute('aria-expanded', 'false');
    function posicionar() {
        const caixa = engrenagem.getBoundingClientRect();
        menu.style.right = Math.max(8, document.documentElement.clientWidth - caixa.right) + 'px';
        const abaixo = innerHeight - caixa.bottom >= 150;
        menu.style.top = abaixo ? caixa.bottom + 'px' : 'auto';
        menu.style.bottom = abaixo ? 'auto' : innerHeight - caixa.top + 'px';
    }
    function fechar() {
        if (nativo) menu.hidePopover();
        else menu.hidden = true;
        engrenagem.setAttribute('aria-expanded', 'false');
    }
    if (nativo) {
        engrenagem.popoverTargetElement = menu;
        menu.addEventListener('beforetoggle', evento => {
            const aberto = evento.newState === 'open';
            engrenagem.setAttribute('aria-expanded', String(aberto));
            if (aberto) posicionar();
        });
    } else {
        menu.hidden = true;
        engrenagem.onclick = () => {
            if (!menu.hidden) return fechar();
            posicionar();
            menu.hidden = false;
            engrenagem.setAttribute('aria-expanded', 'true');
            menu.querySelector('button').focus();
        };
        menu.onfocusout = evento => {
            if (!rodape.contains(evento.relatedTarget)) fechar();
        };
        menu.onkeydown = evento => {
            if (evento.key === 'Escape') { fechar(); engrenagem.focus(); }
        };
    }

    for (const [texto, acao, permitido] of [
        ['Editar dados e exclusão', editarDados, true],
        ['Perfil de visualização', () => abrir(startup), podeEditar],
        ['Arquivos anexados', () => abrirArquivos(startup), true]
    ]) {
        const botao = document.createElement('button');
        botao.type = 'button';
        botao.textContent = texto;
        botao.disabled = !permitido;
        botao.onclick = () => { fechar(); engrenagem.focus(); acao(); };
        menu.append(botao);
    }
    rodape.append(estado, engrenagem, menu);
    return rodape;
}
