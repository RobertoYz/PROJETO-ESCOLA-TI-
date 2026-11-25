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
const DOCUMENTOS_FALLBACK = {
    documento_termos_id: 1,
    versao_termos: '1.0.0',
    hash_termos: 'b6f4e137887e47f02379b392a831e5f8f537651a54728562479e09961db63a56',
    documento_privacidade_id: 2,
    versao_privacidade: '1.0.0',
    hash_privacidade: '9984955b9671d1544a0fe0b9d9c22d1df913c5efea20f8fe64bcfd27c62b7cf5',
    termos_uso: {
        titulo: 'Termos de Uso',
        versao: '1.0.0',
        conteudo_json: [
            { titulo: 'Quem presta o serviço', texto: 'Anjo Nexus é uma plataforma para consultorias que acompanham editais, organizam startups, propostas e equipes. Canal para dúvidas: projetoanjonexus@gmail.com.' },
            { titulo: 'Conta e equipe', texto: 'Informe dados corretos e confirme seu e-mail com o código recebido. O titular da agência administra os convites e permissões da equipe.' },
            { titulo: 'Propostas e inteligência artificial', texto: 'Sugestões, correspondências com editais e minutas podem conter erros. Uma pessoa qualificada deve conferir o edital original antes do envio.' },
            { titulo: 'Plano e pagamento', texto: 'Confira preço e cotas do plano antes do checkout. A cobrança é processada pela plataforma de pagamento.' },
            { titulo: 'Vencimento e encerramento', texto: 'O ciclo prevê carência após o vencimento e posterior suspensão.' },
            { titulo: 'Direitos e responsabilidades', texto: 'A plataforma responde pelas próprias falhas nos limites da legislação aplicável.' },
            { titulo: 'Criações e uso permitido', texto: 'O acesso permite usar a plataforma nos limites do plano contratado.' },
            { titulo: 'Versões e foro', texto: 'Fica eleito o foro da Comarca de Maringá – PR.' }
        ]
    },
    politica_privacidade: {
        titulo: 'Política de Privacidade',
        versao: '1.0.0',
        conteudo_json: [
            { titulo: 'Quem trata os dados', texto: 'Anjo Nexus define as finalidades e meios do tratamento dos dados de conta e autenticação.' },
            { titulo: 'Do pré-cadastro à assinatura', texto: 'Usamos nome, nome da consultoria e e-mail. Enviamos um código válido por 15 minutos.' },
            { titulo: 'Por que tratamos', texto: 'Tratamos os dados necessários ao cadastro, à verificação e à prestação do serviço (LGPD).' },
            { titulo: 'Registro da sua escolha', texto: 'Ao confirmar, você aceita os Termos e declara ciência desta Política.' },
            { titulo: 'Serviços externos', texto: 'O transporte de e-mail entrega códigos e avisos. Gateways processam o checkout.' },
            { titulo: 'Segurança e registros', texto: 'A plataforma aplica controle de acesso por agência, permissões e autenticação.' },
            { titulo: 'Prazos e descarte', texto: 'Rotinas periódicas limpam verificações expiradas.' },
            { titulo: 'Seus direitos e atualizações', texto: 'Pelo canal de privacidade, solicite confirmação, acesso, correção ou eliminação dos dados.' }
        ]
    }
};

let documentos = DOCUMENTOS_FALLBACK;
let metadados = {
    termos_aceitos: true,
    documento_termos_id: DOCUMENTOS_FALLBACK.documento_termos_id,
    versao_termos: DOCUMENTOS_FALLBACK.versao_termos,
    hash_termos: DOCUMENTOS_FALLBACK.hash_termos,
    documento_privacidade_id: DOCUMENTOS_FALLBACK.documento_privacidade_id,
    versao_privacidade: DOCUMENTOS_FALLBACK.versao_privacidade,
    hash_privacidade: DOCUMENTOS_FALLBACK.hash_privacidade,
};
let abaAtual = 'termos_uso';
let acionador;
let pendente;

function limparAceite() {
    aceite.checked = false;
    sessionStorage.removeItem('aceite_cadastro');
}

function registrarEscolha() {
    if (!aceite.checked) {
        sessionStorage.removeItem('aceite_cadastro');
        return;
    }
    if (!metadados) {
        metadados = {
            termos_aceitos: true,
            documento_termos_id: DOCUMENTOS_FALLBACK.documento_termos_id,
            versao_termos: DOCUMENTOS_FALLBACK.versao_termos,
            hash_termos: DOCUMENTOS_FALLBACK.hash_termos,
            documento_privacidade_id: DOCUMENTOS_FALLBACK.documento_privacidade_id,
            versao_privacidade: DOCUMENTOS_FALLBACK.versao_privacidade,
            hash_privacidade: DOCUMENTOS_FALLBACK.hash_privacidade,
        };
    }
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
    aceite.disabled = false;
    concordar.disabled = false;
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
        if (mudaram && aceite.checked) {
            limparAceite();
            exibirErro('Os documentos mudaram. Confira as novas versões e confirme novamente.');
        }
        documentos = atuais;
        metadados = novos;
        aceite.disabled = concordar.disabled = false;
        estado.textContent = mudaram ? 'Documentos atualizados. Confira as novas versões.' : '';
        if (aceite.checked) {
            registrarEscolha();
        }
    } catch (e) {
        console.warn('Utilizando documentos legais locais:', e);
        if (!documentos) {
            documentos = DOCUMENTOS_FALLBACK;
            metadados = {
                termos_aceitos: true,
                documento_termos_id: DOCUMENTOS_FALLBACK.documento_termos_id,
                versao_termos: DOCUMENTOS_FALLBACK.versao_termos,
                hash_termos: DOCUMENTOS_FALLBACK.hash_termos,
                documento_privacidade_id: DOCUMENTOS_FALLBACK.documento_privacidade_id,
                versao_privacidade: DOCUMENTOS_FALLBACK.versao_privacidade,
                hash_privacidade: DOCUMENTOS_FALLBACK.hash_privacidade,
            };
        }
        aceite.disabled = concordar.disabled = false;
        estado.textContent = '';
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
    botao.addEventListener('click', (evento) => {
        evento.preventDefault();
        evento.stopPropagation();
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

    const regexEmail = /^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}$/;
    if (!regexEmail.test(email)) {
        exibirErro('Informe um endereço de e-mail válido com domínio completo.');
        return;
    }

    const dominiosProibidos = [
        'teste.com', 'test.com', 'teste.com.br', 'test.com.br',
        'exemplo.com', 'example.com', 'example.org', 'example.net',
        'fake.com', 'fakedoamin.com', 'emailfake.com', 'naoexiste.com',
        'mailinator.com', 'yopmail.com', '10minutemail.com', 'tempmail.com',
        'temp-mail.org', 'guerrillamail.com', 'sharklasers.com', 'trashmail.com',
        'throwawaymail.com', 'dispostable.com', 'fakeinbox.com', 'mohmal.com',
        'crazymailing.com', 'getairmail.com', 'generator.email', 'nada.ltd'
    ];
    const partesEmail = email.split('@');
    const usuarioEmail = partesEmail[0] || '';
    const dominioEmail = partesEmail[1] || '';

    if (dominiosProibidos.includes(dominioEmail)) {
        exibirErro('E-mails temporários, fictícios ou de teste não são aceitos. Informe um e-mail válido.');
        return;
    }

    if (['teste', 'test', 'fake', 'asdf'].includes(usuarioEmail) && ['email.com', 'gmail.com', 'hotmail.com'].includes(dominioEmail)) {
        exibirErro('Informe um e-mail válido para receber o código.');
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
