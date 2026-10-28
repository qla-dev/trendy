# Nabavka sirovina — audit i implementacija

Rad je na grani `cherry-2`. Postojeći Laravel 9 / Blade / Bootstrap 5 modul je proširen; nema zasebne aplikacije, novih npm/Composer zavisnosti, migracija ili Artisan deployment koraka.

## Analiza postojećeg sistema i plan

- `WorkOrderController`, `config/workorders.php`: izvor proizvodnje je ciljana SQL Server konekcija `services.work_order.target_connection`, zaglavlja `tHF_WOEx`, sastavnice `tHF_WOExItem`, katalog `tHE_SetItem`. Lokalni Eloquent `Composition` nije izvor aktivnog toka. Materijali se prepoznaju po postojećim klasama i tipu M; OPR se isključuje, zatvoreni RN-ovi se ne nude za novu nabavku.
- `WorkOrderOrderItemLink`: postojeće veze `vHF_LinkWOExOrderItem` / `tHF_LinkWOExOrderItem`, uz rezervne `acLnkKey` i `anLnkNo` zaglavlja. Svaka potreba zadržava ključ RN-a, QID/broj/varijantu stavke. Više veza narudžbi ne umnožava količinu materijala.
- Skener `sirovina-scan.blade.php` čuva dimenzije u napomeni; poznati PLANNED_BOM/PLANNED_RAW prefiksi se uklanjaju samo u prikazu.
- Postojeći ekrani narudžbi i skladišta koriste Bootstrap kartice, input-group pretragu, automatske filtere, horizontalno skrolanje i kompaktne tabele. Novi ekran slijedi isti obrazac, bez novog UI frameworka.
- Postojeći raspored RN-a koristi `adSchedStartTime`. Konfiguracija `procurement.schedule_field` određuje osnovni datum, a `procurement.lead_days` pripremu nabavke (početno 7 kalendarskih dana). Ne koristi se datum kreiranja kao zamjena kada raspored nedostaje.
- `app-material.js` i štampa RN-a već koriste QRServer za generisanje QR-a; skeniranje koristi Html5Qrcode. Novi kod ponovo koristi taj servis i proširuje postojeći skener prepoznavanjem URL-a prijema. Postojeći materijalni QR i barcode tok ostaju dostupni.
- `User`, `CustomRoles`, `UserController`, postojeća prijava i Sanctum: dodana je uloga Dobavljač i odvojena serverska provjera pregleda, izmjene, upravljanja i prijema.
- BCMath se već koristi u proizvodnim obračunima. Finansije nabavke koriste decimalne stringove i isti postojeći izvor podrazumijevanog PDV-a/valute.
- Postojeći ERP servisi pišu narudžbe kupaca i proizvodne/skladišne dokumente. SELECT provjera u ciljnoj bazi nije našla primjer dokumenta `0205` u konfiguriranoj tabeli narudžbi. Dostavljeni naziv 0205 nije dovoljan za pretpostavljanje sigurnog upisa.

Izvedeni redoslijed: UI i odabir → količinske raspodjele i finansije → rokovi i prijem → QR/etikete/dokumenti/reklamacije → adapter ERP-a → portal dobavljača → KPI i nacrti poruka → testovi.

## IMPLEMENTIRANO

### Materijali za nabavku

Odvojene kolone Narudžba, Poz., Radni nalog, Materijal, Dimenzije/Napomena, potrebna/naručena/primljena/preostala količina, potreban prijem, status i akcije. Nema checkboxa u zaglavlju niti teksta o varijanti u ćeliji RN-a. Redovi se ne prelamaju; dugi sadržaji imaju tooltip, a tabela horizontalni scroll. Koriste se standardni font i razmaci tabele kao kod upravljanja narudžbama. Prikaz koristi decimalno formatiranje s decimalnim zarezom i podesivom preciznošću po jedinici.

Na oba pregleda pretraga je stalno vidljiva izvan skrivenog panela dodatnih filtera. Panel se početno otvara dugmetom Pokaži filtere; stanje otvaranja zadržava se tokom automatskog filtriranja kroz parametar `filters_open`. Duplirani gornji navigacijski tabovi su uklonjeni; stranice su dostupne u postojećoj sekciji Nabavka u sidebaru, uključujući Dobavljači i postavke. Stavke koje u `acNote` ili `acFieldSE` sadrže doslovnu oznaku `REZERVACIJA_DODATNIH_SIROVINA` isključene su iz izvornog upita, uključujući ponovnu provjeru pri kreiranju. Oznaka se provjerava neovisno o velikim/malim slovima, a donje crte nisu LIKE wildcard znakovi.

