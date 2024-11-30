<?php
// Start output buffering to prevent any accidental output before JSON response
ob_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Directory to save uploaded images
$dir = 'images/';
$password = 'your_password'; // Hardcoded password

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!empty($_FILES['file'])) {
        // Check for upload errors
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize directive in php.ini',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE directive in the HTML form',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION   => 'File upload stopped by extension',
            ];
            $error = $_FILES['file']['error'];
            $message = isset($uploadErrors[$error]) ? $uploadErrors[$error] : 'Unknown error';
            // Return JSON response
            echo json_encode(['success' => false, 'message' => 'File upload error: ' . $message]);
            ob_end_flush();
            exit;
        }

        // Password check
        if ($_POST['password'] !== $password) {
            echo json_encode(['success' => false, 'message' => 'Incorrect password']);
            ob_end_flush();
            exit;
        }

        // Proceed to store the file
        $fileExtension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm', 'ogg'];

        if (in_array($fileExtension, $allowedExtensions)) {
            $timestamp = time();
            $newFileName = $timestamp . '.' . $fileExtension;
            $uploadPath = $dir . $newFileName;

            // Write the file directly to the images/ folder
            $rawData = file_get_contents($_FILES['file']['tmp_name']);
            if (file_put_contents($uploadPath, $rawData)) {
                // Get the description and save it in a text file
                $description = isset($_POST['description']) ? trim($_POST['description']) : '';
                if ($description !== '') {
                    file_put_contents($dir . $timestamp . '.txt', $description);
                }

                // Return a success JSON response
                echo json_encode(['success' => true, 'message' => 'File uploaded successfully']);
            } else {
                // Return a failure JSON response
                echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
            }
        } else {
            // Return a failure JSON response for invalid file type
            echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        }
    }
    ob_end_flush(); // End output buffering and flush the output
    exit;
}

// Get all files from the directory, sorted by date
$files = array_filter(array_diff(scandir($dir, SCANDIR_SORT_DESCENDING), array('.', '..')), function($file) {
    return pathinfo($file, PATHINFO_EXTENSION) !== 'txt';
});
$imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
$videoExtensions = ['mp4', 'webm', 'ogg'];

