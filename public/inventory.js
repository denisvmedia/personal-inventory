'use strict';

$(document).ready(function() {
    $('select.tags').select2({
        tags: true,
        tokenSeparators: [',']
    });

    $('[data-confirm]').on('click', function(e) {
        if (window.confirm($(this).data('confirm'))) {
            return true;
        } else {
            e.preventDefault();
            return false;
        }
    });

    $('#inventory_item_images').on('change', function(e) {
        $('#new-images').empty();
        if (this.files.length > 0) {
            for (let i = 0; i < this.files.length; i++) {
                let img = document.createElement("img");
                $(img).addClass('img-thumbnail');
                $("#new-images").append(img);
                img.src = URL.createObjectURL(this.files[i]);
            }
        }
    });

    $('#new-images').on('click', '.img-thumbnail', function (e) {
        const dt = new DataTransfer()
        const input = document.getElementById('inventory_item_images');
        const { files } = input;

        const index = $('#new-images > .img-thumbnail').index(this);

        for (let i = 0; i < files.length; i++) {
            const file = files[i]
            if (index !== i) {
                dt.items.add(file) // here you exclude the file. thus removing it.
            }
        }

        input.files = dt.files // Assign the updates list

        $(this).remove();
    });
});
