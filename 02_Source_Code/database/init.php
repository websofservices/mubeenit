<?php
require_once __DIR__ . '/../config/database.php';

$db = getDB();
$sql = file_get_contents(__DIR__ . '/schema.sql');
$db->exec($sql);

$check = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($check > 0) {
    echo "Database already seeded.\n";
    exit(0);
}

$hash = password_hash('password123', PASSWORD_DEFAULT);

$db->prepare("INSERT INTO users (name, email, password, role, phone, city) VALUES (?,?,?,?,?,?)")
   ->execute(['System Admin', 'admin@mtnmangoes.com', $hash, 'admin', '03001234567', 'Muzaffargarh']);

$farmers = [
    ['Ali Hassan', 'ali@farmer.com', '03011112222', 'Multan'],
    ['Fatima Bibi', 'fatima@farmer.com', '03023334444', 'Rahim Yar Khan'],
    ['Muhammad Khan', 'khan@farmer.com', '03035556666', 'Bahawalpur'],
];
foreach ($farmers as $f) {
    $db->prepare("INSERT INTO users (name, email, password, role, phone, city) VALUES (?,?,?,'farmer',?,?)")
       ->execute([$f[0], $f[1], $hash, $f[2], $f[3]]);
}

$customers = [
    ['Ahmed Raza', 'ahmed@customer.com', '03047778888', 'Lahore'],
    ['Sara Ali', 'sara@customer.com', '03059990000', 'Islamabad'],
];
foreach ($customers as $c) {
    $db->prepare("INSERT INTO users (name, email, password, role, phone, city) VALUES (?,?,?,'customer',?,?)")
       ->execute([$c[0], $c[1], $hash, $c[2], $c[3]]);
}

$products = [
    [2, 'Chaunsa Mango', 'Chaunsa', 'Premium sweet Chaunsa mangoes from Multan. Famous for rich flavor and aroma.', 450, 500, 'kg'],
    [2, 'Sindhri Mango', 'Sindhri', 'Large, juicy Sindhri mangoes. Excellent for eating fresh.', 380, 300, 'kg'],
    [2, 'Anwar Ratol', 'Anwar Ratol', 'Small, extremely sweet Anwar Ratol. Highly sought after.', 550, 150, 'kg'],
    [3, 'Langra Mango', 'Langra', 'Green-skinned Langra with unique taste. Early season variety.', 320, 400, 'kg'],
    [3, 'Dusehri Mango', 'Dusehri', 'Aromatic Dusehri mangoes. Perfect balance of sweetness.', 400, 250, 'kg'],
    [3, 'Fajri Mango', 'Fajri', 'Late season Fajri. Large size and good shelf life.', 280, 200, 'kg'],
    [4, 'White Chaunsa', 'White Chaunsa', 'Premium White Chaunsa. Soft texture and exceptional sweetness.', 480, 180, 'kg'],
    [4, 'Black Chaunsa', 'Black Chaunsa', 'Dark-skinned Black Chaunsa. Intense flavor.', 520, 120, 'kg'],
    [4, 'Mixed Box (5kg)', 'Assorted', 'Assorted premium mangoes (5kg box). Great gift option.', 2200, 50, 'box'],
];

$stmt = $db->prepare("INSERT INTO products (farmer_id, name, variety, description, price, stock, unit) VALUES (?,?,?,?,?,?,?)");
foreach ($products as $p) {
    $stmt->execute($p);
}

echo "✓ Database initialized & seeded successfully!\n\n";
echo "Demo Login Credentials (password for all: password123)\n";
echo "-----------------------------------------------------\n";
echo "Admin:    admin@mtnmangoes.com\n";
echo "Farmer:   ali@farmer.com | fatima@farmer.com | khan@farmer.com\n";
echo "Customer: ahmed@customer.com | sara@customer.com\n";
