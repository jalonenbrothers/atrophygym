-- 001_create_tables.sql — AtrophyGym base schema
-- Creates: skills, reps tables. Safe to re-run (IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    delegate_desc TEXT NULL,
    cadence_days INT NOT NULL DEFAULT 7,
    archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    skill_id INT NOT NULL,
    rating TINYINT NOT NULL,
    note TEXT NULL,
    logged_at DATE NOT NULL,
    CONSTRAINT fk_reps_skill FOREIGN KEY (skill_id) REFERENCES skills(id),
    CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Idempotent index guard (plain MySQL has no CREATE INDEX IF NOT EXISTS
-- before 8.0.29; the conditional below keeps re-runs clean on ALL versions).
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'reps' AND index_name = 'idx_reps_skill');
SET @sql := IF(@idx = 0,
    'CREATE INDEX idx_reps_skill ON reps (skill_id, logged_at)',
    'SELECT ''index exists, skipping''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
