<?php
/**
 * LAVENDER’S GLAM STUDIO — Premium Professional Makeup Artist Portal
 * Main Server-Side Application Controller (PHP + MySQL + HTML/CSS/JS)
 * Security Optimized with PDO Prepared Statements & Transactional Integrity
 */

// 1. Session & Environment Configuration
session_start();

// Initialize session agenda array if not already present
if (!isset($_SESSION['agenda'])) {
    $_SESSION['agenda'] = [];
}

// Helper to get environment variables with blanks/defaults
function get_env_var($key, $default = '') {
    if (getenv($key) !== false) {
        return getenv($key);
    }
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    static $env = null;
    if ($env === null) {
        $env = [];
        $path = __DIR__ . '/.env';
        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $name = trim($parts[0]);
                    $value = trim($parts[1]);
                    $env[$name] = trim($value, '"\'');
                }
            }
        }
    }
    return isset($env[$key]) ? $env[$key] : $default;
}

// 2. Secure PDO Database Connection & Auto-Initialization
function get_db_connection() {
    $host = get_env_var('DB_HOST', '127.0.0.1');
    $port = get_env_var('DB_PORT', '3306');
    $dbname = get_env_var('DB_NAME', 'lavender_glam_db');
    $user = get_env_var('DB_USER', 'root');
    $password = get_env_var('DB_PASS', '');

    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        seed_database_if_empty($pdo);
        return $pdo;
    } catch (PDOException $e) {
        // If database doesn't exist, create it and run schema
        if ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
            try {
                $dsn_no_db = "mysql:host=$host;port=$port;charset=utf8mb4";
                $pdo_no_db = new PDO($dsn_no_db, $user, $password);
                $pdo_no_db->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                // Select newly created DB
                $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);

                // Run schema.sql
                $schema_path = __DIR__ . '/database/schema.sql';
                if (file_exists($schema_path)) {
                    $sql = file_get_contents($schema_path);
                    // Standard parser splits by semicolon + newline
                    $queries = preg_split('/;\s*$/m', $sql);
                    foreach ($queries as $query) {
                        $query = trim($query);
                        if (!empty($query)) {
                            $pdo->exec($query);
                        }
                    }
                }
                seed_database_if_empty($pdo);
                return $pdo;
            } catch (PDOException $e2) {
                throw new Exception("Database autogenesis failed: " . $e2->getMessage());
            }
        } else {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
}

// Seed services, slots, and default admin credentials if database is empty
function seed_database_if_empty($pdo) {
    // 1. Seed time slots
    $slots_count = $pdo->query("SELECT COUNT(*) FROM time_slots")->fetchColumn();
    if ($slots_count == 0) {
        $slots = [
            ['08:30:00', 1],
            ['11:00:00', 1],
            ['13:30:00', 1],
            ['16:00:00', 1],
            ['18:30:00', 1],
            ['21:00:00', 1],
        ];
        $ins = $pdo->prepare("INSERT INTO time_slots (slot_time, max_capacity) VALUES (?, ?)");
        foreach ($slots as $slot) {
            $ins->execute($slot);
        }
    }

    // 2. Seed default admin credentials
    $admin_count = $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
    if ($admin_count == 0) {
        $password_hash = password_hash('admin123', PASSWORD_BCRYPT);
        $ins = $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES ('admin', ?)");
        $ins->execute([$password_hash]);
    }

    // 3. Seed dynamic services
    $services_count = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
    if ($services_count == 0) {
        $services = [
            [
                'bridal-couture',
                'Bridal Editorial Couture',
                'Bridal Signature',
                'The ultimate luxury bridal look designed for modern couture brides. Includes pre-wedding consult, premium 3D silk lashes, absolute high-definition airbrushing, skin-prep detailing, and a bespoke long-lasting glow formula.',
                15000.00,
                180,
                'both',
                'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?q=80&w=600&auto=format&fit=crop'
            ],
            [
                'red-carpet-glam',
                'Celebrity & Red Carpet Glam',
                'Signature Glam',
                'Camera-ready, flawless red carpet look optimized for photographic studio flash and film. Features custom eye artistry, facial contour sculpting, high-end radiant finish, and custom lip designs using ultra-premium couture cosmetics.',
                10000.00,
                120,
                'both',
                'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?q=80&w=600&auto=format&fit=crop'
            ],
            [
                'fashion-editorial',
                'Fashion Editorial & Runway',
                'Editorial',
                'High-concept, trendsetting makeup designs for fashion publications, runways, and conceptual shoots. Adapts from clean high-fashion skin glow to avant-garde pigment sprays according to director guidelines.',
                12000.00,
                150,
                'both',
                'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?q=80&w=600&auto=format&fit=crop'
            ],
            [
                'luxury-evening',
                'Luxury Evening Soirée',
                'Evening Glam',
                'Elegant and sophisticated evening glam perfect for formal galas, private dinners, and luxury parties. Combines classy soft smokey eyes with clean skin and premium lashes to make you stand out beautifully.',
                8000.00,
                90,
                'both',
                'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?q=80&w=600&auto=format&fit=crop'
            ],
            [
                'one-on-one-masterclass',
                '1-on-1 Personal Glam Masterclass',
                'Private Education',
                'Bespoke private education session where you sit 1-on-1 with the master artist. Master correct facial mappings, personalized color theories, custom eyebrow structures, and transitioning from clean day skin to evening party glow.',
                18000.00,
                240,
                'both',
                'https://images.unsplash.com/photo-1596462502278-27bfdc403348?q=80&w=600&auto=format&fit=crop'
            ],
        ];
        $ins = $pdo->prepare("INSERT INTO services (id, title, tag, description, base_price, duration_minutes, location_type, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        foreach ($services as $srv) {
            $ins->execute($srv);
        }
    }
}

// Establish DB connection for early controller workflows
try {
    $pdo = get_db_connection();
} catch (Exception $e) {
    die("Database Initialization Interrupted: " . $e->getMessage());
}

