<?php
/*
 * 3 modes
 *  mode==0 (or unset) -- user enters initial request
 *  mode==1 -- user requested a re-read, displaying mktable syntax
 *  mode==2 -- displaying SQL syntax for confirmation
 *  mode==3 -- displaying results in comment-form
 */

$sql = $defs = '';
if (Input::exists() && !empty(Input::get('mksql'))) {
    $fldTypeMap = [
        'var' => 'v',
        'varchar' => 'v',
        'v' => 'v',
        'int' => 'i',
        'integer' => 'i',
        'i' => 'i',
        'char' => 'c',
        'c' => 'c',
        'tinyint' => 'b',
        'bool' => 'b',
        'boolean' => 'b',
        'b' => 'b',
        'float' => 'f',
        'f' => 'f',
        'text' => 'text',
        't' => 't',
        'time' => 'time',
        'date' => 'd',
        'd' => 'd',
        'ts' => 'ts',
        'timestamp' => 'ts',
        'dt' => 'dt',
        'datetime' => 'dt',
    ];
    $dfltSize = [
        'i' => 11,
        'v' => 255,
        'c' => 10,
        'b' => 1,
        'f' => 11,
        't' => 0,
        'd' => 0,
        'text' => 0,
        'dt' => 0,
        'ts' => 0,
    ];
    $typeName = [
        'i' => 'int',
        'v' => 'varchar',
        'c' => 'char',
        'b' => 'tinyint',
        'f' => 'float',
        'text' => 'text',
        't' => 'time',
        'd' => 'date',
        'dt' => 'datetime',
        'ts' => 'timestamp',
    ];
    $engine = "MyISAM";
    $charset = "utf8";
    $collate = "utf8_bin";
    $dfltType = 'v';
    $tbls = preg_split("/\R{2,}/", Input::get('defs'));
    $tblPat = "/^\s*
                (?'comment'\#)?\s*
                (?'table_action'!!|!|\+|-)?\s*
                (?'table_name'[\S]+)\s*
               $/x";
    $fldPat = "/^\s*
                (?'comment'\#)?\s*
                (?'field_action'[-!+])?\s* # leading ! or - or +
                (?'field_name'[\w]+)       # field name
                (?:\s+(?:(?'field_type'(?:v|varchar|c|char|i|int|integer|t|text|b|bool|boolean|tiny|tinyint|d|date|dt|datetime|time|timestamp|ts))(?:\s*\(?(?'field_size'\d+)\)?)?))?
                (?:\s+(?'field_props'(?:\*|!!?|nn|(?:not\s+)?null|default\s*(?:=\s*)?(?:\"[^\"]*\"|'[^']*'|[\S]+)|ai|auto_?inc(?:rement)?|[\s,]*)*))?
                (?:\s*,?\s*)?  # any closing whitespace or comma for aesthetics
               $/xi";
    foreach ($tbls as $tbl) {
        $fields = preg_split("/\R/", $tbl);
        #echo "DEBUG: tblDef=$tblDef<br />\n";
        $m = null;
        do {
            $tblDef = array_shift($fields);
            #print_r("DEBUG: tblDef=$tblDef<br />\n");
        } while ($tblDef && !preg_match($tblPat, $tblDef, $m) && preg_match('/^\s*#/', $tblDef));
        if ($tblDef && $m) {
            $tblName = $m['table_name'];
            $tblAction = $m['table_action']; // `!!`=reread, `!`=replace, `-`=drop, `+`=create
        } else {
            $errors[] = "ERROR: Unrecognized table definition line: `$tblDef`";
            continue; // go on to the next table definition
        }
        if ($m['comment'] == '#') { // table was commented out
            $successes[] = "(Commented-out table `$tblName` ignored)";
            continue;
        }
        #echo "tblName=$tblName<br />\n";
        if ($tblAction == '!!') { // re-read table
            $mode = 1;
            $display_text = "This would have been a re-read";
        } if ($tblAction == '-') { // delete table
            $sql .= "DROP TABLE `$tblName`;\n\n";
            continue;
        } elseif ($tblAction == '!') { // alter table definition
            $sql .= "ALTER TABLE `$tblName` ";
        } elseif ($tblAction == '!!') { // re-read table definitin
            $tblPattern = str_replace(['*', '?'], ['%', '_'], $tblName);
            $db->query("SHOW TABLES LIKE '$tblPattern'");
            $rereadText = '';
            foreach ($db->results(true) as $t) {
                $tblName = array_pop($t);
                #pre_r($tblName);
                $rereadSQL = "SHOW COLUMNS FROM `$tblName`";
                $db->query($rereadSQL);
                if ($db->errors()) {
                    $errors[] = $db->errorString();
                } else {
                    $rereadText .= "# Following is the schema for `$tblName`\n# If you wish to make any changes, uncomment the line below (with the table name)\n# as well as any other lines (columns) you wish to modify. Columns preceded by\n#   `-` (minus) will be DROPped\n#   `+` (plus) will be ADDed\n#   `!` (exclamation) will be MODIFY'd\n#   `#` (hash) will be ignored (they are commented out)\n#! ".$tblName;
                    foreach ($db->results() as $x) {
                        #pre_r($x);
                        $rereadText .= "\n#! ".$x->Field." ".$x->Type;
                        if ($x->Null == 'NO') {
                            $rereadText .= " NOT NULL";
                        }
                        if ($x->Key == 'PRI') {
                            $rereadText .= " !!";
                        } elseif ($x->Key == 'UNI') {
                            $rereadText .= " !";
                        } elseif ($x->Key == 'MUL') {
                            $rereadText .= " *";
                        }
                        if ($x->Extra) {
                            $rereadText .= " ".strtoupper($x->Extra); // usually AUTO_INCREMENT
                        }
                        if ($x->Default) {
                            $rereadText .= " DEFAULT ".strtoupper($x->Default);
                        }
                    }
                    $rereadText .= "\n\n";
                    $successes[] = "SUCCESS: definition for table `$tblName` was read in";
                    $sql .= "-- SQL code for $tblName (just read in from !!) will be generated when you click the `Generate SQL ...` button above\n\n";
                }
            }
            if (!$rereadText) {
                $errors[] = "ERROR: No tables match `$tblName`";
            }
            # I'd like to implement comments, but not yet...
            #$tbl = '#'.$tbl."\n".$rereadText;
            $tbl = $rereadText;
        } else { // must be create table
            $sql .= "CREATE TABLE `$tblName` (";
        }
        if ($tblAction != '-' && $tblAction != '!!') {
            if ($tblAction != '!') {
                $flds[$tblName] = [ 'id' => [ 'action' => '+', 'type' => 'i', 'size' => 11, 'index' => 'PRIMARY KEY', 'autoincrement' => true, 'null' => 'NOT NULL' ]];
            } else {
                $flds[$tblName] = [];
            }
            foreach ($fields as $f) {
                #echo "f: $f<br />\n";
                #if (!preg_match('/^(?:\s*([+*])\s*)?([_a-zA-Z0-9]+)\s*(?:(i(?:nt(?:eger)?)?|v(?:ar(?:char)?)?|c(?:har)?|b(?:ool(?:ean)?)?|f(?:loat)?|t(?:ext)?)(?:\((\d+)\))?)?(NN|(?:NOT\s*)NULL)?(?:DEFAULT\s+(.*))?$/i', $f, $m)) {
                if (!preg_match($fldPat, $f, $m)) {
                    $errors[] = "ERROR - badly formed line `$f` - not a valid field definition<br />\n";
                    continue;
                }
                if ($m['comment'] == '#') { // field is commented out - ignore it
                    continue;
                }
                $fldAction = $m['field_action'];
                $fldName = $m['field_name'];
                $fldProps = [];
                #pre_r($m);
                if (!empty($m['field_props'])) {
                    $fldPropPats = [
                        'index' => '/^\s*(\\*|!!?)[,\s]*/',
                        'autoincrement' => '/^\s*(ai|auto_?inc(?:rement)?)[,\s]*/i',
                        'null' => '/^\s*(nn|(?:not\s+)?null)[,\s]*/i',
                        'default' => '/^\s*default\s*(?:=\s*)?("[^"]*"|\'[^\']*\'|[\S]+)[,\s]*/i',
                    ];
                    while (!empty($m['field_props'])) {
                        $matched = false;
                        foreach ($fldPropPats as $n => $pat) {
                            #echo "DEBUG: n=$n<br />\n";
                            if (preg_match($pat, $m['field_props'], $m1)) {
                                $fldProps[$n] = $m1[1];
                                #echo "DEBUG: n=$n, fldProps=<pre>".print_r($fldProps,true)."</pre><br />\n";
                                $m['field_props'] = substr($m['field_props'], strlen($m1[0]));
                                $matched = true;
                                break;
                            } 
                            
                        }
                        if (!$matched) {
                            $errors[] = "Field properties invalid for `$tblName.$fldName` ($m[field_props])<br />\n";
                            break;
                        }
                    }
                }
                if (!empty($fldProps['index'])) {
                    $fldIndex = ($fldProps['index'] == '!!' ? 'PRIMARY ' : ($fldProps['index'] == '!' ? 'UNIQUE ' : '')) . 'KEY';
                } else {
                    $fldIndex = '';
                }
                #echo "fldName=$fldName<br />\n";
                if (!empty($m['field_type'])) {
                    if (isset($fldTypeMap[$m['field_type']])) {
                        $fldType = $fldTypeMap[$m['field_type']];
                    } else {
                        $errors[] = "Invalid type=$m[field_type] for `$tblName.$fldName`<br />\n";
                        break;
                    }
                } else {
                    $fldType = $dfltType;
                }
                if (@$m['field_size']) {
                    $fldSize = $m['field_size'];
                } else {
                    $fldSize = $dfltSize[$fldType];
                }
                #echo "null=$fldProps[null]<br />\n";
                if (!empty($fldProps['null']) && preg_match('/nn|not\s+null/i', $fldProps['null'])) {
                    $fldNull = 'NOT NULL';
                } elseif (!empty($fldProps['null']) && preg_match('/null/i', $fldProps['null'])) {
                    $fldNull = 'NULL';
                } else {
                    $fldNull = ''; // if they didn't specify just leave it with db engine defaults (which is NULL, but leaving it unspecified is more intuitive for a user)
                }
                if (!empty($fldProps['default'])) {
                    if (strtolower($fldProps['default']) == 'now') {
                        $fldProps['default'] = 'CURRENT_TIMESTAMP';
                    }
                    $fldDefault = 'DEFAULT '.$fldProps['default'];
                } else {
                    $fldDefault = '';
                }
                $fldAutoIncrement = empty($fldProps['autoincrement']) ? "" : $fldProps['autoincrement'];
                $flds[$tblName][$fldName] = [ 'action' => $fldAction, 'type' => $fldType, 'size' => $fldSize, 'index' => $fldIndex, 'null' => $fldNull, 'default' => $fldDefault, 'autoincrement' => $fldAutoIncrement ];
                #pre_r($flds);
            }
            #$sql .= "CREATE TABLE `{$tblName}` (";
            $sep = "\n";
            foreach ($flds[$tblName] as $fldName => $props) {
                $sql .= "$sep   ".mkFieldDef($fldName, $props, $tblAction == '!');
                $sep = ",\n";
            }

            if ($tblAction == '!') { // alter table definition
                $sql .= ';';
            } else {
                $sql .= "\n)\nENGINE=$engine DEFAULT CHARSET=$charset COLLATE=$collate;\n\n";
            }

            // Now handle indices
            /*
            $sql .= "ALTER TABLE `{$tblName}`";
            $sep = "\n";
            foreach ($flds[$tblName] as $fldName => $props) {
                if (!empty($props['index'])) {
                    if ($props['index'] == 'index') {
                        $idxType = '';
                    } else {
                        $idxType = strtoupper($props['index']); // primary or unique
                    }
                    if ($props['index'] == 'primary') {
                        $idxName = '';
                    } else {
                        $idxName = "`$fldName`"; // unique or index
                    }
                    $sql .= "$sep   ADD $idxType KEY $idxName (`${fldName}`)";
                    $sep = ",\n";
                }
            }
            $sql .= ";\n\n";
            $sql .= "ALTER TABLE `{$tblName}`";
            $sep = "\n";
            foreach ($flds[$tblName] as $fldName => $props) {
                if (!empty($props['autoincrement'])) {
                    $sql .= "$sep   ".mkFieldDef($fldName, $props);
                    $sep = ",\n";
                }
            }
            $sql .= ";\n\n";
            */
        }
        $defs .= $tbl . "\n\n";
    }
    #pre_r($flds);
} elseif (Input::exists() && !empty(Input::get('runsql'))) {
    $sql = Input::get('sql');
    #echo "sql=$sql<br />\n";
    $db->query($sql);
    if ($db->errors()) {
        $errors[] = $db->errorString();
    } else {
        $successes[] = "SUCCESS! SQL executed successfully";
    }
}

$shortcutHelp = "# Place shortcut syntax to modify database schema here.\n\n# Lines preceded by hash (#) are comments, blank line starts a new table, first non-commented line is always the table, a commented table name (or a comment that can be interpreted that way!) causes all column definitions within that 'chunk' (table definition) to be ignored\n\n# Example to create table `foo` with 3 columns (`id` column is always assumed with appropriate definition)\n# including a unique index (`!`) on column a and a non-unique index (`*`) on column b:\nfoo\na varchar(50) !\nb int *\nc boolean\n\n# Identical example but even more briefly:\nfoo\na v50 !\nb i *\nc b\n\n# Example to change field b, drop field c, and add field d (other columns in table `foo` are unaffected, `+` preceding ADDed columns is optional):\n!foo\n!b char(10)\n-c\n+d int(10)\n\n# Example to DROP table `foo`:\n-foo\n\n# Example to read in the existing schema of a table `foo`:\n!!foo\n\n# Example to read in the existing schema of all tables starting with `fo` (*, ?, %, and _ function as expected for wildcards/LIKE functionality):\n!!fo*";
$sqlHelp = "Normally SQL code gets here by being generated from the above shortcut-syntax.\n\nSQL code here will be executed with no additional checks when you click the button below.\n\nBE CAREFUL!";
$myForm = new Form([
    'defs' => new FormField_TextArea([
        'display' => 'Table Definition(s)',
        'hint_text' => $shortcutHelp,
        #'hint_text' => 'Table name on first line, each new line is a column, type=int,bool,varchar(x),char,etc (most reasonable non-ambiguous abbreviations work), ! (unique) or * (non-unique) after type represents an index on that column; blank line to start new table',
        'value' => ($defs ? : (Input::get('defs') ? : '')),
        'rows' => 20,
        'placeholder' => $shortcutHelp,
        #'Tinymce_Content_Style' => 'Courier New',
        'plaintext' => true,
    ]),
    'mksql' => new FormField_ButtonSubmit([
        'display' => 'Process Shortcut Syntax',
    ]),
    'sql' => new FormField_TextArea([
        'display' => 'SQL Code',
        'hint_text' => $sqlHelp,
        'value' => ($sql ? : (Input::get('sql') ? : '')),
        'plaintext' => true,
        'placeholder' => $sqlHelp,
    ]),
    'runsql' => new FormField_ButtonSubmit([
        'display' => 'Execute SQL Code',
    ]),
], [
    'autoshow' => true,
]);

function mkFieldDef($fldName, $props, $exists=false) {
    global $typeName, $collate;
    #echo "DEBUG: mkFieldDef($fldName):<br />\n";
    #pre_r($props);
    if ($exists && $props['action'] == '-') {
        $sql = "DROP COLUMN `${fldName}`";
    } else {
        if ($exists && $props['action'] == '!') {
            $sql = "MODIFY COLUMN ";
        } elseif ($exists) {
            $sql = "ADD COLUMN ";
        } else {
            $sql = "";
        }
        $sql .= "`${fldName}` ".$typeName[$props['type']];
        if ($props['size'] > 0) { // type "text", "date", etc doesn't specify size
            $sql .= "($props[size])";
        }
        if (!empty($props['index'])) {
            $sql .= ' '.$props['index'];
        }
        if (in_array($props['type'], ['v', 'c', 't'])) {
            $sql .= " COLLATE $collate";
        }
        if (!empty($props['null'])) {
            $sql .= " ".$props['null'];
        }
        if (!empty($props['default'])) {
            $sql .= " $props[default]";
        }
        if (!empty($props['autoincrement'])) {
            $sql .= " AUTO_INCREMENT";
        }
    }
    return $sql;
}
