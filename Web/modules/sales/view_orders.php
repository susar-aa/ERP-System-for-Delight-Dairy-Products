<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$msg = "";

// --- HANDLE ACTIONS ---

// DELETE INVOICE
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    
    // Only Admin can delete invoices usually, but we'll allow it here for now
    $conn->begin_transaction();
    try {
        // Delete items first (Foreign Key constraint)
        $conn->query("DELETE FROM mobile_order_items WHERE mobile_order_id = $del_id");
        // Delete Header
        $conn->query("DELETE FROM mobile_orders WHERE id = $del_id");
        
        $conn->commit();
        $msg = "<div class='alert success'>Invoice deleted successfully.</div>";
    } catch (Exception $e) {
        $conn->rollback();
        $msg = "<div class='alert error'>Error deleting invoice: " . $e->getMessage() . "</div>";
    }
}

// --- FILTERS & SEARCH ---
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$status_filter = $_GET['status'] ?? '';
$route_filter = $_GET['route_id'] ?? '';
$rep_filter = $_GET['rep_id'] ?? '';
$search_query = $_GET['search'] ?? '';

// Base SQL
$sql = "SELECT o.*, c.shop_name, u.full_name as rep_name 
        FROM mobile_orders o 
        LEFT JOIN customers c ON o.customer_id = c.id 
        LEFT JOIN sales_reps sr ON o.rep_id = sr.id 
        LEFT JOIN users u ON sr.user_id = u.id 
        WHERE o.order_date BETWEEN '$start_date' AND '$end_date'";

// Apply Filters
if (!empty($status_filter)) {
    $sql .= " AND o.payment_status = '$status_filter'";
}
if (!empty($route_filter)) {
    $sql .= " AND c.route_id = '$route_filter'";
}
if (!empty($rep_filter)) {
    $sql .= " AND o.rep_id = '$rep_filter'";
}
if (!empty($search_query)) {
    $sql .= " AND (o.order_ref LIKE '%$search_query%' OR c.shop_name LIKE '%$search_query%')";
}

$sql .= " ORDER BY o.order_date DESC, o.id DESC";
$orders = $conn->query($sql);

// --- KPI CALCULATIONS (For Current Month Context) ---
// Note: KPIs should also respect the active filters for accuracy
$kpi_where = "order_date BETWEEN '$start_date' AND '$end_date'";
if (!empty($status_filter)) $kpi_where .= " AND payment_status = '$status_filter'";
if (!empty($rep_filter)) $kpi_where .= " AND rep_id = '$rep_filter'";

$kpi_sql = "SELECT 
            COUNT(*) as total_count,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN payment_status NOT IN ('paid', 'cash', 'bank_transfer') THEN total_amount ELSE 0 END), 0) as pending_amt
            FROM mobile_orders o
            LEFT JOIN customers c ON o.customer_id = c.id
            WHERE $kpi_where";

if (!empty($route_filter)) {
    $kpi_sql .= " AND c.route_id = '$route_filter'";
}

$kpi = $conn->query($kpi_sql)->fetch_assoc();

