<?php
namespace Crossmedia\Fourallportal\Utilities;

class Path
{

    /**
     * utility function to join 2 paths while preventing "double" "//" occurences
     *
     * @param string , string, ... $pathA, pathB, ...
     * @return string
     */
    public static function join()
    {
        $parts = func_get_args();
        foreach ($parts as $key => $part) {
            $parts[$key] = trim($part, '/');
        }

        return str_replace('//', '/', implode('/', $parts));
    }
}
