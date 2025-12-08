<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$msg = "";
$selected_product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

// --- HANDLE ACTIONS ---

// 1. ADD INGREDIENT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_ingredient') {
    $prod_id = intval($_POST['product_id']);
    $mat_id = intval($_POST['material_id']);
    $qty = floatval($_POST['quantity']);

    // Check Duplicate
    $check = $conn->query("SELECT id FROM product_recipes WHERE product_id=$prod_id AND raw_material_id=$mat_id");
    if ($check->num_rows > 0) {
        $msg = "<div class='alert error'>Ingredient already exists. Please edit the existing entry.</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO product_recipes (product_id, raw_material_id, quantity_needed) VALUES (?, ?, ?)");
        $stmt->bind_param("iid", $prod_id, $mat_id, $qty);
        if ($stmt->execute()) {
            $msg = "<div class='alert success'>Ingredient Added Successfully!</div>";
        } else {
            $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
        }
    }
    $selected_product_id = $prod_id; 
}

// 2. UPDATE INGREDIENT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_ingredient') {
    $id = intval($_POST['recipe_id']);
    $qty = floatval($_POST['quantity']);
    $prod_id = intval($_POST['product_id']);

    $stmt = $conn->prepare("UPDATE product_recipes SET quantity_needed=? WHERE id=?");
    $stmt->bind_param("di", $qty, $id);
    if ($stmt->execute()) {
        $msg = "<div class='alert success'>Recipe Updated!</div>";
    }
    $selected_product_id = $prod_id;
}

// 3. DELETE INGREDIENT
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $prod_id = intval($_GET['product_id']);
    $conn->query("DELETE FROM product_recipes WHERE id=$del_id");
    $msg = "<div class='alert success'>Ingredient Removed.</div>";
    $selected_product_id = $prod_id;
}

