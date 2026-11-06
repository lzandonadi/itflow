<?php

/*
 * Invoice, quote and packing slip PDFs - loaded by billingPdf() once TCPDF
 * itself is, as a class has to follow the one it extends.
 *
 * One look for all of them, the same as the document on screen: a band of
 * the accent colour, the logo and who it is from, what it is and its
 * number and dates, who it is for and what it comes to, the lines, the
 * totals and notes, and the footer from the billing settings with the page
 * number. An invoice still owed carries a code to scan to open it online
 * (and pay, when online payments are on); a quote, one to answer it. Paid,
 * cancelled, accepted and declined ones are stamped.
 */

class BillingPdf extends TCPDF {

    public $footer_text = '';
    public $footer_label = '';

    public function Header() {
        // The accent band across the top of every page
        $this->SetFillColor(13, 110, 253);
        $this->Rect(0, 0, $this->getPageWidth(), 2.5, 'F');
    }

    public function Footer() {
        $margins = $this->getMargins();
        $width = $this->getPageWidth() - $margins['left'] - $margins['right'];
        $this->SetY(-14);
        $this->SetDrawColor(222, 226, 230);
        $this->SetLineWidth(0.2);
        $this->Line($margins['left'], $this->GetY() - 1.5, $this->getPageWidth() - $margins['right'], $this->GetY() - 1.5);
        $this->SetFont('dejavusans', '', 7);
        $this->SetTextColor(108, 117, 125);
        $this->Cell($width * 0.75, 4, $this->footer_label, 0, 0, 'L');
        $this->Cell($width * 0.25, 4, __('Page %1$s of %2$s', $this->getAliasNumPage(), $this->getAliasNbPages()), 0, 0, 'R');
    }
}

