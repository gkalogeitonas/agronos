_Research snapshot from 2026-11-07, first posted on #86. It records what the sources said then; it is not a decision. The decisions taken from it are in ADR-0010._

# Μοντελοποίηση καλλιεργειών: έρευνα σε πρωτογενείς πηγές

Ημερομηνία έρευνας: 2026-11-07. Πλαίσιο: προδιαγραφή του feature «καλλιέργειες» στο Agronos.

Σύμβαση σήμανσης:

- Κάθε ισχυρισμός χωρίς σήμανση έχει δίπλα το URL που τον στηρίζει.
- **[ΜΗ ΕΠΑΛΗΘΕΥΜΕΝΟ]**: δεν βρέθηκε ή δεν διαβάστηκε η πρωτογενής πηγή.
- **[ΣΥΜΠΕΡΑΣΜΑ]**: δικός μου υπολογισμός ή ερμηνεία πάνω σε δεδομένα πηγής, όχι κάτι που λέει η ίδια η πηγή.

## 1. Μοντέλα δεδομένων: η καλλιέργεια και η σύνδεσή της με τη γη

### Σύνοψη

| Σύστημα | Η καλλιέργεια δένεται σε | Χρονικά οριοθετημένη; | Είδος και ποικιλία | Κατάλογος |
|---|---|---|---|---|
| farmOS | Plant asset (φύτευση), που μετακινείται σε Land asset με movement log | Ναι, μέσω logs και season | Ένα κοινό λεξιλόγιο «Crop/variety» | Taxonomy του instance, auto-create |
| ADAPT | CropZone μέσα σε Field | Ναι, TimeScopes / Season | Χωριστά: Crop και CropVarietyProduct | Reference data στο έγγραφο |
| John Deere | Field Operation σε Field, με cropSeason | Ναι, ανά εργασία και έτος | Crop id και variety σε επίπεδο εργασίας | Global `/cropTypes` |
| LiteFarm | Management plan → planting plan → location | Ναι, start/complete dates | Χωριστά: crop και crop_variety | Hybrid: global reviewed + ανά farm |
| FIWARE | AgriParcel.hasAgriCrop | Όχι ουσιαστικά, τρέχουσα κατάσταση | Δεν φαίνεται πεδίο ποικιλίας | AgriCrop με σύνδεση σε AGROVOC |

Κανένα από τα πέντε δεν δένει την καλλιέργεια σε αισθητήρα. Κανένα δεν τη δένει ως απλό πεδίο της φάρμας.

### farmOS

