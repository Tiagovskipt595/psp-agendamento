/**
 * Validação de Formulários em Tempo Real
 */

const FormValidator = {
    errors: new Map(),

    init(form) {
        if (!form) return;

        const inputs = form.querySelectorAll('input[required], input[type="email"], input[type="tel"], input[data-validate]');

        inputs.forEach(input => {
            // Validar ao perder foco
            input.addEventListener('blur', () => this.validateField(input));

            // Validar enquanto digita (após primeiro erro)
            input.addEventListener('input', () => {
                if (this.errors.has(input.name)) {
                    this.validateField(input);
                }
            });

            // Limpar erro ao focar
            input.addEventListener('focus', () => {
                this.clearError(input);
            });
        });

        // Validar formulário no submit
        form.addEventListener('submit', (e) => {
            let isValid = true;
            inputs.forEach(input => {
                if (!this.validateField(input)) {
                    isValid = false;
                }
            });

            if (!isValid) {
                e.preventDefault();
                Toast.error('Por favor, corrija os erros no formulário');
                // Scroll para primeiro erro
                const primeiroErro = form.querySelector('.form-group.error');
                if (primeiroErro) {
                    primeiroErro.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    },

    validateField(input) {
        const value = input.value.trim();
        const name = input.name;
        let error = null;

        // Remover erro anterior
        this.clearError(input);

        // Validação de required
        if (input.required && !value) {
            error = this.getRequiredMessage(input);
        }

        // Validações específicas por tipo
        if (!error && value) {
            switch (input.type) {
                case 'email':
                    if (!this.validateEmail(value)) {
                        error = 'Email inválido';
                    }
                    break;
                case 'tel':
                    if (!this.validatePhone(value)) {
                        error = 'Número de telefone inválido';
                    }
                    break;
            }

            // Validações por atributo data-validate
            if (!error && input.dataset.validate) {
                error = this.validateByRule(input, value);
            }

            // Validação de minlength
            if (!error && input.minLength > 0 && value.length < input.minLength) {
                error = `Mínimo de ${input.minLength} caracteres`;
            }

            // Validação de pattern
            if (!error && input.pattern) {
                const regex = new RegExp(input.pattern);
                if (!regex.test(value)) {
                    error = input.title || 'Formato inválido';
                }
            }
        }

        if (error) {
            this.showError(input, error);
            this.errors.set(name, error);
            return false;
        }

        this.errors.delete(name);
        return true;
    },

    getRequiredMessage(input) {
        const labels = {
            nome: 'Nome é obrigatório',
            email: 'Email é obrigatório',
            cc: 'Cartão de Cidadão é obrigatório',
            telemovel: 'Telemóvel é obrigatório',
            data: 'Data é obrigatória',
            hora: 'Hora é obrigatória',
            password: 'Password é obrigatória'
        };
        return labels[input.name] || 'Campo obrigatório';
    },

    validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    },

    validatePhone(phone) {
        // Aceita formatos portugueses e internacionais
        return /^[+]?[0-9\s-]{9,}$/.test(phone);
    },

    validateByRule(input, value) {
        const rule = input.dataset.validate;

        switch (rule) {
            case 'cc':
                if (!/^[0-9]{6,}[A-Z]{0,2}$/.test(value)) {
                    return 'Formato de CC inválido (ex: 12345678AB)';
                }
                break;
            case 'data-futura':
                if (new Date(value) < new Date().setHours(0, 0, 0, 0)) {
                    return 'Data não pode ser anterior a hoje';
                }
                break;
            case 'hora':
                if (!/^[0-2][0-9]:[0-5][0-9]$/.test(value)) {
                    return 'Hora inválida';
                }
                break;
        }
        return null;
    },

    showError(input, message) {
        const formGroup = input.closest('.form-group');
        if (!formGroup) return;

        formGroup.classList.add('error');

        const errorEl = document.createElement('span');
        errorEl.className = 'error-message';
        errorEl.textContent = message;
        formGroup.appendChild(errorEl);

        input.setAttribute('aria-invalid', 'true');
    },

    clearError(input) {
        const formGroup = input.closest('.form-group');
        if (!formGroup) return;

        formGroup.classList.remove('error');
        const errorEl = formGroup.querySelector('.error-message');
        if (errorEl) errorEl.remove();

        input.removeAttribute('aria-invalid');
    }
};

// Auto-initializar em formulários com data-validate
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-validate]').forEach(form => {
        FormValidator.init(form);
    });
});
