<?php
/**
 * Image handle class
 *
 * @author      Stanley Sie <swookon@gmail.com>
 * @access      public
 * @version     Release: 1.0
 */

namespace Stanleysie\HkImg;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use \PDO as PDO;

class ImageHandler
{
    /**
     * database
     *
     * @var object
     */
    private $database;

    /**
     * initialize
     */
    public function __construct($db = null)
    {
        $this->database = $db;
    }

    /**
     * Handle image size.
     *
     * @param string $originalImagePath
     * @param string $resizedImagePath
     * @param int $targetWidth
     * @param int $targetHeight 
     * @return bool
     */
    public function resizeImage($originalImagePath, $resizedImagePath, $targetWidth, $targetHeight) {
        // 獲取原始圖片的尺寸和類型
        list($originalWidth, $originalHeight, $type) = getimagesize($originalImagePath);
    
        // 創建一個新的圖像資源，用於後續的圖像操作
        $resizedImage = imagecreatetruecolor($targetWidth, $targetHeight);
    
        // 根據原始圖片類型創建一個圖像資源
        switch ($type) {
            case IMAGETYPE_JPEG:
                $originalImage = imagecreatefromjpeg($originalImagePath);
                break;
            case IMAGETYPE_PNG:
                $originalImage = imagecreatefrompng($originalImagePath);
                break;
            case IMAGETYPE_GIF:
                $originalImage = imagecreatefromgif($originalImagePath);
                break;
            default:
                return false; // 不支持的圖片類型
        }
    
        // 調整圖片尺寸
        imagecopyresampled($resizedImage, $originalImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $originalWidth, $originalHeight);
    
        // 保存調整後的圖片
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($resizedImage, $resizedImagePath);
                break;
            case IMAGETYPE_PNG:
                imagepng($resizedImage, $resizedImagePath);
                break;
            case IMAGETYPE_GIF:
                imagegif($resizedImage, $resizedImagePath);
                break;
        }
    
        // 釋放圖像資源
        imagedestroy($originalImage);
        imagedestroy($resizedImage);
    
        return true;
    }
    
    /**
     * Handle image compress.
     *
     * @param string $originalImagePath
     * @param string $compressedImagePath
     * @param int $quality
     * @return bool
     */
    public function compressImage($originalImagePath, $compressedImagePath, $quality) {
        // 獲取圖片類型
        $type = exif_imagetype($originalImagePath);
    
        // 根據圖片類型讀取圖片
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($originalImagePath);
                imagejpeg($image, $compressedImagePath, $quality);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($originalImagePath);
                // PNG的質量參數範圍是0（無壓縮）到9
                $pngQuality = ($quality - 100) / 11.111111;
                $pngQuality = round(abs($pngQuality));
                imagepng($image, $compressedImagePath, $pngQuality);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($originalImagePath);
                imagegif($image, $compressedImagePath);
                break;
            default:
                return false; // 不支持的圖片類型
        }
    
        // 釋放圖像資源
        imagedestroy($image);
    
        return true;
    }

    /**
     * Handle image convert.
     *
     * @param string $originalImagePath
     * @param string $outputImagePath
     * @return bool
     */
    public function convertToJpg($originalImagePath, $outputImagePath) {
        // 獲取原始圖片的類型
        $type = exif_imagetype($originalImagePath);
    
        // 根據原始圖片類型創建圖像資源
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($originalImagePath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($originalImagePath);
                // 處理PNG透明度問題
                $background = imagecreatetruecolor(imagesx($image), imagesy($image));
                imagefill($background, 0, 0, imagecolorallocate($background, 255, 255, 255));
                imagecopy($background, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
                $image = $background;
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($originalImagePath);
                break;
            case IMAGETYPE_BMP:
                $image = imagecreatefrombmp($originalImagePath);
                break;
            default:
                echo "不支持的圖片格式";
                return false;
        }
    
        // 將圖像資源轉換為JPG格式並保存
        $result = imagejpeg($image, $outputImagePath, 100); // 100為最高質量
    
        // 釋放圖像資源
        imagedestroy($image);
    
        return $result;
    }

    /**
     * Handle upload to s3.
     *
     * @param string $filePath
     * @param string $bucketName
     * @param int $key
     * @param string $region
     * @param string $accessKeyId
     * @param string $accessKeySecret
     * @return string
     */
    public function uploadToS3($filePath, $bucketName, $key, $region, $accessKeyId, $accessKeySecret) {
        $s3Client = new S3Client([
            'version' => 'latest',
            'region'  => $region,
            'credentials' => [
                'key'    => $accessKeyId,
                'secret' => $accessKeySecret,
            ],
        ]);
    
        try {
            $result = $s3Client->putObject([
                'Bucket' => $bucketName,   // S3 桶名稱
                'Key'    => $key,          // S3 中的物件名稱，例如 'folder/subfolder/filename'         
                'SourceFile' => $filePath,
                'ACL'    => 'public-read', // 根據需要設置
            ]);
            return $result['ObjectURL'];   // 返回文件URL
        } catch (AwsException $e) {
            // 處理錯誤
            echo $e->getMessage();
            return null;
        }
    }
}
