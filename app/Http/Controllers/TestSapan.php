<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestSapan extends Controller
{
    public function optimizeGifWithGifsicle($filePath) {
        // $outputFilePath = str_replace("banner-image", "banner-image1", $filePath);
    
        // if (!file_exists(dirname($outputFilePath))) {
        //     if (!mkdir(dirname($outputFilePath), 0755, true)) {
        //         throw new Exception("Failed to create directory: " . dirname($outputFilePath));
        //     }
        // }

        $outputFilePath = $filePath;
    
        $command = escapeshellcmd("gifsicle --batch --optimize=3 --colors 18 " . escapeshellarg($filePath) . " -o " . escapeshellarg($outputFilePath));
        exec($command . ' 2>&1', $output, $returnVar);
    
        if ($returnVar !== 0) {
            throw new Exception("Gifsicle optimization failed: " . implode("\n", $output) . " | Command: " . $command);
        }
    
        echo "GIF optimized successfully and saved to $outputFilePath.";
    }


    public function getAllGifImagePath()
    {
        $path = storage_path();
        $path = $path."/app/public/banner-image/";
        $gifFiles = glob($path . "*.gif", GLOB_BRACE);

        if ($gifFiles) {
            $count = 0;
            // $countFile = count($gifFiles);
            foreach ($gifFiles as $file) {
                $fileSize = filesize($file) / 1024;
                if ($fileSize >= 2000) {
                    $count += 1;
                    echo $file."====size====";
                    echo $fileSize."====count====".$count."</br>"."</br>";
                    $this->optimizeGifWithGifsicle($file);
                }
            }
        }
        
        // dd($gifFiles);
        return 'hey sapan it is working';
    }
}
