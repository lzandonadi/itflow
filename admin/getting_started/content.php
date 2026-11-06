<?php

/*
 * Get started - Starter content: the packs Maintenance > Starter Content has,
 * each with how much it would add, ticked to add (getting_started_content).
 * Pick which, under each, lists what it would add as it will look, with
 * Preview - every item ticked, so the whole pack goes in unless some are
 * unticked (includes/starter_content_pick.php, js/starter_content.js). Only
 * what isn't here already is listed or added.
 */

defined('FROM_GETTING_STARTED') || die("Direct file access is not allowed");

defined('FROM_STARTER_CONTENT') || define('FROM_STARTER_CONTENT', true);
require_once dirname(__DIR__) . '/post/starter_content_model.php';
require_once dirname(__DIR__) . '/includes/starter_content_pick.php';

$gs_packs = starterContentPacks();

?>
<form action="post.php" method="post" data-gs-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="list-group mb-3 itflow-gs-packs" data-gs-packs>
        <?php foreach ($gs_packs as $gs_key => $gs_pack) {
            $gs_items = starterContentItems($mysqli, $gs_key);
            $gs_total = starterContentTotal($gs_key);
            $gs_missing = count($gs_items);
            $gs_all_here = $gs_missing === 0; ?>
            <div class="list-group-item<?= $gs_all_here ? ' is-here' : '' ?>" data-gs-pack="<?= $gs_key ?>" data-starter-pick>
                <div class="d-flex gap-3 align-items-start">
                    <input class="form-check-input flex-shrink-0 mt-1" type="checkbox" name="packs[]" value="<?= $gs_key ?>" id="gsPack_<?= $gs_key ?>"<?= $gs_all_here ? ' disabled' : ' checked' ?> data-starter-pack-check>
                    <span class="itflow-gs-pack-icon" aria-hidden="true"><i class="fas fa-fw <?= escapeHtml($gs_pack['icon']) ?>"></i></span>
                    <div class="min-w-0 flex-grow-1">
                        <label class="d-flex flex-wrap align-items-baseline gap-2 mb-0" for="gsPack_<?= $gs_key ?>">
                            <span class="fw-semibold"><?= escapeHtml(__($gs_pack['label'])) // i18n-dynamic - starterContentPacks() ?></span>
                            <?php if ($gs_all_here) { ?>
                                <span class="small text-body-secondary"><i class="fas fa-check text-success me-1"></i><?= __('All %d here', $gs_total) ?></span>
                            <?php } else { ?>
                                <span class="small text-body-secondary" data-starter-count data-starter-more="<?= $gs_missing < $gs_total ? 1 : 0 ?>"><?= $gs_missing < $gs_total ? __('%d more to add', $gs_missing) : __('%d to add', $gs_missing) ?></span>
                            <?php } ?>
                        </label>
                        <div class="small text-body-secondary itflow-gs-pack-about" title="<?= escapeHtml(__($gs_pack['description'])) // i18n-dynamic ?>"><?= escapeHtml(__($gs_pack['description'])) // i18n-dynamic ?></div>
                        <?= starterPickHtml($gs_key, $gs_items) ?>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
    <p class="small text-body-secondary"><?= __('Nothing you already have is changed. Each pack can be added later, or brought up to date, in Maintenance &gt; Starter Content.') ?></p>

    <button type="submit" name="getting_started_content" value="1" class="btn btn-primary" data-gs-save><?= __('Add the ones ticked') ?><i class="fas fa-arrow-right ms-2"></i></button>
</form>
<?= starterPreviewModalHtml() ?>
