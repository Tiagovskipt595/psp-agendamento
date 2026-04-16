<?php
require_once '../config/config.php';
exigirLogin();

$db = getDbConnection();
$esquadraId = $_SESSION['esquadra_id'];
$filtroPeriodo = $_GET['periodo'] ?? 'dia';
$dataFiltro = $_GET['data'] ?? date('Y-m-d');

// Calcular datas
if ($filtroPeriodo === 'semana') {
    $dataInicio = date('Y-m-d', strtotime('monday this week', strtotime($dataFiltro)));
    $dataFim = date('Y-m-d', strtotime('sunday this week', strtotime($dataFiltro)));
} elseif ($filtroPeriodo === 'mes') {
    $dataInicio = date('Y-m-01', strtotime($dataFiltro));
    $dataFim = date('Y-m-t', strtotime($dataFiltro));
} else {
    $dataInicio = $dataFiltro;
    $dataFim = $dataFiltro;
}

// Buscar agendamentos
$stmt = $db->prepare("SELECT a.codigo_agendamento, a.nome_cidadao, a.cc_numero, a.email,
                      a.telemovel, a.data_agendamento, a.hora_agendamento, a.estado,
                      s.nome as servico, e.nome as esquadra
                      FROM agendamentos a
                      JOIN servicos s ON a.servico_id = s.id
                      JOIN esquadras e ON a.esquadra_id = e.id
                      WHERE a.esquadra_id = ? AND a.data_agendamento BETWEEN ? AND ?
                      ORDER BY a.data_agendamento ASC, a.hora_agendamento ASC");
$stmt->execute([$esquadraId, $dataInicio, $dataFim]);
$agendamentos = $stmt->fetchAll();

// Headers para download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="agendamentos_' . $dataInicio . '_' . $dataFim . '.csv"');

$output = fopen('php://output', 'w');

// BOM para UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeçalho
fputcsv($output, [
    'Código',
    'Nome',
    'CC',
    'Email',
    'Telemóvel',
    'Data',
    'Hora',
    'Estado',
    'Serviço',
    'Esquadra'
]);

// Dados
foreach ($agendamentos as $agendamento) {
    fputcsv($output, [
        $agendamento['codigo_agendamento'],
        $agendamento['nome_cidadao'],
        $agendamento['cc_numero'],
        $agendamento['email'],
        $agendamento['telemovel'],
        formatarData($agendamento['data_agendamento']),
        $agendamento['hora_agendamento'],
        $agendamento['estado'],
        $agendamento['servico'],
        $agendamento['esquadra']
    ]);
}

fclose($output);
exit;
