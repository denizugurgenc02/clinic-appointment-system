<?php
namespace App\Core\Router;

class Request 
{
    public function getPath(): string 
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        // URL'deki query string kısımlarını (?id=5 vb.) temizle
        $position = strpos($path, '?');
        if ($position !== false) {
            $path = substr($path, 0, $position);
        }
        return rtrim($path, '/') ?: '/';
    }

    public function getMethod(): string 
    {
        return strtolower($_SERVER['REQUEST_METHOD']);
    }
}