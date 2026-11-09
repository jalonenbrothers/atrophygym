-- AtrophyGym migration 001: skills + reps.
-- Creates the two tables of the pinned data model (UX plan E-map §data).
-- Safe to re-run: CREATE TABLE IF NOT EXISTS + a guard for the index
-- (plain MySQL has no CREATE INDEX IF NOT EXISTS before 8.0.29; the
-- conditional INSERT below keeps re-runs clean on ALL MySQL versions).

CREATE TABLE IF NOT EXISTS skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    delegate_desc TEXT NULL,
    cadence_days INT NOT NULL DEFAULT 7,
    archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    skill_id INT NOT NULL,
    rating TINYINT NOT NULL,
    note TEXT NULL,
    logged_at DATE NOT NULL,
    CONSTRAINT fk_reps_skill FOREIGN KEY (skill_id) REFERENCES skills(id),
    CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- idempotent index (works on every MySQL 8; skip if it already exists)
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'reps' AND index_name = 'idx_reps_skill');
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_reps_skill ON reps (skill_id, logged_at)',
    'SELECT "index exists, skipping"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
