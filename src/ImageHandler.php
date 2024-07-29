<?php
/**
 * Image handle class
 *
 * @author      Stanley Sie <swookon@gmail.com>
 * @access      public
 * @version     Release: 1.0
 */

namespace Stanleysie\HkImg;

use Jeffho\HkAwsS3\AWSS3Handler;

class ImageHandler
{
    /**
     * Handle image size.
     *
     * @param string $originalImagePath
     * @param string $resizedImagePath
     * @param int $targetWidth
     * @param int $targetHeight
     * @return bool
     */
    public function resizeImage($originalImagePath, $resizedImagePath, $targetWidth, $targetHeight)
    {
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
                imagejpeg($resizedImage, $resizedImagePath, 100);
                break;
            case IMAGETYPE_PNG:
                imagepng($resizedImage, $resizedImagePath, 9);
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
    public function compressImage($originalImagePath, $compressedImagePath, $quality)
    {
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
                // 保存透明度
                imagesavealpha($image, true);
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
    public function convertToJpg($originalImagePath, $outputImagePath)
    {
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
                //echo "不支持的圖片格式";
                return false;
        }

        // 將圖像資源轉換為JPG格式並保存
        $result = imagejpeg($image, $outputImagePath, 100); // 100為最高質量

        // 釋放圖像資源
        imagedestroy($image);

        return $result;
    }

    /**
     * Resize image while maintaining aspect ratio.
     *
     * @param string $originalImagePath The path to the original image.
     * @param string $resizedImagePath The path where the resized image will be saved.
     * @param int $targetWidth The target width.
     * @param int $targetHeight The target height.
     * @return bool Returns true on success or false on failure.
     */
    public function resizeImageMaintainAspectRatio($originalImagePath, $resizedImagePath, $targetWidth, $targetHeight)
    {
        // Get the dimensions and type of the original image
        list($originalWidth, $originalHeight, $type) = getimagesize($originalImagePath);

        // Calculate the aspect ratio
        $aspectRatio = $originalWidth / $originalHeight;

        // If both target width and height are provided, calculate the new dimensions based on the aspect ratio
        if ($targetWidth && $targetHeight) {
            if ($targetWidth / $targetHeight > $aspectRatio) {
                $newWidth = $targetHeight * $aspectRatio;
                $newHeight = $targetHeight;
            } else {
                $newWidth = $targetWidth;
                $newHeight = $targetWidth / $aspectRatio;
            }
        }
        // If only target width is provided, calculate the height based on the aspect ratio
        elseif ($targetWidth) {
            $newWidth = $targetWidth;
            $newHeight = $targetWidth / $aspectRatio;
        }
        // If only target height is provided, calculate the width based on the aspect ratio
        elseif ($targetHeight) {
            $newHeight = $targetHeight;
            $newWidth = $targetHeight * $aspectRatio;
        }
        // If neither width nor height is provided, return false
        else {
            return false;
        }

        // Create a new image resource for the resized image
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

        // Create an image resource based on the original image type
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
                return false; // Unsupported image type
        }

        // Resize the image while maintaining the aspect ratio
        imagecopyresampled($resizedImage, $originalImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

        // Save the resized image
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($resizedImage, $resizedImagePath, 100);
                break;
            case IMAGETYPE_PNG:
                imagepng($resizedImage, $resizedImagePath, 9); // Use compression level 9 for PNG
                break;
            case IMAGETYPE_GIF:
                imagegif($resizedImage, $resizedImagePath);
                break;
        }

        // Free up memory
        imagedestroy($originalImage);
        imagedestroy($resizedImage);

        return true;
    }

    /**
     * Upload image to S3 with resizing, compressing, and object deletion.
     *
     * @param string $bucketName The name of the S3 bucket.
     * @param string $region The AWS region of the S3 bucket.
     * @param string $accessKeyId The AWS access key ID.
     * @param string $accessKeySecret The AWS access key secret.
     * @param array $config The upload configuration containing:
     *                      - temp_file: string The path to the temporary image file.
     *                      - width: int The target width for resizing.
     *                      - height: int The target height for resizing.
     *                      - obj_key: string The key of the object in S3.
     *                      - old_obj: string | null The key of the old object in S3 to be deleted.
     * @return bool|string Returns the URL of the uploaded object on success, otherwise false.
     */
    public function imgUpload($bucketName, $region, $accessKeyId, $accessKeySecret, array $config)
    {
        // Extract configuration parameters
        $tempFile = $config['temp_file'];
        $width = $config['width'];
        $height = $config['height'];
        $objKey = $config['obj_key'];
        $oldObj = $config['old_obj'] ?? null;

        // Convert the image
        $convertImagePath = sys_get_temp_dir() . '/' . uniqid('convert_image_') . '.jpg';
        if (!$this->convertToJpg($tempFile, $convertImagePath)) {
            return false;
        }

        // Resize the image
        $resizedImagePath = sys_get_temp_dir() . '/' . uniqid('resized_image_') . '.jpg';
        if (!$this->resizeImageMaintainAspectRatio($convertImagePath, $resizedImagePath, $width, $height)) {
            return false;
        }

        // Compress the image
        $compressedImagePath = sys_get_temp_dir() . '/' . uniqid('compressed_image_') . '.jpg';
        if (!$this->compressImage($resizedImagePath, $compressedImagePath, 90)) {
            return false;
        }

        // Set AWS Client
        $AWSS3Handler = new AWSS3Handler();
        $client = $AWSS3Handler->setClient($region, $accessKeyId, $accessKeySecret);
        // Upload the new object to S3
        $result = $AWSS3Handler->putObject($client, $bucketName, $objKey, $compressedImagePath);
        if ($result->status == 'failure') {
            return false;
        }

        // Clean up temporary files
        unlink($convertImagePath);
        unlink($resizedImagePath);
        unlink($compressedImagePath);

        return $result->object;
    }

}
