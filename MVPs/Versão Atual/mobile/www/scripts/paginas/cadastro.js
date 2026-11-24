import { requisicaoApi } from '../servicos/api.js';
import { limparDadosPendencia } from '../servicos/servicoAutenticacao.js';

const formulario = document.getElementById('formulario-cadastro');
const campoNomeAgencia = document.getElementById('campo-nome-agencia');
const campoNomeUsuario = document.getElementById('campo-nome-usuario');
const campoEmail = document.getElementById('campo-email');
const campoSenha = document.getElementById('campo-senha');
const divMensagemErro = document.getElementById('mensagem-erro');
const botaoConfirmar = document.getElementById('botao-confirmar');

const verificacao = document.getElementById('formulario-verificacao');
const campoCodigo = document.getElementById('codigo');
const reenviar = document.getElementById('reenviar');
const voltar = document.getElementById('alterar-email');
const confirmar = verificacao.querySelector('[type="submit"]');
const titulo = document.querySelector('.form-title');
const subtitulo = document.querySelector('.form-subtitle');
let cadastro;
let intervalo;
let disponivelEm = 0;
let ocupado = false;


const aceite = document.getElementById('aceite');
const dialogo = document.getElementById('documentos-legais');
const painel = document.getElementById('painel-documento');
const estado = document.getElementById('estado-documentos');
const concordar = document.getElementById('confirmar-documentos');
const abas = [...dialogo.querySelectorAll('[role="tab"]')];
let documentos;
let metadados;
let abaAtual = 'termos_uso';
let acionador;
let pendente;

function limparAceite() {
    aceite.checked = false;
    sessionStorage.removeItem('aceite_cadastro');
}

function registrarEscolha() {
    if (!metadados || !aceite.checked) return limparAceite();
    sessionStorage.setItem('aceite_cadastro', JSON.stringify(metadados));
}

function exibirDocumento(tipo) {
    abaAtual = tipo;
    for (const aba of abas) {
        const selecionada = aba.id === 'aba-' + tipo;
        aba.setAttribute('aria-selected', String(selecionada));
        aba.tabIndex = selecionada ? 0 : -1;
    }
    painel.setAttribute('aria-labelledby', 'aba-' + tipo);
    painel.replaceChildren();
    if (!documentos) return;
    const documento = documentos[tipo];
    const versao = document.createElement('p');
    versao.textContent = documento.titulo + ' · Versão ' + documento.versao;
    painel.append(versao);
    for (const secao of documento.conteudo_json) {
        const titulo = document.createElement('h3');
        const texto = document.createElement('p');
        titulo.textContent = secao.titulo;
        texto.textContent = secao.texto;
        painel.append(titulo, texto);
    }
    painel.scrollTop = 0;
}

async function atualizarDocumentos() {
    aceite.disabled = concordar.disabled = true;
    estado.textContent = 'Carregando documentos…';
    try {
        const atuais = await requisicaoApi('/termos/vigentes');
        for (const tipo of ['termos_uso', 'politica_privacidade']) {
            const secoes = atuais[tipo]?.conteudo_json;
            if (!Array.isArray(secoes) || !secoes.length ||
                !secoes.every(secao => typeof secao?.titulo === 'string' && typeof secao.texto === 'string')) {
                throw new Error('Documento indisponível.');
            }
        }
        const novos = { termos_aceitos: true };
        for (const campo of ['documento_termos_id', 'documento_privacidade_id', 'versao_termos', 'versao_privacidade', 'hash_termos', 'hash_privacidade']) {
            if (!atuais[campo]) throw new Error('Publicação incompleta.');
            novos[campo] = atuais[campo];
        }
        const mudaram = metadados && JSON.stringify(novos) !== JSON.stringify(metadados);
        if (mudaram) {
            limparAceite();
            exibirErro('Os documentos mudaram. Confira as novas versões e confirme novamente.');
        }
        documentos = atuais;
        metadados = novos;
        aceite.disabled = concordar.disabled = false;
        estado.textContent = mudaram ? 'Documentos atualizados. Confira as novas versões.' : '';
    } catch {
        documentos = metadados = null;
        limparAceite();
        estado.textContent = 'Não foi possível carregar os documentos. Feche e abra novamente para tentar.';
        exibirErro(estado.textContent);
    }
    exibirDocumento(abaAtual);
}

