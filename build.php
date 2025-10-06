<?php
/**
 * Generate Cover 插件打包脚本
 * 
 * 使用方法：php build.php
 */

// 设置脚本运行目录
$script_dir = __DIR__;
$plugin_name = 'generate-cover';
$version = '1.1.0';

// 创建构建目录
$build_dir = $script_dir . '/build';
if (!is_dir($build_dir)) {
    mkdir($build_dir, 0755, true);
}

// 创建插件目录（不包含版本号）
$plugin_dir = $build_dir . '/' . $plugin_name;
if (is_dir($plugin_dir)) {
    // 删除已存在的插件目录
    removeDirectory($plugin_dir);
}
mkdir($plugin_dir, 0755, true);

echo "开始打包 Generate Cover v{$version}...\n";

// 需要复制的文件和目录
$files_to_copy = [
    'generate-cover.php',
    'README.md',
    'PACKAGING.md',
    'assets/',
    'includes/',
    '.gitignore'
];

// 复制文件
foreach ($files_to_copy as $file) {
    $source = $script_dir . '/' . $file;
    $destination = $plugin_dir . '/' . $file;
    
    if (is_file($source)) {
        $dest_dir = dirname($destination);
        if (!is_dir($dest_dir)) {
            mkdir($dest_dir, 0755, true);
        }
        copy($source, $destination);
        echo "复制文件: {$file}\n";
    } elseif (is_dir($source)) {
        copyDirectory($source, $destination);
        echo "复制目录: {$file}\n";
    } else {
        echo "警告: 文件或目录不存在: {$file}\n";
    }
}

// 创建ZIP包（包含版本号）
$zip_file = $build_dir . '/' . $plugin_name . '-v' . $version . '.zip';
if (file_exists($zip_file)) {
    unlink($zip_file);
}

echo "创建ZIP包: {$zip_file}\n";
createZipFromDirectory($plugin_dir, $zip_file);

// 显示文件大小
$file_size = filesize($zip_file);
$file_size_mb = round($file_size / 1024 / 1024, 2);
echo "ZIP包大小: {$file_size_mb} MB\n";

echo "打包完成！\n";
echo "发布包位置: {$zip_file}\n";
echo "解压目录: {$plugin_dir}\n";
echo "注意: 解压后插件文件夹名称为 '{$plugin_name}'，不包含版本号\n";

/**
 * 递归复制目录
 */
function copyDirectory($source, $destination) {
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        $dest_path = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        
        if ($item->isDir()) {
            if (!is_dir($dest_path)) {
                mkdir($dest_path, 0755, true);
            }
        } else {
            copy($item, $dest_path);
        }
    }
}

/**
 * 递归删除目录
 */
function removeDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getRealPath());
        } else {
            unlink($item->getRealPath());
        }
    }
    
    rmdir($dir);
}

/**
 * 从目录创建ZIP文件
 */
function createZipFromDirectory($source, $zip_file) {
    $zip = new ZipArchive();
    
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception("无法创建ZIP文件: {$zip_file}");
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        $file_path = $file->getRealPath();
        $relative_path = substr($file_path, strlen($source) + 1);
        
        // 强制使用正斜杠作为路径分隔符（跨平台兼容）
        $relative_path = str_replace('\\', '/', $relative_path);
        
        if ($file->isDir()) {
            $zip->addEmptyDir($relative_path);
        } else {
            $zip->addFile($file_path, $relative_path);
        }
    }
    
    $zip->close();
}
?>
