<?php
session_start();
require_once '../includes/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$selected_user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$stmt = $db->prepare("SELECT username FROM users WHERE id = :user_id");
$stmt->bindParam(":user_id", $selected_user_id);
$stmt->execute();
$selected_user = $stmt->fetch(PDO::FETCH_ASSOC);

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
    <title>Documents - <?php echo htmlspecialchars($selected_user['username']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <!-- Header Section with Modern Design -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200/60 p-6 mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="p-2 bg-blue-50 rounded-lg">
                            <i class="ri-folder-user-line text-2xl text-blue-600"></i>
                        </div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            <?php echo htmlspecialchars($selected_user['username']); ?> Documents
                        </h1>
                    </div>
                    <div class="flex items-center text-gray-600 gap-4">
                        <div class="flex items-center">
                            <i class="ri-file-list-line mr-2"></i>
                            <span class="text-sm">Total: <?php echo count($documents); ?> documents</span>
                        </div>
                        <div class="flex items-center">
                            <i class="ri-time-line mr-2"></i>
                            <span class="text-sm">Last updated:
                                <?php echo !empty($documents) ? date('M d, Y', strtotime($documents[0]['created_at'])) : 'N/A'; ?></span>
                        </div>
                    </div>
                </div>
                <a href="users.php" class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-50 hover:bg-gray-100 
                          transition duration-150 ease-in-out shadow-sm text-gray-700 text-sm border border-gray-200">
                    <i class="ri-arrow-left-line mr-2"></i>
                    Back
                </a>
            </div>
        </div>

        <?php if (empty($documents)): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200/60 p-8 text-center">
                <div class="max-w-md mx-auto">
                    <div class="p-3 bg-yellow-50 rounded-full inline-block mb-4">
                        <i class="ri-folder-warning-line text-3xl text-yellow-500"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Documents Found</h3>
                    <p class="text-gray-600">This user hasn't uploaded any documents yet.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200/60 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50/50">
                                <th scope="col"
                                    class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Document Details
                                </th>
                                <th scope="col"
                                    class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Type
                                </th>
                                <th scope="col"
                                    class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Size
                                </th>
                                <th scope="col"
                                    class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Upload Date
                                </th>
                                <th scope="col"
                                    class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th scope="col"
                                    class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($documents as $doc): ?>
                                <tr class="hover:bg-gray-50/50 transition-colors duration-150">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="p-2 bg-blue-50 rounded-lg mr-3">
                                                <i class="ri-file-line text-blue-600"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($doc['title']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700">
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
                                        <div class="text-sm text-gray-900">
                                            <?php echo date('M d, Y', strtotime($doc['created_at'])); ?>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('H:i', strtotime($doc['created_at'])); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($doc['is_deleted'] == 0): ?>
                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700">
                                                <i class="ri-checkbox-circle-line mr-1"></i> Active
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700">
                                                <i class="ri-close-circle-line mr-1"></i> Deleted
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($doc['is_deleted'] == 0): ?>
                                            <div class="flex space-x-3">
                                                <a href="document-handler.php?id=<?php echo $doc['id']; ?>&action=view"
                                                    class="p-1.5 bg-green-50 rounded-lg text-green-600 hover:bg-green-100 transition-colors duration-150">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                                <a href="document-handler.php?id=<?php echo $doc['id']; ?>&action=download"
                                                    class="p-1.5 bg-gray-50 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors duration-150">
                                                    <i class="ri-download-line"></i>
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
</body>

</html>