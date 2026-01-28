-- Migration: Add rating start columns to games table
-- These columns store the pre-game ratings for accurate rating change display

ALTER TABLE games
ADD COLUMN IF NOT EXISTS player1_rating_start INT DEFAULT NULL AFTER player2_score,
ADD COLUMN IF NOT EXISTS player2_rating_start INT DEFAULT NULL AFTER player1_rating_start;

-- For MySQL versions that don't support IF NOT EXISTS in ALTER TABLE,
-- you may need to run these separately and ignore errors if columns exist:
-- ALTER TABLE games ADD COLUMN player1_rating_start INT DEFAULT NULL AFTER player2_score;
-- ALTER TABLE games ADD COLUMN player2_rating_start INT DEFAULT NULL AFTER player1_rating_start;
