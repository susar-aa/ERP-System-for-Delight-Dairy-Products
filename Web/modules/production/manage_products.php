<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$msg = "";

// --- HANDLE ACTIONS ---

// 1. ADD / UPDATE PRODUCT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $name = trim($_POST['product_name']);
    $price = floatval($_POST['selling_price']);
    $desc = trim($_POST['description']);
    
    if ($_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO products (product_name, selling_price, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sds", $name, $price, $desc);
        if ($stmt->execute()) {
            $msg = "<div class='alert success'>Product Added Successfully!</div>";
        } else {
            $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
        }
    } 
    elseif ($_POST['action'] == 'edit') {
        $id = intval($_POST['product_id']);
        $stmt = $conn->prepare("UPDATE products SET product_name=?, selling_price=?, description=? WHERE id=?");
        $stmt->bind_param("sdsi", $name, $price, $desc, $id);
        if ($stmt->execute()) {
            $msg = "<div class='alert success'>Product Updated Successfully!</div>";
        } else {
            $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
        }
    }
}

// 2. DELETE PRODUCT
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    // Check usage in recipes or production runs to prevent breaking history
    $check_recipe = $conn->query("SELECT COUNT(*) as c FROM product_recipes WHERE product_id = $del_id")->fetch_assoc()['c'];
    $check_run = $conn->query("SELECT COUNT(*) as c FROM production_runs WHERE product_id = $del_id")->fetch_assoc()['c'];
    
    if ($check_recipe > 0 || $check_run > 0) {
        $msg = "<div class='alert error'>Cannot delete: Product is used in Recipes or Production History.</div>";
    } else {
        $conn->query("DELETE FROM products WHERE id = $del_id");
        $msg = "<div class='alert success'>Product Deleted.</div>";
    }
}

// --- FETCH DATA ---
$products = $conn->query("SELECT * FROM products ORDER BY product_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Products | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Shared Styles */
        .data-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-radius: 8px; overflow: hidden; }
        .data-table th { background: #34495e; color: white; padding: 12px 15px; text-align: left; font-size: 13px; text-transform: uppercase; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid #eee; font-size: 14px; }
        .data-table tr:hover { background: #f9f9f9; }

        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-add-new { background: #2980b9; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; border: none; font-size: 14px; font-weight: bold; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .action-btn { border: none; background: none; cursor: pointer; font-size: 14px; margin-right: 8px; }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 25px; border-radius: 8px; width: 450px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); position: relative; animation: slideIn 0.3s ease; }
        .modal-header { font-weight: bold; font-size: 18px; margin-bottom: 20px; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .close-btn { background: none; border: none; font-size: 20px; cursor: pointer; color: #777; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px; color: #555; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn-save { width: 100%; background: #27ae60; color: white; padding: 12px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; margin-top: 10px; font-weight: bold; }

        @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        
        <div class="top-bar">
            <div>
                <h2>Finished Products</h2>
                <p style="color:#777; font-size:14px; margin:0;">Define the items you manufacture and sell.</p>
            </div>
            <button onclick="openModal('add')" class="btn-add-new">
                <i class="fas fa-plus"></i> Add New Product
            </button>
        </div>

        <?php echo $msg; ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Product Name</th>
                    <th>Selling Price (Rs)</th>
                    <th>Description</th>
                    <th style="width: 150px; text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($products->num_rows > 0): ?>
                    <?php while($row = $products->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['product_name']); ?></strong></td>
                        <td>
                            <span style="background:#e8f6f3; color:#16a085; padding:4px 8px; border-radius:4px; font-weight:bold;">
                                Rs <?php echo number_format($row['selling_price'], 2); ?>
                            </span>
                        </td>
                        <td style="color:#777; font-size:13px;"><?php echo htmlspecialchars($row['description']); ?></td>
                        <td style="text-align:center;">
                            <button class="action-btn" style="color:#f39c12;" onclick='openModal("edit", <?php echo json_encode($row); ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?delete_id=<?php echo $row['id']; ?>" class="action-btn" style="color:#c0392b;" onclick="return confirm('Are you sure you want to delete this product?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:20px;">No products defined yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- MODAL -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTitle">Add Product</span>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="product_id" id="prodId">

                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="product_name" id="prodName" required placeholder="e.g. Vanilla Yoghurt 80g">
                </div>

                <div class="form-group">
                    <label>Selling Price (Rs)</label>
                    <input type="number" step="0.01" name="selling_price" id="prodPrice" required placeholder="0.00">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="prodDesc" rows="3" placeholder="Optional details..."></textarea>
                </div>

                <button type="submit" class="btn-save" id="btnSave">Save Product</button>
            </form>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        const modal = document.getElementById('productModal');
        
        function openModal(mode, data = null) {
            modal.style.display = 'flex';
            if (mode === 'edit') {
                document.getElementById('modalTitle').innerText = 'Edit Product';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('btnSave').innerText = 'Update Product';
                
                document.getElementById('prodId').value = data.id;
                document.getElementById('prodName').value = data.product_name;
                document.getElementById('prodPrice').value = data.selling_price;
                document.getElementById('prodDesc').value = data.description;
            } else {
                document.getElementById('modalTitle').innerText = 'Add Product';
                document.getElementById('formAction').value = 'add';
                document.getElementById('btnSave').innerText = 'Save Product';
                
                document.getElementById('prodId').value = '';
                document.getElementById('prodName').value = '';
                document.getElementById('prodPrice').value = '';
                document.getElementById('prodDesc').value = '';
            }
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>