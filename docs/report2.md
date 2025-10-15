# Αναφορά: Σελίδα Αγρού — Στατιστικά και Παρουσίαση Μετρικών

**Φοιτητής:** Καλογείτονας Γεωργιος

**Επιβλέπων Καθηγητής:** Νικόλαος Σκλάβος

**Ημερομηνία:** Οκτώβριος 2025

---

## Περίληψη



Η παρούσα αναφορά είναι οργανωμένη σε τρία διακριτά κεφάλαια  τα οποία περιγράφουν διαφορετικές φάσεις της υλοποίησης:

1. Εμπλουτισμός Σελίδα Αγρού — η τρέχουσα ενότητα: παρουσίαση της σελίδας λεπτομερειών αγρού με μετρικές και στατιστικά.
2. Real‑time Communication — υλοποίηση real‑time ενημερώσεων μέσω WebSockets (Laravel Reverb + Echo).
3. MQTT Integration — υποστήριξη επικοινωνίας των συσκευών μέσω MQTT (υπό ανάπτυξη).


# Εμπλουτισμός Σελίδα Αγρού (Metrics)

Η ενότητα που ακολουθεί περιγράφει τις προσθήκες στις μετρικές του "Farm Show": τις κάρτες συνοπτικών στατιστικών, την ενσωμάτωση των τελευταίων αναγνώσεων αισθητήρων και την οπτικοποίηση σε χάρτη.

## Στόχοι του μέρους αυτού

- Να εμφανιστεί στο χρήστη μια ενοποιημένη εικόνα του αγρού με γεωγραφικά δεδομένα (χάρτης) και συνοπτικές μετρικές.
- Να εμφανίζονται συνοπτικά στατιστικά ανά τύπο αισθητήρα (min, max, avg) και συνολικός αριθμός αισθητήρων.
- Να παρουσιάζεται η τελευταία μετρούμενη τιμή κάθε αισθητήρα για γρήγορη επισκόπηση.
- Να υποστηρίζεται real‑time ενημέρωση της σελίδας όταν εισέρχονται νέες μετρήσεις.

## Σύντομη Περιγραφή Υλοποίησης

Η σελίδα `Farm Show` συγκεντρώνει δεδομένα από δύο πηγές:

- Τη σχεσιακή βάση (Eloquent models: `Farm`, `Sensor`, `Device`) για μεταδεδομένα και την τελευταία εγγεγραμμένη τιμή (`last_reading`, `last_reading_at`).
- Την υπηρεσία χρονοσειρών (InfluxDB) για ιστορικά δεδομένα και στατιστικά μέσω της κλάσης `SensorTimeSeriesService`.

Η ροή παρουσίασης είναι η εξής:

1. Ο controller της σελίδας φορτώνει το `Farm` με τα σχετιζόμενα `sensors` (με tenant scope).
2. Για κάθε τύπο αισθητήρα καλείται η υπηρεσία χρονικών σειρών για να υπολογίσει στατιστικά (min/max/avg) σε προκαθορισμένο χρονικό εύρος.
3. Οι συνοπτικές κάρτες στατιστικών εμφανίζονται στην κορυφή της σελίδας, ακολουθούμενες από τον χάρτη και τη λίστα αισθητήρων με την τελευταία τιμή.

### Οπτικοποίηση

Η σελίδα περιλαμβάνει έναν χάρτη  που δείχνει τη θέση του αγρού και των αισθητήρων όταν υπάρχουν γεωγραφικές συντεταγμένες.

![Farm metrics](/docs/images/farm_page_with_metrics.png)

## Στατιστικά / Metrics — Τεχνικές λεπτομέρειες

Τα στατιστικά που εμφανίζονται στη σελίδα έχουν τις εξής ιδιότητες και πηγές:

- Aggregations: min, max, avg, count — υπολογίζονται στην InfluxDB μέσω Flux queries στην `SensorTimeSeriesService`.
- Εύρος δεδομένων: προεπιλεγμένα `-24h` για 24ωρα στατιστικά, με fallback σε `-7d` ή `-30d` αν δεν υπάρχουν δεδομένα (progressive range widening).
- Tagging: κάθε σημείο αποθηκεύεται στην InfluxDB με tags `user_id`, `farm_id`, `sensor_id`, `sensor_type` ώστε να επιτρέπεται γρήγορη ομαδοποίηση και φιλτράρισμα ανά αγρό.
- Local cache: για υψηλή απόδοση η εφαρμογή αποθηκεύει τη "τελευταία" μέτρηση σε πεδία του μοντέλου `Sensor` (`last_reading`, `last_reading_at`) έτσι ώστε η λίστα αισθητήρων να εμφανίζεται γρήγορα χωρίς κάθε φορά κλήση προς την InfluxDB.

Σημειώσεις υλοποίησης:

