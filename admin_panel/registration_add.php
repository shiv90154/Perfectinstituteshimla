<?php
// registration_add.php - Secure add page with all fields and proper bind_param
$pageTitle = 'Add Registration';
include 'includes/config.php';
include 'includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_registrations')) {
    header("Location: unauthorized.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch courses
$coursesResult = $conn->query("SELECT title, type FROM courses WHERE is_deleted = 0 ORDER BY title");
$courses = [];
while ($row = $coursesResult->fetch_assoc()) {
    $courses[] = $row;
}

$id_types = ['Aadhaar', 'PAN', 'Voter ID', 'Passport', 'Other'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token.");
    }

    do {
        $reg_id = 'REG' . date('Y') . rand(10000, 99999);
        $check = $conn->prepare("SELECT id FROM registrations WHERE registration_id = ?");
        $check->bind_param('s', $reg_id);
        $check->execute();
        $check->store_result();
    } while ($check->num_rows > 0);
    $check->close();

    // Sanitize all inputs
    $full_name = trim($_POST['full_name'] ?? '');
    $father_name = trim($_POST['father_name'] ?? '');
    $mother_name = trim($_POST['mother_name'] ?? '');
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $gender = $_POST['gender'] ?? 'Male';
    $nationality = trim($_POST['nationality'] ?? 'Indian');
    $id_type = $_POST['id_type'] ?? 'Aadhaar';
    $id_number = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['id_number'] ?? '');
    $contact = preg_replace('/[^0-9]/', '', $_POST['contact'] ?? '');
    $whatsapp = preg_replace('/[^0-9]/', '', $_POST['whatsapp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $guardian_contact = preg_replace('/[^0-9]/', '', $_POST['guardian_contact'] ?? '');
    $guardian_relation = trim($_POST['guardian_relation'] ?? '');
    $present_address = trim($_POST['present_address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? 'India');
    $pincode = trim($_POST['pincode'] ?? '');
    $education_level = trim($_POST['education_level'] ?? '');
    $board_university = trim($_POST['board_university'] ?? '');
    $stream = trim($_POST['stream'] ?? '');
    $additional_qualification = trim($_POST['additional_qualification'] ?? '');
    $running_course = trim($_POST['running_course'] ?? '');
    $organisation = trim($_POST['organisation'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $course_name = trim($_POST['course_name'] ?? '');
    $add_course = !empty($_POST['add_course']) ? trim($_POST['add_course']) : null;
    $course_start_date = !empty($_POST['course_start_date']) ? $_POST['course_start_date'] : null;
    $admission_date = !empty($_POST['admission_date']) ? $_POST['admission_date'] : date('Y-m-d'); // defaults to today (form filling date)
    $total_amount = (float)($_POST['total_amount'] ?? 0);
    $admin_note = trim($_POST['admin_note'] ?? '');

    // Validation
    if (empty($full_name) || empty($contact) || empty($email) || empty($course_name)) {
        $error = "Full Name, Contact, Email, and Course are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!empty($id_number) && $id_type === 'Aadhaar' && strlen($id_number) != 12) {
        $error = "Aadhaar number must be exactly 12 digits.";
    } else {
        // Photo upload
        $photo = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $error = "Invalid photo format. Allowed: jpg, jpeg, png, gif.";
            } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
                $error = "Photo size must be less than 2MB.";
            } else {
                $new_filename = 'photo_' . uniqid() . '.' . $ext;
                $upload_path = '../uploads/' . $new_filename;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                    $photo = $new_filename;
                } else {
                    $error = "Failed to upload photo.";
                }
            }
        }

        if (empty($error)) {
            // Insert with all columns (including new fields, but we set them to NULL/default)
            // Note: coupon_code, discount_amount, net_amount, platform_fee, gst_amount, final_amount, payment_method, is_offline, razorpay_order_id are not set here.
            // They will be set during payment process. We set them to NULL or 0.
            $insert = "INSERT INTO registrations (
                registration_id, full_name, father_name, mother_name, dob, gender, nationality,
                id_type, id_number, photo, contact, whatsapp, email, guardian_contact, guardian_relation,
                present_address, city, state, country, pincode, education_level, board_university,
                stream, additional_qualification, running_course, organisation, department, designation,
                course_name, add_course, course_start_date, admission_date, total_amount, admin_note,
                coupon_code, discount_amount, net_amount, platform_fee, gst_amount, final_amount,
                payment_method, is_offline, status, payment_status, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                NULL, 0, 0, 0, 0, 0,
                NULL, 0, 'pending', 'pending', NOW()
            )";

            $stmt = $conn->prepare($insert);
            // 34 string fields (all except total_amount which is double, and the NULLs)
            // Actually we have: registration_id, full_name, father_name, mother_name, dob, gender, nationality,
            // id_type, id_number, photo, contact, whatsapp, email, guardian_contact, guardian_relation,
            // present_address, city, state, country, pincode, education_level, board_university,
            // stream, additional_qualification, running_course, organisation, department, designation,
            // course_name, add_course, course_start_date, admission_date, total_amount (double), admin_note
            // That's 34 strings + 1 double = 35 parameters.
            // We'll build types accordingly.

            // List all string parameters in order:
            $params = [
                $reg_id, $full_name, $father_name, $mother_name, $dob, $gender, $nationality,
                $id_type, $id_number, $photo, $contact, $whatsapp, $email, $guardian_contact, $guardian_relation,
                $present_address, $city, $state, $country, $pincode, $education_level, $board_university,
                $stream, $additional_qualification, $running_course, $organisation, $department, $designation,
                $course_name, $add_course, $course_start_date, $admission_date, $total_amount, $admin_note
            ];
            // There are 34 strings (all except total_amount) = 33? Let's count:
            // 1 reg_id,2 full_name,3 father_name,4 mother_name,5 dob,6 gender,7 nationality,
            // 8 id_type,9 id_number,10 photo,11 contact,12 whatsapp,13 email,14 guardian_contact,15 guardian_relation,
            // 16 present_address,17 city,18 state,19 country,20 pincode,21 education_level,22 board_university,
            // 23 stream,24 additional_qualification,25 running_course,26 organisation,27 department,28 designation,
            // 29 course_name,30 add_course,31 course_start_date,32 admission_date,33 admin_note
            // That's 33 strings, plus total_amount double => total 34 parameters.
            $types = str_repeat('s', 33) . 'd';
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                // No coupon on admin-added registrations: net & final amount equal the course fee
                $fix = $conn->prepare("UPDATE registrations SET net_amount = total_amount, final_amount = total_amount WHERE registration_id = ?");
                $fix->bind_param('s', $reg_id);
                $fix->execute();
                $_SESSION['message'] = "Registration added successfully. Registration ID: $reg_id";
                header("Location: registrations.php");
                exit;
            } else {
                $error = "Insert failed: " . $conn->error;
            }
        }
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="bg-white rounded-lg shadow-lg p-6">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Add New Registration</h2>
        <a href="registrations.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
            <i class="fas fa-arrow-left mr-2"></i> Back to List
        </a>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Personal Information -->
            <div class="border rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-3 text-gray-700 border-b pb-2">Personal Information</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                        <input type="text" name="full_name" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Father's Name</label>
                        <input type="text" name="father_name" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mother's Name</label>
                        <input type="text" name="mother_name" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                        <input type="date" name="dob" max="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                        <select name="gender" class="w-full px-3 py-2 border rounded-lg">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nationality</label>
                        <input type="text" name="nationality" value="Indian" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Type</label>
                        <select name="id_type" class="w-full px-3 py-2 border rounded-lg">
                            <?php foreach ($id_types as $type): ?>
                                <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Number <span class="text-gray-500 text-xs">(Optional)</span></label>
                        <input type="text" name="id_number" class="w-full px-3 py-2 border rounded-lg" placeholder="Enter ID number">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Photo</label>
                        <input type="file" name="photo" accept="image/*" class="w-full px-3 py-2 border rounded-lg">
                        <p class="text-xs text-gray-500 mt-1">Max 2MB, jpg/png/gif</p>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="border rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-3 text-gray-700 border-b pb-2">Contact Information</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number *</label>
                        <input type="text" name="contact" required maxlength="10" pattern="[0-9]{10}" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number</label>
                        <input type="text" name="whatsapp" maxlength="10" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input type="email" name="email" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Guardian Contact</label>
                        <input type="text" name="guardian_contact" maxlength="10" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Guardian Relation</label>
                        <input type="text" name="guardian_relation" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Present Address</label>
                        <textarea name="present_address" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input type="text" name="city" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                        <input type="text" name="state" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                        <input type="text" name="country" value="India" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pincode</label>
                        <input type="text" name="pincode" maxlength="6" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
            </div>

            <!-- Education & Course -->
            <div class="border rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-3 text-gray-700 border-b pb-2">Education & Course</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Course Name *</label>
                        <select name="course_name" required class="w-full px-3 py-2 border rounded-lg">
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['title'] . ' - ' . ucfirst($c['type'])); ?>">
                                <?php echo htmlspecialchars($c['title'] . ' - ' . ucfirst($c['type'])); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Additional Course</label>
                        <select name="add_course" class="w-full px-3 py-2 border rounded-lg">
                            <option value="">None</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['title'] . ' - ' . ucfirst($c['type'])); ?>">
                                <?php echo htmlspecialchars($c['title'] . ' - ' . ucfirst($c['type'])); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Course Start Date</label>
                        <input type="date" name="course_start_date" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Admission Date</label>
                        <input type="date" name="admission_date" value="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Education Level</label>
                        <input type="text" name="education_level" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Board/University</label>
                        <input type="text" name="board_university" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stream</label>
                        <input type="text" name="stream" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Additional Qualification</label>
                        <textarea name="additional_qualification" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Running Course</label>
                        <input type="text" name="running_course" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Organisation</label>
                        <input type="text" name="organisation" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <input type="text" name="department" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Designation</label>
                        <input type="text" name="designation" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
            </div>

            <!-- Payment & Admin Note -->
            <div class="border rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-3 text-gray-700 border-b pb-2">Payment & Notes</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Amount (₹)</label>
                        <input type="number" step="0.01" name="total_amount" value="0" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Admin Note / Additional Details</label>
                        <textarea name="admin_note" rows="3" class="w-full px-3 py-2 border rounded-lg" placeholder="Any special instructions, fee adjustments, or remarks..."></textarea>
                    </div>
                    
                    <p class="text-xs text-gray-500">Payment status will be set to 'pending'. Coupon and other fees will be applied during payment.</p>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end space-x-3">
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                <i class="fas fa-save mr-2"></i> Save Registration
            </button>
            <a href="registrations.php" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>