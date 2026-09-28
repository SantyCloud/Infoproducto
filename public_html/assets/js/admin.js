/*
 * Panel: botones "Copiar" y confirmación antes de acciones delicadas.
 */
(function () {
    'use strict';

    document.addEventListener('click', function (evento) {
        var boton = evento.target.closest ? evento.target.closest('[data-copiar]') : null;
        if (!boton) {
            return;
        }
        var campo = document.querySelector(boton.getAttribute('data-copiar'));
        if (!campo) {
            return;
        }
        var avisar = function () {
            var original = boton.innerHTML;
            boton.textContent = '¡Copiado!';
            window.setTimeout(function () { boton.innerHTML = original; }, 1600);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(campo.value).then(avisar);
        } else {
            campo.select();
            document.execCommand('copy');
            avisar();
        }
    });

    document.addEventListener('submit', function (evento) {
        var boton = evento.submitter || evento.target.querySelector('[data-confirmar]');
        var pregunta = boton && boton.getAttribute('data-confirmar');
        if (pregunta && !window.confirm(pregunta)) {
            evento.preventDefault();
        }
    });
})();
