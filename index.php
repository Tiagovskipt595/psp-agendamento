<?php
require_once '../config/config.php';

// Verificar conexão com banco de dados
try {
    $db = getDbConnection();
    $dbErro = false;

    // Buscar esquadras ativas
    $stmt = $db->query("SELECT * FROM esquadras WHERE ativo = 1");
    $esquadras = $stmt->fetchAll();
} catch (Exception $e) {
    $dbErro = true;
    $esquadras = [];
}

include 'includes/header.php';
?>

<div class="container">
    <!-- Hero Section -->
    <div class="card text-center" style="padding: 2.5rem; margin-bottom: 2rem;">
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">👮 Agendamento Online PSP</h1>
        <p style="color: #666; margin-bottom: 1.5rem;">Agende seu atendimento de forma rápida e simples</p>

        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="#esquadras" class="btn" style="padding: 0.75rem 1.5rem; background: #0066cc; color: white; text-decoration: none; border-radius: 5px;">
                📍 Selecionar Esquadra
            </a>
            <a href="validar.php" class="btn" style="padding: 0.75rem 1.5rem; background: #28a745; color: white; text-decoration: none; border-radius: 5px;">
                ✅ Validar Agendamento
            </a>
        </div>
    </div>

    <?php if ($dbErro): ?>
        <div class="alert alert-error">
            <strong>Erro de conexão com o banco de dados.</strong>
            <p>Verifique se o banco está configurado e rodando.</p>
            <details style="margin-top: 0.5rem;">
                <summary style="cursor: pointer;">Ver detalhes técnicos</summary>
                <pre style="background: #f5f5f5; padding: 1rem; margin-top: 0.5rem; border-radius: 4px; overflow-x: auto;">
<?= htmlspecialchars($e->getMessage()) ?>
                </pre>
            </details>
        </div>
    <?php elseif (empty($esquadras)): ?>
        <div class="alert alert-warning">
            <strong>Nenhuma esquadra disponível no momento.</strong>
            <p>Tente novamente mais tarde.</p>
        </div>
    <?php else: ?>
        <h2 id="esquadras" style="margin: 2rem 0 1rem;">Esquadras Disponíveis</h2>

        <div class="grid-esquadras">
            <?php foreach ($esquadras as $esquadra): ?>
                <div class="card-esquadra" onclick="selecionarEsquadra(<?= $esquadra['id'] ?>)" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                    <h3><?= sanitize($esquadra['nome']) ?></h3>
                    <p>📍 <?= sanitize($esquadra['morada']) ?></p>
                    <p>📞 <?= sanitize($esquadra['telefone']) ?></p>
                    <span style="display: inline-block; margin-top: 0.75rem; padding: 0.25rem 0.75rem; background: #0066cc; color: white; border-radius: 4px; font-size: 0.875rem;">
                        Agendar →
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Informações Rápidas -->
    <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #eee;">
        <h3 style="text-align: center; margin-bottom: 1.5rem;">Como Funciona</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
            <div style="text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">1️⃣</div>
                <strong>Escolha a Esquadra</strong>
                <p style="color: #666; font-size: 0.9rem;">Selecione a esquadra mais próxima</p>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">2️⃣</div>
                <strong>Selecione o Serviço</strong>
                <p style="color: #666; font-size: 0.9rem;">Escolha o tipo de atendimento</p>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">3️⃣</div>
                <strong>Agende</strong>
                <p style="color: #666; font-size: 0.9rem;">Escolha data e hora convenientes</p>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">4️⃣</div>
                <strong>Confirme</strong>
                <p style="color: #666; font-size: 0.9rem;">Receba confirmação por email</p>
            </div>
        </div>
    </div>
</div>

<script>
function selecionarEsquadra(esquadraId) {
    window.location.href = 'servicos.php?esquadra_id=' + esquadraId;
}
</script>

<?php include 'includes/footer.php'; ?>
