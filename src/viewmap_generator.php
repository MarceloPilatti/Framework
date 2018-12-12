#!/usr/bin/env php
<?php
$mainModule=dirname(__DIR__, 4)."/app/main/view";
$adminModule=dirname(__DIR__, 4)."/app/admin/view";

$filesMain = findTemplateFilesInTemplatePath($mainModule);
$filesAdmin = findTemplateFilesInTemplatePath($adminModule);

$mainRealPath = realpath($mainModule);
$adminRealPath = realpath($adminModule);

$mainEntries = array_map(function ($file) use ($mainModule, $mainRealPath) {
    $file = str_replace('\\', '/', $file);

    $template = (0 === strpos($file, $mainRealPath))
        ? substr($file, strlen($mainRealPath))
        : $file;

    $template = (0 === strpos($template, $mainModule))
        ? substr($template, strlen($mainModule))
        : $template;

    $template = preg_match('#(?P<template>.*?)\.[a-z0-9]+$#i', $template, $matches)
        ? $matches['template']
        : $template;

    $template = preg_replace('#^\.*/#', '', $template);

    return sprintf("    '%s' => __DIR__ . '/%s',", "main/".$template, $file);
}, $filesMain);

$adminEntries = array_map(function ($file) use ($adminModule, $adminRealPath) {
    $file = str_replace('\\', '/', $file);

    $template = (0 === strpos($file, $adminRealPath))
        ? substr($file, strlen($adminRealPath))
        : $file;

    $template = (0 === strpos($template, $adminModule))
        ? substr($template, strlen($adminModule))
        : $template;

    $template = preg_match('#(?P<template>.*?)\.[a-z0-9]+$#i', $template, $matches)
        ? $matches['template']
        : $template;

    $template = preg_replace('#^\.*/#', '', $template);

    return sprintf("    '%s' => __DIR__ . '/%s',", "admin/".$template, $file);
}, $filesAdmin);

$entries=array_merge($mainEntries, $adminEntries);

echo '<' . "?php\nreturn [\n"
    . implode("\n", $entries) . "\n"
    . '];';
exit(0);

function findTemplateFilesInTemplatePath($templatePath)
{
    $rdi = new \RecursiveDirectoryIterator(
        $templatePath,
        \RecursiveDirectoryIterator::FOLLOW_SYMLINKS | \RecursiveDirectoryIterator::SKIP_DOTS
    );
    $rii = new \RecursiveIteratorIterator($rdi, \RecursiveIteratorIterator::LEAVES_ONLY);

    $files = [];
    foreach ($rii as $file) {
        if (strtolower($file->getExtension()) != 'phtml') {
            continue;
        }

        $files[] = $file->getPathname();
    }

    return $files;
}