Odabir do 100 potreba čuva se u korisničkoj sesiji preko filtriranja i paginacije. Vidljiv je broj odabranih i akcija Poništi odabir. Pretraga obuhvata broj narudžbe i pozicije, RN, šifru/naziv materijala i napomene/dimenzije. Filteri uključuju jedinicu, RN, dobavljača, datume, samo odabrane i statuse: nije/djelimično naručeno, naručeno, djelimično/primljeno, kasni, uskoro i bez potrebe. Nulta potreba se ne može nabaviti.

### Nabavke i raspodjele

Jedna narudžbenica može sadržavati potrebe više RN-ova. Opcija Spoji kompatibilne potrebe spaja samo identičan materijal, dimenzije/specifikaciju, jedinicu i porezni profil. Svaka spojena linija čuva pojedinačne raspodjele i količine prema izvorima. Jedna potreba može biti nabavljena kroz više narudžbenica do ukupne potrebe. Primljene količine se raspoređuju po sačuvanom redoslijedu raspodjela (FIFO); drugačiji prioritet zahtijeva poslovnu odluku.

Backend ponovo čita izvore, provjerava zbir raspodjela i preostalu potrebu pod zaključavanjem evidencije. Ponovljeno slanje obrasca vraća isti dokument. Prekoračenje potrebe traži ovlaštenje upravljanja, izričitu potvrdu i obrazloženje. Količina i jedinica su izvorne; opciona težina u KG je zasebna. Konverzije se ne izmišljaju.

Zaglavlje sadrži dobavljača, datum, rok, važenje, prijevoznika, odjel, vrstu nabavke, odgovornu osobu, referenta, kurs i napomene. Navedeni dodatni tekstualni podaci su lokalni; ne predstavljaju potvrđena ERP polja niti automatski mapirane ERP identifikatore.

### Finansije i rokovi

Količina/cijena/rabat/PDV imaju do šest decimala. Osnovica = količina × cijena, zaokruženo half-up na dvije decimale; zatim se zaokružuje rabat, oduzima od osnovice i obračunava PDV na umanjenu osnovicu. Ukupni iznosi su zbir zaokruženih stavki. Podržan je i PDV 0%. Frontend koristi BigInt, backend BCMath. Količine različitih jedinica ne sumiraju se kao zajednička kilaža; KPI ih grupiše po jedinici, a vrijednosti po valuti.

Svaka stavka ima rok ili nasljeđuje obavezni rok zaglavlja. Za spojene potrebe predlaže se najraniji potreban datum. Rokovi se mogu izmijeniti pri pripremi/izmjeni lokalne narudžbenice, uz audit. Kašnjenje koristi Europe/Sarajevo i samo neprimljene stavke. Potpuno primljena ranija stavka ne označava preostale buduće isporuke zakašnjelim. Otkazane nabavke ne kasne. Dobavljačev novi očekivani datum ne mijenja ugovoreni rok.

Izmjena je blokirana nakon prijema, otkazivanja ili ERP potvrde. Za neizmijenjeni izvor materijala zadržava se identitet linije/QR-a. Stariji zapisi dobijaju stabilne identifikatore pri čitanju bez destruktivne konverzije; starije KG nabavke za izvor u drugoj jedinici traže ručnu provjeru i ne koriste se za izmišljenu konverziju potreba.

### QR, prijem i dokumenti

Narudžbenica i njene stavke imaju različite stabilne URL reference. QR ne sadrži izmišljeni datum prijema: autentificirani lookup čita aktuelne podatke. Skeniranje samo otvara obrazac, ništa ne knjiži i ne potvrđuje. Postojeći skener materijala prepoznaje URL prijema samo za isti origin.

Prijem evidentira novi događaj: token protiv ponovljenog slanja, broj prijemnice, stvarni datum, postojećeg zaposlenika, postojeću Pantheon šifru skladišta, prihvaćene i odbijene količine i napomenu. Podržane su djelimične i višestruke isporuke. Samo prihvaćene količine ispunjavaju potrebu. Nepoznato skladište, budući datum, dupli broj prijemnice i prekomjerni prijem se odbijaju. Odbijene količine se ne knjiže u raspoloživu zalihu.

