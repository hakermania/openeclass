<?php

/* vim: set expandtab tabstop=4 shiftwidth=4:
  +----------------------------------------------------------------------+
  | CLAROLINE version 1.3.0 $Revision$                             |
  +----------------------------------------------------------------------+
  | Copyright (c) 2000, 2001 Universite catholique de Louvain (UCL)      |
  +----------------------------------------------------------------------+
  | $Id$  |
  +----------------------------------------------------------------------+
  | This source file is subject to the GENERAL PUBLIC LICENSE,           |
  | available through the world-wide-web at                              |
  | http://www.gnu.org/copyleft/gpl.html                                 |
  +----------------------------------------------------------------------+
  | Authors: Thomas Depraetere <depraetere@ipm.ucl.ac.be>                |
  |          Hugues Peeters    <peeters@ipm.ucl.ac.be>                   |
  |          Christophe Gesch� <gesche@ipm.ucl.ac.be>                    |
  +----------------------------------------------------------------------+
*/

/**
 * Update the file or directory path in the document db document table
 *
 * @author - Hugues Peeters <peeters@ipm.ucl.ac.be>
 * @param  - action (string) - action type require : 'delete' or 'update'
 * @param  - oldPath (string) - old path info stored to change
 * @param  - newPath (string) - new path info to substitute
 * @desc Update the file or directory path in the document db document table
 *
 */