// --- FETCH MASTERS FOR FILTERS ---
$routes = $conn->query("SELECT * FROM routes ORDER BY route_name");
$reps = $conn->query("SELECT sr.id, u.full_name FROM sales_reps sr JOIN users u ON sr.user_id = u.id ORDER BY u.full_name");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Invoices | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Layout */
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-new { background: #27ae60; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-flex; align-items: center; gap: 8px; }
        .btn-new:hover { background: #219150; }

        /* KPI Cards */
        .kpi-row { display: flex; gap: 20px; margin-bottom: 25px; }
        .kpi-card { flex: 1; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; border-left: 4px solid #3498db; }
        .kpi-info h4 { margin: 0 0 5px 0; color: #7f8c8d; font-size: 12px; text-transform: uppercase; }
        .kpi-info span { font-size: 22px; font-weight: bold; color: #2c3e50; }
        .kpi-icon { font-size: 30px; opacity: 0.2; }
        .border-green { border-color: #27ae60; } .text-green { color: #27ae60; }
        .border-red { border-color: #c0392b; } .text-red { color: #c0392b; }

        /* Filters */
        .filter-box { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 12px; font-weight: bold; color: #555; }
        .filter-group input, .filter-group select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; }
        .btn-filter { background: #34495e; color: white; border: none; padding: 9px 20px; border-radius: 4px; cursor: pointer; height: 35px; }
        
        /* Table */
        .invoice-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-radius: 8px; overflow: hidden; }
        .invoice-table th { background: #34495e; color: white; padding: 12px 15px; text-align: left; font-size: 13px; text-transform: uppercase; }
        .invoice-table td { padding: 12px 15px; border-bottom: 1px solid #eee; font-size: 14px; color: #333; }
        .invoice-table tr:hover { background: #f9f9f9; }

        /* Badges & Buttons */
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .st-paid, .st-cash { background: #d4edda; color: #155724; }
        .st-credit { background: #fadbd8; color: #721c24; }
        .st-cheque { background: #fff3cd; color: #856404; }
        .st-bank_transfer { background: #d1ecf1; color: #0c5460; }

        .btn-icon { padding: 5px 8px; border-radius: 4px; border: 1px solid transparent; cursor: pointer; text-decoration: none; display: inline-block; font-size: 12px; margin-right: 2px; }
        .btn-view { background: #e8f4fd; color: #3498db; border-color: #b8daff; }
        .btn-edit { background: #fff3cd; color: #856404; border-color: #ffeeba; }
        .btn-del { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .btn-green { background: #e9f7ef; color: #27ae60; border-color: #d4efdf; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        
        <div class="top-bar">
            <div>
                <h2 style="margin:0;">Invoice Management</h2>
                <p style="color:#777; margin:5px 0 0 0;">View, filter and manage sales history.</p>
            </div>
            <a href="invoice_panel.php" class="btn-new">
                <i class="fas fa-plus-circle"></i> Create New Invoice
            </a>
        </div>

        <?php echo $msg; ?>

        <!-- 1. KPI CARDS -->
        <div class="kpi-row">
            <div class="kpi-card border-green">
                <div class="kpi-info">
                    <h4>Total Revenue (Selected Period)</h4>
                    <span class="text-green">Rs <?php echo number_format($kpi['total_revenue'], 2); ?></span>
                </div>
                <i class="fas fa-coins kpi-icon text-green"></i>
            </div>
            <div class="kpi-card border-red">
                <div class="kpi-info">
                    <h4>Outstanding / Credit</h4>
                    <span class="text-red">Rs <?php echo number_format($kpi['pending_amt'], 2); ?></span>
                </div>
                <i class="fas fa-hand-holding-usd kpi-icon text-red"></i>
            </div>
            <div class="kpi-card">
                <div class="kpi-info">
                    <h4>Total Invoices</h4>
                    <span><?php echo number_format($kpi['total_count']); ?></span>
                </div>
                <i class="fas fa-file-invoice kpi-icon"></i>
            </div>
        </div>

        <!-- 2. FILTERS -->
        <form method="GET" class="filter-box">
            <div class="filter-group">
                <label>From Date</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="filter-group">
                <label>To Date</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            
            <div class="filter-group">
                <label>Route</label>
                <select name="route_id">
                    <option value="">All Routes</option>
                    <?php 
                    $routes->data_seek(0);
                    while($r = $routes->fetch_assoc()): ?>
                        <option value="<?php echo $r['id']; ?>" <?php echo $route_filter == $r['id'] ? 'selected' : ''; ?>>
                            <?php echo $r['route_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Sales Rep</label>
                <select name="rep_id">
                    <option value="">All Reps</option>
                    <?php 
                    $reps->data_seek(0);
                    while($rp = $reps->fetch_assoc()): ?>
                        <option value="<?php echo $rp['id']; ?>" <?php echo $rep_filter == $rp['id'] ? 'selected' : ''; ?>>
                            <?php echo $rp['full_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Status</label>
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="paid" <?php echo $status_filter=='paid'?'selected':''; ?>>Paid</option>
                    <option value="cash" <?php echo $status_filter=='cash'?'selected':''; ?>>Cash</option>
                    <option value="credit" <?php echo $status_filter=='credit'?'selected':''; ?>>Credit</option>
                    <option value="cheque" <?php echo $status_filter=='cheque'?'selected':''; ?>>Cheque</option>
                    <option value="bank_transfer" <?php echo $status_filter=='bank_transfer'?'selected':''; ?>>Bank Transfer</option>
                </select>
            </div>
            
            <div class="filter-group" style="flex:1;">
                <label>Search</label>
                <input type="text" name="search" placeholder="Invoice # or Customer Name..." value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filter</button>
            <a href="view_orders.php" style="margin-bottom:8px; color:#c0392b; font-size:13px; text-decoration:none;">Reset</a>
        </form>

        <!-- 3. DATA TABLE -->
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Invoice #</th>
                    <th>Customer / Shop</th>
                    <th>Sales Rep</th>
                    <th style="text-align:right;">Amount (Rs)</th>
                    <th>Status</th>
                    <th style="text-align:center; width:180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($orders->num_rows > 0): ?>
                    <?php while($row = $orders->fetch_assoc()): 
                        $st = strtolower(str_replace(' ', '_', $row['payment_status']));
                    ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($row['order_date'])); ?></td>
                        <td style="font-weight:bold; color:#2980b9;"><?php echo $row['order_ref']; ?></td>
                        <td>
                            <?php echo htmlspecialchars($row['shop_name']); ?>
                        </td>
                        <td><span style="color:#777; font-size:13px;"><?php echo htmlspecialchars($row['rep_name'] ?? '-'); ?></span></td>
                        <td style="text-align:right; font-weight:bold;">
                            <?php echo number_format($row['total_amount'], 2); ?>
                        </td>
                        <td>
                            <span class="status-badge st-<?php echo $st; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $st)); ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <!-- View Only -->
                            <a href="invoice_panel.php?id=<?php echo $row['id']; ?>&mode=view" class="btn-icon btn-green" title="View Only">
                                <i class="fas fa-eye"></i>
                            </a>
                            <!-- Edit -->
                            <a href="invoice_panel.php?id=<?php echo $row['id']; ?>" class="btn-icon btn-edit" title="Edit Invoice">
                                <i class="fas fa-edit"></i>
                            </a>
                            <!-- Print -->
                            <a href="print_invoice.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn-icon btn-view" title="Print/View">
                                <i class="fas fa-print"></i>
                            </a>
                            <!-- Delete -->
                            <a href="?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure? This will delete the invoice record permanently.')" class="btn-icon btn-del" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding:30px; color:#aaa;">
                            <i class="fas fa-search" style="font-size:30px; margin-bottom:10px;"></i><br>
                            No invoices found matching your criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div>

    <!-- Sidebar JS -->
    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>