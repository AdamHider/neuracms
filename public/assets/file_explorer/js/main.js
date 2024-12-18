let currentDir = '';
let activeFiles = [];
let filePickerElement = '';
let multipleMode = false
let pickerMode = false
let onPicked = function(filePath){
    return '/image/'+filePath
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
    activeFiles = []
    currentDir = dir;
    $.get(basePath+'list', { dir: currentDir }, function(html) {
        $(filePickerElement).empty()
        $(filePickerElement).html(html);
        renderSelected()
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
        },
        dataType: 'json'
    });
}
function createDirectory(data){
    $.post(basePath+'create-directory', data, function(response) {
        loadFiles(currentDir);
        $('#createDirModal').modal('hide');
    }, 'json');
}
function rename(data){
    $.post(basePath+'rename', data, function(response) {
        loadFiles(currentDir);
        $('#renameModal').modal('hide');
    }, 'json');
}
function deleteItem(data){
    $.post(basePath+'delete', data, function(response) {
        loadFiles(currentDir);
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
    let selectedFiles = $('#selectedFiles')
    if (activeFiles.length == 0) return selectedFiles.addClass('invisible');
    if (activeFiles.length == 1) {
        selectedFiles.find('b').html(activeFiles.length)
        selectedFiles.find('[data-file]').attr('data-file', activeFiles[0])
        selectedFiles.find('[data-dir]').attr('data-dir', currentDir)
        selectedFiles.find('.delete-file').on('click', function(e) {
            e.preventDefault();
            const file = $(this).data('file');
            deleteItem({ name: file, dir: currentDir })
        });
        if(pickerMode){
            selectedFiles.find('.select-file').on('click', function(e) {
                e.preventDefault();
                const file = $(this).data('file');
                if(currentDir == '') {
                    currentDir = '/image'
                }  else {
                    currentDir = '/image/'+currentDir
                }
                return onPicked(currentDir+'/'+file)
            });
        }
    }
    selectedFiles.removeClass('invisible');
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