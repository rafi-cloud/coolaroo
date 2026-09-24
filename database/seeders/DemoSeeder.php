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
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\SlotCapacity;
use App\Models\Staff;
use App\Models\Visit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * SDD, TC--01.
 * Seeds realistic volume data across catalogue, customers, reservations,
 * visits, orders, payments, refunds, feedback, and audit logs.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure core foundation tables exist
        if (Role::count() === 0) {
            $this->call(DatabaseSeeder::class);
        }

        $admin = Staff::whereHas('role', fn ($q) => $q->where('role_name', 'admin'))->first();
        $waiter = Staff::whereHas('role', fn ($q) => $q->where('role_name', 'waitstaff'))->first();
        $kitchen = Staff::whereHas('role', fn ($q) => $q->where('role_name', 'kitchen'))->first();
        $bar = Staff::whereHas('role', fn ($q) => $q->where('role_name', 'bar'))->first();

        // 2. Seed Allergens & Dietary Tags
        $allergens = $this->seedAllergens();
        $tags = $this->seedDietaryTags();

        // 3. Seed Menu Categories
        $categories = $this->seedCategories();

        // 4. Seed Menu Items, Sizes, and Add-ons
        $items = $this->seedMenuItems($categories, $allergens, $tags);

        // 5. Seed Realistic Customers
        $customers = $this->seedCustomers();

        // 6. Seed Reservations, Tables & Visits
        $tables = RestaurantTable::all()->keyBy('table_number');
        $slots = SlotCapacity::all()->keyBy(fn ($s) => substr($s->slot_time, 0, 5));
        $this->seedReservationsAndVisits($customers, $tables, $slots, $admin, $waiter);

        // 7. Seed Orders, Order Items, Payments & Refunds
        $this->seedOrdersAndPayments($customers, $items, $tables, $admin, $waiter, $kitchen);

        // 8. Seed Customer Feedback
        $this->seedFeedback($admin);

        // 9. Seed Audit Logs & Archives
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
            $allergens[$name] = Allergen::firstOrCreate(
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
            $tags[$name] = DietaryTag::firstOrCreate(
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
        $categoriesData = [
            ['name' => 'Starters & Breads', 'order' => 1],
            ['name' => 'Mains & Classics', 'order' => 2],
            ['name' => 'Steaks & Grills', 'order' => 3],
            ['name' => 'Woodfired Pizzas', 'order' => 4],
            ['name' => 'Burgers & Pub Fare', 'order' => 5],
            ['name' => 'Pastas & Bowls', 'order' => 6],
            ['name' => 'Desserts', 'order' => 7],
            ['name' => 'Beers & Ciders', 'order' => 8],
            ['name' => 'Wines & Cocktails', 'order' => 9],
            ['name' => 'Non-Alcoholic Drinks', 'order' => 10],
        ];

        $categories = [];
        foreach ($categoriesData as $cat) {
            $categories[$cat['name']] = MenuCategory::firstOrCreate(
                ['category_name' => $cat['name']],
                ['display_order' => $cat['order'], 'is_active' => true]
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

        // 1. Chicken Parmigiana (Pub Classic)
        $parma = MenuItem::firstOrCreate(
            ['item_name' => 'Classic Chicken Parmigiana'],
            [
                'category_id' => $categories['Mains & Classics']->category_id,
                'description' => 'Crisp panko-crumbed chicken breast topped with smoked leg ham, rich house napoli sauce and bubbling mozzarella cheese. Served with pub chips and garden salad.',
                'destination' => Destination::Kitchen->value,
                'prep_minutes' => 15,
                'is_available' => true,
                'is_featured' => true,
                'daily_limit' => 60,
                'sold_today' => 14,
                'calories_kcal' => 980,
                'protein_g' => 62,
                'carbohydrates_g' => 54,
                'fat_g' => 48,
            ]
        );
        $this->attachSizes($parma, [
            ['size_name' => 'Regular', 'price' => 28.50, 'order' => 1],
            ['size_name' => 'Parmageddon (Double)', 'price' => 36.00, 'order' => 2],
        ]);
        $sauceGroup = AddOnGroup::firstOrCreate(
            ['item_id' => $parma->item_id, 'group_name' => 'Choice of Sauce'],
            ['is_required' => false, 'min_select' => 0, 'max_select' => 1, 'display_order' => 1]
        );
        $this->attachOptions($sauceGroup, [
            ['name' => 'House Gravy', 'delta' => 0],
            ['name' => 'Creamy Mushroom', 'delta' => 3.00],
            ['name' => 'Green Peppercorn', 'delta' => 2.50],
        ]);
        $parma->allergens()->syncWithoutDetaching([$allergens['Gluten']->allergen_id, $allergens['Eggs']->allergen_id, $allergens['Milk']->allergen_id]);
        $items['parma'] = $parma;

        // 2. Angus Ribeye 300g (On Special!)
        $steak = MenuItem::firstOrCreate(
            ['item_name' => 'Angus Ribeye 300g'],
            [
                'category_id' => $categories['Steaks & Grills']->category_id,
                'description' => 'Grain-fed Black Angus ribeye chargrilled to perfection, served with roasted chat potatoes, buttered broccolini and rosemary jus.',
                'destination' => Destination::Kitchen->value,
                'prep_minutes' => 20,
                'is_available' => true,
                'is_featured' => true,
                'daily_limit' => 30,
                'sold_today' => 8,
                'calories_kcal' => 850,
                'protein_g' => 74,
                'carbohydrates_g' => 18,
                'fat_g' => 52,
            ]
        );
        $this->attachSizes($steak, [
            [
                'size_name' => '300g Cut',
                'price' => 44.00,
                'sale_price' => 38.00, // on special
                'sale_starts_at' => Carbon::now()->subDays(2),
                'sale_ends_at' => Carbon::now()->addDays(5),
                'order' => 1,
            ],
            [
                'size_name' => '500g King Cut',
                'price' => 58.00,
                'sale_price' => null,
                'order' => 2,
            ],
        ]);
        $steak->allergens()->syncWithoutDetaching([$allergens['Milk']->allergen_id]);
        $steak->dietaryTags()->syncWithoutDetaching([$tags['Gluten-Free']->dietary_tag_id]);
        $items['steak'] = $steak;

        // 3. Wagyu Cheeseburger
        $burger = MenuItem::firstOrCreate(
            ['item_name' => 'Coolaroo Wagyu Cheeseburger'],
            [
                'category_id' => $categories['Burgers & Pub Fare']->category_id,
                'description' => 'Grilled 200g Wagyu beef patty, double American cheddar, bread & butter pickles, crisp cos lettuce, and secret burger sauce in a toasted milk bun with beer-battered fries.',
                'destination' => Destination::Kitchen->value,
                'prep_minutes' => 12,
                'is_available' => true,
                'is_featured' => true,
                'daily_limit' => 50,
                'sold_today' => 16,
                'calories_kcal' => 920,
                'protein_g' => 48,
                'carbohydrates_g' => 64,
                'fat_g' => 46,
            ]
        );
        $this->attachSizes($burger, [
            ['size_name' => 'Regular Single', 'price' => 24.50, 'order' => 1],
            ['size_name' => 'Double Beast', 'price' => 30.50, 'order' => 2],
        ]);
        $burgerExtras = AddOnGroup::firstOrCreate(
            ['item_id' => $burger->item_id, 'group_name' => 'Burger Additions'],
            ['is_required' => false, 'min_select' => 0, 'max_select' => 3, 'display_order' => 1]
        );
        $this->attachOptions($burgerExtras, [
            ['name' => 'Smoked Streaky Bacon', 'delta' => 3.50],
            ['name' => 'Fried Free-Range Egg', 'delta' => 2.50],
            ['name' => 'Pickled Jalapenos', 'delta' => 1.50],
            ['name' => 'Gluten-Free Bun Substitute', 'delta' => 3.00],
        ]);
        $burger->allergens()->syncWithoutDetaching([$allergens['Gluten']->allergen_id, $allergens['Milk']->allergen_id, $allergens['Sesame Seeds']->allergen_id]);
        $items['burger'] = $burger;

        // 4. Woodfired Margherita Pizza
        $pizza = MenuItem::firstOrCreate(
            ['item_name' => 'Woodfired Margherita Pizza'],
            [
                'category_id' => $categories['Woodfired Pizzas']->category_id,
                'description' => 'San Marzano tomato base, fresh buffalo mozzarella, fragrant basil leaves and cold-pressed extra virgin olive oil on 48-hour fermented dough.',
                'destination' => Destination::Kitchen->value,
                'prep_minutes' => 10,
                'is_available' => true,
                'is_featured' => true,
                'daily_limit' => 45,
                'sold_today' => 11,
                'calories_kcal' => 780,
                'protein_g' => 32,
                'carbohydrates_g' => 86,
                'fat_g' => 28,
            ]
        );
        $this->attachSizes($pizza, [
            ['size_name' => '11-Inch Individual', 'price' => 22.00, 'order' => 1],
            ['size_name' => '14-Inch Share', 'price' => 28.50, 'order' => 2],
        ]);
        $pizza->allergens()->syncWithoutDetaching([$allergens['Gluten']->allergen_id, $allergens['Milk']->allergen_id]);
        $pizza->dietaryTags()->syncWithoutDetaching([$tags['Vegetarian']->dietary_tag_id]);
        $items['pizza'] = $pizza;

        // 5. Truffle Mushroom Risotto
        $risotto = MenuItem::firstOrCreate(
            ['item_name' => 'Wild Forest Truffle Risotto'],
            [
                'category_id' => $categories['Pastas & Bowls']->category_id,
                'description' => 'Carnaroli rice cooked with porcini and Swiss brown mushrooms, white truffle oil, baby spinach, toasted pine nuts and aged shaved parmesan.',
                'destination' => Destination::Kitchen->value,
                'prep_minutes' => 14,
                'is_available' => true,
                'is_featured' => false,
                'daily_limit' => 30,
                'sold_today' => 7,
                'calories_kcal' => 640,
                'protein_g' => 18,
                'carbohydrates_g' => 72,
                'fat_g' => 26,
            ]
        );
        $this->attachSizes($risotto, [
            ['size_name' => 'Regular', 'price' => 26.50, 'order' => 1],
        ]);
        $risotto->allergens()->syncWithoutDetaching([$allergens['Milk']->allergen_id, $allergens['Tree Nuts']->allergen_id]);
        $risotto->dietaryTags()->syncWithoutDetaching([$tags['Vegetarian']->dietary_tag_id, $tags['Gluten-Free']->dietary_tag_id]);
        $items['risotto'] = $risotto;

        // 6. Salt & Pepper Calamari (Starter)
        $calamari = MenuItem::firstOrCreate(
            ['item_name' => 'Salt & Pepper Calamari'],
            [
                'category_id' => $categories['Starters & Breads']->category_id,
                'description' => 'Tender flash-fried calamari dusted in Sichuan pepper and sea salt, served with fresh lime aioli and pickled chilli salad.',
                'destination' => Destination::Kitchen->value,
                'prep_minutes' => 8,
                'is_available' => true,
                'is_featured' => true,
                'daily_limit' => 40,
                'sold_today' => 9,
                'calories_kcal' => 450,
                'protein_g' => 28,
                'carbohydrates_g' => 24,
                'fat_g' => 22,
            ]
        );
        $this->attachSizes($calamari, [
            ['size_name' => 'Entree Plate', 'price' => 18.50, 'order' => 1],
            ['size_name' => 'Share Platter', 'price' => 26.00, 'order' => 2],
        ]);
        $calamari->allergens()->syncWithoutDetaching([$allergens['Molluscs']->allergen_id, $allergens['Eggs']->allergen_id]);
        $calamari->dietaryTags()->syncWithoutDetaching([$tags['Dairy-Free']->dietary_tag_id]);
        $items['calamari'] = $calamari;

        // 7. Garlic & Herb Loaf
        $bread = MenuItem::firstOrCreate(
            ['item_name' => 'Warm Garlic & Herb Pull-Apart'],
            [
                'category_id' => $categories['Starters & Breads']->category_id,
                'description' => 'Toasted sourdough cob stuffed with whipped roasted garlic butter, parsley and melted mozzarella.',
                'destination' => Destination::Kitchen->value,
                'prep_minutes' => 6,
                'is_available' => true,
                'is_featured' => false,
                'daily_limit' => null,
                'sold_today' => 15,
                'calories_kcal' => 520,
                'protein_g' => 12,
                'carbohydrates_g' => 58,
                'fat_g' => 24,
            ]
        );
        $this->attachSizes($bread, [
            ['size_name' => 'Regular Loaf', 'price' => 11.00, 'order' => 1],
        ]);
        $bread->allergens()->syncWithoutDetaching([$allergens['Gluten']->allergen_id, $allergens['Milk']->allergen_id]);
        $bread->dietaryTags()->syncWithoutDetaching([$tags['Vegetarian']->dietary_tag_id]);
        $items['bread'] = $bread;

        // 8. Sticky Date Pudding (Dessert)
        $stickyDate = MenuItem::firstOrCreate(
            ['item_name' => 'Warm Sticky Date Pudding'],
            [
                'category_id' => $categories['Desserts']->category_id,
                'description' => 'Traditional warm sponge pudding drizzled with rich butterscotch sauce and served with vanilla bean ice cream and almond praline.',
                'destination' => Destination::Kitchen->value,
                'prep_minutes' => 6,
                'is_available' => true,
                'is_featured' => true,
                'daily_limit' => 25,
                'sold_today' => 6,
                'calories_kcal' => 620,
                'protein_g' => 8,
                'carbohydrates_g' => 84,
                'fat_g' => 26,
            ]
        );
        $this->attachSizes($stickyDate, [
            ['size_name' => 'Standard Serve', 'price' => 14.50, 'order' => 1],
        ]);
        $stickyDate->allergens()->syncWithoutDetaching([$allergens['Gluten']->allergen_id, $allergens['Milk']->allergen_id, $allergens['Eggs']->allergen_id, $allergens['Tree Nuts']->allergen_id]);
        $stickyDate->dietaryTags()->syncWithoutDetaching([$tags['Vegetarian']->dietary_tag_id]);
        $items['stickyDate'] = $stickyDate;

        // 9. Beer - Carlton Draught (Bar)
        $beer = MenuItem::firstOrCreate(
            ['item_name' => 'Carlton Draught Fresh Tank Beer'],
            [
                'category_id' => $categories['Beers & Ciders']->category_id,
                'description' => 'Unpasteurised brewery-fresh tank beer poured cold from the tap. Clean, crisp and refreshing.',
                'destination' => Destination::Bar->value,
                'prep_minutes' => 3,
                'is_available' => true,
                'is_featured' => false,
                'daily_limit' => null,
                'sold_today' => 28,
            ]
        );
        $this->attachSizes($beer, [
            ['size_name' => 'Schooner 425ml', 'price' => 9.50, 'order' => 1],
            ['size_name' => 'Pint 570ml', 'price' => 12.50, 'order' => 2],
            ['size_name' => 'Jug 1140ml', 'price' => 24.00, 'order' => 3],
        ]);
        $beer->allergens()->syncWithoutDetaching([$allergens['Gluten']->allergen_id]);
        $items['beer'] = $beer;

        // 10. Wine - Barossa Shiraz (Bar)
        $wine = MenuItem::firstOrCreate(
            ['item_name' => 'Barossa Valley Shiraz 2022'],
            [
                'category_id' => $categories['Wines & Cocktails']->category_id,
                'description' => 'Full-bodied South Australian Shiraz brimming with ripe plum, dark berries, vanillin oak and subtle pepper spice.',
                'destination' => Destination::Bar->value,
                'prep_minutes' => 3,
                'is_available' => true,
                'is_featured' => false,
                'daily_limit' => null,
                'sold_today' => 12,
            ]
        );
        $this->attachSizes($wine, [
            ['size_name' => 'Glass 150ml', 'price' => 13.00, 'order' => 1],
            ['size_name' => 'Glass 250ml', 'price' => 19.50, 'order' => 2],
            ['size_name' => 'Bottle 750ml', 'price' => 54.00, 'order' => 3],
        ]);
        $wine->allergens()->syncWithoutDetaching([$allergens['Sulphur Dioxide']->allergen_id]);
        $wine->dietaryTags()->syncWithoutDetaching([$tags['Vegan']->dietary_tag_id]);
        $items['wine'] = $wine;

        // 11. Low stock item: Fresh Barramundi Fillet (Remaining 2 < 2 * buffer)
        $barramundi = MenuItem::firstOrCreate(
            ['item_name' => 'Pan-Seared Humpty Doo Barramundi'],
            [
                'category_id' => $categories['Mains & Classics']->category_id,
                'description' => 'Crispy skin wild barramundi on saffron mash with roasted asparagus and lemon caper beurre blanc.',
                'destination' => Destination::Kitchen->value,
                'prep_minutes' => 16,
                'is_available' => true,
                'is_featured' => false,
                'daily_limit' => 15,
                'sold_today' => 13, // 2 remaining -> low stock widget triggers!
                'calories_kcal' => 580,
                'protein_g' => 52,
                'carbohydrates_g' => 20,
                'fat_g' => 30,
            ]
        );
        $this->attachSizes($barramundi, [
            ['size_name' => 'Regular', 'price' => 34.00, 'order' => 1],
        ]);
        $barramundi->allergens()->syncWithoutDetaching([$allergens['Fish']->allergen_id, $allergens['Milk']->allergen_id]);
        $barramundi->dietaryTags()->syncWithoutDetaching([$tags['Gluten-Free']->dietary_tag_id]);
        $items['barramundi'] = $barramundi;

        // 12. Sold out item: BBQ Pork Ribs (is_available = false)
        $ribs = MenuItem::firstOrCreate(
            ['item_name' => 'Slow-Smoked BBQ Pork Ribs'],
            [
                'category_id' => $categories['Steaks & Grills']->category_id,
                'description' => 'Smoked full rack of pork ribs glazed in Kentucky bourbon barbecue glaze, with apple slaw and onion rings.',
                'destination' => Destination::Kitchen->value,
                'prep_minutes' => 15,
                'is_available' => false, // Sold out toggle
                'is_featured' => false,
                'daily_limit' => 20,
                'sold_today' => 20,
                'calories_kcal' => 1150,
                'protein_g' => 65,
                'carbohydrates_g' => 45,
                'fat_g' => 68,
            ]
        );
        $this->attachSizes($ribs, [
            ['size_name' => 'Full Rack', 'price' => 39.50, 'order' => 1],
        ]);
        $ribs->allergens()->syncWithoutDetaching([$allergens['Soybeans']->allergen_id, $allergens['Mustard']->allergen_id]);
        $items['ribs'] = $ribs;

        return $items;
    }

    private function attachSizes(MenuItem $item, array $sizes): void
    {
        foreach ($sizes as $s) {
            MenuItemSize::firstOrCreate(
                ['item_id' => $item->item_id, 'size_name' => $s['size_name']],
                [
                    'price' => $s['price'],
                    'sale_price' => $s['sale_price'] ?? null,
                    'sale_starts_at' => $s['sale_starts_at'] ?? null,
                    'sale_ends_at' => $s['sale_ends_at'] ?? null,
                    'display_order' => $s['order'] ?? 1,
                    'is_active' => true,
                ]
            );
        }
    }

    private function attachOptions(AddOnGroup $group, array $options): void
    {
        foreach ($options as $idx => $opt) {
            AddOnOption::firstOrCreate(
                ['group_id' => $group->group_id, 'option_name' => $opt['name']],
                [
                    'price_delta' => $opt['delta'],
                    'display_order' => $idx + 1,
                    'is_available' => true,
                    'is_active' => true,
                ]
            );
        }
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
        ];

        $customers = [];
        foreach ($profiles as $key => $p) {
            $customers[$key] = Customer::firstOrCreate(
                ['email' => $p['email']],
                [
                    'full_name' => $p['name'],
                    'phone' => $p['phone'],
                    'password_hash' => 'password',
                    'email_verified_at' => Carbon::now()->subMonths(2),
                ]
            );
        }

        return $customers;
    }

    private function seedReservationsAndVisits(
        array $customers,
        $tables,
        $slots,
        Staff $admin,
        Staff $waiter
    ): void {
        $slot1200 = $slots['12:00'] ?? SlotCapacity::first();
        $slot1830 = $slots['18:30'] ?? SlotCapacity::skip(1)->first();
        $slot1900 = $slots['19:00'] ?? SlotCapacity::skip(2)->first();

        // 1. Historical completed reservations (gives Jack & Sarah 'Regular' trust badge: >= 3 visits)
        for ($i = 1; $i <= 4; $i++) {
            $date = Carbon::today()->subDays($i * 6);
            $res = Reservation::firstOrCreate(
                ['reference_code' => 'CR-H00'.$i],
                [
                    'customer_id' => $customers['jack']->customer_id,
                    'slot_id' => $slot1830->slot_id,
                    'party_size' => 4,
                    'booking_date' => $date->toDateString(),
                    'booking_time' => '18:30:00',
                    'status' => ReservationStatus::Completed->value,
                    'reviewed_by_staff_id' => $admin->staff_id,
                    'reviewed_at' => $date->copy()->subDays(1),
                    'seated_at' => $date->copy()->setTime(18, 35),
                    'completed_at' => $date->copy()->setTime(20, 15),
                    'created_at' => $date->copy()->subDays(2),
                ]
            );
            Visit::firstOrCreate(
                ['reservation_id' => $res->reservation_id, 'table_id' => $tables['T3']->table_id],
                [
                    'guest_count' => 4,
                    'opened_at' => $date->copy()->setTime(18, 35),
                    'closed_at' => $date->copy()->setTime(20, 15),
                    'opened_by_staff_id' => $waiter->staff_id,
                    'closed_by_staff_id' => $waiter->staff_id,
                    'close_reason' => VisitCloseReason::StaffClear->value,
                ]
            );
        }

        for ($i = 1; $i <= 3; $i++) {
            $date = Carbon::today()->subDays($i * 7);
            $res = Reservation::firstOrCreate(
                ['reference_code' => 'CR-S00'.$i],
                [
                    'customer_id' => $customers['sarah']->customer_id,
                    'slot_id' => $slot1900->slot_id,
                    'party_size' => 2,
                    'booking_date' => $date->toDateString(),
                    'booking_time' => '19:00:00',
                    'status' => ReservationStatus::Completed->value,
                    'reviewed_by_staff_id' => $waiter->staff_id,
                    'reviewed_at' => $date->copy()->subDays(2),
                    'seated_at' => $date->copy()->setTime(19, 05),
                    'completed_at' => $date->copy()->setTime(20, 30),
                    'created_at' => $date->copy()->subDays(3),
                ]
            );
            Visit::firstOrCreate(
                ['reservation_id' => $res->reservation_id, 'table_id' => $tables['T1']->table_id],
                [
                    'guest_count' => 2,
                    'opened_at' => $date->copy()->setTime(19, 05),
                    'closed_at' => $date->copy()->setTime(20, 30),
                    'opened_by_staff_id' => $waiter->staff_id,
                    'closed_by_staff_id' => $waiter->staff_id,
                    'close_reason' => VisitCloseReason::StaffClear->value,
                ]
            );
        }

        // 2. David: Has uncleared no-show -> 'Flagged' trust badge
        $noShowDate = Carbon::today()->subDays(8);
        Reservation::firstOrCreate(
            ['reference_code' => 'CR-NOSHOW1'],
            [
                'customer_id' => $customers['david']->customer_id,
                'slot_id' => $slot1830->slot_id,
                'party_size' => 6,
                'booking_date' => $noShowDate->toDateString(),
                'booking_time' => '18:30:00',
                'status' => ReservationStatus::NoShow->value,
                'reviewed_by_staff_id' => $admin->staff_id,
                'reviewed_at' => $noShowDate->copy()->subDay(),
                'no_show_at' => $noShowDate->copy()->setTime(18, 50),
                'no_show_by_staff_id' => $waiter->staff_id,
                'created_at' => $noShowDate->copy()->subDays(3),
            ]
        );

        // 3. Emily: Cleared no-show (restored standing)
        $clearedDate = Carbon::today()->subDays(15);
        Reservation::firstOrCreate(
            ['reference_code' => 'CR-CLEARED1'],
            [
                'customer_id' => $customers['emily']->customer_id,
                'slot_id' => $slot1900->slot_id,
                'party_size' => 4,
                'booking_date' => $clearedDate->toDateString(),
                'booking_time' => '19:00:00',
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

        // 4. Liam: Late cancellation (< 2 hours before booking)
        $cancelDate = Carbon::today()->subDays(4);
        Reservation::firstOrCreate(
            ['reference_code' => 'CR-LATECAN1'],
            [
                'customer_id' => $customers['liam']->customer_id,
                'slot_id' => $slot1830->slot_id,
                'party_size' => 4,
                'booking_date' => $cancelDate->toDateString(),
                'booking_time' => '18:30:00',
                'status' => ReservationStatus::Cancelled->value,
                'cancelled_by' => 'customer',
                'cancelled_at' => $cancelDate->copy()->setTime(17, 10),
                'is_late_cancellation' => true,
                'created_at' => $cancelDate->copy()->subDays(1),
            ]
        );

        // 5. Today's Seated Reservation (Table T4 Occupied)
        $resTodaySeated = Reservation::firstOrCreate(
            ['reference_code' => 'CR-TDY-SEAT'],
            [
                'customer_id' => $customers['olivia']->customer_id,
                'slot_id' => $slot1200->slot_id,
                'party_size' => 4,
                'booking_date' => Carbon::today()->toDateString(),
                'booking_time' => Carbon::now()->subMinutes(35)->format('H:i:00'),
                'status' => ReservationStatus::Seated->value,
                'reviewed_by_staff_id' => $admin->staff_id,
                'reviewed_at' => Carbon::today()->subDay(),
                'seated_at' => Carbon::now()->subMinutes(35),
                'created_at' => Carbon::today()->subDays(2),
            ]
        );
        $tables['T4']->update(['status' => TableStatus::Occupied->value]);
        Visit::firstOrCreate(
            ['reservation_id' => $resTodaySeated->reservation_id, 'table_id' => $tables['T4']->table_id],
            [
                'guest_count' => 4,
                'opened_at' => Carbon::now()->subMinutes(35),
                'opened_by_staff_id' => $waiter->staff_id,
            ]
        );

        // 6. Today's Unassigned Confirmed Booking inside T-30 (Triggers Dashboard Alert)
        Reservation::firstOrCreate(
            ['reference_code' => 'CR-TDY-T30'],
            [
                'customer_id' => $customers['noah']->customer_id,
                'slot_id' => $slot1830->slot_id,
                'party_size' => 6,
                'booking_date' => Carbon::today()->toDateString(),
                'booking_time' => Carbon::now()->addMinutes(20)->format('H:i:00'),
                'status' => ReservationStatus::Confirmed->value,
                'reviewed_by_staff_id' => $admin->staff_id,
                'reviewed_at' => Carbon::today()->subHours(3),
                'special_requests' => 'Highchair needed for toddler please',
                'created_at' => Carbon::today()->subDays(1),
            ]
        );

        // 7. Today's Pending Request (Widget 6: pending reservation requests)
        Reservation::firstOrCreate(
            ['reference_code' => 'CR-TDY-REQ1'],
            [
                'customer_id' => $customers['mia']->customer_id,
                'slot_id' => $slot1900->slot_id,
                'party_size' => 5,
                'booking_date' => Carbon::today()->toDateString(),
                'booking_time' => '19:00:00',
                'status' => ReservationStatus::Requested->value,
                'special_requests' => 'Window booth preferred if possible',
                'created_at' => Carbon::today()->subHours(2),
            ]
        );

        // 8. Upcoming bookings over next 3 days
        for ($d = 1; $d <= 3; $d++) {
            Reservation::firstOrCreate(
                ['reference_code' => 'CR-UPC-00'.$d],
                [
                    'customer_id' => $customers['cara']->customer_id,
                    'slot_id' => $slot1830->slot_id,
                    'party_size' => 2,
                    'booking_date' => Carbon::today()->addDays($d)->toDateString(),
                    'booking_time' => '18:30:00',
                    'status' => ReservationStatus::Confirmed->value,
                    'reviewed_by_staff_id' => $admin->staff_id,
                    'reviewed_at' => Carbon::now()->subHours(4),
                    'created_at' => Carbon::now()->subHours(6),
                ]
            );
        }
    }

    private function seedOrdersAndPayments(
        array $customers,
        array $items,
        $tables,
        Staff $admin,
        Staff $waiter,
        Staff $kitchen
    ): void {
        // A. Orders on same weekday last week (to populate Widget 1 "previous" comparison!)
        $lastWeekDate = Carbon::today()->subWeek();
        for ($i = 1; $i <= 5; $i++) {
            $placed = $lastWeekDate->copy()->setTime(12 + $i, 15);
            $order = Order::firstOrCreate(
                ['order_number' => 'ORD-LW-'.$i],
                [
                    'table_id' => $tables['T'.$i]->table_id,
                    'customer_id' => $customers['jack']->customer_id,
                    'idempotency_key' => 'idem-lw-'.$i,
                    'status' => OrderStatus::Served->value,
                    'payment_status' => PaymentStatus::Paid->value,
                    'total_amount' => 75.50,
                    'gst_amount' => round(75.50 / 11, 2),
                    'placed_at' => $placed,
                    'paid_at' => $placed->copy()->addMinutes(2),
                    'ready_at' => $placed->copy()->addMinutes(18),
                    'served_at' => $placed->copy()->addMinutes(22),
                ]
            );

            // Add Order lines
            $this->createOrderLine($order, 1, $items['parma'], 'Regular', 28.50, 2, OrderItemStatus::Served);
            $this->createOrderLine($order, 2, $items['beer'], 'Pint 570ml', 12.50, 1, OrderItemStatus::Served);
            $this->createOrderLine($order, 3, $items['bread'], 'Regular Loaf', 11.00, 1, OrderItemStatus::Served);

            Payment::firstOrCreate(
                ['order_id' => $order->order_id],
                [
                    'method' => PaymentMethod::Stripe->value,
                    'amount' => 75.50,
                    'status' => PaymentAttemptStatus::Succeeded->value,
                    'stripe_session_id' => 'cs_test_lw_'.$i,
                    'provider_payment_id' => 'pi_test_lw_'.$i,
                    'paid_at' => $placed->copy()->addMinutes(2),
                    'created_at' => $placed,
                ]
            );
        }

        // B. Today's Paid Orders across various hours (populates sales today, hourly distribution, top items!)
        $todayTimes = [
            11 => ['h' => 11, 'm' => 45, 'table' => 'T1', 'cust' => 'jack', 'method' => 'stripe'],
            12 => ['h' => 12, 'm' => 15, 'table' => 'T2', 'cust' => 'sarah', 'method' => 'cash'],
            13 => ['h' => 12, 'm' => 45, 'table' => 'T3', 'cust' => 'emily', 'method' => 'stripe'],
            14 => ['h' => 13, 'm' => 30, 'table' => 'T5', 'cust' => 'liam', 'method' => 'stripe'],
            15 => ['h' => 14, 'm' => 10, 'table' => 'T6', 'cust' => 'david', 'method' => 'cash'],
            16 => ['h' => 17, 'm' => 40, 'table' => 'T1', 'cust' => 'olivia', 'method' => 'stripe'],
            17 => ['h' => 18, 'm' => 10, 'table' => 'T2', 'cust' => 'noah', 'method' => 'cash'],
            18 => ['h' => 18, 'm' => 50, 'table' => 'T4', 'cust' => 'mia', 'method' => 'stripe'],
        ];

        foreach ($todayTimes as $idx => $t) {
            $placed = Carbon::today()->setTime($t['h'], $t['m']);
            $isCash = $t['method'] === 'cash';

            $order = Order::firstOrCreate(
                ['order_number' => 'ORD-TDY-'.$idx],
                [
                    'table_id' => $tables[$t['table']]->table_id,
                    'customer_id' => $customers[$t['cust']]->customer_id,
                    'idempotency_key' => 'idem-tdy-'.$idx,
                    'status' => OrderStatus::Served->value,
                    'payment_status' => PaymentStatus::Paid->value,
                    'total_amount' => 88.50,
                    'gst_amount' => round(88.50 / 11, 2),
                    'placed_at' => $placed,
                    'paid_at' => $placed->copy()->addMinutes(3),
                    'ready_at' => $placed->copy()->addMinutes(16),
                    'served_at' => $placed->copy()->addMinutes(20),
                ]
            );

            $this->createOrderLine($order, 1, $items['parma'], 'Regular', 28.50, 1, OrderItemStatus::Served);
            $this->createOrderLine($order, 2, $items['burger'], 'Regular Single', 24.50, 1, OrderItemStatus::Served);
            $this->createOrderLine($order, 3, $items['calamari'], 'Entree Plate', 18.50, 1, OrderItemStatus::Served);
            $this->createOrderLine($order, 4, $items['beer'], 'Pint 570ml', 12.50, 1, OrderItemStatus::Served);

            if ($isCash) {
                Payment::firstOrCreate(
                    ['order_id' => $order->order_id],
                    [
                        'recorded_by_staff_id' => $waiter->staff_id,
                        'method' => PaymentMethod::Cash->value,
                        'amount' => 88.50,
                        'amount_received' => 90.00,
                        'change_given' => 1.50,
                        'rounding_amount' => 0.00,
                        'status' => PaymentAttemptStatus::Succeeded->value,
                        'paid_at' => $placed->copy()->addMinutes(3),
                        'created_at' => $placed,
                    ]
                );
            } else {
                Payment::firstOrCreate(
                    ['order_id' => $order->order_id],
                    [
                        'method' => PaymentMethod::Stripe->value,
                        'amount' => 88.50,
                        'status' => PaymentAttemptStatus::Succeeded->value,
                        'stripe_session_id' => 'cs_test_tdy_'.$idx,
                        'provider_payment_id' => 'pi_test_tdy_'.$idx,
                        'paid_at' => $placed->copy()->addMinutes(3),
                        'created_at' => $placed,
                    ]
                );
            }
        }

        // C. Operational signal: Cash waiting > 10 min (Widget 12 Needs Attention)
        $cashOrder = Order::firstOrCreate(
            ['order_number' => 'ORD-WAITCASH'],
            [
                'table_id' => $tables['T7']->table_id,
                'customer_id' => $customers['james']->customer_id,
                'idempotency_key' => 'idem-wait-cash',
                'status' => OrderStatus::PendingPayment->value,
                'payment_status' => PaymentStatus::Unpaid->value,
                'total_amount' => 45.00,
                'gst_amount' => round(45.00 / 11, 2),
                'placed_at' => Carbon::now()->subMinutes(18),
            ]
        );
        $this->createOrderLine($cashOrder, 1, $items['steak'], '300g Cut', 38.00, 1, OrderItemStatus::Pending);
        $this->createOrderLine($cashOrder, 2, $items['beer'], 'Schooner 425ml', 7.00, 1, OrderItemStatus::Pending);

        Payment::firstOrCreate(
            ['order_id' => $cashOrder->order_id],
            [
                'method' => PaymentMethod::Cash->value,
                'amount' => 45.00,
                'status' => PaymentAttemptStatus::Pending->value,
                'created_at' => Carbon::now()->subMinutes(18),
            ]
        );

        // D. Operational signal: Open Refund Request (Widget 7 & 12)
        $refundOrder = Order::firstOrCreate(
            ['order_number' => 'ORD-REF-OPEN'],
            [
                'table_id' => $tables['T8']->table_id,
                'customer_id' => $customers['cara']->customer_id,
                'idempotency_key' => 'idem-ref-open',
                'status' => OrderStatus::Served->value,
                'payment_status' => PaymentStatus::Paid->value,
                'total_amount' => 62.50,
                'gst_amount' => round(62.50 / 11, 2),
                'placed_at' => Carbon::now()->subHours(1),
                'paid_at' => Carbon::now()->subHours(1),
            ]
        );
        $lineRefund = $this->createOrderLine($refundOrder, 1, $items['parma'], 'Regular', 28.50, 1, OrderItemStatus::Served);
        $this->createOrderLine($refundOrder, 2, $items['barramundi'], 'Regular', 34.00, 1, OrderItemStatus::Served);

        $payRefund = Payment::firstOrCreate(
            ['order_id' => $refundOrder->order_id],
            [
                'method' => PaymentMethod::Stripe->value,
                'amount' => 62.50,
                'status' => PaymentAttemptStatus::Succeeded->value,
                'stripe_session_id' => 'cs_test_refund_target',
                'provider_payment_id' => 'pi_test_refund_target',
                'paid_at' => Carbon::now()->subHours(1),
            ]
        );

        Refund::firstOrCreate(
            ['order_id' => $refundOrder->order_id, 'reason' => 'Guest reported dietary cross-contact error'],
            [
                'order_item_id' => $lineRefund->order_item_id,
                'payment_id' => $payRefund->payment_id,
                'requested_by_staff_id' => $kitchen->staff_id,
                'method' => RefundMethod::Stripe->value,
                'quantity' => 1,
                'amount' => 28.50,
                'status' => RefundStatus::Requested->value,
                'requested_at' => Carbon::now()->subMinutes(25),
            ]
        );

        // E. Operational signal: Stock Conflict Order (Widget 12 Needs Attention)
        $conflictOrder = Order::firstOrCreate(
            ['order_number' => 'ORD-STK-CONF'],
            [
                'table_id' => $tables['T9']->table_id,
                'customer_id' => $customers['noah']->customer_id,
                'idempotency_key' => 'idem-stk-conf',
                'status' => OrderStatus::Preparing->value,
                'payment_status' => PaymentStatus::Paid->value,
                'has_stock_conflict' => true,
                'total_amount' => 34.00,
                'gst_amount' => round(34.00 / 11, 2),
                'placed_at' => Carbon::now()->subMinutes(12),
                'paid_at' => Carbon::now()->subMinutes(10),
            ]
        );
        $lineConflict = $this->createOrderLine($conflictOrder, 1, $items['barramundi'], 'Regular', 34.00, 1, OrderItemStatus::Preparing);

        Payment::firstOrCreate(
            ['order_id' => $conflictOrder->order_id],
            [
                'method' => PaymentMethod::Stripe->value,
                'amount' => 34.00,
                'status' => PaymentAttemptStatus::Succeeded->value,
                'stripe_session_id' => 'cs_test_stk_conf',
                'provider_payment_id' => 'pi_test_stk_conf',
                'paid_at' => Carbon::now()->subMinutes(10),
            ]
        );
    }

    private function createOrderLine(
        Order $order,
        int $lineNo,
        MenuItem $item,
        string $sizeName,
        float $unitPrice,
        int $qty,
        OrderItemStatus $status = OrderItemStatus::Pending
    ): OrderItem {
        $size = MenuItemSize::where('item_id', $item->item_id)->where('size_name', $sizeName)->first();

        return OrderItem::firstOrCreate(
            ['order_id' => $order->order_id, 'line_no' => $lineNo],
            [
                'item_id' => $item->item_id,
                'size_id' => $size ? $size->size_id : $item->sizes()->first()->size_id,
                'item_name' => $item->item_name,
                'size_name' => $sizeName,
                'destination' => $item->destination,
                'quantity' => $qty,
                'original_unit_price' => $unitPrice,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $qty,
                'status' => $status->value,
            ]
        );
    }

    private function seedFeedback(Staff $admin): void
    {
        // Must seed >= 10 non-hidden reviews so the Homepage rating summary card displays (TC--01)
        $orders = Order::where('status', OrderStatus::Served->value)->get();
        if ($orders->count() < 10) {
            return;
        }

        $reviewsData = [
            // 3 Featured 5-star reviews for homepage carousel
            ['food' => 5, 'serv' => 5, 'feat' => true, 'comment' => 'Hands down the best Chicken Parma in the northern suburbs! The crisp panko coating and rich Napoli sauce were sensational.'],
            ['food' => 5, 'serv' => 5, 'feat' => true, 'comment' => 'Outstanding atmosphere, prompt QR table ordering, and the Wagyu cheeseburger was cooked to perfection.'],
            ['food' => 5, 'serv' => 5, 'feat' => true, 'comment' => 'We hosted our family reunion here. Staff were wonderful with the children and the steak was mouth-watering!'],
            // Standard positive/constructive reviews
            ['food' => 5, 'serv' => 4, 'feat' => false, 'comment' => 'Great cold beers on tap and generous portions. Loved the easy payment through the phone.'],
            ['food' => 4, 'serv' => 5, 'feat' => false, 'comment' => 'Salt & pepper calamari was melt-in-the-mouth tender. Very friendly waitstaff.'],
            ['food' => 4, 'serv' => 4, 'feat' => false, 'comment' => 'Very solid pub meal. Quick service even on a busy Friday night.'],
            ['food' => 5, 'serv' => 4, 'feat' => false, 'comment' => 'Truffle mushroom risotto is a must-try for vegetarians! Packed with flavor.'],
            ['food' => 4, 'serv' => 5, 'feat' => false, 'comment' => 'Delightful desserts and great coffee. Will definitely be returning.'],
            ['food' => 3, 'serv' => 4, 'feat' => false, 'comment' => 'Food was decent, though chips could have been slightly crunchier. Service was pleasant.'],
            ['food' => 4, 'serv' => 3, 'feat' => false, 'comment' => 'Lovely steak, though drinks took about 15 minutes during peak rush.'],
            // Review with admin reply
            [
                'food' => 4,
                'serv' => 4,
                'feat' => false,
                'comment' => 'Good family dining experience. Plenty of high chairs and spacious outdoor tables.',
                'reply' => 'Thank you for dining with us! We look forward to welcoming you and your family back soon.',
            ],
            // Hidden abusive/spam review (is_hidden = true)
            [
                'food' => 1,
                'serv' => 1,
                'feat' => false,
                'hidden' => true,
                'reason' => 'Defamatory profanity and off-site spam link',
                'comment' => 'Absolute trash visit our gambling site at spam-link.xyz for free bonus codes!!!',
            ],
        ];

        foreach ($reviewsData as $i => $data) {
            if (! isset($orders[$i])) {
                break;
            }
            $order = $orders[$i];

            Feedback::firstOrCreate(
                ['order_id' => $order->order_id],
                [
                    'customer_id' => $order->customer_id ?? 1,
                    'food_rating' => $data['food'],
                    'service_rating' => $data['serv'],
                    'comment' => $data['comment'],
                    'is_featured' => $data['feat'] ?? false,
                    'is_hidden' => $data['hidden'] ?? false,
                    'hidden_reason' => $data['reason'] ?? null,
                    'admin_reply' => $data['reply'] ?? null,
                    'replied_by_staff_id' => isset($data['reply']) ? $admin->staff_id : null,
                    'replied_at' => isset($data['reply']) ? Carbon::now()->subHours(2) : null,
                    'submitted_at' => Carbon::now()->subDays(max(1, $i * 2)),
                ]
            );
        }
    }

    private function seedAuditLogsAndArchives(Staff $admin, Staff $waiter): void
    {
        // 1. Setting update audit logs
        AuditLog::firstOrCreate(
            ['action_type' => 'setting_update', 'entity_name' => 'setting'],
            [
                'staff_id' => $admin->staff_id,
                'entity_id' => 1,
                'details' => ['key' => 'qr_ordering_enabled', 'old' => false, 'new' => true],
                'ip_address' => '127.0.0.1',
                'logged_at' => Carbon::now()->subDays(3),
            ]
        );

        // 2. Table status override
        AuditLog::firstOrCreate(
            ['action_type' => 'table_override', 'entity_name' => 'restaurant_table'],
            [
                'staff_id' => $waiter->staff_id,
                'entity_id' => 1,
                'details' => ['table' => 'T1', 'from' => 'available', 'to' => 'reserved', 'reason' => 'VIP walk-in party hold'],
                'ip_address' => '127.0.0.1',
                'logged_at' => Carbon::now()->subDays(2),
            ]
        );

        // 3. AI assistant usage requests (Populates AI Usage Report)
        for ($i = 1; $i <= 6; $i++) {
            $tokensIn = rand(250, 600);
            $tokensOut = rand(80, 220);
            AuditLog::firstOrCreate(
                ['action_type' => 'ai_request', 'entity_name' => 'ai', 'entity_id' => $i],
                [
                    'customer_id' => 1,
                    'details' => [
                        'feature' => $i % 2 === 0 ? 'meal_builder' : 'chat',
                        'tokens_in' => $tokensIn,
                        'tokens_out' => $tokensOut,
                        'total_tokens' => $tokensIn + $tokensOut,
                        'prompt_preview' => 'What gluten free dishes do you recommend?',
                    ],
                    'ip_address' => '127.0.0.1',
                    'logged_at' => Carbon::now()->subHours($i * 4),
                ]
            );
        }

        // 4. Archive snapshot for historical data management
        $archiveLog = AuditLog::firstOrCreate(
            ['action_type' => 'menu_item_archive', 'entity_name' => 'menu_item'],
            [
                'staff_id' => $admin->staff_id,
                'entity_id' => 999,
                'details' => ['reason' => 'Seasonal menu rotation Q2'],
                'ip_address' => '127.0.0.1',
                'logged_at' => Carbon::now()->subWeeks(2),
            ]
        );

        HistoricalDataManagement::firstOrCreate(
            ['log_id' => $archiveLog->log_id],
            [
                'entity_name' => 'menu_item',
                'record_id' => '999',
                'record_data' => json_encode([
                    'item_name' => 'Slow Roasted Lamb Shank',
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
