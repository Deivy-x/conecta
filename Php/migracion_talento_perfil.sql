-- ============================================================
-- QuibdóConecta — Migración: tabla talento_perfil
-- Ejecutar una vez en la base de datos
-- ============================================================

ALTER TABLE usuarios
ADD COLUMN IF NOT EXISTS tipo VARCHAR(20) NOT NULL DEFAULT 'candidato',
ADD COLUMN IF NOT EXISTS activo TINYINT(1) NOT NULL DEFAULT 1,
ADD COLUMN IF NOT EXISTS apellido VARCHAR(100) DEFAULT '',
ADD COLUMN IF NOT EXISTS telefono VARCHAR(30) DEFAULT '',
ADD COLUMN IF NOT EXISTS ciudad VARCHAR(100) DEFAULT '';

CREATE TABLE IF NOT EXISTS talento_perfil (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id   INT NOT NULL UNIQUE,
    profesion    VARCHAR(150) DEFAULT '',
    bio          TEXT DEFAULT '',
    skills       TEXT DEFAULT '',
    visible      TINYINT(1) NOT NULL DEFAULT 0,
    avatar_color VARCHAR(200) DEFAULT 'linear-gradient(135deg,#1f9d55,#2ecc71)',
    creado_en    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VISTA ELIMINADA: InfinityFree no permite CREATE VIEW
-- La deduplicación se hace con MAX(id) directamente en talentos.php

-- ── PROTECCIÓN ANTI-DUPLICADOS ────────────────────────────────
DELETE t1 FROM talento_perfil t1
INNER JOIN talento_perfil t2
  ON t1.usuario_id = t2.usuario_id AND t1.id < t2.id;

ALTER TABLE talento_perfil
  ADD UNIQUE KEY IF NOT EXISTS uq_usuario_id (usuario_id);