- Η `SensorTimeSeriesService::stats(int $sensorId, string $range)` επιστρέφει normalized τιμές και χειρίζεται σενάρια μη διαθεσιμότητας δεδομένων.
- Τα queries στη Flux κατασκευάζονται έτσι ώστε να εκτελούν aggregation σε επίπεδο `sensor_type` όταν απαιτείται σύνοψη ανά τύπο αισθητήρα.

### Βελτίωση απόδοσης — Deferred loading (Inertia::defer)

Για να βελτιώσουμε τον χρόνο φόρτωσης της σελίδας `Farm Show`, τα βαρύτερα ερωτήματα χρονοσειρών εκτελούνται με deferred loading. Στον controller χρησιμοποιούμε το `Inertia::defer()` ώστε η αρχική απόδοση της σελίδας να γίνεται γρήγορα με τα δεδομένα της σχεσιακής βάσης (Eloquent), ενώ οι συγκεντρωτικές μετρικές από την InfluxDB φορτώνονται ασύγχρονα και ενημερώνουν τις κάρτες/γραφικά όταν είναι διαθέσιμες.

Οφέλη:

- Μικρότερος χρόνος initial render και ταχύτερο perceived performance για τον χρήστη.
- Δυνατότητα retry / polling σε περίπτωση αποτυχίας του time‑series query.
- Διευκόλυνση caching στα aggregates για συχνά χρησιμοποιούμενα εύρη.

Παράδειγμα (εισαγωγή στην αναφορά):

```php
return Inertia::render('Farms/Show', [
  'farm' => $farm,
  'sensors' => $sensors,
  'sensorDbStats' => $sensorDbStats,
  'timeSeriesStats' => Inertia::defer(fn () => $ts->farmStats($farm, '-24h')),
]);
```



## Real‑time ενημερώσεις

Η σελίδα υποστηρίζει real‑time ενημέρωση των καρτών "Latest Reading" μέσω broadcasting:

- Το backend εκπέμπει `SensorReadingEvent` όταν μια νέα μέτρηση γράφεται και το `Sensor` ενημερώνεται (`last_reading`, `last_reading_at`).
- Ο frontend εγγράφεται στο ιδιωτικό κανάλι `sensor.{sensorId}` και ενημερώνει το UI αμέσως μόλις φτάσει το event.

Αυτό επιτρέπει στους χρήστες να βλέπουν νέες μετρήσεις στον πίνακα και στις κάρτες χωρίς refresh.


## Σχετικά αρχεία

Κάποια από τα κύρια αρχεία και components που σχετίζονται με την ενότητα "Enhanced Farm Page" είναι:

- `app/Models/Farm.php` — Eloquent model του αγρού και σχέσεις με `sensors`.
- `app/Models/Sensor.php` — μοντέλο αισθητήρα, πεδία `last_reading` και `last_reading_at`.
- `app/Http/Controllers/SensorController.php` και `app/Http/Controllers/FarmController.php` — controllers για φόρτωση δεδομένων και rendering της σελίδας.
- `app/Services/TimeSeries/SensorTimeSeriesService.php` — service για ερωτήματα και aggregation προς την InfluxDB.
- `resources/js/pages/Sensors/Show.vue` και `resources/js/pages/Farms/Show.vue` — frontend pages που εμφανίζουν τις κάρτες, τον χάρτη και τη λίστα αισθητήρων.

## Επόμενα βήματα

- Προσθήκη φίλτρου εύρους μετρήσεων στο UI: επιλογές `24h`, `7d`, `30d` και custom range (ημερομηνίες/ώρες). Το φίλτρο θα επιτρέπει στον χρήστη να επιλέγει ποιες μετρήσεις θέλει να δει και θα ενημερώνει δυναμικά τις κάρτες στατιστικών, τον πίνακα ιστορικών και τα γραφήματα.
- Backend υποστήριξη: αποστολή του επιλεγμένου εύρους ως query parameter προς τις μεθόδους της `SensorTimeSeriesService` (`recentReadings`, `stats`), με υποστήριξη limit/paging και caching για συχνά χρησιμοποιούμενα εύρη.


---


# Real‑time Communication

Η ενότητα αυτή περιγράφει την υλοποίηση του real‑time layer της πλατφόρμας, η οποία επιτυγχάνεται με WebSockets χρησιμοποιώντας Laravel Reverb (server side) και Laravel Echo (client side). Στόχος είναι η άμεση διάδοση νέων μετρήσεων και γεγονότων στον browser χωρίς να απαιτείται polling.

Αρχιτεκτονική & ροή δεδομένων

