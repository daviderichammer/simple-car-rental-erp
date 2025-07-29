<div class="page-header">
    <h2>Customer Management</h2>
    <p>Manage customer information and profiles</p>
</div>

<?php if ($permissions->hasPermission('customers', 'create')): ?>
<div class="form-section">
    <h3>Add New Customer</h3>
    <form method="POST">
        <input type="hidden" name="action" value="add_customer">
        <div class="form-grid">
            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>
            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" required>
            </div>
            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label for="driver_license">Driver License</label>
                <input type="text" id="driver_license" name="driver_license" required>
            </div>
            <div class="form-group">
                <label for="date_of_birth">Date of Birth</label>
                <input type="date" id="date_of_birth" name="date_of_birth" required>
            </div>
        </div>
        <button type="submit" class="btn-primary">Add Customer</button>
    </form>
</div>
<?php endif; ?>

<div class="data-table">
    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">Customer List</h3>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Driver License</th>
                <th>Date of Birth</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("SELECT * FROM customers ORDER BY last_name, first_name");
            while ($row = $stmt->fetch()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                echo "<td>" . htmlspecialchars($row['driver_license']) . "</td>";
                echo "<td>" . date('M j, Y', strtotime($row['date_of_birth'])) . "</td>";
                echo "<td>";
                echo "<button class='btn-edit' onclick='editCustomer(" . $row['id'] . ")'>Edit</button>";
                echo "<button class='btn-delete' onclick='deleteCustomer(" . $row['id'] . ")'>Delete</button>";
                echo "</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Edit Customer Modal -->
<div id="editCustomerModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Customer</h3>
            <span class="close" onclick="closeModal('editCustomerModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editCustomerForm">
                <input type="hidden" id="edit_customer_id" name="id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_first_name">First Name</label>
                        <input type="text" id="edit_first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_last_name">Last Name</label>
                        <input type="text" id="edit_last_name" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_phone">Phone</label>
                        <input type="tel" id="edit_phone" name="phone" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_address">Address</label>
                        <textarea id="edit_address" name="address" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_driver_license">Driver License</label>
                        <input type="text" id="edit_driver_license" name="driver_license" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_date_of_birth">Date of Birth</label>
                        <input type="date" id="edit_date_of_birth" name="date_of_birth" required>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-primary" onclick="saveCustomer()">Save Changes</button>
            <button type="button" onclick="closeModal('editCustomerModal')" style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer;">Cancel</button>
        </div>
    </div>
</div>
<?php
