import { inicializarSidebarGlobal } from '../componentes/sidebarGlobal.js';
import { inicializarSeletorGlobal } from '../componentes/seletorGlobalStartup.js';
import {
    usuarioEstaAutenticado,
    obterUsuarioLogado,
    usuarioEhTitular,
    usuarioTemPermissao
} from '../servicos/servicoAutenticacao.js';
import {
    listarEquipeConsultoria,
    convidarMembroEquipe,
    atualizarPermissoesMembro,
    alternarStatusMembro,
    reenviarConviteMembro,
    removerMembroEquipe
} from '../servicos/servicoEquipe.js';

if (!usuarioEstaAutenticado()) {
    window.location.href = 'login.html';
}

const containerListaMembros = document.getElementById('containerListaMembros');
const alertaFeedbackEquipe = document.getElementById('alertaFeedbackEquipe');
const btnAbrirModalConvidar = document.getElementById('btnAbrirModalConvidar');

const modalFormOverlay = document.getElementById('modalFormularioMembroOverlay');
const tituloModalForm = document.getElementById('tituloModalFormularioMembro');
const subtituloModalForm = document.getElementById('subtituloModalFormularioMembro');
const blocoDadosBasicos = document.getElementById('blocoDadosBasicosConvite');
const inputNomeMembro = document.getElementById('inputNomeMembro');
const inputEmailMembro = document.getElementById('inputEmailMembro');
const containerPresets = document.getElementById('containerPresetsPermissao');
const containerCheckboxes = document.getElementById('containerCheckboxesPermissoes');
const formMembro = document.getElementById('formMembroEquipe');
const feedbackFormMembro = document.getElementById('feedbackFormMembro');
const btnSalvarFormMembro = document.getElementById('btnSalvarFormularioMembro');

const modalCredenciaisOverlay = document.getElementById('modalCredenciaisConviteOverlay');
const nomeConvidadoCredenciais = document.getElementById('nomeConvidadoCredenciais');
const valorCodigoPinConvite = document.getElementById('valorCodigoPinConvite');
const inputLinkConviteGerado = document.getElementById('inputLinkConviteGerado');
const btnCopiarCodigoPin = document.getElementById('btnCopiarCodigoPin');
const btnCopiarLinkConvite = document.getElementById('btnCopiarLinkConvite');

const modalConfirmarRemocaoOverlay = document.getElementById('modalConfirmarRemocaoOverlay');
const textoConfirmarRemocao = document.getElementById('textoConfirmarRemocaoMembro');
const btnExecutarRemocao = document.getElementById('btnExecutarRemocaoMembro');

let estadoEquipe = {
    membros: [],
    catalogo_permissoes: [],
    presets: [],
    ator: { is_owner: false, permissoes: [] }
};

let modoModal = 'convidar';
let membroEmEdicao = null;
let membroParaRemover = null;

const LABELS_PERMISSOES = {
    'startups.criar_editar': 'Criar e Editar Startups',
    'startups.excluir': 'Excluir Startups',
    'kanban.gerenciar': 'Gerenciar Kanban',
    'minutas.gerenciar': 'Gerenciar Minutas IA',
    'equipe.gerenciar': 'Gerenciar Equipe',
    'assinatura.gerenciar': 'Assinatura & Faturamento'
};

function exibirFeedbackGlobal(mensagem, tipo = 'sucesso') {
    if (!alertaFeedbackEquipe) return;
    alertaFeedbackEquipe.textContent = mensagem;
    alertaFeedbackEquipe.className = `alerta-feedback ${tipo}`;
    alertaFeedbackEquipe.style.display = 'block';
}

function ocultarFeedbackGlobal() {
    if (!alertaFeedbackEquipe) return;
    alertaFeedbackEquipe.style.display = 'none';
    alertaFeedbackEquipe.textContent = '';
}

