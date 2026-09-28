/*
 * Landing: barra fija, visor de capturas y botones de WhatsApp (con el evento Contact del Pixel).
 * Todo es opcional: sin JavaScript, los botones llevan igual a WhatsApp.
 */
(function () {
    'use strict';

    var DIAS_CONTACTO = 7; // mismo plazo en el que el servidor reutiliza el código del visitante

    function idEvento() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    function contactoReciente() {
        try {
            var fecha = Number(localStorage.getItem('wa_contacto') || 0);
            return Date.now() - fecha < DIAS_CONTACTO * 86400000;
        } catch (e) {
            return false;
        }
    }

    function marcarContacto() {
        try {
            localStorage.setItem('wa_contacto', String(Date.now()));
        } catch (e) { /* modo privado: no pasa nada */ }
    }

    // 1. Botones de WhatsApp: avisan al Pixel (una vez por visitante) y pasan por /wa con el id del evento
    document.addEventListener('click', function (evento) {
        var enlace = evento.target.closest ? evento.target.closest('a.js-wa') : null;
        if (!enlace || evento.defaultPrevented || evento.button !== 0 || evento.metaKey || evento.ctrlKey || evento.shiftKey) {
            return;
        }
        var eid = idEvento();
        var destino = enlace.getAttribute('href') + '&eid=' + encodeURIComponent(eid);
        var hayPixel = typeof window.fbq === 'function';
        if (hayPixel && !contactoReciente()) {
            window.fbq('track', document.body.getAttribute('data-evento-clic') || 'Contact', {}, { eventID: eid });
        }
        marcarContacto();
        evento.preventDefault();
        // Un instante para que el Pixel alcance a enviar el evento antes de salir de la página
        window.setTimeout(function () { window.location.href = destino; }, hayPixel ? 250 : 0);
    });

    // 2. Barra fija inferior: aparece cuando el botón principal ya no se ve
    var barra = document.querySelector('.barra-fija');
    var botonPrincipal = document.querySelector('.hero .boton-wa');
    var cierre = document.getElementById('cierre');
    if (barra && botonPrincipal && 'IntersectionObserver' in window) {
        var pasoPortada = false;
        var enCierre = false;
        var actualizar = function () {
            barra.classList.toggle('visible', pasoPortada && !enCierre);
        };
        new IntersectionObserver(function (entradas) {
            pasoPortada = !entradas[0].isIntersecting && entradas[0].boundingClientRect.top < 0;
            actualizar();
        }).observe(botonPrincipal);
        if (cierre) {
            new IntersectionObserver(function (entradas) {
                enCierre = entradas[0].isIntersecting;
                actualizar();
            }).observe(cierre);
        }
    }

    // 3. Visor: al tocar una captura se abre en grande
    var visor = document.querySelector('.visor');
    if (visor && typeof visor.showModal === 'function') {
        var imagen = visor.querySelector('img');
        document.addEventListener('click', function (evento) {
            var item = evento.target.closest ? evento.target.closest('.galeria__item') : null;
            if (!item) {
                return;
            }
            imagen.src = item.getAttribute('data-grande');
            imagen.alt = item.querySelector('img').alt;
            visor.showModal();
        });
        visor.addEventListener('click', function () { visor.close(); });
    }
})();
