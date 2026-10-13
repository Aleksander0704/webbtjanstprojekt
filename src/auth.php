<?php

/*
 * Företagets anställda: inloggning och hantering.
 * - Logga in: kolla username + lösenord i USER och skapa en egen nyckel (USER_TOKEN).
 * - Logga ut: ta bort den anställdas nyckel (påverkar inte andra).
 * - require_login(): kollar nyckeln i varje anrop och tar reda på vem det är.
 * - Admin: lägga till, lista och inaktivera anställda.
 * - Alla: se företagets medlemmar (behövs t.ex. för inbjudningar i kalendern).
 */
