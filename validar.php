<?php
require_once '../config/config.php';
$db = getDbConnection();
exigirLogin();

$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = strtoupper(sanitize($_POST['codigo'] ?? ''));

    if (!empty($codigo)) {
        $stmt = $db->prepare("SELECT a.*, s.nome as servico_nome, e.nome as esquadra_nome
                              FROM agendamentos a
                              JOIN servicos s ON a.servico_id = s.id
                              JOIN esquadras e ON a.esquadra_id = e.id
                              WHERE a.codigo_agendamento = ?");
        $stmt->execute([$codigo]);
        $resultado = $stmt->fetch();
    }
}

include 'includes/header.php';
?>

<div class="container container-narrow">
    <a href="dashboard.php" class="btn btn-secondary mb-2">← Voltar ao Dashboard</a>

    <div class="card">
        <div class="card-header">
            <h2>Validar Código de Agendamento</h2>
            <p>Introduza o código recebido pelo cidadão</p>
        </div>

        <form method="POST">
            <div class="form-group">
                <label for="codigo">Código de Agendamento</label>
                <input type="text" id="codigo" name="codigo"
                       placeholder="Ex: PSP-ABC123"
                       value="<?= $_POST['codigo'] ?? '' ?>"
                       style="text-transform: uppercase; font-size: 1.2rem; text-align: center;"
                       autofocus>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Validar</button>
        </form>

        <?php if ($resultado): ?>
            <hr class="mt-2 mb-2">

            <div class="alert <?= $resultado['estado'] === 'cancelado' || $resultado['estado'] === 'faltou' ? 'alert-danger' : 'alert-success' ?>">
                <?php
                if ($resultado['estado'] === 'concluido') {
                    echo '✅ Agendamento já concluído';
                } elseif ($resultado['estado'] === 'cancelado') {
                    echo '❌ Agendamento cancelado';
                } elseif ($resultado['estado'] === 'faltou') {
                    echo '❌ Cidadão faltou';
                } elseif ($resultado['estado'] === 'em_atendimento') {
                    echo '📢 Cidadão já está em atendimento';
                } elseif ($resultado['estado'] === 'presente') {
                    echo '✅ Cidadão já deu check-in';
                } else {
                    echo '✅ Agendamento válido - pronto para check-in';
                }
                ?>
            </div>

            <div style="text-align: center;">
                <div class="codigo" style="font-size: 2rem;"><?= sanitize($resultado['codigo_agendamento']) ?></div>
            </div>

            <div class="mt-2">
                <p><strong>Cidadão:</strong> <?= sanitize($resultado['nome_cidadao']) ?></p>
                <p><strong>Serviço:</strong> <?= sanitize($resultado['servico_nome']) ?></p>
                <p><strong>Esquadra:</strong> <?= sanitize($resultado['esquadra_nome']) ?></p>
                <p><strong>Data:</strong> <?= formatarData($resultado['data_agendamento']) ?></p>
                <p><strong>Hora:</strong> <?= formatarHora($resultado['hora_agendamento']) ?></p>
                <p><strong>Estado:</strong>
                    <span class="estado estado-<?= $resultado['estado'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $resultado['estado'])) ?>
                    </span>
                </p>
            </div>

            <?php if ($resultado['estado'] === 'confirmado'): ?>
                <form method="POST" action="api_validar_checkin.php" class="mt-2">
                    <input type="hidden" name="id" value="<?= $resultado['id'] ?>">
                    <button type="submit" class="btn btn-success btn-block">
                        ✅ Registar Check-in (Marcar como Presente)
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
