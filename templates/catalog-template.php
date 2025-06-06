<?php
/**
 * Template for Booking Catalog page.
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <?php wp_head(); ?>
    <style>
        /* Estilos adicionales que necesitan estar en el head */
        .wpb-step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }
        .wpb-step-indicator::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e9ecef;
            z-index: 1;
        }
        .wpb-step-indicator .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        .wpb-step-indicator .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        .wpb-step-indicator .step-label {
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: 500;
            text-align: center;
        }
        .wpb-step-indicator .step.completed .step-number,
        .wpb-step-indicator .step.current .step-number {
            background: #3498db;
            color: white;
        }
        .wpb-step-indicator .step.completed .step-label,
        .wpb-step-indicator .step.current .step-label {
            color: #3498db;
            font-weight: 600;
        }
    </style>
</head>
<body <?php body_class( 'wpb-catalog-page' ); ?>>
    <?php echo do_shortcode( '[booking_catalog]' ); ?>

    <!-- Modal de Reserva -->
    <div class="modal fade" id="wpb-booking-modal" tabindex="-1" aria-labelledby="wpbBookingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="wpbBookingModalLabel">Reservar Servicio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <!-- Indicador de pasos -->
                    <div class="wpb-step-indicator">
                        <div class="step" data-step="1">
                            <div class="step-number">1</div>
                            <div class="step-label">Datos</div>
                        </div>
                        <div class="step" data-step="2">
                            <div class="step-number">2</div>
                            <div class="step-label">Fecha y Hora</div>
                        </div>
                        <div class="step" data-step="3">
                            <div class="step-number">3</div>
                            <div class="step-label">Confirmar</div>
                        </div>
                    </div>

                    <!-- Mensajes de error -->
                    <div class="wpb-message wpb-error-message" style="display: none;">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <span class="wpb-message-text"></span>
                    </div>

                    <!-- Formulario de reserva -->
                    <form class="wpb-booking-form" method="post">
                        <input type="hidden" name="action" value="wpb_create_booking">
                        <input type="hidden" name="service_id" value="">
                        <?php wp_nonce_field('wpb_booking_nonce', 'nonce'); ?>

                        <!-- Paso 1: Información personal -->
                        <div class="wpb-step active" data-step="1">
                            <h4 class="wpb-step-title">Información Personal</h4>
                            <div class="wpb-form-group">
                                <label for="wpb-name">Nombre completo <span class="text-danger">*</span></label>
                                <input type="text" class="wpb-form-control" id="wpb-name" name="name" required>
                                <div class="invalid-feedback">Por favor ingresa tu nombre completo</div>
                            </div>
                            <div class="wpb-form-group">
                                <label for="wpb-email">Correo electrónico <span class="text-danger">*</span></label>
                                <input type="email" class="wpb-form-control" id="wpb-email" name="email" required>
                                <div class="invalid-feedback">Por favor ingresa un correo electrónico válido</div>
                            </div>
                            <div class="wpb-form-group">
                                <label for="wpb-phone">Teléfono</label>
                                <input type="tel" class="wpb-form-control" id="wpb-phone" name="phone">
                            </div>
                            <div class="wpb-form-group">
                                <label for="wpb-persons">Número de personas <span class="text-danger">*</span></label>
                                <input type="number" class="wpb-form-control" id="wpb-persons" name="persons" min="1" value="1" required>
                                <div class="invalid-feedback">Por favor ingresa un número válido de personas</div>
                            </div>
                            <div class="wpb-form-nav">
                                <button type="button" class="wpb-btn wpb-btn-outline wpb-btn-prev" style="visibility: hidden;">
                                    <i class="fas fa-arrow-left me-2"></i>Anterior
                                </button>
                                <button type="button" class="wpb-btn wpb-btn-primary wpb-btn-next">
                                    Siguiente <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Paso 2: Fecha y hora -->
                        <div class="wpb-step" data-step="2">
                            <h4 class="wpb-step-title">Selecciona Fecha y Hora</h4>
                            <div class="wpb-form-group">
                                <label for="wpb-date">Fecha <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                                    <input type="text" class="wpb-form-control" id="wpb-date" name="date" readonly required>
                                </div>
                                <div class="invalid-feedback">Por favor selecciona una fecha</div>
                            </div>
                            <div class="wpb-form-group">
                                <label for="wpb-time">Hora <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="far fa-clock"></i></span>
                                    <select class="wpb-form-control" id="wpb-time" name="time" required>
                                        <option value="">Selecciona una hora</option>
                                        <option value="09:00">09:00 AM</option>
                                        <option value="09:30">09:30 AM</option>
                                        <option value="10:00">10:00 AM</option>
                                        <option value="10:30">10:30 AM</option>
                                        <option value="11:00">11:00 AM</option>
                                        <option value="11:30">11:30 AM</option>
                                        <option value="12:00">12:00 PM</option>
                                        <option value="12:30">12:30 PM</option>
                                        <option value="13:00">01:00 PM</option>
                                        <option value="13:30">01:30 PM</option>
                                        <option value="14:00">02:00 PM</option>
                                        <option value="14:30">02:30 PM</option>
                                        <option value="15:00">03:00 PM</option>
                                        <option value="15:30">03:30 PM</option>
                                        <option value="16:00">04:00 PM</option>
                                        <option value="16:30">04:30 PM</option>
                                        <option value="17:00">05:00 PM</option>
                                        <option value="17:30">05:30 PM</option>
                                        <option value="18:00">06:00 PM</option>
                                    </select>
                                </div>
                                <div class="invalid-feedback">Por favor selecciona una hora</div>
                            </div>
                            <div class="wpb-form-nav">
                                <button type="button" class="wpb-btn wpb-btn-outline wpb-btn-prev">
                                    <i class="fas fa-arrow-left me-2"></i>Anterior
                                </button>
                                <button type="button" class="wpb-btn wpb-btn-primary wpb-btn-next">
                                    Siguiente <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Paso 3: Resumen y confirmación -->
                        <div class="wpb-step wpb-summary-step" data-step="3">
                            <h4 class="wpb-step-title">Confirma tu reserva</h4>
                            <div class="wpb-summary-details">
                                <div class="wpb-summary-item">
                                    <span class="wpb-summary-label">Servicio:</span>
                                    <span class="wpb-summary-value wpb-summary-service"></span>
                                </div>
                                <div class="wpb-summary-item">
                                    <span class="wpb-summary-label">Nombre:</span>
                                    <span class="wpb-summary-value wpb-summary-name"></span>
                                </div>
                                <div class="wpb-summary-item">
                                    <span class="wpb-summary-label">Email:</span>
                                    <span class="wpb-summary-value wpb-summary-email"></span>
                                </div>
                                <div class="wpb-summary-item">
                                    <span class="wpb-summary-label">Teléfono:</span>
                                    <span class="wpb-summary-value wpb-summary-phone"></span>
                                </div>
                                <div class="wpb-summary-item">
                                    <span class="wpb-summary-label">Fecha:</span>
                                    <span class="wpb-summary-value wpb-summary-date"></span>
                                </div>
                                <div class="wpb-summary-item">
                                    <span class="wpb-summary-label">Hora:</span>
                                    <span class="wpb-summary-value wpb-summary-time"></span>
                                </div>
                                <div class="wpb-summary-item">
                                    <span class="wpb-summary-label">Personas:</span>
                                    <span class="wpb-summary-value wpb-summary-persons"></span>
                                </div>
                                <div class="wpb-summary-item">
                                    <span class="wpb-summary-label">Precio por persona:</span>
                                    <span class="wpb-summary-value wpb-summary-price"></span>
                                </div>
                                <div class="wpb-summary-total">
                                    <span>Total a pagar:</span>
                                    <span class="wpb-summary-value wpb-summary-total"></span>
                                </div>
                            </div>
                            <div class="wpb-form-nav">
                                <button type="button" class="wpb-btn wpb-btn-outline wpb-btn-prev">
                                    <i class="fas fa-arrow-left me-2"></i>Anterior
                                </button>
                                <button type="submit" class="wpb-btn wpb-btn-primary wpb-btn-submit">
                                    Confirmar Reserva
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Mensaje de éxito -->
                    <div class="wpb-message wpb-success-message" style="display: none;">
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <i class="fas fa-check-circle" style="font-size: 4rem; color: #27ae60;"></i>
                            </div>
                            <h4>¡Reserva Confirmada!</h4>
                            <p class="mb-0">Hemos recibido tu solicitud de reserva. Te hemos enviado un correo de confirmación con los detalles.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.es.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.js"></script>
    <?php wp_footer(); ?>
</body>
</html>
