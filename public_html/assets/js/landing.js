/*
 * Landing: barra fija, visor de capturas, adelanto del curso y botones de WhatsApp (con el evento Contact del Pixel).
 * Todo es opcional: sin JavaScript, los botones llevan igual a WhatsApp y el adelanto se abre como video.
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

    // 4. Adelanto del curso: unos segundos sin sonido al pasar el mouse (en el celular, al llegar a él)
    //    y completo, con sonido, al tocarlo. Con "ahorro de datos" o "reducir movimiento", solo al tocarlo.
    var botonAdelanto = document.querySelector('.js-adelanto');
    if (botonAdelanto) {
        var figura = botonAdelanto.closest('.adelanto');
        var marco = botonAdelanto.parentNode;
        var video = document.createElement('video');
        var completo = false;
        var cumple = function (medio) { return !!(window.matchMedia && window.matchMedia(medio).matches); };
        var conPrevia = !cumple('(prefers-reduced-motion: reduce)') && !(navigator.connection && navigator.connection.saveData);
        var conMouse = cumple('(hover: hover) and (pointer: fine)');

        video.className = 'adelanto__video';
        video.muted = true;
        video.loop = true;
        video.preload = 'none';
        video.setAttribute('muted', '');
        video.setAttribute('playsinline', '');
        video.setAttribute('aria-hidden', 'true');
        marco.insertBefore(video, botonAdelanto);
        video.addEventListener('playing', function () { figura.classList.add('adelanto--reproduciendo'); });

        var reproducir = function (alFallar) {
            var intento = video.play();
            if (intento && typeof intento.catch === 'function') {
                intento.catch(alFallar);
            }
        };
        var verPrevia = function () {
            if (completo || !conPrevia) {
                return;
            }
            if (!video.getAttribute('src')) {
                video.src = botonAdelanto.getAttribute('data-previa');
            }
            figura.classList.add('adelanto--previa');
            // Si el navegador no deja reproducir solo (ahorro de batería…), queda la portada con su botón
            reproducir(function () { figura.classList.remove('adelanto--previa'); });
        };
        var pausarPrevia = function () {
            if (!completo) {
                video.pause();
            }
        };

        if (conMouse) {
            marco.addEventListener('mouseenter', verPrevia);
            marco.addEventListener('mouseleave', pausarPrevia);
        }
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (entradas) {
                var entrada = entradas[0];
                if (completo) {
                    if (!entrada.isIntersecting) {
                        video.pause(); // bajó y ya no lo ve
                    }
                } else if (!conMouse) {
                    if (entrada.intersectionRatio >= 0.6) {
                        verPrevia();
                    } else {
                        pausarPrevia();
                    }
                }
            }, { threshold: [0, 0.6] }).observe(marco);
        }

        botonAdelanto.addEventListener('click', function (evento) {
            evento.preventDefault();
            completo = true;
            figura.classList.remove('adelanto--previa');
            figura.classList.add('adelanto--completo');
            video.removeAttribute('aria-hidden');
            video.setAttribute('aria-label', 'Adelanto del curso');
            video.loop = false;
            video.muted = false;
            video.removeAttribute('muted');
            video.controls = true;
            video.src = botonAdelanto.getAttribute('href');
            reproducir(function () { /* queda con sus controles para darle play */ });
            video.focus({ preventScroll: true });
        });
    }
})();
