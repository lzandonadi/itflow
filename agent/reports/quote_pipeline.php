<?php

/*
 * Quotes Pipeline - what has been quoted in the period and how it went: won
 * (accepted or invoiced), lost (declined) or still open (sent or viewed),
 * with the win rate by number and by value. Below that, every quote still
 * waiting for an answer today - soonest to expire first - and how each
 * client's quotes went. Drafts have not gone out, so they count nowhere but
 * their own figure; archived quotes are left out. A quote's value is what it
 * bills up front and a year of its recurring lines (12 x quote_monthly_amount).
 */

require_once "includes/inc_report.php";
reportEnforce('quote_pipeline');

$period = reportPeriod('last_12');
$client_id = reportClientId();
$client_sql = reportClientSql('quote_client_id', $client_id);
$value_sql = '(quote_amount + 12 * quote_monthly_amount)';
$outcome_sql = "CASE WHEN quote_status IN ('Accepted', 'Invoiced') THEN 'won' WHEN quote_status = 'Declined' THEN 'lost'
    WHEN quote_status IN ('Sent', 'Viewed') THEN 'open' ELSE 'draft' END";

// In the period, by outcome
$by = ['won' => ['n' => 0, 'v' => 0.0], 'lost' => ['n' => 0, 'v' => 0.0], 'open' => ['n' => 0, 'v' => 0.0], 'draft' => ['n' => 0, 'v' => 0.0]];
$sql = mysqli_query($mysqli, "SELECT $outcome_sql AS outcome, COUNT(quote_id) AS n, SUM($value_sql) AS v FROM quotes
    WHERE quote_archived_at IS NULL AND " . reportRangeSql('quote_date', $period) . " $client_sql GROUP BY outcome");
while ($row = mysqli_fetch_assoc($sql)) {
    $by[$row['outcome']] = ['n' => intval($row['n']), 'v' => floatval($row['v'])];
}
$quoted_n = $by['won']['n'] + $by['lost']['n'] + $by['open']['n'];
$quoted_v = $by['won']['v'] + $by['lost']['v'] + $by['open']['v'];
$win_rate = reportShare($by['won']['n'], $by['won']['n'] + $by['lost']['n']);
$win_rate_value = reportShare($by['won']['v'], $by['won']['v'] + $by['lost']['v']);

$prev_won = dashboardSum("SELECT SUM($value_sql) FROM quotes WHERE quote_archived_at IS NULL AND quote_status IN ('Accepted', 'Invoiced') AND " . reportRangeSql('quote_date', $period, true) . " $client_sql");

// Open right now, whenever quoted
$open = [];
$sql = mysqli_query($mysqli, "SELECT quote_id, quote_prefix, quote_number, quote_scope, quote_status, quote_date, quote_expire, quote_amount, quote_monthly_amount, $value_sql AS value,
        quote_client_id, client_name, client_abbreviation, DATEDIFF(quote_expire, CURDATE()) AS days_left, DATEDIFF(CURDATE(), quote_date) AS age
    FROM quotes JOIN clients ON quote_client_id = client_id
    WHERE quote_archived_at IS NULL AND quote_status IN ('Sent', 'Viewed') $client_sql
    ORDER BY quote_expire IS NULL, quote_expire ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $open[] = $row;
}
$open_live = array_filter($open, function ($q) { return $q['quote_expire'] === null || intval($q['days_left']) >= 0; });
$open_expired = array_filter($open, function ($q) { return $q['quote_expire'] !== null && intval($q['days_left']) < 0; });
$expiring = array_filter($open_live, function ($q) { return $q['quote_expire'] !== null && intval($q['days_left']) <= 14; });
$open_monthly = (bool) array_filter($open, function ($q) { return floatval($q['quote_monthly_amount']) != 0; });

// By client, in the period
$clients = [];
$sql = mysqli_query($mysqli, "SELECT quote_client_id AS client_id, client_name, client_abbreviation, $outcome_sql AS outcome, COUNT(quote_id) AS n, SUM($value_sql) AS v
    FROM quotes JOIN clients ON quote_client_id = client_id
    WHERE quote_archived_at IS NULL AND quote_status <> 'Draft' AND " . reportRangeSql('quote_date', $period) . " $client_sql
    GROUP BY quote_client_id, client_name, client_abbreviation, outcome");
while ($row = mysqli_fetch_assoc($sql)) {
    $id = intval($row['client_id']);
    $clients[$id] = $clients[$id] ?? ['client_id' => $id, 'client' => $row['client_name'], 'abbr' => $row['client_abbreviation'], 'quotes' => 0, 'quoted' => 0.0,
        'won_n' => 0, 'won' => 0.0, 'lost_n' => 0, 'lost' => 0.0, 'open' => 0.0];
    $clients[$id]['quotes'] += intval($row['n']);
    $clients[$id]['quoted'] += floatval($row['v']);
    if ($row['outcome'] === 'won') {
        $clients[$id]['won_n'] += intval($row['n']);
        $clients[$id]['won'] += floatval($row['v']);
    } elseif ($row['outcome'] === 'lost') {
        $clients[$id]['lost_n'] += intval($row['n']);
        $clients[$id]['lost'] += floatval($row['v']);
    } else {
        $clients[$id]['open'] += floatval($row['v']);
    }
}
foreach ($clients as &$row) {
    $row['rate'] = reportShare($row['won_n'], $row['won_n'] + $row['lost_n']);
}
unset($row);
uasort($clients, function ($a, $b) { return $b['quoted'] <=> $a['quoted']; });
$clients = array_values($clients);

$quote_link = function ($row) {
    return '<a class="font-monospace" href="/agent/quote.php?client_id=' . intval($row['quote_client_id']) . '&quote_id=' . intval($row['quote_id']) . '">' . escapeHtml($row['quote_prefix'] . $row['quote_number']) . '</a>';
};
$open_columns = [
    'quote' => ['label' => __('Quote'), 'csv' => function ($row) { return $row['quote_prefix'] . $row['quote_number']; }, 'html' => $quote_link],
    'client_name' => ['label' => __('Client'), 'html' => function ($row) {
        return reportClientCell($row['quote_client_id'], $row['client_name'], $row['client_abbreviation'], 'quotes.php');
    }],
    'quote_scope' => ['label' => __('Scope'), 'class' => 'small itflow-report-text'],
    'quote_status' => ['label' => __('Status'), 'html' => function ($row) {
        return '<span class="badge rounded-pill ' . ($row['quote_status'] === 'Viewed' ? 'text-bg-info' : 'text-bg-secondary') . '">' . escapeHtml($row['quote_status']) . '</span>';
    }],
    'quote_date' => ['label' => __('Quoted'), 'type' => 'date', 'class' => 'text-nowrap'],
    'quote_expire' => ['label' => __('Expires'), 'type' => 'date', 'class' => 'text-nowrap', 'html' => function ($row) {
        if ($row['quote_expire'] === null) {
            return '<span class="text-body-secondary">' . __('No date') . '</span>';
        }
        $left = intval($row['days_left']);
        $date = escapeHtml(formatDate($row['quote_expire'], 'date'));
        if ($left < 0) {
            return '<span class="badge rounded-pill bg-danger-subtle text-danger-emphasis border border-danger-subtle fw-normal" title="' . $date . '">' . _n('Expired %d day ago', 'Expired %d days ago', -$left) . '</span>';
        }
        if ($left <= 14) {
            return '<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-normal" title="' . $date . '">' . ($left === 0 ? __('Today') : _n('In %d day', 'In %d days', $left)) . '</span>';
        }
        return $date;
    }],
    'age' => ['label' => __('Waiting'), 'type' => 'count', 'csv_label' => __('Days waiting'), 'html' => function ($row) {
        return intval($row['age']) . ' day' . (intval($row['age']) === 1 ? '' : 's');
    }],
    // Up front and monthly are in the CSV; on screen, one column - what it comes to in its first year, broken down under it
    'quote_amount' => ['label' => __('Up front'), 'type' => 'money', 'screen' => false],
    'quote_monthly_amount' => ['label' => __('Monthly'), 'type' => 'money', 'screen' => false],
    'value' => ['label' => $open_monthly ? __('First year') : __('Amount'), 'csv_label' => __('First year'), 'type' => 'money', 'class' => 'fw-semibold',
        'title' => $open_monthly ? __('Up front, and a year of its recurring lines') : '', 'html' => function ($row) {
            $html = escapeHtml(reportMoney($row['value']));
            if (floatval($row['quote_monthly_amount']) != 0) {
                $html .= '<div class="small fw-normal text-body-secondary">' . escapeHtml(reportMoney($row['quote_amount'])) . ' + ' . escapeHtml(reportMoney($row['quote_monthly_amount'])) . '/mo</div>';
            }
            return $html;
        }],
];
$client_columns = [
    'client' => ['label' => __('Client'), 'html' => function ($row) {
        return reportClientCell($row['client_id'], $row['client'], $row['abbr'], 'quotes.php');
    }],
    'quotes' => ['label' => __('Quotes'), 'type' => 'count'],
    'quoted' => ['label' => __('Quoted'), 'type' => 'money'],
    'won' => ['label' => __('Won'), 'type' => 'money', 'class' => 'text-success-emphasis'],
    'lost' => ['label' => __('Lost'), 'type' => 'money', 'class' => 'text-danger-emphasis'],
    'open' => ['label' => __('Still open'), 'type' => 'money', 'class' => 'text-body-secondary'],
    'rate' => ['label' => __('Win rate'), 'type' => 'percent', 'html' => function ($row) {
        return reportRateHtml($row['rate'], 60, 35);
    }],
];

if (reportExporting('clients')) {
    reportCsv('Quotes by Client ' . $period['start'] . ' to ' . $period['end'], $client_columns, $clients);
}
if (reportExporting()) {
    reportCsv('Open Quotes', $open_columns, $open, ['quote' => __('Total'), 'quote_amount' => array_sum(array_column($open, 'quote_amount')),
        'quote_monthly_amount' => array_sum(array_column($open, 'quote_monthly_amount')), 'value' => array_sum(array_column($open, 'value'))]);
}

$buckets = reportBuckets($period);
$series = [];
foreach (['won' => ['Won', 'success'], 'lost' => ['Lost', 'danger'], 'open' => [__('Still open'), 'info']] as $key => [$label, $tone]) {
    $series[] = ['label' => $label, 'tone' => $tone, 'stack' => 'q', 'data' => reportSeries("SELECT " . reportBucketSql('quote_date', $buckets['unit']) . " AS b, SUM($value_sql) AS v
        FROM quotes WHERE quote_archived_at IS NULL AND $outcome_sql = '$key' AND " . reportRangeSql('quote_date', $period) . " $client_sql GROUP BY b", $buckets['keys'])];
}

require_once "includes/inc_report_layout.php";

?>

<div class="itflow-report">

    <?php reportHeader('quote_pipeline', [
        'period' => $period,
        'years' => reportPastYears("SELECT DISTINCT YEAR(quote_date) AS y FROM quotes"),
        'client' => $client_id,
        'export' => ['open' => __('Open quotes'), 'clients' => __('By client')],
    ]); ?>

    <?= reportStats([
        ['label' => __('Quoted'), 'value' => reportMoney($quoted_v), 'sub' => reportCount($quoted_n) . ' <span class="text-body-tertiary">quote' . ($quoted_n === 1 ? '' : 's') . ' sent' . ($by['draft']['n'] ? ', ' . $by['draft']['n'] . ' still drafts' : '') . '</span>'],
        ['label' => __('Won'), 'value' => reportMoney($by['won']['v']), 'tone' => $by['won']['v'] > 0 ? 'success' : '', 'sub' => reportChangeHtml($by['won']['v'], $prev_won, 'up', $period['compare'])],
        ['label' => __('Win rate'), 'value' => reportPercent($win_rate), 'sub' => $win_rate_value !== null ? escapeHtml(reportPercent($win_rate_value)) . ' <span class="text-body-tertiary">' . __('by value, of quotes decided') . '</span>' : '<span class="text-body-tertiary">' . __('No quote decided yet') . '</span>'],
        ['label' => __('Open now'), 'value' => reportMoney(array_sum(array_column($open_live, 'value'))), 'url' => '/agent/quotes.php',
            'sub' => count($expiring) ? '<span class="text-warning-emphasis fw-semibold">' . count($expiring) . ' expire within 14 days</span>' : reportCount(count($open_live)) . ' <span class="text-body-tertiary">' . __('waiting for an answer') . '</span>'],
    ]) ?>

    <?= reportSectionOpen(__('Quoted, by how it went'), escapeHtml($period['range'])) ?>
        <div class="card-body">
            <?= reportChart(['type' => 'bar', 'labels' => $buckets['labels'], 'series' => $series, 'stacked' => true, 'format' => 'money'], '', __('Value quoted over the period, won, lost and still open')) ?>
        </div>
    </div>

    <?= reportSectionOpen(__('Waiting for an answer'), count($open_expired) ? '<span class="text-danger-emphasis">' . count($open_expired) . ' past their expiry date - follow up or close them</span>' : __('Soonest to expire first')) ?>
        <?= reportTable($open_columns, $open, [
            'empty' => reportEmpty(__('No quote is waiting for an answer.')),
            'limit' => 300, 'export_table' => 'open',
            'totals' => ['quote' => __('Total'), 'value' => array_sum(array_column($open, 'value'))],
        ]) ?>
    </div>

    <?= reportSectionOpen(__('By client'), escapeHtml($period['range'])) ?>
        <?= reportTable($client_columns, $clients, [
            'empty' => __('No quotes went out in this period.'),
            'totals' => ['client' => __('Total'), 'quotes' => $quoted_n, 'quoted' => $quoted_v, 'won' => $by['won']['v'], 'lost' => $by['lost']['v'], 'open' => $by['open']['v'], 'rate' => $win_rate],
            'totals_html' => ['rate' => reportRateHtml($win_rate, 60, 35)],
        ]) ?>
        <div class="card-footer small text-body-secondary">
            <?= __('By quote date. Won is accepted or invoiced, lost is declined, open is sent or viewed and not yet answered. The win rate counts only quotes that have been decided.') ?>
        </div>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
