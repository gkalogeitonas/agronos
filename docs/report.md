# Σχεδιασμός και Υλοποίηση Πλατφόρμας Έξυπνης Γεωργίας με Ενσωμάτωση IoT Κόμβων — Αναφορά Προόδου Διπλωματικής Εργασίας

**Φοιτητής:** Καλογείτονας Γεωργιος

**Επιβλέπων Καθηγητής:** Νικόλαος Σκλάβος 

**Ημερομηνία:** Αύγουστος 2025  

---

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

##  Περίληψη

Η παρούσα αναφορά παρουσιάζει την πρόοδο υλοποίησης της διπλωματικής εργασίας με τίτλο: **«Σχεδιασμός και Υλοποίηση Πλατφόρμας Έξυπνης Γεωργίας με Ενσωμάτωση IoT Κόμβων»**. 
Η εφαρμογή που αναπτύσσεται στο πλαίσιο της εργασίας ονομάζεται **Agronos**. Το έργο έχει σημειώσει  πρόοδο στην ανάπτυξη ενός ολοκληρωμένου συστήματος διαχείρισης γεωργικών δεδομένων με υποστήριξη πολλαπλών χρηστών (multi-tenant), ασφαλή αυθεντικοποίηση IoT συσκευών και αποδοτική αποθήκευση δεδομενων χρονοσειρας.

Ο πηγαίος κώδικας είναι διαθέσιμος στο GitHub: https://github.com/gkalogeitonas/agronos  
Αποθετήριο firmware συσκευών (device firmware): https://github.com/gkalogeitonas/Agronos-iot-device  
Ζωντανή δοκιμαστική εγκατάσταση της εφαρμογής: https://agronos.kalogeitonas.xyz/

##  Στόχοι Έργου

### Κύριοι Στόχοι
- **Multi-tenant Backend**: Ανάπτυξη Laravel backend με απομόνωση δεδομένων μεταξύ χρηστών
- **IoT Integration**: Ασφαλής αυθεντικοποίηση και συλλογή δεδομένων από γεωργικές συσκευές
- **Time-Series Storage**: Αποθήκευση μετρήσεων αισθητήρων σε InfluxDB ή οποία ειναι μια time series Database για αποδοτικότερη διαχειριση των δεδομενων
- **Web Interface**: Διαδραστικό περιβάλλον χρήστη για διαχείριση αγρών, συσκευών και αισθητήρων
- **API Architecture**: RESTful API — το API είναι σχεδιασμένο αποκλειστικά για επικοινωνία με τις IoT συσκευές (αυθεντικοποίηση συσκευών και αποστολή δεδομένων). 


### Βασικά Στοιχεία Αρχιτεκτονικής

- **Frontend**: Vue.js  για SPA εμπειρία
- **Backend**: Laravel 12 
- **Relational Database**: MySQL για τα κύρια δεδομένα της εφαρμογής
- **Time-Series Database**: InfluxDB για τις  μετρήσεις των  αισθητήρων
- **Authentication**: Laravel Sanctum για device tokens
- **Multi-tenancy**: Global scopes μεσω  trait για απομόνωση δεδομένων

##  Χαρακτηριστικά

### 1. Σύστημα Διαχείρισης Χρηστών

**Λειτουργίες:**
- Εγγραφή και σύνδεση χρηστών (scaffolded μέσω Laravel starter kit)
- Διαχείριση προφίλ χρήστη

![Σελίδα Εγγραφής](images/Screenshot%202025-08-21%20at%2023-06-48%20Register%20-%20Laravel.png)

![Σελίδα Σύνδεσης](images/Screenshot%202025-08-21%20at%2023-24-56%20Log%20in%20-%20Laravel.png)

![Ρυθμίσεις Προφίλ](images/Screenshot%202025-08-21%20at%2023-29-14%20Profile%20settings%20-%20Laravel.png)




### 2. Διαχείριση Αγρών (Farms)

**Λειτουργίες:**
- Δημιουργία και επεξεργασία αγρών
- Γεωγραφικός εντοπισμός με συντεταγμένες

![Λίστα Αγρών](images/Screenshot%202025-08-21%20at%2023-25-20%20Farms%20-%20Laravel.png)
*Εικόνα: Λίστα όλων των αγρών του χρήστη με βασικές πληροφορίες και επιλογές διαχείρισης.*

![Λεπτομέρειες Αγρού](images/Screenshot%202025-08-21%20at%2023-25-32%20Farm-Show%20-%20Laravel.png)
*Εικόνα: Σελίδα λεπτομερειών αγρού με πλήρη στοιχεία, τοποθεσία και συσχετισμένους αισθητήρες/συσκευές.*

![Δημιουργία Αγρού](images/Screenshot%202025-08-21%20at%2023-27-57%20Create%20Farm%20-%20Laravel.png)
*Εικόνα: Φόρμα δημιουργίας νέου αγρού με πεδία ονόματος, τοποθεσίας και περιγραφής.*

![Επεξεργασία Αγρού](images/Screenshot%202025-08-21%20at%2023-27-28%20Edit%20Farm%20-%20Laravel.png)
*Εικόνα: Φόρμα επεξεργασίας υπάρχοντος αγρού με δυνατότητα αλλαγής στοιχείων.*

