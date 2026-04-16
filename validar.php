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

            <?php if ($resultado['estado'] === 'confirmado' || $resultado['estado'] === 'presente'): ?>
                <hr class="mt-2 mb-2">
                <form method="POST" action="api_validar_checkin.php" id="formCheckin">
                    <input type="hidden" name="id" value="<?= $resultado['id'] ?>">

                    <div class="form-group">
                        <label for="observacoes">📝 Observações (opcional)</label>
                        <textarea id="observacoes" name="observacoes" rows="3"
                                  placeholder="Notas sobre o atendimento..."><?= sanitize($resultado['observacoes'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-success btn-block">
                        ✅ Registar Check-in (Marcar como Presente)
                    </button>
                </form>

                <?php if ($resultado['estado'] === 'presente'): ?>
                    <button type="button" class="btn btn-primary btn-block mt-1" onclick="iniciarAtendimento(<?= $resultado['id'] ?>)">
                        📢 Iniciar Atendimento
                    </button>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($resultado['estado'] === 'em_atendimento'): ?>
                <hr class="mt-2 mb-2">
                <div class="alert alert-info">
                    📢 Cidadão está actualmente em atendimento
                </div>

                <?php if (!empty($resultado['observacoes'])): ?>
                    <div class="mt-2">
                        <strong>Observações:</strong>
                        <p style="white-space: pre-wrap;"><?= sanitize($resultado['observacoes']) ?></p>
                    </div>
                <?php endif; ?>

                <button type="button" class="btn btn-success btn-block mt-1" onclick="mostrarModalConcluir(<?= $resultado['id'] ?>)">
                    ✅ Concluir Atendimento
                </button>
            <?php endif; ?>

            <?php if ($resultado['estado'] === 'concluido'): ?>
                <?php if (!empty($resultado['resultado'])): ?>
                    <div class="mt-2">
                        <strong>Resultado:</strong>
                        <span class="estado <?= $resultado['resultado'] === 'atendido' ? 'estado-concluido' : 'estado-cancelado' ?>">
                            <?= ucfirst(str_replace('_', ' ', $resultado['resultado'])) ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resultado['observacoes'])): ?>
                    <div class="mt-2">
                        <strong>Observações:</strong>
                        <p style="white-space: pre-wrap;"><?= sanitize($resultado['observacoes']) ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Concluir Atendimento -->
<div class="modal-overlay" id="modalConcluir">
    <div class="modal">
        <div class="modal-header">
            <h3>Concluir Atendimento</h3>
            <button type="button" class="modal-close" onclick="fecharModal()">&times;</button>
        </div>
        <form id="formConcluir">
            <input type="hidden" id="concluirId" name="id">

            <div class="form-group">
                <label for="resultado">Resultado *</label>
                <select id="resultado" name="resultado" required>
                    <option value="">Selecione...</option>
                    <option value="atendido">✅ Atendido</option>
                    <option value="nao_atendido">❌ Não Atendido</option>
                    <option value="documentos_faltantes">📄 Documentos Faltantes</option>
                </select>
            </div>

            <div class="form-group">
                <label for="observacoesFim">Observações</label>
                <textarea id="observacoesFim" name="observacoes" rows="4"
                          placeholder="Notas sobre o atendimento..."></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="fecharModal()">Cancelar</button>
                <button type="submit" class="btn btn-success">Confirmar</button>
            </div>
        </form>
    </div>
</div>

<script>
async function iniciarAtendimento(id) {
    const observacoes = document.getElementById('observacoes')?.value || '';

    try {
        const response = await fetch('api_atender.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&action=start&observacoes=${encodeURIComponent(observacoes)}`
        });
        const data = await response.json();

        if (data.sucesso) {
            alert('Atendimento iniciado!');
            location.reload();
        } else {
            alert('Erro: ' + (data.erro || data.mensagem));
        }
    } catch (e) {
        alert('Erro ao iniciar atendimento');
    }
}

function mostrarModalConcluir(id) {
    document.getElementById('concluirId').value = id;
    document.getElementById('modalConcluir').classList.add('ativo');
}

function fecharModal() {
    document.getElementById('modalConcluir').classList.remove('ativo');
}

document.getElementById('formConcluir').addEventListener('submit', async function(e) {
    e.preventDefault();

    const id = document.getElementById('concluirId').value;
    const resultado = document.getElementById('resultado').value;
    const observacoes = document.getElementById('observacoesFim').value;

    if (!resultado) {
        alert('Selecione um resultado');
        return;
    }

    try {
        const response = await fetch('api_atender.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&action=conclude&resultado=${resultado}&observacoes=${encodeURIComponent(observacoes)}`
        });
        const data = await response.json();

        if (data.sucesso) {
            alert('Atendimento concluído!');
            fecharModal();
            location.reload();
        } else {
            alert('Erro: ' + (data.erro || data.mensagem));
        }
    } catch (e) {
        alert('Erro ao concluir atendimento');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
