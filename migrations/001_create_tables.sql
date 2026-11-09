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

-- MySQL 8.0.13+ would support CREATE INDEX IF NOT EXISTS; older MySQL does not.
-- The index is non-essential; safe to run on fresh tables (created above).
