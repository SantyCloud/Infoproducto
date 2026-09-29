/*
 * Formularios del área de miembros (activar, entrar, pedir enlace): al enviar se desactiva el botón.
 * Así un doble toque no envía dos veces el mismo código o enlace (el segundo envío diría que ya no sirve).
 */
(function () {
    'use strict';

    document.addEventListener('submit', function (evento) {
        var formulario = evento.target;
        if (formulario.getAttribute('data-enviado')) {
            evento.preventDefault();
            return;
        }
        formulario.setAttribute('data-enviado', '1');
        var boton = evento.submitter || formulario.querySelector('button[type="submit"]');
        // Después del envío: si se desactiva antes, algunos navegadores no envían el formulario
        window.setTimeout(function () {
            if (boton) {
                boton.disabled = true;
            }
        }, 0);
    });

    // Al volver atrás, el navegador puede mostrar la página guardada: el formulario vuelve a funcionar
    window.addEventListener('pageshow', function () {
        var formularios = document.querySelectorAll('form[data-enviado]');
        for (var i = 0; i < formularios.length; i++) {
            formularios[i].removeAttribute('data-enviado');
            var botones = formularios[i].querySelectorAll('button[disabled]');
            for (var j = 0; j < botones.length; j++) {
                botones[j].disabled = false;
            }
        }
    });
})();
