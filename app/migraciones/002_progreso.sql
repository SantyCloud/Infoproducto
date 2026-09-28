-- 002 · Progreso de cada comprador en el curso (qué lecciones ya abrió).

CREATE TABLE progreso (
    comprador_id    INTEGER NOT NULL REFERENCES compradores (id),
    leccion         TEXT    NOT NULL,         -- slug de la lección
    vista_en        TEXT    NOT NULL,
    PRIMARY KEY (comprador_id, leccion)
);
