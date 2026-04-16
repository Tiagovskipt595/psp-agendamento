<?php
require_once '../config/config.php';
$db = getDbConnection();
exigirLogin();

$esquadraId = $_SESSION['esquadra_id'];
$filtroPeriodo = $_GET['periodo'] ?? 'dia';
$dataFiltro = $_GET['data'] ?? date('Y-m-d');

// Calcular datas do período
if ($filtroPeriodo === 'semana') {
    $dataInicio = date('Y-m-d', strtotime('monday this week', strtotime($dataFiltro)));
    $dataFim = date('Y-m-d', strtotime('sunday this week', strtotime($dataFiltro)));
} elseif ($filtroPeriodo === 'mes') {
    $dataInicio = date('Y-m-01', strtotime($dataFiltro));
    $dataFim = date('Y-m-t', strtotime($dataFiltro));
} elseif ($filtroPeriodo === 'dia') {
    $dataInicio = $dataFiltro;
    $dataFim = $dataFiltro;
} else {
    $dataInicio = $dataFiltro;
    $dataFim = $dataFiltro;
}

// Estatísticas do período
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

// Calcular taxa de comparecimento
$totalNaoCancelados = $stats['total'] - $stats['cancelados'];
$taxaComparecimento = $totalNaoCancelados > 0
    ? round((($stats['presentes'] + $stats['concluidos']) / $totalNaoCancelados) * 100, 1)
    : 0;

