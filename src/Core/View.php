<?php
namespace App\Core;

class View 
{
    /**
     * Tam sayfa render eder (Layout içerisine view basar)
     */
    public static function render(string $viewPath, array $data = [], string $layout = 'layouts/main'): void 
    {
        extract($data);

        // İçerik dosyasının yolu
        $viewFile = __DIR__ . "/../../views/{$viewPath}.php";
        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View dosyası bulunamadı: {$viewPath}");
        }

        // Çıktı tamponlamasını başlat ve view içeriğini değişkene al
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Layout dosyasını yükle (Layout içinde $content değişkeni basılacaktır)
        $layoutFile = __DIR__ . "/../../views/{$layout}.php";
        if (file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            echo $content; // Layout yoksa doğrudan içeriği bas
        }
    }

    /**
     * AJAX / Slot ızgaraları için sadece HTML parçası (Partial) render eder
     */
    public static function renderPartial(string $partialPath, array $data = []): void 
    {
        extract($data);
        $partialFile = __DIR__ . "/../../views/{$partialPath}.php";
        
        if (!file_exists($partialFile)) {
            throw new \RuntimeException("Partial view bulunamadı: {$partialPath}");
        }

        require $partialFile;
    }
}