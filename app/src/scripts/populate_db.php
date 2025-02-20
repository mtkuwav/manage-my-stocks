<?php

require_once __DIR__ . '/../../vendor/autoload.php';

function makeRequest($method, $endpoint, $data = null, $token = null) {
    $opts = [
        'http' => [
            'method' => $method,
            'header' => "Content-Type: application/json\r\n" .
                        ($token ? "Authorization: Bearer $token\r\n" : ""),
            'content' => $data ? json_encode($data) : null,
            'ignore_errors' => true
        ]
    ];

    $context = stream_context_create($opts);
    $response = file_get_contents("http://localhost$endpoint", false, $context);
    return json_decode($response, true);
}

echo "Starting database population...\n";

// 1. Login as admin
echo "\nLogging in as admin...\n";
$loginResponse = makeRequest('POST', '/auth/login', [
    'email' => 'admin@boutique.com',
    'password' => 'adminpswd'
]);

if (!isset($loginResponse['access_token'])) {
    die("Failed to login as admin\n");
}

$token = $loginResponse['access_token'];
echo "Successfully logged in\n";

// 2. Create categories
echo "\nCreating categories...\n";
$categories = [
    ['name' => 'Electronics'],
    ['name' => 'Books'],
    ['name' => 'Clothing'],
    ['name' => 'Sports'],
    ['name' => 'Home']
];

$categoryIds = [];
foreach ($categories as $category) {
    $response = makeRequest('POST', '/categories', $category, $token);
    if (isset($response['id'])) {
        $categoryIds[$category['name']] = $response['id'];
        echo "Created category: {$category['name']}\n";
    }
}

// 3. Create products
echo "\nCreating products...\n";
$products = [
    [
        'name' => 'Laptop Dell XPS 13',
        'description' => 'High-end laptop with 16GB RAM',
        'price' => 1299.99,
        'quantity_in_stock' => 10,
        'category_id' => $categoryIds['Electronics']
    ],
    [
        'name' => 'The Art of Programming',
        'description' => 'Essential programming book',
        'price' => 49.99,
        'quantity_in_stock' => 25,
        'category_id' => $categoryIds['Books']
    ],
    [
        'name' => 'Winter Jacket',
        'description' => 'Warm winter jacket',
        'price' => 89.99,
        'quantity_in_stock' => 50,
        'category_id' => $categoryIds['Clothing']
    ],
    [
        'name' => 'Tennis Racket Pro',
        'description' => 'Professional tennis racket',
        'price' => 199.99,
        'quantity_in_stock' => 15,
        'category_id' => $categoryIds['Sports']
    ],
    [
        'name' => 'Smart LED Bulb',
        'description' => 'WiFi-enabled LED bulb',
        'price' => 29.99,
        'quantity_in_stock' => 100,
        'category_id' => $categoryIds['Home']
    ]
];

foreach ($products as $product) {
    $response = makeRequest('POST', '/products', $product, $token);
    if (isset($response['id'])) {
        echo "Created product: {$product['name']}\n";
    }
}

// 4. Create additional users
echo "\nCreating users...\n";
$users = [
    [
        'username' => 'john.doe',
        'email' => 'john.doe@example.com',
        'password' => 'password123'
    ],
    [
        'username' => 'jane.smith',
        'email' => 'jane.smith@example.com',
        'password' => 'password123'
    ]
];

foreach ($users as $user) {
    $response = makeRequest('POST', '/auth/register', $user);
    if (isset($response['id'])) {
        echo "Created user: {$user['username']}\n";
    }
}

echo "\nDatabase population completed!\n";