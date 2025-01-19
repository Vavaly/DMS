<?php
class FileHandler {
    private $uploadDirectory;
    
    public function __construct($uploadDirectory = 'uploads/') {
        $this->uploadDirectory = $uploadDirectory;
        
        // Create upload directory if it doesn't exist
        if (!file_exists($this->uploadDirectory)) {
            mkdir($this->uploadDirectory, 0755, true);
        }
    }
    
    /**
     * Get the full path of a file
     */
    public function getFilePath($filename) {
        return $this->uploadDirectory . $filename;
    }
    
    /**
     * Check if file exists
     */
    public function fileExists($filename) {
        return file_exists($this->getFilePath($filename));
    }
    
    /**
     * Delete a file
     */
    public function deleteFile($filename) {
        $filepath = $this->getFilePath($filename);
        if ($this->fileExists($filename)) {
            return unlink($filepath);
        }
        return false;
    }
    
    /**
     * Get file mime type
     */
    public function getFileMimeType($filename) {
        $filepath = $this->getFilePath($filename);
        if ($this->fileExists($filename)) {
            return mime_content_type($filepath);
        }
        return false;
    }
    
    /**
     * Get file size
     */
    public function getFileSize($filename) {
        $filepath = $this->getFilePath($filename);
        if ($this->fileExists($filename)) {
            return filesize($filepath);
        }
        return false;
    }
    
    /**
     * Stream file for download
     */
    public function streamFile($filename, $originalFilename = null, $forceDownload = false) {
        $filepath = $this->getFilePath($filename);
        
        if (!$this->fileExists($filename)) {
            return false;
        }
        
        $mime = $this->getFileMimeType($filename);
        $size = $this->getFileSize($filename);
        
        // Set proper headers
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $size);
        header('Content-Disposition: ' . ($forceDownload ? 'attachment' : 'inline') . 
               '; filename="' . ($originalFilename ?? $filename) . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Stream file
        readfile($filepath);
        return true;
    }
}