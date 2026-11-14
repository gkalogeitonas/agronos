<?php

namespace Database\Seeders;

use App\Models\Crop;
use Illuminate\Database\Seeder;

/**
 * Loads the shared crop catalogue (ADR-0010). Keyed by name, so running it again leaves no duplicates.
 */
class CropCatalogueSeeder extends Seeder
{
    /**
     * Catalogue crops by name, with an EPPO code only where it is checked (docs/research/crop-modelling.md).
     *
     * @var array<string, string|null>
     */
    public const CROPS = [
        'Olive' => 'OLVEU',
        'Wine grape' => 'VITVI',
        'Table grape' => 'VITVI',
        'Tomato' => 'LYPES',
        'Orange' => 'CIDSI',
        'Cotton' => 'GOSHI',
        'Wheat' => null,
        'Barley' => null,
        'Maize' => null,
        'Potato' => null,
        'Cucumber' => null,
        'Pepper' => null,
        'Eggplant' => null,
        'Zucchini' => null,
        'Onion' => null,
        'Garlic' => null,
        'Lettuce' => null,
        'Cabbage' => null,
        'Carrot' => null,
        'Beans' => null,
        'Watermelon' => null,
        'Melon' => null,
        'Strawberry' => null,
        'Lemon' => null,
        'Mandarin' => null,
        'Peach' => null,
        'Apricot' => null,
        'Cherry' => null,
        'Apple' => null,
        'Pear' => null,
        'Fig' => null,
        'Pomegranate' => null,
        'Almond' => null,
        'Walnut' => null,
        'Pistachio' => null,
        'Alfalfa' => null,
        'Sunflower' => null,
        'Fallow' => null,
        'Vegetable garden' => null,
        'Mixed orchard' => null,
    ];

    public function run(): void
    {
        foreach (self::CROPS as $name => $eppoCode) {
            Crop::query()->withoutGlobalScopes()->updateOrCreate(
                ['user_id' => null, 'name' => $name],
                ['eppo_code' => $eppoCode],
            );
        }
    }
}
