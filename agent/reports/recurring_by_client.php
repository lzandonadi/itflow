<?php

/*
 * Recurring Revenue - what active recurring invoices bring in, as monthly
 * recurring revenue (a yearly invoice counts a twelfth a month) and a year
 * of it: per client, per income category, and every recurring invoice with
 * when it next bills. With financial access, recurring expenses come off it
 * for what is left each month. The same figure as the dashboard's
 * Recurring revenue.
 */

require_once "includes/inc_report.php";
reportEnforce('recurring_by_client');

$client_id = reportClientId();
$can_see_costs = reportPermission('module_financial') >= 1;

$invoices = [];
$sql = mysqli_query($mysqli, "SELECT recurring_invoice_id, recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_scope, recurring_invoice_frequency,
        recurring_invoice_next_date, recurring_invoice_amount, recurring_invoice_auto_send, recurring_invoice_created_at,
        recurring_invoice_client_id, client_name, client_abbreviation, category_name, category_id
    FROM recurring_invoices
    JOIN clients ON recurring_invoice_client_id = client_id
    LEFT JOIN categories ON recurring_invoice_category_id = category_id
    WHERE recurring_invoice_status = 1 AND recurring_invoice_archived_at IS NULL
    " . reportClientSql('recurring_invoice_client_id', $client_id) . "
    ORDER BY client_name ASC, recurring_invoice_next_date ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $amount = floatval($row['recurring_invoice_amount']);
    $row['monthly'] = recurringInvoiceMonthly($amount, $row['recurring_invoice_frequency']);
    $invoices[] = $row;
}

$clients = [];
$categories = [];
foreach ($invoices as $invoice) {
    $id = intval($invoice['recurring_invoice_client_id']);
    $clients[$id] = $clients[$id] ?? ['client_id' => $id, 'client' => $invoice['client_name'], 'abbr' => $invoice['client_abbreviation'],
        'count' => 0, 'mrr' => 0.0, 'next' => null, 'since' => null];
    $clients[$id]['count']++;
    $clients[$id]['mrr'] += $invoice['monthly'];
    $clients[$id]['next'] = $clients[$id]['next'] === null ? $invoice['recurring_invoice_next_date'] : min($clients[$id]['next'], $invoice['recurring_invoice_next_date']);
    $since = substr($invoice['recurring_invoice_created_at'], 0, 10);
    $clients[$id]['since'] = $clients[$id]['since'] === null ? $since : min($clients[$id]['since'], $since);
    $cat = $invoice['category_name'] ?? __('No category');
    $categories[$cat] = ($categories[$cat] ?? 0) + $invoice['monthly'];
}
$mrr = array_sum(array_column($clients, 'mrr'));
foreach ($clients as &$row) {
    $row['arr'] = $row['mrr'] * 12;
    $row['share'] = reportShare($row['mrr'], $mrr, 1);
}
unset($row);
uasort($clients, function ($a, $b) { return $b['mrr'] <=> $a['mrr']; });
$clients = array_values($clients);