function carregarDocumentos() {
    pendente ??= atualizarDocumentos().finally(() => { pendente = null; });
    return pendente;
}

limparAceite();
carregarDocumentos();
window.addEventListener('pageshow', evento => {
    if (evento.persisted) {
        limparAceite();
        retornarAoFormulario();
        carregarDocumentos();
    }
});
aceite.addEventListener('change', registrarEscolha);
for (const botao of formulario.querySelectorAll('[data-documento]')) {
    botao.addEventListener('click', () => {
        acionador = botao;
        exibirDocumento(botao.dataset.documento);
        dialogo.showModal();
        document.getElementById('aba-' + abaAtual).focus();
        carregarDocumentos();
    });
}
for (const aba of abas) {
    aba.addEventListener('click', () => exibirDocumento(aba.id.slice(4)));
    aba.addEventListener('keydown', evento => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(evento.key)) return;
        evento.preventDefault();
        const proxima = evento.key === 'Home' ? abas[0] : evento.key === 'End' ? abas[1] : abas.find(item => item !== aba);
        proxima.click();
        proxima.focus();
    });
}
document.getElementById('fechar-documentos').onclick = () => dialogo.close();
dialogo.addEventListener('close', () => acionador?.focus());
dialogo.addEventListener('click', evento => {
    const limites = dialogo.getBoundingClientRect();
    if (evento.target === dialogo && (evento.clientX < limites.left || evento.clientX > limites.right
        || evento.clientY < limites.top || evento.clientY > limites.bottom)) dialogo.close();
});
dialogo.addEventListener('keydown', evento => {
    if (evento.key !== 'Tab') return;
    const focaveis = [...dialogo.querySelectorAll('button:not(:disabled), [tabindex="0"]')].filter(item => item.tabIndex >= 0);
    const primeiro = focaveis[0];
    const ultimo = focaveis.at(-1);
    if (evento.shiftKey && document.activeElement === primeiro || !evento.shiftKey && document.activeElement === ultimo) {
        evento.preventDefault();
        (evento.shiftKey ? ultimo : primeiro).focus();
    }
});
concordar.onclick = () => {
    aceite.checked = true;
    registrarEscolha();
    dialogo.close();
};
if (new URLSearchParams(location.search).has('rever-termos')) {
    exibirErro('Confira os documentos vigentes e confirme novamente para continuar.');
}

function exibirErro(mensagem) {
    divMensagemErro.textContent = mensagem;
    divMensagemErro.hidden = false;
}

function limparErro() {
    divMensagemErro.textContent = '';
    divMensagemErro.hidden = true;
}

try {
    const dadosSalvos = sessionStorage.getItem('dados_pre_cadastro');
    if (dadosSalvos) {
        const dados = JSON.parse(dadosSalvos);
        sessionStorage.removeItem('dados_pre_cadastro');
        if (dados.nome_agencia && campoNomeAgencia) {
            campoNomeAgencia.value = dados.nome_agencia;
        }
        if (dados.name && campoNomeUsuario) {
            campoNomeUsuario.value = dados.name;
        }
        if (dados.email && campoEmail) {
            campoEmail.value = dados.email;
        }
    }
} catch {
    sessionStorage.removeItem('dados_pre_cadastro');
}

[campoNomeAgencia, campoNomeUsuario, campoEmail, campoSenha].forEach((campo) => {
    campo?.addEventListener('input', () => {
        if (!divMensagemErro.hidden) {
            limparErro();
        }
    });
});

formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limparErro();

    const nomeAgencia = campoNomeAgencia ? campoNomeAgencia.value.trim() : '';
    const nomeUsuario = campoNomeUsuario.value.trim();
    const email = campoEmail.value.trim().toLowerCase();
    const senha = campoSenha.value;

    if (!nomeAgencia || !nomeUsuario || !email || !senha) {
        exibirErro('Preencha todos os campos obrigatórios, incluindo o nome da consultoria/agência.');
        return;
    }

    const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!regexEmail.test(email)) {
        exibirErro('Informe um endereço de e-mail válido.');
        return;
    }

    if (senha.length < 8) {
        exibirErro('A senha deve ter no mínimo 8 caracteres.');
        return;
    }

    if (ocupado) return;
    cadastro = { nome_agencia: nomeAgencia, name: nomeUsuario, email, password: senha };
    await enviarCodigo();
});

