$(document).ready(function() {
    let currentDir = '';

    loadFiles();

    function loadFiles(dir = '') {
        currentDir = dir;
        $.get('file-explorer/list', { dir: currentDir }, function(data) {
            $('#media-files').html(renderFileList(data.files, data.currentDir));
            updateBreadcrumbs(data.currentDir);
            $('#create-dir').val(currentDir); // Обновление текущей директории в форме создания
            $('#upload-dir').val(currentDir); // Обновление текущей директории в форме загрузки
        });
    }

    function renderFileList(files, currentDir) {
        let html = '<ul class="list-group">';
        if (currentDir) {
            html += `<li class="list-group-item"><a href="#" class="nav-link dir-link" data-dir="${currentDir.split('/').slice(0, -1).join('/')}">.. (Parent Directory)</a></li>`;
        }
        files.forEach(function(file) {
            if (file.endsWith('\\')) {
                html += `<li class="list-group-item"><a href="#" class="nav-link dir-link" data-dir="${currentDir}/${file.slice(0, -1)}">[DIR] ${file}</a><button class="btn btn-danger btn-sm float-end delete-dir" data-name="${file}">Delete</button><button class="btn btn-sm btn-warning float-end me-2" data-bs-toggle="modal" data-bs-target="#renameModal" data-name="${file}" data-dir="${currentDir}">Rename</button></li>`;
            } else {
                if (['jpg', 'jpeg', 'png', 'gif'].includes(file.split('.').pop().toLowerCase())) {
                    html += `<li class="list-group-item"><img src="/image/${currentDir}/${file}" alt="${file}" class="img-thumbnail" style="max-width: 100px;">${file}<button class="btn btn-danger btn-sm float-end delete-file" data-name="${file}">Delete</button><button class="btn btn-sm btn-warning float-end me-2" data-bs-toggle="modal" data-bs-target="#renameModal" data-name="${file}" data-dir="${currentDir}">Rename</button></li>`;
                } else {
                    html += `<li class="list-group-item">${file}<button class="btn btn-danger btn-sm float-end delete-file" data-name="${file}">Delete</button><button class="btn btn-sm btn-warning float-end me-2" data-bs-toggle="modal" data-bs-target="#renameModal" data-name="${file}" data-dir="${currentDir}">Rename</button></li>`;
                }
            }
        });
        html += '</ul>';
        return html;
    }

    function updateBreadcrumbs(currentDir) {
        let parts = currentDir ? currentDir.split('/') : [];
        let html = '<li class="breadcrumb-item"><a href="#" class="nav-link dir-link" data-dir="">Home</a></li>';
        let path = '';
        parts.forEach(function(part, index) {
            path += (index > 0 ? '/' : '') + part;
            html += `<li class="breadcrumb-item"><a href="#" class="nav-link dir-link" data-dir="${path}">${part}</a></li>`;
        });
        $('#breadcrumbs').html(html);
    }

    $('#media-files').on('click', '.dir-link', function(e) {
        e.preventDefault();
        const dir = $(this).data('dir');
        loadFiles(dir);
    });

    $('#breadcrumbs').on('click', '.dir-link', function(e) {
        e.preventDefault();
        const dir = $(this).data('dir');
        loadFiles(dir);
    });

    $('#media-files').on('click', '.delete-dir, .delete-file', function(e) {
        e.preventDefault();
        const name = $(this).data('name');
        $.post('admin/file-explorer/delete', { name: name, dir: currentDir }, function(response) {
            loadFiles(currentDir);
            alert(response.message);
        }, 'json');
    });

    $('#renameModal').on('show.bs.modal', function(e) {
        const button = $(e.relatedTarget);
        const name = button.data('name');
        $('#rename-oldName').val(name);
        $('#rename-dir').val(currentDir);
    });

    $('#renameForm').submit(function(e) {
        e.preventDefault();
        $.post('file-explorer/rename', $(this).serialize(), function(response) {
            loadFiles(currentDir);
            $('#renameModal').modal('hide');
            alert(response.message);
        }, 'json');
    });

    $('#uploadForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('dir', currentDir);
        $.ajax({
            url: 'file-explorer/upload',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                loadFiles(currentDir);
                $('#uploadModal').modal('hide');
                alert(response.message);
            },
            dataType: 'json'
        });
    });

    $('#createDirForm').submit(function(e) {
        e.preventDefault();
        $.post('file-explorer/create-directory', $(this).serialize(), function(response) {
            loadFiles(currentDir);
            $('#createDirModal').modal('hide');
            alert(response.message);
        }, 'json');
    });
});