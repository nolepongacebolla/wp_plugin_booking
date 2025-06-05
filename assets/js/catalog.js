jQuery(document).ready(function($){
    $('.wpb-book-button').on('click', function(){
        var id = $(this).data('service-id');
        $('#wpb-modal-' + id).addClass('active');
    });
    $('.wpb-modal-close').on('click', function(){
        $(this).closest('.wpb-modal').removeClass('active');
    });
    $('.wpb-modal').on('click', function(e){
        if($(e.target).is('.wpb-modal')){
            $(this).removeClass('active');
        }
    });

    $('.wpb-booking-form').on('submit', function(e){
        e.preventDefault();
        var form = $(this);
        $.post(wpbCatalog.ajax_url, form.serialize(), function(response){
            if(response.success){
                alert('Reserva realizada');
                form.closest('.wpb-modal').removeClass('active');
                form[0].reset();
                location.reload();
            }else{
                var msg = response.data && response.data.message ? response.data.message : 'Error al reservar';
                alert(msg);
            }
        });
    });
});
