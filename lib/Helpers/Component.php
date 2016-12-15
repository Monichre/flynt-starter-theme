<?php

namespace Flynt\Helpers;

use RecursiveDirectoryIterator;
use Flynt;
use Flynt\Config;
use Flynt\Helpers\Utils;

class Component {

  const DEFAULT_OPTIONS = [
    'dependencies' => [],
    'version' => null,
    'inFooter' => true,
    'media' => 'all'
  ];

  public static function registerAll() {
    // TODO use new Core functionality after adding the feature for dirs
    $directoryIterator = new RecursiveDirectoryIterator(Config\COMPONENT_PATH, RecursiveDirectoryIterator::SKIP_DOTS);

    foreach ($directoryIterator as $name => $file) {
      if ($file->isDir()) {
        Flynt\registerComponent($file->getFilename());
      }
    }
  }

  public static function enqueueAssets($componentName, array $dependencies = []) {

    // register dependencies
    foreach ($dependencies as $dependency) {
      // TODO add a warning if the same script is loaded several times (with different names) in multiple components
      self::addAsset('register', $dependency);
    }

    // collect script dependencies
    $scriptDeps = array_reduce($dependencies, function ($list, $dependency) {
      if ($dependency['type'] === 'script') {
        array_push($list, $dependency['name']);
      }
      return $list;
    }, ['jquery']); // jquery as a default dependency

    // Enqueue Component Scripts if they exist
    $scriptAbsPath = Utils::requireAssetPath("Components/{$componentName}/script.js");
    if (is_file($scriptAbsPath)) {
      self::addAsset('enqueue', [
        'type' => 'script',
        'name' => "Flynt/Components/{$componentName}",
        'path' => "Components/{$componentName}/script.js",
        'dependencies' => $scriptDeps
      ]);
    }

    // collect style dependencies
    $styleDeps = array_reduce($dependencies, function ($list, $dependency) {
      if ($dependency['type'] === 'style') {
        array_push($list, $dependency['name']);
      }
      return $list;
    }, []);

    // Enqueue Component Styles if they exist
    $styleAbsPath = Utils::requireAssetPath("Components/{$componentName}/style.css");
    if (is_file($styleAbsPath)) {
      self::addAsset('enqueue', [
        'type' => 'style',
        'name' => "Flynt/Components/{$componentName}",
        'path' => "Components/{$componentName}/style.css",
        'dependencies' => $styleDeps
      ]);
    }
  }

  public static function addAsset($funcType, $options) {
    if (!in_array($funcType, ['enqueue', 'register'])) {
      trigger_error('Cannot add asset: Invalid Parameter for funcType (' . $funcType . ')', E_USER_WARNING);
      return false;
    }

    // TODO add cdn functionality
    $options = array_merge(self::DEFAULT_OPTIONS, $options);

    if (!array_key_exists('name', $options)) {
      trigger_error('Cannot add asset: Name not provided!', E_USER_WARNING);
      return false;
    }

    if (!array_key_exists('path', $options)) {
      trigger_error('Cannot add asset: Path not provided!', E_USER_WARNING);
      return false;
    }

    $funcName = "wp_{$funcType}_{$options['type']}";
    $lastVar = $options['type'] === 'script' ? $options['inFooter'] : $options['media'];

    if (function_exists($funcName)) {
      $funcName(
        $options['name'],
        Utils::requireAssetUrl($options['path']),
        $options['dependencies'],
        $options['version'],
        $lastVar
      );

      return true;
    }

    return false;
  }
}