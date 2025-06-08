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

    // Single image selector for catalog background
    var imgFrame;
    $('body').on('click', '.wpb-select-image', function(e){
        e.preventDefault();
        var target = $('#'+$(this).data('target'));
        if(imgFrame){ imgFrame.open(); return; }
        imgFrame = wp.media({
            title: wpbGallery.select,
            button: { text: wpbGallery.use },
            library: { type: 'image' },
            multiple: false
        });
        imgFrame.on('select', function(){
            var attachment = imgFrame.state().get('selection').first().toJSON();
            target.val(attachment.url).trigger('change');
        });
        imgFrame.open();
    });

    function updateCatalogPreview(){
        var preview = $('#wpb-catalog-preview');
        if(!preview.length) return;
        preview.find('.preview-title').text($('#wpb_cat_title_text').val());
        preview.find('.preview-subtitle').text($('#wpb_cat_subtitle_text').val());
        preview.find('.preview-btn').text($('#wpb_cat_btn_text').val());
        preview.find('.preview-title').css({
            'font-family': $('#wpb_cat_title_font').val(),
            'font-size': $('#wpb_cat_title_size').val()+'px',
            'color': $('#wpb_cat_title_color').val()
        });
        preview.find('.preview-subtitle').css({
            'font-size': $('#wpb_cat_subtitle_size').val()+'px',
            'color': $('#wpb_cat_subtitle_color').val(),
            'text-align': $('#wpb_cat_subtitle_align').val()
        });
        preview.find('.preview-btn').css({
            'background-color': $('#wpb_cat_btn_color').val(),
            'border-color': $('#wpb_cat_btn_color').val(),
            'border-radius': $('#wpb_cat_btn_radius').val()+'px'
        });
        var type = $('#wpb_cat_bg_type').val();
        if(type === 'color'){
            preview.css('background', $('#wpb_cat_bg_color').val());
        } else if(type === 'image'){
            var url = $('#wpb_cat_bg_image').val();
            preview.css({ 'background':'url('+url+') center/cover no-repeat' });
        } else {
            preview.css('background','#fff');
        }
    }

    $('body').on('input change', 'input[id^="wpb_cat_"], select[id^="wpb_cat_"]', updateCatalogPreview);
    updateCatalogPreview();
});
