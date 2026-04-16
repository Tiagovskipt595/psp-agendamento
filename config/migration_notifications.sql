-- Migração: Adicionar colunas para tracking de notificações e resultado
-- Data: 2026-04-16

ALTER TABLE agendamentos
ADD COLUMN IF NOT EXISTS resultado VARCHAR(50) DEFAULT NULL COMMENT 'atendido, nao_atendido, documentos_faltantes',
ADD COLUMN IF NOT EXISTS notificacao_email_enviada TINYINT(1) DEFAULT 0 COMMENT 'Email de confirmação enviado',
ADD COLUMN IF NOT EXISTS notificacao_sms_enviada TINYINT(1) DEFAULT 0 COMMENT 'SMS de confirmação enviado',
ADD COLUMN IF NOT EXISTS data_notificacao_email DATETIME DEFAULT NULL COMMENT 'Data/hora do envio do email',
ADD COLUMN IF NOT EXISTS data_notificacao_sms DATETIME DEFAULT NULL COMMENT 'Data/hora do envio do SMS';

-- Tabela para logs de notificações
CREATE TABLE IF NOT EXISTS notificacoes_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agendamento_id INT NOT NULL,
    tipo ENUM('email_confirmacao', 'sms_confirmacao', 'email_lembrete', 'sms_lembrete', 'email_cancelamento', 'sms_cancelamento') NOT NULL,
    status ENUM('pendente', 'enviado', 'falhou') DEFAULT 'pendente',
    mensagem_erro TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id) ON DELETE CASCADE,
    INDEX idx_agendamento (agendamento_id),
    INDEX idx_tipo_status (tipo, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
