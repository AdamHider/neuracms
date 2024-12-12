<?php
use ScssPhp\ScssPhp\Compiler;

if (!function_exists('useStyle')) {
    function useStyle($scssPath)
    {
        $scssCompiler = new Compiler();

        $inputFile = FCPATH . $scssPath ;
        
        $outputFile = FCPATH . 'assets/css-compiled/' . md5($scssPath) . '.css';
        if (file_exists($inputFile)) {
            $scssContent = file_get_contents($inputFile);
            $compiledCss = $scssCompiler->compile($scssContent);
            file_put_contents($outputFile, $compiledCss);
        }
        return base_url('/assets/css-compiled/' . md5($scssPath) . '.css');
    }
}