// --- FETCH DATA ---
// Fetch Products with Recipe Status
$products = $conn->query("
    SELECT p.*, (SELECT COUNT(*) FROM product_recipes WHERE product_id = p.id) as item_count 
    FROM products p 
    ORDER BY p.product_name
");

$materials = $conn->query("SELECT * FROM raw_materials ORDER BY name");

// Helper: Material Array for Modal
$mat_list = [];
while($m = $materials->fetch_assoc()) { $mat_list[] = $m; }

// Helper: Get Recipe Details if Product Selected
$recipe_data = [];
$prod_info = null;
$est_cost = 0;

if ($selected_product_id > 0) {
    $prod_info = $conn->query("SELECT * FROM products WHERE id=$selected_product_id")->fetch_assoc();
    
    // Fetch Ingredients + Calculate Estimated Cost
    $sql = "
        SELECT r.*, m.name as mat_name, m.unit_measure,
        (SELECT AVG(cost_per_unit) FROM material_batches WHERE material_id = m.id AND status='active') as avg_cost
        FROM product_recipes r 
        JOIN raw_materials m ON r.raw_material_id = m.id 
        WHERE r.product_id = $selected_product_id
    ";
    $res = $conn->query($sql);
    
    while($row = $res->fetch_assoc()) {
        $row['cost_line'] = $row['quantity_needed'] * ($row['avg_cost'] ?? 0);
        $est_cost += $row['cost_line'];
        $recipe_data[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Recipes (BOM) | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Layout */
        .page-container { display: flex; gap: 20px; height: calc(100vh - 120px); }
        .list-panel { width: 320px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; flex-direction: column; overflow: hidden; }
        .detail-panel { flex: 1; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); padding: 25px; overflow-y: auto; display: flex; flex-direction: column; }

        /* List Styles */
        .list-header { padding: 15px; background: #f8f9fa; border-bottom: 1px solid #eee; display:flex; justify-content:space-between; align-items:center; }
        .list-item { display: flex; justify-content:space-between; align-items:center; padding: 12px 15px; border-bottom: 1px solid #f0f0f0; text-decoration: none; color: #555; transition: 0.2s; }
        .list-item:hover { background: #f4f6f9; color: #3498db; }
        .list-item.active { background: #eaf2f8; border-left: 4px solid #3498db; color: #2980b9; font-weight: bold; }
        
        .search-box { padding: 10px; border-bottom: 1px solid #eee; }
        .search-box input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }

        /* Detail Header */
        .detail-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .prod-title h2 { margin: 0; color: #2c3e50; }
        .prod-meta { color: #7f8c8d; font-size: 14px; margin-top: 5px; }
        .cost-badge { background: #e8f6f3; color: #16a085; padding: 5px 10px; border-radius: 5px; font-weight: bold; border: 1px solid #d0ece7; }

        /* Table */
        .recipe-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .recipe-table th { text-align: left; padding: 10px; background: #34495e; color: white; font-size: 13px; }
        .recipe-table td { padding: 10px; border-bottom: 1px solid #eee; }
        .recipe-table tr:hover { background: #f9f9f9; }

        .btn-add { background: #2980b9; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; border: none; cursor: pointer; }
        .btn-icon { background: none; border: none; cursor: pointer; font-size: 14px; padding: 5px; }
        .btn-create-big { background: #27ae60; color: white; padding: 15px 30px; border-radius: 8px; border: none; cursor: pointer; font-size: 16px; margin-top: 20px; font-weight: bold; }
        
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 8px; }
        .dot-green { background: #2ecc71; }
        .dot-gray { background: #bdc3c7; }

        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 25px; border-radius: 8px; width: 400px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); animation: slideIn 0.3s ease; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn-save { width: 100%; padding: 12px; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }

        @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        
        <div style="margin-bottom: 15px; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h2 style="margin:0;">Recipe Manager</h2>
                <p style="margin:0; color:#777; font-size:14px;">Create and manage Bill of Materials (BOM) for products.</p>
            </div>
            <button onclick="openSelectModal()" class="btn-add" style="background:#8e44ad;">
                <i class="fas fa-magic"></i> New Recipe
            </button>
        </div>

        <?php echo $msg; ?>

        <div class="page-container">
            <!-- LEFT: PRODUCT LIST -->
            <div class="list-panel">
                <div class="list-header">
                    <span>Products</span>
                    <span style="font-size:11px; color:#777;">Green = Has Recipe</span>
                </div>
                <div class="search-box">
                    <input type="text" id="prodSearch" placeholder="Search products..." onkeyup="filterProducts()">
                </div>
                <div style="flex:1; overflow-y:auto;" id="prodList">
                    <?php 
                    if($products->num_rows > 0):
                        while($p = $products->fetch_assoc()): 
                            $has_recipe = $p['item_count'] > 0;
                            $dot_class = $has_recipe ? 'dot-green' : 'dot-gray';
                    ?>
                        <a href="?product_id=<?php echo $p['id']; ?>" class="list-item <?php echo $selected_product_id == $p['id'] ? 'active' : ''; ?>">
                            <span>
                                <span class="status-dot <?php echo $dot_class; ?>"></span>
                                <?php echo htmlspecialchars($p['product_name']); ?>
                            </span>
                            <?php if($has_recipe): ?>
                                <span style="font-size:11px; background:#e8f6f3; color:#16a085; padding:2px 5px; border-radius:4px;"><?php echo $p['item_count']; ?> ingr.</span>
                            <?php endif; ?>
                        </a>
                    <?php endwhile; endif; ?>
                </div>
            </div>

            <!-- RIGHT: RECIPE DETAILS -->
            <div class="detail-panel">
                <?php if($selected_product_id > 0 && $prod_info): ?>
                    
                    <div class="detail-header">
                        <div class="prod-title">
                            <h2><?php echo htmlspecialchars($prod_info['product_name']); ?></h2>
                            <div class="prod-meta">
                                Selling Price: Rs <?php echo number_format($prod_info['selling_price'], 2); ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div class="cost-badge">Est. Cost: Rs <?php echo number_format($est_cost, 2); ?></div>
                            <div style="font-size:11px; color:#aaa; margin-top:5px;">Based on current stock avg cost</div>
                        </div>
                    </div>

                    <?php if(count($recipe_data) == 0): ?>
                        <div style="text-align:center; padding:50px; background:#f9f9f9; border-radius:8px;">
                            <i class="fas fa-flask" style="font-size:40px; color:#bdc3c7; margin-bottom:20px;"></i>
                            <h3 style="color:#7f8c8d; margin:0;">No Recipe Defined</h3>
                            <p style="color:#95a5a6;">This product has no ingredients linked yet.</p>
                            <button onclick="openModal('add')" class="btn-create-big">
                                <i class="fas fa-plus-circle"></i> Create Recipe
                            </button>
                        </div>
                    <?php else: ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                            <h3 style="margin:0;">Ingredients List</h3>
                            <button onclick="openModal('add')" class="btn-add"><i class="fas fa-plus"></i> Add Ingredient</button>
                        </div>

                        <table class="recipe-table">
                            <thead>
                                <tr>
                                    <th>Raw Material</th>
                                    <th style="text-align:right;">Qty Needed</th>
                                    <th>Unit</th>
                                    <th style="text-align:right;">Est. Cost</th>
                                    <th style="text-align:center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recipe_data as $item): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($item['mat_name']); ?></strong></td>
                                    <td style="text-align:right; color:#2980b9; font-weight:bold;"><?php echo $item['quantity_needed']; ?></td>
                                    <td><?php echo $item['unit_measure']; ?></td>
                                    <td style="text-align:right;">Rs <?php echo number_format($item['cost_line'], 2); ?></td>
                                    <td style="text-align:center;">
                                        <button class="btn-icon" style="color:#f39c12;" onclick='openModal("edit", <?php echo json_encode($item); ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?product_id=<?php echo $selected_product_id; ?>&delete_id=<?php echo $item['id']; ?>" 
                                           class="btn-icon" style="color:#c0392b;"
                                           onclick="return confirm('Remove this ingredient?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                <?php else: ?>
                    <div style="text-align:center; margin-top:100px; color:#aaa;">
                        <i class="fas fa-arrow-left" style="font-size:40px; margin-bottom:15px;"></i>
                        <h3>Select a product to view or create its recipe</h3>
                        <p>Use the list on the left or the "New Recipe" button above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- INGREDIENT MODAL -->
    <div id="recipeModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle" style="margin-top:0;">Add Ingredient</h3>
            
            <form method="POST">
                <input type="hidden" name="product_id" value="<?php echo $selected_product_id; ?>">
                <input type="hidden" name="action" id="formAction" value="add_ingredient">
                <input type="hidden" name="recipe_id" id="recipeId">

                <div class="form-group">
                    <label>Raw Material</label>
                    <select name="material_id" id="matSelect" required>
                        <option value="">-- Select Material --</option>
                        <?php foreach($mat_list as $mat): ?>
                            <option value="<?php echo $mat['id']; ?>"><?php echo $mat['name'] . " (" . $mat['unit_measure'] . ")"; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity Needed (per 1 unit of product)</label>
                    <input type="number" step="0.0001" name="quantity" id="qtyInput" required placeholder="e.g. 0.25">
                </div>

                <button type="submit" class="btn-save" id="btnSave">Save Ingredient</button>
                <button type="button" onclick="closeModal()" style="width:100%; margin-top:10px; padding:10px; background:#bdc3c7; border:none; border-radius:4px; cursor:pointer;">Cancel</button>
            </form>
        </div>
    </div>

    <!-- PRODUCT SELECTOR MODAL (New Recipe Shortcut) -->
    <div id="selectProductModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-top:0;">Start New Recipe</h3>
            <p style="color:#777; font-size:13px; margin-bottom:15px;">Select a product to configure.</p>
            <div style="max-height:300px; overflow-y:auto;">
                <?php 
                if($products->num_rows > 0) {
                    $products->data_seek(0);
                    while($p = $products->fetch_assoc()) {
                        echo "<a href='?product_id={$p['id']}' style='display:block; padding:10px; border-bottom:1px solid #eee; text-decoration:none; color:#333;'>{$p['product_name']}</a>";
                    }
                }
                ?>
            </div>
            <button type="button" onclick="document.getElementById('selectProductModal').style.display='none'" style="width:100%; margin-top:15px; padding:10px; background:#bdc3c7; border:none; border-radius:4px; cursor:pointer;">Close</button>
        </div>
    </div>

    <!-- Sidebar JS -->
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        const modal = document.getElementById('recipeModal');
        const prodModal = document.getElementById('selectProductModal');
        const matSelect = document.getElementById('matSelect');
        
        function openModal(mode, data = null) {
            modal.style.display = 'flex';
            if (mode === 'edit') {
                document.getElementById('modalTitle').innerText = 'Edit Ingredient Qty';
                document.getElementById('formAction').value = 'edit_ingredient';
                document.getElementById('recipeId').value = data.id;
                document.getElementById('qtyInput').value = data.quantity_needed;
                
                matSelect.value = data.raw_material_id;
                matSelect.disabled = true;
                document.getElementById('btnSave').innerText = 'Update Quantity';
            } else {
                document.getElementById('modalTitle').innerText = 'Add Ingredient';
                document.getElementById('formAction').value = 'add_ingredient';
                document.getElementById('recipeId').value = '';
                document.getElementById('qtyInput').value = '';
                
                matSelect.value = '';
                matSelect.disabled = false;
                document.getElementById('btnSave').innerText = 'Add to Recipe';
            }
        }

        function openSelectModal() {
            prodModal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
            prodModal.style.display = 'none';
        }

        function filterProducts() {
            const input = document.getElementById('prodSearch');
            const filter = input.value.toLowerCase();
            const items = document.querySelectorAll('.list-item');

            items.forEach(item => {
                const text = item.innerText.toLowerCase();
                item.style.display = text.includes(filter) ? 'flex' : 'none';
            });
        }

        window.onclick = function(event) {
            if (event.target == modal) modal.style.display = 'none';
            if (event.target == prodModal) prodModal.style.display = 'none';
        }
    </script>
</body>
</html>