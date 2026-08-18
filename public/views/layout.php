<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FFMS - Intelligent Farm Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <nav class="bg-green-700 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between">
            <a href="index.php" class="text-xl font-bold">FFMS</a>
            <div class="space-x-4">
                <a href="index.php?module=Farm" class="hover:text-green-200">Farms</a>
                <a href="index.php?module=Crop" class="hover:text-green-200">Crops</a>
                <!-- Add more links -->
            </div>
        </div>
    </nav>
    <main class="flex-grow container mx-auto p-6">
        <?php echo $content; ?>
    </main>
    <footer class="bg-gray-800 text-white text-center p-4 mt-auto">
        &copy; 2026 Intelligent Farm Management System
    </footer>
</body>
</html>
