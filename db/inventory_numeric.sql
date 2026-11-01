-- Inventory quantities may be fractional (2.5 kg, 0.75 l). Idempotent: only converts INT columns.
DO $$
BEGIN
  IF (SELECT data_type FROM information_schema.columns WHERE table_name='inventory' AND column_name='quantity') = 'integer' THEN
    ALTER TABLE inventory ALTER COLUMN quantity TYPE NUMERIC(14,2);
  END IF;
  IF (SELECT data_type FROM information_schema.columns WHERE table_name='inventory' AND column_name='min_level') = 'integer' THEN
    ALTER TABLE inventory ALTER COLUMN min_level TYPE NUMERIC(14,2);
  END IF;
END $$;
