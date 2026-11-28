/*
 * Aplica o tema salvo ANTES da primeira pintura (evita "piscar").
 * Carregar no <head> como script clássico (sem defer/module):
 *   <script src="../scripts/componentes/tema-inicial.js"></script>
 * Chave mantida: localStorage 'tema_preferido' = 'dark' | 'light'.
 */
(function () {
    var salvo = null;
    try { salvo = localStorage.getItem('tema_preferido'); } catch (e) { /* modo privado */ }
    var escuro = salvo ? salvo === 'dark'
        : window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.setAttribute('data-tema', escuro ? 'escuro' : 'claro');
})();
