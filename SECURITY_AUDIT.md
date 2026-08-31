# Clubano Security Audit

Datum: 31.08.2026  
Arbeitsverzeichnis: `/Users/olli/Herd/clubano-import`  
Branch: `feature/local-stabilization`  
Scope: defensives Code- und Konfigurationsaudit der lokalen Clubano-Codebasis. Keine Angriffe, kein Produktivzugriff, keine Reparaturen, kein Git-Push, kein Deployment.

## Kurzfazit

Clubano hat eine brauchbare Sicherheitsbasis: Authentifizierung, Rollen, Tenant-Scopes, Paywall/Demo-Schutz, private Dokumentenablage in Teilen, Tenant-Prüfungen bei vielen Downloads und erste Isolationstests sind vorhanden.

Aus Sicherheitssicht ist der aktuelle Stand aber noch nicht reif genug, um ohne harte Nacharbeit weiter mit echten Vereinen zu skalieren. Es gibt mehrere Punkte, die vor dem nächsten größeren öffentlichen Update priorisiert werden sollten:

1. Daten- und Quellcode-Artefakte aus dem Repository und aus dem Webroot entfernen.
2. Stripe-Webhooks kryptografisch verifizieren.
3. Verwundbare Composer- und NPM-Abhängigkeiten aktualisieren.
4. Import-Zwischendateien serverseitig absichern.
5. SMTP-Passwörter verschlüsseln.
6. Tenant-Isolation durch Tests und Modellschutz wieder grün bekommen.
7. Öffentliche Formulare, Buchungen und Gutscheinlogik stärker gegen Missbrauch drosseln.

Gesamtrating: **Gelb/Rot**. Die Architektur ist rettbar und an vielen Stellen ordentlich gedacht, aber einige Findings sind kritisch, weil sie Zahlungslogik, Datenschutz oder Server-Auslieferung betreffen.

## Abgeleitete Sofortmassnahmen Vom 31.08.2026

Folgende Massnahmen wurden aus diesem Audit direkt lokal umgesetzt:

- Webroot bereinigt: getrackte Laravel-Codekopien, Konfigurationen, Datenbankdateien, Tests, ZIPs, alte Route-Backups und SQL-Dumps wurden aus dem Git-Stand entfernt.
- `.gitignore` erweitert, damit SQL-Dumps, ZIPs und Laravel-Codekopien im `public/`-Ordner nicht erneut ins Repository geraten.
- Stripe-Webhook abgesichert: eingehende Events werden nur noch mit gueltiger `Stripe-Signature` und `STRIPE_WEBHOOK_SECRET` akzeptiert.
- CSRF-Ausnahme von `stripe/*` auf die konkrete Webhook-Route begrenzt.
- Debug-Route `/envcheck` entfernt.
- `OperatorAnnouncementDelivery` tenant-scope-faehig gemacht, damit der Tenant-Isolationstest wieder gruen ist.
- Import-Zwischendateien gegen manipulierte Storage-Pfade abgesichert.
- SMTP-Passwoerter werden kuenftig verschluesselt gespeichert; bestehende Werte werden per Migration verschluesselt.
- Mailpasswoerter werden im SMTP-Formular nicht mehr im Klartext ausgegeben.
- Zentrale Security-Header fuer normale Web-Seiten ergaenzt: `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS bei HTTPS sowie Frame-Schutz ausserhalb bewusst oeffentlicher Embeds.
- Oeffentliche Schreib- und Tracking-Endpunkte gedrosselt: Formularantworten, Formular-Embeds, Einladungsantworten, Mailtracking und Gutscheinpruefung.
- Zentraler HTML-Sanitizer fuer Editor-Inhalte ergaenzt und an Vorlagen, Eventbeschreibungen, Protokolltext, Formularbestaetigungen, Betreiber-Mitteilungen, Gutscheinmails, Austrittsmails und Spendenmails angebunden.

Noch offen und bewusst nicht in diesem ersten Paket geloest:

- Composer-/NPM-Dependency-Updates.
- Vollstaendige CSP-Strategie inklusive erlaubter Embed-Domains je Verein.
- Private Migration bereits vorhandener Belegdateien aus Public Storage.
- Bestehende HTML-Inhalte in der Datenbank nachtraeglich bereinigen.
- Vollstaendiges Admin-Auditlog.

## Positiv Geprüft

- Viele Mandantenmodelle nutzen Tenant-Scopes über `App\Models\Concerns\BelongsToTenant` oder `CurrentTenantScope`.
- Superadmins ohne `tenant_id` sind bewusst vom normalen Vereinskontext getrennt.
- Vereinsbereiche sind überwiegend mit `auth`, `verified`, `tenant.subscribed` und Rollen-Middleware geschützt.
- Registrierung erzeugt einen Verein mit Status `pending`, legt einen Benutzer mit Rolle `Admin` für genau diesen Verein an und führt anschließend einen Login aus.
- Demo-Modus blockiert gefährliche Aktionen wie Rechnungsversand, SEPA, Benutzerverwaltung, Rollen, Adminbereich und Löschaktionen.
- Login und Registrierung sind gedrosselt.
- Dokumenten- und Exportdownloads prüfen an mehreren Stellen explizit den Tenant.
- Datenschutzexporte werden tenantbezogen erzeugt und Downloads prüfen die Zugehörigkeit.
- Öffentliche Dokumentationsseiten strippen rohes HTML aus Markdown.
- Transaktionen haben eine explizite tenantgebundene Route-Model-Bindung.

## Kritische Findings

### 1. Vollständige Code- und Datenkopien liegen im öffentlichen Bereich

Schweregrad: **Kritisch**  
Betroffene Beispiele: `public/app`, `public/config`, `public/database`, `public/resources`, `public/tests`, `public/public`, `public/composer.lock`, `public/artisan`

Im Git-Stand liegen große Teile der Laravel-Anwendung innerhalb des Verzeichnisses `public/`. Das ist gefährlich, weil `public/` der Webroot ist. Wenn Apache/Plesk, Symlinks oder Rewrite-Regeln ungünstig stehen, könnten Quellcode, Konfigurationen, Migrationen, Tests oder weitere interne Dateien direkt abrufbar werden.

Zusätzlich wurden 464 getrackte Dateien in solchen öffentlichen Kopien gefunden. Das ist nicht nur unordentlich, sondern ein echtes Auslieferungsrisiko.

Empfehlung:

- Sofort prüfen, ob diese Dateien auf dem Server öffentlich erreichbar sind.
- Aus dem Git entfernen und aus dem Server-Webroot löschen.
- Deployment künftig als Allowlist bauen: in `public/` gehören nur `index.php`, Build-Assets, öffentlich gewollte Assets und `.htaccess`.
- CI-Check ergänzen, der verbietet, dass `public/app`, `public/config`, `public/database`, `public/resources`, `public/tests`, SQL-Dumps oder ZIP-Backups committed werden.

### 2. Getrackte SQL-Dumps enthalten personenbezogene Daten

Schweregrad: **Kritisch**  
Betroffene Dateien: `dump.sql`, `lokal_dump.sql`

Die getrackten SQL-Dumps enthalten personenbezogene Daten wie Namen, Adressen, E-Mail-Adressen, Telefonnummern, Geburtsdaten und Passwort-Hashes. Diese Daten gehören nicht in ein Git-Repository.

Empfehlung:

- Dateien aus dem Repository entfernen.
- Prüfen, ob sie jemals zu GitHub gepusht wurden.
- Wenn ja: Git-Historie bereinigen oder Repository-Risiko bewerten, Zugänge/Secrets rotieren und datenschutzrechtlich dokumentieren.
- Für lokale Entwicklung nur anonymisierte Seeds oder verschlüsselte, nicht getrackte Dumps nutzen.

### 3. Stripe-Webhooks werden nicht signiert geprüft

Schweregrad: **Kritisch**  
Betroffene Datei: `app/Http/Controllers/StripeWebhookController.php:11`

Der Webhook verarbeitet `$request->all()` direkt und vertraut `type`, `metadata.tenant_id`, `subscription` und `metadata.price_id`. Eine öffentliche Route `/stripe/webhook` kann dadurch theoretisch gefälschte Checkout-Events akzeptieren und Abos aktivieren.

Zusätzlich ist in `bootstrap/app.php:17` nicht nur `stripe/webhook`, sondern `stripe/*` von CSRF ausgenommen.

Empfehlung:

- Stripe-Signatur mit `Stripe-Signature` und `STRIPE_WEBHOOK_SECRET` prüfen.
- Nur echte Stripe-Events akzeptieren.
- Event-IDs idempotent speichern.
- Auch `customer.subscription.deleted`, `invoice.payment_failed`, `invoice.paid` und SEPA-Zahlungszustände sauber behandeln.
- CSRF-Ausnahme auf exakt die benötigte Webhook-Route begrenzen.

## Hohe Findings

### 4. Verwundbare Composer- und NPM-Abhängigkeiten

Schweregrad: **Hoch**

`composer audit` meldet 46 Security-Advisories in 15 Paketen. Sichtbar betroffen sind unter anderem:

- `dompdf/dompdf`
- `guzzlehttp/guzzle`
- `guzzlehttp/psr7`
- `setasign/fpdi`
- mehrere `symfony/*` Pakete, darunter `http-foundation`, `mailer`, `mime`, `process`, `routing`
- `niklasravnsborg/laravel-pdf` ist abandoned

`npm audit` meldet mindestens eine High-Severity-Schwachstelle in `nanoid < 3.3.18`.

Empfehlung:

- Separaten Dependency-Update-Branch erstellen.
- `composer update` gezielt mit PDF-, Mail-, Import- und Zahlungsregressionstests ausführen.
- Abandoned PDF-Paket ersetzen oder entfernen.
- `npm audit fix` prüfen und Build testen.
- Dependency-Audit in die Release-Routine aufnehmen.

### 5. Import-Bestätigung vertraut einem vom Browser gesendeten Dateipfad

Schweregrad: **Hoch**  
Betroffene Datei: `app/Http/Controllers/ImportController.php:188`, `app/Http/Controllers/ImportController.php:220`

Beim Import wird die Datei unter `temp` gespeichert und der Pfad als Hidden Field an den Browser gegeben. Beim Bestätigen wird dieser Pfad aus dem Request gelesen und direkt an `Storage::get`, `Storage::path` und `Storage::delete` weitergegeben.

Ein Vereinsadmin könnte den Hidden-Wert manipulieren und versuchen, andere Storage-Dateien zu lesen oder zu löschen, soweit der Storage-Treiber dies zulässt.

Empfehlung:

- Import-Zwischendateien über eine Datenbanktabelle mit `tenant_id`, `user_id`, UUID, Ablaufzeit und festem Storage-Pfad referenzieren.
- Im Request nur eine Import-Session-ID senden.
- Pfade serverseitig aus der DB laden und auf Prefix `temp/imports/{tenant_id}/...` begrenzen.
- Alte Temp-Dateien per Command bereinigen.

### 6. SMTP-Passwörter liegen als normales Tenant-Feld vor

Schweregrad: **Hoch**  
Betroffene Datei: `app/Models/Tenant.php:45`

`mail_password` ist fillable, aber nicht als verschlüsseltes Attribut gecastet. Wenn Vereine eigene SMTP-Zugänge hinterlegen, handelt es sich um besonders sensible Zugangsdaten.

Empfehlung:

- Laravel encrypted cast für `mail_password` verwenden.
- Migration für bestehende Klartextwerte planen.
- Passwort im Formular nie zurückgeben, nur "beibehalten" oder "neu setzen".
- Zugriff auf Mailkonfiguration strikt rollen- und auditlogpflichtig machen.

### 7. Tenant-Isolationstest schlägt fehl

Schweregrad: **Hoch**  
Betroffene Datei: `app/Models/OperatorAnnouncementDelivery.php`

Der Test `TenantIsolationScopeTest` meldet `OperatorAnnouncementDelivery.php` als ungeschütztes Modell. Das Modell enthält `tenant_id`, aber keinen Tenant-Scope. Auch wenn Betreiber-Mitteilungen ein Operator-Feature sind, sollte die Ausnahme bewusst modelliert und getestet werden.

Empfehlung:

- Entweder `BelongsToTenant` ergänzen oder das Modell eindeutig als operator-owned klassifizieren.
- Falls operator-owned: eigene Tests schreiben, die beweisen, dass normale Vereinsnutzer niemals darauf zugreifen können.
- Tenant-Isolationstest wieder grün machen.

### 8. Öffentliche Formulare, Veranstaltungsbuchungen und Gutscheinprüfung brauchen mehr Missbrauchsschutz

Schweregrad: **Hoch**

Öffentliche Formulare, Veranstaltungsanmeldungen, Einladungstoken, Gutscheinprüfung und öffentliche Listen sind absichtlich ohne Login erreichbar. Das ist fachlich richtig, aber ein Missbrauchsziel für Spam, Fake-Buchungen, Gutschein-Code-Raten und Mail-Fluten.

Empfehlung:

- Throttling pro IP, Formular, Veranstaltung und Gutschein-Code ergänzen.
- Honeypot oder CAPTCHA für öffentliche Formulare und Buchungen.
- Gutschein-Codes ausreichend lang, zufällig und nicht erratbar halten.
- Öffentliche Aktionen auditieren, aber datensparsam.

### 9. HTML-Inhalte werden an mehreren Stellen roh oder nur regex-basiert verarbeitet

Schweregrad: **Hoch/Mittel**

E-Mails und Inhalte nutzen an mehreren Stellen HTML-Editoren. Das Layout `resources/views/mail/layout.blade.php` rendert den Body roh. Betreiber-Mitteilungen nutzen einen eigenen regex-basierten Sanitizer. Event- und Protokollinhalte werden ebenfalls an mehreren Stellen als HTML ausgegeben.

Empfehlung:

- HTML serverseitig mit einer robusten Bibliothek wie HTMLPurifier bereinigen.
- Sanitizing beim Speichern durchführen, nicht erst beim Anzeigen.
- Erlaubte Tags und Attribute zentral definieren.
- Bilder, externe URLs und Styles restriktiv behandeln.

## Mittlere Findings

### 10. Security Header fehlen als zentrale Middleware

Schweregrad: **Mittel**

Es wurde keine zentrale Security-Header-Middleware gefunden. Einbettungen setzen teilweise `frame-ancestors *`, was für öffentliche Embeds bequem, aber breit ist.

Empfehlung:

- Zentrale Header setzen: HSTS, `X-Frame-Options` bzw. CSP `frame-ancestors`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`.
- Embeds separat behandeln und idealerweise pro Verein erlaubte Domains konfigurieren.

### 11. Belege liegen teilweise auf dem Public Disk

Schweregrad: **Mittel**

Belegdateien werden teilweise über den Public Disk abgelegt und über tenantgeprüfte Controller ausgeliefert. Der Controllercheck ist gut, aber der Public Disk birgt ein Restrisiko, wenn Dateien direkt über `/storage/...` erreichbar sind.

Empfehlung:

- Belege, Verträge, Rechnungsgrundlagen und sensible Anhänge auf private Disks verschieben.
- Downloads nur über Controller oder signierte URLs.
- Bestehende Dateien migrieren.

### 12. Sensitive Felder sind mass-assignable

Schweregrad: **Mittel**  
Betroffene Dateien: `app/Models/User.php:29`, `app/Models/Tenant.php:19`

`User` erlaubt mass assignment für `tenant_id`, `role`, `email_verified_at`, Login-Zeitpunkte und Opt-out-Felder. `Tenant` erlaubt mass assignment für Stripe-, Lizenz-, Demo- und Verifikationsfelder. Controller validieren vieles korrekt, aber die Modellgrenze ist breit.

Empfehlung:

- Fillable auf echte Formularfelder begrenzen.
- Kritische Felder nur über Service-Methoden ändern.
- Tests für Rollenwechsel, Tenantwechsel, Lizenzstatus und E-Mail-Verifikation ergänzen.

### 13. Globale E-Mail-Eindeutigkeit passt nicht zum Mandantenmodell

Schweregrad: **Mittel**

Benutzer-E-Mails werden global eindeutig behandelt. Dadurch kann dieselbe Person nicht sauber in mehreren Vereinen mit derselben E-Mail arbeiten. Fachlich und datenschutzseitig ist ein Identitätsmodell mit Vereinsmitgliedschaften besser.

Empfehlung:

- Langfristig `users` als Identität und eine Pivot-Tabelle `tenant_user` für Vereinsrollen nutzen.
- Keine normalen Vereinscontroller sollten durch globale E-Mail-Prüfungen Rückschlüsse auf andere Tenants ermöglichen.
- Migration sauber planen, weil das Lizenz- und Rollenmodell betroffen ist.

### 14. CAMT/XML-Import sollte explizit gehärtet werden

Schweregrad: **Mittel**

Bankimporte parsen XML. Auch wenn moderne PHP-Versionen externe Entities standardmäßig entschärft haben, sollte die Verarbeitung explizit mit sicheren Parseroptionen und Größenlimits erfolgen.

Empfehlung:

- `LIBXML_NONET` nutzen.
- Dateigrößen und Elementanzahl begrenzen.
- Fehlerausgaben ohne sensible XML-Inhalte loggen.

### 15. Betreiberaktionen brauchen vollständiges Audit Logging

Schweregrad: **Mittel**

Das Admin-Cockpit soll DSGVO-konform Support ermöglichen. Dafür ist nicht nur Zugriffsbeschränkung wichtig, sondern auch Nachvollziehbarkeit.

Empfehlung:

- Admin-Aktionen protokollieren: Verein geprüft, Lizenz geändert, Mitteilung versendet, Supportnotiz gesetzt, Datenexport ausgelöst.
- Keine fachlichen Inhalte unnötig öffnen.
- Auditlog revisionssicher und durchsuchbar machen.

### 16. Performance-Risiken können Sicherheitsrisiken werden

Schweregrad: **Mittel**

Einige Übersichten berechnen Summen und Kennzahlen über größere Datenmengen. Bei wachsenden Vereinen kann das zu langsamen Seiten und damit zu Verfügbarkeitsthemen führen.

Empfehlung:

- Aggregationen direkt in SQL ausführen.
- Pagination konsequent nutzen.
- Indizes auf `tenant_id`, Datum, Status, Fremdschlüssel und Suchfelder prüfen.
- Langlaufende Exporte in Queues verschieben.

## Niedrige Findings

### 17. Debug-Route `/envcheck`

Schweregrad: **Niedrig/Mittel**  
Betroffene Datei: `routes/web.php`

Es existiert eine Route, die App-Environment und Debugstatus ausgibt. Sie liegt zwar hinter Auth und Paywall, gehört aber nicht in produktive Vereinssoftware.

Empfehlung:

- Entfernen oder nur lokal per `app()->environment('local')` registrieren.

### 18. Backup- und Altdateien sind getrackt

Schweregrad: **Niedrig/Mittel**

Mehrere Dateien wie `routes/web.php.bak.*`, `resources/views.zip` und `database/migrations/bak.php` sind im Git-Stand. Das erhöht das Risiko, alte Logik, vertrauliche Informationen oder verwirrende Deployment-Artefakte mitzunehmen.

Empfehlung:

- Backup- und ZIP-Dateien aus Git entfernen.
- `.gitignore` um klare Regeln ergänzen.
- Release-Artefakte getrennt vom Quellcode speichern.

## Sofort-Backlog

1. **Webroot und Git bereinigen**: öffentliche Codekopien, SQL-Dumps, ZIPs und Backup-Dateien entfernen.
2. **Stripe absichern**: Webhook-Signatur, Idempotenz und Abo-Status korrekt abbilden.
3. **Dependencies aktualisieren**: Composer- und NPM-Audit abarbeiten, abandoned PDF-Paket ersetzen.
4. **Import-Tempdateien absichern**: keine clientgesteuerten Storage-Pfade mehr.
5. **SMTP-Passwörter verschlüsseln**: encrypted cast plus Migration.
6. **Tenant-Isolationstest reparieren**: `OperatorAnnouncementDelivery` bewusst absichern oder bewusst ausnehmen.
7. **Öffentliche Endpunkte drosseln**: Formulare, Buchungen, Einladungen, Gutscheinprüfung, Tracking.
8. **Security Header einführen**: zentrale Middleware, Embed-Ausnahmen sauber behandeln.

## Nächster Sprint

- Belege und sensible Anhänge auf private Storage verschieben.
- HTMLPurifier oder gleichwertige serverseitige HTML-Sanitization einführen.
- Admin-Auditlog für Betreiberaktionen bauen.
- User/Tenant-Fillables härten.
- Multi-Tenant-Identitätsmodell für gleiche E-Mail in mehreren Vereinen planen.
- XML-/CAMT-Parser weiter härten.
- Performance-Indizes und Query-Aggregationen prüfen.

## Später Einplanen

- CI-Checks für Secret Scan, Dump Scan, Webroot Scan und Dependency Audit.
- Sicherheitscheck als fester Bestandteil jeder Freitagsveröffentlichung.
- Backup- und Restore-Testlauf dokumentieren.
- Datenschutz-Retention für Tracking, Versandprotokolle und Supportdaten.
- Optional: Security-Monitoring im Admin-Cockpit mit Ampelstatus.

## Ausgeführte Prüfungen

- Code- und Routensichtung mit `rg`, `find`, `nl`, `sed`.
- Git-/Ignore-Prüfung für `.env`, `docs/ninox-import/` und `marketing/`.
- Suche nach Dumps, Backups, ZIPs und öffentlichen Codekopien.
- Sichtung von Middleware, Auth, Rollen, Registrierung, Tenant-Scopes, Import, Dokumenten, Mailtracking, Stripe, Bankimport und Datenschutzexport.
- `composer audit --no-interaction`.
- `npm audit --audit-level=low`.
- Feature-Testlauf:
  - `tests/Feature/TenantIsolationScopeTest.php`
  - `tests/Feature/ExportTenantIsolationTest.php`
  - `tests/Feature/ProjectDocumentTenantIsolationTest.php`
  - `tests/Feature/UserInvitationTest.php`
  - `tests/Feature/PrivacyCenterTest.php`

Testergebnis: 13 Tests bestanden, 1 Test fehlgeschlagen. Fehler: `TenantIsolationScopeTest` meldet `OperatorAnnouncementDelivery.php` als Modell ohne explizite Tenant-Isolation.

## Nicht Teil Dieses Audits

- Kein externer Penetrationstest.
- Kein Angriff auf Produktivsysteme.
- Keine Prüfung von Server-Firewall, SSH, Plesk, Fail2ban oder Backups.
- Keine rechtliche Bewertung im Sinne einer anwaltlichen DSGVO-Prüfung.
- Keine Codeänderungen außer dieser Dokumentation.
