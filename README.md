# AuthKreuzmich-for-phpBB-3.0
Establishes a Authentications provider for phpBB 3.0 to authenticate via cURL against Kreuzmich Server 

## Installation
1. Kopiere auth_kreuzmich.php in den includes/auth Ordner
2. Kopiere die common.php in /languages/de/ und ersetze deine alte. Hast du an dieser Datei bereits eigene Änderungen vorgenommen, kopiere die Spracheinträge aus der language_entries.txt in deine common.php
3. Kopiere die Datei eventuell auch in den languages/de_x_sie Ordner, wenn dieser existiert.

## Konfiguration
1. Logge dich im Forum als Administrator ein 
2. Rufe den Administrationsbereich auf
3. Gehe im Administrationsbereich auf Allgemein -> Client-Kommunikation -> Authentifizierung
4. Wähle Kreuzmich aus & stelle deine Kreuzmich URL ein. Entscheide über die abgelaufenen Benutzer.