// 3. REST API / Endpoint Router Handler
if (isset($_GET['action'])) {
    $action = trim($_GET['action']);
    
    // API responses must output JSON
    $api_actions = ['check-availability', 'get-agenda', 'add-to-agenda', 'remove-from-agenda', 'submit-booking', 'get-services', 'admin-login', 'admin-update-booking-status', 'admin-save-service', 'admin-delete-service', 'user-register', 'user-login', 'user-logout', 'get-user-state', 'get-user-bookings'];
    if (in_array($action, $api_actions)) {
        header('Content-Type: application/json');
    }

    // User Auth API: Get current logged-in user state
    if ($action === 'get-user-state') {
        if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
            echo json_encode([
                'logged_in' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'name' => $_SESSION['user_name'],
                    'email' => $_SESSION['user_email'],
                    'phone' => $_SESSION['user_phone']
                ]
            ]);
        } else {
            echo json_encode(['logged_in' => false]);
        }
        exit;
    }

    // User Auth API: Register a new profile
    if ($action === 'user-register') {
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($name) || empty($email) || empty($phone) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'All profile fields are required.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email address format.']);
            exit;
        }

        // Duplication Validation
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'This email address is already registered.']);
            exit;
        }

        // Hashing and relational insertion
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $phone, $password_hash]);
        $user_id = $pdo->lastInsertId();

        // Save active context in session
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_phone'] = $phone;

        echo json_encode([
            'status' => 'success',
            'user' => [
                'id' => $user_id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone
            ]
        ]);
        exit;
    }

    // User Auth API: Sign In
    if ($action === 'user-login') {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Email and Password are required.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_phone'] = $user['phone'];

            echo json_encode([
                'status' => 'success',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['full_name'],
                    'email' => $user['email'],
                    'phone' => $user['phone']
                ]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email or password credentials.']);
        }
        exit;
    }

    // User Auth API: Logout
    if ($action === 'user-logout') {
        unset($_SESSION['user_logged_in']);
        unset($_SESSION['user_id']);
        unset($_SESSION['user_name']);
        unset($_SESSION['user_email']);
        unset($_SESSION['user_phone']);
        
        if (isset($_GET['redirect'])) {
            header('Location: index.php');
        } else {
            echo json_encode(['status' => 'success']);
        }
        exit;
    }

    // User Auth API: Retrieve Personal Booking History
    if ($action === 'get-user-bookings') {
        if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
            echo json_encode(['status' => 'error', 'message' => 'Please log in to view booking history.']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT b.*, 
                   GROUP_CONCAT(CONCAT(s.title, ' (@ ', TIME_FORMAT(bi.selected_time, '%h:%i %p'), ')') SEPARATOR ', ') as services
            FROM bookings b
            LEFT JOIN booking_items bi ON b.id = bi.booking_id
            LEFT JOIN services s ON bi.service_id = s.id
            WHERE b.user_id = ?
            GROUP BY b.id
            ORDER BY b.event_date DESC, b.id DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user_bookings = $stmt->fetchAll();

        $formatted_bookings = array_map(function($bk) {
            return [
                'reference' => $bk['booking_reference'],
                'date' => date("D, M d, Y", strtotime($bk['event_date'])),
                'total' => number_format($bk['total_price']) . ' BDT',
                'travel' => number_format($bk['travel_fee']) . ' BDT',
                'status' => $bk['booking_status'],
                'services' => $bk['services'] ?? 'N/A',
                'notes' => $bk['special_notes'] ?? 'None',
                'address' => $bk['event_address'] ?? 'Studio'
            ];
        }, $user_bookings);

        echo json_encode($formatted_bookings);
        exit;
    }

    // A. Fetch Services JSON
    if ($action === 'get-services') {
        $stmt = $pdo->query("SELECT * FROM services WHERE status = 'active'");
        $services = $stmt->fetchAll();
        echo json_encode($services);
        exit;
    }

    // B. Check Booked Slots on Date
    if ($action === 'check-availability') {
        $date = $_GET['date'] ?? '';
        if (empty($date)) {
            echo json_encode([]);
            exit;
        }

        // Fetch selected times for confirmed/pending slots on date
        $stmt = $pdo->prepare("
            SELECT TIME_FORMAT(bi.selected_time, '%h:%i %p') as booked_time
            FROM booking_items bi
            JOIN bookings b ON bi.booking_id = b.id
            WHERE b.event_date = ? AND b.booking_status != 'cancelled'
        ");
        $stmt->execute([$date]);
        $booked_slots = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Normalize time slot formats (remove leading zero for JS matching)
        $normalized = array_map(function($t) {
            return ltrim($t, '0');
        }, $booked_slots);

        echo json_encode($normalized);
        exit;
    }

    // C. Get Session-Backed Agenda
    if ($action === 'get-agenda') {
        $agenda = $_SESSION['agenda'];
        $items = [];
        $subtotal = 0;
        $travel_total = 0;
        $travel_fee_flat = 2000;

        foreach ($agenda as $key => $cart_item) {
            $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
            $stmt->execute([$cart_item['serviceId']]);
            $service = $stmt->fetch();

            if ($service) {
                $is_location = $cart_item['location'] === 'location';
                $price = $service['base_price'] + ($is_location ? $travel_fee_flat : 0);
                
                $items[] = [
                    'key' => $key,
                    'serviceId' => $service['id'],
                    'serviceName' => $service['title'],
                    'serviceTag' => $service['tag'],
                    'price' => (float)$price,
                    'basePrice' => (float)$service['base_price'],
                    'date' => $cart_item['date'],
                    'timeSlot' => $cart_item['timeSlot'],
                    'location' => $cart_item['location'],
                    'duration' => $service['duration_minutes'] . " Min"
                ];

                $subtotal += $service['base_price'];
                if ($is_location) {
                    $travel_total += $travel_fee_flat;
                }
            }
        }

        echo json_encode([
            'items' => $items,
            'subtotal' => $subtotal,
            'travel' => $travel_total,
            'total' => $subtotal + $travel_total
        ]);
        exit;
    }

    // D. Add Customizable Session Agenda Card
    if ($action === 'add-to-agenda') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST;
        }

        $service_id = $data['serviceId'] ?? '';
        $date = $data['date'] ?? '';
        $time_slot = $data['timeSlot'] ?? ''; // Format: "11:00 AM" or "01:30 PM"
        $location = $data['location'] ?? 'studio';

        if (empty($service_id) || empty($date) || empty($time_slot)) {
            echo json_encode(['status' => 'error', 'message' => 'Please provide complete schedule variables.']);
            exit;
        }

        // Convert slot time e.g., '11:00 AM' -> '11:00:00'
        $time_formatted = date("H:i:s", strtotime($time_slot));

        // Concurrency Validation against MySQL bookings
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM booking_items bi
            JOIN bookings b ON bi.booking_id = b.id
            WHERE b.event_date = ? AND bi.selected_time = ? AND b.booking_status != 'cancelled'
        ");
        $stmt->execute([$date, $time_formatted]);
        $already_booked = $stmt->fetchColumn();

        if ($already_booked > 0) {
            echo json_encode(['status' => 'error', 'message' => 'This slot was recently reserved by another guest. Please choose another time.']);
            exit;
        }

        // Validation against temporary session bookings to prevent self-conflict
        foreach ($_SESSION['agenda'] as $item) {
            if ($item['date'] === $date && $item['timeSlot'] === $time_slot) {
                echo json_encode(['status' => 'error', 'message' => 'You already placed a slot inquiry on this day/time in your Agenda.']);
                exit;
            }
        }

        // Generate a unique session agenda key
        $session_key = uniqid('cart_', true);
        $_SESSION['agenda'][$session_key] = [
            'serviceId' => $service_id,
            'date' => $date,
            'timeSlot' => $time_slot,
            'location' => $location
        ];

        echo json_encode(['status' => 'success', 'count' => count($_SESSION['agenda'])]);
        exit;
    }

    // E. Remove Session Agenda Card
    if ($action === 'remove-from-agenda') {
        $data = json_decode(file_get_contents('php://input'), true);
        $key = $data['key'] ?? '';

        if (isset($_SESSION['agenda'][$key])) {
            unset($_SESSION['agenda'][$key]);
            echo json_encode(['status' => 'success', 'count' => count($_SESSION['agenda'])]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Item not found in agenda.']);
        }
        exit;
    }

    // F. Transactional Database Checkout & Inquiry Submission
    if ($action === 'submit-booking') {
        if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
            echo json_encode(['status' => 'auth_required', 'message' => 'Please sign in or create an account to finalize your booking.']);
            exit;
        }

        $fullName = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $whatsapp = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $skinType = trim($_POST['skin'] ?? '');
        $preference = trim($_POST['preferences'] ?? '');

        if (empty($fullName) || empty($email) || empty($whatsapp)) {
            echo json_encode(['status' => 'error', 'message' => 'Full Name, Email, and WhatsApp number are required.']);
            exit;
        }

        if (empty($_SESSION['agenda'])) {
            echo json_encode(['status' => 'error', 'message' => 'Your Booking Agenda is empty. Please select services first.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $booking_ref = 'LGS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            $subtotal = 0;
            $travel_total = 0;
            $travel_fee_flat = 2000;
            
            // Loop first to fetch prices & dates, validate concurrency, and calculate total
            $bookings_to_insert = [];
            $earliest_date = null;

            foreach ($_SESSION['agenda'] as $cart_item) {
                $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
                $stmt->execute([$cart_item['serviceId']]);
                $service = $stmt->fetch();

                if (!$service) {
                    throw new Exception("Service package does not exist: " . $cart_item['serviceId']);
                }

                $time_formatted = date("H:i:s", strtotime($cart_item['timeSlot']));
                
                // Concurrency DB Lock Check
                $chk = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM booking_items bi
                    JOIN bookings b ON bi.booking_id = b.id
                    WHERE b.event_date = ? AND bi.selected_time = ? AND b.booking_status != 'cancelled'
                ");
                $chk->execute([$cart_item['date'], $time_formatted]);
                if ($chk->fetchColumn() > 0) {
                    throw new Exception("The slot for " . $service['title'] . " on " . $cart_item['date'] . " at " . $cart_item['timeSlot'] . " was already booked.");
                }

                $is_location = $cart_item['location'] === 'location';
                $line_price = $service['base_price'] + ($is_location ? $travel_fee_flat : 0);
                
                $subtotal += $service['base_price'];
                if ($is_location) {
                    $travel_total += $travel_fee_flat;
                }

                if ($earliest_date === null || $cart_item['date'] < $earliest_date) {
                    $earliest_date = $cart_item['date'];
                }

                $bookings_to_insert[] = [
                    'service_id' => $service['id'],
                    'title' => $service['title'],
                    'time_formatted' => $time_formatted,
                    'time_slot' => $cart_item['timeSlot'],
                    'base_price' => $service['base_price'],
                    'location' => $cart_item['location'],
                    'date' => $cart_item['date'],
                    'price' => $line_price
                ];
            }

            $grand_total = $subtotal + $travel_total;

            // Write Master Entry with user_id
            $stmt = $pdo->prepare("
                INSERT INTO bookings (booking_reference, user_id, customer_name, customer_email, customer_phone, event_date, travel_fee, total_price, event_address, skin_profile, special_notes, booking_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $booking_ref,
                $_SESSION['user_id'],
                $fullName,
                $email,
                $whatsapp,
                $earliest_date,
                $travel_total,
                $grand_total,
                $address,
                $skinType,
                $preference
            ]);
            $booking_id = $pdo->lastInsertId();

            // Write Relational Item Lines
            $ins_line = $pdo->prepare("
                INSERT INTO booking_items (booking_id, service_id, selected_time, base_price) 
                VALUES (?, ?, ?, ?)
            ");
            foreach ($bookings_to_insert as $line) {
                $ins_line->execute([
                    $booking_id,
                    $line['service_id'],
                    $line['time_formatted'],
                    $line['base_price']
                ]);
            }

            $pdo->commit();

            // Clear session agenda
            $_SESSION['agenda'] = [];

            // Compile visual receipts for front-end modals
            $receipt_lines = [];
            $whatsapp_text = "🌸 *LAVENDER’S GLAM STUDIO — Bespoke Luxury Makeup Booking* 🌸\n\n";
            $whatsapp_text .= "*Client Details:*\n";
            $whatsapp_text .= "• *Name:* $fullName\n";
            $whatsapp_text .= "• *WhatsApp:* $whatsapp\n";
            $whatsapp_text .= "• *Email:* $email\n";
            if (!empty($skinType)) $whatsapp_text .= "• *Skin Type:* $skinType\n";
            if (!empty($preference)) $whatsapp_text .= "• *Aesthetics:* $preference\n";
            if (!empty($address)) $whatsapp_text .= "• *Venue venue:* $address\n";
            
            $whatsapp_text .= "\n*Reserved Sessions (Ref: $booking_ref):*\n";

            foreach ($bookings_to_insert as $idx => $line) {
                $locationStr = $line['location'] === 'location' ? 'On-Location' : 'In-Studio';
                $whatsapp_text .= ($idx + 1) . ". *{$line['title']}*\n";
                $whatsapp_text .= "   • *Schedule:* " . date("D, M d, Y", strtotime($line['date'])) . " @ {$line['time_slot']}\n";
                $whatsapp_text .= "   • *Location:* $locationStr\n";
                $whatsapp_text .= "   • *Investment:* " . number_format($line['price']) . " BDT\n\n";

                $receipt_lines[] = [
                    'label' => $line['title'] . " (" . $line['time_slot'] . ")",
                    'val' => number_format($line['price']) . " BDT"
                ];
            }

            $whatsapp_text .= "*Financial Breakdown:*\n";
            $whatsapp_text .= "• *Atelier Services Subtotal:* " . number_format($subtotal) . " BDT\n";
            if ($travel_total > 0) {
                $whatsapp_text .= "• *On-Location Surcharge:* " . number_format($travel_total) . " BDT\n";
                $receipt_lines[] = [
                    'label' => 'Travel Surcharges',
                    'val' => '+' . number_format($travel_total) . ' BDT'
                ];
            }
            $whatsapp_text .= "• *Total Investment:* *" . number_format($grand_total) . " BDT*\n\n";
            $whatsapp_text .= "🌸 Please confirm my slot reservations. Thank you!";

            $receipt_lines[] = [
                'label' => 'Bespoke Total Investment',
                'val' => number_format($grand_total) . ' BDT'
            ];

            // Coordinators WhatsApp Redirect URLs
            $wa_number = "+8801974424264";
            $wa_url = "https://api.whatsapp.com/send?phone=" . urlencode($wa_number) . "&text=" . urlencode($whatsapp_text);
            $mailto_url = "mailto:appointments@lavendersglam.com?subject=" . urlencode("Luxury Makeup Appointment Request: $booking_ref") . "&body=" . urlencode(str_replace('*', '', $whatsapp_text));

            echo json_encode([
                'status' => 'success',
                'booking_ref' => $booking_ref,
                'whatsapp_url' => $wa_url,
                'mailto_url' => $mailto_url,
                'receipt_lines' => $receipt_lines
            ]);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Checkout halted: ' . $e->getMessage()]);
            exit;
        }
    }

    // G. Admin Authentication Endpoint
    if ($action === 'admin-login') {
        $user = trim($_POST['username'] ?? '');
        $pass = trim($_POST['password'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->execute([$user]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($pass, $admin['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $admin['username'];
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid administrative credentials.']);
        }
        exit;
    }

    // H. Admin API: Update Booking Status
    if ($action === 'admin-update-booking-status') {
        if (!isset($_SESSION['admin_logged_in'])) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized admin session.']);
            exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) $data = $_POST;

        $id = $data['booking_id'] ?? '';
        $status = $data['status'] ?? '';

        $allowed = ['pending', 'confirmed', 'completed', 'cancelled'];
        if (empty($id) || !in_array($status, $allowed)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid parameters.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE bookings SET booking_status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    // I. Admin API: Save Dynamic Service Package
    if ($action === 'admin-save-service') {
        if (!isset($_SESSION['admin_logged_in'])) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized admin session.']);
            exit;
        }

        $id = trim($_POST['id'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $tag = trim($_POST['tag'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $price = floatval($_POST['base_price'] ?? 0);
        $duration = intval($_POST['duration_minutes'] ?? 0);
        $image = trim($_POST['image_path'] ?? 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?q=80&w=600&auto=format&fit=crop');

        if (empty($title) || empty($desc) || $price <= 0 || $duration <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Complete service variables are required.']);
            exit;
        }

        // Generate Slug ID if empty (creating new service)
        if (empty($id)) {
            $id = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $title));
            $stmt = $pdo->prepare("INSERT INTO services (id, title, tag, description, base_price, duration_minutes, location_type, image_path, status) VALUES (?, ?, ?, ?, ?, ?, 'both', ?, 'active')");
            $stmt->execute([$id, $title, $tag, $desc, $price, $duration, $image]);
        } else {
            $stmt = $pdo->prepare("UPDATE services SET title = ?, tag = ?, description = ?, base_price = ?, duration_minutes = ?, image_path = ? WHERE id = ?");
            $stmt->execute([$title, $tag, $desc, $price, $duration, $image, $id]);
        }

        echo json_encode(['status' => 'success']);
        exit;
    }

    // J. Admin API: Delete Service Package
    if ($action === 'admin-delete-service') {
        if (!isset($_SESSION['admin_logged_in'])) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized admin session.']);
            exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) $data = $_POST;

        $id = $data['id'] ?? '';
        if (empty($id)) {
            echo json_encode(['status' => 'error', 'message' => 'Service ID is required.']);
            exit;
        }

        // Soft archive or complete delete: let's archive it by updating status to inactive
        $stmt = $pdo->prepare("UPDATE services SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success']);
        exit;
    }
}

// Admin logout handler
if (isset($_GET['admin_logout'])) {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_user']);
    header('Location: index.php');
    exit;
}

// 4. Check if Admin Dashboard view requested
$is_admin_dashboard = isset($_GET['action']) && $_GET['action'] === 'admin-dashboard';
$admin_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if ($is_admin_dashboard) {
    if (!$admin_logged_in) {
        // Render Glassmorphic Login Overlay
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Lavender’s Glam Studio — Admin Login</title>
            <link rel="stylesheet" href="main.css">
            <style>
                body {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    padding: 1.5rem;
                }
                .login-card {
                    background: var(--color-surface-glass);
                    backdrop-filter: blur(24px);
                    -webkit-backdrop-filter: blur(24px);
                    border: 1px solid rgba(255, 255, 255, 0.4);
                    border-radius: var(--radius-lg);
                    padding: 3rem;
                    width: 100%;
                    max-width: 420px;
                    box-shadow: var(--shadow-lg);
                    text-align: center;
                }
                .login-logo {
                    font-family: var(--font-serif);
                    font-size: 1.8rem;
                    color: var(--color-surface-dark);
                    margin-bottom: 2rem;
                }
                .login-logo span {
                    color: var(--color-gold);
                }
            </style>
        </head>
        <body>
            <div class="login-card">
                <h2 class="login-logo"><span>Lavender’s</span> Admin</h2>
                <form id="admin-login-form">
                    <div class="form-group" style="text-align: left; margin-bottom: 1.25rem;">
                        <label class="form-label" for="username">Username</label>
                        <input type="text" id="username" class="form-input" style="background:#fff;" required placeholder="e.g. admin">
                    </div>
                    <div class="form-group" style="text-align: left; margin-bottom: 2rem;">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" class="form-input" style="background:#fff;" required placeholder="••••••••">
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%; padding: 0.9rem;">Authenticate</button>
                </form>
            </div>
            
            <script>
                document.getElementById('admin-login-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const u = document.getElementById('username').value.trim();
                    const p = document.getElementById('password').value.trim();
                    
                    const fd = new FormData();
                    fd.append('username', u);
                    fd.append('password', p);
                    
                    fetch('index.php?action=admin-login', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.status === 'success') {
                            window.location.reload();
                        } else {
                            alert(res.message || 'Authentication failed.');
                        }
                    })
                    .catch(err => alert('Request failed. Check credentials.'));
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    // 5. Authenticated Admin Dashboard Layout View
    // Fetch KPI Stats
    $total_revenue = $pdo->query("SELECT SUM(total_price) FROM bookings WHERE booking_status IN ('confirmed', 'completed')")->fetchColumn() ?? 0;
    $pending_count = $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'pending'")->fetchColumn();
    $upcoming_week = $pdo->query("SELECT COUNT(*) FROM bookings WHERE event_date >= CURDATE() AND event_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND booking_status != 'cancelled'")->fetchColumn();

    // Fetch Bookings list
    $stmt = $pdo->query("SELECT * FROM bookings ORDER BY event_date DESC, id DESC");
    $bookings_list = $stmt->fetchAll();

    // Fetch Services List
    $stmt = $pdo->query("SELECT * FROM services WHERE status = 'active' ORDER BY base_price DESC");
    $services_list = $stmt->fetchAll();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Lavender’s Glam Studio — Luxury Admin Console</title>
        <link rel="stylesheet" href="main.css">
        <style>
            body { background: var(--color-bg); padding-bottom: 5rem; }
            .admin-header {
                background: var(--color-surface-dark);
                color: #fff;
                padding: 1.2rem 0;
                margin-bottom: 3rem;
            }
            .admin-header-inner {
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            .admin-title {
                font-family: var(--font-serif);
                font-size: 1.5rem;
                letter-spacing: 0.05em;
            }
            .admin-title span { color: var(--color-gold); }
            .kpi-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 2rem;
                margin-bottom: 3.5rem;
            }
            .kpi-card {
                background: #fff;
                border: 1px solid var(--color-border);
                border-radius: var(--radius-md);
                padding: 1.8rem;
                box-shadow: var(--shadow-sm);
                position: relative;
                overflow: hidden;
            }
            .kpi-card::before {
                content: '';
                position: absolute;
                top: 0; left: 0; width: 100%; height: 4px;
                background: linear-gradient(90deg, var(--color-blush), var(--color-gold));
            }
            .kpi-val {
                font-family: var(--font-serif);
                font-size: 2.2rem;
                font-weight: 700;
                color: var(--color-plum);
                margin-bottom: 0.2rem;
            }
            .kpi-label {
                font-size: 0.72rem;
                font-weight: 700;
                color: var(--color-gold);
                text-transform: uppercase;
                letter-spacing: 0.1em;
            }
            .dashboard-panel {
                background: #fff;
                border: 1px solid var(--color-border);
                border-radius: var(--radius-lg);
                padding: 2.5rem;
                box-shadow: var(--shadow-md);
                margin-bottom: 3.5rem;
            }
            .panel-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 2rem;
                border-bottom: 1px solid var(--color-border);
                padding-bottom: 1rem;
            }
            .panel-title {
                font-family: var(--font-serif);
                font-size: 1.4rem;
                color: var(--color-plum);
            }
            /* Table Styling */
            .admin-table {
                width: 100%;
                border-collapse: collapse;
                text-align: left;
                font-size: 0.88rem;
            }
            .admin-table th {
                font-size: 0.72rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: var(--color-muted);
                padding: 1rem;
                border-bottom: 2px solid var(--color-border);
            }
            .admin-table td {
                padding: 1.2rem 1rem;
                border-bottom: 1px solid var(--color-border);
                vertical-align: middle;
            }
            .admin-table tr:hover {
                background: var(--color-blush-soft);
            }
            .badge {
                padding: 0.35rem 0.7rem;
                border-radius: var(--radius-pill);
                font-size: 0.7rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                display: inline-block;
            }
            .badge-pending { background: #FAF2F0; color: #b97a57; border: 1px solid rgba(185, 122, 87, 0.2); }
            .badge-confirmed { background: #f0f7f2; color: #57b97a; border: 1px solid rgba(87, 185, 122, 0.2); }
            .badge-completed { background: var(--color-blush-soft); color: var(--color-plum); border: 1px solid var(--color-border); }
            .badge-cancelled { background: #fdf0f0; color: #c94c4c; border: 1px solid rgba(201, 76, 76, 0.2); }
            
            .admin-select {
                background: #fff;
                border: 1px solid var(--color-border);
                border-radius: var(--radius-sm);
                padding: 0.4rem;
                font-size: 0.8rem;
                font-family: inherit;
                outline: none;
            }
            .admin-select:focus {
                border-color: var(--color-gold);
            }
            
            /* Service Modal Layout overlay */
            .admin-modal-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(26, 21, 22, 0.5);
                backdrop-filter: blur(8px);
                z-index: 1000;
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
            }
            .admin-modal-overlay.active { display: flex; }
            
            .admin-modal {
                background: #fff;
                border: 1px solid var(--color-border);
                border-radius: var(--radius-lg);
                max-width: 500px;
                width: 100%;
                padding: 2.5rem;
                box-shadow: var(--shadow-lg);
                position: relative;
            }
        </style>
    </head>
    <body>
        
        <header class="admin-header">
            <div class="container admin-header-inner">
                <h1 class="admin-title"><span>Lavender’s</span> Admin Console</h1>
                <div style="display:flex; align-items:center; gap: 1.5rem;">
                    <span style="font-size:0.85rem; color: rgba(255,255,255,0.7);">Hello, <?= htmlspecialchars($_SESSION['admin_user']) ?></span>
                    <a href="index.php?admin_logout=1" class="btn-primary" style="background:var(--color-gold); border-color:var(--color-gold); padding: 0.5rem 1.2rem; font-size:0.7rem;">Sign Out</a>
                </div>
            </div>
        </header>

        <main class="container">
            <!-- 1. KPI Grid -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-val"><?= number_format($total_revenue) ?> BDT</div>
                    <div class="kpi-label">Elite Revenue Generated</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-val"><?= $pending_count ?></div>
                    <div class="kpi-label">Pending Inquiries</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-val"><?= $upcoming_week ?></div>
                    <div class="kpi-label">Scheduled Slots (7 Days)</div>
                </div>
            </div>

            <!-- 2. Master Bookings Table -->
            <div class="dashboard-panel">
                <div class="panel-header">
                    <h2 class="panel-title">Bespoke Appointment Inquiries</h2>
                </div>
                <div style="overflow-x: auto;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Client Details</th>
                                <th>Event Date</th>
                                <th>Investment</th>
                                <th>Special Notes</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bookings_list)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--color-muted); padding: 3rem;">No slots booked yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($bookings_list as $bk): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($bk['booking_reference']) ?></strong></td>
                                        <td>
                                            <div style="font-weight:700; color:var(--color-plum);"><?= htmlspecialchars($bk['customer_name']) ?></div>
                                            <div style="font-size:0.72rem; color:var(--color-muted);"><?= htmlspecialchars($bk['customer_phone']) ?> | <?= htmlspecialchars($bk['customer_email']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight:600;"><?= date("D, M d, Y", strtotime($bk['event_date'])) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight:700; color:var(--color-gold);"><?= number_format($bk['total_price']) ?> BDT</div>
                                            <div style="font-size:0.7rem; color:var(--color-muted);">Travel: <?= number_format($bk['travel_fee']) ?> BDT</div>
                                        </td>
                                        <td style="max-width: 250px; font-size:0.8rem; color:var(--color-muted);">
                                            <?php if($bk['skin_profile']): ?><strong>Skin:</strong> <?= htmlspecialchars($bk['skin_profile']) ?><br><?php endif; ?>
                                            <?= htmlspecialchars($bk['special_notes'] ? substr($bk['special_notes'], 0, 100) . '...' : 'None') ?>
                                        </td>
                                        <td>
                                            <select class="admin-select" onchange="updateBookingStatus(<?= $bk['id'] ?>, this.value)">
                                                <option value="pending" <?= $bk['booking_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="confirmed" <?= $bk['booking_status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                <option value="completed" <?= $bk['booking_status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                <option value="cancelled" <?= $bk['booking_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 3. Service Catalog Panel -->
            <div class="dashboard-panel">
                <div class="panel-header">
                    <h2 class="panel-title">Dynamic Services Catalog</h2>
                    <button class="btn-primary" style="padding: 0.6rem 1.4rem; font-size:0.75rem;" onclick="openServiceModal()">Create New Package</button>
                </div>
                <div style="overflow-x: auto;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Package Code</th>
                                <th>Package Details</th>
                                <th>Appx Duration</th>
                                <th>Base Investment</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($services_list as $srv): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($srv['id']) ?></code></td>
                                    <td>
                                        <div style="font-weight:700; color:var(--color-plum);"><?= htmlspecialchars($srv['title']) ?></div>
                                        <div style="font-size:0.75rem; color:var(--color-muted);"><?= htmlspecialchars($srv['tag']) ?></div>
                                    </td>
                                    <td><strong><?= intval($srv['duration_minutes']) ?> Minutes</strong></td>
                                    <td><strong style="color:var(--color-gold);"><?= number_format($srv['base_price']) ?> BDT</strong></td>
                                    <td style="text-align:right;">
                                        <button class="btn-secondary" style="padding: 0.4rem 0.8rem; font-size:0.7rem; border-color:var(--color-border);" onclick='openServiceModal(<?= json_encode($srv) ?>)'>Edit</button>
                                        <button class="btn-secondary" style="padding: 0.4rem 0.8rem; font-size:0.7rem; color:#c94c4c; border-color:rgba(201,76,76,0.15);" onclick="deleteService('<?= $srv['id'] ?>')">Archive</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <!-- 4. Dynamic CRUD Services Modal overlay -->
        <div class="admin-modal-overlay" id="srv-modal-overlay">
            <div class="admin-modal">
                <button class="modal-close-btn" onclick="closeServiceModal()">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
                <h3 class="panel-heading" style="font-family:var(--font-serif); font-size:1.5rem; margin-bottom: 1.5rem;" id="modal-title">Create Makeup Package</h3>
                
                <form id="service-crud-form">
                    <input type="hidden" id="srv-id">
                    
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label" for="srv-title">Package Name *</label>
                        <input type="text" id="srv-title" class="form-input" style="background:#fff;" required placeholder="e.g., Luxury Reception Glow">
                    </div>
                    
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label" for="srv-tag">Package Category Tag *</label>
                        <input type="text" id="srv-tag" class="form-input" style="background:#fff;" required placeholder="e.g., Bridal Signature">
                    </div>
                    
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label" for="srv-price">Investment Amount (BDT) *</label>
                        <input type="number" id="srv-price" class="form-input" style="background:#fff;" required placeholder="e.g. 10000">
                    </div>
                    
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label" for="srv-duration">Duration (Minutes) *</label>
                        <input type="number" id="srv-duration" class="form-input" style="background:#fff;" required placeholder="e.g. 120">
                    </div>
                    
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label" for="srv-desc">Description & Inclusions *</label>
                        <textarea id="srv-desc" class="form-textarea" style="background:#fff; min-height:80px;" required placeholder="Detailed package parameters..."></textarea>
                    </div>
                    
                    <div class="form-group" style="margin-bottom:1.5rem;">
                        <label class="form-label" for="srv-image">Unsplash Image URL (Optional)</label>
                        <input type="text" id="srv-image" class="form-input" style="background:#fff;" placeholder="Direct image path URL">
                    </div>

                    <button type="submit" class="drawer-action-btn" style="width: 100%;">Save Package</button>
                </form>
            </div>
        </div>

        <script>
            // A. Update Booking Status via AJAX
            function updateBookingStatus(bookingId, statusVal) {
                const fd = new FormData();
                fd.append('booking_id', bookingId);
                fd.append('status', statusVal);
                
                fetch('index.php?action=admin-update-booking-status', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        alert('Status updated successfully!');
                    } else {
                        alert('Status update failed: ' + res.message);
                    }
                })
                .catch(() => alert('Network failure. Can not alter status.'));
            }

            // B. CRUD Modal Controls
            function openServiceModal(srvObj = null) {
                const overlay = document.getElementById('srv-modal-overlay');
                overlay.classList.add('active');
                
                if (srvObj) {
                    document.getElementById('modal-title').textContent = 'Edit Artistry Package';
                    document.getElementById('srv-id').value = srvObj.id;
                    document.getElementById('srv-title').value = srvObj.title;
                    document.getElementById('srv-tag').value = srvObj.tag;
                    document.getElementById('srv-price').value = srvObj.base_price;
                    document.getElementById('srv-duration').value = srvObj.duration_minutes;
                    document.getElementById('srv-desc').value = srvObj.description;
                    document.getElementById('srv-image').value = srvObj.image_path;
                } else {
                    document.getElementById('modal-title').textContent = 'Create Makeup Package';
                    document.getElementById('service-crud-form').reset();
                    document.getElementById('srv-id').value = '';
                }
            }

            function closeServiceModal() {
                document.getElementById('srv-modal-overlay').classList.remove('active');
            }

            // C. CRUD Form Submission
            document.getElementById('service-crud-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const fd = new FormData();
                fd.append('id', document.getElementById('srv-id').value);
                fd.append('title', document.getElementById('srv-title').value.trim());
                fd.append('tag', document.getElementById('srv-tag').value.trim());
                fd.append('base_price', document.getElementById('srv-price').value);
                fd.append('duration_minutes', document.getElementById('srv-duration').value);
                fd.append('description', document.getElementById('srv-desc').value.trim());
                fd.append('image_path', document.getElementById('srv-image').value.trim());
                
                fetch('index.php?action=admin-save-service', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        window.location.reload();
                    } else {
                        alert(res.message || 'Saving failed.');
                    }
                })
                .catch(() => alert('AJAX saving process failed.'));
            });

            // D. Soft Delete/Archive Service
            function deleteService(srvId) {
                if (!confirm('Are you sure you want to archive this makeup package from the catalog?')) return;
                
                const fd = new FormData();
                fd.append('id', srvId);
                
                fetch('index.php?action=admin-delete-service', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        window.location.reload();
                    } else {
                        alert(res.message || 'Archiving failed.');
                    }
                })
                .catch(() => alert('Network failure during archiving.'));
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// 6. Default view: Fetch Dynamic services directly from database
$stmt = $pdo->query("SELECT * FROM services WHERE status = 'active' ORDER BY base_price DESC");
$db_services = $stmt->fetchAll();

// HTML Layout Dynamic Injection
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Lavender’s Glam Studio — Elite Professional Makeup Artist Booking & Portfolio. Bespoke bridal, editorial runway, and red-carpet glow. Book your luxury session online.">
    <meta name="author" content="Lavender’s Glam Studio">
    <title>Lavender’s Glam Studio — Elite Professional Makeup Artist & Luxury Styling Appointments</title>
    
    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✨</text></svg>">

    <!-- Google Fonts: Cormorant Garamond (Serif) & Inter (Sans-serif) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="main.css">
</head>
<body>

    <!-- 1. Top Announcement Bar -->
    <div class="announcement-bar">
        <span>✦ Elite VIP Autumn & Bridal Appointments Now Open • Studio Atelier & On-Location Guest Services ✦</span>
    </div>

    <!-- 2. Header & Glassmorphic Navigation -->
    <header id="site-header">
        <div class="container header-inner">
            <a href="#" class="logo" id="brand-logo" style="display: flex; align-items: center; gap: 0.8rem;">
                <img src="lavender_glam_logo.png" alt="Lavender’s Glam Studio Logo" style="height: 38px; width: 38px; object-fit: cover; border-radius: 50%; border: 1.5px solid var(--color-gold);">
                <span>Lavender’s</span> Glam Studio
            </a>
            
            <nav>
                <ul class="nav-links">
                    <li><a href="#about-section">The Artist</a></li>
                    <li><a href="#services-section">Bespoke Services</a></li>
                    <li><a href="#portfolio-section">The Portfolio</a></li>
                    <li><a href="#faq-section">Booking Guide</a></li>
                </ul>
            </nav>
            
            <div class="header-actions">
                <!-- Floating Booking Agenda Drawer Trigger -->
                <button class="agenda-trigger" id="agenda-trigger-btn" aria-label="Open Booking Agenda">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span class="agenda-badge" id="header-agenda-badge" style="display: none;">0</span>
                </button>
                <a href="#services-section" class="btn-primary header-reserve-btn">Reserve Session</a>

                <!-- User Account Profile state -->
                <div class="header-user-widget" id="header-user-widget">
                    <?php if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true): ?>
                        <div class="user-profile-menu">
                            <button class="profile-trigger" id="profile-trigger-btn" onclick="BookingApp.toggleProfileDropdown(event)">
                                <span class="avatar-circle"><?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?></span>
                                <span class="user-display-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                            </button>
                            <div class="profile-dropdown" id="profile-dropdown">
                                <a href="#" onclick="BookingApp.showMyBookings(event)">My Bookings</a>
                                <a href="index.php?action=user-logout&redirect=1">Sign Out</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <button class="btn-secondary header-login-btn" onclick="BookingApp.openAuthModal()" style="border-color: transparent; background: transparent; padding: 0 0.8rem; font-weight: 600; color: var(--color-plum);">Sign In</button>
                    <?php endif; ?>
                </div>
                
                <!-- Mobile Menu Hamburger Trigger -->
                <button class="mobile-menu-trigger" id="mobile-menu-trigger" aria-label="Open Navigation Menu">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <line x1="4" y1="6" x2="20" y2="6"></line>
                        <line x1="4" y1="12" x2="20" y2="12"></line>
                        <line x1="4" y1="18" x2="20" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <!-- 3. Luxury Hero Section -->
    <section class="hero" id="about-section">
        <div class="container hero-grid">
            <div class="hero-content">
                <div class="eyebrow">Maison de Beauté</div>
                <h1 class="hero-title">Crafting <span>Luxury Beauty</span> For Your Most Elite Celebrations</h1>
                <p class="hero-lead">Welcome to the digital studio of Lavender’s Glam Studio, where premier skin artistry meets high-fashion styling. Specializing in radiant bridal glow, flawless red-carpet contouring, and editorial runway aesthetics using only the world’s finest couture cosmetics.</p>
                <div class="hero-actions">
                    <a href="#services-section" class="btn-primary">View Elite Services</a>
                    <a href="#portfolio-section" class="btn-secondary">Explore Masterpieces</a>
                </div>
            </div>
            <div class="hero-showcase">
                <img src="https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?q=80&w=800&auto=format&fit=crop" alt="Bridal Couture Glam close-up">
                <div class="hero-trust-badge">
                    <div class="trust-avatar">LGS</div>
                    <div class="trust-text">
                        <h4>Lavender Rahman</h4>
                        <p>Lead Artist & Creative Director</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. Bespoke Services Catalog -->
    <section class="services" id="services-section">
        <div class="container">
            <div class="section-intro">
                <span class="section-subtitle">Exquisite Artistry Menu</span>
                <h2 class="section-title">Select Your Custom Service</h2>
            </div>
            
            <!-- Database-Driven Services Grid -->
            <div class="services-grid" id="services-grid">
                <?php foreach($db_services as $service): ?>
                    <div class="service-card">
                        <div class="service-media">
                            <img src="<?= htmlspecialchars($service['image_path']) ?>" alt="<?= htmlspecialchars($service['title']) ?>">
                            <span class="service-tag"><?= htmlspecialchars($service['tag']) ?></span>
                        </div>
                        <div class="service-body">
                            <h3 class="service-name"><?= htmlspecialchars($service['title']) ?></h3>
                            <p class="service-desc"><?= htmlspecialchars(substr($service['description'], 0, 130)) ?>...</p>
                            <div class="service-meta">
                                <div class="meta-item">
                                    <p>Duration</p>
                                    <h5><?= intval($service['duration_minutes']) ?> Minutes</h5>
                                </div>
                                <div class="meta-item">
                                    <p>Base Investment</p>
                                    <h5 class="price-amt"><?= number_format($service['base_price']) ?> BDT</h5>
                                </div>
                            </div>
                            <button class="service-card-btn" onclick="BookingApp.openServiceModal('<?= htmlspecialchars($service['id']) ?>')">Book & Customize</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- 5. Dynamic Portfolio Gallery -->
    <section class="portfolio" id="portfolio-section">
        <div class="container">
            <div class="section-intro">
                <span class="section-subtitle">Artistic Visuals</span>
                <h2 class="section-title">The Masterpiece Portfolio</h2>
            </div>

            <!-- Portfolio Filtering Buttons -->
            <div class="portfolio-filters">
                <button class="filter-btn active" data-filter="all">All Styles</button>
                <button class="filter-btn" data-filter="bridal">Bridal Signature</button>
                <button class="filter-btn" data-filter="editorial">Editorial Runway</button>
                <button class="filter-btn" data-filter="glam">Celebrity Glam</button>
            </div>

            <!-- Portfolio Items Grid -->
            <div class="portfolio-grid" id="portfolio-grid">
                
                <div class="portfolio-item" data-cat="bridal">
                    <img src="https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?q=80&w=600&auto=format&fit=crop" alt="Luminous Bridal Signature Makeup">
                    <div class="portfolio-overlay">
                        <span class="portfolio-cat">Bridal Signature</span>
                        <h4 class="portfolio-title">Rosé Gold Royal Bride</h4>
                    </div>
                </div>

                <div class="portfolio-item" data-cat="glam">
                    <img src="https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?q=80&w=600&auto=format&fit=crop" alt="Deep Contour Celebrity Glam Makeup">
                    <div class="portfolio-overlay">
                        <span class="portfolio-cat">Celebrity Glam</span>
                        <h4 class="portfolio-title">Velvet Plum Soirée Glam</h4>
                    </div>
                </div>

                <div class="portfolio-item" data-cat="editorial">
                    <img src="https://images.unsplash.com/photo-1512496015851-a90fb38ba796?q=80&w=600&auto=format&fit=crop" alt="Minimal High-glow Skin Runway Look">
                    <div class="portfolio-overlay">
                        <span class="portfolio-cat">Editorial Runway</span>
                        <h4 class="portfolio-title">Dewy Opal Editorial Glass Skin</h4>
                    </div>
                </div>

                <div class="portfolio-item" data-cat="bridal">
                    <img src="https://images.unsplash.com/photo-1526047932273-341f2a7631f9?q=80&w=600&auto=format&fit=crop" alt="Timeless Traditional Classic Bride">
                    <div class="portfolio-overlay">
                        <span class="portfolio-cat">Bridal Signature</span>
                        <h4 class="portfolio-title">Classic Silk Imperial Bride</h4>
                    </div>
                </div>

                <div class="portfolio-item" data-cat="editorial">
                    <img src="https://images.unsplash.com/photo-1596462502278-27bfdc403348?q=80&w=600&auto=format&fit=crop" alt="High-contrast Avant-garde editorial cosmetics">
                    <div class="portfolio-overlay">
                        <span class="portfolio-cat">Editorial Runway</span>
                        <h4 class="portfolio-title">Vibrant Pigment Avant-Garde</h4>
                    </div>
                </div>

                <div class="portfolio-item" data-cat="glam">
                    <img src="https://images.unsplash.com/photo-1515688594390-b649af70d282?q=80&w=600&auto=format&fit=crop" alt="Hollywood Red Lip Classic Glow">
                    <div class="portfolio-overlay">
                        <span class="portfolio-cat">Celebrity Glam</span>
                        <h4 class="portfolio-title">Classic Retro Hollywood Red Lip</h4>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- 6. Sliding "Booking Agenda" Drawer Sidebar -->
    <div class="drawer-overlay" id="drawer-overlay"></div>
    
    <!-- Mobile Menu Drawer -->
    <div class="drawer" id="mobile-nav-drawer" style="left: 0; right: auto; transform: translateX(-100%);">
        <div class="drawer-header">
            <h3 class="drawer-title">Menu</h3>
            <button class="drawer-close-btn" id="mobile-nav-close-btn" aria-label="Close Menu">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <div class="drawer-body" style="padding: 2.5rem 2rem;">
            <ul style="list-style: none; display: flex; flex-direction: column; gap: 2rem;">
                <li><a href="#about-section" class="mobile-nav-link" style="font-family: var(--font-serif); font-size: 1.8rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-surface-dark);">The Artist</a></li>
                <li><a href="#services-section" class="mobile-nav-link" style="font-family: var(--font-serif); font-size: 1.8rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-surface-dark);">Bespoke Services</a></li>
                <li><a href="#portfolio-section" class="mobile-nav-link" style="font-family: var(--font-serif); font-size: 1.8rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-surface-dark);">The Portfolio</a></li>
                <li><a href="#faq-section" class="mobile-nav-link" style="font-family: var(--font-serif); font-size: 1.8rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-surface-dark);">Booking Guide</a></li>
            </ul>
            <div style="margin-top: 4rem; border-top: 1px solid var(--color-border); padding-top: 2rem;">
                <a href="#services-section" class="btn-primary mobile-nav-link" style="display: block; text-align: center; width: 100%;">Reserve Session</a>
            </div>
        </div>
    </div>

    <div class="drawer" id="agenda-drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-heading">
        <div class="drawer-header">
            <h3 class="drawer-title" id="drawer-heading">Your Booking Agenda</h3>
            <button class="drawer-close-btn" id="drawer-close-btn" aria-label="Close Booking Drawer">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        
        <div class="drawer-body">
            <!-- JavaScript renders active bookings here -->
            <div class="agenda-items-container" id="agenda-items-container"></div>
        </div>

        <div class="drawer-footer" id="drawer-footer-pricing">
            <div class="drawer-pricing-row">
                <span>Atelier Base Services:</span>
                <span id="drawer-subtotal">0 BDT</span>
            </div>
            <div class="drawer-pricing-row">
                <span>Location Travel Surcharges:</span>
                <span id="drawer-travel">0 BDT</span>
            </div>
            <div class="drawer-pricing-row total">
                <span>Total Investment:</span>
                <span class="total-amt" id="drawer-total">0 BDT</span>
            </div>
            
            <button class="drawer-action-btn" id="proceed-checkout-btn">Proceed to Reservation Inquiry</button>
            <button class="service-card-btn" id="drawer-continue-shopping" style="margin-top: 0.5rem; width: 100%;">Add Another Session</button>
        </div>
    </div>

    <!-- 7. Dynamic Service Customizer & Live Calendar Modal -->
    <div class="modal-overlay" id="booking-modal">
        <div class="modal-wrapper">
            <button class="modal-close-btn" aria-label="Close Customizer">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            
            <div class="service-detail-grid">
                <!-- Left-hand side service overview -->
                <div class="service-detail-hero">
                    <img src="" alt="" id="modal-service-image">
                    <div class="service-detail-info">
                        <span class="detail-eyebrow" id="modal-service-tag">Bridal Signature</span>
                        <h2 class="detail-name" id="modal-service-name">Service Name</h2>
                        <p class="detail-desc" id="modal-service-desc">Service Description goes here. Keep it premium, beautiful, and informative.</p>
                        
                        <div class="detail-meta-row">
                            <div class="detail-meta-item">
                                <h6>Investment</h6>
                                <p class="detail-price" id="modal-service-price">15,000 BDT</p>
                            </div>
                            <div class="detail-meta-item">
                                <h6>Appx. Duration</h6>
                                <p id="modal-service-duration">3 Hours</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right-hand side custom JS widget -->
                <div class="booking-form-pane">
                    <h3 class="widget-title">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        Select Session Details
                    </h3>

                    <!-- A. The Interactive calendar grid -->
                    <div class="booking-widget-container">
                        <div class="calendar-header">
                            <button class="calendar-nav-btn" id="calendar-prev-btn" onclick="BookingApp.changeMonth(-1)" type="button">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
                            </button>
                            <span class="calendar-month-name" id="calendar-month-name">May 2026</span>
                            <button class="calendar-nav-btn" id="calendar-next-btn" onclick="BookingApp.changeMonth(1)" type="button">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </button>
                        </div>
                        <div class="calendar-grid" id="calendar-grid-container"></div>
                    </div>

                    <!-- B. Dynamic Time Slots grid -->
                    <div class="time-slots-container">
                        <h4 class="widget-title">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            Select Available Time
                        </h4>
                        <div class="slots-grid" id="slots-grid-container"></div>
                    </div>

                    <!-- C. Location Mode Surcharge Switch -->
                    <div class="location-selector">
                        <h4 class="widget-title">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2a8 8 0 0 0-8 8c0 5.25 8 12 8 12s8-6.75 8-12a8 8 0 0 0-8-8z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            Select Studio Location
                        </h4>
                        <div class="location-options">
                            <div class="location-card active" id="loc-studio" onclick="BookingApp.selectLocation('studio')">
                                <span class="location-card-title">In-Studio</span>
                                <span class="location-card-fee">Atelier Studio (No Fee)</span>
                            </div>
                            <div class="location-card" id="loc-location" onclick="BookingApp.selectLocation('location')">
                                <span class="location-card-title">On-Location</span>
                                <span class="location-card-fee">+2,000 BDT Travel Surcharge</span>
                            </div>
                        </div>
                    </div>

                    <!-- D. Price Tally & Submission -->
                    <div class="drawer-footer" style="padding: 1.5rem 0 0; border-top: 1px solid var(--color-border); margin-top: 2rem;">
                        <div class="drawer-pricing-row" id="widget-fee-row" style="display:none;">
                            <span>On-Location Travel Fee:</span>
                            <span id="widget-travel-fee">+2,000 BDT</span>
                        </div>
                        <div class="drawer-pricing-row total" style="margin-top: 0; padding-top: 0.5rem; border-top: none;">
                            <span>Total Investment:</span>
                            <span class="total-amt" id="widget-total-amt">0 BDT</span>
                        </div>
                        <button class="drawer-action-btn" type="button" onclick="BookingApp.addActiveBooking()">Add Session to Agenda</button>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- 8. Client Inquiry Form (Database-Free Checkout) -->
    <section class="checkout-section" id="checkout-section">
        <div class="container">
            <div class="section-intro">
                <span class="section-subtitle">Confirm Your Booking Slots</span>
                <h2 class="section-title">Elite Booking Reservation</h2>
            </div>
            
            <div class="checkout-layout">
                <!-- Left-hand Form -->
                <div class="checkout-card">
                    <h3 class="summary-heading" style="margin-bottom: 2rem;">Client Artistry Questionnaire</h3>
                    <form id="checkout-form">
                        <div class="checkout-form-grid">
                            <div class="form-group">
                                <label class="form-label" for="client-name">Full Name *</label>
                                <input type="text" id="client-name" class="form-input" placeholder="e.g. Tasnim Rahman" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="client-phone">WhatsApp Number *</label>
                                <input type="tel" id="client-phone" class="form-input" placeholder="e.g. +8801700000000" required>
                            </div>

                            <div class="form-group form-full-width">
                                <label class="form-label" for="client-email">Email Address *</label>
                                <input type="email" id="client-email" class="form-input" placeholder="e.g. contact@example.com" required>
                            </div>

                            <div class="form-group form-full-width" id="field-address-group" style="display: none;">
                                <label class="form-label" for="client-address">Event Venue / Address *</label>
                                <input type="text" id="client-address" class="form-input" placeholder="Detailed event venue or hotel address for on-location setup">
                            </div>

                            <div class="form-group form-full-width">
                                <label class="form-label" for="client-skin">Skin Profile (Optional)</label>
                                <select id="client-skin" class="form-select">
                                    <option value="" disabled selected>Select your skin profile type</option>
                                    <option value="Dry Skin">Dry Glow Prep</option>
                                    <option value="Oily Skin">Matte Balancing Prep</option>
                                    <option value="Combination Skin">Combination Targeted T-Zone</option>
                                    <option value="Normal / Sensitive Skin">Normal / Sensitive Hydrating</option>
                                    <option value="Mature Skin">Mature Lifting & Velvet Smooth</option>
                                </select>
                            </div>

                            <div class="form-group form-full-width">
                                <label class="form-label" for="client-preferences">Makeup Preferences & Visual Aesthetics</label>
                                <textarea id="client-preferences" class="form-textarea" placeholder="Describe your dream look, target color palettes, hair ideas, or any skin conditions the artist should prep for."></textarea>
                            </div>

                            <div class="form-group form-full-width">
                                <label class="form-checkbox-label">
                                    <input type="checkbox" required>
                                    <span>I understand slots are held temporarily until confirmed by the artist. I agree to pay a scheduling deposit upon initial verification.</span>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="drawer-action-btn" style="margin-top: 2rem; width: 100%;">
                            Submit Elite Booking Request
                        </button>
                    </form>
                </div>

                <!-- Right-hand Summary Pane -->
                <div class="checkout-summary-panel">
                    <h3 class="summary-heading">Review Scheduled Slots</h3>
                    
                    <div class="summary-list" id="checkout-summary-list">
                        <!-- Filled by JS dynamically -->
                    </div>

                    <div style="border-top: 1px solid var(--color-border); padding-top: 1.5rem;">
                        <div class="drawer-pricing-row">
                            <span>Base Investment:</span>
                            <span id="checkout-subtotal">0 BDT</span>
                        </div>
                        <div class="drawer-pricing-row">
                            <span>Travel/Location Surcharges:</span>
                            <span id="checkout-travel">0 BDT</span>
                        </div>
                        <div class="drawer-pricing-row total">
                            <span>Bespoke Investment:</span>
                            <span class="total-amt" id="checkout-total">0 BDT</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- 9. Testimonials Carousel / Reviews -->
    <section class="services" style="background: radial-gradient(circle at 10% 90%, rgba(245, 230, 227, 0.3), transparent 45%), var(--color-surface);">
        <div class="container">
            <div class="section-intro">
                <span class="section-subtitle">Luxury Client Experiences</span>
                <h2 class="section-title">Praise From Elite Clients</h2>
            </div>
            
            <div class="services-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
                <div class="service-card" style="padding: 2.5rem; text-align: center; justify-content: center; min-height: 250px;">
                    <div style="color: var(--color-gold); font-size: 1.5rem; margin-bottom: 1rem;">★★★★★</div>
                    <p class="service-desc" style="font-style: italic; margin-bottom: 1.5rem;">"The Rosé Gold Bridal Glam looked absolutely phenomenal. It survived tears, extreme hotel lighting, and active photoshoot rounds seamlessly! Lavender is a master."</p>
                    <h4 class="service-name" style="font-size: 1.1rem; margin-bottom: 0.2rem;">Farhana S.</h4>
                    <span style="font-size: 0.65rem; color: var(--color-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Elegant Royal Bride</span>
                </div>

                <div class="service-card" style="padding: 2.5rem; text-align: center; justify-content: center; min-height: 250px;">
                    <div style="color: var(--color-gold); font-size: 1.5rem; margin-bottom: 1rem;">★★★★★</div>
                    <p class="service-desc" style="font-style: italic; margin-bottom: 1.5rem;">"Booked the Masterclass, and my daily skincare and cosmetics routine has leveled up completely. The custom skin-mapping chart they gave me was worth every BDT!"</p>
                    <h4 class="service-name" style="font-size: 1.1rem; margin-bottom: 0.2rem;">Raiya K.</h4>
                    <span style="font-size: 0.65rem; color: var(--color-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Masterclass Graduate</span>
                </div>

                <div class="service-card" style="padding: 2.5rem; text-align: center; justify-content: center; min-height: 250px;">
                    <div style="color: var(--color-gold); font-size: 1.5rem; margin-bottom: 1rem;">★★★★★</div>
                    <p class="service-desc" style="font-style: italic; margin-bottom: 1.5rem;">"For high-end fashion shoots, there is no one else I trust in Dhaka. Flawless contouring, professional pacing, and skin that looks like actual glass. High-end excellence!"</p>
                    <h4 class="service-name" style="font-size: 1.1rem; margin-bottom: 0.2rem;">Nyla R.</h4>
                    <span style="font-size: 0.65rem; color: var(--color-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Fashion Shoot Director</span>
                </div>
            </div>
        </div>
    </section>

    <!-- 10. Elegant FAQs Accordion -->
    <section class="faq" id="faq-section">
        <div class="container">
            <div class="section-intro">
                <span class="section-subtitle">Luxury Concierge Guides</span>
                <h2 class="section-title">Frequently Answered Queries</h2>
            </div>
            
            <div class="faq-container">
                <div class="faq-item">
                    <button class="faq-trigger" type="button">
                        Which cosmetics brands do you use inside the Atelier?
                        <span class="faq-icon">+</span>
                    </button>
                    <div class="faq-content">
                        <div class="faq-inner">
                            We utilize exclusively premium, high-end professional products including Charlotte Tilbury, Tom Ford, Chanel, Dior Backstage, Pat McGrath Labs, Hourglass, NARS, and custom dermatologically-tested skin preparers to protect your skin barrier while maximizing look longevity.
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-trigger" type="button">
                        How does the On-Location Travel Fee work?
                        <span class="faq-icon">+</span>
                    </button>
                    <div class="faq-content">
                        <div class="faq-inner">
                            For home or hotel venues, a flat 2,000 BDT surcharge is applied per artist to cover professional packing, travel logistics, and setup time. If you request bookings outside of Dhaka, additional custom out-station logistics will be calculated by the artist during initial WhatsApp validation.
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-trigger" type="button">
                        What is your booking reservation deposit policy?
                        <span class="faq-icon">+</span>
                    </button>
                    <div class="faq-content">
                        <div class="faq-inner">
                            To finalize your elite appointment slots, a 50% reservation deposit must be sent via bKash or Bank Transfer once our team reviews your questionnaire and coordinates scheduling over WhatsApp. Deposit details will be sent directly within the initial conversation.
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-trigger" type="button">
                        Can I reschedule or cancel my reserved appointment?
                        <span class="faq-icon">+</span>
                    </button>
                    <div class="faq-content">
                        <div class="faq-inner">
                            Rescheduling is permitted up to 72 hours before the session, subject to slot availability. Cancellations made inside 7 days are subject to a 50% deposit forfeiture, while cancellations inside 48 hours are non-refundable due to reserved space blockages.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 11. Custom Receipt & Submission Routing Modal -->
    <div class="modal-overlay" id="receipt-modal">
        <div class="receipt-wrapper">
            <button class="modal-close-btn" aria-label="Close Receipt">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            <div class="receipt-icon">
                <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
            <h3 class="receipt-title">Elite Booking Form Compiled!</h3>
            <p class="receipt-subtitle">Your slot details have been packaged into a bespoke reservation request. Select a route below to instantly send it to our beauty coordinators.</p>
            
            <div class="receipt-detail-card" id="receipt-detail-card">
                <!-- Receipt details added here dynamically -->
            </div>

            <div class="btn-group">
                <button class="drawer-action-btn" id="btn-submit-wa" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.6rem; background: #25d366; border-color: #25d366;">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.73-1.458L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.825 1.451 5.436 0 9.86-4.37 9.864-9.799.002-2.63-1.023-5.101-2.885-6.965C16.588 2.005 14.12 1.01 11.816 1.01c-5.44 0-9.866 4.372-9.87 9.802 0 1.94.512 3.835 1.485 5.534l-.993 3.624 3.731-.969c1.55.952 3.125 1.417 4.888 1.417zm11.361-5.115c-.287-.143-1.697-.838-1.959-.933-.262-.096-.452-.143-.642.143-.19.287-.736.933-.903 1.122-.167.19-.334.215-.62.072-2.876-1.44-4.707-4.14-5.355-5.263-.166-.287-.018-.443.126-.585.13-.127.287-.335.43-.502.144-.167.19-.287.287-.478.095-.19.047-.358-.023-.502-.072-.143-.642-1.554-.88-2.127-.233-.564-.47-.488-.642-.497-.166-.008-.356-.01-.546-.01-.19 0-.5.072-.76.358-.263.287-1.002.98-1.002 2.39s1.025 2.775 1.168 2.966c.143.19 2.017 3.08 4.888 4.318.683.294 1.218.47 1.633.602.687.219 1.312.188 1.807.114.55-.082 1.697-.693 1.935-1.362.24-.669.24-1.242.167-1.362-.072-.12-.262-.215-.548-.358z"/></svg>
                    Send Request to WhatsApp
                </button>
                <button class="btn-secondary" id="btn-submit-mail" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.6rem; border-color: var(--color-border); padding: 1rem;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    Send Request via Email
                </button>
            </div>
        </div>
    </div>

    <!-- 11A. Luxury User Authentication Modal (Register / Login) -->
    <div class="modal-overlay" id="auth-modal">
        <div class="auth-wrapper">
            <button class="modal-close-btn" onclick="BookingApp.closeAuthModal()" aria-label="Close Authentication">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            <h3 class="auth-title" style="font-family: var(--font-serif); font-size: 1.8rem; color: var(--color-plum); text-align: center; margin-bottom: 1.5rem;">🌸 Lavender’s Glam Studio</h3>
            
            <div class="auth-tabs">
                <button class="auth-tab-btn active" id="tab-login-btn" onclick="BookingApp.switchAuthTab('login')">Access Account</button>
                <button class="auth-tab-btn" id="tab-register-btn" onclick="BookingApp.switchAuthTab('register')">Join Atelier</button>
            </div>

            <!-- Login Form Pane -->
            <form id="auth-login-form" class="auth-form active" onsubmit="BookingApp.userLogin(event)">
                <p class="auth-subtitle">Log in to sync agenda slots and review appointments.</p>
                <div class="form-group" style="text-align: left; margin-bottom: 1.2rem;">
                    <label class="form-label" for="login-email">Email Address *</label>
                    <input type="email" id="login-email" class="form-input" style="background:#fff;" required placeholder="e.g. tasnim@example.com">
                </div>
                <div class="form-group" style="text-align: left; margin-bottom: 1.8rem;">
                    <label class="form-label" for="login-password">Password *</label>
                    <input type="password" id="login-password" class="form-input" style="background:#fff;" required placeholder="••••••••">
                </div>
                <button type="submit" class="drawer-action-btn" style="width: 100%;">Sign In</button>
            </form>

            <!-- Register Form Pane -->
            <form id="auth-register-form" class="auth-form" onsubmit="BookingApp.userRegister(event)">
                <p class="auth-subtitle">Create a glamorous profile to finalize your booking.</p>
                <div class="form-group" style="text-align: left; margin-bottom: 1rem;">
                    <label class="form-label" for="reg-name">Full Name *</label>
                    <input type="text" id="reg-name" class="form-input" style="background:#fff;" required placeholder="e.g. Tasnim Rahman">
                </div>
                <div class="form-group" style="text-align: left; margin-bottom: 1rem;">
                    <label class="form-label" for="reg-email">Email Address *</label>
                    <input type="email" id="reg-email" class="form-input" style="background:#fff;" required placeholder="e.g. tasnim@example.com">
                </div>
                <div class="form-group" style="text-align: left; margin-bottom: 1rem;">
                    <label class="form-label" for="reg-phone">WhatsApp Number *</label>
                    <input type="tel" id="reg-phone" class="form-input" style="background:#fff;" required placeholder="e.g. +8801700000000">
                </div>
                <div class="form-group" style="text-align: left; margin-bottom: 1.5rem;">
                    <label class="form-label" for="reg-password">Password *</label>
                    <input type="password" id="reg-password" class="form-input" style="background:#fff;" required placeholder="••••••••">
                </div>
                <button type="submit" class="drawer-action-btn" style="width: 100%;">Create Account</button>
            </form>
        </div>
    </div>

    <!-- 11B. Luxury User Booking History Drawer/Modal -->
    <div class="modal-overlay" id="user-bookings-modal">
        <div class="bookings-wrapper">
            <button class="modal-close-btn" onclick="BookingApp.closeBookingsModal()" aria-label="Close History">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            <div class="receipt-icon" style="color: var(--color-gold); margin-bottom: 1rem;">
                <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 14V11m0-4h.01"></path></svg>
            </div>
            <h3 class="receipt-title" style="font-family: var(--font-serif); font-size: 1.8rem; color: var(--color-plum);">Your Booking Agenda History</h3>
            <p class="receipt-subtitle" style="margin-bottom: 2rem;">Track and review all compiled artistry reservation inquiries in our studio archive.</p>
            
            <div class="bookings-history-list" id="user-bookings-list">
                <!-- JavaScript renders user booking rows here dynamically -->
            </div>
        </div>
    </div>

    <!-- 12. Floating Toast System Container -->
    <div id="toast-container" class="toast-container"></div>

    <!-- 13. Footer Section -->
    <footer>
        <div class="container footer-grid">
            <div class="footer-brand">
                <h3><span>Lavender’s</span> Glam Studio</h3>
                <p>Curating exceptional beauty transformations using premium custom formulations and high-fashion paradigms.</p>
                <div class="footer-socials">
                    <a href="https://instagram.com" target="_blank" class="social-icon" aria-label="Follow us on Instagram">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                    </a>
                    <a href="https://facebook.com" target="_blank" class="social-icon" aria-label="Follow us on Facebook">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
                    </a>
                </div>
            </div>
            
            <div class="footer-links-col">
                <h5>Signature Menu</h5>
                <ul>
                    <li><a href="#services-section" onclick="BookingApp.openServiceModal('bridal-couture')">Bridal Editorial Couture</a></li>
                    <li><a href="#services-section" onclick="BookingApp.openServiceModal('red-carpet-glam')">Celebrity & Red Carpet Glam</a></li>
                    <li><a href="#services-section" onclick="BookingApp.openServiceModal('fashion-editorial')">Fashion Editorial & Runway</a></li>
                    <li><a href="#services-section" onclick="BookingApp.openServiceModal('one-on-one-masterclass')">Personal Masterclass</a></li>
                </ul>
            </div>
            
            <div class="footer-links-col">
                <h5>Studio Hours</h5>
                <ul style="color: rgba(250, 242, 240, 0.6); font-size: 0.85rem;">
                    <li>Friday & Saturday: 08:00 AM — 09:00 PM</li>
                    <li>Sunday to Thursday: 09:00 AM — 08:00 PM</li>
                    <li>Dhaka, Bangladesh</li>
                    <li>Email: appointments@lavendersglam.com</li>
                </ul>
            </div>
        </div>
        
        <div class="container footer-bottom">
            <p>&copy; 2026 Lavender’s Glam Studio. All luxury rights reserved.</p>
            <p>Designed for Ultimate Visual Luxury.</p>
        </div>
    </footer>

    <!-- Core Main JS Script -->
    <script src="main.js"></script>
</body>
</html>
