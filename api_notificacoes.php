<?php
/**
 * API: Notificações
 * Permite reenviar emails/SMS de confirmação e verificar estado
 */
require_once 'config/config.php';

header('Content-Type: application/json');

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

$db = getDbConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$codigo = $_POST['codigo'] ?? $_GET['codigo'] ?? '';

// Buscar agendamento
$stmt = $db->prepare("SELECT a.*, e.nome as esquadra_nome, s.nome as servico_nome
                      FROM agendamentos a
                      JOIN esquadras e ON a.esquadra_id = e.id
                      JOIN servicos s ON a.servico_id = s.id
                      WHERE a.codigo_agendamento = ?");
$stmt->execute([$codigo]);
$agendamento = $stmt->fetch();

if (!$agendamento) {
    http_response_code(404);
    echo json_encode(['erro' => 'Agendamento não encontrado']);
    exit;
}

$resultado = ['sucesso' => false, 'mensagem' => ''];

switch ($action) {
    case 'resend_email':
        try {
            $enviado = enviarEmailConfirmacao($codigo, $db);
            if ($enviado) {
                $stmt = $db->prepare("UPDATE agendamentos SET
                    notificacao_email_enviada = 1,
                    data_notificacao_email = NOW()
                    WHERE id = ?");
                $stmt->execute([$agendamento['id']]);
                $resultado = ['sucesso' => true, 'mensagem' => 'Email reenviado com sucesso'];
            } else {
                $resultado = ['sucesso' => false, 'mensagem' => 'Falha ao enviar email'];
            }
        } catch (Exception $e) {
            $resultado = ['sucesso' => false, 'mensagem' => 'Erro: ' . $e->getMessage()];
        }
        break;

    case 'resend_sms':
        try {
            if (!file_exists(__DIR__ . '/config/sms.php')) {
                $resultado = ['sucesso' => false, 'mensagem' => 'Sistema SMS não configurado'];
            } else {
                require_once __DIR__ . '/config/sms.php';
                if (!defined('SMS_ENABLED') || !SMS_ENABLED) {
                    $resultado = ['sucesso' => false, 'mensagem' => 'Sistema SMS desativado'];
                } else {
                    $enviado = enviarSMSConfirmacao($codigo, $db);
                    if ($enviado) {
                        $stmt = $db->prepare("UPDATE agendamentos SET
                            notificacao_sms_enviada = 1,
                            data_notificacao_sms = NOW()
                            WHERE id = ?");
                        $stmt->execute([$agendamento['id']]);
                        $resultado = ['sucesso' => true, 'mensagem' => 'SMS reenviado com sucesso'];
                    } else {
                        $resultado = ['sucesso' => false, 'mensagem' => 'Falha ao enviar SMS'];
                    }
                }
            }
        } catch (Exception $e) {
            $resultado = ['sucesso' => false, 'mensagem' => 'Erro: ' . $e->getMessage()];
        }
        break;

    case 'status':
        $resultado = [
            'sucesso' => true,
            'agendamento' => [
                'codigo' => $agendamento['codigo_agendamento'],
                'estado' => $agendamento['estado'],
                'email_enviado' => (bool)$agendamento['notificacao_email_enviada'],
                'sms_enviado' => (bool)$agendamento['notificacao_sms_enviada'],
                'data_email' => $agendamento['data_notificacao_email'],
                'data_sms' => $agendamento['data_notificacao_sms']
            ]
        ];
        break;

    default:
        http_response_code(400);
        $resultado = ['erro' => 'Ação desconhecida'];
}

echo json_encode($resultado);
