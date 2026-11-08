<?php

/*
 * Renewals & Expirations - domains, certificates, licenses and hardware
 * warranties falling due in the window ahead, and any that lapsed in the last
 * 90 days, soonest first: when, by month, by client and one by one. Domains
 * on auto-renew and short-lived certificates (which renew themselves) show in
 * blue and can be hidden, leaving what someone has to act on.
 */

require_once "includes/inc_report.php";
reportEnforce('renewals');

$windows = ['30' => __('Next 30 days'), '60' => __('Next 60 days'), '90' => __('Next 90 days'), '180' => __('Next 6 months'), '365' => __('Next 12 months')];
$ahead = isset($windows[$_GET['window'] ?? '']) ? intval($_GET['window']) : 90;
$kind = isset(REPORT_RENEWAL_KINDS[$_GET['kind'] ?? '']) ? $_GET['kind'] : '';
$manual_only = !empty($_GET['manual']);
$client_id = reportClientId();

$all = reportRenewals($client_id, $ahead, $kind ? [$kind] : []);
$items = $manual_only ? array_values(array_filter($all, function ($r) { return !$r['auto']; })) : $all;

$lapsed = array_filter($items, function ($r) { return $r['days'] < 0; });
$soon = array_filter($items, function ($r) { return $r['days'] >= 0 && $r['days'] <= 30; });
$auto = array_filter($all, function ($r) { return $r['auto'] && $r['days'] >= 0; });
$ahead_items = array_filter($items, function ($r) { return $r['days'] >= 0; });

// By client
$kind_keys = $kind ? [$kind] : array_keys(REPORT_RENEWAL_KINDS);
$by_client = [];
foreach ($items as $item) {
    $c = $item['client_id'];
    $by_client[$c] = $by_client[$c] ?? ['client_id' => $c, 'client' => $item['client'], 'abbr' => $item['abbr'], 'lapsed' => 0, 'soon' => 0, 'total' => 0] + array_fill_keys($kind_keys, 0);
    $by_client[$c][$item['kind']]++;
    $by_client[$c]['total']++;
    $by_client[$c]['lapsed'] += $item['days'] < 0 ? 1 : 0;
    $by_client[$c]['soon'] += $item['days'] >= 0 && $item['days'] <= 30 ? 1 : 0;
}
usort($by_client, function ($a, $b) {
    return $b['lapsed'] <=> $a['lapsed'] ?: $b['soon'] <=> $a['soon'] ?: $b['total'] <=> $a['total'];
});

$count_cell = function ($key, $tone = '') {
    return function ($row) use ($key, $tone) {
        return $row[$key] ? '<span class="' . $tone . '">' . $row[$key] . '</span>' : '<span class="itflow-report-zero">–</span>';
    };
};
$client_columns = ['client' => ['label' => __('Client'), 'html' => function ($row) {
    return reportClientCell($row['client_id'], $row['client'], $row['abbr']);
}]];
foreach ($kind_keys as $key) {
    $client_columns[$key] = ['label' => __(REPORT_RENEWAL_KINDS[$key]['label']), 'type' => 'count', 'html' => $count_cell($key)]; // i18n-dynamic - REPORT_RENEWAL_KINDS
}
$client_columns += [
    'lapsed' => ['label' => __('Lapsed'), 'type' => 'count', 'html' => $count_cell('lapsed', 'text-danger-emphasis fw-semibold')],
    'soon' => ['label' => __('Next 30 days'), 'type' => 'count', 'html' => $count_cell('soon', 'text-warning-emphasis fw-semibold')],
    'total' => ['label' => __('Total'), 'type' => 'count', 'class' => 'fw-semibold'],
];

$item_columns = [
    'days' => ['label' => __('Due'), 'type' => 'count', 'csv_label' => __('Days until due'), 'html' => function ($row) {
        return reportDuePill($row['days'], $row['auto']);
    }],
    'name' => ['label' => __('What'), 'class' => 'itflow-report-text', 'html' => function ($row) {
        return '<div class="d-flex align-items-center gap-2">' . $row['tile'] . '<div class="min-w-0"><a class="fw-semibold text-body" href="' . escapeHtml($row['url']) . '">' . escapeHtml($row['name']) . '</a>'
            . ($row['detail'] !== '' ? '<div class="small text-body-secondary">' . escapeHtml($row['detail']) . '</div>' : '') . '</div></div>';
    }],
    'kind' => ['label' => __('Type'), 'csv' => function ($row) { return REPORT_RENEWAL_KINDS[$row['kind']]['one']; }, 'html' => function ($row) {
        return '<span class="text-nowrap">' . __(REPORT_RENEWAL_KINDS[$row['kind']]['one']) . '</span>'; // i18n-dynamic - REPORT_RENEWAL_KINDS
    }],
    'detail' => ['label' => __('Detail'), 'screen' => false],
    'client' => ['label' => __('Client'), 'html' => function ($row) {
        return reportClientCell($row['client_id'], $row['client'], $row['abbr']);
    }],
    'date' => ['label' => __('Date'), 'type' => 'date'],
    'auto' => ['label' => __('Renews itself'), 'screen' => false, 'csv' => function ($row) { return $row['auto'] ? 'Yes' : 'No'; }],
];

