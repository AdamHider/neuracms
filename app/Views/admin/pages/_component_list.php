<div class="offcanvas-header">
    <h5 id="offcanvasComponentsLabel">Offcanvas right</h5>
    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
</div>
<div class="offcanvas-body">
    <div id="available-components-container">
        <div id="available-components" class="d-flex flex-column">
            <?php foreach ($components as $group => $componentsGroup): ?>
                <div class="mb-4">
                    <h5><?= $group ?></h5>   
                    <div class="row g-2">
                        <?php foreach ($componentsGroup as $component): ?>
                        <?php if(isset($component['config']['is_private']) && $component['config']['is_private']) { continue; } ?>
                        <div class="col-6">
                            <div class="card text-center component-item <?= $component['config']['type'] ?>-component" data-type="<?= $component['config']['type'] ?>" data-code="<?= $component['config']['code'] ?>">
                                <div class="card-body px-1 py-2">
                                    <h4><i class="bi bi-<?= $component['config']['icon'] ?>"></i></h4>
                                    <span><?= $component['config']['name'] ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>