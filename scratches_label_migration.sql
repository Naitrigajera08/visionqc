USE `detection`;

UPDATE `defect_types`
SET `code` = 'SCRATCHES',
    `name` = 'Scratches',
    `class_code` = 'SCR'
WHERE `code` = 'BURNED';

UPDATE `defect_types`
SET `name` = 'Scratches',
    `class_code` = 'SCR'
WHERE `code` = 'SCRATCHES';

UPDATE `alerts`
SET `title` = REPLACE(REPLACE(`title`, 'Burned', 'Scratches'), 'burned', 'Scratches'),
    `description` = REPLACE(
        REPLACE(
            REPLACE(
                REPLACE(`description`, 'burn defects', 'scratch defects'),
                'Burn defects', 'Scratch defects'
            ),
            'burn defect', 'scratch defect'
        ),
        'Burn defect', 'Scratch defect'
    )
WHERE LOWER(`title`) LIKE '%burn%'
   OR LOWER(`description`) LIKE '%burn%';

UPDATE `photo_uploads`
SET `review_status` = 'scratches'
WHERE `review_status` = 'burned';
