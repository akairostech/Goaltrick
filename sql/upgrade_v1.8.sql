-- =====================================================================
--  GoalTrick - Actualización de la base de datos de v1.7 a v1.8
--  (ayuda y soporte, sanciones e historial de accesos)
--
--  NORMALMENTE NO NECESITAS ESTE ARCHIVO: upgrade.php lo hace todo solo.
--  Si lo aplicas a mano: ejecuta este archivo UNA vez y abre igualmente upgrade.php.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE users ADD COLUMN ban_reason VARCHAR(500) NULL AFTER is_banned;
ALTER TABLE users ADD COLUMN banned_until DATETIME NULL AFTER ban_reason;
ALTER TABLE users ADD COLUMN banned_at DATETIME NULL AFTER banned_until;

-- Historial de sanciones de cada usuario (avisos, suspensiones, expulsiones y readmisiones)
CREATE TABLE IF NOT EXISTS user_sanctions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    admin_id    INT UNSIGNED NULL,
    action      VARCHAR(12)  NOT NULL,            -- warn | suspend | expel | unban
    reason      VARCHAR(500) NOT NULL,
    until_at    DATETIME     NULL,
    ticket_id   INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sanction_user (user_id, created_at),
    CONSTRAINT fk_sanction_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Accesos de cada usuario (IP y navegador) para detectar multicuentas
CREATE TABLE IF NOT EXISTS user_logins (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    ip          VARCHAR(45)  NOT NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_ul_user (user_id, created_at),
    KEY idx_ul_ip (ip),
    CONSTRAINT fk_ul_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ayuda y soporte: tickets de los usuarios y sus respuestas
CREATE TABLE IF NOT EXISTS support_tickets (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id           INT UNSIGNED NOT NULL,
    category          VARCHAR(16)  NOT NULL,       -- bug | report | account | transfer | suggestion | other
    subject           VARCHAR(150) NOT NULL,
    status            VARCHAR(10)  NOT NULL DEFAULT 'open',   -- open | answered | pending | closed
    reported_user_id  INT UNSIGNED NULL,            -- usuario denunciado
    link              VARCHAR(255) NULL,            -- enlace opcional (partido, equipo, jugador...)
    assigned_to       INT UNSIGNED NULL,            -- administrador que lo lleva
    created_at        DATETIME     NOT NULL,
    updated_at        DATETIME     NOT NULL,
    user_read_at      DATETIME     NULL,
    staff_read_at     DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_ticket_user (user_id, updated_at),
    KEY idx_ticket_status (status, updated_at),
    CONSTRAINT fk_ticket_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_replies (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id   INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NULL,
    is_staff    TINYINT(1)   NOT NULL DEFAULT 0,
    is_internal TINYINT(1)   NOT NULL DEFAULT 0,   -- nota interna: sólo la ven los administradores
    body        TEXT         NOT NULL,
    created_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_reply_ticket (ticket_id, id),
    CONSTRAINT fk_reply_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

