document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('cadastroForm');
    const btnSubmit = document.getElementById('btnSubmit');
    const spinner = document.getElementById('spinner');
    const btnText = btnSubmit.querySelector('span');
    const alertBox = document.getElementById('alertBox');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Limpar alertas anteriores
        alertBox.style.display = 'none';
        alertBox.className = 'alert';
        alertBox.innerHTML = '';

        // Pegar valores
        const payload = {
            name: document.getElementById('name').value.trim(),
            email: document.getElementById('email').value.trim(),
            nome_agencia: document.getElementById('nome_agencia').value.trim(),
            password: document.getElementById('password').value
        };

        // Estado de Loading
        setLoading(true);

        try {
            const response = await fetch('http://localhost:8000/api/auth/registro', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (!response.ok) {
                // Erros de Validação (422) ou Servidor (500)
                let errorMessage = data.message || data.mensagem || 'Ocorreu um erro ao realizar o cadastro.';
                
                // Formatar erros de validação do Laravel
                if (data.errors) {
                    const firstErrorKey = Object.keys(data.errors)[0];
                    errorMessage = data.errors[firstErrorKey][0];
                }

                showAlert(errorMessage, 'error');
                setLoading(false);
                return;
            }

            // SUCESSO (201)
            showAlert('Conta criada com sucesso! Redirecionando para o pagamento...', 'success');
            
            // Salvar no localStorage
            localStorage.setItem('auth_token', data.dados.token);
            localStorage.setItem('usuario_email', data.dados.usuario_admin);
            localStorage.setItem('agencia_nome', data.dados.agencia);

            // Redirecionar para o Checkout PIX gerado pelo AbacatePay
            setTimeout(() => {
                window.location.href = data.dados.link_pagamento;
            }, 1500);

        } catch (error) {
            console.error('Erro na requisição:', error);
            showAlert('Erro de conexão com o servidor. Verifique se o back-end está rodando.', 'error');
            setLoading(false);
        }
    });

    function setLoading(isLoading) {
        if (isLoading) {
            btnSubmit.disabled = true;
            btnText.style.display = 'none';
            spinner.style.display = 'block';
        } else {
            btnSubmit.disabled = false;
            btnText.style.display = 'block';
            spinner.style.display = 'none';
        }
    }

    function showAlert(message, type) {
        alertBox.innerHTML = message;
        alertBox.classList.add(`alert-${type}`);
        alertBox.style.display = 'block';
    }
});