function atualizarBotoes() {
    const segundos = Math.max(0, Math.ceil((disponivelEm - Date.now()) / 1000));
    reenviar.textContent = segundos ? `Reenviar código em ${segundos}s` : 'Reenviar código';
    reenviar.disabled = ocupado || segundos > 0;
    confirmar.disabled = ocupado;
    voltar.disabled = ocupado;
    botaoConfirmar.disabled = ocupado;
    if (!segundos) clearInterval(intervalo);
}

function mostrarVerificacao(segundos) {
    clearInterval(intervalo);
    disponivelEm = Date.now() + segundos * 1000;
    formulario.hidden = true;
    verificacao.hidden = false;
    titulo.textContent = 'Confirme seu e-mail';
    subtitulo.textContent = 'Informe o código para escolher seu plano.';
    document.getElementById('destino-codigo').textContent = `Enviado para ${cadastro.email}. Válido por 15 minutos.`;
    campoCodigo.value = '';
    campoCodigo.focus();
    intervalo = setInterval(atualizarBotoes, 250);
}

async function enviarCodigo() {
    if (!aceite.checked || !documentos) {
        exibirErro('Carregue os documentos e confirme o aceite antes de solicitar o código.');
        return;
    }
    ocupado = true;
    atualizarBotoes();
    limparErro();
    try {
        await carregarDocumentos();
        if (!aceite.checked || !documentos) {
            retornarAoFormulario();
            exibirErro('Confira os documentos vigentes e confirme novamente.');
            return;
        }
        const resposta = await requisicaoApi('/auth/enviar-codigo-verificacao', {
            method: 'POST', body: JSON.stringify({ email: cadastro.email, nome: cadastro.name })
        });
        if (aceite.checked && documentos) mostrarVerificacao(resposta.reenvio_em_segundos);
    } catch (erro) {
        if (erro.dados?.segundos_restantes) mostrarVerificacao(erro.dados.segundos_restantes);
        exibirErro(erro.message || 'Não foi possível enviar o código.');
    } finally {
        ocupado = false;
        atualizarBotoes();
    }
}

campoCodigo.addEventListener('input', () => {
    campoCodigo.value = campoCodigo.value.replace(/\D/g, '').slice(0, 6);
    limparErro();
});

reenviar.addEventListener('click', enviarCodigo);
function retornarAoFormulario() {
    clearInterval(intervalo);
    verificacao.hidden = true;
    formulario.hidden = false;
    titulo.textContent = 'Criar conta de consultoria';
    subtitulo.textContent = 'Cadastre seus dados para acessar a inteligência de editais';
    cadastro = null;
    limparErro();
    campoEmail.focus();
}
voltar.addEventListener('click', retornarAoFormulario);

verificacao.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    if (ocupado) return;
    ocupado = true;
    atualizarBotoes();
    limparErro();
    try {
        await carregarDocumentos();
        if (!aceite.checked || !documentos) {
            retornarAoFormulario();
            exibirErro('Confira os documentos vigentes e confirme novamente.');
            return;
        }
        const resposta = await requisicaoApi('/auth/verificar-codigo-email', {
            method: 'POST', body: JSON.stringify({ email: cadastro.email, codigo: campoCodigo.value })
        });
        limparDadosPendencia();
        sessionStorage.setItem('dados_pre_cadastro', JSON.stringify({
            ...cadastro, email_verificado: cadastro.email, verification_token: resposta.verification_token
        }));
        window.location.replace('selecionar-plano.html');
    } catch (erro) {
        const restantes = erro.dados?.tentativas_restantes;
        let mensagem = erro.message || 'Não foi possível confirmar o código.';
        if (restantes === 0) mensagem += ' Solicite outro código.';
        else if (restantes !== undefined) mensagem += ` Tentativas restantes: ${restantes}.`;
        exibirErro(mensagem);
    } finally {
        ocupado = false;
        atualizarBotoes();
    }
});

if (new URLSearchParams(location.search).has('revalidar')) {
    exibirErro('Sua confirmação de e-mail perdeu a validade. Informe a senha e solicite um novo código.');
}
