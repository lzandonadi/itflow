<?php

/*
 * Admin > Payment Providers > Saved Cards - every card a client has saved to
 * pay by (in the client portal, or added for them), with its provider: whose
 * it is, the card and when it runs out, the recurring invoices that autopay
 * with it and when each next does, and when it was saved. The tabs pick out
 * the cards on autopay and the ones that have run out - an expired card on
 * autopay is flagged, as its next autopay will be declined. Filtered by
 * provider (the Payment Providers list links here so) and by client. Remove
 * takes a card off at the provider and in ITFlow, its autopay with it
 * (paymentCardRemove(), functions/payment_provider.php,
 * admin/post/saved_payment_method.php).
 */

// Default Column Sortby Filter
$sort = "client_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$expiry_sql = paymentCardExpirySql();
$this_month = intval(date('Ym'));

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'client_name' => "client_name $order, saved_payment_id ASC",
    'saved_payment_description' => "saved_payment_description $order, client_name ASC",
    'expires' => "expires IS NULL, expires $order, client_name ASC",
    'autopay' => "autopay $order, client_name ASC",
    'saved_payment_created_at' => "saved_payment_created_at $order",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'client_name';
}

// '' all, 'autopay' the ones recurring invoices pay with, 'expired' the ones that have run out
$show = in_array($_GET['show'] ?? '', ['autopay', 'expired'], true) ? $_GET['show'] : '';
$provider_filter = intval($_GET['provider'] ?? 0);
$client_filter = intval($_GET['client'] ?? 0);

$autopay_sql = "(SELECT COUNT(*) FROM recurring_payments WHERE recurring_payment_saved_payment_id = saved_payment_id)";
$search = "(client_name LIKE '%$q%' OR saved_payment_description LIKE '%$q%' OR payment_provider_client LIKE '%$q%' OR saved_payment_provider_method LIKE '%$q%' OR payment_provider_name LIKE '%$q%')";
$filters = $search . ($provider_filter ? " AND saved_payment_provider_id = $provider_filter" : '') . ($client_filter ? " AND saved_payment_client_id = $client_filter" : '');
$show_where = [
    '' => '1 = 1',
    'autopay' => "$autopay_sql > 0",
    'expired' => "$expiry_sql < $this_month",
][$show];

$from = "FROM client_saved_payment_methods
    LEFT JOIN payment_providers ON payment_provider_id = saved_payment_provider_id
    LEFT JOIN clients ON client_id = saved_payment_client_id
    LEFT JOIN client_payment_provider ON client_payment_provider.client_id = saved_payment_client_id AND client_payment_provider.payment_provider_id = saved_payment_provider_id";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS saved_payment_id, saved_payment_client_id, saved_payment_provider_id, saved_payment_provider_method, saved_payment_description,
        saved_payment_created_at, payment_provider_name, payment_provider_active, client_name, client_archived_at, payment_provider_client,
        $expiry_sql AS expires, $autopay_sql AS autopay
    $from
    WHERE $filters AND $show_where
    ORDER BY {$sortable_columns[$sort]}
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$rows = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $rows[intval($row['saved_payment_id'])] = $row;
}
$autopay = paymentCardAutopay(array_keys($rows));

// How many in each tab, with the search and filters
$counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*), COALESCE(SUM($autopay_sql > 0), 0), COALESCE(SUM($expiry_sql < $this_month), 0),
        COALESCE(SUM($expiry_sql < $this_month AND $autopay_sql > 0), 0)
    $from WHERE $filters"));

