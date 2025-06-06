jQuery(document).ready(function($) {
    // Inicialización del modal de reserva
    function initBookingModal(serviceId) {
        const modal = $('#wpb-booking-modal');
        const form = modal.find('.wpb-booking-form');
        const steps = form.find('.wpb-step');
        let currentStep = 0;
        const serviceCard = $(`[data-service-id="${serviceId}"]`);
        const serviceTitle = serviceCard.data('service-title');
        const servicePrice = parseFloat(serviceCard.data('service-price'));
        const remaining = parseInt(serviceCard.data('remaining'));
        
        // Actualizar información del servicio en el modal
        modal.find('.wpb-service-title').text(serviceTitle);
        modal.find('.wpb-service-price').text('$' + servicePrice.toFixed(2));
        modal.find('input[name="service_id"]').val(serviceId);
        
        // Función para mostrar un paso específico
        function showStep(stepIndex) {
            // Validar el paso actual antes de avanzar
            if (stepIndex > currentStep && !validateStep(currentStep)) {
                return;
            }
            
            // Ocultar todos los pasos
            steps.removeClass('active').hide();
            
            // Mostrar el paso actual
            const step = steps.eq(stepIndex);
            step.addClass('active').show();
            
            // Actualizar indicador de pasos
            updateStepIndicator(stepIndex);
            
            // Actualizar botones de navegación
            updateNavigationButtons(stepIndex);
            
            // Si es el paso de resumen, actualizar la información
            if (step.hasClass('wpb-summary-step')) {
                updateSummaryStep();
            }
            
            currentStep = stepIndex;
            
            // Animación
            step.css({opacity: 0}).animate({opacity: 1}, 300);
        }
        
        // Función para validar el paso actual
        function validateStep(stepIndex) {
            const currentStep = steps.eq(stepIndex);
            let isValid = true;
            
            // Validar campos requeridos
            currentStep.find('[required]').each(function() {
                const field = $(this);
                if (!field.val().trim()) {
                    field.addClass('is-invalid');
                    isValid = false;
                } else {
                    field.removeClass('is-invalid');
                    // Validar email
                    if (field.attr('type') === 'email' && !isValidEmail(field.val())) {
                        field.addClass('is-invalid');
                        isValid = false;
                    }
                }
            });
            
            // Validar número de personas
            if (stepIndex === 0) {
                const persons = parseInt($('input[name="persons"]').val()) || 0;
                if (persons < 1 || persons > remaining) {
                    $('input[name="persons"]').addClass('is-invalid');
                    isValid = false;
                }
            }
            
            return isValid;
        }
        
        // Función para validar email
        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        // Función para actualizar el indicador de pasos
        function updateStepIndicator(stepIndex) {
            const indicators = $('.wpb-step-indicator');
            indicators.removeClass('active current');
            
            indicators.each(function(index) {
                if (index < stepIndex) {
                    $(this).addClass('completed');
                } else if (index === stepIndex) {
                    $(this).addClass('current');
                } else {
                    $(this).removeClass('completed');
                }
            });
        }
        
        // Función para actualizar los botones de navegación
        function updateNavigationButtons(stepIndex) {
            const prevButton = form.find('.wpb-btn-prev');
            const nextButton = form.find('.wpb-btn-next');
            const submitButton = form.find('.wpb-btn-submit');
            
            // Mostrar/ocultar botones según el paso
            if (stepIndex === 0) {
                prevButton.hide();
                nextButton.show();
                submitButton.hide();
            } else if (stepIndex === steps.length - 1) {
                prevButton.show();
                nextButton.hide();
                submitButton.show();
            } else {
                prevButton.show();
                nextButton.show();
                submitButton.hide();
            }
        }
        
        // Función para actualizar el paso de resumen
        function updateSummaryStep() {
            const persons = parseInt($('input[name="persons"]').val()) || 1;
            const total = servicePrice * persons;
            
            $('.wpb-summary-service').text(serviceTitle);
            $('.wpb-summary-name').text($('input[name="name"]').val());
            $('.wpb-summary-email').text($('input[name="email"]').val());
            $('.wpb-summary-phone').text($('input[name="phone"]').val() || 'No especificado');
            $('.wpb-summary-date').text($('input[name="date"]').val() || 'No especificada');
            $('.wpb-summary-time').text($('select[name="time"]').val() || 'No especificada');
            $('.wpb-summary-persons').text(persons);
            $('.wpb-summary-price').text('$' + servicePrice.toFixed(2));
            $('.wpb-summary-total').text('$' + total.toFixed(2));
        }
        
        // Manejadores de eventos
        form.on('click', '.wpb-btn-next', function(e) {
            e.preventDefault();
            if (currentStep < steps.length - 1) {
                showStep(currentStep + 1);
            }
        });
        
        form.on('click', '.wpb-btn-prev', function(e) {
            e.preventDefault();
            if (currentStep > 0) {
                showStep(currentStep - 1);
            }
        });
        
        // Envío del formulario
        form.on('submit', function(e) {
            e.preventDefault();
            
            // Mostrar carga
            const submitButton = form.find('.wpb-btn-submit');
            const originalText = submitButton.html();
            submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...');
            
            // Enviar datos por AJAX
            $.ajax({
                url: wpbCatalog.ajax_url,
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Mostrar mensaje de éxito
                        form.hide();
                        $('.wpb-success-message').show();
                        
                        // Cerrar el modal después de 3 segundos
                        setTimeout(function() {
                            modal.modal('hide');
                            // Recargar la página para actualizar la disponibilidad
                            location.reload();
                        }, 3000);
                    } else {
                        // Mostrar mensaje de error
                        const errorMessage = response.data && response.data.message 
                            ? response.data.message 
                            : 'Ocurrió un error al procesar tu reserva. Por favor, inténtalo de nuevo.';
                        
                        $('.wpb-error-message')
                            .text(errorMessage)
                            .show()
                            .delay(5000)
                            .fadeOut();
                    }
                },
                error: function() {
                    $('.wpb-error-message')
                        .text('Error de conexión. Por favor, verifica tu conexión e inténtalo de nuevo.')
                        .show()
                        .delay(5000)
                        .fadeOut();
                },
                complete: function() {
                    submitButton.prop('disabled', false).html(originalText);
                }
            });
        });
        
        // Inicializar datepicker si existe
        if ($.fn.datepicker) {
            $('input[name="date"]').datepicker({
                minDate: 0,
                dateFormat: 'dd/mm/yy',
                beforeShowDay: function(date) {
                    // Ejemplo: deshabilitar domingos
                    const day = date.getDay();
                    return [day !== 0, ''];
                }
            });
        }
        
        // Inicializar timepicker si existe
        if ($.fn.timepicker) {
            $('select[name="time"]').timepicker({
                timeFormat: 'HH:mm',
                interval: 30,
                minTime: '09:00',
                maxTime: '21:00',
                defaultTime: '12:00',
                startTime: '09:00',
                dynamic: false,
                dropdown: true,
                scrollbar: true
            });
        }
        
        // Mostrar el modal
        modal.modal('show');
        
        // Iniciar con el primer paso
        showStep(0);
    }
    
    // Manejador para el botón de reserva
    $(document).on('click', '.wpb-book-now', function(e) {
        e.preventDefault();
        const serviceId = $(this).data('service-id');
        initBookingModal(serviceId);
    });
    
    // Cerrar el modal y reiniciar el formulario
    $('#wpb-booking-modal').on('hidden.bs.modal', function() {
        const modal = $(this);
        const form = modal.find('.wpb-booking-form');
        
        // Reiniciar el formulario
        form[0].reset();
        form.find('.wpb-step').removeClass('active').hide();
        form.find('.wpb-success-message').hide();
        form.show();
        
        // Restablecer indicadores de pasos
        $('.wpb-step-indicator').removeClass('completed current');
    });
    
    // Validación en tiempo real
    $(document).on('input', 'input[required]', function() {
        const input = $(this);
        if (input.val().trim()) {
            input.removeClass('is-invalid');
            
            // Validación de email
            if (input.attr('type') === 'email' && !isValidEmail(input.val())) {
                input.addClass('is-invalid');
            }
        }
    });
    
    // Función auxiliar para validar email
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
});
