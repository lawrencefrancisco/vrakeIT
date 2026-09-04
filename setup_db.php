<?php
// setup_db.php
require_once __DIR__ . '/includes/db.php';

$db = getDB();

try {
    // 0. Temporarily disable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 1. Wipe old tables clean (including the newer ones preventing the drop)
    $db->exec("DROP TABLE IF EXISTS vouchers, merchant_rewards, merchants, good_citizen_transactions, rewards, id_verifications, reports, announcements, users");

    // 1.5 Turn foreign key checks back on
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    // 2. Rebuild the USERS table
    $db->exec("CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(20),
        password VARCHAR(255) NOT NULL,
        points INT DEFAULT 0,
        account_verified BOOLEAN DEFAULT FALSE,
        role VARCHAR(20) DEFAULT 'user',
        avatar VARCHAR(255) DEFAULT 'default.png',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. Rebuild other essential tables used in your app
    $db->exec("CREATE TABLE announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150),
        content TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        reference_number VARCHAR(50),
        flow_label VARCHAR(50),
        status VARCHAR(20) DEFAULT 'pending',
        is_injured BOOLEAN,
        location_address TEXT,
        incident_date DATE,
        incident_time TIME,
        has_other_parties BOOLEAN,
        weather_condition VARCHAR(50),
        road_condition VARCHAR(50),
        insurance_type VARCHAR(50),
        media_urls TEXT,
        event_details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE rewards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(50),
        business_name VARCHAR(100),
        reward_name VARCHAR(100),
        description TEXT,
        points_required INT,
        is_active BOOLEAN DEFAULT TRUE
    )");

    $db->exec("CREATE TABLE merchants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        business_name VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(20),
        password VARCHAR(255) NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE merchant_rewards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        merchant_id INT,
        reward_name VARCHAR(100),
        description TEXT,
        points_required INT,
        category VARCHAR(255),
        quantity INT NULL,
        redeemed_count INT DEFAULT 0,
        expires_at DATETIME NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE vouchers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        merchant_id INT,
        reward_id INT,
        voucher_code VARCHAR(50) NOT NULL,
        status VARCHAR(20) DEFAULT 'active',
        expires_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE good_citizen_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        description VARCHAR(255),
        type ENUM('earned', 'spent'),
        points INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE id_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 4. Load auto-saved users from the Memory Bank
    $backupFile = __DIR__ . '/users_backup.json';
    $savedUsers = [];
    if (file_exists($backupFile)) {
        $savedUsers = json_decode(file_get_contents($backupFile), true);
    }

    // 5. Hardcoded Admin/Test Account
    $defaultUsers = [
        [
            'first'    => 'Admin', 
            'last'     => 'User', 
            'email'    => 'admin@vrakeit.com', 
            'phone'    => '09123456789',
            'password' => password_hash('password123', PASSWORD_DEFAULT), 
            'points'   => 5000, 
            'verified' => 1,
            'role'     => 'admin'
        ]
    ];

    $allUsersToInsert = array_merge($defaultUsers, $savedUsers);

    // 6. Insert all users back into the database
    $stmt = $db->prepare("INSERT IGNORE INTO users (first_name, last_name, email, phone, password, points, account_verified, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($allUsersToInsert as $person) {
        $stmt->execute([
            $person['first'], 
            $person['last'], 
            $person['email'], 
            $person['phone'],
            $person['password'], 
            $person['points'] ?? 0, 
            $person['verified'] ?? 0,
            $person['role'] ?? 'user'
        ]);
    }

    echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px;'>";
    echo "<h1>✅ Database Reset Successful!</h1>";
    echo "<p>All tables created. Saved users have been restored.</p>";
    echo "<a href='index.php' style='display: inline-block; padding: 10px 20px; background: #007ED2; color: white; text-decoration: none; border-radius: 8px;'>Go to Login</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<h2>Error setting up database:</h2><p>" . $e->getMessage() . "</p>";
}
?>