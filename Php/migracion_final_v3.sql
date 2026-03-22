-- ============================================================
-- QuibdóConecta — Migración FINAL completa v3
-- ============================================================

-- 1. Convertir talento_perfil a InnoDB
ALTER TABLE talento_perfil ENGINE = InnoDB;

-- 2. Columnas que le faltan a usuarios
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS tipo      VARCHAR(20)  NOT NULL DEFAULT 'candidato',
    ADD COLUMN IF NOT EXISTS activo    TINYINT(1)   NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS foto      VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS cedula    VARCHAR(20)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS creado_en TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- 3. Columnas que le faltan a talento_perfil
ALTER TABLE talento_perfil
    ADD COLUMN IF NOT EXISTS verificado    TINYINT(1)    NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS destacado     TINYINT(1)    NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS generos       VARCHAR(255)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS precio_desde  DECIMAL(10,0) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS tipo_servicio VARCHAR(100)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS calificacion  DECIMAL(3,2)  DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS total_resenas INT           DEFAULT 0;

-- 4. Tabla empleos
CREATE TABLE IF NOT EXISTS empleos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id  INT NOT NULL,
    titulo      VARCHAR(200) NOT NULL,
    descripcion TEXT         DEFAULT '',
    categoria   VARCHAR(100) DEFAULT '',
    barrio      VARCHAR(100) DEFAULT '',
    ciudad      VARCHAR(100) DEFAULT 'Quibdó',
    salario_min DECIMAL(12,0) DEFAULT NULL,
    salario_max DECIMAL(12,0) DEFAULT NULL,
    modalidad   VARCHAR(50)  DEFAULT 'presencial',
    tipo        VARCHAR(50)  DEFAULT 'privado',
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    vence_en    DATE         DEFAULT NULL,
    FOREIGN KEY (empresa_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabla reseñas
CREATE TABLE IF NOT EXISTS resenas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    de_usuario   INT      NOT NULL,
    para_usuario INT      NOT NULL,
    calificacion TINYINT(1) NOT NULL DEFAULT 5,
    comentario   TEXT     DEFAULT '',
    creado_en    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (de_usuario)   REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (para_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Tabla convocatorias
CREATE TABLE IF NOT EXISTS convocatorias (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    entidad     VARCHAR(150) NOT NULL,
    categoria   VARCHAR(50)  NOT NULL DEFAULT 'gobernacion',
    titulo      VARCHAR(200) NOT NULL,
    vacantes    INT          NOT NULL DEFAULT 1,
    modalidad   VARCHAR(60)  NOT NULL DEFAULT 'Presencial',
    nivel       VARCHAR(100) NOT NULL DEFAULT 'Bachillerato',
    salario     VARCHAR(80)  DEFAULT NULL,
    lugar       VARCHAR(200) DEFAULT 'Quibdó, Chocó',
    requisito   TEXT         DEFAULT '',
    estado      VARCHAR(20)  NOT NULL DEFAULT 'abierta',
    icono       VARCHAR(10)  DEFAULT '🏛️',
    logo_url    VARCHAR(255) DEFAULT NULL,
    url_externa VARCHAR(255) DEFAULT NULL,
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    vence_en    DATE         DEFAULT NULL,
    creado_en   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE convocatorias
    ADD INDEX IF NOT EXISTS idx_categoria (categoria),
    ADD INDEX IF NOT EXISTS idx_estado    (estado),
    ADD INDEX IF NOT EXISTS idx_activo    (activo),
    ADD INDEX IF NOT EXISTS idx_vence     (vence_en);

-- 7. Tabla mensajes
CREATE TABLE IF NOT EXISTS mensajes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    de_usuario   INT      NOT NULL,
    para_usuario INT      NOT NULL,
    mensaje      TEXT     NOT NULL,
    leido        TINYINT(1) NOT NULL DEFAULT 0,
    creado_en    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (de_usuario)   REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (para_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_conversacion (de_usuario, para_usuario),
    INDEX idx_leido        (leido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Tabla verificaciones
CREATE TABLE IF NOT EXISTS verificaciones (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id   INT      NOT NULL UNIQUE,
    tipo_doc     VARCHAR(30) DEFAULT 'cedula',
    doc_url      VARCHAR(255) DEFAULT NULL,
    foto_doc_url VARCHAR(255) DEFAULT NULL,
    estado       VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    revisado_por INT         DEFAULT NULL,
    nota_rechazo TEXT        DEFAULT NULL,
    creado_en    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Tabla roles admin
CREATE TABLE IF NOT EXISTS admin_roles (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id       INT      NOT NULL UNIQUE,
    nivel            VARCHAR(20) NOT NULL DEFAULT 'dev',
    perm_usuarios    TINYINT(1) DEFAULT 0,
    perm_empleos     TINYINT(1) DEFAULT 0,
    perm_verificar   TINYINT(1) DEFAULT 0,
    perm_mensajes    TINYINT(1) DEFAULT 0,
    perm_pagos       TINYINT(1) DEFAULT 0,
    perm_stats       TINYINT(1) DEFAULT 0,
    perm_artistas    TINYINT(1) DEFAULT 0,
    perm_badges      TINYINT(1) DEFAULT 0,
    perm_convocatorias TINYINT(1) DEFAULT 0,
    delegacion_hasta DATETIME   DEFAULT NULL,
    creado_en        TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VISTA ELIMINADA: InfinityFree no permite CREATE VIEW
-- La deduplicación se hace con MAX(id) directamente en talentos.php

-- 10. Superadmin
UPDATE usuarios SET tipo = 'admin', activo = 1
WHERE correo = 'cuestadeivy9@outlook.com';

INSERT INTO admin_roles (
    usuario_id, nivel,
    perm_usuarios, perm_empleos, perm_verificar,
    perm_mensajes, perm_pagos, perm_stats,
    perm_artistas, perm_badges, perm_convocatorias
)
SELECT id, 'superadmin', 1,1,1,1,1,1,1,1,1
FROM usuarios WHERE correo = 'cuestadeivy9@outlook.com'
ON DUPLICATE KEY UPDATE
    nivel='superadmin', perm_usuarios=1, perm_empleos=1,
    perm_verificar=1, perm_mensajes=1, perm_pagos=1,
    perm_stats=1, perm_artistas=1, perm_badges=1, perm_convocatorias=1;

-- ── PROTECCIÓN ANTI-DUPLICADOS ────────────────────────────────
DELETE t1 FROM talento_perfil t1
INNER JOIN talento_perfil t2
  ON t1.usuario_id = t2.usuario_id AND t1.id < t2.id;

ALTER TABLE talento_perfil
  ADD UNIQUE KEY IF NOT EXISTS uq_usuario_id (usuario_id);

-- FIN MIGRACIÓN FINAL v3