<?php

/*
 * DirExists()
 * Return true if the given directory exists; false if not
 * ARGUMENTS:
 *  $fieldList - array of fields (unused)
 *  $data - array of data (we are only interested in the array element identified
 *          with the key $args['fieldname'], if specified, as that tells us the
 *          path we are validating)
 *  $args - an array with various (optional) arguments, with the keys as follows:
 *      'path' - the path can be specified absolutely
 *      'fieldname' - this is the key into the $data array to find the path we are validating
 *      'mkdir' - whether we can attempt to mkdir($path) if the path doesn't exist
 *      'mkdir_mode' - the mode to use when calling mkdir(..., $mode, ...) (if mkdir enabled)
 *      'mkdir_recursive' - the value to use for $recursive in mkdir(..., $recursive, ...) (if mkdir enabled)
 *      'prefix' - a prefix which will be prepended onto $path
 *      'relative_prefix' - a prefix which will be prepended onto $path if $path is not absolute already
 *  $errors - an array into which we can place any errors which occur in order to inform the user
 */
function DirExists(&$fieldList, &$data, $args, &$errors) {
    // calculate $path from $args['path'] or $data[$args['fieldname']]
    if (!$path = @$args['path']) {
        if ($fieldName = @$args['fieldname']) {
            if (!$path = @$data[$fieldName]) {
                $errors[] = "DEV ERROR: No path available for DirExists() for fieldName=$fieldName";
                return false;
            }
        } else {
            $errors[] = "DEV ERROR: DirExists() valid_func requires either `path` or `fieldname` to be specified.";
            return false;
        }
    }
    // if a prefix is specified or a relative_prefix is specified and the path is NOT absolute
    // Note that this function is not smart enough to recognize d:foo\bar as a relative path
    // and to insert the prefix in between the "d:" and the "foo\bar" part. Sorry.
    if (($prefix = @$args['prefix']) ||
            (($prefix = @$args['relative_prefix']) && !pathIsAbsolute($path))) {
        if (!in_array(substr($prefix, -1), ['/', '\\']) && !in_array($path[0], ['/', '\\'])) {
            $prefix .= DIRECTORY_SEPARATOR;
        }
        $path = $prefix . $path;
    }
    if (is_dir($path)) {
        return true;
    }
    if (@$args['mkdir']) {
        if (file_exists($path)) {
            $errors[] = lang('VALID_NO_MKDIR_FILE_EXISTS');
            return false;
        }
        $recursive = !@$args['mkdir_recursive'];
        $mode = @$args['mkdir_mode'] or 0777;
        if (!mkdir($path, $mode, $recursive) || !is_dir($path)) {
            $errors[] = lang('VALID_NO_MKDIR_PERMS');
            return false;
        }
        return true; // we only get here if is_dir() was successful
    } else {
        $errors[] = lang('VALID_DIR_NO_EXISTS');
        return false;
    }
}

# Determine if a $path is absolute. Linux is simply a leading slash.
# Windows can be either a leading slash, a leading backslash, or a c:\ or c:/
function pathIsAbsolute($path) {
    return ($path[0] == DIRECTORY_SEPARATOR || $path[0] == '/'
            || (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN'
                && preg_match('~^[A-Z]:[/\\\\]~i', $path)));
}