function obterIniciais(nome) {
    const partes = String(nome || 'CN').trim().split(/\s+/).filter(Boolean);
    if (partes.length === 1) return partes[0].slice(0, 2).toUpperCase();
    return (partes[0][0] + partes[1][0]).toUpperCase();
}

function renderizarMembros() {
    if (!containerListaMembros) return;
    containerListaMembros.innerHTML = '';

    const lista = estadoEquipe.membros || [];
    if (lista.length === 0) {
        containerListaMembros.innerHTML = `
            <div style="background: #14171c; border: 1px solid #282c34; border-radius: 12px; padding: 2rem; text-align: center; color: #94a3b8;">
                Nenhum membro encontrado na consultoria.
            </div>
        `;
        return;
    }

    lista.forEach((membro) => {
        const card = document.createElement('div');
        card.className = `card-membro-equipe ${membro.is_owner ? 'is-owner' : ''}`;

        let badgeStatusHtml = '';
        if (membro.is_owner) {
            badgeStatusHtml = `<span class="badge-status-membro titular">👑 Titular</span>`;
        } else if (membro.status === 'pendente') {
            badgeStatusHtml = `<span class="badge-status-membro pendente">⏳ Convite Pendente</span>`;
        } else if (membro.status === 'inativo') {
            badgeStatusHtml = `<span class="badge-status-membro inativo">🚫 Inativo</span>`;
        } else {
            badgeStatusHtml = `<span class="badge-status-membro ativo">● Ativo</span>`;
        }

        const permissoesLista = Array.isArray(membro.permissoes) ? membro.permissoes : [];
        let chipsPermissoesHtml = '';

        if (membro.is_owner) {
            chipsPermissoesHtml = `
                <span class="badge-permissao-chip">Acesso Total Operacional</span>
                <span class="badge-permissao-chip" style="border-color: rgba(99, 102, 241, 0.5); color: #a5b4fc;">💳 Titular Financeiro (Assinatura)</span>
            `;
        } else if (permissoesLista.length === 0) {
            chipsPermissoesHtml = `<span class="badge-permissao-chip somente-leitura">👁️ Visualizador (Somente Leitura)</span>`;
        } else {
            chipsPermissoesHtml = permissoesLista.map((cod) => {
                const label = LABELS_PERMISSOES[cod] || cod;
                return `<span class="badge-permissao-chip">${label}</span>`;
            }).join('');
        }

        card.innerHTML = `
            <div class="membro-info-principal">
                <div class="membro-avatar-monograma">${obterIniciais(membro.name)}</div>
                <div class="membro-detalhes">
                    <div class="membro-linha-nome">
                        <span class="membro-nome">${membro.name}</span>
                        ${badgeStatusHtml}
                    </div>
                    <span class="membro-email">${membro.email}</span>
                    <div class="membro-permissoes-lista">${chipsPermissoesHtml}</div>
                </div>
            </div>
            <div class="membro-acoes"></div>
        `;

        const containerAcoes = card.querySelector('.membro-acoes');
        if (!membro.is_owner && membro.pode_gerenciar && containerAcoes) {
            const btnEditar = document.createElement('button');
            btnEditar.type = 'button';
            btnEditar.className = 'btn-acao-membro';
            btnEditar.textContent = '⚙️ Editar Permissões';
            btnEditar.addEventListener('click', () => abrirModalEditarPermissoes(membro));
            containerAcoes.appendChild(btnEditar);

            if (membro.status === 'pendente') {
                const btnReenviar = document.createElement('button');
                btnReenviar.type = 'button';
                btnReenviar.className = 'btn-acao-membro reenviar';
                btnReenviar.textContent = '🔄 Reenviar Convite';
                btnReenviar.addEventListener('click', () => acionarReenvioConvite(membro, btnReenviar));
                containerAcoes.appendChild(btnReenviar);
            } else {
                const btnStatus = document.createElement('button');
                btnStatus.type = 'button';
                btnStatus.className = 'btn-acao-membro';
                btnStatus.textContent = membro.status === 'inativo' ? '✅ Reativar' : '⏸️ Desativar';
                btnStatus.addEventListener('click', () => acionarAlternanciaStatus(membro, btnStatus));
                containerAcoes.appendChild(btnStatus);
            }

            const btnRemover = document.createElement('button');
            btnRemover.type = 'button';
            btnRemover.className = 'btn-acao-membro perigo';
            btnRemover.textContent = membro.status === 'pendente' ? '🗑️ Cancelar Convite' : '🗑️ Remover';
            btnRemover.addEventListener('click', () => abrirModalConfirmarRemocao(membro));
            containerAcoes.appendChild(btnRemover);
        }

        containerListaMembros.appendChild(card);
    });
}