- Εγγραφή μιας νέας μέτρησης → εγγραφή στην InfluxDB (και update των πεδίων `Sensor::last_reading` στο σχεσιακό μοντέλο) → εκπομπή event `SensorReadingEvent` από το service `SensorDataService`.
```php
    event(new SensorReadingEvent(
        $sensorModel->id,
        [
            'value' => $sensorModel->last_reading,
            'time' => optional($sensorModel->last_reading_at)->toDateTimeString() ?: (string) $sensorModel->last_reading_at ?: now()->toDateTimeString(),
        ]
    ));
```
            
- Το event υλοποιεί `ShouldBroadcast` και μεταδίδεται σε ιδιωτικά κανάλια `sensor.{sensorId}`. Προς το παρόν τα events εκπέμπονται σε επίπεδο αισθητήρα και όχι σε επίπεδο αγρού.

```php

class SensorReadingEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $sensorId, public array $payload = [])
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("sensor.{$this->sensorId}");
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
```

- Ο frontend με Laravel Echo εγγράφεται στα αντίστοιχα private κανάλια· οι  listeners  είναι  η σελίδα του αισθητηρα  `resources/js/pages/Sensors/Show.vue` αλλα και το component του αισθητηρα `resources/js/components/SensorCard.vue`, τα οποία ενημερώνουν το UI (latest reading, recent readings) σε πραγματικό χρόνο.



Κύρια components / αρχεία

- [`app/Events/SensorReadingEvent.php`](https://github.com/gkalogeitonas/agronos/blob/main/app/Events/SensorReadingEvent.php) — payload με `sensor_id`, `farm_id`, `value`, `unit`, `timestamp`.
- [`app/Services/SensorDataService.php`](https://github.com/gkalogeitonas/agronos/blob/main/app/Services/SensorDataService.php) — επεξεργασία εισερχόμενων payloads, ενημέρωση `Sensor` μοντέλου, dispatch job για InfluxDB και εκπομπή `SensorReadingEvent`.
- [`routes/channels.php`](https://github.com/gkalogeitonas/agronos/blob/main/routes/channels.php) — policy checks για ιδιωτικά κανάλια (εξουσιοδότηση χρήστη για πρόσβαση σε `sensor.{id}` / `farm.{id}`).
- [`resources/js/bootstrap/echo.ts`](https://github.com/gkalogeitonas/agronos/blob/main/resources/js/bootstrap/echo.ts) — αρχικοποίηση του Echo client (authToken, broadcaster, host).
- [`resources/js/pages/Sensors/Show.vue`](https://github.com/gkalogeitonas/agronos/blob/main/resources/js/pages/Sensors/Show.vue) — σελίδα αισθητήρα (listener που ενημερώνεται σε πραγματικό χρόνο).
- [`resources/js/components/SensorCard.vue`](https://github.com/gkalogeitonas/agronos/blob/main/resources/js/components/SensorCard.vue) — component που εγγράφεται σε per‑sensor κανάλι και εμφανίζει live την τελευταία τιμή.
- [`config/broadcasting.php`](https://github.com/gkalogeitonas/agronos/blob/main/config/broadcasting.php) — ρύθμιση driver (reverb/pusher compatible) και connection settings.

Client implementation (συνοπτικά)

- Εγκαθιστούμε και ρυθμίζουμε το Echo ώστε να συνδεθεί στο Reverb websocket endpoint.
- Οι κύριες subscriptions γίνονται σε επίπεδο αισθητήρα, π.χ. από το component `SensorCard.vue` και από τη σελίδα `Sensors/Show.vue`:

- Η σελίδα `Farms/Show.vue` αυτή τη στιγμή δεν κάνει subscription στα per‑sensor channels — αντίθετα, λαμβάνει τα aggregated time‑series στατιστικά μέσω `Inertia::defer()` και εμφανίζει τις κάρτες όταν τα δεδομένα είναι διαθέσιμα.

Ασφάλεια & fallback

- Τα ιδιωτικά κανάλια απαιτούν authorization — οι έλεγχοι γράφονται σε `routes/channels.php` και βασίζονται στο policy του `Farm/Sensor`.
- Όταν WebSockets δεν είναι διαθέσιμα (περιορισμοί δικτύου ή browser), υπάρχει fallback σε deferred polling: το frontend μπορεί να κάνει periodical Inertia/Fetch αίτημα για να ανακτήσει `timeSeriesStats` ή `sensorDbStats`.


<!-- Dark inline code styling for this document -->
<style>
/* Dark backdrop for inline code in this document */
code, kbd, samp {
  background-color: #f5f5f5; /* light gray, similar to default code background */
  color:rgb(12, 13, 14) !important;
  padding: 0 .25rem;
  border-radius: .375rem;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, "Roboto Mono", "Lucida Console", monospace;
  font-size: .95em;
}

/* Keep block code blocks unchanged */
pre code {
  background: transparent;
  padding: 0;
  border-radius: 0;
}
</style>
