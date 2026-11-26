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

export function rodapePortal(startup, podeEditar) {
    const rodape = document.createElement('div');
    rodape.className = 'rodape-portal';
    rodape.addEventListener('click', evento => evento.stopPropagation());
    const perfil = startup.portal_acesso;
    function botao(texto, rotulo, acao) {
        const el = document.createElement('button');
        el.type = 'button';
        el.textContent = texto;
        el.setAttribute('aria-label', rotulo);
        el.onclick = acao;
        rodape.append(el);
    }
    if (perfil.existe) {
        const estado = document.createElement('span');
        estado.className = perfil.ativo ? 'estado ativo' : 'estado';
        estado.textContent = perfil.ativo ? '● Portal ativo' : '○ Portal pausado';
        rodape.append(estado);
        if (podeEditar) {
            botao('⚙', 'Configurar acesso ao portal', () => abrir(startup));
        }
    } else if (podeEditar) {
        botao('+ Acesso ao portal', 'Criar acesso ao portal', () => abrir(startup));
    }
    return rodape;
}
