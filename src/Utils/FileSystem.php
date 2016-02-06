<?php

namespace PixelPolishers\ResolverBuilder\Utils;

class FileSystem
{
    public static function ensureDirectory($path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }
}
