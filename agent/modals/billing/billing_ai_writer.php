<?php

/*
 * Write a quote or an invoice with AI (aiBillingDraft(), functions/ai_billing.php)
 * - say what it is for, and AI picks its lines: products and services from
 * the catalog at their prices, labor in hours at the client's rate, and
 * anything else with no price, for the agent to set. It suggests a scope and
 * a note for the client too, and what to check before sending. Nothing goes
 * on until the agent ticks the lines, changes what they like and adds them
 * (billing_ai_lines, agent/post/billing.php). ?type=quote|invoice&doc_id=
 *
 * A quote raised from a ticket can draw on the ticket; either can draw on
 * what ITFlow knows of the client - its people, sites and devices - to size
 * per-user and per-device lines.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$type = (string) ($_GET['type'] ?? '');
$doc_id = intval($_GET['doc_id'] ?? 0);
$doc = in_array($type, ['quote', 'invoice'], true) ? billingRow($type, $doc_id) : null;
if (!$doc) {
    exit(json_encode(['error' => __('That document no longer exists.')]));
}
$client_id = intval($doc['client_id']);
enforceClientAccess();

// Using AI is a permission of its own
if (!aiCan()) {
    permissionRefuse(permissionRefusedText('module_ai', 1, 0));
}
if (!billingCanEditItems($type, $doc)) {
    exit(json_encode(['error' => $type === 'quote' ? __("%s can't be changed now - it has been answered or invoiced.", billingNumber($type, $doc)) : __("%s can't be changed now - it is paid, cancelled or non-billable.", billingNumber($type, $doc))]));
}

$t = billingType($type);
$c = $t['col'];
$number = billingNumber($type, $doc);
$currency = (string) $doc["{$c}_currency_code"];
$scope = trim((string) $doc["{$c}_scope"]);
$support = lookupUserPermission('module_support') >= 1;
$ticket = $support ? aiBillingTicket($type, $doc_id) : null;
$rate = contractClientRate($client_id)['rate'];
$catalog_count = count(aiBillingCatalog());
$prompt = $scope !== '' ? $scope : ($ticket ? $ticket['ticket_subject'] : '');
$writing = $type === 'quote' ? __('AI is writing the quote...') : __('AI is writing the invoice...');

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-magic me-2"></i><?= __('Write %s with AI', escapeHtml($number)) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-ai-billing-form data-type="<?= $type ?>" data-doc-id="<?= $doc_id ?>" data-currency="<?= escapeHtml($currency) ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="type" value="<?= $type ?>">
    <input type="hidden" name="doc_id" value="<?= $doc_id ?>">
    <div class="modal-body">

        <!-- What it is for -->
        <div data-ai-billing-ask>
            <label class="form-label" for="aiBillingPrompt"><?= __('What is it for?') ?></label>
            <div class="input-group">
                <span class="input-group-text align-items-start pt-2"><i class="fas fa-fw fa-comment"></i></span>
                <textarea class="form-control" id="aiBillingPrompt" rows="3" maxlength="2000" data-ai-billing-prompt
                    placeholder="<?= escapeHtml(__('Three new laptops for the new starters - set up, joined to the domain and given Microsoft 365')) ?>"><?= escapeHtml($prompt) ?></textarea>
            </div>
            <div class="mt-2">
                <?php if ($ticket) { ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="aiBillingTicket" checked data-ai-billing-use-ticket>
                        <label class="form-check-label" for="aiBillingTicket"><?= __('Use %1$s - %2$s', escapeHtml(ticketReference($ticket)), escapeHtml($ticket['ticket_subject'])) ?></label>
                    </div>
                <?php } ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="aiBillingClient" checked data-ai-billing-use-client>
                    <label class="form-check-label" for="aiBillingClient"><?= __('Use what ITFlow knows of %s', escapeHtml($doc['client_name'])) ?> <span class="text-body-secondary">- <?= $support ? __('its people, sites and devices, to size per-user and per-device lines') : __('how many people it has, to size per-user and per-device lines') ?></span></label>
                </div>
            </div>
            <div class="small text-body-secondary mt-2">
                <i class="fas fa-fw fa-tags me-1"></i><?= $catalog_count ? __('Lines come from your catalog of %d where one fits, at its prices', $catalog_count) : __('Your catalog is empty, so only labor will have a price') ?>;
                <?= $rate > 0 ? __('labor at %s an hour', escapeHtml(billingMoney($rate, $currency))) : __('labor has no rate - set one for the client or as the default') ?>. <?= __('Nothing goes on until you add it.') ?>
            </div>
            <button type="button" class="btn btn-primary mt-3" data-ai-billing-write><i class="fas fa-fw fa-magic me-2"></i><?= __('Write it') ?></button>
        </div>

        <div class="mt-3" data-ai-billing-status aria-live="polite"></div>

        <!-- What AI wrote, to tick and change -->
        <div class="d-none" data-ai-billing-result>
            <div class="mb-3">
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" id="aiBillingUseScope" name="use_scope" value="1" <?= $scope === '' ? 'checked' : '' ?> data-ai-billing-use-scope>
                    <label class="form-check-label" for="aiBillingUseScope"><?= __('Use as the scope') ?><?= $scope !== '' ? ' <span class="text-body-secondary">- ' . __('now: %s', escapeHtml($scope)) . '</span>' : '' ?></label>
                </div>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-comment"></i></span>
                    <input type="text" class="form-control" name="scope" maxlength="255" data-ai-billing-scope aria-label="<?= __('Scope') ?>">
                </div>
            </div>

            <div class="d-flex align-items-center mb-2">
                <span class="fw-semibold me-auto"><?= __('Lines') ?></span>
                <span class="small text-body-secondary" data-ai-billing-total></span>
            </div>
            <div data-ai-billing-lines></div>

            <div class="mt-3">
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" id="aiBillingUseNote" name="use_note" value="1" checked data-ai-billing-use-note>
                    <label class="form-check-label" for="aiBillingUseNote"><?= __('Add to its notes') ?> <span class="text-body-secondary">- <?= __('the client sees them') ?></span></label>
                </div>
                <div class="input-group">
                    <span class="input-group-text align-items-start pt-2"><i class="fas fa-fw fa-sticky-note"></i></span>
                    <textarea class="form-control" name="note" rows="3" data-ai-billing-note aria-label="<?= __('Note') ?>"></textarea>
                </div>
            </div>

            <div class="alert alert-info small mt-3 mb-0 d-none" data-ai-billing-checks>
                <div class="fw-semibold mb-1"><i class="fas fa-fw fa-clipboard-check me-1"></i><?= __('Before you send it') ?></div>
                <ul class="mb-0 ps-3"></ul>
            </div>
            <div class="small text-body-secondary mt-3"><i class="fas fa-fw fa-robot me-1"></i><?= __("Written by AI - check the quantities and hours. Prices are your catalog's and the client's rate; AI never sets one.") ?></div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="billing_ai_lines" class="btn btn-primary text-bold d-none" data-ai-billing-add><i class="fas fa-fw fa-plus me-2"></i><span><?= __('Add to %s', escapeHtml($number)) ?></span></button>
        <button type="button" class="btn btn-light border d-none" data-ai-billing-again><i class="fas fa-fw fa-redo me-2"></i><?= __('Again') ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i><?= __('Cancel') ?></button>
    </div>
</form>

<script>
(function () {
    var form = document.querySelector('[data-ai-billing-form]:not([data-ready])');
    if (!form) {
        return;
    }
    form.setAttribute('data-ready', '1');
    var type = form.getAttribute('data-type');
    var currency = form.getAttribute('data-currency') || 'USD';
    var ask = form.querySelector('[data-ai-billing-ask]');
    var prompt = form.querySelector('[data-ai-billing-prompt]');
    var useTicket = form.querySelector('[data-ai-billing-use-ticket]');
    var useClient = form.querySelector('[data-ai-billing-use-client]');
    var write = form.querySelector('[data-ai-billing-write]');
    var status = form.querySelector('[data-ai-billing-status]');
    var result = form.querySelector('[data-ai-billing-result]');
    var scope = form.querySelector('[data-ai-billing-scope]');
    var lines = form.querySelector('[data-ai-billing-lines]');
    var total = form.querySelector('[data-ai-billing-total]');
    var note = form.querySelector('[data-ai-billing-note]');
    var useNote = form.querySelector('[data-ai-billing-use-note]');
    var checks = form.querySelector('[data-ai-billing-checks]');
    var add = form.querySelector('[data-ai-billing-add]');
    var again = form.querySelector('[data-ai-billing-again]');
    var t = <?= json_encode([
        'add_line' => __('Add this line'), 'item' => __('Item'), 'description' => __('Description (optional)'), 'description_label' => __('Description'),
        'hours' => __('Hours'), 'qty' => __('Qty'), 'billed' => _x('Billed', 'how often'), 'price' => __('Price'), 'set_price' => __('Set a price'),
        'once' => __('once'), 'comes_to' => __('Comes to %s, before tax'), 'add_one' => __('Add %d line'), 'add_many' => __('Add %d lines'),
        'add_to' => __('Add to %s', $number), 'writing' => $writing, 'failed' => __('Could not write it - try again.'),
        'per' => ['month' => _x('monthly', 'per period'), 'quarter' => _x('quarterly', 'per period'), 'year' => _x('yearly', 'per period')],
    ], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var frequencies = <?= json_encode(quoteFrequencies(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_FORCE_OBJECT) ?>;
    var kinds = {
        catalog: ['bg-success-subtle text-success-emphasis', 'fa-tag', <?= json_encode(__('From the catalog'), JSON_HEX_TAG | JSON_HEX_AMP) ?>],
        labor: ['bg-info-subtle text-info-emphasis', 'fa-user-clock', <?= json_encode(__("Labor at the client's rate"), JSON_HEX_TAG | JSON_HEX_AMP) ?>],
        other: ['bg-warning-subtle text-warning-emphasis', 'fa-exclamation-triangle', <?= json_encode(__('Not in the catalog - set a price'), JSON_HEX_TAG | JSON_HEX_AMP) ?>]
    };

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function money(amount) {
        try {
            return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency }).format(amount);
        } catch (e) {
            return (Math.round(amount * 100) / 100).toFixed(2);
        }
    }

    function number(value) {
        var text = String(value == null ? '' : value).replace(/[\s,$€£]/g, '');
        return text === '' ? NaN : Number(text);
    }

    // An input with its icon in front, as the rest of ITFlow's forms have
    function field(label, icon, input, className) {
        var box = el('div', className);
        box.appendChild(el('label', 'form-label small text-body-secondary mb-0', label));
        var group = el('div', 'input-group input-group-sm');
        var span = el('span', 'input-group-text');
        span.appendChild(el('i', 'fas fa-fw ' + icon));
        group.appendChild(span);
        group.appendChild(input);
        box.appendChild(group);
        return box;
    }

    function line(item, i) {
        var base = 'lines[' + i + ']';
        var card = el('div', 'border rounded p-2 mb-2');
        card.setAttribute('data-ai-billing-line', item.kind);

        var top = el('div', 'd-flex align-items-start gap-2');
        var include = el('input', 'form-check-input mt-2');
        include.type = 'checkbox';
        include.name = base + '[include]';
        include.value = '1';
        include.checked = true;
        include.setAttribute('aria-label', t.add_line);
        include.setAttribute('data-ai-billing-include', '');
        top.appendChild(include);

        var main = el('div', 'flex-grow-1 min-w-0');
        var name = el('input', 'form-control form-control-sm fw-semibold mb-1');
        name.type = 'text';
        name.name = base + '[name]';
        name.maxLength = 200;
        name.required = true;
        name.value = item.name;
        name.setAttribute('aria-label', t.item);
        main.appendChild(name);
        var description = el('textarea', 'form-control form-control-sm');
        description.name = base + '[description]';
        description.rows = 2;
        description.value = item.description || '';
        description.placeholder = t.description;
        description.setAttribute('aria-label', t.description_label);
        main.appendChild(description);

        var kind = kinds[item.kind] || kinds.other;
        var badge = el('span', 'badge rounded-pill fw-normal mt-1 ' + kind[0]);
        badge.appendChild(el('i', 'fas ' + kind[1] + ' me-1'));
        badge.appendChild(document.createTextNode(kind[2]));
        main.appendChild(badge);
        top.appendChild(main);
        card.appendChild(top);

        var numbers = el('div', 'd-flex flex-wrap gap-2 mt-2 ps-4');
        var qty = el('input', 'form-control text-end');
        qty.type = 'text';
        qty.inputMode = 'decimal';
        qty.name = base + '[qty]';
        qty.required = true;
        qty.value = item.qty;
        numbers.appendChild(field(item.kind === 'labor' ? t.hours : t.qty, item.kind === 'labor' ? 'fa-clock' : 'fa-balance-scale', qty, 'itflow-ai-line-qty'));
        if (type === 'quote') {
            var billed = el('select', 'form-select');
            billed.name = base + '[frequency]';
            Object.keys(frequencies).forEach(function (value) {
                var option = el('option', '', frequencies[value]);
                option.value = value;
                option.selected = value === (item.frequency || '');
                billed.appendChild(option);
            });
            numbers.appendChild(field(t.billed, 'fa-redo-alt', billed, 'itflow-ai-line-billed'));
        }
        var price = el('input', 'form-control text-end');
        price.type = 'text';
        price.inputMode = 'decimal';
        price.name = base + '[price]';
        price.required = true;
        price.placeholder = t.set_price;
        price.value = item.price === null ? '' : Number(item.price).toFixed(2);
        price.setAttribute('data-ai-billing-price', '');
        numbers.appendChild(field(t.price, 'fa-dollar-sign', price, 'itflow-ai-line-price'));
        var amount = el('div', 'ms-auto align-self-end small fw-semibold text-nowrap pb-1');
        amount.setAttribute('data-ai-billing-amount', '');
        numbers.appendChild(amount);
        card.appendChild(numbers);

        [['kind', item.kind], ['product_id', item.product_id || 0]].forEach(function (pair) {
            var hidden = el('input');
            hidden.type = 'hidden';
            hidden.name = base + '[' + pair[0] + ']';
            hidden.value = pair[1];
            card.appendChild(hidden);
        });

        // An unticked line fades, and isn't checked or sent
        include.addEventListener('change', function () {
            card.classList.toggle('opacity-50', !include.checked);
            card.querySelectorAll('input:not([data-ai-billing-include]), textarea, select').forEach(function (input) {
                input.disabled = !include.checked;
            });
            update();
        });
        return card;
    }

    // What the ticked lines come to, before tax - once, and each month, quarter or year
    function update() {
        var sums = {};
        var count = 0;
        lines.querySelectorAll('[data-ai-billing-line]').forEach(function (card) {
            var on = card.querySelector('[data-ai-billing-include]').checked;
            var q = number(card.querySelector('[name$="[qty]"]').value);
            var p = number(card.querySelector('[data-ai-billing-price]').value);
            var billed = card.querySelector('[name$="[frequency]"]');
            var amount = card.querySelector('[data-ai-billing-amount]');
            var sum = isNaN(q) || isNaN(p) ? NaN : Math.round(q * p * 100) / 100;
            amount.textContent = isNaN(sum) ? '' : money(sum);
            if (on) {
                count++;
                if (!isNaN(sum)) {
                    var key = billed ? billed.value : '';
                    sums[key] = (sums[key] || 0) + sum;
                }
            }
        });
        var parts = Object.keys(frequencies).filter(function (key) {
            return sums[key];
        }).map(function (key) {
            return money(sums[key]) + (key === '' ? (Object.keys(sums).length > 1 ? ' ' + t.once : '') : ' ' + t.per[key]);
        });
        total.textContent = parts.length ? t.comes_to.replace('%s', parts.join(' + ')) : '';
        add.disabled = count === 0 && !(form.querySelector('[data-ai-billing-use-scope]').checked && scope.value.trim()) && !(useNote.checked && note.value.trim());
        add.querySelector('span').textContent = count ? (count === 1 ? t.add_one : t.add_many).replace('%d', count) : t.add_to;
    }

    function show(answer) {
        scope.value = answer.scope || '';
        lines.innerHTML = '';
        answer.lines.forEach(function (item, i) {
            lines.appendChild(line(item, i));
        });
        note.value = answer.note || '';
        useNote.checked = !!answer.note;
        var list = checks.querySelector('ul');
        list.innerHTML = '';
        (answer.checks || []).forEach(function (text) {
            list.appendChild(el('li', '', text));
        });
        checks.classList.toggle('d-none', !(answer.checks || []).length);
        result.classList.remove('d-none');
        ask.classList.add('d-none');
        add.classList.remove('d-none');
        again.classList.remove('d-none');
        update();
    }

    function writeIt() {
        if (!prompt.value.trim()) {
            prompt.focus();
            return;
        }
        write.disabled = true;
        again.disabled = true;
        add.disabled = true;
        status.innerHTML = '<div class="text-body-secondary"><i class="fas fa-spinner fa-spin me-2"></i>' + t.writing + '</div>';
        fetch('/agent/ajax.php?ai_billing_draft', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: new URLSearchParams({
                type: type,
                doc_id: form.getAttribute('data-doc-id'),
                prompt: prompt.value,
                use_ticket: useTicket && useTicket.checked ? '1' : '',
                use_client: useClient.checked ? '1' : ''
            }).toString()
        })
            .then(function (response) {
                return response.json().catch(function () {
                    throw new Error('');
                });
            })
            .then(function (answer) {
                if (answer.error || !answer.lines) {
                    throw new Error(answer.error || '');
                }
                status.innerHTML = '';
                show(answer);
            })
            .catch(function (error) {
                status.innerHTML = '';
                var p = el('p', 'text-danger-emphasis mb-0', error && error.message ? error.message : t.failed);
                p.setAttribute('data-ai-billing-error', '');
                status.appendChild(p);
                if (!result.classList.contains('d-none')) {
                    update();
                }
            })
            .then(function () {
                write.disabled = false;
                again.disabled = false;
            });
    }

    write.addEventListener('click', writeIt);
    prompt.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            writeIt();
        }
    });
    // Again - back to what it is for, to change it and write it again
    again.addEventListener('click', function () {
        ask.classList.remove('d-none');
        result.classList.add('d-none');
        add.classList.add('d-none');
        again.classList.add('d-none');
        status.innerHTML = '';
        prompt.focus();
    });
    form.addEventListener('input', update);
    form.addEventListener('change', function (e) {
        if (!e.target.hasAttribute('data-ai-billing-include')) {
            update();
        }
    });
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
