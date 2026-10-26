-- Animal identification (tag numbers, names, sex, status). Idempotent: runs on every boot.
ALTER TABLE livestock ADD COLUMN IF NOT EXISTS tag_no VARCHAR(50);
ALTER TABLE livestock ADD COLUMN IF NOT EXISTS name   VARCHAR(80);
ALTER TABLE livestock ADD COLUMN IF NOT EXISTS sex    VARCHAR(10);
ALTER TABLE livestock ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active';
ALTER TABLE livestock ADD COLUMN IF NOT EXISTS notes  TEXT;
UPDATE livestock SET status = 'active' WHERE status IS NULL OR status = '';
UPDATE livestock SET tag_no = NULL WHERE tag_no IS NOT NULL AND btrim(tag_no) = '';

-- Backfill: give every untagged animal PREFIX-NNN, numbered per farm + prefix after
-- any numbers already used. Never touches an existing tag.
WITH untagged AS (
    SELECT id, COALESCE(farm_id, 0) AS fid,
           CASE
             WHEN lower(type) ~ '^(cattle|cow|cows|bull|bulls|bovine|heifer|heifers|calf|calves)$' THEN 'COW'
             WHEN lower(type) ~ '^(goat|goats|caprine|kid|kids)$' THEN 'GOAT'
             WHEN lower(type) ~ '^(sheep|ewe|ewes|ram|rams|ovine|lamb|lambs)$' THEN 'SHP'
             WHEN lower(type) ~ '^(pig|pigs|swine|porcine|hog|hogs|sow|sows)$' THEN 'PIG'
             WHEN lower(type) ~ '^(poultry|chicken|chickens|hen|hens|rooster|layer|layers|broiler|broilers|duck|ducks|turkey|turkeys|guineafowl)$' THEN 'CHK'
             WHEN regexp_replace(upper(COALESCE(type,'')), '[^A-Z]', '', 'g') = '' THEN 'ANM'
             ELSE substr(regexp_replace(upper(type), '[^A-Z]', '', 'g'), 1, 3)
           END AS prefix
    FROM livestock WHERE tag_no IS NULL
), numbered AS (
    SELECT u.id, u.prefix,
           ROW_NUMBER() OVER (PARTITION BY u.fid, u.prefix ORDER BY u.id)
           + COALESCE((SELECT MAX(substring(l2.tag_no from '^[A-Z]+-([0-9]+)$')::int)
                       FROM livestock l2
                       WHERE COALESCE(l2.farm_id, 0) = u.fid AND l2.tag_no ~ ('^' || u.prefix || '-[0-9]+$')), 0) AS n
    FROM untagged u
)
UPDATE livestock l SET tag_no = numbered.prefix || '-' || lpad(numbered.n::text, 3, '0')
FROM numbered WHERE l.id = numbered.id;

CREATE UNIQUE INDEX IF NOT EXISTS livestock_farm_tag_uq
    ON livestock (COALESCE(farm_id, 0), lower(tag_no)) WHERE tag_no IS NOT NULL;
