<?php
http_response_code(500);
include 'includes/header.php';
?>
<div class="container">
    <div class="card text-center" style="padding: 3rem;">
        <h1 style="font-size: 4rem; margin: 0;">500</h1>
        <h2>Erro no Servidor</h2>
        <p>Ocorreu um erro inesperado. Por favor tente novamente mais tarde.</p>
        <a href="<?= SITE_URL ?>" class="btn" style="display: inline-block; margin-top: 1rem; padding: 0.75rem 2rem; background: #0066cc; color: white; text-decoration: none; border-radius: 5px;">
            ← Voltar ao Início
        </a>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