Dokumenti su privatni, s nasumičnim imenom, MIME/veličinskom provjerom (PDF, JPG/PNG, DOCX do 10 MB) i kontrolisanim downloadom. Mogu biti vezani za stavku i reklamaciju; interni dokumenti nisu vidljivi dobavljaču bez izričitog dijeljenja. Fakture i atesti dobavljača ostaju vezani za njegovu nabavku.

A4 ekran podržava izbor etiketa iz više narudžbenica, pojedinačne pozicije, početno mjesto, preskakanje zauzetih mjesta, pregled, dimenzije/margine/razmake/pomake u mm i browser štampu/PDF. Stanje papira i geometrija čuvaju se u pregledniku. Zauzeta mjesta se ažuriraju tek nakon korisničke potvrde uspješne štampe, samo za stvarno raspoređene etikete. Neuspjelo učitavanje QR-a blokira štampu.

### Reklamacije, portal i obavijesti

Reklamacije se vežu za dobavljača/nabavku/stavku i opcioni prijem, imaju broj, datum, tip, opis, odgovornu osobu, očekivano/primljeno/prihvaćeno/odbijeno i status. Prilozi/fotografije se dodaju kroz dokumente reklamacije. Tok: otvorena → poslana/u obradi → riješena/odbijena, s bilješkom i datumom rješenja. Završeni status se ne mijenja proizvoljno.

Dobavljački korisnik se kreira u postojećem upravljanju korisnicima s ulogom Dobavljač, zatim ga ovlašteni upravitelj poveže s tačnim Pantheon subjektom i kontaktom kroz Dobavljači i postavke. Nepovezan nalog je blokiran. Dobavljač vidi samo svoje narudžbenice i količine/specifikacije, bez internih cijena, RN veza, proizvodnih i administrativnih podataka. Može potvrditi narudžbu, najaviti kašnjenje s novim očekivanim datumom i razlogom, učitati fakturu/atest i vidjeti relevantne reklamacije. Backend provjerava vlasništvo nad svakim pristupom, uključujući download. Dobavljač je blokiran na ostalim web i autentificiranim API rutama; pri prijavi ne dobija interni API token.

Interne obavijesti su prikazane u detaljima i KPI/filteru najava dobavljača, uz mogućnost označavanja pregledanim. Historija bilježi nastanak, izmjene, prijeme, otkazivanje, ERP pokušaje, dokumente, potvrde/kašnjenja i reklamacije. Postojeći mailing nije aktiviran za automatsko slanje: ponuđeni su ručni predlošci, kontakti, pregled/izmjena i preuzimanje .eml nacrta. e-Nalog na tom ekranu ne šalje email.

KPI: aktivne, zakašnjele i djelimične nabavke, rok u sedam dana, najave dobavljača, zakašnjele stavke, neriješene reklamacije, neisporučene količine po jedinici i vrijednosti po valuti.

## DJELIMIČNO IMPLEMENTIRANO

- Prijem je vezan za postojeće korisnike, skladišne šifre, skener i materijale. Stvarno ERP knjiženje prijema i povećanje zaliha nisu uključeni; time se ne pravi drugi autoritativni sistem skladišnih zaliha.
- A4 geometrija je operativno podesiva, ali originalni `prazno - naljepnice za skladiste.docx` nije pronađen. Podrazumijevani raspored je početni profil, ne potvrđena kopija Word predloška. Fizička štampa, kalibracija konkretnog štampača i skeniranje odštampanog QR-a zahtijevaju stvarni papir/uređaj.
- Nacrti poruka i kontakt postoje; automatsko slanje, eskalacije i praćenje email odgovora nisu uključeni.
- Evidencija ostaje u postojećem privatnom JSON spremištu, proširenom za novi domen. Nema relacijske šeme/tabela nabavke i nije pokretana migracija. Za više aplikacijskih servera ili veći obim potreban je naknadno odobren zajednički transakcijski storage; trenutni dizajn koristi lokalni trajni disk i flock.

## BLOKIRANO / ZAHTIJEVA VANJSKI PRISTUP

Stvarni upis Pantheon `0205` i prijemnih dokumenata. `ProcurementGateway` je odvojen ugovor s trajnim ključem idempotentnosti, a podrazumijevani `UnconfiguredPantheonProcurementGateway` blokira prijenos. Za uključivanje su potrebni potvrđeni primjer 0205, smjer/identifikatori subjekata, obavezna polja i statusi, porezni profil, način numeracije, ERP veza raspodjela i prijema te sigurna provjera već kreiranog dokumenta nakon prekida. Testni adapteri pokrivaju potvrdu, grešku, ponovni pokušaj s istim ključem i izostanak duplikata; to nisu produkcijski ERP upisi.

