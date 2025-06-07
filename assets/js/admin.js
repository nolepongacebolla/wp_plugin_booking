jQuery(function($){
    var frame;
    $('#wpb_gallery_button').on('click', function(e){
        e.preventDefault();
        if(frame){ frame.open(); return; }
        frame = wp.media({
            title: wpbGallery.select,
            button: { text: wpbGallery.use },
            multiple: true,
            library: { type: 'image' }
        });
        frame.on('select', function(){
            var ids = frame.state().get('selection').map(function(attachment){
                attachment = attachment.toJSON();
                return attachment.id;
            }).join(',');
            $('#wpb_gallery').val(ids);
            var preview = $('#wpb_gallery_preview').empty();
            frame.state().get('selection').each(function(att){
                att = att.toJSON();
                if(att.sizes && att.sizes.thumbnail){
                    preview.append('<img src="'+att.sizes.thumbnail.url+'" style="margin-right:5px;"/>');
                } else {
                    preview.append('<img src="'+att.url+'" style="margin-right:5px; width:80px;"/>');
                }
            });
        });
        frame.open();
    });

    $('#wpb-add-item').on('click', function(e){
        e.preventDefault();
        var index = $('.wpb-items-table tbody tr').length;
        var tmpl = wp.template('wpb-item-row');
        $('.wpb-items-table tbody').append(tmpl({i:index}));
    });

    $('.wpb-items-table').on('click', '.wpb-remove-item', function(e){
        e.preventDefault();
        $(this).closest('tr').remove();
    });

});
