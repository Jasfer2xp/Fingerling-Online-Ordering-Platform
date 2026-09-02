<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';   // ← ADD THIS LINE
require_once '../config/database.php';
require_once '../classes/User.php';

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);
$customer_id = $profile['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add_address':
                $user->addCustomerAddress([
                    'customer_id' => $customer_id,
                    'label' => $_POST['label'],
                    'recipient_name' => $_POST['recipient_name'],
                    'phone' => $_POST['phone'],
                    'address_line_1' => $_POST['address_line_1'],
                    'address_line_2' => $_POST['address_line_2'] ?? '',
                    'barangay' => $_POST['barangay'],
                    'city' => $_POST['city'],
                    'province' => $_POST['province'],
                    'postal_code' => $_POST['postal_code'] ?? '',
                    'is_default' => isset($_POST['is_default'])
                ]);
                $_SESSION['success'] = 'Address added successfully';
                break;

            case 'update_address':
                $user->updateCustomerAddress(
                    intval($_POST['address_id']),
                    $customer_id,
                    [
                        'label' => $_POST['label'],
                        'recipient_name' => $_POST['recipient_name'],
                        'phone' => $_POST['phone'],
                        'address_line_1' => $_POST['address_line_1'],
                        'address_line_2' => $_POST['address_line_2'] ?? '',
                        'barangay' => $_POST['barangay'],
                        'city' => $_POST['city'],
                        'province' => $_POST['province'],
                        'postal_code' => $_POST['postal_code'] ?? '',
                        'is_default' => isset($_POST['is_default'])
                    ]
                );
                $_SESSION['success'] = 'Address updated successfully';
                break;

            case 'delete_address':
                $user->deleteCustomerAddress(intval($_POST['address_id']), $customer_id);
                $_SESSION['success'] = 'Address deleted successfully';
                break;

            case 'set_default':
                $user->setDefaultAddress(intval($_POST['address_id']), $customer_id);
                $_SESSION['success'] = 'Default address updated';
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    redirect(base_url('customer/addresses.php'));
}

$addresses = $user->getCustomerAddresses($customer_id);
$page_title = 'Delivery Addresses';
include '../includes/customer_header.php';
?>

<style>
/* ---------- GENERAL LAYOUT ---------- */
body {
    background-color: #f8f9fa;
    color: #333;
    font-family: 'Segoe UI', sans-serif;
}

.addresses-container {
    display: flex;
    max-width: 1200px;
    margin: 40px auto;
    gap: 30px;
    padding: 0 20px;
}

/* ---------- SIDEBAR ---------- */
.profile-sidebar {
    background: #fff;
    border-radius: 10px;
    width: 260px;
    flex-shrink: 0;
    padding: 20px;
    position: sticky;
    top: 20px;
    height: fit-content;
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
    border: 1px solid #eee;
}

.profile-sidebar h3 {
    font-size: 18px;
    margin-bottom: 15px;
    color: #222;
    font-weight: 600;
}

.sidebar-section {
    margin-bottom: 25px;
}

.sidebar-section ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-section ul li a {
    display: block;
    padding: 10px 12px;
    color: #555;
    border-radius: 6px;
    text-decoration: none;
    transition: background 0.2s, color 0.2s;
}

.sidebar-section ul li a:hover,
.sidebar-section ul li a.active {
    background: #f0f0f0;
    color: #222;
}

/* ---------- MAIN CONTENT ---------- */
.addresses-main {
    background: #fff;
    flex: 1;
    border-radius: 10px;
    padding: 25px 30px;
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
    border: 1px solid #eee;
}

.addresses-main h2 {
    font-size: 22px;
    margin-bottom: 20px;
    color: #222;
}