function update_db_info($action, $oldPath, $newPath = "", $isdir = FALSE)
{
	$dbTable = "document";

	/*** DELETE ***/
	if ($action == "delete") {
		mysql_query("DELETE FROM ".$dbTable." 
			WHERE path LIKE \"".$oldPath."%\"");
	}

	/*** UPDATE ***/
	if ($action = "update") {
		$newPath = preg_replace('/\/\//', '/', $newPath);

		mysql_query("UPDATE document
			SET path = '$newPath'
			WHERE path LIKE '$oldPath%'");

/* modified by jexi
			mysql_query("UPDATE document
			SET path = CONCAT('$newPath',
			SUBSTRING(path, LENGTH('$oldPath')+1))
			WHERE path LIKE '$oldPath%'");
*/
		if ($isdir) {
			$res = mysql_query("SELECT path FROM document
				WHERE path LIKE '$oldPath/%'");
			while ($p = mysql_fetch_row($res)) {
				mysql_query("UPDATE document
		                        SET path = CONCAT('$newPath',
		                        SUBSTRING(path, LENGTH('$p[0]')+1))
                		        WHERE path = '$p[0]'");

			}
		}
	}
}

//------------------------------------------------------------------------------

/**
 * Cheks a file or a directory actually exist at this location
 *
 * @author - Hugues Peeters <peeters@ipm.ucl.ac.be>
 * @param  - filePath (string) - path of the presume existing file or dir
 * @return - boolean TRUE if the file or the directory exists
 *           boolean FALSE otherwise.
 */


function check_name_exist($filePath)
{
	clearstatcache();
	if (@chdir (dirname($filePath))) {
	    $fileName = preg_match('!/([^/]+)$!', $filePath, $filename);
    	if (file_exists($filename[1] )) {
    		return true;
	    } else {
		    return false;
	    }
    } else {
        return false; 
    }
}


/**
 * Delete a file or a directory 
 *
 * @author - Hugues Peeters
 * @param  - $file (String) - the path of file or directory to delete
 * @return - bolean - true if the delete succeed 
 *           bolean - false otherwise.
 * @see    - delete() uses check_name_exist() and removeDir() functions
 */

function my_delete($file)
{
	if ( check_name_exist($file) )
	{
		if ( is_file($file) ) // FILE CASE
		{
			unlink($file);
			return true;
		}

		elseif ( is_dir($file) ) // DIRECTORY CASE
		{
			removeDir($file);
			return true;
		}
	}
	else
	{
		return false; // no file or directory to delete
	}
	
}

//------------------------------------------------------------------------------

/**
 * Delete a directory and its whole content
 *
 * @author - Hugues Peeters
 * @param  - $dirPath (String) - the path of the directory to delete
 * @return - no return !
 */


function removeDir($dirPath)
{

	/* Try to remove the directory. If it can not manage to remove it,
	 * it's probable the directory contains some files or other directories,
	 * and that we must first delete them to remove the original directory.
	 */

	if (!@rmdir($dirPath)) // If PHP can not manage to remove the dir...
	{
		chdir($dirPath);
		$handle = opendir($dirPath) ;

		while ($element = readdir($handle) )
		{
			if ( $element == "." || $element == "..")
			{
				continue;	// skip current and parent directories
			}
			elseif ( is_file($element) )
			{
				unlink($element);
			}
			elseif (is_dir ($element) )
			{
				$dirToRemove[] = $dirPath."/".$element;
			}
		}

		closedir ($handle) ;

		if (isset($dirToRemove) and sizeof($dirToRemove) > 0)
		{
			foreach($dirToRemove as $j) removedir($j) ; // recursivity
		}

		rmdir( $dirPath ) ;
	}
}

//------------------------------------------------------------------------------


/**
 * Rename a file or a directory
 * 
 * @author - Hugues Peeters <peeters@ipm.ucl.ac.be>
 * @param  - $filePath (string) - complete path of the file or the directory
 * @param  - $newFileName (string) - new name for the file or the directory
 * @return - boolean - true if succeed
 *         - boolean - false otherwise
 * @see    - rename() uses the check_name_exist() and php2phps() functions
 */

function my_rename($filePath, $newFileName)
{
	$path = @$baseWorkDir.dirname($filePath);
	$oldFileName = basename($filePath);

	if (check_name_exist($path."/".$newFileName)
		&& $newFileName != $oldFileName)
	{
		return false;
	}
	else
	{
		/*** check if the new name has an extension ***/
		if ((!ereg("[^.]+\.[[:alnum:]]+$", $newFileName))
			&& ereg("\.([[:alnum:]]+)$", $oldFileName, $extension))
		{
			$newFileName .= ".".$extension[1];
		}
		
		/*** Prevent file name with php extension ***/
		$newFileName = php2phps($newFileName);

		$newFileName = replace_dangerous_char($newFileName);

		chdir($path);
		rename($oldFileName, $newFileName);

		return true;
	}
}

//------------------------------------------------------------------------------


/**
 * Move a file or a directory to an other area
 *
 * @author - Hugues Peeters <peeters@ipm.ucl.ac.be>
 * @param  - $source (String) - the path of file or directory to move
 * @param  - $target (String) - the path of the new area
 * @return - bolean - true if the move succeed 
 *           bolean - false otherwise.
 * @see    - move() uses check_name_exist() and copyDirTo() functions
 */


function move($source, $target)
{
	if ( check_name_exist($source) )
	{
		$fileName = basename($source);

		if ( check_name_exist($target."/".$fileName) )
		{
			return false; 
		}
		else
		{	/*** File case ***/
			if ( is_file($source) ) 
			{
				copy($source , $target."/".$fileName);
				unlink($source);
				return true;
			}
			/*** Directory case ***/
			elseif (is_dir($source))
			{
				// check to not copy the directory inside itself
				if (ereg("^".$source."*", $target))
				{
					return false;
				}
				else
				{
					copyDirTo($source, $target);
					return true;
				}
			}
		}
	}
	else
	{
		return false;
	}
	
}


//------------------------------------------------------------------------------



/**
 * Move a directory and its content to an other area
 *
 * @author - Hugues Peeters <peeters@ipm.ucl.ac.be>
 * @param  - $origDirPath (String) - the path of the directory to move
 * @param  - $destination (String) - the path of the new directory
 */

function move_dir($src, $dest)
{
	if (file_exists($dest)) {
		if (!is_dir($dest)) {
			die("<br>Error! a file named $dest already exists\n");
		}
	} else {
		mkdir ($dest, 0775);
	}

        $handle = opendir($src);
	if (!$handle) {
		die ("Unable to read $src!");
	}
        while ($element = readdir($handle)) {
		$file = "$src/$element";
                if ( $element == "." || $element == "..") {
                        continue; // skip the current and parent directories
                } elseif (is_file($file)) {
                        copy($file, "$dest/$element") or
			die ("Error copying $src/$element to $dest");
			unlink($file);
                } elseif (is_dir($file)) {
                        move_dir($file, "$dest/$element");
			rmdir($file);
                }
        }
        closedir($handle) ;
}

//----------------------------------------------------------------------------------
/**
 * Move a directory and its content to an other area
 *
 * @author - Hugues Peeters <peeters@ipm.ucl.ac.be>
 * @param  - $origDirPath (String) - the path of the directory to move
 * @param  - $destination (String) - the path of the new area
 * @return - no return !!
 */

function copyDirTo($origDirPath, $destination)
{
	// extract directory name - create it at destination - update destination trail
	$dirName = basename($origDirPath);
	mkdir ($destination."/".$dirName, 0775);
	$destinationTrail = $destination."/".$dirName;

	chdir ($origDirPath) ;
	$handle = opendir($origDirPath);

	while ($element = readdir($handle) )
	{
		if ( $element == "." || $element == "..")
		{
			continue; // skip the current and parent directories
		}
		elseif ( is_file($element) )
		{
			copy($element, $destinationTrail."/".$element);
			unlink($element) ;
		}
		elseif ( is_dir($element) )
		{
			$dirToCopy[] = $origDirPath."/".$element;
		}
	}

	closedir($handle) ;

	if (isset($dirToCopy) and sizeof($dirToCopy) > 0)
	{
		foreach($dirToCopy as $thisDir)
		{
			copyDirTo($thisDir, $destinationTrail);	// recursivity
		}
	}

	rmdir ($origDirPath) ;

}

//------------------------------------------------------------------------------


/* NOTE: These functions batch is used to automatically build HTML forms
 * with a list of the directories contained on the course Directory.
 *
 * From a thechnical point of view, form_dir_lists calls sort_dir wich calls index_dir
 */

/**
 * Indexes all the directories and subdirectories
 * contented in a given directory
 * 
 * @author - Hugues Peeters <peeters@ipm.ucl.ac.be>
 * @param  - path (string) - directory path of the one to index
 * @return - an array containing the path of all the subdirectories
 */

function index_dir($path)
{
	chdir($path);
	$handle = opendir($path);

	// reads directory content end record subdirectoies names in $dir_array
	while ($element = readdir($handle) )
	{
		if ( $element == "." || $element == "..") continue;	// skip the current and parent directories
		if ( is_dir($element) )	 $dirArray[] = $path."/".$element;
	}

	closedir($handle) ;

	// recursive operation if subdirectories exist
	$dirNumber = sizeof ($dirArray);
	if ( $dirNumber > 0 )
	{
		for ($i = 0 ; $i < $dirNnumber ; $i++ )
		{
			$subDirArray = index_dir( $dirArray [$i] ) ;			// function recursivity
			$dirArray  =  array_merge( $dirArray , $subDirArray ) ;	// data merge
		}
	}

	chdir("..") ;

	return $dirArray ;

}


/**
 * Indexes all the directories and subdirectories
 * contented in a given directory, and sort them alphabetically
 *
 * @author - Hugues Peeters <peeters@ipm.ucl.ac.be>
 * @param  - path (string) - directory path of the one to index
 * @return - an array containing the path of all the subdirectories sorted
 *           false, if there is no directory
 * @see    - index_and_sort_dir uses the index_dir() function
 */

function index_and_sort_dir($path)
{
	$dir_list = index_dir($path);

	if ($dir_list)
	{
		sort($dir_list);
		return $dir_list;
	}
	else
	{
		return false;
	}
}


/**
 * build an html form listing all directories of a given directory
 *
 */

function form_dir_list($sourceType, $sourceComponent, $command, $baseWorkDir)
{
	global $PHP_SELF, $langParentDir, $langTo, $langMoveFrom, $langMove;

	$dirList = index_and_sort_dir($baseWorkDir);

	$dialogBox .= "<form action=\"".$PHP_SELF."\" method=\"post\">\n" ;
	$dialogBox .= "<input type=\"hidden\" name=\"".$sourceType."\" value=\"".$sourceComponent."\">\n" ;
	$dialogBox .= " ".$langMoveFrom." ".$sourceComponent." ".$langTo.":\n" ;
	$dialogBox .= "<select name=\"".$command."\">\n" ;
	$dialogBox .= "<option value=\"\" style=\"color:#999999\">".$langParentDir."\n";

	$bwdLen = strlen($baseWorkDir) ;	// base directories lenght, used under

	/* build html form inputs */

	if ($dirList)
	{
		while (list( , $pathValue) = each($dirList) )
		{

			$pathValue = substr ( $pathValue , $bwdLen );		// truncate cunfidential informations confidentielles
			$dirname = basename ($pathValue);					// extract $pathValue directory name du nom

			/* compute de the display tab */

			$tab = "";										// $tab reinitialisation
			$depth = substr_count($pathValue, "/");			// The number of nombre '/' indicates the directory deepness

			for ($h=0; $h<$depth; $h++)
			{
				$tab .= "&nbsp;&nbsp";
			}
			$dialogBox .= "<option value=\"$pathValue\">$tab>$dirname\n";
		}
	}

	$dialogBox .= "</select>\n";
	$dialogBox .= "<input type=\"submit\" value=\"$langMove\">";
	$dialogBox .= "</form>\n";

	return $dialogBox;
}

//------------------------------------------------------------------------------

?>
