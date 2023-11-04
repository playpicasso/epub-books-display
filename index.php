<?php

function extractCoverImage($epubFile) {
    $zip = new ZipArchive();
    $coverImagePath = 'default_cover.jpg'; // Default cover image path

    if ($zip->open($epubFile)) {
        // Get the path to the .opf file from the container.xml
        $container = simplexml_load_string($zip->getFromName('META-INF/container.xml'));
        $opfFilePath = $container->rootfiles->rootfile['full-path'];

        // Load the .opf file to find the cover image
        $opfFile = simplexml_load_string($zip->getFromName($opfFilePath));
        $opfFile->registerXPathNamespace('opf', 'http://www.idpf.org/2007/opf');

        // Find item with property 'cover-image' or referenced as cover in <meta> tag
        $coverItem = $opfFile->xpath('//opf:item[@properties="cover-image"]') ?: $opfFile->xpath('//opf:meta[@name="cover"]/@content');
        
        if ($coverItem) {
            $coverId = (string) $coverItem[0]['id']; // In case of properties="cover-image"
            if (!$coverId) {
                $coverId = (string) $coverItem[0]; // In case of <meta name="cover">
            }
            $coverHref = $opfFile->xpath("//opf:item[@id='$coverId']/@href")[0];

            // Handle possible path issues (if coverHref is a path)
            $pathParts = pathinfo($opfFilePath);
            $coverPath = (isset($pathParts['dirname']) && $pathParts['dirname'] != '.' ? $pathParts['dirname'] . '/' : '') . $coverHref;

            // Extract and save the cover image
            $coverImageData = $zip->getFromName($coverPath);
            if ($coverImageData) {
                $imagePath = 'covers/' . basename($epubFile, '.epub') . '.' . pathinfo($coverPath, PATHINFO_EXTENSION);
                file_put_contents($imagePath, $coverImageData);
                $coverImagePath = $imagePath;
            }
        }
        $zip->close();
    }

    return $coverImagePath;
}

// Collect all .epub files in the current directory
$files = glob("*.epub");

// Create 'covers' directory if it doesn't exist
if (!file_exists('covers')) {
    mkdir('covers', 0777, true);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PLAY's Library</title>
    <style>
        .book-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            padding: 10px;
        }
        .book {
            text-align: center;
            margin-bottom: 20px;
        }
        .book img {
            max-width: 100%;
            height: auto;
            display: block; /* Ensure there's no extra space under the image */
            margin-bottom: -4px; /* Adjust as necessary to remove any gap */
        }
    </style>
</head>
<body>
    <div class="book-grid">
        <?php foreach ($files as $file): ?>
            <div class="book">
                <a href="<?php echo htmlspecialchars($file); ?>" download>
                    <?php $coverImage = extractCoverImage($file); ?>
                    <img src="<?php echo htmlspecialchars($coverImage); ?>" alt="Book Cover">
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