**Σχετικά αρχεία υλοποίησης (GitHub):**
- [Farm model](https://github.com/gkalogeitonas/agronos/blob/main/app/Models/Farm.php): Η Eloquent οντότητα που αναπαριστά τον αγρό και τα πεδία του στη βάση δεδομένων. Περιλαμβάνει το trait με το global scope για απομόνωση tenant.
- [Farm controller](https://github.com/gkalogeitonas/agronos/blob/main/app/Http/Controllers/FarmController.php): Ο controller για τις CRUD λειτουργίες και τη διαχείριση αγρών μέσω web UI.
- [Farm policy](https://github.com/gkalogeitonas/agronos/blob/main/app/Policies/FarmPolicy.php): Η policy που ορίζει τα δικαιώματα πρόσβασης και διαχείρισης αγρών ανά χρήστη. 

### 3. Διαχείριση Συσκευών (Devices)

**Λειτουργίες:**
- Εγγραφή συσκευών με UUID και secret (είτε με χειροκίνητη εισαγωγή είτε με σάρωση QR code που συνοδεύει τη συσκευή — η σάρωση συμπληρώνει αυτόματα τα στοιχεία στη φόρμα)
- Με την καταχώρηση της συσκευής, η εφαρμογή ενημερώνεται για την ύπαρξή της και τη συσχετίζει με τον χρήστη που την καταχώρησε. Η συσκευή λαμβάνει αυτόματα status `registered`.
- Παρακολούθηση κατάστασης (online/offline)
- Διαχείριση κύκλου ζωής συσκευής

![Λίστα Συσκευών](images/Screenshot%202025-08-21%20at%2023-26-52%20Devices%20-%20Laravel.png)
*Εικόνα: Λίστα συσκευών του χρήστη με βασικές πληροφορίες, κατάσταση και επιλογες διαχείρισης.*

![Εγγραφή Συσκευής](images/Screenshot%202025-08-21%20at%2023-28-54%20Register%20Device%20-%20Laravel.png)
*Εικόνα: Φόρμα καταχώρησης νέας συσκευής — ο χρήστης μπορεί να συμπληρώσει τα στοιχεία χειροκίνητα ή να σκανάρει QR code για αυτόματη συμπλήρωση.*

**Ροή ενεργοποίησης και αυθεντικοποίησης συσκευής:**
- Αφού η συσκευή καταχωρηθεί και συσχετιστεί με τον χρήστη, όταν ενεργοποιηθεί για πρώτη φορά, επικοινωνεί με το backend μέσω του κατάλληλου API endpoint (`/api/v1/device/login`), στέλνοντας το παρακάτω JSON payload:

```json
{
  "uuid": "device-uuid-string",
  "secret": "device-secret"
}
```

- Εφόσον τα στοιχεία είναι σωστά, το backend εκδίδει ένα token (μέσω Sanctum) και επιστρέφει:

```json
{
  "token": "plain-text-token"
}
```

- Με την επιτυχή αυθεντικοποίηση, το status της συσκευής αλλάζει αυτόματα σε `online` και ενημερώνεται το `last_seen_at`.
- Για κάθε επόμενη επικοινωνία (π.χ. αποστολή μετρήσεων), η συσκευή χρησιμοποιεί το token ως bearer token.
- Μελλοντικά, το σύστημα θα υποστηρίζει αυτόματη μετάβαση της συσκευής σε κατάσταση `offline` αν δεν επικοινωνήσει για προκαθορισμένο χρονικό διάστημα.

**Σχετικά αρχεία υλοποίησης (GitHub):**
- [Device model](https://github.com/gkalogeitonas/agronos/blob/main/app/Models/Device.php): Η Eloquent οντότητα που αναπαριστά τη συσκευή και τα πεδία της στη βάση δεδομένων.
- [Device controller](https://github.com/gkalogeitonas/agronos/blob/main/app/Http/Controllers/DeviceController.php): Ο controller για τις CRUD λειτουργίες και τη διαχείριση συσκευών μέσω web UI.
- [Device policy](https://github.com/gkalogeitonas/agronos/blob/main/app/Policies/DevicePolicy.php): Η policy που ορίζει τα δικαιώματα πρόσβασης και διαχείρισης συσκευών ανά χρήστη.
- [DeviceAuthController (API)](https://github.com/gkalogeitonas/agronos/blob/main/app/Http/Controllers/Api/V1/DeviceAuthController.php): Ο API controller που υλοποιεί το endpoint αυθεντικοποίησης συσκευών (login και έκδοση token).

### 4. Διαχείριση Αισθητήρων (Sensors)

**Λειτουργίες:**
- QR code scanning για γρήγορη εγγραφή
- Χειρονακτική εισαγωγή αισθητήρων
- Παρακολούθηση τελευταίων μετρήσεων

Κάθε αισθητήρας στην εφαρμογή έχει τα παρακάτω πεδία που μπορουν να συμπληρωθούν κατά την εγγραφή ή την επεξεργασία:

- **Name**: Το εμφανιζόμενο όνομα που μπορεί να δώσει ο χρήστης για εύκολη αναγνώριση.
- **Farm**: Συσχέτιση με τον αγρό όπου τοποθετήθηκε ο αισθητήρας.
- **Type**: Ο τύπος του αισθητήρα (π.χ. temperature, humidity, moisture κ.λπ.).
- **Sensor UUID**: Το μοναδικό UUID του αισθητήρα. Αυτό βρίσκεται στο QR code που είναι τοποθετημένο πάνω στον ίδιο τον αισθητήρα και είναι διαφορετικό από το QR code της συσκευής.
- **Device UUID**: Το UUID της συσκευής  στην οποία είναι συνδεδεμένος ο αισθητήρας. Το Device UUID μπορεί να συμπληρωθεί αυτόματα από το QR του αισθητήρα  ή να επιλεχθεί/εισαχθεί χειροκίνητα.
- **Latitude / Longitude**: Γεωγραφικές συντεταγμένες της θέσης του αισθητήρα. Προτείνεται η αυτόματη συμπλήρωση μέσω GPS του κινητού/συσκευής κατά τη σάρωση, αλλά τα πεδία μπορούν επίσης να συμπληρωθούν χειροκίνητα.

Περιεχόμενο QR code αισθητήρα
- Το QR code που τοποθετείται πάνω στον αισθητήρα περιέχει τα εξής πεδία στο payload: **Sensor UUID**, **Device UUID**  και **Type**. Αυτό επιτρέπει την αυτόματη συμπλήρωση της φόρμας κατά τη σάρωση. 

Σημειωση: Το QR code των αισθητηρων ειναι διαφορετικο απο το QR code που αναφεραμε προηγουμενος για τη συσκευη.

Συμπεριφορά των φορμών εγγραφής

- Όταν ο χρήστης ανοιγει την γενική φόρμα `sensors/create`, η φόρμα εμφανίζει πεδίο επιλογής **Farm** και ο χρήστης επιλέγει σε ποιον αγρό θα συσχετίσει τον αισθητήρα.
- Αν η φόρμα ανοίγει από το context ενός συγκεκριμένου αγρού (`farms/{id}/sensors/create`), τότε το **Farm** συσχετίζεται αυτόματα με το συγκεκριμένο αγρό και το αντίστοιχο πεδίο δεν είναι πλέον ορατό/επεξεργάσιμο από τον χρήστη (hidden) για να αποφεύγονται λάθη συσχέτισης.
- Σε όλες τις φόρμες υπάρχει η επιλογή "Scan Sensor QR" που συμπληρώνει `sensor_uuid`, `device_uuid` (αν υπάρχει) και `type` από το QR, καθώς και η πρόταση για αυτόματη συμπλήρωση `latitude`/`longitude` μέσω GPS.
- Ο χρήστης μπορεί πάντα να επιλέξει manual entry και να συμπληρώσει όλα τα πεδία χειροκίνητα χωρίς σάρωση.

Σάρωση υπάρχοντος αισθητήρα (update flow)
- Αν ο χρήστης σκανάρει το QR ενός αισθητήρα που έχει ήδη καταχωρηθεί στο σύστημα, η εφαρμογή αναγνωρίζει το υπάρχον `sensor_uuid` και μεταβαίνει σε λειτουργία επεξεργασίας (edit). Σε αυτή την περίπτωση, η εφαρμογή εκτελει την  ενημέρωση πεδίων όπως `lat`/`lon`, `device_id` ή `name` (αν υπάρχουν αλλαγές) αντί να δημιουργήσει νέο εγγραφόμενο αισθητήρα.


Backend συμπεριφορά (τεχνικά σημεία):
- Το controller/endpoint που χειρίζεται τη σάρωση και τη δημιουργία/ενημέρωση (`SensorController@scan` ή `sensors.scan`) πραγματοποιεί τα εξής βήματα:
  1. Εξαγωγή payload από το QR (sensor_uuid, device_uuid?, type?).
  2. Αν δίνεται `device_uuid`, επαλήθευση ότι η συσκευή ανήκει στον ίδιο tenant και authorization μέσω `DevicePolicy`.
  3. Αναζήτηση υπάρχοντος Sensor με `sensor_uuid` εντός του tenant scope.
  4. Αν υπάρχει: ενημέρωση επιτρεπόμενων πεδίων (π.χ. `lat`, `lon`, `device_id`, `type`), καταγραφή audit/changes αν απαιτείται.
  5. Αν δεν υπάρχει: δημιουργία νέου Sensor με τα παρεχόμενα πεδία και σύνδεση με τον επιλεγμένο/προβλεπόμενο Farm.
  6. Επιστροφή κατάλληλου response για το UI (created/updated και τα νέα δεδομένα του Sensor).

Μετά την εγγραφή ή ενημέρωση, ο αισθητήρας είναι διαθέσιμος για εισροή μετρήσεων — όταν η συσκευή στέλνει δεδομένα, το `SensorDataService` θα εντοπίσει τον αισθητήρα με βάση το `sensor_uuid` και θα ενημερώσει `last_reading` / `last_reading_at`.



![Λίστα Αισθητήρων](images/Screenshot%202025-08-21%20at%2023-26-08%20Sensors%20-%20Laravel.png)
*Εικόνα: Λίστα συσκευών του χρήστη με βασικές πληροφορίες, κατάσταση και επιλογες διαχείρισης.*

![Λεπτομέρειες Αισθητήρα](images/Screenshot%202025-08-21%20at%2023-26-19%20Θερμοκρασια%20Sensors%20Show%20-%20Laravel.png)
*Εικόνα: Σελίδα λεπτομερειών αισθητήρα με ιστορικό τελευταίων μετρήσεων και μεταδεδομένα.*

![Προσθήκη Αισθητήρα](images/Screenshot%202025-08-21%20at%2023-28-36%20Add%20Sensor%20-%20Laravel.png)
*Εικόνα: Φόρμα προσθήκης αισθητήρα. Πατήστε "Scan Sensor QR" για αυτόματη συμπλήρωση ή προχωρήστε σε manual entry.*

![Επεξεργασία Αισθητήρα](images/Screenshot%202025-08-21%20at%2023-28-11%20Edit%20Sensor%20-%20Laravel.png)
*Εικόνα: Φόρμα επεξεργασίας αισθητήρα όπου ο χρήστης μπορεί να αλλάξει το όνομα, το farm, τις συντεταγμένες και τον τύπο.*

**Σχετικά αρχεία υλοποίησης (GitHub):**
- [Sensor model](https://github.com/gkalogeitonas/agronos/blob/main/app/Models/Sensor.php): Ο Eloquent model που ορίζει τα πεδία (`uuid`, `name`, `type`, `lat`, `lon`, `farm_id`, `device_id`) και χρησιμοποιεί το `BelongsToTenant` trait για tenant isolation.
- [Sensor controller](https://github.com/gkalogeitonas/agronos/blob/main/app/Http/Controllers/SensorController.php): Χειρίζεται τις CRUD ενέργειες, τις φόρμες `create`/`edit`, το endpoint `scan` για QR-based create/update και την εξαγωγή δεδομένων time-series για προβολή.
- [Sensor policy](https://github.com/gkalogeitonas/agronos/blob/main/app/Policies/SensorPolicy.php): Ορίζει τους κανόνες πρόσβασης (view, create, update, delete) ώστε κάθε χρήστης να διαχειρίζεται μόνο τους δικούς του αισθητήρες.
- [Sensor resource](https://github.com/gkalogeitonas/agronos/blob/main/app/Http/Resources/SensorResource.php): Το API resource που μορφοποιεί τα δεδομένα αισθητήρα προς το frontend (περιλαμβάνει τα πεδία `uuid`, `type`, `lat`/`lon`, `last_reading`).
- [SensorTimeSeriesService](https://github.com/gkalogeitonas/agronos/blob/main/app/Services/TimeSeries/SensorTimeSeriesService.php): Υπηρεσία για ερωτήματα προς την InfluxDB (recentReadings, stats) και για την παρουσίαση ιστορικών/στατιστικών μετρήσεων.

### Websocket / Real-time ενημερώσεις στη σελίδα λεπτομερειών αισθητήρα  (report 2)

Η εφαρμογή υποστηρίζει real-time ενημερώσεις για μετρήσεις αισθητήρων ώστε η σελίδα `Sensors/Show` να εμφανίζει αμέσως νέα δεδομένα χωρίς refresh.

- Πλαίσιο (server): Η εφαρμογή χρησιμοποιεί το πακέτο **Reverb** ως broadcast driver (βλ. `config/reverb.php` και `config/broadcasting.php`) και ο server του Reverb τρέχει ξεχωριστά (π.χ. `vendor/bin/reverb serve --host=0.0.0.0 --port=8080`).
- Κανάλι: κάθε αισθητήρας έχει ιδιωτικό κανάλι `sensor.{sensorId}`. Η πολιτική auth για το κανάλι βρίσκεται σε `routes/channels.php` και επιτρέπει μόνο εξουσιοδοτημένους χρήστες (π.χ. ιδιοκτήτη/μέλος tenant) να εγγραφούν.
- Event: το backend εκπέμπει `App\Events\SensorReadingEvent` (υλοποιεί `ShouldBroadcast`). Το event broadcast κάνει `broadcastOn()` σε `new PrivateChannel("sensor.{$this->sensorId}")` και το `broadcastWith()` επιστρέφει ένα payload σχηματικά όπως:

```
{
  "value": 23.4,
  "time": "2025-09-09 12:00:00",
  "message": "optional"
}
```

- Πότε εκπέμπεται: το event αποστέλλεται όταν ο server επεξεργάζεται εισερχόμενα δεδομένα αισθητήρα — συγκεκριμένα μετά την ενημέρωση των πεδίων `last_reading` / `last_reading_at` (βλ. `app/Services/SensorDataService.php`). 

- Frontend: η σελίδα `resources/js/pages/Sensors/Show.vue` εγγράφεται στο ιδιωτικό κανάλι με την helper `useEcho` (παραδείγματος χάριν `useEcho(`sensor.${sensor.value.id}`, 'SensorReadingEvent', handler)`), και ο handler ενημερώνει τοπικά:
  - τον πίνακα `recentReadings` (demo ιστορικό)
  - και το `page.props.sensor.last_reading` / `last_reading_at` ώστε το card "Latest Reading" να ανανεώνεται άμεσα.


Αυτή η μικρή ροή δίνει μια γρήγορη, χρηστική εμπειρία χρήστη: όταν η συσκευή αποστέλλει νέες μετρήσεις, το backend τις καταγράφει, ενημερώνει το μοντέλο και δεσμεύει ένα broadcast — ο browser του ιδιοκτήτη βλέπει αμέσως τα νέα δεδομένα χωρίς reload.




## Ασφάλεια και Multi-tenancy

### Μηχανισμοί Ασφαλείας
- **Data Isolation**: `BelongsToTenant` trait + `TenantScope` για πλήρη απομόνωση δεδομένων
- **Device Authentication**: Hashed secrets + Laravel Sanctum personal access tokens
- **Authorization**: Comprehensive Policies για Farm, Device, Sensor entities
- **Input Validation**: Form Requests και API Resources για consistent validation

Το σύστημα διαθέτει policies για όλες τις κύριες οντότητες (Farm, Device, Sensor) που διασφαλίζουν ότι ένας χρήστης μπορεί να βλέπει/διαχειρίζεται μόνο τα δικά του αντικείμενα. Αυτές οι policies (π.χ. `app/Policies/SensorPolicy.php`) εφαρμόζονται στους αντιστοιχουν controllers.

### Multi-tenant Architecture
Κάθε χρήστης βλέπει και διαχειρίζεται μόνο τα δικά του δεδομένα:
- Αυτόματη εφαρμογή tenant scope σε όλα τα database queries
- Ασφαλής διαχείριση συσκευών και αισθητήρων per user
- Απομόνωση δεδομένων InfluxDB μέσω user_id tag

Επεξήγηση λειτουργίας του `BelongsToTenant` trait:
- Τα μοντέλα `Farm`, `Device` και `Sensor` χρησιμοποιούν το trait `BelongsToTenant` (και το συνοδευτικό global scope `TenantScope`). Το trait προσθέτει έναν global query scope που αυτόματα προσθέτει στα queries ένα `where('user_id', $currentUser->id)`.
- Το αποτέλεσμα είναι ότι κάθε Eloquent query μέσω αυτών των μοντέλων επιστρέφει μόνο εγγραφές που ανήκουν στον τρέχοντα χρήστη, αποκλείοντας σενάρια όπου ένας χρήστης θα μπορούσε να δει ή να τροποποιήσει δεδομένα άλλου χρήστη.
- Αυτή η προσέγγιση λειτουργεί σε συνδυασμό με τις policies ως "defense-in-depth": οι policies χειρίζονται authorization για actions, ενώ το trait περιορίζει τα δεδομένα που είναι διαθέσιμα στο επίπεδο του query.

Δεδομένα (user_id) σε πίνακες:
- Για να λειτουργήσει αυτή η απομόνωση, όλα τα σχετιζόμενα relational tables (π.χ. `farms`, `devices`, `sensors`) περιλαμβάνουν το πεδίο `user_id` ως foreign key. Οι migration αρχεία ορίζουν το πεδίο `user_id` και τους αντίστοιχους περιορισμούς (FK) ώστε να διασφαλίζεται η συσχέτιση εγγραφών με τον ιδιοκτήτη χρήστη.




## Sensor Measurements  — Τεχνικές Λεπτομέρειες

 Όλα τα raw time-series δεδομένα των μετρήσεων  δεν αποθηκεύονται στην relational database της εφαρμογής (MySQL). Τα δεδομένα των αισθητηρων  γράφονται  στην εξωτερική, hosted υπηρεσία InfluxDB (έχουμε δημιουργήσει λογαριασμό και bucket στη hosted InfluxDB υπηρεσία). Η εφαρμογή χρησιμοποιεί το `InfluxDBService` για να γράψει τα σημεία και να εκτελεί ερωτήματα ιστορικών/στατιστικών όταν απαιτείται.

- Για λόγους απόδοσης και απλού rendering των λιστών αισθητήρων, στη relational DB διατηρούμε μόνο τη τελευταία μετρηθείσα τιμή για κάθε `Sensor` (πεδία `last_reading` και `last_reading_at`). Αυτή η τελευταία τιμή ενημερώνεται μετά την επιτυχή εγγραφή του σημείου στην InfluxDB, ώστε η εμφάνιση των SensorCard στη λίστα να διαβάζει τοπικά πεδία και να μην απαιτείται κάθε φορά εξωτερικό query προς την InfluxDB.

Κύριο endpoint
- POST /api/v1/device/data — αυτό είναι το HTTP endpoint που χρησιμοποιούν οι συσκευές για να στέλνουν δέσμες μετρήσεων (DeviceDataController).

Validation
- Το αίτημα επικυρώνεται από το `App\Http\Requests\Api\V1\DeviceDataRequest` που απαιτεί το πεδίο `sensors` ως πίνακα τουλάχιστον ενός αντικειμένου με `uuid` (string) και `value` (numeric). (see `app/Http/Requests/Api/V1/DeviceDataRequest.php`).

DeviceDataController
- Ο `App\Http\Controllers\Api\V1\DeviceDataController` ανακτά τη συσκευή (το authenticated user για το token), ενημερώνει το `status` σε ONLINE και το `last_seen_at`, στη συνέχεια περνάει τα validated sensor payloads στο **`SensorDataService::processSensorData`** μαζί με την υπηρεσία InfluxDB (`InfluxDBService`). (see `app/Http/Controllers/Api/V1/DeviceDataController.php`).

SensorDataService — επεξεργασία των μετρήσεων
```php
<?php

namespace App\Services;

use App\Models\Sensor;
use App\Services\InfluxDBService;
use App\Services\SensorMeasurementPayloadFactory;
use Illuminate\Support\Collection;

class SensorDataService
{
    public function processSensorData($device, array $sensorPayloads, InfluxDBService $influx): array
    {
        $uuids = collect($sensorPayloads)->pluck('uuid')->all();
        $sensors = Sensor::allTenants()
            ->where('device_id', $device->id)
            ->whereIn('uuid', $uuids)
            ->get()
            ->keyBy('uuid');
        $missingUuids = [];
        $writtenCount = 0;

        foreach ($sensorPayloads as $sensor) {
            $sensorModel = $sensors->get($sensor['uuid']);
            if (!$sensorModel) {
                $missingUuids[] = $sensor['uuid'];
                continue;
            }
            $payload = SensorMeasurementPayloadFactory::make($sensorModel, $sensor['value']);
            $influx->writeArray($payload);
            $sensorModel->last_reading = $sensor['value'];
            $sensorModel->last_reading_at = now();
            $sensorModel->save();
            $writtenCount++;
        }

        $response = ['message' => 'Data received.'];
        if (count($missingUuids) > 0) {
            $response['missing_uuids'] = $missingUuids;
        }
        return $response;
    }
}

```
- Το `App\Services\SensorDataService` πραγματοποιεί τα εξής βήματα (βλέπε `app/Services/SensorDataService.php`):

  1. Συλλέγει όλα τα `uuid` από το payload.

  2. Φορτώνει όλους τους αισθητήρες του tenant που ανήκουν στη συγκεκριμένη συσκευή μέσω:
    ```php
     Sensor::allTenants()->where('device_id', $device->id)->whereIn('uuid', $uuids)->get()->keyBy('uuid');
     ```
     Σημείωση: Σε αυτή τη φάση εισροής, η αυθεντικοποίηση γίνεται από τη συσκευή μέσω του token της (όχι από τον interactive χρήστη). Επομένως το κανονικό global tenant scope δεν εφαρμόζεται εδώ — χρησιμοποιούμε explicit helper/μέθοδο όπως `Sensor::allTenants()` για να παρακάμψουμε προσωρινά το scope και να επιτρέψουμε στο token της συσκευής να εντοπίσει αισθητήρες. Η ασφάλεια ωστόσο διασφαλίζεται επειδή το query περιορίζεται ρητά με `where('device_id', $device->id)` και το token/Device έχει ήδη επικυρωθεί ότι ανήκει στον σωστό χρήστη/tenant κατά το login της συσκευής. Αυτό αποτρέπει cross-tenant ή cross-device data injection ενώ επιτρέπει στη συσκευή να στέλνει μετρήσεις χωρίς ενεργή συνεδρία χρήστη.

  3. Για κάθε measurement:
     - Αν υπάρχει αντιστοίχιση (`Sensor` model), δημιουργεί ένα InfluxDB payload με τη βοήθεια του `SensorMeasurementPayloadFactory::make($sensorModel, $sensor['value'])` και το γράφει στην InfluxDB με `InfluxDBService::writeArray($payload)`.
     - Ενημερώνει το `last_reading` και `last_reading_at` πεδία του `Sensor` μοντέλου και αποθηκεύει την εγγραφή.
     - Αν δεν υπάρχει αντιστοίχιση, προσθέτει το uuid στη λίστα `missing_uuids`.
  4. Στο τέλος επιστρέφει ένα συνοπτικό response με μήνυμα επιτυχίας και — αν υπάρχουν — το `missing_uuids` array.

InfluxDB interaction
- Η `App\Services\InfluxDBService` εκθέτει helpers για εγγραφή (`writeArray`, `writePoint`, `writeLineProtocol`) και για query (`query`, `queryPipeline`). Το SensorMeasurementPayloadFactory παράγει τη σωστή δομή (measurement name, tags: user_id,farm_id,sensor_id,sensor_type, fields: value, timestamp) που απαιτεί η InfluxDB client βιβλιοθήκη. (see `app/Services/InfluxDBService.php` and `app/Services/SensorMeasurementPayloadFactory.php`).

- Παράδειγμα payload που δημιουργεί το `SensorMeasurementPayloadFactory::make($sensorModel, $value)` πριν αποσταλεί στην InfluxDB (PHP array):

```php
return [
    'name' => 'sensor_measurement',
    'tags' => [
        'user_id'    => $sensorModel->user_id,
        'farm_id'    => $sensorModel->farm_id,
        'sensor_id'  => $sensorModel->id,
        'sensor_type'=> $sensorModel->type,
    ],
    'fields' => [
        'value' => (float) $value,
    ],
    'time' => time(), // use server time
];
```

- Τι αποθηκεύεται στην InfluxDB με βάση το παραπάνω payload:
  - measurement: `sensor_measurement` — το logical όνομα της χρονικής σειράς.
  - tags: `user_id`, `farm_id`, `sensor_id`, `sensor_type` — αποθηκεύονται ως tags (indexed) για γρήγορο φιλτράρισμα και ομαδοποίηση κατά χρήστη/αγρό/αισθητήρα/τύπου. Τα tags είναι strings στην InfluxDB και είναι σχεδιασμένα για query-selectivity.
  - fields: `value` — αποθηκεύεται ως numeric field (float). Τα fields δεν είναι indexed, χρησιμοποιούνται για aggregation/περαιτέρω ανάλυση (avg, min, max, etc.).
  - time: Εγγραφή χρονικής σφραγίδας με ακρίβεια δευτερολέπτου (precision = s). Το πεδίο `time` χρησιμοποιείται από την InfluxDB ως το timestamp του σημείου.


Παράδειγμα πλήρους ροής (payload -> response)

Example payload (αποστολή από συσκευή):
```json
{
  "sensors": [
    { "uuid": "sensor-temp-001", "value": 22.6 },
    { "uuid": "sensor-humidity-001", "value": 67.8 },
    { "uuid": "sensor-soil-001", "value": 45.2 }
  ]
}
```

Example success response (όταν όλα τα sensors βρίσκονται και αποθηκεύονται):
```json
{
  "message": "Data received."
}
```

Example response με `missing_uuids` (όταν κάποια UUIDs δεν αντιστοιχίζονται σε καταχωρημένους αισθητήρες για τον tenant/device):
```json
{
  "message": "Data received.",
  "missing_uuids": ["sensor-unknown-123", "sensor-old-456"]
}
```


Σχετικά αρχεία (GitHub links):
- [app/Http/Requests/Api/V1/DeviceDataRequest.php](https://github.com/gkalogeitonas/agronos/blob/main/app/Http/Requests/Api/V1/DeviceDataRequest.php) — validation rules για το payload
- [app/Http/Controllers/Api/V1/DeviceDataController.php](https://github.com/gkalogeitonas/agronos/blob/main/app/Http/Controllers/Api/V1/DeviceDataController.php) — controller που δέχεται τα δεδομένα και αντιστοιχεί τη συσκευή
- [app/Services/SensorDataService.php](https://github.com/gkalogeitonas/agronos/blob/main/app/Services/SensorDataService.php) — επεξεργασία payloads, αναζήτηση sensors, εγγραφή σε InfluxDB, ενημέρωση μοντέλων
- [app/Services/InfluxDBService.php](https://github.com/gkalogeitonas/agronos/blob/main/app/Services/InfluxDBService.php) — wrapper του InfluxDB client
- [app/Services/SensorMeasurementPayloadFactory.php](https://github.com/gkalogeitonas/agronos/blob/main/app/Services/SensorMeasurementPayloadFactory.php) — factory για το InfluxDB payload (measurement, tags, fields)



## Παρουσίαση Μετρήσεων 

Η πλατφόρμα Agronos παρέχει  σύστημα παρουσίασης και ανάλυσης των δεδομένων αισθητήρων που συλλέγονται από τις IoT συσκευές. Η αρχιτεκτονική του frontend επιτρέπει την  παρακολούθηση και την ιστορική ανάλυση των μετρήσεων με σύγχρονη και φιλική προς το χρήστη διεπαφή.

### Αρχιτεκτονική Backend για Time-Series Queries

Για την ανάκτηση και επεξεργασία των χρονοσειρών δεδομένων, η πλατφόρμα χρησιμοποιεί εξειδικευμένη υπηρεσία:

#### SensorTimeSeriesService

Η κλάση `App\Services\TimeSeries\SensorTimeSeriesService` αποτελεί το κεντρικό σημείο αλληλεπίδρασης με την InfluxDB για την ανάκτηση δεδομένων αισθητήρων. Παρέχει δύο κύριες μεθόδους:

```php
// Ανάκτηση πρόσφατων μετρήσεων
public function recentReadings(int $sensorId, string $range = '-7d', int $limit = 10): array

// Υπολογισμός στατιστικών (min, max, avg, count)
public function stats(int $sensorId, string $range = '-24h'): array
```

**Βασικά χαρακτηριστικά της υπηρεσίας:**

- **Flux Query Construction**: Χρησιμοποιεί τη γλώσσα Flux της InfluxDB για την κατασκευή πολύπλοκων χρονοσειρών queries.
- **Data Normalization**: Εφαρμόζει συνεπή στρογγυλοποίηση αριθμητικών τιμών και κανονικοποίηση χρονικών σφραγίδων.
- **Progressive Range Widening**: Όταν δεν βρίσκονται δεδομένα στο αρχικό χρονικό διάστημα (π.χ. -24h), αυτόματα επεκτείνει την αναζήτηση σε μεγαλύτερα διαστήματα (-7d, -30d).
- **Error Handling**: Παρέχει graceful fallback σε περίπτωση αποτυχίας σύνδεσης με την InfluxDB.

### Υλοποίηση στο SensorController

Ο `SensorController` ενσωματώνει την `SensorTimeSeriesService` στη μέθοδο `show()` για να παρουσιάσει ολοκληρωμένη εικόνα του αισθητήρα:

```php
public function show(Sensor $sensor)
{
    $this->authorize('view', $sensor);
    $sensor->load(['farm', 'device']);

    // Time-series queries via dedicated service
    $ts = app(SensorTimeSeriesService::class);
    $recent = $ts->recentReadings($sensor->id, '-7d', 20);
    $statsArr = $ts->stats($sensor->id, '-24h');
    
    return Inertia::render('Sensors/Show', [
        'sensor' => (new SensorResource($sensor))->flat(request()),
        'recentReadings' => $recent,
        'stats' => $statsArr,
    ]);
}
```

### Frontend Implementation (Vue.js)

Η σελίδα λεπτομερειών αισθητήρα (`resources/js/pages/Sensors/Show.vue`) παρουσιάζει τα δεδομένα σε οργανωμένη και διαδραστική μορφή:

#### Στοιχεία της Διεπαφής

1. **Header Section**
   - Όνομα αισθητήρα και τύπος σε badge format
   - Κουμπιά για επεξεργασία και διαγραφή με εικονίδια Lucide

2. **Interactive Map (όταν υπάρχουν συντεταγμένες)**
   - Χρήση του component `FarmMapbox` για γεωγραφική απεικόνιση
   - Προβολή θέσης αισθητήρα και ορίων αγροτεμαχίου
   - Zoom level 15 για λεπτομερή εμφάνιση

3. **Sensor Details Card**
   - Responsive grid layout (1 στήλη σε mobile, 2 στήλες σε desktop)
   - Πληροφορίες: UUID αισθητήρα, UUID συσκευής, τύπος, συνδεδεμένο αγροτεμάχιο, συντεταγμένες
   - Monospace font για UUIDs για καλύτερη αναγνωσιμότητα

4. **Real-time Status Cards**
   - **Latest Reading Card**: Εμφανίζει την τελευταία μέτρηση με μεγάλη γραμματοσειρά και μονάδα μέτρησης
   - **24h Statistics Card**: Grid με min/max/average τιμές σε 3 στήλες

5. **Historical Data Table**
   - Scrollable πίνακας με πρόσφατες μετρήσεις (τελευταίες 20)
   - Formatted timestamps χρησιμοποιώντας το composable `useTimestamp`
   - Conditional rendering - εμφανίζεται μόνο όταν υπάρχουν δεδομένα



![Λεπτομέρειες Αισθητήρα](images/Screenshot%202025-08-21%20at%2023-26-19%20Θερμοκρασια%20Sensors%20Show%20-%20Laravel.png)
*Εικόνα: Σελίδα λεπτομερειών αισθητήρα με ιστορικό τελευταίων μετρήσεων και μεταδεδομένα. Η διεπαφή παρουσιάζει real-time στοιχεία, 24ωρα στατιστικά και πίνακα με πρόσφατες μετρήσεις σε responsive layout.*

### Άμεσα επόμενα βήματα 

- Παρουσίαση στατιστικών σε επίπεδο αγρού: Υλοποίηση aggregation layer που θα συγκεντρώνει και θα παρουσιάζει στατιστικά (min/max/avg/count) από όλες τις μετρήσεις των αισθητήρων ενός `Farm` μέσα σε επιλεγμένα χρονικά διαστήματα. 

- Δημιουργία Dashboard για την αρχική σελίδα: Σχεδιασμός και υλοποίηση ενός κεντρικού dashboard που θα παρέχει snapshot της κατάστασης της πλατφόρμας και γρήγορη πρόσβαση σε κρίσιμα metrics. 







## Το κομμάτι των συσκευών — Firmware και λειτουργία

Το έργο περιλαμβάνει και το υποσύστημα των φυσικών συσκευών (Agronos WiFi Sensor). Το firmware είναι ένα ελαφρύ πρόγραμμα για ESP32 που υλοποιείται με PlatformIO και έχει ως στόχο την ανάγνωση αισθητήρων και την αποστολή των μετρήσεων στο backend της πλατφόρμας.

### Αποθετήριο συσκευής / firmware
- Το firmware και οι οδηγίες βρίσκονται στο αποθετήριο: https://github.com/gkalogeitonas/Agronos-iot-device

### Κύρια χαρακτηριστικά
- Πλατφόρμα: ESP32 (PlatformIO).
- Προβολή captive Wi‑Fi portal για provisioning όταν λείπουν διαπιστευτήρια Wi‑Fi.
- Επικράτηση persistent storage για Wi‑Fi credentials και το token αυθεντικοποίησης.
- Αυτόματη προσπάθεια αυθεντικοποίησης προς το backend για απόκτηση token (αποθηκεύεται και επαναχρησιμοποιείται).
- Αποστολή JSON payloads στο endpoint: `BASE_URL/api/v1/device/data`.

### Αρχιτεκτονική firmware
- Κεντρικό αρχείο: `src/main.cpp` — αρχή προγράμματος, διαχείριση Wi‑Fi, portal, auth, περιοδικές μετρήσεις και αποστολή.
- Διαχωρισμός αισθητήρων :
  - Το κύριο design pattern για τους αισθητήρες είναι το Strategy: κάθε αισθητήρας υλοποιεί τη διεπαφή `SensorBase` (παρέχοντας τη μέθοδο `read`) ώστε ο υπόλοιπος κώδικας να αλληλεπιδρά ομοιόμορφα με οποιονδήποτε τύπο αισθητήρα.
  - Η δημιουργία των αντικειμένων των αισθητήρων γίνεται με το Factory pattern, βάσει της διαμόρφωσης `SENSOR_CONFIGS` στο `include/config.h`. Ένας registry/creator (`registerSensorFactory`, `createSensorByType`, `createSensors`) επιτρέπει την εγγραφή νέων τύπων αισθητήρων χωρίς τροποποίηση του factory.
  - Σχετικά αρχεία: `include/sensor.h`, `include/sensor_creator.h`, `src/sensor_factory.cpp`, `src/dht11_temp.cpp`, `src/dht11_hum.cpp`, `src/simulated.cpp`.
- Μεταφορά δεδομένων: `include/data_sender.h` και `src/data_sender.cpp` — κατασκευή JSON και HTTP POST με Authorization Bearer token.
- Provisioning / Storage / Auth: `wifi_portal.*`, `storage.*`, `auth.*` — βοηθητικές βιβλιοθήκες για προσωρινή δικτύωση, αποθήκευση και διαχείριση token.

### Διαμόρφωση αισθητήρων
- Όλη η διαμόρφωση γίνεται στο `include/config.h` μέσω του πίνακα `SENSOR_CONFIGS`.
- Παράδειγμα:

```c++
constexpr SensorConfig SENSOR_CONFIGS[] = {
    { "DHT11TemperatureReader", 21, "Device-1-Temp" },
    { "DHT11HumidityReader",    21, "Device-1-Hum" }
};
```
- Το `type` αντιστοιχεί στο όνομα της κλάσης που έχει καταχωρηθεί στο factory, `pin` είναι το GPIO (ή -1), και `uuid` είναι το sensor UUID που θα χρησιμοποιηθεί για τον εντοπισμό του στο backend.

### Captive Portal (provisioning)
- Όταν η συσκευή δεν έχει αποθηκευμένα Wi‑Fi credentials, ανοίγει captive portal (Access Point — SSID ορισμένο από `AP_SSID`). Ο χρήστης συνδέεται στο AP με κινητό ή φορητό υπολογιστή και ανοίγει τον browser  στη σελίδα του portal (`http://192.168.4.1`) όπου εμφανίζεται μια απλή φόρμα εισαγωγής SSID και password. Με την αποθήκευση των στοιχείων, τα credentials γράφονται σε persistent storage και η συσκευή προσπαθεί να συνδεθεί στο επιλεγμένο Wi‑Fi δίκτυο. Αν η σύνδεση επιτευχθεί, το portal σταματά και η συσκευή επιχειρεί αμέσως αυθεντικοποίηση προς το backend για να αποκτήσει και να αποθηκεύσει το Sanctum token (βλέπε `src/wifi_portal.cpp`, `auth.*`, `storage.*`).

### Προεγκατάσταση / Αυθεντικοποίηση
- Σενάριο: Οι συσκευές προ‑προγραμματίζονται με UUID / secret ή αυτά εκχωρούνται και τυπώνονται σε ετικέτες / QR codes.
- Αν η συσκευή δεν έχει token, μετά τη σύνδεση σε Wi‑Fi επιχειρεί POST στο `POST /api/v1/device/login` με `uuid` και `secret` για να αποκτήσει Sanctum token.
- Το token αποθηκεύεται και χρησιμοποιείται ως Bearer στα επόμενα αιτήματα για αποστολή μετρήσεων.

### Ροή δεδομένων
- Τα πακέτα μετρήσεων είναι JSON με πίνακα `sensors`, π.χ.:

```json
{
  "sensors": [
    { "uuid": "sensor-uuid-1", "value": 22.6 },
    { "uuid": "sensor-uuid-2", "value": 55.1 }
  ]
}
```

- Ο `DataSender` χειρίζεται την κατασκευή του payload και την αποστολή. 

### Build & flash
- Εργαλεία: PlatformIO (συστήνεται χρήση VS Code + PlatformIO extension).
- Εντολές:
  - `pio run` — build
  - `pio run -t upload` — flash στη συσκευή
- Το `platformio.ini` περιέχει τις ρυθμίσεις πλατφόρμας και board.


### Σημαντικά αρχεία
- `include/config.h` — https://github.com/gkalogeitonas/Agronos-iot-device/blob/main/include/config.h
- `include/sensor.h` — https://github.com/gkalogeitonas/Agronos-iot-device/blob/main/include/sensor.h
- `include/sensor_creator.h` — https://github.com/gkalogeitonas/Agronos-iot-device/blob/main/include/sensor_creator.h
- `src/sensor_factory.cpp` — https://github.com/gkalogeitonas/Agronos-iot-device/blob/main/src/sensor_factory.cpp
- `src/dht11_temp.cpp` — https://github.com/gkalogeitonas/Agronos-iot-device/blob/main/src/dht11_temp.cpp
- `src/dht11_hum.cpp` — https://github.com/gkalogeitonas/Agronos-iot-device/blob/main/src/dht11_hum.cpp
- `src/simulated.cpp` — https://github.com/gkalogeitonas/Agronos-iot-device/blob/main/src/simulated.cpp
- `src/data_sender.cpp` — https://github.com/gkalogeitonas/Agronos-iot-device/blob/main/src/data_sender.cpp
- `src/wifi_portal.cpp` — https://github.com/gkalogeitonas/Agronos-iot-device/blob/main/src/wifi_portal.cpp
- `platformio.ini` — https://github.com/gkalogeitonas/Agronos-iot-device/blob/main/platformio.ini
- `README.md` (device repo) — https://github.com/gkalogeitonas/Agronos-iot-device/blob/main/README.md

### Άμεσα επόμενα βήματα
- Reset button: Προγραμματισμός και υλοποίηση φυσικού κουμπιού επαναφοράς στη συσκευή που, όταν πατηθεί (π.χ. long-press), θα καθαρίζει τα αποθηκευμένα Wi‑Fi credentials και το auth token από το persistent storage, επιστρέφοντας τη συσκευή σε κατάσταση provisioning (άνοιγμα captive portal). Το firmware πρέπει να προσθέσει: χειριστή GPIO για το κουμπί, ασφαλή διαγραφή μέσω της `storage` API και κατάλληλη επανεκκίνηση ή επαναφορά κατάστασης.

- Πρόσθεση αισθητήρα υγρασίας εδάφους: Υλοποίηση νέας κλάσης `SoilMoistureSensor` που θα κληρονομεί `SensorBase` και θα διαβάζει τιμή από αναλογικό pin (ή ψηφιακό αν χρησιμοποιείται ψηφιακό module). Βήματα:


Αυτές οι προσθήκες βελτιώνουν το UX πεδίου (εύκολο reprovisioning) και επεκτείνουν τη λειτουργικότητα (υγρασία εδάφους) για πιο πλήρη παρακολούθηση αγρού.