function renderizarOpcoesPermissoesNoModal(selecionadas = []) {
    if (!containerCheckboxes || !containerPresets) return;

    const atorEhOwner = Boolean(estadoEquipe.ator?.is_owner);
    const catalogo = estadoEquipe.catalogo_permissoes?.length
        ? estadoEquipe.catalogo_permissoes
        : [
            { codigo: 'startups.criar_editar', label: 'Criar e Editar Startups', descricao: 'Cadastrar novas empresas e editar dados cadastrais e pitch.', disponivel_para_ator: true },
            { codigo: 'startups.excluir', label: 'Excluir Startups da Carteira', descricao: 'Remover empresas da carteira da consultoria.', disponivel_para_ator: true },
            { codigo: 'kanban.gerenciar', label: 'Gerenciar Pipeline Kanban', descricao: 'Criar, mover, anexar documentos e remover cards.', disponivel_para_ator: true },
            { codigo: 'minutas.gerenciar', label: 'Gerar e Editar Minutas com IA', descricao: 'Gerar propostas técnicas com IA e salvar versões.', disponivel_para_ator: true },
            { codigo: 'equipe.gerenciar', label: 'Gerenciar Equipe', descricao: 'Convidar e gerenciar consultores operacionais (exclusivo do Titular).', disponivel_para_ator: atorEhOwner, apenas_owner_concede: true }
        ];

    const presets = estadoEquipe.presets?.length
        ? estadoEquipe.presets
        : [
            { id: 'gerente_operacional', nome: 'Gerente Operacional', disponivel: atorEhOwner, permissoes: ['startups.criar_editar', 'startups.excluir', 'kanban.gerenciar', 'minutas.gerenciar', 'equipe.gerenciar'] },
            { id: 'consultor_pleno', nome: 'Consultor Pleno', disponivel: true, permissoes: ['startups.criar_editar', 'kanban.gerenciar', 'minutas.gerenciar'] },
            { id: 'visualizador', nome: 'Visualizador', disponivel: true, permissoes: [] }
        ];

    containerPresets.innerHTML = '';
    presets.forEach((preset) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-preset-permissao';
        btn.textContent = preset.nome;
        btn.disabled = preset.disponivel === false;
        if (preset.disponivel === false) {
            btn.title = 'Apenas o Titular da consultoria pode atribuir o perfil de Gerente Operacional.';
        }
        btn.addEventListener('click', () => {
            aplicarPresetDePermissoes(preset.permissoes || []);
            containerPresets.querySelectorAll('.btn-preset-permissao').forEach((b) => b.classList.remove('ativo'));
            btn.classList.add('ativo');
        });
        containerPresets.appendChild(btn);
    });

    containerCheckboxes.innerHTML = '';
    catalogo.forEach((perm) => {
        const disponivel = perm.disponivel_para_ator !== false && (atorEhOwner || perm.codigo !== 'equipe.gerenciar');
        const marcado = selecionadas.includes(perm.codigo) && disponivel;

        const labelEl = document.createElement('label');
        labelEl.className = `item-checkbox-permissao ${!disponivel ? 'desabilitado' : ''}`;
        labelEl.innerHTML = `
            <input type="checkbox" class="chk-permissao-membro" value="${perm.codigo}" ${marcado ? 'checked' : ''} ${!disponivel ? 'disabled' : ''}>
            <div class="checkbox-permissao-texto">
                <span class="checkbox-permissao-titulo">${perm.label} ${perm.apenas_owner_concede ? '<small style="color:#a5b4fc;">(Apenas Titular)</small>' : ''}</span>
                <span class="checkbox-permissao-desc">${perm.descricao || ''}</span>
            </div>
        `;
        containerCheckboxes.appendChild(labelEl);
    });
}

