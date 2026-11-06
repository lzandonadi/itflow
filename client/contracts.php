<?php
/*
 * Client Portal
 * Contracts - the agreements with us: the ones waiting for a signature, the
 * ones in force, or all of them, searchable, 20 a page, each opening here
 * to read, sign, or download as a PDF.
 *
 * SCOPING: every query is keyed to $session_client_id from the session
 * (portalContractScopeSql()), and a draft shows only once it is sent to sign.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalContractsGate();

$per_page = 20;
$tabs = [
    'waiting' => ['label' => __('To sign'), 'icon' => 'fa-file-signature', 'where' => portalContractWaitingSql()],
    'active' => ['label' => __('In force'), 'icon' => 'fa-check-circle', 'where' => "contract_status = 'Active'"],
    'all' => ['label' => _x('All', 'contracts view'), 'icon' => 'fa-list', 'where' => '1 = 1'],
];
$q = trim((string) ($_GET['q'] ?? ''));
$where = portalContractScopeSql();
if ($q !== '') {
    $q_sql = escapeSql($q);
    $where .= " AND contract_name LIKE '%$q_sql%'";
}

$count_sql = [];
foreach ($tabs as $key => $t) {
    $count_sql[] = "COALESCE(SUM({$t['where']}), 0) AS `$key`";
}
$counts = array_map('intval', mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " FROM contracts WHERE $where")));

// The tab with something to sign when there is one, else the ones in force, else all
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : ($counts['waiting'] ? 'waiting' : ($counts['active'] ? 'active' : 'all'));
$total = $counts[$tab];
$pages = max(1, (int) ceil($total / $per_page));
$page = min(max(1, intval($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * $per_page;

$contracts = mysqli_query($mysqli, "SELECT * FROM contracts WHERE $where AND {$tabs[$tab]['where']}
    ORDER BY contract_status = 'Active' DESC, contract_end_date IS NULL, contract_end_date ASC, contract_name ASC
    LIMIT $per_page OFFSET $offset");

/** This page's address with some of its settings changed. */
function contractsUrl(array $changes) {
    global $tab, $q;
    $params = array_merge(['tab' => $tab, 'q' => $q !== '' ? $q : null], $changes);
    $params = array_filter($params, function ($v) { return $v !== null && $v !== ''; });
    return 'contracts.php' . ($params ? '?' . http_build_query($params) : '');
}
?>

<div class="itflow-portal-page-head">
    <div>
        <h1><?= __('Contracts') ?></h1>
        <p><?= $counts['waiting'] ? escapeHtml(_n('%d waiting for your signature', '%d waiting for your signature', $counts['waiting'])) : __('Your agreements with us') ?></p>
    </div>
    <form method="get" class="d-flex" role="search">
        <input type="hidden" name="tab" value="<?= escapeHtml($tab) ?>">
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
            <input type="search" class="form-control" name="q" value="<?= escapeHtml($q) ?>" placeholder="<?= escapeHtml(__('Search contracts')) ?>" aria-label="<?= escapeHtml(__('Search contracts')) ?>" maxlength="200">
        </div>
    </form>
</div>

<nav class="itflow-portal-tabs" aria-label="<?= escapeHtml(__('Contracts')) ?>">
    <?php foreach ($tabs as $key => $t) { ?>
        <a class="<?= $key === $tab ? 'active' : '' ?><?= $key === 'waiting' && $counts['waiting'] ? ' itflow-portal-tab-alert' : '' ?>" href="<?= escapeHtml(contractsUrl(['tab' => $key, 'page' => null])) ?>">
            <i class="fas <?= $t['icon'] ?>"></i><?= escapeHtml($t['label']) ?>
            <span class="badge rounded-pill"><?= $counts[$key] ?></span>
        </a>
    <?php } ?>
</nav>

<section class="itflow-portal-panel">
    <?php if (!$total) { ?>
        <?php if ($q !== '') { ?>
            <?= emptyStateHtml(['thing' => __('contracts'), 'filtered' => true, 'match_text' => __('Nothing here has &ldquo;%s&rdquo; in its name.', escapeHtml($q)), 'clear_url' => contractsUrl(['q' => null, 'page' => null])]) ?>
        <?php } elseif ($tab === 'waiting') { ?>
            <?= emptyStateHtml(['title' => __('Nothing to sign'), 'text' => __('When we send you a contract to sign, it waits here.'), 'icon' => 'fa-check', 'filtered' => false]) ?>
        <?php } else { ?>
            <?= emptyStateHtml(['title' => __('No contracts here'), 'text' => '', 'icon' => 'fa-file-contract', 'filtered' => false]) ?>
        <?php } ?>
    <?php } else { ?>
        <ul class="itflow-portal-list">
            <?php while ($row = mysqli_fetch_assoc($contracts)) {
                $round = portalContractRound(intval($row['contract_id']));
                $state = portalContractState($row, $round);
                $hours_left = $row['contract_status'] === 'Active' ? (contractHoursLeftFor([intval($row['contract_id'])])[intval($row['contract_id'])] ?? null) : null; ?>
                <li>
                    <a class="itflow-portal-ticket" href="contract.php?id=<?= intval($row['contract_id']) ?>">
                        <span class="itflow-portal-ticket-main">
                            <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['contract_name']) ?><span class="fw-normal text-body-secondary"> · <?= escapeHtml(contractTypeLabel($row['contract_type'])) ?></span></span>
                            <span class="itflow-portal-ticket-meta">
                                <span><?= escapeHtml(contractTermLabel($row['contract_term_months'])) ?><?= $row['contract_start_date'] ? ' ' . escapeHtml(__('from %s', formatDate($row['contract_start_date'], 'date'))) : '' ?></span>
                                <?php if ($row['contract_end_date'] && in_array($row['contract_status'], ['Active', 'Draft'], true)) { ?>
                                    <span><?= escapeHtml(intval($row['contract_auto_renew']) ? _x('Renews %s', 'contract state', formatDate(date('Y-m-d', strtotime('+1 day', strtotime($row['contract_end_date']))), 'date')) : _x('Ends %s', 'contract state', formatDate($row['contract_end_date'], 'date'))) ?></span>
                                <?php } ?>
                                <?php if ($hours_left) { ?>
                                    <span><i class="fas fa-hourglass-half me-1"></i><?= escapeHtml(__('%s prepaid left', contractHoursText($hours_left['left']))) ?></span>
                                <?php } ?>
                            </span>
                        </span>
                        <span class="itflow-portal-ticket-side">
                            <?= portalStateBadge($state) ?>
                        </span>
                    </a>
                </li>
            <?php } ?>
        </ul>
        <?php if ($pages > 1) { ?>
            <div class="itflow-portal-pager">
                <span><?= escapeHtml(__('%1$d-%2$d of %3$d', $offset + 1, min($offset + $per_page, $total), $total)) ?></span>
                <div class="btn-group">
                    <a class="btn btn-sm btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= escapeHtml(contractsUrl(['page' => $page - 1 > 1 ? $page - 1 : null])) ?>" aria-label="<?= escapeHtml(__('Previous page')) ?>"><i class="fas fa-chevron-left"></i></a>
                    <a class="btn btn-sm btn-outline-secondary<?= $page >= $pages ? ' disabled' : '' ?>" href="<?= escapeHtml(contractsUrl(['page' => $page + 1])) ?>" aria-label="<?= escapeHtml(__('Next page')) ?>"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
</section>

<?php
require_once "includes/footer.php";
