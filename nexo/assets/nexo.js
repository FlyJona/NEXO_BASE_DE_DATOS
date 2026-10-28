document.addEventListener('DOMContentLoaded', () => {
    // Resaltado de los bloques de código.
    if (window.hljs) {
        document.querySelectorAll('pre code').forEach((bloque) => window.hljs.highlightElement(bloque));
    }

    // Botón "Insertar bloque de código" del editor de publicaciones.
    document.querySelectorAll('[data-insertar-codigo]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const area = document.getElementById(boton.dataset.insertarCodigo);
            const lenguaje = (document.getElementById('lenguaje-codigo')?.value || '').trim();
            const inicio = area.selectionStart;
            const fin = area.selectionEnd;
            const seleccion = area.value.slice(inicio, fin) || '// Escribe tu código aquí';
            area.setRangeText('\n```' + lenguaje + '\n' + seleccion + '\n```\n', inicio, fin, 'end');
            area.focus();
        });
    });

    // Confirmación antes de acciones irreversibles.
    document.querySelectorAll('form[data-confirmar]').forEach((formulario) => {
        formulario.addEventListener('submit', (evento) => {
            if (!window.confirm(formulario.dataset.confirmar)) {
                evento.preventDefault();
            }
        });
    });
});
