<?php
require_once '../config/config.php';
$db = getDbConnection();

$esquadraId = filter_input(INPUT_GET, 'esquadra_id', FILTER_VALIDATE_INT);

if (!$esquadraId) {
    redirect(SITE_URL . 'index.php');
}

// Buscar dados da esquadra
$stmt = $db->prepare("SELECT * FROM esquadras WHERE id = ? AND ativo = 1");
$stmt->execute([$esquadraId]);
$esquadra = $stmt->fetch();

if (!$esquadra) {
    redirect(SITE_URL . 'index.php');
}

// Buscar serviços da esquadra
$stmt = $db->prepare("SELECT * FROM servicos WHERE esquadra_id = ? AND ativo = 1");
$stmt->execute([$esquadraId]);
$servicos = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container">
    <a href="index.php" class="btn btn-secondary mb-2">← Voltar</a>

    <div class="card">
        <div class="card-header">
            <h2><?= sanitize($esquadra['nome']) ?></h2>
            <p><?= sanitize($esquadra['morada']) ?></p>
        </div>
        <p>Selecione o serviço que deseja agendar:</p>
    </div>

    <div class="grid-servicos">
        <?php foreach ($servicos as $servico): ?>
            <div class="card-servico" onclick="selecionarServico(<?= $servico['id'] ?>)">
                <h3><?= sanitize($servico['nome']) ?></h3>
                <p><?= sanitize($servico['descricao']) ?></p>
                <span class="duracao">⏱️ <?= $servico['duracao_minutos'] ?> minutos</span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($servicos)): ?>
        <div class="alert alert-warning">
            ⚠️ Não há serviços disponíveis para esta esquadra.
        </div>
    <?php endif; ?>
</div>

<script>
function selecionarServico(servicoId) {
    window.location.href = 'agendar.php?esquadra_id=<?= $esquadraId ?>&servico_id=' + servicoId;
}
</script>

<?php include 'includes/footer.php'; ?>
