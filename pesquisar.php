<?php
require_once '../config/config.php';
$db = getDbConnection();
exigirLogin();

$esquadraId = $_SESSION['esquadra_id'];
$resultados = [];
$termo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('erro', 'Token de segurança inválido');
    } else {
        $termo = sanitizeAvancado($_POST['termo'] ?? '');

        if (!empty($termo)) {
            $stmt = $db->prepare("SELECT a.*, s.nome as servico_nome
                                  FROM agendamentos a
                                  JOIN servicos s ON a.servico_id = s.id
                                  WHERE a.esquadra_id = ?
                                  AND (a.nome_cidadao LIKE ? OR a.cc_numero LIKE ? OR a.codigo_agendamento LIKE ? OR a.email LIKE ?)
                                  ORDER BY a.data_agendamento DESC, a.hora_agendamento DESC
                                  LIMIT 50");
            $likeTerm = "%{$termo}%";
            $stmt->execute([$esquadraId, $likeTerm, $likeTerm, $likeTerm, $likeTerm]);
            $resultados = $stmt->fetchAll();
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="dashboard">
        <aside class="sidebar">
            <h3>Menu</h3>
            <ul>
                <li><a href="dashboard.php">📊 Visão Geral</a></li>
                <li><a href="lista-agendamentos.php">📅 Todos Agendamentos</a></li>
                <li><a href="validar.php">✅ Validar Código</a></li>
                <li><a href="novo-agendamento.php">➕ Novo Agendamento</a></li>
                <li><a href="pesquisar.php" class="ativo">🔍 Pesquisar</a></li>
            </ul>
        </aside>

        <div class="dashboard-content">
            <h2>🔍 Pesquisar Agendamentos</h2>

            <div class="card" style="padding: 20px; margin-bottom: 20px;">
                <form method="POST">
                    <?= csrfInput() ?>
                    <div style="display: flex; gap: 10px; align-items: flex-end;">
                        <div class="form-group" style="flex: 1; margin: 0;">
                            <label for="termo">Pesquisar por</label>
                            <input type="text" id="termo" name="termo"
                                   placeholder="Nome, CC, Código ou Email"
                                   value="<?= e($termo) ?>"
                                   style="width: 100%;">
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg">Pesquisar</button>
                        <?php if (!empty($termo)): ?>
                            <a href="pesquisar.php" class="btn btn-secondary btn-lg">Limpar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if (!empty($termo)): ?>
                <h3>Resultados (<?= count($resultados) ?>)</h3>

                <?php if (empty($resultados)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🔍</div>
                        <h3>Nenhum resultado encontrado</h3>
                        <p>Tente outros termos de pesquisa</p>
                    </div>
                <?php else: ?>
                    <table class="tabela-agendamentos">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Nome</th>
                                <th>CC</th>
                                <th>Serviço</th>
                                <th>Código</th>
                                <th>Estado</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados as $agendamento): ?>
                                <tr>
                                    <td><?= formatarData($agendamento['data_agendamento']) ?></td>
                                    <td><?= formatarHora($agendamento['hora_agendamento']) ?></td>
                                    <td><?= e($agendamento['nome_cidadao']) ?></td>
                                    <td><code><?= e($agendamento['cc_numero']) ?></code></td>
                                    <td><?= e($agendamento['servico_nome']) ?></td>
                                    <td><code><?= e($agendamento['codigo_agendamento']) ?></code></td>
                                    <td>
                                        <span class="estado estado-<?= $agendamento['estado'] ?>">
                                            <?= ucfirst(str_replace('_', ' ', $agendamento['estado'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary"
                                                onclick="verDetalhes(<?= $agendamento['id'] ?>)">
                                            👁️
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🔍</div>
                    <h3>Pesquisar Agendamentos</h3>
                    <p>Insira um nome, número de CC, código ou email para pesquisar</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
