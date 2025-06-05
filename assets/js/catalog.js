jQuery(document).ready(function($){

    $('.wpb-booking-form').on('submit', function(e){
        e.preventDefault();
        var form = $(this);
        $.post(wpbCatalog.ajax_url, form.serialize(), function(response){
            if(response.success){
                alert('Reserva realizada');
                var modalEl = form.closest('.modal')[0];
                var modal = bootstrap.Modal.getInstance(modalEl);
                if(modal){
                    modal.hide();
                }
              
                form[0].reset();
                location.reload();
            }else{
                var msg = response.data && response.data.message ? response.data.message : 'Error al reservar';
                alert(msg);
            }
        });
    });
});