function aplicarPresetDePermissoes(listaCodigos) {
    const atorEhOwner = Boolean(estadoEquipe.ator?.is_owner);
    containerCheckboxes?.querySelectorAll('.chk-permissao-membro').forEach((chk) => {
        if (chk.disabled) {
            chk.checked = false;
            return;
        }
        if (!atorEhOwner && chk.value === 'equipe.gerenciar') {
            chk.checked = false;
            return;
        }
        chk.checked = listaCodigos.includes(chk.value);
    });
}

function coletarPermissoesMarcadas() {
    const selecionadas = [];
    containerCheckboxes?.querySelectorAll('.chk-permissao-membro:checked').forEach((chk) => {
        if (!chk.disabled) {
            selecionadas.push(chk.value);
        }
    });
    return selecionadas;
}

function abrirModalConvidar() {
    modoModal = 'convidar';
    membroEmEdicao = null;
    if (tituloModalForm) tituloModalForm.textContent = 'Convidar Novo Consultor';
    if (subtituloModalForm) subtituloModalForm.textContent = 'Defina os dados e as permissões granulares de acesso do consultor';
    if (blocoDadosBasicos) blocoDadosBasicos.style.display = 'flex';
    if (inputNomeMembro) {
        inputNomeMembro.value = '';
        inputNomeMembro.required = true;
    }
    if (inputEmailMembro) {
        inputEmailMembro.value = '';
        inputEmailMembro.required = true;
    }
    if (btnSalvarFormMembro) btnSalvarFormMembro.textContent = 'Gerar Convite Seguro';
    if (feedbackFormMembro) {
        feedbackFormMembro.style.display = 'none';
        feedbackFormMembro.textContent = '';
    }

    renderizarOpcoesPermissoesNoModal(['startups.criar_editar', 'kanban.gerenciar', 'minutas.gerenciar']);
    modalFormOverlay?.classList.add('active');
}

function abrirModalEditarPermissoes(membro) {
    modoModal = 'editar';
    membroEmEdicao = membro;
    if (tituloModalForm) tituloModalForm.textContent = `Permissões de ${membro.name}`;
    if (subtituloModalForm) subtituloModalForm.textContent = membro.email;
    if (blocoDadosBasicos) blocoDadosBasicos.style.display = 'none';
    if (inputNomeMembro) inputNomeMembro.required = false;
    if (inputEmailMembro) inputEmailMembro.required = false;
    if (btnSalvarFormMembro) btnSalvarFormMembro.textContent = 'Salvar Permissões';
    if (feedbackFormMembro) {
        feedbackFormMembro.style.display = 'none';
        feedbackFormMembro.textContent = '';
    }

    renderizarOpcoesPermissoesNoModal(Array.isArray(membro.permissoes) ? membro.permissoes : []);
    modalFormOverlay?.classList.add('active');
}

function fecharModalFormulario() {
    modalFormOverlay?.classList.remove('active');
}

function exibirModalCredenciaisPosConvite({ nome, codigo_ativacao, link_convite }) {
    if (nomeConvidadoCredenciais) nomeConvidadoCredenciais.textContent = nome || 'o consultor convidado';
    if (valorCodigoPinConvite) valorCodigoPinConvite.textContent = codigo_ativacao || '------';
    if (inputLinkConviteGerado) inputLinkConviteGerado.value = link_convite || '';
    modalCredenciaisOverlay?.classList.add('active');
}

