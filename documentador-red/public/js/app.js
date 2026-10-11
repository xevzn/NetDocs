// public/js/app.js
// Manejadores delegados: los datos llegan en atributos data-* (escapados en el servidor)
// y nunca se interpolan dentro de código JavaScript en línea.
(function () {
    'use strict';

    // Confirmación antes de enviar formularios con data-confirm="mensaje"
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form && form.getAttribute && form.hasAttribute('data-confirm')) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        }
    }, true);

    document.addEventListener('click', function (e) {
        var el;

        // Mapa de IPs: abrir el modal con los datos de la celda
        el = e.target.closest('[data-modal-ip]');
        if (el && typeof window.abrirModalIp === 'function') {
            e.preventDefault();
            window.abrirModalIp(el.dataset.ip, el.dataset.estado, el.dataset.dispositivo || '', el.dataset.mac || '');
            return;
        }

        // Usuarios: restablecer contraseña
        el = e.target.closest('[data-modal-pass]');
        if (el && typeof window.abrirModalPass === 'function') {
            e.preventDefault();
            window.abrirModalPass(parseInt(el.dataset.id, 10), el.dataset.usuario || '');
            return;
        }

        // Detalles de equipo: editar puerto
        el = e.target.closest('[data-editar-puerto]');
        if (el && typeof window.abrirModalEditar === 'function') {
            e.preventDefault();
            window.abrirModalEditar(parseInt(el.dataset.id, 10), el.dataset.nombre || '', el.dataset.estado || '',
                el.dataset.modo || 'Acceso', el.dataset.vlan || '', el.dataset.ip || '', el.dataset.destino || '');
            return;
        }

        // Detalles de equipo: eliminar puerto
        el = e.target.closest('[data-eliminar-puerto]');
        if (el && typeof window.confirmarEliminarPuerto === 'function') {
            e.preventDefault();
            window.confirmarEliminarPuerto(parseInt(el.dataset.id, 10), parseInt(el.dataset.equipo, 10));
        }
    });

    // Códigos QR generados en el navegador (sin enviar URLs internas a terceros).
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('img[data-qr]').forEach(function (img) {
            if (typeof window.qrcode !== 'function') {
                img.alt = img.dataset.qr;   // sin librería: se muestra la URL como texto
                return;
            }
            var qr = window.qrcode(0, 'M');
            qr.addData(img.dataset.qr);
            qr.make();
            img.src = qr.createDataURL(6, 0);
        });
    });
})();
