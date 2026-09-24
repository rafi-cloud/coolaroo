<?php

namespace Database\Seeders;

use App\Enums\Destination;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundMethod;
use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Enums\VisitCloseReason;
use App\Models\AddOnGroup;
use App\Models\AddOnOption;
use App\Models\Allergen;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\DietaryTag;
use App\Models\Feedback;
use App\Models\HistoricalDataManagement;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\SlotCapacity;
use App\Models\Staff;
use App\Models\Visit;
use App\Services\EtaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DemoSeeder extends Seeder
{
    private const HISTORY_DAYS = 56;

    private const UPCOMING_DAYS = 14;

    /**
     * @var array<string, MenuItem>
     */
    private array $items = [];

    public function run(): void
    {
        if (Role::count() === 0) {
            $this->call(DatabaseSeeder::FOUNDATION);
        }

        $admin = Staff::whereHas('role', fn ($q) => $q->where('role_name', 'admin'))->firstOrFail();
        $waiter = Staff::whereHas('role', fn ($q) => $q->where('role_name', 'waitstaff'))->firstOrFail();
        $kitchen = Staff::whereHas('role', fn ($q) => $q->where('role_name', 'kitchen'))->firstOrFail();

        $allergens = $this->seedAllergens();
        $tags = $this->seedDietaryTags();
        $categories = $this->seedCategories();
        $this->items = $this->seedMenuItems($categories, $allergens, $tags);
        $customers = $this->seedCustomers();

        RestaurantTable::query()->update([
            'status' => TableStatus::Available->value,
            'status_changed_at' => null,
        ]);

        $tables = RestaurantTable::where('is_active', true)->orderBy('table_id')->get()->keyBy('table_number');
        $slots = SlotCapacity::orderBy('slot_time')->get()->keyBy(fn ($s) => substr((string) $s->slot_time, 0, 5));

        $this->seedReservationsAndVisits($customers, $tables, $slots, $admin, $waiter);
        $this->seedOrdersAndPayments($customers, $tables, $waiter, $kitchen);
        $this->syncSoldToday();
        $this->seedFeedback($admin);
        $this->seedAuditLogsAndArchives($admin, $waiter);
    }

    /**
     * @return array<string, Allergen>
     */
    private function seedAllergens(): array
    {
        $data = [
            'Gluten' => 'Contains wheat, rye, barley or oats',
            'Crustaceans' => 'Crabs, prawns, lobsters and crayfish',
            'Eggs' => 'Egg and egg products',
            'Fish' => 'Fish and fish products',
            'Peanuts' => 'Peanuts and peanut products',
            'Soybeans' => 'Soybeans and soy products',
            'Milk' => 'Milk and dairy products including lactose',
            'Tree Nuts' => 'Almonds, hazelnuts, walnuts, cashews, pecans, brazil nuts, pistachios',
            'Celery' => 'Celery and celeriac',
            'Mustard' => 'Mustard seeds and mustard products',
            'Sesame Seeds' => 'Sesame seeds and sesame oil',
            'Sulphur Dioxide' => 'Sulphites preservatives above 10mg/kg',
            'Lupin' => 'Lupin flour and lupin seeds',
            'Molluscs' => 'Mussels, oysters, squid, octopus and snails',
        ];

        $allergens = [];
        foreach ($data as $name => $desc) {
            $allergens[$name] = Allergen::updateOrCreate(
                ['allergen_name' => $name],
                ['description' => $desc, 'is_active' => true]
            );
        }

        return $allergens;
    }

    /**
     * @return array<string, DietaryTag>
     */
    private function seedDietaryTags(): array
    {
        $data = [
            'Vegetarian' => 'No meat or poultry; may contain dairy or eggs',
            'Vegan' => '100% plant-based with no animal products',
            'Gluten-Free' => 'Certified or prepared without gluten ingredients',
            'Dairy-Free' => 'Prepared without milk or dairy derivatives',
            'Nut-Free' => 'Contains no peanuts or tree nuts',
            'Halal' => 'Prepared in accordance with Islamic dietary laws',
        ];

        $tags = [];
        foreach ($data as $name => $desc) {
            $tags[$name] = DietaryTag::updateOrCreate(
                ['tag_name' => $name],
                ['description' => $desc, 'is_active' => true]
            );
        }

        return $tags;
    }

    /**
     * @return array<string, MenuCategory>
     */
    private function seedCategories(): array
    {
        $names = [
            'Starters & Breads',
            'Mains & Classics',
            'Steaks & Grills',
            'Woodfired Pizzas',
            'Burgers & Pub Fare',
            'Pastas & Bowls',
            'Desserts',
            'Beers & Ciders',
            'Wines & Cocktails',
            'Non-Alcoholic Drinks',
        ];

        $categories = [];
        foreach ($names as $order => $name) {
            $categories[$name] = MenuCategory::updateOrCreate(
                ['category_name' => $name],
                ['display_order' => $order + 1, 'is_active' => true]
            );
        }

        return $categories;
    }

    /**
     * @param  array<string, MenuCategory>  $categories
     * @param  array<string, Allergen>  $allergens
     * @param  array<string, DietaryTag>  $tags
     * @return array<string, MenuItem>
     */
    private function seedMenuItems(array $categories, array $allergens, array $tags): array
    {
        $items = [];

        foreach ($this->menuDefinitions() as $def) {
            $item = MenuItem::updateOrCreate(
                ['item_name' => $def['name']],
                [
                    'category_id' => $categories[$def['category']]->category_id,
                    'description' => $def['description'],
                    'destination' => $def['destination'],
                    'prep_minutes' => $def['prep'],
                    'is_available' => $def['available'] ?? true,
                    'is_featured' => $def['featured'] ?? false,
                    'daily_limit' => $def['limit'] ?? null,
                    'is_active' => true,
                    'calories_kcal' => $def['nutrition'][0] ?? null,
                    'protein_g' => $def['nutrition'][1] ?? null,
                    'carbohydrates_g' => $def['nutrition'][2] ?? null,
                    'fat_g' => $def['nutrition'][3] ?? null,
                ]
            );

            foreach (array_values($def['sizes']) as $order => $size) {
                $onSale = isset($size[2]);

                MenuItemSize::updateOrCreate(
                    ['item_id' => $item->item_id, 'size_name' => $size[0]],
                    [
                        'price' => $size[1],
                        'sale_price' => $size[2] ?? null,
                        'sale_starts_at' => $onSale ? Carbon::today()->subDays(2) : null,
                        'sale_ends_at' => $onSale ? Carbon::today()->addDays(5)->endOfDay() : null,
                        'display_order' => $order + 1,
                        'is_active' => true,
                    ]
                );
            }

            foreach (array_values($def['addons'] ?? []) as $groupOrder => $group) {
                $addOnGroup = AddOnGroup::updateOrCreate(
                    ['item_id' => $item->item_id, 'group_name' => $group['name']],
                    [
                        'is_required' => $group['required'] ?? false,
                        'min_select' => $group['min'] ?? 0,
                        'max_select' => $group['max'] ?? 1,
                        'display_order' => $groupOrder + 1,
                    ]
                );

                foreach (array_values($group['options']) as $optionOrder => $option) {
                    AddOnOption::updateOrCreate(
                        ['group_id' => $addOnGroup->group_id, 'option_name' => $option[0]],
                        [
                            'price_delta' => $option[1],
                            'display_order' => $optionOrder + 1,
                            'is_available' => true,
                            'is_active' => true,
                        ]
                    );
                }
            }

            $item->allergens()->sync(
                array_map(fn (string $name) => $allergens[$name]->allergen_id, $def['allergens'] ?? [])
            );
            $item->dietaryTags()->sync(
                array_map(fn (string $name) => $tags[$name]->dietary_tag_id, $def['tags'] ?? [])
            );

            $items[$def['key']] = $item;
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function menuDefinitions(): array
    {
        $kitchen = Destination::Kitchen;
        $bar = Destination::Bar;

        return [
            [
                'key' => 'bread',
                'category' => 'Starters & Breads',
                'name' => 'Warm Garlic & Herb Pull-Apart',
                'description' => 'Toasted sourdough cob stuffed with whipped roasted garlic butter, parsley and melted mozzarella.',
                'destination' => $kitchen, 'prep' => 6, 'nutrition' => [520, 12, 58, 24],
                'allergens' => ['Gluten', 'Milk'], 'tags' => ['Vegetarian'],
                'sizes' => [['Regular Loaf', 11.00]],
            ],
            [
                'key' => 'calamari',
                'category' => 'Starters & Breads',
                'name' => 'Salt & Pepper Calamari',
                'description' => 'Tender flash-fried calamari dusted in Sichuan pepper and sea salt, served with fresh lime aioli and pickled chilli salad.',
                'destination' => $kitchen, 'prep' => 8, 'featured' => true, 'limit' => 40,
                'nutrition' => [450, 28, 24, 22],
                'allergens' => ['Molluscs', 'Eggs'], 'tags' => ['Dairy-Free'],
                'sizes' => [['Entree Plate', 18.50], ['Share Platter', 26.00]],
            ],
            [
                'key' => 'wings',
                'category' => 'Starters & Breads',
                'name' => 'Buffalo Chicken Wings',
                'description' => 'Twice-cooked free-range wings tossed in Louisiana hot sauce with celery sticks and blue cheese dip.',
                'destination' => $kitchen, 'prep' => 12, 'limit' => 35,
                'nutrition' => [610, 38, 18, 42],
                'allergens' => ['Milk', 'Celery'],
                'sizes' => [['Half Dozen', 16.50], ['Dozen', 28.00]],
                'addons' => [[
                    'name' => 'Heat Level', 'required' => true, 'min' => 1, 'max' => 1,
                    'options' => [['Mild', 0], ['Medium', 0], ['Hot', 0], ['Ghost Pepper', 1.50]],
                ]],
            ],
            [
                'key' => 'cauliflower',
                'category' => 'Starters & Breads',
                'name' => 'Crispy Cauliflower Bites',
                'description' => 'Rice-flour battered cauliflower florets with sticky sesame glaze, spring onion and toasted sesame seeds.',
                'destination' => $kitchen, 'prep' => 9, 'nutrition' => [380, 9, 42, 18],
                'allergens' => ['Sesame Seeds', 'Soybeans'], 'tags' => ['Vegan', 'Vegetarian', 'Dairy-Free'],
                'sizes' => [['Entree Bowl', 14.50]],
            ],
            [
                'key' => 'haloumi',
                'category' => 'Starters & Breads',
                'name' => 'Grilled Haloumi & Honey',
                'description' => 'Chargrilled Cypriot haloumi with warm spiced honey, lemon and cracked pistachio.',
                'destination' => $kitchen, 'prep' => 7, 'nutrition' => [420, 24, 16, 30],
                'allergens' => ['Milk', 'Tree Nuts'], 'tags' => ['Vegetarian', 'Gluten-Free'],
                'sizes' => [['Entree Plate', 15.50]],
            ],

            [
                'key' => 'parma',
                'category' => 'Mains & Classics',
                'name' => 'Classic Chicken Parmigiana',
                'description' => 'Crisp panko-crumbed chicken breast topped with smoked leg ham, rich house napoli sauce and bubbling mozzarella cheese. Served with pub chips and garden salad.',
                'destination' => $kitchen, 'prep' => 15, 'featured' => true, 'limit' => 60,
                'nutrition' => [980, 62, 54, 48],
                'allergens' => ['Gluten', 'Eggs', 'Milk'],
                'sizes' => [['Regular', 28.50], ['Parmageddon (Double)', 36.00]],
                'addons' => [[
                    'name' => 'Choice of Sauce', 'min' => 0, 'max' => 1,
                    'options' => [['House Gravy', 0], ['Creamy Mushroom', 3.00], ['Green Peppercorn', 2.50]],
                ]],
            ],
            [
                'key' => 'barramundi',
                'category' => 'Mains & Classics',
                'name' => 'Pan-Seared Humpty Doo Barramundi',
                'description' => 'Crispy skin wild barramundi on saffron mash with roasted asparagus and lemon caper beurre blanc.',
                'destination' => $kitchen, 'prep' => 16, 'limit' => 15,
                'nutrition' => [580, 52, 20, 30],
                'allergens' => ['Fish', 'Milk'], 'tags' => ['Gluten-Free'],
                'sizes' => [['Regular', 34.00]],
            ],
            [
                'key' => 'schnitzel',
                'category' => 'Mains & Classics',
                'name' => 'Golden Chicken Schnitzel',
                'description' => 'Hand-crumbed chicken breast fried golden, with pub chips, garden salad and a lemon cheek.',
                'destination' => $kitchen, 'prep' => 13, 'limit' => 50,
                'nutrition' => [860, 55, 62, 38],
                'allergens' => ['Gluten', 'Eggs'],
                'sizes' => [['Regular', 24.50], ['Large', 30.00]],
            ],
            [
                'key' => 'bangers',
                'category' => 'Mains & Classics',
                'name' => 'Bangers & Buttered Mash',
                'description' => 'Three thick pork and fennel sausages on chive mash with caramelised onion gravy and minted peas.',
                'destination' => $kitchen, 'prep' => 14, 'nutrition' => [790, 34, 58, 44],
                'allergens' => ['Gluten', 'Milk', 'Sulphur Dioxide'],
                'sizes' => [['Regular', 26.00]],
            ],
            [
                'key' => 'lambShank',
                'category' => 'Mains & Classics',
                'name' => 'Slow-Braised Lamb Shank',
                'description' => 'Eight-hour red wine and rosemary braised lamb shank on parsnip puree with glazed heirloom carrots.',
                'destination' => $kitchen, 'prep' => 18, 'featured' => true, 'limit' => 18,
                'nutrition' => [740, 58, 26, 40],
                'allergens' => ['Milk', 'Celery', 'Sulphur Dioxide'], 'tags' => ['Gluten-Free'],
                'sizes' => [['Single Shank', 36.00]],
            ],
            [
                'key' => 'flathead',
                'category' => 'Mains & Classics',
                'name' => 'Beer-Battered Flathead & Chips',
                'description' => 'Southern flathead tails in a crisp lager batter with thick-cut chips, slaw and house tartare.',
                'destination' => $kitchen, 'prep' => 14, 'nutrition' => [880, 42, 74, 40],
                'allergens' => ['Fish', 'Gluten', 'Eggs'],
                'sizes' => [['Regular', 27.50]],
            ],

            [
                'key' => 'steak',
                'category' => 'Steaks & Grills',
                'name' => 'Angus Ribeye 300g',
                'description' => 'Grain-fed Black Angus ribeye chargrilled to perfection, served with roasted chat potatoes, buttered broccolini and rosemary jus.',
                'destination' => $kitchen, 'prep' => 20, 'featured' => true, 'limit' => 30,
                'nutrition' => [850, 74, 18, 52],
                'allergens' => ['Milk'], 'tags' => ['Gluten-Free'],
                'sizes' => [['300g Cut', 44.00, 38.00], ['500g King Cut', 58.00]],
            ],
            [
                'key' => 'ribs',
                'category' => 'Steaks & Grills',
                'name' => 'Slow-Smoked BBQ Pork Ribs',
                'description' => 'Smoked full rack of pork ribs glazed in Kentucky bourbon barbecue glaze, with apple slaw and onion rings.',
                'destination' => $kitchen, 'prep' => 15, 'available' => false, 'limit' => 20,
                'nutrition' => [1150, 65, 45, 68],
                'allergens' => ['Soybeans', 'Mustard'],
                'sizes' => [['Full Rack', 39.50]],
            ],
            [
                'key' => 'porterhouse',
                'category' => 'Steaks & Grills',
                'name' => 'Grass-Fed Porterhouse 250g',
                'description' => 'Gippsland grass-fed porterhouse off the chargrill with confit garlic butter, chips and salad.',
                'destination' => $kitchen, 'prep' => 18, 'limit' => 25,
                'nutrition' => [720, 62, 34, 38],
                'allergens' => ['Milk'],
                'sizes' => [['250g Cut', 38.00]],
                'addons' => [[
                    'name' => 'Cooked To', 'required' => true, 'min' => 1, 'max' => 1,
                    'options' => [['Rare', 0], ['Medium Rare', 0], ['Medium', 0], ['Well Done', 0]],
                ]],
            ],
            [
                'key' => 'skewers',
                'category' => 'Steaks & Grills',
                'name' => 'Lemon & Oregano Chicken Skewers',
                'description' => 'Charred marinated chicken thigh skewers with lemon-oregano dressing, warm pita and tzatziki.',
                'destination' => $kitchen, 'prep' => 15, 'nutrition' => [640, 48, 38, 28],
                'allergens' => ['Gluten', 'Milk'],
                'sizes' => [['Two Skewers', 29.00]],
            ],

            [
                'key' => 'pizza',
                'category' => 'Woodfired Pizzas',
                'name' => 'Woodfired Margherita Pizza',
                'description' => 'San Marzano tomato base, fresh buffalo mozzarella, fragrant basil leaves and cold-pressed extra virgin olive oil on 48-hour fermented dough.',
                'destination' => $kitchen, 'prep' => 10, 'featured' => true, 'limit' => 45,
                'nutrition' => [780, 32, 86, 28],
                'allergens' => ['Gluten', 'Milk'], 'tags' => ['Vegetarian'],
                'sizes' => [['11-Inch Individual', 22.00], ['14-Inch Share', 28.50]],
            ],
            [
                'key' => 'pepperoni',
                'category' => 'Woodfired Pizzas',
                'name' => 'Pepperoni & Hot Honey Pizza',
                'description' => 'Double pepperoni, mozzarella and oregano finished with a drizzle of chilli-infused hot honey.',
                'destination' => $kitchen, 'prep' => 11, 'limit' => 40,
                'nutrition' => [910, 38, 88, 42],
                'allergens' => ['Gluten', 'Milk'],
                'sizes' => [['11-Inch Individual', 25.00], ['14-Inch Share', 31.50]],
            ],
            [
                'key' => 'meatLovers',
                'category' => 'Woodfired Pizzas',
                'name' => 'BBQ Meat Lovers Pizza',
                'description' => 'Smoked brisket, pepperoni, leg ham and chorizo on a bourbon barbecue base with red onion.',
                'destination' => $kitchen, 'prep' => 12, 'limit' => 35,
                'nutrition' => [1040, 48, 92, 50],
                'allergens' => ['Gluten', 'Milk', 'Mustard'],
                'sizes' => [['11-Inch Individual', 27.00], ['14-Inch Share', 34.00]],
            ],
            [
                'key' => 'prawnPizza',
                'category' => 'Woodfired Pizzas',
                'name' => 'Garlic Prawn & Chilli Pizza',
                'description' => 'Australian prawns, roasted garlic cream, scamorza, long red chilli and rocket.',
                'destination' => $kitchen, 'prep' => 12, 'nutrition' => [860, 42, 80, 34],
                'allergens' => ['Gluten', 'Milk', 'Crustaceans'],
                'sizes' => [['11-Inch Individual', 28.00], ['14-Inch Share', 35.00]],
            ],
            [
                'key' => 'pumpkinPizza',
                'category' => 'Woodfired Pizzas',
                'name' => 'Roast Pumpkin & Fetta Pizza',
                'description' => 'Roast kent pumpkin, Danish fetta, caramelised onion, baby spinach and toasted pepita.',
                'destination' => $kitchen, 'prep' => 11, 'nutrition' => [740, 26, 84, 30],
                'allergens' => ['Gluten', 'Milk'], 'tags' => ['Vegetarian'],
                'sizes' => [['11-Inch Individual', 24.00], ['14-Inch Share', 30.00]],
            ],

            [
                'key' => 'burger',
                'category' => 'Burgers & Pub Fare',
                'name' => 'Coolaroo Wagyu Cheeseburger',
                'description' => 'Grilled 200g Wagyu beef patty, double American cheddar, bread & butter pickles, crisp cos lettuce, and secret burger sauce in a toasted milk bun with beer-battered fries.',
                'destination' => $kitchen, 'prep' => 12, 'featured' => true, 'limit' => 50,
                'nutrition' => [920, 48, 64, 46],
                'allergens' => ['Gluten', 'Milk', 'Sesame Seeds'],
                'sizes' => [['Regular Single', 24.50], ['Double Beast', 30.50]],
                'addons' => [[
                    'name' => 'Burger Additions', 'min' => 0, 'max' => 3,
                    'options' => [
                        ['Smoked Streaky Bacon', 3.50],
                        ['Fried Free-Range Egg', 2.50],
                        ['Pickled Jalapenos', 1.50],
                        ['Gluten-Free Bun Substitute', 3.00],
                    ],
                ]],
            ],
            [
                'key' => 'chickenBurger',
                'category' => 'Burgers & Pub Fare',
                'name' => 'Southern Fried Chicken Burger',
                'description' => 'Buttermilk-brined chicken thigh, slaw, dill pickle and chipotle mayo in a brioche bun with fries.',
                'destination' => $kitchen, 'prep' => 12, 'limit' => 40,
                'nutrition' => [880, 44, 72, 42],
                'allergens' => ['Gluten', 'Milk', 'Eggs', 'Mustard'],
                'sizes' => [['Regular', 23.50], ['Double Stack', 29.50]],
            ],
            [
                'key' => 'plantBurger',
                'category' => 'Burgers & Pub Fare',
                'name' => 'Smoky Plant-Based Burger',
                'description' => 'Charred pea-protein patty, vegan cheddar, smoky tomato relish and slaw in a vegan sesame bun.',
                'destination' => $kitchen, 'prep' => 11, 'nutrition' => [700, 30, 68, 32],
                'allergens' => ['Gluten', 'Soybeans', 'Sesame Seeds'], 'tags' => ['Vegan', 'Vegetarian', 'Dairy-Free'],
                'sizes' => [['Regular', 22.50]],
            ],
            [
                'key' => 'loadedChips',
                'category' => 'Burgers & Pub Fare',
                'name' => 'Loaded Pub Chips',
                'description' => 'Thick-cut chips smothered in cheese sauce, bacon, spring onion and chipotle mayo.',
                'destination' => $kitchen, 'prep' => 9, 'nutrition' => [820, 22, 78, 46],
                'allergens' => ['Gluten', 'Milk', 'Eggs'],
                'sizes' => [['Regular', 12.50], ['Share', 18.00]],
            ],
            [
                'key' => 'caesarWrap',
                'category' => 'Burgers & Pub Fare',
                'name' => 'Chicken Caesar Wrap',
                'description' => 'Grilled chicken, cos, bacon, parmesan and caesar dressing rolled in a warm tortilla, with chips.',
                'destination' => $kitchen, 'prep' => 10, 'nutrition' => [690, 40, 58, 30],
                'allergens' => ['Gluten', 'Milk', 'Eggs', 'Fish'],
                'sizes' => [['Regular', 19.50]],
            ],

            [
                'key' => 'risotto',
                'category' => 'Pastas & Bowls',
                'name' => 'Wild Forest Truffle Risotto',
                'description' => 'Carnaroli rice cooked with porcini and Swiss brown mushrooms, white truffle oil, baby spinach, toasted pine nuts and aged shaved parmesan.',
                'destination' => $kitchen, 'prep' => 14, 'limit' => 30,
                'nutrition' => [640, 18, 72, 28],
                'allergens' => ['Milk', 'Tree Nuts'], 'tags' => ['Vegetarian', 'Gluten-Free'],
                'sizes' => [['Regular', 29.00]],
            ],
            [
                'key' => 'bolognese',
                'category' => 'Pastas & Bowls',
                'name' => 'Rigatoni Bolognese',
                'description' => 'Four-hour beef and pork ragu with rigatoni, torn basil and a heavy hand of grana padano.',
                'destination' => $kitchen, 'prep' => 12, 'limit' => 45,
                'nutrition' => [810, 42, 96, 26],
                'allergens' => ['Gluten', 'Milk', 'Celery'],
                'sizes' => [['Regular', 24.00], ['Large', 29.50]],
            ],
            [
                'key' => 'linguine',
                'category' => 'Pastas & Bowls',
                'name' => 'Prawn & Chilli Linguine',
                'description' => 'Australian prawns tossed through linguine with garlic, long red chilli, cherry tomato and lemon.',
                'destination' => $kitchen, 'prep' => 14, 'nutrition' => [720, 38, 84, 22],
                'allergens' => ['Gluten', 'Crustaceans'], 'tags' => ['Dairy-Free'],
                'sizes' => [['Regular', 31.00]],
            ],
            [
                'key' => 'gnocchi',
                'category' => 'Pastas & Bowls',
                'name' => 'Pumpkin & Sage Gnocchi',
                'description' => 'Pillowy potato gnocchi in brown butter and sage with roast pumpkin, walnut and pecorino.',
                'destination' => $kitchen, 'prep' => 13, 'nutrition' => [680, 20, 78, 30],
                'allergens' => ['Gluten', 'Milk', 'Eggs', 'Tree Nuts'], 'tags' => ['Vegetarian'],
                'sizes' => [['Regular', 26.00]],
            ],
            [
                'key' => 'pokeBowl',
                'category' => 'Pastas & Bowls',
                'name' => 'Teriyaki Salmon Poke Bowl',
                'description' => 'Seared Tasmanian salmon, sushi rice, edamame, avocado, pickled ginger and sesame teriyaki.',
                'destination' => $kitchen, 'prep' => 12, 'limit' => 20,
                'nutrition' => [660, 40, 70, 24],
                'allergens' => ['Fish', 'Soybeans', 'Sesame Seeds'], 'tags' => ['Dairy-Free'],
                'sizes' => [['Regular', 27.50]],
            ],

            [
                'key' => 'stickyDate',
                'category' => 'Desserts',
                'name' => 'Warm Sticky Date Pudding',
                'description' => 'Traditional warm sponge pudding drizzled with rich butterscotch sauce and served with vanilla bean ice cream and almond praline.',
                'destination' => $kitchen, 'prep' => 6, 'featured' => true, 'limit' => 25,
                'nutrition' => [620, 8, 84, 26],
                'allergens' => ['Gluten', 'Milk', 'Eggs', 'Tree Nuts'], 'tags' => ['Vegetarian'],
                'sizes' => [['Standard Serve', 14.50]],
            ],
            [
                'key' => 'brownie',
                'category' => 'Desserts',
                'name' => 'Belgian Chocolate Brownie',
                'description' => 'Dense couverture brownie, salted caramel, honeycomb shards and double cream.',
                'destination' => $kitchen, 'prep' => 5, 'nutrition' => [680, 9, 76, 36],
                'allergens' => ['Gluten', 'Milk', 'Eggs'], 'tags' => ['Vegetarian'],
                'sizes' => [['Standard Serve', 13.50]],
            ],
            [
                'key' => 'lemonTart',
                'category' => 'Desserts',
                'name' => 'Lemon Meringue Tart',
                'description' => 'Sharp lemon curd in a sweet pastry shell under torched Italian meringue, with raspberry coulis.',
                'destination' => $kitchen, 'prep' => 5, 'nutrition' => [540, 7, 68, 26],
                'allergens' => ['Gluten', 'Milk', 'Eggs'], 'tags' => ['Vegetarian'],
                'sizes' => [['Standard Serve', 13.00]],
            ],
            [
                'key' => 'gelato',
                'category' => 'Desserts',
                'name' => 'Gelato Trio',
                'description' => 'Three scoops from the daily gelato cabinet with a wafer and chocolate sauce.',
                'destination' => $kitchen, 'prep' => 4, 'nutrition' => [420, 8, 52, 20],
                'allergens' => ['Milk', 'Eggs'], 'tags' => ['Vegetarian', 'Gluten-Free'],
                'sizes' => [['Three Scoops', 11.00]],
            ],

            [
                'key' => 'beer',
                'category' => 'Beers & Ciders',
                'name' => 'Carlton Draught Fresh Tank Beer',
                'description' => 'Unpasteurised brewery-fresh tank beer poured cold from the tap. Clean, crisp and refreshing.',
                'destination' => $bar, 'prep' => 3,
                'allergens' => ['Gluten'],
                'sizes' => [['Schooner 425ml', 9.50], ['Pint 570ml', 12.50], ['Jug 1140ml', 24.00]],
            ],
            [
                'key' => 'xpa',
                'category' => 'Beers & Ciders',
                'name' => 'Balter XPA',
                'description' => 'Gold Coast extra pale ale with tropical stone fruit hops and a soft bitter finish.',
                'destination' => $bar, 'prep' => 3,
                'allergens' => ['Gluten'],
                'sizes' => [['Schooner 425ml', 10.50], ['Pint 570ml', 13.50], ['Jug 1140ml', 26.00]],
            ],
            [
                'key' => 'paleAle',
                'category' => 'Beers & Ciders',
                'name' => 'Coopers Pale Ale 375ml',
                'description' => 'The cloudy South Australian classic, bottle conditioned and lightly fruity.',
                'destination' => $bar, 'prep' => 2,
                'allergens' => ['Gluten'],
                'sizes' => [['Bottle 375ml', 9.50]],
            ],
            [
                'key' => 'cider',
                'category' => 'Beers & Ciders',
                'name' => 'Somersby Apple Cider',
                'description' => 'Crisp and lightly sweet apple cider served over ice.',
                'destination' => $bar, 'prep' => 2,
                'allergens' => ['Sulphur Dioxide'], 'tags' => ['Gluten-Free', 'Vegan'],
                'sizes' => [['Bottle 330ml', 10.00]],
            ],
            [
                'key' => 'zeroBeer',
                'category' => 'Beers & Ciders',
                'name' => 'Great Northern Zero',
                'description' => 'Alcohol-free lager, served cold. All of the afternoon, none of the afternoon nap.',
                'destination' => $bar, 'prep' => 2,
                'allergens' => ['Gluten'],
                'sizes' => [['Bottle 330ml', 8.00]],
            ],

            [
                'key' => 'wine',
                'category' => 'Wines & Cocktails',
                'name' => 'Barossa Valley Shiraz 2022',
                'description' => 'Full-bodied South Australian Shiraz brimming with ripe plum, dark berries, vanillin oak and subtle pepper spice.',
                'destination' => $bar, 'prep' => 3,
                'allergens' => ['Sulphur Dioxide'], 'tags' => ['Vegan'],
                'sizes' => [['Glass 150ml', 13.00], ['Glass 250ml', 19.50], ['Bottle 750ml', 54.00]],
            ],
            [
                'key' => 'sauvBlanc',
                'category' => 'Wines & Cocktails',
                'name' => 'Marlborough Sauvignon Blanc 2024',
                'description' => 'Zesty New Zealand white with passionfruit, cut grass and a bright citrus finish.',
                'destination' => $bar, 'prep' => 3,
                'allergens' => ['Sulphur Dioxide'], 'tags' => ['Vegan'],
                'sizes' => [['Glass 150ml', 12.00], ['Glass 250ml', 18.00], ['Bottle 750ml', 50.00]],
            ],
            [
                'key' => 'prosecco',
                'category' => 'Wines & Cocktails',
                'name' => 'King Valley Prosecco NV',
                'description' => 'Dry Victorian sparkling with green apple, pear and a fine persistent bead.',
                'destination' => $bar, 'prep' => 3,
                'allergens' => ['Sulphur Dioxide'], 'tags' => ['Vegan'],
                'sizes' => [['Glass 150ml', 13.00], ['Bottle 750ml', 52.00]],
            ],
            [
                'key' => 'espressoMartini',
                'category' => 'Wines & Cocktails',
                'name' => 'Espresso Martini',
                'description' => 'Double shot of house espresso shaken hard with vodka and coffee liqueur.',
                'destination' => $bar, 'prep' => 5,
                'sizes' => [['Standard', 20.00]],
            ],
            [
                'key' => 'spritz',
                'category' => 'Wines & Cocktails',
                'name' => 'Coolaroo Sunset Spritz',
                'description' => 'Aperol, prosecco and soda over ice with a blood orange wheel.',
                'destination' => $bar, 'prep' => 4,
                'allergens' => ['Sulphur Dioxide'], 'tags' => ['Vegan'],
                'sizes' => [['Standard', 19.00]],
            ],

            [
                'key' => 'coldBrew',
                'category' => 'Non-Alcoholic Drinks',
                'name' => 'Coolaroo Cold Brew',
                'description' => 'Eighteen-hour steeped single origin cold brew over ice, with or without milk.',
                'destination' => $bar, 'prep' => 3,
                'tags' => ['Vegan', 'Gluten-Free'],
                'sizes' => [['Regular', 6.50], ['Large', 8.00]],
            ],
            [
                'key' => 'flatWhite',
                'category' => 'Non-Alcoholic Drinks',
                'name' => 'Flat White',
                'description' => 'House espresso blend with silky steamed milk. Oat, soy and almond available.',
                'destination' => $bar, 'prep' => 4,
                'allergens' => ['Milk'], 'tags' => ['Vegetarian', 'Gluten-Free'],
                'sizes' => [['Regular', 5.00], ['Large', 5.80]],
                'addons' => [[
                    'name' => 'Milk Choice', 'min' => 0, 'max' => 1,
                    'options' => [['Full Cream', 0], ['Skim', 0], ['Oat', 0.80], ['Soy', 0.80], ['Almond', 0.80]],
                ]],
            ],
            [
                'key' => 'juice',
                'category' => 'Non-Alcoholic Drinks',
                'name' => 'Fresh Orange & Ginger Juice',
                'description' => 'Pressed to order from Sunraysia navels with a knob of fresh ginger.',
                'destination' => $bar, 'prep' => 4,
                'tags' => ['Vegan', 'Gluten-Free', 'Dairy-Free'],
                'sizes' => [['Regular', 8.50]],
            ],
            [
                'key' => 'sparkling',
                'category' => 'Non-Alcoholic Drinks',
                'name' => 'Sparkling Mineral Water 500ml',
                'description' => 'Chilled Australian sparkling mineral water with lemon.',
                'destination' => $bar, 'prep' => 2,
                'tags' => ['Vegan', 'Gluten-Free', 'Dairy-Free'],
                'sizes' => [['Bottle 500ml', 5.50]],
            ],
            [
                'key' => 'llb',
                'category' => 'Non-Alcoholic Drinks',
                'name' => 'Lemon, Lime & Bitters',
                'description' => 'The pub standard: lemonade, lime cordial and a dash of aromatic bitters.',
                'destination' => $bar, 'prep' => 2,
                'tags' => ['Vegetarian'],
                'sizes' => [['Regular', 7.50]],
            ],
        ];
    }

    /**
     * @return array<string, Customer>
     */
    private function seedCustomers(): array
    {
        $profiles = [
            'cara' => ['name' => 'Cara Customer', 'email' => 'customer@coolaroo.test', 'phone' => '0400000000'],
            'jack' => ['name' => 'Jack Thompson', 'email' => 'jack.t@coolaroo.test', 'phone' => '0412345678'],
            'sarah' => ['name' => 'Sarah Jenkins', 'email' => 'sarah.j@coolaroo.test', 'phone' => '0423456789'],
            'david' => ['name' => 'David Miller', 'email' => 'david.m@coolaroo.test', 'phone' => '0434567890'],
            'emily' => ['name' => 'Emily Chen', 'email' => 'emily.c@coolaroo.test', 'phone' => '0445678901'],
            'liam' => ['name' => 'Liam Wilson', 'email' => 'liam.w@coolaroo.test', 'phone' => '0456789012'],
            'olivia' => ['name' => 'Olivia Martin', 'email' => 'olivia.m@coolaroo.test', 'phone' => '0467890123'],
            'noah' => ['name' => 'Noah Taylor', 'email' => 'noah.t@coolaroo.test', 'phone' => '0478901234'],
            'mia' => ['name' => 'Mia Anderson', 'email' => 'mia.a@coolaroo.test', 'phone' => '0489012345'],
            'james' => ['name' => 'James White', 'email' => 'james.w@coolaroo.test', 'phone' => '0490123456'],
            'ava' => ['name' => 'Ava Robinson', 'email' => 'ava.r@coolaroo.test', 'phone' => '0401234567'],
            'ethan' => ['name' => 'Ethan Brooks', 'email' => 'ethan.b@coolaroo.test', 'phone' => '0402345678'],
        ];

        $customers = [];
        foreach ($profiles as $key => $p) {
            $customers[$key] = Customer::updateOrCreate(
                ['email' => $p['email']],
                [
                    'full_name' => $p['name'],
                    'phone' => $p['phone'],
                    'password_hash' => StaffSeeder::PASSWORD,
                    'email_verified_at' => Carbon::now()->subMonths(2),
                ]
            );
        }

        return $customers;
    }

    private function referenceCode(string $key): string
    {
        return 'CR-'.strtoupper(substr(md5('coolaroo-demo-'.$key), 0, 6));
    }

    /**
     * @param  array<string, Customer>  $customers
     * @param  Collection<string, RestaurantTable>  $tables
     * @param  Collection<string, SlotCapacity>  $slots
     */
    private function seedReservationsAndVisits(
        array $customers,
        Collection $tables,
        Collection $slots,
        Staff $admin,
        Staff $waiter
    ): void {
        $slot1200 = $slots['12:00'];
        $slot1830 = $slots['18:30'];
        $slot1900 = $slots['19:00'];

        $regulars = [
            ['jack', 4, 4, $slot1830, 'T3'],
            ['sarah', 3, 2, $slot1900, 'T1'],
        ];

        foreach ($regulars as [$key, $count, $party, $slot, $tableNumber]) {
            for ($i = 1; $i <= $count; $i++) {
                $date = Carbon::today()->subDays($i * 6);
                $bookedAt = $date->copy()->setTimeFromTimeString((string) $slot->slot_time);

                $reservation = Reservation::updateOrCreate(
                    ['reference_code' => $this->referenceCode("past-{$key}-{$i}")],
                    [
                        'customer_id' => $customers[$key]->customer_id,
                        'slot_id' => $slot->slot_id,
                        'party_size' => $party,
                        'booking_date' => $date->toDateString(),
                        'booking_time' => $slot->slot_time,
                        'status' => ReservationStatus::Completed->value,
                        'reviewed_by_staff_id' => $admin->staff_id,
                        'reviewed_at' => $date->copy()->subDay(),
                        'seated_at' => $bookedAt->copy()->addMinutes(5),
                        'completed_at' => $bookedAt->copy()->addMinutes(100),
                        'created_at' => $date->copy()->subDays(2),
                    ]
                );

                Visit::updateOrCreate(
                    ['reservation_id' => $reservation->reservation_id],
                    [
                        'table_id' => $tables[$tableNumber]->table_id,
                        'guest_count' => $party,
                        'opened_at' => $bookedAt->copy()->addMinutes(5),
                        'closed_at' => $bookedAt->copy()->addMinutes(100),
                        'opened_by_staff_id' => $waiter->staff_id,
                        'closed_by_staff_id' => $waiter->staff_id,
                        'close_reason' => VisitCloseReason::StaffClear->value,
                    ]
                );
            }
        }

        $noShowDate = Carbon::today()->subDays(8);
        Reservation::updateOrCreate(
            ['reference_code' => $this->referenceCode('noshow-david')],
            [
                'customer_id' => $customers['david']->customer_id,
                'slot_id' => $slot1830->slot_id,
                'party_size' => 6,
                'booking_date' => $noShowDate->toDateString(),
                'booking_time' => $slot1830->slot_time,
                'status' => ReservationStatus::NoShow->value,
                'reviewed_by_staff_id' => $admin->staff_id,
                'reviewed_at' => $noShowDate->copy()->subDay(),
                'no_show_at' => $noShowDate->copy()->setTime(18, 50),
                'no_show_by_staff_id' => $waiter->staff_id,
                'created_at' => $noShowDate->copy()->subDays(3),
            ]
        );

        $clearedDate = Carbon::today()->subDays(15);
        Reservation::updateOrCreate(
            ['reference_code' => $this->referenceCode('noshow-cleared-emily')],
            [
                'customer_id' => $customers['emily']->customer_id,
                'slot_id' => $slot1900->slot_id,
                'party_size' => 4,
                'booking_date' => $clearedDate->toDateString(),
                'booking_time' => $slot1900->slot_time,
                'status' => ReservationStatus::NoShow->value,
                'reviewed_by_staff_id' => $admin->staff_id,
                'reviewed_at' => $clearedDate->copy()->subDay(),
                'no_show_at' => $clearedDate->copy()->setTime(19, 20),
                'no_show_by_staff_id' => $waiter->staff_id,
                'no_show_cleared_at' => $clearedDate->copy()->addDay(),
                'no_show_cleared_by_staff_id' => $admin->staff_id,
                'no_show_clear_reason' => 'Customer called to explain road closure emergency',
                'created_at' => $clearedDate->copy()->subDays(2),
            ]
        );

        $cancelDate = Carbon::today()->subDays(4);
        Reservation::updateOrCreate(
            ['reference_code' => $this->referenceCode('late-cancel-liam')],
            [
                'customer_id' => $customers['liam']->customer_id,
                'slot_id' => $slot1830->slot_id,
                'party_size' => 4,
                'booking_date' => $cancelDate->toDateString(),
                'booking_time' => $slot1830->slot_time,
                'status' => ReservationStatus::Cancelled->value,
                'cancelled_by' => 'customer',
                'cancelled_at' => $cancelDate->copy()->setTime(17, 10),
                'is_late_cancellation' => true,
                'created_at' => $cancelDate->copy()->subDay(),
            ]
        );

        $declinedDate = Carbon::today()->subDays(11);
        Reservation::updateOrCreate(
            ['reference_code' => $this->referenceCode('declined-james')],
            [
                'customer_id' => $customers['james']->customer_id,
                'slot_id' => $slot1900->slot_id,
                'party_size' => 9,
                'booking_date' => $declinedDate->toDateString(),
                'booking_time' => $slot1900->slot_time,
                'status' => ReservationStatus::Declined->value,
                'reviewed_by_staff_id' => $admin->staff_id,
                'reviewed_at' => $declinedDate->copy()->subDays(2),
                'decline_reason' => 'No table configuration seats nine at that hour',
                'created_at' => $declinedDate->copy()->subDays(3),
            ]
        );

        $seated = Reservation::updateOrCreate(
            ['reference_code' => $this->referenceCode('today-seated')],
            [
                'customer_id' => $customers['olivia']->customer_id,
                'slot_id' => $slot1200->slot_id,
                'party_size' => 4,
                'booking_date' => Carbon::today()->toDateString(),
                'booking_time' => $slot1200->slot_time,
                'status' => ReservationStatus::Seated->value,
                'reviewed_by_staff_id' => $admin->staff_id,
                'reviewed_at' => Carbon::today()->subDay(),
                'seated_at' => Carbon::now()->subMinutes(35),
                'created_at' => Carbon::today()->subDays(2),
            ]
        );

        $tables['T4']->update([
            'status' => TableStatus::Occupied->value,
            'status_changed_at' => Carbon::now()->subMinutes(35),
        ]);

        Visit::updateOrCreate(
            ['reservation_id' => $seated->reservation_id],
            [
                'table_id' => $tables['T4']->table_id,
                'guest_count' => 4,
                'opened_at' => Carbon::now()->subMinutes(35),
                'opened_by_staff_id' => $waiter->staff_id,
                'closed_at' => null,
                'closed_by_staff_id' => null,
                'close_reason' => null,
            ]
        );

        $soonSlot = $this->nearestSlot($slots, Carbon::now());
        Reservation::updateOrCreate(
            ['reference_code' => $this->referenceCode('today-t30')],
            [
                'customer_id' => $customers['noah']->customer_id,
                'slot_id' => $soonSlot->slot_id,
                'party_size' => 6,
                'booking_date' => Carbon::today()->toDateString(),
                'booking_time' => $soonSlot->slot_time,
                'status' => ReservationStatus::Confirmed->value,
                'reviewed_by_staff_id' => $admin->staff_id,
                'reviewed_at' => Carbon::today()->subHours(3),
                'special_requests' => 'Highchair needed for toddler please',
                'created_at' => Carbon::today()->subDay(),
            ]
        );

        Reservation::updateOrCreate(
            ['reference_code' => $this->referenceCode('today-request')],
            [
                'customer_id' => $customers['mia']->customer_id,
                'slot_id' => $slot1900->slot_id,
                'party_size' => 5,
                'booking_date' => Carbon::today()->toDateString(),
                'booking_time' => $slot1900->slot_time,
                'status' => ReservationStatus::Requested->value,
                'special_requests' => 'Window booth preferred if possible',
                'created_at' => Carbon::now()->subHours(2),
            ]
        );

        $bookingCustomers = array_values($customers);
        $spread = ['12:30', '17:30', '19:30'];

        for ($day = 1; $day <= self::UPCOMING_DAYS; $day++) {
            $date = Carbon::today()->addDays($day);

            foreach ($spread as $index => $time) {
                $slot = $slots[$time];
                $customer = $bookingCustomers[($day * 3 + $index) % count($bookingCustomers)];
                $isRequest = $date->isFriday() && $index === 2;

                Reservation::updateOrCreate(
                    ['reference_code' => $this->referenceCode("upcoming-{$day}-{$index}")],
                    [
                        'customer_id' => $customer->customer_id,
                        'slot_id' => $slot->slot_id,
                        'party_size' => [2, 4, 6, 3, 5][($day + $index) % 5],
                        'booking_date' => $date->toDateString(),
                        'booking_time' => $slot->slot_time,
                        'status' => $isRequest
                            ? ReservationStatus::Requested->value
                            : ReservationStatus::Confirmed->value,
                        'reviewed_by_staff_id' => $isRequest ? null : $admin->staff_id,
                        'reviewed_at' => $isRequest ? null : Carbon::now()->subHours(4),
                        'created_at' => Carbon::now()->subHours(6 + $day),
                    ]
                );
            }
        }
    }

    /**
     * @param  Collection<string, SlotCapacity>  $slots
     */
    private function nearestSlot(Collection $slots, Carbon $at): SlotCapacity
    {
        $target = ((int) $at->format('H') * 60) + (int) $at->format('i');

        return $slots->sortBy(function (SlotCapacity $slot) use ($target) {
            [$hour, $minute] = explode(':', substr((string) $slot->slot_time, 0, 5));

            return abs((((int) $hour * 60) + (int) $minute) - $target);
        })->first();
    }

    /**
     * @param  array<string, Customer>  $customers
     * @param  Collection<string, RestaurantTable>  $tables
     */
    private function seedOrdersAndPayments(
        array $customers,
        Collection $tables,
        Staff $waiter,
        Staff $kitchen
    ): void {
        $customerList = array_values($customers);
        $diningTables = $tables->filter(fn (RestaurantTable $t) => str_starts_with($t->table_number, 'T'))->values();
        $baskets = $this->basketTemplates();

        for ($daysAgo = self::HISTORY_DAYS; $daysAgo >= 1; $daysAgo--) {
            $date = Carbon::today()->subDays($daysAgo);
            $count = $this->ordersForWeekday($date->dayOfWeekIso);

            for ($seq = 1; $seq <= $count; $seq++) {
                $placed = $date->copy()->setTime(11, 30)
                    ->addMinutes((int) (600 / $count) * ($seq - 1) + (($daysAgo * 7 + $seq * 13) % 19));

                $this->makeOrder(
                    placed: $placed,
                    seq: $seq,
                    table: $diningTables[($daysAgo + $seq) % $diningTables->count()],
                    customer: $customerList[($daysAgo * 3 + $seq) % count($customerList)],
                    lines: $baskets[($daysAgo + $seq) % count($baskets)],
                    method: $seq % 3 === 0 ? PaymentMethod::Cash : PaymentMethod::Stripe,
                    status: OrderStatus::Served,
                    waiter: $waiter,
                    withVisit: true,
                );
            }
        }

        $todaySeq = 0;
        foreach ($this->todayServiceTimes() as $index => $placed) {
            $todaySeq++;
            $this->makeOrder(
                placed: $placed,
                seq: $todaySeq,
                table: $diningTables[$index % $diningTables->count()],
                customer: $customerList[$index % count($customerList)],
                lines: $baskets[$index % count($baskets)],
                method: $index % 3 === 0 ? PaymentMethod::Cash : PaymentMethod::Stripe,
                status: OrderStatus::Served,
                waiter: $waiter,
                withVisit: true,
            );
        }

        $inFlight = [
            [OrderStatus::Paid, OrderItemStatus::Pending, 'T2', 'sarah', 6],
            [OrderStatus::Preparing, OrderItemStatus::Preparing, 'T5', 'emily', 11],
            [OrderStatus::Ready, OrderItemStatus::Ready, 'T6', 'liam', 19],
        ];

        foreach ($inFlight as $offset => [$status, $lineStatus, $tableNumber, $customerKey, $minutesAgo]) {
            $todaySeq++;
            $order = $this->makeOrder(
                placed: Carbon::now()->subMinutes($minutesAgo),
                seq: $todaySeq,
                table: $tables[$tableNumber],
                customer: $customers[$customerKey],
                lines: $baskets[$offset % count($baskets)],
                method: PaymentMethod::Stripe,
                status: $status,
                waiter: $waiter,
                lineStatus: $lineStatus,
                withVisit: true,
            );

            $tables[$tableNumber]->update([
                'status' => TableStatus::Occupied->value,
                'status_changed_at' => $order->placed_at,
            ]);
        }

        $todaySeq++;
        $cashOrder = $this->makeOrder(
            placed: Carbon::now()->subMinutes(18),
            seq: $todaySeq,
            table: $tables['T7'],
            customer: $customers['james'],
            lines: [['steak', '300g Cut', 1], ['beer', 'Schooner 425ml', 1]],
            method: PaymentMethod::Cash,
            status: OrderStatus::PendingPayment,
            waiter: $waiter,
            lineStatus: OrderItemStatus::Pending,
        );

        $tables['T7']->update([
            'status' => TableStatus::Occupied->value,
            'status_changed_at' => $cashOrder->placed_at,
        ]);

        $todaySeq++;
        $lateOrder = $this->makeOrder(
            placed: Carbon::now()->subMinutes(40),
            seq: $todaySeq,
            table: $tables['T8'],
            customer: $customers['ava'],
            lines: [['lambShank', 'Single Shank', 2], ['wine', 'Glass 250ml', 2]],
            method: PaymentMethod::Stripe,
            status: OrderStatus::Preparing,
            waiter: $waiter,
            lineStatus: OrderItemStatus::Preparing,
        );

        $tables['T8']->update([
            'status' => TableStatus::Occupied->value,
            'status_changed_at' => $lateOrder->placed_at,
        ]);

        $todaySeq++;
        $conflictOrder = $this->makeOrder(
            placed: Carbon::now()->subMinutes(12),
            seq: $todaySeq,
            table: $tables['T9'],
            customer: $customers['noah'],
            lines: [['barramundi', 'Regular', 1], ['llb', 'Regular', 1]],
            method: PaymentMethod::Stripe,
            status: OrderStatus::Preparing,
            waiter: $waiter,
            lineStatus: OrderItemStatus::Preparing,
        );

        $conflictOrder->forceFill(['has_stock_conflict' => true])->save();

        $tables['T9']->update([
            'status' => TableStatus::Occupied->value,
            'status_changed_at' => $conflictOrder->placed_at,
        ]);

        $todaySeq++;
        $this->makeOrder(
            placed: Carbon::now()->subHours(3),
            seq: $todaySeq,
            table: $tables['B1'],
            customer: $customers['ethan'],
            lines: [['espressoMartini', 'Standard', 2]],
            method: PaymentMethod::Stripe,
            status: OrderStatus::Cancelled,
            waiter: $waiter,
            lineStatus: OrderItemStatus::Cancelled,
        );

        $this->refreshLiveEtas();

        $lateOrder->forceFill([
            'kitchen_eta_at' => Carbon::now()->subMinutes(6),
            'bar_eta_at' => Carbon::now()->subMinutes(20),
        ])->save();

        $this->seedRefunds($waiter, $kitchen);
    }

    private function refreshLiveEtas(): void
    {
        $eta = app(EtaService::class);

        $live = Order::whereIn('status', [
            OrderStatus::Paid->value,
            OrderStatus::Preparing->value,
            OrderStatus::Ready->value,
        ])->with('items.menuItem')->get();

        foreach ($live as $order) {
            $order->forceFill([
                'kitchen_eta_at' => $eta->estimate($order, $order->items, Destination::Kitchen),
                'bar_eta_at' => $eta->estimate($order, $order->items, Destination::Bar),
            ])->save();
        }
    }

    /**
     * @return array<int, array<int, array{0: string, 1: string, 2: int}>>
     */
    private function basketTemplates(): array
    {
        return [
            [['parma', 'Regular', 2], ['beer', 'Pint 570ml', 2], ['bread', 'Regular Loaf', 1]],
            [['burger', 'Regular Single', 2], ['loadedChips', 'Share', 1], ['llb', 'Regular', 2]],
            [['pizza', '14-Inch Share', 1], ['pepperoni', '14-Inch Share', 1], ['wine', 'Glass 250ml', 2]],
            [['steak', '300g Cut', 2], ['sauvBlanc', 'Bottle 750ml', 1], ['stickyDate', 'Standard Serve', 2]],
            [['calamari', 'Entree Plate', 1], ['flathead', 'Regular', 2], ['cider', 'Bottle 330ml', 2]],
            [['schnitzel', 'Regular', 1], ['bolognese', 'Regular', 1], ['coldBrew', 'Regular', 2]],
            [['lambShank', 'Single Shank', 2], ['gnocchi', 'Regular', 1], ['spritz', 'Standard', 2]],
            [['chickenBurger', 'Regular', 2], ['wings', 'Half Dozen', 1], ['xpa', 'Schooner 425ml', 2]],
            [['risotto', 'Regular', 1], ['pumpkinPizza', '11-Inch Individual', 1], ['prosecco', 'Glass 150ml', 2]],
            [['pokeBowl', 'Regular', 2], ['juice', 'Regular', 2], ['gelato', 'Three Scoops', 1]],
            [['linguine', 'Regular', 2], ['haloumi', 'Entree Plate', 1], ['wine', 'Glass 150ml', 2]],
            [['meatLovers', '14-Inch Share', 1], ['wings', 'Dozen', 1], ['beer', 'Jug 1140ml', 1]],
            [['porterhouse', '250g Cut', 2], ['cauliflower', 'Entree Bowl', 1], ['brownie', 'Standard Serve', 2]],
            [['caesarWrap', 'Regular', 2], ['flatWhite', 'Regular', 2], ['lemonTart', 'Standard Serve', 1]],
            [['plantBurger', 'Regular', 1], ['skewers', 'Two Skewers', 1], ['zeroBeer', 'Bottle 330ml', 2]],
            [['bangers', 'Regular', 2], ['prawnPizza', '11-Inch Individual', 1], ['sparkling', 'Bottle 500ml', 2]],
        ];
    }

    /**
     * @return array<int, Carbon>
     */
    private function todayServiceTimes(): array
    {
        $end = Carbon::now()->subMinutes(30);
        $start = Carbon::today()->setTime(11, 30);

        if ($start->greaterThanOrEqualTo($end)) {
            $start = Carbon::today()->addMinutes(20);
        }

        $minutes = (int) $start->diffInMinutes($end, false);
        if ($minutes < 20) {
            return [];
        }

        $count = min(9, max(3, intdiv($minutes, 45)));

        return array_map(
            fn (int $i) => $start->copy()->addMinutes(intdiv($minutes * $i, $count)),
            range(0, $count - 1)
        );
    }

    private function ordersForWeekday(int $isoWeekday): int
    {
        return match ($isoWeekday) {
            5 => 6,
            6 => 7,
            7 => 4,
            default => 3,
        };
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: int}>  $lines
     */
    private function makeOrder(
        Carbon $placed,
        int $seq,
        RestaurantTable $table,
        ?Customer $customer,
        array $lines,
        PaymentMethod $method,
        OrderStatus $status,
        Staff $waiter,
        OrderItemStatus $lineStatus = OrderItemStatus::Served,
        bool $withVisit = false,
    ): Order {
        $orderNumber = sprintf('%s-%03d', $placed->format('Ymd'), $seq);
        $isClosed = $status === OrderStatus::Served;

        $visitId = null;
        if ($withVisit) {
            $visit = Visit::updateOrCreate(
                ['table_id' => $table->table_id, 'opened_at' => $placed->copy()->subMinutes(10)],
                [
                    'guest_count' => min($table->seat_capacity, 2 + ($seq % 3)),
                    'opened_by_staff_id' => $waiter->staff_id,
                    'closed_at' => $isClosed ? $placed->copy()->addMinutes(75) : null,
                    'closed_by_staff_id' => $isClosed ? $waiter->staff_id : null,
                    'close_reason' => $isClosed ? VisitCloseReason::StaffClear->value : null,
                ]
            );
            $visitId = $visit->visit_id;
        }

        $order = Order::updateOrCreate(
            ['order_number' => $orderNumber],
            [
                'table_id' => $table->table_id,
                'visit_id' => $visitId,
                'customer_id' => $customer?->customer_id,
                'taken_by_staff_id' => $method === PaymentMethod::Cash ? $waiter->staff_id : null,
                'idempotency_key' => 'seed-'.$orderNumber,
                'status' => $status->value,
                'payment_status' => match ($status) {
                    OrderStatus::PendingPayment, OrderStatus::Cancelled => PaymentStatus::Unpaid->value,
                    default => PaymentStatus::Paid->value,
                },
                'has_stock_conflict' => false,
                'total_amount' => 0,
                'gst_amount' => 0,
                'placed_at' => $placed,
            ]
        );

        Refund::where('order_id', $order->order_id)->delete();
        OrderItem::where('order_id', $order->order_id)->delete();

        $total = 0.0;
        $kitchenPrep = 0;
        $barPrep = 0;
        $isPrepared = in_array($lineStatus, [OrderItemStatus::Ready, OrderItemStatus::Served], true);

        foreach (array_values($lines) as $index => [$key, $sizeName, $quantity]) {
            $item = $this->items[$key];
            $size = MenuItemSize::where('item_id', $item->item_id)
                ->where('size_name', $sizeName)
                ->firstOrFail();

            $onSale = $size->sale_price !== null
                && $size->sale_starts_at !== null
                && $size->sale_ends_at !== null
                && $placed->greaterThanOrEqualTo($size->sale_starts_at)
                && $placed->lessThanOrEqualTo($size->sale_ends_at);

            $unitPrice = (float) ($onSale ? $size->sale_price : $size->price);
            $lineTotal = round($unitPrice * $quantity, 2);
            $total += $lineTotal;

            if ($item->destination === Destination::Bar) {
                $barPrep = max($barPrep, $item->prep_minutes);
            } else {
                $kitchenPrep = max($kitchenPrep, $item->prep_minutes);
            }

            OrderItem::create([
                'order_id' => $order->order_id,
                'line_no' => $index + 1,
                'item_id' => $item->item_id,
                'size_id' => $size->size_id,
                'item_name' => $item->item_name,
                'size_name' => $size->size_name,
                'destination' => $item->destination,
                'quantity' => $quantity,
                'original_unit_price' => $size->price,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'status' => $lineStatus->value,
                'prepared_at' => $isPrepared
                    ? $placed->copy()->addMinutes(4 + $item->prep_minutes)
                    : null,
            ]);
        }

        $total = round($total, 2);
        $unpaid = in_array($status, [OrderStatus::PendingPayment, OrderStatus::Cancelled], true);
        $paidAt = $unpaid ? null : $placed->copy()->addMinutes(2);

        $order->forceFill([
            'total_amount' => $total,
            'gst_amount' => round($total / 11, 2),
            'paid_at' => $paidAt,
            'started_at' => in_array($status, [OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Served], true)
                ? $placed->copy()->addMinutes(4)
                : null,
            'ready_at' => in_array($status, [OrderStatus::Ready, OrderStatus::Served], true)
                ? $placed->copy()->addMinutes(4 + $kitchenPrep)
                : null,
            'served_at' => $isClosed ? $placed->copy()->addMinutes(8 + $kitchenPrep) : null,
            'cancelled_at' => $status === OrderStatus::Cancelled ? $placed->copy()->addMinutes(3) : null,
            'kitchen_eta_at' => $paidAt !== null && $kitchenPrep > 0 ? $paidAt->copy()->addMinutes($kitchenPrep) : null,
            'bar_eta_at' => $paidAt !== null && $barPrep > 0 ? $paidAt->copy()->addMinutes($barPrep) : null,
        ])->save();

        $this->recordPayment($order, $method, $total, $paidAt, $waiter, $status);
        $this->recordStatusHistory($order, $status, $method);

        return $order;
    }

    private function recordPayment(
        Order $order,
        PaymentMethod $method,
        float $total,
        ?Carbon $paidAt,
        Staff $waiter,
        OrderStatus $status
    ): void {
        if ($status === OrderStatus::Cancelled) {
            Payment::where('order_id', $order->order_id)->delete();

            return;
        }

        $succeeded = $paidAt !== null;
        $received = $succeeded && $method === PaymentMethod::Cash ? ceil($total / 5) * 5 : null;

        Payment::updateOrCreate(
            ['order_id' => $order->order_id],
            [
                'recorded_by_staff_id' => $method === PaymentMethod::Cash ? $waiter->staff_id : null,
                'method' => $method->value,
                'amount' => $total,
                'stripe_session_id' => $method === PaymentMethod::Stripe ? 'cs_test_'.$order->order_number : null,
                'provider_payment_id' => $succeeded && $method === PaymentMethod::Stripe
                    ? 'pi_test_'.$order->order_number
                    : null,
                'amount_received' => $received,
                'change_given' => $received !== null ? round($received - $total, 2) : null,
                'rounding_amount' => 0,
                'status' => $succeeded
                    ? PaymentAttemptStatus::Succeeded->value
                    : PaymentAttemptStatus::Pending->value,
                'paid_at' => $paidAt,
                'created_at' => $order->placed_at,
            ]
        );
    }

    private function recordStatusHistory(Order $order, OrderStatus $status, PaymentMethod $method): void
    {
        $paySource = $method === PaymentMethod::Cash ? 'waitstaff' : 'stripe';
        $chain = [[OrderStatus::PendingPayment, $order->placed_at, 'customer']];

        if ($status === OrderStatus::Cancelled) {
            $chain[] = [OrderStatus::Cancelled, $order->cancelled_at, 'waitstaff'];
        } else {
            if ($order->paid_at !== null) {
                $chain[] = [OrderStatus::Paid, $order->paid_at, $paySource];
            }
            if ($order->started_at !== null) {
                $chain[] = [OrderStatus::Preparing, $order->started_at, 'kitchen'];
            }
            if ($order->ready_at !== null) {
                $chain[] = [OrderStatus::Ready, $order->ready_at, 'kitchen'];
            }
            if ($order->served_at !== null) {
                $chain[] = [OrderStatus::Served, $order->served_at, 'waitstaff'];
            }
        }

        OrderStatusHistory::where('order_id', $order->order_id)->delete();

        foreach ($chain as $index => [$chainStatus, $occurredAt, $source]) {
            OrderStatusHistory::create([
                'order_id' => $order->order_id,
                'status_seq' => $index + 1,
                'status' => $chainStatus->value,
                'occurred_at' => $occurredAt,
                'event_source' => $source,
            ]);
        }
    }

    private function seedRefunds(Staff $waiter, Staff $kitchen): void
    {
        $open = Order::where('status', OrderStatus::Served->value)
            ->whereDate('placed_at', Carbon::today())
            ->orderByDesc('order_id')
            ->first();

        $settled = Order::where('status', OrderStatus::Served->value)
            ->whereDate('placed_at', Carbon::today()->subDays(9))
            ->orderBy('order_id')
            ->first();

        $cases = [
            [$open, RefundStatus::Requested, 'Guest reported dietary cross-contact error'],
            [$settled, RefundStatus::Completed, 'Wrong size sent to the table'],
        ];

        foreach ($cases as [$order, $status, $reason]) {
            if ($order === null) {
                continue;
            }

            $line = OrderItem::where('order_id', $order->order_id)->orderBy('line_no')->first();
            $payment = Payment::where('order_id', $order->order_id)->first();

            if ($line === null || $payment === null) {
                continue;
            }

            $isComplete = $status === RefundStatus::Completed;

            Refund::updateOrCreate(
                ['order_id' => $order->order_id],
                [
                    'order_item_id' => $line->order_item_id,
                    'payment_id' => $payment->payment_id,
                    'requested_by_staff_id' => $kitchen->staff_id,
                    'processed_by_staff_id' => $isComplete ? $waiter->staff_id : null,
                    'method' => RefundMethod::Stripe->value,
                    'quantity' => 1,
                    'amount' => $line->unit_price,
                    'reason' => $reason,
                    'status' => $status->value,
                    'return_to_stock' => false,
                    'provider_refund_id' => $isComplete ? 're_test_'.$order->order_number : null,
                    'requested_at' => $isComplete ? $order->served_at : Carbon::now()->subMinutes(25),
                    'completed_at' => $isComplete ? $order->served_at?->copy()->addMinutes(20) : null,
                ]
            );

            if ($isComplete) {
                $line->forceFill(['refunded_qty' => 1])->save();
                $order->forceFill(['payment_status' => PaymentStatus::PartiallyRefunded->value])->save();
            }
        }
    }

    private function syncSoldToday(): void
    {
        MenuItem::query()->update(['sold_today' => 0]);

        $sold = OrderItem::whereHas('order', fn ($query) => $query
            ->whereDate('placed_at', Carbon::today())
            ->where('status', '!=', OrderStatus::Cancelled->value))
            ->selectRaw('item_id, SUM(quantity) as qty')
            ->groupBy('item_id')
            ->pluck('qty', 'item_id');

        foreach ($sold as $itemId => $quantity) {
            MenuItem::whereKey($itemId)->update(['sold_today' => (int) $quantity]);
        }

        $barramundi = $this->items['barramundi']->fresh();
        $barramundi->forceFill(['daily_limit' => $barramundi->sold_today + 2])->save();

        $ribs = $this->items['ribs']->fresh();
        $ribs->forceFill(['daily_limit' => max(1, $ribs->sold_today), 'is_available' => false])->save();
    }

    private function seedFeedback(Staff $admin): void
    {
        $reviews = [
            ['food' => 5, 'serv' => 5, 'feat' => true, 'comment' => 'Hands down the best Chicken Parma in the northern suburbs! The crisp panko coating and rich Napoli sauce were sensational.'],
            ['food' => 5, 'serv' => 5, 'feat' => true, 'comment' => 'Outstanding atmosphere, prompt QR table ordering, and the Wagyu cheeseburger was cooked to perfection.'],
            ['food' => 5, 'serv' => 5, 'feat' => true, 'comment' => 'We hosted our family reunion here. Staff were wonderful with the children and the steak was mouth-watering!'],
            ['food' => 5, 'serv' => 4, 'comment' => 'Great cold beers on tap and generous portions. Loved the easy payment through the phone.'],
            ['food' => 4, 'serv' => 5, 'comment' => 'Salt & pepper calamari was melt-in-the-mouth tender. Very friendly waitstaff.'],
            ['food' => 4, 'serv' => 4, 'comment' => 'Very solid pub meal. Quick service even on a busy Friday night.'],
            ['food' => 5, 'serv' => 4, 'comment' => 'Truffle mushroom risotto is a must-try for vegetarians! Packed with flavour.'],
            ['food' => 4, 'serv' => 5, 'comment' => 'Delightful desserts and great coffee. Will definitely be returning.'],
            ['food' => 3, 'serv' => 4, 'comment' => 'Food was decent, though chips could have been slightly crunchier. Service was pleasant.'],
            ['food' => 4, 'serv' => 3, 'comment' => 'Lovely steak, though drinks took about 15 minutes during peak rush.'],
            ['food' => 5, 'serv' => 5, 'comment' => 'The lamb shank falls off the bone. Best value main on the menu.'],
            ['food' => 4, 'serv' => 4, 'comment' => 'Woodfired pizzas are the real thing, proper leopard-spotted crust.'],
            [
                'food' => 4, 'serv' => 4,
                'comment' => 'Good family dining experience. Plenty of high chairs and spacious outdoor tables.',
                'reply' => 'Thank you for dining with us! We look forward to welcoming you and your family back soon.',
            ],
            [
                'food' => 1, 'serv' => 1, 'hidden' => true,
                'reason' => 'Defamatory profanity and off-site spam link',
                'comment' => 'Absolute trash visit our gambling site at spam-link.xyz for free bonus codes!!!',
            ],
        ];

        $orders = Order::where('status', OrderStatus::Served->value)
            ->whereNotNull('customer_id')
            ->orderByDesc('placed_at')
            ->limit(count($reviews))
            ->get();

        foreach ($reviews as $index => $review) {
            $order = $orders[$index] ?? null;
            if ($order === null) {
                break;
            }

            Feedback::updateOrCreate(
                ['order_id' => $order->order_id],
                [
                    'customer_id' => $order->customer_id,
                    'food_rating' => $review['food'],
                    'service_rating' => $review['serv'],
                    'comment' => $review['comment'],
                    'is_featured' => $review['feat'] ?? false,
                    'is_hidden' => $review['hidden'] ?? false,
                    'hidden_reason' => $review['reason'] ?? null,
                    'admin_reply' => $review['reply'] ?? null,
                    'replied_by_staff_id' => isset($review['reply']) ? $admin->staff_id : null,
                    'replied_at' => isset($review['reply']) ? Carbon::now()->subHours(2) : null,
                    'submitted_at' => $order->served_at ?? $order->placed_at,
                ]
            );
        }
    }

    private function seedAuditLogsAndArchives(Staff $admin, Staff $waiter): void
    {
        AuditLog::firstOrCreate(
            ['action_type' => 'setting_update', 'entity_name' => 'setting', 'entity_id' => 1],
            [
                'staff_id' => $admin->staff_id,
                'details' => ['key' => 'qr_ordering_enabled', 'old' => false, 'new' => true],
                'ip_address' => '127.0.0.1',
                'logged_at' => Carbon::now()->subDays(3),
            ]
        );

        AuditLog::firstOrCreate(
            ['action_type' => 'table_override', 'entity_name' => 'restaurant_table', 'entity_id' => 1],
            [
                'staff_id' => $waiter->staff_id,
                'details' => ['table' => 'T1', 'from' => 'available', 'to' => 'reserved', 'reason' => 'VIP walk-in party hold'],
                'ip_address' => '127.0.0.1',
                'logged_at' => Carbon::now()->subDays(2),
            ]
        );

        $prompts = [
            'What gluten free dishes do you recommend?',
            'Build me a vegan dinner for two under $80',
            'Which pizza is best for someone who hates chilli?',
            'Do you have anything dairy free for dessert?',
            'What goes well with the Barossa Shiraz?',
            'Is the parma available in a smaller size?',
            'Plan a birthday dinner for six people',
            'What is on special tonight?',
        ];

        foreach ($prompts as $index => $prompt) {
            $tokensIn = 250 + (($index * 37) % 350);
            $tokensOut = 80 + (($index * 23) % 140);

            AuditLog::firstOrCreate(
                ['action_type' => 'ai_request', 'entity_name' => 'ai', 'entity_id' => $index + 1],
                [
                    'customer_id' => 1,
                    'details' => [
                        'feature' => $index % 2 === 0 ? 'chat' : 'meal_builder',
                        'tokens_in' => $tokensIn,
                        'tokens_out' => $tokensOut,
                        'total_tokens' => $tokensIn + $tokensOut,
                        'prompt_preview' => $prompt,
                    ],
                    'ip_address' => '127.0.0.1',
                    'logged_at' => Carbon::now()->subHours(($index + 1) * 4),
                ]
            );
        }

        $archiveLog = AuditLog::firstOrCreate(
            ['action_type' => 'menu_item_archive', 'entity_name' => 'menu_item', 'entity_id' => 999],
            [
                'staff_id' => $admin->staff_id,
                'details' => ['reason' => 'Seasonal menu rotation Q2'],
                'ip_address' => '127.0.0.1',
                'logged_at' => Carbon::now()->subWeeks(2),
            ]
        );

        HistoricalDataManagement::updateOrCreate(
            ['log_id' => $archiveLog->log_id],
            [
                'entity_name' => 'menu_item',
                'record_id' => '999',
                'record_data' => json_encode([
                    'item_name' => 'Slow Roasted Lamb Shank (2024 recipe)',
                    'category' => 'Mains & Classics',
                    'price' => 36.00,
                    'deactivated_by' => $admin->full_name,
                    'deactivated_at' => Carbon::now()->subWeeks(2)->toIso8601String(),
                ], JSON_PRETTY_PRINT),
                'status' => 'archived',
                'archived_at' => Carbon::now()->subWeeks(2),
            ]
        );
    }
}