$client_columns = [
    'client' => ['label' => __('Client'), 'html' => function ($row) {
        return reportClientCell($row['client_id'], $row['client'], $row['abbr'], 'recurring_invoices.php');
    }],
    'count' => ['label' => __('Recurring invoices'), 'type' => 'count'],
    'mrr' => ['label' => __('Per month'), 'type' => 'money', 'class' => 'fw-semibold'],
    'arr' => ['label' => __('Per year'), 'type' => 'money', 'class' => 'text-body-secondary'],
    'share' => ['label' => __('Share'), 'type' => 'percent', 'html' => function ($row) {
        return '<div class="d-flex align-items-center justify-content-end gap-2"><div class="itflow-report-meter d-none d-lg-flex" style="width: 5rem"><span style="width: ' . min(100, floatval($row['share'])) . '%"></span></div>'
            . '<span class="small text-body-secondary">' . reportPercent($row['share']) . '</span></div>';
    }],
    'next' => ['label' => __('Next bills'), 'type' => 'date', 'class' => 'text-nowrap'],
    'since' => ['label' => __('Recurring since'), 'type' => 'date', 'class' => 'text-nowrap text-body-secondary'],
];
$invoice_columns = [
    'number' => ['label' => __('Recurring invoice'), 'csv' => function ($row) { return $row['recurring_invoice_prefix'] . $row['recurring_invoice_number']; }, 'html' => function ($row) {
        return '<a class="font-monospace" href="/agent/recurring_invoice.php?client_id=' . intval($row['recurring_invoice_client_id']) . '&recurring_invoice_id=' . intval($row['recurring_invoice_id']) . '">' . escapeHtml($row['recurring_invoice_prefix'] . $row['recurring_invoice_number']) . '</a>';
    }],
    'client_name' => ['label' => __('Client'), 'html' => function ($row) {
        return reportClientCell($row['recurring_invoice_client_id'], $row['client_name'], $row['client_abbreviation'], 'recurring_invoices.php');
    }],
    'recurring_invoice_scope' => ['label' => __('Scope'), 'class' => 'small itflow-report-text'],
    'category_name' => ['label' => __('Category'), 'class' => 'small text-body-secondary'],
    'recurring_invoice_frequency' => ['label' => __('Every'), 'csv' => function ($row) { return ucfirst(recurringInvoicePeriodWord($row['recurring_invoice_frequency'])); }, 'html' => function ($row) {
        return ucfirst(recurringInvoicePeriodWord($row['recurring_invoice_frequency']));
    }],
    'recurring_invoice_amount' => ['label' => __('Amount'), 'type' => 'money'],
    'monthly' => ['label' => __('Per month'), 'type' => 'money', 'class' => 'fw-semibold'],
    'recurring_invoice_next_date' => ['label' => __('Next bills'), 'type' => 'date', 'class' => 'text-nowrap'],
    'auto' => ['label' => __('Sends'), 'csv' => function ($row) { return intval($row['recurring_invoice_auto_send']) ? __('Automatically') : __('Draft for review'); }, 'html' => function ($row) {
        return intval($row['recurring_invoice_auto_send']) ? '<span class="small text-body-secondary">' . __('Automatically') . '</span>' : '<span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal">' . __('Draft first') . '</span>';
    }],
];

if (reportExporting('invoices')) {
    reportCsv('Recurring Invoices', $invoice_columns, $invoices, ['number' => __('Total'), 'monthly' => $mrr]);
}
if (reportExporting()) {
    reportCsv('Recurring Revenue by Client', $client_columns, $clients, ['client' => __('Total'), 'count' => count($invoices), 'mrr' => $mrr, 'arr' => $mrr * 12]);
}

// What comes off it every month
$costs = 0.0;
if ($can_see_costs && !$client_id) {
    $costs = recurringExpenseMonthlyTotal();
}
// What bills in the next 30 days
$next_30 = 0.0;
$limit = date('Y-m-d', strtotime('+30 days'));
foreach ($invoices as $invoice) {
    if ($invoice['recurring_invoice_next_date'] <= $limit) {
        $next_30 += floatval($invoice['recurring_invoice_amount']);
    }
}

require_once "includes/inc_report_layout.php";

arsort($categories);
$cat_rows = reportTopN(array_map(function ($label, $value) { return ['label' => $label, 'value' => $value]; }, array_keys($categories), array_values($categories)), 6);
$cat_colors = array_map(function ($i) { return reportPalette($i); }, array_keys($cat_rows));
foreach ($cat_rows as $i => $row) {
    if ($row['label'] === __('Other')) {
        $cat_colors[$i] = 'faint';
    }
}

