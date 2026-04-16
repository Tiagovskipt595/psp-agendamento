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
    $tituloPeriodo = 'Semana de ' . formatarData($dataInicio);
} elseif ($filtroPeriodo === 'mes') {
    $dataInicio = date('Y-m-01', strtotime($dataFiltro));
    $dataFim = date('Y-m-t', strtotime($dataFiltro));
    $tituloPeriodo = date('F Y', strtotime($dataFiltro));
} else {
    $dataInicio = $dataFiltro;
    $dataFim = $dataFiltro;
    $tituloPeriodo = formatarData($dataFiltro);
}

// Estatísticas
$stmt = $db->prepare("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'confirmado' THEN 1 ELSE 0 END) as confirmados,
    SUM(CASE WHEN estado = 'concluido' THEN 1 ELSE 0 END) as concluidos,
    SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
    SUM(CASE WHEN estado = 'faltou' THEN 1 ELSE 0 END) as faltas
    FROM agendamentos
    WHERE esquadra_id = ? AND data_agendamento BETWEEN ? AND ?");
$stmt->execute([$esquadraId, $dataInicio, $dataFim]);
$stats = $stmt->fetch();

// Agendamentos
$stmt = $db->prepare("SELECT a.*, s.nome as servico_nome
                      FROM agendamentos a
                      JOIN servicos s ON a.servico_id = s.id
                      WHERE a.esquadra_id = ? AND a.data_agendamento BETWEEN ? AND ?
                      ORDER BY a.data_agendamento ASC, a.hora_agendamento ASC");
$stmt->execute([$esquadraId, $dataInicio, $dataFim]);
$agendamentos = $stmt->fetchAll();

// Gerar HTML para PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        h1 { color: #003366; border-bottom: 2px solid #003366; padding-bottom: 10px; }
        h2 { color: #003366; font-size: 14px; margin-top: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .info { background: #f5f5f5; padding: 10px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #003366; color: white; padding: 8px; text-align: left; font-size: 11px; }
        td { padding: 6px; border-bottom: 1px solid #ddd; }
        tr:nth-child(even) { background: #f9f9f9; }
        .stats { display: table; width: 100%; margin: 15px 0; }
        .stat-item { display: table-cell; text-align: center; padding: 10px; background: #f5f5f5; margin: 0 5px; border-radius: 5px; }
        .stat-number { font-size: 24px; font-weight: bold; color: #003366; }
        .stat-label { font-size: 10px; color: #666; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <h1>👮 PSP - Polícia de Segurança Pública</h1>
        <p>Relatório de Agendamentos</p>
    </div>

    <div class="info">
        <strong>Período:</strong> ' . e($tituloPeriodo) . '<br>
        <strong>Esquadra:</strong> ' . e($_SESSION['esquadra_nome']) . '<br>
        <strong>Data Emissão:</strong> ' . date('d/m/Y H:i') . '
    </div>

    <h2>Resumo Estatístico</h2>
    <div class="stats">
        <div class="stat-item">
            <div class="stat-number">' . $stats['total'] . '</div>
            <div class="stat-label">Total</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">' . $stats['confirmados'] . '</div>
            <div class="stat-label">Confirmados</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">' . $stats['concluidos'] . '</div>
            <div class="stat-label">Concluídos</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">' . $stats['cancelados'] . '</div>
            <div class="stat-label">Cancelados</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">' . $stats['faltas'] . '</div>
            <div class="stat-label">Faltas</div>
        </div>
    </div>

    <h2>Lista de Agendamentos</h2>
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Hora</th>
                <th>Cidadão</th>
                <th>Serviço</th>
                <th>Código</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>';

foreach ($agendamentos as $agendamento) {
    $html .= '
            <tr>
                <td>' . formatarData($agendamento['data_agendamento']) . '</td>
                <td>' . formatarHora($agendamento['hora_agendamento']) . '</td>
                <td>' . e($agendamento['nome_cidadao']) . '</td>
                <td>' . e($agendamento['servico_nome']) . '</td>
                <td>' . e($agendamento['codigo_agendamento']) . '</td>
                <td>' . e(ucfirst(str_replace('_', ' ', $agendamento['estado']))) . '</td>
            </tr>';
}

$html .= '
        </tbody>
    </table>

    <div class="footer">
        <p>Documento gerado automaticamente - ' . SITE_NAME . '</p>
    </div>
</body>
</html>';

// Usar DomPDF se disponível, senão imprimir HTML
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';

    $dompdf = new Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="relatorio_agendamentos_' . $dataInicio . '.pdf"');
    echo $dompdf->output();
    exit;
} else {
    // Fallback: imprimir como HTML
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}
