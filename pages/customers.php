<?php
// Customers Page Content
?>

<div class="page-header">
    <h2>Customer Management</h2>
    <p>Your permissions for this page: <?php echo implode(', ', $permissions['customers'] ?? ['View']); ?></p>
</div>

<?php if (in_array('Create', $permissions['customers'] ?? [])): ?>
<div class="form-section">
    <h3>Add New Customer</h3>
    <form method="POST" class="customer-form">
        <input type="hidden" name="action" value="add_customer">
        
        <div class="form-row">
            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>
            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone:</label>
                <input type="tel" id="phone" name="phone" required>
            </div>
        </div>
        
        <div class="form-group">
            <label for="address">Address:</label>
            <textarea id="address" name="address" rows="3" required></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="driver_license">Driver License:</label>
                <input type="text" id="driver_license" name="driver_license" required>
            </div>
            <div class="form-group">
                <label for="date_of_birth">Date of Birth:</label>
                <input type="date" id="date_of_birth" name="date_of_birth" required>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">Add Customer</button>
    </form>
</div>
<?php endif; ?>

<div class="customers-section">
    <h3>Customer List</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Driver License</th>
                <th>Date of Birth</th>
                <?php if (in_array('Edit', $permissions['customers'] ?? [])): ?>
                <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("SELECT * FROM customers ORDER BY last_name, first_name");
            while ($customer = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) . "</td>";
                echo "<td>" . htmlspecialchars($customer['email']) . "</td>";
                echo "<td>" . htmlspecialchars($customer['phone']) . "</td>";
                echo "<td>" . htmlspecialchars($customer['driver_license']) . "</td>";
                echo "<td>" . date('M j, Y', strtotime($customer['date_of_birth'])) . "</td>";
                
                if (in_array('Edit', $permissions['customers'] ?? [])) {
                    echo "<td>";
                    echo "<button class='btn btn-sm btn-edit' onclick='editCustomer(" . $customer['id'] . ")'>Edit</button>";
                    if (in_array('Delete', $permissions['customers'] ?? [])) {
                        echo " <button class='btn btn-sm btn-delete' onclick='deleteCustomer(" . $customer['id'] . ")'>Delete</button>";
                    }
                    echo "</td>";
                }
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<style>
.customer-form {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 15px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.form-group input,
.form-group textarea {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group textarea {
    resize: vertical;
}

.customers-section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function editCustomer(id) {
    // Edit functionality to be implemented
    alert('Edit customer functionality - ID: ' + id);
}

function deleteCustomer(id) {
    if (confirm('Are you sure you want to delete this customer?')) {
        // Delete functionality to be implemented
        alert('Delete customer functionality - ID: ' + id);
    }
}
</script>

