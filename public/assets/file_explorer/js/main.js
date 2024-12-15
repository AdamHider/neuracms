let currentDir = '';
let activeFiles = [];
let filePickerElement = '';
let multipleMode = false
let pickerMode = false
let callbackFunc = () => {}
let onPicked = function(filePath){
    return '/image'+filePath
}
function initFileExplorer(config){
    filePickerElement = config.filePickerElement
    multipleMode = config.multipleMode
    pickerMode = config.pickerMode
    if(config.onPicked){
        onPicked = config.onPicked
    }
    loadFiles();
}
function initControls(){
    $(filePickerElement).find('.button-back').off('click')
    $(filePickerElement).find('.button-back').on('click', (e) => {
        e.preventDefault();
        const button = $(e.delegateTarget);
        const dir = button.data('dir').split('/').slice(0, -1).join('/');
        loadFiles(dir);
    })
    $(filePickerElement).find('.nav-link, .dir-link').off('click')
    $(filePickerElement).find('.nav-link, .dir-link').on('click', function(e) {
        e.preventDefault();
        const dir = $(this).data('dir');
        loadFiles(dir);
    })
    $(filePickerElement).find('.select-file').off('click')
    $(filePickerElement).find('.select-file').on('change', (e) => {
        e.preventDefault();
        const element = e.delegateTarget
        const checked = $(element).prop('checked')
        selectItem(element, checked)
        renderSelected()
    }).on('click', (e) => {
        e.stopPropagation()
    })
    $(filePickerElement).find('#uploadForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        upload(formData)
    });
    $(filePickerElement).find('#createDirForm').submit(function(e) {
        e.preventDefault();
        const data = $(this).serialize()
        createDirectory(data)
    });
    $(filePickerElement).find('#renameModal').on('show.bs.modal', function(e) {
        const button = $(e.relatedTarget);
        const name = button.data('name');
        $('#rename-oldName').val(name);
        $('#rename-newName').val(name);
        $('#rename-dir').val(currentDir);
    });
    $(filePickerElement).find('#renameForm').submit(function(e) {
        e.preventDefault();
        const data = $(this).serialize()
        rename(data)
    });
}
function loadFiles(dir = '') {
    currentDir = dir;
    $.get(basePath+'list', { dir: currentDir }, function(html) {
        $(filePickerElement).empty()
        $(filePickerElement).html(html);
        //updateBreadcrumbs(data.currentDir);
        activeFiles = []
        //renderSelected()
        initControls()
    });
}
function upload(formData){
    $.ajax({
        url: basePath+'upload',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function(response) {
            loadFiles(currentDir);
            $('#uploadModal').modal('hide');
            //alert(response.message);
        },
        dataType: 'json'
    });
}
function createDirectory(data){
    $.post(basePath+'create-directory', data, function(response) {
        loadFiles(currentDir);
        $('#createDirModal').modal('hide');
        //alert(response.message);
    }, 'json');
}
function rename(data){
    $.post(basePath+'rename', data, function(response) {
        loadFiles(currentDir);
        $('#renameModal').modal('hide');
        //alert(response.message);
    }, 'json');
}
function deleteItem(data){
    $.post(basePath+'delete', data, function(response) {
        loadFiles(currentDir);
        //alert(response.message);
    }, 'json');
}
function removeFromActive(dir){
    let newActive = []
    activeFiles.forEach((item) => {
        if(item !== dir) newActive.push(dir)
    })
    activeFiles = newActive
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