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
4. Configura los métodos de pago y la plantilla de correo desde **WPBookingStandar → Ajustes**.

Al activar el plugin se registrará el tipo de contenido **Servicio** con sus categorías, un campo de precio por persona y la **capacidad máxima** disponible. También se crea un tipo de contenido **Reserva** para almacenar las solicitudes realizadas por los clientes junto con nombre, cantidad de personas, precio total, un ID único y estatus de la reserva. Todas las opciones del plugin se agrupan en el menú **WPBookingStandar**, desde donde también se accede a un apartado de ajustes.

Cada servicio puede definirse desde varias cajas de metadatos para facilitar su edición. La galería de imágenes se selecciona con la biblioteca de medios de WordPress y se muestra como miniaturas cuadradas que se amplían al hacer clic.

 El plugin genera automáticamente una página de catálogo sin plantilla del tema donde los servicios se muestran usando **Bootstrap 5** y un encabezado tipo "hero" con animaciones decorativas. El diseño utiliza colores rojo, negro, amarillo y blanco y todo el texto es oscuro para que se lea correctamente. En la parte superior hay un buscador y un filtro por categorías además de un botón para volver al inicio. Cada servicio muestra su categoría, imagen destacada, precio en DOP, la cantidad de cupos restantes y un botón **Reservar** (o un aviso **AGOTADO** si no quedan cupos). Al final se incluye un bloque de información **Servicios Premium** con datos de contacto.

 Al hacer clic en **Reservar** se abre un modal amplio que guía al usuario por cinco pasos: información con galería y video, datos del cliente (nombre, cédula opcional, teléfono y correo), cantidad de personas, método de pago y un resumen final con el costo aplicando descuentos cuando corresponda. Las imágenes de la galería pueden ampliarse al hacer clic para verlas a mayor tamaño y se muestra también el título del servicio. SweetAlert muestra la confirmación y la página se recarga para actualizar los cupos.
Se añadieron animaciones suaves entre pasos del formulario y **SweetAlert** muestra la confirmación de la reserva. Las reservas pueden editarse desde su pantalla de edición incluyendo el estatus y el método de pago. La plantilla de correo se modifica en una pestaña independiente dentro de ajustes utilizando los códigos {name}, {service}, {status} y {total}.
El sistema envía un correo al cliente cuando crea una reserva y cada vez que se actualiza su estatus. El contenido de ese correo puede modificarse con HTML desde **WPBookingStandar → Ajustes**.
Dentro del menú **WPBookingStandar** hay una sección de **Estadísticas** que muestra el total de reservas y las ganancias acumuladas.