.address-card {
    background: #f8f9fa;
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.address-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.address-card-header h4 {
    margin: 0;
    color: #222;
    font-size: 18px;
}

.badge-default {
    background: #ff6600;
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.address-details {
    color: #444;
    line-height: 1.6;
    margin-bottom: 15px;
}

.address-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-primary, .btn-danger, .btn-outline {
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    border: 1px solid transparent;
    transition: all 0.2s;
}

.btn-primary {
    background: #ff6600;
    color: white;
    border: none;
}

.btn-primary:hover {
    background: #ff7b1c;
}

.btn-danger {
    background: #dc3545;
    color: white;
    border: none;
}

.btn-danger:hover {
    background: #c82333;
}

.btn-outline {
    background: transparent;
    color: #495057;
    border: 1px solid #ced4da;
}

.btn-outline:hover {
    background: #e9ecef;
}

.btn-text {
    background: none;
    border: none;
    color: #ff6600;
    cursor: pointer;
    padding: 4px 8px;
    font-size: 14px;
}

.btn-text:hover {
    text-decoration: underline;
}

/* Modal styles */
.modal-content {
    background: #fff;
    border: none;
    border-radius: 10px;
}

.modal-header {
    border-bottom: 1px solid #eee;
    padding: 15px 20px;
}

.modal-title {
    color: #222;
    font-size: 20px;
    font-weight: 600;
}

.modal-body {
    padding: 20px;
}

.form-label {
    font-weight: 500;
    color: #444;
    margin-bottom: 5px;
    display: block;
}

.form-control, .form-select {
    background: #fff;
    border: 1px solid #ddd;
    color: #333;
    border-radius: 6px;
    padding: 10px;
    width: 100%;
    margin-bottom: 15px;
}

.form-control:focus, .form-select:focus {
    border-color: #999;
    outline: none;
    box-shadow: 0 0 0 3px rgba(0,0,0,0.05);
}

.form-check-input {
    margin-right: 8px;
}

.alert {
    padding: 12px 18px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

/* ---------- RESPONSIVE ---------- */
@media (max-width: 900px) {
    .addresses-container { flex-direction: column; }
    .profile-sidebar { width: 100%; position: static; }
}
</style>

<main class="addresses-container">
    <aside class="profile-sidebar">
        <div class="sidebar-section">
            <h3>My Account</h3>
            <ul>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="addresses.php" class="active">Addresses</a></li>
                <li><a href="settings.php">Change Password</a></li>
            </ul>
        </div>

        <div class="sidebar-section">
            <h3>My Purchase</h3>
            <ul>
                <li><a href="orders.php">Orders</a></li>
            </ul>
        </div>
    </aside>

    <section class="addresses-main">
        <div class="main-content">

            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <h1 class="h2">Delivery Addresses</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addressModal">
                    Add Address
                </button>
            </div>

            <?php if (empty($addresses)): ?>
                <div class="text-center py-5">
                    <h4 class="text-muted">No delivery addresses yet</h4>
                    <p class="text-muted mb-4">Add one to make checkout faster and easier.</p>
                    <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addressModal">
                        Add Address
                    </button>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($addresses as $address): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 <?php echo $address['is_default'] ? 'border-primary' : ''; ?>">
                                <?php if ($address['is_default']): ?>
                                    <div class="card-header">
                                        Default Address
                                    </div>
                                <?php endif; ?>
                                <div class="card-body">
                                    <h6 class="card-title mb-2">
                                        <?php echo htmlspecialchars($address['label']); ?>
                                    </h6>
                                    <address class="mb-3 text-muted">
                                        <strong><?php echo htmlspecialchars($address['recipient_name']); ?></strong><br>
                                        <?php echo htmlspecialchars($address['address_line_1']); ?><br>
                                        <?php if ($address['address_line_2']): ?>
                                            <?php echo htmlspecialchars($address['address_line_2']); ?><br>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($address['barangay']); ?>, 
                                        <?php echo htmlspecialchars($address['city']); ?><br>
                                        <?php echo htmlspecialchars($address['province']); ?>
                                        <?php if ($address['postal_code']): ?>
                                            <?php echo htmlspecialchars($address['postal_code']); ?>
                                        <?php endif; ?>
                                    </address>
                                    <p class="text-muted mb-0">
                                        <?php echo htmlspecialchars($address['phone']); ?>
                                    </p>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <div class="btn-group w-100" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick='editAddress(<?php echo json_encode($address); ?>)'>
                                            Edit
                                        </button>
                                        <?php if (!$address['is_default']): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="set_default">
                                                <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success">
                                                    Default
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteAddress(<?php echo $address['id']; ?>, '<?php echo htmlspecialchars($address['label']); ?>')">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<!-- Modal Forms -->
<div class="modal fade" id="addressModal" tabindex="-1" aria-labelledby="addressModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addressModalTitle">Add Address</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add_address">
                    <input type="hidden" name="address_id" id="addressId" value="">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="label" class="form-label">Address Label *</label>
                            <input type="text" class="form-control" id="label" name="label" required>
                            <div class="form-text">e.g. Home, Office, etc.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="recipient_name" class="form-label">Recipient Name *</label>
                            <input type="text" class="form-control" id="recipient_name" name="recipient_name" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number *</label>
                        <input type="tel" class="form-control" id="phone" name="phone" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address_line_1" class="form-label">Address Line 1 *</label>
                        <input type="text" class="form-control" id="address_line_1" name="address_line_1" placeholder="Street address" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address_line_2" class="form-label">Address Line 2</label>
                        <input type="text" class="form-control" id="address_line_2" name="address_line_2" placeholder="Apartment, suite, unit, building, floor, etc.">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="barangay" class="form-label">Barangay *</label>
                            <select class="form-select" id="barangay" name="barangay" required>
                                <option value="">Select Barangay</option>
                                <?php
                                $barangays = [
                                    'Aquino (Marcos)',
                                    'Balatacan',
                                    'Baluk',
                                    'Banglay',
                                    'Bintana',
                                    'Bocator',
                                    'Bongabong',
                                    'Caniangan',
                                    'Capalaran',
                                    'Catagan',
                                    'Barangay I – City Hall (Poblacion)',
                                    'Barangay II – Marilou Annex (Poblacion)',
                                    'Barangay III – Market/Kalubian (Poblacion)',
                                    'Barangay IV – St. Michael (Poblacion)',
                                    'Barangay V – Malubog (Poblacion)',
                                    'Barangay VI – Lower Polao (Poblacion)',
                                    'Barangay VII – Upper Polao (Poblacion)',
                                    'Garang',
                                    'Guinabot',
                                    'Guinalaban',
                                    'Hoyohoy',
                                    'Isidro D. Tan (Dimalooc)',
                                    'Kauswagan',
                                    'Kimat',
                                    'Labuyo',
                                    'Lorenzo Tan',
                                    'Lumban',
                                    'Maloro',
                                    'Mantic',
                                    'Manga',
                                    'Maquilao',
                                    'Matugnaw',
                                    'Migcanaway',
                                    'Minsubong',
                                    'Owayan',
                                    'Paiton',
                                    'Panalsalan',
                                    'Pangabuan',
                                    'Prenza',
                                    'Salimpuno',
                                    'San Antonio',
                                    'San Apolinario',
                                    'San Vicente',
                                    'Santa Cruz',
                                    'Santa Maria (Baga)',
                                    'Santo Niño',
                                    'Sicot',
                                    'Silanga',
                                    'Silangit',
                                    'Simasay',
                                    'Sumirap',
                                    'Taguite',
                                    'Tituron',
                                    'Tugas',
                                    'Villaba'
                                ];
                                foreach ($barangays as $barangay_option):
                                ?>
                                    <option value="<?php echo htmlspecialchars($barangay_option); ?>">
                                        <?php echo htmlspecialchars($barangay_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="city" class="form-label">City/Municipality *</label>
                            <input type="text" class="form-control" id="city" name="city" value="Tangub City" readonly required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="province" class="form-label">Province *</label>
                            <input type="text" class="form-control" id="province" name="province" value="Misamis Occidental" readonly required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="postal_code" class="form-label">Postal Code</label>
                        <input type="text" class="form-control" id="postal_code" name="postal_code">
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_default" name="is_default">
                        <label class="form-check-label" for="is_default">
                            Set as default address
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Add Address</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Address Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Delete Address</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_address">
                    <input type="hidden" name="address_id" id="deleteAddressId" value="">
                    <p>Are you sure you want to delete the address "<strong id="deleteAddressLabel"></strong>"?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Address</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/customer_footer.php'; ?>

<script>
function editAddress(address) {
    document.getElementById('addressModalTitle').textContent = 'Edit Address';
    document.getElementById('formAction').value = 'update_address';
    document.getElementById('addressId').value = address.id;
    document.getElementById('submitBtn').textContent = 'Update Address';

    ['label','recipient_name','phone','address_line_1','address_line_2'].forEach(f => {
        document.getElementById(f).value = address[f] || '';
    });

    // Set barangay from address data
    const barangaySelect = document.getElementById('barangay');
    const options = barangaySelect.options;
    for (let i = 0; i < options.length; i++) {
        if (options[i].value === address.barangay) {
            barangaySelect.selectedIndex = i;
            break;
        }
    }

    document.getElementById('city').value = 'Tangub City';
    document.getElementById('province').value = 'Misamis Occidental';
    document.getElementById('postal_code').value = address.postal_code || '';
    document.getElementById('is_default').checked = address.is_default == 1;

    new bootstrap.Modal(document.getElementById('addressModal')).show();
}

function deleteAddress(id, label) {
    document.getElementById('deleteAddressId').value = id;
    document.getElementById('deleteAddressLabel').textContent = label;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

</body>
</html>