<div class="navbar navbar-expand-md navbar-dark bg-dark">
    <div class="container-fluid">
            <ul class="navbar-nav me-auto mb-2 mb-md-0">
                <?php foreach($items as $item) : ?>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="<?= $item['link'] ?>"><?= $item['title'] ?></a>
                    </li>
                <?php endforeach ?> 
            </ul>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarOffcanvas" aria-controls="navbarHeader" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
    </div>
</div>
<div class="offcanvas offcanvas-start" tabindex="-1" id="navbarOffcanvas" aria-labelledby="navbarCollapseLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="navbarCollapseLabel">Offcanvas</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="navbarOffcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="navbar-nav me-auto mb-2 mb-md-0">
            <?php foreach($items as $item) : ?>
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="<?= $item['link'] ?>"><?= $item['title'] ?></a>
                </li>
            <?php endforeach ?> 
        </ul>
        <div class="dropdown mt-3">
        <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
            Dropdown button
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#">Action</a></li>
            <li><a class="dropdown-item" href="#">Another action</a></li>
            <li><a class="dropdown-item" href="#">Something else here</a></li>
        </ul>
        </div>
    </div>
</div>