/** The footers and the tax ID setting - a page has them loaded, the nightly run reads them here. */
function billingPdfConfig() {
    global $mysqli, $config_invoice_footer, $config_quote_footer, $config_invoice_show_tax_id;
    if (isset($config_invoice_footer, $config_quote_footer, $config_invoice_show_tax_id)) {
        return;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_invoice_footer, config_quote_footer, config_invoice_show_tax_id FROM settings WHERE company_id = 1")) ?: [];
    $config_invoice_footer = $config_invoice_footer ?? (string) ($row['config_invoice_footer'] ?? '');
    $config_quote_footer = $config_quote_footer ?? (string) ($row['config_quote_footer'] ?? '');
    $config_invoice_show_tax_id = $config_invoice_show_tax_id ?? intval($row['config_invoice_show_tax_id'] ?? 0);
}

/**
 * Draw an invoice, quote or recurring invoice - or an invoice's packing slip
 * ($variant 'packing') - and return ['name' => file name, 'data' => the PDF].
 */
function billingPdfRender($type, array $row, $variant = 'document') {
    global $mysqli, $config_invoice_footer, $config_quote_footer, $config_invoice_show_tax_id;
    billingPdfConfig();

    $t = billingType($type);
    $c = $t['col'];
    $id = intval($row["{$c}_id"]);
    $company = billingCompany();
    $bill_to = billingBillTo($row['client_id']);
    $items = billingItems($type, $id);
    $totals = billingTotals($type, $row, $items);
    $currency = (string) $row["{$c}_currency_code"];
    $number = billingNumber($type, $row);
    $packing = $variant === 'packing';
    $e = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };
    $money = function ($amount) use ($currency, $e) {
        return $e(billingMoney($amount, $currency));
    };

    $status = $type === 'recurring' ? '' : (string) $row["{$c}_status"];
    // A quote with lines billed each month, quarter or year shows them apart from what is billed once
    $groups = $type === 'quote' && !empty($totals['recurring']) ? $totals['groups'] : null;
    $shown_groups = $groups ? array_filter($groups, function ($group) {
        return $group['frequency'] !== '' || $group['items'] || $group['discount'] != 0;
    }) : null;
    $paid = $type === 'invoice' ? invoicePaid($id) : 0;
    $state = $type === 'invoice' ? invoiceState($row, $paid) : null;

    // Letter for North America, A4 everywhere else
    $format = in_array($company['company_country'] ?? '', ['United States', 'USA', 'US', 'Canada'], true) ? 'LETTER' : 'A4';
    $pdf = new BillingPdf('P', 'mm', $format, true, 'UTF-8', false);
    // The file name stays English; the title on the page follows the interface language
    $title_en = $packing ? 'Packing Slip' : ['invoice' => 'Invoice', 'quote' => 'Quote', 'recurring' => 'Recurring Invoice'][$type]; // i18n-ignore - the file name
    $title = $packing ? _x('Packing Slip', 'billing document') : ['invoice' => __('Invoice'), 'quote' => __('Quote'), 'recurring' => _x('Recurring invoice', 'billing document')][$type];
    $date = function ($value) {
        return (string) $value !== '' ? formatDate($value, 'date') : '';
    };
    // How often a quote's lines or a recurring invoice are billed
    $frequency_labels = ['' => _x('One-time', 'billing frequency'), 'month' => _x('Each month', 'billing frequency'), 'quarter' => _x('Each quarter', 'billing frequency'), 'year' => _x('Each year', 'billing frequency')];
    $frequency_totals = ['' => _x('Total up front', 'billing document'), 'month' => _x('Total each month', 'billing document'), 'quarter' => _x('Total each quarter', 'billing document'), 'year' => _x('Total each year', 'billing document')];
    $pdf->SetCreator('ITFlow');
    $pdf->SetAuthor((string) ($company['company_name'] ?? ''));
    $pdf->SetTitle("$title $number");
    $pdf->SetMargins(16, 14, 16);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(12);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->setFontSubsetting(true);
    $pdf->setImageScale(1.5);
    $pdf->footer_label = trim(($company['company_name'] ?? '') . ' · ' . "$title $number", ' ·');
    $pdf->AddPage();
    $margins = $pdf->getMargins();
    $width = $pdf->getPageWidth() - $margins['left'] - $margins['right'];

    $grey = '#6c757d';
    $line = '#dee2e6';
    $label = function ($text) use ($grey, $e) {
        return '<span style="font-size:6.5pt; color:' . $grey . '; font-weight:bold;">' . $e(mb_strtoupper($text)) . '</span>';
    };

    // Who it is from, and what it is
    $from_lines = array_merge(array_filter([
        formatAddress($company['company_address'] ?? '', $company['company_city'] ?? '', $company['company_state'] ?? '', $company['company_zip'] ?? '', $company['company_country'] ?? '', "\n"),
        implode('  ·  ', array_filter([$company['company_email'] ?? '', $company['phone_display'] ?? ''])),
        $company['company_website'] ?? '',
        !empty($config_invoice_show_tax_id) && !empty($company['company_tax_id']) ? __('Tax ID: %s', $company['company_tax_id']) : '',
    ]), $packing ? [] : billingTaxRegistrationLines($totals));
    $logo = !empty($company['logo_path']) ? '<img src="' . $e($company['logo_path']) . '" height="42"><br>' : '';
    if ($packing) {
        $dates = [__('Invoice') => $number, __('Date') => $date($row['invoice_date'])];
    } elseif ($type === 'invoice') {
        $dates = [__('Invoice date') => $date($row['invoice_date']), _x('Due', 'billing document') => $date($row['invoice_due'])];
    } elseif ($type === 'quote') {
        $dates = [_x('Quote date', 'billing document') => $date($row['quote_date']), _x('Valid until', 'billing document') => $date($row['quote_expire'])];
    } else {
        $billed = ['month' => __('Monthly'), 'quarter' => _x('Quarterly', 'billing frequency'), 'year' => _x('Yearly', 'billing frequency')];
        $dates = [__('Billed') => $billed[$row['recurring_invoice_frequency']] ?? $row['recurring_invoice_frequency'], _x('Next invoice', 'billing document') => $date($row['recurring_invoice_next_date'])];
    }
    $dates_html = '';
    foreach ($dates as $date_label => $value) {
        $dates_html .= '<tr><td align="right" style="color:' . $grey . ';">' . $e($date_label) . '</td><td align="right" width="30mm">' . $e($value) . '</td></tr>';
    }

    $html = '<table cellpadding="0" cellspacing="0" width="100%"><tr>
        <td width="58%">' . $logo . '<span style="font-size:12pt; font-weight:bold;">' . $e($company['company_name'] ?? '') . '</span><br><span style="font-size:7.5pt; color:' . $grey . '; line-height:1.4;">' . nl2br($e(implode("\n", $from_lines))) . '</span></td>
        <td width="42%" align="right"><span style="font-size:8pt; color:#0a58ca; font-weight:bold; letter-spacing:2px;">' . $e(mb_strtoupper($title)) . '</span><br><span style="font-size:15pt; font-weight:bold;">' . $e($number) . '</span><br>
            <table cellpadding="1.2" cellspacing="0" width="100%" style="font-size:8pt;">' . $dates_html . '</table></td>
    </tr></table>';
    $pdf->SetFont('dejavusans', '', 8.5);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->writeHTML($html, true, false, true, false, '');

    $pdf->SetDrawColor(222, 226, 230);
    $pdf->SetLineWidth(0.2);
    $pdf->Ln(1);
    $pdf->Line($margins['left'], $pdf->GetY(), $margins['left'] + $width, $pdf->GetY());
    $pdf->Ln(4);

    // Who it is for, and what it comes to
    $to_lines = array_filter([
        formatAddress($bill_to['location_address'] ?? '', $bill_to['location_city'] ?? '', $bill_to['location_state'] ?? '', $bill_to['location_zip'] ?? '', $bill_to['location_country'] ?? '', "\n"),
        $bill_to['contact_name'] ?? '',
        implode('  ·  ', array_filter([$bill_to['contact_email'] ?? '', $bill_to['contact_phone'] ?? ''])),
        !empty($bill_to['client_tax_id_number']) ? __('Tax ID: %s', $bill_to['client_tax_id_number']) : '',
        !$packing && !empty($bill_to['tax_exempt_text']) && $totals['tax'] == 0 ? $bill_to['tax_exempt_text'] : '',
    ]);
    $to_label = $packing ? _x('Ship to', 'billing document') : ($type === 'quote' ? _x('Prepared for', 'billing document') : _x('Bill to', 'billing document'));
    if ($packing) {
        $amount_html = '';
    } elseif ($type === 'invoice') {
        $done = in_array($status, ['Paid', 'Cancelled', 'Non-Billable'], true);
        $amount_html = $label($done ? __('Balance') : _x('Amount due', 'billing document')) . '<br><span style="font-size:17pt; font-weight:bold;">' . $money($state['balance']) . '</span>'
            . (!$done && $status !== 'Draft' && $state['balance'] > 0 ? '<br><span style="font-size:7.5pt; color:' . $grey . ';">' . $e(_x('by %s', 'billing document due date', $date($row['invoice_due']))) . '</span>' : '');
    } elseif ($type === 'quote' && $shown_groups) {
        $amount_html = '';
        foreach (array_values($shown_groups) as $i => $group) {
            $amount_html .= ($i ? '<br>' : '') . $label($group['frequency'] === '' ? _x('Up front', 'billing document') : ($frequency_labels[$group['frequency']] ?? $group['label'])) . '<br><span style="font-size:' . ($i ? '12' : '17') . 'pt; font-weight:bold;">' . $money($group['total']) . '</span>';
        }
    } elseif ($type === 'quote') {
        $amount_html = $label(_x('Quote total', 'billing document')) . '<br><span style="font-size:17pt; font-weight:bold;">' . $money($totals['total']) . '</span>';
    } else {
        $amount_html = $label($frequency_labels[recurringInvoicePeriodWord($row['recurring_invoice_frequency'])] ?? $frequency_labels['month']) . '<br><span style="font-size:17pt; font-weight:bold;">' . $money($totals['total']) . '</span>';
    }
    $html = '<table cellpadding="0" cellspacing="0" width="100%"><tr>
        <td width="60%">' . $label($to_label) . '<br><span style="font-size:10pt; font-weight:bold;">' . $e($bill_to['client_name'] ?? $row['client_name']) . '</span><br><span style="font-size:7.5pt; color:' . $grey . '; line-height:1.4;">' . nl2br($e(implode("\n", $to_lines))) . '</span></td>
        <td width="40%" align="right">' . $amount_html . '</td>
    </tr></table>';
    $pdf->writeHTML($html, true, false, true, false, '');

    // A stamp across a document that is done with
    $stamp = null;
    if (!$packing) {
        if ($type === 'invoice' && $status === 'Paid') {
            $stamp = [_x('Paid', 'billing stamp'), [25, 135, 84]];
        } elseif ($type === 'invoice' && $status === 'Cancelled') {
            $stamp = [_x('Cancelled', 'billing stamp'), [220, 53, 69]];
        } elseif ($type === 'quote' && in_array($status, ['Accepted', 'Invoiced'], true)) {
            $stamp = [_x('Accepted', 'billing stamp'), [25, 135, 84]];
        } elseif ($type === 'quote' && $status === 'Declined') {
            $stamp = [_x('Declined', 'billing stamp'), [220, 53, 69]];
        }
    }
    if ($stamp) {
        $stamp[0] = mb_strtoupper($stamp[0]);
        $y = $pdf->GetY();
        $pdf->SetAlpha(0.28);
        $pdf->StartTransform();
        $pdf->Rotate(12, $margins['left'] + $width / 2, 21);
        $pdf->SetFont('dejavusans', 'B', 20);
        $pdf->SetTextColor(...$stamp[1]);
        $pdf->SetDrawColor(...$stamp[1]);
        $pdf->SetLineWidth(0.8);
        $stamp_width = $pdf->GetStringWidth($stamp[0]) + 8;
        $pdf->SetXY($margins['left'] + $width / 2 - $stamp_width / 2, 15.5);
        $pdf->Cell($stamp_width, 11, $stamp[0], 1, 0, 'C');
        $pdf->StopTransform();
        $pdf->SetAlpha(1);
        $pdf->SetDrawColor(222, 226, 230);
        $pdf->SetLineWidth(0.2);
        $pdf->SetTextColor(33, 37, 41);
        $pdf->SetXY($margins['left'], $y);
    }

    $pdf->SetFont('dejavusans', '', 8.5);
    if (trim((string) $row["{$c}_scope"]) !== '') {
        $pdf->Ln(1);
        $pdf->writeHTML('<table cellpadding="5" cellspacing="0" width="100%"><tr><td style="background-color:#f1f3f5;">'
            . $label(__('For')) . '&nbsp;&nbsp;' . $e($row["{$c}_scope"]) . '</td></tr></table>', true, false, true, false, '');
    }
    $pdf->Ln(2);

    // The lines
    $th = 'font-size:6.5pt; color:' . $grey . '; font-weight:bold; border-bottom:0.6px solid #adb5bd;';
    $td = 'border-bottom:0.3px solid ' . $line . ';';
    if ($packing) {
        // The serials of hardware already on the client's assets, under its line
        $serials = [];
        $sql = mysqli_query($mysqli, "SELECT asset_invoice_item_id, asset_serial FROM assets
            WHERE asset_invoice_id = $id AND asset_invoice_item_id > 0 AND COALESCE(asset_serial, '') <> '' ORDER BY asset_name ASC, asset_id ASC");
        while ($serial = mysqli_fetch_assoc($sql)) {
            $serials[intval($serial['asset_invoice_item_id'])][] = $serial['asset_serial'];
        }
        $html = '<table cellpadding="4" cellspacing="0" width="100%"><thead><tr>
            <td width="74%" style="' . $th . '">' . $e(mb_strtoupper(__('Item'))) . '</td><td width="13%" align="right" style="' . $th . '">' . $e(mb_strtoupper(_x('Qty', 'billing document'))) . '</td><td width="13%" align="center" style="' . $th . '">' . $e(mb_strtoupper(_x('Packed', 'billing document'))) . '</td></tr></thead>';
        foreach ($items as $item) {
            $item_serials = $serials[intval($item['item_id'])] ?? [];
            $html .= '<tr nobr="true"><td width="74%" style="' . $td . '"><b>' . $e($item['item_name']) . '</b>'
                . (trim((string) $item['item_description']) !== '' ? '<br><span style="font-size:7.5pt; color:' . $grey . ';">' . nl2br($e($item['item_description'])) . '</span>' : '')
                . ($item_serials ? '<br><span style="font-size:7.5pt;">' . $e(count($item_serials) === 1 ? __('Serial') : _x('Serials', 'billing document')) . ': <font face="courier">' . $e(implode(', ', $item_serials)) . '</font></span>' : '') . '</td>
                <td width="13%" align="right" style="' . $td . '">' . $e(billingQuantity($item['item_quantity'])) . '</td>
                <td width="13%" align="center" style="' . $td . '"><span style="font-size:11pt;">☐</span></td></tr>';
        }
        $html .= '</table>';
    } else {
        $html = '<table cellpadding="4" cellspacing="0" width="100%"><thead><tr>
            <td width="49%" style="' . $th . '">' . $e(mb_strtoupper(__('Item'))) . '</td><td width="9%" align="right" style="' . $th . '">' . $e(mb_strtoupper(_x('Qty', 'billing document'))) . '</td>
            <td width="14%" align="right" style="' . $th . '">' . $e(mb_strtoupper(__('Price'))) . '</td><td width="13%" align="right" style="' . $th . '">' . $e(mb_strtoupper(__('Tax'))) . '</td>
            <td width="15%" align="right" style="' . $th . '">' . $e(mb_strtoupper(__('Amount'))) . '</td></tr></thead>';
        $line_groups = $groups ?: ['' => ['frequency' => '', 'label' => '', 'items' => $items]];
        foreach ($line_groups as $line_group) {
            if ($groups) {
                $html .= '<tr nobr="true"><td colspan="5" style="border-bottom:0.3px solid ' . $line . ';"><br>' . $label($frequency_labels[$line_group['frequency']] ?? $line_group['label']) . '</td></tr>';
            }
            foreach ($line_group['items'] as $item) {
                $tax_label = floatval($item['item_tax']) != 0 || intval($item['item_tax_id'])
                    ? '<br><span style="font-size:6.5pt; color:' . $grey . ';">' . $e(trim(($item['tax_name'] ?? '') . ' ' . ($item['tax_percent'] !== null ? taxPercentText($item['tax_percent']) . '%' : ''))) . '</span>'
                    : '';
                $html .= '<tr nobr="true"><td width="49%" style="' . $td . '"><b>' . $e($item['item_name']) . '</b>'
                    . (trim((string) $item['item_description']) !== '' ? '<br><span style="font-size:7.5pt; color:' . $grey . ';">' . nl2br($e($item['item_description'])) . '</span>' : '') . '</td>
                    <td width="9%" align="right" style="' . $td . '">' . $e(billingQuantity($item['item_quantity'])) . '</td>
                    <td width="14%" align="right" style="' . $td . '">' . $money($item['item_price']) . '</td>
                    <td width="13%" align="right" style="' . $td . '">' . ($tax_label !== '' ? $money($item['item_tax']) . $tax_label : '<span style="color:' . $grey . ';">-</span>') . '</td>
                    <td width="15%" align="right" style="' . $td . '"><b>' . $money($item['item_total']) . '</b></td></tr>';
            }
        }
        if (!$items) {
            $html .= '<tr><td colspan="5" align="center" style="color:' . $grey . ';">' . $e(_x('Nothing on it yet.', 'billing document')) . '</td></tr>';
        }
        $html .= '</table>';
    }
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(3);

    if ($packing) {
        // Who packed it, and who took it
        $sign = '<table cellpadding="5" cellspacing="0" width="100%"><tr nobr="true">
            <td width="48%" style="border:0.4px solid #adb5bd; height:22mm;">' . $label(_x('Packed by', 'billing document')) . '<br><br><br><span style="font-size:7pt; color:' . $grey . ';">' . $e(_x('Name, signature and date', 'billing document')) . '</span></td>
            <td width="4%"></td>
            <td width="48%" style="border:0.4px solid #adb5bd; height:22mm;">' . $label(_x('Received by', 'billing document')) . '<br><br><br><span style="font-size:7pt; color:' . $grey . ';">' . $e(_x('Name, signature and date', 'billing document')) . '</span></td>
        </tr></table>';
        $pdf->writeHTML($sign, true, false, true, false, '');
    } else {
        // Notes, and what it all comes to
        $rows = '<tr><td style="color:' . $grey . ';">' . $e(__('Subtotal')) . '</td><td align="right">' . $money($totals['subtotal']) . '</td></tr>';
        if ($shown_groups) {
            // A block for each - what is billed once, and each month, quarter or year
            // (widths set on each row - "Total each quarter" needs more than half)
            $rows = '';
            $w = ' width="60%"';
            $wr = ' width="40%"';
            foreach (array_values($shown_groups) as $i => $group) {
                if ($i) {
                    $rows .= '<tr><td colspan="2" style="font-size:6pt;">&nbsp;</td></tr>';
                }
                $rows .= '<tr><td colspan="2">' . $label($frequency_labels[$group['frequency']] ?? $group['label']) . '</td></tr>';
                $rows .= '<tr><td' . $w . ' style="color:' . $grey . ';">' . $e(__('Subtotal')) . '</td><td' . $wr . ' align="right">' . $money($group['subtotal']) . '</td></tr>';
                if ($group['discount'] != 0) {
                    $rows .= '<tr><td' . $w . ' style="color:' . $grey . ';">' . $e(__('Discount')) . '</td><td' . $wr . ' align="right">-' . $money($group['discount']) . '</td></tr>';
                }
                foreach ($group['taxes'] as $tax_label => $tax_amount) {
                    $rows .= '<tr><td' . $w . ' style="color:' . $grey . ';">' . $e($tax_label) . '</td><td' . $wr . ' align="right">' . $money($tax_amount) . '</td></tr>';
                }
                $rows .= '<tr><td' . $w . ' style="border-top:0.6px solid #adb5bd; font-size:10pt;"><b>' . $e($frequency_totals[$group['frequency']] ?? $frequency_totals['']) . '</b></td><td' . $wr . ' align="right" style="border-top:0.6px solid #adb5bd; font-size:10pt;"><b>' . $money($group['total']) . '</b></td></tr>';
            }
        }
        if (!$shown_groups) {
            if ($totals['discount'] != 0) {
                $rows .= '<tr><td style="color:' . $grey . ';">' . $e(__('Discount')) . '</td><td align="right">-' . $money($totals['discount']) . '</td></tr>';
            }
            foreach ($totals['taxes'] as $tax_label => $tax_amount) {
                $rows .= '<tr><td style="color:' . $grey . ';">' . $e($tax_label) . '</td><td align="right">' . $money($tax_amount) . '</td></tr>';
            }
            if ($totals['credit'] > 0) {
                $rows .= '<tr><td style="color:' . $grey . ';">' . $e(__('Credit')) . '</td><td align="right">-' . $money($totals['credit']) . '</td></tr>';
            }
            $rows .= '<tr><td style="border-top:0.6px solid #adb5bd; font-size:10pt;"><b>' . $e(_x('Total', 'billing document')) . '</b></td><td align="right" style="border-top:0.6px solid #adb5bd; font-size:10pt;"><b>' . $money($totals['total']) . '</b></td></tr>';
        }
        if ($type === 'invoice' && $state['paid'] > 0) {
            $rows .= '<tr><td style="color:#198754;">' . $e(__('Paid')) . '</td><td align="right" style="color:#198754;">-' . $money($state['paid']) . '</td></tr>';
            $rows .= '<tr><td style="border-top:0.6px solid #adb5bd; font-size:10pt;"><b>' . $e(_x('Balance due', 'billing document')) . '</b></td><td align="right" style="border-top:0.6px solid #adb5bd; font-size:10pt;"><b>' . $money($state['balance']) . '</b></td></tr>';
        }
        $note = trim((string) $row["{$c}_note"]);
        $html = '<table cellpadding="0" cellspacing="0" width="100%"><tr nobr="true">
            <td width="54%">' . ($note !== '' ? $label(__('Notes')) . '<br><span style="font-size:8pt;">' . nl2br($e($note)) . '</span>' : '') . '</td>
            <td width="6%"></td>
            <td width="40%"><table cellpadding="2.5" cellspacing="0" width="100%" style="font-size:8.5pt;">' . $rows . '</table></td>
        </tr></table>';
        $pdf->writeHTML($html, true, false, true, false, '');

        // Open it online - pay an invoice still owed, answer a quote still waiting
        $online = null;
        if ($type === 'invoice' && in_array($status, invoiceOpenStatuses(), true) && $state['balance'] > 0) {
            $online = billingOnlinePayments()
                ? [_x('Pay online', 'billing document'), _x('Scan to open this invoice and pay it by card.', 'billing document')]
                : [_x('View online', 'billing document'), _x('Scan to open this invoice online.', 'billing document')];
        } elseif ($type === 'quote' && in_array($status, ['Sent', 'Viewed'], true) && !quoteExpired($row)) {
            $online = [_x('Answer online', 'billing document'), _x('Scan to open this quote and accept or decline it.', 'billing document')];
        }
        if ($online) {
            $url = billingGuestUrl($type, $row);
            if ($pdf->GetY() > $pdf->getPageHeight() - 60) {
                $pdf->AddPage();
            }
            $pdf->Ln(4);
            $y = $pdf->GetY();
            $pdf->SetFillColor(241, 243, 245);
            $pdf->RoundedRect($margins['left'], $y, $width, 28, 2, '1111', 'F');
            $pdf->write2DBarcode($url, 'QRCODE,M', $margins['left'] + 3, $y + 3, 22, 22, ['border' => false, 'padding' => 0, 'fgcolor' => [33, 37, 41], 'bgcolor' => false], 'N');
            $pdf->SetXY($margins['left'] + 30, $y + 6);
            $pdf->SetFont('dejavusans', 'B', 10);
            $pdf->Cell($width - 34, 5, $online[0], 0, 2, 'L');
            $pdf->SetFont('dejavusans', '', 7.5);
            $pdf->SetTextColor(108, 117, 125);
            $pdf->MultiCell($width - 34, 4, $online[1] . "\n" . $url, 0, 'L', false, 1, $margins['left'] + 30);
            $pdf->SetTextColor(33, 37, 41);
            $pdf->SetY($y + 30);
        }

        $footer = trim((string) ($type === 'quote' ? $config_quote_footer : $config_invoice_footer));
        if ($footer !== '') {
            $pdf->Ln(4);
            $pdf->SetFont('dejavusans', '', 7.5);
            $pdf->SetTextColor(108, 117, 125);
            $pdf->MultiCell($width, 4, $footer, 0, 'C', false, 1);
        }
    }

    $date = $type === 'recurring' ? date('Y-m-d') : (string) $row["{$c}_date"];
    $name = preg_replace('/[^A-Za-z0-9_\-]+/', '_', trim("{$date}_{$row['client_name']}_" . str_replace(' ', '_', $title_en) . "_$number", '_')) . '.pdf';
    return ['name' => $name, 'data' => $pdf->Output($name, 'S')];
}

/**
 * A client's statement as a PDF - billingStatement() - in the same look:
 * who it is from and for, the date and period, what is owed and how late,
 * then each invoice with what was billed, paid and is left. ['name', 'data'].
 */
function billingStatementPdfRender(array $statement) {
    global $config_invoice_footer;
    billingPdfConfig();

    $company = billingCompany();
    $client = $statement['client'];
    $bill_to = billingBillTo($client['client_id']);
    $currency = $statement['currency'];
    $e = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };
    $money = function ($amount) use ($currency, $e) {
        return $e(billingMoney($amount, $currency));
    };
    $grey = '#6c757d';
    $red = '#c42b37';
    $label = function ($text) use ($grey, $e) {
        return '<span style="font-size:6.5pt; color:' . $grey . '; font-weight:bold;">' . $e(mb_strtoupper($text)) . '</span>';
    };

    $format = in_array($company['company_country'] ?? '', ['United States', 'USA', 'US', 'Canada'], true) ? 'LETTER' : 'A4';
    $pdf = new BillingPdf('P', 'mm', $format, true, 'UTF-8', false);
    $pdf->SetCreator('ITFlow');
    $pdf->SetAuthor((string) ($company['company_name'] ?? ''));
    $pdf->SetTitle(_x('Statement %s', 'billing document', $client['client_name']));
    $pdf->SetMargins(16, 14, 16);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(12);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->setFontSubsetting(true);
    $pdf->setImageScale(1.5);
    $pdf->footer_label = trim(($company['company_name'] ?? '') . ' · ' . __('Statement') . ' · ' . $client['client_name'], ' ·');
    $pdf->AddPage();
    $margins = $pdf->getMargins();
    $width = $pdf->getPageWidth() - $margins['left'] - $margins['right'];

    // Who it is from, and what it is
    $from_lines = array_filter([
        formatAddress($company['company_address'] ?? '', $company['company_city'] ?? '', $company['company_state'] ?? '', $company['company_zip'] ?? '', $company['company_country'] ?? '', "\n"),
        implode('  ·  ', array_filter([$company['company_email'] ?? '', $company['phone_display'] ?? ''])),
        $company['company_website'] ?? '',
    ]);
    $logo = !empty($company['logo_path']) ? '<img src="' . $e($company['logo_path']) . '" height="42"><br>' : '';
    $date = function ($value) {
        return (string) $value !== '' ? formatDate($value, 'date') : '';
    };
    $dates = [_x('Statement date', 'billing document') => $date(date('Y-m-d')), _x('Period', 'billing document') => $statement['from'] !== ''
        ? _x('%1$s to %2$s', 'billing period', $date($statement['from']), $date($statement['to'])) : _x('Up to %s', 'billing period', $date($statement['to']))];
    $dates_html = '';
    foreach ($dates as $date_label => $value) {
        $dates_html .= '<tr><td align="right" style="color:' . $grey . ';">' . $e($date_label) . '</td><td align="right" width="42mm">' . $e($value) . '</td></tr>';
    }
    $html = '<table cellpadding="0" cellspacing="0" width="100%"><tr>
        <td width="52%">' . $logo . '<span style="font-size:12pt; font-weight:bold;">' . $e($company['company_name'] ?? '') . '</span><br><span style="font-size:7.5pt; color:' . $grey . '; line-height:1.4;">' . nl2br($e(implode("\n", $from_lines))) . '</span></td>
        <td width="48%" align="right"><span style="font-size:8pt; color:#0a58ca; font-weight:bold; letter-spacing:2px;">' . $e(mb_strtoupper(__('Statement'))) . '</span><br><span style="font-size:15pt; font-weight:bold;">' . $e($client['client_name']) . '</span><br>
            <table cellpadding="1.2" cellspacing="0" width="100%" style="font-size:8pt;">' . $dates_html . '</table></td>
    </tr></table>';
    $pdf->SetFont('dejavusans', '', 8.5);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->SetDrawColor(222, 226, 230);
    $pdf->SetLineWidth(0.2);
    $pdf->Ln(1);
    $pdf->Line($margins['left'], $pdf->GetY(), $margins['left'] + $width, $pdf->GetY());
    $pdf->Ln(4);

    // Who it is for, and what is owed
    $to_lines = array_filter([
        formatAddress($bill_to['location_address'] ?? '', $bill_to['location_city'] ?? '', $bill_to['location_state'] ?? '', $bill_to['location_zip'] ?? '', $bill_to['location_country'] ?? '', "\n"),
        $bill_to['contact_name'] ?? '',
        implode('  ·  ', array_filter([$bill_to['contact_email'] ?? '', $bill_to['contact_phone'] ?? ''])),
    ]);
    $html = '<table cellpadding="0" cellspacing="0" width="100%"><tr>
        <td width="60%">' . $label(_x('Statement for', 'billing document')) . '<br><span style="font-size:10pt; font-weight:bold;">' . $e($bill_to['client_name'] ?? $client['client_name']) . '</span><br><span style="font-size:7.5pt; color:' . $grey . '; line-height:1.4;">' . nl2br($e(implode("\n", $to_lines))) . '</span></td>
        <td width="40%" align="right">' . $label(_x('Total due', 'billing document')) . '<br><span style="font-size:17pt; font-weight:bold;">' . $money($statement['total']) . '</span>'
        . ($statement['overdue'] > 0 ? '<br><span style="font-size:7.5pt; color:' . $red . ';">' . $e(_x('%s of it overdue', 'billing document', billingMoney($statement['overdue'], $currency))) . '</span>' : '') . '</td>
    </tr></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(2);

    // How late it is
    $cells = '';
    foreach ($statement['aging'] as $bucket => $amount) {
        $late = $bucket !== 'Current' && $amount > 0;
        $cells .= '<td align="center" style="border:0.3px solid #dee2e6; background-color:#f8f9fa;">' . $label(billingAgingLabel($bucket)) . '<br><span style="font-size:9.5pt; font-weight:bold;' . ($late ? ' color:' . $red . ';' : '') . '">' . $money($amount) . '</span></td>';
    }
    $pdf->writeHTML('<table cellpadding="4" cellspacing="0" width="100%"><tr>' . $cells . '</tr></table>', true, false, true, false, '');
    $pdf->Ln(3);

    // Each invoice
    $th = 'font-size:6.5pt; color:' . $grey . '; font-weight:bold; border-bottom:0.6px solid #adb5bd;';
    $td = 'border-bottom:0.3px solid #dee2e6;';
    $html = '<table cellpadding="4" cellspacing="0" width="100%"><thead><tr>
        <td width="14%" style="' . $th . '">' . $e(mb_strtoupper(__('Date'))) . '</td><td width="30%" style="' . $th . '">' . $e(mb_strtoupper(__('Invoice'))) . '</td><td width="14%" style="' . $th . '">' . $e(mb_strtoupper(_x('Due', 'billing document'))) . '</td>
        <td width="14%" align="right" style="' . $th . '">' . $e(mb_strtoupper(_x('Total', 'billing document'))) . '</td><td width="14%" align="right" style="' . $th . '">' . $e(mb_strtoupper(__('Paid'))) . '</td><td width="14%" align="right" style="' . $th . '">' . $e(mb_strtoupper(__('Balance'))) . '</td></tr></thead>';
    foreach ($statement['invoices'] as $invoice) {
        $late = $invoice['balance'] > 0 && $invoice['days'] > 0;
        $html .= '<tr nobr="true"><td width="14%" style="' . $td . '">' . $e($date($invoice['invoice_date'])) . '</td>
            <td width="30%" style="' . $td . '"><b>' . $e(billingNumber('invoice', $invoice)) . '</b>' . (trim((string) $invoice['invoice_scope']) !== '' ? '<br><span style="font-size:7pt; color:' . $grey . ';">' . $e($invoice['invoice_scope']) . '</span>' : '') . '</td>
            <td width="14%" style="' . $td . ($late ? ' color:' . $red . ';' : '') . '">' . $e($date($invoice['invoice_due'])) . ($late ? '<br><span style="font-size:7pt;">' . $e(_n('%d day late', '%d days late', intval($invoice['days']))) . '</span>' : '') . '</td>
            <td width="14%" align="right" style="' . $td . '">' . $money($invoice['invoice_amount']) . '</td>
            <td width="14%" align="right" style="' . $td . '">' . (floatval($invoice['paid']) > 0 ? $money($invoice['paid']) : '<span style="color:' . $grey . ';">-</span>') . '</td>
            <td width="14%" align="right" style="' . $td . '"><b>' . ($invoice['balance'] > 0 ? $money($invoice['balance']) : '<span style="color:#198754;">' . $e(__('Paid')) . '</span>') . '</b></td></tr>';
    }
    if (!$statement['invoices']) {
        $html .= '<tr><td colspan="6" align="center" style="color:' . $grey . ';">' . $e(_x('Nothing owed in this period.', 'billing document')) . '</td></tr>';
    }
    $html .= '<tr><td colspan="5" align="right" style="border-top:0.6px solid #adb5bd; font-size:10pt;"><b>' . $e(_x('Total due', 'billing document')) . '</b></td><td align="right" style="border-top:0.6px solid #adb5bd; font-size:10pt;"><b>' . $money($statement['total']) . '</b></td></tr></table>';
    $pdf->writeHTML($html, true, false, true, false, '');

    $footer = trim((string) $config_invoice_footer);
    if ($footer !== '') {
        $pdf->Ln(4);
        $pdf->SetFont('dejavusans', '', 7.5);
        $pdf->SetTextColor(108, 117, 125);
        $pdf->MultiCell($width, 4, $footer, 0, 'C', false, 1);
    }

    $name = preg_replace('/[^A-Za-z0-9_\-]+/', '_', trim($statement['to'] . '_' . $client['client_name'] . '_Statement', '_')) . '.pdf';
    return ['name' => $name, 'data' => $pdf->Output($name, 'S')];
}
