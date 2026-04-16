<?php
/**
 * API: Calendário de Disponibilidade Mensal
 * Retorna disponibilidade por dia para um determinado mês
 */
require_once 'config/config.php';

header('Content-Type: application/json');

$esquadraId = filter_input(INPUT_GET, 'esquadra_id', FILTER_VALIDATE_INT);
$mes = filter_input(INPUT_GET, 'mes', FILTER_DEFAULT); // YYYY-MM
$servicoId = filter_input(INPUT_GET, 'servico_id', FILTER_VALIDATE_INT);

if (!$esquadraId || !$mes) {
    echo json_encode(['erro' => 'Parâmetros inválidos']);
    exit;
}

// Validar formato do mês
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    echo json_encode(['erro' => 'Formato de mês inválido']);
    exit;
}

$db = getDbConnection();

// Buscar duração do serviço (se especificado)
$duracao = 30; // default
if ($servicoId) {
    $stmt = $db->prepare("SELECT duracao_minutos FROM servicos WHERE id = ? AND ativo = 1");
    $stmt->execute([$servicoId]);
    $servico = $stmt->fetch();
    if ($servico) {
        $duracao = (int)$servico['duracao_minutos'];
    }
}

// Extrair ano e mês
list($ano, $mesNum) = explode('-', $mes);
$mesNum = (int)$mesNum;
$ano = (int)$ano;

// Primeiro e último dia do mês
$primeiroDia = mktime(0, 0, 0, $mesNum, 1, $ano);
$ultimoDia = mktime(23, 59, 59, $mesNum + 1, 0, $ano);

// Obter dia da semana do primeiro dia (0=Domingo, 1=Segunda, ...)
$diaSemanaPrimeiro = (int)date('w', $primeiroDia);

// Ajustar para segunda=0
if ($diaSemanaPrimeiro === 0) $diaSemanaPrimeiro = 6;
else $diaSemanaPrimeiro--;

$totalDias = (int)date('t', $primeiroDia);
$diaAtual = date('Y-m-d');
$horaAtual = date('H:i');

// Buscar horários da esquadra para cada dia da semana
$stmt = $db->prepare("SELECT dia_semana, hora_inicio, hora_fim FROM horarios WHERE esquadra_id = ?");
$stmt->execute([$esquadraId]);
$horarios = [];
while ($row = $stmt->fetch()) {
    $horarios[$row['dia_semana']] = [
        'inicio' => $row['hora_inicio'],
        'fim' => $row['hora_fim']
    ];
}

// Buscar agendamentos do mês
$stmt = $db->prepare("
    SELECT data_agendamento, hora_agendamento, estado
    FROM agendamentos
    WHERE esquadra_id = ?
    AND data_agendamento >= ?
    AND data_agendamento <= ?
    AND estado IN ('confirmado', 'presente', 'em_atendimento')
");
$stmt->execute([$esquadraId, date('Y-m-d', $primeiroDia), date('Y-m-d', $ultimoDia)]);
$agendamentos = $stmt->fetchAll();

// Contar agendamentos por dia
$agendamentosPorDia = [];
foreach ($agendamentos as $ag) {
    $data = $ag['data_agendamento'];
    if (!isset($agendamentosPorDia[$data])) {
        $agendamentosPorDia[$data] = 0;
    }
    $agendamentosPorDia[$data]++;
}

// Calcular vagas por dia (baseado nos horários)
function calcularVagas($diaSemana, $horarios, $duracao) {
    if (!isset($horarios[$diaSemana])) {
        return 0; // Esquadra não abre neste dia
    }

    $inicio = strtotime($horarios[$diaSemana]['inicio']);
    $fim = strtotime($horarios[$diaSemana]['fim']);
    $intervalo = $duracao * 60; // em segundos

    return (int)floor(($fim - $inicio) / $intervalo);
}

$resultado = [];

for ($dia = 1; $dia <= $totalDias; $dia++) {
    $dataCompleta = sprintf('%04d-%02d-%02d', $ano, $mesNum, $dia);
    $dataObj = new DateTime($dataCompleta);

    // Dia da semana (0=Segunda a 6=Domingo)
    $diaSemana = (int)$dataObj->format('N') - 1;

    // Calcular vagas totais para este dia
    $vagasTotais = calcularVagas($diaSemana, $horarios, $duracao);

    // Número de agendamentos neste dia
    $agendamentosCount = $agendamentosPorDia[$dataCompleta] ?? 0;

    // Determinar disponibilidade
    if ($vagasTotais === 0) {
        $disponivel = false;
        $estado = 'fechado';
        $vagas = 0;
    } elseif ($dataCompleta < $diaAtual) {
        $disponivel = false;
        $estado = 'passado';
        $vagas = 0;
    } else {
        $vagasRestantes = $vagasTotais - $agendamentosCount;
        if ($vagasRestantes <= 0) {
            $disponivel = false;
            $estado = 'completo';
            $vagas = 0;
        } elseif ($vagasRestantes <= 2) {
            $disponivel = true;
            $estado = 'limitado';
            $vagas = $vagasRestantes;
        } else {
            $disponivel = true;
            $estado = 'disponivel';
            $vagas = $vagasRestantes;
        }
    }

    $resultado[$dataCompleta] = [
        'disponivel' => $disponivel,
        'estado' => $estado,
        'vagas' => $vagas,
        'vagas_totais' => $vagasTotais
    ];
}

echo json_encode($resultado);
