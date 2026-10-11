<?php
/* ================= OUTPUT BUFFER + SESSION ================= */
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ================= DB CONNECTION ================= */
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/payment_config.php';
$db = (new Database())->getConnection();

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ================= INIT ================= */
$errors = [];
$form = $_POST ?? [];

/* ================= FETCH COURSES ================= */
$courses_list = [];
$course_fees = [];
$stmt = $db->query("SELECT title, type, fee FROM courses WHERE is_deleted = 0 ORDER BY title DESC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $display = $row['title'] . ' - ' . ucfirst($row['type']);
    $courses_list[] = $display;
    $course_fees[$display] = (float)$row['fee'];
}

$id_types = ['Aadhaar', 'PAN', 'Voter ID', 'Passport', 'Driving Licence', 'Other'];

/* ================= FORM SUBMISSION ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['main_form'])) {
    // CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid security token.";
    }

    // Terms
    if (empty($_POST['agree_fee']) || empty($_POST['agree_attendance'])) {
        $errors[] = "You must accept all terms & conditions.";
    }

    // Basic validations
    $full_name = trim($form['full_name'] ?? '');
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $full_name)) {
        $errors[] = "Full name should contain only letters and spaces";
    }

    $contact = preg_replace('/[^0-9]/', '', $form['contact'] ?? '');
    if (strlen($contact) != 10) {
        $errors[] = "Valid 10-digit mobile number required";
    }

    $email = trim($form['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email required";
    }

    if (empty($form['gender'])) {
        $errors[] = "Gender is required";
    }
    if (empty($form['course_name'])) {
        $errors[] = "Course selection is required";
    }

    $selectedCourse = $form['course_name'] ?? '';
    $additionalCourse = $form['add_course'] ?? '';

    // Validate additional course is not same as primary
    if (!empty($additionalCourse) && $additionalCourse === $selectedCourse) {
        $errors[] = "Additional course cannot be the same as the primary course.";
    }

    $totalAmount = $course_fees[$selectedCourse] ?? 0;
    if ($totalAmount <= 0) {
        $errors[] = "Course fee not configured. Please contact institute.";
    }

    // Add additional course fee if selected
    $addCourseFee = 0;
    if (!empty($additionalCourse) && isset($course_fees[$additionalCourse])) {
        $addCourseFee = $course_fees[$additionalCourse];
        $totalAmount += $addCourseFee;
    }

    // ID fields (optional)
    $id_type = $form['id_type'] ?? 'Aadhaar';
    $id_number = preg_replace('/[^a-zA-Z0-9]/', '', $form['id_number'] ?? '');
    if (!empty($id_number) && $id_type === 'Aadhaar' && strlen($id_number) != 12) {
        $errors[] = "Aadhaar number must be exactly 12 digits";
    }

    // ---- COUPON VALIDATION ----
    $coupon_code = trim($form['coupon_code'] ?? '');
    $discount = 0;
    if ($coupon_code) {
        $stmt = $db->prepare("SELECT * FROM coupons WHERE code = ? AND status='active' AND valid_from <= CURDATE() AND valid_to >= CURDATE()");
        $stmt->execute([$coupon_code]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($coupon && $coupon['used_count'] < $coupon['max_uses'] && $totalAmount >= $coupon['min_order_amount']) {
            if ($coupon['discount_type'] == 'percentage') {
                $discount = ($totalAmount * $coupon['discount_value']) / 100;
            } else {
                $discount = (float)$coupon['discount_value'];
            }
            if ($discount > $totalAmount) $discount = $totalAmount;
            $discount = round((float)$discount, 2);
        } else {
            $coupon_code = null;
            $discount = 0;
        }
    }

    // ---- Calculate amounts (NO platform fee or GST here) ----
    $net_amount = $totalAmount - $discount;
    $platform_fee = 0;
    $gst = 0;
    $final_amount = $net_amount;   // fees will be added for online payments later

    // ---- Convert date formats (d-m-Y to Y-m-d) ----
    $course_start_date = !empty($form['course_start_date']) ? date('Y-m-d', strtotime($form['course_start_date'])) : null;
    $dob = !empty($form['dob']) ? date('Y-m-d', strtotime($form['dob'])) : null;

    // ---- If no errors, save ----
    if (empty($errors)) {
        $reg_id = 'REG' . date('Y') . rand(10000, 99999);
        // ensure unique
        $check = $db->prepare("SELECT id FROM registrations WHERE registration_id = ?");
        $check->execute([$reg_id]);
        while ($check->rowCount() > 0) {
            $reg_id = 'REG' . date('Y') . rand(10000, 99999);
            $check->execute([$reg_id]);
        }

        $stmt = $db->prepare("
            INSERT INTO registrations (
                registration_id, course_name, add_course, course_start_date, admission_date,
                full_name, dob, gender, nationality, id_type, id_number,
                contact, email, city, state, country,
                education_level, organisation,
                total_amount, coupon_code, discount_amount, net_amount,
                platform_fee, gst_amount, final_amount,
                status, payment_status, registration_date
            ) VALUES (
                :reg_id, :course, :add_course, :course_start_date, CURDATE(),
                :full_name, :dob, :gender, :nationality, :id_type, :id_number,
                :contact, :email, :city, :state, :country,
                :education, :org,
                :total, :coupon, :discount, :net,
                :platform, :gst, :final,
                'pending', 'pending', NOW()
            )
        ");

        $stmt->execute([
            ':reg_id'    => $reg_id,
            ':course'    => $selectedCourse,
            ':add_course'=> $additionalCourse ?: null,
            ':course_start_date' => $course_start_date,   // now Y-m-d
            ':full_name' => $full_name,
            ':dob'       => $dob,                         // now Y-m-d
            ':gender'    => $form['gender'],
            ':nationality' => $form['nationality'] ?? 'Indian',
            ':id_type'   => $id_type,
            ':id_number' => $id_number ?: null,
            ':contact'   => $contact,
            ':email'     => $email,
            ':city'      => $form['city'] ?? null,
            ':state'     => $form['state'] ?? null,
            ':country'   => $form['country'] ?? 'India',
            ':education' => $form['education_level'] ?? null,
            ':org'       => $form['organisation'] ?? null,
            ':total'     => $totalAmount,
            ':coupon'    => $coupon_code,
            ':discount'  => $discount,
            ':net'       => $net_amount,
            ':platform'  => $platform_fee,   // always 0
            ':gst'       => $gst,            // always 0
            ':final'     => $final_amount    // = net_amount
        ]);

        // Count coupon usage (atomic - never exceeds max_uses)
        if ($coupon_code) {
            $db->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE code = ? AND used_count < max_uses")->execute([$coupon_code]);
        }

        // Send confirmation email (optional)
        if (file_exists(__DIR__ . '/../mailer/mailer.php')) {
            require_once __DIR__ . '/../mailer/mailer.php';
            if (function_exists('sendEmail')) {
                $subject = "Registration Confirmation – Perfect Institute";
                $body = "Dear $full_name,<br>Your registration is successful.<br>Registration ID: $reg_id<br>Course: $selectedCourse<br>Please proceed to payment.";
                sendEmail($email, $full_name, $subject, $body, $body);
            }
        }

        $_SESSION['registration_id'] = $reg_id;
        header("Location: payment.php?reg_id=" . urlencode($reg_id));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration – Perfect Institute</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #e9edf2 100%); font-family: 'Inter', sans-serif; padding:2rem 1rem; }
        .container { max-width:1000px; margin:0 auto; background:#fff; border-radius:2rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden; }
        .form-header { background: linear-gradient(135deg, #0a192f 0%, #1e3a5f 100%); padding:2rem; text-align:center; color:white; }
        .form-header h1 { font-size:2rem; font-weight:700; }
        .form-body { padding:2rem; }
        h2 { font-size:1.3rem; font-weight:600; margin:1.8rem 0 1rem; padding-bottom:0.3rem; border-bottom:3px solid #e63946; display:inline-block; color:#0a192f; }
        .row { display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1.5rem; margin-bottom:0.5rem; }
        .input-group { margin-bottom:0.5rem; }
        label { display:block; font-weight:600; font-size:0.85rem; margin-bottom:0.4rem; color:#1e293b; }
        .required { color:#e63946; margin-left:3px; }
        .optional { font-size:0.7rem; font-weight:normal; color:#64748b; margin-left:5px; }
        input, select, textarea { width:100%; padding:0.75rem 1rem; border:1.5px solid #e2e8f0; border-radius:1rem; font-size:0.95rem; background:#f8fafc; transition:all 0.2s; }
        input:focus, select:focus { outline:none; border-color:#0a192f; box-shadow:0 0 0 3px rgba(10,25,47,0.1); background:#fff; }
        .error-box { background:#fff5f5; border-left:5px solid #e53e3e; padding:1rem 1.5rem; border-radius:1rem; margin-bottom:1.5rem; color:#c53030; }
        .error-box ul { padding-left:1.2rem; }
        .terms-box { background:#f1f5f9; padding:1.5rem; border-radius:1.2rem; margin:1rem 0; }
        .checkbox-line { display:flex; align-items:center; gap:0.75rem; margin-bottom:1rem; cursor:pointer; }
        .checkbox-line input { width:1.2rem; height:1.2rem; accent-color:#0a192f; }
        .btn-submit { background:linear-gradient(135deg, #0a192f 0%, #1e3a5f 100%); color:white; border:none; padding:1rem; font-size:1rem; font-weight:600; border-radius:2rem; width:100%; cursor:pointer; transition:0.2s; display:flex; align-items:center; justify-content:center; gap:0.5rem; margin-top:1.5rem; }
        .btn-submit:hover { transform:translateY(-2px); box-shadow:0 10px 20px rgba(0,0,0,0.1); }
        .coupon-box { display:flex; gap:0.5rem; align-items:center; }
        .coupon-box input { flex:1; }
        .coupon-box button { background:#0a192f; color:white; border:none; padding:0.5rem 1.2rem; border-radius:1rem; cursor:pointer; font-weight:600; }
        #couponMessage { font-size:0.85rem; margin-top:0.3rem; }
        .fee-display { background:#f1f5f9; padding:0.8rem; border-radius:1rem; margin-bottom:0.5rem; font-weight:600; }
        .loading-spinner {
            display: inline-block;
            width: 1.2rem;
            height: 1.2rem;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        @media (max-width:640px) { .form-body { padding:1rem; } }
        .fee-breakdown { font-size:0.9rem; color:#475569; margin-top:0.3rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="form-header">
        <h1><i class="fas fa-graduation-cap"></i> Registration Form</h1>
        <p>Perfect Institute of Languages – Your path to fluent English</p>
    </div>
    <div class="form-body">
        <?php if (!empty($errors)): ?>
            <div class="error-box"><ul><?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul></div>
        <?php endif; ?>

        <form method="post" id="registrationForm">
            <input type="hidden" name="main_form" value="1">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

            <!-- Course Selection -->
            <h2><i class="fas fa-book-open"></i> Course Selection</h2>
            <div class="row">
                <div class="input-group">
                    <label>Primary Course <span class="required">*</span></label>
                    <select name="course_name" id="courseSelect" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($courses_list as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" data-fee="<?= $course_fees[$c] ?? 0 ?>" <?= (isset($form['course_name']) && $form['course_name']==$c) ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group">
                    <label>Add More Courses <span class="optional">(Optional)</span></label>
                    <select name="add_course" id="addCourseSelect">
                        <option value="">-- None --</option>
                        <?php foreach ($courses_list as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" data-fee="<?= $course_fees[$c] ?? 0 ?>" <?= (isset($form['add_course']) && $form['add_course']==$c) ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="input-group">
                    <label>Course Start Date</label>
                    <input type="text" name="course_start_date" class="datepicker" placeholder="DD-MM-YYYY" value="<?= htmlspecialchars($form['course_start_date'] ?? '') ?>">
                </div>
                <div class="input-group">
                    <label>Total Course Fee</label>
                    <div class="fee-display" id="courseFeeDisplay">₹ 0.00</div>
                    <div id="feeBreakdown" class="fee-breakdown"></div>
                    <input type="hidden" id="courseFeeHidden" value="0">
                </div>
            </div>
            <div class="row">
                <div class="input-group">
                    <label>Coupon Code (if any)</label>
                    <div class="coupon-box">
                        <input type="text" name="coupon_code" id="couponCode" placeholder="Enter coupon">
                        <button type="button" id="applyCoupon">Apply</button>
                    </div>
                    <div id="couponMessage"></div>
                </div>
            </div>

            <!-- Personal Details -->
            <h2><i class="fas fa-user"></i> Personal Details</h2>
            <div class="row">
                <div class="input-group"><label>Full Name <span class="required">*</span></label><input type="text" name="full_name" required value="<?= htmlspecialchars($form['full_name'] ?? '') ?>"></div>
                <div class="input-group"><label>Date of Birth</label><input type="text" name="dob" class="datepicker" placeholder="DD-MM-YYYY" value="<?= htmlspecialchars($form['dob'] ?? '') ?>"></div>
            </div>
            <div class="row">
                <div class="input-group"><label>Gender <span class="required">*</span></label>
                    <select name="gender" required>
                        <option value="">Select</option>
                        <option value="Male" <?= (isset($form['gender']) && $form['gender']=='Male') ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= (isset($form['gender']) && $form['gender']=='Female') ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= (isset($form['gender']) && $form['gender']=='Other') ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="input-group"><label>Nationality</label><input type="text" name="nationality" value="<?= htmlspecialchars($form['nationality'] ?? 'Indian') ?>"></div>
            </div>
            <div class="row">
                <div class="input-group"><label>ID Type</label>
                    <select name="id_type">
                        <?php foreach ($id_types as $type): ?>
                            <option value="<?= $type ?>" <?= (isset($form['id_type']) && $form['id_type']==$type) ? 'selected' : '' ?>><?= $type ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group"><label>ID Number</label><input type="text" name="id_number" value="<?= htmlspecialchars($form['id_number'] ?? '') ?>"></div>
            </div>

            <!-- Contact -->
            <h2><i class="fas fa-phone-alt"></i> Contact Information</h2>
            <div class="row">
                <div class="input-group"><label>Mobile Number <span class="required">*</span></label><input type="tel" name="contact" required value="<?= htmlspecialchars($form['contact'] ?? '') ?>"></div>
                <div class="input-group"><label>Email Address <span class="required">*</span></label><input type="email" name="email" required value="<?= htmlspecialchars($form['email'] ?? '') ?>"></div>
            </div>

            <!-- Place -->
            <h2><i class="fas fa-map-marker-alt"></i> Place</h2>
            <div class="row">
                <div class="input-group"><label>City / District</label><input type="text" name="city" value="<?= htmlspecialchars($form['city'] ?? '') ?>"></div>
                <div class="input-group"><label>State</label><input type="text" name="state" value="<?= htmlspecialchars($form['state'] ?? '') ?>"></div>
                <div class="input-group"><label>Country</label><input type="text" name="country" value="<?= htmlspecialchars($form['country'] ?? 'India') ?>"></div>
            </div>

            <!-- Additional Details -->
            <h2><i class="fas fa-graduation-cap"></i> Additional Details (Optional)</h2>
            <div class="row">
                <div class="input-group"><label>Educational Qualification (Ongoing or Last Completed)</label><input type="text" name="education_level" value="<?= htmlspecialchars($form['education_level'] ?? '') ?>"></div>
                <div class="input-group"><label>Job & Organisation (if any)</label><input type="text" name="organisation" value="<?= htmlspecialchars($form['organisation'] ?? '') ?>"></div>
            </div>

            <!-- Terms -->
            <h2><i class="fas fa-file-signature"></i> Terms & Conditions</h2>
            <div class="terms-box">
                <label class="checkbox-line"><input type="checkbox" name="agree_fee" required> <span>Fee once paid is non‑refundable.</span></label>
                <label class="checkbox-line"><input type="checkbox" name="agree_attendance" required> <span>I will be regular & punctual during the course.</span></label>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn"><i class="fas fa-paper-plane"></i> Submit & Proceed to Payment</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    // Flatpickr for date fields
    flatpickr(".datepicker", { dateFormat: "d-m-Y", allowInput: true });

    // Course selection => update fee display
    const courseSelect = document.getElementById('courseSelect');
    const addCourseSelect = document.getElementById('addCourseSelect');
    const feeDisplay = document.getElementById('courseFeeDisplay');
    const feeHidden = document.getElementById('courseFeeHidden');
    const feeBreakdown = document.getElementById('feeBreakdown');

    function updateFee() {
        const primaryOption = courseSelect.options[courseSelect.selectedIndex];
        const primaryFee = primaryOption ? parseFloat(primaryOption.dataset.fee) || 0 : 0;
        const primaryName = primaryOption ? primaryOption.value : '';

        const addOption = addCourseSelect.options[addCourseSelect.selectedIndex];
        const addFee = addOption ? parseFloat(addOption.dataset.fee) || 0 : 0;
        const addName = addOption ? addOption.value : '';

        // Prevent selecting same course as primary
        if (addName && addName === primaryName) {
            addCourseSelect.value = '';
            updateFee();
            return;
        }

        const total = primaryFee + addFee;
        feeDisplay.textContent = '₹ ' + total.toFixed(2);
        feeHidden.value = total;

        let breakdown = '';
        if (primaryFee > 0) {
            breakdown += `Primary: ₹${primaryFee.toFixed(2)}`;
        }
        if (addFee > 0) {
            breakdown += ` + Additional: ₹${addFee.toFixed(2)}`;
        }
        if (breakdown) {
            breakdown = `Total: ₹${total.toFixed(2)} (${breakdown})`;
        } else {
            breakdown = 'Please select a course';
        }
        feeBreakdown.textContent = breakdown;

        document.getElementById('couponMessage').innerHTML = '';
        document.getElementById('couponCode').value = '';
    }

    courseSelect.addEventListener('change', function() {
        const primaryValue = this.value;
        const addOptions = addCourseSelect.options;
        for (let i = 0; i < addOptions.length; i++) {
            if (addOptions[i].value === primaryValue && addOptions[i].value !== '') {
                addOptions[i].disabled = true;
            } else {
                addOptions[i].disabled = false;
            }
        }
        if (addCourseSelect.value === primaryValue) {
            addCourseSelect.value = '';
        }
        updateFee();
    });

    addCourseSelect.addEventListener('change', function() {
        if (this.value === courseSelect.value) {
            this.value = '';
        }
        updateFee();
    });

    updateFee();

    // Coupon apply AJAX
    document.getElementById('applyCoupon').addEventListener('click', function() {
        const code = document.getElementById('couponCode').value.trim();
        const fee = parseFloat(feeHidden.value);
        const msgDiv = document.getElementById('couponMessage');
        if (!code) { msgDiv.innerHTML = '<span style="color:red;">Please enter a coupon code</span>'; return; }
        if (!fee || fee <= 0) { msgDiv.innerHTML = '<span style="color:red;">Please select a course first</span>'; return; }

        fetch('validate-coupon.php?code=' + encodeURIComponent(code) + '&amount=' + fee)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    msgDiv.innerHTML = '<span style="color:green;">Coupon applied! Discount: ₹' + Number(data.discount).toFixed(2) + '</span>';
                } else {
                    msgDiv.innerHTML = '<span style="color:red;">' + data.message + '</span>';
                }
            })
            .catch(() => {
                msgDiv.innerHTML = '<span style="color:red;">Error validating coupon</span>';
            });
    });

    // Form submission validation
    const form = document.getElementById('registrationForm');
    form.addEventListener('submit', function(e) {
        const name = form.querySelector('input[name="full_name"]').value.trim();
        if (!/^[a-zA-Z\s]+$/.test(name)) {
            alert('Full name should contain only letters and spaces.');
            e.preventDefault(); return false;
        }
        const contact = form.querySelector('input[name="contact"]').value.trim();
        if (!/^\d{10}$/.test(contact)) {
            alert('Contact number must be exactly 10 digits.');
            e.preventDefault(); return false;
        }
        const email = form.querySelector('input[name="email"]').value.trim();
        if (!/^\S+@\S+\.\S+$/.test(email)) {
            alert('Please enter a valid email address.');
            e.preventDefault(); return false;
        }
        if (!form.querySelector('input[name="agree_fee"]').checked || !form.querySelector('input[name="agree_attendance"]').checked) {
            alert('Please accept all Terms & Conditions.');
            e.preventDefault(); return false;
        }
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="loading-spinner"></span> Submitting...';
    });
</script>
</body>
</html>
<?php ob_end_flush(); ?>