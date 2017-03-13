<?php
$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__);
return PhpCsFixer\Config::create()
    ->setUsingCache(false)
    ->setRules(
        array(
            '@PSR2' => true
        )
    )
    ->setFinder($finder);
