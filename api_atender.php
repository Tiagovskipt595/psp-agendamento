<?php
/**
 * API: Atendimento
 * Workflow: presente → em_atendimento → concluido com resultado e observações
 */
require_once 'config/config.php';

header('Content-Type: application/json');

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

// Requerer login
if (!estaLogado()) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado']);
    exit;
}

$db = getDbConnection();
$usuarioId = $_SESSION['usuario_id'];
$esquadraId = $_SESSION['esquadra_id'];

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
$observacoes = trim($_POST['observacoes'] ?? '');
$resultado = trim($_POST['resultado'] ?? '');

if (!$id) {
    http_response_code(400);
    echo json_encode(['erro' => 'ID inválido']);
    exit;
}

// Verificar que o agendamento pertence à esquadra do agente
$stmt = $db->prepare("SELECT * FROM agendamentos WHERE id = ? AND esquadra_id = ?");
$stmt->execute([$id, $esquadraId]);
$agendamento = $stmt->fetch();

if (!$agendamento) {
    http_response_code(404);
    echo json_encode(['erro' => 'Agendamento não encontrado']);
    exit;
}

$novoEstado = '';
$erro = null;

switch ($action) {
    case 'start':
        // Transição: confirmado → presente → em_atendimento
        if ($agendamento['estado'] === 'confirmado') {
            // Primeiro marcar como presente
            $stmt = $db->prepare("UPDATE agendamentos SET estado = 'presente' WHERE id = ?");
            $stmt->execute([$id]);
        }

        if (in_array($agendamento['estado'], ['confirmado', 'presente'])) {
            $novoEstado = 'em_atendimento';
            $stmt = $db->prepare("UPDATE agendamentos SET
                estado = 'em_atendimento',
                observacoes = CONCAT(IFNULL(observacoes, ''), ?, NOW())
                WHERE id = ?");
            $obsTexto = $observacoes ? "\n[Início atendimento - " . date('d/m/Y H:i') . "]: " . $observacoes : '';
            $stmt->execute([$obsTexto, $id]);

            logSeguranca('atendimento_iniciado', ['agendamento_id' => $id, 'agente_id' => $usuarioId]);
            echo json_encode(['sucesso' => true, 'estado' => $novoEstado, 'mensagem' => 'Atendimento iniciado']);
        } else {
            echo json_encode(['erro' => 'Estado inválido para iniciar atendimento', 'estado_atual' => $agendamento['estado']]);
        }
        break;

    case 'conclude':
        // Transição: em_atendimento → concluido
        // Requer resultado
        if (empty($resultado)) {
            http_response_code(400);
            echo json_encode(['erro' => 'Resultado é obrigatório para concluir']);
            exit;
        }

        $resultadosValidos = ['atendido', 'nao_atendido', 'documentos_faltantes'];
        if (!in_array($resultado, $resultadosValidos)) {
            http_response_code(400);
            echo json_encode(['erro' => 'Resultado inválido']);
            exit;
        }

        if ($agendamento['estado'] !== 'em_atendimento') {
            echo json_encode(['erro' => 'Agendamento não está em atendimento', 'estado_atual' => $agendamento['estado']]);
            exit;
        }

        $stmt = $db->prepare("UPDATE agendamentos SET
            estado = 'concluido',
            resultado = ?,
            observacoes = CONCAT(IFNULL(observacoes, ''), ?, NOW())
            WHERE id = ?");
        $obsTexto = "\n[Conclusão - " . date('d/m/Y H:i') . "]: " . $observacoes;
        $stmt->execute([$resultado, $obsTexto, $id]);

        logSeguranca('atendimento_concluido', [
            'agendamento_id' => $id,
            'agente_id' => $usuarioId,
            'resultado' => $resultado
        ]);

        echo json_encode([
            'sucesso' => true,
            'estado' => 'concluido',
            'resultado' => $resultado,
            'mensagem' => 'Atendimento concluído com sucesso'
        ]);
        break;

    case 'cancel':
        // Cancelar agendamento
        if ($agendamento['estado'] === 'concluido') {
            echo json_encode(['erro' => 'Não é possível cancelar um agendamento já concluído']);
            exit;
        }

        $stmt = $db->prepare("UPDATE agendamentos SET estado = 'cancelado' WHERE id = ?");
        $stmt->execute([$id]);

        logSeguranca('agendamento_cancelado', [
            'agendamento_id' => $id,
            'agente_id' => $usuarioId
        ]);

        echo json_encode(['sucesso' => true, 'estado' => 'cancelado', 'mensagem' => 'Agendamento cancelado']);
        break;

    case 'no_show':
        // Marcar como faltou
        if (!in_array($agendamento['estado'], ['confirmado', 'presente'])) {
            echo json_encode(['erro' => 'Não é possível marcar como falta neste estado']);
            exit;
        }

        $stmt = $db->prepare("UPDATE agendamentos SET estado = 'faltou' WHERE id = ?");
        $stmt->execute([$id]);

        logSeguranca('agendamento_faltou', [
            'agendamento_id' => $id,
            'agente_id' => $usuarioId
        ]);

        echo json_encode(['sucesso' => true, 'estado' => 'faltou', 'mensagem' => 'Marcado como falta']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['erro' => 'Ação desconhecida']);
}
