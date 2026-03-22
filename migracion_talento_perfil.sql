-- ============================================================
-- QuibdóConecta — Migración completa talento_perfil v2
-- Ejecutar UNA sola vez en la base de datos
-- Compatible con la migración anterior (no rompe nada)
-- ============================================================

-- 1. Columnas extra en usuarios
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS tipo       VARCHAR(20)  NOT NULL DEFAULT 'candidato'
        COMMENT 'candidato | empresa | artista | chef | local | admin',
    ADD COLUMN IF NOT EXISTS activo     TINYINT(1)   NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS apellido   VARCHAR(100) DEFAULT '',
    ADD COLUMN IF NOT EXISTS telefono   VARCHAR(30)  DEFAULT '',
    ADD COLUMN IF NOT EXISTS ciudad     VARCHAR(100) DEFAULT '',
    ADD COLUMN IF NOT EXISTS foto       VARCHAR(255) DEFAULT NULL
        COMMENT 'Ruta relativa a uploads/foto-perfil.jpg',
    ADD COLUMN IF NOT EXISTS cedula     VARCHAR(20)  DEFAULT NULL
        COMMENT 'Cédula de ciudadanía para verificación',
    ADD COLUMN IF NOT EXISTS nit        VARCHAR(20)  DEFAULT NULL
        COMMENT 'NIT para empresas',
    ADD COLUMN IF NOT EXISTS creado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- 2. Tabla talento_perfil extendida
CREATE TABLE IF NOT EXISTS talento_perfil (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT NOT NULL UNIQUE,
    profesion       VARCHAR(150) DEFAULT '',
    bio             TEXT         DEFAULT '',
    skills          TEXT         DEFAULT ''
        COMMENT 'CSV: React,Node.js,MySQL',
    ciudad          VARCHAR(100) DEFAULT '',
    visible         TINYINT(1)   NOT NULL DEFAULT 0,
    verificado      TINYINT(1)   NOT NULL DEFAULT 0,
    destacado       TINYINT(1)   NOT NULL DEFAULT 0,
    avatar_color    VARCHAR(200) DEFAULT 'linear-gradient(135deg,#1f9d55,#2ecc71)',
    generos         VARCHAR(255) DEFAULT NULL
        COMMENT 'Para DJs/artistas: Champeta,Salsa,Afrobeats',
    precio_desde    DECIMAL(10,0) DEFAULT NULL
        COMMENT 'Precio mínimo para servicios de eventos',
    tipo_servicio   VARCHAR(100) DEFAULT NULL
        COMMENT 'DJ | Fotografía | Chirimía | Catering | etc.',
    calificacion    DECIMAL(3,2) DEFAULT 0.00,
    total_resenas   INT          DEFAULT 0,
    creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Columnas extra si la tabla ya existía
ALTER TABLE talento_perfil
    ADD COLUMN IF NOT EXISTS verificado    TINYINT(1)    NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS destacado     TINYINT(1)    NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS ciudad        VARCHAR(100)  DEFAULT '',
    ADD COLUMN IF NOT EXISTS generos       VARCHAR(255)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS precio_desde  DECIMAL(10,0) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS tipo_servicio VARCHAR(100)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS calificacion  DECIMAL(3,2)  DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS total_resenas INT           DEFAULT 0;

-- 4. Tabla de reseñas
CREATE TABLE IF NOT EXISTS resenas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    de_usuario   INT NOT NULL,
    para_usuario INT NOT NULL,
    calificacion TINYINT(1) NOT NULL DEFAULT 5,
    comentario   TEXT DEFAULT '',
    creado_en    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (de_usuario)   REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (para_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabla empleos
CREATE TABLE IF NOT EXISTS empleos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id   INT NOT NULL,
    titulo       VARCHAR(200) NOT NULL,
    descripcion  TEXT DEFAULT '',
    categoria    VARCHAR(100) DEFAULT '',
    barrio       VARCHAR(100) DEFAULT '',
    ciudad       VARCHAR(100) DEFAULT 'Quibdó',
    salario_min  DECIMAL(12,0) DEFAULT NULL,
    salario_max  DECIMAL(12,0) DEFAULT NULL,
    modalidad    VARCHAR(50)  DEFAULT 'presencial',
    tipo         VARCHAR(50)  DEFAULT 'privado',
    activo       TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    vence_en     DATE         DEFAULT NULL,
    FOREIGN KEY (empresa_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── PROTECCIÓN ANTI-DUPLICADOS ────────────────────────────────
DELETE t1 FROM talento_perfil t1
INNER JOIN talento_perfil t2
  ON t1.usuario_id = t2.usuario_id AND t1.id < t2.id;

ALTER TABLE talento_perfil
  ADD UNIQUE KEY IF NOT EXISTS uq_usuario_id (usuario_id);

-- FIN MIGRACIÓN v2