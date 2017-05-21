<?php
require_once(pathFinder('helpers/validfuncs.php'));
/*
Set longer execution time and larger memory limit to deal with large backup sets
*/
ini_set('max_execution_time', 600); // 600 seconds=10 minutes
ini_set('memory_limit','1024M');

checkToken();

$destPath = (Input::get('backup_dest') ? : configGet('backup_dest', US_ROOT_DIR.'backup'));
if (substr($destPath, -1) != '/') {
    $destPath .= '/';
}
if (!pathIsAbsolute($destPath)) {
    if (substr(US_ROOT_DIR, -1) != '/') {
        $sep = '/';
    } else {
        $sep = '';
    }
    $destPath = US_ROOT_DIR.$sep.$destPath;
}

/*
 * Get array of existing backup files
 */
$fileList=glob($destPath.'backup*.zip');
$allBackupFiles = [];
foreach($fileList as $file) {
	$allBackupFiles[] = [ 'filename' => $file, 'filesize' => filesize($file) ];
}

/*
 * Determine which row in settings we will use as we edit data below
 */
if (!Input::get('id')) {
    $settings_row = $db->query("SELECT id FROM $T[settings] WHERE (user_id IS NULL OR user_id <= 0) AND (group_id IS NULL OR group_id <= 0)")->first();
    $db->errorSetMessage($errors);
    $_REQUEST['id'] = $_GET['id'] = $settings_row->id;
}
$myForm = new Form([
    'do_the_backup' => new Form_Panel([
        'backup_database' =>
            new FormField_Select([
                'isdbfield' => false,
                'display' => lang('ADMIN_BACKUP_DB_BACKUP'),
                'data' => [
                    ['id'=>'none', 'name'=>lang('ADMIN_BACKUP_DB_NONE')],
                    ['id'=>'non_us', 'name'=>lang('ADMIN_BACKUP_DB_NON_US')],
                    ['id'=>'only_us', 'name'=>lang('ADMIN_BACKUP_DB_US')],
                    ['id'=>'all', 'name'=>lang('ADMIN_BACKUP_DB_ALL')],
                ],
                'value' =>  @$_POST['backup_database'] ? : 'all',
                'hint_text' => lang('ADMIN_BACKUP_DB_BACKUP_HINT'),
            ]),
        'backup_files' =>
            new FormField_Select([
                'isdbfield' => false,
                'display' => lang('ADMIN_BACKUP_FILE_BACKUP'),
                'data' => [
                    ['id'=>'none', 'name'=>lang('ADMIN_BACKUP_FILE_NONE')],
                    ['id'=>'non_us', 'name'=>lang('ADMIN_BACKUP_FILE_NON_US')],
                    ['id'=>'only_us', 'name'=>lang('ADMIN_BACKUP_FILE_US')],
                    ['id'=>'all', 'name'=>lang('ADMIN_BACKUP_FILE_ALL')],
                ],
                'value' => @$_POST['backup_files'] ? : 'all',
                'hint_text' => lang('ADMIN_BACKUP_FILE_BACKUP_HINT'),
            ]),
        'do_backup' => new FormField_ButtonSubmit([
                'display' => lang('ADMIN_BACKUP_BACKUP'),
            ]),
    ], [
        'Head' => lang('ADMIN_BACKUP_WHAT_TO_BACKUP'),
    ]),
    'backup_settings' => new Form_Panel([
        'backup_dest' => new FormField_Text([
            'display' => lang('ADMIN_BACKUP_DEST'),
            'hint_text' => lang('ADMIN_BACKUP_DEST_HINT'),
        ]),
        'backup_include_dirs' => new FormField_Text([
            'display' => lang('ADMIN_BACKUP_INCLUDE_DIRS'),
            'hint_text' => lang('ADMIN_BACKUP_INCLUDE_DIRS_HINT'),
        ]),
        'backup_exclude_dirs' => new FormField_Text([
            'display' => lang('ADMIN_BACKUP_EXCLUDE_DIRS'),
            'hint_text' => lang('ADMIN_BACKUP_EXCLUDE_DIRS_HINT'),
        ]),
        'backup_exclude_ext' => new FormField_Text([
            'display' => lang('ADMIN_BACKUP_EXCLUDE_EXT'),
            'hint_text' => lang('ADMIN_BACKUP_EXCLUDE_EXT_HINT'),
        ]),
        'backup_exclude_regex' => new FormField_Text([
            'display' => lang('ADMIN_BACKUP_EXCLUDE_REGEX'),
            'hint_text' => lang('ADMIN_BACKUP_EXCLUDE_REGEX_HINT'),
        ]),
        'backup_include_tables' => new FormField_Text([
            'display' => lang('ADMIN_BACKUP_INCLUDE_TABLES'),
            'hint_text' => lang('ADMIN_BACKUP_INCLUDE_TABLES'),
        ]),
        'save' => new FormField_ButtonSubmit([
            'display' => lang('SAVE'),
        ]),
    ], [
        'head' => lang('ADMIN_BACKUP_SETTINGS_HEAD'),
        'foot' => lang('ADMIN_BACKUP_SETTINGS_FOOT')
    ]),
    'existing_backups_panel' => new Form_Panel([
        'existing_backups_table' => new FormField_Table([
            'isdbfield' => false,
            'th_row' => [
                lang('ADMIN_BACKUP_FILENAME'),
                lang('ADMIN_BACKUP_FILESIZE'),
            ],
            'td_row' => [
                '{filename}',
                '{filesize}',
            ],
            'data' => $allBackupFiles,
        ]),
    ], [
        'head' => lang('ADMIN_BACKUP_EXISTING_BACKUPS', (Input::get('backup_dest') ? : configGet('backup_dest'))),
    ])
], [
    'dbtable' => 'settings',
    'default' => 'process',
    'autoshow' => empty(Input::get('do_backup')), // we will give progress messages and then redirect if actually backing up...
    'validfunc' => 'DirExists', // see helpers/validfuncs.php
    'validfuncargs' => [
        'fieldname' => 'backup_dest', // the pathname is stored in this field
        'relative_prefix' => US_ROOT_DIR, // if they don't specify an absolute path, prepend the US_ROOT_DIR
        'mkdir' => true, // try to create the directory if it doesn't exist
        'mkdir_recursive' => true, // can create intervening directories if needed
    ],
    'Keep_AdminDashboard' => true,
]);

