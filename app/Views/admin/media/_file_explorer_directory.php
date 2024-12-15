<div class="row row-cols-3 row-cols-sm-4 row-cols-md-5 g-2">
    <?php foreach ($files as $index => $file): ?>
        <?php if (strpos($file, '\\') !== false): ?>
            <div class="col">
                <div class="card fe-item h-100 border-0">
                    <label class="form-check-label nav-link dir-link" for="fileItem<?= $index ?>" data-dir="<?= $currentDir.'/'.rtrim($file, "\\"); ?>">
                        <input type="checkbox" value="" id="fileItem<?= $index ?>" data-dir="<?= $currentDir.'/'.rtrim($file, "\\") ?>"
                            class="select-file form-check-input position-absolute top-0 start-0 m-2">
                        <div class="bg-body-secondary card-image rounded border ratio ratio-1x1" role="button">
                            <div class="image-preview p-2"><i class="bi bi-folder-fill"></i></div>
                        </div>
                        <span class="p-1">
                            <?= rtrim($file, "\\") ?>
                        </span>
                    </label>
                </div>
            </div>
        <?php else: ?>
            <div class="col">
                <div class="card fe-item h-100 border-0">
                    <label class="form-check-label" for="fileItem<?= $index ?>">
                    <input type="checkbox" value="" id="fileItem<?= $index ?>" data-dir="<?= $file ?>" class="select-file form-check-input position-absolute top-0 start-0 m-2">
                        <div class="bg-body-secondary card-image card-media rounded border card-media ratio ratio-1x1" role="button">
                            <div class="image-preview p-2">
                                <?php if (in_array(substr(strrchr($file, "."), 1), ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                    <img src="/image/<?= $currentDir.'/'.$file ?>" alt="<?= $file ?>">
                                <?php else: ?>
                                    <i class="bi bi-file-earmark-text"></i>
                                <?php endif ?>
                            </div>
                        </div><span class="p-1"><?= $file ?></span>
                    </label>
                </div>
            </div>
        <?php endif ?>
    <?php endforeach ?>
</div>