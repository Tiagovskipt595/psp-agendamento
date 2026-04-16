<?php
require_once '../config/config.php';
header('Content-Type: application/json');

if (!estaLogado()) {
    echo json_encode(['sucesso' => false, 'erro' => 'Não autorizado']);
    exit;
}

$db = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método inválido']);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$estado = filter_input(INPUT_POST, 'estado', FILTER_SANITIZE_SPECIAL_CHARS);

$estadosValidos = ['confirmado', 'presente', 'em_atendimento', 'concluido', 'cancelado', 'faltou'];

if (!$id || !in_array($estado, $estadosValidos)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos']);
    exit;
}

try {
    $stmt = $db->prepare("UPDATE agendamentos SET estado = ? WHERE id = ?");
    $stmt->execute([$estado, $id]);

    echo json_encode(['sucesso' => true]);
} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