async function carregarEquipe() {
    ocultarFeedbackGlobal();
    try {
        const dados = await listarEquipeConsultoria();
        estadoEquipe = {
            membros: dados.membros || [],
            catalogo_permissoes: dados.catalogo_permissoes || [],
            presets: dados.presets || [],
            ator: dados.ator || { is_owner: usuarioEhTitular(), permissoes: [] }
        };
        renderizarMembros();
    } catch (erro) {
        if (erro.status === 403) {
            window.location.href = 'busca-editais.html';
            return;
        }
        exibirFeedbackGlobal(erro.message || 'Não foi possível carregar a lista de membros da equipe.', 'erro');
    }
}

formMembro?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (feedbackFormMembro) {
        feedbackFormMembro.style.display = 'none';
        feedbackFormMembro.textContent = '';
    }

    const permissoes = coletarPermissoesMarcadas();
    const textoOriginal = btnSalvarFormMembro.textContent;
    btnSalvarFormMembro.disabled = true;
    btnSalvarFormMembro.textContent = 'Processando...';

    try {
        if (modoModal === 'convidar') {
            const name = inputNomeMembro.value.trim();
            const email = inputEmailMembro.value.trim();

            if (!name || !email) {
                throw new Error('Preencha o nome e o e-mail do consultor.');
            }

            const resposta = await convidarMembroEquipe({ name, email, permissoes });
            fecharModalFormulario();
            await carregarEquipe();

            exibirModalCredenciaisPosConvite({
                nome: resposta.membro?.name || name,
                codigo_ativacao: resposta.codigo_ativacao,
                link_convite: resposta.link_convite
            });
        } else if (modoModal === 'editar' && membroEmEdicao) {
            const resposta = await atualizarPermissoesMembro(membroEmEdicao.id, permissoes);
            fecharModalFormulario();
            exibirFeedbackGlobal(resposta.message || 'Permissões atualizadas com sucesso!', 'sucesso');
            await carregarEquipe();
        }
    } catch (erro) {
        if (feedbackFormMembro) {
            feedbackFormMembro.textContent = erro.message || 'Erro ao salvar configurações do membro.';
            feedbackFormMembro.className = 'alerta-feedback erro';
            feedbackFormMembro.style.display = 'block';
        }
    } finally {
        btnSalvarFormMembro.disabled = false;
        btnSalvarFormMembro.textContent = textoOriginal;
    }
});

async function acionarReenvioConvite(membro, botao) {
    const original = botao.textContent;
    botao.disabled = true;
    botao.textContent = 'Gerando...';
    ocultarFeedbackGlobal();

    try {
        const resposta = await reenviarConviteMembro(membro.id);
        await carregarEquipe();
        exibirModalCredenciaisPosConvite({
            nome: membro.name,
            codigo_ativacao: resposta.codigo_ativacao,
            link_convite: resposta.link_convite
        });
    } catch (erro) {
        exibirFeedbackGlobal(erro.message || 'Falha ao reenviar convite.', 'erro');
    } finally {
        botao.disabled = false;
        botao.textContent = original;
    }
}

async function acionarAlternanciaStatus(membro, botao) {
    const novoStatus = membro.status === 'inativo' ? 'ativo' : 'inativo';
    const original = botao.textContent;
    botao.disabled = true;
    botao.textContent = 'Aguarde...';
    ocultarFeedbackGlobal();

    try {
        const resposta = await alternarStatusMembro(membro.id, novoStatus);
        exibirFeedbackGlobal(resposta.message || 'Status atualizado com sucesso.', 'sucesso');
        await carregarEquipe();
    } catch (erro) {
        exibirFeedbackGlobal(erro.message || 'Falha ao alterar status do consultor.', 'erro');
    } finally {
        botao.disabled = false;
        botao.textContent = original;
    }
}

