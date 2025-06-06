jQuery(document).ready(function($){
    $('.wpb-booking-form').each(function(){
        var form = $(this);
        var steps = form.find('.wpb-step');
        var current = 0;
        var discount = parseFloat(form.data('discount')) || 0;
        var minDisc = parseInt(form.data('discountmin')) || 0;
        function showStep(i){
            steps.removeClass('active').hide();
            steps.eq(i).addClass('active').show();
        }
        showStep(0);

        form.on('click', '.wpb-next', function(e){
            e.preventDefault();
            var step = steps.eq(current);
            var valid = true;
            step.find(':input[required]').each(function(){
                if(!this.checkValidity()){
                    this.reportValidity();
                    valid = false;
                    return false;
                }
            });
            if(valid && current < steps.length - 1){
                current++;
                if(form.find('.wpb-step').eq(current).hasClass('wpb-summary-step')){
                    var price = parseFloat(form.data('price')) || 0;
                    var persons = parseInt(form.find('input[name="persons"]').val()) || 1;
                    var total = price * persons;
                    if(discount && persons >= minDisc){
                        total = total * (1 - discount/100);
                    }
                    form.find('.wpb-summary-name').text(form.find('input[name="name"]').val());
                    form.find('.wpb-summary-email').text(form.find('input[name="email"]').val());
                    form.find('.wpb-summary-persons').text(persons);
                    form.find('.wpb-summary-total').text(total.toFixed(2));
                }
                showStep(current);
            }
        });

        form.on('click', '.wpb-prev', function(e){
            e.preventDefault();
            if(current > 0){
                current--;
                showStep(current);
            }
        });

        form.on('submit', function(e){
            e.preventDefault();
            form.find(':hidden[required]').prop('required', false);
            var btn = form.find('.wpb-confirm');
            var spinner = form.find('.wpb-processing');
            btn.prop('disabled', true);
            spinner.show();
            $.post(wpbCatalog.ajax_url, form.serialize(), function(response){
                if(response.success){
                    Swal.fire({
                        icon: 'success',
                        title: '¡Reserva realizada con éxito!'
                    }).then(function(){
                        location.reload();
                    });
                } else {
                    var msg = response.data && response.data.message ? response.data.message : 'Error al reservar';
                    Swal.fire('Error', msg, 'error');
                }
            }).always(function(){
                spinner.hide();
                btn.prop('disabled', false);
            });
        });
    });

    $('body').on('click', '.wpb-expand-image', function(e){
        e.preventDefault();
        var src = $(this).data('full');
        if(!src) return;
        var box = $('<div class="wpb-lightbox" style="display:none"><img src="'+src+'"/></div>');
        $('body').append(box);
        box.css('display','flex').hide().fadeIn(200);
    });

    $('body').on('click', '.wpb-lightbox', function(){
        $(this).fadeOut(200, function(){ $(this).remove(); });
    });
});
