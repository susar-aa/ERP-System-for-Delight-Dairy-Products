<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

// --- AJAX HANDLER FOR ADDING CUSTOMER ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    if ($_POST['ajax_action'] == 'add_customer') {
        $name = $_POST['shop_name'];
        $phone = $_POST['phone'];
        $addr = $_POST['address'];
        $route = 1; // Default route if not specified

        $stmt = $conn->prepare("INSERT INTO customers (shop_name, phone, address, route_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $name, $phone, $addr, $route);
        if($stmt->execute()) {
            echo json_encode(['status'=>'success', 'id'=>$stmt->insert_id, 'name'=>$name, 'addr'=>$addr, 'phone'=>$phone]);
        } else {
            echo json_encode(['status'=>'error', 'message'=>$conn->error]);
        }
    }
    exit;
}

$msg = "";
$active_order = null;
$active_items = [];
$mode = 'new'; 

// --- 1. HANDLE SAVE INVOICE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save') {
    $order_id = $_POST['order_id'] ?? 0;
    $cust_id = $_POST['customer_id'];
    $date = $_POST['order_date'];
    $pay_term = $_POST['payment_term']; 
    $rep_id = $_POST['rep_id'];
    $desc = $_POST['description'] ?? '';
    $final_total = floatval($_POST['final_net_amt']); 
    
    $conn->begin_transaction();
    try {
        if ($order_id > 0) {
            // UPDATE
            $stmt = $conn->prepare("UPDATE mobile_orders SET customer_id=?, rep_id=?, order_date=?, total_amount=?, payment_status=? WHERE id=?");
            $stmt->bind_param("iisdsi", $cust_id, $rep_id, $date, $final_total, $pay_term, $order_id);
            $stmt->execute();
            $conn->query("DELETE FROM mobile_order_items WHERE mobile_order_id = $order_id");
            $new_id = $order_id;
            $msg = "Invoice Updated Successfully!";
        } else {
            // INSERT
            $ref = "INV-" . date("Ymd") . "-" . rand(1000, 9999);
            $stmt = $conn->prepare("INSERT INTO mobile_orders (order_ref, customer_id, rep_id, order_date, total_amount, payment_status, sync_status) VALUES (?, ?, ?, ?, ?, ?, 'synced')");
            $stmt->bind_param("siisds", $ref, $cust_id, $rep_id, $date, $final_total, $pay_term);
            $stmt->execute();
            $new_id = $stmt->insert_id;
            $msg = "Invoice Created: $ref";
        }

        // INSERT ITEMS
        if(isset($_POST['item_id']) && is_array($_POST['item_id'])){
            $stmt_item = $conn->prepare("INSERT INTO mobile_order_items (mobile_order_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
            for($i=0; $i < count($_POST['item_id']); $i++){
                $pid = intval($_POST['item_id'][$i]);
                $qty = floatval($_POST['item_qty'][$i]);
                $price = floatval($_POST['item_rate'][$i]);
                $line_total = floatval($_POST['item_net_total'][$i]);
                
                if($pid > 0 && $qty > 0) {
                    $stmt_item->bind_param("iiidd", $new_id, $pid, $qty, $price, $line_total);
                    $stmt_item->execute();
                }
            }
        }

        $conn->commit();
        header("Location: invoice_panel.php?id=$new_id&msg=saved");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $msg = "Error: " . $e->getMessage();
    }
}

// --- 2. LOAD DATA (EDIT MODE) ---
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $res = $conn->query("SELECT * FROM mobile_orders WHERE id = $id");
    if($res->num_rows > 0){
        $active_order = $res->fetch_assoc();
        $mode = 'edit';
        $items_res = $conn->query("SELECT i.*, p.product_name FROM mobile_order_items i JOIN products p ON i.product_id = p.id WHERE i.mobile_order_id = $id");
        while($row = $items_res->fetch_assoc()){
            $gross = $row['quantity'] * $row['unit_price'];
            $row['dis_per'] = ($gross > 0) ? round((($gross - $row['line_total']) / $gross) * 100, 2) : 0;
            $active_items[] = $row;
        }
    }
}

// --- 3. FETCH MASTERS ---
$customers = $conn->query("SELECT * FROM customers ORDER BY shop_name");

