function renderFileList(files, currentDir) {
    let fileList = $('<div class="row row-cols-3 row-cols-sm-4 row-cols-md-5 g-2"></div>');
    files.forEach(function(file, index) {
        let col = $('<div class="col"><div class="card fe-item h-100 border-0"></div></div>');
        let card = col.find('.card');
        let title = $('<span>').addClass('p-1').text(file)
        let label, image;
        if (file.endsWith('\\')) {
            image = renderFileImage('icon', 'folder-fill');
            label = renderFileCheckbox(file.slice(0, -1), index);
            label.addClass('nav-link dir-link').attr('data-dir', currentDir + '/' + file.slice(0, -1)).on('click', function(e) {
                e.preventDefault();
                const dir = $(this).data('dir');
                loadFiles(dir);
            })
        } else {
            if (isImage(file)) {
                image = renderFileImage('image', file);
            } else {
                image = renderFileImage('icon', 'file-earmark-text');
            }
            label = renderFileCheckbox(file, index);
        }
        label.append(image)
        label.append(title);
        card.append(label);
        fileList.append(col);
    });

    return fileList;
}

function renderFileImage(type, data){
    const imageContainer = $('<div>').addClass('bg-body-secondary card-image rounded border card-media ratio ratio-1x1').prop('role', 'button');
    const image = $('<div>').addClass('image-preview p-2')
    if(type == 'image'){
        image.append('<img src="/image/' + currentDir + '/' + data + '" alt="' + data + '">')
    } else {
        image.append('<i class="bi bi-'+data+'"></i>')
    }
    return imageContainer.append(image);
}
function renderFileCheckbox(data, index){
    const label = $('<label class="form-check-label" for="fileItem' + index + '">');
    const input = $('<input type="checkbox" value="" id="fileItem' + index + '" data-dir="' + data + '">');
    input.addClass('select-file form-check-input position-absolute top-0 start-0 m-2');
    input.on('change', (e) => {
        e.preventDefault();
        const element = e.delegateTarget
        const checked = $(element).prop('checked')
        selectItem(element, checked)
        renderSelected()
    }).on('click', (e) => {
        e.stopPropagation()
    })
    label.append(input)
    
    return label;
}
function isImage(file){
    return ['jpg', 'jpeg', 'png', 'gif'].includes(file.split('.').pop().toLowerCase())
}

function updateBreadcrumbs(currentDir) {
    $('#breadcrumbs').empty()
    let parts = currentDir ? currentDir.split('/') : [];
    const breadcrums = $('#breadcrumbs');
    
    let item = $('<li>').addClass('breadcrumb-item').on('click', '.dir-link', function(e) {
        e.preventDefault();
        const dir = $(this).data('dir');
        loadFiles(dir);
    })
    breadcrums.append(item.append('<a href="#" class="dir-link" data-dir="">Home</a></li>'));

    let path = '';
    parts.forEach(function(part, index) {
        path += (index > 0 ? '/' : '') + part;
        item = $('<li>').addClass('breadcrumb-item').on('click', '.dir-link', function(e) {
            e.preventDefault();
            const dir = $(this).data('dir');
            loadFiles(dir);
        })
        if (index == parts.length - 1) {
            item.addClass('active').append(part)
        } else {
            item.append('<a href="#" class="dir-link" data-dir="' + path + '">' + part + '</a>')
        }
        breadcrums.append(item)
    });
}
function selectItem(element, checked){
    let dir = $(element).data('dir')
    if(!checked){
        removeFromActive(dir)
    } else {
        if(!multipleMode){
            $('.select-file').prop('checked', false)
            $(element).prop('checked', true)
            activeFiles = [dir]
        } else {
            activeFiles.push(dir)
        }
    }
}
function renderSelected() {
    $('#selectedFiles').empty();
    let controls = $('<div>');
    if (activeFiles.length == 0) return;
    controls.append($('<b>Selected: ' + activeFiles.length + '</b>'))
    if (activeFiles.length == 1) {
        controls.append($('<button class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#renameModal" data-name="' + activeFiles[0] + '" data-dir="' + currentDir + '">Rename</button>'));
        controls.append($('<button class="btn btn-primary ms-2" data-file="' + activeFiles[0] + '">Delete</button>').on('click', function(e) {
            e.preventDefault();
            const file = $(this).data('file');
            deleteItem({ name: file, dir: currentDir })
        }));
        if(pickerMode){
            controls.append($('<button class="btn btn-success ms-2" data-file="' + activeFiles[0] + '">Select</button>').on('click', function(e) {
                e.preventDefault();
                const file = $(this).data('file');
                return onPicked(currentDir+'/'+file)
            }));
        }
    }
    $('#selectedFiles').append(controls);
}