Stvarna sinhronizacija se ne smatra uspješnom bez potvrde i trajnog ERP ključa/broja. Lokalni dokument ostaje sačuvan nakon greške. Puko mijenjanje konfigurirane šifre ne uključuje neprovjereni upis.

## Fajlovi i konfiguracija

Glavni novi/prošireni fajlovi: `app/Services/Procurement/*`, `app/Http/Controllers/ProcurementController.php`, `SupplierProcurementController.php`, `app/Http/Middleware/RestrictSupplier.php`, `config/procurement.php`, `resources/views/content/apps/procurement/*`, `resources/js/scripts/pages/procurement*.js`, `tests/Feature/Procurement*.php`, `tests/Unit/ProcurementCalculatorTest.php`, `tests/js/procurement.cjs`.

Postojeće integracije: `routes/web.php`, `routes/api.php`, `User.php`, `UserController.php`, `AuthenticationController.php`, `Kernel.php`, `AppServiceProvider.php`, `MenuServiceProvider.php`, `CustomRoles.php`, `mainMenu.json`, `sirovina-scan.blade.php`, build manifest.

Spremište: `storage/app/procurement/<hash ciljane konekcije/servera/baze>/ledger.json`, odvojena zaključana registry evidencija veza dobavljačkih naloga i privatni dokumenti u `storage/app/procurement-documents`. JSON se čita i piše pod zasebnim lockom, kroz privremenu datoteku i atomsku zamjenu. Oštećen zapis blokira rad umjesto resetiranja. Svi navedeni direktoriji moraju biti trajni, upisivi i uključeni u backup. Privatne dokumente ne izlagati kroz javni storage symlink.

Deployment ostaje pull → npm dependencies → frontend build. Bez Composer instalacije, Artisan komandi i migracija. U cPanel File Manageru provjeriti storage dozvole i backup. Nisu kreirani lažni produkcijski zapisi ni slani vanjski emailovi.

Potrebna poslovna potvrda: datum RN-a i sedmodnevni pomak, FIFO raspodjela prijema, dozvoljena prekoračenja, konačna geometrija etiketa i ERP mapiranje. Konfiguracija preciznosti i pravila je u `config/procurement.php`; `PROCUREMENT_SCHEDULE_FIELD` i `PROCUREMENT_LEAD_DAYS` su podesivi bez promjene koda.

## Provjere

PHPUnit testovi novog modula koriste izolovani privremeni ledger, mock izvore/ERP adaptere i SQLite in-memory sastavnice; ne kreiraju stvarne ERP dokumente. Pokrivaju UI strukturu, izbor, filtere, izvore i veze, rokove, finansije, jedinice, grupisanje/raspodjelu, djelimične nabavke/prijeme, duplikate, dokumente, reklamacije, portal i zabranu drugih ruta.

Završne provjere:

- PHPUnit `Procurement|CustomRolesTest|ScannedWorkOrderOperationsTest`: **60 testova / 314 provjera — sve prolazi**. Uključene su ciljane regresije postojećih uloga i skenera, početno skriveni filteri, izdvojena pretraga, navigacija i isključenje rezervacija. Poseban test provjerava da HTTP prijem ne može zaobići obavezno skladište, datum i token slanjem starog oblika obrasca.
- Dva Node testa: finansije (uključujući rabat, velike iznose i zaokruživanje) i A4 geometrija/raspoređivanje uz preskakanje zauzetih mjesta — prolaze.
- `npm run production`: uspješno, uz četiri postojeća webpack upozorenja i upozorenje o starosti browser podataka. Nove zavisnosti nisu dodane.
- PHP lint svih novih i izmijenjenih PHP fajlova, sintaksa JS-a i `git diff --check`: prolaze. Blade predlošci se dodatno parsiraju kroz testove, a ključni ekrani se renderuju kroz HTTP testove.
- Stvarni izvor: samo SELECT čitanje; nakon isključenja oznake rezervacija lista vraća 461 potrebu i 40 stavki na prvoj stranici. Ranija provjera prije ovog filtera vraćala je 471 potrebu, s predloženim rokovima prema konfiguriranom rasporedu. Nije izvršen ERP upis.
- Fizički otisak i čitljivost odštampanih etiketa nisu provjereni bez originalnog predloška/papira/štampača; testirana je programska geometrija. Testovi strukture HTML-a nisu zamjena za završni pregled na konkretnom uređaju.

