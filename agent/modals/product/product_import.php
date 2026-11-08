<?php

/*
 * Import products and services from a CSV - columns found by their heading.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-upload me-2"></i><?= __('Import Products') ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="modal-body">
        <p class="mb-2"><?= __('A CSV with a heading row. Columns can be in any order, and only %s is needed:', '<strong>Name</strong>') ?></p>
        <p class="small text-body-secondary mb-3">
            Name, Type (Service or Product), Code, Category, Description, Price, Cost, Tax, Billed (One time, Monthly, Quarterly, Yearly), <!-- i18n-ignore -->
            Counted (Per user, Per device), Vendor, Part Number, Track Stock (Yes or No), Reorder At, Order, Location, <!-- i18n-ignore -->
            Asset Type (Laptop, Switch, Access Point and so on), Make, Model, Warranty (in months), Notes. <!-- i18n-ignore -->
            <?= __('Categories, taxes and vendors are matched by name to the ones you have.') ?>
        </p>

        <label class="form-label"><?= __('File') ?> <strong class="text-danger">*</strong></label>
        <div class="input-group mb-3">
            <span class="input-group-text"><i class="fas fa-fw fa-file-csv"></i></span>
            <input type="file" class="form-control" name="file" accept=".csv,text/csv" required>
        </div>

        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" name="update_existing" value="1" id="productImportUpdate">
            <label class="form-check-label" for="productImportUpdate"><?= __('Update products with the same code') ?></label>
            <div class="form-text"><?= __('For a new price list - a file with just Code and Price (or Cost) will do. Only the columns in the file change. Off, a row whose code is already here is skipped.') ?></div>
        </div>
    </div>
    <div class="modal-footer">
        <a class="btn btn-outline-secondary me-auto" href="post.php?download_products_csv_template"><i class="fas fa-download me-2"></i><?= __('Template') ?></a>
        <button type="submit" name="import_products_csv" class="btn btn-primary text-bold"><i class="fas fa-upload me-2"></i><?= __('Import') ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= __('Cancel') ?></button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
