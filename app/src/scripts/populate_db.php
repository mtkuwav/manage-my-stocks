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
    ['name' => 'Home'],
    ['name' => 'Garden'],
    ['name' => 'Automotive'],
    ['name' => 'Toys'],
    ['name' => 'Beauty'],
    ['name' => 'Food']
];

$categoryIds = [];
foreach ($categories as $category) {
    $response = makeRequest('POST', '/categories', $category, $token);
    if (isset($response['id'])) {
        $categoryIds[$category['name']] = $response['id'];
        echo "Created category: {$category['name']}\n";
    }
}

// 3. Create many users
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
    ],
    [
        'username' => 'manager1',
        'email' => 'manager1@example.com',
        'password' => 'password123'
    ],
    [
        'username' => 'manager2',
        'email' => 'manager2@example.com',
        'password' => 'password123'
    ]
];

$userIds = [];
foreach ($users as $user) {
    $response = makeRequest('POST', '/auth/register', $user);
    if (isset($response['access_token'])) {
        echo "Created user: {$user['username']}\n";
        $userTokens[$user['email']] = $response['access_token'];
    }
}

// 4. Create many products
echo "\nCreating products...\n";
$products = [
    // Electronics
    [
        'name' => 'Laptop Dell XPS 13',
        'description' => 'High-end laptop with 16GB RAM',
        'price' => 1299.99,
        'quantity_in_stock' => 10,
        'category_id' => $categoryIds['Electronics']
    ],
    [
        'name' => 'iPhone 13 Pro',
        'description' => '256GB, Graphite',
        'price' => 999.99,
        'quantity_in_stock' => 15,
        'category_id' => $categoryIds['Electronics']
    ],
    // Books
    [
        'name' => 'The Art of Programming',
        'description' => 'Essential programming book',
        'price' => 49.99,
        'quantity_in_stock' => 25,
        'category_id' => $categoryIds['Books']
    ],
    // Add more products for each category...
];

$productIds = [];
foreach ($products as $product) {
    $response = makeRequest('POST', '/products', $product, $token);
    if (isset($response['id'])) {
        $productIds[] = $response['id'];
        echo "Created product: {$product['name']}\n";
    }
}

// 5. Create orders with different statuses
echo "\nCreating orders...\n";
$orderStatuses = ['processing', 'completed', 'cancelled'];
foreach ($userTokens as $email => $userToken) {
    // Create 3-5 orders per user
    $numOrders = rand(3, 5);
    for ($i = 0; $i < $numOrders; $i++) {
        $order = makeRequest('POST', '/orders', [
            'items' => [
                [
                    'product_id' => $productIds[array_rand($productIds)],
                    'quantity' => rand(1, 3)
                ],
                [
                    'product_id' => $productIds[array_rand($productIds)],
                    'quantity' => rand(1, 3)
                ]
            ]
        ], $userToken);

        if (isset($order['id'])) {
            // Randomly update order status
            $status = $orderStatuses[array_rand($orderStatuses)];
            makeRequest('PATCH', "/orders/{$order['id']}/status", [
                'status' => $status
            ], $token);
            
            echo "Created order for user ($status)\n";

            // If completed, create delivery and maybe returns
            if ($status === 'completed') {
                // Create delivery
                $delivery = makeRequest('POST', '/deliveries', [
                    'order_id' => $order['id']
                ], $token);

                if (isset($delivery['id'])) {
                    // Update delivery status randomly
                    $deliveryStatuses = ['shipped', 'delivered'];
                    $deliveryStatus = $deliveryStatuses[array_rand($deliveryStatuses)];
                    makeRequest('PATCH', "/deliveries/{$delivery['id']}", [
                        'status' => $deliveryStatus
                    ], $token);
                    
                    echo "Created delivery ($deliveryStatus)\n";
                }

                // Randomly create returns (30% chance)
                if (rand(1, 100) <= 30) {
                    $return = makeRequest('POST', '/returns', [
                        'order_item_id' => $order['items'][0]['id'],
                        'quantity_returned' => 1,
                        'reason' => 'Test return'
                    ], $userToken);

                    if (isset($return['id'])) {
                        // Process return randomly
                        $returnStatuses = ['approved', 'rejected'];
                        $returnStatus = $returnStatuses[array_rand($returnStatuses)];
                        makeRequest('PATCH', "/returns/{$return['id']}", [
                            'status' => $returnStatus
                        ], $token);
                        
                        echo "Created return ($returnStatus)\n";
                    }
                }
            }
        }
    }
}

// 6. Create some inventory movements
echo "\nCreating inventory movements...\n";
foreach ($productIds as $productId) {
    $changes = ['+', '-'];
    for ($i = 0; $i < rand(2, 5); $i++) {
        $quantity = $changes[array_rand($changes)] . rand(1, 10);
        makeRequest('PATCH', "/products/$productId", [
            'quantity_in_stock' => $quantity
        ], $token);
    }
    echo "Created inventory movements for product ID: $productId\n";
}

echo "\nDatabase population completed successfully!\n";