// The providers and clients to filter by - those with cards
$providers = mysqli_query($mysqli, "SELECT payment_provider_id, payment_provider_name FROM payment_providers
    WHERE payment_provider_id IN (SELECT saved_payment_provider_id FROM client_saved_payment_methods) OR payment_provider_id = $provider_filter ORDER BY payment_provider_name ASC");
$clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients
    WHERE client_id IN (SELECT saved_payment_client_id FROM client_saved_payment_methods) OR client_id = $client_filter ORDER BY client_name ASC");
$provider_count = mysqli_num_rows($providers);

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$pill_url = function ($show) {
    return '?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'provider' => $_GET['provider'] ?? '', 'client' => $_GET['client'] ?? '', 'show' => $show]));
};
$csrf = $_SESSION['csrf_token'];
$page_title = __('Saved Cards');

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="payment_providers.php"><?= __('Payment Providers') ?></a></li>
    <li class="breadcrumb-item active"><?= __('Saved Cards') ?></li>
</ol>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-wallet me-2"></i><?= __('Saved Cards') ?></h3>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($show !== '') { ?><input type="hidden" name="show" value="<?= $show ?>"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="<?= escapeHtml(__('Search saved cards...')) ?>" title="<?= escapeHtml(__('Client, card, provider, or the provider\'s customer or payment method ID')) ?>">
                        <button class="btn btn-dark" title="<?= escapeHtml(__('Search')) ?>"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <?php if ($provider_count > 1 || $provider_filter) { ?>
                    <div class="col-md-3 col-lg-2">
                        <select class="form-select" name="provider" onchange="this.form.submit()" aria-label="<?= escapeHtml(__('Provider')) ?>">
                            <option value=""><?= __('All providers') ?></option>
                            <?php while ($provider = mysqli_fetch_assoc($providers)) { ?>
                                <option value="<?= intval($provider['payment_provider_id']) ?>" <?= intval($provider['payment_provider_id']) === $provider_filter ? 'selected' : '' ?>><?= escapeHtml($provider['payment_provider_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>
                <div class="col-md-3 col-lg-3">
                    <select class="form-select select2" name="client" onchange="this.form.submit()" aria-label="<?= escapeHtml(__('Client')) ?>">
                        <option value=""><?= __('All clients') ?></option>
                        <?php while ($client = mysqli_fetch_assoc($clients)) { ?>
                            <option value="<?= intval($client['client_id']) ?>" <?= intval($client['client_id']) === $client_filter ? 'selected' : '' ?>><?= escapeHtml($client['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs flex-nowrap overflow-auto mt-2">
            <a class="nav-link <?= $show === '' ? 'active' : '' ?>" href="<?= escapeHtml($pill_url('')) ?>"><i class="fas fa-fw fa-wallet me-1"></i><?= __('All') ?> <span class="badge rounded-pill ms-1"><?= intval($counts[0]) ?></span></a>
            <a class="nav-link <?= $show === 'autopay' ? 'active' : '' ?>" href="<?= escapeHtml($pill_url('autopay')) ?>" title="<?= escapeHtml(__('Cards recurring invoices pay with')) ?>"><i class="fas fa-fw fa-sync-alt me-1"></i><?= __('On autopay') ?> <span class="badge rounded-pill ms-1"><?= intval($counts[1]) ?></span></a>
            <a class="nav-link <?= $show === 'expired' ? 'active' : '' ?>" href="<?= escapeHtml($pill_url('expired')) ?>" title="<?= intval($counts[3]) ? escapeHtml(__('%d still on autopay - it will be declined', intval($counts[3]))) : __('Cards that have run out') ?>"><i class="fas fa-fw fa-calendar-times me-1"></i><?= __('Expired') ?> <span class="badge rounded-pill ms-1 <?= $show !== 'expired' && intval($counts[3]) ? 'text-bg-danger' : '' ?>"><?= intval($counts[2]) ?></span></a>
        </nav>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('client_name', 'Client') ?></th>
                <th><?= $sort_link('saved_payment_description', 'Card') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('expires', 'Expires') ?></th>
                <th class="d-none d-lg-table-cell"><?= $sort_link('autopay', 'Autopay') ?></th>
                <th class="d-none d-lg-table-cell"><?= $sort_link('saved_payment_created_at', 'Saved') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $saved_id => $row) {
                $card = billingCardParts($row['saved_payment_description']);
                $expires = $row['expires'] !== null ? intval($row['expires']) : 0;
                $expired = $expires && $expires < $this_month;
                $expiring = $expires && !$expired && $expires <= intval(date('Ym', strtotime('first day of next month')));
                $uses = $autopay[$saved_id] ?? [];
                $client_id = intval($row['saved_payment_client_id']);
                $label = $card['label'] . ($card['exp'] !== '' ? ' (exp ' . $card['exp'] . ')' : '');
                $confirm = $row['payment_provider_name'] ? __('It is taken off %s too.', $row['payment_provider_name']) : __('It is taken off its provider too.');
                if ($uses) {
                    $confirm .= ' ' . __('Autopay on %s stops - each says so in its history.', implode(', ', array_column($uses, 'reference')));
                }
                ?>
                <tr data-saved-payment-id="<?= $saved_id ?>">
                    <td class="ps-3">
                        <?php if ($row['client_name'] !== null) { ?>
                            <a class="fw-semibold text-body" href="/agent/client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($row['client_name']) ?></a>
                            <?php if ($row['client_archived_at']) { ?><span class="badge text-bg-secondary ms-1"><?= __('Archived') ?></span><?php } ?>
                        <?php } else { ?>
                            <span class="text-body-secondary"><?= __('Client #%d', $client_id) ?></span>
                        <?php } ?>
                        <?php if (trim((string) $row['payment_provider_client']) !== '') { ?>
                            <div class="small text-body-secondary font-monospace" title="Its customer at <?= escapeHtml($row['payment_provider_name']) ?>"><?= escapeHtml($row['payment_provider_client']) ?></div>
                        <?php } ?>
                    </td>
                    <td>
                        <div class="text-nowrap"><i class="<?= escapeHtml($card['icon']) ?> fa-fw fa-lg text-secondary me-1"></i><span class="fw-semibold"><?= escapeHtml($card['label']) ?></span></div>
                        <div class="small text-body-secondary">
                            <?= escapeHtml($row['payment_provider_name'] ?? '') ?><?= intval($row['payment_provider_active'] ?? 0) ? '' : ' <span class="badge text-bg-secondary">' . __('Off') . '</span>' ?>
                            <span class="font-monospace ms-1" title="<?= escapeHtml(__('Its payment method at the provider')) ?>"><?= escapeHtml($row['saved_payment_provider_method']) ?></span>
                        </div>
                        <!-- On smaller screens when it runs out comes along under it -->
                        <?php if ($card['exp'] !== '') { ?><div class="d-md-none small <?= $expired ? 'text-danger-emphasis' : 'text-body-secondary' ?>"><?= __('Exp %s', escapeHtml($card['exp'])) ?><?= $expired ? __(' - expired') : '' ?></div><?php } ?>
                        <?php if ($uses) { ?>
                            <div class="d-lg-none small <?= $expired ? 'text-danger-emphasis' : 'text-body-secondary' ?>"><i class="fas fa-fw fa-redo-alt me-1"></i><?= __('Autopay on %s', escapeHtml(implode(', ', array_column($uses, 'reference')))) ?><?= $expired ? __(' - it will be declined') : '' ?></div>
                        <?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell small text-nowrap" data-card-expires="<?= $expired ? 'expired' : ($expiring ? 'soon' : 'ok') ?>">
                        <?php if ($card['exp'] === '') { ?>
                            <span class="text-body-secondary">-</span>
                        <?php } else { ?>
                            <?= escapeHtml($card['exp']) ?>
                            <?php if ($expired) { ?>
                                <span class="badge text-bg-danger ms-1"><?= __('Expired') ?></span>
                            <?php } elseif ($expiring) { ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1"><?= __('Soon') ?></span>
                            <?php } ?>
                        <?php } ?>
                    </td>
                    <td class="d-none d-lg-table-cell small" data-card-autopay="<?= count($uses) ?>">
                        <?php if (!$uses) { ?>
                            <span class="text-body-secondary"><?= __('None') ?></span>
                        <?php } ?>
                        <?php foreach ($uses as $use) { ?>
                            <div class="text-nowrap">
                                <a class="text-reset" href="/agent/recurring_invoice.php?recurring_invoice_id=<?= $use['id'] ?>"><i class="fas fa-fw fa-redo-alt text-secondary me-1"></i><?= escapeHtml($use['reference']) ?></a>
                                <span class="text-body-secondary">· <?= escapeHtml(billingMoney($use['amount'], $use['currency'])) ?> <?= escapeHtml($use['frequency']) ?></span>
                                <?php if (!$use['active']) { ?>
                                    <span class="badge text-bg-secondary ms-1"><?= __('Not running') ?></span>
                                <?php } elseif ($expired) { ?>
                                    <div class="text-danger-emphasis"><i class="fas fa-fw fa-exclamation-triangle me-1"></i><?= __('Next on %s - it will be declined', escapeHtml(formatDate($use['next'], 'date_short'))) ?></div>
                                <?php } else { ?>
                                    <span class="text-body-secondary">· next <?= escapeHtml(date('M j', strtotime($use['next']))) ?></span>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </td>
                    <td class="d-none d-lg-table-cell small text-body-secondary text-nowrap" title="<?= escapeHtml($row['saved_payment_created_at']) ?>"><?= escapeHtml(date('M j, Y', strtotime($row['saved_payment_created_at']))) ?></td>
                    <td class="text-end pe-3">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="<?= escapeHtml(__('More actions')) ?>" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="?<?= escapeHtml(http_build_query(['client' => $client_id])) ?>">
                                    <i class="fas fa-fw fa-filter text-secondary me-2"></i><?= __('This client\'s cards') ?>
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_saved_payment=<?= $saved_id ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-title="<?= escapeHtml('Remove ' . $label . ($row['client_name'] !== null ? ' from ' . $row['client_name'] : '') . '?') ?>"
                                    data-confirm-text="<?= escapeHtml($confirm) ?>" data-confirm-button="<?= escapeHtml(__('Remove')) ?>">
                                    <i class="fas fa-fw fa-trash me-2"></i><?= __('Remove') ?>
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => __('saved cards'),
        'icon' => 'fa-wallet',
        'text' => __('The cards clients save to pay by - in the client portal, or added for them - show here, with the recurring invoices that autopay with each.'),
        'filtered' => $q !== '' || $provider_filter || $client_filter || $show !== '',
    ];
    ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php
require_once "../includes/footer.php";