- Ο τύπος `plant` έχει δύο πεδία: `plant_type` (ετικέτα «Crop/variety», required, multiple, `auto_create`) και `season` (multiple, `auto_create`). Πηγή: [Plant.php](https://github.com/farmOS/farmOS/blob/4.x/modules/asset/plant/src/Plugin/Asset/AssetType/Plant.php).
- Το λεξιλόγιο περιγράφεται ως «A list of crops/varieties», δηλαδή είδος και ποικιλία μοιράζονται την ίδια λίστα: [taxonomy.vocabulary.plant_type.yml](https://github.com/farmOS/farmOS/blob/4.x/modules/taxonomy/plant_type/config/install/taxonomy.vocabulary.plant_type.yml). Οι όροι έχουν crop family, companions, days to maturity/harvest/transplant: [Term types](https://farmos.org/model/type/term/). Αν χρησιμοποιείται ιεραρχία όρων για είδος και ποικιλία [ΜΗ ΕΠΑΛΗΘΕΥΜΕΝΟ].
- Το `plant` είναι κινητό asset. Η θέση του προκύπτει από movement logs, και η θέση σε οποιαδήποτε στιγμή βρίσκεται με query στα logs. Το Land είναι fixed asset με εγγενή γεωμετρία, και οι ουσιαστικές αλλαγές θέλουν νέο asset και αρχειοθέτηση του παλιού: [Location logic](https://farmos.org/model/logic/location/).
- Το season είναι ετικέτα. Το quick form τη χαρακτηρίζει «for organizing assets for future reference», π.χ. «2026 Summer», και υποστηρίζει μίξη πολλών crops σε μία φύτευση: [Planting.php](https://github.com/farmOS/farmOS/blob/4.x/modules/quick/planting/src/Plugin/QuickForm/Planting.php).
- Αλλαγή καλλιέργειας: νέο plant asset στην ίδια τοποθεσία. Ειδικός χειρισμός πολυετών δεν βρέθηκε στα έγγραφα που διάβασα [ΜΗ ΕΠΑΛΗΘΕΥΜΕΝΟ].

### ADAPT / AgGateway

- Το αποθετήριο `github.com/ADAPT/ADAPT` που δείχνει το [NuGet nuspec](https://api.nuget.org/v3-flatcontainer/aggatewayadaptframework/3.1.0/aggatewayadaptframework.nuspec) δεν υπάρχει πια. Ο κώδικας είναι στο [AgGateway-ADAPT/ADAPT](https://github.com/AgGateway-ADAPT/ADAPT).
- [CropZone.cs](https://github.com/AgGateway-ADAPT/ADAPT/blob/develop/source/ADAPT/Logistics/CropZone.cs): `FieldId`, `CropId`, `TimeScopes`, `BoundingRegion`, `Area`. Το [Field.cs](https://github.com/AgGateway-ADAPT/ADAPT/blob/develop/source/ADAPT/Logistics/Field.cs) έχει `FarmId`, `ActiveBoundaryId` και **δεν έχει** `CropId`. Ούτε το [Farm.cs](https://github.com/AgGateway-ADAPT/ADAPT/blob/develop/source/ADAPT/Logistics/Farm.cs).
- Είδος και ποικιλία είναι χωριστά: το [Crop.cs](https://github.com/AgGateway-ADAPT/ADAPT/blob/develop/source/ADAPT/Products/Crop.cs) έχει `ParentId`, το [CropVarietyProduct.cs](https://github.com/AgGateway-ADAPT/ADAPT/blob/develop/source/ADAPT/Products/CropVarietyProduct.cs) είναι Product με `CropId`.
- Ο διάδοχος ADAPT Standard (JSON schema, άδεια MIT, τελευταίο commit 2026-08) ορίζει το `cropZone` ως «A more detailed representation of/within a Field, defining a crop, its growing season and usually a separate boundary. Where Crop Zone is used, Field is also required», με πεδία `fieldId`, `cropId`, `seasonIds`, `timeScopes`, `boundary`. Το `season` είναι οντότητα με `start`/`end`, περίπου ένα έτος, και οι σεζόν **μπορούν να επικαλύπτονται** στους μήνες της συγκομιδής: [adapt-root-schema.json](https://github.com/AgGateway-ADAPT/Standard/blob/main/adapt-root-schema.json).
- Ειδικός χειρισμός πολυετών δεν υπάρχει στο schema [ΣΥΜΠΕΡΑΣΜΑ από τα πεδία]. Το CropZone ανά Season καλύπτει και αυτά.

### John Deere Operations Center API

- Field: «an area of land within set boundaries assigned by a grower». Στη σελίδα, με ανοιγμένα όλα τα sample responses, η λέξη crop εμφανίζεται μόνο στα OAuth scopes: [Fields](https://developer.deere.com/dev-docs/fields). Το Field δεν έχει crop.
- Η καλλιέργεια εμφανίζεται στο Field Operation: `fieldOperationType`, `cropSeason` (έτος), `startDate`/`endDate`, `varietyTotals` στη σπορά, και στήλες `CROP` και `VARIETY` στα point-level shapefiles: [Field Operations](https://developer.deere.com/dev-docs/field-operations). Η καλλιέργεια δηλαδή προκύπτει από ό,τι έγινε στο χωράφι ανά σεζόν. Το μοντέλο είναι machine-first.
- Ο κατάλογος είναι global `GET /cropTypes`, με `name`, `translatedName`, `color`, density και moisture factors. Το sample δείχνει `"total": 216`: [Crop Types](https://developer.deere.com/dev-docs/crop-types).
- Υπάρχει scope «Work and Crop Plans». Τα σχετικά APIs δεν τα εξέτασα [ΜΗ ΕΠΑΛΗΘΕΥΜΕΝΟ].

### LiteFarm

- `crop`: `crop_common_name`, `crop_genus`, `crop_specie`, `crop_group`, `crop_subgroup`, καθώς και `max_rooting_depth`, `depletion_fraction`, `initial_kc`, `mid_kc`, `end_kc`. Δηλαδή τα αγρονομικά defaults ζουν στο είδος: [cropModel.js](https://github.com/LiteFarmOrg/LiteFarm/blob/integration/packages/api/src/models/cropModel.js).
- `crop_variety`: ανά farm, με `crop_id`, `lifecycle` (`ANNUAL`/`PERENNIAL`), `crop_variety_name`: [cropVarietyModel.js](https://github.com/LiteFarmOrg/LiteFarm/blob/integration/packages/api/src/models/cropVarietyModel.js).
- `management_plan`: `crop_variety_id`, `start_date`, `complete_date`, `abandon_date`: [managementPlanModel.js](https://github.com/LiteFarmOrg/LiteFarm/blob/integration/packages/api/src/models/managementPlanModel.js). Η γη μπαίνει ένα επίπεδο πιο κάτω: `planting_management_plan` με `location_id` ή `pin_coordinate`: [plantingManagementPlanModel.js](https://github.com/LiteFarmOrg/LiteFarm/blob/integration/packages/api/src/models/plantingManagementPlanModel.js).
- Για δέντρα που ήδη υπάρχουν: `already_in_ground`, `is_wild`, καθώς και `harvest_date`, `termination_date`: [cropManagementPlanModel.js](https://github.com/LiteFarmOrg/LiteFarm/blob/integration/packages/api/src/models/cropManagementPlanModel.js).
- Κατάλογος hybrid: το `get(farm_id)` φέρνει `where('reviewed', true).orWhere({ farm_id, deleted: false })`, δηλαδή ελεγμένα global είδη συν τα δικά της φάρμας: [cropController.js](https://github.com/LiteFarmOrg/LiteFarm/blob/integration/packages/api/src/controllers/cropController.js). Το [crop_data.csv](https://github.com/LiteFarmOrg/LiteFarm/blob/integration/packages/api/db/seeds/seedData/crop_data.csv) έχει 167 εγγραφές. Υπάρχει και νεότερο `cropV2.js`, το πλήθος του οποίου δεν μέτρησα [ΜΗ ΕΠΑΛΗΘΕΥΜΕΝΟ].
- Προσοχή στην αντιγραφή: στο csv το Orange έχει ίδιες τιμές με τα Grapes (Kc 0.3/0.85/0.45, p 0.35, ρίζα 1.5), που δεν ταιριάζουν με το FAO Table 12/22 για citrus. Το Tomato έχει `mid_kc` 1.152, που μοιάζει με το 1.15 με κολλημένο τον δείκτη υποσημείωσης 2 του FAO [ΣΥΜΠΕΡΑΣΜΑ]. Το repo είναι [GPL-3.0](https://github.com/LiteFarmOrg/LiteFarm).

### FIWARE Smart Data Models (AgriFood)

- [AgriParcel](https://github.com/smart-data-models/dataModel.Agrifood/blob/master/AgriParcel/schema.json) (v1.0.2): required `location`, `area`, `hasAgriCrop`. Ένα crop ανά parcel, με `hasAgriParcelParent`/`hasAgriParcelChildren`, `cropStatus` (seeded, justBorn, growing, maturing, readyForHarvesting), `lastPlantedAt`, `hasDevice`, `soilTextureType`, `irrigationSystemType`. Τρέχουσα κατάσταση, χωρίς ιστορικό καλλιεργειών.
- [AgriCrop](https://github.com/smart-data-models/dataModel.Agrifood/blob/master/AgriCrop/schema.json) (v0.0.7): καταλογική οντότητα με `agroVocConcept`, `hasAgriSoil`, `plantingFrom`, `harvestingInterval`, `wateringFrequency`. Στις ειδικές ιδιότητες δεν έχει ποικιλία.
- [AgriParcelRecord](https://github.com/smart-data-models/dataModel.Agrifood/blob/master/AgriParcelRecord/schema.json) (v0.1.1): μετρήσεις (`soilMoistureVwc`, `soilTemperature`) με `hasAgriParcel`, `hasDevice` και `depth`. Η μέτρηση δένεται στο parcel και κρατά το βάθος.

## 2. Κατάλογος ειδών

### Πρότυπες λίστες

| Λίστα | Μέγεθος | Άδεια | Ελληνικά | Πρακτικό για seed; |
|---|---|---|---|---|
| EPPO Codes | >98.500 είδη, εκ των οποίων 59.000 φυτά, συν 625 μη ταξινομικές οντότητες | Open Data Licence: εμπορική χρήση ναι, αναφορά στην EPPO και στην ημερομηνία λήψης | Ναι στον ιστότοπο, με κενά | Όχι ολόκληρο. Ναι ως στήλη κωδικού σε μικρό επιμελημένο σύνολο |
| AGROVOC | >40.600 έννοιες (Δεκ. 2022) | CC BY 3.0 IGO για τις γλώσσες FAO | `el` υπάρχει ως γλώσσα, με κενά | Μόνο ως εξωτερικό URI |
| FAO ICC 1.1 | 196 εγγραφές | Η σελίδα δεν αναφέρει άδεια | Όχι | Καλό για ομαδοποίηση |
| FAO WCA 2020 crops | 326 εγγραφές με βοτανικό όνομα | Η σελίδα δεν αναφέρει άδεια | Όχι | Καλή βάση, με μετάφραση |
| HCAT3 (EuroCrops) | 384 εγγραφές, κωδικός 10 ψηφίων | Repo CC-BY-SA-4.0 | Όχι | Ιεραρχία ΕΕ, αλλά χωρίς Ελλάδα |
| Κωδικοί ΕΑΕ | Άγνωστο | Άγνωστη | Ναι | Δεν βρέθηκε δημόσιο αρχείο |

- EPPO: κωδικοί 5 γραμμάτων για φυτά, π.χ. 3 γένος + 2 είδος: [EPPO Codes](https://www.eppo.int/RESOURCES/eppo_databases/eppo_codes). Ελεύθερη λήψη με εγγραφή: [EPPO Data Services](https://data.eppo.int/), [άδεια](https://data.eppo.int/data/Open_Licence.pdf). Στη βάση υπάρχουν ελληνικά ονόματα για [OLVEU](https://gd.eppo.int/taxon/OLVEU) («ελαία ευρωπαϊκή»), [VITVI](https://gd.eppo.int/taxon/VITVI), [LYPES](https://gd.eppo.int/taxon/LYPES) («τομάτα»), [CIDSI](https://gd.eppo.int/taxon/CIDSI) («πορτοκαλιά»). Για [GOSHI](https://gd.eppo.int/taxon/GOSHI) (βαμβάκι) δεν βρήκα. Αν το αρχείο λήψης περιέχει τα ελληνικά ονόματα [ΜΗ ΕΠΑΛΗΘΕΥΜΕΝΟ, θέλει εγγραφή].
- AGROVOC: άδεια και πνευματικά δικαιώματα στο [FAQ](https://aims.fao.org/standards/agrovoc/faq). Το περιεχόμενο σε άλλες γλώσσες ανήκει στους φορείς που το συνέταξαν, άρα και τα ελληνικά. Μέγεθος: [FAO, Introducing AGROVOC](https://openknowledge.fao.org/server/api/core/bitstreams/0973e3dd-be02-4448-9471-cb512ea3f8af/content). Στο [REST API](https://agrovoc.fao.org/browse/rest/v1/agrovoc/) η έννοια «olives» (c_12926) και η «Olea europaea» (c_5339) δεν επέστρεψαν ελληνική ετικέτα στις 2026-11-07.
- ICC 1.1 και WCA 2020: [Caliper downloads](https://www.fao.org/statistics/caliper/tools/download/en), απευθείας [ICC11-core.csv](https://storage.googleapis.com/fao-datalab-caliper/Downloads/ICCv1.1/ICC11-core.csv), [WCACROPS-core.csv](https://stats.fao.org/caliper/download/WCA2020Crops/WCACROPS-core.csv), [ICC→AGROVOC](https://stats.fao.org/caliper/download/ICCv1.1/ICC11-AGROVOC.csv). Το WCA χωρίζει «Grapes for wine», «for table use», «for raisins», που συμπίπτει με το διαχωρισμό του FAO-56. Κρατά το παλιό όνομα *Lycopersicon esculentum*, ενώ η EPPO έχει *Solanum lycopersicum*.
- HCAT3: [HCAT3.csv](https://github.com/maja601/EuroCrops/blob/main/hcat_core/HCAT3.csv), χωρίζει `olives_for_oil_production` από `table_olives`. Στον φάκελο [country_mappings](https://github.com/maja601/EuroCrops/tree/main/csvs/country_mappings) δεν υπάρχει αρχείο για την Ελλάδα.

### Ελλάδα: ΕΑΕ και ΟΠΕΚΕΠΕ/ΑΑΔΕ

- Η ΕΑΕ 2026 τρέχει πλέον στο myAGRO της ΑΑΔΕ: [Δελτίο Τύπου 4/8/2026](https://www.aade.gr/sites/default/files/2026-08/dt_04.08.2026_.pdf). Το νομοσχέδιο μετάβασης: [ΥΠΑΑΤ](https://www.minagric.gr/2013-04-05-10-13-09/ministry-example/ypaat-grafeio-typou/deltiatypou/18602-dt251125b).
- **Κανόνας μίας καλλιέργειας ανά αγροτεμάχιο.** Η [Α.1161/2026](https://www.aade.gr/sites/default/files/2026-08/a1161-2026.pdf) απαιτεί «μία μοναδική καλλιέργεια/χρήση γης εντός εκάστου αγροτεμαχίου» και ότι «αγροτεμάχιο που αποτελείται από δύο διακριτές καλλιέργειες επιμερίζεται υποχρεωτικά σε δύο διακριτά τεμάχια». Ο Έλληνας παραγωγός έχει ήδη ως νοητικό μοντέλο το αγροτεμάχιο με μία καλλιέργεια ανά έτος αίτησης.
- Η φόρμα καταχώρισης έχει Καλλιέργεια, Ποικιλία, Ποικιλία ΕΛ.Γ.Α., Επιλέξιμη Έκταση, Άρδευση, επισπορή: [Χρηστικός Οδηγός ΕΑΕ 2026](https://www.aade.gr/sites/default/files/2026-08/Xristikos_Odigos_myAGRO-2026.pdf) (Εικ. 49, 56, 57). Άρα δύο επίπεδα, είδος και ποικιλία. Η λίστα είναι κλειστή: ποικιλία εκτός λίστας αξιολογείται μετά από αίτημα στο my1521: [FAQ v1.5](https://www.aade.gr/sites/default/files/2026-10/FAQs_myAGRO_5.pdf), ερώτηση 59.
- Τον ίδιο τον πίνακα κωδικών καλλιεργειών/ποικιλιών **δεν τον βρήκα** σε δημόσιο αρχείο. Το opekepe.gr απαντούσε HTTP 522 στις 2026-11-07, και το αρχειοθετημένο εγχειρίδιο ΕΑΕ 2025 ήταν κατεστραμμένο. Από αποσπάσματα αναζήτησης μόνο, το εγχειρίδιο 2021 περιέγραφε αρχείο με κωδικό, περιγραφή, ποικιλίες και αντιστοίχιση ΕΛΓΑ [ΜΗ ΕΠΑΛΗΘΕΥΜΕΝΟ]. Άδεια και μέγεθος [ΜΗ ΕΠΑΛΗΘΕΥΜΕΝΟ].
- Ανοιχτή δημοσίευση ελληνικού LPIS/ΟΣΔΕ σε data.gov.gr ή INSPIRE δεν βρέθηκε [ΜΗ ΕΠΑΛΗΘΕΥΜΕΝΟ, αρνητικό αποτέλεσμα αναζήτησης].

### Ποιες καλλιέργειες μετρούν στην Ελλάδα

[ΕΛΣΤΑΤ, Ετήσια Γεωργική Στατιστική Έρευνα 2024](https://www.statistics.gr/documents/20181/73bb1fdb-a5ea-596e-0bb5-a6660fc6b02b) (δημοσίευση Ιουνίου 2026), χιλ. στρέμματα:

| Κατηγορία | 2024 | % επί 27.061,2 [ΣΥΜΠΕΡΑΣΜΑ] |
|---|---:|---:|
| Ελαιόδεντρα | 6.950,5 | 25,7 |
| Σιτηρά για καρπό | 6.927,3 | 25,6 |
| Κτηνοτροφικά φυτά (εκ των οποίων μηδική 1.719,7) | 5.355,0 | 19,8 |
| Βαμβάκι | 2.241,4 | 8,3 |
| Αμπέλια | 646,3 | 2,4 |
| Πυρηνόκαρπα (ροδάκινο-νεκταρίνι 338,9) | 618,6 | 2,3 |
| Ακρόδρυα (αμύγδαλο 181,1, καρύδι 167,5) | 514,7 | 1,9 |
| Εσπεριδοειδή (πορτοκάλι 275,6) | 400,4 | 1,5 |
| Μηλοειδή και λοιπά (ακτινίδιο 145,0) | 345,9 | 1,3 |

- Η ντομάτα δεν δίνεται ως έκταση στους πίνακες αυτούς. Η παραγωγή της ήταν 745,2 χιλ. τόνοι.
- Το ποσοστό είναι ενδεικτικό, γιατί ο Πίνακας 2 δεν είναι καθαρή έκταση και τα σύνολα διαφέρουν από τον Πίνακα 1. Τα στοιχεία 2023 έχουν μερικώς αναθεωρηθεί.
- Αρδευόμενα το 2024: 10.936,7 από 27.061,2, ενώ στις μόνιμες καλλιέργειες 3.622,1 από 9.675,0 (37%). Οι αρδευόμενες εκτάσεις περιλαμβάνουν δευτερεύουσες καλλιέργειες, άρα τα ποσοστά δεν είναι ακριβή. Το μεγαλύτερο μέρος των μόνιμων καλλιεργειών δεν φαίνεται αρδευόμενο, οπότε ένα μοντέλο που προϋποθέτει άρδευση δεν θα καλύπτει πολλούς ελαιώνες [ΣΥΜΠΕΡΑΣΜΑ].

## 3. Προϊόντα IoT και άρδευσης: σύνδεση καλλιέργειας με σταθμό ή probe

| Προϊόν | Φορέας της καλλιέργειας | Σύνδεση αισθητήρα | Χρόνος | Όριο υγρασίας |
|---|---|---|---|---|
| CropX | Field | Χωρική, αυτόματη | Ημερομηνίες προαιρετικές | [ΜΗ ΕΠΑΛΗΘΕΥΜΕΝΟ] |
| Sentek IrriMAX Live | Zone | Zone με loggers, αυτόματη πρόταση | Growth stages | Budget lines ανά graph |
| Semios | Block | [ΜΗ ΕΠΑΛΗΘΕΥΜΕΝΟ] | Ημερομηνίες ισχύος ορίου | Ανά block, default 70% AWC |
| Arable | Season πάνω σε Site | Χωρική με εξαιρέσεις | Season με start/end | Ανά Site |
| Pessl FarmView / FieldClimate | Cropzone | Επιλογή σταθμών ≤10 km | Cropzone ανά σεζόν | Ανά node, default 50% PAW |
| Davis Mobilize | «each crop» | [ΜΗ ΕΠΑΛΗΘΕΥΜΕΝΟ] | Planting date | [ΜΗ ΕΠΑΛΗΘΕΥΜΕΝΟ] |

- **CropX.** Το crop, το soil και το irrigation ορίζονται στα Field Settings, και οι ημερομηνίες φύτευσης/ανάδυσης/ωρίμανσης ζητούνται «if available». Εκτός ΗΠΑ το έδαφος μπαίνει χειροκίνητα. Αισθητήρας εκτός ορίου χωραφιού φαίνεται Unassigned και δεν δίνει field-level insights, ενώ όταν σχεδιαστεί field γύρω του συνδέεται αυτόματα: [How to Create a Field](https://help.cropx.com/portal/en/kb/articles/how-to-draw-and-create-a-field), [How to Delete a Field](https://help.cropx.com/portal/en/kb/articles/archive-a-field-16-2-2023). Χειρισμός νέας σεζόν δεν βρέθηκε [ΜΗ ΕΠΑΛΗΘΕΥΜΕΝΟ].
- **Sentek IrriMAX Live.** «Zones are records of crop and irrigation information», με polygon, και «a zone can contain one or more loggers». Αν ο logger έχει θέση, το σύστημα επιλέγει τη μικρότερη zone που την περικλείει. Τα agronomic alerts βασίζονται σε budget lines (Full, Refill, Stress) του graph, όχι στην καλλιέργεια: [Terminology](https://www.irrimaxlive.com/help/concepts.html). Το Adaptive Irrigation Guide υπολογίζει full και stress από τη ριζική δραστηριότητα στα δεδομένα και, ως εφεδρικό, από τύπο εδάφους. Οι τιμές αυτές δηλώνονται «inaccurate» και προσωρινές: [Adaptive Irrigation Guide](https://www.irrimaxlive.com/help/automatic-budget-lines.html).
- **Semios.** Τα Kc models δημιουργούνται ανά crop και organization και ανατίθενται ανά block: [Custom ET](https://hub-support.semios.com/en/articles/8055065-how-to-set-a-custom-evapotranspiration-et-rate). Το refill point ορίζεται ανά block με ημερομηνία ισχύος, και επιτρέπονται πολλά όρια ανά σεζόν για στάδια όπως veraison. Το default 70% AWC είναι ρητά «not a scientific recommendation for all crops and soils»: [Custom MAD](https://hub-support.semios.com/en/articles/8054931-how-to-set-a-custom-mad-refill-point-threshold). Η μέτρηση αναφέρεται στο root zone average, με επιλογή βαθών: [Soil Layer](https://hub-support.semios.com/en/articles/8896054-water-management-soil), [AWC](https://hub-support.semios.com/en/articles/10262326-how-to-view-and-interpret-available-water-content-awc). Πώς δένεται probe σε block [ΜΗ ΕΠΑΛΗΘΕΥΜΕΝΟ].
- **Arable.** Το Site έχει boundary. Οι συσκευές είναι Included (μέσα στο όριο), Assigned (εκτός ορίου, αλλά ανατεθειμένες) ή Excluded (μέσα, αλλά εξαιρούνται τα δεδομένα τους): [Sites Onboarding Guide](https://learn.arable.com/hubfs/Arable%20Mobile%20App_%20Sites%20-%20Onboarding%20Guide.pdf). Το Season συνδέεται με το Site και κρατά crop type, varietal, start (~φύτευση), end (~συγκομιδή) και growth stage. Τα growth stages έχουν defaults «for some Varietals» και είναι επεξεργάσιμα. Texture, refill threshold και βάθη ορίζονται στο Site, και τα field capacity και wilting point είναι απαραίτητα για το γράφημα υγρασίας: [Water Activation Guide](https://learn.arable.com/hubfs/Water%20Activation%20Guide_RevB.pdf).
- **Pessl.** Farm → Field → Cropzone, όπου Cropzone είναι «your field or a portion of your field that is managed identically within given borders during a given time». Για πολυετή: «define every single season in a separate Cropzone». Το crop name είναι ελεύθερο κείμενο που «has no automatic implications», ενώ το Irrimet ζητά crop από «FAO crop type table» και δίνει default Kc και βάθος ρίζας, επεξεργάσιμα. Επιλέγονται σταθμοί σε ακτίνα 10 km: [FarmView manual](https://metos.global/en/farmview-manual/). Στο FieldClimate, Refill/Full Point ορίζονται ανά node, default 50% PAW. Στο εγχειρίδιο η καλλιέργεια εμφανίζεται ως επιλογή στη σελίδα Disease Models, για να διαλέξει κανείς μοντέλο: [FieldClimate manual](https://metos.global/en/fieldclimate-manual/).
- **Davis.** «When you plant, set each crop's type, planting date, base threshold, and target GDD in the app». Σε ποια οντότητα δένεται δεν λέγεται: [Mobilize GDD](https://www.davisinstruments.com/blogs/newsletter/mobilize-puts-custom-growing-degree-day-reports-in-the-palm-of-your-hand). Πρόκειται για blog, όχι τεχνική τεκμηρίωση.

Σε κανένα από τα έξι προϊόντα δεν βρήκα καλλιέργεια ως ιδιότητα του αισθητήρα. Αυτό που ορίζεται ανά αισθητήρα ή node είναι το όριο.

## 4. Αγρονομική αξία: FAO-56 και BBCH

### Τιμές για ελληνικές καλλιέργειες

Από το FAO-56 ([Κεφ. 6](https://www.fao.org/4/x0490e/x0490e0b.htm): Tables 11, 12· [Κεφ. 8](https://www.fao.org/4/x0490e/x0490e0e.htm): Table 22). Οι αριθμοί σελίδων του έντυπου δεν επαληθεύτηκαν, άρα παραπέμπω σε αριθμό πίνακα.

| Καλλιέργεια | Kc ini | Kc mid | Kc end | Ύψος (m) | Zr (m) | p | Στάδια ini/dev/mid/late (ημέρες) |
|---|---|---|---|---|---|---|---|
| Ελιά (κάλυψη 40-60%) | 0.65 | 0.70 | 0.70 | 3-5 | 1.2-1.7 | 0.65 | 30/90/60/90 = 270, Μάρτ., Mediterranean |
| Αμπέλι οινοποιήσιμο | 0.30 | 0.70 | 0.45 | 1.5-2 | 1.0-2.0 | 0.45 | 30/60/40/80 = 210, Απρ., Mid Latitudes (wine) |
| Αμπέλι επιτραπέζιο/σταφίδα | 0.30 | 0.85 | 0.45 | 2 | 1.0-2.0 | 0.35 | 20/50/75/60 = 205, Μάρτ., Calif. |
| Εσπεριδοειδή, χωρίς φυτοκάλυψη, 70% κόμη | 0.70 | 0.65 | 0.70 | 4 | 1.2-1.5 | 0.50 | 60/90/120/95 = 365, Ιαν., Mediterranean |
| Εσπεριδοειδή, χωρίς φυτοκάλυψη, 50% κόμη | 0.65 | 0.60 | 0.65 | 3 | 1.1-1.5 | 0.50 | ίδια |
| Βαμβάκι | 0.35 | 1.15-1.20 | 0.70-0.50 | 1.2-1.5 | 1.0-1.7 | 0.65 | 30/50/60/55 = 195, Μάρτ.-Μάιος, Αίγυπτος/Πακιστάν/Καλιφ. |
| Τομάτα | 0.6 | 1.15 | 0.70-0.90 | 0.6 | 0.7-1.5 | 0.40 | 30/40/45/30 = 145, Απρ.-Μάιος, Mediterranean |

Τι περιορίζει αυτούς τους αριθμούς (όλα από τα ίδια κεφάλαια):

- Οι τιμές Table 12 αφορούν sub-humid κλίμα και μη καταπονημένες καλλιέργειες. Τα Kc mid και Kc end προσαρμόζονται με άνεμο, RHmin και ύψος φυτού (Eq. 62, 65). Στα ξηρά ελληνικά καλοκαίρια η διόρθωση έχει σημασία [ΣΥΜΠΕΡΑΣΜΑ].
- Το Table 11 είναι, κατά το FAO, «useful only as a general guide». Για Ελλάδα δεν υπάρχει γραμμή βαμβακιού σε Mediterranean.
- Για ελιά, το FAO παραθέτει μηνιαία Kc από Ισπανία (Pastor & Orgaz 1994, κάλυψη 60%): 0.50, 0.50, 0.65, 0.60, 0.55, 0.50, 0.45, 0.45, 0.55, 0.60, 0.65, 0.50 (Table 12, υποσημ. 24). Η ελιά δεν ταιριάζει καλά στο σχήμα ini/mid/end.
- Στα δέντρα ο Kc εξαρτάται από την κάλυψη κόμης (βλ. εσπεριδοειδή) και από τη φυτοκάλυψη, όχι μόνο από το είδος.
- Ο Kc δεν δίνει όριο υγρασίας. Δίνει ETc = Kc × ETo, και το ETo χρειάζεται ακτινοβολία, θερμοκρασία, υγρασία αέρα και άνεμο: [FAO-56 Κεφ. 1](https://www.fao.org/4/x0490e/x0490e04.htm).

### Το όριο υγρασίας εξαρτάται από καλλιέργεια και έδαφος

Από το [Κεφ. 8](https://www.fao.org/4/x0490e/x0490e0e.htm):

- TAW = 1000 (θFC − θWP) Zr (Eq. 82). RAW = p · TAW (Eq. 83). Το TAW «depends on the type of soil and the rooting depth».
- Το p αφορά ETc ≈ 5 mm/ημέρα, με διόρθωση p = p_table + 0.04 (5 − ETc), 0.1 ≤ p ≤ 0.8.
- Το p εξαρτάται και από το έδαφος: σε αργιλώδη μειώνεται 5-10%, σε αμμώδη αυξάνεται 5-10%.
- Μικρότερες τιμές Zr για προγραμματισμό άρδευσης, μεγαλύτερες για μοντελοποίηση ή ξηρική (Table 22, υποσημ. 1).
- MAD: παρόμοιο με το RAW, αλλά επηρεάζεται από διαχειριστικούς και οικονομικούς παράγοντες. MAD < RAW με αποστροφή κινδύνου, MAD > RAW όταν το στρες είναι σκόπιμο (π.χ. ελλειμματική άρδευση).

Παράδειγμα FAO για τομάτα, Zr ≈ 0.8 m, p = 0.40 (Κεφ. 8). Η τελευταία στήλη είναι δικός μου υπολογισμός, θ_όριο = θFC − p(θFC − θWP):

| Έδαφος | θFC | θWP | TAW (mm) | RAW (mm) | θ_όριο [ΣΥΜΠΕΡΑΣΜΑ] |
|---|---:|---:|---:|---:|---:|
| Loamy sand | 0.15 | 0.06 | 72 | 29 | 0.114 (11%) |
| Silt | 0.32 | 0.15 | 136 | 54 | 0.252 (25%) |
| Silty clay | 0.35 | 0.23 | 96 | 38 | 0.302 (30%) |

Ίδια καλλιέργεια, ίδιο p, και το όριο σε VWC σχεδόν τριπλασιάζεται από έδαφος σε έδαφος. Τιμή «όριο σε % ανά καλλιέργεια» χωρίς έδαφος δεν έχει αγρονομικό έρεισμα [ΣΥΜΠΕΡΑΣΜΑ]. Τυπικές τιμές εδαφών: [Table 19, Κεφ. 7](https://www.fao.org/4/x0490e/x0490e0c.htm), π.χ. sandy loam θFC 0.18-0.28, θWP 0.06-0.16· loam 0.20-0.30, 0.07-0.17· silt loam 0.22-0.36, 0.09-0.21.

### Τι μπορεί να είναι default και τι όχι

| Παράμετρος | Επίπεδο | Πηγή τιμής |
|---|---|---|
| Kc ini/mid/end, Zr, p, στάδια | Ανά είδος/χρήση, ως default | FAO-56 Tables 11, 12, 22, με τις παραπάνω επιφυλάξεις |
| Φύτευση, ανθοφορία, συγκομιδή | Ανά φύτευση και έτος | Χρήστης |
| Κάλυψη κόμης, φυτοκάλυψη | Ανά φύτευση | Χρήστης |
| θFC, θWP, υφή | Ανά αγροτεμάχιο/ζώνη | Χρήστης, ανάλυση, ή Table 19 ως χονδρική εκτίμηση |
| Όριο VWC, RAW, TAW | Παράγωγα | Καλλιέργεια μαζί με έδαφος |
| MAD | Ανά διαχειριστή και στάδιο | Χρήστης |
| ETo | Ανά τοποθεσία και ημέρα | Μετεωρολογικά δεδομένα |

### BBCH

- Το [BBCH Monograph 2018](https://www.julius-kuehn.de/media/Veroeffentlichungen/bbch%20epaper%20en/page.pdf) (JKI, DOI 10.5073/20180906-074619) είναι CC BY 4.0 και καλύπτει ξεχωριστά βαμβάκι (1.3.10), citrus (1.3.14), αμπέλι (1.3.17), solanaceous fruits με τομάτα (1.3.25).
- **Η ελιά δεν υπάρχει στο monograph.** Η κλίμακά της είναι στο Sanz-Cortés et al. 2002, Annals of Applied Biology 140:151-157, DOI 10.1111/j.1744-7348.2002.tb00167.x. Το πλήρες κείμενο δεν το διάβασα [ΜΗ ΕΠΑΛΗΘΕΥΜΕΝΟ].
- Οι δέκα κύριες φάσεις 0-9 αναφέρονται εδώ από μνήμη, γιατί το κείμενο του PDF σε εκείνες τις σελίδες δεν εξήχθη καθαρά [ΜΗ ΕΠΑΛΗΘΕΥΜΕΝΟ].
- Επίσημη αντιστοίχιση BBCH με τα τέσσερα στάδια FAO δεν βρήκα. Τα εμπορικά προϊόντα χρησιμοποιούν ονομαστικά στάδια (Semios: hull split, veraison) ή στάδια κατά ποικιλία (Arable), όχι FAO stages.

## 5. Τι σημαίνει για το Agronos

Τα παρακάτω είναι μοτίβα και επιλογές. Οι αποφάσεις είναι του ιδιοκτήτη.

### Μοτίβα που επαναλαμβάνονται

1. **Μονάδα καλλιέργειας = πολύγωνο ή τοποθεσία μαζί με χρόνο.** ADAPT CropZone, LiteFarm plan, Pessl Cropzone, Arable Season, farmOS plant asset, και η ΕΑΕ κατ' ουσία (αγροτεμάχιο, ένα έτος, μία καλλιέργεια). Εξαιρέσεις: AgriParcel (τρέχουσα κατάσταση) και CropX (ρύθμιση χωραφιού, ιστορικό δεν βρέθηκε).
2. **Ο αισθητήρας δεν κουβαλά καλλιέργεια.** Κληρονομεί από τη μονάδα όπου βρίσκεται, χωρικά (CropX, IrriMAX, Arable) ή με ρητή επιλογή (Pessl, Arable Assigned). Το Arable Excluded δείχνει ότι χρειάζεται και εξαίρεση από τον χωρικό κανόνα.
3. **Η καλλιέργεια δεν δένεται στο ανώτερο επίπεδο («Farm» ως σύνολο χωραφιών) σε κανένα μοντέλο όπου το σχήμα ήταν ορατό.** Το κατώτερο σταθερό επίπεδο είναι το Field ή το CropZone. Προσοχή στους όρους όμως: η Farm του Agronos είναι «γη με προαιρετικό πολύγωνο», άρα μοιάζει περισσότερο με το Field των ADAPT, Deere και CropX παρά με τη Farm τους [ΣΥΜΠΕΡΑΣΜΑ]. Καλλιέργεια στη Farm του Agronos δεν είναι εκτός μοτίβου για φάρμα ενός χωραφιού. Το CropX κάνει ακριβώς αυτό ανά Field.
4. **Το είδος χωρίζεται από την ποικιλία** στα ADAPT, LiteFarm, Arable, ΕΑΕ. Τα farmOS και Pessl FarmView μένουν σε μία ενιαία τιμή, και όλα τα δείγματα λειτουργούν, αλλά τα defaults κρέμονται από το είδος (LiteFarm) ή το FAO crop type (Pessl).
5. **Ο κατάλογος είναι hybrid ή ενιαίος και μικρός.** Deere 216 global τύποι (κατά το sample response), LiteFarm 167 reviewed με ανά-farm προσθήκες, WCA 326. Κανείς δεν φορτώνει ολόκληρο EPPO ή AGROVOC.
6. **Τα όρια υγρασίας δεν είναι ιδιότητα της καλλιέργειας.** Ζουν σε graph, node ή block, είναι επεξεργάσιμα, συχνά χρονικά εξαρτώμενα, και τα defaults είναι ρητά χονδρικά (Semios 70%, FieldClimate 50%, IrriMAX placeholder από έδαφος). Το FAO-56 εξηγεί γιατί.
7. **Η σεζόν στα πολυετή** λύνεται με δύο τρόπους: νέα εγγραφή κάθε σεζόν (Pessl, Arable, ADAPT) ή μία μακρόχρονη εγγραφή με ανοιχτό τέλος (LiteFarm `already_in_ground`, farmOS asset).

### Επιλογές

**Α. Πού δένεται η καλλιέργεια**

| Επιλογή | Υπέρ | Κατά |
|---|---|---|
| Α1. Στη φάρμα, με override ανά αισθητήρα (αρχική ιδέα) | Μικρό κόστος, δουλεύει χωρίς ζώνες και χωρίς πολύγωνο | Το επίπεδο φάρμας ισοδυναμεί με το Field του CropX, αλλά το override ανά αισθητήρα δεν έχει προηγούμενο στις πηγές. Ο αισθητήρας γίνεται άτυπη ζώνη ενός σημείου. Κάθε αλλαγή καλλιέργειας θέλει ενημέρωση πολλών αισθητήρων. Θέλει μετάβαση όταν έρθει το #6 |
| Α2. Φύτευση με στόχο φάρμα ή ζώνη (ζώνη nullable μέχρι το #6) | Ίδιο σχήμα πριν και μετά το #6. Ταιριάζει στα ADAPT και LiteFarm | Φάρμα με πολλά είδη δεν εκφράζεται μέχρι να υπάρξουν ζώνες, εκτός αν επιτραπούν πολλαπλές φυτεύσεις ανά φάρμα, οπότε ο αισθητήρας μένει ασαφής |
| Α3. Πρώτα ζώνες (#6), μετά καλλιέργειες | Πιο κοντά στο μοτίβο 1 και στο αγροτεμάχιο της ΕΑΕ | Καθυστερεί το feature. Απαιτεί σχεδίαση πολυγώνων από όλους |
| Α4. Α2 με ρητή ανάθεση αισθητήρα σε φύτευση | Καλύπτει πολλά είδη χωρίς πολύγωνα. Μοιάζει με Arable Assigned και LiteFarm `pin_coordinate` | Νέα σχέση που επικαλύπτεται με το #6, όπου οι αισθητήρες ανατίθενται σε ζώνη |

Τα Farm polygon και Sensor lat/lon είναι προαιρετικά στο Agronos, άρα η αυτόματη χωρική ανάθεση (IrriMAX, CropX) δεν είναι εγγυημένη. Ένα μοτίβο που το καλύπτει είναι η αυτόματη πρόταση με δυνατότητα ρητής επιλογής.

**Β. Χρόνος και ιστορικό**

- Β1. Μόνο τρέχουσα καλλιέργεια (AgriParcel). Απλό, αλλά χάνεται η ερώτηση «τι καλλιεργούσα όταν μετρήθηκε αυτό».
- Β2. Εγγραφές με `start`/`end` (ανοιχτό `end` για πολυετή). Ετήσιες και πολυετείς χωρούν στο ίδιο σχήμα. Το μοτίβο 7 αφήνει ανοιχτό αν η ελιά γράφεται μία φορά ή ανά σεζόν. Ανά σεζόν κρατά ημερομηνίες σταδίων ανά έτος, με περισσότερη δουλειά για τον χρήστη.
- Σχέση με το InfluxDB: το `farm_id` παγώνει στην εισαγωγή. Για την καλλιέργεια μπορεί να γίνει το ίδιο (tag), ή να βρίσκεται κατά το query με join στα διαστήματα της MySQL. Το tag δεν διορθώνεται αν ο χρήστης δηλώσει τη φύτευση αργότερα ή αναδρομικά, κάτι πιθανό αφού οι περισσότερες πηγές έχουν τις ημερομηνίες προαιρετικές. Το query-time join επιτρέπει διόρθωση, αλλά χρειάζεται δύο πηγές ανά ερώτημα [ΣΥΜΠΕΡΑΣΜΑ].

**Γ. Κατάλογος**

- Γ1. Ελεύθερο κείμενο ανά tenant (Pessl crop name). Μηδενικό κόστος, αλλά τα defaults δεν κρέμονται από πουθενά, ούτε γίνονται συγκρίσεις.
- Γ2. Κλειστός global κατάλογος. Καθαρό, αλλά κολλά σε ό,τι λείπει.
- Γ3. Hybrid (LiteFarm): μικρός global κατάλογος με ελληνικά ονόματα, συν καταχωρίσεις ανά tenant. Global πίνακας δεν είναι tenant-scoped, άρα θέλει ρητό χειρισμό σε σχέση με το ADR-0003.
- Μέγεθος seed: οι κατηγορίες του ΕΛΣΤΑΤ δίνουν μερικές δεκάδες είδη. Ο Έλληνας χρήστης περιμένει να βρει ελαιόδεντρα, σιτηρά, βαμβάκι, αμπέλι, εσπεριδοειδή, πυρηνόκαρπα, ακρόδρυα, κηπευτικά.
- Κωδικοί: μια nullable στήλη EPPO (άδεια που επιτρέπει εμπορική χρήση, με αναφορά) κρατά ανοιχτή τη διαλειτουργικότητα, χωρίς φόρτωση της βάσης. Οι ελληνικές ονομασίες θα γραφτούν με το χέρι ή από την EPPO, με έλεγχο.
- Ποικιλία: ελεύθερο κείμενο ή ξεχωριστός πίνακας. Στις πηγές τα defaults δεν κρέμονται από την ποικιλία, με εξαίρεση τα growth stages του Arable. Η χρήση (οινοποιήσιμο, επιτραπέζιο, ελιά ελαιοποιήσιμη/βρώσιμη) αλλάζει όμως τιμές FAO, άρα χρειάζεται να είναι ξεχωριστή εγγραφή ή πεδίο.
- Το seed των αγρονομικών τιμών χρειάζεται έλεγχο σε σχέση με το FAO. Το csv του LiteFarm έχει λάθη και άδεια GPL. Το FAO-56 δεν έχει διαθέσιμη άδεια που να διάβασα, άρα η επαναχρησιμοποίηση πίνακα, έναντι αριθμών, χρειάζεται έλεγχο [ΜΗ ΕΠΑΛΗΘΕΥΜΕΝΟ].

**Δ. Όριο άρδευσης (#7, #74)**

- Δ1. Χειροκίνητο όριο ανά φάρμα ή ζώνη, ανεξάρτητο από την καλλιέργεια. Η καλλιέργεια χρησιμεύει ως ετικέτα και ως κείμενο βοήθειας (p, βάθος ρίζας). Ταιριάζει με Semios, FieldClimate, IrriMAX.
- Δ2. Προτεινόμενο όριο από καλλιέργεια και υφή εδάφους (FAO Table 19 και 22). Χρειάζεται νέο πεδίο υφής ανά φάρμα/ζώνη. Το εύρος του Table 19 κάνει την πρόταση χονδρική, οπότε πρέπει να φαίνεται ως εκτίμηση.
- Δ3. Εκμάθηση από τα δεδομένα (IrriMAX Adaptive Guide). Δεν χρειάζεται έδαφος, αλλά χρειάζεται συχνές μετρήσεις και καθαρό σήμα κατανάλωσης.
- Τρία σημεία που αφορούν οποιαδήποτε επιλογή:
  - Ο πίνακας `sensors` του Agronos δεν έχει βάθος, ενώ το FAO μιλά για ζώνη ριζών 1-2 m στα δέντρα, το AgriParcelRecord κρατά `depth`, και Semios/Arable ορίζουν ποια βάθη μετρούν. Ένας αισθητήρας σε ένα βάθος δεν αντιπροσωπεύει απαραίτητα τον μέσο όρο της ζώνης.
  - Ο αισθητήρας `moisture` του Agronos δίνει `%` ([SensorType.php](https://github.com/gkalogeitonas/agronos/blob/main/app/Enums/SensorType.php)). Αν αυτό είναι VWC ή μη βαθμονομημένο ποσοστό δεν το εξέτασα. Οι τύποι του FAO θέλουν VWC.
  - Το όριο μεταβάλλεται στη σεζόν (Semios, MAD σε στάδια). Το #7 ίσως θέλει ημερομηνία ισχύος, όχι μία τιμή.

**Ε. Χρήση του Kc**

Το Kc έχει αξία μόνο με ETo. Αυτό χρειάζεται άνεμο και ακτινοβολία, που δεν είναι στους τύπους αισθητήρων που δόθηκαν (το «φως» δεν είναι απαραίτητα ακτινοβολία σε W/m²). Άρα είτε εξωτερική πηγή καιρού, είτε μένει εκτός αρχικού scope. Αποθήκευση Kc στο seed χωρίς χρήση είναι φθηνή, αλλά δεν αποδίδει κάτι άμεσα.

### Τι δεν επαληθεύτηκε, συγκεντρωτικά

- Ο πίνακας κωδικών καλλιεργειών/ποικιλιών ΕΑΕ, το μέγεθος και η άδειά του (opekepe.gr εκτός λειτουργίας στις 2026-11-07).
- Ανοιχτά ελληνικά δεδομένα LPIS.
- Άδειες ICC/WCA και επαναχρησιμοποίησης πινάκων FAO-56, καθώς και οι αριθμοί σελίδων του FAO-56.
- Αν το αρχείο EPPO περιέχει ελληνικά ονόματα (θέλει λογαριασμό).
- Σύνδεση probe με block στη Semios, χειρισμός σεζόν στο CropX, οντότητα καλλιέργειας στο Davis Mobilize, «Work and Crop Plans» της Deere.
- Κείμενο BBCH ελιάς (Sanz-Cortés 2002) και κύριες φάσεις BBCH (από μνήμη).
- Εδαφικές πηγές για την Ελλάδα δεν ερευνήθηκαν.

