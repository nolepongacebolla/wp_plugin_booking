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

 Cada servicio puede definirse desde varias cajas de metadatos para indicar precio, capacidad, fecha de inicio y textos de apoyo. También se pueden introducir los bloques **Incluye** y **Términos y condiciones** que aparecerán en el modal de reserva. La galería de imágenes se selecciona con la biblioteca de medios de WordPress y se muestra como miniaturas cuadradas que se amplían al hacer clic. El texto descriptivo y el bloque **Incluye** usan la fuente **Poppins** con un fondo claro para una lectura más cómoda.
Adicionalmente, es posible crear grupos de **Artículos** con precio y stock opcional para que el cliente elija cantidades durante la reserva.

 El plugin crea automáticamente una página de catálogo sin plantilla del tema. Esta página presenta un encabezado fijo con el nombre del sitio, un bloque **hero** en tonos claros y una cuadrícula de tarjetas blancas donde se listan los servicios. Cada tarjeta muestra la categoría, la imagen destacada, el precio en DOP, los cupos disponibles y un botón **Reservar** (o una etiqueta **AGOTADO** si ya no hay plazas). Un filtro por categoría y un enlace para volver al inicio permiten una navegación sencilla. Al final se incluye un bloque **Servicios Premium** con los datos de contacto.

 Al hacer clic en **Reservar** se abre un modal amplio que guía al usuario por cinco pasos: información con galería y video, datos del cliente (nombre, cédula opcional, teléfono y correo), cantidad de personas, método de pago y un resumen final con el costo aplicando descuentos cuando corresponda. Las imágenes de la galería pueden ampliarse al hacer clic para verlas a mayor tamaño y se muestra también el título del servicio. SweetAlert muestra la confirmación y la página se recarga para actualizar los cupos.
Se añadieron animaciones suaves entre pasos del formulario y **SweetAlert** muestra la confirmación de la reserva. Las reservas pueden editarse desde su pantalla de edición incluyendo el estatus y el método de pago. La plantilla de correo se modifica en una pestaña independiente dentro de ajustes utilizando los códigos {name}, {service}, {status} y {total}. La pestaña **FrontPage** permite personalizar los textos y datos de contacto que aparecen en el encabezado y en el bloque de *Servicios Premium* del catálogo.
El sistema envía un correo al cliente cuando crea una reserva y cada vez que se actualiza su estatus. El contenido de ese correo puede modificarse con HTML desde **WPBookingStandar → Ajustes**.
Dentro del menú **WPBookingStandar** hay una sección de **Estadísticas** que muestra el total de reservas y las ganancias acumuladas.
Esa pantalla permite filtrar por rango de fechas y servicio y presenta gráficas de pastel con el número de reservas por estatus junto a una línea de ingresos mensuales utilizando Chart.js en un diseño más limpio.
Además, en la pestaña **Diseño del Modal de Reserva** dentro de Ajustes se pueden personalizar visualmente colores, tipografías e imágenes del formulario paso a paso sin necesidad de saber CSS. Un campo opcional de *CSS adicional* permite ajustes avanzados. También es posible cambiar el encabezado que antecede a la descripción del servicio.
La nueva pestaña **Diseño de la Página de Catálogo** permite modificar la tipografía, colores y fondo del encabezado principal, así como el estilo de los botones y ver una vista previa en tiempo real de los cambios.
