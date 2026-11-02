# Personuppgifter och intresseavvägning

Arrangören (t.ex. Högby IF) är personuppgiftsansvarig för uppgifterna om sina deltagare. Systemet är personuppgiftsbiträde.

## Vilka uppgifter

| Uppgift | Varför | Publiceras |
|---|---|---|
| Namn | Start- och resultatlistor, identifiera löparen | Ja |
| Födelsedatum | Åldersklasser, ålderskrav, krävs i resultatfilen till Friidrottsförbundet | Bara födelseår |
| Kön | Klass och placering per kön | Indirekt (klass) |
| Förening eller ort | Resultatlistor, mästerskapsbehörighet | Ja |
| E-post, telefon | Kvitto och kontakt inför loppet | Nej |
| Startnummer, chip, läsningar, tider | Tidtagning och resultat | Startnummer och tid |

Personnummer samlas aldrig in. Kortuppgifter hanteras av Stripe och når aldrig systemet.

## Intresseavvägning för publicering

**Ändamål:** Att publicera start- och resultatlistor är en del av tävlingen. Löpare, publik och Friidrottsförbundets statistik behöver dem. Sanktionerade lopp ska rapporteras till förbundet.

**Nödvändighet:** Bara namn, födelseår, förening/ort, startnummer och resultat publiceras. Det är vad som behövs för att resultaten ska vara begripliga och kunna kontrolleras. Fullständigt födelsedatum skickas bara till förbundet.

**Avvägning:** Deltagaren anmäler sig frivilligt till en tävling och kan förvänta sig att resultat publiceras, vilket är praxis inom friidrott. Deltagaren informeras före anmälan (villkoren måste godkännas) och kan be om att få sitt namn dolt. Barn i lopp utan tidtagning listas utan tid.

**Slutsats:** Publiceringen sker med stöd av berättigat intresse (artikel 6.1 f GDPR), enligt Riksidrottsförbundets vägledning.

## Rättigheter och gallring

- **Dölja namn:** under *Anmälningar* kan arrangören dölja en löpare. Löparen visas då som "Anonym" i startlista, resultatlista och PDF. Den officiella resultatfilen till förbundet påverkas inte.
- **Gallring:** e-post och telefon raderas automatiskt 12 månader efter loppet (`registrations:prune-contacts`, dagligen; ändras med `CONTACT_RETENTION_MONTHS`). Namn och resultat sparas i resultatlistorna.
- **Bokföring:** betalningar (belopp, datum, betalsätt) sparas så länge bokföringslagen kräver (7 år).
- **Villkor:** varje arrangör har en villkorssida på `/{arrangör}/terms`. När villkoren godkändes sparas på anmälan.
