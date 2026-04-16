<?php
require_once '../config/config.php';
header('Content-Type: application/json');

$db = getDbConnection();

$esquadraId = filter_input(INPUT_GET, 'esquadra_id', FILTER_VALIDATE_INT);
$data = filter_input(INPUT_GET, 'data', FILTER_SANITIZE_SPECIAL_CHARS);
$duracao = filter_input(INPUT_GET, 'duracao', FILTER_VALIDATE_INT) ?: 30;

if (!$esquadraId || !$data) {
    echo json_encode([]);
    exit;
}

// Dia da semana (0 = Domingo, 6 = Sábado)
$diaSemana = date('w', strtotime($data));

// Buscar horário de funcionamento da esquadra para este dia da semana
$stmt = $db->prepare("SELECT hora_inicio, hora_fim FROM horarios WHERE esquadra_id = ? AND dia_semana = ?");
$stmt->execute([$esquadraId, $diaSemana]);
$horario = $stmt->fetch();

if (!$horario) {
    // Esquadra não abre neste dia da semana
    echo json_encode([]);
    exit;
}

// Buscar agendamentos já existentes para esta data
$stmt = $db->prepare("SELECT hora_agendamento FROM agendamentos
                      WHERE esquadra_id = ? AND data_agendamento = ? AND estado IN ('confirmado', 'presente', 'em_atendimento')");
$stmt->execute([$esquadraId, $data]);
$agendamentos = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Gerar slots de horários disponíveis
$horaInicio = strtotime($horario['hora_inicio']);
$horaFim = strtotime($horario['hora_fim']);
$intervalo = $duracao * 60; // Converter para segundos

$horarios = [];
$horaAtual = $horaInicio;

while ($horaAtual + $intervalo <= $horaFim) {
    $horaSlot = date('H:i', $horaAtual);
    $disponivel = !in_array($horaSlot, $agendamentos);

    $horarios[] = [
        'hora' => $horaSlot,
        'disponivel' => $disponivel
    ];

    $horaAtual += $intervalo;
}

echo json_encode($horarios);
