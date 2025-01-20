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

$documents = getUserDocuments($db, $selected_user_id);
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

<body class="bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <!-- Header Section -->
        <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    Dokumen User: <?php echo htmlspecialchars($selected_user['username']); ?>
                </h1>
                <div class="flex items-center text-gray-600">
                    <i class="ri-file-list-line mr-2"></i>
                    <span>Total dokumen: <?php echo count($documents); ?></span>
                </div>
            </div>
            <a href="users.php" 
               class="inline-flex items-center px-4 py-2 rounded-lg bg-white border border-gray-300 hover:bg-gray-50 
                      transition duration-150 ease-in-out shadow-sm text-gray-700 text-sm">
                <i class="ri-arrow-left-line mr-2"></i>
                Kembali
            </a>
        </div>

        <?php if (empty($documents)): ?>
        <div class="rounded-lg bg-yellow-50 p-4 border border-yellow-200">
            <div class="flex">
                <i class="ri-information-line text-yellow-500 mr-3 mt-0.5"></i>
                <p class="text-sm text-yellow-700">
                    Tidak ada dokumen yang ditemukan untuk user ini.
                </p>
            </div>
        </div>
        <?php else: ?>
        <!-- Table Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-50">
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Nama Dokumen
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tipe
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Ukuran
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tanggal Upload
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($documents as $doc): ?>
                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <i class="ri-file-line text-gray-400 text-lg mr-3"></i>
                                    <span class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($doc['title']); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700">
                                    <?php echo htmlspecialchars($doc['file_type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php
                                if (isset($doc['file_size'])) {
                                    echo number_format($doc['file_size'] / 1024, 2) . ' KB';
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-600">
                                    <?php echo date('d/m/Y', strtotime($doc['created_at'])); ?>
                                    <span class="text-gray-400">
                                        <?php echo date('H:i', strtotime($doc['created_at'])); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($doc['is_deleted'] == 0): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700">
                                    Active
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700">
                                    Deleted
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($doc['is_deleted'] == 0): ?>
                                <div class="flex space-x-3">
                                    <a href="document-handler.php?id=<?php echo $doc['id']; ?>&action=view"
                                       class="text-sm text-green-600 hover:text-green-800 transition-colors duration-150">
                                        <i class="ri-eye-line text-lg"></i>
                                    </a>
                                    <a href="document-handler.php?id=<?php echo $doc['id']; ?>&action=download"
                                       class="text-sm text-gray-600 hover:text-gray-800 transition-colors duration-150">
                                        <i class="ri-download-line text-lg"></i>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function viewDocument(docId) {
            // Original empty function maintained
        }
    </script>
</body>
</html>