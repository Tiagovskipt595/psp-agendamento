<?php
require_once '../config/config.php';
header('Content-Type: application/json');

exigirLogin();

$db = getDbConnection();
$esquadraId = $_SESSION['esquadra_id'];

$tipo = $_GET['tipo'] ?? 'dia';
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-d');
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');

// Validar datas
if ($tipo === 'personalizado') {
    if (!$dataInicio || !$dataFim) {
        echo json_encode(['erro' => 'Datas inválidas']);
        exit;
    }
} elseif ($tipo === 'semana') {
    $dataInicio = date('Y-m-d', strtotime('monday this week'));
    $dataFim = date('Y-m-d', strtotime('sunday this week'));
} elseif ($tipo === 'mes') {
    $dataInicio = date('Y-m-01');
    $dataFim = date('Y-m-t');
} elseif ($tipo === 'dia') {
    $dataInicio = date('Y-m-d');
    $dataFim = date('Y-m-d');
}

// Estatísticas gerais
$stmt = $db->prepare("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'confirmado' THEN 1 ELSE 0 END) as confirmados,
    SUM(CASE WHEN estado = 'presente' THEN 1 ELSE 0 END) as presentes,
    SUM(CASE WHEN estado = 'em_atendimento' THEN 1 ELSE 0 END) as em_atendimento,
    SUM(CASE WHEN estado = 'concluido' THEN 1 ELSE 0 END) as concluidos,
    SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
    SUM(CASE WHEN estado = 'faltou' THEN 1 ELSE 0 END) as faltas
    FROM agendamentos
    WHERE esquadra_id = ? AND data_agendamento BETWEEN ? AND ?");
$stmt->execute([$esquadraId, $dataInicio, $dataFim]);
$stats = $stmt->fetch();

// Estatísticas por serviço
$stmt = $db->prepare("SELECT s.nome, COUNT(a.id) as total
                      FROM servicos s
                      LEFT JOIN agendamentos a ON s.id = a.servico_id
                          AND a.data_agendamento BETWEEN ? AND ?
                          AND a.esquadra_id = ?
                      WHERE s.esquadra_id = ?
                      GROUP BY s.id, s.nome
                      ORDER BY total DESC");
$stmt->execute([$dataInicio, $dataFim, $esquadraId, $esquadraId]);
$servicosStats = $stmt->fetchAll();

// Estatísticas por hora do dia
$stmt = $db->prepare("SELECT hora_agendamento, COUNT(*) as total
                      FROM agendamentos
                      WHERE esquadra_id = ? AND data_agendamento BETWEEN ? AND ?
                      GROUP BY hora_agendamento
                      ORDER BY hora_agendamento");
$stmt->execute([$esquadraId, $dataInicio, $dataFim]);
$horasStats = $stmt->fetchAll();

// Média de atendimentos por dia
$stmt = $db->prepare("SELECT
    AVG(daily_count) as media_diaria
    FROM (
        SELECT COUNT(*) as daily_count
        FROM agendamentos
        WHERE esquadra_id = ? AND data_agendamento BETWEEN ? AND ?
        GROUP BY data_agendamento
    ) as daily");
$stmt->execute([$esquadraId, $dataInicio, $dataFim]);
$mediaDiaria = $stmt->fetch()['media_diaria'] ?? 0;

// Taxa de comparecimento
$totalNaoCancelados = $stats['total'] - $stats['cancelados'];
$taxaComparecimento = $totalNaoCancelados > 0
    ? round((($stats['presentes'] + $stats['concluidos']) / $totalNaoCancelados) * 100, 1)
    : 0;

echo json_encode([
    'periodo' => [
        'inicio' => $dataInicio,
        'fim' => $dataFim,
        'tipo' => $tipo
    ],
    'resumo' => $stats,
    'media_diaria' => round($mediaDiaria, 1),
    'taxa_comparecimento' => $taxaComparecimento,
    'por_servico' => $servicosStats,
    'por_hora' => $horasStats
]);
