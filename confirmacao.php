<?php
require_once '../config/config.php';
$db = getDbConnection();

$codigo = filter_input(INPUT_GET, 'codigo', FILTER_SANITIZE_SPECIAL_CHARS);

if (!$codigo) {
    redirect(SITE_URL . 'index.php');
}

// Buscar agendamento
$stmt = $db->prepare("SELECT a.*, e.nome as esquadra_nome, e.morada as esquadra_morada, s.nome as servico_nome
                      FROM agendamentos a
                      JOIN esquadras e ON a.esquadra_id = e.id
                      JOIN servicos s ON a.servico_id = s.id
                      WHERE a.codigo_agendamento = ?");
$stmt->execute([$codigo]);
$agendamento = $stmt->fetch();

if (!$agendamento) {
    redirect(SITE_URL . 'index.php');
}

include 'includes/header.php';
?>

<div class="container container-narrow">
    <div class="card confirmacao">
        <div class="alert alert-success">
            ✅ Agendamento realizado com sucesso!
        </div>

        <h2>O Seu Agendamento</h2>

        <div class="codigo"><?= sanitize($agendamento['codigo_agendamento']) ?></div>

        <!-- QR Code gerado via API do Google Charts -->
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($agendamento['codigo_agendamento']) ?>"
             alt="QR Code" class="qr-code">

        <div class="mt-2 text-left">
            <p><strong>Serviço:</strong> <?= sanitize($agendamento['servico_nome']) ?></p>
            <p><strong>Esquadra:</strong> <?= sanitize($agendamento['esquadra_nome']) ?></p>
            <p><strong>Morada:</strong> <?= sanitize($agendamento['esquadra_morada']) ?></p>
            <p><strong>Data:</strong> <?= formatarData($agendamento['data_agendamento']) ?></p>
            <p><strong>Hora:</strong> <?= formatarHora($agendamento['hora_agendamento']) ?>
            <p><strong>Nome:</strong> <?= sanitize($agendamento['nome_cidadao']) ?></p>
        </div>

        <div class="alert alert-info mt-2">
            📱 Foi enviada uma confirmação para o seu email e telemóvel.
        </div>

        <p class="mt-2" style="color: var(--text-light);">
            Por favor, chegue 10 minutos antes da hora marcada e apresente este código na receção.
        </p>

        <a href="<?= SITE_URL ?>" class="btn btn-primary mt-2">Página Inicial</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
