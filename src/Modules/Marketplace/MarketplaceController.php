<?php
require_once __DIR__ . '/../../Helpers/SessionHelper.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/AuditHelper.php';
require_once __DIR__ . '/../Finance/FinanceService.php';

class MarketplaceController {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }

    private function farmIdForUser($userId) {
        $st = $this->pdo->prepare("SELECT farm_id FROM farm_members WHERE user_id = ? LIMIT 1");
        $st->execute([(int)$userId]);
        $v = $st->fetchColumn();
        if ($v !== false && $v !== null) return (int)$v;
        $st = $this->pdo->prepare("SELECT id FROM farms ORDER BY id LIMIT 1");
        $st->execute();
        $v = $st->fetchColumn();
        return $v === false ? null : (int)$v;
    }

    public function index() {
        $q = trim((string)($_GET['q'] ?? ''));
        $cat = trim((string)($_GET['category'] ?? ''));
        $sql = "SELECT l.*, f.name AS seller_farm, u.username AS seller_user FROM marketplace_listings l LEFT JOIN farms f ON f.id = l.seller_farm_id LEFT JOIN users u ON u.id = l.seller_user_id WHERE l.status = 'active'";
        $params = [];
        if ($q !== '') { $sql .= " AND (LOWER(l.item_name) LIKE LOWER(?) OR LOWER(COALESCE(l.category,'')) LIKE LOWER(?))"; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($cat !== '') { $sql .= " AND LOWER(COALESCE(l.category,'')) = LOWER(?)"; $params[] = $cat; }
        $sql .= " ORDER BY l.created_at DESC LIMIT 100";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $mine = $this->pdo->prepare("SELECT * FROM marketplace_listings WHERE seller_user_id=? ORDER BY created_at DESC");
        $mine->execute([$uid]);
        $orders = $this->pdo->prepare("SELECT o.*, l.item_name, l.unit, l.seller_user_id, f.name AS seller_farm FROM marketplace_orders o JOIN marketplace_listings l ON l.id=o.listing_id LEFT JOIN farms f ON f.id=l.seller_farm_id WHERE o.buyer_user_id=? ORDER BY o.created_at DESC");
        $orders->execute([$uid]);
        $sales = $this->pdo->prepare("SELECT o.*, l.item_name, l.unit, u.username AS buyer_name FROM marketplace_orders o JOIN marketplace_listings l ON l.id=o.listing_id LEFT JOIN users u ON u.id=o.buyer_user_id WHERE l.seller_user_id=? ORDER BY o.created_at DESC");
        $sales->execute([$uid]);
        return ['listings' => $st->fetchAll() ?: [], 'myListings' => $mine->fetchAll() ?: [], 'orders' => $orders->fetchAll() ?: [], 'sales' => $sales->fetchAll() ?: [], 'q' => $q, 'category' => $cat, 'userId' => $uid];
    }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php?module=Marketplace'); exit; }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $farmId = $this->farmIdForUser($uid);
        $item = trim($_POST['item_name'] ?? '');
        $cat = trim($_POST['category'] ?? '');
        $qty = (float)($_POST['quantity'] ?? 0);
        $unit = trim($_POST['unit'] ?? '');
        $price = (float)($_POST['price_per_unit'] ?? 0);
        if ($item === '' || $qty <= 0 || $unit === '' || $price <= 0) {
            SessionHelper::setFlash('error', 'Item, quantity, unit and price are required.');
            header('Location: index.php?module=Marketplace'); exit;
        }
        try {
            $st = $this->pdo->prepare("INSERT INTO marketplace_listings (seller_farm_id, seller_user_id, item_name, category, quantity, unit, price_per_unit) VALUES (?,?,?,?,?,?,?)");
            $st->execute([$farmId, $uid, $item, $cat !== '' ? $cat : null, $qty, $unit, $price]);
            $id = (int)$this->pdo->lastInsertId();
            AuditHelper::log($this->pdo, 'human', 'marketplace_listing_created', 'marketplace_listings', $id, ['item'=>$item], $uid);
            SessionHelper::setFlash('success', 'Listing created.');
        } catch (PDOException $e) {
            SessionHelper::setFlash('error', 'Could not create listing.');
        }
        header('Location: index.php?module=Marketplace'); exit;
    }

    public function buy() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php?module=Marketplace'); exit; }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $buyerFarm = $this->farmIdForUser($uid);
        $listingId = ValidationHelper::id($_POST['listing_id'] ?? '');
        $qty = (float)($_POST['quantity'] ?? 0);
        if (!$listingId || $qty <= 0) { SessionHelper::setFlash('error', 'Invalid purchase.'); header('Location: index.php?module=Marketplace'); exit; }
        try {
            $this->pdo->beginTransaction();
            $st = $this->pdo->prepare("SELECT * FROM marketplace_listings WHERE id = ? AND status = 'active' FOR UPDATE");
            $st->execute([$listingId]);
            $l = $st->fetch();
            if (!$l) throw new Exception('Listing not available.');
            if ($qty > (float)$l['quantity']) throw new Exception('Not enough quantity available.');
            $total = $qty * (float)$l['price_per_unit'];
            $st = $this->pdo->prepare("INSERT INTO marketplace_orders (listing_id, buyer_farm_id, buyer_user_id, quantity, total_amount, status) VALUES (?,?,?,?,?,'completed')");
            $st->execute([$listingId, $buyerFarm, $uid, $qty, $total]);
            $orderId = (int)$this->pdo->lastInsertId();
            // reduce listing quantity
            $newQty = (float)$l['quantity'] - $qty;
            $newStatus = $newQty <= 0 ? 'sold' : 'active';
            $st = $this->pdo->prepare("UPDATE marketplace_listings SET quantity = ?, status = ? WHERE id = ?");
            $st->execute([$newQty, $newStatus, $listingId]);
            // post finance: buyer expense, seller income
            try {
                (new FinanceService($this->pdo))->postSafe('expense', 'Procurement', $total, 'Marketplace purchase: ' . $l['item_name'], $buyerFarm, date('Y-m-d'), 'paid');
                (new FinanceService($this->pdo))->postSafe('income', 'Sales', $total, 'Marketplace sale: ' . $l['item_name'], (int)$l['seller_farm_id'], date('Y-m-d'), 'paid');
            } catch (Throwable $e) { }
            AuditHelper::log($this->pdo, 'human', 'marketplace_purchase', 'marketplace_orders', $orderId, ['listing'=>$listingId,'qty'=>$qty], $uid);
            $this->pdo->commit();
            SessionHelper::setFlash('success', 'Purchase completed.');
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            SessionHelper::setFlash('error', $e->getMessage());
        }
        header('Location: index.php?module=Marketplace'); exit;
    }

    public function cancel() {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $id = ValidationHelper::id($_POST['listing_id'] ?? '');
        if (!$id) { SessionHelper::setFlash('error', 'Invalid listing.'); header('Location: index.php?module=Marketplace'); exit; }
        $st = $this->pdo->prepare("UPDATE marketplace_listings SET status='cancelled' WHERE id=? AND seller_user_id=? AND status='active'");
        $st->execute([$id, $uid]);
        SessionHelper::setFlash($st->rowCount() ? 'success' : 'error', $st->rowCount() ? 'Listing cancelled.' : 'Listing could not be cancelled.');
        header('Location: index.php?module=Marketplace'); exit;
    }

    public function edit() {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $id = ValidationHelper::id($_POST['listing_id'] ?? '');
        $qty = (float)($_POST['quantity'] ?? 0);
        $price = (float)($_POST['price_per_unit'] ?? 0);
        if (!$id || $qty <= 0 || $price <= 0) { SessionHelper::setFlash('error', 'Enter a valid quantity and price.'); header('Location: index.php?module=Marketplace'); exit; }
        $st = $this->pdo->prepare("UPDATE marketplace_listings SET quantity=?, price_per_unit=? WHERE id=? AND seller_user_id=? AND status='active'");
        $st->execute([$qty, $price, $id, $uid]);
        SessionHelper::setFlash($st->rowCount() ? 'success' : 'error', $st->rowCount() ? 'Listing updated.' : 'Listing could not be updated.');
        header('Location: index.php?module=Marketplace'); exit;
    }
}
?>