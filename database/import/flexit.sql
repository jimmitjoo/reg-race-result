-- Export from Högby IF's old registration system (Flexit) for `php artisan import:flexit` (#14).
-- Run against a restored copy of the backup, never the live database:
--
--   sqlcmd -C -S localhost -U sa -P … -d Hogby -v from=2026-11-06 -i flexit.sql -y 0 -o flexit.json
--
-- `from` is the first race day to export. A competition with an earlier date comes along when it has
-- registrations ordered since a month before: the old system kept them under wrong dates (#14).
-- The file holds names, birth dates, email and phone: delete it once it is imported.

SET NOCOUNT ON;

-- FOR JSON needs level 130; the backup is at 100. Fine on a throwaway copy.
ALTER DATABASE CURRENT SET COMPATIBILITY_LEVEL = 150;
GO

DECLARE @ids TABLE (id int PRIMARY KEY);
INSERT @ids SELECT c.CompetitionId FROM Competition c
WHERE c.CompetitionDate >= '$(from)' OR EXISTS (
    SELECT 1 FROM Class cl JOIN Registration r ON r.ClassId = cl.ClassId
    JOIN OrderDetail od ON od.RegistrationId = r.RegistrationId JOIN [Order] o ON o.OrderId = od.OrderId
    WHERE cl.CompetitionId = c.CompetitionId AND o.DateOrder >= DATEADD(day, -30, '$(from)'));

-- FOR JSON straight to sqlcmd comes in rows of 2033 characters; one nvarchar(max) does not.
DECLARE @json nvarchar(max) = (SELECT
    (SELECT c.CompetitionId AS id, RTRIM(g.EventGroupName) AS arrangemang, RTRIM(e.EventName) AS race,
            CONVERT(char(10), c.CompetitionDate, 23) AS date, RTRIM(c.CompetitionName) AS name, c.IsActive AS active,
            -- Every date the arrangemang has had, for telling the race days from the wrong dates: whether a
            -- competition on it is active, how many there are, and how many registrations were ordered in the year before it.
            (SELECT x.d, MAX(x.active) AS active, COUNT(*) AS n, SUM(x.ok) AS ok
             FROM (SELECT CONVERT(char(10), p.CompetitionDate, 23) AS d, CAST(p.IsActive AS int) AS active,
                          (SELECT COUNT(*) FROM Class pcl JOIN Registration pr ON pr.ClassId = pcl.ClassId
                           JOIN OrderDetail pod ON pod.RegistrationId = pr.RegistrationId JOIN [Order] po ON po.OrderId = pod.OrderId
                           WHERE pcl.CompetitionId = p.CompetitionId AND po.DateOrder <= p.CompetitionDate
                             AND po.DateOrder > DATEADD(day, -365, p.CompetitionDate)) AS ok
                   FROM Competition p JOIN Event pe ON pe.EventId = p.EventId WHERE pe.EventGroupId = e.EventGroupId) x
             GROUP BY x.d FOR JSON PATH) AS dates
     FROM Competition c
     JOIN Event e ON e.EventId = c.EventId
     JOIN EventGroup g ON g.EventGroupId = e.EventGroupId
     WHERE c.CompetitionId IN (SELECT id FROM @ids)
     FOR JSON PATH) AS competitions,

    -- Active classes, and inactive ones that still have registrations.
    (SELECT cl.ClassId AS id, cl.CompetitionId AS competition, cl.ClassName AS name, cl.SortOrder AS sort,
            -- A team class names its members in team attributes ("För- och efternamn deltagare 1" …).
            CASE WHEN cl.IsTeam = 1 THEN (SELECT NULLIF(COUNT(*), 0) FROM Attribute a WHERE a.CompetitionId = cl.CompetitionId AND a.IsTeamAttribute = 1 AND a.IsActive = 1) END AS team_size
     FROM Class cl
     WHERE cl.CompetitionId IN (SELECT id FROM @ids)
       AND (cl.IsActive = 1 OR EXISTS (SELECT 1 FROM Registration r WHERE r.ClassId = cl.ClassId))
     FOR JSON PATH) AS classes,

    -- The standard price; campaigns (#85) are left out. Some competitions have two standard campaigns, one at 0 kr
    -- (Sylvesterloppet 2019): the highest price of each period, in a fixed order, so every export gives the same.
    (SELECT pc.ClassId AS class, CONVERT(char(10), p.ToDate, 23) AS until, CAST(MAX(pc.Price) * 100 AS int) AS amount
     FROM PriceClass pc
     JOIN Campaign k ON k.CampaignId = pc.CampaignId AND k.IsDefault = 1
     JOIN Period p ON p.PeriodId = pc.PeriodId
     WHERE p.CompetitionId IN (SELECT id FROM @ids) AND pc.Price IS NOT NULL
     GROUP BY pc.ClassId, CONVERT(char(10), p.ToDate, 23)
     ORDER BY pc.ClassId, until
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
            k.CampaignName AS campaign, k.IsDefault AS standard,
            RTRIM(t.TeamName) AS team,
            (SELECT ra.Value AS name FROM RegistrationAttribute ra JOIN Attribute ta ON ta.AttributeId = ra.AttributeId
             WHERE ra.RegistrationId = r.RegistrationId AND ta.IsTeamAttribute = 1 AND ta.IsActive = 1
             ORDER BY ta.SortOrder FOR JSON PATH) AS members
     FROM Registration r
     CROSS APPLY (SELECT REPLACE(REPLACE(ISNULL(r.SocialNo, ''), '-', ''), ' ', '') AS digits) s
     JOIN Class cl ON cl.ClassId = r.ClassId
     JOIN Competition c ON c.CompetitionId = cl.CompetitionId
     JOIN Campaign k ON k.CampaignId = r.CampaignId
     LEFT JOIN Address a ON a.AddressId = r.AddressId
     LEFT JOIN Team t ON t.TeamId = r.TeamId
     LEFT JOIN OrderDetail od ON od.RegistrationId = r.RegistrationId
     LEFT JOIN [Order] o ON o.OrderId = od.OrderId
     LEFT JOIN PaymentMethod pm ON pm.PaymentMethodId = o.PaymentMethodId
     WHERE c.CompetitionId IN (SELECT id FROM @ids)
     FOR JSON PATH, INCLUDE_NULL_VALUES) AS registrations
FOR JSON PATH, WITHOUT_ARRAY_WRAPPER);

SELECT @json;
