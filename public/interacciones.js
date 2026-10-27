'use strict';
const enfocarPin = () => {
    document.querySelector('[data-enfocar-rut], [data-enfocar-pin]')?.focus();
};
enfocarPin();
window.addEventListener('pageshow', enfocarPin);
document.querySelectorAll('form[data-confirmar]').forEach((formulario) =>
    formulario.addEventListener('submit', (evento) => {
        if (!window.confirm(formulario.dataset.confirmar)) evento.preventDefault();
    }),
);
const menu = document.querySelector('[data-menu]');
menu?.addEventListener('click', () => {
    const abierta = document.body.classList.toggle('menu-abierto');
    menu.setAttribute('aria-expanded', String(abierta));
});
document
    .querySelectorAll('[data-imprimir]')
    .forEach((boton) => boton.addEventListener('click', () => window.print()));

// El historial puede restaurar una página sin consultar al servidor.
// Oculta el contenido al salir y revalida la sesión al restaurarlo.
if (document.body.hasAttribute('data-sesion-protegida')) {
    window.addEventListener('pagehide', () => {
        document.body.hidden = true;
    });

    window.addEventListener('pageshow', (evento) => {
        if (evento.persisted) {
            window.location.reload();
            return;
        }
        document.body.hidden = false;
    });
}
