<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit1d748d33dcb87897c6d2d890673c7280
{
    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'StudyChurch\\' => 12,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'StudyChurch\\' => 
        array (
            0 => __DIR__ . '/../..' . '/inc',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit1d748d33dcb87897c6d2d890673c7280::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit1d748d33dcb87897c6d2d890673c7280::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}