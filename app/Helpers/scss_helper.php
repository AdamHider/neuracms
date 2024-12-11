<?php
use ScssPhp\ScssPhp\Compiler;

if (!function_exists('useStyle')) {
    function useStyle($scssPath)
    {
        $scssCompiler = new Compiler();

        // Путь к файлам SCSS и CSS
        $inputFile = FCPATH . $scssPath ;
        $outputFile = FCPATH . 'assets/css-compiled/' . 'main' . '.css';

        // Компилируем SCSS в CSS
        if (file_exists($inputFile)) {
            $scssContent = file_get_contents($inputFile);
            $compiledCss = $scssCompiler->compile($scssContent);
            file_put_contents($outputFile, $compiledCss);
        }


        // Возвращаем путь к скомпилированному CSS файлу
        return base_url('/assets/css-compiled/' . 'main' . '.css');
    }
}
