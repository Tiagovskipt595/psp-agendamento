/**
 * Sistema de Toast Notifications
 */

const Toast = {
    container: null,

    init() {
        if (this.container) return;
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        document.body.appendChild(this.container);
    },

    show(mensagem, tipo = 'info', duracao = 4000) {
        this.init();

        const toast = document.createElement('div');
        toast.className = `toast toast-${tipo}`;
        toast.innerHTML = `
            <span class="toast-icon">${this.getIcon(tipo)}</span>
            <span class="toast-message">${mensagem}</span>
            <button class="toast-close" onclick="Toast.remove(this.parentElement)">&times;</button>
        `;

        this.container.appendChild(toast);

        // Animar entrada
        requestAnimationFrame(() => {
            toast.classList.add('toast-show');
        });

        // Remover automaticamente
        if (duracao > 0) {
            setTimeout(() => this.remove(toast), duracao);
        }

        return toast;
    },

    getIcon(tipo) {
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ',
            loading: '⏳'
        };
        return icons[tipo] || icons.info;
    },

    remove(toast) {
        if (!toast) return;
        toast.classList.remove('toast-show');
        toast.classList.add('toast-hide');
        setTimeout(() => toast.remove(), 300);
    },

    success(mensagem) { this.show(mensagem, 'success'); },
    error(mensagem) { this.show(mensagem, 'error'); },
    warning(mensagem) { this.show(mensagem, 'warning'); },
    info(mensagem) { this.show(mensagem, 'info'); },

    loading(mensagem) {
        return this.show(mensagem, 'loading', 0);
    }
};

// Criar estilos CSS
const style = document.createElement('style');
style.textContent = `
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .toast {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        min-width: 300px;
        max-width: 450px;
        font-size: 0.95rem;
        transform: translateX(120%);
        transition: transform 0.3s ease;
        animation: slideIn 0.3s ease forwards;
    }

    @keyframes slideIn {
        to { transform: translateX(0); }
    }

    .toast-hide {
        animation: slideOut 0.3s ease forwards;
    }

    @keyframes slideOut {
        to { transform: translateX(120%); }
    }

    .toast-success {
        background: #28a745;
        color: white;
    }

    .toast-error {
        background: #dc3545;
        color: white;
    }

    .toast-warning {
        background: #ffc107;
        color: #333;
    }

    .toast-info {
        background: #17a2b8;
        color: white;
    }

    .toast-loading {
        background: #6c757d;
        color: white;
    }

    .toast-icon {
        font-size: 1.2rem;
        font-weight: bold;
    }

    .toast-message {
        flex: 1;
    }

    .toast-close {
        background: none;
        border: none;
        color: inherit;
        font-size: 1.5rem;
        cursor: pointer;
        opacity: 0.7;
        padding: 0;
        line-height: 1;
    }

    .toast-close:hover {
        opacity: 1;
    }

    @media (max-width: 768px) {
        .toast-container {
            top: 10px;
            right: 10px;
            left: 10px;
        }

        .toast {
            min-width: unset;
            width: 100%;
        }
    }
`;
document.head.appendChild(style);