/*
 * If the user asked us to do the backup, validate and go ahead
 */
if (!$errors && !empty(Input::get('do_backup'))) {
	/*
	 * Create backup destination folder: configGet('backup_dest')
	 */
	if (!file_exists($destPath)) {
		if (mkdir($destPath)) {
			$destPathSuccess = true;
			$successes[] = lang('ADMIN_BACKUP_DEST_PATH_CREATED');
		} else {
			$destPathSuccess = false;
			$errors[] = lang('ADMIN_BACKUP_DEST_PATH_NOT_CREATED');
		}
	}

	/*
	 * Generate backup pathname
	 */
	$backupPath  = $destPath.'backup_'
    	. date("Y-m-d\TH-i-s")
        . '_Files_'.Input::get('backup_files')
        . '_Database_'.Input::get('backup_database')
    	. '.zip';

	if (file_exists($backupPath)) {
        $errors[] = lang('ADMIN_BACKUP_FILE_EXISTS', $backupPath);
        $backupPath = null; // indicates error condition and prevents overwrite
	}

    $backupFiles = Input::get('backup_files');
    $backupItems = [];
	if (!$errors && $backupPath && $backupFiles != 'none') {
        # Calculate which directories we start with
        $includeDirs = [];
        $includeFiles = [];
        if ($backupFiles == 'us' || $backupFiles == 'all') {
            $includeDirs[] = US_ROOT_DIR;
        }
        if ($backupFiles == 'non_us' || $backupFiles == 'all') {
            $backupIncludeDirs = (Input::get('backup_include_dirs') ? : configGet('backup_include_dirs'));
            if (!is_array($backupIncludeDirs)) {
                $backupIncludeDirs = explode(';', $backupIncludeDirs);
            }
            $includeDirs = array_merge($includeDirs, $backupIncludeDirs);
        }

        # Calculate all files within these directories
        foreach ($includeDirs as $path) {
            if (!file_exists($path)) {
                $errors[] = lang('ADMIN_BACKUP_INCLUDE_DIR_NOT_EXIST', $path);
                continue;
            }
            $path = realpath($path); // got rid of this - backslashes get eaten and that doesn't work in windows
            if (!$path) continue;
            $pathnames = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
            foreach($pathnames as $pathname) {
                $allFilesFolders[] = $pathname->getPathName();
            }
        }

		/*
		 * Build a list of all types of exclusions in $exclusions
		 */
        $exclusions = [
            '/^'.path2regex($destPath).'/i', # never back up anything within the backup destination directory itself
            '/'.path2regex('/').'\.{1,2}$/', # never back up the . or .. directories
        ];
        if ($backupFiles == 'non_us') {
            $exclusions[] = '/^'.path2regex(US_ROOT_DIR).'/i'; // exclude all files under US_ROOT_DIR
        }
        if ($exclDir = (Input::get('backup_exclude_dirs') ? : configGet('backup_exclude_dirs'))) {
            if (!is_array($exclDir)) {
                $exclDir = explode(";", $exclDir);
            }
            foreach ($exclDir as $dir) {
                $dir = trim($dir);
                if (!pathIsAbsolute($dir)) {
                    $errors[] = lang('ADMIN_BACKUP_EXCL_DIR_NOT_ABS', $dir);
                    break;
                }
                $exclusions[] = '/^'.path2regex($dir).'/i'; # anchored to the front
            }
        }
        if ($exclExt = (Input::get('backup_exclude_ext') ? : configGet('backup_exclude_ext'))) {
            if (!is_array($exclExt)) {
                $exclExt = explode(";", $exclExt);
            }
            $re = $sep = '';
            foreach ($exclExt as $ext) {
                $re .= $sep.preg_quote(trim($ext), '/');
                $sep = '|';
            }
            $exclusions[] = '/(?:'.$re.')$/i'; // anchored to the end of the string as an extension should be
        }
        if ($exclRegex = (Input::get('backup_exclude_regex') ? : configGet('backup_exclude_regex'))) {
            if (!is_array($exclRegex)) {
                $exclRegex = explode(";", $exclRegex);
            }
            foreach ($exclRegex as $regex) {
                $exclusions[] = $regex;
            }
        }

        /*
         * Now actually exclude the files based on the regular expressions in $exclusions
         */
		$backupItems=$allFilesFolders;
		foreach ($exclusions as $re) {
            $backupItems = preg_grep($re, $backupItems, PREG_GREP_INVERT);
		}
    }

    $backupDatabase = Input::get('backup_database');
    $dbBackup = null; // indicates no tables backed up - this will be set below if used
	if (!$errors && $backupPath && $backupDatabase != 'none') {
        $tableList = [];
        if ($backupDatabase == 'us' || $backupDatabase == 'all') {
            $tableList = array_merge($tableList, $us_tables);
        }
        if ($backupDatabase == 'non_us' || $backupDatabase == 'all') {
            if ($nonUSTables = configGet('backup_include_tables')) {
                if (!is_array($nonUSTables)) {
                    $nonUSTables = explode(';', $nonUSTables);
                }
                $tableList = array_merge($tableList, $nonUSTables);
            } else {
                $errors[] = lang('ADMIN_BACKUP_NO_NON_US_TABLES');
            }
        }
        foreach ($tableList as &$t) {
            $t = trim($t); // trim in case they put spaces
            if (isset($T[$t])) {
                $t = $T[$t]; // apply prefixes (or any other renaming)
            }
        }
        if ($tableList) {
    		$userspiceDump = Shuttle_Dumper::create([
                'host' => configGet('mysql/host'),
                'username' => configGet('mysql/username'),
                'password' => configGet('mysql/password'),
                'db_name' => configGet('mysql/db'),
                'include_tables' => $tableList,
            ]);
    		$dbBackup = $destPath.'db.sql';
    		$userspiceDump->dump($dbBackup);
    		$successes[] = lang('ADMIN_BACKUP_TABLES_BACKED_UP', sizeof($tableList));
        }
    }

    # Zip everything up into a single compressed archive
    if (!$errors && $backupPath && ($backupItems || $dbBackup)) {
    	if (extension_loaded('zip')) {
    		$zip = new ZipArchive();
    		if ($zip->open($backupPath, ZIPARCHIVE::CREATE)) {
                $strip_prefix = str_replace('\\', '/', US_DOC_ROOT);
                if (substr($strip_prefix, -1) != '/') {
                    $strip_prefix .= '/';
                }
                if ($dbBackup) {
					$zip->addEmptyDir('sql');
                    $zip->addFile($dbBackup, 'sql/db.sql');
                }
                foreach ($backupItems as $file) {
                    $zipName = str_replace(['\\', $strip_prefix], ['/', ''], $file);
                    $file = str_replace('\\', '/', $file);
                    #dbg("Backing up $file as $zipName");
    				if (is_dir($file)) {
    					$zip->addEmptyDir($zipName);
    				} elseif (is_file($file)) {
                        dbg("Backing up $file");
    					if ($zip->addFile($file, $zipName)) {
                            dbg("SUCCESS");
                        } else {
                            dbg("FAILURE");
                        }
                        #$zip->addFromString($zipName, "abc");
    				} else {
                        $errors[] = "ERROR: Unknown backup entity - neither file nor dir - `$file`";
                    }
                }
        		if ($zip->close()) {
        			$successes[] = lang('ADMIN_BACKUP_FILE_SUCCESS', [$backupPath, sizeof($backupItems)+($dbBackup?1:0)]);
                } else {
                    $errors[] = lang("ADMIN_BACKUP_NO_CLOSE_ZIP", $backupPath);
                }
            } else {
                $errors[] = lang("ADMIN_BACKUP_NO_OPEN_ZIP", $backupPath);
            }
        } else {
            $errors[] = lang("ADMIN_BACKUP_NO_ZIP_EXTENSION");
        }
    } elseif (!$backupItems && !$dbBackup) {
        $errors[] = lang("ADMIN_BACKUP_NOTHING_TO_BACKUP");
    }
    # We explicitly did NOT `autoshow` the form above, so now we need to display
    echo $myForm->getHTML();
} elseif ($errors && !empty(Input::get('do_backup'))) {
    # Since no `autoshow` occurred above we need to redirect to show the errors
    Redirect::to('admin_backup.php');
} // else - presumably we already did the `autoshow` above so we're done

