<?php
// Telephone Numbers page
if (!isset($pdo)) die("Database connection not available");

$stats = $pdo->query("SELECT COUNT(*) as total, COUNT(DISTINCT category) as categories FROM telephone_numbers")->fetch();
$categories = $pdo->query("SELECT category, COUNT(*) as count FROM telephone_numbers WHERE category IS NOT NULL AND category != '' GROUP BY category ORDER BY count DESC")->fetchAll();

$where = [];
$params = [];
if (!empty($_GET['category'])) {
    $where[] = "category = ?";
    $params[] = $_GET['category'];
}
if (!empty($_GET['search'])) {
    $where[] = "(name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $search = '%' . $_GET['search'] . '%';
    $params[] = $search; $params[] = $search; $params[] = $search;
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT * FROM telephone_numbers $whereClause ORDER BY name LIMIT 100");
$stmt->execute($params);
$numbers = $stmt->fetchAll();
?>
<div class="container-fluid">
    <h2>Telephone Numbers</h2>
    <div class="row mb-4" style="display: flex; flex-wrap: wrap; gap: 20px;">
        <div class="col-md-6" style="flex: 1; min-width: 200px;">
            <div class="card"><div class="card-body"><h5>Total Contacts</h5><h3><?= number_format($stats['total']) ?></h3></div></div>
        </div>
        <div class="col-md-6" style="flex: 1; min-width: 200px;">
            <div class="card"><div class="card-body"><h5>Categories</h5><h3><?= $stats['categories'] ?></h3></div></div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="page" value="telephone">
                <div class="col-md-4" style="flex: 1; min-width: 200px;">
                    <label>Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['category']) ?>" <?= ($_GET['category'] ?? '') === $cat['category'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category']) ?> (<?= $cat['count'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5" style="flex: 1; min-width: 200px;">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, Phone, or Email" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                <div class="col-md-3" style="flex: 1; min-width: 200px;">
                    <label>&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="?page=telephone" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-striped">
                <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Category</th></tr></thead>
                <tbody>
                    <?php foreach ($numbers as $num): ?>
                    <tr>
                        <td><?= htmlspecialchars($num['name']) ?></td>
                        <td><?= htmlspecialchars($num['phone']) ?></td>
                        <td><?= htmlspecialchars($num['email']) ?></td>
                        <td><span class="badge bg-primary"><?= htmlspecialchars($num['category']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($numbers)): ?>
                    <tr><td colspan="4" class="text-center">No contacts found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