$stats = [
    ['label' => __('Recurring revenue'), 'value' => reportMoney($mrr), 'sub' => '<span class="text-body-tertiary">' . __('Per month') . '</span>', 'url' => '/agent/recurring_invoices.php'],
    ['label' => __('A year of it'), 'value' => reportMoney($mrr * 12), 'sub' => '<span class="text-body-tertiary">' . __('At today\'s recurring invoices') . '</span>'],
    ['label' => __('Recurring invoices'), 'value' => reportCount(count($invoices)), 'sub' => reportCount(count($clients)) . ' <span class="text-body-tertiary">' . _n('client, %s each a month', 'clients, %s each a month', count($clients), count($clients) ? escapeHtml(reportMoney($mrr / count($clients))) : '-') . '</span>'],
];
if ($can_see_costs && !$client_id) {
    $stats[] = ['label' => __('After recurring expenses'), 'value' => reportMoney($mrr - $costs), 'tone' => $mrr - $costs < 0 ? 'danger' : '',
        'sub' => escapeHtml(reportMoney($costs)) . ' <span class="text-body-tertiary">' . __('a month goes out') . '</span>', 'url' => '/agent/recurring_expenses.php'];
} else {
    $stats[] = ['label' => __('Bills in the next 30 days'), 'value' => reportMoney($next_30), 'sub' => '<span class="text-body-tertiary">' . __('From these recurring invoices') . '</span>'];
}

?>

<div class="itflow-report">

    <?php reportHeader('recurring_by_client', [
        'client' => $client_id,
        'subtitle' => __('As of %s', formatDate('now', 'date')),
        'export' => ['clients' => __('By client'), 'invoices' => __('Every recurring invoice')],
    ]); ?>

    <?= reportStats($stats) ?>

    <?php if ($invoices) { ?>
        <div class="row g-3">
            <div class="col-lg-4">
                <?= reportSectionOpen(__('By category'), __('Per month'), 'h-100 mb-0') ?>
                    <div class="card-body">
                        <?= reportChart([
                            'type' => 'doughnut',
                            'labels' => array_column($cat_rows, 'label'),
                            'series' => [['label' => __('Per month'), 'data' => array_map(function ($r) { return round($r['value'], 2); }, $cat_rows)]],
                            'colors' => $cat_colors,
                            'format' => 'money',
                        ], 'donut', __('Recurring revenue by category')) ?>
                        <div class="mt-3"><?= reportLegendHtml(array_column($cat_rows, 'label'), array_column($cat_rows, 'value'), $cat_colors) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <?php $top = array_slice($clients, 0, 12); ?>
                <?= reportSectionOpen(__('Biggest recurring clients'), __('Per month'), 'h-100 mb-0') ?>
                    <div class="card-body">
                        <?= reportChart([
                            'type' => 'bar',
                            'horizontal' => true,
                            'labels' => array_map(function ($c) { return $c['client']; }, $top),
                            'series' => [['label' => __('Per month'), 'data' => array_map(function ($c) { return round($c['mrr'], 2); }, $top), 'tone' => 'accent']],
                            'format' => 'money',
                            'legend' => false,
                        ], '', __('Recurring revenue per month for the biggest clients')) ?>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

    <?= reportSectionOpen(__('By client'), __('Most per month first')) ?>
        <?= reportTable($client_columns, $clients, [
            'empty' => __('No active recurring invoices.'),
            'totals' => ['client' => __('Total'), 'count' => count($invoices), 'mrr' => $mrr, 'arr' => $mrr * 12],
        ]) ?>
    </div>

    <?= reportSectionOpen(__('Every recurring invoice'), reportCount(count($invoices)) . ' active', 'itflow-report-long') ?>
        <?= reportTable($invoice_columns, $invoices, ['empty' => __('No active recurring invoices.'), 'limit' => 300, 'export_table' => 'invoices', 'totals' => ['number' => __('Total'), 'monthly' => $mrr]]) ?>
        <div class="card-footer small text-body-secondary">
            <?= __('Active recurring invoices only. A yearly invoice counts a twelfth of its amount each month.') ?>
        </div>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