// Load descriptions into an associative array
$descriptions = [];
foreach ($files as $file) {
    $filePath = $dir . $file;
    $fileExtension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if (in_array($fileExtension, $imageExtensions) || in_array($fileExtension, $videoExtensions)) {
        $txtFilePath = $dir . pathinfo($file, PATHINFO_FILENAME) . '.txt';
        if (file_exists($txtFilePath)) {
            $descriptions[$file] = file_get_contents($txtFilePath);
        } else {
            $descriptions[$file] = ''; // No description found
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Grid</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0 auto;
            padding: 5%;
            height: 100vh;
            max-width: 800px;
        }
        a {
            color: lightgray;
        }
        .grid-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            padding: 10px;
        }
        .grid-item {
            position: relative;
            width: 100%;
            padding-top: 100%;
            overflow: hidden;
        }
        .grid-item img, .grid-item video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer;
        }
        /* Lightbox Styling */
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .lightbox-content {
            display: flex; /* Enable flexbox layout */
            justify-content: center; /* Center content horizontally */
            align-items: center; /* Center content vertically */
            gap: 5px; /* Add spacing between image/video and description */
            max-width: 90%; /* Prevent content from overflowing */
        }
        .heart-icon {
            position: absolute;
            font-size: 60px;
            color: white;
            cursor: pointer;
            opacity: 0; /* Hide the heart icon by default */
            transition: opacity 0.3s; /* Smooth transition for showing/hiding */
            background-color: rgba(0, 0, 0, 0.5); /* Dark background */
            border-radius: 50%; /* Make it a circle */
            width: 80px;
            height: 80px;
            display: flex;
            justify-content: center;
            align-items: center;
            user-select: none; /* Make text non-selectable */
        }
        .heart-icon a {
            text-decoration: none; /* Remove underline */
            color: inherit; /* Inherit color from parent */
        }
        .lightbox-content:hover .heart-icon {
            opacity: 1; /* Show the heart icon on hover */
        }
        .heart-icon:hover {
            transform: scale(1.2); /* Scale up the heart icon on hover */
        }
        .lightbox img, .lightbox video {
            max-width: 90%;
            max-height: 90%;
            cursor: pointer;
            justify-content: center;
        }
        .lightbox:target {
            display: flex;
        }
        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: #fff;
            font-size: 32px;
            text-decoration: none;
        }
        .description {
            color: black;
            background-color: white; /* White background for description */
            padding: 10px; /* Add padding for readability */
            border-radius: 5px; /* Optional rounded corners */
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2); /* Optional shadow for emphasis */
            max-width: 40%; /* Limit width to avoid overflowing */
            text-align: left; /* Align text to the left */
        }
        .lightbox-prev, .lightbox-next {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 24px;
            text-decoration: none;
            border-radius: 50%;
            cursor: pointer;
            user-select: none;
        }

        .lightbox-prev {
            left: 20px;
        }

        .lightbox-next {
            right: 20px;
        }
        /* Entire page drop zone styling */
        #drop-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 2000;
            color: white;
            font-size: 72px;
        }
        #drop-overlay.visible {
            display: flex;
        }
        /* Add responsiveness */
        @media (max-width: 768px) {
            .grid-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 480px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<!-- Overlay shown during drag/drop -->
<div id="drop-overlay">+</div>

<div class="grid-container">
    <?php
    foreach ($files as $key => $file) {
        $filePath = $dir . $file;
        $fileExtension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $description = isset($descriptions[$file]) ? $descriptions[$file] : '';
        // $nextIndex = ($key + 1) % count($files); // Circular navigation
        // $prevIndex = ($key - 1 + count($files)) % count($files);
        // $nextFile = $files[$nextIndex];
        // $prevFile = $files[$prevIndex];

        // Extract the timestamp from the filename (assuming the filename is based on time())
        // Example filename: "1672531199.jpg"
        $timestamp = pathinfo($file, PATHINFO_FILENAME); // Extract the timestamp part of the filename
        if (is_numeric($timestamp)) {
            $dateTime = DateTime::createFromFormat('U', $timestamp);
            $formattedDateTime = $dateTime->format('F j, Y, g:i a'); // Format: Month Day, Year, Hour:Minute AM/PM
        } else {
            $formattedDateTime = 'Unknown date';
        }

        if (in_array($fileExtension, $imageExtensions)) {
            // Display cropped image with lightbox
            echo "
            <div class='grid-item' title='$description'>
                <a href='#lightbox-$file'>
                    <img src='$filePath' alt='$description'>
                </a>
            </div>
            <div id='lightbox-$file' class='lightbox'>
                <a href='#' class='lightbox-close'>&times;</a>
                <div class='lightbox-content'>
                    <img src='$filePath' alt='$description'>
                    <div class='heart-icon'><a href='mailto:maximilian@ernestus.de?subject=Hey%20I%20like%20your%20post&body=I%20wanted%20to%20let%20you%20know%20that%20I%20really%20enjoyed%20your%20post%20$file%20may%20your%20ego%20be%20caressed%20gently'>♥︎</a></div>
                </div>
                <div class='description'>$description<br><br><a href=#lightbox-$file>$formattedDateTime</a></div>
            </div>";
        } elseif (in_array($fileExtension, $videoExtensions)) {
            // Display cropped video with lightbox
            echo "
            <div class='grid-item' title='$description'>
                <a href='#lightbox-$file'>
                    <video muted playsinline>
                        <source src='$filePath' type='video/$fileExtension'>
                    </video>
                </a>
            </div>
            <div id='lightbox-$file' class='lightbox'>
                <a href='#' class='lightbox-close'>&times;</a>
                <div class='lightbox-content'>
                    <video controls playsinline>
                        <source src='$filePath' type='video/$fileExtension'>
                    </video>
                    <div class='heart-icon'><a href='mailto:maximilian@ernestus.de?subject=Hey%20I%20like%20your%20post&body=I%20wanted%20to%20let%20you%20know%20that%20I%20really%20enjoyed%20your%20post%20$file%20may%20your%20ego%20be%20caressed%20gently'>♥︎</a></div>
                </div>
                <div class='description'>$description<br><br><a href=#lightbox-$file>$formattedDateTime</a></div>
            </div>";
        }
    }
    ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const dropOverlay = document.getElementById('drop-overlay');

    // Show overlay when files are dragged over the page
    document.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropOverlay.classList.add('visible');
    });

    // Hide overlay when drag leaves the page
    document.addEventListener('dragleave', () => {
        dropOverlay.classList.remove('visible');
    });

    // Handle drop event for the entire page
    document.addEventListener('drop', (e) => {
        e.preventDefault();
        dropOverlay.classList.remove('visible');

        const file = e.dataTransfer.files[0];
        if (file) {
            const formData = new FormData();
            const description = prompt("Enter a short description for the file:");
            formData.append('file', file);
            formData.append('description', description);
            const password = prompt("Enter the password:");
            formData.append('password', password);

            // Send file to server using fetch
            fetch('', {
                method: 'POST',
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('File uploaded successfully!');
                    location.reload(); // Reload page to display new file
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                alert('Error uploading file: ' + err.message);
            });
        }
    });

    // Add event listener to close the lightbox and stop video playback
    document.addEventListener('click', (event) => {
        if (event.target.classList.contains('lightbox-close')) {
            const lightbox = event.target.closest('.lightbox');
            if (lightbox) {
                const video = lightbox.querySelector('video');
                if (video) {
                    video.pause(); // Stop the video playback
                    video.currentTime = 0; // Reset video to the start
                }
            }
        }
    });


    // Add event listener for video hover to play silently and loop
    const videos = document.querySelectorAll('.grid-item video');
    videos.forEach(video => {
        video.addEventListener('mouseenter', () => {
            video.play(); // Play video on hover
            video.loop = true; // Loop the video
        });
        video.addEventListener('mouseleave', () => {
            video.pause(); // Pause video when not hovering
            video.currentTime = 0; // Reset video to the start
        });
    });
});
</script>
</body>
</html>

