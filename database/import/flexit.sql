-- Export from Högby IF's old registration system (Flexit) for `php artisan import:flexit` (#14).
-- Run against a restored copy of the backup, never the live database:
--
--   sqlcmd -C -S localhost -U sa -P … -d Hogby -v from=2026-11-06 -i flexit.sql -y 0 -o flexit.json
--
-- `from` is the first race day to export. The file holds names, birth dates, email and phone:
-- delete it once it is imported.

SET NOCOUNT ON;

-- FOR JSON needs level 130; the backup is at 100. Fine on a throwaway copy.
ALTER DATABASE CURRENT SET COMPATIBILITY_LEVEL = 150;
GO

-- FOR JSON straight to sqlcmd comes in rows of 2033 characters; one nvarchar(max) does not.
DECLARE @json nvarchar(max) = (SELECT
    (SELECT c.CompetitionId AS id, RTRIM(g.EventGroupName) AS arrangemang, RTRIM(e.EventName) AS race,
            CONVERT(char(10), c.CompetitionDate, 23) AS date, RTRIM(c.CompetitionName) AS name, c.IsActive AS active
     FROM Competition c
     JOIN Event e ON e.EventId = c.EventId
     JOIN EventGroup g ON g.EventGroupId = e.EventGroupId
     WHERE c.CompetitionDate >= '$(from)'
     FOR JSON PATH) AS competitions,

    -- Active classes, and inactive ones that still have registrations.
    (SELECT cl.ClassId AS id, cl.CompetitionId AS competition, cl.ClassName AS name, cl.SortOrder AS sort
     FROM Class cl
     JOIN Competition c ON c.CompetitionId = cl.CompetitionId
     WHERE c.CompetitionDate >= '$(from)'
       AND (cl.IsActive = 1 OR EXISTS (SELECT 1 FROM Registration r WHERE r.ClassId = cl.ClassId))
     FOR JSON PATH) AS classes,

    -- The standard price; campaigns (#85) are left out.
    (SELECT pc.ClassId AS class, CONVERT(char(10), p.ToDate, 23) AS until, CAST(pc.Price * 100 AS int) AS amount
     FROM PriceClass pc
     JOIN Campaign k ON k.CampaignId = pc.CampaignId AND k.IsDefault = 1
     JOIN Period p ON p.PeriodId = pc.PeriodId
     JOIN Competition c ON c.CompetitionId = p.CompetitionId
     WHERE c.CompetitionDate >= '$(from)' AND pc.Price IS NOT NULL
     FOR JSON PATH) AS prices,

    -- Only the birth date leaves the database, never the last four digits of the personnummer.
    (SELECT r.RegistrationId AS id, r.ClassId AS class, RTRIM(r.FirstName) AS first_name, RTRIM(r.LastName) AS last_name,
            CASE LEN(s.digits)
                WHEN 12 THEN LEFT(s.digits, 8)
                WHEN 8 THEN s.digits
                WHEN 10 THEN CASE WHEN CAST(LEFT(s.digits, 2) AS int) > YEAR(GETDATE()) % 100 THEN '19' ELSE '20' END + LEFT(s.digits, 6)
                WHEN 6 THEN CASE WHEN CAST(LEFT(s.digits, 2) AS int) > YEAR(GETDATE()) % 100 THEN '19' ELSE '20' END + s.digits
            END AS birth,
            r.Gender AS gender, RTRIM(r.Club) AS club, a.Email AS email, a.Phone AS phone,
            CAST(od.Price * 100 AS int) AS price, pm.Code AS method,
            CONVERT(char(19), COALESCE(od.DatePaid, o.DatePaid), 126) AS paid_at,
            CONVERT(char(19), o.DateOrder, 126) AS ordered_at,
            k.CampaignName AS campaign, k.IsDefault AS standard
     FROM Registration r
     CROSS APPLY (SELECT REPLACE(REPLACE(ISNULL(r.SocialNo, ''), '-', ''), ' ', '') AS digits) s
     JOIN Class cl ON cl.ClassId = r.ClassId
     JOIN Competition c ON c.CompetitionId = cl.CompetitionId
     JOIN Campaign k ON k.CampaignId = r.CampaignId
     LEFT JOIN Address a ON a.AddressId = r.AddressId
     LEFT JOIN OrderDetail od ON od.RegistrationId = r.RegistrationId
     LEFT JOIN [Order] o ON o.OrderId = od.OrderId
     LEFT JOIN PaymentMethod pm ON pm.PaymentMethodId = o.PaymentMethodId
     WHERE c.CompetitionDate >= '$(from)'
     FOR JSON PATH, INCLUDE_NULL_VALUES) AS registrations
FOR JSON PATH, WITHOUT_ARRAY_WRAPPER);

SELECT @json;