if (reportExporting('clients')) {
    reportCsv('Renewals by Client', $client_columns, $by_client);
}
if (reportExporting()) {
    reportCsv('Renewals', $item_columns, $items);
}

require_once "includes/inc_report_layout.php";

// By month: lapsed first, then each month from this one to the end of the window
$months = ['lapsed' => 'Lapsed'];
$cursor = new DateTime('first day of this month'); // i18n-ignore - a date expression
$last = (new DateTime('today'))->modify("+$ahead days");
while ($cursor <= $last) {
    $months[$cursor->format('Y-m')] = $cursor->format('M y');
    $cursor->modify('+1 month');
}
$series = [];
foreach ($kind_keys as $key) {
    $counts = array_fill_keys(array_keys($months), 0);
    foreach ($items as $item) {
        if ($item['kind'] === $key) {
            $counts[$item['days'] < 0 ? 'lapsed' : substr($item['date'], 0, 7)]++;
        }
    }
    $series[] = ['label' => __(REPORT_RENEWAL_KINDS[$key]['label']), 'data' => array_values($counts), 'tone' => REPORT_RENEWAL_KINDS[$key]['tone'], 'stack' => 'due']; // i18n-dynamic - REPORT_RENEWAL_KINDS
}
$clients_affected = count(array_unique(array_column($items, 'client_id')));

$kind_choices = ['' => __('Everything')];
foreach (REPORT_RENEWAL_KINDS as $key => $meta) {
    $kind_choices[$key] = __($meta['label']); // i18n-dynamic - REPORT_RENEWAL_KINDS
}

?>

<div class="itflow-report">

    <?php reportHeader('renewals', [
        'subtitle' => $windows[$ahead],
        'controls' => reportSelectControl('window', $ahead, $windows, 'fa-calendar-alt')
            . reportClientControl($client_id)
            . reportSelectControl('kind', $kind, $kind_choices, 'fa-filter')
            . reportSwitchControl('manual', $manual_only, __('Hide ones that renew themselves')),
        'export' => ['items' => __('Every renewal'), 'clients' => __('By client')],
    ]); ?>

    <?= reportStats([
        ['label' => __('Lapsed'), 'value' => reportCount(count($lapsed)), 'tone' => $lapsed ? 'danger' : 'success', 'sub' => '<span class="text-body-tertiary">' . __('In the last %d days', REPORT_RENEWAL_LAPSED_DAYS) . '</span>'],
        ['label' => __('Due in 30 days'), 'value' => reportCount(count($soon)), 'tone' => $soon ? 'warning' : '',
            'sub' => reportCount(count(array_filter($soon, function ($r) { return $r['days'] <= 7; }))) . ' <span class="text-body-tertiary">' . __('this week') . '</span>'],
        ['label' => __('Due in the window'), 'value' => reportCount(count($ahead_items)), 'sub' => reportCount($clients_affected) . ' <span class="text-body-tertiary">' . _n('client', 'clients', $clients_affected) . '</span>'],
        ['label' => __('Renew themselves'), 'value' => reportCount(count($auto)), 'tone' => $auto ? 'info' : '',
            'title' => __('Domains on auto-renew and short-lived certificates'), 'sub' => '<span class="text-body-tertiary">' . ($manual_only ? __('Hidden below') : __('Auto-renew and short-lived')) . '</span>'],
    ]) ?>

    <?php if (!$items) { ?>
        <div class="card"><div class="card-body"><?= reportEmpty(__('Nothing comes up for renewal in this window, and nothing has lapsed lately.')) ?></div></div>
    <?php } else { ?>

        <?= reportSectionOpen(__('When they fall due'), __('By month')) ?>
            <div class="card-body">
                <?= reportChart(['type' => 'bar', 'labels' => array_values($months), 'series' => $series, 'stacked' => true, 'format' => 'count', 'legend' => count($series) > 1],
                    '', __('Renewals falling due by month')) ?>
            </div>
        </div>

        <?php if (!$client_id && count($by_client) > 1) { ?>
            <?= reportSectionOpen(__('By client'), __('The most lapsed and soonest first')) ?>
                <?= reportTable($client_columns, $by_client, ['limit' => 50, 'export_table' => 'clients']) ?>
            </div>
        <?php } ?>

        <?= reportSectionOpen(__('Every renewal'), __('Soonest first'), 'itflow-report-long') ?>
            <?= reportTable($item_columns, $items, ['limit' => 300, 'export_table' => 'items']) ?>
            <div class="card-footer small text-body-secondary">
                <?= __('Domains, certificates and licenses by their expiry date, hardware by its warranty end. Lapsed shows the last %d days; older ones are on each list\'s Expired view. Archived items, archived clients and assets that are lost, stolen or retired are left out.', REPORT_RENEWAL_LAPSED_DAYS) ?>
                <?= __('Blue ones renew themselves: domains set to auto-renew and short-lived certificates.') ?>
            </div>
        </div>

    <?php } ?>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