Ranija šira Unit suite je imala 6 grešaka i 4 pada u drugim modulima; ti testovi nisu obrisani ili zamijenjeni. Ovdje navedena ciljna suite je zelena; ne tvrdi se da je cijeli historijski testni skup bez grešaka.

## Dopuna 28.10.2026. — zaglavlje i plan proizvodnje

Padajući meni Todo/Chat/Email/Kalendar iz zajedničkog breadcrumb zaglavlja skriven je na rutama modula nabavke (`app-procurement*`); ostali ekrani nisu mijenjani tim uslovom.

U planu proizvodnje označavanje prethodnih nezavršenih sedmica i prioritet pri početnom sortiranju ranije su zavisili od odabrane sedmice, iako je to polje na početnom ekranu prazno. Sada se bez odabira koristi početak tekuće poslovne sedmice; s odabirom ostaje početak odabrane sedmice. Ista logika je u tabeli i Excel izvozu. Postojeći filter godine, statusa, ostali filteri i izričito korisničko sortiranje ostaju primijenjeni. NULL status se tretira jednako kao u postojećem filteru nezaključenih naloga.

Provjere: 69 PHP testova / 375 provjera prolazi; Node testovi sedmica/datuma i live osvježavanja prolaze. Raniji Node test detalja operacija pada na očekivanju `text-success`; i taj test i njegov JS izvor identični su HEAD-u i nisu mijenjani u ovoj popravci. Provjera stvarne `data()` metode samo čitanjem vraća status 200, ukupno 351 nalog i svih 25 stavki prve stranice označenih crveno kao nezavršene iz ranijih sedmica. Nije bilo izmjena poslovnih podataka niti migracija.

### Pravilo ranijih RN-ova uz datume isporuke

Svih šest polja (`isporuka_od/do`, `trendy_germany_date_from/to`, `grob_date_from/to`) sada aktivira isto pravilo prijenosa ranijih nezaključenih RN-ova. Raspon uključuje normalne stavke u periodu i nezaključene stavke s datumom isporuke prije granice Od. Zaključeni F/I/Z izvan perioda se ne dodaju. Ako je zadan samo Do, datum isporuke ostaje ograničen gornjom granicom, a crvena oznaka koristi današnji lokalni poslovni datum.

Trendy i ostali naručitelji imaju nezavisne granice; grupa bez aktivnog datuma isporuke zadržava prethodno pravilo planiranog početka. Opći filter datuma isporuke odnosi se na obje grupe. Ostali filteri (godina, sedmica, status, naručitelj, ručno zadani početni termini itd.) ostaju primijenjeni. Pri aktivnim datumima isporuke zakašnjeli su prvi, a izabrano sortiranje se primjenjuje unutar tih grupa. Tabela i Excel koriste iste uslove i isti izvor datuma isporuke iz narudžbe/pozicije; nedostajući datum se ne izmišlja.

Provjere proširenja: 78 PHP testova / 423 provjere prolazi; Node sedmice/datumi i live osvježavanje prolaze. Stvarne read-only provjere općeg, Trendy i ostalog raspona vraćaju uspješan odgovor i crvene ranije RN-ove na početku liste. Poslovni podaci nisu mijenjani.

### Crvena boja ima prednost nad statusom i prioritetom

Bojanje sada ima poseban `is_late` indikator: prošli datum isporuke uvijek označava kašnjenje, uz zadržavanje postojećih oznaka ranije sedmice/raspona. Status i prioritet ne poništavaju crvenu oznaku, uključujući zaključene statuse ako ih korisnik izričito uključi u prikaz. Nedostajući datum se ne izmišlja, a današnji ili budući rok sam po sebi nije zakašnjeli. Postojeći filter Bez boje i opcija izvoza bez boja ostaju dostupni.

Sortiranje, uključivanje ranijih nezaključenih RN-ova i statusni filteri ostaju nepromijenjeni; samo prikaz i Excel dobivaju prioritetnu crvenu oznaku. Frontend verzija JS-a je povećana da preglednik učita novo pravilo. Provjere: 25 PHP testova / 270 provjera i tri Node testa (boje, sedmice/datumi, live osvježavanje) prolaze. Read-only provjera 100 stavki plana sedmice 42 našla je 99 s prošlim datumom isporuke i nijednu koja nije crvena.
