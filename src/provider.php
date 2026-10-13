<?php

/*
 * Allt som har med The Provider och företagets nyckel att göra.
 * - Pratar med The Provider: /login, /verify (förlänger nyckeln 30 min) och /logout.
 * - Krypterar och dekrypterar företagets session_key med APP_ENCRYPTION_KEY från .env.
 *   Nyckeln sparas aldrig i klartext i databasen (tabellen COMPANY).
 * - Kollar att företaget har betalat för rätt tjänst (services: 1 = kalender, 2 = blogg, 4 = wiki).
 */