// Fetch Products WITH Stock Logic
// Joining with product_stock table to get SUM of available quantity
$products = $conn->query("
    SELECT p.*, COALESCE(SUM(ps.quantity_available), 0) as current_stock 
    FROM products p 
    LEFT JOIN product_stock ps ON p.id = ps.product_id 
    GROUP BY p.id 
    ORDER BY p.product_name
");

$reps = $conn->query("SELECT sr.id, u.full_name FROM sales_reps sr JOIN users u ON sr.user_id = u.id");

// Prepare JS Arrays
$prod_js = [];
while($p = $products->fetch_assoc()){ $prod_js[] = $p; }

$cust_js = [];
while($c = $customers->fetch_assoc()){ $cust_js[] = $c; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoicing Panel | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body { background: #f0f0f0; font-family: 'Segoe UI', sans-serif; font-size: 13px; color:#333; }
        .main-content { margin-left: 260px; padding: 15px; }
        
        /* Toolbar & Header */
        .toolbar { background: #fff; padding: 8px; border-bottom: 2px solid #ddd; margin-bottom: 15px; display: flex; gap: 10px; box-shadow: 0 2px 3px rgba(0,0,0,0.05); }
        .tool-btn { border: 1px solid #ccc; background: #f9f9f9; padding: 6px 12px; cursor: pointer; border-radius: 4px; display: flex; align-items: center; gap: 6px; font-weight: 600; font-size: 13px; }
        .tool-btn:hover { background: #eef; border-color: #3498db; color: #2980b9; }

        .header-section { background: #fff; padding: 20px; border-radius: 5px; display: flex; gap: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .h-col { display: flex; flex-direction: column; gap: 10px; flex: 1; }
        .form-line { display: flex; align-items: center; }
        .form-line label { width: 100px; font-weight: 600; color: #555; font-size: 12px; }
        .form-line input, .form-line select, .form-line textarea { flex: 1; padding: 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 13px; }

        /* Summary Box */
        .summary-box { background: #f1f2f6; border: 1px solid #dcdde1; padding: 15px; border-radius: 5px; flex: 0 0 300px; }
        .sum-line { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .sum-line input { width: 140px; text-align: right; padding: 5px; font-weight: bold; border: 1px solid #ccc; }

        /* Item Entry Bar */
        .item-entry { background: #fff; padding: 15px; margin-top: 15px; border-radius: 5px; display: flex; align-items: flex-end; gap: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .entry-group { display: flex; flex-direction: column; gap: 5px; }
        .entry-group label { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #777; }
        .entry-group input { padding: 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 13px; }
        
        .btn-add { background: #2ecc71; color: white; border: none; padding: 0 20px; height: 32px; border-radius: 3px; cursor: pointer; font-weight: bold; }
        .btn-update { background: #f39c12; color: white; } /* Update Mode Style */

        /* Searchable Select Styles */
        .search-select { position: relative; flex: 1; }
        .search-select input { width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 3px; }
        .search-results { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ccc; z-index: 100; max-height: 200px; overflow-y: auto; display: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .search-item { padding: 8px; cursor: pointer; display: flex; justify-content: space-between; border-bottom: 1px solid #f0f0f0; }
        .search-item:hover { background: #f9f9f9; }
        .stock-badge { background: #3498db; color: white; padding: 1px 6px; border-radius: 10px; font-size: 10px; }
        .stock-low { background: #e74c3c; }

        /* Grid */
        .grid-container { margin-top: 15px; background: #fff; border-radius: 5px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); min-height: 300px; }
        .grid-table { width: 100%; border-collapse: collapse; }
        .grid-table th { background: #34495e; color: #fff; padding: 10px; text-align: left; font-size: 12px; }
        .grid-table td { padding: 8px 10px; border-bottom: 1px solid #eee; cursor: pointer; }
        .grid-table tr:hover { background: #eaf2f8; } /* Highlight on hover */
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 20px; border-radius: 5px; width: 400px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .modal-header { font-weight: bold; font-size: 16px; margin-bottom: 15px; display: flex; justify-content: space-between; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <?php if(isset($_GET['msg']) && $_GET['msg']=='saved'): ?>
            <div style="background:#d4edda; color:#155724; padding:10px; margin-bottom:10px; border-radius:4px;">Saved Successfully!</div>
        <?php endif; ?>

        <form method="POST" id="invoiceForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="order_id" value="<?php echo $active_order['id'] ?? 0; ?>">

            <!-- TOOLBAR -->
            <div class="toolbar">
                <button type="button" class="tool-btn" onclick="resetForm()"><i class="fas fa-file"></i> Clear</button>
                <button type="button" class="tool-btn" onclick="window.location.href='invoice_panel.php'"><i class="fas fa-plus"></i> New</button>
                <button type="button" class="tool-btn" onclick="validateAndSubmit()"><i class="fas fa-save"></i> Save</button>
                <?php if($mode == 'edit'): ?>
                    <button type="button" class="tool-btn" onclick="window.open('print_invoice.php?id=<?php echo $active_order['id']; ?>', '_blank')"><i class="fas fa-print"></i> Print</button>
                <?php endif; ?>
                <button type="button" class="tool-btn" onclick="window.location.href='index.php'"><i class="fas fa-times"></i> Cancel</button>
            </div>

            <!-- HEADER INFO -->
            <div class="header-section">
                <!-- COL 1 -->
                <div class="h-col">
                    <div class="form-line">
                        <label>Invoice No</label>
                        <input type="text" value="<?php echo $active_order['order_ref'] ?? '(Auto)'; ?>" readonly style="background:#f1f1f1;">
                    </div>
                    <div class="form-line">
                        <label>Date</label>
                        <input type="date" name="order_date" value="<?php echo $active_order['order_date'] ?? date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-line">
                        <label>Sales Rep</label>
                        <select name="rep_id" required>
                            <?php while($r = $reps->fetch_assoc()): ?>
                                <option value="<?php echo $r['id']; ?>" <?php echo ($active_order['rep_id']??1) == $r['id']?'selected':''; ?>><?php echo $r['full_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <!-- COL 2 -->
                <div class="h-col">
                    <div class="form-line">
                        <label>Customer</label>
                        <!-- CUSTOM CUSTOMER SEARCH -->
                        <div class="search-select">
                            <input type="hidden" name="customer_id" id="custId" value="<?php echo $active_order['customer_id'] ?? ''; ?>">
                            <input type="text" id="custSearch" placeholder="Search Customer..." autocomplete="off">
                            <div class="search-results" id="custResults"></div>
                        </div>
                        <button type="button" onclick="openCustModal()" style="background:#27ae60; color:white; border:none; width:30px; margin-left:5px; border-radius:3px; cursor:pointer;"><i class="fas fa-plus"></i></button>
                    </div>
                    <div class="form-line">
                        <label>Address</label>
                        <input type="text" id="custAddress" readonly style="background:#f9f9f9;">
                    </div>
                    <div class="form-line">
                        <label>Pay Term</label>
                        <select name="payment_term">
                            <option value="cash" <?php echo ($active_order['payment_status']??'') == 'cash' ? 'selected':''; ?>>Cash</option>
                            <option value="credit" <?php echo ($active_order['payment_status']??'') == 'credit' ? 'selected':''; ?>>Credit</option>
                            <option value="cheque" <?php echo ($active_order['payment_status']??'') == 'cheque' ? 'selected':''; ?>>Cheque</option>
                            <option value="bank_transfer" <?php echo ($active_order['payment_status']??'') == 'bank_transfer' ? 'selected':''; ?>>Bank Transfer</option>
                            <option value="paid" <?php echo ($active_order['payment_status']??'') == 'paid' ? 'selected':''; ?>>Paid</option>
                        </select>
                    </div>
                </div>

                <!-- COL 3 -->
                <div class="summary-box">
                    <div class="sum-line"><label>Sub Total</label><input type="text" id="hdrSubTotal" readonly value="0.00"></div>
                    <div class="sum-line"><label>Discount</label><input type="number" id="hdrDiscount" value="0.00" step="0.01" oninput="calcTotals()"></div>
                    <div class="sum-line"><label>VAT / Tax</label><input type="number" id="hdrVat" value="0.00" step="0.01" oninput="calcTotals()"></div>
                    <div style="border-top:1px solid #ccc; margin:10px 0;"></div>
                    <div class="sum-line">
                        <label style="font-size:15px; color:#2c3e50;">Net Amount</label>
                        <input type="text" name="final_net_amt" id="hdrNetAmt" readonly value="0.00" style="font-size:16px; background:#fff;">
                    </div>
                </div>
            </div>

            <!-- ITEM ENTRY BAR -->
            <div class="item-entry">
                <input type="hidden" id="entryRowIndex" value="-1"> <!-- -1 means New Row -->

                <div class="entry-group" style="flex:2;">
                    <label>Select Product</label>
                    <!-- CUSTOM PRODUCT SEARCH -->
                    <div class="search-select">
                        <input type="hidden" id="entryId">
                        <input type="hidden" id="entryCode">
                        <input type="text" id="entrySearch" placeholder="Type to search product..." autocomplete="off">
                        <div class="search-results" id="prodResults"></div>
                    </div>
                </div>
                
                <div class="entry-group" style="width:80px;">
                    <label>Qty</label>
                    <input type="number" id="entryQty" value="1" min="1" step="0.01" oninput="calcEntry()">
                </div>
                <div class="entry-group" style="width:100px;">
                    <label>Rate</label>
                    <input type="number" id="entryRate" step="0.01" oninput="calcEntry()">
                </div>
                <div class="entry-group" style="width:80px;">
                    <label>Dis (%)</label>
                    <input type="number" id="entryDisPer" value="0" min="0" max="100" step="0.1" oninput="calcEntry()">
                </div>
                <div class="entry-group" style="width:120px;">
                    <label>Net Total</label>
                    <input type="text" id="entryNetTotal" readonly style="background:#f9f9f9; font-weight:bold;">
                </div>
                
                <button type="button" id="btnAdd" class="btn-add" onclick="handleGridAction()">ADD <i class="fas fa-plus"></i></button>
            </div>

            <!-- GRID -->
            <div class="grid-container">
                <table class="grid-table" id="gridTable">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Item Code</th>
                            <th>Product Name</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Rate</th>
                            <th class="text-right">Dis %</th>
                            <th class="text-right">Net Total</th>
                            <th class="text-center" style="width:50px;">Del</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($active_items)): $count=1; foreach($active_items as $itm): ?>
                        <tr onclick="editRow(this)">
                            <td><?php echo $count++; ?></td>
                            <td>ITM-<?php echo str_pad($itm['product_id'], 3, '0', STR_PAD_LEFT); ?>
                                <input type="hidden" name="item_id[]" value="<?php echo $itm['product_id']; ?>">
                            </td>
                            <td><?php echo htmlspecialchars($itm['product_name']); ?></td>
                            <td class="text-right"><input type="hidden" name="item_qty[]" value="<?php echo $itm['quantity']; ?>"><?php echo $itm['quantity']; ?></td>
                            <td class="text-right"><input type="hidden" name="item_rate[]" value="<?php echo $itm['unit_price']; ?>"><?php echo $itm['unit_price']; ?></td>
                            <td class="text-right"><input type="hidden" value="<?php echo $itm['dis_per']; ?>"><?php echo $itm['dis_per']; ?></td>
                            <td class="text-right"><input type="hidden" name="item_net_total[]" value="<?php echo $itm['line_total']; ?>"><?php echo $itm['line_total']; ?></td>
                            <td class="text-center"><i class="fas fa-trash-alt" style="color:#c0392b;" onclick="event.stopPropagation(); removeRow(this)"></i></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <!-- ADD CUSTOMER MODAL -->
    <div id="custModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Add New Customer <span onclick="closeCustModal()" style="cursor:pointer;">&times;</span></div>
            <div class="entry-group">
                <label>Shop Name</label><input type="text" id="newShopName" placeholder="Enter Shop Name">
            </div>
            <div class="entry-group" style="margin-top:10px;">
                <label>Phone</label><input type="text" id="newPhone" placeholder="Enter Phone">
            </div>
            <div class="entry-group" style="margin-top:10px;">
                <label>Address</label><input type="text" id="newAddress" placeholder="Enter Address">
            </div>
            <button type="button" onclick="saveNewCustomer()" style="width:100%; margin-top:20px; padding:10px; background:#2980b9; color:white; border:none; border-radius:3px;">Save Customer</button>
        </div>
    </div>

    <script>
        const products = <?php echo json_encode($prod_js); ?>;
        const customers = <?php echo json_encode($cust_js); ?>;
        let selectedRow = null;

        // --- 1. SEARCHABLE DROPDOWNS ---
        
        // Setup Product Search
        const prodInput = document.getElementById('entrySearch');
        const prodResults = document.getElementById('prodResults');
        
        prodInput.addEventListener('input', function() {
            const val = this.value.toLowerCase();
            prodResults.innerHTML = '';
            if(val.length < 1) { prodResults.style.display = 'none'; return; }
            
            const filtered = products.filter(p => p.product_name.toLowerCase().includes(val));
            if(filtered.length > 0) {
                prodResults.style.display = 'block';
                filtered.forEach(p => {
                    const div = document.createElement('div');
                    div.className = 'search-item';
                    const stockClass = p.current_stock < 10 ? 'stock-low' : '';
                    div.innerHTML = `<span>${p.product_name}</span> <span class="stock-badge ${stockClass}">Stock: ${p.current_stock}</span>`;
                    div.onclick = () => selectProduct(p);
                    prodResults.appendChild(div);
                });
            } else {
                prodResults.style.display = 'none';
            }
        });

        function selectProduct(p) {
            document.getElementById('entryId').value = p.id;
            document.getElementById('entryCode').value = 'ITM-' + String(p.id).padStart(3, '0');
            document.getElementById('entrySearch').value = p.product_name;
            document.getElementById('entryRate').value = p.selling_price;
            prodResults.style.display = 'none';
            calcEntry();
        }

        // Setup Customer Search
        const custInput = document.getElementById('custSearch');
        const custResults = document.getElementById('custResults');

        custInput.addEventListener('input', function() {
            const val = this.value.toLowerCase();
            custResults.innerHTML = '';
            if(val.length < 1) { custResults.style.display = 'none'; return; }
            
            const filtered = customers.filter(c => c.shop_name.toLowerCase().includes(val));
            if(filtered.length > 0) {
                custResults.style.display = 'block';
                filtered.forEach(c => {
                    const div = document.createElement('div');
                    div.className = 'search-item';
                    div.innerHTML = `<span>${c.shop_name}</span>`;
                    div.onclick = () => selectCustomer(c);
                    custResults.appendChild(div);
                });
            }
        });

        function selectCustomer(c) {
            document.getElementById('custId').value = c.id;
            document.getElementById('custSearch').value = c.shop_name;
            document.getElementById('custAddress').value = c.address;
            custResults.style.display = 'none';
        }

        // Init Data for Edit Mode
        const preCustId = "<?php echo $active_order['customer_id'] ?? ''; ?>";
        if(preCustId) {
            const preCust = customers.find(c => c.id == preCustId);
            if(preCust) selectCustomer(preCust);
        }

        // --- 2. GRID OPERATIONS ---

        function calcEntry() {
            const qty = parseFloat(document.getElementById('entryQty').value) || 0;
            const rate = parseFloat(document.getElementById('entryRate').value) || 0;
            const dis = parseFloat(document.getElementById('entryDisPer').value) || 0;
            const net = (qty * rate) * ((100 - dis) / 100);
            document.getElementById('entryNetTotal').value = net.toFixed(2);
        }

        function handleGridAction() {
            const id = document.getElementById('entryId').value;
            if(!id) { alert("Select a product"); return; }
            
            const rowIndex = document.getElementById('entryRowIndex').value;
            const code = document.getElementById('entryCode').value;
            const name = document.getElementById('entrySearch').value;
            const qty = document.getElementById('entryQty').value;
            const rate = document.getElementById('entryRate').value;
            const dis = document.getElementById('entryDisPer').value;
            const net = document.getElementById('entryNetTotal').value;

            if(rowIndex == "-1") {
                // ADD NEW
                const tbody = document.querySelector('#gridTable tbody');
                const rowCount = tbody.rows.length + 1;
                const tr = document.createElement('tr');
                tr.onclick = function() { editRow(this); };
                tr.innerHTML = buildRowHTML(rowCount, id, code, name, qty, rate, dis, net);
                tbody.appendChild(tr);
            } else {
                // UPDATE EXISTING
                const tr = document.querySelector('#gridTable tbody').rows[rowIndex];
                tr.innerHTML = buildRowHTML(parseInt(rowIndex)+1, id, code, name, qty, rate, dis, net);
                // Reset Mode
                resetEntry();
            }
            calcTotals();
        }

        function buildRowHTML(idx, id, code, name, qty, rate, dis, net) {
            return `
                <td>${idx}</td>
                <td>${code}<input type="hidden" name="item_id[]" value="${id}"></td>
                <td>${name}</td>
                <td class="text-right"><input type="hidden" name="item_qty[]" value="${qty}">${qty}</td>
                <td class="text-right"><input type="hidden" name="item_rate[]" value="${rate}">${rate}</td>
                <td class="text-right"><input type="hidden" value="${dis}">${dis}</td>
                <td class="text-right"><input type="hidden" name="item_net_total[]" value="${net}">${net}</td>
                <td class="text-center"><i class="fas fa-trash-alt" style="color:#c0392b;" onclick="event.stopPropagation(); removeRow(this)"></i></td>
            `;
        }

        function editRow(tr) {
            // Load data to entry
            const inputs = tr.querySelectorAll('input');
            document.getElementById('entryRowIndex').value = tr.rowIndex - 1; // Adjust for header
            document.getElementById('entryId').value = inputs[0].value;
            document.getElementById('entryCode').value = tr.cells[1].innerText;
            document.getElementById('entrySearch').value = tr.cells[2].innerText;
            document.getElementById('entryQty').value = inputs[1].value;
            document.getElementById('entryRate').value = inputs[2].value;
            document.getElementById('entryDisPer').value = inputs[3].value;
            document.getElementById('entryNetTotal').value = inputs[4].value;

            // UI Change
            const btn = document.getElementById('btnAdd');
            btn.innerHTML = 'UPDATE <i class="fas fa-check"></i>';
            btn.className = 'btn-add btn-update';
        }

        function resetEntry() {
            document.getElementById('entryRowIndex').value = "-1";
            document.getElementById('entryId').value = "";
            document.getElementById('entrySearch').value = "";
            document.getElementById('entryQty').value = "1";
            document.getElementById('entryRate').value = "";
            document.getElementById('entryDisPer').value = "0";
            document.getElementById('entryNetTotal').value = "";
            
            const btn = document.getElementById('btnAdd');
            btn.innerHTML = 'ADD <i class="fas fa-plus"></i>';
            btn.className = 'btn-add';
        }

        function removeRow(icon) {
            if(confirm('Remove Item?')) {
                icon.closest('tr').remove();
                calcTotals();
            }
        }

        function resetForm() {
            if(confirm('Clear current form?')) {
                // Clear Form Fields
                document.getElementById('invoiceForm').reset();
                document.querySelector('input[name="order_id"]').value = 0;
                document.querySelector('#gridTable tbody').innerHTML = '';
                
                // Clear Custom Search Fields
                document.getElementById('custSearch').value = '';
                document.getElementById('custId').value = '';
                document.getElementById('custAddress').value = '';
                
                // Reset Totals
                document.getElementById('hdrSubTotal').value = '0.00';
                document.getElementById('hdrDiscount').value = '0.00';
                document.getElementById('hdrVat').value = '0.00';
                document.getElementById('hdrNetAmt').value = '0.00';
                
                // Reset Entry Bar
                resetEntry();
            }
        }

        function calcTotals() {
            let total = 0;
            document.querySelectorAll('input[name="item_net_total[]"]').forEach(inp => total += parseFloat(inp.value)||0);
            
            document.getElementById('hdrSubTotal').value = total.toFixed(2);
            const dis = parseFloat(document.getElementById('hdrDiscount').value)||0;
            const vat = parseFloat(document.getElementById('hdrVat').value)||0;
            document.getElementById('hdrNetAmt').value = (total - dis + vat).toFixed(2);
        }

        function validateAndSubmit() {
            if(document.querySelector('#gridTable tbody').rows.length == 0) { alert('Grid is empty'); return; }
            if(!document.getElementById('custId').value) { alert('Select customer'); return; }
            document.getElementById('invoiceForm').submit();
        }

        // --- 3. CUSTOMER MODAL ---
        function openCustModal() { document.getElementById('custModal').style.display = 'flex'; }
        function closeCustModal() { document.getElementById('custModal').style.display = 'none'; }
        
        function saveNewCustomer() {
            const name = document.getElementById('newShopName').value;
            const phone = document.getElementById('newPhone').value;
            const addr = document.getElementById('newAddress').value;
            
            if(!name) { alert("Shop name required"); return; }

            const formData = new FormData();
            formData.append('ajax_action', 'add_customer');
            formData.append('shop_name', name);
            formData.append('phone', phone);
            formData.append('address', addr);

            fetch('invoice_panel.php', { method:'POST', body:formData })
            .then(res => res.json())
            .then(data => {
                if(data.status == 'success') {
                    // Add to array & select it
                    const newC = { id: data.id, shop_name: data.name, address: data.addr, phone: data.phone };
                    customers.push(newC);
                    selectCustomer(newC);
                    closeCustModal();
                    alert("Customer Added!");
                } else {
                    alert("Error: " + data.message);
                }
            });
        }

        // Close dropdowns on click outside
        document.addEventListener('click', function(e) {
            if(!e.target.closest('.search-select')) {
                prodResults.style.display = 'none';
                custResults.style.display = 'none';
            }
        });

        // Init
        calcTotals();
    </script>
</body>
</html>