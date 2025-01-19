<?php
session_start();
require_once '../includes/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Ambil user_id dari parameter URL
$selected_user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;



// Ambil informasi user yang dipilih
$stmt = $db->prepare("SELECT username FROM users WHERE id = :user_id");
$stmt->bindParam(":user_id", $selected_user_id);
$stmt->execute();
$selected_user = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil dokumen user yang dipilih
function getUserDocuments($db, $user_id)
{
    $query = "SELECT * FROM documents  
              WHERE uploaded_by = :user_id AND is_deleted = 0 
              ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$documents = getUserDocuments($db, $selected_user_id);  // Changed variable name to match function
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumen User - <?php echo htmlspecialchars($selected_user['username']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    Dokumen User: <?php echo htmlspecialchars($selected_user['username']); ?>
                </h1>
                <p class="text-gray-600">Total dokumen: <?php echo count($documents); ?></p>
            </div>
            <a href="users.php" class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition">
                <i class="ri-arrow-left-line mr-2"></i>Kembali
            </a>
        </div>

        <?php if (empty($documents)): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative">
                Tidak ada dokumen yang ditemukan untuk user ini.
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama
                                Dokumen</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Ukuran</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tanggal Upload</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($documents as $doc): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <i class="ri-file-line text-gray-500 mr-2"></i>
                                        <span class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($doc['title']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($doc['file_type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-500">
                                        <?php
                                        if (isset($doc['file_size'])) {
                                            echo number_format($doc['file_size'] / 1024, 2) . ' KB';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-500">
                                        <?php echo date('d/m/Y H:i', strtotime($doc['created_at'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusClass = $doc['is_deleted'] == 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                    $statusText = $doc['is_deleted'] == 0 ? 'active' : 'deleted';
                                    ?>
                                    <span class="text-sm px-2 py-1 rounded-full <?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="flex space-x-2">
                                        <?php if ($doc['is_deleted'] == 0): ?>
                                            <a href="document-handler.php?id=<?php echo $doc['id']; ?>&action=view"
                                                class="text-green-600 hover:text-green-900 transition-colors duration-200"
                                                title="Lihat Dokumen">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            <a href="document-handler.php?id=<?php echo $doc['id']; ?>&action=download"
                                                class="text-black-600 hover:text-black-900 transition-colors duration-200"
                                                title="Unduh Dokumen">
                                                <i class="ri-download-line"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function viewDocument(docId) {

        }
    </script>
</body>

</html>