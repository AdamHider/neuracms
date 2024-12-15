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
        $('.button-back').off('click')
        $('.button-back').on('click', (e) => {
            e.preventDefault();
            const button = $(e.delegateTarget);
            const dir = button.data('dir').split('/').slice(0, -1).join('/');
            loadFiles(dir);
        })
        $('.nav-link, .dir-link').on('click', function(e) {
            e.preventDefault();
            const dir = $(this).data('dir');
            loadFiles(dir);
        })
        $('.select-file').on('change', (e) => {
            e.preventDefault();
            const element = e.delegateTarget
            const checked = $(element).prop('checked')
            selectItem(element, checked)
            renderSelected()
        }).on('click', (e) => {
            e.stopPropagation()
        })
    }
    $('#uploadForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        upload(formData)
    });
    $('#createDirForm').submit(function(e) {
        e.preventDefault();
        const data = $(this).serialize()
        createDirectory(data)
    });
    $('#renameModal').on('show.bs.modal', function(e) {
        const button = $(e.relatedTarget);
        const name = button.data('name');
        $('#rename-oldName').val(name);
        $('#rename-newName').val(name);
        $('#rename-dir').val(currentDir);
    });
    $('#renameForm').submit(function(e) {
        e.preventDefault();
        const data = $(this).serialize()
        rename(data)
    });