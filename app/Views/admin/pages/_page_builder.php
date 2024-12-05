<div class="container">
    <div id="toolbar" class="d-flex justify-content-start">
        <button class="btn btn-secondary me-2 draggable" data-type="text" draggable="true">Text</button>
        <button class="btn btn-secondary me-2 draggable" data-type="image" draggable="true">Image</button>
        <button class="btn btn-secondary me-2 draggable" data-type="button" draggable="true">Button</button>
    </div>
    <div id="workspace" contenteditable="true">
        <!-- Рабочая область для конструктора страниц -->
    </div>
    <button id="save" class="btn btn-primary mt-3">Save</button>
    <textarea id="output" class="form-control mt-3" rows="5" readonly></textarea>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
        $(document).ready(function() {
            let dropHighlight = $('<div class="drop-highlight"></div>');

            $('.draggable').on('dragstart', function(event) {
                event.originalEvent.dataTransfer.setData('type', $(this).data('type'));
            });

            $('#workspace').on('dragover', function(event) {
                event.preventDefault();
                let offsetY = event.originalEvent.clientY - $(this).offset().top;
                dropHighlight.css('top', offsetY + 'px');
                $(this).append(dropHighlight);
            });

            $('#workspace').on('dragleave', function(event) {
                dropHighlight.detach();
            });

            $('#workspace').on('drop', function(event) {
                event.preventDefault();
                dropHighlight.detach();

                const type = event.originalEvent.dataTransfer.getData('type');
                let element;

                switch (type) {
                    case 'text':
                        element = $('<p contenteditable="true">New Text</p>');
                        break;
                    case 'image':
                        element = $('<img src="https://via.placeholder.com/150" alt="Image" contenteditable="true">');
                        break;
                    case 'button':
                        element = $('<button contenteditable="true" class="btn btn-secondary">New Button</button>');
                        break;
                }

                if (element) {
                    let offsetY = event.originalEvent.clientY - $(this).offset().top;
                    let beforeElement = null;

                    $(this).children().each(function() {
                        if (offsetY < ($(this).position().top + $(this).outerHeight() / 2)) {
                            beforeElement = $(this);
                            return false;
                        }
                    });

                    if (beforeElement) {
                        beforeElement.before(element);
                    } else {
                        $(this).append(element);
                    }
                }
            });

            $('#save').on('click', function() {
                const content = $('#workspace').html();
                $('#output').val(content);
            });
        });
    </script>
    <style>
        #toolbar { margin-bottom: 15px; }
        #workspace { border: 1px solid #ddd; min-height: 300px; padding: 15px; position: relative;  }
        .draggable { cursor: grab; }
        .drop-highlight {
            border-top: 2px dashed #007bff;
            position: absolute;
            width: 100%;
            top: 0;
            left: 0;
        }
    
    </style>