function abrirModalConfirmarRemocao(membro) {
    membroParaRemover = membro;
    if (textoConfirmarRemocao) {
        textoConfirmarRemocao.innerHTML = membro.status === 'pendente'
            ? `Tem certeza que deseja cancelar o convite pendente enviado para <strong style="color:#fff;">${membro.name} (${membro.email})</strong>? O link e o código PIN perderão a validade imediatamente.`
            : `Tem certeza que deseja remover <strong style="color:#fff;">${membro.name} (${membro.email})</strong> da equipe?<br><br>• Todas as sessões e tokens ativos deste consultor serão <strong>encerrados imediatamente</strong>.<br>• Todas as startups, cards do Kanban e minutas produzidas na consultoria <strong>serão integralmente preservados</strong>.`;
    }
    modalConfirmarRemocaoOverlay?.classList.add('active');
}

function fecharModalConfirmarRemocao() {
    membroParaRemover = null;
    modalConfirmarRemocaoOverlay?.classList.remove('active');
}

btnExecutarRemocao?.addEventListener('click', async () => {
    if (!membroParaRemover || btnExecutarRemocao.disabled) return;
    const original = btnExecutarRemocao.textContent;
    btnExecutarRemocao.disabled = true;
    btnExecutarRemocao.textContent = 'Removendo...';

    try {
        const resposta = await removerMembroEquipe(membroParaRemover.id);
        fecharModalConfirmarRemocao();
        exibirFeedbackGlobal(resposta.message || 'Membro removido com sucesso.', 'sucesso');
        await carregarEquipe();
    } catch (erro) {
        fecharModalConfirmarRemocao();
        exibirFeedbackGlobal(erro.message || 'Não foi possível remover o membro.', 'erro');
    } finally {
        btnExecutarRemocao.disabled = false;
        btnExecutarRemocao.textContent = original;
    }
});

btnAbrirModalConvidar?.addEventListener('click', abrirModalConvidar);
document.getElementById('btnFecharModalFormularioMembro')?.addEventListener('click', fecharModalFormulario);
document.getElementById('btnCancelarModalFormularioMembro')?.addEventListener('click', fecharModalFormulario);

const fecharCredenciais = () => modalCredenciaisOverlay?.classList.remove('active');
document.getElementById('btnFecharModalCredenciaisConvite')?.addEventListener('click', fecharCredenciais);
document.getElementById('btnConcluirModalCredenciais')?.addEventListener('click', fecharCredenciais);

document.getElementById('btnFecharModalConfirmarRemocao')?.addEventListener('click', fecharModalConfirmarRemocao);
document.getElementById('btnCancelarModalConfirmarRemocao')?.addEventListener('click', fecharModalConfirmarRemocao);

btnCopiarCodigoPin?.addEventListener('click', async () => {
    const codigo = valorCodigoPinConvite?.textContent?.trim() || '';
    if (!codigo) return;
    try {
        await navigator.clipboard.writeText(codigo);
        btnCopiarCodigoPin.textContent = '✔ Código Copiado!';
        setTimeout(() => { btnCopiarCodigoPin.textContent = '📋 Copiar Código de 6 Dígitos'; }, 2000);
    } catch (_) {}
});

btnCopiarLinkConvite?.addEventListener('click', async () => {
    const link = inputLinkConviteGerado?.value || '';
    if (!link) return;
    try {
        await navigator.clipboard.writeText(link);
        btnCopiarLinkConvite.textContent = '✔ Link Copiado!';
        setTimeout(() => { btnCopiarLinkConvite.textContent = '🔗 Copiar Link de Convite'; }, 2000);
    } catch (_) {}
});

document.addEventListener('DOMContentLoaded', () => {
    const usuario = obterUsuarioLogado();
    if (usuario && !usuarioEhTitular(usuario) && !usuarioTemPermissao('equipe.gerenciar', usuario)) {
        window.location.href = 'busca-editais.html';
        return;
    }

    inicializarSidebarGlobal('equipe');
    inicializarSeletorGlobal('container-seletor-global');
    carregarEquipe();
});
