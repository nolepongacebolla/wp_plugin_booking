jQuery(document).ready(function($){
    $('.wpb-booking-form').each(function(){
        var form = $(this);
        var steps = form.find('.wpb-step');
        var current = 0;
        function showStep(i){
            steps.removeClass('active').hide().eq(i).addClass('active').show();
        }
        showStep(0);

        form.on('click', '.wpb-next', function(e){
            e.preventDefault();
            if(current < steps.length - 1){
                current++;
                if(form.find('.wpb-step').eq(current).hasClass('wpb-summary-step')){
                    var price = parseFloat(form.data('price')) || 0;
                    var persons = parseInt(form.find('input[name="persons"]').val()) || 1;
                    var total = price * persons;
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
            $.post(wpbCatalog.ajax_url, form.serialize(), function(response){
                if(response.success){
                    steps.hide();
                    form.find('.wpb-success').show();
                } else {
                    var msg = response.data && response.data.message ? response.data.message : 'Error al reservar';
                    form.find('.wpb-error').text(msg).show();
                }
            });
        });

    });
});
