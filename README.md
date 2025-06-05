# WP Plugin Booking

Este repositorio contiene la estructura básica de un plugin para WordPress orientado a WooCommerce. Al activarlo se crea una página llamada **Catálogo de Reservas** que usa el shortcode `[booking_catalog]` para mostrar los servicios registrados.

## Estructura

- `wp-plugin-booking.php` – Archivo principal del plugin.
- `includes/` – Archivos PHP de soporte.
- `assets/` – Recursos estáticos como JavaScript y CSS.
- `languages/` – Archivos de traducción.
- `templates/` – Plantillas de salida.
- `uninstall.php` – Lógica de limpieza durante la desinstalación.

## Instalación

1. Copia este directorio en la carpeta `wp-content/plugins` de tu instalación de WordPress.
2. Asegúrate de tener **WooCommerce** activo.
3. Activa *WP Plugin Booking* desde el panel de administración de WordPress.

Al activar el plugin se registrará el tipo de contenido **Servicio** con sus categorías, un campo de precio por persona y la **capacidad máxima** disponible. También se crea un tipo de contenido **Reserva** para almacenar las solicitudes realizadas por los clientes junto con nombre, cantidad de personas, precio total, un ID único y estatus de la reserva.
El plugin genera automáticamente una página de catálogo sin plantilla del tema donde los servicios se muestran con un diseño elegante en colores rojo, negro, amarillo y blanco. En la parte superior hay un buscador y un filtro por categorías además de un botón para volver al inicio. Cada servicio muestra su imagen destacada, precio en DOP, la cantidad de cupos restantes y un botón **Reservar** (o un aviso **AGOTADO** si no quedan cupos). El botón abre un modal con un formulario para ingresar nombre y correo y seleccionar el número de personas.

El plugin genera automáticamente una página de catálogo sin plantilla del tema donde los servicios se muestran con un diseño elegante en colores rojo, negro, amarillo y blanco. Cada servicio incluye su imagen destacada, título, costo por persona en pesos dominicanos y un botón **Reservar** que abre un modal con un formulario. Las reservas se guardan como entradas del tipo **Reserva** accesibles desde el área de administración.
