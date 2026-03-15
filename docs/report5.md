# Αναφορά 5

**Repository:** https://github.com/gkalogeitonas/Agronos-iot-device

**Φοιτητής:** Καλογείτονας Γεωργιος

**Επιβλέπων Καθηγητής:** Νικόλαος Σκλάβος

**Ημερομηνία:** Μάρτιος 2026

---

## Υποστήριξη LoRa Συσκευών — Zero-Trust Αποκρυπτογράφηση & Dedicated Webhook


---

### Επισκόπηση Αρχιτεκτονικής

Η ροή δεδομένων είναι η εξής:

1. Ο LoRa κόμβος κρυπτογραφεί τις μετρήσεις και τις δημοσιεύει μέσω LoRa radio.
2. Το LoRa Gateway λαμβάνει το πακέτο και το προωθεί ως MQTT μήνυμα στο topic `lora/{gateway_uuid}/data`, συμπεριλαμβάνοντας metadata (RSSI, SNR) και το κρυπτογραφημένο payload.
3. Ο EMQX Rule Engine διαβιβάζει αυτόματα το μήνυμα ως HTTP POST στο `/api/v1/lora/webhook`.
4. Το Laravel backend αποκρυπτογραφεί, επικυρώνει και επεξεργάζεται τα δεδομένα μέσω της υπάρχουσας `SensorDataService`.

Κύριο αρχιτεκτονικό χαρακτηριστικό είναι η **μηδενική εμπιστοσύνη** (zero-trust): ακόμη και αν ένα gateway παραβιαστεί, τα δεδομένα παραμένουν αδιάβαστα χωρίς το AES κλειδί που είναι αποκλειστικά αποθηκευμένο στον κόμβο και στη βάση δεδομένων του backend.

---

### Επέκταση Σχήματος Βάσης Δεδομένων

Ο πίνακας `devices` επεκτάθηκε με δύο νέα πεδία για την υποστήριξη της κρυπτογραφικής κατάστασης κάθε LoRa κόμβου:

```php
// database/migrations/2026_03_08_105849_add_lora_columns_to_devices_table.php
$table->unsignedBigInteger('lora_frame_counter')->default(0)->after('signal_strength');
$table->string('lora_aes_key', 32)->nullable()->after('lora_frame_counter');
```

- **`lora_frame_counter`**: Αποθηκεύει τον τελευταίο έγκυρο frame counter που δέχτηκε το σύστημα. Χρησιμοποιείται για προστασία από replay attacks.
- **`lora_aes_key`**: Το 128-bit AES κλειδί του κόμβου (32-χαρακτηρο hex string). Ορίζεται χειροκίνητα κατά την εγγραφή της συσκευής και αποκρύπτεται από τα API responses (`$hidden`).

---

### Υπηρεσία Κρυπτογραφίας: `LoRaCryptoService`

Δημιουργήθηκε η κλάση `app/Services/LoRaCryptoService.php` που ενθυλακώνει όλες τις κρυπτογραφικές λειτουργίες. Αποτελείται από τρεις βασικές μεθόδους:

#### 1. Επικύρωση Frame Counter (Anti-Replay)

```php
public function validateFrameCounter(Device $device, int $incomingFcnt): void
```

Ελέγχει ότι ο εισερχόμενος counter είναι αυστηρά μεγαλύτερος από τον αποθηκευμένο (αποτρέπει replay attacks) και ότι η διαφορά δεν υπερβαίνει το `MAX_FCNT_GAP = 10.000` (αποτρέπει κακόβουλα άλματα counter). Σε επιτυχή επικύρωση, ενημερώνει αμέσως το `lora_frame_counter` στη βάση.

Δύο custom exceptions χειρίζονται τις αποτυχίες:
- `LoRaReplayException` — εισερχόμενος fcnt ≤ αποθηκευμένος
- `LoRaFrameCounterGapException` — gap υπερβαίνει το MAX_FCNT_GAP

#### 2. Αποκρυπτογράφηση AES-128-CTR

```php
public function decrypt(Device $device, int $fcnt, string $base64Ciphertext): string
```

Χρησιμοποιεί AES-128-CTR με ντετερμινιστικό 16-byte nonce που παράγεται αποκλειστικά από γνωστές τιμές, χωρίς να χρειάζεται μετάδοσή του στο δίκτυο:

```
Nonce = [4 bytes: CRC32(device.uuid) LE] [4 bytes: fcnt LE] [8 bytes: μηδενικά]
```

Αφού αποκωδικοποιηθεί το Base64 ciphertext, εκτελείται `openssl_decrypt` με `OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING`.

#### 3. Αποσειριοποίηση Binary Payload

```php
public function deserialize(string $binary): array // array<string, float>
```

Το binary payload ακολουθεί μια μεταβλητού μήκους δομή: **N × 6 bytes**, όπου κάθε εγγραφή είναι:

| Bytes | Περιγραφή |
|-------|-----------|
| 0–3 | Πρώτοι 4 χαρακτήρες του UUID αισθητήρα (ASCII) |
| 4–5 | Τιμή ως int16 Little-Endian, κλιμακωμένη ×100 |

Η μέθοδος επιστρέφει `array<string, float>` με κλειδί το 4-χαρακτηρο prefix και τιμή διαιρεμένη διά 100. Η δομή αυτή είναι αγνωστική ως προς τον τύπο αισθητήρα — το backend δεν χρειάζεται να γνωρίζει εκ των προτέρων ποιοι αισθητήρες θα στείλουν δεδομένα, αρκεί να υπάρχουν pre-registered στη βάση με το αντίστοιχο UUID.

Η επιλογή να στέλνονται μόνο οι 4 πρώτοι χαρακτήρες και όχι ολόκληρο το UUID έγινε για εξοικονόμηση δεδομένων που αποστέλλονται από το σχετικό άργο LoRa, με σκοπό να ελαχιστοποιηθεί ο χρόνος που κάθε συσκευή δεσμεύει το κανάλι.
---

### Webhook Controller: `LoRaDataController`

Δημιουργήθηκε ο `app/Http/Controllers/Api/V1/LoRaDataController.php` που υλοποιεί την πλήρη αλυσίδα επεξεργασίας:

```
Λήψη → Αποκωδικοποίηση inner JSON → Εύρεση συσκευής → Anti-Replay → Αποκρυπτογράφηση → Αποσειριοποίηση → Ενημέρωση state → Χαρτογράφηση αισθητήρων → SensorDataService
```

Βασικά σημεία:

- **Double-layer validation**: Πρώτα επικυρώνεται το EMQX envelope (μέσω `LoRaWebhookRequest` Form Request), μετά το inner JSON payload με `Validator::make()`.
- **Multi-tenant lookup**: `Device::allTenants()->where('uuid', ...)->where('type', DeviceType::LORA->value)` εξασφαλίζει ότι μόνο LoRa συσκευές ανιχνεύονται.
- **Sensor mapping by UUID prefix**: Αντιστοιχίζει τα deserialized readings στους pre-registered αισθητήρες μέσω `substr($sensor->uuid, 0, 4)`.
- **Χρήση υπάρχουσας υποδομής**: Μετά την αποκρυπτογράφηση, τα δεδομένα τροφοδοτούνται στην `SensorDataService::processSensorData()` — η ίδια υπηρεσία που χρησιμοποιούν οι WiFi συσκευές. Αυτό σημαίνει ότι οι εγγραφές InfluxDB, οι ενημερώσεις μοντέλου αισθητήρα και τα WebSocket broadcasts (μέσω `SensorReadingEvent`) λειτουργούν αυτόματα για LoRa συσκευές χωρίς επιπλέον κώδικα.

---

### Route & EMQX Ενσωμάτωση

Προστέθηκε το endpoint στο `routes/api.php` εντός της ομάδας `v1`:

```php
Route::post('/lora/webhook', [LoRaDataController::class, 'webhook']);
```

Το EMQX ACL (`docker/emqx/acl.conf`) επεκτάθηκε ώστε τα gateways να μπορούν να δημοσιεύουν και να συνδράμουν στα topics τους:

```erlang
{allow, all, publish,   ["lora/${username}/#"]}.
{allow, all, subscribe, ["lora/${username}/#"]}.
```

Η σύνδεση EMQX Rule Engine → HTTP webhook (`POST /api/v1/lora/webhook`) γίνεται χειροκίνητα από το EMQX dashboard με topic filter `lora/+/data`.

---

### Σχετικά Αρχεία

- [database/migrations/2026_03_08_105849_add_lora_columns_to_devices_table.php](../database/migrations/2026_03_08_105849_add_lora_columns_to_devices_table.php)
- [app/Models/Device.php](../app/Models/Device.php)
- [app/Services/LoRaCryptoService.php](../app/Services/LoRaCryptoService.php)
- [app/Http/Controllers/Api/V1/LoRaDataController.php](../app/Http/Controllers/Api/V1/LoRaDataController.php)
- [app/Http/Requests/Api/V1/LoRaWebhookRequest.php](../app/Http/Requests/Api/V1/LoRaWebhookRequest.php)
- [app/Exceptions/LoRaReplayException.php](../app/Exceptions/LoRaReplayException.php)
- [app/Exceptions/LoRaFrameCounterGapException.php](../app/Exceptions/LoRaFrameCounterGapException.php)
- [tests/Unit/LoRaCryptoServiceTest.php](../tests/Unit/LoRaCryptoServiceTest.php)
- [tests/Feature/Api/V1/LoRaWebhookTest.php](../tests/Feature/Api/V1/LoRaWebhookTest.php)
- [docker/emqx/acl.conf](../docker/emqx/acl.conf)
- [routes/api.php](../routes/api.php)
