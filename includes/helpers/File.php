<?php
namespace THREEDPRESS\Helpers;

class File {
    /**
     * Validate uploaded 3D file
     * @param array $file
     * @return bool|string true if valid, error message if not
     */
    public static function validate($file) {
        $allowed = ['stl', 'obj'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            return 'Invalid file type.';
        }
        if ($file['size'] > 20 * 1024 * 1024) { // 20MB limit
            return 'File too large.';
        }
        return true;
    }
}
