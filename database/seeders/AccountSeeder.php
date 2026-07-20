<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [

            // IDEELLER BEREICH
            ['number' => '1200', 'name' => 'Forderungen aus Lieferungen und Leistungen', 'type' => 'einnahme', 'tax_area' => 'ideell'],
            ['number' => '1493', 'name' => 'Verrechnungskonto Gewinnermittlung § 4 Abs. 3 EStG', 'type' => 'einnahme', 'tax_area' => 'ideell'],
            ['number' => '1600', 'name' => 'Kasse', 'type' => 'kasse', 'tax_area' => 'ideell'],
            ['number' => '1610', 'name' => 'Nebenkasse 1', 'type' => 'kasse', 'tax_area' => 'ideell'],
            ['number' => '1800', 'name' => 'Bank', 'type' => 'bank', 'tax_area' => 'ideell'],
            ['number' => '4000', 'name' => 'Echte Mitgliedsbeiträge', 'type' => 'einnahme', 'tax_area' => 'ideell'],
            ['number' => '4040', 'name' => 'Erträge aus Spenden / Zuwendungen', 'type' => 'einnahme', 'tax_area' => 'ideell'],
            ['number' => '4213', 'name' => 'Erlöse aus Zuwendungen Dritter (Sponsoren)', 'type' => 'einnahme', 'tax_area' => 'ideell'],
            ['number' => '4700', 'name' => 'Erlösschmälerungen', 'type' => 'ausgabe', 'tax_area' => 'ideell'],
            ['number' => '6301', 'name' => 'Verwaltungskosten', 'type' => 'ausgabe', 'tax_area' => 'ideell'],
            ['number' => '6420', 'name' => 'Beiträge', 'type' => 'ausgabe', 'tax_area' => 'ideell'],
            ['number' => '7100', 'name' => 'Sonstige Zinsen und ähnliche Erträge', 'type' => 'einnahme', 'tax_area' => 'ideell'],
            ['number' => '7690', 'name' => 'Steuernachzahlungen Vorjahre', 'type' => 'ausgabe', 'tax_area' => 'ideell'],

            // ZWECKBETRIEB
            ['number' => '0135', 'name' => 'EDV-Software', 'type' => 'ausgabe', 'tax_area' => 'zweckbetrieb'],
            ['number' => '1200', 'name' => 'Forderungen aus Lieferungen und Leistungen', 'type' => 'einnahme', 'tax_area' => 'zweckbetrieb'],
            ['number' => '1215', 'name' => 'Forderungen zum allgemeinen USt-Satz (EÜR)', 'type' => 'einnahme', 'tax_area' => 'zweckbetrieb'],
            ['number' => '1406', 'name' => 'Abziehbare Vorsteuer 19 %', 'type' => 'ausgabe', 'tax_area' => 'zweckbetrieb'],
            ['number' => '1493', 'name' => 'Verrechnungskonto Gewinnermittlung § 4 Abs. 3 EStG', 'type' => 'einnahme', 'tax_area' => 'zweckbetrieb'],
            ['number' => '1600', 'name' => 'Kasse', 'type' => 'kasse', 'tax_area' => 'zweckbetrieb'],
            ['number' => '1800', 'name' => 'Bank', 'type' => 'bank', 'tax_area' => 'zweckbetrieb'],
            ['number' => '3801', 'name' => 'Umsatzsteuer 7 %', 'type' => 'einnahme', 'tax_area' => 'zweckbetrieb'],
            ['number' => '3806', 'name' => 'Umsatzsteuer 19 %', 'type' => 'einnahme', 'tax_area' => 'zweckbetrieb'],
            ['number' => '3821', 'name' => 'USt nicht fällig 7 %', 'type' => 'einnahme', 'tax_area' => 'zweckbetrieb'],
            ['number' => '4040', 'name' => 'Erträge aus Spenden / Zuwendungen', 'type' => 'einnahme', 'tax_area' => 'zweckbetrieb'],
            ['number' => '4107', 'name' => 'Erlöse aus Fortbildung/Unterricht steuerfrei', 'type' => 'einnahme', 'tax_area' => 'zweckbetrieb'],
            ['number' => '4201', 'name' => 'Erlöse aus Eintrittsgeldern', 'type' => 'einnahme', 'tax_area' => 'zweckbetrieb'],
            ['number' => '4400', 'name' => 'Erlöse 19 % USt', 'type' => 'einnahme', 'tax_area' => 'zweckbetrieb'],
            ['number' => '4520', 'name' => 'Erlöse Leergut', 'type' => 'einnahme', 'tax_area' => 'zweckbetrieb'],
            ['number' => '6304', 'name' => 'Veranstaltungskosten', 'type' => 'ausgabe', 'tax_area' => 'zweckbetrieb'],
            ['number' => '6305', 'name' => 'Kosten der Mitgliederverwaltung', 'type' => 'ausgabe', 'tax_area' => 'zweckbetrieb'],
            ['number' => '6317', 'name' => 'Aufwendungen für gemietete unbewegliche WG', 'type' => 'ausgabe', 'tax_area' => 'zweckbetrieb'],
            ['number' => '6335', 'name' => 'Instandhaltung betrieblicher Räume', 'type' => 'ausgabe', 'tax_area' => 'zweckbetrieb'],
            ['number' => '6550', 'name' => 'Garagenmiete', 'type' => 'ausgabe', 'tax_area' => 'zweckbetrieb'],
            ['number' => '6855', 'name' => 'Nebenkosten des Geldverkehrs', 'type' => 'ausgabe', 'tax_area' => 'zweckbetrieb'],

            // WIRTSCHAFTLICHER GESCHÄFTSBETRIEB
            ['number' => '1200', 'name' => 'Forderungen aus Lieferungen und Leistungen', 'type' => 'einnahme', 'tax_area' => 'wirtschaftlich'],
            ['number' => '1493', 'name' => 'Verrechnungskonto Gewinnermittlung § 4 Abs. 3 EStG', 'type' => 'einnahme', 'tax_area' => 'wirtschaftlich'],
            ['number' => '1610', 'name' => 'Nebenkasse 1', 'type' => 'kasse', 'tax_area' => 'wirtschaftlich'],
            ['number' => '1800', 'name' => 'Bank', 'type' => 'bank', 'tax_area' => 'wirtschaftlich'],
            ['number' => '3806', 'name' => 'Umsatzsteuer 19 %', 'type' => 'einnahme', 'tax_area' => 'wirtschaftlich'],
            ['number' => '3826', 'name' => 'USt nicht fällig 19 %', 'type' => 'einnahme', 'tax_area' => 'wirtschaftlich'],
            ['number' => '4090', 'name' => 'Umsatzerlöse', 'type' => 'einnahme', 'tax_area' => 'wirtschaftlich'],
            ['number' => '4100', 'name' => 'Sonstige steuerfreie Umsätze Inland', 'type' => 'einnahme', 'tax_area' => 'wirtschaftlich'],
            ['number' => '4200', 'name' => 'Erlöse', 'type' => 'einnahme', 'tax_area' => 'wirtschaftlich'],
            ['number' => '5200', 'name' => 'Wareneingang', 'type' => 'ausgabe', 'tax_area' => 'wirtschaftlich'],
            ['number' => '7300', 'name' => 'Zinsen und ähnliche Aufwendungen', 'type' => 'ausgabe', 'tax_area' => 'wirtschaftlich'],
        ];

        foreach ($accounts as $data) {
            Account::create($data + [
                'active' => true,
                'online' => false,
                'tenant_id' => 1, // Optional: bei Mandantenanwendung dynamisch setzen
            ]);
        }
    }
}
