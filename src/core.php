<?php

/*
 * Grunden som alla andra filer använder.
 * - Läser in .env (DB-uppgifter, API-adress, krypteringsnyckel).
 * - Ansluter till databaserna: den gemensamma (central) och rätt företagsdatabas,
 *   t.ex. "acme" i URL:en -> databasen company_acme.
 * - Hjälpfunktioner för JSON-svar, t.ex. json_ok($data) och json_error(403, "Ingen åtkomst").
 */
