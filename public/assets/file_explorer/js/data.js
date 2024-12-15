
function loadFiles(dir = '') {
    currentDir = dir;
    $(filePickerElement).find('#media-files').empty()
    $.get(basePath+'list', { dir: currentDir }, function(html) {
        $(filePickerElement).find('#media-files').html(html);
        //updateBreadcrumbs(data.currentDir);
        $(filePickerElement).find('#create-dir').val(currentDir); 
        $(filePickerElement).find('#upload-dir').val(currentDir); 
        $(filePickerElement).find('.button-back').data('dir', currentDir);
        activeFiles = []
        //renderSelected()
        initControls()
    });
}
function upload(formData){
    formData.append('dir', currentDir);
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