/*
		if (backupUsTables($us_tables,$backupPath.'sql/')) {
			$successes[]='SQL dumps were successful.';
		} else {
			$errors[]='SQL dumps failed.';
		}
		$targetZipFile=backupZip($backupPath,true);
		if ($targetZipFile) {
			$successes[]='DB and Files Zipped';
			$backupZipHash=hash_file('sha1', $targetZipFile);
			$backupZipHashFilename=substr($targetZipFile,0,strlen($targetZipFile)-4).'_SHA1_'.$backupZipHash.'.zip';
			if (rename($targetZipFile,$backupZipHashFilename)) {
				$successes[]='File SHA1 hashed and renamed to: '.$backupZipHashFilename;
			} else {
				$errors[]='Could not rename backup zip file to contain hash value.';
			}
		} else {
			$errors[]='Error creating zip file';
		}

	} elseif ($backupPath && Input::get('backup_source') == 'db_us_files') {
		$backupItems=[];
		$backupItems[]=US_DOC_ROOT.US_URL_ROOT.'users';
		$backupItems[]=US_DOC_ROOT.US_URL_ROOT.'usersc';

		if (backupObjects($backupItems,$backupPath.'files/')) {
			$successes[]='Backup was successful.';
		} else {
			$errors[]='Backup failed.';
		}
		if (backupUsTables($us_tables,$backupPath.'sql/')) {
			$successes[]='SQL dumps were successful.';
		} else {
			$errors[]='SQL dumps failed.';
		}
		$targetZipFile=backupZip($backupPath,true);
		if ($targetZipFile) {
			$successes[]='DB and US Files Zipped';
			$backupZipHash=hash_file('sha1', $targetZipFile);
			$backupZipHashFilename=substr($targetZipFile,0,strlen($targetZipFile)-4).'_SHA1_'.$backupZipHash.'.zip';
			if (rename($targetZipFile,$backupZipHashFilename)) {
				$successes[]='File SHA1 hashed and renamed to: '.$backupZipHashFilename;
			} else {
				$errors[]='Could not rename backup zip file to contain hash value.';
			}
		} else {
			$errors[]='Error creating zip file';
		}
	} elseif ($backupPath && Input::get('backup_source') == 'db_only') {
		if (backupUsTables($us_tables,$backupPath.'sql/')) {
			$successes[]='SQL dumps were successful.';
		} else {
			$errors[]='SQL dumps failed.';
		}
		$targetZipFile=backupZip($backupPath,true);
		if ($targetZipFile) {
			$successes[]='DB and US Files Zipped';
			$backupZipHash=hash_file('sha1', $targetZipFile);
			$backupZipHashFilename=substr($targetZipFile,0,strlen($targetZipFile)-4).'_SHA1_'.$backupZipHash.'.zip';
			if (rename($targetZipFile,$backupZipHashFilename)) {
				$successes[]='File SHA1 hashed and renamed to: '.$backupZipHashFilename;
			} else {
				$errors[]='Could not rename backup zip file to contain hash value.';
			}
		} else {
			$errors[]='Error creating zip file';
		}
	} elseif (!$backupPath) {
		$errors[]='Backup path already exists or could not be created.';
	}
    */
