<?php
$pageTitle = 'Yayın Akışı';
require_once 'templates/header.php';

// Günlerin eşleştirme tablosu
$days = [
    'Sunday' => 'Pazar',
    'Monday' => 'Pazartesi',
    'Tuesday' => 'Salı',
    'Wednesday' => 'Çarşamba',
    'Thursday' => 'Perşembe',
    'Friday' => 'Cuma',
    'Saturday' => 'Cumartesi'
];

// DJ listesini çek (sadece admin için)
$djs = [];
if (isLoggedIn() && hasRole('admin')) {
    $djResult = $conn->query("SELECT id, display_name FROM users WHERE role = 'dj' ORDER BY display_name ASC");
    while ($dj = $djResult->fetch_assoc()) {
        $djs[] = $dj;
    }
}

// Yayın ekleme işlemi (sadece admin/dj)
$error = '';
$success = '';
if (isLoggedIn() && hasRole(['admin', 'dj']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Geçersiz form gönderimi';
    } else {
        // DJ seçimi (admin ise formdan, dj ise kendi id'si)
        if (hasRole('admin')) {
            $userId = (int)($_POST['dj_id'] ?? 0);
        } else {
            $userId = $_SESSION['user_id'];
        }
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $dayOfWeek = $_POST['day_of_week'] ?? '';
        if (!array_key_exists($dayOfWeek, $days)) {
            $error = 'Geçersiz gün seçimi.';
        } else {
            // Veritabanına kaydetmek için İngilizce gün adını kullan
            $dayOfWeek = array_search($dayOfWeek, $days);
        }
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';

        if ($title === '' || $dayOfWeek < 0 || $dayOfWeek > 6 || $startTime === '' || $endTime === '' || !$userId) {
            $error = 'Tüm zorunlu alanları doldurun.';
        } else {
            $stmt = $conn->prepare("INSERT INTO broadcast_schedule (user_id, day_of_week, start_time, end_time, title, description) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iissss', $userId, $dayOfWeek, $startTime, $endTime, $title, $description);
            if ($stmt->execute()) {
                $success = 'Yayın başarıyla eklendi.';
            } else {
                $error = 'Yayın eklenemedi: ' . $stmt->error;
            }
        }
    }
}

// Yayın silme işlemi
if (isLoggedIn() && hasRole(['admin', 'dj']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_schedule'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Geçersiz form gönderimi';
    } else {
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM broadcast_schedule WHERE id = ?");
        $stmt->bind_param('i', $scheduleId);
        if ($stmt->execute()) {
            $success = 'Yayın başarıyla silindi.';
        } else {
            $error = 'Yayın silinemedi: ' . $stmt->error;
        }
    }
}

// Yayın düzenleme işlemi
if (isLoggedIn() && hasRole(['admin', 'dj']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_schedule'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Geçersiz form gönderimi';
    } else {
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $dayOfWeek = $_POST['day_of_week'] ?? '';
        if (!array_key_exists($dayOfWeek, $days)) {
            $error = 'Geçersiz gün seçimi.';
        } else {
            // Veritabanına kaydetmek için İngilizce gün adını kullan
            $dayOfWeek = array_search($dayOfWeek, $days);
        }
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';

        if ($title === '' || $dayOfWeek < 0 || $dayOfWeek > 6 || $startTime === '' || $endTime === '') {
            $error = 'Tüm zorunlu alanları doldurun.';
        } else {
            $stmt = $conn->prepare("UPDATE broadcast_schedule SET title = ?, description = ?, day_of_week = ?, start_time = ?, end_time = ? WHERE id = ?");
            $stmt->bind_param('ssissi', $title, $description, $dayOfWeek, $startTime, $endTime, $scheduleId);
            if ($stmt->execute()) {
                $success = 'Yayın başarıyla güncellendi.';
            } else {
                $error = 'Yayın güncellenemedi: ' . $stmt->error;
            }
        }
    }
}

// Yayın akışlarını çek
$sql = "SELECT bs.*, u.display_name, u.avatar FROM broadcast_schedule bs INNER JOIN users u ON bs.user_id = u.id ORDER BY bs.day_of_week, bs.start_time";
$result = $conn->query($sql);
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold mb-2">Yayın Akışı</h1>
    <p class="text-gray-400">Tüm yaklaşan radyo programlarını ve yayınlarını görüntüleyin.</p>
</div>

<?php if ($error): ?>
    <div class="mb-6 p-4 rounded-md bg-error-500 text-white"><?php echo $error; ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="mb-6 p-4 rounded-md bg-success-500 text-white"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (isLoggedIn() && hasRole(['admin', 'dj'])): ?>
<!-- Yayın ekleme formu -->
<div class="bg-gray-800 rounded-lg shadow-lg p-6 mb-10">
    <h2 class="text-xl font-bold mb-4">Yeni Yayın Ekle</h2>
    <form method="POST" action="schedule.php" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="add_schedule" value="1">
        <?php if (hasRole('admin')): ?>
        <div>
            <label class="block text-gray-300 mb-1">DJ *</label>
            <select name="dj_id" class="w-full p-2 bg-gray-700 border border-gray-600 rounded-md text-white" required>
                <option value="">DJ Seçiniz</option>
                <?php foreach ($djs as $dj): ?>
                    <option value="<?php echo $dj['id']; ?>"><?php echo htmlspecialchars($dj['display_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div>
            <label class="block text-gray-300 mb-1">Başlık *</label>
            <input type="text" name="title" class="w-full p-2 bg-gray-700 border border-gray-600 rounded-md text-white" required>
        </div>
        <div>
            <label class="block text-gray-300 mb-1">Gün *</label>
            <select name="day_of_week" class="w-full p-2 bg-gray-700 border border-gray-600 rounded-md text-white" required>
                <?php foreach ($days as $i => $d): ?>
                    <option value="<?php echo $i; ?>"><?php echo $d; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-gray-300 mb-1">Başlangıç Saati *</label>
            <input type="time" name="start_time" class="w-full p-2 bg-gray-700 border border-gray-600 rounded-md text-white" required>
        </div>
        <div>
            <label class="block text-gray-300 mb-1">Bitiş Saati *</label>
            <input type="time" name="end_time" class="w-full p-2 bg-gray-700 border border-gray-600 rounded-md text-white" required>
        </div>
        <div class="md:col-span-2">
            <label class="block text-gray-300 mb-1">Açıklama</label>
            <textarea name="description" rows="2" class="w-full p-2 bg-gray-700 border border-gray-600 rounded-md text-white"></textarea>
        </div>
        <div class="md:col-span-2 text-right">
            <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white py-2 px-6 rounded-md">Ekle</button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Yayın akışı listesi -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($show = $result->fetch_assoc()): ?>
            <div class="bg-gray-800 rounded-lg p-6 transition duration-300 hover:shadow-lg hover:shadow-primary-900/20">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-full overflow-hidden border-2 border-primary-500">
                        <img src="assets/img/<?php echo $show['avatar']; ?>" alt="<?php echo htmlspecialchars($show['display_name']); ?>" class="w-full h-full object-cover" onerror="this.src='assets/img/default-avatar.png'">
                    </div>
                    <div class="ml-4">
                        <h3 class="text-xl font-bold"><?php echo htmlspecialchars($show['title']); ?></h3>
                        <p class="text-gray-400"><?php echo htmlspecialchars($show['display_name']); ?></p>
                    </div>
                </div>
                <div class="bg-gray-700 rounded p-3 text-center mb-4">
                    <?php 
                        // `day_of_week` değerini kontrol et ve eşleştir
                        $dayName = $days[$show['day_of_week']] ?? 'Bilinmeyen Gün';
                    ?>
                    <span class="block text-primary-400 font-bold"><?php echo $dayName; ?></span>
                    <span class="text-gray-300"><?php echo date('H:i', strtotime($show['start_time'])); ?> - <?php echo date('H:i', strtotime($show['end_time'])); ?></span>
                </div>
                <p class="text-gray-400"><?php echo htmlspecialchars_decode($show['description']); ?></p>
                <div class="mt-4 flex justify-between">
                    <!-- Düzenleme Formu -->
                    <form method="POST" action="schedule.php" class="inline-block">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="schedule_id" value="<?php echo $show['id']; ?>">
                        <input type="hidden" name="edit_schedule" value="1">
                        <button type="submit" class="bg-secondary-600 hover:bg-secondary-700 text-white py-1 px-4 rounded-md">Düzenle</button>
                    </form>
                    <!-- Silme Formu -->
                    <form method="POST" action="schedule.php" class="inline-block" onsubmit="return confirm('Bu yayını silmek istediğinize emin misiniz?');">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="schedule_id" value="<?php echo $show['id']; ?>">
                        <input type="hidden" name="delete_schedule" value="1">
                        <button type="submit" class="bg-error-600 hover:bg-error-700 text-white py-1 px-4 rounded-md">Sil</button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-span-1 md:col-span-3 bg-gray-800 rounded-lg p-6 text-center">
            <p class="text-gray-400">Henüz yayın eklenmedi.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'templates/footer.php'; ?>