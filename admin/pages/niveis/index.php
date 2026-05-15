<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

$jogadores = $pdo->query("
    SELECT id, nome_completo, COALESCE(nivel_jogo, 3) AS nivel_jogo
    FROM jogadores
    ORDER BY nome_completo ASC
")->fetchAll(PDO::FETCH_ASSOC);

$niveis = [
    1 => ['label' => 'Iniciante 1', 'color' => '#6c757d',  'desc' => 'Jogador iniciante que não sabe jogar'],
    2 => ['label' => 'Iniciante 2', 'color' => '#17a2b8',  'desc' => 'Jogador iniciante que tem alguma noção de jogo'],
    3 => ['label' => 'Médio',       'color' => '#28a745',  'desc' => 'Jogador que já sabe fazer o básico'],
    4 => ['label' => 'Avançado',    'color' => '#fd7e14',  'desc' => 'Faz bem o básico, recebe, ataca e saca forte'],
    5 => ['label' => 'Profissional','color' => '#ffc107',  'desc' => 'Jogador com nível quase profissional'],
];
?>
<!DOCTYPE html>
<html>
<head>
<title>OAB Santana Vôlei Clube - Admin - Nível dos Jogadores</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
<style>
.niveis-page h2 { font-size: 1.5rem; font-weight: 700; color: #0b3c75; margin-bottom: 4px; }
.niveis-page h2 span { color: #f5a623; }
.niveis-page__sub { color: #777; font-size: .9rem; margin-bottom: 24px; }

.niveis-ref { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 28px; }
.niveis-ref__card {
    flex: 1; min-width: 140px; border: 1px solid #e0e0e0; border-radius: 10px;
    padding: 16px 12px; text-align: center; background: #fff;
}
.niveis-ref__stars { font-size: 1.1rem; color: #f5a623; margin-bottom: 6px; }
.niveis-ref__stars .off { color: #ddd; }
.niveis-ref__badge {
    display: inline-block; font-size: 11px; font-weight: 700; padding: 3px 10px;
    border-radius: 20px; color: #fff; margin-bottom: 8px; text-transform: uppercase; letter-spacing: .5px;
}
.niveis-ref__desc { font-size: 12px; color: #666; line-height: 1.4; }

.niveis-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
.niveis-table th { background: #0b3c75; color: #fff; font-size: 12px; letter-spacing: .5px; text-transform: uppercase; padding: 12px 16px; text-align: left; }
.niveis-table td { padding: 12px 16px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
.niveis-table tr:last-child td { border-bottom: none; }
.niveis-table tr:hover td { background: #f8f9ff; }
.niveis-table__nome { font-weight: 600; color: #222; }

.stars-input { display: flex; gap: 4px; }
.stars-input__star {
    font-size: 1.4rem; cursor: pointer; color: #ddd; transition: color .15s; line-height: 1;
    user-select: none;
}
.stars-input__star.--ativo { color: #f5a623; }
.stars-input__star:hover { color: #f5a623; }

.nivel-badge {
    display: inline-block; font-size: 11px; font-weight: 700; padding: 3px 10px;
    border-radius: 20px; color: #fff; text-transform: uppercase; letter-spacing: .5px;
    margin-left: 10px; vertical-align: middle; min-width: 90px; text-align: center;
}
.saved-flash { font-size: 12px; color: #28a745; margin-left: 8px; opacity: 0; transition: opacity .3s; }
.saved-flash.--show { opacity: 1; }
</style>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="niveis-page">
            <div class="row">
                <div class="col-md-12">
                    <h2>Nível dos <span>Jogadores</span></h2>
                    <p class="niveis-page__sub">Defina o nível de cada jogador clicando nas estrelas. Usado para montar times equilibrados.</p>
                </div>
            </div>

            <!-- Legenda de níveis -->
            <div class="niveis-ref">
                <?php foreach ($niveis as $n => $info): ?>
                <div class="niveis-ref__card">
                    <div class="niveis-ref__stars">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <span class="<?= $s <= $n ? '' : 'off' ?>">&#9733;</span>
                        <?php endfor; ?>
                    </div>
                    <span class="niveis-ref__badge" style="background:<?= $info['color'] ?>"><?= $info['label'] ?></span>
                    <p class="niveis-ref__desc"><?= $info['desc'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Tabela de jogadores -->
            <div class="row">
                <div class="col-md-12">
                    <table class="niveis-table">
                        <thead>
                            <tr>
                                <th>Jogador</th>
                                <th>Nível</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jogadores as $j):
                                $nv = (int)$j['nivel_jogo'];
                                $info = $niveis[$nv] ?? $niveis[3];
                            ?>
                            <tr data-id="<?= $j['id'] ?>">
                                <td class="niveis-table__nome"><?= htmlspecialchars($j['nome_completo']) ?></td>
                                <td>
                                    <div class="stars-input" data-id="<?= $j['id'] ?>" data-current="<?= $nv ?>">
                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <span class="stars-input__star <?= $s <= $nv ? '--ativo' : '' ?>"
                                              data-valor="<?= $s ?>">&#9733;</span>
                                        <?php endfor; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="nivel-badge js-badge-<?= $j['id'] ?>"
                                          style="background:<?= $info['color'] ?>">
                                        <?= $info['label'] ?>
                                    </span>
                                    <span class="saved-flash js-flash-<?= $j['id'] ?>">&#10003; Salvo</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>
<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";

var niveisInfo = <?= json_encode($niveis) ?>;

document.querySelectorAll('.stars-input').forEach(function(group) {
    var stars = group.querySelectorAll('.stars-input__star');
    var id    = group.dataset.id;

    stars.forEach(function(star) {
        star.addEventListener('mouseenter', function() {
            var val = parseInt(this.dataset.valor);
            stars.forEach(function(s) {
                s.classList.toggle('--ativo', parseInt(s.dataset.valor) <= val);
            });
        });

        star.addEventListener('mouseleave', function() {
            var current = parseInt(group.dataset.current);
            stars.forEach(function(s) {
                s.classList.toggle('--ativo', parseInt(s.dataset.valor) <= current);
            });
        });

        star.addEventListener('click', function() {
            var val = parseInt(this.dataset.valor);
            group.dataset.current = val;

            stars.forEach(function(s) {
                s.classList.toggle('--ativo', parseInt(s.dataset.valor) <= val);
            });

            // Atualiza badge
            var info  = niveisInfo[val];
            var badge = document.querySelector('.js-badge-' + id);
            var flash = document.querySelector('.js-flash-' + id);
            if (badge && info) {
                badge.textContent = info.label;
                badge.style.background = info.color;
            }

            // Salva via AJAX
            $.post(ADMIN_BASE_URL + '/services/salvar_nivel.php', {
                jogador_id: id,
                nivel: val
            }, function(res) {
                if (res.ok && flash) {
                    flash.classList.add('--show');
                    setTimeout(function() { flash.classList.remove('--show'); }, 2000);
                }
            });
        });
    });
});
</script>
</body>
</html>
