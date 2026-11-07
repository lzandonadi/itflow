<div class="modal" id="deleteClientModal<?= $client_id ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <div class="mb-4" style="text-align: center;">
                    <i class="far fa-10x fa-times-circle text-danger mb-3 mt-3"></i>
                    <h2><?= __('Are you really, really, really sure?') ?></h2>
                    <br>
                    <h6 class="mb-4 text-secondary"><b><u><?= __('DANGER') ?></u></b><br> <?= __('Do you really want to %1$s? This includes %2$s\'s documents, tickets, files, payments, invoices, logs, etc.', '<b>' . __('delete %s and ALL associated data', '<i>' . $client_name . '</i>') . '</b>', '<i>' . $client_name . '</i>') ?>
                    <br><br>
                    <?php if ($config_module_enable_accounting) { ?>                   
                        <div class="alert alert-danger" role="alert">
                            <?= __('Deleting this client will also delete all associated accounting data. See') ?> <a href="https://forum.itflow.org/d/1147-deleting-a-client-deletes-payments" target="_blank">this</a> forum post for why that may be a bad idea.
                        </div>
                    <?php } ?>
                    <u><?= __('This process cannot be undone.') ?></u></h6>
                    <div class="mb-3">
                        <input type="hidden" id="clientName<?= $client_id ?>" value="<?= $client_name ?>">
                        <input class="form-control" type="text" id="clientNameProvided<?= $client_id ?>" onkeyup="validateClientNameDelete(<?= $client_id ?>)" placeholder="<?= __('Type \'%s\' to confirm data deletion', $client_name) ?>">
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-lg px-5 me-4" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                    <a class="btn btn-danger btn-lg px-5 disabled" id="clientDeleteButton<?= $client_id ?>" href="post.php?delete_client=<?= $client_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><?= __('Yes, Delete!') ?></a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php /* defer is safe here */ ?>
<script src="/agent/js/client_delete_confirm.js" defer></script>
