-- Fix missing created_at column in food_wastage table
-- Check if column exists first, then add if missing

-- Add created_at column if it doesn't exist
ALTER TABLE food_wastage 
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Update existing records to have a created_at value if they don't have one
UPDATE food_wastage 
SET created_at = CURRENT_TIMESTAMP 
WHERE created_at IS NULL;