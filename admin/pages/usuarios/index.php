<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
if ($_SESSION['usuario']['nivel_acesso'] !== 'admin') {
    header('Location: ' . BASE_URL . '/admin/inicio');
    exit;
}

require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

$tipo   = $_GET['tipo']    ?? null;   // 'admin' | 'jogador'
$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$entity = null;

// ── MODO EDIÇÃO ──────────────────────────────────────────────
if ($editId && $tipo === 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM admin_usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $entity = $stmt->fetch();
    if (!$entity) { header('Location: ' . BASE_URL . '/admin/usuarios'); exit; }
    $partes   = explode(' ', $entity['nome_completo'], 2);
    $entNome  = $partes[0];
    $entSobre = $partes[1] ?? '';
}

if ($editId && $tipo === 'jogador') {
    $stmt = $pdo->prepare("SELECT * FROM jogadores WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $entity = $stmt->fetch();
    if (!$entity) { header('Location: ' . BASE_URL . '/admin/usuarios'); exit; }
    $partes   = explode(' ', $entity['nome_completo'], 2);
    $entNome  = $partes[0];
    $entSobre = $partes[1] ?? '';
}

// ── MODO LISTA ───────────────────────────────────────────────
if (!$editId) {
    $admins   = $pdo->query("SELECT id, nome_completo, email, cpf, nivel_acesso, created_at FROM admin_usuarios ORDER BY nome_completo")->fetchAll();
    $jogadores = $pdo->query("SELECT id, nome_completo, email, cpf, telefone, favorito, created_at FROM jogadores ORDER BY nome_completo")->fetchAll();
}

$deleted = $_GET['deleted'] ?? null; // 'admin' | 'jogador'
$saved   = !empty($_GET['saved']);

function nivelBadge(string $nivel): string {
    $map = ['admin' => '#0B3C75', 'editor' => '#e67e22', 'leitor' => '#6c757d'];
    $bg  = $map[$nivel] ?? '#999';
    return '<span style="background:'.$bg.';color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;text-transform:uppercase;letter-spacing:.5px;">'.$nivel.'</span>';
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin - Administrar Usuários</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <?php if (!$editId): ?>
        <!-- ══ LISTA ══════════════════════════════════════════════ -->
        <section class="usuariosPage">
            <div class="row">
                <div class="col-md-12">
                    <h2>Administrar <span>Usuários</span></h2>
                    <p class="usuariosPage__sub">Clique em um usuário para visualizar e editar seus dados.</p>
                </div>
            </div>

            <?php if ($deleted === 'admin'): ?>
                <div class="usuariosPage__alert --success">Usuário do sistema excluído com sucesso.</div>
            <?php elseif ($deleted === 'jogador'): ?>
                <div class="usuariosPage__alert --success">Jogador excluído com sucesso.</div>
            <?php endif; ?>

            <!-- Usuários do sistema -->
            <div class="usuariosPage__secao">
                <h3>Usuários do <span>Sistema</span></h3>
                <p class="usuariosPage__secaoSub">Usuários com acesso ao painel administrativo.</p>
            </div>
            <?php if (empty($admins)): ?>
                <p class="usuariosPage__vazio">Nenhum usuário do sistema cadastrado.</p>
            <?php else: ?>
            <div class="usuariosTable">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nome Completo</th>
                            <th>E-mail</th>
                            <th>Nível</th>
                            <th>Cadastro</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $i => $u): ?>
                        <tr class="usuariosTable__row" data-href="<?= BASE_URL ?>/admin/usuarios?tipo=admin&id=<?= $u['id'] ?>">
                            <td data-label="#"><?= $i + 1 ?></td>
                            <td data-label="Nome" class="usuariosTable__nome"><?= htmlspecialchars($u['nome_completo']) ?></td>
                            <td data-label="E-mail"><?= htmlspecialchars($u['email']) ?></td>
                            <td data-label="Nível"><?= nivelBadge($u['nivel_acesso']) ?></td>
                            <td data-label="Cadastro"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                            <td><a href="<?= BASE_URL ?>/admin/usuarios?tipo=admin&id=<?= $u['id'] ?>" class="usuariosTable__btn">Editar</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Jogadores da plataforma -->
            <div class="usuariosPage__secao" style="margin-top:40px;">
                <h3>Jogadores da <span>Plataforma</span></h3>
                <p class="usuariosPage__secaoSub">Usuários cadastrados no site público que confirmam presença nos treinos.</p>
            </div>
            <?php if (empty($jogadores)): ?>
                <p class="usuariosPage__vazio">Nenhum jogador cadastrado na plataforma.</p>
            <?php else: ?>
            <div class="usuariosTable">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nome Completo</th>
                            <th>E-mail</th>
                            <th>CPF</th>
                            <th>Telefone</th>
                            <th>Cadastro</th>
                            <th title="Favorito — acesso automático mesmo no modo manual">&#9733;</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jogadores as $i => $j):
                            $d = preg_replace('/\D/', '', $j['cpf'] ?? '');
                            $cpfMask = strlen($d) === 11 ? substr($d,0,3).'.***.***.'.substr($d,9,2) : $j['cpf'];
                            $isFav = (int)($j['favorito'] ?? 0) === 1;
                        ?>
                        <tr class="usuariosTable__row" data-href="<?= BASE_URL ?>/admin/usuarios?tipo=jogador&id=<?= $j['id'] ?>">
                            <td data-label="#"><?= $i + 1 ?></td>
                            <td data-label="Nome" class="usuariosTable__nome"><?= htmlspecialchars($j['nome_completo']) ?></td>
                            <td data-label="E-mail"><?= htmlspecialchars($j['email']) ?></td>
                            <td data-label="CPF" style="font-family:monospace;"><?= htmlspecialchars($cpfMask) ?></td>
                            <td data-label="Telefone"><?= htmlspecialchars($j['telefone'] ?: '—') ?></td>
                            <td data-label="Cadastro"><?= date('d/m/Y', strtotime($j['created_at'])) ?></td>
                            <td data-label="Favorito">
                                <button class="usuariosTable__star <?= $isFav ? '--ativo' : '' ?>"
                                        data-id="<?= $j['id'] ?>"
                                        title="<?= $isFav ? 'Remover dos favoritos' : 'Marcar como favorito' ?>">
                                    <?= $isFav ? '&#9733;' : '&#9734;' ?>
                                </button>
                            </td>
                            <td><a href="<?= BASE_URL ?>/admin/usuarios?tipo=jogador&id=<?= $j['id'] ?>" class="usuariosTable__btn">Editar</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        </section>

        <?php elseif ($tipo === 'admin'): ?>
        <!-- ══ EDITAR USUÁRIO DO SISTEMA ══════════════════════════ -->
        <section class="usuariosPage">
            <div class="row">
                <div class="col-md-12">
                    <a href="<?= BASE_URL ?>/admin/usuarios" class="usuariosPage__back">&larr; Voltar para a lista</a>
                    <h2>Editar usuário <span><?= htmlspecialchars($entNome) ?></span></h2>
                </div>
            </div>

            <input type="hidden" id="entityId"   value="<?= $editId ?>">
            <input type="hidden" id="entityTipo" value="admin">

            <div class="formGroup">
                <div class="row">

                    <div class="col-md-12 formGroup__divisor">
                        <h3>Dados <span>pessoais</span></h3>
                    </div>

                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label>Nome</label>
                            <input class="input" type="text" id="entNome" value="<?= htmlspecialchars($entNome) ?>" placeholder="Primeiro nome" />
                            <span class="errorText">Digite um nome válido</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label>Sobrenome</label>
                            <input class="input" type="text" id="entSobre" value="<?= htmlspecialchars($entSobre) ?>" placeholder="Sobrenome" />
                            <span class="errorText">Digite um sobrenome válido</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label>CPF</label>
                            <input class="input" type="text" id="entCpf" value="<?= htmlspecialchars($entity['cpf'] ?? '') ?>" placeholder="___.___.___-__" />
                            <span class="errorText">CPF inválido</span>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>E-mail</label>
                            <input class="input" type="email" id="entEmail" value="<?= htmlspecialchars($entity['email']) ?>" />
                            <span class="errorText">E-mail inválido</span>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="formGroup__item">
                            <label>Nível de acesso</label>
                            <select class="input" id="entNivel">
                                <option value="admin"  <?= $entity['nivel_acesso'] === 'admin'  ? 'selected' : '' ?>>ADMIN</option>
                                <option value="editor" <?= $entity['nivel_acesso'] === 'editor' ? 'selected' : '' ?>>EDITOR</option>
                                <option value="leitor" <?= $entity['nivel_acesso'] === 'leitor' ? 'selected' : '' ?>>LEITOR</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-12 formGroup__divisor">
                        <h3>Redefinir <span>senha</span></h3>
                    </div>

                    <div class="col-md-12">
                        <p class="meusDados__senhaAviso">Preencha somente se desejar alterar a senha.</p>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>Nova senha</label>
                            <input class="input" type="password" id="entSenha" placeholder="Entre 6 e 20 caracteres" />
                            <span class="errorText">A senha deve ter entre 6 e 20 caracteres</span>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>Confirmar nova senha</label>
                            <input class="input" type="password" id="entSenhaConfirm" placeholder="Repita a nova senha" />
                            <span class="errorText">As senhas não são iguais</span>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div id="editFeedback" class="usuariosPage__feedback" style="display:none;"></div>
                    </div>

                    <div class="col-md-12 usuariosPage__actions">
                        <button class="btn btn--primary" id="btnSalvar">Salvar alterações</button>
                        <?php if ($entity['id'] !== $_SESSION['usuario']['id']): ?>
                        <button class="btn btn--danger" id="btnExcluir">Excluir usuário</button>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </section>

        <?php elseif ($tipo === 'jogador'): ?>
        <!-- ══ EDITAR JOGADOR ═════════════════════════════════════ -->
        <section class="usuariosPage">
            <div class="row">
                <div class="col-md-12">
                    <a href="<?= BASE_URL ?>/admin/usuarios" class="usuariosPage__back">&larr; Voltar para a lista</a>
                    <h2>Editar jogador <span><?= htmlspecialchars($entNome) ?></span></h2>
                </div>
            </div>

            <input type="hidden" id="entityId"   value="<?= $editId ?>">
            <input type="hidden" id="entityTipo" value="jogador">

            <div class="formGroup">
                <div class="row">

                    <div class="col-md-12 formGroup__divisor">
                        <h3>Dados <span>pessoais</span></h3>
                    </div>

                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label>Nome</label>
                            <input class="input" type="text" id="entNome" value="<?= htmlspecialchars($entNome) ?>" />
                            <span class="errorText">Digite um nome válido</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label>Sobrenome</label>
                            <input class="input" type="text" id="entSobre" value="<?= htmlspecialchars($entSobre) ?>" />
                            <span class="errorText">Digite um sobrenome válido</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label>CPF</label>
                            <input class="input" type="text" id="entCpf" value="<?= htmlspecialchars($entity['cpf'] ?? '') ?>" placeholder="___.___.___-__" />
                            <span class="errorText">CPF inválido</span>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="formGroup__item">
                            <label>E-mail</label>
                            <input class="input" type="email" id="entEmail" value="<?= htmlspecialchars($entity['email']) ?>" />
                            <span class="errorText">E-mail inválido</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label>Telefone</label>
                            <input class="input" type="text" id="entTelefone" value="<?= htmlspecialchars($entity['telefone'] ?? '') ?>" placeholder="(00) 00000-0000" />
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="formGroup__item">
                            <label>Data de nascimento</label>
                            <input class="input" type="date" id="entNascimento" value="<?= htmlspecialchars($entity['data_nascimento'] ?? '') ?>" />
                        </div>
                    </div>

                    <div class="col-md-12 formGroup__divisor">
                        <h3>Redefinir <span>senha</span></h3>
                    </div>

                    <div class="col-md-12">
                        <p class="meusDados__senhaAviso">Preencha somente se desejar alterar a senha do jogador.</p>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>Nova senha</label>
                            <input class="input" type="password" id="entSenha" placeholder="Entre 6 e 20 caracteres" />
                            <span class="errorText">A senha deve ter entre 6 e 20 caracteres</span>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>Confirmar nova senha</label>
                            <input class="input" type="password" id="entSenhaConfirm" placeholder="Repita a nova senha" />
                            <span class="errorText">As senhas não são iguais</span>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div id="editFeedback" class="usuariosPage__feedback" style="display:none;"></div>
                    </div>

                    <div class="col-md-12 usuariosPage__actions">
                        <button class="btn btn--primary" id="btnSalvar">Salvar alterações</button>
                        <button class="btn btn--danger" id="btnExcluir">Excluir jogador</button>
                    </div>

                </div>
            </div>
        </section>

        <?php endif; ?>

        <!-- MODAL CONFIRMAÇÃO DE EXCLUSÃO -->
        <?php if ($editId): ?>
        <div class="deleteModal" id="deleteModal">
            <div class="deleteModal__box">
                <h3 class="deleteModal__title">Excluir usuário</h3>
                <p class="deleteModal__text">
                    Tem certeza que deseja excluir <strong><?= htmlspecialchars($entity['nome_completo']) ?></strong>?<br>
                    Esta ação não pode ser desfeita.
                </p>
                <div class="deleteModal__actions">
                    <button class="btn btn--gray"   id="btnCancelarExclusao">Cancelar</button>
                    <button class="btn btn--danger" id="btnConfirmarExclusao">Confirmar exclusão</button>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
    var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
    var BASE_URL       = "<?= BASE_URL ?>";
</script>

<?php
$version = time();
echo '<script src="' . ADMIN_BASE_URL . '/pages/usuarios/usuarios.js?v' . $version . '"></script>';
?>

</body>
</html>