// Agendamentos do dia selecionado
$stmt = $db->prepare("SELECT a.*, s.nome as servico_nome
                      FROM agendamentos a
                      JOIN servicos s ON a.servico_id = s.id
                      WHERE a.esquadra_id = ? AND a.data_agendamento = ?
                      ORDER BY a.hora_agendamento ASC");
$stmt->execute([$esquadraId, $dataFiltro]);
$agendamentos = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container">
    <div class="dashboard">
        <aside class="sidebar">
            <h3>Menu</h3>
            <ul>
                <li><a href="dashboard.php" class="ativo">📊 Visão Geral</a></li>
                <li><a href="lista-agendamentos.php">📅 Todos Agendamentos</a></li>
                <li><a href="validar.php">✅ Validar Código</a></li>
                <li><a href="novo-agendamento.php">➕ Novo Agendamento</a></li>
            </ul>

            <h3 class="mt-2">Agente</h3>
            <p style="font-size: 0.9rem; color: var(--text-light);">
                <?= sanitize($_SESSION['usuario_nome']) ?><br>
                <?= sanitize($_SESSION['esquadra_nome']) ?>
            </p>
        </aside>

        <div class="dashboard-content">
            <!-- Filtros -->
            <div class="card" style="padding: 20px; margin-bottom: 20px;">
                <form method="GET" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <div class="form-group" style="margin: 0; min-width: 200px;">
                        <label style="font-size: 0.85rem; margin-bottom: 5px;">Período</label>
                        <select name="periodo" id="filtroPeriodo" onchange="this.form.submit()">
                            <option value="dia" <?= $filtroPeriodo === 'dia' ? 'selected' : '' ?>>Dia</option>
                            <option value="semana" <?= $filtroPeriodo === 'semana' ? 'selected' : '' ?>>Semana</option>
                            <option value="mes" <?= $filtroPeriodo === 'mes' ? 'selected' : '' ?>>Mês</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin: 0; min-width: 200px;">
                        <label style="font-size: 0.85rem; margin-bottom: 5px;">Data</label>
                        <input type="date" name="data" value="<?= $dataFiltro ?>" onchange="this.form.submit()">
                    </div>
                    <div style="flex: 1;"></div>
                    <button type="button" class="btn btn-secondary" onclick="exportarPDF()">📄 Exportar PDF</button>
                    <button type="button" class="btn btn-secondary" onclick="exportarCSV()">📊 Exportar CSV</button>
                </form>
            </div>

            <h2>Visão Geral - <?= formatarData($dataFiltro, 'd \d\e F \d\e Y') ?>
                <?php if ($filtroPeriodo !== 'dia'): ?>
                    <span style="font-size: 0.9rem; color: var(--text-light);">
                        (<?= formatarData($dataInicio) ?> a <?= formatarData($dataFim) ?>)
                    </span>
                <?php endif; ?>
            </h2>

            <div class="stats-grid mt-2">
                <div class="stat-card">
                    <div class="numero"><?= $stats['total'] ?></div>
                    <div class="label">Total</div>
                </div>
                <div class="stat-card">
                    <div class="numero" style="color: var(--info);"><?= $stats['confirmados'] ?></div>
                    <div class="label">Confirmados</div>
                </div>
                <div class="stat-card">
                    <div class="numero" style="color: var(--warning);"><?= $stats['presentes'] ?></div>
                    <div class="label">Presentes</div>
                </div>
                <div class="stat-card">
                    <div class="numero" style="color: var(--primary-color);"><?= $stats['em_atendimento'] ?></div>
                    <div class="label">Em Atendimento</div>
                </div>
                <div class="stat-card">
                    <div class="numero" style="color: var(--success);"><?= $stats['concluidos'] ?></div>
                    <div class="label">Concluídos</div>
                </div>
                <div class="stat-card">
                    <div class="numero" style="color: var(--danger);"><?= $stats['cancelados'] ?></div>
                    <div class="label">Cancelados</div>
                </div>
                <div class="stat-card">
                    <div class="numero" style="color: var(--danger);"><?= $stats['faltas'] ?></div>
                    <div class="label">Faltas</div>
                </div>
                <div class="stat-card">
                    <div class="numero" style="color: <?= $taxaComparecimento >= 80 ? 'var(--success)' : 'var(--warning)' ?>;"><?= $taxaComparecimento ?>%</div>
                    <div class="label">Taxa Comparecimento</div>
                </div>
            </div>

            <h3 class="mb-2">Agendamentos de Hoje</h3>

            <?php if (empty($agendamentos)): ?>
                <div class="alert alert-info">
                    Não há agendamentos para hoje.
                </div>
            <?php else: ?>
                <table class="tabela-agendamentos">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Cidadão</th>
                            <th>Serviço</th>
                            <th>Código</th>
                            <th>Estado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agendamentos as $agendamento): ?>
                            <tr>
                                <td><?= formatarHora($agendamento['hora_agendamento']) ?></td>
                                <td><?= sanitize($agendamento['nome_cidadao']) ?></td>
                                <td><?= sanitize($agendamento['servico_nome']) ?></td>
                                <td><code><?= sanitize($agendamento['codigo_agendamento']) ?></code></td>
                                <td>
                                    <span class="estado estado-<?= $agendamento['estado'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $agendamento['estado'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <?php if ($agendamento['estado'] === 'confirmado'): ?>
                                            <button class="btn btn-success" style="padding: 5px 10px; font-size: 0.85rem;"
                                                    onclick="atualizarEstado(<?= $agendamento['id'] ?>, 'presente')">
                                                ✓
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($agendamento['estado'] === 'presente'): ?>
                                            <button class="btn btn-primary" style="padding: 5px 10px; font-size: 0.85rem;"
                                                    onclick="atualizarEstado(<?= $agendamento['id'] ?>, 'em_atendimento')">
                                                📢
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($agendamento['estado'] === 'em_atendimento'): ?>
                                            <button class="btn btn-success" style="padding: 5px 10px; font-size: 0.85rem;"
                                                    onclick="atualizarEstado(<?= $agendamento['id'] ?>, 'concluido')">
                                                ✓✓
                                            </button>
                                        <?php endif; ?>
                                        <a href="editar-agendamento.php?id=<?= $agendamento['id'] ?>"
                                           class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.85rem;">
                                            ✏️
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
async function atualizarEstado(id, novoEstado) {
    if (!confirm('Confirmar mudança de estado para: ' + novoEstado)) return;

    try {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('estado', novoEstado);

        const response = await fetch('api_atualizar_estado.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (result.sucesso) {
            Toast.success('Estado atualizado com sucesso');
            setTimeout(() => location.reload(), 500);
        } else {
            Toast.error('Erro: ' + result.erro);
        }
    } catch (e) {
        Toast.error('Erro ao atualizar estado');
    }
}

function exportarCSV() {
    const periodo = document.getElementById('filtroPeriodo').value;
    const data = document.querySelector('input[name="data"]').value;
    window.location.href = `export_csv.php?periodo=${periodo}&data=${data}`;
}

function exportarPDF() {
    const periodo = document.getElementById('filtroPeriodo').value;
    const data = document.querySelector('input[name="data"]').value;
    window.open(`export_pdf.php?periodo=${periodo}&data=${data}`, '_blank');
}
</script>

<?php include 'includes/footer.php'; ?>
