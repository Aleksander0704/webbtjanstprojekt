#!/usr/bin/env bash
# Skapar fyra testföretag, ett per konto hos The Provider.
# Varje företag får en egen databas byggd från mallen foretag1.sql.
# Databasen company_1 hör till kontot foretagsnamn_1, osv.
#
# Kör från projektmappen i WSL:
#   bash database/skapa_testforetag.sh
#
# Byt "foretagsnamn" i provider_username mot ert riktiga prefix hos The Provider.

set -e
cd "$(dirname "$0")"

# nummer | företagsnamn | konto hos The Provider | services
FORETAG=(
    "1|Klippoteket Frisörsalong|foretagsnamn_1|1"
    "2|Bageriet Gyllene Vetet|foretagsnamn_2|2"
    "3|Skövde Bil & Motor|foretagsnamn_3|4"
    "4|Nordljus Arkitekter|foretagsnamn_4|7"
)

for rad in "${FORETAG[@]}"; do
    IFS='|' read -r nr namn konto services <<< "$rad"
    db="company_$nr"

    echo "Skapar $db ($namn)..."
    ddev mysql -uroot -proot -e "CREATE DATABASE $db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" < /dev/null
    ddev mysql -uroot -proot "$db" < foretag1.sql
    ddev mysql -uroot -proot "$db" < /dev/null -e \
        "INSERT INTO COMPANY (name, provider_username, services) VALUES ('$namn', '$konto', $services);"
done

echo "Klart!"
